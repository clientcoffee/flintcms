<?php

namespace Components;

use Flint\Auth;
use Flint\RenderComponent;
use Flint\Paths;

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

        // Parser is used only for inline title rendering (no full page render).
        $parser = new \Flint\Parser($app);

        // Determine admin state for hidden/draft visibility.
        $auth = new Auth($app);
        $isAdmin = $auth->isAdmin();
        $pagesDir = $app->root . '/site/pages';

        // Sitemap cache uses a fingerprint of page count + latest mtime.
        $cacheEnabled = self::isSitemapCacheEnabled($app);
        $fingerprint = $cacheEnabled ? self::pagesFingerprint($pagesDir) : null;
        $cachePath = $cacheEnabled ? self::sitemapCachePath($isAdmin) : null;

        if ($cacheEnabled && $fingerprint !== null && $cachePath !== null) {
            $cached = self::readSitemapCache($cachePath, $fingerprint);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Build a tree of pages based on URL structure.
        $pages = self::collectPages($pagesDir, '', $isAdmin, $parser);
        if (empty($pages)) {
            return '';
        }
        $tree = self::buildUrlTree($pages);

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
            <?= self::renderItems($tree['children'] ?? [], $isAdmin, $tree['page'] ?? null) ?>
        </ul>
        <?php

        $markup = trim((string)ob_get_clean());

        if ($cacheEnabled && $fingerprint !== null && $cachePath !== null) {
            self::writeSitemapCache($cachePath, $fingerprint, $markup);
        }

        // Return the final sitemap markup.
        return $markup;
    }

    private static function isSitemapCacheEnabled($app): bool
    {
        $system = $app->config['system'] ?? [];
        if (!is_array($system)) {
            return true;
        }

        if (!array_key_exists('sitemap_cache', $system)) {
            return true;
        }

        $value = $system['sitemap_cache'];
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private static function pagesFingerprint(string $pagesDir): ?string
    {
        if (!is_dir($pagesDir)) {
            return null;
        }

        // Track only markdown files for an inexpensive invalidation signal.
        $count = 0;
        $maxMtime = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($pagesDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $name = $file->getFilename();
            if (!preg_match('/\.(md|mdx)$/i', $name)) {
                continue;
            }
            $count++;
            $mtime = $file->getMTime();
            if ($mtime > $maxMtime) {
                $maxMtime = $mtime;
            }
        }

        return $count . ':' . $maxMtime;
    }

    private static function sitemapCachePath(bool $isAdmin): string
    {
        // Separate caches for admin vs public visibility rules.
        $cacheDir = Paths::$cacheDir . '/sitemap';
        $suffix = $isAdmin ? 'admin' : 'public';
        return $cacheDir . '/sitemap-' . $suffix . '.php';
    }

    private static function readSitemapCache(string $cachePath, string $fingerprint): ?string
    {
        if (!is_file($cachePath)) {
            return null;
        }

        // Cache payload is a tiny PHP array for fast include.
        $payload = require $cachePath;
        if (!is_array($payload) || ($payload['fingerprint'] ?? '') !== $fingerprint) {
            return null;
        }

        $html = $payload['html'] ?? null;
        return is_string($html) ? $html : null;
    }

    private static function writeSitemapCache(string $cachePath, string $fingerprint, string $html): void
    {
        $cacheDir = dirname($cachePath);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        // Persist as PHP for low overhead reads.
        $payload = [
            'fingerprint' => $fingerprint,
            'html' => $html
        ];

        $exported = var_export($payload, true);
        $contents = "<?php\n\nreturn {$exported};\n";
        file_put_contents($cachePath, $contents, LOCK_EX);
    }

    private static function collectPages(
        string $baseDir,
        string $relativeDir,
        bool $includePrivate,
        \Flint\Parser $parser
    ): array {
        // Walk the pages directory and build a flat list of pages.
        if (!is_dir($baseDir)) {
            return [];
        }

        $directory = $relativeDir === '' ? $baseDir : $baseDir . '/' . $relativeDir;
        $entries = scandir($directory);
        if ($entries === false) {
            return [];
        }

        $files = [];

        foreach ($entries as $entry) {
            // Skip dot entries and hidden files.
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }

            $fullPath = $directory . '/' . $entry;
            if (is_dir($fullPath)) {
                $childRelative = ltrim($relativeDir . '/' . $entry, '/');
                $children = self::collectPages($baseDir, $childRelative, $includePrivate, $parser);
                $files = array_merge($files, $children);
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
            if ($label !== '') {
                $label = strip_inline_markdown($label);
            }

            if ($label !== '') {
                $label = self::stripInlineLabel($label, $parser);
            }

            if ($label === '') {
                $label = self::labelFromRelative($relativeFile, $slug);
            }

            $files[] = [
                'type' => 'file',
                'label' => $label,
                'path' => $slug,
                'status' => $status,
            ];
        }

        return $files;
    }

    private static function buildUrlTree(array $pages): array
    {
        /** @var array{page: ?array, children: array<string, array{segment:string,page:?array,children:array}>} $root */
        $root = [
            'page' => null,
            'children' => []
        ];

        foreach ($pages as $page) {
            $rawPath = (string)($page['path'] ?? '');
            $trimmed = trim($rawPath, '/');
            $segments = $trimmed === '' ? [] : explode('/', $trimmed);

            /** @var array{page: ?array, children: array<string, array{segment:string,page:?array,children:array}>} $node */
            $node = &$root;
            foreach ($segments as $segment) {
                if (!isset($node['children'][$segment])) {
                    $node['children'][$segment] = [
                        'segment' => $segment,
                        'page' => null,
                        'children' => []
                    ];
                }
                $node = &$node['children'][$segment];
            }

            if ($segments === []) {
                $root['page'] = $page;
            } else {
                $node['page'] = $page;
            }
        }

        return $root;
    }

    private static function renderItems(array $nodes, bool $isAdmin, ?array $rootPage): string
    {
        // Render the tree recursively as nested lists.
        ob_start();
        $children = array_values($nodes);
        usort($children, fn($a, $b) => strcmp(self::nodeLabel($a), self::nodeLabel($b)));

        if ($rootPage) {
            $statusClass = self::pageStatusClass($rootPage, $isAdmin);
            $label = (string)($rootPage['label'] ?? '');
            $path = (string)($rootPage['path'] ?? '#');
            ?>
            <li class="sitemap__item<?= $statusClass ?>">
                <a class="sitemap__link" href="<?= esc_html($path) ?>">
                    <span class="sitemap__label"><?= esc_html($label) ?></span>
                </a>
            </li>
            <?php
        }

        foreach ($children as $node) {
            $page = $node['page'] ?? null;
            $childNodes = $node['children'] ?? [];
            if ($page) {
                $statusClass = self::pageStatusClass($page, $isAdmin);
                $label = (string)($page['label'] ?? '');
                $path = (string)($page['path'] ?? '#');
                ?>
                <li class="sitemap__item<?= $statusClass ?>">
                    <a class="sitemap__link" href="<?= esc_html($path) ?>">
                        <span class="sitemap__label"><?= esc_html($label) ?></span>
                    </a>
                    <?php if (!empty($childNodes)) : ?>
                        <ul>
                            <?= self::renderItems($childNodes, $isAdmin, null) ?>
                        </ul>
                    <?php endif; ?>
                </li>
                <?php
            } else {
                ?>
                <li class="sitemap__item sitemap__item--group">
                    <span class="sitemap__label"><?= esc_html(self::nodeLabel($node)) ?></span>
                    <?php if (!empty($childNodes)) : ?>
                        <ul>
                            <?= self::renderItems($childNodes, $isAdmin, null) ?>
                        </ul>
                    <?php endif; ?>
                </li>
                <?php
            }
        }

        return trim((string)ob_get_clean());
    }

    private static function pageStatusClass(array $page, bool $isAdmin): string
    {
        $status = $page['status'] ?? 'published';
        $isHidden = $status === 'hidden';
        $isDraft = $status === 'draft';

        if ($isAdmin && $isHidden) {
            return ' sitemap__item--hidden';
        }

        if ($isAdmin && $isDraft) {
            return ' sitemap__item--draft';
        }

        return '';
    }

    private static function nodeLabel(array $node): string
    {
        if (!empty($node['page']['label'])) {
            return (string)$node['page']['label'];
        }

        $segment = (string)($node['segment'] ?? '');
        if ($segment === '') {
            return '';
        }

        $segment = str_replace(['-', '_'], ' ', $segment);
        return ucwords($segment);
    }

    private static function stripInlineLabel(string $label, \Flint\Parser $parser): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }

        $rendered = $parser->renderInlineMarkdown($label);
        $plain = trim(strip_tags($rendered));
        if ($plain === '') {
            return '';
        }

        return html_entity_decode($plain, ENT_QUOTES, 'UTF-8');
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
