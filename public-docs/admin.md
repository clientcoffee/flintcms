# Administration

## Content tab quick tour

- Open `/admin` and select **Content**.
- The left list shows pages from `site/pages/`.
- Selecting a page loads its markdown into the editor.
- **Save** writes changes to disk; **Cancel** restores the last loaded content.

## Parent folder selector

- Use the folder icon in the Content header to pick a parent folder.
- New pages are created in the selected folder.

## Updates

- Update checks run on admin page views and are cached for 24 hours.
- Cache file: `app/.update-admin-check.json`.
- Force a check by deleting the cache file and reloading `/admin`.
- API endpoints: `/api/updates/check` and `/api/updates/apply`.

## Backups

- `/api/export` generates a backup archive of `site/` (including `site/config.php`).
- Keep backups outside the web root and restore by replacing `site/` with the archive contents.

## Password reset

- There is no reset endpoint; edit `site/config.php` and replace `admin.password` with a new `password_hash()` value.
- After editing, log out of any open sessions so cached cookies cannot reuse the old secret.
