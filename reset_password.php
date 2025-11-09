<?php
session_start();
require_once __DIR__ . '/include/Config.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>重置密码 - <?php echo Config::getInstance()->get('site_title', '测试网站'); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2>重置密码</h2>
                <p class="auth-subtitle">请设置新密码</p>
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
                                        WHERE pr.token = ? AND pr.expires_at > NOW()");
                    $stmt->execute([$token]);
                    $resetData = $stmt->fetch();
                    if ($resetData) {
                        $validToken = true;
                    } else {
                        $_SESSION['reset_error'] = '无效的重置链接或链接已过期';
                    }
                } catch (PDOException $e) {
                    $_SESSION['reset_error'] = '数据库错误：' . $e->getMessage();
                }
            } else {
                $_SESSION['reset_error'] = '请提供重置令牌';
            }
            ?>
            <?php if (isset($_SESSION['reset_error'])): ?>
                <div class="message error"><?php echo $_SESSION['reset_error']; unset($_SESSION['reset_error']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['reset_success'])): ?>
                <div class="message"><?php echo $_SESSION['reset_success']; unset($_SESSION['reset_success']); ?></div>
                <p class="form-hint"><a href="login.php" class="link-primary">返回登录</a></p>
            <?php elseif ($validToken): ?>
                <form action="handle_reset_password.php" method="post" class="auth-form">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <div class="form-group">
                        <label for="new_password">新密码 <span class="required">*</span></label>
                        <input type="password" id="new_password" name="new_password" 
                               minlength="6" 
                               title="密码长度至少6位" 
                               required>
                        <small>密码长度至少6位</small>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">确认新密码 <span class="required">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               minlength="6" 
                               title="请再次输入密码" 
                               required>
                    </div>
                    <button type="submit" class="btn-small btn-register full-width">重置密码</button>
                </form>
            <?php else: ?>
                <p class="form-hint"><a href="forgot_password.php" class="link-primary">重新获取重置链接</a></p>
            <?php endif; ?>
        </div>
    </div>
    <button id="themeToggle" class="theme-toggle auth-theme-toggle">🌙</button>
    <script src="theme-toggle.js"></script>
</body>
</html>