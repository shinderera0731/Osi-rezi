<?php
// modules/settings/SettingsView.php - 設定ビューモジュール

// 現在の設定値を取得
$current_tax_rate = (float)getSetting($pdo, 'tax_rate', 10);
$current_low_stock_threshold = (int)getSetting($pdo, 'low_stock_threshold', 5);
?>

<div class="card">
    <h3>⚙️ アプリケーション設定</h3>
    <form method="POST" action="create.php">
        <input type="hidden" name="action" value="save_app_settings">
        
        <div class="form-grid">
            <div>
                <div class="form-group">
                    <label for="tax_rate">税率 (%):</label>
                    <input type="number" id="tax_rate" name="tax_rate" step="0.01" min="0" max="100" 
                           value="<?php echo htmlspecialchars($current_tax_rate); ?>" 
                           class="form-group input" required>
                    <small style="color: #666;">レジでの会計時に適用される税率です</small>
                </div>
                
                <div class="form-group">
                    <label for="low_stock_threshold_setting">低在庫アラート閾値 (個):</label>
                    <input type="number" id="low_stock_threshold_setting" name="low_stock_threshold" 
                           step="1" min="0" value="<?php echo htmlspecialchars($current_low_stock_threshold); ?>" 
                           class="form-group input" required>
                    <small style="color: #666;">この数値以下になると在庫不足として警告表示されます</small>
                </div>
            </div>
            
            <div>
                <div class="form-group">
                    <label>現在の設定値:</label>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border: 1px solid #e0e0e0;">
                        <p><strong>税率:</strong> <?php echo $current_tax_rate; ?>%</p>
                        <p><strong>低在庫閾値:</strong> <?php echo $current_low_stock_threshold; ?>個</p>
                        <p><strong>最終更新:</strong> <?php echo date('Y年m月d日 H:i'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <button type="submit" class="btn success">💾 設定を保存</button>
        </div>
    </form>
</div>

<div class="card">
    <h3>👥 ユーザー管理</h3>
    <p>新しいスタッフアカウントの作成や既存スタッフの管理を行います。</p>
    <div style="text-align: center; margin-top: 15px;">
        <a href="register.php?from_settings=true" class="btn success">➕ 新規スタッフ登録</a>
        <a href="?tab=staff_management" class="btn" style="background: #007bff;">👥 スタッフ管理画面へ</a>
    </div>
</div>

<div class="card" style="opacity: 0.7;">
    <h3>🏪 店舗情報設定</h3>
    <p>店舗名や住所、連絡先などの基本情報を設定します。</p>
    <button class="btn" style="background: #ccc; color: #333;" disabled>編集 (未実装)</button>
</div>

<div class="card" style="opacity: 0.7;">
    <h3>🧾 レシート設定</h3>
    <p>レシートに表示するメッセージやロゴ、レイアウトなどを設定します。</p>
    <button class="btn" style="background: #ccc; color: #333;" disabled>編集 (未実装)</button>
</div>

<div class="card" style="opacity: 0.7;">
    <h3>💾 データ管理</h3>
    <p>データベースのバックアップやデータのインポート/エクスポートなどを行います。</p>
    <button class="btn" style="background: #ccc; color: #333;" disabled>実行 (未実装)</button>
</div>

<div class="card" style="border: 2px solid #dc3545; background-color: #fff5f5;">
    <h3 style="color: #dc3545;">🗑️ データベースリセット</h3>
    <div class="alert" style="background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; margin-bottom: 15px; padding: 15px; border-radius: 6px;">
        <strong>⚠️ 重要な警告:</strong> この操作を実行すると、以下のデータが完全に削除されます：
        <ul style="margin: 10px 0; padding-left: 20px;">
            <li>すべての商品データ</li>
            <li>すべての在庫データ</li>
            <li>すべての取引履歴</li>
            <li>すべての入出庫履歴</li>
            <li>すべての精算データ</li>
            <li>すべてのスタッフ情報（管理者とスタッフアカウントは再作成されます）</li>
        </ul>
        <strong style="color: #dc3545;">この操作は元に戻せません。</strong>
    </div>
    
    <form method="POST" action="create.php" onsubmit="return confirmReset()">
        <input type="hidden" name="action" value="reset_database">
        <div class="form-group">
            <label for="confirmation_key" style="color: #dc3545; font-weight: bold;">
                確認キー入力 (「RESET_DATABASE」と入力してください):
            </label>
            <input 
                type="text" 
                id="confirmation_key" 
                name="confirmation_key" 
                placeholder="RESET_DATABASE" 
                required 
                style="border: 2px solid #dc3545; font-family: monospace;"
                autocomplete="off"
            >
        </div>
        <div style="text-align: center; margin-top: 20px;">
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                <button type="submit" class="btn danger" style="font-size: 16px; padding: 12px 25px;">
                    🗑️ データベースをリセットする
                </button>
            <?php else: ?>
                <p style="color: #dc3545; font-weight: bold;">※ この機能は管理者のみ利用できます</p>
                <button type="button" class="btn" style="background: #ccc; color: #333;" disabled>
                    🗑️ データベースをリセットする (権限なし)
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- システム情報 -->
<div class="card" style="background: #f8f9fa;">
    <h3>📱 システム情報</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
        <div>
            <h4 style="color: #00a499; margin-bottom: 10px;">環境情報</h4>
            <p><strong>PHP バージョン:</strong> <?php echo phpversion(); ?></p>
            <p><strong>データベース:</strong> MySQL</p>
            <p><strong>サーバー:</strong> <?php echo $_SERVER['SERVER_NAME']; ?></p>
            <p><strong>現在時刻:</strong> <?php echo date('Y年m月d日 H:i:s'); ?></p>
        </div>
        <div>
            <h4 style="color: #00a499; margin-bottom: 10px;">ユーザー情報</h4>
            <p><strong>ログインユーザー:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
            <p><strong>ユーザー役割:</strong> <?php echo $_SESSION['user_role'] === 'admin' ? '管理者' : 'スタッフ'; ?></p>
            <p><strong>ログイン時刻:</strong> <?php echo isset($_SESSION['login_time']) ? $_SESSION['login_time'] : '不明'; ?></p>
        </div>
    </div>
    
    <div style="text-align: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #e0e0e0;">
        <p style="color: #666; font-size: 0.9em;">
            <strong>システム状態:</strong>
            <span style="color: #5cb85c; font-weight: 600;">✅ 正常稼働中</span>
        </p>
    </div>
</div>

<script>
// データベースリセット確認関数
function confirmReset() {
    const confirmationKey = document.getElementById('confirmation_key').value;
    
    if (confirmationKey !== 'RESET_DATABASE') {
        alert('確認キーが正しくありません。「RESET_DATABASE」と正確に入力してください。');
        document.getElementById('confirmation_key').focus();
        return false;
    }
    
    const confirmed = confirm(
        '本当にデータベースをリセットしますか？\n\n' +
        'この操作により以下が実行されます：\n' +
        '• 全てのデータが削除されます\n' +
        '• システムが初期状態に戻ります\n' +
        '• デフォルトアカウント（admin/password, staff/password）が再作成されます\n\n' +
        'この操作は元に戻せません。'
    );
    
    if (!confirmed) {
        return false;
    }
    
    const doubleConfirmed = confirm(
        '最終確認：\n\n' +
        'データベースを完全にリセットして\n' +
        '全てのデータを削除しますか？\n\n' +
        'この操作は取り消せません。'
    );
    
    if (doubleConfirmed) {
        // 処理中の表示
        const submitButton = document.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '🔄 リセット中...';
        }
    }
    
    return doubleConfirmed;
}
</script>