<?php
// modules/api/ApiKeysView.php
// APIキー管理画面のビュー

// 親の select.php から以下の変数が渡されることを想定
// $api_keys = [];
// $users = []; // ※UIからは使われなくなるが、生成ロジックはユーザーIDを必要とするため、既存の構造を維持する
?>

<div class="card">
    <h3>🔑 APIキー管理</h3>
    <p style="margin-bottom: 15px;">外部アプリケーションとの連携に使用するAPIキーを管理します。APIキーは<strong>管理者のみ</strong>が生成・管理できます。</p>

    <!-- APIキー生成フォーム (対象ユーザー選択を削除) -->
    <div style="text-align: center; margin-bottom: 20px;">
        <form method="POST" action="create.php" style="display: inline-block;">
            <input type="hidden" name="action" value="generate_api_key_for_admin">
            <!-- ユーザー選択ドロップダウンを削除 -->
            <button type="submit" class="btn success">➕ 新しいAPIキーを生成</button>
        </form>
    </div>
    
    <h4>現在発行されているAPIキー</h4>
    <?php if (!empty($api_keys)): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>関連ユーザー</th>
                        <th>APIキー</th>
                        <th>ステータス</th>
                        <th>作成日</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($api_keys as $key): ?>
                        <tr>
                            <td><?php echo h($key['id']); ?></td>
                            <td><?php echo h($key['username']); ?></td>
                            <td style="font-family: monospace; font-size: 0.9em; max-width: 250px; overflow-x: auto;"><?php echo h($key['api_key']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $key['status'] === 'active' ? 'status-normal' : 'status-low'; ?>">
                                    <?php echo $key['status'] === 'active' ? '有効' : '無効'; ?>
                                </span>
                            </td>
                            <td><?php echo formatDateTime($key['created_at']); ?></td>
                            <td>
                                <form method="POST" action="create.php" style="display: inline-block;">
                                    <input type="hidden" name="action" value="<?php echo $key['status'] === 'active' ? 'deactivate_api_key' : 'activate_api_key'; ?>">
                                    <input type="hidden" name="api_key_id" value="<?php echo h($key['id']); ?>">
                                    <button type="submit" class="btn btn-small" style="background: <?php echo $key['status'] === 'active' ? '#f0ad4e' : '#5cb85c'; ?>; color: white;">
                                        <?php echo $key['status'] === 'active' ? '無効化' : '有効化'; ?>
                                    </button>
                                </form>
                                <form method="POST" action="create.php" style="display: inline-block;">
                                    <input type="hidden" name="action" value="delete_api_key">
                                    <input type="hidden" name="api_key_id" value="<?php echo h($key['id']); ?>">
                                    <button type="submit" class="btn danger btn-small"
                                            onclick="return confirm('本当にこのAPIキーを削除しますか？\nこの操作は元に戻せません。')">
                                        🗑️ 削除
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p style="text-align: center; padding: 40px; color: #666;">📝 発行されたAPIキーはありません。</p>
    <?php endif; ?>
</div>

<style>
/* report-specific styles */
.status-badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; display: inline-block; min-width: 60px; text-align: center; }
.status-low { background: #fdecec; color: #b33939; }
.status-normal { background: #e6f7e9; color: #1a6d2f; }
.status-warning { background: #fff8e6; color: #8c6a0c; }
.btn-small { padding: 4px 8px; font-size: 12px; }
.danger { background-color: #dc3545; }
.danger:hover { background-color: #c82333; }
</style>
