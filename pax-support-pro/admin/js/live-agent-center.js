/**
 * Live Agent Center JavaScript
 * Real-time chat management for agents
 */

(function($) {
    'use strict';

    class LiveAgentCenter {
        constructor() {
            this.sessionId = window.paxLiveAgent?.selectedSessionId || null;
            this.pollInterval = window.paxLiveAgent?.refreshInterval || 15000;
            this.typingTimeout = null;
            this.lastUpdate = null;
            this.pollTimer = null;
            this.isTyping = false;
            this.lastMessageId = window.paxLiveAgent?.lastMessageId || null;
        }

        init() {
            this.bindEvents();
            this.startPolling();
            this.initAutoScroll();
            this.startHeartbeat();
            this.captureInitialLastMessage();
            
            if (this.sessionId) {
                this.markMessagesRead();
            }
        }

        startHeartbeat() {
            // Update agent's last_seen every 60 seconds
            this.updateLastSeen();
            setInterval(() => this.updateLastSeen(), 60000);
        }

        updateLastSeen() {
            // Update user meta to indicate agent is online
            const userId = window.paxLiveAgent?.userId;
            if (userId) {
                const timestamp = Math.floor(Date.now() / 1000);
                // Store in localStorage as backup
                localStorage.setItem('pax_agent_last_seen', timestamp);
                
                // Update via AJAX
                $.ajax({
                    url: window.paxLiveAgent?.ajaxUrl || ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'pax_update_agent_status',
                        nonce: window.paxLiveAgent?.nonce,
                        timestamp: timestamp
                    }
                });
            }
        }

        bindEvents() {
            const self = this;

            // Session selection
            $(document).on('click', '.pax-session-item', function() {
                const sessionId = $(this).data('session-id');
                window.location.href = `?page=pax-live-agent-center&session=${sessionId}`;
            });

            // Refresh sessions
            $('.pax-refresh-sessions').on('click', function() {
                self.refreshSessions();
            });

            // Accept session
            $(document).on('click', '.pax-accept-chat', function() {
                const sessionId = $(this).data('session-id');
                self.acceptSession(sessionId);
            });

            // Decline session
            $(document).on('click', '.pax-decline-chat', function() {
                const sessionId = $(this).data('session-id');
                if (confirm(window.paxLiveAgent.strings.confirmDecline)) {
                    self.declineSession(sessionId);
                }
            });

            // Close session
            $(document).on('click', '.pax-close-chat', function() {
                const sessionId = $(this).data('session-id');
                if (confirm(window.paxLiveAgent.strings.confirmClose)) {
                    self.closeSession(sessionId);
                }
            });

            // Convert to ticket
            $(document).on('click', '.pax-convert-ticket', function() {
                const sessionId = $(this).data('session-id');
                self.convertToTicket(sessionId);
            });

            // Export chat
            $(document).on('click', '.pax-export-chat', function() {
                const sessionId = $(this).data('session-id');
                self.exportChat(sessionId);
            });

            // Send message
            $('#pax-chat-form').on('submit', function(e) {
                e.preventDefault();
                self.sendMessage();
            });

            // Typing indicator
            $('#pax-message-input').on('input', function() {
                self.handleTyping();
            });

            // Auto-resize textarea
            $('#pax-message-input').on('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });

            // File upload
            $('.pax-attach-button').on('click', function() {
                $('#pax-file-input').click();
            });

            $('#pax-file-input').on('change', function() {
                if (this.files.length > 0) {
                    self.uploadFile(this.files[0]);
                }
            });

            // Emoji button (placeholder)
            $('.pax-emoji-button').on('click', function() {
                // Future: emoji picker
                alert('Emoji picker coming soon!');
            });
        }

        startPolling() {
            const self = this;
            
            this.pollTimer = setInterval(function() {
                self.pollUpdates();
                self.refreshSessionsList();
            }, this.pollInterval);

            // Initial poll
            if (this.sessionId) {
                this.pollUpdates();
            }
        }

        async pollUpdates() {
            if (!this.sessionId) return;

            try {
                const url = new URL(`${window.paxLiveAgent.restUrl}/liveagent/status/poll`);
                url.searchParams.append('session_id', this.sessionId);
                if (this.lastMessageId) {
                    url.searchParams.append('last_message_id', this.lastMessageId);
                }
                url.searchParams.append('_t', Date.now()); // Cache buster

                const response = await fetch(url, {
                    headers: {
                        'X-WP-Nonce': window.paxLiveAgent.nonce
                    }
                });

                const data = await response.json();

                if (data.success && data.has_updates) {
                    this.handleUpdates(data);
                }

                if (data.last_message_id) {
                    this.lastMessageId = data.last_message_id;
                }

                this.lastUpdate = data.last_activity || new Date().toISOString();
            } catch (error) {
                console.error('Poll error:', error);
            }
        }

        handleUpdates(data) {
            // New messages
            if (data.new_messages && data.new_messages.length > 0) {
                data.new_messages.forEach(msg => {
                    this.appendMessage(msg);
                    if (msg && msg.id) {
                        this.lastMessageId = msg.id;
                    }
                });
                this.playNotificationSound();
                this.showToast(window.paxLiveAgent.strings.newMessage);
                this.markMessagesRead();
            }

            // Typing indicator
            const typingState = data.typing || {};
            if (typingState.user) {
                this.showTypingIndicator();
            } else {
                this.hideTypingIndicator();
            }

            // Session status change
            if (data.session_status === 'closed') {
                this.showToast(window.paxLiveAgent.strings.sessionClosed);
                setTimeout(() => {
                    location.reload();
                }, 2000);
            }
        }

        async acceptSession(sessionId) {
            try {
                console.log('[PAX Live Agent] Accepting session:', sessionId);
                
                const response = await fetch(`${window.paxLiveAgent.restUrl}/liveagent/session/accept`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.paxLiveAgent.nonce
                    },
                    body: JSON.stringify({
                        session_id: sessionId,
                        agent_id: window.paxLiveAgent.agentId
                    })
                });

                console.log('[PAX Live Agent] Response status:', response.status);

                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('[PAX Live Agent] HTTP error:', response.status, errorText);
                    alert(`Failed to accept session: HTTP ${response.status}`);
                    return;
                }

                const data = await response.json();
                console.log('[PAX Live Agent] Response data:', data);

                if (data.success) {
                    console.log('[PAX Live Agent] Session accepted successfully');
                    location.reload();
                } else {
                    const errorMsg = data.message || 'Unknown error';
                    console.error('[PAX Live Agent] Accept failed:', errorMsg);
                    alert(`Failed to accept session: ${errorMsg}`);
                }
            } catch (error) {
                console.error('[PAX Live Agent] Accept error:', error);
                alert(`Error accepting session: ${error.message}`);
            }
        }

        async declineSession(sessionId) {
            try {
                const response = await fetch(`${window.paxLiveAgent.restUrl}/liveagent/session/decline`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.paxLiveAgent.nonce
                    },
                    body: JSON.stringify({
                        session_id: sessionId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to decline session');
                }
            } catch (error) {
                console.error('Decline error:', error);
                alert('Error declining session');
            }
        }

        async closeSession(sessionId) {
            const notes = prompt('Add notes (optional):');
            
            try {
                const response = await fetch(`${window.paxLiveAgent.restUrl}/liveagent/session/close`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.paxLiveAgent.nonce
                    },
                    body: JSON.stringify({
                        session_id: sessionId,
                        notes: notes
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.showToast('Chat closed successfully');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    alert('Failed to close session');
                }
            } catch (error) {
                console.error('Close error:', error);
                alert('Error closing session');
            }
        }

        async sendMessage() {
            const $input = $('#pax-message-input');
            const message = $input.val().trim();

            if (!message) return;

            try {
                const response = await fetch(`${window.paxLiveAgent.restUrl}/live/message`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.paxLiveAgent.nonce
                    },
                    body: JSON.stringify({
                        session_id: this.sessionId,
                        message: message,
                        sender: 'agent'
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.appendMessage(data.message);
                    if (data.message?.id) {
                        this.lastMessageId = data.message.id;
                    }
                    $input.val('').css('height', 'auto');
                    this.sendTypingStatus(false);
                } else {
                    alert('Failed to send message');
                }
            } catch (error) {
                console.error('Send error:', error);
                alert('Error sending message');
            }
        }

        handleTyping() {
            if (!this.isTyping) {
                this.isTyping = true;
                this.sendTypingStatus(true);
            }

            clearTimeout(this.typingTimeout);
            this.typingTimeout = setTimeout(() => {
                this.isTyping = false;
                this.sendTypingStatus(false);
            }, 3000);
        }

        async sendTypingStatus(isTyping) {
            try {
                await fetch(`${window.paxLiveAgent.restUrl}/liveagent/status/typing`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.paxLiveAgent.nonce
                    },
                    body: JSON.stringify({
                        session_id: this.sessionId,
                        is_typing: isTyping,
                        sender: 'agent'
                    })
                });
            } catch (error) {
                console.error('Typing status error:', error);
            }
        }

        async uploadFile(file) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('session_id', this.sessionId);
            formData.append('sender', 'agent');

            try {
                const response = await fetch(`${window.paxLiveAgent.restUrl}/liveagent/file/upload`, {
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': window.paxLiveAgent.nonce
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    // Send message with attachment
                    const message = `[File: ${data.attachment.filename}]`;
                    await this.sendMessageWithAttachment(message, data.attachment.id);
                    $('#pax-file-input').val('');
                } else {
                    alert(data.message || 'Failed to upload file');
                }
            } catch (error) {
                console.error('Upload error:', error);
                alert('Error uploading file');
            }
        }

        async sendMessageWithAttachment(message, attachmentId) {
            try {
                const response = await fetch(`${window.paxLiveAgent.restUrl}/live/message`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.paxLiveAgent.nonce
                    },
                    body: JSON.stringify({
                        session_id: this.sessionId,
                        message: message,
                        sender: 'agent',
                        attachment_id: attachmentId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.appendMessage(data.message);
                    if (data.message?.id) {
                        this.lastMessageId = data.message.id;
                    }
                }
            } catch (error) {
                console.error('Send attachment error:', error);
            }
        }

        async convertToTicket(sessionId) {
            if (!confirm('Convert this chat to a ticket?')) return;

            try {
                const response = await fetch(`${window.paxLiveAgent.restUrl}/liveagent/session/convert-ticket`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.paxLiveAgent.nonce
                    },
                    body: JSON.stringify({
                        session_id: sessionId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.showToast('Converted to ticket #' + data.ticket_id);
                } else {
                    alert('Failed to convert to ticket');
                }
            } catch (error) {
                console.error('Convert error:', error);
                alert('Error converting to ticket');
            }
        }

        async exportChat(sessionId) {
            try {
                const url = new URL(`${window.paxLiveAgent.restUrl}/liveagent/session/export`);
                url.searchParams.append('session_id', sessionId);

                const response = await fetch(url, {
                    headers: {
                        'X-WP-Nonce': window.paxLiveAgent.nonce
                    }
                });

                const data = await response.json();

                if (data.success) {
                    const blob = new Blob([JSON.stringify(data.data, null, 2)], { type: 'application/json' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `chat-session-${sessionId}.json`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                } else {
                    alert('Failed to export chat');
                }
            } catch (error) {
                console.error('Export error:', error);
                alert('Error exporting chat');
            }
        }

        appendMessage(message) {
            const $container = $('#pax-chat-messages');
            const isAgent = message.sender === 'agent';
            const rawContent = message.message || message.text || '';
            const parts = String(rawContent).split('\n');
            const htmlContent = parts.map(part => this.escapeHtml(part)).join('<br>');
            const time = message.timestamp ? new Date(message.timestamp).toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit' 
            }) : '';

            let attachmentMarkup = '';
            if (message.attachment && message.attachment.url) {
                const filename = message.attachment.filename || window.paxLiveAgent.strings.attachment || 'attachment';
                attachmentMarkup = `
                    <div class="pax-message-attachment">
                        <span class="dashicons dashicons-paperclip" aria-hidden="true"></span>
                        <a href="${this.escapeHtml(message.attachment.url)}" target="_blank" rel="noopener noreferrer">
                            ${this.escapeHtml(filename)}
                        </a>
                    </div>
                `;
            }

            const $message = $(`
                <div class="pax-message ${isAgent ? 'pax-message-agent' : 'pax-message-user'}" data-message-id="${message.id || ''}">
                    <div class="pax-message-bubble">
                        <div class="pax-message-content">${htmlContent}${attachmentMarkup}</div>
                        <div class="pax-message-meta">
                            <span class="pax-message-time">${this.escapeHtml(time)}</span>
                            ${isAgent && message.read ? '<span class="pax-message-read"><span class="dashicons dashicons-yes"></span></span>' : ''}
                        </div>
                    </div>
                </div>
            `);

            $('.pax-chat-empty').remove();
            $container.append($message);
            this.scrollToBottom();
        }

        async markMessagesRead() {
            if (!this.sessionId) return;

            try {
                await fetch(`${window.paxLiveAgent.restUrl}/liveagent/message/mark-read`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.paxLiveAgent.nonce
                    },
                    body: JSON.stringify({
                        session_id: this.sessionId,
                        reader_type: 'agent'
                    })
                });
            } catch (error) {
                console.error('Mark read error:', error);
            }
        }

        showTypingIndicator() {
            $('.pax-typing-indicator').show();
            this.scrollToBottom();
        }

        hideTypingIndicator() {
            $('.pax-typing-indicator').hide();
        }

        refreshSessions() {
            location.reload();
        }

        refreshSessionsList() {
            // Update unread counts without full reload
            // Future enhancement
        }

        initAutoScroll() {
            this.scrollToBottom();
        }

        scrollToBottom() {
            const $container = $('#pax-chat-messages');
            if ($container.length) {
                $container.scrollTop($container[0].scrollHeight);
            }
        }

        playNotificationSound() {
            if (window.paxLiveAgent.soundEnabled) {
                const audio = document.getElementById('pax-notification-sound');
                if (audio) {
                    audio.play().catch(e => console.log('Audio play failed:', e));
                }
            }
        }

        showToast(message) {
            // Simple toast notification
            const $toast = $(`
                <div class="pax-toast-notification">
                    ${this.escapeHtml(message)}
                </div>
            `);

            $('body').append($toast);

            setTimeout(() => {
                $toast.addClass('show');
            }, 100);

            setTimeout(() => {
                $toast.removeClass('show');
                setTimeout(() => {
                    $toast.remove();
                }, 300);
            }, 3000);
        }

        captureInitialLastMessage() {
            if (this.lastMessageId) {
                return;
            }
            const $lastMessage = $('#pax-chat-messages .pax-message').last();
            const initialId = $lastMessage.data('message-id');
            if (initialId) {
                this.lastMessageId = initialId;
            }
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        if (typeof window.paxLiveAgent !== 'undefined') {
            window.liveAgentCenter = new LiveAgentCenter();
            window.liveAgentCenter.init();
        }
    });

    // Add toast notification styles
    const toastStyles = `
        <style>
        .pax-toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.9);
            color: #fff;
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            z-index: 10000;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s ease;
        }
        .pax-toast-notification.show {
            opacity: 1;
            transform: translateY(0);
        }
        </style>
    `;
    $('head').append(toastStyles);

})(jQuery);
