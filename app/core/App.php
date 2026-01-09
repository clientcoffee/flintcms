<?php

namespace Flint;

/**
 * Core Application Class.
 * Handles routing, configuration loading, and theme rendering.
 */
class App
{
    public readonly array $config;
    public readonly string $root;
    public readonly string $appDir;
    public readonly Scheduler $scheduler;

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

        // Load the configuration file from app directory.
        $configPath = Paths::$configFile;

        if (!file_exists($configPath)) {
            throw new \Exception("Configuration file (config.php) missing. Run setup to generate it.");
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
     * Orchestrate the request lifecycle from routing to rendering.
     */
    public function run(): void
    {
        // Normalize the request path.
        $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $requestPath = urldecode($requestPath);

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
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
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

        // Serve static files from /content/uploads/.
        if (str_starts_with($requestPath, '/content/uploads/')) {
            // Allow direct access to user-uploaded media.
            $uploadFilePath = $this->root . $requestPath;

            // Security: Validate path stays within uploads directory.
            $realUploadPath = realpath($uploadFilePath);
            $realUploadsDir = realpath($this->root . '/content/uploads');

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
            $themeAssetPath = $this->root . '/content/themes/' . $themeName . '/' . $assetPath;

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
            $componentAssetPath = $this->root . '/content/components/' . $componentName . '/' . $assetPath;

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

        // Resolve the content file for the request.
        $resolvedContentPath = $this->resolveContentFile($requestPath);
        if (!$resolvedContentPath) {
            $this->render404();
            return;
        }

        // Render the resolved page through the theme.
        $this->render($resolvedContentPath);
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

        // Contact form submission (public endpoint, no auth required) - DEPRECATED.
        if ($requestPath === '/api/contact' && $requestMethod === 'POST') {
            $this->handleContactForm();
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
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid file type']);
                return;
            }

            // SECURITY: Comprehensive scan for dangerous code in uploaded files
            // Only markdown files are allowed to contain code (for documentation)
            if (!in_array($extension, ['md', 'mdx'])) {
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
                $targetPath = $this->root . '/content/pages/' . $finalFilename;

                // Ensure pages directory exists.
                if (!is_dir($this->root . '/content/pages')) {
                    mkdir($this->root . '/content/pages', 0755, true);
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

                $fileUrl = '/content/pages/' . $finalFilename;
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
                        $themeDir = $this->root . '/content/themes/' . $themeName;

                        // Ensure themes directory exists.
                        if (!is_dir($this->root . '/content/themes')) {
                            mkdir($this->root . '/content/themes', 0755, true);
                        }

                        // Remove existing theme if present.
                        if (is_dir($themeDir)) {
                            $this->recursiveRemoveDirectory($themeDir);
                        }

                        // Extract theme safely (prevent ZIP slip attack).
                        $realThemeDir = realpath($this->root . '/content/themes');
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
            $uploadDir = $this->root . '/content/uploads/' . $yearMonth;
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
            $fileUrl = '/content/uploads/' . $yearMonth . '/' . $finalFilename;
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

        // Browse available components from API (admin only).
        if ($requestPath === '/api/components/browse' && $requestMethod === 'GET') {
            $available = $this->browseAvailableComponents();
            echo json_encode(['success' => true, 'components' => $available]);
            return;
        }

        // Install component from GitHub (admin only).
        if ($requestPath === '/api/components/install' && $requestMethod === 'POST') {
            if (!$this->validateCsrfToken()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                return;
            }
            $payload = $this->readJsonPayload();
            $repo = $payload['repo'] ?? '';
            $result = $this->installComponent($repo);
            echo json_encode($result);
            return;
        }

        // Update component from GitHub (admin only).
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
        if ($requestPath === '/api/content/list' && $requestMethod === 'GET') {
            $items = $this->listContentTree();
            echo json_encode(['success' => true, 'items' => $items]);
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
        if ($requestPath === '/api/content' && $requestMethod === 'GET') {
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
                // Load config.php and return as flat key structure.
                $configPath = $this->appDir . '/config.php';
                if (!file_exists($configPath)) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Config file not found']);
                    return;
                }

                $configData = require $configPath;
                $flatConfig = [];
                foreach ($configData as $section => $values) {
                    if (is_array($values)) {
                        foreach ($values as $key => $value) {
                            $flatConfig[$section . '.' . $key] = $value;
                        }
                    }
                }

                echo json_encode(['success' => true, 'settings' => $flatConfig]);
                return;
            }

            if ($requestMethod === 'POST') {
                if (!$this->validateCsrfToken()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                    return;
                }

                // Save settings to config.php.
                $bodyData = $this->readJsonPayload();
                if (!isset($bodyData['settings']) || !is_array($bodyData)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Settings data required']);
                    return;
                }

                $settings = $bodyData['settings'];
                $protectedKeys = ['admin.password', 'system.root'];

                // Convert flat keys back to sections.
                $configData = [];
                foreach ($settings as $key => $value) {
                    // Skip protected keys.
                    if (in_array($key, $protectedKeys)) {
                        continue;
                    }

                    $parts = explode('.', $key, 2);
                    if (count($parts) === 2) {
                        $section = $parts[0];
                        $settingKey = $parts[1];
                        if (!isset($configData[$section])) {
                            $configData[$section] = [];
                        }
                        $configData[$section][$settingKey] = $value;
                    }
                }

                // Load existing config to preserve protected keys.
                $configPath = $this->appDir . '/config.php';
                $existingConfig = require $configPath;

                // Merge with protected keys.
                foreach ($existingConfig as $section => $values) {
                    if (is_array($values)) {
                        foreach ($values as $key => $value) {
                            $flatKey = $section . '.' . $key;
                            if (in_array($flatKey, $protectedKeys)) {
                                if (!isset($configData[$section])) {
                                    $configData[$section] = [];
                                }
                                $configData[$section][$key] = $value;
                            }
                        }
                    }
                }

                // Write config file using writePhpConfig method.
                $this->writePhpConfig($configPath, $configData);

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

        $checker = new UpdateChecker($this->appDir, $this->root, $this->config);
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

            $checker = new UpdateChecker($this->appDir, $this->root, $this->config);
            $updateInfo = $checker->checkForUpdates();

            echo json_encode([
                'success' => true,
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

            $checker = new UpdateChecker($this->appDir, $this->root, $this->config);

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
     * Handle content export - creates tarball of content/ + config.php.
     */
    private function handleExport(): void
    {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "flint-export-{$timestamp}.tar.gz";
        $tempDir = sys_get_temp_dir() . '/flint-export-' . uniqid();

        try {
            // Create temporary directory
            mkdir($tempDir, 0755, true);

            // Copy content directory
            $contentSource = $this->root . '/content';
            $contentDest = $tempDir . '/content';
            if (is_dir($contentSource)) {
                $this->recursiveCopy($contentSource, $contentDest);
            }

            // Copy config.php
            $configSource = $this->appDir . '/config.php';
            if (file_exists($configSource)) {
                copy($configSource, $tempDir . '/config.php');
            }

            // Create tarball
            $tarball = sys_get_temp_dir() . '/' . $filename;
            $phar = new \PharData($tarball);
            $phar->buildFromDirectory($tempDir);
            $phar->compress(\Phar::GZ);

            // Clean up uncompressed tar
            @unlink($tarball);
            $tarball .= '.gz';

            // Send file
            header('Content-Type: application/gzip');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($tarball));
            readfile($tarball);

            // Cleanup
            @unlink($tarball);
            $this->recursiveRemoveDirectory($tempDir);
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Export failed: ' . $e->getMessage()]);

            // Cleanup on error
            if (is_dir($tempDir)) {
                $this->recursiveRemoveDirectory($tempDir);
            }
        }
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
        $contentSearchDirectories = ['content/pages'];

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
     * Build a hierarchical list of content files under content/pages.
     */
    private function listContentTree(): array
    {
        return $this->buildMarkdownTree(Paths::$pagesDir, '', true);
    }

    /**
     * Build a hierarchical list of block files under content/blocks.
     */
    private function listBlockTree(): array
    {
        return $this->buildMarkdownTree(Paths::$blocksDir, '', false);
    }

    /**
     * Recursively build a markdown file tree.
     *
     * @param string $baseDir Base directory to scan
     * @param string $relativeDir Relative directory inside base
     * @param bool $useContentSlug Whether to map content file slugs
     * @return array
     */
    private function buildMarkdownTree(string $baseDir, string $relativeDir, bool $useContentSlug): array
    {
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
                $children = $this->buildMarkdownTree($baseDir, $childRelative, $useContentSlug);
                if (!empty($children)) {
                    $directories[] = [
                        'type' => 'directory',
                        'name' => $entry,
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
                $label = $this->contentLabelFromRelative($relativeFile, $slug);
                $files[] = [
                    'type' => 'file',
                    'label' => $label,
                    'path' => $slug
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
     * Convert a content/pages relative file path into a URL slug.
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
            return 'index';
        }

        return $baseName;
    }

    /**
     * Render a content file through the configured theme.
     */
    private function render(string $filePath): void
    {
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
        $themeDirectory = $this->root . '/content/themes/' . $themeName;

        if (!is_dir($themeDirectory)) {
            throw new \Exception("Theme '$themeName' not found.");
        }

        // Load theme configuration.
        $themeConfig = $this->loadThemeConfig($themeName);

        // Prepare the data available to the theme.
        $themeData = [
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
            $admin = new Admin($this, $isAdmin, $themeData['currentPath']);
            $finalHtml = $admin->injectAdminUI($finalHtml, $pageStatus);
        }

        // Output final HTML.
        echo $finalHtml;
    }

    /**
     * Render a 404 response using the theme when available.
     */
    private function render404(): void
    {
        // Provide a consistent 404 response.
        http_response_code(404);
        $themeName = $this->config['site']['theme'] ?? 'motion';
        $errorPagePath = $this->root . '/content/themes/' . $themeName . '/404.php';
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

        $configPath = $this->appDir . '/config.php';
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

        $this->renderMagicLinkPage(true, 'You are signed in. Continue to your admin dashboard.');
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
        $rateLimitDir = $this->root . '/content/submissions';
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
        $rateLimitDir = $this->root . '/content/submissions';
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
     * @return array{success:bool, message?:string, error?:string}
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

        $tokenEntry = MagicLink::buildConfigEntry($mode, $pair['hash'], $expiresAt, $issuedAt);
        MagicLink::writeTokenStore($this->appDir, $tokenEntry);

        $magicLink = MagicLink::buildMagicLink($siteWebsite, $pair['token']);
        $blockName = MagicLink::defaultBlockForMode($mode);
        $subject = MagicLink::defaultSubjectForMode($mode, $siteName);

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
            if ($this->shouldExposeMagicLink()) {
                return [
                    'success' => true,
                    'message' => 'Magic link generated for local use.',
                    'magic_link' => $magicLink
                ];
            }

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
     * Handle the public contact form submission.
     */
    private function handleContactForm(): void
    {
        // Provide a JSON response for contact submissions.
        header('Content-Type: application/json');

        // Rate limiting check.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $lastSubmissionTimestamp = $_SESSION['last_contact_submission'] ?? 0;
        $secondsSinceLastSubmission = time() - $lastSubmissionTimestamp;

        if ($secondsSinceLastSubmission < 60) {
            // Throttle repeat submissions to reduce abuse.
            echo json_encode([
                'success' => false,
                'message' => 'Please wait before submitting again.'
            ]);
            return;
        }

        // Get and validate input.
        $payload = $this->readJsonPayload();
        if ($payload === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
            return;
        }

        $senderName = trim((string)($payload['name'] ?? ''));
        $senderEmail = trim((string)($payload['email'] ?? ''));
        $messageBody = trim((string)($payload['message'] ?? ''));

        // Validation.
        $validationErrors = [];

        if ($senderName === '' || strlen($senderName) < 2) {
            $validationErrors[] = 'Name must be at least 2 characters';
        }

        if (strlen($senderName) > 100) {
            $validationErrors[] = 'Name is too long';
        }

        if (!filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            $validationErrors[] = 'Invalid email address';
        }

        if ($messageBody === '' || strlen($messageBody) < 10) {
            $validationErrors[] = 'Message must be at least 10 characters';
        }

        if (strlen($messageBody) > 5000) {
            $validationErrors[] = 'Message is too long';
        }

        // Check for spam patterns.
        if (preg_match('/<a\s+href/i', $messageBody) || preg_match('/\[url=/i', $messageBody)) {
            $validationErrors[] = 'Invalid message content';
        }

        if (!empty($validationErrors)) {
            // Return all validation errors as a single response message.
            echo json_encode([
                'success' => false,
                'message' => implode(', ', $validationErrors)
            ]);
            return;
        }

        // Trigger form_validate hook (components can perform validation).
        $defenseResult = HookManager::trigger('form_validate', [
            'form_type' => 'contact',
            'data' => $payload
        ]);

        // If validation result is returned and indicates failure, handle it.
        if (is_array($defenseResult)) {
            if (isset($defenseResult['should_engage']) && $defenseResult['should_engage']) {
                // A component wants to engage defenses - trigger request_start again.
                HookManager::trigger('request_start', [
                    'path' => $_SERVER['REQUEST_URI'] ?? '/api/contact',
                    'method' => 'POST',
                    'ip' => $this->getClientIp(),
                    'context' => 'form_abuse'
                ]);
            }

            if (isset($defenseResult['valid']) && !$defenseResult['valid']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Submission failed security validation. ' . implode(', ', $defenseResult['errors'] ?? [])
                ]);
                return;
            }
        }

        // Sanitize inputs.
        $safeSenderName = htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8');
        $safeSenderEmail = htmlspecialchars($senderEmail, ENT_QUOTES, 'UTF-8');
        $safeMessageBody = htmlspecialchars($messageBody, ENT_QUOTES, 'UTF-8');

        // Store submission (opt-out via store="false").
        $shouldStore = !isset($payload['store']) || $payload['store'] !== 'false';
        if ($shouldStore) {
            $this->storeSubmission([
                'name' => $safeSenderName,
                'email' => $safeSenderEmail,
                'message' => $safeMessageBody,
                'submitted_at' => time(),
                'submitted_from' => $payload['from'] ?? $_SERVER['HTTP_REFERER'] ?? '',
                'ip_address' => $this->getClientIp(),
            ]);
        }

        // Send email.
        $emailSent = $this->sendContactEmail($safeSenderName, $safeSenderEmail, $safeMessageBody);

        if ($emailSent) {
            $_SESSION['last_contact_submission'] = time();
            echo json_encode(['success' => true]);
            return;
        }

        // Surface a generic failure response when mail dispatch fails.
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send email. Please try again later.'
        ]);
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

        // Validate form token via Defense hooks
        $defenseResult = HookManager::trigger('form_validate', [
            'token' => $formToken,
            'form_type' => $formName
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
        $systemFields = ['form_token', 'form_name', 'success_message', 'redirect_url'];

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
     * Compose and send the contact form email.
     */
    private function sendContactEmail(string $senderName, string $senderEmail, string $messageBody): bool
    {
        // Load email template.
        $templatePath = $this->root . '/content/blocks/contact-email.md';

        if (!file_exists($templatePath)) {
            error_log("Contact email template not found: {$templatePath}");
            return false;
        }

        $templateContents = file_get_contents($templatePath);
        if ($templateContents === false) {
            error_log("Failed to read contact email template: {$templatePath}");
            return false;
        }

        // Parse frontmatter for subject.
        $subjectLine = 'New Contact Form Submission';
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $templateContents, $frontmatterMatch)) {
            if (preg_match('/subject:\s*(.+)$/m', $frontmatterMatch[1], $subjectMatch)) {
                $subjectLine = trim($subjectMatch[1]);
            }
            $templateContents = $frontmatterMatch[2];
        }

        // Replace template variables.
        $messageText = str_replace(
            ['{{name}}', '{{email}}', '{{message}}', '{{date}}'],
            [$senderName, $senderEmail, $messageBody, date('F j, Y g:i A')],
            $templateContents
        );

        // Convert markdown to plain text for email.
        $messageText = strip_tags($messageText);

        // Get admin email from config.
        $adminEmail = $this->config['mail']['admin_email'] ?? '';
        if ($adminEmail === '' || $adminEmail === 'admin@example.com') {
            error_log("Admin email not configured");
            return false;
        }

        $safeReplyEmail = str_replace(["\r", "\n"], '', $senderEmail);
        if ($safeReplyEmail === '') {
            error_log("Invalid reply-to email");
            return false;
        }

        // Email headers.
        $headers = [
            'From: ' . $safeReplyEmail,
            'Reply-To: ' . $safeReplyEmail,
            'X-Mailer: Flint',
            'Content-Type: text/plain; charset=UTF-8'
        ];

        // Send email.
        $success = mail($adminEmail, $subjectLine, $messageText, implode("\r\n", $headers));

        if (!$success) {
            error_log("Failed to send contact form email to {$adminEmail}");
        }

        return $success;
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
        // Store in content/submissions/forms/ (event log structure)
        $formsDir = $this->root . '/content/submissions/forms';

        // Create directory if it doesn't exist.
        if (!is_dir($formsDir)) {
            mkdir($formsDir, 0750, true);
        }

        // Create .htaccess in root submissions directory if it doesn't exist.
        $submissionsDir = $this->root . '/content/submissions';
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
        $formsDir = $this->root . '/content/submissions/forms';

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
        $formsDir = $this->root . '/content/submissions/forms';

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
        $componentsDir = $this->root . '/content/components';
        $components = [];

        if (!is_dir($componentsDir)) {
            return $components;
        }

        $directories = glob($componentsDir . '/*', GLOB_ONLYDIR);

        foreach ($directories as $dir) {
            $name = basename($dir);
            $configPath = $dir . '/config.php';

            if (!file_exists($configPath)) {
                continue;
            }

            $config = require $configPath;
            $enabled = ($config['component']['enabled'] ?? 'false') === 'true';

            $components[] = [
                'name' => $name,
                'displayName' => $config['component']['name'] ?? $name,
                'version' => $config['component']['version'] ?? '1.0.0',
                'author' => $config['component']['author'] ?? 'Unknown',
                'description' => $config['component']['description'] ?? '',
                'enabled' => $enabled,
                'repo' => $config['component']['repo'] ?? ''
            ];
        }

        return $components;
    }

    /**
     * Browse available components from Flint components API.
     *
     * @return array List of available components
     */
    private function browseAvailableComponents(): array
    {
        // Hypothetical Flint components repository API
        $apiUrl = 'https://components.flintcms.com/api/components';

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Flint/' . \Flint\Version::VERSION);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            // Return sample components as fallback
            return $this->getSampleComponents();
        }

        $data = json_decode($response, true);
        return $data['components'] ?? $this->getSampleComponents();
    }

    /**
     * Get sample components for demonstration.
     *
     * @return array Sample components
     */
    private function getSampleComponents(): array
    {
        return [
            [
                'name' => 'Analytics',
                'displayName' => 'Analytics Tracker',
                'version' => '1.0.0',
                'author' => 'Flint',
                'description' => 'Track page views and visitor analytics',
                'repo' => 'flintcms/component-analytics',
                'downloads' => 1234,
                'stars' => 45
            ],
            [
                'name' => 'Search',
                'displayName' => 'Full-Text Search',
                'version' => '1.2.0',
                'author' => 'Flint',
                'description' => 'Add full-text search to your site',
                'repo' => 'flintcms/component-search',
                'downloads' => 2156,
                'stars' => 89
            ],
            [
                'name' => 'Comments',
                'displayName' => 'Comment System',
                'version' => '1.1.0',
                'author' => 'Community',
                'description' => 'Add commenting functionality to pages',
                'repo' => 'flintcms/component-comments',
                'downloads' => 891,
                'stars' => 34
            ]
        ];
    }

    /**
     * Install a component from a GitHub repository.
     *
     * @param string $repo GitHub repository (username/repo format)
     * @return array Result with success status and message
     */
    private function installComponent(string $repo): array
    {
        if (empty($repo)) {
            return ['success' => false, 'error' => 'Repository name required'];
        }

        // Validate repo format
        if (!preg_match('/^[a-zA-Z0-9_-]+\/[a-zA-Z0-9_-]+$/', $repo)) {
            return ['success' => false, 'error' => 'Invalid repository format'];
        }

        $componentsDir = $this->root . '/content/components';
        if (!is_dir($componentsDir)) {
            mkdir($componentsDir, 0755, true);
        }

        // Download latest release as ZIP from GitHub
        $zipUrl = "https://github.com/{$repo}/archive/refs/heads/main.zip";
        $tempZip = sys_get_temp_dir() . '/' . uniqid('component_') . '.zip';

        $ch = curl_init($zipUrl);
        $fp = fopen($tempZip, 'w');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        fclose($fp);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$success || $httpCode !== 200) {
            @unlink($tempZip);
            return ['success' => false, 'error' => 'Failed to download component'];
        }

        // Extract ZIP
        $zip = new \ZipArchive();
        if ($zip->open($tempZip) !== true) {
            @unlink($tempZip);
            return ['success' => false, 'error' => 'Failed to extract component'];
        }

        // Find component name from config.php in the ZIP
        $componentName = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (str_ends_with($filename, '/config.php')) {
                // Extract config.php and evaluate it safely
                $configContent = $zip->getFromIndex($i);
                $tempConfigPath = sys_get_temp_dir() . '/temp-config-' . uniqid() . '.php';
                file_put_contents($tempConfigPath, $configContent);
                $config = require $tempConfigPath;
                @unlink($tempConfigPath);
                $componentName = $config['component']['name'] ?? null;
                break;
            }
        }

        if (!$componentName) {
            $zip->close();
            @unlink($tempZip);
            return ['success' => false, 'error' => 'Invalid component: config file not found'];
        }

        // Extract to components directory
        $extractPath = $componentsDir . '/' . $componentName;

        // SECURITY: Validate extraction path to prevent symlink attacks
        try {
            $validatedExtractPath = $this->validateSecurePath($extractPath, Paths::$siteComponentsDir);
        } catch (\Exception $e) {
            $zip->close();
            @unlink($tempZip);
            return ['success' => false, 'error' => 'Invalid component path'];
        }

        if (is_dir($validatedExtractPath)) {
            $zip->close();
            @unlink($tempZip);
            return ['success' => false, 'error' => 'Component already installed'];
        }

        // Extract all files (strip the first directory level from GitHub archive)
        $zip->extractTo(sys_get_temp_dir());
        $tempDir = sys_get_temp_dir() . '/' . basename($repo) . '-main';

        if (is_dir($tempDir)) {
            rename($tempDir, $validatedExtractPath);
        }

        $zip->close();
        @unlink($tempZip);

        return ['success' => true, 'message' => 'Component installed successfully'];
    }

    /**
     * Update a component from its GitHub repository.
     *
     * @param string $name Component name
     * @return array Result with success status and message
     */
    private function updateComponent(string $name): array
    {
        $componentDir = $this->root . '/content/components/' . $name;
        $configPath = $componentDir . '/config.php';

        if (!is_dir($componentDir) || !file_exists($configPath)) {
            return ['success' => false, 'error' => 'Component not found'];
        }

        $config = require $configPath;
        $repo = $config['component']['repo'] ?? '';

        if (empty($repo)) {
            return ['success' => false, 'error' => 'Repository information not found'];
        }

        // Backup current component
        $backupDir = $componentDir . '_backup_' . time();
        rename($componentDir, $backupDir);

        // Try to install updated version
        $result = $this->installComponent($repo);

        if (!$result['success']) {
            // Restore backup on failure
            if (is_dir($backupDir)) {
                rename($backupDir, $componentDir);
            }
            return $result;
        }

        // Remove backup on success
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
        $componentDir = $this->root . '/content/components/' . $name;
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
        $componentDir = $this->root . '/content/components/' . $name;

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
        $themeConfigPath = $this->root . '/content/themes/' . $themeName . '/config.php';

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
            $this->root . '/content/uploads/.htaccess' => [
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
            $this->root . '/content/pages/.htaccess' => [
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
            $this->root . '/content/blocks/.htaccess' => [
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
            $this->root . '/content/components/.htaccess' => [
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
            $this->root . '/content',
            $this->root . '/content/uploads',
            $this->root . '/content/pages',
            $this->root . '/content/blocks',
            $this->root . '/content/submissions',
            $this->root . '/content/components',
            $this->root . '/content/themes',
            $this->appDir . '/core',
            $this->appDir . '/core/components',
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
     * @return string Path like '/content/uploads/202501'
     */
    public function getUploadDir(?int $timestamp = null): string
    {
        $yearMonth = date('Ym', $timestamp ?? time());
        return $this->root . '/content/uploads/' . $yearMonth;
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
        $logDir = $this->root . '/content/submissions/logs';
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
     * ln -s /etc/passwd content/uploads/passwords.txt
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
     * @param string $allowedBaseDir Base directory path must be within (default: content dir)
     * @return string Validated real path
     * @throws \Exception If path is invalid or outside allowed directory
     */
    private function validateSecurePath(string $filePath, string $allowedBaseDir = null): string
    {
        // Default to content directory if not specified
        if ($allowedBaseDir === null) {
            $allowedBaseDir = Paths::$contentDir;
        }

        // Resolve the real path (follows symlinks)
        $realPath = realpath($filePath);

        // Check if path exists and is accessible
        if ($realPath === false) {
            // Path doesn't exist yet (file creation case)
            // Validate the directory instead
            $dir = dirname($filePath);
            $realDir = realpath($dir);

            if ($realDir === false) {
                throw new \Exception('Invalid directory path');
            }

            $realPath = $realDir . '/' . basename($filePath);
        }

        // Get the real path of the allowed base directory
        $realAllowedBase = realpath($allowedBaseDir);
        if ($realAllowedBase === false) {
            throw new \Exception('Invalid base directory');
        }

        // SECURITY: Verify the real path starts with the allowed base directory
        // This prevents directory traversal and symlink attacks
        if (!str_starts_with($realPath, $realAllowedBase)) {
            error_log("Security: Path traversal attempt blocked - path: {$filePath}, real: {$realPath}, base: {$realAllowedBase}, IP: " . $this->getClientIp());
            throw new \Exception('Invalid file path - security violation');
        }

        return $realPath;
    }
}
