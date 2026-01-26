# Hooks and Helper Functions

Flint keeps template helpers small and readable so layouts stay focused on markup.
These helpers are available in theme layouts, views, and theme components.

## template helpers

- `esc_html($value)` – hardened HTML escaping for any output in templates.
- `e($value)` – shorthand for `esc_html()`.
- `render_block($name, $fallback = '')` – render a markdown block by name.
- `render_assets($position = 'foot', $assets = null)` – output component assets for `head` or `foot`.
- `theme_asset($path, $themeName = null)` – build a URL for the active theme asset.
- `page_meta($key, $default = '')` – escaped access to page frontmatter values.

## hook helpers

- `hook($name, $context = [])` – trigger a hook by name.
- `theme_styles()` – wrapper for the `theme_styles` hook.
- `theme_scripts()` – wrapper for the `theme_scripts` hook.

## layout usage example

```php
<link rel="stylesheet" href="<?= theme_asset('tailwind.min.css') ?>">
<?php render_assets('head'); ?>
<?php theme_styles(); ?>

<nav>
  <?= render_block('nav') ?>
</nav>

<?php render_assets('foot'); ?>
<?php theme_scripts(); ?>
```
