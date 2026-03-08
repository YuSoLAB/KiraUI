<?php
/**
 * 标签页：个人信息
 * 依赖：$activeTab、$user、$avatarUrl
 */
?>
<div id="profile" class="tab-content <?php echo $activeTab === 'profile' ? 'active' : ''; ?>">
    <div class="profile-section">
        <h2>个人信息</h2>
        <div class="avatar-container">
            <img src="<?php echo htmlspecialchars($avatarUrl); ?>"
                 alt="头像" class="avatar-preview" id="currentAvatar">
            <div id="previewContainer" style="display: none;">
                <img id="avatarPreview" alt="预览" class="avatar-preview">
            </div>
            <div class="avatar-info">
                <h3><?php echo htmlspecialchars($user['nickname']); ?></h3>
                <p><?php echo htmlspecialchars($user['email']); ?></p>
                <p>KID: <?php echo htmlspecialchars($user['id']); ?></p>

                <form method="post" enctype="multipart/form-data"
                      class="avatar-upload" id="avatarForm">
                    <input type="hidden" name="action" value="upload_avatar">
                    <input type="hidden" name="active_tab"
                           value="<?php echo htmlspecialchars($activeTab); ?>">
                    <input type="file" name="avatar"
                           accept="image/jpeg,image/png,image/gif"
                           style="display: none;" id="avatar-upload">
                    <div class="avatar-upload">
                        <label for="avatar-upload" class="btn secondary">选择头像</label>
                        <button type="submit" class="btn primary"
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
                <input type="text" id="nickname" name="nickname"
                       value="<?php echo htmlspecialchars($user['nickname']); ?>"
                       maxlength="50" placeholder="请输入您的昵称">
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