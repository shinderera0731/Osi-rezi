<?php
// includes/styles.php - 軽量シンプルデザインシステム（強化版）
/**
 * 軽量で高速なCSSスタイル
 * アニメーション・グラデーション・重いエフェクトを排除
 * 操作性とパフォーマンスを重視
 * 新機能: ウェルカム画面、メニューグリッド、POS対応
 */
?>
<style>
/* === リセット & ベース === */
/* styles.phpに追加 */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

body {
    background-color: #f8fafc;
    color: #1e293b;
    min-height: 100vh;
    padding: 20px;
    line-height: 1.6;
}

/* === カラーパレット（CSS変数） === */
:root {
    --primary: #2563eb;
    --primary-hover: #1d4ed8;
    --secondary: #64748b;
    --success: #059669; /* 未使用 */
    --success-hover: #047857; /* 未使用 */
    --warning: #d97706;
    --danger: #dc2626; /* 未使用 */
    --danger-hover: #b91c1c; /* 未使用 */
    --background: #f8fafc;
    --surface: #ffffff;
    --border: #e2e8f0;
    --text: #1e293b;
    --text-muted: #64748b;
    --shadow: rgba(0, 0, 0, 0.05);
}

/* === レイアウト === */
.container {
    max-width: 1200px;
    margin: 0 auto;
    background: var(--surface);
    border-radius: 8px;
    box-shadow: 0 1px 3px var(--shadow);
    overflow: hidden;
    border: 1px solid var(--border);
}

.header {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    color: var(--primary);
    padding: 24px 32px;
    border-bottom: 2px solid var(--primary);
    box-shadow: 0 2px 4px rgba(37, 99, 235, 0.1);
}

.header h1 {
    font-size: 1.875rem;
    font-weight: 600;
    margin: 0;
}

.header p {
    font-size: 0.875rem;
    opacity: 0.9;
    margin-top: 4px;
}

.content {
    padding: 32px;
}

/* === カード === */
.card {
    background: var(--surface);
    padding: 24px;
    border-radius: 8px;
    border: 1px solid var(--border);
    margin-bottom: 24px;
}

.card h3 {
    color: var(--primary);
    margin-bottom: 16px;
    font-size: 1.25rem;
    font-weight: 600;
}

/* === ナビゲーション === */
.nav {
    background: var(--surface);
    padding: 12px 16px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.nav-left {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.nav-left a {
    padding: 8px 16px;
    color: var(--text);
    text-decoration: none;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    border: 1px solid transparent;
    transition: all 0.2s ease;
}

.nav-left a:hover {
    background-color: var(--background);
    border-color: var(--border);
}

.nav-left a.active {
    background-color: var(--primary);
    color: white;
}
.simple-nav {
    padding: 8px 16px;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
}

.home-only-btn {
    padding: 8px 16px;
    color: var(--primary);
    text-decoration: none;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 600;
    border: 1px solid var(--primary);
    transition: all 0.2s ease;
}

.home-only-btn:hover {
    background-color: var(--primary);
    color: white;
}
.tab-buttons {
    display: none !important;
}
/* レスポンシブ対応 */
@media (max-width: 768px) {
    .simple-nav {
        flex-direction: column;
        gap: 8px;
    }
    
    .simple-nav .nav-left {
        margin-bottom: 8px;
    }
}
/* === ボタン === */
.btn {
    display: inline-block;
    padding: 12px 20px;
    background: var(--primary);
    color: white;
    text-decoration: none;
    border: none;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    border: 1px solid transparent;
    transition: background-color 0.2s ease;
}

.btn:hover {
    background: var(--primary-hover);
}

.btn.success,
.btn.danger {
    background: var(--primary);
}

.btn.success:hover,
.btn.danger:hover {
    background: var(--primary-hover);
}

.btn.secondary {
    background: var(--secondary);
    color: white;
}

.btn-small {
    padding: 6px 12px;
    font-size: 0.75rem;
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* === フォーム === */
.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: var(--text);
    font-size: 0.875rem;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 0.875rem;
    background: var(--surface);
    transition: border-color 0.2s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

/* === テーブル === */
.table-container {
    overflow-x: auto;
    border: 1px solid var(--border);
    border-radius: 6px;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: var(--surface);
}

th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--border);
    font-size: 0.875rem;
}

th {
    background: var(--background);
    font-weight: 600;
    color: var(--text);
}

tr:hover {
    background: var(--background);
}

tr:last-child td {
    border-bottom: none;
}

/* === アラート === */
.alert {
    padding: 16px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-weight: 500;
    border: 1px solid;
}

.alert.success {
    background: #f0fdf4;
    color: #15803d;
    border-color: #bbf7d0;
}

.alert.error {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fecaca;
}

.alert.warning {
    background: #fffbeb;
    color: #d97706;
    border-color: #fed7aa;
}

/* === ステータスバッジ === */
.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-align: center;
    min-width: 60px;
}

.status-normal {
    background: #f0fdf4;
    color: #15803d;
}

.status-warning {
    background: #fffbeb;
    color: #d97706;
}

.status-low {
    background: #fef2f2;
    color: #dc2626;
}

/* === タブ === */
.tab-buttons {
    display: flex;
    background: var(--background);
    border: 1px solid var(--border);
    border-radius: 6px;
    margin-bottom: 24px;
    overflow: hidden;
}

.tab-button {
    flex: 1;
    padding: 12px 16px;
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text);
    border-right: 1px solid var(--border);
    transition: all 0.2s ease;
}

.tab-button:last-child {
    border-right: none;
}

.tab-button:hover {
    background: var(--surface);
}

.tab-button.active {
    background: var(--primary);
    color: white;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* === グリッドレイアウト === */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.stat-card {
    background: var(--surface);
    padding: 20px;
    border-radius: 6px;
    text-align: center;
    border: 1px solid var(--border);
    transition: all 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-number {
    font-size: 1.875rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 8px;
}

.stat-label {
    font-size: 0.875rem;
    color: var(--text-muted);
    font-weight: 500;
}

/* === モーダル === */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background: var(--surface);
    padding: 24px;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.close-button {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--text-muted);
}

/* === 追加: ウェルカムセクション === */
.welcome-section {
    text-align: center;
    margin-bottom: 40px;
}

.welcome-title {
    font-size: 2.5rem;
    color: var(--primary);
    margin-bottom: 12px;
    font-weight: 700;
}

.welcome-subtitle {
    font-size: 1.125rem;
    color: var(--text-muted);
    margin-bottom: 24px;
}

/* === 追加: メニューグリッド === */
.menu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.menu-item {
    background: var(--surface);
    padding: 24px;
    border-radius: 8px;
    text-decoration: none;
    text-align: center;
    border: 1px solid var(--border);
    color: var(--text);
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.menu-item:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 12px var(--shadow);
    transform: translateY(-2px);
}

.menu-icon {
    font-size: 2.5rem;
    margin-bottom: 12px;
    display: block;
}

.menu-title {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--primary);
}

.menu-description {
    font-size: 0.875rem;
    color: var(--text-muted);
    line-height: 1.5;
}

/* === 追加: 統計サマリー === */
.stats-summary {
    background: var(--background);
    padding: 24px;
    border-radius: 8px;
    border: 1px solid var(--border);
    margin-bottom: 24px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.stat-item {
    background: var(--surface);
    padding: 20px;
    border-radius: 6px;
    text-align: center;
    border: 1px solid var(--border);
    transition: all 0.2s ease;
}

.stat-item:hover {
    transform: translateY(-1px);
}

.text-warning .stat-number {
    color: var(--warning);
}

/* === POS専用レイアウト === */
.section-split {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.section-left {
    flex: 2;
    min-width: 300px;
}

.section-right {
    flex: 1;
    min-width: 300px;
    position: sticky;
    top: 20px;
    max-height: 80vh;
    overflow-y: auto;
}

.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 16px;
}

.product-item {
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 6px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    background: var(--surface);
}

.product-item:hover {
    border-color: var(--primary);
    box-shadow: 0 2px 8px var(--shadow);
}

.product-item:active {
    transform: scale(0.98);
}

/* === 追加: 検索・フィルタ === */
.search-filter {
    background: var(--background);
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 20px;
}

.search-row {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.search-input {
    flex: 1;
    min-width: 200px;
    padding: 8px 12px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 0.875rem;
    transition: border-color 0.2s ease;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
}

.category-filter {
    min-width: 120px;
    padding: 8px 12px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 0.875rem;
}

/* === 追加: ツールバー === */
.toolbar {
    background: var(--background);
    padding: 12px 16px;
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.toolbar-left {
    display: flex;
    gap: 12px;
    align-items: center;
}

.toolbar-right {
    display: flex;
    gap: 8px;
    align-items: center;
}

/* === ユーティリティ === */
.text-center { text-align: center; }
.text-right { text-align: right; }
.font-bold { font-weight: 600; }
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.p-4 { padding: 16px; }

/* === レスポンシブ === */
@media (max-width: 768px) {
    body {
        padding: 10px;
    }
    
    .container {
        margin: 0;
        border-radius: 6px;
    }
    
    .header {
        padding: 16px 20px;
    }
    
    .header h1 {
        font-size: 1.5rem;
    }
    
    .content {
        padding: 20px;
    }
    
    .nav {
        flex-direction: column;
        align-items: stretch;
    }
    
    .nav-left {
        justify-content: center;
        margin-bottom: 8px;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-summary {
        grid-template-columns: 1fr;
    }
    
    .tab-buttons {
        flex-direction: column;
    }
    
    .tab-button {
        border-right: none;
        border-bottom: 1px solid var(--border);
    }
    
    .tab-button:last-child {
        border-bottom: none;
    }
    
    .welcome-title {
        font-size: 2rem;
    }
    
    .menu-grid {
        grid-template-columns: 1fr;
    }
    
    .section-split {
        flex-direction: column;
    }
    
    .section-left,
    .section-right {
        min-width: unset;
        width: 100%;
    }
    
    .product-grid {
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 8px;
    }
    
    .search-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-input,
    .category-filter {
        min-width: unset;
        width: 100%;
    }
    
    .toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .toolbar-left,
    .toolbar-right {
        justify-content: center;
    }
}

/* === 印刷対応 === */
@media print {
    body {
        background: white;
        padding: 0;
    }
    
    .nav, .btn {
        display: none;
    }
    
    .container {
        box-shadow: none;
        border: none;
    }
}
/* カート関連 */
.cart-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
    transition: all 0.2s ease;
}

.cart-item:last-child {
    border-bottom: none;
}

.cart-item:hover {
    background: var(--background);
    border-radius: 6px;
    padding: 12px;
    margin: 0 -12px;
}

.cart-item-details {
    flex: 1;
}

.cart-item-staff {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid var(--border);
}

.cart-item-staff label {
    font-size: 0.75rem;
    color: var(--text-muted);
    display: block;
    margin-bottom: 4px;
    font-weight: 500;
}

.cart-item-staff select {
    width: 100%;
    padding: 6px 10px;
    font-size: 0.75rem;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: var(--surface);
    transition: border-color 0.2s ease;
}

.cart-item-staff select:focus {
    outline: none;
    border-color: var(--primary);
}

.cart-actions {
    margin-left: 10px;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

/* 在庫情報表示 */
.stock-info {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 4px;
    font-weight: 500;
}

.stock-warning {
    color: var(--warning);
    font-weight: 600;
}

.stock-low {
    color: var(--danger);
    font-weight: 600;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

/* コミッション関連フィールド */
.commission-field {
    display: none;
}

.commission-field.active {
    display: block;
}

/* 空のカート表示 */
.empty-cart {
    text-align: center;
    color: var(--text-muted);
    padding: 40px 20px;
    font-style: italic;
}

/* POS特有のレイアウト調整 */
.pos-layout {
    display: flex;
    gap: 20px;
    min-height: 70vh;
}

.pos-left {
    flex: 2;
    min-width: 400px;
}

.pos-right {
    flex: 1;
    min-width: 300px;
    position: sticky;
    top: 20px;
    max-height: 80vh;
    overflow-y: auto;
}

/* カート合計セクション */
.cart-total {
    border-top: 2px solid var(--primary);
    padding-top: 16px;
    margin-top: 16px;
}

.total-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.total-row.final {
    font-weight: 700;
    font-size: 1.125rem;
    color: var(--primary);
    border-top: 1px solid var(--border);
    padding-top: 8px;
}

/* 支払いボタン */
.payment-buttons {
    margin-top: 20px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.payment-btn {
    width: 100%;
    padding: 12px;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 6px;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}

.payment-btn.cash,
.payment-btn.clear {
    background: var(--primary);
    color: white;
}

.payment-btn.card {
    background: var(--primary);
    color: white;
}

/* POS専用レスポンシブ調整 */
@media (max-width: 768px) {
    .pos-layout {
        flex-direction: column;
    }
    
    .pos-left,
    .pos-right {
        min-width: unset;
        width: 100%;
    }
    
    .pos-right {
        position: static;
        max-height: none;
    }
    
    .product-grid {
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 8px;
    }
}
.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.header-left h1 {
    margin: 0;
}

.header-left p {
    margin: 4px 0 0 0;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-info {
    color: var(--primary); 
    font-size: 0.875rem;
    opacity: 0.9;
}

.header-btn {
    font-size: 0.75rem;
    padding: 8px 16px;
}

@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        gap: 16px;
        text-align: center;
    }
}
</style>
