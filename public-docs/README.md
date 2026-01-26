# Documentation

Public-facing guides for Flint site owners, editors, and integrators.

## Fast start

1. Install Flint: [installation.md](installation.md)
2. Complete the first-run checklist: [getting-started.md](getting-started.md)
3. Start editing content in `/admin` and the Content tab.

## Navigation

### Setup
- [installation.md](installation.md)
- [getting-started.md](getting-started.md)
- [configuration.md](configuration.md)
- [structure.md](structure.md)

### Content + Admin
- [content.md](content.md)
- [admin.md](admin.md)
- [backup.md](backup.md)

### Customization
- [themes.md](themes.md)
- [components.md](components.md)
- [hooks.md](hooks.md)
- [core-components.md](core-components.md)

## Glossary

- **Pages**: Markdown or MDX files under `site/pages/` that map to URLs.
- **Blocks**: Reusable content files under `site/blocks/` inserted into pages.
- **Themes**: Layouts + helpers under `site/themes/` that control presentation.
- **Components**: PHP components under `site/components/` (or theme components) used in MDX.
- **Frontmatter**: YAML at the top of a Markdown file that sets metadata like title or layout type.

## Versioning

Public docs track `dev` by default. Release behavior is tied to tagged versions in `CHANGELOG.md`.

Contributor and maintainer docs live in `docs/`.
