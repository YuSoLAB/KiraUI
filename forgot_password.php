<?php
session_start();
require_once __DIR__ . '/include/Config.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>忘记密码 - <?php echo Config::getInstance()->get('site_title', '测试网站'); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2>找回密码</h2>
                <p class="auth-subtitle">请输入您的用户名或注册邮箱</p>
            </div>
            <?php if (isset($_SESSION['forgot_error'])): ?>
                <div class="message error"><?php echo $_SESSION['forgot_error']; unset($_SESSION['forgot_error']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['forgot_success'])): ?>
                <div class="message"><?php echo $_SESSION['forgot_success']; unset($_SESSION['forgot_success']); ?></div>
            <?php endif; ?>
            <form action="handle_forgot_password.php" method="post" class="auth-form">
                <div class="form-group">
                    <label for="user_identifier">用户名或邮箱 <span class="required">*</span></label>
                    <input type="text" id="user_identifier" name="user_identifier" required>
                    <small>请输入您注册时使用的用户名或邮箱地址</small>
                </div>
                <button type="submit" class="btn-small btn-register full-width">发送重置链接</button>
                <p class="form-hint">记得密码了？<a href="login.php" class="link-primary">返回登录</a></p>
            </form>
        </div>
    </div>
    <button id="themeToggle" class="theme-toggle auth-theme-toggle">🌙</button>
    <script src="theme-toggle.js"></script>
</body>
</html>