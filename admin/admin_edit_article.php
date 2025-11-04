<?php
?>
<div class="tab-content" id="edit-article">
    <div class="section">
        <h2>
            <?php echo $isNewArticle ? '发布新文章' : '编辑文章'; ?>
            <?php if (!$isNewArticle): ?>
                <small style="color: #666;">(ID: <?php echo $currentArticle['id'] ?? '未知'; ?>)</small>
            <?php endif; ?>
        </h2>
        
        <?php if (!$isNewArticle && empty($currentArticle)): ?>
            <div class="message error">文章不存在！</div>
            <a href="?page=articles" class="btn btn-warning">返回文章管理</a>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="action" value="save_article">
                <?php if (!$isNewArticle): ?>
                    <input type="hidden" name="id" value="<?php echo $currentArticle['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="title">文章标题 <span style="color: red;">*</span></label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($currentArticle['title'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="date">发布日期</label>
                    <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($currentArticle['date'] ?? date('Y-m-d')); ?>">
                </div>
                
                <div class="form-group">
                    <label for="tags">标签 (用逗号分隔)</label>
                    <input type="text" id="tags" name="tags" value="<?php 
                        $tags = $currentArticle['tags'] ?? '';
                        if (is_string($tags)) {
                            $tags = explode(',', $tags);
                            $tags = array_map('trim', $tags);
                        }
                        echo htmlspecialchars(implode(', ', $tags));
                    ?>">
                </div>
                
                <div class="form-group">
                    <label for="excerpt">摘要</label>
                    <textarea id="excerpt" name="excerpt" rows="3"><?php echo htmlspecialchars($currentArticle['excerpt'] ?? ''); ?></textarea>
                </div>
                
                <?php if (isset($currentArticle)): ?>
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
                    <label for="content">文章内容 <span style="color: red;">*</span></label>
                    <div class="code-editor">
                        <textarea id="content" name="content" style="display: none;"><?php echo htmlspecialchars($currentArticle['content'] ?? ''); ?></textarea>
                        <div id="content_editor" style="border: 1px solid #ddd; border-radius: 4px;"></div>
                    </div>
                    
                    <div style="margin-top: 10px; font-size: 0.9em; color: #666;">
                        <span id="word-count">字数: 0</span> | 
                        <span id="read-time">阅读时长: 0 分钟</span>
                    </div>
                    <small>提示: 可以使用HTML标签进行格式化</small>
                </div>
                
                <div>
                    <button type="submit" class="btn btn-primary">发布文章</button>
                    <a href="?page=articles" class="btn btn-warning">取消</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>