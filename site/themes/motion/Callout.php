<?php

namespace Modules;

use Flint\RenderComponent;

/**
 * Callout Component
 *
 * Displays highlighted callout boxes with emoji icons and custom styling.
 * Useful for tips, warnings, important notes, and informational messages.
 */
class Callout extends RenderComponent
{
    /**
     * Render a callout box
     *
     * @param array  $props   Component properties (type: info|alert|success|warning)
     * @param string $content Callout message content (pre-sanitized by Parser)
     * @return string Rendered HTML
     */
    public static function render(array $props, string $content): string
    {
        // Props are passed from markdown like {{Callout type="info"}}.
        $calloutType = self::prop($props, 'type', 'info');

        // Theme-specific visual mapping for each callout type.
        $styleMap = [
            'info' => [
                'bg' => 'bg-blue-50',
                'border' => 'border-l-blue-500',
                'icon' => '💡',
                'text' => 'text-blue-900'
            ],
            'alert' => [
                'bg' => 'bg-red-50',
                'border' => 'border-l-red-500',
                'icon' => '⚠️',
                'text' => 'text-red-900'
            ],
            'success' => [
                'bg' => 'bg-green-50',
                'border' => 'border-l-green-500',
                'icon' => '✅',
                'text' => 'text-green-900'
            ],
            'warning' => [
                'bg' => 'bg-yellow-50',
                'border' => 'border-l-yellow-500',
                'icon' => '⚡',
                'text' => 'text-yellow-900'
            ],
        ];

        // Select the requested style or fall back to info.
        $styleConfig = $styleMap[$calloutType] ?? $styleMap['info'];
        $iconGlyph = $styleConfig['icon'];

        // Callout content should be treated as plain text here.
        $safeContent = htmlspecialchars($content);

        // Components return HTML strings, so we buffer markup output.
        ob_start();
        ?>
        <div class="my-4 p-4 rounded-md border-l-4 <?= esc_html($styleConfig['bg']) ?> <?= esc_html($styleConfig['border']) ?> <?= esc_html($styleConfig['text']) ?> flex gap-3 items-start">
            <!-- Emoji gives a quick visual cue for the callout type. -->
            <span class="text-xl flex-shrink-0 mt-0.5"><?= esc_html($iconGlyph) ?></span>
            <!-- Content is plain text to keep callouts simple and safe. -->
            <div class="flex-1 text-sm leading-relaxed"><?= $safeContent ?></div>
        </div>
        <?php

        return trim(ob_get_clean());
    }
}
