<?php

/**
 * Flint Configuration Example
 *
 * Copy this file to site/config.php and update with your settings.
 * The password should be hashed using password_hash().
 * Run setup to generate a proper site/config.php with hashed password.
 *
 * This file contains sensitive configuration. Keep secure permissions (0600).
 * DO NOT commit site/config.php to version control.
 */

return [
    'site' => [
        'name' => 'Flint',
        'theme' => 'motion',
        'website' => 'https://example.com',
    ],
    'mail' => [
        'admin_email' => 'admin@example.com',
        'smtp_host' => 'localhost',
    ],
    'system' => [
        'cache_enabled' => false,
        // Internal environment flag: development, local, or production
        'environment' => 'production',
        // Show detailed errors (SECURITY: Only enable during development!)
        // false = Production mode (generic errors, logs only)
        // true = Development mode (detailed errors with file, line, trace)
        'show_errors' => false,
    ],
    'admin' => [
        // Password must be hashed with password_hash()
        // Example: password_hash('your-password', PASSWORD_DEFAULT)
        'password' => '$2y$10$example.hash.replace.with.real.hashed.password',
    ],
    'updates' => [
        // Auto-update behavior: true = auto-install, false = notify only, 'ask' = prompt before installing
        'auto_update' => 'ask',
        // Check for updates on admin login
    ],
];
