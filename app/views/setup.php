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
</head>
<body class="bg-gray-50 min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">Welcome to Flint</h2>
        <p class="mt-2 text-center text-sm text-gray-600">The drop-in, flat-file CMS.</p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
            <?php if (!empty($setupResult)) : ?>
                <div class="mb-6 rounded-lg border px-4 py-3 text-sm <?= !empty($setupResult['success']) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700' ?>">
                    <?= htmlspecialchars($setupResult['message'] ?? 'Setup status unknown.', ENT_QUOTES) ?>
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

            <?php if (empty($setupResult['success'])) : ?>
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
</body>
</html>
