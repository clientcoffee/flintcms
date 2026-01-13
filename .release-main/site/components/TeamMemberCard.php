<?php

namespace Components;

use Flint\RenderComponent;

/**
 * Render a team member card with optional bio and links.
 */
class TeamMemberCard extends RenderComponent
{
    public static function render(array $props, string $content): string
    {
        // Collect primary identity fields.
        $memberName = self::prop($props, 'name');

        // Exit early without a name.
        if ($memberName === '') {
            return '';
        }

        // Group profile metadata and content.
        $memberRole = self::prop($props, 'role');
        $memberBio = self::contentOrProp($content, $props, 'bio');
        $memberPhotoUrl = self::prop($props, 'photo');
        $memberLocation = self::prop($props, 'location');
        $memberEmail = self::prop($props, 'email');
        $memberLinksRaw = self::prop($props, 'links');

        // Create a fallback initial for when no photo is provided.
        $memberInitial = strtoupper(substr($memberName, 0, 1));

        ob_start();
        ?>
        <div class="motion-team-card my-6 rounded-2xl border border-gray-200 bg-white/80 p-5 shadow-sm">
            <div class="flex items-start gap-4">
                <?php if ($memberPhotoUrl !== '') : ?>
                    <img class="h-16 w-16 rounded-2xl object-cover border border-gray-200" src="<?= self::escape($memberPhotoUrl) ?>" alt="<?= self::escape($memberName) ?>">
                <?php else : ?>
                    <div class="h-16 w-16 rounded-2xl bg-gray-100 border border-gray-200 flex items-center justify-center text-xl font-semibold text-gray-500">
                        <?= self::escape($memberInitial) ?>
                    </div>
                <?php endif; ?>
                <div class="flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-lg font-semibold text-gray-900"><?= self::escape($memberName) ?></h3>
                        <?php if ($memberRole !== '') : ?>
                            <span class="text-sm text-gray-500">· <?= self::escape($memberRole) ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($memberLocation !== '') : ?>
                        <div class="text-sm text-gray-500 mt-1"><?= self::escape($memberLocation) ?></div>
                    <?php endif; ?>
                    <?php if ($memberBio !== '') : ?>
                        <p class="mt-3 text-sm text-gray-600"><?= self::escape($memberBio) ?></p>
                    <?php endif; ?>
                    <?php if ($memberEmail !== '') : ?>
                        <a class="mt-3 inline-block text-sm text-gray-700 underline" href="mailto:<?= self::escape($memberEmail) ?>">
                            <?= self::escape($memberEmail) ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($memberLinksRaw !== '') : ?>
                        <?= self::renderLinks($memberLinksRaw) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php

        // Return the assembled team member card.
        return trim((string)ob_get_clean());
    }

    private static function renderLinks(string $linksRaw): string
    {
        // Parse link tokens into structured data using parent's helper.
        $linkItems = self::parseKeyValue($linksRaw, '|');

        // Exit early if there are no valid links.
        if (empty($linkItems)) {
            return '';
        }

        ob_start();
        ?>
        <div class="mt-3 flex flex-wrap gap-3">
            <?php foreach ($linkItems as $linkItem) : ?>
                <?php
                $linkLabel = self::escape($linkItem['key']);
                $linkUrl = self::escape($linkItem['value']);
                ?>
                <a class="text-sm text-gray-700 underline hover:text-gray-900" href="<?= $linkUrl ?>" rel="noopener noreferrer">
                    <?= $linkLabel ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php

        // Return the rendered link markup.
        return trim((string)ob_get_clean());
    }
}
