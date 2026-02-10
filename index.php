<?php
require_once __DIR__ . '/auth.php';
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NotResume — Your Story, Not a Resume</title>
    <meta name="description" content="Create stunning one-page profiles that let recruiters see the real you. Not a resume — something better.">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>◆</text></svg>">
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="/" class="logo">◆ NotResume</a>
            <nav class="nav-links">
                <?php if ($user): ?>
                    <a href="/dashboard">Dashboard</a>
                    <a href="/logout" class="btn-ghost">Log out</a>
                <?php else: ?>
                    <a href="/login">Log in</a>
                    <a href="/register" class="btn-primary">Get Started</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero container">
            <h1>Not a resume.<br>Your story.</h1>
            <p>Create a beautiful one-page profile powered by AI. Share a single link with recruiters and stand out from the stack.</p>
            <div class="hero-cta">
                <?php if ($user): ?>
                    <a href="/create" class="btn btn-primary btn-lg">Create a Page</a>
                    <a href="/dashboard" class="btn btn-secondary btn-lg">Dashboard</a>
                <?php else: ?>
                    <a href="/register" class="btn btn-primary btn-lg">Create Your Page — Free</a>
                    <a href="/login" class="btn btn-secondary btn-lg">Log In</a>
                <?php endif; ?>
            </div>

            <div class="hero-features">
                <div class="hero-feature card">
                    <div class="feature-icon">✦</div>
                    <h3>AI-Powered</h3>
                    <p>Paste your resume or describe yourself. AI builds a stunning page in seconds.</p>
                </div>
                <div class="hero-feature card">
                    <div class="feature-icon">◇</div>
                    <h3>One Link</h3>
                    <p>notresume.com/yourname — a clean, memorable link for your profile.</p>
                </div>
                <div class="hero-feature card">
                    <div class="feature-icon">⬡</div>
                    <h3>Stand Out</h3>
                    <p>Beautiful, responsive pages that tell your story far better than a PDF.</p>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>© <?= date('Y') ?> NotResume. Your story, beautifully told.</p>
        </div>
    </footer>
</body>
</html>
