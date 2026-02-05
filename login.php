<?php session_start(); ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户登录</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .input-wrapper { position: relative; margin-bottom: 5px; }
        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            fill: #9b8cff;
            transition: all 0.3s ease;
            pointer-events: none;
        }
        .auth-form input { padding-left: 44px !important; }
        .auth-form input:focus + .input-icon { fill: #ff4db1; transform: translateY(-50%) scale(1.1); }
        body.dark-mode .input-icon { fill: #b0a0ff; }
        body.dark-mode .auth-form input:focus + .input-icon { fill: #ff66b8; }
        .auth-card { animation: floatIn 0.6s cubic-bezier(0.18, 0.89, 0.32, 1.28); }
        @keyframes floatIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo-area" style="font-size: 3rem; margin-bottom: 10px;">🪐</div>
                <h2>欢迎回来</h2>
                <p class="auth-subtitle">登录您的账号以探索更多精彩</p>
            </div>

            <?php if (isset($_SESSION['register_success'])): ?>
                <div class="message success">
                    <span>✨</span> <?php echo $_SESSION['register_success']; unset($_SESSION['register_success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['login_error'])): ?>
                <div class="message error">
                    <span>⚠️</span> <?php echo $_SESSION['login_error']; unset($_SESSION['login_error']); ?>
                </div>
            <?php endif; ?>

            <form action="handle_login.php" method="post" class="auth-form">
                <div class="form-group">
                    <label for="login_id">账号</label>
                    <div class="input-wrapper">
                        <input type="text" id="login_id" name="login_id" placeholder="用户名或邮箱" required>
                        <svg class="input-icon" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">密码</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" placeholder="请输入密码" required>
                        <svg class="input-icon" viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                    </div>
                    <div class="form-options">
                        <div class="remember-me">
                            <input type="checkbox" id="remember" name="remember_me" value="on">
                            <label for="remember">记住我</label>
                        </div>
                        <div class="form-hint-right">
                            <a href="forgot_password.php" class="link">忘记密码?</a>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary full-width" style="margin-top: 10px;">立即登录</button>
                <p class="form-hint">还没有账号？<a href="register.php" class="link-primary">免费注册</a></p>
            </form>
        </div>
    </div>
    <button id="themeToggle" class="theme-toggle auth-theme-toggle">🌙</button>
    <script src="theme-toggle.js"></script>
</body>
</html>