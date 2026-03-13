<?php
// 用户管理
require_once __DIR__ . '/../include/Db.php';
require_once 'admin_functions.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $db = Db::getInstance();
    $userId = $_POST['user_id'] ?? 0;
    switch ($_POST['action']) {
        case 'update_status':
            $status    = $_POST['status'] ?? 'normal';
            $duration  = $_POST['duration'] ?? 0;
            $expiresAt = null;
            if ($duration > 0 && in_array($status, ['frozen','banned'])) {
                $expiresAt = date('Y-m-d H:i:s', strtotime("+$duration hours"));
            }
            $stmt = $db->prepare("UPDATE users SET status = ?, status_expires_at = ? WHERE id = ?");
            $stmt->execute([$status, $expiresAt, $userId]);
            $message = "用户状态已更新";
            break;
        case 'save_email_settings':
            // 已迁移至 admin_ajax.php (type=user, user_action=save_email_settings)
            // 此分支仅作向后兼容保留
            $message = "请使用新版 AJAX 接口保存设置";
            break;
    }
}
$db    = Db::getInstance();
$stmt  = $db->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
$emailSettings = getRegistrationEmailSettings();
// 读取注册开关状态（依赖 Config 类，admin_functions 已在上方 require）
$registrationEnabled = '1'; // 默认开启
if (class_exists('Config')) {
    $registrationEnabled = Config::getInstance()->get('registration_enabled', '1');
}
?>
<style>
/* ── registration toggle ── */
.reg-toggle-track {
    width:42px; height:24px; border-radius:12px; background:#ccc;
    position:relative; transition:background .25s; cursor:pointer;
}
#registrationToggle:checked ~ .reg-toggle-track,
.reg-toggle-wrap.on .reg-toggle-track { background:#6c5dfb; }
.reg-toggle-thumb {
    position:absolute; top:3px; left:3px; width:18px; height:18px;
    border-radius:50%; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.25);
    transition:left .25s;
}
#registrationToggle:checked ~ .reg-toggle-track .reg-toggle-thumb,
.reg-toggle-wrap.on .reg-toggle-track .reg-toggle-thumb { left:21px; }
body.dark-mode .reg-toggle-track { background:#444; }

.usr-row { display:grid; grid-template-columns:48px 1fr 1fr 1.4fr 120px 90px auto; gap:.4rem; align-items:center; padding:.55rem 1rem; border-bottom:1px solid rgba(155,140,255,.12); font-size:.84rem; }
.usr-row:last-child { border-bottom:none; }
.usr-row:hover { background:rgba(155,140,255,.05); }
.usr-status-form { display:flex; gap:.3rem; align-items:center; flex-wrap:wrap; }
.usr-status-form select { padding:.28rem .5rem; border:1px solid var(--admin-border,rgba(155,140,255,.4)); border-radius:7px; font-size:.78rem; background:var(--admin-card,#fff); color:inherit; }
.usr-status-form select:focus { outline:none; border-color:#6c5dfb; }
.ust-normal  { background:rgba(39,174,96,.12);  color:#1a7a45; }
.ust-frozen  { background:rgba(255,193,7,.15);  color:#856404; }
.ust-banned  { background:rgba(255,71,87,.1);   color:#c0392b; }
.usr-email-card { margin-top:1rem; }
body.dark-mode .usr-row:hover { background:rgba(176,160,255,.06); }
body.dark-mode .usr-row { border-bottom-color:rgba(176,160,255,.12); }
body.dark-mode .usr-status-form select { background:#2a2a42aa; border-color:var(--dark-admin-border); color:var(--dark-text,#eaeaea); }
body.dark-mode .ust-normal { background:rgba(39,174,96,.15); color:#6fcf97; }
body.dark-mode .ust-frozen { background:rgba(255,193,7,.1);  color:#f2c94c; }
body.dark-mode .ust-banned { background:rgba(255,71,87,.1);  color:#eb5757; }
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
body.dark-mode input::placeholder,
body.dark-mode textarea::placeholder { color: #6b6b8a !important; }
@media(max-width:800px){
    .usr-row { grid-template-columns:1fr auto; }
    .usr-row > :nth-child(1),
    .usr-row > :nth-child(3),
    .usr-row > :nth-child(4),
    .usr-row > :nth-child(5) { display:none; }
}
</style>

<div class="admin-section">

    <div class="mhdr">
        <div>
            <h2 class="mhdr-title">👥 用户管理</h2>
            <p class="mhdr-sub">管理注册用户的状态，配置注册邮箱白 / 黑名单规则。</p>
        </div>
    </div>

    <?php if (isset($message)): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- 注册开关 -->
    <div class="mbuilder" style="padding:1.2rem;margin-bottom:1rem;">
        <p style="margin:0 0 .9rem;font-size:.83rem;font-weight:700;color:#6c5dfb;">🔒 注册开关</p>
        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
            <label style="display:flex;align-items:center;gap:.55rem;cursor:pointer;user-select:none;">
                <div class="reg-toggle-wrap">
                    <input type="checkbox" id="registrationToggle" <?php echo $registrationEnabled === '1' ? 'checked' : ''; ?> style="display:none;">
                    <div class="reg-toggle-track">
                        <div class="reg-toggle-thumb"></div>
                    </div>
                </div>
                <span id="registrationToggleLabel" style="font-size:.88rem;font-weight:600;">
                    <?php echo $registrationEnabled === '1' ? '注册已开放' : '注册已关闭'; ?>
                </span>
            </label>
            <span id="registrationToggleMsg" style="font-size:.82rem;display:none;"></span>
        </div>
        <p style="margin:.7rem 0 0;font-size:.78rem;color:var(--sub,#999);">关闭后，访客访问注册页时将看到「当前站点已关闭注册」的提示。</p>
    </div>

    <!-- 用户列表 -->
    <div class="mbuilder" style="margin-bottom:1rem; overflow-x:auto;">
        <div class="mhead" style="grid-template-columns:48px 1fr 1fr 1.4fr 120px 90px auto;">
            <span>ID</span><span>用户名</span><span>昵称</span><span>邮箱</span><span>注册时间</span><span>状态</span><span>操作</span>
        </div>
        <?php 
        $hasUsers = false;
        foreach ($users as $user):
            if ($user['role'] == 'admin' || $user['email'] == 'admin@example.com') continue;
            $hasUsers = true;
            $statusMap = ['normal'=>['ust-normal','正常'], 'frozen'=>['ust-frozen','冻结'], 'banned'=>['ust-banned','封禁']];
            [$statusClass, $statusText] = $statusMap[$user['status']] ?? ['ust-normal','正常'];
            if ($user['status'] !== 'normal' && $user['status_expires_at']) {
                $statusText .= '<br><small style="font-size:.68rem;">' . $user['status_expires_at'] . '</small>';
            }
        ?>
        <div class="usr-row">
            <span style="color:var(--sub,#aaa);"><?php echo $user['id']; ?></span>
            <span style="font-weight:600;"><?php echo htmlspecialchars($user['username']); ?></span>
            <span><?php echo htmlspecialchars($user['nickname'] ?? ''); ?></span>
            <span style="word-break:break-all;"><?php echo htmlspecialchars($user['email']); ?></span>
            <span style="color:var(--sub,#888);"><?php echo substr($user['created_at'], 0, 10); ?></span>
            <span><span class="mbadge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></span>
            <form method="post" class="usr-status-form">
                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                <input type="hidden" name="action" value="update_status">
                <select name="status">
                    <option value="normal" <?php echo $user['status']=='normal'?'selected':''; ?>>正常</option>
                    <option value="frozen" <?php echo $user['status']=='frozen'?'selected':''; ?>>冻结</option>
                    <option value="banned" <?php echo $user['status']=='banned'?'selected':''; ?>>封禁</option>
                </select>
                <select name="duration">
                    <option value="0">永久</option>
                    <option value="24">24 小时</option>
                    <option value="72">3 天</option>
                    <option value="168">7 天</option>
                    <option value="720">30 天</option>
                </select>
                <button type="submit" class="btn btn-xs mbtn-e">更新</button>
            </form>
        </div>
        <?php endforeach; ?>
        <?php if (!$hasUsers): ?>
        <p class="mempty">暂无普通用户</p>
        <?php endif; ?>
    </div>

    <!-- 注册邮箱设置 -->
    <div class="mbuilder" style="padding:1.2rem;" class="usr-email-card">
        <p style="margin:0 0 1rem; font-size:.83rem; font-weight:700; color:#6c5dfb;">📋 注册邮箱设置</p>
        <form id="emailSettingsForm">
            <div class="mfg" style="margin-bottom:.75rem;">
                <label>邮箱过滤模式</label>
                <select name="email_mode">
                    <option value="all"       <?php echo $emailSettings['email_mode']=='all'       ?'selected':''; ?>>允许所有邮箱</option>
                    <option value="whitelist" <?php echo $emailSettings['email_mode']=='whitelist' ?'selected':''; ?>>仅允许白名单邮箱</option>
                    <option value="blacklist" <?php echo $emailSettings['email_mode']=='blacklist' ?'selected':''; ?>>禁止黑名单邮箱</option>
                </select>
            </div>
            <div class="mfrow2" style="margin-bottom:1rem;">
                <div class="mfg">
                    <label>允许的邮箱域名 <small style="font-weight:normal;color:var(--sub,#999);">（每行一个，白名单模式下生效）</small></label>
                    <textarea name="allowed_domains" rows="5" style="padding:.48rem .72rem;border:1px solid var(--admin-border,rgba(155,140,255,.4));border-radius:8px;font-size:.86rem;resize:vertical;background:var(--admin-card,#fff);color:inherit;box-sizing:border-box;width:100%;"><?php echo htmlspecialchars(implode("\n", $emailSettings['allowed_domains'])); ?></textarea>
                </div>
                <div class="mfg">
                    <label>禁止的邮箱域名 <small style="font-weight:normal;color:var(--sub,#999);">（每行一个，黑名单模式下生效）</small></label>
                    <textarea name="blocked_domains" rows="5" style="padding:.48rem .72rem;border:1px solid var(--admin-border,rgba(155,140,255,.4));border-radius:8px;font-size:.86rem;resize:vertical;background:var(--admin-card,#fff);color:inherit;box-sizing:border-box;width:100%;"><?php echo htmlspecialchars(implode("\n", $emailSettings['blocked_domains'])); ?></textarea>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:.8rem;">
                <button type="submit" id="emailSettingsBtn" class="btn btn-primary">💾 保存设置</button>
                <span id="emailSettingsMsg" style="font-size:.82rem;display:none;"></span>
            </div>
        </form>
    </div>

</div>

<script>
// ── 注册开关 ──────────────────────────────────────────
(function () {
    var checkbox = document.getElementById('registrationToggle');
    var label    = document.getElementById('registrationToggleLabel');
    var msg      = document.getElementById('registrationToggleMsg');
    if (!checkbox) return;

    // 同步视觉状态
    function syncUI(on) {
        var wrap = checkbox.closest('.reg-toggle-wrap') || checkbox.parentElement;
        if (on) { wrap.classList.add('on'); } else { wrap.classList.remove('on'); }
        label.textContent = on ? '注册已开放' : '注册已关闭';
        label.style.color = on ? '#27ae60' : '#e74c3c';
    }
    syncUI(checkbox.checked);

    checkbox.addEventListener('change', async function () {
        var on = checkbox.checked;
        syncUI(on);
        msg.style.display = 'none';

        var fd = new FormData();
        fd.append('type', 'user');
        fd.append('user_action', 'save_registration_settings');
        fd.append('registration_enabled', on ? '1' : '0');

        try {
            var r = await fetch('admin_ajax.php', { method: 'POST', body: fd });
            var d = await r.json();
            msg.textContent   = d.msg || (d.ok ? '已保存' : '保存失败');
            msg.style.color   = d.ok ? '#27ae60' : '#e74c3c';
            msg.style.display = 'inline';
            setTimeout(function () { msg.style.display = 'none'; }, 2500);
            if (!d.ok) { checkbox.checked = !on; syncUI(!on); } // 回滚
        } catch (err) {
            msg.textContent   = '网络错误，请重试';
            msg.style.color   = '#e74c3c';
            msg.style.display = 'inline';
            checkbox.checked  = !on; syncUI(!on); // 回滚
        }
    });
})();

// ── 注册邮箱设置 ───────────────────────────────────────
(function () {
    var form = document.getElementById('emailSettingsForm');
    if (!form) return;
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        var btn = document.getElementById('emailSettingsBtn');
        var msg = document.getElementById('emailSettingsMsg');
        var orig = btn.textContent;
        btn.disabled = true;
        btn.textContent = '保存中…';
        msg.style.display = 'none';

        var fd = new FormData(form);
        fd.append('type', 'user');
        fd.append('user_action', 'save_email_settings');

        try {
            var r = await fetch('admin_ajax.php', { method: 'POST', body: fd });
            var d = await r.json();
            msg.textContent = d.msg || (d.ok ? '已保存' : '保存失败');
            msg.style.color  = d.ok ? '#27ae60' : '#e74c3c';
            msg.style.display = 'inline';
            setTimeout(function () { msg.style.display = 'none'; }, 3000);
        } catch (err) {
            msg.textContent = '网络错误，请重试';
            msg.style.color = '#e74c3c';
            msg.style.display = 'inline';
        } finally {
            btn.disabled = false;
            btn.textContent = orig;
        }
    });
})();
</script>