<?php
require_once __DIR__ . '/auth.php';

$user = requireAuth();
$flash = getFlash();

// Handle resend verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend_verification') {
    if (verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $result = resendVerification($user['id']);
        if ($result['success']) {
            setFlash('success', 'Verification email sent! Check your inbox.');
        } else {
            setFlash('error', $result['error'] ?? 'Failed to send verification email.');
        }
        header('Location: /dashboard');
        exit;
    }
}

// Get user's pages
$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM pages WHERE user_id = ? ORDER BY updated_at DESC");
$stmt->execute([$user['id']]);
$pages = $stmt->fetchAll();

$pageCount = count($pages);
$showUnverified = !$user['email_verified'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — NotResume</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>◆</text></svg>">
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="/" class="logo">◆ NotResume</a>
            <nav class="nav-links">
                <span style="font-size:0.82rem;color:var(--text-muted);"><?= htmlspecialchars($user['email']) ?></span>
                <a href="/logout" class="btn-ghost">Log out</a>
            </nav>
        </div>
    </header>

    <main class="container container-wide">
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?>" style="margin-top:24px;">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($showUnverified): ?>
            <div class="alert alert-warning" style="margin-top:24px;">
                <div>
                    <strong>Verify your email to create pages.</strong><br>
                    We sent a verification link to <?= htmlspecialchars($user['email']) ?>.
                    <form method="POST" style="display:inline; margin-left:8px;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="resend_verification">
                        <button type="submit" style="background:none;border:none;color:inherit;font-weight:700;cursor:pointer;text-decoration:underline;font-size:inherit;font-family:inherit;">Resend email</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="dashboard-header">
            <h1>Your Pages</h1>
            <?php if ($user['email_verified'] && $pageCount < MAX_PAGES_PER_USER): ?>
                <a href="/create" class="btn btn-primary">+ New Page</a>
            <?php endif; ?>
        </div>

        <?php if ($pageCount === 0): ?>
            <div class="empty-state card">
                <div class="empty-icon">◇</div>
                <h3>No pages yet</h3>
                <p>Create your first one-page profile and share it with recruiters.</p>
                <?php if ($user['email_verified']): ?>
                    <a href="/create" class="btn btn-primary">Create Your First Page</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="page-list">
                <?php foreach ($pages as $pg): ?>
                    <div class="page-card">
                        <div class="page-card-info">
                            <div class="page-card-title"><?= htmlspecialchars($pg['title']) ?></div>
                            <div class="page-card-slug">
                                <a href="/<?= htmlspecialchars($pg['slug']) ?>" target="_blank">
                                    notresume.com/<?= htmlspecialchars($pg['slug']) ?>
                                </a>
                                <?php if (!$pg['is_published']): ?>
                                    <span style="color:var(--warning);margin-left:6px;">· Draft</span>
                                <?php endif; ?>
                            </div>
                            <div class="page-card-meta">
                                <?= $pg['views'] ?> view<?= $pg['views'] !== 1 ? 's' : '' ?>
                                · Updated <?= date('M j, Y', strtotime($pg['updated_at'])) ?>
                            </div>
                        </div>
                        <div class="page-card-actions">
                            <a href="/edit?id=<?= $pg['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                            <a href="/<?= htmlspecialchars($pg['slug']) ?>" class="btn btn-ghost btn-sm" target="_blank">View</a>
                            <a href="/delete?id=<?= $pg['id'] ?>" class="btn btn-ghost btn-sm" style="color:var(--error);"
                               data-confirm="Delete '<?= htmlspecialchars($pg['title']) ?>'? This cannot be undone.">Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($pageCount >= MAX_PAGES_PER_USER): ?>
                <p class="text-muted text-center mt-2" style="font-size:0.85rem;">
                    You've reached the maximum of <?= MAX_PAGES_PER_USER ?> pages.
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>© <?= date('Y') ?> NotResume</p>
        </div>
    </footer>

    <script src="app.js"></script>
</body>
</html>
