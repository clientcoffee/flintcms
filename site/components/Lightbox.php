<?php

namespace Components;

use Flint\RenderComponent;

/**
 * Render an image thumbnail that opens a dialog lightbox.
 */
class Lightbox extends RenderComponent
{
    public static function render(array $props, string $content): string
    {
        // Gather primary inputs for the lightbox.
        $sourceUrl = self::prop($props, 'src');

        // Exit early when there is no image source.
        if ($sourceUrl === '') {
            return '';
        }

        // Create a stable dialog ID for this instance.
        static $instanceCounter = 0;
        $instanceCounter++;
        $dialogId = 'lightbox-' . $instanceCounter;

        // Group descriptive metadata and fallback values.
        $altText = self::prop($props, 'alt');
        $captionText = self::prop($props, 'caption', $altText);
        $thumbnailUrl = self::prop($props, 'thumb', $sourceUrl);

        ob_start();
        ?>
        <figure class="motion-lightbox-figure my-6">
            <button type="button" class="block w-full" onclick="document.getElementById('<?= self::escape($dialogId) ?>').showModal()">
                <img class="w-full rounded-2xl border border-gray-200 shadow-sm" src="<?= self::escape($thumbnailUrl) ?>" alt="<?= self::escape($altText) ?>">
            </button>
            <?php if ($captionText !== '') : ?>
                <figcaption class="mt-2 text-sm text-gray-500"><?= self::escape($captionText) ?></figcaption>
            <?php endif; ?>
        </figure>

        <dialog id="<?= self::escape($dialogId) ?>" class="motion-lightbox">
            <div class="bg-white rounded-2xl p-4 shadow-2xl max-w-4xl w-[calc(100vw-2rem)]">
                <div class="flex items-center justify-between mb-3">
                    <div class="text-sm font-semibold text-gray-700"><?= self::escape($captionText) ?></div>
                    <button type="button" class="text-gray-500 hover:text-gray-800" onclick="this.closest('dialog').close()">Close</button>
                </div>
                <img class="w-full rounded-xl border border-gray-200" src="<?= self::escape($sourceUrl) ?>" alt="<?= self::escape($altText) ?>">
            </div>
        </dialog>
        <?php

        // Return the combined thumbnail and dialog markup.
        return trim((string)ob_get_clean());
    }
}
