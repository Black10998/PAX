/**
 * Live Agent front-end enhancer for PAX Support Pro v5.9.5
 * - Hard Live Agent mode (assistant paused, prompts hidden)
 * - 1s polling with after=last_id + ETag/304 and heartbeat reconnect
 * - Offline queueing, optimistic sends, rating modal, restart CTA
 * - Mobile safe-area handling (100dvh/visualViewport) + accessibility upgrades
 */
(function () {
    'use strict';

    const CONFIG = window.PAX_LIVE || {};
    const DEFAULT_STRINGS = {
        connecting: 'Connecting to a live agent…',
        queued: 'You are now in queue. Please wait…',
        connected: 'Agent connected!',
        closed: 'Chat ended — thanks for chatting with us!',
        statusError: 'Unable to connect right now. Please try again.',
        typeHere: 'Type your message…',
        endChat: 'End Chat',
        submit: 'Submit',
        startNewChat: 'Start New Chat',
        offline: 'You are offline. We will reconnect automatically…',
        reconnected: 'Back online — synced messages.',
        rateChat: 'Rate your experience'
    };
    const STRINGS = Object.assign({}, DEFAULT_STRINGS, CONFIG.strings || {});

    const MAX_TEXTAREA_HEIGHT = 184;
    const HEARTBEAT_INTERVAL = 30000;
    const INITIAL_POLL = 1000;
    const SLOW_POLL = 2000;
    const MAX_BACKOFF = 10000;

    function ensureExtras(instance) {
        if (!instance.__liveExtras) {
            instance.__liveExtras = {
                pollInterval: INITIAL_POLL,
                slowInterval: SLOW_POLL,
                ticks: 0,
                lastId: null,
                etag: null,
                pollTimer: null,
                stopRequested: false,
                heartbeatTimer: null,
                backoff: INITIAL_POLL,
                offline: false,
                offlineToastShown: false,
                pendingMessages: new Map(),
                queuedMessages: [],
                creatingSession: null,
                forceNewSession: false,
                hardMode: false,
                controls: {},
                enhanced: false,
                connectivityBound: false,
                viewportGuarded: false,
                bannerState: 'hidden',
                ratingOpen: false,
                lastClosedSessionId: null
            };
        }
        if (
            !instance.__liveExtras.originalSend &&
            instance.__proto__ &&
            instance.__proto__.__liveOriginalSend
        ) {
            instance.__liveExtras.originalSend = instance.__proto__.__liveOriginalSend;
        }
        return instance.__liveExtras;
    }

    function installLiveEnhancements(instance) {
        const extras = ensureExtras(instance);
        if (!instance.chatWindow || extras.enhanced) {
            return;
        }
        extras.enhanced = true;

        upgradeComposer(instance);
        createLiveBanner(instance);
        createLiveControls(instance);
        createRatingModal(instance);
        setupViewportGuards(instance);
        setupConnectivityListeners(instance);
        setupFocusManagement(instance);
        refreshControlLabels(instance);
    }

    function upgradeComposer(instance) {
        const extras = ensureExtras(instance);
        const composer = instance.chatWindow.querySelector('.pax-input-area');
        if (!composer) {
            return;
        }

        let field = instance.inputField;
        if (!field || field.tagName !== 'TEXTAREA') {
            const textarea = document.createElement('textarea');
            textarea.id = field ? field.id : 'pax-input';
            textarea.className = field ? `${field.className} pax-input-textarea` : 'pax-input-textarea';
            textarea.placeholder = STRINGS.typeHere;
            textarea.setAttribute('rows', '1');
            textarea.setAttribute('aria-label', STRINGS.typeHere);
            textarea.setAttribute('autocapitalize', 'sentences');
            textarea.setAttribute('autocomplete', 'off');
            textarea.setAttribute('spellcheck', 'true');
            textarea.style.resize = 'none';
            textarea.style.overflowY = 'auto';
            textarea.style.minHeight = '48px';

            if (field) {
                composer.replaceChild(textarea, field);
            } else {
                composer.insertBefore(textarea, composer.firstChild);
            }

            instance.inputField = textarea;
            extras.textarea = textarea;
        } else {
            field.setAttribute('aria-label', STRINGS.typeHere);
            field.placeholder = STRINGS.typeHere;
            field.style.resize = 'none';
            field.style.overflowY = 'auto';
            field.style.minHeight = '48px';
            extras.textarea = field;
        }

        const textarea = instance.inputField;
        if (textarea && !textarea.__paxLiveBound) {
            textarea.addEventListener('keydown', (event) => handleComposerKeydown(instance, event));
            textarea.addEventListener('input', () => autoSizeTextarea(textarea));
            textarea.addEventListener('focus', () => setTimeout(() => instance.scrollToBottom?.(), 0));
            textarea.__paxLiveBound = true;
        }

        autoSizeTextarea(textarea);

        const sendButton = composer.querySelector('#pax-send');
        if (sendButton) {
            sendButton.type = 'button';
            sendButton.setAttribute('aria-label', STRINGS.submit);
            instance.sendButton = sendButton;
        }

        composer.style.setProperty('--pax-safe-bottom', 'calc(env(safe-area-inset-bottom, 0px) + 8px)');
    }

    function autoSizeTextarea(textarea) {
        if (!textarea) {
            return;
        }
        textarea.style.height = 'auto';
        const next = Math.min(MAX_TEXTAREA_HEIGHT, Math.max(48, textarea.scrollHeight));
        textarea.style.height = `${next}px`;
    }

    function handleComposerKeydown(instance, event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            if (typeof instance.handleSend === 'function') {
                instance.handleSend();
            }
        } else if (event.key === 'Escape') {
            hideRatingModal(instance);
        }
    }

    function createLiveBanner(instance) {
        const extras = ensureExtras(instance);
        if (extras.controls.banner) {
            return;
        }
        const messages = instance.messageContainer;
        if (!messages || !messages.parentNode) {
            return;
        }

        const banner = document.createElement('div');
        banner.className = 'pax-live-banner pax-live-banner--hidden';
        banner.setAttribute('role', 'status');
        banner.setAttribute('aria-live', 'polite');
        banner.innerHTML = `<span class="pax-live-text">${STRINGS.connecting}</span>`;

        messages.parentNode.insertBefore(banner, messages);
        extras.controls.banner = banner;
    }

    function createLiveControls(instance) {
        const extras = ensureExtras(instance);
        const composer = instance.chatWindow.querySelector('.pax-input-area');
        if (!composer) {
            return;
        }

        let controlBar = instance.chatWindow.querySelector('.pax-liveagent-controls');
        if (!controlBar) {
            controlBar = document.createElement('div');
            controlBar.className = 'pax-liveagent-controls';
            composer.parentNode.insertBefore(controlBar, composer);
        }

        let endButton = controlBar.querySelector('.pax-liveagent-end');
        if (!endButton) {
            endButton = document.createElement('button');
            endButton.type = 'button';
            endButton.className = 'pax-liveagent-end';
            endButton.setAttribute('aria-label', STRINGS.endChat);
            endButton.textContent = STRINGS.endChat;
            endButton.hidden = true;
            controlBar.appendChild(endButton);
        }

        let restartButton = controlBar.querySelector('.pax-live-restart');
        if (!restartButton) {
            restartButton = document.createElement('button');
            restartButton.type = 'button';
            restartButton.className = 'pax-live-restart';
            restartButton.setAttribute('aria-label', STRINGS.startNewChat);
            restartButton.textContent = STRINGS.startNewChat;
            restartButton.hidden = true;
            controlBar.appendChild(restartButton);
        }

        endButton.addEventListener('click', () => handleEndChat(instance));
        restartButton.addEventListener('click', () => handleRestart(instance));

        extras.controls.controlBar = controlBar;
        extras.controls.endButton = endButton;
        extras.controls.restartButton = restartButton;
    }

    function refreshControlLabels(instance) {
        const extras = ensureExtras(instance);
        if (extras.controls.endButton) {
            extras.controls.endButton.textContent = STRINGS.endChat;
            extras.controls.endButton.setAttribute('aria-label', STRINGS.endChat);
        }
        if (extras.controls.restartButton) {
            extras.controls.restartButton.textContent = STRINGS.startNewChat;
            extras.controls.restartButton.setAttribute('aria-label', STRINGS.startNewChat);
        }
        if (instance.inputField) {
            instance.inputField.placeholder = STRINGS.typeHere;
            instance.inputField.setAttribute('aria-label', STRINGS.typeHere);
        }
        if (instance.sendButton) {
            instance.sendButton.setAttribute('aria-label', STRINGS.submit);
        }
    }

    function createRatingModal(instance) {
        const extras = ensureExtras(instance);
        if (extras.controls.ratingOverlay) {
            return;
        }

        const overlay = document.createElement('div');
        overlay.className = 'pax-rating-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('hidden', '');
        overlay.innerHTML = `
            <div class="pax-rating-modal" role="document">
                <header class="pax-rating-header">
                    <h2>${STRINGS.rateChat}</h2>
                </header>
                <div class="pax-rating-stars" role="radiogroup" aria-label="${STRINGS.rateChat}">
                    ${[1, 2, 3, 4, 5]
                        .map(
                            (value) => `
                        <button type="button" class="pax-rating-star" data-rating-star="${value}" data-value="${value}" aria-label="${value} ${value === 1 ? 'star' : 'stars'}" aria-pressed="${value === 5 ? 'true' : 'false'}">
                            <span aria-hidden="true">★</span>
                        </button>`
                        )
                        .join('')}
                </div>
                <label class="pax-rating-label" for="pax-rating-comment">${STRINGS.submit}</label>
                <textarea id="pax-rating-comment" class="pax-rating-comment" rows="4" placeholder="${STRINGS.typeHere}"></textarea>
                <div class="pax-rating-actions">
                    <button type="button" class="pax-rating-skip" data-rating-skip>${STRINGS.startNewChat}</button>
                    <button type="button" class="pax-rating-submit btn-primary" data-rating-submit>${STRINGS.submit}</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        const stars = Array.from(overlay.querySelectorAll('[data-rating-star]'));
        stars.forEach((button) => {
            button.addEventListener('click', () => setRatingValue(instance, parseInt(button.dataset.value, 10)));
            button.addEventListener('keydown', (event) => handleStarKeydown(instance, event));
        });

        const submitButton = overlay.querySelector('[data-rating-submit]');
        const skipButton = overlay.querySelector('[data-rating-skip]');
        const textarea = overlay.querySelector('.pax-rating-comment');

        submitButton.addEventListener('click', () => submitRating(instance));
        skipButton.addEventListener('click', () => skipRating(instance));

        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) {
                skipRating(instance);
            }
        });

        overlay.addEventListener('keydown', (event) => trapFocus(instance, event));

        extras.controls.ratingOverlay = overlay;
        extras.controls.ratingModal = overlay.querySelector('.pax-rating-modal');
        extras.controls.ratingStars = stars;
        extras.controls.ratingTextarea = textarea;
        extras.controls.ratingSubmit = submitButton;
        extras.controls.ratingSkip = skipButton;
        extras.ratingValue = 5;
    }

    function setupViewportGuards(instance) {
        const extras = ensureExtras(instance);
        if (extras.viewportGuarded || !window.visualViewport) {
            return;
        }
        const adjust = () => {
            const composer = instance.chatWindow.querySelector('.pax-input-area');
            if (!composer) {
                return;
            }
            const viewport = window.visualViewport;
            const delta = Math.max(0, window.innerHeight - viewport.height - viewport.offsetTop);
            composer.style.transform = delta > 0 ? `translateY(-${delta}px)` : '';
        };

        window.visualViewport.addEventListener('resize', adjust, { passive: true });
        window.visualViewport.addEventListener('scroll', adjust, { passive: true });
        extras.viewportGuarded = true;
        extras.viewportAdjust = adjust;
    }

    function setupConnectivityListeners(instance) {
        const extras = ensureExtras(instance);
        if (extras.connectivityBound) {
            return;
        }
        extras.connectivityBound = true;

        window.addEventListener('online', () => handleReconnected(instance));
        window.addEventListener('offline', () => handleOffline(instance, true));
        document.addEventListener('visibilitychange', () => handleVisibilityChange(instance));
    }

    function setupFocusManagement(instance) {
        const extras = ensureExtras(instance);
        if (extras.focusGuard) {
            return;
        }

        extras.focusGuard = true;
        if (instance.sendButton) {
            instance.sendButton.addEventListener('keydown', (event) => {
                if (event.key === 'Tab' && !event.shiftKey) {
                    const textarea = instance.inputField;
                    if (textarea) {
                        event.preventDefault();
                        textarea.focus();
                    }
                }
            });
        }
    }

    function activateHardLiveMode(instance) {
        const extras = ensureExtras(instance);
        if (extras.hardMode) {
            return;
        }
        extras.hardMode = true;

        document.documentElement.classList.add('pax-liveagent-engaged');
        document.body.classList.add('pax-liveagent-engaged');

        if (instance.chatWindow) {
            instance.chatWindow.classList.add('pax-liveagent-hard');
        }

        if (instance.modeSwitcher) {
            const assistantTab = instance.modeSwitcher.querySelector('[data-mode="assistant"]');
            if (assistantTab) {
                assistantTab.setAttribute('disabled', 'disabled');
                assistantTab.setAttribute('aria-disabled', 'true');
            }
        }

        if (typeof instance.removeQuickPromptsBar === 'function') {
            instance.removeQuickPromptsBar();
        }

        disableComposer(instance, STRINGS.connecting);
    }

    function deactivateHardLiveMode(instance) {
        const extras = ensureExtras(instance);
        if (!extras.hardMode) {
            return;
        }
        extras.hardMode = false;

        document.documentElement.classList.remove('pax-liveagent-engaged');
        document.body.classList.remove('pax-liveagent-engaged');

        if (instance.chatWindow) {
            instance.chatWindow.classList.remove('pax-liveagent-hard');
        }

        if (instance.modeSwitcher) {
            const assistantTab = instance.modeSwitcher.querySelector('[data-mode="assistant"]');
            if (assistantTab) {
                assistantTab.removeAttribute('disabled');
                assistantTab.removeAttribute('aria-disabled');
            }
        }

        enableComposer(instance);
    }

    function disableComposer(instance, placeholder) {
        if (instance.inputField) {
            instance.inputField.setAttribute('disabled', 'disabled');
            if (placeholder) {
                instance.inputField.placeholder = placeholder;
            }
            instance.inputField.classList.add('is-disabled');
        }
        if (instance.sendButton) {
            instance.sendButton.disabled = true;
            instance.sendButton.classList.add('is-disabled');
        }
    }

    function enableComposer(instance) {
        if (instance.inputField) {
            instance.inputField.removeAttribute('disabled');
            instance.inputField.placeholder = STRINGS.typeHere;
            instance.inputField.classList.remove('is-disabled');
        }
        if (instance.sendButton) {
            instance.sendButton.disabled = false;
            instance.sendButton.classList.remove('is-disabled');
        }
    }

    function showLiveBanner(instance, state, customText) {
        const extras = ensureExtras(instance);
        const banner = extras.controls.banner;
        if (!banner) {
            return;
        }

        extras.bannerState = state;
        const text = customText || STRINGS[state] || '';
        const stateClass = state === 'queued' ? 'queue' : state;
        banner.dataset.state = state;
        banner.className = `pax-live-banner pax-live-${stateClass}`;

        if (state === 'connecting' || state === 'queued') {
            banner.innerHTML = `
                <span class="pax-live-dots" aria-hidden="true">
                    <span class="pax-conn-dot"></span>
                    <span class="pax-conn-dot"></span>
                    <span class="pax-conn-dot"></span>
                </span>
                <span class="pax-live-text">${text}</span>
            `;
        } else {
            banner.innerHTML = `<span class="pax-live-text">${text}</span>`;
        }

        banner.classList.remove('pax-live-banner--hidden');
    }

    function hideLiveBanner(instance) {
        const extras = ensureExtras(instance);
        const banner = extras.controls.banner;
        if (!banner) {
            return;
        }
        extras.bannerState = 'hidden';
        banner.className = 'pax-live-banner pax-live-banner--hidden';
    }

    function updateLiveControlsState(instance) {
        const extras = ensureExtras(instance);
        const endButton = extras.controls.endButton;
        const restartButton = extras.controls.restartButton;
        const status = instance.sessions.liveagent.status;

        if (endButton) {
            const show = status === 'active';
            endButton.hidden = !show;
            endButton.disabled = !show;
            endButton.classList.toggle('is-loading', extras.closingSession === true);
        }

        if (restartButton) {
            restartButton.hidden = extras.showRestart !== true;
        }
    }

    async function handleEndChat(instance) {
        const extras = ensureExtras(instance);
        if (extras.closingSession || !instance.sessions.liveagent.sessionId) {
            return;
        }

        extras.closingSession = true;
        updateLiveControlsState(instance);
        disableComposer(instance, STRINGS.closed);

        try {
            const response = await fetch(instance.buildLiveUrl('live/close'), {
                method: 'POST',
                headers: instance.buildLiveHeaders({
                    'Content-Type': 'application/json'
                }),
                credentials: 'same-origin',
                cache: 'no-store',
                body: JSON.stringify({
                    session_id: instance.sessions.liveagent.sessionId
                })
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.message || STRINGS.statusError);
            }

            extras.lastClosedSessionId = instance.sessions.liveagent.sessionId;
            instance.sessions.liveagent.status = 'closed';
            showLiveBanner(instance, 'closed');
            showRatingModal(instance);
        } catch (error) {
            instance.showToast?.(error.message || STRINGS.statusError);
            enableComposer(instance);
        } finally {
            extras.closingSession = false;
            updateLiveControlsState(instance);
        }
    }

    function handleRestart(instance) {
        const extras = ensureExtras(instance);
        extras.forceNewSession = true;
        extras.showRestart = false;
        updateLiveControlsState(instance);
        hideLiveBanner(instance);
        if (typeof instance.switchMode === 'function') {
            instance.switchMode('liveagent', true);
        }
    }

    function showRatingModal(instance) {
        const extras = ensureExtras(instance);
        const overlay = extras.controls.ratingOverlay;
        if (!overlay || extras.ratingOpen) {
            return;
        }

        extras.ratingOpen = true;
        overlay.removeAttribute('hidden');
        overlay.classList.add('is-open');
        extras.lastFocusedElement = document.activeElement;

        setRatingValue(instance, extras.ratingValue || 5);

        const focusTarget = extras.controls.ratingStars?.[extras.ratingValue ? extras.ratingValue - 1 : 4];
        (focusTarget || overlay).focus({ preventScroll: true });
    }

    function hideRatingModal(instance) {
        const extras = ensureExtras(instance);
        const overlay = extras.controls.ratingOverlay;
        if (!overlay || !extras.ratingOpen) {
            return;
        }

        extras.ratingOpen = false;
        overlay.setAttribute('hidden', '');
        overlay.classList.remove('is-open');

        if (extras.lastFocusedElement && typeof extras.lastFocusedElement.focus === 'function') {
            extras.lastFocusedElement.focus({ preventScroll: true });
        }
    }

    function setRatingValue(instance, value) {
        const extras = ensureExtras(instance);
        extras.ratingValue = value;
        const stars = extras.controls.ratingStars || [];
        stars.forEach((button) => {
            const isActive = parseInt(button.dataset.value, 10) <= value;
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            button.classList.toggle('is-active', isActive);
        });
    }

    function handleStarKeydown(instance, event) {
        const extras = ensureExtras(instance);
        const stars = extras.controls.ratingStars || [];
        const currentIndex = stars.indexOf(event.currentTarget);
        if (currentIndex === -1) {
            return;
        }
        if (event.key === 'ArrowRight' || event.key === 'ArrowUp') {
            event.preventDefault();
            const next = stars[Math.min(stars.length - 1, currentIndex + 1)];
            next?.focus();
            setRatingValue(instance, parseInt(next.dataset.value, 10));
        } else if (event.key === 'ArrowLeft' || event.key === 'ArrowDown') {
            event.preventDefault();
            const prev = stars[Math.max(0, currentIndex - 1)];
            prev?.focus();
            setRatingValue(instance, parseInt(prev.dataset.value, 10));
        }
    }

    async function submitRating(instance) {
        const extras = ensureExtras(instance);
        const sessionId = extras.lastClosedSessionId || instance.sessions.liveagent.sessionId;
        if (!sessionId) {
            hideRatingModal(instance);
            resetLiveAgentState(instance, true);
            return;
        }

        const stars = extras.ratingValue || 5;
        const comment = extras.controls.ratingTextarea?.value?.trim() || '';

        try {
            const response = await fetch(instance.buildLiveUrl('live/rate'), {
                method: 'POST',
                headers: instance.buildLiveHeaders({
                    'Content-Type': 'application/json'
                }),
                credentials: 'same-origin',
                cache: 'no-store',
                body: JSON.stringify({
                    session_id: sessionId,
                    stars,
                    comment
                })
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.message || STRINGS.statusError);
            }
        } catch (error) {
            instance.showToast?.(error.message || STRINGS.statusError);
        } finally {
            hideRatingModal(instance);
            resetLiveAgentState(instance, true);
        }
    }

    function skipRating(instance) {
        hideRatingModal(instance);
        resetLiveAgentState(instance, true);
    }

    function resetLiveAgentState(instance, showRestartButton) {
        const extras = ensureExtras(instance);

        instance.stopPolling?.();
        cancelHeartbeat(instance);
        disableComposer(instance, STRINGS.closed);
        extras.lastId = null;
        extras.etag = null;
        extras.pendingMessages.clear();
        extras.queuedMessages = [];
        extras.offline = false;
        extras.offlineToastShown = false;
        extras.backoff = INITIAL_POLL;
        extras.pollInterval = INITIAL_POLL;
        extras.ticks = 0;
        extras.forceNewSession = true;
        extras.showRestart = !!showRestartButton;

        updateLiveControlsState(instance);
        showLiveBanner(instance, 'closed');

        deactivateHardLiveMode(instance);
        if (typeof instance.switchMode === 'function') {
            instance.switchMode('assistant', true);
        }

        setTimeout(() => {
            extras.showRestart = !!showRestartButton;
            updateLiveControlsState(instance);
        }, 150);
    }

    function trapFocus(instance, event) {
        const extras = ensureExtras(instance);
        if (!extras.ratingOpen) {
            return;
        }
        if (event.key !== 'Tab') {
            return;
        }

        const focusable = [];
        const modal = extras.controls.ratingModal;
        if (!modal) {
            return;
        }

        const selectors = 'button, textarea, [href], [tabindex]:not([tabindex="-1"])';
        modal.querySelectorAll(selectors).forEach((element) => {
            if (!element.hasAttribute('disabled')) {
                focusable.push(element);
            }
        });

        if (!focusable.length) {
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function handleOffline(instance, showToast) {
        const extras = ensureExtras(instance);
        if (!extras.offline) {
            extras.offline = true;
            extras.backoff = Math.min(extras.backoff * 2, MAX_BACKOFF);
            extras.pollInterval = Math.min(extras.pollInterval * 2, MAX_BACKOFF);
            if (showToast && !extras.offlineToastShown) {
                instance.showToast?.(STRINGS.offline);
                extras.offlineToastShown = true;
            }
            showLiveBanner(instance, 'connecting', STRINGS.offline);
        }
    }

    function handleReconnected(instance) {
        const extras = ensureExtras(instance);
        if (!extras.offline) {
            return;
        }
        extras.offline = false;
        extras.offlineToastShown = false;
        extras.backoff = INITIAL_POLL;
        extras.pollInterval = INITIAL_POLL;
        extras.ticks = 0;
        instance.showToast?.(STRINGS.reconnected);
        if (extras.bannerState === 'connecting') {
            showLiveBanner(instance, 'queued');
        }
        flushQueuedMessages(instance);
    }

    function handleVisibilityChange(instance) {
        if (document.visibilityState === 'visible') {
            const extras = ensureExtras(instance);
            extras.pollInterval = INITIAL_POLL;
            extras.ticks = 0;
            instance.pollLiveAgentMessages?.({ immediate: true }).catch(() => handleOffline(instance));
        }
    }

    async function flushQueuedMessages(instance) {
        const extras = ensureExtras(instance);
        if (!extras.queuedMessages.length) {
            return;
        }

        const queue = [...extras.queuedMessages];
        extras.queuedMessages = [];

        for (const item of queue) {
            try {
                await extras.originalSend.call(instance, item.message, item.replyTo, { flush: true });
            } catch (error) {
                instance.showToast?.(error.message || STRINGS.statusError);
            }
        }

        instance.pollLiveAgentMessages?.({ immediate: true });
    }

    function scheduleHeartbeat(instance) {
        const extras = ensureExtras(instance);
        cancelHeartbeat(instance);
        if (!instance.sessions.liveagent.sessionId) {
            return;
        }
        extras.heartbeatTimer = setInterval(() => {
            if (extras.stopRequested) {
                return;
            }
            instance.pollLiveAgentMessages?.({ heartbeat: true });
        }, HEARTBEAT_INTERVAL);
    }

    function cancelHeartbeat(instance) {
        const extras = ensureExtras(instance);
        if (extras.heartbeatTimer) {
            clearInterval(extras.heartbeatTimer);
            extras.heartbeatTimer = null;
        }
    }

    function scheduleNextPoll(instance, delay) {
        const extras = ensureExtras(instance);
        if (extras.stopRequested) {
            return;
        }
        const next = typeof delay === 'number' ? delay : extras.pollInterval;
        if (extras.pollTimer) {
            clearTimeout(extras.pollTimer);
        }
        extras.pollTimer = setTimeout(async () => {
            await instance.pollLiveAgentMessages?.();
        }, next);
    }

    function normaliseMessage(raw) {
        if (!raw) {
            return null;
        }
        const text = typeof raw.message === 'string' ? raw.message : (typeof raw.text === 'string' ? raw.text : '');
        return {
            id: raw.id || raw.message_id || `${Date.now()}_${Math.random().toString(16).slice(2)}`,
            sender: raw.sender || (raw.role === 'admin' ? 'agent' : raw.role === 'agent' ? 'agent' : 'user'),
            text,
            timestamp: raw.timestamp || raw.created_at || raw.sent_at || new Date().toISOString(),
            read: !!raw.read,
            attachments: raw.attachment || raw.attachments || null
        };
    }

    function processIncomingMessage(instance, message) {
        const extras = ensureExtras(instance);
        if (!message) {
            return false;
        }

        for (const [key, pending] of extras.pendingMessages) {
            if (pending.sender === 'user' && pending.pending && pending.text === message.text) {
                pending.pending = false;
                pending.failed = false;
                pending.id = message.id;
                pending.timestamp = message.timestamp;
                extras.pendingMessages.delete(key);
                updatePendingMessageDom(instance, pending);
                return false;
            }
        }

        const existing = instance.sessions.liveagent.messages.some((msg) => msg.id === message.id);
        if (existing) {
            return false;
        }

        instance.sessions.liveagent.messages.push(message);
        return true;
    }

    function createPendingMessage(instance, message, replyTo) {
        const pendingKey = `tmp_${Date.now()}`;
        const pendingMessage = {
            id: pendingKey,
            sender: 'user',
            text: message,
            timestamp: new Date().toISOString(),
            replyTo: replyTo ? replyTo.id : null,
            pending: true,
            failed: false,
            __pendingKey: pendingKey
        };
        instance.sessions.liveagent.messages.push(pendingMessage);
        return pendingMessage;
    }

    function updatePendingMessageDom(instance, message) {
        if (!instance.messageContainer) {
            return;
        }
        let selector = `[data-message-id="${message.id}"]`;
        if (message.__pendingKey) {
            selector = `[data-message-id="${message.__pendingKey}"], [data-temp-id="${message.__pendingKey}"]`;
        }
        const element = instance.messageContainer.querySelector(selector);
        if (!element) {
            return;
        }
        element.dataset.messageId = message.id;
        if (message.__pendingKey) {
            element.dataset.tempId = message.__pendingKey;
        }
        element.classList.toggle('is-pending', !!message.pending);
        element.classList.toggle('is-error', !!message.failed);
        if (message.timestamp) {
            const timeElement = element.querySelector('.pax-msg-time');
            if (timeElement) {
                const time = new Date(message.timestamp);
                if (!isNaN(time.getTime())) {
                    timeElement.textContent = time.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                }
            }
        }
    }

    function enhanceRenderedMessage(instance, message) {
        if (!instance.messageContainer || !message) {
            return;
        }
        const selector = `[data-message-id="${message.id}"]`;
        const element = instance.messageContainer.querySelector(selector);
        if (!element) {
            return;
        }
        if (message.__pendingKey) {
            element.dataset.tempId = message.__pendingKey;
        }
        element.classList.toggle('is-pending', !!message.pending);
        element.classList.toggle('is-error', !!message.failed);
    }

    function queuePendingMessage(extras, pendingMessage, message, replyTo) {
        extras.pendingMessages.set(pendingMessage.__pendingKey, pendingMessage);
        extras.queuedMessages.push({ message, replyTo });
    }

    function patchPrototype(proto) {
        if (proto.__paxLiveV595) {
            return;
        }
        proto.__paxLiveV595 = true;

        const originalSetup = proto.setup;
        proto.setup = function setupWrapper() {
            const result = originalSetup.apply(this, arguments);
            installLiveEnhancements(this);
            return result;
        };

        const originalSwitchMode = proto.switchMode;
        proto.switchMode = async function switchModeWrapper(mode, saveState = true) {
            const extras = ensureExtras(this);
            const switchingToLive = mode === 'liveagent';
            if (switchingToLive) {
                extras.forceNewSession = true;
                extras.showRestart = false;
                activateHardLiveMode(this);
                showLiveBanner(this, 'connecting');
                disableComposer(this, STRINGS.connecting);
            } else {
                deactivateHardLiveMode(this);
                hideLiveBanner(this);
            }
            const result = await originalSwitchMode.call(this, mode, saveState);
            if (switchingToLive) {
                showLiveBanner(this, 'queued');
                updateLiveControlsState(this);
            } else {
                extras.forceNewSession = false;
                extras.lastId = null;
                extras.etag = null;
                updateLiveControlsState(this);
            }
            return result;
        };

        const originalEnsureLiveAgentSession = proto.ensureLiveAgentSession;
        proto.ensureLiveAgentSession = async function ensureLiveAgentSessionWrapper(force = false) {
            const extras = ensureExtras(this);
            if (extras.creatingSession) {
                return extras.creatingSession;
            }

            const shouldForce = force || extras.forceNewSession || !this.sessions.liveagent.sessionId;
            extras.forceNewSession = false;

            if (shouldForce) {
                this.sessions.liveagent.sessionId = null;
                this.sessions.liveagent.messages = [];
                this.sessions.liveagent.status = 'pending';
                extras.lastId = null;
                extras.etag = null;
                extras.pendingMessages.clear();
                extras.queuedMessages = [];
                extras.pollInterval = INITIAL_POLL;
                extras.backoff = INITIAL_POLL;
                extras.ticks = 0;
                showLiveBanner(this, 'connecting');
                disableComposer(this, STRINGS.connecting);
                this.renderMessages?.();
            }

            extras.creatingSession = (async () => {
                try {
                    const currentUser = window.paxSupportPro?.currentUser || {};
                    const response = await fetch(this.buildLiveUrl('live/session'), {
                        method: 'POST',
                        headers: this.buildLiveHeaders({
                            'Content-Type': 'application/json'
                        }),
                        credentials: 'same-origin',
                        cache: 'no-store',
                        body: JSON.stringify({
                            user_meta: {
                                id: currentUser?.id || 0,
                                name: currentUser?.name || 'Guest',
                                email: currentUser?.email || ''
                            },
                            page_url: window.location.href,
                            user_agent: navigator.userAgent
                        })
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || !data.success) {
                        const message = data.message || STRINGS.statusError;
                        showLiveBanner(this, 'error', message);
                        throw new Error(message);
                    }

                    const summary = data.session || {};
                    this.sessions.liveagent.sessionId = summary.id || data.session_id || null;
                    this.sessions.liveagent.status = summary.status || data.status || 'pending';
                    showLiveBanner(this, 'queued');
                    updateLiveControlsState(this);
                    return this.sessions.liveagent.sessionId;
                } finally {
                    extras.creatingSession = null;
                }
            })();

            return extras.creatingSession;
        };

        const originalStartPolling = proto.startPolling;
        proto.startPolling = function startPollingWrapper() {
            const extras = ensureExtras(this);
            if (!this.sessions.liveagent.sessionId) {
                return;
            }
            if (extras.pollTimer) {
                return;
            }
            extras.stopRequested = false;
            extras.pollInterval = INITIAL_POLL;
            extras.backoff = INITIAL_POLL;
            extras.ticks = 0;
            extras.pollTimer = setTimeout(async () => {
                await this.pollLiveAgentMessages?.();
            }, 0);
            scheduleHeartbeat(this);
            originalStartPolling.call(this);
        };

        const originalStopPolling = proto.stopPolling;
        proto.stopPolling = function stopPollingWrapper() {
            const extras = ensureExtras(this);
            extras.stopRequested = true;
            if (extras.pollTimer) {
                clearTimeout(extras.pollTimer);
                extras.pollTimer = null;
            }
            cancelHeartbeat(this);
            originalStopPolling.call(this);
        };

        const originalPoll = proto.pollLiveAgentMessages;
        proto.pollLiveAgentMessages = async function pollLiveAgentMessagesWrapper(options = {}) {
            const extras = ensureExtras(this);
            if (!this.sessions.liveagent.sessionId) {
                return;
            }

            const params = new URLSearchParams({
                session_id: this.sessions.liveagent.sessionId
            });
            if (extras.lastId) {
                params.append('after', extras.lastId);
            }

            const headers = this.buildLiveHeaders();
            if (extras.etag) {
                headers['If-None-Match'] = extras.etag;
            }

            let response;
            try {
                response = await fetch(this.buildLiveUrl(`live/messages?${params.toString()}`), {
                    method: 'GET',
                    headers,
                    credentials: 'same-origin',
                    cache: 'no-store'
                });
            } catch (error) {
                handleOffline(this, true);
                scheduleNextPoll(this, extras.backoff);
                return;
            }

            if (response.status === 304) {
                extras.ticks += 1;
                if (extras.ticks >= 180) {
                    extras.pollInterval = extras.slowInterval;
                }
                handleReconnected(this);
                scheduleNextPoll(this);
                return;
            }

            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                handleOffline(this, true);
                scheduleNextPoll(this, extras.backoff);
                return;
            }

            const etag = response.headers.get('ETag') || data.etag;
            if (etag) {
                extras.etag = etag;
            }

            handleReconnected(this);

            extras.lastId = data.last_id || extras.lastId;
            extras.ticks += 1;
            if (extras.ticks >= 180) {
                extras.pollInterval = extras.slowInterval;
            } else {
                extras.pollInterval = INITIAL_POLL;
            }

            const messages = Array.isArray(data.messages) ? data.messages : [];
            let rendered = false;
            messages.forEach((raw) => {
                const normalised = normaliseMessage(raw);
                if (!normalised) {
                    return;
                }
                const shouldRender = processIncomingMessage(this, normalised);
                if (shouldRender) {
                    this.renderMessage?.(normalised);
                    enhanceRenderedMessage(this, normalised);
                    rendered = true;
                }
            });

            if (rendered) {
                this.scrollToBottom?.();
                this.saveState?.();
            }

            if (data.session) {
                const status = data.session.status || data.session.state;
                if (status) {
                    this.sessions.liveagent.status = status;
                }
                if (data.session.agent) {
                    this.sessions.liveagent.agentInfo = data.session.agent;
                }
            } else if (data.status) {
                this.sessions.liveagent.status = data.status;
            }

            if (this.sessions.liveagent.status === 'active') {
                enableComposer(this);
                showLiveBanner(this, 'connected');
            } else if (this.sessions.liveagent.status === 'pending') {
                disableComposer(this, STRINGS.queued);
                showLiveBanner(this, 'queued');
            }

            if (typeof this.removeLiveAgentOnboarding === 'function') {
                this.removeLiveAgentOnboarding();
            }

            if (typeof this.syncLiveAgentStatus === 'function') {
                this.syncLiveAgentStatus();
            }

            updateLiveControlsState(this);
            flushQueuedMessages(this);
            scheduleNextPoll(this);
            return data;
        };

        const originalRenderMessage = proto.renderMessage;
        proto.renderMessage = function renderMessageWrapper(message) {
            originalRenderMessage.call(this, message);
            enhanceRenderedMessage(this, message);
        };

        const originalSend = proto.sendLiveAgentMessage;
        ensureExtras.prototype = ensureExtras.prototype || {};
        proto.__liveOriginalSend = originalSend;

        proto.sendLiveAgentMessage = async function sendLiveAgentMessageWrapper(message, replyTo, options = {}) {
            const extras = ensureExtras(this);

            if (options.flush === true) {
                return originalSend.call(this, message, replyTo);
            }

            if (!this.sessions.liveagent.sessionId) {
                throw new Error('Live session is not available.');
            }

            const pendingMessage = createPendingMessage(this, message, replyTo);
            extras.pendingMessages.set(pendingMessage.__pendingKey, pendingMessage);
            this.renderMessage?.(pendingMessage);
            enhanceRenderedMessage(this, pendingMessage);
            this.scrollToBottom?.();

            if (extras.offline || navigator.onLine === false) {
                pendingMessage.pending = true;
                pendingMessage.failed = false;
                updatePendingMessageDom(this, pendingMessage);
                queuePendingMessage(extras, pendingMessage, message, replyTo);
                handleOffline(this, true);
                return;
            }

            try {
                await originalSend.call(this, message, replyTo);
                pendingMessage.pending = true;
                pendingMessage.failed = false;
                updatePendingMessageDom(this, pendingMessage);
                extras.etag = null;
                extras.lastId = pendingMessage.id;
                extras.pollInterval = INITIAL_POLL;
                extras.ticks = 0;
                await this.pollLiveAgentMessages?.({ immediate: true });
            } catch (error) {
                pendingMessage.pending = false;
                pendingMessage.failed = true;
                updatePendingMessageDom(this, pendingMessage);
                queuePendingMessage(extras, pendingMessage, message, replyTo);
                handleOffline(this, true);
                throw error;
            }
        };

        ensureExtras.prototype.originalSend = originalSend;
        ensureExtras.prototype.originalPoll = originalPoll;
    }

    function boot() {
        if (window.PAXUnifiedChat && window.PAXUnifiedChat.prototype) {
            patchPrototype(window.PAXUnifiedChat.prototype);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    window.addEventListener('paxUnifiedChatReady', boot);
})();
