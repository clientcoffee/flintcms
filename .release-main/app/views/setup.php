<?php
$setupResult = $setupResult ?? [];
$formData = $formData ?? [
    'site_name' => 'Flint',
    'site_website' => '',
    'theme' => 'motion',
    'admin_email' => '',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flint Setup</title>
    <link rel="stylesheet" href="/assets/css/tailwind.min.css">
    <style>
        .confetti {
            position: fixed;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
            z-index: 50;
        }

        .confetti-piece {
            position: absolute;
            top: -30px;
            animation: confetti-fall linear forwards;
            will-change: transform, opacity;
        }

        .confetti-shape {
            display: block;
            width: 100%;
            height: 100%;
            border-radius: 2px;
            opacity: 0.9;
            animation: confetti-flutter ease-in-out infinite;
            will-change: transform;
        }

        @keyframes confetti-fall {
            0% {
                transform: translate3d(0, -40px, 0) scale(0.5);
                opacity: 0;
            }
            12% {
                transform: translate3d(0, 0, 0) scale(1);
                opacity: 1;
            }
            50% {
                opacity: 1;
            }
            66% {
                opacity: 0;
            }
            100% {
                transform: translate3d(var(--drift-x), calc(100vh + 60px), 0) scale(1);
                opacity: 0;
            }
        }

        @keyframes confetti-flutter {
            0% {
                transform: translateX(0) rotate(0deg);
            }
            50% {
                transform: translateX(var(--sway-x)) rotate(180deg);
            }
            100% {
                transform: translateX(0) rotate(360deg);
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">Welcome to Flint</h2>
        <p class="mt-2 text-center text-sm text-gray-600">The drop-in, flat-file CMS.</p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div id="setup-card" class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10 relative z-10">
            <?php if (!empty($setupResult)) : ?>
                <div class="mb-6 rounded-lg border px-4 py-3 text-sm <?= !empty($setupResult['success']) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700' ?>">
                    <?= render_flash_markdown($setupResult['message'] ?? 'Setup status unknown.') ?>
                </div>
                <?php if (!empty($setupResult['magic_link'])) : ?>
                    <div class="mb-6 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-xs text-yellow-800">
                        <p class="font-semibold mb-2">Email delivery failed. Use this magic link instead:</p>
                        <a href="<?= htmlspecialchars($setupResult['magic_link'], ENT_QUOTES) ?>" class="text-yellow-900 underline break-all">
                            <?= htmlspecialchars($setupResult['magic_link'], ENT_QUOTES) ?>
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($setupResult['success'])) : ?>
                <?php
                $homeUrl = $formData['site_website'] ?? '';
                $homeUrl = $homeUrl !== '' ? $homeUrl : '/';
                ?>
                <div class="mt-6 text-center">
                    <h3 class="text-xl font-semibold text-gray-900">Congratulations!</h3>
                    <p class="mt-2 text-sm text-gray-600">Your new site is ready. Time to make it yours.</p>
                    <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES) ?>" class="mt-4 inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Visit My New Site!
                    </a>
                </div>
            <?php else : ?>
                <form class="space-y-6" action="/" method="POST">
                    <div>
                        <label for="site_name" class="block text-sm font-medium text-gray-700">Site Name</label>
                        <div class="mt-1">
                            <input id="site_name" name="site_name" type="text" required value="<?= htmlspecialchars($formData['site_name'], ENT_QUOTES) ?>" class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                    </div>

                    <div>
                        <label for="site_website" class="block text-sm font-medium text-gray-700">Website</label>
                        <div class="mt-1">
                            <input id="site_website" name="site_website" type="url" required value="<?= htmlspecialchars($formData['site_website'], ENT_QUOTES) ?>" class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                    </div>

                    <div>
                        <label for="theme" class="block text-sm font-medium text-gray-700">Theme</label>
                        <div class="mt-1">
                            <select id="theme" name="theme" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                <option value="motion" <?= $formData['theme'] === 'motion' ? 'selected' : '' ?>>Motion</option>
                                <option value="lost-in-thought" <?= $formData['theme'] === 'lost-in-thought' ? 'selected' : '' ?>>Lost in Thought</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="admin_email" class="block text-sm font-medium text-gray-700">Admin Email</label>
                        <div class="mt-1">
                            <input id="admin_email" name="admin_email" type="email" required value="<?= htmlspecialchars($formData['admin_email'], ENT_QUOTES) ?>" class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        <p class="mt-1 text-xs text-gray-500">We will send a magic link to set your admin password.</p>
                    </div>

                    <div>
                        <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Complete Setup
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!empty($setupResult['success'])) : ?>
        <div id="confetti" class="confetti"></div>
        <script>
            (function () {
                if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    return;
                }

                var root = document.getElementById('confetti');
                if (!root) {
                    return;
                }

                var colors = ['#f87171', '#fb923c', '#fbbf24', '#34d399', '#60a5fa', '#a78bfa'];
                var total = 80;

                for (var i = 0; i < total; i++) {
                    var piece = document.createElement('span');
                    var shape = document.createElement('span');
                    var size = Math.floor(Math.random() * 7) + 6;
                    var driftX = Math.random() * 120 - 60;
                    var swayX = (Math.random() * 50 + 20) * (Math.random() < 0.5 ? -1 : 1);
                    var fallDuration = Math.random() * 1 + 3;
                    var flutterDuration = Math.random() * 2 + 2.5;
                    piece.className = 'confetti-piece';
                    shape.className = 'confetti-shape';
                    piece.style.left = Math.random() * 100 + 'vw';
                    piece.style.width = size + 'px';
                    piece.style.height = Math.round(size * 1.4) + 'px';
                    shape.style.backgroundColor = colors[i % colors.length];
                    piece.style.animationDelay = (Math.random() * 0.6) + 's';
                    piece.style.animationDuration = fallDuration + 's';
                    piece.style.setProperty('--drift-x', driftX + 'px');
                    piece.style.setProperty('--sway-x', swayX + 'px');
                    shape.style.animationDuration = flutterDuration + 's';
                    shape.style.animationDelay = (Math.random() * 0.6) + 's';
                    piece.appendChild(shape);
                    root.appendChild(piece);
                }

                window.setTimeout(function () {
                    if (root.parentNode) {
                        root.parentNode.removeChild(root);
                    }
                }, 3000);
            })();
        </script>
    <?php endif; ?>
</body>
</html>
