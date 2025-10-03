<?php
// modules/settlement/SettlementService.php

/**
 * 精算機能のビジネスロジック（計算やデータ取得）を担当するクラス
 */
class SettlementService {
    private $pdo;

    /**
     * コンストラクタ
     * @param PDO $pdo データベース接続オブジェクト
     */
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * 精算画面に必要なすべてのデータをまとめて取得します。
     * @return array 精算用データの連想配列
     */
    public function getSettlementData() {
        $today = date('Y-m-d');
        try {
            $settlement_data = $this->getTodaySettlement($today);
            $total_sales_cash = $this->getTodayTotalSales($today);
            $transaction_count = $this->getTodayTransactionCount($today);

            $initial_cash_float = $settlement_data['initial_cash_float'] ?? 0;
            $expected_cash_on_hand = $initial_cash_float + $total_sales_cash;

            return [
                'today' => $today,
                'settlement_data' => $settlement_data,
                'total_sales_cash' => $total_sales_cash,
                'transaction_count' => $transaction_count,
                'initial_cash_float' => $initial_cash_float,
                'expected_cash_on_hand' => $expected_cash_on_hand,
                'settlement_history' => $this->getSettlementHistory(),
            ];
        } catch (PDOException $e) {
            error_log("Settlement data acquisition error: " . $e->getMessage());
            // エラー時も画面が壊れないようにデフォルト値を返す
            return [
                'today' => $today,
                'settlement_data' => null,
                'total_sales_cash' => 0,
                'transaction_count' => 0,
                'initial_cash_float' => 0,
                'expected_cash_on_hand' => 0,
                'settlement_history' => [],
            ];
        }
    }

    /**
     * 今日の精算データを取得
     */
    private function getTodaySettlement($date) {
        $stmt = $this->pdo->prepare("SELECT * FROM daily_settlement WHERE settlement_date = ?");
        $stmt->execute([$date]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 今日の現金売上合計を取得
     */
    private function getTodayTotalSales($date) {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(total_amount), 0) AS total_sales
            FROM transactions
            WHERE DATE(transaction_date) = ?
        ");
        $stmt->execute([$date]);
        return $stmt->fetchColumn();
    }

    /**
     * 今日の取引件数を取得
     */
    private function getTodayTransactionCount($date) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM transactions WHERE DATE(transaction_date) = ?");
        $stmt->execute([$date]);
        return $stmt->fetchColumn();
    }

    /**
     * 過去7日間の精算履歴を取得
     */
    private function getSettlementHistory() {
        return $this->pdo->query("
            SELECT * FROM daily_settlement 
            WHERE settlement_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ORDER BY settlement_date DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}
