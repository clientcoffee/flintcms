<?php
$siteName = $site['name'] ?? '';
$pageTitle = page_meta('title', $siteName);
$keywords = page_meta('keywords');
$keywordsMeta = $keywords !== '' ? '<meta name="keywords" content="' . $keywords . '">' : '';
$postTitle = page_meta('title', 'Untitled Post');

$iconEmoji = '';
if (!empty($page['meta']['icon'])) {
    $iconEmoji = getEmojiFromSlug($page['meta']['icon']);
}
$iconMarkup = $iconEmoji !== '' ? '<span class="text-6xl mb-4 block">' . $iconEmoji . '</span>' : '';

$postDescription = $page['meta']['description'] ?? '';
$descriptionMarkup = $postDescription !== ''
    ? '<p class="text-xl text-white/90 mb-6 leading-relaxed">' . esc_html($postDescription) . '</p>'
    : '';

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

$authHtml = $isAdmin
    ? '<button id="admin-logout-btn" class="text-sm text-gray-700 hover:text-gray-900 font-medium nav-link">Logout</button>'
    : '<a href="/login" class="text-sm text-gray-700 hover:text-gray-900 font-medium nav-link">Login</a>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= esc_html($siteName) ?></title>
    <?= $keywordsMeta ?>
    <link rel="stylesheet" href="<?= theme_asset('tailwind.min.css') ?>">
    <?php render_assets('head'); ?>
    <?php theme_styles(); ?>
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

    <?php render_assets('foot'); ?>

    <!-- Reading progress script -->
    <script>
        window.addEventListener('scroll', () => {
            const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (winScroll / height) * 100;
            document.getElementById('reading-progress').style.width = scrolled + '%';
        });
    </script>
    <?php theme_scripts(); ?>
</body>
</html>
