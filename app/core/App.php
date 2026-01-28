<?php

namespace Flint;

/**
 * Core Application Class.
 * Handles routing, configuration loading, and theme rendering.
 */
class App
{
    private const DEFAULT_COMPONENT_REGISTRY = 'https://raw.githubusercontent.com/clientcoffee/flint-components/main/manifest.json';
    public readonly array $config;
    public readonly string $root;
    public readonly string $appDir;
    public readonly Scheduler $scheduler;
    private bool $themeHooksRegistered = false;

    /**
     * Bootstrap the application with app and root directories.
     */
    public function __construct(string $appDir, string $rootDir)
    {
        // Store the app directory for config and core files.
        $this->appDir = $appDir;

        // Store the project root for content lookups.
        $this->root = $rootDir;

        // Initialize global path constants for use throughout the application.
        Paths::init($rootDir, $appDir);

        // Load the configuration file from the site bundle.
        $configPath = Paths::$configFile;

        if (!file_exists($configPath)) {
            throw new \Exception("Configuration file (site/config.php) missing. Run setup to generate it.");
        }

        $config = require $configPath;
        if (!isset($config['site']) || !is_array($config['site'])) {
            $config['site'] = [];
        }
        if (empty($config['site']['domain']) || !is_string($config['site']['domain'])) {
            $config['site']['domain'] = $this->resolveSiteDomain();
        }
        if (empty($config['site']['website']) || !is_string($config['site']['website'])) {
            $config['site']['website'] = $this->resolveSiteWebsite();
        }
        $this->config = $config;

        // Enforce security measures on every request.
        $this->enforceSecurityMeasures();

        // Initialize scheduler.
        $this->scheduler = new Scheduler($this);

        // Initialize hook system and load enabled components.
        HookManager::init($this);

        // Allow components to register scheduled tasks.
        HookManager::trigger('register_scheduled_tasks', ['scheduler' => $this->scheduler]);
    }

    /**
     * Resolve the active configuration file path.
     */
    private function resolveConfigPath(): string
    {
        return Paths::$configFile;
    }

    /**
     * Resolve the current site domain from server state.
     */
    private function resolveSiteDomain(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');

        if (empty($host) && !empty($_SERVER['REQUEST_URI'])) {
            $requestHost = parse_url($_SERVER['REQUEST_URI'], PHP_URL_HOST);
            if (!empty($requestHost)) {
                $host = $requestHost;
            }
        }

        $host = trim((string)$host);
        if ($host === '') {
            return 'localhost';
        }

        $parsedHost = parse_url('http://' . $host, PHP_URL_HOST);
        if (!empty($parsedHost)) {
            $host = $parsedHost;
        }

        return $host;
    }

    /**
     * Resolve the current site website URL from server state.
     */
    private function resolveSiteWebsite(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $this->resolveSiteDomain();
        return $scheme . '://' . $host;
    }

    /**
     * Register CMS-level theme hooks once per request.
     */
    private function registerThemeHooks(string $themeName, string $themeDirectory): void
    {
        if ($this->themeHooksRegistered) {
            return;
        }

        $this->themeHooksRegistered = true;

        $themeStylesPath = $themeDirectory . '/theme.css';
        if (is_file($themeStylesPath)) {
            HookManager::on('theme_styles', function () use ($themeName): void {
                echo '<link rel="stylesheet" href="' . theme_asset('theme.css', $themeName) . '">' . "\n";
            }, 20);
        }
    }

    /**
     * Orchestrate the request lifecycle from routing to rendering.
     */
    public function run(): void
    {
        // Normalize the request path.
        $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $requestPath = urldecode($requestPath);
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Security: Path Traversal Prevention.
        if (str_contains($requestPath, '..')) {
            $this->abort(403, "Invalid path.");
        }

        // Security: Reject overly long or malformed paths early.
        if (strlen($requestPath) > 2048) {
            $this->abort(414, "Request path too long.");
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $requestPath) || str_contains($requestPath, '\\')) {
            $this->abort(400, "Invalid path.");
        }

        // Trigger request_start hook (components can perform checks here).
        $clientIp = $this->getClientIp();
        HookManager::trigger('request_start', [
            'path' => $requestPath,
            'method' => $requestMethod,
            'ip' => $clientIp
        ]);

        // Block known abusive IPs quickly.
        if ($this->isIpBlocked($clientIp)) {
            $this->abort(403, "Access temporarily blocked.");
        }

        // Trap obvious WordPress/Joomla probes and block for four hours.
        if ($this->isTrapPath($requestPath)) {
            $this->blockIpForSeconds($clientIp, 4 * 60 * 60, 'honeypot');
            $this->abort(404, "Not found.");
        }

        // Run scheduled tasks (lightweight check, skip for static assets).
        if (!preg_match('/\.(js|mjs|css|jpg|jpeg|png|gif|svg|woff|woff2|ttf|eot)$/i', $requestPath)) {
            $this->scheduler->run();
        }

        // Serve static files from /public/ directory.
        if (preg_match('/\.(js|mjs|css|jpg|jpeg|png|gif|svg|woff|woff2|ttf|eot)$/i', $requestPath, $extensionMatches)) {
            // Skip routing for static assets.
            $publicFilePath = $this->root . '/public' . $requestPath;
            if (file_exists($publicFilePath) && is_file($publicFilePath)) {
                // Set correct MIME type based on extension.
                $extension = strtolower($extensionMatches[1]);
                header('Content-Type: ' . $this->getMimeType($extension));
                readfile($publicFilePath);
                exit;
            }
        }

        // Serve admin assets from app assets directory
        if ($requestPath === '/assets/js/admin.js') {
            $adminJsPath = $this->appDir . '/assets/js/admin.js';
            if (file_exists($adminJsPath) && is_file($adminJsPath)) {
                header('Content-Type: application/javascript');
                readfile($adminJsPath);
                exit;
            }
        }
        if (str_starts_with($requestPath, '/assets/css/')) {
            $relativePath = ltrim($requestPath, '/');
            $assetPath = $this->appDir . '/' . $relativePath;
            $assetDir = $this->appDir . '/assets/css';
            $realAssetPath = realpath($assetPath);
            $realAssetDir = realpath($assetDir);
            if ($realAssetPath !== false && $realAssetDir !== false && str_starts_with($realAssetPath, $realAssetDir) && is_file($realAssetPath)) {
                header('Content-Type: text/css');
                readfile($realAssetPath);
                exit;
            }
        }

        // Serve static files from /uploads/.
        if (str_starts_with($requestPath, '/uploads/')) {
            // Allow direct access to user-uploaded media.
            $uploadFilePath = $this->root . '/site' . $requestPath;

            // Security: Validate path stays within uploads directory.
            $realUploadPath = realpath($uploadFilePath);
            $realUploadsDir = realpath($this->root . '/site/uploads');

            if (
                $realUploadPath !== false && $realUploadsDir !== false &&
                strpos($realUploadPath, $realUploadsDir) === 0 &&
                is_file($realUploadPath)
            ) {
                $mimeType = mime_content_type($realUploadPath);

                // Security: Prevent SVG XSS by forcing download.
                if ($mimeType === 'image/svg+xml') {
                    header('Content-Disposition: attachment; filename="' . basename($realUploadPath) . '"');
                    header('X-Content-Type-Options: nosniff');
                }

                header('Content-Type: ' . $mimeType);
                readfile($realUploadPath);
                exit;
            }
        }

        // Serve theme assets from /themes/{theme}/*.
        if (preg_match('#^/themes/([a-zA-Z0-9_-]+)/(.+\.(js|mjs|css|jpg|jpeg|png|gif|svg|woff|woff2|ttf|eot))$#i', $requestPath, $themeMatches)) {
            $themeName = $themeMatches[1];
            $assetPath = $themeMatches[2];
            $themeAssetPath = $this->root . '/site/themes/' . $themeName . '/' . $assetPath;

            // Security: prevent directory traversal.
            if (strpos($assetPath, '..') !== false) {
                http_response_code(403);
                exit;
            }

            if (file_exists($themeAssetPath) && is_file($themeAssetPath)) {
                $extension = strtolower($themeMatches[3]);
                header('Content-Type: ' . $this->getMimeType($extension));
                readfile($themeAssetPath);
                exit;
            }
        }

        // Serve component assets from /components/{component}/*.
        if (preg_match('#^/components/([a-zA-Z0-9_-]+)/(.+\.(js|mjs|css|jpg|jpeg|png|gif|svg|woff|woff2|ttf|eot))$#i', $requestPath, $componentMatches)) {
            $componentName = $componentMatches[1];
            $assetPath = $componentMatches[2];
            $componentAssetPath = $this->root . '/site/components/' . $componentName . '/' . $assetPath;

            // Security: prevent directory traversal.
            if (strpos($assetPath, '..') !== false) {
                http_response_code(403);
                exit;
            }

            if (file_exists($componentAssetPath) && is_file($componentAssetPath)) {
                $extension = strtolower($componentMatches[3]);
                header('Content-Type: ' . $this->getMimeType($extension));
                readfile($componentAssetPath);
                exit;
            }
        }

        if ($requestPath === '/setup/magic' || $requestPath === '/magic') {
            $this->handleMagicLink();
            return;
        }

        // Login route.
        if ($requestPath === '/login') {
            $this->renderLoginPage();
            return;
        }

        // Admin route.
        if ($requestPath === '/admin') {
            $this->renderAdminPage();
            return;
        }

        // API Routes.
        if (str_starts_with($requestPath, '/api/')) {
            $this->handleAPI($requestPath);
            return;
        }

        // Allow components to register custom routes via hooks.
        $customRouteHandled = HookManager::trigger('custom_routes', [
            'path' => $requestPath,
            'method' => $requestMethod,
            'app' => $this
        ]);

        // If a component handled the route, stop processing.
        if (is_array($customRouteHandled) && in_array(true, $customRouteHandled, true)) {
            return;
        }

        $microCacheEligible = $this->isMicroCacheEligible($requestPath, $requestMethod);
        if ($microCacheEligible) {
            $cached = $this->readMicroCache($requestPath);
            if ($cached !== null) {
                echo $cached;
                return;
            }
        }

        // Resolve the content file for the request.
        $resolvedContentPath = $this->resolveContentFile($requestPath);
        if (!$resolvedContentPath) {
            $this->render404();
            return;
        }

        // Render the resolved page through the theme.
        $this->render($resolvedContentPath, $microCacheEligible, $requestPath);
    }

    /**
     * Handle API requests under /api.
     */
    private function handleAPI(string $requestPath): void
    {
        // Initialize the API response context.
        header('Content-Type: application/json');
        $authService = new Auth($this);
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Login.
        if ($requestPath === '/api/login' && $requestMethod === 'POST') {
            // Reject malformed JSON bodies.
            $payload = $this->readJsonPayload();
            if ($payload === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
                return;
            }

            // Normalize the incoming password field.
            $passwordInput = trim((string)($payload['password'] ?? ''));
            if ($passwordInput === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Password required']);
                return;
            }

            if (!$authService->login($passwordInput)) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Invalid password']);
                return;
            }

            echo json_encode(['success' => true]);
            return;
        }

        if ($requestPath === '/api/login/magic' && $requestMethod === 'POST') {
            $guard = $this->guardMagicLinkRequest();
            if (!$guard['allowed']) {
                http_response_code($guard['status']);
                echo json_encode(['success' => false, 'error' => $guard['message']]);
                return;
            }

            $payload = $this->readJsonPayload();
            if ($payload === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
                return;
            }

            $mode = trim((string)($payload['mode'] ?? MagicLink::MODE_LOGIN));
            if (!in_array($mode, [MagicLink::MODE_LOGIN, MagicLink::MODE_RESET], true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid magic link mode']);
                return;
            }

            $result = $this->issueMagicLink($mode);
            if (!$result['success']) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Failed to send magic link']);
                return;
            }

            $response = [
                'success' => true,
                'message' => $result['message'] ?? 'Magic link sent'
            ];
            if (!empty($result['magic_link'])) {
                $response['magic_link'] = $result['magic_link'];
            }

            echo json_encode($response);
            return;
        }

        if ($requestPath === '/api/security/password-reset' && $requestMethod === 'POST') {
            $guard = $this->guardMagicLinkRequest();
            if (!$guard['allowed']) {
                http_response_code($guard['status']);
                echo json_encode(['success' => false, 'error' => $guard['message']]);
                return;
            }

            $result = $this->issueMagicLink(MagicLink::MODE_RESET);
            if (!$result['success']) {
                error_log('Security: Failed to send reset magic link.');
            }

            $response = [
                'success' => true,
                'message' => 'If an admin email is configured, a reset link has been sent.'
            ];
            if (!empty($result['magic_link'])) {
                $response['magic_link'] = $result['magic_link'];
            }

            echo json_encode($response);
            return;
        }

        // Logout via browser navigation.
        if ($requestPath === '/logout') {
            $authService->logout();
            header('Location: /');
            return;
        }

        // Logout.
        if ($requestPath === '/api/logout') {
            $authService->logout();
            echo json_encode(['success' => true]);
            return;
        }

        // Generic form submission (public endpoint, no auth required).
        if ($requestPath === '/api/form' && $requestMethod === 'POST') {
            $this->handleFormSubmission();
            return;
        }

        // Handle file upload endpoint (images, PDFs, ZIPs, markdown, themes).
        if ($requestPath === '/api/upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // Check admin status.
            if (!$authService->isAdmin()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            // Validate uploaded file exists.
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
                return;
            }

            $file = $_FILES['file'];

            // Validate file size (50MB for ZIPs/themes, 5MB for others).
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
            if (strlen($originalName) > 120) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Filename is too long']);
                return;
            }

            // SECURITY: Check ALL extensions in filename to prevent double extension attacks
            // Example attack: malicious.php.jpg would be detected as .jpg but executed as .php
            $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar', 'phps'];
            $allParts = explode('.', $file['name']);
            if (count($allParts) > 1) {
                // Check each part that could be an extension (skip first part which is filename)
                for ($i = 1; $i < count($allParts); $i++) {
                    $possibleExtension = strtolower($allParts[$i]);
                    if (in_array($possibleExtension, $dangerousExtensions)) {
                        error_log("Security: Double extension attack blocked - filename: {$file['name']} from IP: " . $this->getClientIp());
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Filename contains dangerous extension']);
                        return;
                    }
                }
            }
            $maxSize = ($extension === 'zip') ? 50 * 1024 * 1024 : 5 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                $limitText = ($extension === 'zip') ? '50MB' : '5MB';
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => "File size exceeds {$limitText} limit"]);
                return;
            }

            // Validate MIME type.
            $allowedTypes = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
                'application/pdf',
                'application/zip', 'application/x-zip-compressed',
                'text/markdown', 'text/plain'
            ];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = $finfo ? (finfo_file($finfo, $file['tmp_name']) ?: '') : '';

            if (!in_array($mimeType, $allowedTypes)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid file type']);
                return;
            }

            // SECURITY: Comprehensive scan for dangerous code in uploaded files
            // Only markdown files are allowed to contain code (for documentation)
            $scanTextTypes = str_starts_with($mimeType, 'text/')
                || in_array($mimeType, ['image/svg+xml', 'application/xml', 'text/xml'], true);

            if ($scanTextTypes && !in_array($extension, ['md', 'mdx'], true)) {
                $fileContents = file_get_contents($file['tmp_name']);
                if ($fileContents !== false) {
                    // Check for various PHP code patterns
                    $dangerousPatterns = [
                        '/<\?php/i',           // Standard PHP tags
                        '/<\?=/i',             // Short echo tags
                        '/<\?(?![xml])/i',     // Short tags (but not <?xml)
                        '/eval\s*\(/i',        // eval() function
                        '/base64_decode\s*\(/i', // Base64 decoding (often used for obfuscation)
                        '/system\s*\(/i',      // system() command execution
                        '/exec\s*\(/i',        // exec() command execution
                        '/passthru\s*\(/i',    // passthru() command execution
                        '/shell_exec\s*\(/i',  // shell_exec() command execution
                        '/proc_open\s*\(/i',   // proc_open() command execution
                        '/popen\s*\(/i',       // popen() command execution
                        '/assert\s*\(/i',      // assert() code execution
                        '/preg_replace.*\/e/i', // preg_replace with /e modifier (code execution)
                        '/`.*`/s',             // Backtick operator (shell execution)
                    ];

                    foreach ($dangerousPatterns as $pattern) {
                        if (preg_match($pattern, $fileContents)) {
                            error_log("Security: Dangerous code pattern detected in upload - pattern: {$pattern}, filename: {$file['name']}, IP: " . $this->getClientIp());
                            http_response_code(400);
                            echo json_encode(['success' => false, 'error' => 'File contains dangerous content']);
                            return;
                        }
                    }
                }
            }

            // Determine file type and handle accordingly.
            $fileType = 'file';

            // Handle markdown files.
            if (in_array($extension, ['md', 'mdx'])) {
                $safeFilename = $this->sanitizeFilename($originalName);
                $finalFilename = $safeFilename . '.' . $extension;
                $targetPath = $this->root . '/site/pages/' . $finalFilename;

                // Ensure pages directory exists.
                if (!is_dir($this->root . '/site/pages')) {
                    mkdir($this->root . '/site/pages', 0755, true);
                }

                // SECURITY: Validate path to prevent symlink attacks
                try {
                    $validatedPath = $this->validateSecurePath($targetPath, Paths::$pagesDir);
                } catch (\Exception $e) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid file path']);
                    return;
                }

                // Security: Set umask to ensure file is created with 0644 (no execute bit).
                $oldUmask = umask(0133);

                if (!move_uploaded_file($file['tmp_name'], $validatedPath)) {
                    umask($oldUmask); // Restore original umask on failure.
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Failed to save markdown file']);
                    return;
                }

                // Restore original umask and ensure permissions are correct.
                umask($oldUmask);
                chmod($validatedPath, 0644);

                $fileUrl = '/site/pages/' . $finalFilename;
                $fileType = 'markdown';

                echo json_encode([
                    'success' => true,
                    'url' => $fileUrl,
                    'filename' => $finalFilename,
                    'size' => $file['size'],
                    'type' => $mimeType,
                    'fileType' => $fileType
                ]);
                return;
            }

            // Handle ZIP files (check for theme).
            if ($extension === 'zip') {
                $zip = new \ZipArchive();
                if ($zip->open($file['tmp_name']) === true) {
                    // Check if it's a theme (contains config.php with theme config).
                    $isTheme = false;
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $filename = $zip->getNameIndex($i);
                        if (basename($filename) === 'config.php') {
                            // Check if it contains theme config by reading the file
                            $configContent = $zip->getFromIndex($i);
                            if (strpos($configContent, "'theme'") !== false || strpos($configContent, '"theme"') !== false) {
                                $isTheme = true;
                                break;
                            }
                        }
                    }

                    if ($isTheme) {
                        // Extract as theme.
                        $themeName = $this->sanitizeFilename($originalName);
                        $themeDir = $this->root . '/site/themes/' . $themeName;

                        // Ensure themes directory exists.
                        if (!is_dir($this->root . '/site/themes')) {
                            mkdir($this->root . '/site/themes', 0755, true);
                        }

                        // Remove existing theme if present.
                        if (is_dir($themeDir)) {
                            $this->recursiveRemoveDirectory($themeDir);
                        }

                        // Extract theme safely (prevent ZIP slip attack).
                        $realThemeDir = realpath($this->root . '/site/themes');
                        if ($realThemeDir === false) {
                            $zip->close();
                            http_response_code(500);
                            echo json_encode(['success' => false, 'error' => 'Themes directory not accessible']);
                            return;
                        }

                        // Validate and extract each file individually.
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $entry = $zip->getNameIndex($i);

                            // Security: Block path traversal in ZIP entries.
                            if (strpos($entry, '..') !== false || strpos($entry, './') === 0 || $entry[0] === '/') {
                                $zip->close();
                                http_response_code(400);
                                echo json_encode(['success' => false, 'error' => 'Invalid file path in ZIP']);
                                return;
                            }

                            // Extract to theme directory.
                            $targetPath = $themeDir . '/' . $entry;

                            // Double-check resolved path is within theme directory.
                            $realTargetDir = realpath(dirname($targetPath));
                            if ($realTargetDir === false) {
                                // Directory doesn't exist yet, create it.
                                if (!mkdir(dirname($targetPath), 0755, true) && !is_dir(dirname($targetPath))) {
                                    $zip->close();
                                    http_response_code(500);
                                    echo json_encode(['success' => false, 'error' => 'Failed to create theme directory']);
                                    return;
                                }
                                $realTargetDir = realpath(dirname($targetPath));
                            }

                            // Verify path is still within themes directory (with trailing slash check).
                            $normalizedRealTargetDir = rtrim($realTargetDir, '/') . '/';
                            $normalizedRealThemeDir = rtrim($realThemeDir, '/') . '/';
                            if (
                                $realTargetDir === false ||
                                strpos($normalizedRealTargetDir, $normalizedRealThemeDir) !== 0
                            ) {
                                $zip->close();
                                http_response_code(400);
                                echo json_encode(['success' => false, 'error' => 'Invalid extraction path']);
                                return;
                            }

                            // Extract file.
                            if (!$zip->extractTo($themeDir, $entry)) {
                                $zip->close();
                                http_response_code(500);
                                echo json_encode(['success' => false, 'error' => 'Failed to extract theme file']);
                                return;
                            }

                            // Security: Verify extracted file is within expected directory (catch symlinks).
                            $extractedPath = $themeDir . '/' . $entry;
                            if (file_exists($extractedPath)) {
                                $realExtractedPath = realpath($extractedPath);
                                if ($realExtractedPath === false) {
                                    $zip->close();
                                    http_response_code(400);
                                    echo json_encode(['success' => false, 'error' => 'Suspicious file detected']);
                                    return;
                                }
                                $normalizedRealExtractedPath = rtrim($realExtractedPath, '/') . '/';
                                // Check if file path starts with theme directory (or equals for files).
                                if (
                                    strpos($normalizedRealExtractedPath, $normalizedRealThemeDir) !== 0 &&
                                    $realExtractedPath !== $themeDir . '/' . $entry
                                ) {
                                    unlink($extractedPath); // Remove suspicious file.
                                    $zip->close();
                                    http_response_code(400);
                                    echo json_encode(['success' => false, 'error' => 'Symlink or suspicious file detected']);
                                    return;
                                }
                            }
                        }
                        $zip->close();

                        echo json_encode([
                            'success' => true,
                            'themeName' => $themeName,
                            'message' => "Theme '{$themeName}' installed successfully",
                            'fileType' => 'theme'
                        ]);
                        return;
                    }
                    $zip->close();
                }
                // Fall through to regular ZIP upload if not a theme.
            }

            // Handle regular files (images, PDFs, non-theme ZIPs).
            $safeFilename = $this->sanitizeFilename($originalName);
            $uniqueSuffix = substr(bin2hex(random_bytes(3)), 0, 6);
            $finalFilename = $safeFilename . '-' . $uniqueSuffix . '.' . $extension;

            // Create month-based subdirectory.
            $yearMonth = date('Y-m');
            $uploadDir = $this->root . '/site/uploads/' . $yearMonth;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // SECURITY: Validate path to prevent symlink attacks
            $targetPath = $uploadDir . '/' . $finalFilename;
            try {
                $validatedPath = $this->validateSecurePath($targetPath, Paths::$uploadsDir);
            } catch (\Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid file path']);
                return;
            }

            // Security: Set umask to ensure file is created with 0644 (no execute bit).
            $oldUmask = umask(0133);

            // Move uploaded file.
            if (!move_uploaded_file($file['tmp_name'], $validatedPath)) {
                umask($oldUmask); // Restore original umask on failure.
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to save file']);
                return;
            }

            // Restore original umask and ensure permissions are correct.
            umask($oldUmask);
            chmod($validatedPath, 0644);

            // Security: Sanitize SVG files to remove dangerous content.
            if ($mimeType === 'image/svg+xml') {
                if (!$this->sanitizeSvg($validatedPath)) {
                    unlink($validatedPath); // Remove invalid SVG.
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid or unsafe SVG file']);
                    return;
                }
            }

            // Determine file type for response.
            if (str_starts_with($mimeType, 'image/')) {
                $fileType = 'image';
            } elseif ($mimeType === 'application/pdf') {
                $fileType = 'pdf';
            } elseif (in_array($mimeType, ['application/zip', 'application/x-zip-compressed'])) {
                $fileType = 'zip';
            }

            // Return success with file URL.
            $fileUrl = '/uploads/' . $yearMonth . '/' . $finalFilename;
            echo json_encode([
                'success' => true,
                'url' => $fileUrl,
                'filename' => $finalFilename,
                'size' => $file['size'],
                'type' => $mimeType,
                'fileType' => $fileType
            ]);
            return;
        }

        // Check auth for remaining endpoints.
        if (!$authService->isAdmin()) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        // Clear the IP blocklist.
        if ($requestPath === '/api/blocklist/clear' && $requestMethod === 'POST') {
            if (!$this->validateCsrfToken()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                return;
            }
            $this->clearBlockedIps();
            echo json_encode(['success' => true]);
            return;
        }

        // List blocked IPs (admin only).
        if ($requestPath === '/api/blocklist/list' && $requestMethod === 'GET') {
            $items = $this->listBlockedIps();
            echo json_encode(['success' => true, 'items' => $items]);
            return;
        }

        // Admin overview for dashboard widgets.
        if ($requestPath === '/api/admin/overview' && $requestMethod === 'GET') {
            echo json_encode(['success' => true] + $this->buildAdminOverview());
            return;
        }

        // Clear admin sessions.
        if ($requestPath === '/api/admin/clear-sessions' && $requestMethod === 'POST') {
            if (!$this->validateCsrfToken()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                return;
            }

            $result = $this->clearSessionFiles();
            if (!$result['success']) {
                http_response_code(500);
            }
            echo json_encode($result);
            return;
        }

        // Clear stored magic links and rate limit markers.
        if ($requestPath === '/api/admin/clear-magic-links' && $requestMethod === 'POST') {
            if (!$this->validateCsrfToken()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                return;
            }

            $result = $this->clearMagicLinkArtifacts();
            if (!$result['success']) {
                http_response_code(500);
            }
            echo json_encode($result);
            return;
        }

        // Get form submissions (admin only).
        if ($requestPath === '/api/submissions' && $requestMethod === 'GET') {
            $submissions = $this->loadSubmissions();
            echo json_encode(['success' => true, 'submissions' => $submissions]);
            return;
        }

        // Clear all submissions (admin only).
        if ($requestPath === '/api/submissions/clear' && $requestMethod === 'POST') {
            if (!$this->validateCsrfToken()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                return;
            }
            $this->clearSubmissions();
            echo json_encode(['success' => true]);
            return;
        }

        // List installed components (admin only).
        if ($requestPath === '/api/components' && $requestMethod === 'GET') {
            $components = $this->listInstalledComponents();
            echo json_encode(['success' => true, 'components' => $components]);
            return;
        }

        // Browse available components from registries (admin only).
        if ($requestPath === '/api/components/browse' && $requestMethod === 'GET') {
            $available = $this->browseAvailableComponents();
            echo json_encode(['success' => true, 'components' => $available]);
            return;
        }

        // Install component from registry (admin only).
        if ($requestPath === '/api/components/install' && $requestMethod === 'POST') {
            if (!$this->validateCsrfToken()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                return;
            }
            $payload = $this->readJsonPayload();
            $name = $payload['name'] ?? '';
            $downloadUrl = $payload['download_url'] ?? '';
            $checksum = $payload['sha256'] ?? '';
            $result = $this->installComponent($name, $downloadUrl, $checksum);
            echo json_encode($result);
            return;
        }

        // Update component from registry (admin only).
        if ($requestPath === '/api/components/update' && $requestMethod === 'POST') {
            if (!$this->validateCsrfToken()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                return;
            }
            $payload = $this->readJsonPayload();
            $name = $payload['name'] ?? '';
            $result = $this->updateComponent($name);
            echo json_encode($result);
            return;
        }

        // Toggle component enabled/disabled (admin only).
        if ($requestPath === '/api/components/toggle' && $requestMethod === 'POST') {
            if (!$this->validateCsrfToken()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                return;
            }
            $payload = $this->readJsonPayload();
            $name = $payload['name'] ?? '';
            $enabled = $payload['enabled'] ?? false;
            $result = $this->toggleComponent($name, $enabled);
            echo json_encode($result);
            return;
        }

        // Delete component (admin only).
        if ($requestPath === '/api/components/delete' && $requestMethod === 'POST') {
            if (!$this->validateCsrfToken()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                return;
            }
            $payload = $this->readJsonPayload();
            $name = $payload['name'] ?? '';
            $result = $this->deleteComponent($name);
            echo json_encode($result);
            return;
        }

        // List content tree (admin only).
        if ($requestPath === '/api/site/list' && $requestMethod === 'GET') {
            $items = $this->listContentTree();
            echo json_encode(['success' => true, 'items' => $items]);
            return;
        }

        // Create new content file (admin only).
        if ($requestPath === '/api/site/create' && $requestMethod === 'POST') {
            $payload = $this->readJsonPayload();
            if ($payload === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
                return;
            }

            $title = trim((string)($payload['title'] ?? ''));
            $body = (string)($payload['body'] ?? '');
            $parent = trim((string)($payload['parent'] ?? ''), '/');

            if ($title === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Title required']);
                return;
            }

            if ($parent !== '' && !$this->isSafePathSegment($parent)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid parent path']);
                return;
            }

            $slug = $this->slugify($title);
            if ($slug === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Title must include letters or numbers']);
                return;
            }

            $targetDir = $parent === '' ? Paths::$pagesDir : Paths::$pagesDir . '/' . $parent;
            if (!is_dir($targetDir)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Parent directory not found']);
                return;
            }

            $targetPath = $targetDir . '/' . $slug . '.md';
            try {
                $validatedPath = $this->validateSecurePath($targetPath, Paths::$pagesDir);
            } catch (\Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid file path']);
                return;
            }

            if (file_exists($validatedPath)) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'File already exists']);
                return;
            }

            $safeTitle = addcslashes($title, "\"\\");
            $frontmatter = "---\n" . 'title: "' . $safeTitle . "\"\n---\n\n";
            $contentBody = $frontmatter . ltrim($body, "\n");

            if (file_put_contents($validatedPath, $contentBody) === false) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to create file']);
                return;
            }

            $relativeFile = ltrim(($parent === '' ? '' : $parent . '/') . $slug . '.md', '/');
            $path = $this->contentSlugFromRelative($relativeFile);

            echo json_encode([
                'success' => true,
                'path' => $path,
                'file' => $relativeFile,
                'content' => $contentBody,
            ]);
            return;
        }

        // Move content file between directories (admin only).
        if ($requestPath === '/api/site/move' && $requestMethod === 'POST') {
            $payload = $this->readJsonPayload();
            if ($payload === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
                return;
            }

            $sourcePath = trim((string)($payload['source'] ?? ''));
            $destination = trim((string)($payload['destination'] ?? ''), '/');

            if ($sourcePath === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Source required']);
                return;
            }

            if ($destination !== '' && !$this->isSafePathSegment($destination)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid destination path']);
                return;
            }

            $resolvedSource = $this->resolveContentFile($sourcePath);
            if (!$resolvedSource || !file_exists($resolvedSource)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Source file not found']);
                return;
            }

            $targetDir = $destination === '' ? Paths::$pagesDir : Paths::$pagesDir . '/' . $destination;
            if (!is_dir($targetDir)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Destination directory not found']);
                return;
            }

            $fileName = basename($resolvedSource);
            $targetPath = $targetDir . '/' . $fileName;

            if ($resolvedSource === $targetPath) {
                $relativeFile = ltrim(($destination === '' ? '' : $destination . '/') . $fileName, '/');
                echo json_encode([
                    'success' => true,
                    'path' => $this->contentSlugFromRelative($relativeFile),
                ]);
                return;
            }

            if (file_exists($targetPath)) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'Destination already exists']);
                return;
            }

            if (!safe_rename($resolvedSource, $targetPath, Paths::$pagesDir, Paths::$pagesDir)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to move file']);
                return;
            }

            $relativeFile = ltrim(($destination === '' ? '' : $destination . '/') . $fileName, '/');
            $path = $this->contentSlugFromRelative($relativeFile);

            echo json_encode(['success' => true, 'path' => $path]);
            return;
        }

        // List block files (admin only).
        if ($requestPath === '/api/blocks/list' && $requestMethod === 'GET') {
            $items = $this->listBlockTree();
            echo json_encode(['success' => true, 'items' => $items]);
            return;
        }

        // Get raw block content.
        if ($requestPath === '/api/blocks' && $requestMethod === 'GET') {
            $requestedPath = ltrim((string)($_GET['path'] ?? ''), '/');
            if ($requestedPath === '' || !preg_match('/\.(md|mdx)$/i', $requestedPath)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid block path']);
                return;
            }

            $blockPath = Paths::$blocksDir . '/' . $requestedPath;
            try {
                $validatedPath = $this->validateSecurePath($blockPath, Paths::$blocksDir);
            } catch (\Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid block path']);
                return;
            }

            if (!file_exists($validatedPath)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Block not found']);
                return;
            }

            $contentBody = file_get_contents($validatedPath);
            echo json_encode(['success' => true, 'content' => $contentBody, 'file' => $validatedPath]);
            return;
        }

        // Save block content.
        if ($requestPath === '/api/blocks/save' && $requestMethod === 'POST') {
            $payload = $this->readJsonPayload();
            if ($payload === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
                return;
            }

            $requestedPath = ltrim((string)($payload['path'] ?? ''), '/');
            $updatedContent = (string)($payload['content'] ?? '');
            if ($requestedPath === '' || !preg_match('/\.(md|mdx)$/i', $requestedPath)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid block path']);
                return;
            }

            $blockPath = Paths::$blocksDir . '/' . $requestedPath;
            try {
                $validatedPath = $this->validateSecurePath($blockPath, Paths::$blocksDir);
            } catch (\Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid block path']);
                return;
            }

            if (!file_exists($validatedPath)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Block not found']);
                return;
            }

            if (file_put_contents($validatedPath, $updatedContent) === false) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to save block']);
                return;
            }

            echo json_encode(['success' => true]);
            return;
        }

        // Get raw content.
        if ($requestPath === '/api/site' && $requestMethod === 'GET') {
            // Resolve the requested content path.
            $requestedPath = (string)($_GET['path'] ?? '/');
            $resolvedContentPath = $this->resolveContentFile($requestedPath);
            if (!$resolvedContentPath || !file_exists($resolvedContentPath)) {
                http_response_code(404);
                echo json_encode(['error' => 'File not found']);
                return;
            }

            // Stream file contents back to the editor.
            $contentBody = file_get_contents($resolvedContentPath);
            echo json_encode(['success' => true, 'content' => $contentBody, 'file' => $resolvedContentPath]);
            return;
        }

        // Save content.
        if ($requestPath === '/api/save' && $requestMethod === 'POST') {
            // Reject malformed JSON bodies.
            $payload = $this->readJsonPayload();
            if ($payload === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
                return;
            }

            // Normalize requested path and content.
            $requestedPath = trim((string)($payload['path'] ?? ''));
            $updatedContent = (string)($payload['content'] ?? '');
            if ($requestedPath === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Path required']);
                return;
            }

            $resolvedContentPath = $this->resolveContentFile($requestedPath);
            if (!$resolvedContentPath || !file_exists($resolvedContentPath)) {
                http_response_code(404);
                echo json_encode(['error' => 'File not found']);
                return;
            }

            // Persist the updated file contents.
            if (file_put_contents($resolvedContentPath, $updatedContent) === false) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save file']);
                return;
            }

            echo json_encode(['success' => true]);
            return;
        }

        // Handle settings endpoint.
        if ($requestPath === '/api/settings') {
            if ($requestMethod === 'GET') {
                $configPath = $this->resolveConfigPath();
                if (!file_exists($configPath)) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Config file not found']);
                    return;
                }

                $configData = require $configPath;
                [$editable, $readonly] = $this->splitSettings($configData);

                $coreSiteKeys = $this->coreSiteKeys($configData['site'] ?? []);

                echo json_encode([
                    'success' => true,
                    'editable' => $editable,
                    'readonly' => $readonly,
                    'meta' => [
                        'core_site_keys' => $coreSiteKeys,
                    ],
                ]);
                return;
            }

            if ($requestMethod === 'POST') {
                if (!$this->validateCsrfToken()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                    return;
                }

                $bodyData = $this->readJsonPayload();
                if (!isset($bodyData['settings']) || !is_array($bodyData['settings'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Settings data required']);
                    return;
                }

                $settings = $bodyData['settings'];
                $removed = $bodyData['removed'] ?? [];
                if (!is_array($removed)) {
                    $removed = [];
                }

                $configPath = $this->resolveConfigPath();
                $existingConfig = require $configPath;
                $siteSettings = $existingConfig['site'] ?? [];
                if (!is_array($siteSettings)) {
                    $siteSettings = [];
                }

                foreach ($settings as $key => $value) {
                    if (!str_starts_with($key, 'site.')) {
                        continue;
                    }
                    $settingKey = substr($key, 5);
                    if ($settingKey === '') {
                        continue;
                    }
                    $siteSettings[$settingKey] = is_scalar($value) ? (string)$value : '';
                }

                $coreSiteKeys = $this->coreSiteKeys($siteSettings);
                foreach ($removed as $key) {
                    if (!is_string($key) || !str_starts_with($key, 'site.')) {
                        continue;
                    }
                    if (in_array($key, $coreSiteKeys, true)) {
                        continue;
                    }
                    $settingKey = substr($key, 5);
                    if ($settingKey !== '') {
                        unset($siteSettings[$settingKey]);
                    }
                }

                $existingConfig['site'] = $siteSettings;
                $this->writePhpConfig($configPath, $existingConfig);

                echo json_encode(['success' => true]);
                return;
            }
        }

        // Check admin status.
        if ($requestPath === '/api/status') {
            echo json_encode(['isAdmin' => $authService->isAdmin()]);
            return;
        }

        // Export content and config as tarball.
        if ($requestPath === '/api/export' && $requestMethod === 'POST') {
            if (!$this->validateCsrfToken()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                return;
            }
            $this->handleExport();
            return;
        }

        // Update system endpoints.
        if (str_starts_with($requestPath, '/api/updates/')) {
            $this->handleUpdateAPI($requestPath, $authService);
            return;
        }

        // Allow components to register custom API endpoints via hooks.
        $customApiHandled = HookManager::trigger('custom_api_endpoints', [
            'path' => $requestPath,
            'method' => $requestMethod,
            'app' => $this,
            'auth' => $authService
        ]);

        // If a component handled the API request, stop processing.
        if (is_array($customApiHandled) && in_array(true, $customApiHandled, true)) {
            return;
        }

        // Fall back to a generic API error.
        http_response_code(404);
        echo json_encode(['error' => 'API endpoint not found']);
    }

    /**
     * Perform an update check on admin page views (max once per 24 hours).
     */
    private function maybeCheckForUpdatesOnAdminView(): void
    {
        $checkPath = $this->appDir . '/.update-admin-check.json';
        $lastCheckedAt = 0;

        if (file_exists($checkPath)) {
            $cachedContents = file_get_contents($checkPath);
            $cachedData = json_decode($cachedContents, true);
            if (is_array($cachedData) && isset($cachedData['last_checked_at'])) {
                $lastCheckedAt = (int)$cachedData['last_checked_at'];
            }
        }

        if ($lastCheckedAt > 0 && (time() - $lastCheckedAt) < 86400) {
            return;
        }

        $checker = new UpdateChecker($this->appDir, $this->root);
        $checker->checkForUpdates();

        $payload = [
            'last_checked_at' => time(),
        ];

        @file_put_contents($checkPath, json_encode($payload, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * Handle update API requests.
     */
    private function handleUpdateAPI(string $requestPath, Auth $authService): void
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Check for updates (admin only).
        if ($requestPath === '/api/updates/check') {
            if (!$authService->isAdmin()) {
                http_response_code(403);
                echo json_encode(['error' => 'Admin access required']);
                return;
            }

            $checker = new UpdateChecker($this->appDir, $this->root);
            $force = ($_GET['force'] ?? '') === '1';
            if ($force) {
                $checker->clearCache();
            }
            $updateInfo = $checker->checkForUpdates();
            $updateError = $checker->getLastError();

            echo json_encode([
                'success' => $updateError === null,
                'error' => $updateError,
                'current_version' => Version::VERSION,
                'update_available' => $updateInfo !== null,
                'update' => $updateInfo,
                'auto_update_mode' => $this->config['updates']['auto_update'] ?? 'ask',
            ]);
            return;
        }

        // Apply update (admin only).
        if ($requestPath === '/api/updates/apply' && $requestMethod === 'POST') {
            if (!$authService->isAdmin()) {
                http_response_code(403);
                echo json_encode(['error' => 'Admin access required']);
                return;
            }

            if (!$this->validateCsrfToken()) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                return;
            }

            $payload = $this->readJsonPayload();
            if (!$payload || !isset($payload['download_url'])) {
                http_response_code(400);
                echo json_encode(['error' => 'download_url required']);
                return;
            }

            $checker = new UpdateChecker($this->appDir, $this->root);

            // Download update
            $zipFile = $checker->downloadUpdate($payload['download_url']);
            if (!$zipFile) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to download update']);
                return;
            }

            // Apply update
            if (!$checker->applyUpdate($zipFile)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to apply update']);
                return;
            }

            echo json_encode([
                'success' => true,
                'message' => 'Update applied successfully. Please refresh the page.',
            ]);
            return;
        }

        // Get version info (public).
        if ($requestPath === '/api/updates/version') {
            echo json_encode([
                'success' => true,
                'version' => Version::getInfo(),
            ]);
            return;
        }

        http_response_code(404);
        echo json_encode(['error' => 'Update endpoint not found']);
    }

    /**
     * Handle content export - creates archive of site/ (including config.php).
     */
    private function handleExport(): void
    {
        $timestamp = date('Y-m-d_H-i-s');
        $baseName = "flint-export-{$timestamp}";
        $tempDir = sys_get_temp_dir() . '/flint-export-' . uniqid();
        $archivePath = '';

        try {
            $this->prepareExportWorkspace($tempDir);
            $archive = $this->buildExportArchive($tempDir, $baseName);
            $archivePath = $archive['path'];
            $this->sendExportArchive($archivePath, $archive['filename'], $archive['content_type']);
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Export failed: ' . $e->getMessage()
            ]);
        } finally {
            if ($archivePath !== '' && file_exists($archivePath)) {
                unlink($archivePath);
            }
            if (is_dir($tempDir)) {
                $this->recursiveRemoveDirectory($tempDir);
            }
        }
    }

    /**
     * Prepare a temporary workspace for export.
     */
    private function prepareExportWorkspace(string $tempDir): void
    {
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $contentSource = $this->root . '/site';
        $contentDest = $tempDir . '/site';
        if (is_dir($contentSource)) {
            $this->recursiveCopy($contentSource, $contentDest);
        }

        $configSource = $this->resolveConfigPath();
        $configDestination = $tempDir . '/site/config.php';
        if (file_exists($configSource)) {
            $configDir = dirname($configDestination);
            if (!is_dir($configDir)) {
                mkdir($configDir, 0755, true);
            }
            copy($configSource, $configDestination);
        }
    }

    /**
     * Build the export archive and return metadata.
     *
     * @return array{path:string,filename:string,content_type:string}
     */
    private function buildExportArchive(string $tempDir, string $baseName): array
    {
        if ($this->canCreatePharArchive()) {
            $path = $this->createTarGzArchive($tempDir, $baseName);
            return [
                'path' => $path,
                'filename' => $baseName . '.tar.gz',
                'content_type' => 'application/gzip',
            ];
        }

        $path = $this->createZipArchive($tempDir, $baseName);
        return [
            'path' => $path,
            'filename' => $baseName . '.zip',
            'content_type' => 'application/zip',
        ];
    }

    /**
     * Determine whether Phar archives can be created.
     */
    private function canCreatePharArchive(): bool
    {
        if (!class_exists('PharData')) {
            return false;
        }

        $readonly = ini_get('phar.readonly');
        if ($readonly === false) {
            return true;
        }

        return filter_var($readonly, FILTER_VALIDATE_BOOLEAN) === false;
    }

    /**
     * Create a tar.gz archive using PharData.
     */
    private function createTarGzArchive(string $sourceDir, string $baseName): string
    {
        $tarPath = sys_get_temp_dir() . '/' . $baseName . '.tar';
        $phar = new \PharData($tarPath);
        $phar->buildFromDirectory($sourceDir);
        $phar->compress(\Phar::GZ);

        $gzPath = $tarPath . '.gz';
        if (file_exists($tarPath)) {
            unlink($tarPath);
        }

        return $gzPath;
    }

    /**
     * Create a zip archive when Phar is unavailable.
     */
    private function createZipArchive(string $sourceDir, string $baseName): string
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('ZipArchive extension unavailable.');
        }

        $zipPath = sys_get_temp_dir() . '/' . $baseName . '.zip';
        $zip = new \ZipArchive();
        $result = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw new \RuntimeException('Unable to create zip archive.');
        }

        $this->addDirectoryToZip($zip, $sourceDir, '');
        $zip->close();

        return $zipPath;
    }

    /**
     * Add directory contents to a zip archive.
     */
    private function addDirectoryToZip(\ZipArchive $zip, string $sourceDir, string $relativeDir): void
    {
        $entries = scandir($sourceDir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $sourceDir . '/' . $entry;
            $zipPath = $relativeDir === '' ? $entry : $relativeDir . '/' . $entry;

            if (is_dir($fullPath)) {
                $zip->addEmptyDir($zipPath);
                $this->addDirectoryToZip($zip, $fullPath, $zipPath);
                continue;
            }

            $zip->addFile($fullPath, $zipPath);
        }
    }

    /**
     * Stream the archive download response.
     */
    private function sendExportArchive(string $path, string $filename, string $contentType): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException('Export archive missing.');
        }

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
    }

    /**
     * Recursively copy a directory.
     */
    private function recursiveCopy(string $source, string $dest): void
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        $items = scandir($source);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourcePath = $source . '/' . $item;
            $destPath = $dest . '/' . $item;

            if (is_dir($sourcePath)) {
                $this->recursiveCopy($sourcePath, $destPath);
            } else {
                copy($sourcePath, $destPath);
            }
        }
    }

    /**
     * Resolve a URL path to a content file on disk.
     */
    private function resolveContentFile(string $requestPath): ?string
    {
        // Normalize the request path to a content slug.
        $normalizedSlug = trim($requestPath, '/');
        if ($normalizedSlug === '') {
            $normalizedSlug = 'index';
        }

        // Guard against unsafe path segments.
        if (!$this->isSafePathSegment($normalizedSlug)) {
            return null;
        }

        // Define directories that may contain pages.
        $contentSearchDirectories = ['site/pages'];

        foreach ($contentSearchDirectories as $contentDirectory) {
            // Build candidate file paths for both Markdown and MDX.
            $candidatePaths = [
                $this->root . '/' . $contentDirectory . '/' . $normalizedSlug . '.md',
                $this->root . '/' . $contentDirectory . '/' . $normalizedSlug . '.mdx',
                $this->root . '/' . $contentDirectory . '/' . $normalizedSlug . '/index.md',
                $this->root . '/' . $contentDirectory . '/' . $normalizedSlug . '/index.mdx',
            ];

            foreach ($candidatePaths as $candidatePath) {
                if (file_exists($candidatePath)) {
                    // SECURITY: Validate path to prevent symlink attacks
                    try {
                        return $this->validateSecurePath($candidatePath, Paths::$pagesDir);
                    } catch (\Exception $e) {
                        // Path validation failed, skip this candidate
                        continue;
                    }
                }
            }
        }

        // Return null when no content file matches.
        return null;
    }

    /**
     * Build a hierarchical list of content files under site/pages.
     */
    private function listContentTree(): array
    {
        $parser = new Parser($this);
        return $this->buildMarkdownTreeWithParser(Paths::$pagesDir, '', true, $parser);
    }

    /**
     * Build a hierarchical list of block files under site/blocks.
     */
    private function listBlockTree(): array
    {
        return $this->buildMarkdownTreeWithParser(Paths::$blocksDir, '', false, null);
    }

    /**
     * Recursively build a markdown file tree with an optional parser for labels.
     */
    private function buildMarkdownTreeWithParser(
        string $baseDir,
        string $relativeDir,
        bool $useContentSlug,
        ?Parser $parser
    ): array {
        if ($parser === null && $useContentSlug) {
            $parser = new Parser($this);
        }

        if (!is_dir($baseDir)) {
            return [];
        }

        $directory = $relativeDir === '' ? $baseDir : $baseDir . '/' . $relativeDir;
        $entries = scandir($directory);
        if ($entries === false) {
            return [];
        }

        $directories = [];
        $files = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }

            $fullPath = $directory . '/' . $entry;
            if (is_dir($fullPath)) {
                $childRelative = ltrim($relativeDir . '/' . $entry, '/');
                $children = $this->buildMarkdownTreeWithParser($baseDir, $childRelative, $useContentSlug, $parser);
                if (!empty($children)) {
                    $directories[] = [
                        'type' => 'directory',
                        'name' => $entry,
                        'path' => $childRelative,
                        'children' => $children
                    ];
                }
                continue;
            }

            if (!preg_match('/\.(md|mdx)$/i', $entry)) {
                continue;
            }

            $relativeFile = ltrim($relativeDir . '/' . $entry, '/');
            if ($useContentSlug) {
                $slug = $this->contentSlugFromRelative($relativeFile);
                $meta = extract_frontmatter($fullPath);
                $status = resolve_status($meta);
                $label = trim((string)($meta['title'] ?? ''));
                if ($label !== '') {
                    $label = $this->stripInlineLabel($label, $parser);
                }
                if ($label === '') {
                    $label = $this->contentLabelFromRelative($relativeFile, $slug);
                }
                $files[] = [
                    'type' => 'file',
                    'label' => $label,
                    'path' => $slug,
                    'file' => $relativeFile,
                    'status' => $status,
                ];
            } else {
                $label = preg_replace('/\.(md|mdx)$/i', '', $entry);
                $files[] = [
                    'type' => 'file',
                    'label' => $label,
                    'path' => $relativeFile
                ];
            }
        }

        usort($directories, fn($a, $b) => strcmp($a['name'], $b['name']));
        usort($files, fn($a, $b) => strcmp($a['label'], $b['label']));

        return array_merge($directories, $files);
    }

    /**
     * Convert a site/pages relative file path into a URL slug.
     */
    private function contentSlugFromRelative(string $relativeFile): string
    {
        $relativeFile = str_replace('\\', '/', $relativeFile);
        $trimmed = preg_replace('/\.(md|mdx)$/i', '', $relativeFile);
        $trimmed = ltrim($trimmed, '/');
        $baseName = basename($trimmed);

        if ($baseName === 'index') {
            $dir = trim(dirname($trimmed), '.');
            if ($dir === '' || $dir === '.') {
                return '/';
            }
            return '/' . $dir;
        }

        return '/' . $trimmed;
    }

    /**
     * Provide a friendly label for a content file.
     */
    private function contentLabelFromRelative(string $relativeFile, string $slug): string
    {
        if ($slug === '/') {
            return 'home';
        }

        $relativeFile = str_replace('\\', '/', $relativeFile);
        $trimmed = preg_replace('/\.(md|mdx)$/i', '', $relativeFile);
        $baseName = basename($trimmed);

        if ($baseName === 'index') {
            $dir = trim(dirname($trimmed), '.');
            if ($dir === '' || $dir === '.') {
                return 'home';
            }

            return basename($dir);
        }

        return $baseName;
    }

    /**
     * Convert inline markdown to a plain label string.
     */
    private function stripInlineLabel(string $label, ?Parser $parser): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }

        if ($parser === null) {
            return $label;
        }

        $rendered = $parser->renderInlineMarkdown($label);
        $plain = trim(strip_tags($rendered));
        if ($plain === '') {
            return '';
        }

        return html_entity_decode($plain, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Build the admin dashboard overview payload.
     */
    private function buildAdminOverview(): array
    {
        $configPath = $this->resolveConfigPath();
        $configData = file_exists($configPath) ? require $configPath : [];
        if (!is_array($configData)) {
            $configData = [];
        }

        [$editable, $readonly] = $this->splitSettings($configData);
        $pageCount = $this->countTreeFiles($this->listContentTree());
        $blockCount = $this->countTreeFiles($this->listBlockTree());
        $currentTheme = $configData['site']['theme'] ?? 'motion';

        return [
            'counts' => [
                'pages' => $pageCount,
                'blocks' => $blockCount,
            ],
            'themes' => $this->listThemeOptions(),
            'current_theme' => $currentTheme,
            'site_settings' => $editable,
            'readonly_settings' => $readonly,
        ];
    }

    /**
     * Count file entries in a tree list response.
     */
    private function countTreeFiles(array $items): int
    {
        $count = 0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (($item['type'] ?? '') === 'file') {
                $count++;
                continue;
            }

            if (($item['type'] ?? '') === 'directory' && isset($item['children'])) {
                $children = is_array($item['children']) ? $item['children'] : [];
                $count += $this->countTreeFiles($children);
            }
        }

        return $count;
    }

    /**
     * List available themes for admin selection.
     */
    private function listThemeOptions(): array
    {
        $themesDir = Paths::$themesDir;
        if (!is_dir($themesDir)) {
            return [];
        }

        $entries = scandir($themesDir);
        if ($entries === false) {
            return [];
        }

        $themes = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }

            $themePath = $themesDir . '/' . $entry;
            if (!is_dir($themePath)) {
                continue;
            }

            $themeConfig = $this->loadThemeConfig($entry);
            $label = $themeConfig['theme']['name'] ?? $themeConfig['name'] ?? $this->formatThemeLabel($entry);
            $themes[] = [
                'name' => $entry,
                'label' => $label,
            ];
        }

        usort($themes, fn($a, $b) => strcmp($a['label'], $b['label']));

        return $themes;
    }

    /**
     * Format a human-friendly theme label from a slug.
     */
    private function formatThemeLabel(string $themeName): string
    {
        $cleaned = str_replace(['-', '_'], ' ', $themeName);
        $words = preg_split('/\s+/', trim($cleaned)) ?: [];
        $words = array_map(fn($word) => ucfirst(strtolower($word)), $words);

        return trim(implode(' ', $words));
    }

    /**
     * Split settings into editable site keys and read-only config keys.
     *
     * @return array{0:array<string,string>,1:array<string,string>}
     */
    private function splitSettings(array $configData): array
    {
        $editable = [];
        $readonly = [];

        foreach ($configData as $group => $values) {
            if (!is_array($values)) {
                continue;
            }

            foreach ($values as $key => $value) {
                if (!is_string($key) || $key === '') {
                    continue;
                }

                $fullKey = $group . '.' . $key;
                $normalized = $this->normalizeSettingValue($value);
                if ($group === 'site') {
                    $editable[$fullKey] = $normalized;
                    continue;
                }

                $readonly[$fullKey] = $normalized;
            }
        }

        ksort($editable);
        ksort($readonly);

        return [$editable, $readonly];
    }

    /**
     * Normalize a config value for display or editing.
     */
    private function normalizeSettingValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        if ($value === null) {
            return '';
        }

        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES);
        return $encoded === false ? '' : $encoded;
    }

    /**
     * Identify core site keys that should not be removed.
     *
     * @param array<string,mixed> $siteSettings
     * @return array<int,string>
     */
    private function coreSiteKeys(array $siteSettings): array
    {
        $baseKeys = ['name', 'theme', 'website', 'tagline'];
        $exampleKeys = $this->loadExampleSiteKeys();
        $candidates = array_unique(array_merge($baseKeys, $exampleKeys));

        $coreKeys = [];
        foreach ($candidates as $key) {
            if ($key === '') {
                continue;
            }

            if (!array_key_exists($key, $siteSettings) && !in_array($key, $exampleKeys, true)) {
                continue;
            }

            $coreKeys[] = 'site.' . $key;
        }

        sort($coreKeys);

        return $coreKeys;
    }

    /**
     * Load site keys from the example config for core defaults.
     *
     * @return array<int,string>
     */
    private function loadExampleSiteKeys(): array
    {
        $paths = [
            $this->root . '/site/config.example.php',
            $this->root . '/config.example.php',
            $this->appDir . '/config.example.php',
        ];

        foreach ($paths as $path) {
            if (!file_exists($path)) {
                continue;
            }

            $configData = require $path;
            if (!is_array($configData)) {
                continue;
            }

            $siteSettings = $configData['site'] ?? null;
            if (is_array($siteSettings)) {
                return array_keys($siteSettings);
            }
        }

        return [];
    }

    /**
     * Render a content file through the configured theme.
     */
    private function render(string $filePath, bool $writeMicroCache = false, string $microCacheKey = ''): void
    {
        require_once $this->appDir . '/core/helpers.php';

        // Parse the content file into metadata and HTML.
        $parser = new Parser($this);
        $pagePayload = $parser->parseFile($filePath);

        // Resolve admin status once per request.
        $authService = new Auth($this);
        $isAdmin = $authService->isAdmin();

        // Determine page visibility based on status metadata.
        $pageStatus = strtolower($pagePayload['meta']['status'] ?? 'published');

        // Draft pages are only accessible to admins.
        if ($pageStatus === 'draft' && !$isAdmin) {
            $this->render404();
            return;
        }

        // Locate the active theme directory.
        $themeName = $this->config['site']['theme'] ?? 'motion';
        $themeDirectory = $this->root . '/site/themes/' . $themeName;

        if (!is_dir($themeDirectory)) {
            throw new \Exception("Theme '$themeName' not found.");
        }

        // Load theme configuration.
        $themeConfig = $this->loadThemeConfig($themeName);

        // Prepare the data available to the theme.
        $themeData = [
            'app' => $this,
            'site' => $this->config['site'],
            'page' => $pagePayload,
            'content' => $pagePayload['content_html'],
            'isAdmin' => $isAdmin,
            'currentPath' => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
            'componentsUsed' => $pagePayload['components_used'] ?? [],
            'componentAssets' => $pagePayload['component_assets'] ?? [],
            'pageStatus' => $pageStatus,
            'themeConfig' => $themeConfig
        ];

        // Add admin assets to component assets BEFORE rendering theme.
        if ($isAdmin) {
            $adminAssets = Admin::getAssets($themeData['currentPath']);
            $themeData['componentAssets'] = $this->mergeAssets(
                $themeData['componentAssets'],
                $adminAssets
            );
        }

        ThemeContext::set($themeData);

        $this->registerThemeHooks($themeName, $themeDirectory);

        // Include theme helpers if they exist.
        if (file_exists($themeDirectory . '/helpers.php')) {
            require_once $themeDirectory . '/helpers.php';
        }

        // Render view inside the layout template.
        ob_start();
        extract($themeData);
        if (!file_exists($themeDirectory . '/view.php')) {
            echo $content;
        } else {
            include $themeDirectory . '/view.php';
        }
        $viewContent = ob_get_clean();

        // Render the layout wrapper and capture output.
        // Support layout cascade: layout-{type}.php → layout.php
        $layoutFile = null;
        $contentType = $pagePayload['meta']['type'] ?? null;

        if ($contentType) {
            $typedLayoutPath = $themeDirectory . '/layout-' . $contentType . '.php';
            if (file_exists($typedLayoutPath)) {
                $layoutFile = $typedLayoutPath;
            }
        }

        // Fallback to default layout.php
        if ($layoutFile === null && file_exists($themeDirectory . '/layout.php')) {
            $layoutFile = $themeDirectory . '/layout.php';
        }

        ob_start();
        if ($layoutFile === null) {
            echo $viewContent;
        } else {
            include $layoutFile;
        }
        $finalHtml = ob_get_clean();

        // Inject admin UI HTML (buttons, controls) if user is admin.
        if ($isAdmin) {
            $admin = new Admin($isAdmin);
            $finalHtml = $admin->injectAdminUI($finalHtml, $pageStatus);
        }

        // Minify HTML output for public pages (whitespace-only).
        if (!$isAdmin && $this->isHtmlMinifyEnabled()) {
            $finalHtml = $this->minifyHtml($finalHtml);
        }

        if ($writeMicroCache && !$isAdmin && $pageStatus !== 'draft') {
            $this->writeMicroCache($microCacheKey, $finalHtml);
        }

        // Output final HTML.
        echo $finalHtml;

        ThemeContext::clear();
    }

    /**
     * Render a 404 response using the theme when available.
     */
    private function render404(): void
    {
        // Provide a consistent 404 response.
        http_response_code(404);
        $themeName = $this->config['site']['theme'] ?? 'motion';
        $errorPagePath = $this->root . '/site/themes/' . $themeName . '/404.php';
        if (file_exists($errorPagePath)) {
            // Make site config and theme config available to 404 template.
            $site = $this->config['site'];
            $themeConfig = $this->loadThemeConfig($themeName);
            include $errorPagePath;
            return;
        }

        // Fallback Admin Theme for 404.
        $templatePath = $this->appDir . '/views/404-fallback.php';
        if (file_exists($templatePath)) {
            require $templatePath;
            return;
        }

        echo 'Page Not Found';
    }

    /**
     * Check if HTML minification is enabled.
     */
    private function isHtmlMinifyEnabled(): bool
    {
        $system = $this->config['system'] ?? [];
        if (!is_array($system)) {
            return true;
        }

        if (!array_key_exists('minify_html', $system)) {
            return true;
        }

        $value = $system['minify_html'];
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Lightweight HTML minifier (remove whitespace between tags).
     */
    private function minifyHtml(string $html): string
    {
        $html = preg_replace('/>\\s+</', '><', $html);
        return trim($html);
    }

    /**
     * Check if micro-cache is enabled.
     */
    private function isMicroCacheEnabled(): bool
    {
        $system = $this->config['system'] ?? [];
        if (!is_array($system)) {
            return true;
        }

        if (!array_key_exists('micro_cache', $system)) {
            return true;
        }

        $value = $system['micro_cache'];
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Determine the micro-cache TTL (seconds).
     */
    private function microCacheTtl(): int
    {
        $system = $this->config['system'] ?? [];
        if (!is_array($system)) {
            return 10;
        }

        $ttl = $system['micro_cache_ttl'] ?? 10;
        $ttl = is_numeric($ttl) ? (int)$ttl : 10;
        if ($ttl < 1) {
            $ttl = 1;
        }

        return $ttl;
    }

    /**
     * Decide if the current request can use micro-cache.
     */
    private function isMicroCacheEligible(string $requestPath, string $requestMethod): bool
    {
        if (!$this->isMicroCacheEnabled()) {
            return false;
        }

        if ($requestMethod !== 'GET') {
            return false;
        }

        if (!empty($_SERVER['QUERY_STRING'])) {
            return false;
        }

        if (str_starts_with($requestPath, '/api/')) {
            return false;
        }

        if (in_array($requestPath, ['/admin', '/login', '/logout', '/magic', '/setup/magic'], true)) {
            return false;
        }

        if (!empty($_COOKIE)) {
            return false;
        }

        $cacheControl = strtolower($_SERVER['HTTP_CACHE_CONTROL'] ?? '');
        if (str_contains($cacheControl, 'no-cache') || str_contains($cacheControl, 'no-store')) {
            return false;
        }

        return true;
    }

    /**
     * Resolve micro-cache path for a request.
     */
    private function microCachePath(string $requestPath): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $key = sha1($host . '|' . $requestPath);
        $cacheDir = Paths::$cacheDir . '/micro-cache';

        return $cacheDir . '/' . $key . '.html';
    }

    /**
     * Read micro-cache for a request.
     */
    private function readMicroCache(string $requestPath): ?string
    {
        $cachePath = $this->microCachePath($requestPath);
        if (!is_file($cachePath)) {
            return null;
        }

        $ttl = $this->microCacheTtl();
        if ((time() - filemtime($cachePath)) > $ttl) {
            @unlink($cachePath);
            return null;
        }

        $contents = file_get_contents($cachePath);
        return $contents === false ? null : $contents;
    }

    /**
     * Write micro-cache response to disk.
     */
    private function writeMicroCache(string $requestPath, string $html): void
    {
        $cachePath = $this->microCachePath($requestPath);
        $cacheDir = dirname($cachePath);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        file_put_contents($cachePath, $html, LOCK_EX);
    }

    /**
     * Stop processing the request with an HTTP error.
     */
    private function abort(int $code, string $message): void
    {
        // Return an error response and stop execution.
        http_response_code($code);
        die($message);
    }

    /**
     * Render a standalone login page for admin access.
     */
    private function renderLoginPage(): void
    {
        // Avoid showing the login screen when already authenticated.
        $authService = new Auth($this);
        if ($authService->isAdmin()) {
            header('Location: /');
            return;
        }

        $templatePath = $this->appDir . '/views/login.php';
        if (!file_exists($templatePath)) {
            throw new \Exception('Login template missing.');
        }

        require $templatePath;
    }

    /**
     * Handle magic link sign-in for setup and password provisioning.
     */
    private function handleMagicLink(): void
    {
        $token = trim((string)($_GET['token'] ?? ''));

        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
            $this->recordMagicLinkFailure();
            $this->renderMagicLinkPage(false, 'Magic link is missing or invalid.');
            return;
        }

        $magicLinkConfig = MagicLink::readTokenStore($this->appDir);
        if (!is_array($magicLinkConfig)) {
            $this->recordMagicLinkFailure();
            $this->renderMagicLinkPage(false, 'Magic link has already been used or expired.');
            return;
        }

        $tokenHash = (string)($magicLinkConfig['token_hash'] ?? '');
        $expiresAt = (int)($magicLinkConfig['expires_at'] ?? 0);
        $mode = (string)($magicLinkConfig['mode'] ?? MagicLink::MODE_SETUP);
        if (
            $tokenHash === '' ||
            $expiresAt <= 0 ||
            $expiresAt < time() ||
            !in_array($mode, [MagicLink::MODE_SETUP, MagicLink::MODE_LOGIN, MagicLink::MODE_RESET], true)
        ) {
            $this->recordMagicLinkFailure();
            $this->renderMagicLinkPage(false, 'Magic link has already been used or expired.');
            return;
        }

        $incomingHash = hash('sha256', $token);
        if (!hash_equals($tokenHash, $incomingHash)) {
            $this->recordMagicLinkFailure();
            $this->renderMagicLinkPage(false, 'Magic link is invalid.');
            return;
        }

        $configPath = $this->resolveConfigPath();
        if (!file_exists($configPath)) {
            $this->renderMagicLinkPage(false, 'Configuration file missing.');
            return;
        }

        $configData = require $configPath;
        if (!isset($configData['admin']) || !is_array($configData['admin'])) {
            $configData['admin'] = [];
        }
        $newPassword = null;
        if ($mode === MagicLink::MODE_RESET || $mode === MagicLink::MODE_SETUP) {
            $newPassword = MagicLink::generatePassword(64);
            $configData['admin']['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }
        unset($configData['admin']['magic_link']);
        $this->writeAppConfig($configPath, $configData);
        MagicLink::clearTokenStore($this->appDir);

        $authService = new Auth($this);
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_authenticated_at'] = time();
        session_regenerate_id(true);

        if ($newPassword !== null) {
            $this->renderMagicLinkPage(true, 'Your admin password is ready. Save it now.', $newPassword);
            return;
        }

        header('Location: /admin');
        return;
    }

    /**
     * Render the magic link result page.
     */
    private function renderMagicLinkPage(bool $success, string $message, ?string $password = null): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        $templatePath = $this->appDir . '/views/magic-link.php';
        if (!file_exists($templatePath)) {
            throw new \Exception('Magic link template missing.');
        }

        $title = 'Magic Link';
        require $templatePath;
    }

    /**
     * Guard magic link requests against abuse.
     *
     * @return array{allowed:bool,status:int,message:string}
     */
    private function guardMagicLinkRequest(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if ($contentType !== '' && stripos($contentType, 'application/json') === false) {
            return ['allowed' => false, 'status' => 400, 'message' => 'Invalid request'];
        }

        if (!$this->isSameOriginRequest()) {
            return ['allowed' => false, 'status' => 403, 'message' => 'Invalid request'];
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $lastAttempt = (int)($_SESSION['magic_link_last'] ?? 0);
        if ($lastAttempt > 0 && (time() - $lastAttempt) < 10) {
            return ['allowed' => false, 'status' => 429, 'message' => 'Too many requests'];
        }
        $_SESSION['magic_link_last'] = time();

        $ip = $this->getClientIp();
        if ($this->isRateLimited('magic-link-ip-' . md5($ip), 5, 15 * 60)) {
            return ['allowed' => false, 'status' => 429, 'message' => 'Too many requests'];
        }

        if ($this->isRateLimited('magic-link-global', 50, 15 * 60)) {
            return ['allowed' => false, 'status' => 429, 'message' => 'Too many requests'];
        }

        return ['allowed' => true, 'status' => 200, 'message' => 'OK'];
    }

    /**
     * Check same-origin for requests that include Origin/Referer headers.
     */
    private function isSameOriginRequest(): bool
    {
        $hostHeader = $_SERVER['HTTP_HOST'] ?? $this->resolveSiteDomain();
        $host = parse_url('http://' . $hostHeader, PHP_URL_HOST) ?: $hostHeader;
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';

        if ($origin === '' && $referer === '') {
            return true;
        }

        foreach ([$origin, $referer] as $value) {
            if ($value === '') {
                continue;
            }
            $parsedHost = parse_url($value, PHP_URL_HOST);
            if ($parsedHost && !hash_equals($host, $parsedHost)) {
                return false;
            }
        }

        return true;
    }

    /**
     * IP/global rate limit helper using the submissions directory.
     */
    private function isRateLimited(string $bucket, int $maxRequests, int $windowSeconds): bool
    {
        $rateLimitDir = $this->root . '/site/submissions';
        $this->ensureDir($rateLimitDir);

        $rateLimitFile = $rateLimitDir . '/.' . $bucket . '.json';
        $now = time();

        $requests = [];
        if (file_exists($rateLimitFile)) {
            $data = file_get_contents($rateLimitFile);
            if ($data !== false) {
                $requests = json_decode($data, true) ?: [];
            }
        }

        $requests = array_filter($requests, function ($timestamp) use ($now, $windowSeconds) {
            return ($now - (int)$timestamp) < $windowSeconds;
        });

        if (count($requests) >= $maxRequests) {
            return true;
        }

        $requests[] = $now;
        file_put_contents($rateLimitFile, json_encode(array_values($requests)), LOCK_EX);

        return false;
    }

    /**
     * Determine if magic links can be exposed in responses for local development.
     */
    private function shouldExposeMagicLink(): bool
    {
        $environment = strtolower(trim((string)($this->config['system']['environment'] ?? '')));
        if ($environment !== '') {
            return in_array($environment, ['dev', 'development', 'local'], true);
        }

        $debug = $this->config['system']['debug'] ?? false;
        $showErrors = $this->config['system']['show_errors'] ?? false;
        $devMode = $this->isTruthy($debug) || $this->isTruthy($showErrors);

        if (!$devMode) {
            return false;
        }

        $ip = $this->getClientIp();
        if (in_array($ip, ['127.0.0.1', '::1'], true)) {
            return true;
        }

        $hostHeader = $_SERVER['HTTP_HOST'] ?? '';
        $host = parse_url('http://' . $hostHeader, PHP_URL_HOST) ?: $hostHeader;
        if ($host === 'localhost') {
            return true;
        }

        return str_ends_with($host, '.local') || str_ends_with($host, '.test');
    }

    /**
     * Normalize config flags to boolean.
     */
    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return false;
    }

    /**
     * Record failed magic link validations and block abusive IPs.
     */
    private function recordMagicLinkFailure(): void
    {
        $ip = $this->getClientIp();
        $bucket = 'magic-link-fail-' . md5($ip);
        $rateLimitDir = $this->root . '/site/submissions';
        $this->ensureDir($rateLimitDir);

        $rateLimitFile = $rateLimitDir . '/.' . $bucket . '.json';
        $now = time();
        $windowSeconds = 60 * 60;
        $maxFailures = 6;
        $banEscalationWindow = 24 * 60 * 60;

        $data = [
            'attempts' => [],
            'last_ban_at' => 0,
        ];
        if (file_exists($rateLimitFile)) {
            $rawData = file_get_contents($rateLimitFile);
            if ($rawData !== false) {
                $decoded = json_decode($rawData, true);
                if (is_array($decoded)) {
                    $data = array_merge($data, $decoded);
                }
            }
        }

        $attempts = $data['attempts'] ?? [];
        if (!is_array($attempts)) {
            $attempts = [];
        }

        $attempts = array_filter($attempts, function ($timestamp) use ($now, $windowSeconds) {
            return ($now - (int)$timestamp) < $windowSeconds;
        });

        $attempts[] = $now;

        if (count($attempts) >= $maxFailures) {
            $lastBanAt = (int)($data['last_ban_at'] ?? 0);
            $banDuration = 60 * 60;
            $reason = 'magic-link-abuse-hour';

            if ($lastBanAt > 0 && ($now - $lastBanAt) < $banEscalationWindow) {
                $banDuration = $banEscalationWindow;
                $reason = 'magic-link-abuse-day';
            }

            $this->blockIpForSeconds($ip, $banDuration, $reason);
            $data['last_ban_at'] = $now;
            $attempts = [];
        }

        $data['attempts'] = array_values($attempts);
        file_put_contents($rateLimitFile, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * Issue a magic link for login or password reset.
     *
     * @return array{success:bool, message?:string, error?:string, magic_link?:string}
     */
    private function issueMagicLink(string $mode): array
    {
        $adminEmail = $this->config['mail']['admin_email'] ?? '';
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Admin email is not configured'];
        }

        $siteName = $this->config['site']['name'] ?? 'Flint';
        $siteWebsite = $this->config['site']['website'] ?? $this->resolveSiteWebsite();
        if (!is_string($siteWebsite) || $siteWebsite === '') {
            $siteWebsite = $this->resolveSiteWebsite();
        }

        $pair = MagicLink::generateTokenPair();
        $expiresAt = time() + MagicLink::defaultTtlSeconds($mode);
        $issuedAt = time();
        $exposeMagicLink = $this->shouldExposeMagicLink();

        if (!$exposeMagicLink) {
            $existingMagicLink = MagicLink::readTokenStore($this->appDir);
            if (is_array($existingMagicLink)) {
                $existingIssuedAt = (int)($existingMagicLink['issued_at'] ?? 0);
                if ($existingIssuedAt > 0 && (time() - $existingIssuedAt) < 120) {
                    return [
                        'success' => true,
                        'message' => 'A magic link was already sent. Please wait a moment before requesting another.'
                    ];
                }
            }
        }

        $tokenEntry = MagicLink::buildConfigEntry($mode, $pair['hash'], $expiresAt, $issuedAt);
        MagicLink::writeTokenStore($this->appDir, $tokenEntry);

        $magicLink = MagicLink::buildMagicLink($siteWebsite, $pair['token']);
        $blockName = MagicLink::defaultBlockForMode($mode);
        $subject = MagicLink::defaultSubjectForMode($mode, $siteName);

        if ($exposeMagicLink) {
            return [
                'success' => true,
                'message' => 'Magic link generated for local use.',
                'magic_link' => $magicLink
            ];
        }

        $emailSent = MagicLink::sendEmail(
            $this->appDir,
            $this->root,
            $adminEmail,
            $siteName,
            $siteWebsite,
            $magicLink,
            $blockName,
            $subject
        );

        if (!$emailSent) {
            MagicLink::clearTokenStore($this->appDir);
            return ['success' => false, 'error' => 'Failed to send magic link email'];
        }

        $message = $mode === MagicLink::MODE_RESET
            ? 'Check your email for a password reset link.'
            : 'Check your email for a sign-in link.';

        return ['success' => true, 'message' => $message];
    }

    /**
     * Render the admin page.
     */
    private function renderAdminPage(): void
    {
        // Require admin authentication.
        $authService = new Auth($this);
        if (!$authService->isAdmin()) {
            header('Location: /login');
            return;
        }

        $this->maybeCheckForUpdatesOnAdminView();
        $csrfToken = $authService->getCsrfToken();
        $autoUpdateMode = $this->config['updates']['auto_update'] ?? 'ask';
        $version = Version::VERSION;
        $channel = Version::CHANNEL;

        $templatePath = $this->appDir . '/views/admin.php';
        if (!file_exists($templatePath)) {
            throw new \Exception('Admin template missing.');
        }

        require $templatePath;
    }

    /**
     * Handle generic form submission from Form component
     *
     * All forms POST to /api/form and automatically email the admin.
     */
    private function handleFormSubmission(): void
    {
        header('Content-Type: application/json');

        // Rate limiting
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $lastSubmission = $_SESSION['last_form_submission'] ?? 0;
        if (time() - $lastSubmission < 60) {
            echo json_encode([
                'success' => false,
                'message' => 'Please wait before submitting again.'
            ]);
            return;
        }

        // Get form data
        $formName = $_POST['form_name'] ?? 'form';
        $formToken = $_POST['form_token'] ?? '';
        $successMessage = $_POST['success_message'] ?? 'Thank you! Your submission has been received.';
        $redirectUrl = $_POST['redirect_url'] ?? '';

        // Validate one-time form nonce.
        if (!validate_form_nonce($formToken, 3600, $this->getClientIp())) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid or expired form token. Please refresh and try again.'
            ]);
            return;
        }

        // Validate form token via Defense hooks
        $defenseResult = HookManager::trigger('form_validate', [
            'token' => $formToken,
            'form_type' => $formName,
            'data' => $_POST
        ]);

        if (is_array($defenseResult)) {
            if (isset($defenseResult['should_engage']) && $defenseResult['should_engage']) {
                HookManager::trigger('request_start', [
                    'path' => '/api/form',
                    'method' => 'POST',
                    'ip' => $this->getClientIp(),
                    'context' => 'form_abuse'
                ]);
            }

            if (isset($defenseResult['valid']) && !$defenseResult['valid']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Submission failed security validation.'
                ]);
                return;
            }
        }

        // Extract all form fields (except hidden system fields)
        $formData = [];
        $systemFields = [
            'form_token',
            'form_name',
            'success_message',
            'redirect_url',
            'field_order',
            'mouse_entropy',
            'page_title',
            'page_url'
        ];

        foreach ($_POST as $key => $value) {
            if (!in_array($key, $systemFields)) {
                $formData[$key] = is_string($value) ? trim($value) : $value;
            }
        }

        // Basic validation - ensure we have some data
        if (empty($formData)) {
            echo json_encode([
                'success' => false,
                'message' => 'No form data received.'
            ]);
            return;
        }

        // SECURITY: Validate email addresses to prevent email header injection
        // Check all fields that look like email addresses
        foreach ($formData as $fieldName => $fieldValue) {
            if (stripos($fieldName, 'email') !== false && is_string($fieldValue) && !empty($fieldValue)) {
                // Validate email format
                $validatedEmail = filter_var($fieldValue, FILTER_VALIDATE_EMAIL);
                if (!$validatedEmail) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Please provide a valid email address.'
                    ]);
                    return;
                }

                // SECURITY: Additional check for email header injection attempts
                // Reject emails containing newlines or carriage returns
                if (preg_match("/[\r\n]/", $fieldValue)) {
                    error_log("Security: Email header injection attempt blocked from IP: " . $this->getClientIp());
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid email address format.'
                    ]);
                    return;
                }

                // Store validated email
                $formData[$fieldName] = $validatedEmail;
            }
        }

        // SECURITY: Validate input lengths to prevent DoS attacks
        $maxFieldLength = 10000; // Maximum characters per field
        foreach ($formData as $fieldName => $fieldValue) {
            if (is_string($fieldValue) && mb_strlen($fieldValue) > $maxFieldLength) {
                echo json_encode([
                    'success' => false,
                    'message' => "Field '{$fieldName}' exceeds maximum length of {$maxFieldLength} characters."
                ]);
                return;
            }
        }

        // Store submission via hook
        $submissionData = [
            'form_name' => $formName,
            'data' => $formData,
            'timestamp' => time(),
            'ip' => $this->getClientIp(),
            'from' => $_SERVER['HTTP_REFERER'] ?? 'unknown'
        ];

        $this->storeSubmission($submissionData);

        // Send email to admin
        $emailSent = $this->sendFormEmail($formName, $formData);

        if (!$emailSent) {
            error_log("Failed to send form submission email for: {$formName}");
        }

        // Update rate limit
        $_SESSION['last_form_submission'] = time();

        // Success response
        $response = [
            'success' => true,
            'message' => $successMessage
        ];

        if ($redirectUrl) {
            $response['redirect'] = $redirectUrl;
        }

        echo json_encode($response);
    }

    /**
     * Send form submission email to admin
     */
    private function sendFormEmail(string $formName, array $formData): bool
    {
        $adminEmail = $this->config['mail']['admin_email'] ?? '';
        if (!$adminEmail || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid or missing admin email in config");
            return false;
        }

        // Build email body
        $emailBody = "New form submission from: {$formName}\n\n";
        $emailBody .= "Submitted: " . date('Y-m-d H:i:s') . "\n";
        $emailBody .= "IP Address: " . $this->getClientIp() . "\n";
        $emailBody .= "Referrer: " . ($_SERVER['HTTP_REFERER'] ?? 'Direct') . "\n\n";
        $emailBody .= "Form Data:\n";
        $emailBody .= str_repeat('-', 50) . "\n\n";

        foreach ($formData as $field => $value) {
            $fieldLabel = ucwords(str_replace('_', ' ', $field));
            $emailBody .= "{$fieldLabel}:\n";

            if (is_array($value)) {
                $emailBody .= implode(', ', $value) . "\n\n";
            } else {
                $emailBody .= wordwrap($value, 70) . "\n\n";
            }
        }

        // Email headers
        $siteName = $this->config['site']['name'] ?? 'Flint';
        $subject = "[{$siteName}] New {$formName} submission";
        $headers = "From: {$siteName} <noreply@{$_SERVER['SERVER_NAME']}>\r\n";
        $headers .= "Reply-To: {$adminEmail}\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "X-Mailer: Flint\r\n";

        // Send email
        return mail($adminEmail, $subject, $emailBody, $headers);
    }


    /**
     * Read and decode a JSON request body.
     */
    private function readJsonPayload(): ?array
    {
        // Read the raw request body.
        $maxPayloadBytes = 2 * 1024 * 1024;
        if (isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > $maxPayloadBytes) {
            return null;
        }

        $rawBody = file_get_contents('php://input');
        if ($rawBody === false) {
            return null;
        }

        if (strlen($rawBody) > $maxPayloadBytes) {
            return null;
        }

        // Parse JSON into a structured array.
        $decodedBody = json_decode($rawBody, true);
        if (!is_array($decodedBody) || json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decodedBody;
    }

    /**
     * Validate path segments to avoid traversal and control characters.
     */
    private function isSafePathSegment(string $path): bool
    {
        // Disallow empty path segments.
        if ($path === '') {
            return false;
        }

        // Block obvious traversal or control characters.
        if (str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, "\0")) {
            return false;
        }

        // Allow only safe characters.
        return (bool)preg_match('/^[a-zA-Z0-9\/_-]+$/', $path);
    }

    /**
     * Merge new assets into existing component assets array.
     */
    private function mergeAssets(array $componentAssets, array $newAssets): array
    {
        // Initialize asset structure if empty.
        if (empty($componentAssets)) {
            $componentAssets = [
                'scripts' => [],
                'inline_scripts' => [],
                'styles' => [],
                'inline_styles' => []
            ];
        }

        // Merge each asset type.
        foreach (['scripts', 'inline_scripts', 'styles', 'inline_styles'] as $type) {
            if (!empty($newAssets[$type])) {
                foreach ($newAssets[$type] as $asset) {
                    $componentAssets[$type][] = $asset;
                }
            }
        }

        return $componentAssets;
    }

    /**
     * Get MIME type for file extension.
     */
    private function getMimeType(string $extension): string
    {
        $mimeTypes = [
            'js' => 'text/javascript',
            'mjs' => 'text/javascript',
            'css' => 'text/css',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject'
        ];
        return $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
    }

    /**
     * Sanitize filename for safe storage.
     */
    private function sanitizeFilename(string $filename): string
    {
        // Lowercase and replace spaces with hyphens.
        $filename = strtolower(str_replace(' ', '-', $filename));

        // Remove special characters (keep alphanumeric and hyphens).
        $filename = preg_replace('/[^a-z0-9\-]/', '', $filename);

        // Remove multiple consecutive hyphens.
        $filename = preg_replace('/-+/', '-', $filename);

        // Trim hyphens from start/end.
        $filename = trim($filename, '-');

        // Limit filename length to keep paths tidy.
        if (strlen($filename) > 80) {
            $filename = substr($filename, 0, 80);
        }

        // Ensure not empty.
        if (empty($filename)) {
            $filename = 'upload';
        }

        return $filename;
    }

    /**
     * Recursively remove a directory and all its contents.
     */
    private function recursiveRemoveDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->recursiveRemoveDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }

    /**
     * Extract the client IP for logging and blocking.
     */
    private function getClientIp(): string
    {
        $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($remoteAddress === '' || !filter_var($remoteAddress, FILTER_VALIDATE_IP)) {
            return '0.0.0.0';
        }

        return $remoteAddress;
    }

    /**
     * Validate CSRF token from request headers.
     *
     * @return bool True if token is valid, false otherwise
     */
    private function validateCsrfToken(): bool
    {
        $authService = new Auth($this);
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (empty($token)) {
            return false;
        }

        return $authService->validateCsrfToken($token);
    }

    /**
     * Sanitize SVG file by removing dangerous elements and attributes.
     *
     * @param string $svgPath Path to SVG file
     * @return bool True if sanitization succeeded, false otherwise
     */
    private function sanitizeSvg(string $svgPath): bool
    {
        $content = file_get_contents($svgPath);
        if ($content === false) {
            return false;
        }

        // Disable external entity loading (XXE protection).
        $oldEntityLoader = libxml_disable_entity_loader(true);
        $oldErrorLevel = libxml_use_internal_errors(true);

        try {
            // Load SVG as XML.
            $dom = new \DOMDocument();
            $dom->loadXML($content, LIBXML_NONET | LIBXML_NOENT);

            if (!$dom->documentElement || $dom->documentElement->tagName !== 'svg') {
                return false;
            }

            // Remove dangerous elements.
            $dangerousElements = [
                'script', 'object', 'embed', 'iframe', 'frame', 'frameset',
                'link', 'meta', 'style', 'foreign', 'foreignObject', 'use'
            ];

            foreach ($dangerousElements as $tagName) {
                $elements = $dom->getElementsByTagName($tagName);
                $toRemove = [];
                foreach ($elements as $element) {
                    $toRemove[] = $element;
                }
                foreach ($toRemove as $element) {
                    $element->parentNode->removeChild($element);
                }
            }

            // Remove event handlers and dangerous attributes.
            $xpath = new \DOMXPath($dom);
            $dangerousAttrs = [
                'onload', 'onerror', 'onclick', 'onmouseover', 'onmouseout',
                'onmousemove', 'onmousedown', 'onmouseup', 'onkeydown', 'onkeyup',
                'onkeypress', 'onfocus', 'onblur', 'onchange', 'onsubmit'
            ];

            $allElements = $xpath->query('//*');
            foreach ($allElements as $element) {
                if (!$element instanceof \DOMElement) {
                    continue;
                }

                // Remove event handler attributes.
                foreach ($dangerousAttrs as $attr) {
                    if ($element->hasAttribute($attr)) {
                        $element->removeAttribute($attr);
                    }
                }

                // Remove data URIs from href/xlink:href (can contain JavaScript).
                if ($element->hasAttribute('href')) {
                    $href = $element->getAttribute('href');
                    if (stripos($href, 'data:') === 0 || stripos($href, 'javascript:') === 0) {
                        $element->removeAttribute('href');
                    }
                }
                if ($element->hasAttribute('xlink:href')) {
                    $xlinkHref = $element->getAttribute('xlink:href');
                    if (stripos($xlinkHref, 'data:') === 0 || stripos($xlinkHref, 'javascript:') === 0) {
                        $element->removeAttribute('xlink:href');
                    }
                }
            }

            // Save sanitized SVG.
            $sanitized = $dom->saveXML();
            if ($sanitized === false) {
                return false;
            }

            file_put_contents($svgPath, $sanitized, LOCK_EX);
            return true;
        } catch (\Exception $e) {
            return false;
        } finally {
            libxml_disable_entity_loader($oldEntityLoader);
            libxml_use_internal_errors($oldErrorLevel);
        }
    }

    /**
     * Identify obvious probe paths for WordPress/Joomla and similar attacks.
     */
    private function isTrapPath(string $requestPath): bool
    {
        $normalizedPath = rtrim($requestPath, '/');

        $trapExactPaths = [
            '/wp-login.php',
            '/wp-admin',
            '/wp-admin.php',
            '/xmlrpc.php',
            '/administrator',
            '/administrator/index.php',
            '/joomla'
        ];

        if (in_array($normalizedPath, $trapExactPaths, true)) {
            return true;
        }

        $trapPrefixes = [
            '/wp-content',
            '/wp-includes',
            '/wp-json',
            '/.env',
            '/.git',
            '/phpmyadmin',
            '/pma'
        ];

        foreach ($trapPrefixes as $trapPrefix) {
            if (str_starts_with($normalizedPath, $trapPrefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the requesting IP is currently blocked.
     */
    private function isIpBlocked(string $ipAddress): bool
    {
        if ($ipAddress === '0.0.0.0') {
            return false;
        }

        $blockedIps = $this->loadBlockedIps();
        if (empty($blockedIps)) {
            return false;
        }

        $currentTime = time();
        $wasUpdated = false;

        foreach ($blockedIps as $blockedIp => $entry) {
            $expiresAt = null;

            if (is_array($entry) && isset($entry['expires_at'])) {
                $expiresAt = (int)$entry['expires_at'];
            } elseif (is_int($entry)) {
                $expiresAt = $entry;
            }

            if ($expiresAt === null || $expiresAt <= $currentTime) {
                unset($blockedIps[$blockedIp]);
                $wasUpdated = true;
            }
        }

        if ($wasUpdated) {
            $this->saveBlockedIps($blockedIps);
        }

        if (!isset($blockedIps[$ipAddress])) {
            return false;
        }

        $entry = $blockedIps[$ipAddress];
        if (is_array($entry) && isset($entry['expires_at'])) {
            return (int)$entry['expires_at'] > $currentTime;
        }

        if (is_int($entry)) {
            return $entry > $currentTime;
        }

        return false;
    }

    /**
     * Add an IP to the blocklist for a fixed window.
     */
    private function blockIpForSeconds(string $ipAddress, int $seconds, string $reason): void
    {
        if ($ipAddress === '0.0.0.0' || $seconds <= 0) {
            return;
        }

        $blockedIps = $this->loadBlockedIps();
        $blockedIps[$ipAddress] = [
            'expires_at' => time() + $seconds,
            'reason' => $reason
        ];

        $this->saveBlockedIps($blockedIps);
    }

    /**
     * Read the on-disk IP blocklist.
     */
    private function loadBlockedIps(): array
    {
        $blocklistPath = $this->getBlocklistPath();
        if (!file_exists($blocklistPath)) {
            return [];
        }

        $rawContents = file_get_contents($blocklistPath);
        if ($rawContents === false) {
            return [];
        }

        $decoded = json_decode($rawContents, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Persist the IP blocklist to disk.
     */
    private function saveBlockedIps(array $blockedIps): void
    {
        $blocklistPath = $this->getBlocklistPath();
        file_put_contents($blocklistPath, json_encode($blockedIps, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * Clear any stored IP blocklist entries.
     */
    private function clearBlockedIps(): void
    {
        $blocklistPath = $this->getBlocklistPath();
        if (file_exists($blocklistPath)) {
            unlink($blocklistPath);
            return;
        }

        $this->saveBlockedIps([]);
    }

    /**
     * Return the current blocklist entries with human-friendly metadata.
     *
     * @return array<int, array{ip:string,expires_at:int,expires_in:string,reason:string}>
     */
    private function listBlockedIps(): array
    {
        $blockedIps = $this->loadBlockedIps();
        if (empty($blockedIps)) {
            return [];
        }

        $now = time();
        $items = [];
        $updated = false;

        foreach ($blockedIps as $blockedIp => $entry) {
            $expiresAt = null;
            $reason = 'Security block';

            if (is_array($entry)) {
                if (isset($entry['expires_at'])) {
                    $expiresAt = (int)$entry['expires_at'];
                }
                if (isset($entry['reason']) && is_string($entry['reason'])) {
                    $reason = $entry['reason'];
                }
            } elseif (is_int($entry)) {
                $expiresAt = $entry;
            }

            if ($expiresAt === null || $expiresAt <= $now) {
                unset($blockedIps[$blockedIp]);
                $updated = true;
                continue;
            }

            $items[] = [
                'ip' => $blockedIp,
                'expires_at' => $expiresAt,
                'expires_in' => $this->formatExpiryWindow($expiresAt - $now),
                'reason' => $reason,
            ];
        }

        if ($updated) {
            $this->saveBlockedIps($blockedIps);
        }

        usort($items, fn($a, $b) => $b['expires_at'] <=> $a['expires_at']);

        return $items;
    }

    /**
     * Format a remaining seconds window into a short label.
     */
    private function formatExpiryWindow(int $remainingSeconds): string
    {
        if ($remainingSeconds <= 0) {
            return 'Expired';
        }

        $minutes = (int)ceil($remainingSeconds / 60);
        if ($minutes < 60) {
            return $minutes . ' min left';
        }

        $hours = (int)floor($minutes / 60);
        $leftMinutes = $minutes % 60;
        if ($hours < 24) {
            return $leftMinutes > 0
                ? $hours . 'h ' . $leftMinutes . 'm left'
                : $hours . 'h left';
        }

        $days = (int)floor($hours / 24);
        return $days . 'd left';
    }

    /**
     * Clear stored session files from disk.
     *
     * @return array{success:bool,removed?:int,errors?:int,message?:string,error?:string}
     */
    private function clearSessionFiles(): array
    {
        $handler = (string)(ini_get('session.save_handler') ?: 'files');
        if ($handler !== 'files') {
            return ['success' => false, 'error' => 'Session handler is not file-based.'];
        }

        $savePath = $this->parseSessionSavePath((string)ini_get('session.save_path'));
        if ($savePath === null || !is_dir($savePath)) {
            return ['success' => false, 'error' => 'Session save path not available.'];
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $removed = 0;
        $errors = 0;
        foreach ($this->findSessionFiles($savePath) as $file) {
            if (!is_file($file)) {
                continue;
            }

            if (unlink($file)) {
                $removed++;
            } else {
                $errors++;
            }
        }

        $message = $errors === 0
            ? "Cleared {$removed} session file(s)."
            : "Cleared {$removed} session file(s) with {$errors} error(s).";

        return [
            'success' => $errors === 0,
            'removed' => $removed,
            'errors' => $errors,
            'message' => $message,
        ];
    }

    /**
     * Clear stored magic link tokens and cooldown markers.
     *
     * @return array{success:bool,removed?:int,errors?:int,message?:string}
     */
    private function clearMagicLinkArtifacts(): array
    {
        $removed = 0;
        $errors = 0;

        $tokenPath = $this->appDir . '/storage/.tokens/magic-link.json';
        if (file_exists($tokenPath)) {
            if (unlink($tokenPath)) {
                $removed++;
            } else {
                $errors++;
            }
        }

        $submissionsDir = $this->root . '/site/submissions';
        if (is_dir($submissionsDir)) {
            $files = glob($submissionsDir . '/.magic-link-*');
            if ($files !== false) {
                foreach ($files as $file) {
                    if (!is_file($file)) {
                        continue;
                    }

                    if (unlink($file)) {
                        $removed++;
                    } else {
                        $errors++;
                    }
                }
            }
        }

        $message = $errors === 0
            ? "Cleared {$removed} magic link artifact(s)."
            : "Cleared {$removed} magic link artifact(s) with {$errors} error(s).";

        return [
            'success' => $errors === 0,
            'removed' => $removed,
            'errors' => $errors,
            'message' => $message,
        ];
    }

    /**
     * Parse session.save_path into a usable directory path.
     */
    private function parseSessionSavePath(string $rawPath): ?string
    {
        $rawPath = trim($rawPath);
        if ($rawPath === '') {
            return null;
        }

        $parts = array_values(array_filter(
            explode(';', $rawPath),
            static fn (string $value): bool => $value !== ''
        ));
        $path = end($parts);
        if (!is_string($path)) {
            return null;
        }

        return $path;
    }

    /**
     * Locate session files inside the save path.
     *
     * @return array<int,string>
     */
    private function findSessionFiles(string $savePath): array
    {
        $files = [];

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($savePath, \FilesystemIterator::SKIP_DOTS)
            );
        } catch (\UnexpectedValueException $e) {
            return $files;
        }

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            if (str_starts_with($fileInfo->getFilename(), 'sess_')) {
                $files[] = $fileInfo->getPathname();
            }
        }

        return $files;
    }

    /**
     * Resolve the blocklist file path and ensure the storage directory exists.
     */
    private function getBlocklistPath(): string
    {
        $storageDir = $this->appDir . '/storage';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        return $storageDir . '/blocked-ips.json';
    }

    /**
     * Store a form submission as a JSON file.
     */
    private function storeSubmission(array $data): void
    {
        // Store in site/submissions/forms/ (event log structure)
        $formsDir = $this->root . '/site/submissions/forms';

        // Create directory if it doesn't exist.
        if (!is_dir($formsDir)) {
            mkdir($formsDir, 0750, true);
        }

        // Create .htaccess in root submissions directory if it doesn't exist.
        $submissionsDir = $this->root . '/site/submissions';
        $htaccessPath = $submissionsDir . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            file_put_contents(
                $htaccessPath,
                "# Deny all web access to event submissions\nOrder Allow,Deny\nDeny from all\n",
                LOCK_EX
            );
        }

        // Generate unique filename.
        $filename = 'form-' . time() . '-' . bin2hex(random_bytes(4)) . '.json';
        $filepath = $formsDir . '/' . $filename;

        // SECURITY: Validate path to prevent symlink attacks
        try {
            $validatedPath = $this->validateSecurePath($filepath, Paths::$submissionsDir);
        } catch (\Exception $e) {
            error_log("Failed to validate submission path: " . $e->getMessage());
            return;
        }

        // Save submission.
        file_put_contents(
            $validatedPath,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    /**
     * Load all form submissions from the submissions directory.
     */
    private function loadSubmissions(): array
    {
        $formsDir = $this->root . '/site/submissions/forms';

        if (!is_dir($formsDir)) {
            return [];
        }

        $submissions = [];
        $files = glob($formsDir . '/form-*.json');

        if ($files === false) {
            return [];
        }

        // Sort by modification time (newest first).
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $data['filename'] = basename($file);
                    $submissions[] = $data;
                }
            }
        }

        return $submissions;
    }

    /**
     * Delete all form submissions.
     */
    private function clearSubmissions(): void
    {
        $formsDir = $this->root . '/site/submissions/forms';

        if (!is_dir($formsDir)) {
            return;
        }

        $files = glob($formsDir . '/form-*.json');
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * List all installed components with their configuration and status.
     *
     * @return array List of installed components
     */
    private function listInstalledComponents(): array
    {
        $componentsDir = $this->root . '/site/components';
        $components = [];
        $registryMap = [];

        if (!is_dir($componentsDir)) {
            return $components;
        }

        foreach ($this->browseAvailableComponents() as $component) {
            $registryName = strtolower((string)($component['name'] ?? ''));
            $registrySlug = strtolower((string)($component['slug'] ?? ''));
            if ($registryName !== '') {
                $registryMap[$registryName] = $component;
            }
            if ($registrySlug !== '') {
                $registryMap[$registrySlug] = $component;
            }
        }

        $directories = glob($componentsDir . '/*', GLOB_ONLYDIR);

        foreach ($directories as $dir) {
            $name = basename($dir);
            $configPath = $dir . '/config.php';

            if (!file_exists($configPath)) {
                continue;
            }

            $config = require $configPath;
            $enabledValue = $config['component']['enabled'] ?? false;
            $enabled = is_bool($enabledValue)
                ? $enabledValue
                : filter_var($enabledValue, FILTER_VALIDATE_BOOLEAN);

            $downloadUrl = '';
            $sha256 = '';
            if (isset($config['component']['source']) && is_array($config['component']['source'])) {
                $downloadUrl = (string)($config['component']['source']['download_url'] ?? '');
                $sha256 = (string)($config['component']['source']['sha256'] ?? '');
            }
            if (isset($config['component']['download_url'])) {
                $downloadUrl = (string)$config['component']['download_url'];
            }
            if (isset($config['component']['sha256'])) {
                $sha256 = (string)$config['component']['sha256'];
            }

            $registryKey = strtolower((string)($config['component']['slug'] ?? $name));
            if (isset($registryMap[$registryKey])) {
                $registryEntry = $registryMap[$registryKey];
                $downloadUrl = (string)($registryEntry['download_url'] ?? $downloadUrl);
                $sha256 = (string)($registryEntry['sha256'] ?? $sha256);
                $latestVersion = (string)($registryEntry['version'] ?? '');
            } else {
                $latestVersion = '';
            }

            $components[] = [
                'name' => $name,
                'slug' => $config['component']['slug'] ?? $name,
                'displayName' => $config['component']['name'] ?? $name,
                'version' => $config['component']['version'] ?? '1.0.0',
                'latest_version' => $latestVersion,
                'author' => $config['component']['author'] ?? 'Unknown',
                'description' => $config['component']['description'] ?? '',
                'enabled' => $enabled,
                'type' => $config['component']['type'] ?? 'render',
                'download_url' => $downloadUrl,
                'sha256' => $sha256,
            ];
        }

        return $components;
    }

    /**
     * Browse available components from configured registries.
     *
     * @return array List of available components
     */
    private function browseAvailableComponents(): array
    {
        $components = [];
        $seen = [];

        foreach ($this->getComponentRegistryUrls() as $registryUrl) {
            foreach ($this->fetchComponentRegistry($registryUrl) as $component) {
                $normalized = $this->normalizeRegistryComponent($component);
                if ($normalized === null) {
                    continue;
                }

                $key = strtolower($normalized['slug'] ?: $normalized['name']);
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $components[] = $normalized;
            }
        }

        return $components;
    }

    /**
     * Resolve the component registry URLs from config.
     *
     * @return array
     */
    private function getComponentRegistryUrls(): array
    {
        $registries = $this->config['components']['registries'] ?? [];
        if (is_string($registries)) {
            $registries = [$registries];
        }

        $normalized = array_values(array_filter(array_map(function ($url) {
            if (!is_string($url)) {
                return null;
            }
            $url = trim($url);
            return $url !== '' ? $url : null;
        }, $registries)));

        if ($normalized === []) {
            return [self::DEFAULT_COMPONENT_REGISTRY];
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Fetch a registry JSON payload.
     */
    private function fetchComponentRegistry(string $registryUrl): array
    {
        $ch = curl_init($registryUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Flint/' . \Flint\Version::VERSION);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode != 200 || $response === false) {
            return [];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return [];
        }

        $components = $data['components'] ?? [];
        return is_array($components) ? $components : [];
    }

    /**
     * Normalize a registry entry into the admin payload shape.
     */
    private function normalizeRegistryComponent(array $component): ?array
    {
        $name = trim((string)($component['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $slug = trim((string)($component['slug'] ?? ''));
        if ($slug === '') {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        }

        $downloadUrl = trim((string)($component['download_url'] ?? ''));
        if ($downloadUrl === '') {
            return null;
        }

        return [
            'name' => $name,
            'slug' => $slug,
            'displayName' => $component['displayName'] ?? $name,
            'version' => (string)($component['version'] ?? ''),
            'author' => (string)($component['author'] ?? 'Unknown'),
            'description' => (string)($component['description'] ?? ''),
            'type' => (string)($component['type'] ?? 'render'),
            'requires' => (string)($component['requires'] ?? ''),
            'download_url' => $downloadUrl,
            'sha256' => (string)($component['sha256'] ?? ''),
            'homepage' => (string)($component['homepage'] ?? ''),
        ];
    }

    /**
     * Find a registry component entry by name or slug.
     */
    private function findRegistryComponent(string $name): ?array
    {
        $needle = strtolower($name);
        foreach ($this->browseAvailableComponents() as $component) {
            $componentName = strtolower((string)($component['name'] ?? ''));
            $componentSlug = strtolower((string)($component['slug'] ?? ''));
            if ($needle === $componentName || $needle === $componentSlug) {
                return $component;
            }
        }

        return null;
    }

    /**
     * Install a component from a registry package.
     */
    private function installComponent(string $name, string $downloadUrl, string $checksum = ''): array
    {
        $name = trim($name);
        $downloadUrl = trim($downloadUrl);
        $checksum = trim($checksum);

        if ($name === '') {
            return ['success' => false, 'error' => 'Component name required'];
        }

        if (!preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
            return ['success' => false, 'error' => 'Invalid component name'];
        }

        if ($downloadUrl === '' || !filter_var($downloadUrl, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'error' => 'Invalid download URL'];
        }

        $scheme = parse_url($downloadUrl, PHP_URL_SCHEME);
        if ($scheme !== 'https') {
            return ['success' => false, 'error' => 'Download URL must be HTTPS'];
        }

        $componentsDir = $this->root . '/site/components';
        if (!is_dir($componentsDir)) {
            mkdir($componentsDir, 0755, true);
        }

        $targetDir = $componentsDir . '/' . $name;
        if (is_dir($targetDir)) {
            return ['success' => false, 'error' => 'Component already installed'];
        }

        $tempZip = sys_get_temp_dir() . '/' . uniqid('component_', true) . '.zip';
        $tempDir = sys_get_temp_dir() . '/' . uniqid('component_extract_', true);

        $fp = fopen($tempZip, 'w');
        if ($fp === false) {
            return ['success' => false, 'error' => 'Unable to create temporary file'];
        }

        $ch = curl_init($downloadUrl);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!$success || $httpCode !== 200) {
            @unlink($tempZip);
            return ['success' => false, 'error' => 'Failed to download component'];
        }

        if ($checksum !== '') {
            $hash = hash_file('sha256', $tempZip);
            if (!hash_equals(strtolower($checksum), strtolower($hash))) {
                @unlink($tempZip);
                return ['success' => false, 'error' => 'Component checksum mismatch'];
            }
        }

        $zip = new \ZipArchive();
        if ($zip->open($tempZip) != true) {
            @unlink($tempZip);
            return ['success' => false, 'error' => 'Failed to read component archive'];
        }

        if (!mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
            $zip->close();
            @unlink($tempZip);
            return ['success' => false, 'error' => 'Unable to extract component'];
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry === false) {
                continue;
            }

            if (strpos($entry, '..') !== false || strpos($entry, './') == 0 || $entry[0] == '/') {
                $zip->close();
                $this->recursiveRemoveDirectory($tempDir);
                @unlink($tempZip);
                return ['success' => false, 'error' => 'Invalid file path in component archive'];
            }

            if (!$zip->extractTo($tempDir, $entry)) {
                $zip->close();
                $this->recursiveRemoveDirectory($tempDir);
                @unlink($tempZip);
                return ['success' => false, 'error' => 'Failed to extract component'];
            }
        }
        $zip->close();

        $entries = array_values(array_diff(scandir($tempDir), ['.', '..']));
        $directories = array_values(array_filter($entries, function ($entry) use ($tempDir) {
            return is_dir($tempDir . '/' . $entry);
        }));

        $componentRoot = null;
        if (count($directories) === 1) {
            $componentRoot = $directories[0];
        } elseif (is_dir($tempDir . '/' . $name)) {
            $componentRoot = $name;
        }

        if ($componentRoot === null) {
            $this->recursiveRemoveDirectory($tempDir);
            @unlink($tempZip);
            return ['success' => false, 'error' => 'Component archive missing root folder'];
        }

        if ($componentRoot != $name) {
            $this->recursiveRemoveDirectory($tempDir);
            @unlink($tempZip);
            return ['success' => false, 'error' => 'Component folder name mismatch'];
        }

        $sourceDir = $tempDir . '/' . $componentRoot;

        try {
            $validatedTarget = $this->validateSecurePath($targetDir, Paths::$siteComponentsDir);
        } catch (\Exception $e) {
            $this->recursiveRemoveDirectory($tempDir);
            @unlink($tempZip);
            return ['success' => false, 'error' => 'Invalid component path'];
        }

        $moved = @rename($sourceDir, $validatedTarget);
        if (!$moved) {
            $this->recursiveCopy($sourceDir, $validatedTarget);
            $this->recursiveRemoveDirectory($sourceDir);
        }

        $this->recursiveRemoveDirectory($tempDir);
        @unlink($tempZip);

        return ['success' => true, 'message' => 'Component installed successfully'];
    }

    private function updateComponent(string $name): array
    {
        $componentDir = $this->root . '/site/components/' . $name;
        $configPath = $componentDir . '/config.php';

        if (!is_dir($componentDir)) {
            return ['success' => false, 'error' => 'Component not found'];
        }

        $registryComponent = $this->findRegistryComponent($name);
        if ($registryComponent === null) {
            return ['success' => false, 'error' => 'Component not found in registry'];
        }

        $downloadUrl = $registryComponent['download_url'] ?? '';
        $checksum = $registryComponent['sha256'] ?? '';
        $latestVersion = $registryComponent['version'] ?? '';

        $installedVersion = '';
        if (file_exists($configPath)) {
            $config = require $configPath;
            $installedVersion = (string)($config['component']['version'] ?? '');
        }

        if ($installedVersion !== '' && $latestVersion !== '' && version_compare($installedVersion, $latestVersion, '>=')) {
            return ['success' => false, 'error' => 'Component is already up to date'];
        }

        // Backup current component.
        $backupDir = $componentDir . '_backup_' . time();
        if (!rename($componentDir, $backupDir)) {
            return ['success' => false, 'error' => 'Failed to backup component'];
        }

        // Try to install updated version.
        $result = $this->installComponent($name, $downloadUrl, $checksum);

        if (!$result['success']) {
            // Restore backup on failure.
            if (is_dir($backupDir)) {
                rename($backupDir, $componentDir);
            }
            return $result;
        }

        // Remove backup on success.
        $this->deleteDirectory($backupDir);

        return ['success' => true, 'message' => 'Component updated successfully'];
    }

    /**
     * Toggle a component enabled/disabled state.
     *
     * @param string $name Component name
     * @param bool $enabled Whether to enable or disable
     * @return array Result with success status and message
     */
    private function toggleComponent(string $name, bool $enabled): array
    {
        $componentDir = $this->root . '/site/components/' . $name;
        $configPath = $componentDir . '/config.php';

        if (!file_exists($configPath)) {
            return ['success' => false, 'error' => 'Component not found'];
        }

        $config = require $configPath;
        $config['component']['enabled'] = $enabled;

        $this->writePhpConfig($configPath, $config);

        $status = $enabled ? 'enabled' : 'disabled';
        return ['success' => true, 'message' => "Component {$status} successfully"];
    }

    /**
     * Delete a component.
     *
     * @param string $name Component name
     * @return array Result with success status and message
     */
    private function deleteComponent(string $name): array
    {
        $componentDir = $this->root . '/site/components/' . $name;

        if (!is_dir($componentDir)) {
            return ['success' => false, 'error' => 'Component not found'];
        }

        $this->deleteDirectory($componentDir);

        return ['success' => true, 'message' => 'Component deleted successfully'];
    }

    /**
     * Recursively delete a directory.
     *
     * @param string $dir Directory path
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Write PHP config file from array.
     *
     * @param string $path Path to config file
     * @param array $data Configuration array
     */
    private function writePhpConfig(string $path, array $data): void
    {
        $php = "<?php\n/**\n * Component Configuration\n */\n\nreturn ";
        $php .= var_export($data, true);
        $php .= ";\n";
        file_put_contents($path, $php, LOCK_EX);
    }

    /**
     * Write the main application config file.
     *
     * @param string $path Path to config file
     * @param array $data Configuration array
     */
    private function writeAppConfig(string $path, array $data): void
    {
        $php = "<?php\n/**\n * Flint Configuration\n *\n * This file contains sensitive configuration. Keep secure permissions (0600).\n * DO NOT commit this file to version control.\n */\n\nreturn ";
        $php .= var_export($data, true);
        $php .= ";\n";
        file_put_contents($path, $php, LOCK_EX);
        @chmod($path, 0600);
    }

    /**
     * Load theme configuration from theme's config.php file.
     *
     * @param string $themeName Theme name
     * @return array Theme configuration array
     */
    private function loadThemeConfig(string $themeName): array
    {
        $themeConfigPath = $this->root . '/site/themes/' . $themeName . '/config.php';

        if (file_exists($themeConfigPath)) {
            // SECURITY: Validate path to prevent symlink attacks
            try {
                $validatedPath = $this->validateSecurePath($themeConfigPath, Paths::$themesDir);
                $themeConfig = require $validatedPath;
                return is_array($themeConfig) ? $themeConfig : [];
            } catch (\Exception $e) {
                error_log("Failed to load theme config: " . $e->getMessage());
                return [];
            }
        }

        return []; // No config, return empty array
    }

    /**
     * Enforce security measures: create missing .htaccess and index.php sentinel files.
     */
    private function enforceSecurityMeasures(): void
    {
        // Define .htaccess rules for each directory.
        $htaccessRules = [
            $this->root . '/site/uploads/.htaccess' => [
                '# Prevent PHP execution in uploads directory',
                '<FilesMatch "\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$">',
                '    Order Allow,Deny',
                '    Deny from all',
                '</FilesMatch>',
                '',
                '# Prevent .htaccess override',
                '<Files .htaccess>',
                '    Order Allow,Deny',
                '    Deny from all',
                '</Files>',
            ],
            $this->root . '/site/pages/.htaccess' => [
                '# Block direct access to markdown files',
                '<FilesMatch "\.md$|\.mdx$">',
                '    Order Allow,Deny',
                '    Deny from all',
                '</FilesMatch>',
                '',
                '# Prevent PHP execution',
                '<FilesMatch "\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$">',
                '    Order Allow,Deny',
                '    Deny from all',
                '</FilesMatch>',
            ],
            $this->root . '/site/blocks/.htaccess' => [
                '# Block direct access to block files',
                '<FilesMatch "\.md$|\.mdx$">',
                '    Order Allow,Deny',
                '    Deny from all',
                '</FilesMatch>',
                '',
                '# Prevent PHP execution',
                '<FilesMatch "\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$">',
                '    Order Allow,Deny',
                '    Deny from all',
                '</FilesMatch>',
            ],
            $this->root . '/site/components/.htaccess' => [
                '# Prevent direct web execution of components',
                '<FilesMatch "\.php$">',
                '    Order Allow,Deny',
                '    Deny from all',
                '</FilesMatch>',
            ],
            $this->appDir . '/.htaccess' => [
                '# Deny all web access to app directory',
                'Order Allow,Deny',
                'Deny from all',
            ],
        ];

        // Create missing .htaccess files.
        foreach ($htaccessRules as $path => $lines) {
            if (!file_exists($path)) {
                $dir = dirname($path);
                if (is_dir($dir)) {
                    file_put_contents($path, implode("\n", $lines) . "\n");
                }
            }
        }

        // Define directories that need index.php sentinel files.
        $sentinelDirs = [
            $this->root . '/site',
            $this->root . '/site/uploads',
            $this->root . '/site/pages',
            $this->root . '/site/blocks',
            $this->root . '/site/submissions',
            $this->root . '/site/components',
            $this->root . '/site/themes',
            $this->appDir . '/core',
        ];

        // Create missing index.php sentinel files.
        foreach ($sentinelDirs as $dir) {
            if (is_dir($dir)) {
                $indexFile = $dir . '/index.php';
                if (!file_exists($indexFile)) {
                    file_put_contents($indexFile, "<?php // Shhh.\n");
                }
            }
        }
    }

    /**
     * ==================================================================
     * HELPER FUNCTIONS
     *
     * Utility functions to make CMS development easier and friendlier.
     * ==================================================================
     */

    /**
     * Get current month upload directory (yyyymm format)
     *
     * @param int|null $timestamp Optional timestamp (defaults to now)
     * @return string Path like '/site/uploads/202501'
     */
    public function getUploadDir(?int $timestamp = null): string
    {
        $yearMonth = date('Ym', $timestamp ?? time());
        return $this->root . '/site/uploads/' . $yearMonth;
    }

    /**
     * Ensure directory exists with proper permissions
     *
     * @param string $path Directory path
     * @param int $permissions Directory permissions (default: 0755)
     * @return bool True if directory exists or was created
     */
    public function ensureDir(string $path, int $permissions = 0755): bool
    {
        if (is_dir($path)) {
            return true;
        }

        return mkdir($path, $permissions, true);
    }

    /**
     * Write JSON file with proper formatting and locking
     *
     * @param string $path File path
     * @param mixed $data Data to encode
     * @param bool $pretty Pretty print JSON (default: true)
     * @return bool True on success
     */
    public function writeJson(string $path, mixed $data, bool $pretty = true): bool
    {
        $flags = LOCK_EX;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
        }

        $json = json_encode($data, $flags);
        if ($json === false) {
            return false;
        }

        return file_put_contents($path, $json, LOCK_EX) !== false;
    }

    /**
     * Read and decode JSON file
     *
     * @param string $path File path
     * @param bool $assoc Return as associative array (default: true)
     * @return mixed Decoded data or null on failure
     */
    public function readJson(string $path, bool $assoc = true): mixed
    {
        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        return json_decode($content, $assoc);
    }


    /**
     * Format bytes to human-readable size
     *
     * @param int $bytes Number of bytes
     * @param int $precision Decimal places (default: 2)
     * @return string Formatted size like '2.34 MB'
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Log message to file with timestamp
     *
     * @param string $message Log message
     * @param string $level Log level (info, warning, error)
     * @param string $category Optional category/component name
     */
    public function log(string $message, string $level = 'info', string $category = 'app'): void
    {
        $logDir = $this->root . '/site/submissions/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }

        $date = date('Y-m-d H:i:s');
        $line = "[{$date}] [{$level}] [{$category}] {$message}\n";

        $logFile = $logDir . '/app-' . date('Ym') . '.log';
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Escape HTML output safely
     *
     * @param string $text Text to escape
     * @return string Escaped text
     */
    public function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Generate secure random token
     *
     * @param int $length Token length in bytes (default: 32 = 64 hex chars)
     * @return string Hex token
     */
    public function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Check if request is AJAX
     *
     * @return bool True if AJAX request
     */
    public function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Get file extension safely
     *
     * @param string $filename Filename or path
     * @return string Lowercase extension without dot
     */
    public function getExtension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    /**
     * Slugify string for URLs
     *
     * @param string $text Text to slugify
     * @return string URL-safe slug
     */
    public function slugify(string $text): string
    {
        // Convert to lowercase
        $text = strtolower($text);

        // Replace non-alphanumeric with hyphens
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        // Remove leading/trailing hyphens
        $text = trim($text, '-');

        return $text;
    }

    /**
     * Validate file path against symlink attacks
     *
     * SECURITY: Prevents symlink attacks where attackers create symbolic links
     * to sensitive files (like /etc/passwd) and trick the application into
     * reading/writing them.
     *
     * ATTACK EXAMPLE:
     * ```bash
     * # Attacker creates:
     * ln -s /etc/passwd site/uploads/passwords.txt
     * # Then requests: /api/upload?file=passwords.txt
     * # Without this check, app would read /etc/passwd
     * ```
     *
     * HOW IT WORKS:
     * - realpath() resolves symlinks to their actual target
     * - We verify the real path is within allowed directories
     * - If path escapes allowed dirs, it's rejected
     *
     * @param string $filePath Path to validate
     * @param string $allowedBaseDir Base directory path must be within (default: site dir)
     * @return string Validated real path
     * @throws \Exception If path is invalid or outside allowed directory
     */
    private function validateSecurePath(string $filePath, ?string $allowedBaseDir = null): string
    {
        if ($allowedBaseDir === null) {
            $allowedBaseDir = Paths::$siteDir;
        }

        try {
            return resolve_secure_path($filePath, $allowedBaseDir, true);
        } catch (\RuntimeException $e) {
            error_log("Security: Path traversal attempt blocked - path: {$filePath}, base: {$allowedBaseDir}, IP: " . $this->getClientIp());
            throw new \Exception('Invalid file path - security violation');
        }
    }
}
