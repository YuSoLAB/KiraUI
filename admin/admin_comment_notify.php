<?php
/**
 * admin_comment_notify.php — 后台「评论通知设置」面板
 *
 * 挂载方式：在后台路由中以 include 方式引入（page=comment_notify）
 * 依赖：comment_functions.php、admin_ajax.php（type=comment_notify）
 */

require_once __DIR__ . '/../include/Db.php';
require_once __DIR__ . '/../include/Config.php';

$db  = Db::getInstance();
$cfg = Config::getInstance();

// ── 读取当前通知设置 ────────────────────────────────────────────
$notifyRow = null;
try {
    $stmt      = $db->query("SELECT * FROM comment_settings LIMIT 1");
    $notifyRow = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
} catch (PDOException $e) {}

$emailNotifyEnabled = isset($notifyRow['email_notify_enabled']) ? (bool)$notifyRow['email_notify_enabled'] : true;
$notifyAdmin        = isset($notifyRow['notify_admin'])        ? (bool)$notifyRow['notify_admin']        : true;
$notifyGuestReply   = isset($notifyRow['notify_guest_reply'])  ? (bool)$notifyRow['notify_guest_reply']  : true;

// SMTP 是否已启用（用于提示）
$smtpEnabled = $cfg->get('smtp_enabled', '0') === '1'
    && $cfg->get('smtp_host', '') !== ''
    && $cfg->get('smtp_username', '') !== '';

$ajaxUrl = 'admin_ajax.php';
?>
<style>
/* ── 通知设置面板专属样式 ── */
.cn-section { margin-bottom: 1.6rem; }
.cn-section-title {
    font-size: .82rem;
    font-weight: 700;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: var(--sub, #999);
    margin-bottom: .7rem;
    padding-bottom: .4rem;
    border-bottom: 1px solid rgba(155,140,255,.18);
}
.cn-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: .9rem 0;
    border-bottom: 1px solid rgba(155,140,255,.08);
}
.cn-row:last-child { border-bottom: none; }
.cn-label { flex: 1; }
.cn-label strong { display: block; font-size: .9rem; color: var(--text, #1a1a2e); margin-bottom: .2rem; }
.cn-label span   { font-size: .78rem; color: var(--sub, #888); line-height: 1.5; }

/* 开关 */
.cn-toggle { display: inline-flex; align-items: center; cursor: pointer; flex-shrink: 0; }
.cn-toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
.cn-toggle-track {
    width: 44px; height: 24px; border-radius: 12px; background: #ccc;
    position: relative; transition: background .25s; flex-shrink: 0;
}
.cn-toggle input:checked + .cn-toggle-track { background: #5b4ef8; }
.cn-toggle-thumb {
    position: absolute; top: 3px; left: 3px;
    width: 18px; height: 18px; border-radius: 50%; background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,.3); transition: left .25s;
}
.cn-toggle input:checked + .cn-toggle-track .cn-toggle-thumb { left: 23px; }
body.dark-mode .cn-toggle-track { background: #444; }
body.dark-mode .cn-label strong { color: #eaeaea; }

/* 主开关禁用态 */
.cn-rows-wrapper {
    transition: opacity .25s;
    pointer-events: auto;
}
.cn-rows-wrapper.disabled {
    opacity: .45;
    pointer-events: none;
}

/* SMTP 未配置提示 */
.cn-smtp-warn {
    display: flex; align-items: flex-start; gap: .6rem;
    background: #fff8e6; border: 1px solid #f0d080; border-radius: 8px;
    padding: .75rem 1rem; font-size: .82rem; color: #7a5c00; margin-bottom: 1.2rem;
    line-height: 1.5;
}
.cn-smtp-warn svg { flex-shrink: 0; margin-top: 1px; }
body.dark-mode .cn-smtp-warn { background: #2e2510; border-color: #6b4e00; color: #f0c060; }

/* 保存按钮 & 消息 */
.cn-footer { margin-top: 1.4rem; display: flex; align-items: center; gap: 1rem; }
.ajax-msg-cn { font-size: .85rem; display: none; }
.ajax-msg-cn.success { color: #2a9d5c; }
.ajax-msg-cn.error   { color: #e53e3e; }
</style>

<div class="admin-section">
    <div class="mhdr">
        <div>
            <h2 class="mhdr-title">🔔 评论邮件通知设置</h2>
            <p class="mhdr-sub">配置评论发生时的邮件通知行为。用户个人是否接收通知由其在「用户中心 → 消息设置」中自行管理。</p>
        </div>
    </div>

    <?php if (!$smtpEnabled): ?>
    <div class="cn-smtp-warn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <span>
            SMTP 邮件服务尚未配置或未启用，邮件通知将无法发出。
            请先前往 <strong>系统设置 → SMTP 邮件配置</strong> 完成设置。
        </span>
    </div>
    <?php endif; ?>

    <div id="cn-msg" class="ajax-msg-cn"></div>

    <div class="mbuilder" style="padding: 1.2rem 1.4rem;">

        <!-- ── 主开关 ── -->
        <div class="cn-section">
            <div class="cn-row" style="padding-top:0;">
                <div class="cn-label">
                    <strong>📧 邮件通知总开关</strong>
                    <span>关闭后，所有评论相关的邮件通知将全部停止发送（包括管理员通知和用户回复通知），单个开关的配置保留。</span>
                </div>
                <label class="cn-toggle" id="masterToggleLabel">
                    <input type="checkbox" id="masterToggle"
                           <?php echo $emailNotifyEnabled ? 'checked' : ''; ?>>
                    <span class="cn-toggle-track"><span class="cn-toggle-thumb"></span></span>
                </label>
            </div>
        </div>

        <hr style="border:none;border-top:1px solid rgba(155,140,255,.15);margin:0 0 1.2rem;">

        <!-- ── 子项（受主开关控制）── -->
        <div class="cn-rows-wrapper <?php echo $emailNotifyEnabled ? '' : 'disabled'; ?>" id="cnSubRows">

            <!-- 管理员通知 -->
            <div class="cn-section">
                <p class="cn-section-title">管理员通知</p>
                <div class="cn-row">
                    <div class="cn-label">
                        <strong>新评论提交时通知管理员</strong>
                        <span>每当有新评论（包括需要审核的评论）提交时，向所有角色为「管理员」且已设置邮箱的账号发送通知邮件，提醒前往后台审核。</span>
                    </div>
                    <label class="cn-toggle">
                        <input type="checkbox" class="cn-sub-toggle" data-key="notify_admin"
                               <?php echo $notifyAdmin ? 'checked' : ''; ?>>
                        <span class="cn-toggle-track"><span class="cn-toggle-thumb"></span></span>
                    </label>
                </div>
            </div>

            <!-- 用户回复通知 -->
            <div class="cn-section">
                <p class="cn-section-title">用户回复通知</p>
                <div class="cn-row">
                    <div class="cn-label">
                        <strong>向已登录用户发送回复通知</strong>
                        <span>当已注册用户的评论被回复时，向其发送邮件通知（前提是该用户已绑定邮箱，且未在个人设置中关闭此通知）。仅使用手机号注册、尚未绑定邮箱的用户将自动跳过邮件发送。</span>
                    </div>
                    <label class="cn-toggle">
                        <input type="checkbox" class="cn-sub-toggle" data-key="notify_user_reply"
                               checked disabled>
                        <span class="cn-toggle-track"><span class="cn-toggle-thumb"></span></span>
                    </label>
                </div>
                <p style="font-size:.75rem;color:var(--sub,#999);margin:.2rem 0 .8rem 0;padding-left:2px;">
                    ℹ️ 用户注册账号后的回复通知由总开关控制；用户可在「用户中心」自行选择是否接收。此开关不可单独关闭。
                </p>

                <div class="cn-row">
                    <div class="cn-label">
                        <strong>向游客（未注册用户）发送回复通知</strong>
                        <span>当游客评论被回复时，向其填写的邮箱发送通知。游客无法自主管理订阅，关闭此项后将不再向游客发送任何邮件。</span>
                    </div>
                    <label class="cn-toggle">
                        <input type="checkbox" class="cn-sub-toggle" data-key="notify_guest_reply"
                               <?php echo $notifyGuestReply ? 'checked' : ''; ?>>
                        <span class="cn-toggle-track"><span class="cn-toggle-thumb"></span></span>
                    </label>
                </div>
            </div>

        </div><!-- /#cnSubRows -->

        <div class="cn-footer">
            <button type="button" id="cn-save-btn" class="btn btn-primary">💾 保存通知设置</button>
            <span id="cn-msg-inline" class="ajax-msg-cn"></span>
        </div>

    </div><!-- /.mbuilder -->
</div><!-- /.admin-section -->

<script>
(function () {
    var AJAX_URL   = <?php echo json_encode($ajaxUrl); ?>;
    var masterEl   = document.getElementById('masterToggle');
    var subRowsEl  = document.getElementById('cnSubRows');
    var saveBtn    = document.getElementById('cn-save-btn');
    var msgEl      = document.getElementById('cn-msg-inline');

    /* 主开关联动子项 */
    masterEl && masterEl.addEventListener('change', function () {
        subRowsEl.classList.toggle('disabled', !this.checked);
    });

    /* 显示操作消息 */
    function showMsg(type, text) {
        if (!msgEl) return;
        msgEl.className = 'ajax-msg-cn ' + type;
        msgEl.textContent = text;
        msgEl.style.display = 'inline';
        clearTimeout(msgEl._t);
        msgEl._t = setTimeout(function () { msgEl.style.display = 'none'; }, 4000);
    }

    /* 保存 */
    saveBtn && saveBtn.addEventListener('click', async function () {
        var orig = saveBtn.textContent;
        saveBtn.disabled = true;
        saveBtn.textContent = '保存中…';

        var payload = new FormData();
        payload.append('type',           'comment_notify');
        payload.append('email_notify_enabled', masterEl.checked ? '1' : '0');

        document.querySelectorAll('.cn-sub-toggle:not([disabled])').forEach(function (el) {
            payload.append(el.dataset.key, el.checked ? '1' : '0');
        });

        try {
            var res  = await fetch(AJAX_URL, { method: 'POST', body: payload });
            var data = await res.json();
            showMsg(data.ok ? 'success' : 'error', data.msg || (data.ok ? '已保存' : '保存失败'));
        } catch (e) {
            showMsg('error', '网络错误：' + e.message);
        } finally {
            saveBtn.disabled    = false;
            saveBtn.textContent = orig;
        }
    });
})();
</script>