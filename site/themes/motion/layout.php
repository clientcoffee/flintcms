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

/**
 * Motion layout template.
 *
 * The CMS injects $site, $page, $viewContent, $componentAssets, and $isAdmin.
 * We compute everything up front to keep the HTML clean and predictable.
 */
$siteName = $site['name'] ?? '';
$pageTitleRaw = (string)($page['meta']['title'] ?? $siteName);
$pageTitle = render_inline_markdown($pageTitleRaw);
$pageTitlePlain = trim(strip_tags($pageTitle));
if ($pageTitlePlain === '') {
    $pageTitlePlain = $siteName;
}
// Optional SEO keywords are stored in frontmatter if present.
$keywords = page_meta('keywords');
$hasKeywords = $keywords !== '';

// Adjust content spacing when a banner is present on non-post pages.
$themeConfig = \Flint\ThemeContext::get('themeConfig', []);
$contentType = strtolower((string)($page['meta']['type'] ?? ''));
$bannerUrl = $page['meta']['banner'] ?? ($themeConfig['settings']['default_banner'] ?? '');
$hasBanner = $contentType !== 'post' && $bannerUrl !== '';
$mainPaddingClass = $hasBanner ? 'pt-10 sm:pt-8' : 'pt-14 sm:pt-10';
$contentPaddingClass = $hasBanner ? 'pb-12 sm:pb-16' : 'py-12 sm:py-16';

// Nav content comes from a markdown block in /site/blocks/nav.md.
$navItems = '';
$navPath = \Components\Block::resolveMarkdownBlockPath(\Flint\Paths::$rootDir, 'nav');
// Block resolution uses the CMS helper so it respects the current site root.
if ($navPath !== null && is_readable($navPath)) {
    $navItems = file_get_contents($navPath);
}

// Render the Nav component if we have items, otherwise fall back to the block renderer.
$navHtml = trim($navItems) !== '' ? \Components\Nav::render(['items' => $navItems], '') : render_block('nav');

// Simple auth toggle driven by the CMS session state.
$showLogout = !empty($isAdmin);
$loginUrl = '/login';
$adminUrl = '/admin';

// Footer column content: nav, recent posts, and quick links.
$footerNavHtml = '';
if ($navPath !== null && is_readable($navPath)) {
    $footerNavHtml = render_block('nav');
}

$recentPosts = function_exists('motion_theme_get_recent_blog_posts')
    ? motion_theme_get_recent_blog_posts(5, $showLogout)
    : [];

$footerLinks = [
    ['label' => 'Sitemap', 'href' => '/sitemap'],
];
if ($showLogout) {
    $footerLinks[] = ['label' => 'Admin', 'href' => $adminUrl];
} else {
    $footerLinks[] = ['label' => 'Login', 'href' => $loginUrl];
}

// Collect head assets here so the template is mostly HTML below.
// render_assets() injects component CSS; theme_styles() triggers theme hooks.
ob_start();
render_assets('head');
theme_styles();
$headAssets = ob_get_clean();

// Collect footer assets (scripts) the same way for a clean footer block.
ob_start();
render_assets('foot');
theme_scripts();
$footerAssets = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Character encoding for international character support -->
    <meta charset="UTF-8">

    <!-- Responsive viewport settings for mobile devices -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Page title: Use page-specific title if available, otherwise site name -->
    <title><?= esc_html($pageTitlePlain) ?> | <?= esc_html($siteName) ?></title>

    <!-- Optional: SEO keywords meta tag (only if page has keywords defined) -->
    <?php if ($hasKeywords) : ?>
        <meta name="keywords" content="<?= $keywords ?>">
    <?php endif; ?>

    <!-- Theme's main stylesheet (Tailwind CSS) -->
    <link rel="stylesheet" href="<?= theme_asset('tailwind.min.css') ?>">
    <?= $headAssets ?>
</head>
<body class="bg-[#FAFAFA] text-gray-800 antialiased">
    <!-- Fixed Top Navigation Bar -->
    <!-- This stays at the top of the viewport as users scroll -->
    <div class="fixed top-0 left-0 right-0 bg-white border-b border-gray-200/60 shadow-sm z-50">
        <div class="max-w-5xl mx-auto px-6 sm:px-12 py-2 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
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
    <main class="<?= esc_html($mainPaddingClass) ?> min-h-screen">
        <div class="max-w-3xl mx-auto px-6 sm:px-12 <?= esc_html($contentPaddingClass) ?>">
            <!-- $viewContent is the fully rendered HTML from Parser + view.php. -->
            <?= $viewContent ?>
        </div>
    </main>

    <!-- Site Footer -->
    <footer class="border-t border-gray-200 bg-white">
        <div class="max-w-5xl mx-auto px-6 sm:px-12 py-10 motion-footer">
            <div class="motion-footer__grid">
                <div class="motion-footer__col">
                    <h3 class="motion-footer__title">Navigate</h3>
                    <div class="motion-footer__nav">
                        <?= $footerNavHtml ?>
                    </div>
                </div>

                <div class="motion-footer__col">
                    <h3 class="motion-footer__title">Recent posts</h3>
                    <?php if (!empty($recentPosts)) : ?>
                        <ul class="motion-footer__list">
                            <?php foreach ($recentPosts as $post) : ?>
                                <li>
                                    <a class="motion-footer__link" href="<?= esc_html($post['path']) ?>">
                                        <?= esc_html($post['label']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="motion-footer__empty">No posts yet.</p>
                    <?php endif; ?>
                </div>

                <div class="motion-footer__col">
                    <h3 class="motion-footer__title">Account</h3>
                    <ul class="motion-footer__list">
                        <?php foreach ($footerLinks as $link) : ?>
                            <li>
                                <a class="motion-footer__link" href="<?= esc_html($link['href']) ?>">
                                    <?= esc_html($link['label']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        <?php if ($showLogout) : ?>
                            <li>
                                <button id="admin-logout-btn" class="motion-footer__button">Logout</button>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="motion-footer__meta">
                <!-- Copyright/Branding. I'd appreciate it if you left this in your themes. -->
                <p class="text-sm text-gray-500">
                    Powered by <a href="https://flintcms.com" target="_blank" title="A flat file CMS built on Markdown" class="text-gray-700 hover:text-gray-900 font-medium">Flint</a>
                </p>
            </div>
        </div>
    </footer>

    <!-- Component External Scripts (Footer Position) -->
    <!-- Most JavaScript goes here at the end of body for better page load performance -->
    <?= $footerAssets ?>
</body>
</html>
