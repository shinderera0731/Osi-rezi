<?php
// sales_reports.php - ★最終リファクタリング版

// --- 共通設定の読み込み ---
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/functions.php';
require_once 'includes/styles.php';
require_once 'includes/navigation.php';

// --- 専門家（サービスクラス）の読み込み ---
require_once 'modules/reports/ReportsService.php';

// ログイン必須
requireLogin();

// --- 期間設定をドロップダウン選択に対応 ---
$period = $_GET['period'] ?? 'daily';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('n');

// 期間に応じて開始日・終了日を計算
switch ($period) {
    case 'monthly':
        $start_date = sprintf('%04d-%02d-01', $year, $month);
        $end_date = date('Y-m-t', strtotime($start_date));
        break;
    case 'yearly':
        $start_date = sprintf('%04d-01-01', $year);
        $end_date = sprintf('%04d-12-31', $year);
        break;
    default: // daily
        $start_date = sprintf('%04d-%02d-01', $year, $month);
        $end_date = date('Y-m-t', strtotime($start_date));
        break;
}

// --- レポートのシェフを呼び出し、データ（料理）を準備 ---
$reportsService = new ReportsService($pdo);
$reportData = $reportsService->getDetailedReport($start_date, $end_date);
// 取引履歴も取得
$transactions = $reportsService->getTransactionsForPeriod($start_date, $end_date); // 新しく追加

// データを使いやすいように変数に展開
$total_sales   = $reportData['total_sales'];
$staff_sales   = $reportData['staff_sales'];
$product_sales = $reportData['product_sales'];
$daily_sales   = $reportData['daily_sales'];
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>売上・歩合レポート - Oshi-rezi</title>
    <style>
        .filter-form { 
            background: #f8f9fa; 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            display: flex; 
            gap: 15px; 
            align-items: center; 
            flex-wrap: wrap; 
        }
        .filter-form select {
            padding: 8px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.9rem;
            background: white;
        }
        .filter-form label {
            font-size: 0.9rem;
            font-weight: 500;
            color: #374151;
        }
    </style>
</head>
<body>
    <div class="container">
       
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1><img src="images/osi-rezi2.png" alt="推しレジ" style="width: 40px; height: 40px; vertical-align: middle; margin-right: 10px; border-radius: 12px;">Osi-rezi</h1>
                    <p>詳細レポート</p>
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

            <!-- 期間選択 - ReportsView.phpのスタイルに統一 -->
            <div class="card">
                <h3>📊 レポート期間設定</h3>
                <div class="filter-form">
                    <form method="GET" id="reportFilterForm" style="display: flex; gap: 15px; align-items: center;">
                        <label>集計対象:</label>
                        <select name="period" id="periodSelector" style="padding: 8px;">
                            <option value="daily" <?php if (($_GET['period'] ?? 'daily') === 'daily') echo 'selected'; ?>>日別</option>
                            <option value="monthly" <?php if (($_GET['period'] ?? '') === 'monthly') echo 'selected'; ?>>月別</option>
                            <option value="yearly" <?php if (($_GET['period'] ?? '') === 'yearly') echo 'selected'; ?>>年度別</option>
                        </select>
                        
                        <span id="yearGroup">
                            <label>年:</label>
                            <select name="year" id="yearSelector" style="padding: 8px;">
                                <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php if ($y == ($_GET['year'] ?? date('Y'))) echo 'selected'; ?>><?php echo $y; ?>年</option>
                                <?php endfor; ?>
                            </select>
                        </span>
                        
                        <span id="monthGroup">
                            <label>月:</label>
                            <select name="month" id="monthSelector" style="padding: 8px;">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php if ($m == ($_GET['month'] ?? date('n'))) echo 'selected'; ?>><?php echo $m; ?>月</option>
                                <?php endfor; ?>
                            </select>
                        </span>
                        
                        <button type="submit" class="btn">📈 レポート更新</button>
                    </form>
                </div>
            </div>

            <!-- サマリーカード (非表示にしました) -->
            <!--
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo formatPrice($total_sales['total_sales'] ?? 0); ?></div>
                    <div class="stat-label">総売上</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo formatPrice($total_sales['total_commission'] ?? 0); ?></div>
                    <div class="stat-label">総歩合額</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo ($total_sales['transaction_count'] ?? 0); ?>件</div>
                    <div class="stat-label">取引件数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo formatPrice($total_sales['avg_transaction'] ?? 0); ?></div>
                    <div class="stat-label">平均客単価</div>
                </div>
            </div>
            -->

            <!-- 日別売上推移 -->
            <div class="card">
                <h3>📈 日別売上推移</h3>
                <?php if (!empty($daily_sales)): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>日付</th>
                                    <th>取引件数</th>
                                    <th>売上金額</th>
                                    <th>歩合額</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($daily_sales as $daily): ?>
                                    <tr>
                                        <td><?php echo formatDate($daily['sale_date'], 'Y年m月d日(D)'); ?></td>
                                        <td><?php echo $daily['daily_transaction_count']; ?>件</td>
                                        <td><?php echo formatPrice($daily['daily_sales']); ?></td>
                                        <td class="commission-highlight"><?php echo formatPrice($daily['daily_commission']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center" style="padding: 20px;">データがありません。</p>
                <?php endif; ?>
            </div>

            <!-- スタッフ別実績 -->
            <div class="card">
                <h3>👥 スタッフ別実績</h3>
                <?php if (!empty($staff_sales)): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>スタッフ名</th>
                                    <th>売上金額</th>
                                    <th>歩合額</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staff_sales as $staff): ?>
                                    <tr>
                                        <td><strong><?php echo h($staff['staff_name']); ?></strong></td>
                                        <td><?php echo formatPrice($staff['staff_total_sales']); ?></td>
                                        <td class="commission-highlight"><?php echo formatPrice($staff['staff_commission']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center" style="padding: 20px;">データがありません。</p>
                <?php endif; ?>
            </div>

            <!-- 商品別実績 -->
            <div class="card">
                <h3>📦 商品別実績（トップ10）</h3>
                <?php if (!empty($product_sales)): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>商品名</th>
                                    <th>販売数量</th>
                                    <th>売上金額</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($product_sales as $product): ?>
                                    <tr>
                                        <td><strong><?php echo h($product['item_name']); ?></strong></td>
                                        <td><?php echo $product['total_quantity']; ?>個</td>
                                        <td><?php echo formatPrice($product['product_sales']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center" style="padding: 20px;">データがありません。</p>
                <?php endif; ?>
            </div>

            <!-- 取引履歴を追加 -->
            <div class="card">
                <h3>🛒 取引履歴</h3>
                <?php if (!empty($transactions)): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>取引日時</th>
                                    <th>担当スタッフ</th>
                                    <th>取引ID</th>
                                    <th>合計金額</th>
                                    <th>受取金額</th>
                                    <th>お釣り</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo formatDateTime($transaction['transaction_date']); ?></td>
                                        <td><?php echo h($transaction['username'] ?? 'ゲスト'); ?></td>
                                        <td><?php echo h($transaction['id']); ?></td>
                                        <td><?php echo formatPrice($transaction['total_amount']); ?></td>
                                        <td><?php echo formatPrice($transaction['cash_received']); ?></td>
                                        <td><?php echo formatPrice($transaction['change_given']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center" style="padding: 20px;">データがありません。</p>
                <?php endif; ?>
            </div>

            <!-- 戻るボタン -->
            <div style="text-align: center; margin-top: 30px;">
                <a href="select.php?tab=reports" class="btn">レポート画面に戻る</a>
            </div>

        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const periodSelector = document.getElementById('periodSelector');
        const yearGroup = document.getElementById('yearGroup');
        const monthGroup = document.getElementById('monthGroup');
        
        function toggleSelectors() {
            const selectedPeriod = periodSelector.value;
            
            // 年度別の場合は年と月を非表示
            if (selectedPeriod === 'yearly') {
                yearGroup.style.display = 'none';
                monthGroup.style.display = 'none';
            } else if (selectedPeriod === 'monthly') {
                // 月別の場合は年のみ表示
                yearGroup.style.display = 'inline';
                monthGroup.style.display = 'none';
            } else {
                // 日別の場合は年月両方表示
                yearGroup.style.display = 'inline';
                monthGroup.style.display = 'inline';
            }
        }
        
        periodSelector.addEventListener('change', toggleSelectors);
        toggleSelectors(); // 初期化時に実行
    });
    </script>
</body>
</html>