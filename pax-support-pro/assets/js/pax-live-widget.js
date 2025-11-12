/**
 * Live Agent transport and UI helper.
 * Restores hard live mode, instant polling with ETag/304, offline queueing,
 * accessibility affordances, and safe mobile viewport behaviour.
 */
(function() {
    'use strict';

    const WIN = window;
    const DOC = document;
    const CONFIG = WIN.PAX_LIVE || {};

    const DEFAULT_STRINGS = {
        connecting: 'Connecting to support…',
        queued: 'Queued – waiting for connection…',
        connected: 'Agent connected!',
        closed: 'Chat closed.',
        statusError: 'Unable to connect right now. Please try again.',
        typeHere: 'Type your message…',
        endChat: 'End chat',
        rateChat: 'Rate chat',
        submit: 'Submit',
        startNewChat: 'Start a new chat',
        offline: 'You appear to be offline. Messages will be queued.',
        reconnected: 'Back online — resuming chat.',
    };

    const ensureTrailingSlash = (input) => {
        if (!input) {
            return '/';
        }
        return input.endsWith('/') ? input : `${input}/`;
    };

    const uniqueId = (prefix = 'client') => `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2, 10)}`;

    class LiveAgentWidget {
        constructor(settings) {
            this.config = Object.assign(
                {
                    restBase: ensureTrailingSlash(`${WIN.location.origin}/wp-json/pax/v1/`),
                    nonce: '',
                    pollInterval: 1000,
                    heartbeatInterval: 30000,
                },
                settings || {}
            );

            this.strings = Object.assign({}, DEFAULT_STRINGS, settings?.strings || {});

            this.state = {
                sessionId: null,
                status: 'idle',
                lastId: 0,
                etag: '',
                polling: null,
                heartbeat: null,
                fetching: false,
                online: typeof navigator === 'undefined' ? true : navigator.onLine !== false,
                backoff: this.config.pollInterval,
            };

            this.queue = [];
            this.optimisticOrder = [];
            this.flushInProgress = false;
            this.listeners = {
                messages: new Set(),
                status: new Set(),
                queue: new Set(),
                connectivity: new Set(),
            };

            this.banner = null;
            this.container = null;
            this.composerInput = null;
            this.boundComposerHandler = null;

            this.handleOnline = this.handleOnline.bind(this);
            this.handleOffline = this.handleOffline.bind(this);
            this.handleVisibility = this.handleVisibility.bind(this);
            this.handleViewport = this.handleViewport.bind(this);

            this.applyViewportMetrics();
            this.bindGlobalEvents();
            this.handleViewport();
        }

        bindGlobalEvents() {
            WIN.addEventListener('online', this.handleOnline);
            WIN.addEventListener('offline', this.handleOffline);
            DOC.addEventListener('visibilitychange', this.handleVisibility);

            if (WIN.visualViewport) {
                WIN.visualViewport.addEventListener('resize', this.handleViewport, { passive: true });
                WIN.visualViewport.addEventListener('scroll', this.handleViewport, { passive: true });
            } else {
                WIN.addEventListener('resize', this.handleViewport, { passive: true });
            }
        }

        applyViewportMetrics(height, offset) {
            const viewportHeight = height || WIN.innerHeight || DOC.documentElement.clientHeight || 0;
            const keyboardOffset = offset || 0;
            DOC.documentElement.style.setProperty('--pax-live-viewport-height', `${viewportHeight}px`);
            DOC.documentElement.style.setProperty('--pax-live-viewport-offset', `${keyboardOffset}px`);
            DOC.documentElement.style.setProperty('--pax-live-safe-bottom', `calc(env(safe-area-inset-bottom, 0px) + ${keyboardOffset}px)`);
        }

        handleViewport() {
            if (!WIN.visualViewport) {
                this.applyViewportMetrics(WIN.innerHeight, 0);
                return;
            }
            const vv = WIN.visualViewport;
            const totalHeight = WIN.innerHeight || DOC.documentElement.clientHeight || vv.height;
            const keyboardOffset = Math.max(0, totalHeight - vv.height - vv.offsetTop);
            this.applyViewportMetrics(vv.height, keyboardOffset);
        }

        headers(withJson = true) {
            const headers = {
                'X-WP-Nonce': this.config.nonce || '',
                'Cache-Control': 'no-store',
            };
            if (withJson) {
                headers['Content-Type'] = 'application/json';
            }
            return headers;
        }

        async ensureSession(options = {}) {
            if (this.state.sessionId && !options.force) {
                return { id: this.state.sessionId, status: this.state.status, session: this.sessionSummary || {} };
            }

            this.applyHardMode();

            const payload = {
                page_url: options.pageUrl || WIN.location.href,
                user_agent: options.userAgent || navigator.userAgent,
                user_meta: options.userMeta || {},
            };

            const response = await fetch(`${this.config.restBase}live/session`, {
                method: 'POST',
                headers: this.headers(),
                credentials: 'same-origin',
                cache: 'no-store',
                body: JSON.stringify(payload),
            });

            const data = await response.json();

            if (!response.ok || !data?.success) {
                this.showBanner('error', this.strings.statusError);
                throw new Error(data?.message || 'Failed to create session');
            }

            const summary = data.session || {};
            const sessionId = summary.id || data.session_id;

            this.state.sessionId = sessionId;
            this.state.status = summary.status || data.status || 'pending';
            this.state.lastId = 0;
            this.state.etag = '';
            this.sessionSummary = summary;
            this.optimisticOrder = [];
            this.queue = [];
            this.emitQueue();
            this.emitStatus({ summary });

            if (options.force) {
                this.stop();
            }

            return { id: sessionId, session: summary };
        }

        applyHardMode() {
            DOC.documentElement.classList.add('pax-live-mode');
            WIN.dispatchEvent(new CustomEvent('pax:live-mode-on'));
        }

        setContainer(node) {
            if (!node || !(node instanceof Element)) {
                return;
            }
            this.container = node;
            this.container.dataset.liveReady = '1';
        }

        setComposer(input) {
            if (!(input instanceof Element)) {
                return;
            }
            if (this.boundComposerHandler) {
                input.removeEventListener('keydown', this.boundComposerHandler);
            }
            input.setAttribute('aria-label', this.strings.typeHere || DEFAULT_STRINGS.typeHere);
            input.setAttribute('autocomplete', 'off');
            input.setAttribute('autocapitalize', 'sentences');
            input.setAttribute('spellcheck', 'true');
            this.composerInput = input;
            this.boundComposerHandler = (event) => {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    WIN.dispatchEvent(new CustomEvent('pax:live-enter', { detail: { value: input.value } }));
                }
            };
            input.addEventListener('keydown', this.boundComposerHandler);
        }

        start(options = {}) {
            if (options.sessionId) {
                this.state.sessionId = options.sessionId;
            }
            if (!this.state.sessionId) {
                return;
            }
            this.stopPolling();
            this.clearHeartbeat();
            this.fetchMessages(true);
            this.scheduleHeartbeat();
        }

        stop() {
            this.stopPolling();
            this.clearHeartbeat();
        }

        stopPolling() {
            if (this.state.polling) {
                clearTimeout(this.state.polling);
                this.state.polling = null;
            }
        }

        schedulePoll(delay) {
            this.stopPolling();
            const interval = typeof delay === 'number' ? delay : this.getNextInterval();
            this.state.polling = setTimeout(() => this.fetchMessages(), interval);
        }

        scheduleHeartbeat() {
            this.clearHeartbeat();
            this.state.heartbeat = setTimeout(() => {
                this.fetchMessages(true);
                this.scheduleHeartbeat();
            }, this.config.heartbeatInterval);
        }

        clearHeartbeat() {
            if (this.state.heartbeat) {
                clearTimeout(this.state.heartbeat);
                this.state.heartbeat = null;
            }
        }

        async fetchMessages(force = false) {
            if (!this.state.sessionId) {
                return;
            }
            if (this.state.fetching && !force) {
                return;
            }

            this.state.fetching = true;

            try {
                const headers = this.headers(false);
                if (this.state.etag) {
                    headers['If-None-Match'] = this.state.etag;
                }

                const params = new URLSearchParams({
                    session_id: this.state.sessionId,
                    after: this.state.lastId,
                });

                const response = await fetch(`${this.config.restBase}live/messages?${params.toString()}`, {
                    method: 'GET',
                    headers,
                    credentials: 'same-origin',
                    cache: 'no-store',
                });

                if (response.status === 304) {
                    this.state.backoff = this.config.pollInterval;
                    return;
                }

                const data = await response.json();

                if (!response.ok || data?.success === false) {
                    throw new Error(data?.message || 'Failed to fetch messages');
                }

                const etag = response.headers.get('ETag');
                if (etag) {
                    this.state.etag = etag;
                }

                if (typeof data.last_id === 'number') {
                    this.state.lastId = data.last_id;
                }

                if (Array.isArray(data.messages) && data.messages.length) {
                    this.processIncomingMessages(data.messages, data);
                } else if (data.status) {
                    this.state.status = data.status;
                    this.emitStatus(data);
                }

                this.state.backoff = this.config.pollInterval;
            } catch (error) {
                console.error('[PAX Live] fetchMessages error:', error);
                this.state.backoff = Math.min(this.state.backoff * 1.5, 8000);
            } finally {
                this.state.fetching = false;
                this.schedulePoll();
            }
        }

        getNextInterval() {
            if (!this.state.online) {
                return Math.min(this.state.backoff * 2, 10000);
            }
            if (DOC.hidden) {
                return Math.max(this.config.pollInterval * 3, 3000);
            }
            return Math.max(this.state.backoff, this.config.pollInterval);
        }

        processIncomingMessages(messages, meta = {}) {
            const normalized = messages.map((message) => this.normalizeMessage(message));

            normalized.forEach((message) => {
                if (message.sender === 'user' && this.optimisticOrder.length) {
                    message.clientId = this.optimisticOrder.shift();
                }
            });

            this.emitMessages(normalized, meta);

            if (meta.status) {
                this.state.status = meta.status;
                this.emitStatus(meta);
            }
        }

        normalizeMessage(raw) {
            const seq = parseInt(raw.seq || raw.id || 0, 10) || Date.now();
            const timestamp = raw.timestamp || raw.created_at || new Date().toISOString();
            const sender = raw.sender || (raw.role === 'admin' ? 'agent' : 'user');
            const role = raw.role || (sender === 'agent' ? 'admin' : 'user');
            return {
                id: seq,
                seq,
                sender,
                role,
                content: raw.content || raw.message || '',
                timestamp,
                created_at: raw.created_at || timestamp,
                meta: raw.meta || {},
                clientId: raw.clientId || null,
                pending: !!raw.pending,
            };
        }

        emitMessages(messages, meta = {}) {
            const detail = Object.assign(
                {
                    sessionId: this.state.sessionId,
                    messages,
                    lastId: this.state.lastId,
                    status: this.state.status,
                },
                meta
            );

            WIN.dispatchEvent(new CustomEvent('pax:live-messages', { detail }));
            this.listeners.messages.forEach((listener) => {
                try {
                    listener(detail);
                } catch (error) {
                    console.error('[PAX Live] Message listener error:', error);
                }
            });
        }

        emitStatus(meta = {}) {
            const detail = Object.assign(
                {
                    sessionId: this.state.sessionId,
                    status: this.state.status,
                },
                meta
            );

            if (this.container) {
                this.container.dataset.liveStatus = detail.status || '';
            }

            WIN.dispatchEvent(new CustomEvent('pax:live-status', { detail }));
            this.listeners.status.forEach((listener) => {
                try {
                    listener(detail);
                } catch (error) {
                    console.error('[PAX Live] Status listener error:', error);
                }
            });
        }

        emitQueue() {
            const detail = {
                sessionId: this.state.sessionId,
                length: this.queue.length,
            };
            WIN.dispatchEvent(new CustomEvent('pax:live-queue', { detail }));
            this.listeners.queue.forEach((listener) => {
                try {
                    listener(detail);
                } catch (error) {
                    console.error('[PAX Live] Queue listener error:', error);
                }
            });
        }

        emitConnectivity() {
            const detail = { online: this.state.online };
            WIN.dispatchEvent(new CustomEvent('pax:live-connectivity', { detail }));
            this.listeners.connectivity.forEach((listener) => {
                try {
                    listener(detail);
                } catch (error) {
                    console.error('[PAX Live] Connectivity listener error:', error);
                }
            });
        }

        async sendMessage(options = {}) {
            const sessionId = options.sessionId || this.state.sessionId;
            const content = (options.content || '').trim();
            if (!sessionId || !content) {
                throw new Error('Session ID and message content are required.');
            }

            const clientId = options.clientId || uniqueId('client');
            const optimisticPayload = {
                id: Date.now(),
                seq: Date.now(),
                sender: 'user',
                role: 'user',
                content,
                timestamp: new Date().toISOString(),
                created_at: new Date().toISOString(),
                pending: true,
                clientId,
            };

            this.optimisticOrder.push(clientId);
            this.emitMessages([optimisticPayload], { optimistic: true });

            const payload = {
                session_id: sessionId,
                content,
            };

            if (options.replyTo) {
                payload.reply_to = options.replyTo;
            }
            if (clientId) {
                payload.client_id = clientId;
            }

            if (!this.state.online) {
                this.queue.push(payload);
                this.emitQueue();
                this.showBanner('queued', this.strings.queued);
                return { queued: true, clientId };
            }

            try {
                const result = await this.sendMessageInternal(payload);
                this.fetchMessages(true);
                return Object.assign({ clientId }, result);
            } catch (error) {
                console.error('[PAX Live] sendMessage error:', error);
                this.queue.push(payload);
                this.emitQueue();
                this.showBanner('queued', this.strings.queued);
                throw error;
            }
        }

        async sendMessageInternal(payload, fromQueue = false) {
            const response = await fetch(`${this.config.restBase}live/message`, {
                method: 'POST',
                headers: this.headers(),
                credentials: 'same-origin',
                cache: 'no-store',
                body: JSON.stringify(payload),
            });

            const data = await response.json();
            if (!response.ok || data?.success === false) {
                if (!fromQueue) {
                    throw new Error(data?.message || 'Failed to send message');
                }
                throw new Error('Queue send failure');
            }

            this.state.backoff = this.config.pollInterval;
            return { success: true, data };
        }

        flushQueue() {
            if (this.flushInProgress || !this.queue.length || !this.state.online) {
                return;
            }

            this.flushInProgress = true;

            const processNext = async () => {
                if (!this.queue.length || !this.state.online) {
                    this.flushInProgress = false;
                    this.emitQueue();
                    return;
                }

                const payload = this.queue[0];

                try {
                    await this.sendMessageInternal(payload, true);
                    this.queue.shift();
                    this.emitQueue();
                    this.fetchMessages(true);
                    setTimeout(processNext, 150);
                } catch (error) {
                    console.error('[PAX Live] flushQueue error:', error);
                    this.flushInProgress = false;
                    this.state.backoff = Math.min(this.state.backoff * 2, 8000);
                }
            };

            processNext();
        }

        handleOnline() {
            this.state.online = true;
            this.emitConnectivity();
            this.showBanner('reconnected', this.strings.reconnected);
            this.flushQueue();
            this.schedulePoll(100);
        }

        handleOffline() {
            this.state.online = false;
            this.emitConnectivity();
            this.showBanner('offline', this.strings.offline);
        }

        handleVisibility() {
            if (!this.state.sessionId) {
                return;
            }
            if (!DOC.hidden) {
                this.fetchMessages(true);
            }
        }

        showBanner(state, customText) {
            if (state === 'hide') {
                this.hideBanner();
                return;
            }

            let banner = this.banner;
            if (!banner) {
                banner = document.createElement('div');
                banner.className = 'pax-live-banner';
                banner.setAttribute('role', 'status');
                banner.setAttribute('aria-live', 'polite');
                const container = DOC.querySelector('.pax-chat-window') || DOC.querySelector('#pax-chat') || DOC.body;
                if (container && container.firstChild) {
                    container.insertBefore(banner, container.firstChild);
                } else if (container) {
                    container.append(banner);
                }
                this.banner = banner;
            }

            const dots = '<span class="pax-conn-dot"></span><span class="pax-conn-dot"></span><span class="pax-conn-dot"></span>';
            const textFromState = (fallback) => customText || this.strings[state] || fallback;

            if (state === 'connecting') {
                banner.className = 'pax-live-banner pax-live-connecting';
                banner.innerHTML = `${dots}<span>${textFromState(DEFAULT_STRINGS.connecting)}</span>`;
            } else if (state === 'queue' || state === 'queued') {
                banner.className = 'pax-live-banner pax-live-queue';
                banner.innerHTML = `${dots}<span>${textFromState(DEFAULT_STRINGS.queued)}</span>`;
            } else if (state === 'connected') {
                banner.className = 'pax-live-banner pax-live-connected';
                banner.textContent = textFromState(DEFAULT_STRINGS.connected);
            } else if (state === 'offline') {
                banner.className = 'pax-live-banner pax-live-offline';
                banner.textContent = textFromState(DEFAULT_STRINGS.offline);
            } else if (state === 'reconnected') {
                banner.className = 'pax-live-banner pax-live-reconnected';
                banner.textContent = textFromState(DEFAULT_STRINGS.reconnected);
                setTimeout(() => {
                    if (this.banner === banner) {
                        this.hideBanner();
                    }
                }, 2500);
            } else if (state === 'error') {
                banner.className = 'pax-live-banner pax-live-error';
                banner.textContent = textFromState(DEFAULT_STRINGS.statusError);
            }
        }

        hideBanner() {
            if (this.banner && this.banner.parentNode) {
                this.banner.parentNode.removeChild(this.banner);
            }
            this.banner = null;
        }

        subscribe(type, listener) {
            if (!listener || typeof listener !== 'function' || !this.listeners[type]) {
                return () => {};
            }
            this.listeners[type].add(listener);
            return () => this.unsubscribe(type, listener);
        }

        unsubscribe(type, listener) {
            if (listener && this.listeners[type]) {
                this.listeners[type].delete(listener);
            }
        }

        fetchNow() {
            this.fetchMessages(true);
        }
    }

    const widget = new LiveAgentWidget({
        restBase: ensureTrailingSlash(CONFIG.restBase || CONFIG.base || `${WIN.location.origin}/wp-json/pax/v1/`),
        nonce: CONFIG.nonce || WIN.wpApiSettings?.nonce || '',
        strings: CONFIG.strings || {},
        pollInterval: CONFIG.pollInterval || 1000,
        heartbeatInterval: CONFIG.heartbeatInterval || 30000,
    });

    WIN.PAXLiveWidget = {
        HEADERS: (withJson = true) => widget.headers(withJson),
        ensureSession: (options) => widget.ensureSession(options || {}),
        sendMessage: (options) => widget.sendMessage(options || {}),
        start: (options) => widget.start(options || {}),
        stop: () => widget.stop(),
        fetchNow: () => widget.fetchNow(),
        flushQueue: () => widget.flushQueue(),
        showBanner: (state, text) => widget.showBanner(state, text),
        hideBanner: () => widget.hideBanner(),
        setComposer: (input) => widget.setComposer(input),
        setContainer: (node) => widget.setContainer(node),
        applyHardMode: () => widget.applyHardMode(),
        subscribe: (type, listener) => widget.subscribe(type, listener),
        unsubscribe: (type, listener) => widget.unsubscribe(type, listener),
        queueLength: () => widget.queue.length,
        state: widget.state,
    };
})();
