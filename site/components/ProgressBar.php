<?php

namespace Components;

use Flint\RenderComponent;

class ProgressBar extends RenderComponent
{
    public static function render(array $props, string $content): string
    {
        // Group numeric inputs and label text
        $progressLabel = self::prop($props, 'label');
        $currentValue = (float)self::prop($props, 'value', 0);
        $maximumValue = (float)self::prop($props, 'max', 100);
        $showValueText = self::prop($props, 'show_value', 'true') !== 'false';

        // Normalize invalid max values
        if ($maximumValue <= 0) {
            $maximumValue = 100;
        }

        // Calculate a bounded percentage
        $progressPercent = max(0, min(100, ($currentValue / $maximumValue) * 100));

        // Prepare display strings
        $valueText = $showValueText ? number_format($currentValue, 0) . '/' . number_format($maximumValue, 0) : '';

        // Build the progress bar layout
        $progressHtml = '<div class="motion-progress my-6">';
        $progressHtml .= '<div class="mb-2 flex items-center justify-between text-sm">';
        $progressHtml .= '<span class="font-semibold text-gray-700">' . self::escape($progressLabel) . '</span>';
        if ($showValueText) {
            $progressHtml .= '<span class="text-gray-500">' . self::escape($valueText) . '</span>';
        }
        $progressHtml .= '</div>';
        $progressHtml .= '<div class="h-3 w-full rounded-full bg-gray-200">';
        $progressHtml .= '<div class="h-3 rounded-full bg-gray-900" style="width: ' . number_format($progressPercent, 2) . '%;"></div>';
        $progressHtml .= '</div>';
        $progressHtml .= '</div>';

        // Return the final progress markup
        return $progressHtml;
    }
}
