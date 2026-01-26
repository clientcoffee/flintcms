# Installation

## prerequisites

- PHP 8.2+ with `pdo` and `intl` extensions recommended.
- Writable directories: `app/`, `content/`, `content/uploads/`, `content/themes/`.
- Web server document root points at `app/` (or symlink to `/app/index.php`).

## steps

1. Upload or clone the repo and run `composer install` from the top level for tests/shared tooling (not required for runtime).
2. Copy `app/config.example.php` to `app/config.php` or run the web installer at `/`.
3. Ensure `config.php` is populated before hitting `/`; the installer writes defaults once.
4. Seed `content/themes` and `content/pages` with the shipped assets from `dist/` if you are reinstalling.

## verification

- Run `php -S localhost:8000 -t app` and open `/` to confirm the homepage renders.
- Run `scripts/build.sh --skip-checks` to confirm packaging works from your install tree.
