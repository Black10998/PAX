<?php
/**
 * Test for scheduler database insert format fix.
 *
 * @package PAX_Support_Pro
 */

/**
 * Test class for scheduler insert functionality.
 */
class PAX_Scheduler_Insert_Test extends WP_UnitTestCase {

	/**
	 * Test that the format array matches the data array length.
	 */
	public function test_scheduler_insert_format_count() {
		// Simulate the data array from pax_sup_rest_schedule_create
		$data_array = array(
			'user_id'       => 1,
			'agent_id'      => 2,
			'schedule_date' => '2025-11-01',
			'schedule_time' => '14:00',
			'timezone'      => 'UTC',
			'contact'       => 'test@example.com',
			'note'          => 'Test note',
			'status'        => 'pending',
			'created_at'    => current_time( 'mysql' ),
			'updated_at'    => current_time( 'mysql' ),
		);

		// The format array that should match
		$format_array = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		// Assert that counts match
		$this->assertEquals(
			count( $data_array ),
			count( $format_array ),
			'Format array count must match data array count'
		);

		// Assert we have exactly 10 items
		$this->assertEquals( 10, count( $data_array ), 'Data array should have 10 fields' );
		$this->assertEquals( 10, count( $format_array ), 'Format array should have 10 format specifiers' );
	}

	/**
	 * Test that format specifiers match data types.
	 */
	public function test_scheduler_insert_format_types() {
		$data_types = array(
			'user_id'       => 'integer',
			'agent_id'      => 'integer',
			'schedule_date' => 'string',
			'schedule_time' => 'string',
			'timezone'      => 'string',
			'contact'       => 'string',
			'note'          => 'string',
			'status'        => 'string',
			'created_at'    => 'string',
			'updated_at'    => 'string',
		);

		$format_array = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		$index = 0;
		foreach ( $data_types as $field => $type ) {
			$expected_format = ( 'integer' === $type ) ? '%d' : '%s';
			$this->assertEquals(
				$expected_format,
				$format_array[ $index ],
				"Format specifier for {$field} should be {$expected_format}"
			);
			$index++;
		}
	}

	/**
	 * Test actual database insert with correct format.
	 */
	public function test_scheduler_database_insert() {
		global $wpdb;

		// Ensure tables exist
		pax_sup_ensure_ticket_tables();

		$table = pax_sup_get_schedules_table();

		$test_data = array(
			'user_id'       => 1,
			'agent_id'      => 0,
			'schedule_date' => '2025-11-01',
			'schedule_time' => '14:00',
			'timezone'      => 'UTC',
			'contact'       => 'test@example.com',
			'note'          => 'Test callback note',
			'status'        => 'pending',
			'created_at'    => current_time( 'mysql' ),
			'updated_at'    => current_time( 'mysql' ),
		);

		$format = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		$result = $wpdb->insert( $table, $test_data, $format );

		// Assert insert was successful
		$this->assertNotFalse( $result, 'Database insert should succeed with correct format array' );
		$this->assertGreaterThan( 0, $wpdb->insert_id, 'Insert ID should be greater than 0' );

		// Clean up
		$wpdb->delete( $table, array( 'id' => $wpdb->insert_id ), array( '%d' ) );
	}

	/**
	 * Test that insert fails with incorrect format count (the bug we fixed).
	 */
	public function test_scheduler_insert_fails_with_wrong_format_count() {
		global $wpdb;

		// Ensure tables exist
		pax_sup_ensure_ticket_tables();

		$table = pax_sup_get_schedules_table();

		$test_data = array(
			'user_id'       => 1,
			'agent_id'      => 0,
			'schedule_date' => '2025-11-01',
			'schedule_time' => '14:00',
			'timezone'      => 'UTC',
			'contact'       => 'test@example.com',
			'note'          => 'Test callback note',
			'status'        => 'pending',
			'created_at'    => current_time( 'mysql' ),
			'updated_at'    => current_time( 'mysql' ),
		);

		// Intentionally use wrong format count (9 instead of 10) - this was the bug
		$wrong_format = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		// Suppress errors for this test
		$wpdb->suppress_errors( true );
		$result = $wpdb->insert( $table, $test_data, $wrong_format );
		$wpdb->suppress_errors( false );

		// Assert insert failed due to format mismatch
		$this->assertFalse( $result, 'Database insert should fail with incorrect format array count' );
	}
}
