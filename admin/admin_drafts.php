<?php
?>
<div class="tab-content" id="drafts">
    <div class="section">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2>草稿箱</h2>
            <a href="?page=edit_draft&edit=new" class="btn btn-primary">新建草稿</a>
        </div>
        <ul class="article-list">
            <?php 
            $drafts = getDrafts();
            if (!empty($drafts)): ?>
                <?php foreach ($drafts as $draft): ?>
                    <li class="article-item">
                        <div>
                            <strong><?php echo htmlspecialchars($draft['title']); ?></strong>
                            <div style="font-size: 0.9em; color: #666;">
                                ID: <?php echo $draft['id']; ?> | 
                                日期: <?php echo $draft['date']; ?> |
                                标签: <?php echo implode(', ', $draft['tags']); ?> |
                                字数: <?php echo $draft['word_count'] ?? 0; ?>
                            </div>
                        </div>
                        <div class="article-actions">
                            <a href="?page=edit_draft&edit=<?php echo $draft['id']; ?>" class="btn btn-info">编辑</a>
                            <a href="../draft_preview.php?id=<?php echo $draft['id']; ?>" class="btn btn-secondary" target="_blank">预览</a>
                            <form method="post" onsubmit="return confirm('确定要发布这篇草稿吗？');" style="display: inline;">
                                <input type="hidden" name="action" value="publish_draft">
                                <input type="hidden" name="id" value="<?php echo $draft['id']; ?>">
                                <button type="submit" class="btn btn-success">发布</button>
                            </form>
                            <form method="post" onsubmit="return confirm('确定要删除这篇草稿吗？');" style="display: inline;">
                                <input type="hidden" name="action" value="delete_draft">
                                <input type="hidden" name="id" value="<?php echo $draft['id']; ?>">
                                <button type="submit" class="btn btn-danger">删除</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li class="article-item">草稿箱为空</li>
            <?php endif; ?>
        </ul>
    </div>
</div>