<?php
// modules/settlement/SettlementView.php - ★修正版
// このファイルはHTMLの表示だけに集中します。

// 計算済みの変数を使って表示用の変数を準備
$actual_cash_on_hand = $settlement_data['actual_cash_on_hand'] ?? null;
$discrepancy = $settlement_data['discrepancy'] ?? null;
$settlement_exists = $settlement_data !== false && $settlement_data !== null;
?>

<div class="card info-box">
    <h2>💰 本日のサマリー (<?php echo date('Y年m月d日'); ?>)</h2>
    
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">釣銭準備金:</span>
            <span class="info-value">¥<?php echo number_format($initial_cash_float); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">本日の売上 (現金):</span>
            <span class="info-value">¥<?php echo number_format($total_sales_cash); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">取引件数:</span>
            <span class="info-value"><?php echo number_format($transaction_count); ?>件</span>
        </div>
        <div class="info-item highlight">
            <span class="info-label">予想手元金額:</span>
            <span class="info-value">¥<?php echo number_format($expected_cash_on_hand); ?></span>
        </div>
        <?php if ($actual_cash_on_hand !== null): ?>
        <div class="info-item">
            <span class="info-label">実際手元金額:</span>
            <span class="info-value">¥<?php echo number_format($actual_cash_on_hand); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">差異:</span>
            <span class="info-value <?php echo $discrepancy > 0 ? 'surplus' : ($discrepancy < 0 ? 'shortage' : 'balanced'); ?>">
                ¥<?php echo number_format($discrepancy); ?>
                <?php if ($discrepancy > 0): ?>
                    (余剰)
                <?php elseif ($discrepancy < 0): ?>
                    (不足)
                <?php else: ?>
                    (一致)
                <?php endif; ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="settlement-grid">
    <!-- 釣銭準備金の設定 -->
    <div class="card">
        <h3>💵 釣銭準備金の設定</h3>
        <p style="margin-bottom: 15px; color: #666;">営業開始時に準備する釣銭の金額を設定します。</p>
        
        <form method="POST" action="create.php">
            <input type="hidden" name="action" value="set_cash_float">
            <div class="form-group">
                <label for="initial_cash_float">釣銭準備金額:</label>
                <input type="number" id="initial_cash_float" name="initial_cash_float" 
                       step="1" min="0" value="<?php echo $initial_cash_float; ?>" 
                       class="cash-input" required>
            </div>
            <button type="submit" class="btn success" style="width: 100%;">
                <?php echo $settlement_exists ? '💾 準備金を更新' : '💾 準備金を設定'; ?>
            </button>
        </form>
        
        <?php if ($settlement_exists): ?>
        <div style="margin-top: 10px; padding: 10px; background: #e6f7e9; border-radius: 4px; font-size: 0.9em;">
            <strong>✅ 設定済み</strong><br>
            本日設定完了
        </div>
        <?php endif; ?>
    </div>

    <!-- 精算処理 -->
    <div class="card">
        <h3>🧮 精算処理</h3>
        <?php if (!$settlement_exists || $initial_cash_float == 0): ?>
            <div class="alert error">
                <strong>⚠️ 注意:</strong> 精算を行う前に、まず釣銭準備金を設定してください。
            </div>
        <?php endif; ?>
        
        <form method="POST" action="create.php">
            <input type="hidden" name="action" value="settle_up">
            
            <h4 style="margin-bottom: 15px;">💰 実際手元金額の内訳</h4>
            <div class="denomination-grid">
                <div class="denomination-item">
                    <label>10,000円札:</label>
                    <input type="number" name="bill_10000" min="0" value="0" class="denomination-input" oninput="calculateActualCash()">
                    <span class="amount-display">¥0</span>
                </div>
                <div class="denomination-item">
                    <label>5,000円札:</label>
                    <input type="number" name="bill_5000" min="0" value="0" class="denomination-input" oninput="calculateActualCash()">
                    <span class="amount-display">¥0</span>
                </div>
                <div class="denomination-item">
                    <label>1,000円札:</label>
                    <input type="number" name="bill_1000" min="0" value="0" class="denomination-input" oninput="calculateActualCash()">
                    <span class="amount-display">¥0</span>
                </div>
                <div class="denomination-item">
                    <label>500円玉:</label>
                    <input type="number" name="coin_500" min="0" value="0" class="denomination-input" oninput="calculateActualCash()">
                    <span class="amount-display">¥0</span>
                </div>
                <div class="denomination-item">
                    <label>100円玉:</label>
                    <input type="number" name="coin_100" min="0" value="0" class="denomination-input" oninput="calculateActualCash()">
                    <span class="amount-display">¥0</span>
                </div>
                <div class="denomination-item">
                    <label>50円玉:</label>
                    <input type="number" name="coin_50" min="0" value="0" class="denomination-input" oninput="calculateActualCash()">
                    <span class="amount-display">¥0</span>
                </div>
                <div class="denomination-item">
                    <label>10円玉:</label>
                    <input type="number" name="coin_10" min="0" value="0" class="denomination-input" oninput="calculateActualCash()">
                    <span class="amount-display">¥0</span>
                </div>
                <div class="denomination-item">
                    <label>5円玉:</label>
                    <input type="number" name="coin_5" min="0" value="0" class="denomination-input" oninput="calculateActualCash()">
                    <span class="amount-display">¥0</span>
                </div>
                <div class="denomination-item">
                    <label>1円玉:</label>
                    <input type="number" name="coin_1" min="0" value="0" class="denomination-input" oninput="calculateActualCash()">
                    <span class="amount-display">¥0</span>
                </div>
            </div>

            <div class="total-display">
                <span class="total-label">実際手元金額合計:</span>
                <span class="total-value">¥<span id="actual_cash_total_display">0</span></span>
            </div>

            <div class="difference-display" id="difference_display" style="display: none;">
                <span class="difference-label">予想との差異:</span>
                <span class="difference-value" id="difference_value">¥0</span>
            </div>

            <input type="hidden" id="actual_cash_on_hand" name="actual_cash_on_hand" value="0">
            <button type="submit" class="btn success settlement-btn" 
                    <?php echo (!$settlement_exists || $initial_cash_float == 0) ? 'disabled' : ''; ?>>
                ✅ 精算を完了する
            </button>
        </form>
    </div>
</div>

<!-- 精算履歴 -->
<div class="card">
    <h3>📋 精算履歴 (過去7日間)</h3>
    <?php if (!empty($settlement_history)): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>日付</th>
                        <th>釣銭準備金</th>
                        <th>売上</th>
                        <th>予想手元金額</th>
                        <th>実際手元金額</th>
                        <th>差異</th>
                        <th>状態</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($settlement_history as $history): ?>
                        <tr>
                            <td><?php echo date('m/d (D)', strtotime($history['settlement_date'])); ?></td>
                            <td>¥<?php echo number_format($history['initial_cash_float']); ?></td>
                            <td>¥<?php echo number_format($history['total_sales_cash']); ?></td>
                            <td>¥<?php echo number_format($history['expected_cash_on_hand']); ?></td>
                            <td>
                                <?php if ($history['actual_cash_on_hand'] !== null): ?>
                                    ¥<?php echo number_format($history['actual_cash_on_hand']); ?>
                                <?php else: ?>
                                    <span style="color: #999;">未精算</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($history['discrepancy'] !== null): ?>
                                    <span class="<?php echo $history['discrepancy'] > 0 ? 'surplus' : ($history['discrepancy'] < 0 ? 'shortage' : 'balanced'); ?>">
                                        ¥<?php echo number_format($history['discrepancy']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($history['actual_cash_on_hand'] !== null): ?>
                                    <span class="status-badge status-normal">完了</span>
                                <?php else: ?>
                                    <span class="status-badge status-warning">未完了</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p style="text-align: center; color: #666; padding: 20px;">📝 精算履歴がありません。</p>
    <?php endif; ?>
</div>

<style>
.info-box { background-color: #ffffff; border: 1px solid #e2e8f0; }
.info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
.info-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(0,0,0,0.1); }
.info-item:last-child { border-bottom: none; }
.info-item.highlight { background: rgba(0, 164, 153, 0.1); padding: 10px; border-radius: 4px; border-bottom: none; font-weight: bold; }
.info-label { font-weight: 500; color: #333; }
.info-value { font-weight: 700; color: #1a6d2f; }
.surplus { color: #d9534f; font-weight: bold; }
.shortage { color: #f0ad4e; font-weight: bold; }
.balanced { color: #5cb85c; font-weight: bold; }
.settlement-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
.denomination-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 20px; }
.denomination-item { display: grid; grid-template-columns: 1fr 80px 80px; align-items: center; gap: 10px; padding: 8px; background: #f9f9f9; border-radius: 4px; }
.denomination-item label { font-size: 14px; font-weight: 500; margin: 0; }
.denomination-input { padding: 6px; border: 1px solid #ddd; border-radius: 4px; text-align: center; font-size: 14px; }
.amount-display { font-size: 12px; color: #666; text-align: right; font-weight: 500; }
.total-display { background: #e6f7e9; padding: 15px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border: 1px solid #b7e0c4; }
.total-label { font-size: 1.1em; font-weight: 600; color: #333; }
.total-value { font-size: 1.3em; font-weight: 700; color: #1a6d2f; }
.difference-display { background: #fff8e6; padding: 10px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border: 1px solid #f2e2be; }
.difference-label { font-weight: 600; color: #333; }
.difference-value { font-weight: 700; }
.settlement-btn { width: 100%; font-size: 1.1em; padding: 12px; }
.cash-input { font-size: 1.2em; text-align: center; font-weight: bold; }
.status-badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; display: inline-block; min-width: 60px; text-align: center; }
.status-normal { background: #e6f7e9; color: #1a6d2f; }
.status-warning { background: #fff8e6; color: #8c6a0c; }
@media (max-width: 768px) {
    .settlement-grid, .denomination-grid, .info-grid { grid-template-columns: 1fr; }
}