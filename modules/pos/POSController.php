<?php
// modules/pos/POSController.php
/**
 * POS機能のリクエスト処理（スタッフ別会計対応版）
 */
class POSController {
    private $posService;
    
    public function __construct($pdo) {
        $this->posService = new POSService($pdo);
    }
    
    /**
     * リクエストを処理して適切なアクションを実行
     */
    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return false;
        }
        
        $action = $_POST['action'] ?? '';
        
        try {
            switch ($action) {
                case 'add_to_cart':
                    return $this->addToCart();
                case 'remove_from_cart':
                    return $this->removeFromCart();
                case 'update_cart_staff':
                    return $this->updateCartStaff();
                case 'checkout':
                    return $this->processCheckout();
                default:
                    return false;
            }
        } catch (Exception $e) {
            $_SESSION['error'] = '❌ ' . $e->getMessage();
            return true;
        }
    }
    
    /**
     * カートに商品を追加
     */
    private function addToCart() {
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        
        // スタッフIDがPOSTで提供されない場合は、ログインユーザーのIDを使用
        $staffId = (int)($_POST['staff_id'] ?? 0);
        if ($staffId === 0 && isset($_SESSION['user_id'])) {
            $staffId = (int)$_SESSION['user_id'];
        }
        
        if ($productId <= 0 || $quantity <= 0) {
            throw new Exception('無効な商品IDまたは数量です');
        }
        
        $this->posService->addToCart($productId, $quantity, $staffId);
        $_SESSION['message'] = '✅ カートに商品を追加しました';
        return true;
    }
    
    /**
     * カートから商品を削除
     */
    private function removeFromCart() {
        $cartKey = $_POST['cart_key'] ?? '';
        
        if (empty($cartKey)) {
            throw new Exception('無効なカートキーです');
        }
        
        $itemName = $this->posService->removeFromCart($cartKey);
        if ($itemName) {
            $_SESSION['message'] = "✅ カートから商品「{$itemName}」を削除しました";
        }
        return true;
    }
    
    /**
     * カート内商品のスタッフ割り当てを更新
     */
    private function updateCartStaff() {
        $cartKey = $_POST['cart_key'] ?? '';
        $staffId = (int)($_POST['staff_id'] ?? 0) ?: null;
        
        if (empty($cartKey)) {
            throw new Exception('無効なカートキーです');
        }
        
        $newCartKey = $this->posService->assignStaffToCartItem($cartKey, $staffId);
        
        // AJAX リクエストの場合は何も返さない
        if (isset($_POST['ajax'])) {
            exit;
        }
        return true;
    }
    
    /**
     * 会計処理
     */
    private function processCheckout() {
        $cashReceived = (float)($_POST['cash_received'] ?? 0);
        $userId = $_SESSION['user_id'];
        
        if ($cashReceived <= 0) {
            throw new Exception('受取金額を正しく入力してください');
        }
        
        $result = $this->posService->processCheckout($cashReceived, $userId);
        $_SESSION['message'] = "✅ 会計が完了しました！お釣り: ¥" . number_format($result['change']);
        
        return true;
    }
    
    /**
     * POS画面用のデータを取得
     */
    public function getViewData() {
        // カテゴリーフィルターを取得
        $categoryId = isset($_GET['category']) ? (int)$_GET['category'] : null;
        
        return [
            'products' => $this->posService->getAvailableProducts($categoryId),
            'categories' => $this->posService->getCategories(),
            'selected_category' => $categoryId,
            'staff_list' => $this->posService->getStaffList(),
            'cart_calculation' => $this->posService->calculateCart(),
            'tax_rate' => (float)getSetting($GLOBALS['pdo'], 'tax_rate', 10)
        ];
    }
}
?>
