<?php

namespace Flint;

/**
 * HookManager
 *
 * Central event-based hooks system for component integration.
 * Allows components to register callbacks at specific lifecycle points
 * without tight coupling to core application code.
 *
 * Hook Points:
 * - request_start: Before any processing (context: path, method, ip)
 * - before_routing: After validation, before routing (context: path, method)
 * - form_submit: When form is submitted (context: form_type, data)
 * - form_token_generate: Request form token (context: form_type)
 * - form_validate: Validate form submission (context: form_type, data)
 * - 404_render: Before 404 page renders (context: path, theme)
 * - admin_panel_load: Admin interface loading (context: tab)
 * - response_send: Before sending response (context: status, headers)
 * - theme_styles: After theme assets are inserted into <head>
 * - theme_scripts: Before closing </body>, for footer scripts
 */
class HookManager
{
    /** @var array Hook registry: [event => [priority => [callbacks]]] */
    private static array $hooks = [];

    /** @var array Loaded components */
    private static array $components = [];

    /** @var bool Whether components have been initialized */
    private static bool $initialized = false;

    /** @var App Application instance */
    private static ?App $app = null;

    /**
     * Initialize the hook system and discover components
     *
     * @param App $app Application instance
     */
    public static function init(App $app): void
    {
        if (self::$initialized) {
            return;
        }

        self::$app = $app;
        self::$initialized = true;

        // Discover and load enabled components
        self::loadComponents();
    }

    /**
     * Discover and initialize components from site/components directory
     */
    private static function loadComponents(): void
    {
        // Load site components from site/components
        $siteComponentsDir = self::$app->root . '/site/components';
        self::scanComponentDirectory($siteComponentsDir, 'Components');

        // Load core components from app/core/components
        $coreComponentsDir = self::$app->appDir . '/core/components';
        self::scanComponentDirectory($coreComponentsDir, 'Flint\\Components');
    }

    /**
     * Scan a directory for components and load enabled ones
     *
     * @param string $componentsDir Directory to scan
     * @param string $namespace Namespace prefix for components
     */
    private static function scanComponentDirectory(string $componentsDir, string $namespace): void
    {
        if (!is_dir($componentsDir)) {
            return;
        }

        // Scan for component directories
        $directories = array_filter(
            glob($componentsDir . '/*'),
            'is_dir'
        );

        foreach ($directories as $componentDir) {
            $componentName = basename($componentDir);
            $configPath = $componentDir . '/config.php';
            $componentClass = $componentDir . '/' . $componentName . '.php';

            // Skip if no config.php exists
            if (!file_exists($configPath)) {
                continue;
            }

            // Parse component config
            $config = require $configPath;

            // Check if component is enabled
            if (!isset($config['component']['enabled']) || $config['component']['enabled'] !== true) {
                continue;
            }

            // Check if component class exists
            if (!file_exists($componentClass)) {
                continue;
            }

            // Load component class
            require_once $componentClass;

            $className = "\\{$namespace}\\{$componentName}\\{$componentName}";

            if (!class_exists($className)) {
                continue;
            }

            // Check if component implements ComponentInterface
            if (!in_array('Flint\\ComponentInterface', class_implements($className) ?: [])) {
                continue;
            }

            // Initialize component - it will register its own hooks
            $className::init(self::class, $config);

            self::$components[$componentName] = [
                'class' => $className,
                'config' => $config,
                'priority' => $config['component']['priority'] ?? 100,
                'location' => $componentsDir
            ];
        }
    }

    /**
     * Register a hook callback for a specific event
     *
     * @param string $event Event name (e.g., 'request_start', 'form_submit')
     * @param callable $callback Callback function to execute
     * @param int $priority Priority (lower = earlier execution, default 100)
     */
    public static function on(string $event, callable $callback, int $priority = 100): void
    {
        if (!isset(self::$hooks[$event])) {
            self::$hooks[$event] = [];
        }

        if (!isset(self::$hooks[$event][$priority])) {
            self::$hooks[$event][$priority] = [];
        }

        self::$hooks[$event][$priority][] = $callback;

        // Sort by priority (ascending) to ensure correct execution order
        ksort(self::$hooks[$event]);
    }

    /**
     * Trigger a hook event and execute all registered callbacks
     *
     * @param string $event Event name
     * @param array $context Context data passed to callbacks
     * @return mixed Last callback return value, or null
     */
    public static function trigger(string $event, array $context = []): mixed
    {
        if (!isset(self::$hooks[$event])) {
            return null;
        }

        $result = null;

        // Execute callbacks in priority order
        foreach (self::$hooks[$event] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $callbackResult = $callback($context);

                // If callback returns non-null, update result
                if ($callbackResult !== null) {
                    $result = $callbackResult;
                }

                // If callback returns false, stop propagation
                if ($callbackResult === false) {
                    return false;
                }
            }
        }

        return $result;
    }

    /**
     * Check if a hook has any registered callbacks
     *
     * @param string $event Event name
     * @return bool True if hooks are registered
     */
    public static function has(string $event): bool
    {
        return isset(self::$hooks[$event]) && !empty(self::$hooks[$event]);
    }

    /**
     * Get list of loaded components
     *
     * @return array Component information
     */
    public static function getComponents(): array
    {
        return self::$components;
    }

    /**
     * Get application instance
     *
     * @return App|null Application instance
     */
    public static function getApp(): ?App
    {
        return self::$app;
    }

    /**
     * Reset hook system (primarily for testing)
     */
    public static function reset(): void
    {
        self::$hooks = [];
        self::$components = [];
        self::$initialized = false;
        self::$app = null;
    }
}
