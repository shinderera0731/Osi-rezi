<?php
// modules/staff/StaffService.php

/**
 * スタッフ管理機能のビジネスロジック（データ取得など）を担当するクラス
 */
class StaffService {
    private $pdo;

    /**
     * コンストラクタ
     * @param PDO $pdo データベース接続オブジェクト
     */
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * すべてのスタッフの詳細情報を取得します。
     * @return array スタッフ情報の配列
     */
    public function getAllStaffDetails() {
        try {
            $stmt = $this->pdo->query("
                SELECT u.id, u.username, u.role,
                       sd.employee_id, sd.hire_date, sd.phone_number, sd.address, sd.emergency_contact,
                       sc.commission_rate
                FROM users u
                LEFT JOIN staff_details sd ON u.id = sd.user_id
                LEFT JOIN staff_commissions sc ON u.id = sc.user_id
                ORDER BY u.role, u.username
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Staff data loading error: " . $e->getMessage());
            return []; // エラーの場合は空の配列を返す
        }
    }
}
