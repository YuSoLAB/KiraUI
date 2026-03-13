<?php
/**
 * admin_profile_review.php
 * 信息变更审核管理面板
 * 依赖：$db（来自 admin.php 上下文）、Config::getInstance()
 *
 * 列表数据完全由前端 AJAX 加载，PHP 只负责初始开关状态 + 待审核角标。
 */

$config        = Config::getInstance();
$reviewEnabled = $config->get('profile_review_enabled', '0') === '1';

// 待处理数量（角标，JS 刷新后会同步更新）
$pendingCount = (int)$db->query(
    "SELECT COUNT(*) FROM pending_profile_changes WHERE status='pending'"
)->fetchColumn();

// 读取 URL 初始状态，传给 JS 做首次加载
$initStatus = in_array($_GET['review_status'] ?? '', ['pending','approved','rejected','all'], true)
    ? $_GET['review_status']
    : 'pending';
$initPage = max(1, (int)($_GET['rp'] ?? 1));
?>

<style>
/* ── review rows (mirrors usr-row from admin_users) ── */
.rev-row {
    display: grid;
    grid-template-columns: 1.2fr 100px 1.6fr 90px 90px 130px;
    gap: .4rem;
    align-items: center;
    padding: .6rem 1rem;
    border-bottom: 1px solid rgba(155,140,255,.12);
    font-size: .84rem;
}
.rev-row-noact {
    display: grid;
    grid-template-columns: 1.2fr 100px 1.6fr 90px 90px;
    gap: .4rem;
    align-items: center;
    padding: .6rem 1rem;
    border-bottom: 1px solid rgba(155,140,255,.12);
    font-size: .84rem;
}
.rev-row:last-child,
.rev-row-noact:last-child { border-bottom: none; }
.rev-row:hover,
.rev-row-noact:hover { background: rgba(155,140,255,.05); }

/* type badges */
.mbadge-avatar { background: rgba(99,102,241,.15); color: #818cf8; }
.mbadge-nick   { background: rgba(251,191,36,.15);  color: #b38600; }

/* approve / reject buttons */
.btn-review-approve { background: rgba(39,174,96,.12) !important; color: #1a7a45 !important; }
.btn-review-approve:hover { background: rgba(39,174,96,.25) !important; }
.btn-review-reject  { background: rgba(255,71,87,.1)  !important; color: #c0392b !important; }
.btn-review-reject:hover  { background: rgba(255,71,87,.22)  !important; }

/* list container transitions */
#rev-list-wrap { transition: opacity .18s ease; }
#rev-list-wrap.rev-loading { opacity: .4; pointer-events: none; }

/* skeleton shimmer */
.rev-skeleton {
    height: 52px;
    border-radius: 6px;
    background: linear-gradient(90deg, rgba(155,140,255,.08) 25%, rgba(155,140,255,.15) 50%, rgba(155,140,255,.08) 75%);
    background-size: 200% 100%;
    animation: rev-shimmer 1.2s infinite;
    margin: .3rem 1rem;
}
@keyframes rev-shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

/* dark mode */
body.dark-mode .rev-row,
body.dark-mode .rev-row-noact { border-bottom-color: rgba(176,160,255,.12); }
body.dark-mode .rev-row:hover,
body.dark-mode .rev-row-noact:hover { background: rgba(176,160,255,.06); }
body.dark-mode .mbadge-nick  { color: #f2c94c; background: rgba(251,191,36,.1); }
body.dark-mode .btn-review-approve { background: rgba(39,174,96,.15)  !important; color: #6fcf97 !important; }
body.dark-mode .btn-review-reject  { background: rgba(255,71,87,.1)   !important; color: #eb5757 !important; }
body.dark-mode .rev-skeleton {
    background: linear-gradient(90deg, rgba(176,160,255,.06) 25%, rgba(176,160,255,.12) 50%, rgba(176,160,255,.06) 75%);
    background-size: 200% 100%;
    animation: rev-shimmer 1.2s infinite;
}

@media(max-width:800px) {
    .rev-row,
    .rev-row-noact { grid-template-columns: 1fr auto; }
    .rev-row > :nth-child(2),
    .rev-row > :nth-child(4),
    .rev-row-noact > :nth-child(2),
    .rev-row-noact > :nth-child(4) { display: none; }
}
</style>

<div class="admin-section">

    <div class="mhdr">
        <div>
            <h2 class="mhdr-title">
                🔍 信息变更审核
                <span id="revPendingBadge" style="display:<?php echo $pendingCount > 0 ? 'inline-flex' : 'none'; ?>;
                     align-items:center;justify-content:center;
                     background:#f87171;color:#fff;font-size:.68rem;font-weight:700;
                     min-width:18px;height:18px;border-radius:9px;padding:0 5px;
                     vertical-align:middle;margin-left:6px;"><?php echo $pendingCount; ?></span>
            </h2>
            <p class="mhdr-sub">启用后，用户提交的昵称和头像变更将进入待审核队列，通过审核后才会公开展示。</p>
        </div>
    </div>

    <!-- 审核开关 -->
    <div class="mbuilder" style="padding:1.2rem;margin-bottom:1rem;">
        <p style="margin:0 0 .9rem;font-size:.83rem;font-weight:700;color:#6c5dfb;">🔒 审核开关</p>
        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
            <label style="display:flex;align-items:center;gap:.55rem;cursor:pointer;user-select:none;">
                <div class="reg-toggle-wrap <?php echo $reviewEnabled ? 'on' : ''; ?>">
                    <input type="checkbox" id="reviewToggleInput" <?php echo $reviewEnabled ? 'checked' : ''; ?> style="display:none;">
                    <div class="reg-toggle-track">
                        <div class="reg-toggle-thumb"></div>
                    </div>
                </div>
                <span id="reviewToggleLabel" style="font-size:.88rem;font-weight:600;color:<?php echo $reviewEnabled ? '#27ae60' : '#e74c3c'; ?>;">
                    <?php echo $reviewEnabled ? '审核已启用' : '审核已关闭'; ?>
                </span>
            </label>
            <span id="reviewToggleMsg" style="font-size:.82rem;display:none;"></span>
        </div>
        <p style="margin:.7rem 0 0;font-size:.78rem;color:var(--sub,#999);">关闭后，用户提交的昵称和头像变更将直接生效，无需审核。</p>
    </div>

    <!-- 状态筛选 Tab（JS 拦截，不跳转） -->
    <div id="revTabs" style="display:flex;gap:6px;margin-bottom:1rem;flex-wrap:wrap;">
        <button class="rev-tab" data-status="pending"  type="button">待审核</button>
        <button class="rev-tab" data-status="approved" type="button">已通过</button>
        <button class="rev-tab" data-status="rejected" type="button">已拒绝</button>
        <button class="rev-tab" data-status="all"      type="button">全部</button>
    </div>

    <!-- 审核列表（完全由 JS 渲染） -->
    <div id="rev-list-wrap" class="mbuilder" style="margin-bottom:1rem;overflow-x:auto;min-height:80px;">
        <div id="rev-skeleton">
            <div class="rev-skeleton"></div>
            <div class="rev-skeleton" style="opacity:.7"></div>
            <div class="rev-skeleton" style="opacity:.4"></div>
        </div>
    </div>

    <!-- 分页（由 JS 渲染） -->
    <div id="rev-pagination" style="display:flex;justify-content:center;gap:6px;margin-bottom:1rem;flex-wrap:wrap;"></div>

</div>

<!-- 拒绝原因弹窗 -->
<div id="rejectModal" style="display:none;position:fixed;inset:0;z-index:9999;
     background:rgba(10,8,28,.72);backdrop-filter:blur(6px);
     align-items:center;justify-content:center;">
    <div class="mbuilder" style="padding:1.6rem;width:min(420px,90vw);box-shadow:0 20px 60px rgba(0,0,0,.35);">
        <h3 style="margin:0 0 1rem;font-size:.95rem;font-weight:700;">填写拒绝原因（可选）</h3>
        <input type="hidden" id="rejectId" value="">
        <div class="mfg" style="margin-bottom:1rem;">
            <textarea id="rejectReason" rows="3"
                      placeholder="请简短说明拒绝原因，将显示给用户..."
                      style="width:100%;padding:.48rem .72rem;border-radius:8px;font-size:.86rem;resize:vertical;
                             border:1px solid var(--admin-border,rgba(155,140,255,.4));
                             background:var(--admin-card,#fff);color:inherit;box-sizing:border-box;"></textarea>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:.6rem;">
            <button id="rejectCancel" class="btn btn-xs" style="border:1px solid var(--admin-border,rgba(155,140,255,.3));background:transparent;">取消</button>
            <button id="rejectConfirm" class="btn btn-xs btn-review-reject" style="font-weight:700;">确认拒绝</button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    /* ── 状态 ─────────────────────────────────────── */
    var curStatus = <?php echo json_encode($initStatus); ?>;
    var curPage   = <?php echo (int)$initPage; ?>;
    var isLoading = false;

    var TAB_META = {
        pending:  { color:'#f59e0b' },
        approved: { color:'#27ae60' },
        rejected: { color:'#e74c3c' },
        all:      { color:'#6c5dfb' }
    };
    var STATUS_BADGE = {
        pending:  { cls:'ust-frozen', text:'待审核' },
        approved: { cls:'ust-normal', text:'已通过' },
        rejected: { cls:'ust-banned', text:'已拒绝' }
    };

    /* ── 工具函数 ─────────────────────────────────── */
    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function fmtDate(str) {
        if (!str) return '';
        var d = new Date(str.replace(' ','T'));
        var p = function(n){ return String(n).padStart(2,'0'); };
        return p(d.getMonth()+1) + '-' + p(d.getDate()) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
    }

    /* ── Tab 激活样式 ─────────────────────────────── */
    function syncTabs(active) {
        document.querySelectorAll('.rev-tab').forEach(function(btn) {
            var m = TAB_META[btn.dataset.status] || {};
            if (btn.dataset.status === active) {
                btn.style.cssText = 'padding:5px 14px;border-radius:20px;font-size:.82rem;font-weight:600;'
                    + 'border:none;cursor:pointer;transition:all .2s;background:' + m.color + ';color:#fff;';
            } else {
                btn.style.cssText = 'padding:5px 14px;border-radius:20px;font-size:.82rem;font-weight:600;'
                    + 'border:1px solid rgba(155,140,255,.2);cursor:pointer;transition:all .2s;'
                    + 'background:rgba(155,140,255,.1);color:var(--sub,#888);';
            }
        });
    }

    /* ── 渲染单行 ─────────────────────────────────── */
    function renderRow(item, showAction) {
        var isPending = item.status === 'pending';
        var isAvatar  = item.type   === 'avatar';
        var rowCls    = showAction ? 'rev-row' : 'rev-row-noact';
        var sb        = STATUS_BADGE[item.status] || { cls:'ust-normal', text: item.status };

        var avatarCurrent = item.current_avatar
            ? '../uploads/avatars/' + esc(item.current_avatar)
            : '../img/default-avatar.png';
        var avatarPending = '../uploads/avatars/' + esc(item.new_value);

        var contentHtml = isAvatar
            ? ('<div style="display:flex;align-items:center;gap:10px;">'
                + '<div style="text-align:center;font-size:.7rem;color:var(--sub,#888);">'
                +   '<img src="' + avatarCurrent + '" alt="当前" style="width:40px;height:40px;border-radius:50%;object-fit:cover;display:block;border:2px solid rgba(155,140,255,.25);">'
                +   '<span style="margin-top:2px;display:block;">当前</span></div>'
                + '<span style="color:var(--sub,#888);">→</span>'
                + '<div style="text-align:center;font-size:.7rem;color:var(--sub,#888);">'
                +   '<img src="' + avatarPending + '" alt="申请" style="width:40px;height:40px;border-radius:50%;object-fit:cover;display:block;border:2px solid rgba(108,93,251,.5);">'
                +   '<span style="margin-top:2px;display:block;">申请</span></div></div>')
            : ('<span style="color:var(--sub,#888);text-decoration:line-through;font-size:.83rem;">'
                + esc(item.current_nickname || item.username)
                + '</span><span style="color:var(--sub,#888);margin:0 4px;">→</span>'
                + '<span style="font-weight:600;">' + esc(item.new_value) + '</span>');

        var statusExtra = (item.status === 'rejected' && item.reject_reason)
            ? '<div style="font-size:.73rem;color:#e74c3c;margin-top:3px;word-break:break-all;max-width:140px;">' + esc(item.reject_reason) + '</div>'
            : '';

        var actionHtml = '';
        if (showAction) {
            actionHtml = isPending
                ? '<span style="display:flex;gap:.3rem;justify-content:center;flex-wrap:wrap;">'
                    + '<button class="btn btn-xs btn-review-approve" data-id="' + esc(item.id) + '">✓ 通过</button>'
                    + '<button class="btn btn-xs btn-review-reject"  data-id="' + esc(item.id) + '">✗ 拒绝</button></span>'
                : '<span style="color:var(--sub,#888);font-size:.78rem;text-align:center;display:block;">—</span>';
        }

        return '<div class="' + rowCls + '">'
            + '<span><span style="font-weight:600;">' + esc(item.nickname || item.username || '') + '</span><br>'
            +   '<small style="color:var(--sub,#aaa);">@' + esc(item.username) + '</small></span>'
            + '<span><span class="mbadge ' + (isAvatar ? 'mbadge-avatar' : 'mbadge-nick') + '">'
            +   (isAvatar ? '🖼️ 头像' : '✏️ 昵称') + '</span></span>'
            + '<span>' + contentHtml + '</span>'
            + '<span style="color:var(--sub,#888);font-size:.82rem;">' + fmtDate(item.created_at) + '</span>'
            + '<span><span class="mbadge ' + sb.cls + '">' + sb.text + '</span>' + statusExtra + '</span>'
            + actionHtml
            + '</div>';
    }

    /* ── 渲染整个列表区 ───────────────────────────── */
    function renderList(data) {
        var wrap      = document.getElementById('rev-list-wrap');
        var pager     = document.getElementById('rev-pagination');
        var showAction = (data.statusFilter === 'pending' || data.statusFilter === 'all');
        var html       = '';

        if (!data.items.length) {
            html = '<p class="mempty">'
                + (data.statusFilter === 'pending' ? '✅ 暂无待审核的变更申请' : '该分类下暂无记录')
                + '</p>';
        } else {
            html += showAction
                ? '<div class="mhead rev-row"><span>用户</span><span>变更类型</span><span>变更内容</span><span>申请时间</span><span>状态</span><span style="text-align:center;">操作</span></div>'
                : '<div class="mhead rev-row-noact"><span>用户</span><span>变更类型</span><span>变更内容</span><span>申请时间</span><span>状态</span></div>';
            data.items.forEach(function(item) { html += renderRow(item, showAction); });
        }

        wrap.innerHTML = html;
        wrap.classList.remove('rev-loading');

        // 角标
        var badge = document.getElementById('revPendingBadge');
        if (badge) {
            badge.textContent   = data.pendingCount;
            badge.style.display = data.pendingCount > 0 ? 'inline-flex' : 'none';
        }

        // 分页
        if (data.totalPages > 1) {
            var pHtml = '';
            for (var i = 1; i <= data.totalPages; i++) {
                var active = (i === data.page);
                pHtml += '<button class="rev-pg-btn" data-page="' + i + '" type="button" style="'
                    + 'display:inline-flex;align-items:center;justify-content:center;'
                    + 'width:34px;height:34px;border-radius:8px;font-size:.85rem;cursor:pointer;border:none;transition:all .2s;'
                    + 'background:' + (active ? '#6c5dfb' : 'rgba(155,140,255,.12)') + ';'
                    + 'color:'      + (active ? '#fff'    : 'var(--sub,#888)') + ';">' + i + '</button>';
            }
            pager.innerHTML     = pHtml;
            pager.style.display = 'flex';
            pager.querySelectorAll('.rev-pg-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    loadReviews(curStatus, parseInt(this.dataset.page, 10));
                });
            });
        } else {
            pager.innerHTML     = '';
            pager.style.display = 'none';
        }

        // 重绑操作按钮
        wrap.querySelectorAll('.btn-review-approve').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (!confirm('确认通过该变更申请？')) return;
                reviewAction(this.dataset.id, 'approve', '');
            });
        });
        wrap.querySelectorAll('.btn-review-reject').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.getElementById('rejectId').value     = this.dataset.id;
                document.getElementById('rejectReason').value = '';
                document.getElementById('rejectModal').style.display = 'flex';
            });
        });
    }

    /* ── 核心：AJAX 加载列表 ──────────────────────── */
    function loadReviews(status, page, pushState) {
        if (isLoading) return;
        isLoading = true;
        curStatus = status;
        curPage   = page || 1;

        syncTabs(curStatus);

        var wrap = document.getElementById('rev-list-wrap');
        wrap.classList.add('rev-loading');

        // 更新 URL，不触发页面跳转
        if (pushState !== false) {
            var params = new URLSearchParams(window.location.search);
            params.set('review_status', curStatus);
            curPage > 1 ? params.set('rp', curPage) : params.delete('rp');
            history.pushState(
                { revStatus: curStatus, revPage: curPage }, '',
                window.location.pathname + '?' + params.toString()
            );
        }

        var fd = new FormData();
        fd.append('type',   'profile_review');
        fd.append('action', 'get_list');
        fd.append('status', curStatus);
        fd.append('page',   curPage);

        fetch('admin_ajax.php', { method:'POST', body:fd })
            .then(function(r){ return r.json(); })
            .then(function(data) {
                isLoading = false;
                if (data.ok) {
                    renderList(data);
                } else {
                    wrap.classList.remove('rev-loading');
                    wrap.innerHTML = '<p class="mempty" style="color:#e74c3c;">加载失败：' + esc(data.msg||'未知错误') + '</p>';
                }
            })
            .catch(function() {
                isLoading = false;
                wrap.classList.remove('rev-loading');
                wrap.innerHTML = '<p class="mempty" style="color:#e74c3c;">网络错误，请重试</p>';
            });
    }

    /* ── 审核操作 ─────────────────────────────────── */
    function reviewAction(id, action, reason) {
        var fd = new FormData();
        fd.append('type',   'profile_review');
        fd.append('action', action);
        fd.append('id',     id);
        fd.append('reason', reason);
        fetch('admin_ajax.php', { method:'POST', body:fd })
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (data.ok) { loadReviews(curStatus, curPage); }
                else { alert('操作失败：' + (data.msg || '未知错误')); }
            })
            .catch(function(){ alert('网络错误，请重试'); });
    }

    /* ── Tab 点击 ─────────────────────────────────── */
    document.getElementById('revTabs').addEventListener('click', function(e) {
        var btn = e.target.closest('.rev-tab');
        if (!btn || btn.dataset.status === curStatus) return;
        loadReviews(btn.dataset.status, 1);
    });

    /* ── 浏览器前进/后退 ──────────────────────────── */
    window.addEventListener('popstate', function(e) {
        var st = e.state;
        if (st && st.revStatus) loadReviews(st.revStatus, st.revPage || 1, false);
    });

    /* ── 拒绝弹窗 ─────────────────────────────────── */
    var modal = document.getElementById('rejectModal');
    document.getElementById('rejectCancel').addEventListener('click', function() { modal.style.display='none'; });
    modal.addEventListener('click', function(e){ if(e.target===modal) modal.style.display='none'; });
    document.getElementById('rejectConfirm').addEventListener('click', function() {
        var id     = document.getElementById('rejectId').value;
        var reason = document.getElementById('rejectReason').value.trim();
        modal.style.display = 'none';
        reviewAction(id, 'reject', reason);
    });

    /* ── 审核开关 ─────────────────────────────────── */
    var checkbox = document.getElementById('reviewToggleInput');
    var tLabel   = document.getElementById('reviewToggleLabel');
    var tMsg     = document.getElementById('reviewToggleMsg');

    function syncToggleUI(on) {
        var wrap = checkbox.closest('.reg-toggle-wrap') || checkbox.parentElement;
        on ? wrap.classList.add('on') : wrap.classList.remove('on');
        tLabel.textContent = on ? '审核已启用' : '审核已关闭';
        tLabel.style.color = on ? '#27ae60' : '#e74c3c';
    }
    syncToggleUI(checkbox.checked);
    checkbox.addEventListener('change', function() {
        var on = checkbox.checked;
        syncToggleUI(on);
        tMsg.style.display = 'none';
        var fd = new FormData();
        fd.append('type','profile_review'); fd.append('action','toggle_setting'); fd.append('enabled', on?'1':'0');
        fetch('admin_ajax.php',{method:'POST',body:fd})
            .then(function(r){return r.json();})
            .then(function(d){
                tMsg.textContent=d.msg||(d.ok?'已保存':'保存失败');
                tMsg.style.color=d.ok?'#27ae60':'#e74c3c';
                tMsg.style.display='inline';
                setTimeout(function(){tMsg.style.display='none';},2500);
                if(!d.ok){checkbox.checked=!on;syncToggleUI(!on);}
            })
            .catch(function(){
                tMsg.textContent='网络错误，请重试'; tMsg.style.color='#e74c3c'; tMsg.style.display='inline';
                checkbox.checked=!on; syncToggleUI(!on);
            });
    });

    /* ── 首次加载 ─────────────────────────────────── */
    loadReviews(curStatus, curPage, false);

})();
</script>