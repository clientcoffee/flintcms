# Components

## classification

- **core components** live under `app/core/components/` and are loaded via `Components\*`.
- **user components** live under `content/components/` and share the same namespace.
- The parser (`app/core/Parser.php`) attempts core components first, then user components, then theme modules.

## composition rules

- Each component declares a static `render(array $props, string $content): string`.
- Use `getAssets()` to register JS/CSS:
  - `styles`/`scripts` entries inject `<link>`/`<script>` tags.
  - `inline_styles`/`inline_scripts` allow shared inline blocks.
- Components must return single-line HTML to avoid Markdown breaking.

## developing

1. Drop your file into `content/components/MyWidget.php`.
2. Use `\Components\Block::render()` for reusable blocks, or `<MyWidget>` inline in Markdown.
3. Reference assets via `/components/MyWidget/style.css` and mount them in `getAssets()`.
