<?php

namespace Components;

/**
 * List the immediate child pages for a section index.
 */
class ChildrenNav extends ContentNavBase
{
    public static function render(array $props, string $content): string
    {
        // Resolve the current page context and ensure this is an index page.
        $context = resolve_current_context();
        if ($context === null || !$context['isIndex']) {
            return '';
        }

        // Skip root index pages to avoid redundant lists.
        if ($context['relativeDir'] === '') {
            return '';
        }

        // Collect child pages within the current directory.
        $children = list_child_pages(
            $context['dirPath'],
            $context['relativeDir'],
            is_admin(),
            true
        );

        if (empty($children)) {
            return '';
        }

        // Normalize display props.
        $title = trim((string)prop($props, 'title', 'More in this section'));
        $wrapperClass = trim('motion-children-nav ' . (string)prop($props, 'class', ''));

        ob_start();
        ?>
        <section class="<?= esc_html($wrapperClass) ?>">
            <?php if ($title !== '') : ?>
                <h2 class="motion-children-nav__title"><?= esc_html($title) ?></h2>
            <?php endif; ?>

            <ul class="motion-children-nav__list">
                <?php foreach ($children as $child) : ?>
                    <li>
                        <a class="motion-children-nav__link" href="<?= esc_html($child['path']) ?>">
                            <?= esc_html($child['label']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php

        // Return the rendered list markup.
        return trim((string)ob_get_clean());
    }
}
