/**
 * Live Chat Engine - Modern Implementation
 * PAX Support Pro v5.1.0
 * 
 * Features:
 * - Singleton pattern (prevents duplicate instances)
 * - Modern async/await (no callbacks)
 * - localStorage session persistence
 * - 2-second real-time polling
 * - Unified chat UI integration
 * - Automatic error recovery
 * - Visual state indicators
 * 
 * @package PAX_Support_Pro
 * @version 5.1.0
 */

(function() {
    'use strict';

    // Singleton instance
    let instance = null;

    class LiveChatEngine {
        constructor() {
            // Enforce singleton
            if (instance) {
                console.warn('[PAX Live Chat] Singleton instance already exists');
                return instance;
            }

            // Configuration
            this.config = {
                pollInterval: 2000,        // 2 seconds for real-time feel
                reconnectDelay: 5000,      // 5 seconds before reconnect attempt
                sessionTimeout: 86400000,  // 24 hours in milliseconds
                storageKey: 'pax_livechat_session_v2',
                maxReconnectAttempts: 3
            };

            // State
            this.state = {
                sessionId: null,
                status: null,              // null, 'pending', 'active', 'closed'
                lastMessageId: 0,
                isPolling: false,
                pollTimer: null,
                reconnectAttempts: 0,
                isTyping: false,
                typingTimer: null
            };

            // UI references
            this.ui = {
                chatWindow: null,
                messagesContainer: null,
                inputField: null,
                statusIndicator: null
            };

            // Bind methods to maintain context
            this.poll = this.poll.bind(this);
            this.handleMessage = this.handleMessage.bind(this);
            this.handleError = this.handleError.bind(this);

            instance = this;
        }

        /**
         * Initialize the engine
         */
        async init() {
            this.log('Initializing Live Chat Engine v5.1.0');

            // Validate environment
            if (!window.paxLiveChat) {
                this.error('paxLiveChat configuration not found');
                return false;
            }

            // Load persisted session
            this.loadSession();

            // Bind global events
            this.bindEvents();

            // Expose public API
            window.paxLiveChatEngine = {
                open: () => this.open(),
                close: () => this.close(),
                send: (message) => this.sendMessage(message),
                getState: () => ({ ...this.state })
            };

            this.log('Engine initialized successfully');
            return true;
        }

        /**
         * Bind DOM events
         */
        bindEvents() {
            // Listen for chat open requests
            document.addEventListener('pax-livechat-open', () => this.open());
            
            // Listen for page visibility changes
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this.pausePolling();
                } else if (this.state.sessionId && this.state.status === 'active') {
                    this.resumePolling();
                }
            });

            // Cleanup on page unload
            window.addEventListener('beforeunload', () => {
                this.saveSession();
            });
        }

        /**
         * Open live chat
         */
        async open() {
            this.log('Opening live chat');

            // Check authentication
            if (!window.paxLiveChat.isLoggedIn) {
                this.showLoginPrompt();
                return;
            }

            // Check for existing session
            if (this.state.sessionId) {
                this.log(`Resuming session ${this.state.sessionId}`);
                
                if (this.state.status === 'active') {
                    this.showChatWindow();
                    this.startPolling();
                } else if (this.state.status === 'pending') {
                    this.showWaitingScreen();
                    this.startPolling();
                }
                return;
            }

            // Create new session
            await this.createSession();
        }

        /**
         * Create new chat session
         */
        async createSession() {
            this.log('Creating new session');
            this.showWaitingScreen();

            try {
                const response = await fetch(`${window.paxLiveChat.restUrl}/liveagent/session/create`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.paxLiveChat.nonce
                    },
                    body: JSON.stringify({
                        initial_message: window.paxLiveChat.welcomeMessage || 'Hello, I need help.'
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();

                if (data.success && data.session_id) {
                    this.state.sessionId = data.session_id;
                    this.state.status = data.status || 'pending';
                    this.saveSession();

                    this.log(`Session created: ${this.state.sessionId} (${this.state.status})`);

                    if (this.state.status === 'active') {
                        this.showChatWindow();
                    }

                    this.startPolling();
                } else {
                    throw new Error(data.message || 'Failed to create session');
                }
            } catch (error) {
                this.error('Create session failed:', error);
                this.showError(error.message);
                this.closeWaitingScreen();
            }
        }

        /**
         * Start polling for updates
         */
        startPolling() {
            if (this.state.isPolling) {
                this.log('Polling already active');
                return;
            }

            this.log('Starting poll loop');
            this.state.isPolling = true;
            this.state.reconnectAttempts = 0;
            this.poll();
        }

        /**
         * Stop polling
         */
        stopPolling() {
            this.log('Stopping poll loop');
            this.state.isPolling = false;
            
            if (this.state.pollTimer) {
                clearTimeout(this.state.pollTimer);
                this.state.pollTimer = null;
            }
        }

        /**
         * Pause polling (on page hide)
         */
        pausePolling() {
            if (this.state.pollTimer) {
                clearTimeout(this.state.pollTimer);
                this.state.pollTimer = null;
            }
        }

        /**
         * Resume polling (on page show)
         */
        resumePolling() {
            if (this.state.isPolling && !this.state.pollTimer) {
                this.poll();
            }
        }

        /**
         * Poll for updates
         */
        async poll() {
            if (!this.state.isPolling || !this.state.sessionId) {
                return;
            }

            try {
                const url = `${window.paxLiveChat.restUrl}/liveagent/status/poll?session_id=${this.state.sessionId}&last_message_id=${this.state.lastMessageId}`;
                
                const response = await fetch(url, {
                    headers: {
                        'X-WP-Nonce': window.paxLiveChat.nonce
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const data = await response.json();

                if (data.success) {
                    this.handlePollResponse(data);
                    this.state.reconnectAttempts = 0;
                } else {
                    throw new Error(data.message || 'Poll failed');
                }

            } catch (error) {
                this.handlePollError(error);
            }

            // Schedule next poll
            if (this.state.isPolling) {
                this.state.pollTimer = setTimeout(this.poll, this.config.pollInterval);
            }
        }

        /**
         * Handle poll response
         */
        handlePollResponse(data) {
            // Update session status
            if (data.session_status && data.session_status !== this.state.status) {
                this.log(`Status changed: ${this.state.status} → ${data.session_status}`);
                this.state.status = data.session_status;
                this.saveSession();

                if (data.session_status === 'active' && !this.ui.chatWindow) {
                    this.showChatWindow();
                } else if (data.session_status === 'closed') {
                    this.handleSessionClosed();
                }
            }

            // Handle new messages
            if (data.new_messages && data.new_messages.length > 0) {
                data.new_messages.forEach(msg => {
                    this.handleMessage(msg);
                    if (msg.id > this.state.lastMessageId) {
                        this.state.lastMessageId = msg.id;
                    }
                });
            }

            // Handle typing indicators
            if (data.agent_typing !== undefined) {
                this.updateTypingIndicator(data.agent_typing);
            }
        }

        /**
         * Handle poll error
         */
        handlePollError(error) {
            this.state.reconnectAttempts++;
            
            if (this.state.reconnectAttempts >= this.config.maxReconnectAttempts) {
                this.error('Max reconnect attempts reached');
                this.showError('Connection lost. Please refresh the page.');
                this.stopPolling();
            } else {
                this.log(`Poll error (attempt ${this.state.reconnectAttempts}):`, error.message);
            }
        }

        /**
         * Handle incoming message
         */
        handleMessage(message) {
            // Dispatch event for UI integration
            document.dispatchEvent(new CustomEvent('pax-livechat-message', {
                detail: { message, sessionId: this.state.sessionId }
            }));

            // Legacy DOM manipulation (if UI container exists)
            if (this.ui.messagesContainer) {
                const messageEl = this.createMessageElement(message);
                this.ui.messagesContainer.appendChild(messageEl);
                this.scrollToBottom();
            }

            // Play notification sound for agent messages
            if (message.sender === 'agent' && document.hidden) {
                this.playNotificationSound();
            }
        }

        /**
         * Send message
         */
        async sendMessage(text) {
            if (!text || !text.trim()) {
                return;
            }

            if (!this.state.sessionId || this.state.status !== 'active') {
                this.showError('Chat session is not active');
                return;
            }

            this.log('Sending message');

            // Add message to UI immediately (optimistic update)
            const tempMessage = {
                id: Date.now(),
                sender: 'user',
                message: text,
                created_at: new Date().toISOString(),
                temp: true
            };
            this.handleMessage(tempMessage);

            try {
                const response = await fetch(`${window.paxLiveChat.restUrl}/liveagent/message/send`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.paxLiveChat.nonce
                    },
                    body: JSON.stringify({
                        session_id: this.state.sessionId,
                        message: text
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to send message');
                }

                // Remove temp message and add real one
                const tempEl = this.ui.messagesContainer.querySelector(`[data-temp-id="${tempMessage.id}"]`);
                if (tempEl) {
                    tempEl.remove();
                }

                if (data.message) {
                    this.handleMessage(data.message);
                    this.state.lastMessageId = data.message.id;
                }

            } catch (error) {
                this.error('Send message failed:', error);
                this.showError('Failed to send message');
                
                // Remove temp message
                const tempEl = this.ui.messagesContainer.querySelector(`[data-temp-id="${tempMessage.id}"]`);
                if (tempEl) {
                    tempEl.classList.add('message-error');
                }
            }

            // Clear input
            if (this.ui.inputField) {
                this.ui.inputField.value = '';
            }
        }

        /**
         * Close chat session
         */
        async close() {
            if (!this.state.sessionId) {
                return;
            }

            this.log('Closing session');

            try {
                await fetch(`${window.paxLiveChat.restUrl}/liveagent/session/close`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.paxLiveChat.nonce
                    },
                    body: JSON.stringify({
                        session_id: this.state.sessionId
                    })
                });
            } catch (error) {
                this.error('Close session failed:', error);
            }

            this.handleSessionClosed();
        }

        /**
         * Handle session closed
         */
        handleSessionClosed() {
            this.log('Session closed');
            this.stopPolling();
            this.clearSession();
            this.closeChatWindow();
            this.showToast('Chat session ended', 'info');
        }

        /**
         * Show waiting screen
         */
        showWaitingScreen() {
            this.log('Showing waiting screen');
            
            // Dispatch event for UI integration
            document.dispatchEvent(new CustomEvent('pax-livechat-state', {
                detail: { state: 'waiting', sessionId: this.state.sessionId }
            }));
        }

        /**
         * Close waiting screen
         */
        closeWaitingScreen() {
            this.log('Closing waiting screen');
            
            // Dispatch event for UI integration
            document.dispatchEvent(new CustomEvent('pax-livechat-state', {
                detail: { state: 'closed', sessionId: null }
            }));
        }

        /**
         * Show chat window
         */
        showChatWindow() {
            this.log('Showing chat window');
            
            // Dispatch event for UI integration
            document.dispatchEvent(new CustomEvent('pax-livechat-state', {
                detail: { 
                    state: 'active', 
                    sessionId: this.state.sessionId,
                    status: this.state.status
                }
            }));
        }

        /**
         * Close chat window
         */
        closeChatWindow() {
            this.log('Closing chat window');
            this.ui.chatWindow = null;
            this.ui.messagesContainer = null;
            this.ui.inputField = null;
            
            // Dispatch event for UI integration
            document.dispatchEvent(new CustomEvent('pax-livechat-state', {
                detail: { state: 'closed', sessionId: null }
            }));
        }

        /**
         * Show login prompt
         */
        showLoginPrompt() {
            this.log('Showing login prompt');
            
            // Dispatch event for UI integration
            document.dispatchEvent(new CustomEvent('pax-livechat-login-required', {
                detail: { loginUrl: window.paxLiveChat.loginUrl }
            }));
        }

        /**
         * Show error message
         */
        showError(message) {
            this.error(message);
            this.showToast(message, 'error');
        }

        /**
         * Show toast notification
         */
        showToast(message, type = 'info') {
            this.log(`Toast (${type}): ${message}`);
            
            // Dispatch event for UI integration
            document.dispatchEvent(new CustomEvent('pax-livechat-toast', {
                detail: { message, type }
            }));
        }

        /**
         * Create message element
         */
        createMessageElement(message) {
            const div = document.createElement('div');
            div.className = `chat-message message-${message.sender}`;
            
            if (message.temp) {
                div.setAttribute('data-temp-id', message.id);
            }

            div.innerHTML = `
                <div class="message-content">${this.escapeHtml(message.message)}</div>
                <div class="message-time">${this.formatTime(message.created_at)}</div>
            `;

            return div;
        }

        /**
         * Update typing indicator
         */
        updateTypingIndicator(isTyping) {
            if (!this.ui.statusIndicator) {
                return;
            }

            if (isTyping) {
                this.ui.statusIndicator.textContent = 'Agent is typing...';
                this.ui.statusIndicator.classList.add('typing');
            } else {
                this.ui.statusIndicator.textContent = 'Online';
                this.ui.statusIndicator.classList.remove('typing');
            }
        }

        /**
         * Scroll messages to bottom
         */
        scrollToBottom() {
            if (this.ui.messagesContainer) {
                this.ui.messagesContainer.scrollTop = this.ui.messagesContainer.scrollHeight;
            }
        }

        /**
         * Play notification sound
         */
        playNotificationSound() {
            // Optional: play sound for new messages
        }

        /**
         * Save session to localStorage
         */
        saveSession() {
            if (!this.state.sessionId) {
                return;
            }

            try {
                const data = {
                    sessionId: this.state.sessionId,
                    status: this.state.status,
                    lastMessageId: this.state.lastMessageId,
                    timestamp: Date.now()
                };

                localStorage.setItem(this.config.storageKey, JSON.stringify(data));
                this.log('Session saved to localStorage');
            } catch (error) {
                this.error('Failed to save session:', error);
            }
        }

        /**
         * Load session from localStorage
         */
        loadSession() {
            try {
                const stored = localStorage.getItem(this.config.storageKey);
                
                if (!stored) {
                    return;
                }

                const data = JSON.parse(stored);

                // Check if session is expired
                if (Date.now() - data.timestamp > this.config.sessionTimeout) {
                    this.log('Session expired, clearing');
                    this.clearSession();
                    return;
                }

                this.state.sessionId = data.sessionId;
                this.state.status = data.status;
                this.state.lastMessageId = data.lastMessageId || 0;

                this.log(`Session loaded: ${this.state.sessionId} (${this.state.status})`);
            } catch (error) {
                this.error('Failed to load session:', error);
                this.clearSession();
            }
        }

        /**
         * Clear session
         */
        clearSession() {
            this.state.sessionId = null;
            this.state.status = null;
            this.state.lastMessageId = 0;

            try {
                localStorage.removeItem(this.config.storageKey);
                this.log('Session cleared');
            } catch (error) {
                this.error('Failed to clear session:', error);
            }
        }

        /**
         * Utility: Escape HTML
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * Utility: Format time
         */
        formatTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        /**
         * Logging
         */
        log(...args) {
            if (window.paxLiveChat && window.paxLiveChat.debug) {
                console.log('[PAX Live Chat Engine]', ...args);
            }
        }

        error(...args) {
            console.error('[PAX Live Chat Engine]', ...args);
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            const engine = new LiveChatEngine();
            engine.init();
        });
    } else {
        const engine = new LiveChatEngine();
        engine.init();
    }

})();
