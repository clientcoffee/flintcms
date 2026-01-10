<?php

namespace Flint;

class Setup
{
    /**
     * Render the setup screen or handle a submitted form.
     */
    public static function render(): void
    {
        $templatePath = __DIR__ . '/../views/setup.php';
        if (!file_exists($templatePath)) {
            throw new \RuntimeException('Setup template missing.');
        }

        $formData = [
            'site_name' => 'Flint',
            'site_website' => self::resolveDefaultWebsite(),
            'theme' => 'motion',
            'admin_email' => '',
        ];
        $setupResult = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $setupResult = self::handlePost($formData);
            if (!empty($setupResult['form'])) {
                $formData = $setupResult['form'];
            }
        }

        require $templatePath;
    }

    /**
     * Handle setup form submission and persist site/config.php.
     */
    private static function handlePost(array $formData): array
    {
        // Resolve the configuration path once for reuse.
        $appDir = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
        $rootDir = realpath($appDir . '/..') ?: dirname($appDir);
        $configPath = $rootDir . '/site/config.php';
        $legacyConfigPaths = [
            $rootDir . '/config.php',
            $appDir . '/config.php',
        ];

        // Refuse to overwrite an existing install.
        $legacyConfigExists = false;
        foreach ($legacyConfigPaths as $legacyConfigPath) {
            if (file_exists($legacyConfigPath)) {
                $legacyConfigExists = true;
                break;
            }
        }

        if (file_exists($configPath) || $legacyConfigExists) {
            return [
                'success' => false,
                'message' => 'Config already exists. Setup will not overwrite an existing install.',
                'form' => $formData,
            ];
        }

        $formData['site_name'] = trim((string)($_POST['site_name'] ?? $formData['site_name']));
        $formData['site_name'] = $formData['site_name'] !== '' ? $formData['site_name'] : 'Flint';
        $formData['site_website'] = self::normalizeWebsite((string)($_POST['site_website'] ?? $formData['site_website']));
        $formData['theme'] = trim((string)($_POST['theme'] ?? $formData['theme'])) ?: 'motion';
        $formData['admin_email'] = trim((string)($_POST['admin_email'] ?? $formData['admin_email']));

        if ($formData['admin_email'] === '' || !filter_var($formData['admin_email'], FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Please provide a valid admin email address.',
                'form' => $formData,
            ];
        }

        // Create content directories non-destructively.
        $directories = [
            $rootDir . '/site',
            $rootDir . '/site/pages',
            $rootDir . '/site/blocks',
            $rootDir . '/site/uploads',
            $rootDir . '/site/themes',
            $rootDir . '/site/components',
            $rootDir . '/site/submissions',
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        self::seedDefaultContent($rootDir);

        $adminPassword = MagicLink::generatePassword(64);
        $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);

        $magicPair = MagicLink::generateTokenPair();
        $magicExpiresAt = time() + MagicLink::defaultTtlSeconds(MagicLink::MODE_SETUP);

        $environment = self::shouldExposeMagicLink() ? 'development' : 'production';
        $configData = [
            'site' => [
                'name' => $formData['site_name'],
                'theme' => $formData['theme'],
                'website' => $formData['site_website'],
            ],
            'mail' => [
                'admin_email' => $formData['admin_email'],
                'smtp_host' => 'localhost',
            ],
            'admin' => [
                'password' => $hashedPassword,
            ],
            'system' => [
                'cache_enabled' => false,
                'environment' => $environment,
                'show_errors' => false,
            ],
            'updates' => [
                'auto_update' => 'ask',
            ],
        ];

        if (!self::writeConfig($configPath, $configData)) {
            return [
                'success' => false,
                'message' => 'Error writing config file. Please check permissions.',
                'form' => $formData,
            ];
        }

        // Remove any legacy config files inside app/ or app/core/ to avoid confusion.
        foreach (array_merge($legacyConfigPaths, [$appDir . '/core/config.php']) as $legacyConfig) {
            if (file_exists($legacyConfig)) {
                @unlink($legacyConfig);
            }
        }

        $tokenEntry = MagicLink::buildConfigEntry(
            MagicLink::MODE_SETUP,
            $magicPair['hash'],
            $magicExpiresAt,
            time()
        );
        MagicLink::writeTokenStore($appDir, $tokenEntry);

        $magicLink = MagicLink::buildMagicLink($formData['site_website'], $magicPair['token']);
        $exposeMagicLink = self::shouldExposeMagicLink($environment);
        $emailSent = false;

        if (!$exposeMagicLink) {
            $emailSent = MagicLink::sendEmail(
                $appDir,
                $rootDir,
                $formData['admin_email'],
                $formData['site_name'],
                $formData['site_website'],
                $magicLink,
                MagicLink::defaultBlockForMode(MagicLink::MODE_SETUP),
                MagicLink::defaultSubjectForMode(MagicLink::MODE_SETUP, $formData['site_name'])
            );
        }

        if ($exposeMagicLink) {
            $message = 'Setup complete! Use this magic link to finish sign-in: [Open magic link](' . $magicLink . ')';
        } else {
            $message = $emailSent
                ? 'Setup complete! Check your email for a magic link to finish sign-in.'
                : 'Setup complete, but the magic link email could not be sent.';
        }

        return [
            'success' => true,
            'message' => $message,
            'magic_link' => ($emailSent || $exposeMagicLink) ? null : $magicLink,
            'form' => $formData,
        ];
    }

    /**
     * Determine if magic links can be exposed during setup for local development.
     */
    private static function shouldExposeMagicLink(?string $environment = null): bool
    {
        $environment = strtolower(trim((string)$environment));
        if ($environment !== '') {
            return in_array($environment, ['dev', 'development', 'local'], true);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (in_array($ip, ['127.0.0.1', '::1'], true)) {
            return true;
        }

        $hostHeader = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
        $host = parse_url('http://' . $hostHeader, PHP_URL_HOST) ?: $hostHeader;
        if ($host === 'localhost') {
            return true;
        }

        return str_ends_with($host, '.local') || str_ends_with($host, '.test');
    }

    /**
     * Resolve the default website value from server state.
     */
    private static function resolveDefaultWebsite(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $host = trim((string)$host);
        if ($host === '') {
            $host = 'localhost';
        }

        return $scheme . '://' . $host;
    }

    /**
     * Normalize website input to include a scheme.
     */
    private static function normalizeWebsite(string $website): string
    {
        $website = trim($website);
        if ($website === '') {
            $website = self::resolveDefaultWebsite();
        }

        if (!preg_match('#^https?://#i', $website)) {
            $defaultScheme = parse_url(self::resolveDefaultWebsite(), PHP_URL_SCHEME) ?: 'https';
            $website = $defaultScheme . '://' . ltrim($website, '/');
        }

        return rtrim($website, '/');
    }

    /**
     * Write the site/config.php file with safe permissions.
     */
    private static function writeConfig(string $path, array $config): bool
    {
        $php = "<?php\n/**\n * Flint Configuration\n *\n * This file contains sensitive configuration. Keep secure permissions (0600).\n * DO NOT commit this file to version control.\n */\n\nreturn ";
        $php .= var_export($config, true);
        $php .= ";\n";

        if (file_put_contents($path, $php, LOCK_EX) === false) {
            return false;
        }

        chmod($path, 0600);
        return true;
    }

    /**
     * Seed default pages and blocks from the core content library.
     */
    private static function seedDefaultContent(string $rootDir): void
    {
        $coreContentDir = $rootDir . '/app/core/content';
        $pagesSource = $coreContentDir . '/pages';
        $pagesDestination = $rootDir . '/site/pages';

        if (!self::hasMarkdownContent($pagesDestination)) {
            self::copyMissingFiles($pagesSource, $pagesDestination);
        }

        self::copyMissingFiles($coreContentDir . '/blocks', $rootDir . '/site/blocks');
        self::copyMissingFiles($coreContentDir . '/uploads', $rootDir . '/site/uploads');
    }

    /**
     * Copy files from source to destination, skipping anything that already exists.
     */
    private static function copyMissingFiles(string $source, string $destination): void
    {
        $source = rtrim($source, DIRECTORY_SEPARATOR);
        $destination = rtrim($destination, DIRECTORY_SEPARATOR);

        if (!is_dir($source)) {
            return;
        }

        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($source) + 1);
            $targetPath = $destination . DIRECTORY_SEPARATOR . $relativePath;

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
                continue;
            }

            if (!file_exists($targetPath)) {
                copy($item->getPathname(), $targetPath);
            }
        }
    }

    /**
     * Check for existing markdown content before seeding.
     */
    private static function hasMarkdownContent(string $directory): bool
    {
        if (!is_dir($directory)) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            if (preg_match('/\.(md|mdx)$/i', $item->getFilename())) {
                return true;
            }
        }

        return false;
    }
}
