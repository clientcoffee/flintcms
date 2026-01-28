<?php

/**
 * Motion section layout.
 *
 * Used for sub-directory index pages to append section navigation.
 */

$childrenNavHtml = '';
$subdirNavHtml = '';

if (class_exists(\Components\ChildrenNav::class)) {
    $childrenNavHtml = \Components\ChildrenNav::render([], '');
}

if (class_exists(\Components\SubdirNav::class)) {
    $subdirNavHtml = \Components\SubdirNav::render([], '');
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
