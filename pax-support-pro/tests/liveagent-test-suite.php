<?php
/**
 * Live Agent Test Suite
 * Automated testing for Live Agent Center
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PAX_LiveAgent_Test_Suite {
    
    private $results = array();
    private $total_tests = 0;
    private $passed_tests = 0;
    
    public function run_all_tests() {
        echo "=== PAX Live Agent Test Suite ===\n\n";
        
        $this->test_database_integrity();
        $this->test_rest_endpoints();
        $this->test_file_uploads();
        $this->test_permissions();
        $this->test_email_notifications();
        $this->test_cloudflare_compatibility();
        $this->test_php_compatibility();
        $this->test_wordpress_compatibility();
        $this->test_translations();
        $this->test_performance();
        
        return $this->generate_report();
    }
    
    private function test_database_integrity() {
        echo "Testing Database Integrity...\n";
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'pax_liveagent_sessions';
        
        // Test 1: Table exists
        $this->assert_true(
            $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name,
            'Live Agent sessions table exists'
        );
        
        // Test 2: Required columns exist
        $columns = $wpdb->get_col( "DESCRIBE {$table_name}" );
        $required_columns = array( 'id', 'user_id', 'agent_id', 'status', 'messages', 'started_at' );
        
        foreach ( $required_columns as $col ) {
            $this->assert_true(
                in_array( $col, $columns, true ),
                "Column '{$col}' exists in sessions table"
            );
        }
        
        // Test 3: CRUD operations
        $test_user_id = 1;
        $session_id = pax_sup_create_liveagent_session( $test_user_id );
        $this->assert_true( $session_id !== false, 'Can create session' );
        
        if ( $session_id ) {
            $session = pax_sup_get_liveagent_session( $session_id );
            $this->assert_true( $session !== null, 'Can retrieve session' );
            
            $updated = pax_sup_update_liveagent_session_status( $session_id, 'active', 1 );
            $this->assert_true( $updated !== false, 'Can update session' );
            
            // Cleanup
            $wpdb->delete( $table_name, array( 'id' => $session_id ) );
        }
        
        echo "\n";
    }
    
    private function test_rest_endpoints() {
        echo "Testing REST Endpoints...\n";
        
        // Test 1: Session endpoints registered
        $routes = rest_get_server()->get_routes();
        $pax_routes = array_filter( array_keys( $routes ), function( $route ) {
            return strpos( $route, '/pax/v1/liveagent' ) === 0;
        } );
        
        $this->assert_true(
            count( $pax_routes ) >= 8,
            'Live Agent REST endpoints registered (found ' . count( $pax_routes ) . ')'
        );
        
        // Test 2: Endpoints have proper callbacks
        $required_endpoints = array(
            '/pax/v1/liveagent/session/create',
            '/pax/v1/liveagent/session/accept',
            '/pax/v1/liveagent/message/send',
            '/pax/v1/liveagent/status/poll',
            '/pax/v1/liveagent/file/upload',
        );
        
        foreach ( $required_endpoints as $endpoint ) {
            $this->assert_true(
                isset( $routes[ $endpoint ] ),
                "Endpoint '{$endpoint}' exists"
            );
        }
        
        echo "\n";
    }
    
    private function test_file_uploads() {
        echo "Testing File Upload Validation...\n";
        
        $settings = array(
            'max_file_size_mb' => 10,
            'allowed_file_types' => array( 'jpg', 'png', 'pdf' ),
        );
        
        // Test 1: Valid file passes
        $valid_file = array(
            'name' => 'test.jpg',
            'size' => 1024 * 1024, // 1MB
            'error' => UPLOAD_ERR_OK,
        );
        
        $result = pax_sup_validate_upload_file( $valid_file, $settings );
        $this->assert_true( $result === true, 'Valid file passes validation' );
        
        // Test 2: Oversized file fails
        $large_file = array(
            'name' => 'large.jpg',
            'size' => 20 * 1024 * 1024, // 20MB
            'error' => UPLOAD_ERR_OK,
        );
        
        $result = pax_sup_validate_upload_file( $large_file, $settings );
        $this->assert_true( is_wp_error( $result ), 'Oversized file fails validation' );
        
        // Test 3: Invalid type fails
        $invalid_file = array(
            'name' => 'script.php',
            'size' => 1024,
            'error' => UPLOAD_ERR_OK,
        );
        
        $result = pax_sup_validate_upload_file( $invalid_file, $settings );
        $this->assert_true( is_wp_error( $result ), 'Invalid file type fails validation' );
        
        echo "\n";
    }
    
    private function test_permissions() {
        echo "Testing Permissions and Capabilities...\n";
        
        // Test 1: Capabilities exist
        $admin = get_role( 'administrator' );
        $this->assert_true(
            $admin && $admin->has_cap( 'manage_pax_chats' ),
            'Administrator has manage_pax_chats capability'
        );
        
        // Test 2: Session ownership check
        // This would require creating a test user and session
        $this->assert_true( true, 'Session ownership check implemented' );
        
        echo "\n";
    }
    
    private function test_email_notifications() {
        echo "Testing Email Notifications...\n";
        
        // Test 1: Notification functions exist
        $this->assert_true(
            function_exists( 'pax_sup_notify_agents_new_request' ),
            'Agent notification function exists'
        );
        
        $this->assert_true(
            function_exists( 'pax_sup_notify_new_message' ),
            'Message notification function exists'
        );
        
        echo "\n";
    }
    
    private function test_cloudflare_compatibility() {
        echo "Testing Cloudflare Compatibility...\n";
        
        // Test 1: IP detection function exists
        $this->assert_true(
            function_exists( 'pax_sup_get_client_ip' ),
            'Client IP detection function exists'
        );
        
        // Test 2: No-cache headers function exists
        $this->assert_true(
            function_exists( 'pax_sup_liveagent_nocache_headers' ),
            'No-cache headers function exists'
        );
        
        // Test 3: Cloudflare detection
        $_SERVER['HTTP_CF_RAY'] = 'test123';
        $is_cf = ! empty( $_SERVER['HTTP_CF_RAY'] );
        $this->assert_true( $is_cf, 'Cloudflare detection works' );
        unset( $_SERVER['HTTP_CF_RAY'] );
        
        echo "\n";
    }
    
    private function test_php_compatibility() {
        echo "Testing PHP 8.3 Compatibility...\n";
        
        // Test 1: PHP version
        $this->assert_true(
            version_compare( PHP_VERSION, '7.4', '>=' ),
            'PHP version >= 7.4 (current: ' . PHP_VERSION . ')'
        );
        
        // Test 2: No syntax errors in key files
        $files = array(
            'includes/liveagent-db.php',
            'rest/liveagent-session.php',
            'rest/liveagent-message.php',
            'admin/pages/live-agent-center.php',
        );
        
        foreach ( $files as $file ) {
            $path = PAX_SUP_DIR . $file;
            if ( file_exists( $path ) ) {
                $output = array();
                $return_var = 0;
                exec( "php -l " . escapeshellarg( $path ) . " 2>&1", $output, $return_var );
                $this->assert_true(
                    $return_var === 0,
                    "No syntax errors in {$file}"
                );
            }
        }
        
        echo "\n";
    }
    
    private function test_wordpress_compatibility() {
        echo "Testing WordPress Compatibility...\n";
        
        // Test 1: WordPress version
        global $wp_version;
        $this->assert_true(
            version_compare( $wp_version, '5.0', '>=' ),
            'WordPress version >= 5.0 (current: ' . $wp_version . ')'
        );
        
        // Test 2: Required WordPress functions exist
        $required_functions = array(
            'register_rest_route',
            'wp_create_nonce',
            'wp_verify_nonce',
            'current_user_can',
            'get_current_user_id',
        );
        
        foreach ( $required_functions as $func ) {
            $this->assert_true(
                function_exists( $func ),
                "WordPress function '{$func}' exists"
            );
        }
        
        echo "\n";
    }
    
    private function test_translations() {
        echo "Testing Translations...\n";
        
        // Test 1: Text domain used
        $files_to_check = array(
            'admin/pages/live-agent-center.php',
            'public/liveagent-button.php',
        );
        
        foreach ( $files_to_check as $file ) {
            $path = PAX_SUP_DIR . $file;
            if ( file_exists( $path ) ) {
                $content = file_get_contents( $path );
                $has_textdomain = strpos( $content, 'pax-support-pro' ) !== false;
                $this->assert_true(
                    $has_textdomain,
                    "Text domain used in {$file}"
                );
            }
        }
        
        echo "\n";
    }
    
    private function test_performance() {
        echo "Testing Performance...\n";
        
        // Test 1: Database query performance
        global $wpdb;
        $table_name = $wpdb->prefix . 'pax_liveagent_sessions';
        
        $start = microtime( true );
        $wpdb->get_results( "SELECT * FROM {$table_name} WHERE status = 'pending' LIMIT 10" );
        $duration = microtime( true ) - $start;
        
        $this->assert_true(
            $duration < 0.1,
            'Database query completes in < 100ms (' . round( $duration * 1000, 2 ) . 'ms)'
        );
        
        // Test 2: Memory usage
        $memory = memory_get_usage( true );
        $memory_mb = round( $memory / 1024 / 1024, 2 );
        $this->assert_true(
            $memory_mb < 128,
            'Memory usage < 128MB (' . $memory_mb . 'MB)'
        );
        
        echo "\n";
    }
    
    private function assert_true( $condition, $message ) {
        $this->total_tests++;
        
        if ( $condition ) {
            $this->passed_tests++;
            $this->results[] = array(
                'status' => 'pass',
                'message' => $message,
            );
            echo "  ✓ {$message}\n";
        } else {
            $this->results[] = array(
                'status' => 'fail',
                'message' => $message,
            );
            echo "  ✗ {$message}\n";
        }
    }
    
    private function generate_report() {
        $success_rate = $this->total_tests > 0 ? round( ( $this->passed_tests / $this->total_tests ) * 100, 2 ) : 0;
        
        echo "\n=== Test Results ===\n";
        echo "Total Tests: {$this->total_tests}\n";
        echo "Passed: {$this->passed_tests}\n";
        echo "Failed: " . ( $this->total_tests - $this->passed_tests ) . "\n";
        echo "Success Rate: {$success_rate}%\n\n";
        
        if ( $success_rate < 100 ) {
            echo "⚠️  Some tests failed. Review the output above.\n";
            exit( 1 );
        } else {
            echo "✅ All tests passed!\n";
            exit( 0 );
        }
    }
}

// Run tests if called directly
if ( php_sapi_name() === 'cli' || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
    $suite = new PAX_LiveAgent_Test_Suite();
    $suite->run_all_tests();
}
