<?php
require_once __DIR__ . '/auth.php';

$user = requireVerified();
$pdo = getDB();

$pageId = (int)($_GET['id'] ?? 0);
if (!$pageId) {
    header('Location: /dashboard');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ? AND user_id = ?");
$stmt->execute([$pageId, $user['id']]);
$page = $stmt->fetch();

if (!$page) {
    setFlash('error', 'Page not found.');
    header('Location: /dashboard');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_settings') {
            $title = trim($_POST['title'] ?? 'Untitled');
            $slug = trim(strtolower($_POST['slug'] ?? ''));
            $isPublished = isset($_POST['is_published']) ? 1 : 0;

            if (!$slug) {
                $error = 'Please provide a slug.';
            } elseif (!isValidSlug($slug)) {
                $error = 'Invalid slug format.';
            } elseif (!isSlugAvailable($slug, $pageId)) {
                $error = 'This slug is already taken.';
            } else {
                $stmt = $pdo->prepare("UPDATE pages SET title = ?, slug = ?, is_published = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$title, $slug, $isPublished, $pageId, $user['id']]);
                setFlash('success', 'Settings saved.');
                header('Location: /edit?id=' . $pageId);
                exit;
            }
        } elseif ($action === 'save_html') {
            $htmlContent = $_POST['html_content'] ?? '';
            if ($htmlContent) {
                $htmlContent = sanitizePageHtml($htmlContent);
                $stmt = $pdo->prepare("UPDATE pages SET html_content = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$htmlContent, $pageId, $user['id']]);
                // Refresh page data
                $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ? AND user_id = ?");
                $stmt->execute([$pageId, $user['id']]);
                $page = $stmt->fetch();
                setFlash('success', 'Page content updated.');
                header('Location: /edit?id=' . $pageId);
                exit;
            }
        }
    }
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit: <?= htmlspecialchars($page['title']) ?> — NotResume</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>◆</text></svg>">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken()) ?>">
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="/" class="logo">◆ NotResume</a>
            <nav class="nav-links">
                <a href="/dashboard">Dashboard</a>
                <a href="/<?= htmlspecialchars($page['slug']) ?>" target="_blank" class="btn-ghost">View Page ↗</a>
            </nav>
        </div>
    </header>

    <main class="container container-wide create-wrapper">
        <h1>Edit: <?= htmlspecialchars($page['title']) ?></h1>

        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Page Settings -->
        <form method="POST" class="card mb-3">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_settings">

            <h3 style="margin-bottom:20px;">Page Settings</h3>

            <div class="form-group">
                <label for="title">Page Title</label>
                <input type="text" id="title" name="title" class="form-input"
                       value="<?= htmlspecialchars($page['title']) ?>" required>
            </div>

            <div class="form-group">
                <label for="slug-input">URL Slug</label>
                <div class="slug-input-group">
                    <span class="slug-prefix">notresume.com/</span>
                    <input type="text" id="slug-input" name="slug" class="slug-input"
                           value="<?= htmlspecialchars($page['slug']) ?>"
                           data-page-id="<?= $page['id'] ?>"
                           pattern="[a-zA-Z0-9][a-zA-Z0-9_-]*"
                           minlength="3" maxlength="100" required>
                </div>
                <div id="slug-status" class="slug-status"></div>
            </div>

            <div class="form-group">
                <div class="toggle-wrapper">
                    <label class="toggle">
                        <input type="checkbox" name="is_published" <?= $page['is_published'] ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                    <span class="toggle-label">Published</span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>

        <!-- Regenerate Content -->
        <div class="card mb-3">
            <h3 style="margin-bottom:4px;">Regenerate Content</h3>
            <p class="text-muted" style="font-size:0.85rem; margin-bottom:20px;">
                Want to update your page? Describe the changes or paste new content below.
            </p>

            <div id="file-badge-area"></div>

            <div class="ai-prompt-area">
                <textarea id="ai-prompt" class="ai-textarea"
                    placeholder="Describe what you'd like to change, or paste updated resume content..."
                ></textarea>
                <div class="upload-btn-wrapper">
                    <div class="upload-btn" title="Upload resume">
                        +
                        <input type="file" id="resume-file"
                               accept=".pdf,.doc,.docx,.txt">
                    </div>
                    <button type="button" id="send-btn" class="send-btn" title="Generate">→</button>
                </div>
            </div>

            <div id="generating-indicator" class="generating-indicator">
                <div class="spinner"></div>
                <span>Generating — this may take a moment...</span>
            </div>
        </div>

        <!-- Preview -->
        <div id="preview-area" class="preview-area"></div>

        <!-- Hidden form to save regenerated HTML -->
        <form method="POST" id="save-html-form" class="mt-2" style="display:none;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_html">
            <input type="hidden" name="html_content" id="html-content-hidden">
            <button type="submit" class="btn btn-primary btn-lg">Save New Content</button>
            <button type="button" class="btn btn-secondary btn-lg" onclick="this.parentElement.style.display='none';">Cancel</button>
        </form>

        <!-- Current Content Preview -->
        <div class="card mt-3">
            <div class="flex-between mb-2">
                <h3>Current Page Content</h3>
                <a href="/<?= htmlspecialchars($page['slug']) ?>" class="btn btn-ghost btn-sm" target="_blank">View Live ↗</a>
            </div>
            <div style="border:1px solid var(--border); border-radius:var(--radius-sm); padding:24px; background:var(--bg); max-height:500px; overflow:auto;">
                <?= $page['html_content'] ?>
            </div>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container"><p>© <?= date('Y') ?> NotResume</p></div>
    </footer>

    <script src="app.js"></script>
    <script>
    // Show save button when new content is generated
    const observer = new MutationObserver(() => {
        const preview = document.getElementById('preview-area');
        const saveForm = document.getElementById('save-html-form');
        if (preview.classList.contains('active') && preview.innerHTML.trim()) {
            saveForm.style.display = 'flex';
            saveForm.style.gap = '12px';
        }
    });
    observer.observe(document.getElementById('preview-area'), { attributes: true, childList: true });
    </script>
</body>
</html>
