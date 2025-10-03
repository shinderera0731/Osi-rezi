<?php
// modules/staff/StaffView.php - ★修正版
// このファイルはHTMLの表示だけに集中します。

// -----------------------------------------------------------------------------
// ▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼
//
//  上位の select.php ファイルで、以下のような処理が行われることを想定しています。
//
//  require_once 'modules/staff/StaffService.php';
//  $staffService = new StaffService($pdo);
//  $all_users = $staffService->getAllStaffDetails();
//
// ▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲
// -----------------------------------------------------------------------------

?>

<div class="card">
    <h3>🧑‍💻 スタッフ管理</h3>
    <p style="margin-bottom: 15px;">新規スタッフの登録や、既存スタッフの情報を確認・設定できます。</p>
    
    <div style="text-align: center; margin-bottom: 20px;">
        <a href="register.php?from_settings=true" class="btn success">➕ 新規スタッフ登録</a>
    </div>

    <h4>登録済みスタッフ一覧</h4>
    <?php if (count($all_users) > 0): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ユーザーID</th>
                        <th>ユーザー名</th>
                        <th>役割</th>
                        <th>従業員ID</th>
                        <th>入社日</th>
                        <th>電話番号</th>
                        <th>歩合率</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_users as $user): ?>
                        <tr>
                            <td><?php echo h($user['id']); ?></td>
                            <td><strong><?php echo h($user['username']); ?></strong></td>
                            <td>
                                <span class="status-badge <?php echo $user['role'] === 'admin' ? 'status-warning' : 'status-normal'; ?>">
                                    <?php echo $user['role'] === 'admin' ? '管理者' : 'スタッフ'; ?>
                                </span>
                            </td>
                            <td><?php echo h($user['employee_id'] ?? '-'); ?></td>
                            <td><?php echo $user['hire_date'] ? formatDate($user['hire_date']) : '-'; ?></td>
                            <td><?php echo h($user['phone_number'] ?? '-'); ?></td>
                            <td><?php echo number_format($user['commission_rate'] ?? 0, 1); ?>%</td>
                            <td>
                                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                                    <button type="button" class="btn btn-small" 
                                            style="background: #007bff; margin-right: 5px; padding: 6px 10px; font-size: 12px;" 
                                            onclick="openEditStaffModal(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                        編集
                                    </button>
                                    <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                    <form method="POST" action="create.php" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_staff">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn danger btn-small" 
                                                style="padding: 6px 8px; font-size: 12px;"
                                                onclick="return confirm('ユーザー「<?php echo h($user['username']); ?>」を削除しますか？\n※この操作は元に戻せません。')">
                                            🗑️
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #666; font-size: 12px;">権限なし</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #666;">
            <p>📝 登録されているスタッフがいません。</p>
            <p>上記の「新規スタッフ登録」ボタンから追加してください。</p>
        </div>
    <?php endif; ?>
</div>

<!-- スタッフ編集モーダル -->
<div id="editStaffModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close-button" onclick="closeEditStaffModal()">&times;</span>
        <h3 style="color: #00a499; margin-bottom: 15px;">📝 スタッフ情報編集</h3>
        <div class="modal-body">
            <form id="editStaffForm" method="POST" action="create.php">
                <input type="hidden" name="action" value="update_staff_details">
                <input type="hidden" name="user_id" id="modal_staff_user_id">
                
                <div class="form-group">
                    <label for="modal_staff_username">ユーザー名:</label>
                    <input type="text" name="username" id="modal_staff_username" required>
                </div>
                
                <div class="form-group">
                    <label for="modal_staff_role">役割:</label>
                    <select name="role" id="modal_staff_role" required>
                        <option value="staff">スタッフ</option>
                        <option value="admin">管理者</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="modal_staff_employee_id">従業員ID:</label>
                    <input type="text" name="employee_id" id="modal_staff_employee_id">
                </div>
                
                <div class="form-group">
                    <label for="modal_staff_hire_date">入社日:</label>
                    <input type="date" name="hire_date" id="modal_staff_hire_date">
                </div>
                
                <div class="form-group">
                    <label for="modal_staff_phone_number">電話番号:</label>
                    <input type="text" name="phone_number" id="modal_staff_phone_number">
                </div>
                
                <div class="form-group">
                    <label for="modal_staff_address">住所:</label>
                    <textarea name="address" id="modal_staff_address" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="modal_staff_emergency_contact">緊急連絡先:</label>
                    <input type="text" name="emergency_contact" id="modal_staff_emergency_contact">
                </div>
                
                <div class="form-group">
                    <label for="modal_staff_commission_rate">基本歩合率 (%):</label>
                    <input type="number" name="commission_rate" id="modal_staff_commission_rate" 
                           step="0.1" min="0" max="100" required>
                    <small style="color: #666; font-size: 0.9em;">
                        ※ 個別商品の歩合設定がある場合は、そちらが優先されます
                    </small>
                </div>
                
                <div class="modal-footer" style="margin-top: 20px; text-align: right;">
                    <button type="submit" class="btn success">💾 更新</button>
                    <button type="button" class="btn" style="background: #ccc; color: #333;" onclick="closeEditStaffModal()">キャンセル</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* モーダルスタイル */
.modal {
    position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%;
    overflow: auto; background-color: rgba(0,0,0,0.4);
}
.modal-content {
    background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888;
    width: 90%; max-width: 600px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    position: relative;
}
.close-button {
    color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;
}
.close-button:hover, .close-button:focus { color: black; text-decoration: none; }
.modal-body .form-group { margin-bottom: 15px; }
.modal-body .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; font-size: 14px; }
.modal-body .form-group input,
.modal-body .form-group select,
.modal-body .form-group textarea {
    width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;
    font-size: 14px; transition: border-color 0.2s; background-color: #f9f9f9;
}
.modal-body .form-group input:focus,
.modal-body .form-group select:focus,
.modal-body .form-group textarea:focus {
    outline: none; border-color: #00a499; background-color: #fff;
}
.btn-small { padding: 6px 12px; font-size: 12px; }
</style>

<script>
// スタッフ編集モーダル関連の関数
function openEditStaffModal(staffData) {
    document.getElementById('modal_staff_user_id').value = staffData.id;
    document.getElementById('modal_staff_username').value = staffData.username;
    document.getElementById('modal_staff_role').value = staffData.role;
    document.getElementById('modal_staff_employee_id').value = staffData.employee_id || '';
    document.getElementById('modal_staff_hire_date').value = staffData.hire_date || '';
    document.getElementById('modal_staff_phone_number').value = staffData.phone_number || '';
    document.getElementById('modal_staff_address').value = staffData.address || '';
    document.getElementById('modal_staff_emergency_contact').value = staffData.emergency_contact || '';
    document.getElementById('modal_staff_commission_rate').value = staffData.commission_rate || '0.0';

    document.getElementById('editStaffModal').style.display = 'block';
}

function closeEditStaffModal() {
    document.getElementById('editStaffModal').style.display = 'none';
}

// モーダル外クリックで閉じる
window.onclick = function(event) {
    const modal = document.getElementById('editStaffModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>
