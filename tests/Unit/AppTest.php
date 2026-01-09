<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Flint\App;

/**
 * Unit tests for App class methods.
 * Tests individual methods in isolation.
 */
class AppTest extends TestCase
{
    private const TEST_ROOT_SUFFIX = '/flint_site_test';

    private string $testRoot;
    private App $app;

    protected function setUp(): void
    {
        // Create test environment
        $this->testRoot = sys_get_temp_dir() . self::TEST_ROOT_SUFFIX;
        if (is_dir($this->testRoot)) {
            $this->recursiveRemoveDirectory($this->testRoot);
        }
        mkdir($this->testRoot . '/app', 0755, true);
        mkdir($this->testRoot . '/site/pages', 0755, true);
        mkdir($this->testRoot . '/site/blocks', 0755, true);
        mkdir($this->testRoot . '/site/uploads', 0755, true);
        mkdir($this->testRoot . '/site/themes', 0755, true);
        mkdir($this->testRoot . '/site/components', 0755, true);
        mkdir($this->testRoot . '/site/submissions', 0755, true);

        $configContent = <<<'PHP'
<?php
return array (
  'site' => 
  array (
    'name' => 'TEST',
    'theme' => 'motion',
  ),
  'mail' => 
  array (
    'admin_email' => 'test@example.com',
    'smtp_host' => 'localhost',
  ),
  'admin' => 
  array (
    'password' => '$2y$12$8vurOMM1cWgXfi8iAgN6bekru/YLRkE.16TQQQnrQsxL/rgowgYB.',
  ),
  'system' => 
  array (
    'cache_enabled' => false,
    'show_errors' => true,
    'debug' => false,
  ),
  'updates' => 
  array (
    'auto_update' => 'ask',
  ),
);
PHP;
        file_put_contents($this->testRoot . '/config.php', $configContent);
        $this->app = new App($this->testRoot . '/app', $this->testRoot);
    }

    protected function tearDown(): void
    {
        $this->recursiveRemoveDirectory($this->testRoot);
    }

    /**
     * Test getMimeType() returns correct MIME types
     */
    public function testGetMimeTypeReturnsCorrectTypes(): void
    {
        $reflection = new \ReflectionClass($this->app);
        $method = $reflection->getMethod('getMimeType');
        $this->assertEquals('text/javascript', $method->invoke($this->app, 'js'));
        $this->assertEquals('text/javascript', $method->invoke($this->app, 'mjs'));
        $this->assertEquals('text/css', $method->invoke($this->app, 'css'));
        $this->assertEquals('image/jpeg', $method->invoke($this->app, 'jpg'));
        $this->assertEquals('image/jpeg', $method->invoke($this->app, 'jpeg'));
        $this->assertEquals('image/png', $method->invoke($this->app, 'png'));
        $this->assertEquals('image/gif', $method->invoke($this->app, 'gif'));
        $this->assertEquals('image/svg+xml', $method->invoke($this->app, 'svg'));
        $this->assertEquals('font/woff', $method->invoke($this->app, 'woff'));
        $this->assertEquals('font/woff2', $method->invoke($this->app, 'woff2'));
        $this->assertEquals('font/ttf', $method->invoke($this->app, 'ttf'));
        $this->assertEquals('application/vnd.ms-fontobject', $method->invoke($this->app, 'eot'));
    }

    public function testGetMimeTypeHandlesUnknownExtension(): void
    {
        $reflection = new \ReflectionClass($this->app);
        $method = $reflection->getMethod('getMimeType');
        $this->assertEquals('application/octet-stream', $method->invoke($this->app, 'unknown'));
    }

    public function testGetMimeTypeIsCaseInsensitive(): void
    {
        $reflection = new \ReflectionClass($this->app);
        $method = $reflection->getMethod('getMimeType');
        $this->assertEquals('text/javascript', $method->invoke($this->app, 'JS'));
        $this->assertEquals('image/jpeg', $method->invoke($this->app, 'JPG'));
    }

    /**
     * Test sanitizeFilename() properly sanitizes filenames
     */
    public function testSanitizeFilenameRemovesSpaces(): void
    {
        $reflection = new \ReflectionClass($this->app);
        $method = $reflection->getMethod('sanitizeFilename');
        $this->assertEquals('my-file', $method->invoke($this->app, 'my file'));
        $this->assertEquals('multiple-spaces-here', $method->invoke($this->app, 'multiple   spaces   here'));
    }

    public function testSanitizeFilenameRemovesSpecialCharacters(): void
    {
        $reflection = new \ReflectionClass($this->app);
        $method = $reflection->getMethod('sanitizeFilename');
        $this->assertEquals('myfile', $method->invoke($this->app, 'my@file!'));
        $this->assertEquals('testphp', $method->invoke($this->app, 'test.php'));
        $this->assertEquals('helloworld', $method->invoke($this->app, 'hello$world%'));
    }

    public function testSanitizeFilenameConvertsToLowercase(): void
    {
        $reflection = new \ReflectionClass($this->app);
        $method = $reflection->getMethod('sanitizeFilename');
        $this->assertEquals('myfile', $method->invoke($this->app, 'MyFile'));
        $this->assertEquals('uppercase', $method->invoke($this->app, 'UPPERCASE'));
    }

    public function testSanitizeFilenameRemovesMultipleHyphens(): void
    {
        $reflection = new \ReflectionClass($this->app);
        $method = $reflection->getMethod('sanitizeFilename');
        $this->assertEquals('my-file', $method->invoke($this->app, 'my---file'));
    }

    public function testSanitizeFilenameTrimsHyphens(): void
    {
        $reflection = new \ReflectionClass($this->app);
        $method = $reflection->getMethod('sanitizeFilename');
        $this->assertEquals('myfile', $method->invoke($this->app, '-myfile-'));
    }

    public function testSanitizeFilenameHandlesEmptyString(): void
    {
        $reflection = new \ReflectionClass($this->app);
        $method = $reflection->getMethod('sanitizeFilename');
        $this->assertEquals('upload', $method->invoke($this->app, ''));
        $this->assertEquals('upload', $method->invoke($this->app, '!!!'));
    }

    /**
     * Test recursiveRemoveDirectory() properly removes directories
     */
    public function testRecursiveRemoveDirectoryRemovesEmptyDir(): void
    {
        $testDir = $this->testRoot . '/empty_dir';
        mkdir($testDir);

        $reflection = new \ReflectionClass($this->app);
        $method = $reflection->getMethod('recursiveRemoveDirectory');
        $this->assertTrue(is_dir($testDir));
        $method->invoke($this->app, $testDir);
        $this->assertFalse(is_dir($testDir));
    }

    public function testRecursiveRemoveDirectoryRemovesDirWithFiles(): void
    {
        $testDir = $this->testRoot . '/files_dir';
        mkdir($testDir);
        file_put_contents($testDir . '/file1.txt', 'test');
        file_put_contents($testDir . '/file2.txt', 'test');

        $reflection = new \ReflectionClass($this->app);
        $method = $reflection->getMethod('recursiveRemoveDirectory');
        $this->assertTrue(is_dir($testDir));
        $method->invoke($this->app, $testDir);
        $this->assertFalse(is_dir($testDir));
    }

    public function testRecursiveRemoveDirectoryRemovesNestedDirs(): void
    {
        $testDir = $this->testRoot . '/nested';
        mkdir($testDir . '/sub1/sub2', 0755, true);
        file_put_contents($testDir . '/sub1/sub2/file.txt', 'test');

        $reflection = new \ReflectionClass($this->app);
        $method = $reflection->getMethod('recursiveRemoveDirectory');

        $this->assertTrue(is_dir($testDir));
        $method->invoke($this->app, $testDir);
        $this->assertFalse(is_dir($testDir));
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
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
