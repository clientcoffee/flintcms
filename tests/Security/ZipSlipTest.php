<?php

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Test ZIP slip attack prevention.
 * These tests validate path resolution rules used during extraction.
 */
class ZipSlipTest extends TestCase
{
    private array $tempRoots = [];

    protected function tearDown(): void
    {
        foreach ($this->tempRoots as $root) {
            $this->recursiveRemoveDirectory($root);
        }
        $this->tempRoots = [];
    }

    public function testRejectsTraversalEntries(): void
    {
        $base = $this->makeTempRoot();
        mkdir($base . '/site/themes', 0755, true);

        // ZIP slip entries like ../evil.php must never resolve inside the base.
        $this->expectException(\RuntimeException::class);
        resolve_secure_path($base . '/site/themes/../evil.php', $base . '/site/themes', true);
    }

    public function testRejectsAbsoluteEntries(): void
    {
        $base = $this->makeTempRoot();
        mkdir($base . '/site/themes', 0755, true);

        // Absolute paths must be rejected to prevent extraction outside the theme dir.
        $this->expectException(\RuntimeException::class);
        resolve_secure_path('/etc/passwd', $base . '/site/themes', true);
    }

    public function testAllowsValidThemeEntryPaths(): void
    {
        $base = $this->makeTempRoot();
        mkdir($base . '/site/themes/my-theme', 0755, true);

        // Normal theme entries should resolve within the allowed base.
        $entryPath = $base . '/site/themes/my-theme/config.php';
        $resolved = resolve_secure_path($entryPath, $base . '/site/themes', true);
        $expected = normalize_path(realpath($base . '/site/themes/my-theme') . '/config.php');
        $this->assertSame($expected, $resolved);
    }

    private function makeTempRoot(): string
    {
        $root = sys_get_temp_dir() . '/flint_zip_' . bin2hex(random_bytes(4));
        $this->tempRoots[] = $root;
        return $root;
    }

    private function recursiveRemoveDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_link($path)) {
                unlink($path);
                continue;
            }
            if (is_dir($path)) {
                $this->recursiveRemoveDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
