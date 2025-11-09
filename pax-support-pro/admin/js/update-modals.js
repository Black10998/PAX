/**
 * Update Notification Modals
 * PAX Support Pro
 */

(function() {
    'use strict';

    let autoDismissTimer = null;

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        // Check for update notification in URL or transient
        checkForUpdateNotification();
        
        // Listen for custom events
        document.addEventListener('pax-update-success', handleUpdateSuccess);
        document.addEventListener('pax-update-failure', handleUpdateFailure);
    }

    /**
     * Check for update notification
     */
    function checkForUpdateNotification() {
        // Check URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const updateStatus = urlParams.get('pax_update_status');
        const updateVersion = urlParams.get('pax_update_version');
        
        if (updateStatus === 'success' && updateVersion) {
            showSuccessModal(updateVersion);
            // Clean URL
            cleanUrl();
        } else if (updateStatus === 'failed') {
            const errorMsg = urlParams.get('pax_update_error') || 'Unknown error occurred';
            showFailureModal(errorMsg);
            // Clean URL
            cleanUrl();
        }
    }

    /**
     * Clean URL parameters
     */
    function cleanUrl() {
        const url = new URL(window.location);
        url.searchParams.delete('pax_update_status');
        url.searchParams.delete('pax_update_version');
        url.searchParams.delete('pax_update_error');
        window.history.replaceState({}, '', url);
    }

    /**
     * Handle update success event
     */
    function handleUpdateSuccess(event) {
        const version = event.detail?.version || 'Unknown';
        const changelog = event.detail?.changelog || [];
        showSuccessModal(version, changelog);
    }

    /**
     * Handle update failure event
     */
    function handleUpdateFailure(event) {
        const error = event.detail?.error || 'Unknown error occurred';
        showFailureModal(error);
    }

    /**
     * Show success modal
     */
    function showSuccessModal(version, changelog = []) {
        const modal = createSuccessModal(version, changelog);
        document.body.appendChild(modal);
        
        // Trigger animation
        setTimeout(() => {
            modal.classList.add('active');
        }, 10);
        
        // Start auto-dismiss
        startAutoDismiss(modal);
    }

    /**
     * Show failure modal
     */
    function showFailureModal(error) {
        const modal = createFailureModal(error);
        document.body.appendChild(modal);
        
        // Trigger animation
        setTimeout(() => {
            modal.classList.add('active');
        }, 10);
    }

    /**
     * Create success modal
     */
    function createSuccessModal(version, changelog) {
        const overlay = document.createElement('div');
        overlay.className = 'pax-update-modal-overlay';
        
        const changelogHtml = changelog.length > 0 ? `
            <div class="pax-changelog">
                <div class="pax-changelog-title">
                    <span class="dashicons dashicons-list-view"></span>
                    What's New
                </div>
                <ul class="pax-changelog-list">
                    ${changelog.map(item => `<li class="pax-changelog-item">${escapeHtml(item)}</li>`).join('')}
                </ul>
            </div>
        ` : '';
        
        overlay.innerHTML = `
            <div class="pax-update-modal">
                <div class="pax-update-modal-header success">
                    <button class="pax-modal-close" aria-label="Close"></button>
                    <div class="pax-update-icon">
                        <svg class="pax-checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                            <circle class="pax-checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
                            <path class="pax-checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                        </svg>
                    </div>
                    <h2 class="pax-update-modal-title">PAX Support Pro Updated Successfully!</h2>
                    <p class="pax-update-modal-subtitle">Your plugin is now up to date</p>
                </div>
                <div class="pax-update-modal-body">
                    <div class="pax-version-info">
                        <div class="pax-version-label">Current Version</div>
                        <div class="pax-version-number">v${escapeHtml(version)}</div>
                    </div>
                    <p class="pax-update-message">
                        Your plugin is now running the latest version with all the newest features and security improvements.
                    </p>
                    ${changelogHtml}
                    <div class="pax-auto-dismiss">
                        <div class="pax-auto-dismiss-bar"></div>
                    </div>
                </div>
                <div class="pax-update-modal-footer">
                    <button class="pax-modal-btn pax-modal-btn-primary" data-action="close">
                        <span class="dashicons dashicons-yes"></span>
                        Got it!
                    </button>
                </div>
            </div>
        `;
        
        // Add event listeners
        addModalEventListeners(overlay);
        
        return overlay;
    }

    /**
     * Create failure modal
     */
    function createFailureModal(error) {
        const overlay = document.createElement('div');
        overlay.className = 'pax-update-modal-overlay';
        
        overlay.innerHTML = `
            <div class="pax-update-modal">
                <div class="pax-update-modal-header error">
                    <button class="pax-modal-close" aria-label="Close"></button>
                    <div class="pax-update-icon">
                        <div class="pax-error-icon"></div>
                    </div>
                    <h2 class="pax-update-modal-title">Update Failed</h2>
                    <p class="pax-update-modal-subtitle">There was a problem installing the update</p>
                </div>
                <div class="pax-update-modal-body">
                    <p class="pax-update-message">
                        <strong>Error:</strong> ${escapeHtml(error)}
                    </p>
                    <p class="pax-update-message">
                        Please check your internet connection, GitHub credentials, or try again later. 
                        If the problem persists, contact support for assistance.
                    </p>
                </div>
                <div class="pax-update-modal-footer">
                    <button class="pax-modal-btn pax-modal-btn-danger" data-action="retry">
                        <span class="dashicons dashicons-update"></span>
                        Retry Update
                    </button>
                    <a href="https://github.com/AhmadAlkhalaf/pax-support-pro/issues" 
                       target="_blank" 
                       class="pax-modal-btn pax-modal-btn-secondary">
                        <span class="dashicons dashicons-sos"></span>
                        Get Support
                    </a>
                    <button class="pax-modal-btn pax-modal-btn-secondary" data-action="close">
                        Close
                    </button>
                </div>
            </div>
        `;
        
        // Add event listeners
        addModalEventListeners(overlay);
        
        return overlay;
    }

    /**
     * Add modal event listeners
     */
    function addModalEventListeners(overlay) {
        // Close button
        const closeBtn = overlay.querySelector('.pax-modal-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => closeModal(overlay));
        }
        
        // Action buttons
        const actionButtons = overlay.querySelectorAll('[data-action]');
        actionButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const action = e.currentTarget.dataset.action;
                
                if (action === 'close') {
                    closeModal(overlay);
                } else if (action === 'retry') {
                    closeModal(overlay);
                    retryUpdate();
                }
            });
        });
        
        // Click outside to close
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                closeModal(overlay);
            }
        });
        
        // ESC key to close
        const escHandler = (e) => {
            if (e.key === 'Escape') {
                closeModal(overlay);
                document.removeEventListener('keydown', escHandler);
            }
        };
        document.addEventListener('keydown', escHandler);
    }

    /**
     * Start auto-dismiss timer
     */
    function startAutoDismiss(overlay) {
        const progressBar = overlay.querySelector('.pax-auto-dismiss-bar');
        
        if (progressBar) {
            // Start progress animation
            setTimeout(() => {
                progressBar.classList.add('active');
            }, 100);
            
            // Auto-dismiss after 5 seconds
            autoDismissTimer = setTimeout(() => {
                closeModal(overlay);
            }, 5000);
        }
    }

    /**
     * Close modal
     */
    function closeModal(overlay) {
        // Clear auto-dismiss timer
        if (autoDismissTimer) {
            clearTimeout(autoDismissTimer);
            autoDismissTimer = null;
        }
        
        // Fade out
        overlay.classList.remove('active');
        
        // Remove from DOM
        setTimeout(() => {
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
        }, 300);
    }

    /**
     * Retry update
     */
    function retryUpdate() {
        // Redirect to plugins page with update action
        const pluginSlug = 'pax-support-pro/pax-support-pro.php';
        const updateUrl = `${window.location.origin}/wp-admin/update.php?action=upgrade-plugin&plugin=${encodeURIComponent(pluginSlug)}`;
        window.location.href = updateUrl;
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Expose global function for PHP integration
    window.paxShowUpdateSuccess = function(version, changelog) {
        showSuccessModal(version, changelog);
    };

    window.paxShowUpdateFailure = function(error) {
        showFailureModal(error);
    };

})();
