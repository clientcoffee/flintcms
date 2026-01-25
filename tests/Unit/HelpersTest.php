<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for core helper utilities.
 * Focused on path safety, nonce validation, and security-sensitive helpers.
 */
class HelpersTest extends TestCase
{
    private string $testRoot;

    protected function setUp(): void
    {
        $this->testRoot = sys_get_temp_dir() . '/flint_helpers_' . bin2hex(random_bytes(4));
        mkdir($this->testRoot, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->recursiveRemoveDirectory($this->testRoot);

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_write_close();
        }
    }

    public function testNormalizePathCollapsesSegments(): void
    {
        $this->assertSame('/var/www', normalize_path('/var/./www/'));
        $this->assertSame('/var/www', normalize_path('/var/www/'));
        $this->assertSame('foo/bar', normalize_path('foo//bar'));
        $this->assertSame('/', normalize_path('/../'));
        $this->assertSame('.', normalize_path(''));
    }

    public function testPathIsWithinMatchesBoundaries(): void
    {
        $this->assertTrue(path_is_within('/var/www/site', '/var/www'));
        $this->assertTrue(path_is_within('/var/www', '/var/www'));
        $this->assertFalse(path_is_within('/var/www-2', '/var/www'));
    }

    public function testResolveSecurePathRejectsRelativePaths(): void
    {
        // Reject any non-absolute paths to avoid implicit base resolution.
        $this->expectException(\RuntimeException::class);
        resolve_secure_path('relative/path', $this->testRoot);
    }

    public function testResolveSecurePathRejectsControlCharacters(): void
    {
        // Control characters can truncate or confuse path handling.
        $this->expectException(\RuntimeException::class);
        resolve_secure_path($this->testRoot . "/bad\nname.txt", $this->testRoot);
    }

    public function testResolveSecurePathAllowsMissingInsideBase(): void
    {
        $path = $this->testRoot . '/missing.txt';
        $resolved = resolve_secure_path($path, $this->testRoot, true);
        $expected = normalize_path(realpath($this->testRoot) . '/' . basename($path));
        $this->assertSame($expected, $resolved);
    }

    public function testResolveSecurePathRejectsMissingWhenNotAllowed(): void
    {
        $this->expectException(\RuntimeException::class);
        resolve_secure_path($this->testRoot . '/missing.txt', $this->testRoot, false);
    }

    public function testResolveSecurePathRejectsOutsideBase(): void
    {
        $outside = sys_get_temp_dir() . '/flint_outside_' . bin2hex(random_bytes(4));
        mkdir($outside, 0755, true);

        try {
            $this->expectException(\RuntimeException::class);
            resolve_secure_path($outside . '/file.txt', $this->testRoot, true);
        } finally {
            $this->recursiveRemoveDirectory($outside);
        }
    }

    public function testResolveSecurePathRejectsSymlinkTraversal(): void
    {
        $outsideRoot = sys_get_temp_dir() . '/flint_symlink_target_' . bin2hex(random_bytes(4));
        mkdir($outsideRoot, 0755, true);

        $symlink = $this->testRoot . '/link';
        if (!symlink($outsideRoot, $symlink)) {
            $this->markTestSkipped('Symlinks are not supported in this environment.');
        }

        $this->expectException(\RuntimeException::class);
        resolve_secure_path($symlink . '/file.txt', $this->testRoot, true);
    }

    public function testSafeWriteFileAndUnlink(): void
    {
        $notesDir = $this->testRoot . '/notes';
        mkdir($notesDir, 0755, true);
        $path = $notesDir . '/readme.txt';
        $this->assertTrue(safe_write_file($path, 'hello', $this->testRoot));
        $this->assertFileExists($path);
        $this->assertTrue(safe_unlink($path, $this->testRoot));
        $this->assertFileDoesNotExist($path);
    }

    public function testSafeCopyAndRename(): void
    {
        $src = $this->testRoot . '/src.txt';
        $destDir = $this->testRoot . '/dest';
        $dest = $destDir . '/copied.txt';
        $renamed = $destDir . '/renamed.txt';

        file_put_contents($src, 'source');
        mkdir($destDir, 0755, true);

        $this->assertTrue(safe_copy($src, $dest, $this->testRoot, $this->testRoot));
        $this->assertFileExists($dest);
        $this->assertTrue(safe_rename($dest, $renamed, $this->testRoot, $this->testRoot));
        $this->assertFileExists($renamed);
        $this->assertFileDoesNotExist($dest);
    }

    public function testEnsureStorageDirCreatesDirectory(): void
    {
        $path = $this->testRoot . '/storage';
        $this->assertTrue(ensure_storage_dir($path));
        $this->assertDirectoryExists($path);

        $filePath = $this->testRoot . '/storage-file';
        file_put_contents($filePath, 'data');
        $this->assertFalse(ensure_storage_dir($filePath));
    }

    public function testSanitizeEmailHeaderStripsNewlines(): void
    {
        // Header injection must be neutralized before sending email.
        $input = "Hello\r\nBcc: attacker@example.com";
        $this->assertSame('HelloBcc: attacker@example.com', sanitize_email_header($input));
        $this->assertSame('Hello', sanitize_email_header("Hello%0a"));
    }

    public function testGetClientIpUsesHeaderPriority(): void
    {
        $originalServer = $_SERVER;
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.5';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.6';
        $_SERVER['HTTP_X_REAL_IP'] = '203.0.113.7';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.8';

        $this->assertSame('203.0.113.5', get_client_ip());

        unset($_SERVER['HTTP_CF_CONNECTING_IP']);
        $this->assertSame('203.0.113.6', get_client_ip());

        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $this->assertSame('203.0.113.7', get_client_ip());

        unset($_SERVER['HTTP_X_REAL_IP']);
        $this->assertSame('203.0.113.8', get_client_ip());

        $_SERVER = $originalServer;
    }

    public function testValidateFormNonceAcceptsOnceAndThenRejectsReuse(): void
    {
        // Nonces should be single-use to prevent replay.
        $token = $this->buildNonceToken(time(), 'nonce-1', '127.0.0.1');
        $this->assertTrue(validate_form_nonce($token, 3600, '127.0.0.1'));
        $this->assertFalse(validate_form_nonce($token, 3600, '127.0.0.1'));
    }

    public function testValidateFormNonceRejectsExpiredToken(): void
    {
        // Expired nonces should never validate.
        $token = $this->buildNonceToken(time() - 4000, 'nonce-expired', null);
        $this->assertFalse(validate_form_nonce($token, 3000, null));
    }

    public function testValidateFormNonceRejectsIpMismatch(): void
    {
        $token = $this->buildNonceToken(time(), 'nonce-ip', '203.0.113.9');
        $this->assertFalse(validate_form_nonce($token, 3600, '203.0.113.10'));
    }

    private function buildNonceToken(int $createdAt, string $nonce, ?string $ip): string
    {
        $payload = ['created' => $createdAt, 'nonce' => $nonce];
        if ($ip !== null) {
            $payload['ip'] = $ip;
        }
        return base64_encode(json_encode($payload));
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
