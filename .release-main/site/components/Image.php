<?php

namespace Components;

use Flint\RenderComponent;

/**
 * Render an image with optional alignment and caption.
 */
class Image extends RenderComponent
{
    public static function render(array $props, string $content): string
    {
        // Require a source URL before rendering.
        $src = trim((string) self::prop($props, 'src'));
        if ($src === '') {
            return '<!-- Image component: missing src. -->';
        }

        // Normalize optional text metadata.
        $alt = (string) self::prop($props, 'alt', '');
        $title = (string) self::prop($props, 'title', '');

        // Resolve alignment from props with a default of block.
        $alignment = strtolower(trim((string) self::prop($props, 'alignment', '')));
        if ($alignment === '') {
            $alignment = strtolower(trim((string) self::prop($props, 'align', 'block')));
        }

        if (!in_array($alignment, ['left', 'right', 'block'], true)) {
            $alignment = 'block';
        }

        // Build wrapper classes and inline layout styles.
        $figureClass = 'flint-image flint-image--' . $alignment;
        $figureStyle = 'margin: 0 0 1.5rem 0;';

        if ($alignment === 'left') {
            $figureStyle = 'float: left; margin: 0 1.5rem 1rem 0;';
        } elseif ($alignment === 'right') {
            $figureStyle = 'float: right; margin: 0 0 1rem 1.5rem;';
        }

        // Assemble image attributes for the <img> tag.
        $attributes = [
            'src' => $src,
            'alt' => $alt,
            'loading' => 'lazy',
            'decoding' => 'async',
            'style' => 'max-width: 100%; height: auto; display: block;'
        ];

        if ($title !== '') {
            $attributes['title'] = $title;
        }

        // Support captions via inline content or props.
        $captionSource = self::contentOrProp($content, $props, 'caption');
        $captionHtml = '';
        if ($captionSource !== '') {
            if (trim($content) !== '') {
                $captionHtml = $captionSource;
            } else {
                $captionHtml = self::renderCaptionMarkdown($captionSource);
            }
        }

        ob_start();
        ?>
        <figure class="<?= self::escape($figureClass) ?>" style="<?= self::escape($figureStyle) ?>">
            <img <?= self::buildAttributes($attributes) ?>>
            <?php if ($captionHtml !== '') : ?>
                <figcaption style="margin-top: 0.5rem; font-size: 0.875rem; color: #6b7280; line-height: 1.4;">
                    <?= $captionHtml ?>
                </figcaption>
            <?php endif; ?>
        </figure>
        <?php

        // Return the final figure markup.
        return trim((string)ob_get_clean());
    }

    private static function renderCaptionMarkdown(string $caption): string
    {
        // Fallback to escaped HTML when the app is not available.
        $app = self::getApp();
        if ($app === null) {
            return sprintf('<p>%s</p>', self::escape($caption));
        }

        // Parse markdown captions with the CMS parser.
        $parser = new \Flint\Parser($app);
        $parsed = $parser->parse($caption);

        return $parsed['content_html'] ?? '';
    }
}
