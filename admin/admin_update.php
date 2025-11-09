<?php
require_once dirname(__DIR__) . '/include/Updater.php';
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(dirname(__FILE__)));
}
$updateResult = [];
$updateInfo = [];
$updater = new Updater();
if (isset($_POST['check_update']) && isset($_GET['action']) && $_GET['action'] === 'check_update') {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }    
    $updateInfo = $updater->checkForUpdates();
    header('Content-Type: application/json');
    echo json_encode($updateInfo);
    exit;
}
if (isset($_POST['check_update'])) {
    $updateInfo = $updater->checkForUpdates();
}
if (isset($_GET['action']) && $_GET['action'] === 'get_progress') {    
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header('Content-Type: application/json');
    echo json_encode($updater->getProgress());
    exit;
}
if (isset($_POST['perform_update']) && isset($_POST['download_url'])) {
    if (!defined('PERFORMING_UPDATE')) {
        define('PERFORMING_UPDATE', true);        
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $updateResult = $updater->performUpdate($_POST['download_url']);
        if ($updateResult['success']) {
            $updateInfo = $updater->checkForUpdates();
        }
    }
}
if (isset($_POST['cleanup_update_files'])) {
    header('Content-Type: application/json');
    echo json_encode(Updater::manualCleanup());
    exit;
}
$versionData = include ROOT_DIR . '/version.php';
?>
<div class="section">
    <h2>系统更新</h2>
    <div class="current-version" style="margin-bottom: 20px;">
        <p>当前版本: <?php echo $versionData['version']; ?></p>
        <?php if ($versionData['last_check'] > 0): ?>
        <p>最后检查更新: <?php echo date('Y-m-d H:i', $versionData['last_check']); ?></p>
        <?php endif; ?>
    </div>
    <form method="post" class="check-update-form" style="margin-bottom: 20px;">
        <button type="submit" name="check_update" class="btn btn-primary" id="checkUpdateBtn">
            检查更新
        </button>
        <div id="updateLoading" style="display: none; margin-left: 10px; color: #666;">
            <span>正在检查更新，请稍候...</span>
            <span class="spinner" style="display: inline-block; width: 16px; height: 16px; border: 2px solid #ccc; border-top-color: #333; border-radius: 50%; animation: spin 1s linear infinite;"></span>
        </div>
    </form>
    <div id="updateResultContainer"></div>
    <style>
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    </style>
    <script>
    function startUpdate(event, form) {
        event.preventDefault();        
        if (!confirm('确定要更新吗？更新过程中网站可能暂时无法访问。')) {
            return;
        }        
        const progressDiv = document.getElementById('updateProgress');
        if (progressDiv) {
            progressDiv.style.display = 'block';
        } else {
            alert('进度条元素未找到，无法开始更新。');
            return;
        }        
        let params = new URLSearchParams(new FormData(form));
        if (!params.has('perform_update')) {
            params.append('perform_update', '1');
        }
        fetch('admin_update.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: params
        }).then(() => {
            console.log('更新请求已发送，开始监控进度');
            checkUpdateProgress();
        }).catch(error => {
            console.log('更新请求发送失败（网络错误）', error);
            progressDiv.querySelector('#progressMessage').textContent = '更新请求发送失败，请检查网络';
            progressDiv.querySelector('#progressMessage').style.color = 'red';
        });
    }
    function checkUpdateProgress() {
        const progressBar = document.getElementById('progressBar');
        const progressMessage = document.getElementById('progressMessage');
        const progressStatus = document.getElementById('progressStatus');        
        if (!progressBar || !progressMessage || !progressStatus) {
            console.error('进度条组件缺失，停止轮询。');
            return;
        }
        fetch('admin_update.php?action=get_progress')
            .then(response => response.json())
            .then(data => {
                progressBar.style.width = data.percentage + '%';
                progressMessage.textContent = data.message;
                progressStatus.textContent = `步骤 ${data.step}/9 (${data.percentage}%)`;
                if (data.percentage < 100 && data.step != -1) {
                    setTimeout(checkUpdateProgress, 1000);
                } else if (data.step == -1) {
                    progressMessage.style.color = 'red';
                    setTimeout(() => {
                        alert('更新失败: ' + data.message);
                    }, 1000);
                } else {
                    progressMessage.style.color = 'green';
                    setTimeout(() => {
                        alert('更新成功！页面将刷新以应用更改。');
                        window.location.reload();
                    }, 2000);
                }
            })
            .catch(error => {
                progressMessage.textContent = '获取进度失败，正在重试...';
                setTimeout(checkUpdateProgress, 2000);
            });
    }
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('.check-update-form');
        const btn = document.getElementById('checkUpdateBtn');
        const loading = document.getElementById('updateLoading');
        const resultContainer = document.getElementById('updateResultContainer');
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            btn.disabled = true;
            loading.style.display = 'inline-block';
            resultContainer.innerHTML = '';
            try {
                const response = await fetch('admin_update.php?action=check_update', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'check_update=1',
                    timeout: 10000
                });
                if (!response.ok) throw new Error('网络请求失败');                
                const data = await response.json();
                if (data.success) {
                    if (data.has_update) {
                        resultContainer.innerHTML = `
                            <div class="update-available" style="padding: 15px; border-radius: 8px; background: #e8f5e9; margin-top: 10px;">
                                <h3>发现新版本: ${data.latest_version}</h3>
                                <div class="changelog" style="margin: 15px 0; padding: 10px; background: rgba(255,255,255,0.5); border-radius: 4px; max-height: 300px; overflow-y: auto;">
                                    <h4>更新内容:</h4>
                                    <pre>${data.changelog}</pre>
                                </div>
                                <form method="post" class="perform-update-form" onsubmit="startUpdate(event, this); return false;">
                                    <input type="hidden" name="download_url" value="${data.download_url}">
                                    <button type="submit" name="perform_update" class="btn btn-danger">立即更新</button>
                                </form>
                            </div>
                            <div id="updateProgress" style="display: none; margin-top: 20px;">
                                <div class="progress-container" style="width: 100%; background-color: #eee; border-radius: 5px;">
                                    <div id="progressBar" style="width: 0%; height: 30px; border-radius: 5px; background-color: #4CAF50; transition: width 0.3s ease;"></div>
                                </div>
                                <div id="progressMessage" style="margin-top: 10px; color: #666;"></div>
                                <div id="progressStatus" style="font-size: 0.9em; color: #888; margin-top: 5px;"></div>
                            </div>
                            `;
                    } else {
                        resultContainer.innerHTML = `
                            <div class="no-update" style="padding: 15px; border-radius: 8px; background: #e8f5e9; margin-top: 10px;">
                                <p>当前已是最新版本</p>
                            </div>
                        `;
                    }
                } else {
                    resultContainer.innerHTML = `
                        <div class="update-error message error" style="margin-top: 10px;">
                            <p>检查更新失败: ${data.message}</p>
                        </div>
                    `;
                }
            } catch (error) {
                resultContainer.innerHTML = `
                    <div class="update-error message error" style="margin-top: 10px;">
                        <p>检查更新超时或出错: ${error.message}</p>
                    </div>
                `;
            } finally {
                btn.disabled = false;
                loading.style.display = 'none';
            }
        });
    });
    </script>
    <?php if (!empty($updateInfo)): ?>
        <?php if ($updateInfo['success']): ?>
            <?php if ($updateInfo['has_update']): ?>
                <div class="update-available" style="padding: 15px; border-radius: 8px; background: #e8f5e9; margin-bottom: 20px;">
                    <h3>发现新版本: <?php echo $updateInfo['latest_version']; ?></h3>
                    <div class="changelog" style="margin: 15px 0; padding: 10px; background: rgba(255,255,255,0.5); border-radius: 4px; max-height: 300px; overflow-y: auto;">
                        <h4>更新内容:</h4>
                        <pre><?php echo nl2br(htmlspecialchars($updateInfo['changelog'])); ?></pre>
                    </div>
                    <form method="post" class="perform-update-form" onsubmit="startUpdate(event, this); return false;">
                        <input type="hidden" name="download_url" value="<?php echo htmlspecialchars($updateInfo['download_url']); ?>">
                        <button type="submit" name="perform_update" class="btn btn-danger">立即更新</button>
                    </form>
                </div>
                <div id="updateProgress" style="display: none; margin-top: 20px;">
                    <div class="progress-container" style="width: 100%; background-color: #eee; border-radius: 5px;">
                        <div id="progressBar" style="width: 0%; height: 30px; border-radius: 5px; background-color: #4CAF50; transition: width 0.3s ease;"></div>
                    </div>
                    <div id="progressMessage" style="margin-top: 10px; color: #666;"></div>
                    <div id="progressStatus" style="font-size: 0.9em; color: #888; margin-top: 5px;"></div>
                </div>
            <?php else: ?>
                <div class="no-update" style="padding: 15px; border-radius: 8px; background: #e8f5e9; margin-bottom: 20px;">
                    <p>当前已是最新版本</p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="update-error message error">
                <p>检查更新失败: <?php echo $updateInfo['message']; ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($updateResult)): ?>
        <?php if ($updateResult['success']): ?>
            <div class="update-success message">
                <p><?php echo $updateResult['message']; ?> 请刷新页面查看更新内容。</p>
            </div>
        <?php else: ?>
            <div class="update-error message error">
                <p><?php echo $updateResult['message']; ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>