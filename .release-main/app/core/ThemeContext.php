<?php

namespace Flint;

/**
 * ThemeContext
 *
 * Stores the active theme rendering context for helper functions.
 */
final class ThemeContext
{
    private static array $data = [];

    public static function set(array $data): void
    {
        self::$data = $data;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$data[$key] ?? $default;
    }

    public static function clear(): void
    {
        self::$data = [];
    }
}
