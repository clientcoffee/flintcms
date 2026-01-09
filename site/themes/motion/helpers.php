<?php

/**
 * Theme helper functions for the Motion theme.
 */

/**
 * Helper function to convert emoji slugs to emoji characters.
 */
function getEmojiFromSlug(string $slug): string
{
    // Define the supported emoji tokens in one place.
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

    // Return the mapped emoji or a safe empty string.
    return $emojiMap[$slug] ?? '';
}

/**
 * Render external stylesheet links from component assets.
 */
function motion_theme_render_component_styles(array $componentAssets): void
{
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
    return stripos($content, '</script') === false &&
           stripos($content, '<script') === false;
}
