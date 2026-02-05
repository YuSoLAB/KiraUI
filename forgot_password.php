<?php
session_start();
require_once __DIR__ . '/include/Config.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>找回密码 - <?php echo Config::getInstance()->get('site_title', '测试网站'); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .input-wrapper { position: relative; margin-bottom: 5px; }
        .input-icon {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            width: 20px; height: 20px; fill: #9b8cff; transition: 0.3s; pointer-events: none;
        }
        .auth-form input { padding-left: 44px !important; }
        .auth-form input:focus + .input-icon { fill: #ff4db1; }
        body.dark-mode .input-icon { fill: #b0a0ff; }
        body.dark-mode .auth-form input:focus + .input-icon { fill: #ff66b8; }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2>找回密码</h2>
                <p class="auth-subtitle">不要担心，我们会帮您找回账号</p>
            </div>

            <?php if (isset($_SESSION['forgot_error'])): ?>
                <div class="message error">🚫 <?php echo $_SESSION['forgot_error']; unset($_SESSION['forgot_error']); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['forgot_success'])): ?>
                <div class="message success">✅ <?php echo $_SESSION['forgot_success']; unset($_SESSION['forgot_success']); ?></div>
            <?php endif; ?>

            <form action="handle_forgot_password.php" method="post" class="auth-form">
                <div class="form-group">
                    <label for="user_identifier">用户名或邮箱 <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="text" id="user_identifier" name="user_identifier" required placeholder="请输入注册时的信息">
                        <svg class="input-icon" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary full-width">发送重置链接</button>
                <p class="form-hint">想起来了？<a href="login.php" class="link-primary">返回登录</a></p>
            </form>
        </div>
    </div>
    <button id="themeToggle" class="theme-toggle auth-theme-toggle">🌙</button>
    <script src="theme-toggle.js"></script>
</body>
</html>