<?php
/**
 * Chat Reactions Admin Page
 * 
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register Chat Reactions admin page
 */
add_action( 'admin_menu', 'pax_sup_register_chat_reactions_page', 20 );
function pax_sup_register_chat_reactions_page() {
    add_submenu_page(
        'pax-support-console',
        __( 'Chat Reactions', 'pax-support-pro' ),
        __( 'Chat Reactions', 'pax-support-pro' ),
        pax_sup_get_console_capability(),
        'pax-support-reactions',
        'pax_sup_render_chat_reactions_page'
    );
}

/**
 * Render Chat Reactions page
 */
function pax_sup_render_chat_reactions_page() {
    if ( ! current_user_can( pax_sup_get_console_capability() ) ) {
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'pax_chat_reactions';
    
    // Get statistics
    $stats = $wpdb->get_results(
        "SELECT 
            reaction_type,
            COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
        FROM {$table_name}
        GROUP BY reaction_type",
        ARRAY_A
    );
    
    // Get recent reactions
    $recent = $wpdb->get_results(
        "SELECT 
            reaction_type,
            message_text,
            is_active,
            created_at,
            user_id
        FROM {$table_name}
        ORDER BY created_at DESC
        LIMIT 100",
        ARRAY_A
    );
    
    // Calculate totals
    $total_likes = 0;
    $total_dislikes = 0;
    $total_copies = 0;
    
    foreach ( $stats as $stat ) {
        if ( $stat['reaction_type'] === 'like' ) {
            $total_likes = (int) $stat['active'];
        } elseif ( $stat['reaction_type'] === 'dislike' ) {
            $total_dislikes = (int) $stat['active'];
        } elseif ( $stat['reaction_type'] === 'copy' ) {
            $total_copies = (int) $stat['total'];
        }
    }
    
    ?>
    <div class="wrap pax-reactions-page">
        <h1>
            <span class="dashicons dashicons-thumbs-up" style="font-size: 28px; vertical-align: middle; margin-right: 8px;"></span>
            <?php esc_html_e( 'Chat Reactions', 'pax-support-pro' ); ?>
        </h1>
        
        <div class="pax-reactions-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
            <!-- Likes Card -->
            <div class="pax-stat-card" style="background: linear-gradient(135deg, #4caf50 0%, #66bb6a 100%); color: white; padding: 24px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <span class="dashicons dashicons-thumbs-up" style="font-size: 32px;"></span>
                    <span style="font-size: 36px; font-weight: 700;"><?php echo esc_html( $total_likes ); ?></span>
                </div>
                <h3 style="margin: 0; font-size: 16px; font-weight: 500; opacity: 0.9;"><?php esc_html_e( 'Total Likes', 'pax-support-pro' ); ?></h3>
            </div>
            
            <!-- Dislikes Card -->
            <div class="pax-stat-card" style="background: linear-gradient(135deg, #f44336 0%, #e57373 100%); color: white; padding: 24px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <span class="dashicons dashicons-thumbs-down" style="font-size: 32px;"></span>
                    <span style="font-size: 36px; font-weight: 700;"><?php echo esc_html( $total_dislikes ); ?></span>
                </div>
                <h3 style="margin: 0; font-size: 16px; font-weight: 500; opacity: 0.9;"><?php esc_html_e( 'Total Dislikes', 'pax-support-pro' ); ?></h3>
            </div>
            
            <!-- Copies Card -->
            <div class="pax-stat-card" style="background: linear-gradient(135deg, #2196f3 0%, #64b5f6 100%); color: white; padding: 24px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <span class="dashicons dashicons-admin-page" style="font-size: 32px;"></span>
                    <span style="font-size: 36px; font-weight: 700;"><?php echo esc_html( $total_copies ); ?></span>
                </div>
                <h3 style="margin: 0; font-size: 16px; font-weight: 500; opacity: 0.9;"><?php esc_html_e( 'Total Copies', 'pax-support-pro' ); ?></h3>
            </div>
            
            <!-- Satisfaction Rate Card -->
            <?php
            $total_feedback = $total_likes + $total_dislikes;
            $satisfaction_rate = $total_feedback > 0 ? round( ( $total_likes / $total_feedback ) * 100 ) : 0;
            ?>
            <div class="pax-stat-card" style="background: linear-gradient(135deg, #9c27b0 0%, #ba68c8 100%); color: white; padding: 24px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <span class="dashicons dashicons-chart-line" style="font-size: 32px;"></span>
                    <span style="font-size: 36px; font-weight: 700;"><?php echo esc_html( $satisfaction_rate ); ?>%</span>
                </div>
                <h3 style="margin: 0; font-size: 16px; font-weight: 500; opacity: 0.9;"><?php esc_html_e( 'Satisfaction Rate', 'pax-support-pro' ); ?></h3>
            </div>
        </div>
        
        <!-- Recent Reactions Table -->
        <div class="pax-reactions-table" style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-top: 20px;">
            <h2 style="margin-top: 0;"><?php esc_html_e( 'Recent Reactions', 'pax-support-pro' ); ?></h2>
            
            <?php if ( empty( $recent ) ) : ?>
                <p style="color: #666; font-style: italic;"><?php esc_html_e( 'No reactions yet.', 'pax-support-pro' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped" style="margin-top: 16px;">
                    <thead>
                        <tr>
                            <th style="width: 100px;"><?php esc_html_e( 'Type', 'pax-support-pro' ); ?></th>
                            <th><?php esc_html_e( 'Message', 'pax-support-pro' ); ?></th>
                            <th style="width: 100px;"><?php esc_html_e( 'Status', 'pax-support-pro' ); ?></th>
                            <th style="width: 120px;"><?php esc_html_e( 'User', 'pax-support-pro' ); ?></th>
                            <th style="width: 150px;"><?php esc_html_e( 'Date', 'pax-support-pro' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $recent as $reaction ) : ?>
                            <tr>
                                <td>
                                    <?php
                                    $icon = '';
                                    $color = '';
                                    if ( $reaction['reaction_type'] === 'like' ) {
                                        $icon = 'dashicons-thumbs-up';
                                        $color = '#4caf50';
                                    } elseif ( $reaction['reaction_type'] === 'dislike' ) {
                                        $icon = 'dashicons-thumbs-down';
                                        $color = '#f44336';
                                    } elseif ( $reaction['reaction_type'] === 'copy' ) {
                                        $icon = 'dashicons-admin-page';
                                        $color = '#2196f3';
                                    }
                                    ?>
                                    <span class="dashicons <?php echo esc_attr( $icon ); ?>" style="color: <?php echo esc_attr( $color ); ?>; font-size: 20px;"></span>
                                    <span style="text-transform: capitalize;"><?php echo esc_html( $reaction['reaction_type'] ); ?></span>
                                </td>
                                <td>
                                    <div style="max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo esc_html( wp_trim_words( $reaction['message_text'], 15 ) ); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ( $reaction['is_active'] ) : ?>
                                        <span style="color: #4caf50; font-weight: 500;">
                                            <span class="dashicons dashicons-yes-alt" style="font-size: 16px; vertical-align: middle;"></span>
                                            <?php esc_html_e( 'Active', 'pax-support-pro' ); ?>
                                        </span>
                                    <?php else : ?>
                                        <span style="color: #999;">
                                            <span class="dashicons dashicons-minus" style="font-size: 16px; vertical-align: middle;"></span>
                                            <?php esc_html_e( 'Removed', 'pax-support-pro' ); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    if ( $reaction['user_id'] > 0 ) {
                                        $user = get_userdata( $reaction['user_id'] );
                                        echo esc_html( $user ? $user->display_name : __( 'Unknown', 'pax-support-pro' ) );
                                    } else {
                                        echo esc_html__( 'Guest', 'pax-support-pro' );
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $reaction['created_at'] ) ) ); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
        .pax-reactions-page {
            max-width: 1400px;
        }
        .pax-stat-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .pax-stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.15) !important;
        }
    </style>
    <?php
}
