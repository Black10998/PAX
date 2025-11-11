/**
 * Live Agent widget helpers.
 * Ensures every REST call ships nonce headers, same-origin credentials, and no-store cache policy.
 */
(function() {
    'use strict';

    const BASE = (window.PAX_LIVE?.restBase || `${window.location.origin}/wp-json/pax/v1/`).replace(/\/?$/, '/');
    const NONCE = window.PAX_LIVE?.nonce || window.wpApiSettings?.nonce || '';

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

    window.PAXLiveWidget = {
        BASE,
        HEADERS,
        ensureSession,
        sendMessage,
        fetchMessages,
        showBanner
    };
})();
