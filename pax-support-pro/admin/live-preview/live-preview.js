/**
 * PAX Support Pro - Live Preview System
 * Real-time preview of admin settings changes
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
        console.log('[PAX Live Preview] Initializing...');
        
        // Get preview elements
        const preview = {
            container: document.getElementById('pax-live-preview'),
            brand: document.getElementById('pax-preview-brand'),
            welcome: document.getElementById('pax-preview-welcome'),
            launcher: document.getElementById('pax-preview-launcher'),
            chat: document.getElementById('pax-preview-chat'),
            send: document.getElementById('pax-preview-send'),
            statusEnabled: document.getElementById('pax-status-enabled'),
            statusChat: document.getElementById('pax-status-chat'),
            statusAI: document.getElementById('pax-status-ai')
        };
        
        if (!preview.container) {
            console.warn('[PAX Live Preview] Container not found');
            return;
        }
        
        // Initialize with current values
        updateAllPreviews();
        
        // Set up input listeners
        setupInputListeners();
        
        // Set up MutationObserver for dynamic fields
        setupMutationObserver();
        
        console.log('[PAX Live Preview] Initialized successfully');
    }
    
    /**
     * Update all preview elements with current form values
     */
    function updateAllPreviews() {
        updateBrandName();
        updateWelcomeMessage();
        updateColors();
        updateToggles();
    }
    
    /**
     * Update brand name in preview
     */
    function updateBrandName() {
        const brandInput = document.querySelector('input[name="brand_name"]');
        const brandElement = document.getElementById('pax-preview-brand');
        
        if (brandInput && brandElement) {
            const value = brandInput.value || 'PAX SUPPORT';
            brandElement.textContent = value;
            animateElement(brandElement);
        }
    }
    
    /**
     * Update welcome message in preview
     */
    function updateWelcomeMessage() {
        // Try to find welcome message field (might be in different formats)
        const welcomeInput = document.querySelector('input[name="welcome_message"]') ||
                           document.querySelector('textarea[name="welcome_message"]') ||
                           document.querySelector('[name*="welcome"]');
        const welcomeElement = document.getElementById('pax-preview-welcome');
        
        if (welcomeInput && welcomeElement) {
            const value = welcomeInput.value || '👋 Welcome! How can I help you today?';
            welcomeElement.textContent = value;
            animateElement(welcomeElement);
        }
    }
    
    /**
     * Update all color variables in preview
     */
    function updateColors() {
        const colorMap = {
            'color_accent': '--pax-accent',
            'color_bg': '--pax-bg',
            'color_panel': '--pax-panel',
            'color_border': '--pax-border',
            'color_text': '--pax-text',
            'color_sub': '--pax-sub'
        };
        
        const preview = document.getElementById('pax-live-preview');
        if (!preview) return;
        
        Object.keys(colorMap).forEach(function(inputName) {
            const input = document.querySelector('input[name="' + inputName + '"]');
            if (input && input.value) {
                preview.style.setProperty(colorMap[inputName], input.value);
            }
        });
    }
    
    /**
     * Update toggle states in preview
     */
    function updateToggles() {
        updateToggle('enabled', 'pax-status-enabled');
        updateToggle('enable_chat', 'pax-status-chat');
        updateToggle('ai_assistant_enabled', 'pax-status-ai');
    }
    
    /**
     * Update individual toggle state
     */
    function updateToggle(inputName, elementId) {
        const input = document.querySelector('input[name="' + inputName + '"]');
        const element = document.getElementById(elementId);
        
        if (input && element) {
            const isChecked = input.checked || input.value === '1';
            if (isChecked) {
                element.classList.add('active');
            } else {
                element.classList.remove('active');
            }
        }
    }
    
    /**
     * Set up input event listeners
     */
    function setupInputListeners() {
        // Brand name
        const brandInput = document.querySelector('input[name="brand_name"]');
        if (brandInput) {
            brandInput.addEventListener('input', debounce(updateBrandName, 100));
            brandInput.addEventListener('change', updateBrandName);
        }
        
        // Welcome message
        const welcomeInput = document.querySelector('input[name="welcome_message"]') ||
                           document.querySelector('textarea[name="welcome_message"]') ||
                           document.querySelector('[name*="welcome"]');
        if (welcomeInput) {
            welcomeInput.addEventListener('input', debounce(updateWelcomeMessage, 100));
            welcomeInput.addEventListener('change', updateWelcomeMessage);
        }
        
        // Color inputs
        const colorInputs = document.querySelectorAll('input[name^="color_"]');
        colorInputs.forEach(function(input) {
            input.addEventListener('input', debounce(updateColors, 50));
            input.addEventListener('change', updateColors);
        });
        
        // Toggle inputs
        const toggleInputs = document.querySelectorAll('input[name="enabled"], input[name="enable_chat"], input[name="ai_assistant_enabled"]');
        toggleInputs.forEach(function(input) {
            input.addEventListener('change', updateToggles);
        });
        
        // Listen to all form inputs for comprehensive coverage
        const allInputs = document.querySelectorAll('.pax-form-group input, .pax-form-group textarea, .pax-form-group select');
        allInputs.forEach(function(input) {
            input.addEventListener('change', function() {
                updateAllPreviews();
            });
        });
    }
    
    /**
     * Set up MutationObserver for dynamically added fields
     */
    function setupMutationObserver() {
        const targetNode = document.querySelector('.pax-settings-container') || document.body;
        
        // Debounce the mutation callback to prevent freeze
        const debouncedMutationHandler = debounce(function() {
            setupInputListeners();
            updateAllPreviews();
        }, 1000);
        
        const observer = new MutationObserver(function(mutations) {
            let hasRelevantChanges = false;
            
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    hasRelevantChanges = true;
                }
            });
            
            if (hasRelevantChanges) {
                debouncedMutationHandler();
            }
        });
        
        observer.observe(targetNode, {
            childList: true,
            subtree: true
        });
    }
    
    /**
     * Animate element on update
     */
    function animateElement(element) {
        if (!element) return;
        
        element.style.transform = 'scale(1.05)';
        element.style.filter = 'brightness(1.2)';
        
        setTimeout(function() {
            element.style.transform = '';
            element.style.filter = '';
        }, 200);
    }
    
    /**
     * Debounce function to limit update frequency
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction() {
            const context = this;
            const args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    }
    
    /**
     * Handle color picker changes (for WordPress color pickers)
     */
    if (typeof jQuery !== 'undefined') {
        jQuery(document).ready(function($) {
            // WordPress color picker
            $('.pax-color-input').wpColorPicker({
                change: function() {
                    updateColors();
                },
                clear: function() {
                    updateColors();
                }
            });
        });
    }
    
})();
