<?php
// 管理员缓存管理
?>
<style>
/* ── cache ── */
.cstat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:.75rem; margin:.9rem 0; }
.cstat-item { background:rgba(155,140,255,.07); border:1px solid rgba(155,140,255,.2); border-radius:10px; padding:.9rem 1rem; }
.cstat-label { display:block; font-size:.76rem; color:var(--sub,#888); margin-bottom:.25rem; }
.cstat-value { display:block; font-size:1.35rem; font-weight:800; color:#6c5dfb; }
.idx-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:.75rem; margin:.9rem 0; }
.idx-item { background:rgba(155,140,255,.07); border:1px solid rgba(155,140,255,.2); border-radius:10px; padding:.9rem 1rem; }
.tags-cloud { display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.6rem; }
.tag-item { background:rgba(108,93,251,.1); color:#6c5dfb; padding:.2rem .65rem; border-radius:20px; font-size:.78rem; font-weight:600; }
.bkp-row { display:grid; grid-template-columns:1fr 90px 150px auto; gap:.5rem; align-items:center; padding:.55rem 1rem; border-bottom:1px solid rgba(155,140,255,.12); }
.bkp-row:last-child { border-bottom:none; }
.bkp-row:hover { background:rgba(155,140,255,.05); }
.bkp-name { display:flex; align-items:center; gap:.4rem; font-size:.86rem; word-break:break-all; }
.bkp-name svg { flex-shrink:0; color:#6c5dfb; }
.cache-acts { display:flex; gap:.4rem; flex-wrap:wrap; }
/* section divider */
.msub-hdr { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.5rem; margin:.4rem 0 .8rem; }
.msub-hdr h3 { margin:0; font-size:1rem; color:#6c5dfb; font-weight:700; }
/* dark */
body.dark-mode .cstat-item,
body.dark-mode .idx-item { background:rgba(176,160,255,.07); border-color:rgba(176,160,255,.2); }
body.dark-mode .cstat-value { color:var(--dark-vio,#b096ff); }
body.dark-mode .tag-item { background:rgba(176,160,255,.12); color:var(--dark-vio,#b096ff); }
body.dark-mode .bkp-row:hover { background:rgba(176,160,255,.06); }
body.dark-mode .bkp-row { border-bottom-color:rgba(176,160,255,.12); }
body.dark-mode .bkp-name svg { color:var(--dark-vio,#b096ff); }
body.dark-mode .msub-hdr h3 { color:var(--dark-vio,#b096ff); }
@media(max-width:560px){
    .bkp-row { grid-template-columns:1fr auto; }
    .bkp-row > :nth-child(2),.bkp-row > :nth-child(3){ display:none; }
}
</style>

<div class="admin-section">

    <!-- ── 缓存统计 ── -->
    <div class="mhdr">
        <div>
            <h2 class="mhdr-title">🗂 缓存 &amp; 索引管理</h2>
            <p class="mhdr-sub">查看缓存状态、管理文章索引、清理备份文件。</p>
        </div>
    </div>

    <!-- 缓存统计 -->
    <div class="mbuilder" style="padding:1rem 1.1rem 1.2rem; margin-bottom:1rem;">
        <div class="msub-hdr">
            <h3>📊 缓存统计</h3>
            <div class="cache-acts">
                <button type="button" class="btn btn-xs mbtn-t" onclick="cacheAjax('clear_expired',this)">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    清理过期缓存
                </button>
                <button type="button" class="btn btn-xs mbtn-d" onclick="cacheAjax('clear_all',this,'确定要清空所有缓存吗？')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    清空所有缓存
                </button>
            </div>
        </div>
        <div class="cstat-grid">
            <div class="cstat-item">
                <span class="cstat-label">总缓存文件</span>
                <span class="cstat-value"><?php echo $stats['total_files'] ?? 0; ?></span>
            </div>
            <div class="cstat-item">
                <span class="cstat-label">有效缓存文件</span>
                <span class="cstat-value"><?php echo $stats['active_files'] ?? 0; ?></span>
            </div>
            <div class="cstat-item">
                <span class="cstat-label">缓存总大小</span>
                <span class="cstat-value"><?php echo $stats['total_size'] ?? '0 KB'; ?></span>
            </div>
        </div>
    </div>

    <!-- 索引管理 -->
    <div class="mbuilder" style="padding:1rem 1.1rem 1.2rem; margin-bottom:1rem;">
        <div class="msub-hdr">
            <h3>📚 索引统计</h3>
            <div class="cache-acts">
                <button type="button" class="btn btn-xs mbtn-e" onclick="cacheAjax('rebuild_index',this)">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        重建索引
                </button>
                <button type="button" class="btn btn-xs mbtn-d" onclick="cacheAjax('clear_index',this,'确定要清空文章索引吗？')">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        清空索引
                </button>
            </div>
        </div>
        <div class="idx-grid">
            <div class="idx-item">
                <span class="cstat-label">总文章数</span>
                <span class="cstat-value"><?php echo $index_stats['total_articles'] ?? 0; ?></span>
            </div>
            <div class="idx-item">
                <span class="cstat-label">总字数</span>
                <span class="cstat-value"><?php echo $index_stats['total_words'] ?? 0; ?></span>
            </div>
            <div class="idx-item">
                <span class="cstat-label">标签数量</span>
                <span class="cstat-value"><?php echo count($index_stats['tags'] ?? []); ?></span>
            </div>
            <div class="idx-item">
                <span class="cstat-label">最后更新</span>
                <span class="cstat-value" style="font-size:.9rem;"><?php echo date('m-d H:i', time()); ?></span>
            </div>
        </div>
        <?php if (!empty($index_stats['tags'])): ?>
        <div style="margin-top:.6rem; padding-top:.75rem; border-top:1px solid rgba(155,140,255,.15);">
            <p style="margin:0 0 .4rem; font-size:.8rem; font-weight:700; color:var(--sub,#888);">🔥 热门标签</p>
            <div class="tags-cloud">
                <?php foreach (array_slice($index_stats['tags'], 0, 10) as $tag => $count): ?>
                <span class="tag-item"><?php echo htmlspecialchars($tag); ?> (<?php echo $count; ?>)</span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- 备份文件管理 -->
    <div class="mbuilder" style="padding:1rem 1.1rem 1.2rem;">
        <div class="msub-hdr">
            <h3>💾 备份文件管理</h3>
        </div>
        <?php $backupFiles = getBackupFiles(); ?>
        <?php if (!empty($backupFiles)): ?>
        <div class="mtip" style="margin-bottom:.8rem;">
            共 <strong><?php echo count($backupFiles); ?></strong> 个备份文件，总大小：<strong><?php 
                $totalSize = array_sum(array_column($backupFiles, 'size_bytes'));
                echo formatFileSize($totalSize);
            ?></strong>
        </div>
        <div class="mhead" style="grid-template-columns:1fr 90px 150px auto;">
            <span>文件名</span><span>大小</span><span>创建时间</span><span>操作</span>
        </div>
        <?php foreach ($backupFiles as $file): ?>
        <div class="bkp-row">
            <div class="bkp-name">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <?php echo htmlspecialchars($file['name']); ?>
            </div>
            <span style="font-size:.82rem;"><?php echo $file['size']; ?></span>
            <span style="font-size:.82rem;"><?php echo $file['date']; ?></span>
            <form method="post" style="display:inline;" onsubmit="return confirm('确定要删除备份文件 <?php echo htmlspecialchars(addslashes($file['name'])); ?> 吗？');">
                <input type="hidden" name="action" value="delete_backup">
                <input type="hidden" name="filename" value="<?php echo htmlspecialchars($file['name']); ?>">
                <button type="submit" class="btn btn-xs mbtn-d">删除</button>
            </form>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <p class="mempty">暂无备份文件</p>
        <?php endif; ?>
    </div>

</div>

<style>
/* ── cache-ajax toast ── */
#cache-toast {
    position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999;
    padding: .65rem 1.1rem; border-radius: 10px;
    font-size: .88rem; font-weight: 600;
    box-shadow: 0 4px 18px rgba(0,0,0,.18);
    opacity: 0; transform: translateY(8px);
    transition: opacity .25s, transform .25s;
    pointer-events: none;
}
#cache-toast.show { opacity: 1; transform: translateY(0); }
#cache-toast.ok   { background:#e8f5e9; color:#2e7d32; border:1px solid #a5d6a7; }
#cache-toast.err  { background:#ffebee; color:#c62828; border:1px solid #ef9a9a; }
body.dark-mode #cache-toast.ok  { background:#1b3a1f; color:#81c784; border-color:#388e3c; }
body.dark-mode #cache-toast.err { background:#3b1414; color:#ef9a9a; border-color:#c62828; }
</style>

<div id="cache-toast"></div>

<script>
(function () {
    /* ── 显示 Toast ── */
    function showToast(msg, ok) {
        var el = document.getElementById('cache-toast');
        el.textContent = msg;
        el.className = 'show ' + (ok ? 'ok' : 'err');
        clearTimeout(el._t);
        el._t = setTimeout(function () { el.className = ''; }, 3000);
    }

    /* ── 核心 AJAX 函数 ── */
    window.cacheAjax = function (action, btn, confirmMsg) {
        if (confirmMsg && !confirm(confirmMsg)) return;

        var origText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin .7s linear infinite"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> 处理中…';

        var fd = new FormData();
        fd.append('type', 'cache');
        fd.append('cache_action', action);

        fetch('admin_ajax.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                showToast(data.msg || (data.ok ? '操作成功' : '操作失败'), data.ok);
                if (data.ok) {
                    /* 保存主题状态，1 秒后刷新页面更新统计数字 */
                    setTimeout(function () {
                        var isDark = document.body.classList.contains('dark-mode');
                        var url = location.pathname + '?page=cache' +
                            (isDark ? '&_dark=1' : '');
                        location.href = url;
                    }, 1000);
                }
            })
            .catch(function () {
                showToast('请求失败，请检查网络连接', false);
            })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = origText;
            });
    };

    /* ── 页面载入时恢复主题（若 URL 带 _dark=1）── */
    (function restoreTheme() {
        var params = new URLSearchParams(location.search);
        if (params.get('_dark') === '1') {
            document.body.classList.add('dark-mode');
            params.delete('_dark');
            var clean = location.pathname + '?' + params.toString();
            history.replaceState(null, '', clean.replace(/\?$/, ''));
        }
    })();
})();

/* ── 旋转动画 ── */
if (!document.getElementById('spin-keyframes')) {
    var s = document.createElement('style');
    s.id = 'spin-keyframes';
    s.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
    document.head.appendChild(s);
}
</script>