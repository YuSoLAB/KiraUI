<?php
/**
 * tab_email_prefs.php — 用户中心「消息设置」标签页（邮件通知偏好）
 *
 * 挂载方式：在用户中心路由中根据 $activeTab === 'email_prefs' 引入。
 * 本文件替代或补充原有 tab_messages.php 中消息列表顶部的设置入口。
 *
 * 依赖：
 *   - $_SESSION['user_logged_in'], $_SESSION['user']
 *   - users 表中存在 notify_on_reply 字段（见 db_patch_comment_notify.sql）
 *   - comment_settings 表中存在 email_notify_enabled 字段
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
$userId     = $isLoggedIn ? (int)($_SESSION['user']['id'] ?? 0) : 0;

// ── 读取用户当前偏好 ────────────────────────────────────────────
$notifyOnReply = true; // 默认接收
$userEmail     = '';
$hasEmail      = false;

if ($isLoggedIn && $userId > 0) {
    require_once dirname(dirname(__DIR__)) . '/include/Db.php';
    $db = Db::getInstance();
    try {
        $stmt = $db->prepare("SELECT email, notify_on_reply FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $uRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($uRow) {
            $notifyOnReply = (bool)$uRow['notify_on_reply'];
            $userEmail     = $uRow['email'] ?? '';
            $hasEmail      = ($userEmail !== '');
        }
    } catch (PDOException $e) {
        // 字段不存在时静默处理
    }
}

// ── 读取全局开关（只影响提示，不影响用户设置本身）──────────────
$globalEnabled = true;
try {
    if (isset($db)) {
        $gStmt = $db->query("SELECT email_notify_enabled FROM comment_settings LIMIT 1");
        $gRow  = $gStmt ? $gStmt->fetch(PDO::FETCH_ASSOC) : null;
        if ($gRow) {
            $globalEnabled = (bool)$gRow['email_notify_enabled'];
        }
    }
} catch (PDOException $e) {}

// POST 保存接口（同页面提交）
// 注意：若用户中心是独立路由文件，POST 处理应在父页面 switch 中处理，
// 此处仅作示例内联处理，实际集成时请移至对应路由。
$saveMsg  = '';
$saveMsgT = '';
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'save_email_prefs') {
    $newVal = isset($_POST['notify_on_reply']) ? 1 : 0;
    try {
        $db = $db ?? (require_once dirname(dirname(__DIR__)) . '/include/Db.php') ? Db::getInstance() : null;
        if ($db) {
            $upd = $db->prepare("UPDATE users SET notify_on_reply = ? WHERE id = ?");
            $upd->execute([$newVal, $userId]);
            $notifyOnReply = (bool)$newVal;
            // 同步 Session
            $_SESSION['user']['notify_on_reply'] = $newVal;
            $saveMsg  = '设置已保存';
            $saveMsgT = 'success';
        }
    } catch (PDOException $e) {
        $saveMsg  = '保存失败，请重试';
        $saveMsgT = 'error';
    }
}
?>

<div id="email-prefs"
     class="tab-content <?php echo ($activeTab ?? '') === 'email_prefs' ? 'active' : ''; ?>">
<div class="profile-section">

    <h2 style="margin:0 0 1.2rem;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
        消息设置
    </h2>

    <?php if (!$isLoggedIn): ?>
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            <p>请先登录后再管理消息设置。</p>
        </div>
    <?php else: ?>

        <?php if (!$globalEnabled): ?>
        <div class="ep-banner ep-banner--warn">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            站点管理员已暂停所有邮件通知发送，你的设置会被保留，待管理员重新开启后生效。
        </div>
        <?php endif; ?>

        <?php if (!$hasEmail): ?>
        <div class="ep-banner ep-banner--info">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            你当前使用手机号注册，尚未绑定邮箱，因此无法接收邮件通知。
            如需开启，请前往「个人资料」页面绑定邮箱地址。
        </div>
        <?php endif; ?>

        <?php if ($saveMsg): ?>
        <div class="ep-banner ep-banner--<?php echo $saveMsgT; ?>" id="ep-save-msg">
            <?php echo htmlspecialchars($saveMsg); ?>
        </div>
        <?php endif; ?>

        <div class="ep-card">
            <div class="ep-card-head">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
                邮件通知
            </div>

            <div class="ep-row" id="ep-reply-row">
                <div class="ep-row-info">
                    <strong>评论被回复时通知我</strong>
                    <span>
                        当你的评论被他人回复时，系统会发送邮件至
                        <?php if ($hasEmail): ?>
                            <code class="ep-email"><?php echo htmlspecialchars($userEmail); ?></code>
                        <?php else: ?>
                            <em>（未绑定邮箱）</em>
                        <?php endif; ?>
                        告知你详情并附上跳转链接。
                    </span>
                </div>
                <label class="ep-toggle <?php echo !$hasEmail ? 'ep-toggle--disabled' : ''; ?>"
                       title="<?php echo !$hasEmail ? '请先绑定邮箱' : ''; ?>">
                    <input type="checkbox" id="epReplyToggle"
                           <?php echo $notifyOnReply ? 'checked' : ''; ?>
                           <?php echo !$hasEmail    ? 'disabled' : ''; ?>>
                    <span class="ep-toggle-track"><span class="ep-toggle-thumb"></span></span>
                </label>
            </div>

            <div class="ep-footer">
                <button type="button" id="ep-save-btn"
                        class="ep-btn ep-btn--primary"
                        <?php echo !$hasEmail ? 'disabled' : ''; ?>>
                    保存设置
                </button>
                <span id="ep-ajax-msg" class="ep-ajax-msg"></span>
            </div>
        </div>

        <p class="ep-hint">
            💡 站内消息通知（无需邮箱）可在「<strong>我的消息</strong>」标签页中查看。
        </p>

    <?php endif; ?>

</div>
</div><!-- /#email-prefs -->

<style>
/* ── 消息设置面板样式 ── */
.ep-banner {
    display: flex; align-items: flex-start; gap: .55rem;
    padding: .75rem 1rem; border-radius: 8px;
    font-size: .83rem; line-height: 1.55; margin-bottom: 1rem;
}
.ep-banner--warn  { background: #fff8e6; border: 1px solid #f0d080; color: #7a5500; }
.ep-banner--info  { background: #eef5ff; border: 1px solid #b8d0f8; color: #2a5db0; }
.ep-banner--success { background: #f0fff4; border: 1px solid #a0ddb8; color: #276749; }
.ep-banner--error   { background: #fff0f0; border: 1px solid #f0b0b0; color: #c53030; }

.ep-card {
    border: 1px solid rgba(155,140,255,.2);
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 1rem;
}
.ep-card-head {
    display: flex; align-items: center; gap: .55rem;
    padding: .85rem 1.1rem;
    font-weight: 700; font-size: .9rem;
    background: rgba(108,93,251,.05);
    border-bottom: 1px solid rgba(155,140,255,.15);
    color: var(--text, #1a1a2e);
}
.ep-row {
    display: flex; align-items: center; justify-content: space-between; gap: 1rem;
    padding: 1rem 1.1rem;
    border-bottom: 1px solid rgba(155,140,255,.08);
}
.ep-row:last-of-type { border-bottom: none; }
.ep-row-info { flex: 1; }
.ep-row-info strong { display: block; font-size: .88rem; color: var(--text, #1a1a2e); margin-bottom: .2rem; }
.ep-row-info span   { font-size: .78rem; color: var(--sub, #888); line-height: 1.5; }
.ep-row-info code.ep-email {
    font-family: monospace; font-size: .8rem;
    background: rgba(108,93,251,.08); padding: .1rem .35rem;
    border-radius: 4px; color: #5b4ef8;
}

/* Toggle 开关 */
.ep-toggle { display: inline-flex; align-items: center; cursor: pointer; flex-shrink: 0; }
.ep-toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
.ep-toggle-track {
    width: 44px; height: 24px; border-radius: 12px;
    background: #ccc; position: relative; transition: background .25s;
}
.ep-toggle input:checked + .ep-toggle-track { background: #5b4ef8; }
.ep-toggle-thumb {
    position: absolute; top: 3px; left: 3px;
    width: 18px; height: 18px; border-radius: 50%; background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,.3); transition: left .25s;
}
.ep-toggle input:checked + .ep-toggle-track .ep-toggle-thumb { left: 23px; }
.ep-toggle--disabled { opacity: .45; cursor: not-allowed; }
.ep-toggle--disabled .ep-toggle-track { background: #bbb; }

/* Footer */
.ep-footer {
    display: flex; align-items: center; gap: .8rem;
    padding: .85rem 1.1rem;
    background: rgba(248,248,252,.6);
    border-top: 1px solid rgba(155,140,255,.1);
}
.ep-btn {
    padding: .45rem 1.2rem; border-radius: 7px; font-size: .85rem;
    font-weight: 600; cursor: pointer; border: none; transition: opacity .2s;
}
.ep-btn--primary { background: #5b4ef8; color: #fff; }
.ep-btn--primary:hover { opacity: .88; }
.ep-btn:disabled { opacity: .45; cursor: not-allowed; }
.ep-ajax-msg { font-size: .82rem; }
.ep-ajax-msg.success { color: #2a9d5c; }
.ep-ajax-msg.error   { color: #e53e3e; }

.ep-hint { font-size: .78rem; color: var(--sub, #999); line-height: 1.55; margin-top: .5rem; }

/* 暗色模式 */
body.dark-mode .ep-card            { border-color: rgba(176,160,255,.2); }
body.dark-mode .ep-card-head       { background: rgba(108,93,251,.1); color: #eaeaea; }
body.dark-mode .ep-row-info strong { color: #eaeaea; }
body.dark-mode .ep-footer          { background: rgba(20,20,36,.5); }
body.dark-mode .ep-banner--warn  { background: #2e2510; border-color: #6b4e00; color: #f0c060; }
body.dark-mode .ep-banner--info  { background: #101e36; border-color: #2a5090; color: #7ab0f8; }
body.dark-mode .ep-toggle-track  { background: #444; }
body.dark-mode code.ep-email     { background: rgba(108,93,251,.18); }
</style>

<script>
(function () {
    var toggle  = document.getElementById('epReplyToggle');
    var saveBtn = document.getElementById('ep-save-btn');
    var msgEl   = document.getElementById('ep-ajax-msg');

    if (!toggle || !saveBtn) return;

    function showMsg(type, text) {
        if (!msgEl) return;
        msgEl.className = 'ep-ajax-msg ' + type;
        msgEl.textContent = text;
        clearTimeout(msgEl._t);
        msgEl._t = setTimeout(function () { msgEl.textContent = ''; msgEl.className = 'ep-ajax-msg'; }, 3500);
    }

    saveBtn.addEventListener('click', async function () {
        var orig = saveBtn.textContent;
        saveBtn.disabled = true;
        saveBtn.textContent = '保存中…';

        var fd = new FormData();
        fd.append('action', 'save_email_prefs');
        if (toggle.checked) fd.append('notify_on_reply', '1');

        try {
            var res  = await fetch(location.pathname, { method: 'POST', body: fd });
            var text = await res.text();
            // 若父页面返回 JSON（AJAX 模式）则解析，否则视为成功（全页刷新不到这里）
            try {
                var data = JSON.parse(text);
                showMsg(data.ok ? 'success' : 'error', data.msg || (data.ok ? '已保存' : '保存失败'));
            } catch (_) {
                showMsg('success', '设置已保存');
            }
        } catch (e) {
            showMsg('error', '网络错误：' + e.message);
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = orig;
        }
    });
})();
</script>