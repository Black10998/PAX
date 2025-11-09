/**
 * Theme Toggle - Dark/Light Mode
 * PAX Support Pro
 */

(function() {
    'use strict';

    const THEME_KEY = 'pax_console_theme';
    const THEME_LIGHT = 'light';
    const THEME_DARK = 'dark';

    /**
     * Initialize theme system
     */
    function init() {
        // Get saved theme or detect system preference
        const savedTheme = localStorage.getItem(THEME_KEY);
        const systemPrefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        const initialTheme = savedTheme || (systemPrefersDark ? THEME_DARK : THEME_LIGHT);

        // Apply initial theme
        applyTheme(initialTheme);

        // Create and inject toggle button
        createToggleButton();

        // Listen for system theme changes
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
                if (!localStorage.getItem(THEME_KEY)) {
                    applyTheme(e.matches ? THEME_DARK : THEME_LIGHT);
                }
            });
        }
    }

    /**
     * Apply theme to document
     */
    function applyTheme(theme) {
        document.documentElement.setAttribute('data-pax-theme', theme);
        localStorage.setItem(THEME_KEY, theme);

        // Update WordPress admin bar if present
        const adminBar = document.getElementById('wpadminbar');
        if (adminBar) {
            if (theme === THEME_DARK) {
                adminBar.style.background = '#1e293b';
                adminBar.style.borderBottom = '1px solid #334155';
            } else {
                adminBar.style.background = '';
                adminBar.style.borderBottom = '';
            }
        }
    }

    /**
     * Toggle between themes
     */
    function toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-pax-theme');
        const newTheme = currentTheme === THEME_DARK ? THEME_LIGHT : THEME_DARK;
        applyTheme(newTheme);

        // Add animation class
        const button = document.querySelector('.pax-theme-toggle');
        if (button) {
            button.classList.add('pax-theme-toggle-animate');
            setTimeout(() => {
                button.classList.remove('pax-theme-toggle-animate');
            }, 300);
        }
    }

    /**
     * Create theme toggle button
     */
    function createToggleButton() {
        const headerRight = document.querySelector('.pax-console-header-right');
        if (!headerRight) return;

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'pax-theme-toggle';
        button.setAttribute('aria-label', 'Toggle dark/light mode');
        button.title = 'Toggle dark/light mode';
        
        button.innerHTML = `
            <span class="dashicons dashicons-moon"></span>
            <span class="dashicons dashicons-sun"></span>
        `;

        button.addEventListener('click', toggleTheme);

        // Insert before the first child (or append if no children)
        if (headerRight.firstChild) {
            headerRight.insertBefore(button, headerRight.firstChild);
        } else {
            headerRight.appendChild(button);
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
