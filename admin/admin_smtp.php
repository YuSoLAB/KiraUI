<?php
require_once dirname(__DIR__) . '/include/Config.php';

$config = Config::getInstance();
$smtpConfig = [
    'enabled'    => $config->get('smtp_enabled', '0') === '1',
    'host'       => $config->get('smtp_host', ''),
    'port'       => $config->get('smtp_port', '587'),
    'username'   => $config->get('smtp_username', ''),
    'password'   => $config->get('smtp_password', ''),
    'from_email' => $config->get('smtp_from_email', ''),
    'from_name'  => $config->get('smtp_from_name', ''),
    'encryption' => $config->get('smtp_encryption', 'tls'),
];

// ── 注意：POST 处理已移至 admin_ajax.php（type=config, config_action=save_smtp）
// 原有的服务端 POST + PRG 重定向方案会导致整页刷新，使深色模式丢失，
// 并因父页面已输出内容导致 header() 失效，产生各种兼容性问题。

$ajaxUrl = 'admin_ajax.php';
?>
<style>
/* ── smtp ── */
.smtp-toggle { display:inline-flex; align-items:center; gap:.55rem; font-size:.9rem; cursor:pointer; user-select:none; }
.smtp-toggle input[type=checkbox] { width:16px; height:16px; accent-color:#6c5dfb; cursor:pointer; }
.smtp-hint { font-size:.75rem; color:var(--sub,#999); margin-top:.2rem; display:block; }

.ajax-msg { display:none; padding:.75rem 1rem; border-radius:6px; font-size:.875rem; margin-bottom:1rem; }
.ajax-msg.success { background:#f0fff4; border-left:4px solid #38a169; color:#276749; }
.ajax-msg.error   { background:#fff0f0; border-left:4px solid #e53e3e; color:#c53030; }
body.dark-mode .ajax-msg.success { background:#1a3a2a; color:#9ae6b4; border-color:#38a169; }
body.dark-mode .ajax-msg.error   { background:#3a1a1a; color:#fc8181; border-color:#e53e3e; }

body.dark-mode input[type=text],
body.dark-mode input[type=email],
body.dark-mode input[type=number],
body.dark-mode input[type=password],
body.dark-mode textarea,
body.dark-mode select {
    background: #1e1e32 !important;
    color: #eaeaea !important;
    border-color: rgba(176,160,255,.35) !important;
}
body.dark-mode input::placeholder { color: #6b6b8a !important; }
</style>

<div class="admin-section">

    <div class="mhdr">
        <div>
            <h2 class="mhdr-title">📧 SMTP 邮件配置</h2>
            <p class="mhdr-sub">配置邮件发送服务器，用于发送注册验证、密码找回等系统邮件。</p>
        </div>
    </div>

    <div class="mtip">💡 部分邮件服务商（如 QQ 邮箱、163 邮箱）需要使用「授权码」而非登录密码，请在对应邮箱设置中获取。</div>

    <div id="smtp-msg" class="ajax-msg"></div>

    <div class="mbuilder" style="padding:1.2rem;">
        <form id="smtp-form">

            <!-- 启用开关 -->
            <div class="mfg" style="margin-bottom:1rem;">
                <label class="smtp-toggle">
                    <input type="checkbox" name="smtp_enabled"
                           <?php echo $smtpConfig['enabled'] ? 'checked' : ''; ?>>
                    启用 SMTP 邮件功能
                </label>
            </div>

            <div class="mfrow2" style="margin-bottom:.75rem;">
                <div class="mfg">
                    <label>SMTP 服务器地址</label>
                    <input type="text" name="host" value="<?php echo htmlspecialchars($smtpConfig['host']); ?>" placeholder="例如：smtp.example.com">
                </div>
                <div class="mfg">
                    <label>SMTP 端口</label>
                    <input type="number" name="port" value="<?php echo htmlspecialchars($smtpConfig['port']); ?>" placeholder="25 / 465 / 587">
                </div>
            </div>

            <div class="mfg" style="margin-bottom:.75rem;">
                <label>加密方式</label>
                <select name="encryption">
                    <option value="tls" <?php echo $smtpConfig['encryption'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                    <option value="ssl" <?php echo $smtpConfig['encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    <option value=""   <?php echo $smtpConfig['encryption'] === ''    ? 'selected' : ''; ?>>无加密</option>
                </select>
            </div>

            <div class="mfrow2" style="margin-bottom:.75rem;">
                <div class="mfg">
                    <label>SMTP 用户名</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($smtpConfig['username']); ?>">
                </div>
                <div class="mfg">
                    <label>SMTP 密码 / 授权码</label>
                    <input type="password" name="password"
                           placeholder="<?php echo $smtpConfig['password'] !== '' ? '（已保存，留空则不修改）' : '请输入密码'; ?>">
                    <span class="smtp-hint">留空表示不修改已保存的密码；部分邮箱需使用授权码</span>
                </div>
            </div>

            <div class="mfrow2" style="margin-bottom:1rem;">
                <div class="mfg">
                    <label>发件人邮箱</label>
                    <input type="email" name="from_email" value="<?php echo htmlspecialchars($smtpConfig['from_email']); ?>">
                </div>
                <div class="mfg">
                    <label>发件人名称</label>
                    <input type="text" name="from_name" value="<?php echo htmlspecialchars($smtpConfig['from_name']); ?>">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" id="smtp-submit-btn">💾 保存 SMTP 配置</button>
        </form>
    </div>

</div>

<script>
(function () {
    const AJAX_URL = <?php echo json_encode($ajaxUrl); ?>;

    function showMsg(type, text) {
        const el = document.getElementById('smtp-msg');
        if (!el) return;
        el.className = 'ajax-msg ' + type;
        el.textContent = text;
        el.style.display = 'block';
        clearTimeout(el._t);
        el._t = setTimeout(function () { el.style.display = 'none'; }, 5000);
    }

    const form = document.getElementById('smtp-form');
    const btn  = document.getElementById('smtp-submit-btn');
    if (!form || !btn) return;

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const orig = btn.textContent;
        btn.disabled = true;
        btn.textContent = '保存中…';
        try {
            const fd = new FormData(form);
            fd.append('type', 'config');
            fd.append('config_action', 'save_smtp');
            const res  = await fetch(AJAX_URL, { method: 'POST', body: fd });
            const data = await res.json();
            showMsg(data.ok ? 'success' : 'error', data.msg || (data.ok ? '保存成功' : '保存失败'));
        } catch (err) {
            showMsg('error', '网络错误，请重试：' + err.message);
        } finally {
            btn.disabled = false;
            btn.textContent = orig;
        }
    });
})();
</script>