<?php
/**
 * Modern Settings UI Rendering
 * PAX Support Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function pax_sup_render_modern_settings() {
    if ( ! current_user_can( pax_sup_get_console_capability() ) ) {
        return;
    }

    $options = pax_sup_get_options();
    ?>
    <div class="pax-modern-settings">
        <!-- Header -->
        <div class="pax-settings-header">
            <h1>
                <span class="dashicons dashicons-admin-settings"></span>
                <?php esc_html_e( 'PAX Support Pro Settings', 'pax-support-pro' ); ?>
            </h1>
        </div>

        <form method="post" action="">
            <?php wp_nonce_field( 'pax_sup_save_settings' ); ?>
            
            <div class="pax-settings-layout">
                <!-- Settings Content -->
                <div class="pax-settings-content">
                    
                    <!-- General Settings Card -->
                    <div class="pax-card">
                        <div class="pax-card-header">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <h2><?php esc_html_e( 'General Settings', 'pax-support-pro' ); ?></h2>
                        </div>
                        <div class="pax-card-body">
                            <!-- Enable Plugin -->
                            <div class="pax-form-group">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div>
                                        <label class="pax-form-label">
                                            <span class="dashicons dashicons-yes-alt"></span>
                                            <?php esc_html_e( 'Enable Plugin', 'pax-support-pro' ); ?>
                                            <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Master switch to enable/disable the entire support system', 'pax-support-pro' ); ?>">?</span>
                                        </label>
                                        <p class="pax-form-description"><?php esc_html_e( 'Master switch to enable or disable the entire support system.', 'pax-support-pro' ); ?></p>
                                    </div>
                                    <label class="pax-toggle">
                                        <input type="checkbox" name="enabled" <?php checked( $options['enabled'] ); ?>>
                                        <span class="pax-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Enable Chat -->
                            <div class="pax-form-group">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div>
                                        <label class="pax-form-label">
                                            <span class="dashicons dashicons-format-chat"></span>
                                            <?php esc_html_e( 'Enable Chat', 'pax-support-pro' ); ?>
                                            <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Show the chat launcher on your website', 'pax-support-pro' ); ?>">?</span>
                                        </label>
                                        <p class="pax-form-description"><?php esc_html_e( 'Display the chat launcher widget on your website.', 'pax-support-pro' ); ?></p>
                                    </div>
                                    <label class="pax-toggle">
                                        <input type="checkbox" name="enable_chat" <?php checked( $options['enable_chat'] ); ?>>
                                        <span class="pax-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Chat Access Control -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-admin-users"></span>
                                    <?php esc_html_e( 'Chat Access Control', 'pax-support-pro' ); ?>
                                    <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Control who can access the chat system', 'pax-support-pro' ); ?>">?</span>
                                </label>
                                <p class="pax-form-description"><?php esc_html_e( 'Choose who can access the chat system. Select "Everyone" for all users, "Logged-in Users Only" to require login, or "Disabled for All" to completely disable chat.', 'pax-support-pro' ); ?></p>
                                <select name="chat_access_control" class="pax-text-input" style="font-family: inherit;">
                                    <option value="everyone" <?php selected( $options['chat_access_control'] ?? 'everyone', 'everyone' ); ?>><?php esc_html_e( 'Everyone', 'pax-support-pro' ); ?></option>
                                    <option value="logged_in" <?php selected( $options['chat_access_control'] ?? 'everyone', 'logged_in' ); ?>><?php esc_html_e( 'Logged-in Users Only', 'pax-support-pro' ); ?></option>
                                    <option value="disabled" <?php selected( $options['chat_access_control'] ?? 'everyone', 'disabled' ); ?>><?php esc_html_e( 'Disabled for All', 'pax-support-pro' ); ?></option>
                                </select>
                            </div>

                            <!-- Chat Disabled Message -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-admin-comments"></span>
                                    <?php esc_html_e( 'Chat Disabled Message', 'pax-support-pro' ); ?>
                                    <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Message shown when chat is disabled', 'pax-support-pro' ); ?>">?</span>
                                </label>
                                <p class="pax-form-description"><?php esc_html_e( 'Custom message displayed when chat is disabled or restricted. Used for both "Logged-in Users Only" and "Disabled for All" modes.', 'pax-support-pro' ); ?></p>
                                <input type="text" name="chat_disabled_message" value="<?php echo esc_attr( $options['chat_disabled_message'] ?? 'Chat is currently disabled. Please try again later.' ); ?>" class="pax-text-input" style="font-family: inherit;">
                            </div>

                            <!-- Disable Chat Menu -->
                            <div class="pax-form-group">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div>
                                        <label class="pax-form-label">
                                            <span class="dashicons dashicons-menu-alt"></span>
                                            <?php esc_html_e( 'Disable Chat Menu', 'pax-support-pro' ); ?>
                                            <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Hide all chat menu items', 'pax-support-pro' ); ?>">?</span>
                                        </label>
                                        <p class="pax-form-description"><?php esc_html_e( 'When enabled, hides all chat menu items (Help Center, What\'s New, etc.). Only main chat remains.', 'pax-support-pro' ); ?></p>
                                    </div>
                                    <label class="pax-toggle">
                                        <input type="checkbox" name="disable_chat_menu" <?php checked( $options['disable_chat_menu'] ?? 0 ); ?>>
                                        <span class="pax-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Enable Tickets -->
                            <div class="pax-form-group">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div>
                                        <label class="pax-form-label">
                                            <span class="dashicons dashicons-tickets-alt"></span>
                                            <?php esc_html_e( 'Enable Tickets', 'pax-support-pro' ); ?>
                                            <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Allow users to create support tickets', 'pax-support-pro' ); ?>">?</span>
                                        </label>
                                        <p class="pax-form-description"><?php esc_html_e( 'Allow users to create and manage support tickets.', 'pax-support-pro' ); ?></p>
                                    </div>
                                    <label class="pax-toggle">
                                        <input type="checkbox" name="enable_ticket" <?php checked( $options['enable_ticket'] ); ?>>
                                        <span class="pax-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Enable Callback Scheduling -->
                            <div class="pax-form-group">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div>
                                        <label class="pax-form-label">
                                            <span class="dashicons dashicons-phone"></span>
                                            <?php esc_html_e( 'Enable Callback Scheduling', 'pax-support-pro' ); ?>
                                            <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Allow users to schedule callback requests', 'pax-support-pro' ); ?>">?</span>
                                        </label>
                                        <p class="pax-form-description"><?php esc_html_e( 'Enable the callback scheduling system for users to request callbacks at specific times.', 'pax-support-pro' ); ?></p>
                                        <?php if ( empty( $options['callback_enabled'] ) ) : ?>
                                            <p class="pax-form-description" style="color: #e53935; font-weight: 500; margin-top: 8px;">
                                                <span class="dashicons dashicons-warning" style="font-size: 16px; vertical-align: middle;"></span>
                                                <?php esc_html_e( 'Callback scheduling is currently disabled. Users will not be able to schedule callbacks.', 'pax-support-pro' ); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <label class="pax-toggle">
                                        <input type="checkbox" name="callback_enabled" <?php checked( $options['callback_enabled'] ?? 1 ); ?>>
                                        <span class="pax-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Enable Live Agent System -->
                            <div class="pax-form-group">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div>
                                        <label class="pax-form-label">
                                            <span class="dashicons dashicons-businessman"></span>
                                            <?php esc_html_e( 'Enable Live Agent System', 'pax-support-pro' ); ?>
                                            <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Activate the Live Agent real-time chat and admin interface', 'pax-support-pro' ); ?>">?</span>
                                        </label>
                                        <p class="pax-form-description"><?php esc_html_e( 'Activate the Live Agent real-time chat and admin interface.', 'pax-support-pro' ); ?></p>
                                    </div>
                                    <label class="pax-toggle">
                                        <input type="checkbox" name="live_agent_enabled" <?php checked( $options['live_agent_enabled'] ?? 0 ); ?>>
                                        <span class="pax-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Brand Name -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-tag"></span>
                                    <?php esc_html_e( 'Brand Name', 'pax-support-pro' ); ?>
                                    <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Your brand name displayed in the chat header', 'pax-support-pro' ); ?>">?</span>
                                </label>
                                <p class="pax-form-description"><?php esc_html_e( 'Your brand name displayed in the chat header.', 'pax-support-pro' ); ?></p>
                                <input type="text" name="brand_name" value="<?php echo esc_attr( $options['brand_name'] ); ?>" class="pax-text-input" style="font-family: inherit;">
                            </div>

                            <!-- What's New URL -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-megaphone"></span>
                                    <?php esc_html_e( "What's New URL", 'pax-support-pro' ); ?>
                                    <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Custom URL for the What\'s New button (leave empty to show coming soon message)', 'pax-support-pro' ); ?>">?</span>
                                </label>
                                <p class="pax-form-description"><?php esc_html_e( 'Custom URL for the What\'s New button. Leave empty to show "Coming soon" message.', 'pax-support-pro' ); ?></p>
                                <input type="url" name="whats_new_url" value="<?php echo esc_attr( $options['whats_new_url'] ?? '' ); ?>" class="pax-text-input" placeholder="https://example.com/whats-new" style="font-family: inherit;">
                            </div>

                            <!-- Donate URL -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-heart"></span>
                                    <?php esc_html_e( 'Donate/Support URL', 'pax-support-pro' ); ?>
                                    <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Custom URL for the Donate/Support button', 'pax-support-pro' ); ?>">?</span>
                                </label>
                                <p class="pax-form-description"><?php esc_html_e( 'Custom URL for the Donate/Support button.', 'pax-support-pro' ); ?></p>
                                <input type="url" name="donate_url" value="<?php echo esc_attr( $options['donate_url'] ?? 'https://www.paypal.me/AhmadAlkhalaf29' ); ?>" class="pax-text-input" placeholder="https://www.paypal.me/YourName" style="font-family: inherit;">
                            </div>
                        </div>
                    </div>

                    <!-- Color Settings Card -->
                    <div class="pax-card">
                        <div class="pax-card-header">
                            <span class="dashicons dashicons-art"></span>
                            <h2><?php esc_html_e( 'Color Scheme', 'pax-support-pro' ); ?></h2>
                        </div>
                        <div class="pax-card-body">
                            <!-- Accent Color -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-admin-appearance"></span>
                                    <?php esc_html_e( 'Primary Color', 'pax-support-pro' ); ?>
                                    <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Main accent color for buttons and highlights', 'pax-support-pro' ); ?>">?</span>
                                </label>
                                <p class="pax-form-description"><?php esc_html_e( 'Changes the main accent color for the chat interface.', 'pax-support-pro' ); ?></p>
                                <div class="pax-color-picker-wrapper">
                                    <div class="pax-color-preview" style="background: <?php echo esc_attr( $options['color_accent'] ); ?>"></div>
                                    <input type="color" name="color_accent" value="<?php echo esc_attr( $options['color_accent'] ); ?>" class="pax-color-input">
                                </div>
                            </div>

                            <!-- Background Color -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-admin-appearance"></span>
                                    <?php esc_html_e( 'Background Color', 'pax-support-pro' ); ?>
                                    <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Main background color of the chat window', 'pax-support-pro' ); ?>">?</span>
                                </label>
                                <p class="pax-form-description"><?php esc_html_e( 'Main background color of the chat window.', 'pax-support-pro' ); ?></p>
                                <div class="pax-color-picker-wrapper">
                                    <div class="pax-color-preview" style="background: <?php echo esc_attr( $options['color_bg'] ); ?>"></div>
                                    <input type="color" name="color_bg" value="<?php echo esc_attr( $options['color_bg'] ); ?>" class="pax-color-input">
                                </div>
                            </div>

                            <!-- Panel Color -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-admin-appearance"></span>
                                    <?php esc_html_e( 'Panel Color', 'pax-support-pro' ); ?>
                                    <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Color for panels and cards within the chat', 'pax-support-pro' ); ?>">?</span>
                                </label>
                                <p class="pax-form-description"><?php esc_html_e( 'Color for panels and cards within the chat.', 'pax-support-pro' ); ?></p>
                                <div class="pax-color-picker-wrapper">
                                    <div class="pax-color-preview" style="background: <?php echo esc_attr( $options['color_panel'] ); ?>"></div>
                                    <input type="color" name="color_panel" value="<?php echo esc_attr( $options['color_panel'] ); ?>" class="pax-color-input">
                                </div>
                            </div>

                            <!-- Border Color -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-admin-appearance"></span>
                                    <?php esc_html_e( 'Border Color', 'pax-support-pro' ); ?>
                                    <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Color for borders and dividers', 'pax-support-pro' ); ?>">?</span>
                                </label>
                                <p class="pax-form-description"><?php esc_html_e( 'Color for borders and dividers.', 'pax-support-pro' ); ?></p>
                                <div class="pax-color-picker-wrapper">
                                    <div class="pax-color-preview" style="background: <?php echo esc_attr( $options['color_border'] ); ?>"></div>
                                    <input type="color" name="color_border" value="<?php echo esc_attr( $options['color_border'] ); ?>" class="pax-color-input">
                                </div>
                            </div>

                            <!-- Text Color -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-admin-appearance"></span>
                                    <?php esc_html_e( 'Text Color', 'pax-support-pro' ); ?>
                                    <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Primary text color', 'pax-support-pro' ); ?>">?</span>
                                </label>
                                <p class="pax-form-description"><?php esc_html_e( 'Primary text color for the chat interface.', 'pax-support-pro' ); ?></p>
                                <div class="pax-color-picker-wrapper">
                                    <div class="pax-color-preview" style="background: <?php echo esc_attr( $options['color_text'] ); ?>"></div>
                                    <input type="color" name="color_text" value="<?php echo esc_attr( $options['color_text'] ); ?>" class="pax-color-input">
                                </div>
                            </div>

                            <!-- Reaction Button Color -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-smiley"></span>
                                    <?php esc_html_e( 'Reaction Button Color', 'pax-support-pro' ); ?>
                                    <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Color for the chat reaction button (+)', 'pax-support-pro' ); ?>">?</span>
                                </label>
                                <p class="pax-form-description"><?php esc_html_e( 'Customize the color of the reaction button that appears on bot messages.', 'pax-support-pro' ); ?></p>
                                <div class="pax-color-picker-wrapper">
                                    <div class="pax-color-preview" style="background: <?php echo esc_attr( $options['reaction_btn_color'] ?? '#e53935' ); ?>"></div>
                                    <input type="color" name="reaction_btn_color" value="<?php echo esc_attr( $options['reaction_btn_color'] ?? '#e53935' ); ?>" class="pax-color-input">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Customization Card -->
                    <div class="pax-card">
                        <div class="pax-card-header">
                            <span class="dashicons dashicons-format-chat"></span>
                            <h2><?php esc_html_e( 'Chat Customization', 'pax-support-pro' ); ?></h2>
                        </div>
                        <div class="pax-card-body">
                            <!-- Custom Send Icon -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-upload"></span>
                                    <?php esc_html_e( 'Custom Send Icon', 'pax-support-pro' ); ?>
                                    <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Upload a custom icon for the send button', 'pax-support-pro' ); ?>">?</span>
                                </label>
                                <p class="pax-form-description"><?php esc_html_e( 'Upload a custom icon to replace the default send arrow. Recommended size: 24x24px (PNG, SVG, or JPG).', 'pax-support-pro' ); ?></p>
                                <div style="display: flex; align-items: center; gap: 12px; margin-top: 12px;">
                                    <input type="hidden" name="custom_send_icon" id="custom_send_icon" value="<?php echo esc_attr( $options['custom_send_icon'] ?? '' ); ?>">
                                    <button type="button" id="upload_send_icon_button" class="pax-btn pax-btn-secondary">
                                        <span class="dashicons dashicons-upload"></span>
                                        <?php esc_html_e( 'Upload Icon', 'pax-support-pro' ); ?>
                                    </button>
                                    <?php if ( ! empty( $options['custom_send_icon'] ) ) : ?>
                                        <div id="send_icon_preview" style="display: flex; align-items: center; gap: 8px;">
                                            <img src="<?php echo esc_url( $options['custom_send_icon'] ); ?>" style="width: 32px; height: 32px; object-fit: contain; background: var(--pax-accent); padding: 6px; border-radius: 6px;">
                                            <button type="button" id="remove_send_icon_button" class="pax-btn pax-btn-danger" style="padding: 4px 8px; font-size: 12px;">
                                                <span class="dashicons dashicons-no" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                                <?php esc_html_e( 'Remove', 'pax-support-pro' ); ?>
                                            </button>
                                        </div>
                                    <?php else : ?>
                                        <div id="send_icon_preview" style="display: none;"></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Custom Launcher Icon -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-format-chat"></span>
                                    <?php esc_html_e( 'Custom Launcher Icon', 'pax-support-pro' ); ?>
                                    <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Upload a custom icon for the chat launcher button', 'pax-support-pro' ); ?>">?</span>
                                </label>
                                <p class="pax-form-description"><?php esc_html_e( 'Upload a custom icon to replace the default chat launcher. Recommended size: 48x48px (PNG, SVG, or JPG).', 'pax-support-pro' ); ?></p>
                                <div style="display: flex; align-items: center; gap: 12px; margin-top: 12px;">
                                    <input type="hidden" name="custom_launcher_icon" id="custom_launcher_icon" value="<?php echo esc_attr( $options['custom_launcher_icon'] ?? '' ); ?>">
                                    <button type="button" id="upload_launcher_icon_button" class="pax-btn pax-btn-secondary">
                                        <span class="dashicons dashicons-upload"></span>
                                        <?php esc_html_e( 'Upload Launcher Icon', 'pax-support-pro' ); ?>
                                    </button>
                                    <?php if ( ! empty( $options['custom_launcher_icon'] ) ) : ?>
                                        <div id="launcher_icon_preview" style="display: flex; align-items: center; gap: 8px;">
                                            <img src="<?php echo esc_url( $options['custom_launcher_icon'] ); ?>" style="width: 48px; height: 48px; object-fit: contain; background: var(--pax-accent); padding: 8px; border-radius: 50%;">
                                            <button type="button" id="remove_launcher_icon_button" class="pax-btn pax-btn-danger" style="padding: 4px 8px; font-size: 12px;">
                                                <span class="dashicons dashicons-no" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                                <?php esc_html_e( 'Remove', 'pax-support-pro' ); ?>
                                            </button>
                                        </div>
                                    <?php else : ?>
                                        <div id="launcher_icon_preview" style="display: none;"></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Welcome Message -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-welcome-learn-more"></span>
                                    <?php esc_html_e( 'Welcome Message', 'pax-support-pro' ); ?>
                                    <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Custom welcome message shown when chat opens for logged-in users', 'pax-support-pro' ); ?>">?</span>
                                </label>
                                <p class="pax-form-description"><?php esc_html_e( 'Customize the welcome message displayed when logged-in users open the chat. Leave empty to use default message.', 'pax-support-pro' ); ?></p>
                                <textarea name="welcome_message" class="pax-text-input" rows="3" placeholder="<?php esc_attr_e( '👋 Welcome back! How can I help you today?', 'pax-support-pro' ); ?>" style="width: 100%; resize: vertical; font-family: inherit;"><?php echo esc_textarea( $options['welcome_message'] ?? '' ); ?></textarea>
                            </div>

                            <!-- Enable Reply-to-Message -->
                            <div class="pax-form-group">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div>
                                        <label class="pax-form-label">
                                            <span class="dashicons dashicons-undo"></span>
                                            <?php esc_html_e( 'Enable Reply-to-Message', 'pax-support-pro' ); ?>
                                            <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Allow users to reply to specific messages', 'pax-support-pro' ); ?>">?</span>
                                        </label>
                                        <p class="pax-form-description"><?php esc_html_e( 'Enable the ability for users to reply to specific messages with context.', 'pax-support-pro' ); ?></p>
                                    </div>
                                    <label class="pax-toggle">
                                        <input type="checkbox" name="enable_reply_to" <?php checked( $options['enable_reply_to'] ?? 0 ); ?>>
                                        <span class="pax-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Enable Quick Actions -->
                            <div class="pax-form-group">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div>
                                        <label class="pax-form-label">
                                            <span class="dashicons dashicons-admin-tools"></span>
                                            <?php esc_html_e( 'Enable Quick Actions', 'pax-support-pro' ); ?>
                                            <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Show quick actions dropdown in chat header', 'pax-support-pro' ); ?>">?</span>
                                        </label>
                                        <p class="pax-form-description"><?php esc_html_e( 'Enable quick actions menu with options like Reload Chat, Clear History, and Toggle Theme.', 'pax-support-pro' ); ?></p>
                                    </div>
                                    <label class="pax-toggle">
                                        <input type="checkbox" name="enable_quick_actions" <?php checked( $options['enable_quick_actions'] ?? 1 ); ?>>
                                        <span class="pax-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Enable Customization Mode -->
                            <div class="pax-form-group">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div>
                                        <label class="pax-form-label">
                                            <span class="dashicons dashicons-admin-appearance"></span>
                                            <?php esc_html_e( 'Enable Customization Mode', 'pax-support-pro' ); ?>
                                            <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Allow premium users to customize chat appearance', 'pax-support-pro' ); ?>">?</span>
                                        </label>
                                        <p class="pax-form-description"><?php esc_html_e( 'Enable live customization mode for premium users to modify chat colors and appearance.', 'pax-support-pro' ); ?></p>
                                    </div>
                                    <label class="pax-toggle">
                                        <input type="checkbox" name="enable_customization" <?php checked( $options['enable_customization'] ?? 0 ); ?>>
                                        <span class="pax-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AI Settings Card -->
                    <div class="pax-card">
                        <div class="pax-card-header">
                            <span class="dashicons dashicons-superhero"></span>
                            <h2><?php esc_html_e( 'AI Assistant', 'pax-support-pro' ); ?></h2>
                        </div>
                        <div class="pax-card-body">
                            <!-- AI Notice Box -->
                            <div class="pax-ai-notice" style="background: linear-gradient(135deg, rgba(33, 150, 243, 0.1) 0%, rgba(33, 150, 243, 0.05) 100%); border: 2px solid rgba(33, 150, 243, 0.3); border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                                <div style="display: flex; align-items: flex-start; gap: 16px;">
                                    <span class="dashicons dashicons-info" style="font-size: 24px; width: 24px; height: 24px; color: #2196F3; flex-shrink: 0; margin-top: 2px;"></span>
                                    <div>
                                        <p style="margin: 0 0 12px 0; font-size: 14px; line-height: 1.6; color: #23282d; font-weight: 500;">
                                            <?php esc_html_e( 'The AI Assistant uses the OpenAI API. To activate AI features, enter your own API key below. Data is sent securely to OpenAI and never stored locally by this plugin.', 'pax-support-pro' ); ?>
                                        </p>
                                        <p style="margin: 0; font-size: 12px; line-height: 1.5; color: #666; opacity: 0.9;">
                                            <?php esc_html_e( 'By using this feature, you agree to OpenAI\'s API terms and privacy policy.', 'pax-support-pro' ); ?>
                                            <a href="https://openai.com/policies/terms-of-use" target="_blank" rel="noopener noreferrer" style="color: #2196F3; text-decoration: none; margin-left: 4px;">
                                                <?php esc_html_e( 'Learn more', 'pax-support-pro' ); ?> →
                                            </a>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Enable AI -->
                            <div class="pax-form-group">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div>
                                        <label class="pax-form-label">
                                            <span class="dashicons dashicons-superhero-alt"></span>
                                            <?php esc_html_e( 'Enable AI Assistant', 'pax-support-pro' ); ?>
                                            <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Enable AI-powered chat responses', 'pax-support-pro' ); ?>">?</span>
                                        </label>
                                        <p class="pax-form-description"><?php esc_html_e( 'Enable AI-powered automatic responses in chat.', 'pax-support-pro' ); ?></p>
                                    </div>
                                    <label class="pax-toggle">
                                        <input type="checkbox" name="ai_assistant_enabled" <?php checked( $options['ai_assistant_enabled'] ); ?>>
                                        <span class="pax-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <!-- OpenAI Integration -->
                            <div class="pax-form-group">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div>
                                        <label class="pax-form-label">
                                            <span class="dashicons dashicons-cloud"></span>
                                            <?php esc_html_e( 'OpenAI Integration', 'pax-support-pro' ); ?>
                                            <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Use OpenAI API for advanced AI responses', 'pax-support-pro' ); ?>">?</span>
                                        </label>
                                        <p class="pax-form-description"><?php esc_html_e( 'Use OpenAI API for advanced AI responses.', 'pax-support-pro' ); ?></p>
                                    </div>
                                    <label class="pax-toggle">
                                        <input type="checkbox" name="openai_enabled" <?php checked( $options['openai_enabled'] ); ?>>
                                        <span class="pax-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <!-- API Key -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-admin-network"></span>
                                    <?php esc_html_e( 'OpenAI API Key', 'pax-support-pro' ); ?>
                                    <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Your OpenAI API key for authentication', 'pax-support-pro' ); ?>">?</span>
                                </label>
                                <p class="pax-form-description"><?php esc_html_e( 'Your OpenAI API key. Can also be defined in wp-config.php as PXA_OPENAI_API_KEY.', 'pax-support-pro' ); ?></p>
                                <input type="password" name="openai_key" value="<?php echo esc_attr( $options['openai_key'] ); ?>" class="pax-text-input" style="font-family: 'Monaco', monospace;" autocomplete="off">
                            </div>

                            <!-- Temperature -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-performance"></span>
                                    <?php esc_html_e( 'AI Temperature', 'pax-support-pro' ); ?>
                                    <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Controls randomness: 0 = focused, 1 = creative', 'pax-support-pro' ); ?>">?</span>
                                </label>
                                <p class="pax-form-description"><?php esc_html_e( 'Controls AI creativity. Lower values (0.1-0.3) are more focused, higher values (0.7-1.0) are more creative.', 'pax-support-pro' ); ?></p>
                                <div class="pax-range-wrapper">
                                    <div class="pax-range-header">
                                        <span><?php esc_html_e( 'Temperature', 'pax-support-pro' ); ?></span>
                                        <span class="pax-range-value"></span>
                                    </div>
                                    <input type="range" name="openai_temperature" min="0" max="1" step="0.05" value="<?php echo esc_attr( $options['openai_temperature'] ); ?>" class="pax-range-slider" data-unit="">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Reactions Card -->
                    <div class="pax-card">
                        <div class="pax-card-header">
                            <span class="dashicons dashicons-thumbs-up"></span>
                            <h2><?php esc_html_e( 'Chat Reactions', 'pax-support-pro' ); ?></h2>
                        </div>
                        <div class="pax-card-body">
                            <!-- Enable Copy Icon -->
                            <div class="pax-form-group">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div>
                                        <label class="pax-form-label">
                                            <span class="dashicons dashicons-admin-page"></span>
                                            <?php esc_html_e( 'Enable Copy Icon', 'pax-support-pro' ); ?>
                                            <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Show copy icon on bot messages', 'pax-support-pro' ); ?>">?</span>
                                        </label>
                                        <p class="pax-form-description"><?php esc_html_e( 'Allow users to copy bot messages to clipboard.', 'pax-support-pro' ); ?></p>
                                    </div>
                                    <label class="pax-toggle">
                                        <input type="checkbox" name="chat_reactions_enable_copy" <?php checked( $options['chat_reactions_enable_copy'] ?? 1 ); ?>>
                                        <span class="pax-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Enable Like Icon -->
                            <div class="pax-form-group">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div>
                                        <label class="pax-form-label">
                                            <span class="dashicons dashicons-thumbs-up"></span>
                                            <?php esc_html_e( 'Enable Like Icon', 'pax-support-pro' ); ?>
                                            <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Show like icon on bot messages', 'pax-support-pro' ); ?>">?</span>
                                        </label>
                                        <p class="pax-form-description"><?php esc_html_e( 'Allow users to like bot messages.', 'pax-support-pro' ); ?></p>
                                    </div>
                                    <label class="pax-toggle">
                                        <input type="checkbox" name="chat_reactions_enable_like" <?php checked( $options['chat_reactions_enable_like'] ?? 1 ); ?>>
                                        <span class="pax-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Enable Dislike Icon -->
                            <div class="pax-form-group">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div>
                                        <label class="pax-form-label">
                                            <span class="dashicons dashicons-thumbs-down"></span>
                                            <?php esc_html_e( 'Enable Dislike Icon', 'pax-support-pro' ); ?>
                                            <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Show dislike icon on bot messages', 'pax-support-pro' ); ?>">?</span>
                                        </label>
                                        <p class="pax-form-description"><?php esc_html_e( 'Allow users to dislike bot messages for feedback.', 'pax-support-pro' ); ?></p>
                                    </div>
                                    <label class="pax-toggle">
                                        <input type="checkbox" name="chat_reactions_enable_dislike" <?php checked( $options['chat_reactions_enable_dislike'] ?? 1 ); ?>>
                                        <span class="pax-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Reset Reactions Button -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-update"></span>
                                    <?php esc_html_e( 'Reset All Reactions', 'pax-support-pro' ); ?>
                                </label>
                                <p class="pax-form-description"><?php esc_html_e( 'Clear all stored reaction data from the database.', 'pax-support-pro' ); ?></p>
                                <button type="button" id="pax-reset-reactions" class="button button-secondary">
                                    <?php esc_html_e( 'Reset Reactions', 'pax-support-pro' ); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Customization Card -->
                    <div class="pax-card">
                        <div class="pax-card-header">
                            <span class="dashicons dashicons-admin-customizer"></span>
                            <h2><?php esc_html_e( 'Chat Customization', 'pax-support-pro' ); ?></h2>
                        </div>
                        <div class="pax-card-body">
                            <!-- Welcome Text -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-format-chat"></span>
                                    <?php esc_html_e( 'Welcome Text', 'pax-support-pro' ); ?>
                                    <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Custom welcome message shown when chat opens', 'pax-support-pro' ); ?>">?</span>
                                </label>
                                <p class="pax-form-description"><?php esc_html_e( 'Custom welcome message displayed when users open the chat. Leave empty for default message.', 'pax-support-pro' ); ?></p>
                                <textarea name="chat_welcome_text" rows="3" class="pax-text-input" style="font-family: inherit; resize: vertical;"><?php echo esc_textarea( $options['chat_welcome_text'] ?? '' ); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Animations Card -->
                    <div class="pax-card">
                        <div class="pax-card-header">
                            <span class="dashicons dashicons-image-flip-horizontal"></span>
                            <h2><?php esc_html_e( 'Chat Animations', 'pax-support-pro' ); ?></h2>
                        </div>
                        <div class="pax-card-body">
                            <!-- Enable Animations -->
                            <div class="pax-form-group">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div>
                                        <label class="pax-form-label">
                                            <span class="dashicons dashicons-controls-play"></span>
                                            <?php esc_html_e( 'Enable Animations', 'pax-support-pro' ); ?>
                                            <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Enable smooth open/close animations', 'pax-support-pro' ); ?>">?</span>
                                        </label>
                                        <p class="pax-form-description"><?php esc_html_e( 'Enable smooth animations when opening and closing the chat window.', 'pax-support-pro' ); ?></p>
                                    </div>
                                    <label class="pax-toggle">
                                        <input type="checkbox" name="chat_animations_enabled" <?php checked( $options['chat_animations_enabled'] ?? 1 ); ?>>
                                        <span class="pax-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Animation Duration -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-clock"></span>
                                    <?php esc_html_e( 'Animation Duration', 'pax-support-pro' ); ?>
                                    <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Duration of open/close animations in milliseconds', 'pax-support-pro' ); ?>">?</span>
                                </label>
                                <p class="pax-form-description"><?php esc_html_e( 'Duration of animations in milliseconds (100-1000ms).', 'pax-support-pro' ); ?></p>
                                <div class="pax-range-wrapper">
                                    <div class="pax-range-header">
                                        <span><?php esc_html_e( 'Duration', 'pax-support-pro' ); ?></span>
                                        <span class="pax-range-value"></span>
                                    </div>
                                    <input type="range" name="chat_animation_duration" min="100" max="1000" step="50" value="<?php echo esc_attr( $options['chat_animation_duration'] ?? 300 ); ?>" class="pax-range-slider" data-unit="ms">
                                </div>
                            </div>

                            <!-- Animation Easing -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-chart-line"></span>
                                    <?php esc_html_e( 'Animation Easing', 'pax-support-pro' ); ?>
                                    <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Animation timing function', 'pax-support-pro' ); ?>">?</span>
                                </label>
                                <p class="pax-form-description"><?php esc_html_e( 'Choose the animation timing function for smooth transitions.', 'pax-support-pro' ); ?></p>
                                <select name="chat_animation_easing" class="pax-select">
                                    <option value="ease" <?php selected( $options['chat_animation_easing'] ?? 'ease', 'ease' ); ?>><?php esc_html_e( 'Ease (Default)', 'pax-support-pro' ); ?></option>
                                    <option value="ease-in" <?php selected( $options['chat_animation_easing'] ?? 'ease', 'ease-in' ); ?>><?php esc_html_e( 'Ease In', 'pax-support-pro' ); ?></option>
                                    <option value="ease-out" <?php selected( $options['chat_animation_easing'] ?? 'ease', 'ease-out' ); ?>><?php esc_html_e( 'Ease Out', 'pax-support-pro' ); ?></option>
                                    <option value="ease-in-out" <?php selected( $options['chat_animation_easing'] ?? 'ease', 'ease-in-out' ); ?>><?php esc_html_e( 'Ease In-Out', 'pax-support-pro' ); ?></option>
                                    <option value="cubic-bezier(0.4, 0, 0.2, 1)" <?php selected( $options['chat_animation_easing'] ?? 'ease', 'cubic-bezier(0.4, 0, 0.2, 1)' ); ?>><?php esc_html_e( 'Material Design', 'pax-support-pro' ); ?></option>
                                    <option value="cubic-bezier(0.34, 1.56, 0.64, 1)" <?php selected( $options['chat_animation_easing'] ?? 'ease', 'cubic-bezier(0.34, 1.56, 0.64, 1)' ); ?>><?php esc_html_e( 'Bounce', 'pax-support-pro' ); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Layout Settings Card -->
                    <div class="pax-card">
                        <div class="pax-card-header">
                            <span class="dashicons dashicons-layout"></span>
                            <h2><?php esc_html_e( 'Layout & Position', 'pax-support-pro' ); ?></h2>
                        </div>
                        <div class="pax-card-body">
                            <!-- Launcher Position -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-move"></span>
                                    <?php esc_html_e( 'Chat Launcher Position', 'pax-support-pro' ); ?>
                                    <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Where the chat launcher appears on your site', 'pax-support-pro' ); ?>">?</span>
                                </label>
                                <p class="pax-form-description"><?php esc_html_e( 'Selects where the chat launcher button appears on your website.', 'pax-support-pro' ); ?></p>
                                <select name="launcher_position" class="pax-select">
                                    <option value="bottom-left" <?php selected( $options['launcher_position'], 'bottom-left' ); ?>><?php esc_html_e( 'Bottom Left', 'pax-support-pro' ); ?></option>
                                    <option value="bottom-right" <?php selected( $options['launcher_position'], 'bottom-right' ); ?>><?php esc_html_e( 'Bottom Right', 'pax-support-pro' ); ?></option>
                                    <option value="top-left" <?php selected( $options['launcher_position'], 'top-left' ); ?>><?php esc_html_e( 'Top Left', 'pax-support-pro' ); ?></option>
                                    <option value="top-right" <?php selected( $options['launcher_position'], 'top-right' ); ?>><?php esc_html_e( 'Top Right', 'pax-support-pro' ); ?></option>
                                </select>
                            </div>

                            <!-- Ticket Cooldown -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-clock"></span>
                                    <?php esc_html_e( 'Ticket Cooldown (days)', 'pax-support-pro' ); ?>
                                    <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Days users must wait between tickets (0 = disabled)', 'pax-support-pro' ); ?>">?</span>
                                </label>
                                <p class="pax-form-description"><?php esc_html_e( 'Number of days users must wait before creating another ticket. Set to 0 to disable.', 'pax-support-pro' ); ?></p>
                                <div class="pax-range-wrapper">
                                    <div class="pax-range-header">
                                        <span><?php esc_html_e( 'Days', 'pax-support-pro' ); ?></span>
                                        <span class="pax-range-value"></span>
                                    </div>
                                    <input type="range" name="ticket_cooldown_days" min="0" max="30" step="1" value="<?php echo intval( $options['ticket_cooldown_days'] ); ?>" class="pax-range-slider" data-unit=" days">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System & Maintenance -->
                    <div class="pax-card">
                        <div class="pax-card-header">
                            <span class="dashicons dashicons-admin-tools"></span>
                            <h2><?php esc_html_e( 'System & Maintenance', 'pax-support-pro' ); ?></h2>
                        </div>
                        <div class="pax-card-body">
                            <!-- Plugin Updates -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-update"></span>
                                    <?php esc_html_e( 'Plugin Updates', 'pax-support-pro' ); ?>
                                    <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Check for and install plugin updates from GitHub', 'pax-support-pro' ); ?>">?</span>
                                </label>
                                <p class="pax-form-description"><?php esc_html_e( 'Current version:', 'pax-support-pro' ); ?> <strong><?php echo esc_html( PAX_SUP_VER ); ?></strong></p>
                                <div style="margin-top: 12px;">
                                    <button type="button" id="pax-check-updates" class="pax-btn pax-btn-secondary">
                                        <span class="dashicons dashicons-update"></span>
                                        <?php esc_html_e( 'Check for Updates', 'pax-support-pro' ); ?>
                                    </button>
                                    <span id="pax-update-status" style="margin-left: 12px; display: none;"></span>
                                </div>
                                <div id="pax-update-info" style="margin-top: 12px; display: none;"></div>
                            </div>

                            <!-- Auto Update Settings -->
                            <div class="pax-form-group">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div>
                                        <label class="pax-form-label">
                                            <span class="dashicons dashicons-update-alt"></span>
                                            <?php esc_html_e( 'Auto Update', 'pax-support-pro' ); ?>
                                            <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'Automatically check for updates in the background', 'pax-support-pro' ); ?>">?</span>
                                        </label>
                                        <p class="pax-form-description"><?php esc_html_e( 'Enable automatic background update checks.', 'pax-support-pro' ); ?></p>
                                    </div>
                                    <label class="pax-toggle">
                                        <input type="checkbox" name="auto_update_enabled" <?php checked( $options['auto_update_enabled'] ); ?>>
                                        <span class="pax-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Update Check Frequency -->
                            <div class="pax-form-group">
                                <label class="pax-form-label">
                                    <span class="dashicons dashicons-clock"></span>
                                    <?php esc_html_e( 'Update Check Frequency', 'pax-support-pro' ); ?>
                                    <span class="pax-tooltip" data-tooltip="<?php esc_attr_e( 'How often to check for updates automatically', 'pax-support-pro' ); ?>">?</span>
                                </label>
                                <select name="update_check_frequency" class="pax-select">
                                    <option value="daily" <?php selected( $options['update_check_frequency'] ?? 'daily', 'daily' ); ?>><?php esc_html_e( 'Daily', 'pax-support-pro' ); ?></option>
                                    <option value="weekly" <?php selected( $options['update_check_frequency'] ?? 'daily', 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'pax-support-pro' ); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Menu Items -->
                    <div class="pax-card">
                        <div class="pax-card-header">
                            <span class="dashicons dashicons-menu-alt"></span>
                            <h2><?php esc_html_e( 'Chat Menu Items', 'pax-support-pro' ); ?></h2>
                        </div>
                        <div class="pax-card-body">
                            <p class="pax-form-description" style="margin-bottom: 20px;">
                                <?php esc_html_e( 'Customize the menu items shown in the chat widget. Click on a label to edit it inline. Changes sync in real-time.', 'pax-support-pro' ); ?>
                            </p>
                            
                            <div id="pax-menu-items-list">
                                <?php
                                $menu_items = isset( $options['chat_menu_items'] ) && is_array( $options['chat_menu_items'] )
                                    ? $options['chat_menu_items']
                                    : pax_sup_default_menu_items();
                                
                                $menu_icons_map = array(
                                    'chat'          => 'dashicons-format-chat',
                                    'ticket'        => 'dashicons-tickets-alt',
                                    'help'          => 'dashicons-editor-help',
                                    'speed'         => 'dashicons-performance',
                                    'agent'         => 'dashicons-admin-users',
                                    'whatsnew'      => 'dashicons-megaphone',
                                    'troubleshooter'=> 'dashicons-admin-tools',
                                    'diag'          => 'dashicons-chart-line',
                                    'callback'      => 'dashicons-phone',
                                    'order'         => 'dashicons-cart',
                                    'myreq'         => 'dashicons-list-view',
                                    'feedback'      => 'dashicons-star-filled',
                                    'donate'        => 'dashicons-heart',
                                );
                                
                                foreach ( $menu_items as $key => $item ) :
                                    $label = isset( $item['label'] ) ? $item['label'] : ucfirst( $key );
                                    $visible = isset( $item['visible'] ) ? $item['visible'] : 1;
                                    $icon_class = isset( $menu_icons_map[ $key ] ) ? $menu_icons_map[ $key ] : 'dashicons-admin-generic';
                                ?>
                                <div class="pax-menu-item" data-key="<?php echo esc_attr( $key ); ?>">
                                    <div class="pax-menu-item-icon">
                                        <span class="dashicons <?php echo esc_attr( $icon_class ); ?>"></span>
                                    </div>
                                    <div class="pax-menu-item-content">
                                        <input type="text" 
                                               name="menu_items[<?php echo esc_attr( $key ); ?>][label]" 
                                               value="<?php echo esc_attr( $label ); ?>" 
                                               class="pax-menu-item-label"
                                               data-original="<?php echo esc_attr( $label ); ?>"
                                               placeholder="<?php echo esc_attr( ucfirst( $key ) ); ?>">
                                        <span class="pax-menu-item-key"><?php echo esc_html( $key ); ?></span>
                                    </div>
                                    <label class="pax-toggle pax-menu-item-toggle">
                                        <input type="checkbox" 
                                               name="menu_items[<?php echo esc_attr( $key ); ?>][visible]" 
                                               value="1"
                                               <?php checked( $visible ); ?>>
                                        <span class="pax-toggle-slider"></span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Enhanced Live Preview Panel -->
                <?php require_once PAX_SUP_DIR . 'admin/live-preview/live-preview.html'; ?>
            </div>

            <!-- Action Buttons -->
            <div class="pax-actions">
                <button type="submit" class="pax-btn pax-btn-primary">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e( 'Save Changes', 'pax-support-pro' ); ?>
                </button>
                <button type="button" id="pax-reset-defaults" class="pax-btn pax-btn-danger">
                    <span class="dashicons dashicons-image-rotate"></span>
                    <?php esc_html_e( 'Reset to Defaults', 'pax-support-pro' ); ?>
                </button>
            </div>
        </form>

        <!-- Reset Confirmation Modal -->
        <div id="pax-reset-modal" class="pax-modal-overlay">
            <div class="pax-modal">
                <div class="pax-modal-header">
                    <span class="dashicons dashicons-warning"></span>
                    <h3><?php esc_html_e( 'Reset to Default Settings?', 'pax-support-pro' ); ?></h3>
                </div>
                <div class="pax-modal-body">
                    <p><?php esc_html_e( 'This will restore all settings to their default values. Your current configuration will be lost.', 'pax-support-pro' ); ?></p>
                    <p><strong><?php esc_html_e( 'This action cannot be undone.', 'pax-support-pro' ); ?></strong></p>
                </div>
                <div class="pax-modal-actions">
                    <button type="button" id="pax-cancel-reset" class="pax-btn pax-btn-secondary">
                        <?php esc_html_e( 'Cancel', 'pax-support-pro' ); ?>
                    </button>
                    <button type="button" id="pax-confirm-reset" class="pax-btn pax-btn-danger">
                        <?php esc_html_e( 'Reset Settings', 'pax-support-pro' ); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php
}
