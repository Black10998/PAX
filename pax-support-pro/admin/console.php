<?php
/**
 * Admin console view.
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function pax_sup_render_console() {
    if ( ! current_user_can( pax_sup_get_console_capability() ) ) {
        return;
    }

    pax_sup_ticket_prepare_tables();

    global $wpdb;
    $table = pax_sup_get_ticket_table();

    $search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $ticket_id = isset( $_GET['ticket'] ) ? absint( $_GET['ticket'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    if ( $search ) {
        $like = '%' . $wpdb->esc_like( $search ) . '%';
        $sql  = $wpdb->prepare(
            "SELECT id, subject, status, created_at, updated_at FROM {$table} WHERE subject LIKE %s OR message LIKE %s ORDER BY updated_at DESC LIMIT 50",
            $like,
            $like
        );
    } else {
        $sql = "SELECT id, subject, status, created_at, updated_at FROM {$table} ORDER BY updated_at DESC LIMIT 50";
    }

    $rows    = $wpdb->get_results( $sql );
    $metrics = pax_sup_get_server_metrics();

    $active_ticket = $ticket_id ? pax_sup_ticket_get( $ticket_id ) : null;
    $messages      = $active_ticket ? pax_sup_ticket_get_messages( $ticket_id ) : array();

    $notice = get_transient( 'pax_sup_admin_notice' );
    if ( $notice ) {
        delete_transient( 'pax_sup_admin_notice' );
    }
    ?>
    <div class="wrap pax-console-modern">
        <!-- Modern Header Bar -->
        <div class="pax-console-header">
            <div class="pax-console-header-left">
                <span class="dashicons dashicons-dashboard pax-console-icon"></span>
                <h1 class="pax-console-title"><?php esc_html_e( 'PAX Support Console', 'pax-support-pro' ); ?></h1>
            </div>
            <div class="pax-console-header-right">
                <button type="button" class="pax-console-btn pax-console-btn-refresh" onclick="location.reload();" title="<?php esc_attr_e( 'Refresh', 'pax-support-pro' ); ?>">
                    <span class="dashicons dashicons-update"></span>
                </button>
                <form method="get" class="pax-console-search-form">
                    <input type="hidden" name="page" value="pax-support-console" />
                    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" class="pax-console-search-input" placeholder="<?php esc_attr_e( 'Search tickets…', 'pax-support-pro' ); ?>" />
                    <button type="submit" class="pax-console-search-btn" title="<?php esc_attr_e( 'Search tickets by subject or message content', 'pax-support-pro' ); ?>">
                        <span class="dashicons dashicons-search"></span>
                    </button>
                </form>
                <select class="pax-console-filter" onchange="if(this.value) window.location.href=this.value;" title="<?php esc_attr_e( 'Filter tickets by status', 'pax-support-pro' ); ?>">
                    <option value=""><?php esc_html_e( 'Filter', 'pax-support-pro' ); ?></option>
                    <option value="<?php echo esc_url( add_query_arg( 'filter', 'open' ) ); ?>"><?php esc_html_e( 'Open Tickets', 'pax-support-pro' ); ?></option>
                    <option value="<?php echo esc_url( add_query_arg( 'filter', 'closed' ) ); ?>"><?php esc_html_e( 'Closed Tickets', 'pax-support-pro' ); ?></option>
                    <option value="<?php echo esc_url( add_query_arg( 'filter', 'frozen' ) ); ?>"><?php esc_html_e( 'Frozen Tickets', 'pax-support-pro' ); ?></option>
                </select>
            </div>
        </div>

        <?php if ( $notice ) : ?>
            <div class="pax-console-notice pax-console-notice-<?php echo 'error' === $notice['type'] ? 'error' : 'success'; ?>">
                <span class="dashicons dashicons-<?php echo 'error' === $notice['type'] ? 'warning' : 'yes-alt'; ?>"></span>
                <p><?php echo esc_html( $notice['message'] ); ?></p>
            </div>
        <?php endif; ?>

        <!-- System Metrics Cards -->
        <div class="pax-console-metrics">
            <div class="pax-metric-card" title="<?php esc_attr_e( 'Current PHP version running on your server', 'pax-support-pro' ); ?>">
                <div class="pax-metric-icon pax-metric-icon-cpu">
                    <span class="dashicons dashicons-performance"></span>
                </div>
                <div class="pax-metric-content">
                    <div class="pax-metric-label"><?php esc_html_e( 'PHP Version', 'pax-support-pro' ); ?></div>
                    <div class="pax-metric-value"><?php echo esc_html( $metrics['php_version'] ); ?></div>
                </div>
            </div>

            <div class="pax-metric-card" title="<?php esc_attr_e( 'Current memory usage vs. configured limit', 'pax-support-pro' ); ?>">
                <div class="pax-metric-icon pax-metric-icon-memory">
                    <span class="dashicons dashicons-database"></span>
                </div>
                <div class="pax-metric-content">
                    <div class="pax-metric-label"><?php esc_html_e( 'Memory Usage', 'pax-support-pro' ); ?></div>
                    <div class="pax-metric-value"><?php echo esc_html( $metrics['memory_usage'] ); ?></div>
                    <div class="pax-metric-sub"><?php echo esc_html( $metrics['memory_limit'] ); ?> limit</div>
                </div>
            </div>

            <div class="pax-metric-card" title="<?php esc_attr_e( 'Current server load average (1 minute)', 'pax-support-pro' ); ?>">
                <div class="pax-metric-icon pax-metric-icon-server">
                    <span class="dashicons dashicons-admin-site-alt3"></span>
                </div>
                <div class="pax-metric-content">
                    <div class="pax-metric-label"><?php esc_html_e( 'Server Load', 'pax-support-pro' ); ?></div>
                    <div class="pax-metric-value"><?php echo esc_html( $metrics['server_load'] ); ?></div>
                </div>
            </div>

            <div class="pax-metric-card" title="<?php esc_attr_e( 'Current server time in your timezone', 'pax-support-pro' ); ?>">
                <div class="pax-metric-icon pax-metric-icon-time">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="pax-metric-content">
                    <div class="pax-metric-label"><?php esc_html_e( 'Server Time', 'pax-support-pro' ); ?></div>
                    <div class="pax-metric-value"><?php echo esc_html( $metrics['server_time'] ); ?></div>
                </div>
            </div>
        </div>

        <!-- Analytics Dashboard -->
        <?php pax_sup_render_analytics_dashboard(); ?>
        <!-- Tickets Grid -->
        <div class="pax-console-grid">
            <div class="pax-console-section pax-console-tickets-list">
                <div class="pax-console-section-header">
                    <h2 class="pax-console-section-title">
                        <span class="dashicons dashicons-tickets-alt"></span>
                        <?php esc_html_e( 'Recent Tickets', 'pax-support-pro' ); ?>
                    </h2>
                    <?php if ( $search ) : ?>
                        <a href="<?php echo esc_url( remove_query_arg( array( 'ticket', 's' ) ) ); ?>" class="pax-console-btn-link">
                            <span class="dashicons dashicons-no-alt"></span>
                            <?php esc_html_e( 'Clear Search', 'pax-support-pro' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="pax-console-section-body">
                    <?php if ( $rows ) : ?>
                        <div class="pax-tickets-modern-list">
                            <?php foreach ( $rows as $row ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( array( 'ticket' => (int) $row->id ), remove_query_arg( 'paged' ) ) ); ?>" 
                                   class="pax-ticket-item <?php echo $ticket_id === (int) $row->id ? 'active' : ''; ?>">
                                    <div class="pax-ticket-item-header">
                                        <span class="pax-ticket-id">#<?php echo esc_html( $row->id ); ?></span>
                                        <span class="pax-ticket-status pax-ticket-status-<?php echo esc_attr( $row->status ); ?>">
                                            <?php echo esc_html( pax_sup_format_ticket_status( $row->status ) ); ?>
                                        </span>
                                    </div>
                                    <div class="pax-ticket-item-subject"><?php echo esc_html( $row->subject ); ?></div>
                                    <div class="pax-ticket-item-meta">
                                        <span class="dashicons dashicons-clock"></span>
                                        <?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->updated_at, false ) ); ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <div class="pax-console-empty">
                            <span class="dashicons dashicons-info"></span>
                            <p><?php esc_html_e( 'No tickets found.', 'pax-support-pro' ); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="pax-console-section pax-console-ticket-detail">
                <?php if ( $active_ticket ) : ?>
                    <div class="pax-console-section-header">
                        <h2 class="pax-console-section-title">
                            <span class="dashicons dashicons-admin-comments"></span>
                            <?php echo esc_html( $active_ticket->subject ); ?>
                        </h2>
                        <span class="pax-ticket-status-badge pax-ticket-status-<?php echo esc_attr( $active_ticket->status ); ?>">
                            <?php echo esc_html( pax_sup_format_ticket_status( $active_ticket->status ) ); ?>
                        </span>
                    </div>
                    <div class="pax-console-section-body">
                        <div class="pax-ticket-meta-grid">
                            <div class="pax-ticket-meta-item">
                                <span class="pax-ticket-meta-label"><?php esc_html_e( 'Ticket ID', 'pax-support-pro' ); ?></span>
                                <span class="pax-ticket-meta-value">#<?php echo esc_html( $active_ticket->id ); ?></span>
                            </div>
                            <div class="pax-ticket-meta-item">
                                <span class="pax-ticket-meta-label"><?php esc_html_e( 'Created', 'pax-support-pro' ); ?></span>
                                <span class="pax-ticket-meta-value"><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $active_ticket->created_at, false ) ); ?></span>
                            </div>
                            <div class="pax-ticket-meta-item">
                                <span class="pax-ticket-meta-label"><?php esc_html_e( 'Last Update', 'pax-support-pro' ); ?></span>
                                <span class="pax-ticket-meta-value"><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $active_ticket->updated_at, false ) ); ?></span>
                            </div>
                        </div>

                        <div class="pax-conversation-section">
                            <h3 class="pax-conversation-title">
                                <span class="dashicons dashicons-format-chat"></span>
                                <?php esc_html_e( 'Conversation', 'pax-support-pro' ); ?>
                            </h3>
                            <div class="pax-conversation-thread">
                                <?php foreach ( (array) $messages as $message ) : ?>
                                    <div class="pax-message-item pax-message-<?php echo esc_attr( $message->sender ); ?>">
                                        <div class="pax-message-header">
                                            <span class="pax-message-sender"><?php echo esc_html( strtoupper( $message->sender ) ); ?></span>
                                            <span class="pax-message-time">
                                                <span class="dashicons dashicons-clock"></span>
                                                <?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $message->created_at, false ) ); ?>
                                            </span>
                                        </div>
                                        <div class="pax-message-content"><?php echo wpautop( wp_kses_post( $message->note ) ); ?></div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ( empty( $messages ) ) : ?>
                                    <div class="pax-console-empty">
                                        <span class="dashicons dashicons-info"></span>
                                        <p><?php esc_html_e( 'No messages yet.', 'pax-support-pro' ); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="pax-reply-section">
                            <h3 class="pax-reply-title">
                                <span class="dashicons dashicons-edit"></span>
                                <?php esc_html_e( 'Reply to User', 'pax-support-pro' ); ?>
                            </h3>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pax-reply-form">
                                <textarea name="reply_message" rows="5" class="pax-reply-textarea" placeholder="<?php esc_attr_e( 'Type your reply here...', 'pax-support-pro' ); ?>" required></textarea>
                                <input type="hidden" name="action" value="pax_sup_ticket_action" />
                                <input type="hidden" name="ticket_id" value="<?php echo esc_attr( $active_ticket->id ); ?>" />
                                <input type="hidden" name="ticket_action" value="reply" />
                                <?php wp_nonce_field( 'pax_sup_ticket_action_' . $active_ticket->id ); ?>
                                <button type="submit" class="pax-console-btn pax-console-btn-primary">
                                    <span class="dashicons dashicons-yes"></span>
                                    <?php esc_html_e( 'Send Reply', 'pax-support-pro' ); ?>
                                </button>
                            </form>
                        </div>

                        <div class="pax-ticket-actions">
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pax-ticket-action-form">
                                <input type="hidden" name="action" value="pax_sup_ticket_action" />
                                <input type="hidden" name="ticket_id" value="<?php echo esc_attr( $active_ticket->id ); ?>" />
                                <input type="hidden" name="ticket_action" value="<?php echo 'frozen' === $active_ticket->status ? 'unfreeze' : 'freeze'; ?>" />
                                <?php wp_nonce_field( 'pax_sup_ticket_action_' . $active_ticket->id ); ?>
                                <button type="submit" class="pax-console-btn pax-console-btn-secondary">
                                    <span class="dashicons dashicons-<?php echo 'frozen' === $active_ticket->status ? 'unlock' : 'lock'; ?>"></span>
                                    <?php echo 'frozen' === $active_ticket->status ? esc_html__( 'Unfreeze', 'pax-support-pro' ) : esc_html__( 'Freeze', 'pax-support-pro' ); ?>
                                </button>
                            </form>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pax-delete-form pax-ticket-action-form">
                                <input type="hidden" name="action" value="pax_sup_ticket_action" />
                                <input type="hidden" name="ticket_id" value="<?php echo esc_attr( $active_ticket->id ); ?>" />
                                <input type="hidden" name="ticket_action" value="delete" />
                                <?php wp_nonce_field( 'pax_sup_ticket_action_' . $active_ticket->id ); ?>
                                <button type="submit" class="pax-console-btn pax-console-btn-danger pax-delete-button">
                                    <span class="dashicons dashicons-trash"></span>
                                    <?php esc_html_e( 'Delete Ticket', 'pax-support-pro' ); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="pax-console-section-header">
                        <h2 class="pax-console-section-title">
                            <span class="dashicons dashicons-admin-comments"></span>
                            <?php esc_html_e( 'Ticket Details', 'pax-support-pro' ); ?>
                        </h2>
                    </div>
                    <div class="pax-console-section-body">
                        <div class="pax-console-empty pax-console-empty-large">
                            <span class="dashicons dashicons-tickets-alt"></span>
                            <h3><?php esc_html_e( 'Select a Ticket', 'pax-support-pro' ); ?></h3>
                            <p><?php esc_html_e( 'Choose a ticket from the list to view the conversation and manage status, replies, or deletion.', 'pax-support-pro' ); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var deleteForms = document.querySelectorAll('.pax-delete-form');
        deleteForms.forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.confirm('<?php echo esc_js( __( 'Are you sure you want to delete this ticket? This action cannot be undone.', 'pax-support-pro' ) ); ?>')) {
                    event.preventDefault();
                }
            });
        });
    });
    </script>
    <?php
}