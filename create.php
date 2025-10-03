<?php
// create.php - 最終リファクタリング版
// すべてのデータ作成・更新・削除処理を担当するメインキッチン

// --- 共通設定の読み込み（新しい専門書を読み込む） ---
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/functions.php';
require_once 'includes/messages.php'; // メッセージ表示機能を追加
// 追加: APIキーサービスを読み込み
require_once 'modules/api/ApiKeyService.php';

// POSTリクエスト以外は受け付けない
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setErrorMessage('不正なアクセスです。');
    header('Location: index.php');
    exit;
}

// ログイン必須 (ただし、データベース初期化アクションは除く)
$action = $_POST['action'] ?? '';
if ($action !== 'create_tables' && $action !== 'register_staff') { // register_staffアクションも除外
    requireLogin();
}

try {
    switch ($action) {
        
        // ★修正：新規スタッフ登録アクション（staff_id自動生成対応）
        case 'register_staff':
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $from_settings = isset($_POST['from_settings']) && $_POST['from_settings'] === 'true';

            // バリデーション
            if (empty($username) || empty($password) || strlen($password) < 6) {
                setErrorMessage('ユーザー名とパスワードを正しく入力してください。');
                header('Location: ' . ($from_settings ? 'register.php?from_settings=true' : 'register.php'));
                exit;
            }

            // ユーザー名の重複チェック
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                setErrorMessage('このユーザー名は既に存在します。別のユーザー名をお試しください。');
                header('Location: ' . ($from_settings ? 'register.php?from_settings=true' : 'register.php'));
                exit;
            }

            $pdo->beginTransaction();
            try {
                // ★修正：新しいスタッフIDを生成
                $staff_id = generateNewStaffId($pdo);
                
                // パスワードをハッシュ化してusersテーブルに保存（staff_id付き）
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt_user = $pdo->prepare("INSERT INTO users (staff_id, username, password, role, hourly_rate) VALUES (?, ?, ?, 'staff', 1200.00)");
                $stmt_user->execute([$staff_id, $username, $hashed_password]);
                $new_user_id = $pdo->lastInsertId();

                // staff_details にも関連データを挿入（employee_idにもstaff_idを設定）
                $stmt_details = $pdo->prepare("INSERT INTO staff_details (user_id, employee_id, hire_date) VALUES (?, ?, ?)");
                $stmt_details->execute([$new_user_id, $staff_id, date('Y-m-d')]);
                
                // staff_commissions にも初期データを挿入（デフォルト歩合率）
                $default_commission_rate = (float)getSetting($pdo, 'default_commission_rate', 5.0);
                $stmt_commission = $pdo->prepare("INSERT INTO staff_commissions (user_id, commission_rate) VALUES (?, ?)");
                $stmt_commission->execute([$new_user_id, $default_commission_rate]);

                $pdo->commit();

                setSuccessMessage('アカウントが正常に登録されました。スタッフID: ' . $staff_id . ($from_settings ? '' : ' ログインしてください。'));
                header('Location: ' . ($from_settings ? 'select.php?tab=staff_management' : 'login.php'));
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Registration Error: " . $e->getMessage());
                setErrorMessage('データベースエラーが発生しました: ' . $e->getMessage());
                header('Location: ' . ($from_settings ? 'register.php?from_settings=true' : 'register.php'));
                exit;
            }
            break;
        
        // データベーステーブル作成
        case 'create_tables':
            // 修正: 新しい設計図保管庫から設計図を読み込む
            $schema_path = __DIR__ . '/database/schema.php';
            if (!file_exists($schema_path)) {
                setErrorMessage('スキーマファイルが見つかりません。');
                header('Location: index.php');
                exit;
            }
            
            require_once $schema_path;
            if (createTables($pdo)) {
                setSuccessMessage('データベースが正常に作成されました。');
            } else {
                setErrorMessage('テーブル作成に失敗しました。');
            }
            header('Location: index.php');
            exit;
        
        // ★修正：APIキーの生成 (ユーザー選択欄削除に対応)
        case 'generate_api_key':
        case 'generate_api_key_for_admin': // ApiKeysView.phpから送られる新しいアクション名
            requireAdmin();
            
            // 全てのAPIキーは、初期設定で作成されるユーザーID=1 (admin) に紐づける
            // これは、APIキーをどのユーザーに紐づけるかの「選択」を不要にするため
            // 実際のAPIアクセス権は、APIキーのステータス('active')によって制御される
            $adminUserId = 1; 

            $apiKeyService = new ApiKeyService($pdo);
            try {
                $newKey = $apiKeyService->generateNewApiKey($adminUserId);
                setSuccessMessage('新しいAPIキーを生成しました。');
                setWarningMessage('生成されたキー: ' . $newKey);
            } catch (Exception $e) {
                setErrorMessage($e->getMessage());
            }

            header('Location: select.php?tab=api_keys');
            exit;
            
        // 追加: APIキーの有効化
        case 'activate_api_key':
        case 'deactivate_api_key':
            requireAdmin();
            $keyId = (int)($_POST['api_key_id'] ?? 0);
            if ($keyId > 0) {
                $apiKeyService = new ApiKeyService($pdo);
                if ($action === 'activate_api_key') {
                    $apiKeyService->activateApiKey($keyId);
                    setSuccessMessage('APIキーを有効化しました。');
                } else {
                    $apiKeyService->deactivateApiKey($keyId);
                    $apiKeyService->deactivateApiKey($keyId);
                    setSuccessMessage('APIキーを無効化しました。');
                }
            }
            header('Location: select.php?tab=api_keys');
            exit;
        
        // 追加: APIキーの削除
        case 'delete_api_key':
            requireAdmin();
            $keyId = (int)($_POST['api_key_id'] ?? 0);
            if ($keyId > 0) {
                $apiKeyService = new ApiKeyService($pdo);
                $apiKeyService->deleteApiKey($keyId);
                setSuccessMessage('APIキーを削除しました。');
            }
            header('Location: select.php?tab=api_keys');
            exit;

        // 釣銭準備金の設定
        case 'set_cash_float':
            $initial_cash_float = (float)($_POST['initial_cash_float'] ?? 0);
            $today = date('Y-m-d');
            
            if ($initial_cash_float < 0) {
                setErrorMessage('釣銭準備金は0以上で入力してください。');
                header('Location: select.php?tab=settlement');
                exit;
            }
            
            try {
                // 今日の売上合計を取得
                $stmt_sales = $pdo->prepare("
                    SELECT COALESCE(SUM(total_amount), 0) AS total_sales
                    FROM transactions
                    WHERE DATE(transaction_date) = ? AND is_deleted = 0
                ");
                $stmt_sales->execute([$today]);
                $total_sales_cash = $stmt_sales->fetchColumn();
                
                // 既存レコードの確認
                $stmt = $pdo->prepare("SELECT * FROM daily_settlement WHERE settlement_date = ?");
                $stmt->execute([$today]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // 更新
                    $expected_cash_on_hand = $initial_cash_float + $total_sales_cash;
                    $stmt = $pdo->prepare("
                        UPDATE daily_settlement 
                        SET initial_cash_float = ?, 
                            total_sales_cash = ?,
                            expected_cash_on_hand = ? 
                        WHERE settlement_date = ?
                    ");
                    $stmt->execute([$initial_cash_float, $total_sales_cash, $expected_cash_on_hand, $today]);
                    setSuccessMessage('釣銭準備金を更新しました。');
                } else {
                    // 新規作成
                    $expected_cash_on_hand = $initial_cash_float + $total_sales_cash;
                    $stmt = $pdo->prepare("
                        INSERT INTO daily_settlement (settlement_date, initial_cash_float, total_sales_cash, expected_cash_on_hand)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$today, $initial_cash_float, $total_sales_cash, $expected_cash_on_hand]);
                    setSuccessMessage('釣銭準備金を設定しました。');
                }
            } catch (PDOException $e) {
                error_log("Cash float setting error: " . $e->getMessage());
                setErrorMessage('釣銭準備金の設定に失敗しました。');
            }
            
            header('Location: select.php?tab=settlement');
            exit;
            
        // 取引削除アクション（修正版）
        case 'delete_transaction':
            $transaction_id = (int)($_POST['transaction_id'] ?? 0);
            
            if ($transaction_id <= 0) {
                setErrorMessage('無効な取引IDです。');
                header('Location: select.php?tab=transactions');
                exit;
            }

            $pdo->beginTransaction();
            try {
                // 在庫を元に戻す処理
                $stmt_items = $pdo->prepare("
                    SELECT item_id, quantity
                    FROM transaction_items
                    WHERE transaction_id = ?
                ");
                $stmt_items->execute([$transaction_id]);
                $transaction_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

                foreach ($transaction_items as $item) {
                    // 在庫を元に戻す
                    $stmt_update = $pdo->prepare("
                        UPDATE inventory SET quantity = quantity + ? WHERE id = ?
                    ");
                    $stmt_update->execute([$item['quantity'], $item['item_id']]);
                    
                    // 在庫変動履歴を記録（修正: created_byカラム対応）
                    $created_by = $_SESSION['username'] ?? 'System';
                    $stmt_movement = $pdo->prepare("
                        INSERT INTO stock_movements (item_id, movement_type, quantity, reason, created_by) 
                        VALUES (?, '調整', ?, ?, ?)
                    ");
                    $stmt_movement->execute([
                        $item['item_id'], 
                        $item['quantity'], 
                        "取引ID: {$transaction_id} の削除に伴う在庫復元", 
                        $created_by
                    ]);
                }
                
                // 取引の論理削除
                $stmt_delete = $pdo->prepare("
                    UPDATE transactions SET is_deleted = 1 WHERE id = ?
                ");
                $stmt_delete->execute([$transaction_id]);

                $pdo->commit();
                
                setSuccessMessage("取引ID: {$transaction_id} の取引を削除し、在庫を元に戻しました。");
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Transaction Deletion Error: " . $e->getMessage());
                error_log("SQL State: " . $e->getCode());
                setErrorMessage('取引の削除中にデータベースエラーが発生しました: ' . $e->getMessage());
            }
            
            header('Location: select.php?tab=transactions');
            exit;

        // 新商品追加
        case 'add_item':
            $name = trim($_POST['name'] ?? '');
            $category_id = (int)($_POST['category_id'] ?? 0);
            
            if (empty($name)) {
                setErrorMessage('商品名を入力してください。');
                header('Location: input.php?tab=inventory_ops');
                exit;
            }
            
            // 同名商品の重複チェック
            $stmt = $pdo->prepare("SELECT id FROM inventory WHERE name = ?");
            $stmt->execute([$name]);
            if ($stmt->fetch()) {
                setErrorMessage("商品「{$name}」は既に登録されています。");
                header('Location: input.php?tab=inventory_ops');
                exit;
            }

            // 商品追加
            $stmt = $pdo->prepare("INSERT INTO inventory (name, category_id, quantity, unit, cost_price, selling_price, commission_type, commission_rate, fixed_commission_amount, reorder_level, supplier, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $name, 
                $category_id, 
                (int)($_POST['quantity'] ?? 0), 
                trim($_POST['unit'] ?? ''),
                (float)($_POST['cost_price'] ?? 0), 
                (float)($_POST['selling_price'] ?? 0),
                $_POST['commission_type'] ?? 'percentage', 
                (float)($_POST['commission_rate'] ?? 0),
                (float)($_POST['fixed_commission_amount'] ?? 0), 
                (int)($_POST['reorder_level'] ?? 10),
                trim($_POST['supplier'] ?? '') ?: null, 
                $_POST['expiry_date'] ?: null
            ]);
            
            setSuccessMessage("商品「{$name}」が追加されました。");
            header('Location: select.php?tab=inventory');
            exit;

        // 商品更新（新規追加）
        case 'update_item':
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $category_id = (int)($_POST['category_id'] ?? 0);
            
            if ($id <= 0) {
                setErrorMessage('無効な商品IDです。');
                header('Location: select.php?tab=inventory');
                exit;
            }

            if (empty($name)) {
                setErrorMessage('商品名を入力してください。');
                header('Location: input.php?tab=inventory_ops&edit_id=' . $id);
                exit;
            }

            // 同名商品の重複チェック（自分以外）
            $stmt = $pdo->prepare("SELECT id FROM inventory WHERE name = ? AND id != ?");
            $stmt->execute([$name, $id]);
            if ($stmt->fetch()) {
                setErrorMessage("商品「{$name}」は既に登録されています。");
                header('Location: input.php?tab=inventory_ops&edit_id=' . $id);
                exit;
            }

            // 商品更新
            $stmt = $pdo->prepare("
                UPDATE inventory SET 
                    name = ?, category_id = ?, quantity = ?, unit = ?, 
                    cost_price = ?, selling_price = ?, commission_type = ?, 
                    commission_rate = ?, fixed_commission_amount = ?, 
                    reorder_level = ?, supplier = ?, expiry_date = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $name, 
                $category_id, 
                (int)($_POST['quantity'] ?? 0), 
                trim($_POST['unit'] ?? ''),
                (float)($_POST['cost_price'] ?? 0), 
                (float)($_POST['selling_price'] ?? 0),
                $_POST['commission_type'] ?? 'percentage', 
                (float)($_POST['commission_rate'] ?? 0),
                (float)($_POST['fixed_commission_amount'] ?? 0), 
                (int)($_POST['reorder_level'] ?? 10),
                trim($_POST['supplier'] ?? '') ?: null, 
                $_POST['expiry_date'] ?: null,
                $id
            ]);
            
            setSuccessMessage("商品「{$name}」を更新しました。");
            header('Location: select.php?tab=inventory');
            exit;

        // 商品削除（新規追加）
        case 'delete_item':
            requireAdmin(); // 管理者のみ削除可能
            $item_id = (int)($_POST['item_id'] ?? 0);
            
            if ($item_id <= 0) {
                setErrorMessage('無効な商品IDです。');
                header('Location: select.php?tab=inventory');
                exit;
            }

            try {
                // 商品名を取得（ログ用）
                $stmt = $pdo->prepare("SELECT name FROM inventory WHERE id = ?");
                $stmt->execute([$item_id]);
                $item_name = $stmt->fetchColumn();
                
                if (!$item_name) {
                    setErrorMessage('削除対象の商品が見つかりません。');
                    header('Location: select.php?tab=inventory');
                    exit;
                }

                // 商品を削除
                $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
                $stmt->execute([$item_id]);
                
                setSuccessMessage("商品「{$item_name}」を削除しました。");
            } catch (PDOException $e) {
                error_log("Item deletion error: " . $e->getMessage());
                setErrorMessage('商品の削除に失敗しました。この商品は取引で使用されている可能性があります。');
            }
            
            header('Location: select.php?tab=inventory');
            exit;

        // 在庫更新
        case 'update_stock':
            $item_id = (int)($_POST['item_id'] ?? 0);
            $new_quantity = (int)($_POST['new_quantity'] ?? 0);
            $movement_type = $_POST['movement_type'] ?? '';
            $reason = trim($_POST['reason'] ?? '');
            $created_by = $_SESSION['username'] ?? 'System';

            if ($item_id <= 0 || $new_quantity <= 0) {
                setErrorMessage('商品と数量を正しく選択してください。');
                header('Location: input.php?tab=inventory_ops');
                exit;
            }

            // 出庫・廃棄の場合は数量を負数に
            if ($movement_type === '出庫' || $movement_type === '廃棄') {
                $new_quantity = -$new_quantity;
            }

            $pdo->beginTransaction();
            try {
                // 現在の在庫数をチェック（出庫・廃棄の場合）
                if ($new_quantity < 0) {
                    $stmt = $pdo->prepare("SELECT quantity, name FROM inventory WHERE id = ?");
                    $stmt->execute([$item_id]);
                    $current_item = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($current_item['quantity'] + $new_quantity < 0) {
                        setErrorMessage("在庫不足です。現在庫: {$current_item['quantity']}, 要求数量: " . abs($new_quantity));
                        header('Location: input.php?tab=inventory_ops');
                        exit;
                    }
                }

                // 在庫を更新
                $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE id = ?");
                $stmt->execute([$new_quantity, $item_id]);

                // 在庫変動履歴を記録
                $stmt = $pdo->prepare("INSERT INTO stock_movements (item_id, movement_type, quantity, reason, created_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$item_id, $movement_type, abs($new_quantity), $reason, $created_by]);

                $pdo->commit();

                setSuccessMessage("在庫が更新されました。");
                header('Location: select.php?tab=inventory');
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Stock Update Error: " . $e->getMessage());
                setErrorMessage('在庫更新中にデータベースエラーが発生しました。');
                header('Location: input.php?tab=inventory_ops');
                exit;
            }

        // スタッフ詳細更新（新規追加）
        case 'update_staff_details':
            requireAdmin();
            $user_id = (int)($_POST['user_id'] ?? 0);
            $username = trim($_POST['username'] ?? '');
            $role = $_POST['role'] ?? 'staff';
            
            if ($user_id <= 0 || empty($username)) {
                setErrorMessage('無効なデータです。');
                header('Location: select.php?tab=staff_management');
                exit;
            }

            $pdo->beginTransaction();
            try {
                // ユーザー情報更新
                $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $role, $user_id]);

                // スタッフ詳細更新
                $stmt = $pdo->prepare("
                    UPDATE staff_details SET 
                        employee_id = ?, hire_date = ?, phone_number = ?, 
                        address = ?, emergency_contact = ? 
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    trim($_POST['employee_id'] ?? '') ?: null,
                    $_POST['hire_date'] ?: null,
                    trim($_POST['phone_number'] ?? '') ?: null,
                    trim($_POST['address'] ?? '') ?: null,
                    trim($_POST['emergency_contact'] ?? '') ?: null,
                    $user_id
                ]);

                // 歩合率更新
                $commission_rate = (float)($_POST['commission_rate'] ?? 0);
                $stmt = $pdo->prepare("
                    UPDATE staff_commissions SET commission_rate = ? WHERE user_id = ?
                ");
                $stmt->execute([$commission_rate, $user_id]);

                $pdo->commit();
                setSuccessMessage("スタッフ「{$username}」の情報を更新しました。");
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Staff update error: " . $e->getMessage());
                setErrorMessage('スタッフ情報の更新に失敗しました。');
            }
            
            header('Location: select.php?tab=staff_management');
            exit;

        // スタッフ削除（新規追加）
        case 'delete_staff':
            requireAdmin();
            $user_id = (int)($_POST['user_id'] ?? 0);
            
            if ($user_id <= 0) {
                setErrorMessage('無効なユーザーIDです。');
                header('Location: select.php?tab=staff_management');
                exit;
            }

            // 自分自身は削除不可
            if ($user_id === $_SESSION['user_id']) {
                setErrorMessage('自分自身を削除することはできません。');
                header('Location: select.php?tab=staff_management');
                exit;
            }

            try {
                // ユーザー名を取得（ログ用）
                $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $username = $stmt->fetchColumn();

                // ユーザー削除（関連テーブルも CASCADE で削除される）
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                
                setSuccessMessage("スタッフ「{$username}」を削除しました。");
            } catch (PDOException $e) {
                error_log("Staff deletion error: " . $e->getMessage());
                setErrorMessage('スタッフの削除に失敗しました。このスタッフは取引データに関連している可能性があります。');
            }
            
            header('Location: select.php?tab=staff_management');
            exit;
            
        // データベースリセット（修正版）
        case 'reset_database':
            requireAdmin();
            
            $confirmation_key = trim($_POST['confirmation_key'] ?? '');
            
            // 確認キーのチェック
            if ($confirmation_key !== 'RESET_DATABASE') {
                setErrorMessage('確認キーが正しくありません。');
                header('Location: select.php?tab=settings');
                exit;
            }
            
            try {
                // エラーログに開始をマーク
                error_log("Database reset started by user: " . ($_SESSION['username'] ?? 'Unknown'));
                
                // データベース初期化（絶対パスで指定）
                $schema_path = __DIR__ . '/database/schema.php';
                if (!file_exists($schema_path)) {
                    throw new Exception("スキーマファイルが見つかりません: {$schema_path}");
                }
                
                require_once $schema_path;
                
                // タイムアウトを延長
                set_time_limit(300); // 5分
                
                if (createTables($pdo)) {
                    // 現在のセッションを破棄（管理者アカウントが再作成されるため）
                    session_destroy();
                    
                    error_log("Database reset completed successfully");
                    
                    // セッション破棄後のメッセージ設定は困難なので、URLパラメータで渡す
                    header('Location: login.php?reset_success=1');
                } else {
                    throw new Exception('テーブル作成処理でエラーが発生しました');
                }
            } catch (Exception $e) {
                error_log("Database Reset Error: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                
                setErrorMessage('データベースリセット中にエラーが発生しました: ' . $e->getMessage());
                header('Location: select.php?tab=settings');
            }
            exit;

        // アプリケーション設定保存
        case 'save_app_settings':
            requireAdmin();
            
            $tax_rate = (float)($_POST['tax_rate'] ?? 10);
            $low_stock_threshold = (int)($_POST['low_stock_threshold'] ?? 5);
            
            try {
                // 設定値の更新
                $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmt->execute(['tax_rate', $tax_rate]);
                $stmt->execute(['low_stock_threshold', $low_stock_threshold]);
                
                setSuccessMessage('設定が保存されました。');
            } catch (PDOException $e) {
                error_log("Settings save error: " . $e->getMessage());
                setErrorMessage('設定の保存に失敗しました。');
            }
            
            header('Location: select.php?tab=settings');
            exit;
        
        default:
            setErrorMessage('無効な操作です。');
            header('Location: index.php');
            exit;
    }

} catch (PDOException $e) {
    error_log("Database Error in create.php: " . $e->getMessage());
    setErrorMessage('データベースエラーが発生しました。');
    header('Location: index.php');
    exit;
}

/**
 * 新しいスタッフIDを生成する関数
 */

?>
