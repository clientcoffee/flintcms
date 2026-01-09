<?php
/**
 * Motion Theme - Main Layout Template
 *
 * This is the master template that wraps around all page content.
 * It provides the HTML structure, header navigation, footer, and handles
 * component assets (CSS/JS) from components that need them.
 *
 * Available Variables:
 * - $site: Site configuration array (name, theme, etc.)
 * - $page: Current page data (meta, content, etc.)
 * - $viewContent: The rendered page content (HTML)
 * - $componentAssets: Array of CSS/JS assets from components
 * - $isAdmin: Boolean indicating if current user is admin
 *
 * @package Flint
 * @subpackage MotionTheme
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Character encoding for international character support -->
    <meta charset="UTF-8">

    <!-- Responsive viewport settings for mobile devices -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Page title: Use page-specific title if available, otherwise site name -->
    <title><?= htmlspecialchars($page['meta']['title'] ?? $site['name']) ?> | <?= htmlspecialchars($site['name']) ?></title>

    <!-- Optional: SEO keywords meta tag (only if page has keywords defined) -->
    <?php if (!empty($page['meta']['keywords'])) : ?>
    <meta name="keywords" content="<?= htmlspecialchars($page['meta']['keywords']) ?>">
    <?php endif; ?>

    <!-- Theme's main stylesheet (Tailwind CSS) -->
    <link rel="stylesheet" href="/themes/motion/tailwind.min.css">

    <!-- Component External Stylesheets -->
    <!-- Components can register external CSS files they need -->
    <?php if (!empty($componentAssets['styles'] ?? [])) : ?>
        <?php foreach ($componentAssets['styles'] as $externalStylesheet) : ?>
            <link rel="stylesheet" href="<?= htmlspecialchars($externalStylesheet['href']) ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Component Inline Styles -->
    <!-- Components can also inject inline CSS for custom styling -->
    <?php if (!empty($componentAssets['inline_styles'] ?? [])) : ?>
        <style>
            <?php foreach ($componentAssets['inline_styles'] as $inlineStyle) : ?>
                <?php
                // SECURITY: Validate inline CSS to prevent XSS attacks
                // Malicious components could try to inject scripts via CSS
                $styleContent = $inlineStyle['content'] ?? '';

                // Check for dangerous patterns that could lead to code execution
                $hasDangerousContent = (
                    stripos($styleContent, '</style') !== false ||  // Trying to close style tag early
                    stripos($styleContent, '<script') !== false ||  // Injecting script tags
                    stripos($styleContent, 'javascript:') !== false || // JavaScript URLs in CSS
                    stripos($styleContent, 'expression(') !== false  // IE expression() exploit
                );

                if (!$hasDangerousContent) {
                    // Content is safe, output it
                    echo $styleContent;
                } else {
                    // SECURITY VIOLATION: Log the attempted attack
                    error_log('Security: Blocked potentially malicious inline style content');
                }
                ?>

            <?php endforeach; ?>
        </style>
    <?php endif; ?>

    <!-- Component Scripts for <head> Section -->
    <!-- Some components need their JavaScript in the head (rare, most go in footer) -->
    <?php
    if (!empty($componentAssets['scripts'] ?? [])) :
        foreach ($componentAssets['scripts'] as $componentScript) :
            // Only include scripts explicitly marked for head placement
            if (($componentScript['position'] ?? 'footer') === 'head') :
                ?>
        <script src="<?= htmlspecialchars($componentScript['src']) ?>"<?= !empty($componentScript['type']) ? ' type="' . htmlspecialchars($componentScript['type']) . '"' : '' ?>></script>
                <?php
            endif;
        endforeach;
    endif;
    ?>

    <!-- Theme Global Styles -->
    <style>
        /* Import Inter font from Google Fonts for modern typography */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        /* Set Inter as primary font with system font fallbacks */
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;
        }

        /* Enable smooth scrolling for anchor links */
        html {
            scroll-behavior: smooth;
        }

        /* Heading typography: Bold weights and tight letter spacing */
        h1 { font-weight: 700; letter-spacing: -0.02em; }
        h2 { font-weight: 600; letter-spacing: -0.01em; }
        h3, h4 { font-weight: 600; }

        /* Navigation link hover effects with smooth transitions */
        .nav-link {
            transition: all 0.15s ease;
        }

        /* Global smooth transitions for color/background changes */
        * {
            transition-property: background-color, border-color, color, fill, stroke;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
            transition-duration: 150ms;
        }
    </style>
</head>
<body class="bg-[#FAFAFA] text-gray-800 antialiased">
    <!-- Fixed Top Navigation Bar -->
    <!-- This stays at the top of the viewport as users scroll -->
    <div class="fixed top-0 left-0 right-0 bg-white/80 backdrop-blur-md border-b border-gray-200/60 z-50">
        <div class="max-w-5xl mx-auto px-6 sm:px-12 py-3 flex justify-between items-center">
            <!-- Site Logo/Home Link -->
            <a href="/" class="flex items-center gap-2 text-gray-900 hover:text-gray-600 font-semibold text-sm nav-link">
                <!-- Home icon (SVG) -->
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <!-- Site name from configuration -->
                <?= htmlspecialchars($site['name']) ?>
            </a>

            <!-- Navigation Menu (hidden on mobile, shown on tablet+) -->
            <div class="hidden sm:flex items-center gap-3">
                <?php
                // Load navigation links from a markdown block file
                // This allows site owners to edit navigation without touching code
                // File location: content/blocks/nav.md
                echo \Components\Block::render(['name' => 'nav'], '');
                ?>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <!-- pt-16 creates top padding to account for fixed header -->
    <main class="pt-16 min-h-screen">
        <div class="max-w-3xl mx-auto px-6 sm:px-12 py-12 sm:py-16">
            <?php
            // This is where the actual page content is rendered
            // $viewContent contains the parsed markdown as HTML
            echo $viewContent;
            ?>
        </div>
    </main>

    <!-- Site Footer -->
    <footer class="border-t border-gray-200 bg-white">
        <div class="max-w-3xl mx-auto px-6 sm:px-12 py-8 flex items-center justify-between">
            <!-- Copyright/Branding -->
            <p class="text-sm text-gray-500">
                Powered by <a href="#" class="text-gray-700 hover:text-gray-900 font-medium">Flint</a>
            </p>

            <!-- Admin Link (only shown to logged-in administrators) -->
            <?php if (!empty($isAdmin)) : ?>
                <a href="/admin" class="text-sm text-gray-700 hover:text-gray-900 font-medium">Admin</a>
            <?php endif; ?>
        </div>
    </footer>

    <!-- Component External Scripts (Footer Position) -->
    <!-- Most JavaScript goes here at the end of body for better page load performance -->
    <?php if (!empty($componentAssets['scripts'] ?? [])) : ?>
        <?php foreach ($componentAssets['scripts'] as $componentScript) : ?>
            <?php if (($componentScript['position'] ?? 'footer') === 'footer') : ?>
                <script src="<?= htmlspecialchars($componentScript['src']) ?>"<?= !empty($componentScript['type']) ? ' type="' . htmlspecialchars($componentScript['type']) . '"' : '' ?>></script>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Component Inline Scripts (Footer Position) -->
    <!-- Inline JavaScript from components (use sparingly for security reasons) -->
    <?php if (!empty($componentAssets['inline_scripts'] ?? [])) : ?>
        <?php foreach ($componentAssets['inline_scripts'] as $inlineScript) : ?>
            <?php if (($inlineScript['position'] ?? 'footer') === 'footer') : ?>
                <script<?= !empty($inlineScript['type']) ? ' type="' . htmlspecialchars($inlineScript['type']) . '"' : '' ?>>
                    <?php
                    // SECURITY: Validate inline JavaScript to prevent XSS attacks
                    // This is HIGH RISK - inline scripts bypass Content Security Policy
                    $scriptContent = $inlineScript['content'] ?? '';

                    // Check for dangerous patterns that could break out of script context
                    $hasScriptInjection = (
                        stripos($scriptContent, '</script') !== false ||  // Trying to close script tag early
                        stripos($scriptContent, '<script') !== false     // Trying to inject additional script tags
                    );

                    if (!$hasScriptInjection) {
                        // Content appears safe, output it
                        // Note: This is still risky - components should be from trusted sources only
                        echo $scriptContent;
                    } else {
                        // SECURITY VIOLATION: Log the attempted attack
                        error_log('Security: Blocked potentially malicious inline script content');
                        // Output a safe placeholder instead
                        echo '// Script blocked for security reasons';
                    }
                    ?>

                </script>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
