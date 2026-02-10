/**
 * NotResume.com - Frontend JavaScript
 */

document.addEventListener('DOMContentLoaded', () => {
    initSlugChecker();
    initAIGenerator();
    initDeleteConfirm();
});

/* ---- Slug availability checker ---- */
function initSlugChecker() {
    const slugInput = document.getElementById('slug-input');
    if (!slugInput) return;

    let timeout = null;
    const statusEl = document.getElementById('slug-status');
    const pageId = slugInput.dataset.pageId || '';

    slugInput.addEventListener('input', (e) => {
        const val = e.target.value.trim().toLowerCase().replace(/[^a-z0-9_-]/g, '');
        e.target.value = val;

        if (timeout) clearTimeout(timeout);
        if (!val || val.length < 3) {
            statusEl.textContent = val.length > 0 ? 'Minimum 3 characters' : '';
            statusEl.className = 'slug-status';
            return;
        }

        statusEl.textContent = 'Checking...';
        statusEl.className = 'slug-status';

        timeout = setTimeout(async () => {
            try {
                const res = await fetch(`${window.location.origin}/api_generate?action=check_slug&slug=${encodeURIComponent(val)}&page_id=${pageId}`);
                const data = await res.json();
                if (data.available) {
                    statusEl.textContent = '✓ Available';
                    statusEl.className = 'slug-status available';
                } else {
                    statusEl.textContent = '✗ ' + (data.reason || 'Already taken');
                    statusEl.className = 'slug-status taken';
                }
            } catch {
                statusEl.textContent = 'Could not check';
                statusEl.className = 'slug-status';
            }
        }, 400);
    });
}

/* ---- AI Page Generator ---- */
function initAIGenerator() {
    const textarea = document.getElementById('ai-prompt');
    const sendBtn = document.getElementById('send-btn');
    const fileInput = document.getElementById('resume-file');
    const fileBadgeArea = document.getElementById('file-badge-area');
    const generatingEl = document.getElementById('generating-indicator');
    const previewArea = document.getElementById('preview-area');
    const htmlHidden = document.getElementById('html-content-hidden');

    if (!textarea || !sendBtn) return;

    let uploadedFile = null;

    // File upload
    if (fileInput) {
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const allowed = [
                'application/pdf',
                'text/plain',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];

            if (!allowed.includes(file.type)) {
                alert('Please upload a PDF, TXT, DOC, or DOCX file.');
                fileInput.value = '';
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                alert('File must be under 5MB.');
                fileInput.value = '';
                return;
            }

            uploadedFile = file;
            fileBadgeArea.innerHTML = `
                <div class="file-badge">
                    📄 ${escapeHtml(file.name)}
                    <span class="remove-file" onclick="removeFile()">✕</span>
                </div>
            `;
        });
    }

    window.removeFile = function() {
        uploadedFile = null;
        if (fileInput) fileInput.value = '';
        fileBadgeArea.innerHTML = '';
    };

    // Auto-resize textarea
    textarea.addEventListener('input', () => {
        textarea.style.height = 'auto';
        textarea.style.height = Math.max(180, textarea.scrollHeight) + 'px';
    });

    // Send button & Ctrl+Enter
    sendBtn.addEventListener('click', generatePage);
    textarea.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            generatePage();
        }
    });

    async function generatePage() {
        const prompt = textarea.value.trim();
        if (!prompt && !uploadedFile) {
            alert('Please describe what you want on your page, or upload a resume.');
            return;
        }

        sendBtn.disabled = true;
        generatingEl.classList.add('active');
        previewArea.classList.remove('active');
        previewArea.innerHTML = '';

        const formData = new FormData();
        formData.append('action', 'generate');
        formData.append('prompt', prompt);
        if (uploadedFile) {
            formData.append('resume', uploadedFile);
        }

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            formData.append('csrf_token', csrfMeta.content);
        }

        try {
            const res = await fetch(`${window.location.origin}/api_generate`, {
                method: 'POST',
                body: formData
            });

            const data = await res.json();

            if (data.success && data.html) {
                previewArea.innerHTML = data.html;
                previewArea.classList.add('active');
                if (htmlHidden) {
                    htmlHidden.value = data.html;
                }
            } else {
                alert(data.error || 'Failed to generate page. Please try again.');
            }
        } catch (err) {
            console.error(err);
            alert('Network error. Please try again.');
        } finally {
            sendBtn.disabled = false;
            generatingEl.classList.remove('active');
        }
    }
}

/* ---- Delete confirmation ---- */
function initDeleteConfirm() {
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', (e) => {
            if (!confirm(el.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });
}

/* ---- Helpers ---- */
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
