<?php
$title = $title ?? 'Magic Link';
$success = $success ?? false;
$message = $message ?? '';
$password = $password ?? null;

ob_start();
?>
<div class="min-h-screen bg-[#FAFAFA] flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-lg">
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <p class="text-sm uppercase tracking-[0.2em] text-gray-400">Flint</p>
                    <h1 class="text-2xl font-semibold text-gray-900">Magic Link</h1>
                </div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?= $success ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>">
                    <?= $success ? 'Verified' : 'Invalid' ?>
                </span>
            </div>

            <p class="text-sm text-gray-600 mb-6"><?= htmlspecialchars($message, ENT_QUOTES) ?></p>

            <?php if ($success && $password) : ?>
                <div class="border border-gray-200 rounded-xl bg-gray-50 p-4">
                    <p class="text-xs uppercase tracking-[0.2em] text-gray-400 mb-2">New Admin Password</p>
                    <div class="flex items-center justify-between gap-3">
                        <code id="generated-password" class="text-sm font-mono text-gray-900 break-all">
                            <?= htmlspecialchars($password, ENT_QUOTES) ?>
                        </code>
                        <button id="copy-password" type="button" class="inline-flex items-center gap-2 px-3 py-2 border border-gray-200 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-100">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <rect x="9" y="9" width="13" height="13" rx="2"></rect>
                                <rect x="2" y="2" width="13" height="13" rx="2"></rect>
                            </svg>
                            Copy
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-3">This password is only shown once. Save it now.</p>
                </div>
                <div class="mt-6 flex items-center justify-between">
                    <a href="/admin" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700">Go to Admin</a>
                    <a href="/" class="text-sm text-gray-500 hover:text-gray-700">View site</a>
                </div>
            <?php elseif ($success) : ?>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    You are signed in. Head to the admin dashboard to continue.
                </div>
                <div class="mt-6 flex items-center justify-between">
                    <a href="/admin" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700">Go to Admin</a>
                    <a href="/" class="text-sm text-gray-500 hover:text-gray-700">View site</a>
                </div>
            <?php else : ?>
                <div class="mt-6 flex items-center justify-between">
                    <a href="/login" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700">Try login</a>
                    <a href="/" class="text-sm text-gray-500 hover:text-gray-700">Back to site</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($success && $password) : ?>
<script>
    const copyButton = document.getElementById("copy-password");
    const passwordValue = document.getElementById("generated-password")?.textContent?.trim() || "";

    copyButton?.addEventListener("click", async () => {
        if (!passwordValue) {
            return;
        }

        try {
            await navigator.clipboard.writeText(passwordValue);
            copyButton.innerHTML = "Copied";
        } catch (error) {
            const tempInput = document.createElement("input");
            tempInput.value = passwordValue;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand("copy");
            document.body.removeChild(tempInput);
            copyButton.innerHTML = "Copied";
        }
    });
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layouts/admin.php';
