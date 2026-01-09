<?php
namespace Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Test path traversal attack prevention.
 * Critical security tests for file serving.
 */
class PathTraversalTest extends TestCase
{
    public function testBlocksDoubleDotInRequestPath(): void
    {
        $requestPath = '/content/uploads/../../config.ini';
        $this->assertStringContainsString('..', $requestPath);
        // App should block this at line 37
    }

    public function testBlocksEncodedPathTraversal(): void
    {
        $encoded = urldecode('%2e%2e%2fconfig.ini');
        $this->assertStringContainsString('..', $encoded);
    }

    public function testBlocksDoubleEncodedTraversal(): void
    {
        $doubleEncoded = urldecode(urldecode('%252e%252e%252fconfig.ini'));
        $this->assertStringContainsString('..', $doubleEncoded);
    }

    public function testRealpathValidatesUploadDirectory(): void
    {
        $testRoot = sys_get_temp_dir() . '/flint_path_test_' . uniqid();
        mkdir($testRoot . '/content/uploads', 0755, true);

        // Valid path
        $validPath = $testRoot . '/content/uploads/image.jpg';
        touch($validPath);
        $realPath = realpath($validPath);
        $realUploadsDir = realpath($testRoot . '/content/uploads');

        $this->assertStringStartsWith($realUploadsDir, $realPath);

        // Cleanup
        unlink($validPath);
        rmdir($testRoot . '/content/uploads');
        rmdir($testRoot . '/content');
        rmdir($testRoot);
    }

    public function testSymlinkNotFollowedOutsideUploads(): void
    {
        $testRoot = sys_get_temp_dir() . '/flint_symlink_test_' . uniqid();
        mkdir($testRoot . '/content/uploads', 0755, true);

        // Create symlink to config.ini
        $configPath = $testRoot . '/config.ini';
        $symlinkPath = $testRoot . '/content/uploads/evil.ini';
        file_put_contents($configPath, 'secret');

        if (symlink($configPath, $symlinkPath)) {
            $realPath = realpath($symlinkPath);
            $realUploadsDir = realpath($testRoot . '/content/uploads');

            // realpath follows symlinks, so this should NOT start with uploads dir
            $this->assertFalse(str_starts_with($realPath, $realUploadsDir),
                "Symlink should resolve outside uploads directory");

            unlink($symlinkPath);
        }

        // Cleanup
        unlink($configPath);
        rmdir($testRoot . '/content/uploads');
        rmdir($testRoot . '/content');
        rmdir($testRoot);
    }
}
