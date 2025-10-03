<?php
// register.php - 統一スタイル版

// --- 共通設定の読み込み ---
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/functions.php';
require_once 'includes/messages.php';
require_once 'includes/styles.php';
require_once 'includes/navigation.php';

// from_settingsパラメータがあるかチェック (設定画面からの遷移か)
$from_settings = isset($_GET['from_settings']) && $_GET['from_settings'] === 'true';

// ログイン中のユーザーが管理者であり、かつ「設定」からのアクセスでなければ登録させない
if (isLoggedIn() && !($from_settings && $_SESSION['user_role'] === 'admin')) {
    header('Location: index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新規登録 - Oshi-rezi</title>
    <style>
        /* 登録ページ専用の追加スタイル */
        .register-page-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-form-container {
            background: var(--surface);
            padding: 40px;
            border-radius: 8px;
            border: 1px solid var(--border);
            width: 100%;
            max-width: 450px;
            box-shadow: 0 4px 12px var(--shadow);
        }
        
        .register-title {
            color: var(--primary);
            font-size: 2rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: var(--border);
            border-radius: 2px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .strength-weak {
            background: #dc2626;
            width: 33%;
        }

        .strength-medium {
            background: var(--warning);
            width: 66%;
        }

        .strength-strong {
            background: #15803d;
            width: 100%;
        }

        .password-requirements {
            margin-top: 12px;
            padding: 12px;
            background: var(--background);
            border-radius: 6px;
            border: 1px solid var(--border);
            font-size: 0.75rem;
            display: none;
        }

        .password-requirements.show {
            display: block;
        }

        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 6px;
            color: var(--text-muted);
            transition: color 0.2s ease;
        }

        .requirement:last-child {
            margin-bottom: 0;
        }

        .requirement.met {
            color: #15803d;
        }

        .requirement.unmet {
            color: #dc2626;
        }

        .requirement::before {
            content: "✓";
            margin-right: 8px;
            font-weight: bold;
        }

        .requirement.unmet::before {
            content: "✗";
        }

        .form-group input.error {
            border-color: #dc2626;
            background: #fef2f2;
        }

        .form-group small.error {
            color: #dc2626;
            font-weight: 500;
        }

        .form-group small.success {
            color: #15803d;
            font-weight: 500;
        }

        .btn:disabled {
            background: var(--secondary);
            cursor: not-allowed;
            opacity: 0.6;
        }

        .register-links {
            margin-top: 24px;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .register-links a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .register-links a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .register-form-container {
                padding: 30px 20px;
                margin: 10px;
            }

            .register-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-page-container">
        <div class="register-form-container">
            <h1 class="register-title">新規登録</h1>
            
            <?php showMessage(); ?>
            
            <form method="POST" action="create.php" id="registerForm">
                <input type="hidden" name="action" value="register_staff">
                <?php if ($from_settings): ?>
                    <input type="hidden" name="from_settings" value="true">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="username">ユーザー名 *</label>
                    <input type="text" id="username" name="username" required autocomplete="username" 
                           value="<?php echo h($_POST['username'] ?? ''); ?>" placeholder="半角英数字で入力">
                </div>
                
                <div class="form-group">
                    <label for="password">パスワード *</label>
                    <input type="password" id="password" name="password" required autocomplete="new-password" 
                           placeholder="6文字以上で入力">
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strengthBar"></div>
                    </div>
                    <div class="password-requirements" id="passwordRequirements">
                        <div class="requirement" id="lengthReq">6文字以上</div>
                        <div class="requirement" id="letterReq">英字を含む</div>
                        <div class="requirement" id="numberReq">数字を含む</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">パスワード（確認） *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           autocomplete="new-password" placeholder="上記と同じパスワードを入力">
                    <small id="passwordMatch" style="display: none;"></small>
                </div>
                
                <button type="submit" class="btn" id="submitBtn" style="width: 100%; margin-top: 20px;">
                    <?php echo $from_settings ? 'スタッフを登録' : 'アカウントを登録'; ?>
                </button>
            </form>
            
            <?php if (!$from_settings): ?>
                <div class="register-links">
                    <p>既にアカウントをお持ちですか？ 
                       <a href="login.php">ログインはこちら</a>
                    </p>
                </div>
            <?php else: ?>
                <div class="register-links">
                    <p><a href="select.php?tab=staff_management">← スタッフ管理に戻る</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const strengthBar = document.getElementById('strengthBar');
            const passwordRequirements = document.getElementById('passwordRequirements');
            const submitBtn = document.getElementById('submitBtn');
            const passwordMatch = document.getElementById('passwordMatch');

            // パスワード強度チェック
            function checkPasswordStrength(password) {
                let strength = 0;
                const requirements = {
                    length: password.length >= 6,
                    letter: /[a-zA-Z]/.test(password),
                    number: /\d/.test(password)
                };

                // 要件の表示更新
                document.getElementById('lengthReq').className = 
                    'requirement ' + (requirements.length ? 'met' : 'unmet');
                document.getElementById('letterReq').className = 
                    'requirement ' + (requirements.letter ? 'met' : 'unmet');
                document.getElementById('numberReq').className = 
                    'requirement ' + (requirements.number ? 'met' : 'unmet');

                // 強度計算
                if (requirements.length) strength++;
                if (requirements.letter) strength++;
                if (requirements.number) strength++;

                // 強度バーの更新
                strengthBar.className = 'password-strength-bar';
                if (strength === 1) strengthBar.classList.add('strength-weak');
                else if (strength === 2) strengthBar.classList.add('strength-medium');
                else if (strength === 3) strengthBar.classList.add('strength-strong');

                return strength;
            }

            // パスワードマッチチェック
            function checkPasswordMatch() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (confirmPassword.length === 0) {
                    passwordMatch.style.display = 'none';
                    confirmPasswordInput.classList.remove('error');
                    return true;
                }

                if (password === confirmPassword) {
                    passwordMatch.textContent = '✓ パスワードが一致しています';
                    passwordMatch.className = 'success';
                    passwordMatch.style.display = 'block';
                    confirmPasswordInput.classList.remove('error');
                    return true;
                } else {
                    passwordMatch.textContent = '✗ パスワードが一致しません';
                    passwordMatch.className = 'error';
                    passwordMatch.style.display = 'block';
                    confirmPasswordInput.classList.add('error');
                    return false;
                }
            }

            // フォームバリデーション
            function validateForm() {
                const username = usernameInput.value.trim();
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                let isValid = true;

                // ユーザー名チェック
                if (username.length < 3) {
                    usernameInput.classList.add('error');
                    isValid = false;
                } else {
                    usernameInput.classList.remove('error');
                }

                // パスワード強度チェック
                const strength = checkPasswordStrength(password);
                if (strength < 2) {
                    passwordInput.classList.add('error');
                    isValid = false;
                } else {
                    passwordInput.classList.remove('error');
                }

                // パスワードマッチチェック
                if (!checkPasswordMatch()) {
                    isValid = false;
                }

                // 送信ボタンの状態更新
                submitBtn.disabled = !isValid;
                
                return isValid;
            }

            // イベントリスナー
            usernameInput.addEventListener('input', function() {
                // ユーザー名の入力制限（英数字のみ）
                this.value = this.value.replace(/[^a-zA-Z0-9]/g, '');
                validateForm();
            });

            passwordInput.addEventListener('input', function() {
                if (this.value.length > 0) {
                    passwordRequirements.classList.add('show');
                } else {
                    passwordRequirements.classList.remove('show');
                }
                validateForm();
            });

            passwordInput.addEventListener('focus', function() {
                if (this.value.length > 0) {
                    passwordRequirements.classList.add('show');
                }
            });

            passwordInput.addEventListener('blur', function() {
                setTimeout(() => {
                    passwordRequirements.classList.remove('show');
                }, 200);
            });

            confirmPasswordInput.addEventListener('input', validateForm);

            // フォーム送信時の最終チェック
            form.addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                    alert('入力内容に問題があります。赤色になっている項目を確認してください。');
                    return false;
                }

                // 送信中の表示
                submitBtn.disabled = true;
                submitBtn.textContent = '登録中...';
            });

            // 初期状態でのフォームバリデーション
            validateForm();
        });
    </script>
</body>
</html>