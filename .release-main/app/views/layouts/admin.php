<?php

/** @var string $title */
/** @var string $content */
/** @var string $bodyClass */
$title = $title ?? 'Admin';
$bodyClass = $bodyClass ?? 'bg-[#FAFAFA] text-gray-800 antialiased';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES) ?></title>
    <link rel="stylesheet" href="/assets/css/tailwind.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES) ?>">
    <?= $content ?>
</body>
</html>
