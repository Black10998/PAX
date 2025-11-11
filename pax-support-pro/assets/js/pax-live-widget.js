/**
 * Lightweight Live Agent widget utilities for direct REST access.
 * Ensures all requests include nonce, same-origin credentials, and no-store cache policy.
 */
(function() {
    'use strict';

    const PAX_API_ROOT = (window.wpApiSettings?.root || `${window.location.origin}/wp-json/`).replace(/\/?$/, '/');
    const BASE = (window.PAX_LIVE?.restBase || `${PAX_API_ROOT}pax/v1/`).replace(/\/?$/, '/');
    const NONCE = window.PAX_LIVE?.nonce || window.wpApiSettings?.nonce || '';

    function buildHeaders(extra) {
        return Object.assign(
            {
                'Content-Type': 'application/json',
                'X-WP-Nonce': NONCE
            },
            extra || {}
        );
    }

    async function createSession() {
        const payload = {
            page_url: window.location.href,
            user_agent: navigator.userAgent
        };

        const response = await fetch(`${BASE}live/session`, {
            method: 'POST',
            headers: buildHeaders(),
            credentials: 'same-origin',
            cache: 'no-store',
            body: JSON.stringify(payload)
        });

        const data = await response.json();
        console.log('[PAX-STATE] Session bootstrap:', data);
        return data;
    }

    async function acceptSession(sessionId) {
        const response = await fetch(`${BASE}live/accept`, {
            method: 'POST',
            headers: buildHeaders(),
            credentials: 'same-origin',
            cache: 'no-store',
            body: JSON.stringify({ session_id: sessionId })
        });

        const data = await response.json();
        console.log('[PAX-STATE] Session accepted:', data);
        return data;
    }

    async function paxSendMessage(sessionId, message, from = 'user') {
        const response = await fetch(`${BASE}live/message`, {
            method: 'POST',
            headers: buildHeaders(),
            credentials: 'same-origin',
            cache: 'no-store',
            body: JSON.stringify({
                session_id: sessionId,
                content: message,
                sender: from
            })
        });

        const data = await response.json();
        console.log('[PAX-CHAT] Message sent:', data);
        return data;
    }

    async function paxPollStatus(sessionId) {
        try {
            const response = await fetch(`${BASE}live/messages?session_id=${encodeURIComponent(sessionId)}`, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': NONCE
                },
                credentials: 'same-origin',
                cache: 'no-store'
            });

            const data = await response.json();
            console.log('[PAX-CHAT] Poll update:', data);

            if (data.messages) {
                updateChatUI(data.messages);
            }

            return data;
        } catch (error) {
            console.error('[PAX-ERROR] Poll failed', error);
            throw error;
        }
    }

    function startPolling(sessionId, interval = 3000) {
        paxPollStatus(sessionId);
        return window.setInterval(() => paxPollStatus(sessionId), interval);
    }

    function updateChatUI(messages) {
        const chatBox = document.querySelector('#pax-chat-messages, #pax-liveagent-messages');
        if (!chatBox) {
            return;
        }

        chatBox.innerHTML = '';
        messages.forEach((msg) => {
            const div = document.createElement('div');
            const sender = msg.sender || msg.from || 'user';
            div.className = sender === 'agent' || sender === 'admin' ? 'pax-msg-admin' : 'pax-msg-user';
            div.textContent = `${sender}: ${msg.message || msg.content || ''}`;
            chatBox.appendChild(div);
        });
    }

    async function initChat(sessionId) {
        await acceptSession(sessionId);
        return startPolling(sessionId);
    }

    window.PAXLiveWidget = {
        createSession,
        acceptSession,
        paxSendMessage,
        paxPollStatus,
        startPolling,
        updateChatUI,
        initChat
    };
})();
