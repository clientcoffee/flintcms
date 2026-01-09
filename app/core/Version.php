<?php

namespace Flint;

/**
 * Version management for Flint.
 * Single source of truth for current version.
 */
class Version
{
    /**
     * Current Flint version.
     * Follows semantic versioning: MAJOR.MINOR.PATCH
     */
    public const VERSION = '0.1.0';

    /**
     * Release channel: stable, beta, dev
     */
    public const CHANNEL = 'dev';

    /**
     * Build date (set during build process)
     */
    public const BUILD_DATE = 'dev';

    /**
     * Get full version string.
     */
    public static function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * Get full version info.
     */
    public static function getInfo(): array
    {
        return [
            'version' => self::VERSION,
            'channel' => self::CHANNEL,
            'build_date' => self::BUILD_DATE,
        ];
    }

    /**
     * Compare two version strings.
     * Returns: -1 if $a < $b, 0 if equal, 1 if $a > $b
     */
    public static function compare(string $a, string $b): int
    {
        return version_compare($a, $b);
    }

    /**
     * Check if a version is newer than current.
     */
    public static function isNewer(string $version): bool
    {
        return self::compare($version, self::VERSION) > 0;
    }
}
