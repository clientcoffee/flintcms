# Getting Started

## first run

- Point your browser at the site root; Flint auto-routes to `Flint\Setup` when `config.php` is missing.
- Provide site name, theme, and admin email. The one-shot form writes `config.php` and creates default content.
- Use the admin login (password hash from `config.php`) to reveal edit controls on every page.

## verifying behavior

- Use `content/pages/index.md` and `content/pages/about.md` as live examples. Editing them (via admin inline editor) updates the rendered HTML immediately.
- Components such as `<Nav />`, `<Mermaid />`, and `<Block />` appear in the default content; modify the markdown in `content/blocks` to see live results.
- Check `/api/status` (while logged in) to confirm session info.

## tips

- Keep `app/config.php` out of version control; track `app/config.example.php` instead.
- Use `scripts/build.sh` from the repo root whenever you need a fresh `dist/` release preview.
