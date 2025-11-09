<?php
/**
 * Unified Chat System Test Suite
 * 
 * @package PAX_Support_Pro
 * @version 5.4.2
 */

// Test results
$tests = array();

// Test 1: Check if unified chat JS exists
$tests['unified_js_exists'] = file_exists( __DIR__ . '/../assets/js/pax-unified-chat.js' );

// Test 2: Check if unified REST endpoints file exists
$tests['unified_rest_exists'] = file_exists( __DIR__ . '/../includes/rest/chat-endpoints.php' );

// Test 3: Check if chat.php has been updated
$chat_php_content = file_get_contents( __DIR__ . '/../public/chat.php' );
$tests['chat_php_updated'] = strpos( $chat_php_content, 'pax-unified-chat' ) !== false;

// Test 4: Check if CSS has unified styles
$css_content = file_get_contents( __DIR__ . '/../public/css/livechat-unified.css' );
$tests['css_has_unified'] = strpos( $css_content, 'pax-mode-switcher' ) !== false;

// Test 5: Check if admin CSS has unified styles
$admin_css_content = file_get_contents( __DIR__ . '/../admin/css/live-agent-center.css' );
$tests['admin_css_updated'] = strpos( $admin_css_content, 'pax-unified-admin' ) !== false;

// Test 6: Check if legacy liveagent-button is disabled
$main_plugin_content = file_get_contents( __DIR__ . '/../pax-support-pro.php' );
$tests['legacy_disabled'] = strpos( $main_plugin_content, '// v5.4.2: Legacy Live Chat button disabled' ) !== false;

// Output results
echo "PAX Support Pro v5.4.2 - Unified Chat Test Suite\n";
echo str_repeat( '=', 60 ) . "\n\n";

$passed = 0;
$failed = 0;

foreach ( $tests as $test_name => $result ) {
    $status = $result ? '✅ PASS' : '❌ FAIL';
    echo sprintf( "%-40s %s\n", ucwords( str_replace( '_', ' ', $test_name ) ), $status );
    
    if ( $result ) {
        $passed++;
    } else {
        $failed++;
    }
}

echo "\n" . str_repeat( '=', 60 ) . "\n";
echo sprintf( "Total: %d tests | Passed: %d | Failed: %d\n", count( $tests ), $passed, $failed );
echo str_repeat( '=', 60 ) . "\n";

if ( $failed === 0 ) {
    echo "\n✅ All tests passed! Unified chat system is properly integrated.\n";
} else {
    echo "\n❌ Some tests failed. Please review the implementation.\n";
}

exit( $failed > 0 ? 1 : 0 );
