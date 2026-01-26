# Content Generation

## directories

- `content/pages/` – page markdown/MDX (`.md`, `.mdx`, or directories with `index.md(x)`).
- `content/blocks/` – reusable snippets loaded via `\Components\Block::render(['name'=>'foo'])`.
- `content/components/` – runtime components accessible through `<ComponentName>` syntax.

## authoring process

1. Create or edit a page under `content/pages/`.
2. Use frontmatter (`title`, `type`, `status`, etc.). Example:

```
---
title: "Hello"
status: published
type: post
---
```

3. Embed components (e.g., `<Mermaid>graph TD;</Mermaid>`).
4. Save; admin inline editor or direct file write updates the served content instantly.

## best practices

- Keep heavy images under `content/uploads/` and reference via absolute paths.
- Reuse `content/blocks/` for shared structures like forms or navs.
- Use `<Block name="nav" />` as glue between components and layouts.
