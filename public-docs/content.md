# Content

## Directories

- `site/pages/` – page markdown/MDX (`.md`, `.mdx`, or directories with `index.md(x)`).
- `site/blocks/` – reusable snippets (for nav, footers, shared sections).
- `site/components/` – PHP components usable in Markdown via `<ComponentName>`.
- `site/uploads/` – images and assets referenced in content.

## URL mapping

- `site/pages/index.md` → `/`
- `site/pages/about.md` → `/about`
- `site/pages/blog/index.md` → `/blog`
- `site/pages/blog/post.md` → `/blog/post`

## Frontmatter essentials

```markdown
---
title: "Hello"
description: "Short subtitle for listings"
status: published
type: post
---
```

## Drafts and visibility

- `status: published` appears in public lists and navigation.
- `status: draft` keeps the page out of public lists (still accessible if you know the URL).
- `status: hidden` removes the page from navigation and sitemap output.

## MDX + components

You can embed components directly in Markdown:

```markdown
<Callout type="warning">Back up before you update.</Callout>
<Block name="nav" />
```

Components live in `site/components/` (site‑specific) or in the active theme under `site/themes/<theme>/`.

## Authoring process

1. Create or edit a page under `site/pages/`.
2. Add frontmatter and content.
3. Save; the Admin editor or direct file writes update content immediately.

## Best practices

- Keep media under `site/uploads/` and reference via absolute paths.
- Reuse `site/blocks/` for shared content like navigation.
- Keep components small and focused on one task.
