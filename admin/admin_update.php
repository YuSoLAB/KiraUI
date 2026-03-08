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
                <input type="file" id="manual-update-file" accept=".zip" class="upd-file-input">
                <button class="btn btn-secondary" onclick="startManualUpdate()">上传并更新</button>
            </div>
        </div>
    </div>

    <!-- 更新进度 -->
    <div id="update-progress-container" style="display:none;" class="upd-progress-wrap">
        <h3 class="upd-section-title">⚙️ 更新进度</h3>
        <div class="upd-progress-bar-wrap">
            <div id="progress-bar" class="upd-progress-bar"></div>
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

async function loadCurrentVersion() {
    try {
        const response = await fetch('../version.json');
        if (response.ok) {
            const versionData = await response.json();
            currentVersion = versionData.currentVersion || null;
            document.getElementById('current-version-text').textContent = currentVersion;
        }
    } catch (e) {
        console.error('加载版本信息失败:', e);
        document.getElementById('current-version-text').textContent = currentVersion;
    }
}

// 页面加载完成后获取当前版本
document.addEventListener('DOMContentLoaded', function() {
    loadCurrentVersion();
    loadUpdateSources(); // 原有的加载更新源函数
});

function compareVersions(v1, v2) {
    const parts1 = v1.split('.').map(Number);
    const parts2 = v2.split('.').map(Number);
    for (let i = 0; i < Math.max(parts1.length, parts2.length); i++) {
        const p1 = parts1[i] || 0;
        const p2 = parts2[i] || 0;
        if (p1 > p2) return 1;
        if (p1 < p2) return -1;
    }
    return 0;
}

function log(msg, type = 'info') {
    const logDiv = document.getElementById('progress-log');
    const color = type === 'error' ? '#ff4db1' : (type === 'success' ? '#00ff00' : '#b0b0c5');
    logDiv.innerHTML += `<div style="color: ${color}">[${new Date().toLocaleTimeString()}] ${msg}</div>`;
    logDiv.scrollTop = logDiv.scrollHeight;
}

function setProgress(percent) {
    document.getElementById('progress-bar').style.width = percent + '%';
}

async function loadUpdateSources() {
    try {
        const formData = new FormData();
        formData.append('step', 'get_update_sources');
        const response = await fetch('admin_update_api.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.code === 200) {
            const sources = result.data.sources || [];
            const defaultUrl = result.data.default || (sources.length ? sources[0].url : '');
            
            // 填充下拉框
            const select = document.getElementById('update-source-select');
            select.innerHTML = '';
            sources.forEach(source => {
                const option = document.createElement('option');
                option.value = source.url;
                option.textContent = source.name;
                if (source.url === defaultUrl) option.selected = true;
                select.appendChild(option);
            });
            if (sources.length === 0) {
                const option = document.createElement('option');
                option.value = 'https://www.kiraui.org/api/update.json';
                option.textContent = '默认官方源';
                option.selected = true;
                select.appendChild(option);
            }

            // 填充模态框列表
            const listDiv = document.getElementById('source-list');
            listDiv.innerHTML = '';
            sources.forEach(source => {
                const div = document.createElement('div');
                div.className = 'upd-src-item';
                div.innerHTML = `
                    <div class="upd-src-info">
                        <strong>${source.name}</strong>
                        <small class="upd-src-url">${source.url}</small>
                        ${source.url === defaultUrl ? '<span class="upd-src-default">默认</span>' : ''}
                    </div>
                    <div class="upd-src-btns">
                        <button class="btn btn-xs mbtn-t" onclick="setDefaultSource('${source.url}')">设为默认</button>
                        <button class="btn btn-xs mbtn-d" onclick="deleteUpdateSource('${source.url}')">删除</button>
                    </div>
                `;
                listDiv.appendChild(div);
            });
            if (sources.length === 0) {
                listDiv.innerHTML = '<p class="upd-src-empty">暂无自定义更新源，使用默认官方源。</p>';
            }
        } else {
            console.error('加载更新源失败', result.msg);
        }
    } catch (e) {
        console.error('加载更新源异常', e);
    }
}

// 显示管理源模态框
function showSourceManager() {
    document.getElementById('update-source-modal').style.display = 'flex';
    loadUpdateSources();
}

function closeSourceModal() {
    document.getElementById('update-source-modal').style.display = 'none';
}

// 添加更新源
async function addUpdateSource() {
    const name = document.getElementById('new-source-name').value.trim();
    const url = document.getElementById('new-source-url').value.trim();
    if (!name || !url) return alert('请填写名称和URL');
    try {
        const formData = new FormData();
        formData.append('step', 'add_update_source');
        formData.append('name', name);
        formData.append('url', url);
        const response = await fetch('admin_update_api.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.code === 200) {
            document.getElementById('new-source-name').value = '';
            document.getElementById('new-source-url').value = '';
            loadUpdateSources();
        } else {
            alert('添加失败：' + result.msg);
        }
    } catch (e) {
        alert('添加异常：' + e.message);
    }
}

// 删除更新源
async function deleteUpdateSource(url) {
    if (!confirm('确定要删除该更新源吗？')) return;
    try {
        const formData = new FormData();
        formData.append('step', 'delete_update_source');
        formData.append('url', url);
        const response = await fetch('admin_update_api.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.code === 200) {
            loadUpdateSources();
        } else {
            alert('删除失败：' + result.msg);
        }
    } catch (e) {
        alert('删除异常：' + e.message);
    }
}

// 设置默认源
async function setDefaultSource(url) {
    try {
        const formData = new FormData();
        formData.append('step', 'set_default_source');
        formData.append('url', url);
        const response = await fetch('admin_update_api.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.code === 200) {
            loadUpdateSources();
        } else {
            alert('设置失败：' + result.msg);
        }
    } catch (e) {
        alert('设置异常：' + e.message);
    }
}

// 检查更新，使用选中的源
async function checkUpdate() {
    const btn = document.getElementById('btn-check-update');
    btn.innerText = '检查中...';
    btn.disabled = true;
    
    // 获取当前选中的源URL
    const select = document.getElementById('update-source-select');
    const sourceUrl = select.value;

    try {
        const formData = new FormData();
        formData.append('step', 'check_update');
        formData.append('source_url', sourceUrl);
        
        const response = await fetch('admin_update_api.php', { method: 'POST', body: formData });
        const result = await response.json();
        
        if (result.code === 200 && result.data) {
            const data = result.data;
            if (data.version !== currentVersion) {
                updateData = data;
                document.getElementById('new-version-text').innerText = data.version;

                // 处理 changelog（兼容数组和字符串）
                let changelogHtml = '';
                if (Array.isArray(data.changelog)) {
                    // 按版本从新到旧排序
                    const sortedChangelog = data.changelog.sort((a, b) => compareVersions(b.version, a.version));
                    sortedChangelog.forEach(item => {
                        changelogHtml += `
                            <div class="upd-cl-item">
                                <h4 class="upd-cl-ver">v${item.version}</h4>
                                <div>${item.content.replace(/\n/g, '<br>')}</div>
                            </div>
                        `;
                    });
                } else {
                    changelogHtml = data.changelog.replace(/\n/g, '<br>');
                }
                document.getElementById('update-changelog').innerHTML = changelogHtml;

                // 处理 manual_steps（现有逻辑，保持原样）
                const manualDiv = document.getElementById('manual-prompt');
                const startBtn = document.getElementById('btn-start-update');
                let stepsHtml = '';
                if (data.manual_steps && Array.isArray(data.manual_steps)) {
                    const steps = data.manual_steps.sort((a, b) => compareVersions(a.affected_below, b.affected_below));
                    steps.forEach(step => {
                        if (step.affected_below && compareVersions(currentVersion, step.affected_below) < 0) {
                            stepsHtml += `
                                <div class="upd-manual-step">
                                    <p><strong>⚠️ ${step.message}</strong></p>
                                    ${step.link ? `<p><a href="${step.link}" target="_blank" class="btn btn-primary">前往操作</a></p>` : ''}
                                </div>
                            `;
                        }
                    });
                } else if (data.manual && data.manual.required) {
                    // 向后兼容旧格式
                    let needManual = true;
                    if (data.manual.affected_below && compareVersions(currentVersion, data.manual.affected_below) >= 0) {
                        needManual = false;
                    }
                    if (needManual) {
                        stepsHtml = `
                            <div class="upd-manual-step">
                                <p><strong>⚠️ 此更新需要手动准备：</strong> ${data.manual.message || '请按照指引操作。'}</p>
                                ${data.manual.link ? `<p><a href="${data.manual.link}" target="_blank" class="btn btn-primary">前往手动操作页面</a></p>` : ''}
                            </div>
                        `;
                    }
                }

                if (stepsHtml) {
                    manualDiv.innerHTML = stepsHtml + `<p><button class="btn btn-secondary" onclick="startUpdate(true)">我已准备好，开始在线更新</button></p>`;
                    manualDiv.style.display = 'block';
                    startBtn.style.display = 'none';
                } else {
                    manualDiv.style.display = 'none';
                    startBtn.style.display = 'inline-block';
                }
                
                document.getElementById('update-info').style.display = 'block';
            } else {
                alert('当前已经是最新版本！');
            }
        } else {
            // 检查失败，如果有其他可用源，提示用户切换
            if (result.available_sources && result.available_sources.length > 0) {
                const msg = `检查更新失败：${result.msg || '未知错误'}\n是否尝试其他更新源？`;
                if (confirm(msg)) {
                    // 自动切换到第一个可用源并重试
                    const newSource = result.available_sources[0];
                    select.value = newSource;
                    checkUpdate(); // 重新检查
                }
            } else {
                throw new Error(result.msg || '检查更新失败');
            }
        }
    } catch (e) {
        alert('检查更新失败：' + e.message);
    }
    
    btn.innerText = '检查更新';
    btn.disabled = false;
}

async function startManualUpdate() {
    const fileInput = document.getElementById('manual-update-file');
    if (!fileInput.files.length) return alert('请先选择ZIP压缩包');
    
    if (!confirm('确定要使用本地包更新吗？将自动进行备份并覆盖文件。')) return;
    
    document.getElementById('update-progress-container').style.display = 'block';
    log('开始上传手动更新包...');
    
    const formData = new FormData();
    formData.append('update_file', fileInput.files[0]);
    formData.append('step', 'upload_manual');
    
    try {
        const res = await fetch('admin_update_api.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.code !== 200) throw new Error(json.msg);
        log('文件上传成功，准备执行更新序列...', 'success');
        await executeUpdateQueue(true, json.data.temp_path);
    } catch (e) {
        log('上传失败: ' + e.message, 'error');
    }
}

async function startUpdate(ignoreManual = false) {
    // 如果不忽略手动检查，则执行原有的手动判断
    if (!ignoreManual && updateData && updateData.manual && updateData.manual.required) {
        let needManual = true;
        if (updateData.manual.affected_below && compareVersions(currentVersion, updateData.manual.affected_below) >= 0) {
            needManual = false;
        }
        if (needManual) {
            alert('此更新需要先完成手动操作，请点击"检查更新"重新确认。');
            return;
        }
    }
    if (!confirm('确定要开始在线更新吗？该过程不可逆（失败将自动尝试回滚）。')) return;
    document.getElementById('update-progress-container').style.display = 'block';
    document.getElementById('btn-start-update').disabled = true;
    await executeUpdateQueue(false, updateData.download_url, updateData.hash);
}

async function executeUpdateQueue(isManual, fileSource, hash = '') {
    const steps = [
        { id: 'backup',    name: '备份当前文件',      percent: 20 },
        { id: 'download',  name: '获取更新包',         percent: 45, skip: isManual },
        { id: 'verify',    name: '验证文件完整性',     percent: 55, skip: isManual },
        { id: 'extract',   name: '解压到缓存目录',     percent: 68 },
        { id: 'apply',     name: '执行文件覆盖',       percent: 82 },
        { id: 'db_migrate',name: '执行数据库迁移',     percent: 95 },
        { id: 'cleanup',   name: '清理临时文件',       percent: 100 }
    ];

    for (let step of steps) {
        if (step.skip) continue;
        log(`执行: ${step.name}...`);
        
        try {
            const formData = new FormData();
            formData.append('step', step.id);
            if (fileSource) formData.append('file_source', fileSource);
            if (hash) formData.append('hash', hash);

            const res = await fetch('admin_update_api.php', { method: 'POST', body: formData });
            const result = await res.json();
            
            if (result.code !== 200) throw new Error(result.msg);
            
            // db_migrate 步骤：把每条迁移结果打印到日志
            if (step.id === 'db_migrate' && result.data && result.data.length > 0) {
                result.data.forEach(m => {
                    const icon = m.status === 'ok' ? '✅' : '❌';
                    log(`  ${icon} ${m.migration}${m.description ? ' — ' + m.description : ''}`,
                        m.status === 'ok' ? 'success' : 'error');
                });
            } else if (step.id === 'db_migrate') {
                log('  数据库已是最新，无待执行迁移', 'success');
            }

            log(`${step.name} 完成`, 'success');
            setProgress(step.percent);
        } catch (e) {
            log(`步骤失败: ${e.message}`, 'error');
            log('正在尝试回滚系统...', 'warning');
            await triggerRollback();
            return;
        }
    }
    log('🎉 更新成功！系统将刷新。', 'success');
    setTimeout(() => window.location.reload(), 2000);
}

async function triggerRollback() {
    try {
        const formData = new FormData();
        formData.append('step', 'rollback');
        const res = await fetch('admin_update_api.php', { method: 'POST', body: formData });
        
        const contentType = res.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const text = await res.text();
            if (text.includes('login') || text.includes('未授权') || text.includes('Unauthorized')) {
                log('回滚失败：会话已过期，请重新登录后手动恢复备份文件。', 'error');
            } else if (text.includes('<html') || text.includes('<!DOCTYPE')) {
                log('回滚失败：服务器返回错误页面，请检查系统状态。', 'error');
            } else {
                log('回滚失败：服务器响应格式错误。', 'error');
            }
            return;
        }
        
        const result = await res.json();
        if (result.code === 200) {
            log('回滚成功，系统已恢复原样。', 'success');
        } else {
            log('回滚失败：' + (result.msg || '未知错误') + '，请手动恢复 cache/backups 目录下的压缩包！', 'error');
        }
    } catch (e) {
        if (e.message.includes('Unexpected token')) {
            log('回滚失败：会话可能已过期，请重新登录后手动恢复备份文件。', 'error');
        } else {
            log('回滚请求异常: ' + e.message, 'error');
        }
    }
}

// 页面加载时加载更新源
window.addEventListener('load', loadUpdateSources);

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSourceModal(); });
</script>

<style>
/* ═══════════════════════════════════════════════════
   admin_update.php — 与 admin_menus.php 统一的样式
   ═══════════════════════════════════════════════════ */

/* ───────────── Header ───────────── */
.upd-hdr-actions {
    display: flex;
    align-items: center;
    gap: .5rem;
    flex-wrap: wrap;
    margin-top: .75rem;
}
.upd-source-select {
    padding: .48rem .72rem;
    border: 1px solid var(--admin-border, rgba(155,140,255,.4));
    border-radius: 8px;
    font-size: .9rem;
    background: var(--admin-card, #fff);
    color: inherit;
    max-width: 260px;
    transition: border-color .15s;
}
.upd-source-select:focus {
    outline: none;
    border-color: #6c5dfb;
    box-shadow: 0 0 0 3px rgba(108,93,251,.1);
}

/* ───────────── New-version card ───────────── */
.upd-card {
    border: 1px solid var(--admin-border, rgba(155,140,255,.35));
    border-radius: 12px;
    overflow: hidden;
    background: var(--admin-card, #fff);
    margin-bottom: 1.2rem;
}
.upd-card-hd {
    display: flex;
    align-items: center;
    gap: .65rem;
    padding: .75rem 1.2rem;
    background: rgba(155,140,255,.06);
    border-bottom: 1px solid var(--admin-border, rgba(155,140,255,.2));
}
.upd-newver-label {
    font-size: .95rem;
    font-weight: 700;
    color: var(--sub, #555);
}
.upd-newver-badge {
    font-size: .88rem;
    font-weight: 700;
    background: rgba(231,76,60,.12);
    color: #c0392b;
    border-radius: 10px;
    padding: 2px 10px;
}
.upd-card-bd {
    padding: 1.1rem 1.2rem;
    display: flex;
    flex-direction: column;
    gap: .8rem;
}
.upd-section-label {
    margin: 0;
    font-size: .83rem;
    font-weight: 700;
    color: var(--sub, #666);
}
.upd-changelog {
    background: rgba(155,140,255,.07);
    border-radius: 8px;
    padding: .75rem 1rem;
    font-size: .88rem;
    line-height: 1.6;
    max-height: 280px;
    overflow-y: auto;
}
.upd-cl-item {
    margin-bottom: 12px;
    padding: 8px 10px;
    background: rgba(155,140,255,.05);
    border-radius: 6px;
}
.upd-cl-item:last-child { margin-bottom: 0; }
.upd-cl-ver {
    margin: 0 0 4px;
    font-size: .86rem;
    color: #6c5dfb;
}
.upd-card-actions {
    display: flex;
    flex-direction: column;
    gap: .6rem;
}
.upd-manual-prompt {
    padding: .75rem 1rem;
    background: rgba(255,193,7,.1);
    border-left: 3px solid #f2c94c;
    border-radius: 6px;
    color: #856404;
    font-size: .88rem;
}
.upd-manual-step {
    margin-bottom: 8px;
    padding: 8px 10px;
    background: rgba(255,193,7,.08);
    border-radius: 6px;
}
.upd-manual-step:last-child { margin-bottom: 0; }

/* ───────────── Divider ───────────── */
.upd-divider {
    margin: 1.2rem 0;
    border: 0;
    border-top: 1px dashed var(--admin-border, rgba(155,140,255,.3));
}

/* ───────────── Manual upload section ───────────── */
.upd-manual-section {
    margin-bottom: 1.2rem;
}
.upd-section-title {
    margin: 0 0 .75rem;
    font-size: 1rem;
    font-weight: 700;
    color: var(--sub, #555);
}
.upd-file-group { max-width: 560px; }
.upd-file-row {
    display: flex;
    align-items: center;
    gap: .6rem;
    flex-wrap: wrap;
}
.upd-file-input {
    flex: 1;
    min-width: 0;
    padding: .42rem .6rem;
    border: 1px solid var(--admin-border, rgba(155,140,255,.4));
    border-radius: 8px;
    font-size: .88rem;
    background: var(--admin-card, #fff);
    color: inherit;
}

/* ───────────── Progress ───────────── */
.upd-progress-wrap {
    margin-top: .5rem;
}
.upd-progress-bar-wrap {
    width: 100%;
    background: rgba(155,140,255,.12);
    border-radius: 8px;
    overflow: hidden;
    height: 18px;
    margin-bottom: .75rem;
}
.upd-progress-bar {
    width: 0%;
    height: 100%;
    background: linear-gradient(90deg, #6c5dfb, #ff4db1);
    border-radius: 8px;
    transition: width .3s ease;
}
.upd-log {
    font-family: 'Courier New', Consolas, monospace;
    background: #1a1a2e;
    color: #b0b0c5;
    padding: 1rem 1.1rem;
    border-radius: 10px;
    height: 160px;
    overflow-y: auto;
    font-size: .82rem;
    line-height: 1.6;
    border: 1px solid rgba(155,140,255,.15);
}

/* ───────────── Source modal list ───────────── */
.upd-source-list {
    margin-bottom: 1rem;
    display: flex;
    flex-direction: column;
    gap: .4rem;
}
.upd-src-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: .6rem;
    padding: .6rem .8rem;
    background: rgba(155,140,255,.07);
    border-radius: 8px;
    border: 1px solid var(--admin-border, rgba(155,140,255,.2));
}
.upd-src-info {
    display: flex;
    flex-direction: column;
    gap: .15rem;
    min-width: 0;
}
.upd-src-info strong {
    font-size: .88rem;
}
.upd-src-url {
    font-size: .76rem;
    color: var(--sub, #999);
    word-break: break-all;
}
.upd-src-default {
    font-size: .7rem;
    background: rgba(108,93,251,.12);
    color: #6c5dfb;
    border-radius: 8px;
    padding: 1px 7px;
    font-weight: 600;
    width: fit-content;
}
.upd-src-btns {
    display: flex;
    gap: .25rem;
    flex-shrink: 0;
}
.upd-src-empty {
    font-size: .86rem;
    color: var(--sub, #999);
    text-align: center;
    padding: .8rem;
}
.upd-addsrc-form {
    border-top: 1px solid var(--admin-border, rgba(155,140,255,.2));
    padding-top: .9rem;
}

/* ═══════════ Dark Mode ═══════════ */
body.dark-mode .upd-source-select {
    background: #2a2a42aa;
    border-color: var(--dark-admin-border);
    color: var(--dark-text, #eaeaea);
}
body.dark-mode .upd-source-select:focus {
    border-color: var(--dark-vio, #b096ff);
    box-shadow: 0 0 0 3px rgba(176,160,255,.12);
}
body.dark-mode .upd-card {
    background: var(--dark-admin-card, #2a2a42dd);
    border-color: var(--dark-admin-border);
}
body.dark-mode .upd-card-hd {
    background: rgba(176,160,255,.05);
    border-bottom-color: var(--dark-admin-border);
}
body.dark-mode .upd-newver-label { color: var(--dark-sub, #b0b0c5); }
body.dark-mode .upd-newver-badge { background: rgba(235,87,87,.15); color: #eb5757; }
body.dark-mode .upd-section-label { color: var(--dark-sub, #b0b0c5); }
body.dark-mode .upd-section-title { color: var(--dark-sub, #b0b0c5); }
body.dark-mode .upd-changelog {
    background: rgba(176,160,255,.06);
    color: var(--dark-text, #eaeaea);
}
body.dark-mode .upd-cl-item { background: rgba(176,160,255,.04); }
body.dark-mode .upd-cl-ver  { color: var(--dark-vio, #b096ff); }
body.dark-mode .upd-manual-prompt {
    background: rgba(242,201,76,.08);
    border-left-color: #f2c94c;
    color: #f2c94c;
}
body.dark-mode .upd-manual-step { background: rgba(242,201,76,.06); }
body.dark-mode .upd-file-input {
    background: #2a2a42aa;
    border-color: var(--dark-admin-border);
    color: var(--dark-text, #eaeaea);
}
body.dark-mode .upd-progress-bar-wrap { background: rgba(176,160,255,.1); }
body.dark-mode .upd-log {
    background: #111125;
    border-color: rgba(176,160,255,.12);
}
body.dark-mode .upd-src-item {
    background: rgba(176,160,255,.05);
    border-color: var(--dark-admin-border);
}
body.dark-mode .upd-src-url { color: var(--dark-sub, #b0b0c5); }
body.dark-mode .upd-src-default {
    background: rgba(176,160,255,.12);
    color: var(--dark-vio, #b096ff);
}
body.dark-mode .upd-src-empty { color: var(--dark-sub, #b0b0c5); }
body.dark-mode .upd-addsrc-form { border-top-color: var(--dark-admin-border); }

/* ─── Responsive ─── */
@media (max-width: 640px) {
    .upd-hdr-actions { width: 100%; }
    .upd-source-select { max-width: 100%; width: 100%; }
    .upd-src-item { flex-direction: column; align-items: flex-start; }
    .upd-file-row { flex-direction: column; align-items: stretch; }
}
</style>