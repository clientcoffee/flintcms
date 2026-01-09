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
     * Handle setup form submission and persist config.php.
     */
    private static function handlePost(array $formData): array
    {
        // Resolve the configuration path once for reuse.
        $appDir = __DIR__ . '/..';
        $rootDir = dirname($appDir);
        $configPath = $appDir . '/config.php';

        // Refuse to overwrite an existing install.
        if (file_exists($configPath)) {
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
            $rootDir . '/content',
            $rootDir . '/content/pages',
            $rootDir . '/content/blocks',
            $rootDir . '/content/uploads',
            $rootDir . '/content/themes',
            $rootDir . '/content/components',
            $rootDir . '/content/submissions',
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        // Create welcome page if it doesn't exist.
        $welcomePage = $rootDir . '/content/pages/index.md';
        if (!file_exists($welcomePage)) {
            $welcomeContent = <<<'MD'
---
title: Welcome to Flint
description: Your new flat-file CMS is ready to go!
icon: home
order: 1
---

# 👋 Hello from Flint!

Welcome to your new content management system. Everything is set up and ready for you to start creating.

## What's Flint?

A beautifully simple, flat-file CMS that just works:

- **Zero Dependencies** - Drop it on any PHP 8.2+ server
- **No Database** - All content lives in simple markdown files
- **Built-in Editor** - Edit content right in your browser
- **Lightning Fast** - No database queries, just files
- **Theme System** - Beautiful, responsive themes included

## Get Started in 3 Steps

1. **Login** - Click the login link in the footer to access the admin panel
2. **Edit** - Modify this page or create new content
3. **Customize** - Change your theme and site settings

## What's Next?

- Explore the admin panel to customize your site
- Create new pages by adding markdown files
- Customize your theme and settings

**Ready to build something amazing?** Your content awaits! ✨
MD;
            file_put_contents($welcomePage, $welcomeContent);
        }

        $adminPassword = MagicLink::generatePassword(64);
        $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);

        $magicPair = MagicLink::generateTokenPair();
        $magicExpiresAt = time() + MagicLink::defaultTtlSeconds(MagicLink::MODE_SETUP);

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

        $tokenEntry = MagicLink::buildConfigEntry(
            MagicLink::MODE_SETUP,
            $magicPair['hash'],
            $magicExpiresAt,
            time()
        );
        MagicLink::writeTokenStore($appDir, $tokenEntry);

        $magicLink = MagicLink::buildMagicLink($formData['site_website'], $magicPair['token']);
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

        $message = $emailSent
            ? 'Setup complete! Check your email for a magic link to finish sign-in.'
            : 'Setup complete, but the magic link email could not be sent.';

        return [
            'success' => true,
            'message' => $message,
            'magic_link' => $emailSent ? null : $magicLink,
            'form' => $formData,
        ];
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
     * Write the config.php file with safe permissions.
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
}
