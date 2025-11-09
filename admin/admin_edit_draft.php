<?php
?>
<div class="tab-content" id="edit-draft">
    <div class="section">
        <h2>
            <?php echo $isNewDraft ? '新建草稿' : '编辑草稿'; ?>
            <?php if (!$isNewDraft): ?>
                <small style="color: #666;">(ID: <?php echo $currentArticle['id'] ?? ''; ?>)</small>
            <?php endif; ?>
        </h2>
        <?php if (!$isNewDraft && empty($currentDraft)): ?>
            <div class="message error">草稿不存在！</div>
            <a href="?page=drafts" class="btn btn-warning">返回草稿箱</a>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="action" value="save_draft">
                <?php if (!$isNewDraft): ?>
                    <input type="hidden" name="id" value="<?php echo $currentDraft['id']; ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="title">草稿标题 <span style="color: red;">*</span></label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($currentDraft['title'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="date">日期</label>
                    <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($currentDraft['date'] ?? date('Y-m-d')); ?>">
                </div>
                <div class="form-group">
                    <label for="tags">标签 (用逗号分隔)</label>
                    <input type="text" id="tags" name="tags" value="<?php echo htmlspecialchars(implode(', ', $currentDraft['tags'] ?? [])); ?>">
                </div>
                <div class="form-group">
                    <label for="excerpt">摘要</label>
                    <textarea id="excerpt" name="excerpt" rows="3"><?php echo htmlspecialchars($currentDraft['excerpt'] ?? ''); ?></textarea>
                </div>
                <?php if (isset($currentDraft)): ?>
                    <div class="shortcode-toolbar" style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-radius: 6px; border: 1px solid #eee;">
                        <p style="margin: 0 0 8px; font-weight: bold;">短代码辅助：</p>
                        <div class="shortcode-buttons">
                            <button type="button" class="shortcode-btn" data-type="image">插入图片</button>
                            <button type="button" class="shortcode-btn" data-type="video">插入视频</button>
                            <button type="button" class="shortcode-btn" data-type="code">代码框</button>
                            <button type="button" class="shortcode-btn" data-type="link">链接按钮</button>
                            <button type="button" class="shortcode-btn" data-type="download">下载按钮</button>
                            <button type="button" class="shortcode-btn" data-type="encrypted_download">加密下载按钮</button>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="content">草稿内容 <span style="color: red;">*</span></label>
                    <div class="code-editor">
                        <textarea id="content" name="content" style="display: none;"><?php echo htmlspecialchars($currentDraft['content'] ?? ''); ?></textarea>
                        <div id="content_editor" style="border: 1px solid #ddd; border-radius: 4px;"></div>
                    </div>
                    <div style="margin-top: 10px; font-size: 0.9em; color: #666;">
                        <span id="word-count">字数: 0</span> | 
                        <span id="read-time">阅读时长: 0 分钟</span>
                    </div>
                    <small>提示: 可以使用HTML标签进行格式化</small>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">保存草稿</button>
                    <a href="../draft_preview.php?id=<?php echo $currentDraft['id']; ?>" class="btn btn-secondary" target="_blank">预览草稿</a>
                    <?php if (!$isNewDraft): ?>
                        <button type="submit" name="action" value="publish_draft" class="btn btn-success" onclick="return confirm('确定要发布这篇草稿吗？');">发布为文章</button>
                    <?php endif; ?>
                    <a href="?page=drafts" class="btn btn-warning">取消</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>