<?php

namespace Components;

use Flint\RenderComponent;

class Accordion extends RenderComponent
{
    public static function render(array $props, string $content): string
    {
        // Collect and normalize the accordion items
        $rawItemsText = self::contentOrProp($content, $props, 'items');
        $accordionItems = self::parseItems($rawItemsText);

        // Exit early when there is nothing to render
        if (empty($accordionItems)) {
            return '';
        }

        // Group display flags and shared markup
        $openFirstPanel = self::prop($props, 'open') === 'first';
        $accordionHtml = '<div class="motion-accordion my-6 space-y-3">';

        // Render each accordion panel in order
        foreach ($accordionItems as $panelIndex => $panelData) {
            $panelTitle = self::escape($panelData['title']);
            $panelBody = self::escape($panelData['body']);
            $openAttribute = ($openFirstPanel && $panelIndex === 0) ? ' open' : '';

            $accordionHtml .= '<details class="group rounded-2xl border border-gray-200 bg-white/80 shadow-sm"' . $openAttribute . '>';
            $accordionHtml .= '<summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-base font-semibold text-gray-900">';
            $accordionHtml .= '<span>' . $panelTitle . '</span>';
            $accordionHtml .= '<span class="ml-4 text-gray-400 transition group-open:rotate-180">v</span>';
            $accordionHtml .= '</summary>';
            $accordionHtml .= '<div class="px-4 pb-4 text-sm text-gray-600">' . $panelBody . '</div>';
            $accordionHtml .= '</details>';
        }
        $accordionHtml .= '</div>';

        // Return the composed accordion markup
        return $accordionHtml;
    }

    /**
     * Parse accordion items supporting both :: and | delimiters
     */
    private static function parseItems(string $rawItemsText): array
    {
        // Try :: delimiter first, fallback to |
        $items = self::parseKeyValue($rawItemsText, '::');
        if (empty($items)) {
            $items = self::parseKeyValue($rawItemsText, '|');
        }

        // Convert to accordion format
        return array_map(fn($item) => [
            'title' => $item['key'],
            'body' => $item['value']
        ], $items);
    }
}
