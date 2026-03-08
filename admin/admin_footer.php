<?php
require_once dirname(__DIR__) . '/include/Config.php';
$config = Config::getInstance();
$footerConfig = [
    'content' => $config->get('footer_content', ''),
    'css'     => $config->get('footer_css', ''),
    'js'      => $config->get('footer_js', ''),
];
$ajaxUrl = 'admin_ajax.php';
?>
<style>
/* ── footer editor ── */
.ftr-code { width:100%; padding:.55rem .72rem; border:1px solid var(--admin-border,rgba(155,140,255,.4)); border-radius:8px; font-family:Consolas,Monaco,monospace; font-size:.84rem; resize:vertical; background:var(--admin-card,#fff); color:inherit; box-sizing:border-box; transition:border-color .15s; }
.ftr-code:focus { outline:none; border-color:#6c5dfb; box-shadow:0 0 0 3px rgba(108,93,251,.1); }
.ftr-preview { border:1px solid rgba(155,140,255,.25); border-radius:10px; padding:1rem; margin-top:.8rem; background:rgba(155,140,255,.03); min-height:60px; }
.ftr-section-label { font-size:.83rem; font-weight:700; color:var(--sub,#666); margin-bottom:.3rem; display:block; }
body.dark-mode .ftr-code { background:#2a2a42aa; border-color:var(--dark-admin-border); color:var(--dark-text,#eaeaea); }
body.dark-mode .ftr-code:focus { border-color:var(--dark-vio,#b096ff); box-shadow:0 0 0 3px rgba(176,160,255,.12); }
body.dark-mode .ftr-preview { background:rgba(176,160,255,.04); border-color:rgba(176,160,255,.2); }

.ajax-msg { display:none; padding:.6rem 1rem; border-radius:6px; font-size:.875rem; margin-bottom:.8rem; }
.ajax-msg.success { background:#f0fff4; border-left:4px solid #38a169; color:#276749; }
.ajax-msg.error   { background:#fff0f0; border-left:4px solid #e53e3e; color:#c53030; }
body.dark-mode .ajax-msg.success { background:#1a3a2a; color:#9ae6b4; border-color:#38a169; }
body.dark-mode .ajax-msg.error   { background:#3a1a1a; color:#fc8181; border-color:#e53e3e; }
</style>

<div class="admin-section">

    <div class="mhdr">
        <div>
            <h2 class="mhdr-title">🦶 页脚管理</h2>
            <p class="mhdr-sub">配置网站底部内容，支持 HTML、CSS 和 JavaScript。</p>
        </div>
    </div>

    <div class="mtip">💡 页脚内容会渲染在每个页面底部，可使用 HTML 标签。CSS/JS 仅对页脚区域生效，修改后保存并刷新前台页面查看效果。</div>

    <div id="ftr-msg" class="ajax-msg"></div>

    <div class="mbuilder" style="padding:1.2rem; margin-bottom:1rem;">
        <form id="ftr-form">
            <div class="mfg" style="margin-bottom:.9rem;">
                <label class="ftr-section-label">页脚内容 (HTML)</label>
                <textarea class="ftr-code" name="footer_content" id="ftr-content" rows="8"><?php echo htmlspecialchars($footerConfig['content']); ?></textarea>
            </div>

            <div class="mfg" style="margin-bottom:.9rem;">
                <label class="ftr-section-label">页脚样式 (CSS)</label>
                <textarea class="ftr-code" name="footer_css" id="ftr-css" rows="8"><?php echo htmlspecialchars($footerConfig['css']); ?></textarea>
            </div>

            <div class="mfg" style="margin-bottom:1rem;">
                <label class="ftr-section-label">页脚脚本 (JavaScript)</label>
                <textarea class="ftr-code" name="footer_js" id="ftr-js" rows="8"><?php echo htmlspecialchars($footerConfig['js']); ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary" id="ftr-submit-btn">💾 保存配置</button>
        </form>
    </div>

    <!-- 预览（纯本地渲染，不依赖服务器） -->
    <div class="mbuilder" style="padding:1.2rem;">
        <p style="margin:0 0 .5rem; font-size:.83rem; font-weight:700; color:#6c5dfb;">👁 实时预览</p>
        <div class="ftr-preview" id="ftr-preview-box">
            <style id="ftr-preview-style"><?php echo $footerConfig['css']; ?></style>
            <div id="ftr-preview-content"><?php echo $footerConfig['content']; ?></div>
        </div>
    </div>

</div>

<script>
(function () {
    const AJAX_URL = <?php echo json_encode($ajaxUrl); ?>;

    // 实时预览同步
    function syncPreview() {
        const content = document.getElementById('ftr-content');
        const css     = document.getElementById('ftr-css');
        const previewContent = document.getElementById('ftr-preview-content');
        const previewStyle   = document.getElementById('ftr-preview-style');

        if (content && previewContent) {
            content.addEventListener('input', function () { previewContent.innerHTML = content.value; });
        }
        if (css && previewStyle) {
            css.addEventListener('input', function () { previewStyle.textContent = css.value; });
        }
        // JS 预览不自动执行（安全考虑），保存后刷新前台即可看到效果
    }
    syncPreview();

    // 消息工具
    function showMsg(type, text) {
        const el = document.getElementById('ftr-msg');
        if (!el) return;
        el.className = 'ajax-msg ' + type;
        el.textContent = text;
        el.style.display = 'block';
        clearTimeout(el._t);
        el._t = setTimeout(function () { el.style.display = 'none'; }, 5000);
    }

    // AJAX 提交
    const form = document.getElementById('ftr-form');
    const btn  = document.getElementById('ftr-submit-btn');
    if (form) {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const orig = btn.textContent;
            btn.disabled = true;
            btn.textContent = '保存中…';
            try {
                const fd = new FormData(form);
                fd.append('type', 'config');
                fd.append('config_action', 'save_footer');
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
    }
})();
</script>