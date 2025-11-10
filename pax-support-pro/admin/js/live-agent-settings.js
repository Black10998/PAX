/**
 * Live Agent Settings interactions
 * - Connection diagnostics
 * - Lightweight progressive enhancement
 */

(() => {
    const root = document.querySelector('.pax-liveagent-settings');
    if (!root || typeof window.paxLiveAgentSettings === 'undefined') {
        return;
    }

    const { restUrl, nonce, strings } = window.paxLiveAgentSettings;
    const testButton = root.querySelector('[data-action="test-connection"]');
    const statusChip = root.querySelector('[data-connection-status]');

    if (!testButton || !statusChip) {
        return;
    }

    let currentController = null;

    const setStatus = (state, label) => {
        statusChip.dataset.connectionStatus = state;
        statusChip.textContent = label;
    };

    const abortCurrent = () => {
        if (currentController) {
            currentController.abort();
            currentController = null;
        }
    };

    const runDiagnostics = async () => {
        abortCurrent();
        currentController = new AbortController();

        testButton.disabled = true;
        setStatus('loading', strings.checking);

        try {
            const response = await fetch(`${restUrl}/live/status?ping=1`, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': nonce,
                    'Accept': 'application/json'
                },
                signal: currentController.signal,
            });

            if (!response.ok) {
                throw new Error(response.statusText);
            }

            const payload = await response.json();
            const message = payload && payload.message ? payload.message : strings.success;

            setStatus('success', message);
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
            const reason = error && error.message ? error.message : '';
            const template = strings.error || 'Connection failed';
            const label = template.includes('%s')
                ? template.replace('%s', reason || 'unknown')
                : [template, reason].filter(Boolean).join(' ').trim();
            setStatus('error', label);
        } finally {
            testButton.disabled = false;
            abortCurrent();
        }
    };

    testButton.addEventListener('click', runDiagnostics);
})();
