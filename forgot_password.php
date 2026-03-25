<?php
session_start();
require_once __DIR__ . '/include/Config.php';
$_siteTitle = Config::getInstance()->get('site_title', '测试网站');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>找回密码 - <?php echo htmlspecialchars($_siteTitle); ?></title>
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

        /* 图形验证码区域 */
        .captcha-group { display: flex; gap: 10px; align-items: center; }
        .captcha-group .input-wrapper { flex: 1 1 0; margin-bottom: 0; min-width: 0; }
        .captcha-img-wrap { flex-shrink: 0; display: flex; align-items: center; gap: 6px; }
        .captcha-img {
            height: 44px; width: auto; border-radius: 8px;
            border: 1.5px solid rgba(155,140,255,.35); cursor: pointer;
            display: block; background: #f6f4ff; transition: opacity .2s;
        }
        .captcha-img:hover { opacity: .85; }
        .captcha-refresh {
            background: none; border: none; padding: 0; cursor: pointer;
            font-size: 18px; line-height: 1; color: #9b8cff; transition: transform .3s;
        }
        .captcha-refresh:hover { transform: rotate(180deg); color: #ff4db1; }
        body.dark-mode .captcha-img { border-color: rgba(176,160,255,.3); background: #2a2845; }
        .captcha-hint { font-size: .8rem; color: #aaa; margin-top: 4px; }
        #captcha_input { font-family: monospace; letter-spacing: 3px; font-size: 16px; text-align: center; }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2>找回密码</h2>
                <p class="auth-subtitle">支持用户名、手机号或绑定邮箱</p>
            </div>

            <?php if (isset($_SESSION['forgot_error'])): ?>
                <div class="message error">🚫 <?php echo $_SESSION['forgot_error']; unset($_SESSION['forgot_error']); ?></div>
            <?php endif; ?>

            <?php if (isset($_SESSION['forgot_success'])): ?>
                <div class="message success">✅ <?php echo $_SESSION['forgot_success']; unset($_SESSION['forgot_success']); ?></div>
            <?php endif; ?>

            <form action="handle_forgot_password.php" method="post" class="auth-form">

                <!-- 用户名或手机号 -->
                <div class="form-group">
                    <label for="user_identifier">账号标识 <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="text" id="user_identifier" name="user_identifier"
                               required placeholder="用户名 / 手机号 / 绑定邮箱"
                               value="<?php echo htmlspecialchars($_SESSION['forgot_input'] ?? ''); unset($_SESSION['forgot_input']); ?>">
                        <svg class="input-icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
                    </div>
                </div>

                <!-- 图形验证码 -->
                <div class="form-group">
                    <label for="captcha_input">图形验证码 <span class="required">*</span></label>
                    <div class="captcha-group">
                        <div class="input-wrapper">
                            <input type="text" id="captcha_input" name="captcha_input"
                                   maxlength="5" autocomplete="off" required
                                   placeholder="请输入图中字符">
                            <svg class="input-icon" viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                        </div>
                        <div class="captcha-img-wrap">
                            <img id="captchaImg"
                                 src="captcha.php?t=<?php echo time(); ?>"
                                 alt="图形验证码" class="captcha-img"
                                 title="看不清？点击刷新">
                            <button type="button" class="captcha-refresh"
                                    onclick="refreshCaptcha()" title="刷新验证码">↻</button>
                        </div>
                    </div>
                    <div class="captcha-hint">不区分大小写 · 看不清可点击图片刷新</div>
                </div>

                <button type="submit" class="btn btn-primary full-width">发送短信验证码</button>
                <p class="form-hint">想起来了？<a href="login.php" class="link-primary">返回登录</a></p>
            </form>
        </div>
    </div>
    <button id="themeToggle" class="theme-toggle auth-theme-toggle">🌙</button>
    <script src="theme-toggle.js"></script>
    <script>
    function refreshCaptcha() {
        const img = document.getElementById('captchaImg');
        img.src = 'captcha.php?t=' + Date.now();
        document.getElementById('captcha_input').value = '';
        const btn = document.querySelector('.captcha-refresh');
        btn.style.transform = 'rotate(360deg)';
        setTimeout(() => { btn.style.transform = ''; }, 400);
    }
    document.getElementById('captchaImg').addEventListener('click', refreshCaptcha);
    </script>
</body>
</html>