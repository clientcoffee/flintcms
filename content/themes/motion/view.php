<!-- Banner Image -->
<?php
$bannerUrl = $page['meta']['banner'] ?? ($themeConfig['settings']['default_banner'] ?? '');
?>
<?php if (!empty($bannerUrl)) : ?>
    <?php
    // Handle relative URLs
    if (!preg_match('/^https?:\/\//', $bannerUrl)) {
        $bannerUrl = '/content' . $bannerUrl; // /uploads/img.jpg becomes /content/uploads/img.jpg
    }
    ?>
    <div class="page-banner mb-8 -mx-6 sm:-mx-12">
        <img src="<?= htmlspecialchars($bannerUrl) ?>" alt="<?= htmlspecialchars($page['meta']['title'] ?? '') ?>" class="w-full h-64 object-cover rounded-lg" />
    </div>
<?php endif; ?>

<!-- Page Title with Edit Controls -->
<?php if (!empty($page['meta']['title'])) : ?>
    <div class="mb-8 sm:mb-12">
        <div class="flex items-center gap-3">
            <?php if (!empty($page['meta']['icon'])) : ?>
                <?php $iconEmoji = getEmojiFromSlug($page['meta']['icon']); ?>
                <?php if ($iconEmoji) : ?>
                    <span class="page-icon text-4xl sm:text-5xl"><?= $iconEmoji ?></span>
                <?php endif; ?>
            <?php endif; ?>
            <h1 class="text-4xl sm:text-5xl font-bold text-gray-900 mb-3 leading-tight">
                <?= htmlspecialchars($page['meta']['title']) ?>
            </h1>
        </div>

        <?php if (!empty($page['meta']['description'])) : ?>
            <p class="text-lg text-gray-600 leading-relaxed">
                <?= htmlspecialchars($page['meta']['description']) ?>
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Content Area with Motion Theme Styling -->
<article id="content-display" class="motion-content">
    <style>
        .motion-content {
            color: rgb(55, 53, 47);
            line-height: 1.6;
        }

        .motion-content h1 {
            font-size: 2.25rem;
            font-weight: 700;
            margin-top: 2rem;
            margin-bottom: 0.5rem;
            line-height: 1.2;
            color: rgb(55, 53, 47);
        }

        .motion-content h2 {
            font-size: 1.875rem;
            font-weight: 600;
            margin-top: 1.75rem;
            margin-bottom: 0.5rem;
            line-height: 1.3;
            color: rgb(55, 53, 47);
        }

        .motion-content h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
            line-height: 1.4;
            color: rgb(55, 53, 47);
        }

        .motion-content p {
            font-size: 1rem;
            line-height: 1.7;
            margin-bottom: 0.75rem;
            color: rgb(55, 53, 47);
        }

        .motion-content strong {
            font-weight: 600;
            color: rgb(55, 53, 47);
        }

        .motion-content a {
            color: rgb(55, 53, 47);
            text-decoration: underline;
            text-decoration-color: rgba(55, 53, 47, 0.4);
            text-underline-offset: 2px;
            transition: all 0.15s ease;
        }

        .motion-content a:hover {
            text-decoration-color: rgb(55, 53, 47);
            background-color: rgba(55, 53, 47, 0.08);
        }

        .motion-content li {
            font-size: 1rem;
            line-height: 1.7;
            padding: 0.25rem 0;
            color: rgb(55, 53, 47);
            list-style-position: outside;
            margin-left: 1.5rem;
        }

        .motion-content ul {
            margin: 0.5rem 0 1rem 0;
        }

        .motion-content li::marker {
            color: rgba(55, 53, 47, 0.4);
        }

        .motion-embed {
            position: relative;
            padding-top: 56.25%;
            background: rgba(148, 163, 184, 0.15);
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.35);
        }

        .motion-embed iframe {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            border: 0;
        }

        .motion-lightbox::backdrop {
            background: rgba(15, 23, 42, 0.65);
        }

    </style>

    <?= $content ?>
</article>
