<?php
/** @var string $csrfToken */
/** @var string $autoUpdateMode */
/** @var string $version */
/** @var string $channel */

$title = 'Admin Panel';
$bodyClass = 'bg-[#FAFAFA] text-gray-800 antialiased';

ob_start();
?>
<div class="fixed top-0 left-0 right-0 bg-white/80 backdrop-blur-md border-b border-gray-200/60 z-50">
    <div class="px-6 py-3 flex justify-between items-center">
        <h1 class="text-lg font-semibold text-gray-900">Admin Panel</h1>
        <a href="/" class="text-sm text-gray-600 hover:text-gray-900">&lt;- Back to Site</a>
    </div>
</div>

<div class="pt-16 flex min-h-screen">
    <aside class="w-64 bg-white border-r border-gray-200 fixed left-0 top-16 bottom-0">
        <nav class="p-6 space-y-2">
            <a href="#dashboard" class="admin-nav-link active block px-4 py-2 rounded-lg text-sm font-medium text-gray-900 bg-gray-100" data-tab="dashboard">Dashboard</a>
            <a href="#settings" class="admin-nav-link block px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900" data-tab="settings">Settings</a>
            <a href="#advanced" class="admin-nav-link block px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900" data-tab="advanced">Advanced</a>
            <a href="#security" class="admin-nav-link block px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900" data-tab="security">Security</a>
            <a href="#content" class="admin-nav-link block px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900" data-tab="content">Content</a>
            <a href="#blocks" class="admin-nav-link block px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900" data-tab="blocks">Blocks</a>
            <a href="#updates" class="admin-nav-link block px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900" data-tab="updates">Updates</a>
            <a href="#notifications" class="admin-nav-link block px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900" data-tab="notifications">Notifications</a>
            <a href="#submissions" class="admin-nav-link block px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900" data-tab="submissions">Submissions</a>
            <a href="#components" class="admin-nav-link block px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900" data-tab="components">Components</a>
            <a href="/api/logout" class="block px-4 py-2 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50">Logout</a>
        </nav>
    </aside>

    <main class="ml-64 flex-1 p-6 sm:p-12">
        <div class="max-w-4xl">
            <div id="dashboard-tab" class="admin-tab">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">Dashboard</h2>
                    <button type="button" id="dashboard-refresh-btn" class="px-3 py-1.5 text-xs font-semibold text-gray-600 border border-gray-200 rounded-full hover:bg-gray-50">Refresh</button>
                </div>
                <div id="dashboard-status" class="hidden mb-6 rounded-xl border px-4 py-3 text-sm"></div>
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="bg-white border border-gray-200 rounded-2xl p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Content Overview</h3>
                                <p class="text-sm text-gray-500">Quick stats for your pages and blocks.</p>
                            </div>
                            <button type="button" id="dashboard-open-content" class="text-xs text-indigo-600 hover:text-indigo-700 font-semibold">Open Pages</button>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 mt-4">
                            <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                                <p class="text-xs uppercase tracking-wide text-gray-500">Pages</p>
                                <p id="dashboard-pages-count" class="text-3xl font-semibold text-gray-900 mt-2">--</p>
                                <button type="button" id="dashboard-open-pages" class="mt-3 text-xs text-indigo-600 hover:text-indigo-700 font-semibold">Manage Pages</button>
                            </div>
                            <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                                <p class="text-xs uppercase tracking-wide text-gray-500">Blocks</p>
                                <p id="dashboard-blocks-count" class="text-3xl font-semibold text-gray-900 mt-2">--</p>
                                <button type="button" id="dashboard-open-blocks" class="mt-3 text-xs text-indigo-600 hover:text-indigo-700 font-semibold">Manage Blocks</button>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-2xl p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Active Theme</h3>
                                <p class="text-sm text-gray-500">Switch the live theme for your site.</p>
                            </div>
                            <button type="button" id="dashboard-theme-save" class="px-3 py-1.5 text-xs font-semibold text-indigo-600 border border-indigo-100 rounded-full hover:bg-indigo-50">Apply</button>
                        </div>
                        <div class="mt-4">
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Theme</label>
                            <select id="dashboard-theme-select" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></select>
                            <p class="text-xs text-gray-500 mt-2">Theme changes update <span class="font-semibold">site.theme</span> and require a refresh.</p>
                        </div>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-2xl p-6 lg:col-span-2">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Current Settings</h3>
                                <p class="text-sm text-gray-500">Snapshot of active configuration values.</p>
                            </div>
                            <button type="button" id="dashboard-open-settings" class="text-xs text-indigo-600 hover:text-indigo-700 font-semibold">Open Settings</button>
                        </div>
                        <div id="dashboard-settings-list" class="mt-4 space-y-3 text-sm text-gray-700"></div>
                    </div>
                </div>
            </div>

            <div id="settings-tab" class="admin-tab hidden">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Settings</h2>
                <div class="bg-white border border-gray-200 rounded-2xl p-6">
                    <form id="settings-form" class="space-y-6">
                        <div id="settings-container"></div>
                        <div class="flex gap-3 pt-4 border-t border-gray-200">
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700">Save Settings</button>
                            <button type="button" id="add-setting-btn" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">Add Site Setting</button>
                        </div>
                    </form>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl p-6 mt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Read-Only Configuration</h3>
                    <p class="text-sm text-gray-500 mb-4">System settings outside <span class="font-semibold">site.*</span> are managed in code.</p>
                    <div id="settings-readonly" class="space-y-3 text-sm text-gray-700"></div>
                </div>
            </div>

            <div id="advanced-tab" class="admin-tab hidden">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Advanced</h2>
                <div id="advanced-status" class="hidden mb-6 rounded-xl border px-4 py-3 text-sm"></div>
                <div class="bg-white border border-gray-200 rounded-2xl p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Export Content</h3>
                    <p class="text-sm text-gray-500 mb-4">Download a backup of your content directory and configuration as a compressed archive.</p>
                    <button type="button" id="export-btn" class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        Export Content
                    </button>
                    <div id="export-status" class="mt-3 hidden"></div>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl p-6 mt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Sessions & Magic Links</h3>
                    <p class="text-sm text-gray-500 mb-4">Clear login sessions or reset magic link cooldowns in development.</p>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" id="clear-sessions-btn" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200">Clear Sessions</button>
                        <button type="button" id="clear-magic-links-btn" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200">Clear Magic Links</button>
                    </div>
                </div>
            </div>

            <div id="security-tab" class="admin-tab hidden">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Security</h2>
                <div id="security-message" class="hidden mb-6 rounded-xl border px-4 py-3 text-sm"></div>
                <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Password Reset</h3>
                    <p class="text-sm text-gray-500 mb-4">Send a secure magic link to the admin email. Opening it forces a new password.</p>
                    <button type="button" id="security-password-reset-btn" class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700">Send Reset Link</button>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Blocked Hosts</h3>
                    <p class="text-sm text-gray-500 mb-4">Clear IP blocks if you accidentally banned a visitor.</p>
                    <button type="button" id="clear-blocklist-btn" class="px-4 py-2 bg-red-50 text-red-700 text-sm font-semibold rounded-lg hover:bg-red-100">Clear Blocked Hosts</button>
                </div>
            </div>

            <div id="content-tab" class="admin-tab hidden">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Content</h2>
                <div class="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
                    <div class="bg-white border border-gray-200 rounded-2xl p-4">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-sm font-semibold text-gray-900">Pages</p>
                            <button type="button" id="refresh-content-btn" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">Refresh</button>
                        </div>
                        <div id="content-list" class="space-y-1 text-sm text-gray-700"></div>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-2xl p-4">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <p id="content-editor-title" class="text-sm font-semibold text-gray-900">Select a page</p>
                                <p id="content-editor-path" class="text-xs text-gray-500"></p>
                            </div>
                        </div>
                        <div id="content-editor-placeholder" class="text-sm text-gray-500">Choose a page from the list to edit its markdown.</div>
                        <textarea id="content-editor-area" class="hidden w-full min-h-[380px] rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" spellcheck="false"></textarea>
                        <div class="flex gap-3 pt-4 border-t border-gray-200 mt-4">
                            <button type="button" id="content-save-btn" class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-50" disabled>Save</button>
                            <button type="button" id="content-cancel-btn" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 disabled:opacity-50" disabled>Cancel</button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="blocks-tab" class="admin-tab hidden">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Blocks</h2>
                <div class="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
                    <div class="bg-white border border-gray-200 rounded-2xl p-4">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-sm font-semibold text-gray-900">Blocks</p>
                            <button type="button" id="refresh-blocks-btn" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">Refresh</button>
                        </div>
                        <div id="blocks-list" class="space-y-1 text-sm text-gray-700"></div>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-2xl p-4">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <p id="block-editor-title" class="text-sm font-semibold text-gray-900">Select a block</p>
                                <p id="block-editor-path" class="text-xs text-gray-500"></p>
                            </div>
                        </div>
                        <div id="block-editor-placeholder" class="text-sm text-gray-500">Choose a block to edit its markdown.</div>
                        <textarea id="block-editor-area" class="hidden w-full min-h-[380px] rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" spellcheck="false"></textarea>
                        <div class="flex gap-3 pt-4 border-t border-gray-200 mt-4">
                            <button type="button" id="block-save-btn" class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-50" disabled>Save</button>
                            <button type="button" id="block-cancel-btn" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 disabled:opacity-50" disabled>Cancel</button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="updates-tab" class="admin-tab hidden">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">System Updates</h2>
                <div id="update-banner" class="hidden mb-6"></div>
                <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Current Version</h3>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($version, ENT_QUOTES) ?></p>
                            <p class="text-sm text-gray-500 mt-1">Channel: <?= htmlspecialchars($channel, ENT_QUOTES) ?></p>
                        </div>
                        <button id="check-updates-btn" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">Check for Updates</button>
                    </div>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Update Settings</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Auto-Update Mode</label>
                            <select id="auto-update-mode" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                                <option value="true" <?= $autoUpdateMode === 'true' ? 'selected' : '' ?>>Automatic - Install updates automatically</option>
                                <option value="ask" <?= $autoUpdateMode === 'ask' ? 'selected' : '' ?>>Ask - Prompt before installing</option>
                                <option value="false" <?= $autoUpdateMode === 'false' ? 'selected' : '' ?>>Manual - Notify only, never auto-install</option>
                            </select>
                            <p class="text-sm text-gray-500 mt-2">Controls how Flint handles available updates. Your content and themes are always preserved.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div id="notifications-tab" class="admin-tab hidden">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Notifications</h2>
                <div class="bg-white border border-gray-200 rounded-2xl p-6">
                    <p class="text-gray-600">No new notifications.</p>
                </div>
            </div>

            <div id="submissions-tab" class="admin-tab hidden">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Form Submissions</h2>
                <div class="bg-white border border-gray-200 rounded-2xl p-6">
                    <div class="flex justify-between items-center mb-4">
                        <p class="text-sm text-gray-600" id="submissions-count">Loading...</p>
                        <button type="button" id="clear-submissions-btn" class="px-4 py-2 bg-red-50 text-red-700 text-sm font-semibold rounded-lg hover:bg-red-100">Clear All</button>
                    </div>
                    <div id="submissions-container" class="space-y-3"></div>
                </div>
            </div>

            <div id="components-tab" class="admin-tab hidden">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Components</h2>
                <div class="mb-8">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Installed Components</h3>
                        <button id="refresh-components-btn" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium">Refresh</button>
                    </div>
                    <div id="installed-components" class="grid gap-4"></div>
                </div>
                <div>
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Browse Components</h3>
                        <button id="browse-btn" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">Browse Available</button>
                    </div>
                    <div id="browse-components" class="grid gap-4"></div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    const CSRF_TOKEN = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>
<script src="/assets/js/admin.js"></script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layouts/admin.php';
?>
