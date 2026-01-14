<?php

namespace Components;

use Flint\RenderComponent;

/**
 * Render a simple accordion from key-value pairs.
 */
class Accordion extends RenderComponent
{
    public static function render(array $props, string $content): string
    {
        // Collect and normalize the accordion items.
        $rawItemsText = content_or_prop($content, $props, 'items');
        $accordionItems = self::parseItems($rawItemsText);

        // Exit early when there is nothing to render.
        if (empty($accordionItems)) {
            return '';
        }

        // Compute display flags used by the markup block.
        $openFirstPanel = prop($props, 'open') === 'first';

        ob_start();
        ?>
        <div class="motion-accordion my-6 space-y-3">
            <?php foreach ($accordionItems as $panelIndex => $panelData) : ?>
                <?php
                $panelTitle = esc_html($panelData['title']);
                $panelBody = esc_html($panelData['body']);
                $openAttribute = ($openFirstPanel && $panelIndex === 0) ? ' open' : '';
                ?>
                <details class="group rounded-2xl border border-gray-200 bg-white/80 shadow-sm"<?= $openAttribute ?>>
                    <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-base font-semibold text-gray-900">
                        <span><?= $panelTitle ?></span>
                        <span class="ml-4 text-gray-400 transition group-open:rotate-180">v</span>
                    </summary>
                    <div class="px-4 pb-4 text-sm text-gray-600"><?= $panelBody ?></div>
                </details>
            <?php endforeach; ?>
        </div>
        <?php

        // Return the composed accordion markup.
        return trim((string)ob_get_clean());
    }

    /**
     * Parse accordion items supporting both :: and | delimiters.
     */
    private static function parseItems(string $rawItemsText): array
    {
        // Try :: delimiter first, fallback to |.
        $items = parse_key_value($rawItemsText, '::');
        if (empty($items)) {
            $items = parse_key_value($rawItemsText, '|');
        }

        // Convert to the accordion-friendly shape.
        return array_map(fn($item) => [
            'title' => $item['key'],
            'body' => $item['value']
        ], $items);
    }
}
