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
        // Optional logo URL (prefer /uploads/... set via admin settings).
        'logo' => '',
    ],
    'mail' => [
        'admin_email' => 'admin@example.com',
        'smtp_host' => 'localhost',
    ],
    'system' => [
        'cache_enabled' => false,
        // Micro-cache public HTML responses (short TTL).
        'micro_cache' => true,
        // Micro-cache TTL in seconds.
        'micro_cache_ttl' => 10,
        // Minify HTML output for public pages.
        'minify_html' => true,
        // Skip scheduler/admin checks for public GETs without a session cookie.
        'fast_public' => true,
        // Add cache headers to static assets (themes/components/assets).
        'asset_cache' => true,
        // Asset cache TTL in seconds (default: 1 year).
        'asset_cache_ttl' => 31536000,
        // Optional cache headers for uploads (off by default).
        'uploads_cache' => false,
        // Add lazy-loading attributes to markdown images.
        'lazy_images' => true,
        // Persist rendered markdown output (mtime keyed).
        'render_cache' => true,
        // Cache sitemap markup (mtime keyed).
        'sitemap_cache' => true,
        // Cache admin file trees (mtime keyed).
        'tree_cache' => true,
        // Cache inline markdown renders (per request).
        'inline_cache' => true,
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
    'components' => [
        // Registry manifests for optional components.
        'registries' => [
            'https://raw.githubusercontent.com/clientcoffee/flint-components/main/manifest.json',
        ],
    ],
    'updates' => [
        // Auto-update behavior: true = auto-install, false = notify only, 'ask' = prompt before installing
        'auto_update' => 'ask',
    ],
];
