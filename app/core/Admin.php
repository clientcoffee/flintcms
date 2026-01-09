<?php

namespace Flint;

/**
 * Admin UI Class
 * Handles all admin functionality: logout, edit controls, status badges.
 * Auto-injects admin UI into any theme without requiring theme implementation.
 */
class Admin
{
    private App $app;
    private bool $isAdmin;
    private string $currentPath;

    public function __construct(App $app, bool $isAdmin, string $currentPath)
    {
        $this->app = $app;
        $this->isAdmin = $isAdmin;
        $this->currentPath = $currentPath;
    }

    /**
     * Declare admin assets using component asset system.
     */
    public static function getAssets(string $currentPath): array
    {
        return [
            'inline_scripts' => [
                [
                    'content' => self::getAdminJavaScript($currentPath),
                    'position' => 'footer'
                ]
            ]
        ];
    }

    /**
     * Generate admin JavaScript for edit mode and logout.
     */
    private static function getAdminJavaScript(string $currentPath): string
    {
        $escapedPath = json_encode($currentPath);
        return <<<JS
// Admin functionality JavaScript
(function() {
    const init = () => {
    // Current path for API calls
    const currentPath = {$escapedPath};

    // DOM element cache
    const editButton = document.getElementById('edit-btn');
    const saveButton = document.getElementById('save-btn');
    const cancelButton = document.getElementById('cancel-btn');
    const contentDisplay = document.getElementById('content-display');
    const contentEditorWrap = document.getElementById('content-editor-wrap');
    const contentEditor = document.getElementById('content-editor');
    const logoutButton = document.getElementById('admin-logout-btn');

    // State
    let originalContent = '';
    let isEditing = false;

    // Toggle edit UI states in a single helper
    const setEditMode = (shouldEnable) => {
        if (!contentDisplay || !contentEditor || !editButton || !saveButton || !cancelButton) {
            return;
        }
        contentDisplay.classList.toggle('hidden', shouldEnable);
        if (contentEditorWrap) {
            contentEditorWrap.classList.toggle('hidden', !shouldEnable);
        }
        contentEditor.classList.toggle('hidden', !shouldEnable);
        editButton.classList.toggle('hidden', shouldEnable);
        saveButton.classList.toggle('hidden', !shouldEnable);
        cancelButton.classList.toggle('hidden', !shouldEnable);
        isEditing = shouldEnable;
    };

    // Load content and switch to edit mode
    editButton?.addEventListener('click', async () => {
        if (!contentEditor) {
            return;
        }

        try {
            const fetchResponse = await fetch(`/api/site?path=\${encodeURIComponent(currentPath)}`);
            const responseData = await fetchResponse.json();
            if (!responseData.success) {
                alert('Failed to load content for editing');
                return;
            }

            originalContent = responseData.content;
            contentEditor.value = responseData.content;
            setEditMode(true);
        } catch (error) {
            console.error('Error loading content:', error);
            alert('Error loading content');
        }
    });

    // Save content edits to the server
    saveButton?.addEventListener('click', async () => {
        if (!contentEditor) {
            return;
        }

        try {
            const updatedContent = contentEditor.value;
            const saveResponse = await fetch('/api/save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ path: currentPath, content: updatedContent })
            });

            const responseData = await saveResponse.json();
            if (!responseData.success) {
                alert('Failed to save content');
                return;
            }

            window.location.reload();
        } catch (error) {
            console.error('Error saving content:', error);
            alert('Error saving content');
        }
    });

    // Cancel edits and restore the original content
    cancelButton?.addEventListener('click', () => {
        if (!contentEditor) {
            return;
        }

        contentEditor.value = originalContent;
        setEditMode(false);
    });

    // Keyboard shortcuts for saving or exiting edit mode
    document.addEventListener('keydown', (event) => {
        if (!isEditing) {
            return;
        }

        if (event.ctrlKey && event.key === 's') {
            event.preventDefault();
            saveButton?.click();
            return;
        }

        if (event.key === 'Escape') {
            cancelButton?.click();
        }
    });

    // Logout button handler
    logoutButton?.addEventListener('click', async () => {
        try {
            await fetch('/api/logout');
            window.location.reload();
        } catch (error) {
            console.error('Logout error:', error);
        }
    });

    // === DRAG-AND-DROP UPLOAD FUNCTIONALITY ===

    let isDragging = false;
    let dragCounter = 0;
    let currentCursorPosition = 0;

    // Track cursor position in editor.
    contentEditor?.addEventListener('selectionchange', () => {
        if (contentEditor) {
            currentCursorPosition = contentEditor.selectionStart;
        }
    });
    contentEditor?.addEventListener('click', () => {
        if (contentEditor) {
            currentCursorPosition = contentEditor.selectionStart;
        }
    });
    contentEditor?.addEventListener('keyup', () => {
        if (contentEditor) {
            currentCursorPosition = contentEditor.selectionStart;
        }
    });

    // Create dropzone overlay.
    const dropzoneOverlay = document.createElement('div');
    dropzoneOverlay.id = 'admin-dropzone-overlay';
    dropzoneOverlay.style.cssText = `
        position: fixed;
        inset: 0;
        background: rgba(59, 130, 246, 0.95);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        pointer-events: none;
    `;
    dropzoneOverlay.innerHTML = `
        <div style="text-align: center; color: white;">
            <svg style="width: 80px; height: 80px; margin: 0 auto 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
            </svg>
            <p style="font-size: 24px; font-weight: 600; margin: 0;">Drop image here to upload</p>
            <p style="font-size: 14px; opacity: 0.9; margin-top: 8px;">JPG, PNG, GIF, WebP, SVG • Max 5MB</p>
        </div>
    `;
    document.body.appendChild(dropzoneOverlay);

    // Create success modal.
    const successModal = document.createElement('div');
    successModal.id = 'admin-upload-modal';
    successModal.style.cssText = `
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.75);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    `;
    successModal.innerHTML = `
        <div style="background: white; border-radius: 16px; padding: 32px; max-width: 500px; width: 90%;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h3 style="margin: 0; font-size: 20px; font-weight: 600;">Image Uploaded Successfully</h3>
                <button id="modal-close-btn" style="background: none; border: none; cursor: pointer; font-size: 24px; color: #6b7280;">×</button>
            </div>
            <div style="text-align: center; margin-bottom: 24px;">
                <img id="modal-thumbnail" src="" alt="Uploaded image" style="max-width: 100%; max-height: 300px; border-radius: 8px; border: 1px solid #e5e7eb;">
            </div>
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <button id="modal-copy-url" style="flex: 1; min-width: 120px; padding: 12px 20px; background: #3b82f6; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    Copy URL
                </button>
                <button id="modal-open-tab" style="flex: 1; min-width: 120px; padding: 12px 20px; background: #10b981; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    Open in Tab
                </button>
                <button id="modal-copy-markdown" style="flex: 1; min-width: 120px; padding: 12px 20px; background: #8b5cf6; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    Copy Markdown
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(successModal);

    // Show dropzone overlay when dragging.
    document.addEventListener('dragenter', (e) => {
        e.preventDefault();
        dragCounter++;
        if (dragCounter === 1) {
            dropzoneOverlay.style.display = 'flex';
        }
    });

    document.addEventListener('dragleave', (e) => {
        e.preventDefault();
        dragCounter--;
        if (dragCounter === 0) {
            dropzoneOverlay.style.display = 'none';
        }
    });

    document.addEventListener('dragover', (e) => {
        e.preventDefault();
    });

    document.addEventListener('drop', async (e) => {
        e.preventDefault();
        dragCounter = 0;
        dropzoneOverlay.style.display = 'none';

        const files = e.dataTransfer.files;
        if (files.length === 0) return;

        const file = files[0];

        // Validate file type.
        if (!file.type.startsWith('image/')) {
            alert('Please upload an image file (JPG, PNG, GIF, WebP, SVG)');
            return;
        }

        // Validate file size (5MB).
        const maxSize = 5 * 1024 * 1024;
        if (file.size > maxSize) {
            alert('File size exceeds 5MB limit. Please compress your image.');
            return;
        }

        // Upload file.
        const formData = new FormData();
        formData.append('file', file);

        try {
            const response = await fetch('/api/upload', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (!data.success) {
                alert('Upload failed: ' + (data.error || 'Unknown error'));
                return;
            }

            // Auto-inject markdown if editing.
            if (isEditing && contentEditor && data.fileType !== 'theme') {
                let markdown = '';
                if (data.fileType === 'image') {
                    markdown = `![Image](\${data.url})`;
                } else if (data.fileType === 'pdf') {
                    markdown = `[\${data.filename}](\${data.url})`;
                } else if (data.fileType === 'zip') {
                    markdown = `[\${data.filename}](\${data.url})`;
                } else if (data.fileType === 'markdown') {
                    markdown = `[\${data.filename}](\${data.url})`;
                }

                if (markdown) {
                    const before = contentEditor.value.substring(0, currentCursorPosition);
                    const after = contentEditor.value.substring(currentCursorPosition);
                    contentEditor.value = before + markdown + after;
                    currentCursorPosition += markdown.length;
                    contentEditor.setSelectionRange(currentCursorPosition, currentCursorPosition);
                    contentEditor.focus();
                }
            }

            // Show success modal.
            const thumbnail = document.getElementById('modal-thumbnail');
            if (data.fileType === 'image') {
                thumbnail.src = data.url;
                thumbnail.style.display = 'block';
            } else if (data.fileType === 'theme') {
                thumbnail.style.display = 'none';
                alert(data.message || `Theme installed successfully!`);
            } else {
                thumbnail.style.display = 'none';
            }
            successModal.style.display = 'flex';

            // Store URL and markdown for modal buttons.
            successModal.dataset.url = data.url || '';
            successModal.dataset.fileType = data.fileType;
            if (data.fileType === 'image') {
                successModal.dataset.markdown = `![Image](\${data.url})`;
            } else if (data.fileType === 'pdf' || data.fileType === 'zip' || data.fileType === 'markdown') {
                successModal.dataset.markdown = `[\${data.filename}](\${data.url})`;
            } else {
                successModal.dataset.markdown = '';
            }

        } catch (error) {
            console.error('Upload error:', error);
            alert('Upload failed. Please try again.');
        }
    });

    // Modal button handlers.
    document.getElementById('modal-close-btn')?.addEventListener('click', () => {
        successModal.style.display = 'none';
    });

    document.getElementById('modal-copy-url')?.addEventListener('click', () => {
        navigator.clipboard.writeText(successModal.dataset.url);
        alert('URL copied to clipboard!');
    });

    document.getElementById('modal-open-tab')?.addEventListener('click', () => {
        window.open(successModal.dataset.url, '_blank');
    });

    document.getElementById('modal-copy-markdown')?.addEventListener('click', () => {
        navigator.clipboard.writeText(successModal.dataset.markdown);
        alert('Markdown copied to clipboard!');
    });

    // Click outside modal to close.
    successModal.addEventListener('click', (e) => {
        if (e.target === successModal) {
            successModal.style.display = 'none';
        }
    });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
JS;
    }

    /**
     * Generate edit controls HTML (edit/save/cancel buttons).
     */
    public function getEditControlsHtml(): string
    {
        return <<<HTML
<div id="admin-edit-controls" class="inline-flex items-start gap-2 ml-3 align-top">
    <button id="edit-btn" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-md transition" title="Edit page">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
        </svg>
    </button>
    <button id="save-btn" class="hidden p-2 text-green-600 hover:text-green-700 hover:bg-green-50 rounded-md transition" title="Save changes">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
    </button>
    <button id="cancel-btn" class="hidden p-2 text-red-600 hover:text-red-700 hover:bg-red-50 rounded-md transition" title="Cancel editing">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
    </button>
</div>
HTML;
    }

    /**
     * Generate status badge HTML (draft/hidden).
     */
    public function getStatusBadgeHtml(string $pageStatus): string
    {
        if ($pageStatus === 'draft') {
            return <<<HTML
<div class="admin-status-badge mt-4 w-full">
    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
        <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
        </svg>
        Draft (Not Published)
    </span>
</div>
HTML;
        } elseif ($pageStatus === 'hidden') {
            return <<<HTML
<div class="admin-status-badge mt-4 w-full">
    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
        </svg>
        Hidden from Navigation
    </span>
</div>
HTML;
        }

        return '';
    }

    /**
     * Generate content editor textarea HTML.
     */
    public function getEditorTextareaHtml(): string
    {
        return <<<HTML
<div id="content-editor-wrap" class="hidden max-w-3xl mx-auto px-6 sm:px-12 pb-16">
    <textarea id="content-editor" class="w-full p-4 border border-gray-300 rounded-md font-mono text-sm" style="min-height: 60vh;" placeholder="Edit markdown content..."></textarea>
</div>
HTML;
    }

    /**
     * Inject admin UI into rendered HTML.
     */
    public function injectAdminUI(string $html, string $pageStatus): string
    {
        if (!$this->isAdmin) {
            return $html;
        }

        // 1. Inject edit controls + status badge after first <h1>
        $html = $this->injectEditControls($html, $pageStatus);

        // 2. Inject editor textarea before </body>
        $html = $this->injectEditorTextarea($html);

        return $html;
    }

    /**
     * Inject edit controls after first <h1> tag.
     */
    private function injectEditControls(string $html, string $pageStatus): string
    {
        $editControlsHtml = $this->getEditControlsHtml();
        $statusBadgeHtml = $this->getStatusBadgeHtml($pageStatus);

        // Find first </h1> tag and inject after it
        $pattern = '/(<h1[^>]*>.*?<\/h1>)/s';
        if (preg_match($pattern, $html, $matches)) {
            $h1Tag = $matches[1];
            $replacement = $h1Tag . $editControlsHtml . $statusBadgeHtml;
            $html = preg_replace($pattern, $replacement, $html, 1);
        }

        return $html;
    }

    /**
     * Inject editor textarea before </body>.
     */
    private function injectEditorTextarea(string $html): string
    {
        $textareaHtml = $this->getEditorTextareaHtml();

        if (strpos($html, '</main>') !== false) {
            $html = str_replace('</main>', $textareaHtml . "\n</main>", $html);
            return $html;
        }

        // Fallback: inject before </body>.
        if (strpos($html, '</body>') !== false) {
            $html = str_replace('</body>', $textareaHtml . "\n</body>", $html);
        }

        return $html;
    }
}
