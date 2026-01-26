# Hooks and Helper Functions

Hooks let you extend Flint without editing core files. Use them inside components to add behavior or modify data at specific points in the request lifecycle.

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

## Hook example

Register a hook in a component:

```php
<?php

use Flint\HookManager;

HookManager::on('request_start', function (array $context): void {
    // Inspect or gate requests early
});
```

## Common hooks

- `request_start` – first hook in the request lifecycle (security, throttling).
- `render_page` – before page render (modify payload).
- `form_validate` – validate form submissions.
- `form_submit` – run after a successful form submission.

## Where hooks run

- Hooks are registered by components during boot.
- Hooks run when the core calls `HookManager::run()` for each event.

## Safety notes

- Validate any data you accept in hooks.
- Keep `request_start` fast and side-effect free where possible.

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
