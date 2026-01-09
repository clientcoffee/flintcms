<?php

namespace Components\Defense;

use Flint\BaseComponent;

/**
 * Defense Component - Asymmetric Active Defense System
 *
 * Makes attackers regret their life choices through tarpitting,
 * resource exhaustion, and proof-of-work challenges.
 *
 * This is a drop-in component that hooks into the application lifecycle
 * without tight coupling to core application code.
 */
class Defense extends BaseComponent
{
    private static string $clientIp = '';
    private static string $storageDir = '';

    // Defense levels from config
    private const MODE_PASSIVE = 'passive';
    private const MODE_ACTIVE = 'active';

    /**
     * Component-specific initialization
     */
    protected static function onInit(): void
    {
        self::$clientIp = self::getClientIp();
        // Store defense events in content/submissions/defense (event log)
        self::$storageDir = self::$app->root . '/content/submissions/defense';
        self::ensureStorageDir(self::$storageDir);

        // Ensure component config exists (self-setup)
        self::ensureConfig();
    }

    /**
     * Ensure component configuration file exists with defaults
     */
    private static function ensureConfig(): void
    {
        $configPath = self::$app->root . '/content/components/Defense/config.php';

        // If config already exists, nothing to do
        if (file_exists($configPath)) {
            return;
        }

        // Create default config as PHP array
        $defaultConfig = <<<'PHP'
<?php
/**
 * Defense Component Configuration
 *
 * Asymmetric active defense system with tarpitting, proof-of-work challenges,
 * and novel form defenses.
 */

return [
    'component' => [
        'name' => 'Defense',
        'version' => '1.0.0',
        'author' => 'Flint',
        'description' => 'Asymmetric active defense system with tarpitting, proof-of-work challenges, and novel form defenses',
        'repo' => 'flintcms/component-defense',
        'enabled' => false,
        'priority' => 10,
    ],
    'defense' => [
        // Defense mode: "passive" = tarpit + large fake responses, "active" = adds proof of work
        'mode' => 'passive',

        // Cryptocurrency wallet address for proof-of-work earnings (Monero address format)
        // When mode=active, attackers must solve crypto puzzles that earn this wallet
        'wallet' => '4AdUndXHHZ6cfufTMvppY6JwXNouMBzSkbLYfpAV5Usx3skxNgYeYTRj5UzqtReoS44qo9mtmXCqY45DJ852K5Jv2684Rge',

        // Suspicious behavior thresholds
        'rapid_request_threshold' => 10,
        'scanner_404_threshold' => 5,
        'failed_login_threshold' => 3,
    ],
];
PHP;

        file_put_contents($configPath, $defaultConfig, LOCK_EX);
    }

    /**
     * Register component hooks
     */
    protected static function registerHooks(): void
    {
        self::registerHook('request_start', [self::class, 'onRequestStart']);
        self::registerHook('form_token_generate', [self::class, 'onFormTokenGenerate']);
        self::registerHook('form_validate', [self::class, 'onFormValidate']);
    }

    /**
     * Hook callback: Request start
     */
    public static function onRequestStart(array $context): mixed
    {
        $path = $context['path'] ?? '';
        $method = $context['method'] ?? 'GET';

        if (self::shouldEngage($path, $method)) {
            self::engage($path, 'suspicious_activity');
        }

        return null; // Continue normal execution
    }

    /**
     * Hook callback: Generate form token
     */
    public static function onFormTokenGenerate(array $context): string
    {
        $data = [
            'created' => time(),
            'ip' => self::$clientIp,
            'nonce' => bin2hex(random_bytes(8))
        ];

        return base64_encode(json_encode($data));
    }

    /**
     * Hook callback: Validate form submission
     */
    public static function onFormValidate(array $context): array
    {
        $formData = $context['data'] ?? [];
        $formType = $context['form_type'] ?? 'contact';

        return self::validateFormSubmission($formData, $formType);
    }

    /**
     * Check if defense should engage for this request.
     */
    private static function shouldEngage(string $requestPath, string $requestMethod): bool
    {
        // Check for suspicious patterns
        if (self::isHoneypotPath($requestPath)) {
            return true;
        }

        if (self::isScanningPattern($requestPath)) {
            return true;
        }

        if (self::isRapidFire()) {
            return true;
        }

        if (self::hasExcessiveFailures()) {
            return true;
        }

        return false;
    }

    /**
     * Execute defensive measures against the attacker.
     */
    private static function engage(string $requestPath, string $context = 'scan'): void
    {
        $mode = self::getConfig('defense.mode', self::MODE_PASSIVE);

        // Log the engagement
        self::logEngagement($requestPath, $context, $mode);

        // Increment offense counter
        self::recordOffense($context);

        // Get offense level for this IP
        $offenseLevel = self::getOffenseLevel();

        // Apply tarpit delays (exponential backoff)
        self::tarpit($offenseLevel);

        // Randomly serve fake error messages during sustained attacks
        // This confuses attackers by making them think they found vulnerabilities
        if ($offenseLevel >= 3 && rand(1, 100) <= 30) {
            self::serveFakeError($requestPath, $offenseLevel);
            return; // Exit after serving fake error
        }

        if ($mode === self::MODE_ACTIVE) {
            // Active mode: Demand proof of work
            self::demandProofOfWork($offenseLevel);
        } else {
            // Passive mode: Send large fake response
            self::serveFakeResponse($requestPath, $offenseLevel);
        }
    }

    /**
     * Tarpit: Delay response exponentially based on offense level.
     */
    private static function tarpit(int $offenseLevel): void
    {
        // Exponential delay: 1s, 2s, 4s, 8s, 16s, 32s, 60s (max)
        $delay = min(pow(2, $offenseLevel - 1), 60);

        // Add randomness so they can't pattern-detect
        $jitter = rand(0, $delay * 500) / 1000; // up to 50% jitter

        $totalDelay = $delay + $jitter;

        // Sleep in small increments to avoid locking PHP process
        $slept = 0;
        while ($slept < $totalDelay) {
            $chunk = min(0.5, $totalDelay - $slept); // 500ms chunks
            usleep((int)($chunk * 1000000));
            $slept += $chunk;

            // Check if connection is still alive
            if (connection_aborted()) {
                return;
            }
        }
    }

    /**
     * Active Defense: Demand proof of work (crypto mining).
     */
    private static function demandProofOfWork(int $offenseLevel): void
    {
        // Difficulty increases with offense level
        $difficulty = 4 + $offenseLevel; // Leading zeros required

        // Generate challenge
        $challenge = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(16));

        // Wallet address from config (Monero address format)
        $walletAddress = self::getConfig(
            'defense.wallet',
            '4AdUndXHHZ6cfufTMvppY6JwXNouMBzSkbLYfpAV5Usx3skxNgYeYTRj5UzqtReoS44qo9mtmXCqY45DJ852K5Jv2684Rge'
        );

        http_response_code(429); // Too Many Requests
        header('Content-Type: application/json');
        header('Retry-After: 60');

        echo json_encode([
            'error' => 'Proof of work required',
            'challenge' => $challenge,
            'nonce' => $nonce,
            'difficulty' => $difficulty,
            'algorithm' => 'SHA-256',
            'instructions' => 'Find a solution where SHA-256(challenge + nonce + solution) has ' . $difficulty . ' leading zero bits',
            'submit_to' => '/api/defense/verify',
            'wallet' => $walletAddress,
            'message' => 'Your CPU cycles are now working for us. How does it feel? 😈'
        ]);

        exit;
    }

    /**
     * Passive Defense: Serve large, convincing fake response.
     */
    private static function serveFakeResponse(string $requestPath, int $offenseLevel): void
    {
        // Choose response type based on path
        if (strpos($requestPath, 'admin') !== false || strpos($requestPath, 'wp-') !== false) {
            self::serveFakeAdminPanel($offenseLevel);
        } elseif (strpos($requestPath, 'api') !== false) {
            self::serveFakeAPI($offenseLevel);
        } else {
            self::serveGarbageHTML($offenseLevel);
        }
    }

    /**
     * Serve a convincing fake admin panel that wastes time.
     */
    private static function serveFakeAdminPanel(int $offenseLevel): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');

        // Start output buffering for chunked transfer
        if (!ob_get_level()) {
            ob_start();
        }

        echo '<!DOCTYPE html><html><head><title>Admin Login</title>';
        echo '<style>body{font-family:Arial;margin:40px;background:#f5f5f5}';
        echo '.login{max-width:400px;margin:0 auto;background:white;padding:30px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1)}';
        echo 'input{width:100%;padding:10px;margin:10px 0;border:1px solid #ddd;border-radius:4px}';
        echo 'button{width:100%;padding:12px;background:#007bff;color:white;border:none;border-radius:4px;cursor:pointer}';
        echo '</style></head><body><div class="login">';
        echo '<h2>Administrator Login</h2>';
        echo '<form method="POST"><input name="user" placeholder="Username" required>';
        echo '<input type="password" name="pass" placeholder="Password" required>';

        // Flush and delay
        ob_flush();
        flush();
        sleep(2);

        echo '<button type="submit">Login</button></form>';

        // Add fake vulnerability hints in comments to waste security researcher time
        echo '<!-- TODO: Fix SQL injection in user parameter -->';
        echo '<!-- FIXME: XSS vulnerability in admin dashboard -->';
        echo '<!-- NOTE: Session token stored in cookie "admin_session" -->';
        echo '<!-- DEBUG: Admin password hash: ' . hash('sha256', 'fake_password_' . time()) . ' -->';

        ob_flush();
        flush();
        sleep(1);

        // Generate massive amount of fake JavaScript
        echo '<script>';
        echo '// Anti-CSRF token validation';
        echo 'const csrfToken = "' . bin2hex(random_bytes(32)) . '";';

        // Generate kilobytes of fake validation code
        for ($i = 0; $i < 50; $i++) {
            echo "\n// Validation function " . $i . "\n";
            echo 'function validate' . $i . '(input) { ';
            echo 'return input.length > 0 && input.length < 100 && /^[a-zA-Z0-9]+$/.test(input); }';

            if ($i % 5 == 0) {
                ob_flush();
                flush();
                usleep(500000); // 500ms delay
            }
        }

        echo '</script>';

        // Fake analytics tracking that goes nowhere
        echo '<script async src="/analytics.js?id=' . bin2hex(random_bytes(8)) . '"></script>';
        echo '</div></body></html>';

        ob_end_flush();
        exit;
    }

    /**
     * Serve fake API response with large payload.
     */
    private static function serveFakeAPI(int $offenseLevel): void
    {
        http_response_code(200);
        header('Content-Type: application/json');

        // Generate massive fake data payload
        $fakeData = [
            'success' => true,
            'message' => 'Data retrieved successfully',
            'timestamp' => time(),
            'records' => []
        ];

        // Generate thousands of fake records
        $recordCount = 1000 * $offenseLevel;
        for ($i = 0; $i < $recordCount; $i++) {
            $fakeData['records'][] = [
                'id' => $i,
                'uuid' => bin2hex(random_bytes(16)),
                'name' => 'User_' . $i,
                'email' => 'user' . $i . '@example.com',
                'created' => date('Y-m-d H:i:s', time() - rand(0, 31536000)),
                'data' => str_repeat('x', 100), // Padding
                'token' => bin2hex(random_bytes(32)),
            ];

            // Drip feed the response
            if ($i % 100 == 0 && $i > 0) {
                echo json_encode(['batch' => $i, 'data' => array_slice($fakeData['records'], $i - 100, 100)]) . "\n";
                ob_flush();
                flush();
                usleep(100000); // 100ms delay
            }
        }

        echo json_encode($fakeData);
        exit;
    }

    /**
     * Serve garbage HTML that looks legitimate but wastes bandwidth.
     */
    private static function serveGarbageHTML(int $offenseLevel): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');

        echo '<!DOCTYPE html><html><head><title>Loading...</title></head><body>';

        // Generate HTML comments with fake vulnerability information
        $vulnComments = [
            '<!-- Vulnerable to: SQL Injection in /api/users?id= -->',
            '<!-- TODO: Patch XSS in search parameter -->',
            '<!-- SECURITY: Remove debug endpoint /internal/debug -->',
            '<!-- WARNING: Backup file at /backup/database.sql -->',
            '<!-- FIXME: Hardcoded API key: sk_live_' . bin2hex(random_bytes(16)) . ' -->',
            '<!-- NOTE: Admin panel accessible at /secret/admin -->',
        ];

        foreach ($vulnComments as $comment) {
            echo $comment . "\n";
            ob_flush();
            flush();
            usleep(200000);
        }

        // Generate massive amounts of fake content
        $paragraphs = 500 * $offenseLevel;
        for ($i = 0; $i < $paragraphs; $i++) {
            $words = rand(50, 200);
            echo '<p>';
            for ($j = 0; $j < $words; $j++) {
                echo self::randomWord() . ' ';
            }
            echo '</p>' . "\n";

            if ($i % 10 == 0) {
                ob_flush();
                flush();
                usleep(50000); // 50ms delay
            }
        }

        echo '</body></html>';
        exit;
    }

    /**
     * Serve context-aware fake error messages to confuse attackers.
     *
     * Analyzes the request path to determine what the attacker is probing for,
     * then serves a semi-dynamic, realistic-looking error message that suggests
     * they've found a vulnerability (when they haven't).
     */
    private static function serveFakeError(string $requestPath, int $offenseLevel): void
    {
        // Detect what technology the attacker is probing for
        $errorType = self::detectProbeType($requestPath);

        // Generate semi-dynamic error details
        $timestamp = date('Y-m-d H:i:s');
        $pid = rand(1000, 9999);
        $tid = rand(100, 999);
        $lineNumber = rand(10, 500);
        $sessionId = bin2hex(random_bytes(16));
        $requestId = strtoupper(bin2hex(random_bytes(8)));

        // Set appropriate content type and status code
        http_response_code(500);

        switch ($errorType) {
            case 'sql':
                header('Content-Type: text/html; charset=utf-8');
                echo self::generateSqlError($requestPath, $lineNumber, $timestamp);
                break;

            case 'php':
                header('Content-Type: text/html; charset=utf-8');
                echo self::generatePhpError($requestPath, $lineNumber, $timestamp);
                break;

            case 'asp':
                header('Content-Type: text/html; charset=utf-8');
                echo self::generateAspError($requestPath, $lineNumber, $timestamp);
                break;

            case 'apache':
                header('Content-Type: text/html; charset=utf-8');
                echo self::generateApacheError($requestPath, $pid, $tid, $timestamp);
                break;

            case 'nginx':
                header('Content-Type: text/html; charset=utf-8');
                echo self::generateNginxError($requestPath, $pid, $timestamp);
                break;

            case 'ftp':
                header('Content-Type: text/plain');
                echo self::generateFtpError($requestPath);
                break;

            case 'mysql':
                header('Content-Type: text/html; charset=utf-8');
                echo self::generateMysqlError($requestPath, $lineNumber);
                break;

            case 'windows':
                header('Content-Type: text/html; charset=utf-8');
                echo self::generateWindowsError($requestPath, $timestamp);
                break;

            default:
                header('Content-Type: text/html; charset=utf-8');
                echo self::generateGenericError($requestPath, $timestamp);
        }

        exit;
    }

    /**
     * Detect what type of technology the attacker is probing for.
     */
    private static function detectProbeType(string $path): string
    {
        $patterns = [
            'sql' => ['/union.*select/i', '/\' or /i', '/\' and /i', '/concat\(/i'],
            'php' => ['/\.php/i', '/phpinfo/i', '/eval\(/i', '/base64_decode/i'],
            'asp' => ['/\.asp/i', '/\.aspx/i', '/cmd\.exe/i'],
            'mysql' => ['/mysql/i', '/phpmyadmin/i', '/database/i'],
            'apache' => ['/\.htaccess/i', '/apache/i', '/httpd/i'],
            'nginx' => ['/nginx/i', '/conf\.d/i'],
            'ftp' => ['/ftp/i', '/upload/i', '/21/'],
            'windows' => ['/\\\\/i', '/c:/i', '/system32/i', '/cmd/i'],
        ];

        foreach ($patterns as $type => $regexes) {
            foreach ($regexes as $regex) {
                if (preg_match($regex, $path)) {
                    return $type;
                }
            }
        }

        return 'generic';
    }

    /**
     * Generate fake SQL error message.
     */
    private static function generateSqlError(string $path, int $line, string $timestamp): string
    {
        $tables = ['users', 'sessions', 'admin', 'config', 'posts', 'orders', 'customers'];
        $table = $tables[array_rand($tables)];
        $column = ['id', 'username', 'password', 'email', 'token'][array_rand(['id', 'username', 'password', 'email', 'token'])];

        return <<<HTML
<!DOCTYPE html>
<html><head><title>SQL Error</title></head>
<body style="font-family:monospace;background:#f5f5f5;padding:20px">
<h1 style="color:#c00">SQL Error</h1>
<p><strong>Error:</strong> You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near '$path' at line $line</p>
<p><strong>Query:</strong> SELECT * FROM $table WHERE $column = '{$path}'</p>
<p><strong>Error Code:</strong> 1064</p>
<p><strong>Timestamp:</strong> $timestamp</p>
<pre style="background:#fff;padding:10px;border:1px solid #ccc">
Stack trace:
#0 /var/www/html/includes/database.php($line): mysqli->query('SELECT * FROM $table...')
#1 /var/www/html/includes/auth.php(42): Database->executeQuery('SELECT * FROM $table...')
#2 /var/www/html/index.php(15): Auth->validateUser()
#3 {main}
</pre>
</body></html>
HTML;
    }

    /**
     * Generate fake PHP error message.
     */
    private static function generatePhpError(string $path, int $line, string $timestamp): string
    {
        $file = '/var/www/html/' . basename($path);
        return <<<HTML
<!DOCTYPE html>
<html><head><title>PHP Error</title></head>
<body style="font-family:monospace;background:#f5f5f5;padding:20px">
<h1 style="color:#c00">PHP Fatal error</h1>
<p><strong>Fatal error:</strong> Uncaught Error: Call to undefined function mysql_connect() in $file:$line</p>
<pre style="background:#fff;padding:10px;border:1px solid #ccc">
Stack trace:
#0 $file($line): include()
#1 /var/www/html/includes/config.php(28): require_once('$file')
#2 {main}
  thrown in <b>$file</b> on line <b>$line</b>
</pre>
<p><strong>PHP Version:</strong> 7.4.33</p>
<p><strong>Timestamp:</strong> $timestamp</p>
<hr>
<small>This error has been logged to /var/log/php/error.log</small>
</body></html>
HTML;
    }

    /**
     * Generate fake ASP.NET error message.
     */
    private static function generateAspError(string $path, int $line, string $timestamp): string
    {
        return <<<HTML
<!DOCTYPE html>
<html><head><title>Server Error</title>
<style>body{font-family:Verdana;background:#fff;margin:20px}h1{color:#c00;font-size:18px}</style>
</head><body>
<h1>Server Error in '/' Application.</h1>
<hr>
<h2>Runtime Error</h2>
<p><strong>Description:</strong> An exception occurred while processing your request. Additionally, another exception occurred while executing the custom error page for the first exception.</p>
<p><strong>Exception Details:</strong> System.Data.SqlClient.SqlException: Invalid column name 'password'</p>
<p><strong>Source Error:</strong></p>
<pre style="background:#ffffcc;padding:10px;border:1px solid #cc9">
Line $line:  SqlCommand cmd = new SqlCommand("SELECT * FROM Users WHERE username='" + username + "'");
</pre>
<p><strong>Source File:</strong> C:\\inetpub\\wwwroot\\Default.aspx.cs    <strong>Line:</strong> $line</p>
<hr>
<p><strong>Version Information:</strong> Microsoft .NET Framework Version:4.0.30319; ASP.NET Version:4.8.4075.0</p>
</body></html>
HTML;
    }

    /**
     * Generate fake Apache error message.
     */
    private static function generateApacheError(string $path, int $pid, int $tid, string $timestamp): string
    {
        return <<<HTML
<!DOCTYPE html>
<html><head><title>500 Internal Server Error</title></head>
<body style="font-family:monospace;background:#f5f5f5;padding:20px">
<h1>Internal Server Error</h1>
<p>The server encountered an internal error or misconfiguration and was unable to complete your request.</p>
<p><strong>Error:</strong> [core:error] [pid $pid:tid $tid] AH00124: Request exceeded the limit of 10 internal redirects due to probable configuration error. Use 'LimitInternalRecursion' to increase the limit if necessary. Use 'LogLevel debug' to get a backtrace.</p>
<p><strong>Path:</strong> $path</p>
<hr>
<p><small>Apache/2.4.52 (Ubuntu) Server at {$_SERVER['HTTP_HOST']} Port 443</small></p>
<p><small>Error log: /var/log/apache2/error.log</small></p>
</body></html>
HTML;
    }

    /**
     * Generate fake Nginx error message.
     */
    private static function generateNginxError(string $path, int $pid, string $timestamp): string
    {
        return <<<HTML
<!DOCTYPE html>
<html><head><title>502 Bad Gateway</title></head>
<body style="font-family:monospace;background:#f5f5f5;padding:20px">
<h1>502 Bad Gateway</h1>
<p>nginx/$pid</p>
<hr>
<pre style="background:#fff;padding:10px;border:1px solid #ccc">
$timestamp [error] $pid#0: *1 connect() failed (111: Connection refused) while connecting to upstream, client: {$_SERVER['REMOTE_ADDR']}, server: {$_SERVER['HTTP_HOST']}, request: "GET $path HTTP/1.1", upstream: "fastcgi://127.0.0.1:9000", host: "{$_SERVER['HTTP_HOST']}"
$timestamp [error] $pid#0: *1 upstream timed out (110: Connection timed out) while reading response header from upstream
</pre>
<hr>
<p><small>nginx/1.18.0 (Ubuntu)</small></p>
</body></html>
HTML;
    }

    /**
     * Generate fake FTP error message.
     */
    private static function generateFtpError(string $path): string
    {
        return <<<TXT
220 ProFTPD Server (Flint FTP) [::ffff:127.0.0.1]
USER anonymous
331 Anonymous login ok, send your complete email address as your password
PASS
530 Login incorrect.
USER admin
331 Password required for admin
PASS
530 Login incorrect.
421 Too many failed login attempts
Connection closed by remote host.
TXT;
    }

    /**
     * Generate fake MySQL error message.
     */
    private static function generateMysqlError(string $path, int $line): string
    {
        return <<<HTML
<!DOCTYPE html>
<html><head><title>MySQL Error</title></head>
<body style="font-family:monospace;background:#f5f5f5;padding:20px">
<h1 style="color:#c00">MySQL Error</h1>
<p><strong>Error:</strong> Access denied for user 'webapp'@'localhost' (using password: YES)</p>
<p><strong>Error Code:</strong> 1045</p>
<p><strong>Connection String:</strong> mysql://webapp:***@localhost:3306/production_db</p>
<pre style="background:#fff;padding:10px;border:1px solid #ccc">
Failed Query: SELECT * FROM admin_users WHERE username='admin' AND password=MD5('$path')
Error at line: $line
</pre>
<p><strong>Attempted Connection:</strong> Host: localhost, Port: 3306, Database: production_db</p>
</body></html>
HTML;
    }

    /**
     * Generate fake Windows error message.
     */
    private static function generateWindowsError(string $path, string $timestamp): string
    {
        return <<<HTML
<!DOCTYPE html>
<html><head><title>Server Error</title></head>
<body style="font-family:'Segoe UI',Arial;background:#fff;padding:20px">
<h1 style="color:#c00">Server Error</h1>
<hr style="border:none;border-top:1px solid #ccc">
<p><strong>HTTP Error 500.0 - Internal Server Error</strong></p>
<p>The page cannot be displayed because an internal server error has occurred.</p>
<h3>Most likely causes:</h3>
<ul>
<li>IIS received the request; however, an internal error occurred during the processing of the request.</li>
<li>The request was not processed successfully because of an error in the configuration.</li>
</ul>
<h3>Detailed Error Information:</h3>
<table style="border-collapse:collapse;margin:10px 0">
<tr><td style="padding:5px;border:1px solid #ccc"><strong>Module:</strong></td><td style="padding:5px;border:1px solid #ccc">FastCgiModule</td></tr>
<tr><td style="padding:5px;border:1px solid #ccc"><strong>Notification:</strong></td><td style="padding:5px;border:1px solid #ccc">ExecuteRequestHandler</td></tr>
<tr><td style="padding:5px;border:1px solid #ccc"><strong>Handler:</strong></td><td style="padding:5px;border:1px solid #ccc">PHP-FastCGI</td></tr>
<tr><td style="padding:5px;border:1px solid #ccc"><strong>Error Code:</strong></td><td style="padding:5px;border:1px solid #ccc">0x80070002</td></tr>
<tr><td style="padding:5px;border:1px solid #ccc"><strong>Physical Path:</strong></td><td style="padding:5px;border:1px solid #ccc">C:\\inetpub\\wwwroot$path</td></tr>
<tr><td style="padding:5px;border:1px solid #ccc"><strong>Logon User:</strong></td><td style="padding:5px;border:1px solid #ccc">Anonymous</td></tr>
</table>
<hr style="border:none;border-top:1px solid #ccc">
<p><small>Microsoft-IIS/10.0 | $timestamp</small></p>
</body></html>
HTML;
    }

    /**
     * Generate generic fake error message.
     */
    private static function generateGenericError(string $path, string $timestamp): string
    {
        return <<<HTML
<!DOCTYPE html>
<html><head><title>500 Internal Server Error</title></head>
<body style="font-family:monospace;background:#f5f5f5;padding:20px">
<h1 style="color:#c00">500 Internal Server Error</h1>
<p>The server encountered an unexpected condition that prevented it from fulfilling the request.</p>
<p><strong>Request URI:</strong> $path</p>
<p><strong>Timestamp:</strong> $timestamp</p>
<pre style="background:#fff;padding:10px;border:1px solid #ccc">
Error: Segmentation fault (core dumped)
Signal: SIGSEGV (Address not mapped to object)
Process: www-data [12345]
Path: /usr/sbin/web-server
</pre>
<hr>
<p><small>If you are the system administrator please check the server logs for more information.</small></p>
</body></html>
HTML;
    }

    /**
     * Novel form defenses: Honeypot, timing, entropy analysis.
     */
    private static function validateFormSubmission(array $formData, string $formType = 'contact'): array
    {
        $errors = [];
        $suspicionScore = 0;

        // 1. Honeypot field check (should be empty)
        if (!empty($formData['website']) || !empty($formData['url']) || !empty($formData['company'])) {
            $suspicionScore += 10;
            self::recordOffense('honeypot_filled');
        }

        // 2. Timing check (form should take at least 3 seconds to fill)
        if (isset($formData['form_token'])) {
            $tokenData = self::decodeFormToken($formData['form_token']);
            if ($tokenData) {
                $timeSpent = time() - $tokenData['created'];
                if ($timeSpent < 3) {
                    $suspicionScore += 5;
                    $errors[] = 'Form submitted too quickly';
                }
                if ($timeSpent > 3600) {
                    $suspicionScore += 3;
                    $errors[] = 'Form token expired';
                }
            }
        }

        // 3. Entropy analysis (detect copy-pasted spam)
        if (isset($formData['message'])) {
            $entropy = self::calculateEntropy($formData['message']);
            if ($entropy < 3.5) { // Very low entropy = repetitive/spam
                $suspicionScore += 5;
            }
        }

        // 4. Field order validation (randomized per session)
        if (isset($formData['field_order'])) {
            $expectedOrder = self::getExpectedFieldOrder();
            if ($formData['field_order'] !== $expectedOrder) {
                $suspicionScore += 3;
            }
        }

        // 5. Mouse movement check (JavaScript should track this)
        if (!isset($formData['mouse_entropy']) || $formData['mouse_entropy'] < 0.1) {
            $suspicionScore += 4;
        }

        // 6. Duplicate detection (same message recently)
        if (self::isDuplicateSubmission($formData)) {
            $suspicionScore += 8;
            $errors[] = 'Duplicate submission detected';
        }

        // 7. Known spam patterns
        $spamPatterns = [
            '/\b(viagra|cialis|casino|lottery|prize|winner)\b/i',
            '/\b(click here|buy now|limited time|act now)\b/i',
            '/http.*http.*http/i', // Multiple URLs
            '/<a\s+href/i', // HTML links
            '/\[url=/i', // BBCode links
        ];

        foreach ($spamPatterns as $pattern) {
            if (isset($formData['message']) && preg_match($pattern, $formData['message'])) {
                $suspicionScore += 3;
            }
        }

        return [
            'valid' => $suspicionScore < 10,
            'suspicion_score' => $suspicionScore,
            'errors' => $errors,
            'should_engage' => $suspicionScore >= 10
        ];
    }

    /**
     * Decode form token.
     */
    private static function decodeFormToken(string $token): ?array
    {
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return null;
        }

        return json_decode($decoded, true);
    }

    /**
     * Calculate Shannon entropy of text (detect spam patterns).
     */
    private static function calculateEntropy(string $text): float
    {
        $text = strtolower($text);
        $len = strlen($text);
        if ($len == 0) {
            return 0;
        }

        $frequencies = array_count_values(str_split($text));
        $entropy = 0;

        foreach ($frequencies as $count) {
            $probability = $count / $len;
            $entropy -= $probability * log($probability, 2);
        }

        return $entropy;
    }

    /**
     * Check if this is a duplicate submission.
     */
    private static function isDuplicateSubmission(array $formData): bool
    {
        if (!isset($formData['message'])) {
            return false;
        }

        $hash = hash('sha256', $formData['message']);
        $cacheFile = self::$storageDir . '/submissions.json';

        $recent = [];
        if (file_exists($cacheFile)) {
            $recent = json_decode(file_get_contents($cacheFile), true) ?? [];
        }

        // Check if hash exists in last hour
        $cutoff = time() - 3600;
        $recent = array_filter($recent, fn($item) => $item['time'] > $cutoff);

        if (isset($recent[$hash])) {
            return true;
        }

        // Store this hash
        $recent[$hash] = ['time' => time()];
        file_put_contents($cacheFile, json_encode($recent), LOCK_EX);

        return false;
    }

    /**
     * Generate expected field order (stored in session).
     */
    private static function getExpectedFieldOrder(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['field_order'])) {
            $_SESSION['field_order'] = bin2hex(random_bytes(8));
        }

        return $_SESSION['field_order'];
    }

    /**
     * Check if path matches honeypot patterns.
     */
    private static function isHoneypotPath(string $path): bool
    {
        $honeypots = [
            '/wp-admin', '/wp-login', '/wp-content',
            '/admin', '/administrator', '/admin.php',
            '/phpmyadmin', '/pma', '/mysql',
            '/.env', '/.git', '/config.php',
            '/backup', '/db', '/database',
            '/xmlrpc.php', '/wp-cron.php'
        ];

        foreach ($honeypots as $trap) {
            if (str_starts_with($path, $trap)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect scanning patterns (SQL injection, XSS attempts, etc).
     */
    private static function isScanningPattern(string $path): bool
    {
        $patterns = [
            '/[\x00-\x1F]/', // Control characters
            '/\.\.[\/\\\\]/', // Path traversal
            '/(union|select|insert|update|delete|drop)/i', // SQL keywords
            '/<script|javascript:|onerror=/i', // XSS attempts
            '/\${|<%|<\?php/i', // Template injection
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for rapid-fire requests.
     */
    private static function isRapidFire(): bool
    {
        $requestFile = self::$storageDir . '/requests.json';
        $requests = [];

        if (file_exists($requestFile)) {
            $requests = json_decode(file_get_contents($requestFile), true) ?? [];
        }

        $now = time();
        $key = self::$clientIp;

        // Clean old entries
        if (isset($requests[$key])) {
            $requests[$key] = array_filter($requests[$key], fn($t) => $t > $now - 60);
        } else {
            $requests[$key] = [];
        }

        // Add current request
        $requests[$key][] = $now;

        // Save
        file_put_contents($requestFile, json_encode($requests), LOCK_EX);

        $threshold = self::getConfig('defense.rapid_request_threshold', 10);
        return count($requests[$key]) > $threshold;
    }

    /**
     * Check for excessive failures (login, 404s, etc).
     */
    private static function hasExcessiveFailures(): bool
    {
        $offenseLevel = self::getOffenseLevel();
        return $offenseLevel >= 5;
    }

    /**
     * Record an offense for this IP.
     */
    private static function recordOffense(string $type): void
    {
        $offenseFile = self::$storageDir . '/offenses.json';
        $offenses = [];

        if (file_exists($offenseFile)) {
            $offenses = json_decode(file_get_contents($offenseFile), true) ?? [];
        }

        $key = self::$clientIp;
        if (!isset($offenses[$key])) {
            $offenses[$key] = ['count' => 0, 'types' => [], 'first' => time()];
        }

        $offenses[$key]['count']++;
        $offenses[$key]['types'][$type] = ($offenses[$key]['types'][$type] ?? 0) + 1;
        $offenses[$key]['last'] = time();

        // Clean old offenses (older than 24 hours)
        $cutoff = time() - 86400;
        foreach ($offenses as $ip => $data) {
            if ($data['last'] < $cutoff) {
                unset($offenses[$ip]);
            }
        }

        file_put_contents($offenseFile, json_encode($offenses), LOCK_EX);
    }

    /**
     * Get offense level for this IP (1-10).
     */
    private static function getOffenseLevel(): int
    {
        $offenseFile = self::$storageDir . '/offenses.json';

        if (!file_exists($offenseFile)) {
            return 1;
        }

        $offenses = json_decode(file_get_contents($offenseFile), true) ?? [];
        $key = self::$clientIp;

        if (!isset($offenses[$key])) {
            return 1;
        }

        $count = $offenses[$key]['count'];
        return min(10, ceil($count / 3));
    }

    /**
     * Log defense engagement.
     */
    private static function logEngagement(string $path, string $context, string $mode): void
    {
        $logFile = self::$storageDir . '/engagements.log';
        $entry = sprintf(
            "[%s] IP: %s | Path: %s | Context: %s | Mode: %s | Level: %d\n",
            date('Y-m-d H:i:s'),
            self::$clientIp,
            $path,
            $context,
            $mode,
            self::getOffenseLevel()
        );

        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Generate random word for garbage content.
     */
    private static function randomWord(): string
    {
        $consonants = 'bcdfghjklmnprstvwxyz';
        $vowels = 'aeiou';
        $length = rand(3, 10);
        $word = '';

        for ($i = 0; $i < $length; $i++) {
            $word .= ($i % 2 == 0) ? $consonants[rand(0, strlen($consonants) - 1)] : $vowels[rand(0, strlen($vowels) - 1)];
        }

        return $word;
    }
}
