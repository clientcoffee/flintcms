<?php

namespace Components\Backups;

use Flint\BaseComponent;
use Flint\HookManager;
use Flint\Paths;

/**
 * Backups Component.
 *
 * Creates site backups (config + content) as tarballs.
 * Emails admin with a secure 24-hour download link.
 * Automatically cleans up expired backups.
 */
class Backups extends BaseComponent
{
    private static string $metadataDir = '';

    /**
     * Component initialization.
     */
    protected static function onInit(): void
    {
        self::$metadataDir = self::$app->root . '/site/submissions/backups';

        // Ensure metadata directory exists.
        safe_mkdir(self::$metadataDir, Paths::$siteDir, 0755);

        // Ensure base uploads directory has .htaccess protection.
        $uploadsDir = self::$app->root . '/site/uploads';
        safe_mkdir($uploadsDir, Paths::$siteDir, 0755);

        $htaccess = $uploadsDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            // Prevent PHP execution in uploads directory.
            $htaccessContent = <<<HTACCESS
# Prevent PHP execution in uploads directory
<FilesMatch "\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Prevent .htaccess override
<Files .htaccess>
    Order Allow,Deny
    Deny from all
</Files>
HTACCESS;
            safe_write_file($htaccess, $htaccessContent, Paths::$uploadsDir, LOCK_EX);
        }

        // Cleanup expired backups on init.
        self::cleanupExpired();
    }

    /**
     * Get backup directory for current month (yyyymm format).
     */
    private static function getBackupDir(?int $timestamp = null): string
    {
        $backupDir = self::$app->getUploadDir($timestamp);
        self::$app->ensureDir($backupDir);
        return $backupDir;
    }

    /**
     * Generate unique backup ID (backup-yyyymmdd-hhiiss-random).
     *
     * @return string Backup ID like 'backup-20250104-030000-abc123de'.
     */
    private static function generateBackupId(): string
    {
        $timestamp = date('Ymd-His');
        $random = bin2hex(random_bytes(4));
        return "backup-{$timestamp}-{$random}";
    }

    /**
     * Register component hooks.
     */
    protected static function registerHooks(): void
    {
        // Hook for manual backup trigger.
        self::registerHook('admin_panel_load', [self::class, 'onAdminLoad']);

        // Register custom routes for backup downloads.
        self::registerHook('custom_routes', [self::class, 'handleCustomRoutes']);

        // Register custom API endpoints for backup management.
        self::registerHook('custom_api_endpoints', [self::class, 'handleApiEndpoints']);

        // Register scheduled backup task.
        self::registerHook('register_scheduled_tasks', [self::class, 'registerScheduledTasks']);
    }

    /**
     * Register scheduled backup task with scheduler.
     */
    public static function registerScheduledTasks(array $context): void
    {
        $scheduler = $context['scheduler'] ?? null;
        if (!$scheduler) {
            return;
        }

        // Get backup schedule configuration.
        $scheduleType = self::getConfig('backups.schedule', 'manual');

        // Don't register if schedule is manual (admin-triggered only).
        if ($scheduleType === 'manual') {
            return;
        }

        // Build schedule configuration.
        $schedule = ['type' => $scheduleType];

        // Add schedule-specific options.
        if ($scheduleType === 'daily') {
            $schedule['time'] = self::getConfig('backups.schedule_time', '03:00');
        } elseif ($scheduleType === 'weekly') {
            $schedule['time'] = self::getConfig('backups.schedule_time', '03:00');
            $schedule['day'] = (int)self::getConfig('backups.schedule_day', 0); // 0 = Sunday.
        } elseif ($scheduleType === 'monthly') {
            $schedule['time'] = self::getConfig('backups.schedule_time', '03:00');
            $schedule['day'] = (int)self::getConfig('backups.schedule_day', 1); // 1st of month.
        } elseif ($scheduleType === 'interval') {
            $schedule['seconds'] = (int)self::getConfig('backups.schedule_interval', 86400); // Default: 24 hours.
        }

        // Register task with scheduler.
        $scheduler->registerTask('backups_automatic', $schedule, function () {
            self::createBackup();
        });
    }

    /**
     * Admin panel load hook - adds backup button functionality.
     */
    public static function onAdminLoad(array $context): void
    {
        // Hook is registered, actual UI handled by admin panel.
    }

    /**
     * Handle custom routes (backup download).
     */
    public static function handleCustomRoutes(array $context): bool
    {
        $path = $context['path'] ?? '';

        // Handle backup download route: /backup/download/{token}.
        if (preg_match('#^/backup/download/([a-f0-9]{64})$#', $path, $matches)) {
            $token = $matches[1];
            self::serveBackup($token);
            return true; // Route handled.
        }

        return false; // Route not handled.
    }

    /**
     * Handle custom API endpoints (create, list, delete backups).
     */
    public static function handleApiEndpoints(array $context): bool
    {
        $path = $context['path'] ?? '';
        $method = $context['method'] ?? 'GET';
        $auth = $context['auth'] ?? null;

        // Create backup (admin only).
        if ($path === '/api/backup/create' && $method === 'POST') {
            if ($auth && !$auth->isAdmin()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return true;
            }

            $result = self::createBackup();
            echo json_encode($result);
            return true; // Request handled.
        }

        // List backups (admin only).
        if ($path === '/api/backup/list' && $method === 'GET') {
            if ($auth && !$auth->isAdmin()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return true;
            }

            $backups = self::listBackups();
            echo json_encode(['success' => true, 'backups' => $backups]);
            return true; // Request handled.
        }

        return false; // Request not handled.
    }

    /**
     * Create a backup of the site.
     *
     * @return array Result with success status and backup info.
     */
    public static function createBackup(): array
    {
        try {
            $timestamp = time();
            $backupId = self::generateBackupId();
            $archiveType = self::selectArchiveType();
            if ($archiveType === 'none') {
                return [
                    'success' => false,
                    'error' => 'No archive engine available for backups'
                ];
            }
            $extension = $archiveType === 'tar' ? 'tar.gz' : 'zip';
            $filename = "{$backupId}.{$extension}";

            // Get backup directory for current month (yyyymm).
            $backupDir = self::getBackupDir($timestamp);
            $filepath = $backupDir . '/' . $filename;

            // Create temporary directory for staging.
            $tempDir = $backupDir . '/temp-' . $backupId;
            ensure_storage_dir($tempDir);

            // Copy files to temp directory.
            self::stageBackupFiles($tempDir);

            // Create archive.
            $success = $archiveType === 'tar'
                ? self::createTarball($tempDir, $filepath)
                : self::createZipArchive($tempDir, $filepath);

            // Remove temp directory.
            self::removeDirectory($tempDir, Paths::$uploadsDir);

            if (!$success) {
                return [
                    'success' => false,
                    'error' => 'Failed to create tarball'
                ];
            }

            // Generate download token.
            $token = bin2hex(random_bytes(32));
            $expiry = $timestamp + self::getConfig('backups.link_expiration', 86400);

            // Store metadata.
            $metadata = [
                'backup_id' => $backupId,
                'filename' => $filename,
                'filepath' => $filepath,
                'token' => $token,
                'created' => $timestamp,
                'expires' => $expiry,
                'size' => filesize($filepath),
                'downloaded' => false
            ];

            self::writeJsonFile(
                self::$metadataDir . "/backup-{$backupId}.json",
                $metadata
            );

            // Send email to admin.
            $emailSent = self::sendBackupEmail($metadata);

            // Cleanup old backups.
            self::cleanupOldBackups();

            return [
                'success' => true,
                'backup_id' => $backupId,
                'filename' => $filename,
                'size' => $metadata['size'],
                'expires' => $expiry,
                'email_sent' => $emailSent
            ];
        } catch (\Exception $e) {
            self::log("Backup failed: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Stage files for backup.
     */
    private static function stageBackupFiles(string $tempDir): void
    {
        $root = self::$app->root;

        // Copy site/config.php if enabled.
        if (self::getConfig('backups.include_config', true)) {
            $configCandidates = [
                $root . '/site/config.php',
                $root . '/config.php',
                $root . '/app/config.php',
            ];
            $configSrc = null;
            foreach ($configCandidates as $candidate) {
                if (file_exists($candidate)) {
                    $configSrc = $candidate;
                    break;
                }
            }

            if ($configSrc !== null) {
                $configDest = $tempDir . '/site/config.php';
                $configDir = dirname($configDest);
                if (!is_dir($configDir)) {
                    safe_mkdir($configDir, Paths::$siteDir, 0755);
                }
                safe_copy($configSrc, $configDest, Paths::$siteDir, Paths::$uploadsDir);
            }
        }

        // Copy content directory.
        if (self::getConfig('backups.include_content', true)) {
            $contentSrc = $root . '/site';
            $contentDest = $tempDir . '/site';

            if (is_dir($contentSrc)) {
                self::copyDirectory($contentSrc, $contentDest, Paths::$siteDir, Paths::$uploadsDir);

                // Exclude uploads if configured.
                if (!self::getConfig('backups.include_uploads', true)) {
                    $uploadsDir = $contentDest . '/uploads';
                    if (is_dir($uploadsDir)) {
                        self::removeDirectory($uploadsDir, Paths::$uploadsDir);
                    }
                }
            }
        }

        // Copy themes if enabled.
        if (self::getConfig('backups.include_themes', false)) {
            $themesSrc = $root . '/site/themes';
            $themesDest = $tempDir . '/themes';
            if (is_dir($themesSrc)) {
                self::copyDirectory($themesSrc, $themesDest, Paths::$siteDir, Paths::$uploadsDir);
            }
        }
    }

    /**
     * Select the archive format based on available PHP extensions.
     */
    private static function selectArchiveType(): string
    {
        if (class_exists('\\PharData') && (int)ini_get('phar.readonly') === 0) {
            return 'tar';
        }

        if (class_exists('\\ZipArchive')) {
            return 'zip';
        }

        return 'none';
    }

    /**
     * Create tarball from directory using PharData.
     */
    private static function createTarball(string $sourceDir, string $outputFile): bool
    {
        if (!class_exists('\\PharData')) {
            self::log("Tarball creation failed: PharData not available", 'error');
            return false;
        }

        if ((int)ini_get('phar.readonly') !== 0) {
            self::log("Tarball creation failed: phar.readonly enabled", 'error');
            return false;
        }

        try {
            $outputFile = resolve_secure_path($outputFile, Paths::$uploadsDir, true);
            $tarPath = preg_replace('/\\.gz$/', '', $outputFile);
            if ($tarPath === null || $tarPath === '') {
                throw new \RuntimeException('Invalid tar path');
            }

            if (file_exists($tarPath)) {
                safe_unlink($tarPath, Paths::$uploadsDir);
            }

            $phar = new \PharData($tarPath);
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $item) {
                if ($item->isLink()) {
                    continue;
                }
                if ($item->isFile()) {
                    $relative = ltrim(str_replace($sourceDir, '', $item->getPathname()), '/');
                    $phar->addFile($item->getPathname(), $relative);
                }
            }

            $phar->compress(\Phar::GZ);
            unset($phar);

            if (file_exists($tarPath)) {
                safe_unlink($tarPath, Paths::$uploadsDir);
            }

            return file_exists($outputFile);
        } catch (\Throwable $error) {
            self::log("Tarball creation failed: " . $error->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Create ZIP archive from directory using ZipArchive.
     */
    private static function createZipArchive(string $sourceDir, string $outputFile): bool
    {
        if (!class_exists('\\ZipArchive')) {
            self::log("Zip creation failed: ZipArchive not available", 'error');
            return false;
        }

        try {
            $outputFile = resolve_secure_path($outputFile, Paths::$uploadsDir, true);
            $zip = new \ZipArchive();
            if ($zip->open($outputFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Unable to open ZIP output file');
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $item) {
                if ($item->isLink() || !$item->isFile()) {
                    continue;
                }
                $relative = ltrim(str_replace($sourceDir, '', $item->getPathname()), '/');
                $zip->addFile($item->getPathname(), $relative);
            }

            $zip->close();
            return file_exists($outputFile);
        } catch (\Throwable $error) {
            self::log("Zip creation failed: " . $error->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Copy directory recursively.
     */
    private static function copyDirectory(
        string $source,
        string $dest,
        string|array $sourceBase,
        string|array $destBase
    ): void {
        $source = resolve_secure_path($source, $sourceBase, false);
        $dest = resolve_secure_path($dest, $destBase, true);

        safe_mkdir($dest, $destBase, 0755);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $destPath = $dest . '/' . $iterator->getSubPathName();

            if ($item->isLink()) {
                continue;
            }

            if ($item->isDir()) {
                safe_mkdir($destPath, $destBase, 0755);
            } else {
                safe_copy((string)$item, $destPath, $sourceBase, $destBase);
            }
        }
    }

    /**
     * Remove directory recursively.
     */
    private static function removeDirectory(string $dir, string|array $allowedBase): void
    {
        $dir = resolve_secure_path($dir, $allowedBase, false);
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                safe_rmdir((string)$item, $allowedBase);
            } else {
                safe_unlink((string)$item, $allowedBase);
            }
        }

        safe_rmdir($dir, $allowedBase);
    }

    /**
     * Send backup email to admin.
     */
    private static function sendBackupEmail(array $metadata): bool
    {
        $adminEmail = self::$app->config['mail']['admin_email'] ?? '';
        if (!$adminEmail || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            self::log("Invalid admin email, cannot send backup link", 'error');
            return false;
        }

        $siteName = self::$app->config['site']['name'] ?? 'Flint';
        $siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                   . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

        $downloadUrl = $siteUrl . '/backup/download/' . $metadata['token'];
        $expiresDate = date('Y-m-d H:i:s', $metadata['expires']);
        $sizeFormatted = self::formatBytes($metadata['size']);

        $subject = "[{$siteName}] Site Backup Ready";

        $body = <<<EMAIL
Site Backup Complete

Your backup is ready for download:

Backup Details:
- Created: {$expiresDate}
- Size: {$sizeFormatted}
- Filename: {$metadata['filename']}

Download Link (valid for 24 hours):
{$downloadUrl}

This link will expire on: {$expiresDate}

The backup includes:
- Site configuration
- Content directory (pages, blocks, components)
- Form submissions and event logs
- Theme files (if enabled)
- Uploaded files (if enabled)

Important:
- Do not share this link
- Link expires automatically after 24 hours
- Backup file will be deleted after expiration
- Store backup in a secure location

---
{$siteName}
Automated Backup System
EMAIL;

        $headers = "From: {$siteName} <noreply@{$_SERVER['SERVER_NAME']}>\r\n";
        $headers .= "Reply-To: {$adminEmail}\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "X-Mailer: Flint Backups\r\n";

        $sent = mail($adminEmail, $subject, $body, $headers);

        if ($sent) {
            self::log("Backup email sent to {$adminEmail}");
        } else {
            self::log("Failed to send backup email", 'error');
        }

        return $sent;
    }

    /**
     * Serve backup file for download.
     */
    public static function serveBackup(string $token): bool
    {
        // Find backup by token.
        $metadata = self::findBackupByToken($token);

        if (!$metadata) {
            http_response_code(404);
            echo "Backup not found or expired";
            return false;
        }

        // Check expiration.
        if (time() > $metadata['expires']) {
            http_response_code(410);
            echo "Download link has expired";
            self::deleteBackup($metadata['backup_id']);
            return false;
        }

        // Check if file exists.
        if (!file_exists($metadata['filepath'])) {
            http_response_code(404);
            echo "Backup file not found";
            return false;
        }

        // Mark as downloaded.
        $metadata['downloaded'] = true;
        $metadata['downloaded_at'] = time();
        self::writeJsonFile(
            self::$metadataDir . "/backup-{$metadata['backup_id']}.json",
            $metadata
        );

        // Serve file.
        header('Content-Type: application/x-gzip');
        header('Content-Disposition: attachment; filename="' . $metadata['filename'] . '"');
        header('Content-Length: ' . filesize($metadata['filepath']));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');

        readfile($metadata['filepath']);

        self::log("Backup downloaded: {$metadata['backup_id']}");

        return true;
    }

    /**
     * Find backup metadata by token.
     */
    private static function findBackupByToken(string $token): ?array
    {
        $files = glob(self::$metadataDir . '/backup-*.json');

        foreach ($files as $file) {
            $metadata = read_json_file($file);
            if ($metadata && isset($metadata['token']) && $metadata['token'] === $token) {
                return $metadata;
            }
        }

        return null;
    }

    /**
     * Cleanup expired backups.
     */
    private static function cleanupExpired(): void
    {
        $files = glob(self::$metadataDir . '/backup-*.json');
        $now = time();

        foreach ($files as $file) {
            $metadata = read_json_file($file);

            if ($metadata && $now > $metadata['expires']) {
                self::deleteBackup($metadata['backup_id']);
                self::log("Cleaned up expired backup: {$metadata['backup_id']}");
            }
        }
    }

    /**
     * Cleanup old backups (keep only max_backups).
     */
    private static function cleanupOldBackups(): void
    {
        $maxBackups = self::getConfig('backups.max_backups', 10);
        $files = glob(self::$metadataDir . '/backup-*.json');

        // Sort by creation time (newest first).
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Delete old backups.
        $count = 0;
        foreach ($files as $file) {
            $count++;
            if ($count > $maxBackups) {
                $metadata = read_json_file($file);
                if ($metadata) {
                    self::deleteBackup($metadata['backup_id']);
                    self::log("Cleaned up old backup: {$metadata['backup_id']}");
                }
            }
        }
    }

    /**
     * Delete a backup and its metadata.
     */
    private static function deleteBackup(string $backupId): void
    {
        // Read metadata to get filepath.
        $metadataFile = self::$metadataDir . "/backup-{$backupId}.json";
        $metadata = null;

        if (file_exists($metadataFile)) {
            $metadata = read_json_file($metadataFile);
            safe_unlink($metadataFile, Paths::$siteDir);
        }

        // Delete tarball using filepath from metadata.
        if ($metadata && isset($metadata['filepath']) && file_exists($metadata['filepath'])) {
            safe_unlink($metadata['filepath'], Paths::$uploadsDir);
        } else {
            // Fallback: search uploads directory structure.
            $uploadsDir = self::$app->root . '/site/uploads';
            $pattern = $uploadsDir . '/*/backup-' . $backupId . '.*';
            $files = glob($pattern);
            foreach ($files as $file) {
                if (is_file($file)) {
                    safe_unlink($file, Paths::$uploadsDir);
                }
            }
        }
    }

    /**
     * Format bytes to human readable.
     */
    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get all backups info.
     */
    public static function listBackups(): array
    {
        $files = glob(self::$metadataDir . '/backup-*.json');
        $backups = [];

        foreach ($files as $file) {
            $metadata = read_json_file($file);
            if ($metadata) {
                $metadata['expired'] = time() > $metadata['expires'];
                $metadata['size_formatted'] = self::formatBytes($metadata['size']);
                $backups[] = $metadata;
            }
        }

        // Sort by creation time (newest first).
        usort($backups, function ($a, $b) {
            return $b['created'] - $a['created'];
        });

        return $backups;
    }
}
