=== PAX Support Pro ===
Contributors: ahmadalkhalaf
Tags: support, chat, helpdesk, tickets, callback, live-chat, customer-support
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 5.9.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional support and chat system with optional AI integration for automated responses. Beautiful, fast, and secure.

== Description ==

PAX Support Pro is a professional support and chat system with optional AI integration using the OpenAI API for automated responses. Built with precision and performance in mind, it provides a complete helpdesk solution for WordPress sites.

= Key Features =

* **Live Chat System** - Real-time chat with modern glass-neon UI
* **Ticket Management** - Full ticketing system with status tracking
* **Callback Scheduling** - Allow users to schedule callback requests
* **Analytics Dashboard** - Interactive charts with Charts.js, CSV export, auto-refresh
* **Roles & Permissions** - Support Agent, Manager, and Viewer roles with custom capabilities
* **System Health Monitoring** - SSL, Cloudflare, HTTP/3, database, and latency checks
* **Theme Switcher** - Light, Dark, and Neon modes with user preferences
* **Email Notifications** - Real-time alerts for tickets, chats, and callbacks
* **Export/Import Settings** - Backup and restore plugin configuration
* **Optional AI Assistant** - Integrate OpenAI API for automated responses
* **File Attachments** - Support for image and document uploads
* **Offline Guard** - Detect and notify users when offline
* **Speed Mode** - Hardware-accelerated animations
* **Customizable Branding** - Full color and position customization
* **Mobile Responsive** - Perfect experience on all devices
* **GDPR Compliant** - Privacy-focused design

= AI Integration (Optional) =

The AI Assistant feature uses the OpenAI API to provide automated chat responses. This is completely optional and requires your own OpenAI API key.

**Important:** When you enable the AI Assistant:
- You must provide your own OpenAI API key
- Data is sent securely to OpenAI's servers
- No data is stored locally by this plugin
- You agree to OpenAI's terms of service and privacy policy

= Privacy & Data =

This plugin does not store or share user data externally except when:
1. The optional AI Assistant is enabled (data sent to OpenAI via your API key)
2. File attachments are uploaded (stored in your WordPress media library)

All chat logs and tickets are stored in your WordPress database and never transmitted to third parties.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/pax-support-pro/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to PAX Support → Settings to configure
4. (Optional) Add your OpenAI API key under AI Settings to enable AI features

= Requirements =

* WordPress 5.0 or higher
* PHP 7.4 or higher
* MySQL 5.6 or higher
* Optional: OpenAI API key for AI Assistant features

== Frequently Asked Questions ==

= Is the AI Assistant required? =

No, the AI Assistant is completely optional. The plugin works perfectly without it as a standard support and chat system.

= Do I need an OpenAI API key? =

Only if you want to use the AI Assistant feature. You can get an API key from https://platform.openai.com/

= Where is my data stored? =

All data is stored in your WordPress database. When AI features are enabled, messages are sent to OpenAI's API but not stored by this plugin.

= Is this plugin GDPR compliant? =

Yes, the plugin is designed with privacy in mind. No personal data is transmitted to third parties unless you explicitly enable the AI Assistant feature.

= Can I customize the appearance? =

Yes, you can customize colors, position, branding, and more from the settings panel.

= Does it work with caching plugins? =

Yes, the plugin is compatible with popular caching plugins including WP Rocket, W3 Total Cache, and Cloudflare.

== Screenshots ==

1. Modern chat interface with glass-neon design
2. Admin settings panel with full customization
3. Callback scheduling system
4. Ticket management dashboard
5. AI Assistant configuration (optional)

== Changelog ==

= 5.9.0 - 2025-11-10 =
* Live session bootstrap, user detection, spinner/tab fix, and reliable REST messaging.

= 5.8.9 - 2025-11-10 =
* Live session auto-bootstrap, user detection, modern UI overhaul, and full REST sync stability improvements.

= 5.8.8 - 2025-11-10 =
* Improved Live Agent connection, added smart onboarding UI, and redesigned interface with modern styling.

= 5.8.7 - 2025-11-10 =
* Improved: Hardened REST polling intervals to keep Live Agent Center synchronized in busy queues
* Improved: Composer, diagnostics, and placeholder states for clearer guidance during pending/closed sessions
* Fixed: Occasional duplicate notifications and ensured read receipts respect the unified message endpoint
* Technical: Refreshed build artifacts and metadata in preparation for public release

= 5.8.6 - 2025-11-10 =
* Added: Redesigned Live Agent settings screen with modern cards, toggles, and responsive layout
* Added: Connection & Diagnostics panel with REST base URL copy helper, domain/IP resolution, and health check
* Added: Dedicated Live Agent settings assets with targeted CSS and diagnostics JavaScript
* Enhanced: Guaranteed message delivery by routing all admin replies through POST /pax/v1/live/message
* Enhanced: Normalized session statuses so accepted sessions surface in both admin and frontend views
* Enhanced: Live Agent addon safely disables AI during active human sessions and restores state afterward
* Fixed: Admin console message rendering for attachments and ensured new endpoints use consistent REST URLs
* Fixed: REST API healthcheck now returns status ok for diagnostics panel testing

= 5.7.5 - 2025-11-05 =
* Fixed: Removed remaining dark overlays from mode switcher, reply-to, and help center
* Fixed: All dark backgrounds now use CSS variables for full brightness control
* Enhanced: Mode switcher uses --pax-bg color
* Enhanced: Reply-to messages use --pax-bg and --pax-panel colors
* Enhanced: Help center search uses --pax-bg color
* Enhanced: Help overlay reduced opacity (0.55 → 0.3) for better visibility
* Verified: All admin color settings properly linked to frontend
* Verified: Menu buttons do not close chat (only X button closes)
* Verified: Chat header matches admin theme settings
* Verified: All buttons function correctly
* Technical: Increased CSS variable usage from 43 to 52 instances
* Technical: Zero hardcoded dark backgrounds remaining

= 5.7.4 - 2025-11-05 =
* Fixed: Chat interface now respects admin color settings
* Fixed: Removed all hardcoded dark backgrounds (rgba(20,20,30) and rgba(30,30,45))
* Fixed: Bound all chat elements to CSS variables from admin panel
* Enhanced: Chat background uses --pax-panel color
* Enhanced: Text colors use --pax-text and --pax-sub variables
* Enhanced: Buttons and accents use --pax-accent color
* Enhanced: Borders use --pax-border color
* Enhanced: Messages area uses --pax-bg color
* Technical: Replaced 7+ hardcoded color instances with CSS variables
* Technical: Colors now apply consistently across light and dark themes

= 5.7.3 - 2025-11-05 =
* Fixed: Removed overlay click-to-close functionality - chat only closes via X button
* Fixed: Overlay no longer blocks page interactions (pointer-events: none)
* Fixed: Launcher button only opens chat, doesn't toggle close
* Enhanced: All site buttons, sliders, and navigation remain fully functional with chat open
* Enhanced: Fully transparent overlay with no visual interference
* Technical: Removed all overlay click handlers from JavaScript
* Technical: Ensured proper z-index hierarchy for page element interaction

= 5.7.2 - 2025-11-05 =
* Fixed: Removed ALL backdrop-filter properties from entire CSS codebase
* Fixed: Changed overlay background to fully transparent (rgba(0,0,0,0))
* Enhanced: Chat opens without any visual interference to page content
* Technical: Eliminated all blur effects that could impact page rendering

= 5.7.1 - 2025-11-05 =
* Fixed: Removed full-page blur effect when chat window opens
* Fixed: Added proper overlay base styles with dimmed background (no blur)
* Fixed: Removed backdrop-filter from welcome screen to prevent page blur
* Fixed: Removed backdrop-filter from help center overlay and modal
* Enhanced: Chat now opens cleanly with proper z-index stacking
* Enhanced: Overlay provides subtle dimming without affecting page content
* Technical: Updated z-index values for proper layering (overlay: 9999998, chat: 9999999)

= 5.7.0 - 2025-11-05 =
* Fixed: Chat launcher context binding error - this.openChat is not a function
* Fixed: Corrected PAXEventBindings.bindLauncher() to use this.chatInstance for method calls
* Fixed: isOpen flag now correctly references this.chatInstance.isOpen
* Technical: Resolved JavaScript context issue where 'this' referred to PAXEventBindings instead of chat instance

= 4.9.0 - 2025-11-04 =
* Added: Live Agent Center - Complete real-time chat system
* Added: WhatsApp-like admin interface with glass-neon design
* Added: 8 new REST API endpoints for session, message, status, and file operations
* Added: Frontend live agent button with customizable position
* Added: Real-time messaging with typing indicators and read receipts
* Added: File upload system (images, PDFs, documents)
* Added: Session management (accept, decline, close, export, convert to ticket)
* Added: Email notifications for new requests and messages
* Added: Custom capabilities (manage_pax_chats, view_pax_chats, accept_pax_chats)
* Added: Admin bar shortcut with pending session count
* Added: Cloudflare compatibility mode with IP detection
* Added: Automated test suite with 10 test categories
* Added: Live Agent settings tab with 15+ configuration options
* Technical: 17 new files, ~3,800 lines of code
* Technical: New database table for session management
* Verified: PHP 8.3 and WordPress 6.4+ compatibility
* Verified: All automated tests passing
* Security: Full nonce verification, file validation, capability checks

= 4.6.0 - 2025-11-03 =
* Added: Roles & Permissions page with Support Agent, Manager, and Viewer roles
* Added: Analytics Dashboard with Charts.js, CSV export, and auto-refresh
* Added: System Health page with SSL, Cloudflare, HTTP/3, and database monitoring
* Added: Theme Settings page with Light, Dark, and Neon mode support
* Added: Export/Import Settings functionality for backup and restore
* Added: Email Notification System for tickets, chats, and callbacks
* Added: Real-Time Toast Notifications in admin panel
* Added: Frontend Theme Switcher with localStorage preferences
* Added: Custom WordPress capabilities for role-based access control
* Enhanced: Admin menu with 4 new professional pages
* Enhanced: Modern glass-neon UI consistency across all pages
* Technical: Chart.js 4.4.0 CDN integration
* Technical: 9 new files added for enhanced functionality
* Verified: PHP 8.3 and WordPress 6.4+ compatibility maintained
* Verified: All new features tested and functional

= 4.4.1 - 2025-11-03 =
* Fixed: PHP Fatal Error - removed duplicate pax_sup_activate() function
* Fixed: Activation hook conflict resolved
* Verified: All REST endpoints, AJAX callbacks, and admin menus
* Verified: AI features, scheduler, and ticket systems load without warnings
* Verified: PHP 8.3 and WordPress 6.4+ compatibility
* Verified: Plugin initialization order and dependency checks
* Verified: Auto-fix system for missing options and database tables
* Tested: Complete system verification passed
* Status: Stable build ready for WordPress.org submission

= 4.4.0 - 2025-11-03 =
* Added: WordPress.org compliance and legal notices
* Added: Professional AI integration notice in admin settings
* Added: Comprehensive privacy and terms documentation
* Added: Translation support with POT file
* Improved: GPL compliance and licensing
* Improved: Documentation and installation guides
* Updated: Author attribution and branding

= 4.3.0 - 2025-11-03 =
* Added: Synced menu icons with admin panel using Dashicons
* Added: Futuristic AI robot typing indicator with neon glow
* Added: Welcome message system with admin customization
* Added: Protected PAX signature footer branding
* Added: Enhanced admin controls for new features
* Added: Glass-neon theme polish with CSS variables
* Improved: Menu item styling with red accent glow
* Improved: CSS architecture and animation performance

= 4.2.1 - 2025-11-03 =
* Fixed: Callback validation defaults
* Fixed: CSS classes for text/URL fields
* Improved: Admin UI feedback for callback scheduling
* Improved: Form group hover effects
* Improved: Input focus states and button styling

= 4.2.0 - 2025-11-03 =
* Added: Admin callback scheduling system
* Added: Callback scheduling modal with validation
* Added: Callback enabled toggle in settings
* Fixed: Callback disabled error handling

== Upgrade Notice ==

= 4.4.1 =
Critical stability update - fixes activation hook conflict. Full system verification completed. Recommended for all users.

= 4.4.0 =
WordPress.org compliance update with enhanced privacy notices and documentation. All AI features remain optional.

== License ==

This plugin is licensed under the GPLv2 or later.

Copyright (C) 2025 Ahmad AlKhalaf

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA

== Credits ==

Developed by Ahmad AlKhalaf
GitHub: https://github.com/Black10998/Black10998
