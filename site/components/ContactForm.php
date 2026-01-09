<?php

namespace Components;

use Flint\RenderComponent;
use Flint\HookManager;

class ContactForm extends RenderComponent
{
    public static function render(array $props, string $content): string
    {
        $formName = trim((string) self::prop($props, 'name', 'contact'));
        $formId = trim((string) self::prop($props, 'id', 'contact-form'));
        $fieldsBlock = trim((string) self::prop($props, 'fields', 'contact-form'));
        $formClass = self::prop($props, 'class', 'max-w-2xl mx-auto space-y-6');
        $wrapperClass = self::prop($props, 'wrapper_class', 'my-8');
        $submitText = self::prop($props, 'submit', 'Send Message');
        $submitClass = self::prop($props, 'submit_class', 'inline-flex items-center gap-2 bg-indigo-600 text-white px-6 py-3 rounded-md hover:bg-indigo-700 transition-colors font-medium');
        $successMessage = self::prop($props, 'success', 'Thank you! Your message has been sent successfully.');
        $redirectUrl = self::prop($props, 'redirect', '');

        $fieldsHtml = trim($content) !== '' ? $content : self::loadBlockFields($fieldsBlock);
        if ($fieldsHtml === '') {
            return '<!-- ContactForm component: no fields found -->';
        }

        $formToken = HookManager::trigger('form_token_generate', ['form_type' => $formName]);
        if ($formToken === null || $formToken === '') {
            $formToken = base64_encode(json_encode([
                'created' => time(),
                'nonce' => bin2hex(random_bytes(8))
            ]));
        }

        $fieldOrder = bin2hex(random_bytes(8));
        $messageId = $formId . '-message';

        $html = '<div class="' . self::escape($wrapperClass) . '">';
        $html .= '<div id="' . self::escape($messageId) . '" class="hidden mb-4 rounded-2xl border px-4 py-3 text-sm"></div>';
        $html .= '<form id="' . self::escape($formId) . '" class="' . self::escape($formClass) . '" action="/api/form" method="POST">';

        // Honeypot fields.
        $html .= '<input type="text" name="website" style="position:absolute;left:-9999px;width:1px;height:1px" tabindex="-1" autocomplete="off">';
        $html .= '<input type="text" name="url" style="position:absolute;left:-9999px;width:1px;height:1px" tabindex="-1" autocomplete="off">';
        $html .= '<input type="text" name="company" style="position:absolute;left:-9999px;width:1px;height:1px" tabindex="-1" autocomplete="off">';

        // Defense tokens.
        $html .= '<input type="hidden" name="form_token" value="' . self::escape($formToken) . '">';
        $html .= '<input type="hidden" name="form_name" value="' . self::escape($formName) . '">';
        $html .= '<input type="hidden" name="success_message" value="' . self::escape($successMessage) . '">';
        if ($redirectUrl !== '') {
            $html .= '<input type="hidden" name="redirect_url" value="' . self::escape($redirectUrl) . '">';
        }
        $html .= '<input type="hidden" name="field_order" value="' . self::escape($fieldOrder) . '">';
        $html .= '<input type="hidden" name="mouse_entropy" value="0">';
        $html .= '<input type="hidden" name="page_title" value="">';
        $html .= '<input type="hidden" name="page_url" value="">';

        $html .= $fieldsHtml;

        $html .= '<button type="submit" class="' . self::escape($submitClass) . '">';
        $html .= '<span>' . self::escape($submitText) . '</span>';
        $html .= '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">';
        $html .= '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z"></path>';
        $html .= '</svg>';
        $html .= '</button>';
        $html .= '</form>';

        $html .= '<script>(function(){';
        $html .= 'var form=document.getElementById(' . json_encode($formId) . ');';
        $html .= 'if(!form){return;}';
        $html .= 'var message=document.getElementById(' . json_encode($messageId) . ');';
        $html .= 'var mouseMovements=[];';
        $html .= 'var formStartTime=Date.now();';
        $html .= 'form.addEventListener("mousemove",function(e){mouseMovements.push({x:e.clientX,y:e.clientY,t:Date.now()-formStartTime});if(mouseMovements.length>50){mouseMovements.shift();}});';
        $html .= 'function calculateMouseEntropy(){if(mouseMovements.length<5){return 0;}var distances=[];for(var i=1;i<mouseMovements.length;i++){var dx=mouseMovements[i].x-mouseMovements[i-1].x;var dy=mouseMovements[i].y-mouseMovements[i-1].y;distances.push(Math.sqrt(dx*dx+dy*dy));}var sum=0;for(var j=0;j<distances.length;j++){sum+=distances[j];}var avg=sum/distances.length;var variance=0;for(var k=0;k<distances.length;k++){variance+=Math.pow(distances[k]-avg,2);}variance=variance/distances.length;return Math.sqrt(variance)/100;}';
        $html .= 'form.addEventListener("submit",async function(event){event.preventDefault();var submitButton=form.querySelector("button[type=submit]");var originalText=submitButton?submitButton.textContent:"";if(submitButton){submitButton.textContent="Sending...";submitButton.disabled=true;}if(message){message.classList.add("hidden");message.classList.remove("border-red-200","bg-red-50","text-red-700","border-emerald-200","bg-emerald-50","text-emerald-700");}var entropyInput=form.querySelector("input[name=mouse_entropy]");if(entropyInput){entropyInput.value=calculateMouseEntropy();}var titleInput=form.querySelector("input[name=page_title]");if(titleInput){titleInput.value=document.title||"";}var urlInput=form.querySelector("input[name=page_url]");if(urlInput){urlInput.value=window.location.href||"";}var payload=new FormData(form);try{var response=await fetch(form.getAttribute("action")||"/api/form",{method:"POST",body:payload});var responseData=await response.json();if(!responseData.success){throw new Error(responseData.message||"Failed to send message");}if(message){message.textContent=responseData.message||"' . self::escape($successMessage) . '";message.classList.remove("hidden");message.classList.add("border-emerald-200","bg-emerald-50","text-emerald-700");}form.reset();}catch(error){if(message){message.textContent=error.message||"An error occurred. Please try again.";message.classList.remove("hidden");message.classList.add("border-red-200","bg-red-50","text-red-700");}}finally{if(submitButton){submitButton.textContent=originalText;submitButton.disabled=false;}}});';
        $html .= '})();</script>';
        $html .= '</div>';

        return $html;
    }

    private static function loadBlockFields(string $blockName): string
    {
        $app = self::getApp();
        if ($app === null) {
            return '';
        }

        if (!class_exists('\\Components\\Block')) {
            $blockPath = $app->appDir . '/core/components/Block/Block.php';
            if (file_exists($blockPath)) {
                require_once $blockPath;
            }
        }

        if (!class_exists('\\Components\\Block')) {
            return '';
        }

        $blockPath = \Components\Block::resolveMarkdownBlockPath($app->root, $blockName);
        if ($blockPath === null || !file_exists($blockPath)) {
            return '';
        }

        $content = file_get_contents($blockPath);
        if ($content === false) {
            return '';
        }

        return trim($content);
    }
}
