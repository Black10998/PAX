# Feature: File Attachments for Tickets

## Overview
This feature adds comprehensive file upload and attachment support to the PAX Support Pro ticket system. Users can attach files to tickets via drag-and-drop or file selection, with secure storage and display.

## Features Implemented

### 1. Database Schema
- **New Table**: `wp_pax_attachments`
  - `id`: Unique attachment identifier
  - `ticket_id`: Associated ticket ID
  - `message_id`: Associated message ID
  - `user_id`: User who uploaded the file
  - `file_name`: Original filename
  - `file_path`: Stored filename (unique)
  - `file_type`: MIME type
  - `file_size`: File size in bytes
  - `created_at`: Upload timestamp

### 2. Secure File Storage
- **Location**: `wp-content/uploads/pax-support-pro/attachments/`
- **Security Measures**:
  - `.htaccess` file prevents directory listing
  - `index.php` prevents direct directory access
  - Unique filenames prevent overwrites and guessing
  - File type validation using MIME types
  - File size limits (default: 5MB)
  - Extension whitelist

### 3. Allowed File Types
- **Images**: JPG, JPEG, PNG, GIF
- **Documents**: PDF, DOC, DOCX, XLS, XLSX, TXT
- **Archives**: ZIP

### 4. User Interface

#### File Upload Methods
1. **Click to Upload**: Click attachment button to open file picker
2. **Drag and Drop**: Drag files directly into chat area
3. **Multiple Files**: Support for multiple file attachments per ticket

#### Visual Elements
- Attachment button with paperclip icon
- File preview area showing selected files
- Drag-and-drop overlay with visual feedback
- File size and type display
- Remove button for each attachment
- Image thumbnails in ticket view
- Download links for non-image files

### 5. REST API Endpoints

#### Upload Attachments
- **Endpoint**: `POST /wp-json/pax/v1/ticket`
- **Method**: Multipart form data
- **Fields**: 
  - Standard ticket fields (subject, message, etc.)
  - `attachment0`, `attachment1`, etc. for files
- **Response**: Includes uploaded attachment details

#### Delete Attachment
- **Endpoint**: `DELETE /wp-json/pax/v1/attachment/{id}`
- **Permission**: Owner or admin
- **Response**: Success/failure status

#### Download Attachment
- **Endpoint**: `GET /wp-json/pax/v1/attachment/{id}/download`
- **Permission**: Ticket owner or admin
- **Response**: File URL and metadata

### 6. Security Features

#### File Validation
- MIME type verification
- File extension whitelist
- File size limits
- Empty file detection
- Malicious filename sanitization

#### Access Control
- Users can only access attachments from their own tickets
- Admins can access all attachments
- Direct file access requires authentication
- Unique filenames prevent guessing

#### Server-Side Protection
- `.htaccess` restricts file types
- Directory listing disabled
- Index file prevents browsing
- WordPress media handling integration

## File Structure

```
pax-support-pro/
├── includes/
│   ├── attachments.php          # Core attachment handling
│   └── helpers.php               # Updated with attachment table
├── rest/
│   ├── attachment.php            # Attachment REST endpoints
│   └── ticket.php                # Updated ticket endpoint
├── public/
│   ├── assets.css                # Attachment UI styles
│   ├── assets.js                 # Upload and drag-drop logic
│   └── chat.php                  # Updated UI with file input
└── tests/
    └── test-file-attachments.php # Unit tests
```

## Usage

### For Users

#### Attaching Files to Tickets
1. Open the ticket creation form
2. Click the paperclip icon or drag files into the chat area
3. Selected files appear in preview area
4. Remove unwanted files by clicking the X button
5. Submit ticket with attachments

#### Viewing Attachments
- Images display as thumbnails (clickable to view full size)
- Documents show as download links with file info
- File size displayed for all attachments

### For Developers

#### Adding Custom File Types
```php
add_filter( 'pax_sup_allowed_file_types', function( $types ) {
    $types['svg'] = 'image/svg+xml';
    return $types;
} );
```

#### Changing Max File Size
```php
add_filter( 'pax_sup_max_file_size', function( $size ) {
    return 10 * MB_IN_BYTES; // 10MB
} );
```

#### Handling Attachment Events
```php
// After attachment saved
add_action( 'pax_sup_attachment_saved', function( $attachment_id, $ticket_id ) {
    // Custom logic
}, 10, 2 );
```

## API Reference

### Functions

#### `pax_sup_handle_file_upload( $file, $ticket_id, $user_id )`
Handles file upload with validation and storage.

**Parameters:**
- `$file` (array): File array from `$_FILES`
- `$ticket_id` (int): Ticket ID
- `$user_id` (int): User ID

**Returns:** Array with file info or WP_Error

#### `pax_sup_save_attachment( $ticket_id, $message_id, $user_id, $file_info )`
Saves attachment record to database.

**Parameters:**
- `$ticket_id` (int): Ticket ID
- `$message_id` (int): Message ID
- `$user_id` (int): User ID
- `$file_info` (array): File information

**Returns:** Attachment ID or false

#### `pax_sup_get_ticket_attachments( $ticket_id )`
Retrieves all attachments for a ticket.

**Parameters:**
- `$ticket_id` (int): Ticket ID

**Returns:** Array of attachments

#### `pax_sup_delete_attachment( $attachment_id )`
Deletes attachment file and database record.

**Parameters:**
- `$attachment_id` (int): Attachment ID

**Returns:** Boolean success status

## Testing

### Unit Tests
Run the test suite:
```bash
phpunit tests/test-file-attachments.php
```

### Manual Testing Checklist
- [ ] Upload single file via button
- [ ] Upload multiple files via button
- [ ] Drag and drop single file
- [ ] Drag and drop multiple files
- [ ] Remove file from preview
- [ ] Submit ticket with attachments
- [ ] View attachments in ticket detail
- [ ] Download non-image attachment
- [ ] View image attachment (full size)
- [ ] Delete attachment (owner)
- [ ] Verify file size limit
- [ ] Verify file type restriction
- [ ] Test access control (other users)
- [ ] Test admin access to all attachments

## Performance Considerations

### Optimization
- Files stored with unique names for browser caching
- Image thumbnails generated on-the-fly
- Lazy loading for attachment lists
- Efficient database queries with indexes

### Limits
- Default max file size: 5MB (configurable)
- Recommended max attachments per ticket: 10
- Supported concurrent uploads: Based on server configuration

## Security Considerations

### Implemented Protections
1. **File Type Validation**: Whitelist approach
2. **MIME Type Verification**: Server-side checking
3. **File Size Limits**: Prevents DoS attacks
4. **Unique Filenames**: Prevents overwrites and guessing
5. **Access Control**: Permission-based downloads
6. **Directory Protection**: .htaccess and index.php
7. **Sanitization**: Filename and path sanitization
8. **WordPress Integration**: Uses wp_handle_upload()

### Best Practices
- Regularly review uploaded files
- Monitor disk space usage
- Implement file retention policies
- Enable virus scanning if available
- Use CDN for large file serving

## Browser Compatibility
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile browsers (iOS Safari, Chrome Mobile)

## Known Limitations
1. No virus scanning (requires third-party integration)
2. No automatic image optimization
3. No file versioning
4. No bulk download feature
5. No attachment search functionality

## Future Enhancements
- [ ] Image compression and optimization
- [ ] Video file support
- [ ] Attachment preview modal
- [ ] Bulk attachment operations
- [ ] Attachment search and filtering
- [ ] File versioning system
- [ ] Integration with cloud storage (S3, Google Drive)
- [ ] Virus scanning integration
- [ ] Automatic file cleanup for old tickets

## Changelog

### Version 1.2.0 (2025-10-30)
- Initial implementation of file attachments
- Drag-and-drop support
- Secure file storage
- REST API endpoints
- Unit tests
- Documentation

## Support
For issues or questions about file attachments:
- Check the documentation
- Review unit tests for examples
- Contact support team
- Submit bug reports via GitHub

## License
This feature is part of PAX Support Pro and follows the same GPL v3.0 license.
