<?php

namespace Components;

use Flint\RenderComponent;

/**
 * Render markdown blocks from /site/blocks/.
 */
class Block extends RenderComponent
{
    /**
     * Render a reusable content block from /site/blocks/.
     */
    public static function render(array $props, string $content): string
    {
        // Read the block name from props or component body.
        $blockName = self::contentOrProp($content, $props, 'name');

        if ($blockName === '') {
            return '<!-- Block component: no name specified. -->';
        }

        // Fetch the active app so we can resolve filesystem paths.
        $app = self::getApp();
        if ($app === null) {
            return '<!-- Block component: application context not available. -->';
        }

        $blockPath = self::resolveMarkdownBlockPath($app->root, $blockName);

        if ($blockPath === null) {
            return sprintf('<!-- Block "%s" not found. -->', self::escape($blockName));
        }

        // Read and parse the block content through the CMS parser.
        $blockContent = file_get_contents($blockPath);

        // Create a fresh parser instance for isolated block rendering.
        $parser = new \Flint\Parser($app);
        $parsedBlock = $parser->parse($blockContent);

        // Return the rendered HTML.
        return $parsedBlock['content_html'];
    }

    /**
     * Resolve a markdown block path for the given root and block name.
     */
    public static function resolveMarkdownBlockPath(string $root, string $blockName): ?string
    {
        foreach (self::blockFileCandidates($root, $blockName) as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Build candidate block file paths (supporting nested directories).
     */
    private static function blockFileCandidates(string $root, string $blockName): array
    {
        $directory = self::blockDirectoryName($blockName);
        $candidates = [];

        foreach (['.md', '.mdx'] as $extension) {
            $candidates[] = "{$root}/site/blocks/{$directory}/{$blockName}{$extension}";
            $candidates[] = "{$root}/site/blocks/{$blockName}{$extension}";
        }

        return $candidates;
    }

    /**
     * Convert a block name such as "nav" or "contact-email" into a directory name.
     */
    private static function blockDirectoryName(string $blockName): string
    {
        $parts = preg_split('/[\\s\\-_]+/', $blockName);
        $parts = array_filter($parts, 'strlen');

        if (empty($parts)) {
            return ucfirst($blockName);
        }

        return implode('', array_map(fn($part) => ucfirst(strtolower($part)), $parts));
    }
}
