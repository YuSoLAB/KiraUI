<div class="admin-section">

    <div class="mhdr">
        <div>
            <h2 class="mhdr-title">🔄 系统更新</h2>
            <p class="mhdr-sub">检查并安装系统更新，支持在线更新与手动上传更新包。</p>
            <div class="upd-hdr-actions">
                <select id="update-source-select" class="upd-source-select"></select>
                <button id="btn-check-update" class="btn btn-primary" onclick="checkUpdate()">检查更新</button>
                <button class="btn btn-secondary" onclick="showSourceManager()">管理更新源</button>
            </div>
        </div>
    </div>

    <div class="mtip">💡 当前版本：<strong id="current-version-text">加载中...</strong>　|　选择更新源后点「检查更新」，发现新版本后可在线一键更新，或手动上传 ZIP 包更新。</div>

    <!-- 发现新版本卡片 -->
    <div id="update-info" style="display:none;" class="upd-card">
        <div class="upd-card-hd">
            <span class="upd-newver-label">🎉 发现新版本</span>
            <span id="new-version-text" class="upd-newver-badge"></span>
        </div>
        <div class="upd-card-bd">
            <p class="upd-section-label">更新说明</p>
            <div id="update-changelog" class="upd-changelog"></div>
            <div class="upd-card-actions">
                <button id="btn-start-update" class="btn btn-primary" onclick="startUpdate(false)">✅ 确认并开始在线更新</button>
                <div id="manual-prompt" class="upd-manual-prompt" style="display:none;"></div>
            </div>
        </div>
    </div>

    <div class="upd-divider"></div>

    <!-- 手动上传 -->
    <div class="upd-manual-section">
        <h3 class="upd-section-title">📦 手动上传更新包</h3>
        <div class="mfg upd-file-group">
            <label>选择 ZIP 格式更新包</label>
            <div class="upd-file-row">
                <label class="upd-file-label" id="upd-file-label-text" for="manual-update-file">
                    <span class="upd-file-icon">📁</span>
                    <span id="upd-file-name-display">点击选择文件或拖拽到此处</span>
                </label>
                <input type="file" id="manual-update-file" accept=".zip" class="upd-file-input-hidden">
                <button class="btn btn-secondary" id="btn-manual-upload" onclick="startManualUpdate()">上传并更新</button>
            </div>
        </div>

        <!-- 上传进度卡片 -->
        <div id="upload-progress-container" style="display:none;" class="upd-upload-card">
            <div class="upd-upload-header">
                <span class="upd-upload-fileicon">📦</span>
                <div class="upd-upload-meta">
                    <div id="upload-filename" class="upd-upload-filename">—</div>
                    <div id="upload-size-info" class="upd-upload-size-info">—</div>
                </div>
                <span id="upload-percent-badge" class="upd-upload-percent-badge">0%</span>
            </div>
            <div class="upd-bar-wrap">
                <div id="upload-progress-bar" class="upd-bar"></div>
                <span id="upload-bar-label" class="upd-bar-label">0%</span>
            </div>
            <div class="upd-stats-row">
                <span id="upload-speed-text" class="upd-stat-chip">🚀 —</span>
                <span id="upload-eta-text"   class="upd-stat-chip">⏱ —</span>
                <span id="upload-chunk-text" class="upd-stat-chip">🧩 —</span>
            </div>
        </div>
    </div>

    <!-- 更新进度 -->
    <div id="update-progress-container" style="display:none;" class="upd-progress-wrap">
        <h3 class="upd-section-title">⚙️ 更新进度</h3>

        <!-- 下载子进度（在线更新时显示） -->
        <div id="download-progress-wrap" style="display:none;" class="upd-dl-wrap">
            <div class="upd-dl-header">
                <span>📥 正在下载更新包</span>
                <span id="dl-percent-badge" class="upd-dl-badge">0%</span>
            </div>
            <div class="upd-bar-wrap">
                <div id="download-progress-bar" class="upd-bar upd-dl-bar"></div>
                <span id="download-bar-label" class="upd-bar-label">0%</span>
            </div>
            <div class="upd-stats-row">
                <span id="dl-speed-text" class="upd-stat-chip">🚀 —</span>
                <span id="dl-size-text"  class="upd-stat-chip">💾 —</span>
                <span id="dl-eta-text"   class="upd-stat-chip">⏱ —</span>
            </div>
        </div>

        <!-- 步骤进度 -->
        <div class="upd-steps-row">
            <div class="upd-steps-track">
                <div id="progress-bar" class="upd-steps-bar"></div>
            </div>
            <span id="progress-percent-text" class="upd-steps-pct">0%</span>
        </div>
        <div id="progress-log" class="upd-log">等待开始...</div>
    </div>

</div>

<!-- 管理更新源 Modal -->
<div id="update-source-modal" class="mmodal" style="display:none;" onclick="if(event.target===this)closeSourceModal()">
    <div class="mmodal-box">
        <div class="mmodal-hd">
            <h3>管理更新源</h3>
            <button onclick="closeSourceModal()">✕</button>
        </div>
        <div class="mmodal-bd">
            <div id="source-list" class="upd-source-list"></div>
            <div class="upd-addsrc-form">
                <p class="upd-section-label" style="margin-bottom:.5rem;">添加新更新源</p>
                <div class="mfg">
                    <label>名称</label>
                    <input type="text" id="new-source-name" placeholder="如：官方源" autocomplete="off">
                </div>
                <div class="mfg" style="margin-top:.55rem;">
                    <label>URL</label>
                    <input type="text" id="new-source-url" placeholder="https://example.com/update.json" autocomplete="off">
                </div>
            </div>
        </div>
        <div class="mmodal-ft">
            <button class="btn btn-secondary" onclick="closeSourceModal()">关闭</button>
            <button class="btn btn-primary" onclick="addUpdateSource()">＋ 添加</button>
        </div>
    </div>
</div>

<script>
let updateData = null;
const CHUNK_SIZE = 2 * 1024 * 1024; // 2 MB per chunk

/* ─── 初始化 ─── */
document.addEventListener('DOMContentLoaded', function () {
    loadCurrentVersion();
    loadUpdateSources();

    // 文件选择显示
    const fileInput = document.getElementById('manual-update-file');
    fileInput.addEventListener('change', function () {
        const file = this.files[0];
        document.getElementById('upd-file-name-display').textContent =
            file ? file.name + '  (' + fmtBytes(file.size) + ')' : '点击选择文件或拖拽到此处';
    });

    // 拖拽
    const dropZone = document.getElementById('upd-file-label-text');
    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('upd-drag-over'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('upd-drag-over'));
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('upd-drag-over');
        if (e.dataTransfer.files.length && e.dataTransfer.files[0].name.endsWith('.zip')) {
            fileInput.files = e.dataTransfer.files;
            fileInput.dispatchEvent(new Event('change'));
        }
    });
});

async function loadCurrentVersion() {
    try {
        const r = await fetch('../version.json');
        if (r.ok) {
            const d = await r.json();
            currentVersion = d.currentVersion || null;
            document.getElementById('current-version-text').textContent = currentVersion;
        }
    } catch (e) {
        document.getElementById('current-version-text').textContent = currentVersion;
    }
}

/* ─── 版本比较 ─── */
function compareVersions(v1, v2) {
    const p1 = v1.split('.').map(Number), p2 = v2.split('.').map(Number);
    for (let i = 0; i < Math.max(p1.length, p2.length); i++) {
        const a = p1[i] || 0, b = p2[i] || 0;
        if (a > b) return 1; if (a < b) return -1;
    }
    return 0;
}

/* ─── 格式化工具 ─── */
function fmtBytes(b, d = 1) {
    if (!b) return '0 B';
    const k = 1024, s = ['B','KB','MB','GB'], i = Math.floor(Math.log(b)/Math.log(k));
    return +(b/Math.pow(k,i)).toFixed(d) + ' ' + s[i];
}
function fmtSpeed(bps) { return fmtBytes(bps) + '/s'; }
function fmtEta(sec) {
    if (!isFinite(sec) || sec <= 0) return '计算中…';
    if (sec < 60) return Math.ceil(sec) + ' 秒';
    if (sec < 3600) return Math.ceil(sec / 60) + ' 分钟';
    return (sec / 3600).toFixed(1) + ' 小时';
}

/* ─── 进度日志 ─── */
function log(msg, type = 'info') {
    const el = document.getElementById('progress-log');
    const clr = { error:'#ff4db1', success:'#4ade80', warning:'#f2c94c', info:'#b0b0c5' }[type] || '#b0b0c5';
    el.innerHTML += `<div style="display:flex;gap:.5rem;color:${clr}"><span style="color:#555;flex-shrink:0">${new Date().toLocaleTimeString()}</span>${msg}</div>`;
    el.scrollTop = el.scrollHeight;
}

function setProgress(pct) {
    document.getElementById('progress-bar').style.width = pct + '%';
    document.getElementById('progress-percent-text').textContent = pct + '%';
}

/* ════════════════════════════════════
   分片上传
   ════════════════════════════════════ */
async function startManualUpdate() {
    const fileInput = document.getElementById('manual-update-file');
    if (!fileInput.files.length) return alert('请先选择ZIP压缩包');
    if (!confirm('确定要使用本地包更新吗？将自动进行备份并覆盖文件。')) return;

    const file = fileInput.files[0];
    const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
    const uploadId = 'up_' + Date.now().toString(36);

    // 显示进度卡片
    document.getElementById('upload-progress-container').style.display = 'block';
    document.getElementById('update-progress-container').style.display = 'none';
    document.getElementById('btn-manual-upload').disabled = true;
    document.getElementById('upload-filename').textContent = file.name;
    document.getElementById('upload-size-info').textContent =
        fmtBytes(file.size) + '　·　共 ' + totalChunks + ' 个分片（每片 ' + fmtBytes(CHUNK_SIZE) + '）';
    setBarProgress('upload', 0);
    document.getElementById('upload-chunk-text').textContent = '🧩 0 / ' + totalChunks;

    const startTs = Date.now();

    try {
        for (let i = 0; i < totalChunks; i++) {
            const start = i * CHUNK_SIZE;
            const chunk = file.slice(start, Math.min(start + CHUNK_SIZE, file.size));

            await uploadOneChunk(chunk, i, totalChunks, uploadId, chunkLoaded => {
                const totalDone  = i * CHUNK_SIZE + chunkLoaded;
                const pct        = Math.min(99, Math.round(totalDone / file.size * 100));
                const elapsedSec = (Date.now() - startTs) / 1000;
                const speed      = elapsedSec > 0 ? totalDone / elapsedSec : 0;
                const eta        = speed > 0 ? (file.size - totalDone) / speed : 0;
                setBarProgress('upload', pct);
                if (speed > 0) document.getElementById('upload-speed-text').textContent = '🚀 ' + fmtSpeed(speed);
                if (eta  > 0)  document.getElementById('upload-eta-text').textContent   = '⏱ 剩余 ' + fmtEta(eta);
                document.getElementById('upload-chunk-text').textContent = '🧩 分片 ' + (i + 1) + ' / ' + totalChunks;
            });
        }

        setBarProgress('upload', 100);
        document.getElementById('upload-chunk-text').textContent = '✅ 上传完成，正在合并…';

        // 合并分片
        const fd = new FormData();
        fd.append('step', 'upload_chunk_merge');
        fd.append('total_chunks', totalChunks);
        fd.append('upload_id', uploadId);
        const mergeRes  = await fetch('admin_update_api.php', { method: 'POST', body: fd });
        const mergeJson = await mergeRes.json();
        if (mergeJson.code !== 200) throw new Error(mergeJson.msg);

        setTimeout(() => {
            document.getElementById('upload-progress-container').style.display = 'none';
            document.getElementById('update-progress-container').style.display = 'block';
            log('✅ 文件上传完成（' + fmtBytes(file.size) + '），开始执行更新序列…', 'success');
            executeUpdateQueue(true, mergeJson.data.temp_path);
        }, 500);

    } catch (e) {
        log('上传失败: ' + e.message, 'error');
        document.getElementById('upload-chunk-text').textContent = '❌ 上传失败';
        document.getElementById('btn-manual-upload').disabled = false;
    }
}

function uploadOneChunk(chunk, index, total, uploadId, onProgress) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        const fd  = new FormData();
        fd.append('step',         'upload_chunk');
        fd.append('chunk',        chunk, 'chunk');
        fd.append('chunk_index',  index);
        fd.append('total_chunks', total);
        fd.append('upload_id',    uploadId);

        xhr.upload.addEventListener('progress', e => {
            if (e.lengthComputable && onProgress) onProgress(e.loaded);
        });
        xhr.addEventListener('load', () => {
            try {
                const r = JSON.parse(xhr.responseText);
                r.code === 200 ? resolve(r) : reject(new Error(r.msg || '分片上传失败'));
            } catch { reject(new Error('服务器响应格式错误')); }
        });
        xhr.addEventListener('error',   () => reject(new Error('网络错误')));
        xhr.addEventListener('timeout', () => reject(new Error('上传超时')));
        xhr.timeout = 120000;
        xhr.open('POST', 'admin_update_api.php');
        xhr.send(fd);
    });
}

/* 通用进度条设置 */
function setBarProgress(prefix, pct) {
    const bar   = document.getElementById(prefix + '-progress-bar');
    const label = document.getElementById(prefix + '-bar-label');
    const badge = document.getElementById(prefix + '-percent-badge');
    if (bar)   bar.style.width   = pct + '%';
    if (label) label.textContent = pct + '%';
    if (badge) badge.textContent = pct + '%';
}

/* ════════════════════════════════════
   下载进度轮询
   ════════════════════════════════════ */
let dlTimer = null, dlLastBytes = 0, dlLastTime = 0;

function startDownloadPoll() {
    dlLastBytes = 0; dlLastTime = Date.now();
    document.getElementById('download-progress-wrap').style.display = 'block';
    setBarProgress('download', 0);
    document.getElementById('dl-speed-text').textContent = '🚀 —';
    document.getElementById('dl-size-text').textContent  = '💾 —';
    document.getElementById('dl-eta-text').textContent   = '⏱ —';
    dlTimer = setInterval(pollDl, 700);
}
function stopDownloadPoll() {
    if (dlTimer) { clearInterval(dlTimer); dlTimer = null; }
    setTimeout(() => { document.getElementById('download-progress-wrap').style.display = 'none'; }, 600);
}
async function pollDl() {
    try {
        const fd = new FormData();
        fd.append('step', 'download_status');
        const j = await (await fetch('admin_update_api.php', { method:'POST', body:fd })).json();
        if (j.code !== 200 || !j.data) return;
        const { downloaded = 0, total = 0, status } = j.data;
        const now = Date.now(), dt = (now - dlLastTime) / 1000;
        const speed = dt > 0.2 ? (downloaded - dlLastBytes) / dt : 0;
        dlLastBytes = downloaded; dlLastTime = now;
        const pct = total > 0 ? Math.min(99, Math.round(downloaded / total * 100)) : 0;
        const eta = speed > 0 && total > 0 ? (total - downloaded) / speed : 0;
        setBarProgress('download', pct);
        document.getElementById('dl-percent-badge').textContent = pct + '%';
        if (speed > 0) document.getElementById('dl-speed-text').textContent = '🚀 ' + fmtSpeed(speed);
        document.getElementById('dl-size-text').textContent = total > 0
            ? '💾 ' + fmtBytes(downloaded) + ' / ' + fmtBytes(total)
            : '💾 ' + fmtBytes(downloaded);
        if (eta > 0) document.getElementById('dl-eta-text').textContent = '⏱ 剩余 ' + fmtEta(eta);
        if (status === 'done') stopDownloadPoll();
    } catch { /* silent */ }
}

/* ════════════════════════════════════
   更新源管理
   ════════════════════════════════════ */
async function loadUpdateSources() {
    try {
        const fd = new FormData(); fd.append('step', 'get_update_sources');
        const result = await (await fetch('admin_update_api.php', { method:'POST', body:fd })).json();
        if (result.code !== 200) return;
        const sources = result.data.sources || [];
        const defUrl  = result.data.default || (sources.length ? sources[0].url : '');

        const select = document.getElementById('update-source-select');
        select.innerHTML = '';
        sources.forEach(s => {
            const o = document.createElement('option');
            o.value = s.url; o.textContent = s.name;
            if (s.url === defUrl) o.selected = true;
            select.appendChild(o);
        });
        if (!sources.length) {
            const o = document.createElement('option');
            o.value = 'https://www.kiraui.org/api/update.json';
            o.textContent = '默认官方源'; o.selected = true;
            select.appendChild(o);
        }

        const listDiv = document.getElementById('source-list');
        listDiv.innerHTML = '';
        sources.forEach(s => {
            const div = document.createElement('div');
            div.className = 'upd-src-item';
            div.innerHTML = `
                <div class="upd-src-info">
                    <strong>${s.name}</strong>
                    <small class="upd-src-url">${s.url}</small>
                    ${s.url === defUrl ? '<span class="upd-src-default">默认</span>' : ''}
                </div>
                <div class="upd-src-btns">
                    <button class="btn btn-xs mbtn-t" onclick="setDefaultSource('${s.url}')">设为默认</button>
                    <button class="btn btn-xs mbtn-d" onclick="deleteUpdateSource('${s.url}')">删除</button>
                </div>`;
            listDiv.appendChild(div);
        });
        if (!sources.length) listDiv.innerHTML = '<p class="upd-src-empty">暂无自定义更新源，使用默认官方源。</p>';
    } catch (e) { console.error('加载更新源异常', e); }
}

function showSourceManager() { document.getElementById('update-source-modal').style.display = 'flex'; loadUpdateSources(); }
function closeSourceModal()   { document.getElementById('update-source-modal').style.display = 'none'; }

async function addUpdateSource() {
    const name = document.getElementById('new-source-name').value.trim();
    const url  = document.getElementById('new-source-url').value.trim();
    if (!name || !url) return alert('请填写名称和URL');
    const fd = new FormData();
    fd.append('step','add_update_source'); fd.append('name',name); fd.append('url',url);
    const r = await (await fetch('admin_update_api.php',{method:'POST',body:fd})).json();
    if (r.code === 200) { document.getElementById('new-source-name').value=''; document.getElementById('new-source-url').value=''; loadUpdateSources(); }
    else alert('添加失败：' + r.msg);
}

async function deleteUpdateSource(url) {
    if (!confirm('确定要删除该更新源吗？')) return;
    const fd = new FormData(); fd.append('step','delete_update_source'); fd.append('url',url);
    const r = await (await fetch('admin_update_api.php',{method:'POST',body:fd})).json();
    if (r.code === 200) loadUpdateSources(); else alert('删除失败：' + r.msg);
}

async function setDefaultSource(url) {
    const fd = new FormData(); fd.append('step','set_default_source'); fd.append('url',url);
    const r = await (await fetch('admin_update_api.php',{method:'POST',body:fd})).json();
    if (r.code === 200) loadUpdateSources(); else alert('设置失败：' + r.msg);
}

/* ════════════════════════════════════
   检查更新
   ════════════════════════════════════ */
async function checkUpdate() {
    const btn = document.getElementById('btn-check-update');
    btn.innerText = '检查中…'; btn.disabled = true;
    const sourceUrl = document.getElementById('update-source-select').value;
    try {
        const fd = new FormData(); fd.append('step','check_update'); fd.append('source_url',sourceUrl);
        const result = await (await fetch('admin_update_api.php',{method:'POST',body:fd})).json();
        if (result.code === 200 && result.data) {
            const data = result.data;
            if (data.version !== currentVersion) {
                updateData = data;
                document.getElementById('new-version-text').innerText = data.version;
                let clHtml = '';
                if (Array.isArray(data.changelog)) {
                    data.changelog.sort((a,b) => compareVersions(b.version,a.version)).forEach(item => {
                        clHtml += `<div class="upd-cl-item"><h4 class="upd-cl-ver">v${item.version}</h4><div>${item.content.replace(/\n/g,'<br>')}</div></div>`;
                    });
                } else { clHtml = data.changelog.replace(/\n/g,'<br>'); }
                document.getElementById('update-changelog').innerHTML = clHtml;

                const manualDiv = document.getElementById('manual-prompt');
                const startBtn  = document.getElementById('btn-start-update');
                let stepsHtml = '';
                if (data.manual_steps && Array.isArray(data.manual_steps)) {
                    data.manual_steps.sort((a,b) => compareVersions(a.affected_below,b.affected_below)).forEach(step => {
                        if (step.affected_below && compareVersions(currentVersion,step.affected_below) < 0) {
                            stepsHtml += `<div class="upd-manual-step"><p><strong>⚠️ ${step.message}</strong></p>${step.link?`<p><a href="${step.link}" target="_blank" class="btn btn-primary">前往操作</a></p>`:''}</div>`;
                        }
                    });
                } else if (data.manual && data.manual.required) {
                    let need = !(data.manual.affected_below && compareVersions(currentVersion,data.manual.affected_below) >= 0);
                    if (need) stepsHtml = `<div class="upd-manual-step"><p><strong>⚠️ 此更新需要手动准备：</strong> ${data.manual.message||'请按照指引操作。'}</p>${data.manual.link?`<p><a href="${data.manual.link}" target="_blank" class="btn btn-primary">前往手动操作页面</a></p>`:''}</div>`;
                }
                if (stepsHtml) {
                    manualDiv.innerHTML = stepsHtml + `<p><button class="btn btn-secondary" onclick="startUpdate(true)">我已准备好，开始在线更新</button></p>`;
                    manualDiv.style.display = 'block'; startBtn.style.display = 'none';
                } else { manualDiv.style.display = 'none'; startBtn.style.display = 'inline-block'; }
                document.getElementById('update-info').style.display = 'block';
            } else { alert('当前已经是最新版本！'); }
        } else {
            if (result.available_sources?.length) {
                if (confirm(`检查更新失败：${result.msg||'未知错误'}\n是否尝试其他更新源？`)) {
                    document.getElementById('update-source-select').value = result.available_sources[0];
                    checkUpdate(); return;
                }
            } else throw new Error(result.msg || '检查更新失败');
        }
    } catch (e) { alert('检查更新失败：' + e.message); }
    btn.innerText = '检查更新'; btn.disabled = false;
}

/* ════════════════════════════════════
   在线更新入口
   ════════════════════════════════════ */
async function startUpdate(ignoreManual = false) {
    if (!ignoreManual && updateData?.manual?.required) {
        let need = !(updateData.manual.affected_below && compareVersions(currentVersion,updateData.manual.affected_below) >= 0);
        if (need) { alert('此更新需要先完成手动操作。'); return; }
    }
    if (!confirm('确定要开始在线更新吗？该过程不可逆（失败将自动尝试回滚）。')) return;
    document.getElementById('update-progress-container').style.display = 'block';
    document.getElementById('btn-start-update').disabled = true;
    await executeUpdateQueue(false, updateData.download_url, updateData.hash);
}

/* ════════════════════════════════════
   更新队列
   ════════════════════════════════════ */
async function executeUpdateQueue(isManual, fileSource, hash = '') {
    const steps = [
        { id:'backup',     name:'备份当前文件',   percent:20 },
        { id:'download',   name:'下载更新包',     percent:45, skip:isManual, dlProgress:true },
        { id:'verify',     name:'验证文件完整性', percent:55, skip:isManual },
        { id:'extract',    name:'解压到缓存目录', percent:68 },
        { id:'apply',      name:'执行文件覆盖',   percent:82 },
        { id:'db_migrate', name:'执行数据库迁移', percent:95 },
        { id:'cleanup',    name:'清理临时文件',   percent:100 }
    ];

    for (const step of steps) {
        if (step.skip) continue;
        log(`执行: ${step.name}…`);
        if (step.dlProgress) startDownloadPoll();
        try {
            const fd = new FormData();
            fd.append('step', step.id);
            if (fileSource) fd.append('file_source', fileSource);
            if (hash)       fd.append('hash', hash);
            const result = await (await fetch('admin_update_api.php',{method:'POST',body:fd})).json();
            if (step.dlProgress) stopDownloadPoll();
            if (result.code !== 200) throw new Error(result.msg);
            if (step.id === 'db_migrate') {
                if (result.data?.length) {
                    result.data.forEach(m => log(`  ${m.status==='ok'?'✅':'❌'} ${m.migration}${m.description?' — '+m.description:''}`, m.status==='ok'?'success':'error'));
                } else { log('  数据库已是最新，无待执行迁移', 'success'); }
            }
            log(`${step.name} 完成`, 'success');
            setProgress(step.percent);
        } catch (e) {
            if (step.dlProgress) stopDownloadPoll();
            log(`步骤失败: ${e.message}`, 'error');
            log('正在尝试回滚系统…', 'warning');
            await triggerRollback(); return;
        }
    }
    log('🎉 更新成功！系统将在 2 秒后刷新。', 'success');
    setProgress(100);
    setTimeout(() => window.location.reload(), 2000);
}

async function triggerRollback() {
    try {
        const fd = new FormData(); fd.append('step','rollback');
        const res = await fetch('admin_update_api.php',{method:'POST',body:fd});
        const ct  = res.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
            const text = await res.text();
            log(text.includes('login')||text.includes('未授权')
                ? '回滚失败：会话已过期，请重新登录后手动恢复备份文件。'
                : '回滚失败：服务器响应格式错误，请检查系统状态。', 'error'); return;
        }
        const r = await res.json();
        log(r.code===200 ? '回滚成功，系统已恢复原样。' : '回滚失败：'+(r.msg||'未知错误')+'，请手动恢复 cache/backups 目录！', r.code===200?'success':'error');
    } catch (e) { log('回滚请求异常: ' + e.message, 'error'); }
}

window.addEventListener('load', loadUpdateSources);
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSourceModal(); });
</script>

<style>
/* ═══════════════════════════════════════════════════
   admin_update.php — 统一样式 + 分片上传 & 下载进度
   ═══════════════════════════════════════════════════ */

/* ─── Header ─── */
.upd-hdr-actions { display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; margin-top:.75rem; }
.upd-source-select {
    padding:.48rem .72rem; border:1px solid var(--admin-border,rgba(155,140,255,.4));
    border-radius:8px; font-size:.9rem; background:var(--admin-card,#fff); color:inherit;
    max-width:260px; transition:border-color .15s;
}
.upd-source-select:focus { outline:none; border-color:#6c5dfb; box-shadow:0 0 0 3px rgba(108,93,251,.1); }

/* ─── New-version card ─── */
.upd-card { border:1px solid var(--admin-border,rgba(155,140,255,.35)); border-radius:12px; overflow:hidden; background:var(--admin-card,#fff); margin-bottom:1.2rem; }
.upd-card-hd { display:flex; align-items:center; gap:.65rem; padding:.75rem 1.2rem; background:rgba(155,140,255,.06); border-bottom:1px solid var(--admin-border,rgba(155,140,255,.2)); }
.upd-newver-label { font-size:.95rem; font-weight:700; color:var(--sub,#555); }
.upd-newver-badge { font-size:.88rem; font-weight:700; background:rgba(231,76,60,.12); color:#c0392b; border-radius:10px; padding:2px 10px; }
.upd-card-bd { padding:1.1rem 1.2rem; display:flex; flex-direction:column; gap:.8rem; }
.upd-section-label { margin:0; font-size:.83rem; font-weight:700; color:var(--sub,#666); }
.upd-changelog { background:rgba(155,140,255,.07); border-radius:8px; padding:.75rem 1rem; font-size:.88rem; line-height:1.6; max-height:280px; overflow-y:auto; }
.upd-cl-item { margin-bottom:12px; padding:8px 10px; background:rgba(155,140,255,.05); border-radius:6px; }
.upd-cl-item:last-child { margin-bottom:0; }
.upd-cl-ver { margin:0 0 4px; font-size:.86rem; color:#6c5dfb; }
.upd-card-actions { display:flex; flex-direction:column; gap:.6rem; }
.upd-manual-prompt { padding:.75rem 1rem; background:rgba(255,193,7,.1); border-left:3px solid #f2c94c; border-radius:6px; color:#856404; font-size:.88rem; }
.upd-manual-step { margin-bottom:8px; padding:8px 10px; background:rgba(255,193,7,.08); border-radius:6px; }
.upd-manual-step:last-child { margin-bottom:0; }

/* ─── Divider ─── */
.upd-divider { margin:1.2rem 0; border:0; border-top:1px dashed var(--admin-border,rgba(155,140,255,.3)); }

/* ─── Manual upload ─── */
.upd-manual-section { margin-bottom:1.2rem; }
.upd-section-title { margin:0 0 .75rem; font-size:1rem; font-weight:700; color:var(--sub,#555); }
.upd-file-group { max-width:620px; }
.upd-file-row { display:flex; align-items:stretch; gap:.6rem; flex-wrap:wrap; }
.upd-file-input-hidden { position:absolute; width:1px; height:1px; opacity:0; pointer-events:none; }
.upd-file-label {
    flex:1; min-width:200px; display:flex; align-items:center; gap:.5rem;
    padding:.52rem .9rem; border:1.5px dashed var(--admin-border,rgba(155,140,255,.4));
    border-radius:8px; font-size:.88rem; color:var(--sub,#777);
    background:rgba(155,140,255,.04); cursor:pointer;
    transition:border-color .15s, background .15s; user-select:none;
}
.upd-file-label:hover, .upd-file-label.upd-drag-over {
    border-color:#6c5dfb; background:rgba(108,93,251,.06); color:#6c5dfb;
}
.upd-file-icon { font-size:1rem; flex-shrink:0; }

/* ─── Upload progress card ─── */
.upd-upload-card {
    margin-top:.9rem; padding:1rem 1.1rem;
    border:1px solid var(--admin-border,rgba(155,140,255,.35));
    border-radius:12px; background:var(--admin-card,#fff);
    animation:updFadeIn .25s ease;
}
@keyframes updFadeIn { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:none} }
.upd-upload-header { display:flex; align-items:center; gap:.75rem; margin-bottom:.75rem; }
.upd-upload-fileicon { font-size:1.6rem; flex-shrink:0; }
.upd-upload-meta { flex:1; min-width:0; }
.upd-upload-filename { font-size:.9rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.upd-upload-size-info { font-size:.78rem; color:var(--sub,#888); margin-top:.1rem; }
.upd-upload-percent-badge { flex-shrink:0; font-size:1.15rem; font-weight:800; color:#6c5dfb; min-width:3.5rem; text-align:right; }

/* ─── Shared bar ─── */
.upd-bar-wrap { position:relative; width:100%; background:rgba(155,140,255,.12); border-radius:999px; overflow:hidden; height:20px; margin-bottom:.65rem; }
.upd-bar {
    width:0%; height:100%; border-radius:999px;
    background:linear-gradient(90deg,#6c5dfb,#ff4db1);
    transition:width .35s cubic-bezier(.4,0,.2,1); position:relative;
}
.upd-bar::after {
    content:''; position:absolute; inset:0;
    background:linear-gradient(90deg,transparent,rgba(255,255,255,.18),transparent);
    background-size:200% 100%; animation:updShimmer 1.8s infinite linear;
}
@keyframes updShimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
.upd-bar-label {
    position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);
    font-size:.72rem; font-weight:700; color:#fff; text-shadow:0 1px 3px rgba(0,0,0,.3); pointer-events:none;
}

/* Stats row */
.upd-stats-row { display:flex; gap:.6rem; flex-wrap:wrap; }
.upd-stat-chip { font-size:.78rem; color:var(--sub,#888); background:rgba(155,140,255,.07); border-radius:6px; padding:.22rem .5rem; white-space:nowrap; }

/* ─── Download progress box ─── */
.upd-dl-wrap {
    margin-bottom:1rem; padding:.85rem 1rem;
    border:1px solid rgba(108,93,251,.25); border-radius:10px;
    background:rgba(108,93,251,.04); animation:updFadeIn .25s ease;
}
.upd-dl-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:.6rem; font-size:.88rem; font-weight:600; color:var(--sub,#666); }
.upd-dl-badge { font-size:1rem; font-weight:800; color:#6c5dfb; }
.upd-dl-bar { background:linear-gradient(90deg,#3b82f6,#6c5dfb) !important; }

/* ─── Steps progress ─── */
.upd-progress-wrap { margin-top:.5rem; }
.upd-steps-row { display:flex; align-items:center; gap:.75rem; margin-bottom:.75rem; }
.upd-steps-track { flex:1; background:rgba(155,140,255,.12); border-radius:999px; overflow:hidden; height:18px; }
.upd-steps-bar { width:0%; height:100%; background:linear-gradient(90deg,#6c5dfb,#ff4db1); border-radius:999px; transition:width .4s ease; }
.upd-steps-pct { font-size:.82rem; font-weight:700; color:#6c5dfb; min-width:2.8rem; text-align:right; }

/* ─── Log ─── */
.upd-log {
    font-family:'Courier New',Consolas,monospace;
    background:#1a1a2e; color:#b0b0c5; padding:1rem 1.1rem;
    border-radius:10px; height:190px; overflow-y:auto;
    font-size:.82rem; line-height:1.65; border:1px solid rgba(155,140,255,.15);
}

/* ─── Source list ─── */
.upd-source-list { margin-bottom:1rem; display:flex; flex-direction:column; gap:.4rem; }
.upd-src-item { display:flex; justify-content:space-between; align-items:center; gap:.6rem; padding:.6rem .8rem; background:rgba(155,140,255,.07); border-radius:8px; border:1px solid var(--admin-border,rgba(155,140,255,.2)); }
.upd-src-info { display:flex; flex-direction:column; gap:.15rem; min-width:0; }
.upd-src-info strong { font-size:.88rem; }
.upd-src-url { font-size:.76rem; color:var(--sub,#999); word-break:break-all; }
.upd-src-default { font-size:.7rem; background:rgba(108,93,251,.12); color:#6c5dfb; border-radius:8px; padding:1px 7px; font-weight:600; width:fit-content; }
.upd-src-btns { display:flex; gap:.25rem; flex-shrink:0; }
.upd-src-empty { font-size:.86rem; color:var(--sub,#999); text-align:center; padding:.8rem; }
.upd-addsrc-form { border-top:1px solid var(--admin-border,rgba(155,140,255,.2)); padding-top:.9rem; }

/* ═══ Dark Mode ═══ */
body.dark-mode .upd-source-select { background:#2a2a42aa; border-color:var(--dark-admin-border); color:var(--dark-text,#eaeaea); }
body.dark-mode .upd-source-select:focus { border-color:var(--dark-vio,#b096ff); box-shadow:0 0 0 3px rgba(176,160,255,.12); }
body.dark-mode .upd-card { background:var(--dark-admin-card,#2a2a42dd); border-color:var(--dark-admin-border); }
body.dark-mode .upd-card-hd { background:rgba(176,160,255,.05); border-bottom-color:var(--dark-admin-border); }
body.dark-mode .upd-newver-label { color:var(--dark-sub,#b0b0c5); }
body.dark-mode .upd-newver-badge { background:rgba(235,87,87,.15); color:#eb5757; }
body.dark-mode .upd-section-label, body.dark-mode .upd-section-title { color:var(--dark-sub,#b0b0c5); }
body.dark-mode .upd-changelog { background:rgba(176,160,255,.06); color:var(--dark-text,#eaeaea); }
body.dark-mode .upd-cl-item { background:rgba(176,160,255,.04); }
body.dark-mode .upd-cl-ver { color:var(--dark-vio,#b096ff); }
body.dark-mode .upd-manual-prompt { background:rgba(242,201,76,.08); border-left-color:#f2c94c; color:#f2c94c; }
body.dark-mode .upd-manual-step { background:rgba(242,201,76,.06); }
body.dark-mode .upd-upload-card { background:var(--dark-admin-card,#2a2a42dd); border-color:var(--dark-admin-border); }
body.dark-mode .upd-upload-percent-badge { color:var(--dark-vio,#b096ff); }
body.dark-mode .upd-file-label { background:rgba(176,160,255,.04); border-color:var(--dark-admin-border); color:var(--dark-sub,#b0b0c5); }
body.dark-mode .upd-file-label:hover, body.dark-mode .upd-file-label.upd-drag-over { border-color:var(--dark-vio,#b096ff); color:var(--dark-vio,#b096ff); background:rgba(176,160,255,.07); }
body.dark-mode .upd-stat-chip { background:rgba(176,160,255,.06); color:var(--dark-sub,#b0b0c5); }
body.dark-mode .upd-bar-wrap { background:rgba(176,160,255,.1); }
body.dark-mode .upd-dl-wrap { background:rgba(108,93,251,.07); border-color:rgba(176,160,255,.2); }
body.dark-mode .upd-dl-header { color:var(--dark-sub,#b0b0c5); }
body.dark-mode .upd-dl-badge { color:var(--dark-vio,#b096ff); }
body.dark-mode .upd-steps-track { background:rgba(176,160,255,.1); }
body.dark-mode .upd-steps-pct { color:var(--dark-vio,#b096ff); }
body.dark-mode .upd-log { background:#111125; border-color:rgba(176,160,255,.12); }
body.dark-mode .upd-src-item { background:rgba(176,160,255,.05); border-color:var(--dark-admin-border); }
body.dark-mode .upd-src-url { color:var(--dark-sub,#b0b0c5); }
body.dark-mode .upd-src-default { background:rgba(176,160,255,.12); color:var(--dark-vio,#b096ff); }
body.dark-mode .upd-src-empty { color:var(--dark-sub,#b0b0c5); }
body.dark-mode .upd-addsrc-form { border-top-color:var(--dark-admin-border); }

/* ─── Responsive ─── */
@media (max-width:640px) {
    .upd-hdr-actions { width:100%; }
    .upd-source-select { max-width:100%; width:100%; }
    .upd-src-item { flex-direction:column; align-items:flex-start; }
    .upd-file-row { flex-direction:column; align-items:stretch; }
    .upd-stats-row { gap:.35rem; }
    .upd-steps-row { gap:.4rem; }
}
</style>