<?php
// modules/reports/ReportsView.php
?>
<style>
    .report-menu { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .report-item { background: white; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0; text-align: left; transition: all 0.2s ease; color: #333; cursor: pointer; display: flex; flex-direction: column; justify-content: center; min-height: 120px; }
    .report-item:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,0.1); border-color: #0d9488; }
    .report-item.active { border-color: #0d9488; background: #f0fdfa; border-width: 2px; }
    .report-title { font-size: 1.25rem; font-weight: 600; }
    .report-description { font-size: 0.875rem; color: #6b7280; }
    .report-content { display: none; animation: fadeIn 0.3s ease; }
    .report-content.active { display: block; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .chart-container { width: 100%; max-width: 900px; margin: auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); min-height: 450px; }
    .filter-form { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="card">
    <h3>📊 レポート・分析</h3>
    
    <div class="filter-form">
        <form id="reportFilterForm" method="GET" style="display: flex; gap: 15px; align-items: center;">
            <input type="hidden" name="tab" value="reports">
            <label>集計対象:</label>
            <select name="period" id="periodSelector" class="form-group input" style="padding: 8px;">
                <option value="daily" <?php if (($_GET['period'] ?? 'daily') === 'daily') echo 'selected'; ?>>日別</option>
                <option value="monthly" <?php if (($_GET['period'] ?? '') === 'monthly') echo 'selected'; ?>>月別</option>
                <option value="yearly" <?php if (($_GET['period'] ?? '') === 'yearly') echo 'selected'; ?>>年度別</option>
            </select>
            <select name="year" id="yearSelector" class="form-group input" style="padding: 8px;">
                <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                <option value="<?php echo $y; ?>" <?php if ($y == ($_GET['year'] ?? date('Y'))) echo 'selected'; ?>><?php echo $y; ?>年</option>
                <?php endfor; ?>
            </select>
            <select name="month" id="monthSelector" class="form-group input" style="padding: 8px;">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?php echo $m; ?>" <?php if ($m == ($_GET['month'] ?? date('n'))) echo 'selected'; ?>><?php echo $m; ?>月</option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn">表示する</button>
        </form>
    </div>

    <div class="report-menu">
        <div class="report-item" onclick="showView('daily')" id="daily-tab">
            <div class="report-title">📈 売上推移</div>
        </div>
        <div class="report-item" onclick="showView('staff')" id="staff-tab">
            <div class="report-title">👥 スタッフ別歩合実績</div>
        </div>
        <div class="report-item" onclick="showView('products')" id="products-tab">
            <div class="report-title">📦 商品別分析</div>
        </div>
    </div>

    <div id="daily-view" class="report-content"><div class="chart-container"><canvas id="salesTrendChart"></canvas></div><p style="text-align: center; margin-top: 20px;"><a href="sales_reports.php" class="btn">詳細レポートを見る</a></p></div>
    <div id="staff-view" class="report-content"><div class="chart-container"><canvas id="staffPerformanceChart"></canvas></div><p style="text-align: center; margin-top: 20px;"><a href="sales_reports.php" class="btn">詳細レポートを見る</a></p></div>
    <div id="products-view" class="report-content"><div class="chart-container"><canvas id="productPerformanceChart"></canvas></div><p style="text-align: center; margin-top: 20px;"><a href="sales_reports.php" class="btn">詳細レポートを見る</a></p></div>
</div>

<script>
function showView(viewType) {
    document.querySelectorAll('.report-content').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.report-item').forEach(i => i.classList.remove('active'));
    document.getElementById(viewType + '-view').classList.add('active');
    document.getElementById(viewType + '-tab').classList.add('active');
}

document.addEventListener('DOMContentLoaded', function() {
    showView('daily');

    const trendData = <?php echo json_encode($trend_data ?? []); ?>;
    const period = '<?php echo $_GET['period'] ?? 'daily'; ?>';
    const monthSalesData = <?php echo json_encode($month_sales ?? []); ?>;
    const staffPerformanceData = <?php echo json_encode($staff_performance ?? []); ?>;
    const productPerformanceData = <?php echo json_encode(array_slice($product_performance ?? [], 0, 5)); ?>;

    const periodSelector = document.getElementById('periodSelector');
    const yearSelector = document.getElementById('yearSelector');
    const monthSelector = document.getElementById('monthSelector');
    function toggleSelectors() {
        const selectedPeriod = periodSelector.value;
        yearSelector.style.display = (selectedPeriod === 'yearly') ? 'none' : 'block';
        monthSelector.style.display = (selectedPeriod === 'daily') ? 'block' : 'none';
    }
    periodSelector.addEventListener('change', toggleSelectors);
    toggleSelectors();

    // 売上推移グラフ
    if (trendData && trendData.length > 0) {
        let labels = [];
        let salesData = [];
        let transactionData = [];

        if (period === 'daily') {
            labels = trendData.map(item => new Date(item.sale_date).getDate());
            salesData = trendData.map(item => item.sales);
            transactionData = trendData.map(item => item.transactions);
        } else if (period === 'monthly') {
            labels = trendData.map(item => item.month_num + '月');
            salesData = trendData.map(item => item.sales);
            transactionData = trendData.map(item => item.transactions);
        } else if (period === 'yearly') {
            labels = trendData.map(item => item.year_num + '年');
            salesData = trendData.map(item => item.sales);
            transactionData = trendData.map(item => item.transactions);
        }

        new Chart(document.getElementById('salesTrendChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: '売上', data: salesData, backgroundColor: '#0ea5e9', yAxisID: 'y-sales', order: 2 },
                    { label: '客数', data: transactionData, type: 'line', borderColor: '#cbd5e1', yAxisID: 'y-transactions', order: 1, tension: 0.3 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: false } },
                    'y-sales': { type: 'linear', position: 'right', title: { display: true, text: '売上(万円)' }, grid: { drawOnChartArea: false }, ticks: { callback: value => (value / 10000) } },
                    'y-transactions': { type: 'linear', position: 'left', title: { display: true, text: '客数(件)' }, ticks: { stepSize: 2, beginAtZero: true } }
                }
            }
        });
    }

    if (staffPerformanceData && staffPerformanceData.length > 0) {
        new Chart(document.getElementById('staffPerformanceChart'), {
            type: 'bar',
            data: {
                labels: staffPerformanceData.map(s => s.username),
                datasets: [{ label: '歩合額 (円)', data: staffPerformanceData.map(s => s.staff_commission), backgroundColor: '#10b981' }]
            },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, scales: { x: { beginAtZero: true, ticks: { callback: value => '¥' + value.toLocaleString() } } } }
        });
    }

    if (productPerformanceData && productPerformanceData.length > 0) {
        new Chart(document.getElementById('productPerformanceChart'), {
            type: 'bar',
            data: {
                labels: productPerformanceData.map(p => p.item_name),
                datasets: [{ label: '売上 (円)', data: productPerformanceData.map(p => p.product_sales), backgroundColor: '#fb923c' }]
            },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, scales: { x: { beginAtZero: true, ticks: { callback: value => '¥' + value.toLocaleString() } } } }
        });
    }
});
</script>