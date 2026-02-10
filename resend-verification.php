<?php
/**
 * Resend Verification - redirects to dashboard with action
 */
require_once __DIR__ . '/auth.php';

$user = requireAuth();

if ($user['email_verified']) {
    header('Location: /dashboard');
    exit;
}

$result = resendVerification($user['id']);
if ($result['success']) {
    setFlash('success', 'Verification email sent! Check your inbox.');
} else {
    setFlash('error', $result['error'] ?? 'Could not send verification email. Please try again later.');
}

header('Location: /dashboard');
exit;
