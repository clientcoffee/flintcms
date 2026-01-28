<?php

namespace Components;

use Flint\RenderComponent;
use Flint\HookManager;

/**
 * Render a contact form with spam defense and AJAX submit handling.
 */
class ContactForm extends RenderComponent
{
    public static function render(array $props, string $content): string
    {
        // Normalize form configuration inputs.
        $formName = trim((string) prop($props, 'name', 'contact'));
        $formId = trim((string) prop($props, 'id', 'contact-form'));
        $fieldsBlock = trim((string) prop($props, 'fields', 'contact-form'));
        $formClass = prop($props, 'class', 'max-w-2xl mx-auto space-y-6');
        $wrapperClass = prop($props, 'wrapper_class', 'my-8');
        $submitText = prop($props, 'submit', 'Send Message');
        $submitClass = prop($props, 'submit_class', 'inline-flex items-center gap-2 bg-indigo-600 text-white px-6 py-3 rounded-md hover:bg-indigo-700 transition-colors font-medium');
        $successMessage = prop($props, 'success', 'Thank you! Your message has been sent successfully.');
        $redirectUrl = prop($props, 'redirect', '');

        // Prefer inline fields, fallback to a reusable block.
        $fieldsHtml = trim($content) !== '' ? $content : self::loadBlockFields($fieldsBlock);
        if ($fieldsHtml === '') {
            return '<!-- ContactForm component: no fields found. -->';
        }

        // Ask plugins to generate a form token, fallback to a local token.
        $formToken = HookManager::trigger('form_token_generate', ['form_type' => $formName]);
        if ($formToken === null || $formToken === '') {
            $formToken = base64_encode(json_encode([
                'created' => time(),
                'nonce' => bin2hex(random_bytes(8))
            ]));
        }

        // Precompute runtime IDs used in the markup and JS.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (!isset($_SESSION['field_order'])) {
            $_SESSION['field_order'] = bin2hex(random_bytes(8));
        }
        $fieldOrder = $_SESSION['field_order'];
        $messageId = $formId . '-message';

        ob_start();
        ?>
        <div class="<?= esc_html($wrapperClass) ?>">
            <div id="<?= esc_html($messageId) ?>" class="hidden mb-4 rounded-2xl border px-4 py-3 text-sm"></div>
            <form id="<?= esc_html($formId) ?>" class="<?= esc_html($formClass) ?>" action="/api/form" method="POST">
                <!-- Honeypot fields. -->
                <input type="text" name="website" style="position:absolute;left:-9999px;width:1px;height:1px" tabindex="-1" autocomplete="off">
                <input type="text" name="url" style="position:absolute;left:-9999px;width:1px;height:1px" tabindex="-1" autocomplete="off">
                <input type="text" name="company" style="position:absolute;left:-9999px;width:1px;height:1px" tabindex="-1" autocomplete="off">

                <!-- Defense tokens. -->
                <input type="hidden" name="form_token" value="<?= esc_html($formToken) ?>">
                <input type="hidden" name="form_name" value="<?= esc_html($formName) ?>">
                <input type="hidden" name="success_message" value="<?= esc_html($successMessage) ?>">
                <?php if ($redirectUrl !== '') : ?>
                    <input type="hidden" name="redirect_url" value="<?= esc_html($redirectUrl) ?>">
                <?php endif; ?>
                <input type="hidden" name="field_order" value="<?= esc_html($fieldOrder) ?>">
                <input type="hidden" name="mouse_entropy" value="0">
                <input type="hidden" name="page_title" value="">
                <input type="hidden" name="page_url" value="">

                <?= $fieldsHtml ?>

                <button type="submit" class="<?= esc_html($submitClass) ?>">
                    <span><?= esc_html($submitText) ?></span>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z"></path>
                    </svg>
                </button>
            </form>

            <script>
                (function () {
                    // Wire up a lightweight AJAX submit handler for the form.
                    var form = document.getElementById(<?= json_encode($formId) ?>);
                    if (!form) {
                        return;
                    }
                    var message = document.getElementById(<?= json_encode($messageId) ?>);
                    var mouseMovements = [];
                    var formStartTime = Date.now();
                    // Track short mouse history to estimate bot entropy.
                    form.addEventListener("mousemove", function (e) {
                        mouseMovements.push({ x: e.clientX, y: e.clientY, t: Date.now() - formStartTime });
                        if (mouseMovements.length > 50) {
                            mouseMovements.shift();
                        }
                    });
                    function calculateMouseEntropy() {
                        if (mouseMovements.length < 5) {
                            return 0;
                        }
                        var distances = [];
                        for (var i = 1; i < mouseMovements.length; i++) {
                            var dx = mouseMovements[i].x - mouseMovements[i - 1].x;
                            var dy = mouseMovements[i].y - mouseMovements[i - 1].y;
                            distances.push(Math.sqrt(dx * dx + dy * dy));
                        }
                        var sum = 0;
                        for (var j = 0; j < distances.length; j++) {
                            sum += distances[j];
                        }
                        var avg = sum / distances.length;
                        var variance = 0;
                        for (var k = 0; k < distances.length; k++) {
                            variance += Math.pow(distances[k] - avg, 2);
                        }
                        variance = variance / distances.length;
                        return Math.sqrt(variance) / 100;
                    }
                    form.addEventListener("submit", async function (event) {
                        event.preventDefault();
                        // Swap the button label while the request is in flight.
                        var submitButton = form.querySelector("button[type=submit]");
                        var originalText = submitButton ? submitButton.textContent : "";
                        if (submitButton) {
                            submitButton.textContent = "Sending...";
                            submitButton.disabled = true;
                        }
                        // Clear the previous status alert.
                        if (message) {
                            message.classList.add("hidden");
                            message.classList.remove("border-red-200", "bg-red-50", "text-red-700", "border-emerald-200", "bg-emerald-50", "text-emerald-700");
                        }
                        // Refresh defense fields before submit.
                        var entropyInput = form.querySelector("input[name=mouse_entropy]");
                        if (entropyInput) {
                            entropyInput.value = calculateMouseEntropy();
                        }
                        var titleInput = form.querySelector("input[name=page_title]");
                        if (titleInput) {
                            titleInput.value = document.title || "";
                        }
                        var urlInput = form.querySelector("input[name=page_url]");
                        if (urlInput) {
                            urlInput.value = window.location.href || "";
                        }
                        var payload = new FormData(form);
                        var successFallback = <?= json_encode($successMessage) ?>;
                        try {
                            // Post the form and surface the server response.
                            var response = await fetch(form.getAttribute("action") || "/api/form", { method: "POST", body: payload });
                            var responseData = await response.json();
                            if (!responseData.success) {
                                throw new Error(responseData.message || "Failed to send message");
                            }
                            if (message) {
                                message.textContent = responseData.message || successFallback;
                                message.classList.remove("hidden");
                                message.classList.add("border-emerald-200", "bg-emerald-50", "text-emerald-700");
                            }
                            form.reset();
                        } catch (error) {
                            // Show errors inline for fast debugging.
                            if (message) {
                                message.textContent = error.message || "An error occurred. Please try again.";
                                message.classList.remove("hidden");
                                message.classList.add("border-red-200", "bg-red-50", "text-red-700");
                            }
                        } finally {
                            // Restore the submit button state.
                            if (submitButton) {
                                submitButton.textContent = originalText;
                                submitButton.disabled = false;
                            }
                        }
                    });
                })();
            </script>
        </div>
        <?php

        return trim((string)ob_get_clean());
    }

    private static function loadBlockFields(string $blockName): string
    {
        // Look up the app to resolve block paths.
        $app = get_app();
        if ($app === null) {
            return '';
        }

        // Bail if the Block component cannot be loaded.
        if (!class_exists('\\Components\\Block')) {
            return '';
        }

        // Resolve the markdown path from the block name.
        $blockPath = \Components\Block::resolveMarkdownBlockPath($app->root, $blockName);
        if ($blockPath === null || !file_exists($blockPath)) {
            return '';
        }

        // Return the raw markdown so the form can render it inline.
        $content = file_get_contents($blockPath);
        if ($content === false) {
            return '';
        }

        return trim($content);
    }
}
