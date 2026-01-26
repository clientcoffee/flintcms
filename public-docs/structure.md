# Directory Structure

## Top-level

- `app/` – runtime code, installer, setup, admin UI, core components.
- `site/` – content, themes, components, uploads; never overwritten by updates.
- `docs/` – maintainer and contributor guides.
- `public-docs/` – public CMS documentation.
- `scripts/` – build/publish automation.
- `tests/`, `vendor/` – dev tooling.
- `dist/` – build output created by `scripts/build.sh` (do not edit directly).

## Public vs internal docs

- `public-docs/` is for site owners, editors, and integrators.
- `docs/` is for developers and maintainers.

## Safe to edit vs avoid

Safe to edit:
- `site/pages/`, `site/blocks/`, `site/themes/`, `site/components/`, `site/uploads/`

Avoid editing:
- `app/` (core runtime)
- `dist/` (build output)

## Site subdirectories

- `pages/` – served content.
- `blocks/` – `Block` component sources.
- `themes/` – theme directories (`layout.php`, `view.php`, helpers, assets).
- `components/` – site components and component assets.
- `uploads/` – user media.

## Config location

- Live config: `site/config.php`
- Template: `site/config.example.php`

## Release rules

- `scripts/build.sh` copies `app/` and `site/` into `dist/`.
- `dist/` excludes config, vendor, tests, docs, IDE files, and user uploads.
