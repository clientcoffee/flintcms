# Build Process

Flint uses an improved build system to create production-ready distribution packages.

## Quick Start

```bash
# Simple build (with pre-checks)
composer build

# Build with full validation
composer build:check

# Build without pre-checks (faster)
./scripts/build.sh --skip-checks

# Verbose build output
./scripts/build.sh --verbose
```

## Build Process

The build system:

Flint treats builds as "clean-room" outputs. The script removes stale artifacts along the way (fresh `dist/`, fresh dependencies, and runtime caches scrubbed from the output) so releases ship only known-good data.

1. **Pre-Build Checks** (optional, enabled by default)
   - Performs a clean dependency install (removes `vendor/` and runs `composer install`)
   - Runs test suite to ensure code quality
   - Runs PHPStan static analysis
   - Fails the build if checks report errors

2. **File Copying**
   - Copies `app/` directory to `dist/`
   - Copies the `site/` bundle while omitting live uploads (they get recreated safely)
   - Rebuilds `site/uploads/` inside `dist/` by copying the bundled `.htaccess` and `index.php` guard files plus the default imagery from `app/core/content/uploads`
   - Excludes files listed in `.buildignore`
   - Preserves file permissions and structure

3. **Build Metadata**
   - Generates `.build-info` with timestamp and commit hash
   - Useful for tracking which version is deployed

4. **Post-Build Validation**
   - Verifies required files exist (`index.php`, `core/App.php`, etc.)
   - Ensures excluded files are not present (tests, dev docs, etc.)
   - Fails build if validation errors occur

## Build Output

Build creates `dist/` directory containing:
- All runtime PHP files
- Themes and components
- Content (pages, blocks, uploads)
- `site/config.example.php` (not `site/config.php`)
- `.build-info` metadata

Excluded from `dist/`:
- Tests (`tests/` directory)
- Dev dependencies (`composer.json`, `phpunit.xml`, `vendor/`)
- Dev documentation (CLAUDE.md, TESTING.md, etc.)
- IDE files (.claude/, .vscode/, etc.)
- Config files (`site/config.php`)

## Composer Scripts

```bash
# Run tests only
composer test

# Run static analysis only
composer analyse

# Run both tests and analysis
composer check

# Build without pre-checks
composer build

# Build with full checks
composer build:check
```

## Build Script Options

```bash
scripts/build.sh [OPTIONS]

Options:
  --skip-checks    Skip pre-build validation (tests and static analysis)
  --verbose, -v    Show detailed rsync output
  --help, -h       Show help message
```

## Exclusion Management

Edit `.buildignore` to control which files are excluded from builds.

Format:
```
# Comments start with #
site/config.php      # Exclude specific file
tests/               # Exclude directory
*.log               # Exclude pattern
```

## Testing Builds Locally

```bash
# Build the distribution
composer build

# Test the build
cd dist
php -S localhost:8000

# Visit http://localhost:8000 in browser
```

## Publishing Releases

```bash
# Create and publish a release
composer build
scripts/publish-release.sh v1.0.0
```

This uses a git worktree to publish `dist/` contents to the `main` branch.

## CI/CD Integration

The build process integrates with GitHub Actions:

- **PR/Push to dev**: Run tests and static analysis
- **Tagged release (v*)**: Build and publish to `main` branch

## Troubleshooting

### Build fails with "Missing required file"
- Check that `app/` directory contains all required files
- Verify you haven't accidentally excluded required files in `.buildignore`

### Pre-checks fail
- Review test failures: `composer test`
- Review static analysis: `composer analyse`
- Use `--skip-checks` to build anyway (not recommended for releases)

### "composer install" fails
- Check network access and credentials for private repos
- Try rerunning `composer install` manually to see full output
- Use `--skip-checks` if you don't need validation

## Build Size

Typical build size: ~3-4MB (depends on themes and content)

The build excludes:
- Tests and fixtures
- Dev dependencies (PHPUnit, PHPStan)
- Documentation
- Git history

This keeps the distribution lightweight and deployment-ready.

## Best Practices

1. **Always run tests before building releases**
   ```bash
   composer build:check
   ```

2. **Review static analysis warnings**
   ```bash
   composer analyse
   ```

3. **Test builds locally before publishing**
   ```bash
   cd dist && php -S localhost:8000
   ```

4. **Use semantic versioning for releases**
   ```bash
   scripts/publish-release.sh v1.2.3
   ```

5. **Keep .buildignore updated**
   - Add new test files
   - Add new dev documentation
   - Exclude IDE-specific files

## Build Artifacts

Each build generates:
- `dist/` - The complete distribution package
- `dist/.build-info` - Build metadata (date, commit, source)

The `.build-info` file helps track deployed versions in production.

## Runtime cache cleanup

The build script removes runtime cache files (for example, magic link or scheduler cache artifacts) so releases ship clean output.
