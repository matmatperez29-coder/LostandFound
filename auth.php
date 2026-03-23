<?php
// ============================================
// iAcademy Lost & Found — Auth Guard
// config/auth.php
// ============================================

function requireLogin(string $role = ''): void {
    if (session_status() === PHP_SESSION_NONE) session_start();

    if (empty($_SESSION['user_id'])) {
        header('Location: ' . getLoginUrl() . '?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }

    if ($role && $_SESSION['role'] !== $role) {
        // Wrong role — send to their dashboard
        $dest = $_SESSION['role'] === 'admin' ? '../admin/dashboard.php' : '../user/dashboard.php';
        header('Location: ' . $dest);
        exit;
    }
}

function getLoginUrl(): string {
    // Resolve relative path to login from anywhere
    $depth = substr_count($_SERVER['SCRIPT_NAME'], '/') - 1;
    return str_repeat('../', $depth) . 'auth/login.php';
}

function currentUser(): array {
    return [
        'id'         => $_SESSION['user_id']    ?? 0,
        'first_name' => $_SESSION['first_name'] ?? '',
        'last_name'  => $_SESSION['last_name']  ?? '',
        'email'      => $_SESSION['email']      ?? '',
        'role'       => $_SESSION['role']       ?? 'user',
    ];
}
