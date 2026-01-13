<?php

namespace Components\GoogleAnalytics;

use Flint\RenderComponent;

/**
 * Google Analytics 4 integration component.
 *
 * Injects Google Analytics tracking code into page head.
 */
class GoogleAnalytics extends RenderComponent
{
     /**
     * Render Google Analytics tracking script for page head.
     *
     * @param array $props Component properties (measurement_id).
     * @param string $content Component body content (unused).
     * @return string HTML script tag for GA4 tracking.
     */
    public static function render(array $props, string $content): string
    {
        $measurementId = self::prop($props, 'measurement_id', '');

        // If no measurement ID is provided, return empty string.
        if (empty($measurementId)) {
            return '';
        }

        // Escape the measurement ID for safety.
        $safeMeasurementId = self::escape($measurementId);

        // Return Google Analytics gtag.js script.
        ob_start();
        ?>
        <!-- Google Analytics. -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?= $safeMeasurementId ?>"></script>
        <script>
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());
          gtag('config', '<?= $safeMeasurementId ?>');
        </script>
        <?php

        return trim((string)ob_get_clean());
    }
}
