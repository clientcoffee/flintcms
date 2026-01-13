<?php

namespace Components;

use Flint\RenderComponent;

/**
 * Render a list of social links from key-value pairs.
 */
class SocialLinks extends RenderComponent
{
    public static function render(array $props, string $content): string
    {
        // Resolve link data from props or inline content.
        $linksRaw = self::contentOrProp($content, $props, 'links');
        $linkItems = self::parseKeyValue($linksRaw, '|');

        // Exit early if no valid links exist.
        if (empty($linkItems)) {
            return '';
        }

        ob_start();
        ?>
        <div class="motion-social-links my-6 flex flex-wrap gap-3">
            <?php foreach ($linkItems as $linkItem) : ?>
                <?php
                $linkLabel = self::escape($linkItem['key']);
                $linkUrl = self::escape($linkItem['value']);
                ?>
                <a class="inline-flex items-center rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50" href="<?= $linkUrl ?>" rel="noopener noreferrer">
                    <?= $linkLabel ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php

        // Return the finished markup.
        return trim((string)ob_get_clean());
    }
}
