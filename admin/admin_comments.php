<?php
// 评论管理
require_once __DIR__ . '/../include/Db.php';
require_once 'admin_functions.php';
require_once 'comment_functions.php';

$db = Db::getInstance();

// ── 处理操作（PRG 模式：POST → Redirect → GET，彻底消除"重新提交表单"提示）──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $commentId  = intval($_POST['comment_id'] ?? 0);
    $articleId  = intval($_POST['article_id'] ?? 0);
    // 携带当前 tab，操作完成后跳回同一个 tab
    $returnTab  = $_POST['current_tab'] ?? 'pending';

    $msg = '操作失败，请重试';
    $mt  = 'error';

    switch ($_POST['action']) {

        case 'approve':
            if (moderateComment($articleId, $commentId, true)) {
                $msg = '评论已通过审核'; $mt = 'success';
            }
            break;

        case 'reject':
            if (moderateComment($articleId, $commentId, false)) {
                $msg = '评论已拒绝'; $mt = 'success';
            }
            break;

        case 'delete':
            if (deleteComment($articleId, $commentId)) {
                $msg = '评论已删除'; $mt = 'success';
            }
            break;

        case 'approve_all':
            $stmt = $db->prepare("UPDATE comments SET approved = 1 WHERE approved = 0");
            $stmt->execute();
            $msg = '所有待审评论已批量通过'; $mt = 'success';
            break;

        case 'update_email_moderation':
            $emailHash = $_POST['email_hash'] ?? '';
            $mode      = $_POST['mode'] ?? 'strict';
            if ($emailHash && in_array($mode, ['auto', 'strict'])) {
                updateEmailModeration($emailHash, $mode);
                $msg = '邮箱审核模式已更新'; $mt = 'success';
            }
            $returnTab = 'approved'; // 该操作始终在"已通过"tab
            break;

        case 'save_comment_settings':
            $settings = [
                'email_mode'           => $_POST['email_mode']          ?? 'all',
                'default_moderation'   => $_POST['default_moderation']  ?? 'strict',
                'enable_comments'      => isset($_POST['enable_comments']),
                'allow_guest_comments' => isset($_POST['allow_guest_comments']),
                'allowed_domains'      => isset($_POST['allowed_domains'])
                    ? array_filter(array_map('trim', explode("\n", $_POST['allowed_domains']))) : [],
                'blocked_domains'      => isset($_POST['blocked_domains'])
                    ? array_filter(array_map('trim', explode("\n", $_POST['blocked_domains']))) : [],
            ];
            saveCommentSettings($settings);
            $msg = '评论设置已保存'; $mt = 'success';
            $returnTab = 'settings';
            break;
    }

    // 因本文件由 admin.php include，页面已有输出，无法用 header()，改用 JS 跳转实现 PRG
    // 保留原有的 page 参数，避免跳回后台默认首页
    $basePath  = strtok($_SERVER['REQUEST_URI'], '?');
    $page      = $_GET['page'] ?? '';
    $pageParam = $page ? ('page=' . urlencode($page) . '&') : '';
    $location  = $basePath . '?' . $pageParam . 'msg=' . urlencode($msg) . '&mt=' . urlencode($mt) . '#tab-' . $returnTab;
    echo '<script>location.replace(' . json_encode($location) . ');</script>';
    exit;
}

// ── 从 GET 参数恢复提示消息 ─────────────────────────────────────────────
$message     = isset($_GET['msg']) ? htmlspecialchars(urldecode($_GET['msg'])) : '';
$messageType = $_GET['mt'] ?? 'success';

// ── 读取数据 ──────────────────────────────────────────────────────────────
// 待审评论（approved = 0）
$stmtPending = $db->query("
    SELECT c.*, a.title AS article_title
    FROM comments c
    LEFT JOIN articles a ON a.id = c.article_id
    WHERE c.approved = 0
    ORDER BY c.created_at DESC
");
$pendingComments = $stmtPending->fetchAll();

// 已通过评论（approved = 1）- 最近 50 条
$stmtApproved = $db->query("
    SELECT c.*, a.title AS article_title
    FROM comments c
    LEFT JOIN articles a ON a.id = c.article_id
    WHERE c.approved = 1
    ORDER BY c.created_at DESC
    LIMIT 50
");
$approvedComments = $stmtApproved->fetchAll();

// ── 批量查询已通过评论的邮箱审核模式（修复永远显示"严格模式"的问题）────────
$emailModes = [];
$emailHashes = array_unique(array_filter(array_column($approvedComments, 'email_hash')));
if (!empty($emailHashes)) {
    $placeholders = implode(',', array_fill(0, count($emailHashes), '?'));
    $stmtModes = $db->prepare(
        "SELECT email_hash, moderation FROM email_moderation WHERE email_hash IN ($placeholders)"
    );
    $stmtModes->execute(array_values($emailHashes));
    foreach ($stmtModes->fetchAll() as $row) {
        $emailModes[$row['email_hash']] = $row['moderation'];
    }
}

// 当前评论设置
$commentSettings = initCommentSettings();
?>

<style>
/* ── comment admin ── */
.cmt-tabs { display:flex; gap:.5rem; margin-bottom:1.2rem; flex-wrap:wrap; }
.cmt-tab  { padding:.42rem 1.1rem; border-radius:20px; font-size:.82rem; font-weight:600;
            cursor:pointer; border:1.5px solid rgba(155,140,255,.35);
            background:transparent; color:inherit; transition:all .18s; }
.cmt-tab.active,
.cmt-tab:hover { background:#6c5dfb; color:#fff; border-color:#6c5dfb; }

.cmt-panel { display:none; }
.cmt-panel.active { display:block; }

.cmt-row { display:grid;
           grid-template-columns: 44px 1fr 130px 110px auto;
           gap:.5rem; align-items:start;
           padding:.7rem 1rem; border-bottom:1px solid rgba(155,140,255,.12);
           font-size:.83rem; }
.cmt-row:last-child { border-bottom:none; }
.cmt-row:hover { background:rgba(155,140,255,.05); }

.cmt-avatar { width:36px; height:36px; border-radius:50%; object-fit:cover;
              background:#e0deff; display:flex; align-items:center; justify-content:center; }
.cmt-meta  { font-size:.73rem; color:var(--sub,#999); margin-top:.2rem; }
.cmt-body  { margin-top:.3rem; line-height:1.55; word-break:break-word; }
.cmt-article { font-size:.75rem; color:#6c5dfb; margin-top:.25rem; font-style:italic; }
.cmt-actions { display:flex; flex-wrap:wrap; gap:.3rem; }

.badge-pending  { background:rgba(255,193,7,.18);  color:#856404; }
.badge-approved { background:rgba(39,174,96,.15);  color:#1a7a45; }

.bulk-bar { display:flex; align-items:center; gap:.8rem; padding:.6rem 1rem;
            background:rgba(108,93,251,.07); border-radius:10px; margin-bottom:.8rem; font-size:.82rem; }

.cmt-empty { padding:2.5rem 1rem; text-align:center; color:var(--sub,#aaa); font-size:.88rem; }

.settings-grid { display:grid; grid-template-columns:1fr 1fr; gap:1.2rem; }
@media(max-width:680px) {
    .settings-grid { grid-template-columns:1fr; }
    .cmt-row { grid-template-columns:1fr auto; }
    .cmt-row > :nth-child(1),
    .cmt-row > :nth-child(3),
    .cmt-row > :nth-child(4) { display:none; }
}

body.dark-mode .cmt-row:hover { background:rgba(176,160,255,.06); }
body.dark-mode .cmt-row { border-bottom-color:rgba(176,160,255,.1); }
body.dark-mode .bulk-bar { background:rgba(108,93,251,.12); }
body.dark-mode input[type=text], body.dark-mode textarea, body.dark-mode select {
    background:#1e1e32 !important; color:#eaeaea !important;
    border-color:rgba(176,160,255,.35) !important;
}
</style>

<div class="admin-section">

    <div class="mhdr">
        <div>
            <h2 class="mhdr-title">💬 评论管理</h2>
            <p class="mhdr-sub">审核、拒绝或删除用户评论，配置评论功能设置。</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message <?php echo $messageType === 'error' ? 'message-error' : ''; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- 标签页切换 -->
    <div class="cmt-tabs">
        <button class="cmt-tab" data-tab="pending" onclick="switchTab('pending',this)">
            ⏳ 待审核
            <?php if (count($pendingComments) > 0): ?>
                <span class="mbadge badge-pending" style="margin-left:.3rem;"><?php echo count($pendingComments); ?></span>
            <?php endif; ?>
        </button>
        <button class="cmt-tab" data-tab="approved" onclick="switchTab('approved',this)">✅ 已通过</button>
        <button class="cmt-tab" data-tab="settings" onclick="switchTab('settings',this)">⚙️ 评论设置</button>
    </div>

    <!-- ── 待审核 ── -->
    <div id="tab-pending" class="cmt-panel">
        <?php if (count($pendingComments) > 0): ?>
        <div class="bulk-bar">
            <span>共 <strong><?php echo count($pendingComments); ?></strong> 条待审评论</span>
            <form method="post" style="margin:0;">
                <input type="hidden" name="action" value="approve_all">
                <input type="hidden" name="current_tab" value="pending">
                <button type="submit" class="btn btn-xs mbtn-e"
                    onclick="return confirm('确认批量通过所有待审评论？')">✅ 全部通过</button>
            </form>
        </div>
        <div class="mbuilder" style="overflow-x:auto;">
            <div class="mhead" style="grid-template-columns:44px 1fr 130px 110px auto;">
                <span>头像</span><span>内容</span><span>邮箱</span><span>时间</span><span>操作</span>
            </div>
            <?php foreach ($pendingComments as $cmt): ?>
            <div class="cmt-row">
                <!-- 头像 -->
                <div>
                    <?php $avatar = getCommentAvatar($cmt['email'], $cmt['user_id'] ?? null); ?>
                    <?php if (strpos($avatar, 'http') === 0): ?>
                        <img src="<?php echo htmlspecialchars($avatar); ?>" class="cmt-avatar" alt="avatar"
                             onerror="this.style.display='none'">
                    <?php else: ?>
                        <img src="../<?php echo htmlspecialchars($avatar); ?>" class="cmt-avatar" alt="avatar"
                             onerror="this.style.display='none'">
                    <?php endif; ?>
                </div>
                <!-- 内容 -->
                <div>
                    <strong><?php echo htmlspecialchars($cmt['name']); ?></strong>
                    <span class="cmt-meta">&nbsp;#<?php echo $cmt['id']; ?>
                        <?php if ($cmt['parent_id']): ?>
                            · 回复 #<?php echo $cmt['parent_id']; ?>
                        <?php endif; ?>
                    </span>
                    <div class="cmt-body"><?php echo nl2br(htmlspecialchars($cmt['content'])); ?></div>
                    <?php if (!empty($cmt['article_title'])): ?>
                        <div class="cmt-article">📄 <?php echo htmlspecialchars($cmt['article_title']); ?></div>
                    <?php endif; ?>
                </div>
                <!-- 邮箱 -->
                <span style="word-break:break-all;color:var(--sub,#888);font-size:.75rem;">
                    <?php echo htmlspecialchars($cmt['email']); ?>
                </span>
                <!-- 时间 -->
                <span style="color:var(--sub,#888);font-size:.75rem;">
                    <?php echo substr($cmt['created_at'], 0, 16); ?>
                </span>
                <!-- 操作 -->
                <div class="cmt-actions">
                    <form method="post" style="margin:0;">
                        <input type="hidden" name="action"      value="approve">
                        <input type="hidden" name="comment_id"  value="<?php echo $cmt['id']; ?>">
                        <input type="hidden" name="article_id"  value="<?php echo $cmt['article_id']; ?>">
                        <input type="hidden" name="current_tab" value="pending">
                        <button type="submit" class="btn btn-xs mbtn-e" title="通过">✅</button>
                    </form>
                    <form method="post" style="margin:0;">
                        <input type="hidden" name="action"      value="reject">
                        <input type="hidden" name="comment_id"  value="<?php echo $cmt['id']; ?>">
                        <input type="hidden" name="article_id"  value="<?php echo $cmt['article_id']; ?>">
                        <input type="hidden" name="current_tab" value="pending">
                        <button type="submit" class="btn btn-xs" title="拒绝" style="background:rgba(255,193,7,.15);color:#856404;">🚫</button>
                    </form>
                    <form method="post" style="margin:0;"
                          onsubmit="return confirm('确认删除这条评论？')">
                        <input type="hidden" name="action"      value="delete">
                        <input type="hidden" name="comment_id"  value="<?php echo $cmt['id']; ?>">
                        <input type="hidden" name="article_id"  value="<?php echo $cmt['article_id']; ?>">
                        <input type="hidden" name="current_tab" value="pending">
                        <button type="submit" class="btn btn-xs mbtn-d" title="删除">🗑️</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="cmt-empty">🎉 暂无待审核评论</div>
        <?php endif; ?>
    </div>

    <!-- ── 已通过 ── -->
    <div id="tab-approved" class="cmt-panel">
        <?php if (count($approvedComments) > 0): ?>
        <div class="mbuilder" style="overflow-x:auto;">
            <div class="mhead" style="grid-template-columns:44px 1fr 130px 110px auto;">
                <span>头像</span><span>内容</span><span>邮箱</span><span>时间</span><span>操作</span>
            </div>
            <?php foreach ($approvedComments as $cmt): ?>
            <?php
                // 从数据库读取该邮箱实际的审核模式，默认为 strict
                $currentMode = $emailModes[$cmt['email_hash']] ?? 'strict';
            ?>
            <div class="cmt-row">
                <div>
                    <?php $avatar = getCommentAvatar($cmt['email'], $cmt['user_id'] ?? null); ?>
                    <?php if (strpos($avatar, 'http') === 0): ?>
                        <img src="<?php echo htmlspecialchars($avatar); ?>" class="cmt-avatar" alt="avatar"
                             onerror="this.style.display='none'">
                    <?php else: ?>
                        <img src="../<?php echo htmlspecialchars($avatar); ?>" class="cmt-avatar" alt="avatar"
                             onerror="this.style.display='none'">
                    <?php endif; ?>
                </div>
                <div>
                    <strong><?php echo htmlspecialchars($cmt['name']); ?></strong>
                    <span class="cmt-meta">&nbsp;#<?php echo $cmt['id']; ?></span>
                    <div class="cmt-body"><?php echo nl2br(htmlspecialchars($cmt['content'])); ?></div>
                    <?php if (!empty($cmt['article_title'])): ?>
                        <div class="cmt-article">📄 <?php echo htmlspecialchars($cmt['article_title']); ?></div>
                    <?php endif; ?>
                </div>
                <span style="word-break:break-all;color:var(--sub,#888);font-size:.75rem;">
                    <?php echo htmlspecialchars($cmt['email']); ?>
                </span>
                <span style="color:var(--sub,#888);font-size:.75rem;">
                    <?php echo substr($cmt['created_at'], 0, 16); ?>
                </span>
                <div class="cmt-actions">
                    <!-- 设置邮箱审核模式（正确回显当前模式） -->
                    <form method="post" style="margin:0;">
                        <input type="hidden" name="action"      value="update_email_moderation">
                        <input type="hidden" name="email_hash"  value="<?php echo htmlspecialchars($cmt['email_hash']); ?>">
                        <input type="hidden" name="current_tab" value="approved">
                        <select name="mode" style="padding:.25rem .4rem;font-size:.75rem;border:1px solid rgba(155,140,255,.4);border-radius:6px;background:var(--admin-card,#fff);color:inherit;">
                            <option value="auto"   <?php echo $currentMode === 'auto'   ? 'selected' : ''; ?>>自动通过</option>
                            <option value="strict" <?php echo $currentMode === 'strict' ? 'selected' : ''; ?>>严格审核</option>
                        </select>
                        <button type="submit" class="btn btn-xs" style="font-size:.72rem;">设置</button>
                    </form>
                    <form method="post" style="margin:0;"
                          onsubmit="return confirm('确认删除这条评论？')">
                        <input type="hidden" name="action"      value="delete">
                        <input type="hidden" name="comment_id"  value="<?php echo $cmt['id']; ?>">
                        <input type="hidden" name="article_id"  value="<?php echo $cmt['article_id']; ?>">
                        <input type="hidden" name="current_tab" value="approved">
                        <button type="submit" class="btn btn-xs mbtn-d" title="删除">🗑️</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="cmt-empty">暂无已通过的评论</div>
        <?php endif; ?>
    </div>

    <!-- ── 评论设置 ── -->
    <div id="tab-settings" class="cmt-panel">
        <div class="mbuilder" style="padding:1.4rem;">
            <p style="margin:0 0 1.2rem;font-size:.83rem;font-weight:700;color:#6c5dfb;">⚙️ 评论功能设置</p>
            <form method="post">
                <input type="hidden" name="action"      value="save_comment_settings">
                <input type="hidden" name="current_tab" value="settings">

                <!-- 开关行 -->
                <div style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-bottom:1rem;">
                    <label style="display:flex;align-items:center;gap:.45rem;font-size:.85rem;cursor:pointer;">
                        <input type="checkbox" name="enable_comments"
                            <?php echo $commentSettings['enable_comments'] ? 'checked' : ''; ?>>
                        启用评论功能
                    </label>
                    <label style="display:flex;align-items:center;gap:.45rem;font-size:.85rem;cursor:pointer;">
                        <input type="checkbox" name="allow_guest_comments"
                            <?php echo ($commentSettings['allow_guest_comments'] ?? true) ? 'checked' : ''; ?>>
                        允许游客评论（无需登录）
                    </label>
                </div>

                <div class="settings-grid" style="margin-bottom:1rem;">
                    <div class="mfg">
                        <label>默认审核模式</label>
                        <select name="default_moderation">
                            <option value="strict" <?php echo $commentSettings['default_moderation']==='strict'?'selected':''; ?>>严格（所有评论需审核）</option>
                            <option value="auto"   <?php echo $commentSettings['default_moderation']==='auto'  ?'selected':''; ?>>宽松（历史通过用户自动发布）</option>
                        </select>
                    </div>
                    <div class="mfg">
                        <label>邮箱过滤模式</label>
                        <select name="email_mode">
                            <option value="all"       <?php echo $commentSettings['email_mode']==='all'       ?'selected':''; ?>>允许所有邮箱</option>
                            <option value="whitelist" <?php echo $commentSettings['email_mode']==='whitelist' ?'selected':''; ?>>仅允许白名单</option>
                            <option value="blacklist" <?php echo $commentSettings['email_mode']==='blacklist' ?'selected':''; ?>>禁止黑名单</option>
                        </select>
                    </div>
                </div>

                <div class="settings-grid" style="margin-bottom:1.2rem;">
                    <div class="mfg">
                        <label>允许的邮箱域名
                            <small style="font-weight:normal;color:var(--sub,#999);">（每行一个，白名单模式生效）</small>
                        </label>
                        <textarea name="allowed_domains" rows="5"
                            style="padding:.48rem .72rem;border:1px solid var(--admin-border,rgba(155,140,255,.4));border-radius:8px;font-size:.86rem;resize:vertical;background:var(--admin-card,#fff);color:inherit;box-sizing:border-box;width:100%;"><?php
                            echo htmlspecialchars(implode("\n", $commentSettings['allowed_domains']));
                        ?></textarea>
                    </div>
                    <div class="mfg">
                        <label>禁止的邮箱域名
                            <small style="font-weight:normal;color:var(--sub,#999);">（每行一个，黑名单模式生效）</small>
                        </label>
                        <textarea name="blocked_domains" rows="5"
                            style="padding:.48rem .72rem;border:1px solid var(--admin-border,rgba(155,140,255,.4));border-radius:8px;font-size:.86rem;resize:vertical;background:var(--admin-card,#fff);color:inherit;box-sizing:border-box;width:100%;"><?php
                            echo htmlspecialchars(implode("\n", $commentSettings['blocked_domains']));
                        ?></textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">💾 保存设置</button>
            </form>
        </div>
    </div>

</div>

<script>
// ── Tab 切换 + URL hash 持久化 ─────────────────────────────────────────────
function switchTab(name, el) {
    document.querySelectorAll('.cmt-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.cmt-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    if (el) el.classList.add('active');
    // 更新 URL hash，使刷新后能恢复当前 tab（不触发页面滚动）
    history.replaceState(null, '', '#tab-' + name);
}

// 页面加载时：优先读取 URL hash 恢复 tab
(function () {
    const hash = location.hash.replace('#tab-', '');
    const validTabs = ['pending', 'approved', 'settings'];
    const target = validTabs.includes(hash) ? hash : 'pending';
    const btn = document.querySelector('.cmt-tab[data-tab="' + target + '"]');
    switchTab(target, btn);
})();
</script>