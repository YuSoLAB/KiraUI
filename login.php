<?php session_start(); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>用户登录</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2>用户登录</h2>
                <p class="auth-subtitle">欢迎回来，请登录您的账号</p>
            </div>
            <?php if (isset($_SESSION['register_success'])): ?>
                <div class="message"><?php echo $_SESSION['register_success']; unset($_SESSION['register_success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['login_error'])): ?>
                <div class="message error"><?php echo $_SESSION['login_error']; unset($_SESSION['login_error']); ?></div>
            <?php endif; ?>
            <form action="handle_login.php" method="post" class="auth-form">
                <div class="form-group">
                    <label for="login_id">用户名或邮箱 <span class="required">*</span></label>
                    <input type="text" id="login_id" name="login_id" required>
                    <small>请输入用户名或注册邮箱</small>
                </div>
                <div class="form-group">
                    <label for="password">密码 <span class="required">*</span></label>
                    <input type="password" id="password" name="password" required>
                    <div class="form-hint-right">
                        <a href="forgot_password.php" class="link">忘记密码?</a>
                    </div>
                </div>
                <button type="submit" class="btn-small btn-register full-width">登录</button>
                <p class="form-hint">还没有账号？<a href="register.php" class="link-primary">立即注册</a></p>
            </form>
        </div>
    </div>
    <button id="themeToggle" class="theme-toggle auth-theme-toggle">🌙</button>
    <script src="theme-toggle.js"></script>
</body>
</html>