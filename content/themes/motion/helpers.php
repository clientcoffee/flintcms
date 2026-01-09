<?php

/**
 * Theme helper functions for the Motion theme.
 */

/**
 * Helper function to convert emoji slugs to emoji characters.
 */
function getEmojiFromSlug(string $slug): string
{
    // Define the supported emoji tokens in one place.
    $emojiMap = [
        'rocket' => '🚀',
        'sparkles' => '✨',
        'fire' => '🔥',
        'book' => '📚',
        'lightning' => '⚡',
        'star' => '⭐',
        'heart' => '❤️',
        'check' => '✅',
        'warning' => '⚠️',
        'info' => 'ℹ️',
        'question' => '❓',
        'lightbulb' => '💡',
        'hammer' => '🔨',
        'wrench' => '🔧',
        'gear' => '⚙️',
        'lock' => '🔒',
        'key' => '🔑',
        'envelope' => '✉️',
        'phone' => '📞',
        'home' => '🏠',
        'globe' => '🌍',
        'eye' => '👁️',
    ];

    // Return the mapped emoji or a safe empty string.
    return $emojiMap[$slug] ?? '';
}
