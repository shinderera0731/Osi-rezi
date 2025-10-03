<?php
// modules/inventory/InventoryService.php

class InventoryService {
    private $pdo;
    private $lowStockThreshold;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->lowStockThreshold = (int)getSetting($pdo, 'low_stock_threshold', 5);
    }
    
    public function getProductById($id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT i.*, c.name as category_name 
                FROM inventory i 
                LEFT JOIN categories c ON i.category_id = c.id 
                WHERE i.id = ?
            ");
            $stmt->execute([$id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            return $product ?: null;
        } catch (PDOException $e) {
            error_log("Error getting product by ID: " . $e->getMessage());
            return null;
        }
    }

    public function getInventoryList($filters = []) {
        $sql = "SELECT i.*, c.name AS category_name FROM inventory i LEFT JOIN categories c ON i.category_id = c.id WHERE 1=1";
        $params = [];
        if (!empty($filters['category'])) {
            $sql .= " AND c.name = ?";
            $params[] = $filters['category'];
        }
        if (!empty($filters['search_keyword'])) {
            $sql .= " AND i.name LIKE ?";
            $params[] = '%' . $filters['search_keyword'] . '%';
        }
        $sql .= " ORDER BY c.name, i.name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getStockAlerts() {
        $stmt_low = $this->pdo->prepare("SELECT i.* FROM inventory i WHERE i.quantity <= i.reorder_level OR i.quantity <= ?");
        $stmt_low->execute([$this->lowStockThreshold]);
        $lowStock = $stmt_low->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt_expiring = $this->pdo->query("SELECT i.* FROM inventory i WHERE i.expiry_date IS NOT NULL AND i.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
        $expiring = $stmt_expiring->fetchAll(PDO::FETCH_ASSOC);
        
        return ['low_stock' => $lowStock, 'expiring' => $expiring];
    }
    
    /**
     * 入出庫履歴を取得
     */
    public function getStockMovements($limit = 20) {
        try {
            // ★エラー修正: LIMIT句のプレースホルダーを名前付き(:limit)に変更
            $stmt = $this->pdo->prepare("
                SELECT sm.*, i.name as item_name, i.unit
                FROM stock_movements sm
                JOIN inventory i ON sm.item_id = i.id
                ORDER BY sm.created_at DESC
                LIMIT :limit
            ");
            // ★エラー修正: bindValueを使って、LIMITに渡す値が必ず整数であることを保証する
            $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting stock movements: " . $e->getMessage());
            return [];
        }
    }
    
    public function getCategories() {
        return $this->pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getStatistics() {
        $totalItems = $this->pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
        $totalValue = $this->pdo->query("SELECT SUM(quantity * cost_price) FROM inventory")->fetchColumn() ?? 0;
        $lowStockCount = $this->pdo->prepare("SELECT COUNT(*) FROM inventory WHERE quantity <= reorder_level OR quantity <= ?");
        $lowStockCount->execute([$this->lowStockThreshold]);
        $expiringCount = $this->pdo->query("SELECT COUNT(*) FROM inventory WHERE expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();

        return [
            'total_items' => $totalItems,
            'total_value' => $totalValue,
            'low_stock_count' => $lowStockCount->fetchColumn(),
            'expiring_count' => $expiringCount
        ];
    }
    
    /**
     * API経由で在庫を更新
     * @param int $itemId 商品ID
     * @param int $quantity 更新数量
     * @param string $movementType 処理種別
     * @param string $reason 理由
     * @param string $createdBy 処理実行者
     */
    public function updateStockFromApi($itemId, $quantity, $movementType, $reason, $createdBy) {
        $this->pdo->beginTransaction();
        try {
            // 在庫を更新
            $stmt = $this->pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE id = ?");
            $stmt->execute([$quantity, $itemId]);

            // 在庫変動履歴を記録
            $stmt = $this->pdo->prepare("INSERT INTO stock_movements (item_id, movement_type, quantity, reason, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$itemId, $movementType, $quantity, $reason, $createdBy]);

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("API Stock Update Error: " . $e->getMessage());
            throw new Exception("在庫更新中にデータベースエラーが発生しました。");
        }
    }
}
