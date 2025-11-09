<?php
/**
 * Toggle Button Integration Test
 * 
 * @package PAX_Support_Pro
 * @version 5.4.2
 */

// Test results
$tests = array();

// Test 1: Check if chat.php has toggle button
$chat_php_content = file_get_contents( __DIR__ . '/../public/chat.php' );
$tests['toggle_button_in_html'] = strpos( $chat_php_content, 'id="pax-chat-toggle"' ) !== false;

// Test 2: Check if CSS has toggle button styles
$css_content = file_get_contents( __DIR__ . '/../public/css/livechat-unified.css' );
$tests['toggle_button_css'] = strpos( $css_content, '.pax-chat-toggle-btn' ) !== false;

// Test 3: Check if JavaScript has toggle button handler
$js_content = file_get_contents( __DIR__ . '/../assets/js/pax-unified-chat.js' );
$tests['toggle_button_js'] = strpos( $js_content, 'setupToggleButton' ) !== false;

// Test 4: Check if toggle button has proper positioning
$tests['toggle_button_fixed'] = strpos( $css_content, 'position: fixed' ) !== false;

// Test 5: Check if toggle button has click handler
$tests['toggle_button_click'] = strpos( $js_content, "getElementById('pax-chat-toggle')" ) !== false;

// Test 6: Check if old launcher is hidden
$tests['old_launcher_hidden'] = strpos( $chat_php_content, 'style="display: none;"' ) !== false;

// Test 7: Check if toggle button has accessibility attributes
$tests['toggle_button_a11y'] = strpos( $chat_php_content, 'aria-label' ) !== false;

// Test 8: Check if toggle button has pulse animation
$tests['toggle_button_pulse'] = strpos( $css_content, 'pax-pulse' ) !== false;

// Output results
echo "PAX Support Pro v5.4.2 - Toggle Button Test Suite\n";
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
    echo "\n✅ All tests passed! Toggle button is properly integrated.\n";
} else {
    echo "\n❌ Some tests failed. Please review the implementation.\n";
}

exit( $failed > 0 ? 1 : 0 );
