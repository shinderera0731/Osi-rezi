<?php
// index.php - ★最終リファクタリング版
// アプリケーションのメインエントランス（玄関）です。

// --- 共通設定の読み込み（新しい専門書を読み込む） ---
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/functions.php';
require_once 'includes/styles.php';
require_once 'includes/navigation.php';

// --- 専門家（サービスクラス）の読み込み ---
// ダッシュボードの統計情報を取得するために、在庫の専門家（シェフ）を呼び出します
require_once 'modules/inventory/InventoryService.php';

// データベーステーブルが存在するかチェック
$tablesExist = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'inventory'");
    if ($stmt->rowCount() > 0) {
        $tablesExist = true;
    }
} catch (PDOException $e) {
    $tablesExist = false;
}

// ログイン済みの場合のみ、ダッシュボードのデータを準備
$statistics = null;
$alerts = null;
if (isLoggedIn() && $tablesExist) {
    $inventoryService = new InventoryService($pdo);
    $statistics = $inventoryService->getStatistics();
    $alerts = $inventoryService->getStockAlerts();
}

?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ダッシュボード - Oshi-rezi</title>
</head>
<body>
    <div class="container">
       <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1><img src="images/osi-rezi2.png" alt="推しレジ" style="width: 40px; height: 40px; vertical-align: middle; margin-right: 10px; border-radius: 12px;">Osi-rezi</h1>
                    <p>ようこそ！</p>
                </div>
                <?php if (isLoggedIn()): ?>
                <div class="header-right">
                    <span class="user-info">👤 <?php echo h($_SESSION['username']); ?></span>
                    <a href="logout.php" class="btn danger header-btn">ログアウト</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="content">
            <?php require_once 'includes/messages.php'; ?>

            <?php if (!$tablesExist): ?>
                <!-- システム初期化が必要な場合 -->
                <div class="card">
                    <h3>🔧 システム初期化が必要です</h3>
                    <p style="margin-bottom: 20px;">データベーステーブルが見つかりません。以下のボタンでシステムを初期化してください。</p>
                    <form method="POST" action="create.php">
                        <input type="hidden" name="action" value="create_tables">
                        <button type="submit" class="btn success">システムを初期化する</button>
                    </form>
                    <div class="card" style="margin-top: 20px; background: #f8fafc;">
                        <h4 style="color: #4a90a4; margin-bottom: 15px;">初期化後、以下のテストアカウントが利用できます:</h4>
                        <p>管理者: <strong>admin / password</strong></p>
                        <p>スタッフ: <strong>staff / password</strong></p>
                    </div>
                </div>

            <?php elseif (!isLoggedIn()): ?>
                <!-- ログインが必要な場合 -->
                <div class="card" style="text-align: center;">
                    <h3>🔐 ログインが必要です</h3>
                    <p style="margin-bottom: 30px; font-size: 1.1em; line-height: 1.6;">
                        Oshi-reziの機能をご利用いただくには、まずログインしてください。<br>
                        シンプルで使いやすいPOSシステムがあなたをお待ちしています。
                    </p>
                    <a href="login.php" class="btn success" style="font-size: 1.1em; padding: 16px 32px;">ログインページへ</a>
                </div>

            <?php else: ?>
                <!-- ダッシュボード（ログイン済み） -->
                <?php if ($statistics && $alerts): ?>
                    <!-- アラート表示 -->
                    <?php if (count($alerts['low_stock']) > 0): ?>
                        <div class="alert warning">
                            <strong>⚠️ 在庫不足警告:</strong> <?php echo count($alerts['low_stock']); ?>件の商品が発注点を下回っています
                            <a href="select.php?tab=inventory" style="margin-left: 10px; color: #b8860b; text-decoration: underline;">詳細を確認</a>
                        </div>
                    <?php endif; ?>

                    <?php if (count($alerts['expiring']) > 0): ?>
                        <div class="alert warning">
                            <strong>📅 賞味期限警告:</strong> <?php echo count($alerts['expiring']); ?>件の商品が7日以内に期限切れになります
                            <a href="select.php?tab=inventory" style="margin-left: 10px; color: #b8860b; text-decoration: underline;">詳細を確認</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- メイン機能メニュー -->
                <div class="menu-grid">
                    <a href="pos.php" class="menu-item">
                        <span class="menu-icon">🛒</span>
                        <div class="menu-title">レジ・会計</div>
                        <div class="menu-description">商品の会計処理を実行</div>
                    </a>
                    <a href="select.php?tab=inventory" class="menu-item">
                        <span class="menu-icon">📦</span>
                        <div class="menu-title">在庫管理</div>
                        <div class="menu-description">在庫確認と商品管理</div>
                    </a>
                    <a href="select.php?tab=settlement" class="menu-item">
                        <span class="menu-icon">💰</span>
                        <div class="menu-title">点検・精算</div>
                        <div class="menu-description">日次の売上確認と精算処理</div>
                    </a>
                    <a href="select.php?tab=reports" class="menu-item">
                        <span class="menu-icon">📈</span>
                        <div class="menu-title">レポート</div>
                        <div class="menu-description">売上や商品別ランキングを確認</div>
                    </a>
                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                        <a href="select.php?tab=staff_management" class="menu-item">
                            <span class="menu-icon">🧑‍💻</span>
                            <div class="menu-title">スタッフ管理</div>
                            <div class="menu-description">スタッフ情報の確認・設定</div>
                        </a>
                        <!-- ★追加: API管理へのリンクを追加 -->
                        <a href="select.php?tab=api_keys" class="menu-item">
                            <span class="menu-icon">🔑</span>
                            <div class="menu-title">API管理</div>
                            <div class="menu-description">APIキーの生成と管理</div>
                        </a>
                        <a href="select.php?tab=settings" class="menu-item">
                            <span class="menu-icon">⚙️</span>
                            <div class="menu-title">設定</div>
                            <div class="menu-description">アプリケーションの各種設定</div>
                        </a>
                    <?php endif; ?>
                    <!-- 追加: 全取引履歴ボタン -->
                    <a href="select.php?tab=transactions" class="menu-item">
                        <span class="menu-icon">📋</span>
                        <div class="menu-title">取引履歴</div>
                        <div class="menu-description">過去の取引を一覧で確認</div>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
