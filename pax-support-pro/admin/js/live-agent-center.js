(function() {
    'use strict';

    const config = window.paxLiveAgentCenterConfig || {};
    if (!config.rest || !config.rest.base) {
        return;
    }

    const API_BASE = (window.PAX_LIVE?.restBase || `${window.location.origin}/wp-json/pax/v1/`).replace(/\/?$/, '/');
    const REST_BASE = config.rest && config.rest.base ? ensureTrailingSlash(config.rest.base) : API_BASE;
    const REST_ROUTES = {
        sessions: config.rest.sessions || (API_BASE + 'live/sessions'),
        session: config.rest.session || (API_BASE + 'live/session/'),
        messages: config.rest.messages || (API_BASE + 'live/messages'),
        message: config.rest.message || (API_BASE + 'live/message'),
        accept: config.rest.accept || (API_BASE + 'live/accept'),
        decline: config.rest.decline || (API_BASE + 'live/decline'),
        close: config.rest.close || (API_BASE + 'live/close'),
        status: config.rest.status || (API_BASE + 'live/status'),
        typing: config.rest.typing,
        markRead: config.rest.markRead,
        fileUpload: config.rest.fileUpload
    };

    class LiveAgentCenter {
        constructor(cfg) {
            this.config = cfg;
            this.state = {
                sessions: {
                    pending: [],
                    active: [],
                    recent: []
                },
                selectedTab: 'pending',
                selectedSessionId: null,
                selectedSessionStatus: null,
                lastMessageId: null,
                polling: {
                    sessions: null,
                    messages: null
                },
                typingTimeout: null,
                isSending: false,
                audioEnabled: document.querySelector('.pax-liveagent-app')?.dataset.soundEnabled === '1'
            };

            this.elements = {};
            this.pendingNotified = new Set();
            this.lastUserMessageId = {};
            this.focusTarget = null;
            this.sessionPollTimer = null;
            this.sessionPollStart = 0;
            this.sessionPollDelay = 3000;
            this.heartbeatTimer = null;
            this.messagePollTimer = null;
            this.messagePollStart = 0;
            this.messagePollDelay = 1000;
            this.connectionState = 'online';
            this.tooltipElement = null;
            this.tooltipHideTimer = null;
            this.tooltipHandlers = new WeakMap();
            this.lastMessageEtag = null;
            this.currentTooltipTarget = null;
            this.handleViewportChange = () => this.hideTooltip();

            this.init();
        }

        init() {
            this.cacheElements();
            this.bindEvents();
            this.renderDiagnostics();
            this.fetchSessions({ initial: true });
            this.startSessionPolling();
            this.startHeartbeat();
            window.addEventListener('scroll', this.handleViewportChange, { passive: true });
            window.addEventListener('resize', this.handleViewportChange);

            const params = new URLSearchParams(window.location.search);
            const focusParam = params.get('focus');
            if (focusParam) {
                this.focusTarget = parseInt(focusParam, 10);
            }
            const initialSession = params.get('session');
            if (initialSession && !this.focusTarget) {
                this.loadSession(parseInt(initialSession, 10), 'pending');
            } else if (this.config.initialSession && !this.focusTarget) {
                this.loadSession(this.config.initialSession, 'active');
            }
        }

        cacheElements() {
            this.elements.root = document.querySelector('.pax-liveagent-app');
            this.elements.tabButtons = Array.from(document.querySelectorAll('.pax-tab-button'));
            this.elements.sessionLists = {
                pending: document.querySelector('[data-list="pending"]'),
                active: document.querySelector('[data-list="active"]'),
                recent: document.querySelector('[data-list="recent"]')
            };
            this.elements.counters = {
                pending: document.querySelector('[data-counter="pending"]'),
                active: document.querySelector('[data-counter="active"]')
            };
            this.elements.emptyState = document.getElementById('pax-liveagent-empty');
            this.elements.panel = document.getElementById('pax-liveagent-panel');
            this.elements.avatar = document.getElementById('pax-liveagent-avatar');
            this.elements.customerName = document.getElementById('pax-liveagent-customer-name');
            this.elements.sessionMeta = document.getElementById('pax-liveagent-session-meta');
            this.elements.pageUrl = document.getElementById('pax-liveagent-page-url');
            this.elements.actionBar = document.getElementById('pax-liveagent-actions');
            this.elements.tags = document.getElementById('pax-liveagent-session-tags');
            this.elements.messages = document.getElementById('pax-liveagent-messages');
            this.elements.typing = document.getElementById('pax-liveagent-typing');
            this.elements.composer = document.getElementById('pax-liveagent-composer');
            this.elements.attachButton = document.getElementById('pax-liveagent-attach');
            this.elements.fileInput = document.getElementById('pax-liveagent-file');
            this.elements.input = document.getElementById('pax-liveagent-input');
            this.elements.sendButton = document.getElementById('pax-liveagent-send');
            this.elements.refreshButton = document.getElementById('pax-liveagent-refresh');
            this.elements.refreshHelp = document.getElementById('pax-liveagent-refresh-help');
            this.elements.pingButton = document.getElementById('pax-liveagent-ping');
            this.elements.pingHelp = document.getElementById('pax-liveagent-ping-help');
            this.elements.pingStatus = document.getElementById('pax-liveagent-ping-status');
            this.elements.restLabel = document.getElementById('pax-liveagent-rest');
            this.elements.chime = document.getElementById('pax-liveagent-chime');
            this.elements.detailEmail = document.getElementById('pax-liveagent-detail-email');
            this.elements.detailIp = document.getElementById('pax-liveagent-detail-ip');
            this.elements.detailDomain = document.getElementById('pax-liveagent-detail-domain');
            this.elements.detailAuth = document.getElementById('pax-liveagent-detail-auth');
            this.elements.detailUa = document.getElementById('pax-liveagent-detail-ua');
            this.elements.detailStarted = document.getElementById('pax-liveagent-detail-started');
            this.elements.detailLast = document.getElementById('pax-liveagent-detail-last');
            this.elements.composerHelp = document.getElementById('pax-liveagent-composer-help');
        }

        bindEvents() {
            this.elements.tabButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    this.switchTab(button.dataset.tab);
                });
            });

            Object.values(this.elements.sessionLists).forEach((list) => {
                list.addEventListener('click', (event) => {
                    const card = event.target.closest('.pax-session-card');
                    if (!card) {
                        return;
                    }

                    const sessionId = parseInt(card.dataset.sessionId, 10);
                    const status = card.dataset.sessionStatus;

                    const acceptButton = event.target.closest('[data-action="accept"]');
                    if (acceptButton) {
                        event.stopPropagation();
                        this.acceptSession(sessionId, card, acceptButton);
                        return;
                    }

                    const declineButton = event.target.closest('[data-action="decline"]');
                    if (declineButton) {
                        event.stopPropagation();
                        this.declineSession(sessionId, declineButton);
                        return;
                    }

                    this.loadSession(sessionId, status);
                });
            });

            if (this.elements.sendButton) {
                this.elements.sendButton.addEventListener('click', () => {
                    this.sendMessage();
                });
                this.attachTooltip(this.elements.sendButton, this.config.strings?.tooltipComposer);
            }

            if (this.elements.input) {
                this.elements.input.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' && !event.shiftKey) {
                        event.preventDefault();
                        this.sendMessage();
                    }
                });

                this.elements.input.addEventListener('input', () => {
                    this.autoResizeInput();
                    this.emitTyping(true);
                });
            }

            if (this.elements.attachButton && this.elements.fileInput) {
                this.elements.attachButton.addEventListener('click', () => {
                    this.elements.fileInput.click();
                });

                this.elements.fileInput.addEventListener('change', (event) => {
                    const file = event.target.files?.[0];
                    if (file) {
                        this.uploadAttachment(file);
                    }
                    this.elements.fileInput.value = '';
                });
            }

            if (this.elements.pingButton) {
                this.elements.pingButton.addEventListener('click', () => this.pingRest());
                this.attachTooltip(this.elements.pingButton, this.config.strings?.tooltipPing);
            }

            if (this.elements.refreshButton) {
                this.elements.refreshButton.addEventListener('click', () => this.handleManualRefresh());
                this.attachTooltip(this.elements.refreshButton, this.config.strings?.tooltipRefresh);
            }

            if (this.elements.refreshHelp) {
                this.attachTooltip(this.elements.refreshHelp, this.config.strings?.tooltipRefresh, { focus: true });
                this.elements.refreshHelp.addEventListener('click', () => this.toggleTooltip(this.elements.refreshHelp));
            }

            if (this.elements.pingHelp) {
                this.attachTooltip(this.elements.pingHelp, this.config.strings?.tooltipPing, { focus: true });
                this.elements.pingHelp.addEventListener('click', () => this.toggleTooltip(this.elements.pingHelp));
            }

            if (this.elements.composerHelp) {
                this.attachTooltip(this.elements.composerHelp, this.config.strings?.tooltipComposer, { focus: true });
                this.elements.composerHelp.addEventListener('click', () => this.toggleTooltip(this.elements.composerHelp));
            }
        }

        renderDiagnostics() {
            if (this.elements.restLabel && this.config.diagnostics?.restBase) {
                this.elements.restLabel.textContent = this.config.diagnostics.restBase;
            }
        }

        switchTab(tab) {
            if (this.state.selectedTab === tab) {
                return;
            }

            this.state.selectedTab = tab;
            this.elements.tabButtons.forEach((button) => {
                const isActive = button.dataset.tab === tab;
                button.classList.toggle('active', isActive);
                button.setAttribute('aria-selected', String(isActive));
            });

            Object.entries(this.elements.sessionLists).forEach(([key, list]) => {
                if (key === tab) {
                    list.removeAttribute('hidden');
                    list.classList.add('active');
                } else {
                    list.setAttribute('hidden', 'hidden');
                    list.classList.remove('active');
                }
            });
        }

        startSessionPolling() {
            this.stopSessionPolling();
            this.sessionPollStart = Date.now();
            this.sessionPollDelay = 1000;
            this.scheduleSessionPoll(0);
        }

        scheduleSessionPoll(delay = this.sessionPollDelay) {
            if (this.sessionPollTimer) {
                clearTimeout(this.sessionPollTimer);
            }
            const nextDelay = Math.max(250, delay);
            this.sessionPollTimer = window.setTimeout(async () => {
                await this.fetchSessions({ auto: true });
                const pendingCount = this.state.sessions.pending.length;
                if (pendingCount > 0) {
                    const elapsed = Date.now() - this.sessionPollStart;
                    this.sessionPollDelay = elapsed >= 60000 ? 3000 : 1000;
                } else {
                    this.sessionPollStart = Date.now();
                    this.sessionPollDelay = 3000;
                }
                this.scheduleSessionPoll(this.sessionPollDelay);
            }, nextDelay);
            this.state.polling.sessions = this.sessionPollTimer;
        }

        stopSessionPolling() {
            if (this.sessionPollTimer) {
                clearTimeout(this.sessionPollTimer);
                this.sessionPollTimer = null;
            }
            this.state.polling.sessions = null;
        }

        startHeartbeat() {
            this.stopHeartbeat();
            this.heartbeatTimer = window.setInterval(() => {
                this.fetchSessions({ heartbeat: true, silent: true });
            }, 30000);
        }

        stopHeartbeat() {
            if (this.heartbeatTimer) {
                clearInterval(this.heartbeatTimer);
                this.heartbeatTimer = null;
            }
        }

        startMessagePolling() {
            this.stopMessagePolling();
            if (!this.state.selectedSessionId) {
                return;
            }
            this.messagePollStart = Date.now();
            this.messagePollDelay = 1000;
            this.scheduleMessagePoll(0);
        }

        scheduleMessagePoll(delay = this.messagePollDelay) {
            if (this.messagePollTimer) {
                clearTimeout(this.messagePollTimer);
            }
            const nextDelay = Math.max(250, delay);
            this.messagePollTimer = window.setTimeout(async () => {
                await this.fetchMessages(true, { auto: true });
                if (!this.state.selectedSessionId) {
                    return;
                }
                const elapsed = Date.now() - this.messagePollStart;
                this.messagePollDelay = elapsed >= 180000 ? 3000 : 1000;
                this.scheduleMessagePoll(this.messagePollDelay);
            }, nextDelay);
            this.state.polling.messages = this.messagePollTimer;
        }

        stopMessagePolling() {
            if (this.messagePollTimer) {
                clearTimeout(this.messagePollTimer);
                this.messagePollTimer = null;
            }
            this.state.polling.messages = null;
        }

        async fetchSessions(options = {}) {
            if (typeof options === 'boolean') {
                options = { initial: options };
            }
            const {
                initial = false,
                manual = false,
                heartbeat = false,
                silent = false,
                auto = false,
            } = options;

            const previousPendingIds = new Set((this.state.sessions.pending || []).map((session) => session.id));
            try {
                const response = await fetch(`${API_BASE}live/sessions?limit=30`, {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': window.PAX_LIVE?.nonce || this.config.nonce || ''
                    },
                    credentials: 'same-origin',
                    cache: 'no-store'
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    if (!silent) {
                        this.handleConnectivityDrop();
                    }
                    return false;
                }

                this.handleConnectivityRestore();

                const payload = data.sessions || {};
                this.state.sessions.pending = Array.isArray(payload.pending) ? payload.pending : [];
                this.state.sessions.active = Array.isArray(payload.active) ? payload.active : [];
                this.state.sessions.recent = Array.isArray(payload.recent) ? payload.recent : [];

                const counts = data.meta?.counts;
                if (counts) {
                    if (this.elements.counters.pending) {
                        this.elements.counters.pending.textContent = counts.pending ?? this.state.sessions.pending.length;
                    }
                    if (this.elements.counters.active) {
                        this.elements.counters.active.textContent = counts.active ?? this.state.sessions.active.length;
                    }
                } else {
                    if (this.elements.counters.pending) {
                        this.elements.counters.pending.textContent = this.state.sessions.pending.length;
                    }
                    if (this.elements.counters.active) {
                        this.elements.counters.active.textContent = this.state.sessions.active.length;
                    }
                }

                const newPending = this.state.sessions.pending.find((session) => !previousPendingIds.has(session.id));
                if (newPending && !this.pendingNotified.has(newPending.id)) {
                    this.pendingNotified.add(newPending.id);
                    this.notifyNewRequest(newPending);
                }

                this.updateSessionLists();

                if (initial && this.state.sessions.pending.length) {
                    this.loadSession(this.state.sessions.pending[0].id, 'pending');
                }

                if (this.focusTarget) {
                    this.openSession(this.focusTarget);
                    this.focusTarget = null;
                }
                return true;
            } catch (error) {
                console.error('LiveAgentCenter: failed to fetch sessions', error);
                if (!silent) {
                    this.handleConnectivityDrop();
                }
                return false;
            }
        }

        updateSessionLists() {
            ['pending', 'active', 'recent'].forEach((status) => {
                const list = this.elements.sessionLists[status];
                if (!list) {
                    return;
                }

                list.querySelectorAll('.pax-session-card').forEach((card) => card.remove());

                const sessions = this.state.sessions[status] || [];
                const emptyState = list.querySelector('[data-empty]');
                if (emptyState) {
                    emptyState.hidden = sessions.length > 0;
                }

                const fragment = document.createDocumentFragment();
                sessions.forEach((session) => {
                    fragment.appendChild(this.createSessionCard(session, status));
                });

                list.appendChild(fragment);
            });

            if (this.state.selectedSessionId) {
                this.highlightActiveCard(this.state.selectedSessionId);
            }
        }

        createSessionCard(session, status) {
            const card = document.createElement('article');
            card.className = 'pax-session-card';
            card.dataset.sessionId = session.id;
            card.dataset.sessionStatus = status;

            if (session.id === this.state.selectedSessionId) {
                card.classList.add('active');
            }

            const avatar = document.createElement('div');
            avatar.className = 'pax-session-card-avatar';
            avatar.innerHTML = session.avatar || `<span class="dashicons dashicons-admin-users"></span>`;

            const statusDot = document.createElement('span');
            statusDot.className = 'pax-session-card-status';
            statusDot.dataset.status = session.status;
            avatar.appendChild(statusDot);

            const header = document.createElement('div');
            header.className = 'pax-session-card-header';
            header.appendChild(avatar);

            const headerText = document.createElement('div');
            const title = document.createElement('div');
            title.className = 'pax-session-card-title';
            title.textContent = session.user_name || this.config.strings?.unknownUser || 'Guest';
            headerText.appendChild(title);

            const subtitle = document.createElement('div');
            subtitle.className = 'pax-session-card-subtitle';
            const metaPieces = [];
            if (session.user_email) {
                metaPieces.push(session.user_email);
            }
            if (session.domain) {
                metaPieces.push(session.domain);
            }
            if (session.auth_plugin && session.auth_plugin !== 'core') {
                metaPieces.push(`Auth: ${session.auth_plugin}`);
            }
            metaPieces.push(this.formatRelativeTime(session.last_activity));
            subtitle.textContent = metaPieces.filter(Boolean).join(' • ');
            headerText.appendChild(subtitle);

            if (session.page_url) {
                const pathLine = document.createElement('div');
                pathLine.className = 'pax-session-card-subtitle pax-session-card-subtitle--muted';
                let label = session.page_url;
                try {
                    const parsed = new URL(session.page_url, window.location.origin);
                    label = parsed.pathname || parsed.href;
                } catch (error) {
                    // Keep original label
                }
                pathLine.textContent = label;
                headerText.appendChild(pathLine);
            }
            header.appendChild(headerText);
            card.appendChild(header);

            if (session.last_message?.excerpt) {
                const preview = document.createElement('div');
                preview.className = 'pax-session-card-subtitle';
                preview.textContent = session.last_message.excerpt;
                card.appendChild(preview);
            }

            const footer = document.createElement('div');
            footer.className = 'pax-session-card-footer';

            const badges = document.createElement('div');
            badges.className = 'pax-session-badges';
            if (session.unread_count && status !== 'recent') {
                const badge = document.createElement('span');
                badge.className = 'pax-chip';
                badge.textContent = `${session.unread_count} ${this.config.strings?.unread || 'Unread'}`;
                badges.appendChild(badge);
            }

            if (status === 'active') {
                const badge = document.createElement('span');
                badge.className = 'pax-chip pax-chip-secondary';
                badge.textContent = this.config.strings?.live || 'Live';
                badges.appendChild(badge);
            }

            footer.appendChild(badges);

            const timestamp = document.createElement('span');
            timestamp.textContent = this.formatAbsoluteTime(session.last_activity);
            footer.appendChild(timestamp);

            card.appendChild(footer);

            if (status === 'pending') {
                const actions = document.createElement('div');
                actions.className = 'pax-session-card-actions';

                const accept = document.createElement('button');
                accept.className = 'button button-primary';
                accept.dataset.action = 'accept';
                accept.innerHTML = `<span class="dashicons dashicons-yes"></span>${this.config.strings?.accept || 'Accept'}`;
                const acceptWrap = this.wrapActionWithHelp(accept, this.config.strings?.tooltipAccept, 'accept');
                if (acceptWrap) {
                    actions.appendChild(acceptWrap);
                }

                const decline = document.createElement('button');
                decline.className = 'button';
                decline.dataset.action = 'decline';
                decline.innerHTML = `<span class="dashicons dashicons-no-alt"></span>${this.config.strings?.decline || 'Decline'}`;
                const declineWrap = this.wrapActionWithHelp(decline, this.config.strings?.tooltipDecline, 'decline');
                if (declineWrap) {
                    actions.appendChild(declineWrap);
                }

                card.appendChild(actions);
            }

            return card;
        }

        extractPath(url) {
            if (!url) {
                return '';
            }
            try {
                const parsed = new URL(url, window.location.origin);
                if (parsed.pathname && parsed.pathname !== '/') {
                    return parsed.pathname;
                }
                return parsed.hostname || parsed.href;
            } catch (error) {
                return url;
            }
        }

        formatAuthProvider(provider) {
            if (!provider || provider === 'core') {
                return 'Core';
            }
            return provider
                .replace(/[_-]/g, ' ')
                .replace(/\b\w/g, (char) => char.toUpperCase());
        }

        formatRelativeTime(timestamp) {
            if (!timestamp) {
                return '';
            }
            const date = new Date(timestamp.replace(' ', 'T'));
            const diff = (Date.now() - date.getTime()) / 1000;
            if (diff < 60) {
                return this.config.strings?.justNow || 'Just now';
            }
            if (diff < 3600) {
                const minutes = Math.floor(diff / 60);
                return `${minutes}m ${this.config.strings?.ago || 'ago'}`;
            }
            if (diff < 86400) {
                const hours = Math.floor(diff / 3600);
                return `${hours}h ${this.config.strings?.ago || 'ago'}`;
            }
            const days = Math.floor(diff / 86400);
            return `${days}d ${this.config.strings?.ago || 'ago'}`;
        }

        formatAbsoluteTime(timestamp) {
            if (!timestamp) {
                return '';
            }
            const date = new Date(timestamp.replace(' ', 'T'));
            return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        }

        async loadSession(sessionId, status) {
            if (!sessionId) {
                return;
            }

            this.stopMessagePolling();

            this.state.selectedSessionId = sessionId;
            this.state.selectedSessionStatus = status;
            this.state.lastMessageId = null;
            this.lastMessageEtag = null;
            this.lastUserMessageId[sessionId] = null;

            this.highlightActiveCard(sessionId);
            this.elements.emptyState.hidden = true;
            this.elements.panel.hidden = false;
            this.elements.messages.innerHTML = '';
            this.elements.tags.innerHTML = '';
            this.elements.actionBar.innerHTML = '';
            this.elements.typing.hidden = true;
            this.elements.input.value = '';

            this.autoResizeInput();

            let summary = this.findSessionSummary(sessionId);

            if (!summary) {
                try {
                    const response = await this.request(`${REST_ROUTES.session}${sessionId}`, { method: 'GET' });
                    if (response.success) {
                        summary = response.session;
                    }
                } catch (error) {
                    console.error('LiveAgentCenter: failed to load session', error);
                }
            }

            if (summary) {
                this.renderSessionHeader(summary);
            }

            await this.fetchMessages(false);
            this.startMessagePolling();
            this.markMessagesRead();
        }

        highlightActiveCard(sessionId) {
            const cards = document.querySelectorAll('.pax-session-card');
            cards.forEach((card) => {
                card.classList.toggle('active', parseInt(card.dataset.sessionId, 10) === sessionId);
            });
        }

        findSessionSummary(sessionId) {
            const allSessions = [
                ...this.state.sessions.pending,
                ...this.state.sessions.active,
                ...this.state.sessions.recent
            ];
            return allSessions.find((session) => session.id === sessionId);
        }

        openSession(sessionId) {
            if (!sessionId) {
                return;
            }
            const summary = this.findSessionSummary(sessionId);
            if (summary) {
                const status = summary.status === 'active'
                    ? 'active'
                    : (summary.status === 'pending' ? 'pending' : 'recent');
                const targetTab = status === 'recent' ? 'recent' : status;
                this.switchTab(targetTab);
                this.loadSession(sessionId, status === 'recent' ? 'recent' : status);
            } else {
                this.switchTab('pending');
                this.loadSession(sessionId, 'pending');
            }
        }

        renderSessionHeader(session) {
            if (this.elements.customerName) {
                this.elements.customerName.textContent = session.user_name || this.config.strings?.unknownUser || 'Guest';
            }

            if (this.elements.sessionMeta) {
                const parts = [];
                if (session.user_email) {
                    parts.push(session.user_email);
                }
                if (session.domain) {
                    parts.push(session.domain);
                }
                if (session.page_url) {
                    parts.push(this.extractPath(session.page_url));
                }
                this.elements.sessionMeta.textContent = parts.join(' • ');
            }

            if (this.elements.pageUrl) {
                if (session.page_url) {
                    this.elements.pageUrl.textContent = this.extractPath(session.page_url);
                    this.elements.pageUrl.href = session.page_url;
                    this.elements.pageUrl.hidden = false;
                    this.attachTooltip(this.elements.pageUrl, session.page_url, { focus: true });
                } else {
                    this.elements.pageUrl.textContent = '—';
                    this.elements.pageUrl.removeAttribute('href');
                    this.elements.pageUrl.hidden = false;
                }
            }

            if (this.elements.avatar) {
                if (session.avatar) {
                    this.elements.avatar.innerHTML = session.avatar;
                } else {
                    this.elements.avatar.textContent = (session.user_name || 'G').charAt(0).toUpperCase();
                }
            }

            this.renderActionButtons(session);
            this.renderTags(session);

            if (this.elements.composer) {
                const isActive = session.status === 'active';
                this.elements.composer.hidden = !isActive;

                if (this.elements.input) {
                    this.elements.input.disabled = !isActive;
                    this.elements.input.placeholder = isActive
                        ? (this.config.strings?.composerHint || 'Type a reply…')
                        : session.status === 'pending'
                            ? (this.config.strings?.acceptPrompt || 'Accept this chat to reply.')
                            : (this.config.strings?.closedMessage || 'This session is closed.');
                }

                if (this.elements.sendButton) {
                    this.elements.sendButton.disabled = !isActive;
                    this.elements.sendButton.classList.toggle('button-disabled', !isActive);
                }
            }

            if (this.elements.messages && !this.elements.messages.querySelector('.pax-message') && session.status !== 'active') {
                this.elements.messages.innerHTML = '';
                const placeholder = document.createElement('div');
                placeholder.className = 'pax-chat-placeholder pax-chat-placeholder-inline';
                const icon = document.createElement('span');
                icon.className = 'dashicons dashicons-info';
                placeholder.appendChild(icon);
                const text = document.createElement('p');
                text.textContent = session.status === 'pending'
                    ? (this.config.strings?.acceptPrompt || 'Accept this chat to reply.')
                    : (this.config.strings?.closedMessage || 'This session is closed.');
                placeholder.appendChild(text);
                this.elements.messages.appendChild(placeholder);
            }

            if (this.elements.detailEmail) {
                this.elements.detailEmail.textContent = session.user_email || '—';
            }
            if (this.elements.detailIp) {
                const ipAddress = session.user_ip || '—';
                this.elements.detailIp.textContent = ipAddress;
                if (ipAddress && ipAddress !== '—') {
                    this.attachTooltip(this.elements.detailIp, ipAddress, { focus: true });
                }
            }
            if (this.elements.detailDomain) {
                this.elements.detailDomain.textContent = session.domain || '—';
            }
            if (this.elements.detailAuth) {
                this.elements.detailAuth.textContent = this.formatAuthProvider(session.auth_plugin);
            }
            if (this.elements.detailUa) {
                if (session.user_agent) {
                    const uaDisplay = this.truncate(session.user_agent, 88);
                    this.elements.detailUa.textContent = uaDisplay;
                    this.attachTooltip(this.elements.detailUa, session.user_agent, { focus: true });
                } else {
                    this.elements.detailUa.textContent = '—';
                }
            }
            if (this.elements.detailStarted) {
                this.elements.detailStarted.textContent = session.started_at ? this.formatAbsoluteTime(session.started_at) : '—';
            }
            if (this.elements.detailLast) {
                this.elements.detailLast.textContent = session.last_activity ? this.formatRelativeTime(session.last_activity) : '—';
            }
        }

        renderActionButtons(session) {
            if (!this.elements.actionBar) {
                return;
            }

            this.elements.actionBar.innerHTML = '';

            if (session.status === 'pending') {
                const accept = this.createHeaderButton('button button-primary', this.config.strings?.accept || 'Accept', 'dashicons-yes');
                accept.addEventListener('click', () => this.acceptSession(session.id, null, accept));
                const acceptWrap = this.wrapActionWithHelp(accept, this.config.strings?.tooltipAccept, 'accept-header');
                if (acceptWrap) {
                    this.elements.actionBar.appendChild(acceptWrap);
                }

                const decline = this.createHeaderButton('button', this.config.strings?.decline || 'Decline', 'dashicons-no-alt');
                decline.addEventListener('click', () => this.declineSession(session.id, decline));
                const declineWrap = this.wrapActionWithHelp(decline, this.config.strings?.tooltipDecline, 'decline-header');
                if (declineWrap) {
                    this.elements.actionBar.appendChild(declineWrap);
                }
            } else if (session.status === 'active') {
                const close = this.createHeaderButton('button button-secondary', this.config.strings?.close || 'End Session', 'dashicons-no-alt');
                close.addEventListener('click', () => this.closeSession(session.id, close));
                const closeWrap = this.wrapActionWithHelp(close, this.config.strings?.tooltipClose, 'close-header');
                if (closeWrap) {
                    this.elements.actionBar.appendChild(closeWrap);
                }
            }
        }

        renderTags(session) {
            if (!this.elements.tags) {
                return;
            }
            this.elements.tags.innerHTML = '';

            const tags = [];
            tags.push({
                label: session.status === 'active' ? (this.config.strings?.live || 'Live') : session.status.toUpperCase(),
                variant: session.status === 'active' ? 'secondary' : 'primary'
            });

            if (session.unread_count) {
                tags.push({
                    label: `${session.unread_count} ${this.config.strings?.unread || 'Unread'}`,
                    variant: 'primary'
                });
            }

            if (session.domain) {
                tags.push({
                    label: session.domain,
                    variant: 'secondary'
                });
            }

            if (session.auth_plugin && session.auth_plugin !== 'core') {
                tags.push({
                    label: `Auth: ${session.auth_plugin}`,
                    variant: 'secondary'
                });
            }

            tags.forEach((tag) => {
                const pill = document.createElement('span');
                pill.className = `pax-chip${tag.variant === 'secondary' ? ' pax-chip-secondary' : ''}`;
                pill.textContent = tag.label;
                this.elements.tags.appendChild(pill);
            });
        }

        createHeaderButton(className, label, icon) {
            const button = document.createElement('button');
            button.className = className;
            button.innerHTML = `<span class="dashicons ${icon}"></span>${label}`;
            return button;
        }

        async fetchMessages(incremental = false, options = {}) {
            if (typeof options === 'boolean') {
                options = { manual: options };
            }
            const { auto = false } = options;

            const sessionId = this.state.selectedSessionId;
            if (!sessionId) {
                return;
            }

            const params = new URLSearchParams({
                session_id: String(sessionId)
            });

            if (incremental && this.state.lastMessageId) {
                params.set('after', this.state.lastMessageId);
            }

            const headers = new Headers({
                'X-WP-Nonce': window.PAX_LIVE?.nonce || this.config.nonce || ''
            });

            if (this.lastMessageEtag) {
                headers.set('If-None-Match', this.lastMessageEtag);
            }

            const previousUserMessageId = this.lastUserMessageId[sessionId] || null;

            try {
                const response = await fetch(`${REST_ROUTES.messages}?${params.toString()}`, {
                    method: 'GET',
                    headers,
                    credentials: 'same-origin',
                    cache: 'no-store'
                });

                if (response.status === 304) {
                    this.handleConnectivityRestore();
                    return;
                }

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error('Failed to fetch messages');
                }

                this.handleConnectivityRestore();

                const etag = response.headers.get('ETag') || data.etag;
                if (etag) {
                    this.lastMessageEtag = etag;
                }

                if (data.session) {
                    this.renderSessionHeader(data.session);
                }

                const messages = Array.isArray(data.messages) ? data.messages : [];

                if (!incremental) {
                    this.renderMessages(messages, true);
                    const lastMessage = data.session?.last_message;
                    if (lastMessage?.sender === 'user' && lastMessage?.id) {
                        this.lastUserMessageId[sessionId] = lastMessage.id;
                    }
                    this.scrollToBottom(true);
                } else if (messages.length) {
                    this.renderMessages(messages, false);
                }

                if (data.last_id) {
                    this.state.lastMessageId = data.last_id;
                } else if (messages.length) {
                    const lastMsg = messages[messages.length - 1];
                    if (lastMsg?.id) {
                        this.state.lastMessageId = lastMsg.id;
                    }
                }

                if (messages.length) {
                    const latestUserMessage = [...messages].reverse().find((msg) => msg.sender === 'user');
                    if (latestUserMessage?.id && latestUserMessage.id !== previousUserMessageId) {
                        this.lastUserMessageId[sessionId] = latestUserMessage.id;
                        if (data.session?.status === 'active') {
                            this.notifyNewMessage();
                            this.markMessagesRead();
                        }
                    }
                }

                if (data.typing) {
                    this.toggleTyping(data.typing.user);
                }
            } catch (error) {
                console.error('LiveAgentCenter: failed to fetch messages', error);
                this.handleConnectivityDrop();
            }
        }

        renderMessages(messages, replace) {
            if (!this.elements.messages) {
                return;
            }

            const fragment = document.createDocumentFragment();
            messages.forEach((message) => {
                fragment.appendChild(this.createMessageBubble(message));
            });

            if (replace) {
                this.elements.messages.innerHTML = '';
                this.elements.messages.appendChild(fragment);
            } else {
                this.elements.messages.appendChild(fragment);
            }

            this.scrollToBottom();
        }

        createMessageBubble(message) {
            const wrapper = document.createElement('div');
            wrapper.className = 'pax-message';
            wrapper.classList.add(
                message.sender === 'agent' ? 'pax-message-agent'
                    : message.sender === 'system' ? 'pax-message-system'
                        : 'pax-message-user'
            );

            const bubble = document.createElement('div');
            bubble.className = 'pax-message-bubble';

            const content = document.createElement('div');
            content.className = 'pax-message-content';
            content.textContent = message.message || '';
            bubble.appendChild(content);

            if (message.attachment) {
                const attachment = document.createElement('div');
                attachment.className = 'pax-message-attachment';
                attachment.innerHTML = `<span class="dashicons dashicons-admin-page"></span>`;
                const link = document.createElement('a');
                link.href = message.attachment.url;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.textContent = message.attachment.filename || message.attachment.url;
                attachment.appendChild(link);
                bubble.appendChild(attachment);
            }

            const meta = document.createElement('div');
            meta.className = 'pax-message-meta';
            const time = document.createElement('span');
            time.textContent = this.formatAbsoluteTime(message.timestamp);
            meta.appendChild(time);

            if (message.sender === 'agent' && message.read) {
                const read = document.createElement('span');
                read.className = 'pax-message-read';
                read.innerHTML = `<span class="dashicons dashicons-yes"></span>${this.config.strings?.read || 'Read'}`;
                meta.appendChild(read);
            }

            bubble.appendChild(meta);
            wrapper.appendChild(bubble);
            return wrapper;
        }

        async markMessagesRead() {
            if (!REST_ROUTES.markRead || !this.state.selectedSessionId) {
                return;
            }
            try {
                await this.request(REST_ROUTES.markRead, {
                    method: 'POST',
                    body: {
                        session_id: this.state.selectedSessionId,
                        reader_type: 'agent'
                    }
                });
            } catch (error) {
                // Silently fail
            }
        }

        scrollToBottom(force) {
            if (!this.elements.messages) {
                return;
            }
            if (force || this.isScrolledToBottom()) {
                this.elements.messages.scrollTo({
                    top: this.elements.messages.scrollHeight,
                    behavior: 'smooth'
                });
            }
        }

        isScrolledToBottom() {
            if (!this.elements.messages) {
                return false;
            }
            const { scrollTop, scrollHeight, clientHeight } = this.elements.messages;
            return scrollHeight - scrollTop - clientHeight < 48;
        }

        autoResizeInput() {
            if (!this.elements.input) {
                return;
            }
            this.elements.input.style.height = 'auto';
            this.elements.input.style.height = `${Math.min(this.elements.input.scrollHeight, 160)}px`;
        }

        async acceptSession(sessionId, card, trigger) {
            if (!sessionId) {
                return;
            }
            if (card) {
                card.classList.add('pax-session-card--processing');
            }
            this.setButtonLoading(trigger, true);

            try {
                await this.request(REST_ROUTES.accept, {
                    method: 'POST',
                    body: { session_id: sessionId }
                });
                await this.fetchSessions({ manual: true });
                await this.loadSession(sessionId, 'active');
            } catch (error) {
                console.error('LiveAgentCenter: accept failed', error);
                this.toast(this.config.strings?.refreshFailed || 'Refresh failed. Please try again.');
            } finally {
                this.setButtonLoading(trigger, false);
                if (card) {
                    card.classList.remove('pax-session-card--processing');
                }
            }
        }

        async declineSession(sessionId, trigger) {
            if (!sessionId) {
                return;
            }
            const confirmMessage = this.config.strings?.confirmDecline || 'Decline this chat request?';
            if (!window.confirm(confirmMessage)) {
                return;
            }

            this.setButtonLoading(trigger, true);

            try {
                await this.request(REST_ROUTES.decline, {
                    method: 'POST',
                    body: { session_id: sessionId }
                });
                await this.fetchSessions({ manual: true });
                if (this.state.selectedSessionId === sessionId) {
                    this.state.selectedSessionId = null;
                    this.elements.panel.hidden = true;
                    this.elements.emptyState.hidden = false;
                    this.stopMessagePolling();
                }
            } catch (error) {
                console.error('LiveAgentCenter: decline failed', error);
                this.toast(this.config.strings?.refreshFailed || 'Refresh failed. Please try again.');
            } finally {
                this.setButtonLoading(trigger, false);
            }
        }

        async closeSession(sessionId, trigger) {
            if (!sessionId) {
                return;
            }
            const confirmMessage = this.config.strings?.confirmClose || 'End this chat session?';
            if (!window.confirm(confirmMessage)) {
                return;
            }

            this.setButtonLoading(trigger, true);

            try {
                await this.request(REST_ROUTES.close, {
                    method: 'POST',
                    body: { session_id: sessionId }
                });
                await this.fetchSessions({ manual: true });
                await this.loadSession(sessionId, 'recent');
            } catch (error) {
                console.error('LiveAgentCenter: close session failed', error);
                this.toast(this.config.strings?.refreshFailed || 'Refresh failed. Please try again.');
            } finally {
                this.setButtonLoading(trigger, false);
            }
        }

        async sendMessage(extra = {}) {
            if (this.state.isSending || !this.state.selectedSessionId) {
                return;
            }

            const text = this.elements.input?.value.trim() || '';
            if (!text && !extra.attachment_id) {
                return;
            }

            this.state.isSending = true;

            try {
                const payload = {
                    session_id: this.state.selectedSessionId,
                    message: text,
                    ...extra
                };

                await this.request(REST_ROUTES.message, {
                    method: 'POST',
                    body: payload
                });

                if (!extra.attachment_id && this.elements.input) {
                    this.elements.input.value = '';
                    this.autoResizeInput();
                }

                this.emitTyping(false);
                await this.fetchMessages(true, { manual: true });
            } catch (error) {
                console.error('LiveAgentCenter: send failed', error);
                window.alert(this.config.strings?.messageFailed || 'Unable to send message. Please try again.');
            } finally {
                this.state.isSending = false;
            }
        }

        async uploadAttachment(file) {
            if (!REST_ROUTES.fileUpload || !this.state.selectedSessionId) {
                return;
            }

            const uploadingLabel = this.config.strings?.uploading || 'Uploading…';
            this.elements.sendButton.disabled = true;
            this.elements.sendButton.querySelector('.pax-send-label').textContent = uploadingLabel;

            try {
                const formData = new FormData();
                formData.append('session_id', this.state.selectedSessionId);
                formData.append('sender', 'agent');
                formData.append('file', file);

                const response = await this.request(REST_ROUTES.fileUpload, {
                    method: 'POST',
                    body: formData,
                    isForm: true
                });

                if (response.success && response.attachment) {
                    await this.sendMessage({
                        attachment_id: response.attachment.id,
                        message: this.elements.input.value.trim()
                    });
                } else {
                    throw new Error('Upload failed');
                }
            } catch (error) {
                console.error('LiveAgentCenter: upload failed', error);
                window.alert(this.config.strings?.uploadFailed || 'Upload failed. Please try again.');
            } finally {
                this.elements.sendButton.disabled = false;
                this.elements.sendButton.querySelector('.pax-send-label').textContent = this.config.strings?.send || 'Send';
            }
        }

        async handleManualRefresh() {
            if (!this.elements.refreshButton || this.elements.refreshButton.classList.contains('is-loading')) {
                return;
            }

            this.setRefreshLoading(true);
            this.stopSessionPolling();

            try {
                const success = await this.fetchSessions({ manual: true });
                if (success && this.state.selectedSessionId) {
                    await this.fetchMessages(true, { manual: true });
                }
                if (success && this.connectionState === 'online') {
                    this.toast(this.config.strings?.refreshComplete || 'Sessions updated');
                } else if (!success) {
                    this.toast(this.config.strings?.refreshFailed || 'Refresh failed. Please try again.');
                }
            } catch (error) {
                console.error('LiveAgentCenter: manual refresh failed', error);
                this.toast(this.config.strings?.refreshFailed || 'Refresh failed. Please try again.');
            } finally {
                this.setRefreshLoading(false);
                this.startSessionPolling();
            }
        }

        setRefreshLoading(isLoading) {
            if (!this.elements.refreshButton) {
                return;
            }
            const button = this.elements.refreshButton;
            const label = button.querySelector('.pax-refresh-label');
            if (label && !button.dataset.labelDefault) {
                button.dataset.labelDefault = label.textContent.trim();
            }
            button.classList.toggle('is-loading', !!isLoading);
            button.disabled = !!isLoading;
            if (isLoading) {
                button.setAttribute('aria-busy', 'true');
            } else {
                button.removeAttribute('aria-busy');
            }
            if (label) {
                label.textContent = isLoading
                    ? (this.config.strings?.refreshing || 'Refreshing…')
                    : (this.config.strings?.refreshLabel || button.dataset.labelDefault || 'Refresh');
            }
        }

        setButtonLoading(button, isLoading) {
            if (!button) {
                return;
            }
            button.disabled = !!isLoading;
            button.classList.toggle('is-loading', !!isLoading);
            if (isLoading) {
                button.setAttribute('aria-busy', 'true');
            } else {
                button.removeAttribute('aria-busy');
            }
        }

        handleConnectivityDrop() {
            if (this.connectionState !== 'offline') {
                this.connectionState = 'offline';
                if (this.elements.root) {
                    this.elements.root.classList.add('is-offline');
                }
                this.toast(this.config.strings?.offlineNotice || 'Connection lost. We will retry automatically…');
            }
        }

        handleConnectivityRestore() {
            if (this.connectionState !== 'online') {
                this.connectionState = 'online';
                if (this.elements.root) {
                    this.elements.root.classList.remove('is-offline');
                }
                this.toast(this.config.strings?.reconnected || 'Back online — sessions are up to date.');
            }
        }

        wrapActionWithHelp(button, tooltip, key) {
            if (!button) {
                return null;
            }
            if (tooltip) {
                this.attachTooltip(button, tooltip);
            }
            const wrapper = document.createElement('div');
            wrapper.className = 'pax-action-with-help';
            wrapper.appendChild(button);
            const helpIcon = this.createHelpIcon(tooltip, key);
            if (helpIcon) {
                wrapper.appendChild(helpIcon);
            }
            return wrapper;
        }

        createHelpIcon(text, key) {
            if (!text) {
                return null;
            }
            const help = document.createElement('button');
            help.type = 'button';
            help.className = 'pax-help-icon';
            help.textContent = '?';
            if (key) {
                help.dataset.help = key;
            }
            help.setAttribute('aria-label', text);
            this.attachTooltip(help, text, { focus: true });
            help.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                this.toggleTooltip(help);
            });
            return help;
        }

        attachTooltip(target, text, options = {}) {
            if (!target || !text) {
                return;
            }
            const opts = {
                focus: false,
                ...options
            };
            target.dataset.tooltip = text;
            if (opts.focus && !target.hasAttribute('tabindex')) {
                target.setAttribute('tabindex', '0');
            }
            if (this.tooltipHandlers.has(target)) {
                return;
            }
            const show = () => this.showTooltip(target);
            const hide = () => this.hideTooltip(target);
            const touch = (event) => {
                this.showTooltip(target);
                if (this.tooltipHideTimer) {
                    clearTimeout(this.tooltipHideTimer);
                }
                this.tooltipHideTimer = window.setTimeout(() => this.hideTooltip(target), 1800);
                event.preventDefault();
                event.stopPropagation();
            };
            target.addEventListener('mouseenter', show);
            target.addEventListener('mouseleave', hide);
            target.addEventListener('focus', show);
            target.addEventListener('blur', hide);
            target.addEventListener('touchstart', touch, { passive: false });
            this.tooltipHandlers.set(target, { show, hide, touch });
        }

        toggleTooltip(target) {
            if (this.tooltipElement && this.tooltipElement.classList.contains('is-visible') && this.currentTooltipTarget === target) {
                this.hideTooltip(target);
            } else {
                this.showTooltip(target);
            }
        }

        showTooltip(target) {
            if (!target || !target.dataset.tooltip) {
                return;
            }
            const tooltip = this.ensureTooltipElement();
            tooltip.textContent = target.dataset.tooltip;
            tooltip.classList.add('is-visible');
            this.currentTooltipTarget = target;

            window.requestAnimationFrame(() => {
                this.positionTooltip(target, tooltip);
            });
        }

        hideTooltip(target) {
            if (!this.tooltipElement) {
                return;
            }
            if (target && this.currentTooltipTarget && target !== this.currentTooltipTarget) {
                return;
            }
            this.tooltipElement.classList.remove('is-visible', 'pax-tooltip--below');
            this.currentTooltipTarget = null;
            if (this.tooltipHideTimer) {
                clearTimeout(this.tooltipHideTimer);
                this.tooltipHideTimer = null;
            }
        }

        ensureTooltipElement() {
            if (!this.tooltipElement) {
                this.tooltipElement = document.createElement('div');
                this.tooltipElement.className = 'pax-tooltip';
                document.body.appendChild(this.tooltipElement);
            }
            return this.tooltipElement;
        }

        positionTooltip(target, tooltip) {
            const rect = target.getBoundingClientRect();
            const tooltipRect = tooltip.getBoundingClientRect();
            const scrollY = window.scrollY || window.pageYOffset;
            const scrollX = window.scrollX || window.pageXOffset;

            let top = rect.top + scrollY - tooltipRect.height - 12;
            tooltip.classList.remove('pax-tooltip--below');
            if (top < scrollY + 8) {
                top = rect.bottom + scrollY + 12;
                tooltip.classList.add('pax-tooltip--below');
            }

            let left = rect.left + scrollX + (rect.width / 2) - (tooltipRect.width / 2);
            const minLeft = scrollX + 12;
            const maxLeft = scrollX + window.innerWidth - tooltipRect.width - 12;
            left = Math.min(Math.max(left, minLeft), maxLeft);

            tooltip.style.top = `${top}px`;
            tooltip.style.left = `${left}px`;
        }

        truncate(text, length = 80) {
            if (!text) {
                return '';
            }
            if (text.length <= length) {
                return text;
            }
            return `${text.slice(0, length - 1)}…`;
        }

        async pingRest() {
            if (!this.elements.pingStatus) {
                return;
            }
            const testing = this.config.strings?.pingTesting || 'Pinging…';
            const success = this.config.strings?.pingSuccess || 'REST API reachable';
            const errorText = this.config.strings?.pingError || 'Unable to reach REST API';

            this.elements.pingStatus.textContent = testing;
            try {
                const headers = new Headers();
                const liveNonce = window.PAX_LIVE?.nonce || this.config.nonce;
                if (liveNonce) {
                    headers.set('X-WP-Nonce', liveNonce);
                }
                if (window.PAX_LIVE?.noStore) {
                    headers.set('Cache-Control', 'no-store');
                }

                const response = await fetch(`${REST_ROUTES.status}?healthcheck=1`, {
                    method: 'GET',
                    headers,
                    credentials: 'same-origin',
                    cache: 'no-store'
                });
                const data = await response.json();
                if (data?.status === 'ok') {
                    this.elements.pingStatus.textContent = success;
                    this.elements.pingStatus.style.color = 'var(--pax-success)';
                } else {
                    throw new Error('Healthcheck failed');
                }
            } catch (error) {
                this.elements.pingStatus.textContent = errorText;
                this.elements.pingStatus.style.color = varColor('--pax-accent');
            } finally {
                window.setTimeout(() => {
                    if (this.elements.pingStatus) {
                        this.elements.pingStatus.textContent = '';
                        this.elements.pingStatus.style.color = '';
                    }
                }, 4000);
            }
        }

        toggleTyping(isTyping) {
            if (!this.elements.typing) {
                return;
            }
            this.elements.typing.hidden = !isTyping;
        }

        emitTyping(status) {
            if (!REST_ROUTES.typing || !this.state.selectedSessionId) {
                return;
            }
            if (this.state.typingTimeout) {
                clearTimeout(this.state.typingTimeout);
            }

            if (status) {
                this.request(REST_ROUTES.typing, {
                    method: 'POST',
                    body: {
                        session_id: this.state.selectedSessionId,
                        sender: 'agent',
                        is_typing: true
                    }
                });
                this.state.typingTimeout = window.setTimeout(() => {
                    this.emitTyping(false);
                }, 2000);
            } else {
                this.request(REST_ROUTES.typing, {
                    method: 'POST',
                    body: {
                        session_id: this.state.selectedSessionId,
                        sender: 'agent',
                        is_typing: false
                    }
                });
            }
        }

        notify() {
            if (!this.state.audioEnabled) {
                return;
            }
            this.playDing();
        }

        async request(url, options) {
            const headers = new Headers(options?.headers || {});
            if (!options?.isForm && options?.method && options.method.toUpperCase() !== 'GET') {
                headers.set('Content-Type', 'application/json');
            }
            const liveNonce = window.PAX_LIVE?.nonce || this.config.nonce;
            if (liveNonce) {
                headers.set('X-WP-Nonce', liveNonce);
            }
            if (window.PAX_LIVE?.noStore) {
                headers.set('Cache-Control', 'no-store');
            }

            const fetchOptions = {
                method: options?.method || 'GET',
                headers,
                credentials: 'same-origin',
                cache: 'no-store'
            };

            if (options?.body) {
                fetchOptions.body = options.isForm ? options.body : JSON.stringify(options.body);
            }

            const response = await fetch(url, fetchOptions);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        }

        getLiveString(key, fallback) {
            return (window.PAX_LIVE && window.PAX_LIVE.strings && window.PAX_LIVE.strings[key]) || fallback;
        }

        notifyNewRequest(session) {
            const label = this.getLiveString('newRequest', 'New live request');
            const name = session?.user_name || this.config.strings?.unknownUser || 'Guest';
            this.toast(`${label}: ${name}`);
            this.playDing();
        }

        notifyNewMessage() {
            const label = this.getLiveString('newMessage', 'New message');
            const sessionId = this.state.selectedSessionId;
            const summary = sessionId ? this.findSessionSummary(sessionId) : null;
            const name = summary?.user_name || this.config.strings?.unknownUser || 'Guest';
            this.toast(`${label}: ${name}`);
            this.playDing();
        }

        playDing() {
            if (!this.state.audioEnabled) {
                return;
            }

            const src = window.PAX_LIVE?.assets?.ding;
            if (src) {
                try {
                    const audio = new Audio(src);
                    audio.play().catch(() => {});
                    return;
                } catch (error) {
                    // Fallback to embedded chime element
                }
            }
            if (this.elements.chime) {
                try {
                    this.elements.chime.currentTime = 0;
                    this.elements.chime.play().catch(() => {});
                } catch (error) {
                    // ignore
                }
            }
        }

        toast(message) {
            if (!message) {
                return;
            }
            const toast = document.createElement('div');
            toast.className = 'pax-toast';
            toast.textContent = message;
            document.body.appendChild(toast);
            window.setTimeout(() => {
                toast.classList.add('pax-toast-hide');
                window.setTimeout(() => toast.remove(), 300);
            }, 4000);
        }
    }

    function ensureTrailingSlash(value) {
        if (!value) {
            return '';
        }
        return value.endsWith('/') ? value : `${value}/`;
    }

    function varColor(token) {
        if (!token.startsWith('--')) {
            return token;
        }
        return getComputedStyle(document.documentElement).getPropertyValue(token) || token;
    }

    window.addEventListener('DOMContentLoaded', () => {
        new LiveAgentCenter(config);
    });
})();
