# PAX Support Pro v5.4.9 - Testing Guide

## Overview
This version implements comprehensive diagnostic logging and dual execution approach (event dispatch + direct fallback) for all unified chat menu buttons.

## Changes in v5.4.9

### 1. Enhanced Logging in pax-unified-chat.js
- All action methods now log function entry, event dispatch, and direct fallback attempts
- Methods enhanced: openNewTicket, openTroubleshooter, openDiagnostics, openCallback, openOrderLookup, openMyRequest, openFeedback, openDonate, openWhatsNew, toggleSpeed

### 2. Enhanced Logging in assets.js
- All event listeners now log event reception, function type checks, and execution
- window.paxDebug initialization now logs all exposed functions
- Consistent "PAX-ASSETS:" prefix for easy filtering

### 3. Dual Execution Approach
Each menu action now:
1. Dispatches a CustomEvent (e.g., 'pax-open-ticket-modal')
2. Attempts direct fallback via window.paxDebug object
3. Logs every step for diagnostic purposes

## Testing Instructions

### Phase 1: Console Function Tests
Open browser console and test each function directly:

```javascript
// Test 1: Check window.paxDebug availability
console.log('window.paxDebug:', window.paxDebug);

// Test 2: New Ticket Modal
window.paxDebug.openTicketModal();

// Test 3: Troubleshooter Modal
window.paxDebug.troubleModal.open();

// Test 4: Diagnostics Modal
window.paxDebug.openDiagnosticsModal();

// Test 5: Schedule/Callback Modal
window.paxDebug.openScheduleModal();

// Test 6: Order Lookup Modal
window.paxDebug.orderModal.open();

// Test 7: My Request Modal
window.paxDebug.openMyRequestModal();

// Test 8: Feedback Modal
window.paxDebug.feedbackModal.open();

// Test 9: Speed Toggle
window.paxDebug.setSpeed(true);  // Enable
window.paxDebug.setSpeed(false); // Disable
```

### Phase 2: Event Dispatch Tests
Test event system directly:

```javascript
// Test 1: New Ticket Event
document.dispatchEvent(new CustomEvent('pax-open-ticket-modal'));

// Test 2: Troubleshooter Event
document.dispatchEvent(new CustomEvent('pax-open-troubleshooter'));

// Test 3: Diagnostics Event
document.dispatchEvent(new CustomEvent('pax-open-diagnostics'));

// Test 4: Callback Event
document.dispatchEvent(new CustomEvent('pax-open-schedule-modal'));

// Test 5: Order Lookup Event
document.dispatchEvent(new CustomEvent('pax-open-order-modal'));

// Test 6: My Request Event
document.dispatchEvent(new CustomEvent('pax-open-myrequest'));

// Test 7: Feedback Event
document.dispatchEvent(new CustomEvent('pax-open-feedback'));

// Test 8: Speed Toggle Event
document.dispatchEvent(new CustomEvent('pax-toggle-speed'));
```

### Phase 3: Unified Chat Menu Tests
Click each menu button in the unified chat interface:

1. **New Ticket** - Should open ticket creation modal
2. **Troubleshooter** - Should open troubleshooter modal
3. **Diagnostics** - Should open diagnostics modal
4. **Callback** - Should open schedule callback modal
5. **Order Lookup** - Should open order lookup modal
6. **My Request** - Should open my request modal
7. **Feedback** - Should open feedback modal
8. **Donate** - Should open donate URL in new tab
9. **What's New** - Should open what's new URL in new tab
10. **Speed Toggle** - Should toggle speed mode

### Phase 4: Console Log Analysis
After clicking each button, check console for this sequence:

```
PAX: handleMenuAction called with action: [action-name]
PAX: [ActionName] called
PAX: Dispatching [event-name] event
PAX: Event dispatched
PAX: Calling [function-name] directly as fallback
PAX-ASSETS: Received [event-name] event
PAX-ASSETS: [function-name] type: function
PAX-ASSETS: Calling [function-name]()
PAX-ASSETS: [function-name]() called successfully
```

### Phase 5: Settings Integration Test
1. Go to WordPress Admin → PAX Support Pro → Settings
2. Disable specific menu items (e.g., Troubleshooter, Diagnostics)
3. Refresh frontend
4. Verify disabled items don't appear in menu
5. Check console for: `PAX: Skipping menu item '[key]' - not visible in settings`

### Phase 6: Responsive Testing

#### Desktop (1920x1080)
- [ ] All menu items visible
- [ ] Modals open correctly
- [ ] No layout issues

#### Tablet (768x1024)
- [ ] Menu scrollable if needed
- [ ] Modals responsive
- [ ] Touch interactions work

#### Mobile (375x667)
- [ ] Menu accessible
- [ ] Modals fit screen
- [ ] Safe area insets respected

## Expected Console Output Examples

### Successful Button Click (New Ticket)
```
PAX: handleMenuAction called with action: ticket
PAX: Opening New Ticket
PAX: openNewTicket called
PAX: User logged in, proceeding
PAX: Dispatching pax-open-ticket-modal event
PAX: Event dispatched
PAX: Calling openTicketModal directly as fallback
PAX-ASSETS: Received pax-open-ticket-modal event
PAX-ASSETS: openTicketModal type: function
PAX-ASSETS: Calling openTicketModal()
PAX-ASSETS: openTicketModal() called successfully
```

### Login Required (Not Logged In)
```
PAX: openNewTicket called
PAX: User not logged in, showing toast
```

### Disabled Menu Item
```
PAX: Skipping menu item 'troubleshooter' - not visible in settings
```

## Troubleshooting

### If Modal Doesn't Open
1. Check console for error messages
2. Verify window.paxDebug is populated: `console.log(window.paxDebug)`
3. Check if event listener received event (look for "PAX-ASSETS: Received")
4. Verify function exists: `console.log(typeof window.paxDebug.openTicketModal)`

### If Event Not Received
1. Check if event is dispatched (look for "PAX: Event dispatched")
2. Verify event name matches listener
3. Check browser console for JavaScript errors

### If Direct Fallback Fails
1. Check if window.paxDebug exists
2. Verify function is exposed in window.paxDebug
3. Check console for "PAX: Calling [function] directly as fallback"

## Success Criteria

✅ All Phase 1 tests open modals/execute functions
✅ All Phase 2 tests trigger event listeners
✅ All Phase 3 tests work from UI clicks
✅ Console logs show complete execution flow
✅ Settings integration respects enabled/disabled items
✅ All responsive tests pass
✅ No JavaScript errors in console

## Version Update Checklist

After successful testing:
- [ ] Update version to 5.4.9 in pax-support-pro.php
- [ ] Update version comment in pax-unified-chat.js
- [ ] Update version comment in assets.js
- [ ] Build production ZIP
- [ ] Test production build
- [ ] Commit changes
- [ ] Push to GitHub

## Notes

- All logging uses consistent prefixes: "PAX:" for unified chat, "PAX-ASSETS:" for assets.js
- Dual execution ensures fallback if event system fails
- window.paxDebug provides direct access for testing and debugging
- Comprehensive logging enables precise troubleshooting
