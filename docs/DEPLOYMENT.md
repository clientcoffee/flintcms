# Deployment & Release Management

Complete guide for building, releasing, and updating Flint.

---

## Table of Contents

1. [Build Process](#build-process)
2. [Release Process](#release-process)
3. [Update System](#update-system)
4. [CI/CD](#cicd)
5. [Troubleshooting](#troubleshooting)

---

## Build Process

### Quick Start

```bash
# Simple build with pre-checks
composer build

# Build with full validation
composer build:check

# Build without pre-checks (faster)
./scripts/build.sh --skip-checks

# Verbose output
./scripts/build.sh --verbose
```


### How It Works

1. **Pre-Build Checks** (optional, enabled by default)
   - Runs test suite
   - Runs PHPStan static analysis
   - Warns about issues but doesn't block

2. **File Copying**
   - Copies `app/` directory to `dist/`
   - Excludes files listed in `.buildignore`
   - Preserves permissions

3. **Build Metadata**
   - Generates `.build-info` with timestamp and commit
   - Useful for tracking deployed versions

4. **Post-Build Validation**
   - Verifies required files exist
   - Ensures excluded files are not present
   - Fails if validation errors occur

### Build Output

Creates `dist/` directory containing:
- All runtime PHP files
- Themes and components
- Content (pages, blocks, uploads)
- `config.example.php` (not `config.php`)
- `.build-info` metadata

**Excluded from dist/**:
- Tests (`tests/` directory)
- Dev dependencies (`composer.json`, `vendor/`)
- Dev documentation (docs/)
- IDE files (.claude/, .vscode/)
- Config files (`config.php`)

### Composer Scripts

```bash
composer test            # Run tests only
composer analyse         # Run static analysis
composer check           # Run tests and analysis
composer build           # Build without pre-checks
composer build:check     # Build with full checks
```

### Exclusion Management

Edit `.buildignore` to control excluded files:

```
# Comments start with #
config.php           # Exclude specific file
tests/               # Exclude directory
*.log               # Exclude pattern
```

### Testing Builds Locally

```bash
# Build
composer build

# Test
cd dist
php -S localhost:8000

# Visit http://localhost:8000
```

---

## Release Process

### Repository Structure

WordPress-style split:
- **dev** - Source code, tests, build tooling
- **main** - Release mirror (built, deployable files only)

### Release Steps

1. **Update version** in `app/core/Version.php`:
   ```php
   public const VERSION = '0.2.0';
   public const CHANNEL = 'stable';
   ```

2. **Create tag** on dev:
   ```bash
   git tag v0.2.0
   git push origin v0.2.0
   ```

3. **Build distribution**:
   ```bash
   composer build:check
   ```

4. **Publish to main**:
   ```bash
   scripts/publish-release.sh v0.2.0
   ```

### GitHub Actions

- **ci.yml** - Runs tests on pushes/PRs to dev
- **release.yml** - Runs on tags (v*), publishes to main

### Release Checklist

Before creating a release:
- [ ] Update VERSION constant
- [ ] Run tests: `composer test`
- [ ] Run static analysis: `composer analyse`
- [ ] Build dist: `composer build`
- [ ] Test manually
- [ ] Create git tag
- [ ] Push to GitHub
- [ ] Verify GitHub release created
- [ ] Test update from previous version

---

## Update System

### Overview

Built-in update system that:
- Checks GitHub releases for new versions
- Downloads and applies updates automatically or manually
- Preserves your content, themes, and configuration
- Creates automatic backups before updates
- Provides admin interface and API endpoints

### Update Modes

Configure in `config.php`:

```ini
[updates]
auto_update = ask           # Options: true, ask, false
```

Update checks run on admin page views and are throttled to once per 24 hours.

**Modes**:

- **`true`** (Auto) - Automatically downloads and installs updates
- **`ask`** (Recommended) - Shows update banner with install button
- **`false`** (Manual) - Only notifies, no install button

### What Gets Updated

✅ **Updated**:
- `app/` directory (core CMS files)
- System components

❌ **Never Touched**:
- `site/` directory (your content)
- `config.php` (your configuration)
- `site/uploads/` (your media)

### Update Process

When applying an update:

1. **Backup** - Copies `app/` to `app-backup-{timestamp}/`
2. **Download** - Fetches ZIP from GitHub release
3. **Extract** - Unzips to temporary directory
4. **Replace** - Replaces `app/` directory only
5. **Verify** - Checks critical files exist
6. **Rollback** - Restores backup if anything fails

### Admin Interface

**Updates Tab**: Admin Panel → Updates

Shows:
- Current version and release channel
- "Check for Updates" button
- Update notification banner
- Auto-update mode selector

### API Endpoints

**Check for Updates**:
```http
GET /api/updates/check
```

Response:
```json
{
  "success": true,
  "current_version": "0.1.0",
  "update_available": true,
  "update": {
    "version": "0.2.0",
    "url": "https://github.com/user/repo/releases/tag/v0.2.0",
    "download_url": "...",
    "release_notes": "## What's New..."
  }
}
```

**Apply Update** (Admin only):
```http
POST /api/updates/apply
Content-Type: application/json

{
  "download_url": "https://github.com/user/repo/archive/v0.2.0.zip"
}
```

**Get Version** (Public):
```http
GET /api/updates/version
```

### GitHub Release Requirements

For updates to work:
1. Use semantic versioning tags: `v0.1.0`, `v1.0.0`
2. Include ZIP asset or use automatic zipball
3. ZIP must contain `app/` directory

### Safety Features

**Automatic Backup**:
- Entire `app/` copied before update
- Backup remains until next update
- Manual restore: `rm -rf app && mv app-backup-* app`

**Rollback on Failure**:
- Automatically restores from backup
- Error message shown in admin
- Site continues on old version

**Validation**:
- Checks for required files after extraction
- Verifies directory structure
- Fails safely if structure is wrong

---

## CI/CD

### GitHub Actions Workflows

**.github/workflows/ci.yml**:
```yaml
name: CI

on:
  push:
    branches: [ main, dev ]
  pull_request:
    branches: [ main, dev ]

jobs:
  php-lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - name: Install dependencies
        run: composer install
      - name: Run phpcs
        run: ./vendor/bin/phpcs --standard=PSR12 app/

  js-lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup Bun
        uses: oven-sh/setup-bun@v1
        with:
          bun-version: '1.1.6'
      - name: Install dependencies
        run: bun install
      - name: Run ESLint
        run: bun run lint:js
```

**.github/workflows/release.yml**:
```yaml
name: Release

on:
  push:
    tags:
      - 'v*'

jobs:
  build-and-publish:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Build distribution
        run: composer build
      - name: Publish to main
        run: scripts/publish-release.sh ${{ github.ref_name }}
```

### Required Checks

All PRs must pass:
- PHP CodeSniffer (PSR-12)
- ESLint
- Security audit
- Manual code review

---

## Troubleshooting

### Build Failures

**"Missing required file"**:
- Check `app/` contains all required files
- Verify `.buildignore` hasn't excluded required files

**Pre-checks fail**:
- Review: `composer test`
- Review: `composer analyse`
- Use `--skip-checks` to build anyway (not recommended)

**"vendor/ not found" warning**:
- Run `composer install`
- Or use `--skip-checks`

### Update Failures

**Update check fails**:
- Check internet connectivity
- Verify GitHub API accessible
- Check `GITHUB_REPO` constant correct
- GitHub API rate limit (60 req/hour)

**Update install fails**:
- Check PHP write permissions on `app/`
- Verify ZIP extension: `php -m | grep zip`
- Check disk space
- Review PHP error logs

**Manual rollback**:
```bash
cd /path/to/flint
rm -rf app
mv app-backup-{timestamp} app
```

**Clear update cache**:
```bash
rm .update-cache.json
```

### Best Practices

1. **Test in staging** - Always test updates in dev first
2. **Backup separately** - Have your own backup system
3. **Monitor logs** - Check PHP errors after updates
4. **Use 'ask' mode** - Recommended for production
5. **Read release notes** - Review changes before installing
6. **Keep config updated** - Check `config.example.php` for new options

---

## Production Deployment

### Pre-Deployment

- [ ] Update version
- [ ] Run full test suite
- [ ] Build distribution
- [ ] Test locally
- [ ] Review security checklist
- [ ] Backup production site

### Deploy Steps

1. **Build**:
   ```bash
   composer build:check
   ```

2. **Upload** `dist/` contents to server

3. **Set permissions**:
   ```bash
   find . -type d -exec chmod 755 {} \;
   find . -type f -exec chmod 644 {} \;
   chmod 600 app/config.php
   ```

4. **Verify**:
   - Check site loads
   - Test admin login
   - Verify components work
   - Check logs for errors

### Post-Deployment

- [ ] Monitor error logs
- [ ] Test critical functionality
- [ ] Verify backups running
- [ ] Check update system working

---

**Last Updated**: 2025-01-04
