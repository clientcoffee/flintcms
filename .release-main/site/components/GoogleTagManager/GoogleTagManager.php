<?php

namespace Components\GoogleTagManager;

use Flint\RenderComponent;

/**
 * Google Tag Manager integration component.
 *
 * Injects Google Tag Manager container code into the page head.
 * Use renderNoScript() to output the noscript iframe after <body>.
 */
class GoogleTagManager extends RenderComponent
{
     /**
     * Render Google Tag Manager script for page head.
     *
     * @param array $props Component properties (container_id).
     * @param string $content Component body content (unused).
     * @return string HTML script tag for GTM tracking.
     */
    public static function render(array $props, string $content): string
    {
        $containerId = prop($props, 'container_id', '');

        // If no container ID is provided, return empty string.
        if (empty($containerId)) {
            return '';
        }

        // Escape the container ID for safety.
        $safeContainerId = esc_html($containerId);

        // Return Google Tag Manager head script.
        ob_start();
        ?>
        <!-- Google Tag Manager. -->
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','<?= $safeContainerId ?>');</script>
        <!-- End Google Tag Manager. -->
        <?php

        return trim((string)ob_get_clean());
    }

     /**
     * Get the noscript iframe code (to be manually added to theme body).
     *
     * This is a helper method that themes can call to get the noscript iframe.
     * Call it right after the opening <body> tag in your theme template.
     *
     * @param string $containerId GTM container ID.
     * @return string HTML noscript iframe.
     */
    public static function renderNoScript(string $containerId): string
    {
        if (empty($containerId)) {
            return '';
        }

        $safeContainerId = htmlspecialchars($containerId, ENT_QUOTES, 'UTF-8');

        ob_start();
        ?>
        <!-- Google Tag Manager (noscript). -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= $safeContainerId ?>"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript). -->
        <?php

        return trim((string)ob_get_clean());
    }
}
