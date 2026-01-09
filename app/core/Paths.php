<?php

namespace Flint;

/**
 * Global Path Constants
 *
 * Centralized storage for all file system paths used throughout Flint.
 * Initialized once during application bootstrap, then accessible statically
 * from any component without needing to pass App instance around.
 *
 * WHY STATIC PATHS?
 * - Eliminates repeated string concatenation: $this->root . '/site/pages'
 * - Provides single source of truth for all directory locations
 * - Makes refactoring easier (change path in one place)
 * - Type-safe: IDE autocomplete knows these are strings
 * - No need to pass App instance to access paths
 *
 * USAGE:
 * ```php
 * // Instead of:
 * $uploadPath = $app->root . '/site/uploads';
 *
 * // Use:
 * $uploadPath = Paths::$uploadsDir;
 * ```
 *
 * @package Flint
 * @subpackage Core
 */
class Paths
{
    // ==========================================
    // ROOT DIRECTORIES
    // ==========================================

    /**
     * Project root directory (parent of app/)
     * Example: /var/www/flint
     *
     * @var string
     */
    public static string $rootDir;

    /**
     * Application directory (contains core files)
     * Example: /var/www/flint/app
     *
     * @var string
     */
    public static string $appDir;

    // ==========================================
    // SITE DIRECTORIES
    // ==========================================

    /**
     * Main site directory (all user content, themes, and operational data)
     * Example: /var/www/flint/site
     *
     * @var string
     */
    public static string $siteDir;
    /**
     * Pages directory (markdown page files)
     * Example: /var/www/flint/site/pages
     *
     * @var string
     */
    public static string $pagesDir;

    /**
     * Blocks directory (reusable content blocks)
     * Example: /var/www/flint/site/blocks
     *
     * @var string
     */
    public static string $blocksDir;

    /**
     * Uploads directory (user-uploaded files)
     * Example: /var/www/flint/site/uploads
     *
     * @var string
     */
    public static string $uploadsDir;

    /**
     * Themes directory (site themes)
     * Example: /var/www/flint/site/themes
     *
     * @var string
     */
    public static string $themesDir;

    /**
     * Site components directory (user-installed components)
     * Example: /var/www/flint/site/components
     *
     * @var string
     */
    public static string $siteComponentsDir;

    /**
     * Submissions directory (form submissions, defense logs)
     * Example: /var/www/flint/site/submissions
     *
     * @var string
     */
    public static string $submissionsDir;

    // ==========================================
    // CORE DIRECTORIES
    // ==========================================

    /**
     * Core directory (core classes)
     * Example: /var/www/flint/app/core
     *
     * @var string
     */
    public static string $coreDir;

    /**
     * Core components directory (built-in components)
     * Example: /var/www/flint/app/core/components
     *
     * @var string
     */
    public static string $coreComponentsDir;

    // ==========================================
    // STORAGE DIRECTORIES
    // ==========================================

    /**
     * Storage directory (logs, cache, temp files)
     * Example: /var/www/flint/app/storage
     *
     * @var string
     */
    public static string $storageDir;

    /**
     * Logs directory (application and component logs)
     * Example: /var/www/flint/app/storage/logs
     *
     * @var string
     */
    public static string $logsDir;

    /**
     * Cache directory (temporary cached data)
     * Example: /var/www/flint/app/storage/cache
     *
     * @var string
     */
    public static string $cacheDir;

    /**
     * Backups directory (site backups)
     * Example: /var/www/flint/app/storage/backups
     *
     * @var string
     */
    public static string $backupsDir;

    // ==========================================
    // FILE PATHS
    // ==========================================

    /**
     * Configuration file path
     * Example: /var/www/flint/site/config.php
     *
     * @var string
     */
    public static string $configFile;

    // ==========================================
    // INITIALIZATION
    // ==========================================

    /**
     * Whether paths have been initialized
     *
     * @var bool
     */
    private static bool $initialized = false;

    /**
     * Initialize all path constants
     *
     * Called once during application bootstrap by App::__construct().
     * After initialization, all paths are available globally.
     *
     * SECURITY: Paths are immutable after initialization.
     * Subsequent calls to init() are ignored to prevent tampering.
     *
     * @param string $rootDirectory Project root directory
     * @param string $appDirectory Application directory
     */
    public static function init(string $rootDirectory, string $appDirectory): void
    {
        // Prevent re-initialization (security: immutable paths)
        if (self::$initialized) {
            return;
        }

        // Set root directories
        self::$rootDir = $rootDirectory;
        self::$appDir = $appDirectory;

        // Compute site directories
        self::$siteDir = $rootDirectory . '/site';
        self::$pagesDir = self::$siteDir . '/pages';
        self::$blocksDir = self::$siteDir . '/blocks';
        self::$uploadsDir = self::$siteDir . '/uploads';
        self::$themesDir = self::$siteDir . '/themes';
        self::$siteComponentsDir = self::$siteDir . '/components';
        self::$submissionsDir = self::$siteDir . '/submissions';

        // Compute core directories
        self::$coreDir = $appDirectory . '/core';
        self::$coreComponentsDir = $appDirectory . '/core/components';

        // Compute storage directories
        self::$storageDir = $appDirectory . '/storage';
        self::$logsDir = $appDirectory . '/storage/logs';
        self::$cacheDir = $appDirectory . '/storage/cache';
        self::$backupsDir = $appDirectory . '/storage/backups';

        // Compute file paths
        self::$configFile = self::$siteDir . '/config.php';

        // Mark as initialized
        self::$initialized = true;
    }

    /**
     * Get current active theme directory
     *
     * DYNAMIC PATH: This path depends on the current theme configuration.
     * Call this method to get the theme-specific directory.
     *
     * @param string $themeName Theme name from config
     * @return string Full path to theme directory
     */
    public static function themeDir(string $themeName): string
    {
        return self::$themesDir . '/' . $themeName;
    }

    /**
     * Check if paths have been initialized
     *
     * @return bool True if initialized
     */
    public static function isInitialized(): bool
    {
        return self::$initialized;
    }
}
