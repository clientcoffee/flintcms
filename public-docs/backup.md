# Backup & Reinstall

## backups

- Use `/api/export` (POST, admin) to package `content/` + `config.php`.
- Manually copy `content/`, `app/config.php`, and `content/uploads/` before major changes.
- The installer also creates `app-backup-{timestamp}` during updates.

## rebuild

1. Upload the new `dist/` contents into place (overwrite `app/`).
2. Reuse the exported tarball to restore `content/` and `config.php`.
3. Run `composer dump-autoload` if Composer files changed.

## reinstall

- Delete `app/config.php` and revisit `/` to rerun the setup form (makes a new `config.php` without clobbering content).
- If you want a fresh theme, replace `content/themes/motion` with the default from `dist/content/themes`.
