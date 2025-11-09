<?php
/**
 * Test page for update modals
 * Access via: /wp-admin/admin.php?page=pax-test-modals
 * 
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add test page to admin menu (only for testing)
function pax_sup_add_test_modal_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    add_submenu_page(
        null, // Hidden from menu
        'Test Update Modals',
        'Test Update Modals',
        'manage_options',
        'pax-test-modals',
        'pax_sup_render_test_modal_page'
    );
}
add_action( 'admin_menu', 'pax_sup_add_test_modal_page' );

/**
 * Render test page
 */
function pax_sup_render_test_modal_page() {
    ?>
    <div class="wrap">
        <h1>Update Modal Testing</h1>
        <p>Use the buttons below to test the update notification modals.</p>
        
        <div style="margin: 30px 0;">
            <h2>Test Success Modal</h2>
            <p>This will display the success modal with a sample version and changelog.</p>
            <button type="button" class="button button-primary" onclick="testSuccessModal()">
                Show Success Modal
            </button>
        </div>
        
        <div style="margin: 30px 0;">
            <h2>Test Failure Modal</h2>
            <p>This will display the failure modal with a sample error message.</p>
            <button type="button" class="button button-secondary" onclick="testFailureModal()">
                Show Failure Modal
            </button>
        </div>
        
        <div style="margin: 30px 0;">
            <h2>Test via URL Parameters</h2>
            <p>You can also test by adding URL parameters:</p>
            <ul>
                <li>
                    <a href="<?php echo esc_url( add_query_arg( array( 'pax_update_status' => 'success', 'pax_update_version' => '1.2.0' ) ) ); ?>">
                        Test Success via URL
                    </a>
                </li>
                <li>
                    <a href="<?php echo esc_url( add_query_arg( array( 'pax_update_status' => 'failed', 'pax_update_error' => 'Connection timeout: Unable to download update package' ) ) ); ?>">
                        Test Failure via URL
                    </a>
                </li>
            </ul>
        </div>
        
        <script>
        function testSuccessModal() {
            if (typeof paxShowUpdateSuccess === 'function') {
                paxShowUpdateSuccess('1.2.0', [
                    'Added animated post-update success/failure modals',
                    'Enhanced dashboard analytics chart with dynamic colors',
                    'Implemented system health indicator with real-time monitoring',
                    'Fixed security vulnerabilities in update verification',
                    'Performance optimizations and bug fixes'
                ]);
            } else {
                alert('Modal script not loaded. Please refresh the page.');
            }
        }
        
        function testFailureModal() {
            if (typeof paxShowUpdateFailure === 'function') {
                paxShowUpdateFailure('Connection timeout: Unable to download update package from GitHub. Please check your internet connection and try again.');
            } else {
                alert('Modal script not loaded. Please refresh the page.');
            }
        }
        </script>
    </div>
    <?php
}
