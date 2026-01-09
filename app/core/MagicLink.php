<?php

namespace Flint;

class MagicLink
{
    public const MODE_SETUP = 'setup';
    public const MODE_LOGIN = 'login';
    public const MODE_RESET = 'reset';

    /**
     * Generate a random token and its hash for storage.
     *
     * @return array{token:string, hash:string}
     */
    public static function generateTokenPair(): array
    {
        $token = bin2hex(random_bytes(32));
        return [
            'token' => $token,
            'hash' => hash('sha256', $token),
        ];
    }

    /**
     * Build a magic link URL for the site.
     */
    public static function buildMagicLink(string $siteWebsite, string $token): string
    {
        return rtrim($siteWebsite, '/') . '/magic?token=' . $token;
    }

    /**
     * Build the config payload for a magic link.
     */
    public static function buildConfigEntry(
        string $mode,
        string $tokenHash,
        int $expiresAt,
        ?int $issuedAt = null
    ): array {
        return [
            'mode' => $mode,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'issued_at' => $issuedAt ?? time(),
        ];
    }

    /**
     * Generate a random password using a restricted character set.
     */
    public static function generatePassword(int $length = 64): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_';
        $maxIndex = strlen($alphabet) - 1;
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $maxIndex)];
        }

        return $password;
    }

    /**
     * Default email block for the given mode.
     */
    public static function defaultBlockForMode(string $mode): string
    {
        return match ($mode) {
            self::MODE_RESET => 'magic-link-reset',
            self::MODE_LOGIN => 'magic-link-login',
            default => 'magic-link-setup',
        };
    }

    /**
     * Default subject line for the given mode.
     */
    public static function defaultSubjectForMode(string $mode, string $siteName): string
    {
        return match ($mode) {
            self::MODE_RESET => $siteName . ' password reset',
            self::MODE_LOGIN => $siteName . ' sign-in link',
            default => $siteName . ' setup link',
        };
    }

    /**
     * Default TTL for magic links by mode.
     */
    public static function defaultTtlSeconds(string $mode): int
    {
        return 30 * 60;
    }

    /**
     * Resolve the magic link token storage path.
     */
    public static function getTokenStorePath(string $appDir): string
    {
        $tokenDir = $appDir . '/storage/.tokens';
        if (!is_dir($tokenDir)) {
            mkdir($tokenDir, 0700, true);
        }

        self::hardenTokenDir($tokenDir);

        return $tokenDir . '/magic-link.json';
    }

    /**
     * Read token store contents if present.
     */
    public static function readTokenStore(string $appDir): ?array
    {
        $path = self::getTokenStorePath($appDir);
        if (!file_exists($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $decoded = json_decode($contents, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Persist token store contents.
     */
    public static function writeTokenStore(string $appDir, array $data): void
    {
        $path = self::getTokenStorePath($appDir);
        $json = json_encode($data, JSON_PRETTY_PRINT);
        if ($json === false) {
            return;
        }

        file_put_contents($path, $json, LOCK_EX);
        @chmod($path, 0600);
    }

    /**
     * Remove the stored magic link token.
     */
    public static function clearTokenStore(string $appDir): void
    {
        $path = self::getTokenStorePath($appDir);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * Render and send the magic link email.
     */
    public static function sendEmail(
        string $appDir,
        string $rootDir,
        string $adminEmail,
        string $siteName,
        string $siteWebsite,
        string $magicLink,
        string $blockName,
        string $subject
    ): bool {
        $blockPath = $rootDir . '/content/blocks/' . $blockName . '.md';
        $markdown = file_exists($blockPath)
            ? file_get_contents($blockPath)
            : "# Finish setting up Flint\n\nClick the link below to finish:\n\n{{magic_link}}\n";

        $markdown = str_replace('{{magic_link}}', $magicLink, $markdown);
        $contentHtml = self::renderMarkdown($appDir, $rootDir, $markdown);

        $templatePath = $appDir . '/views/emails/magic-link.php';
        if (!file_exists($templatePath)) {
            return false;
        }

        ob_start();
        require $templatePath;
        $emailHtml = ob_get_clean();

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';

        $domain = parse_url($siteWebsite, PHP_URL_HOST) ?: ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $domain = preg_replace('/[^A-Za-z0-9.-]/', '', (string)$domain);
        if ($domain === '') {
            $domain = 'localhost';
        }

        $fromName = self::sanitizeEmailHeader($siteName);
        $fromEmail = 'noreply@' . $domain;
        $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';

        return mail($adminEmail, $subject, $emailHtml, implode("\r\n", $headers));
    }

    /**
     * Render markdown to HTML using the core parser.
     */
    private static function renderMarkdown(string $appDir, string $rootDir, string $markdown): string
    {
        try {
            $app = new App($appDir, $rootDir);
            $parser = new Parser($app);
            $parsed = $parser->parse($markdown);
            return $parsed['content_html'] ?? '';
        } catch (\Throwable $e) {
            return nl2br(htmlspecialchars($markdown, ENT_QUOTES, 'UTF-8'));
        }
    }

    /**
     * Sanitize header values for email.
     */
    private static function sanitizeEmailHeader(string $value): string
    {
        return trim(str_replace(["\r", "\n"], '', $value));
    }

    /**
     * Add simple defenses to the token directory.
     */
    private static function hardenTokenDir(string $tokenDir): void
    {
        $htaccessPath = $tokenDir . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            file_put_contents($htaccessPath, "Deny from all\n", LOCK_EX);
            @chmod($htaccessPath, 0640);
        }

        $indexPath = $tokenDir . '/index.php';
        if (!file_exists($indexPath)) {
            $indexContents = "<?php\nhttp_response_code(403);\n";
            file_put_contents($indexPath, $indexContents, LOCK_EX);
            @chmod($indexPath, 0640);
        }
    }
}
