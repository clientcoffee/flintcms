<?php

/**
 * Backups Component Configuration.
 *
 * Automated site backup with secure 24-hour download links.
 */

return [
    'component' => [
        'name' => 'Backups',
        'version' => '1.0.0',
        'author' => 'Flint',
        'description' => 'Automated site backup with secure 24-hour download links',
        'enabled' => false,
        'priority' => 100,
    ],
    'backups' => [
        // Link expiration in seconds (default: 24 hours).
        'link_expiration' => 86400,

        // Maximum number of backups to keep.
        'max_backups' => 10,

        // Include in backup.
        'include_config' => true,
        'include_content' => true,
        'include_themes' => false,
        'include_uploads' => true,

        // Automatic backup schedule.
        // Options: manual, hourly, daily, weekly, monthly, interval.
        // - manual: Only create backups when manually triggered from admin panel.
        // - hourly: Run backup every hour.
        // - daily: Run backup once per day at specified time.
        // - weekly: Run backup once per week on specified day at specified time.
        // - monthly: Run backup once per month on specified day at specified time.
        // - interval: Run backup every N seconds.
        'schedule' => 'manual',

        // Schedule time (for daily/weekly/monthly schedules).
        // Format: HH:MM (24-hour format, e.g., "03:00" for 3 AM).
        'schedule_time' => '03:00',

        // Schedule day.
        // For weekly: 0 = Sunday, 1 = Monday, ..., 6 = Saturday.
        // For monthly: 1-28 (day of month, kept to 28 for safety).
        'schedule_day' => 0,

        // Schedule interval in seconds (for interval schedule).
        // Default: 86400 (24 hours).
        'schedule_interval' => 86400,
    ],
];
