# Background Import Process Enhancement

## Overview

The Connect Ecommerce plugin has been enhanced with a robust background import process that allows users to:
- Import products in the background without keeping the browser tab open
- Pause, resume, and stop imports at any time
- Track progress in real-time with live log updates
- Resume imports from the exact point where they were stopped

## Features

### 1. Background Processing
- Uses WordPress Action Scheduler for reliable background execution
- Import continues even if the user closes the browser tab or navigates away
- Processes one product at a time to avoid memory issues and timeouts

### 2. Import Control
- **Start**: Initiates a new background import process
- **Pause**: Temporarily pauses the import, saving current progress
- **Resume**: Continues from where it was paused
- **Stop**: Completely stops the import (progress is saved for reference)

### 3. Real-Time Monitoring
- Live progress display showing:
  - Current product being processed
  - Total products synced
  - Number of errors encountered
- Auto-updating log with color-coded messages:
  - **Blue**: Success messages
  - **Red**: Error messages
  - **Orange**: Warning messages
  - **Gray**: Informational messages

### 4. Progress Persistence
- Import state is saved in WordPress options
- Logs are stored and accessible even after page reload
- Old completed imports are automatically cleaned up after 7 days

## Technical Implementation

### Components

#### 1. BACKGR Class
**Location**: `/includes/Helpers/BACKGR.php`

**Key Methods**:
- `start($config)`: Initializes a new import process
- `pause()`: Pauses the current import
- `resume()`: Resumes a paused import
- `stop()`: Stops the import completely
- `process_batch($args)`: Processes a single product (called by Action Scheduler)
- `add_log($message, $type)`: Adds a log entry
- `get_logs($process_id, $offset, $limit)`: Retrieves logs for display

**Storage**:
- State: `wp_options` table with key `conecom_import_state`
- Logs: `wp_options` table with key `conecom_import_logs`

#### 2. AJAX Endpoints
**Location**: `/includes/Admin/Import_Products.php`

- `conecom_start_background_import`: Starts a new import
- `conecom_pause_import`: Pauses current import
- `conecom_resume_import`: Resumes paused import
- `conecom_stop_import`: Stops current import
- `conecom_get_import_status`: Gets current status and logs (polling endpoint)

#### 3. JavaScript Handler
**Location**: `/includes/assets/background-import.js`

**Key Functions**:
- `init()`: Initializes the handler and checks for existing processes
- `startImport()`: Starts a new import via AJAX
- `pauseImport()`: Pauses the current import
- `resumeImport()`: Resumes a paused import
- `stopImport()`: Stops the import with confirmation
- `startPolling()`: Begins polling for status updates every 2 seconds
- `updateUI(data)`: Updates the UI with current status and logs

#### 4. User Interface
**Location**: `/includes/Admin/Settings.php`

The UI includes:
- Import control buttons (Start, Pause, Resume, Stop)
- Progress information display
- AI generation options selector
- Real-time log viewer
- Legacy manual import option (fallback)

### Data Flow

1. **Starting an Import**:
   ```
   User clicks "Start" 
   → AJAX call to conecom_start_background_import
   → BACKGR creates process
   → Schedules first batch in Action Scheduler
   → Returns process_id to frontend
   → JavaScript starts polling for status
   ```

2. **Processing Products**:
   ```
   Action Scheduler executes conecom_process_import_batch
   → BACKGR::process_batch()
   → Gets product from API
   → Calls PROD::sync_product_item()
   → Logs result
   → Updates state
   → Schedules next batch
   ```

3. **Status Polling**:
   ```
   JavaScript polls every 2 seconds
   → AJAX call to conecom_get_import_status
   → Returns current state and new logs
   → UI updates with progress and logs
   → Continues until status is 'completed' or 'stopped'
   ```

4. **Pausing/Resuming**:
   ```
   User clicks "Pause"
   → AJAX call to conecom_pause_import
   → Updates state to 'paused'
   → Cancels scheduled Action Scheduler jobs
   → Stops polling
   
   User clicks "Resume"
   → AJAX call to conecom_resume_import
   → Updates state to 'running'
   → Schedules next batch
   → Resumes polling
   ```

## Database Schema

The enhancement uses WordPress options for storage:

### conecom_import_state
```php
array(
    'process_id_1' => array(
        'status'         => 'running|paused|stopped|completed',
        'config'         => array(...),
        'current_loop'   => 0,
        'total_products' => 100,
        'synced_count'   => 45,
        'error_count'    => 2,
        'api_products'   => array(...),
        'created_at'     => '2024-12-12 10:00:00',
        'updated_at'     => '2024-12-12 10:05:00',
        'started_at'     => '2024-12-12 10:00:00',
        'finished_at'    => null
    ),
    ...
)
```

### conecom_import_logs
```php
array(
    'process_id_1' => array(
        array(
            'message'   => 'Product synced: Product Name',
            'type'      => 'success|error|warning|info',
            'timestamp' => '2024-12-12 10:00:15'
        ),
        ...
    ),
    ...
)
```

## Configuration

### Action Scheduler
The plugin uses Action Scheduler with a custom group: `conecom_imports`

To monitor Action Scheduler jobs:
- Go to: `Tools > Action Scheduler`
- Filter by group: `conecom_imports`

### Cleanup Schedule
Old processes are automatically cleaned up after 7 days. To manually trigger cleanup:

```php
CLOSE\ConnectEcommerce\Helpers\BACKGR::cleanup_old_processes( 7 );
```

## Usage

### For End Users

1. Navigate to: `WooCommerce > Connect Ecommerce > Synchronization > Products`
2. Select AI generation options (if desired)
3. Click "Start Background Import"
4. Monitor progress in real-time
5. Use Pause/Resume/Stop buttons as needed
6. Close the browser tab and return later to check progress

### For Developers

#### Starting a Custom Import Programmatically

```php
use CLOSE\ConnectEcommerce\Helpers\BACKGR;

$config = array(
	'generate_ai'    => 'none', // 'none', 'new', or 'all'.
	'api_pagination' => 50,
);

$handler    = new BACKGR();
$process_id = $handler->start( $config );
```

#### Getting Import Status

```php
// Get specific process state.
$state = BACKGR::get_state( $process_id );

// Or get the most recent active process.
$state = BACKGR::get_state();
```

#### Getting Logs

```php
// Get logs with pagination.
$logs = BACKGR::get_logs( $process_id, $offset = 0, $limit = 100 );

// On page load, start from offset 0 to get all logs.
$all_logs = BACKGR::get_logs( $process_id, 0, 500 );
```

## Troubleshooting

### Import Not Starting
- Check that Action Scheduler is working: `Tools > Action Scheduler`
- Verify WP-Cron is running (or use system cron)
- Check WordPress debug log for errors

### Import Stuck
- Check Action Scheduler for failed jobs
- Verify API connection in settings
- Check PHP memory limits and execution time

### Logs Not Updating
- Verify JavaScript console for errors
- Check browser network tab for failed AJAX requests
- Clear browser cache and reload page

### Resume Not Working
- Check that process_id is still valid
- Verify state hasn't been corrupted
- Check Action Scheduler queue

## Performance Considerations

- **Memory**: Each batch processes one product to minimize memory usage
- **Execution Time**: Action Scheduler handles timeout issues automatically
- **API Rate Limiting**: Products are processed sequentially to avoid overwhelming the API
- **Storage**: Logs are limited to 500 entries per process to prevent option bloat

## Security

- All AJAX endpoints verify WordPress nonces
- Only users with `manage_options` capability can control imports
- Input sanitization on all user-provided data
- Safe handling of API responses

## Log Updates

The import logs update in real-time while the import is running:
- JavaScript polls for status every 2 seconds
- New log entries are fetched incrementally
- When you return to the page, all previous logs are loaded from the beginning
- Logs are preserved even after closing and reopening the browser

This ensures you always see the complete import history, whether you stay on the page or return later.

## Future Enhancements

Potential improvements:
- Email notifications when import completes
- Batch processing (multiple products per job) for faster imports
- Import scheduling (start at specific time)
- Import filtering by product category or other criteria
- Import statistics and reporting
- Ability to export logs

## Testing Checklist

- [ ] Start a new import and verify it processes products
- [ ] Pause an active import and verify it stops
- [ ] Resume a paused import and verify it continues
- [ ] Stop an import and verify it terminates
- [ ] Close browser tab during import and return later
- [ ] Verify logs update in real-time
- [ ] Test with large product catalogs (1000+ products)
- [ ] Test with API errors and connection issues
- [ ] Test with different AI generation options
- [ ] Verify cleanup of old processes after 7 days
- [ ] Test concurrent imports (if applicable)
- [ ] Verify WP-CLI compatibility still works

## Support

For issues or questions:
- Check Action Scheduler status: `Tools > Action Scheduler`
- Enable WordPress debug logging
- Check browser console for JavaScript errors
- Review PHP error logs

## Changelog

### Version 3.4.0
- Added background import process with Action Scheduler
- Implemented pause/resume/stop functionality
- Added real-time log updates with polling
- Enhanced UI with import control buttons
- Added progress tracking and display
- Implemented automatic cleanup of old processes
