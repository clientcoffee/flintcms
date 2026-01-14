<?php

namespace Flint;

/**
 * Base Component Class
 *
 * This is the foundation for all Flint components. Components are modular pieces
 * of functionality that extend Flint with new features (like backups, analytics,
 * security features, etc.).
 *
 * COMPONENT LIFECYCLE:
 * 1. Component is discovered by HookManager
 * 2. init() is called with hook manager and config
 * 3. onInit() is called for component-specific setup
 * 4. registerHooks() is called to register event listeners
 * 5. Component responds to events during request lifecycle
 *
 * WHY ABSTRACT BASE CLASS?
 * - Reduces boilerplate code in components
 * - Provides consistent API across all components
 * - Encapsulates common patterns (logging, config access, storage, etc.)
 * - Makes component development faster and easier
 *
 * WHY STATIC?
 * Components use static methods because:
 * - No need for object instantiation (reduces memory)
 * - Simple lifecycle (no constructor/destructor complexity)
 * - Easy to call from hooks (no need to pass instance around)
 * - Static late binding (static::) allows inheritance to work correctly
 *
 * STATIC LATE BINDING (static:: vs self::):
 * - self:: refers to the class where it's written (BaseComponent)
 * - static:: refers to the class that was called (e.g., Backups, Defense)
 * - This allows BaseComponent methods to work for all child classes
 *
 * @package Flint
 * @subpackage Core
 */
abstract class BaseComponent implements ComponentInterface
{
    /**
     * Reference to the main application instance.
     * Provides access to paths, config, and utilities.
     *
     * NOTE: Protected (not private) so child classes can access it.
     *
     * @var App|null
     */
    protected static ?App $app = null;

    /**
     * Component configuration from config.php
     *
     * Structure:
     * [
     *   'component' => ['name' => '...', 'version' => '...', 'enabled' => true],
     *   'settings' => ['component_specific_settings' => 'value']
     * ]
     *
     * @var array
     */
    protected static array $config = [];

    /**
     * Fully qualified class name of the HookManager.
     * Used to register event listeners and access the application.
     *
     * Example: 'Flint\\HookManager'
     *
     * @var string
     */
    protected static string $hookManager = '';

    /**
     * Initialize the component
     *
     * Called by HookManager when component is loaded. This method:
     * 1. Stores the hook manager reference (for registering hooks)
     * 2. Stores the component config (from config.php)
     * 3. Gets application reference (for paths, utilities, etc.)
     * 4. Calls onInit() for component-specific setup
     * 5. Calls registerHooks() to register event listeners
     *
     * COMPONENT DEVELOPERS:
     * You don't need to override this method. Instead:
     * - Override onInit() for one-time setup (create directories, etc.)
     * - Override registerHooks() to register your event listeners
     *
     * @param string $hookManager Fully qualified HookManager class name
     * @param array $config Component configuration from config.php
     */
    public static function init(string $hookManager, array $config): void
    {
        // Store hook manager reference so we can register hooks later
        static::$hookManager = $hookManager;

        // Store configuration for this component
        static::$config = $config;

        // Get application instance from hook manager
        // This gives us access to paths, config, and utilities
        static::$app = $hookManager::getApp();

        // Call component-specific initialization
        // Override onInit() in your component to perform setup
        static::onInit();

        // Register component's event listeners
        // Components MUST implement registerHooks() to define their behavior
        static::registerHooks();
    }

    /**
     * Get component metadata
     *
     * Returns information about this component from config.php.
     * Used by admin interface to display component information.
     *
     * CONFIG FILE FORMAT:
     * [
     *   'component' => [
     *     'name' => 'My Component',
     *     'version' => '1.0.0',
     *     'author' => 'Developer Name',
     *     'description' => 'What this component does'
     *   ]
     * ]
     *
     * @return array Associative array with keys: name, version, author, description
     */
    public static function getInfo(): array
    {
        return [
            // Component name (falls back to class name if not in config)
            'name' => static::$config['component']['name'] ?? static::getComponentName(),

            // Component version (falls back to 1.0.0 if not in config)
            'version' => static::$config['component']['version'] ?? '1.0.0',

            // Component author (falls back to 'Unknown' if not in config)
            'author' => static::$config['component']['author'] ?? 'Unknown',

            // Component description (falls back to empty string if not in config)
            'description' => static::$config['component']['description'] ?? ''
        ];
    }

    /**
     * Extract component name from class name
     *
     * EXAMPLE:
     * - Class: \Components\Backups → Returns: "Backups"
     * - Class: \Components\GoogleAnalytics → Returns: "GoogleAnalytics"
     *
     * This is used as a fallback when component config doesn't specify a name.
     *
     * @return string Component name (last part of class name)
     */
    protected static function getComponentName(): string
    {
        // Get the full class name including namespace
        // Example: "Components\Backups"
        $fullClassName = static::class;

        // Split on namespace separator
        // ['Components', 'Backups']
        $namespaceparts = explode('\\', $fullClassName);

        // Return the last part (the class name without namespace)
        // "Backups"
        return end($namespaceparts);
    }

    /**
     * Get component priority for hook execution order
     *
     * PRIORITY SYSTEM:
     * - Lower numbers = earlier execution
     * - Default = 100 (middle priority)
     * - Core functionality uses 0-50
     * - Most components use 100
     * - Post-processing uses 150-200
     *
     * EXAMPLE USE CASE:
     * A security component might use priority 10 (early) to block requests
     * before other components run. An analytics component might use priority
     * 150 (late) to track data after other components have modified it.
     *
     * @return int Priority value (lower = earlier)
     */
    protected static function getPriority(): int
    {
        // Read priority from component config, default to 100
        return (int)(static::$config['component']['priority'] ?? 100);
    }

    /**
     * Register an event listener (hook)
     *
     * This is a helper method that simplifies hook registration.
     * It automatically uses the component's configured priority.
     *
     * EVENT SYSTEM:
     * Flint uses an event-driven architecture. Components listen for events
     * (like 'page_render', 'form_submit', etc.) and respond with callbacks.
     *
     * EXAMPLE USAGE:
     * ```php
     * static::registerHook('page_render', function($html) {
     *     // Modify page HTML
     *     return $html . '<script>console.log("Hello");</script>';
     * });
     * ```
     *
     * @param string $eventName Event name to listen for (e.g., 'page_render')
     * @param callable $callbackFunction Function to call when event fires
     * @param int|null $priorityOverride Optional priority (overrides component default)
     */
    protected static function registerHook(string $eventName, callable $callbackFunction, ?int $priorityOverride = null): void
    {
        // Use override priority if provided, otherwise use component's default
        $effectivePriority = $priorityOverride ?? static::getPriority();

        // Register the hook with HookManager
        // static::$hookManager is the HookManager class name (string)
        // We call its static on() method to register the hook
        static::$hookManager::on($eventName, $callbackFunction, $effectivePriority);
    }

    /**
     * Friendly alias for registerHook().
     */
    protected static function register_hook(string $eventName, callable $callbackFunction, ?int $priorityOverride = null): void
    {
        static::registerHook($eventName, $callbackFunction, $priorityOverride);
    }

    /**
     * Get configuration value with dot notation support
     *
     * DOT NOTATION:
     * Allows nested config access with a simple string.
     *
     * EXAMPLES:
     * - "component.name" → $config['component']['name']
     * - "settings.api_key" → $config['settings']['api_key']
     * - "settings.backup.retention_days" → $config['settings']['backup']['retention_days']
     *
     * WHY DOT NOTATION?
     * - Cleaner code: getConfig('settings.api_key') vs $config['settings']['api_key']
     * - Safe access: Returns default if any key in path doesn't exist
     * - Consistent API: All components use same pattern
     *
     * @param string $keyPath Configuration key (supports dot notation)
     * @param mixed $defaultValue Default value if key not found
     * @return mixed Configuration value or default
     */
    protected static function getConfig(string $keyPath, mixed $defaultValue = null): mixed
    {
        // Split key path on dots
        // "settings.api_key" → ["settings", "api_key"]
        $keySegments = explode('.', $keyPath);

        // Start with full config array
        $currentValue = static::$config;

        // Walk down the path
        foreach ($keySegments as $keySegment) {
            // Check if key exists at this level
            if (!isset($currentValue[$keySegment])) {
                // Key not found, return default
                return $defaultValue;
            }

            // Move deeper into the array
            $currentValue = $currentValue[$keySegment];
        }

        // Successfully traversed entire path, return value
        return $currentValue;
    }

    /**
     * Friendly alias for getConfig().
     */
    protected static function get_config(string $keyPath, mixed $defaultValue = null): mixed
    {
        return static::getConfig($keyPath, $defaultValue);
    }

    /**
     * Component-specific initialization hook
     *
     * Override this method in your component to perform one-time setup tasks.
     *
     * COMMON USE CASES:
     * - Create required directories (logs, storage, cache)
     * - Initialize database connections
     * - Load external resources
     * - Validate configuration
     * - Set up scheduled tasks
     *
     * EXAMPLE:
     * ```php
     * protected static function onInit(): void
     * {
     *     // Create storage directory for this component
     *     static::ensureStorageDir(static::$app->root . '/storage/backups');
     *
     *     // Validate required config
     *     if (empty(static::getConfig('settings.api_key'))) {
     *         static::log('API key not configured', 'warning');
     *     }
     * }
     * ```
     *
     * NOTE: This is called BEFORE registerHooks(), so you can set up
     * resources that your hooks will need.
     */
    protected static function onInit(): void
    {
        // Override in subclass if needed
        // Default implementation does nothing
    }

    /**
     * Register component-specific hooks
     *
     * Components MUST implement this method to define their behavior.
     * Use the registerHook() helper method to register event listeners.
     *
     * AVAILABLE EVENTS (common ones):
     * - page_render: Modify HTML before sending to browser
     * - page_data: Modify page data structure
     * - form_submit: Handle form submissions
     * - api_request: Handle API endpoints
     * - admin_render: Add content to admin interface
     * - cron_hourly: Run scheduled tasks every hour
     * - cron_daily: Run scheduled tasks every day
     *
     * EXAMPLE:
     * ```php
     * protected static function registerHooks(): void
     * {
     *     // Add analytics tracking code to all pages
     *     static::registerHook('page_render', function($html) {
     *         return $html . '<script>/* analytics code *\/</script>';
     *     });
     *
     *     // Handle API endpoint
     *     static::registerHook('api_request', function($endpoint, $method) {
     *         if ($endpoint === '/api/my-component' && $method === 'POST') {
     *             // Handle request
     *             return ['success' => true];
     *         }
     *         return null; // Not handled by this component
     *     });
     * }
     * ```
     */
    abstract protected static function registerHooks(): void;

    /**
     * Ensure a directory exists, creating it if necessary
     *
     * SECURITY: Creates directories with secure permissions (0755 by default).
     *
     * PERMISSIONS EXPLAINED:
     * - 0755 = rwxr-xr-x
     *   - Owner (you): read, write, execute
     *   - Group: read, execute
     *   - Others: read, execute
     *
     * WHY 0755?
     * - Owner can create/delete files in directory (write)
     * - Others can list and access files (read/execute)
     * - Others cannot create files (no write)
     * - Standard for web server directories
     *
     * RECURSIVE CREATION:
     * The third parameter "true" enables recursive directory creation.
     * This means parent directories are created automatically if needed.
     *
     * EXAMPLE:
     * ensureStorageDir('/var/www/storage/backups/2024/january')
     * Creates: /var/www/storage → /var/www/storage/backups → ... → january
     *
     * @param string $directoryPath Full path to directory
     * @param int $permissions Unix permissions (octal notation)
     * @return bool True if directory exists or was created successfully
     */
    protected static function ensureStorageDir(string $directoryPath, int $permissions = 0755): bool
    {
        // Check if directory already exists
        if (is_dir($directoryPath)) {
            return true;
        }

        // Create directory with specified permissions
        // Third parameter "true" enables recursive creation
        return mkdir($directoryPath, $permissions, true);
    }

    /**
     * Get the client's real IP address
     *
     * PROBLEM:
     * $_SERVER['REMOTE_ADDR'] doesn't work correctly when using:
     * - Reverse proxies (Nginx, Apache)
     * - Load balancers
     * - CDNs (Cloudflare, etc.)
     *
     * These services set the real IP in HTTP headers.
     *
     * SOLUTION:
     * Check common proxy headers in priority order to find the real IP.
     *
     * SECURITY WARNING:
     * - These headers can be spoofed by attackers
     * - Only use for logging, not for security decisions
     * - If you trust your proxy/CDN, this is safe
     *
     * HEADER PRIORITY ORDER:
     * 1. HTTP_CF_CONNECTING_IP - Cloudflare's real IP header (most trustworthy if using CF)
     * 2. HTTP_X_FORWARDED_FOR - Standard proxy header (comma-separated list)
     * 3. HTTP_X_REAL_IP - Nginx proxy header
     * 4. REMOTE_ADDR - Direct connection IP (fallback)
     *
     * @return string Client IP address (IPv4 or IPv6)
     */
    protected static function getClientIp(): string
    {
        // Define headers to check in priority order
        $headersToCheck = [
            'HTTP_CF_CONNECTING_IP',  // Cloudflare: Most reliable if using CF
            'HTTP_X_FORWARDED_FOR',   // Standard proxy: Real IP before proxying
            'HTTP_X_REAL_IP',         // Nginx proxy: Original client IP
            'REMOTE_ADDR'             // Direct connection: No proxy involved
        ];

        // Check each header in order
        foreach ($headersToCheck as $headerName) {
            // Check if header exists and is not empty
            if (!empty($_SERVER[$headerName])) {
                // X-Forwarded-For can contain multiple IPs: "client, proxy1, proxy2"
                // Take the first one (the original client)
                $ipAddress = explode(',', $_SERVER[$headerName])[0];

                // Trim whitespace and return
                return trim($ipAddress);
            }
        }

        // No headers found, return placeholder
        // This should never happen in a real environment
        return '0.0.0.0';
    }

    /**
     * Friendly alias for getClientIp().
     */
    protected static function get_client_ip(): string
    {
        return static::getClientIp();
    }

    /**
     * Sanitize email headers to prevent injection.
     */
    protected static function sanitize_email_header(string $value): string
    {
        return str_replace(["\r", "\n", "\0", "%0a", "%0d"], '', $value);
    }

    /**
     * Write data to JSON file atomically
     *
     * ATOMIC WRITES:
     * The LOCK_EX flag ensures atomic writes - the file is locked during writing.
     * This prevents corruption when multiple processes write simultaneously.
     *
     * CONCURRENCY EXAMPLE:
     * - Process A starts writing file.json
     * - Process B tries to write file.json (blocked by lock)
     * - Process A finishes and releases lock
     * - Process B acquires lock and writes (doesn't corrupt A's data)
     *
     * WHY JSON?
     * - Human-readable (easy debugging)
     * - Cross-language compatible
     * - PHP has built-in JSON support
     * - Easy to edit manually if needed
     *
     * JSON_PRETTY_PRINT:
     * Makes JSON human-readable with indentation.
     * Slightly larger file size, but much easier to debug.
     *
     * @param string $filePath Absolute path to file
     * @param mixed $dataToWrite Data to encode as JSON (array, object, etc.)
     * @param int $jsonFlags JSON encoding flags (default: JSON_PRETTY_PRINT)
     * @return bool True on success, false on failure
     */
    protected static function writeJsonFile(string $filePath, mixed $dataToWrite, int $jsonFlags = JSON_PRETTY_PRINT): bool
    {
        // Encode data as JSON string
        $jsonString = json_encode($dataToWrite, $jsonFlags);

        // Check if encoding failed (invalid data)
        if ($jsonString === false) {
        return false;
    }

    /**
     * Friendly alias for ensureStorageDir().
     */
    protected static function ensure_storage_dir(string $directoryPath, int $permissions = 0755): bool
    {
        return static::ensureStorageDir($directoryPath, $permissions);
    }

        // Write to file with exclusive lock (LOCK_EX)
        // Returns number of bytes written, or false on failure
        $bytesWritten = file_put_contents($filePath, $jsonString, LOCK_EX);

        // Return true if write succeeded (bytes written > 0)
        return $bytesWritten !== false;
    }

    /**
     * Read and decode JSON file
     *
     * ASSOCIATIVE ARRAYS vs OBJECTS:
     * - associative=true: Returns array ['key' => 'value']
     * - associative=false: Returns object with properties
     *
     * Most PHP developers prefer arrays, so default is true.
     *
     * ERROR HANDLING:
     * Returns null on any error:
     * - File doesn't exist
     * - File is not readable
     * - JSON is malformed
     *
     * @param string $filePath Absolute path to file
     * @param bool $associative Decode as associative array (true) or object (false)
     * @return mixed Decoded data or null on failure
     */
    protected static function readJsonFile(string $filePath, bool $associative = true): mixed
    {
        // Check if file exists and is a regular file (not directory)
        if (!file_exists($filePath) || !is_file($filePath)) {
            return null;
        }

        // Read file contents
        $jsonString = file_get_contents($filePath);

        // Check if read failed
        if ($jsonString === false) {
            return null;
        }

        // Decode JSON string
        // Second parameter determines array vs object
        return json_decode($jsonString, $associative);
    }

    /**
     * Friendly alias for readJsonFile().
     */
    protected static function read_json_file(string $filePath, bool $associative = true): mixed
    {
        return static::readJsonFile($filePath, $associative);
    }

    /**
     * Write log message to component-specific log file
     *
     * LOG LEVELS:
     * - info: Normal operational messages (component started, task completed)
     * - warning: Something unexpected but not critical (missing config, deprecated API)
     * - error: Something failed but component continues (API timeout, file not found)
     *
     * LOG FILE LOCATION:
     * Each component gets its own log file:
     * - app/storage/logs/backups.log
     * - app/storage/logs/defense.log
     * - app/storage/logs/googleanalytics.log
     *
     * LOG FORMAT:
     * [2024-01-15 14:30:22] [info] Component initialized successfully
     * [2024-01-15 14:35:10] [error] Failed to connect to API: timeout
     *
     * WHY SEPARATE LOG FILES?
     * - Easier to debug specific components
     * - Can delete old logs per-component
     * - Prevents giant monolithic log file
     *
     * CONCURRENCY SAFETY:
     * FILE_APPEND | LOCK_EX ensures multiple processes can log simultaneously
     * without corrupting the log file.
     *
     * @param string $message Log message (can be multiline)
     * @param string $level Log level (info, warning, error)
     */
    protected static function log(string $message, string $level = 'info'): void
    {
        // Get component name for log filename
        $componentName = static::getComponentName();

        // Define log directory path
        $logDirectory = static::$app->appDir . '/storage/logs';

        // Create log directory if it doesn't exist
        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0755, true);
        }

        // Construct log file path (lowercase component name)
        // Example: app/storage/logs/backups.log
        $logFilePath = $logDirectory . '/' . strtolower($componentName) . '.log';

        // Get current timestamp for log entry
        $timestamp = date('Y-m-d H:i:s');

        // Format log line: [timestamp] [level] message
        $logLine = "[{$timestamp}] [{$level}] {$message}\n";

        // Append to log file with exclusive lock
        // FILE_APPEND: Add to end of file (don't overwrite)
        // LOCK_EX: Lock file during write (concurrency safe)
        file_put_contents($logFilePath, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Friendly alias for log().
     */
    protected static function app_log(string $message, string $level = 'info'): void
    {
        static::log($message, $level);
    }
}
