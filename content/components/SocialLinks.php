<?php

namespace Components;

use Flint\RenderComponent;

class SocialLinks extends RenderComponent
{
    public static function render(array $props, string $content): string
    {
        // Resolve link data from props or inline content
        $linksRaw = self::contentOrProp($content, $props, 'links');
        $linkItems = self::parseKeyValue($linksRaw, '|');

        // Exit early if no valid links exist
        if (empty($linkItems)) {
            return '';
        }

        // Render the social buttons
        $linksHtml = '<div class="motion-social-links my-6 flex flex-wrap gap-3">';
        foreach ($linkItems as $linkItem) {
            $linkLabel = self::escape($linkItem['key']);
            $linkUrl = self::escape($linkItem['value']);
            $linksHtml .= '<a class="inline-flex items-center rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50" href="' . $linkUrl . '" rel="noopener noreferrer">' . $linkLabel . '</a>';
        }
        $linksHtml .= '</div>';

        // Return the finished markup
        return $linksHtml;
    }
}
