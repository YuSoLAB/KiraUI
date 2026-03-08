<?php
// 管理员公告管理
require_once dirname(__DIR__) . '/include/Config.php';
$config = Config::getInstance();
$announcementConfig = [
    'content' => $config->get('announcement_content', ''),
    'enabled' => $config->get('announcement_enabled', '0') === '1',
];
// AJAX 端点路径（相对于浏览器当前 URL 所在目录）
$ajaxUrl = 'admin_ajax.php';
?>
<style>
/* ── announcement ── */
.ann-code { width:100%; padding:.55rem .72rem; border:1px solid var(--admin-border,rgba(155,140,255,.4)); border-radius:8px; font-family:Consolas,Monaco,monospace; font-size:.84rem; resize:vertical; background:var(--admin-card,#fff); color:inherit; box-sizing:border-box; transition:border-color .15s; }
.ann-code:focus { outline:none; border-color:#6c5dfb; box-shadow:0 0 0 3px rgba(108,93,251,.1); }
.ann-preview { border:1px solid rgba(155,140,255,.25); border-radius:10px; padding:1rem 1.1rem; margin-top:.8rem; background:rgba(155,140,255,.03); min-height:48px; font-size:.9rem; line-height:1.7; }
.ann-toggle  { display:inline-flex; align-items:center; gap:.5rem; cursor:pointer; font-size:.9rem; user-select:none; }
.ann-toggle input { accent-color:#6c5dfb; width:15px; height:15px; }
body.dark-mode .ann-code { background:#2a2a42aa; border-color:var(--dark-admin-border); color:var(--dark-text,#eaeaea); }
body.dark-mode .ann-code:focus { border-color:var(--dark-vio,#b096ff); box-shadow:0 0 0 3px rgba(176,160,255,.12); }
body.dark-mode .ann-preview { background:rgba(176,160,255,.04); border-color:rgba(176,160,255,.2); }

/* ── 通用 AJAX 消息条 ── */
.ajax-msg { display:none; padding:.6rem 1rem; border-radius:6px; font-size:.875rem; margin-bottom:.8rem; }
.ajax-msg.success { background:#f0fff4; border-left:4px solid #38a169; color:#276749; }
.ajax-msg.error   { background:#fff0f0; border-left:4px solid #e53e3e; color:#c53030; }
body.dark-mode .ajax-msg.success { background:#1a3a2a; color:#9ae6b4; border-color:#38a169; }
body.dark-mode .ajax-msg.error   { background:#3a1a1a; color:#fc8181; border-color:#e53e3e; }
</style>

<div class="admin-section">

    <div class="mhdr">
        <div>
            <h2 class="mhdr-title">📢 弹窗公告管理</h2>
            <p class="mhdr-sub">配置网站首页显示的弹窗公告，支持 HTML 标签进行格式化。</p>
        </div>
    </div>

    <div class="mtip">💡 公告内容支持 HTML，例如 <code>&lt;strong&gt;加粗&lt;/strong&gt;</code>、<code>&lt;br&gt;</code> 换行等标签。启用后将在访客首次访问时弹出。</div>

    <div id="ann-msg" class="ajax-msg"></div>

    <div class="mbuilder" style="padding:1.2rem; margin-bottom:1rem;">
        <form id="ann-form">
            <!-- 启用开关 -->
            <div class="mfg" style="margin-bottom:1rem;">
                <label class="ann-toggle">
                    <input type="checkbox" name="announcement_enabled" id="ann-enabled"
                           <?php echo $announcementConfig['enabled'] ? 'checked' : ''; ?>>
                    启用弹窗公告
                </label>
            </div>

            <!-- 内容编辑 -->
            <div class="mfg" style="margin-bottom:1rem;">
                <label style="font-size:.83rem;font-weight:700;color:var(--sub,#666);margin-bottom:.3rem;display:block;">公告内容 (支持 HTML)</label>
                <textarea class="ann-code" id="announcement_content" name="announcement_content" rows="8"><?php echo htmlspecialchars($announcementConfig['content']); ?></textarea>
                <small style="font-size:.75rem;color:var(--sub,#999);margin-top:.3rem;display:block;">可使用 HTML 标签进行排版，如 &lt;strong&gt;、&lt;a&gt;、&lt;ul&gt; 等</small>
            </div>

            <button type="submit" class="btn btn-primary" id="ann-submit-btn">💾 保存配置</button>
        </form>
    </div>

    <!-- 实时预览 -->
    <div class="mbuilder" style="padding:1.2rem;">
        <p style="margin:0 0 .4rem; font-size:.83rem; font-weight:700; color:#6c5dfb;">👁 公告预览</p>
        <div class="ann-preview" id="ann-preview-box">
            <?php echo $announcementConfig['content']; ?>
        </div>
    </div>

</div>

<script>
(function () {
    const AJAX_URL = <?php echo json_encode($ajaxUrl); ?>;

    // 实时预览
    const ta  = document.getElementById('announcement_content');
    const box = document.getElementById('ann-preview-box');
    if (ta && box) {
        ta.addEventListener('input', function () { box.innerHTML = ta.value; });
    }

    // 消息显示工具
    function showMsg(type, text) {
        const el = document.getElementById('ann-msg');
        if (!el) return;
        el.className = 'ajax-msg ' + type;
        el.textContent = text;
        el.style.display = 'block';
        // 5 秒后自动隐藏
        clearTimeout(el._hideTimer);
        el._hideTimer = setTimeout(function () { el.style.display = 'none'; }, 5000);
    }

    // AJAX 表单提交（不刷新页面，保留深色模式等所有 JS 状态）
    const form = document.getElementById('ann-form');
    const btn  = document.getElementById('ann-submit-btn');
    if (form) {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            const origText  = btn.textContent;
            btn.disabled    = true;
            btn.textContent = '保存中…';

            try {
                const fd = new FormData(form);
                fd.append('type', 'config');
                fd.append('config_action', 'save_announcement');
                // checkbox 未勾选时 FormData 不含该字段，后端 empty() 判断为 '0'

                const res  = await fetch(AJAX_URL, { method: 'POST', body: fd });
                const data = await res.json();
                showMsg(data.ok ? 'success' : 'error', data.msg || (data.ok ? '保存成功' : '保存失败'));
            } catch (err) {
                showMsg('error', '网络错误，请重试：' + err.message);
            } finally {
                btn.disabled    = false;
                btn.textContent = origText;
            }
        });
    }
})();
</script>