# Flint

A flat-file CMS that ships as a folder. No database, no framework, no build chain required to run.

Flint keeps your content as Markdown and configuration files, so you can version it, back it up, and move it anywhere. It is designed to stay small, readable, and easy to deploy.

Website: https://flintcms.com

## Quick Start

1. **Upload** the project files to your web server.
2. **Visit** your site in the browser.
3. **Complete** the one-time setup wizard.
4. **Log in** at `/admin` to start editing.

## Writing Content

Pages live in `site/pages/` as Markdown:

- `site/pages/index.md` -> Home page
- `site/pages/about.md` -> /about

Blocks live in `site/blocks/` and can be reused across pages.

### Using Components

You can embed components directly in Markdown:

```markdown
# My Page

<Callout type="alert">This is important!</Callout>
```

## Configuration

Your site config lives in `site/config.php`. The setup wizard creates this file for you.

## Themes

Themes live in `site/themes/`. The default theme is **Motion**, which ships with its own CSS and layout templates. You can drop in a new theme by adding a folder and updating `site/config.php`.

## Requirements

- PHP 8.2 or higher
- Apache with mod_rewrite (or Nginx with equivalent rewrite rules)
- Write permissions on `site/` and `app/`

## License

MIT. See `LICENSE` in the project root.
