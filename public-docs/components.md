# Components

## Component lookup order

Flint resolves components in this order:

1. Theme components in `site/themes/<theme>/` (`Modules\\ComponentName`)
2. Site components in `site/components/` (`Components\\ComponentName`)

This allows themes to override site components for styling.

## Component registry (optional)

Optional components live in the `flint-components` registry. The admin UI reads a JSON manifest and installs ZIP packages into `site/components/`.

Default registry:
- `https://raw.githubusercontent.com/clientcoffee/flint-components/main/manifest.json`

You can add custom registries in `site/config.php`:

```php
return [
    'components' => [
        'registries' => [
            'https://raw.githubusercontent.com/clientcoffee/flint-components/main/manifest.json',
            // 'https://example.com/my-registry.json',
        ],
    ],
];
```

## Minimal component example

`site/components/Callout/Callout.php`:

```php
<?php

namespace Components;

class Callout
{
    public static function render(array $props, string $content): string
    {
        $type = htmlspecialchars($props['type'] ?? 'info', ENT_QUOTES, 'UTF-8');
        return "<div class=\"callout callout-{$type}\">{$content}</div>";
    }

    public static function getAssets(): array
    {
        return [
            'styles' => ['/components/Callout/style.css'],
        ];
    }
}
```

Optional config file: `site/components/Callout/config.php`

```php
<?php

return [
    'component' => [
        'name' => 'Callout',
        'slug' => 'callout',
        'version' => '1.0.0',
        'author' => 'Your Name',
        'description' => 'Callout box component.',
        'type' => 'render',
        'enabled' => true,
    ],
];
```

## Asset registration

Components can declare assets via `getAssets()`:

- `styles` and `scripts` add external CSS/JS links.
- `inline_styles` and `inline_scripts` inject inline blocks.

Assets live under `/components/<ComponentName>/` and are served from `site/components/<ComponentName>/`.

## Naming and namespaces

- `site/components/MyWidget/MyWidget.php` should define `namespace Components; class MyWidget`.
- Theme components live in `site/themes/<theme>/` and use `namespace Modules;`.

## Enabling and settings

Component metadata is stored in `site/components/<name>/config.php`.
Set `component.enabled` to `true` or `false` to control loading.
