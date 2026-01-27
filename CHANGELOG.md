# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Added
- Add one-line installer script and quick install instructions.

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
