/**
 * Live Chat Frontend - Full Real-Time Implementation
 * PAX Support Pro v5.7.11
 * 
 * Features:
 * - Real-time messaging with Live Agent Center
 * - Agent online/offline status indicator
 * - Typing indicators
 * - File upload support
 * - Toast notifications
 * - Session management (accept/decline/close)
 * - Mobile responsive with smart keyboard handling
 * - Message interaction bar (Reply/Like/Dislike/Copy)
 * - Login restriction modal
 * - Cloudflare compatible
 */

(function($) {
    'use strict';

    class LiveChatFrontend {
        constructor() {
            this.sessionId = null;
            this.pollInterval = 3000; // 3 seconds for real-time feel
            this.pollTimer = null;
            this.typingTimer = null;
            this.typingTimeout = 3000;
            this.isTyping = false;
            this.lastMessageId = 0;
            this.agentOnline = false;
            this.chatOpen = false;
            this.sessionStatus = null;
            this.shownToasts = new Set(); // Track shown toasts to prevent duplicates
            this.storageKey = 'pax_livechat_session'; // localStorage key for session persistence
            this.keyboardOpen = false;
            this.originalChatHeight = null;
        }

        // Save session to localStorage for persistence
        saveSessionToStorage() {
            if (this.sessionId && window.localStorage) {
                try {
                    window.localStorage.setItem(this.storageKey, JSON.stringify({
                        sessionId: this.sessionId,
                        status: this.sessionStatus,
                        timestamp: Date.now()
                    }));
                } catch (e) {
                    // Silent fail if localStorage is not available
                }
            }
        }

        // Load session from localStorage
        loadSessionFromStorage() {
            if (window.localStorage) {
                try {
                    const stored = window.localStorage.getItem(this.storageKey);
                    if (stored) {
                        const data = JSON.parse(stored);
                        // Only restore if less than 24 hours old
                        if (Date.now() - data.timestamp < 24 * 60 * 60 * 1000) {
                            this.sessionId = data.sessionId;
                            this.sessionStatus = data.status;
                            return true;
                        } else {
                            // Clear old session
                            window.localStorage.removeItem(this.storageKey);
                        }
                    }
                } catch (e) {
                    // Silent fail
                }
            }
            return false;
        }

        // Clear session from localStorage
        clearSessionFromStorage() {
            if (window.localStorage) {
                try {
                    window.localStorage.removeItem(this.storageKey);
                } catch (e) {
                    // Silent fail
                }
            }
        }

        init() {
            if (!window.paxLiveChat) {
                this.debugLog('paxLiveChat not defined', 'error');
                return;
            }

            this.debugLog('Initializing Live Chat');

            // No standalone button - Live Chat is now in the menu
            // Manual activation only - no automatic triggers
            this.bindEvents();
            this.checkAgentStatus();
            // Removed: this.checkExistingSession() - no auto-open on page load
            
            // Check agent status every 30 seconds
            setInterval(() => this.checkAgentStatus(), 30000);
            
            // Expose public method for menu integration
            window.paxLiveChatOpen = () => this.toggleChat();
            
            this.debugLog('Live Chat initialized successfully');
        }

        // Debug logging (only active when WP_DEBUG or debug flag is set)
        debugLog(message, level = 'log') {
            if (window.paxLiveChat && window.paxLiveChat.debug && window.console) {
                const prefix = '[PAX Live Chat]';
                switch(level) {
                    case 'error':
                        console.error(prefix, message);
                        break;
                    case 'warn':
                        console.warn(prefix, message);
                        break;
                    default:
                        console.log(prefix, message);
                }
            }
        }

        bindEvents() {
            const self = this;

            // Close chat window
            $(document).on('click', '.pax-close-chat', function() {
                self.closeChat();
            });

            // Minimize chat
            $(document).on('click', '.pax-minimize-chat', function() {
                self.minimizeChat();
            });

            // Send message
            $(document).on('submit', '#pax-chat-form', function(e) {
                e.preventDefault();
                self.sendMessage();
            });

            // Typing indicator
            $(document).on('input', '#pax-chat-input', function() {
                self.handleTyping();
            });

            // File upload
            $(document).on('change', '#pax-chat-file-input', function(e) {
                self.handleFileUpload(e.target.files[0]);
            });

            // Trigger file input
            $(document).on('click', '.pax-attach-file', function() {
                $('#pax-chat-file-input').click();
            });

            // End session
            $(document).on('click', '.pax-end-session', function() {
                if (confirm(window.paxLiveChat.strings.confirmEnd)) {
                    self.endSession();
                }
            });

            // Cancel waiting
            $(document).on('click', '.pax-cancel-waiting', function() {
                self.cancelWaiting();
            });

            // Mobile keyboard handling
            $(document).on('focus', '#pax-chat-input', function() {
                self.handleKeyboardOpen();
            });

            $(document).on('blur', '#pax-chat-input', function() {
                self.handleKeyboardClose();
            });

            // Visual Viewport API for better mobile keyboard detection
            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', () => {
                    self.handleViewportResize();
                });
            }
        }

        async checkAgentStatus() {
            try {
                const response = await fetch(`${window.paxLiveChat.restUrl}/liveagent/status/agent-online`, {
                    headers: {
                        'X-WP-Nonce': window.paxLiveChat.nonce
                    }
                });

                const data = await response.json();
                this.agentOnline = data.online || false;
                this.updateStatusIndicator();
            } catch (error) {
                console.error('Agent status check error:', error);
                this.agentOnline = false;
                this.updateStatusIndicator();
            }
        }

        async checkExistingSession() {
            if (!window.paxLiveChat.isLoggedIn) return;

            try {
                const response = await fetch(`${window.paxLiveChat.restUrl}/liveagent/session/my-session`, {
                    headers: {
                        'X-WP-Nonce': window.paxLiveChat.nonce
                    }
                });

                const data = await response.json();

                if (data.success && data.session) {
                    const session = data.session;
                    this.sessionId = session.id;
                    this.sessionStatus = session.status;
                    // Do NOT automatically open window - only store session info
                    // Window will be opened by startChat() when user clicks menu
                }
            } catch (error) {
                console.error('Check existing session error:', error);
            }
        }

        toggleChat() {
            if (this.chatOpen) {
                this.minimizeChat();
            } else {
                this.startChat();
            }
        }

        showLoginPrompt() {
            // Create login modal
            const $modal = $(`
                <div class="pax-login-modal-overlay" id="pax-login-modal">
                    <div class="pax-login-modal">
                        <div class="pax-login-header">
                            <h3>${window.paxLiveChat.strings.loginRequired}</h3>
                            <button class="pax-close-login-modal" aria-label="Close">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>
                        <div class="pax-login-content">
                            <p>${window.paxLiveChat.strings.loginMessage}</p>
                            <div class="pax-login-actions">
                                <a href="${window.paxLiveChat.loginUrl}" class="pax-btn-primary">${window.paxLiveChat.strings.login}</a>
                                <a href="${window.paxLiveChat.loginUrl}?action=register" class="pax-btn-secondary">Register</a>
                            </div>
                        </div>
                    </div>
                </div>
            `);

            $('body').append($modal);

            // Close modal on click outside or close button
            $(document).on('click', '.pax-close-login-modal, .pax-login-modal-overlay', function(e) {
                if (e.target === this || $(e.target).hasClass('pax-close-login-modal')) {
                    $('#pax-login-modal').remove();
                }
            });
        }

        async startChat() {
            if (!window.paxLiveChat.isLoggedIn) {
                this.showLoginPrompt();
                return;
            }

            // Try to load session from localStorage first
            if (!this.sessionId) {
                this.loadSessionFromStorage();
            }

            // Check for existing session from server (manual trigger only)
            if (!this.sessionId) {
                await this.checkExistingSession();
            }

            // If session exists after check, just open the window
            if (this.sessionId) {
                this.saveSessionToStorage(); // Save to localStorage
                if (this.sessionStatus === 'active') {
                    this.openChatWindow();
                } else if (this.sessionStatus === 'pending') {
                    this.showWaitingScreen();
                    this.startPolling();
                }
                return;
            }

            // Create new session
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

                // Check for HTTP errors
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || `HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();

                if (data.success && data.session_id) {
                    this.sessionId = data.session_id;
                    this.sessionStatus = data.status || 'pending';
                    this.saveSessionToStorage(); // Save to localStorage
                    
                    // If session is already active, open chat window directly (no toast)
                    if (this.sessionStatus === 'active') {
                        this.openChatWindow();
                    } else {
                        // Session is pending, start polling (no toast, waiting screen is enough)
                        this.startPolling();
                    }
                } else {
                    const errorMsg = data.message || window.paxLiveChat.strings.errorOccurred;
                    this.showToast(errorMsg, 'error');
                    this.closeWaitingScreen();
                    console.error('Session creation failed:', data);
                }
            } catch (error) {
                console.error('Start chat error:', error);
                const errorMsg = error.message || window.paxLiveChat.strings.errorOccurred;
                this.showToast(errorMsg, 'error');
                this.closeWaitingScreen();
            }
        }

        showWaitingScreen() {
            const $waiting = $(`
                <div class="pax-chat-window pax-waiting-screen" id="pax-chat-window">
                    <div class="pax-chat-header">
                        <div class="pax-header-info">
                            <div class="pax-loading-spinner"></div>
                            <span>${window.paxLiveChat.strings.connecting}</span>
                        </div>
                        <button class="pax-close-chat" aria-label="Close">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="pax-waiting-content">
                        <div class="pax-waiting-animation">
                            <div class="pax-pulse-ring"></div>
                            <div class="pax-pulse-ring pax-delay-1"></div>
                            <div class="pax-pulse-ring pax-delay-2"></div>
                        </div>
                        <h3>${window.paxLiveChat.strings.pleaseWait}</h3>
                        <p>${window.paxLiveChat.strings.connectingAgent}</p>
                        <button class="pax-cancel-waiting pax-btn-secondary">${window.paxLiveChat.strings.cancel}</button>
                    </div>
                </div>
            `);

            $('body').append($waiting);
            this.chatOpen = true;
            
            // Auto-close after 60 seconds if no response
            setTimeout(() => {
                if (this.sessionStatus === 'pending') {
                    this.showToast(window.paxLiveChat.strings.noResponse, 'warning');
                    this.cancelWaiting();
                }
            }, 60000);
        }

        closeWaitingScreen() {
            $('#pax-chat-window').remove();
            this.chatOpen = false;
        }

        async cancelWaiting() {
            if (this.sessionId) {
                try {
                    await fetch(`${window.paxLiveChat.restUrl}/liveagent/session/close`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': window.paxLiveChat.nonce
                        },
                        body: JSON.stringify({
                            session_id: this.sessionId
                        })
                    });
                } catch (error) {
                    console.error('Cancel waiting error:', error);
                }
            }

            this.stopPolling();
            this.sessionId = null;
            this.sessionStatus = null;
            this.closeWaitingScreen();
        }

        async openChatWindow() {
            this.closeWaitingScreen();

            const $chatWindow = $(`
                <div class="pax-chat-window" id="pax-chat-window">
                    <div class="pax-chat-header">
                        <div class="pax-header-info">
                            <div class="pax-agent-avatar">
                                <svg viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                </svg>
                            </div>
                            <div>
                                <h4>${window.paxLiveChat.strings.liveAgent}</h4>
                                <span class="pax-agent-status">${window.paxLiveChat.strings.online}</span>
                            </div>
                        </div>
                        <div class="pax-header-actions">
                            <button class="pax-minimize-chat" aria-label="Minimize">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                            </button>
                            <button class="pax-close-chat" aria-label="Close">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="pax-chat-messages" id="pax-chat-messages">
                        <div class="pax-loading-messages">
                            <div class="pax-loading-spinner"></div>
                            <span>${window.paxLiveChat.strings.loadingMessages}</span>
                        </div>
                    </div>
                    <div class="pax-typing-indicator" id="pax-typing-indicator" style="display: none;">
                        <span></span><span></span><span></span>
                        ${window.paxLiveChat.strings.agentTyping}
                    </div>
                    <div class="pax-chat-footer">
                        <form id="pax-chat-form">
                            <input type="file" id="pax-chat-file-input" accept="image/*,.pdf,.doc,.docx" style="display: none;">
                            <button type="button" class="pax-attach-file" aria-label="Attach file">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>
                                </svg>
                            </button>
                            <input type="text" id="pax-chat-input" placeholder="${window.paxLiveChat.strings.typeMessage}" autocomplete="off" required>
                            <button type="submit" class="pax-send-btn" aria-label="Send">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="22" y1="2" x2="11" y2="13"></line>
                                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                </svg>
                            </button>
                        </form>
                        <button class="pax-end-session">${window.paxLiveChat.strings.endSession}</button>
                    </div>
                </div>
            `);

            $('body').append($chatWindow);
            this.chatOpen = true;
            
            // Load messages
            await this.loadMessages();
            
            // Start polling for new messages
            this.startPolling();
            
            // Focus input
            $('#pax-chat-input').focus();
        }

        async loadMessages() {
            if (!this.sessionId) return;

            try {
                const response = await fetch(`${window.paxLiveChat.restUrl}/liveagent/messages/${this.sessionId}`, {
                    headers: {
                        'X-WP-Nonce': window.paxLiveChat.nonce
                    }
                });

                const data = await response.json();

                if (data.success && data.messages) {
                    const $messagesContainer = $('#pax-chat-messages');
                    $messagesContainer.empty();

                    if (data.messages.length === 0) {
                        $messagesContainer.append(`
                            <div class="pax-no-messages">
                                ${window.paxLiveChat.strings.startConversation}
                            </div>
                        `);
                    } else {
                        data.messages.forEach(msg => {
                            this.appendMessage(msg);
                            if (msg.id > this.lastMessageId) {
                                this.lastMessageId = msg.id;
                            }
                        });
                        this.scrollToBottom();
                    }

                    // Mark messages as read
                    this.markMessagesAsRead();
                }
            } catch (error) {
                console.error('Load messages error:', error);
            }
        }

        appendMessage(message) {
            const isUser = message.sender_type === 'user';
            const time = this.formatTime(message.created_at);
            
            let content = '';
            if (message.attachment_url) {
                const isImage = /\.(jpg|jpeg|png|gif|webp)$/i.test(message.attachment_url);
                if (isImage) {
                    content = `<img src="${message.attachment_url}" alt="Attachment" class="pax-message-image">`;
                } else {
                    content = `<a href="${message.attachment_url}" target="_blank" class="pax-message-file">📎 ${message.message || 'File attachment'}</a>`;
                }
            } else {
                content = this.escapeHtml(message.message);
            }

            // Check if message is already liked/disliked
            const likeStatus = this.getMessageLikeStatus(message.id);

            const $message = $(`
                <div class="pax-message ${isUser ? 'pax-message-user' : 'pax-message-agent'}" data-message-id="${message.id}">
                    <div class="pax-message-content">
                        ${content}
                    </div>
                    <div class="pax-message-time">${time}</div>
                    <div class="pax-message-actions">
                        <button class="pax-msg-action pax-msg-reply" data-action="reply" title="Reply">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 14l-4-4 4-4"/>
                                <path d="M5 10h11a4 4 0 0 1 0 8h-1"/>
                            </svg>
                        </button>
                        <button class="pax-msg-action pax-msg-like ${likeStatus === 'liked' ? 'active' : ''}" data-action="like" title="Like">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/>
                            </svg>
                        </button>
                        <button class="pax-msg-action pax-msg-dislike ${likeStatus === 'disliked' ? 'active' : ''}" data-action="dislike" title="Dislike">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"/>
                            </svg>
                        </button>
                        <button class="pax-msg-action pax-msg-copy" data-action="copy" title="Copy">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                            </svg>
                        </button>
                    </div>
                </div>
            `);

            $('#pax-chat-messages').append($message);
            this.bindMessageActions($message);
        }

        bindMessageActions($message) {
            const self = this;
            const messageId = $message.data('message-id');
            const messageText = $message.find('.pax-message-content').text().trim();

            // Reply action
            $message.find('.pax-msg-reply').on('click', function() {
                const $input = $('#pax-chat-input');
                $input.val(`@${messageText.substring(0, 30)}... `).focus();
                self.showActionTooltip($(this), 'Reply');
            });

            // Like action
            $message.find('.pax-msg-like').on('click', function() {
                const $btn = $(this);
                const currentStatus = self.getMessageLikeStatus(messageId);
                
                if (currentStatus === 'liked') {
                    // Remove like
                    self.setMessageLikeStatus(messageId, null);
                    $btn.removeClass('active');
                    self.showActionTooltip($btn, 'Like removed');
                } else {
                    // Add like
                    self.setMessageLikeStatus(messageId, 'liked');
                    $btn.addClass('active');
                    $message.find('.pax-msg-dislike').removeClass('active');
                    self.showActionTooltip($btn, 'Liked!');
                }
            });

            // Dislike action
            $message.find('.pax-msg-dislike').on('click', function() {
                const $btn = $(this);
                const currentStatus = self.getMessageLikeStatus(messageId);
                
                if (currentStatus === 'disliked') {
                    // Remove dislike
                    self.setMessageLikeStatus(messageId, null);
                    $btn.removeClass('active');
                    self.showActionTooltip($btn, 'Dislike removed');
                } else {
                    // Add dislike
                    self.setMessageLikeStatus(messageId, 'disliked');
                    $btn.addClass('active');
                    $message.find('.pax-msg-like').removeClass('active');
                    self.showActionTooltip($btn, 'Disliked!');
                }
            });

            // Copy action
            $message.find('.pax-msg-copy').on('click', function() {
                const $btn = $(this);
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(messageText).then(() => {
                        self.showActionTooltip($btn, 'Copied!');
                    }).catch(() => {
                        self.fallbackCopyText(messageText);
                        self.showActionTooltip($btn, 'Copied!');
                    });
                } else {
                    self.fallbackCopyText(messageText);
                    self.showActionTooltip($btn, 'Copied!');
                }
            });
        }

        getMessageLikeStatus(messageId) {
            if (!window.localStorage) return null;
            try {
                const key = `pax_msg_like_${messageId}`;
                return window.localStorage.getItem(key);
            } catch (e) {
                return null;
            }
        }

        setMessageLikeStatus(messageId, status) {
            if (!window.localStorage) return;
            try {
                const key = `pax_msg_like_${messageId}`;
                if (status) {
                    window.localStorage.setItem(key, status);
                } else {
                    window.localStorage.removeItem(key);
                }
            } catch (e) {
                // Silent fail
            }
        }

        showActionTooltip($button, text) {
            const $tooltip = $('<div class="pax-action-tooltip"></div>').text(text);
            $button.append($tooltip);
            
            setTimeout(() => {
                $tooltip.addClass('show');
            }, 10);
            
            setTimeout(() => {
                $tooltip.removeClass('show');
                setTimeout(() => $tooltip.remove(), 300);
            }, 1500);
        }

        fallbackCopyText(text) {
            const $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
        }

        async sendMessage() {
            const $input = $('#pax-chat-input');
            const message = $input.val().trim();

            if (!message || !this.sessionId) return;

            try {
                const response = await fetch(`${window.paxLiveChat.restUrl}/liveagent/message/send`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.paxLiveChat.nonce
                    },
                    body: JSON.stringify({
                        session_id: this.sessionId,
                        message: message,
                        sender_type: 'user'
                    })
                });

                const data = await response.json();

                if (data.success && data.message) {
                    this.appendMessage(data.message);
                    this.scrollToBottom();
                    $input.val('');
                    
                    if (data.message.id > this.lastMessageId) {
                        this.lastMessageId = data.message.id;
                    }
                } else {
                    this.showToast(data.message || window.paxLiveChat.strings.errorOccurred, 'error');
                }
            } catch (error) {
                console.error('Send message error:', error);
                this.showToast(window.paxLiveChat.strings.errorOccurred, 'error');
            }
        }

        async handleFileUpload(file) {
            if (!file || !this.sessionId) return;

            // Validate file size (10MB max)
            if (file.size > 10 * 1024 * 1024) {
                this.showToast(window.paxLiveChat.strings.fileTooLarge, 'error');
                return;
            }

            const formData = new FormData();
            formData.append('file', file);
            formData.append('session_id', this.sessionId);

            try {
                this.showToast(window.paxLiveChat.strings.uploading, 'info');

                const response = await fetch(`${window.paxLiveChat.restUrl}/liveagent/file/upload`, {
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': window.paxLiveChat.nonce
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success && data.message) {
                    this.appendMessage(data.message);
                    this.scrollToBottom();
                    this.showToast(window.paxLiveChat.strings.fileUploaded, 'success');
                    
                    if (data.message.id > this.lastMessageId) {
                        this.lastMessageId = data.message.id;
                    }
                } else {
                    this.showToast(data.message || window.paxLiveChat.strings.uploadFailed, 'error');
                }
            } catch (error) {
                console.error('File upload error:', error);
                this.showToast(window.paxLiveChat.strings.uploadFailed, 'error');
            }

            // Reset file input
            $('#pax-chat-file-input').val('');
        }

        handleTyping() {
            if (!this.sessionId) return;

            clearTimeout(this.typingTimer);

            if (!this.isTyping) {
                this.isTyping = true;
                this.sendTypingStatus(true);
            }

            this.typingTimer = setTimeout(() => {
                this.isTyping = false;
                this.sendTypingStatus(false);
            }, this.typingTimeout);
        }

        async sendTypingStatus(isTyping) {
            if (!this.sessionId) return;

            try {
                await fetch(`${window.paxLiveChat.restUrl}/liveagent/status/typing`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.paxLiveChat.nonce
                    },
                    body: JSON.stringify({
                        session_id: this.sessionId,
                        is_typing: isTyping,
                        user_type: 'user'
                    })
                });
            } catch (error) {
                console.error('Typing status error:', error);
            }
        }

        startPolling() {
            this.stopPolling();
            this.pollTimer = setInterval(() => this.pollUpdates(), this.pollInterval);
        }

        stopPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        }

        async pollUpdates() {
            if (!this.sessionId) return;

            try {
                const url = new URL(`${window.paxLiveChat.restUrl}/liveagent/status/poll`);
                url.searchParams.append('session_id', this.sessionId);
                url.searchParams.append('last_message_id', this.lastMessageId);

                const response = await fetch(url, {
                    headers: {
                        'X-WP-Nonce': window.paxLiveChat.nonce
                    }
                });

                const data = await response.json();

                if (data.success) {
                    // Check session status
                    if (data.session_status && data.session_status !== this.sessionStatus) {
                        this.sessionStatus = data.session_status;
                        this.saveSessionToStorage(); // Update localStorage with new status
                        
                        if (data.session_status === 'active' && $('.pax-waiting-screen').length) {
                            this.showToast(window.paxLiveChat.strings.agentJoined, 'success');
                            this.openChatWindow();
                        } else if (data.session_status === 'declined') {
                            this.showToast(window.paxLiveChat.strings.requestDeclined, 'warning');
                            this.clearSessionFromStorage(); // Clear on decline
                            this.closeChat();
                        } else if (data.session_status === 'closed') {
                            this.showToast(window.paxLiveChat.strings.sessionEnded, 'info');
                            this.clearSessionFromStorage(); // Clear on close
                            this.closeChat();
                        }
                    }

                    // New messages
                    if (data.new_messages && data.new_messages.length > 0) {
                        data.new_messages.forEach(msg => {
                            this.appendMessage(msg);
                            if (msg.id > this.lastMessageId) {
                                this.lastMessageId = msg.id;
                            }
                            
                            // Show toast for new agent messages
                            if (msg.sender_type === 'agent' && !this.chatOpen) {
                                this.showToast(window.paxLiveChat.strings.newMessage, 'info');
                            }
                        });
                        this.scrollToBottom();
                        this.markMessagesAsRead();
                    }

                    // Agent typing
                    if (data.agent_typing !== undefined) {
                        if (data.agent_typing) {
                            $('#pax-typing-indicator').fadeIn(200);
                        } else {
                            $('#pax-typing-indicator').fadeOut(200);
                        }
                    }
                }
            } catch (error) {
                console.error('Poll updates error:', error);
            }
        }

        async markMessagesAsRead() {
            if (!this.sessionId) return;

            try {
                await fetch(`${window.paxLiveChat.restUrl}/liveagent/message/mark-read`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.paxLiveChat.nonce
                    },
                    body: JSON.stringify({
                        session_id: this.sessionId,
                        user_type: 'user'
                    })
                });
            } catch (error) {
                console.error('Mark read error:', error);
            }
        }

        async endSession() {
            if (!this.sessionId) return;

            try {
                const response = await fetch(`${window.paxLiveChat.restUrl}/liveagent/session/close`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.paxLiveChat.nonce
                    },
                    body: JSON.stringify({
                        session_id: this.sessionId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.showToast(window.paxLiveChat.strings.sessionEnded, 'success');
                    this.clearSessionFromStorage(); // Clear from localStorage
                    this.closeChat();
                }
            } catch (error) {
                console.error('End session error:', error);
            }
        }

        closeChat() {
            this.stopPolling();
            $('#pax-chat-window').remove();
            this.chatOpen = false;
            this.sessionId = null;
            this.sessionStatus = null;
            this.lastMessageId = 0;
        }

        minimizeChat() {
            $('#pax-chat-window').addClass('minimized');
            this.chatOpen = false;
        }

        showLoginPrompt() {
            const $prompt = $(`
                <div class="pax-chat-modal" id="pax-login-modal">
                    <div class="pax-modal-content">
                        <h3>${window.paxLiveChat.strings.loginRequired}</h3>
                        <p>${window.paxLiveChat.strings.loginMessage}</p>
                        <div class="pax-modal-actions">
                            <a href="${window.paxLiveChat.loginUrl}" class="pax-btn-primary">${window.paxLiveChat.strings.login}</a>
                            <button class="pax-btn-secondary pax-close-modal">${window.paxLiveChat.strings.cancel}</button>
                        </div>
                    </div>
                </div>
            `);

            $('body').append($prompt);

            $(document).on('click', '.pax-close-modal, .pax-chat-modal', function(e) {
                if (e.target === this) {
                    $('#pax-login-modal').remove();
                }
            });
        }

        showToast(message, type = 'info') {
            // Prevent duplicate toasts within 5 seconds
            const toastKey = `${type}:${message}`;
            if (this.shownToasts.has(toastKey)) {
                return;
            }
            
            this.shownToasts.add(toastKey);
            setTimeout(() => this.shownToasts.delete(toastKey), 5000);

            const icons = {
                success: '✓',
                error: '✕',
                warning: '⚠',
                info: 'ℹ'
            };

            const $toast = $(`
                <div class="pax-toast pax-toast-${type}">
                    <span class="pax-toast-icon">${icons[type]}</span>
                    <span class="pax-toast-message">${message}</span>
                </div>
            `);

            $('body').append($toast);

            setTimeout(() => {
                $toast.addClass('show');
            }, 100);

            setTimeout(() => {
                $toast.removeClass('show');
                setTimeout(() => $toast.remove(), 300);
            }, 3000);
        }

        scrollToBottom() {
            const $messages = $('#pax-chat-messages');
            $messages.scrollTop($messages[0].scrollHeight);
        }

        formatTime(timestamp) {
            const date = new Date(timestamp);
            const hours = date.getHours().toString().padStart(2, '0');
            const minutes = date.getMinutes().toString().padStart(2, '0');
            return `${hours}:${minutes}`;
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Mobile keyboard handling methods
        handleKeyboardOpen() {
            if (this.keyboardOpen) return;
            
            this.keyboardOpen = true;
            const $chatWindow = $('#pax-chat-window');
            
            if ($chatWindow.length) {
                // Store original height
                if (!this.originalChatHeight) {
                    this.originalChatHeight = $chatWindow.outerHeight();
                }
                
                // Shrink to 50% of viewport height
                const targetHeight = window.innerHeight * 0.5;
                $chatWindow.addClass('pax-keyboard-active');
                $chatWindow.css('max-height', `${targetHeight}px`);
                
                // Scroll to bottom to keep input visible
                setTimeout(() => {
                    this.scrollToBottom();
                }, 100);
            }
        }

        handleKeyboardClose() {
            if (!this.keyboardOpen) return;
            
            this.keyboardOpen = false;
            const $chatWindow = $('#pax-chat-window');
            
            if ($chatWindow.length) {
                // Restore original height
                $chatWindow.removeClass('pax-keyboard-active');
                $chatWindow.css('max-height', '');
            }
        }

        handleViewportResize() {
            if (!window.visualViewport) return;
            
            const $chatWindow = $('#pax-chat-window');
            if (!$chatWindow.length) return;
            
            const viewportHeight = window.visualViewport.height;
            const windowHeight = window.innerHeight;
            
            // Keyboard is open if viewport height is significantly smaller than window height
            const keyboardHeight = windowHeight - viewportHeight;
            
            if (keyboardHeight > 150) {
                // Keyboard is open
                if (!this.keyboardOpen) {
                    this.keyboardOpen = true;
                    const targetHeight = viewportHeight * 0.5;
                    $chatWindow.addClass('pax-keyboard-active');
                    $chatWindow.css('max-height', `${targetHeight}px`);
                    
                    setTimeout(() => {
                        this.scrollToBottom();
                    }, 100);
                }
            } else {
                // Keyboard is closed
                if (this.keyboardOpen) {
                    this.keyboardOpen = false;
                    $chatWindow.removeClass('pax-keyboard-active');
                    $chatWindow.css('max-height', '');
                }
            }
        }
    }

    // Singleton instance to prevent duplicates
    let liveChatInstance = null;

    // Initialize when DOM is ready (global, single instance)
    $(document).ready(function() {
        // Prevent duplicate initialization
        if (liveChatInstance) {
            if (window.console && window.console.warn) {
                console.warn('[PAX Live Chat] Already initialized, skipping duplicate');
            }
            return;
        }

        if (window.paxLiveChat && window.paxLiveChat.enabled) {
            liveChatInstance = new LiveChatFrontend();
            liveChatInstance.init();
            
            // Store instance globally for debugging
            window.paxLiveChatInstance = liveChatInstance;
            
            if (window.console && window.console.log && window.paxLiveChat.debug) {
                console.log('[PAX Live Chat] Initialized successfully');
            }
        }
    });

})(jQuery);
