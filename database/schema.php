<?php
// database/schema.php
// データベースの完全な構造と初期データを定義します。

/**
 * データベーステーブルを初期化（作成および初期データ投入）します。
 * @param PDO $pdo データベース接続オブジェクト
 * @return bool 成功した場合はtrue、失敗した場合はfalse
 */
function createTables($pdo) {
    try {
        // トランザクションタイムアウトを延長
        $pdo->exec("SET SESSION innodb_lock_wait_timeout = 300;");
        
        // 外部キー制約を無効化
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        
        // より安全なテーブル削除順序
        $tables_to_drop = [
            'api_keys',
            'staff_commissions', 
            'staff_details',
            'app_settings',
            'daily_settlement',
            'transaction_items',
            'transactions',
            'stock_movements',
            'inventory',
            'categories',
            'users'
        ];
        
        foreach ($tables_to_drop as $table) {
            try {
                $pdo->exec("DROP TABLE IF EXISTS `{$table}`;");
                error_log("Dropped table: {$table}");
            } catch (PDOException $e) {
                error_log("Failed to drop table {$table}: " . $e->getMessage());
                // 個別のテーブル削除失敗は継続
            }
        }
        
        // 外部キー制約を再有効化
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        // --- テーブル作成 ---
        // 商品カテゴリテーブル
        $pdo->exec("CREATE TABLE categories (
            id INT AUTO_INCREMENT PRIMARY KEY, 
            name VARCHAR(50) NOT NULL UNIQUE, 
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // 在庫テーブル
        $pdo->exec("CREATE TABLE inventory (
            id INT AUTO_INCREMENT PRIMARY KEY, 
            name VARCHAR(100) NOT NULL, 
            category_id INT, 
            quantity INT NOT NULL DEFAULT 0, 
            unit VARCHAR(20) NOT NULL, 
            cost_price DECIMAL(10,2) NOT NULL DEFAULT 0, 
            selling_price DECIMAL(10,2) NOT NULL DEFAULT 0, 
            commission_type ENUM('percentage', 'fixed_amount') NOT NULL DEFAULT 'percentage', 
            commission_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00, 
            fixed_commission_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00, 
            reorder_level INT DEFAULT 10, 
            supplier VARCHAR(100), 
            expiry_date DATE, 
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
            FOREIGN KEY (category_id) REFERENCES categories(id)
        )");

        // 在庫変動履歴テーブル
        $pdo->exec("CREATE TABLE stock_movements (
            id INT AUTO_INCREMENT PRIMARY KEY, 
            item_id INT, 
            movement_type ENUM('入庫', '出庫', '廃棄', '調整') NOT NULL, 
            quantity INT NOT NULL, 
            reason VARCHAR(200), 
            created_by VARCHAR(50), 
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
            FOREIGN KEY (item_id) REFERENCES inventory(id) ON DELETE CASCADE
        )");

        // ユーザーテーブル（時給・staff_idカラム含む）
        $pdo->exec("CREATE TABLE users (
           id INT AUTO_INCREMENT PRIMARY KEY, 
           username VARCHAR(50) NOT NULL UNIQUE, 
           password VARCHAR(255) NOT NULL, 
           role ENUM('admin', 'staff') DEFAULT 'staff', 
           staff_id VARCHAR(20) UNIQUE NULL,
           hourly_rate DECIMAL(10, 2) NOT NULL DEFAULT 0.00, 
           created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )");
        
        // 取引履歴テーブル
        $pdo->exec("CREATE TABLE transactions (
            id INT AUTO_INCREMENT PRIMARY KEY, 
            transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP, 
            total_amount DECIMAL(10, 2) NOT NULL, 
            cash_received DECIMAL(10, 2) NOT NULL, 
            change_given DECIMAL(10, 2) NOT NULL, 
            user_id INT NULL, 
            total_commission_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00, 
            is_deleted BOOLEAN NOT NULL DEFAULT 0, 
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )");

        // 取引商品詳細テーブル
        $pdo->exec("CREATE TABLE transaction_items (
            id INT AUTO_INCREMENT PRIMARY KEY, 
            transaction_id INT NOT NULL, 
            item_id INT NOT NULL, 
            item_name VARCHAR(100) NOT NULL, 
            item_price DECIMAL(10,2) NOT NULL, 
            quantity INT NOT NULL, 
            item_commission_type ENUM('percentage', 'fixed_amount') NOT NULL, 
            item_commission_rate DECIMAL(5,2) NOT NULL, 
            item_fixed_commission_amount DECIMAL(10,2) NOT NULL, 
            staff_id INT NULL, 
            FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE, 
            FOREIGN KEY (item_id) REFERENCES inventory(id) ON DELETE RESTRICT, 
            FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE SET NULL
        )");

        // 日次精算テーブル
        $pdo->exec("CREATE TABLE daily_settlement (
            id INT AUTO_INCREMENT PRIMARY KEY, 
            settlement_date DATE NOT NULL UNIQUE, 
            initial_cash_float DECIMAL(10, 2) NOT NULL, 
            total_sales_cash DECIMAL(10, 2) NOT NULL, 
            expected_cash_on_hand DECIMAL(10, 2) NOT NULL, 
            actual_cash_on_hand DECIMAL(10, 2) NULL, 
            discrepancy DECIMAL(10, 2) NULL
        )");

        // アプリケーション設定テーブル
        $pdo->exec("CREATE TABLE app_settings (
            setting_key VARCHAR(255) PRIMARY KEY, 
            setting_value TEXT
        )");

        // スタッフ詳細テーブル
        $pdo->exec("CREATE TABLE staff_details (
            id INT AUTO_INCREMENT PRIMARY KEY, 
            user_id INT NOT NULL UNIQUE, 
            employee_id VARCHAR(50) UNIQUE NULL, 
            hire_date DATE NULL, 
            phone_number VARCHAR(20) NULL, 
            address TEXT NULL, 
            emergency_contact VARCHAR(100) NULL, 
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        // スタッフ歩合テーブル
        $pdo->exec("CREATE TABLE staff_commissions (
            id INT AUTO_INCREMENT PRIMARY KEY, 
            user_id INT NOT NULL UNIQUE, 
            commission_rate DECIMAL(5, 2) NOT NULL DEFAULT 0.00, 
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        
        // APIキーテーブル
        $pdo->exec("CREATE TABLE api_keys (
            id INT AUTO_INCREMENT PRIMARY KEY, 
            user_id INT NOT NULL, 
            api_key VARCHAR(255) NOT NULL UNIQUE, 
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active', 
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        // --- 初期データ投入 ---
        
        // 1. カテゴリデータ
        $categories_data = [
            'ドリンク',
            'フード', 
            '原材料',
            'デザート',
            'スナック',
            '調味料',
            '消耗品',
            'その他'
        ];
        $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
        foreach ($categories_data as $category_name) {
            $stmt->execute([$category_name]);
        }

        // 2. ユーザーデータ（時給・staff_id付き）
        $admin_pass = password_hash('password', PASSWORD_DEFAULT);
        $staff_pass = password_hash('password', PASSWORD_DEFAULT);
        $manager_pass = password_hash('password', PASSWORD_DEFAULT);
        
        $pdo->prepare("INSERT INTO users (username, password, role, hourly_rate, staff_id) VALUES ('admin', ?, 'admin', 1500.00, 'EMP001')")->execute([$admin_pass]);
        $admin_id = $pdo->lastInsertId();
        
        $pdo->prepare("INSERT INTO users (username, password, role, hourly_rate, staff_id) VALUES ('staff', ?, 'staff', 1200.00, 'EMP002')")->execute([$staff_pass]);
        $staff_id = $pdo->lastInsertId();
        
        $pdo->prepare("INSERT INTO users (username, password, role, hourly_rate, staff_id) VALUES ('manager', ?, 'staff', 1350.00, 'EMP003')")->execute([$manager_pass]);
        $manager_id = $pdo->lastInsertId();

        // 3. スタッフ詳細データ
        $staff_details_data = [
            [$admin_id, 'EMP001', '2024-01-01', '090-1234-5678', '東京都渋谷区', '緊急連絡先: 家族 090-8765-4321'],
            [$staff_id, 'EMP002', '2024-02-01', '080-2345-6789', '東京都新宿区', '緊急連絡先: 家族 080-9876-5432'],
            [$manager_id, 'EMP003', '2024-01-15', '070-3456-7890', '東京都池袋区', '緊急連絡先: 家族 070-1234-9876']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO staff_details (user_id, employee_id, hire_date, phone_number, address, emergency_contact) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($staff_details_data as $detail) {
            $stmt->execute($detail);
        }

        // 4. スタッフ歩合データ
        $commissions_data = [
            [$admin_id, 0.00],
            [$staff_id, 3.50],
            [$manager_id, 5.00]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO staff_commissions (user_id, commission_rate) VALUES (?, ?)");
        foreach ($commissions_data as $commission) {
            $stmt->execute($commission);
        }

        // 5. 商品データ（inventory）
        $inventory_data = [
            // [商品名, カテゴリID, 数量, 単位, 原価, 販売価格, 歩合タイプ, 歩合率, 固定歩合額, 再注文レベル, 仕入先]
            ['ホットコーヒー', 1, 0, '杯', 80.00, 320.00, 'percentage', 5.00, 0.00, 0, 'コーヒー豆商会'],
            ['アイスコーヒー', 1, 0, '杯', 85.00, 350.00, 'percentage', 5.00, 0.00, 0, 'コーヒー豆商会'],
            ['カフェラテ', 1, 0, '杯', 120.00, 420.00, 'percentage', 6.00, 0.00, 0, 'コーヒー豆商会'],
            ['カプチーノ', 1, 0, '杯', 125.00, 450.00, 'percentage', 6.00, 0.00, 0, 'コーヒー豆商会'],
            ['エスプレッソ', 1, 0, '杯', 90.00, 280.00, 'percentage', 5.00, 0.00, 0, 'コーヒー豆商会'],
            ['紅茶', 1, 0, '杯', 60.00, 250.00, 'percentage', 4.00, 0.00, 0, '茶葉卸業者'],
            ['緑茶', 1, 0, '杯', 50.00, 200.00, 'percentage', 4.00, 0.00, 0, '茶葉卸業者'],
            ['オレンジジュース', 1, 0, '杯', 100.00, 300.00, 'percentage', 4.50, 0.00, 0, 'フルーツ卸'],
            ['アップルジュース', 1, 0, '杯', 100.00, 300.00, 'percentage', 4.50, 0.00, 0, 'フルーツ卸'],
            ['コーラ', 1, 0, '杯', 80.00, 250.00, 'percentage', 4.00, 0.00, 0, '飲料卸業者'],
            
            ['サンドイッチ（ハム&チーズ）', 2, 0, '個', 180.00, 480.00, 'fixed_amount', 0.00, 50.00, 5, 'パン卸業者'],
            ['サラダ（コブ）', 2, 0, '皿', 180.00, 480.00, 'percentage', 7.00, 0.00, 5, '野菜卸業者'],
            ['チーズケーキ', 4, 0, '個', 150.00, 380.00, 'percentage', 6.00, 0.00, 3, 'デザート卸'],
            ['チョコレートケーキ', 4, 0, '個', 160.00, 420.00, 'percentage', 6.00, 0.00, 3, 'デザート卸'],
            ['クッキー', 5, 0, '個', 50.00, 150.00, 'percentage', 4.00, 0.00, 10, 'お菓子卸'],
            
        ];

        $stmt = $pdo->prepare("INSERT INTO inventory (name, category_id, quantity, unit, cost_price, selling_price, commission_type, commission_rate, fixed_commission_amount, reorder_level, supplier) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($inventory_data as $item) {
            $stmt->execute($item);
        }

        // 6. サンプル在庫変動データ
        $stock_movements_data = [
            [1, '入庫', 100, '初期在庫設定', 'admin'],
            [2, '入庫', 100, '初期在庫設定', 'admin'],
            [11, '入庫', 50, '新規仕入れ', 'admin'],
            [12, '入庫', 30, '新規仕入れ', 'admin'],
            [13, '入庫', 20, '新規仕入れ', 'admin'],
            [14, '入庫', 15, '新規仕入れ', 'admin'],
            [15, '入庫', 100, '消耗品補充', 'staff']
        ];

        $stmt = $pdo->prepare("INSERT INTO stock_movements (item_id, movement_type, quantity, reason, created_by) VALUES (?, ?, ?, ?, ?)");
        foreach ($stock_movements_data as $movement) {
            $stmt->execute($movement);
        }

        // 在庫数を更新（在庫変動に基づく）
        foreach ($stock_movements_data as $movement) {
            $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE id = ?");
            $stmt->execute([$movement[2], $movement[0]]);
        }

        // 7. 日次精算初期データ（本日分）
        $today = date('Y-m-d');
        $pdo->prepare("INSERT INTO daily_settlement (settlement_date, initial_cash_float, total_sales_cash, expected_cash_on_hand) VALUES (?, 10000.00, 0.00, 10000.00)")->execute([$today]);

        // 8. アプリケーション設定
        $settings_data = [
            ['tax_rate', '10.0'],
            ['low_stock_threshold', '5'],
            ['currency', 'JPY'],
            ['business_name', 'カフェ管理システム'],
            ['business_address', '東京都渋谷区サンプル1-2-3'],
            ['business_phone', '03-1234-5678'],
            ['receipt_footer', 'ご利用ありがとうございました'],
            ['default_commission_rate', '5.0'],
            ['backup_frequency', 'daily']
        ];

        $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)");
        foreach ($settings_data as $setting) {
            $stmt->execute($setting);
        }

        // 9. 管理者用APIキー
        $admin_api_key = bin2hex(random_bytes(32));
        $pdo->prepare("INSERT INTO api_keys (user_id, api_key, status) VALUES (?, ?, 'active')")->execute([$admin_id, $admin_api_key]);

        error_log("Database initialization completed successfully");
        return true;
        
    } catch (PDOException $e) {
        error_log("Database Table Creation Error: " . $e->getMessage());
        error_log("Error Code: " . $e->getCode());
        error_log("SQL State: " . ($e->errorInfo[0] ?? 'Unknown'));
        return false;
    } finally {
        // 確実に外部キー制約を有効化
        try {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        } catch (PDOException $e) {
            error_log("Failed to re-enable foreign key checks: " . $e->getMessage());
        }
    }
}

/**
 * 既存データベース用：hourly_rateカラムを追加する関数
 * 既にテーブルが存在する場合に使用
 */
function addHourlyRateColumn($pdo) {
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN hourly_rate DECIMAL(10, 2) NOT NULL DEFAULT 0.00");
        
        // 既存ユーザーに時給を設定
        $pdo->exec("UPDATE users SET hourly_rate = 1500.00 WHERE role = 'admin'");
        $pdo->exec("UPDATE users SET hourly_rate = 1200.00 WHERE role = 'staff'");
        
        return true;
    } catch (PDOException $e) {
        // カラムが既に存在する場合はエラーを無視
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            return true;
        }
        error_log("Migration error: " . $e->getMessage());
        return false;
    }
}

/**
 * 次のstaff_IDを自動生成する関数
 */
function generateNextStaffId($pdo) {
    try {
        // 既存の最大staff_idを取得
        $stmt = $pdo->query("SELECT staff_id FROM users WHERE staff_id LIKE 'EMP%' ORDER BY staff_id DESC LIMIT 1");
        $last_staff_id = $stmt->fetchColumn();
        
        if ($last_staff_id) {
            // EMP003 -> 3 を抽出
            $last_number = (int)substr($last_staff_id, 3);
            $next_number = $last_number + 1;
        } else {
            // 初回の場合
            $next_number = 1;
        }
        
        return 'EMP' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        error_log("Staff ID generation error: " . $e->getMessage());
        return 'EMP999'; // フォールバック
    }
}

/**
 * サンプルトランザクションデータを追加する関数
 * デモ用途（staff_id対応版）
 */
function addSampleTransactions($pdo) {
    try {
        // サンプル取引データ
        $sample_transactions = [
            [
                'total_amount' => 800.00,
                'cash_received' => 1000.00,
                'change_given' => 200.00,
                'user_id' => 2,
                'items' => [
                    ['item_id' => 1, 'quantity' => 2, 'price' => 320.00],
                    ['item_id' => 11, 'quantity' => 1, 'price' => 480.00]
                ]
            ],
            [
                'total_amount' => 1200.00,
                'cash_received' => 1200.00,
                'change_given' => 0.00,
                'user_id' => 3,
                'items' => [
                    ['item_id' => 3, 'quantity' => 2, 'price' => 420.00],
                    ['item_id' => 12, 'quantity' => 1, 'price' => 480.00]
                ]
            ]
        ];

        foreach ($sample_transactions as $trans) {
            // トランザクション挿入
            $stmt = $pdo->prepare("INSERT INTO transactions (total_amount, cash_received, change_given, user_id, total_commission_amount) VALUES (?, ?, ?, ?, 0.00)");
            $stmt->execute([$trans['total_amount'], $trans['cash_received'], $trans['change_given'], $trans['user_id']]);
            $transaction_id = $pdo->lastInsertId();

            // トランザクションアイテム挿入（staff_id付き）
            $item_stmt = $pdo->prepare("INSERT INTO transaction_items (transaction_id, item_id, item_name, item_price, quantity, item_commission_type, item_commission_rate, item_fixed_commission_amount, staff_id) SELECT ?, i.id, i.name, ?, ?, i.commission_type, i.commission_rate, i.fixed_commission_amount, ? FROM inventory i WHERE i.id = ?");
            
            foreach ($trans['items'] as $item) {
                $item_stmt->execute([$transaction_id, $item['price'], $item['quantity'], $trans['user_id'], $item['item_id']]);
            }
        }

        return true;
    } catch (PDOException $e) {
        error_log("Sample transaction creation error: " . $e->getMessage());
        return false;
    }
}
?>