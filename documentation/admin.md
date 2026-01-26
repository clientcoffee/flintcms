# Administration

## password reset

- There is no reset endpoint; edit `app/config.php` and replace `admin.password` with a new `password_hash()` value.
- After editing, log out of any open sessions so cached cookies cannot reuse the old secret.
- For automated deployments, inject the admin password during install by editing the setup form payload.

## update checks

- Update checks run on every admin page view but are throttled to once per 24 hours. The cache file is `app/.update-admin-check.json`.
- Force a check by deleting that file and reloading the admin UI.
- `/api/updates/check` and `/api/updates/apply` remain available for scripted workflows.

## maintenance

- Logout button appears in the footer when signed in.
- `/api/export` bundles `content/` + `config.php` for backups (requires admin).
