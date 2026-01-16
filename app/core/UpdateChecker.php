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
    private string $cacheFile;
    private ?string $lastError = null;
    private const CACHE_DURATION = 3600; // 1 hour
    private const UPDATE_REPO = 'clientcoffee/flintcms';

    public function __construct(string $appDir, string $rootDir)
    {
        $this->appDir = $appDir;
        $this->root = $rootDir;
        $this->cacheFile = $appDir . '/.update-cache.json';
    }

    /**
     * Check for available updates.
     * Returns update info or null if no update available.
     */
    public function checkForUpdates(): ?array
    {
        $this->lastError = null;

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
        $url = "https://api.github.com/repos/" . self::UPDATE_REPO . "/releases/latest";

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
        if ($response === false && function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'User-Agent: Flint/' . Version::VERSION,
                    'Accept: application/vnd.github.v3+json',
                ]);
                $response = curl_exec($ch);
            }
        }

        if ($response === false) {
            $this->lastError = 'Unable to reach GitHub for updates.';
            $fallback = $this->fetchLatestReleaseFromRedirect();
            if ($fallback) {
                $this->lastError = null;
                return $fallback;
            }
            return null;
        }

        $data = json_decode($response, true);
        if (!$data) {
            $this->lastError = 'Unexpected update response from GitHub.';
            $fallback = $this->fetchLatestReleaseFromRedirect();
            if ($fallback) {
                $this->lastError = null;
                return $fallback;
            }
            $tagFallback = $this->fetchLatestTag();
            if ($tagFallback) {
                $this->lastError = null;
                return $tagFallback;
            }
            return null;
        }

        if (isset($data['message'])) {
            $this->lastError = 'GitHub API error: ' . $data['message'];
            $fallback = $this->fetchLatestReleaseFromRedirect();
            if ($fallback) {
                $this->lastError = null;
                return $fallback;
            }
            $tagFallback = $this->fetchLatestTag();
            if ($tagFallback) {
                $this->lastError = null;
                return $tagFallback;
            }
            return null;
        }

        if (!isset($data['tag_name'])) {
            $this->lastError = 'Unexpected update response from GitHub.';
            $fallback = $this->fetchLatestReleaseFromRedirect();
            if ($fallback) {
                $this->lastError = null;
                return $fallback;
            }
            $tagFallback = $this->fetchLatestTag();
            if ($tagFallback) {
                $this->lastError = null;
                return $tagFallback;
            }
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
     * Fallback: resolve latest release via GitHub redirect.
     */
    private function fetchLatestReleaseFromRedirect(): ?array
    {
        $latestUrl = "https://github.com/" . self::UPDATE_REPO . "/releases/latest";
        $finalUrl = null;

        if (function_exists('curl_init')) {
            $ch = curl_init($latestUrl);
            if ($ch !== false) {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'User-Agent: Flint/' . Version::VERSION,
                ]);
                curl_exec($ch);
                $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            }
        }

        if (!$finalUrl) {
            $headers = @get_headers($latestUrl, true);
            if (is_array($headers) && isset($headers['Location'])) {
                $location = $headers['Location'];
                if (is_array($location)) {
                    $finalUrl = end($location);
                } else {
                    $finalUrl = $location;
                }
            }
        }

        if (!$finalUrl || !preg_match('~/tag/v?([^/]+)$~', $finalUrl, $matches)) {
            return null;
        }

        $version = ltrim($matches[1], 'v');
        $tag = 'v' . $version;
        $downloadUrl = "https://github.com/" . self::UPDATE_REPO . "/archive/refs/tags/" . $tag . ".zip";

        return [
            'version' => $version,
            'url' => $finalUrl,
            'download_url' => $downloadUrl,
            'body' => '',
            'published_at' => '',
        ];
    }

    /**
     * Fallback: resolve the latest tag when no releases are published.
     */
    private function fetchLatestTag(): ?array
    {
        $url = "https://api.github.com/repos/" . self::UPDATE_REPO . "/tags?per_page=1";

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
        if ($response === false && function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'User-Agent: Flint/' . Version::VERSION,
                    'Accept: application/vnd.github.v3+json',
                ]);
                $response = curl_exec($ch);
            }
        }

        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data[0]['name'])) {
            return null;
        }

        $tagName = (string)$data[0]['name'];
        $version = ltrim($tagName, 'v');
        $downloadUrl = $data[0]['zipball_url'] ?? null;
        if (!$downloadUrl) {
            $downloadUrl = "https://github.com/" . self::UPDATE_REPO . "/archive/refs/tags/" . $tagName . ".zip";
        }

        return [
            'version' => $version,
            'url' => "https://github.com/" . self::UPDATE_REPO . "/tree/" . $tagName,
            'download_url' => $downloadUrl,
            'body' => '',
            'published_at' => '',
        ];
    }

    /**
     * Get the last update check error, if any.
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
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

        if (!isset($cache['current_version']) || $cache['current_version'] !== Version::VERSION) {
            return null;
        }

        $duration = isset($cache['duration']) ? (int)$cache['duration'] : self::CACHE_DURATION;
        if ($duration <= 0) {
            $duration = self::CACHE_DURATION;
        }

        // Check if cache is expired
        if (time() - $cache['timestamp'] > $duration) {
            return null;
        }

        return $cache['data'] ?? null;
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
            'current_version' => Version::VERSION,
        ];

        safe_write_file($this->cacheFile, json_encode($cache, JSON_PRETTY_PRINT), $this->appDir);
    }

    /**
     * Clear update cache.
     */
    public function clearCache(): void
    {
        if (file_exists($this->cacheFile)) {
            safe_unlink($this->cacheFile, $this->appDir);
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

        if (!safe_write_file($zipFile, $data, sys_get_temp_dir(), LOCK_EX)) {
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
            if (!$this->recursiveRemoveDirectory($appTarget, $this->root)) {
                throw new \Exception("Failed to remove old app directory");
            }

            if (!$this->moveDirectory($appSource, $appTarget)) {
                throw new \Exception("Failed to move new app directory");
            }

            // Clean up
            $this->recursiveRemoveDirectory($tempExtractDir, sys_get_temp_dir());
            safe_unlink($zipFile, sys_get_temp_dir());

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

        return $this->recursiveCopy($appDir, $backupDir, $this->root, $this->root);
    }

    /**
     * Restore from backup.
     */
    private function restoreBackup(string $backupDir): bool
    {
        $appDir = $this->root . '/app';

        // Remove failed update
        if (is_dir($appDir)) {
            $this->recursiveRemoveDirectory($appDir, $this->root);
        }

        // Restore backup
        return safe_rename($backupDir, $appDir, $this->root, $this->root);
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
    private function recursiveCopy(
        string $src,
        string $dst,
        string|array $allowedSrcBases,
        string|array $allowedDestBases
    ): bool {
        if (!is_dir($src)) {
            return false;
        }

        if (!safe_mkdir($dst, $allowedDestBases, 0755)) {
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
                if (!$this->recursiveCopy($srcPath, $dstPath, $allowedSrcBases, $allowedDestBases)) {
                    return false;
                }
            } else {
                if (!safe_copy($srcPath, $dstPath, $allowedSrcBases, $allowedDestBases)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Move a directory across filesystems with a copy fallback.
     */
    private function moveDirectory(string $src, string $dst): bool
    {
        if (safe_rename($src, $dst, sys_get_temp_dir(), $this->root)) {
            return true;
        }

        if (!$this->recursiveCopy($src, $dst, sys_get_temp_dir(), $this->root)) {
            if (is_dir($dst)) {
                $this->recursiveRemoveDirectory($dst, $this->root);
            }
            return false;
        }

        $this->recursiveRemoveDirectory($src, sys_get_temp_dir());
        return true;
    }

    /**
     * Recursively remove directory.
     */
    private function recursiveRemoveDirectory(string $dir, string|array $allowedBaseDirs): bool
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
            if (is_dir($path)) {
                $this->recursiveRemoveDirectory($path, $allowedBaseDirs);
            } else {
                safe_unlink($path, $allowedBaseDirs);
            }
        }

        return safe_rmdir($dir, $allowedBaseDirs);
    }
}
