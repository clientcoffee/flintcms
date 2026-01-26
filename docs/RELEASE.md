# Release Process

This repo uses a WordPress-style split:
- `dev` contains source, tests, and build tooling.
- `main` is the release mirror containing only the built, deployable files.

## Build output rules
- Release root is the contents of `app/` (the `app/` directory itself is not included).
- `config.php` is excluded; `config.example.php` is included.
- Development tooling and tests are excluded.

## Local release steps
1. Create a version tag on `dev` (for example: `v0.1.0`).
2. Run `scripts/build.sh` to generate `dist/`.
3. Run `scripts/publish-release.sh v0.1.0` to push `dist/` to `main`.

## GitHub Actions
- `ci.yml` runs tests on pushes/PRs to `dev`.
- `release.yml` runs on tags (`v*`) and publishes to `main`.
