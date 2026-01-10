<?php
/**
 * Motion post layout template.
 *
 * Posts have a dedicated banner header, so we keep the post-specific UI here.
 * Data comes from $page (frontmatter + content) and $site (global config).
 */
$siteName = $site['name'] ?? '';
// page_meta() reads frontmatter from ThemeContext and escapes it.
$pageTitle = page_meta('title', $siteName);
// Keywords are optional metadata from the markdown frontmatter.
$keywords = page_meta('keywords');
$hasKeywords = $keywords !== '';
// Post title falls back to a safe default if frontmatter is missing.
$postTitle = page_meta('title', 'Untitled Post');
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
    </main>

    <!-- Footer -->
    <footer class="border-t border-gray-200 bg-white">
        <div class="max-w-3xl mx-auto px-6 sm:px-12 py-8 flex items-center justify-between">
            <!-- Copyright/Branding. I'd appreciate it if you left this in your themes. -->
            <p class="text-sm text-gray-500">
                Powered by <a href="https://flintcms.com" target="_blank" title="A flat file CMS built on Markdown" class="text-gray-700 hover:text-gray-900 font-medium">Flint</a>
            </p>
            <?php if ($showLogout) : ?>
                <div class="flex items-center gap-4">
                    <a href="<?= esc_html($adminUrl) ?>" class="text-sm text-gray-700 hover:text-gray-900 font-medium nav-link">Admin</a>
                    <button id="admin-logout-btn" class="text-sm text-gray-700 hover:text-gray-900 font-medium nav-link">Logout</button>
                </div>
            <?php else : ?>
                <a href="<?= esc_html($loginUrl) ?>" class="text-sm text-gray-700 hover:text-gray-900 font-medium nav-link">Login</a>
            <?php endif; ?>
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
