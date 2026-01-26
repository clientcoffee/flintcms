# Directory Structure

## top-level

- `app/` – runtime code, installer, setup, admin UI, core components.
- `content/` – content, themes, components, uploads; never overwritten by updates.
- `docs/` – maintainer and contributor guides.
- `public-docs/` – public CMS documentation.
- `scripts/` – build/publish automation.
- `tests/`, `vendor/` – dev tooling.
- `dist/` – release output created by `scripts/build.sh`.

## app subdirectories

- `core/` – `App.php`, `Parser.php`, `Auth.php`, `Admin.php`, `Setup.php`.
- `core/components/` – essential components loaded before user widgets.
- `public/` – empty in source but populated by public assets (mermaid, shared scripts).

## content subdirectories

- `pages/` – served content.
- `blocks/` – `Block` component sources.
- `themes/` – theme directories. Each theme contains `layout.php`, optional typed layouts, `view.php`, helpers, assets.
- `components/` – optional components shipped with dist.
- `uploads/` – user media; symlinked by `content` references.

## release rules

- `scripts/build.sh` copies `app/`/`content/` into `dist/`, excluding config, vendor, tests, docs, IDE files, `config.php`.
- Keep `config.example.php` under version control; `config.php` remains site-specific and ignored.
