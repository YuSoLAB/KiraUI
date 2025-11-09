<?php
?>
<div class="tab-content" id="articles">
    <div class="section">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2>文章列表</h2>
            <a href="?page=edit_article&edit=new" class="btn btn-primary">发布新文章</a>
        </div>
        <ul class="article-list">
            <?php if (!empty($articles)): ?>
                <?php foreach ($articles as $article): ?>
                    <li class="article-item">
                        <div>
                            <strong><?php echo htmlspecialchars($article['title']); ?></strong>
                            <div style="font-size: 0.9em; color: #666;">
                                ID: <?php echo $article['id']; ?> | 
                                日期: <?php echo $article['date']; ?> |
                                标签: <?php echo implode(', ', $article['tags']); ?> |
                                字数: <?php echo $article['word_count'] ?? 0; ?> |
                                阅读时长: <?php echo $article['read_time'] ?? 0; ?> 分钟
                            </div>
                        </div>
                        <div class="article-actions">
                            <a href="?page=edit_article&edit=<?php echo intval($article['id']); ?>" class="btn btn-info">编辑</a>
                            <form method="post" onsubmit="return confirm('确定要将这篇文章移至草稿箱吗？');" style="display: inline;">
                                <input type="hidden" name="action" value="move_to_draft">
                                <input type="hidden" name="id" value="<?php echo $article['id']; ?>">
                                <button type="submit" class="btn btn-warning">移至草稿箱</button>
                            </form>
                            <form method="post" onsubmit="return confirm('确定要删除这篇文章吗？');" style="display: inline;">
                                <input type="hidden" name="action" value="delete_article">
                                <input type="hidden" name="id" value="<?php echo $article['id']; ?>">
                                <button type="submit" class="btn btn-danger">删除</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li class="article-item">没有找到文章</li>
            <?php endif; ?>
        </ul>
    </div>
</div>