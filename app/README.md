# Flint

A deadly simple, drop-in flat-file CMS for PHP.

## Getting Started

1.  **Upload:** Upload all files to your web server (FTP/SFTP).
2.  **Visit:** Go to your website URL.
3.  **Setup:** Fill out the one-time setup form.

That's it.

## Writing Content

Create Markdown files in the `/site` directory.

*   `/site/pages/index.md` -> Your Homepage
*   `/site/pages/about.md` -> /about

### Using Components

You can use components inside your Markdown (stored in `/site/components`):

```markdown
# My Page

<Callout type="alert">This is important!</Callout>
```

## Configuration

Edit `site/config.php` to change your theme or site name.

## Developer Scripts

### Merge All Open PRs

Use `scripts/merge-open-prs.sh` to merge open PRs targeting `main`. It merges
the first PR (by number), then merges `main` into the remaining PR branches
and merges those PRs.

Requirements:
- `gh auth login` completed for this repo
- Clean working tree

Optional overrides:
- `BASE_BRANCH=main` (default)
- `REMOTE=origin` (default)
- `LIMIT=200` (max PRs to fetch)
- `MERGE_METHOD=--merge` (or `--squash`, `--rebase`)

Example:

```bash
scripts/merge-open-prs.sh
```

## Themes

Themes are located in `/site/themes`. The default theme is **Motion**, which features a clean, modern design. Themes use Tailwind CSS by default via CDN.

## Requirements

*   PHP 8.2 or higher.
