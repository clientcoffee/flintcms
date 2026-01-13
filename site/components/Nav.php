<?php

namespace Components;

use Flint\RenderComponent;

/**
 * Render navigation menus from markdown lists or presets.
 */
class Nav extends RenderComponent
{
    public static function render(array $props, string $content): string
    {
        // Normalize configuration inputs from props and inline content.
        $navigationMode = strtolower(self::prop($props, 'mode', 'auto'));
        $autoPreset = strtolower(self::prop($props, 'auto', 'default'));
        $customHtml = self::prop($props, 'html');
        $itemsMarkdown = self::contentOrProp($content, $props, 'items');

        // Render raw HTML if explicitly requested.
        if ($navigationMode === 'html' && $customHtml !== '') {
            return $customHtml;
        }

        // Resolve auto nav items when no list is supplied.
        if ($navigationMode === 'auto' && $itemsMarkdown === '') {
            $itemsMarkdown = self::getAutoList($autoPreset);
        }

        // Parse the markdown list into a nested structure.
        $navigationItems = self::parseMarkdownList($itemsMarkdown);

        // Exit early when nothing is parsed.
        if (empty($navigationItems)) {
            return '';
        }

        // Render the list as the final nav markup.
        return self::renderItems($navigationItems);
    }

    private static function getAutoList(string $auto): string
    {
        // Return a minimal preset with just the essentials.
        if ($auto === 'minimal') {
            return "- [About](/about)\n- [Contact](/contact)";
        }

        // Return the default preset with a dropdown section.
        return "- [About](/about)\n- Showcase\n  - [Markdown Showcase](/markdown-showcase)\n  - [Hidden Example](/hidden-example)\n- [Contact](/contact)";
    }

    private static function parseMarkdownList(string $text): array
    {
        // Bail early when there is no list input.
        if ($text === '') {
            return [];
        }

        // Split lines while preserving indentation levels.
        $listLines = preg_split('/\r?\n/', $text);

        // Initialize the root collection and stack.
        $rootItems = [];
        $stackFrames = [
            ['indent' => -1, 'items' => &$rootItems],
        ];
        $stackDepth = 1;

        // Walk each line and build the nested structure.
        foreach ($listLines as $lineText) {
            if (trim($lineText) === '') {
                continue;
            }

            // Only process bullet list syntax.
            if (!preg_match('/^(\s*)([-*])\s+(.*)$/', $lineText, $lineMatch)) {
                continue;
            }

            // Extract indentation and line body.
            $lineIndentation = strlen($lineMatch[1]);
            $lineBody = trim($lineMatch[3]);

            // Initialize label and URL fields.
            $itemLabel = '';
            $itemUrl = '';

            // Support markdown links or plain label with URL in parentheses.
            if (preg_match('/^\[(.+)\]\((.+)\)$/', $lineBody, $linkMatch)) {
                $itemLabel = trim($linkMatch[1]);
                $itemUrl = trim($linkMatch[2]);
            } elseif (preg_match('/^(.+)\(([^)]+)\)$/', $lineBody, $plainMatch)) {
                $itemLabel = trim($plainMatch[1]);
                $itemUrl = trim($plainMatch[2]);
            } else {
                $itemLabel = $lineBody;
            }

            // Create the nav item payload.
            $navItem = [
                'label' => $itemLabel,
                'url' => $itemUrl,
                'children' => [],
            ];

            // Roll the stack back to the correct parent based on indentation.
            while ($stackDepth > 1 && $lineIndentation <= $stackFrames[$stackDepth - 1]['indent']) {
                array_pop($stackFrames);
                $stackDepth--;
            }

            // Append the item to its parent.
            $parentItems = &$stackFrames[$stackDepth - 1]['items'];
            $parentItems[] = $navItem;
            $lastIndex = null;

            // Locate the last appended item without using count().
            foreach ($parentItems as $itemIndex => $unusedValue) {
                $lastIndex = $itemIndex;
            }

            // Push a new frame for potential nested children.
            $stackFrames[] = [
                'indent' => $lineIndentation,
                'items' => &$parentItems[$lastIndex]['children'],
            ];
            $stackDepth++;
        }

        // Return the fully parsed nav tree.
        return $rootItems;
    }

    private static function renderItems(array $items): string
    {
        ob_start();
        ?>
        <ul class="flex items-center gap-1">
            <?php foreach ($items as $itemData) : ?>
                <?= self::renderItem($itemData) ?>
            <?php endforeach; ?>
        </ul>
        <?php

        return trim((string)ob_get_clean());
    }

    private static function renderItem(array $item): string
    {
        // Normalize the incoming item payload.
        $itemLabel = self::escape($item['label']);
        $itemUrl = trim($item['url'] ?? '');
        $childItems = $item['children'] ?? [];

        // Render dropdown items for parents with children.
        if (!empty($childItems)) {
            $triggerClasses = 'px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-md nav-link flex items-center gap-1';

            ob_start();
            ?>
            <li class="relative group">
                <?php if ($itemUrl !== '') : ?>
                    <a href="<?= self::escape($itemUrl) ?>" class="<?= self::escape($triggerClasses) ?>" aria-haspopup="menu">
                        <span><?= $itemLabel ?></span>
                        <span class="text-xs text-gray-400" aria-hidden="true">v</span>
                    </a>
                <?php else : ?>
                    <button type="button" class="<?= self::escape($triggerClasses) ?>" aria-haspopup="menu">
                        <span><?= $itemLabel ?></span>
                        <span class="text-xs text-gray-400" aria-hidden="true">v</span>
                    </button>
                <?php endif; ?>
                <div class="absolute left-0 top-full hidden min-w-[200px] pt-2 group-hover:block group-focus-within:block">
                    <div class="rounded-xl border border-gray-200 bg-white/95 shadow-lg backdrop-blur-sm">
                        <ul class="py-2" role="menu">
                            <?php foreach ($childItems as $childItem) : ?>
                                <?= self::renderChild($childItem) ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </li>
            <?php

            return trim((string)ob_get_clean());
        }

        // Render a simple leaf item.
        $href = $itemUrl !== '' ? self::escape($itemUrl) : '#';
        ob_start();
        ?>
        <li>
            <a href="<?= $href ?>" class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-md nav-link">
                <?= $itemLabel ?>
            </a>
        </li>
        <?php

        return trim((string)ob_get_clean());
    }

    private static function renderChild(array $item): string
    {
        // Normalize child item data.
        $childLabel = self::escape($item['label']);
        $childUrl = trim($item['url'] ?? '');
        $childItems = $item['children'] ?? [];
        $childHref = $childUrl !== '' ? self::escape($childUrl) : '#';

        // Render the child item and any nested children.
        ob_start();
        ?>
        <li class="px-2">
            <a href="<?= $childHref ?>" class="block rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-100">
                <?= $childLabel ?>
            </a>
            <?php if (!empty($childItems)) : ?>
                <ul class="mt-1 space-y-1 pl-2">
                    <?php foreach ($childItems as $nestedChild) : ?>
                        <?= self::renderChild($nestedChild) ?>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </li>
        <?php

        return trim((string)ob_get_clean());
    }
}
