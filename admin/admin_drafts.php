<?php
// 草稿箱页面
?>
<style>
/* ── drafts ── */
.dft-meta { font-size:.78rem; color:var(--sub,#888); margin-top:.2rem; display:flex; flex-wrap:wrap; gap:.3rem .7rem; }
.dft-mrow { display:grid; grid-template-columns:1fr auto; gap:.75rem; align-items:center; padding:.7rem 1rem; border-bottom:1px solid rgba(155,140,255,.12); }
.dft-mrow:last-child { border-bottom:none; }
.dft-mrow:hover { background:rgba(155,140,255,.05); }
.dft-acts { display:flex; gap:.25rem; flex-wrap:wrap; align-items:center; flex-shrink:0; }
.dft-title { font-weight:700; font-size:.95rem; }
.mbtn-s  { background:rgba(39,174,96,.12);  color:#1a7a45; }
.mbtn-p  { background:rgba(72,219,251,.12); color:#0a8a9f; }
body.dark-mode .dft-mrow:hover { background:rgba(176,160,255,.06); }
body.dark-mode .dft-mrow { border-bottom-color:rgba(176,160,255,.12); }
body.dark-mode .mbtn-s { background:rgba(39,174,96,.15); color:#6fcf97; }
body.dark-mode .mbtn-p { background:rgba(72,219,251,.1); color:#56d9f0; }
@media(max-width:640px){
    .dft-mrow { grid-template-columns:1fr; }
    .dft-acts { justify-content:flex-start; }
}
</style>

<div class="admin-section">

    <div class="mhdr">
        <div>
            <h2 class="mhdr-title">📂 草稿箱</h2>
            <p class="mhdr-sub">管理未发布的草稿，可预览、发布或删除。</p>
        </div>
        <a href="?page=edit_draft&edit=new" class="btn btn-primary">＋ 新建草稿</a>
    </div>

    <div class="mbuilder">
        <div class="mhead" style="grid-template-columns:1fr auto;">
            <span>草稿信息</span>
            <span>操作</span>
        </div>

        <?php 
        $drafts = getDrafts();
        if (!empty($drafts)): ?>
            <?php foreach ($drafts as $draft): ?>
            <div class="dft-mrow">
                <div>
                    <div class="dft-title"><?php echo htmlspecialchars($draft['title']); ?></div>
                    <div class="dft-meta">
                        <span>🆔 <?php echo $draft['id']; ?></span>
                        <span>📅 <?php echo $draft['date']; ?></span>
                        <span>🏷️ <?php echo htmlspecialchars(implode(', ', $draft['tags'])); ?></span>
                        <span>📖 <?php echo $draft['word_count'] ?? 0; ?> 字</span>
                    </div>
                </div>
                <div class="dft-acts">
                    <a href="?page=edit_draft&edit=<?php echo $draft['id']; ?>" class="btn btn-xs mbtn-e">编辑</a>
                    <a href="../draft_preview.php?id=<?php echo $draft['id']; ?>" class="btn btn-xs mbtn-p" target="_blank">预览</a>
                    <form method="post" onsubmit="return confirm('确定要发布这篇草稿吗？');" style="display:inline;">
                        <input type="hidden" name="action" value="publish_draft">
                        <input type="hidden" name="id" value="<?php echo $draft['id']; ?>">
                        <button type="submit" class="btn btn-xs mbtn-s">发布</button>
                    </form>
                    <form method="post" onsubmit="return confirm('确定要删除这篇草稿吗？');" style="display:inline;">
                        <input type="hidden" name="action" value="delete_draft">
                        <input type="hidden" name="id" value="<?php echo $draft['id']; ?>">
                        <button type="submit" class="btn btn-xs mbtn-d">删除</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="mempty">草稿箱为空，点击「新建草稿」开始写作。</p>
        <?php endif; ?>
    </div>

</div>