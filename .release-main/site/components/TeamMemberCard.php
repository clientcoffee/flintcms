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
        $memberName = prop($props, 'name');

        // Exit early without a name.
        if ($memberName === '') {
            return '';
        }

        // Group profile metadata and content.
        $memberRole = prop($props, 'role');
        $memberBio = content_or_prop($content, $props, 'bio');
        $memberPhotoUrl = prop($props, 'photo');
        $memberLocation = prop($props, 'location');
        $memberEmail = prop($props, 'email');
        $memberLinksRaw = prop($props, 'links');

        // Create a fallback initial for when no photo is provided.
        $memberInitial = strtoupper(substr($memberName, 0, 1));

        ob_start();
        ?>
        <div class="motion-team-card my-6 rounded-2xl border border-gray-200 bg-white/80 p-5 shadow-sm">
            <div class="flex items-start gap-4">
                <?php if ($memberPhotoUrl !== '') : ?>
                    <img class="h-16 w-16 rounded-2xl object-cover border border-gray-200" src="<?= esc_html($memberPhotoUrl) ?>" alt="<?= esc_html($memberName) ?>">
                <?php else : ?>
                    <div class="h-16 w-16 rounded-2xl bg-gray-100 border border-gray-200 flex items-center justify-center text-xl font-semibold text-gray-500">
                        <?= esc_html($memberInitial) ?>
                    </div>
                <?php endif; ?>
                <div class="flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-lg font-semibold text-gray-900"><?= esc_html($memberName) ?></h3>
                        <?php if ($memberRole !== '') : ?>
                            <span class="text-sm text-gray-500">· <?= esc_html($memberRole) ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($memberLocation !== '') : ?>
                        <div class="text-sm text-gray-500 mt-1"><?= esc_html($memberLocation) ?></div>
                    <?php endif; ?>
                    <?php if ($memberBio !== '') : ?>
                        <p class="mt-3 text-sm text-gray-600"><?= esc_html($memberBio) ?></p>
                    <?php endif; ?>
                    <?php if ($memberEmail !== '') : ?>
                        <a class="mt-3 inline-block text-sm text-gray-700 underline" href="mailto:<?= esc_html($memberEmail) ?>">
                            <?= esc_html($memberEmail) ?>
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
        $linkItems = parse_key_value($linksRaw, '|');

        // Exit early if there are no valid links.
        if (empty($linkItems)) {
            return '';
        }

        ob_start();
        ?>
        <div class="mt-3 flex flex-wrap gap-3">
            <?php foreach ($linkItems as $linkItem) : ?>
                <?php
                $linkLabel = esc_html($linkItem['key']);
                $linkUrl = esc_html($linkItem['value']);
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
