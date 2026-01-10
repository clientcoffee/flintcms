<?php
/**
 * Motion post layout template.
 *
 * Posts have a dedicated banner header, so we keep the post-specific UI here.
 * Data comes from $page (frontmatter + content) and $site (global config).
 */
$siteName = $site['name'] ?? '';
$pageTitle = page_meta('title', $siteName);
$keywords = page_meta('keywords');
$keywordsMeta = $keywords !== '' ? '<meta name="keywords" content="' . $keywords . '">' : '';
$postTitle = page_meta('title', 'Untitled Post');

// Icons are stored in frontmatter as slugs and mapped to emoji in helpers.php.
$iconEmoji = '';
if (!empty($page['meta']['icon'])) {
    $iconEmoji = getEmojiFromSlug($page['meta']['icon']);
}
$iconMarkup = $iconEmoji !== '' ? '<span class="text-6xl mb-4 block">' . $iconEmoji . '</span>' : '';

// Optional description below the title.
$postDescription = $page['meta']['description'] ?? '';
$descriptionMarkup = $postDescription !== ''
    ? '<p class="text-xl text-white/90 mb-6 leading-relaxed">' . esc_html($postDescription) . '</p>'
    : '';

// Meta chips (author/date/readtime) come straight from frontmatter.
$metaParts = [];
$author = $page['meta']['author'] ?? '';
if ($author !== '') {
    $metaParts[] = '<span>By ' . esc_html($author) . '</span>';
}
$date = $page['meta']['date'] ?? '';
if ($date !== '') {
    $metaParts[] = '<time>' . esc_html($date) . '</time>';
}
$readtime = $page['meta']['readtime'] ?? '';
if ($readtime !== '') {
    $metaParts[] = '<span>' . esc_html($readtime) . ' min read</span>';
}
$postMetaMarkup = !empty($metaParts)
    ? '<div class="post-meta flex items-center gap-4">' . implode('<span>•</span>', $metaParts) . '</div>'
    : '';

// Auth button is driven by CMS session state.
$authHtml = $isAdmin
    ? '<button id="admin-logout-btn" class="text-sm text-gray-700 hover:text-gray-900 font-medium nav-link">Logout</button>'
    : '<a href="/login" class="text-sm text-gray-700 hover:text-gray-900 font-medium nav-link">Login</a>';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= esc_html($siteName) ?></title>
    <?= $keywordsMeta ?>
    <link rel="stylesheet" href="<?= theme_asset('tailwind.min.css') ?>">
    <?= $headAssets ?>
</head>
<body class="bg-[#FAFAFA] text-gray-800 antialiased">
    <!-- Reading Progress Bar -->
    <div id="reading-progress"></div>

    <!-- Post Header with Gradient -->
    <header class="post-header text-white py-16 sm:py-24">
        <div class="max-w-3xl mx-auto px-6 sm:px-12">
            <a href="/" class="inline-flex items-center gap-2 text-white/80 hover:text-white text-sm mb-6">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Home
            </a>

            <?= $iconMarkup ?>

            <h1 class="text-4xl sm:text-5xl font-bold mb-4 leading-tight">
                <?= $postTitle ?>
            </h1>

            <?= $descriptionMarkup ?>

            <?= $postMetaMarkup ?>
        </div>
    </header>

    <!-- Post Content -->
    <main class="py-12 sm:py-16">
        <article class="max-w-3xl mx-auto px-6 sm:px-12">
            <!-- $viewContent is the rendered HTML from view.php + markdown parser. -->
            <?= $viewContent ?>
        </article>
    </main>

    <!-- Footer -->
    <footer class="border-t border-gray-200 bg-white">
        <div class="max-w-3xl mx-auto px-6 sm:px-12 py-8 flex items-center justify-between">
            <!-- Copyright/Branding. I'd appreciate it if you left this in your themes. -->
            <p class="text-sm text-gray-500">
                Powered by <a href="https://flintcms.com" target="_blank" title="A flat file CMS built on Markdown" class="text-gray-700 hover:text-gray-900 font-medium">Flint</a>
            </p>
            <?= $authHtml ?>
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
