<?php
/**
 * Scheduler administration.
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function pax_sup_render_scheduler_page() {
    if ( ! current_user_can( pax_sup_get_console_capability() ) ) {
        return;
    }

    $settings   = pax_sup_get_scheduler_settings();
    $all_users  = get_users(
        array(
            'fields'  => array( 'ID', 'display_name', 'user_email' ),
            'orderby' => 'display_name',
            'order'   => 'ASC',
        )
    );
    $timezone   = isset( $settings['timezone'] ) ? $settings['timezone'] : wp_timezone_string();
    $start      = isset( $settings['hours']['start'] ) ? $settings['hours']['start'] : '09:00';
    $end        = isset( $settings['hours']['end'] ) ? $settings['hours']['end'] : '17:00';
    $slots      = isset( $settings['slots_per_hour'] ) ? (int) $settings['slots_per_hour'] : 1;
    $reminder   = isset( $settings['reminder_lead'] ) ? (int) $settings['reminder_lead'] : 60;
    $agents     = isset( $settings['agents'] ) ? (array) $settings['agents'] : array();
    $table      = pax_sup_get_schedules_table();
    global $wpdb;

    $schedules = $wpdb->get_results(
        "SELECT * FROM {$table} ORDER BY schedule_date DESC, schedule_time DESC LIMIT 100",
        ARRAY_A
    );

    // Calculate analytics metrics
    $today = current_time( 'Y-m-d' );
    $count_today = 0;
    $count_pending = 0;
    $count_completed = 0;
    $count_active = 0;

    foreach ( $schedules as $schedule ) {
        if ( $schedule['schedule_date'] === $today ) {
            $count_today++;
        }
        if ( $schedule['status'] === 'pending' ) {
            $count_pending++;
        }
        if ( $schedule['status'] === 'done' ) {
            $count_completed++;
        }
        if ( in_array( $schedule['status'], array( 'pending', 'confirmed' ), true ) ) {
            $count_active++;
        }
    }

    ?>
    <div class="wrap">
        <?php if ( isset( $_GET['pax_scheduler_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
            <?php pax_sup_admin_notice( __( 'Scheduler settings updated.', 'pax-support-pro' ) ); ?>
        <?php endif; ?>

        <?php if ( isset( $_GET['pax_scheduler_status'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
            <?php pax_sup_admin_notice( __( 'Schedule updated successfully.', 'pax-support-pro' ) ); ?>
        <?php endif; ?>

        <?php if ( isset( $_GET['pax_scheduler_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
            <?php pax_sup_admin_notice( __( 'Unable to update the schedule.', 'pax-support-pro' ), 'error' ); ?>
        <?php endif; ?>

        <div class="scheduler-modern">
            <!-- Header -->
            <div class="scheduler-header">
                <div class="scheduler-header-left">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <h1><?php esc_html_e( 'Callback Scheduler', 'pax-support-pro' ); ?></h1>
                    <button class="scheduler-help-btn" id="scheduler-help" type="button">
                        <span class="dashicons dashicons-editor-help"></span>
                    </button>
                </div>
                <div class="scheduler-header-right">
                    <button class="scheduler-btn scheduler-btn-primary" type="button" id="schedule-callback-btn">
                        <span class="dashicons dashicons-phone"></span>
                        <?php esc_html_e( 'Schedule Callback', 'pax-support-pro' ); ?>
                    </button>
                    <button class="scheduler-btn scheduler-btn-refresh" type="button" onclick="location.reload();">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e( 'Refresh', 'pax-support-pro' ); ?>
                    </button>
                </div>
            </div>

            <!-- Analytics Cards -->
            <div class="scheduler-analytics">
                <div class="scheduler-metric-card">
                    <div class="scheduler-metric-icon scheduler-icon-today">
                        <span class="dashicons dashicons-calendar"></span>
                    </div>
                    <div class="scheduler-metric-content">
                        <div class="scheduler-metric-value"><?php echo esc_html( $count_today ); ?></div>
                        <div class="scheduler-metric-label"><?php esc_html_e( 'Today', 'pax-support-pro' ); ?></div>
                    </div>
                </div>

                <div class="scheduler-metric-card">
                    <div class="scheduler-metric-icon scheduler-icon-pending">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="scheduler-metric-content">
                        <div class="scheduler-metric-value"><?php echo esc_html( $count_pending ); ?></div>
                        <div class="scheduler-metric-label"><?php esc_html_e( 'Pending', 'pax-support-pro' ); ?></div>
                    </div>
                </div>

                <div class="scheduler-metric-card">
                    <div class="scheduler-metric-icon scheduler-icon-completed">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="scheduler-metric-content">
                        <div class="scheduler-metric-value"><?php echo esc_html( $count_completed ); ?></div>
                        <div class="scheduler-metric-label"><?php esc_html_e( 'Completed', 'pax-support-pro' ); ?></div>
                    </div>
                </div>

                <div class="scheduler-metric-card">
                    <div class="scheduler-metric-icon scheduler-icon-active">
                        <span class="dashicons dashicons-phone"></span>
                    </div>
                    <div class="scheduler-metric-content">
                        <div class="scheduler-metric-value"><?php echo esc_html( $count_active ); ?></div>
                        <div class="scheduler-metric-label"><?php esc_html_e( 'Active', 'pax-support-pro' ); ?></div>
                    </div>
                </div>
            </div>

            <!-- Settings Section -->
            <div class="scheduler-callbacks-section" style="margin-bottom: 24px;">
                <div class="scheduler-section-header">
                    <h2><?php esc_html_e( 'Working Hours & Agents', 'pax-support-pro' ); ?></h2>
                </div>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'pax_sup_save_scheduler' ); ?>
                    <input type="hidden" name="action" value="pax_sup_save_scheduler_settings">
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Default timezone', 'pax-support-pro' ); ?></th>
                            <td>
                                <input type="text" class="regular-text" name="timezone" value="<?php echo esc_attr( $timezone ); ?>" required>
                                <p class="description"><?php esc_html_e( 'Used when visitors do not share a timezone.', 'pax-support-pro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Working hours', 'pax-support-pro' ); ?></th>
                            <td>
                                <label>
                                    <?php esc_html_e( 'Start', 'pax-support-pro' ); ?>
                                    <input type="time" name="hours[start]" value="<?php echo esc_attr( $start ); ?>">
                                </label>
                                &nbsp;
                                <label>
                                    <?php esc_html_e( 'End', 'pax-support-pro' ); ?>
                                    <input type="time" name="hours[end]" value="<?php echo esc_attr( $end ); ?>">
                                </label>
                                <p class="description"><?php esc_html_e( 'Visitors can only pick time slots within this range.', 'pax-support-pro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Slots per hour', 'pax-support-pro' ); ?></th>
                            <td>
                                <input type="number" name="slots_per_hour" value="<?php echo esc_attr( $slots ); ?>" min="1" max="12">
                                <p class="description"><?php esc_html_e( 'Maximum simultaneous callbacks per hour.', 'pax-support-pro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Reminder lead time (minutes)', 'pax-support-pro' ); ?></th>
                            <td>
                                <input type="number" name="reminder_lead" value="<?php echo esc_attr( $reminder ); ?>" min="15" step="5">
                                <p class="description"><?php esc_html_e( 'Send reminder emails this many minutes before the scheduled slot.', 'pax-support-pro' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Assign to agents', 'pax-support-pro' ); ?></th>
                            <td>
                                <select name="agents[]" multiple size="6" style="min-width:260px;">
                                    <?php foreach ( $all_users as $user ) : ?>
                                        <option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( in_array( $user->ID, $agents, true ) ); ?>>
                                            <?php echo esc_html( $user->display_name . ( $user->user_email ? ' (' . $user->user_email . ')' : '' ) ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Selected users receive callback notifications and appear in analytics.', 'pax-support-pro' ); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( __( 'Save scheduler settings', 'pax-support-pro' ) ); ?>
                </form>
            </div>

            <!-- Callbacks List Section -->
            <div class="scheduler-callbacks-section">
                <div class="scheduler-section-header">
                    <h2><?php esc_html_e( 'Upcoming Callbacks', 'pax-support-pro' ); ?></h2>
                    <div class="scheduler-filters">
                        <input type="search" class="scheduler-search" id="scheduler-search" placeholder="<?php esc_attr_e( 'Search...', 'pax-support-pro' ); ?>">
                        <select class="scheduler-filter-status" id="scheduler-filter-status">
                            <option value=""><?php esc_html_e( 'All Status', 'pax-support-pro' ); ?></option>
                            <option value="pending"><?php esc_html_e( 'Pending', 'pax-support-pro' ); ?></option>
                            <option value="confirmed"><?php esc_html_e( 'Confirmed', 'pax-support-pro' ); ?></option>
                            <option value="done"><?php esc_html_e( 'Done', 'pax-support-pro' ); ?></option>
                            <option value="canceled"><?php esc_html_e( 'Canceled', 'pax-support-pro' ); ?></option>
                        </select>
                    </div>
                </div>

                <div class="scheduler-callbacks-list" id="callbacks-list">
                    <?php if ( empty( $schedules ) ) : ?>
                        <div class="scheduler-empty-state">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <h3><?php esc_html_e( 'No Scheduled Callbacks', 'pax-support-pro' ); ?></h3>
                            <p><?php esc_html_e( 'Callbacks will appear here once visitors schedule them.', 'pax-support-pro' ); ?></p>
                        </div>
                    <?php else : ?>
                        <?php foreach ( $schedules as $schedule ) :
                            $schedule = pax_sup_prepare_schedule_row( $schedule );
                            $agent    = $schedule['agent_id'] ? get_userdata( $schedule['agent_id'] ) : null;
                            ?>
                            <div class="scheduler-callback-card" data-id="<?php echo esc_attr( $schedule['id'] ); ?>" data-status="<?php echo esc_attr( $schedule['status'] ); ?>">
                                <div class="callback-header">
                                    <span class="callback-id">#<?php echo esc_html( $schedule['id'] ); ?></span>
                                    <span class="callback-status status-<?php echo esc_attr( $schedule['status'] ); ?>">
                                        <?php echo esc_html( ucfirst( $schedule['status'] ) ); ?>
                                    </span>
                                </div>
                                <div class="callback-body">
                                    <div class="callback-datetime">
                                        <span class="dashicons dashicons-calendar"></span>
                                        <?php echo esc_html( $schedule['schedule_date'] . ' ' . $schedule['schedule_time'] ); ?>
                                    </div>
                                    <div class="callback-contact">
                                        <span class="dashicons dashicons-email"></span>
                                        <?php echo esc_html( $schedule['contact'] ); ?>
                                    </div>
                                    <?php if ( ! empty( $schedule['note'] ) ) : ?>
                                        <div class="callback-note">
                                            <?php echo esc_html( wp_trim_words( $schedule['note'], 20, '…' ) ); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="callback-footer">
                                    <div class="callback-agent">
                                        <?php if ( $agent instanceof WP_User ) : ?>
                                            <img src="<?php echo esc_url( get_avatar_url( $agent->ID, array( 'size' => 24 ) ) ); ?>" alt="">
                                            <span><?php echo esc_html( $agent->display_name ); ?></span>
                                        <?php else : ?>
                                            <span class="unassigned"><?php esc_html_e( 'Unassigned', 'pax-support-pro' ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="callback-actions">
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
                                            <?php wp_nonce_field( 'pax_sup_update_schedule_status' ); ?>
                                            <input type="hidden" name="action" value="pax_sup_update_schedule_status">
                                            <input type="hidden" name="schedule_id" value="<?php echo esc_attr( $schedule['id'] ); ?>">
                                            <select name="status" onchange="this.form.submit()" style="font-size:12px;padding:4px;">
                                                <?php foreach ( array( 'pending', 'confirmed', 'done', 'canceled' ) as $status ) : ?>
                                                    <option value="<?php echo esc_attr( $status ); ?>" <?php selected( $schedule['status'], $status ); ?>><?php echo esc_html( ucfirst( $status ) ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
                                            <?php wp_nonce_field( 'pax_sup_delete_schedule' ); ?>
                                            <input type="hidden" name="action" value="pax_sup_delete_schedule">
                                            <input type="hidden" name="schedule_id" value="<?php echo esc_attr( $schedule['id'] ); ?>">
                                            <button type="submit" class="btn-delete" onclick="return confirm('<?php echo esc_js( __( 'Delete this schedule?', 'pax-support-pro' ) ); ?>');">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Schedule Callback Modal -->
        <div class="scheduler-modal-overlay" id="schedule-callback-overlay"></div>
        <div class="scheduler-modal" id="schedule-callback-modal">
            <div class="scheduler-modal-header">
                <h3><?php esc_html_e( 'Schedule New Callback', 'pax-support-pro' ); ?></h3>
                <button class="scheduler-modal-close" id="schedule-callback-close" type="button">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
            <form method="post" action="" id="schedule-callback-form">
                <?php wp_nonce_field( 'pax_sup_schedule_callback' ); ?>
                <input type="hidden" name="action" value="pax_sup_schedule_callback">
                
                <div class="scheduler-modal-body">
                    <div class="scheduler-form-group">
                        <label for="callback-name"><?php esc_html_e( 'Customer Name', 'pax-support-pro' ); ?> <span class="required">*</span></label>
                        <input type="text" id="callback-name" name="name" required placeholder="<?php esc_attr_e( 'Enter customer name', 'pax-support-pro' ); ?>">
                    </div>
                    
                    <div class="scheduler-form-group">
                        <label for="callback-phone"><?php esc_html_e( 'Phone Number', 'pax-support-pro' ); ?> <span class="required">*</span></label>
                        <input type="tel" id="callback-phone" name="phone" required placeholder="<?php esc_attr_e( 'Enter phone number', 'pax-support-pro' ); ?>">
                    </div>
                    
                    <div class="scheduler-form-row">
                        <div class="scheduler-form-group">
                            <label for="callback-date"><?php esc_html_e( 'Date', 'pax-support-pro' ); ?> <span class="required">*</span></label>
                            <input type="date" id="callback-date" name="date" required min="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
                        </div>
                        
                        <div class="scheduler-form-group">
                            <label for="callback-time"><?php esc_html_e( 'Time', 'pax-support-pro' ); ?> <span class="required">*</span></label>
                            <input type="time" id="callback-time" name="time" required>
                        </div>
                    </div>
                    
                    <div class="scheduler-form-group">
                        <label for="callback-timezone"><?php esc_html_e( 'Timezone', 'pax-support-pro' ); ?></label>
                        <input type="text" id="callback-timezone" name="timezone" value="<?php echo esc_attr( $timezone ); ?>" readonly>
                    </div>
                    
                    <div class="scheduler-form-group">
                        <label for="callback-note"><?php esc_html_e( 'Note (Optional)', 'pax-support-pro' ); ?></label>
                        <textarea id="callback-note" name="note" rows="3" placeholder="<?php esc_attr_e( 'Add any additional notes...', 'pax-support-pro' ); ?>"></textarea>
                    </div>
                    
                    <div class="scheduler-info-box">
                        <span class="dashicons dashicons-info"></span>
                        <p><?php printf( esc_html__( 'Available hours: %s - %s', 'pax-support-pro' ), esc_html( $start ), esc_html( $end ) ); ?></p>
                    </div>
                </div>
                
                <div class="scheduler-modal-footer">
                    <button type="button" class="scheduler-btn scheduler-btn-secondary" id="schedule-callback-cancel">
                        <?php esc_html_e( 'Cancel', 'pax-support-pro' ); ?>
                    </button>
                    <button type="submit" class="scheduler-btn scheduler-btn-primary">
                        <span class="dashicons dashicons-yes"></span>
                        <?php esc_html_e( 'Schedule Callback', 'pax-support-pro' ); ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Help Tooltip Overlay -->
        <div class="scheduler-help-overlay" id="scheduler-help-overlay"></div>
        <div class="scheduler-help-tooltip" id="scheduler-help-tooltip">
            <div class="scheduler-help-header">
                <h3><?php esc_html_e( 'Scheduler Help', 'pax-support-pro' ); ?></h3>
                <button class="scheduler-help-close" id="scheduler-help-close" type="button">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
            <div class="scheduler-help-content">
                <h4><?php esc_html_e( 'Analytics Dashboard', 'pax-support-pro' ); ?></h4>
                <ul>
                    <li><strong><?php esc_html_e( 'Today:', 'pax-support-pro' ); ?></strong> <?php esc_html_e( 'Callbacks scheduled for today', 'pax-support-pro' ); ?></li>
                    <li><strong><?php esc_html_e( 'Pending:', 'pax-support-pro' ); ?></strong> <?php esc_html_e( 'Callbacks awaiting confirmation', 'pax-support-pro' ); ?></li>
                    <li><strong><?php esc_html_e( 'Completed:', 'pax-support-pro' ); ?></strong> <?php esc_html_e( 'Successfully completed callbacks', 'pax-support-pro' ); ?></li>
                    <li><strong><?php esc_html_e( 'Active:', 'pax-support-pro' ); ?></strong> <?php esc_html_e( 'Pending + Confirmed callbacks', 'pax-support-pro' ); ?></li>
                </ul>

                <h4><?php esc_html_e( 'Status Badges', 'pax-support-pro' ); ?></h4>
                <ul>
                    <li><strong><?php esc_html_e( 'Pending:', 'pax-support-pro' ); ?></strong> <?php esc_html_e( 'Awaiting agent confirmation', 'pax-support-pro' ); ?></li>
                    <li><strong><?php esc_html_e( 'Confirmed:', 'pax-support-pro' ); ?></strong> <?php esc_html_e( 'Agent confirmed the callback', 'pax-support-pro' ); ?></li>
                    <li><strong><?php esc_html_e( 'Done:', 'pax-support-pro' ); ?></strong> <?php esc_html_e( 'Callback completed successfully', 'pax-support-pro' ); ?></li>
                    <li><strong><?php esc_html_e( 'Canceled:', 'pax-support-pro' ); ?></strong> <?php esc_html_e( 'Callback was canceled', 'pax-support-pro' ); ?></li>
                </ul>

                <h4><?php esc_html_e( 'Managing Callbacks', 'pax-support-pro' ); ?></h4>
                <ul>
                    <li><?php esc_html_e( 'Use the status dropdown to update callback status', 'pax-support-pro' ); ?></li>
                    <li><?php esc_html_e( 'Click the trash icon to delete a callback', 'pax-support-pro' ); ?></li>
                    <li><?php esc_html_e( 'Use search and filters to find specific callbacks', 'pax-support-pro' ); ?></li>
                    <li><?php esc_html_e( 'Double-click notes to edit inline', 'pax-support-pro' ); ?></li>
                    <li><?php esc_html_e( 'Drag and drop cards to reorder', 'pax-support-pro' ); ?></li>
                </ul>

                <h4><?php esc_html_e( 'Keyboard Shortcuts', 'pax-support-pro' ); ?></h4>
                <ul>
                    <li><strong>Ctrl/Cmd + K:</strong> <?php esc_html_e( 'Focus search box', 'pax-support-pro' ); ?></li>
                    <li><strong>Ctrl/Cmd + R:</strong> <?php esc_html_e( 'Refresh page', 'pax-support-pro' ); ?></li>
                    <li><strong>?:</strong> <?php esc_html_e( 'Show this help', 'pax-support-pro' ); ?></li>
                    <li><strong>Escape:</strong> <?php esc_html_e( 'Close help or cancel editing', 'pax-support-pro' ); ?></li>
                    <li><strong>Ctrl/Cmd + Enter:</strong> <?php esc_html_e( 'Save inline edit', 'pax-support-pro' ); ?></li>
                </ul>
            </div>
        </div>
    </div>
    <?php
}

function pax_sup_handle_scheduler_save() {
    if ( ! current_user_can( pax_sup_get_console_capability() ) ) {
        wp_die( esc_html__( 'You do not have permission to manage the scheduler.', 'pax-support-pro' ) );
    }

    check_admin_referer( 'pax_sup_save_scheduler' );

    $timezone = sanitize_text_field( wp_unslash( $_POST['timezone'] ?? '' ) );
    $hours    = isset( $_POST['hours'] ) && is_array( $_POST['hours'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['hours'] ) ) : array();
    $slots    = isset( $_POST['slots_per_hour'] ) ? (int) wp_unslash( $_POST['slots_per_hour'] ) : 1;
    $reminder = isset( $_POST['reminder_lead'] ) ? (int) wp_unslash( $_POST['reminder_lead'] ) : 60;
    $agents   = isset( $_POST['agents'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['agents'] ) ) : array();

    pax_sup_save_scheduler_settings(
        array(
            'timezone'       => $timezone,
            'hours'          => $hours,
            'slots_per_hour' => $slots,
            'agents'         => $agents,
            'reminder_lead'  => $reminder,
        )
    );

    wp_safe_redirect( add_query_arg( 'pax_scheduler_saved', '1', admin_url( 'admin.php?page=pax-support-scheduler' ) ) );
    exit;
}
add_action( 'admin_post_pax_sup_save_scheduler_settings', 'pax_sup_handle_scheduler_save' );

function pax_sup_handle_schedule_status_update() {
    if ( ! current_user_can( pax_sup_get_console_capability() ) ) {
        wp_die( esc_html__( 'You do not have permission to manage schedules.', 'pax-support-pro' ) );
    }

    check_admin_referer( 'pax_sup_update_schedule_status' );

    $schedule_id = isset( $_POST['schedule_id'] ) ? (int) wp_unslash( $_POST['schedule_id'] ) : 0;
    $status      = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
    $allowed     = array( 'pending', 'confirmed', 'done', 'canceled' );

    if ( ! $schedule_id || ! in_array( $status, $allowed, true ) ) {
        wp_safe_redirect( add_query_arg( 'pax_scheduler_error', '1', admin_url( 'admin.php?page=pax-support-scheduler' ) ) );
        exit;
    }

    global $wpdb;
    $table    = pax_sup_get_schedules_table();
    $schedule = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $schedule_id ), ARRAY_A );

    if ( empty( $schedule ) ) {
        wp_safe_redirect( add_query_arg( 'pax_scheduler_error', '1', admin_url( 'admin.php?page=pax-support-scheduler' ) ) );
        exit;
    }

    $wpdb->update(
        $table,
        array(
            'status'      => $status,
            'updated_at'  => current_time( 'mysql' ),
            'reminder_sent' => ( 'pending' === $status || 'confirmed' === $status ) ? (int) $schedule['reminder_sent'] : 1,
        ),
        array( 'id' => $schedule_id ),
        array( '%s', '%s', '%d' ),
        array( '%d' )
    );

    $schedule['status'] = $status;

    pax_sup_notify_schedule_event( $schedule, 'canceled' === $status ? 'canceled' : 'updated' );

    wp_safe_redirect( add_query_arg( 'pax_scheduler_status', '1', admin_url( 'admin.php?page=pax-support-scheduler' ) ) );
    exit;
}
add_action( 'admin_post_pax_sup_update_schedule_status', 'pax_sup_handle_schedule_status_update' );

function pax_sup_handle_schedule_delete() {
    if ( ! current_user_can( pax_sup_get_console_capability() ) ) {
        wp_die( esc_html__( 'You do not have permission to manage schedules.', 'pax-support-pro' ) );
    }

    check_admin_referer( 'pax_sup_delete_schedule' );

    $schedule_id = isset( $_POST['schedule_id'] ) ? (int) wp_unslash( $_POST['schedule_id'] ) : 0;

    if ( ! $schedule_id ) {
        wp_safe_redirect( add_query_arg( 'pax_scheduler_error', '1', admin_url( 'admin.php?page=pax-support-scheduler' ) ) );
        exit;
    }

    global $wpdb;
    $table    = pax_sup_get_schedules_table();
    $schedule = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $schedule_id ), ARRAY_A );

    if ( $schedule ) {
        $wpdb->delete( $table, array( 'id' => $schedule_id ), array( '%d' ) );
        $schedule['status'] = 'canceled';
        pax_sup_notify_schedule_event( $schedule, 'canceled' );
    }

    wp_safe_redirect( add_query_arg( 'pax_scheduler_status', '1', admin_url( 'admin.php?page=pax-support-scheduler' ) ) );
    exit;
}
add_action( 'admin_post_pax_sup_delete_schedule', 'pax_sup_handle_schedule_delete' );

/**
 * AJAX Handlers for Phase 3
 */

/**
 * AJAX: Get all callbacks
 */
function pax_sup_ajax_get_callbacks() {
    check_ajax_referer( 'pax_scheduler_nonce', 'nonce' );

    if ( ! current_user_can( pax_sup_get_console_capability() ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied', 'pax-support-pro' ) ) );
    }

    global $wpdb;
    $table = pax_sup_get_schedules_table();

    $schedules = $wpdb->get_results(
        "SELECT * FROM {$table} ORDER BY schedule_date DESC, schedule_time DESC LIMIT 100",
        ARRAY_A
    );

    // Calculate analytics
    $today = current_time( 'Y-m-d' );
    $analytics = array(
        'today' => 0,
        'pending' => 0,
        'completed' => 0,
        'active' => 0,
    );

    $callbacks = array();
    foreach ( $schedules as $schedule ) {
        $schedule = pax_sup_prepare_schedule_row( $schedule );
        $callbacks[] = $schedule;

        if ( $schedule['schedule_date'] === $today ) {
            $analytics['today']++;
        }
        if ( $schedule['status'] === 'pending' ) {
            $analytics['pending']++;
        }
        if ( $schedule['status'] === 'done' ) {
            $analytics['completed']++;
        }
        if ( in_array( $schedule['status'], array( 'pending', 'confirmed' ), true ) ) {
            $analytics['active']++;
        }
    }

    wp_send_json_success( array(
        'callbacks' => $callbacks,
        'analytics' => $analytics,
    ) );
}
add_action( 'wp_ajax_pax_sup_get_callbacks', 'pax_sup_ajax_get_callbacks' );

/**
 * AJAX: Update callback status
 */
function pax_sup_ajax_update_status() {
    check_ajax_referer( 'pax_scheduler_nonce', 'nonce' );

    if ( ! current_user_can( pax_sup_get_console_capability() ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied', 'pax-support-pro' ) ) );
    }

    $schedule_id = isset( $_POST['schedule_id'] ) ? (int) $_POST['schedule_id'] : 0;
    $status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
    $allowed = array( 'pending', 'confirmed', 'done', 'canceled' );

    if ( ! $schedule_id || ! in_array( $status, $allowed, true ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid parameters', 'pax-support-pro' ) ) );
    }

    global $wpdb;
    $table = pax_sup_get_schedules_table();
    $schedule = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $schedule_id ), ARRAY_A );

    if ( empty( $schedule ) ) {
        wp_send_json_error( array( 'message' => __( 'Callback not found', 'pax-support-pro' ) ) );
    }

    $wpdb->update(
        $table,
        array(
            'status' => $status,
            'updated_at' => current_time( 'mysql' ),
            'reminder_sent' => ( 'pending' === $status || 'confirmed' === $status ) ? (int) $schedule['reminder_sent'] : 1,
        ),
        array( 'id' => $schedule_id ),
        array( '%s', '%s', '%d' ),
        array( '%d' )
    );

    $schedule['status'] = $status;
    pax_sup_notify_schedule_event( $schedule, 'canceled' === $status ? 'canceled' : 'updated' );

    wp_send_json_success( array(
        'message' => __( 'Status updated successfully', 'pax-support-pro' ),
        'callback' => pax_sup_prepare_schedule_row( $schedule ),
    ) );
}
add_action( 'wp_ajax_pax_sup_update_status', 'pax_sup_ajax_update_status' );

/**
 * AJAX: Delete callback
 */
function pax_sup_ajax_delete_callback() {
    check_ajax_referer( 'pax_scheduler_nonce', 'nonce' );

    if ( ! current_user_can( pax_sup_get_console_capability() ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied', 'pax-support-pro' ) ) );
    }

    $schedule_id = isset( $_POST['schedule_id'] ) ? (int) $_POST['schedule_id'] : 0;

    if ( ! $schedule_id ) {
        wp_send_json_error( array( 'message' => __( 'Invalid callback ID', 'pax-support-pro' ) ) );
    }

    global $wpdb;
    $table = pax_sup_get_schedules_table();
    $schedule = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $schedule_id ), ARRAY_A );

    if ( ! $schedule ) {
        wp_send_json_error( array( 'message' => __( 'Callback not found', 'pax-support-pro' ) ) );
    }

    $wpdb->delete( $table, array( 'id' => $schedule_id ), array( '%d' ) );
    $schedule['status'] = 'canceled';
    pax_sup_notify_schedule_event( $schedule, 'canceled' );

    wp_send_json_success( array(
        'message' => __( 'Callback deleted successfully', 'pax-support-pro' ),
    ) );
}
add_action( 'wp_ajax_pax_sup_delete_callback', 'pax_sup_ajax_delete_callback' );

/**
 * AJAX: Update callback note (inline edit)
 */
function pax_sup_ajax_update_note() {
    check_ajax_referer( 'pax_scheduler_nonce', 'nonce' );

    if ( ! current_user_can( pax_sup_get_console_capability() ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied', 'pax-support-pro' ) ) );
    }

    $schedule_id = isset( $_POST['schedule_id'] ) ? (int) $_POST['schedule_id'] : 0;
    $note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

    if ( ! $schedule_id ) {
        wp_send_json_error( array( 'message' => __( 'Invalid callback ID', 'pax-support-pro' ) ) );
    }

    global $wpdb;
    $table = pax_sup_get_schedules_table();

    $result = $wpdb->update(
        $table,
        array(
            'note' => $note,
            'updated_at' => current_time( 'mysql' ),
        ),
        array( 'id' => $schedule_id ),
        array( '%s', '%s' ),
        array( '%d' )
    );

    if ( false === $result ) {
        wp_send_json_error( array( 'message' => __( 'Failed to update note', 'pax-support-pro' ) ) );
    }

    wp_send_json_success( array(
        'message' => __( 'Note updated successfully', 'pax-support-pro' ),
        'note' => $note,
    ) );
}
add_action( 'wp_ajax_pax_sup_update_note', 'pax_sup_ajax_update_note' );

/**
 * AJAX: Reorder callbacks
 */
function pax_sup_ajax_reorder_callbacks() {
    check_ajax_referer( 'pax_scheduler_nonce', 'nonce' );

    if ( ! current_user_can( pax_sup_get_console_capability() ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied', 'pax-support-pro' ) ) );
    }

    $order = isset( $_POST['order'] ) ? array_map( 'intval', (array) $_POST['order'] ) : array();

    if ( empty( $order ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid order data', 'pax-support-pro' ) ) );
    }

    global $wpdb;
    $table = pax_sup_get_schedules_table();

    // Update sort order for each callback
    foreach ( $order as $index => $schedule_id ) {
        $wpdb->update(
            $table,
            array( 'sort_order' => $index ),
            array( 'id' => $schedule_id ),
            array( '%d' ),
            array( '%d' )
        );
    }

    wp_send_json_success( array(
        'message' => __( 'Order updated successfully', 'pax-support-pro' ),
    ) );
}
add_action( 'wp_ajax_pax_sup_reorder_callbacks', 'pax_sup_ajax_reorder_callbacks' );

/**
 * AJAX: Schedule callback from admin
 */
function pax_sup_ajax_schedule_callback_admin() {
    check_ajax_referer( 'pax_scheduler_nonce', 'nonce' );

    if ( ! current_user_can( pax_sup_get_console_capability() ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied', 'pax-support-pro' ) ) );
    }

    $options = pax_sup_get_options();
    if ( empty( $options['callback_enabled'] ) ) {
        wp_send_json_error( array( 'message' => __( 'Callback scheduling is currently disabled. Please enable it in settings.', 'pax-support-pro' ) ) );
    }

    $name = pax_sup_trim( $_POST['name'] ?? '', 120 );
    $phone = pax_sup_trim( $_POST['phone'] ?? '', 40 );
    $date = sanitize_text_field( $_POST['date'] ?? '' );
    $time = sanitize_text_field( $_POST['time'] ?? '' );
    $timezone = sanitize_text_field( $_POST['timezone'] ?? wp_timezone_string() );
    $note = pax_sup_trim( $_POST['note'] ?? '', 400 );

    // Validation
    if ( empty( $name ) || empty( $phone ) ) {
        wp_send_json_error( array( 'message' => __( 'Name and phone number are required.', 'pax-support-pro' ) ) );
    }

    if ( empty( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        wp_send_json_error( array( 'message' => __( 'Please provide a valid date.', 'pax-support-pro' ) ) );
    }

    if ( empty( $time ) || ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
        wp_send_json_error( array( 'message' => __( 'Please provide a valid time.', 'pax-support-pro' ) ) );
    }

    $settings = pax_sup_get_scheduler_settings();
    
    // Validate time is within allowed hours
    if ( ! pax_sup_schedule_within_hours( $time, $settings ) ) {
        wp_send_json_error( array( 'message' => __( 'Selected time is outside working hours.', 'pax-support-pro' ) ) );
    }

    // Check if time is in the future
    $timestamp = pax_sup_schedule_datetime_to_timestamp( $date, $time, $timezone );
    if ( ! $timestamp || $timestamp < time() ) {
        wp_send_json_error( array( 'message' => __( 'Please select a future time.', 'pax-support-pro' ) ) );
    }

    // Create contact string
    $contact = $name . ' — ' . $phone;

    // Assign agent
    $agent_id = pax_sup_schedule_assign_agent( $settings );

    global $wpdb;
    $table = pax_sup_get_schedules_table();

    $inserted = $wpdb->insert(
        $table,
        array(
            'user_id'       => 0, // Admin-created, no user
            'agent_id'      => $agent_id,
            'schedule_date' => $date,
            'schedule_time' => $time,
            'timezone'      => $timezone,
            'contact'       => $contact,
            'note'          => $note,
            'status'        => 'pending',
            'created_at'    => current_time( 'mysql' ),
            'updated_at'    => current_time( 'mysql' ),
        ),
        array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
    );

    if ( false === $inserted ) {
        wp_send_json_error( array( 'message' => __( 'Failed to schedule callback. Please try again.', 'pax-support-pro' ) ) );
    }

    $schedule = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $wpdb->insert_id ), ARRAY_A );

    if ( $schedule ) {
        pax_sup_notify_schedule_event( $schedule, 'created' );
    }

    wp_send_json_success( array(
        'message' => __( 'Callback scheduled successfully!', 'pax-support-pro' ),
        'schedule' => $schedule,
    ) );
}
add_action( 'wp_ajax_pax_sup_schedule_callback_admin', 'pax_sup_ajax_schedule_callback_admin' );
