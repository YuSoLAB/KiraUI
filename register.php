<?php
session_start();
// ── 注册开关检测 ─────────────────────────────────────────────
if (!defined('ROOT_DIR')) { define('ROOT_DIR', dirname(__FILE__)); }
$_regEnabled = true;
$_configFile = ROOT_DIR . '/include/Config.php';
if (file_exists($_configFile)) {
    require_once $_configFile;
    $_regEnabled = Config::getInstance()->get('registration_enabled', '1') !== '0';
}
if (!$_regEnabled) {
    $pageTitle = '注册已关闭';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .reg-closed-wrap {
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 2rem;
        }
        .reg-closed-card {
            background: var(--card-bg, #fff);
            border-radius: 18px;
            box-shadow: 0 8px 40px rgba(108,93,251,.13);
            padding: 3rem 2.5rem;
            max-width: 440px; width: 100%;
            text-align: center;
        }
        .reg-closed-icon { font-size: 3.5rem; margin-bottom: 1rem; display: block; }
        .reg-closed-title { font-size: 1.4rem; font-weight: 700; margin: 0 0 .6rem; color: var(--text, #1a1a2e); }
        .reg-closed-desc  { font-size: .92rem; color: var(--sub, #888); line-height: 1.7; margin: 0 0 1.8rem; }
        body.dark-mode .reg-closed-card { background: #1e1e32; box-shadow: 0 8px 40px rgba(0,0,0,.35); }
        body.dark-mode .reg-closed-title { color: #eaeaea; }
    </style>
</head>
<body>
    <div class="reg-closed-wrap">
        <div class="reg-closed-card">
            <span class="reg-closed-icon">🔒</span>
            <h2 class="reg-closed-title">注册暂未开放</h2>
            <p class="reg-closed-desc">当前站点已关闭注册，暂时无法创建新账号。<br>如有疑问，请联系管理员。</p>
            <a href="login.php" class="btn btn-primary">返回登录</a>
        </div>
    </div>
    <button id="themeToggle" class="theme-toggle auth-theme-toggle">🌙</button>
    <script src="theme-toggle.js"></script>
</body>
</html>
<?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户注册</title>
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

        /* ── 邮件验证码区域 ───────────────────────────────────── */
        .verify-group { display: flex; gap: 10px; align-items: stretch; }
        .verify-group .input-wrapper { flex: 1 1 0; margin-bottom: 0; min-width: 0; }
        .verify-btn {
            white-space: nowrap; padding: 0 15px; height: 50px; margin: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; min-width: 120px; max-width: 140px;
            flex-shrink: 0; box-sizing: border-box; line-height: 1;
        }

        /* ── 图形验证码区域 ───────────────────────────────────── */
        .captcha-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .captcha-group .input-wrapper {
            flex: 1 1 0;
            margin-bottom: 0;
            min-width: 0;
        }
        .captcha-img-wrap {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .captcha-img {
            height: 44px;
            width: auto;
            border-radius: 8px;
            border: 1.5px solid rgba(155,140,255,.35);
            cursor: pointer;
            display: block;
            background: #f6f4ff;
            transition: opacity .2s;
        }
        .captcha-img:hover { opacity: .85; }
        .captcha-refresh {
            background: none; border: none; padding: 0; cursor: pointer;
            font-size: 18px; line-height: 1; color: #9b8cff;
            transition: transform .3s;
        }
        .captcha-refresh:hover { transform: rotate(180deg); color: #ff4db1; }
        body.dark-mode .captcha-img {
            border-color: rgba(176,160,255,.3);
            background: #2a2845;
        }
        .captcha-hint {
            font-size: .8rem;
            color: #aaa;
            margin-top: 4px;
        }

        /* ── 响应式 ──────────────────────────────────────────── */
        @media (max-width: 768px) { .verify-btn { padding: 0 12px; min-width: 110px; max-width: 130px; font-size: 13px; height: 50px; } }
        @media (max-width: 600px) {
            .verify-group { flex-direction: row; flex-wrap: nowrap; align-items: center; }
            .verify-group .input-wrapper { flex: 1 1 0; min-width: 0; }
            .verify-btn { flex: 0 0 auto; min-width: 110px; max-width: 130px; height: 50px; align-self: stretch; }
            .captcha-group { flex-wrap: wrap; }
            .captcha-group .input-wrapper { flex: 2 1 120px; }
        }
        @media (max-width: 480px) {
            .verify-group { flex-direction: column; gap: 8px; }
            .verify-group .input-wrapper { width: 100%; min-width: 100%; }
            .verify-btn { width: 100%; height: 50px; max-width: 100%; padding: 0 20px; align-self: auto; }
        }
        @media (max-width: 360px) { .verify-btn { font-size: 12px; padding: 0 10px; height: 46px; } }

        #verify_code { font-family: monospace; letter-spacing: 2px; font-size: 16px; text-align: center; }
        #captcha_input { font-family: monospace; letter-spacing: 3px; font-size: 16px; text-align: center; }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2>加入我们</h2>
                <p class="auth-subtitle">只需几步，开启您的旅程</p>
            </div>

            <?php if (isset($_SESSION['register_error'])): ?>
                <div class="message error">⚠️ <?php echo $_SESSION['register_error']; unset($_SESSION['register_error']); ?></div>
            <?php endif; ?>

            <form action="handle_register.php" method="post" class="auth-form">

                <!-- 用户名 -->
                <div class="form-group">
                    <label for="username">用户名 <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="text" id="username" name="username"
                               pattern="^[a-zA-Z0-9_]{1,20}$" required
                               placeholder="设置唯一用户ID">
                        <svg class="input-icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
                    </div>
                    <small>字母、数字或下划线，20位以内</small>
                </div>

                <!-- 邮箱 -->
                <div class="form-group">
                    <label for="email">邮箱 <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="email" id="email" name="email" required placeholder="example@mail.com">
                        <svg class="input-icon" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                    </div>
                </div>

                <!-- 邮箱验证码 -->
                <div class="form-group">
                    <label for="verify_code">邮箱验证码 <span class="required">*</span></label>
                    <div class="verify-group">
                        <div class="input-wrapper">
                            <input type="text" id="verify_code" name="verify_code"
                                   maxlength="6" required placeholder="6位验证码">
                            <svg class="input-icon" viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                        </div>
                        <button type="button" id="send_code" class="btn btn-secondary verify-btn">发送验证码</button>
                    </div>
                    <small id="verifyCodeHint" style="display: none; color: #4caf50;">✨ 验证码已发送，10分钟内有效</small>
                </div>

                <!-- 密码 -->
                <div class="form-group">
                    <label for="password">密码 <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password"
                               minlength="6" required placeholder="至少6位字符">
                        <svg class="input-icon" viewBox="0 0 24 24"><path d="M2 17h20v2H2zm1.15-4.05L4 11.47l.85 1.48 1.3-.75-.85-1.48H7v-1.5H5.3l.85-1.47-1.3-.75-.85 1.48L4 7.03l-1.3.75.85 1.47L2.7 10.73l1.3.75.85-1.48zM12 6c-3.87 0-7 3.13-7 7s3.13 7 7 7 7-3.13 7-7-3.13-7-7-7zm0 12c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm6 1h2v2h-2z"/></svg>
                    </div>
                </div>

                <!-- 图形验证码（注册强制） -->
                <div class="form-group">
                    <label for="captcha_input">图形验证码 <span class="required">*</span></label>
                    <div class="captcha-group">
                        <div class="input-wrapper">
                            <input type="text"
                                   id="captcha_input"
                                   name="captcha_input"
                                   maxlength="5"
                                   autocomplete="off"
                                   required
                                   placeholder="请输入图中字符">
                            <svg class="input-icon" viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                        </div>
                        <div class="captcha-img-wrap">
                            <img id="captchaImg"
                                 src="captcha.php?t=<?php echo time(); ?>"
                                 alt="图形验证码"
                                 class="captcha-img"
                                 title="看不清？点击刷新">
                            <button type="button"
                                    class="captcha-refresh"
                                    onclick="refreshCaptcha()"
                                    title="刷新验证码">↻</button>
                        </div>
                    </div>
                    <div class="captcha-hint">不区分大小写 · 看不清可点击图片刷新</div>
                </div>

                <button type="submit" class="btn btn-primary full-width">立即注册</button>
                <p class="form-hint">已有账号？<a href="login.php" class="link-primary">直接登录</a></p>
                <p class="auth-terms">
                    注册即代表同意 <a href="page?slug=terms" class="link">服务条款</a> 与 <a href="page?slug=privacy" class="link">隐私政策</a>
                </p>
            </form>
        </div>
    </div>
    <button id="themeToggle" class="theme-toggle auth-theme-toggle">🌙</button>
    <script src="theme-toggle.js"></script>
    <script>
    // ── 图形验证码刷新 ────────────────────────────────────────
    function refreshCaptcha() {
        const img = document.getElementById('captchaImg');
        img.src = 'captcha.php?t=' + Date.now();
        const btn = document.querySelector('.captcha-refresh');
        btn.style.transform = 'rotate(360deg)';
        setTimeout(() => { btn.style.transform = ''; }, 400);
    }
    document.getElementById('captchaImg').addEventListener('click', refreshCaptcha);

    // ── 邮件验证码发送 ────────────────────────────────────────
    document.getElementById('send_code').addEventListener('click', function () {
        const email   = document.getElementById('email').value;
        const sendBtn = this;
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            alert('请先输入有效的邮箱地址');
            return;
        }
        sendBtn.disabled    = true;
        sendBtn.textContent = '少女祈祷中...';
        fetch('send_verify_code.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'email=' + encodeURIComponent(email)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('verifyCodeHint').style.display = 'block';
                let countdown = 60;
                sendBtn.textContent = `${countdown}秒后重试`;
                const timer = setInterval(() => {
                    countdown--;
                    sendBtn.textContent = `${countdown}秒后重试`;
                    if (countdown <= 0) {
                        clearInterval(timer);
                        sendBtn.disabled    = false;
                        sendBtn.textContent = '发送验证码';
                    }
                }, 1000);
            } else {
                alert('发送失败: ' + data.message);
                sendBtn.disabled    = false;
                sendBtn.textContent = '发送验证码';
            }
        })
        .catch(() => {
            alert('发送失败，请重试');
            sendBtn.disabled    = false;
            sendBtn.textContent = '发送验证码';
        });
    });
    </script>
</body>
</html>