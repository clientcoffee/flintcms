# Configuration

## config.php schema

```php
<?php

return [
    'site' => [
        'name' => 'My Site',
        'theme' => 'motion',
        'website' => 'https://example.com',
    ],
    'mail' => [
        'admin_email' => 'you@example.com',
        'smtp_host' => 'localhost',
    ],
    'system' => [
        'cache_enabled' => false,
        'show_errors' => false,
    ],
    'admin' => [
        'password' => '$2y$10$replace-with-password-hash',
    ],
    'updates' => [
        'auto_update' => 'ask',
    ],
];
```

- `app/config.example.php` stores the template; do not ship `app/config.php`.
- `app/core/Setup.php` creates `app/config.php` once after verifying no prior install exists.
- `admin.password` must be a `password_hash()` value; never store plaintext.
- The CMS loads `app/config.php` on each request; avoid committing it.
- `site.domain` is injected at runtime from `$_SERVER['HTTP_HOST']`/`$_SERVER['SERVER_NAME']` (fallback to absolute `$_SERVER['REQUEST_URI']`), so it does not need to be set manually.
- `site.website` is stored in config and seeded during setup for use in email and link generation.

## components configuration

- Component settings live in `content/components/<name>/config.php` and must `return` a PHP array.
- `component.enabled` controls loading; `component.priority` controls order.
- For optional per-component overrides, store JSON/YAML alongside the component and load it manually.

## theme configuration

- Themes can define `content/themes/<name>/config.php` and return an array under `theme` and `settings`.
- Theme config is loaded by `app/core/App.php` and passed into layouts alongside `site` data.
