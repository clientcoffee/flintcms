<?php
/** @var bool $showDetailedErrors */
/** @var string $errorReference */
/** @var string $errorClass */
/** @var string $errorMessage */
/** @var string $functionName */
/** @var string $relativeFile */
/** @var int $errorLine */
/** @var array $traceEntries */

$title = 'Error - Flint';
$bodyClass = 'bg-[#FAFAFA] text-gray-800 antialiased';

ob_start();
?>
<div class="min-h-screen bg-gradient-to-br from-indigo-500 via-purple-600 to-fuchsia-500 p-6 flex items-center justify-center">
    <?php if ($showDetailedErrors) : ?>
        <div class="w-full max-w-5xl bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="bg-gradient-to-r from-rose-500 to-pink-500 px-8 py-6 text-white">
                <h1 class="text-2xl font-bold">Application Error</h1>
                <p class="text-sm text-white/90">An error occurred while processing your request. Details below.</p>
            </div>
            <div class="p-8 space-y-6">
                <div>
                    <span class="inline-flex items-center rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">
                        <?= htmlspecialchars($errorClass, ENT_QUOTES) ?>
                    </span>
                    <div class="mt-3 rounded-lg border-l-4 border-amber-400 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">
                        <?= htmlspecialchars($errorMessage, ENT_QUOTES) ?>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs uppercase tracking-wide text-slate-400">Function/Method</p>
                        <code class="text-sm text-slate-800"><?= htmlspecialchars($functionName, ENT_QUOTES) ?></code>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs uppercase tracking-wide text-slate-400">File</p>
                        <code class="text-sm text-slate-800"><?= htmlspecialchars($relativeFile, ENT_QUOTES) ?></code>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs uppercase tracking-wide text-slate-400">Line Number</p>
                        <code class="text-sm text-slate-800"><?= htmlspecialchars((string)$errorLine, ENT_QUOTES) ?></code>
                    </div>
                </div>

                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-2">Stack Trace</p>
                    <div class="rounded-xl bg-slate-900 text-slate-100 p-4 text-xs font-mono overflow-x-auto space-y-2">
                        <?php foreach ($traceEntries as $entry) : ?>
                            <?php if ($entry['type'] === 'parsed') : ?>
                                <div>
                                    <span class="text-slate-400"><?= htmlspecialchars($entry['number'], ENT_QUOTES) ?></span>
                                    <span class="text-emerald-300"><?= htmlspecialchars($entry['file'], ENT_QUOTES) ?></span>
                                    <span class="text-sky-300"><?= htmlspecialchars($entry['line'], ENT_QUOTES) ?></span>
                                    <span class="text-rose-300"><?= htmlspecialchars($entry['function'], ENT_QUOTES) ?></span>
                                </div>
                            <?php else : ?>
                                <div><?= htmlspecialchars($entry['line'], ENT_QUOTES) ?></div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php else : ?>
        <div class="w-full max-w-lg bg-white rounded-2xl shadow-2xl p-8 text-center">
            <div class="text-5xl mb-4">&#9888;</div>
            <h1 class="text-2xl font-bold text-slate-900 mb-2">Something Went Wrong</h1>
            <p class="text-sm text-slate-600 leading-relaxed mb-6">
                We encountered an unexpected error while processing your request. Our team has been notified and is working to resolve the issue.
            </p>
            <div class="mb-6 rounded-lg bg-slate-100 px-4 py-3 text-xs font-mono text-slate-600">
                Error Reference: <?= htmlspecialchars($errorReference, ENT_QUOTES) ?>
            </div>
            <a href="/" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Return Home</a>
        </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layouts/admin.php';
?>
