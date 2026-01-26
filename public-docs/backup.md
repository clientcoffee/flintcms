# Backup & Restore

## What gets backed up

- `site/` (pages, blocks, themes, components, uploads)
- `site/config.php`

## Create a backup

- Use the admin export endpoint: `/api/export` (POST, admin only).
- Store the archive outside the web root.

## Restore steps

1. Extract the archive.
2. Replace your `site/` directory with the backup.
3. Ensure `site/config.php` is present and readable.

## What not to back up

- `dist/` (build output)
- `vendor/` (dev-only dependencies)

## Suggested cadence

- Before updates or theme changes
- Weekly for active sites
