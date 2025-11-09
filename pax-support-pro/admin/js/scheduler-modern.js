/**
 * Scheduler Modern UI JavaScript
 * Phase 3: Full AJAX Interactivity
 * Version: 3.0.0
 */

(function() {
    'use strict';

    // State management
    let callbacks = [];
    let draggedElement = null;
    let editingCard = null;
    let autoRefreshInterval = null;

    // AJAX configuration
    const ajax = {
        url: window.paxScheduler?.ajaxUrl || '',
        nonce: window.paxScheduler?.nonce || '',
        strings: window.paxScheduler?.strings || {},
    };

    /**
     * AJAX request with retry logic
     */
    function ajaxRequest(params, retries) {
        retries = retries || 0;
        const maxRetries = 2;

        return fetch(ajax.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(params),
        })
        .then(function(response) {
            if (!response.ok && retries < maxRetries) {
                // Retry on network error
                return new Promise(function(resolve) {
                    setTimeout(function() {
                        resolve(ajaxRequest(params, retries + 1));
                    }, 1000 * (retries + 1)); // Exponential backoff
                });
            }
            return response.json();
        })
        .catch(function(error) {
            if (retries < maxRetries) {
                // Retry on error
                return new Promise(function(resolve) {
                    setTimeout(function() {
                        resolve(ajaxRequest(params, retries + 1));
                    }, 1000 * (retries + 1));
                });
            }
            throw error;
        });
    }

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        initHelpTooltip();
        initSearchFilter();
        initInlineEditing();
        initDragAndDrop();
        initKeyboardShortcuts();
        initAnimations();
        initToastNotifications();
        initFormValidation();
        initAjaxOperations();
        initAutoRefresh();
    });

    /**
     * Initialize help tooltip functionality
     */
    function initHelpTooltip() {
        const helpBtn = document.getElementById('scheduler-help');
        const helpOverlay = document.getElementById('scheduler-help-overlay');
        const helpTooltip = document.getElementById('scheduler-help-tooltip');
        const helpClose = document.getElementById('scheduler-help-close');

        if (!helpBtn || !helpOverlay || !helpTooltip || !helpClose) {
            return;
        }

        // Open help tooltip
        helpBtn.addEventListener('click', function(e) {
            e.preventDefault();
            helpOverlay.classList.add('active');
            helpTooltip.classList.add('active');
        });

        // Close help tooltip
        function closeHelp() {
            helpOverlay.classList.remove('active');
            helpTooltip.classList.remove('active');
        }

        helpClose.addEventListener('click', closeHelp);
        helpOverlay.addEventListener('click', closeHelp);

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && helpTooltip.classList.contains('active')) {
                closeHelp();
            }
        });
    }

    /**
     * Initialize search and filter functionality
     */
    function initSearchFilter() {
        const searchInput = document.getElementById('scheduler-search');
        const filterSelect = document.getElementById('scheduler-filter-status');
        const callbackCards = document.querySelectorAll('.scheduler-callback-card');

        if (!searchInput || !filterSelect || callbackCards.length === 0) {
            return;
        }

        // Search functionality
        searchInput.addEventListener('input', function() {
            filterCallbacks();
        });

        // Filter functionality
        filterSelect.addEventListener('change', function() {
            filterCallbacks();
        });

        function filterCallbacks() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusFilter = filterSelect.value.toLowerCase();

            callbackCards.forEach(function(card) {
                // Remove previous highlights
                removeHighlights(card);

                const cardText = card.textContent.toLowerCase();
                const cardStatus = card.getAttribute('data-status');

                const matchesSearch = searchTerm === '' || cardText.includes(searchTerm);
                const matchesStatus = statusFilter === '' || cardStatus === statusFilter;

                if (matchesSearch && matchesStatus) {
                    card.style.display = '';
                    
                    // Add highlighting if search term exists
                    if (searchTerm && searchTerm.length > 2) {
                        highlightText(card, searchTerm);
                    }
                } else {
                    card.style.display = 'none';
                }
            });

            // Show empty state if no results
            const visibleCards = Array.from(callbackCards).filter(function(card) {
                return card.style.display !== 'none';
            });

            const callbacksList = document.getElementById('callbacks-list');
            let emptyState = callbacksList.querySelector('.scheduler-empty-state');

            if (visibleCards.length === 0 && !emptyState) {
                emptyState = document.createElement('div');
                emptyState.className = 'scheduler-empty-state';
                emptyState.innerHTML = '<span class="dashicons dashicons-search"></span>' +
                    '<h3>No Results Found</h3>' +
                    '<p>Try adjusting your search or filter criteria.</p>';
                callbacksList.appendChild(emptyState);
            } else if (visibleCards.length > 0 && emptyState) {
                emptyState.remove();
            }
        }

        /**
         * Highlight search term in text
         */
        function highlightText(card, searchTerm) {
            const textElements = card.querySelectorAll('.callback-contact, .callback-note, .callback-datetime');
            
            textElements.forEach(function(element) {
                const text = element.textContent;
                const lowerText = text.toLowerCase();
                const index = lowerText.indexOf(searchTerm);
                
                if (index !== -1) {
                    const before = text.substring(0, index);
                    const match = text.substring(index, index + searchTerm.length);
                    const after = text.substring(index + searchTerm.length);
                    
                    element.innerHTML = before + 
                        '<mark class="scheduler-highlight">' + match + '</mark>' + 
                        after;
                }
            });
        }

        /**
         * Remove highlights from card
         */
        function removeHighlights(card) {
            const highlights = card.querySelectorAll('.scheduler-highlight');
            highlights.forEach(function(mark) {
                const parent = mark.parentNode;
                parent.replaceChild(document.createTextNode(mark.textContent), mark);
                parent.normalize();
            });
        }
    }

    /**
     * Initialize inline editing functionality
     */
    function initInlineEditing() {
        const callbackCards = document.querySelectorAll('.scheduler-callback-card');

        callbackCards.forEach(function(card) {
            // Double-click to edit note
            const noteElement = card.querySelector('.callback-note');
            if (noteElement) {
                noteElement.addEventListener('dblclick', function(e) {
                    e.stopPropagation();
                    enableInlineEdit(noteElement, card);
                });
            }

            // Click edit button (if exists)
            const editBtn = card.querySelector('.btn-edit');
            if (editBtn) {
                editBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openEditModal(card);
                });
            }
        });
    }

    /**
     * Enable inline editing for an element
     */
    function enableInlineEdit(element, card) {
        if (editingCard) return; // Only one edit at a time

        editingCard = card;
        const originalText = element.textContent.trim();
        const originalHTML = element.innerHTML;
        const scheduleId = card.getAttribute('data-id');

        // Create input
        const input = document.createElement('textarea');
        input.className = 'scheduler-inline-edit';
        input.value = originalText;
        input.rows = 3;

        // Replace element with input
        element.innerHTML = '';
        element.appendChild(input);
        input.focus();
        input.select();

        // Save on blur or Enter
        function saveEdit() {
            const newText = input.value.trim();
            if (newText !== originalText) {
                // Save via AJAX
                saveInlineEdit(scheduleId, newText, element, originalHTML);
            } else {
                element.innerHTML = originalHTML;
            }
            editingCard = null;
        }

        // Cancel on Escape
        function cancelEdit() {
            element.innerHTML = originalHTML;
            editingCard = null;
        }

        input.addEventListener('blur', saveEdit);
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.ctrlKey) {
                e.preventDefault();
                saveEdit();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                cancelEdit();
            }
        });
    }

    /**
     * Open edit modal for callback
     */
    function openEditModal(card) {
        const callbackId = card.getAttribute('data-id');
        showToast('Edit modal coming in Phase 3 (AJAX)', 'info');
        // Phase 3 will implement full modal editing
    }

    /**
     * Initialize drag and drop functionality
     */
    function initDragAndDrop() {
        const callbackCards = document.querySelectorAll('.scheduler-callback-card');
        const callbacksList = document.getElementById('callbacks-list');

        if (!callbacksList) return;

        callbackCards.forEach(function(card) {
            card.setAttribute('draggable', 'true');

            card.addEventListener('dragstart', function(e) {
                draggedElement = card;
                card.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', card.innerHTML);
            });

            card.addEventListener('dragend', function(e) {
                card.classList.remove('dragging');
                draggedElement = null;
            });

            card.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';

                if (draggedElement && draggedElement !== card) {
                    const rect = card.getBoundingClientRect();
                    const midpoint = rect.top + rect.height / 2;

                    if (e.clientY < midpoint) {
                        card.parentNode.insertBefore(draggedElement, card);
                    } else {
                        card.parentNode.insertBefore(draggedElement, card.nextSibling);
                    }
                }
            });

            card.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Save order via AJAX
                saveDragOrder();
            });
        });
    }

    /**
     * Initialize keyboard shortcuts
     */
    function initKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + K: Focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.getElementById('scheduler-search');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }

            // Ctrl/Cmd + R: Refresh
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                location.reload();
            }

            // ?: Show help
            if (e.key === '?' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                const activeElement = document.activeElement;
                if (activeElement.tagName !== 'INPUT' && activeElement.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                    const helpBtn = document.getElementById('scheduler-help');
                    if (helpBtn) helpBtn.click();
                }
            }
        });
    }

    /**
     * Initialize animations
     */
    function initAnimations() {
        // Fade in cards on load
        const cards = document.querySelectorAll('.scheduler-callback-card');
        cards.forEach(function(card, index) {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            
            setTimeout(function() {
                card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 50);
        });

        // Hover effects
        cards.forEach(function(card) {
            card.addEventListener('mouseenter', function() {
                card.style.transform = 'translateY(-4px)';
            });

            card.addEventListener('mouseleave', function() {
                card.style.transform = 'translateY(0)';
            });
        });

        // Analytics cards animation
        const metricCards = document.querySelectorAll('.scheduler-metric-card');
        metricCards.forEach(function(card, index) {
            card.style.opacity = '0';
            card.style.transform = 'scale(0.9)';
            
            setTimeout(function() {
                card.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                card.style.opacity = '1';
                card.style.transform = 'scale(1)';
            }, index * 100);
        });
    }

    /**
     * Toast notification system
     */
    function showToast(message, type) {
        type = type || 'info';
        
        const toast = document.createElement('div');
        toast.className = 'scheduler-toast scheduler-toast-' + type;
        
        const icon = document.createElement('span');
        icon.className = 'dashicons';
        
        switch(type) {
            case 'success':
                icon.classList.add('dashicons-yes-alt');
                break;
            case 'error':
                icon.classList.add('dashicons-dismiss');
                break;
            case 'warning':
                icon.classList.add('dashicons-warning');
                break;
            default:
                icon.classList.add('dashicons-info');
        }
        
        const text = document.createElement('span');
        text.textContent = message;
        
        toast.appendChild(icon);
        toast.appendChild(text);
        
        document.body.appendChild(toast);
        
        // Trigger animation
        setTimeout(function() {
            toast.classList.add('show');
        }, 10);
        
        // Remove after 3 seconds
        setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                toast.remove();
            }, 300);
        }, 3000);
    }

    /**
     * Initialize toast notifications
     */
    function initToastNotifications() {
        // Add toast container styles if not exists
        if (!document.getElementById('scheduler-toast-styles')) {
            const style = document.createElement('style');
            style.id = 'scheduler-toast-styles';
            style.textContent = `
                .scheduler-toast {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 12px 20px;
                    border-radius: 8px;
                    background: white;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    z-index: 10001;
                    opacity: 0;
                    transform: translateX(400px);
                    transition: opacity 0.3s ease, transform 0.3s ease;
                    font-size: 14px;
                    font-weight: 500;
                }
                .scheduler-toast.show {
                    opacity: 1;
                    transform: translateX(0);
                }
                .scheduler-toast-success {
                    border-left: 4px solid #10b981;
                    color: #065f46;
                }
                .scheduler-toast-error {
                    border-left: 4px solid #ef4444;
                    color: #991b1b;
                }
                .scheduler-toast-warning {
                    border-left: 4px solid #f59e0b;
                    color: #92400e;
                }
                .scheduler-toast-info {
                    border-left: 4px solid #2563eb;
                    color: #1e40af;
                }
                .scheduler-toast .dashicons {
                    font-size: 20px;
                    width: 20px;
                    height: 20px;
                }
                .scheduler-inline-edit {
                    width: 100%;
                    padding: 8px;
                    border: 2px solid #2563eb;
                    border-radius: 6px;
                    font-family: inherit;
                    font-size: 13px;
                    resize: vertical;
                }
                .scheduler-callback-card.dragging {
                    opacity: 0.5;
                    cursor: move;
                }
                @media (max-width: 768px) {
                    .scheduler-toast {
                        right: 10px;
                        left: 10px;
                        max-width: calc(100% - 20px);
                    }
                }
            `;
            document.head.appendChild(style);
        }
    }

    /**
     * Show loading state
     */
    function showLoading(element) {
        if (!element) return;
        
        element.classList.add('scheduler-loading-state');
        element.setAttribute('data-original-content', element.innerHTML);
        
        const spinner = document.createElement('div');
        spinner.className = 'scheduler-spinner';
        element.innerHTML = '';
        element.appendChild(spinner);
        element.disabled = true;
    }

    /**
     * Hide loading state
     */
    function hideLoading(element) {
        if (!element) return;
        
        element.classList.remove('scheduler-loading-state');
        const originalContent = element.getAttribute('data-original-content');
        if (originalContent) {
            element.innerHTML = originalContent;
            element.removeAttribute('data-original-content');
        }
        element.disabled = false;
    }

    /**
     * Add confirmation dialog (enhanced)
     */
    function confirmAction(message, callback, options) {
        options = options || {};
        const title = options.title || 'Confirm Action';
        const confirmText = options.confirmText || 'Confirm';
        const cancelText = options.cancelText || 'Cancel';
        const type = options.type || 'warning';
        
        // Create modal
        const modal = document.createElement('div');
        modal.className = 'scheduler-confirm-modal';
        modal.innerHTML = `
            <div class="scheduler-confirm-overlay"></div>
            <div class="scheduler-confirm-dialog scheduler-confirm-${type}">
                <div class="scheduler-confirm-header">
                    <h3>${title}</h3>
                </div>
                <div class="scheduler-confirm-body">
                    <p>${message}</p>
                </div>
                <div class="scheduler-confirm-footer">
                    <button class="scheduler-btn-cancel">${cancelText}</button>
                    <button class="scheduler-btn-confirm">${confirmText}</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Show modal
        setTimeout(function() {
            modal.classList.add('show');
        }, 10);
        
        // Handle confirm
        const confirmBtn = modal.querySelector('.scheduler-btn-confirm');
        confirmBtn.addEventListener('click', function() {
            closeModal();
            callback();
        });
        
        // Handle cancel
        const cancelBtn = modal.querySelector('.scheduler-btn-cancel');
        const overlay = modal.querySelector('.scheduler-confirm-overlay');
        
        cancelBtn.addEventListener('click', closeModal);
        overlay.addEventListener('click', closeModal);
        
        // Close on Escape
        function handleEscape(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        }
        document.addEventListener('keydown', handleEscape);
        
        function closeModal() {
            modal.classList.remove('show');
            setTimeout(function() {
                modal.remove();
                document.removeEventListener('keydown', handleEscape);
            }, 300);
        }
    }

    /**
     * Validate form field
     */
    function validateField(field) {
        const value = field.value.trim();
        const type = field.type;
        const required = field.hasAttribute('required');
        
        // Remove previous error
        removeFieldError(field);
        
        // Check required
        if (required && !value) {
            showFieldError(field, 'This field is required');
            return false;
        }
        
        // Validate email
        if (type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                showFieldError(field, 'Please enter a valid email address');
                return false;
            }
        }
        
        // Validate time
        if (type === 'time' && value) {
            const timeRegex = /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/;
            if (!timeRegex.test(value)) {
                showFieldError(field, 'Please enter a valid time (HH:MM)');
                return false;
            }
        }
        
        // Validate number
        if (type === 'number' && value) {
            const min = field.getAttribute('min');
            const max = field.getAttribute('max');
            const numValue = parseFloat(value);
            
            if (isNaN(numValue)) {
                showFieldError(field, 'Please enter a valid number');
                return false;
            }
            
            if (min && numValue < parseFloat(min)) {
                showFieldError(field, 'Value must be at least ' + min);
                return false;
            }
            
            if (max && numValue > parseFloat(max)) {
                showFieldError(field, 'Value must be at most ' + max);
                return false;
            }
        }
        
        return true;
    }

    /**
     * Show field error
     */
    function showFieldError(field, message) {
        field.classList.add('scheduler-field-error');
        
        const error = document.createElement('div');
        error.className = 'scheduler-field-error-message';
        error.textContent = message;
        
        field.parentNode.insertBefore(error, field.nextSibling);
    }

    /**
     * Remove field error
     */
    function removeFieldError(field) {
        field.classList.remove('scheduler-field-error');
        
        const error = field.parentNode.querySelector('.scheduler-field-error-message');
        if (error) {
            error.remove();
        }
    }

    /**
     * Validate entire form
     */
    function validateForm(form) {
        const fields = form.querySelectorAll('input, textarea, select');
        let isValid = true;
        
        fields.forEach(function(field) {
            if (!validateField(field)) {
                isValid = false;
            }
        });
        
        return isValid;
    }

    /**
     * Initialize form validation
     */
    function initFormValidation() {
        const forms = document.querySelectorAll('form');
        
        forms.forEach(function(form) {
            // Real-time validation on blur
            const fields = form.querySelectorAll('input, textarea, select');
            fields.forEach(function(field) {
                field.addEventListener('blur', function() {
                    validateField(field);
                });
                
                // Remove error on input
                field.addEventListener('input', function() {
                    if (field.classList.contains('scheduler-field-error')) {
                        removeFieldError(field);
                    }
                });
            });
            
            // Validate on submit
            form.addEventListener('submit', function(e) {
                if (!validateForm(form)) {
                    e.preventDefault();
                    showToast('Please fix the errors in the form', 'error');
                    
                    // Focus first error field
                    const firstError = form.querySelector('.scheduler-field-error');
                    if (firstError) {
                        firstError.focus();
                    }
                }
            });
        });
    }

    /**
     * Initialize AJAX operations
     */
    function initAjaxOperations() {
        // Intercept status dropdown changes
        const statusForms = document.querySelectorAll('form[action*="admin-post.php"]');
        statusForms.forEach(function(form) {
            const statusSelect = form.querySelector('select[name="status"]');
            const scheduleIdInput = form.querySelector('input[name="schedule_id"]');
            
            if (statusSelect && scheduleIdInput) {
                statusSelect.removeAttribute('onchange');
                statusSelect.addEventListener('change', function(e) {
                    e.preventDefault();
                    const scheduleId = scheduleIdInput.value;
                    const newStatus = statusSelect.value;
                    updateCallbackStatus(scheduleId, newStatus, statusSelect);
                });
            }
        });

        // Intercept delete buttons
        const deleteForms = document.querySelectorAll('form[action*="admin-post.php"] .btn-delete');
        deleteForms.forEach(function(btn) {
            const form = btn.closest('form');
            const scheduleIdInput = form.querySelector('input[name="schedule_id"]');
            
            if (scheduleIdInput) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const scheduleId = scheduleIdInput.value;
                    deleteCallback(scheduleId);
                });
            }
        });
    }

    /**
     * Update callback status via AJAX
     */
    function updateCallbackStatus(scheduleId, status, selectElement) {
        const originalValue = selectElement.getAttribute('data-original-value') || selectElement.value;
        selectElement.setAttribute('data-original-value', originalValue);
        
        showLoading(selectElement.parentElement);

        ajaxRequest({
            action: 'pax_sup_update_status',
            nonce: ajax.nonce,
            schedule_id: scheduleId,
            status: status,
        })
        .then(function(data) {
            hideLoading(selectElement.parentElement);
            
            if (data.success) {
                showToast(ajax.strings.updateSuccess || 'Status updated', 'success');
                
                // Update status badge
                const card = selectElement.closest('.scheduler-callback-card');
                if (card) {
                    const badge = card.querySelector('.callback-status');
                    if (badge) {
                        badge.className = 'callback-status status-' + status;
                        badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                    }
                    card.setAttribute('data-status', status);
                }
                
                // Refresh analytics
                refreshAnalytics();
            } else {
                showToast(data.data?.message || ajax.strings.errorOccurred || 'Error', 'error');
                selectElement.value = originalValue;
            }
        })
        .catch(function(error) {
            hideLoading(selectElement.parentElement);
            showToast(ajax.strings.errorOccurred || 'Error', 'error');
            selectElement.value = originalValue;
            console.error('AJAX error:', error);
        });
    }

    /**
     * Delete callback via AJAX
     */
    function deleteCallback(scheduleId) {
        confirmAction(
            ajax.strings.confirmDelete || 'Delete this callback?',
            function() {
                const card = document.querySelector('[data-id="' + scheduleId + '"]');
                if (card) {
                    showLoading(card);
                }

                fetch(ajax.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'pax_sup_delete_callback',
                        nonce: ajax.nonce,
                        schedule_id: scheduleId,
                    }),
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    if (data.success) {
                        showToast(ajax.strings.deleteSuccess || 'Deleted', 'success');
                        
                        // Remove card with animation
                        if (card) {
                            card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                            card.style.opacity = '0';
                            card.style.transform = 'translateX(-100px)';
                            
                            setTimeout(function() {
                                card.remove();
                                
                                // Check if list is empty
                                const callbacksList = document.getElementById('callbacks-list');
                                const remainingCards = callbacksList.querySelectorAll('.scheduler-callback-card');
                                
                                if (remainingCards.length === 0) {
                                    callbacksList.innerHTML = '<div class="scheduler-empty-state">' +
                                        '<span class="dashicons dashicons-calendar-alt"></span>' +
                                        '<h3>No Scheduled Callbacks</h3>' +
                                        '<p>Callbacks will appear here once visitors schedule them.</p>' +
                                        '</div>';
                                }
                            }, 300);
                        }
                        
                        // Refresh analytics
                        refreshAnalytics();
                    } else {
                        if (card) {
                            hideLoading(card);
                        }
                        showToast(data.data?.message || ajax.strings.errorOccurred || 'Error', 'error');
                    }
                })
                .catch(function(error) {
                    if (card) {
                        hideLoading(card);
                    }
                    showToast(ajax.strings.errorOccurred || 'Error', 'error');
                    console.error('AJAX error:', error);
                });
            },
            {
                title: 'Confirm Delete',
                confirmText: 'Delete',
                cancelText: 'Cancel',
                type: 'danger',
            }
        );
    }

    /**
     * Save inline edit via AJAX
     */
    function saveInlineEdit(scheduleId, note, element, originalHTML) {
        fetch(ajax.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'pax_sup_update_note',
                nonce: ajax.nonce,
                schedule_id: scheduleId,
                note: note,
            }),
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                element.textContent = note || '(No note)';
                showToast(ajax.strings.saveSuccess || 'Saved', 'success');
            } else {
                element.innerHTML = originalHTML;
                showToast(data.data?.message || ajax.strings.errorOccurred || 'Error', 'error');
            }
        })
        .catch(function(error) {
            element.innerHTML = originalHTML;
            showToast(ajax.strings.errorOccurred || 'Error', 'error');
            console.error('AJAX error:', error);
        });
    }

    /**
     * Save drag and drop order via AJAX
     */
    function saveDragOrder() {
        const cards = document.querySelectorAll('.scheduler-callback-card');
        const order = Array.from(cards).map(function(card) {
            return parseInt(card.getAttribute('data-id'));
        });

        fetch(ajax.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'pax_sup_reorder_callbacks',
                nonce: ajax.nonce,
                order: JSON.stringify(order),
            }),
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                showToast('Order saved', 'success');
            } else {
                showToast(data.data?.message || 'Failed to save order', 'error');
            }
        })
        .catch(function(error) {
            showToast('Failed to save order', 'error');
            console.error('AJAX error:', error);
        });
    }

    /**
     * Refresh analytics via AJAX
     */
    function refreshAnalytics() {
        fetch(ajax.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'pax_sup_get_callbacks',
                nonce: ajax.nonce,
            }),
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success && data.data.analytics) {
                const analytics = data.data.analytics;
                
                // Update metric values
                const metricCards = document.querySelectorAll('.scheduler-metric-card');
                metricCards.forEach(function(card) {
                    const icon = card.querySelector('.scheduler-metric-icon');
                    const value = card.querySelector('.scheduler-metric-value');
                    
                    if (icon && value) {
                        if (icon.classList.contains('scheduler-icon-today')) {
                            value.textContent = analytics.today;
                        } else if (icon.classList.contains('scheduler-icon-pending')) {
                            value.textContent = analytics.pending;
                        } else if (icon.classList.contains('scheduler-icon-completed')) {
                            value.textContent = analytics.completed;
                        } else if (icon.classList.contains('scheduler-icon-active')) {
                            value.textContent = analytics.active;
                        }
                    }
                });
            }
        })
        .catch(function(error) {
            console.error('Failed to refresh analytics:', error);
        });
    }

    /**
     * Initialize auto-refresh
     */
    function initAutoRefresh() {
        // Refresh every 30 seconds
        autoRefreshInterval = setInterval(function() {
            refreshAnalytics();
        }, 30000);
    }

    /**
     * Schedule Callback Modal
     */
    function initScheduleCallbackModal() {
        const btn = document.getElementById('schedule-callback-btn');
        const modal = document.getElementById('schedule-callback-modal');
        const overlay = document.getElementById('schedule-callback-overlay');
        const closeBtn = document.getElementById('schedule-callback-close');
        const cancelBtn = document.getElementById('schedule-callback-cancel');
        const form = document.getElementById('schedule-callback-form');
        
        if (!btn || !modal || !overlay || !form) return;
        
        // Open modal
        btn.addEventListener('click', function() {
            modal.classList.add('active');
            overlay.classList.add('active');
            // Set default date to today
            const dateInput = document.getElementById('callback-date');
            if (dateInput && !dateInput.value) {
                dateInput.value = new Date().toISOString().split('T')[0];
            }
        });
        
        // Close modal
        function closeModal() {
            modal.classList.remove('active');
            overlay.classList.remove('active');
            form.reset();
        }
        
        closeBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);
        overlay.addEventListener('click', closeModal);
        
        // Handle form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            const data = {};
            formData.forEach((value, key) => data[key] = value);
            
            // Validate time is within allowed hours
            const time = data.time;
            const settings = window.paxScheduler?.settings || {};
            const startHour = settings.start || '09:00';
            const endHour = settings.end || '17:00';
            
            if (time < startHour || time > endHour) {
                showToast('error', 'Selected time is outside available hours (' + startHour + ' - ' + endHour + ')');
                return;
            }
            
            showLoading('Scheduling callback...');
            
            // Submit via AJAX
            ajaxRequest({
                action: 'pax_sup_schedule_callback_admin',
                nonce: ajax.nonce,
                ...data
            }).then(function(response) {
                hideLoading();
                if (response.success) {
                    showToast('success', response.data.message || 'Callback scheduled successfully');
                    closeModal();
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showToast('error', response.data.message || 'Failed to schedule callback');
                }
            }).catch(function(error) {
                hideLoading();
                showToast('error', 'Network error. Please try again.');
            });
        });
    }
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initScheduleCallbackModal);
    } else {
        initScheduleCallbackModal();
    }

    // Make functions available globally for debugging and external use
    window.schedulerShowToast = showToast;
    window.schedulerShowLoading = showLoading;
    window.schedulerHideLoading = hideLoading;
    window.schedulerConfirm = confirmAction;
    window.schedulerRefreshAnalytics = refreshAnalytics;
})();
