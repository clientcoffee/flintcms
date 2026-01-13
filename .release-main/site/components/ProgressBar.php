<?php

namespace Components;

use Flint\RenderComponent;

/**
 * Render a simple progress bar with optional label and value text.
 */
class ProgressBar extends RenderComponent
{
    public static function render(array $props, string $content): string
    {
        // Group numeric inputs and label text.
        $progressLabel = self::prop($props, 'label');
        $currentValue = (float)self::prop($props, 'value', 0);
        $maximumValue = (float)self::prop($props, 'max', 100);
        $showValueText = self::prop($props, 'show_value', 'true') !== 'false';

        // Normalize invalid max values.
        if ($maximumValue <= 0) {
            $maximumValue = 100;
        }

        // Calculate a bounded percentage.
        $progressPercent = max(0, min(100, ($currentValue / $maximumValue) * 100));

        // Prepare display strings.
        $valueText = $showValueText ? number_format($currentValue, 0) . '/' . number_format($maximumValue, 0) : '';

        ob_start();
        ?>
        <div class="motion-progress my-6">
            <div class="mb-2 flex items-center justify-between text-sm">
                <span class="font-semibold text-gray-700"><?= self::escape($progressLabel) ?></span>
                <?php if ($showValueText) : ?>
                    <span class="text-gray-500"><?= self::escape($valueText) ?></span>
                <?php endif; ?>
            </div>
            <div class="h-3 w-full rounded-full bg-gray-200">
                <div class="h-3 rounded-full bg-gray-900" style="width: <?= number_format($progressPercent, 2) ?>%;"></div>
            </div>
        </div>
        <?php

        // Return the final progress markup.
        return trim((string)ob_get_clean());
    }
}
