# Flint Directory Structure

## Overview

Flint uses a clear separation between **core CMS files** (which get updated) and **user content** (which is safe from updates).

## Directory Layout

```
/flint/
├── app/                      # Core CMS (updated with new releases)
│   ├── core/                 # Core PHP classes
│   │   ├── App.php          # Main application
│   │   ├── Parser.php       # MDX-Lite parser
│   │   ├── Auth.php         # Authentication
│   │   ├── Admin.php        # Admin interface
│   │   └── Setup.php        # Initial setup
│   ├── core/components/      # Core components ONLY
│   │   ├── Nav.php          # Navigation component (core)
│   │   └── Block/           # Block inclusion component (core)
│   ├── index.php             # Application entry point
│   ├── public/               # Public assets
│   └── README.md             # App documentation
│
├── site/                  # Your content (SAFE from updates)
│   ├── pages/               # Your pages (.md, .mdx files)
│   │   ├── index.md
│   │   ├── about.md
│   │   └── ...
│   ├── blocks/              # Reusable content blocks
│   │   ├── nav.md
│   │   ├── contact-form.md
│   │   └── ...
│   ├── uploads/             # User-uploaded media
│   │   └── YYYY-MM/        # Organized by year-month
│   ├── components/          # Your custom components + shipped non-core
│   │   ├── FormField/      # Shipped with CMS (non-core)
│   │   ├── Accordion.php   # Shipped example
│   │   ├── Mermaid/        # Shipped example
│   │   └── ...             # Your custom components
│   └── themes/              # Your themes
│       └── motion/         # Default shipped theme
│
├── config.php                # Your configuration (SAFE from updates)
├── config.example.php        # Example configuration
├── composer.json             # Development dependencies
├── phpunit.xml               # Test configuration
└── tests/                    # Test suite
```

## Component Classification

### Core Components (in `app/core/components/`)

These components are **required** for CMS functionality and must exist:

- **Nav.php** - Navigation rendering
- **Block/** - Block inclusion system

Core components are updated with CMS releases.

### Non-Core Components (in `site/components/`)

These components are **optional** and safe from updates:

- **FormField/** - Ships with CMS for contact forms
- **Accordion.php** - Shipped example component
- **CTACard.php** - Shipped example component
- **Lightbox.php** - Shipped example component
- **Mermaid/** - Shipped diagram component
- **ProgressBar.php** - Shipped example component
- **SocialLinks.php** - Shipped example component
- **TeamMemberCard.php** - Shipped example component

Plus any custom components you create.

## Update Safety

### Files Updated During CMS Updates

- `app/` directory (entire contents)
- `config.example.php` (reference only)
- `BUILD.md`, `TESTING.md`, etc. (documentation)

### Files NEVER Touched by Updates

- `site/` directory (all contents)
- `config.php` (your actual configuration)
- `tests/` (if you add custom tests)

## Autoloading

The autoloader maps namespaces to directories:

```php
'Flint\\'       => app/core/                  // Core classes
'Components\\' => app/core/components/       // Core components
'Modules\\'    => site/themes/            // Theme components
```

User components in `site/components/` are loaded dynamically by the Parser.

## Web Server Configuration

### Development

Point your web server or PHP built-in server to the `app/` directory:

```bash
cd app
php -S localhost:8000
```

The `app/index.php` will automatically find `site/` and `config.php` in the parent directory.

### Production

**Option 1: DocumentRoot at app/**
```apache
DocumentRoot /var/www/flint/app
```

**Option 2: Symbolic link**
```bash
ln -s /var/www/flint/app/index.php /var/www/public/index.php
```

The app will automatically resolve paths to `../site/` and `../config.php`.

## Creating Custom Components

Place custom components in `site/components/`:

```php
<?php
// site/components/MyComponent.php
namespace Components;

class MyComponent {
    public static function render(array $props, string $content): string {
        return "<div class='my-component'>{$content}</div>";
    }
}
```

Or keep it minimal (still use the Components namespace):
```php
<?php
// site/components/SimpleComponent.php
namespace Components;

class SimpleComponent {
    public static function render(array $props, string $content): string {
        return "<div>{$content}</div>";
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

Update `config.php` to use your theme:
```ini
[site]
theme = mytheme
```

## Build Process

The build system creates `dist/` with both `app/` and `site/`:

```
dist/
├── app/                    # Core CMS
├── site/                # User content (no uploads/ to save space)
└── config.example.php      # Config template
```

See `BUILD.md` for details.

## Migration from Old Structure

If you have an old Flint installation:

1. **Move your files**:
   ```bash
   mv app/site/pages site/pages
   mv app/site/blocks site/blocks
   mv app/site/uploads site/uploads
   mv app/themes/mytheme site/themes/mytheme
   mv app/config.php config.php
   ```

2. **Update custom components** - Move from `app/core/components/` to `site/components/`

3. **Update references** - Content/theme paths remain the same (`/site/pages/`, `/themes/`, etc.)

4. **Test** - Everything should work without code changes

## Best Practices

1. **Never edit files in `app/`** - Your changes will be lost on updates
2. **Keep themes in `site/themes/`** - Safe from updates
3. **Keep custom components in `site/components/`** - Safe from updates
4. **Version control `site/` and `config.php`** - Your unique site content
5. **Don't version control `site/uploads/`** - Large media files
6. **Backup `config.php`** - Contains passwords and settings

## Upgrading Flint

To upgrade to a new version:

1. Backup your `site/` directory and `config.php`
2. Replace the `app/` directory with new version
3. Check `config.example.php` for new configuration options
4. Test your site
5. Your themes, components, and content remain unchanged

## Summary

```
app/      = Core CMS (update this)
site/  = Your site (never touched by updates)
config.php = Your config (never touched by updates)
```

This structure ensures clean updates while protecting your customizations.
