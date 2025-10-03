<?php
// modules/api/ApiKeyService.php

/**
 * APIキーの管理、生成、検証を担当するクラス
 */
class ApiKeyService {
    private $pdo;

    /**
     * コンストラクタ
     * @param PDO $pdo データベース接続オブジェクト
     */
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * 新しいAPIキーを生成し、データベースに保存
     * @param int $userId ユーザーID
     * @return string 生成されたAPIキー
     */
    public function generateNewApiKey($userId) {
        $apiKey = bin2hex(random_bytes(32)); // 安全なランダムトークンを生成
        
        try {
            $stmt = $this->pdo->prepare("INSERT INTO api_keys (user_id, api_key) VALUES (?, ?)");
            $stmt->execute([$userId, $apiKey]);
            return $apiKey;
        } catch (PDOException $e) {
            error_log("Failed to generate API key: " . $e->getMessage());
            throw new Exception("APIキーの生成に失敗しました。");
        }
    }

    /**
     * APIキーを検証
     * @param string $apiKey 検証するAPIキー
     * @return array ['valid' => bool, 'reason' => string]
     */
    public function validateApiKey($apiKey) {
        if (empty($apiKey)) {
            return ['valid' => false, 'reason' => 'APIキーが提供されていません。'];
        }
        
        $stmt = $this->pdo->prepare("SELECT status FROM api_keys WHERE api_key = ?");
        $stmt->execute([$apiKey]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return ['valid' => false, 'reason' => 'APIキーが無効です。'];
        }
        
        if ($result['status'] === 'inactive') {
            return ['valid' => false, 'reason' => 'このAPIキーは無効化されています。'];
        }
        
        return ['valid' => true, 'reason' => 'APIキーは有効です。'];
    }

    /**
     * すべてのAPIキーを取得
     * @return array APIキーのリスト
     */
    public function getAllApiKeys() {
        try {
            $stmt = $this->pdo->query("SELECT ak.*, u.username FROM api_keys ak JOIN users u ON ak.user_id = u.id ORDER BY ak.created_at DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Failed to get API keys: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * すべてのユーザーを取得
     * @return array ユーザーのリスト
     */
    public function getAllUsers() {
        try {
            $stmt = $this->pdo->query("SELECT id, username, role FROM users ORDER BY username ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Failed to get users: " . $e->getMessage());
            return [];
        }
    }

    /**
     * APIキーを無効化
     * @param int $id APIキーのID
     */
    public function deactivateApiKey($id) {
        $stmt = $this->pdo->prepare("UPDATE api_keys SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$id]);
    }
    
    /**
     * APIキーを再有効化
     * @param int $id APIキーのID
     */
    public function activateApiKey($id) {
        $stmt = $this->pdo->prepare("UPDATE api_keys SET status = 'active' WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * APIキーを削除
     * @param int $id APIキーのID
     */
    public function deleteApiKey($id) {
        $stmt = $this->pdo->prepare("DELETE FROM api_keys WHERE id = ?");
        $stmt->execute([$id]);
    }
}
