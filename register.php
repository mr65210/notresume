<?php
require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    header('Location: /dashboard');
    exit;
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if ($password !== $passwordConfirm) {
            $error = 'Passwords do not match.';
        } else {
            $result = registerUser($email, $password);
            if ($result['success']) {
                // Auto-login
                $_SESSION['user_id'] = $result['user_id'];
                session_regenerate_id(true);
                setFlash('success', 'Account created! Please check your email to verify your address.');
                header('Location: /dashboard');
                exit;
            } else {
                $error = $result['error'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — NotResume</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>◆</text></svg>">
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="/" class="logo">◆ NotResume</a>
            <nav class="nav-links">
                <a href="/login">Log in</a>
            </nav>
        </div>
    </header>

    <div class="auth-wrapper">
        <div class="auth-card">
            <h1>Get started</h1>
            <p class="subtitle">Create your account to build your page.</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="on">
                <?= csrfField() ?>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-input"
                           placeholder="you@company.com" required autofocus
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-input"
                           placeholder="At least 8 characters" required minlength="8">
                </div>

                <div class="form-group">
                    <label for="password_confirm">Confirm Password</label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-input"
                           placeholder="Type your password again" required minlength="8">
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">Create Account</button>
            </form>

            <div class="auth-footer">
                Already have an account? <a href="/login">Log in</a>
            </div>
        </div>
    </div>
</body>
</html>
