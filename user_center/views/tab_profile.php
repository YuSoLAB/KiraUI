<?php
/**
 * 标签页：个人信息
 * 依赖：$activeTab、$user、$avatarUrl
 */
?>
<div id="profile" class="tab-content <?php echo $activeTab === 'profile' ? 'active' : ''; ?>">
    <div class="profile-section">
        <h2>个人信息</h2>

        <?php
        // 查询该用户是否有待审核的变更
        $pendingNickname = null;
        $pendingAvatar   = null;
        try {
            $pStmt = $db->prepare(
                "SELECT type, new_value FROM pending_profile_changes
                  WHERE user_id = ? AND status = 'pending'
                  ORDER BY created_at DESC"
            );
            $pStmt->execute([$user['id']]);
            foreach ($pStmt->fetchAll(PDO::FETCH_ASSOC) as $pr) {
                if ($pr['type'] === 'nickname' && $pendingNickname === null) {
                    $pendingNickname = $pr['new_value'];
                } elseif ($pr['type'] === 'avatar' && $pendingAvatar === null) {
                    $pendingAvatar = $pr['new_value'];
                }
            }
        } catch (Exception $e) {}

        // 最近被拒绝的变更（用于提示用户）
        // 排除条件：① 系统自动写入的"已被新申请替代" ② 该 type 之后已有更新记录（重新提交或已通过）
        $rejectedItems = [];
        try {
            $rStmt = $db->prepare(
                "SELECT p.id, p.type, p.reject_reason, p.reviewed_at
                   FROM pending_profile_changes p
                  WHERE p.user_id = ?
                    AND p.status = 'rejected'
                    AND p.reject_reason != '已被新申请替代'
                    AND p.reviewed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                    AND NOT EXISTS (
                          SELECT 1 FROM pending_profile_changes p2
                           WHERE p2.user_id = p.user_id
                             AND p2.type    = p.type
                             AND p2.id      > p.id
                        )
                  ORDER BY p.reviewed_at DESC
                  LIMIT 4"
            );
            $rStmt->execute([$user['id']]);
            $rejectedItems = $rStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // 显示拒绝通知（带关闭按钮，关闭状态存入 localStorage 避免刷新复现）
        foreach ($rejectedItems as $ri):
            $noticeKey = 'profile_reject_dismissed_' . $ri['id'];
        ?>
        <div class="profile-reject-notice"
             data-notice-key="<?php echo htmlspecialchars($noticeKey); ?>"
             style="background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.3);
                    border-radius:10px;padding:10px 14px;margin-bottom:12px;font-size:.84rem;
                    color:#f87171;display:flex;align-items:flex-start;gap:8px;">
            <span style="flex:1;">
                ⚠️ 您的<?php echo $ri['type'] === 'nickname' ? '昵称' : '头像'; ?>变更申请已被拒绝
                <?php if ($ri['reject_reason']): ?>
                    ：<?php echo htmlspecialchars($ri['reject_reason']); ?>
                <?php endif; ?>
            </span>
            <button onclick="dismissProfileNotice(this)"
                    title="关闭"
                    style="background:none;border:none;cursor:pointer;color:#f87171;
                           opacity:.7;padding:0;line-height:1;flex-shrink:0;font-size:1rem;">✕</button>
        </div>
        <?php endforeach; ?>
        <script>
        // 关闭拒绝通知，记入 localStorage，刷新后不再显示
        function dismissProfileNotice(btn) {
            var el = btn.closest('.profile-reject-notice');
            if (!el) return;
            try { localStorage.setItem(el.dataset.noticeKey, '1'); } catch(e) {}
            el.style.transition = 'opacity .2s,transform .2s';
            el.style.opacity = '0';
            el.style.transform = 'translateY(-4px)';
            setTimeout(function(){ el.remove(); }, 220);
        }
        // 页面加载时隐藏已被关闭过的通知
        document.querySelectorAll('.profile-reject-notice').forEach(function(el) {
            try {
                if (localStorage.getItem(el.dataset.noticeKey)) el.remove();
            } catch(e) {}
        });
        </script>

        <div class="avatar-container">
            <img src="<?php echo htmlspecialchars($avatarUrl); ?>"
                 alt="头像" class="avatar-preview" id="currentAvatar">
            <div class="avatar-info">
                <h3><?php
                    // 昵称显示：有昵称用昵称，否则用用户名兜底
                    echo htmlspecialchars($user['nickname'] ?: $user['username']);
                ?></h3>
                <p><?php echo htmlspecialchars($user['email']); ?></p>
                <p>KID: <?php echo htmlspecialchars($user['id']); ?></p>

                <?php if ($pendingAvatar): ?>
                <div style="background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.3);
                            border-radius:8px;padding:8px 12px;margin-bottom:8px;font-size:.8rem;color:#fbbf24;">
                    🕐 头像变更审核中，通过后将自动生效
                </div>
                <?php endif; ?>
                <form id="avatarForm" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_avatar">
                    <input type="hidden" name="active_tab"
                           value="<?php echo htmlspecialchars($activeTab); ?>">
                    <input type="file" name="avatar"
                           accept="image/jpeg,image/png,image/gif"
                           style="display: none;" id="avatar-upload">
                    <div class="avatar-upload">
                        <label for="avatar-upload" class="btn secondary">选择头像</label>
                        <button type="button" class="btn primary"
                                id="uploadButton" disabled>上传头像</button>
                    </div>
                </form>

                <div id="uploadProgress"
                     style="display:none;margin-top:10px;width:100%;
                            background:rgba(155,140,255,.18);border-radius:6px;">
                    <div id="progressBar"
                         style="width:0%;height:8px;border-radius:6px;
                                background:#6c5dfb;transition:width 0.3s ease;">
                    </div>
                </div>
                <div id="uploadMessage" style="margin-top:8px;font-size:.82rem;color:var(--sub);"></div>
            </div>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="update_nickname">
            <input type="hidden" name="active_tab"
                   value="<?php echo htmlspecialchars($activeTab); ?>">
            <div class="form-group">
                <label for="nickname">昵称</label>
                <?php if ($pendingNickname !== null): ?>
                <div style="background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.3);
                            border-radius:8px;padding:6px 12px;margin-bottom:8px;font-size:.8rem;color:#fbbf24;">
                    🕐 昵称「<?php echo htmlspecialchars($pendingNickname); ?>」审核中，通过后将自动生效
                </div>
                <?php endif; ?>
                <input type="text" id="nickname" name="nickname"
                       value="<?php echo htmlspecialchars($user['nickname'] ?? ''); ?>"
                       maxlength="50" placeholder="请输入您的昵称（留空则显示用户名）">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn primary">更新昵称</button>
                <a href="index.php?action=logout" class="btn-logout"
                   onclick="return confirm('确定要退出登录吗？');">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2"
                         style="margin-right:6px;">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    退出账号
                </a>
            </div>
        </form>
    </div>
</div>

<!-- ==================== 裁剪弹窗 ==================== -->
<div id="cropModal" class="crop-modal-overlay" style="display:none;" aria-modal="true" role="dialog">
    <div class="crop-modal-box">
        <div class="crop-modal-header">
            <span class="crop-modal-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" style="margin-right:6px;vertical-align:-3px;">
                    <path d="M6 2v14a2 2 0 0 0 2 2h14"/>
                    <path d="M18 22V8a2 2 0 0 0-2-2H2"/>
                </svg>
                裁剪头像
            </span>
            <button id="cropModalClose" class="crop-modal-close" aria-label="关闭">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <div class="crop-modal-body">
            <div class="crop-canvas-wrap">
                <img id="cropImage" alt="待裁剪图片" style="max-width:100%;display:block;">
            </div>
            <div class="crop-tip">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px;">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                拖动或缩放选框以调整裁剪区域，头像将裁剪为圆形
            </div>
        </div>

        <div class="crop-modal-footer">
            <div class="crop-zoom-controls">
                <button class="crop-zoom-btn" id="zoomOut" title="缩小">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.2">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        <line x1="8" y1="11" x2="14" y2="11"/>
                    </svg>
                </button>
                <button class="crop-zoom-btn" id="zoomIn" title="放大">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.2">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        <line x1="11" y1="8" x2="11" y2="14"/>
                        <line x1="8" y1="11" x2="14" y2="11"/>
                    </svg>
                </button>
                <button class="crop-zoom-btn" id="rotateLeft" title="向左旋转">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.2">
                        <polyline points="1 4 1 10 7 10"/>
                        <path d="M3.51 15a9 9 0 1 0 .49-3.51"/>
                    </svg>
                </button>
                <button class="crop-zoom-btn" id="rotateRight" title="向右旋转">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.2">
                        <polyline points="23 4 23 10 17 10"/>
                        <path d="M20.49 15a9 9 0 1 1-.49-3.51"/>
                    </svg>
                </button>
            </div>
            <div class="crop-action-btns">
                <button id="cropCancel" class="btn secondary">取消</button>
                <button id="cropConfirm" class="btn primary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.2"
                         style="margin-right:5px;vertical-align:-2px;">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    确认裁剪
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== Cropper.js ==================== -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>

<style>
/* ---------- 裁剪弹窗样式 ---------- */
.crop-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(10, 8, 28, 0.72);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    display: flex;
    align-items: center;
    justify-content: center;
    animation: cropOverlayIn .18s ease;
}
@keyframes cropOverlayIn {
    from { opacity: 0; }
    to   { opacity: 1; }
}

.crop-modal-box {
    background: var(--card, #1a1730);
    border: 1px solid rgba(155,140,255,.22);
    border-radius: 16px;
    width: min(520px, 95vw);
    box-shadow: 0 24px 64px rgba(0,0,0,.55), 0 0 0 1px rgba(108,93,251,.12);
    animation: cropBoxIn .22s cubic-bezier(.34,1.4,.64,1);
    overflow: hidden;
}
@keyframes cropBoxIn {
    from { opacity: 0; transform: scale(.93) translateY(12px); }
    to   { opacity: 1; transform: scale(1)  translateY(0);     }
}

.crop-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px 14px;
    border-bottom: 1px solid rgba(155,140,255,.12);
}

.crop-modal-title {
    font-size: .95rem;
    font-weight: 600;
    color: var(--text, #e8e2ff);
    display: flex;
    align-items: center;
}

.crop-modal-close {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--sub, #a09ac0);
    padding: 4px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    transition: color .15s, background .15s;
}
.crop-modal-close:hover {
    color: var(--text, #e8e2ff);
    background: rgba(155,140,255,.12);
}

.crop-modal-body {
    padding: 18px 20px 14px;
}

.crop-canvas-wrap {
    width: 100%;
    max-height: 340px;
    overflow: hidden;
    border-radius: 10px;
    background: #0d0b1e;
    border: 1px solid rgba(155,140,255,.1);
}

/* Cropper.js 内部圆形预览覆盖 */
.crop-canvas-wrap .cropper-view-box,
.crop-canvas-wrap .cropper-face {
    border-radius: 50%;
}

.crop-tip {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    margin-top: 10px;
    font-size: .78rem;
    color: var(--sub, #a09ac0);
    line-height: 1.5;
}

.crop-modal-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px 18px;
    border-top: 1px solid rgba(155,140,255,.12);
    gap: 10px;
    flex-wrap: wrap;
}

.crop-zoom-controls {
    display: flex;
    gap: 6px;
}

.crop-zoom-btn {
    background: rgba(155,140,255,.1);
    border: 1px solid rgba(155,140,255,.2);
    border-radius: 8px;
    color: var(--sub, #a09ac0);
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background .15s, color .15s, border-color .15s;
}
.crop-zoom-btn:hover {
    background: rgba(108,93,251,.25);
    border-color: rgba(108,93,251,.45);
    color: var(--text, #e8e2ff);
}

.crop-action-btns {
    display: flex;
    gap: 10px;
}

/* 上传中按钮禁用态 */
#uploadButton:disabled {
    opacity: .45;
    cursor: not-allowed;
}

/* 头像预览圆形过渡 */
#currentAvatar {
    transition: opacity .3s ease, transform .3s ease;
}
#currentAvatar.refreshing {
    opacity: 0;
    transform: scale(.85);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
(function () {
    const fileInput     = document.getElementById('avatar-upload');
    const uploadButton  = document.getElementById('uploadButton');
    const currentAvatar = document.getElementById('currentAvatar');
    const uploadProgress= document.getElementById('uploadProgress');
    const progressBar   = document.getElementById('progressBar');
    const uploadMessage = document.getElementById('uploadMessage');

    const cropModal     = document.getElementById('cropModal');
    const cropImage     = document.getElementById('cropImage');
    const cropConfirm   = document.getElementById('cropConfirm');
    const cropCancel    = document.getElementById('cropCancel');
    const cropModalClose= document.getElementById('cropModalClose');

    // 安全检查：若关键元素不存在则直接退出，避免整个脚本崩溃
    if (!fileInput || !uploadButton || !currentAvatar || !cropModal || !cropImage) {
        console.error('[Avatar] 初始化失败：找不到必要的 DOM 元素，请检查 HTML 结构。', {
            fileInput, uploadButton, currentAvatar, cropModal, cropImage
        });
        return;
    }

    let cropper = null;
    let croppedBlob = null;   // 裁剪后的 Blob
    let originalFile = null;  // 原始文件（用于获取扩展名）

    /* ---------- 工具函数 ---------- */
    function showMessage(msg, isError) {
        uploadMessage.style.color = isError ? '#f87171' : '#a3e4b4';
        uploadMessage.textContent = msg;
    }

    function setProgress(pct) {
        uploadProgress.style.display = pct > 0 ? 'block' : 'none';
        progressBar.style.width = pct + '%';
    }

    /* ---------- 工具：格式化文件大小 ---------- */
    function formatSize(bytes) {
        if (bytes >= 1024 * 1024) return (bytes / 1024 / 1024).toFixed(1) + ' MB';
        return (bytes / 1024).toFixed(0) + ' KB';
    }

    /* ---------- 1. 选文件 → 打开裁剪弹窗 ---------- */
    fileInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;

        // 仅校验格式，不限制原始文件大小（上传的是裁剪后 Blob，通常 < 300 KB）
        if (!['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) {
            showMessage('只支持 jpg / png / gif 格式的图片', true);
            this.value = '';
            return;
        }

        originalFile = file;
        croppedBlob  = null;
        uploadButton.disabled = true;

        // 提示原始大小，让用户知道实际上传的是裁剪后的小图
        const sizeHint = file.size > 1024 * 1024
            ? `原图 ${formatSize(file.size)}，裁剪后将自动压缩`
            : `原图 ${formatSize(file.size)}`;
        showMessage(sizeHint, false);

        const reader = new FileReader();
        reader.onload = function (e) {
            cropImage.src = e.target.result;
            openCropModal();
        };
        reader.readAsDataURL(file);
    });

    /* ---------- 2. 打开 / 关闭弹窗 ---------- */
    function openCropModal() {
        cropModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        // 等图片渲染后初始化 Cropper
        cropImage.onload = function () {
            if (cropper) { cropper.destroy(); cropper = null; }
            cropper = new Cropper(cropImage, {
                aspectRatio: 1,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.8,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false,
            });
        };
        // 若图片已缓存 onload 不触发
        if (cropImage.complete) cropImage.onload();
    }

    function closeCropModal() {
        cropModal.style.display = 'none';
        document.body.style.overflow = '';
        if (cropper) { cropper.destroy(); cropper = null; }
        // 若未确认裁剪则清空 input
        if (!croppedBlob) {
            fileInput.value = '';
            uploadButton.disabled = true;
        }
    }

    cropModalClose.addEventListener('click', closeCropModal);
    cropCancel.addEventListener('click', closeCropModal);
    cropModal.addEventListener('click', function (e) {
        if (e.target === cropModal) closeCropModal();
    });

    /* ---------- 3. 缩放 / 旋转按钮 ---------- */
    document.getElementById('zoomIn').addEventListener('click',
        () => cropper && cropper.zoom(0.1));
    document.getElementById('zoomOut').addEventListener('click',
        () => cropper && cropper.zoom(-0.1));
    document.getElementById('rotateLeft').addEventListener('click',
        () => cropper && cropper.rotate(-90));
    document.getElementById('rotateRight').addEventListener('click',
        () => cropper && cropper.rotate(90));

    /* ---------- 4. 确认裁剪 → 生成预览 + Blob ---------- */
    cropConfirm.addEventListener('click', function () {
        if (!cropper) return;

        // 输出 512×512 的高清画布
        const canvas = cropper.getCroppedCanvas({ width: 512, height: 512 });
        if (!canvas) { showMessage('裁剪失败，请重试', true); return; }

        // 圆形遮罩：在同尺寸画布上剪裁为圆
        const circleCanvas = document.createElement('canvas');
        circleCanvas.width  = 512;
        circleCanvas.height = 512;
        const ctx = circleCanvas.getContext('2d');
        ctx.beginPath();
        ctx.arc(256, 256, 256, 0, Math.PI * 2);
        ctx.closePath();
        ctx.clip();
        ctx.drawImage(canvas, 0, 0);

        // 更新当前头像预览（动画）
        currentAvatar.classList.add('refreshing');
        const previewUrl = circleCanvas.toDataURL('image/png');
        setTimeout(() => {
            currentAvatar.src = previewUrl;
            currentAvatar.classList.remove('refreshing');
        }, 300);

        // 转为 Blob，供后续上传
        circleCanvas.toBlob(function (blob) {
            croppedBlob = blob;
            uploadButton.disabled = false;
            closeCropModal();
            showMessage('已完成裁剪，点击「上传头像」保存', false);
        }, 'image/png');
    });

    /* ---------- 5. 上传裁剪后的图片 ---------- */
    uploadButton.addEventListener('click', function () {
        if (!croppedBlob) return;

        const ext      = (originalFile.name.split('.').pop() || 'png').toLowerCase();
        const filename = 'avatar.' + ext;

        const formData = new FormData();
        formData.append('action',     'upload_avatar');
        formData.append('active_tab', document.querySelector('[name="active_tab"]').value);
        formData.append('avatar',     croppedBlob, filename);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'index.php', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) {
                setProgress(Math.round(e.loaded / e.total * 100));
            }
        });

        xhr.addEventListener('load', function () {
            setProgress(0);
            try {
                const res = JSON.parse(xhr.responseText);
                if (res.success) {
                    showMessage('✓ ' + res.message, false);
                    if (res.avatarUrl) {
                        currentAvatar.classList.add('refreshing');
                        setTimeout(() => {
                            currentAvatar.src = res.avatarUrl + '?t=' + Date.now();
                            currentAvatar.classList.remove('refreshing');
                        }, 300);
                    }
                    croppedBlob  = null;
                    originalFile = null;
                    fileInput.value = '';
                    uploadButton.disabled = true;
                } else {
                    showMessage(res.message || '上传失败', true);
                }
            } catch (e) {
                showMessage('服务器响应异常', true);
            }
        });

        xhr.addEventListener('error', function () {
            setProgress(0);
            showMessage('网络错误，请重试', true);
        });

        uploadButton.disabled = true;
        showMessage('上传中…', false);
        setProgress(1); // 占位让进度条可见
        xhr.send(formData);
    });

    /* ---------- ESC 关闭弹窗 ---------- */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && cropModal.style.display !== 'none') {
            closeCropModal();
        }
    });
})();
}); // DOMContentLoaded
</script>