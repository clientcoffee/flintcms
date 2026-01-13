<?php

/**
 * Motion section layout.
 *
 * Used for sub-directory index pages to append section navigation.
 */

$componentsDir = isset(\Flint\Paths::$siteComponentsDir) ? \Flint\Paths::$siteComponentsDir : '';
$childrenNavHtml = '';
$subdirNavHtml = '';

if ($componentsDir !== '' && is_dir($componentsDir)) {
    $childrenComponentPath = $componentsDir . '/ChildrenNav.php';
    if (is_file($childrenComponentPath)) {
        require_once $childrenComponentPath;
        if (class_exists(\Components\ChildrenNav::class)) {
            $childrenNavHtml = \Components\ChildrenNav::render([], '');
        }
    }

    $subdirComponentPath = $componentsDir . '/SubdirNav.php';
    if (is_file($subdirComponentPath)) {
        require_once $subdirComponentPath;
        if (class_exists(\Components\SubdirNav::class)) {
            $subdirNavHtml = \Components\SubdirNav::render([], '');
        }
    }
}

if ($childrenNavHtml !== '' || $subdirNavHtml !== '') {
    ob_start();
    ?>
    <div class="motion-section-nav">
        <?= $childrenNavHtml ?>
        <?= $subdirNavHtml ?>
    </div>
    <?php
    $viewContent .= ob_get_clean();
}

include __DIR__ . '/layout.php';
