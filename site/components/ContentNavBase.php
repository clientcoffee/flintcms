<?php

namespace Components;

use Flint\Paths;
use Flint\RenderComponent;
use Flint\ThemeContext;

/**
 * Shared helpers for content navigation components.
 */
abstract class ContentNavBase extends RenderComponent
{
    protected static function getCurrentPath(): string
    {
        // Prefer the theme context, fallback to the request URI.
        $path = (string)ThemeContext::get('currentPath', '');
        if ($path === '') {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        }

        if ($path === '') {
            $path = '/';
        }

        return $path;
    }

    protected static function isAdmin(): bool
    {
        // Admin state comes from the theme context.
        return (bool)ThemeContext::get('isAdmin', false);
    }

    /**
     * Resolve current page context from the request path.
     *
     * @return array{filePath:string,relativeFile:string,relativeDir:string,dirPath:string,slug:string,isIndex:bool}|null.
     */
    protected static function resolveCurrentContext(): ?array
    {
        // Pages directory comes from the CMS Paths helper.
        $pagesDir = isset(Paths::$pagesDir) ? Paths::$pagesDir : '';
        if ($pagesDir === '' || !is_dir($pagesDir)) {
            return null;
        }

        // Normalize the request into a slug we can match on disk.
        $currentPath = self::getCurrentPath();
        $normalizedSlug = trim($currentPath, '/');
        if ($normalizedSlug === '') {
            $normalizedSlug = 'index';
        }

        // Reject path traversal or unsafe input early.
        if (!self::isSafePathSegment($normalizedSlug)) {
            return null;
        }

        // Try file and index variants for both MD and MDX.
        $candidatePaths = [
            $pagesDir . '/' . $normalizedSlug . '.md',
            $pagesDir . '/' . $normalizedSlug . '.mdx',
            $pagesDir . '/' . $normalizedSlug . '/index.md',
            $pagesDir . '/' . $normalizedSlug . '/index.mdx',
        ];

        $filePath = '';
        foreach ($candidatePaths as $candidatePath) {
            if (file_exists($candidatePath)) {
                $filePath = $candidatePath;
                break;
            }
        }

        if ($filePath === '') {
            return null;
        }

        // Build relative metadata for navigation helpers.
        $relativeFile = ltrim(str_replace($pagesDir, '', $filePath), '/');
        $relativeDir = trim(dirname($relativeFile), '.');
        if ($relativeDir === '.') {
            $relativeDir = '';
        }
        $dirPath = $relativeDir === '' ? $pagesDir : $pagesDir . '/' . $relativeDir;

        $baseName = basename(preg_replace('/\.(md|mdx)$/i', '', $relativeFile));
        $isIndex = $baseName === 'index';

        return [
            'filePath' => $filePath,
            'relativeFile' => $relativeFile,
            'relativeDir' => $relativeDir,
            'dirPath' => $dirPath,
            'slug' => slug_from_path($relativeFile),
            'isIndex' => $isIndex,
        ];
    }

    protected static function isSafePathSegment(string $path): bool
    {
        // Allow only safe characters and no traversal.
        if ($path === '') {
            return false;
        }

        if (str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, "\0")) {
            return false;
        }

        return (bool)preg_match('/^[a-zA-Z0-9\/_-]+$/', $path);
    }

    protected static function findIndexFile(string $directory): ?string
    {
        // Prefer index.md, then fallback to index.mdx.
        $indexMd = $directory . '/index.md';
        if (file_exists($indexMd)) {
            return $indexMd;
        }

        $indexMdx = $directory . '/index.mdx';
        if (file_exists($indexMdx)) {
            return $indexMdx;
        }

        return null;
    }

    protected static function listChildPages(
        string $directory,
        string $relativeDir,
        bool $includePrivate,
        bool $excludeIndex = true
    ): array {
        // Collect child pages from the filesystem.
        if (!is_dir($directory)) {
            return [];
        }

        $entries = scandir($directory);
        if ($entries === false) {
            return [];
        }

        $items = [];

        foreach ($entries as $entry) {
            // Skip dot entries and hidden files.
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }

            $fullPath = $directory . '/' . $entry;
            if (is_dir($fullPath)) {
                continue;
            }

            if (!preg_match('/\.(md|mdx)$/i', $entry)) {
                continue;
            }

            $baseName = basename($entry, '.' . pathinfo($entry, PATHINFO_EXTENSION));
            if ($excludeIndex && $baseName === 'index') {
                continue;
            }

            $relativeFile = ltrim($relativeDir . '/' . $entry, '/');
            $meta = extract_frontmatter($fullPath);
            $status = resolve_status($meta);

            if (!$includePrivate && in_array($status, ['hidden', 'draft'], true)) {
                continue;
            }

            // Derive a human label from title or filename.
            $label = trim((string)($meta['title'] ?? ''));
            if ($label !== '') {
                $label = strip_inline_markdown($label);
            }

            if ($label === '') {
                $label = self::labelFromRelative($relativeFile, slug_from_path($relativeFile));
            }

            $items[] = [
                'label' => $label,
                'path' => slug_from_path($relativeFile),
                'relativeFile' => $relativeFile,
                'order' => self::getOrderValue($meta),
                'date' => self::getDateValue($meta),
            ];
        }

        $hasOrder = false;
        $hasDate = false;
        foreach ($items as $item) {
            if ($item['order'] !== null) {
                $hasOrder = true;
            }
            if ($item['date'] !== null) {
                $hasDate = true;
            }
        }

        // Sort by order, date, or label based on available metadata.
        usort($items, function (array $a, array $b) use ($hasOrder, $hasDate) {
            if ($hasOrder) {
                return ($a['order'] ?? PHP_INT_MAX) <=> ($b['order'] ?? PHP_INT_MAX);
            }

            if ($hasDate) {
                return ($a['date'] ?? PHP_INT_MAX) <=> ($b['date'] ?? PHP_INT_MAX);
            }

            return strcasecmp($a['label'], $b['label']);
        });

        return $items;
    }

    protected static function getOrderValue(array $meta): ?int
    {
        // Normalize numeric order values from frontmatter.
        if (!array_key_exists('order', $meta)) {
            return null;
        }

        $raw = trim((string)$meta['order']);
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }

        return (int)$raw;
    }

    protected static function getDateValue(array $meta): ?int
    {
        // Parse date strings into timestamps for sorting.
        $raw = trim((string)($meta['date'] ?? ''));
        if ($raw === '') {
            return null;
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }

        return $timestamp;
    }

    protected static function extractFrontmatter(string $filePath): array
    {
        // Read the frontmatter block if the file starts with it.
        $contents = file_get_contents($filePath);
        if ($contents === false || !str_starts_with($contents, "---")) {
            return [];
        }

        $parts = preg_split('/^---$/m', $contents, 3);
        if (!is_array($parts) || count($parts) !== 3) {
            return [];
        }

        return parse_yaml($parts[1]);
    }

    protected static function resolveStatus(array $meta): string
    {
        // Normalize status and draft flags.
        $status = strtolower(trim((string)($meta['status'] ?? 'published')));
        if ($status === '') {
            $status = 'published';
        }

        $draftFlag = strtolower(trim((string)($meta['draft'] ?? '')));
        if (in_array($draftFlag, ['1', 'true', 'yes', 'on'], true)) {
            $status = 'draft';
        }

        return $status;
    }

    protected static function slugFromRelative(string $relativeFile): string
    {
        // Convert a relative filename into a route slug.
        $relativeFile = str_replace('\\', '/', $relativeFile);
        $trimmed = preg_replace('/\.(md|mdx)$/i', '', $relativeFile);
        $trimmed = ltrim($trimmed, '/');
        $baseName = basename($trimmed);

        if ($baseName === 'index') {
            $dir = trim(dirname($trimmed), '.');
            if ($dir === '' || $dir === '.') {
                return '/';
            }
            return '/' . $dir;
        }

        return '/' . $trimmed;
    }

    protected static function labelFromRelative(string $relativeFile, string $slug): string
    {
        // Produce a label when no frontmatter title exists.
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
