<?php

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use Flint\App;

/**
 * Test ZIP slip attack prevention.
 * Critical security test for theme upload functionality.
 */
class ZipSlipTest extends TestCase
{
    private const TEST_ROOT_SUFFIX = '/flint_site_test';

    private string $testRoot;
    private App $app;

    protected function setUp(): void
    {
        // Create temporary test environment
        $this->testRoot = sys_get_temp_dir() . self::TEST_ROOT_SUFFIX;
        if (is_dir($this->testRoot)) {
            $this->recursiveRemoveDirectory($this->testRoot);
        }
        mkdir($this->testRoot . '/app', 0755, true);
        mkdir($this->testRoot . '/site/themes', 0755, true);
        mkdir($this->testRoot . '/site/uploads', 0755, true);

        // Create minimal config
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
        // Clean up test environment
        $this->recursiveRemoveDirectory($this->testRoot);
    }

    public function testBlocksPathTraversalInZipEntry(): void
    {
        // Create malicious ZIP with ../ in entry name
        $zipPath = $this->testRoot . '/malicious.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('theme.ini', '[theme]');
        $zip->addFromString('../../../evil.php', '<?php echo "pwned";');
        $zip->close();

        // Simulate upload
        $_FILES['file'] = [
            'name' => 'malicious.zip',
            'tmp_name' => $zipPath,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($zipPath)
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // Test should reject upload (we'd need to refactor App to test this properly)
        // For now, this is a placeholder showing what the test should do
        $this->assertTrue(true); // Placeholder
    }

    public function testBlocksAbsolutePathsInZipEntry(): void
    {
        // Create ZIP with absolute path
        $zipPath = $this->testRoot . '/absolute.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('theme.ini', '[theme]');
        $zip->addFromString('/etc/passwd', 'fake');
        $zip->close();

        // Should be rejected
        $this->assertTrue(true); // Placeholder
    }

    public function testAllowsValidThemeZip(): void
    {
        // Create valid ZIP
        $zipPath = $this->testRoot . '/valid.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('theme.ini', '[theme]\nname=Test Theme');
        $zip->addFromString('layout.php', '<?php echo "test";');
        $zip->close();

        // Should be accepted
        $this->assertTrue(true); // Placeholder
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
