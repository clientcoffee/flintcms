<?php

namespace Flint;

/**
 * Authentication & Session Management
 *
 * This class handles all authentication logic for Flint admin users.
 * It provides secure session management with the following features:
 *
 * - Password verification using PHP's password_hash/password_verify
 * - Sliding session expiration (extends on each pageview)
 * - Session fixation attack prevention (regenerates session ID on login)
 * - CSRF token generation and validation
 * - Timing-safe comparison for token validation
 *
 * SECURITY CONSIDERATIONS:
 * - Passwords must be hashed with password_hash() before storing in config
 * - Session cookies are HttpOnly to prevent XSS theft
 * - Session cookies use SameSite=Lax to prevent CSRF
 * - CSRF tokens use cryptographically secure random bytes
 * - Token comparison uses hash_equals() to prevent timing attacks
 *
 * @package Flint
 * @subpackage Core
 */
class Auth
{
    /**
     * Reference to the main application instance.
     * Used to access configuration settings (admin password, etc.)
     *
     * @var App
     */
    private App $application;

    /**
     * Session lifetime in seconds (5 days = 432000 seconds).
     * This is a "sliding expiration" - the timer resets on each pageview.
     *
     * WHY 5 DAYS?
     * - Long enough that admins don't have to re-login constantly
     * - Short enough to limit exposure if someone leaves a session open
     * - Balanced between security and convenience
     *
     * @var int
     */
    private int $sessionLifetimeSeconds = 432000; // 5 days

    /**
     * Initialize authentication system and start secure session
     *
     * Sets up PHP session with secure cookie parameters:
     * - lifetime: 5 days (matches $sessionLifetimeSeconds)
     * - httponly: true (prevents JavaScript access via document.cookie)
     * - samesite: Lax (prevents CSRF while allowing normal navigation)
     *
     * @param App $app The main application instance for config access
     */
    public function __construct(App $app)
    {
        // Store the application reference so we can access config['admin']['password']
        $this->application = $app;

        // Start the session if one isn't already active.
        // PHP_SESSION_NONE means no session has been started yet.
        if (session_status() === PHP_SESSION_NONE) {
            // SECURITY: Configure secure session cookie parameters before starting session
            session_set_cookie_params([
                // How long the session cookie lasts in the browser
                'lifetime' => $this->sessionLifetimeSeconds,

                // Cookie path (/) means available across entire site
                'path' => '/',

                // SECURITY: httponly=true prevents JavaScript from reading the session cookie
                // This protects against XSS attacks stealing session IDs
                'httponly' => true,

                // SECURITY: secure=true ensures cookie only sent over HTTPS
                // This prevents session hijacking via Man-in-the-Middle attacks
                // Automatically detects if HTTPS is enabled, falls back to false for development
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',

                // SECURITY: samesite=Lax prevents CSRF attacks
                // "Lax" allows cookies on normal navigation but blocks them on cross-site POST
                // (Strict would be more secure but breaks normal website navigation)
                'samesite' => 'Lax'
            ]);

            // Now start the session with our secure parameters
            session_start();
        }
    }

    /**
     * Check if current session is authenticated as admin
     *
     * This method performs THREE security checks:
     * 1. Is the admin_authenticated flag set and true?
     * 2. Is the authenticated_at timestamp present and valid?
     * 3. Has less than 5 days passed since last activity?
     *
     * SLIDING EXPIRATION:
     * If all checks pass, we update the timestamp to "now", which
     * extends the session by another 5 days. This means active admins
     * stay logged in, but inactive sessions expire.
     *
     * @return bool True if authenticated and session is still valid
     */
    public function isAdmin(): bool
    {
        // SECURITY CHECK 1: Ensure the authenticated flag exists and is true
        // This flag is set during successful login (see login() method)
        if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
            return false;
        }

        // SECURITY CHECK 2: Ensure we have a valid authentication timestamp
        // This timestamp records when the user logged in or last accessed a page
        $authenticatedAt = (int)($_SESSION['admin_authenticated_at'] ?? 0);
        if ($authenticatedAt <= 0) {
            // No valid timestamp means the session is corrupted or forged
            $this->logout();
            return false;
        }

        // SECURITY CHECK 3: Ensure the session hasn't expired
        // Calculate how many seconds have passed since last activity
        $secondsSinceLastActivity = time() - $authenticatedAt;

        if ($secondsSinceLastActivity > $this->sessionLifetimeSeconds) {
            // Session has expired (more than 5 days of inactivity)
            $this->logout();
            return false;
        }

        // All checks passed! Now implement sliding expiration:
        // Update the timestamp to "now" so the 5-day timer resets.
        // This means as long as the admin keeps using the site,
        // their session stays alive indefinitely.
        $_SESSION['admin_authenticated_at'] = time();

        return true;
    }

    /**
     * Attempt to authenticate with the provided password
     *
     * SECURITY: This method uses password_verify() which is:
     * - Timing-safe (prevents timing attacks)
     * - Automatically handles salt verification
     * - Compatible with password_hash() output
     *
     * On successful login, we regenerate the session ID to prevent
     * session fixation attacks.
     *
     * SESSION FIXATION ATTACK:
     * An attacker tricks a user into using a known session ID, then
     * waits for the user to login. Without session_regenerate_id(),
     * the attacker would then be logged in as the user.
     *
     * @param string $password The plaintext password to verify
     * @return bool True if password matches and login successful
     */
    public function login(string $password): bool
    {
        // Get the configured admin password hash from config.php
        // This should be a bcrypt hash like: $2y$10$...
        $hashedPasswordFromConfig = $this->application->config['admin']['password'] ?? '';

        // SECURITY: Use password_verify() for timing-safe comparison
        // This function automatically:
        // - Extracts the salt from the hash
        // - Hashes the input password with the same salt
        // - Compares in constant time (prevents timing attacks)
        if (password_verify($password, $hashedPasswordFromConfig)) {
            // Password is correct! Set the session flags
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_authenticated_at'] = time();

            // SECURITY: Regenerate session ID to prevent session fixation
            // The "true" parameter deletes the old session file
            session_regenerate_id(true);

            return true;
        }

        // Password was incorrect
        return false;
    }

    /**
     * Log out the current admin session
     *
     * This method performs complete session cleanup:
     * 1. Unset authentication flags from $_SESSION
     * 2. Destroy the session file on the server
     *
     * NOTE: We don't explicitly delete the cookie because session_destroy()
     * handles that. The browser will discard the cookie when it sees the
     * session is destroyed on the next request.
     */
    public function logout(): void
    {
        // Clear the authentication markers from the session
        unset($_SESSION['admin_authenticated']);
        unset($_SESSION['admin_authenticated_at']);

        // Destroy the entire session (deletes the session file on server)
        session_destroy();
    }

    /**
     * Generate a CSRF token for the current session
     *
     * CSRF (Cross-Site Request Forgery) ATTACK:
     * An attacker tricks a logged-in user into submitting a forged request
     * to perform an action (like changing settings) without their knowledge.
     *
     * CSRF TOKEN DEFENSE:
     * We generate a random token and store it in the session. Forms include
     * this token as a hidden field. When the form is submitted, we verify
     * the token matches the session. Since attackers can't read the session,
     * they can't forge valid requests.
     *
     * TOKEN LIFETIME:
     * The token persists for the entire session. We don't rotate it on
     * every request because that would break multi-tab usage and back button.
     *
     * @return string A 64-character hexadecimal CSRF token
     */
    public function getCsrfToken(): string
    {
        // Generate new token only if one doesn't already exist
        if (!isset($_SESSION['csrf_token'])) {
            // SECURITY: Use random_bytes() for cryptographically secure randomness
            // 32 bytes = 256 bits of entropy (industry standard for token security)
            // bin2hex() converts binary to readable hex string (64 chars)
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Validate a submitted CSRF token against the session token
     *
     * SECURITY: This method uses hash_equals() for timing-safe comparison.
     *
     * TIMING ATTACK:
     * A normal comparison (===) returns false as soon as it finds a
     * mismatched character. An attacker can measure response time to
     * figure out which characters are correct, eventually reconstructing
     * the entire token.
     *
     * TIMING-SAFE COMPARISON:
     * hash_equals() always compares the entire string, taking the same
     * amount of time regardless of where the mismatch occurs. This prevents
     * attackers from using timing measurements to guess the token.
     *
     * @param string $submittedToken The token from the form submission
     * @return bool True if token matches the session token
     */
    public function validateCsrfToken(string $submittedToken): bool
    {
        // Reject immediately if no session token exists
        // (This would indicate the session expired or was never started)
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }

        // SECURITY: Use hash_equals() for constant-time comparison
        // First parameter: expected value (from session)
        // Second parameter: user-provided value (from form)
        return hash_equals($_SESSION['csrf_token'], $submittedToken);
    }
}
