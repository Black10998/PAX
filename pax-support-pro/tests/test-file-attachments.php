<?php
/**
 * Test for file attachment functionality.
 *
 * @package PAX_Support_Pro
 */

/**
 * Test class for file attachments.
 */
class PAX_File_Attachments_Test extends WP_UnitTestCase {

    /**
     * Test attachments table creation.
     */
    public function test_attachments_table_exists() {
        global $wpdb;
        
        pax_sup_ensure_ticket_tables();
        
        $table = pax_sup_get_attachments_table();
        $query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table );
        $result = $wpdb->get_var( $query );
        
        $this->assertEquals( $table, $result, 'Attachments table should exist' );
    }

    /**
     * Test allowed file types.
     */
    public function test_allowed_file_types() {
        $allowed = pax_sup_get_allowed_file_types();
        
        $this->assertIsArray( $allowed, 'Allowed file types should be an array' );
        $this->assertArrayHasKey( 'jpg', $allowed, 'JPG should be allowed' );
        $this->assertArrayHasKey( 'pdf', $allowed, 'PDF should be allowed' );
        $this->assertArrayHasKey( 'png', $allowed, 'PNG should be allowed' );
        $this->assertEquals( 'image/jpeg', $allowed['jpg'], 'JPG mime type should be correct' );
    }

    /**
     * Test max file size.
     */
    public function test_max_file_size() {
        $max_size = pax_sup_get_max_file_size();
        
        $this->assertIsInt( $max_size, 'Max file size should be an integer' );
        $this->assertEquals( 5 * MB_IN_BYTES, $max_size, 'Default max size should be 5MB' );
    }

    /**
     * Test upload directory creation.
     */
    public function test_upload_directory_creation() {
        $upload_dir = pax_sup_get_upload_dir();
        
        $this->assertDirectoryExists( $upload_dir, 'Upload directory should exist' );
        $this->assertFileExists( $upload_dir . '/.htaccess', '.htaccess should exist for security' );
        $this->assertFileExists( $upload_dir . '/index.php', 'index.php should exist for security' );
    }

    /**
     * Test file validation - empty file.
     */
    public function test_validate_empty_file() {
        $file = array();
        $result = pax_sup_validate_file( $file );
        
        $this->assertInstanceOf( 'WP_Error', $result, 'Empty file should return WP_Error' );
        $this->assertEquals( 'no_file', $result->get_error_code() );
    }

    /**
     * Test file validation - file too large.
     */
    public function test_validate_large_file() {
        $file = array(
            'name'     => 'large.jpg',
            'type'     => 'image/jpeg',
            'size'     => 10 * MB_IN_BYTES, // 10MB
            'tmp_name' => '/tmp/test.jpg',
            'error'    => 0,
        );
        
        $result = pax_sup_validate_file( $file );
        
        $this->assertInstanceOf( 'WP_Error', $result, 'Large file should return WP_Error' );
        $this->assertEquals( 'file_too_large', $result->get_error_code() );
    }

    /**
     * Test file validation - invalid type.
     */
    public function test_validate_invalid_type() {
        $file = array(
            'name'     => 'test.exe',
            'type'     => 'application/x-msdownload',
            'size'     => 1024,
            'tmp_name' => '/tmp/test.exe',
            'error'    => 0,
        );
        
        $result = pax_sup_validate_file( $file );
        
        $this->assertInstanceOf( 'WP_Error', $result, 'Invalid file type should return WP_Error' );
        $this->assertEquals( 'invalid_file_type', $result->get_error_code() );
    }

    /**
     * Test file validation - valid file.
     */
    public function test_validate_valid_file() {
        $file = array(
            'name'     => 'test.jpg',
            'type'     => 'image/jpeg',
            'size'     => 1024 * 100, // 100KB
            'tmp_name' => '/tmp/test.jpg',
            'error'    => 0,
        );
        
        $result = pax_sup_validate_file( $file );
        
        $this->assertIsArray( $result, 'Valid file should return array' );
        $this->assertEquals( 'test.jpg', $result['name'] );
        $this->assertEquals( 'jpg', $result['ext'] );
        $this->assertEquals( 'image/jpeg', $result['type'] );
    }

    /**
     * Test save attachment to database.
     */
    public function test_save_attachment() {
        global $wpdb;
        
        pax_sup_ensure_ticket_tables();
        
        $file_info = array(
            'file_name' => 'test.jpg',
            'file_path' => 'test-123.jpg',
            'file_type' => 'image/jpeg',
            'file_size' => 1024,
        );
        
        $attachment_id = pax_sup_save_attachment( 1, 1, 1, $file_info );
        
        $this->assertIsInt( $attachment_id, 'Save attachment should return integer ID' );
        $this->assertGreaterThan( 0, $attachment_id, 'Attachment ID should be greater than 0' );
        
        // Clean up
        $table = pax_sup_get_attachments_table();
        $wpdb->delete( $table, array( 'id' => $attachment_id ), array( '%d' ) );
    }

    /**
     * Test get ticket attachments.
     */
    public function test_get_ticket_attachments() {
        global $wpdb;
        
        pax_sup_ensure_ticket_tables();
        
        $file_info = array(
            'file_name' => 'test.jpg',
            'file_path' => 'test-123.jpg',
            'file_type' => 'image/jpeg',
            'file_size' => 1024,
        );
        
        $attachment_id = pax_sup_save_attachment( 999, 1, 1, $file_info );
        
        $attachments = pax_sup_get_ticket_attachments( 999 );
        
        $this->assertIsArray( $attachments, 'Get ticket attachments should return array' );
        $this->assertCount( 1, $attachments, 'Should have 1 attachment' );
        $this->assertEquals( 'test.jpg', $attachments[0]['file_name'] );
        $this->assertTrue( $attachments[0]['is_image'], 'JPEG should be marked as image' );
        
        // Clean up
        $table = pax_sup_get_attachments_table();
        $wpdb->delete( $table, array( 'id' => $attachment_id ), array( '%d' ) );
    }

    /**
     * Test format file size.
     */
    public function test_format_file_size() {
        $this->assertEquals( '1.00 KB', pax_sup_format_file_size( 1024 ) );
        $this->assertEquals( '1.00 MB', pax_sup_format_file_size( 1048576 ) );
        $this->assertEquals( '1.00 GB', pax_sup_format_file_size( 1073741824 ) );
        $this->assertEquals( '500 bytes', pax_sup_format_file_size( 500 ) );
    }

    /**
     * Test delete attachment.
     */
    public function test_delete_attachment() {
        global $wpdb;
        
        pax_sup_ensure_ticket_tables();
        
        $file_info = array(
            'file_name' => 'test.jpg',
            'file_path' => 'test-delete-123.jpg',
            'file_type' => 'image/jpeg',
            'file_size' => 1024,
        );
        
        $attachment_id = pax_sup_save_attachment( 1, 1, 1, $file_info );
        
        $deleted = pax_sup_delete_attachment( $attachment_id );
        
        $this->assertTrue( $deleted, 'Delete attachment should return true' );
        
        // Verify deletion
        $table = pax_sup_get_attachments_table();
        $result = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $attachment_id )
        );
        
        $this->assertNull( $result, 'Attachment should be deleted from database' );
    }

    /**
     * Test attachment security - directory listing disabled.
     */
    public function test_directory_listing_disabled() {
        $upload_dir = pax_sup_get_upload_dir();
        $htaccess   = $upload_dir . '/.htaccess';
        
        $this->assertFileExists( $htaccess, '.htaccess should exist' );
        
        $content = file_get_contents( $htaccess );
        $this->assertStringContainsString( 'Options -Indexes', $content, '.htaccess should disable directory listing' );
    }
}
