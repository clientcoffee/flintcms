# Development Workflow

## code layout

- `app/core/` holds runtime classes (`App.php`, `Parser.php`, `Auth.php`, etc.).
- `site/components/` supplies reusable MDX components; `site/themes/` contains layouts and theme-specific components.
- `public-docs/` holds public CMS guides; `docs/` holds maintainer docs; `scripts/` implement build/publish workflows.

## typical flow

1. Create a feature branch from `dev`.
2. Edit PHP, CSS, or markdown as needed.
3. Run `bun run test` (phpcs, PHPStan, PHPUnit, ESLint).
4. Update `CHANGELOG.md` for any code changes (CI enforces this).
5. Ensure your PR follows `.github/PULL_REQUEST_TEMPLATE.md`.
6. Run `./scripts/build.sh --verbose` to verify dist output.
7. Open a PR targeting `dev`, merge when green.

## release prep

- Tag `dev` with `vX.Y.Z`.
- Let `.github/workflows/release.yml` build, then use `scripts/publish-release.sh vX.Y.Z` to sync to `main`.
- Confirm `dist/` is clean (no caches, no runtime artifacts).
- Merge from `main` to `dev` if fixes land only on the release mirror.
