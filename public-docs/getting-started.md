# Getting Started

## First run checklist

1. Start your local server (or point your web server at `app/`).
2. Visit the site root and complete the setup form.
3. Log into `/admin`.
4. Open the Content tab and select a page to edit.

## Where content lives

- Pages: `site/pages/`
- Blocks: `site/blocks/`
- Uploads: `site/uploads/`
- Themes: `site/themes/`

## Create your first page

1. Create `site/pages/hello.md`.
2. Add frontmatter and body content:

```markdown
---
title: Hello World
description: My first Flint page
---

This is my first page.
```

3. Visit `/hello` in the browser.

## Editing with the Admin

- Open `/admin` and navigate to **Content**.
- Select a page in the list to load it into the editor.
- Use **Save** to write changes back to disk.
- Draft pages can live alongside published pages in `site/pages/`.

## Next steps

- Content authoring: [content.md](content.md)
- Theme customization: [themes.md](themes.md)
- Hooks and helpers: [hooks.md](hooks.md)
