<?php
// includes/navigation.php
/**
 * 共通ナビゲーション
 * 元のconfig.phpから分離
 */

/**
 * ナビゲーションを生成
 * @param string $current_page 現在のページ名
 * @return string HTMLナビゲーション
 */
/*function getNavigation($current_page = '') {
    $nav_html = '
    <div class="nav">
        <div class="nav-left">
            <a href="index.php"' . ($current_page === 'index' ? ' class="active"' : '') . '>🏠 ホーム</a>';

    if (isLoggedIn()) {
        $nav_html .= '
            <a href="pos.php"' . ($current_page === 'pos' ? ' class="active"' : '') . '>🛒 レジ</a>
            <a href="inventory.php"' . ($current_page === 'inventory' ? ' class="active"' : '') . '>📦 在庫管理</a>
            <a href="reports.php"' . ($current_page === 'reports' ? ' class="active"' : '') . '>📊 レポート</a>
            <a href="select.php"' . ($current_page === 'select' ? ' class="active"' : '') . '>⚙️ 設定</a>
        </div>
        <div>
            <span style="margin-right: 15px; color: #666;">👤 ' . htmlspecialchars($_SESSION['username']) . '</span>
            <a href="logout.php" class="btn danger">ログアウト</a>
        </div>';
    } else {
        $nav_html .= '
        </div>
        <div>
            <a href="login.php" class="btn">ログイン</a>
        </div>';
    }
    $nav_html .= '</div>';
    return $nav_html;
}*/

/**
 * 簡素版ナビゲーション（ホームボタンのみ）
 */
function getSimpleNavigation() {
    if (!isLoggedIn()) {
        return '';
    }
    
    $nav_html = '
    <div class="nav simple-nav">
        <div class="nav-left">
            <a href="index.php" class="home-only-btn">🏠 ホーム</a>
        </div>
        
    </div>';
    
    return $nav_html;
}

// 既存のgetNavigation関数を修正
if (!function_exists('getNavigation')) {
    function getNavigation($current_page = '') {
        // ホーム画面ではナビゲーションを表示しない
        if ($current_page === 'index') {
            return '';
        }
        
        // 他の画面では簡素版を表示
        return getSimpleNavigation();
    }
}
