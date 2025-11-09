# Bug Fix: Scheduler Database Insert Format Mismatch

## Summary
Fixed a critical bug in the callback scheduler that prevented users from successfully scheduling callbacks due to a mismatch between the number of data fields and format specifiers in the database insert operation.

## Bug Details

### Location
- **File**: `pax-support-pro/rest/scheduler.php`
- **Function**: `pax_sup_rest_schedule_create()`
- **Line**: 134 (before fix)

### Issue
The `$wpdb->insert()` call had a format array with only 9 format specifiers for 10 data fields:

```php
$inserted = $wpdb->insert(
    $table,
    array(
        'user_id'       => $user_id,        // 1
        'agent_id'      => $agent_id,       // 2
        'schedule_date' => $date,           // 3
        'schedule_time' => $time,           // 4
        'timezone'      => $timezone,       // 5
        'contact'       => $contact,        // 6
        'note'          => $note,           // 7
        'status'        => 'pending',       // 8
        'created_at'    => current_time( 'mysql' ),  // 9
        'updated_at'    => current_time( 'mysql' ),  // 10
    ),
    array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )  // Only 9!
);
```

### Impact
- **Severity**: Critical
- **Affected Feature**: Callback scheduling (core plugin feature)
- **User Impact**: Users unable to schedule callbacks; database insert operations would fail silently
- **Error Type**: Silent failure - no error message shown to user, but operation fails

### Root Cause
When the `updated_at` field was added to the data array, the corresponding format specifier (`%s`) was not added to the format array, resulting in a count mismatch that causes `$wpdb->insert()` to fail.

## Fix

### Changes Made
Added the missing 10th format specifier to match the 10 data fields:

```php
// Before (9 format specifiers)
array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )

// After (10 format specifiers)
array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
```

### Field-to-Format Mapping
| # | Field | Type | Format |
|---|-------|------|--------|
| 1 | user_id | integer | %d |
| 2 | agent_id | integer | %d |
| 3 | schedule_date | string | %s |
| 4 | schedule_time | string | %s |
| 5 | timezone | string | %s |
| 6 | contact | string | %s |
| 7 | note | string | %s |
| 8 | status | string | %s |
| 9 | created_at | string | %s |
| 10 | updated_at | string | %s |

## Testing

### Validation Script
Run the validation script to verify the fix:
```bash
php pax-support-pro/tests/validate-scheduler-fix.php
```

### Unit Tests
Unit tests have been created in `test-scheduler-insert.php` to:
1. Verify format array count matches data array count
2. Verify format specifiers match data types
3. Test successful database insert with correct format
4. Test that insert fails with incorrect format count (the original bug)

### Manual Testing
To manually test the fix:
1. Activate the plugin in WordPress
2. Navigate to the callback scheduler interface
3. Fill out the callback form with valid data
4. Submit the form
5. Verify the callback is successfully created in the database
6. Check the `wp_pax_schedules` table for the new record

## Prevention
To prevent similar issues in the future:
1. Always count data fields and format specifiers when using `$wpdb->insert()` or `$wpdb->update()`
2. Use array_keys() and count() to validate before database operations
3. Add unit tests for all database operations
4. Enable WordPress debug mode during development to catch database errors

## Related Code
This same pattern is used in other files that should be reviewed:
- `rest/ticket.php` - ticket creation (appears correct)
- `rest/scheduler.php` - schedule updates (should be reviewed)
- Other database insert/update operations throughout the plugin

## References
- WordPress Codex: [$wpdb->insert()](https://developer.wordpress.org/reference/classes/wpdb/insert/)
- WordPress Codex: [Data Validation](https://developer.wordpress.org/apis/security/data-validation/)
