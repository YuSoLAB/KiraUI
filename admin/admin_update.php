<div class="section">
    <h2>系统更新</h2>
    <p><strong>当前版本:</strong> <span id="current-version-text">加载中...</span></p>
    <div class="action-bar column-left">
        <select id="update-source-select" style="max-width:400px;"></select>
        <button id="btn-check-update" class="btn btn-primary" onclick="checkUpdate()">检查更新</button>
        <button class="btn btn-secondary" onclick="showSourceManager()">管理更新源</button>
    </div>

    <div id="update-info" style="display: none;" class="stats-card">
        <h3>发现新版本: <span id="new-version-text" style="color: #e74c3c;"></span></h3>
        <p><strong>更新说明:</strong></p>
        <div id="update-changelog" style="background: rgba(155,140,255,.1); padding: 10px; border-radius: 8px;"></div>
        <div style="margin-top: 15px;">
            <button id="btn-start-update" class="btn btn-warning" onclick="startUpdate(false)">确认并开始在线更新</button>
            <div id="manual-prompt" style="display:none; margin-top:15px; padding:10px; background: #fff3cd; border-radius:8px; color:#856404;"></div>
        </div>
    </div>

    <hr style="margin: 30px 0; border: 0; border-top: 1px dashed var(--admin-border);">

    <div class="form-group">
        <label>手动上传更新包 (ZIP格式)</label>
        <input type="file" id="manual-update-file" accept=".zip" style="margin-bottom: 10px;">
        <button class="btn btn-secondary" onclick="startManualUpdate()">上传并更新</button>
    </div>

    <div id="update-progress-container" style="display:none; margin-top: 20px;">
        <h3>更新进度</h3>
        <div style="width: 100%; background: #eee; border-radius: 8px; overflow: hidden; height: 20px;">
            <div id="progress-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #9b8cff, #ff4db1); transition: width 0.3s;"></div>
        </div>
        <div id="progress-log" style="margin-top: 10px; font-family: monospace; background: #1a1a2e; color: #00ff00; padding: 15px; border-radius: 8px; height: 150px; overflow-y: auto; font-size: 13px;">
            等待开始...
        </div>
    </div>
</div>

<div id="update-source-modal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:var(--admin-bg); border:1px solid var(--admin-border); border-radius:12px; padding:20px; z-index:1000; max-width:500px; width:90%; max-height:80vh; overflow:auto;">
    <h3>管理更新源</h3>
    <div id="source-list"></div>
    <div style="margin-top:15px;">
        <input type="text" id="new-source-name" placeholder="名称" style="width:100%; margin-bottom:5px;">
        <input type="text" id="new-source-url" placeholder="URL" style="width:100%; margin-bottom:5px;">
        <button class="btn btn-primary" onclick="addUpdateSource()">添加</button>
    </div>
    <div style="margin-top:10px; text-align:right;">
        <button class="btn btn-secondary" onclick="closeSourceModal()">关闭</button>
    </div>
</div>
<div id="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:999;" onclick="closeSourceModal()"></div>


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
                div.style.padding = '8px';
                div.style.margin = '5px 0';
                div.style.background = 'rgba(155,140,255,.1)';
                div.style.borderRadius = '6px';
                div.innerHTML = `
                    <strong>${source.name}</strong> <small>${source.url}</small>
                    ${source.url === defaultUrl ? ' (默认)' : ''}
                    <div style="margin-top:5px;">
                        <button class="btn btn-small btn-secondary" onclick="setDefaultSource('${source.url}')">设为默认</button>
                        <button class="btn btn-small btn-danger" onclick="deleteUpdateSource('${source.url}')">删除</button>
                    </div>
                `;
                listDiv.appendChild(div);
            });
            if (sources.length === 0) {
                listDiv.innerHTML = '<p>暂无自定义更新源，使用默认官方源。</p>';
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
    document.getElementById('update-source-modal').style.display = 'block';
    document.getElementById('modal-overlay').style.display = 'block';
    loadUpdateSources();
}

function closeSourceModal() {
    document.getElementById('update-source-modal').style.display = 'none';
    document.getElementById('modal-overlay').style.display = 'none';
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
                            <div style="margin-bottom: 15px; padding: 10px; background: rgba(155,140,255,0.05); border-radius: 6px;">
                                <h4 style="margin:0 0 5px; color: #9b8cff;">v${item.version}</h4>
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
                                <div style="margin-bottom: 10px; padding: 8px; background: #fff3cd; border-radius: 6px;">
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
                            <div>
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
            alert('此更新需要先完成手动操作，请点击“检查更新”重新确认。');
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
        { id: 'backup', name: '备份当前文件与数据库', percent: 20 },
        { id: 'download', name: '获取更新包', percent: 50, skip: isManual },
        { id: 'verify', name: '验证文件完整性', percent: 60, skip: isManual },
        { id: 'extract', name: '解压到缓存目录准备', percent: 75 },
        { id: 'apply', name: '执行覆盖更新', percent: 95 },
        { id: 'cleanup', name: '清理临时文件', percent: 100 }
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
</script>