<?php
// modules/reports/ReportsService.php

class ReportsService {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getComprehensiveReportData($period, $year, $month) {
        try {
            $trendData = [];
            switch ($period) {
                case 'yearly': $trendData = $this->getYearlyPerformance(); break;
                case 'monthly': $trendData = $this->getMonthlyPerformanceForYear($year); break;
                default: $trendData = $this->getDailyPerformanceForMonth($year, $month); break;
            }
            return [
                'today_sales' => $this->getTodaySales(),
                'month_sales' => $this->getSalesForMonth($year, $month),
                'staff_performance' => $this->getStaffPerformanceForMonth($year, $month),
                'product_performance' => $this->getProductPerformanceForMonth($year, $month),
                'trend_data' => $trendData,
            ];
        } catch (PDOException $e) { return []; }
    }

    public function getDetailedReport($startDate, $endDate) {
        return [
            'total_sales'   => $this->getTotalSalesForPeriod($startDate, $endDate),
            'staff_sales'   => $this->getStaffSalesForPeriod($startDate, $endDate),
            'product_sales' => $this->getProductSalesForPeriod($startDate, $endDate),
            'daily_sales'   => $this->getDailySalesForPeriod($startDate, $endDate),
        ];
    }
    
    public function getTransactionsForPeriod($startDate, $endDate) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT t.*, u.username,
                    CASE 
                        WHEN t.is_deleted = 1 THEN '削除済み'
                        ELSE '通常'
                    END as status_label
                FROM transactions t
                LEFT JOIN users u ON t.user_id = u.id
                WHERE DATE(t.transaction_date) BETWEEN ? AND ?
                ORDER BY t.is_deleted ASC, t.transaction_date DESC
            ");
            $stmt->execute([$startDate, $endDate]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting transactions for period: " . $e->getMessage());
            return [];
        }
    }

    public function getDeletedTransactionDetails($transactionId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT t.*, u.username,
                    GROUP_CONCAT(
                        CONCAT(ti.item_name, ' × ', ti.quantity, '個 (', ti.item_price, '円)')
                        SEPARATOR ', '
                    ) as items_summary
                FROM transactions t
                LEFT JOIN users u ON t.user_id = u.id
                LEFT JOIN transaction_items ti ON t.id = ti.transaction_id
                WHERE t.id = ? AND t.is_deleted = 1
                GROUP BY t.id
            ");
            $stmt->execute([$transactionId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting deleted transaction details: " . $e->getMessage());
            return null;
        }
    }

    private function getTodaySales() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as transactions, COALESCE(SUM(total_amount), 0) as total, COALESCE(SUM(total_commission_amount), 0) as commission FROM transactions WHERE DATE(transaction_date) = CURDATE() AND is_deleted = 0");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getSalesForMonth($year, $month) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as transactions, COALESCE(SUM(total_amount), 0) as total, COALESCE(SUM(total_commission_amount), 0) as commission FROM transactions WHERE YEAR(transaction_date) = ? AND MONTH(transaction_date) = ? AND is_deleted = 0");
        $stmt->execute([$year, $month]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getStaffPerformanceForMonth($year, $month) {
        $stmt = $this->pdo->prepare("
            SELECT
                u.username,
                SUM(
                    CASE ti.item_commission_type
                        WHEN 'percentage' THEN (ti.item_price * ti.quantity) * (ti.item_commission_rate / 100)
                        WHEN 'fixed_amount' THEN ti.item_fixed_commission_amount * ti.quantity
                        ELSE 0
                    END
                ) AS staff_commission
            FROM
                transaction_items ti
            JOIN
                transactions t ON ti.transaction_id = t.id
            JOIN
                users u ON ti.staff_id = u.id
            WHERE
                YEAR(t.transaction_date) = ? AND MONTH(t.transaction_date) = ?
                AND ti.staff_id IS NOT NULL
                AND t.is_deleted = 0
            GROUP BY
                u.username
            ORDER BY
                staff_commission DESC
        ");
        $stmt->execute([$year, $month]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getProductPerformanceForMonth($year, $month) {
        $stmt = $this->pdo->prepare("SELECT ti.item_name, COALESCE(SUM(ti.item_price * ti.quantity), 0) as product_sales FROM transaction_items ti JOIN transactions t ON ti.transaction_id = t.id WHERE YEAR(t.transaction_date) = ? AND MONTH(t.transaction_date) = ? AND t.is_deleted = 0 GROUP BY ti.item_name ORDER BY product_sales DESC");
        $stmt->execute([$year, $month]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getDailyPerformanceForMonth($year, $month) {
        $stmt = $this->pdo->prepare("SELECT DATE(transaction_date) as sale_date, COALESCE(SUM(total_amount), 0) as sales, COUNT(DISTINCT id) as transactions FROM transactions WHERE YEAR(transaction_date) = ? AND MONTH(transaction_date) = ? AND is_deleted = 0 GROUP BY DATE(transaction_date)");
        $stmt->execute([$year, $month]);
        $salesByDate = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) { 
            $salesByDate[$row['sale_date']] = $row; 
        }
        $performance = [];
        // cal_days_in_month()を使わずに日数を取得
        $daysInMonth = date('t', strtotime("{$year}-{$month}-01"));
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%d-%02d-%02d', $year, $month, $day);
            $performance[] = $salesByDate[$date] ?? ['sale_date' => $date, 'sales' => 0, 'transactions' => 0];
        }
        return $performance;
    }
    
    private function getMonthlyPerformanceForYear($year) {
        $stmt = $this->pdo->prepare("SELECT MONTH(transaction_date) as month_num, COALESCE(SUM(total_amount), 0) as sales, COUNT(DISTINCT id) as transactions FROM transactions WHERE YEAR(transaction_date) = ? AND is_deleted = 0 GROUP BY MONTH(transaction_date)");
        $stmt->execute([$year]);
        $salesByMonth = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $salesByMonth[$row['month_num']] = $row; }
        $performance = [];
        for ($m = 1; $m <= 12; $m++) {
            $performance[] = $salesByMonth[$m] ?? ['month_num' => $m, 'sales' => 0, 'transactions' => 0];
        }
        return $performance;
    }
    
    private function getYearlyPerformance() {
        $stmt = $this->pdo->query("SELECT YEAR(transaction_date) as year_num, COALESCE(SUM(total_amount), 0) as sales, COUNT(DISTINCT id) as transactions FROM transactions WHERE YEAR(transaction_date) >= YEAR(CURDATE()) - 4 AND is_deleted = 0 GROUP BY YEAR(transaction_date) ORDER BY year_num ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getTotalSalesForPeriod($startDate, $endDate) {
        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT t.id) as transaction_count, COALESCE(SUM(ti.item_price * ti.quantity), 0) as total_sales, COALESCE(SUM(t.total_commission_amount), 0) as total_commission, COALESCE(AVG(t.total_amount), 0) as avg_transaction FROM transactions t LEFT JOIN transaction_items ti ON t.id = ti.transaction_id WHERE DATE(t.transaction_date) BETWEEN ? AND ? AND t.is_deleted = 0");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getStaffSalesForPeriod($startDate, $endDate) {
        $stmt = $this->pdo->prepare("
            SELECT
                u.username AS staff_name,
                SUM(ti.item_price * ti.quantity) AS staff_total_sales,
                SUM(
                    CASE ti.item_commission_type
                        WHEN 'percentage' THEN (ti.item_price * ti.quantity) * (ti.item_commission_rate / 100)
                        WHEN 'fixed_amount' THEN ti.item_fixed_commission_amount * ti.quantity
                        ELSE 0
                    END
                ) AS staff_commission
            FROM
                transaction_items ti
            JOIN
                transactions t ON ti.transaction_id = t.id
            JOIN
                users u ON ti.staff_id = u.id
            WHERE
                DATE(t.transaction_date) BETWEEN ? AND ?
                AND ti.staff_id IS NOT NULL
                AND t.is_deleted = 0
            GROUP BY
                u.username
            ORDER BY
                staff_total_sales DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProductSalesForPeriod($startDate, $endDate) {
        $stmt = $this->pdo->prepare("SELECT ti.item_name, SUM(ti.quantity) as total_quantity, SUM(ti.item_price * ti.quantity) as product_sales, SUM(t.total_commission_amount) as product_commission, AVG(ti.item_price) as avg_price FROM transaction_items ti JOIN transactions t ON ti.transaction_id = t.id WHERE DATE(t.transaction_date) BETWEEN ? AND ? AND t.is_deleted = 0 GROUP BY ti.item_name ORDER BY product_sales DESC LIMIT 10");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getDailySalesForPeriod($startDate, $endDate) {
        $stmt = $this->pdo->prepare("SELECT DATE(t.transaction_date) as sale_date, COUNT(DISTINCT t.id) as daily_transaction_count, COALESCE(SUM(t.total_amount), 0) as daily_sales, COALESCE(SUM(t.total_commission_amount), 0) as daily_commission FROM transactions t WHERE DATE(t.transaction_date) BETWEEN ? AND ? AND is_deleted = 0 GROUP BY DATE(t.transaction_date) ORDER BY sale_date DESC");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getStaffDetailedSalesForPeriod($startDate, $endDate) {
        $stmt = $this->pdo->prepare("
            SELECT
                u.username AS staff_name,
                ti.item_name,
                ti.quantity,
                ti.item_price,
                (ti.item_price * ti.quantity) AS item_total_price,
                CASE ti.item_commission_type
                    WHEN 'percentage' THEN (ti.item_price * ti.quantity) * (ti.item_commission_rate / 100)
                    WHEN 'fixed_amount' THEN ti.item_fixed_commission_amount * ti.quantity
                    ELSE 0
                END AS item_commission
            FROM
                transaction_items ti
            JOIN
                transactions t ON ti.transaction_id = t.id
            LEFT JOIN
                users u ON ti.staff_id = u.id
            WHERE
                DATE(t.transaction_date) BETWEEN ? AND ?
                AND ti.staff_id IS NOT NULL
                AND t.is_deleted = 0
            ORDER BY
                staff_name ASC, item_total_price DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}