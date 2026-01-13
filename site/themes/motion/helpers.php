<?php

/**
 * Theme helper functions for the Motion theme.
 */

/**
 * Helper function to convert emoji slugs to emoji characters.
 */
function getEmojiFromSlug(string $slug): string
{
    // Define the supported emoji tokens in one place to keep templates simple.
    $emojiMap = [
        'rocket' => '🚀',
        'sparkles' => '✨',
        'fire' => '🔥',
        'book' => '📚',
        'lightning' => '⚡',
        'star' => '⭐',
        'heart' => '❤️',
        'check' => '✅',
        'warning' => '⚠️',
        'construction' => '🚧',
        'info' => 'ℹ️',
        'question' => '❓',
        'lightbulb' => '💡',
        'hammer' => '🔨',
        'wrench' => '🔧',
        'gear' => '⚙️',
        'lock' => '🔒',
        'key' => '🔑',
        'envelope' => '✉️',
        'phone' => '📞',
        'home' => '🏠',
        'globe' => '🌍',
        'eye' => '👁️',
    ];

    // Return the mapped emoji or a safe empty string (no fallback icons).
    return $emojiMap[$slug] ?? '';
}

/**
 * Render external stylesheet links from component assets.
 */
function motion_theme_render_component_styles(array $componentAssets): void
{
    // Component assets are collected by the parser and passed via ThemeContext.
    foreach ($componentAssets['styles'] ?? [] as $style) {
        $href = trim($style['href'] ?? '');
        if ($href === '') {
            continue;
        }
        echo '<link rel="stylesheet" href="' . htmlspecialchars($href) . '">' . "\n";
    }
}

/**
 * Render safe inline styles that components register.
 */
function motion_theme_render_component_inline_styles(array $componentAssets): void
{
    // Inline styles are opt-in and must pass a safety check.
    $inlineStyles = $componentAssets['inline_styles'] ?? [];
    $safeSnippets = [];

    foreach ($inlineStyles as $inlineStyle) {
        $content = $inlineStyle['content'] ?? '';
        if ($content === '') {
            continue;
        }

        if (!motion_theme_is_safe_inline_style($content)) {
            error_log('Security: Blocked potentially malicious inline style content');
            continue;
        }

        $safeSnippets[] = $content;
    }

    if (empty($safeSnippets)) {
        return;
    }

    echo "<style>\n";
    foreach ($safeSnippets as $snippet) {
        echo $snippet . "\n";
    }
    echo "</style>\n";
}

/**
 * Render external scripts that a component requested.
 */
function motion_theme_render_component_scripts(array $componentAssets, string $position = 'footer'): void
{
    // Scripts are split into head/footer for performance.
    foreach ($componentAssets['scripts'] ?? [] as $script) {
        $scriptPosition = $script['position'] ?? 'footer';
        if ($scriptPosition !== $position) {
            continue;
        }

        $src = trim($script['src'] ?? '');
        if ($src === '') {
            continue;
        }

        $typeAttr = '';
        if (!empty($script['type'])) {
            $typeAttr = ' type="' . htmlspecialchars($script['type']) . '"';
        }

        echo '<script src="' . htmlspecialchars($src) . '"' . $typeAttr . "></script>\n";
    }
}

/**
 * Render inline scripts that a component requested.
 */
function motion_theme_render_component_inline_scripts(array $componentAssets, string $position = 'footer'): void
{
    // Inline scripts are sanitized to avoid accidental injection.
    foreach ($componentAssets['inline_scripts'] ?? [] as $inlineScript) {
        $scriptPosition = $inlineScript['position'] ?? 'footer';
        if ($scriptPosition !== $position) {
            continue;
        }

        $content = $inlineScript['content'] ?? '';
        if ($content === '') {
            continue;
        }

        if (!motion_theme_is_safe_inline_script($content)) {
            error_log('Security: Blocked potentially malicious inline script content');
            continue;
        }

        $typeAttr = '';
        if (!empty($inlineScript['type'])) {
            $typeAttr = ' type="' . htmlspecialchars($inlineScript['type']) . '"';
        }

        echo '<script' . $typeAttr . '>' . "\n";
        echo $content . "\n";
        echo "</script>\n";
    }
}

/**
 * Check inline style content for unsafe tokens.
 */
function motion_theme_is_safe_inline_style(string $content): bool
{
    // Minimal static allowlist to prevent obvious script injection.
    return stripos($content, '</style') === false &&
           stripos($content, '<script') === false &&
           stripos($content, 'javascript:') === false &&
           stripos($content, 'expression(') === false;
}

/**
 * Check inline script content for unsafe tokens.
 */
function motion_theme_is_safe_inline_script(string $content): bool
{
    // Block any attempt to close the script tag.
    return stripos($content, '</script') === false &&
           stripos($content, '<script') === false;
}

/**
 * Parse frontmatter metadata from a markdown file.
 */
function motion_theme_parse_frontmatter(string $filePath): array
{
    $contents = file_get_contents($filePath);
    if ($contents === false || !str_starts_with($contents, '---')) {
        return [];
    }

    $parts = preg_split('/^---$/m', $contents, 3);
    if (!is_array($parts) || count($parts) !== 3) {
        return [];
    }

    return motion_theme_parse_yaml_lite($parts[1]);
}

/**
 * Minimal YAML-lite parser for frontmatter blocks.
 */
function motion_theme_parse_yaml_lite(string $yamlText): array
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

/**
 * Resolve content status from frontmatter.
 */
function motion_theme_resolve_status(array $meta): string
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

/**
 * Convert a site/pages relative file path into a URL slug.
 */
function motion_theme_slug_from_relative(string $relativeFile): string
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

/**
 * Build a list of recent blog posts for the footer.
 *
 * @return array<int, array{label:string,path:string,date:int,status:string}>
 */
function motion_theme_get_recent_blog_posts(int $limit = 5, bool $includePrivate = false): array
{
    $pagesDir = isset(\Flint\Paths::$pagesDir) ? \Flint\Paths::$pagesDir : '';
    if ($pagesDir === '') {
        return [];
    }

    $blogDir = $pagesDir . '/blog';
    if (!is_dir($blogDir)) {
        return [];
    }

    $entries = scandir($blogDir);
    if ($entries === false) {
        return [];
    }

    $posts = [];

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
            continue;
        }

        $fullPath = $blogDir . '/' . $entry;
        if (is_dir($fullPath)) {
            continue;
        }

        if (!preg_match('/\.(md|mdx)$/i', $entry)) {
            continue;
        }

        $baseName = basename($entry, '.' . pathinfo($entry, PATHINFO_EXTENSION));
        if ($baseName === 'index') {
            continue;
        }

        $relativeFile = 'blog/' . $entry;
        $meta = motion_theme_parse_frontmatter($fullPath);
        $status = motion_theme_resolve_status($meta);
        if (!$includePrivate && in_array($status, ['hidden', 'draft'], true)) {
            continue;
        }

        $label = trim((string)($meta['title'] ?? ''));
        if ($label === '') {
            $label = $baseName;
        }

        $dateValue = trim((string)($meta['date'] ?? ''));
        $timestamp = $dateValue === '' ? 0 : (int)(strtotime($dateValue) ?: 0);

        $posts[] = [
            'label' => $label,
            'path' => motion_theme_slug_from_relative($relativeFile),
            'date' => $timestamp,
            'status' => $status,
        ];
    }

    usort($posts, function (array $a, array $b): int {
        if ($a['date'] !== $b['date']) {
            return $b['date'] <=> $a['date'];
        }

        return strcasecmp($a['label'], $b['label']);
    });

    if ($limit < 1) {
        return $posts;
    }

    return array_slice($posts, 0, $limit);
}

// Theme hooks are registered by the CMS; keep helpers focused on presentation.
