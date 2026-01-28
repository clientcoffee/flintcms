# Flint Directory Structure

## Overview

Flint keeps **core CMS files** and **site content** separate. Updates only replace core files so your content, themes, and configuration stay safe.

## Directory Layout

```
/flint/
├── app/                        # Core CMS (updated with releases)
│   ├── core/                   # Core PHP classes (App, Parser, Auth, etc.)
│   ├── assets/                 # Admin JS/CSS assets
│   ├── storage/                # Logs, cache, backups
│   ├── views/                  # Admin + error templates
│   ├── index.php               # Runtime entry point
│   └── index-dist.php          # Build-target entry point
├── site/                       # Your site bundle (never touched by updates)
│   ├── pages/                  # Markdown pages
│   ├── blocks/                 # Reusable content blocks
│   ├── components/             # Site components (shipped + custom)
│   ├── themes/                 # Themes
│   ├── uploads/                # User uploads
│   ├── submissions/            # Runtime submissions/logs
│   ├── config.example.php      # Copy to site/config.php
│   └── index.php               # Site entry (guards direct access)
├── dist/                       # Build output (app/ + site/)
├── public-docs/                # Public CMS documentation
├── docs/                       # Maintainer + contributor docs
├── scripts/                    # Build/release tooling
├── tests/                      # PHPUnit tests
├── composer.json
├── package.json
└── CHANGELOG.md
```

`site/config.php` is generated during setup and is not committed to git.

## Component Classification

### Core Runtime (in `app/core/`)

Core logic lives in `app/core/` (App, Parser, HookManager, Scheduler, etc.). These files are updated with releases.

### Site Components (in `site/components/`)

Site components are **shipped scaffolding** plus anything you add. They are **not** updated by the auto-update system, so treat them as part of your site.

Component enablement lives in each component's config file:

```php
// site/components/MyComponent/config.php
return [
    'component' => [
        'enabled' => true,
        'priority' => 100,
    ],
];
```

## Update Safety

### Updated by the auto-update system

- `app/` (core CMS files only)

### Never touched by updates

- `site/` directory (pages, themes, components, uploads)
- `site/config.php` (your configuration)
- `tests/` (if you add custom tests)

## Autoloading

The runtime autoloader maps namespaces like this:

```php
'Flint\\'      => app/core/
'Components\\' => site/components/
'\Modules\\'  => site/themes/
```

Site components in `site/components/` are loaded dynamically when enabled.

## Web Server Configuration

### Development

Point your web server (or PHP built-in server) to `app/`:

```bash
cd app
php -S localhost:8000
```

`app/index.php` resolves the site bundle and config from `../site/`.

### Production

**Option 1: DocumentRoot at app/**
```apache
DocumentRoot /var/www/flint/app
```

**Option 2: Symbolic link**
```bash
ln -s /var/www/flint/app/index.php /var/www/public/index.php
```

## Creating Custom Components

Place components in `site/components/`:

```
site/components/MyComponent/
├── MyComponent.php
└── config.php
```

```php
<?php
// site/components/MyComponent/MyComponent.php
namespace Components\MyComponent;

use Flint\RenderComponent;

class MyComponent extends RenderComponent
{
    public static function render(array $props, string $content): string
    {
        return "<div class='my-component'>{$content}</div>";
    }
}
```

## Creating Custom Themes

Place custom themes in `site/themes/`:

```
site/themes/mytheme/
├── layout.php           # Required: Main HTML wrapper
├── layout-post.php      # Optional: Post-specific layout
├── view.php             # Optional: Content wrapper
├── 404.php              # Optional: Custom 404 page
├── MyComponent.php      # Optional: Theme-specific components
├── style.css            # Optional: Theme styles
└── assets/              # Optional: Theme assets
```

Update `site/config.php` to use your theme:

```php
return [
    'site' => [
        'theme' => 'mytheme',
    ],
];
```

## Build Process

The build system creates `dist/` with both `app/` and `site/`:

```
dist/
├── app/                     # Core CMS
└── site/                    # Site bundle (includes config.example.php)
```

See `docs/BUILD.md` for details.

## Best Practices

1. **Never edit `app/` in production** - Updates overwrite it
2. **Keep themes in `site/themes/`** - Safe from updates
3. **Keep custom components in `site/components/`** - Safe from updates
4. **Version control `site/` (minus uploads)** - Your unique site content
5. **Do not commit `site/config.php`** - Contains passwords and settings
6. **Back up `site/config.php`** - Critical for restores

## Upgrading Flint

To upgrade to a new version:

1. Backup your `site/` directory and `site/config.php`
2. Replace the `app/` directory with the new version (or use the update system)
3. Check `site/config.example.php` for new configuration options
4. Test your site

## Summary

```
app/           = Core CMS (updated)
site/          = Your site bundle (never touched by updates)
site/config.php = Your config (never touched by updates)
```
