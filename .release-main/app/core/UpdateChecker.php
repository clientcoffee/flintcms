<?php

namespace Flint;

/**
 * Update checker for Flint.
 * Checks GitHub releases for new versions.
 */
class UpdateChecker
{
    private string $root;
    private string $appDir;
    private array $config;
    private string $cacheFile;
    private const CACHE_DURATION = 3600; // 1 hour
    private const GITHUB_REPO = 'yourname/flint'; // Update with actual repo

    public function __construct(string $appDir, string $rootDir, array $config)
    {
        $this->appDir = $appDir;
        $this->root = $rootDir;
        $this->config = $config;
        $this->cacheFile = $appDir . '/.update-cache.json';
    }

    /**
     * Check for available updates.
     * Returns update info or null if no update available.
     */
    public function checkForUpdates(): ?array
    {
        // Check cache first
        if ($cached = $this->getCachedUpdate()) {
            return $cached;
        }

        // Fetch latest release from GitHub
        $latest = $this->fetchLatestRelease();
        if (!$latest) {
            return null;
        }

        // Compare versions
        if (!Version::isNewer($latest['version'])) {
            // Cache "no update" for shorter duration
            $this->cacheUpdate(null, 300); // 5 minutes
            return null;
        }

        // Update available
        $updateInfo = [
            'version' => $latest['version'],
            'url' => $latest['url'],
            'download_url' => $latest['download_url'],
            'release_notes' => $latest['body'] ?? '',
            'published_at' => $latest['published_at'] ?? '',
            'current_version' => Version::VERSION,
        ];

        // Cache the result
        $this->cacheUpdate($updateInfo);

        return $updateInfo;
    }

    /**
     * Fetch latest release from GitHub API.
     */
    private function fetchLatestRelease(): ?array
    {
        $url = "https://api.github.com/repos/" . self::GITHUB_REPO . "/releases/latest";

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: Flint/' . Version::VERSION,
                    'Accept: application/vnd.github.v3+json'
                ],
                'timeout' => 5,
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['tag_name'])) {
            return null;
        }

        // Extract version from tag (e.g., "v0.2.0" -> "0.2.0")
        $version = ltrim($data['tag_name'], 'v');

        // Find ZIP asset for download
        $downloadUrl = null;
        if (isset($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if (str_ends_with($asset['name'], '.zip')) {
                    $downloadUrl = $asset['browser_download_url'];
                    break;
                }
            }
        }

        // Fallback to zipball if no ZIP asset
        if (!$downloadUrl) {
            $downloadUrl = $data['zipball_url'] ?? null;
        }

        return [
            'version' => $version,
            'url' => $data['html_url'],
            'download_url' => $downloadUrl,
            'body' => $data['body'] ?? '',
            'published_at' => $data['published_at'] ?? '',
        ];
    }

    /**
     * Get cached update info.
     */
    private function getCachedUpdate(): ?array
    {
        if (!file_exists($this->cacheFile)) {
            return null;
        }

        $cache = json_decode(file_get_contents($this->cacheFile), true);
        if (!$cache || !isset($cache['timestamp'])) {
            return null;
        }

        // Check if cache is expired
        if (time() - $cache['timestamp'] > self::CACHE_DURATION) {
            return null;
        }

        return $cache['data'];
    }

    /**
     * Cache update info.
     */
    private function cacheUpdate(?array $data, int $duration = self::CACHE_DURATION): void
    {
        $cache = [
            'timestamp' => time(),
            'duration' => $duration,
            'data' => $data,
        ];

        @file_put_contents($this->cacheFile, json_encode($cache, JSON_PRETTY_PRINT));
    }

    /**
     * Clear update cache.
     */
    public function clearCache(): void
    {
        if (file_exists($this->cacheFile)) {
            @unlink($this->cacheFile);
        }
    }

    /**
     * Download update package.
     */
    public function downloadUpdate(string $downloadUrl): ?string
    {
        $tmpDir = sys_get_temp_dir();
        $zipFile = $tmpDir . '/flint-update-' . time() . '.zip';

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: Flint/' . Version::VERSION,
                ],
                'timeout' => 30,
            ]
        ]);

        $data = @file_get_contents($downloadUrl, false, $context);
        if ($data === false) {
            return null;
        }

        if (@file_put_contents($zipFile, $data) === false) {
            return null;
        }

        return $zipFile;
    }

    /**
     * Apply update from downloaded package.
     */
    public function applyUpdate(string $zipFile): bool
    {
        // Verify ZIP file exists
        if (!file_exists($zipFile)) {
            return false;
        }

        // Create backup of current app directory
        $backupDir = $this->root . '/app-backup-' . time();
        if (!$this->backupAppDirectory($backupDir)) {
            return false;
        }

        try {
            // Extract ZIP
            $zip = new \ZipArchive();
            if ($zip->open($zipFile) !== true) {
                throw new \Exception("Failed to open ZIP file");
            }

            $tempExtractDir = sys_get_temp_dir() . '/flint-extract-' . time();
            if (!$zip->extractTo($tempExtractDir)) {
                $zip->close();
                throw new \Exception("Failed to extract ZIP");
            }
            $zip->close();

            // Find the app directory in extracted files
            // GitHub zipballs have a root folder like "username-repo-commit/"
            $appSource = $this->findAppDirectory($tempExtractDir);
            if (!$appSource) {
                throw new \Exception("Could not find app/ directory in update package");
            }

            // Replace app directory
            $appTarget = $this->root . '/app';
            if (!$this->recursiveRemoveDirectory($appTarget)) {
                throw new \Exception("Failed to remove old app directory");
            }

            if (!rename($appSource, $appTarget)) {
                throw new \Exception("Failed to move new app directory");
            }

            // Clean up
            $this->recursiveRemoveDirectory($tempExtractDir);
            @unlink($zipFile);

            // Clear update cache
            $this->clearCache();

            return true;
        } catch (\Exception $e) {
            // Restore backup on failure
            if (is_dir($backupDir)) {
                $this->restoreBackup($backupDir);
            }
            return false;
        }
    }

    /**
     * Backup app directory.
     */
    private function backupAppDirectory(string $backupDir): bool
    {
        $appDir = $this->root . '/app';
        if (!is_dir($appDir)) {
            return false;
        }

        return $this->recursiveCopy($appDir, $backupDir);
    }

    /**
     * Restore from backup.
     */
    private function restoreBackup(string $backupDir): bool
    {
        $appDir = $this->root . '/app';

        // Remove failed update
        if (is_dir($appDir)) {
            $this->recursiveRemoveDirectory($appDir);
        }

        // Restore backup
        return rename($backupDir, $appDir);
    }

    /**
     * Find app directory in extracted files.
     */
    private function findAppDirectory(string $dir): ?string
    {
        // Check if app/ exists directly
        if (is_dir($dir . '/app')) {
            return $dir . '/app';
        }

        // Check one level deep (for GitHub zipballs)
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $subDir = $dir . '/' . $item;
            if (is_dir($subDir . '/app')) {
                return $subDir . '/app';
            }
        }

        return null;
    }

    /**
     * Recursively copy directory.
     */
    private function recursiveCopy(string $src, string $dst): bool
    {
        if (!is_dir($src)) {
            return false;
        }

        if (!mkdir($dst, 0755, true)) {
            return false;
        }

        $items = scandir($src);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $srcPath = $src . '/' . $item;
            $dstPath = $dst . '/' . $item;

            if (is_dir($srcPath)) {
                if (!$this->recursiveCopy($srcPath, $dstPath)) {
                    return false;
                }
            } else {
                if (!copy($srcPath, $dstPath)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Recursively remove directory.
     */
    private function recursiveRemoveDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return true;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }

        return rmdir($dir);
    }
}
