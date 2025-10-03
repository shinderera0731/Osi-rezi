<?php
// input.php - 最終リファクタリング版
// 在庫の追加・編集・入出庫を専門に行う画面

ob_start();

// --- 共通設定の読み込み（新しい専門書を読み込む） ---
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/functions.php';
require_once 'includes/styles.php';
require_once 'includes/navigation.php';
require_once 'includes/messages.php'; // メッセージ表示機能を追加

// --- 専門家（サービスクラス）の読み込み ---
require_once 'modules/inventory/InventoryService.php';

// ログイン必須
requireLogin();

// --- 在庫のシェフを呼び出し、データ（料理）を準備 ---
$inventoryService = new InventoryService($pdo);
$categories = $inventoryService->getCategories();
$inventory_items = $inventoryService->getInventoryList(); // 入出庫フォームの選択肢用

// 編集モードの場合、編集する商品データを取得
$item_to_edit = null;
$edit_item_id = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
if ($edit_item_id > 0) {
    $item_to_edit = $inventoryService->getProductById($edit_item_id);
    if (!$item_to_edit) {
        setErrorMessage('編集対象の商品が見つかりませんでした。');
        header('Location: select.php?tab=inventory');
        exit;
    }
}

// カテゴリ追加フォームの処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_category') {
    $category_name = trim($_POST['category_name'] ?? '');
    if (!empty($category_name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([$category_name]);
            setSuccessMessage("カテゴリ「{$category_name}」が追加されました。");
        } catch (PDOException $e) {
            setErrorMessage("カテゴリの追加に失敗しました。このカテゴリは既に存在するか、無効な値です。");
        }
        header('Location: input.php');
        exit;
    }
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品管理 - Oshi-rezi</title>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1><img src="images/osi-rezi2.png" alt="推しレジ" style="width: 40px; height: 40px; vertical-align: middle; margin-right: 10px; border-radius: 12px;">Osi-rezi</h1>
                    <p>商品追加・入出庫</p>
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

            <!-- 在庫一覧に戻るボタン -->
            <div style="margin-bottom: 20px; text-align: right;">
                <a href="select.php?tab=inventory" class="btn">📦 在庫一覧に戻る</a>
            </div>

            <!-- 商品追加/編集フォーム -->
            <div class="card">
                <h3><?php echo $item_to_edit ? '📝 商品編集' : '➕ 新商品追加'; ?></h3>
                <form method="POST" action="create.php">
                    <input type="hidden" name="action" value="<?php echo $item_to_edit ? 'update_item' : 'add_item'; ?>">
                    <?php if ($item_to_edit): ?>
                        <input type="hidden" name="id" value="<?php echo h($item_to_edit['id']); ?>">
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div>
                            <div class="form-group">
                                <label>商品名 *</label>
                                <input type="text" name="name" required value="<?php echo h($item_to_edit['name'] ?? ''); ?>" placeholder="例: アイスコーヒー">
                            </div>
                            <div class="form-group">
                                <label>カテゴリ *</label>
                                <select name="category_id" required>
                                    <option value="">選択してください</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php if(isset($item_to_edit['category_id']) && $item_to_edit['category_id'] == $category['id']) echo 'selected'; ?>><?php echo h($category['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>在庫数 *</label>
                                <input type="number" name="quantity" min="0" required value="<?php echo h($item_to_edit['quantity'] ?? 0); ?>" placeholder="初期在庫数">
                            </div>
                            <div class="form-group">
                                <label>単位 *</label>
                                <input type="text" name="unit" required value="<?php echo h($item_to_edit['unit'] ?? ''); ?>" placeholder="例: 個, 杯, kg">
                            </div>
                            <div class="form-group">
                                <label>発注点</label>
                                <input type="number" name="reorder_level" min="0" value="<?php echo h($item_to_edit['reorder_level'] ?? 10); ?>" placeholder="在庫警告レベル">
                                <small style="color: #666; font-size: 0.9em;">この数量以下になると警告が表示されます</small>
                            </div>
                        </div>
                        <div>
                            <div class="form-group">
                                <label>仕入価格(円) *</label>
                                <input type="number" name="cost_price" step="0.01" min="0" required value="<?php echo h($item_to_edit['cost_price'] ?? ''); ?>" placeholder="原価">
                            </div>
                            <div class="form-group">
                                <label>販売価格(円) *</label>
                                <input type="number" name="selling_price" step="0.01" min="0" required value="<?php echo h($item_to_edit['selling_price'] ?? ''); ?>" placeholder="売価">
                            </div>
                            <div class="form-group">
                                <label>仕入先</label>
                                <input type="text" name="supplier" value="<?php echo h($item_to_edit['supplier'] ?? ''); ?>" placeholder="例: 株式会社○○">
                            </div>
                            <div class="form-group">
                                <label>賞味期限</label>
                                <input type="date" name="expiry_date" value="<?php echo h($item_to_edit['expiry_date'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- 歩合設定セクション -->
                    <div style="margin-top: 20px; padding: 15px; background: #f8fafc; border-radius: 6px; border-left: 4px solid #007bff;">
                        <h4 style="color: #007bff; margin-bottom: 15px;">💰 歩合設定</h4>
                        <div class="form-grid">
                            <div>
                                <div class="form-group">
                                    <label>歩合タイプ</label>
                                    <select name="commission_type" id="commission_type" onchange="toggleCommissionInput()">
                                        <option value="percentage" <?php if(isset($item_to_edit['commission_type']) && $item_to_edit['commission_type'] === 'percentage') echo 'selected'; ?>>パーセンテージ (%)</option>
                                        <option value="fixed_amount" <?php if(isset($item_to_edit['commission_type']) && $item_to_edit['commission_type'] === 'fixed_amount') echo 'selected'; ?>>固定額 (円)</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <!-- パーセンテージ用 -->
                                <div class="form-group" id="percentage_input" style="<?php echo (isset($item_to_edit['commission_type']) && $item_to_edit['commission_type'] === 'fixed_amount') ? 'display:none;' : ''; ?>">
                                    <label>歩合率 (%)</label>
                                    <input type="number" name="commission_rate" step="0.1" min="0" max="100" 
                                           value="<?php echo h($item_to_edit['commission_rate'] ?? 0); ?>" 
                                           placeholder="例: 5.0">
                                    <small style="color: #666; font-size: 0.9em;">売上に対するパーセンテージを設定</small>
                                </div>

                                <!-- 固定額用 -->
                                <div class="form-group" id="fixed_amount_input" style="<?php echo (!isset($item_to_edit['commission_type']) || $item_to_edit['commission_type'] !== 'fixed_amount') ? 'display:none;' : ''; ?>">
                                    <label>固定歩合額 (円)</label>
                                    <input type="number" name="fixed_commission_amount" step="1" min="0" 
                                           value="<?php echo h($item_to_edit['fixed_commission_amount'] ?? 0); ?>" 
                                           placeholder="例: 50">
                                    <small style="color: #666; font-size: 0.9em;">商品1個あたりの固定歩合額を設定</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <button type="submit" class="btn"><?php echo $item_to_edit ? '💾 商品を更新' : '💾 商品を追加'; ?></button>
                        <?php if ($item_to_edit): ?>
                            <a href="select.php?tab=inventory" class="btn secondary" style="margin-left: 10px;">キャンセル</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- 新規カテゴリ追加フォーム -->
            <div class="card">
                <h3>🆕 新規カテゴリ追加</h3>
                <form method="POST" action="input.php" style="display: flex; gap: 10px; align-items: flex-end;">
                    <input type="hidden" name="action" value="add_category">
                    <div class="form-group" style="flex-grow: 1; margin-bottom: 0;">
                        <label for="category_name">カテゴリ名:</label>
                        <input type="text" id="category_name" name="category_name" required placeholder="例: スイーツ, コーヒー豆" style="width: 100%;">
                    </div>
                    <button type="submit" class="btn">追加</button>
                </form>
            </div>

            <!-- 入出庫フォーム -->
            <div class="card" id="movement">
                <h3>🔄 入出庫処理</h3>
                <form method="POST" action="create.php">
                    <input type="hidden" name="action" value="update_stock">
                    <div class="form-grid">
                        <div>
                            <div class="form-group">
                                <label>商品選択 *</label>
                                <select name="item_id" required>
                                    <option value="">選択してください</option>
                                    <?php foreach ($inventory_items as $item): ?>
                                        <option value="<?php echo $item['id']; ?>">
                                            <?php echo h($item['name']); ?> 
                                            (現在: <?php echo $item['quantity']; ?><?php echo h($item['unit']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>処理種別 *</label>
                                <select name="movement_type" required>
                                    <option value="入庫">📦 入庫</option>
                                    <option value="出庫">📤 出庫</option>
                                    <option value="廃棄">🗑️ 廃棄</option>
                                    <option value="調整">⚖️ 調整</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <div class="form-group">
                                <label>数量 *</label>
                                <input type="number" name="new_quantity" min="1" required placeholder="処理する数量">
                            </div>
                            <div class="form-group">
                                <label>理由・メモ</label>
                                <textarea name="reason" rows="3" placeholder="入出庫の理由やメモを入力（任意）"></textarea>
                            </div>
                        </div>
                    </div>
                    <div style="text-align: center; margin-top: 20px;">
                        <button type="submit" class="btn">🔄 在庫を更新</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function toggleCommissionInput() {
        const commissionType = document.getElementById('commission_type').value;
        const percentageInput = document.getElementById('percentage_input');
        const fixedAmountInput = document.getElementById('fixed_amount_input');
        
        if (commissionType === 'percentage') {
            percentageInput.style.display = 'block';
            fixedAmountInput.style.display = 'none';
            // 固定額フィールドをクリア
            const fixedInput = document.querySelector('input[name="fixed_commission_amount"]');
            if (fixedInput) fixedInput.value = '0';
        } else {
            percentageInput.style.display = 'none';
            fixedAmountInput.style.display = 'block';
            // パーセンテージフィールドをクリア
            const percentageInputField = document.querySelector('input[name="commission_rate"]');
            if (percentageInputField) percentageInputField.value = '0';
        }
    }

    // ページ読み込み時にも実行
    document.addEventListener('DOMContentLoaded', function() {
        toggleCommissionInput();
        
        // フォーム送信前のバリデーション
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('input[required], select[required]');
                let allValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.style.borderColor = '#dc3545';
                        allValid = false;
                    } else {
                        field.style.borderColor = '#ddd';
                    }
                });
                
                if (!allValid) {
                    e.preventDefault();
                    alert('必須項目を入力してください。');
                }
            });
        });
        
        // 販売価格が仕入価格より低い場合の警告
        const costPrice = document.querySelector('input[name="cost_price"]');
        const sellingPrice = document.querySelector('input[name="selling_price"]');
        
        if (costPrice && sellingPrice) {
            function checkPrices() {
                const cost = parseFloat(costPrice.value) || 0;
                const selling = parseFloat(sellingPrice.value) || 0;
                
                if (cost > 0 && selling > 0 && selling < cost) {
                    sellingPrice.style.borderColor = '#f0ad4e';
                    sellingPrice.title = '販売価格が仕入価格を下回っています';
                } else {
                    sellingPrice.style.borderColor = '#ddd';
                    sellingPrice.title = '';
                }
            }
            
            costPrice.addEventListener('change', checkPrices);
            sellingPrice.addEventListener('change', checkPrices);
        }
    });
    </script>

    <style>
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 15px;
    }
    
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
            gap: 10px;
        }
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #333;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        transition: border-color 0.2s;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #007bff;
    }
    
    .form-group small {
        display: block;
        margin-top: 5px;
    }
    
    .btn.secondary {
        background: #6c757d;
        color: white;
    }
    
    .btn.secondary:hover {
        background: #5a6268;
    }
    </style>
</body>
</html>