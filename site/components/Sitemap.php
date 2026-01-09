<?php

namespace Components;

use Flint\Auth;
use Flint\RenderComponent;

class Sitemap extends RenderComponent
{
    public static function render(array $props, string $content): string
    {
        $app = self::getApp();
        if (!$app) {
            return '';
        }

        $auth = new Auth($app);
        $isAdmin = $auth->isAdmin();
        $pagesDir = $app->root . '/site/pages';

        $items = self::buildTree($pagesDir, '', $isAdmin);
        if (empty($items)) {
            return '';
        }

        $attrs = [
            'class' => trim((string)self::prop($props, 'class', 'sitemap'))
        ];
        $id = trim((string)self::prop($props, 'id', ''));
        if ($id !== '') {
            $attrs['id'] = $id;
        }

        $html = '<ul ' . self::buildAttributes($attrs) . '>';
        $html .= self::renderItems($items, $isAdmin);
        $html .= '</ul>';

        return $html;
    }

    private static function buildTree(string $baseDir, string $relativeDir, bool $includePrivate): array
    {
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
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }

            $fullPath = $directory . '/' . $entry;
            if (is_dir($fullPath)) {
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

            if (!preg_match('/\.(md|mdx)$/i', $entry)) {
                continue;
            }

            $relativeFile = ltrim($relativeDir . '/' . $entry, '/');
            $slug = self::slugFromRelative($relativeFile);
            $meta = self::extractFrontmatter($fullPath);
            $status = self::resolveStatus($meta);

            if (!$includePrivate && in_array($status, ['hidden', 'draft'], true)) {
                continue;
            }

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

        usort($directories, fn($a, $b) => strcmp($a['label'], $b['label']));
        usort($files, fn($a, $b) => strcmp($a['label'], $b['label']));

        return array_merge($directories, $files);
    }

    private static function renderItems(array $items, bool $isAdmin): string
    {
        $html = '';

        foreach ($items as $item) {
            if ($item['type'] === 'directory') {
                $html .= '<li>';
                $html .= '<span>' . self::escape($item['label']) . '</span>';
                $html .= '<ul>' . self::renderItems($item['children'], $isAdmin) . '</ul>';
                $html .= '</li>';
                continue;
            }

            $html .= '<li>';
            $html .= '<a href="' . self::escape($item['path']) . '">' . self::escape($item['label']) . '</a>';

            if ($isAdmin) {
                if ($item['status'] === 'hidden') {
                    $html .= self::statusIcon('eye', 'Hidden');
                } elseif ($item['status'] === 'draft') {
                    $html .= self::statusIcon('pencil', 'Draft');
                }
            }

            $html .= '</li>';
        }

        return $html;
    }

    private static function statusIcon(string $type, string $label): string
    {
        $path = '';
        if ($type === 'eye') {
            $path = '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"></path>'
                . '<circle cx="12" cy="12" r="3"></circle>';
        } else {
            $path = '<path d="M12 20h9"></path>'
                . '<path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"></path>';
        }

        return '<svg role="img" aria-label="' . self::escape($label) . '" viewBox="0 0 24 24" width="14" height="14"'
            . ' style="display:inline-block;vertical-align:text-bottom;margin-left:0.35rem;color:#9ca3af;"'
            . ' fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">'
            . $path . '</svg>';
    }

    private static function extractFrontmatter(string $filePath): array
    {
        $contents = file_get_contents($filePath);
        if ($contents === false || !str_starts_with($contents, "---")) {
            return [];
        }

        $parts = preg_split('/^---$/m', $contents, 3);
        if (!is_array($parts) || count($parts) !== 3) {
            return [];
        }

        return self::parseYamlLite($parts[1]);
    }

    private static function parseYamlLite(string $yamlText): array
    {
        $metadata = [];
        $lines = explode("\n", $yamlText);

        foreach ($lines as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            [$keyText, $valueText] = explode(':', $line, 2);
            $key = trim($keyText);
            if ($key === '') {
                continue;
            }

            $value = trim(trim($valueText), "\"'");
            $metadata[$key] = $value;
        }

        return $metadata;
    }

    private static function resolveStatus(array $meta): string
    {
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

    private static function slugFromRelative(string $relativeFile): string
    {
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

    private static function labelFromRelative(string $relativeFile, string $slug): string
    {
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
