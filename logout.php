<?php
session_start();
require_once "includes/db.php";
require_once "includes/functions.php";

if (!empty($_COOKIE['remember_me'])) {
    $parts = explode(':', $_COOKIE['remember_me']);
    if (count($parts) === 2) {
        $selector = $parts[0];
        $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE selector = ?");
        $stmt->execute([$selector]);
    }

    setcookie(
        'remember_me',
        '',
        [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
}

if (isset($_SESSION['user_id'])) {
    logActivity($pdo, $_SESSION['user_id'], "Logged out");
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}
session_destroy();

header("Location: login.php");
exit;
