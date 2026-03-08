<?php
// 网站信息配置页面
require_once dirname(__DIR__) . '/include/Config.php';
$config = Config::getInstance();
$siteConfig = [
    'badge_text'   => $config->get('badge_text', '📝 KiraUI'),
    'site_title'   => $config->get('site_title', '测试网站'),
    'welcome_text' => $config->get('welcome_text', '这是一个网站'),
    'html_title'   => $config->get('html_title', 'YuSoLAB'),
];

$ajaxUrl = 'admin_ajax.php';

$imgDir  = ROOT_DIR . '/img/';
$banners = [];
if (file_exists($imgDir)) {
    $banners = array_map('basename', glob($imgDir . 'banner*.png') ?: []);
}
$hasLogo = file_exists($imgDir . 'logo.ico');
?>
<style>
/* ── siteinfo ── */
.si-preview-grid { display:flex; flex-wrap:wrap; gap:1.2rem; margin-top:.8rem; }
.si-img-card { border:1px solid rgba(155,140,255,.25); border-radius:10px; padding:.75rem 1rem; background:rgba(155,140,255,.04); text-align:center; }
.si-img-card p { margin:.4rem 0 0; font-size:.78rem; color:var(--sub,#888); }
.si-img-card img { max-height:120px; max-width:180px; border-radius:6px; }
body.dark-mode .si-img-card { background:rgba(176,160,255,.06); border-color:rgba(176,160,255,.2); }
body.dark-mode input[type=text],
body.dark-mode input[type=email],
body.dark-mode input[type=number],
body.dark-mode input[type=password],
body.dark-mode input[type=file],
body.dark-mode textarea,
body.dark-mode select {
    background: #1e1e32 !important;
    color: #eaeaea !important;
    border-color: rgba(176,160,255,.35) !important;
}
body.dark-mode input::placeholder,
body.dark-mode textarea::placeholder { color: #6b6b8a !important; }

.ajax-msg { display:none; padding:.6rem 1rem; border-radius:6px; font-size:.875rem; margin-bottom:.8rem; }
.ajax-msg.success { background:#f0fff4; border-left:4px solid #38a169; color:#276749; }
.ajax-msg.error   { background:#fff0f0; border-left:4px solid #e53e3e; color:#c53030; }
body.dark-mode .ajax-msg.success { background:#1a3a2a; color:#9ae6b4; border-color:#38a169; }
body.dark-mode .ajax-msg.error   { background:#3a1a1a; color:#fc8181; border-color:#e53e3e; }
</style>

<div class="admin-section">

    <div class="mhdr">
        <div>
            <h2 class="mhdr-title">🌐 网站信息配置</h2>
            <p class="mhdr-sub">配置网站基本信息、Logo 和背景图片。</p>
        </div>
    </div>

    <div id="si-msg" class="ajax-msg"></div>

    <!-- 基本信息 -->
    <div class="mbuilder" style="padding:1.2rem; margin-bottom:1rem;">
        <p style="margin:0 0 1rem; font-size:.83rem; font-weight:700; color:#6c5dfb;">📋 基本信息</p>
        <form id="si-info-form">
            <div class="mfg" style="margin-bottom:.75rem;">
                <label>Badge 文本</label>
                <input type="text" name="badge_text" value="<?php echo htmlspecialchars($siteConfig['badge_text']); ?>" required>
            </div>
            <div class="mfg" style="margin-bottom:.75rem;">
                <label>网站标题</label>
                <input type="text" name="site_title" value="<?php echo htmlspecialchars($siteConfig['site_title']); ?>" required>
            </div>
            <div class="mfg" style="margin-bottom:.75rem;">
                <label>欢迎词</label>
                <textarea name="welcome_text" rows="3" style="padding:.48rem .72rem;border:1px solid var(--admin-border,rgba(155,140,255,.4));border-radius:8px;font-size:.9rem;width:100%;box-sizing:border-box;resize:vertical;background:var(--admin-card,#fff);color:inherit;"><?php echo htmlspecialchars($siteConfig['welcome_text']); ?></textarea>
            </div>
            <div class="mfg" style="margin-bottom:1rem;">
                <label>HTML 页面标题</label>
                <input type="text" name="html_title" value="<?php echo htmlspecialchars($siteConfig['html_title']); ?>" required>
                <small style="color:var(--sub,#999);font-size:.76rem;">此标题将显示在浏览器标题栏中</small>
            </div>
            <button type="submit" class="btn btn-primary" id="si-info-btn">💾 保存网站信息</button>
        </form>
    </div>

    <!-- 图片上传 -->
    <div class="mbuilder" style="padding:1.2rem; margin-bottom:1rem;">
        <p style="margin:0 0 1rem; font-size:.83rem; font-weight:700; color:#6c5dfb;">🖼️ 图片上传</p>
        <form id="si-upload-form">
            <div class="mfg" style="margin-bottom:.75rem;">
                <label>网站 Logo <small style="font-weight:normal;color:var(--sub,#999);">（必须为 .ico 格式，自动命名为 logo.ico）</small></label>
                <input type="file" name="logo" accept=".ico" style="padding:.4rem;border:1px solid var(--admin-border,rgba(155,140,255,.4));border-radius:8px;width:100%;box-sizing:border-box;background:var(--admin-card,#fff);color:inherit;">
                <?php if ($hasLogo): ?>
                    <small style="color:#856404;font-size:.76rem;">⚠️ 已有 Logo，上传将覆盖现有文件</small>
                <?php endif; ?>
            </div>
            <div class="mfg" style="margin-bottom:1rem;">
                <label>背景图片 <small style="font-weight:normal;color:var(--sub,#999);">（支持 png/jpg/jpeg/gif，自动命名为 banner1.png, banner2.png…）</small></label>
                <input type="file" name="banner" accept="image/*" style="padding:.4rem;border:1px solid var(--admin-border,rgba(155,140,255,.4));border-radius:8px;width:100%;box-sizing:border-box;background:var(--admin-card,#fff);color:inherit;">
            </div>
            <button type="submit" class="btn btn-primary" id="si-upload-btn">⬆️ 上传图片</button>
        </form>
    </div>

    <!-- 现有图片 -->
    <div class="mbuilder" style="padding:1.2rem;" id="si-img-gallery"
         <?php echo (empty($banners) && !$hasLogo) ? 'style="display:none;padding:1.2rem;"' : ''; ?>>
        <p style="margin:0 0 .8rem; font-size:.83rem; font-weight:700; color:#6c5dfb;">📁 现有图片</p>
        <div class="si-preview-grid" id="si-img-grid">
            <?php if ($hasLogo): ?>
            <div class="si-img-card">
                <img src="../img/logo.ico" alt="Logo">
                <p>logo.ico</p>
            </div>
            <?php endif; ?>
            <?php foreach ($banners as $banner): ?>
            <div class="si-img-card">
                <img src="../img/<?php echo htmlspecialchars($banner); ?>" alt="<?php echo htmlspecialchars($banner); ?>">
                <p><?php echo htmlspecialchars($banner); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<script>
(function () {
    const AJAX_URL = <?php echo json_encode($ajaxUrl); ?>;

    function showMsg(type, text) {
        const el = document.getElementById('si-msg');
        if (!el) return;
        el.className = 'ajax-msg ' + type;
        el.textContent = text;
        el.style.display = 'block';
        clearTimeout(el._t);
        el._t = setTimeout(function () { el.style.display = 'none'; }, 5000);
    }

    function bindAjaxForm(formId, btnId, extraFields, onSuccess) {
        const form = document.getElementById(formId);
        const btn  = document.getElementById(btnId);
        if (!form || !btn) return;

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const orig = btn.textContent;
            btn.disabled = true;
            btn.textContent = '保存中…';
            try {
                const fd = new FormData(form);
                Object.entries(extraFields).forEach(([k, v]) => fd.append(k, v));
                const res  = await fetch(AJAX_URL, { method: 'POST', body: fd });
                const data = await res.json();
                showMsg(data.ok ? 'success' : 'error', data.msg || (data.ok ? '操作成功' : '操作失败'));
                if (data.ok && typeof onSuccess === 'function') onSuccess(data);
            } catch (err) {
                showMsg('error', '网络错误，请重试：' + err.message);
            } finally {
                btn.disabled = false;
                btn.textContent = orig;
            }
        });
    }

    // 基本信息表单
    bindAjaxForm('si-info-form', 'si-info-btn', {
        type: 'config',
        config_action: 'save_siteinfo'
    });

    // 图片上传表单（上传成功后在画廊追加新图）
    bindAjaxForm('si-upload-form', 'si-upload-btn', {
        type: 'config',
        config_action: 'upload_image'
    }, function () {
        // 简单地刷新图库区域，只需重载页面图库部分
        // 由于是局部刷新，直接让用户看到消息提示即可；
        // 如需立即展示新图，可发一次 fetch 获取文件列表，此处保持简单：
        const gallery = document.getElementById('si-img-gallery');
        if (gallery) gallery.style.display = '';
    });
})();
</script>