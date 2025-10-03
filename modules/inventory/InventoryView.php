<?php
// modules/inventory/InventoryView.php - ★修正版
// このファイルは、HTMLの表示だけに集中します。

?>



<!-- フィルター -->
<div style="background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
    <h4>🔍 絞り込み検索</h4>
    <form method="GET" style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
        <input type="hidden" name="tab" value="inventory">
        <div>
            <label style="font-size: 14px; font-weight: 600;">カテゴリ:</label>
            <select name="category" onchange="this.form.submit()" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="">全て</option>
                <?php foreach ($categories_data as $category): ?>
                    <option value="<?php echo h($category['name']); ?>"
                        <?php echo ($filters['category'] ?? '') === $category['name'] ? 'selected' : ''; ?>>
                        <?php echo h($category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size: 14px; font-weight: 600;">状態:</label>
            <select name="status" onchange="this.form.submit()" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="">全て</option>
                <option value="normal" <?php echo ($filters['status'] ?? '') === 'normal' ? 'selected' : ''; ?>>正常在庫</option>
                <option value="low_stock" <?php echo ($filters['status'] ?? '') === 'low_stock' ? 'selected' : ''; ?>>在庫不足</option>
                <option value="expiring" <?php echo ($filters['status'] ?? '') === 'expiring' ? 'selected' : ''; ?>>期限間近</option>
            </select>
        </div>
        <div>
            <label style="font-size: 14px; font-weight: 600;">キーワード検索:</label>
            <input type="text" name="search_keyword" value="<?php echo h($filters['search_keyword'] ?? ''); ?>" 
                   placeholder="商品名で検索" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 200px;">
        </div>
        <div>
            <button type="submit" class="btn" style="padding: 8px 16px;">🔍 検索</button>
            <a href="select.php?tab=inventory" class="btn" style="background: #ccc; color: #333; padding: 8px 16px;">🔄 リセット</a>
        </div>
    </form>
</div>

<!-- 在庫一覧 -->
<div class="card">
    <h3>📦 在庫一覧</h3>
    <div style="margin-bottom: 15px;">
        <a href="input.php?tab=inventory_ops" class="btn">➕ 新商品追加</a>
        <span style="margin-left: 15px; color: #666;">
            <?php echo count($inventory_items); ?>件の商品が表示されています
        </span>
    </div>
    
    <?php if (!empty($inventory_items)): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>商品名</th>
                        <th>カテゴリ</th>
                        <th>在庫数</th>
                        <th>単位</th>
                        <th>仕入価格</th>
                        <th>販売価格</th>
                        <th>状態</th>
                        <th>賞味期限</th>
                        <th>在庫価値</th>
                        <th>歩合設定</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventory_items as $item): 
                        $stockStatus = getStockStatus($item);
                        $expiryStatus = getExpiryStatus($item['expiry_date']);
                        $commission = formatCommission($item['commission_type'], $item['commission_rate'], $item['fixed_commission_amount']);
                        $inventory_value = $item['quantity'] * $item['cost_price'];
                    ?>
                        <tr>
                            <td><strong><?php echo h($item['name']); ?></strong></td>
                            <td><?php echo h($item['category_name'] ?? '未分類'); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><?php echo h($item['unit']); ?></td>
                            <td><?php echo formatPrice($item['cost_price']); ?></td>
                            <td><?php echo formatPrice($item['selling_price']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $stockStatus['class']; ?>">
                                    <?php echo $stockStatus['text']; ?>
                                </span>
                                <?php if ($expiryStatus && $expiryStatus['class'] !== 'status-normal'): ?>
                                    <br><span class="status-badge <?php echo $expiryStatus['class']; ?>">
                                        <?php echo $expiryStatus['text']; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['expiry_date']): ?>
                                    <?php echo formatDate($item['expiry_date']); ?>
                                    <?php if ($expiryStatus): ?>
                                        <?php if ($expiryStatus['days'] < 0): ?>
                                            <small style="color: #dc3545;">(期限切れ)</small>
                                        <?php elseif ($expiryStatus['days'] <= 7): ?>
                                            <small style="color: #f0ad4e;">(残り<?php echo $expiryStatus['days']; ?>日)</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatPrice($inventory_value); ?></td>
                            <td><?php echo $commission; ?></td>
                            <td style="white-space: nowrap;">
                                <a href="input.php?tab=inventory_ops&edit_id=<?php echo $item['id']; ?>" 
                                   class="btn btn-small" style="background: #007bff; margin-right: 5px; padding: 4px 8px; font-size: 12px;">編集</a>
                                <form method="POST" action="create.php" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_item">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="btn danger btn-small" 
                                            style="padding: 4px 8px; font-size: 12px;"
                                            onclick="return confirm('商品「<?php echo h($item['name']); ?>」を削除しますか？\n※この操作は元に戻せません。')">
                                        🗑️
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p style="text-align: center; padding: 40px; color: #666;">
            <?php if (array_filter($filters)): ?>
                🔍 検索条件に一致する商品がありません
            <?php else: ?>
                📦 登録されている商品がありません
            <?php endif; ?>
        </p>
    <?php endif; ?>
</div>

<!-- 在庫アラート -->
<?php if (!empty($low_stock_items) || !empty($expiring_items)): ?>
    <div class="card">
        <h3>⚠️ 在庫アラート</h3>
        
        <?php if (!empty($low_stock_items)): ?>
            <h4 style="color: #d9534f; margin-bottom: 15px;">在庫不足商品 (<?php echo count($low_stock_items); ?>件)</h4>
            <div class="table-container" style="margin-bottom: 20px;">
                <table>
                    <thead>
                        <tr>
                            <th>商品名</th>
                            <th>現在庫数</th>
                            <th>発注点</th>
                            <th>仕入先</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($low_stock_items as $item): ?>
                            <tr>
                                <td><strong><?php echo h($item['name']); ?></strong></td>
                                <td>
                                    <span class="status-badge status-low">
                                        <?php echo $item['quantity']; ?><?php echo h($item['unit']); ?>
                                    </span>
                                </td>
                                <td><?php echo $item['reorder_level']; ?><?php echo h($item['unit']); ?></td>
                                <td><?php echo h($item['supplier'] ?? '未設定'); ?></td>
                                <td>
                                    <a href="input.php?tab=inventory_ops#movement" class="btn btn-small">📦 入庫</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (!empty($expiring_items)): ?>
            <h4 style="color: #f0ad4e; margin-bottom: 15px;">賞味期限間近商品 (<?php echo count($expiring_items); ?>件)</h4>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>商品名</th>
                            <th>在庫数</th>
                            <th>賞味期限</th>
                            <th>残り日数</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expiring_items as $item): 
                            $expiryStatus = getExpiryStatus($item['expiry_date']);
                        ?>
                            <tr>
                                <td><strong><?php echo h($item['name']); ?></strong></td>
                                <td><?php echo $item['quantity']; ?><?php echo h($item['unit']); ?></td>
                                <td><?php echo formatDate($item['expiry_date']); ?></td>
                                <td>
                                    <?php if ($expiryStatus['days'] < 0): ?>
                                        <span class="status-badge status-low">期限切れ</span>
                                    <?php elseif ($expiryStatus['days'] == 0): ?>
                                        <span class="status-badge status-low">本日</span>
                                    <?php else: ?>
                                        <span class="status-badge status-warning">
                                            <?php echo $expiryStatus['days']; ?>日
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="input.php?tab=inventory_ops#movement" class="btn btn-small danger">🗑️ 廃棄</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- 最近の入出庫履歴 -->
<div class="card">
    <h3>📋 最近の入出庫履歴</h3>
    <?php if (!empty($recent_movements)): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>日時</th>
                        <th>商品名</th>
                        <th>処理</th>
                        <th>数量</th>
                        <th>理由</th>
                        <th>担当者</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_movements as $movement): ?>
                        <tr>
                            <td><?php echo formatDateTime($movement['created_at'], 'm/d H:i'); ?></td>
                            <td><?php echo h($movement['item_name']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $movement['movement_type'] === '入庫' ? 'status-normal' : 'status-warning'; ?>">
                                    <?php echo h($movement['movement_type']); ?>
                                </span>
                            </td>
                            <td><?php echo $movement['quantity']; ?><?php echo h($movement['unit'] ?? ''); ?></td>
                            <td><?php echo h($movement['reason'] ?? '-'); ?></td>
                            <td><?php echo h($movement['created_by'] ?? 'System'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p style="text-align: center; color: #666; padding: 40px;">📝 履歴データがありません</p>
    <?php endif; ?>
</div>

<style>
.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
    min-width: 60px;
    text-align: center;
}
.status-low {
    background: #fdecec;
    color: #b33939;
}
.status-normal {
    background: #e6f7e9;
    color: #1a6d2f;
}
.status-warning {
    background: #fff8e6;
    color: #8c6a0c;
}
.table-container {
    overflow-x: auto;
}
.btn-small {
    padding: 4px 8px;
    font-size: 12px;
    border-radius: 4px;
}
.stat-card.status-warning {
    border-left: 4px solid #f0ad4e;
}
</style>

<script>
// 削除確認
document.querySelectorAll('form button.danger').forEach(button => {
    button.addEventListener('click', function(e) {
        const form = this.closest('form');
        const itemId = form.querySelector('input[name="item_id"]').value;
        if (!confirm('本当に削除しますか？\n※この操作は元に戻せません。')) {
            e.preventDefault();
        }
    });
});

// 自動検索機能
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="search_keyword"]');
    if (searchInput) {
        let timeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                this.form.submit();
            }, 1000); // 1秒後に自動検索
        });
    }
    
    // 統計カードのアニメーション
    setTimeout(() => {
        const statNumbers = document.querySelectorAll('.stat-number');
        statNumbers.forEach(element => {
            const text = element.textContent;
            const finalValue = parseInt(text.replace(/[^\d]/g, ''));
            
            if (finalValue > 0 && !text.includes('¥')) {
                let currentValue = 0;
                const increment = Math.ceil(finalValue / 20);
                const timer = setInterval(() => {
                    currentValue += increment;
                    if (currentValue >= finalValue) {
                        currentValue = finalValue;
                        clearInterval(timer);
                    }
                    element.textContent = currentValue;
                }, 50);
            }
        });
    }, 300);
});
</script>