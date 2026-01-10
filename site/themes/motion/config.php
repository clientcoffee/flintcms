<?php

/**
 * Motion Theme Configuration
 *
 * Modern, clean theme with Tailwind CSS and smooth animations.
 */

return [
    'theme' => [
        'name' => 'Motion',
        'version' => '1.0.0',
        'author' => 'Flint',
        'description' => 'Modern, clean theme with Tailwind CSS and smooth animations',
        'parent' => '', // Parent theme (optional, for theme inheritance)
    ],
    'settings' => [
        // Use local Tailwind CSS (true) or CDN version (false)
        'use_local_tailwind' => true,

        // Enable dark mode support
        'dark_mode_enabled' => false,

        // Custom color scheme (overrides default)
        'primary_color' => '#4F46E5',
        'primary_color_dark' => '#1E3A8A',
        'secondary_color' => '#7C3AED',

        // Typography
        'font_family' => 'system-ui, -apple-system, sans-serif',

        // Layout
        // Relative to /site (example: /uploads/default-banner.jpg)
        'default_banner' => '/uploads/motion-banner.jpg',
        'max_content_width' => '1200px',
        'sidebar_width' => '300px',
    ],
];
