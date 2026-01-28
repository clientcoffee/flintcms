<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Flint\App;
use Flint\Paths;

/**
 * Unit tests for App class methods.
 * Tests individual methods in isolation.
 */
class AppTest extends TestCase
{
    private string $testRoot;
    private App $app;

    protected function setUp(): void
    {
        $this->resetPaths();

        // Create isolated test environment for each run to avoid collisions.
        $this->testRoot = sys_get_temp_dir() . '/flint_site_test_' . bin2hex(random_bytes(4));
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
    'micro_cache' => true,
    'micro_cache_ttl' => 10,
    'minify_html' => true,
    'fast_public' => true,
    'asset_cache' => true,
    'asset_cache_ttl' => 31536000,
    'uploads_cache' => false,
    'lazy_images' => true,
    'render_cache' => true,
    'sitemap_cache' => true,
    'tree_cache' => true,
    'inline_cache' => true,
  ),
  'updates' => 
  array (
    'auto_update' => 'ask',
  ),
);
PHP;
        file_put_contents($this->testRoot . '/site/config.php', $configContent);
        $this->app = new App($this->testRoot . '/app', $this->testRoot);
    }

    protected function tearDown(): void
    {
        $this->recursiveRemoveDirectory($this->testRoot);

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_write_close();
        }
    }

    private function resetPaths(): void
    {
        $reflection = new \ReflectionClass(Paths::class);
        $initialized = $reflection->getProperty('initialized');
        $initialized->setAccessible(true);
        $initialized->setValue(null, false);
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

    public function testSanitizeFilenameTrimsToMaximumLength(): void
    {
        $reflection = new \ReflectionClass($this->app);
        $method = $reflection->getMethod('sanitizeFilename');
        $input = str_repeat('a', 120);
        $result = $method->invoke($this->app, $input);
        $this->assertSame(80, strlen($result));
        $this->assertSame(str_repeat('a', 80), $result);
    }

    public function testSanitizeFilenameStripsUnicodeCharacters(): void
    {
        $reflection = new \ReflectionClass($this->app);
        $method = $reflection->getMethod('sanitizeFilename');
        $this->assertSame('caf', $method->invoke($this->app, 'café'));
        $this->assertSame('nave', $method->invoke($this->app, 'naïve'));
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

    public function testSplitSettingsSeparatesEditableAndReadonly(): void
    {
        $reflection = new \ReflectionClass($this->app);
        $method = $reflection->getMethod('splitSettings');
        $config = [
            'site' => [
                'name' => 'Test Site',
                'theme' => 'motion',
            ],
            'system' => [
                'environment' => 'development',
            ],
            'updates' => [
                'auto_update' => 'ask',
            ],
        ];
        [$editable, $readonly] = $method->invoke($this->app, $config);
        $this->assertSame('Test Site', $editable['site.name']);
        $this->assertSame('motion', $editable['site.theme']);
        $this->assertArrayHasKey('system.environment', $readonly);
        $this->assertArrayHasKey('updates.auto_update', $readonly);
        $this->assertArrayNotHasKey('system.environment', $editable);
    }

    public function testCoreSiteKeysUsesDefaults(): void
    {
        $reflection = new \ReflectionClass($this->app);
        $method = $reflection->getMethod('coreSiteKeys');
        $coreKeys = $method->invoke($this->app, [
            'name' => 'Test Site',
            'theme' => 'motion',
        ]);
        $this->assertContains('site.name', $coreKeys);
        $this->assertContains('site.theme', $coreKeys);
        $this->assertNotContains('site.custom_key', $coreKeys);
    }

    public function testCountTreeFilesCountsNestedItems(): void
    {
        $reflection = new \ReflectionClass($this->app);
        $method = $reflection->getMethod('countTreeFiles');
        $tree = [
            [
                'type' => 'file',
                'label' => 'home',
                'path' => '/',
            ],
            [
                'type' => 'directory',
                'name' => 'docs',
                'children' => [
                    [
                        'type' => 'file',
                        'label' => 'about',
                        'path' => '/about',
                    ],
                    [
                        'type' => 'directory',
                        'name' => 'nested',
                        'children' => [
                            [
                                'type' => 'file',
                                'label' => 'deep',
                                'path' => '/docs/deep',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $this->assertSame(3, $method->invoke($this->app, $tree));
    }

    public function testParseSessionSavePathHandlesDepthPrefix(): void
    {
        $reflection = new \ReflectionClass($this->app);
        $method = $reflection->getMethod('parseSessionSavePath');
        $this->assertSame('/var/lib/php/sessions', $method->invoke($this->app, '5;/var/lib/php/sessions'));
        $this->assertSame('/tmp', $method->invoke($this->app, '/tmp'));
        $this->assertNull($method->invoke($this->app, ''));
    }

    public function testMicroCacheEligibilitySkipsQueryAndCookies(): void
    {
        $reflection = new \ReflectionClass($this->app);
        $method = $reflection->getMethod('isMicroCacheEligible');

        $originalQuery = $_SERVER['QUERY_STRING'] ?? null;
        $originalCookies = $_COOKIE;

        $_SERVER['QUERY_STRING'] = '';
        $_COOKIE = [];
        $this->assertTrue($method->invoke($this->app, '/about', 'GET'));

        $_SERVER['QUERY_STRING'] = 'q=1';
        $this->assertFalse($method->invoke($this->app, '/about', 'GET'));

        $_SERVER['QUERY_STRING'] = '';
        $_COOKIE['PHPSESSID'] = 'test';
        $this->assertFalse($method->invoke($this->app, '/about', 'GET'));

        $_COOKIE = [];
        $this->assertFalse($method->invoke($this->app, '/api/test', 'GET'));

        if ($originalQuery === null) {
            unset($_SERVER['QUERY_STRING']);
        } else {
            $_SERVER['QUERY_STRING'] = $originalQuery;
        }
        $_COOKIE = $originalCookies;
    }

    public function testPublicVisitorDetection(): void
    {
        $reflection = new \ReflectionClass($this->app);
        $method = $reflection->getMethod('isPublicVisitor');

        $originalCookies = $_COOKIE;
        $_COOKIE = [];
        $this->assertTrue($method->invoke($this->app, '/about', 'GET'));
        $this->assertFalse($method->invoke($this->app, '/api/status', 'GET'));

        $_COOKIE['PHPSESSID'] = 'test';
        $this->assertFalse($method->invoke($this->app, '/about', 'GET'));

        $_COOKIE = $originalCookies;
    }

    public function testApplyAssetCacheHeadersSetsCacheControl(): void
    {
        if (function_exists('header_remove')) {
            header_remove();
        }

        $reflection = new \ReflectionClass($this->app);
        $method = $reflection->getMethod('applyAssetCacheHeaders');
        $method->invoke($this->app);

        $headers = headers_list();
        $this->assertContains('Cache-Control: public, max-age=31536000, immutable', $headers);

        if (function_exists('header_remove')) {
            header_remove();
        }
    }

    public function testTreeCacheWritesFiles(): void
    {
        file_put_contents($this->testRoot . '/site/pages/page.md', "# Page");
        file_put_contents($this->testRoot . '/site/blocks/nav.md', "Nav");

        $reflection = new \ReflectionClass($this->app);
        $listContentTree = $reflection->getMethod('listContentTree');
        $listBlockTree = $reflection->getMethod('listBlockTree');
        $treeCachePath = $reflection->getMethod('treeCachePath');

        $listContentTree->invoke($this->app);
        $listBlockTree->invoke($this->app);

        $pagesCachePath = $treeCachePath->invoke($this->app, 'pages');
        $blocksCachePath = $treeCachePath->invoke($this->app, 'blocks');

        $this->assertFileExists($pagesCachePath);
        $this->assertFileExists($blocksCachePath);
    }

    public function testRenderCacheWritesFile(): void
    {
        $filePath = $this->testRoot . '/site/pages/cache-test.md';
        file_put_contents($filePath, "# Cache Test");

        $parser = new \Flint\Parser($this->app);
        $parsed = $parser->parseFile($filePath);

        $reflection = new \ReflectionClass($parser);
        $cachePathMethod = $reflection->getMethod('renderCachePath');
        $cachePath = $cachePathMethod->invoke($parser, $filePath);

        $this->assertFileExists($cachePath);
        $payload = require $cachePath;
        $this->assertSame(filemtime($filePath), $payload['mtime']);
        $this->assertSame($parsed['content_html'], $payload['data']['content_html']);
    }

    public function testInlineMarkdownCacheStoresEntry(): void
    {
        $parser = new \Flint\Parser($this->app);
        $parser->renderInlineMarkdown('**Hello**');

        $reflection = new \ReflectionClass($parser);
        $property = $reflection->getProperty('inlineCache');
        $property->setAccessible(true);
        $cache = $property->getValue($parser);

        $this->assertCount(1, $cache);
    }

    public function testSitemapCacheWritesFile(): void
    {
        file_put_contents($this->testRoot . '/site/pages/index.md', "---\ntitle: Home\n---\n");

        \Flint\ThemeContext::set(['app' => $this->app]);
        try {
            $html = \Components\Sitemap::render([], '');
            $this->assertStringContainsString('/', $html);
        } finally {
            \Flint\ThemeContext::clear();
        }

        $cachePath = \Flint\Paths::$cacheDir . '/sitemap/sitemap-public.php';
        $this->assertFileExists($cachePath);
    }

    public function testMinifyHtmlRemovesWhitespace(): void
    {
        $reflection = new \ReflectionClass($this->app);
        $method = $reflection->getMethod('minifyHtml');

        $html = "<div>\n  <span>Hi</span>\n</div>";
        $this->assertSame('<div><span>Hi</span></div>', $method->invoke($this->app, $html));
    }

    public function testLazyLoadingAddsAttributes(): void
    {
        $parser = new \Flint\Parser($this->app);
        $reflection = new \ReflectionClass($parser);
        $method = $reflection->getMethod('addLazyLoadingToImages');

        $html = '<p><img src="/test.png" width="100" height="50"></p>';
        $result = $method->invoke($parser, $html);

        $this->assertStringContainsString('loading="lazy"', $result);
        $this->assertStringContainsString('decoding="async"', $result);
        $this->assertStringContainsString('aspect-ratio: 100 / 50', $result);
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
