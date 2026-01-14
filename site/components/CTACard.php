<?php

namespace Components;

use Flint\RenderComponent;

/**
 * Render a call-to-action card with optional secondary link.
 */
class CTACard extends RenderComponent
{
    public static function render(array $props, string $content): string
    {
        // Group primary call-to-action content.
        $cardTitle = prop($props, 'title');
        $cardBody = content_or_prop($content, $props, 'body');
        $primaryButtonText = prop($props, 'button_text', 'Get Started');
        $primaryButtonUrl = prop($props, 'button_url', '#');
        $secondaryButtonText = prop($props, 'secondary_text');
        $secondaryButtonUrl = prop($props, 'secondary_url');

        // Exit early if there is nothing to display.
        if ($cardTitle === '' && $cardBody === '') {
            return '';
        }

        ob_start();
        ?>
        <div class="motion-cta my-8 rounded-3xl border border-gray-200 bg-gradient-to-br from-white via-white to-gray-100 p-6 shadow-sm">
            <?php if ($cardTitle !== '') : ?>
                <h3 class="text-xl font-semibold text-gray-900"><?= esc_html($cardTitle) ?></h3>
            <?php endif; ?>
            <?php if ($cardBody !== '') : ?>
                <p class="mt-2 text-sm text-gray-600"><?= esc_html($cardBody) ?></p>
            <?php endif; ?>
            <div class="mt-4 flex flex-wrap gap-3">
                <a class="inline-flex items-center rounded-full bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800" href="<?= esc_html($primaryButtonUrl) ?>">
                    <?= esc_html($primaryButtonText) ?>
                </a>
                <?php if ($secondaryButtonText !== '' && $secondaryButtonUrl !== '') : ?>
                    <a class="inline-flex items-center rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white" href="<?= esc_html($secondaryButtonUrl) ?>">
                        <?= esc_html($secondaryButtonText) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php

        // Return the final CTA card markup.
        return trim((string)ob_get_clean());
    }
}
