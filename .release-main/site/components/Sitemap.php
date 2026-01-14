<?php

namespace Components;

use Flint\Auth;
use Flint\RenderComponent;

/**
 * Render a sitemap of all pages, including hidden/drafts for admins.
 */
class Sitemap extends RenderComponent
{
    public static function render(array $props, string $content): string
    {
        // Resolve the app instance for filesystem access.
        $app = get_app();
        if (!$app) {
            return '';
        }

        // Determine admin state for hidden/draft visibility.
        $auth = new Auth($app);
        $isAdmin = $auth->isAdmin();
        $pagesDir = $app->root . '/site/pages';

        // Build a recursive tree of pages.
        $items = self::buildTree($pagesDir, '', $isAdmin);
        if (empty($items)) {
            return '';
        }

        // Collect list attributes for the wrapper.
        $attrs = [
            'class' => trim((string)prop($props, 'class', 'sitemap'))
        ];
        $id = trim((string)prop($props, 'id', ''));
        if ($id !== '') {
            $attrs['id'] = $id;
        }

        ob_start();
        ?>
        <ul <?= html_attrs($attrs) ?>>
            <?= self::renderItems($items, $isAdmin) ?>
        </ul>
        <?php

        // Return the final sitemap markup.
        return trim((string)ob_get_clean());
    }

    private static function buildTree(string $baseDir, string $relativeDir, bool $includePrivate): array
    {
        // Walk the pages directory and build a mixed tree.
        if (!is_dir($baseDir)) {
            return [];
        }

        $directory = $relativeDir === '' ? $baseDir : $baseDir . '/' . $relativeDir;
        $entries = scandir($directory);
        if ($entries === false) {
            return [];
        }

        $directories = [];
        $files = [];

        foreach ($entries as $entry) {
            // Skip dot entries and hidden files.
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }

            $fullPath = $directory . '/' . $entry;
            if (is_dir($fullPath)) {
                // Recurse into subdirectories and keep non-empty branches.
                $childRelative = ltrim($relativeDir . '/' . $entry, '/');
                $children = self::buildTree($baseDir, $childRelative, $includePrivate);
                if (!empty($children)) {
                    $directories[] = [
                        'type' => 'directory',
                        'label' => $entry,
                        'children' => $children
                    ];
                }
                continue;
            }

            // Only include markdown files.
            if (!preg_match('/\.(md|mdx)$/i', $entry)) {
                continue;
            }

            $relativeFile = ltrim($relativeDir . '/' . $entry, '/');
            $slug = slug_from_path($relativeFile);
            $meta = extract_frontmatter($fullPath);
            $status = resolve_status($meta);

            if (!$includePrivate && in_array($status, ['hidden', 'draft'], true)) {
                continue;
            }

            // Use frontmatter title when available.
            $label = trim((string)($meta['title'] ?? ''));
            if ($label === '') {
                $label = self::labelFromRelative($relativeFile, $slug);
            }

            $files[] = [
                'type' => 'file',
                'label' => $label,
                'path' => $slug,
                'status' => $status
            ];
        }

        // Sort directories and files alphabetically by label.
        usort($directories, fn($a, $b) => strcmp($a['label'], $b['label']));
        usort($files, fn($a, $b) => strcmp($a['label'], $b['label']));

        return array_merge($directories, $files);
    }

    private static function renderItems(array $items, bool $isAdmin): string
    {
        // Render the tree recursively as nested lists.
        ob_start();
        foreach ($items as $item) {
            if ($item['type'] === 'directory') {
                ?>
                <li class="sitemap__group">
                    <span class="sitemap__group-label"><?= esc_html($item['label']) ?></span>
                    <ul class="sitemap__group-list">
                        <?= self::renderItems($item['children'], $isAdmin) ?>
                    </ul>
                </li>
                <?php
                continue;
            }

            $status = $item['status'] ?? 'published';
            $isHidden = $status === 'hidden';
            $isDraft = $status === 'draft';
            $statusClass = '';

            if ($isAdmin && ($isHidden || $isDraft)) {
                $statusClass = $isHidden ? ' sitemap__item--hidden' : ' sitemap__item--draft';
            }
            ?>
            <li class="sitemap__item<?= $statusClass ?>">
                <a class="sitemap__link" href="<?= esc_html($item['path']) ?>">
                    <?= esc_html($item['label']) ?>
                </a>
                <?php if ($isAdmin && $isHidden) : ?>
                    <?= self::statusIcon('hidden', 'Hidden') ?>
                <?php elseif ($isAdmin && $isDraft) : ?>
                    <?= self::statusIcon('draft', 'Draft') ?>
                <?php endif; ?>
            </li>
            <?php
        }

        return trim((string)ob_get_clean());
    }

    private static function statusIcon(string $type, string $label): string
    {
        // Provide a minimal inline SVG for status states.
        $path = '';
        if ($type === 'hidden') {
            $path = '<path d="M17.94 17.94A10.94 10.94 0 0112 20c-5 0-9.27-3.11-11-8 1.04-2.79 2.8-5 5-6.28"></path>'
                . '<path d="M9.9 4.24A10.94 10.94 0 0112 4c5 0 9.27 3.11 11 8-0.55 1.47-1.32 2.78-2.25 3.88"></path>'
                . '<path d="M14.12 14.12a3 3 0 01-4.24-4.24"></path>'
                . '<path d="M1 1l22 22"></path>';
        } else {
            $path = '<path d="M12 20h9"></path>'
                . '<path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"></path>';
        }

        ob_start();
        ?>
        <svg class="sitemap__icon" role="img" aria-label="<?= esc_html($label) ?>" viewBox="0 0 24 24" width="14" height="14"
            fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <?= $path ?>
        </svg>
        <?php

        return trim((string)ob_get_clean());
    }

    private static function labelFromRelative(string $relativeFile, string $slug): string
    {
        // Build a fallback label from the filename.
        if ($slug === '/') {
            return 'home';
        }

        $relativeFile = str_replace('\\', '/', $relativeFile);
        $trimmed = preg_replace('/\.(md|mdx)$/i', '', $relativeFile);
        $baseName = basename($trimmed);

        if ($baseName === 'index') {
            return 'index';
        }

        return $baseName;
    }
}
