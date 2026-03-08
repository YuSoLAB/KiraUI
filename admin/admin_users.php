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
            $settings = [
                'email_mode'      => $_POST['email_mode'] ?? 'all',
                'allowed_domains' => isset($_POST['allowed_domains']) ? explode("\n", $_POST['allowed_domains']) : [],
                'blocked_domains' => isset($_POST['blocked_domains']) ? explode("\n", $_POST['blocked_domains']) : []
            ];
            $settings['allowed_domains'] = array_filter(array_map('trim', $settings['allowed_domains']));
            $settings['blocked_domains'] = array_filter(array_map('trim', $settings['blocked_domains']));
            saveRegistrationEmailSettings($settings);
            $message = "注册邮箱设置已保存";
            break;
    }
}
$db    = Db::getInstance();
$stmt  = $db->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
$emailSettings = getRegistrationEmailSettings();
?>
<style>
/* ── users ── */
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
            <span><?php echo htmlspecialchars($user['nickname']); ?></span>
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
        <form method="post">
            <input type="hidden" name="action" value="save_email_settings">
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
                    <textarea name="allowed_domains" rows="5" style="padding:.48rem .72rem;border:1px solid var(--admin-border,rgba(155,140,255,.4));border-radius:8px;font-size:.86rem;resize:vertical;background:var(--admin-card,#fff);color:inherit;box-sizing:border-box;width:100%;"><?php echo implode("\n", $emailSettings['allowed_domains']); ?></textarea>
                </div>
                <div class="mfg">
                    <label>禁止的邮箱域名 <small style="font-weight:normal;color:var(--sub,#999);">（每行一个，黑名单模式下生效）</small></label>
                    <textarea name="blocked_domains" rows="5" style="padding:.48rem .72rem;border:1px solid var(--admin-border,rgba(155,140,255,.4));border-radius:8px;font-size:.86rem;resize:vertical;background:var(--admin-card,#fff);color:inherit;box-sizing:border-box;width:100%;"><?php echo implode("\n", $emailSettings['blocked_domains']); ?></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">💾 保存设置</button>
        </form>
    </div>

</div>