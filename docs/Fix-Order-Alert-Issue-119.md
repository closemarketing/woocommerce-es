# Fix: Order Sync Alert Behavior (Issue #119)

## 🐛 Problem

The plugin was displaying alerts **every time** an order was sent to the API, regardless of whether the request was successful or not. This behavior was confusing for users, as alerts should only appear when an actual error occurs.

### Symptoms
- Alert shown on **every** API request when an order is sent
- Alerts appear even when API response indicates success
- Users believe something went wrong when it actually succeeded
- Misleading user experience during normal operation
- Unnecessary support requests

### Impact
- Poor user experience
- Confusion about order status
- Users unsure if orders were successfully sent
- Increased support burden

## 🔍 Root Cause

**Location:** `includes/Admin/Orders.php` - `sync_erp_order()` method

**The Problem:**

```php
// OLD CODE - BUGGY
public function sync_erp_order() {
    // ... 
    if ( 'erp-post' === $type ) {
        $result = ORDER::create_invoice( ... );
    }
    // Always sends success, regardless of actual result!
    wp_send_json_success(
        array(
            'message'  => $result['message'] ?? '',
            'order_id' => $order_id,
        )
    );
}
```

**The Issue:**
1. The AJAX handler **always** called `wp_send_json_success()` with the result message
2. It never checked `$result['status']` to determine if the operation actually succeeded
3. JavaScript always displayed the message, treating everything as success

**JavaScript Side:**
```javascript
// OLD CODE - sync-order-widget.js
.then( (response) => {
    button_sync.innerHTML = ConEcom_ajaxActionOrder.label_synced;
    button_sync.insertAdjacentHTML( 'afterend', '<p>' + response.data.message + '</p>' );
})
```

The JavaScript always showed the message from the server, regardless of success or error.

## ✅ Solution

### 1. Fixed PHP AJAX Handler

**File:** `includes/Admin/Orders.php`

```php
public function sync_erp_order() {
    if ( ! check_ajax_referer( 'sync_erp_order_nonce', 'nonce' ) ) {
        wp_send_json_error( array( 'error' => 'Error' ) );
    }
    $order_id = isset( $_POST['order_id'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : 0;
    $type     = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';

    $result = array(
        'status'  => 'error',
        'message' => __( 'Invalid request type', 'woocommerce-es' ),
    );

    if ( 'erp-post' === $type ) {
        $result = ORDER::create_invoice( $this->settings, $order_id, $this->meta_key_order, $this->options['slug'], $this->connapi_erp, true );
    }

    // ✅ NEW: Check result status and respond accordingly
    if ( isset( $result['status'] ) && 'error' === $result['status'] ) {
        wp_send_json_error(
            array(
                'message'  => $result['message'] ?? __( 'Unknown error occurred', 'woocommerce-es' ),
                'order_id' => $order_id,
            )
        );
    } else {
        wp_send_json_success(
            array(
                'message'  => $result['message'] ?? __( 'Order sent successfully', 'woocommerce-es' ),
                'order_id' => $order_id,
            )
        );
    }
}
```

**Key Changes:**
1. Check `$result['status']` before responding
2. Use `wp_send_json_error()` when status is 'error'
3. Use `wp_send_json_success()` only when status is not 'error'
4. Provide default messages if result message is missing

### 2. Fixed JavaScript Handler

**File:** `includes/assets/sync-order-widget.js`

```javascript
.then( (response) => {
    if ( response.success ) {
        // ✅ Success: Show success state without alert
        button_sync.innerHTML = ConEcom_ajaxActionOrder.label_synced;
        button_sync.insertAdjacentHTML( 'afterend', '<p style="color: green;">' + response.data.message + '</p>' );
        
        // Auto-hide success message after 5 seconds
        setTimeout(function() {
            var successMsg = button_sync.nextElementSibling;
            if (successMsg && successMsg.tagName === 'P') {
                successMsg.remove();
            }
        }, 5000);
    } else {
        // ✅ Error: Show alert and error message
        alert( 'Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error occurred') );
        button_sync.classList.remove('disabled');
        button_sync.setAttribute('onclick', 'syncOrderERP(' + order_id + ',\'' + element_id + '\',\'' + type + '\')');
        button_sync.innerHTML = ConEcom_ajaxActionOrder.label_syncing.replace('ing', '');
        button_sync.insertAdjacentHTML( 'afterend', '<p style="color: red;">' + (response.data ? response.data.message : 'Unknown error') + '</p>' );
    }
})
.catch(err => {
    // ✅ Network/Connection error handling
    console.log(err);
    alert( 'Connection error: Unable to sync order. Please try again.' );
    button_sync.classList.remove('disabled');
    button_sync.setAttribute('onclick', 'syncOrderERP(' + order_id + ',\'' + element_id + '\',\'' + type + '\')');
    button_sync.innerHTML = ConEcom_ajaxActionOrder.label_syncing.replace('ing', '');
});
```

**Key Changes:**
1. Check `response.success` to determine if operation succeeded
2. **Success**: Show green message, auto-hide after 5 seconds, NO alert
3. **Error**: Show alert, display red error message, re-enable button for retry
4. **Network Error**: Show alert, re-enable button, log to console

## 📊 Before vs After

### Before Fix:

| Scenario | Behavior | User Experience |
|----------|----------|-----------------|
| Success | Shows message (no differentiation) | ❌ Confusing |
| Error | Shows message (no differentiation) | ❌ Not obvious it's an error |
| Network Error | Silent failure | ❌ User doesn't know what happened |

**Example:**
```
✅ Order created: Invoice #12345
❌ Error connecting to API
```
Both look the same - just text appearing below the button!

### After Fix:

| Scenario | Behavior | User Experience |
|----------|----------|-----------------|
| Success | ✅ Green message, auto-hides after 5s | ✅ Clear success indication |
| Error | 🚨 Alert popup + red message | ✅ Obvious error, requires attention |
| Network Error | 🚨 Alert popup + re-enable button | ✅ User can retry |

**Example:**

**Success:**
```
Button changes to "Synced"
✅ "Order sent successfully" (in green)
[Message disappears after 5 seconds]
```

**Error:**
```
Alert popup: "Error: Failed to connect to API"
Button re-enabled for retry
❌ "Failed to connect to API" (in red, stays visible)
```

## 🔄 Backward Compatibility

✅ **Fully backward compatible**
- Existing code continues to work
- No breaking changes to API
- No changes to database
- Maintains all existing functionality

## 📈 Performance Impact

✅ **Positive impact**
- Slightly better due to proper status checking
- No additional network requests
- Cleaner error handling

## 🎯 User Experience Improvements

### Success Flow
1. User clicks "Send to ERP" button
2. Button shows "Syncing..." with spinner
3. Request completes successfully
4. Button changes to "Synced"
5. ✅ Green success message appears
6. Message auto-hides after 5 seconds
7. **No alert popup** (non-intrusive)

### Error Flow
1. User clicks "Send to ERP" button
2. Button shows "Syncing..." with spinner
3. Request fails
4. 🚨 **Alert popup** appears with error message
5. User acknowledges alert
6. ❌ Red error message stays visible
7. Button re-enabled for retry
8. User can try again

### Network Error Flow
1. User clicks "Send to ERP" button
2. Button shows "Syncing..." with spinner
3. Network connection fails
4. 🚨 **Alert popup**: "Connection error: Unable to sync order. Please try again."
5. Button re-enabled
6. Error logged to console
7. User can retry when connection restored

## 📝 Files Changed

```
modified:   includes/Admin/Orders.php
            - sync_erp_order() method: Added status checking logic
            
modified:   includes/assets/sync-order-widget.js
            - syncOrderERP() function: Added success/error differentiation
            - Added auto-hide for success messages
            - Added error handling with alerts
            - Added network error handling
            
modified:   readme.txt
            - Updated changelog

new file:   docs/Fix-Order-Alert-Issue-119.md (this file)
```

## 🧪 Testing

### Manual Testing

1. **Test Success Scenario**:
   ```
   - Configure valid API credentials
   - Create a test order
   - Click "Send to ERP" button
   - ✅ Verify: Green success message appears, no alert
   - ✅ Verify: Message disappears after 5 seconds
   - ✅ Verify: Order is created in ERP
   ```

2. **Test Error Scenario**:
   ```
   - Configure invalid API credentials (or disable API temporarily)
   - Create a test order
   - Click "Send to ERP" button
   - ✅ Verify: Alert popup appears with error message
   - ✅ Verify: Red error message appears and stays visible
   - ✅ Verify: Button is re-enabled for retry
   - ✅ Verify: Order is NOT created in ERP
   ```

3. **Test Network Error**:
   ```
   - Disconnect network
   - Create a test order
   - Click "Send to ERP" button
   - ✅ Verify: Alert popup appears with connection error
   - ✅ Verify: Button is re-enabled
   - ✅ Verify: Can retry after reconnecting
   ```

4. **Test Retry After Error**:
   ```
   - Trigger an error (step 2 above)
   - Fix the issue (e.g., restore API credentials)
   - Click "Send to ERP" button again
   - ✅ Verify: Order syncs successfully this time
   ```

### Browser Console

Check for any JavaScript errors:
```javascript
// Should see no errors in success case
// Should see logged error in network failure case
```

## 🔍 Integration Points

### This fix integrates with:

1. **ORDER::create_invoice()** - Returns result with 'status' and 'message'
2. **ALERT::send_order_error_alert()** - Still called for errors (unchanged)
3. **WooCommerce Order Meta** - Order sync status stored in meta (unchanged)

### Existing Alert System

The existing `ALERT` helper still works and sends email/Slack notifications for errors. This fix only affects the **UI** alerts shown to admin users in the WordPress admin panel.

**Alert Flow:**
1. Order sync fails
2. `ORDER::create_invoice()` returns `status: 'error'`
3. `ALERT::send_order_error_alert()` sends notification to admin
4. AJAX handler returns error response
5. JavaScript shows alert popup to user

## 📚 Related Code

### ORDER::create_invoice() Return Format

```php
// Success
return array(
    'status'  => 'ok',
    'message' => 'Invoice created: #12345',
    'invoice_id' => 12345,
);

// Error
return array(
    'status'  => 'error',
    'message' => 'Failed to connect to API',
);
```

### wp_send_json_success() Format

```javascript
// Success response
{
    success: true,
    data: {
        message: "Order sent successfully",
        order_id: 123
    }
}

// Error response
{
    success: false,
    data: {
        message: "Failed to connect to API",
        order_id: 123
    }
}
```

## 💡 Additional Notes

### Why Auto-Hide Success Messages?

Success messages auto-hide after 5 seconds because:
- ✅ Reduces visual clutter
- ✅ User already saw the success state (button changed to "Synced")
- ✅ Common UX pattern for non-critical notifications
- ✅ Error messages stay visible (more important)

### Why Alert Popups for Errors?

Error alerts are shown because:
- ✅ Requires user attention
- ✅ Indicates something needs to be fixed
- ✅ Prevents users from continuing without addressing the issue
- ✅ Common UX pattern for errors requiring action

### Alternative Approaches Considered

1. **Toast Notifications**: Would require additional library
2. **WordPress Admin Notices**: Not visible in order edit screen
3. **WooCommerce Notices**: Not applicable for admin backend
4. **Current Approach**: Simple, effective, no dependencies ✅

## 🚀 Deployment

This fix can be deployed safely:
1. No breaking changes
2. Improves existing functionality
3. Better user experience
4. No database changes required
5. No configuration changes needed

---

**Fixed by:** Proper status checking in AJAX handler and JavaScript response handling  
**Type:** Bug Fix  
**Priority:** Medium (UX improvement)  
**Version:** n.e.x.t

## 🔗 References

- Issue #119: Plugin shows alert every time an order is sent to the API
- Related: ALERT helper class for email/Slack notifications
- Related: ORDER::create_invoice() for order submission

