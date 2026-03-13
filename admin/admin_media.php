<?php
/**
 * admin_media.php — 媒体库管理
 * UI 风格继承自 admin_menus.php，所有 AJAX 请求发送到 admin_media_ajax.php
 */

// 确保上传目录存在
$_mediaDirs = [
    ROOT_DIR . '/uploads/images/',
    ROOT_DIR . '/uploads/videos/',
    ROOT_DIR . '/uploads/audios/',
    ROOT_DIR . '/uploads/files/',
];
foreach ($_mediaDirs as $_d) {
    if (!file_exists($_d)) @mkdir($_d, 0755, true);
}
unset($_mediaDirs, $_d);
?>

<div class="admin-section">

    <!-- ── Header ─────────────────────────────────────────────────── -->
    <div class="mhdr">
        <div>
            <h2 class="mhdr-title">🖼️ 媒体库</h2>
            <p class="mhdr-sub">管理上传的图片、视频、音频及其他文件。文件将根据类型自动归类，支持全窗口拖曳上传。</p>
        </div>
        <button class="btn btn-primary" onclick="mlTriggerUpload()">＋ 上传文件</button>
    </div>
    <input type="file" id="mlFileInput" multiple style="display:none" onchange="mlHandleFileInput(this)">

    <!-- ── 拖曳上传区 ─────────────────────────────────────────────── -->
    <div class="ml-dropzone" id="mlDropZone">
        <div class="ml-drop-inner">
            <span class="ml-drop-icon">📂</span>
            <span class="ml-drop-text">
                将文件拖曳到<strong>浏览器窗口任意位置</strong>即可上传，或
                <button type="button" class="ml-drop-btn" onclick="mlTriggerUpload()">点击选择文件</button>
            </span>
            <span class="ml-drop-hint">图片 → images · 视频 → videos · 音频 → audios · 其他 → files（自动归类）</span>
        </div>
        <div class="ml-drop-overlay" id="mlDropOverlay"><span>松手立即上传 🚀</span></div>
    </div>

    <!-- ── 上传进度条 ─────────────────────────────────────────────── -->
    <div class="ml-progress-wrap" id="mlProgressWrap" style="display:none">
        <div class="ml-progress-header">
            <span id="mlProgressFile" class="ml-progress-file">准备上传...</span>
            <span id="mlProgressPct"  class="ml-progress-pct">0%</span>
        </div>
        <div class="ml-progress-bar"><div id="mlProgressBar"></div></div>
        <div class="ml-progress-footer">
            <span id="mlProgressTxt"   class="ml-progress-txt"></span>
            <span id="mlProgressSpeed" class="ml-progress-speed"></span>
        </div>
    </div>

    <!-- ── 分类标签 ─────────────────────────────────────────────── -->
    <div class="ml-ftabs" id="mlFolderTabs">
        <button type="button" class="ml-ftab active" data-folder="all"    onclick="mlSwitchFolder('all',this)">全部 <span class="ml-fcnt" id="fcnt-all"></span></button>
        <button type="button" class="ml-ftab"        data-folder="images" onclick="mlSwitchFolder('images',this)">🖼️ 图片 <span class="ml-fcnt" id="fcnt-images"></span></button>
        <button type="button" class="ml-ftab"        data-folder="videos" onclick="mlSwitchFolder('videos',this)">🎬 视频 <span class="ml-fcnt" id="fcnt-videos"></span></button>
        <button type="button" class="ml-ftab"        data-folder="audios" onclick="mlSwitchFolder('audios',this)">🎵 音频 <span class="ml-fcnt" id="fcnt-audios"></span></button>
        <button type="button" class="ml-ftab"        data-folder="files"  onclick="mlSwitchFolder('files',this)">📄 其他 <span class="ml-fcnt" id="fcnt-files"></span></button>
    </div>

    <!-- ── 工具栏 ─────────────────────────────────────────────────── -->
    <div class="ml-toolbar">
        <div class="ml-search-wrap">
            <input type="text" class="ml-search" id="mlSearch" placeholder="🔍 搜索文件名..." oninput="mlApplyFilter()">
        </div>
        <span class="ml-count" id="mlCount"></span>
        <div id="mlClipboardBar" class="ml-clipboard-bar" style="display:none">
            <span id="mlClipInfo" class="ml-clip-info"></span>
            <button type="button" class="btn btn-xs mbtn-e" onclick="mlOpenPasteModal()">📋 粘贴到…</button>
            <button type="button" class="btn btn-xs mbtn-d" onclick="mlClearClipboard()">✕ 清除</button>
        </div>
        <!-- 视图切换 -->
        <div class="ml-view-toggle" title="切换显示方式">
            <button type="button" id="mlViewGrid" class="ml-vtbtn active" onclick="mlSetView('grid')" title="网格视图">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                    <rect x="1" y="1" width="6" height="6" rx="1.2"/>
                    <rect x="9" y="1" width="6" height="6" rx="1.2"/>
                    <rect x="1" y="9" width="6" height="6" rx="1.2"/>
                    <rect x="9" y="9" width="6" height="6" rx="1.2"/>
                </svg>
            </button>
            <button type="button" id="mlViewList" class="ml-vtbtn" onclick="mlSetView('list')" title="列表视图">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                    <rect x="1" y="2"  width="14" height="2.5" rx="1.2"/>
                    <rect x="1" y="6.8" width="14" height="2.5" rx="1.2"/>
                    <rect x="1" y="11.5" width="14" height="2.5" rx="1.2"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- ── 文件列表/网格 ──────────────────────────────────────────────── -->
    <div class="mbuilder" id="mlBuilder">
        <!-- 列表模式表头（网格模式下隐藏） -->
        <div class="ml-list-head" id="mlListHead" style="display:none">
            <span class="mlh-icon"></span>
            <span class="mlh-name">文件名</span>
            <span class="mlh-folder">分类</span>
            <span class="mlh-size">大小</span>
            <span class="mlh-acts">操作</span>
        </div>
        <div id="mlGrid" class="ml-grid">
            <div class="ml-loading">⏳ 加载中...</div>
        </div>
        <div id="mlEmpty" class="mempty" style="display:none">
            📭 暂无文件，点击「上传文件」或将文件拖入此页面
        </div>
    </div>

</div><!-- /admin-section -->

<!-- ── Toast 通知 ───────────────────────────────────────────────────── -->
<div id="mlToast" class="ml-toast" style="display:none"></div>

<!-- ══ 重命名 Modal ══════════════════════════════════════════════════════ -->
<div id="mlRenameModal" class="mmodal" style="display:none" onclick="if(event.target===this)mlCloseRename()">
    <div class="mmodal-box">
        <div class="mmodal-hd">
            <h3>✏️ 重命名文件</h3>
            <button type="button" onclick="mlCloseRename()">✕</button>
        </div>
        <div class="mmodal-bd">
            <div class="mfg">
                <label>原文件名</label>
                <input type="text" id="mlOldNameDisplay" disabled style="opacity:.6">
            </div>
            <div class="mfg">
                <label>新文件名 <span class="req">*</span></label>
                <input type="text" id="mlNewName" placeholder="输入新文件名（含扩展名）" autocomplete="off">
            </div>
        </div>
        <div class="mmodal-ft">
            <button type="button" class="btn btn-secondary" onclick="mlCloseRename()">取消</button>
            <button type="button" class="btn btn-primary"   onclick="mlDoRename()">确认重命名</button>
        </div>
        <div id="mlRenameMsg" class="mmodal-msg"></div>
    </div>
</div>

<!-- ══ 粘贴 Modal ════════════════════════════════════════════════════════ -->
<div id="mlPasteModal" class="mmodal" style="display:none" onclick="if(event.target===this)mlClosePaste()">
    <div class="mmodal-box">
        <div class="mmodal-hd">
            <h3 id="mlPasteTitle">📋 粘贴文件</h3>
            <button type="button" onclick="mlClosePaste()">✕</button>
        </div>
        <div class="mmodal-bd">
            <div class="mfg">
                <label>操作文件</label>
                <div id="mlPasteFileList" class="ml-paste-list"></div>
            </div>
            <div class="mfg">
                <label>目标文件夹 <span class="req">*</span></label>
                <select id="mlPasteDst">
                    <option value="images">🖼️ 图片  (uploads/images/)</option>
                    <option value="videos">🎬 视频  (uploads/videos/)</option>
                    <option value="audios">🎵 音频  (uploads/audios/)</option>
                    <option value="files" >📄 其他  (uploads/files/)</option>
                </select>
            </div>
        </div>
        <div class="mmodal-ft">
            <button type="button" class="btn btn-secondary" onclick="mlClosePaste()">取消</button>
            <button type="button" class="btn btn-primary"   onclick="mlDoPaste()" id="mlPasteBtn">确认</button>
        </div>
        <div id="mlPasteMsg" class="mmodal-msg"></div>
    </div>
</div>

<!-- ══ 预览 Modal ════════════════════════════════════════════════════════ -->
<div id="mlPreviewModal" class="mmodal" style="display:none" onclick="mlClosePreview()">
    <div class="ml-preview-box" onclick="event.stopPropagation()">
        <button type="button" class="ml-preview-close" onclick="mlClosePreview()">✕</button>
        <div id="mlPreviewContent" class="ml-preview-content"></div>
        <div class="ml-preview-footer">
            <span id="mlPreviewName"></span>
            <button type="button" class="btn btn-xs mbtn-e" id="mlPreviewCopyUrl">🔗 复制链接</button>
        </div>
    </div>
</div>

<!-- ══ JS ════════════════════════════════════════════════════════════════ -->
<script>
/* ═══════════════════════════ Media Library ════════════════════════════ */
(function() {
'use strict';

const ML_AJAX = 'admin_media_ajax.php';
const FOLDER_LABELS = { images:'图片', videos:'视频', audios:'音频', files:'其他' };
const IMG_EXTS   = new Set(['jpg','jpeg','png','gif','webp','svg','bmp','ico','avif','tiff','tif']);
const VIDEO_EXTS = new Set(['mp4','webm','avi','mov','mkv','flv','wmv','m4v','3gp','ogv']);
const AUDIO_EXTS = new Set(['mp3','wav','ogg','flac','aac','m4a','wma','opus','aiff']);
const EXT_ICONS  = {
    pdf:'📄', zip:'🗜️', rar:'🗜️', '7z':'🗜️', tar:'🗜️', gz:'🗜️',
    doc:'📝', docx:'📝', xls:'📊', xlsx:'📊', ppt:'📑', pptx:'📑',
    txt:'📃', md:'📃', csv:'📊', json:'📋', xml:'📋', html:'🌐',
    js:'💻',  css:'🎨', py:'🐍',
    mp4:'🎬', webm:'🎬', avi:'🎬', mov:'🎬', mkv:'🎬',
    mp3:'🎵', wav:'🎵', ogg:'🎵', flac:'🎵', aac:'🎵',
};

let mlAllFiles  = [];
let mlFiltered  = [];
let mlFolder    = 'all';
let mlViewMode  = 'grid';              // 'grid' | 'list'
let mlClipboard = null;          // {type:'copy'|'cut', item:{name,folder}}
let mlRenameTarget = null;       // {name, folder}
let mlCurrentPreview = null;     // current file for preview footer
let mlToastTimer;
let mlDragCount = 0;

// ─── Init ────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Restore persisted folder + view mode
    const savedFolder = localStorage.getItem('ml_folder') || 'all';
    const savedView   = localStorage.getItem('ml_view')   || 'grid';

    // Apply saved folder tab
    if (savedFolder !== 'all') {
        mlFolder = savedFolder;
        document.querySelectorAll('.ml-ftab').forEach(b => {
            b.classList.toggle('active', b.dataset.folder === savedFolder);
        });
    }

    // Apply saved view mode (no re-render yet — mlLoadFiles will call mlApplyFilter)
    mlViewMode = savedView;
    mlApplyViewUI();

    mlLoadFiles();
    mlInitGlobalDrop();
});

// ─── Load files ──────────────────────────────────────────────────────────
async function mlLoadFiles() {
    const grid  = document.getElementById('mlGrid');
    const empty = document.getElementById('mlEmpty');
    // Always safe: replace grid content with a fresh loading spinner
    // (the old #mlLoading may no longer exist after a previous render)
    if (empty) empty.style.display = 'none';
    grid.innerHTML = '<div class="ml-loading">⏳ 加载中...</div>';

    try {
        const fd = new FormData();
        fd.append('act', 'list');
        fd.append('folder', 'all');
        const res  = await fetch(ML_AJAX, { method:'POST', body:fd });
        const data = await res.json();
        if (data.ok) {
            mlAllFiles = data.files || [];
            mlUpdateFolderCounts();
            mlApplyFilter();
        } else {
            mlToast(data.msg || '加载失败', true);
            grid.innerHTML = '';
        }
    } catch(e) {
        mlToast('网络错误：' + e.message, true);
        grid.innerHTML = '';
    }
}

// ─── Folder counts ───────────────────────────────────────────────────────
function mlUpdateFolderCounts() {
    const counts = { all: mlAllFiles.length, images:0, videos:0, audios:0, files:0 };
    mlAllFiles.forEach(f => { if (counts[f.folder] !== undefined) counts[f.folder]++; });
    Object.entries(counts).forEach(([k,v]) => {
        const el = document.getElementById('fcnt-' + k);
        if (el) el.textContent = v ? '(' + v + ')' : '';
    });
}

// ─── Folder switch ────────────────────────────────────────────────────────
window.mlSwitchFolder = function(folder, btn) {
    mlFolder = folder;
    localStorage.setItem('ml_folder', folder);
    document.querySelectorAll('.ml-ftab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    mlApplyFilter();
};

// ─── Filter & render ─────────────────────────────────────────────────────
window.mlApplyFilter = function() {
    const q = (document.getElementById('mlSearch').value || '').toLowerCase().trim();
    mlFiltered = mlAllFiles.filter(f => {
        if (mlFolder !== 'all' && f.folder !== mlFolder) return false;
        if (q && !f.name.toLowerCase().includes(q)) return false;
        return true;
    });
    mlRenderGrid();
};

// ─── View toggle ─────────────────────────────────────────────────────────
window.mlSetView = function(mode) {
    mlViewMode = mode;
    localStorage.setItem('ml_view', mode);
    mlApplyViewUI();
    mlRenderGrid();
};

function mlApplyViewUI() {
    const gridBtn = document.getElementById('mlViewGrid');
    const listBtn = document.getElementById('mlViewList');
    const head    = document.getElementById('mlListHead');
    const grid    = document.getElementById('mlGrid');

    if (mlViewMode === 'list') {
        gridBtn?.classList.remove('active');
        listBtn?.classList.add('active');
        grid?.classList.add('ml-list-mode');
        if (head) head.style.display = '';
    } else {
        gridBtn?.classList.add('active');
        listBtn?.classList.remove('active');
        grid?.classList.remove('ml-list-mode');
        if (head) head.style.display = 'none';
    }
}

function mlRenderGrid() {
    const grid  = document.getElementById('mlGrid');
    const empty = document.getElementById('mlEmpty');
    const count = document.getElementById('mlCount');

    count.textContent = mlFiltered.length
        ? `共 ${mlFiltered.length} 个文件`
        : '无匹配文件';

    if (!mlFiltered.length) {
        grid.innerHTML = '';
        empty.style.display = '';
        return;
    }
    empty.style.display = 'none';

    const cutKey = mlClipboard?.type === 'cut'
        ? `${mlClipboard.item.folder}|${mlClipboard.item.name}`
        : '';

    if (mlViewMode === 'list') {
        grid.innerHTML = mlFiltered.map(f => {
            const isCut  = (cutKey === `${f.folder}|${f.name}`);
            const fLabel = FOLDER_LABELS[f.folder] || f.folder;
            const icon   = IMG_EXTS.has(f.ext) ? '🖼️'
                         : VIDEO_EXTS.has(f.ext) ? '🎬'
                         : AUDIO_EXTS.has(f.ext) ? '🎵'
                         : (EXT_ICONS[f.ext] || '📁');
            const escaped = {
                name:   _he(f.name),
                folder: _he(f.folder),
                url:    _he(f.url),
            };
            // For images show tiny thumbnail in list mode
            const thumbCell = IMG_EXTS.has(f.ext)
                ? `<img src="${escaped.url}" class="ml-list-thumb" alt="" draggable="false"
                        onerror="this.outerHTML='<span class=ml-list-icon>${icon}</span>'">`
                : `<span class="ml-list-icon">${icon}</span>`;

            return `<div class="ml-row${isCut ? ' ml-cut' : ''}"
                         onclick="mlPreview(${JSON.stringify(f).replace(/"/g,'&quot;')})">
                <span class="mlc-icon">${thumbCell}</span>
                <span class="mlc-name" title="${escaped.name}">${escaped.name}</span>
                <span class="mlc-folder"><span class="bsub">${fLabel}</span></span>
                <span class="mlc-size">${f.size_h}</span>
                <span class="mlc-acts" onclick="event.stopPropagation()">
                    <button type="button" title="复制链接" onclick="mlCopyUrl('${escaped.url}','${escaped.name}')">🔗</button>
                    <button type="button" title="复制"     onclick="mlMarkCopy('${escaped.name}','${escaped.folder}')">📋</button>
                    <button type="button" title="剪切"     onclick="mlMarkCut('${escaped.name}','${escaped.folder}')">✂️</button>
                    <button type="button" title="重命名"   onclick="mlOpenRename('${escaped.name}','${escaped.folder}')">✏️</button>
                    <button type="button" title="删除" class="ma-del" onclick="mlDelete('${escaped.name}','${escaped.folder}')">🗑️</button>
                </span>
            </div>`;
        }).join('');
    } else {
        // ── Grid mode ────────────────────────────────────────────────────
        grid.innerHTML = mlFiltered.map(f => {
            const isCut   = (cutKey === `${f.folder}|${f.name}`);
            const fLabel  = FOLDER_LABELS[f.folder] || f.folder;
            const thumbHtml = mlThumbHtml(f);
            const escaped = {
                name:   _he(f.name),
                folder: _he(f.folder),
                url:    _he(f.url),
            };

            return `<div class="ml-card${isCut ? ' ml-cut' : ''}"
                         title="${escaped.name}"
                         onclick="mlPreview(${JSON.stringify(f).replace(/"/g,'&quot;')})">
                <div class="ml-thumb">
                    ${thumbHtml}
                    <span class="ml-folder-badge">${fLabel}</span>
                </div>
                <div class="ml-info">
                    <span class="ml-name">${escaped.name}</span>
                    <span class="ml-meta">${f.size_h}</span>
                </div>
                <div class="ml-card-acts" onclick="event.stopPropagation()">
                    <button type="button" title="复制链接" onclick="mlCopyUrl('${escaped.url}','${escaped.name}')">🔗</button>
                    <button type="button" title="复制"     onclick="mlMarkCopy('${escaped.name}','${escaped.folder}')">📋</button>
                    <button type="button" title="剪切"     onclick="mlMarkCut('${escaped.name}','${escaped.folder}')">✂️</button>
                    <button type="button" title="重命名"   onclick="mlOpenRename('${escaped.name}','${escaped.folder}')">✏️</button>
                    <button type="button" title="删除" class="ma-del" onclick="mlDelete('${escaped.name}','${escaped.folder}')">🗑️</button>
                </div>
            </div>`;
        }).join('');
    }
}

function mlThumbHtml(f) {
    if (IMG_EXTS.has(f.ext)) {
        return `<img src="${_he(f.url)}" alt="${_he(f.name)}" loading="lazy" draggable="false"
                     onerror="this.style.display='none';this.parentNode.querySelector('.ml-thumb-icon').style.display='flex'">
                <span class="ml-thumb-icon" style="display:none">🖼️</span>`;
    }
    if (VIDEO_EXTS.has(f.ext)) return `<span class="ml-thumb-icon">🎬</span>`;
    if (AUDIO_EXTS.has(f.ext)) return `<span class="ml-thumb-icon">🎵</span>`;
    const icon = EXT_ICONS[f.ext] || '📁';
    return `<span class="ml-thumb-icon">${icon}</span>`;
}

function _he(s) {
    return String(s)
        .replace(/&/g,'&amp;').replace(/"/g,'&quot;')
        .replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/'/g,'&#39;');
}

// ─── Upload ───────────────────────────────────────────────────────────────
const ML_CHUNK_SIZE  = 2 * 1024 * 1024;   // 2 MB 每片
const ML_MAX_RETRY   = 3;                  // 单片最多重试次数
const ML_RETRY_DELAY = 1500;               // 重试等待基础 ms

window.mlTriggerUpload = function() {
    document.getElementById('mlFileInput').click();
};
window.mlHandleFileInput = function(input) {
    if (input.files.length) mlUploadFiles(input.files);
    input.value = '';
};

/** 根据文件元数据生成稳定指纹（无需哈希，不依赖内容）*/
function mlFileHash(file) {
    const raw = file.name + '|' + file.size + '|' + file.lastModified;
    return btoa(unescape(encodeURIComponent(raw)))
        .replace(/[^a-zA-Z0-9]/g, '')
        .slice(0, 40);
}

/** 查询服务器上已上传的分片列表（断点续传）*/
async function mlCheckResume(fileHash) {
    try {
        const fd = new FormData();
        fd.append('act',       'check_resume');
        fd.append('file_hash', fileHash);
        const res  = await fetch(ML_AJAX, { method:'POST', body:fd });
        const data = await res.json();
        return data.ok ? data : { uploaded_chunks:[] };
    } catch(e) { return { uploaded_chunks:[] }; }
}

/** 上传单个分片，失败自动重试 */
async function mlUploadChunk(file, fileHash, chunkBlob, ci, total, attempt = 0) {
    const fd = new FormData();
    fd.append('act',         'chunk_upload');
    fd.append('file_hash',   fileHash);
    fd.append('file_name',   file.name);
    fd.append('file_size',   file.size);
    fd.append('chunk_index', ci);
    fd.append('chunk_total', total);
    fd.append('chunk',       chunkBlob, file.name);
    try {
        const res  = await fetch(ML_AJAX, { method:'POST', body:fd });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        if (!data.ok) throw new Error(data.msg || '服务器错误');
        return data;
    } catch(e) {
        if (attempt < ML_MAX_RETRY) {
            await new Promise(r => setTimeout(r, ML_RETRY_DELAY * (attempt + 1)));
            return mlUploadChunk(file, fileHash, chunkBlob, ci, total, attempt + 1);
        }
        throw e;
    }
}

/** 主上传函数：分片 + 断点续传 + 真实进度 */
async function mlUploadFiles(fileList) {
    const files  = Array.from(fileList);
    const wrap   = document.getElementById('mlProgressWrap');
    const bar    = document.getElementById('mlProgressBar');
    const fileLbl= document.getElementById('mlProgressFile');
    const pctLbl = document.getElementById('mlProgressPct');
    const txt    = document.getElementById('mlProgressTxt');
    const speed  = document.getElementById('mlProgressSpeed');

    // 初始化进度 UI
    wrap.style.display  = 'flex';
    bar.style.transition= 'none';
    bar.style.width     = '0%';
    if (pctLbl) pctLbl.textContent = '0%';
    if (speed)  speed.textContent  = '';

    const totalBytes    = files.reduce((s, f) => s + f.size, 0);
    let   doneBytes     = 0;
    let   speedWindow   = 0;      // bytes since last speed sample
    let   speedStamp    = Date.now();
    const uploaded      = [];
    const errors        = [];

    /** 更新进度条（每个分片上传完毕后调用）*/
    function tick(chunkBytes, fileIdx, fileName, ci, total) {
        doneBytes   += chunkBytes;
        speedWindow += chunkBytes;

        const now  = Date.now();
        const diff = (now - speedStamp) / 1000;
        if (diff >= 0.6) {
            const bps  = speedWindow / diff;
            speedWindow = 0; speedStamp = now;
            if (speed) speed.textContent =
                bps >= 1048576 ? `${(bps/1048576).toFixed(1)} MB/s`
              : bps >= 1024    ? `${(bps/1024).toFixed(0)} KB/s`
              :                  `${bps.toFixed(0)} B/s`;
        }

        const pct = totalBytes > 0 ? Math.round(doneBytes / totalBytes * 100) : 0;
        bar.style.transition = 'width .15s ease';
        bar.style.width      = pct + '%';
        if (pctLbl) pctLbl.textContent = pct + '%';

        const chunkInfo = total > 1 ? ` · 分片 ${ci+1}/${total}` : '';
        if (fileLbl) fileLbl.textContent = `${fileIdx+1}/${files.length}：${fileName}`;
        if (txt)     txt.textContent     = `正在上传${chunkInfo}`;
    }

    for (let i = 0; i < files.length; i++) {
        const file       = files[i];
        const fileHash   = mlFileHash(file);
        const totalChunk = Math.ceil(file.size / ML_CHUNK_SIZE) || 1;

        if (fileLbl) fileLbl.textContent = `${i+1}/${files.length}：${file.name}`;
        if (txt)     txt.textContent     = '检查断点续传...';

        try {
            // 获取已上传分片（断点续传）
            const resumeInfo = await mlCheckResume(fileHash);
            const doneSet    = new Set(resumeInfo.uploaded_chunks || []);

            // 将已上传分片计入总进度
            let preBytes = 0;
            for (const ci of doneSet) {
                const s = ci * ML_CHUNK_SIZE;
                preBytes += Math.min(ML_CHUNK_SIZE, file.size - s);
            }
            doneBytes += preBytes;

            let finalResult = null;

            for (let ci = 0; ci < totalChunk; ci++) {
                // 跳过已完成分片（但最后一片始终上传，确保服务器触发合并）
                if (doneSet.has(ci) && ci < totalChunk - 1) {
                    continue;
                }

                const start = ci * ML_CHUNK_SIZE;
                const end   = Math.min(start + ML_CHUNK_SIZE, file.size);
                const blob  = file.slice(start, end);

                const result = await mlUploadChunk(file, fileHash, blob, ci, totalChunk);
                tick(end - start, i, file.name, ci, totalChunk);

                if (result.done) finalResult = result;
            }

            if (finalResult) {
                uploaded.push(finalResult);
            }

        } catch(e) {
            errors.push(`${file.name}：${e.message}`);
        }
    }

    // 完成
    bar.style.width = '100%';
    if (pctLbl) pctLbl.textContent = '100%';
    if (txt)    txt.textContent    = '上传完成';
    if (speed)  speed.textContent  = '';

    if (uploaded.length) {
        mlToast(`✅ 成功上传 ${uploaded.length} 个文件`);
        await mlLoadFiles();
    }
    if (errors.length) mlToast('⚠️ ' + errors.join('\n'), true);
    if (!uploaded.length && !errors.length) mlToast('没有文件被处理', true);

    setTimeout(() => {
        wrap.style.display = 'none';
        bar.style.width    = '0%';
        if (pctLbl) pctLbl.textContent = '0%';
    }, 1200);
}

// ─── Global drag & drop（全窗口拖曳，屏蔽页内元素拖动触发）─────────────
// 通过监听 dragstart/dragend 区分「页内元素」和「外部文件」拖入
let mlInternalDrag = false;

function mlInitGlobalDrop() {
    const zone = document.getElementById('mlDropZone');

    // 页内拖动开始 → 标记，防止触发上传覆层
    document.addEventListener('dragstart', () => { mlInternalDrag = true;  });
    document.addEventListener('dragend',   () => { mlInternalDrag = false; });

    document.addEventListener('dragenter', e => {
        if (mlInternalDrag) return;                              // 页内拖动：忽略
        if (!e.dataTransfer?.types?.includes('Files')) return;  // 非文件：忽略
        mlDragCount++;
        zone.classList.add('drag-over');
        e.preventDefault();
    });
    document.addEventListener('dragleave', () => {
        if (mlInternalDrag) return;
        mlDragCount = Math.max(0, mlDragCount - 1);
        if (mlDragCount === 0) zone.classList.remove('drag-over');
    });
    document.addEventListener('dragover', e => {
        if (!mlInternalDrag && e.dataTransfer?.types?.includes('Files')) e.preventDefault();
    });
    document.addEventListener('drop', e => {
        e.preventDefault();
        mlDragCount = 0;
        zone.classList.remove('drag-over');
        mlInternalDrag = false;
        if (e.dataTransfer?.files?.length) mlUploadFiles(e.dataTransfer.files);
    });
}

// ─── Copy URL ─────────────────────────────────────────────────────────────
window.mlCopyUrl = function(proxyUrl, name) {
    // Build a clean public URL from the proxy URL params
    let publicUrl;
    try {
        const u = new URL(proxyUrl, location.href);
        const folder = u.searchParams.get('folder');
        const fname  = u.searchParams.get('name');
        if (folder && fname) {
            publicUrl = location.origin + '/uploads/' + folder + '/' + encodeURIComponent(fname);
        } else {
            publicUrl = location.origin + proxyUrl;
        }
    } catch(e) {
        publicUrl = location.origin + proxyUrl;
    }
    if (navigator.clipboard) {
        navigator.clipboard.writeText(publicUrl).then(() => mlToast('🔗 链接已复制：' + name));
    } else {
        const ta = document.createElement('textarea');
        ta.value = publicUrl;
        document.body.appendChild(ta); ta.select(); document.execCommand('copy');
        document.body.removeChild(ta);
        mlToast('🔗 链接已复制：' + name);
    }
};

// ─── Clipboard copy/cut ────────────────────────────────────────────────────
window.mlMarkCopy = function(name, folder) {
    mlClipboard = { type:'copy', item:{ name, folder } };
    mlUpdateClipboardBar();
    mlRenderGrid();
    mlToast(`📋 已复制：${name}`);
};
window.mlMarkCut = function(name, folder) {
    mlClipboard = { type:'cut', item:{ name, folder } };
    mlUpdateClipboardBar();
    mlRenderGrid();
    mlToast(`✂️ 已剪切：${name}`);
};
window.mlClearClipboard = function() {
    mlClipboard = null;
    mlUpdateClipboardBar();
    mlRenderGrid();
};

function mlUpdateClipboardBar() {
    const bar = document.getElementById('mlClipboardBar');
    if (!mlClipboard) { bar.style.display = 'none'; return; }
    bar.style.display = 'flex';
    const typeText = mlClipboard.type === 'copy' ? '📋 已复制' : '✂️ 已剪切';
    document.getElementById('mlClipInfo').textContent = `${typeText}：${mlClipboard.item.name}`;
}

// ─── Paste modal ───────────────────────────────────────────────────────────
window.mlOpenPasteModal = function() {
    if (!mlClipboard) return;
    const { type, item } = mlClipboard;
    document.getElementById('mlPasteTitle').textContent = type === 'copy' ? '📋 复制到文件夹' : '✂️ 移动到文件夹';
    document.getElementById('mlPasteFileList').textContent = item.name;
    document.getElementById('mlPasteDst').value = item.folder;
    document.getElementById('mlPasteMsg').textContent = '';
    document.getElementById('mlPasteBtn').disabled = false;
    document.getElementById('mlPasteModal').style.display = 'flex';
};
window.mlClosePaste = function() { document.getElementById('mlPasteModal').style.display = 'none'; };

window.mlDoPaste = async function() {
    if (!mlClipboard) return;
    const { type, item } = mlClipboard;
    const dst   = document.getElementById('mlPasteDst').value;
    const msgEl = document.getElementById('mlPasteMsg');
    const btn   = document.getElementById('mlPasteBtn');
    const act   = type === 'copy' ? 'copy' : 'move';

    btn.disabled = true;
    msgEl.textContent = '';

    const fd = new FormData();
    fd.append('act',        act);
    fd.append('src_folder', item.folder);
    fd.append('dst_folder', dst);
    fd.append('name',       item.name);

    try {
        const res  = await fetch(ML_AJAX, { method:'POST', body:fd });
        const data = await res.json();
        if (data.ok) {
            mlToast(`✅ ${act === 'copy' ? '复制' : '移动'}成功：${data.name}`);
            mlClipboard = null;
            mlUpdateClipboardBar();
            mlClosePaste();
            await mlLoadFiles();
        } else {
            msgEl.textContent = data.msg || '操作失败';
            btn.disabled = false;
        }
    } catch(e) {
        msgEl.textContent = '网络错误：' + e.message;
        btn.disabled = false;
    }
};

// ─── Rename ────────────────────────────────────────────────────────────────
window.mlOpenRename = function(name, folder) {
    mlRenameTarget = { name, folder };
    document.getElementById('mlOldNameDisplay').value = name;
    document.getElementById('mlNewName').value        = name;
    document.getElementById('mlRenameMsg').textContent = '';
    document.getElementById('mlRenameModal').style.display = 'flex';
    setTimeout(() => {
        const inp = document.getElementById('mlNewName');
        inp.focus();
        // 选中文件名（不含扩展名）
        const dot = name.lastIndexOf('.');
        inp.setSelectionRange(0, dot > 0 ? dot : name.length);
    }, 60);
};
window.mlCloseRename = function() { document.getElementById('mlRenameModal').style.display = 'none'; };

window.mlDoRename = async function() {
    if (!mlRenameTarget) return;
    const newName = document.getElementById('mlNewName').value.trim();
    const msgEl   = document.getElementById('mlRenameMsg');
    if (!newName) { msgEl.textContent = '文件名不能为空'; return; }

    const fd = new FormData();
    fd.append('act',      'rename');
    fd.append('folder',   mlRenameTarget.folder);
    fd.append('old_name', mlRenameTarget.name);
    fd.append('new_name', newName);

    try {
        const res  = await fetch(ML_AJAX, { method:'POST', body:fd });
        const data = await res.json();
        if (data.ok) {
            mlToast(`✏️ 已重命名为：${data.new_name}`);
            mlCloseRename();
            await mlLoadFiles();
        } else {
            msgEl.textContent = data.msg || '重命名失败';
        }
    } catch(e) {
        msgEl.textContent = '网络错误：' + e.message;
    }
};

// ─── Delete ────────────────────────────────────────────────────────────────
window.mlDelete = async function(name, folder) {
    if (!confirm(`确定要删除文件「${name}」吗？\n\n此操作不可撤销。`)) return;

    const fd = new FormData();
    fd.append('act',    'delete');
    fd.append('folder', folder);
    fd.append('name',   name);

    try {
        const res  = await fetch(ML_AJAX, { method:'POST', body:fd });
        const data = await res.json();
        if (data.ok) {
            mlToast(`🗑️ 已删除：${name}`);
            // 若剪贴板中正是被删除的文件，清空
            if (mlClipboard?.item?.name === name && mlClipboard?.item?.folder === folder) {
                mlClearClipboard();
            }
            await mlLoadFiles();
        } else {
            mlToast(data.msg || '删除失败', true);
        }
    } catch(e) {
        mlToast('网络错误：' + e.message, true);
    }
};

// ─── Preview ───────────────────────────────────────────────────────────────
window.mlPreview = function(f) {
    mlCurrentPreview = f;
    const modal   = document.getElementById('mlPreviewModal');
    const content = document.getElementById('mlPreviewContent');
    const nameEl  = document.getElementById('mlPreviewName');
    const copyBtn = document.getElementById('mlPreviewCopyUrl');

    let html = '';
    if (IMG_EXTS.has(f.ext)) {
        html = `<img src="${_he(f.url)}" alt="${_he(f.name)}">`;
    } else if (VIDEO_EXTS.has(f.ext)) {
        html = `<video src="${_he(f.url)}" controls preload="metadata"></video>`;
    } else if (AUDIO_EXTS.has(f.ext)) {
        html = `<div class="ml-audio-preview">
                    <span class="ml-audio-icon">🎵</span>
                    <audio src="${_he(f.url)}" controls></audio>
                    <p class="ml-audio-name">${_he(f.name)}</p>
                </div>`;
    } else {
        // 不可预览：直接复制链接
        mlCopyUrl(f.url, f.name);
        mlToast(`📋 已复制文件链接：${f.name}`);
        return;
    }

    content.innerHTML = html;
    nameEl.textContent = f.name + '  ·  ' + f.size_h;
    copyBtn.onclick = () => mlCopyUrl(f.url, f.name);
    modal.style.display = 'flex';
};
window.mlClosePreview = function() {
    document.getElementById('mlPreviewModal').style.display = 'none';
    document.getElementById('mlPreviewContent').innerHTML = '';
    mlCurrentPreview = null;
};

// ─── Keyboard ──────────────────────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        mlCloseRename();
        mlClosePaste();
        mlClosePreview();
    }
    if (e.key === 'Enter' && document.getElementById('mlRenameModal').style.display === 'flex') {
        e.preventDefault();
        mlDoRename();
    }
});

// ─── Toast ─────────────────────────────────────────────────────────────────
function mlToast(msg, isErr = false) {
    const el = document.getElementById('mlToast');
    el.textContent = msg;
    el.className = 'ml-toast' + (isErr ? ' ml-toast-err' : '');
    el.style.display = 'block';
    clearTimeout(mlToastTimer);
    mlToastTimer = setTimeout(() => { el.style.display = 'none'; }, isErr ? 4000 : 2800);
}

})(); // IIFE end
</script>

<!-- ══ CSS ══════════════════════════════════════════════════════════════════ -->
<style>
/* ═══════════════════════ Media Library ═══════════════════════════════════ */

/* ── Drop Zone ─────────────────────────────────────────────────────────── */
.ml-dropzone {
    position: relative;
    border: 2px dashed var(--admin-border, rgba(155,140,255,.4));
    border-radius: 12px;
    padding: 1.6rem 1.5rem;
    text-align: center;
    margin-bottom: 1.1rem;
    background: rgba(108,93,251,.025);
    transition: border-color .2s, background .2s;
    overflow: hidden;
    cursor: default;
}
.ml-dropzone.drag-over {
    border-color: #6c5dfb;
    background: rgba(108,93,251,.07);
    box-shadow: 0 0 0 4px rgba(108,93,251,.1);
}
.ml-drop-inner {
    display: flex; flex-direction: column;
    align-items: center; gap: .3rem;
    pointer-events: none;
}
.ml-drop-icon { font-size: 2.2rem; line-height: 1; }
.ml-drop-text { font-size: .92rem; color: var(--sub, #666); pointer-events: auto; }
.ml-drop-hint { font-size: .76rem; color: var(--sub, #aaa); }
.ml-drop-btn {
    background: none; border: none;
    color: #6c5dfb; cursor: pointer;
    font-size: .92rem; padding: 0;
    text-decoration: underline dotted;
    pointer-events: auto;
}
.ml-drop-btn:hover { text-decoration: underline; }
.ml-drop-overlay {
    display: none; position: absolute; inset: 0;
    background: rgba(108,93,251,.13);
    align-items: center; justify-content: center;
    font-size: 1.3rem; font-weight: 700; color: #6c5dfb;
    pointer-events: none; letter-spacing: .02em;
}
.ml-dropzone.drag-over .ml-drop-overlay { display: flex; }

/* ── Progress ──────────────────────────────────────────────────────────── */
.ml-progress-wrap {
    display: flex; flex-direction: column; gap: .32rem;
    margin-bottom: 1rem; padding: .6rem .9rem;
    background: rgba(108,93,251,.05); border-radius: 8px;
}
.ml-progress-header {
    display: flex; justify-content: space-between; align-items: baseline; gap: .5rem;
}
.ml-progress-file {
    font-size: .82rem; font-weight: 600; color: var(--text,#333);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1;
}
.ml-progress-pct { font-size: .82rem; font-weight: 700; color: #6c5dfb; flex-shrink: 0; }
.ml-progress-footer {
    display: flex; justify-content: space-between; align-items: center; gap: .5rem;
}
.ml-progress-speed { font-size: .74rem; color: #6c5dfb; font-weight: 600; flex-shrink: 0; }
.ml-progress-bar {
    width: 100%; height: 7px;
    background: rgba(155,140,255,.18); border-radius: 99px; overflow: hidden;
}
.ml-progress-bar > div {
    height: 100%; width: 0%;
    background: linear-gradient(90deg, #6c5dfb, #a78bfa);
    border-radius: 99px; transition: width .3s;
}
.ml-progress-txt { font-size: .82rem; color: var(--sub, #777); white-space: nowrap; }

/* ── Folder tabs ───────────────────────────────────────────────────────── */
.ml-ftabs { display: flex; gap: .38rem; flex-wrap: wrap; margin-bottom: .85rem; }
.ml-ftab {
    padding: .28rem .82rem; border-radius: 20px;
    border: 1px solid var(--admin-border, rgba(155,140,255,.3));
    background: transparent; cursor: pointer;
    font-size: .84rem; color: var(--sub, #666);
    transition: all .15s; white-space: nowrap;
}
.ml-ftab:hover  { border-color: #6c5dfb; color: #6c5dfb; background: rgba(108,93,251,.05); }
.ml-ftab.active { background: #6c5dfb; color: #fff; border-color: #6c5dfb; }
.ml-fcnt { font-size: .74rem; opacity: .75; }

/* ── Toolbar ────────────────────────────────────────────────────────────── */
.ml-toolbar {
    display: flex; align-items: center; gap: .7rem;
    margin-bottom: .85rem; flex-wrap: wrap;
}
.ml-search-wrap { flex: 1; min-width: 180px; }
.ml-search {
    width: 100%; padding: .42rem .78rem;
    border: 1px solid var(--admin-border, rgba(155,140,255,.35));
    border-radius: 8px; font-size: .88rem; box-sizing: border-box;
    background: var(--admin-card, #fff); color: inherit;
    transition: border-color .15s;
}
.ml-search:focus {
    outline: none; border-color: #6c5dfb;
    box-shadow: 0 0 0 3px rgba(108,93,251,.1);
}
.ml-count { font-size: .81rem; color: var(--sub, #aaa); white-space: nowrap; }
.ml-clipboard-bar { display: flex; align-items: center; gap: .38rem; flex-wrap: wrap; }
.ml-clip-info {
    font-size: .82rem; color: #6c5dfb;
    background: rgba(108,93,251,.08);
    padding: .2rem .65rem; border-radius: 10px; max-width: 260px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

/* ── View Toggle ─────────────────────────────────────────────────────────── */
.ml-view-toggle {
    display: flex; gap: 0;
    border: 1px solid var(--admin-border, rgba(155,140,255,.35));
    border-radius: 8px; overflow: hidden; flex-shrink: 0;
}
.ml-vtbtn {
    padding: .38rem .58rem; border: none; background: transparent;
    cursor: pointer; color: var(--sub, #999);
    transition: background .13s, color .13s;
    display: flex; align-items: center; justify-content: center;
    line-height: 1;
}
.ml-vtbtn + .ml-vtbtn { border-left: 1px solid var(--admin-border, rgba(155,140,255,.25)); }
.ml-vtbtn:hover  { background: rgba(108,93,251,.07); color: #6c5dfb; }
.ml-vtbtn.active { background: rgba(108,93,251,.12); color: #6c5dfb; }

/* ── Grid ───────────────────────────────────────────────────────────────── */
.ml-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(155px, 1fr));
    gap: .82rem;
    padding: 1rem;
    min-height: 100px;
}
.ml-loading { grid-column: 1 / -1; text-align: center; color: var(--sub, #aaa); padding: 2.5rem; }

/* ── List mode header ─────────────────────────────────────────────────────── */
.ml-list-head {
    display: grid;
    grid-template-columns: 2.6rem 1fr 80px 70px 240px;
    gap: .5rem; padding: .42rem 1rem;
    background: rgba(155,140,255,.07);
    border-bottom: 1px solid var(--admin-border, rgba(155,140,255,.2));
    font-size: .77rem; font-weight: 700; color: var(--sub, #888);
}
.ml-list-head span { display: flex; align-items: center; }

/* ── List mode rows ──────────────────────────────────────────────────────── */
.ml-grid.ml-list-mode {
    display: flex; flex-direction: column;
    gap: 0; padding: 0;
}
.ml-row {
    display: grid;
    grid-template-columns: 2.6rem 1fr 80px 70px 240px;
    gap: .5rem; align-items: center;
    padding: .48rem 1rem;
    border-bottom: 1px solid var(--admin-border, rgba(155,140,255,.1));
    cursor: pointer; transition: background .12s;
}
.ml-row:last-child { border-bottom: none; }
.ml-row:hover { background: rgba(155,140,255,.05); }
.ml-row.ml-cut { opacity: .42; }

.mlc-icon {
    display: flex; align-items: center; justify-content: center;
}
.ml-list-thumb {
    width: 32px; height: 32px; object-fit: cover;
    border-radius: 4px; display: block;
}
.ml-list-icon { font-size: 1.4rem; line-height: 1; }

.mlc-name {
    font-size: .83rem; font-weight: 500;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.mlc-folder { }
.mlc-size { font-size: .78rem; color: var(--sub, #999); white-space: nowrap; }

.mlc-acts {
    display: flex; gap: 2px; align-items: center;
}
.mlc-acts button {
    padding: .18rem .35rem; border-radius: 5px; border: none;
    font-size: .72rem; cursor: pointer;
    background: rgba(155,140,255,.1); color: inherit;
    transition: background .1s; line-height: 1.3;
}
.mlc-acts button:hover       { background: rgba(108,93,251,.18); }
.mlc-acts button.ma-del      { background: rgba(220,53,69,.1); color: #c0392b; }
.mlc-acts button.ma-del:hover { background: rgba(220,53,69,.25); }

/* ── File Card ──────────────────────────────────────────────────────────── */
.ml-card {
    border: 1px solid var(--admin-border, rgba(155,140,255,.3));
    border-radius: 10px; overflow: hidden;
    background: var(--admin-card, #fff);
    transition: box-shadow .15s, border-color .15s, transform .12s;
    cursor: pointer; position: relative;
    user-select: none;
}
.ml-card:hover {
    border-color: #6c5dfb;
    box-shadow: 0 4px 18px rgba(108,93,251,.15);
    transform: translateY(-1px);
}
.ml-card.ml-cut { opacity: .42; border-style: dashed; }

.ml-thumb {
    height: 108px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(155,140,255,.04); overflow: hidden; position: relative;
}
.ml-thumb img {
    width: 100%; height: 100%; object-fit: cover;
    transition: transform .22s;
}
.ml-card:hover .ml-thumb img { transform: scale(1.05); }
.ml-thumb-icon { font-size: 2.6rem; line-height: 1; }

.ml-folder-badge {
    position: absolute; top: 5px; right: 5px;
    font-size: .62rem; padding: 1px 6px; border-radius: 8px;
    background: rgba(0,0,0,.38); color: #fff;
    backdrop-filter: blur(4px); pointer-events: none;
}

.ml-info {
    padding: .42rem .58rem .48rem;
    border-top: 1px solid var(--admin-border, rgba(155,140,255,.15));
}
.ml-name {
    display: block; font-size: .79rem; font-weight: 600;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    color: inherit; margin-bottom: .1rem;
}
.ml-meta { font-size: .71rem; color: var(--sub, #aaa); }

/* ── Card action overlay ────────────────────────────────────────────────── */
.ml-card-acts {
    position: absolute; bottom: 0; left: 0; right: 0;
    display: flex; justify-content: center; gap: 2px;
    padding: .28rem .15rem;
    background: linear-gradient(transparent, rgba(0,0,0,.62));
    opacity: 0; transition: opacity .15s; pointer-events: none;
    border-radius: 0 0 9px 9px;
}
.ml-card:hover .ml-card-acts { opacity: 1; pointer-events: auto; }
.ml-card-acts button {
    padding: .19rem .36rem; border-radius: 5px; border: none;
    font-size: .73rem; cursor: pointer; font-weight: 600;
    background: rgba(255,255,255,.88); color: #333;
    transition: background .1s, transform .1s; line-height: 1.3;
}
.ml-card-acts button:hover { background: #fff; transform: scale(1.1); }
.ml-card-acts .ma-del { background: rgba(220,53,69,.82); color: #fff; }
.ml-card-acts .ma-del:hover { background: #c0392b; color: #fff; }

/* ── Paste file list ─────────────────────────────────────────────────────── */
.ml-paste-list {
    font-size: .85rem; color: var(--sub, #666);
    background: rgba(155,140,255,.06);
    padding: .45rem .65rem; border-radius: 7px;
    word-break: break-all; margin: 0;
}

/* ── Toast ──────────────────────────────────────────────────────────────── */
.ml-toast {
    position: fixed; bottom: 1.6rem; right: 1.6rem;
    padding: .58rem 1.1rem; border-radius: 8px;
    background: #27ae60; color: #fff; font-size: .87rem; font-weight: 600;
    box-shadow: 0 4px 20px rgba(0,0,0,.22); z-index: 9999;
    animation: mlToastIn .2s ease; max-width: 340px; pointer-events: none;
    line-height: 1.45; white-space: pre-wrap;
}
.ml-toast.ml-toast-err { background: #c0392b; }
@keyframes mlToastIn { from { opacity: 0; transform: translateY(10px); } }

/* ── Preview Modal ───────────────────────────────────────────────────────── */
.ml-preview-box {
    position: relative; max-width: 92vw; max-height: 92vh;
    background: #111; border-radius: 14px; overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,.65);
    display: flex; flex-direction: column; align-items: center;
    animation: mmIn .18s ease;
}
.ml-preview-close {
    position: absolute; top: .55rem; right: .65rem;
    background: rgba(0,0,0,.55); border: none; color: #fff;
    border-radius: 50%; width: 2.1rem; height: 2.1rem;
    cursor: pointer; font-size: 1rem; line-height: 1; z-index: 10;
    transition: background .15s;
}
.ml-preview-close:hover { background: rgba(220,53,69,.8); }
.ml-preview-content { display: flex; align-items: center; justify-content: center; }
.ml-preview-content img  { max-width: 86vw; max-height: 78vh; object-fit: contain; display: block; }
.ml-preview-content video { max-width: 86vw; max-height: 78vh; display: block; }
.ml-audio-preview {
    display: flex; flex-direction: column; align-items: center;
    gap: 1rem; padding: 2.5rem 2rem;
}
.ml-audio-icon { font-size: 4rem; }
.ml-audio-name { color: #ccc; font-size: .85rem; margin: 0; }
.ml-preview-footer {
    display: flex; align-items: center; gap: .75rem; justify-content: center;
    padding: .5rem 1rem; background: rgba(0,0,0,.5);
    width: 100%; box-sizing: border-box;
}
#mlPreviewName { font-size: .8rem; color: #bbb; flex: 1; text-align: center; }

/* ═══════════════════════════ Dark Mode ══════════════════════════════════ */
body.dark-mode .ml-dropzone {
    background: rgba(176,160,255,.03);
    border-color: var(--dark-admin-border);
}
body.dark-mode .ml-dropzone.drag-over {
    background: rgba(176,160,255,.07);
    border-color: var(--dark-vio, #b096ff);
    box-shadow: 0 0 0 4px rgba(176,160,255,.1);
}
body.dark-mode .ml-drop-text  { color: var(--dark-sub, #b0b0c5); }
body.dark-mode .ml-drop-hint  { color: var(--dark-sub, #888); }
body.dark-mode .ml-drop-btn   { color: var(--dark-vio, #b096ff); }
body.dark-mode .ml-drop-overlay { color: var(--dark-vio, #b096ff); background: rgba(176,160,255,.12); }
body.dark-mode .ml-ftab {
    border-color: var(--dark-admin-border);
    color: var(--dark-sub, #b0b0c5);
}
body.dark-mode .ml-ftab:hover  { border-color: var(--dark-vio,#b096ff); color: var(--dark-vio,#b096ff); }
body.dark-mode .ml-ftab.active { background: var(--dark-vio,#b096ff); color: #1a1a2e; border-color: var(--dark-vio,#b096ff); }
body.dark-mode .ml-search {
    background: #2a2a42aa;
    border-color: var(--dark-admin-border);
    color: var(--dark-text, #eaeaea);
}
body.dark-mode .ml-search:focus {
    border-color: var(--dark-vio,#b096ff);
    box-shadow: 0 0 0 3px rgba(176,160,255,.12);
}
body.dark-mode .ml-clip-info { color: var(--dark-vio,#b096ff); background: rgba(176,160,255,.1); }
body.dark-mode .ml-card {
    background: var(--dark-admin-card, #2a2a42);
    border-color: var(--dark-admin-border);
}
body.dark-mode .ml-card:hover { border-color: var(--dark-vio, #b096ff); }
body.dark-mode .ml-thumb { background: rgba(176,160,255,.04); }
body.dark-mode .ml-info  { border-top-color: var(--dark-admin-border); }
body.dark-mode .ml-progress-wrap { background: rgba(176,160,255,.05); }
body.dark-mode .ml-progress-bar  { background: rgba(176,160,255,.12); }
body.dark-mode .ml-progress-file { color: var(--dark-text,#eaeaea); }
body.dark-mode .ml-progress-pct  { color: var(--dark-vio,#b096ff); }
body.dark-mode .ml-progress-speed{ color: var(--dark-vio,#b096ff); }
body.dark-mode .ml-paste-list    { background: rgba(176,160,255,.06); color: var(--dark-sub,#b0b0c5); }
body.dark-mode .ml-view-toggle   { border-color: var(--dark-admin-border); }
body.dark-mode .ml-vtbtn + .ml-vtbtn { border-left-color: var(--dark-admin-border); }
body.dark-mode .ml-vtbtn         { color: var(--dark-sub,#b0b0c5); }
body.dark-mode .ml-vtbtn:hover   { color: var(--dark-vio,#b096ff); background: rgba(176,160,255,.07); }
body.dark-mode .ml-vtbtn.active  { color: var(--dark-vio,#b096ff); background: rgba(176,160,255,.12); }
body.dark-mode .ml-list-head {
    background: rgba(176,160,255,.05);
    border-bottom-color: var(--dark-admin-border);
    color: var(--dark-sub, #b0b0c5);
}
body.dark-mode .ml-row { border-bottom-color: var(--dark-admin-border); }
body.dark-mode .ml-row:hover { background: rgba(176,160,255,.05); }
body.dark-mode .mlc-size { color: var(--dark-sub,#b0b0c5); }
body.dark-mode .mlc-acts button { background: rgba(176,160,255,.08); }
body.dark-mode .mlc-acts button:hover { background: rgba(176,160,255,.18); }
body.dark-mode .mlc-acts button.ma-del { color: #eb5757; background: rgba(255,71,87,.08); }
body.dark-mode .mlc-acts button.ma-del:hover { background: rgba(255,71,87,.2); }

/* ─── Responsive ─── */
@media (max-width: 600px) {
    .ml-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: .55rem; }
    .ml-thumb { height: 90px; }
    .ml-toolbar { gap: .5rem; }
    /* List: hide size column on small screens */
    .ml-list-head,
    .ml-row { grid-template-columns: 2.2rem 1fr 68px 190px; }
    .mlh-size, .mlc-size { display: none; }
}
@media (max-width: 480px) {
    .ml-grid { grid-template-columns: repeat(2, 1fr); }
    /* List: also hide folder badge */
    .ml-list-head,
    .ml-row { grid-template-columns: 2.2rem 1fr 170px; }
    .mlh-folder, .mlc-folder { display: none; }
}
</style>