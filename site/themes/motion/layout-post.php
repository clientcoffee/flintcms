<?php

/**
 * Motion post layout template.
 *
 * Posts have a dedicated banner header, so we keep the post-specific UI here.
 * Data comes from $page (frontmatter + content) and $site (global config).
 */

$siteName = $site['name'] ?? '';
$postTitleRaw = (string)($page['meta']['title'] ?? 'Untitled Post');
$postTitle = $postTitleRaw !== '' ? render_inline_markdown($postTitleRaw) : '';
$postTitlePlain = trim(strip_tags($postTitle));
$pageTitle = $postTitlePlain !== '' ? $postTitlePlain : $siteName;
// Keywords are optional metadata from the markdown frontmatter.
$keywords = page_meta('keywords');
$hasKeywords = $keywords !== '';
// This title is shown in the post banner header.

// Icons are stored in frontmatter as slugs and mapped to emoji in helpers.php.
// Emoji slug is optional, so start with an empty value.
$iconEmoji = '';
if (!empty($page['meta']['icon'])) {
    $iconEmoji = getEmojiFromSlug($page['meta']['icon']);
}

// Optional description below the title.
$postDescription = $page['meta']['description'] ?? '';
// We render the description only when present.
$hasDescription = $postDescription !== '';

// Meta chips (author/date/readtime) come straight from frontmatter.
$author = $page['meta']['author'] ?? '';
$date = $page['meta']['date'] ?? '';
$readtime = $page['meta']['readtime'] ?? '';
// Each meta field is optional; we only render what exists.
// Compute booleans once so the markup stays clean.
$hasAuthor = $author !== '';
$hasDate = $date !== '';
$hasReadtime = $readtime !== '';
$hasMeta = $hasAuthor || $hasDate || $hasReadtime;

// Auth button is driven by CMS session state.
$showLogout = !empty($isAdmin);
$loginUrl = '/login';
$adminUrl = '/admin';

// Footer column content: nav, recent posts, and quick links.
$footerNavHtml = '';
$navPath = \Components\Block::resolveMarkdownBlockPath(\Flint\Paths::$rootDir, 'nav');
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

// Capture head assets from the CMS hooks before the HTML renders.
// This is how components and themes inject CSS without hard-coding paths.
ob_start();
render_assets('head');
theme_styles();
$headAssets = ob_get_clean();

// Capture footer assets (scripts registered by components/theme).
ob_start();
render_assets('foot');
theme_scripts();
$footerAssets = ob_get_clean();

// Optional post navigation (previous/next siblings).
$neighborNavHtml = '';
$componentsDir = isset(\Flint\Paths::$siteComponentsDir) ? \Flint\Paths::$siteComponentsDir : '';
if ($componentsDir !== '' && is_dir($componentsDir)) {
    $neighborComponentPath = $componentsDir . '/NeighborNav.php';
    if (is_file($neighborComponentPath)) {
        require_once $neighborComponentPath;
        if (class_exists(\Components\NeighborNav::class)) {
            $neighborNavHtml = \Components\NeighborNav::render([], '');
        }
    }
}
$hasNeighborNav = $neighborNavHtml !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= esc_html($siteName) ?></title>
    <?php if ($hasKeywords) : ?>
        <meta name="keywords" content="<?= $keywords ?>">
    <?php endif; ?>
    <!-- Tailwind and theme styles are injected through the CMS asset pipeline. -->
    <link rel="stylesheet" href="<?= theme_asset('tailwind.min.css') ?>">
    <?= $headAssets ?>
</head>
<body class="bg-[#FAFAFA] text-gray-800 antialiased">
    <!-- Reading progress bar is styled in theme.css, not inline. -->
    <div id="reading-progress"></div>

    <!-- Post Header with Gradient -->
    <header class="post-header text-white py-16 sm:py-24">
        <div class="max-w-3xl mx-auto px-6 sm:px-12">
            <!-- Back link is static; CMS routing handles it. -->
            <a href="/" class="inline-flex items-center gap-2 text-white/80 hover:text-white text-sm mb-6">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Home
            </a>

            <?php if ($iconEmoji !== '') : ?>
                <span class="text-6xl mb-4 block"><?= $iconEmoji ?></span>
            <?php endif; ?>

            <!-- Title comes from markdown frontmatter. -->
            <h1 class="text-4xl sm:text-5xl font-bold mb-4 leading-tight">
                <?= $postTitle ?>
            </h1>

            <?php if ($hasDescription) : ?>
                <p class="text-xl text-white/90 mb-6 leading-relaxed"><?= esc_html($postDescription) ?></p>
            <?php endif; ?>

            <!-- Meta chips are optional and driven by frontmatter. -->
            <?php if ($hasMeta) : ?>
                <div class="post-meta flex items-center gap-4">
                    <?php if ($hasAuthor) : ?>
                        <span>By <?= esc_html($author) ?></span>
                    <?php endif; ?>
                    <?php if ($hasDate) : ?>
                        <?php if ($hasAuthor) : ?>
                            <span>•</span>
                        <?php endif; ?>
                        <time><?= esc_html($date) ?></time>
                    <?php endif; ?>
                    <?php if ($hasReadtime) : ?>
                        <?php if ($hasAuthor || $hasDate) : ?>
                            <span>•</span>
                        <?php endif; ?>
                        <span><?= esc_html($readtime) ?> min read</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Post Content -->
    <main class="py-12 sm:py-16">
        <article class="max-w-3xl mx-auto px-6 sm:px-12">
            <!-- $viewContent is the rendered HTML from view.php + markdown parser. -->
            <?= $viewContent ?>
        </article>
        <?php if ($hasNeighborNav) : ?>
            <div class="max-w-3xl mx-auto px-6 sm:px-12 motion-post-neighbors">
                <?= $neighborNavHtml ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
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

    <?= $footerAssets ?>

    <!-- Reading progress script (pure front-end, no CMS involvement). -->
    <script>
        window.addEventListener('scroll', () => {
            const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (winScroll / height) * 100;
            document.getElementById('reading-progress').style.width = scrolled + '%';
        });
    </script>
</body>
</html>
