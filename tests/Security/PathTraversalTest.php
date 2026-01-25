<?php

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Test path traversal attack prevention.
 * Critical security tests for file serving.
 */
class PathTraversalTest extends TestCase
{
    private array $tempRoots = [];

    protected function tearDown(): void
    {
        foreach ($this->tempRoots as $root) {
            $this->recursiveRemoveDirectory($root);
        }
        $this->tempRoots = [];
    }

    public function testBlocksDoubleDotTraversal(): void
    {
        $base = $this->makeTempRoot();
        mkdir($base . '/site/uploads', 0755, true);

        // Classic ../ traversal should be rejected by secure path resolution.
        $this->expectException(\RuntimeException::class);
        resolve_secure_path($base . '/site/uploads/../../config.ini', $base . '/site/uploads', true);
    }

    public function testBlocksEncodedTraversalAfterDecode(): void
    {
        $base = $this->makeTempRoot();
        mkdir($base . '/site/uploads', 0755, true);

        // Encoded traversal sequences must be caught after decoding.
        $decoded = urldecode('%2e%2e%2fconfig.ini');
        $this->expectException(\RuntimeException::class);
        resolve_secure_path($base . '/site/uploads/' . $decoded, $base . '/site/uploads', true);
    }

    public function testBlocksDoubleEncodedTraversalAfterDecode(): void
    {
        $base = $this->makeTempRoot();
        mkdir($base . '/site/uploads', 0755, true);

        // Double-encoded traversal attempts should still be blocked.
        $decoded = urldecode(urldecode('%252e%252e%252fconfig.ini'));
        $this->expectException(\RuntimeException::class);
        resolve_secure_path($base . '/site/uploads/' . $decoded, $base . '/site/uploads', true);
    }

    public function testAllowsValidUploadPathInsideBase(): void
    {
        $base = $this->makeTempRoot();
        mkdir($base . '/site/uploads', 0755, true);

        // A safe path inside uploads should resolve without errors.
        $filePath = $base . '/site/uploads/image.jpg';
        $resolved = resolve_secure_path($filePath, $base . '/site/uploads', true);
        $expected = normalize_path(realpath($base . '/site/uploads') . '/image.jpg');
        $this->assertSame($expected, $resolved);
    }

    public function testSymlinkTraversalRejected(): void
    {
        $base = $this->makeTempRoot();
        mkdir($base . '/site/uploads', 0755, true);

        // Symlink hops should be rejected to prevent escaping the base directory.
        $configPath = $base . '/config.ini';
        file_put_contents($configPath, 'secret');

        $symlinkPath = $base . '/site/uploads/evil.ini';
        if (!symlink($configPath, $symlinkPath)) {
            $this->markTestSkipped('Symlinks are not supported in this environment.');
        }

        $this->expectException(\RuntimeException::class);
        resolve_secure_path($symlinkPath, $base . '/site/uploads', true);
    }

    private function makeTempRoot(): string
    {
        $root = sys_get_temp_dir() . '/flint_path_' . bin2hex(random_bytes(4));
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
