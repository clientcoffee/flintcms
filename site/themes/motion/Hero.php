<?php

namespace Modules;

use Flint\RenderComponent;

/**
 * Hero Component
 *
 * Renders a large, eye-catching hero section with gradient background,
 * title, subtitle, optional content, and call-to-action button.
 */
class Hero extends RenderComponent
{
    /**
     * Render the hero component
     *
     * @param array  $props   Component properties (title, subtitle, cta, url)
     * @param string $content Optional markdown content to display (pre-sanitized by Parser)
     * @return string Rendered HTML
     */
    public static function render(array $props, string $content): string
    {
        // Props come from markdown attributes like {{Hero title="..."}}.
        $title = self::prop($props, 'title');
        // Subtitle is optional and used for supporting context.
        $subtitle = self::prop($props, 'subtitle');
        // CTA text and URL are optional; defaults keep the component usable.
        $cta = self::prop($props, 'cta', 'Learn More');
        $ctaUrl = self::prop($props, 'url', '#');

        // The CMS parser already sanitized $content for safe rendering.
        $hasTitle = $title !== '';
        $hasSubtitle = $subtitle !== '';
        $hasContent = $content !== '';
        $hasCta = $cta !== '' && $ctaUrl !== '';

        // Components return HTML strings, so we buffer a template-like output.
        ob_start();
        ?>
        <div class="motion-hero bg-gradient-to-br from-indigo-600 to-purple-700 text-white rounded-3xl p-12 my-8 shadow-2xl">
            <!-- Title block is optional for flexible authoring. -->
            <?php if ($hasTitle) : ?>
                <h1 class="text-5xl font-bold mb-4"><?= self::escape($title) ?></h1>
            <?php endif; ?>

            <!-- Subtitle block is optional for shorter hero sections. -->
            <?php if ($hasSubtitle) : ?>
                <p class="text-xl text-indigo-100 mb-6"><?= self::escape($subtitle) ?></p>
            <?php endif; ?>

            <!-- $content is already parsed HTML from the CMS markdown parser. -->
            <?php if ($hasContent) : ?>
                <div class="prose prose-lg prose-invert mb-6"><?= $content ?></div>
            <?php endif; ?>

            <!-- CTA block only renders when both text and URL are present. -->
            <?php if ($hasCta) : ?>
                <a href="<?= self::escape($ctaUrl) ?>" class="inline-flex items-center gap-2 bg-white text-indigo-600 px-6 py-3 rounded-xl font-semibold hover:bg-indigo-50 transition-all shadow-lg hover:scale-105">
                    <?= self::escape($cta) ?>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                    </svg>
                </a>
            <?php endif; ?>
        </div>
        <?php

        return trim(ob_get_clean());
    }
}
