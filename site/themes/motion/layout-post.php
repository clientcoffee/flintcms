<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= page_meta('title', $site['name']) ?> | <?= esc_html($site['name']) ?></title>
    <?php $keywords = page_meta('keywords'); ?>
    <?php if ($keywords !== '') : ?>
    <meta name="keywords" content="<?= $keywords ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= theme_asset('tailwind.min.css') ?>">
    <?php render_assets('head'); ?>
    <?php theme_styles(); ?>
    <link rel="stylesheet" href="<?= theme_asset('motion.css') ?>">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;
        }

        /* Post-specific styles */
        .post-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .post-meta {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.9);
        }

        /* Reading progress bar */
        #reading-progress {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            width: 0%;
            z-index: 9999;
        }
    </style>
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

            <?php if (!empty($page['meta']['icon'])) : ?>
                <?php $iconEmoji = getEmojiFromSlug($page['meta']['icon']); ?>
                <?php if ($iconEmoji) : ?>
                    <span class="text-6xl mb-4 block"><?= $iconEmoji ?></span>
                <?php endif; ?>
            <?php endif; ?>

            <h1 class="text-4xl sm:text-5xl font-bold mb-4 leading-tight">
                <?= page_meta('title', 'Untitled Post') ?>
            </h1>

            <?php if (!empty($page['meta']['description'])) : ?>
                <p class="text-xl text-white/90 mb-6 leading-relaxed">
                    <?= esc_html($page['meta']['description']) ?>
                </p>
            <?php endif; ?>

            <div class="post-meta flex items-center gap-4">
                <?php if (!empty($page['meta']['author'])) : ?>
                    <span>By <?= esc_html($page['meta']['author']) ?></span>
                <?php endif; ?>
                <?php if (!empty($page['meta']['date'])) : ?>
                    <span>•</span>
                    <time><?= esc_html($page['meta']['date']) ?></time>
                <?php endif; ?>
                <?php if (!empty($page['meta']['readtime'])) : ?>
                    <span>•</span>
                    <span><?= esc_html($page['meta']['readtime']) ?> min read</span>
                <?php endif; ?>
            </div>
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
            <?php if (!empty($isAdmin)) : ?>
                <button id="admin-logout-btn" class="text-sm text-gray-700 hover:text-gray-900 font-medium nav-link">Logout</button>
            <?php else : ?>
                <a href="/login" class="text-sm text-gray-700 hover:text-gray-900 font-medium nav-link">Login</a>
            <?php endif; ?>
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
