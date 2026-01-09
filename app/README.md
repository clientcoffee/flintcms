# Flint

A deadly simple, drop-in flat-file CMS for PHP.

## Getting Started

1.  **Upload:** Upload all files to your web server (FTP/SFTP).
2.  **Visit:** Go to your website URL.
3.  **Setup:** Fill out the one-time setup form.

That's it.

## Writing Content

Create Markdown files in the `/content` directory.

*   `/content/pages/index.md` -> Your Homepage
*   `/content/pages/about.md` -> /about

### Using Components

You can use components inside your Markdown (stored in `/content/components`):

```markdown
# My Page

<Callout type="alert">This is important!</Callout>
```

## Configuration

Edit `config.php` to change your theme or site name.

## Themes

Themes are located in `/themes`. The default theme is **Motion**, which features a clean, modern design. Themes use Tailwind CSS by default via CDN.

## Requirements

*   PHP 8.2 or higher.
