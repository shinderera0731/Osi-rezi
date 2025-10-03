<?php
// config/database.php - 統一データベース接続
if (!isset($pdo)) {
    // データベース接続設定
    if ($_SERVER['SERVER_NAME'] === 'localhost') {
        $db_name = 'cafe_management';
        $db_host = 'localhost';
        $db_id   = 'root';
        $db_pw   = '';
    } else {
        $db_name = 'gs-cinderella_pos_system';
        $db_host = 'mysql3109.db.sakura.ne.jp';
        $db_id   = 'gs-cinderella_pos_system';
        $db_pw   = '';
    }

    // データベース接続（1回のみ実行）
    try {
        $server_info = 'mysql:dbname=' . $db_name . ';charset=utf8;host=' . $db_host;
        $pdo = new PDO($server_info, $db_id, $db_pw);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die('データベース接続エラー: ' . $e->getMessage());
    }
}

// 共通関数
function getSetting($pdo, $key, $default = null) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        error_log("Failed to get setting '{$key}': " . $e->getMessage());
        return $default;
    }
}

function initializeDatabase($pdo) {
    require_once __DIR__ . '/../database/schema.php';
    return createTables($pdo);
}
?>