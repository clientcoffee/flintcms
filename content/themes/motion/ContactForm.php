<?php

namespace Modules;

use Flint\RenderComponent;

/**
 * Contact Form Component
 *
 * Renders a secure contact form with bot protection features:
 * - Honeypot fields for bot detection
 * - Form tokens for CSRF protection
 * - Mouse entropy tracking for human verification
 * - Async submission with status feedback
 *
 * Form fields are loaded from a markdown block, allowing customizable forms
 * without code changes.
 */
class ContactForm extends RenderComponent
{
    /**
     * Render a contact form
     *
     * @param array  $props   Component properties (fields: block name, store: true|false)
     * @param string $content Unused (form fields come from blocks)
     * @return string Rendered HTML with inline JavaScript
     */
    public static function render(array $props, string $content): string
    {
        // Get block name from props using helper method, default to 'contact-form'.
        $blockName = self::prop($props, 'fields', 'contact-form');

        // Load form fields from block.
        $formFields = \Components\Block::render(['name' => $blockName], '');

        // Generate form token via hook system
        $formToken = \Flint\HookManager::trigger('form_token_generate', ['form_type' => 'contact']);

        // If no component provided a token, generate a basic one
        if ($formToken === null || $formToken === '') {
            $formToken = base64_encode(json_encode([
                'created' => time(),
                'nonce' => bin2hex(random_bytes(8))
            ]));
        }

        $fieldOrder = bin2hex(random_bytes(8));

        // Build the form HTML shell.
        $formHtml = '<div class="contact-form-wrapper my-8">';
        $formHtml .= '<div id="form-message" class="hidden mb-4 rounded-2xl border px-4 py-3 text-sm"></div>';
        $formHtml .= '<form id="contact-form" class="max-w-2xl mx-auto space-y-6">';

        // Honeypot fields (hidden, bots will fill these)
        $formHtml .= '<input type="text" name="website" style="position:absolute;left:-9999px;width:1px;height:1px" tabindex="-1" autocomplete="off">';
        $formHtml .= '<input type="text" name="url" style="position:absolute;left:-9999px;width:1px;height:1px" tabindex="-1" autocomplete="off">';
        $formHtml .= '<input type="text" name="company" style="position:absolute;left:-9999px;width:1px;height:1px" tabindex="-1" autocomplete="off">';

        // Defense tokens (hidden)
        $formHtml .= '<input type="hidden" name="form_token" id="form-token" value="' . self::escape($formToken) . '">';
        $formHtml .= '<input type="hidden" name="field_order" id="field-order" value="' . self::escape($fieldOrder) . '">';
        $formHtml .= '<input type="hidden" name="mouse_entropy" id="mouse-entropy" value="0">';

        // Insert form fields from block.
        $formHtml .= $formFields;

        // Add the submit button.
        $formHtml .= '<button type="submit" class="inline-flex items-center gap-2 bg-indigo-600 text-white px-6 py-3 rounded-md hover:bg-indigo-700 transition-colors font-medium">';
        $formHtml .= '<span>Send Message</span>';
        $formHtml .= '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">';
        $formHtml .= '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z"></path>';
        $formHtml .= '</svg>';
        $formHtml .= '</button>';
        $formHtml .= '</form>';

        // Append the submission script with defense tracking.
        $formHtml .= '<script>';
        $formHtml .= '(function() {';
        $formHtml .= '  let mouseMovements = [];';
        $formHtml .= '  let formStartTime = Date.now();';
        $formHtml .= '  ';
        $formHtml .= '  // Track mouse movement for bot detection';
        $formHtml .= '  document.getElementById("contact-form").addEventListener("mousemove", function(e) {';
        $formHtml .= '    mouseMovements.push({x: e.clientX, y: e.clientY, t: Date.now() - formStartTime});';
        $formHtml .= '    if (mouseMovements.length > 50) mouseMovements.shift(); // Keep last 50';
        $formHtml .= '  });';
        $formHtml .= '  ';
        $formHtml .= '  // Calculate mouse entropy on form submission';
        $formHtml .= '  function calculateMouseEntropy() {';
        $formHtml .= '    if (mouseMovements.length < 5) return 0;';
        $formHtml .= '    let distances = [];';
        $formHtml .= '    for (let i = 1; i < mouseMovements.length; i++) {';
        $formHtml .= '      let dx = mouseMovements[i].x - mouseMovements[i-1].x;';
        $formHtml .= '      let dy = mouseMovements[i].y - mouseMovements[i-1].y;';
        $formHtml .= '      distances.push(Math.sqrt(dx*dx + dy*dy));';
        $formHtml .= '    }';
        $formHtml .= '    let avg = distances.reduce((a,b) => a+b, 0) / distances.length;';
        $formHtml .= '    let variance = distances.reduce((a,b) => a + Math.pow(b-avg, 2), 0) / distances.length;';
        $formHtml .= '    return Math.sqrt(variance) / 100; // Normalize';
        $formHtml .= '  }';
        $formHtml .= '  ';
        $formHtml .= 'document.getElementById("contact-form").addEventListener("submit", async (event) => {';
        $formHtml .= '  // Prevent the default form submission flow. ';
        $formHtml .= '  event.preventDefault();';
        $formHtml .= '  // Gather interactive elements for status updates. ';
        $formHtml .= '  const messageContainer = document.getElementById("form-message");';
        $formHtml .= '  const submitButton = event.target.querySelector("button[type=submit]");';
        $formHtml .= '  const originalButtonText = submitButton.textContent;';
        $formHtml .= '  // Put the form into a busy state. ';
        $formHtml .= '  submitButton.textContent = "Sending...";';
        $formHtml .= '  submitButton.disabled = true;';
        $formHtml .= '  messageContainer.classList.add("hidden");';
        $formHtml .= '  messageContainer.classList.remove("border-red-200", "bg-red-50", "text-red-700", "border-emerald-200", "bg-emerald-50", "text-emerald-700");';
        $formHtml .= '  // Update mouse entropy before submission';
        $formHtml .= '  document.getElementById("mouse-entropy").value = calculateMouseEntropy();';
        $formHtml .= '  ';
        $formHtml .= '  // Build the payload from input values. ';
        $formHtml .= '  const formPayload = {';
        $formHtml .= '    name: document.getElementById("name").value,';
        $formHtml .= '    email: document.getElementById("email").value,';
        $formHtml .= '    message: document.getElementById("message").value,';
        $formHtml .= '    form_token: document.getElementById("form-token").value,';
        $formHtml .= '    field_order: document.getElementById("field-order").value,';
        $formHtml .= '    mouse_entropy: document.getElementById("mouse-entropy").value,';
        $formHtml .= '    website: document.querySelector(\'input[name="website"]\').value,';
        $formHtml .= '    url: document.querySelector(\'input[name="url"]\').value,';
        $formHtml .= '    company: document.querySelector(\'input[name="company"]\').value,';
        $formHtml .= '    store: "' . self::escape(self::prop($props, 'store', 'true')) . '",';
        $formHtml .= '    from: window.location.href';
        $formHtml .= '  };';
        $formHtml .= '  try {';
        $formHtml .= '    // Send the request to the contact endpoint. ';
        $formHtml .= '    const response = await fetch("/api/contact", {';
        $formHtml .= '      method: "POST",';
        $formHtml .= '      headers: { "Content-Type": "application/json" },';
        $formHtml .= '      body: JSON.stringify(formPayload)';
        $formHtml .= '    });';
        $formHtml .= '    const responseData = await response.json();';
        $formHtml .= '    if (!responseData.success) {';
        $formHtml .= '      throw new Error(responseData.message || "Failed to send message");';
        $formHtml .= '    }';
        $formHtml .= '    // Display the success state and reset the form. ';
        $formHtml .= '    messageContainer.textContent = "Thank you! Your message has been sent successfully.";';
        $formHtml .= '    messageContainer.classList.remove("hidden");';
        $formHtml .= '    messageContainer.classList.add("border-emerald-200", "bg-emerald-50", "text-emerald-700");';
        $formHtml .= '    event.target.reset();';
        $formHtml .= '  } catch (error) {';
        $formHtml .= '    // Display the error state and keep inputs intact. ';
        $formHtml .= '    messageContainer.textContent = error.message || "An error occurred. Please try again.";';
        $formHtml .= '    messageContainer.classList.remove("hidden");';
        $formHtml .= '    messageContainer.classList.add("border-red-200", "bg-red-50", "text-red-700");';
        $formHtml .= '  } finally {';
        $formHtml .= '    // Restore the button state. ';
        $formHtml .= '    submitButton.textContent = originalButtonText;';
        $formHtml .= '    submitButton.disabled = false;';
        $formHtml .= '  }';
        $formHtml .= '});';
        $formHtml .= '})();'; // Close IIFE
        $formHtml .= '</script>';
        $formHtml .= '</div>';

        // Return the final contact form markup.
        return $formHtml;
    }
}
