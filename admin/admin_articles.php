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

/* ── 置顶样式 ── */
.art-mrow.is-pinned {
    background: rgba(255, 200, 50, .06);
    border-left: 3px solid #f5c518;
}
body.dark-mode .art-mrow.is-pinned {
    background: rgba(255, 200, 50, .08);
    border-left-color: #e6b800;
}
.art-pin-badge {
    display: inline-flex;
    align-items: center;
    gap: .2rem;
    font-size: .7rem;
    font-weight: 600;
    color: #b8860b;
    background: rgba(245, 197, 24, .18);
    border: 1px solid rgba(245, 197, 24, .4);
    border-radius: 4px;
    padding: .1rem .4rem;
    margin-left: .4rem;
    vertical-align: middle;
}
body.dark-mode .art-pin-badge {
    color: #f5c518;
    background: rgba(245, 197, 24, .12);
    border-color: rgba(245, 197, 24, .3);
}
.mbtn-pin {
    background: rgba(245, 197, 24, .12);
    border: 1px solid rgba(245, 197, 24, .4);
    color: #8a6a00;
    cursor: pointer;
    border-radius: 4px;
    padding: .2rem .55rem;
    font-size: .75rem;
    transition: background .15s, color .15s;
}
.mbtn-pin:hover {
    background: rgba(245, 197, 24, .28);
    color: #5a4400;
}
.mbtn-unpin {
    background: rgba(245, 197, 24, .22);
    border: 1px solid rgba(245, 197, 24, .55);
    color: #5a4400;
    cursor: pointer;
    border-radius: 4px;
    padding: .2rem .55rem;
    font-size: .75rem;
    font-weight: 600;
    transition: background .15s, color .15s;
}
.mbtn-unpin:hover {
    background: rgba(255,80,80,.12);
    border-color: rgba(255,80,80,.4);
    color: #c0392b;
}
body.dark-mode .mbtn-pin  { color: #e6c800; border-color: rgba(245,197,24,.35); background: rgba(245,197,24,.1); }
body.dark-mode .mbtn-unpin{ color: #f5c518; border-color: rgba(245,197,24,.5);  background: rgba(245,197,24,.18); }
body.dark-mode .mbtn-pin:hover  { background: rgba(245,197,24,.22); color: #ffe066; }
body.dark-mode .mbtn-unpin:hover{ background: rgba(255,80,80,.14);  border-color: rgba(255,80,80,.4); color: #ff8080; }

/* 置顶区与普通区之间的分割线 */
.art-section-divider {
    display: flex;
    align-items: center;
    gap: .6rem;
    padding: .45rem 1rem;
    font-size: .72rem;
    font-weight: 600;
    color: var(--sub, #999);
    background: rgba(155,140,255,.04);
    border-bottom: 1px solid rgba(155,140,255,.12);
    letter-spacing: .04em;
    text-transform: uppercase;
}
.art-section-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: rgba(155,140,255,.15);
}

@media(max-width:640px){
    .art-mrow { grid-template-columns:1fr; }
    .art-acts { justify-content:flex-start; }
}
</style>

<?php
/* ── 获取已置顶的文章 ID 及置顶时间（供本页使用）──────────── */
$pinnedMap = [];   // [article_id => pinned_at_string]
try {
    $db = Db::getInstance();
    $pinStmt = $db->query(
        "SELECT id, pinned_at FROM article_index WHERE pinned_at IS NOT NULL ORDER BY pinned_at DESC"
    );
    foreach ($pinStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $pinnedMap[(int)$row['id']] = $row['pinned_at'];
    }
} catch (Exception $e) {
    // 列可能尚未迁移，忽略错误
}

/* ── 将 $articles 分为"置顶区"和"普通区" ─────────────────── */
$pinnedArticles  = [];
$regularArticles = [];
if (!empty($articles)) {
    foreach ($articles as $a) {
        if (isset($pinnedMap[(int)$a['id']])) {
            $pinnedArticles[] = $a;
        } else {
            $regularArticles[] = $a;
        }
    }
    // 置顶区按置顶时间倒序（已在 SQL 中排好；这里保险排一次）
    usort($pinnedArticles, function($x, $y) use ($pinnedMap) {
        return strcmp($pinnedMap[(int)$y['id']], $pinnedMap[(int)$x['id']]);
    });
}
?>

<div class="admin-section">

    <div class="mhdr">
        <div>
            <h2 class="mhdr-title">📝 文章管理</h2>
            <p class="mhdr-sub">管理已发布的文章，可编辑、移至草稿或永久删除。置顶文章将优先展示在首页。</p>
        </div>
        <a href="?page=edit_article&edit=new" class="btn btn-primary">＋ 发布新文章</a>
    </div>

    <div class="mbuilder">
        <div class="mhead" style="grid-template-columns:1fr auto;">
            <span>文章信息</span>
            <span>操作</span>
        </div>

        <?php if (!empty($articles)): ?>

            <?php /* ── 置顶区 ── */ ?>
            <?php if (!empty($pinnedArticles)): ?>
                <div class="art-section-divider">📌 置顶文章（<?php echo count($pinnedArticles); ?> 篇）</div>
                <?php foreach ($pinnedArticles as $article): ?>
                <div class="art-mrow is-pinned" id="art-row-<?php echo intval($article['id']); ?>">
                    <div>
                        <div class="art-title">
                            <?php echo htmlspecialchars($article['title']); ?>
                            <span class="art-pin-badge">📌 已置顶</span>
                        </div>
                        <div class="art-meta">
                            <span>🆔 <?php echo $article['id']; ?></span>
                            <span>📅 <?php echo $article['date']; ?></span>
                            <span>🏷️ <?php echo htmlspecialchars(implode(', ', $article['tags'])); ?></span>
                            <span>📖 <?php echo $article['word_count'] ?? 0; ?> 字</span>
                            <span>⏱ <?php echo $article['read_time'] ?? 0; ?> 分钟</span>
                            <span style="color:#b8860b;">🕐 置顶于 <?php echo $pinnedMap[(int)$article['id']]; ?></span>
                        </div>
                    </div>
                    <div class="art-acts">
                        <a href="?page=edit_article&edit=<?php echo intval($article['id']); ?>" class="btn btn-xs mbtn-e">编辑</a>
                        <button class="btn btn-xs mbtn-unpin"
                                onclick="togglePin(<?php echo intval($article['id']); ?>, this)"
                                title="取消置顶">
                            取消置顶
                        </button>
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
            <?php endif; ?>

            <?php /* ── 普通区 ── */ ?>
            <?php if (!empty($regularArticles)): ?>
                <?php if (!empty($pinnedArticles)): ?>
                    <div class="art-section-divider">📄 普通文章（<?php echo count($regularArticles); ?> 篇）</div>
                <?php endif; ?>
                <?php foreach ($regularArticles as $article): ?>
                <div class="art-mrow" id="art-row-<?php echo intval($article['id']); ?>">
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
                        <button class="btn btn-xs mbtn-pin"
                                onclick="togglePin(<?php echo intval($article['id']); ?>, this)"
                                title="置顶此文章">
                            📌 置顶
                        </button>
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
            <?php endif; ?>

        <?php else: ?>
            <p class="mempty">暂无已发布文章，点击「发布新文章」开始创作。</p>
        <?php endif; ?>
    </div>

</div>

<script>
/**
 * togglePin(id, btn)
 * 通过 AJAX 切换文章置顶状态，成功后刷新页面以重新分区排列。
 */
function togglePin(articleId, btn) {
    btn.disabled = true;
    const isPinned = btn.classList.contains('mbtn-unpin');
    const label    = isPinned ? '取消置顶中…' : '置顶中…';
    const orig     = btn.textContent;
    btn.textContent = label;

    const fd = new FormData();
    fd.append('type',           'article');
    fd.append('article_action', 'toggle_pin');
    fd.append('id',             articleId);

    fetch('admin_ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                // 刷新当前页，重新排列分区
                location.reload();
            } else {
                alert('操作失败：' + (data.msg || '未知错误'));
                btn.textContent = orig;
                btn.disabled = false;
            }
        })
        .catch(() => {
            alert('网络错误，请稍后重试。');
            btn.textContent = orig;
            btn.disabled = false;
        });
}
</script>