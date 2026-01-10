<?php
$contentType = strtolower((string)($page['meta']['type'] ?? ''));
$allowBanner = $contentType !== 'post';
$bannerUrl = $page['meta']['banner'] ?? ($themeConfig['settings']['default_banner'] ?? '');
$hasBanner = $allowBanner && $bannerUrl !== '';
$pageTitle = page_meta('title');
$hasTitle = $pageTitle !== '';
$renderPageHeader = $contentType !== 'post' && $hasTitle;

if ($hasBanner && !preg_match('/^https?:\/\//', $bannerUrl)) {
    $bannerUrl = '/site' . $bannerUrl;
}

$iconEmoji = '';
if (!empty($page['meta']['icon'])) {
    $iconEmoji = getEmojiFromSlug($page['meta']['icon']);
}

$iconWrapperClass = 'mb-3 flex items-start relative z-10 motion-page-icon-wrap';
if ($hasBanner) {
    $iconWrapperClass .= ' motion-page-icon-wrap--offset';
}

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

<article id="content-display" class="motion-content">
    <?= $content ?>
</article>
