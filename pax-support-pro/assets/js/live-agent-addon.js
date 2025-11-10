/**
 * Live Agent Add-on for PAX Unified Chat
 * Instant connect UI with real routing
 */

// Extend PAXUnifiedChat with Live Agent methods
if (typeof PAXUnifiedChat !== 'undefined') {
    
    PAXUnifiedChat.prototype.startLiveAgent = function() {
        console.log('PAX-LIVE: Starting Live Agent session');
        
        // Cancel any ongoing AI requests
        if (this.sessions.assistant.aiController) {
            this.sessions.assistant.aiController.abort();
            this.sessions.assistant.aiController = null;
        }
        
        // Hide typing indicator
        this.hideTypingIndicator();
        
        // Switch mode
        this.currentMode = 'live';
        this.sessions.liveagent.status = 'pending';
        this.sessions.liveagent.startedAt = Date.now();
        
        // Show connecting banner with SVG spinner
        this.showLiveBanner('connecting');
        
        // Disable AI features
        this.disableAIFeatures();
        
        // Get REST base and user data
        const restBase = window.paxSupportPro?.rest?.base || window.location.origin + '/wp-json/pax/v1/';
        const currentUser = window.paxSupportPro?.currentUser || {};
        
        this.sessions.liveagent.restBase = restBase;
        
        // Create session
        fetch(restBase + 'live/start', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': window.paxSupportPro?.nonce || ''
            },
            body: JSON.stringify({
                user_meta: {
                    id: currentUser.id || 0,
                    name: currentUser.name || 'Guest',
                    email: currentUser.email || ''
                },
                page_url: window.location.href,
                user_agent: navigator.userAgent
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.session_id) {
                this.sessions.liveagent.sessionId = data.session_id;
                this.sessions.liveagent.status = data.status || 'pending';
                this.saveState();
                
                // Start polling
                this.startLivePolling();
                
                // Set timeout (5 minutes)
                this.sessions.liveagent.timeout = setTimeout(() => {
                    this.handleLiveTimeout();
                }, 300000);
            } else {
                throw new Error('Failed to create session');
            }
        })
        .catch(error => {
            console.error('PAX-LIVE: Error starting session:', error);
            this.showError('Failed to connect to live agent. Please try again.');
            this.returnToAI();
        });
    };
    
    PAXUnifiedChat.prototype.startLivePolling = function() {
        if (this.isPolling) return;
        
        this.isPolling = true;
        this.pollInterval = setInterval(() => {
            this.pollLiveStatus();
        }, 2000);
        
        // Initial poll
        this.pollLiveStatus();
    };
    
    PAXUnifiedChat.prototype.pollLiveStatus = function() {
        if (!this.sessions.liveagent.sessionId || !this.sessions.liveagent.restBase) return;
        
        const url = this.sessions.liveagent.restBase + 'live/status?session_id=' + this.sessions.liveagent.sessionId;
        
        fetch(url, {
            headers: {
                'X-WP-Nonce': window.paxSupportPro?.nonce || ''
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status && data.status !== this.sessions.liveagent.status) {
                this.handleStatusChange(data.status, data);
            }
        })
        .catch(error => {
            console.error('PAX-LIVE: Polling error:', error);
        });
    };
    
    PAXUnifiedChat.prototype.handleStatusChange = function(status, data) {
        console.log('PAX-LIVE: Status changed to:', status);
        
        this.sessions.liveagent.status = status;
        
        if (status === 'accepted') {
            this.stopLivePolling();
            this.showLiveBanner('connected');
            
            if (data.agent) {
                this.sessions.liveagent.agentInfo = data.agent;
            }
            
            // Enable input
            if (this.inputField) {
                this.inputField.disabled = false;
                this.inputField.placeholder = 'Type your message to the agent…';
            }
            
            this.saveState();
        } else if (status === 'declined') {
            this.stopLivePolling();
            this.showError('No agent available right now.');
            this.returnToAI();
        }
    };
    
    PAXUnifiedChat.prototype.handleLiveTimeout = function() {
        console.log('PAX-LIVE: Session timeout');
        this.stopLivePolling();
        this.showError('Connection timeout. No agent available.');
        this.returnToAI();
    };
    
    PAXUnifiedChat.prototype.stopLivePolling = function() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
        this.isPolling = false;
        
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
        this.enableAIFeatures();
        this.saveState();
        
        if (this.inputField) {
            this.inputField.disabled = false;
            this.inputField.placeholder = 'Type your message…';
        }
    };
    
    PAXUnifiedChat.prototype.endLiveSession = function() {
        console.log('PAX-LIVE: Ending session');
        this.returnToAI();
    };
    
    PAXUnifiedChat.prototype.sendLiveMessage = function(message) {
        if (!this.sessions.liveagent.sessionId || !this.sessions.liveagent.restBase) {
            throw new Error('No active live session');
        }
        
        if (this.sessions.liveagent.status !== 'accepted') {
            throw new Error('Session not accepted yet');
        }
        
        return fetch(this.sessions.liveagent.restBase + 'live/message', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': window.paxSupportPro?.nonce || ''
            },
            body: JSON.stringify({
                session_id: this.sessions.liveagent.sessionId,
                message: message
            })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Failed to send message');
            }
            return data;
        });
    };
    
    PAXUnifiedChat.prototype.showLiveBanner = function(state) {
        let banner = document.getElementById('pax-live-banner');
        
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'pax-live-banner';
            banner.className = 'pax-live-banner';
            
            if (this.chatWindow && this.messageContainer) {
                this.chatWindow.insertBefore(banner, this.messageContainer);
            }
        }
        
        const spinner = `<svg class="pax-spinner" width="16" height="16" viewBox="0 0 16 16" fill="none">
            <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-dasharray="30 10" opacity="0.3"/>
            <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-dasharray="10 30" opacity="1">
                <animateTransform attributeName="transform" type="rotate" from="0 8 8" to="360 8 8" dur="1s" repeatCount="indefinite"/>
            </circle>
        </svg>`;
        
        if (state === 'connecting') {
            banner.innerHTML = `${spinner} <span>Connecting to a live agent…</span>`;
            banner.className = 'pax-live-banner pax-live-connecting';
        } else if (state === 'connected') {
            banner.innerHTML = `<span class="pax-live-badge">●</span> <span>Connected to Live Agent</span>`;
            banner.className = 'pax-live-banner pax-live-connected';
        }
        
        banner.style.display = 'flex';
    };
    
    PAXUnifiedChat.prototype.hideLiveBanner = function() {
        const banner = document.getElementById('pax-live-banner');
        if (banner) {
            banner.style.display = 'none';
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
        if (this.currentMode === 'live' && this.sessions.liveagent.status === 'accepted') {
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
