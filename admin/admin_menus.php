<?php
/**
 * admin_menus.php — 菜单管理（纯显示，无 header() 调用）
 * 所有 AJAX 请求发送到 admin_ajax.php
 */
$db = Db::getInstance();

$stmt     = $db->query("SELECT * FROM nav_menus ORDER BY COALESCE(parent_id,0) ASC, sort_order ASC, id ASC");
$menuFlat = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 构建树（用于按正确顺序渲染扁平列表）
$map = []; $menuTree = [];
foreach ($menuFlat as $item) { $item['children'] = []; $map[$item['id']] = $item; }
foreach ($map as $id => &$item) {
    if ($item['parent_id'] && isset($map[$item['parent_id']])) {
        $map[$item['parent_id']]['children'][] = &$item;
    } else { $menuTree[] = &$item; }
}
unset($item);

// 将树展开为扁平有序数组（父 → 子）
$menuOrdered = [];
foreach ($menuTree as $item) {
    $menuOrdered[] = ['item' => $item, 'level' => 0, 'parent_id' => 0];
    foreach ($item['children'] as $child) {
        $menuOrdered[] = ['item' => $child, 'level' => 1, 'parent_id' => (int)$item['id']];
    }
}

// 顶级菜单（供下拉选择父级）
$topMenus  = array_values(array_filter($menuFlat, fn($m) => $m['parent_id'] === null));
$sitePages = [];
try {
    $ps = $db->query("SELECT id, title, slug FROM site_pages WHERE status='published' ORDER BY title");
    $sitePages = $ps->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<div class="admin-section">

    <div class="mhdr">
        <div>
            <h2 class="mhdr-title">🧭 菜单管理</h2>
            <p class="mhdr-sub">管理前台导航栏菜单。上下拖曳排序，向右拖曳设为上方菜单的子项，向左拖曳提升为顶级。最后点「保存排序」生效。</p>
        </div>
        <button class="btn btn-primary" onclick="openMenuModal(0)">＋ 新增菜单项</button>
    </div>

    <div class="mtip">💡 拖曳 <span class="mdrag-demo">⠿</span> 句柄上下移动排序；向<strong>右</strong>拖超过 60px 将该项设为上方菜单的子菜单，向<strong>左</strong>拖超过 60px 提升为顶级菜单。拖曳时会显示操作提示，完成后点「保存排序」生效。</div>

    <!-- 拖曳时浮动提示 -->
    <div id="mdrag-tip" class="mdrag-tip" aria-hidden="true"></div>

    <div class="mbuilder">
        <div class="mhead">
            <span></span><span>名称</span><span>链接</span><span>状态</span><span>操作</span>
        </div>

        <?php if (empty($menuFlat)): ?>
        <p class="mempty">暂无菜单项，点击「新增菜单项」开始添加。</p>
        <?php else: ?>

        <!-- 扁平列表：父项后紧跟其子项，通过 data-level / data-parent-id 维护层级 -->
        <ul id="menu-root" class="msort-list">
            <?php foreach ($menuOrdered as $row):
                $item     = $row['item'];
                $level    = $row['level'];
                $parentId = $row['parent_id'];
                $liClass  = 'mli' . ($level === 1 ? ' mli-child' : '') . ($item['is_active'] ? '' : ' mli-off');
                $rowClass = 'mrow' . ($level === 1 ? ' mrow-child' : '');
            ?>
            <li class="<?php echo $liClass; ?>"
                data-id="<?php echo (int)$item['id']; ?>"
                data-level="<?php echo $level; ?>"
                data-parent-id="<?php echo $parentId; ?>">
                <div class="<?php echo $rowClass; ?>">
                    <span class="mdrag" title="上下拖曳排序，左右拖曳调整层级">⠿</span>
                    <span class="mname">
                        <?php if ($item['icon']): ?><em class="micon"><?php echo htmlspecialchars($item['icon']); ?></em><?php endif; ?>
                        <?php echo htmlspecialchars($item['label']); ?>
                        <?php if ($level === 1): ?><span class="bsub">子菜单</span><?php endif; ?>
                    </span>
                    <span class="murl"><code><?php echo htmlspecialchars($item['url']); ?></code></span>
                    <span class="mstatus"><?php echo $item['is_active'] ? '<span class="mbadge bon">启用</span>' : '<span class="mbadge boff">禁用</span>'; ?></span>
                    <span class="macts">
                        <button class="btn btn-xs mbtn-e" onclick="openMenuModal(<?php echo (int)$item['id']; ?>)">编辑</button>
                        <button class="btn btn-xs mbtn-t" onclick="menuToggle(<?php echo (int)$item['id']; ?>)"><?php echo $item['is_active'] ? '禁用' : '启用'; ?></button>
                        <button class="btn btn-xs mbtn-d" onclick="menuDelete(<?php echo (int)$item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['label'])); ?>')">删除</button>
                    </span>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>

        <?php endif; ?>
    </div>

    <div class="msave-row">
        <button class="btn btn-primary" onclick="saveMenuOrder()">💾 保存排序</button>
        <span id="msaveMsg" class="msave-msg"></span>
    </div>
</div>

<!-- 新增/编辑 Modal -->
<div id="menuModal" class="mmodal" style="display:none;" onclick="if(event.target===this)closeMenuModal()">
    <div class="mmodal-box">
        <div class="mmodal-hd">
            <h3 id="mmTitle">新增菜单项</h3>
            <button onclick="closeMenuModal()">✕</button>
        </div>
        <div class="mmodal-bd">
            <input type="hidden" id="mm_id" value="0">
            <div class="mfg">
                <label>菜单名称 <span class="req">*</span></label>
                <input type="text" id="mm_label" placeholder="如：关于我们" maxlength="100" autocomplete="off">
            </div>
            <div class="mfg">
                <label>链接地址</label>
                <div class="mfurl">
                    <input type="text" id="mm_url" placeholder="输入URL 或从右侧快速选择">
                    <select id="mm_psel" onchange="mmPickPage(this)">
                        <option value="">— 快速选择页面 —</option>
                        <option value="index.php">首页 (index.php)</option>
                        <?php foreach ($sitePages as $pg): ?>
                        <option value="page.php?slug=<?php echo htmlspecialchars($pg['slug']); ?>"><?php echo htmlspecialchars($pg['title']); ?></option>
                        <?php endforeach; ?>
                        <option value="#">空链接 (#)</option>
                    </select>
                </div>
            </div>
            <div class="mfrow2">
                <div class="mfg">
                    <label>父菜单（设为二级菜单）</label>
                    <select id="mm_parent">
                        <option value="0">— 顶级菜单 —</option>
                        <?php foreach ($topMenus as $tm): ?>
                        <option value="<?php echo (int)$tm['id']; ?>"><?php echo htmlspecialchars($tm['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mfg">
                    <label>图标（Emoji 可选）</label>
                    <input type="text" id="mm_icon" placeholder="如：📄 🔗 ✉️" maxlength="10">
                </div>
            </div>
            <div class="mfg mfcheck">
                <label><input type="checkbox" id="mm_tab"> 在新标签页打开</label>
            </div>
        </div>
        <div class="mmodal-ft">
            <button class="btn btn-secondary" onclick="closeMenuModal()">取消</button>
            <button class="btn btn-primary" onclick="mmSubmit()">保存</button>
        </div>
        <div id="mmMsg" class="mmodal-msg"></div>
    </div>
</div>

<script>
const _menuData = <?php echo json_encode(array_values($menuFlat)); ?>;
const _AJAX     = 'admin_ajax.php';

/* ── fetch helper ───────────────────────────────────────────── */
function mPost(data) {
    const fd = new FormData();
    fd.append('type', 'menu');
    Object.entries(data).forEach(([k,v]) => fd.append(k, v));
    return fetch(_AJAX, {method:'POST', body:fd}).then(r => r.json());
}

/* ═══════════════════════════════════════════════════════════════
   拖曳逻辑：上下排序 + 左右调整层级
   ══════════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Sortable === 'undefined') return;

    const list   = document.getElementById('menu-root');
    if (!list) return;

    const tip    = document.getElementById('mdrag-tip');
    const THRESH = 60;  // 触发层级变化的水平像素阈值

    let dragStartX = 0;
    let curX = 0, curY = 0;

    /* ── 从任意原生事件提取 clientX ── */
    function evX(oe) {
        if (!oe) return 0;
        if (oe.changedTouches && oe.changedTouches[0]) return oe.changedTouches[0].clientX;
        if (oe.touches && oe.touches[0]) return oe.touches[0].clientX;
        return oe.clientX ?? 0;
    }
    function evY(oe) {
        if (!oe) return 0;
        if (oe.changedTouches && oe.changedTouches[0]) return oe.changedTouches[0].clientY;
        if (oe.touches && oe.touches[0]) return oe.touches[0].clientY;
        return oe.clientY ?? 0;
    }

    /* ── 浮动提示（跟随指针）── */
    function showTip(deltaX, x, y) {
        if (!tip) return;
        if (deltaX > THRESH) {
            tip.textContent = '→ 设为子菜单';
            tip.className = 'mdrag-tip mdrag-tip-in';
        } else if (deltaX < -THRESH) {
            tip.textContent = '← 提升为顶级';
            tip.className = 'mdrag-tip mdrag-tip-out';
        } else {
            tip.textContent = '↕ 重新排序';
            tip.className = 'mdrag-tip mdrag-tip-sort';
        }
        tip.style.left = (x + 18) + 'px';
        tip.style.top  = (y - 12) + 'px';
        tip.style.display = 'block';
    }
    function hideTip() {
        if (tip) { tip.style.display = 'none'; tip.className = 'mdrag-tip'; }
    }

    /* ── 用 pointermove 跟踪坐标（比 mousemove 在拖曳中更可靠）── */
    document.addEventListener('pointermove', function(e) {
        curX = e.clientX;
        curY = e.clientY;
    });

    /* ── 更新层级和视觉 ── */
    function applyLevel(li, level, parentId) {
        li.dataset.level    = String(level);
        li.dataset.parentId = String(parentId);
        const row = li.querySelector('.mrow');
        if (level === 1) {
            li.classList.add('mli-child');
            row && row.classList.add('mrow-child');
            const nameSpan = li.querySelector('.mname');
            if (nameSpan && !nameSpan.querySelector('.bsub')) {
                const sub = document.createElement('span');
                sub.className = 'bsub';
                sub.textContent = '子菜单';
                nameSpan.appendChild(sub);
            }
        } else {
            li.classList.remove('mli-child');
            row && row.classList.remove('mrow-child');
            li.querySelector('.bsub')?.remove();
        }
    }

    /* ── 向上找最近的顶级项 ── */
    function prevTopLevel(li) {
        let cur = li.previousElementSibling;
        while (cur) {
            if (cur.dataset.level === '0') return cur;
            cur = cur.previousElementSibling;
        }
        return null;
    }

    /* ── Sortable 初始化 ── */
    new Sortable(list, {
        animation     : 180,
        handle        : '.mdrag',
        ghostClass    : 'msort-ghost',
        chosenClass   : 'msort-chosen',
        fallbackOnBody: true,
        swapThreshold : 0.5,

        onStart(e) {
            /* 优先用原生事件坐标，fallback 到 pointermove 缓存值 */
            const oe = e.originalEvent;
            dragStartX = evX(oe) || curX;
        },

        onMove(e) {
            /* onMove 持续触发，在此更新提示 */
            const oe = e.originalEvent;
            const x  = evX(oe) || curX;
            const y  = evY(oe) || curY;
            showTip(x - dragStartX, x, y);
            return true; // 不阻止排序
        },

        onEnd(e) {
            hideTip();
            const li = e.item;

            /* 用 changedTouches / clientX 从 mouseup/pointerup/touchend 取终点坐标 */
            const oe     = e.originalEvent;
            const endX   = evX(oe) || curX;
            const deltaX = endX - dragStartX;

            if (deltaX > THRESH) {
                /* 向右：设为上方顶级项的子菜单 */
                if (li.dataset.level !== '1') {
                    const parent = prevTopLevel(li);
                    if (parent) applyLevel(li, 1, +parent.dataset.id);
                }
            } else if (deltaX < -THRESH) {
                /* 向左：提升为顶级 */
                if (li.dataset.level === '1') applyLevel(li, 0, 0);
            } else {
                /* 纯垂直排序：子菜单跟随父级更新 parent_id */
                if (li.dataset.level === '1') {
                    const parent = prevTopLevel(li);
                    if (parent) {
                        li.dataset.parentId = parent.dataset.id;
                    } else {
                        applyLevel(li, 0, 0); // 移到顶部，自动提升
                    }
                }
            }
        },
    });
});

/* ── 保存排序 ───────────────────────────────────────────────── */
function saveMenuOrder() {
    const items = [];
    /* 按父级分组计算 sort_order */
    const orderMap = {}; // parentId → counter

    document.querySelectorAll('#menu-root > .mli').forEach(li => {
        const id       = +li.dataset.id;
        const parentId = +li.dataset.parentId || 0;
        if (!orderMap[parentId]) orderMap[parentId] = 10;
        items.push({ id, parent_id: parentId, sort_order: orderMap[parentId] });
        orderMap[parentId] += 10;
    });

    mPost({ menu_action:'save_order', items: JSON.stringify(items) }).then(d => {
        const msg = document.getElementById('msaveMsg');
        msg.textContent = d.ok ? '✓ 排序已保存' : ('错误：' + (d.msg || '未知'));
        msg.className = 'msave-msg ' + (d.ok ? 'msave-ok' : 'msave-err');
        setTimeout(() => { msg.textContent = ''; msg.className = 'msave-msg'; }, 3500);
    });
}

/* ── Modal ──────────────────────────────────────────────────── */
function openMenuModal(id) {
    document.getElementById('mm_id').value    = id;
    document.getElementById('mmMsg').textContent = '';
    document.getElementById('menuModal').style.display = 'flex';
    if (!id) {
        document.getElementById('mmTitle').textContent = '新增菜单项';
        document.getElementById('mm_label').value  = '';
        document.getElementById('mm_url').value    = '#';
        document.getElementById('mm_parent').value = '0';
        document.getElementById('mm_icon').value   = '';
        document.getElementById('mm_tab').checked  = false;
        document.getElementById('mm_psel').value   = '';
    } else {
        document.getElementById('mmTitle').textContent = '编辑菜单项';
        const item = _menuData.find(m => m.id == id);
        if (!item) return;
        document.getElementById('mm_label').value   = item.label  || '';
        document.getElementById('mm_url').value     = item.url    || '#';
        document.getElementById('mm_parent').value  = item.parent_id || '0';
        document.getElementById('mm_icon').value    = item.icon   || '';
        document.getElementById('mm_tab').checked   = item.open_new_tab == 1;
        document.getElementById('mm_psel').value    = '';
    }
    setTimeout(() => document.getElementById('mm_label').focus(), 80);
}
function closeMenuModal() { document.getElementById('menuModal').style.display='none'; }
function mmPickPage(sel)  { if (sel.value) document.getElementById('mm_url').value = sel.value; }

function mmSubmit() {
    const id    = +document.getElementById('mm_id').value;
    const label = document.getElementById('mm_label').value.trim();
    const url   = document.getElementById('mm_url').value.trim() || '#';
    const pid   = document.getElementById('mm_parent').value;
    const icon  = document.getElementById('mm_icon').value.trim();
    const tab   = document.getElementById('mm_tab').checked ? 1 : 0;
    if (!label) { document.getElementById('mmMsg').textContent='菜单名称不能为空'; return; }
    mPost({menu_action: id?'edit':'add', id, label, url, parent_id:pid, icon, open_new_tab:tab})
        .then(d => { d.ok ? location.reload() : (document.getElementById('mmMsg').textContent = d.msg||'操作失败'); });
}

function menuToggle(id) {
    mPost({menu_action:'toggle', id}).then(d => { d.ok ? location.reload() : alert(d.msg); });
}
function menuDelete(id, label) {
    if (!confirm('确认删除菜单「'+label+'」？其子菜单将自动提升为顶级。')) return;
    mPost({menu_action:'delete', id}).then(d => { d.ok ? location.reload() : alert(d.msg); });
}

document.addEventListener('keydown', e => { if (e.key==='Escape') closeMenuModal(); });
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>

<style>
/* ───────────── Layout ───────────── */
.mhdr { display:flex; justify-content:space-between; align-items:flex-start;
    gap:1rem; margin-bottom:1rem; flex-wrap:wrap; }
.mhdr-title { margin:0 0 .2rem; font-size:1.25rem; color:#6c5dfb; }
.mhdr-sub { margin:0; font-size:.86rem; color:var(--sub,#888); }

.mtip { padding:.5rem 1rem; margin-bottom:1rem;
    background:rgba(108,93,251,.06); border-left:3px solid #6c5dfb;
    border-radius:6px; font-size:.84rem; color:var(--sub,#777); }

/* ───────────── Builder ───────────── */
.mbuilder { border:1px solid var(--admin-border,rgba(155,140,255,.35));
    border-radius:12px; overflow:hidden; background:var(--admin-card,#fff); }
.mhead { display:grid; grid-template-columns:2rem 1fr 1.3fr 80px 230px;
    gap:.5rem; padding:.5rem 1rem;
    background:rgba(155,140,255,.07); border-bottom:1px solid var(--admin-border,rgba(155,140,255,.25));
    font-size:.78rem; font-weight:700; color:var(--sub,#888); }

/* ───────────── List ───────────── */
.msort-list { list-style:none; margin:0; padding:0; }
.mli { border-bottom:1px solid rgba(155,140,255,.12); transition:background .15s; }

.mli:last-child { border-bottom:none; }
.mli-off > .mrow { opacity:.45; }

.mrow { display:grid; grid-template-columns:2rem 1fr 1.3fr 80px 230px;
    gap:.5rem; align-items:center; padding:.55rem 1rem; }
.mrow:hover { background:rgba(155,140,255,.05); }
.mrow-child { padding-left:2.4rem; background:rgba(155,140,255,.028); border-left:3px solid rgba(108,93,251,.18); }

/* ───────────── Drag handle ───────────── */
.mdrag { cursor:grab; color:rgba(155,140,255,.7); font-size:1.15rem;
    text-align:center; user-select:none; line-height:1; }
.mdrag:active { cursor:grabbing; }
.mdrag-demo { font-size:1rem; opacity:.7; }

/* ───────────── Floating drag tip ───────────── */
.mdrag-tip {
    display:none; position:fixed; z-index:9999; pointer-events:none;
    padding:.28rem .7rem; border-radius:20px; font-size:.78rem; font-weight:700;
    letter-spacing:.02em; white-space:nowrap;
    box-shadow:0 3px 12px rgba(0,0,0,.18);
    transition:background .1s, color .1s;
}
.mdrag-tip-in   { background:#6c5dfb; color:#fff; }
.mdrag-tip-out  { background:#27ae60; color:#fff; }
.mdrag-tip-sort { background:rgba(255,255,255,.92); color:#555; border:1px solid rgba(155,140,255,.35); }

/* ───────────── Sortable visual ───────────── */
.msort-ghost  { opacity:.25; background:rgba(108,93,251,.1)!important; border-radius:6px; }
.msort-chosen { box-shadow:0 4px 18px rgba(108,93,251,.22); border-radius:6px; z-index:10; }

/* ───────────── Cells ───────────── */
.murl code { font-size:.76rem; color:var(--sub,#888);
    background:rgba(155,140,255,.07); padding:1px 5px; border-radius:4px; word-break:break-all; }
.micon { margin-right:.2rem; font-style:normal; }
.bsub  { font-size:.67rem; background:rgba(108,93,251,.1); color:#6c5dfb;
    border-radius:8px; padding:1px 7px; margin-left:.3rem; vertical-align:middle; font-style:normal; }
.mbadge { font-size:.7rem; border-radius:10px; padding:2px 9px; }
.bon  { background:#d4edda; color:#155724; }
.boff { background:#f8d7da; color:#721c24; }

/* ───────────── Action buttons ───────────── */
.btn-xs { padding:.2rem .58rem; font-size:.75rem; border-radius:6px; cursor:pointer;
    border:none; font-weight:600; margin:0 1px; transition:filter .12s; }
.btn-xs:hover { filter:brightness(.87); }
.mbtn-e { background:rgba(108,93,251,.1);  color:#6c5dfb; }
.mbtn-t { background:rgba(255,193,7,.15);  color:#856404; }
.mbtn-d { background:rgba(255,71,87,.1);   color:#c0392b; }

/* ───────────── Save row ───────────── */
.msave-row { display:flex; align-items:center; gap:1rem; margin-top:1rem; flex-wrap:wrap; }
.msave-msg { font-size:.86rem; }
.msave-ok  { color:#27ae60; }
.msave-err { color:#c0392b; }
.mempty    { padding:2rem; text-align:center; color:var(--sub,#999); }

/* ═══════════ Modal ═══════════ */
.mmodal { position:fixed; inset:0; background:rgba(0,0,0,.48);
    z-index:2100; display:flex; align-items:center; justify-content:center;
    backdrop-filter:blur(3px); }
.mmodal-box { background:var(--admin-card,#fffffff0);
    border:1px solid var(--admin-border,rgba(155,140,255,.4));
    border-radius:16px; width:500px; max-width:95vw;
    box-shadow:0 16px 48px rgba(108,93,251,.2);
    animation:mmIn .18s ease; overflow:hidden; }
@keyframes mmIn { from { opacity:0; transform:scale(.95) translateY(8px); } }
.mmodal-hd { display:flex; justify-content:space-between; align-items:center;
    padding:.9rem 1.2rem; border-bottom:1px solid var(--admin-border,rgba(155,140,255,.2));
    background:rgba(155,140,255,.05); }
.mmodal-hd h3 { margin:0; font-size:1rem; color:#6c5dfb; }
.mmodal-hd button { background:none; border:none; font-size:1.2rem; cursor:pointer;
    color:var(--sub,#aaa); line-height:1; }
.mmodal-hd button:hover { color:#c0392b; }
.mmodal-bd { padding:1.15rem; display:flex; flex-direction:column; gap:.8rem; }
.mmodal-ft { display:flex; justify-content:flex-end; gap:.5rem;
    padding:.75rem 1.2rem; border-top:1px solid var(--admin-border,rgba(155,140,255,.2));
    background:rgba(155,140,255,.03); }
.mmodal-msg { padding:0 1.2rem .65rem; font-size:.82rem; color:#c0392b; min-height:1rem; }

/* ───────────── Form ───────────── */
.mfg { display:flex; flex-direction:column; gap:.28rem; }
.mfg label { font-size:.83rem; font-weight:600; color:var(--sub,#666); }
.mfg input[type=text], .mfg select {
    padding:.48rem .72rem; border:1px solid var(--admin-border,rgba(155,140,255,.4));
    border-radius:8px; font-size:.9rem; box-sizing:border-box; width:100%;
    background:var(--admin-card,#fff); color:inherit; transition:border-color .15s; }
.mfg input:focus, .mfg select:focus {
    outline:none; border-color:#6c5dfb; box-shadow:0 0 0 3px rgba(108,93,251,.1); }
.mfrow2 { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; }
.mfurl  { display:flex; gap:.4rem; }
.mfurl input { flex:1; }
.mfcheck label { flex-direction:row; align-items:center; gap:.4rem;
    font-weight:normal; cursor:pointer; }
.req { color:#c0392b; }

/* ═══════════ Dark Mode ═══════════ */
body.dark-mode .mhdr-title { color:var(--dark-vio,#b096ff); }
body.dark-mode .mhdr-sub,
body.dark-mode .mtip { color:var(--dark-sub,#b0b0c5); }
body.dark-mode .mtip { background:rgba(176,160,255,.07); border-left-color:var(--dark-vio,#b096ff); }
body.dark-mode .mbuilder { background:var(--dark-admin-card,#2a2a42dd); border-color:var(--dark-admin-border); }
body.dark-mode .mhead { background:rgba(176,160,255,.05); border-bottom-color:var(--dark-admin-border); color:var(--dark-sub,#b0b0c5); }
body.dark-mode .mli { border-bottom-color:rgba(176,160,255,.12); }
body.dark-mode .mrow:hover { background:rgba(176,160,255,.06); }
body.dark-mode .mrow-child { background:rgba(176,160,255,.03); border-left-color:rgba(176,160,255,.2); }
body.dark-mode .murl code   { color:var(--dark-sub,#b0b0c5); background:rgba(176,160,255,.07); }
body.dark-mode .bsub  { background:rgba(176,160,255,.12); color:var(--dark-vio,#b096ff); }
body.dark-mode .bon   { background:#1e3a26; color:#6fcf97; }
body.dark-mode .boff  { background:#3a1e22; color:#eb5757; }
body.dark-mode .mdrag { color:rgba(176,160,255,.45); }
body.dark-mode .mdrag-tip-sort { background:rgba(40,36,70,.95); color:var(--dark-sub,#b0b0c5); border-color:rgba(176,160,255,.25); }
body.dark-mode .mbtn-e { background:rgba(176,160,255,.12); color:var(--dark-vio,#b096ff); }
body.dark-mode .mbtn-t { background:rgba(255,193,7,.1);   color:#f2c94c; }
body.dark-mode .mbtn-d { background:rgba(255,71,87,.1);   color:#eb5757; }
/* Modal dark */
body.dark-mode .mmodal-box { background:var(--dark-admin-card,#2a2a42); border-color:var(--dark-admin-border); box-shadow:0 16px 48px rgba(0,0,0,.5); }
body.dark-mode .mmodal-hd  { background:rgba(176,160,255,.05); border-bottom-color:var(--dark-admin-border); }
body.dark-mode .mmodal-hd h3 { color:var(--dark-vio,#b096ff); }
body.dark-mode .mmodal-hd button { color:var(--dark-sub,#b0b0c5); }
body.dark-mode .mmodal-ft  { background:rgba(176,160,255,.03); border-top-color:var(--dark-admin-border); }
body.dark-mode .mfg label  { color:var(--dark-sub,#b0b0c5); }
body.dark-mode .mfg input[type=text],
body.dark-mode .mfg select { background:#2a2a42aa; border-color:var(--dark-admin-border); color:var(--dark-text,#eaeaea); }
body.dark-mode .mfg input:focus,
body.dark-mode .mfg select:focus { border-color:var(--dark-vio,#b096ff); box-shadow:0 0 0 3px rgba(176,160,255,.12); }

/* ─── Responsive ─── */
@media (max-width:680px) {
    .mhead { display:none; }
    .mrow { grid-template-columns:2rem 1fr auto; }
    .murl,.mstatus { display:none; }
    .mfrow2 { grid-template-columns:1fr; }
    .mfurl  { flex-direction:column; }
}
</style>