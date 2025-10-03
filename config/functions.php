<?php
// config/functions.php - 完全版（固定額歩合対応）

/**
 * HTMLエスケープ関数（XSS対策）
 */
function h($string, $encoding = 'UTF-8') {
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, $encoding);
}

/**
 * 日付フォーマット関数
 */
function formatDate($date, $format = 'Y年m月d日') {
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }
    return date($format, strtotime($date));
}

/**
 * 日時フォーマット関数
 */
function formatDateTime($datetime, $format = 'Y年m月d日 H:i') {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return '-';
    }
    return date($format, strtotime($datetime));
}

/**
 * 数値をカンマ区切りでフォーマット
 */
function formatNumber($number, $decimals = 0) {
    return number_format($number, $decimals);
}

/**
 * 金額をフォーマット（円マーク付き）
 */
function formatPrice($amount) {
    return '¥' . number_format($amount);
}

/**
 * formatCurrencyのエイリアス（下位互換性のため）
 */
function formatCurrency($amount) {
    return formatPrice($amount);
}

/**
 * 商品の歩合を計算（fixed_amount対応版）
 */
function calculateCommission($item, $quantity = 1, $price = null) {
    // 販売価格が指定されていない場合は商品の販売価格を使用
    if ($price === null) {
        $price = $item['selling_price'] ?? 0;
    }
    
    // 総販売額
    $totalSalesAmount = $price * $quantity;
    
    // 歩合タイプに応じた計算（fixed_amount対応）
    switch ($item['commission_type'] ?? 'percentage') {
        case 'fixed':
        case 'fixed_amount': // データベースの値に対応
            // 固定額の場合：固定額 × 数量
            $commission = ($item['fixed_commission_amount'] ?? 0) * $quantity;
            break;
            
        case 'percentage':
            // パーセンテージの場合：販売額 × 歩合率 / 100
            $commissionRate = $item['commission_rate'] ?? 0;
            $commission = $totalSalesAmount * ($commissionRate / 100);
            break;
            
        default:
            // その他の場合は歩合なし
            $commission = 0;
            break;
    }
    
    return round($commission, 2);
}

/**
 * 取引全体の歩合を計算（fixed_amount対応版）
 */
function calculateTransactionCommissions($cartItems, $pdo) {
    $totalCommission = 0;
    $staffCommissions = [];
    
    foreach ($cartItems as $cartItem) {
        // 商品データを取得
        $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
        $stmt->execute([$cartItem['product_id']]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            continue;
        }
        
        // この商品の歩合を計算
        $itemCommission = calculateCommission($item, $cartItem['quantity'], $cartItem['price']);
        $totalCommission += $itemCommission;
        
        // スタッフ別に歩合を集計
        $staffId = $cartItem['staff_id'] ?? null;
        if ($staffId && $itemCommission > 0) {
            if (!isset($staffCommissions[$staffId])) {
                $staffCommissions[$staffId] = 0;
            }
            $staffCommissions[$staffId] += $itemCommission;
        }
    }
    
    return [
        'total_commission' => round($totalCommission, 2),
        'staff_commissions' => $staffCommissions
    ];
}

/**
 * 在庫状態を取得
 */
function getStockStatus($item) {
    // 設定から低在庫しきい値を取得（デフォルト：5）
    global $pdo;
    $lowStockThreshold = 5;
    if (isset($pdo)) {
        $lowStockThreshold = (int)getSetting($pdo, 'low_stock_threshold', 5);
    }
    
    if ($item['quantity'] <= 0) {
        return ['class' => 'status-low', 'text' => '在庫切れ'];
    } elseif ($item['quantity'] <= ($item['reorder_level'] ?? 0) || $item['quantity'] <= $lowStockThreshold) {
        return ['class' => 'status-warning', 'text' => '在庫少'];
    } else {
        return ['class' => 'status-normal', 'text' => '正常'];
    }
}

/**
 * 賞味期限の状態を取得
 */
function getExpiryStatus($expiryDate) {
    if (!$expiryDate || $expiryDate === '0000-00-00') {
        return null;
    }
    
    $today = new DateTime();
    $expiry = new DateTime($expiryDate);
    $diff = $today->diff($expiry);
    $daysUntilExpiry = $diff->invert ? -$diff->days : $diff->days;
    
    if ($daysUntilExpiry < 0) {
        return ['class' => 'status-low', 'text' => '期限切れ', 'days' => $daysUntilExpiry];
    } elseif ($daysUntilExpiry <= 7) {
        return ['class' => 'status-warning', 'text' => '期限間近', 'days' => $daysUntilExpiry];
    } else {
        return ['class' => 'status-normal', 'text' => '正常', 'days' => $daysUntilExpiry];
    }
}

/**
 * 歩合設定をフォーマット（fixed_amount対応版）
 */
function formatCommission($commissionType, $commissionRate, $fixedCommissionAmount) {
    switch ($commissionType) {
        case 'fixed':
        case 'fixed_amount': // データベースの値に対応
            return formatPrice($fixedCommissionAmount) . ' (固定)';
        case 'percentage':
            return $commissionRate . '% (歩合)';
        default:
            return 'なし';
    }
}

/**
 * 新しいスタッフIDを生成する
 */
function generateNewStaffId($pdo) {
    try {
        // 既存の最大スタッフIDを取得
        $stmt = $pdo->query("SELECT staff_id FROM users WHERE staff_id IS NOT NULL ORDER BY staff_id DESC LIMIT 1");
        $lastStaffId = $stmt->fetchColumn();
        
        if ($lastStaffId) {
            // 既存のIDから数字部分を抽出して+1
            $number = (int)str_replace('EMP', '', $lastStaffId);
            $newNumber = $number + 1;
        } else {
            // 初回の場合は4番から開始（既存のEMP001-003は初期データ）
            $newNumber = 4;
        }
        
        return 'EMP' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        error_log("Staff ID generation error: " . $e->getMessage());
        // エラーの場合はタイムスタンプベースのIDを生成
        return 'EMP' . date('ymdHis');
    }
}

/**
 * 安全なリダイレクト関数
 */
function safeRedirect($url) {
    // ヘッダーインジェクション対策
    $url = str_replace(["\r", "\n"], '', $url);
    header('Location: ' . $url);
    exit;
}

/**
 * ファイルサイズを読みやすい形式にフォーマット
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * データベーステーブルが存在するかチェック
 */
function tableExists($pdo, $tableName) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$tableName}'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * 消費税計算
 */
function calculateTax($amount, $taxRate = 10) {
    return round($amount * ($taxRate / 100));
}

/**
 * 税込み金額計算
 */
function calculateTaxIncludedAmount($amount, $taxRate = 10) {
    return $amount + calculateTax($amount, $taxRate);
}

/**
 * 配列から特定のキーの値を抽出
 */
function pluck($array, $key) {
    return array_map(function($item) use ($key) {
        return is_array($item) ? $item[$key] : $item->$key;
    }, $array);
}

/**
 * 配列をグループ化
 */
function groupBy($array, $key) {
    $result = [];
    foreach ($array as $item) {
        $groupKey = is_array($item) ? $item[$key] : $item->$key;
        $result[$groupKey][] = $item;
    }
    return $result;
}

/**
 * JSONレスポンスを送信
 */
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * CSRFトークン生成
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRFトークン検証
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * バリデーション: 必須チェック
 */
function validateRequired($value, $fieldName) {
    if (empty(trim($value))) {
        return "{$fieldName}は必須です。";
    }
    return null;
}

/**
 * バリデーション: 最小長チェック
 */
function validateMinLength($value, $minLength, $fieldName) {
    if (strlen(trim($value)) < $minLength) {
        return "{$fieldName}は{$minLength}文字以上で入力してください。";
    }
    return null;
}

/**
 * バリデーション: 数値チェック
 */
function validateNumeric($value, $fieldName) {
    if (!is_numeric($value)) {
        return "{$fieldName}は数値で入力してください。";
    }
    return null;
}

/**
 * バリデーション: 正の数チェック
 */
function validatePositiveNumber($value, $fieldName) {
    if (!is_numeric($value) || $value < 0) {
        return "{$fieldName}は0以上の数値で入力してください。";
    }
    return null;
}

/**
 * POSでの取引保存時の歩合記録
 */
function saveTransactionCommissions($pdo, $transactionId, $cartItems) {
    $commissionData = calculateTransactionCommissions($cartItems, $pdo);
    
    // 取引テーブルの総歩合額を更新
    $stmt = $pdo->prepare("UPDATE transactions SET total_commission_amount = ? WHERE id = ?");
    $stmt->execute([$commissionData['total_commission'], $transactionId]);
    
    // transaction_commissions テーブルが存在すれば、スタッフ別歩合を保存
    if (tableExists($pdo, 'transaction_commissions')) {
        foreach ($commissionData['staff_commissions'] as $staffId => $commissionAmount) {
            if ($commissionAmount > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO transaction_commissions (transaction_id, staff_id, commission_amount) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$transactionId, $staffId, $commissionAmount]);
            }
        }
    }
    
    return $commissionData;
}

/**
 * デバッグ用：歩合計算の詳細を表示
 */
function debugCommissionCalculation($item, $quantity = 1) {
    if ($_SERVER['SERVER_NAME'] === 'localhost') {
        echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px; background: #f9f9f9;'>";
        echo "<strong>歩合計算デバッグ</strong><br>";
        echo "商品: " . h($item['name']) . "<br>";
        echo "歩合タイプ: " . h($item['commission_type']) . "<br>";
        echo "歩合率: " . h($item['commission_rate']) . "%<br>";
        echo "固定歩合額: " . formatPrice($item['fixed_commission_amount']) . "<br>";
        echo "販売価格: " . formatPrice($item['selling_price']) . "<br>";
        echo "数量: " . $quantity . "<br>";
        
        $commission = calculateCommission($item, $quantity);
        echo "<strong>計算結果: " . formatPrice($commission) . "</strong><br>";
        echo "</div>";
    }
}

/**
 * エラーログ出力（開発環境でのデバッグ用）
 */
function debugLog($message, $data = null) {
    if ($_SERVER['SERVER_NAME'] === 'localhost') {
        error_log("DEBUG: {$message}" . ($data ? " - " . print_r($data, true) : ""));
    }
}
?>