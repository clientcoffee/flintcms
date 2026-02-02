<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Password required — <?= htmlspecialchars($title, ENT_QUOTES) ?></title>
    <link rel="stylesheet" href="/assets/css/tailwind.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4 py-12">
    <div class="max-w-lg w-full space-y-8 bg-white rounded-3xl border border-gray-100 shadow-xl p-8">
        <div>
            <p class="text-xs uppercase tracking-[0.4em] text-gray-500">World in Brief</p>
            <h1 class="mt-3 text-3xl font-semibold text-gray-900">Protected area</h1>
            <p class="mt-2 text-sm text-gray-600">
                The <strong><?= htmlspecialchars($displayDir, ENT_QUOTES) ?></strong> section is locked with a password. Enter it below to continue.
            </p>
        </div>
        <?php if ($error) : ?>
            <p class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-4 py-2">
                <?= htmlspecialchars($error, ENT_QUOTES) ?>
            </p>
        <?php endif; ?>
        <form method="POST" action="<?= $formAction ?>" class="space-y-4">
            <input
                name="walled_garden_password"
                type="password"
                placeholder="Password"
                autocomplete="current-password"
                required
                class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-base focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
            >
            <button type="submit" class="w-full bg-indigo-600 text-white font-semibold rounded-2xl px-4 py-3 hover:bg-indigo-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300">
                Unlock section
            </button>
        </form>
        <p class="text-xs text-gray-500">Need a password? Contact the site administrator.</p>
    </div>
</body>
</html>
