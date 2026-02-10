<?php
require_once __DIR__ . '/auth.php';

$user = requireVerified();

// Check page limit
$pdo = getDB();
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM pages WHERE user_id = ?");
$stmt->execute([$user['id']]);
if ($stmt->fetch()['cnt'] >= MAX_PAGES_PER_USER) {
    setFlash('error', 'You have reached the maximum of ' . MAX_PAGES_PER_USER . ' pages.');
    header('Location: /dashboard');
    exit;
}

$error = null;

// Handle form save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $title = trim($_POST['title'] ?? 'Untitled');
        $slug = trim(strtolower($_POST['slug'] ?? ''));
        $htmlContent = $_POST['html_content'] ?? '';
        $isPublished = isset($_POST['is_published']) ? 1 : 0;

        if (!$title) $title = 'Untitled';
        if (!$slug) {
            $error = 'Please provide a slug (URL path).';
        } elseif (!isValidSlug($slug)) {
            $error = 'Invalid slug. Use 3-100 characters: letters, numbers, hyphens, underscores.';
        } elseif (!isSlugAvailable($slug)) {
            $error = 'This slug is already taken.';
        } elseif (!$htmlContent) {
            $error = 'Please generate page content first using the AI prompt.';
        } else {
            $htmlContent = sanitizePageHtml($htmlContent);

            $stmt = $pdo->prepare("
                INSERT INTO pages (user_id, slug, title, html_content, is_published)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user['id'], $slug, $title, $htmlContent, $isPublished]);

            setFlash('success', 'Page created! View it at notresume.com/' . $slug);
            header('Location: /dashboard');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Page — NotResume</title>
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
                <a href="/logout" class="btn-ghost">Log out</a>
            </nav>
        </div>
    </header>

    <main class="container container-wide create-wrapper">
        <h1>Create a new page</h1>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Step 1: AI Generation -->
        <div class="card mb-3">
            <h3 style="margin-bottom:4px;">Describe your page</h3>
            <p class="text-muted" style="font-size:0.85rem; margin-bottom:20px;">
                Tell us about yourself or paste your resume content. You can also upload a resume file. The AI will generate a beautiful one-page profile for you.
            </p>

            <div id="file-badge-area"></div>

            <div class="ai-prompt-area">
                <textarea id="ai-prompt" class="ai-textarea"
                    placeholder="Tell us about yourself...&#10;&#10;For example: &quot;I'm a senior product designer with 8 years of experience at companies like Spotify and Airbnb. I specialize in design systems and user research. I graduated from RISD and I'm passionate about accessible design...&quot;&#10;&#10;Or simply paste your resume text here."
                ><?= htmlspecialchars($_POST['prompt'] ?? '') ?></textarea>

                <div class="upload-btn-wrapper">
                    <div class="upload-btn" title="Upload resume (PDF, DOC, DOCX, TXT)">
                        +
                        <input type="file" id="resume-file"
                               accept=".pdf,.doc,.docx,.txt,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain">
                    </div>
                    <button type="button" id="send-btn" class="send-btn" title="Generate page (Ctrl+Enter)">
                        →
                    </button>
                </div>
            </div>

            <div id="generating-indicator" class="generating-indicator">
                <div class="spinner"></div>
                <span>Generating your page — this may take a moment...</span>
            </div>
        </div>

        <!-- Preview -->
        <div id="preview-area" class="preview-area"></div>

        <!-- Step 2: Save Form (shown after generation) -->
        <form method="POST" id="save-form" class="card mt-3">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="html_content" id="html-content-hidden" value="<?= htmlspecialchars($_POST['html_content'] ?? '') ?>">

            <h3 style="margin-bottom:20px;">Page Settings</h3>

            <div class="form-group">
                <label for="title">Page Title</label>
                <input type="text" id="title" name="title" class="form-input"
                       placeholder="e.g., Jane Doe — Product Designer"
                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                       required>
            </div>

            <div class="form-group">
                <label for="slug-input">URL Slug</label>
                <div class="slug-input-group">
                    <span class="slug-prefix">notresume.com/</span>
                    <input type="text" id="slug-input" name="slug" class="slug-input"
                           placeholder="your-name"
                           pattern="[a-zA-Z0-9][a-zA-Z0-9_-]*"
                           minlength="3" maxlength="100"
                           value="<?= htmlspecialchars($_POST['slug'] ?? '') ?>"
                           required>
                </div>
                <div id="slug-status" class="slug-status"></div>
                <p class="form-hint">Letters, numbers, hyphens, and underscores. Min 3 characters.</p>
            </div>

            <div class="form-group">
                <div class="toggle-wrapper">
                    <label class="toggle">
                        <input type="checkbox" name="is_published" checked>
                        <span class="toggle-slider"></span>
                    </label>
                    <span class="toggle-label">Publish immediately</span>
                </div>
            </div>

            <div style="display:flex; gap:12px;">
                <button type="submit" class="btn btn-primary btn-lg">Save & Publish</button>
                <a href="/dashboard" class="btn btn-secondary btn-lg">Cancel</a>
            </div>
        </form>
    </main>

    <footer class="site-footer">
        <div class="container"><p>© <?= date('Y') ?> NotResume</p></div>
    </footer>

    <script src="app.js"></script>
</body>
</html>
