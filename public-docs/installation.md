# Installation

## One-line install (recommended)

```bash
bash -c "$(curl -fsSL https://raw.githubusercontent.com/clientcoffee/flintcms/dev/scripts/install.sh)" -- -v 0.2.3 -d flint -s 2817181c0213596a7e89b94082f9d49aff4be9554132878860f9709347b0437a
```

SHA256 (v0.2.3 release tarball): `2817181c0213596a7e89b94082f9d49aff4be9554132878860f9709347b0437a`

Manual verification (macOS/Linux):

```bash
curl -fsSL https://codeload.github.com/clientcoffee/flintcms/tar.gz/refs/tags/v0.2.3 -o flintcms-v0.2.3.tar.gz
printf '%s  %s\n' '2817181c0213596a7e89b94082f9d49aff4be9554132878860f9709347b0437a' flintcms-v0.2.3.tar.gz | shasum -a 256 -c -
```

## Requirements

- PHP 8.2+
- A web server that can point the document root at `app/`
- Write access to `site/`, `site/uploads/`, and `site/submissions/`

## Install steps (manual)

1. Download or clone the repo.
2. Copy `site/config.example.php` to `site/config.php` or run the web installer at `/`.
3. Ensure the web server points to `app/` (or symlink `app/index.php` to your public root).

## Local quick start

```bash
php -S localhost:8000 -t app
```

Open `http://localhost:8000` and complete the setup flow.

## Safe to edit vs. do not edit

Safe to edit:
- `site/pages/` (content)
- `site/blocks/` (reusable content)
- `site/themes/` (layouts and view templates)
- `site/components/` (custom components)
- `site/uploads/` (media)

Avoid editing:
- `app/` (core runtime)
- `vendor/` (dependencies for tests/dev only)

## Common install issues

- **Permissions**: ensure `site/` is writable by your web server user.
- **Blank page**: check PHP error logs and confirm `app/` is your document root.
- **Setup loop**: confirm `site/config.php` exists and is readable.
