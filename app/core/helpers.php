<?php

use Flint\HookManager;
use Flint\ThemeContext;

if (!function_exists('esc_html')) {
    /**
     * Escape HTML output safely.
     */
    function esc_html(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value) || is_object($value)) {
            return '';
        }

        $text = str_replace("\0", '', (string)$value);

        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false);
    }
}

if (!function_exists('e')) {
    /**
     * Shorthand for esc_html().
     */
    function e(mixed $value): string
    {
        return esc_html($value);
    }
}

if (!function_exists('render_flash_markdown')) {
    /**
     * Render limited markdown for flash messages (links + inline code).
     */
    function render_flash_markdown(string $message): string
    {
        $safe = esc_html($message);
        if ($safe === '') {
            return '';
        }

        $safe = preg_replace(
            '/`([^`]+)`/',
            '<code class="rounded bg-gray-100 px-1 py-0.5 font-mono text-xs">$1</code>',
            $safe
        );

        $safe = preg_replace_callback('/\\[([^\\]]+)\\]\\(([^\\s)]+)\\)/', function ($matches) {
            $url = $matches[2];
            if (!preg_match('/^(https?:\\/\\/|\\/)/i', $url)) {
                return $matches[0];
            }

            return '<a href="' . $url . '" class="text-indigo-600 underline break-all">' . $matches[1] . '</a>';
        }, $safe);

        return $safe;
    }
}

if (!function_exists('hook')) {
    /**
     * Trigger a hook event.
     */
    function hook(string $name, array $context = []): mixed
    {
        return HookManager::trigger($name, $context);
    }
}

if (!function_exists('theme_styles')) {
    /**
     * Trigger the theme styles hook.
     */
    function theme_styles(): mixed
    {
        return hook('theme_styles');
    }
}

if (!function_exists('theme_scripts')) {
    /**
     * Trigger the theme scripts hook.
     */
    function theme_scripts(): mixed
    {
        return hook('theme_scripts');
    }
}

if (!function_exists('render_block')) {
    /**
     * Render a markdown block by name.
     */
    function render_block(string $name, string $fallback = ''): string
    {
        return \Components\Block::render(['name' => $name], $fallback);
    }
}

if (!function_exists('render_assets')) {
    /**
     * Render component assets for the requested position.
     */
    function render_assets(string $position = 'foot', ?array $assets = null): void
    {
        $assets = $assets ?? ThemeContext::get('componentAssets', []);
        $position = strtolower($position);
        if ($position === 'footer') {
            $position = 'foot';
        }

        if ($position === 'head') {
            foreach ($assets['styles'] ?? [] as $style) {
                $href = trim($style['href'] ?? '');
                if ($href === '') {
                    continue;
                }
                echo '<link rel="stylesheet" href="' . esc_html($href) . '">' . "\n";
            }

            $inlineStyles = [];
            foreach ($assets['inline_styles'] ?? [] as $inlineStyle) {
                $content = $inlineStyle['content'] ?? '';
                if ($content === '') {
                    continue;
                }

                $hasDangerousContent = (
                    stripos($content, '</style') !== false ||
                    stripos($content, '<script') !== false ||
                    stripos($content, 'javascript:') !== false ||
                    stripos($content, 'expression(') !== false
                );

                if ($hasDangerousContent) {
                    error_log('Security: Blocked potentially malicious inline style content');
                    continue;
                }

                $inlineStyles[] = $content;
            }

            if (!empty($inlineStyles)) {
                echo "<style>\n";
                foreach ($inlineStyles as $snippet) {
                    echo $snippet . "\n";
                }
                echo "</style>\n";
            }

            foreach ($assets['scripts'] ?? [] as $script) {
                $scriptPosition = strtolower((string)($script['position'] ?? 'footer'));
                if ($scriptPosition === 'footer') {
                    $scriptPosition = 'foot';
                }
                if ($scriptPosition !== 'head') {
                    continue;
                }

                $src = trim($script['src'] ?? '');
                if ($src === '') {
                    continue;
                }

                $typeAttr = '';
                if (!empty($script['type'])) {
                    $typeAttr = ' type="' . esc_html($script['type']) . '"';
                }

                echo '<script src="' . esc_html($src) . '"' . $typeAttr . "></script>\n";
            }

            return;
        }

        foreach ($assets['scripts'] ?? [] as $script) {
            $scriptPosition = strtolower((string)($script['position'] ?? 'footer'));
            if ($scriptPosition === 'footer') {
                $scriptPosition = 'foot';
            }
            if ($scriptPosition !== 'foot') {
                continue;
            }

            $src = trim($script['src'] ?? '');
            if ($src === '') {
                continue;
            }

            $typeAttr = '';
            if (!empty($script['type'])) {
                $typeAttr = ' type="' . esc_html($script['type']) . '"';
            }

            echo '<script src="' . esc_html($src) . '"' . $typeAttr . "></script>\n";
        }

        foreach ($assets['inline_scripts'] ?? [] as $inlineScript) {
            $inlinePosition = strtolower((string)($inlineScript['position'] ?? 'footer'));
            if ($inlinePosition === 'footer') {
                $inlinePosition = 'foot';
            }
            if ($inlinePosition !== 'foot') {
                continue;
            }

            $content = $inlineScript['content'] ?? '';
            if ($content === '') {
                continue;
            }

            $hasScriptInjection = (
                stripos($content, '</script') !== false ||
                stripos($content, '<script') !== false
            );

            if ($hasScriptInjection) {
                error_log('Security: Blocked potentially malicious inline script content');
                continue;
            }

            $typeAttr = '';
            if (!empty($inlineScript['type'])) {
                $typeAttr = ' type="' . esc_html($inlineScript['type']) . '"';
            }

            echo '<script' . $typeAttr . '>' . "\n";
            echo $content . "\n";
            echo "</script>\n";
        }
    }
}

if (!function_exists('theme_asset')) {
    /**
     * Build a theme asset URL for the active theme.
     */
    function theme_asset(string $path, ?string $themeName = null): string
    {
        $path = ltrim($path, '/');
        $themeName = $themeName ?? (ThemeContext::get('site', [])['theme'] ?? 'motion');
        $url = '/themes/' . rawurlencode($themeName) . '/' . $path;

        return esc_html($url);
    }
}

if (!function_exists('page_meta')) {
    /**
     * Read escaped page meta values with a default fallback.
     */
    function page_meta(string $key, mixed $default = ''): string
    {
        $page = ThemeContext::get('page', []);
        $meta = is_array($page) ? ($page['meta'] ?? []) : [];
        $value = $meta[$key] ?? $default;

        return esc_html($value);
    }
}
