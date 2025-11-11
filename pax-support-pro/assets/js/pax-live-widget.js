/**
 * Live Agent widget helpers.
 * Ensures every REST call ships nonce headers, same-origin credentials, and no-store cache policy.
 */
(function() {
    'use strict';

    const BASE = (window.PAX_LIVE?.restBase || `${window.location.origin}/wp-json/pax/v1/`).replace(/\/?$/, '/');
    const NONCE = window.PAX_LIVE?.nonce || window.wpApiSettings?.nonce || '';
    const LP = {
        controller: null,
        lastId: 0,
        sessionId: null,
        dedupe: new Set(),
        retryDelay: 400,
        onMessage: null,
        online: (typeof navigator === 'undefined') ? true : navigator.onLine !== false,
    };

    const HEADERS = (withJson = true) => {
        const headers = { 'X-WP-Nonce': NONCE, 'Cache-Control': 'no-store' };
        if (withJson) {
            headers['Content-Type'] = 'application/json';
        }
        return headers;
    };

    function showBanner(state, customText) {
        const banner = document.querySelector('.pax-live-banner') || (function create() {
            const el = document.createElement('div');
            el.className = 'pax-live-banner';
            el.setAttribute('role', 'status');
            el.setAttribute('aria-live', 'polite');
            const container = document.querySelector('.pax-chat-window') || document.querySelector('#pax-chat');
            if (container) {
                container.prepend(el);
            }
            return el;
        }());

        if (!banner) {
            return;
        }

        const dots = '<span class="pax-conn-dot"></span><span class="pax-conn-dot"></span><span class="pax-conn-dot"></span>';
        const strings = window.PAX_LIVE?.strings || {};
        let text = customText || '';

        if (state === 'connecting') {
            text = text || strings.connecting || 'Connecting to support…';
            banner.className = 'pax-live-banner pax-live-connecting';
            banner.innerHTML = `${dots}<span>${text}</span>`;
        } else if (state === 'queue') {
            text = text || strings.queued || 'You are now in queue, please wait…';
            banner.className = 'pax-live-banner pax-live-queue';
            banner.innerHTML = `${dots}<span>${text}</span>`;
        } else if (state === 'connected') {
            text = text || strings.connected || 'Agent connected!';
            banner.className = 'pax-live-banner pax-live-connected';
            banner.textContent = text;
        } else if (state === 'error') {
            text = text || strings.statusError || 'Unable to connect right now. Please try again.';
            banner.className = 'pax-live-banner pax-live-error';
            banner.textContent = text;
        } else if (state === 'hide') {
            banner.remove();
        }
    }

    async function ensureSession(cache) {
        if (cache?.sessionId) {
            return cache;
        }

        const response = await fetch(`${BASE}live/session`, {
            method: 'POST',
            headers: HEADERS(),
            credentials: 'same-origin',
            cache: 'no-store',
            body: JSON.stringify({
                page_url: window.location.href,
                user_agent: navigator.userAgent
            })
        });

        const data = await response.json();
        if (data?.success && (data.session?.id || data.session_id)) {
            return {
                sessionId: data.session?.id || data.session_id,
                status: data.session?.status || data.status || 'pending'
            };
        }

        showBanner('error', window.PAX_LIVE?.strings?.statusError);
        throw new Error(data?.message || 'Failed to create session');
    }

    async function sendMessage(sessionId, content) {
        const response = await fetch(`${BASE}live/message`, {
            method: 'POST',
            headers: HEADERS(),
            credentials: 'same-origin',
            cache: 'no-store',
            body: JSON.stringify({ session_id: sessionId, content })
        });
        return response.json();
    }

    async function fetchMessages(sessionId) {
        const response = await fetch(`${BASE}live/messages?session_id=${encodeURIComponent(sessionId)}`, {
            method: 'GET',
            headers: HEADERS(false),
            credentials: 'same-origin',
            cache: 'no-store'
        });
        return response.json();
    }

    function pumpLongPoll(force = false) {
        if (!LP.sessionId || !LP.online) {
            return;
        }

        if (LP.controller && !force) {
            return;
        }

        if (LP.controller) {
            try {
                LP.controller.abort();
            } catch (error) {
                // ignore
            }
        }

        LP.controller = new AbortController();

        const query = new URLSearchParams({
            session_id: LP.sessionId,
            after: LP.lastId,
            wait: 25
        });

        fetch(`${BASE}live/messages?${query.toString()}`, {
            method: 'GET',
            headers: HEADERS(false),
            credentials: 'same-origin',
            cache: 'no-store',
            signal: LP.controller.signal
        })
            .then((response) => response.json())
            .then((data) => {
                LP.retryDelay = 50;
                if (!data || !Array.isArray(data.messages)) {
                    return;
                }
                data.messages.forEach((message) => {
                    const id = parseInt(message.seq || message.id || 0, 10);
                    if (!id) {
                        return;
                    }
                    if (LP.dedupe.has(id)) {
                        return;
                    }
                    LP.dedupe.add(id);
                    if (LP.dedupe.size > 500) {
                        const first = LP.dedupe.values().next().value;
                        LP.dedupe.delete(first);
                    }
                    LP.lastId = Math.max(LP.lastId, id);
                    if (typeof LP.onMessage === 'function') {
                        LP.onMessage(message, data);
                    }
                });
            })
            .catch(() => {
                LP.retryDelay = Math.min(LP.retryDelay * 2, 4000);
            })
            .finally(() => {
                setTimeout(() => pumpLongPoll(), LP.retryDelay);
            });
    }

    function startLongPoll(sessionId, onMessage) {
        if (!sessionId) {
            return;
        }
        LP.sessionId = sessionId;
        LP.onMessage = typeof onMessage === 'function' ? onMessage : null;
        LP.retryDelay = 400;
        LP.lastId = 0;
        LP.dedupe.clear();
        pumpLongPoll(true);
    }

    function stopLongPoll() {
        if (LP.controller) {
            try {
                LP.controller.abort();
            } catch (error) {
                // ignore
            }
            LP.controller = null;
        }
    }

    window.addEventListener('online', () => {
        LP.online = true;
        pumpLongPoll(true);
    });

    window.addEventListener('offline', () => {
        LP.online = false;
        stopLongPoll();
    });

    window.PAXLiveWidget = {
        BASE,
        HEADERS,
        ensureSession,
        sendMessage,
        fetchMessages,
        showBanner,
        startLongPoll,
        stopLongPoll,
        LP
    };
})();
