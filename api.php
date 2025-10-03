<?php
// api.php - 外部連携用APIエンドポイント（fixed_amount歩合対応版）
// 外部システムがAPIキーを使って在庫情報・売上データ・歩合データを取得するための窓口です。

// --- 共通設定の読み込み ---
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'modules/api/ApiKeyService.php';
require_once 'modules/inventory/InventoryService.php';

header('Content-Type: application/json; charset=UTF-8');

// POSTリクエストも許可
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// APIキーの検証（GETまたはPOSTからキーを取得）
$apiKey = $_REQUEST['api_key'] ?? ''; // GETとPOST両方から取得
$apiKeyService = new ApiKeyService($pdo);
$validationResult = $apiKeyService->validateApiKey($apiKey);

if (!$validationResult['valid']) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid API Key', 'details' => $validationResult['reason']]);
    exit;
}

// リクエストの処理
$action = $_REQUEST['action'] ?? ''; // GETとPOST両方から取得

switch ($action) {
    case 'get_products':
        try {
            $inventoryService = new InventoryService($pdo);
            $products = $inventoryService->getInventoryList();
            echo json_encode(['success' => true, 'data' => $products]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error']);
            error_log("API Error (get_products): " . $e->getMessage());
        }
        break;

    case 'check_stock':
        $itemId = $_REQUEST['item_id'] ?? 0;
        try {
            $inventoryService = new InventoryService($pdo);
            $product = $inventoryService->getProductById($itemId);
            if ($product) {
                echo json_encode(['success' => true, 'data' => ['id' => $product['id'], 'name' => $product['name'], 'quantity' => $product['quantity']]]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Product not found']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error']);
            error_log("API Error (check_stock): " . $e->getMessage());
        }
        break;
        
    case 'update_stock':
        // POSTリクエストのみ許可
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        $itemId = $_POST['item_id'] ?? 0;
        $quantity = $_POST['quantity'] ?? 0;
        $movementType = $_POST['movement_type'] ?? '';
        $reason = $_POST['reason'] ?? 'APIによる在庫更新';
        $createdBy = $_POST['created_by'] ?? 'API';

        // 入力値のバリデーション
        if ($itemId <= 0 || !is_numeric($quantity) || empty($movementType)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid parameters. Required: item_id, quantity, movement_type.']);
            exit;
        }

        try {
            $inventoryService = new InventoryService($pdo);
            $inventoryService->updateStockFromApi($itemId, $quantity, $movementType, $reason, $createdBy);
            echo json_encode(['success' => true, 'message' => 'Stock updated successfully.']);
        } catch (Exception $e) {
            http_response_code(400); // 在庫不足などのクライアントエラーとして扱う
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            error_log("API Error (update_stock): " . $e->getMessage());
        }
        break;

    // 給与計算アプリ連携用エンドポイント
    case 'get_staff_list':
        try {
            // hourly_rateカラムとstaff_idカラムが存在するかチェック
            $check_columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'hourly_rate'");
            $has_hourly_rate = $check_columns->rowCount() > 0;
            $check_columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'staff_id'");
            $has_staff_id = $check_columns->rowCount() > 0;

            $select_columns = 'id, username';
            if ($has_staff_id) {
                $select_columns .= ', staff_id';
            }
            if ($has_hourly_rate) {
                $select_columns .= ', hourly_rate';
            } else {
                // hourly_rateカラムが存在しない場合は0.00を返す
                $select_columns .= ', 0.00 as hourly_rate';
            }

            $sql = "SELECT {$select_columns} FROM users WHERE role IN ('staff', 'admin') ORDER BY username";
            
            $stmt = $pdo->query($sql);
            $staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 数値として返すため型変換
            foreach ($staff_list as &$staff) {
                if (isset($staff['hourly_rate'])) {
                    $staff['hourly_rate'] = (float)$staff['hourly_rate'];
                }
            }
            
            echo json_encode(['success' => true, 'data' => $staff_list]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error']);
            error_log("API Error (get_staff_list): " . $e->getMessage());
        }
        break;

    // 売上データ取得（歩合給計算用）- fixed_amount対応版
    case 'get_sales_data':
        $employee_id = (int)($_REQUEST['employee_id'] ?? 0);
        $start_date = $_REQUEST['start_date'] ?? date('Y-m-d');
        $end_date = $_REQUEST['end_date'] ?? date('Y-m-d');
        
        try {
            $sql = "SELECT 
                        t.id as transaction_id,
                        t.transaction_date,
                        t.total_amount,
                        ti.staff_id,
                        ti.item_name,
                        ti.item_price,
                        ti.quantity,
                        ti.item_commission_type,
                        ti.item_commission_rate,
                        ti.item_fixed_commission_amount,
                        (ti.item_price * ti.quantity) as sales_amount,
                        CASE 
                            WHEN ti.item_commission_type = 'percentage' THEN 
                                (ti.item_price * ti.quantity * ti.item_commission_rate / 100)
                            WHEN ti.item_commission_type = 'fixed_amount' THEN 
                                (ti.item_fixed_commission_amount * ti.quantity)
                            ELSE 0
                        END as commission_amount
                    FROM transactions t
                    JOIN transaction_items ti ON t.id = ti.transaction_id
                    WHERE t.is_deleted = 0
                    AND DATE(t.transaction_date) BETWEEN ? AND ?";
            
            $params = [$start_date, $end_date];
            
            if ($employee_id > 0) {
                $sql .= " AND ti.staff_id = ?";
                $params[] = $employee_id;
            }
            
            $sql .= " ORDER BY t.transaction_date DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $sales_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 集計データも計算
            $total_sales = 0;
            $total_commission = 0;
            foreach ($sales_data as $sale) {
                $total_sales += $sale['sales_amount'];
                $total_commission += $sale['commission_amount'];
            }
            
            echo json_encode([
                'success' => true, 
                'data' => $sales_data,
                'summary' => [
                    'total_sales' => $total_sales,
                    'total_commission' => $total_commission,
                    'transaction_count' => count($sales_data)
                ]
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error']);
            error_log("API Error (get_sales_data): " . $e->getMessage());
        }
        break;

    // スタッフの歩合率取得
    case 'get_staff_rates':
        try {
            $sql = "SELECT 
                        u.id,
                        u.username,
                        sc.commission_rate as base_commission_rate,
                        sd.employee_id,
                        sd.hire_date
                    FROM users u
                    LEFT JOIN staff_commissions sc ON u.id = sc.user_id
                    LEFT JOIN staff_details sd ON u.id = sd.user_id
                    WHERE u.role = 'staff'
                    ORDER BY u.username";
            
            $stmt = $pdo->query($sql);
            $staff_rates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $staff_rates]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error']);
            error_log("API Error (get_staff_rates): " . $e->getMessage());
        }
        break;

    // 期間別歩合集計 - fixed_amount対応版
    case 'get_commission_summary':
        $staff_id = (int)($_REQUEST['staff_id'] ?? 0);
        $start_date = $_REQUEST['start_date'] ?? date('Y-m-01'); // 月初
        $end_date = $_REQUEST['end_date'] ?? date('Y-m-t'); // 月末
        
        if ($staff_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'staff_id is required']);
            exit;
        }
        
        try {
            // 日別売上・歩合集計
            $sql = "SELECT 
                        DATE(t.transaction_date) as sale_date,
                        COUNT(DISTINCT t.id) as transaction_count,
                        SUM(ti.item_price * ti.quantity) as daily_sales,
                        SUM(
                            CASE 
                                WHEN ti.item_commission_type = 'percentage' THEN 
                                    (ti.item_price * ti.quantity * ti.item_commission_rate / 100)
                                WHEN ti.item_commission_type = 'fixed_amount' THEN 
                                    (ti.item_fixed_commission_amount * ti.quantity)
                                ELSE 0
                            END
                        ) as daily_commission
                    FROM transactions t
                    JOIN transaction_items ti ON t.id = ti.transaction_id
                    WHERE t.is_deleted = 0
                    AND ti.staff_id = ?
                    AND DATE(t.transaction_date) BETWEEN ? AND ?
                    GROUP BY DATE(t.transaction_date)
                    ORDER BY DATE(t.transaction_date)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$staff_id, $start_date, $end_date]);
            $daily_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 期間合計
            $total_sales = array_sum(array_column($daily_data, 'daily_sales'));
            $total_commission = array_sum(array_column($daily_data, 'daily_commission'));
            $total_transactions = array_sum(array_column($daily_data, 'transaction_count'));
            
            // スタッフ情報取得
            $staff_sql = "SELECT u.username, sc.commission_rate as base_rate 
                         FROM users u 
                         LEFT JOIN staff_commissions sc ON u.id = sc.user_id 
                         WHERE u.id = ?";
            $staff_stmt = $pdo->prepare($staff_sql);
            $staff_stmt->execute([$staff_id]);
            $staff_info = $staff_stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'staff_info' => $staff_info,
                'period' => [
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ],
                'summary' => [
                    'total_sales' => $total_sales,
                    'total_commission' => $total_commission,
                    'total_transactions' => $total_transactions,
                    'average_commission_per_day' => count($daily_data) > 0 ? $total_commission / count($daily_data) : 0
                ],
                'daily_breakdown' => $daily_data
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error']);
            error_log("API Error (get_commission_summary): " . $e->getMessage());
        }
        break;

    // 全スタッフの月次歩合ランキング - fixed_amount対応版
    case 'get_monthly_ranking':
        $year_month = $_REQUEST['year_month'] ?? date('Y-m');
        $start_date = $year_month . '-01';
        $end_date = date('Y-m-t', strtotime($start_date));
        
        try {
            $sql = "SELECT 
                        u.id,
                        u.username,
                        COUNT(DISTINCT t.id) as transaction_count,
                        SUM(ti.item_price * ti.quantity) as monthly_sales,
                        SUM(
                            CASE 
                                WHEN ti.item_commission_type = 'percentage' THEN 
                                    (ti.item_price * ti.quantity * ti.item_commission_rate / 100)
                                WHEN ti.item_commission_type = 'fixed_amount' THEN 
                                    (ti.item_fixed_commission_amount * ti.quantity)
                                ELSE 0
                            END
                        ) as monthly_commission
                    FROM users u
                    LEFT JOIN transaction_items ti ON u.id = ti.staff_id
                    LEFT JOIN transactions t ON ti.transaction_id = t.id AND t.is_deleted = 0
                        AND DATE(t.transaction_date) BETWEEN ? AND ?
                    WHERE u.role = 'staff'
                    GROUP BY u.id, u.username
                    ORDER BY monthly_commission DESC, monthly_sales DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$start_date, $end_date]);
            $ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'period' => [
                    'year_month' => $year_month,
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ],
                'ranking' => $ranking
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error']);
            error_log("API Error (get_monthly_ranking): " . $e->getMessage());
        }
        break;

    // デバッグ用：特定取引の歩合計算確認
    case 'debug_commission':
        $transaction_id = (int)($_REQUEST['transaction_id'] ?? 0);
        
        if ($transaction_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'transaction_id is required']);
            exit;
        }
        
        try {
            $sql = "SELECT 
                        ti.*,
                        t.transaction_date,
                        u.username as staff_name,
                        CASE 
                            WHEN ti.item_commission_type = 'percentage' THEN 
                                (ti.item_price * ti.quantity * ti.item_commission_rate / 100)
                            WHEN ti.item_commission_type = 'fixed_amount' THEN 
                                (ti.item_fixed_commission_amount * ti.quantity)
                            ELSE 0
                        END as calculated_commission
                    FROM transaction_items ti
                    JOIN transactions t ON ti.transaction_id = t.id
                    LEFT JOIN users u ON ti.staff_id = u.id
                    WHERE t.id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$transaction_id]);
            $debug_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'transaction_id' => $transaction_id,
                'debug_data' => $debug_data
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error']);
            error_log("API Error (debug_commission): " . $e->getMessage());
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action', 'available_actions' => [
            'get_products', 'check_stock', 'update_stock', 'get_staff_list', 
            'get_sales_data', 'get_staff_rates', 'get_commission_summary', 
            'get_monthly_ranking', 'debug_commission'
        ]]);
        break;
}
?>
