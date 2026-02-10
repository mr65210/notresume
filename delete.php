<?php
require_once __DIR__ . '/auth.php';

$user = requireAuth();
$pdo = getDB();

$pageId = (int)($_GET['id'] ?? 0);
if (!$pageId) {
    header('Location: /dashboard');
    exit;
}

$stmt = $pdo->prepare("SELECT id, title FROM pages WHERE id = ? AND user_id = ?");
$stmt->execute([$pageId, $user['id']]);
$page = $stmt->fetch();

if (!$page) {
    setFlash('error', 'Page not found.');
    header('Location: /dashboard');
    exit;
}

// Delete
$stmt = $pdo->prepare("DELETE FROM pages WHERE id = ? AND user_id = ?");
$stmt->execute([$pageId, $user['id']]);

setFlash('success', '"' . $page['title'] . '" has been deleted.');
header('Location: /dashboard');
exit;
