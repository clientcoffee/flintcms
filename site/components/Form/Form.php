<?php

namespace Components\Form;

use Flint\BaseComponent;
use Flint\HookManager;

/**
 * Form Component.
 *
 * Handles form rendering, submission processing, and email delivery.
 * Completely modular - extends core via hooks only.
 */
class Form extends BaseComponent
{
    /**
     * Component initialization.
     */
    protected static function onInit(): void
    {
        // No storage needed - forms are stateless, submissions handled by core storeSubmission.
    }

    /**
     * Register hooks for API endpoints.
     */
    protected static function registerHooks(): void
    {
        self::registerHook('custom_api_endpoints', [self::class, 'handleApiEndpoints']);
    }

    /**
     * Handle form API endpoints.
     */
    public static function handleApiEndpoints(array $context): bool
    {
        $path = $context['path'] ?? '';
        $method = $context['method'] ?? 'GET';

        // Handle generic form submission.
        if ($path === '/api/form' && $method === 'POST') {
            self::handleFormSubmission();
            return true;
        }

        // Handle legacy contact form endpoint.
        if ($path === '/api/contact' && $method === 'POST') {
            self::handleContactForm();
            return true;
        }

        return false;
    }

    /**
     * Handle generic form submission from Form component.
     */
    private static function handleFormSubmission(): void
    {
        header('Content-Type: application/json');

        // Rate limiting (session-based).
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

        // Rate limiting (IP-based).
        if (self::isRateLimited()) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => 'Too many requests. Please try again later.'
            ]);
            return;
        }

        // Get payload.
        $payload = self::readJsonPayload();
        if ($payload === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $formName = $payload['form_name'] ?? 'contact';
        $successMessage = $payload['success_message'] ?? 'Thank you! Your submission has been received.';
        $redirectUrl = $payload['redirect_url'] ?? null;

        // Remove meta fields.
        $formData = $payload;
        unset($formData['form_token'], $formData['form_name'], $formData['success_message'], $formData['redirect_url']);

        // Validate form token via Defense hooks.
        $defenseResult = HookManager::trigger('form_validate', [
            'form_type' => $formName,
            'data' => $payload
        ]);

        if (is_array($defenseResult)) {
            if (isset($defenseResult['should_engage']) && $defenseResult['should_engage']) {
                HookManager::trigger('request_start', [
                    'path' => '/api/form',
                    'method' => 'POST',
                    'ip' => get_client_ip(),
                    'context' => 'form_abuse'
                ]);
            }

            if (isset($defenseResult['valid']) && !$defenseResult['valid']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Submission failed validation. ' . implode(', ', $defenseResult['errors'] ?? [])
                ]);
                return;
            }
        }

        // Send email.
        $emailSent = self::sendFormEmail($formName, $formData);

        if ($emailSent) {
            $_SESSION['last_form_submission'] = time();
            echo json_encode([
                'success' => true,
                'message' => $successMessage,
                'redirect' => $redirectUrl
            ]);
            return;
        }

        echo json_encode([
            'success' => false,
            'message' => 'Failed to send email. Please try again later.'
        ]);
    }

    /**
     * Handle legacy contact form submission.
     */
    private static function handleContactForm(): void
    {
        header('Content-Type: application/json');

        // Rate limiting.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $lastSubmission = $_SESSION['last_contact_submission'] ?? 0;
        if (time() - $lastSubmission < 60) {
            echo json_encode([
                'success' => false,
                'message' => 'Please wait before submitting again.'
            ]);
            return;
        }

        // Rate limiting (IP-based).
        if (self::isRateLimited()) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => 'Too many requests. Please try again later.'
            ]);
            return;
        }

        // Get payload.
        $payload = self::readJsonPayload();
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
        if (preg_match('/<a\s+href/i', $messageBody) || preg_match('/\[url=/i', $messageBody)) {
            $validationErrors[] = 'Invalid message content';
        }

        if (!empty($validationErrors)) {
            echo json_encode([
                'success' => false,
                'message' => implode(', ', $validationErrors)
            ]);
            return;
        }

        // Validate via Defense hooks.
        $defenseResult = HookManager::trigger('form_validate', [
            'form_type' => 'contact',
            'data' => $payload
        ]);

        if (is_array($defenseResult)) {
            if (isset($defenseResult['should_engage']) && $defenseResult['should_engage']) {
                HookManager::trigger('request_start', [
                    'path' => '/api/contact',
                    'method' => 'POST',
                    'ip' => get_client_ip(),
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

        // Sanitize.
        $safeSenderName = htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8');
        $safeSenderEmail = htmlspecialchars($senderEmail, ENT_QUOTES, 'UTF-8');
        $safeMessageBody = htmlspecialchars($messageBody, ENT_QUOTES, 'UTF-8');

        // Store submission (opt-out via store="false").
        $shouldStore = !isset($payload['store']) || $payload['store'] !== 'false';
        if ($shouldStore) {
            self::$app->writeJson(
                self::$app->root . '/site/submissions/forms/form-' . time() . '-' . bin2hex(random_bytes(4)) . '.json',
                [
                    'name' => $safeSenderName,
                    'email' => $safeSenderEmail,
                    'message' => $safeMessageBody,
                    'submitted_at' => time(),
                    'submitted_from' => $payload['from'] ?? $_SERVER['HTTP_REFERER'] ?? '',
                    'ip_address' => get_client_ip(),
                ]
            );
        }

        // Send email.
        $emailSent = self::sendContactEmail($safeSenderName, $safeSenderEmail, $safeMessageBody);

        if ($emailSent) {
            $_SESSION['last_contact_submission'] = time();
            echo json_encode(['success' => true]);
            return;
        }

        echo json_encode([
            'success' => false,
            'message' => 'Failed to send email. Please try again later.'
        ]);
    }

    /**
     * Check IP-based rate limiting (10 requests per 60 seconds).
     *
     * @return bool True if rate limit exceeded, false otherwise.
     */
    private static function isRateLimited(): bool
    {
        $ip = get_client_ip();
        $rateLimitFile = self::$app->root . '/site/submissions/.rate-limit-' . md5($ip) . '.json';
        $now = time();
        $windowSize = 60; // seconds.
        $maxRequests = 10; // max requests per window.

        // Load existing rate limit data.
        $requests = [];
        if (file_exists($rateLimitFile)) {
            $data = file_get_contents($rateLimitFile);
            if ($data !== false) {
                $requests = json_decode($data, true) ?: [];
            }
        }

        // Remove requests outside the time window.
        $requests = array_filter($requests, function ($timestamp) use ($now, $windowSize) {
            return ($now - $timestamp) < $windowSize;
        });

        // Check if limit is exceeded.
        if (count($requests) >= $maxRequests) {
            return true;
        }

        // Add current request timestamp.
        $requests[] = $now;

        // Save updated rate limit data.
        file_put_contents($rateLimitFile, json_encode($requests), LOCK_EX);

        return false;
    }

    /**
     * Send form submission email.
     */
    private static function sendFormEmail(string $formName, array $formData): bool
    {
        $adminEmail = self::$app->config['mail']['admin_email'] ?? '';
        if (!$adminEmail || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid or missing admin email in config");
            return false;
        }

        // Build email body.
        $emailBody = "New form submission from: {$formName}\n\n";
        $emailBody .= "Submitted: " . date('Y-m-d H:i:s') . "\n";
        $emailBody .= "IP Address: " . get_client_ip() . "\n";
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

        // Email headers (sanitize all values to prevent header injection).
        $siteName = sanitize_email_header(self::$app->config['site']['name'] ?? 'Flint');
        $serverName = sanitize_email_header($_SERVER['SERVER_NAME'] ?? 'localhost');
        $safeFormName = sanitize_email_header($formName);

        $subject = "[{$siteName}] New {$safeFormName} submission";
        $headers = "From: {$siteName} <noreply@{$serverName}>\r\n";
        $headers .= "Reply-To: {$adminEmail}\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "X-Mailer: Flint\r\n";

        return mail($adminEmail, $subject, $emailBody, $headers);
    }

    /**
     * Send contact form email.
     */
    private static function sendContactEmail(string $senderName, string $senderEmail, string $messageBody): bool
    {
        // Load email template.
        $templatePath = self::$app->root . '/site/blocks/contact-email.md';

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

        // Get admin email.
        $adminEmail = self::$app->config['mail']['admin_email'] ?? '';
        if ($adminEmail === '' || $adminEmail === 'admin@example.com') {
            error_log("Admin email not configured");
            return false;
        }

        // Sanitize all email header values to prevent header injection.
        $safeReplyEmail = sanitize_email_header($senderEmail);
        if ($safeReplyEmail === '' || !filter_var($safeReplyEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid reply-to email");
            return false;
        }

        // Email headers (all values sanitized).
        $siteName = sanitize_email_header(self::$app->config['site']['name'] ?? 'Flint');
        $serverName = sanitize_email_header($_SERVER['SERVER_NAME'] ?? 'localhost');
        $safeSubject = sanitize_email_header($subjectLine);

        $headers = "From: {$siteName} <noreply@{$serverName}>\r\n";
        $headers .= "Reply-To: {$safeReplyEmail}\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "X-Mailer: Flint\r\n";

        return mail($adminEmail, $safeSubject, $messageText, $headers);
    }

    /**
     * Read JSON payload from request body.
     */
    private static function readJsonPayload(): ?array
    {
        $rawBody = file_get_contents('php://input');
        if ($rawBody === false || $rawBody === '') {
            return null;
        }

        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Render a complete form with fields.
     *
     * All forms POST to /api/form and email the configured admin.
     *
     * Props include.
     * - name: Form name/id (required for identification).
     * - class: Additional CSS classes.
     * - fields: Field definitions (semicolon/newline separated).
     * - submit: Submit button text.
     * - submit_class: Submit button CSS classes.
     * - success: Success message (optional).
     * - redirect: Redirect URL after success (optional).
     *
     * Field format: type:name:label:required:placeholder.
     * Example: text:email:Email Address:true:you@example.com.
     */
    public static function render(array $props, string $content): string
    {
        $name = prop($props, 'name', 'contact');
        $class = prop($props, 'class', 'space-y-4');
        $fieldsRaw = content_or_prop($content, $props, 'fields');
        $submitText = prop($props, 'submit', 'Submit');
        $submitClass = prop($props, 'submit_class', 'w-full bg-gray-900 text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-800 transition');
        $successMessage = prop($props, 'success', 'Thank you! Your submission has been received.');
        $redirectUrl = prop($props, 'redirect');

        // Parse fields.
        $fields = self::parseFields($fieldsRaw);

        // Generate form token via hook (Defense component).
        $formToken = \Flint\HookManager::trigger('form_token_generate', ['form_type' => $name]);
        if ($formToken === null || $formToken === '') {
            $formToken = base64_encode(json_encode([
                'created' => time(),
                'nonce' => bin2hex(random_bytes(8))
            ]));
        }

        // Build form.
        $formAttrs = [
            'id' => $name,
            'name' => $name,
            'action' => '/api/form',
            'method' => 'POST',
            'class' => $class
        ];

        ob_start();
        ?>
        <form <?= html_attrs($formAttrs) ?>>
            <!-- Hidden fields. -->
            <input type="hidden" name="form_token" value="<?= esc_html($formToken) ?>" />
            <input type="hidden" name="form_name" value="<?= esc_html($name) ?>" />
            <input type="hidden" name="success_message" value="<?= esc_html($successMessage) ?>" />
            <?php if ($redirectUrl) : ?>
                <input type="hidden" name="redirect_url" value="<?= esc_html($redirectUrl) ?>" />
            <?php endif; ?>

            <!-- Render fields. -->
            <?php foreach ($fields as $field) : ?>
                <?= self::renderField($field) ?>
            <?php endforeach; ?>

            <!-- Submit button. -->
            <div>
                <button type="submit" class="<?= esc_html($submitClass) ?>">
                    <?= esc_html($submitText) ?>
                </button>
            </div>
        </form>
        <?php

        return trim((string)ob_get_clean());
    }

    /**
     * Parse field definitions from text.
     */
    private static function parseFields(string $fieldsRaw): array
    {
        if (trim($fieldsRaw) === '') {
            return [];
        }

        $lines = self::parseList($fieldsRaw);
        $fields = [];

        foreach ($lines as $line) {
            $parts = explode(':', $line);
            if (count($parts) < 2) {
                continue;
            }

            $fields[] = [
                'type' => trim($parts[0]),
                'name' => trim($parts[1]),
                'label' => trim($parts[2] ?? ''),
                'required' => isset($parts[3]) && strtolower(trim($parts[3])) === 'true',
                'placeholder' => trim($parts[4] ?? ''),
                'rows' => trim($parts[5] ?? '6'),
                'options' => trim($parts[6] ?? '')
            ];
        }

        return $fields;
    }

    /**
     * Render a single form field.
     */
    private static function renderField(array $field): string
    {
        $type = $field['type'];
        $name = $field['name'];
        $label = $field['label'];
        $required = $field['required'];
        $placeholder = $field['placeholder'];
        $rows = $field['rows'];

        if ($name === '') {
            return '<!-- Form: field name required. -->';
        }

        $fieldClass = 'w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500';
        $labelAttrs = [
            'class' => 'block text-sm font-medium mb-2',
            'for' => $name
        ];

        ob_start();
        ?>
        <div>
            <?php if ($label !== '') : ?>
                <label <?= html_attrs($labelAttrs) ?>>
                    <?= esc_html($label) ?>
                    <?php if ($required) : ?>
                        <span class="text-red-500 ml-1">*</span>
                    <?php endif; ?>
                </label>
            <?php endif; ?>

            <?php if ($type === 'textarea') : ?>
                <?php
                $attrs = [
                    'id' => $name,
                    'name' => $name,
                    'required' => $required,
                    'rows' => $rows,
                    'placeholder' => $placeholder ?: null,
                    'class' => $fieldClass
                ];
                ?>
                <textarea <?= html_attrs($attrs) ?>></textarea>
            <?php elseif ($type === 'select') : ?>
                <?php
                $attrs = [
                    'id' => $name,
                    'name' => $name,
                    'required' => $required,
                    'class' => $fieldClass
                ];
                $options = explode(',', $field['options']);
                ?>
                <select <?= html_attrs($attrs) ?>>
                    <?php foreach ($options as $option) : ?>
                        <?php $option = trim($option); ?>
                        <?php if ($option !== '') : ?>
                            <option value="<?= esc_html($option) ?>"><?= esc_html($option) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($type === 'checkbox') : ?>
                <?php
                $attrs = [
                    'type' => 'checkbox',
                    'id' => $name,
                    'name' => $name,
                    'required' => $required,
                    'class' => 'h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500'
                ];
                ?>
                <div class="flex items-center">
                    <input <?= html_attrs($attrs) ?> />
                    <?php if ($placeholder) : ?>
                        <label for="<?= esc_html($name) ?>" class="ml-2 text-sm text-gray-600">
                            <?= esc_html($placeholder) ?>
                        </label>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <?php
                $attrs = [
                    'type' => $type,
                    'id' => $name,
                    'name' => $name,
                    'required' => $required,
                    'placeholder' => $placeholder ?: null,
                    'class' => $fieldClass
                ];
                ?>
                <input <?= html_attrs($attrs) ?> />
            <?php endif; ?>
        </div>
        <?php

        return trim((string)ob_get_clean());
    }

    /**
     * Split multiline field definitions into rows.
     */
    protected static function parseList(string $text): array
    {
        $lines = preg_split('/\r?\n|;/', $text);
        $result = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '') {
                $result[] = $trimmed;
            }
        }
        return $result;
    }
}
