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
                pollInterval: 1000,        // 1 second polling cadence
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
                pollEtag: null,
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
            this.state.pollEtag = null;
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
                const url = new URL(`${window.paxLiveChat.restUrl}/liveagent/status/poll`);
                url.searchParams.set('session_id', this.state.sessionId);
                url.searchParams.set('last_message_id', this.state.lastMessageId);
                url.searchParams.set('_t', Date.now());

                const headers = new Headers({
                    'X-WP-Nonce': window.paxLiveChat.nonce,
                    'Cache-Control': 'no-store'
                });

                if (this.state.pollEtag) {
                    headers.set('If-None-Match', this.state.pollEtag);
                }

                const response = await fetch(url.toString(), {
                    headers,
                    credentials: 'same-origin',
                    cache: 'no-store'
                });

                if (response.status === 304) {
                    this.state.reconnectAttempts = 0;
                    const etag304 = response.headers.get('ETag');
                    if (etag304) {
                        this.state.pollEtag = etag304;
                    }
                } else {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const data = await response.json();

                    const etag = response.headers.get('ETag');
                    if (etag) {
                        this.state.pollEtag = etag;
                    }

                    if (data.success) {
                        this.handlePollResponse(data);
                        this.state.reconnectAttempts = 0;
                    } else {
                        throw new Error(data.message || 'Poll failed');
                    }
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

            if (typeof data.last_message_id !== 'undefined') {
                this.state.lastMessageId = data.last_message_id;
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
            this.state.pollEtag = null;
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
            this.state.pollEtag = null;

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

    class LiveChatUI {
        constructor(engine) {
            this.engine = engine;
            this.state = {
                panelOpen: false,
                waiting: false,
                hasUnread: false,
                intentOpen: false
            };
            this.typingTimer = null;
            this.typingActive = false;
            this.viewportRaf = null;
            this.closedMessagePlaced = false;

            this.build();
            this.attachEngineUI();
            this.bindEvents();
            this.bindEngineEvents();
            this.updateViewportHeight();

            const status = engine.state.status || 'closed';
            if (status === 'active') {
                this.updateStatus(window.paxLiveChat.strings.online || 'Online', 'online');
                this.refreshComposerState('active');
            } else if (status === 'pending') {
                this.updateStatus(window.paxLiveChat.strings.connecting || 'Connecting…', 'connecting');
                this.refreshComposerState('pending');
            } else {
                this.updateStatus(window.paxLiveChat.strings.offline || 'Offline', 'offline');
                this.refreshComposerState('closed');
            }
        }

        build() {
            const position = window.paxLiveChat.buttonPosition || 'bottom-right';
            this.root = document.createElement('div');
            this.root.className = `pax-livechat-root pax-position-${position}`;
            document.body.appendChild(this.root);

            this.launcher = document.createElement('button');
            this.launcher.type = 'button';
            this.launcher.className = 'pax-livechat-launcher';
            this.launcher.setAttribute('aria-label', window.paxLiveChat.strings.liveAgent || 'Live Agent');
            this.launcher.innerHTML = `
                <span class="pax-launcher-sheen"></span>
                <span class="pax-launcher-icon" aria-hidden="true">
                    <span class="dashicons dashicons-format-chat"></span>
                </span>
                <span class="pax-launcher-label">${window.paxLiveChat.strings.liveAgent || 'Live Agent'}</span>
                <span class="pax-launcher-badge" hidden></span>
            `;
            this.root.appendChild(this.launcher);

            this.panel = document.createElement('section');
            this.panel.className = 'pax-livechat-panel';
            this.panel.hidden = true;
            this.panel.setAttribute('aria-hidden', 'true');
            this.panel.setAttribute('role', 'dialog');
            this.panel.setAttribute('aria-label', window.paxLiveChat.strings.liveAgent || 'Live Agent');
            this.root.appendChild(this.panel);

            const header = document.createElement('header');
            header.className = 'pax-livechat-header';
            header.innerHTML = `
                <div class="pax-agent-block">
                    <div class="pax-agent-avatar" aria-hidden="true">
                        <span class="dashicons dashicons-sos"></span>
                    </div>
                    <div class="pax-agent-meta">
                        <span class="pax-agent-name">${window.paxLiveChat.strings.liveAgent || 'Live Agent'}</span>
                        <span class="pax-agent-status pax-agent-status--offline">${window.paxLiveChat.strings.offline || 'Offline'}</span>
                    </div>
                </div>
                <div class="pax-header-actions">
                    <button type="button" class="pax-panel-close" aria-label="${window.paxLiveChat.strings.close || 'Close'}">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
            `;
            this.panel.appendChild(header);

            this.statusIndicator = header.querySelector('.pax-agent-status');
            this.closeButton = header.querySelector('.pax-panel-close');

            const body = document.createElement('div');
            body.className = 'pax-livechat-body';
            this.panel.appendChild(body);

            this.messagesWrapper = document.createElement('div');
            this.messagesWrapper.className = 'pax-livechat-messages chat-messages';
            this.messagesWrapper.setAttribute('role', 'log');
            this.messagesWrapper.setAttribute('aria-live', 'polite');
            body.appendChild(this.messagesWrapper);

            this.emptyState = document.createElement('div');
            this.emptyState.className = 'pax-livechat-empty';
            this.emptyState.innerHTML = `
                <span class="dashicons dashicons-format-status"></span>
                <p>${window.paxLiveChat.strings.startConversation || 'Start the conversation by sending a message.'}</p>
            `;
            this.messagesWrapper.appendChild(this.emptyState);

            this.waitingOverlay = document.createElement('div');
            this.waitingOverlay.className = 'pax-livechat-overlay pax-livechat-overlay--waiting';
            this.waitingOverlay.innerHTML = `
                <div class="pax-spinner" aria-hidden="true"></div>
                <p>${window.paxLiveChat.strings.connectingAgent || 'Connecting you to an agent…'}</p>
            `;
            this.waitingOverlay.setAttribute('hidden', 'hidden');
            body.appendChild(this.waitingOverlay);

            const footer = document.createElement('footer');
            footer.className = 'pax-livechat-footer';
            this.panel.appendChild(footer);

            this.form = document.createElement('form');
            this.form.className = 'pax-livechat-composer';
            footer.appendChild(this.form);

            this.input = document.createElement('textarea');
            this.input.className = 'pax-livechat-input';
            this.input.rows = 1;
            this.input.placeholder = window.paxLiveChat.strings.typeMessage || 'Type your message…';
            this.input.autocomplete = 'off';
            this.input.setAttribute('aria-label', this.input.placeholder);
            this.form.appendChild(this.input);

            this.sendButton = document.createElement('button');
            this.sendButton.type = 'submit';
            this.sendButton.className = 'pax-send-button';
            this.sendButton.innerHTML = `<span class="dashicons dashicons-arrow-right-alt2"></span>`;
            this.form.appendChild(this.sendButton);

            this.endSessionButton = document.createElement('button');
            this.endSessionButton.type = 'button';
            this.endSessionButton.className = 'pax-end-session';
            this.endSessionButton.textContent = window.paxLiveChat.strings.endSession || 'End Session';
            footer.appendChild(this.endSessionButton);

            this.toastContainer = document.createElement('div');
            this.toastContainer.className = 'pax-livechat-toast-container';
            document.body.appendChild(this.toastContainer);
        }

        attachEngineUI() {
            this.engine.ui.chatWindow = this.panel;
            this.engine.ui.messagesContainer = this.messagesWrapper;
            this.engine.ui.inputField = this.input;
            this.engine.ui.statusIndicator = this.statusIndicator;
        }

        bindEvents() {
            this.launcher.addEventListener('click', () => {
                if (!this.state.panelOpen) {
                    this.openPanel({ intent: true });
                } else {
                    this.closePanel({ silent: true });
                }
            });

            this.closeButton.addEventListener('click', () => {
                this.closePanel();
            });

            this.form.addEventListener('submit', (event) => {
                event.preventDefault();
                const value = this.input.value.trim();
                if (!value) {
                    return;
                }
                this.engine.sendMessage(value);
                this.setEmptyState(false);
            });

            this.input.addEventListener('input', () => {
                this.autoResizeInput();
                this.handleTypingInput();
            });

            this.input.addEventListener('focus', () => {
                this.root.classList.add('pax-livechat-keyboard');
                this.engine.scrollToBottom();
            });

            this.input.addEventListener('blur', () => {
                this.root.classList.remove('pax-livechat-keyboard');
                this.sendTyping(false);
            });

            this.endSessionButton.addEventListener('click', () => {
                const confirmText = window.paxLiveChat.strings.confirmEnd || 'End this chat session?';
                if (window.confirm(confirmText)) {
                    this.engine.close();
                }
            });

            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', () => this.updateViewportHeight(), { passive: true });
            }
            window.addEventListener('resize', () => this.updateViewportHeight());
        }

        bindEngineEvents() {
            document.addEventListener('pax-livechat-state', (event) => this.handleStateEvent(event));
            document.addEventListener('pax-livechat-message', (event) => this.handleMessageEvent(event));
            document.addEventListener('pax-livechat-toast', (event) => this.handleToastEvent(event));
            document.addEventListener('pax-livechat-login-required', (event) => this.handleLoginRequired(event));
        }

        openPanel({ intent = false } = {}) {
            this.attachEngineUI();
            this.panel.hidden = false;
            this.panel.setAttribute('aria-hidden', 'false');
            this.root.classList.add('pax-livechat-open');
            this.state.panelOpen = true;
            if (intent) {
                this.state.intentOpen = true;
                this.engine.open();
            }
            this.updateLauncherBadge(false);
            this.autoResizeInput();
            setTimeout(() => this.input.focus(), 50);
            this.engine.scrollToBottom();
        }

        closePanel({ silent = false } = {}) {
            this.panel.hidden = true;
            this.panel.setAttribute('aria-hidden', 'true');
            this.root.classList.remove('pax-livechat-open');
            this.state.panelOpen = false;
            this.sendTyping(false);
            if (!silent) {
                this.state.intentOpen = false;
            }
        }

        ensurePanelVisible() {
            if (!this.state.panelOpen) {
                if (!this.state.intentOpen) {
                    this.state.intentOpen = true;
                }
                this.openPanel();
            }
        }

        setWaiting(show) {
            this.state.waiting = show;
            if (show) {
                this.waitingOverlay.removeAttribute('hidden');
                this.setComposerEnabled(false, window.paxLiveChat.strings.connecting || 'Connecting…');
            } else {
                this.waitingOverlay.setAttribute('hidden', 'hidden');
                this.refreshComposerState(this.engine.state.status || 'closed');
            }
        }

        refreshComposerState(status) {
            let placeholder;
            if (status === 'active') {
                placeholder = window.paxLiveChat.strings.typeMessage || 'Type your message…';
            } else if (status === 'pending') {
                placeholder = window.paxLiveChat.strings.connectingAgent || 'Waiting for an agent…';
            } else {
                placeholder = window.paxLiveChat.strings.sessionEnded || 'Chat session ended.';
            }
            const enabled = status === 'active';
            this.setComposerEnabled(enabled, placeholder);
        }

        setComposerEnabled(enabled, placeholder) {
            if (typeof placeholder === 'string') {
                this.input.placeholder = placeholder;
            }
            this.input.disabled = !enabled;
            this.sendButton.disabled = !enabled;
            this.endSessionButton.hidden = !enabled;
            this.autoResizeInput();
        }

        updateStatus(label, variant = 'offline') {
            this.statusIndicator.textContent = label;
            this.statusIndicator.className = `pax-agent-status pax-agent-status--${variant}`;
        }

        setEmptyState(show) {
            if (show) {
                this.emptyState.removeAttribute('hidden');
            } else {
                this.emptyState.setAttribute('hidden', 'hidden');
            }
        }

        handleStateEvent(event) {
            const detail = event.detail || {};
            const state = detail.state;
            if (state === 'waiting') {
                this.closedMessagePlaced = false;
                this.ensurePanelVisible();
                this.updateStatus(window.paxLiveChat.strings.connecting || 'Connecting…', 'connecting');
                this.setWaiting(true);
            } else if (state === 'active') {
                this.closedMessagePlaced = false;
                if (this.state.intentOpen) {
                    this.openPanel();
                }
                this.updateStatus(window.paxLiveChat.strings.online || 'Online', 'online');
                this.setWaiting(false);
                this.refreshComposerState('active');
                this.setEmptyState(!this.messagesWrapper.querySelector('.chat-message'));
                this.updateLauncherBadge(false);
            } else if (state === 'closed') {
                this.state.intentOpen = false;
                this.updateStatus(window.paxLiveChat.strings.offline || 'Offline', 'offline');
                this.setWaiting(false);
                this.refreshComposerState('closed');
                if (!this.closedMessagePlaced) {
                    this.appendSystemMessage(window.paxLiveChat.strings.sessionEnded || 'Chat session ended.');
                    this.closedMessagePlaced = true;
                }
                this.updateLauncherBadge(false);
            }
            this.attachEngineUI();
        }

        handleMessageEvent(event) {
            const detail = event.detail || {};
            const message = detail.message;
            if (!message) {
                return;
            }
            this.setEmptyState(false);
            if (message.sender === 'agent' && !this.state.panelOpen) {
                this.updateLauncherBadge(true);
            }
        }

        handleToastEvent(event) {
            const detail = event.detail || {};
            if (detail.message) {
                this.showToast(detail.message, detail.type);
            }
        }

        handleLoginRequired(event) {
            const detail = event.detail || {};
            this.showLoginModal(detail);
        }

        appendSystemMessage(text) {
            if (!text) {
                return;
            }
            const wrapper = document.createElement('div');
            wrapper.className = 'chat-message message-system';
            const bubble = document.createElement('div');
            bubble.className = 'message-content';
            bubble.textContent = text;
            wrapper.appendChild(bubble);
            this.messagesWrapper.appendChild(wrapper);
            this.engine.scrollToBottom();
        }

        updateLauncherBadge(show) {
            this.state.hasUnread = show;
            const badge = this.launcher.querySelector('.pax-launcher-badge');
            if (show) {
                this.launcher.classList.add('pax-livechat-launcher--notify');
                badge.removeAttribute('hidden');
                badge.textContent = '●';
            } else {
                this.launcher.classList.remove('pax-livechat-launcher--notify');
                badge.setAttribute('hidden', 'hidden');
                badge.textContent = '';
            }
        }

        showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `pax-livechat-toast pax-livechat-toast--${type || 'info'}`;
            toast.textContent = message;
            this.toastContainer.appendChild(toast);
            requestAnimationFrame(() => toast.classList.add('show'));
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 200);
            }, 4000);
        }

        showLoginModal(detail) {
            if (this.loginOverlay) {
                return;
            }
            const loginUrl = (detail && detail.loginUrl) || window.paxLiveChat.loginUrl || '#';
            this.loginOverlay = document.createElement('div');
            this.loginOverlay.className = 'pax-livechat-login';
            this.loginOverlay.innerHTML = `
                <div class="pax-login-dialog" role="dialog" aria-modal="true">
                    <header>
                        <h3>${window.paxLiveChat.strings.loginRequired || 'Login Required'}</h3>
                        <button type="button" class="pax-login-close" aria-label="${window.paxLiveChat.strings.close || 'Close'}">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </header>
                    <p>${window.paxLiveChat.strings.loginMessage || 'Please log in to start a live chat with our support team.'}</p>
                    <div class="pax-login-actions">
                        <a class="pax-primary" href="${loginUrl}">${window.paxLiveChat.strings.login || 'Log In'}</a>
                        <button type="button" class="pax-secondary pax-login-close">${window.paxLiveChat.strings.cancel || 'Cancel'}</button>
                    </div>
                </div>
            `;
            document.body.appendChild(this.loginOverlay);
            this.loginOverlay.addEventListener('click', (event) => {
                if (event.target === this.loginOverlay || event.target.classList.contains('pax-login-close')) {
                    this.hideLoginModal();
                }
            });
        }

        hideLoginModal() {
            if (!this.loginOverlay) {
                return;
            }
            this.loginOverlay.remove();
            this.loginOverlay = null;
        }

        updateViewportHeight() {
            if (this.viewportRaf) {
                cancelAnimationFrame(this.viewportRaf);
            }
            this.viewportRaf = requestAnimationFrame(() => {
                const viewport = window.visualViewport;
                const height = viewport ? viewport.height : window.innerHeight;
                document.documentElement.style.setProperty('--pax-livechat-vvh', `${Math.round(height)}px`);
            });
        }

        autoResizeInput() {
            this.input.style.height = 'auto';
            this.input.style.height = `${Math.min(this.input.scrollHeight, 160)}px`;
        }

        handleTypingInput() {
            if (!this.engine.state.sessionId || this.input.disabled) {
                return;
            }
            if (!this.typingActive) {
                this.sendTyping(true);
                this.typingActive = true;
            }
            if (this.typingTimer) {
                clearTimeout(this.typingTimer);
            }
            this.typingTimer = setTimeout(() => {
                this.typingActive = false;
                this.sendTyping(false);
            }, 2500);
        }

        async sendTyping(isTyping) {
            if (!this.engine.state.sessionId) {
                return;
            }
            try {
                await fetch(`${window.paxLiveChat.restUrl}/liveagent/status/typing`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.paxLiveChat.nonce,
                        'Cache-Control': 'no-store'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        session_id: this.engine.state.sessionId,
                        is_typing: !!isTyping,
                        sender: 'user'
                    })
                });
            } catch (error) {
                // Typing errors are non-critical
            }
        }
    }

    const bootstrapLiveChat = async () => {
        const engine = new LiveChatEngine();
        const initialised = await engine.init();
        if (initialised === false) {
            return;
        }
        const ui = new LiveChatUI(engine);
        window.paxLiveChatOpen = () => ui.openPanel({ intent: true });
        window.paxLiveChatClose = () => ui.closePanel({ silent: true });
        window.paxLiveChatUI = ui;
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrapLiveChat);
    } else {
        bootstrapLiveChat();
    }

})();
