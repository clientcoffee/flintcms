# Flint

A flat-file CMS that ships as a folder. No database, no framework, no build chain required to run.

Flint is for teams who want content to be a set of files they can see, version, and ship. It is fast to host, easy to reason about, and simple enough to explain to a teammate in five minutes.

Website: https://flintcms.com

## Highlights

- **Flat-file core**: Markdown content + PHP config. No database required.
- **Theme + components**: Drop-in themes, reusable blocks, and hookable components.
- **Security-first**: Defense component for rate limits and threat tracking.
- **Admin panel**: Built-in UI for content management and settings.
- **Backups + scheduler**: Automated backups and scheduled tasks.
- **Zero runtime deps**: PHP 8.2+ is enough to run in production.

## Why Flint

- You want a CMS you can deploy by copying a directory.
- You want content in Git, not in a database.
- You want a codebase that stays readable over clever.
- You want the flexibility of Markdown with the power of components.

## Quick Start

<!-- install:start -->
### One-line install

```bash
bash -c "$(curl -fsSL https://raw.githubusercontent.com/clientcoffee/flintcms/dev/scripts/install.sh)" -- -v 0.2.3 -d flint -s 2817181c0213596a7e89b94082f9d49aff4be9554132878860f9709347b0437a
```

SHA256 (v0.2.3 release tarball): `2817181c0213596a7e89b94082f9d49aff4be9554132878860f9709347b0437a`

Manual verification (macOS/Linux):

```bash
curl -fsSL https://codeload.github.com/clientcoffee/flintcms/tar.gz/refs/tags/v0.2.3 -o flintcms-v0.2.3.tar.gz
printf '%s  %s
' '2817181c0213596a7e89b94082f9d49aff4be9554132878860f9709347b0437a' flintcms-v0.2.3.tar.gz | shasum -a 256 -c -
```
<!-- install:end -->


1. **Clone**
   ```bash
   git clone https://github.com/clientcoffee/flintcms.git flint
   cd flint
   ```

2. **Run a local server**
   ```bash
   php -S localhost:8000
   ```

3. **Finish setup**
   - Visit `http://localhost:8000`
   - Complete the setup wizard
   - Access the admin at `/admin`

Optional: use the build script when you want a production bundle.

```bash
./scripts/build.sh
```

For a quick local build + serve loop:

```bash
./scripts/build.sh --skip-checks
cd dist/app && php -S localhost:8000
```

## Versioning

- The app version lives in `app/core/Version.php`.
- Release notes live in `CHANGELOG.md`.
- Release tags follow semver (e.g. `v0.2.0`).

## Documentation

- Developer guide: `docs/CLAUDE.md`
- Build & deployment: `docs/BUILD.md`, `docs/DEPLOYMENT.md`
- Updates system: `docs/UPDATES.md`
- Testing: `docs/TESTING.md`
- Project structure: `docs/STRUCTURE.md`

## Project Structure

```
flint/
├── app/                # Core PHP runtime and admin assets
├── site/               # Pages, blocks, components, themes
├── scripts/            # Build and release scripts
├── docs/               # Deep-dive documentation
├── dist/               # Build output (generated)
├── CHANGELOG.md
└── README.md
```

## Contributing

- Read `docs/CLAUDE.md` first.
- Use feature branches and keep PRs focused.
- Target `dev` for all PRs; `main` is build-only for public releases.
- Run `bun run test` before opening a PR (use `bun run test:fix` to auto-fix).

## License

MIT. See `LICENSE`.
