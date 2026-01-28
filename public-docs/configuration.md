# Configuration

## Minimal config example

Create `site/config.php` (or run the setup flow) and start with:

```php
<?php

return [
    'site' => [
        'name' => 'My Site',
        'theme' => 'motion',
        'website' => 'https://example.com',
    ],
    'admin' => [
        'password' => '$2y$10$replace-with-password-hash',
    ],
];
```

## Config file locations

- `site/config.php` is the live configuration file.
- `site/config.example.php` is a template; copy it to start.
- Do not commit `site/config.php` to version control.

## Component registries

Optional components are served from registry manifests. Configure them in `site/config.php`:

```php
'components' => [
    'registries' => [
        'https://raw.githubusercontent.com/clientcoffee/flint-components/main/manifest.json',
    ],
],
```

## Performance (runtime)

These settings keep page loads fast without any build step:

```php
'system' => [
    'micro_cache' => true,      // short TTL HTML cache for public pages
    'micro_cache_ttl' => 10,    // seconds
    'minify_html' => true,      // remove whitespace between tags
    'fast_public' => true,      // skip scheduler/admin checks for public GETs
    'asset_cache' => true,      // add cache headers to static assets
    'asset_cache_ttl' => 31536000, // seconds (1 year)
    'uploads_cache' => false,   // optional cache headers for uploads
    'lazy_images' => true,      // add loading="lazy" to markdown images
    'render_cache' => true,     // cache parsed markdown by file mtime
    'sitemap_cache' => true,    // cache sitemap markup by file mtime
    'tree_cache' => true,       // cache admin file trees by file mtime
    'inline_cache' => true,     // cache inline markdown per request
],
```

## Updates settings

`updates.auto_update` controls how updates are applied:

- `true` — auto-install updates
- `false` — notify only
- `ask` — prompt in the admin UI before installing (default)

Example:

```php
'updates' => [
    'auto_update' => 'ask',
],
```

## Permissions and security

- Keep `site/config.php` readable only by the web server user (`0600` recommended).
- Always store the admin password using `password_hash()`.

## Theme selection

```php
'site' => [
    'theme' => 'motion',
],
```
