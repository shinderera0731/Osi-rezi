<?php
// includes/messages.php
/**
 * メッセージ表示機能
 * 元のconfig.phpから分離
 */

/**
 * セッションに保存されたメッセージを表示
 */
function showMessage() {
    if (isset($_SESSION['message'])) {
        echo '<div class="alert success">' . $_SESSION['message'] . '</div>';
        unset($_SESSION['message']);
    }
    if (isset($_SESSION['error'])) {
        echo '<div class="alert error">' . $_SESSION['error'] . '</div>';
        unset($_SESSION['error']);
    }
    if (isset($_SESSION['warning'])) {
        echo '<div class="alert warning">' . $_SESSION['warning'] . '</div>';
        unset($_SESSION['warning']);
    }
}

/**
 * 成功メッセージを設定
 */
function setSuccessMessage($message) {
    $_SESSION['message'] = '✅ ' . $message;
}

/**
 * エラーメッセージを設定
 */
function setErrorMessage($message) {
    $_SESSION['error'] = '❌ ' . $message;
}

/**
 * 警告メッセージを設定
 */
function setWarningMessage($message) {
    $_SESSION['warning'] = '⚠️ ' . $message;
}

// メッセージを表示
showMessage();
?>