<?php
/**
 * register.php — 注册页面
 * 根据后台 registration_mode 配置自动切换：
 *   phone  → 仅手机号 + 短信验证码
 *   email  → 仅邮箱   + 邮件验证码
 *   both   → 手机号 + 邮箱均需验证
 */
session_start();
if (!defined('ROOT_DIR')) { define('ROOT_DIR', dirname(__FILE__)); }

$_regEnabled = true;
$_regMode    = 'phone';
$_siteTitle  = '测试网站';

if (file_exists(ROOT_DIR . '/include/Config.php')) {
    require_once ROOT_DIR . '/include/Config.php';
    $cfg         = Config::getInstance();
    $_regEnabled = $cfg->get('registration_enabled', '1') !== '0';
    $_regMode    = $cfg->get('registration_mode', 'phone');
    $_siteTitle  = $cfg->get('site_title', '测试网站');
}
if (!in_array($_regMode, ['phone', 'email', 'both'])) { $_regMode = 'phone'; }

// ── 注册已关闭 ─────────────────────────────────────────────────
if (!$_regEnabled) { ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册已关闭 - <?php echo htmlspecialchars($_siteTitle); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .reg-closed-wrap { min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem; }
        .reg-closed-card { background:var(--card-bg,#fff);border-radius:18px;box-shadow:0 8px 40px rgba(108,93,251,.13);
            padding:3rem 2.5rem;max-width:440px;width:100%;text-align:center; }
        .reg-closed-icon  { font-size:3.5rem;margin-bottom:1rem;display:block; }
        .reg-closed-title { font-size:1.4rem;font-weight:700;margin:0 0 .6rem;color:var(--text,#1a1a2e); }
        .reg-closed-desc  { font-size:.92rem;color:var(--sub,#888);line-height:1.7;margin:0 0 1.8rem; }
        body.dark-mode .reg-closed-card  { background:#1e1e32;box-shadow:0 8px 40px rgba(0,0,0,.35); }
        body.dark-mode .reg-closed-title { color:#eaeaea; }
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
<?php exit; }

// ── 模式文案 ───────────────────────────────────────────────────
$_subtitle = match($_regMode) {
    'email' => '使用邮箱注册账号',
    'both'  => '使用手机号 + 邮箱注册账号',
    default => '使用手机号注册账号',
};
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户注册 - <?php echo htmlspecialchars($_siteTitle); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .input-wrapper { position:relative;margin-bottom:5px; }
        .input-icon {
            position:absolute;left:14px;top:50%;transform:translateY(-50%);
            width:20px;height:20px;fill:#9b8cff;transition:.3s;pointer-events:none;
        }
        .auth-form input:not([type="checkbox"]) { padding-left:44px !important; }
        .auth-form input:focus + .input-icon { fill:#ff4db1; }
        body.dark-mode .input-icon { fill:#b0a0ff; }
        body.dark-mode .auth-form input:focus + .input-icon { fill:#ff88cc; }

        /* 发送验证码按钮行 */
        .verify-group { display:flex;gap:10px;align-items:stretch; }
        .verify-group .input-wrapper { flex:1 1 0;margin-bottom:0;min-width:0; }
        .verify-btn {
            white-space:nowrap;padding:0 15px;height:50px;margin:0;
            display:flex;align-items:center;justify-content:center;
            font-size:14px;min-width:120px;max-width:140px;
            flex-shrink:0;box-sizing:border-box;line-height:1;
        }

        /* 图形验证码内联展开区 */
        .captcha-inline-box {
            display:none;
            margin-top:10px;padding:14px 16px;
            background:var(--form-bg,rgba(108,93,251,.04));
            border:1px solid rgba(108,93,251,.18);
            border-radius:12px;
            animation:fadeSlideDown .2s ease;
        }
        body.dark-mode .captcha-inline-box { background:rgba(108,93,251,.07);border-color:rgba(176,160,255,.2); }
        @keyframes fadeSlideDown { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
        .captcha-inline-box .captcha-row { display:flex;gap:10px;align-items:center; }
        .captcha-inline-box .captcha-row .input-wrapper { flex:1 1 0;min-width:0;margin-bottom:0; }
        .captcha-img-wrap { flex-shrink:0;display:flex;align-items:center;gap:6px; }
        .captcha-img {
            height:44px;width:auto;border-radius:8px;
            border:1.5px solid rgba(155,140,255,.35);cursor:pointer;
            display:block;background:#f6f4ff;transition:opacity .2s;
        }
        .captcha-img:hover { opacity:.85; }
        .captcha-refresh {
            background:none;border:none;padding:0;cursor:pointer;
            font-size:18px;line-height:1;color:#9b8cff;transition:transform .3s;
        }
        .captcha-refresh:hover { transform:rotate(180deg);color:#ff4db1; }
        body.dark-mode .captcha-img { border-color:rgba(176,160,255,.3);background:#2a2845; }
        .captcha-hint { font-size:.8rem;color:#aaa;margin-top:6px; }
        .captcha-inline-actions { display:flex;gap:8px;margin-top:10px; }
        .captcha-inline-actions .btn { flex:1;height:42px;font-size:14px; }

        /* 状态提示 */
        .field-status { font-size:.8rem;margin-top:4px;min-height:18px;display:none; }
        .field-status.ok  { color:#4caf50; }
        .field-status.err { color:#e74c3c; }

        /* both 模式分隔线 */
        .section-divider {
            display:flex;align-items:center;gap:10px;margin:8px 0 4px;
            color:#aaa;font-size:.8rem;
        }
        .section-divider::before,.section-divider::after {
            content:'';flex:1;height:1px;background:rgba(155,140,255,.2);
        }

        @media(max-width:768px) { .verify-btn{padding:0 12px;min-width:110px;max-width:130px;font-size:13px;height:50px;} }
        @media(max-width:480px) {
            .verify-group{flex-direction:column;gap:8px;}
            .verify-group .input-wrapper{width:100%;min-width:100%;}
            .verify-btn{width:100%;height:50px;max-width:100%;padding:0 20px;align-self:auto;}
        }
        #sms_code,#email_code { font-family:monospace;letter-spacing:2px;font-size:16px;text-align:center; }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2>加入我们</h2>
                <p class="auth-subtitle"><?php echo htmlspecialchars($_subtitle); ?></p>
            </div>

            <?php if (isset($_SESSION['register_error'])): ?>
                <div class="message error">⚠️ <?php echo $_SESSION['register_error']; unset($_SESSION['register_error']); ?></div>
            <?php endif; ?>

            <form action="handle_register.php" method="post" class="auth-form">
                <input type="hidden" name="reg_mode" value="<?php echo htmlspecialchars($_regMode); ?>">

                <!-- 用户名 -->
                <div class="form-group">
                    <label for="username">用户名 <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="text" id="username" name="username" required
                               pattern="^[a-zA-Z0-9_]{1,20}$"
                               placeholder="字母、数字、下划线，最多20位">
                        <svg class="input-icon" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    </div>
                </div>

                <!-- 密码 -->
                <div class="form-group">
                    <label for="password">密码 <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" required minlength="6" placeholder="至少 6 位">
                        <svg class="input-icon" viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                    </div>
                </div>

                <?php if ($_regMode === 'both'): ?>
                <div class="section-divider">手机号验证</div>
                <?php endif; ?>

                <!-- ══ 手机号区块（phone / both 模式） ══ -->
                <?php if ($_regMode === 'phone' || $_regMode === 'both'): ?>
                <div class="form-group">
                    <label for="phone">手机号 <span class="required">*</span></label>
                    <div class="verify-group">
                        <div class="input-wrapper">
                            <input type="tel" id="phone" name="phone"
                                   pattern="^1[3-9]\d{9}$" maxlength="11"
                                   <?= $_regMode === 'phone' ? 'required' : '' ?>
                                   placeholder="请输入 11 位手机号">
                            <svg class="input-icon" viewBox="0 0 24 24"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1-9.4 0-17-7.6-17-17 0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1L6.6 10.8z"/></svg>
                        </div>
                        <button type="button" id="send_sms" class="btn btn-secondary verify-btn">发送验证码</button>
                    </div>

                    <!-- 图形验证码内联展开（SMS） -->
                    <div class="captcha-inline-box" id="captchaBoxPhone">
                        <div class="captcha-row">
                            <div class="input-wrapper">
                                <input type="text" id="captcha_phone" maxlength="5" autocomplete="off"
                                       placeholder="输入图中字符（不区分大小写）">
                                <svg class="input-icon" viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                            </div>
                            <div class="captcha-img-wrap">
                                <img id="captchaImgPhone" src="captcha.php?t=<?php echo time(); ?>"
                                     alt="图形验证码" class="captcha-img" title="看不清？点击刷新">
                                <button type="button" class="captcha-refresh"
                                        onclick="refreshCaptchaPhone()" title="刷新验证码">↻</button>
                            </div>
                        </div>
                        <div class="captcha-hint">不区分大小写 · 看不清可点击图片刷新</div>
                        <div class="captcha-inline-actions">
                            <button type="button" id="cancelCaptchaPhone" class="btn btn-secondary">取消</button>
                            <button type="button" id="confirmSendSms" class="btn btn-primary">确认发送</button>
                        </div>
                    </div>
                    <div class="field-status" id="smsStatus"></div>
                </div>

                <!-- 短信验证码 -->
                <div class="form-group">
                    <label for="sms_code">短信验证码 <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="text" id="sms_code" name="sms_code"
                               maxlength="6" <?= $_regMode === 'phone' ? 'required' : '' ?>
                               placeholder="6 位短信验证码">
                        <svg class="input-icon" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($_regMode === 'both'): ?>
                <div class="section-divider">邮箱验证</div>
                <?php endif; ?>

                <!-- ══ 邮箱区块（email / both 模式） ══ -->
                <?php if ($_regMode === 'email' || $_regMode === 'both'): ?>
                <div class="form-group">
                    <label for="email">邮箱地址 <span class="required">*</span></label>
                    <div class="verify-group">
                        <div class="input-wrapper">
                            <input type="email" id="email" name="email"
                                   <?= $_regMode === 'email' ? 'required' : '' ?>
                                   placeholder="请输入有效的邮箱地址">
                            <svg class="input-icon" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                        </div>
                        <button type="button" id="send_email" class="btn btn-secondary verify-btn">发送验证码</button>
                    </div>

                    <!-- 图形验证码内联展开（Email） -->
                    <div class="captcha-inline-box" id="captchaBoxEmail">
                        <div class="captcha-row">
                            <div class="input-wrapper">
                                <input type="text" id="captcha_email" maxlength="5" autocomplete="off"
                                       placeholder="输入图中字符（不区分大小写）">
                                <svg class="input-icon" viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                            </div>
                            <div class="captcha-img-wrap">
                                <img id="captchaImgEmail" src="captcha.php?t=<?php echo time() + 1; ?>"
                                     alt="图形验证码" class="captcha-img" title="看不清？点击刷新">
                                <button type="button" class="captcha-refresh"
                                        onclick="refreshCaptchaEmail()" title="刷新验证码">↻</button>
                            </div>
                        </div>
                        <div class="captcha-hint">不区分大小写 · 看不清可点击图片刷新</div>
                        <div class="captcha-inline-actions">
                            <button type="button" id="cancelCaptchaEmail" class="btn btn-secondary">取消</button>
                            <button type="button" id="confirmSendEmail" class="btn btn-primary">确认发送</button>
                        </div>
                    </div>
                    <div class="field-status" id="emailStatus"></div>
                </div>

                <!-- 邮件验证码 -->
                <div class="form-group">
                    <label for="email_code">邮件验证码 <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="text" id="email_code" name="email_code"
                               maxlength="6" <?= $_regMode === 'email' ? 'required' : '' ?>
                               placeholder="6 位邮件验证码">
                        <svg class="input-icon" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                    </div>
                </div>
                <?php endif; ?>

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
    // ── 通用倒计时 ──────────────────────────────────────────────
    function startCountdown(btn, seconds, resetLabel) {
        btn.disabled    = true;
        btn.textContent = seconds + 's 后' + resetLabel;
        const timer = setInterval(() => {
            seconds--;
            btn.textContent = seconds + 's 后' + resetLabel;
            if (seconds <= 0) { clearInterval(timer); btn.disabled = false; btn.textContent = resetLabel; }
        }, 1000);
    }

    function setStatus(el, msg, type) {
        el.textContent   = msg;
        el.className     = 'field-status ' + (type || '');
        el.style.display = msg ? 'block' : 'none';
    }

    <?php if ($_regMode === 'phone' || $_regMode === 'both'): ?>
    // ══ 短信 OTP 发送逻辑 ════════════════════════════════════════
    (function () {
        const sendBtn    = document.getElementById('send_sms');
        const captchaBox = document.getElementById('captchaBoxPhone');
        const confirmBtn = document.getElementById('confirmSendSms');
        const cancelBtn  = document.getElementById('cancelCaptchaPhone');
        const statusEl   = document.getElementById('smsStatus');

        function refreshCaptchaPhone() {
            const img = document.getElementById('captchaImgPhone');
            img.src = 'captcha.php?t=' + Date.now();
            document.getElementById('captcha_phone').value = '';
            const btn = captchaBox.querySelector('.captcha-refresh');
            btn.style.transform = 'rotate(360deg)';
            setTimeout(() => { btn.style.transform = ''; }, 400);
        }
        window.refreshCaptchaPhone = refreshCaptchaPhone;
        document.getElementById('captchaImgPhone').addEventListener('click', refreshCaptchaPhone);

        sendBtn.addEventListener('click', function () {
            const phone = (document.getElementById('phone')?.value || '').trim();
            if (!/^1[3-9]\d{9}$/.test(phone)) {
                setStatus(statusEl, '请先输入正确的 11 位手机号', 'err'); return;
            }
            setStatus(statusEl, '', '');
            refreshCaptchaPhone();
            captchaBox.style.display = 'block';
            document.getElementById('captcha_phone').focus();
        });

        cancelBtn.addEventListener('click', () => {
            captchaBox.style.display = 'none';
            document.getElementById('captcha_phone').value = '';
        });

        document.getElementById('captcha_phone').addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); confirmBtn.click(); }
        });

        confirmBtn.addEventListener('click', async function () {
            const phone   = (document.getElementById('phone')?.value || '').trim();
            const captcha = (document.getElementById('captcha_phone')?.value || '').trim();
            if (!captcha) { setStatus(statusEl, '请输入图形验证码', 'err'); return; }

            confirmBtn.disabled = true; confirmBtn.textContent = '发送中…';
            setStatus(statusEl, '', '');
            try {
                const fd = new FormData();
                fd.append('action', 'send');
                fd.append('phone', phone);
                fd.append('captcha_input', captcha);
                const r    = await fetch('send_sms_verify.php', { method: 'POST', body: fd });
                const data = await r.json();
                if (data.ok) {
                    captchaBox.style.display = 'none';
                    document.getElementById('captcha_phone').value = '';
                    setStatus(statusEl, '✅ 验证码已发送，5 分钟内有效', 'ok');
                    sendBtn.disabled = true;
                    startCountdown(sendBtn, 60, '重新发送');
                } else {
                    refreshCaptchaPhone();
                    setStatus(statusEl, '❌ ' + (data.msg || '发送失败，请重试'), 'err');
                }
            } catch { setStatus(statusEl, '❌ 网络错误，请重试', 'err'); }
            finally { confirmBtn.disabled = false; confirmBtn.textContent = '确认发送'; }
        });
    })();
    <?php endif; ?>

    <?php if ($_regMode === 'email' || $_regMode === 'both'): ?>
    // ══ 邮件 OTP 发送逻辑 ════════════════════════════════════════
    (function () {
        const sendBtn    = document.getElementById('send_email');
        const captchaBox = document.getElementById('captchaBoxEmail');
        const confirmBtn = document.getElementById('confirmSendEmail');
        const cancelBtn  = document.getElementById('cancelCaptchaEmail');
        const statusEl   = document.getElementById('emailStatus');

        function refreshCaptchaEmail() {
            const img = document.getElementById('captchaImgEmail');
            img.src = 'captcha.php?t=' + Date.now();
            document.getElementById('captcha_email').value = '';
            const btn = captchaBox.querySelector('.captcha-refresh');
            btn.style.transform = 'rotate(360deg)';
            setTimeout(() => { btn.style.transform = ''; }, 400);
        }
        window.refreshCaptchaEmail = refreshCaptchaEmail;
        document.getElementById('captchaImgEmail').addEventListener('click', refreshCaptchaEmail);

        function isValidEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }

        sendBtn.addEventListener('click', function () {
            const email = (document.getElementById('email')?.value || '').trim();
            if (!isValidEmail(email)) {
                setStatus(statusEl, '请先输入有效的邮箱地址', 'err'); return;
            }
            setStatus(statusEl, '', '');
            refreshCaptchaEmail();
            captchaBox.style.display = 'block';
            document.getElementById('captcha_email').focus();
        });

        cancelBtn.addEventListener('click', () => {
            captchaBox.style.display = 'none';
            document.getElementById('captcha_email').value = '';
        });

        document.getElementById('captcha_email').addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); confirmBtn.click(); }
        });

        confirmBtn.addEventListener('click', async function () {
            const email   = (document.getElementById('email')?.value || '').trim();
            const captcha = (document.getElementById('captcha_email')?.value || '').trim();
            if (!captcha) { setStatus(statusEl, '请输入图形验证码', 'err'); return; }

            confirmBtn.disabled = true; confirmBtn.textContent = '发送中…';
            setStatus(statusEl, '', '');
            try {
                const fd = new FormData();
                fd.append('email', email);
                fd.append('captcha_input', captcha);
                const r    = await fetch('send_verify_code.php', { method: 'POST', body: fd });
                const data = await r.json();
                if (data.ok) {
                    captchaBox.style.display = 'none';
                    document.getElementById('captcha_email').value = '';
                    setStatus(statusEl, '✅ 验证码已发送至邮箱，10 分钟内有效', 'ok');
                    sendBtn.disabled = true;
                    startCountdown(sendBtn, 60, '重新发送');
                } else {
                    refreshCaptchaEmail();
                    setStatus(statusEl, '❌ ' + (data.msg || '发送失败，请重试'), 'err');
                }
            } catch { setStatus(statusEl, '❌ 网络错误，请重试', 'err'); }
            finally { confirmBtn.disabled = false; confirmBtn.textContent = '确认发送'; }
        });
    })();
    <?php endif; ?>
    </script>
</body>
</html>