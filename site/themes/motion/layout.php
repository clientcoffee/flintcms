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

<?php
$siteName = $site['name'] ?? '';
$pageTitle = page_meta('title', $siteName);
$keywords = page_meta('keywords');
$keywordsMeta = $keywords !== '' ? '<meta name="keywords" content="' . $keywords . '">' : '';

$navItems = '';
$navPath = \Components\Block::resolveMarkdownBlockPath(\Flint\Paths::$rootDir, 'nav');
if ($navPath !== null && is_readable($navPath)) {
    $navItems = file_get_contents($navPath);
}

$navHtml = trim($navItems) !== '' ? \Components\Nav::render(['items' => $navItems], '') : render_block('nav');

$authHtml = $isAdmin
    ? '<button id="admin-logout-btn" class="text-sm text-gray-700 hover:text-gray-900 font-medium nav-link">Logout</button>'
    : '<a href="/login" class="text-sm text-gray-700 hover:text-gray-900 font-medium nav-link">Login</a>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Character encoding for international character support -->
    <meta charset="UTF-8">

    <!-- Responsive viewport settings for mobile devices -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Page title: Use page-specific title if available, otherwise site name -->
    <title><?= $pageTitle ?> | <?= esc_html($siteName) ?></title>

    <!-- Optional: SEO keywords meta tag (only if page has keywords defined) -->
    <?= $keywordsMeta ?>

    <!-- Theme's main stylesheet (Tailwind CSS) -->
    <link rel="stylesheet" href="<?= theme_asset('tailwind.min.css') ?>">
    <?php render_assets('head'); ?>
    <?php theme_styles(); ?>
</head>
<body class="bg-[#FAFAFA] text-gray-800 antialiased">
    <!-- Fixed Top Navigation Bar -->
    <!-- This stays at the top of the viewport as users scroll -->
    <div class="fixed top-0 left-0 right-0 bg-white border-b border-gray-200/60 shadow-sm z-50">
        <div class="max-w-5xl mx-auto px-6 sm:px-12 py-3 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <!-- Site Logo/Home Link -->
            <a href="/" class="flex items-center gap-2 text-gray-900 hover:text-gray-600 font-semibold text-sm nav-link">
                <!-- Home icon (SVG) -->
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <!-- Site name from configuration -->
                <?= esc_html($siteName) ?>
            </a>

            <!-- Navigation Menu -->
            <div class="nav-menu flex items-center gap-3">
                <?= $navHtml ?>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <!-- pt-16 creates top padding to account for fixed header -->
    <main class="pt-24 sm:pt-16 min-h-screen">
        <div class="max-w-3xl mx-auto px-6 sm:px-12 py-12 sm:py-16">
            <?= $viewContent ?>
        </div>
    </main>

    <!-- Site Footer -->
    <footer class="border-t border-gray-200 bg-white">
        <div class="max-w-3xl mx-auto px-6 sm:px-12 py-8 flex items-center justify-between">
            <!-- Copyright/Branding. I'd appreciate it if you left this in your themes. -->
            <p class="text-sm text-gray-500">
                Powered by <a href="https://flintcms.com" target="_blank" title="A flat file CMS built on Markdown" class="text-gray-700 hover:text-gray-900 font-medium">Flint</a>
            </p>

            <?= $authHtml ?>
        </div>
    </footer>

    <!-- Component External Scripts (Footer Position) -->
    <!-- Most JavaScript goes here at the end of body for better page load performance -->
    <?php render_assets('foot'); ?>
    <?php theme_scripts(); ?>
</body>
</html>
