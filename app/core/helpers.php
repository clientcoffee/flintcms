<?php

use Flint\HookManager;
use Flint\Paths;
use Flint\ThemeContext;

if (!function_exists('esc_html')) {
    /**
     * Escape HTML output safely.
     */
    function esc_html(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value) || is_object($value)) {
            return '';
        }

        $text = str_replace("\0", '', (string)$value);

        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false);
    }
}

if (!function_exists('prop')) {
    /**
     * Read a prop value with a default fallback.
     */
    function prop(array $props, string $key, mixed $default = ''): mixed
    {
        return $props[$key] ?? $default;
    }
}

if (!function_exists('content_or_prop')) {
    /**
     * Prefer inline content over a prop value.
     */
    function content_or_prop(string $content, array $props, string $propKey): string
    {
        $trimmedContent = trim($content);
        if ($trimmedContent !== '') {
            return $trimmedContent;
        }

        return trim((string)($props[$propKey] ?? ''));
    }
}

if (!function_exists('html_attrs')) {
    /**
     * Build HTML attributes from a key/value array.
     */
    function html_attrs(array $attributes): string
    {
        $attributeParts = [];

        foreach ($attributes as $attributeName => $attributeValue) {
            if ($attributeValue === true) {
                $attributeParts[] = esc_html($attributeName);
                continue;
            }

            if ($attributeValue !== false && $attributeValue !== null) {
                $escapedName = esc_html($attributeName);
                $escapedValue = esc_html((string)$attributeValue);
                $attributeParts[] = $escapedName . '="' . $escapedValue . '"';
            }
        }

        return implode(' ', $attributeParts);
    }
}

if (!function_exists('parse_key_value')) {
    /**
     * Parse key/value pairs from a delimited list.
     */
    function parse_key_value(string $text, string $delimiter = '|'): array
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return [];
        }

        $lines = preg_split('/\r?\n|;/', $trimmed) ?: [];
        $parsedPairs = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, $delimiter)) {
                continue;
            }

            $parts = explode($delimiter, $line, 2);
            $key = trim($parts[0]);
            $value = trim($parts[1] ?? '');

            if ($key !== '' && $value !== '') {
                $parsedPairs[] = ['key' => $key, 'value' => $value];
            }
        }

        return $parsedPairs;
    }
}

if (!function_exists('parse_markdown_list')) {
    /**
     * Parse a markdown bullet list into a nested array.
     */
    function parse_markdown_list(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        $listLines = preg_split('/\r?\n/', $text) ?: [];
        $rootItems = [];
        $stackFrames = [
            ['indent' => -1, 'items' => &$rootItems],
        ];
        $stackDepth = 1;

        foreach ($listLines as $lineText) {
            if (trim($lineText) === '') {
                continue;
            }

            if (!preg_match('/^(\s*)([-*])\s+(.*)$/', $lineText, $lineMatch)) {
                continue;
            }

            $lineIndentation = strlen($lineMatch[1]);
            $lineBody = trim($lineMatch[3]);
            $itemLabel = '';
            $itemUrl = '';

            if (preg_match('/^\[(.+)\]\((.+)\)$/', $lineBody, $linkMatch)) {
                $itemLabel = trim($linkMatch[1]);
                $itemUrl = trim($linkMatch[2]);
            } elseif (preg_match('/^(.+)\(([^)]+)\)$/', $lineBody, $plainMatch)) {
                $itemLabel = trim($plainMatch[1]);
                $itemUrl = trim($plainMatch[2]);
            } else {
                $itemLabel = $lineBody;
            }

            $navItem = [
                'label' => $itemLabel,
                'url' => $itemUrl,
                'children' => [],
            ];

            while ($stackDepth > 1 && $lineIndentation <= $stackFrames[$stackDepth - 1]['indent']) {
                array_pop($stackFrames);
                $stackDepth--;
            }

            $parentItems = &$stackFrames[$stackDepth - 1]['items'];
            $parentItems[] = $navItem;
            $lastIndex = null;
            foreach ($parentItems as $itemIndex => $unusedValue) {
                $lastIndex = $itemIndex;
            }

            $stackFrames[] = [
                'indent' => $lineIndentation,
                'items' => &$parentItems[$lastIndex]['children'],
            ];
            $stackDepth++;
        }

        return $rootItems;
    }
}

if (!function_exists('parse_yaml')) {
    /**
     * Parse simple YAML-style key/value pairs.
     */
    function parse_yaml(string $yamlText): array
    {
        $metadata = [];
        $lines = explode("\n", $yamlText);

        foreach ($lines as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            [$keyText, $valueText] = explode(':', $line, 2);
            $key = trim($keyText);
            if ($key === '') {
                continue;
            }

            $value = trim(trim($valueText), "\"'");
            $metadata[$key] = $value;
        }

        return $metadata;
    }
}

if (!function_exists('slug_from_path')) {
    /**
     * Convert a relative content path into a route slug.
     */
    function slug_from_path(string $relativeFile): string
    {
        $relativeFile = str_replace('\\', '/', $relativeFile);
        $trimmed = preg_replace('/\.(md|mdx)$/i', '', $relativeFile);
        $trimmed = ltrim($trimmed, '/');
        $baseName = basename((string)$trimmed);

        if ($baseName === 'index') {
            $dir = trim(dirname((string)$trimmed), '.');
            if ($dir === '' || $dir === '.') {
                return '/';
            }
            return '/' . $dir;
        }

        return '/' . $trimmed;
    }
}

if (!function_exists('extract_frontmatter')) {
    /**
     * Extract YAML frontmatter from a markdown file.
     */
    function extract_frontmatter(string $filePath): array
    {
        $contents = file_get_contents($filePath);
        if ($contents === false || !str_starts_with($contents, "---")) {
            return [];
        }

        $parts = preg_split('/^---$/m', $contents, 3);
        if (!is_array($parts) || count($parts) !== 3) {
            return [];
        }

        return parse_yaml($parts[1]);
    }
}

if (!function_exists('resolve_status')) {
    /**
     * Normalize published/hidden/draft page status from metadata.
     */
    function resolve_status(array $meta): string
    {
        $status = strtolower(trim((string)($meta['status'] ?? 'published')));
        if ($status === '') {
            $status = 'published';
        }

        $draftFlag = strtolower(trim((string)($meta['draft'] ?? '')));
        if (in_array($draftFlag, ['1', 'true', 'yes', 'on'], true)) {
            $status = 'draft';
        }

        return $status;
    }
}

if (!function_exists('resolve_current_context')) {
    /**
     * Resolve the current page context from the request path.
     *
     * @return array{filePath:string,relativeFile:string,relativeDir:string,dirPath:string,slug:string,isIndex:bool}|null
     */
    function resolve_current_context(): ?array
    {
        $pagesDir = isset(Paths::$pagesDir) ? Paths::$pagesDir : '';
        if ($pagesDir === '' || !is_dir($pagesDir)) {
            return null;
        }

        $path = (string)ThemeContext::get('currentPath', '');
        if ($path === '') {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        }

        if ($path === '') {
            $path = '/';
        }

        $normalizedSlug = trim($path, '/');
        if ($normalizedSlug === '') {
            $normalizedSlug = 'index';
        }

        if (
            str_contains($normalizedSlug, '..') ||
            str_contains($normalizedSlug, '\\') ||
            str_contains($normalizedSlug, "\0") ||
            !preg_match('/^[a-zA-Z0-9\/_-]+$/', $normalizedSlug)
        ) {
            return null;
        }

        $candidatePaths = [
            $pagesDir . '/' . $normalizedSlug . '.md',
            $pagesDir . '/' . $normalizedSlug . '.mdx',
            $pagesDir . '/' . $normalizedSlug . '/index.md',
            $pagesDir . '/' . $normalizedSlug . '/index.mdx',
        ];

        $filePath = '';
        foreach ($candidatePaths as $candidatePath) {
            if (file_exists($candidatePath)) {
                $filePath = $candidatePath;
                break;
            }
        }

        if ($filePath === '') {
            return null;
        }

        $relativeFile = ltrim(str_replace($pagesDir, '', $filePath), '/');
        $relativeDir = trim(dirname($relativeFile), '.');
        if ($relativeDir === '.') {
            $relativeDir = '';
        }
        $dirPath = $relativeDir === '' ? $pagesDir : $pagesDir . '/' . $relativeDir;

        $baseName = basename(preg_replace('/\.(md|mdx)$/i', '', $relativeFile));
        $isIndex = $baseName === 'index';

        return [
            'filePath' => $filePath,
            'relativeFile' => $relativeFile,
            'relativeDir' => $relativeDir,
            'dirPath' => $dirPath,
            'slug' => slug_from_path($relativeFile),
            'isIndex' => $isIndex,
        ];
    }
}

if (!function_exists('is_admin')) {
    /**
     * Read admin state from theme context.
     */
    function is_admin(): bool
    {
        return (bool)ThemeContext::get('isAdmin', false);
    }
}

if (!function_exists('list_child_pages')) {
    /**
     * List child pages for a directory, sorted by order/date/label.
     */
    function list_child_pages(
        string $directory,
        string $relativeDir,
        bool $includePrivate,
        bool $excludeIndex = true
    ): array {
        if (!is_dir($directory)) {
            return [];
        }

        $entries = scandir($directory);
        if ($entries === false) {
            return [];
        }

        $items = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }

            $fullPath = $directory . '/' . $entry;
            if (is_dir($fullPath)) {
                continue;
            }

            if (!preg_match('/\.(md|mdx)$/i', $entry)) {
                continue;
            }

            $baseName = basename($entry, '.' . pathinfo($entry, PATHINFO_EXTENSION));
            if ($excludeIndex && $baseName === 'index') {
                continue;
            }

            $relativeFile = ltrim($relativeDir . '/' . $entry, '/');
            $meta = extract_frontmatter($fullPath);
            $status = resolve_status($meta);

            if (!$includePrivate && in_array($status, ['hidden', 'draft'], true)) {
                continue;
            }

            $label = trim((string)($meta['title'] ?? ''));
            if ($label !== '') {
                $label = strip_inline_markdown($label);
            }

            if ($label === '') {
                $slug = slug_from_path($relativeFile);
                if ($slug === '/') {
                    $label = 'home';
                } else {
                    $trimmed = preg_replace('/\.(md|mdx)$/i', '', $relativeFile);
                    $baseName = basename((string)$trimmed);
                    $label = $baseName === 'index' ? 'index' : $baseName;
                }
            }

            $order = null;
            if (array_key_exists('order', $meta)) {
                $rawOrder = trim((string)$meta['order']);
                if ($rawOrder !== '' && is_numeric($rawOrder)) {
                    $order = (int)$rawOrder;
                }
            }

            $dateValue = null;
            $rawDate = trim((string)($meta['date'] ?? ''));
            if ($rawDate !== '') {
                $timestamp = strtotime($rawDate);
                if ($timestamp !== false) {
                    $dateValue = $timestamp;
                }
            }

            $items[] = [
                'label' => $label,
                'path' => slug_from_path($relativeFile),
                'relativeFile' => $relativeFile,
                'order' => $order,
                'date' => $dateValue,
            ];
        }

        $hasOrder = false;
        $hasDate = false;
        foreach ($items as $item) {
            if ($item['order'] !== null) {
                $hasOrder = true;
            }
            if ($item['date'] !== null) {
                $hasDate = true;
            }
        }

        usort($items, function (array $a, array $b) use ($hasOrder, $hasDate) {
            if ($hasOrder) {
                return ($a['order'] ?? PHP_INT_MAX) <=> ($b['order'] ?? PHP_INT_MAX);
            }

            if ($hasDate) {
                return ($a['date'] ?? PHP_INT_MAX) <=> ($b['date'] ?? PHP_INT_MAX);
            }

            return strcasecmp($a['label'], $b['label']);
        });

        return $items;
    }
}

if (!function_exists('get_app')) {
    /**
     * Fetch the current application instance when available.
     */
    function get_app(): ?\Flint\App
    {
        $parser = \Flint\Parser::getCurrentInstance();
        if ($parser) {
            return $parser->getApplication();
        }

        $app = ThemeContext::get('app');
        return $app instanceof \Flint\App ? $app : null;
    }
}

if (!function_exists('get_config')) {
    /**
     * Read a config value using dot notation.
     */
    function get_config(string $keyPath, mixed $default = null, ?array $config = null): mixed
    {
        if ($config === null) {
            $app = get_app();
            if ($app instanceof \Flint\App) {
                $config = $app->config ?? [];
            } else {
                $config = ['site' => ThemeContext::get('site', [])];
            }
        }

        $segments = explode('.', $keyPath);
        $currentValue = $config;

        foreach ($segments as $segment) {
            if (!is_array($currentValue) || !array_key_exists($segment, $currentValue)) {
                return $default;
            }

            $currentValue = $currentValue[$segment];
        }

        return $currentValue;
    }
}

if (!function_exists('register_hook')) {
    /**
     * Register a hook listener with the CMS.
     */
    function register_hook(string $eventName, callable $callback, int $priority = 100): void
    {
        HookManager::on($eventName, $callback, $priority);
    }
}

if (!function_exists('app_log')) {
    /**
     * Log a message to the app log file.
     */
    function app_log(string $message, string $level = 'info'): void
    {
        $app = get_app();
        if (!$app instanceof \Flint\App) {
            error_log('[' . $level . '] ' . $message);
            return;
        }

        $logDirectory = $app->appDir . '/storage/logs';
        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0755, true);
        }

        $logFilePath = $logDirectory . '/app.log';
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[{$timestamp}] [{$level}] {$message}\n";

        file_put_contents($logFilePath, $logLine, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('sanitize_email_header')) {
    /**
     * Sanitize email header values to prevent injection.
     */
    function sanitize_email_header(string $value): string
    {
        return str_replace(["\r", "\n", "\0", "%0a", "%0d"], '', $value);
    }
}

if (!function_exists('get_client_ip')) {
    /**
     * Resolve the best-guess client IP address.
     */
    function get_client_ip(): string
    {
        $headersToCheck = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headersToCheck as $headerName) {
            if (!empty($_SERVER[$headerName])) {
                $ipAddress = explode(',', $_SERVER[$headerName])[0];
                return trim($ipAddress);
            }
        }

        return '0.0.0.0';
    }
}

if (!function_exists('ensure_storage_dir')) {
    /**
     * Ensure a directory exists, creating it if needed.
     */
    function ensure_storage_dir(string $directoryPath, int $permissions = 0755): bool
    {
        if (is_dir($directoryPath)) {
            return true;
        }

        if (file_exists($directoryPath)) {
            return false;
        }

        return mkdir($directoryPath, $permissions, true);
    }
}

if (!function_exists('validate_form_nonce')) {
    /**
     * Validate a one-time form nonce encoded in the form token.
     */
    function validate_form_nonce(string $token, int $ttlSeconds = 3600, ?string $clientIp = null): bool
    {
        if ($token === '') {
            return false;
        }

        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return false;
        }

        $data = json_decode($decoded, true);
        if (!is_array($data)) {
            return false;
        }

        $created = (int)($data['created'] ?? 0);
        $nonce = (string)($data['nonce'] ?? '');
        if ($created <= 0 || $nonce === '') {
            return false;
        }

        $age = time() - $created;
        if ($age < 0 || $age > $ttlSeconds) {
            return false;
        }

        if ($clientIp !== null && isset($data['ip']) && $data['ip'] !== $clientIp) {
            return false;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['form_nonces']) || !is_array($_SESSION['form_nonces'])) {
            $_SESSION['form_nonces'] = [];
        }

        $cutoff = time() - $ttlSeconds;
        foreach ($_SESSION['form_nonces'] as $storedNonce => $storedAt) {
            if (!is_int($storedAt) || $storedAt < $cutoff) {
                unset($_SESSION['form_nonces'][$storedNonce]);
            }
        }

        if (isset($_SESSION['form_nonces'][$nonce])) {
            return false;
        }

        $_SESSION['form_nonces'][$nonce] = time();

        return true;
    }
}

if (!function_exists('is_absolute_path')) {
    /**
     * Check if a path is absolute.
     */
    function is_absolute_path(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return (bool)preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }
}

if (!function_exists('normalize_path')) {
    /**
     * Normalize a path without touching the filesystem.
     */
    function normalize_path(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $isAbsolute = str_starts_with($path, '/');
        $segments = explode('/', $path);
        $stack = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($stack);
                continue;
            }
            $stack[] = $segment;
        }

        $normalized = ($isAbsolute ? '/' : '') . implode('/', $stack);
        return $normalized === '' ? ($isAbsolute ? '/' : '.') : $normalized;
    }
}

if (!function_exists('path_is_within')) {
    /**
     * Check if a path is within a base directory.
     */
    function path_is_within(string $path, string $baseDir): bool
    {
        $path = rtrim(normalize_path($path), '/');
        $baseDir = rtrim(normalize_path($baseDir), '/');

        if ($path === $baseDir) {
            return true;
        }

        return str_starts_with($path, $baseDir . '/');
    }
}

if (!function_exists('path_has_symlink')) {
    /**
     * Detect symlinks between base and target path.
     */
    function path_has_symlink(string $path, string $baseDir): bool
    {
        if (!path_is_within($path, $baseDir)) {
            return true;
        }

        $relative = ltrim(substr(normalize_path($path), strlen(normalize_path($baseDir))), '/');
        if ($relative === '') {
            return false;
        }

        $current = rtrim(normalize_path($baseDir), '/');
        foreach (explode('/', $relative) as $segment) {
            $current .= '/' . $segment;
            if (is_link($current)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('resolve_secure_path')) {
    /**
     * Resolve a path to a safe absolute location within allowed bases.
     */
    function resolve_secure_path(string $path, string|array $allowedBaseDirs, bool $allowMissing = true): string
    {
        if (!is_absolute_path($path)) {
            throw new \RuntimeException('Path must be absolute');
        }

        if (preg_match('/[\x00-\x1F]/', $path)) {
            throw new \RuntimeException('Path contains control characters');
        }

        $bases = is_array($allowedBaseDirs) ? $allowedBaseDirs : [$allowedBaseDirs];
        $realBases = [];
        foreach ($bases as $base) {
            if (!is_string($base) || $base === '') {
                continue;
            }
            $realBase = realpath($base);
            if ($realBase !== false) {
                $realBases[] = normalize_path($realBase);
            }
        }

        if ($realBases === []) {
            throw new \RuntimeException('No valid base directories');
        }

        $realPath = realpath($path);
        if ($realPath === false) {
            if (!$allowMissing) {
                throw new \RuntimeException('Path not found');
            }
            $realDir = realpath(dirname($path));
            if ($realDir === false) {
                throw new \RuntimeException('Invalid directory path');
            }
            $realPath = normalize_path($realDir . '/' . basename($path));
        } else {
            $realPath = normalize_path($realPath);
        }

        foreach ($realBases as $base) {
            if (!path_is_within($realPath, $base)) {
                continue;
            }
            if (path_has_symlink($realPath, $base)) {
                throw new \RuntimeException('Symlink traversal blocked');
            }
            return $realPath;
        }

        throw new \RuntimeException('Path outside allowed base directories');
    }
}

if (!function_exists('safe_mkdir')) {
    /**
     * Create a directory within an allowed base.
     */
    function safe_mkdir(string $path, string|array $allowedBaseDirs, int $permissions = 0755): bool
    {
        $resolved = resolve_secure_path($path, $allowedBaseDirs, true);
        if (is_dir($resolved)) {
            return true;
        }

        return mkdir($resolved, $permissions, true);
    }
}

if (!function_exists('safe_write_file')) {
    /**
     * Write a file within an allowed base directory.
     */
    function safe_write_file(string $path, string $contents, string|array $allowedBaseDirs, int $flags = LOCK_EX): bool
    {
        $resolved = resolve_secure_path($path, $allowedBaseDirs, true);
        $dir = dirname($resolved);
        if (!is_dir($dir) && !safe_mkdir($dir, $allowedBaseDirs, 0755)) {
            return false;
        }

        return file_put_contents($resolved, $contents, $flags) !== false;
    }
}

if (!function_exists('safe_unlink')) {
    /**
     * Delete a file within an allowed base.
     */
    function safe_unlink(string $path, string|array $allowedBaseDirs): bool
    {
        $resolved = resolve_secure_path($path, $allowedBaseDirs, false);
        if (!is_file($resolved)) {
            return false;
        }

        return unlink($resolved);
    }
}

if (!function_exists('safe_rmdir')) {
    /**
     * Remove an empty directory within an allowed base.
     */
    function safe_rmdir(string $path, string|array $allowedBaseDirs): bool
    {
        $resolved = resolve_secure_path($path, $allowedBaseDirs, false);
        if (!is_dir($resolved)) {
            return false;
        }

        return rmdir($resolved);
    }
}

if (!function_exists('safe_copy')) {
    /**
     * Copy a file between allowed base directories.
     */
    function safe_copy(string $src, string $dst, string|array $allowedSrcBases, string|array $allowedDestBases): bool
    {
        $resolvedSrc = resolve_secure_path($src, $allowedSrcBases, false);
        $resolvedDst = resolve_secure_path($dst, $allowedDestBases, true);

        if (!is_file($resolvedSrc)) {
            return false;
        }

        $dir = dirname($resolvedDst);
        if (!is_dir($dir) && !safe_mkdir($dir, $allowedDestBases, 0755)) {
            return false;
        }

        return copy($resolvedSrc, $resolvedDst);
    }
}

if (!function_exists('safe_rename')) {
    /**
     * Rename a file or directory between allowed base directories.
     */
    function safe_rename(
        string $src,
        string $dst,
        string|array $allowedSrcBases,
        string|array $allowedDestBases
    ): bool {
        $resolvedSrc = resolve_secure_path($src, $allowedSrcBases, false);
        $resolvedDst = resolve_secure_path($dst, $allowedDestBases, true);

        $dir = dirname($resolvedDst);
        if (!is_dir($dir) && !safe_mkdir($dir, $allowedDestBases, 0755)) {
            return false;
        }

        return rename($resolvedSrc, $resolvedDst);
    }
}

if (!function_exists('safe_move_uploaded_file')) {
    /**
     * Move an uploaded file to a safe destination.
     */
    function safe_move_uploaded_file(string $tmpPath, string $destPath, string|array $allowedDestBases): bool
    {
        $resolvedDest = resolve_secure_path($destPath, $allowedDestBases, true);
        $dir = dirname($resolvedDest);
        if (!is_dir($dir) && !safe_mkdir($dir, $allowedDestBases, 0755)) {
            return false;
        }

        return move_uploaded_file($tmpPath, $resolvedDest);
    }
}

if (!function_exists('read_json_file')) {
    /**
     * Read a JSON file into an array or object.
     */
    function read_json_file(string $filePath, bool $associative = true): mixed
    {
        if (!file_exists($filePath) || !is_file($filePath)) {
            return null;
        }

        $jsonString = file_get_contents($filePath);
        if ($jsonString === false) {
            return null;
        }

        return json_decode($jsonString, $associative);
    }
}

if (!function_exists('e')) {
    /**
     * Shorthand for esc_html().
     */
    function e(mixed $value): string
    {
        return esc_html($value);
    }
}

if (!function_exists('render_flash_markdown')) {
    /**
     * Render limited markdown for flash messages (links + inline code).
     */
    function render_flash_markdown(string $message): string
    {
        $safe = esc_html($message);
        if ($safe === '') {
            return '';
        }

        $safe = preg_replace(
            '/`([^`]+)`/',
            '<code class="rounded bg-gray-100 px-1 py-0.5 font-mono text-xs">$1</code>',
            $safe
        );

        $safe = preg_replace_callback('/\\[([^\\]]+)\\]\\(([^\\s)]+)\\)/', function ($matches) {
            $url = $matches[2];
            if (!preg_match('/^(https?:\\/\\/|\\/)/i', $url)) {
                return $matches[0];
            }

            return '<a href="' . $url . '" class="text-indigo-600 underline break-all">' . $matches[1] . '</a>';
        }, $safe);

        return $safe;
    }
}

if (!function_exists('render_inline_markdown')) {
    /**
     * Render inline markdown without wrapping block-level tags.
     */
    function render_inline_markdown(string $text): string
    {
        $text = (string)$text;
        if ($text === '') {
            return '';
        }

        $app = ThemeContext::get('app');
        if ($app instanceof \Flint\App) {
            $parser = new \Flint\Parser($app);
            return $parser->renderInlineMarkdown($text);
        }

        return esc_html($text);
    }
}

if (!function_exists('strip_inline_markdown')) {
    /**
     * Convert inline markdown into plain text.
     */
    function strip_inline_markdown(string $text): string
    {
        $rendered = render_inline_markdown($text);
        if ($rendered === '') {
            return '';
        }

        $plain = trim(strip_tags($rendered));
        if ($plain === '') {
            return '';
        }

        return html_entity_decode($plain, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('hook')) {
    /**
     * Trigger a hook event.
     */
    function hook(string $name, array $context = []): mixed
    {
        return HookManager::trigger($name, $context);
    }
}

if (!function_exists('theme_styles')) {
    /**
     * Trigger the theme styles hook.
     */
    function theme_styles(): mixed
    {
        return hook('theme_styles');
    }
}

if (!function_exists('theme_scripts')) {
    /**
     * Trigger the theme scripts hook.
     */
    function theme_scripts(): mixed
    {
        return hook('theme_scripts');
    }
}

if (!function_exists('render_block')) {
    /**
     * Render a markdown block by name.
     */
    function render_block(string $name, string $fallback = ''): string
    {
        return \Components\Block::render(['name' => $name], $fallback);
    }
}

if (!function_exists('render_assets')) {
    /**
     * Render component assets for the requested position.
     */
    function render_assets(string $position = 'foot', ?array $assets = null): void
    {
        $assets = $assets ?? ThemeContext::get('componentAssets', []);
        $position = strtolower($position);
        if ($position === 'footer') {
            $position = 'foot';
        }

        if ($position === 'head') {
            foreach ($assets['styles'] ?? [] as $style) {
                $href = trim($style['href'] ?? '');
                if ($href === '') {
                    continue;
                }
                echo '<link rel="stylesheet" href="' . esc_html($href) . '">' . "\n";
            }

            $inlineStyles = [];
            foreach ($assets['inline_styles'] ?? [] as $inlineStyle) {
                $content = $inlineStyle['content'] ?? '';
                if ($content === '') {
                    continue;
                }

                $hasDangerousContent = (
                    stripos($content, '</style') !== false ||
                    stripos($content, '<script') !== false ||
                    stripos($content, 'javascript:') !== false ||
                    stripos($content, 'expression(') !== false
                );

                if ($hasDangerousContent) {
                    error_log('Security: Blocked potentially malicious inline style content');
                    continue;
                }

                $inlineStyles[] = $content;
            }

            if (!empty($inlineStyles)) {
                echo "<style>\n";
                foreach ($inlineStyles as $snippet) {
                    echo $snippet . "\n";
                }
                echo "</style>\n";
            }

            foreach ($assets['scripts'] ?? [] as $script) {
                $scriptPosition = strtolower((string)($script['position'] ?? 'footer'));
                if ($scriptPosition === 'footer') {
                    $scriptPosition = 'foot';
                }
                if ($scriptPosition !== 'head') {
                    continue;
                }

                $src = trim($script['src'] ?? '');
                if ($src === '') {
                    continue;
                }

                $typeAttr = '';
                if (!empty($script['type'])) {
                    $typeAttr = ' type="' . esc_html($script['type']) . '"';
                }

                echo '<script src="' . esc_html($src) . '"' . $typeAttr . "></script>\n";
            }

            return;
        }

        foreach ($assets['scripts'] ?? [] as $script) {
            $scriptPosition = strtolower((string)($script['position'] ?? 'footer'));
            if ($scriptPosition === 'footer') {
                $scriptPosition = 'foot';
            }
            if ($scriptPosition !== 'foot') {
                continue;
            }

            $src = trim($script['src'] ?? '');
            if ($src === '') {
                continue;
            }

            $typeAttr = '';
            if (!empty($script['type'])) {
                $typeAttr = ' type="' . esc_html($script['type']) . '"';
            }

            echo '<script src="' . esc_html($src) . '"' . $typeAttr . "></script>\n";
        }

        foreach ($assets['inline_scripts'] ?? [] as $inlineScript) {
            $inlinePosition = strtolower((string)($inlineScript['position'] ?? 'footer'));
            if ($inlinePosition === 'footer') {
                $inlinePosition = 'foot';
            }
            if ($inlinePosition !== 'foot') {
                continue;
            }

            $content = $inlineScript['content'] ?? '';
            if ($content === '') {
                continue;
            }

            $hasScriptInjection = (
                stripos($content, '</script') !== false ||
                stripos($content, '<script') !== false
            );

            if ($hasScriptInjection) {
                error_log('Security: Blocked potentially malicious inline script content');
                continue;
            }

            $typeAttr = '';
            if (!empty($inlineScript['type'])) {
                $typeAttr = ' type="' . esc_html($inlineScript['type']) . '"';
            }

            echo '<script' . $typeAttr . '>' . "\n";
            echo $content . "\n";
            echo "</script>\n";
        }
    }
}

if (!function_exists('theme_asset')) {
    /**
     * Build a theme asset URL for the active theme.
     */
    function theme_asset(string $path, ?string $themeName = null): string
    {
        $path = ltrim($path, '/');
        $themeName = $themeName ?? (ThemeContext::get('site', [])['theme'] ?? 'motion');
        $url = '/themes/' . rawurlencode($themeName) . '/' . $path;

        return esc_html($url);
    }
}

if (!function_exists('page_meta')) {
    /**
     * Read escaped page meta values with a default fallback.
     */
    function page_meta(string $key, mixed $default = ''): string
    {
        $page = ThemeContext::get('page', []);
        $meta = is_array($page) ? ($page['meta'] ?? []) : [];
        $value = $meta[$key] ?? $default;

        return esc_html($value);
    }
}
