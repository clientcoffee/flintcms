<?php
// Load quotes from JSON file
$quotesPath = __DIR__ . '/quotes.json';
$quotes = [];

if (file_exists($quotesPath)) {
    $quotesContent = file_get_contents($quotesPath);
    $quotes = json_decode($quotesContent, true);
}

// Select random quote
$randomQuote = '';
if (!empty($quotes) && is_array($quotes)) {
    $randomQuote = $quotes[array_rand($quotes)];
}

$siteName = htmlspecialchars($site['name'] ?? 'Flint', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | <?= $siteName ?></title>
    <link rel="stylesheet" href="/themes/motion/tailwind.min.css">
    <link rel="stylesheet" href="<?= theme_asset('motion.css') ?>">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap');
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        .float-animation {
            animation: float 3s ease-in-out infinite;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 min-h-screen flex items-center justify-center px-6">
    <div class="max-w-2xl w-full">
        <!-- 404 Header -->
        <div class="text-center mb-8">
            <div class="float-animation inline-block">
                <h1 class="text-9xl font-black text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 mb-4">
                    404
                </h1>
            </div>
            <p class="text-2xl font-semibold text-gray-800 mb-2">
                Well, this is awkward...
            </p>
            <p class="text-gray-600">
                The page you're looking for has achieved enlightenment and transcended to another dimension.
            </p>
        </div>

        <!-- Random Quote Card -->
        <?php if ($randomQuote) : ?>
        <div class="bg-white/80 backdrop-blur-sm rounded-2xl border border-gray-200 shadow-lg p-6 mb-8">
            <div class="flex items-start gap-3">
                <svg class="w-8 h-8 text-indigo-500 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/>
                </svg>
                <div class="flex-1">
                    <p class="text-lg text-gray-800 italic leading-relaxed">
                        "<?= htmlspecialchars($randomQuote, ENT_QUOTES) ?>"
                    </p>
                    <p class="text-sm text-gray-500 mt-3">
                        — Wisdom for the wayward traveler
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Navigation Options -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="/" class="inline-flex items-center justify-center gap-2 bg-indigo-600 text-white px-6 py-3 rounded-xl font-semibold hover:bg-indigo-700 transition-all hover:scale-105 shadow-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                Take Me Home
            </a>
            <button onclick="history.back()" class="inline-flex items-center justify-center gap-2 bg-white text-gray-700 px-6 py-3 rounded-xl font-semibold hover:bg-gray-50 transition-all border border-gray-300 shadow">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Go Back
            </button>
        </div>

        <!-- Cheeky Footer Message -->
        <p class="text-center text-sm text-gray-500 mt-8">
            Lost? Even the greatest adventurers take wrong turns. 🗺️
        </p>
    </div>
</body>
</html>
