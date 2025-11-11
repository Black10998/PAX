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

    const HEADERS = (withJson = true) => {
        const headers = {};
        if (withJson) {
            headers['Content-Type'] = 'application/json';
        }
        const nonce = window.PAX_LIVE?.nonce || config.nonce || '';
        if (nonce) {
            headers['X-WP-Nonce'] = nonce;
        }
        headers['Cache-Control'] = 'no-store';
        return headers;
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
            this.sessionPoll = null;
            this.messageStreamTimer = null;
            this.messageStreamController = null;

            this.init();
        }

        init() {
            this.cacheElements();
            this.bindEvents();
            this.renderDiagnostics();
            this.fetchSessions(true);
            this.startSessionPolling();

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
            this.elements.pingButton = document.getElementById('pax-liveagent-ping');
            this.elements.pingStatus = document.getElementById('pax-liveagent-ping-status');
            this.elements.restLabel = document.getElementById('pax-liveagent-rest');
            this.elements.chime = document.getElementById('pax-liveagent-chime');
            this.elements.detailEmail = document.getElementById('pax-liveagent-detail-email');
            this.elements.detailDomain = document.getElementById('pax-liveagent-detail-domain');
            this.elements.detailAuth = document.getElementById('pax-liveagent-detail-auth');
            this.elements.detailStarted = document.getElementById('pax-liveagent-detail-started');
            this.elements.detailLast = document.getElementById('pax-liveagent-detail-last');
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

                    if (event.target.matches('[data-action="accept"]')) {
                        event.stopPropagation();
                        this.acceptSession(sessionId, card);
                        return;
                    }

                    if (event.target.matches('[data-action="decline"]')) {
                        event.stopPropagation();
                        this.declineSession(sessionId);
                        return;
                    }

                    this.loadSession(sessionId, status);
                });
            });

            if (this.elements.sendButton) {
                this.elements.sendButton.addEventListener('click', () => {
                    this.sendMessage();
                });
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
            if (this.sessionPoll) {
                clearInterval(this.sessionPoll);
                this.sessionPoll = null;
            }
            this.fetchSessions();
            if (!this.sessionPoll) {
                this.sessionPoll = window.setInterval(() => this.fetchSessions(), 2000);
            }
        }

        startMessagePolling() {
            this.stopMessagePolling();
            if (!this.state.selectedSessionId) {
                return;
            }
            const loop = async () => {
                if (!this.state.selectedSessionId) {
                    return;
                }
                await this.fetchMessages(true, 25);
                this.messageStreamTimer = window.setTimeout(loop, 60);
            };
            loop();
        }

        stopMessagePolling() {
            if (this.messageStreamTimer) {
                clearTimeout(this.messageStreamTimer);
                this.messageStreamTimer = null;
            }
            if (this.messageStreamController) {
                try {
                    this.messageStreamController.abort();
                } catch (error) {
                    // ignore abort errors
                }
                this.messageStreamController = null;
            }
        }

        async fetchSessions(initial = false) {
            const previousPendingIds = new Set((this.state.sessions.pending || []).map((session) => session.id));
            try {
                const response = await fetch(`${API_BASE}live/sessions?limit=30`, {
                    method: 'GET',
                    headers: HEADERS(false),
                    credentials: 'same-origin',
                    cache: 'no-store'
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    return;
                }

                this.state.sessions.pending = data.pending || [];
                this.state.sessions.active = data.active || [];
                this.state.sessions.recent = data.recent || [];

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
            } catch (error) {
                console.error('LiveAgentCenter: failed to fetch sessions', error);
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

            if (this.elements.counters.pending) {
                this.elements.counters.pending.textContent = this.state.sessions.pending.length;
            }
            if (this.elements.counters.active) {
                this.elements.counters.active.textContent = this.state.sessions.active.length;
            }

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
                actions.appendChild(accept);

                const decline = document.createElement('button');
                decline.className = 'button';
                decline.dataset.action = 'decline';
                decline.innerHTML = `<span class="dashicons dashicons-no-alt"></span>${this.config.strings?.decline || 'Decline'}`;
                actions.appendChild(decline);

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
            if (this.elements.detailDomain) {
                this.elements.detailDomain.textContent = session.domain || '—';
            }
            if (this.elements.detailAuth) {
                this.elements.detailAuth.textContent = this.formatAuthProvider(session.auth_plugin);
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
                accept.addEventListener('click', () => this.acceptSession(session.id));

                const decline = this.createHeaderButton('button button-danger', this.config.strings?.decline || 'Decline', 'dashicons-no-alt');
                decline.addEventListener('click', () => this.declineSession(session.id));

                this.elements.actionBar.append(accept, decline);
            } else if (session.status === 'active') {
                const close = this.createHeaderButton('button button-danger', this.config.strings?.close || 'End Session', 'dashicons-no-alt');
                close.addEventListener('click', () => this.closeSession(session.id));
                this.elements.actionBar.append(close);
            }
        }

        handleStatusChange(status, payload = {}) {
            if (!this.state.selectedSessionId) {
                return;
            }

            const summary = this.findSessionSummary(this.state.selectedSessionId);
            if (summary) {
                summary.status = status;
                if (payload?.agent) {
                    summary.agent = payload.agent;
                    summary.agent_id = payload.agent.id;
                }
                this.renderSessionHeader(summary);
            }

            if (status === 'closed' || status === 'declined') {
                this.toast(this.getLiveString('sessionClosed', 'Session closed'));
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

        async fetchMessages(incremental = false, wait = 0) {
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

            if (wait) {
                params.set('wait', Math.min(25, Math.max(1, wait)));
            }

            const previousUserMessageId = this.lastUserMessageId[sessionId] || null;

            try {
                const controller = (incremental && wait) ? new AbortController() : null;
                if (controller) {
                    this.messageStreamController = controller;
                }

                const response = await this.request(`${REST_ROUTES.messages}?${params.toString()}`, {
                    method: 'GET',
                    signal: controller ? controller.signal : undefined
                });

                if (!response.success) {
                    return;
                }

                if (response.session) {
                    this.renderSessionHeader(response.session);
                }

                const messages = response.messages || [];
                let latestUserMessageId = previousUserMessageId;
                if (messages.length) {
                    const userMessage = [...messages].reverse().find((msg) => msg.sender === 'user');
                    if (userMessage?.id) {
                        latestUserMessageId = userMessage.id;
                    }
                }

                if (!incremental) {
                    this.renderMessages(messages, true);
                    const lastMessage = response.session?.last_message;
                    if (lastMessage?.sender === 'user' && lastMessage?.id) {
                        this.lastUserMessageId[sessionId] = lastMessage.id;
                    }
                } else if (messages.length) {
                    this.renderMessages(messages, false);
                    if (latestUserMessageId && latestUserMessageId !== previousUserMessageId && response.session?.status === 'active') {
                        this.lastUserMessageId[sessionId] = latestUserMessageId;
                        this.notifyNewMessage();
                        this.markMessagesRead();
                    }
                }

                if (response.last_id) {
                    this.state.lastMessageId = response.last_id;
                } else if (response.messages && response.messages.length) {
                    const last = response.messages[response.messages.length - 1];
                    if (last?.id) {
                        this.state.lastMessageId = last.id;
                    }
                }

                if (response.status && response.status !== this.state.selectedSessionStatus) {
                    this.state.selectedSessionStatus = response.status;
                    this.handleStatusChange(response.status, response);
                }

                if (response.typing) {
                    this.toggleTyping(response.typing.user);
                }

                if (!incremental) {
                    this.scrollToBottom(true);
                }
            } catch (error) {
                if (error?.name === 'AbortError') {
                    return;
                }
                console.error('LiveAgentCenter: failed to fetch messages', error);
            } finally {
                if (controller && this.messageStreamController === controller) {
                    this.messageStreamController = null;
                }
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

            if (messages.length) {
                const last = messages[messages.length - 1];
                if (last?.id) {
                    this.state.lastMessageId = last.id;
                }
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

        async acceptSession(sessionId, card) {
            if (!sessionId) {
                return;
            }
            if (card) {
                card.classList.add('pax-session-card--processing');
            }
            try {
                await this.request(REST_ROUTES.accept, {
                    method: 'POST',
                    body: { session_id: sessionId }
                });
                this.fetchSessions();
                this.loadSession(sessionId, 'active');
            } catch (error) {
                console.error('LiveAgentCenter: accept failed', error);
            } finally {
                if (card) {
                    card.classList.remove('pax-session-card--processing');
                }
            }
        }

        async declineSession(sessionId) {
            if (!sessionId) {
                return;
            }
            const confirmMessage = this.config.strings?.confirmDecline || 'Decline this chat request?';
            if (!window.confirm(confirmMessage)) {
                return;
            }
            try {
                await this.request(REST_ROUTES.decline, {
                    method: 'POST',
                    body: { session_id: sessionId }
                });
                this.fetchSessions();
                if (this.state.selectedSessionId === sessionId) {
                    this.state.selectedSessionId = null;
                    this.elements.panel.hidden = true;
                    this.elements.emptyState.hidden = false;
                }
            } catch (error) {
                console.error('LiveAgentCenter: decline failed', error);
            }
        }

        async closeSession(sessionId) {
            if (!sessionId) {
                return;
            }
            const confirmMessage = this.config.strings?.confirmClose || 'End this chat session?';
            if (!window.confirm(confirmMessage)) {
                return;
            }
            try {
                await this.request(REST_ROUTES.close, {
                    method: 'POST',
                    body: { session_id: sessionId }
                });
                this.fetchSessions();
                this.loadSession(sessionId, 'recent');
            } catch (error) {
                console.error('LiveAgentCenter: close session failed', error);
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

                const response = await this.request(REST_ROUTES.message, {
                    method: 'POST',
                    body: payload
                });

                if (response.success) {
                    this.elements.input.value = '';
                    this.autoResizeInput();
                    this.emitTyping(false);
                }
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

        async pingRest() {
            if (!this.elements.pingStatus) {
                return;
            }
            const testing = this.config.strings?.pingTesting || 'Pinging…';
            const success = this.config.strings?.pingSuccess || 'REST API reachable';
            const errorText = this.config.strings?.pingError || 'Unable to reach REST API';

            this.elements.pingStatus.textContent = testing;
            try {
                const response = await fetch(`${REST_ROUTES.status}?healthcheck=1`, {
                    method: 'GET',
                    headers: HEADERS(false),
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
            const expectsJson = !options?.isForm && options?.method && options.method.toUpperCase() !== 'GET';
            const headers = new Headers(HEADERS(expectsJson));
            if (options?.headers) {
                Object.entries(options.headers).forEach(([key, value]) => {
                    if (typeof value !== 'undefined') {
                        headers.set(key, value);
                    }
                });
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

            if (options?.signal) {
                fetchOptions.signal = options.signal;
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
