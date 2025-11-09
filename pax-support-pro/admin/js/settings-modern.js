/**
 * Modern Settings UI JavaScript
 * PAX Support Pro
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        initColorPickers();
        initRangeSliders();
        initLivePreview();
        initResetButton();
        initToggles();
        initFormValidation();
        initMenuItemsSync();
        initPreviewHelp();
        initResetReactionsButton();
        initMenuItems();
    }

    /**
     * Initialize color pickers with live preview
     */
    function initColorPickers() {
        const colorInputs = document.querySelectorAll('.pax-color-input');
        
        colorInputs.forEach(input => {
            const preview = input.previousElementSibling;
            
            // Set initial color
            if (preview && preview.classList.contains('pax-color-preview')) {
                preview.style.background = input.value;
            }
            
            // Update on input
            input.addEventListener('input', function() {
                if (preview) {
                    preview.style.background = this.value;
                }
                updateLivePreview();
            });
            
            // Click preview to open color picker
            if (preview) {
                preview.addEventListener('click', function() {
                    input.click();
                });
            }
        });
    }

    /**
     * Initialize range sliders with value display
     */
    function initRangeSliders() {
        const rangeSliders = document.querySelectorAll('.pax-range-slider');
        
        rangeSliders.forEach(slider => {
            const valueDisplay = slider.parentElement.querySelector('.pax-range-value');
            const unit = slider.dataset.unit || '';
            
            // Set initial value
            if (valueDisplay) {
                valueDisplay.textContent = slider.value + unit;
            }
            
            // Update gradient
            updateSliderGradient(slider);
            
            // Update on input
            slider.addEventListener('input', function() {
                if (valueDisplay) {
                    valueDisplay.textContent = this.value + unit;
                }
                updateSliderGradient(this);
                updateLivePreview();
            });
        });
    }

    /**
     * Update slider gradient based on value
     */
    function updateSliderGradient(slider) {
        const min = slider.min || 0;
        const max = slider.max || 100;
        const value = slider.value;
        const percentage = ((value - min) / (max - min)) * 100;
        
        const color = getComputedStyle(document.documentElement)
            .getPropertyValue('--pax-primary').trim() || '#e53935';
        
        slider.style.background = `linear-gradient(to right, ${color} 0%, ${color} ${percentage}%, #ddd ${percentage}%, #ddd 100%)`;
    }

    /**
     * Initialize live preview updates
     */
    function initLivePreview() {
        // Update preview on any form change
        const form = document.querySelector('.pax-modern-settings form');
        if (!form) return;
        
        form.addEventListener('change', updateLivePreview);
        form.addEventListener('input', debounce(updateLivePreview, 300));
        
        // Initial preview update
        updateLivePreview();
    }

    /**
     * Update live preview panel
     */
    function updateLivePreview() {
        const preview = document.querySelector('.pax-preview-chat');
        if (!preview) return;
        
        // Get form values - Colors
        const colorAccent = document.querySelector('[name="color_accent"]')?.value || '#e53935';
        const colorBg = document.querySelector('[name="color_bg"]')?.value || '#0d0f12';
        const colorPanel = document.querySelector('[name="color_panel"]')?.value || '#121418';
        const colorBorder = document.querySelector('[name="color_border"]')?.value || '#2a2d33';
        const colorText = document.querySelector('[name="color_text"]')?.value || '#e8eaf0';
        const reactionColor = document.querySelector('[name="reaction_btn_color"]')?.value || '#e53935';
        
        // Get form values - Toggles
        const enabled = document.querySelector('[name="enabled"]')?.checked ?? true;
        const enableChat = document.querySelector('[name="enable_chat"]')?.checked ?? true;
        const enableTicket = document.querySelector('[name="enable_ticket"]')?.checked ?? true;
        
        // Get form values - Text
        const brandName = document.querySelector('[name="brand_name"]')?.value || 'PAX SUPPORT';
        
        // Get custom send icon
        const customSendIcon = document.querySelector('[name="custom_send_icon"]')?.value || '';
        
        // Apply colors to preview with smooth transition
        preview.style.setProperty('--preview-accent', colorAccent);
        preview.style.setProperty('--preview-bg', colorBg);
        preview.style.setProperty('--preview-panel', colorPanel);
        preview.style.setProperty('--preview-border', colorBorder);
        preview.style.setProperty('--preview-text', colorText);
        preview.style.setProperty('--preview-reaction', reactionColor);
        
        // Update brand name in preview
        const previewTitle = preview.querySelector('.pax-preview-title');
        if (previewTitle) {
            previewTitle.textContent = brandName;
        }
        
        // Update send button icon in preview
        const previewButton = preview.querySelector('.pax-preview-button');
        if (previewButton && customSendIcon) {
            // Add visual indicator that custom icon is set
            previewButton.style.backgroundImage = `url(${customSendIcon})`;
            previewButton.style.backgroundSize = '18px 18px';
            previewButton.style.backgroundPosition = 'center';
            previewButton.style.backgroundRepeat = 'no-repeat';
        } else if (previewButton) {
            previewButton.style.backgroundImage = 'none';
        }
        
        // Update preview visibility based on toggles
        const previewPanel = document.querySelector('.pax-preview-panel');
        if (previewPanel) {
            if (!enabled) {
                previewPanel.style.opacity = '0.4';
                previewPanel.style.pointerEvents = 'none';
                addPreviewOverlay('Plugin Disabled');
            } else if (!enableChat) {
                previewPanel.style.opacity = '0.6';
                addPreviewOverlay('Chat Disabled');
            } else {
                previewPanel.style.opacity = '1';
                previewPanel.style.pointerEvents = 'auto';
                removePreviewOverlay();
            }
        }
        
        // Update menu items preview
        updateMenuItemsPreview();
        
        // Animate preview update
        preview.style.transform = 'scale(0.98)';
        setTimeout(() => {
            preview.style.transform = 'scale(1)';
        }, 100);
    }
    
    /**
     * Add overlay to preview when disabled
     */
    function addPreviewOverlay(message) {
        const previewContent = document.querySelector('.pax-preview-content');
        if (!previewContent) return;
        
        let overlay = previewContent.querySelector('.pax-preview-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'pax-preview-overlay';
            overlay.style.cssText = `
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.7);
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: 600;
                font-size: 16px;
                border-radius: 8px;
                z-index: 10;
                backdrop-filter: blur(4px);
            `;
            previewContent.appendChild(overlay);
        }
        overlay.textContent = message;
    }
    
    /**
     * Remove overlay from preview
     */
    function removePreviewOverlay() {
        const overlay = document.querySelector('.pax-preview-overlay');
        if (overlay) {
            overlay.remove();
        }
    }
    
    /**
     * Update menu items in preview
     */
    function updateMenuItemsPreview() {
        const preview = document.querySelector('.pax-preview-chat');
        if (!preview) return;
        
        // Get or create menu items container in preview
        let menuContainer = preview.querySelector('.pax-preview-menu');
        if (!menuContainer) {
            menuContainer = document.createElement('div');
            menuContainer.className = 'pax-preview-menu';
            menuContainer.style.cssText = `
                margin-top: 12px;
                display: flex;
                flex-direction: column;
                gap: 6px;
            `;
            preview.appendChild(menuContainer);
        }
        
        // Clear existing items
        menuContainer.innerHTML = '';
        
        // Get menu items from form
        const menuItems = document.querySelectorAll('.pax-menu-item');
        let visibleCount = 0;
        
        menuItems.forEach(item => {
            const key = item.dataset.key;
            const labelInput = item.querySelector('.pax-menu-item-label');
            const visibleToggle = item.querySelector('.pax-menu-item-toggle input');
            
            if (visibleToggle && visibleToggle.checked && visibleCount < 5) {
                const label = labelInput?.value || key;
                const menuItem = document.createElement('div');
                menuItem.style.cssText = `
                    padding: 8px 12px;
                    background: var(--preview-panel, #121418);
                    border: 1px solid var(--preview-border, #2a2d33);
                    border-radius: 6px;
                    color: var(--preview-text, #e8eaf0);
                    font-size: 12px;
                    cursor: pointer;
                    transition: all 0.2s ease;
                `;
                menuItem.textContent = label;
                menuContainer.appendChild(menuItem);
                visibleCount++;
            }
        });
        
        // Show message if no items visible
        if (visibleCount === 0) {
            menuContainer.innerHTML = '<div style="padding: 8px; color: var(--preview-text, #e8eaf0); opacity: 0.5; font-size: 11px; text-align: center;">No menu items visible</div>';
        }
    }

    /**
     * Initialize reset to default button
     */
    function initResetButton() {
        const resetBtn = document.getElementById('pax-reset-defaults');
        const modal = document.getElementById('pax-reset-modal');
        const confirmBtn = document.getElementById('pax-confirm-reset');
        const cancelBtn = document.getElementById('pax-cancel-reset');
        
        if (!resetBtn || !modal) return;
        
        // Show modal
        resetBtn.addEventListener('click', function(e) {
            e.preventDefault();
            modal.classList.add('active');
        });
        
        // Cancel
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                modal.classList.remove('active');
            });
        }
        
        // Close on overlay click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });
        
        // Confirm reset
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                resetToDefaults();
                modal.classList.remove('active');
            });
        }
    }

    /**
     * Reset all settings to defaults
     */
    function resetToDefaults() {
        // Show loading state
        const confirmBtn = document.getElementById('pax-confirm-reset');
        const originalText = confirmBtn.textContent;
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Resetting...';

        // Call AJAX to reset settings in database
        const formData = new FormData();
        formData.append('action', 'pax_sup_reset_settings');
        formData.append('nonce', paxSupportAdmin.resetNonce);

        fetch(paxSupportAdmin.ajax, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccessMessage(data.data.message || 'Settings reset successfully!');
                // Reload page to show reset values
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showErrorMessage(data.data.message || 'Failed to reset settings.');
                confirmBtn.disabled = false;
                confirmBtn.textContent = originalText;
            }
        })
        .catch(error => {
            console.error('Reset error:', error);
            showErrorMessage('An error occurred while resetting settings.');
            confirmBtn.disabled = false;
            confirmBtn.textContent = originalText;
        });
    }

    /**
     * Initialize toggle switches
     */
    function initToggles() {
        const toggles = document.querySelectorAll('.pax-toggle input');
        
        toggles.forEach(toggle => {
            toggle.addEventListener('change', function() {
                // Add animation
                const slider = this.nextElementSibling;
                if (slider) {
                    slider.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        slider.style.transform = 'scale(1)';
                    }, 100);
                }
            });
        });
    }

    /**
     * Initialize form validation
     */
    function initFormValidation() {
        const form = document.querySelector('.pax-modern-settings form');
        if (!form) return;
        
        form.addEventListener('submit', function(e) {
            // Validate required fields
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = 'var(--pax-danger)';
                    setTimeout(() => {
                        field.style.borderColor = '';
                    }, 2000);
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showErrorMessage('Please fill in all required fields.');
                return false;
            }
        });
    }

    /**
     * Show success message
     */
    function showSuccessMessage(message) {
        const existing = document.querySelector('.pax-success-message');
        if (existing) {
            existing.remove();
        }
        
        const messageEl = document.createElement('div');
        messageEl.className = 'pax-success-message';
        messageEl.innerHTML = `
            <span class="dashicons dashicons-yes-alt"></span>
            <span>${message}</span>
        `;
        
        document.body.appendChild(messageEl);
        
        setTimeout(() => {
            messageEl.style.opacity = '0';
            messageEl.style.transform = 'translateX(400px)';
            setTimeout(() => {
                messageEl.remove();
            }, 300);
        }, 4000);
    }

    /**
     * Show error message
     */
    function showErrorMessage(message) {
        const existing = document.querySelector('.pax-error-message');
        if (existing) {
            existing.remove();
        }
        
        const messageEl = document.createElement('div');
        messageEl.className = 'pax-success-message';
        messageEl.style.background = 'var(--pax-danger)';
        messageEl.innerHTML = `
            <span class="dashicons dashicons-warning"></span>
            <span>${message}</span>
        `;
        
        document.body.appendChild(messageEl);
        
        setTimeout(() => {
            messageEl.style.opacity = '0';
            messageEl.style.transform = 'translateX(400px)';
            setTimeout(() => {
                messageEl.remove();
            }, 300);
        }, 4000);
    }

    /**
     * Debounce function
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Initialize icon selection
     */
    function initIconSelection() {
        const iconOptions = document.querySelectorAll('.pax-icon-option');
        
        iconOptions.forEach(option => {
            option.addEventListener('click', function() {
                const group = this.dataset.group;
                const value = this.dataset.value;
                
                // Remove active from siblings
                document.querySelectorAll(`.pax-icon-option[data-group="${group}"]`).forEach(opt => {
                    opt.classList.remove('active');
                });
                
                // Add active to this
                this.classList.add('active');
                
                // Update hidden input
                const input = document.querySelector(`[name="${group}"]`);
                if (input) {
                    input.value = value;
                }
                
                updateLivePreview();
            });
        });
    }

    // Initialize icon selection if present
    if (document.querySelector('.pax-icon-option')) {
        initIconSelection();
    }

    /**
     * Initialize menu items real-time sync
     */
    function initMenuItemsSync() {
        const menuItems = document.querySelectorAll('.pax-menu-item');
        
        menuItems.forEach(item => {
            const labelInput = item.querySelector('.pax-menu-item-label');
            const visibleToggle = item.querySelector('.pax-menu-item-toggle input');
            const key = item.dataset.key;
            
            // Real-time label sync
            if (labelInput) {
                labelInput.addEventListener('input', function() {
                    syncMenuItemToFrontend(key, this.value, visibleToggle?.checked);
                    updateLivePreview();
                    
                    // Visual feedback
                    labelInput.style.borderColor = 'var(--pax-success)';
                    setTimeout(() => {
                        labelInput.style.borderColor = '';
                    }, 500);
                });
                
                // Restore original on ESC
                labelInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        this.value = this.dataset.original;
                        this.blur();
                        updateLivePreview();
                    }
                });
            }
            
            // Real-time visibility sync
            if (visibleToggle) {
                visibleToggle.addEventListener('change', function() {
                    syncMenuItemToFrontend(key, labelInput?.value, this.checked);
                    updateLivePreview();
                    
                    // Visual feedback
                    item.style.opacity = this.checked ? '1' : '0.5';
                });
                
                // Set initial opacity
                item.style.opacity = visibleToggle.checked ? '1' : '0.5';
            }
        });
    }
    
    /**
     * Sync menu item changes to frontend (if chat widget is open)
     */
    function syncMenuItemToFrontend(key, label, visible) {
        // Store in localStorage for frontend to pick up
        try {
            const storedMenu = JSON.parse(localStorage.getItem('pax_menu_sync') || '{}');
            storedMenu[key] = {
                key: key,
                label: label,
                visible: visible,
                timestamp: Date.now()
            };
            localStorage.setItem('pax_menu_sync', JSON.stringify(storedMenu));
            
            // Dispatch custom event for real-time sync
            window.dispatchEvent(new CustomEvent('pax-menu-updated', {
                detail: { key, label, visible }
            }));
        } catch (e) {
            console.warn('Could not sync menu item:', e);
        }
    }

    /**
     * Initialize preview help tooltip
     */
    function initPreviewHelp() {
        const helpBtn = document.getElementById('pax-preview-help');
        if (!helpBtn) return;
        
        let tooltip = null;
        
        helpBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (tooltip && tooltip.classList.contains('active')) {
                closeTooltip();
                return;
            }
            
            // Create tooltip
            tooltip = document.createElement('div');
            tooltip.className = 'pax-help-tooltip';
            tooltip.innerHTML = `
                <button class="pax-help-tooltip-close" aria-label="Close">&times;</button>
                <h4>Live Preview Tips</h4>
                <ul>
                    <li><strong>Colors:</strong> Change any color picker to see instant updates</li>
                    <li><strong>Brand Name:</strong> Edit the brand name field to update the preview title</li>
                    <li><strong>Toggles:</strong> Enable/disable features to see preview changes</li>
                    <li><strong>Menu Items:</strong> Edit labels or toggle visibility to update menu preview</li>
                    <li><strong>No Save Required:</strong> All changes are preview-only until you click "Save Changes"</li>
                </ul>
            `;
            
            document.body.appendChild(tooltip);
            
            // Position tooltip
            const rect = helpBtn.getBoundingClientRect();
            tooltip.style.top = (rect.bottom + 10) + 'px';
            tooltip.style.left = (rect.left - 20) + 'px';
            
            // Show tooltip
            setTimeout(() => {
                tooltip.classList.add('active');
            }, 10);
            
            // Close button
            const closeBtn = tooltip.querySelector('.pax-help-tooltip-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', closeTooltip);
            }
            
            // Close on outside click
            setTimeout(() => {
                document.addEventListener('click', handleOutsideClick);
            }, 100);
        });
        
        function closeTooltip() {
            if (tooltip) {
                tooltip.classList.remove('active');
                setTimeout(() => {
                    if (tooltip && tooltip.parentNode) {
                        tooltip.remove();
                    }
                    tooltip = null;
                }, 200);
                document.removeEventListener('click', handleOutsideClick);
            }
        }
        
        function handleOutsideClick(e) {
            if (tooltip && !tooltip.contains(e.target) && e.target !== helpBtn) {
                closeTooltip();
            }
        }
    }
    
    // ============================================
    // Custom Send Icon Upload
    // ============================================
    
    const uploadButton = document.getElementById('upload_send_icon_button');
    const removeButton = document.getElementById('remove_send_icon_button');
    const iconInput = document.getElementById('custom_send_icon');
    const iconPreview = document.getElementById('send_icon_preview');
    
    if (uploadButton) {
        uploadButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Create WordPress media uploader
            const mediaUploader = wp.media({
                title: 'Select Send Icon',
                button: {
                    text: 'Use this icon'
                },
                multiple: false,
                library: {
                    type: ['image']
                }
            });
            
            // When an image is selected
            mediaUploader.on('select', function() {
                const attachment = mediaUploader.state().get('selection').first().toJSON();
                
                // Update hidden input
                if (iconInput) {
                    iconInput.value = attachment.url;
                }
                
                // Update preview
                if (iconPreview) {
                    iconPreview.style.display = 'flex';
                    iconPreview.innerHTML = `
                        <img src="${attachment.url}" style="width: 32px; height: 32px; object-fit: contain; background: var(--pax-accent); padding: 6px; border-radius: 6px;">
                        <button type="button" id="remove_send_icon_button" class="pax-btn pax-btn-danger" style="padding: 4px 8px; font-size: 12px;">
                            <span class="dashicons dashicons-no" style="font-size: 14px; width: 14px; height: 14px;"></span>
                            Remove
                        </button>
                    `;
                    
                    // Re-attach remove button listener
                    const newRemoveButton = document.getElementById('remove_send_icon_button');
                    if (newRemoveButton) {
                        newRemoveButton.addEventListener('click', removeSendIcon);
                    }
                }
                
                // Update live preview
                updateLivePreview();
                showSuccessMessage('Send icon updated');
            });
            
            // Open the uploader
            mediaUploader.open();
        });
    }
    
    function removeSendIcon(e) {
        if (e) e.preventDefault();
        
        if (iconInput) {
            iconInput.value = '';
        }
        
        if (iconPreview) {
            iconPreview.style.display = 'none';
            iconPreview.innerHTML = '';
        }
        
        updateLivePreview();
        showSuccessMessage('Send icon removed');
    }
    
    if (removeButton) {
        removeButton.addEventListener('click', removeSendIcon);
    }
    
    // ============================================
    // Custom Launcher Icon Upload
    // ============================================
    
    const uploadLauncherButton = document.getElementById('upload_launcher_icon_button');
    const removeLauncherButton = document.getElementById('remove_launcher_icon_button');
    const launcherIconInput = document.getElementById('custom_launcher_icon');
    const launcherIconPreview = document.getElementById('launcher_icon_preview');
    
    if (uploadLauncherButton) {
        uploadLauncherButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Create WordPress media uploader
            const mediaUploader = wp.media({
                title: 'Select Launcher Icon',
                button: {
                    text: 'Use this icon'
                },
                multiple: false,
                library: {
                    type: ['image']
                }
            });
            
            // When an image is selected
            mediaUploader.on('select', function() {
                const attachment = mediaUploader.state().get('selection').first().toJSON();
                
                // Update hidden input
                if (launcherIconInput) {
                    launcherIconInput.value = attachment.url;
                }
                
                // Update preview
                if (launcherIconPreview) {
                    launcherIconPreview.style.display = 'flex';
                    launcherIconPreview.innerHTML = `
                        <img src="${attachment.url}" style="width: 48px; height: 48px; object-fit: contain; background: var(--pax-accent); padding: 8px; border-radius: 50%;">
                        <button type="button" id="remove_launcher_icon_button" class="pax-btn pax-btn-danger" style="padding: 4px 8px; font-size: 12px;">
                            <span class="dashicons dashicons-no" style="font-size: 14px; width: 14px; height: 14px;"></span>
                            Remove
                        </button>
                    `;
                    
                    // Re-attach remove button listener
                    const newRemoveButton = document.getElementById('remove_launcher_icon_button');
                    if (newRemoveButton) {
                        newRemoveButton.addEventListener('click', removeLauncherIcon);
                    }
                }
                
                // Update live preview
                updateLivePreview();
                showSuccessMessage('Launcher icon updated');
            });
            
            // Open the uploader
            mediaUploader.open();
        });
    }
    
    function removeLauncherIcon(e) {
        if (e) e.preventDefault();
        
        if (launcherIconInput) {
            launcherIconInput.value = '';
        }
        
        if (launcherIconPreview) {
            launcherIconPreview.style.display = 'none';
            launcherIconPreview.innerHTML = '';
        }
        
        updateLivePreview();
        showSuccessMessage('Launcher icon removed');
    }
    
    if (removeLauncherButton) {
        removeLauncherButton.addEventListener('click', removeLauncherIcon);
    }
    
    // ============================================
    // Update Checker
    // ============================================
    
    const checkUpdatesBtn = document.getElementById('pax-check-updates');
    const updateStatus = document.getElementById('pax-update-status');
    const updateInfo = document.getElementById('pax-update-info');
    
    if (checkUpdatesBtn) {
        checkUpdatesBtn.addEventListener('click', async function() {
            // Disable button and show loading
            checkUpdatesBtn.disabled = true;
            checkUpdatesBtn.innerHTML = '<span class="dashicons dashicons-update spin"></span> Checking...';
            
            if (updateStatus) {
                updateStatus.style.display = 'inline';
                updateStatus.textContent = 'Checking for updates...';
                updateStatus.className = '';
            }
            
            if (updateInfo) {
                updateInfo.style.display = 'none';
                updateInfo.innerHTML = '';
            }
            
            try {
                const response = await fetch(ajaxurl.replace('admin-ajax.php', 'wp-json/pax/v1/check-updates'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': document.querySelector('[name="_wpnonce"]')?.value || ''
                    },
                    credentials: 'same-origin'
                });
                
                if (!response.ok) {
                    throw new Error('Failed to check for updates');
                }
                
                const data = await response.json();
                
                // Update status
                if (updateStatus) {
                    updateStatus.style.display = 'inline';
                    if (data.has_update) {
                        updateStatus.innerHTML = '<span style="color: #e53935;">⚠️ Update available!</span>';
                    } else {
                        updateStatus.innerHTML = '<span style="color: #4caf50;">✅ Up to date</span>';
                    }
                }
                
                // Show update info
                if (updateInfo && data.has_update) {
                    updateInfo.style.display = 'block';
                    updateInfo.innerHTML = `
                        <div style="padding: 16px; background: rgba(229, 57, 53, 0.1); border: 1px solid rgba(229, 57, 53, 0.3); border-radius: 8px; margin-top: 12px;">
                            <h4 style="margin: 0 0 8px 0; color: #e53935;">
                                <span class="dashicons dashicons-info"></span>
                                Update Available: ${data.latest_version}
                            </h4>
                            <p style="margin: 0 0 12px 0; color: #9aa0a8;">
                                ${data.message}
                            </p>
                            <a href="${window.location.origin}/wp-admin/plugins.php" class="pax-btn pax-btn-primary" style="display: inline-block;">
                                <span class="dashicons dashicons-download"></span>
                                Go to Plugins Page to Update
                            </a>
                        </div>
                    `;
                } else if (updateInfo && !data.has_update) {
                    updateInfo.style.display = 'block';
                    updateInfo.innerHTML = `
                        <div style="padding: 16px; background: rgba(76, 175, 80, 0.1); border: 1px solid rgba(76, 175, 80, 0.3); border-radius: 8px; margin-top: 12px;">
                            <p style="margin: 0; color: #4caf50;">
                                <span class="dashicons dashicons-yes-alt"></span>
                                ${data.message}
                            </p>
                        </div>
                    `;
                }
                
                showSuccessMessage('Update check completed');
                
            } catch (error) {
                console.error('Update check error:', error);
                
                if (updateStatus) {
                    updateStatus.style.display = 'inline';
                    updateStatus.innerHTML = '<span style="color: #e53935;">❌ Check failed</span>';
                }
                
                if (updateInfo) {
                    updateInfo.style.display = 'block';
                    updateInfo.innerHTML = `
                        <div style="padding: 16px; background: rgba(229, 57, 53, 0.1); border: 1px solid rgba(229, 57, 53, 0.3); border-radius: 8px; margin-top: 12px;">
                            <p style="margin: 0; color: #e53935;">
                                <span class="dashicons dashicons-warning"></span>
                                Failed to check for updates. Please try again later.
                            </p>
                        </div>
                    `;
                }
                
                showErrorMessage('Failed to check for updates');
            } finally {
                // Re-enable button
                checkUpdatesBtn.disabled = false;
                checkUpdatesBtn.innerHTML = '<span class="dashicons dashicons-update"></span> Check for Updates';
            }
        });
    }
    
    // Add spin animation for loading spinner
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .spin {
            animation: spin 1s linear infinite;
            display: inline-block;
        }
    `;
    document.head.appendChild(style);
    
    /**
     * Initialize Unified Menu Items
     */
    function initMenuItems() {
        const addBtn = document.getElementById('pax-add-menu-item');
        const menusList = document.getElementById('pax-menu-items-list');
        
        if (!addBtn || !menusList) return;
        
        let menuIndex = Date.now();
        
        // Add new custom menu item
        addBtn.addEventListener('click', function() {
            const key = 'custom_' + menuIndex;
            const newItem = document.createElement('div');
            newItem.className = 'pax-menu-item';
            newItem.setAttribute('data-key', key);
            newItem.setAttribute('draggable', 'true');
            newItem.innerHTML = `
                <div class="pax-menu-item-drag">
                    <span class="dashicons dashicons-menu"></span>
                </div>
                <div class="pax-menu-item-icon">
                    <span class="dashicons dashicons-external"></span>
                </div>
                <div class="pax-menu-item-content">
                    <input type="text" 
                           name="menu_items[${key}][label]" 
                           value="" 
                           class="pax-menu-item-label"
                           placeholder="Label">
                    <input type="url" 
                           name="menu_items[${key}][url]" 
                           value="" 
                           class="pax-menu-item-url"
                           placeholder="https://example.com">
                </div>
                <label class="pax-toggle pax-menu-item-toggle">
                    <input type="checkbox" 
                           name="menu_items[${key}][visible]" 
                           value="1"
                           checked>
                    <span class="pax-toggle-slider"></span>
                </label>
                <button type="button" class="pax-btn-icon pax-remove-menu-item">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            `;
            
            menusList.appendChild(newItem);
            menuIndex++;
            
            attachRemoveHandler(newItem.querySelector('.pax-remove-menu-item'));
            attachDragHandlers(newItem);
        });
        
        // Remove menu item
        function attachRemoveHandler(btn) {
            if (!btn) return;
            btn.addEventListener('click', function() {
                const item = this.closest('.pax-menu-item');
                if (item) {
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(-20px)';
                    setTimeout(() => item.remove(), 200);
                }
            });
        }
        
        menusList.querySelectorAll('.pax-remove-menu-item').forEach(attachRemoveHandler);
        
        // Drag and drop
        let draggedItem = null;
        
        menusList.addEventListener('dragstart', function(e) {
            if (e.target.closest('.pax-custom-menu-drag')) {
                draggedItem = e.target.closest('.pax-custom-menu-item');
                draggedItem.style.opacity = '0.5';
            }
        });
        
        menusList.addEventListener('dragend', function(e) {
            if (draggedItem) {
                draggedItem.style.opacity = '1';
                draggedItem = null;
            }
        });
        
        menusList.addEventListener('dragover', function(e) {
            e.preventDefault();
            const afterElement = getDragAfterElement(menusList, e.clientY);
            if (afterElement == null) {
                menusList.appendChild(draggedItem);
            } else {
                menusList.insertBefore(draggedItem, afterElement);
            }
        });
        
        function getDragAfterElement(container, y) {
            const draggableElements = [...container.querySelectorAll('.pax-custom-menu-item:not(.dragging)')];
            
            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }
        
        // Make items draggable
        menusList.querySelectorAll('.pax-custom-menu-item').forEach(item => {
            item.setAttribute('draggable', 'true');
        });
    }
    
    /**
     * Initialize Reset Reactions Button
     */
    function initResetReactionsButton() {
        const resetBtn = document.getElementById('pax-reset-reactions');
        if (!resetBtn) return;
        
        resetBtn.addEventListener('click', function() {
            if (!confirm('Are you sure you want to reset all chat reactions? This will delete all stored reaction data.')) {
                return;
            }
            
            resetBtn.disabled = true;
            resetBtn.textContent = 'Resetting...';
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'pax_reset_reactions',
                    nonce: paxSupportAdmin?.resetNonce || ''
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessMessage('All reactions have been reset successfully.');
                } else {
                    showErrorMessage(data.data?.message || 'Failed to reset reactions.');
                }
            })
            .catch(error => {
                console.error('Reset reactions error:', error);
                showErrorMessage('Failed to reset reactions. Please try again.');
            })
            .finally(() => {
                resetBtn.disabled = false;
                resetBtn.textContent = 'Reset Reactions';
            });
        });
    }
    
    // Export for external use
    window.paxSettings = {
        updatePreview: updateLivePreview,
        showSuccess: showSuccessMessage,
        showError: showErrorMessage,
        syncMenuItem: syncMenuItemToFrontend
    };
})();
