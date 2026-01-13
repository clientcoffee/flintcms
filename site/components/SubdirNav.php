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
        $context = self::resolveCurrentContext();
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
            self::isAdmin()
        );

        if (empty($columns)) {
            return '';
        }

        // Normalize display props for headings and classes.
        $title = trim((string)self::prop($props, 'title', 'Explore sub-sections'));
        $wrapperClass = trim('motion-subdir-nav ' . (string)self::prop($props, 'class', ''));

        ob_start();
        ?>
        <section class="<?= self::escape($wrapperClass) ?>">
            <?php if ($title !== '') : ?>
                <h2 class="motion-subdir-nav__title"><?= self::escape($title) ?></h2>
            <?php endif; ?>

            <div class="motion-subdir-nav__grid">
                <?php foreach ($columns as $column) : ?>
                    <div class="motion-subdir-nav__column">
                        <?php if ($column['path'] !== '') : ?>
                            <a class="motion-subdir-nav__heading" href="<?= self::escape($column['path']) ?>">
                                <?= self::escape($column['label']) ?>
                            </a>
                        <?php else : ?>
                            <div class="motion-subdir-nav__heading">
                                <?= self::escape($column['label']) ?>
                            </div>
                        <?php endif; ?>

                        <ul class="motion-subdir-nav__list">
                            <?php foreach ($column['children'] as $child) : ?>
                                <li>
                                    <a class="motion-subdir-nav__link" href="<?= self::escape($child['path']) ?>">
                                        <?= self::escape($child['label']) ?>
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
            $indexMeta = $indexFile ? self::extractFrontmatter($indexFile) : [];
            $indexStatus = $indexFile ? self::resolveStatus($indexMeta) : 'published';

            if (!$includePrivate && in_array($indexStatus, ['hidden', 'draft'], true)) {
                continue;
            }

            // Derive a label and link for the section heading.
            $label = trim((string)($indexMeta['title'] ?? ''));
            if ($label === '') {
                $label = $entry;
            }

            $path = '';
            if ($indexFile) {
                $relativeIndex = ltrim($childRelative . '/' . basename($indexFile), '/');
                $path = self::slugFromRelative($relativeIndex);
            }

            // List child pages inside this section.
            $children = self::listChildPages($fullPath, $childRelative, $includePrivate, true);
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
