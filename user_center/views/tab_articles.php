<?php
/**
 * 标签页：我的收藏
 * 依赖：$activeTab, $_SESSION['user']['id']
 */

// ── 动态计算网站根目录路径（末尾带 /）────────────────────────────
// 本文件被 user_center/index.php include，所以：
//   SCRIPT_NAME = /yusolab/user_center/index.php
//   dirname × 1 = /yusolab/user_center
//   dirname × 2 = /yusolab  → siteRoot = /yusolab/
// 部署在真正根目录时：dirname × 2 = '/' → siteRoot = '/'
$siteRoot = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';

// ── 查询收藏列表 ─────────────────────────────────────────────────
$favorites      = [];
$favoritesError = '';

if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    $currentUserId = (int)($_SESSION['user']['id'] ?? 0);
    if ($currentUserId > 0) {
        try {
            $db = Db::getInstance();
            $stmt = $db->prepare(
                "SELECT
                     uf.article_id,
                     uf.created_at                            AS fav_at,
                     COALESCE(ai.title,   a.title,  '文章已删除') AS title,
                     COALESCE(ai.date,    a.date,   NULL)      AS art_date,
                     COALESCE(ai.excerpt, a.excerpt, '')        AS excerpt,
                     COALESCE(ai.tags,    a.tags,   '')         AS tags,
                     COALESCE(ai.read_time, a.read_time, 0)     AS read_time,
                     COALESCE(a.cover_image, ai.cover_image)    AS cover_image,
                     CASE WHEN a.id IS NULL THEN 1 ELSE 0 END   AS is_deleted
                 FROM user_favorites uf
                 LEFT JOIN articles      a  ON uf.article_id = a.id
                 LEFT JOIN article_index ai ON uf.article_id = ai.id
                 WHERE uf.user_id = ?
                 ORDER BY uf.created_at DESC"
            );
            $stmt->execute([$currentUserId]);
            $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("获取收藏列表错误: " . $e->getMessage());
            $favoritesError = '加载收藏列表失败，请稍后重试。';
        }
    }
}

/**
 * 将数据库存储的封面图路径解析为正确 URL。
 * 数据库存的是相对于网站根目录的路径，如 serve_media.php?folder=images&name=xxx
 * $siteRoot 已包含末尾 /，直接拼接即可。
 */
function resolveCoverUrl(string $raw, string $siteRoot): string {
    if ($raw === '') return '';
    if (preg_match('#^https?://#', $raw)) return $raw;   // 绝对 URL
    if ($raw[0] === '/') return $raw;                     // 根相对路径
    return $siteRoot . ltrim($raw, '/');                  // 相对根目录路径
}
?>
<div id="articles" class="tab-content <?php echo $activeTab === 'articles' ? 'active' : ''; ?>">
    <div class="profile-section">
        <h2>我的收藏</h2>

        <?php if ($favoritesError): ?>
            <p class="form-error"><?php echo htmlspecialchars($favoritesError); ?></p>

        <?php elseif (empty($favorites)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
                <p>您尚未收藏任何文章。</p>
                <a href="<?php echo htmlspecialchars($siteRoot); ?>index.php"
                   class="btn primary" style="margin-top:12px;">去发现文章</a>
            </div>

        <?php else: ?>
            <p class="fav-count">共收藏了 <strong><?php echo count($favorites); ?></strong> 篇文章</p>

            <div class="fav-list">
                <?php foreach ($favorites as $fav):
                    $isDeleted  = (bool)$fav['is_deleted'];
                    $artId      = (int)$fav['article_id'];
                    $title      = htmlspecialchars($fav['title']);
                    $artDate    = $fav['art_date'] ? htmlspecialchars($fav['art_date']) : '';
                    $excerpt    = htmlspecialchars(mb_strimwidth($fav['excerpt'] ?? '', 0, 80, '…'));
                    $favAt      = date('Y-m-d', strtotime($fav['fav_at']));
                    $readTime   = (int)$fav['read_time'];
                    $tags       = !empty($fav['tags'])
                                    ? array_map('trim', explode(',', $fav['tags']))
                                    : [];
                    $coverImage = resolveCoverUrl($fav['cover_image'] ?? '', $siteRoot);
                    $articleUrl = htmlspecialchars($siteRoot . 'article.php?id=' . $artId);
                ?>
                <div class="fav-item <?php echo $isDeleted ? 'fav-deleted' : ''; ?>"
                     data-article-id="<?php echo $artId; ?>">

                    <?php if ($coverImage && !$isDeleted): ?>
                    <div class="fav-cover">
                        <img src="<?php echo htmlspecialchars($coverImage); ?>"
                             alt="<?php echo $title; ?>" loading="lazy">
                    </div>
                    <?php endif; ?>

                    <div class="fav-body">
                        <div class="fav-title-row">
                            <?php if ($isDeleted): ?>
                                <span class="fav-title fav-title-deleted"><?php echo $title; ?></span>
                                <span class="fav-badge-deleted">已删除</span>
                            <?php else: ?>
                                <a href="<?php echo $articleUrl; ?>"
                                   class="fav-title"><?php echo $title; ?></a>
                            <?php endif; ?>
                        </div>

                        <?php if ($excerpt): ?>
                        <p class="fav-excerpt"><?php echo $excerpt; ?></p>
                        <?php endif; ?>

                        <?php if ($tags): ?>
                        <div class="fav-tags">
                            <?php foreach (array_slice($tags, 0, 4) as $tag): ?>
                            <span class="tag tag-sm"><?php echo htmlspecialchars($tag); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <div class="fav-meta">
                            <?php if ($artDate): ?>
                            <span>📅 <?php echo $artDate; ?></span>
                            <?php endif; ?>
                            <?php if ($readTime): ?>
                            <span>⏱ 约 <?php echo $readTime; ?> 分钟</span>
                            <?php endif; ?>
                            <span class="fav-meta-time">⭐ 收藏于 <?php echo $favAt; ?></span>
                        </div>
                    </div>

                    <div class="fav-actions">
                        <?php if (!$isDeleted): ?>
                        <a href="<?php echo $articleUrl; ?>"
                           class="btn btn-small btn-read">阅读</a>
                        <?php endif; ?>
                        <button class="btn btn-small btn-unfav"
                                data-article-id="<?php echo $artId; ?>"
                                title="取消收藏">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"
                                 stroke="currentColor" stroke-width="1">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                            取消收藏
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.fav-count { color:var(--text-muted,#888); margin-bottom:16px; font-size:.93rem; }
.fav-list  { display:flex; flex-direction:column; gap:14px; }
.fav-item  {
    display:flex; align-items:flex-start; gap:14px;
    background:var(--card-bg,#fff);
    border:1px solid var(--border-color,rgba(0,0,0,.08));
    border-radius:14px; padding:16px;
    transition:box-shadow .2s, opacity .2s;
}
.fav-item:hover:not(.fav-deleted) { box-shadow:0 4px 18px rgba(108,93,251,.10); }
.fav-deleted { opacity:.65; background:var(--bg-secondary,#f7f7f9); }
.fav-cover  { flex-shrink:0; width:88px; height:66px; border-radius:8px; overflow:hidden; }
.fav-cover img { width:100%; height:100%; object-fit:cover; }
.fav-body   { flex:1; min-width:0; }
.fav-title-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:5px; }
a.fav-title {
    font-size:1rem; font-weight:600;
    color:var(--primary,#6c5dfb); text-decoration:none; word-break:break-word;
}
a.fav-title:hover { text-decoration:underline; }
.fav-title-deleted { font-size:1rem; font-weight:600; color:var(--text-muted,#888); word-break:break-word; }
.fav-badge-deleted {
    font-size:.72rem; background:#f5c6cb; color:#842029;
    border-radius:20px; padding:2px 8px; white-space:nowrap; flex-shrink:0;
}
.dark-mode .fav-badge-deleted { background:#5a1a20; color:#f8b4bb; }
.fav-excerpt { font-size:.87rem; color:var(--text-secondary,#666); margin:4px 0 6px; line-height:1.5; }
.fav-tags   { display:flex; flex-wrap:wrap; gap:5px; margin-bottom:6px; }
.tag-sm     { font-size:.72rem !important; padding:2px 8px !important; }
.fav-meta   { display:flex; flex-wrap:wrap; gap:10px; font-size:.8rem; color:var(--text-muted,#999); }
.fav-meta-time { color:var(--primary-light,#9b8cff); }
.fav-actions {
    display:flex; flex-direction:column; gap:7px;
    flex-shrink:0; align-items:flex-end;
}
.btn-read   { background:var(--primary,#6c5dfb); color:#fff !important; border:none; }
.btn-unfav  {
    display:inline-flex; align-items:center; gap:4px;
    background:transparent;
    border:1px solid var(--border-color,rgba(0,0,0,.15));
    color:var(--text-muted,#888); cursor:pointer;
    font-size:.8rem; border-radius:8px; padding:5px 10px;
    transition:background .15s, color .15s;
}
.btn-unfav:hover        { background:#ffe0e6; color:#c0392b; border-color:#f5a0aa; }
.dark-mode .btn-unfav:hover { background:#4a1020; color:#f8a0aa; }
.btn-unfav.loading      { opacity:.6; pointer-events:none; }

/* ── 夜间模式 ──────────────────────────────────────────────── */
body.dark-mode .fav-count { color: rgba(176,160,255,.55); }
body.dark-mode .fav-item {
    background: rgba(36,34,60,.85);
    border-color: rgba(176,160,255,.13);
}
body.dark-mode .fav-item:hover:not(.fav-deleted) {
    box-shadow: 0 4px 20px rgba(108,93,251,.22);
    border-color: rgba(176,160,255,.28);
}
body.dark-mode .fav-deleted {
    background: rgba(28,27,48,.6);
    opacity: .55;
}
body.dark-mode a.fav-title          { color: #b0a0ff; }
body.dark-mode a.fav-title:hover    { color: #d0c4ff; }
body.dark-mode .fav-title-deleted   { color: rgba(176,160,255,.4); }
body.dark-mode .fav-excerpt         { color: rgba(200,196,230,.65); }
body.dark-mode .fav-meta            { color: rgba(176,160,255,.45); }
body.dark-mode .fav-meta-time       { color: rgba(176,160,255,.7); }
body.dark-mode .btn-read            { background: var(--primary, #6c5dfb); color: #fff !important; }
body.dark-mode .btn-unfav {
    border-color: rgba(176,160,255,.18);
    color: rgba(176,160,255,.6);
    background: transparent;
}
body.dark-mode .btn-unfav:hover     { background: rgba(220,50,80,.15); color: #ff8fab; border-color: rgba(220,50,80,.35); }
body.dark-mode .fav-actions         { border-top-color: rgba(255,255,255,.06); }

@media (max-width:600px) {
    .fav-item    { flex-wrap:wrap; padding:12px; gap:10px; }
    .fav-cover   { width:100%; height:110px; border-radius:10px; }
    .fav-cover img { width:100%; height:100%; object-fit:cover; }
    .fav-body    { width:100%; }
    .fav-actions {
        flex-direction:row;
        width:100%;
        justify-content:flex-end;
        padding-top:4px;
        border-top:1px solid rgba(0,0,0,.06);
        margin-top:4px;
    }
    .dark-mode .fav-actions { border-top-color:rgba(255,255,255,.06); }
    .btn-read, .btn-unfav { flex:1; justify-content:center; font-size:.82rem; }
}
</style>

<script>
(function () {
    // PHP 注入的网站根目录路径，末尾带 /
    // 例如：/yusolab/  或  /
    const siteRoot  = <?php echo json_encode($siteRoot); ?>;
    const favApiUrl = siteRoot + 'favorites_api.php';
    const indexUrl  = siteRoot + 'index.php';
    const UNFAV_BTN = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg> 取消收藏';

    document.querySelectorAll('.btn-unfav').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const self      = this;
            const articleId = self.getAttribute('data-article-id');
            const item      = document.querySelector('.fav-item[data-article-id="' + articleId + '"]');
            if (!item || self.classList.contains('loading')) return;

            self.classList.add('loading');
            self.textContent = '处理中…';

            fetch(favApiUrl, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    'action=toggle&article_id=' + encodeURIComponent(articleId),
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && !data.favorited) {
                    item.style.transition = 'opacity .3s, max-height .4s, margin .4s, padding .4s';
                    item.style.overflow   = 'hidden';
                    item.style.maxHeight  = item.offsetHeight + 'px';
                    requestAnimationFrame(function() {
                        item.style.opacity   = '0';
                        item.style.maxHeight = '0';
                        item.style.margin    = '0';
                        item.style.padding   = '0';
                    });
                    setTimeout(function() {
                        item.remove();
                        var remaining = document.querySelectorAll('.fav-item').length;
                        var countEl   = document.querySelector('.fav-count strong');
                        if (countEl) countEl.textContent = remaining;
                        if (remaining === 0) {
                            var list = document.querySelector('.fav-list');
                            if (list) list.innerHTML =
                                '<div class="empty-state" style="padding:40px 0;text-align:center;">' +
                                '<p>您暂无收藏文章。</p>' +
                                '<a href="' + indexUrl + '" class="btn primary" style="margin-top:12px;">去发现文章</a>' +
                                '</div>';
                            var countP = document.querySelector('.fav-count');
                            if (countP) countP.remove();
                        }
                    }, 420);
                } else {
                    self.classList.remove('loading');
                    self.innerHTML = UNFAV_BTN;
                    alert(data.message || '操作失败，请稍后重试');
                }
            })
            .catch(function() {
                self.classList.remove('loading');
                self.innerHTML = UNFAV_BTN;
                alert('网络错误，请检查连接后重试');
            });
        });
    });
})();
</script>