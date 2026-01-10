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

if (!function_exists('motion_theme_register_hooks')) {
    /**
     * Register Motion theme hooks (styles, scripts).
     */
    function motion_theme_register_hooks(): void
    {
        // Prevent duplicate registration if helpers are included multiple times.
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;

        \Flint\HookManager::on('theme_styles', function (): void {
            // The CMS calls theme_styles() inside layouts; we attach theme.css here.
            echo '<link rel="stylesheet" href="' . theme_asset('theme.css') . '">' . "\n";
        }, 20);
    }
}

// Ensure hooks are registered as soon as the theme helpers load.
motion_theme_register_hooks();
