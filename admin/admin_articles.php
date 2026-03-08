<?php
// 管理员文章管理
?>
<style>
/* ── articles ── */
.art-meta { font-size:.78rem; color:var(--sub,#888); margin-top:.2rem; display:flex; flex-wrap:wrap; gap:.3rem .7rem; }
.art-meta span { display:inline-flex; align-items:center; gap:.2rem; }
.art-mrow { display:grid; grid-template-columns:1fr auto; gap:.75rem; align-items:center; padding:.7rem 1rem; border-bottom:1px solid rgba(155,140,255,.12); }
.art-mrow:last-child { border-bottom:none; }
.art-mrow:hover { background:rgba(155,140,255,.05); }
.art-acts { display:flex; gap:.25rem; flex-wrap:wrap; align-items:center; flex-shrink:0; }
.art-title { font-weight:700; color:inherit; font-size:.95rem; }
body.dark-mode .art-mrow:hover { background:rgba(176,160,255,.06); }
body.dark-mode .art-mrow { border-bottom-color:rgba(176,160,255,.12); }
@media(max-width:640px){
    .art-mrow { grid-template-columns:1fr; }
    .art-acts { justify-content:flex-start; }
}
</style>

<div class="admin-section">

    <div class="mhdr">
        <div>
            <h2 class="mhdr-title">📝 文章管理</h2>
            <p class="mhdr-sub">管理已发布的文章，可编辑、移至草稿或永久删除。</p>
        </div>
        <a href="?page=edit_article&edit=new" class="btn btn-primary">＋ 发布新文章</a>
    </div>

    <div class="mbuilder">
        <div class="mhead" style="grid-template-columns:1fr auto;">
            <span>文章信息</span>
            <span>操作</span>
        </div>

        <?php if (!empty($articles)): ?>
            <?php foreach ($articles as $article): ?>
            <div class="art-mrow">
                <div>
                    <div class="art-title"><?php echo htmlspecialchars($article['title']); ?></div>
                    <div class="art-meta">
                        <span>🆔 <?php echo $article['id']; ?></span>
                        <span>📅 <?php echo $article['date']; ?></span>
                        <span>🏷️ <?php echo htmlspecialchars(implode(', ', $article['tags'])); ?></span>
                        <span>📖 <?php echo $article['word_count'] ?? 0; ?> 字</span>
                        <span>⏱ <?php echo $article['read_time'] ?? 0; ?> 分钟</span>
                    </div>
                </div>
                <div class="art-acts">
                    <a href="?page=edit_article&edit=<?php echo intval($article['id']); ?>" class="btn btn-xs mbtn-e">编辑</a>
                    <form method="post" onsubmit="return confirm('确定要将这篇文章移至草稿箱吗？');" style="display:inline;">
                        <input type="hidden" name="action" value="move_to_draft">
                        <input type="hidden" name="id" value="<?php echo $article['id']; ?>">
                        <button type="submit" class="btn btn-xs mbtn-t">移至草稿</button>
                    </form>
                    <form method="post" onsubmit="return confirm('确定要删除这篇文章吗？');" style="display:inline;">
                        <input type="hidden" name="action" value="delete_article">
                        <input type="hidden" name="id" value="<?php echo $article['id']; ?>">
                        <button type="submit" class="btn btn-xs mbtn-d">删除</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="mempty">暂无已发布文章，点击「发布新文章」开始创作。</p>
        <?php endif; ?>
    </div>

</div>