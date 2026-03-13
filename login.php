<?php
session_start();
// ── 登录失败次数（当前会话）─────────────────────────────────
$_failCount  = (int)($_SESSION['login_fail_count'] ?? 0);
$_showCaptcha = ($_failCount >= 3);
?>
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
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            width: 20px; height: 20px; fill: #9b8cff; transition: all 0.3s ease; pointer-events: none;
        }
        .auth-form input:not([type="checkbox"]) { padding-left: 44px !important; }
        .auth-form input:focus + .input-icon { fill: #ff4db1; transform: translateY(-50%) scale(1.1); }
        body.dark-mode .input-icon { fill: #b0a0ff; }
        body.dark-mode .auth-form input:focus + .input-icon { fill: #ff88cc; }

        /* ── 图形验证码区域 ─────────────────────────────────── */
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

        @media (max-width: 480px) {
            .captcha-group { flex-wrap: wrap; }
            .captcha-group .input-wrapper { flex: 2 1 120px; }
            .captcha-img-wrap { flex: 1 1 auto; }
        }
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
                    <span>✨</span> <?php echo htmlspecialchars($_SESSION['register_success']); unset($_SESSION['register_success']); ?>
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

                <?php if ($_showCaptcha): ?>
                <!-- ── 图形验证码（连续失败 3 次后出现）──────────────── -->
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
                <?php endif; ?>

                <button type="submit" class="btn btn-primary full-width">立即登录</button>
                <p class="form-hint">还没有账号？<a href="register.php" class="link-primary">免费注册</a></p>
            </form>
        </div>
    </div>
    <button id="themeToggle" class="theme-toggle auth-theme-toggle">🌙</button>
    <script src="theme-toggle.js"></script>
    <?php if ($_showCaptcha): ?>
    <script>
    function refreshCaptcha() {
        const img = document.getElementById('captchaImg');
        img.src = 'captcha.php?t=' + Date.now();
        const btn = document.querySelector('.captcha-refresh');
        btn.style.transform = 'rotate(360deg)';
        setTimeout(() => { btn.style.transform = ''; }, 400);
    }
    // 点击图片也可刷新
    document.getElementById('captchaImg').addEventListener('click', refreshCaptcha);
    </script>
    <?php endif; ?>
</body>
</html>