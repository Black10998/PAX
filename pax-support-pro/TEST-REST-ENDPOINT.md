# REST Endpoint Test - v5.5.0

## Endpoint: /pax-support-pro/v1/chat

### Test 1: Ping Check (GET)
```bash
curl -X GET "https://yourdomain.com/wp-json/pax-support-pro/v1/chat?ping=1"
```

**Expected Response:**
```json
{
  "status": "ok",
  "time": 1699123456
}
```

**HTTP Status:** 200 OK

---

### Test 2: Ping Check (HEAD)
```bash
curl -I "https://yourdomain.com/wp-json/pax-support-pro/v1/chat?ping=1"
```

**Expected Response:**
```
HTTP/1.1 200 OK
Content-Type: application/json
```

**HTTP Status:** 200 OK

---

### Test 3: Basic Status (GET without ping)
```bash
curl -X GET "https://yourdomain.com/wp-json/pax-support-pro/v1/chat"
```

**Expected Response:**
```json
{
  "status": "online",
  "version": "5.5.0"
}
```

**HTTP Status:** 200 OK

---

### Test 4: Chat Message (POST)
```bash
curl -X POST "https://yourdomain.com/wp-json/pax-support-pro/v1/chat" \
  -H "Content-Type: application/json" \
  -d '{"message": "Hello", "session": "test-123"}'
```

**Expected Response:**
```json
{
  "reply": "...",
  "status": "online",
  "language": "en",
  "session": "test-123",
  "suggestions": []
}
```

**HTTP Status:** 200 OK

---

## Browser Test

Open browser console and run:

```javascript
// Test 1: Ping with fetch
fetch('/wp-json/pax-support-pro/v1/chat?ping=1')
  .then(r => r.json())
  .then(data => console.log('Ping response:', data));

// Test 2: Ping with HEAD
fetch('/wp-json/pax-support-pro/v1/chat?ping=1', { method: 'HEAD' })
  .then(r => console.log('HEAD response status:', r.status));

// Test 3: Basic status
fetch('/wp-json/pax-support-pro/v1/chat')
  .then(r => r.json())
  .then(data => console.log('Status response:', data));
```

---

## Expected Behavior

### Before v5.5.0:
- ❌ GET/HEAD requests return 404 Not Found
- ❌ Ping checks fail in assets.js
- ❌ Console shows: "Failed to load resource: the server responded with a status of 404"

### After v5.5.0:
- ✅ GET requests with ?ping=1 return {"status":"ok","time":...}
- ✅ HEAD requests return 200 OK
- ✅ GET requests without ping return {"status":"online","version":"5.5.0"}
- ✅ POST requests continue to work for chat messages
- ✅ No 404 errors in console

---

## Implementation Details

### File: rest/chat.php

**Changes:**
1. Updated `pax_sup_register_chat_route()` to register multiple methods
2. Added `pax_sup_rest_chat_ping()` callback for GET/HEAD requests
3. Maintained existing POST functionality for chat messages

**Code:**
```php
register_rest_route(
    PAX_SUP_REST_NS,
    '/chat',
    array(
        array(
            'methods'             => WP_REST_Server::CREATABLE, // POST
            'permission_callback' => 'pax_sup_rest_require_read_permission',
            'callback'            => 'pax_sup_rest_chat',
        ),
        array(
            'methods'             => WP_REST_Server::READABLE, // GET, HEAD
            'permission_callback' => '__return_true',
            'callback'            => 'pax_sup_rest_chat_ping',
        ),
    )
);
```

---

## Troubleshooting

### Issue: Still getting 404
**Solution:** Clear WordPress rewrite rules
```php
// In WordPress admin, go to Settings → Permalinks and click "Save Changes"
// Or run in wp-cli:
wp rewrite flush
```

### Issue: Permission denied
**Solution:** Check if `__return_true` is working
```php
// The ping endpoint uses __return_true for permission_callback
// This allows public access for ping checks
```

### Issue: Wrong response format
**Solution:** Check if PAX_SUP_VER constant is defined
```php
// Should be defined in pax-support-pro.php
define( 'PAX_SUP_VER', '5.5.0' );
```

---

## Security Considerations

- **Ping endpoint:** Public access (no authentication required)
- **Chat endpoint:** Requires read permission (authenticated users)
- **Rate limiting:** Applied to chat messages, not ping checks
- **Content validation:** Ping checks don't process user input

---

## Performance Impact

- **Minimal:** Ping checks return immediately without database queries
- **Caching:** No caching needed for ping responses
- **Load:** Ping checks run every 30 seconds per active chat session
- **Bandwidth:** ~50 bytes per ping request/response

---

## Version History

### v5.5.0 (2025-11-04)
- ✅ Added GET/HEAD support for /chat endpoint
- ✅ Added pax_sup_rest_chat_ping() callback
- ✅ Fixed 404 errors for ping checks
- ✅ Maintained backward compatibility with POST requests

### v5.4.9 (2025-11-04)
- Fixed element ID mismatch
- All menu buttons functional

---

## Related Files

- `rest/chat.php` - REST endpoint registration and callbacks
- `public/assets.js` - Ping check implementation (line 155)
- `pax-support-pro.php` - Plugin loader and constants
- `includes/helpers.php` - Permission callbacks
