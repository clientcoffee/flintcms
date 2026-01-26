# Release Process

This repo uses a WordPress-style split:
- `dev` contains source, tests, and build tooling.
- `main` is the release mirror containing only the built, deployable files.

## Build output rules
- Release root is the `dist/` directory (both `app/` and `site/`).
- `site/config.php` is excluded; `site/config.example.php` is included.
- Development tooling and tests are excluded.

## Local release steps
1. Update `CHANGELOG.md` for the release version.
2. Create a version tag on `dev` (for example: `v0.1.0`).
3. Run `scripts/build.sh` to generate `dist/`.
4. Run `scripts/publish-release.sh v0.1.0 dev main` to push `dist/` to `main`.
5. Create a GitHub Release from the tag.
6. Update the one-line install docs (hash and version) in `public-docs/installation.md` and `README.md`.

## GitHub Actions
- `ci.yml` runs tests on pushes/PRs to `dev`.
- `release.yml` runs on tags (`v*`) and publishes to `main`.
