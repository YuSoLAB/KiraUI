<?php
session_start();
require_once __DIR__ . '/include/Config.php';

$_siteTitle  = Config::getInstance()->get('site_title', '测试网站');
$_method     = $_SESSION['reset_method'] ?? 'sms';   // 'email' | 'sms'
$_masked     = $_SESSION['reset_contact_masked'] ?? null;

// session 有效性：email 通道看 reset_email，sms 通道看 reset_phone
$_hasSession = !empty($_SESSION['reset_user_id']) && (
    ($_method === 'email' && !empty($_SESSION['reset_email'])) ||
    ($_method === 'sms'   && !empty($_SESSION['reset_phone']))
);

// 界面文案根据通道切换
$_tipIcon    = $_method === 'email' ? '📧' : '📱';
$_tipLabel   = $_method === 'email' ? '邮箱' : '手机';
$_expire     = $_method === 'email' ? '10 分钟' : '5 分钟';
$_codeLabel  = $_method === 'email' ? '邮箱验证码' : '短信验证码';
$_noCodeHint = $_method === 'email' ? '没收到邮件？' : '没收到短信？';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>重置密码 - <?php echo htmlspecialchars($_siteTitle); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .input-wrapper { position: relative; margin-bottom: 5px; }
        .input-icon {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            width: 20px; height: 20px; fill: #9b8cff; transition: 0.3s; pointer-events: none;
        }
        .auth-form input:not([type="checkbox"]) { padding-left: 44px !important; }
        .auth-form input:focus + .input-icon { fill: #ff4db1; }
        body.dark-mode .input-icon { fill: #b0a0ff; }
        body.dark-mode .auth-form input:focus + .input-icon { fill: #ff88cc; }

        #sms_code { font-family: monospace; letter-spacing: 3px; font-size: 16px; text-align: center; }

        .sms-sent-tip {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            background: rgba(108,93,251,.07);
            border: 1px solid rgba(108,93,251,.18);
            border-radius: 10px;
            font-size: .88rem;
            color: var(--text-secondary, #555);
            margin-bottom: 18px;
        }
        .sms-sent-tip .tip-icon { font-size: 1.1rem; flex-shrink: 0; }
        .sms-sent-tip strong { color: var(--text, #222); font-weight: 700; }
        body.dark-mode .sms-sent-tip { background: rgba(108,93,251,.12); border-color: rgba(176,160,255,.2); color: #ccc; }
        body.dark-mode .sms-sent-tip strong { color: #e8e4ff; }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2>重置密码</h2>
                <p class="auth-subtitle">输入<?php echo $_tipLabel; ?>验证码并设置新密码</p>
            </div>

            <?php if (isset($_SESSION['reset_error'])): ?>
                <div class="message error">🚫 <?php echo $_SESSION['reset_error']; unset($_SESSION['reset_error']); ?></div>
            <?php endif; ?>

            <?php if (!$_hasSession): ?>
                <div class="message error">🚫 链接已失效，请重新申请</div>
                <p class="form-hint" style="margin-top:16px">
                    <a href="forgot_password.php" class="btn btn-secondary full-width">重新获取验证码</a>
                </p>

            <?php else: ?>
                <?php if ($_masked): ?>
                <div class="sms-sent-tip">
                    <span class="tip-icon"><?php echo $_tipIcon; ?></span>
                    <span>验证码已发送至 <strong><?php echo htmlspecialchars($_masked); ?></strong>，<?php echo $_expire; ?>内有效</span>
                </div>
                <?php endif; ?>

                <form action="handle_reset_password.php" method="post" class="auth-form">

                    <div class="form-group">
                        <label for="sms_code"><?php echo htmlspecialchars($_codeLabel); ?> <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <input type="text" id="sms_code" name="sms_code"
                                   maxlength="6" required placeholder="请输入 6 位验证码">
                            <svg class="input-icon" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new_password">新密码 <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <input type="password" id="new_password" name="new_password"
                                   minlength="6" required placeholder="至少 6 位字符">
                            <svg class="input-icon" viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                        </div>
                        <small>建议使用字母、数字和符号的组合</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">确认新密码 <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password"
                                   minlength="6" required placeholder="请再次输入新密码">
                            <svg class="input-icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary full-width">重置密码</button>
                    <p class="form-hint">
                        <?php echo $_noCodeHint; ?><a href="forgot_password.php" class="link-primary">重新发送</a>
                    </p>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <button id="themeToggle" class="theme-toggle auth-theme-toggle">🌙</button>
    <script src="theme-toggle.js"></script>
</body>
</html>