/**
 * Live Agent Frontend JavaScript
 * User-side chat interface
 */

(function($) {
    'use strict';

    class LiveAgentFrontend {
        constructor() {
            this.sessionId = null;
            this.pollInterval = 10000;
            this.pollTimer = null;
            this.waitTimeout = 60000;
            this.isWaiting = false;
            this.lastUpdate = null;
        }

        init() {
            this.createButton();
            this.bindEvents();
            this.checkExistingSession();
        }

        createButton() {
            const position = window.paxLiveAgent.buttonPosition;
            const text = window.paxLiveAgent.buttonText;

            const $button = $(`
                <button class="pax-liveagent-button pax-position-${position}" id="pax-liveagent-btn">
                    <span class="dashicons dashicons-format-chat"></span>
                    <span class="pax-btn-text">${text}</span>
                </button>
            `);

            $('body').append($button);
        }

        bindEvents() {
            const self = this;

            $(document).on('click', '#pax-liveagent-btn', function() {
                self.startChat();
            });

            $(document).on('click', '.pax-cancel-request', function() {
                self.cancelRequest();
            });

            $(document).on('click', '.pax-close-chat-window', function() {
                self.closeChatWindow();
            });

            $(document).on('submit', '#pax-user-chat-form', function(e) {
                e.preventDefault();
                self.sendMessage();
            });

            $(document).on('input', '#pax-user-message-input', function() {
                self.handleTyping();
            });
        }

        async checkExistingSession() {
            if (!window.paxLiveAgent.isLoggedIn) return;

            try {
                const url = new URL(`${window.paxLiveAgent.restUrl}/liveagent/sessions/list`);
                url.searchParams.append('status', 'active');
                url.searchParams.append('limit', 1);

                const response = await fetch(url, {
                    headers: {
                        'X-WP-Nonce': window.paxLiveAgent.nonce
                    }
                });

                const data = await response.json();

                if (data.success && data.sessions.length > 0) {
                    const session = data.sessions[0];
                    if (session.user_id == window.paxLiveAgent.userId) {
                        this.sessionId = session.id;
                        this.openChatWindow();
                    }
                }
            } catch (error) {
                console.error('Check session error:', error);
            }
        }

        async startChat() {
            if (!window.paxLiveAgent.isLoggedIn) {
                this.showLoginPrompt();
                return;
            }

            this.showWaitingScreen();

            try {
                const response = await fetch(`${window.paxLiveAgent.restUrl}/liveagent/session/create`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.paxLiveAgent.nonce
                    },
                    body: JSON.stringify({
                        user_id: window.paxLiveAgent.userId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.sessionId = data.session_id;
                    this.waitForAgent();
                } else {
                    alert('Failed to start chat');
                    this.hideWaitingScreen();
                }
            } catch (error) {
                console.error('Start chat error:', error);
                alert('Error starting chat');
                this.hideWaitingScreen();
            }
        }

        showLoginPrompt() {
            const $prompt = $(`
                <div class="pax-liveagent-modal" id="pax-login-prompt">
                    <div class="pax-modal-content">
                        <div class="pax-modal-header">
                            <h3>${window.paxLiveAgent.strings.loginRequired}</h3>
                            <button class="pax-modal-close">&times;</button>
                        </div>
                        <div class="pax-modal-body">
                            <p>You need to be logged in to start a live chat with our support team.</p>
                            <a href="${window.paxLiveAgent.loginUrl}" class="pax-btn-primary">
                                ${window.paxLiveAgent.strings.login}
                            </a>
                        </div>
                    </div>
                </div>
            `);

            $('body').append($prompt);

            $(document).on('click', '.pax-modal-close, .pax-liveagent-modal', function(e) {
                if (e.target === this) {
                    $('#pax-login-prompt').remove();
                }
            });
        }

        showWaitingScreen() {
            this.isWaiting = true;

            const $waiting = $(`
                <div class="pax-liveagent-modal" id="pax-waiting-screen">
                    <div class="pax-modal-content pax-waiting-content">
                        <div class="pax-spinner"></div>
                        <h3>${window.paxLiveAgent.strings.connecting}</h3>
                        <p>${window.paxLiveAgent.strings.pleaseWait}</p>
                        <button class="pax-btn-secondary pax-cancel-request">
                            ${window.paxLiveAgent.strings.cancel}
                        </button>
                    </div>
                </div>
            `);

            $('body').append($waiting);
        }

        hideWaitingScreen() {
            this.isWaiting = false;
            $('#pax-waiting-screen').remove();
        }

        waitForAgent() {
            const self = this;
            const startTime = Date.now();

            const checkInterval = setInterval(async function() {
                try {
                    const response = await fetch(
                        `${window.paxLiveAgent.restUrl}/liveagent/session/${self.sessionId}`,
                        {
                            headers: {
                                'X-WP-Nonce': window.paxLiveAgent.nonce
                            }
                        }
                    );

                    const data = await response.json();

                    if (data.success) {
                        if (data.session.status === 'active') {
                            clearInterval(checkInterval);
                            self.hideWaitingScreen();
                            self.openChatWindow();
                        } else if (data.session.status === 'closed') {
                            clearInterval(checkInterval);
                            self.hideWaitingScreen();
                            self.showDeclinedMessage();
                        } else if (Date.now() - startTime > self.waitTimeout) {
                            clearInterval(checkInterval);
                            self.hideWaitingScreen();
                            self.showTimeoutMessage();
                        }
                    }
                } catch (error) {
                    console.error('Wait check error:', error);
                }
            }, 3000);
        }

        cancelRequest() {
            if (this.sessionId) {
                // Close the session
                fetch(`${window.paxLiveAgent.restUrl}/liveagent/session/close`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.paxLiveAgent.nonce
                    },
                    body: JSON.stringify({
                        session_id: this.sessionId
                    })
                });
            }

            this.hideWaitingScreen();
            this.sessionId = null;
        }

        openChatWindow() {
            const $window = $(`
                <div class="pax-chat-window" id="pax-chat-window">
                    <div class="pax-chat-header">
                        <div class="pax-chat-title">
                            <span class="dashicons dashicons-format-chat"></span>
                            <span>Support Agent</span>
                        </div>
                        <button class="pax-close-chat-window">&times;</button>
                    </div>
                    <div class="pax-chat-messages" id="pax-user-chat-messages"></div>
                    <div class="pax-chat-input">
                        <form id="pax-user-chat-form">
                            <input type="text" 
                                   id="pax-user-message-input" 
                                   placeholder="${window.paxLiveAgent.strings.typeMessage}"
                                   autocomplete="off">
                            <button type="submit" class="pax-send-btn">
                                <span class="dashicons dashicons-arrow-right-alt2"></span>
                            </button>
                        </form>
                    </div>
                </div>
            `);

            $('body').append($window);
            $('#pax-liveagent-btn').hide();

            this.loadMessages();
            this.startPolling();
        }

        closeChatWindow() {
            $('#pax-chat-window').remove();
            $('#pax-liveagent-btn').show();
            
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
            }
        }

        async loadMessages() {
            try {
                const response = await fetch(
                    `${window.paxLiveAgent.restUrl}/liveagent/messages/${this.sessionId}`,
                    {
                        headers: {
                            'X-WP-Nonce': window.paxLiveAgent.nonce
                        }
                    }
                );

                const data = await response.json();

                if (data.success && data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        this.appendMessage(msg);
                    });
                } else {
                    this.showWelcomeMessage();
                }

                this.markMessagesRead();
            } catch (error) {
                console.error('Load messages error:', error);
            }
        }

        showWelcomeMessage() {
            const $welcome = $(`
                <div class="pax-system-message">
                    ${window.paxLiveAgent.welcomeMessage}
                </div>
            `);

            $('#pax-user-chat-messages').append($welcome);
        }

        startPolling() {
            const self = this;

            this.pollTimer = setInterval(function() {
                self.pollUpdates();
            }, this.pollInterval);
        }

        async pollUpdates() {
            try {
                const url = new URL(`${window.paxLiveAgent.restUrl}/liveagent/status/poll`);
                url.searchParams.append('session_id', this.sessionId);
                if (this.lastUpdate) {
                    url.searchParams.append('last_update', this.lastUpdate);
                }
                url.searchParams.append('_t', Date.now());

                const response = await fetch(url, {
                    headers: {
                        'X-WP-Nonce': window.paxLiveAgent.nonce
                    }
                });

                const data = await response.json();

                if (data.success && data.has_updates) {
                    if (data.new_messages && data.new_messages.length > 0) {
                        data.new_messages.forEach(msg => {
                            this.appendMessage(msg);
                        });
                        this.markMessagesRead();
                    }

                    if (data.session_status === 'closed') {
                        this.showChatEnded();
                    }
                }

                this.lastUpdate = data.last_activity || new Date().toISOString();
            } catch (error) {
                console.error('Poll error:', error);
            }
        }

        async sendMessage() {
            const $input = $('#pax-user-message-input');
            const message = $input.val().trim();

            if (!message) return;

            try {
                const response = await fetch(`${window.paxLiveAgent.restUrl}/liveagent/message/send`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.paxLiveAgent.nonce
                    },
                    body: JSON.stringify({
                        session_id: this.sessionId,
                        message: message,
                        sender: 'user'
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.appendMessage(data.message);
                    $input.val('');
                }
            } catch (error) {
                console.error('Send error:', error);
            }
        }

        handleTyping() {
            // Send typing indicator
            fetch(`${window.paxLiveAgent.restUrl}/liveagent/status/typing`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.paxLiveAgent.nonce
                },
                body: JSON.stringify({
                    session_id: this.sessionId,
                    is_typing: true,
                    sender: 'user'
                })
            });
        }

        appendMessage(message) {
            const $container = $('#pax-user-chat-messages');
            const isUser = message.sender === 'user';
            const time = new Date(message.timestamp).toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit' 
            });

            const $message = $(`
                <div class="pax-message ${isUser ? 'pax-message-user' : 'pax-message-agent'}">
                    <div class="pax-message-bubble">
                        <div class="pax-message-text">${this.escapeHtml(message.message)}</div>
                        <div class="pax-message-time">${time}</div>
                    </div>
                </div>
            `);

            $container.append($message);
            this.scrollToBottom();
        }

        async markMessagesRead() {
            try {
                await fetch(`${window.paxLiveAgent.restUrl}/liveagent/message/mark-read`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.paxLiveAgent.nonce
                    },
                    body: JSON.stringify({
                        session_id: this.sessionId,
                        reader_type: 'user'
                    })
                });
            } catch (error) {
                console.error('Mark read error:', error);
            }
        }

        showDeclinedMessage() {
            const $message = $(`
                <div class="pax-liveagent-modal">
                    <div class="pax-modal-content">
                        <h3>${window.paxLiveAgent.strings.allBusy}</h3>
                        <p>${window.paxLiveAgent.strings.tryAgain}</p>
                        <button class="pax-btn-primary" onclick="location.reload()">OK</button>
                    </div>
                </div>
            `);

            $('body').append($message);
        }

        showTimeoutMessage() {
            const $message = $(`
                <div class="pax-liveagent-modal">
                    <div class="pax-modal-content">
                        <h3>${window.paxLiveAgent.strings.noResponse}</h3>
                        <p>${window.paxLiveAgent.strings.submitTicket}</p>
                        <button class="pax-btn-primary" onclick="location.reload()">OK</button>
                    </div>
                </div>
            `);

            $('body').append($message);
        }

        showChatEnded() {
            const $notice = $(`
                <div class="pax-system-message">
                    ${window.paxLiveAgent.strings.chatEnded}
                </div>
            `);

            $('#pax-user-chat-messages').append($notice);
            $('#pax-user-chat-form').hide();
            
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
            }
        }

        scrollToBottom() {
            const $container = $('#pax-user-chat-messages');
            if ($container.length) {
                $container.scrollTop($container[0].scrollHeight);
            }
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Initialize
    $(document).ready(function() {
        if (typeof window.paxLiveAgent !== 'undefined') {
            window.liveAgentFrontend = new LiveAgentFrontend();
            window.liveAgentFrontend.init();
        }
    });

})(jQuery);
