/**
 * Live Agent Add-on for PAX Unified Chat
 * Instant connect UI with real routing
 */

const PAX_LIVE_CONFIG = window.PAX_LIVE || {};
const PAX_LIVE_REST_BASE = (() => {
    const raw = PAX_LIVE_CONFIG.restBase
        || window.paxSupportPro?.rest?.base
        || `${window.location.origin}/wp-json/pax/v1/`;
    return raw.replace(/\/?$/, '/');
})();

const PAX_LIVE_ROUTES = {
    base: PAX_LIVE_REST_BASE,
    session: `${PAX_LIVE_REST_BASE}live/session`,
    status: `${PAX_LIVE_REST_BASE}live/status`,
    message: `${PAX_LIVE_REST_BASE}live/message`,
    messages: `${PAX_LIVE_REST_BASE}live/messages`
};

const PAX_LIVE_NONCE = PAX_LIVE_CONFIG.nonce || window.paxSupportPro?.nonce || '';
const PAX_LIVE_NO_STORE = !!PAX_LIVE_CONFIG.noStore;
const PAX_LIVE_STRINGS = Object.assign(
    {
        connecting: 'Connecting to an agent…',
        queued: 'You are now in queue, please wait…',
        connected: 'Agent connected!',
        typeHere: 'Type your message…',
        statusError: 'Unable to connect to a live agent right now.'
    },
    PAX_LIVE_CONFIG.strings || {}
);

const PAX_LIVE_HEADERS = (withJson = true) => {
    if (window.PAXLiveWidget?.HEADERS) {
        const headers = window.PAXLiveWidget.HEADERS(withJson);
        if (PAX_LIVE_NO_STORE) {
            headers['Cache-Control'] = 'no-store';
        }
        return headers;
    }

    const headers = { 'X-WP-Nonce': PAX_LIVE_NONCE };
    if (withJson) {
        headers['Content-Type'] = 'application/json';
    }
    if (PAX_LIVE_NO_STORE) {
        headers['Cache-Control'] = 'no-store';
    }
    return headers;
};

const paxLiveAgentText = (key, fallback) => {
    if (PAX_LIVE_STRINGS && Object.prototype.hasOwnProperty.call(PAX_LIVE_STRINGS, key)) {
        return PAX_LIVE_STRINGS[key] || fallback;
    }
    if (window.paxSupportPro && window.paxSupportPro.strings && window.paxSupportPro.strings.liveagent) {
        return window.paxSupportPro.strings.liveagent[key] || fallback;
    }
    return fallback;
};

// Extend PAXUnifiedChat with Live Agent methods
if (typeof PAXUnifiedChat !== 'undefined') {
    
    PAXUnifiedChat.prototype.startLiveAgent = function() {
        console.log('PAX-LIVE: Starting Live Agent session');

        if (typeof this.removeLiveAgentOnboarding === 'function') {
            this.removeLiveAgentOnboarding();
        }

        if (this.sessions.assistant.aiController) {
            this.sessions.assistant.aiController.abort();
            this.sessions.assistant.aiController = null;
        }

        this.hideTypingIndicator();

        this.currentMode = 'liveagent';
        this.sessions.liveagent.status = 'connecting';
        this.sessions.liveagent.startedAt = Date.now();
        this.sessions.liveagent.messages = [];

        this.showLiveBanner('connecting');
        this.setComposerEnabled(false);

        this.disableAIFeatures();
        this.aiWasEnabled = typeof window.paxSupportPro !== 'undefined' ? window.paxSupportPro.aiEnabled : undefined;
        if (typeof window.paxSupportPro !== 'undefined') {
            window.paxSupportPro.aiEnabled = false;
        }

        this.ensureLiveAgentSession(true)
            .then(() => {
                if (typeof this.renderQuickPromptsBar === 'function') {
                    this.renderQuickPromptsBar();
                }
                if (this.sessions.liveagent.timeout) {
                    clearTimeout(this.sessions.liveagent.timeout);
                }
                this.sessions.liveagent.timeout = setTimeout(() => {
                    this.handleLiveTimeout();
                }, 300000);
            })
            .catch((error) => {
                console.error('PAX-LIVE: Error starting session:', error);
                this.showLiveBanner('error', paxLiveAgentText('statusError', 'Unable to connect to a live agent right now.'));
                this.returnToAI();
            });
    };
    
    PAXUnifiedChat.prototype.startLivePolling = function() {
        this.startLongPoll(true);
    };
    
    PAXUnifiedChat.prototype.pollLiveStatus = function() {
        this.syncLiveAgentStatus();
    };
    
    PAXUnifiedChat.prototype.handleStatusChange = function(status, data) {
        console.log('PAX-LIVE: Status changed to:', status);
        
        this.sessions.liveagent.status = status;
        
        if (status === 'accepted' || status === 'active') {
            this.sessions.liveagent.status = 'active';
            this.showLiveBanner('connected');

            if (data?.agent) {
                this.sessions.liveagent.agentInfo = data.agent;
            }

            this.setComposerEnabled(true);
            if (typeof this.removeLiveAgentOnboarding === 'function') {
                this.removeLiveAgentOnboarding();
            }

            this.saveState();
        } else if (status === 'pending') {
            this.sessions.liveagent.status = 'pending';
            this.showLiveBanner('queue');
            this.setComposerEnabled(false);
        } else if (status === 'declined' || status === 'closed') {
            this.sessions.liveagent.status = status;
            this.setComposerEnabled(false);
            this.showLiveBanner('error', paxLiveAgentText('statusError', 'Unable to connect to a live agent right now.'));
            this.stopLongPoll();
        }
    };
    
    PAXUnifiedChat.prototype.handleLiveTimeout = function() {
        console.log('PAX-LIVE: Session timeout');
        this.stopLongPoll();
        this.showLiveBanner('error', paxLiveAgentText('statusError', 'Unable to connect to a live agent right now.'));
        this.setComposerEnabled(false);
    };
    
    PAXUnifiedChat.prototype.stopLivePolling = function() {
        this.stopLongPoll();
        if (this.sessions.liveagent.timeout) {
            clearTimeout(this.sessions.liveagent.timeout);
            this.sessions.liveagent.timeout = null;
        }
    };
    
    PAXUnifiedChat.prototype.returnToAI = function() {
        console.log('PAX-LIVE: Returning to AI mode');
        
        this.stopLivePolling();
        this.sessions.liveagent.status = 'idle';
        this.sessions.liveagent.sessionId = null;
        this.currentMode = 'assistant';
        this.hideLiveBanner();
        if (typeof window.paxSupportPro !== 'undefined' && typeof this.aiWasEnabled !== 'undefined') {
            window.paxSupportPro.aiEnabled = this.aiWasEnabled;
        }
        this.aiWasEnabled = undefined;
        this.enableAIFeatures();
        this.saveState();
        
        if (this.inputField) {
            this.inputField.disabled = false;
            const assistantPlaceholder = window.paxSupportPro?.strings?.assistantPlaceholder || 'Ask me anything...';
            this.inputField.placeholder = assistantPlaceholder;
        }
    };
    
    PAXUnifiedChat.prototype.endLiveSession = function() {
        console.log('PAX-LIVE: Ending session');
        this.closeLiveAgentSession().finally(() => this.returnToAI());
    };
    
    PAXUnifiedChat.prototype.sendLiveMessage = function(message) {
        return this.sendLiveAgentMessage(message);
    };
    
    PAXUnifiedChat.prototype.showLiveBanner = function(state, customMessage) {
        if (this.currentMode && this.currentMode !== 'liveagent') {
            if (typeof this.hideLiveBanner === 'function') {
                this.hideLiveBanner();
            }
            return;
        }

        if (window.PAXLiveWidget?.showBanner) {
            window.PAXLiveWidget.showBanner(state, customMessage);
            return;
        }

        const dots = '<span class="pax-conn-dot"></span><span class="pax-conn-dot"></span><span class="pax-conn-dot"></span>';
        let banner = document.getElementById('pax-live-banner');

        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'pax-live-banner';
            banner.className = 'pax-live-banner';
            banner.setAttribute('role', 'status');
            banner.setAttribute('aria-live', 'polite');

            if (this.chatWindow && this.messageContainer) {
                this.chatWindow.insertBefore(banner, this.messageContainer);
            }
        }

        if (state === 'connecting') {
            const message = customMessage || paxLiveAgentText('connecting', 'Connecting to support…');
            banner.className = 'pax-live-banner pax-live-connecting';
            banner.innerHTML = `${dots}<span>${message}</span>`;
        } else if (state === 'queue' || state === 'queued') {
            const message = customMessage || paxLiveAgentText('queued', 'You are now in queue, please wait…');
            banner.className = 'pax-live-banner pax-live-queue';
            banner.innerHTML = `${dots}<span>${message}</span>`;
        } else if (state === 'connected') {
            const message = customMessage || paxLiveAgentText('connected', 'Agent connected!');
            banner.className = 'pax-live-banner pax-live-connected';
            banner.textContent = message;
        } else if (state === 'offline') {
            const message = customMessage || paxLiveAgentText('offline', 'You appear to be offline. Messages will be queued.');
            banner.className = 'pax-live-banner pax-live-offline';
            banner.textContent = message;
        } else if (state === 'reconnected') {
            const message = customMessage || paxLiveAgentText('reconnected', 'Back online — resuming chat.');
            banner.className = 'pax-live-banner pax-live-reconnected';
            banner.textContent = message;
        } else if (state === 'error') {
            const message = customMessage || paxLiveAgentText('statusError', 'Unable to connect right now. Please try again.');
            banner.className = 'pax-live-banner pax-live-error';
            banner.textContent = message;
        }
    };
    
    PAXUnifiedChat.prototype.hideLiveBanner = function() {
        if (window.PAXLiveWidget?.showBanner) {
            window.PAXLiveWidget.showBanner('hide');
            return;
        }
        const banner = document.getElementById('pax-live-banner');
        if (banner) {
            banner.remove();
        }
    };
    
    PAXUnifiedChat.prototype.disableAIFeatures = function() {
        // Hide reaction icons
        const reactions = document.querySelectorAll('.pax-reaction-icons');
        reactions.forEach(el => {
            el.style.display = 'none';
        });
    };
    
    PAXUnifiedChat.prototype.enableAIFeatures = function() {
        // Show reaction icons
        const reactions = document.querySelectorAll('.pax-reaction-icons');
        reactions.forEach(el => {
            el.style.display = '';
        });
    };
    
    // Override handleSend to route to live agent when active
    const originalHandleSend = PAXUnifiedChat.prototype.handleSend;
    PAXUnifiedChat.prototype.handleSend = function() {
        if (this.currentMode === 'liveagent' && this.sessions.liveagent.status === 'accepted') {
            // Send to live agent
            if (!this.inputField || !this.inputField.value.trim()) {
                return;
            }
            
            const message = this.inputField.value.trim();
            this.inputField.value = '';
            
            // Add user message to UI
            const userMsg = {
                id: Date.now(),
                text: message,
                sender: 'user',
                timestamp: new Date().toISOString()
            };
            
            this.sessions.liveagent.messages.push(userMsg);
            this.renderMessage(userMsg);
            this.scrollToBottom();
            
            // Send to server
            this.sendLiveMessage(message)
                .catch(error => {
                    console.error('PAX-LIVE: Error sending message:', error);
                    this.showError('Failed to send message. Please try again.');
                });
        } else {
            // Use original handler for AI
            originalHandleSend.call(this);
        }
    };
}
