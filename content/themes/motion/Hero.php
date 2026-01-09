<?php

namespace Modules;

use Flint\RenderComponent;

/**
 * Hero Component
 *
 * Renders a large, eye-catching hero section with gradient background,
 * title, subtitle, optional content, and call-to-action button.
 */
class Hero extends RenderComponent
{
    /**
     * Render the hero component
     *
     * @param array  $props   Component properties (title, subtitle, cta, url)
     * @param string $content Optional markdown content to display (pre-sanitized by Parser)
     * @return string Rendered HTML
     */
    public static function render(array $props, string $content): string
    {
        // Use helper methods from RenderComponent
        $title = self::prop($props, 'title');
        $subtitle = self::prop($props, 'subtitle');
        $cta = self::prop($props, 'cta', 'Learn More');
        $ctaUrl = self::prop($props, 'url', '#');

        $html = '<div class="motion-hero bg-gradient-to-br from-indigo-600 to-purple-700 text-white rounded-3xl p-12 my-8 shadow-2xl">';

        if ($title) {
            $html .= '<h1 class="text-5xl font-bold mb-4">' . self::escape($title) . '</h1>';
        }

        if ($subtitle) {
            $html .= '<p class="text-xl text-indigo-100 mb-6">' . self::escape($subtitle) . '</p>';
        }

        if ($content) {
            // Content is pre-sanitized by Parser
            $html .= '<div class="prose prose-lg prose-invert mb-6">' . $content . '</div>';
        }

        if ($cta && $ctaUrl) {
            $html .= '<a href="' . self::escape($ctaUrl) . '" class="inline-flex items-center gap-2 bg-white text-indigo-600 px-6 py-3 rounded-xl font-semibold hover:bg-indigo-50 transition-all shadow-lg hover:scale-105">';
            $html .= self::escape($cta);
            $html .= '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>';
            $html .= '</a>';
        }

        $html .= '</div>';

        return $html;
    }
}
