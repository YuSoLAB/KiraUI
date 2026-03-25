<?php
// 评论管理
require_once __DIR__ . '/../include/Db.php';
require_once 'admin_functions.php';
require_once 'comment_functions.php';

$db = Db::getInstance();

// ── 所有评论操作已迁移至 AJAX（admin_ajax.php type=comment）──────────────
// 不再处理 POST，直接读取数据渲染页面。
$message     = '';
$messageType = 'success';

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
    // 确保表存在（首次使用前可能尚未由 updateEmailModeration 创建）
    $db->exec("CREATE TABLE IF NOT EXISTS email_moderation (
        email_hash VARCHAR(32) PRIMARY KEY,
        moderation VARCHAR(20) NOT NULL DEFAULT 'strict',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
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
            <span>共 <strong id="pendingCount"><?php echo count($pendingComments); ?></strong> 条待审评论</span>
            <button type="button" class="btn btn-xs mbtn-e" id="approveAllBtn"
                onclick="cmtApproveAll()">✅ 全部通过</button>
        </div>
        <div class="mbuilder" style="overflow-x:auto;">
            <div class="mhead" style="grid-template-columns:44px 1fr 130px 110px auto;">
                <span>头像</span><span>内容</span><span>邮箱</span><span>时间</span><span>操作</span>
            </div>
            <?php foreach ($pendingComments as $cmt): ?>
            <div class="cmt-row" id="cmt-row-<?php echo $cmt['id']; ?>">
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
                    <button type="button" class="btn btn-xs mbtn-e" title="通过"
                        onclick="cmtAction('approve',<?php echo $cmt['id']; ?>,<?php echo $cmt['article_id']; ?>,this)">✅</button>
                    <button type="button" class="btn btn-xs" title="拒绝"
                        style="background:rgba(255,193,7,.15);color:#856404;"
                        onclick="cmtAction('reject',<?php echo $cmt['id']; ?>,<?php echo $cmt['article_id']; ?>,this)">🚫</button>
                    <button type="button" class="btn btn-xs mbtn-d" title="删除"
                        onclick="cmtConfirmDelete(<?php echo $cmt['id']; ?>,<?php echo $cmt['article_id']; ?>,this)">🗑️</button>
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
            <div class="cmt-row" id="cmt-row-<?php echo $cmt['id']; ?>">
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
                    <!-- 设置邮箱审核模式（AJAX，无刷新） -->
                    <select data-email-hash="<?php echo htmlspecialchars($cmt['email_hash']); ?>"
                            onchange="cmtUpdateEmailMode(this)"
                            style="padding:.25rem .4rem;font-size:.75rem;border:1px solid rgba(155,140,255,.4);border-radius:6px;background:var(--admin-card,#fff);color:inherit;">
                        <option value="auto"   <?php echo $currentMode === 'auto'   ? 'selected' : ''; ?>>自动通过</option>
                        <option value="strict" <?php echo $currentMode === 'strict' ? 'selected' : ''; ?>>严格审核</option>
                    </select>
                    <button type="button" class="btn btn-xs mbtn-d" title="删除"
                        onclick="cmtConfirmDelete(<?php echo $cmt['id']; ?>,<?php echo $cmt['article_id']; ?>,this)">🗑️</button>
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
            <form id="commentSettingsForm">
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

                <div style="display:flex;align-items:center;gap:.8rem;">
                    <button type="submit" id="commentSettingsBtn" class="btn btn-primary">💾 保存设置</button>
                    <span id="commentSettingsMsg" style="font-size:.82rem;display:none;"></span>
                </div>
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
    history.replaceState(null, '', '#tab-' + name);
}

(function () {
    const hash = location.hash.replace('#tab-', '');
    const validTabs = ['pending', 'approved', 'settings'];
    const target = validTabs.includes(hash) ? hash : 'pending';
    const btn = document.querySelector('.cmt-tab[data-tab="' + target + '"]');
    switchTab(target, btn);
})();

// ── 公共：显示顶部提示条（自动消失）──────────────────────────────────────
function cmtShowMsg(text, isOk) {
    var el = document.getElementById('cmtToast');
    if (!el) {
        el = document.createElement('div');
        el.id = 'cmtToast';
        el.style.cssText = [
            'position:fixed;top:18px;left:50%;transform:translateX(-50%);z-index:9999',
            'padding:.55rem 1.4rem;border-radius:10px;font-size:.85rem;font-weight:600',
            'box-shadow:0 4px 18px rgba(0,0,0,.18);transition:opacity .3s;pointer-events:none'
        ].join(';');
        document.body.appendChild(el);
    }
    el.textContent = text;
    el.style.background = isOk ? '#27ae60' : '#e74c3c';
    el.style.color = '#fff';
    el.style.opacity = '1';
    clearTimeout(el._t);
    el._t = setTimeout(function () { el.style.opacity = '0'; }, 2600);
}

// ── 公共：更新"待审核"tab 徽章及计数 ────────────────────────────────────
function cmtUpdatePendingBadge(count) {
    var countEl = document.getElementById('pendingCount');
    if (countEl) countEl.textContent = count;

    var tabBtn = document.querySelector('.cmt-tab[data-tab="pending"]');
    if (!tabBtn) return;
    var badge = tabBtn.querySelector('.mbadge');
    if (count > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'mbadge badge-pending';
            badge.style.marginLeft = '.3rem';
            tabBtn.appendChild(badge);
        }
        badge.textContent = count;
    } else {
        if (badge) badge.remove();
        // 待审列表清空时显示空状态
        var panel = document.getElementById('tab-pending');
        if (panel && panel.querySelector('.cmt-row') === null) {
            var builder = panel.querySelector('.mbuilder');
            var bulkBar = panel.querySelector('.bulk-bar');
            if (builder) builder.style.display = 'none';
            if (bulkBar) bulkBar.style.display = 'none';
            if (!panel.querySelector('.cmt-empty')) {
                var emp = document.createElement('div');
                emp.className = 'cmt-empty';
                emp.textContent = '🎉 暂无待审核评论';
                panel.appendChild(emp);
            }
        }
    }
}

// ── 单条操作：approve / reject / delete ─────────────────────────────────
async function cmtAction(action, commentId, articleId, btn) {
    var orig = btn.textContent;
    btn.disabled = true;
    btn.textContent = '…';

    var fd = new FormData();
    fd.append('type',           'comment');
    fd.append('comment_action', action);
    fd.append('comment_id',     commentId);
    fd.append('article_id',     articleId);

    try {
        var r = await fetch('admin_ajax.php', { method: 'POST', body: fd });
        var d = await r.json();
        cmtShowMsg(d.msg || (d.ok ? '操作成功' : '操作失败'), d.ok);
        if (d.ok) {
            // 从 DOM 移除该行
            var row = document.getElementById('cmt-row-' + commentId);
            if (row) {
                row.style.transition = 'opacity .25s';
                row.style.opacity = '0';
                setTimeout(function () { row.remove(); }, 260);
            }
            if (typeof d.pending !== 'undefined') {
                cmtUpdatePendingBadge(d.pending);
            }
        } else {
            btn.disabled = false;
            btn.textContent = orig;
        }
    } catch (err) {
        cmtShowMsg('网络错误，请重试', false);
        btn.disabled = false;
        btn.textContent = orig;
    }
}

// ── 删除前确认 ────────────────────────────────────────────────────────────
function cmtConfirmDelete(commentId, articleId, btn) {
    if (!confirm('确认删除这条评论？')) return;
    cmtAction('delete', commentId, articleId, btn);
}

// ── 批量通过所有待审 ──────────────────────────────────────────────────────
async function cmtApproveAll() {
    if (!confirm('确认批量通过所有待审评论？')) return;
    var btn = document.getElementById('approveAllBtn');
    if (btn) { btn.disabled = true; btn.textContent = '处理中…'; }

    var fd = new FormData();
    fd.append('type',           'comment');
    fd.append('comment_action', 'approve_all');

    try {
        var r = await fetch('admin_ajax.php', { method: 'POST', body: fd });
        var d = await r.json();
        cmtShowMsg(d.msg || (d.ok ? '操作成功' : '操作失败'), d.ok);
        if (d.ok) {
            // 移除所有待审行
            document.querySelectorAll('#tab-pending .cmt-row').forEach(function (row) {
                row.remove();
            });
            cmtUpdatePendingBadge(0);
        }
    } catch (err) {
        cmtShowMsg('网络错误，请重试', false);
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = '✅ 全部通过'; }
    }
}

// ── 邮箱审核模式（select onchange 即时保存，无需"设置"按钮）────────────────
async function cmtUpdateEmailMode(sel) {
    var emailHash = sel.dataset.emailHash;
    var mode      = sel.value;
    var orig      = sel.disabled;
    sel.disabled  = true;

    var fd = new FormData();
    fd.append('type',           'comment');
    fd.append('comment_action', 'update_email_moderation');
    fd.append('email_hash',     emailHash);
    fd.append('mode',           mode);

    try {
        var r = await fetch('admin_ajax.php', { method: 'POST', body: fd });
        var d = await r.json();
        cmtShowMsg(d.msg || (d.ok ? '已保存' : '保存失败'), d.ok);
    } catch (err) {
        cmtShowMsg('网络错误，请重试', false);
    } finally {
        sel.disabled = orig;
    }
}

// ── 评论设置 AJAX 保存（无刷新、无白屏）────────────────────────────────────
(function () {
    var form = document.getElementById('commentSettingsForm');
    if (!form) return;
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        var btn = document.getElementById('commentSettingsBtn');
        var msg = document.getElementById('commentSettingsMsg');
        var orig = btn.textContent;
        btn.disabled = true;
        btn.textContent = '保存中…';
        msg.style.display = 'none';

        var fd = new FormData(form);
        fd.append('type', 'comment');
        fd.append('comment_action', 'save_settings');

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