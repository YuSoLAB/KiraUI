<?php
session_start();
require_once __DIR__ . '/include/Config.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>重置密码 - <?php echo Config::getInstance()->get('site_title', '测试网站'); ?></title>
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
        body.dark-mode .auth-form input:focus + .input-icon { fill: #ff88cc; }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2>设置新密码</h2>
                <p class="auth-subtitle">请为了账号安全设置一个强密码</p>
            </div>

            <?php 
            $token = $_GET['token'] ?? '';
            $validToken = false;
            if (!empty($token)) {
                try {
                    require_once 'include/Db.php';
                    $db = Db::getInstance();
                    $stmt = $db->prepare("SELECT pr.*, u.email FROM password_reset pr 
                                        JOIN users u ON pr.user_id = u.id 
                                        WHERE pr.token = ? AND pr.expires_at > UTC_TIMESTAMP()");
                    $stmt->execute([$token]);
                    $resetData = $stmt->fetch();
                    if ($resetData) {
                        $validToken = true;
                    } else {
                        $_SESSION['reset_error'] = '链接无效或已过期，请重新申请';
                    }
                } catch (PDOException $e) {
                    $_SESSION['reset_error'] = '系统错误：' . $e->getMessage();
                }
            } else {
                $_SESSION['reset_error'] = '缺少必要的重置令牌';
            }
            ?>

            <?php if (isset($_SESSION['reset_error'])): ?>
                <div class="message error">🚫 <?php echo $_SESSION['reset_error']; unset($_SESSION['reset_error']); ?></div>
            <?php endif; ?>

            <?php if (isset($_SESSION['reset_success'])): ?>
                <div class="message success">✅ <?php echo $_SESSION['reset_success']; unset($_SESSION['reset_success']); ?></div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="login.php" class="btn btn-primary">返回登录</a>
                </div>
            <?php elseif ($validToken): ?>
                <form action="handle_reset_password.php" method="post" class="auth-form">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="form-group">
                        <label for="new_password">新密码 <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <input type="password" id="new_password" name="new_password" minlength="6" required placeholder="至少6位">
                            <svg class="input-icon" viewBox="0 0 24 24"><path d="M12.65 10C11.83 7.67 9.61 6 7 6c-3.31 0-6 2.69-6 6s2.69 6 6 6c2.61 0 4.83-1.67 5.65-4H17v4h4v-4h2v-4H12.65zM7 14c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2z"/></svg>
                        </div>
                        <small>建议使用字母、数字和符号的组合</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">确认新密码 <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" minlength="6" required placeholder="请再次输入">
                            <svg class="input-icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary full-width">重置密码</button>
                </form>
            <?php else: ?>
                <p class="form-hint" style="margin-top: 20px;">
                    <a href="forgot_password.php" class="btn btn-secondary full-width">重新获取链接</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
    <button id="themeToggle" class="theme-toggle auth-theme-toggle">🌙</button>
    <script src="theme-toggle.js"></script>
</body>
</html>