<?php
// modules/reports/TransactionsView.php
// このファイルは取引履歴のビューを担当します。

// select.phpから変数が渡されることを想定
// $transactions = [];

?>
<div class="card">
    <h3>📋 取引履歴</h3>
    <p>過去の取引を期間で絞り込んで確認できます。</p>

    <form method="GET" class="period-selector" style="display:flex; gap: 15px; align-items: center; flex-wrap: wrap; margin-top: 20px;">
        <input type="hidden" name="tab" value="transactions">
        <div>
            <label>開始日:</label>
            <input type="date" name="start_date" value="<?php echo h($_GET['start_date'] ?? date('Y-m-01')); ?>">
        </div>
        <div>
            <label>終了日:</label>
            <input type="date" name="end_date" value="<?php echo h($_GET['end_date'] ?? date('Y-m-t')); ?>">
        </div>
        <div>
            <button type="submit" class="btn">🔍 検索</button>
        </div>
    </form>

    <?php if (!empty($transactions)): ?>
        <div class="table-container" style="margin-top: 20px;">
            <table>
                <thead>
                    <tr>
                        <th>取引日時</th>
                        <th>担当スタッフ</th>
                        <th>合計金額</th>
                        <th>受取金額</th>
                        <th>お釣り</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><?php echo formatDateTime($transaction['transaction_date']); ?></td>
                            <td><?php echo h($transaction['username'] ?? 'ゲスト'); ?></td>
                            <td><?php echo formatPrice($transaction['total_amount']); ?></td>
                            <td><?php echo formatPrice($transaction['cash_received']); ?></td>
                            <td><?php echo formatPrice($transaction['change_given']); ?></td>
                            <td>
                                <?php if ($transaction['is_deleted'] ?? false): ?>
                                    <span class="status-badge status-low">削除済み</span>
                                <?php else: ?>
                                    <form method="POST" action="create.php" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_transaction">
                                        <input type="hidden" name="transaction_id" value="<?php echo h($transaction['id']); ?>">
                                        <button type="submit" class="btn danger btn-small"
                                                onclick="return confirm('取引ID: <?php echo h($transaction['id']); ?> の取引を削除しますか？\nこの操作は元に戻せません。');">
                                            削除
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-center" style="padding: 20px;">データがありません。</p>
    <?php endif; ?>
</div>

<style>
/* report-specific styles */
.period-selector input, .period-selector button {
    padding: 8px;
    border-radius: 6px;
    border: 1px solid #e0e0e0;
}
.commission-highlight {
    font-weight: bold;
    color: #1a6d2f;
}
</style>
