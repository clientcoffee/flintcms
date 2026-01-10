<?php

namespace Components;

use Flint\RenderComponent;

class Mermaid extends RenderComponent
{
    /**
     * Declare component dependencies
     */
    public static function getAssets(): array
    {
        // Load Mermaid from CDN (self-contained, no local assets)
        $inlineModuleScript = <<<SCRIPT
import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.esm.min.mjs';
mermaid.initialize({
    startOnLoad: true,
    theme: 'default',
    securityLevel: 'loose',
    fontFamily: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
});
SCRIPT;

        return [
            'inline_scripts' => [
                [
                    'content' => $inlineModuleScript,
                    'type' => 'module',
                    'position' => 'footer'
                ]
            ]
        ];
    }

    public static function render(array $props, string $content): string
    {
        // Generate a stable, unique ID for each diagram instance
        static $counter = 0;
        $counter++;
        $diagramId = 'mermaid-' . $counter;

        // Preserve Mermaid syntax exactly, including line breaks
        $diagramSource = trim($content);

        // Return a single-line wrapper to avoid markdown parsing interference
        // Mermaid consumes raw text and will render it into SVG on the client
        return '<div class="mermaid-wrapper my-8"><div id="' . self::escape($diagramId) . '" class="mermaid">' . $diagramSource . '</div></div>';
    }
}
