<?php
// ============================================
// iAcademy Lost & Found — Logout
// auth/logout.php
// ============================================

session_start();
require_once __DIR__ . '/../config/db.php';

if (isset($_SESSION['user_id'])) {
    // Log logout activity
    try {
        $pdo = getPDO();
        $pdo->prepare(
            "INSERT INTO activity_log (actor_id, action, target_type, target_id, ip_address)
             VALUES (?, 'user.logout', 'user', ?, ?)"
        )->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Exception $e) { /* silent */ }
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

header('Location: login.php?logged_out=1');
exit;
