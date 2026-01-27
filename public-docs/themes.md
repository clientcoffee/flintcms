# Motion Theme Guide

The Motion theme is the default Flint theme. It pairs a clean layout with Tailwind CSS, a small set of theme components, and a light content presentation layer. This guide documents how the theme works, how to customize it, and how to build new features with its existing pieces.

## quick start

1. Set the theme in `site/config.php`:
   ```php
   <?php

   return [
       'site' => [
           'name' => 'My Site',
           'theme' => 'motion',
       ],
       // ...
   ];
   ```
2. Edit the navigation block in `site/blocks/nav.md`.
3. Update page frontmatter to control titles, descriptions, and layout types.


## theme anatomy

- `layout.php` — global HTML wrapper.
- `view.php` — content wrapper and typography.
- `helpers.php` — theme-specific helpers.
- `config.php` — theme metadata and settings.

## file map

- `site/themes/motion/layout.php` - Default page wrapper (header, nav, footer, assets).
- `site/themes/motion/layout-post.php` - Layout used when frontmatter `type: post`.
- `site/themes/motion/view.php` - Page content wrapper and Motion-specific typography.
- `site/themes/motion/helpers.php` - Theme helpers (icon slug mapping).
- `site/themes/motion/config.php` - Theme metadata and optional settings.
- `site/themes/motion/404.php` - Custom 404 page.
- `site/themes/motion/quotes.json` - Quotes consumed by the 404 page.
- `site/themes/motion/tailwind.min.css` - Local Tailwind build used by layouts.
- `site/themes/motion/style.css` - Optional additional styling (not linked by default).
- `site/themes/motion/*.php` - Theme components in the `Modules` namespace.


## assets

- Theme assets live in `site/themes/<theme>/`.
- Use `theme_asset('tailwind.min.css')` to build URLs for theme files.
- Component assets are injected automatically from `$componentAssets`.

## layout cascade

Flint chooses a layout based on the `type` frontmatter field:

- `layout-{type}.php` renders when it exists (for example, `layout-post.php`).
- `layout.php` is the default fallback.

Example frontmatter that uses `layout-post.php`:

```markdown
---
title: A Strong Opening
type: post
author: Alex Rivera
date: 2024-06-01
readtime: 6
description: A longer article with a hero header and reading progress.
---
```

## layout.php behavior

`layout.php` is the master template. It:

- Builds the HTML shell and `<title>` tag from `site.name` and `page.meta.title`.
- Injects the Tailwind stylesheet from `/themes/motion/tailwind.min.css`.
- Renders component assets from `$componentAssets` (external styles, inline styles, and scripts).
- Loads the navigation block from `site/blocks/nav.md`.
- Shows the admin link when `$isAdmin` is true.

The layout receives these key variables:

- `$site` - site settings from `site/config.php`.
- `$page` - parsed frontmatter and content payload.
- `$viewContent` - HTML produced by `view.php`.
- `$componentAssets` - CSS/JS arrays gathered by the parser.
- `$isAdmin` - admin status for showing controls.
- `$themeConfig` - theme configuration from `site/themes/motion/config.php`.

## view.php behavior

`view.php` wraps rendered content and applies Motion typography. It reads metadata from frontmatter:

- `title` - main page title.
- `description` - short subtitle under the title.
- `icon` - slug passed to `getEmojiFromSlug()` in `helpers.php`.
- `banner` - image URL (relative paths are prefixed with `/site`).

Example frontmatter for the Motion view:

```markdown
---
title: About the Studio
description: Our process, values, and how we work.
icon: sparkles
banner: /uploads/about-hero.jpg
keywords: studio, design, process
---
```

## theme configuration

Motion ships a theme config file that is exposed as `$themeConfig` in layouts, views, and the 404 page. You can use these values to drive layout decisions.

```php
<?php

return [
    'theme' => [
        'name' => 'Motion',
        'version' => '1.0.0',
        'author' => 'Flint',
        'description' => 'Modern, clean theme with Tailwind CSS and smooth animations',
        'parent' => '',
    ],
    'settings' => [
        'use_local_tailwind' => true,
        'dark_mode_enabled' => false,
        'primary_color' => '#4F46E5',
        'secondary_color' => '#7C3AED',
        'font_family' => 'system-ui, -apple-system, sans-serif',
        'max_content_width' => '1200px',
        'sidebar_width' => '300px',
    ],
];
```

Note: the default layout does not auto-apply these settings. Wire them into your templates if you want to use them.

## extending motion

You extend Motion by activating a theme that declares Motion as its parent. Set `theme` to your child theme and add `parent => 'motion'` in the child theme config.

### child theme config

```php
<?php

return [
    'theme' => [
        'name' => 'Motion Child',
        'version' => '0.1.0',
        'author' => 'Your Name',
        'description' => 'Motion with custom layout and palette.',
        'parent' => 'motion',
    ],
    'settings' => [
        'primary_color' => '#0F766E',
        'secondary_color' => '#14B8A6',
        'font_family' => 'system-ui, -apple-system, sans-serif',
    ],
];
```

### activate the child theme

```php
return [
    'site' => [
        'theme' => 'motion-child',
    ],
];
```

### inheritance behavior

- Flint resolves the active theme first, then falls back to `parent` for missing files.
- `settings` are merged parent → child (child wins).
- Parent `helpers.php` loads before child `helpers.php`.
- Child themes can override `layout.php`, `layout-*.php`, `view.php`, `404.php`, and any theme components in `site/themes/<child>/*.php`.

### directory layout (recommended)

```
site/themes/
  motion/
    layout.php
    view.php
    config.php
    helpers.php
  motion-child/
    layout.php          # override
    config.php          # points to parent
    helpers.php         # optional
```

### what to document in your child theme

- Which files are overridden vs inherited.
- Any new theme components or layout types you introduce.
- Any config keys your templates expect to exist.


## theme components

Theme components live in `site/themes/<theme>/*.php` and use the `Modules` namespace.
They are resolved before site or core components, so themes can override default styling.

## built-in Motion components

### Hero

```markdown
<Hero
  title="Launch faster"
  subtitle="Motion gives you a clean, modern base to build from."
  cta="Get started"
  url="/getting-started"
>
Optional markdown content can go here.
</Hero>
```

### Callout

```markdown
<Callout type="warning">Back up your content before you upgrade.</Callout>
```

Supported `type` values: `info`, `alert`, `success`, `warning`.

### Alert

```markdown
<Alert type="success" title="Saved">
Your changes are live.
</Alert>
```

Supported `type` values: `info`, `success`, `warning`, `error`.

### ContactForm

`ContactForm` pulls its fields from a content block and posts to `/api/contact`.

```markdown
<ContactForm fields="contact-form" store="true" />
```

Default field block (edit `site/blocks/contact-form.md`):

```markdown
<FormField type="text" name="name" label="Name" required="true" />
<FormField type="email" name="email" label="Email" required="true" />
<FormField type="textarea" name="message" label="Message" required="true" rows="6" />
```

## navigation block

The header navigation is driven by a block file so you can update links without touching PHP:

`site/blocks/nav.md`

```markdown
<Nav mode="list" items="- [About](/about)
- [Contact](/contact)" />
```

## 404 page and quotes

The Motion 404 page reads `site/themes/motion/quotes.json` and displays a random quote. The file is a JSON array of strings:

```json
[
  "Great things never came from comfort zones.",
  "Not all who wander are lost.",
  "You found a path we did not publish."
]
```

## asset and typography notes

- Layouts include `/themes/motion/tailwind.min.css` by default.
- `style.css` is optional; add a `<link>` tag to `layout.php` if you want to use it.
- Component assets (scripts/styles) are already injected through `$componentAssets`.

## extending Motion

1. Duplicate the theme folder: `site/themes/motion` -> `site/themes/my-theme`.
2. Update `site/themes/my-theme/config.php` with your name and settings.
3. Add new layouts (for example, `layout-landing.php`) and use `type: landing` in frontmatter.
4. Add new theme components in `site/themes/my-theme/` and use them in Markdown:

```php
<?php

namespace Modules;

use Flint\RenderComponent;

class Badge extends RenderComponent
{
    public static function render(array $props, string $content): string
    {
        $label = self::prop($props, 'label', 'New');
        return '<span class="inline-flex items-center rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700">' .
            self::escape($label) .
            '</span>';
    }
}
```

```markdown
<Badge label="New Feature" />
```

## troubleshooting

- **Icons not showing**: update `site/themes/motion/helpers.php` with the slug you want to support.
- **Banner missing**: check that `banner` is set in frontmatter and the file lives under `site/uploads/`.
- **Layout not switching**: confirm the frontmatter `type` matches the layout filename (for example, `layout-post.php` requires `type: post`).
