# Layout Type System

## Overview

Flint supports flexible, type-based layouts that allow different content types to have unique designs without touching core code.

## How It Works

### Layout Cascade

When rendering a page, Flint follows this cascade:

1. **Check for typed layout**: `layout-{type}.php`
2. **Fallback to default**: `layout.php`

This is handled automatically in `App.php` lines 693-716.

### Example

```yaml
---
title: Building a Modern CMS
type: post
author: John Doe
date: January 3, 2026
---
```

**Cascade**:
- Looks for: `themes/motion/layout-post.php` ✓ (uses this)
- Falls back to: `themes/motion/layout.php` (if above doesn't exist)

## Use Cases

### Blog Posts (`type: post`)

**File**: `layout-post.php`

**Features**:
- Gradient header with author/date
- Reading progress bar
- Back navigation
- Focused reading experience

**Frontmatter**:
```yaml
---
title: My Blog Post
type: post
author: Jane Smith
date: January 3, 2026
readtime: 5
---
```

### Landing Pages (`type: landing`)

**File**: `layout-landing.php`

**Features**:
- Full-width hero sections
- No header/footer navigation
- CTA-focused design
- Minimal distractions

**Frontmatter**:
```yaml
---
title: Get Started
type: landing
banner: /uploads/hero.jpg
---
```

### Documentation (`type: docs`)

**File**: `layout-docs.php`

**Features**:
- Sidebar navigation
- Table of contents
- Previous/next links
- Search integration

**Frontmatter**:
```yaml
---
title: Installation Guide
type: docs
order: 1
---
```

### Portfolio Items (`type: portfolio`)

**File**: `layout-portfolio.php`

**Features**:
- Image gallery
- Project details
- Skills/tech stack
- Client testimonials

**Frontmatter**:
```yaml
---
title: E-Commerce Platform
type: portfolio
banner: /uploads/project-hero.jpg
client: Acme Corp
---
```

### Minimal Reading (`type: minimal`)

**File**: `layout-minimal.php`

**Features**:
- No navigation
- Maximum line width for reading
- High contrast
- Print-friendly

**Frontmatter**:
```yaml
---
title: Privacy Policy
type: minimal
---
```

## Creating Custom Layouts

### 1. Create Layout File

Create `themes/{theme}/layout-{type}.php`:

```php
<?php
// themes/motion/layout-custom.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Your custom head -->
</head>
<body>
    <!-- Your custom design -->
    <?= $viewContent ?>  <!-- Content goes here -->
</body>
</html>
```

### 2. Use in Content

```markdown
---
title: My Page
type: custom
---

Content here...
```

### 3. Access Frontmatter Data

All frontmatter is available in the layout via `$page['meta']`:

```php
<?= htmlspecialchars($page['meta']['title']) ?>
<?= htmlspecialchars($page['meta']['author'] ?? '') ?>
<?= htmlspecialchars($page['meta']['date'] ?? '') ?>
<?= htmlspecialchars($page['meta']['readtime'] ?? '') ?>
```

## Variables Available in Layouts

```php
$site              // Config [site] section
$page              // Page data with ['meta'], ['content_html']
$viewContent       // Rendered view.php output
$content           // Raw rendered HTML
$isAdmin           // Boolean - is user admin?
$currentPath       // Current URL path
$componentAssets   // Assets from components (scripts, styles)
$pageStatus        // 'published', 'draft', or 'hidden'
```

## Examples Included

### `layout-post.php` (Motion Theme)

**Location**: `themes/motion/layout-post.php`

**Features**:
- Gradient purple header
- Reading progress bar (JavaScript)
- Author, date, read time metadata
- Back to home link
- Responsive design

**Demo**: See `/blog/example-post`

## Best Practices

### 1. Naming Convention

Use descriptive, lowercase names:
- ✅ `layout-post.php`
- ✅ `layout-landing.php`
- ✅ `layout-docs.php`
- ❌ `layout-Post.php`
- ❌ `layout-my-custom-thing.php` (too long)

### 2. Fallback Gracefully

Always have a `layout.php` as fallback. Type-specific layouts are optional enhancements.

### 3. Share Common Elements

Extract common code to partials:

```php
// themes/motion/partials/head.php
<meta charset="UTF-8">
<title><?= htmlspecialchars($page['meta']['title']) ?></title>

// Then in layouts:
<?php include __DIR__ . '/partials/head.php'; ?>
```

### 4. Use View.php for Content Wrapper

Keep `view.php` for content-specific styling, layouts for page structure:

- **Layout**: Header, footer, navigation, overall structure
- **View**: Content area styling, article wrappers

### 5. Document Custom Fields

If your layout uses custom frontmatter fields, document them:

```php
/**
 * Layout: Portfolio
 *
 * Required fields:
 * - title: Project name
 * - type: portfolio
 *
 * Optional fields:
 * - client: Client name
 * - tech: Comma-separated tech stack
 * - url: Live project URL
 */
```

## Performance

Layout selection is done once per request with simple file existence checks. No performance impact.

## Security

Layout filenames are NOT user-controlled. The `type` field is read from frontmatter but:
- Only alphanumeric and hyphens are safe
- File must exist in theme directory
- No path traversal possible

## Migration

### From Single Layout

**Before**:
```
themes/motion/
  layout.php  (handles all pages)
```

**After**:
```
themes/motion/
  layout.php       (default for pages)
  layout-post.php  (for blog posts)
```

Existing pages continue using `layout.php`. Add `type: post` to posts to use new layout.

## Testing

Test layout cascade:

```bash
# 1. Create layout-test.php
echo '<?php echo "TEST LAYOUT: " . $page["meta"]["title"]; ?>' > themes/motion/layout-test.php

# 2. Create test page
cat > site/pages/test.md <<EOF
---
title: Test Page
type: test
---
Content here.
EOF

# 3. Visit http://localhost:8000/test
# Should show: "TEST LAYOUT: Test Page"

# 4. Remove type field
# Should show default layout.php
```

## Troubleshooting

### Layout not loading

**Check**:
1. Filename matches type: `layout-{type}.php`
2. File is in active theme directory
3. Type is in frontmatter: `type: {type}`
4. No typos in type field

### Wrong layout used

**Debug**:
```php
// Add to layout file:
echo "<!-- Layout: " . basename(__FILE__) . " -->";
// View page source to see which layout was used
```

### Fallback not working

Ensure `layout.php` exists as fallback:
```bash
ls themes/motion/layout.php
```

## Future Enhancements

Potential additions (not yet implemented):
- View cascade: `view-{type}.php` → `view.php`
- Theme-level type config
- Type templates (starter templates for new types)
- Admin UI for managing layouts

## Summary

The layout type system provides:
- ✅ Flexibility - Any content can have any layout
- ✅ Simplicity - Just add `type:` to frontmatter
- ✅ Versatility - Create unlimited layout types
- ✅ Maintainability - No core code changes needed
- ✅ Performance - Fast file existence checks
- ✅ Safety - Controlled layout selection

Perfect for blogs, documentation, portfolios, landing pages, and any other content type you can imagine!
