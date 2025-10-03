<?php
// modules/pos/POSService.php
/**
 * POS機能のビジネスロジック（スタッフ別会計対応版）
 * staff_id問題修正版
 */
class POSService {
    private $pdo;
    private $taxRate;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->taxRate = (float)getSetting($pdo, 'tax_rate', 10);
    }
    
    /**
     * 販売可能商品一覧を取得（カテゴリ別対応）
     */
    public function getAvailableProducts($categoryId = null) {
        try {
            $sql = "
                SELECT i.id, i.name, i.selling_price AS price, i.quantity AS stock, i.unit, 
                       i.commission_type, i.commission_rate, i.fixed_commission_amount,
                       c.name AS category_name
                FROM inventory i 
                LEFT JOIN categories c ON i.category_id = c.id
            ";
            
            if ($categoryId) {
                $sql .= " WHERE i.category_id = ?";
                $stmt = $this->pdo->prepare($sql . " ORDER BY i.name ASC");
                $stmt->execute([$categoryId]);
            } else {
                $stmt = $this->pdo->prepare($sql . " ORDER BY c.name ASC, i.name ASC");
                $stmt->execute();
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting products: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * カテゴリ一覧を取得
     */
    public function getCategories() {
        try {
            $stmt = $this->pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting categories: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 商品をカートに追加（スタッフ別対応）
     * 修正: スタッフ未選択の場合はログインユーザーをデフォルトに設定
     */
    public function addToCart($productId, $quantity, $staffId = null) {
        // 商品情報を取得
        $stmt = $this->pdo->prepare("
            SELECT id, name, selling_price AS price, quantity AS stock, unit,
                   commission_type, commission_rate, fixed_commission_amount
            FROM inventory WHERE id = ?
        ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            throw new Exception('商品が見つかりません');
        }
        
        // スタッフIDが未指定または0の場合、ログインユーザーをデフォルトに設定
        if (empty($staffId) && isset($_SESSION['user_id'])) {
            $staffId = $_SESSION['user_id'];
        }
        
        // 在庫チェック（全カートアイテムの合計）
        $currentCartQuantity = $this->getTotalCartQuantityForProduct($productId);
        $totalRequested = $currentCartQuantity + $quantity;
        
        if ($totalRequested > $product['stock']) {
            throw new Exception("「{$product['name']}」の在庫が不足しています。現在の在庫: {$product['stock']}{$product['unit']}");
        }
        
        // カートキーの生成（商品ID + スタッフID）
        $cartKey = $this->generateCartKey($productId, $staffId);
        
        // カートに追加または更新
        if (isset($_SESSION['cart'][$cartKey])) {
            $_SESSION['cart'][$cartKey]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$cartKey] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $quantity,
                'commission_type' => $product['commission_type'],
                'commission_rate' => $product['commission_rate'],
                'fixed_commission_amount' => $product['fixed_commission_amount'],
                'staff_id' => $staffId,
                'staff_name' => $staffId ? $this->getStaffName($staffId) : '未選択'
            ];
        }
        
        return true;
    }
    
    /**
     * カートから商品を削除
     */
    public function removeFromCart($cartKey) {
        if (isset($_SESSION['cart'][$cartKey])) {
            $itemName = $_SESSION['cart'][$cartKey]['name'];
            unset($_SESSION['cart'][$cartKey]);
            return $itemName;
        }
        return false;
    }
    
    /**
     * カート内容の計算
     */
    public function calculateCart() {
        if (empty($_SESSION['cart'])) {
            return [
                'subtotal' => 0,
                'tax' => 0,
                'total' => 0,
                'total_commission' => 0
            ];
        }
        
        $subtotal = 0;
        $totalCommission = 0;
        
        foreach ($_SESSION['cart'] as $item) {
            $itemTotal = $item['price'] * $item['quantity'];
            $subtotal += $itemTotal;
            
            // 歩合計算
            if ($item['commission_type'] === 'percentage') {
                $totalCommission += $itemTotal * ($item['commission_rate'] / 100);
            } elseif ($item['commission_type'] === 'fixed_amount') {
                $totalCommission += $item['fixed_commission_amount'] * $item['quantity'];
            }
        }
        
        $tax = $subtotal * ($this->taxRate / 100);
        $total = $subtotal + $tax;
        
        return [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'total_commission' => $totalCommission
        ];
    }
    
    /**
     * 会計処理
     * 修正: スタッフ未選択商品のstaff_id確保処理を追加
     */
    public function processCheckout($cashReceived, $userId) {
        if (empty($_SESSION['cart'])) {
            throw new Exception('カートが空です');
        }
        
        // スタッフ未選択の商品をチェック・修正
        $this->ensureAllItemsHaveStaff($userId);
        
        $calculation = $this->calculateCart();
        
        if ($cashReceived < $calculation['total']) {
            throw new Exception('受取金額が合計金額より少ないです');
        }
        
        $this->pdo->beginTransaction();
        
        try {
            // 在庫確認と更新
            $this->validateAndUpdateInventory();
            
            // 取引記録
            $transactionId = $this->recordTransaction(
                $calculation['total'], 
                $cashReceived, 
                $cashReceived - $calculation['total'],
                $userId,
                $calculation['total_commission']
            );
            
            // 取引明細記録
            $this->recordTransactionItems($transactionId, $_SESSION['cart']);
            
            $this->pdo->commit();
            
            $change = $cashReceived - $calculation['total'];
            
            // 在庫アラートチェック
            $this->checkLowStockAlerts();
            
            // カートクリア
            $_SESSION['cart'] = [];
            
            return [
                'transaction_id' => $transactionId,
                'change' => $change,
                'total' => $calculation['total']
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * スタッフをカート商品に割り当て（新しいエントリ作成対応）
     */
    public function assignStaffToCartItem($cartKey, $staffId) {
        if (isset($_SESSION['cart'][$cartKey])) {
            $item = $_SESSION['cart'][$cartKey];
            
            // 新しいスタッフが割り当てられる場合
            if ($item['staff_id'] != $staffId) {
                // 元のエントリを削除
                unset($_SESSION['cart'][$cartKey]);
                
                // 新しいキーで再作成
                $newCartKey = $this->generateCartKey($item['id'], $staffId);
                
                // 既存の同じ商品+スタッフの組み合わせがあるかチェック
                if (isset($_SESSION['cart'][$newCartKey])) {
                    $_SESSION['cart'][$newCartKey]['quantity'] += $item['quantity'];
                } else {
                    $item['staff_id'] = $staffId;
                    $item['staff_name'] = $this->getStaffName($staffId);
                    $_SESSION['cart'][$newCartKey] = $item;
                }
                
                return $newCartKey;
            }
            
            return $cartKey;
        }
        return false;
    }
    
    /**
     * 全ユーザー（スタッフ）一覧を取得
     */
    public function getStaffList() {
        try {
            $stmt = $this->pdo->query("SELECT id, username FROM users ORDER BY username");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting staff list: " . $e->getMessage());
            return [];
        }
    }
    
    // プライベートメソッド
    private function generateCartKey($productId, $staffId) {
        return $productId . '_' . ($staffId ?? 'unassigned');
    }
    
    private function getTotalCartQuantityForProduct($productId) {
        $total = 0;
        foreach ($_SESSION['cart'] as $cartKey => $item) {
            if ($item['id'] == $productId) {
                $total += $item['quantity'];
            }
        }
        return $total;
    }
    
    /**
     * 新規追加: カート内の全商品にstaff_idが設定されていることを確保
     */
    private function ensureAllItemsHaveStaff($defaultUserId) {
        foreach ($_SESSION['cart'] as $cartKey => &$item) {
            if (empty($item['staff_id'])) {
                $item['staff_id'] = $defaultUserId;
                $item['staff_name'] = $this->getStaffName($defaultUserId);
                
                // ログに記録
                error_log("Auto-assigned staff_id {$defaultUserId} to item {$item['name']} in cart");
            }
        }
        unset($item); // 参照を解除
    }
    
    private function validateAndUpdateInventory() {
        // 商品別の合計数量を計算
        $productQuantities = [];
        foreach ($_SESSION['cart'] as $item) {
            $productId = $item['id'];
            if (!isset($productQuantities[$productId])) {
                $productQuantities[$productId] = 0;
            }
            $productQuantities[$productId] += $item['quantity'];
        }
        
        // 各商品の在庫確認と更新
        foreach ($productQuantities as $productId => $totalQuantity) {
            $this->updateInventory($productId, $totalQuantity);
        }
    }
    
    private function updateInventory($itemId, $quantity) {
        // 在庫確認（FOR UPDATEでロック）
        $stmt = $this->pdo->prepare("SELECT quantity, name FROM inventory WHERE id = ? FOR UPDATE");
        $stmt->execute([$itemId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current || $current['quantity'] < $quantity) {
            throw new Exception("「{$current['name']}」の在庫が不足しています");
        }
        
        // 在庫更新
        $stmt = $this->pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
        $stmt->execute([$quantity, $itemId]);
        
        // 履歴記録
        $stmt = $this->pdo->prepare("
            INSERT INTO stock_movements 
            (item_id, movement_type, quantity, reason, created_by) 
            VALUES (?, '出庫', ?, 'レジ販売', 'POS')
        ");
        $stmt->execute([$itemId, $quantity]);
    }
    
    private function recordTransaction($total, $cashReceived, $change, $userId, $totalCommission) {
        $stmt = $this->pdo->prepare("
            INSERT INTO transactions 
            (total_amount, cash_received, change_given, user_id, total_commission_amount) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$total, $cashReceived, $change, $userId, $totalCommission]);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * 修正: staff_idのNULLチェックとロギングを追加
     */
    private function recordTransactionItems($transactionId, $cartItems) {
        $stmt = $this->pdo->prepare("
            INSERT INTO transaction_items 
            (transaction_id, item_id, item_name, item_price, quantity, 
             item_commission_type, item_commission_rate, item_fixed_commission_amount, staff_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($cartItems as $item) {
            // staff_idがNULLでないことを確認
            if (empty($item['staff_id'])) {
                error_log("ERROR: staff_id is null for item: " . $item['name'] . " in transaction: " . $transactionId);
                throw new Exception("スタッフが選択されていない商品があります: " . $item['name']);
            }
            
            $stmt->execute([
                $transactionId,
                $item['id'],
                $item['name'],
                $item['price'],
                $item['quantity'],
                $item['commission_type'],
                $item['commission_rate'],
                $item['fixed_commission_amount'],
                $item['staff_id']
            ]);
            
            // 正常に記録されたことをログに記録
            error_log("Transaction item recorded: {$item['name']} with staff_id: {$item['staff_id']}");
        }
    }
    
    private function getStaffName($staffId) {
        if (!$staffId) return '未選択';
        
        try {
            $stmt = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$staffId]);
            return $stmt->fetchColumn() ?: '不明';
        } catch (PDOException $e) {
            return '不明';
        }
    }
    
    private function checkLowStockAlerts() {
        $lowStockThreshold = (int)getSetting($this->pdo, 'low_stock_threshold', 5);
        
        $stmt = $this->pdo->prepare("
            SELECT name, quantity 
            FROM inventory 
            WHERE quantity <= ? AND quantity > 0
        ");
        $stmt->execute([$lowStockThreshold]);
        $lowStockItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($lowStockItems)) {
            $alertMessages = [];
            foreach ($lowStockItems as $item) {
                $alertMessages[] = "⚠️ {$item['name']} の在庫が残り {$item['quantity']} 個です";
            }
            $_SESSION['warning'] = implode('<br>', $alertMessages);
        }
    }
}
?>
