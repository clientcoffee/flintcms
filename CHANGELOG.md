# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Added
- Add one-line installer script and quick install instructions.
- Add component registry support for optional components.
- Add registry configuration defaults for component browsing and installs.
- Add runtime performance toggles for micro-cache, HTML minify, lazy images, and render caching.
- Add persistent render cache keyed by file mtime for pages and blocks.
- Add sitemap cache keyed by page count + latest mtime.
- Add admin tree cache keyed by markdown mtimes.
- Add inline markdown cache (per-request) to reduce repeat parsing.
- Add static asset cache headers with configurable TTLs (uploads optional).
- Add fast public path to skip admin/scheduler checks for logged-out visitors.
- Add editable page location controls and in-place filename renaming that respects `.md`/`.mdx` requirements.
- Implement walled-garden landing page that protects directories by checking `password:` frontmatter on index files.
- Add the “World in Brief” layout (brief theme) with a blog list layout triggered by `type: list`.
- Add `sanitize_page_title` helper to normalize title metadata without stripping digits or symbols.
- Add site logo support in admin settings with strict image upload validation and Motion header fallback to site name text.

### Changed
- Move optional components into external registry and keep core lean.
- Convert core components to directory-based layout.
- Deprecate legacy contact endpoint in favor of `/api/form`.
- Update component documentation for registry-based installs.
- Add lazy-loading + aspect-ratio hints to markdown-rendered images.
- Serve `/assets/css/tailwind.min.css` from the app bundle for every theme, upgrade the Tailwind pipeline to v3 (typography plugin) and strip the dev-only `tailwind.css` before packaging.
- Serve `/assets/css/tailwind.min.css` from the app bundle for every theme, upgrade the Tailwind pipeline to v3 (typography plugin) and strip the dev-only `tailwind.css` before packaging.
- Improve admin edit targeting across theme layouts to keep live edit links working.
- Use title sanitization in Motion layouts to preserve currency symbols, digits, and slashes while normalizing whitespace.
- Update admin editor shortcuts to use the Alt modifier and avoid browser Ctrl/Cmd collisions.
- Add basic markdown editor behaviors (wrap selections and continue lists).
- Normalize spacing in the About page content.

## [0.2.3] - 2026-01-26
### Added
- Add admin content creation flow with title-first editing and keyboard shortcuts.
- Add admin content parent selector dropdown and drag-and-drop file moves.
- Render sitemap from URL structure with nested list output.

### Changed
- Simplify admin content list to use URL-based hierarchy and show file paths.
- Improve markdown list parsing for nested ordered/unordered lists.
- Adjust list indentation for content rendering.

## [0.2.2] - 2026-01-14
### Changed
- Bake update repo into core via `UPDATE_REPO`.
- Remove update repo configuration from setup and site config.

## [0.2.1] - 2026-01-12
### Added
- Ship README.md alongside LICENSE in build output.

### Changed
- Render inline markdown in page and post titles.
- Consolidate components under site/components and update loaders.
- Move theme hook registration into core.

## [0.2.0] - 2026-01-10
### Added
- Add initial changelog to track project releases.

### Changed
- Bump core app version to 0.2.0 in `app/core/Version.php`.
