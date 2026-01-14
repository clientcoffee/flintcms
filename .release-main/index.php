<?php

/**
 * Entry file exported to dist/ so deployments can point the webroot at dist/.
 * This file resolves the correct app entry depending on where it was copied.
 */

$targetEntry = __DIR__ . '/app/index.php';

if (file_exists($targetEntry)) {
    require $targetEntry;
    return;
}

$fallbackEntry = __DIR__ . '/index.php';
if (file_exists($fallbackEntry)) {
    require $fallbackEntry;
    return;
}

throw new RuntimeException('Unable to locate the app entry point.');
