<?php
require_once __DIR__ . '/auth.php';

$slug = trim($_GET['slug'] ?? '');

if (!$slug) {
    header('Location: /');
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("
    SELECT p.*, u.email as owner_email
    FROM pages p
    JOIN users u ON p.user_id = u.id
    WHERE p.slug = ?
");
$stmt->execute([$slug]);
$page = $stmt->fetch();

if (!$page) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Not Found — NotResume</title>
        <link rel="stylesheet" href="style.css">
        <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>◆</text></svg>">
    </head>
    <body>
        <div class="auth-wrapper">
            <div class="auth-card text-center">
                <div style="font-size: 4rem; margin-bottom: 16px; opacity:0.3;">◇</div>
                <h1>Page not found</h1>
                <p class="subtitle">There's no page at this address. It may have been removed or the URL might be wrong.</p>
                <a href="/" class="btn btn-primary btn-lg mt-3">Go to NotResume</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Check if published (allow owner to see unpublished)
$currentUser = currentUser();
$isOwner = $currentUser && $currentUser['id'] == $page['user_id'];

if (!$page['is_published'] && !$isOwner) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Not Available — NotResume</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <div class="auth-wrapper">
            <div class="auth-card text-center">
                <h1>This page is private</h1>
                <p class="subtitle">The owner has not published this page yet.</p>
                <a href="/" class="btn btn-primary btn-lg mt-3">Go to NotResume</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Increment view count (not for owner)
if (!$isOwner) {
    $stmt = $pdo->prepare("UPDATE pages SET views = views + 1 WHERE id = ?");
    $stmt->execute([$page['id']]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page['title']) ?> — NotResume</title>
    <meta name="description" content="<?= htmlspecialchars($page['title']) ?> — View my professional profile on NotResume.">
    <meta property="og:title" content="<?= htmlspecialchars($page['title']) ?>">
    <meta property="og:description" content="View my professional profile on NotResume.">
    <meta property="og:url" content="<?= SITE_URL ?>/<?= htmlspecialchars($page['slug']) ?>">
    <meta property="og:type" content="profile">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>◆</text></svg>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { min-height: 100vh; }
        .nr-bar {
            padding: 8px 20px;
            background: #0C0A09;
            color: #fff;
            text-align: center;
            font-family: 'DM Sans', -apple-system, sans-serif;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        .nr-bar a { color: #fff; text-decoration: underline; opacity: 0.8; }
        .nr-bar a:hover { opacity: 1; }
        .nr-draft-bar {
            padding: 8px 20px;
            background: #FFFBEB;
            color: #92400E;
            text-align: center;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            border-bottom: 1px solid #FDE68A;
        }
        .nr-content { padding: 0; }
    </style>
</head>
<body>
    <div class="public-page-wrapper">
        <?php if (!$page['is_published'] && $isOwner): ?>
            <div class="nr-draft-bar">
                ⚠ This page is a <strong>draft</strong> — only you can see it.
                <a href="/edit?id=<?= $page['id'] ?>">Edit page</a>
            </div>
        <?php endif; ?>

        <div class="nr-bar">
            <span>Built with</span>
            <a href="<?= SITE_URL ?>" target="_blank">◆ NotResume</a>
            <?php if ($isOwner): ?>
                <span>·</span>
                <a href="/edit?id=<?= $page['id'] ?>">Edit</a>
            <?php endif; ?>
        </div>

        <div class="nr-content">
            <?= $page['html_content'] ?>
        </div>
    </div>
</body>
</html>
