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
        $context = resolve_current_context();
        if ($context === null) {
            return '';
        }

        // Gather sibling pages from the current directory.
        $siblings = list_child_pages(
            $context['dirPath'],
            $context['relativeDir'],
            is_admin(),
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
        $wrapperClass = trim('motion-neighbor-nav ' . (string)prop($props, 'class', ''));
        $previousLabel = (string)prop($props, 'previous_label', 'Previous');
        $nextLabel = (string)prop($props, 'next_label', 'Next');

        ob_start();
        ?>
        <nav class="<?= esc_html($wrapperClass) ?>" aria-label="Sibling navigation">
            <?php if ($previous) : ?>
                <a class="motion-neighbor-nav__link motion-neighbor-nav__link--prev" href="<?= esc_html($previous['path']) ?>" rel="prev">
                    <span class="motion-neighbor-nav__arrow" aria-hidden="true">&larr;</span>
                    <span class="motion-neighbor-nav__text">
                        <span class="motion-neighbor-nav__kicker"><?= esc_html($previousLabel) ?></span>
                        <span class="motion-neighbor-nav__title"><?= esc_html($previous['label']) ?></span>
                    </span>
                </a>
            <?php endif; ?>

            <?php if ($next) : ?>
                <a class="motion-neighbor-nav__link motion-neighbor-nav__link--next" href="<?= esc_html($next['path']) ?>" rel="next">
                    <span class="motion-neighbor-nav__text">
                        <span class="motion-neighbor-nav__kicker"><?= esc_html($nextLabel) ?></span>
                        <span class="motion-neighbor-nav__title"><?= esc_html($next['label']) ?></span>
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
