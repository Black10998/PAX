/**
 * PAX Support Pro - Unified Chat Engine
 * Merges Assistant + Live Agent into single interface with mode switching
 * 
 * @package PAX_Support_Pro
 * @version 5.6.9
 */

(function() {
    'use strict';

    // v5.6.1: Debug mode (set to false for production)
    window.PAX_DEBUG_MODE = false;
    
    // Debug helper functions
    function paxDebugLog(category, message, data) {
        if (!window.PAX_DEBUG_MODE) return;
        const timestamp = new Date().toISOString().split('T')[1].slice(0, -1);
        const prefix = `[PAX-DEBUG ${timestamp}] ${category}:`;
        if (data) {
            console.log(prefix, message, data);
        } else {
            console.log(prefix, message);
        }
    }
    
    function paxErrorLog(category, error, context) {
        console.error(`[PAX-ERROR] ${category}:`, error, context || {});
    }
    
    function paxInspectState(label, state) {
        if (!window.PAX_DEBUG_MODE) return;
        console.group(`🔍 ${label}`);
        console.log(state);
        console.groupEnd();
    }
    
    window.paxDebugLog = paxDebugLog;
    window.paxErrorLog = paxErrorLog;
    window.paxInspectState = paxInspectState;

    /**
     * PAX Settings Manager
     * Full synchronization system for admin settings to frontend UI
     */
    class PAXSettingsManager {
        constructor() {
            this.state = {
                pluginEnabled: true,
                chatEnabled: true,
                accessControl: 'everyone',
                isLoggedIn: false,
                disableMenu: false,
                toggleOnClick: false,
                features: {},
                colorScheme: {},
                brandName: '',
                customLauncherIcon: '',
                customSendIcon: '',
                messages: {},
                urls: {},
                launcherPosition: 'bottom-right'
            };
            this.loaded = false;
        }

        loadSettings() {
            try {
                paxDebugLog('SETTINGS', 'Loading settings from window.paxSupportPro');
                console.log('PAX-SETTINGS: Loading settings from window.paxSupportPro');
                
                // Debug: Inspect window.paxSupportPro
                paxInspectState('window.paxSupportPro', window.paxSupportPro);
                
                const pax = window.paxSupportPro || {};
                const options = pax.options || {};
                
                paxDebugLog('SETTINGS', 'Options loaded', options);
            
            // Core toggles
            this.state.pluginEnabled = !!options.enabled;
            this.state.chatEnabled = !!options.enable_chat;
            this.state.accessControl = options.chat_access_control || 'everyone';
            this.state.isLoggedIn = !!pax.isLoggedIn;
            this.state.disableMenu = !!options.disable_chat_menu;
            this.state.toggleOnClick = !!options.toggle_on_click;
            
            // Features
            this.state.features = {
                aiAssistant: !!options.ai_assistant_enabled,
                liveAgent: !!options.live_agent_enabled,
                tickets: !!options.enable_tickets,
                callback: !!options.enable_callback,
                feedback: !!options.enable_feedback,
                diagnostics: !!options.enable_diagnostics,
                troubleshooter: !!options.enable_troubleshooter,
                orderLookup: !!options.enable_order_lookup,
                myRequest: !!options.enable_my_request,
                donate: !!options.enable_donate,
                speed: !!options.enable_speed,
                helpCenter: !!options.enable_help_center,
                whatsNew: !!options.enable_whats_new,
                quickActions: !!options.enable_quick_actions,
                replyTo: !!options.enable_reply_to,
                offlineGuard: !!options.enable_offline_guard
            };
            
            // Color scheme (from CSS variables or options)
            this.state.colorScheme = {
                primary: options.color_accent || '#4f46e5',
                background: options.color_bg || '#ffffff',
                panel: options.color_panel || '#f9fafb',
                border: options.color_border || '#e5e7eb',
                text: options.color_text || '#111827',
                subtext: options.color_sub || '#6b7280',
                reaction: options.reaction_btn_color || '#e53935'
            };
            
            // Branding
            this.state.brandName = options.brand_name || 'PAX Support';
            this.state.customLauncherIcon = options.custom_launcher_icon || '';
            this.state.customSendIcon = options.custom_send_icon || '';
            
            // Messages
            this.state.messages = {
                welcome: options.welcome_message || '👋 Welcome! How can I help you today?',
                chatDisabled: options.chat_disabled_message || 'Chat disabled by administrator.',
                loginRequired: 'Please log in to use the chat system.'
            };
            
            // URLs
            const links = pax.links || {};
            this.state.urls = {
                whatsNew: links.whatsNew || '',
                donate: links.donate || '',
                helpCenter: links.help || ''
            };
            
            // Position
            this.state.launcherPosition = options.launcher_position || 'bottom-right';
            
            console.log('PAX-SETTINGS: Normalized state:', this.state);
            paxInspectState('PAXSettingsManager.state', this.state);
            
            this.loaded = true;
            paxDebugLog('SETTINGS', 'Settings loaded successfully', { loaded: this.loaded });
            return this.state;
            
            } catch (error) {
                paxErrorLog('SETTINGS', error, { method: 'loadSettings' });
                // Return safe defaults on error
                this.loaded = false;
                return this.state;
            }
        }

        applyFunctionalState() {
            console.log('PAX-SYNC: Applying functional state to UI');
            
            // Feature toggles will be handled by individual components
            // This method provides the state for other components to use
            
            console.log('PAX-FEATURE: AI Assistant:', this.state.features.aiAssistant);
            console.log('PAX-FEATURE: Live Agent:', this.state.features.liveAgent);
            console.log('PAX-FEATURE: Quick Actions:', this.state.features.quickActions);
            console.log('PAX-FEATURE: Reply-To:', this.state.features.replyTo);
        }

        applyVisualState() {
            console.log('PAX-SYNC: Applying visual state to UI');
            
            // Apply color scheme
            this.applyColorScheme();
            
            // Apply launcher position
            this.applyLauncherPosition();
            
            // Apply custom icons (handled by individual components)
            console.log('PAX-SYNC: Custom launcher icon:', this.state.customLauncherIcon);
            console.log('PAX-SYNC: Custom send icon:', this.state.customSendIcon);
        }

        applyColorScheme() {
            console.log('PAX-COLOR: Applying color scheme');
            
            const root = document.documentElement;
            const colors = this.state.colorScheme;
            
            root.style.setProperty('--pax-accent', colors.primary);
            root.style.setProperty('--pax-bg', colors.background);
            root.style.setProperty('--pax-panel', colors.panel);
            root.style.setProperty('--pax-border', colors.border);
            root.style.setProperty('--pax-text', colors.text);
            root.style.setProperty('--pax-sub', colors.subtext);
            
            // Convert reaction color to rgba
            const reactionRgb = this.hexToRgb(colors.reaction);
            if (reactionRgb) {
                root.style.setProperty('--pax-reaction-bg', `rgba(${reactionRgb.r}, ${reactionRgb.g}, ${reactionRgb.b}, 0.9)`);
                root.style.setProperty('--pax-reaction-bg-hover', colors.reaction);
                root.style.setProperty('--pax-reaction-border', `rgba(${reactionRgb.r}, ${reactionRgb.g}, ${reactionRgb.b}, 0.5)`);
            }
            
            console.log('PAX-COLOR: Updated CSS variables:', colors);
        }

        applyLauncherPosition() {
            const launcher = document.getElementById('pax-unified-launcher');
            if (!launcher) return;
            
            // Remove old position classes
            launcher.classList.remove('position-bottom-right', 'position-bottom-left', 'position-top-right', 'position-top-left');
            
            // Add new position class
            launcher.classList.add(`position-${this.state.launcherPosition}`);
            
            console.log('PAX-SYNC: Applied launcher position:', this.state.launcherPosition);
        }

        hexToRgb(hex) {
            const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
            return result ? {
                r: parseInt(result[1], 16),
                g: parseInt(result[2], 16),
                b: parseInt(result[3], 16)
            } : null;
        }

        syncToUI() {
            console.log('PAX-SYNC: Starting full UI synchronization');
            
            this.applyFunctionalState();
            this.applyVisualState();
            
            console.log('PAX-SYNC: Full sync complete');
            
            this.dispatchSyncEvent();
        }

        dispatchSyncEvent() {
            console.log('PAX-SYNC: Dispatching paxSettingsSynced event');
            const event = new CustomEvent('paxSettingsSynced', {
                detail: this.state
            });
            document.dispatchEvent(event);
        }
    }

    /**
     * PAX Access Control Manager
     * Centralized access control system that ensures all UI elements respect admin settings
     */
    class PAXAccessControl {
        constructor() {
            this.state = {
                chatEnabled: false,
                requiresLogin: false,
                isLoggedIn: false,
                menuEnabled: true,
                features: {},
                menuItems: {}
            };
            this.loaded = false;
        }

        loadSettings() {
            console.log('PAX-ACCESS: Loading settings from window.paxSupportPro');
            
            const pax = window.paxSupportPro || {};
            const options = pax.options || {};
            const menuItems = pax.menuItems || {};
            
            // Global access control
            const chatAccess = options.chat_access_control || 'everyone';
            this.state.chatEnabled = chatAccess !== 'disabled';
            this.state.requiresLogin = chatAccess === 'logged_in';
            this.state.isLoggedIn = !!pax.isLoggedIn;
            this.state.menuEnabled = !options.disable_chat_menu;
            
            console.log('PAX-ACCESS: Global settings:', {
                chat_access_control: chatAccess,
                chatEnabled: this.state.chatEnabled,
                requiresLogin: this.state.requiresLogin,
                isLoggedIn: this.state.isLoggedIn,
                menuEnabled: this.state.menuEnabled
            });
            
            // Feature flags from options
            this.state.features = {
                tickets: !!options.enable_tickets,
                feedback: !!options.enable_feedback,
                callback: !!options.enable_callback,
                diagnostics: !!options.enable_diagnostics,
                troubleshooter: !!options.enable_troubleshooter,
                orderLookup: !!options.enable_order_lookup,
                myRequest: !!options.enable_my_request,
                donate: !!options.enable_donate,
                speed: !!options.enable_speed,
                helpCenter: !!options.enable_help_center,
                whatsNew: !!options.enable_whats_new
            };
            
            console.log('PAX-ACCESS: Feature flags:', this.state.features);
            
            // Menu items with computed enabled state
            this.state.menuItems = {};
            for (const [key, item] of Object.entries(menuItems)) {
                this.state.menuItems[key] = {
                    visible: !!item.visible,
                    requiresLogin: !!item.requiresLogin,
                    enabled: this.computeFeatureEnabled(key, item)
                };
            }
            
            console.log('PAX-ACCESS: Menu items:', this.state.menuItems);
            
            this.loaded = true;
            this.dispatchStateChange();
        }

        computeFeatureEnabled(key, item) {
            // Map menu item keys to feature flags
            const featureMap = {
                'ticket': 'tickets',
                'feedback': 'feedback',
                'callback': 'callback',
                'diag': 'diagnostics',
                'troubleshooter': 'troubleshooter',
                'order': 'orderLookup',
                'myreq': 'myRequest',
                'donate': 'donate',
                'speed': 'speed',
                'help': 'helpCenter',
                'whatsnew': 'whatsNew'
            };
            
            const featureKey = featureMap[key];
            if (featureKey && this.state.features.hasOwnProperty(featureKey)) {
                return this.state.features[featureKey];
            }
            
            // Default to visible if no feature flag mapping
            return !!item.visible;
        }

        canAccessChat() {
            console.log('PAX-ACCESS: Checking chat access');
            
            if (!this.state.chatEnabled) {
                console.log('PAX-BLOCK: Chat disabled by admin');
                return { allowed: false, reason: 'Chat is currently disabled by administrator' };
            }
            
            if (this.state.requiresLogin && !this.state.isLoggedIn) {
                console.log('PAX-BLOCK: Login required, user not logged in');
                return { allowed: false, reason: 'Please log in to use the chat system.' };
            }
            
            console.log('PAX-ALLOW: Chat access granted');
            return { allowed: true };
        }

        canAccessFeature(feature) {
            console.log('PAX-ACCESS: Checking feature access:', feature);
            
            // Check global chat access first
            const chatAccess = this.canAccessChat();
            if (!chatAccess.allowed) {
                return chatAccess;
            }
            
            // Check feature flag
            if (!this.state.features[feature]) {
                console.log('PAX-BLOCK: Feature disabled:', feature);
                return { allowed: false, reason: 'This feature is currently unavailable' };
            }
            
            console.log('PAX-ALLOW: Feature access granted:', feature);
            return { allowed: true };
        }

        canAccessMenuItem(itemKey) {
            console.log('PAX-ACCESS: Checking menu item access:', itemKey);
            
            // Check global chat access first
            const chatAccess = this.canAccessChat();
            if (!chatAccess.allowed) {
                return chatAccess;
            }
            
            const item = this.state.menuItems[itemKey];
            if (!item) {
                console.log('PAX-BLOCK: Menu item not found:', itemKey);
                return { allowed: false, reason: 'This feature is currently unavailable' };
            }
            
            // Check visibility
            if (!item.visible) {
                console.log('PAX-BLOCK: Menu item not visible:', itemKey);
                return { allowed: false, reason: 'This feature is currently unavailable' };
            }
            
            // Check enabled state (from feature flags)
            if (!item.enabled) {
                console.log('PAX-BLOCK: Menu item feature disabled:', itemKey);
                return { allowed: false, reason: 'This feature is currently unavailable' };
            }
            
            // Check login requirement
            if (item.requiresLogin && !this.state.isLoggedIn) {
                console.log('PAX-BLOCK: Menu item requires login:', itemKey);
                return { allowed: false, reason: 'Please log in to access this feature' };
            }
            
            console.log('PAX-ALLOW: Menu item access granted:', itemKey);
            return { allowed: true };
        }

        dispatchStateChange() {
            console.log('PAX-STATE: Dispatching paxAccessStateChanged event');
            const event = new CustomEvent('paxAccessStateChanged', {
                detail: this.state
            });
            document.dispatchEvent(event);
        }
    }

    /**
     * PAX Event Bindings Manager
     * Manages all event listeners with idempotent rebinding support
     */
    class PAXEventBindings {
        constructor(accessControl, chatInstance) {
            this.accessControl = accessControl;
            this.chatInstance = chatInstance;
            this.bindings = {
                launcher: { element: null, handler: null, bound: false },
                chatMenu: { element: null, handler: null, bound: false },
                headerMenu: { element: null, handler: null, bound: false },
                closeBtn: { element: null, handler: null, bound: false },
                overlay: { element: null, handler: null, bound: false },
                sendBtn: { element: null, handler: null, bound: false },
                inputField: { element: null, handler: null, bound: false }
            };
        }

        unbindAll() {
            console.log('PAX-BIND: Unbinding all event listeners');
            
            for (const [name, binding] of Object.entries(this.bindings)) {
                if (binding.bound && binding.element && binding.handler) {
                    console.log('PAX-BIND: Unbinding', name);
                    binding.element.removeEventListener('click', binding.handler);
                    binding.element.removeEventListener('keypress', binding.handler);
                    binding.bound = false;
                }
            }
        }

        bindLauncher() {
            try {
                const launcher = document.getElementById('pax-unified-launcher');
                if (!launcher) return;
                
                // Remove all existing event listeners by cloning
                launcher.replaceWith(launcher.cloneNode(true));
                const newLauncher = document.getElementById('pax-unified-launcher');
                
                // Bind single click handler
                newLauncher.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    // Only open chat - close button is the only way to close
                    if (!this.chatInstance.isOpen) {
                        this.chatInstance.openChat();
                    }
                });
                
                this.bindings.launcher.element = newLauncher;
                this.bindings.launcher.bound = true;
                console.log('PAX-BIND: Launcher single-bound and synchronized');
            } catch (error) {
                paxErrorLog('BIND', error, { method: 'bindLauncher' });
            }
        }

        recreateLauncher() {
            console.log('PAX-LAUNCHER: Recreating launcher element');
            
            const launcher = document.createElement('button');
            launcher.id = 'pax-unified-launcher';
            launcher.className = 'pax-unified-launcher';
            launcher.setAttribute('title', 'Open Chat');
            launcher.setAttribute('aria-label', 'Toggle Chat Window');
            
            // Add default icon (SVG)
            launcher.innerHTML = `
                <svg class="pax-launcher-icon" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                </svg>
            `;
            
            document.body.appendChild(launcher);
            console.log('PAX-LAUNCHER: Launcher recreated');
            
            // Bind the recreated launcher
            this.bindLauncher();
        }

        bindChatMenu() {
            const menuBtn = document.getElementById('pax-head-more');
            
            if (!menuBtn) {
                console.log('PAX-BIND: Chat menu button not found');
                return;
            }
            
            // Unbind existing
            if (this.bindings.chatMenu.bound && this.bindings.chatMenu.handler) {
                menuBtn.removeEventListener('click', this.bindings.chatMenu.handler);
            }
            
            // Store element
            this.bindings.chatMenu.element = menuBtn;
            
            // Check if menu should be available
            if (!this.accessControl.state.menuEnabled) {
                menuBtn.style.display = 'none';
                console.log('PAX-BIND: Chat menu disabled by admin');
                return;
            }
            
            // Create handler with access control
            this.bindings.chatMenu.handler = (e) => {
                e.stopPropagation();
                console.log('PAX-CLICK: Chat menu button (⋮) clicked');
                
                const access = this.accessControl.canAccessChat();
                if (!access.allowed) {
                    console.log('PAX-BLOCK: Chat menu blocked:', access.reason);
                    this.chatInstance.showToast(access.reason);
                    return;
                }
                
                console.log('PAX-ALLOW: Opening chat menu');
                this.chatInstance.toggleChatMenu();
            };
            
            menuBtn.addEventListener('click', this.bindings.chatMenu.handler);
            menuBtn.style.display = '';
            this.bindings.chatMenu.bound = true;
            console.log('PAX-BIND: Chat menu bound');
        }

        bindHeaderMenu() {
            const headerBtn = document.getElementById('pax-menu-btn');
            
            if (!headerBtn) {
                console.log('PAX-BIND: Header menu button not found');
                return;
            }
            
            // Unbind existing
            if (this.bindings.headerMenu.bound && this.bindings.headerMenu.handler) {
                headerBtn.removeEventListener('click', this.bindings.headerMenu.handler);
            }
            
            // Store element
            this.bindings.headerMenu.element = headerBtn;
            
            // Header menu always available (Settings, Clear, About, System Info)
            this.bindings.headerMenu.handler = (e) => {
                e.stopPropagation();
                console.log('PAX-CLICK: Header menu button (☰) clicked');
                this.chatInstance.toggleHeaderMenu();
            };
            
            headerBtn.addEventListener('click', this.bindings.headerMenu.handler);
            this.bindings.headerMenu.bound = true;
            console.log('PAX-BIND: Header menu bound');
        }

        bindChatControls() {
            // Close button
            const closeBtn = document.getElementById('pax-close');
            if (closeBtn) {
                if (this.bindings.closeBtn.handler) {
                    closeBtn.removeEventListener('click', this.bindings.closeBtn.handler);
                }
                this.bindings.closeBtn.element = closeBtn;
                this.bindings.closeBtn.handler = () => this.chatInstance.closeChat();
                closeBtn.addEventListener('click', this.bindings.closeBtn.handler);
                this.bindings.closeBtn.bound = true;
                console.log('PAX-BIND: Close button bound');
            }
            
            // Overlay (no click handler - chat only closes via X button)
            const overlay = document.getElementById('pax-chat-overlay');
            if (overlay) {
                if (this.bindings.overlay.handler) {
                    overlay.removeEventListener('click', this.bindings.overlay.handler);
                }
                this.bindings.overlay.element = overlay;
                this.bindings.overlay.handler = null; // No click handler
                this.bindings.overlay.bound = true;
                console.log('PAX-BIND: Overlay element tracked (no click handler)');
            }
            
            // Send button
            const sendBtn = document.getElementById('pax-send');
            if (sendBtn) {
                if (this.bindings.sendBtn.handler) {
                    sendBtn.removeEventListener('click', this.bindings.sendBtn.handler);
                }
                this.bindings.sendBtn.element = sendBtn;
                this.bindings.sendBtn.handler = () => this.chatInstance.handleSend();
                sendBtn.addEventListener('click', this.bindings.sendBtn.handler);
                this.bindings.sendBtn.bound = true;
                console.log('PAX-BIND: Send button bound');
            }
            
            // Input field
            const inputField = document.getElementById('pax-input');
            if (inputField) {
                if (this.bindings.inputField.handler) {
                    inputField.removeEventListener('keypress', this.bindings.inputField.handler);
                }
                this.bindings.inputField.element = inputField;
                this.bindings.inputField.handler = (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.chatInstance.handleSend();
                    }
                };
                inputField.addEventListener('keypress', this.bindings.inputField.handler);
                this.bindings.inputField.bound = true;
                console.log('PAX-BIND: Input field bound');
            }
        }

        bindAll() {
            console.log('PAX-BIND: Binding all event listeners with access control');
            this.bindLauncher();
            this.bindChatMenu();
            this.bindHeaderMenu();
            this.bindChatControls();
        }

        rebindAll() {
            console.log('PAX-BIND: Rebinding all event listeners');
            this.unbindAll();
            this.bindAll();
        }
    }

    class PAXUnifiedChat {
        constructor() {
            this.currentMode = 'assistant'; // 'assistant' | 'liveagent'
            this.sessions = {
                assistant: {
                    messages: [],
                    context: {},
                    history: [],
                    aiController: null
                },
                liveagent: {
                    sessionId: null,
                    messages: [],
                    status: 'idle',
                    agentInfo: null,
                    unreadCount: 0,
                    restBase: null,
                    startedAt: null,
                    timeout: null
                }
            };
            this.liveConfig = window.PAX_LIVE || {};
            this.liveRestBase = null;
            this.replyToMessage = null;
            this.pollInterval = null;
            this.isPolling = false;
            this.lastMessageId = 0;
            this.isOpen = false; // Track chat open/close state
            
            // DOM elements
            this.chatWindow = null;
            this.messageContainer = null;
            this.inputField = null;
            this.sendButton = null;
            this.modeSwitcher = null;
            
            // v5.5.6: Settings and access control system
            this.settingsManager = new PAXSettingsManager();
            this.accessControl = new PAXAccessControl();
            this.eventBindings = null; // Will be initialized after DOM ready
            
            this.init();
        }

        init() {
            // Wait for DOM ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setup());
            } else {
                this.setup();
            }
        }

        setup() {
            try {
                paxDebugLog('INIT', 'setup() started');
                
                // Get DOM elements
                this.chatWindow = document.getElementById('pax-chat');
                this.messageContainer = document.getElementById('pax-messages');
                this.inputField = document.getElementById('pax-input');
                this.sendButton = document.getElementById('pax-send');
                
                console.log('PAX-INIT: DOM elements retrieved:', {
                    chatWindow: !!this.chatWindow,
                    messageContainer: !!this.messageContainer,
                    inputField: !!this.inputField,
                    sendButton: !!this.sendButton
                });
                
                paxDebugLog('INIT', 'DOM elements retrieved', {
                    chatWindow: !!this.chatWindow,
                    messageContainer: !!this.messageContainer,
                    inputField: !!this.inputField,
                    sendButton: !!this.sendButton
                });
                
                if (!this.chatWindow || !this.messageContainer) {
                    console.error('PAX-INIT: CRITICAL - Required DOM elements not found!');
                    console.error('PAX-INIT: chatWindow:', this.chatWindow);
                    console.error('PAX-INIT: messageContainer:', this.messageContainer);
                    paxErrorLog('INIT', new Error('Required DOM elements not found'), {
                        chatWindow: !!this.chatWindow,
                        messageContainer: !!this.messageContainer
                    });
                    return;
                }
                
                // Log initial chat window state
                const initialStyle = window.getComputedStyle(this.chatWindow);
                console.log('PAX-INIT: Initial chat window state:', {
                    display: initialStyle.display,
                    opacity: initialStyle.opacity,
                    visibility: initialStyle.visibility,
                    classList: Array.from(this.chatWindow.classList)
                });

                // Load saved state
                this.loadState();
                paxDebugLog('INIT', 'State loaded');

                // Setup mode switcher
                this.setupModeSwitcher();
                paxDebugLog('INIT', 'Mode switcher setup');

                // v5.5.5: Initialize with access control system
                this.initializeWithAccessControl();
                paxDebugLog('INIT', 'Access control initialized');

                // Show welcome message if no messages
                if (this.sessions[this.currentMode].messages.length === 0) {
                    this.showWelcomeMessage();
                    paxDebugLog('INIT', 'Welcome message shown');
                }

                // Restore current mode
                this.switchMode(this.currentMode, false);
                paxDebugLog('INIT', 'Mode switched', { mode: this.currentMode });

                console.log('PAX Unified Chat initialized in', this.currentMode, 'mode');
                paxDebugLog('INIT', 'setup() completed successfully');
                
            } catch (error) {
                paxErrorLog('INIT', error, { method: 'setup' });
            }
        }

        initializeWithAccessControl() {
            console.log('PAX-ACCESS: Initializing with access control system');
            
            // Wait for settings to be ready
            if (window.paxSupportPro?.settingsLoaded) {
                console.log('PAX-ACCESS: Settings already loaded');
                this.setupAccessControl();
            } else {
                console.log('PAX-ACCESS: Waiting for paxSettingsReady event');
                document.addEventListener('paxSettingsReady', () => {
                    console.log('PAX-ACCESS: paxSettingsReady event received');
                    this.setupAccessControl();
                }, { once: true });
                
                // Fallback
                setTimeout(() => {
                    if (!this.eventBindings) {
                        console.warn('PAX-ACCESS: Fallback initialization after 1s timeout');
                        this.setupAccessControl();
                    }
                }, 1000);
            }
        }

        setupAccessControl() {
            try {
                console.log('PAX-ACCESS: Setting up access control');
                paxDebugLog('INIT', 'setupAccessControl() started');
                
                // v5.5.7-debug: Comprehensive state inspection
                console.group('🔍 PAX INITIALIZATION DEBUG');
                console.log('Timestamp:', new Date().toISOString());
                console.log('window.paxSupportPro:', window.paxSupportPro);
                console.log('settingsManager:', this.settingsManager);
                console.log('accessControl:', this.accessControl);
                console.log('chatWindow:', this.chatWindow);
                console.log('messageContainer:', this.messageContainer);
                console.log('inputField:', this.inputField);
                console.log('sendButton:', this.sendButton);
                console.groupEnd();
                
                // v5.5.6: Load settings first
                this.settingsManager.loadSettings();
                this.settingsManager.syncToUI();
                
                // Load settings into access control
                this.accessControl.loadSettings();
                
                // Initialize event bindings manager
                this.eventBindings = new PAXEventBindings(this.accessControl, this);
                paxDebugLog('INIT', 'Event bindings manager created');
                
                // Bind all events with access control
                this.eventBindings.bindAll();
                paxDebugLog('INIT', 'All events bound');
                
                // Setup menus (now with access control)
                this.setupMenusWithAccessControl();
                paxDebugLog('INIT', 'Menus setup complete');
                
                // Listen for access state changes
                document.addEventListener('paxAccessStateChanged', () => {
                    console.log('PAX-STATE: Access state changed, rebinding events');
                    paxDebugLog('STATE', 'Access state changed event received');
                    this.eventBindings.rebindAll();
                    this.setupMenusWithAccessControl();
                });
                
                // Listen for settings sync events
                document.addEventListener('paxSettingsSynced', () => {
                    console.log('PAX-STATE: Settings synced, updating UI');
                    paxDebugLog('STATE', 'Settings synced event received');
                    this.accessControl.loadSettings();
                    this.eventBindings.rebindAll();
                    this.setupMenusWithAccessControl();
                });
                
                console.log('PAX-ACCESS: Access control setup complete');
                paxDebugLog('INIT', 'setupAccessControl() completed successfully');
                
                // Final state inspection
                paxInspectState('Final Initialization State', {
                    settingsManager: this.settingsManager.state,
                    accessControl: this.accessControl.state,
                    eventBindings: this.eventBindings.bindings,
                    chatWindow: !!this.chatWindow
                });
                
                // v5.6.1: Run self-test
                this.runSelfTest();
                
            } catch (error) {
                paxErrorLog('INIT', error, { method: 'setupAccessControl' });
            }
        }

        runSelfTest() {
            // v5.6.1: Comprehensive self-test to verify all components
            console.group('🔍 PAX Chat System Self-Test');
            
            const launcher = document.getElementById('pax-unified-launcher');
            const chatWindow = document.getElementById('pax-chat');
            const messageContainer = document.getElementById('pax-messages');
            const inputField = document.getElementById('pax-input');
            const sendButton = document.getElementById('pax-send');
            const overlay = document.getElementById('pax-chat-overlay');
            
            // DOM element tests
            const tests = {
                launcher: !!launcher,
                chatWindow: !!chatWindow,
                messageContainer: !!messageContainer,
                inputField: !!inputField,
                sendButton: !!sendButton,
                overlay: !!overlay,
                settingsLoaded: !!window.paxSupportPro,
                accessControlReady: !!this.accessControl && this.accessControl.loaded,
                eventBindingsReady: !!this.eventBindings
            };
            
            // CSS visibility tests
            if (chatWindow) {
                const styles = window.getComputedStyle(chatWindow);
                tests.chatWindowHidden = styles.display === 'none';
                tests.chatWindowHasTransition = styles.transition.includes('transform') || styles.transition.includes('opacity');
                
                console.log('Chat Window CSS:', {
                    display: styles.display,
                    opacity: styles.opacity,
                    visibility: styles.visibility,
                    transform: styles.transform,
                    pointerEvents: styles.pointerEvents,
                    zIndex: styles.zIndex
                });
            }
            
            // Event binding tests
            if (launcher && this.eventBindings) {
                tests.launcherHasClickHandler = this.eventBindings.bindings?.launcher?.bound === true;
            }
            
            // Access control tests
            if (this.accessControl) {
                const chatAccess = this.accessControl.canAccessChat();
                tests.chatAccessible = chatAccess.allowed;
                console.log('Chat Access:', chatAccess);
            }
            
            // Display results
            console.table(tests);
            
            const allPassed = Object.entries(tests).every(([key, value]) => {
                // chatWindowHidden should be true (hidden by default)
                if (key === 'chatWindowHidden') return value === true;
                // All other tests should be true
                return value === true;
            });
            
            if (allPassed) {
                console.log('%c✅ PAX Chat System Ready - All Tests Passed', 'background: #4caf50; color: #fff; padding: 4px 8px; border-radius: 4px; font-weight: bold;');
            } else {
                console.warn('%c⚠️ PAX Chat System: Some Tests Failed', 'background: #ff9800; color: #fff; padding: 4px 8px; border-radius: 4px; font-weight: bold;');
                console.warn('Failed tests:', Object.entries(tests).filter(([k, v]) => {
                    if (k === 'chatWindowHidden') return v !== true;
                    return v !== true;
                }));
            }
            
            console.groupEnd();
            
            return allPassed;
        }

        setupMenusWithAccessControl() {
            console.log('PAX-ACCESS: Setting up menus with access control');
            
            // Remove old menus if they exist
            const oldChatMenu = document.getElementById('pax-menu-dropdown');
            const oldHeaderMenu = document.getElementById('pax-header-menu-dropdown');
            if (oldChatMenu) oldChatMenu.remove();
            if (oldHeaderMenu) oldHeaderMenu.remove();
            
            // Setup menus with access control
            this.setupChatMenuWithAccessControl();
            this.setupHeaderMenuWithAccessControl();
        }

        setupChatMenuWithAccessControl() {
            const menuBtn = document.getElementById('pax-head-more');
            if (!menuBtn) {
                console.warn('PAX-ACCESS: Chat menu button (⋮) not found');
                return;
            }

            console.log('PAX-ACCESS: Setting up chat menu with access control');

            // Create menu dropdown
            const menuDropdown = document.createElement('div');
            menuDropdown.className = 'pax-menu-dropdown';
            menuDropdown.id = 'pax-menu-dropdown';
            menuDropdown.style.display = 'none';

            let menuHTML = '';
            let itemCount = 0;
            
            // Check if menu is enabled
            if (!this.accessControl.state.menuEnabled) {
                console.log('PAX-ACCESS: Chat menu disabled by admin');
                menuHTML = '<div class="pax-menu-disabled">Menu is currently disabled</div>';
            } else if (!this.accessControl.state.chatEnabled) {
                console.log('PAX-ACCESS: Chat disabled, showing message');
                menuHTML = '<div class="pax-menu-disabled">Chat is currently disabled</div>';
            } else if (this.accessControl.state.requiresLogin && !this.accessControl.state.isLoggedIn) {
                console.log('PAX-ACCESS: Login required, showing message');
                menuHTML = '<div class="pax-menu-disabled">Please log in to access chat</div>';
            } else {
                // Build menu items with access control
                const menuItems = window.paxSupportPro?.menuItems || {};
                const menuIcons = window.paxSupportPro?.menuIcons || {};
                
                for (const [key, item] of Object.entries(menuItems)) {
                    const access = this.accessControl.canAccessMenuItem(key);
                    
                    if (!access.allowed) {
                        console.log(`PAX-ACCESS: Skipping menu item '${key}' - ${access.reason}`);
                        continue;
                    }
                    
                    const icon = menuIcons[key] || (item.url ? 'dashicons-external' : 'dashicons-admin-generic');
                    const label = item.label || key;

                    if (item.url) {
                        menuHTML += `
                            <a href="${item.url}" target="_blank" rel="noopener noreferrer" class="pax-menu-item pax-custom-menu-link">
                                <span class="dashicons ${icon}"></span>
                                <span>${label}</span>
                            </a>
                        `;
                    } else {
                        menuHTML += `
                            <button class="pax-menu-item" data-action="${key}">
                                <span class="dashicons ${icon}"></span>
                                <span>${label}</span>
                            </button>
                        `;
                    }
                    itemCount++;
                }
                

            }

            console.log(`PAX-ACCESS: Created ${itemCount} menu items (including custom menus)`);

            menuDropdown.innerHTML = menuHTML;
            this.chatWindow.appendChild(menuDropdown);

            // Handle menu item clicks with access control
            menuDropdown.addEventListener('click', (e) => {
                const item = e.target.closest('.pax-menu-item');
                if (!item) return;

                const action = item.dataset.action;
                const itemLabel = item.querySelector('span:last-child')?.textContent || action;
                console.log('PAX-CLICK: Menu item clicked:', {
                    action: action,
                    label: itemLabel,
                    timestamp: new Date().toISOString()
                });
                
                menuDropdown.style.display = 'none';
                this.handleMenuActionWithAccessControl(action);
            });

            // Close menu on outside click
            document.addEventListener('click', (e) => {
                if (!menuDropdown.contains(e.target) && e.target !== menuBtn) {
                    menuDropdown.style.display = 'none';
                }
            });

            console.log('PAX-ACCESS: Chat menu setup complete');
        }

        setupHeaderMenuWithAccessControl() {
            const headerBtn = document.getElementById('pax-menu-btn');
            if (!headerBtn) {
                console.warn('PAX-ACCESS: Header menu button (☰) not found');
                return;
            }

            console.log('PAX-ACCESS: Setting up header menu');

            // Create header menu dropdown
            const headerDropdown = document.createElement('div');
            headerDropdown.className = 'pax-header-menu-dropdown';
            headerDropdown.id = 'pax-header-menu-dropdown';
            headerDropdown.style.display = 'none';

            headerDropdown.innerHTML = `
                <button class="pax-header-item" data-action="settings">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <span>Settings</span>
                </button>
                <button class="pax-header-item" data-action="clear">
                    <span class="dashicons dashicons-trash"></span>
                    <span>Clear Chat</span>
                </button>
                <button class="pax-header-item" data-action="about">
                    <span class="dashicons dashicons-info"></span>
                    <span>About</span>
                </button>
                <button class="pax-header-item" data-action="system">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <span>System Info</span>
                </button>
            `;

            this.chatWindow.appendChild(headerDropdown);

            // Handle header menu clicks
            headerDropdown.addEventListener('click', (e) => {
                const item = e.target.closest('.pax-header-item');
                if (!item) return;

                const action = item.dataset.action;
                console.log('PAX-CLICK: Header action triggered:', action);
                headerDropdown.style.display = 'none';

                switch (action) {
                    case 'settings':
                        this.showSettings();
                        break;
                    case 'clear':
                        this.clearChat();
                        break;
                    case 'about':
                        this.showAbout();
                        break;
                    case 'system':
                        this.showSystemInfo();
                        break;
                }
            });

            // Close menu on outside click
            document.addEventListener('click', (e) => {
                if (!headerDropdown.contains(e.target) && e.target !== headerBtn) {
                    headerDropdown.style.display = 'none';
                }
            });

            console.log('PAX-ACCESS: Header menu setup complete');
        }

        toggleChatMenu() {
            const menuDropdown = document.getElementById('pax-menu-dropdown');
            if (!menuDropdown) return;
            
            this.closeHeaderMenu();
            const isVisible = menuDropdown.style.display !== 'none';
            menuDropdown.style.display = isVisible ? 'none' : 'block';
            console.log('PAX-CLICK: Chat menu display:', menuDropdown.style.display);
        }

        toggleHeaderMenu() {
            const headerDropdown = document.getElementById('pax-header-menu-dropdown');
            if (!headerDropdown) return;
            
            this.closeChatMenu();
            const isVisible = headerDropdown.style.display !== 'none';
            headerDropdown.style.display = isVisible ? 'none' : 'block';
            console.log('PAX-CLICK: Header menu display:', headerDropdown.style.display);
        }

        closeChatMenu() {
            const menu = document.getElementById('pax-menu-dropdown');
            if (menu) menu.style.display = 'none';
        }

        closeHeaderMenu() {
            const menu = document.getElementById('pax-header-menu-dropdown');
            if (menu) menu.style.display = 'none';
        }

        setupToggleButton() {
            // v5.4.3: Unified launcher integrated into core system
            const launcher = document.getElementById('pax-unified-launcher');
            if (!launcher) {
                console.warn('PAX Unified Chat: Launcher not found');
                return;
            }

            // Apply settings-based positioning
            this.applyLauncherSettings(launcher);

            // Main launcher click handler
            launcher.addEventListener('click', () => {
                this.toggleChat();
            });

            // Close button
            const closeBtn = document.getElementById('pax-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    this.closeChat();
                });
            }

            // Overlay exists but no click handler (chat only closes via X button)
            const overlay = document.getElementById('pax-chat-overlay');
            if (overlay) {
                console.log('PAX-INIT: Overlay element found (no click-to-close)');
            }

            console.log('PAX Unified Launcher initialized');
        }

        applyLauncherSettings(launcher) {
            if (!launcher) return;

            // Get position from data attribute
            const position = launcher.dataset.position || 'bottom-right';
            
            // Apply position class
            launcher.classList.add(`position-${position}`);

            // Apply color scheme from settings if available
            if (window.paxSupportPro && window.paxSupportPro.options) {
                const opts = window.paxSupportPro.options;
                
                // Apply custom colors via CSS variables
                if (opts.color_accent) {
                    launcher.style.setProperty('--launcher-bg', opts.color_accent);
                }
            }
        }

        toggleChat() {
            console.log('PAX-TOGGLE: Triggered');
            if (!this.chatWindow) return;
            if (this.chatWindow.classList.contains('open')) {
                console.log('PAX-CLOSE: Closing chat window');
                this.chatWindow.classList.remove('open');
                this.isOpen = false;
            } else {
                console.log('PAX-OPEN: Opening chat window');
                this.chatWindow.classList.add('open');
                this.isOpen = true;
            }
        }

        openChat() {
            try {
                console.log('PAX-OPEN: openChat() called');
                paxDebugLog('CHAT', 'openChat() called');
                
                if (!this.chatWindow) {
                    console.error('PAX-OPEN: chatWindow is null!');
                    paxErrorLog('CHAT', new Error('chatWindow is null'), {
                        method: 'openChat'
                    });
                    return;
                }
                
                console.log('PAX-OPEN: Adding .open class to chatWindow', this.chatWindow);
                this.chatWindow.classList.add('open');
                console.log('PAX-OPEN: chatWindow.classList after add:', Array.from(this.chatWindow.classList));
                paxDebugLog('CHAT', 'Added open class to chatWindow');
                
                // CRITICAL FIX: Force inline styles to ensure visibility
                // This overrides any CSS that might be preventing display
                console.log('PAX-OPEN: Applying forced inline styles');
                this.chatWindow.style.display = 'flex';
                this.chatWindow.style.opacity = '1';
                this.chatWindow.style.visibility = 'visible';
                this.chatWindow.style.pointerEvents = 'all';
                this.chatWindow.style.transform = this.chatWindow.classList.contains('modal-mode') 
                    ? 'translate(-50%, -50%) scale(1)' 
                    : 'scale(1) translateY(0)';
                
                // Force display check
                const computedStyle = window.getComputedStyle(this.chatWindow);
                console.log('PAX-OPEN: Computed styles after forcing inline styles:', {
                    display: computedStyle.display,
                    opacity: computedStyle.opacity,
                    visibility: computedStyle.visibility,
                    transform: computedStyle.transform,
                    pointerEvents: computedStyle.pointerEvents,
                    zIndex: computedStyle.zIndex
                });
                
                // Verify it's actually visible
                const isVisible = computedStyle.display !== 'none' && 
                                 computedStyle.visibility !== 'hidden' && 
                                 parseFloat(computedStyle.opacity) > 0;
                console.log('PAX-OPEN: Chat window is now visible:', isVisible ? '✅ YES' : '❌ NO');
                
                if (!isVisible) {
                    console.error('PAX-OPEN: CRITICAL - Chat window still not visible after forcing styles!');
                    console.error('PAX-OPEN: This indicates a severe CSS conflict or browser issue');
                }
                
                const overlay = document.getElementById('pax-chat-overlay');
                if (overlay) {
                    overlay.classList.add('open');
                    // Force overlay visibility too
                    overlay.style.display = 'block';
                    overlay.style.opacity = '1';
                    console.log('PAX-OPEN: Added .open class to overlay and forced visibility');
                    paxDebugLog('CHAT', 'Added open class to overlay');
                } else {
                    console.warn('PAX-OPEN: Overlay element not found');
                    paxDebugLog('CHAT', 'Overlay element not found');
                }

                // v5.4.4: Prevent body scroll on mobile
                if (window.innerWidth <= 768) {
                    document.body.classList.add('pax-chat-open');
                    paxDebugLog('CHAT', 'Added pax-chat-open to body (mobile)');
                }

                // Focus input
                if (this.inputField) {
                    setTimeout(() => {
                        this.inputField.focus();
                        paxDebugLog('CHAT', 'Input field focused');
                    }, 300);
                } else {
                    paxDebugLog('CHAT', 'Input field not found');
                }

                // Start polling if in liveagent mode
                if (this.currentMode === 'liveagent') {
                    this.startPolling();
                    paxDebugLog('CHAT', 'Started polling (liveagent mode)');
                }
                
                paxDebugLog('CHAT', 'openChat() completed successfully');
                
            } catch (error) {
                paxErrorLog('CHAT', error, { method: 'openChat' });
            }
        }

        closeChat() {
            console.log('PAX-CLOSE: Closing chat window');
            this.chatWindow.classList.remove('open');
            
            // Remove forced inline styles
            this.chatWindow.style.display = '';
            this.chatWindow.style.opacity = '';
            this.chatWindow.style.visibility = '';
            this.chatWindow.style.pointerEvents = '';
            this.chatWindow.style.transform = '';
            
            const overlay = document.getElementById('pax-chat-overlay');
            if (overlay) {
                overlay.classList.remove('open');
                // Remove forced overlay styles
                overlay.style.display = '';
                overlay.style.opacity = '';
            }

            // v5.4.4: Restore body scroll on mobile
            document.body.classList.remove('pax-chat-open');

            // Close any open menus
            this.closeChatMenu();
            this.closeHeaderMenu();

            // Stop polling when chat is closed
            if (this.currentMode === 'liveagent') {
                this.stopPolling();
            }
        }

        setupModeSwitcher() {
            const header = this.chatWindow.querySelector('.pax-header');
            if (!header) return;

            // Create mode switcher tabs
            const switcher = document.createElement('div');
            switcher.className = 'pax-mode-switcher';
            switcher.innerHTML = `
                <button class="pax-mode-tab ${this.currentMode === 'assistant' ? 'active' : ''}" data-mode="assistant">
                    <span class="dashicons dashicons-format-chat"></span>
                    <span class="pax-mode-label">Assistant</span>
                </button>
                <button class="pax-mode-tab ${this.currentMode === 'liveagent' ? 'active' : ''}" data-mode="liveagent">
                    <span class="dashicons dashicons-businessman"></span>
                    <span class="pax-mode-label">Live Agent</span>
                    <span class="pax-unread-badge" style="display: none;">0</span>
                </button>
            `;

            // Insert after header title
            const titleDiv = header.querySelector('div');
            if (titleDiv) {
                titleDiv.after(switcher);
            } else {
                header.appendChild(switcher);
            }

            this.modeSwitcher = switcher;

            // Add click handlers
            switcher.querySelectorAll('.pax-mode-tab').forEach(tab => {
                tab.addEventListener('click', (e) => {
                    e.preventDefault();
                    const mode = tab.dataset.mode;
                    if (mode !== this.currentMode) {
                        this.switchMode(mode);
                    }
                });
            });
        }

        setupEventListeners() {
            // Send button
            if (this.sendButton) {
                this.sendButton.addEventListener('click', () => this.handleSend());
            }

            // Input field - Enter key
            if (this.inputField) {
                this.inputField.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.handleSend();
                    }
                });
            }

            // Reply-to close buttons (delegated)
            this.messageContainer.addEventListener('click', (e) => {
                if (e.target.closest('.pax-reply-close')) {
                    this.clearReplyTo();
                }
                
                // Reply-to message click
                if (e.target.closest('.pax-reply-to-msg')) {
                    const msgId = e.target.closest('.pax-reply-to-msg').dataset.replyTo;
                    this.scrollToMessage(msgId);
                }

                // Set reply-to
                if (e.target.closest('.pax-msg-reply-btn')) {
                    const msgElement = e.target.closest('.pax-message');
                    if (msgElement) {
                        const msgId = msgElement.dataset.messageId;
                        const msgText = msgElement.querySelector('.pax-msg-text')?.textContent || '';
                        const msgSender = msgElement.classList.contains('pax-msg-user') ? 'user' : 'assistant';
                        this.setReplyTo(msgId, msgText, msgSender);
                    }
                }
            });

            // Window unload - save state
            window.addEventListener('beforeunload', () => this.saveState());
        }

        setupChatMenu() {
            // v5.5.2: Use pax-head-more (⋮) for unified chat menu
            const menuBtn = document.getElementById('pax-head-more');
            if (!menuBtn) {
                console.warn('PAX: Chat menu button (⋮) not found');
                return;
            }

            console.log('PAX-CLICK: Setting up unified chat menu on ⋮ button');
            console.log('PAX-CLICK: window.paxSupportPro available:', !!window.paxSupportPro);

            // Create menu dropdown
            const menuDropdown = document.createElement('div');
            menuDropdown.className = 'pax-menu-dropdown';
            menuDropdown.id = 'pax-menu-dropdown';
            menuDropdown.style.display = 'none';

            // Get menu items from settings
            const menuItems = window.paxSupportPro?.menuItems || {};
            const menuIcons = window.paxSupportPro?.menuIcons || {};
            const isLoggedIn = window.paxSupportPro?.isLoggedIn || false;
            const options = window.paxSupportPro?.options || {};

            console.log('PAX-CLICK: Menu items from settings:', menuItems);
            console.log('PAX-CLICK: User logged in:', isLoggedIn);
            console.log('PAX-CLICK: Chat access control:', options.chat_access_control);

            let menuHTML = '';
            let itemCount = 0;
            
            // v5.5.2: Check global chat access control
            const chatAccess = options.chat_access_control || 'everyone';
            if (chatAccess === 'disabled') {
                console.log('PAX-CLICK: Chat access disabled globally');
                menuHTML = '<div class="pax-menu-disabled">Chat is currently disabled</div>';
            } else if (chatAccess === 'logged_in' && !isLoggedIn) {
                console.log('PAX-CLICK: Chat requires login, user not logged in');
                menuHTML = '<div class="pax-menu-disabled">Please log in to access chat</div>';
            } else {
                // Build menu items
                for (const [key, item] of Object.entries(menuItems)) {
                    // v5.5.2: Respect visibility setting from admin
                    if (!item.visible) {
                        console.log(`PAX-CLICK: Skipping menu item '${key}' - not visible in settings`);
                        continue;
                    }
                    
                    // v5.5.2: Check item-specific access control
                    if (item.requiresLogin && !isLoggedIn) {
                        console.log(`PAX-CLICK: Skipping menu item '${key}' - requires login`);
                        continue;
                    }
                    
                    const icon = menuIcons[key] || 'dashicons-admin-generic';
                    const label = item.label || key;

                    menuHTML += `
                        <button class="pax-menu-item" data-action="${key}">
                            <span class="dashicons ${icon}"></span>
                            <span>${label}</span>
                        </button>
                    `;
                    itemCount++;
                }
            }

            console.log(`PAX-CLICK: Created ${itemCount} menu items`);

            menuDropdown.innerHTML = menuHTML;
            this.chatWindow.appendChild(menuDropdown);

            // Toggle menu with access control
            menuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                console.log('PAX-CLICK: Chat menu button (⋮) clicked');
                console.log('PAX-CLICK: Current state:', {
                    chatAccess: chatAccess,
                    isLoggedIn: isLoggedIn,
                    settingsLoaded: window.paxSupportPro?.settingsLoaded
                });
                
                // v5.5.3: Check access control before opening menu
                if (chatAccess === 'disabled') {
                    console.log('PAX-CLICK: Chat disabled by admin');
                    this.showToast('Chat is currently disabled by administrator');
                    return;
                }
                
                if (chatAccess === 'logged_in' && !isLoggedIn) {
                    console.log('PAX-CLICK: Login required');
                    this.showToast('Please log in to access chat features');
                    return;
                }
                
                this.closeHeaderMenu(); // Close header menu
                const isVisible = menuDropdown.style.display !== 'none';
                menuDropdown.style.display = isVisible ? 'none' : 'block';
                console.log('PAX-CLICK: Chat menu display:', menuDropdown.style.display);
            });

            // Close menu on outside click
            document.addEventListener('click', (e) => {
                if (!menuDropdown.contains(e.target) && e.target !== menuBtn) {
                    menuDropdown.style.display = 'none';
                }
            });

            // Handle menu item clicks
            menuDropdown.addEventListener('click', (e) => {
                console.log('PAX-CLICK: Menu dropdown clicked', e.target);
                const item = e.target.closest('.pax-menu-item');
                if (!item) {
                    console.log('PAX-CLICK: Not a menu item, ignoring click');
                    return;
                }

                const action = item.dataset.action;
                const itemLabel = item.querySelector('span:last-child')?.textContent || action;
                console.log('PAX-CLICK: Menu item clicked:', {
                    action: action,
                    label: itemLabel,
                    timestamp: new Date().toISOString()
                });
                menuDropdown.style.display = 'none';

                this.handleMenuAction(action);
            });

            console.log('PAX-CLICK: Chat menu setup complete with', itemCount, 'items');
        }

        setupQuickActions() {
            // v5.5.2: Use pax-menu-btn (☰) for header menu (Settings, Clear, About, System Info)
            const headerBtn = document.getElementById('pax-menu-btn');
            if (!headerBtn) {
                console.warn('PAX-CLICK: Header menu button (☰) not found');
                return;
            }

            console.log('PAX-CLICK: Setting up header menu on ☰ button');

            // Create header menu dropdown
            const headerDropdown = document.createElement('div');
            headerDropdown.className = 'pax-header-menu-dropdown';
            headerDropdown.id = 'pax-header-menu-dropdown';
            headerDropdown.style.display = 'none';

            headerDropdown.innerHTML = `
                <button class="pax-header-item" data-action="settings">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <span>Settings</span>
                </button>
                <button class="pax-header-item" data-action="clear">
                    <span class="dashicons dashicons-trash"></span>
                    <span>Clear Chat</span>
                </button>
                <button class="pax-header-item" data-action="about">
                    <span class="dashicons dashicons-info"></span>
                    <span>About</span>
                </button>
                <button class="pax-header-item" data-action="system">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <span>System Info</span>
                </button>
            `;

            this.chatWindow.appendChild(headerDropdown);

            // Toggle menu
            headerBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                console.log('PAX-CLICK: Header menu button (☰) clicked');
                this.closeChatMenu(); // Close chat menu
                const isVisible = headerDropdown.style.display !== 'none';
                headerDropdown.style.display = isVisible ? 'none' : 'block';
                console.log('PAX-CLICK: Header menu display:', headerDropdown.style.display);
            });

            // Close menu on outside click
            document.addEventListener('click', (e) => {
                if (!headerDropdown.contains(e.target) && e.target !== headerBtn) {
                    headerDropdown.style.display = 'none';
                }
            });

            // Handle actions
            headerDropdown.addEventListener('click', (e) => {
                console.log('PAX-CLICK: Header dropdown clicked', e.target);
                const item = e.target.closest('.pax-header-item');
                if (!item) {
                    console.log('PAX-CLICK: Not a header item, ignoring click');
                    return;
                }

                const action = item.dataset.action;
                console.log('PAX-CLICK: Header action triggered:', action);
                headerDropdown.style.display = 'none';

                switch (action) {
                    case 'settings':
                        this.showSettings();
                        break;
                    case 'clear':
                        this.clearChat();
                        break;
                    case 'about':
                        this.showAbout();
                        break;
                    case 'system':
                        this.showSystemInfo();
                        break;
                }
            });
            
            console.log('PAX-CLICK: Header menu setup complete');
        }

        closeChatMenu() {
            const menu = document.getElementById('pax-menu-dropdown');
            if (menu) menu.style.display = 'none';
        }

        closeHeaderMenu() {
            const menu = document.getElementById('pax-header-menu-dropdown');
            if (menu) menu.style.display = 'none';
        }

        refreshMenuVisibility() {
            // v5.5.2: Refresh menu visibility based on current settings
            console.log('PAX-CLICK: Refreshing menu visibility');
            
            const menuDropdown = document.getElementById('pax-menu-dropdown');
            if (!menuDropdown) {
                console.log('PAX-CLICK: Menu dropdown not found, recreating');
                this.setupChatMenu();
                return;
            }

            const menuItems = window.paxSupportPro?.menuItems || {};
            const isLoggedIn = window.paxSupportPro?.isLoggedIn || false;
            const options = window.paxSupportPro?.options || {};
            const chatAccess = options.chat_access_control || 'everyone';

            // Check if menu should be completely disabled
            if (chatAccess === 'disabled' || (chatAccess === 'logged_in' && !isLoggedIn)) {
                console.log('PAX-CLICK: Chat access restricted, hiding menu');
                const menuBtn = document.getElementById('pax-head-more');
                if (menuBtn) menuBtn.style.display = 'none';
                return;
            }

            // Show menu button
            const menuBtn = document.getElementById('pax-head-more');
            if (menuBtn) menuBtn.style.display = '';

            // Update individual menu items
            const items = menuDropdown.querySelectorAll('.pax-menu-item');
            items.forEach(item => {
                const action = item.dataset.action;
                const menuItem = menuItems[action];
                
                if (!menuItem || !menuItem.visible) {
                    item.style.display = 'none';
                } else if (menuItem.requiresLogin && !isLoggedIn) {
                    item.style.display = 'none';
                } else {
                    item.style.display = '';
                }
            });

            console.log('PAX-CLICK: Menu visibility refreshed');
        }

        handleMenuActionWithAccessControl(action) {
            console.log('PAX-ACCESS: handleMenuAction called with action:', action);
            
            // Check access control
            const access = this.accessControl.canAccessMenuItem(action);
            if (!access.allowed) {
                console.log('PAX-BLOCK: Menu action blocked:', access.reason);
                this.showToast(access.reason);
                return;
            }
            
            console.log('PAX-ALLOW: Executing menu action:', action);
            
            // Handle specific actions
            switch (action) {
                case 'help':
                    console.log('PAX: Opening Help Center');
                    this.openHelpCenter();
                    break;
                case 'chat':
                    console.log('PAX: Focusing chat input');
                    // Already in chat, just focus input
                    if (this.inputField) {
                        this.inputField.focus();
                    }
                    break;
                case 'ticket':
                    console.log('PAX: Opening New Ticket');
                    this.openNewTicket();
                    break;
                case 'whatsnew':
                    console.log('PAX: Opening What\'s New');
                    this.openWhatsNew();
                    break;
                case 'troubleshooter':
                    console.log('PAX: Opening Troubleshooter');
                    this.openTroubleshooter();
                    break;
                case 'diag':
                    console.log('PAX: Opening Diagnostics');
                    this.openDiagnostics();
                    break;
                case 'callback':
                    console.log('PAX: Opening Callback');
                    this.openCallback();
                    break;
                case 'order':
                    console.log('PAX: Opening Order Lookup');
                    this.openOrderLookup();
                    break;
                case 'myreq':
                    console.log('PAX: Opening My Request');
                    this.openMyRequest();
                    break;
                case 'feedback':
                    console.log('PAX: Opening Feedback');
                    this.openFeedback();
                    break;
                case 'donate':
                    console.log('PAX: Opening Donate');
                    this.openDonate();
                    break;
                case 'speed':
                    console.log('PAX: Toggling Speed');
                    this.toggleSpeed();
                    break;
                default:
                    console.log('PAX: Unknown action, dispatching event:', action);
                    // Trigger existing menu handler if available
                    const event = new CustomEvent('pax-menu-action', { detail: { action } });
                    document.dispatchEvent(event);
                    break;
            }
        }

        openNewTicket() {
            console.log('PAX-ACCESS: openNewTicket called');
            
            // Check access via access control system
            const access = this.accessControl.canAccessFeature('tickets');
            if (!access.allowed) {
                console.log('PAX-BLOCK: Ticket creation blocked:', access.reason);
                this.showToast(access.reason);
                return;
            }

            console.log('PAX-ALLOW: Opening ticket modal');
            
            // Trigger existing ticket modal if available
            const event = new CustomEvent('pax-open-ticket-modal');
            document.dispatchEvent(event);
            
            // Also try direct call as fallback
            if (window.paxDebug && typeof window.paxDebug.openTicketModal === 'function') {
                window.paxDebug.openTicketModal();
            }
        }

        openWhatsNew() {
            const url = window.paxSupportPro?.links?.whatsNew;
            if (url) {
                window.open(url, '_blank', 'noopener,noreferrer');
            } else {
                this.showToast('Coming soon!');
            }
        }

        openTroubleshooter() {
            console.log('PAX-ACCESS: openTroubleshooter called');
            
            const access = this.accessControl.canAccessFeature('troubleshooter');
            if (!access.allowed) {
                console.log('PAX-BLOCK: Troubleshooter blocked:', access.reason);
                this.showToast(access.reason);
                return;
            }

            console.log('PAX-ALLOW: Opening troubleshooter');
            
            const event = new CustomEvent('pax-open-troubleshooter');
            document.dispatchEvent(event);
            
            // Direct fallback
            if (window.paxDebug && window.paxDebug.troubleModal && window.paxDebug.troubleModal.open) {
                window.paxDebug.troubleModal.open();
            }
        }

        openDiagnostics() {
            console.log('PAX-ACCESS: openDiagnostics called');
            
            const access = this.accessControl.canAccessFeature('diagnostics');
            if (!access.allowed) {
                console.log('PAX-BLOCK: Diagnostics blocked:', access.reason);
                this.showToast(access.reason);
                return;
            }

            console.log('PAX-ALLOW: Opening diagnostics');
            
            const event = new CustomEvent('pax-open-diagnostics');
            document.dispatchEvent(event);
            
            // Direct fallback
            if (window.paxDebug && typeof window.paxDebug.openDiagnosticsModal === 'function') {
                window.paxDebug.openDiagnosticsModal();
            }
        }

        openCallback() {
            console.log('PAX-ACCESS: openCallback called');
            
            const access = this.accessControl.canAccessFeature('callback');
            if (!access.allowed) {
                console.log('PAX-BLOCK: Callback blocked:', access.reason);
                this.showToast(access.reason);
                return;
            }

            console.log('PAX-ALLOW: Opening callback modal');
            
            const event = new CustomEvent('pax-open-schedule-modal');
            document.dispatchEvent(event);
            
            // Direct fallback
            if (window.paxDebug && typeof window.paxDebug.openScheduleModal === 'function') {
                window.paxDebug.openScheduleModal();
            }
        }

        openOrderLookup() {
            console.log('PAX-ACCESS: openOrderLookup called');
            
            const access = this.accessControl.canAccessFeature('orderLookup');
            if (!access.allowed) {
                console.log('PAX-BLOCK: Order lookup blocked:', access.reason);
                this.showToast(access.reason);
                return;
            }

            console.log('PAX-ALLOW: Opening order lookup');
            
            const event = new CustomEvent('pax-open-order-modal');
            document.dispatchEvent(event);
            
            // Direct fallback
            if (window.paxDebug && window.paxDebug.orderModal && window.paxDebug.orderModal.open) {
                window.paxDebug.orderModal.open();
            }
        }

        openMyRequest() {
            console.log('PAX-ACCESS: openMyRequest called');
            
            const access = this.accessControl.canAccessFeature('myRequest');
            if (!access.allowed) {
                console.log('PAX-BLOCK: My Request blocked:', access.reason);
                this.showToast(access.reason);
                return;
            }

            console.log('PAX-ALLOW: Opening my request modal');
            
            const event = new CustomEvent('pax-open-myrequest');
            document.dispatchEvent(event);
            
            // Direct fallback
            if (window.paxDebug && typeof window.paxDebug.openMyRequestModal === 'function') {
                console.log('PAX: Calling openMyRequestModal() directly as fallback');
                window.paxDebug.openMyRequestModal();
            }
        }

        openFeedback() {
            console.log('PAX-ACCESS: openFeedback called');
            
            const access = this.accessControl.canAccessFeature('feedback');
            if (!access.allowed) {
                console.log('PAX-BLOCK: Feedback blocked:', access.reason);
                this.showToast(access.reason);
                return;
            }

            console.log('PAX-ALLOW: Opening feedback');
            
            const event = new CustomEvent('pax-open-feedback');
            document.dispatchEvent(event);
            
            // Direct fallback
            if (window.paxDebug && window.paxDebug.feedbackModal && window.paxDebug.feedbackModal.open) {
                window.paxDebug.feedbackModal.open();
            }
        }

        openDonate() {
            console.log('PAX-ACCESS: openDonate called');
            
            const access = this.accessControl.canAccessFeature('donate');
            if (!access.allowed) {
                console.log('PAX-BLOCK: Donate blocked:', access.reason);
                this.showToast(access.reason);
                return;
            }

            console.log('PAX-ALLOW: Opening donate link');
            
            const url = window.paxSupportPro?.links?.donate;
            if (url) {
                window.open(url, '_blank', 'noopener,noreferrer');
            } else {
                this.showToast('Thank you for your support!');
            }
        }

        toggleSpeed() {
            console.log('PAX-ACCESS: toggleSpeed called');
            
            const access = this.accessControl.canAccessFeature('speed');
            if (!access.allowed) {
                console.log('PAX-BLOCK: Speed toggle blocked:', access.reason);
                this.showToast(access.reason);
                return;
            }

            console.log('PAX-ALLOW: Toggling speed');
            
            // Trigger existing speed toggle
            const event = new CustomEvent('pax-toggle-speed');
            document.dispatchEvent(event);
        }

        showToast(message) {
            console.log('PAX: showToast called with message:', message);
            
            // Create toast notification
            let stack = document.getElementById('pax-toast-stack');
            if (!stack) {
                stack = document.createElement('div');
                stack.id = 'pax-toast-stack';
                stack.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999999;display:flex;flex-direction:column;gap:10px;';
                document.body.appendChild(stack);
            }

            const toast = document.createElement('div');
            toast.className = 'pax-toast';
            toast.textContent = message;
            toast.style.cssText = 'background:#ffffff;color:#212121;padding:12px 20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.08);border:1px solid #e0e0e0;font-size:14px;opacity:0;transform:translateY(10px);transition:all 0.3s ease;';
            
            stack.appendChild(toast);
            
            requestAnimationFrame(() => {
                toast.style.opacity = '1';
                toast.style.transform = 'translateY(0)';
            });

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(10px)';
                setTimeout(() => {
                    toast.remove();
                    if (stack.children.length === 0) {
                        stack.remove();
                    }
                }, 300);
            }, 3000);
        }

        showSettings() {
            // Placeholder for settings modal
            this.showToast('Settings feature coming soon!');
        }

        showAbout() {
            // v5.5.1: Show about information
            const version = window.paxSupportPro?.version || '5.5.1';
            const aboutHTML = `
                <div style="padding: 20px; max-width: 400px;">
                    <h3 style="margin-top: 0;">PAX Support Pro</h3>
                    <p><strong>Version:</strong> ${version}</p>
                    <p><strong>Description:</strong> Professional support ticket system with modern admin UI, real-time chat, and comprehensive callback management.</p>
                    <p><strong>Developer:</strong> Ahmad AlKhalaf</p>
                    <p><strong>Website:</strong> <a href="https://github.com/Black10998" target="_blank">GitHub</a></p>
                </div>
            `;
            
            // Create modal
            const modal = document.createElement('div');
            modal.className = 'pax-about-modal';
            modal.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); z-index: 2147483648; color: #333;';
            modal.innerHTML = aboutHTML;
            
            const overlay = document.createElement('div');
            overlay.className = 'pax-about-overlay';
            overlay.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.05); z-index: 2147483647;';
            
            document.body.appendChild(overlay);
            document.body.appendChild(modal);
            
            overlay.addEventListener('click', () => {
                modal.remove();
                overlay.remove();
            });
        }

        showSystemInfo() {
            // v5.5.1: Show system information
            const info = {
                'Browser': navigator.userAgent,
                'Platform': navigator.platform,
                'Language': navigator.language,
                'Online': navigator.onLine ? 'Yes' : 'No',
                'Cookies Enabled': navigator.cookieEnabled ? 'Yes' : 'No',
                'Screen Resolution': `${screen.width}x${screen.height}`,
                'Viewport': `${window.innerWidth}x${window.innerHeight}`,
                'WordPress Version': window.paxSupportPro?.wpVersion || 'Unknown',
                'Plugin Version': window.paxSupportPro?.version || '5.5.1'
            };
            
            let infoHTML = '<div style="padding: 20px; max-width: 500px;"><h3 style="margin-top: 0;">System Information</h3><table style="width: 100%; border-collapse: collapse;">';
            for (const [key, value] of Object.entries(info)) {
                infoHTML += `<tr style="border-bottom: 1px solid #eee;"><td style="padding: 8px; font-weight: bold;">${key}:</td><td style="padding: 8px; word-break: break-all;">${value}</td></tr>`;
            }
            infoHTML += '</table></div>';
            
            // Create modal
            const modal = document.createElement('div');
            modal.className = 'pax-system-modal';
            modal.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); z-index: 2147483648; color: #333; max-height: 80vh; overflow-y: auto;';
            modal.innerHTML = infoHTML;
            
            const overlay = document.createElement('div');
            overlay.className = 'pax-system-overlay';
            overlay.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.05); z-index: 2147483647;';
            
            document.body.appendChild(overlay);
            document.body.appendChild(modal);
            
            overlay.addEventListener('click', () => {
                modal.remove();
                overlay.remove();
            });
        }

        openHelpCenter() {
            console.log('PAX: openHelpCenter called');
            
            // Close any open menus
            this.closeChatMenu();
            this.closeHeaderMenu();

            // Check if popup already exists
            let popup = document.getElementById('pax-help-center-popup');
            if (popup) {
                console.log('PAX: Help Center popup already exists, opening it');
                popup.classList.add('open');
                this.lockBodyScroll();
                return;
            }

            console.log('PAX: Creating new Help Center popup');

            // Create popup structure
            popup = document.createElement('div');
            popup.id = 'pax-help-center-popup';
            popup.className = 'pax-help-center-popup';
            popup.innerHTML = `
                <div class="pax-help-overlay"></div>
                <div class="pax-help-modal">
                    <div class="pax-help-header">
                        <h2 class="pax-help-title">
                            <span class="dashicons dashicons-editor-help"></span>
                            Help Center
                        </h2>
                        <button class="pax-help-close" type="button" title="Close">
                            <svg viewBox="0 0 24 24"><path d="M6.7 5.3 5.3 6.7 10.6 12l-5.3 5.3 1.4 1.4L12 13.4l5.3 5.3 1.4-1.4L13.4 12l5.3-5.3-1.4-1.4L12 10.6z"/></svg>
                        </button>
                    </div>
                    <div class="pax-help-search">
                        <span class="dashicons dashicons-search"></span>
                        <input type="text" id="pax-help-search-input" placeholder="Search help articles..." />
                    </div>
                    <div class="pax-help-content">
                        <div class="pax-help-loading">
                            <div class="pax-spinner"></div>
                            <p>Loading help articles...</p>
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(popup);

            // Setup event listeners
            this.setupHelpCenterListeners(popup);

            // Load help articles
            this.loadHelpArticles();

            // Show popup with animation
            setTimeout(() => {
                popup.classList.add('open');
                this.lockBodyScroll();
            }, 10);
        }

        setupHelpCenterListeners(popup) {
            // Close button
            const closeBtn = popup.querySelector('.pax-help-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => this.closeHelpCenter());
            }

            // Overlay click
            const overlay = popup.querySelector('.pax-help-overlay');
            if (overlay) {
                overlay.addEventListener('click', () => this.closeHelpCenter());
            }

            // ESC key
            const escHandler = (e) => {
                if (e.key === 'Escape' && popup.classList.contains('open')) {
                    this.closeHelpCenter();
                }
            };
            document.addEventListener('keydown', escHandler);
            popup.dataset.escHandler = 'attached';

            // Search input
            const searchInput = popup.querySelector('#pax-help-search-input');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        this.loadHelpArticles(e.target.value);
                    }, 500);
                });
            }
        }

        async loadHelpArticles(query = '') {
            const popup = document.getElementById('pax-help-center-popup');
            if (!popup) return;

            const contentDiv = popup.querySelector('.pax-help-content');
            if (!contentDiv) return;

            // Show loading
            contentDiv.innerHTML = `
                <div class="pax-help-loading">
                    <div class="pax-spinner"></div>
                    <p>Loading help articles...</p>
                </div>
            `;

            try {
                const url = new URL(window.paxSupportPro.rest.help);
                if (query) {
                    url.searchParams.append('q', query);
                }
                url.searchParams.append('lang', window.paxSupportPro.locale);

                const response = await fetch(url, {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': window.paxSupportPro.nonce
                    }
                });

                const data = await response.json();

                if (data.articles && data.articles.length > 0) {
                    this.renderHelpArticles(data.articles);
                } else {
                    contentDiv.innerHTML = `
                        <div class="pax-help-empty">
                            <span class="dashicons dashicons-info"></span>
                            <p>No help articles found. Try a different search term.</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading help articles:', error);
                contentDiv.innerHTML = `
                    <div class="pax-help-error">
                        <span class="dashicons dashicons-warning"></span>
                        <p>Failed to load help articles. Please try again.</p>
                    </div>
                `;
            }
        }

        renderHelpArticles(articles) {
            const popup = document.getElementById('pax-help-center-popup');
            if (!popup) return;

            const contentDiv = popup.querySelector('.pax-help-content');
            if (!contentDiv) return;

            // Get last open section from localStorage
            const lastOpen = localStorage.getItem('pax-help-last-open');

            let html = '<div class="pax-help-articles">';
            
            articles.forEach((article, index) => {
                const isOpen = lastOpen === `article-${index}`;
                html += `
                    <div class="pax-help-article ${isOpen ? 'open' : ''}" data-article-id="article-${index}">
                        <div class="pax-help-article-header">
                            <h3 class="pax-help-article-title">${this.escapeHtml(article.title)}</h3>
                            <button class="pax-help-article-toggle" type="button">
                                <svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                            </button>
                        </div>
                        <div class="pax-help-article-content">
                            <p class="pax-help-article-summary">${this.escapeHtml(article.summary)}</p>
                            ${article.url ? `<a href="${this.escapeHtml(article.url)}" class="pax-help-article-link" target="_blank" rel="noopener">Read full article →</a>` : ''}
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            contentDiv.innerHTML = html;

            // Setup accordion functionality
            this.setupHelpAccordion();
        }

        setupHelpAccordion() {
            const articles = document.querySelectorAll('.pax-help-article');
            
            articles.forEach(article => {
                const header = article.querySelector('.pax-help-article-header');
                const toggle = article.querySelector('.pax-help-article-toggle');
                
                const clickHandler = () => {
                    const isOpen = article.classList.contains('open');
                    
                    // Close all other articles
                    articles.forEach(a => a.classList.remove('open'));
                    
                    // Toggle current article
                    if (!isOpen) {
                        article.classList.add('open');
                        // Remember last open section
                        localStorage.setItem('pax-help-last-open', article.dataset.articleId);
                    } else {
                        localStorage.removeItem('pax-help-last-open');
                    }
                };

                header.addEventListener('click', clickHandler);
                toggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    clickHandler();
                });
            });
        }

        closeHelpCenter() {
            const popup = document.getElementById('pax-help-center-popup');
            if (!popup) return;

            popup.classList.remove('open');
            this.unlockBodyScroll();

            // Remove popup after animation
            setTimeout(() => {
                if (popup.parentNode) {
                    popup.parentNode.removeChild(popup);
                }
            }, 300);
        }

        lockBodyScroll() {
            if (window.innerWidth <= 768) {
                document.body.style.overflow = 'hidden';
                document.body.style.position = 'fixed';
                document.body.style.width = '100%';
            }
        }

        unlockBodyScroll() {
            document.body.style.overflow = '';
            document.body.style.position = '';
            document.body.style.width = '';
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        async switchMode(mode, saveState = true) {
            if (mode !== 'assistant' && mode !== 'liveagent') {
                console.error('Invalid mode:', mode);
                return;
            }

            // Save current state
            if (saveState) {
                this.saveState();
            }

            // Stop polling if switching away from liveagent
            if (this.currentMode === 'liveagent' && mode !== 'liveagent') {
                this.stopPolling();
            }

            const switchingToLive = mode === 'liveagent';
            if (switchingToLive) {
                if (typeof this.showLiveBanner === 'function' && !this.sessions.liveagent.sessionId) {
                    this.showLiveBanner('connecting');
                }
                await this.ensureLiveAgentSession();
            }

            // Update mode
            this.currentMode = mode;

            // Update UI
            this.updateModeUI();

            // Load messages for new mode
            this.renderMessages();

            // Update input placeholder
            if (this.inputField) {
                const assistantPlaceholder = window.paxSupportPro?.strings?.assistantPlaceholder || 'Ask me anything...';
                const liveagentPlaceholder = this.getLiveAgentString('typeHere', 'Type your message…');
                this.inputField.placeholder = mode === 'liveagent'
                    ? liveagentPlaceholder
                    : assistantPlaceholder;
            }

            // Start polling for liveagent
            // Clear unread badge for switched mode
            if (mode === 'liveagent') {
                this.sessions.liveagent.unreadCount = 0;
                this.updateUnreadBadge();

                if (this.sessions.liveagent.sessionId) {
                    this.removeLiveAgentOnboarding();
                    this.startPolling();
                    this.syncLiveAgentStatus();
                } else {
                    this.renderLiveAgentOnboarding();
                }
            } else {
                this.removeLiveAgentOnboarding();
                if (typeof this.hideLiveBanner === 'function') {
                    this.hideLiveBanner();
                }
            }

            // Save state
            if (saveState) {
                this.saveState();
            }

            console.log('Switched to', mode, 'mode');
        }

        updateModeUI() {
            // Update tab active state
            if (this.modeSwitcher) {
                this.modeSwitcher.querySelectorAll('.pax-mode-tab').forEach(tab => {
                    tab.classList.toggle('active', tab.dataset.mode === this.currentMode);
                });
            }

            // Update header subtitle
            const subtitle = this.chatWindow.querySelector('.pax-sub');
            if (subtitle) {
                subtitle.textContent = this.currentMode === 'liveagent' ? 'Live Agent' : 'Assistant';
            }

            // Update chat window class
            this.chatWindow.classList.toggle('mode-liveagent', this.currentMode === 'liveagent');
            this.chatWindow.classList.toggle('mode-assistant', this.currentMode === 'assistant');
        }

        renderLiveAgentOnboarding() {
            if (!this.messageContainer) {
                return;
            }

            if (this.messageContainer.querySelector('.pax-liveagent-onboarding')) {
                return;
            }

            const strings = window.paxSupportPro?.strings?.liveagent || {};
            const panel = document.createElement('div');
            panel.className = 'pax-liveagent-onboarding';
            panel.innerHTML = `
                <div class="pax-liveagent-onboarding__inner">
                    <div class="pax-liveagent-onboarding__content">
                        <h3>${this.getLiveAgentString('onboardingTitle', 'Need live support?')}</h3>
                        <p>${this.getLiveAgentString('onboardingSubtitle', 'Choose how you would like to get help:')}</p>
                    </div>
                    <div class="pax-liveagent-onboarding__actions">
                        <button type="button" class="pax-liveagent-btn pax-liveagent-btn--primary" data-action="start">
                            ${this.getLiveAgentString('onboardingStart', 'Start Live Chat')}
                        </button>
                        <button type="button" class="pax-liveagent-btn pax-liveagent-btn--secondary" data-action="assistant">
                            ${this.getLiveAgentString('onboardingAssistant', 'Ask Assistant first')}
                        </button>
                        <button type="button" class="pax-liveagent-btn pax-liveagent-btn--ghost" data-action="message">
                            ${this.getLiveAgentString('onboardingLeaveMessage', 'Leave a message')}
                        </button>
                    </div>
                </div>
            `;

            this.messageContainer.appendChild(panel);
            this.sessions.liveagent.onboardingVisible = true;

            const startBtn = panel.querySelector('[data-action="start"]');
            const assistantBtn = panel.querySelector('[data-action="assistant"]');
            const messageBtn = panel.querySelector('[data-action="message"]');

            if (startBtn) {
                startBtn.addEventListener('click', () => {
                    if (startBtn.disabled) {
                        return;
                    }
                    startBtn.disabled = true;
                    startBtn.classList.add('is-loading');
                    this.removeLiveAgentOnboarding();
                    if (typeof this.startLiveAgent === 'function') {
                        this.startLiveAgent();
                    } else {
                        this.ensureLiveAgentSession(true).then(() => {
                            this.startPolling();
                            this.syncLiveAgentStatus();
                        });
                    }
                });
            }

            if (assistantBtn) {
                assistantBtn.addEventListener('click', () => {
                    this.switchMode('assistant', true);
                });
            }

            if (messageBtn) {
                messageBtn.addEventListener('click', () => {
                    this.openNewTicket();
                    this.switchMode('assistant', true);
                });
            }
        }

        removeLiveAgentOnboarding() {
            if (!this.messageContainer) {
                return;
            }
            const panel = this.messageContainer.querySelector('.pax-liveagent-onboarding');
            if (panel && panel.parentNode) {
                panel.parentNode.removeChild(panel);
            }
            this.sessions.liveagent.onboardingVisible = false;
        }

        getLiveRestBase() {
            if (!this.liveRestBase) {
                const raw = this.liveConfig.restBase
                    || window.paxSupportPro?.rest?.base
                    || `${window.location.origin}/wp-json/pax/v1/`;
                this.liveRestBase = raw.replace(/\/?$/, '/');
            }
            return this.liveRestBase;
        }

        getLiveNonce() {
            return this.liveConfig.nonce || window.paxSupportPro?.nonce || '';
        }

        buildLiveUrl(path) {
            const base = this.getLiveRestBase();
            const sanitized = path.replace(/^\/+/, '');
            return `${base}${sanitized}`;
        }

        buildLiveHeaders(extra = {}) {
            const headers = Object.assign({}, extra);
            const nonce = this.getLiveNonce();
            if (nonce) {
                headers['X-WP-Nonce'] = nonce;
            }
            return headers;
        }

        syncLiveAgentStatus() {
            if (typeof this.hideLiveBanner === 'function' && this.currentMode !== 'liveagent') {
                this.hideLiveBanner();
                return;
            }

            if (typeof this.showLiveBanner !== 'function') {
                return;
            }

            const status = this.sessions.liveagent.status;
            if (status === 'accepted' || status === 'active') {
                this.showLiveBanner('connected');
            } else if (status === 'pending') {
                this.showLiveBanner('queue');
            } else if (status === 'connecting') {
                this.showLiveBanner('connecting');
            }
        }

        getLiveAgentString(key, fallback = '') {
            const liveStrings = (this.liveConfig && this.liveConfig.strings) ? this.liveConfig.strings : null;
            if (liveStrings && Object.prototype.hasOwnProperty.call(liveStrings, key)) {
                return liveStrings[key] || fallback;
            }
            return window.paxSupportPro?.strings?.liveagent?.[key] || fallback;
        }

        async handleSend() {
            if (!this.inputField || !this.inputField.value.trim()) {
                return;
            }

            const message = this.inputField.value.trim();
            const replyTo = this.replyToMessage;

            // Clear input and reply-to
            this.inputField.value = '';
            this.clearReplyTo();

            // Add user message to UI immediately
            const userMsg = {
                id: Date.now(),
                text: message,
                sender: 'user',
                timestamp: new Date().toISOString(),
                replyTo: replyTo ? replyTo.id : null
            };

            this.sessions[this.currentMode].messages.push(userMsg);
            this.renderMessage(userMsg);
            this.scrollToBottom();

            // Send to server
            try {
                if (this.currentMode === 'assistant') {
                    await this.sendAssistantMessage(message, replyTo);
                } else {
                    await this.sendLiveAgentMessage(message, replyTo);
                }
            } catch (error) {
                console.error('Error sending message:', error);
                this.showError('Failed to send message. Please try again.');
            }
        }

        async sendAssistantMessage(message, replyTo) {
            // Show typing indicator
            this.showTypingIndicator();

            try {
                const response = await fetch(window.paxSupportPro.rest.ai, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.paxSupportPro.nonce
                    },
                    body: JSON.stringify({
                        message: message,
                        replyTo: replyTo ? replyTo.id : null,
                        lang: window.paxSupportPro.locale
                    })
                });

                const data = await response.json();

                // Hide typing indicator
                this.hideTypingIndicator();

                if (data.reply) {
                    const assistantMsg = {
                        id: Date.now() + 1,
                        text: data.reply,
                        sender: 'assistant',
                        timestamp: new Date().toISOString(),
                        replyTo: null
                    };

                    this.sessions.assistant.messages.push(assistantMsg);
                    this.renderMessage(assistantMsg);
                    this.scrollToBottom();
                    this.saveState();
                }
            } catch (error) {
                // Hide typing indicator on error
                this.hideTypingIndicator();
                throw error;
            }
        }

        showTypingIndicator() {
            // Remove existing indicator if any
            this.hideTypingIndicator();

            const indicator = document.createElement('div');
            indicator.className = 'pax-typing-indicator';
            indicator.id = 'pax-typing-indicator';
            indicator.innerHTML = `
                <div class="pax-typing-dots">
                    <span class="pax-typing-dot"></span>
                    <span class="pax-typing-dot"></span>
                    <span class="pax-typing-dot"></span>
                </div>
            `;

            this.messageContainer.appendChild(indicator);
            this.scrollToBottom();
        }

        hideTypingIndicator() {
            const indicator = document.getElementById('pax-typing-indicator');
            if (indicator) {
                indicator.remove();
            }
        }

        async sendLiveAgentMessage(message, replyTo) {
            const sessionId = this.sessions.liveagent.sessionId;
            
            if (!sessionId) {
                throw new Error('No active Live Agent session');
            }

            const response = await fetch(this.buildLiveUrl('live/message'), {
                method: 'POST',
                headers: this.buildLiveHeaders({
                    'Content-Type': 'application/json'
                }),
                credentials: 'same-origin',
                cache: 'no-store',
                body: JSON.stringify({
                    session_id: sessionId,
                    content: message,
                    reply_to: replyTo ? replyTo.id : null
                })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Failed to send message');
            }

            // Message will be reflected in next poll
            this.saveState();
        }

        async ensureLiveAgentSession(force = false) {
            if (this.sessions.liveagent.sessionId && !force) {
                return;
            }

            try {
                const endpoint = this.buildLiveUrl('live/session');
                const currentUser = window.paxSupportPro?.currentUser || {};

                const response = await fetch(endpoint, {
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

                const data = await response.json();

                if (data.success && data.session) {
                    const sessionSummary = data.session;
                    this.sessions.liveagent.sessionId = sessionSummary.id || data.session_id || this.sessions.liveagent.sessionId;
                    this.sessions.liveagent.status = sessionSummary.status || data.status || 'pending';
                    this.sessions.liveagent.userName = sessionSummary.user_name || sessionSummary.userName || this.sessions.liveagent.userName;
                    this.sessions.liveagent.userEmail = sessionSummary.user_email || sessionSummary.userEmail || this.sessions.liveagent.userEmail;
                    this.sessions.liveagent.restBase = this.getLiveRestBase();
                    this.saveState();
                    console.log('Live Agent session created:', this.sessions.liveagent.sessionId);
                    if (typeof this.syncLiveAgentStatus === 'function') {
                        this.syncLiveAgentStatus();
                    }
                } else if (data.session_id) {
                    this.sessions.liveagent.sessionId = data.session_id;
                    this.sessions.liveagent.status = data.status || 'pending';
                    this.sessions.liveagent.restBase = this.getLiveRestBase();
                    this.saveState();
                    console.log('Live Agent session created:', data.session_id);
                    if (typeof this.syncLiveAgentStatus === 'function') {
                        this.syncLiveAgentStatus();
                    }
                }
            } catch (error) {
                console.error('Error creating Live Agent session:', error);
                if (typeof this.hideLiveBanner === 'function') {
                    this.hideLiveBanner();
                }
            }
        }

        startPolling() {
            if (this.isPolling) return;

            this.isPolling = true;
            this.pollInterval = setInterval(() => this.pollLiveAgentMessages(), 3000);
            
            // Initial poll
            this.pollLiveAgentMessages();
        }

        stopPolling() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
            this.isPolling = false;
        }

        async pollLiveAgentMessages() {
            if (!this.sessions.liveagent.sessionId) return;

            try {
                const url = this.buildLiveUrl(`live/messages?session_id=${encodeURIComponent(this.sessions.liveagent.sessionId)}`);
                const response = await fetch(url, {
                    headers: this.buildLiveHeaders(),
                    credentials: 'same-origin',
                    cache: 'no-store'
                });

                const data = await response.json();

                if (data.messages && Array.isArray(data.messages)) {
                    let hasNewMessages = false;

                    data.messages.forEach(msg => {
                        // Check if message already exists
                        const exists = this.sessions.liveagent.messages.some(m => m.id === msg.id);
                        
                        if (!exists) {
                            this.sessions.liveagent.messages.push(msg);
                            
                            // Only render if in liveagent mode
                            if (this.currentMode === 'liveagent') {
                                this.renderMessage(msg);
                                hasNewMessages = true;
                            } else {
                                // Increment unread count
                                this.sessions.liveagent.unreadCount++;
                                this.updateUnreadBadge();
                            }
                        }
                    });

                    if (hasNewMessages) {
                        this.scrollToBottom();
                        this.saveState();
                        this.removeLiveAgentOnboarding();
                    }
                }

                // Update status
                if (data.status) {
                    const previousStatus = this.sessions.liveagent.status;
                    this.sessions.liveagent.status = data.status;

                    if (typeof this.handleStatusChange === 'function' && previousStatus !== data.status) {
                        this.handleStatusChange(data.status, data);
                    } else if (previousStatus !== data.status) {
                        this.syncLiveAgentStatus();
                    }
                }

                if (typeof data.agent_typing !== 'undefined') {
                    this.sessions.liveagent.agentTyping = !!data.agent_typing;
                }

                // Update agent info
                if (data.agent) {
                    this.sessions.liveagent.agentInfo = data.agent;
                }

                if (this.sessions.liveagent.sessionId && this.currentMode === 'liveagent') {
                    this.removeLiveAgentOnboarding();
                    this.syncLiveAgentStatus();
                }
            } catch (error) {
                console.error('Error polling Live Agent messages:', error);
            }
        }

        updateUnreadBadge() {
            if (!this.modeSwitcher) return;

            const badge = this.modeSwitcher.querySelector('.pax-unread-badge');
            if (!badge) return;

            const count = this.sessions.liveagent.unreadCount;
            
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        }

        renderMessages() {
            if (!this.messageContainer) return;

            // Clear container
            this.messageContainer.innerHTML = '';

            // Render all messages for current mode
            const messages = this.sessions[this.currentMode].messages;
            messages.forEach(msg => this.renderMessage(msg));

            if (this.currentMode === 'liveagent') {
                if (!this.sessions.liveagent.sessionId || messages.length === 0) {
                    this.renderLiveAgentOnboarding();
                } else {
                    this.removeLiveAgentOnboarding();
                    this.syncLiveAgentStatus();
                }
            } else if (typeof this.hideLiveBanner === 'function') {
                this.hideLiveBanner();
            }

            this.scrollToBottom();
        }

        renderMessage(msg) {
            if (!this.messageContainer) return;

            const msgDiv = document.createElement('div');
            msgDiv.className = `pax-message pax-msg-${msg.sender}`;
            msgDiv.dataset.messageId = msg.id;

            let content = '';

            // Reply-to context bubble
            if (msg.replyTo) {
                const replyToMsg = this.sessions[this.currentMode].messages.find(m => m.id == msg.replyTo);
                if (replyToMsg) {
                    content += `
                        <div class="pax-reply-to-msg" data-reply-to="${replyToMsg.id}">
                            <span class="dashicons dashicons-undo"></span>
                            <div class="pax-reply-content">
                                <div class="pax-reply-sender">${replyToMsg.sender === 'user' ? 'You' : (replyToMsg.sender === 'agent' ? 'Agent' : 'Assistant')}</div>
                                <div class="pax-reply-text">${this.escapeHtml(replyToMsg.text.substring(0, 50))}${replyToMsg.text.length > 50 ? '...' : ''}</div>
                            </div>
                        </div>
                    `;
                }
            }

            // Message text
            content += `<div class="pax-msg-text">${this.escapeHtml(msg.text)}</div>`;

            // Timestamp
            const time = new Date(msg.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            content += `<div class="pax-msg-time">${time}</div>`;

            // Reply button (if reply-to enabled)
            if (window.paxSupportPro?.options?.enable_reply_to && msg.sender !== 'user') {
                content += `<button class="pax-msg-reply-btn" title="Reply to this message"><span class="dashicons dashicons-undo"></span></button>`;
            }

            msgDiv.innerHTML = content;
            this.messageContainer.appendChild(msgDiv);
        }

        setReplyTo(msgId, msgText, msgSender) {
            this.replyToMessage = { id: msgId, text: msgText, sender: msgSender };

            // Show reply-to indicator
            let indicator = this.chatWindow.querySelector('.pax-reply-indicator');
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.className = 'pax-reply-indicator';
                const inputArea = this.chatWindow.querySelector('.pax-input-area');
                if (inputArea) {
                    inputArea.before(indicator);
                }
            }

            indicator.innerHTML = `
                <div class="pax-reply-content">
                    <span class="dashicons dashicons-undo"></span>
                    <div>
                        <div class="pax-reply-to-label">Replying to ${msgSender === 'user' ? 'yourself' : msgSender}</div>
                        <div class="pax-reply-to-text">${this.escapeHtml(msgText.substring(0, 50))}${msgText.length > 50 ? '...' : ''}</div>
                    </div>
                </div>
                <button class="pax-reply-close"><span class="dashicons dashicons-no-alt"></span></button>
            `;
            indicator.style.display = 'flex';

            // Focus input
            if (this.inputField) {
                this.inputField.focus();
            }
        }

        clearReplyTo() {
            this.replyToMessage = null;
            const indicator = this.chatWindow.querySelector('.pax-reply-indicator');
            if (indicator) {
                indicator.style.display = 'none';
            }
        }

        scrollToMessage(msgId) {
            const msgElement = this.messageContainer.querySelector(`[data-message-id="${msgId}"]`);
            if (msgElement) {
                msgElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                msgElement.classList.add('pax-msg-highlight');
                setTimeout(() => msgElement.classList.remove('pax-msg-highlight'), 2000);
            }
        }

        scrollToBottom() {
            if (this.messageContainer) {
                this.messageContainer.scrollTop = this.messageContainer.scrollHeight;
            }
        }

        showWelcomeMessage() {
            if (!window.paxSupportPro?.options?.welcome_message) return;

            const welcomeMsg = {
                id: 'welcome-' + Date.now(),
                text: window.paxSupportPro.options.welcome_message,
                sender: 'assistant',
                timestamp: new Date().toISOString(),
                replyTo: null
            };

            this.sessions.assistant.messages.push(welcomeMsg);
            this.renderMessage(welcomeMsg);
        }

        showError(message) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'pax-error-message';
            errorDiv.textContent = message;
            this.messageContainer.appendChild(errorDiv);
            this.scrollToBottom();

            setTimeout(() => errorDiv.remove(), 5000);
        }

        // Quick Actions
        reloadConversation() {
            if (confirm('Reload conversation? This will refresh messages from the server.')) {
                this.sessions[this.currentMode].messages = [];
                this.renderMessages();
                
                if (this.currentMode === 'liveagent') {
                    this.pollLiveAgentMessages();
                }
            }
        }

        clearChat() {
            if (confirm('Clear all messages? This cannot be undone.')) {
                this.sessions[this.currentMode].messages = [];
                this.renderMessages();
                this.saveState();
                this.showWelcomeMessage();
            }
        }

        toggleAI() {
            // This would toggle AI assistant on/off
            console.log('Toggle AI - Not implemented yet');
        }

        // State Management
        saveState() {
            try {
                const state = {
                    currentMode: this.currentMode,
                    sessions: this.sessions,
                    timestamp: Date.now()
                };
                localStorage.setItem('pax_unified_chat_state', JSON.stringify(state));
            } catch (error) {
                console.error('Error saving state:', error);
            }
        }

        loadState() {
            try {
                const saved = localStorage.getItem('pax_unified_chat_state');
                if (!saved) return;

                const state = JSON.parse(saved);
                
                // Check if state is recent (within 24 hours)
                if (Date.now() - state.timestamp > 24 * 60 * 60 * 1000) {
                    localStorage.removeItem('pax_unified_chat_state');
                    return;
                }

                this.currentMode = state.currentMode || 'assistant';
                this.sessions = state.sessions || this.sessions;

                console.log('State loaded from localStorage');
            } catch (error) {
                console.error('Error loading state:', error);
            }
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Initialize when ready - DOM-ready initialization for full sync
    if (typeof window !== 'undefined') {
        window.PAXUnifiedChat = PAXUnifiedChat;
        
        // Wait for DOM to be fully ready before initializing
        document.addEventListener('DOMContentLoaded', () => {
            const paxChatInstance = new PAXUnifiedChat();
            window.paxUnifiedChat = paxChatInstance;
            console.log('PAX: Unified Chat initialized after DOM ready');
        });
    }
})();
