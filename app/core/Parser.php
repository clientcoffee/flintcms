<?php

namespace Flint;

/**
 * Markdown & MDX-Lite Parser
 *
 * This is the heart of Flint's content rendering system. It takes markdown
 * files with optional frontmatter and custom components, then outputs HTML.
 *
 * PARSING FLOW:
 * 1. Extract YAML-like frontmatter (metadata like title, date, etc.)
 * 2. Find and render custom components (<Hero>, <Alert>, etc.)
 * 3. Convert markdown syntax to HTML (headers, lists, links, etc.)
 * 4. Replace component placeholders with actual rendered HTML
 * 5. Collect component assets (CSS/JS) for injection into page
 *
 * COMPONENT CASCADE SYSTEM:
 * Components are resolved in this priority order:
 * - Theme components (site/themes/{theme}/*.php) → \Modules\ namespace
 * - Site components (site/components/*.php) → \Components\ namespace
 *
 * This allows themes to override default components for custom styling.
 *
 * WHY PLACEHOLDERS?
 * Components are replaced with placeholders (___COMPONENT_0___, etc.) before
 * markdown parsing. This prevents markdown parser from breaking component HTML.
 * After markdown parsing, placeholders are replaced with actual HTML.
 *
 * SECURITY:
 * - All markdown text is escaped with htmlspecialchars()
 * - Components are responsible for their own output escaping
 * - HTML from components is preserved (not escaped) - trust your components!
 *
 * @package Flint
 * @subpackage Core
 */
class Parser
{
    /**
     * Reference to the main application instance.
     * Used to access configuration (theme name) and paths.
     *
     * @var App
     */
    private App $application;

    /**
     * Cache of rendered component HTML keyed by placeholder.
     * Example: ['___COMPONENT_0___' => '<div class="hero">...</div>']
     *
     * @var array
     */
    private array $componentHtmlCache = [];

    /**
     * Counter for generating unique component placeholders.
     * Increments for each component found: 0, 1, 2, etc.
     *
     * @var int
     */
    private int $componentPlaceholderCounter = 0;

    /**
     * List of component class names used in the current document.
     * Example: ['Hero', 'Alert', 'ContactForm']
     * Used for debugging and analytics.
     *
     * @var array
     */
    private array $usedComponentNames = [];

    /**
     * Collection of CSS/JS assets required by components.
     * Components can register external scripts/styles or inline code.
     *
     * Structure:
     * [
     *   'scripts' => [['src' => '/path.js', 'position' => 'footer']],
     *   'inline_scripts' => [['content' => 'alert("hi")', 'position' => 'footer']],
     *   'styles' => [['href' => '/path.css']],
     *   'inline_styles' => [['content' => '.foo{color:red}']]
     * ]
     *
     * @var array
     */
    private array $componentAssets = [];

    /**
     * Static reference to current parser instance.
     * Used by the Block component to access the parser during rendering.
     *
     * WHY STATIC?
     * Block component needs to parse nested markdown files (blocks).
     * It needs access to the current parser instance to maintain context.
     * This is essentially a thread-local variable pattern.
     *
     * @var Parser|null
     */
    private static ?Parser $currentInstance = null;

    /**
     * Initialize parser with application context
     *
     * @param App $app The main application instance for config/path access
     */
    public function __construct(App $app)
    {
        // Store the application reference for configuration lookups
        // (primarily for getting the active theme name)
        $this->application = $app;
    }

    /**
     * Get the current parser instance (used by Block component)
     *
     * The Block component calls this to get access to the parser,
     * so it can parse nested markdown files while maintaining context.
     *
     * @return Parser|null Current parser instance or null if none active
     */
    public static function getCurrentInstance(): ?Parser
    {
        return self::$currentInstance;
    }

    /**
     * Render inline markdown without wrapping block-level tags.
     */
    public function renderInlineMarkdown(string $inlineText): string
    {
        return $this->processInlineMarkdown($inlineText);
    }

    /**
     * Get the application instance from this parser
     *
     * Components can use this to access application config and utilities.
     *
     * @return App The main application instance
     */
    public function getApplication(): App
    {
        return $this->application;
    }

    /**
     * Parse a markdown file from disk
     *
     * Convenience method that reads a file and parses its contents.
     *
     * @param string $filePath Absolute path to the markdown file
     * @return array Parsed content with meta, content_raw, content_html, etc.
     */
    public function parseFile(string $filePath): array
    {
        // Load the file contents from disk
        $rawFileContents = file_get_contents($filePath);

        // Parse the raw markdown text
        return $this->parse($rawFileContents);
    }

    /**
     * Parse raw markdown text into structured data
     *
     * This is the main entry point for parsing. It orchestrates:
     * 1. Frontmatter extraction (YAML-like metadata)
     * 2. Component discovery and rendering
     * 3. Markdown to HTML conversion
     * 4. Component placeholder restoration
     *
     * @param string $rawMarkdownText The raw markdown content to parse
     * @return array Associative array with keys:
     *   - meta: Frontmatter data (title, date, etc.)
     *   - content_raw: Original markdown body (without frontmatter)
     *   - content_html: Rendered HTML output
     *   - components_used: Array of component names used
     *   - component_assets: CSS/JS assets to inject
     */
    public function parse(string $rawMarkdownText): array
    {
        // Set current instance for nested parsing (allows Block component to work)
        // Save previous instance so we can restore it after parsing (nested parse support)
        $previousParserInstance = self::$currentInstance;
        self::$currentInstance = $this;

        // STEP 1: Parse frontmatter (YAML-like metadata at top of file)
        // Frontmatter looks like:
        // ---
        // title: My Page
        // date: 2024-01-01
        // ---
        $extractedFrontmatter = [];
        $markdownBody = $rawMarkdownText;

        if (str_starts_with($rawMarkdownText, "---")) {
            // Split document on "---" delimiters (expects 3 parts: empty, frontmatter, body)
            $frontmatterSections = preg_split('/^---$/m', $rawMarkdownText, 3);
            $sectionCount = count($frontmatterSections);

            if ($sectionCount === 3) {
                // Valid frontmatter structure found
                // Part [0] is empty (before first ---)
                // Part [1] is the frontmatter content
                // Part [2] is the markdown body
                $extractedFrontmatter = $this->parseYamlLite($frontmatterSections[1]);
                $markdownBody = $frontmatterSections[2];
            }
        }

        // STEP 2: Reset component tracking for this document
        $this->componentHtmlCache = [];
        $this->componentPlaceholderCounter = 0;
        $this->usedComponentNames = [];
        $this->componentAssets = [];

        // STEP 3: Find and render components, replacing them with placeholders
        // This prevents markdown parser from breaking component HTML
        $markdownBody = $this->parseComponents($markdownBody);

        // STEP 4: Convert markdown syntax to HTML
        $renderedHtmlOutput = $this->renderMarkdown($markdownBody);

        // STEP 5: Restore component HTML from placeholders
        // Now that markdown parsing is done, it's safe to put back the component HTML
        $renderedHtmlOutput = $this->restoreComponents($renderedHtmlOutput);

        // Restore previous parser instance (for nested parsing support)
        self::$currentInstance = $previousParserInstance;

        // Return structured data for downstream consumers
        return [
            'meta' => $extractedFrontmatter,
            'content_raw' => $markdownBody,
            'content_html' => $renderedHtmlOutput,
            'components_used' => $this->usedComponentNames,
            'component_assets' => $this->componentAssets
        ];
    }

    /**
     * Find and render custom components in markdown
     *
     * COMPONENT SYNTAX:
     * <ComponentName prop="value">Inner content</ComponentName>
     *
     * COMPONENT CASCADE:
     * Components are resolved in priority order:
     * 1. Theme components (site/themes/{theme}/ → \Modules\ComponentName)
     * 2. Site components (site/components/ → \Components\ComponentName)
     *
     * WHY THIS ORDER?
     * - Themes should be able to override default components for styling
     * - Site components can add custom functionality
     * - Core components provide baseline functionality
     *
     * PLACEHOLDER STRATEGY:
     * Components are replaced with ___COMPONENT_0___, ___COMPONENT_1___, etc.
     * This protects component HTML from being mangled by markdown parser.
     *
     * @param string $markdownSource Markdown text that may contain components
     * @return string Markdown with components replaced by placeholders
     */
    private function parseComponents(string $markdownSource): string
    {
        // REGEX PATTERN: Match <Tag attr="val">Content</Tag>
        // - <([A-Z][a-zA-Z0-9]*): Component name must start with uppercase
        // - \s*([^>]*): Optional attributes with any characters except >
        // - >: Opening tag close
        // - (.*?): Inner content (non-greedy)
        // - <\/\1>: Closing tag matching opening tag name
        // - /s: Dot matches newlines (for multi-line content)
        $componentTagPattern = '/<([A-Z][a-zA-Z0-9]*)\s*([^>]*)>(.*?)<\/\1>/s';

        // Replace each matched component with a placeholder
        return preg_replace_callback($componentTagPattern, function ($componentMatch) {
            $componentName = $componentMatch[1];           // e.g., "Hero"
            $attributeString = $componentMatch[2];         // e.g., 'title="Welcome" cta="Click"'
            $innerContent = $componentMatch[3];            // e.g., "This is **bold** text"

            // Parse HTML-style attributes into associative array
            $componentProps = $this->parseAttributes($attributeString);

            // Variable to hold the resolved component class name
            $resolvedComponentClass = null;

            // PRIORITY 1: Try theme components first (site/themes/{theme}/)
            $themeComponentClassName = "\\Modules\\{$componentName}";

            // Check if theme component class is already loaded
            if (!class_exists($themeComponentClassName)) {
                // Get active theme name from config
                $activeThemeName = $this->application->config['site']['theme'] ?? 'motion';
                $themeComponentDirectory = Paths::themeDir($activeThemeName);

                // Try to find component file in theme directory
                $themeComponentFilePath = $this->getComponentPath($themeComponentDirectory, $componentName);

                if ($themeComponentFilePath !== null) {
                    // Found component file in theme, load it
                    require_once $themeComponentFilePath;
                }
            }

            // Check if theme component is now available and has render method
            if (class_exists($themeComponentClassName) && method_exists($themeComponentClassName, 'render')) {
                $resolvedComponentClass = $themeComponentClassName;
            }

            // PRIORITY 2: Fall back to site components if theme component not found
            if (!$resolvedComponentClass) {
                $siteComponentClassName = "\\Components\\{$componentName}";
                $siteComponentDirectory = $this->application->root . '/site/components';

                // Try to find component file in site components directory
                $siteComponentFilePath = $this->getComponentPath($siteComponentDirectory, $componentName);

                if ($siteComponentFilePath !== null) {
                    // Found component file in site directory, load it
                    require_once $siteComponentFilePath;
                }

                // Check if site component is now available and has render method
                if (class_exists($siteComponentClassName) && method_exists($siteComponentClassName, 'render')) {
                    $resolvedComponentClass = $siteComponentClassName;
                }
            }

            // If we successfully resolved a component, render it
            if ($resolvedComponentClass) {
                // Track component usage for debugging and analytics
                if (!in_array($componentName, $this->usedComponentNames)) {
                    $this->usedComponentNames[] = $componentName;
                }

                // Collect component assets if component declares them
                // Components can implement getAssets() to register CSS/JS
                if (method_exists($resolvedComponentClass, 'getAssets')) {
                    $componentAssets = $resolvedComponentClass::getAssets();
                    // Merge these assets into our collection for later injection
                    $this->mergeAssets($componentAssets);
                }

                // Recursively parse inner content for nested components
                // Example: <Hero><Alert>nested</Alert></Hero>
                $parsedInnerContent = $this->parseComponents($innerContent);

                // Call the component's render method to get HTML
                $renderedComponentHtml = $resolvedComponentClass::render($componentProps, $parsedInnerContent);

                // Generate a unique placeholder for this component
                $uniquePlaceholder = "___COMPONENT_" . $this->componentPlaceholderCounter . "___";

                // Store the rendered HTML in cache for later restoration
                $this->componentHtmlCache[$uniquePlaceholder] = $renderedComponentHtml;

                // Increment counter for next component
                $this->componentPlaceholderCounter++;

                // Return placeholder (will be replaced after markdown parsing)
                return $uniquePlaceholder;
            }

            // Component not found in any location, return original tag unchanged
            // This prevents breaking the page if a component is missing
            return $componentMatch[0];
        }, $markdownSource);
    }

    /**
     * Find a component PHP file in a directory
     *
     * COMPONENT FILE STRUCTURE:
     * Components can be organized two ways:
     *
     * 1. Single file: ComponentName.php
     * 2. Directory: ComponentName/ComponentName.php (for complex components with assets)
     *
     * @param string $baseDirectory Directory to search in (theme or site)
     * @param string $componentName Component name (e.g., "Hero")
     * @return string|null Absolute path to component file or null if not found
     */
    private function getComponentPath(string $baseDirectory, string $componentName): ?string
    {
        // OPTION 1: Check for single-file component (Hero.php)
        $singleFilePath = $baseDirectory . '/' . $componentName . '.php';
        if (file_exists($singleFilePath)) {
            return $singleFilePath;
        }

        // OPTION 2: Check for directory-based component (Hero/Hero.php)
        // This structure allows components to have additional files (CSS, JS, assets)
        $directoryFilePath = $baseDirectory . '/' . $componentName . '/' . $componentName . '.php';
        if (file_exists($directoryFilePath)) {
            return $directoryFilePath;
        }

        // Component file not found in this directory
        return null;
    }

    /**
     * Replace component placeholders with actual rendered HTML
     *
     * After markdown parsing is complete, we can safely restore component HTML.
     * This prevents the markdown parser from breaking component markup.
     *
     * @param string $htmlWithPlaceholders HTML containing ___COMPONENT_X___ placeholders
     * @return string HTML with placeholders replaced by actual component HTML
     */
    private function restoreComponents(string $htmlWithPlaceholders): string
    {
        // Replace each placeholder with its cached HTML
        foreach ($this->componentHtmlCache as $placeholder => $componentHtml) {
            $htmlWithPlaceholders = str_replace($placeholder, $componentHtml, $htmlWithPlaceholders);
        }

        return $htmlWithPlaceholders;
    }

    /**
     * Merge component assets into the collection
     *
     * Components can register CSS and JavaScript assets they need.
     * These are collected and injected into the page by the theme layout.
     *
     * ASSET TYPES:
     * - scripts: External JavaScript files (e.g., {'src': '/path.js', 'position': 'footer'})
     * - inline_scripts: Inline JavaScript code (e.g., {'content': 'alert("hi")', 'position': 'footer'})
     * - styles: External CSS files (e.g., {'href': '/path.css'})
     * - inline_styles: Inline CSS code (e.g., {'content': '.foo{color:red}'})
     *
     * @param array $newAssets Assets declared by a component
     */
    private function mergeAssets(array $newAssets): void
    {
        // Initialize asset buckets on first use
        if (empty($this->componentAssets)) {
            $this->componentAssets = [
                'scripts' => [],
                'inline_scripts' => [],
                'styles' => [],
                'inline_styles' => []
            ];
        }

        // Merge external scripts
        if (!empty($newAssets['scripts'])) {
            foreach ($newAssets['scripts'] as $externalScript) {
                $this->componentAssets['scripts'][] = $externalScript;
            }
        }

        // Merge inline scripts
        if (!empty($newAssets['inline_scripts'])) {
            foreach ($newAssets['inline_scripts'] as $inlineScript) {
                $this->componentAssets['inline_scripts'][] = $inlineScript;
            }
        }

        // Merge external stylesheets
        if (!empty($newAssets['styles'])) {
            foreach ($newAssets['styles'] as $externalStylesheet) {
                $this->componentAssets['styles'][] = $externalStylesheet;
            }
        }

        // Merge inline styles
        if (!empty($newAssets['inline_styles'])) {
            foreach ($newAssets['inline_styles'] as $inlineStyle) {
                $this->componentAssets['inline_styles'][] = $inlineStyle;
            }
        }
    }

    /**
     * Parse HTML-style attributes into key-value pairs
     *
     * EXAMPLE INPUT: 'title="Hello World" type="info" count="5"'
     * EXAMPLE OUTPUT: ['title' => 'Hello World', 'type' => 'info', 'count' => '5']
     *
     * NOTE: Only double-quoted attributes are supported currently.
     * Single quotes and unquoted attributes are not parsed.
     *
     * @param string $attributeString Raw attribute string from component tag
     * @return array Associative array of attribute name => value
     */
    private function parseAttributes(string $attributeString): array
    {
        $parsedAttributes = [];

        // REGEX PATTERN: (\w+)="([^"]*)"
        // - (\w+): Attribute name (word characters: letters, numbers, underscore)
        // - =: Equals sign
        // - "([^"]*)": Double-quoted value (any characters except quotes)
        // PREG_SET_ORDER: Returns matches grouped by match (not by capture group)
        if (preg_match_all('/(\w+)="([^"]*)"/', $attributeString, $attributeMatches, PREG_SET_ORDER)) {
            foreach ($attributeMatches as $singleAttributeMatch) {
                $attributeName = $singleAttributeMatch[1];   // e.g., "title"
                $attributeValue = $singleAttributeMatch[2];  // e.g., "Hello World"
                $parsedAttributes[$attributeName] = $attributeValue;
            }
        }

        return $parsedAttributes;
    }

    /**
     * Parse lightweight YAML frontmatter into key-value pairs
     *
     * SUPPORTED SYNTAX:
     * key: value
     * title: My Page Title
     * date: 2024-01-01
     * draft: false
     *
     * LIMITATIONS:
     * - Only supports simple key: value pairs
     * - No nested structures (no arrays, no objects)
     * - No multi-line values
     * - Values can be quoted with " or ' (quotes are stripped)
     *
     * This is intentionally simple to avoid requiring a full YAML parser.
     *
     * @param string $yamlText Raw frontmatter text (between --- markers)
     * @return array Associative array of metadata
     */
    private function parseYamlLite(string $yamlText): array
    {
        $parsedMetadata = [];

        // Split into individual lines
        $yamlLines = explode("\n", $yamlText);

        foreach ($yamlLines as $yamlLine) {
            // Skip lines that don't contain a colon (not key:value format)
            if (!str_contains($yamlLine, ':')) {
                continue;
            }

            // Split on first colon only (value might contain colons)
            [$keyText, $valueText] = explode(':', $yamlLine, 2);

            // Trim whitespace and remove surrounding quotes
            $cleanKey = trim($keyText);
            $cleanValue = trim(trim($valueText), '"\'');

            $parsedMetadata[$cleanKey] = $cleanValue;
        }

        return $parsedMetadata;
    }

    /**
     * Convert markdown syntax to HTML
     *
     * This is a lightweight markdown renderer that supports:
     * - Headers (# ## ### etc.)
     * - Bold text (**text**)
     * - Links [text](url)
     * - Inline code `code`
     * - Code blocks (```)
     * - Lists (unordered + ordered, including nested)
     * - Blockquotes (> text)
     * - Images and video embeds ![alt](url)
     * - Tables (| col1 | col2 |)
     * - Horizontal rules (---, ***, ___)
     *
     * WHY CUSTOM PARSER?
     * - No external dependencies (works "drop-in" without composer install)
     * - Full control over HTML output and styling
     * - Security: We control escaping strategy
     *
     * PARSING STRATEGY:
     * - Line-by-line processing (not AST-based)
     * - State machine for block elements (lists, blockquotes, code blocks)
     * - Inline elements processed after block structure is established
     *
     * @param string $markdownText Raw markdown text to convert
     * @return string Rendered HTML
     */
    private function renderMarkdown(string $markdownText): string
    {
        // Split markdown into individual lines for processing
        $markdownLines = explode("\n", $markdownText);
        $htmlOutputFragments = [];

        // Track current parser state (for multi-line blocks)
        $isInBlockquote = false;
        $currentListDepth = -1;
        /** @var array<int, string> $listStack */
        $listStack = [];

        // Track position in line array
        $currentLineIndex = 0;
        $totalLineCount = count($markdownLines);

        // Process each line
        while ($currentLineIndex < $totalLineCount) {
            $currentLineText = $markdownLines[$currentLineIndex];
            $trimmedLine = trim($currentLineText);

            // Skip empty lines
            if ($trimmedLine === '') {
                $currentLineIndex++;
                continue;
            }

            // STANDALONE IMAGES/EMBEDS (no paragraph wrapper)
            // Pattern: ![alt text](url) as entire line
            if (preg_match('/^!\[([^\]]*)\]\(([^)]+)\)$/', $trimmedLine, $imageMatch)) {
                $altText = $imageMatch[1];
                $imageUrl = $imageMatch[2];
                // Third parameter "true" means render as block (not inline)
                $htmlOutputFragments[] = $this->renderImageOrEmbed($altText, $imageUrl, true);
                $currentLineIndex++;
                continue;
            }

            // FENCED CODE BLOCKS (triple backticks)
            // Pattern: ```language
            if (preg_match('/^```(\w*)$/', $trimmedLine, $codeFenceMatch)) {
                // Close any open blocks before starting code block
                if ($currentListDepth >= 0) {
                    for ($depth = $currentListDepth; $depth >= 0; $depth--) {
                        $listTypeToClose = array_pop($listStack);
                        if ($listTypeToClose === null) {
                            break;
                        }
                        $htmlOutputFragments[] = "</{$listTypeToClose}>";
                    }
                    $currentListDepth = -1;
                }
                if ($isInBlockquote) {
                    $htmlOutputFragments[] = "</blockquote>";
                    $isInBlockquote = false;
                }

                // Extract optional language label (for syntax highlighting)
                $languageLabel = $codeFenceMatch[1] ?: '';
                $codeBlockLines = [];
                $currentLineIndex++;

                // Collect all lines until closing fence (```)
                while ($currentLineIndex < $totalLineCount) {
                    $codeLineText = $markdownLines[$currentLineIndex];

                    // Check for closing fence
                    if (trim($codeLineText) === '```') {
                        break;
                    }

                    // Add line to code block
                    $codeBlockLines[] = $codeLineText;
                    $currentLineIndex++;
                }

                // Render code block with syntax highlighting class
                $codeText = htmlspecialchars(implode("\n", $codeBlockLines));
                $languageClass = $languageLabel ? ' class="language-' . htmlspecialchars($languageLabel) . '"' : '';
                $htmlOutputFragments[] = '<pre class="bg-gray-100 p-4 rounded-md overflow-x-auto my-4"><code' . $languageClass . '>' . $codeText . '</code></pre>';

                // Move past closing fence
                $currentLineIndex++;
                continue;
            }

            // TABLES (detected by pipes at start and end)
            // Pattern: | col1 | col2 |
            if (preg_match('/^\|(.+)\|$/', $trimmedLine)) {
                // Close list if open
                if ($currentListDepth >= 0) {
                    for ($depth = $currentListDepth; $depth >= 0; $depth--) {
                        $listTypeToClose = array_pop($listStack);
                        if ($listTypeToClose === null) {
                            break;
                        }
                        $htmlOutputFragments[] = "</{$listTypeToClose}>";
                    }
                    $currentListDepth = -1;
                }

                // Parse entire table block
                $tableParseResult = $this->parseTable($markdownLines, $currentLineIndex);
                $htmlOutputFragments[] = $tableParseResult['html'];
                $currentLineIndex = $tableParseResult['nextIndex'];
                continue;
            }

            // HORIZONTAL RULES (3+ dashes, asterisks, or underscores)
            // Pattern: ---, ***, ___
            if (preg_match('/^[-*_]{3,}$/', $trimmedLine)) {
                // Close any open blocks
                if ($currentListDepth >= 0) {
                    for ($depth = $currentListDepth; $depth >= 0; $depth--) {
                        $listTypeToClose = array_pop($listStack);
                        if ($listTypeToClose === null) {
                            break;
                        }
                        $htmlOutputFragments[] = "</{$listTypeToClose}>";
                    }
                    $currentListDepth = -1;
                }
                if ($isInBlockquote) {
                    $htmlOutputFragments[] = "</blockquote>";
                    $isInBlockquote = false;
                }

                $htmlOutputFragments[] = '<hr class="my-4 border-gray-300" />';
                $currentLineIndex++;
                continue;
            }

            // BLOCKQUOTES (lines starting with "> ")
            // Pattern: > This is a quote
            if (str_starts_with($trimmedLine, '> ')) {
                // Close list if open
                if ($currentListDepth >= 0) {
                    for ($depth = $currentListDepth; $depth >= 0; $depth--) {
                        $listTypeToClose = array_pop($listStack);
                        if ($listTypeToClose === null) {
                            break;
                        }
                        $htmlOutputFragments[] = "</{$listTypeToClose}>";
                    }
                    $currentListDepth = -1;
                }

                // Open blockquote if not already open
                if (!$isInBlockquote) {
                    $htmlOutputFragments[] = '<blockquote class="border-l-4 border-gray-300 pl-4 italic my-4">';
                    $isInBlockquote = true;
                }

                // Extract text after "> " and process inline markdown
                $blockquoteContent = $this->processInlineMarkdown(substr($trimmedLine, 2));
                $htmlOutputFragments[] = "<p>{$blockquoteContent}</p>";

                $currentLineIndex++;
                continue;
            }

            // HEADERS (# through ######)
            // Pattern: ### Header Text
            if (preg_match('/^(#{1,6})\s+(.*)$/', $trimmedLine, $headerMatch)) {
                // Close any open blocks
                if ($currentListDepth >= 0) {
                    for ($depth = $currentListDepth; $depth >= 0; $depth--) {
                        $listTypeToClose = array_pop($listStack);
                        if ($listTypeToClose === null) {
                            break;
                        }
                        $htmlOutputFragments[] = "</{$listTypeToClose}>";
                    }
                    $currentListDepth = -1;
                }
                if ($isInBlockquote) {
                    $htmlOutputFragments[] = "</blockquote>";
                    $isInBlockquote = false;
                }

                // Determine header level from number of # symbols
                $headerLevel = strlen($headerMatch[1]);  // 1-6
                $headerText = $headerMatch[2];

                // Process inline markdown in header text
                $headerContent = $this->processInlineMarkdown($headerText);

                $htmlOutputFragments[] = "<h{$headerLevel}>{$headerContent}</h{$headerLevel}>";

                $currentLineIndex++;
                continue;
            }

            // LISTS (unordered + ordered, including nested)
            // Pattern: - List item
            //          * Another item
            //            - Nested item (2 spaces = 1 level of nesting)
            $listMatch = null;
            $listType = '';
            if (preg_match('/^(\s*)([-*])\s+(.*)$/', $currentLineText, $listMatch)) {
                $listType = 'ul';
            } elseif (preg_match('/^(\s*)(\d+)\.\s+(.*)$/', $currentLineText, $listMatch)) {
                $listType = 'ol';
            }

            if ($listType !== '') {
                // Close blockquote if open
                if ($isInBlockquote) {
                    $htmlOutputFragments[] = "</blockquote>";
                    $isInBlockquote = false;
                }

                // Calculate nesting depth based on indentation
                // 2 spaces = 1 level of nesting
                $indentationSpaces = strlen($listMatch[1]);
                $targetDepth = (int)($indentationSpaces / 2);
                $listClass = $listType === 'ol' ? ' class="list-decimal pl-6"' : ' class="list-disc pl-6"';

                // Open list if not already open
                if ($currentListDepth < 0) {
                    $htmlOutputFragments[] = "<{$listType}{$listClass}>";
                    $listStack[] = $listType;
                    $currentListDepth = 0;
                }

                // Handle depth changes (nested lists)
                if ($targetDepth > $currentListDepth) {
                    // Going deeper - open new nested list tags
                    for ($depth = $currentListDepth + 1; $depth <= $targetDepth; $depth++) {
                        $htmlOutputFragments[] = "<{$listType}{$listClass}>";
                        $listStack[] = $listType;
                    }
                }

                if ($targetDepth < $currentListDepth) {
                    // Going shallower - close nested lists
                    for ($depth = $currentListDepth; $depth > $targetDepth; $depth--) {
                        $listTypeToClose = array_pop($listStack);
                        if ($listTypeToClose === null) {
                            break;
                        }
                        $htmlOutputFragments[] = "</{$listTypeToClose}>";
                    }
                    $currentListDepth = $targetDepth;
                }

                if ($targetDepth > $currentListDepth) {
                    $currentListDepth = $targetDepth;
                }

                if (isset($listStack[$currentListDepth]) && $listStack[$currentListDepth] !== $listType) {
                    $htmlOutputFragments[] = "</{$listStack[$currentListDepth]}>";
                    array_pop($listStack);
                    $htmlOutputFragments[] = "<{$listType}{$listClass}>";
                    $listStack[] = $listType;
                }

                // Process inline markdown in list item text
                $listItemText = $listMatch[3];
                $listItemContent = $this->processInlineMarkdown($listItemText);

                $htmlOutputFragments[] = "<li>{$listItemContent}</li>";

                $currentLineIndex++;
                continue;
            }

            // Close list/blockquote if we hit a non-matching line
            if ($currentListDepth >= 0) {
                for ($depth = $currentListDepth; $depth >= 0; $depth--) {
                    $listTypeToClose = array_pop($listStack);
                    if ($listTypeToClose === null) {
                        break;
                    }
                    $htmlOutputFragments[] = "</{$listTypeToClose}>";
                }
                $currentListDepth = -1;
            }
            if ($isInBlockquote) {
                $htmlOutputFragments[] = "</blockquote>";
                $isInBlockquote = false;
            }

            // COMPONENT PLACEHOLDERS (output directly without paragraph wrapper)
            // Pattern: ___COMPONENT_0___, ___COMPONENT_1___, etc.
            if (preg_match('/^___COMPONENT_\d+___$/', $trimmedLine)) {
                // This is a component placeholder, output as-is
                $htmlOutputFragments[] = $trimmedLine;
                $currentLineIndex++;
                continue;
            }

            // DEFAULT: PARAGRAPHS
            // Any line that doesn't match a special pattern becomes a paragraph
            $paragraphContent = $this->processInlineMarkdown($trimmedLine);
            $htmlOutputFragments[] = "<p>{$paragraphContent}</p>";

            $currentLineIndex++;
        }

        // Close any blocks still open at end of document
        if ($currentListDepth >= 0) {
            for ($depth = $currentListDepth; $depth >= 0; $depth--) {
                $listTypeToClose = array_pop($listStack);
                if ($listTypeToClose === null) {
                    break;
                }
                $htmlOutputFragments[] = "</{$listTypeToClose}>";
            }
        }
        if ($isInBlockquote) {
            $htmlOutputFragments[] = "</blockquote>";
        }

        // Join all HTML fragments into final output
        return implode("\n", $htmlOutputFragments);
    }

    /**
     * Parse a markdown table into HTML
     *
     * TABLE SYNTAX:
     * | Header 1 | Header 2 |
     * |----------|----------|
     * | Cell 1   | Cell 2   |
     * | Cell 3   | Cell 4   |
     *
     * The separator row (|---|---|) is optional and ignored.
     *
     * @param array $markdownLines All markdown lines
     * @param int $startIndex Index where table starts (modified by reference)
     * @return array Array with 'html' (rendered table) and 'nextIndex' (where to continue parsing)
     */
    private function parseTable(array $markdownLines, int &$startIndex): array
    {
        $tableHtmlFragments = ['<table class="min-w-full border-collapse border border-gray-300">'];
        $currentLineIndex = $startIndex;
        $isHeaderRow = true;
        $totalLineCount = count($markdownLines);

        while ($currentLineIndex < $totalLineCount) {
            $currentLineText = trim($markdownLines[$currentLineIndex]);

            // Stop if line is not a table row (doesn't have pipes at start/end)
            if (!preg_match('/^\|(.+)\|$/', $currentLineText)) {
                break;
            }

            // Skip separator row (|---|---|)
            if (preg_match('/^\|[\s\-:]+\|$/', $currentLineText)) {
                $currentLineIndex++;
                continue;
            }

            // Parse cells by splitting on pipe character
            $cellValues = array_map('trim', explode('|', trim($currentLineText, '|')));

            if ($isHeaderRow) {
                // First row becomes <thead> with <th> cells
                $tableHtmlFragments[] = '<thead class="bg-gray-100">';
                $tableHtmlFragments[] = '<tr>';
                foreach ($cellValues as $cellValue) {
                    $cellContent = $this->processInlineMarkdown($cellValue);
                    $tableHtmlFragments[] = "<th class=\"border border-gray-300 px-4 py-2 text-left font-semibold\">{$cellContent}</th>";
                }
                $tableHtmlFragments[] = '</tr>';
                $tableHtmlFragments[] = '</thead>';
                $tableHtmlFragments[] = '<tbody>';
                $isHeaderRow = false;
            } else {
                // Subsequent rows become <td> cells
                $tableHtmlFragments[] = '<tr>';
                foreach ($cellValues as $cellValue) {
                    $cellContent = $this->processInlineMarkdown($cellValue);
                    $tableHtmlFragments[] = "<td class=\"border border-gray-300 px-4 py-2\">{$cellContent}</td>";
                }
                $tableHtmlFragments[] = '</tr>';
            }

            $currentLineIndex++;
        }

        $tableHtmlFragments[] = '</tbody>';
        $tableHtmlFragments[] = '</table>';

        return [
            'html' => implode("\n", $tableHtmlFragments),
            'nextIndex' => $currentLineIndex
        ];
    }

    /**
     * Process inline markdown formatting
     *
     * INLINE ELEMENTS:
     * - Bold: **text**
     * - Links: [text](url)
     * - Inline code: `code`
     * - Images: ![alt](url)
     *
     * SECURITY: Text is escaped with htmlspecialchars(), but HTML tags
     * from components are preserved (they're temporarily replaced with placeholders).
     *
     * WHY PRESERVE HTML TAGS?
     * Components generate HTML that should not be escaped. We need to distinguish
     * between user-written text (must escape) and component HTML (don't escape).
     *
     * @param string $inlineText Text that may contain inline markdown
     * @return string HTML with inline markdown processed
     */
    private function processInlineMarkdown(string $inlineText): string
    {
        // STEP 1: Temporarily replace HTML tags (from components) with placeholders
        // This prevents markdown parser from breaking component HTML
        $htmlTagMap = [];
        $tagPlaceholderPrefix = '___HTML_TAG_';
        $tagCounter = 0;

        // Match HTML tags: <div class="...">content</div>, <span>, etc.
        $inlineText = preg_replace_callback('/<[^>]+>/', function ($tagMatch) use (&$htmlTagMap, $tagPlaceholderPrefix, &$tagCounter) {
            // Generate unique placeholder for this tag
            $placeholderKey = $tagPlaceholderPrefix . $tagCounter . '___';
            // Store original HTML tag
            $htmlTagMap[$placeholderKey] = $tagMatch[0];
            $tagCounter++;
            // Replace with placeholder
            return $placeholderKey;
        }, $inlineText);

        // STEP 2: Process inline code (must be before other markdown to take precedence)
        // Pattern: `code text`
        // SECURITY: Code content is escaped to prevent XSS
        $codeSpanMap = [];
        $codePlaceholderPrefix = '___CODE_SPAN_';
        $codeCounter = 0;
        $inlineText = preg_replace_callback('/`([^`]+)`/', function ($codeMatch) use (&$codeSpanMap, $codePlaceholderPrefix, &$codeCounter) {
            $placeholderKey = $codePlaceholderPrefix . $codeCounter . '___';
            $codeSpanMap[$placeholderKey] = '<code class="bg-gray-100 px-1 py-0.5 rounded text-sm font-mono">' . htmlspecialchars($codeMatch[1], ENT_QUOTES, 'UTF-8') . '</code>';
            $codeCounter++;
            return $placeholderKey;
        }, $inlineText);

        // STEP 3: Process images and video embeds (inline)
        // Pattern: ![alt text](url)
        // Second parameter "false" means render inline (not as block)
        $inlineText = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/', function ($imageMatch) {
            return $this->renderImageOrEmbed($imageMatch[1], $imageMatch[2], false);
        }, $inlineText);

        // STEP 4: Process bold text
        // Pattern: **bold text**
        // SECURITY: Text content is escaped to prevent XSS
        // Fixed in code review - was vulnerable to XSS before using callback
        $inlineText = preg_replace_callback('/\*\*(.*?)\*\*/', function ($boldMatch) {
            return '<strong>' . htmlspecialchars($boldMatch[1], ENT_QUOTES, 'UTF-8') . '</strong>';
        }, $inlineText);

        // STEP 5: Process italics
        // Pattern: *italic text* or _italic text_
        $inlineText = preg_replace_callback('/(?<!\*)\*(?!\s)([^*]+?)(?<!\s)\*(?!\*)/', function ($italicMatch) {
            return '<em>' . htmlspecialchars($italicMatch[1], ENT_QUOTES, 'UTF-8') . '</em>';
        }, $inlineText);
        $inlineText = preg_replace_callback('/(?<!\w)_(?!\s)([^_]+?)(?<!\s)_(?!\w)/', function ($italicMatch) {
            return '<em>' . htmlspecialchars($italicMatch[1], ENT_QUOTES, 'UTF-8') . '</em>';
        }, $inlineText);

        // STEP 6: Process links
        // Pattern: [link text](url)
        // SECURITY: Both link text and URL are escaped to prevent XSS
        // Fixed in code review - was vulnerable to XSS before using callback
        $inlineText = preg_replace_callback('/\[(.*?)\]\((.*?)\)/', function ($linkMatch) {
            $linkText = htmlspecialchars($linkMatch[1], ENT_QUOTES, 'UTF-8');
            $linkUrl = htmlspecialchars($linkMatch[2], ENT_QUOTES, 'UTF-8');
            return '<a href="' . $linkUrl . '">' . $linkText . '</a>';
        }, $inlineText);

        // STEP 7: Restore HTML tags from components
        // Now that markdown processing is done, put back the component HTML
        foreach ($htmlTagMap as $placeholderKey => $originalTagHtml) {
            $inlineText = str_replace($placeholderKey, $originalTagHtml, $inlineText);
        }

        // STEP 8: Restore inline code spans
        foreach ($codeSpanMap as $placeholderKey => $codeHtml) {
            $inlineText = str_replace($placeholderKey, $codeHtml, $inlineText);
        }

        return $inlineText;
    }

    /**
     * Render an image tag or video embed
     *
     * SUPPORTED VIDEO PLATFORMS:
     * - YouTube (including shorts)
     * - Vimeo
     * - TikTok
     * - Instagram Reels
     * - X/Twitter
     * - Twitch
     * - Facebook
     * - Dailymotion
     * - Loom
     * - Wistia
     *
     * BLOCK vs INLINE RENDERING:
     * - Block (standalone): Rendered as <figure> with caption or <iframe> embed
     * - Inline (in paragraph): Rendered as clickable link or inline <img>
     *
     * @param string $altText Alternative text for image or video title
     * @param string $sourceUrl URL to image or video
     * @param bool $renderAsBlock True for block-level rendering, false for inline
     * @return string Rendered HTML
     */
    private function renderImageOrEmbed(string $altText, string $sourceUrl, bool $renderAsBlock): string
    {
        // Clean up input
        $trimmedAltText = trim($altText);
        $escapedAltText = htmlspecialchars($trimmedAltText);
        $escapedSourceUrl = htmlspecialchars(trim($sourceUrl));

        // Check if this is a video URL (YouTube, Vimeo, etc.)
        $videoEmbedData = $this->getVideoEmbedData($sourceUrl);

        if ($videoEmbedData) {
            // This is a video embed
            $embedUrl = $videoEmbedData['embed_url'];
            $videoTitle = $videoEmbedData['title'];

            if (!$renderAsBlock) {
                // INLINE: Render as clickable link
                $linkLabel = $escapedAltText !== '' ? $escapedAltText : $videoTitle;
                return '<a class="text-gray-700 underline" href="' . $escapedSourceUrl . '">' . $linkLabel . '</a>';
            }

            // BLOCK: Render as iframe embed
            return '<div class="motion-embed my-6"><iframe src="' . htmlspecialchars($embedUrl) . '" title="' . htmlspecialchars($videoTitle) . '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe></div>';
        }

        // Not a video - render as image
        if ($renderAsBlock) {
            // BLOCK: Render as <figure> with optional caption
            $captionHtml = $escapedAltText !== '' ? '<figcaption class="mt-2 text-sm text-gray-500">' . $escapedAltText . '</figcaption>' : '';
            return '<figure class="motion-figure my-6"><img class="w-full rounded-2xl shadow-sm border border-gray-200" src="' . $escapedSourceUrl . '" alt="' . $escapedAltText . '">' . $captionHtml . '</figure>';
        }

        // INLINE: Render as inline image
        return '<img class="inline-block max-w-full rounded-lg border border-gray-200" src="' . $escapedSourceUrl . '" alt="' . $escapedAltText . '">';
    }

    /**
     * Detect video platform and return embed data
     *
     * Checks URL against known video platform patterns and returns
     * embed URL and title if matched.
     *
     * @param string $sourceUrl Full URL to video
     * @return array|null Array with 'embed_url' and 'title' or null if not a video
     */
    private function getVideoEmbedData(string $sourceUrl): ?array
    {
        // Parse URL into components
        $urlParts = parse_url(trim($sourceUrl));
        if (!$urlParts) {
            return null;
        }

        $hostName = strtolower($urlParts['host'] ?? '');
        $urlPath = $urlParts['path'] ?? '';
        $queryString = $urlParts['query'] ?? '';

        // Check YouTube URLs (most common, check first)
        $youtubeId = $this->getYouTubeId($hostName, $urlPath, $queryString);
        if ($youtubeId) {
            return [
                'embed_url' => 'https://www.youtube.com/embed/' . $youtubeId,
                'title' => 'YouTube video'
            ];
        }

        // Check Vimeo URLs
        $vimeoId = $this->getVimeoId($hostName, $urlPath);
        if ($vimeoId) {
            return [
                'embed_url' => 'https://player.vimeo.com/video/' . $vimeoId,
                'title' => 'Vimeo video'
            ];
        }

        // Check TikTok URLs
        $tikTokId = $this->getTikTokId($hostName, $urlPath);
        if ($tikTokId) {
            return [
                'embed_url' => 'https://www.tiktok.com/embed/' . $tikTokId,
                'title' => 'TikTok video'
            ];
        }

        // Check Instagram reel URLs
        $instagramReelId = $this->getInstagramReelId($hostName, $urlPath);
        if ($instagramReelId) {
            return [
                'embed_url' => 'https://www.instagram.com/reel/' . $instagramReelId . '/embed',
                'title' => 'Instagram reel'
            ];
        }

        // Check X/Twitter status URLs
        $twitterStatusId = $this->getTwitterStatusId($hostName, $urlPath);
        if ($twitterStatusId) {
            $encodedUrl = rawurlencode($sourceUrl);
            return [
                'embed_url' => 'https://twitframe.com/show?url=' . $encodedUrl,
                'title' => 'X video'
            ];
        }

        // Check Twitch videos and clips
        $twitchEmbedUrl = $this->getTwitchEmbedUrl($hostName, $urlPath);
        if ($twitchEmbedUrl) {
            return [
                'embed_url' => $twitchEmbedUrl,
                'title' => 'Twitch video'
            ];
        }

        // Check Facebook video URLs
        $facebookEmbedUrl = $this->getFacebookEmbedUrl($hostName, $urlPath, $sourceUrl);
        if ($facebookEmbedUrl) {
            return [
                'embed_url' => $facebookEmbedUrl,
                'title' => 'Facebook video'
            ];
        }

        // Check Dailymotion URLs
        $dailymotionId = $this->getDailymotionId($hostName, $urlPath);
        if ($dailymotionId) {
            return [
                'embed_url' => 'https://www.dailymotion.com/embed/video/' . $dailymotionId,
                'title' => 'Dailymotion video'
            ];
        }

        // Check Loom URLs
        $loomId = $this->getLoomId($hostName, $urlPath);
        if ($loomId) {
            return [
                'embed_url' => 'https://www.loom.com/embed/' . $loomId,
                'title' => 'Loom video'
            ];
        }

        // Check Wistia URLs
        $wistiaId = $this->getWistiaId($hostName, $urlPath);
        if ($wistiaId) {
            return [
                'embed_url' => 'https://fast.wistia.net/embed/iframe/' . $wistiaId,
                'title' => 'Wistia video'
            ];
        }

        // Not a recognized video URL
        return null;
    }

    /**
     * Extract YouTube video ID from URL
     *
     * SUPPORTED FORMATS:
     * - https://youtu.be/VIDEO_ID
     * - https://www.youtube.com/watch?v=VIDEO_ID
     * - https://www.youtube.com/embed/VIDEO_ID
     * - https://www.youtube.com/shorts/VIDEO_ID
     *
     * @param string $hostName Domain name (youtu.be or youtube.com)
     * @param string $urlPath URL path component
     * @param string $queryString URL query string
     * @return string|null Video ID or null if not found
     */
    private function getYouTubeId(string $hostName, string $urlPath, string $queryString): ?string
    {
        // Check if this is a YouTube domain
        if (!str_contains($hostName, 'youtu.be') && !str_contains($hostName, 'youtube.com')) {
            return null;
        }

        $videoId = null;

        // Format 1: youtu.be/VIDEO_ID
        if (str_contains($hostName, 'youtu.be')) {
            $videoId = ltrim($urlPath, '/');
        }

        // Format 2-4: youtube.com variations
        if (str_contains($hostName, 'youtube.com')) {
            // Parse query string (for watch?v=VIDEO_ID)
            parse_str($queryString, $queryParameters);

            if (!empty($queryParameters['v'])) {
                // Format 2: watch?v=VIDEO_ID
                $videoId = $queryParameters['v'];
            } elseif (preg_match('#^/embed/([A-Za-z0-9_-]+)#', $urlPath, $embedMatch)) {
                // Format 3: /embed/VIDEO_ID
                $videoId = $embedMatch[1];
            } elseif (preg_match('#^/shorts/([A-Za-z0-9_-]+)#', $urlPath, $shortsMatch)) {
                // Format 4: /shorts/VIDEO_ID
                $videoId = $shortsMatch[1];
            }
        }

        // Validate video ID format (alphanumeric, underscore, dash, min 6 chars)
        if (!$videoId || !preg_match('/^[A-Za-z0-9_-]{6,}$/', $videoId)) {
            return null;
        }

        return $videoId;
    }

    /**
     * Extract Vimeo video ID from URL
     *
     * SUPPORTED FORMATS:
     * - https://vimeo.com/VIDEO_ID
     * - https://vimeo.com/video/VIDEO_ID
     *
     * @param string $hostName Domain name (vimeo.com)
     * @param string $urlPath URL path component
     * @return string|null Video ID or null if not found
     */
    private function getVimeoId(string $hostName, string $urlPath): ?string
    {
        if (!str_contains($hostName, 'vimeo.com')) {
            return null;
        }

        // Format 1: /VIDEO_ID
        if (preg_match('#/(\d+)$#', $urlPath, $idMatch)) {
            return $idMatch[1];
        }

        // Format 2: /video/VIDEO_ID
        if (preg_match('#/video/(\d+)#', $urlPath, $idMatch)) {
            return $idMatch[1];
        }

        return null;
    }

    /**
     * Extract TikTok video ID from URL
     *
     * SUPPORTED FORMATS:
     * - https://www.tiktok.com/@user/video/VIDEO_ID
     * - https://vm.tiktok.com/t/VIDEO_ID
     *
     * @param string $hostName Domain name (tiktok.com)
     * @param string $urlPath URL path component
     * @return string|null Video ID or null if not found
     */
    private function getTikTokId(string $hostName, string $urlPath): ?string
    {
        if (!str_contains($hostName, 'tiktok.com')) {
            return null;
        }

        // Format 1: /video/VIDEO_ID
        if (preg_match('#/video/(\d+)#', $urlPath, $idMatch)) {
            return $idMatch[1];
        }

        // Format 2: /t/VIDEO_ID (short links)
        if (preg_match('#/t/([A-Za-z0-9]+)#', $urlPath, $idMatch)) {
            return $idMatch[1];
        }

        return null;
    }

    /**
     * Extract Instagram reel ID from URL
     *
     * SUPPORTED FORMATS:
     * - https://www.instagram.com/reel/REEL_ID
     *
     * @param string $hostName Domain name (instagram.com)
     * @param string $urlPath URL path component
     * @return string|null Reel ID or null if not found
     */
    private function getInstagramReelId(string $hostName, string $urlPath): ?string
    {
        if (!str_contains($hostName, 'instagram.com')) {
            return null;
        }

        // Format: /reel/REEL_ID
        if (preg_match('#/reel/([A-Za-z0-9_-]+)#', $urlPath, $idMatch)) {
            return $idMatch[1];
        }

        return null;
    }

    /**
     * Extract X/Twitter status ID from URL
     *
     * SUPPORTED FORMATS:
     * - https://twitter.com/user/status/STATUS_ID
     * - https://x.com/user/status/STATUS_ID
     *
     * @param string $hostName Domain name (twitter.com or x.com)
     * @param string $urlPath URL path component
     * @return string|null Status ID or null if not found
     */
    private function getTwitterStatusId(string $hostName, string $urlPath): ?string
    {
        $isTwitterHost = str_contains($hostName, 'twitter.com') || str_contains($hostName, 'x.com');
        if (!$isTwitterHost) {
            return null;
        }

        // Format: /status/STATUS_ID
        if (preg_match('#/status/(\d+)#', $urlPath, $idMatch)) {
            return $idMatch[1];
        }

        return null;
    }

    /**
     * Build Twitch embed URL with parent parameters
     *
     * SUPPORTED FORMATS:
     * - https://www.twitch.tv/videos/VIDEO_ID
     * - https://www.twitch.tv/USERNAME/clip/CLIP_ID
     *
     * NOTE: Twitch requires parent parameter for embeds to work
     *
     * @param string $hostName Domain name (twitch.tv)
     * @param string $urlPath URL path component
     * @return string|null Embed URL or null if not found
     */
    private function getTwitchEmbedUrl(string $hostName, string $urlPath): ?string
    {
        if (!str_contains($hostName, 'twitch.tv')) {
            return null;
        }

        // Format 1: /videos/VIDEO_ID
        if (preg_match('#/videos/(\d+)#', $urlPath, $idMatch)) {
            $videoId = $idMatch[1];
            return 'https://player.twitch.tv/?video=' . $videoId . '&parent=localhost&parent=127.0.0.1';
        }

        // Format 2: /clip/CLIP_ID
        if (preg_match('#/clip/([A-Za-z0-9_-]+)#', $urlPath, $idMatch)) {
            $clipId = $idMatch[1];
            return 'https://clips.twitch.tv/embed?clip=' . $clipId . '&parent=localhost&parent=127.0.0.1';
        }

        return null;
    }

    /**
     * Build Facebook video embed URL
     *
     * SUPPORTED FORMATS:
     * - https://www.facebook.com/user/videos/VIDEO_ID
     * - https://www.facebook.com/watch?v=VIDEO_ID
     * - https://fb.watch/VIDEO_ID
     *
     * @param string $hostName Domain name (facebook.com or fb.watch)
     * @param string $urlPath URL path component
     * @param string $sourceUrl Full source URL (needed for embed parameter)
     * @return string|null Embed URL or null if not found
     */
    private function getFacebookEmbedUrl(string $hostName, string $urlPath, string $sourceUrl): ?string
    {
        if (!str_contains($hostName, 'facebook.com') && !str_contains($hostName, 'fb.watch')) {
            return null;
        }

        // Check for video URLs
        if (preg_match('#/videos/(\d+)#', $urlPath) || str_contains($sourceUrl, 'watch')) {
            $encodedUrl = rawurlencode($sourceUrl);
            return 'https://www.facebook.com/plugins/video.php?href=' . $encodedUrl;
        }

        // Check for short links (fb.watch)
        if (str_contains($hostName, 'fb.watch')) {
            $encodedUrl = rawurlencode($sourceUrl);
            return 'https://www.facebook.com/plugins/video.php?href=' . $encodedUrl;
        }

        return null;
    }

    /**
     * Extract Dailymotion video ID from URL
     *
     * SUPPORTED FORMATS:
     * - https://www.dailymotion.com/video/VIDEO_ID
     * - https://dai.ly/VIDEO_ID
     *
     * @param string $hostName Domain name (dailymotion.com or dai.ly)
     * @param string $urlPath URL path component
     * @return string|null Video ID or null if not found
     */
    private function getDailymotionId(string $hostName, string $urlPath): ?string
    {
        if (!str_contains($hostName, 'dailymotion.com') && !str_contains($hostName, 'dai.ly')) {
            return null;
        }

        // Format 1: dai.ly/VIDEO_ID (short link)
        if (str_contains($hostName, 'dai.ly') && preg_match('#^/([A-Za-z0-9]+)#', $urlPath, $idMatch)) {
            return $idMatch[1];
        }

        // Format 2: /video/VIDEO_ID
        if (preg_match('#/video/([A-Za-z0-9]+)#', $urlPath, $idMatch)) {
            return $idMatch[1];
        }

        return null;
    }

    /**
     * Extract Loom video ID from URL
     *
     * SUPPORTED FORMATS:
     * - https://www.loom.com/share/VIDEO_ID
     * - https://www.loom.com/embed/VIDEO_ID
     *
     * @param string $hostName Domain name (loom.com)
     * @param string $urlPath URL path component
     * @return string|null Video ID or null if not found
     */
    private function getLoomId(string $hostName, string $urlPath): ?string
    {
        if (!str_contains($hostName, 'loom.com')) {
            return null;
        }

        // Format 1: /share/VIDEO_ID
        if (preg_match('#/share/([A-Za-z0-9]+)#', $urlPath, $idMatch)) {
            return $idMatch[1];
        }

        // Format 2: /embed/VIDEO_ID
        if (preg_match('#/embed/([A-Za-z0-9]+)#', $urlPath, $idMatch)) {
            return $idMatch[1];
        }

        return null;
    }

    /**
     * Extract Wistia video ID from URL
     *
     * SUPPORTED FORMATS:
     * - https://company.wistia.com/medias/VIDEO_ID
     * - https://fast.wistia.net/embed/iframe/VIDEO_ID
     * - https://wi.st/VIDEO_ID
     *
     * @param string $hostName Domain name (wistia.com or wi.st)
     * @param string $urlPath URL path component
     * @return string|null Video ID or null if not found
     */
    private function getWistiaId(string $hostName, string $urlPath): ?string
    {
        if (!str_contains($hostName, 'wistia.com') && !str_contains($hostName, 'wi.st')) {
            return null;
        }

        // Format 1: /medias/VIDEO_ID
        if (preg_match('#/medias/([A-Za-z0-9]+)#', $urlPath, $idMatch)) {
            return $idMatch[1];
        }

        // Format 2: /embed/iframe/VIDEO_ID
        if (preg_match('#/embed/iframe/([A-Za-z0-9]+)#', $urlPath, $idMatch)) {
            return $idMatch[1];
        }

        return null;
    }
}
