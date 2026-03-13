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
// 兼容旧 logo.ico 及新 logo.png
$hasLogo    = file_exists($imgDir . 'logo.png') || file_exists($imgDir . 'logo.ico');
$logoFile   = file_exists($imgDir . 'logo.png') ? 'logo.png' : (file_exists($imgDir . 'logo.ico') ? 'logo.ico' : null);
// 兼容 favicon.ico / favicon.png / favicon.svg
$faviconFile = null;
foreach (['favicon.ico', 'favicon.png', 'favicon.svg'] as $_f) {
    if (file_exists($imgDir . $_f)) { $faviconFile = $_f; break; }
}
$hasFavicon = $faviconFile !== null;
?>
<style>
/* ── siteinfo ── */

/* 视图切换按钮 */
.si-view-toggle { display:flex; gap:.4rem; }
.si-view-btn {
    padding:.3rem .55rem; border-radius:6px; border:1px solid rgba(108,93,251,.3);
    background:transparent; cursor:pointer; font-size:.82rem; color:var(--sub,#888);
    transition:background .15s, color .15s, border-color .15s;
    line-height:1;
}
.si-view-btn.active, .si-view-btn:hover {
    background:rgba(108,93,251,.12); color:#6c5dfb; border-color:rgba(108,93,251,.5);
}

/* ── 网格模式（默认）── */
.si-preview-grid {
    display:grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap:1rem; margin-top:.8rem;
}
.si-img-card {
    border:1px solid rgba(155,140,255,.25); border-radius:10px;
    padding:.75rem .75rem .65rem; background:rgba(155,140,255,.04);
    text-align:center; position:relative; transition:box-shadow .15s;
}
.si-img-card:hover { box-shadow:0 2px 10px rgba(108,93,251,.15); }
.si-img-card .si-card-thumb {
    width:100%; aspect-ratio:16/9; object-fit:cover;
    border-radius:6px; display:block; background:rgba(108,93,251,.06);
}
.si-img-card .si-card-thumb-icon {
    width:100%; aspect-ratio:16/9; border-radius:6px;
    background:rgba(108,93,251,.06); display:flex; align-items:center;
    justify-content:center; font-size:2rem;
}
.si-img-card p { margin:.45rem 0 .5rem; font-size:.76rem; color:var(--sub,#888); word-break:break-all; line-height:1.3; }
.si-del-btn {
    font-size:.74rem; padding:.25rem .6rem;
    border:1px solid rgba(229,62,62,.4); background:transparent;
    color:#e53e3e; border-radius:6px; cursor:pointer; transition:background .15s;
}
.si-del-btn:hover { background:rgba(229,62,62,.1); }

/* ── 列表模式 ── */
.si-preview-grid.si-view-list {
    display:flex; flex-direction:column; gap:.5rem;
}
.si-view-list .si-img-card {
    display:grid;
    grid-template-columns: 72px 1fr auto;
    align-items:center;
    gap:.75rem;
    text-align:left;
    padding:.55rem .75rem;
}
.si-view-list .si-img-card .si-card-thumb {
    width:72px; height:48px; aspect-ratio:unset; object-fit:cover; flex-shrink:0;
}
.si-view-list .si-img-card .si-card-thumb-icon {
    width:72px; height:48px; aspect-ratio:unset; flex-shrink:0; font-size:1.4rem;
}
.si-view-list .si-img-card p { margin:0; font-size:.8rem; }

/* ── 上传区域 ── */
.si-drop-zone {
    border: 2px dashed rgba(108,93,251,.35);
    border-radius: 10px;
    padding: 1.5rem 1rem;
    text-align: center;
    cursor: pointer;
    transition: border-color .2s, background .2s;
    background: rgba(108,93,251,.03);
    position: relative;
}
.si-drop-zone:hover, .si-drop-zone.dragover {
    border-color: #6c5dfb;
    background: rgba(108,93,251,.08);
}
.si-drop-zone input[type=file] {
    position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%;
}
.si-drop-icon { font-size:2rem; margin-bottom:.4rem; }
.si-drop-hint { font-size:.82rem; color:var(--sub,#888); margin:.2rem 0 0; }
.si-drop-hint strong { color:#6c5dfb; }

/* ── 上传队列 ── */
.si-queue { margin-top:.75rem; display:flex; flex-direction:column; gap:.5rem; }
.si-q-item {
    display:grid;
    grid-template-columns: 48px 1fr auto;
    align-items:center;
    gap:.6rem;
    padding:.55rem .7rem;
    border-radius:8px;
    background:rgba(108,93,251,.05);
    border:1px solid rgba(108,93,251,.15);
    font-size:.82rem;
}
.si-q-thumb { width:48px; height:48px; object-fit:cover; border-radius:5px; background:#eee; }
.si-q-thumb-placeholder { width:48px; height:48px; border-radius:5px; background:rgba(108,93,251,.12); display:flex; align-items:center; justify-content:center; font-size:1.2rem; }
.si-q-info { min-width:0; }
.si-q-name { font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:inherit; }
.si-q-meta { font-size:.75rem; color:var(--sub,#888); margin-top:.1rem; }
.si-q-status { font-size:.75rem; white-space:nowrap; }
.si-q-status.pending  { color:#888; }
.si-q-status.uploading{ color:#6c5dfb; }
.si-q-status.done     { color:#38a169; }
.si-q-status.error    { color:#e53e3e; }
.si-q-status.resume   { color:#d69e2e; }
.si-progress-wrap { height:4px; background:rgba(108,93,251,.12); border-radius:2px; margin-top:.3rem; overflow:hidden; }
.si-progress-bar  { height:100%; background:#6c5dfb; border-radius:2px; transition:width .15s linear; width:0%; }
.si-q-item.done .si-progress-bar { background:#38a169; }
.si-q-item.error .si-progress-bar { background:#e53e3e; }

/* ── 简单文件行 ── */
.si-file-row { display:flex; align-items:center; gap:.6rem; }
.si-file-row input[type=file] { flex:1; padding:.4rem; border:1px solid var(--admin-border,rgba(155,140,255,.4)); border-radius:8px; font-size:.85rem; background:var(--admin-card,#fff); color:inherit; }
.si-file-prog { height:3px; background:rgba(108,93,251,.12); border-radius:2px; margin-top:.3rem; overflow:hidden; display:none; }
.si-file-prog-bar { height:100%; background:#6c5dfb; border-radius:2px; transition:width .15s; width:0%; }

.ajax-msg { display:none; padding:.6rem 1rem; border-radius:6px; font-size:.875rem; margin-bottom:.8rem; }
.ajax-msg.success { background:#f0fff4; border-left:4px solid #38a169; color:#276749; }
.ajax-msg.error   { background:#fff0f0; border-left:4px solid #e53e3e; color:#c53030; }

body.dark-mode .si-img-card { background:rgba(176,160,255,.06); border-color:rgba(176,160,255,.2); }
body.dark-mode .si-view-btn { border-color:rgba(108,93,251,.3); color:#9a8fb0; }
body.dark-mode .si-view-btn.active, body.dark-mode .si-view-btn:hover { background:rgba(108,93,251,.2); color:#b4a8ff; }
body.dark-mode .si-img-card .si-card-thumb-icon { background:rgba(108,93,251,.15); }
body.dark-mode .si-drop-zone { background:rgba(108,93,251,.06); border-color:rgba(108,93,251,.3); }
body.dark-mode .si-q-item { background:rgba(108,93,251,.08); border-color:rgba(108,93,251,.2); }
body.dark-mode .si-q-thumb-placeholder { background:rgba(108,93,251,.2); }
body.dark-mode input[type=text], body.dark-mode input[type=email],
body.dark-mode input[type=number], body.dark-mode input[type=password],
body.dark-mode input[type=file], body.dark-mode textarea, body.dark-mode select {
    background: #1e1e32 !important; color: #eaeaea !important;
    border-color: rgba(176,160,255,.35) !important;
}
body.dark-mode input::placeholder, body.dark-mode textarea::placeholder { color: #6b6b8a !important; }
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

        <!-- Logo -->
        <div class="mfg" style="margin-bottom:1rem;">
            <label>导航栏 Logo
                <small style="font-weight:normal;color:var(--sub,#999);">（png/jpg/jpeg/gif，命名为 logo.png）</small>
            </label>
            <div class="si-file-row">
                <input type="file" id="si-logo-input" accept="image/*">
                <button type="button" class="btn btn-primary" id="si-logo-btn" style="white-space:nowrap;">⬆️ 上传</button>
            </div>
            <div class="si-file-prog" id="si-logo-prog"><div class="si-file-prog-bar" id="si-logo-progbar"></div></div>
            <?php if ($hasLogo): ?>
                <small style="color:#856404;font-size:.76rem;">⚠️ 已有导航栏 Logo，上传将覆盖</small>
            <?php endif; ?>
        </div>

        <!-- Favicon -->
        <div class="mfg" style="margin-bottom:1rem;">
            <label>网站 Favicon
                <small style="font-weight:normal;color:var(--sub,#999);">（.ico / .png / .svg）</small>
            </label>
            <div class="si-file-row">
                <input type="file" id="si-fav-input" accept=".ico,.png,.svg">
                <button type="button" class="btn btn-primary" id="si-fav-btn" style="white-space:nowrap;">⬆️ 上传</button>
            </div>
            <div class="si-file-prog" id="si-fav-prog"><div class="si-file-prog-bar" id="si-fav-progbar"></div></div>
            <?php if ($hasFavicon): ?>
                <small style="color:#856404;font-size:.76rem;">⚠️ 已有 Favicon（<?php echo htmlspecialchars($faviconFile); ?>），上传将覆盖</small>
            <?php endif; ?>
        </div>

        <!-- Banner 多选 + 拖拽 -->
        <div class="mfg">
            <label>背景图片
                <small style="font-weight:normal;color:var(--sub,#999);">（png/jpg/jpeg/gif，支持多选，分片上传，断点续传）</small>
            </label>
            <div class="si-drop-zone" id="si-banner-drop">
                <input type="file" id="si-banner-input" accept="image/*" multiple>
                <div class="si-drop-icon">🖼️</div>
                <p style="margin:.2rem 0 0;font-size:.88rem;font-weight:600;">点击选择或拖拽图片到此处</p>
                <p class="si-drop-hint">支持 <strong>多选</strong>，每个文件自动命名为 banner1.png、banner2.png…</p>
            </div>
            <div class="si-queue" id="si-banner-queue"></div>
        </div>
    </div>

    <!-- 现有图片 -->
    <div class="mbuilder" style="padding:1.2rem;" id="si-img-gallery"
         <?php echo (empty($banners) && !$hasLogo && !$hasFavicon) ? 'style="display:none;padding:1.2rem;"' : ''; ?>>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.8rem;">
            <p style="margin:0; font-size:.83rem; font-weight:700; color:#6c5dfb;">📁 现有图片</p>
            <div class="si-view-toggle" id="si-view-toggle">
                <button type="button" class="si-view-btn active" data-mode="grid" title="网格视图">⊞ 网格</button>
                <button type="button" class="si-view-btn" data-mode="list" title="列表视图">☰ 列表</button>
            </div>
        </div>
        <div class="si-preview-grid" id="si-img-grid">
            <?php if ($hasLogo && $logoFile): ?>
            <div class="si-img-card" data-file="<?php echo htmlspecialchars($logoFile); ?>">
                <img class="si-card-thumb" src="../img/<?php echo htmlspecialchars($logoFile); ?>" alt="Logo" draggable="false">
                <p>导航栏 Logo（<?php echo htmlspecialchars($logoFile); ?>）</p>
                <button type="button" class="si-del-btn" data-file="<?php echo htmlspecialchars($logoFile); ?>">🗑 删除</button>
            </div>
            <?php endif; ?>
            <?php if ($hasFavicon && $faviconFile): ?>
            <div class="si-img-card" data-file="<?php echo htmlspecialchars($faviconFile); ?>">
                <?php if (str_ends_with($faviconFile, '.svg') || str_ends_with($faviconFile, '.ico')): ?>
                <div class="si-card-thumb-icon">🔖</div>
                <?php else: ?>
                <img class="si-card-thumb" src="../img/<?php echo htmlspecialchars($faviconFile); ?>" alt="Favicon" draggable="false">
                <?php endif; ?>
                <p>网站 Favicon（<?php echo htmlspecialchars($faviconFile); ?>）</p>
                <button type="button" class="si-del-btn" data-file="<?php echo htmlspecialchars($faviconFile); ?>">🗑 删除</button>
            </div>
            <?php endif; ?>
            <?php foreach ($banners as $banner): ?>
            <div class="si-img-card" data-file="<?php echo htmlspecialchars($banner); ?>">
                <img class="si-card-thumb" src="../img/<?php echo htmlspecialchars($banner); ?>" alt="<?php echo htmlspecialchars($banner); ?>" draggable="false">
                <p><?php echo htmlspecialchars($banner); ?></p>
                <button type="button" class="si-del-btn" data-file="<?php echo htmlspecialchars($banner); ?>">🗑 删除</button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<script>
(function () {
    'use strict';
    const AJAX_URL   = <?php echo json_encode($ajaxUrl); ?>;
    const IMG_BASE   = '../img/';
    const CHUNK_SIZE = 512 * 1024; // 512 KB — 安全绕过服务器 upload_max_filesize 限制

    /* ── 工具函数 ── */
    function showMsg(type, text) {
        const el = document.getElementById('si-msg');
        if (!el) return;
        el.className = 'ajax-msg ' + type;
        el.textContent = text;
        el.style.display = 'block';
        clearTimeout(el._t);
        el._t = setTimeout(() => { el.style.display = 'none'; }, 6000);
    }

    function fmtSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(2) + ' MB';
    }

    /** 安全 JSON 解析：服务器返回非 JSON（如 HTML 错误页）时给出友好提示 */
    async function safeJson(res) {
        const text = await res.text();
        try {
            return JSON.parse(text);
        } catch {
            // 截取前 80 字符辅助调试
            const preview = text.slice(0, 80).replace(/\s+/g, ' ');
            throw new Error(`服务器返回非 JSON 响应：${preview}…`);
        }
    }

    /** 生成 upload_id：文件名+大小+类型的简单指纹，用于断点续传 */
    async function makeUploadId(file, fileType) {
        const raw = `${file.name}-${file.size}-${file.lastModified}-${fileType}`;
        if (crypto && crypto.subtle) {
            const buf = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(raw));
            return Array.from(new Uint8Array(buf)).map(b => b.toString(16).padStart(2,'0')).join('').slice(0, 24);
        }
        // 降级：简单 hash
        let h = 0;
        for (let i = 0; i < raw.length; i++) { h = (Math.imul(31, h) + raw.charCodeAt(i)) | 0; }
        return Math.abs(h).toString(16).padStart(8,'0') + file.size.toString(16);
    }

    /* ── 核心：分片上传单个文件 ──────────────────────────────────── */
    async function uploadChunked(file, fileType, onProgress) {
        if (file.size === 0) throw new Error('文件为空，跳过上传');

        const uploadId    = await makeUploadId(file, fileType);
        const totalChunks = Math.ceil(file.size / CHUNK_SIZE);

        // 1. 查询断点（已上传的分片）
        let doneSet = new Set();
        try {
            const fd = new FormData();
            fd.append('type',          'config');
            fd.append('config_action', 'check_chunks');
            fd.append('upload_id',     uploadId);
            fd.append('chunk_total',   totalChunks);
            const r = await fetch(AJAX_URL, { method: 'POST', body: fd });
            const d = await safeJson(r);
            if (d.ok && Array.isArray(d.done)) doneSet = new Set(d.done);
        } catch { /* 忽略断点查询失败，全量上传 */ }

        const resuming = doneSet.size > 0 && doneSet.size < totalChunks;

        // 2. 逐片上传
        for (let i = 0; i < totalChunks; i++) {
            if (doneSet.has(i)) {
                onProgress((i + 1) / totalChunks, resuming ? 'resume' : 'uploading');
                continue;
            }

            const chunk = file.slice(i * CHUNK_SIZE, (i + 1) * CHUNK_SIZE);
            const fd = new FormData();
            fd.append('type',          'config');
            fd.append('config_action', 'upload_chunk');
            fd.append('upload_id',     uploadId);
            fd.append('chunk_index',   i);
            fd.append('chunk_total',   totalChunks);
            fd.append('file_type',     fileType);
            fd.append('orig_name',     file.name);
            fd.append('chunk',         chunk, 'chunk');

            const res = await fetch(AJAX_URL, { method: 'POST', body: fd });
            const data = await safeJson(res);

            if (!data.ok) throw new Error(data.msg || `分片 ${i} 上传失败`);
            onProgress((i + 1) / totalChunks, 'uploading');

            if (data.complete) return data; // 全部完成，返回服务器响应
        }

        throw new Error('所有分片发送完毕但服务器未返回完成确认，请重试');
    }

    /* ── 图库辅助 ── */
    function addGalleryCard(file, label, isFavicon) {
        const gallery = document.getElementById('si-img-gallery');
        const grid    = document.getElementById('si-img-grid');
        if (!gallery || !grid) return;
        const existing = grid.querySelector('[data-file="' + CSS.escape(file) + '"]');
        if (existing) existing.remove();

        const card = document.createElement('div');
        card.className    = 'si-img-card';
        card.dataset.file = file;
        card.innerHTML =
            '<img class="si-card-thumb" src="' + IMG_BASE + file + '?t=' + Date.now()
            + '" alt="' + file + '" draggable="false">'
            + '<p>' + (label || file) + '</p>'
            + '<button type="button" class="si-del-btn" data-file="' + file + '">🗑 删除</button>';
        grid.appendChild(card);
        gallery.style.display = '';
    }

    /* ── 基本信息表单 ── */
    (function () {
        const form = document.getElementById('si-info-form');
        const btn  = document.getElementById('si-info-btn');
        if (!form || !btn) return;
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const orig = btn.textContent;
            btn.disabled = true; btn.textContent = '保存中…';
            try {
                const fd = new FormData(form);
                fd.append('type', 'config');
                fd.append('config_action', 'save_siteinfo');
                const res  = await fetch(AJAX_URL, { method: 'POST', body: fd });
                const data = await safeJson(res);
                showMsg(data.ok ? 'success' : 'error', data.msg || (data.ok ? '操作成功' : '操作失败'));
            } catch (err) {
                showMsg('error', '请求失败：' + err.message);
            } finally {
                btn.disabled = false; btn.textContent = orig;
            }
        });
    })();

    /* ── 简单单文件上传（Logo / Favicon）── */
    function bindSimpleUpload(inputId, btnId, progId, progBarId, fileType) {
        const input   = document.getElementById(inputId);
        const btn     = document.getElementById(btnId);
        const prog    = document.getElementById(progId);
        const progBar = document.getElementById(progBarId);
        if (!input || !btn) return;

        btn.addEventListener('click', async () => {
            const file = input.files[0];
            if (!file) { showMsg('error', '请先选择文件'); return; }

            const orig = btn.textContent;
            btn.disabled = true; btn.textContent = '上传中…';
            prog.style.display = 'block';
            progBar.style.width = '0%';

            try {
                const data = await uploadChunked(file, fileType, (pct) => {
                    progBar.style.width = Math.round(pct * 100) + '%';
                });
                progBar.style.width = '100%';
                showMsg('success', data.msg || '上传成功！');
                addGalleryCard(data.file, data.label, fileType === 'favicon');
                input.value = '';
            } catch (err) {
                showMsg('error', '上传失败：' + err.message);
                progBar.style.width = '0%';
            } finally {
                btn.disabled = false; btn.textContent = orig;
                setTimeout(() => { prog.style.display = 'none'; }, 1500);
            }
        });
    }

    bindSimpleUpload('si-logo-input', 'si-logo-btn', 'si-logo-prog', 'si-logo-progbar', 'logo');
    bindSimpleUpload('si-fav-input',  'si-fav-btn',  'si-fav-prog',  'si-fav-progbar',  'favicon');

    /* ── 多文件 Banner 上传队列 ── */
    (function () {
        const drop  = document.getElementById('si-banner-drop');
        const input = document.getElementById('si-banner-input');
        const queue = document.getElementById('si-banner-queue');
        if (!drop || !input || !queue) return;

        let isUploading = false;
        let pendingFiles = []; // { file, itemEl, barEl, statusEl, metaEl }

        /* 拖拽视觉 — 仅响应真实文件拖入，忽略页面内图片拖动 */
        drop.addEventListener('dragover', (e) => {
            // 只有携带外部文件时才激活上传区
            if (!e.dataTransfer.types.includes('Files')) return;
            e.preventDefault();
            drop.classList.add('dragover');
        });
        drop.addEventListener('dragleave', () => { drop.classList.remove('dragover'); });
        drop.addEventListener('drop', (e) => {
            e.preventDefault();
            drop.classList.remove('dragover');
            // 页面内拖动（如从图库拖拽图片）不含文件，直接忽略
            if (!e.dataTransfer.files || !e.dataTransfer.files.length) return;
            enqueueFiles(e.dataTransfer.files);
        });

        /* 防止在上传区外拖放时浏览器打开图片 */
        document.addEventListener('dragover',  (e) => { if (e.target !== drop && !drop.contains(e.target)) e.preventDefault(); });
        document.addEventListener('drop',      (e) => { if (e.target !== drop && !drop.contains(e.target)) e.preventDefault(); });
        input.addEventListener('change', () => {
            enqueueFiles(input.files);
            input.value = '';
        });

        function enqueueFiles(fileList) {
            Array.from(fileList).forEach(file => {
                if (!/\.(png|jpe?g|gif)$/i.test(file.name)) {
                    showMsg('error', `${file.name} 格式不支持（仅 png/jpg/jpeg/gif）`);
                    return;
                }
                const item = buildQueueItem(file);
                pendingFiles.push(item);
                queue.appendChild(item.el);
            });
            if (!isUploading) processQueue();
        }

        function buildQueueItem(file) {
            const el = document.createElement('div');
            el.className = 'si-q-item';

            // 缩略图
            const thumb = document.createElement('div');
            thumb.className = 'si-q-thumb-placeholder';
            thumb.textContent = '🖼️';
            el.appendChild(thumb);

            // 读取本地预览
            const reader = new FileReader();
            reader.onload = (ev) => {
                const img = document.createElement('img');
                img.className = 'si-q-thumb';
                img.src = ev.target.result;
                thumb.replaceWith(img);
            };
            reader.readAsDataURL(file);

            // 信息列
            const info = document.createElement('div');
            info.className = 'si-q-info';
            const nameEl = document.createElement('div');
            nameEl.className = 'si-q-name';
            nameEl.textContent = file.name;
            const metaEl = document.createElement('div');
            metaEl.className = 'si-q-meta';
            metaEl.textContent = fmtSize(file.size) + ' · 等待上传';
            const progWrap = document.createElement('div');
            progWrap.className = 'si-progress-wrap';
            const barEl = document.createElement('div');
            barEl.className = 'si-progress-bar';
            progWrap.appendChild(barEl);
            info.append(nameEl, metaEl, progWrap);
            el.appendChild(info);

            // 状态列
            const statusEl = document.createElement('div');
            statusEl.className = 'si-q-status pending';
            statusEl.textContent = '⏳ 排队';
            el.appendChild(statusEl);

            return { file, el, barEl, statusEl, metaEl };
        }

        async function processQueue() {
            if (isUploading) return;
            isUploading = true;

            while (pendingFiles.length) {
                const item = pendingFiles.shift();
                await uploadItem(item);
            }

            isUploading = false;
        }

        async function uploadItem({ file, el, barEl, statusEl, metaEl }) {
            statusEl.className = 'si-q-status uploading';
            statusEl.textContent = '⬆️ 上传中';
            el.classList.add('uploading');

            try {
                const data = await uploadChunked(file, 'banner', (pct, phase) => {
                    barEl.style.width = Math.round(pct * 100) + '%';
                    metaEl.textContent = fmtSize(file.size) + ' · '
                        + (phase === 'resume' ? '续传 ' : '') + Math.round(pct * 100) + '%';
                    if (phase === 'resume') {
                        statusEl.className = 'si-q-status resume';
                        statusEl.textContent = '🔄 续传';
                    }
                });

                barEl.style.width = '100%';
                el.classList.remove('uploading');
                el.classList.add('done');
                statusEl.className = 'si-q-status done';
                statusEl.textContent = '✅ 完成';
                metaEl.textContent = fmtSize(file.size) + ' · 已上传为 ' + data.file;

                addGalleryCard(data.file, data.label || data.file, false);

                // 3 秒后淡出队列项
                setTimeout(() => {
                    el.style.transition = 'opacity .4s';
                    el.style.opacity = '0';
                    setTimeout(() => el.remove(), 400);
                }, 3000);

            } catch (err) {
                el.classList.remove('uploading');
                el.classList.add('error');
                statusEl.className = 'si-q-status error';
                statusEl.textContent = '❌ 失败';
                metaEl.textContent = err.message;
                barEl.style.width = '100%';

                // 点击可重试
                const retryBtn = document.createElement('button');
                retryBtn.type = 'button';
                retryBtn.className = 'si-del-btn';
                retryBtn.style.cssText = 'border-color:rgba(108,93,251,.5);color:#6c5dfb;margin-top:.3rem;';
                retryBtn.textContent = '🔁 重试';
                retryBtn.addEventListener('click', () => {
                    el.classList.remove('error');
                    retryBtn.remove();
                    barEl.style.width = '0%';
                    // 重新推入队列头
                    pendingFiles.unshift({ file, el, barEl, statusEl, metaEl });
                    if (!isUploading) processQueue();
                });
                el.querySelector('.si-q-info').appendChild(retryBtn);
            }
        }
    })();

    /* ── 删除图片（事件委托）── */
    document.getElementById('si-img-grid').addEventListener('click', async (e) => {
        const btn = e.target.closest('.si-del-btn');
        if (!btn) return;
        const file = btn.dataset.file;
        if (!file || !confirm('确定删除 ' + file + ' 吗？')) return;

        const orig = btn.textContent;
        btn.disabled = true; btn.textContent = '删除中…';
        try {
            const fd = new FormData();
            fd.append('type',          'config');
            fd.append('config_action', 'delete_image');
            fd.append('file',          file);
            const res  = await fetch(AJAX_URL, { method: 'POST', body: fd });
            const data = await safeJson(res);
            showMsg(data.ok ? 'success' : 'error', data.msg || '操作失败');
            if (data.ok) {
                const card = document.querySelector('[data-file="' + CSS.escape(file) + '"]');
                if (card) card.remove();
                if (!document.getElementById('si-img-grid').children.length) {
                    document.getElementById('si-img-gallery').style.display = 'none';
                }
            }
        } catch (err) {
            showMsg('error', '请求失败：' + err.message);
            btn.disabled = false; btn.textContent = orig;
        }
    });

    /* ── 图库视图切换（网格 / 列表） ── */
    (function () {
        const toggleWrap = document.getElementById('si-view-toggle');
        const grid       = document.getElementById('si-img-grid');
        if (!toggleWrap || !grid) return;

        // 持久化用户偏好
        const PREF_KEY = 'si_gallery_view';
        const saved    = localStorage.getItem(PREF_KEY) || 'grid';
        if (saved === 'list') {
            grid.classList.add('si-view-list');
            toggleWrap.querySelector('[data-mode="grid"]').classList.remove('active');
            toggleWrap.querySelector('[data-mode="list"]').classList.add('active');
        }

        toggleWrap.addEventListener('click', (e) => {
            const btn = e.target.closest('.si-view-btn');
            if (!btn) return;
            const mode = btn.dataset.mode;
            toggleWrap.querySelectorAll('.si-view-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            grid.classList.toggle('si-view-list', mode === 'list');
            localStorage.setItem(PREF_KEY, mode);
        });
    })();

})();
</script>