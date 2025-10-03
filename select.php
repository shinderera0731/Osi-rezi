<?php
// select.php

// --- 共通設定の読み込み ---
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/functions.php';
require_once 'includes/styles.php';
require_once 'includes/navigation.php';

// --- 専門家（サービスクラス）の読み込み ---
require_once 'modules/inventory/InventoryService.php';
require_once 'modules/reports/ReportsService.php';
require_once 'modules/settlement/SettlementService.php';
require_once 'modules/staff/StaffService.php';
require_once 'modules/api/ApiKeyService.php';

requireLogin();

$active_tab = $_GET['tab'] ?? 'inventory';

// --- タブに応じたデータ準備 ---
switch ($active_tab) {
    case 'inventory':
        $inventoryService = new InventoryService($pdo);
        $filters = [
            'category' => $_GET['category'] ?? '',
            'status' => $_GET['status'] ?? '',
            'search_keyword' => trim($_GET['search_keyword'] ?? '')
        ];
        $inventory_items = $inventoryService->getInventoryList($filters);
        $low_stock_items = $inventoryService->getStockAlerts()['low_stock'];
        $expiring_items = $inventoryService->getStockAlerts()['expiring'];
        $recent_movements = $inventoryService->getStockMovements();
        $categories_data = $inventoryService->getCategories();
        $statistics = $inventoryService->getStatistics();
        break;

    case 'reports':
        $reportsService = new ReportsService($pdo);
        $period = $_GET['period'] ?? 'daily';
        $year = $_GET['year'] ?? date('Y');
        $month = $_GET['month'] ?? date('n');
        
        $reportData = $reportsService->getComprehensiveReportData($period, $year, $month);
        extract($reportData);
        break;

    case 'settlement':
        $settlementService = new SettlementService($pdo);
        $settlementPageData = $settlementService->getSettlementData();
        extract($settlementPageData);
        break;
        
    case 'transactions':
        $reportsService = new ReportsService($pdo);
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-t');
        $transactions = $reportsService->getTransactionsForPeriod($start_date, $end_date);
        break;

    case 'staff_management':
        requireAdmin();
        $staffService = new StaffService($pdo);
        $all_users = $staffService->getAllStaffDetails();
        break;
    
    case 'api_keys':
        requireAdmin();
        $apiKeyService = new ApiKeyService($pdo);
        $api_keys = $apiKeyService->getAllApiKeys();
        $users = $apiKeyService->getAllUsers();
        break;
        
    case 'settings':
        requireAdmin();
        break;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理画面 - Oshi-rezi</title>
</head>
<body>
    <div class="container">
      <div class="header">
        <div class="header-content">
            <div class="header-left">
                <h1><img src="images/osi-rezi2.png" alt="推しレジ" style="width: 40px; height: 40px; vertical-align: middle; margin-right: 10px; border-radius: 12px;">Osi-rezi</h1>
                <p>総合管理画面</p>
            </div>
            <div class="header-right">
                <span class="user-info">👤 <?php echo h($_SESSION['username']); ?></span>
                <a href="logout.php" class="btn danger header-btn">ログアウト</a>
            </div>
        </div>
      </div>
        <div class="content">
            <?php echo getSimpleNavigation(); ?>
            <?php require_once 'includes/messages.php'; ?>

            <div class="tab-buttons">
                <a href="select.php?tab=inventory" class="tab-button <?php echo $active_tab === 'inventory' ? 'active' : ''; ?>">📦 在庫管理</a>
                <a href="select.php?tab=reports" class="tab-button <?php echo $active_tab === 'reports' ? 'active' : ''; ?>">📈 レポート</a>
                <a href="select.php?tab=settlement" class="tab-button <?php echo $active_tab === 'settlement' ? 'active' : ''; ?>">💰 精算</a>
                <a href="select.php?tab=transactions" class="tab-button <?php echo $active_tab === 'transactions' ? 'active' : ''; ?>">📋 取引履歴</a>
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                <a href="select.php?tab=staff_management" class="tab-button <?php echo $active_tab === 'staff_management' ? 'active' : ''; ?>">🧑‍💻 スタッフ管理</a>
                <a href="select.php?tab=api_keys" class="tab-button <?php echo $active_tab === 'api_keys' ? 'active' : ''; ?>">🔑 API管理</a>
                <a href="select.php?tab=settings" class="tab-button <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">⚙️ 設定</a>
                <?php endif; ?>
            </div>

            <div class="tab-content <?php if ($active_tab === 'inventory') echo 'active'; ?>">
                <?php if ($active_tab === 'inventory') include 'modules/inventory/InventoryView.php'; ?>
            </div>
            <div class="tab-content <?php if ($active_tab === 'reports') echo 'active'; ?>">
                <?php if ($active_tab === 'reports') include 'modules/reports/ReportsView.php'; ?>
            </div>
            <div class="tab-content <?php if ($active_tab === 'settlement') echo 'active'; ?>">
                <?php if ($active_tab === 'settlement') include 'modules/settlement/SettlementView.php'; ?>
            </div>
            <div class="tab-content <?php if ($active_tab === 'transactions') echo 'active'; ?>">
                <?php if ($active_tab === 'transactions') include 'modules/reports/TransactionsView.php'; ?>
            </div>
            <div class="tab-content <?php if ($active_tab === 'staff_management') echo 'active'; ?>">
                <?php if ($active_tab === 'staff_management') include 'modules/staff/StaffView.php'; ?>
            </div>
            <div class="tab-content <?php if ($active_tab === 'api_keys') echo 'active'; ?>">
                <?php if ($active_tab === 'api_keys') include 'modules/api/ApiKeysView.php'; ?>
            </div>
            <div class="tab-content <?php if ($active_tab === 'settings') echo 'active'; ?>">
                <?php if ($active_tab === 'settings') include 'modules/settings/SettingsView.php'; ?>
            </div>
        </div>
    </div>
</body>
</html>