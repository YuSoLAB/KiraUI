<?php
/**
 * admin_user_badges.php — 用户认证角标与头衔管理页面
 *
 * 放置路径：admin/admin_user_badges.php
 * 在 admin.php 中通过 case 'user_badges' 引入。
 */
require_once __DIR__ . '/../include/Db.php';
require_once __DIR__ . '/admin_functions.php';
require_once __DIR__ . '/badge_functions.php';

$db         = Db::getInstance();
$badgeTypes = getAllBadgeTypes();

// 拉取所有用户（含管理员）及其角标配置
$users = $db->query(
    "SELECT u.id, u.username, u.nickname, u.email, u.role, u.avatar,
            b.id            AS badge_id,
            b.badge_type,
            b.badge_color,
            b.badge_icon_color,
            b.title_text,
            b.title_color,
            b.title_bg_color,
            b.is_active
       FROM users u
  LEFT JOIN user_badges b ON b.user_id = u.id
   ORDER BY u.role DESC, u.created_at ASC"
)->fetchAll(PDO::FETCH_ASSOC);

/**
 * 默认头像：纯本地 SVG data URI，不发起任何外部请求。
 * 替代原 Gravatar ?d=mp 占位图。
 */
define('DEFAULT_AVATAR_SVG', 'data:image/svg+xml,%3Csvg xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22 viewBox%3D%220 0 60 60%22%3E%3Crect width%3D%2260%22 height%3D%2260%22 rx%3D%2230%22 fill%3D%22%23b0b8c9%22%2F%3E%3Ccircle cx%3D%2230%22 cy%3D%2223%22 r%3D%2210%22 fill%3D%22%23fff%22%2F%3E%3Cellipse cx%3D%2230%22 cy%3D%2248%22 rx%3D%2215%22 ry%3D%2210%22 fill%3D%22%23fff%22%2F%3E%3C%2Fsvg%3E');

function _badgeAdminAvatar(string $email, ?string $localAvatar): string
{
    if ($localAvatar && file_exists(ROOT_DIR . '/uploads/avatars/' . $localAvatar)) {
        return '/uploads/avatars/' . $localAvatar;
    }
    if (function_exists('getCommentAvatar')) {
        $src = getCommentAvatar($email, 0);
        // getCommentAvatar 若仍返回 Gravatar 链接则使用本地默认
        if ($src && strpos($src, 'gravatar.com') === false) {
            return $src;
        }
    }
    return DEFAULT_AVATAR_SVG;
}
?>
<script>
/* 全局默认头像 data URI，供所有 onerror / JS 逻辑复用，无外部请求 */
window.__DEFAULT_AVATAR = <?php echo json_encode(DEFAULT_AVATAR_SVG); ?>;
</script>
<style>
/* ══════════════════════════════════════
   Badge Admin — List + Detail Panel
══════════════════════════════════════ */

/* ── 搜索栏 ── */
.ub-search-bar {
    display: flex;
    align-items: center;
    gap: .75rem;
    margin-bottom: .75rem;
    flex-wrap: wrap;
}
.ub-search-bar input {
    flex: 1;
    min-width: 200px;
    padding: .42rem .75rem;
    border: 1px solid var(--admin-border, rgba(155,140,255,.35));
    border-radius: 8px;
    font-size: .85rem;
    background: var(--admin-card, #fff);
    color: inherit;
    transition: border-color .18s;
}
.ub-search-bar input:focus { outline: none; border-color: #6c5dfb; }

/* ── 用户列表 ── */
.ub-list {
    border: 1px solid var(--admin-border, rgba(155,140,255,.2));
    border-radius: 12px;
    overflow: hidden;
}
.ub-list-row {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .6rem 1rem;
    border-bottom: 1px solid var(--admin-border, rgba(155,140,255,.12));
    transition: background .15s;
}
.ub-list-row:last-child { border-bottom: none; }
.ub-list-row:hover { background: rgba(108,93,251,.05); }

/* 头像 */
.ub-list-avatar {
    position: relative;
    flex-shrink: 0;
}
.ub-list-avatar img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: block;
}
.ub-list-dot {
    position: absolute;
    bottom: -1px;
    right: -1px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid var(--admin-card, #fff);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,.2);
}
.ub-list-dot svg { width: 6px; height: 6px; }

/* 用户信息 */
.ub-list-info { flex: 1; min-width: 0; }
.ub-list-name {
    font-size: .85rem;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ub-list-meta {
    font-size: .72rem;
    color: var(--sub, #999);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* 状态标签 */
.ub-status {
    font-size: .7rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 20px;
    flex-shrink: 0;
    white-space: nowrap;
}
.ub-status-on   { background: rgba(108,93,251,.12); color: #6c5dfb; }
.ub-status-off  { background: rgba(0,0,0,.06);      color: var(--sub, #aaa); }
.ub-status-none { background: rgba(0,0,0,.04);      color: var(--sub, #bbb); }

/* 编辑按钮 */
.ub-edit-btn {
    flex-shrink: 0;
    padding: .28rem .75rem;
    font-size: .78rem;
    border: 1px solid var(--admin-border, rgba(155,140,255,.4));
    border-radius: 7px;
    background: transparent;
    color: var(--text, #333);
    cursor: pointer;
    transition: background .15s, border-color .15s, color .15s;
}
.ub-edit-btn:hover {
    background: rgba(108,93,251,.1);
    border-color: #6c5dfb;
    color: #6c5dfb;
}

/* ── 详情面板 ── */
.ub-detail { display: none; }
.ub-detail.ub-detail-open { display: block; }

.ub-detail-inner {
    background: var(--admin-card, #fff);
    border: 1px solid var(--admin-border, rgba(155,140,255,.3));
    border-radius: 12px;
    padding: 1.25rem 1.4rem 1.4rem;
}

.ub-detail-back {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    font-size: .82rem;
    color: var(--sub, #888);
    cursor: pointer;
    border: none;
    background: none;
    padding: 0 0 .9rem 0;
    transition: color .15s;
}
.ub-detail-back:hover { color: #6c5dfb; }

.ub-detail-userhead {
    display: flex;
    align-items: center;
    gap: .75rem;
    margin-bottom: 1rem;
    padding-bottom: .85rem;
    border-bottom: 1px solid var(--admin-border, rgba(155,140,255,.18));
}
.ub-detail-avatar { position: relative; flex-shrink: 0; }
.ub-detail-avatar img {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: block;
}
.ub-detail-dot {
    position: absolute;
    bottom: -2px;
    right: -2px;
    width: 15px;
    height: 15px;
    border-radius: 50%;
    border: 2px solid var(--admin-card, #fff);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,.25);
}
.ub-detail-dot svg { width: 8px; height: 8px; }

.ub-detail-uname { font-size: .9rem; font-weight: 700; }
.ub-detail-umeta { font-size: .75rem; color: var(--sub, #999); margin-top: 2px; }
.ub-role-admin   { color: #ef4444; font-weight: 700; }

/* 表单双列 */
.ub-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .65rem 1.2rem;
}
@media (max-width: 560px) { .ub-form-grid { grid-template-columns: 1fr; } }
.ub-form-span2 { grid-column: 1 / -1; }

.ub-field label {
    display: block;
    font-size: .73rem;
    font-weight: 600;
    color: var(--sub, #888);
    margin-bottom: 3px;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.ub-field input[type=text],
.ub-field select {
    width: 100%;
    padding: .38rem .6rem;
    border: 1px solid var(--admin-border, rgba(155,140,255,.35));
    border-radius: 8px;
    font-size: .83rem;
    background: var(--admin-card, #fff);
    color: inherit;
    box-sizing: border-box;
    transition: border-color .18s;
}
.ub-field input[type=text]:focus,
.ub-field select:focus { outline: none; border-color: #6c5dfb; }

.ub-color-row { display: flex; gap: .5rem; align-items: center; }
.ub-color-row input[type=color] {
    width: 34px;
    height: 34px;
    padding: 2px;
    border: 1px solid var(--admin-border, rgba(155,140,255,.35));
    border-radius: 8px;
    cursor: pointer;
    flex-shrink: 0;
    background: none;
}
.ub-color-row input[type=text] { flex: 1; }

.ub-divider {
    border: none;
    border-top: 1px solid var(--admin-border, rgba(155,140,255,.18));
    margin: .4rem 0;
    grid-column: 1 / -1;
}

/* 预览条 */
.ub-preview-row {
    display: flex;
    align-items: center;
    gap: .65rem;
    padding: .5rem .8rem;
    background: rgba(108,93,251,.05);
    border-radius: 8px;
    min-height: 38px;
    margin-top: .85rem;
}
.ub-preview-label { font-size: .72rem; color: var(--sub, #999); flex-shrink: 0; }
.ub-preview-name  { font-size: .85rem; font-weight: 600; }

/* 操作行 */
.ub-footer-row {
    display: flex;
    align-items: center;
    gap: .65rem;
    margin-top: .9rem;
    flex-wrap: wrap;
}
.ub-toggle-wrap {
    display: flex;
    align-items: center;
    gap: .4rem;
    margin-left: auto;
    font-size: .78rem;
    color: var(--sub, #999);
}
.ub-toggle { position: relative; display: inline-block; width: 36px; height: 20px; }
.ub-toggle input { display: none; }
.ub-toggle-slider {
    position: absolute;
    inset: 0;
    background: #ccc;
    border-radius: 10px;
    transition: background .2s;
    cursor: pointer;
}
.ub-toggle-slider:before {
    content: '';
    position: absolute;
    width: 14px; height: 14px;
    left: 3px; top: 3px;
    background: #fff;
    border-radius: 50%;
    transition: transform .2s;
    box-shadow: 0 1px 3px rgba(0,0,0,.2);
}
.ub-toggle input:checked + .ub-toggle-slider { background: #6c5dfb; }
.ub-toggle input:checked + .ub-toggle-slider:before { transform: translateX(16px); }

.ub-save-msg { font-size: .78rem; }

/* ── 暗色模式 ── */
body.dark-mode .ub-list              { border-color: rgba(176,160,255,.15); }
body.dark-mode .ub-list-row          { border-color: rgba(176,160,255,.1); }
body.dark-mode .ub-list-row:hover    { background: rgba(108,93,251,.08); }
body.dark-mode .ub-detail-inner      { background: #1e1e32; border-color: rgba(176,160,255,.18); }
body.dark-mode .ub-field input[type=text],
body.dark-mode .ub-field select      { background: #14142a; border-color: rgba(176,160,255,.3); color: #eaeaea; }
body.dark-mode .ub-preview-row       { background: rgba(108,93,251,.08); }
body.dark-mode .ub-search-bar input  { background: #14142a; border-color: rgba(176,160,255,.3); color: #eaeaea; }
body.dark-mode .ub-edit-btn          { color: #ccc; border-color: rgba(176,160,255,.3); }
body.dark-mode .ub-edit-btn:hover    { background: rgba(108,93,251,.18); border-color: #6c5dfb; color: #a78bfa; }
body.dark-mode .ub-list-dot          { border-color: #1e1e32; }
body.dark-mode .ub-detail-dot        { border-color: #1e1e32; }
</style>

<?php
// 序列化用户数据供 JS 使用
$usersJs = [];
foreach ($users as $u) {
    $usersJs[(int)$u['id']] = [
        'id'               => (int)$u['id'],
        'username'         => $u['username'] ?? '',
        'nickname'         => $u['nickname']  ?? $u['username'] ?? '',
        'email'            => $u['email'] ?? '',
        'role'             => $u['role']  ?? 'user',
        'avatar'           => _badgeAdminAvatar($u['email'] ?? '', $u['avatar'] ?? null),
        'hasBadge'         => !empty($u['badge_id']),
        'badge_type'       => $u['badge_type']       ?? 'verified',
        'badge_color'      => $u['badge_color']       ?? '#1d9bf0',
        'badge_icon_color' => $u['badge_icon_color']  ?? '#ffffff',
        'title_text'       => $u['title_text']        ?? '',
        'title_color'      => $u['title_color']       ?? '#6c5dfb',
        'title_bg_color'   => $u['title_bg_color']    ?? '',
        'is_active'        => !empty($u['is_active']),
    ];
}
?>

<div class="admin-section">
    <div class="mhdr">
        <div>
            <h2 class="mhdr-title">🏅 用户认证管理</h2>
            <p class="mhdr-sub">为任意用户配置专属认证角标（头像右下角）和头衔（昵称后）。</p>
        </div>
    </div>

    <!-- 搜索栏（列表视图） -->
    <div class="ub-search-bar" id="ubSearchBar">
        <input type="text" id="ubSearch"
               placeholder="🔍 搜索用户名 / 昵称 / 邮箱…"
               oninput="ubFilter(this.value)">
        <span style="font-size:.8rem;color:var(--sub,#999);">共 <?php echo count($users); ?> 位用户</span>
    </div>

    <!-- ══ 用户列表 ══ -->
    <div class="ub-list" id="ubList">
    <?php foreach ($users as $u):
        $uid       = (int)$u['id'];
        $nick      = htmlspecialchars($u['nickname'] ?? $u['username'] ?? '');
        $uname     = htmlspecialchars($u['username'] ?? '');
        $email     = htmlspecialchars($u['email'] ?? '');
        $role      = $u['role'] ?? 'user';
        $avatarSrc = _badgeAdminAvatar($u['email'] ?? '', $u['avatar'] ?? null);

        $hasBadge = !empty($u['badge_id']);
        $bColor   = $u['badge_color']      ?? '#1d9bf0';
        $bIconC   = $u['badge_icon_color'] ?? '#ffffff';
        $bType    = $u['badge_type']       ?? 'verified';
        $isActive = !empty($u['is_active']);

        $searchVal = strtolower(($u['username'] ?? '').'|'.($u['nickname'] ?? '').'|'.($u['email'] ?? ''));
    ?>
    <div class="ub-list-row"
         data-uid="<?php echo $uid; ?>"
         data-search="<?php echo htmlspecialchars($searchVal); ?>">

        <!-- 头像 + 角标点 -->
        <div class="ub-list-avatar">
            <img src="<?php echo htmlspecialchars($avatarSrc); ?>"
                 alt="<?php echo $nick; ?>"
                 onerror="this.src=window.__DEFAULT_AVATAR||this.dataset.fb;this.onerror=null"
                 data-fb="<?php echo DEFAULT_AVATAR_SVG; ?>">
            <?php if ($hasBadge && $isActive): ?>
            <div class="ub-list-dot" style="background:<?php echo htmlspecialchars($bColor); ?>">
                <?php echo getBadgeIconSvg($bType, $bIconC); ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- 用户信息 -->
        <div class="ub-list-info">
            <div class="ub-list-name">
                <?php if ($role === 'admin'): ?>
                    <span class="ub-role-admin">[管理员] </span>
                <?php endif; ?>
                <?php echo $nick; ?>
                <span style="font-weight:400;font-size:.75rem;color:var(--sub,#aaa);"> @<?php echo $uname; ?></span>
            </div>
            <div class="ub-list-meta"><?php echo $email; ?></div>
        </div>

        <!-- 状态标签 -->
        <?php if (!$hasBadge): ?>
            <span class="ub-status ub-status-none">未配置</span>
        <?php elseif ($isActive): ?>
            <span class="ub-status ub-status-on">已启用</span>
        <?php else: ?>
            <span class="ub-status ub-status-off">已禁用</span>
        <?php endif; ?>

        <button class="ub-edit-btn" onclick="ubOpenDetail(<?php echo $uid; ?>)">编辑</button>

    </div>
    <?php endforeach; ?>
    </div><!-- /#ubList -->

    <!-- ══ 详情面板（二级） ══ -->
    <div class="ub-detail" id="ubDetail">
    <div class="ub-detail-inner">

        <button class="ub-detail-back" onclick="ubCloseDetail()">← 返回列表</button>

        <!-- 用户头部 -->
        <div class="ub-detail-userhead">
            <div class="ub-detail-avatar">
                <img id="det_avatar" src="" alt=""
                     onerror="this.src=window.__DEFAULT_AVATAR||this.dataset.fb;this.onerror=null"
                     data-fb="<?php echo DEFAULT_AVATAR_SVG; ?>">
                <div class="ub-detail-dot" id="det_dot" style="display:none;"></div>
            </div>
            <div>
                <div class="ub-detail-uname" id="det_uname"></div>
                <div class="ub-detail-umeta" id="det_umeta"></div>
            </div>
        </div>

        <!-- 表单 -->
        <div class="ub-form-grid">

            <!-- 角标类型 -->
            <div class="ub-field">
                <label>角标类型</label>
                <select id="det_bt" onchange="detPreview()">
                    <?php foreach ($badgeTypes as $bk => $bv): ?>
                    <option value="<?php echo $bk; ?>"><?php echo htmlspecialchars($bv['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div><!-- 占位 --></div>

            <!-- 角标背景色 -->
            <div class="ub-field">
                <label>角标背景色</label>
                <div class="ub-color-row">
                    <input type="color" id="det_bc" oninput="detSyncColor('bc')">
                    <input type="text"  id="det_bc_hex" maxlength="7" placeholder="#1d9bf0"
                           oninput="detSyncHex('bc')">
                </div>
            </div>

            <!-- 角标图标色 -->
            <div class="ub-field">
                <label>角标图标色</label>
                <div class="ub-color-row">
                    <input type="color" id="det_bic" oninput="detSyncColor('bic')">
                    <input type="text"  id="det_bic_hex" maxlength="7" placeholder="#ffffff"
                           oninput="detSyncHex('bic')">
                </div>
            </div>

            <hr class="ub-divider">

            <!-- 头衔文字 -->
            <div class="ub-field ub-form-span2">
                <label>头衔文字 <small style="font-weight:400;color:var(--sub,#bbb);">（留空则不显示）</small></label>
                <input type="text" id="det_tt" maxlength="30"
                       placeholder="例：Lv.5 资深作者" oninput="detPreview()">
            </div>

            <!-- 头衔文字色 -->
            <div class="ub-field">
                <label>头衔文字色</label>
                <div class="ub-color-row">
                    <input type="color" id="det_tc" oninput="detSyncColor('tc')">
                    <input type="text"  id="det_tc_hex" maxlength="7" placeholder="#6c5dfb"
                           oninput="detSyncHex('tc')">
                </div>
            </div>

            <!-- 头衔背景色 -->
            <div class="ub-field">
                <label>头衔背景色 <small style="font-weight:400;color:var(--sub,#bbb);">（留空=无背景）</small></label>
                <div class="ub-color-row">
                    <input type="color" id="det_tbc" oninput="detSyncColor('tbc')">
                    <input type="text"  id="det_tbc_hex" maxlength="7" placeholder="留空=无背景"
                           oninput="detSyncHex('tbc')">
                    <button type="button" class="btn btn-xs"
                            onclick="document.getElementById('det_tbc_hex').value='';detPreview();"
                            title="清除背景色"
                            style="flex-shrink:0;padding:.28rem .55rem;font-size:.75rem;">✕</button>
                </div>
            </div>

        </div><!-- /.ub-form-grid -->

        <!-- 实时预览 -->
        <div class="ub-preview-row">
            <span class="ub-preview-label">预览</span>
            <span class="ub-preview-name" id="det_prev_name"></span>
            <span id="det_prev_title"
                  style="font-size:.72em;font-weight:700;margin-left:4px;vertical-align:middle;display:none;"></span>
        </div>

        <!-- 操作行 -->
        <div class="ub-footer-row">
            <button class="btn btn-primary btn-xs"
                    style="font-size:.8rem;padding:.35rem .9rem;"
                    onclick="detSave()">💾 保存</button>
            <button class="btn btn-xs" id="det_del_btn"
                    style="font-size:.78rem;padding:.3rem .7rem;background:rgba(239,68,68,.1);color:#ef4444;border-color:#ef4444;display:none;"
                    onclick="detDelete()">🗑 删除</button>
            <div class="ub-toggle-wrap">
                <span>启用</span>
                <label class="ub-toggle">
                    <input type="checkbox" id="det_active" onchange="detPreview()">
                    <span class="ub-toggle-slider"></span>
                </label>
            </div>
            <span class="ub-save-msg" id="det_msg" style="display:none;"></span>
        </div>

    </div><!-- /.ub-detail-inner -->
    </div><!-- /#ubDetail -->

</div><!-- /.admin-section -->

<script>
/* ══════════════════════════════════════════
   Badge Admin JS — List + Detail Panel
══════════════════════════════════════════ */

const UB_ICONS = {
    verified: c => `<svg viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`,
    official: c => `<svg viewBox="0 0 24 24" fill="${c}"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>`,
    vip:      c => `<svg viewBox="0 0 24 24" fill="${c}"><path d="M5 16L2 6l5.5 5L12 4l4.5 7L22 6l-3 10H5z"/><rect x="5" y="18" width="14" height="2" rx="1"/></svg>`,
    admin:    c => `<svg viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>`,
    hot:      c => `<svg viewBox="0 0 24 24" fill="${c}"><path d="M12.36 2.02C10.05 5.27 13 8.65 11 12c-1.07-3.22-4-3-4-7C4.14 7.29 3 10.35 3 13c0 5 4 9 9 9s9-4 9-9c0-5.44-4.55-9.02-8.64-10.98z"/></svg>`,
    star:     c => `<svg viewBox="0 0 24 24" fill="${c}"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>`,
};

const UB_DATA = <?php echo json_encode($usersJs, JSON_UNESCAPED_UNICODE); ?>;

let _curUid = null;

/* ── 打开详情 ── */
function ubOpenDetail(uid) {
    const u = UB_DATA[uid];
    if (!u) return;
    _curUid = uid;

    document.getElementById('ubSearchBar').style.display = 'none';
    document.getElementById('ubList').style.display      = 'none';
    const det = document.getElementById('ubDetail');
    det.classList.add('ub-detail-open');

    /* 头部 */
    document.getElementById('det_avatar').src  = u.avatar;
    document.getElementById('det_avatar').alt  = u.nickname;
    document.getElementById('det_prev_name').textContent = u.nickname;

    const rolePfx = u.role === 'admin'
        ? '<span class="ub-role-admin">[管理员] </span>' : '';
    document.getElementById('det_uname').innerHTML =
        rolePfx + escHtml(u.nickname) +
        ` <span style="font-weight:400;font-size:.78rem;color:var(--sub,#aaa);">@${escHtml(u.username)}</span>`;
    document.getElementById('det_umeta').textContent = u.email;

    document.getElementById('det_del_btn').style.display = u.hasBadge ? '' : 'none';

    /* 表单 */
    setVal('det_bt', u.badge_type);
    setColor('det_bc',  u.badge_color);
    setColor('det_bic', u.badge_icon_color);
    setVal('det_tt', u.title_text);
    setColor('det_tc',  u.title_color);
    setColor('det_tbc', u.title_bg_color);
    document.getElementById('det_active').checked = u.is_active;

    detPreview();
    det.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/* ── 关闭详情 ── */
function ubCloseDetail() {
    document.getElementById('ubDetail').classList.remove('ub-detail-open');
    document.getElementById('ubSearchBar').style.display = '';
    document.getElementById('ubList').style.display      = '';
    document.getElementById('det_msg').style.display     = 'none';
    _curUid = null;
}

/* ── 颜色辅助 ── */
function setColor(id, val) {
    const v   = (val && /^#[0-9a-fA-F]{3,6}$/.test(val.trim())) ? val.trim() : '';
    const cp  = document.getElementById(id);
    const hex = document.getElementById(id + '_hex');
    if (cp)  cp.value  = v || '#ffffff';
    if (hex) hex.value = v;
}
function setVal(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = val || '';
}
function detSyncColor(key) {
    const cp  = document.getElementById('det_' + key);
    const hex = document.getElementById('det_' + key + '_hex');
    if (cp && hex) hex.value = cp.value;
    detPreview();
}
function detSyncHex(key) {
    const hex = document.getElementById('det_' + key + '_hex');
    const cp  = document.getElementById('det_' + key);
    if (!hex || !cp) return;
    const v = hex.value.trim();
    if (/^#[0-9a-fA-F]{6}$/.test(v)) cp.value = v;
    detPreview();
}

/* ── 实时预览 ── */
function detPreview() {
    const bType  = document.getElementById('det_bt')?.value       || 'verified';
    const bColor = document.getElementById('det_bc_hex')?.value   || '#1d9bf0';
    const bIconC = document.getElementById('det_bic_hex')?.value  || '#ffffff';
    const ttxt   = document.getElementById('det_tt')?.value       || '';
    const tColor = document.getElementById('det_tc_hex')?.value   || '#6c5dfb';
    const tBgC   = document.getElementById('det_tbc_hex')?.value  || '';
    const active = document.getElementById('det_active')?.checked ?? false;

    const dot = document.getElementById('det_dot');
    if (dot) {
        if (active) {
            dot.style.background = bColor;
            dot.style.display    = 'flex';
            dot.innerHTML = `<span style="display:flex;align-items:center;justify-content:center;width:8px;height:8px;">${(UB_ICONS[bType]||UB_ICONS.verified)(bIconC)}</span>`;
        } else {
            dot.style.display = 'none';
        }
    }

    const titleEl = document.getElementById('det_prev_title');
    if (titleEl) {
        if (active && ttxt.trim()) {
            const bg = tBgC ? `background:${tBgC};padding:1px 6px;border-radius:20px;` : '';
            titleEl.style.cssText = `font-size:.72em;font-weight:700;color:${tColor};${bg}margin-left:4px;vertical-align:middle;`;
            titleEl.textContent   = ttxt;
            titleEl.style.display = 'inline';
        } else {
            titleEl.style.display = 'none';
        }
    }
}

/* ── 保存 ── */
async function detSave() {
    if (!_curUid) return;
    const data = new FormData();
    data.append('action',           'save');
    data.append('user_id',          _curUid);
    data.append('badge_type',       document.getElementById('det_bt')?.value      || 'verified');
    data.append('badge_color',      document.getElementById('det_bc_hex')?.value  || '#1d9bf0');
    data.append('badge_icon_color', document.getElementById('det_bic_hex')?.value || '#ffffff');
    data.append('title_text',       document.getElementById('det_tt')?.value      || '');
    data.append('title_color',      document.getElementById('det_tc_hex')?.value  || '#6c5dfb');
    data.append('title_bg_color',   document.getElementById('det_tbc_hex')?.value || '');
    data.append('is_active',        document.getElementById('det_active')?.checked ? '1' : '0');
    try {
        const r = await fetch('admin_ajax_badges.php', { method: 'POST', body: data });
        const d = await r.json();
        showDetMsg(d.msg || (d.ok ? '已保存' : '保存失败'), d.ok ? '#27ae60' : '#e74c3c');
        if (d.ok) {
            const u = UB_DATA[_curUid];
            u.hasBadge         = true;
            u.badge_type       = document.getElementById('det_bt').value;
            u.badge_color      = document.getElementById('det_bc_hex').value;
            u.badge_icon_color = document.getElementById('det_bic_hex').value;
            u.title_text       = document.getElementById('det_tt').value;
            u.title_color      = document.getElementById('det_tc_hex').value;
            u.title_bg_color   = document.getElementById('det_tbc_hex').value;
            u.is_active        = document.getElementById('det_active').checked;
            document.getElementById('det_del_btn').style.display = '';
            refreshListRow(_curUid);
        }
    } catch (e) { showDetMsg('网络错误', '#e74c3c'); }
}

/* ── 删除 ── */
async function detDelete() {
    if (!_curUid) return;
    if (!confirm('确定要删除该用户的角标配置吗？')) return;
    const data = new FormData();
    data.append('action',  'delete');
    data.append('user_id', _curUid);
    try {
        const r = await fetch('admin_ajax_badges.php', { method: 'POST', body: data });
        const d = await r.json();
        showDetMsg(d.msg || (d.ok ? '已删除' : '删除失败'), d.ok ? '#27ae60' : '#e74c3c');
        if (d.ok) {
            const u = UB_DATA[_curUid];
            u.hasBadge  = false;
            u.is_active = false;
            document.getElementById('det_active').checked    = false;
            document.getElementById('det_del_btn').style.display = 'none';
            detPreview();
            refreshListRow(_curUid);
        }
    } catch (e) { showDetMsg('网络错误', '#e74c3c'); }
}

/* ── 刷新列表行（保存/删除后同步状态 + 角标点） ── */
function refreshListRow(uid) {
    const row = document.querySelector(`.ub-list-row[data-uid="${uid}"]`);
    if (!row) return;
    const u = UB_DATA[uid];

    const statusEl = row.querySelector('.ub-status');
    if (statusEl) {
        if (!u.hasBadge) {
            statusEl.className   = 'ub-status ub-status-none';
            statusEl.textContent = '未配置';
        } else if (u.is_active) {
            statusEl.className   = 'ub-status ub-status-on';
            statusEl.textContent = '已启用';
        } else {
            statusEl.className   = 'ub-status ub-status-off';
            statusEl.textContent = '已禁用';
        }
    }

    const wrap = row.querySelector('.ub-list-avatar');
    if (!wrap) return;
    let dot = wrap.querySelector('.ub-list-dot');
    if (u.hasBadge && u.is_active) {
        if (!dot) {
            dot = document.createElement('div');
            dot.className = 'ub-list-dot';
            wrap.appendChild(dot);
        }
        dot.style.background = u.badge_color;
        dot.innerHTML = (UB_ICONS[u.badge_type] || UB_ICONS.verified)(u.badge_icon_color);
    } else if (dot) {
        dot.remove();
    }
}

/* ── 搜索过滤 ── */
function ubFilter(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('#ubList .ub-list-row').forEach(row => {
        const s = row.getAttribute('data-search') || '';
        row.style.display = (!q || s.includes(q)) ? '' : 'none';
    });
}

/* ── 工具 ── */
function showDetMsg(text, color) {
    const el = document.getElementById('det_msg');
    if (!el) return;
    el.textContent   = text;
    el.style.color   = color;
    el.style.display = 'inline';
    setTimeout(() => { el.style.display = 'none'; }, 3000);
}
function escHtml(s) {
    return String(s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
</script>