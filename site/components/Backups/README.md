# Backups Component

Automated site backup system with secure time-limited download links and flexible scheduling.

## Features

- **Complete Site Backups**: Archives site/config.php and entire content directory
- **Tarball Compression**: Creates .tar.gz archives for efficient storage
- **Secure Downloads**: 64-character token-based URLs valid for 24 hours
- **Email Delivery**: Automatically emails admin with download link
- **Auto-Cleanup**: Removes expired backups and enforces backup retention limits
- **Flexible Scheduling**: Manual, hourly, daily, weekly, monthly, or interval-based backups
- **Zero Configuration**: Works out of the box with sensible defaults

## Installation

1. Enable the component in `site/components/Backups/config.php`:
   ```php
   <?php

   return [
       'component' => [
           'enabled' => true,
       ],
       // ...
   ];
   ```

2. Configure your admin email in `site/config.php`:
   ```php
   <?php

   return [
       'mail' => [
           'admin_email' => 'admin@example.com',
           'smtp_host' => 'localhost',
       ],
       // ...
   ];
   ```

3. The component will automatically create required directories:
   - `site/uploads/yyyy-mm/` - Backup storage (organized by year-month)
   - `site/submissions/backups/` - Metadata storage

## Configuration

Edit `site/components/Backups/config.php`:

### Basic Settings

```php
'backups' => [
    // Backups are stored in site/uploads/yyyy-mm/ (organized by year-month)
    // Storage location is automatic and date-based.

    // Link expiration in seconds (default: 24 hours)
    'link_expiration' => 86400,

    // Maximum number of backups to keep (oldest deleted first)
    'max_backups' => 10,
],
```

### What to Include

```php
'backups' => [
    // Include site/config.php in backup
    'include_config' => true,

    // Include entire content directory
    'include_content' => true,

    // Include theme files (usually not needed)
    'include_themes' => false,

    // Include uploaded files (can be large)
    'include_uploads' => true,
],
```

### Automatic Scheduling

```php
'backups' => [
    // Backup schedule type
    // Options: manual, hourly, daily, weekly, monthly, interval
    'schedule' => 'manual',

    // Time of day for scheduled backups (24-hour format)
    'schedule_time' => '03:00',

    // Day for weekly/monthly backups
    // Weekly: 0=Sunday, 1=Monday, ..., 6=Saturday
    // Monthly: 1-28 (day of month)
    'schedule_day' => 0,

    // Interval in seconds (for interval schedule)
    'schedule_interval' => 86400,
],
```

## Schedule Types

### Manual (Default)
```php
'backups' => [
    'schedule' => 'manual',
],
```
- Backups only created when triggered from admin panel
- Best for small sites or infrequent updates

### Hourly
```php
'backups' => [
    'schedule' => 'hourly',
],
```
- Creates backup once every hour
- Good for high-traffic sites with frequent content changes
- Watch disk space usage

### Daily
```php
'backups' => [
    'schedule' => 'daily',
    'schedule_time' => '03:00',
],
```
- Creates one backup per day at specified time
- Most common choice for production sites
- Default time: 3:00 AM (low traffic period)

### Weekly
```php
'backups' => [
    'schedule' => 'weekly',
    'schedule_time' => '03:00',
    'schedule_day' => 0,
],
```
- Creates backup once per week on specified day
- Good for sites with weekly content cycles
- `schedule_day`: 0=Sunday, 1=Monday, ..., 6=Saturday

### Monthly
```php
'backups' => [
    'schedule' => 'monthly',
    'schedule_time' => '03:00',
    'schedule_day' => 1,
],
```
- Creates backup once per month on specified day
- Good for low-change sites or long-term archival
- `schedule_day`: 1-28 (kept to 28 for safety across all months)

### Interval
```php
'backups' => [
    'schedule' => 'interval',
    'schedule_interval' => 86400,
],
```
- Creates backup every N seconds
- Flexible for custom timing needs
- Default: 86400 seconds (24 hours)

## How It Works

### Automatic Backups

When automatic scheduling is enabled:

1. **Lightweight Check**: On each page load (not static assets), the scheduler checks if a backup is due
2. **Lock Protection**: Uses lock files to prevent concurrent execution
3. **Execution**: If due, creates backup in background
4. **Email Notification**: Sends download link to admin email
5. **Cleanup**: Removes expired backups and enforces retention limit

The scheduler is intelligent:
- Won't run twice in the same period (daily, weekly, monthly)
- Respects configured time windows
- Uses file locks to prevent overlapping backups
- Gracefully handles failures

### Manual Backups

Trigger from admin panel:
1. Click "Create Backup" button
2. Backup process runs immediately
3. Email sent with download link
4. Link valid for 24 hours

## Security

### Download Links

- **Token-Based**: 64-character random hex token (2^256 possibilities)
- **Time-Limited**: Links expire after 24 hours
- **One-Time Use**: Files deleted after expiration
- **No Guessing**: Tokens are cryptographically random

### Route Registration

- Uses hook system for custom routes (`custom_routes`)
- Download route: `/backup/download/{token}`
- No hardcoded routes in core

### API Endpoints

- Admin authentication required
- Registered via `custom_api_endpoints` hook
- Endpoints:
  - `POST /api/backup/create` - Create backup (admin only)
  - `GET /api/backup/list` - List all backups (admin only)

### Storage Protection

- Backup files stored outside web root structure
- `.htaccess` denies direct web access
- Only accessible via authenticated API or token URL

## Backup Contents

A typical backup includes:

```
backup-20250104-123456-abc123.tar.gz
├── site/config.php               (if include_config = true)
└── site/
    ├── pages/                    (if include_content = true)
    ├── blocks/                   (if include_content = true)
    ├── components/               (if include_content = true)
    ├── submissions/              (if include_content = true)
    ├── themes/                   (if include_themes = true)
    └── uploads/                  (if include_uploads = true)
```

## Email Format

When a backup is created, admin receives:

```
Subject: [YourSite] Site Backup Ready

Your backup is ready for download:

Backup Details:
- Created: 2025-01-04 03:00:00
- Size: 2.4 MB
- Filename: backup-20250104-030000-abc123.tar.gz

Download Link (valid for 24 hours):
https://yoursite.com/backup/download/[64-char-token]

This link will expire on: 2025-01-05 03:00:00

Important:
- Do not share this link
- Link expires automatically after 24 hours
- Backup file will be deleted after expiration
- Store backup in a secure location
```

## API Usage

### Create Backup

```javascript
fetch('/api/backup/create', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  }
})
.then(res => res.json())
.then(data => {
  console.log(data);
  // {
  //   success: true,
  //   backup_id: "20250104-123456-abc123",
  //   filename: "backup-20250104-123456-abc123.tar.gz",
  //   size: 2458321,
  //   expires: 1735891200,
  //   email_sent: true
  // }
});
```

### List Backups

```javascript
fetch('/api/backup/list')
.then(res => res.json())
.then(data => {
  console.log(data.backups);
  // [
  //   {
  //     backup_id: "20250104-123456-abc123",
  //     filename: "backup-20250104-123456-abc123.tar.gz",
  //     created: 1735804800,
  //     expires: 1735891200,
  //     expired: false,
  //     size: 2458321,
  //     size_formatted: "2.34 MB",
  //     downloaded: false
  //   }
  // ]
});
```

## Restoring from Backup

1. Download backup via email link
2. Extract tarball:
   ```bash
   tar -xzf backup-20250104-123456-abc123.tar.gz
   ```
3. Copy files to site root:
   ```bash
   # Restore config (careful - overwrites current config)
   cp site/config.php /path/to/flint/site/config.php

   # Restore content
   cp -r site/* /path/to/site/site/
   ```
4. Verify site is working

## Troubleshooting

### Backups Not Creating

1. Check component is enabled in site/components/Backups/config.php
2. Verify `tar` command is available: `which tar`
3. Check directory permissions (needs write access)
4. Review scheduler state: `site/submissions/scheduler/state/backups_automatic.json`

### Email Not Sending

1. Verify admin email is configured in `site/config.php`
2. Check PHP mail configuration
3. Review form submission logs for errors
4. Test mail function: `php -r "mail('test@example.com', 'Test', 'Body');"`

### Scheduler Not Running

1. Ensure site is receiving traffic (scheduler runs on page loads)
2. Check for lock files: `site/submissions/scheduler/locks/`
3. Verify schedule configuration in site/components/Backups/config.php
4. Stale locks (>5 minutes) are automatically cleaned up

### Download Link Expired

1. Backups expire after 24 hours (configurable via `link_expiration`)
2. Create a new backup from admin panel
3. Consider increasing `link_expiration` if needed
4. Files are automatically deleted after expiration

## Performance Considerations

### Backup Size

Large backups can:
- Consume significant disk space
- Take time to create
- Increase email attachment size (if ever added)

Recommendations:
- Set `include_uploads = false` for sites with many large files
- Adjust `max_backups` based on available disk space
- Monitor `site/uploads/` directory size (backups are in yyyy-mm subdirectories)

### Scheduling

- Hourly backups: High overhead, only for critical sites
- Daily backups: Recommended for most sites
- Weekly/Monthly: Good for low-change sites

### Lock Timeouts

- Locks expire after 5 minutes
- If backup takes longer, lock may expire prematurely
- Adjust lock timeout in Scheduler.php if needed

## Architecture

### Modular Design

The Backups component is completely modular:
- No core file modifications required
- Extends system via hooks
- Self-contained in `site/components/Backups/`

### Hooks Used

- `admin_panel_load` - Adds backup UI to admin panel
- `custom_routes` - Registers download route
- `custom_api_endpoints` - Registers API endpoints
- `register_scheduled_tasks` - Registers automatic backup task

### Dependencies

- Core Scheduler system (`app/core/Scheduler.php`)
- BaseComponent class for helpers
- HookManager for event system
- PHP `tar` command for compression

## License

Part of Flint. Same license as core system.
