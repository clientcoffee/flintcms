# Flint Update System

## Overview

Flint includes a built-in update system that checks for new releases from GitHub and can automatically or manually apply updates while preserving your content, themes, and configuration.

## Features

- **Automatic Update Checks**: Checks GitHub releases for new versions
- **Smart Update Modes**: Choose how updates are handled (auto, ask, manual)
- **Content Preservation**: Your content, themes, and config are never touched
- **Backup System**: Automatic backup before applying updates
- **Admin Interface**: Visual update notifications and one-click installation
- **API Endpoints**: Programmatic access to update system

## Update Modes

Configure in `site/config.php`:

```php
return [
    'updates' => [
        'auto_update' => 'ask',
    ],
];
```

Update checks run on admin page views and are throttled to once per 24 hours.

### Mode Options

**`auto_update = true`** - Automatic
- Automatically downloads and installs updates
- Best for: Sites where you want latest features immediately
- Risk: Low (backups are automatic, but immediate changes)

**`auto_update = ask`** - Prompt (Recommended)
- Shows update banner with install button
- You choose when to install
- Best for: Most users who want control
- Risk: Minimal

**`auto_update = false`** - Manual Only
- Only notifies about updates
- No install button, just link to release notes
- Best for: Custom deployments, testing environments
- Risk: None (you handle updates manually)

## How It Works

### 1. Version Tracking

Current version is defined in `app/core/Version.php`:

```php
public const VERSION = '0.1.0';
public const CHANNEL = 'dev';  // dev, beta, or stable
```

### 2. Update Checking

The `UpdateChecker` class:
- Fetches latest release from GitHub API
- Compares with current version
- Caches results and throttles admin checks to once per 24 hours
- Returns update info if newer version available

### 3. Download & Apply

When applying an update:
1. **Backup**: Copies current `app/` to `app-backup-{timestamp}/`
2. **Download**: Fetches ZIP from GitHub release
3. **Extract**: Unzips to temporary directory
4. **Replace**: Replaces `app/` directory only
5. **Verify**: Checks critical files exist
6. **Rollback**: Restores backup if anything fails

### 4. What Gets Updated

✅ **Updated**:
- `app/` directory (core CMS files)
- System components

❌ **Never Touched**:
- `site/` directory (your pages, themes, components)
- `site/config.php` (your configuration)
- `site/uploads/` (your media files)

## Admin Interface

### Updates Tab

Access: **Admin Panel → Updates**

Shows:
- Current version and release channel
- "Check for Updates" button
- Update notification banner (when available)
- Auto-update mode selector

### Update Banner

When an update is available, shows:
- New version number
- Install button (if mode allows)
- Link to release notes
- Behavior changes based on `auto_update` mode

## API Endpoints

### Check for Updates

```http
GET /api/updates/check
```

**Requires**: Admin authentication

**Response**:
```json
{
  "success": true,
  "current_version": "0.1.0",
  "update_available": true,
  "update": {
    "version": "0.2.0",
    "url": "https://github.com/user/repo/releases/tag/v0.2.0",
    "download_url": "https://codeload.github.com/user/repo/zip/refs/tags/v0.2.0",
    "release_notes": "## What's New...",
    "published_at": "2026-01-03T12:00:00Z"
  },
  "auto_update_mode": "ask"
}
```

### Apply Update

```http
POST /api/updates/apply
Content-Type: application/json

{
  "download_url": "https://codeload.github.com/user/repo/zip/refs/tags/v0.2.0"
}
```

**Requires**: Admin authentication

**Response**:
```json
{
  "success": true,
  "message": "Update applied successfully. Please refresh the page."
}
```

### Get Version

```http
GET /api/updates/version
```

**Public endpoint** (no auth required)

**Response**:
```json
{
  "success": true,
  "version": {
    "version": "0.1.0",
    "channel": "dev",
    "build_date": "2026-01-03"
  }
}
```

## Configuration

### site/config.php

```php
return [
    'updates' => [
        // Update mode: true (auto), 'ask' (prompt), false (manual only)
        'auto_update' => 'ask',
    ],
];
```

### Update Cache Files

- `app/.update-cache.json` caches the latest release metadata.
- `app/.update-admin-check.json` throttles admin checks to once per 24 hours.

### Setting GitHub Repository

Edit `app/core/UpdateChecker.php`:

```php
private const UPDATE_REPO = 'clientcoffee/flintcms';
```

Replace with your actual GitHub repository.

## Update Process Details

### GitHub Release Requirements

For updates to work, GitHub releases must:
1. Use semantic versioning tags: `v0.1.0`, `v1.0.0`, etc.
2. Include a ZIP asset or use automatic zipball
3. Contain `app/` directory in the ZIP

### Release Structure

```
release.zip
└── username-repo-abc123/  # GitHub adds this wrapper
    └── app/               # Your CMS core
        ├── core/
        ├── components/
        └── index.php
```

The update system automatically finds the `app/` directory.

## Safety Features

### Automatic Backup

Before any update:
- Entire `app/` directory copied to `app-backup-{timestamp}/`
- Backup remains until next update
- Manual restore: `rm -rf app && mv app-backup-* app`

### Rollback on Failure

If update fails:
- Automatically restores from backup
- Error message shown in admin
- Site continues running old version

### Validation

After extracting:
- Checks for required files (`index.php`, `core/App.php`)
- Verifies directory structure
- Fails safely if structure is wrong

## Troubleshooting

### Update Check Fails

**Problem**: "Failed to check for updates"

**Solutions**:
- Check internet connectivity
- Verify GitHub API is accessible
- Check `GITHUB_REPO` constant is correct
- GitHub API rate limit (60 requests/hour unauthenticated)

### Update Install Fails

**Problem**: "Failed to apply update"

**Solutions**:
- Check PHP has write permissions to `app/` directory
- Verify ZIP extension is available: `php -m | grep zip`
- Check disk space available
- Review PHP error logs

### Manual Rollback

If site breaks after update:

```bash
cd /path/to/flint
rm -rf app
mv app-backup-{timestamp} app
# Replace {timestamp} with actual backup directory name
```

### Clear Update Cache

If showing wrong version:

```bash
rm /path/to/flint/.update-cache.json
```

Or via PHP:

```php
$checker = new \Flint\UpdateChecker($root, $config);
$checker->clearCache();
```

## Development

### Testing Updates

1. **Create Test Release**:
   - Push tag: `git tag v0.2.0 && git push --tags`
   - GitHub automatically creates release

2. **Test Update Process**:
   - Set version to older in `Version.php`
   - Check for updates in admin
   - Apply update
   - Verify site still works

3. **Test Rollback**:
   - Break something in new version
   - Check if backup restores properly

### Version Bumping

Update `app/core/Version.php`:

```php
public const VERSION = '0.2.0';  // Increment
public const CHANNEL = 'stable'; // Change if needed
```

### Release Checklist

Before creating a release:
- [ ] Update `VERSION` constant
- [ ] Run tests: `composer test`
- [ ] Run static analysis: `composer analyse`
- [ ] Build dist: `composer build`
- [ ] Test manually
- [ ] Create git tag
- [ ] Push to GitHub
- [ ] Verify GitHub release created
- [ ] Test update from previous version

## Security Considerations

### GitHub API

- Uses public API (no authentication required)
- Rate limited: 60 requests/hour per IP
- Caching reduces API calls

### Download Verification

Currently:
- ✅ Downloads from GitHub (HTTPS)
- ❌ No checksum verification (future enhancement)
- ❌ No signature verification (future enhancement)

### Permissions

- Only admins can check/apply updates
- Update endpoint requires authentication
- Version endpoint is public (safe)

## Best Practices

1. **Test in Staging**: Test updates in dev environment first
2. **Backup Separately**: Have your own backup system too
3. **Monitor Logs**: Check PHP error logs after updates
4. **Use `ask` Mode**: Recommended for production sites
5. **Read Release Notes**: Review changes before installing
6. **Keep Config Updated**: Check `config.example.php` for new options

## Future Enhancements

Planned improvements:
- [ ] Checksum verification
- [ ] GPG signature verification
- [ ] Migration scripts support
- [ ] Scheduled update checks
- [ ] Email notifications
- [ ] Multi-version rollback
- [ ] Update history log

## Examples

### Manual Update Check

```php
use Flint\UpdateChecker;
use Flint\Version;

$checker = new UpdateChecker('/path/to/flint', $config);
$updateInfo = $checker->checkForUpdates();

if ($updateInfo) {
    echo "Update available: " . $updateInfo['version'];
    echo "Current: " . Version::VERSION;
}
```

### Programmatic Update

```php
$checker = new UpdateChecker('/path/to/flint', $config);
$zipFile = $checker->downloadUpdate($downloadUrl);

if ($zipFile && $checker->applyUpdate($zipFile)) {
    echo "Update successful!";
} else {
    echo "Update failed!";
}
```

## Summary

The Flint update system provides:
- ✅ Automatic update detection
- ✅ Flexible update modes
- ✅ Content preservation
- ✅ Automatic backups
- ✅ Safe rollback
- ✅ Admin interface
- ✅ API access

Your content, themes, and configuration are always safe during updates.
