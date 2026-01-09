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
        // Resolve the callout type from props using helper method
        $calloutType = self::prop($props, 'type', 'info');

        // Define the Motion theme style map.
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

        // Return single-line HTML to avoid markdown splitting the component.
        return sprintf(
            '<div class="my-4 p-4 rounded-md border-l-4 %s %s %s flex gap-3 items-start"><span class="text-xl flex-shrink-0 mt-0.5">%s</span><div class="flex-1 text-sm leading-relaxed">%s</div></div>',
            $styleConfig['bg'],
            $styleConfig['border'],
            $styleConfig['text'],
            $iconGlyph,
            htmlspecialchars($content)
        );
    }
}
