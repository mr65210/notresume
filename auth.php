<?php
/**
 * Authentication & Security Helpers
 */
require_once __DIR__ . '/db.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate CSRF token
 */
function csrfToken(): string {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verify CSRF token
 */
function verifyCsrf(string $token): bool {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Get CSRF hidden input field
 */
function csrfField(): string {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars(csrfToken()) . '">';
}

/**
 * Get currently logged in user or null
 */
function currentUser(): ?array {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id, email, email_verified, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

/**
 * Require authenticated user, redirect to login if not
 */
function requireAuth(): array {
    $user = currentUser();
    if (!$user) {
        header('Location: ' . SITE_URL . '/login');
        exit;
    }
    return $user;
}

/**
 * Require verified email
 */
function requireVerified(): array {
    $user = requireAuth();
    if (!$user['email_verified']) {
        header('Location: ' . SITE_URL . '/dashboard?unverified=1');
        exit;
    }
    return $user;
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return currentUser() !== null;
}

/**
 * Register a new user
 */
function registerUser(string $email, string $password): array {
    $pdo = getDB();

    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([strtolower(trim($email))]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'An account with this email already exists.'];
    }

    // Validate
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Please enter a valid email address.'];
    }
    if (strlen($password) < 8) {
        return ['success' => false, 'error' => 'Password must be at least 8 characters.'];
    }

    // Create user
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    $verifyToken = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 86400); // 24 hours

    $stmt = $pdo->prepare("
        INSERT INTO users (email, password_hash, verify_token, verify_token_expires)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([strtolower(trim($email)), $hash, $verifyToken, $expires]);
    $userId = (int)$pdo->lastInsertId();

    // Send verification email
    $sent = sendVerificationEmail(strtolower(trim($email)), $verifyToken);

    return ['success' => true, 'user_id' => $userId, 'email_sent' => $sent];
}

/**
 * Log in a user
 */
function loginUser(string $email, string $password): array {
    $pdo = getDB();

    $stmt = $pdo->prepare("SELECT id, email, password_hash, email_verified FROM users WHERE email = ?");
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Invalid email or password.'];
    }

    $_SESSION['user_id'] = $user['id'];
    session_regenerate_id(true);

    return ['success' => true, 'user' => $user];
}

/**
 * Log out
 */
function logoutUser(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Send verification email via Resend API
 */
function sendVerificationEmail(string $email, string $token): bool {
    $verifyUrl = SITE_URL . '/verify?token=' . urlencode($token);

    $html = '
    <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif; max-width: 520px; margin: 0 auto; padding: 40px 20px;">
        <h1 style="font-size: 28px; font-weight: 700; color: #111; margin-bottom: 8px;">NotResume</h1>
        <p style="font-size: 16px; color: #555; line-height: 1.6;">Verify your email to start building your pages.</p>
        <a href="' . $verifyUrl . '" style="display: inline-block; margin: 24px 0; padding: 14px 32px; background: #111; color: #fff; text-decoration: none; border-radius: 8px; font-size: 16px; font-weight: 600;">Verify Email</a>
        <p style="font-size: 13px; color: #999; line-height: 1.5;">Or paste this link: ' . $verifyUrl . '</p>
        <p style="font-size: 13px; color: #999;">This link expires in 24 hours.</p>
    </div>';

    $data = [
        'from' => FROM_NAME . ' <' . FROM_EMAIL . '>',
        'to'   => [$email],
        'subject' => 'Verify your NotResume account',
        'html' => $html
    ];

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . RESEND_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode >= 200 && $httpCode < 300;
}

/**
 * Resend verification email
 */
function resendVerification(int $userId): array {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT email, email_verified FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'error' => 'User not found.'];
    }
    if ($user['email_verified']) {
        return ['success' => false, 'error' => 'Email is already verified.'];
    }

    $verifyToken = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 86400);

    $stmt = $pdo->prepare("UPDATE users SET verify_token = ?, verify_token_expires = ? WHERE id = ?");
    $stmt->execute([$verifyToken, $expires, $userId]);

    $sent = sendVerificationEmail($user['email'], $verifyToken);

    return ['success' => $sent, 'error' => $sent ? null : 'Failed to send email.'];
}

/**
 * Sanitize HTML output - remove dangerous elements/attributes
 */
function sanitizePageHtml(string $html): string {
    // Remove script tags and contents
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);

    // Remove event handlers (on*)
    $html = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
    $html = preg_replace('/\s+on\w+\s*=\s*\S+/i', '', $html);

    // Remove javascript: URLs
    $html = preg_replace('/href\s*=\s*["\']javascript:[^"\']*["\']/i', 'href="#"', $html);
    $html = preg_replace('/src\s*=\s*["\']javascript:[^"\']*["\']/i', '', $html);

    // Remove data: URLs in src (except images)
    $html = preg_replace('/src\s*=\s*["\']data:(?!image\/)[^"\']*["\']/i', '', $html);

    // Remove <object>, <embed>, <applet>, <form>, <input type="hidden">
    $html = preg_replace('/<(object|embed|applet|iframe|frame|frameset|meta|link|base)\b[^>]*>.*?<\/\1>/is', '', $html);
    $html = preg_replace('/<(object|embed|applet|iframe|frame|frameset|meta|link|base)\b[^>]*\/?>/i', '', $html);

    // Remove form elements
    $html = preg_replace('/<form\b[^>]*>.*?<\/form>/is', '', $html);
    $html = preg_replace('/<(input|textarea|select|button)\b[^>]*\/?>/i', '', $html);

    // Remove style attributes with expressions/url
    $html = preg_replace('/style\s*=\s*["\'][^"\']*expression\s*\([^"\']*["\']/i', '', $html);
    $html = preg_replace('/style\s*=\s*["\'][^"\']*url\s*\(\s*["\']?javascript:[^"\']*["\']/i', '', $html);

    // Remove import
    $html = preg_replace('/@import\s/i', '', $html);

    // Remove vbscript
    $html = preg_replace('/vbscript\s*:/i', '', $html);

    return $html;
}

/**
 * Flash messages
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Validate slug
 */
function isValidSlug(string $slug): bool {
    if (strlen($slug) < 3 || strlen($slug) > 100) return false;
    if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/', $slug)) return false;
    if (in_array(strtolower($slug), unserialize(RESERVED_SLUGS))) return false;
    return true;
}

/**
 * Check if slug is available
 */
function isSlugAvailable(string $slug, ?int $excludePageId = null): bool {
    $pdo = getDB();
    $sql = "SELECT id FROM pages WHERE slug = ?";
    $params = [$slug];

    if ($excludePageId) {
        $sql .= " AND id != ?";
        $params[] = $excludePageId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return !$stmt->fetch();
}

/**
 * JSON response helper
 */
function jsonResponse(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
