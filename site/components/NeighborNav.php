<?php

namespace Components;

/**
 * Render previous/next navigation for sibling pages.
 */
class NeighborNav extends ContentNavBase
{
    public static function render(array $props, string $content): string
    {
        // Resolve the current page context to find siblings.
        $context = self::resolveCurrentContext();
        if ($context === null) {
            return '';
        }

        // Gather sibling pages from the current directory.
        $siblings = self::listChildPages(
            $context['dirPath'],
            $context['relativeDir'],
            self::isAdmin(),
            true
        );

        if (count($siblings) < 2) {
            return '';
        }

        // Find the current page within the sibling list.
        $currentIndex = null;
        foreach ($siblings as $index => $item) {
            if ($item['relativeFile'] === $context['relativeFile']) {
                $currentIndex = $index;
                break;
            }
        }

        if ($currentIndex === null) {
            return '';
        }

        // Select the previous and next siblings around the current page.
        $previous = $siblings[$currentIndex - 1] ?? null;
        $next = $siblings[$currentIndex + 1] ?? null;

        if ($previous === null && $next === null) {
            return '';
        }

        // Normalize display props for labels and styling.
        $wrapperClass = trim('motion-neighbor-nav ' . (string)self::prop($props, 'class', ''));
        $previousLabel = (string)self::prop($props, 'previous_label', 'Previous');
        $nextLabel = (string)self::prop($props, 'next_label', 'Next');

        ob_start();
        ?>
        <nav class="<?= self::escape($wrapperClass) ?>" aria-label="Sibling navigation">
            <?php if ($previous) : ?>
                <a class="motion-neighbor-nav__link motion-neighbor-nav__link--prev" href="<?= self::escape($previous['path']) ?>" rel="prev">
                    <span class="motion-neighbor-nav__arrow" aria-hidden="true">&larr;</span>
                    <span class="motion-neighbor-nav__text">
                        <span class="motion-neighbor-nav__kicker"><?= self::escape($previousLabel) ?></span>
                        <span class="motion-neighbor-nav__title"><?= self::escape($previous['label']) ?></span>
                    </span>
                </a>
            <?php endif; ?>

            <?php if ($next) : ?>
                <a class="motion-neighbor-nav__link motion-neighbor-nav__link--next" href="<?= self::escape($next['path']) ?>" rel="next">
                    <span class="motion-neighbor-nav__text">
                        <span class="motion-neighbor-nav__kicker"><?= self::escape($nextLabel) ?></span>
                        <span class="motion-neighbor-nav__title"><?= self::escape($next['label']) ?></span>
                    </span>
                    <span class="motion-neighbor-nav__arrow" aria-hidden="true">&rarr;</span>
                </a>
            <?php endif; ?>
        </nav>
        <?php

        // Return the final nav markup.
        return trim((string)ob_get_clean());
    }
}
