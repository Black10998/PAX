(function() {
    'use strict';

    function setStatus(element, state, message) {
        if (!element) {
            return;
        }

        element.textContent = message || '';
        element.classList.remove('testing', 'success', 'error');
        if (state) {
            element.classList.add(state);
        }
    }

    function copyToClipboard(value) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(value).catch(function() {
                fallbackCopy(value);
            });
        } else {
            fallbackCopy(value);
        }
    }

    function fallbackCopy(value) {
        var textarea = document.createElement('textarea');
        textarea.value = value;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
        } catch (err) {
            console.warn('Clipboard copy failed', err);
        }
        document.body.removeChild(textarea);
    }

    function initDiagnostics() {
        var button = document.getElementById('pax-liveagent-test-connection');
        var statusLabel = document.getElementById('pax-liveagent-test-status');
        var config = window.paxLiveAgentSettings || {};

        if (button) {
            button.addEventListener('click', function() {
                if (!config.testEndpoint) {
                    setStatus(statusLabel, 'error', 'REST endpoint not available');
                    return;
                }

                setStatus(statusLabel, 'testing', config.strings ? config.strings.testing : 'Testing connection…');
                button.disabled = true;

                var url = config.testEndpoint + (config.testEndpoint.includes('?') ? '&' : '?') + 'healthcheck=1&_=' + Date.now();

                fetch(url, {
                    headers: {
                        'X-WP-Nonce': config.nonce || ''
                    }
                })
                .then(function(response) {
                    return response.json().then(function(data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function(result) {
                    if (result.ok && result.data && (result.data.status === 'ok' || result.data.success)) {
                        setStatus(statusLabel, 'success', config.strings ? config.strings.success : 'Connection successful!');
                    } else {
                        throw new Error('Invalid response');
                    }
                })
                .catch(function() {
                    setStatus(statusLabel, 'error', config.strings ? config.strings.failure : 'Connection failed.');
                })
                .finally(function() {
                    button.disabled = false;
                });
            });
        }

        document.querySelectorAll('.pax-copy-button').forEach(function(copyBtn) {
            copyBtn.addEventListener('click', function() {
                var value = copyBtn.getAttribute('data-copy-value');
                if (!value) {
                    return;
                }
                copyToClipboard(value);
                var label = copyBtn.querySelector('.pax-copy-label');
                if (label) {
                    var original = label.textContent;
                    label.textContent = (config.strings && config.strings.copied) ? config.strings.copied : 'Copied!';
                    setTimeout(function() {
                        label.textContent = original;
                    }, 1600);
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDiagnostics);
    } else {
        initDiagnostics();
    }
})();
