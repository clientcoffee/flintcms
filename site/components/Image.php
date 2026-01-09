<?php

namespace Components;

use Flint\RenderComponent;

class Image extends RenderComponent
{
    public static function render(array $props, string $content): string
    {
        $src = trim((string) self::prop($props, 'src'));
        if ($src === '') {
            return '<!-- Image component: missing src -->';
        }

        $alt = (string) self::prop($props, 'alt', '');
        $title = (string) self::prop($props, 'title', '');

        $alignment = strtolower(trim((string) self::prop($props, 'alignment', '')));
        if ($alignment === '') {
            $alignment = strtolower(trim((string) self::prop($props, 'align', 'block')));
        }

        if (!in_array($alignment, ['left', 'right', 'block'], true)) {
            $alignment = 'block';
        }

        $figureClass = 'flint-image flint-image--' . $alignment;
        $figureStyle = 'margin: 0 0 1.5rem 0;';

        if ($alignment === 'left') {
            $figureStyle = 'float: left; margin: 0 1.5rem 1rem 0;';
        } elseif ($alignment === 'right') {
            $figureStyle = 'float: right; margin: 0 0 1rem 1.5rem;';
        }

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

        $captionSource = self::contentOrProp($content, $props, 'caption');
        $captionHtml = '';
        if ($captionSource !== '') {
            if (trim($content) !== '') {
                $captionHtml = $captionSource;
            } else {
                $captionHtml = self::renderCaptionMarkdown($captionSource);
            }
        }

        $html = '<figure class="' . self::escape($figureClass) . '" style="' . self::escape($figureStyle) . '">';
        $html .= '<img ' . self::buildAttributes($attributes) . '>';

        if ($captionHtml !== '') {
            $html .= '<figcaption style="margin-top: 0.5rem; font-size: 0.875rem; color: #6b7280; line-height: 1.4;">';
            $html .= $captionHtml;
            $html .= '</figcaption>';
        }

        $html .= '</figure>';

        return $html;
    }

    private static function renderCaptionMarkdown(string $caption): string
    {
        $app = self::getApp();
        if ($app === null) {
            return '<p>' . self::escape($caption) . '</p>';
        }

        $parser = new \Flint\Parser($app);
        $parsed = $parser->parse($caption);

        return $parsed['content_html'] ?? '';
    }
}
