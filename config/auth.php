<?php
// config/auth.php
/**
 * 認証・セッション管理
 * 元のconfig.phpから分離
 */

// セッション設定
ini_set('session.use_strict_mode', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    session_regenerate_id(true); // セッション固定攻撃対策
}

/**
 * ユーザーがログインしているかチェック
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * ログインが必要な場合にリダイレクト
 */
function requireLogin($redirect_to = 'login.php') {
    if (!isLoggedIn()) {
        $_SESSION['error'] = 'ログインが必要です。';
        header('Location: ' . $redirect_to);
        exit();
    }
}

/**
 * 管理者権限が必要な場合にチェック
 */
function requireAdmin() {
    requireLogin();
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        $_SESSION['error'] = 'この操作には管理者権限が必要です。';
        header('Location: index.php');
        exit();
    }
}

/**
 * ユーザー情報を取得
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['user_role']
    ];
}

/**
 * ログイン処理
 */
function authenticateUser($pdo, $username, $password) {
    try {
        $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            session_regenerate_id(true);
            return true;
        }
        return false;
    } catch (PDOException $e) {
        error_log("Authentication Error: " . $e->getMessage());
        return false;
    }
}

/**
 * ログアウト処理
 */
function logoutUser() {
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-42000, '/');
    }
    session_destroy();
}
?>