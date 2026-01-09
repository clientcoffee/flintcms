<?php

declare(strict_types=1);

/*
 * Flint - The Drop-in CMS
 * Entry Point
 */

// Global security headers to harden common attack vectors.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-site');
header('X-Permitted-Cross-Domain-Policies: none');
header("Content-Security-Policy-Report-Only: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; script-src 'self' 'unsafe-inline'; connect-src 'self'; frame-src https://www.youtube.com https://player.vimeo.com https://fast.wistia.net https://fast.wistia.com https://www.tiktok.com https://www.instagram.com https://www.facebook.com https://player.twitch.tv https://twitframe.com;");

// 1. Zero-Config Autoloader
spl_autoload_register(function (string $class) {
    $appDir = __DIR__;
    $rootDir = dirname(__DIR__);

    $map = [
        'Flint\\' => $appDir . '/core/',
        'Components\\' => $appDir . '/core/components/',  // System components
        'Modules\\' => $rootDir . '/content/themes/',  // User themes
    ];

    foreach ($map as $prefix => $baseDir) {
        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) {
                require $file;
                return;
            }

            // Support component directories like core/components/Block/Block.php
            if ($prefix === 'Components\\') {
                $relativePath = str_replace('\\', '/', $relative);
                $basename = basename($relativePath);
                $nestedFile = $baseDir . $relativePath . '/' . $basename . '.php';
                if (file_exists($nestedFile)) {
                    require $nestedFile;
                    return;
                }
            }
        }
    }
});

// 2. Boot
try {
    $appDir = __DIR__;  // App directory contains config
    $rootDir = dirname(__DIR__);  // Parent directory contains content

    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (str_starts_with($requestPath, '/assets/')) {
        $assetPath = $appDir . $requestPath;
        $assetDir = $appDir . '/assets';
        $realAssetPath = realpath($assetPath);
        $realAssetDir = realpath($assetDir);

        if ($realAssetPath !== false && $realAssetDir !== false && str_starts_with($realAssetPath, $realAssetDir) && is_file($realAssetPath)) {
            $extension = strtolower(pathinfo($realAssetPath, PATHINFO_EXTENSION));
            $mimeTypes = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'mjs' => 'application/javascript',
                'map' => 'application/json',
            ];
            $contentType = $mimeTypes[$extension] ?? 'application/octet-stream';
            header('Content-Type: ' . $contentType);
            readfile($realAssetPath);
            exit;
        }
    }

    // Check for installation config.php
    if (!file_exists($appDir . '/config.php')) {
        \Flint\Setup::render();
        exit;
    }

    $app = new \Flint\App($appDir, $rootDir);
    $app->run();
} catch (\Throwable $e) {
    http_response_code(500);

    // Determine if we should show detailed errors based on config
    $showDetailedErrors = false;
    $configPath = $appDir . '/config.php';

    if (file_exists($configPath)) {
        try {
            $config = require $configPath;
            $showErrorsValue = $config['system']['show_errors'] ?? false;
            if (is_bool($showErrorsValue)) {
                $showDetailedErrors = $showErrorsValue;
            } elseif (is_string($showErrorsValue) || is_int($showErrorsValue)) {
                $showDetailedErrors = filter_var($showErrorsValue, FILTER_VALIDATE_BOOLEAN);
            } else {
                $showDetailedErrors = false;
            }
        } catch (\Throwable $configError) {
            // If we can't load config, assume production mode (safer)
            $showDetailedErrors = false;
        }
    }

    // Always log the full error details regardless of display mode
    $errorDetails = sprintf(
        "[%s] %s: %s in %s:%d\nStack trace:\n%s",
        date('Y-m-d H:i:s'),
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    error_log($errorDetails);

    // Extract the function/method name from the stack trace
    $trace = $e->getTrace();
    $functionName = 'N/A';
    if (!empty($trace[0])) {
        $firstFrame = $trace[0];
        if (isset($firstFrame['class']) && isset($firstFrame['function'])) {
            $functionName = $firstFrame['class'] . $firstFrame['type'] . $firstFrame['function'] . '()';
        } elseif (isset($firstFrame['function'])) {
            $functionName = $firstFrame['function'] . '()';
        }
    }

    // Get relative file path for cleaner display
    $relativeFile = str_replace($rootDir . '/', '', $e->getFile());

    $traceEntries = [];
    $traceLines = explode("\n", $e->getTraceAsString());
    foreach ($traceLines as $traceLine) {
        if (empty(trim($traceLine))) {
            continue;
        }

        if (preg_match('/^(#\d+)\s+(.+?)(\(\d+\)):\s+(.+)$/', $traceLine, $matches)) {
            $traceEntries[] = [
                'type' => 'parsed',
                'number' => $matches[1],
                'file' => str_replace($rootDir . '/', '', $matches[2]),
                'line' => $matches[3],
                'function' => $matches[4],
            ];
        } else {
            $traceEntries[] = [
                'type' => 'raw',
                'line' => $traceLine,
            ];
        }
    }

    $errorClass = get_class($e);
    $errorMessage = $e->getMessage();
    $errorLine = $e->getLine();
    $errorReference = substr(md5($e->getMessage() . $e->getFile() . $e->getLine()), 0, 8);

    $templatePath = $appDir . '/views/error.php';
    if (file_exists($templatePath)) {
        require $templatePath;
    } else {
        echo $showDetailedErrors ? 'Application error' : 'An unexpected error occurred.';
    }
}
