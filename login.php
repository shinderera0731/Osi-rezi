<?php
ob_start(); // 出力バッファを開始

// 共通設定ファイルを読み込み
require_once 'config/database.php';
require_once 'config/auth.php'; // 👈 追加
require_once 'config/functions.php'; // 👈 追加
require_once 'includes/styles.php';
require_once 'includes/messages.php'; // 👈 追加

// 既にログインしている場合はホームにリダイレクト
if (isLoggedIn()) {
    ob_clean(); // バッファをクリア
    header('Location: index.php');
    exit();
}

$error_message = '';

// ログインフォームが送信された場合
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'ユーザー名とパスワードを入力してください。';
    } else {
        try {
            if (authenticateUser($pdo, $username, $password)) {
                setSuccessMessage('ログインしました。');
                header('Location: index.php'); // ログイン後、ホームにリダイレクト
                exit();
            } else {
                $error_message = 'ユーザー名またはパスワードが正しくありません。';
            }
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            $error_message = 'データベースエラーが発生しました。';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン - Oshi-rezi</title>
    <style>
        /* === 統一デザインCSS === */
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --secondary: #64748b;
            --success: #059669;
            --warning: #d97706;
            --danger: #dc2626;
            --background: #f8fafc;
            --surface: #ffffff;
            --border: #e2e8f0;
            --text: #1e293b;
            --text-muted: #64748b;
            --shadow: rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        /* ログインページ専用スタイル */
        .login-page {
            background: var(--background);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }

        .login-container {
            background: var(--surface);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            text-align: center;
            border: 1px solid var(--border);
            animation: slideUp 0.6s ease-out;
        }

        .login-container h1 {
            color: var(--text);
            margin-bottom: 30px;
            font-size: 2rem;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
            transition: transform 0.2s ease;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text);
            font-size: 0.875rem;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--background);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--surface);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary) 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            position: relative;
            overflow: hidden;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .login-error {
            background: #fef2f2;
            color: var(--danger);
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #fecaca;
            font-weight: 500;
        }

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
            color: var(--danger);
            border-color: #fecaca;
        }

        .alert.warning {
            background: #fffbeb;
            color: var(--warning);
            border-color: #fed7aa;
        }

        .register-link {
            margin-top: 25px;
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .register-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .test-accounts {
            background: var(--background);
            padding: 20px;
            border-radius: 8px;
            margin-top: 25px;
            text-align: left;
            border: 1px solid var(--border);
        }

        .test-accounts p {
            margin: 8px 0;
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .test-accounts strong {
            color: var(--text);
            font-family: monospace;
            background: var(--border);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.8125rem;
        }

        /* アニメーション */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }

        /* レスポンシブ対応 */
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
                margin: 10px;
            }

            .login-container h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <h1>🔑 ログイン</h1>
        <?php if (!empty($error_message)): ?>
            <div class="login-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php showMessage(); ?>
        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">ユーザー名:</label>
                <input type="text" id="username" name="username" required autocomplete="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="password">パスワード:</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn">ログイン</button>
        </form>
        <div class="register-link">
            アカウントをお持ちでないですか？ <a href="register.php">新規登録はこちら</a>
        </div>
        <div class="test-accounts">
            <p><strong>🔐 テスト用アカウント:</strong></p>
            <p>管理者: <strong>admin</strong> / <strong>password</strong></p>
            <p>スタッフ: <strong>staff</strong> / <strong>password</strong></p>
        </div>
    </div>

    <script>
        // ページロード時のアニメーション
        document.addEventListener('DOMContentLoaded', function() {
            // ボタンのクリックエフェクト
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const ripple = document.createElement('div');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.position = 'absolute';
                    ripple.style.borderRadius = '50%';
                    ripple.style.background = 'rgba(255, 255, 255, 0.6)';
                    ripple.style.transform = 'scale(0)';
                    ripple.style.animation = 'ripple 0.6s linear';
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';
                    ripple.style.width = size + 'px';
                    ripple.style.height = size + 'px';
                    
                    this.style.position = 'relative';
                    this.style.overflow = 'hidden';
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });

            // フォーカス時のアニメーション
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'translateY(-2px)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'translateY(0)';
                });
            });
        });

        // リップルアニメーション
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
