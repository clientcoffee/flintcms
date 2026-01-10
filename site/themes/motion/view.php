<?php
/**
 * Motion view template.
 *
 * The CMS passes $page, $content, $themeConfig, and $site into the theme.
 * We normalize those values here so the markup below is clean and predictable.
 */
$contentType = strtolower((string)($page['meta']['type'] ?? ''));
// Frontmatter drives layout decisions like "post" vs "page".
// Posts use layout-post.php, so we suppress the shared banner/header here.
$allowBanner = $contentType !== 'post';
// Banner can come from the page frontmatter or the theme default.
$bannerUrl = $page['meta']['banner'] ?? ($themeConfig['settings']['default_banner'] ?? '');
// Theme config is loaded from site/themes/motion/config.php by the CMS.
$hasBanner = $allowBanner && $bannerUrl !== '';
// page_meta() pulls from ThemeContext and escapes for safe output.
$pageTitle = page_meta('title');
// page_meta() reads from ThemeContext and escapes output.
$hasTitle = $pageTitle !== '';
$renderPageHeader = $contentType !== 'post' && $hasTitle;

// Theme assets expect /site prefix for relative uploads.
if ($hasBanner && !preg_match('/^https?:\/\//', $bannerUrl)) {
    // Relative URLs from frontmatter should point to /site/uploads.
    $bannerUrl = '/site' . $bannerUrl;
}

// Icons are stored as slugs in frontmatter and mapped to emoji.
$iconEmoji = '';
if (!empty($page['meta']['icon'])) {
    // Emoji slugs keep frontmatter readable for non-technical users.
    $iconEmoji = getEmojiFromSlug($page['meta']['icon']);
}

// Offset the icon when it sits on top of a banner.
$iconWrapperClass = 'mb-3 flex items-start relative z-10 motion-page-icon-wrap';
if ($hasBanner) {
    // The offset aligns the icon with the banner image.
    $iconWrapperClass .= ' motion-page-icon-wrap--offset';
}

// Optional description text below the page title.
$pageDescription = $page['meta']['description'] ?? '';
// Description is optional and rendered only when present.
$hasDescription = $pageDescription !== '';
?>
<!-- Banner output (optional). -->
<?php if ($hasBanner) : ?>
    <div class="page-banner mb-8 motion-page-banner">
        <img src="<?= esc_html($bannerUrl) ?>" alt="<?= $pageTitle ?>" class="w-full object-cover motion-page-banner__image" />
    </div>
<?php endif; ?>

<!-- Page header output (optional). -->
<?php if ($renderPageHeader) : ?>
    <div class="mb-8 sm:mb-12">
        <?php if ($iconEmoji !== '') : ?>
            <div class="<?= esc_html($iconWrapperClass) ?>">
                <span class="page-icon text-7xl sm:text-8xl leading-none align-top"><?= $iconEmoji ?></span>
            </div>
        <?php endif; ?>

        <div class="flex flex-wrap items-start gap-3 mb-3">
            <h1 class="font-bold text-gray-900 leading-tight motion-page-title"><?= $pageTitle ?></h1>
        </div>

        <?php if ($hasDescription) : ?>
            <p class="text-lg text-gray-600 leading-relaxed"><?= esc_html($pageDescription) ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- $content is the parsed HTML from the CMS markdown parser. -->
<article id="content-display" class="motion-content">
    <?= $content ?>
</article>
