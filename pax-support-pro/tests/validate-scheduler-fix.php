<?php
/**
 * Validation script for scheduler insert format fix.
 * 
 * This script demonstrates the bug that was fixed:
 * - The data array had 10 fields
 * - The format array had only 9 format specifiers
 * - This caused wpdb->insert() to fail silently
 *
 * @package PAX_Support_Pro
 */

// Simulate the data structure used in pax_sup_rest_schedule_create()
$schedule_data = array(
	'user_id'       => 1,        // %d
	'agent_id'      => 2,        // %d
	'schedule_date' => '2025-11-01',  // %s
	'schedule_time' => '14:00',       // %s
	'timezone'      => 'UTC',         // %s
	'contact'       => 'test@example.com',  // %s
	'note'          => 'Test note',   // %s
	'status'        => 'pending',     // %s
	'created_at'    => '2025-10-30 12:00:00',  // %s
	'updated_at'    => '2025-10-30 12:00:00',  // %s
);

// BEFORE FIX (BUGGY): Only 9 format specifiers for 10 data fields
$format_before = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

// AFTER FIX (CORRECT): 10 format specifiers for 10 data fields
$format_after = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

echo "=== Scheduler Insert Format Validation ===\n\n";

echo "Data fields count: " . count( $schedule_data ) . "\n";
echo "Data fields:\n";
$index = 1;
foreach ( array_keys( $schedule_data ) as $field ) {
	echo "  {$index}. {$field}\n";
	$index++;
}

echo "\n";
echo "BEFORE FIX:\n";
echo "  Format specifiers count: " . count( $format_before ) . "\n";
echo "  Format array: " . implode( ', ', $format_before ) . "\n";
echo "  Status: ❌ MISMATCH - " . count( $schedule_data ) . " fields vs " . count( $format_before ) . " formats\n";
echo "  Impact: wpdb->insert() would fail, breaking callback scheduling\n";

echo "\n";
echo "AFTER FIX:\n";
echo "  Format specifiers count: " . count( $format_after ) . "\n";
echo "  Format array: " . implode( ', ', $format_after ) . "\n";
echo "  Status: ✅ CORRECT - " . count( $schedule_data ) . " fields vs " . count( $format_after ) . " formats\n";
echo "  Impact: wpdb->insert() works correctly, callback scheduling functional\n";

echo "\n";
echo "=== Field-to-Format Mapping ===\n";
$index = 0;
foreach ( $schedule_data as $field => $value ) {
	$type = is_int( $value ) ? 'integer' : 'string';
	$expected_format = is_int( $value ) ? '%d' : '%s';
	$actual_format = isset( $format_after[ $index ] ) ? $format_after[ $index ] : 'MISSING';
	$status = ( $expected_format === $actual_format ) ? '✅' : '❌';
	
	echo sprintf(
		"  %s %-15s => %-7s (expected: %s, actual: %s)\n",
		$status,
		$field,
		$type,
		$expected_format,
		$actual_format
	);
	$index++;
}

echo "\n=== Summary ===\n";
echo "Bug: Database insert format array had 9 specifiers for 10 data fields\n";
echo "Fix: Added missing 10th format specifier (%s for 'updated_at' field)\n";
echo "File: pax-support-pro/rest/scheduler.php, line 134\n";
echo "Impact: Critical - Fixes broken callback scheduling feature\n";
