<?php

namespace Components;

/**
 * Render columns of subdirectories and their immediate children.
 */
class SubdirNav extends ContentNavBase
{
    public static function render(array $props, string $content): string
    {
        // Resolve the current page context and ensure this is an index page.
        $context = resolve_current_context();
        if ($context === null || !$context['isIndex']) {
            return '';
        }

        // Skip root index pages to avoid noise.
        if ($context['relativeDir'] === '') {
            return '';
        }

        // Collect subdirectories and their child pages.
        $columns = self::listSubdirectories(
            $context['dirPath'],
            $context['relativeDir'],
            is_admin()
        );

        if (empty($columns)) {
            return '';
        }

        // Normalize display props for headings and classes.
        $title = trim((string)prop($props, 'title', 'Explore sub-sections'));
        $wrapperClass = trim('motion-subdir-nav ' . (string)prop($props, 'class', ''));

        ob_start();
        ?>
        <section class="<?= esc_html($wrapperClass) ?>">
            <?php if ($title !== '') : ?>
                <h2 class="motion-subdir-nav__title"><?= esc_html($title) ?></h2>
            <?php endif; ?>

            <div class="motion-subdir-nav__grid">
                <?php foreach ($columns as $column) : ?>
                    <div class="motion-subdir-nav__column">
                        <?php if ($column['path'] !== '') : ?>
                            <a class="motion-subdir-nav__heading" href="<?= esc_html($column['path']) ?>">
                                <?= esc_html($column['label']) ?>
                            </a>
                        <?php else : ?>
                            <div class="motion-subdir-nav__heading">
                                <?= esc_html($column['label']) ?>
                            </div>
                        <?php endif; ?>

                        <ul class="motion-subdir-nav__list">
                            <?php foreach ($column['children'] as $child) : ?>
                                <li>
                                    <a class="motion-subdir-nav__link" href="<?= esc_html($child['path']) ?>">
                                        <?= esc_html($child['label']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php

        // Return the rendered subdirectory grid.
        return trim((string)ob_get_clean());
    }

    private static function listSubdirectories(string $directory, string $relativeDir, bool $includePrivate): array
    {
        // Enumerate directories and build columns.
        if (!is_dir($directory)) {
            return [];
        }

        $entries = scandir($directory);
        if ($entries === false) {
            return [];
        }

        $columns = [];

        foreach ($entries as $entry) {
            // Skip dot entries and hidden folders.
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }

            $fullPath = $directory . '/' . $entry;
            if (!is_dir($fullPath)) {
                continue;
            }

            // Resolve the index page and status for this section.
            $childRelative = ltrim($relativeDir . '/' . $entry, '/');
            $indexFile = self::findIndexFile($fullPath);
            $indexMeta = $indexFile ? extract_frontmatter($indexFile) : [];
            $indexStatus = $indexFile ? resolve_status($indexMeta) : 'published';

            if (!$includePrivate && in_array($indexStatus, ['hidden', 'draft'], true)) {
                continue;
            }

            // Derive a label and link for the section heading.
            $label = trim((string)($indexMeta['title'] ?? ''));
            if ($label !== '') {
                $label = strip_inline_markdown($label);
            }
            if ($label === '') {
                $label = $entry;
            }

            $path = '';
            if ($indexFile) {
                $relativeIndex = ltrim($childRelative . '/' . basename($indexFile), '/');
                $path = slug_from_path($relativeIndex);
            }

            // List child pages inside this section.
            $children = list_child_pages($fullPath, $childRelative, $includePrivate, true);
            if (empty($children)) {
                continue;
            }

            $columns[] = [
                'label' => $label,
                'path' => $path,
                'children' => $children,
            ];
        }

        // Sort columns alphabetically by label.
        usort($columns, fn($a, $b) => strcasecmp($a['label'], $b['label']));

        return $columns;
    }
}
