<?php

namespace Flint;

/**
 * Render Component Base Class
 *
 * This is the foundation for stateless rendering components like Hero, Alert,
 * Callout, etc. These components don't interact with the application lifecycle -
 * they simply transform props and content into HTML.
 *
 * RENDER COMPONENT vs BASE COMPONENT:
 *
 * RenderComponent (this class):
 * - Stateless - no lifecycle, no hooks, no persistence
 * - Used for: UI components (Hero, Alert, Button, Card, etc.)
 * - Example: <Hero title="Welcome">Content here</Hero>
 * - No init(), no hooks, just render()
 *
 * BaseComponent:
 * - Stateful - has lifecycle, registers hooks, can persist data
 * - Used for: Functionality (Backups, Analytics, Security, etc.)
 * - Example: Component that runs every hour to create backups
 * - Has init(), onInit(), registerHooks()
 *
 * PROPS SYSTEM (similar to React):
 * Props are attributes passed to components in markdown:
 * <Alert type="warning" title="Important!">Message here</Alert>
 *
 * Translates to PHP:
 * $props = ['type' => 'warning', 'title' => 'Important!']
 * $content = 'Message here'
 *
 * WHY STATIC?
 * - No state to maintain (stateless rendering)
 * - No object instantiation overhead
 * - Simple API for parser to call
 * - Follows functional programming paradigm (input → output)
 *
 * SECURITY:
 * All helper methods properly escape output to prevent XSS attacks.
 * Use self::escape() for any user-provided text that goes in HTML.
 *
 * @package Flint
 * @subpackage Core
 */
abstract class RenderComponent
{
    /**
     * Render the component to HTML
     *
     * This is the only method components MUST implement.
     * It receives props (attributes) and content (inner markdown/HTML)
     * and returns rendered HTML.
     *
     * EXAMPLE IMPLEMENTATION:
     * ```php
     * public static function render(array $props, string $content): string
     * {
     *     $title = self::prop($props, 'title', 'Default Title');
     *     $type = self::prop($props, 'type', 'info');
     *
     *     return '<div class="alert alert-' . self::escape($type) . '">' .
     *            '<h3>' . self::escape($title) . '</h3>' .
     *            '<p>' . $content . '</p>' .
     *            '</div>';
     * }
     * ```
     *
     * IMPORTANT:
     * - $props is an associative array of attributes
     * - $content is already processed by markdown parser
     * - Always escape user-provided values with self::escape()
     * - $content from parser is usually safe (already processed)
     *
     * @param array $props Component properties/attributes
     * @param string $content Component inner content (parsed markdown)
     * @return string Rendered HTML
     */
    abstract public static function render(array $props, string $content): string;

    /**
     * Declare component asset dependencies
     *
     * Override this method if your component needs external CSS/JavaScript.
     * Assets are collected by the parser and injected into the page layout.
     *
     * RETURN FORMAT:
     * ```php
     * return [
     *     // External JavaScript files
     *     'scripts' => [
     *         ['src' => '/path/to/script.js', 'position' => 'footer'],
     *         ['src' => 'https://cdn.example.com/lib.js', 'position' => 'head']
     *     ],
     *
     *     // Inline JavaScript code
     *     'inline_scripts' => [
     *         ['content' => 'console.log("Hello");', 'position' => 'footer']
     *     ],
     *
     *     // External CSS files
     *     'styles' => [
     *         ['href' => '/path/to/styles.css']
     *     ],
     *
     *     // Inline CSS code
     *     'inline_styles' => [
     *         ['content' => '.custom { color: red; }']
     *     ]
     * ];
     * ```
     *
     * POSITION:
     * - 'head': Load in <head> (blocking - delays page render)
     * - 'footer': Load at end of <body> (default - better performance)
     *
     * USE CASES:
     * - Charts component needs Chart.js library
     * - Map component needs Google Maps API
     * - Interactive component needs custom JavaScript
     *
     * @return array Assets configuration
     */
    public static function getAssets(): array
    {
        // Default: no assets needed
        return [];
    }

    /**
     * Escape HTML special characters
     *
     * SECURITY: This prevents XSS (Cross-Site Scripting) attacks.
     *
     * WHAT IT DOES:
     * - & becomes &amp;
     * - < becomes &lt;
     * - > becomes &gt;
     * - " becomes &quot;
     * - ' becomes &#039;
     *
     * XSS ATTACK EXAMPLE:
     * Without escaping:
     *   $title = '<script>alert("XSS")</script>';
     *   echo '<h1>' . $title . '</h1>';
     *   // Result: <h1><script>alert("XSS")</script></h1> (script executes!)
     *
     * With escaping:
     *   echo '<h1>' . self::escape($title) . '</h1>';
     *   // Result: <h1>&lt;script&gt;alert("XSS")&lt;/script&gt;</h1> (safe text)
     *
     * WHEN TO USE:
     * - Always escape user-provided data
     * - Always escape props (title, name, etc.)
     * - Don't escape HTML you intentionally created
     *
     * FLAGS:
     * - ENT_QUOTES: Escape both double and single quotes
     * - UTF-8: Handle international characters correctly
     *
     * @param string $text Text to escape
     * @return string HTML-safe text
     */
    protected static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get prop value with default fallback
     *
     * This is a convenience method for safe prop access.
     *
     * EXAMPLE:
     * ```php
     * // Without helper (prone to errors):
     * $title = isset($props['title']) ? $props['title'] : 'Default';
     *
     * // With helper (clean):
     * $title = self::prop($props, 'title', 'Default');
     * ```
     *
     * NULL COALESCE OPERATOR (??):
     * Returns left side if it exists and is not null, otherwise right side.
     * $props['title'] ?? 'Default' means:
     * - If $props['title'] exists and is not null → return it
     * - Otherwise → return 'Default'
     *
     * @param array $props Properties array
     * @param string $key Property key to retrieve
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Property value or default
     */
    protected static function prop(array $props, string $key, mixed $default = ''): mixed
    {
        return $props[$key] ?? $default;
    }

    /**
     * Check if prop is truthy
     *
     * BOOLEAN PROPS:
     * In markdown, boolean props work like HTML:
     * - <Alert dismissible> → dismissible is true
     * - <Alert dismissible="false"> → dismissible is false
     * - <Alert> → dismissible doesn't exist (false)
     *
     * This method returns true only if:
     * 1. Prop exists in array
     * 2. Prop is not the string 'false'
     * 3. Prop is not the boolean false
     *
     * EXAMPLE:
     * ```php
     * $props1 = ['dismissible' => 'true'];   // propBool returns true
     * $props2 = ['dismissible' => true];     // propBool returns true
     * $props3 = ['dismissible' => 'false'];  // propBool returns false
     * $props4 = ['dismissible' => false];    // propBool returns false
     * $props5 = [];                          // propBool returns false
     * ```
     *
     * USE CASE:
     * ```php
     * if (self::propBool($props, 'dismissible')) {
     *     // Add close button
     * }
     * ```
     *
     * @param array $props Properties array
     * @param string $key Property key to check
     * @return bool True if prop exists and is truthy
     */
    protected static function propBool(array $props, string $key): bool
    {
        // Check if prop exists
        // AND prop is not the string 'false'
        // AND prop is not the boolean false
        return isset($props[$key]) && $props[$key] !== 'false' && $props[$key] !== false;
    }

    /**
     * Get content or fallback to prop
     *
     * COMMON PATTERN:
     * Components often accept content in two ways:
     * 1. As inner content: <Alert>This is the message</Alert>
     * 2. As a prop: <Alert message="This is the message" />
     *
     * This method checks content first, then falls back to prop.
     *
     * EXAMPLE:
     * ```php
     * // Usage in component:
     * $message = self::contentOrProp($content, $props, 'message');
     *
     * // Works with both:
     * <Alert>Hello World</Alert>           → $message = "Hello World"
     * <Alert message="Hello World" />      → $message = "Hello World"
     * <Alert message="From Prop">From Content</Alert> → $message = "From Content"
     * ```
     *
     * WHY?
     * Some users prefer self-closing tags with props, others prefer content.
     * This makes components flexible.
     *
     * @param string $content Component inner content
     * @param array $props Component properties
     * @param string $propKey Property key to check as fallback
     * @return string Content or prop value (whichever is non-empty)
     */
    protected static function contentOrProp(string $content, array $props, string $propKey): string
    {
        // Trim whitespace from content
        $trimmedContent = trim($content);

        // If content exists, use it; otherwise use prop
        return $trimmedContent !== '' ? $trimmedContent : trim($props[$propKey] ?? '');
    }

    /**
     * Parse list items from text
     *
     * Supports two delimiter formats:
     * - Newline-delimited: "Item 1\nItem 2\nItem 3"
     * - Semicolon-delimited: "Item 1; Item 2; Item 3"
     *
     * EXAMPLE INPUT:
     * ```
     * Features:
     * - Fast rendering
     * - Easy to use
     * - Secure by default
     * ```
     *
     * EXAMPLE OUTPUT:
     * ['Fast rendering', 'Easy to use', 'Secure by default']
     *
     * USE CASE:
     * ```php
     * // Component definition:
     * <Features list="Fast\nEasy\nSecure" />
     *
     * // In component:
     * $items = self::parseList($props['list']);
     * foreach ($items as $item) {
     *     echo '<li>' . self::escape($item) . '</li>';
     * }
     * ```
     *
     * REGEX EXPLANATION:
     * /\r?\n|;/
     * - \r?\n: Matches newline (Unix \n or Windows \r\n)
     * - |: OR operator
     * - ;: Matches semicolon
     *
     * @param string $text Text containing list items
     * @return array Parsed and trimmed items (empty items removed)
     */
    protected static function parseList(string $text): array
    {
        // Return empty array if input is empty
        if (trim($text) === '') {
            return [];
        }

        // Split on newlines or semicolons
        $rawItems = preg_split('/\r?\n|;/', $text);
        $parsedItems = [];

        // Trim each item and filter out empty ones
        foreach ($rawItems as $item) {
            $trimmedItem = trim($item);
            if ($trimmedItem !== '') {
                $parsedItems[] = $trimmedItem;
            }
        }

        return $parsedItems;
    }

    /**
     * Parse key-value pairs from text
     *
     * Supports custom delimiters to separate key from value.
     *
     * EXAMPLE INPUT (delimiter="|"):
     * ```
     * Name|John Doe
     * Email|john@example.com
     * Role|Administrator
     * ```
     *
     * EXAMPLE OUTPUT:
     * [
     *   ['key' => 'Name', 'value' => 'John Doe'],
     *   ['key' => 'Email', 'value' => 'john@example.com'],
     *   ['key' => 'Role', 'value' => 'Administrator']
     * ]
     *
     * USE CASE - Definition List Component:
     * ```php
     * <DefinitionList items="Term 1|Definition 1\nTerm 2|Definition 2" />
     *
     * $items = self::parseKeyValue($props['items'], '|');
     * echo '<dl>';
     * foreach ($items as $item) {
     *     echo '<dt>' . self::escape($item['key']) . '</dt>';
     *     echo '<dd>' . self::escape($item['value']) . '</dd>';
     * }
     * echo '</dl>';
     * ```
     *
     * DELIMITER OPTIONS:
     * - "|" (pipe): Most common, easy to type
     * - "::" (double colon): Good for URLs that contain pipe
     * - "=" (equals): Looks like assignment
     * - "→" (arrow): Visually clear but harder to type
     *
     * @param string $text Text containing key-value pairs
     * @param string $delimiter Character(s) separating key from value
     * @return array Array of ['key' => ..., 'value' => ...] items
     */
    protected static function parseKeyValue(string $text, string $delimiter = '|'): array
    {
        // First, split into individual lines using parseList
        $lines = self::parseList($text);
        $parsedPairs = [];

        // Process each line
        foreach ($lines as $line) {
            // Skip lines that don't contain the delimiter
            if (!str_contains($line, $delimiter)) {
                continue;
            }

            // Split on delimiter (limit to 2 parts in case value contains delimiter)
            $parts = explode($delimiter, $line, 2);
            $key = trim($parts[0]);
            $value = trim($parts[1]);

            // Only add if both key and value are non-empty
            if ($key !== '' && $value !== '') {
                $parsedPairs[] = ['key' => $key, 'value' => $value];
            }
        }

        return $parsedPairs;
    }

    /**
     * Parse markdown link format
     *
     * Extracts label and URL from markdown link syntax: [Label](URL)
     *
     * EXAMPLE INPUTS:
     * - "[Click Here](https://example.com)" → ['label' => 'Click Here', 'url' => 'https://example.com']
     * - "[Home](/)" → ['label' => 'Home', 'url' => '/']
     * - "Not a link" → null
     *
     * USE CASE:
     * ```php
     * // Component that accepts a link prop:
     * <Button link="[Visit Site](https://example.com)" />
     *
     * // In component:
     * $linkData = self::parseMarkdownLink($props['link']);
     * if ($linkData) {
     *     echo '<a href="' . self::escape($linkData['url']) . '">';
     *     echo self::escape($linkData['label']);
     *     echo '</a>';
     * }
     * ```
     *
     * REGEX EXPLANATION:
     * /^\[(.+)\]\((.+)\)$/
     * - ^: Start of string
     * - \[: Literal opening bracket
     * - (.+): Capture one or more characters (label)
     * - \]: Literal closing bracket
     * - \(: Literal opening parenthesis
     * - (.+): Capture one or more characters (URL)
     * - \): Literal closing parenthesis
     * - $: End of string
     *
     * @param string $text Text to parse
     * @return array|null ['label' => ..., 'url' => ...] or null if not a link
     */
    protected static function parseMarkdownLink(string $text): ?array
    {
        // Try to match markdown link pattern
        if (preg_match('/^\[(.+)\]\((.+)\)$/', $text, $match)) {
            return [
                'label' => trim($match[1]),
                'url' => trim($match[2])
            ];
        }

        // Not a markdown link
        return null;
    }

    /**
     * Get current Application instance
     *
     * This allows render components to access application context
     * (paths, config, utilities) when needed.
     *
     * HOW IT WORKS:
     * 1. Parser stores itself as "current instance" during parsing
     * 2. Components call this method to get parser
     * 3. Parser provides access to Application
     *
     * WHEN TO USE:
     * - Reading files from content directory
     * - Accessing configuration
     * - Getting path information
     *
     * EXAMPLE:
     * ```php
     * $app = self::getApp();
     * if ($app) {
     *     $imagePath = $app->root . '/content/images/logo.png';
     *     if (file_exists($imagePath)) {
     *         // Use image
     *     }
     * }
     * ```
     *
     * NULL SAFETY:
     * Returns null if called outside of parsing context.
     * Always check for null before using.
     *
     * NULLSAFE OPERATOR (?.):
     * $parser?->getApplication() means:
     * - If $parser is null → return null
     * - Otherwise → call getApplication()
     *
     * @return App|null Application instance or null if not available
     */
    protected static function getApp(): ?App
    {
        // Get current parser instance
        $currentParser = \Flint\Parser::getCurrentInstance();

        // Get application from parser (null-safe)
        return $currentParser?->getApplication();
    }

    /**
     * Build HTML attributes string from array
     *
     * Converts associative array into HTML attribute string.
     *
     * EXAMPLE:
     * ```php
     * $attrs = [
     *     'id' => 'my-div',
     *     'class' => 'container large',
     *     'data-value' => '123',
     *     'disabled' => true,
     *     'hidden' => false
     * ];
     * echo '<div ' . self::buildAttributes($attrs) . '>';
     * // Result: <div id="my-div" class="container large" data-value="123" disabled>
     * ```
     *
     * BOOLEAN ATTRIBUTES:
     * - true: Attribute appears without value (e.g., "disabled")
     * - false/null: Attribute is omitted entirely
     *
     * HTML BOOLEAN ATTRIBUTES:
     * - disabled, readonly, required, checked, selected
     * - Their presence means true, absence means false
     * - HTML5 allows value-less syntax: <input disabled> (not <input disabled="disabled">)
     *
     * SECURITY:
     * Both keys and values are escaped to prevent attribute injection attacks.
     *
     * ATTRIBUTE INJECTION EXAMPLE (prevented by escaping):
     * Without escaping:
     *   $attrs = ['title' => '" onclick="alert(\'XSS\')'];
     *   // Result: title="" onclick="alert('XSS')" (BAD!)
     *
     * With escaping:
     *   // Result: title="&quot; onclick=&quot;alert('XSS')" (SAFE!)
     *
     * @param array $attributes Attribute name => value pairs
     * @return string Space-separated HTML attributes
     */
    protected static function buildAttributes(array $attributes): string
    {
        $attributeParts = [];

        // Process each attribute
        foreach ($attributes as $attributeName => $attributeValue) {
            if ($attributeValue === true) {
                // Boolean true: Add attribute without value
                // Example: disabled, readonly, required
                $attributeParts[] = self::escape($attributeName);
            } elseif ($attributeValue !== false && $attributeValue !== null) {
                // Normal attribute: Add name="value"
                // Convert value to string and escape both name and value
                $escapedName = self::escape($attributeName);
                $escapedValue = self::escape((string)$attributeValue);
                $attributeParts[] = $escapedName . '="' . $escapedValue . '"';
            }
            // false/null values are omitted (no output)
        }

        // Join all attributes with spaces
        return implode(' ', $attributeParts);
    }
}
