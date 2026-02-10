<?php
require_once __DIR__ . '/auth.php';

$token = $_GET['token'] ?? '';
$success = false;
$error = null;

if ($token) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT id, email FROM users
        WHERE verify_token = ? AND verify_token_expires > NOW() AND email_verified = 0
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $stmt = $pdo->prepare("
            UPDATE users SET email_verified = 1, verify_token = NULL, verify_token_expires = NULL
            WHERE id = ?
        ");
        $stmt->execute([$user['id']]);
        $success = true;

        // Auto-login if not already
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['user_id'] = $user['id'];
            session_regenerate_id(true);
        }
    } else {
        $error = 'Invalid or expired verification link.';
    }
} else {
    $error = 'No verification token provided.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email — NotResume</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>◆</text></svg>">
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="/" class="logo">◆ NotResume</a>
        </div>
    </header>

    <div class="auth-wrapper">
        <div class="auth-card text-center">
            <?php if ($success): ?>
                <div style="font-size: 3rem; margin-bottom: 16px;">✓</div>
                <h1>Verified!</h1>
                <p class="subtitle">Your email has been verified. You're all set.</p>
                <a href="/dashboard" class="btn btn-primary btn-lg mt-3">Go to Dashboard</a>
            <?php else: ?>
                <div style="font-size: 3rem; margin-bottom: 16px;">✗</div>
                <h1>Verification Failed</h1>
                <p class="subtitle"><?= htmlspecialchars($error) ?></p>
                <a href="/login" class="btn btn-secondary btn-lg mt-3">Log In</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
