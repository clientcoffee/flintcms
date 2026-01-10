<?php
/**
 * Motion view template.
 *
 * The CMS passes $page, $content, $themeConfig, and $site into the theme.
 * We normalize those values here so the markup below is clean and predictable.
 */
$contentType = strtolower((string)($page['meta']['type'] ?? ''));
// Posts use layout-post.php, so we suppress the shared banner/header here.
$allowBanner = $contentType !== 'post';
// Banner can come from the page frontmatter or the theme default.
$bannerUrl = $page['meta']['banner'] ?? ($themeConfig['settings']['default_banner'] ?? '');
$hasBanner = $allowBanner && $bannerUrl !== '';
// page_meta() pulls from ThemeContext and escapes for safe output.
$pageTitle = page_meta('title');
$hasTitle = $pageTitle !== '';
$renderPageHeader = $contentType !== 'post' && $hasTitle;

// Theme assets expect /site prefix for relative uploads.
if ($hasBanner && !preg_match('/^https?:\/\//', $bannerUrl)) {
    $bannerUrl = '/site' . $bannerUrl;
}

// Icons are stored as slugs in frontmatter and mapped to emoji.
$iconEmoji = '';
if (!empty($page['meta']['icon'])) {
    $iconEmoji = getEmojiFromSlug($page['meta']['icon']);
}

// Offset the icon when it sits on top of a banner.
$iconWrapperClass = 'mb-3 flex items-start relative z-10 motion-page-icon-wrap';
if ($hasBanner) {
    $iconWrapperClass .= ' motion-page-icon-wrap--offset';
}

// Build the optional banner and header HTML once, then output below.
$bannerMarkup = $hasBanner
    ? '<div class="page-banner mb-8 motion-page-banner"><img src="' . esc_html($bannerUrl) . '" alt="' . $pageTitle . '" class="w-full object-cover motion-page-banner__image" /></div>'
    : '';

$iconMarkup = $iconEmoji !== ''
    ? '<div class="' . $iconWrapperClass . '"><span class="page-icon text-7xl sm:text-8xl leading-none align-top">' . $iconEmoji . '</span></div>'
    : '';

$pageDescription = $page['meta']['description'] ?? '';
$pageDescriptionMarkup = $pageDescription !== ''
    ? '<p class="text-lg text-gray-600 leading-relaxed">' . esc_html($pageDescription) . '</p>'
    : '';

$pageHeaderMarkup = '';
if ($renderPageHeader) {
    $pageHeaderMarkup = '<div class="mb-8 sm:mb-12">' .
        $iconMarkup .
        '<div class="flex flex-wrap items-start gap-3 mb-3"><h1 class="font-bold text-gray-900 leading-tight motion-page-title">' . $pageTitle . '</h1></div>' .
        $pageDescriptionMarkup .
        '</div>';
}
?>
<?= $bannerMarkup ?>

<?= $pageHeaderMarkup ?>

<!-- $content is the parsed HTML from the CMS markdown parser. -->
<article id="content-display" class="motion-content">
    <?= $content ?>
</article>
