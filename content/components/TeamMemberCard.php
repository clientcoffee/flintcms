<?php

namespace Components;

use Flint\RenderComponent;

class TeamMemberCard extends RenderComponent
{
    public static function render(array $props, string $content): string
    {
        // Collect primary identity fields
        $memberName = self::prop($props, 'name');

        // Exit early without a name
        if ($memberName === '') {
            return '';
        }

        // Group profile metadata and content
        $memberRole = self::prop($props, 'role');
        $memberBio = self::contentOrProp($content, $props, 'bio');
        $memberPhotoUrl = self::prop($props, 'photo');
        $memberLocation = self::prop($props, 'location');
        $memberEmail = self::prop($props, 'email');
        $memberLinksRaw = self::prop($props, 'links');

        // Create a fallback initial for when no photo is provided
        $memberInitial = strtoupper(substr($memberName, 0, 1));

        // Build the card shell and avatar area
        $cardHtml = '<div class="motion-team-card my-6 rounded-2xl border border-gray-200 bg-white/80 p-5 shadow-sm">';
        $cardHtml .= '<div class="flex items-start gap-4">';
        if ($memberPhotoUrl !== '') {
            $cardHtml .= '<img class="h-16 w-16 rounded-2xl object-cover border border-gray-200" src="' . self::escape($memberPhotoUrl) . '" alt="' . self::escape($memberName) . '">';
        } else {
            $cardHtml .= '<div class="h-16 w-16 rounded-2xl bg-gray-100 border border-gray-200 flex items-center justify-center text-xl font-semibold text-gray-500">' . self::escape($memberInitial) . '</div>';
        }
        $cardHtml .= '<div class="flex-1">';
        $cardHtml .= '<div class="flex flex-wrap items-center gap-2">';
        $cardHtml .= '<h3 class="text-lg font-semibold text-gray-900">' . self::escape($memberName) . '</h3>';
        if ($memberRole !== '') {
            $cardHtml .= '<span class="text-sm text-gray-500">· ' . self::escape($memberRole) . '</span>';
        }
        $cardHtml .= '</div>';

        // Render optional metadata blocks
        if ($memberLocation !== '') {
            $cardHtml .= '<div class="text-sm text-gray-500 mt-1">' . self::escape($memberLocation) . '</div>';
        }
        if ($memberBio !== '') {
            $cardHtml .= '<p class="mt-3 text-sm text-gray-600">' . self::escape($memberBio) . '</p>';
        }
        if ($memberEmail !== '') {
            $cardHtml .= '<a class="mt-3 inline-block text-sm text-gray-700 underline" href="mailto:' . self::escape($memberEmail) . '">' . self::escape($memberEmail) . '</a>';
        }
        if ($memberLinksRaw !== '') {
            $cardHtml .= self::renderLinks($memberLinksRaw);
        }
        $cardHtml .= '</div>';
        $cardHtml .= '</div>';
        $cardHtml .= '</div>';

        // Return the assembled team member card
        return $cardHtml;
    }

    private static function renderLinks(string $linksRaw): string
    {
        // Parse link tokens into structured data using parent's helper
        $linkItems = self::parseKeyValue($linksRaw, '|');

        // Exit early if there are no valid links
        if (empty($linkItems)) {
            return '';
        }

        // Render the links in a simple inline list
        $linksHtml = '<div class="mt-3 flex flex-wrap gap-3">';
        foreach ($linkItems as $linkItem) {
            $linkLabel = self::escape($linkItem['key']);
            $linkUrl = self::escape($linkItem['value']);
            $linksHtml .= '<a class="text-sm text-gray-700 underline hover:text-gray-900" href="' . $linkUrl . '" rel="noopener noreferrer">' . $linkLabel . '</a>';
        }
        $linksHtml .= '</div>';

        // Return the rendered link markup
        return $linksHtml;
    }
}
