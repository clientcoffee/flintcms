<?php

namespace Components;

use Flint\RenderComponent;

class Block extends RenderComponent
{
    /**
     * Render a reusable content block from /content/blocks/
     */
    public static function render(array $props, string $content): string
    {
        // Get block name from props or content
        $blockName = self::contentOrProp($content, $props, 'name');

        if ($blockName === '') {
            return '<!-- Block component: no name specified -->';
        }

        // Get the current application instance
        $app = self::getApp();
        if ($app === null) {
            return '<!-- Block component: application context not available -->';
        }

        // Locate the block file (try .mdx first, then .md)
        $blockPath = null;
        foreach (['.mdx', '.md'] as $extension) {
            $testPath = $app->root . '/content/blocks/' . $blockName . $extension;
            if (file_exists($testPath) && is_file($testPath)) {
                $blockPath = $testPath;
                break;
            }
        }

        if ($blockPath === null) {
            return '<!-- Block "' . self::escape($blockName) . '" not found -->';
        }

        // Read and parse the block content
        $blockContent = file_get_contents($blockPath);

        // Create a new Parser instance to process the block
        $parser = new \Flint\Parser($app);
        $parsedBlock = $parser->parse($blockContent);

        // Return the rendered HTML
        return $parsedBlock['content_html'];
    }
}
