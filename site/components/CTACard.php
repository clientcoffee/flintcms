<?php

namespace Components;

use Flint\RenderComponent;

class CTACard extends RenderComponent
{
    public static function render(array $props, string $content): string
    {
        // Group primary call-to-action content
        $cardTitle = self::prop($props, 'title');
        $cardBody = self::contentOrProp($content, $props, 'body');
        $primaryButtonText = self::prop($props, 'button_text', 'Get Started');
        $primaryButtonUrl = self::prop($props, 'button_url', '#');
        $secondaryButtonText = self::prop($props, 'secondary_text');
        $secondaryButtonUrl = self::prop($props, 'secondary_url');

        // Exit early if there is nothing to display
        if ($cardTitle === '' && $cardBody === '') {
            return '';
        }

        // Build the CTA card layout
        $ctaHtml = '<div class="motion-cta my-8 rounded-3xl border border-gray-200 bg-gradient-to-br from-white via-white to-gray-100 p-6 shadow-sm">';
        if ($cardTitle !== '') {
            $ctaHtml .= '<h3 class="text-xl font-semibold text-gray-900">' . self::escape($cardTitle) . '</h3>';
        }
        if ($cardBody !== '') {
            $ctaHtml .= '<p class="mt-2 text-sm text-gray-600">' . self::escape($cardBody) . '</p>';
        }
        $ctaHtml .= '<div class="mt-4 flex flex-wrap gap-3">';
        $ctaHtml .= '<a class="inline-flex items-center rounded-full bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800" href="' . self::escape($primaryButtonUrl) . '">' . self::escape($primaryButtonText) . '</a>';
        if ($secondaryButtonText !== '' && $secondaryButtonUrl !== '') {
            $ctaHtml .= '<a class="inline-flex items-center rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white" href="' . self::escape($secondaryButtonUrl) . '">' . self::escape($secondaryButtonText) . '</a>';
        }
        $ctaHtml .= '</div>';
        $ctaHtml .= '</div>';

        // Return the final CTA card markup
        return $ctaHtml;
    }
}
