<!-- Banner Image -->
<?php
$contentType = strtolower((string)($page['meta']['type'] ?? ''));
$allowBanner = $contentType !== 'post';
$bannerUrl = $page['meta']['banner'] ?? ($themeConfig['settings']['default_banner'] ?? '');
$hasBanner = $allowBanner && !empty($bannerUrl);
?>
<?php if ($hasBanner) : ?>
    <?php
    // Handle relative URLs
    if (!preg_match('/^https?:\/\//', $bannerUrl)) {
        $bannerUrl = '/site' . $bannerUrl; // /uploads/img.jpg becomes /site/uploads/img.jpg
    }
    ?>
    <div class="page-banner mb-8" style="margin-left: calc(50% - 50vw); margin-right: calc(50% - 50vw);">
        <img src="<?= esc_html($bannerUrl) ?>" alt="<?= page_meta('title') ?>" class="w-full object-cover" style="height: clamp(12rem, 32vw, 20rem);" />
    </div>
<?php endif; ?>

<!-- Page Title with Edit Controls -->
<?php if (!empty($page['meta']['title'])) : ?>
    <div class="mb-8 sm:mb-12">
        <?php if (!empty($page['meta']['icon'])) : ?>
            <?php $iconEmoji = getEmojiFromSlug($page['meta']['icon']); ?>
            <?php if ($iconEmoji) : ?>
                <?php $iconStyle = $hasBanner ? 'transform: translateY(-60%);' : ''; ?>
                <div class="mb-3 flex items-start relative z-10" style="<?= $iconStyle ?>">
                    <span class="page-icon text-7xl sm:text-8xl leading-none align-top"><?= $iconEmoji ?></span>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <div class="flex flex-wrap items-start gap-3 mb-3">
            <h1 class="font-bold text-gray-900 leading-tight" style="font-size: clamp(1.9rem, 3vw, 2.55rem);">
                <?= page_meta('title') ?>
            </h1>
        </div>

        <?php if (!empty($page['meta']['description'])) : ?>
            <p class="text-lg text-gray-600 leading-relaxed">
                <?= page_meta('description') ?>
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
