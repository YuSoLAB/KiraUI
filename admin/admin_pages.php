<?php
/**
 * admin_pages.php — 页面管理（纯显示，无 header() 调用）
 * 所有 AJAX 请求发送到 admin_ajax.php
 */
$db = Db::getInstance();
$stmt   = $db->query("SELECT id,title,slug,status,meta_description,created_at,updated_at FROM site_pages ORDER BY updated_at DESC");
$pages  = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-section">
    <div class="phdr">
        <div>
            <h2 class="phdr-title">📄 页面管理</h2>
            <p class="phdr-sub">创建自定义前端页面（如隐私协议、关于我们），通过 <code>page.php?slug=xxx</code> 访问。</p>
        </div>
        <button class="btn btn-primary" onclick="openPageModal(0)">＋ 新建页面</button>
    </div>

    <!-- 页面列表 -->
    <div class="ptable-wrap">
        <table class="ptable">
            <thead>
                <tr><th>标题</th><th>Slug (URL)</th><th>状态</th><th>更新时间</th><th>操作</th></tr>
            </thead>
            <tbody>
            <?php if (empty($pages)): ?>
                <tr><td colspan="5" class="pempty">暂无页面，点击「新建页面」开始创建。</td></tr>
            <?php else: ?>
                <?php foreach ($pages as $p): ?>
                <tr>
                    <td class="ptitle"><?php echo htmlspecialchars($p['title']); ?></td>
                    <td>
                        <code class="pslug"><?php echo htmlspecialchars($p['slug']); ?></code>
                        <a href="../page.php?slug=<?php echo urlencode($p['slug']); ?>" target="_blank" class="ppreview" title="前台预览">↗</a>
                    </td>
                    <td>
                        <?php if ($p['status']==='published'): ?>
                            <span class="mbadge bon">已发布</span>
                        <?php else: ?>
                            <span class="mbadge bdraft">草稿</span>
                        <?php endif; ?>
                    </td>
                    <td class="ptime"><?php echo date('Y-m-d H:i', strtotime($p['updated_at'])); ?></td>
                    <td class="pacts">
                        <button class="btn btn-xs mbtn-e" onclick="openPageModal(<?php echo (int)$p['id']; ?>)">编辑</button>
                        <button class="btn btn-xs mbtn-d" onclick="pageDelete(<?php echo (int)$p['id']; ?>, '<?php echo htmlspecialchars(addslashes($p['title'])); ?>')">删除</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 页面编辑器 Modal（大尺寸）-->
<div id="pageModal" class="pmodal" style="display:none;" onclick="if(event.target===this)closePageModal()">
    <div class="pmodal-box">
        <div class="pmodal-hd">
            <h3 id="pmTitle">新建页面</h3>
            <button onclick="closePageModal()">✕</button>
        </div>
        <div class="pmodal-bd">
            <input type="hidden" id="pm_id" value="0">
            <div class="pfgrid">
                <div class="pfg">
                    <label>页面标题 <span class="req">*</span></label>
                    <input type="text" id="pm_title" placeholder="如：隐私协议" oninput="pmAutoSlug()">
                </div>
                <div class="pfg">
                    <label>Slug（URL路径）</label>
                    <div class="pslug-row">
                        <span class="pslug-pre">page.php?slug=</span>
                        <input type="text" id="pm_slug" placeholder="privacy-policy" oninput="pmSlugEdited=true">
                    </div>
                    <small>英文字母、数字、连字符，留空自动生成</small>
                </div>
                <div class="pfg">
                    <label>SEO 描述（可选）</label>
                    <input type="text" id="pm_desc" placeholder="简短描述">
                </div>
                <div class="pfg">
                    <label>状态</label>
                    <select id="pm_status">
                        <option value="published">已发布</option>
                        <option value="draft">草稿（不公开）</option>
                    </select>
                </div>
            </div>
            <div class="pfg pfg-full">
                <label>页面内容（支持 HTML）</label>
                <div class="petoolbar">
                    <button type="button" onclick="peInsert('h2')">H2</button>
                    <button type="button" onclick="peInsert('h3')">H3</button>
                    <button type="button" onclick="peInsert('p')">P</button>
                    <button type="button" onclick="peInsert('strong')"><b>B</b></button>
                    <button type="button" onclick="peInsert('em')"><i>I</i></button>
                    <button type="button" onclick="peInsert('a href=\'\'')">🔗 链接</button>
                    <button type="button" onclick="peInsert('ul')">UL</button>
                    <button type="button" onclick="peInsert('li')">LI</button>
                    <button type="button" onclick="peInsert('blockquote')">引用</button>
                    <button type="button" onclick="peInsert('hr /')">HR</button>
                    <button type="button" onclick="togglePreview()" class="petoolbar-preview">👁 预览</button>
                </div>
                <textarea id="pm_content" class="pecontent" placeholder="在此输入页面内容，支持 HTML..."></textarea>
                <div id="pePreview" class="pepreview" style="display:none;">
                    <div class="pepreview-label">预览效果</div>
                    <div id="pePreviewContent" class="pepreview-body"></div>
                </div>
            </div>
        </div>
        <div class="pmodal-ft">
            <button class="btn btn-secondary" onclick="closePageModal()">取消</button>
            <button class="btn btn-primary" onclick="pageSave()">💾 保存页面</button>
        </div>
        <div id="pmMsg" class="pmodal-msg"></div>
    </div>
</div>

<script>
const _PAGE_AJAX  = 'admin_ajax.php';
let pmSlugEdited  = false;

function pPost(data) {
    const fd = new FormData();
    fd.append('type','page');
    Object.entries(data).forEach(([k,v]) => fd.append(k,v));
    return fetch(_PAGE_AJAX,{method:'POST',body:fd}).then(r=>r.json());
}

function openPageModal(id) {
    pmSlugEdited = !!id;
    document.getElementById('pm_id').value = id;
    document.getElementById('pmMsg').textContent = '';
    document.getElementById('pePreview').style.display = 'none';
    document.getElementById('pageModal').style.display  = 'flex';

    if (!id) {
        document.getElementById('pmTitle').textContent  = '新建页面';
        document.getElementById('pm_title').value   = '';
        document.getElementById('pm_slug').value    = '';
        document.getElementById('pm_desc').value    = '';
        document.getElementById('pm_status').value  = 'published';
        document.getElementById('pm_content').value = '';
    } else {
        document.getElementById('pmTitle').textContent = '编辑页面';
        document.getElementById('pmMsg').textContent   = '加载中…';
        pPost({page_action:'get',id}).then(d => {
            document.getElementById('pmMsg').textContent = '';
            if (!d.ok||!d.data) { alert('加载失败'); return; }
            const p = d.data;
            document.getElementById('pm_title').value   = p.title  ||'';
            document.getElementById('pm_slug').value    = p.slug   ||'';
            document.getElementById('pm_desc').value    = p.meta_description||'';
            document.getElementById('pm_status').value  = p.status ||'published';
            document.getElementById('pm_content').value = p.content||'';
        });
    }
    setTimeout(() => document.getElementById('pm_title').focus(), 80);
}
function closePageModal() { document.getElementById('pageModal').style.display='none'; }

function pmAutoSlug() {
    if (pmSlugEdited) return;
    const raw = document.getElementById('pm_title').value
        .toLowerCase().replace(/[\u4e00-\u9fa5]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
    document.getElementById('pm_slug').value = raw;
}

function pageSave() {
    const id      = +document.getElementById('pm_id').value;
    const title   = document.getElementById('pm_title').value.trim();
    const slug    = document.getElementById('pm_slug').value.trim();
    const desc    = document.getElementById('pm_desc').value.trim();
    const status  = document.getElementById('pm_status').value;
    const content = document.getElementById('pm_content').value;
    if (!title) { document.getElementById('pmMsg').textContent='页面标题不能为空'; return; }
    document.getElementById('pmMsg').textContent = '保存中…';
    pPost({page_action:'save',id,title,slug,meta_description:desc,status,content})
        .then(d => { d.ok ? location.reload() : (document.getElementById('pmMsg').textContent = d.msg||'保存失败'); });
}

function pageDelete(id, title) {
    if (!confirm('确认删除页面「'+title+'」？指向该页面的菜单链接将重置为 #')) return;
    pPost({page_action:'delete',id}).then(d => { d.ok ? location.reload() : alert(d.msg); });
}

function togglePreview() {
    const el = document.getElementById('pePreview');
    if (el.style.display==='none') {
        document.getElementById('pePreviewContent').innerHTML = document.getElementById('pm_content').value;
        el.style.display='block';
    } else {
        el.style.display='none';
    }
}

function peInsert(tag) {
    const ta    = document.getElementById('pm_content');
    const start = ta.selectionStart, end = ta.selectionEnd;
    const sel   = ta.value.substring(start,end);
    const self  = tag.endsWith('/');
    const tagName = tag.split(' ')[0];
    const ins   = self ? '<'+tag+'>' : '<'+tag+'>'+sel+'</'+tagName+'>';
    ta.value = ta.value.substring(0,start)+ins+ta.value.substring(end);
    ta.focus();
    ta.selectionStart = ta.selectionEnd = start+ins.length;
}

document.addEventListener('keydown', e => { if(e.key==='Escape') closePageModal(); });
</script>

<style>
/* ─── Header ─── */
.phdr { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:1rem; flex-wrap:wrap; }
.phdr-title { margin:0 0 .2rem; font-size:1.25rem; color:#6c5dfb; }
.phdr-sub   { margin:0; font-size:.86rem; color:var(--sub,#888); }
.phdr-sub code { font-size:.8rem; background:rgba(155,140,255,.1); padding:1px 5px; border-radius:4px; }

/* ─── Table ─── */
.ptable-wrap { border:1px solid var(--admin-border,rgba(155,140,255,.35));
    border-radius:12px; overflow:hidden; background:var(--admin-card,#fff); }
.ptable { width:100%; border-collapse:collapse; }
.ptable th { background:rgba(155,140,255,.07); padding:.5rem 1rem;
    text-align:left; font-size:.78rem; font-weight:700; color:var(--sub,#888);
    border-bottom:1px solid var(--admin-border,rgba(155,140,255,.2)); }
.ptable td { padding:.6rem 1rem; border-bottom:1px solid rgba(155,140,255,.1); font-size:.9rem; }
.ptable tr:last-child td { border-bottom:none; }
.ptable tr:hover td { background:rgba(155,140,255,.04); }
.pempty { text-align:center; color:var(--sub,#999); padding:2rem !important; }
.ptitle { font-weight:600; }
.pslug  { font-size:.78rem; background:rgba(155,140,255,.08); padding:2px 6px; border-radius:4px; }
.ppreview { margin-left:.4rem; color:#6c5dfb; text-decoration:none; font-size:.88rem; }
.ptime  { color:var(--sub,#888); font-size:.8rem; white-space:nowrap; }
.pacts  { white-space:nowrap; }
.mbadge { font-size:.7rem; border-radius:10px; padding:2px 9px; }
.bon    { background:#d4edda; color:#155724; }
.bdraft { background:#fff3cd; color:#856404; }

/* ─── Modal ─── */
.pmodal { position:fixed; inset:0; background:rgba(0,0,0,.48);
    z-index:2100; display:flex; align-items:flex-start; justify-content:center;
    padding:2rem 0; overflow-y:auto; backdrop-filter:blur(3px); }
.pmodal-box { background:var(--admin-card,#fffffff0);
    border:1px solid var(--admin-border,rgba(155,140,255,.4));
    border-radius:16px; width:820px; max-width:96vw;
    box-shadow:0 16px 48px rgba(108,93,251,.18);
    animation:mmIn .18s ease; display:flex; flex-direction:column;
    max-height:calc(100vh - 4rem); overflow:hidden; }
.pmodal-hd { display:flex; justify-content:space-between; align-items:center;
    padding:.85rem 1.2rem; border-bottom:1px solid var(--admin-border,rgba(155,140,255,.2));
    background:rgba(155,140,255,.05); flex-shrink:0; }
.pmodal-hd h3 { margin:0; font-size:1rem; color:#6c5dfb; }
.pmodal-hd button { background:none; border:none; font-size:1.2rem; cursor:pointer;
    color:var(--sub,#aaa); }
.pmodal-hd button:hover { color:#c0392b; }
.pmodal-bd { padding:1.15rem; display:flex; flex-direction:column; gap:.8rem;
    overflow-y:auto; flex:1; }
.pmodal-ft { display:flex; justify-content:flex-end; gap:.5rem;
    padding:.75rem 1.2rem; border-top:1px solid var(--admin-border,rgba(155,140,255,.2));
    background:rgba(155,140,255,.03); flex-shrink:0; }
.pmodal-msg { padding:0 1.2rem .65rem; font-size:.82rem; color:#c0392b; min-height:1rem; }

/* ─── Form ─── */
.pfgrid { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; }
.pfg { display:flex; flex-direction:column; gap:.28rem; }
.pfg label { font-size:.83rem; font-weight:600; color:var(--sub,#666); }
.pfg input[type=text], .pfg select {
    padding:.47rem .72rem; border:1px solid var(--admin-border,rgba(155,140,255,.4));
    border-radius:8px; font-size:.9rem; box-sizing:border-box; width:100%;
    background:var(--admin-card,#fff); color:inherit; transition:border-color .15s; }
.pfg input:focus, .pfg select:focus {
    outline:none; border-color:#6c5dfb; box-shadow:0 0 0 3px rgba(108,93,251,.1); }
.pfg small { font-size:.75rem; color:var(--sub,#999); }
.pfg-full { grid-column:1/-1; }
.pslug-row { display:flex; align-items:center;
    border:1px solid var(--admin-border,rgba(155,140,255,.4)); border-radius:8px; overflow:hidden; }
.pslug-pre { padding:.47rem .6rem; background:rgba(155,140,255,.07);
    font-size:.75rem; color:var(--sub,#888); white-space:nowrap;
    border-right:1px solid var(--admin-border,rgba(155,140,255,.2)); }
.pslug-row input { border:none!important; border-radius:0!important; flex:1; }
.req { color:#c0392b; }

/* ─── Editor ─── */
.petoolbar { display:flex; gap:.3rem; flex-wrap:wrap; margin-bottom:.4rem; }
.petoolbar button { padding:.2rem .55rem; font-size:.78rem;
    background:rgba(155,140,255,.1); border:1px solid rgba(155,140,255,.25);
    border-radius:6px; cursor:pointer; color:inherit; font-family:inherit; }
.petoolbar button:hover { background:rgba(108,93,251,.15); color:#6c5dfb; }
.petoolbar-preview { margin-left:auto; background:rgba(108,93,251,.1)!important; }
.pecontent { width:100%; min-height:300px; padding:.8rem; box-sizing:border-box;
    font-family:Consolas,Monaco,monospace; font-size:.87rem;
    border:1px solid var(--admin-border,rgba(155,140,255,.4)); border-radius:8px;
    resize:vertical; line-height:1.6; background:var(--admin-card,#fff); color:inherit; }
.pecontent:focus { outline:none; border-color:#6c5dfb; box-shadow:0 0 0 3px rgba(108,93,251,.1); }
.pepreview { border:1px solid var(--admin-border,rgba(155,140,255,.3)); border-radius:8px; overflow:hidden; margin-top:.5rem; }
.pepreview-label { background:rgba(155,140,255,.07); padding:.3rem .8rem;
    font-size:.76rem; color:var(--sub,#888); border-bottom:1px solid var(--admin-border,rgba(155,140,255,.2)); }
.pepreview-body { padding:1rem; max-height:260px; overflow-y:auto; line-height:1.8; }

/* ═══ Dark Mode ═══ */
body.dark-mode .phdr-title { color:var(--dark-vio,#b096ff); }
body.dark-mode .phdr-sub   { color:var(--dark-sub,#b0b0c5); }
body.dark-mode .ptable-wrap { background:var(--dark-admin-card,#2a2a42dd); border-color:var(--dark-admin-border); }
body.dark-mode .ptable th   { background:rgba(176,160,255,.05); border-bottom-color:var(--dark-admin-border); color:var(--dark-sub,#b0b0c5); }
body.dark-mode .ptable td   { border-bottom-color:rgba(176,160,255,.1); }
body.dark-mode .ptable tr:hover td { background:rgba(176,160,255,.05); }
body.dark-mode .pslug   { background:rgba(176,160,255,.08); color:var(--dark-sub,#b0b0c5); }
body.dark-mode .ptime   { color:var(--dark-sub,#b0b0c5); }
body.dark-mode .bon     { background:#1e3a26; color:#6fcf97; }
body.dark-mode .bdraft  { background:#3a330d; color:#f2c94c; }
/* Modal dark */
body.dark-mode .pmodal-box  { background:var(--dark-admin-card,#2a2a42); border-color:var(--dark-admin-border); box-shadow:0 16px 48px rgba(0,0,0,.5); }
body.dark-mode .pmodal-hd   { background:rgba(176,160,255,.05); border-bottom-color:var(--dark-admin-border); }
body.dark-mode .pmodal-hd h3{ color:var(--dark-vio,#b096ff); }
body.dark-mode .pmodal-hd button { color:var(--dark-sub,#b0b0c5); }
body.dark-mode .pmodal-ft   { background:rgba(176,160,255,.03); border-top-color:var(--dark-admin-border); }
body.dark-mode .pfg label   { color:var(--dark-sub,#b0b0c5); }
body.dark-mode .pfg small   { color:var(--dark-sub,#b0b0c5); }
body.dark-mode .pfg input[type=text],
body.dark-mode .pfg select  { background:#2a2a42aa; border-color:var(--dark-admin-border); color:var(--dark-text,#eaeaea); }
body.dark-mode .pfg input:focus,
body.dark-mode .pfg select:focus { border-color:var(--dark-vio,#b096ff); box-shadow:0 0 0 3px rgba(176,160,255,.12); }
body.dark-mode .pslug-pre   { background:rgba(176,160,255,.07); border-right-color:var(--dark-admin-border); color:var(--dark-sub,#b0b0c5); }
body.dark-mode .pslug-row   { border-color:var(--dark-admin-border); }
body.dark-mode .petoolbar button { background:rgba(176,160,255,.08); border-color:rgba(176,160,255,.2); color:var(--dark-text,#eaeaea); }
body.dark-mode .petoolbar button:hover { background:rgba(176,160,255,.15); color:var(--dark-vio,#b096ff); }
body.dark-mode .pecontent   { background:#1a1a2e; border-color:var(--dark-admin-border); color:var(--dark-text,#eaeaea); }
body.dark-mode .pecontent:focus { border-color:var(--dark-vio,#b096ff); box-shadow:0 0 0 3px rgba(176,160,255,.12); }
body.dark-mode .pepreview   { border-color:var(--dark-admin-border); }
body.dark-mode .pepreview-label { background:rgba(176,160,255,.05); border-bottom-color:var(--dark-admin-border); color:var(--dark-sub,#b0b0c5); }

/* ─── Responsive ─── */
@media (max-width:600px) {
    .pfgrid { grid-template-columns:1fr; }
    .ptable .murl, .ptable .ptime { display:none; }
}
</style>