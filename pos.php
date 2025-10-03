<?php
// pos.php - ★2段階決済対応版
// 新しいPOSメインファイル

// --- 共通設定の読み込み ---
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/functions.php';

// --- 専門家（サービスクラスとコントローラー）の読み込み ---
require_once 'modules/pos/POSService.php';
require_once 'modules/pos/POSController.php';

// ログイン必須
requireLogin();

// カートがなければ初期化
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// 支払い画面モードの判定
$paymentMode = isset($_GET['payment']) && $_GET['payment'] === 'true';

// リクエスト処理の司令塔（コントローラー）を呼び出す
$controller = new POSController($pdo);
if ($controller->handleRequest()) {
    // 処理が成功したら、ページを再読み込みして結果を反映
    header('Location: pos.php');
    exit;
}

// HTMLが出力される前にスタイルとナビゲーションを読み込む
require_once 'includes/styles.php';
require_once 'includes/navigation.php';

// 表示用データをシェフ（サービス）から受け取る
$viewData = $controller->getViewData();
$products = $viewData['products'];
$categories = $viewData['categories'];
$selectedCategory = $viewData['selected_category'];
$staffList = $viewData['staff_list'];
$cartCalculation = $viewData['cart_calculation'];
$taxRate = $viewData['tax_rate'];

// 低在庫アラートのしきい値を取得
$lowStockThreshold = (int)getSetting($pdo, 'low_stock_threshold', 5);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS画面 - Oshi-rezi</title>
    <style>
        /* 支払い画面専用スタイル */
        .payment-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .payment-modal {
            background: white;
            padding: 40px;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .payment-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 20px;
        }
        
        .payment-title {
            font-size: 2rem;
            font-weight: 700;
            color: #2563eb;
            margin-bottom: 10px;
        }
        
        .payment-summary {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .payment-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .payment-item:last-child {
            border-bottom: none;
        }
        
        .payment-total {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2563eb;
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .payment-input-section {
            margin-bottom: 30px;
        }
        
        .payment-input {
            width: 100%;
            padding: 15px;
            font-size: 1.3rem;
            text-align: center;
            border: 2px solid #2563eb;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .payment-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .payment-btn-large {
            padding: 15px 30px;
            font-size: 1.2rem;
            font-weight: 600;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            min-width: 150px;
        }
        
        .btn-complete {
            background: #059669;
            color: white;
        }
        
        .btn-complete:hover {
            background: #047857;
        }
        
        .btn-cancel {
            background: #6b7280;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #4b5563;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1><img src="images/osi-rezi2.png" alt="推しレジ" style="width: 40px; height: 40px; vertical-align: middle; margin-right: 10px; border-radius: 12px;">Osi-rezi</h1>
                    <p>レジ・会計</p>
                </div>
                <div class="header-right">
                    <span class="user-info">👤 <?php echo h($_SESSION['username']); ?></span>
                    <a href="logout.php" class="btn danger header-btn">ログアウト</a>
                </div>
            </div>
        </div>
        
        <div class="content">
            <?php echo getSimpleNavigation(); ?>

            <div class="section-split">
                <!-- 商品選択エリア -->
                <div class="card section-left">
                    <h3>🧾 商品選択</h3>
                    
                    <!-- カテゴリー選択 -->
                    <div style="margin-bottom: 20px;">
                        <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <label style="font-weight: 600;">カテゴリー:</label>
                            <select name="category" onchange="this.form.submit()" style="padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; background: white;">
                                <option value="">全て</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo ($selectedCategory == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo h($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($selectedCategory): ?>
                                <a href="pos.php" style="color: #2563eb; text-decoration: none; font-size: 14px;">✖ クリア</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <?php if (empty($products)): ?>
                        <p class="alert warning">販売できる商品が登録されていません。</p>
                    <?php else: ?>
                        <div class="product-grid">
                            <?php foreach ($products as $product): ?>
                                <div class="product-item" onclick="addToCart(<?php echo $product['id']; ?>)">
                                    <h4><?php echo h($product['name']); ?></h4>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- カート・会計エリア -->
                <div class="card section-right">
                    <h3>🛒 カート</h3>
                    <?php if (empty($_SESSION['cart'])): ?>
                        <p class="empty-cart">カートは空です。</p>
                    <?php else: ?>
                        <div style="margin-bottom: 15px;">
                            <?php foreach ($_SESSION['cart'] as $cartKey => $item): ?>
                                <div class="cart-item">
                                    <div class="cart-item-details">
                                        <span class="font-bold"><?php echo h($item['name']); ?></span>
                                        <span style="font-size: 0.9em; color: #666;"> x <?php echo $item['quantity']; ?></span>
                                        <br><?php echo formatPrice($item['price']); ?>
                                        
                                        <!-- スタッフ選択 -->
                                        <div class="cart-item-staff">
                                            <label>担当スタッフ:</label>
                                            <select onchange="updateCartStaff('<?php echo $cartKey; ?>', this.value)" class="form-group input">
                                                <option value="">選択してください</option>
                                                <?php foreach ($staffList as $staff): ?>
                                                    <option value="<?php echo $staff['id']; ?>" 
                                                        <?php echo ($item['staff_id'] == $staff['id']) ? 'selected' : ''; ?>>
                                                        <?php echo h($staff['username']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small>現在: <?php echo h($item['staff_name']); ?></small>
                                        </div>
                                    </div>
                                    <div class="cart-actions">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="remove_from_cart">
                                            <input type="hidden" name="cart_key" value="<?php echo $cartKey; ?>">
                                            <button type="submit" class="btn danger btn-small">削除</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- 合計表示 -->
                        <div class="cart-total">
                            <div class="total-row">
                                <span>小計:</span>
                                <span><?php echo formatPrice($cartCalculation['subtotal']); ?></span>
                            </div>
                            <div class="total-row">
                                <span>税 (<?php echo $taxRate; ?>%):</span>
                                <span><?php echo formatPrice($cartCalculation['tax']); ?></span>
                            </div>
                            <div class="total-row final">
                                <span>合計:</span>
                                <span><?php echo formatPrice($cartCalculation['total']); ?></span>
                            </div>
                        </div>

                        <!-- 支払いへ進むボタン -->
                        <div style="margin-top: 20px;">
                            <button onclick="showPaymentScreen()" class="btn success payment-btn cash" style="width: 100%;">
                                支払いへ進む
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 支払い画面モーダル -->
    <div id="paymentScreen" class="payment-screen" style="display: none;">
        <div class="payment-modal">
            <div class="payment-header">
                <h2 class="payment-title">💰 支払い確認</h2>
            </div>
            
            <div class="payment-summary">
                <h3 style="margin-bottom: 15px;">📋 注文内容</h3>
                <?php if (!empty($_SESSION['cart'])): ?>
                    <?php foreach ($_SESSION['cart'] as $item): ?>
                        <div class="payment-item">
                            <div>
                                <span><?php echo h($item['name']); ?> × <?php echo $item['quantity']; ?></span>
                                <?php if (!empty($item['staff_name']) && $item['staff_name'] !== '未選択'): ?>
                                    <br><small style="color: #6b7280;">担当: <?php echo h($item['staff_name']); ?></small>
                                <?php endif; ?>
                            </div>
                            <span><?php echo formatPrice($item['price'] * $item['quantity']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div class="payment-total">
                    <div class="payment-item">
                        <span>小計:</span>
                        <span><?php echo formatPrice($cartCalculation['subtotal']); ?></span>
                    </div>
                    <div class="payment-item">
                        <span>税込合計:</span>
                        <span style="font-size: 1.2em; font-weight: 700;"><?php echo formatPrice($cartCalculation['total']); ?></span>
                    </div>
                </div>
            </div>
            
            <form method="POST" id="checkoutForm">
                <input type="hidden" name="action" value="checkout">
                <div class="payment-input-section">
                    <label style="font-size: 1.1em; font-weight: 600; margin-bottom: 10px; display: block;">受取金額を入力:</label>
                    <input type="number" id="cash_received" name="cash_received" 
                           step="1" min="<?php echo ceil($cartCalculation['total']); ?>" 
                           required class="payment-input" placeholder="現金額を入力" autofocus>
                    <div id="changeAmount" style="font-size: 1.1em; color: #059669; font-weight: 600; text-align: center; min-height: 25px;"></div>
                </div>
                
                <div class="payment-buttons">
                    <button type="button" onclick="hidePaymentScreen()" class="payment-btn-large btn-cancel">
                        戻る
                    </button>
                    <button type="submit" class="payment-btn-large btn-complete">
                        会計完了
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // スタッフ割り当て更新
        function updateCartStaff(cartKey, staffId) {
            const formData = new FormData();
            formData.append('action', 'update_cart_staff');
            formData.append('cart_key', cartKey);
            formData.append('staff_id', staffId);
            formData.append('ajax', '1');

            fetch('pos.php', {
                method: 'POST',
                body: formData
            })
            .then(() => location.reload())
            .catch(error => console.error('Error:', error));
        }

        // 商品クリックでカートに追加する機能
        function addToCart(productId) {
            const formData = new FormData();
            formData.append('action', 'add_to_cart');
            formData.append('product_id', productId);
            formData.append('quantity', 1);

            fetch('pos.php', {
                method: 'POST',
                body: formData
            })
            .then(() => location.reload())
            .catch(error => console.error('Error:', error));
        }

        // 支払い画面表示
        function showPaymentScreen() {
            document.getElementById('paymentScreen').style.display = 'flex';
            document.getElementById('cash_received').focus();
        }

        // 支払い画面非表示
        function hidePaymentScreen() {
            document.getElementById('paymentScreen').style.display = 'none';
        }

        // お釣り計算
        document.addEventListener('DOMContentLoaded', function() {
            const cashInput = document.getElementById('cash_received');
            const changeDisplay = document.getElementById('changeAmount');
            const total = <?php echo $cartCalculation['total']; ?>;

            if (cashInput && changeDisplay) {
                cashInput.addEventListener('input', function() {
                    const cashReceived = parseFloat(this.value) || 0;
                    const change = cashReceived - total;
                    
                    if (cashReceived >= total && cashReceived > 0) {
                        changeDisplay.textContent = `お釣り: ¥${change.toLocaleString()}`;
                        changeDisplay.style.color = '#059669';
                    } else if (cashReceived > 0) {
                        changeDisplay.textContent = `不足: ¥${Math.abs(change).toLocaleString()}`;
                        changeDisplay.style.color = '#dc2626';
                    } else {
                        changeDisplay.textContent = '';
                    }
                });
            }

            // 会計完了確認
            const checkoutForm = document.getElementById('checkoutForm');
            if (checkoutForm) {
                checkoutForm.addEventListener('submit', function(e) {
                    const cashReceived = parseFloat(document.getElementById('cash_received').value) || 0;
                    const change = cashReceived - total;
                    
                    if (cashReceived < total) {
                        e.preventDefault();
                        alert('受取金額が不足しています。');
                        return;
                    }
                    
                    if (!confirm(`合計: ¥${total.toLocaleString()}\n受取: ¥${cashReceived.toLocaleString()}\nお釣り: ¥${change.toLocaleString()}\n\n会計を完了しますか？`)) {
                        e.preventDefault();
                    }
                });
            }
        });

        // ESCキーで支払い画面を閉じる
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hidePaymentScreen();
            }
        });
    </script>
</body>
</html>