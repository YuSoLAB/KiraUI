<?php
/**
 * admin_email_notify.php — 邮件通知管理
 * 支持 HTML 富文本编辑、多用户收件人选择、发件防抖批量发送
 */
$db = Db::getInstance();
$config = Config::getInstance();

// 读取所有已启用用户（含邮箱）
$users = $db->query(
    "SELECT id, username, nickname, email, role, status
       FROM users
      WHERE status = 'normal'
      ORDER BY role ASC, username ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// SMTP 是否已配置
$smtpEnabled = $config->get('smtp_enabled', '0') === '1';
$smtpHost    = $config->get('smtp_host', '');
$smtpOk      = $smtpEnabled && $smtpHost !== '';

// 发送历史（最近 50 条，从 system_config 读缓存记录）
$historyRaw  = $config->get('email_notify_history', '[]');
$sendHistory = json_decode($historyRaw, true) ?: [];
$sendHistory = array_slice($sendHistory, 0, 50);

$ajaxUrl = 'admin_ajax.php';
?>

<style>
/* ══════════════════════════════════════════
   邮件通知管理 — EN（Email Notify）前缀
   ══════════════════════════════════════════ */

/* ─── 布局 ─── */
.en-wrap   { display:grid; grid-template-columns:300px 1fr; gap:1.25rem; align-items:start; }
.en-wrap-full { grid-template-columns:1fr; }

/* ─── 公共卡片 ─── */
.en-card {
    border:1px solid var(--admin-border,rgba(155,140,255,.35));
    border-radius:12px;
    background:var(--admin-card,#fff);
    overflow:hidden;
}
.en-card-hd {
    display:flex; justify-content:space-between; align-items:center;
    padding:.65rem 1rem;
    background:rgba(155,140,255,.06);
    border-bottom:1px solid var(--admin-border,rgba(155,140,255,.2));
    font-size:.82rem; font-weight:700; color:var(--sub,#666);
}
.en-card-hd span { font-size:.75rem; font-weight:400; color:var(--sub,#999); }
.en-card-bd { padding:.75rem 1rem; }

/* ─── 用户列表 ─── */
.en-filter {
    display:flex; gap:.4rem; margin-bottom:.55rem; flex-wrap:wrap;
}
.en-filter input[type=text] {
    flex:1; min-width:0;
    padding:.35rem .6rem; font-size:.8rem;
    border:1px solid var(--admin-border,rgba(155,140,255,.35));
    border-radius:7px; background:var(--admin-card,#fff); color:inherit;
}
.en-filter input:focus { outline:none; border-color:#6c5dfb; box-shadow:0 0 0 3px rgba(108,93,251,.1); }
.en-filter select {
    padding:.35rem .5rem; font-size:.78rem;
    border:1px solid var(--admin-border,rgba(155,140,255,.35));
    border-radius:7px; background:var(--admin-card,#fff); color:inherit; cursor:pointer;
}
.en-chk-all {
    display:flex; align-items:center; gap:.4rem;
    padding:.35rem .2rem; font-size:.8rem; color:var(--sub,#777);
    border-bottom:1px solid var(--admin-border,rgba(155,140,255,.12));
    margin-bottom:.3rem; cursor:pointer; user-select:none;
}
.en-chk-all input { width:15px; height:15px; accent-color:#6c5dfb; cursor:pointer; }
.en-user-list {
    max-height:340px; overflow-y:auto;
    display:flex; flex-direction:column; gap:1px;
}
.en-user-item {
    display:flex; align-items:center; gap:.55rem;
    padding:.38rem .2rem; border-radius:6px; cursor:pointer;
    transition:background .12s; font-size:.82rem;
}
.en-user-item:hover { background:rgba(155,140,255,.06); }
.en-user-item input[type=checkbox] { width:15px; height:15px; accent-color:#6c5dfb; cursor:pointer; flex-shrink:0; }
.en-user-avatar {
    width:26px; height:26px; border-radius:50%; flex-shrink:0;
    background:rgba(108,93,251,.15); display:flex; align-items:center; justify-content:center;
    font-size:.72rem; font-weight:700; color:#6c5dfb; overflow:hidden;
}
.en-user-avatar img { width:100%; height:100%; object-fit:cover; }
.en-user-info { flex:1; min-width:0; line-height:1.25; }
.en-user-name { font-weight:600; color:inherit; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.en-user-email { font-size:.72rem; color:var(--sub,#999); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.en-role-badge {
    font-size:.62rem; border-radius:8px; padding:1px 6px; flex-shrink:0;
    font-weight:700; letter-spacing:.02em;
}
.en-role-admin  { background:#ede8ff; color:#6c5dfb; }
.en-role-editor { background:#fff3cd; color:#856404; }
.en-role-user   { background:#e8f5e9; color:#2e7d32; }
.en-sel-count {
    margin-top:.55rem; padding:.3rem .5rem;
    background:rgba(108,93,251,.07); border-radius:6px;
    font-size:.79rem; color:var(--sub,#777); text-align:center;
}
.en-sel-count strong { color:#6c5dfb; }

/* ─── 右侧主编辑区 ─── */
.en-compose { display:flex; flex-direction:column; gap:.85rem; }

.en-fg { display:flex; flex-direction:column; gap:.28rem; }
.en-fg label { font-size:.83rem; font-weight:600; color:var(--sub,#666); }
.en-frow2 { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; }

.en-fg input[type=text], .en-fg input[type=email], .en-fg select, .en-fg textarea {
    padding:.48rem .72rem;
    border:1px solid var(--admin-border,rgba(155,140,255,.4));
    border-radius:8px; font-size:.9rem; box-sizing:border-box; width:100%;
    background:var(--admin-card,#fff); color:inherit; transition:border-color .15s;
    font-family:inherit;
}
.en-fg input:focus, .en-fg select:focus, .en-fg textarea:focus {
    outline:none; border-color:#6c5dfb; box-shadow:0 0 0 3px rgba(108,93,251,.1);
}

/* ─── HTML 编辑器 ─── */
.en-editor-wrap {
    border:1px solid var(--admin-border,rgba(155,140,255,.4));
    border-radius:10px; overflow:hidden;
}
.en-editor-tabs {
    display:flex; border-bottom:1px solid var(--admin-border,rgba(155,140,255,.2));
    background:rgba(155,140,255,.05);
}
.en-etab {
    padding:.42rem .9rem; font-size:.8rem; font-weight:600; cursor:pointer;
    color:var(--sub,#888); border-bottom:2px solid transparent; transition:all .15s;
    user-select:none;
}
.en-etab.active { color:#6c5dfb; border-bottom-color:#6c5dfb; background:rgba(108,93,251,.04); }
.en-etab:hover:not(.active) { color:#6c5dfb; }

.en-toolbar {
    display:flex; flex-wrap:wrap; gap:.2rem; padding:.45rem .65rem;
    border-bottom:1px solid var(--admin-border,rgba(155,140,255,.15));
    background:rgba(155,140,255,.03);
}
.en-tbtn {
    padding:.28rem .55rem; font-size:.78rem; border-radius:5px; cursor:pointer;
    border:1px solid rgba(155,140,255,.3); background:var(--admin-card,#fff);
    color:var(--sub,#555); transition:all .12s; font-family:inherit; line-height:1.4;
}
.en-tbtn:hover { background:rgba(108,93,251,.1); color:#6c5dfb; border-color:#6c5dfb; }
.en-tbtn-sep { width:1px; background:rgba(155,140,255,.25); margin:2px 3px; align-self:stretch; }

.en-editor-pane { display:none; }
.en-editor-pane.active { display:block; }

#en-html-input {
    width:100%; min-height:280px; padding:.75rem; font-size:.85rem;
    font-family:'Fira Code', 'Courier New', monospace; line-height:1.65;
    border:none; resize:vertical; background:var(--admin-card,#fff); color:inherit;
    box-sizing:border-box;
}
#en-html-input:focus { outline:none; }

#en-preview-pane {
    min-height:280px; padding:1rem 1.2rem;
    font-size:.9rem; line-height:1.75; color:#333;
    background:#fafafa;
}

.en-tpl-bar {
    display:flex; gap:.4rem; flex-wrap:wrap; padding:.5rem .65rem;
    border-bottom:1px solid var(--admin-border,rgba(155,140,255,.12));
    background:rgba(155,140,255,.02);
}
.en-tpl-btn {
    padding:.22rem .62rem; font-size:.73rem; border-radius:12px; cursor:pointer;
    border:1px solid rgba(155,140,255,.3); background:rgba(108,93,251,.05);
    color:#6c5dfb; font-weight:600; transition:all .12s;
}
.en-tpl-btn:hover { background:#6c5dfb; color:#fff; border-color:#6c5dfb; }

/* ─── 发送设置 ─── */
.en-send-cfg {
    display:grid; grid-template-columns:1fr 1fr 1fr; gap:.75rem;
}
.en-cfg-item { display:flex; flex-direction:column; gap:.25rem; }
.en-cfg-item label { font-size:.78rem; font-weight:600; color:var(--sub,#777); }
.en-cfg-item input[type=number] {
    padding:.38rem .6rem; font-size:.88rem;
    border:1px solid var(--admin-border,rgba(155,140,255,.4));
    border-radius:7px; background:var(--admin-card,#fff); color:inherit;
    box-sizing:border-box; width:100%;
}
.en-cfg-item input:focus { outline:none; border-color:#6c5dfb; box-shadow:0 0 0 3px rgba(108,93,251,.1); }
.en-cfg-hint { font-size:.7rem; color:var(--sub,#aaa); margin-top:1px; }

/* ─── 进度条 ─── */
.en-progress { display:none; flex-direction:column; gap:.5rem; }
.en-progress.show { display:flex; }
.en-progress-bar-wrap {
    height:10px; border-radius:8px; background:rgba(155,140,255,.15); overflow:hidden;
}
.en-progress-bar {
    height:100%; border-radius:8px; background:linear-gradient(90deg,#6c5dfb,#a78bfa);
    transition:width .35s; width:0%;
}
.en-progress-label { font-size:.8rem; color:var(--sub,#777); display:flex; justify-content:space-between; }
.en-progress-log {
    max-height:120px; overflow-y:auto; font-size:.75rem; line-height:1.7;
    background:rgba(155,140,255,.04); border-radius:7px; padding:.5rem .75rem;
    color:var(--sub,#666); border:1px solid var(--admin-border,rgba(155,140,255,.2));
}
.en-log-ok   { color:#27ae60; }
.en-log-fail { color:#c0392b; }

/* ─── 发送按钮区 ─── */
.en-action-row { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; }
.en-send-result {
    font-size:.84rem; padding:.4rem .85rem; border-radius:7px;
    display:none;
}
.en-send-result.ok  { background:#f0fff4; color:#276749; border:1px solid #9ae6b4; display:block; }
.en-send-result.err { background:#fff0f0; color:#c53030; border:1px solid #fc8181; display:block; }

/* ─── 发送历史 ─── */
.en-history-list { display:flex; flex-direction:column; gap:0; }
.en-history-item {
    display:grid; grid-template-columns:1fr auto auto;
    gap:.5rem; align-items:center;
    padding:.55rem .75rem; font-size:.8rem;
    border-bottom:1px solid var(--admin-border,rgba(155,140,255,.1));
}
.en-history-item:last-child { border-bottom:none; }
.en-history-subject { font-weight:600; color:inherit; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.en-history-meta { font-size:.72rem; color:var(--sub,#aaa); margin-top:.1rem; }
.en-history-stat {
    font-size:.72rem; border-radius:8px; padding:2px 9px; font-weight:700; white-space:nowrap;
}
.en-hs-ok { background:#d4edda; color:#155724; }
.en-hs-partial { background:#fff3cd; color:#856404; }
.en-hs-fail { background:#f8d7da; color:#721c24; }
.en-history-time { font-size:.72rem; color:var(--sub,#aaa); white-space:nowrap; }
.en-empty { padding:2rem; text-align:center; color:var(--sub,#bbb); font-size:.875rem; }

/* ─── SMTP 警告 ─── */
.en-warn {
    display:flex; align-items:center; gap:.6rem;
    padding:.65rem 1rem; border-radius:8px; font-size:.84rem;
    background:#fff8e1; border:1px solid #ffe082; color:#7a5800;
    margin-bottom:1rem;
}
body.dark-mode .en-warn { background:#2e2510; border-color:#735a00; color:#f2c94c; }

/* ─── 暗色模式 ─── */
body.dark-mode .en-card { background:var(--dark-admin-card,#2a2a42dd); border-color:var(--dark-admin-border); }
body.dark-mode .en-card-hd { background:rgba(176,160,255,.05); border-bottom-color:var(--dark-admin-border); color:var(--dark-sub,#b0b0c5); }
body.dark-mode .en-etab { color:var(--dark-sub,#9090b0); }
body.dark-mode .en-etab.active { color:var(--dark-vio,#b096ff); border-bottom-color:var(--dark-vio,#b096ff); }
body.dark-mode .en-toolbar { background:rgba(176,160,255,.03); border-bottom-color:rgba(176,160,255,.1); }
body.dark-mode .en-tbtn { background:#2a2a42; border-color:rgba(176,160,255,.25); color:var(--dark-sub,#b0b0c5); }
body.dark-mode .en-tbtn:hover { background:rgba(176,160,255,.12); color:var(--dark-vio,#b096ff); border-color:var(--dark-vio,#b096ff); }
body.dark-mode .en-tpl-btn { background:rgba(176,160,255,.07); color:var(--dark-vio,#b096ff); border-color:rgba(176,160,255,.25); }
body.dark-mode .en-tpl-btn:hover { background:var(--dark-vio,#b096ff); color:#1a1a2e; }
body.dark-mode #en-html-input { background:#1a1a30; color:#eaeaea; }
body.dark-mode #en-preview-pane { background:#1e1e32; color:#dde; }
body.dark-mode .en-progress-log { background:rgba(176,160,255,.04); border-color:var(--dark-admin-border); color:var(--dark-sub,#b0b0c5); }
body.dark-mode .en-progress-bar-wrap { background:rgba(176,160,255,.1); }
body.dark-mode .en-filter input[type=text],
body.dark-mode .en-filter select { background:#2a2a42; border-color:var(--dark-admin-border); color:#eaeaea; }
body.dark-mode .en-fg input, body.dark-mode .en-fg select, body.dark-mode .en-fg textarea { background:#1e1e32!important; border-color:var(--dark-admin-border)!important; color:#eaeaea!important; }
body.dark-mode .en-cfg-item input { background:#1e1e32; border-color:var(--dark-admin-border); color:#eaeaea; }
body.dark-mode .en-user-item:hover { background:rgba(176,160,255,.07); }
body.dark-mode .en-user-avatar { background:rgba(176,160,255,.12); color:var(--dark-vio,#b096ff); }
body.dark-mode .en-sel-count { background:rgba(176,160,255,.07); color:var(--dark-sub,#b0b0c5); }
body.dark-mode .en-sel-count strong { color:var(--dark-vio,#b096ff); }
body.dark-mode .en-role-admin  { background:rgba(176,160,255,.14); color:var(--dark-vio,#b096ff); }
body.dark-mode .en-role-editor { background:rgba(242,201,76,.1);  color:#f2c94c; }
body.dark-mode .en-role-user   { background:rgba(111,207,151,.1); color:#6fcf97; }
body.dark-mode .en-history-item { border-bottom-color:rgba(176,160,255,.1); }
body.dark-mode .en-hs-ok   { background:#1e3a26; color:#6fcf97; }
body.dark-mode .en-hs-partial { background:#2e2510; color:#f2c94c; }
body.dark-mode .en-hs-fail { background:#3a1e22; color:#eb5757; }
body.dark-mode .en-send-result.ok  { background:#1a3a2a; color:#9ae6b4; border-color:#276749; }
body.dark-mode .en-send-result.err { background:#3a1a1a; color:#fc8181; border-color:#c53030; }
body.dark-mode .en-editor-wrap { border-color:var(--dark-admin-border); }
body.dark-mode .en-tpl-bar { border-bottom-color:rgba(176,160,255,.1); }

/* ─── 响应式 ─── */
@media (max-width:860px) {
    .en-wrap { grid-template-columns:1fr; }
    .en-send-cfg { grid-template-columns:1fr 1fr; }
    .en-frow2 { grid-template-columns:1fr; }
}
@media (max-width:520px) {
    .en-send-cfg { grid-template-columns:1fr; }
}
</style>

<div class="admin-section">

    <div class="mhdr">
        <div>
            <h2 class="mhdr-title">✉️ 邮件通知管理</h2>
            <p class="mhdr-sub">编写 HTML 邮件内容，选择收件用户，支持批量分批发送防触发限制。</p>
        </div>
    </div>

    <?php if (!$smtpOk): ?>
    <div class="en-warn">
        ⚠️ 当前 SMTP 服务未启用或未配置，邮件将无法发送。
        请先前往 <strong>SMTP 管理</strong> 完成配置。
    </div>
    <?php endif; ?>

    <div class="en-wrap">

        <!-- ══ 左侧：收件人选择 ══ -->
        <div>
            <div class="en-card">
                <div class="en-card-hd">
                    📋 收件人
                    <span id="en-total-count"><?php echo count($users); ?> 位用户</span>
                </div>
                <div class="en-card-bd">

                    <div class="en-filter">
                        <input type="text" id="en-search" placeholder="搜索用户名 / 邮箱…" oninput="enFilterUsers()">
                        <select id="en-role-filter" onchange="enFilterUsers()">
                            <option value="">全部角色</option>
                            <option value="admin">管理员</option>
                            <option value="editor">编辑</option>
                            <option value="user">用户</option>
                        </select>
                    </div>

                    <label class="en-chk-all" onclick="enToggleAll()">
                        <input type="checkbox" id="en-chk-all-input" onchange="enToggleAll()">
                        全选 / 取消全选
                    </label>

                    <div class="en-user-list" id="en-user-list">
                        <?php foreach ($users as $u):
                            $display = $u['nickname'] ?: $u['username'];
                            $initials = mb_strtoupper(mb_substr($display, 0, 1));
                            $roleCls  = 'en-role-' . $u['role'];
                            $roleMap  = ['admin'=>'管理员','editor'=>'编辑','user'=>'用户'];
                            $roleLabel = $roleMap[$u['role']] ?? $u['role'];
                        ?>
                        <label class="en-user-item"
                               data-name="<?php echo htmlspecialchars(strtolower($u['username'])); ?>"
                               data-email="<?php echo htmlspecialchars(strtolower($u['email'] ?? '')); ?>"
                               data-role="<?php echo htmlspecialchars($u['role']); ?>">
                            <input type="checkbox" class="en-user-chk"
                                   name="recipient[]"
                                   value="<?php echo (int)$u['id']; ?>"
                                   data-email="<?php echo htmlspecialchars($u['email'] ?? ''); ?>"
                                   data-name="<?php echo htmlspecialchars($display); ?>">
                            <span class="en-user-avatar"><?php echo $initials; ?></span>
                            <span class="en-user-info">
                                <span class="en-user-name"><?php echo htmlspecialchars($display); ?></span>
                                <span class="en-user-email"><?php
                                    echo !empty($u['email'])
                                        ? htmlspecialchars($u['email'])
                                        : '<span style="font-style:italic;opacity:.6;">未绑定邮箱</span>';
                                ?></span>
                            </span>
                            <span class="en-role-badge <?php echo $roleCls; ?>"><?php echo $roleLabel; ?></span>
                        </label>
                        <?php endforeach; ?>
                        <?php if (empty($users)): ?>
                        <div class="en-empty">暂无可用用户</div>
                        <?php endif; ?>
                    </div>

                    <div class="en-sel-count">
                        已选 <strong id="en-sel-num">0</strong> 人 /
                        共 <strong id="en-vis-num"><?php echo count($users); ?></strong> 人
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ 右侧：撰写邮件 ══ -->
        <div class="en-compose">

            <!-- 基本信息 -->
            <div class="en-card">
                <div class="en-card-hd">📝 邮件信息</div>
                <div class="en-card-bd" style="display:flex;flex-direction:column;gap:.75rem;">
                    <div class="en-fg">
                        <label>邮件主题 <span style="color:#c0392b">*</span></label>
                        <input type="text" id="en-subject" placeholder="输入邮件主题…" maxlength="200">
                    </div>
                    <div class="en-frow2">
                        <div class="en-fg">
                            <label>发件人名称</label>
                            <input type="text" id="en-from-name"
                                   value="<?php echo htmlspecialchars($config->get('smtp_from_name', '')); ?>"
                                   placeholder="系统默认">
                        </div>
                        <div class="en-fg">
                            <label>回复地址（Reply-To）</label>
                            <input type="text" id="en-reply-to" placeholder="留空则使用发件人地址">
                        </div>
                    </div>
                </div>
            </div>

            <!-- HTML 编辑器 -->
            <div class="en-card">
                <div class="en-card-hd">
                    🖊 邮件内容（HTML）
                    <span>支持完整 HTML / 内联 CSS</span>
                </div>
                <div class="en-editor-wrap">

                    <!-- 标签页切换 -->
                    <div class="en-editor-tabs">
                        <div class="en-etab active" onclick="enSwitchTab('edit')">✏️ 编辑</div>
                        <div class="en-etab"         onclick="enSwitchTab('preview')">👁 预览</div>
                        <div class="en-etab"         onclick="enSwitchTab('template')">📂 快速模板</div>
                    </div>

                    <!-- 编辑工具栏 -->
                    <div class="en-toolbar" id="en-toolbar">
                        <button class="en-tbtn" onclick="enFmt('bold')" title="粗体"><b>B</b></button>
                        <button class="en-tbtn" onclick="enFmt('italic')" title="斜体"><i>I</i></button>
                        <button class="en-tbtn" onclick="enFmt('underline')" title="下划线"><u>U</u></button>
                        <div class="en-tbtn-sep"></div>
                        <button class="en-tbtn" onclick="enInsert('h2')" title="大标题">H2</button>
                        <button class="en-tbtn" onclick="enInsert('h3')" title="小标题">H3</button>
                        <button class="en-tbtn" onclick="enInsert('p')" title="段落">¶</button>
                        <button class="en-tbtn" onclick="enInsert('hr')" title="分割线">─</button>
                        <div class="en-tbtn-sep"></div>
                        <button class="en-tbtn" onclick="enInsert('ul')" title="无序列表">• 列表</button>
                        <button class="en-tbtn" onclick="enInsert('ol')" title="有序列表">① 列表</button>
                        <div class="en-tbtn-sep"></div>
                        <button class="en-tbtn" onclick="enInsert('btn')" title="按钮">🔘 按钮</button>
                        <button class="en-tbtn" onclick="enInsert('link')" title="链接">🔗 链接</button>
                        <button class="en-tbtn" onclick="enInsert('img')" title="图片">🖼 图片</button>
                        <div class="en-tbtn-sep"></div>
                        <button class="en-tbtn" onclick="enInsert('var_name')" title="变量：用户名">{{姓名}}</button>
                        <button class="en-tbtn" onclick="enInsert('var_email')" title="变量：邮箱">{{邮箱}}</button>
                    </div>

                    <!-- 编辑面板 -->
                    <div class="en-editor-pane active" id="en-pane-edit">
                        <textarea id="en-html-input" placeholder="在此输入 HTML 邮件内容…
例如：
&lt;h2&gt;通知标题&lt;/h2&gt;
&lt;p&gt;亲爱的 {{name}}，&lt;/p&gt;
&lt;p&gt;这是来自站点的重要通知。&lt;/p&gt;"
                                  oninput="enUpdatePreview()"></textarea>
                    </div>

                    <!-- 预览面板 -->
                    <div class="en-editor-pane" id="en-pane-preview">
                        <div id="en-preview-pane">
                            <em style="color:#aaa;font-size:.8rem;">切换到「编辑」标签后，预览将在此自动更新。</em>
                        </div>
                    </div>

                    <!-- 模板面板 -->
                    <div class="en-editor-pane" id="en-pane-template">
                        <div class="en-tpl-bar">
                            <button class="en-tpl-btn" onclick="enLoadTpl('welcome')">🎉 欢迎邮件</button>
                            <button class="en-tpl-btn" onclick="enLoadTpl('notice')">📢 系统公告</button>
                            <button class="en-tpl-btn" onclick="enLoadTpl('activity')">🎁 活动通知</button>
                            <button class="en-tpl-btn" onclick="enLoadTpl('security')">🔒 安全提醒</button>
                            <button class="en-tpl-btn" onclick="enLoadTpl('simple')">📄 纯文本样式</button>
                        </div>
                        <div style="padding:.9rem 1rem;font-size:.82rem;color:var(--sub,#888);line-height:1.7;">
                            选择模板将<strong>替换</strong>当前编辑内容（不可撤销）。<br>
                            模板中 <code>{{name}}</code> 会在发送时自动替换为用户昵称或用户名，<code>{{email}}</code> 替换为用户邮箱。
                        </div>
                    </div>

                </div>
            </div>

            <!-- 发送设置 -->
            <div class="en-card">
                <div class="en-card-hd">
                    ⚙️ 发送设置
                    <span>防抖配置，避免触发邮件服务器限制</span>
                </div>
                <div class="en-card-bd">
                    <div class="en-send-cfg">
                        <div class="en-cfg-item">
                            <label>每批发送数量</label>
                            <input type="number" id="en-batch-size" value="5" min="1" max="50">
                            <div class="en-cfg-hint">每批最多发送 N 封后暂停</div>
                        </div>
                        <div class="en-cfg-item">
                            <label>批次间隔（秒）</label>
                            <input type="number" id="en-batch-delay" value="3" min="1" max="60">
                            <div class="en-cfg-hint">两批之间等待 N 秒再发下一批</div>
                        </div>
                        <div class="en-cfg-item">
                            <label>单封间隔（毫秒）</label>
                            <input type="number" id="en-msg-delay" value="200" min="0" max="5000" step="100">
                            <div class="en-cfg-hint">同一批内每封之间的延迟</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 发送进度 -->
            <div class="en-progress" id="en-progress">
                <div class="en-progress-label">
                    <span id="en-prog-label">准备发送…</span>
                    <span id="en-prog-pct">0%</span>
                </div>
                <div class="en-progress-bar-wrap">
                    <div class="en-progress-bar" id="en-progress-bar"></div>
                </div>
                <div class="en-progress-log" id="en-progress-log"></div>
            </div>

            <!-- 操作按钮 -->
            <div class="en-action-row">
                <button class="btn btn-primary" id="en-send-btn" onclick="enStartSend()">
                    🚀 开始发送
                </button>
                <button class="btn btn-secondary" id="en-stop-btn" onclick="enStopSend()" style="display:none;">
                    ⏹ 停止发送
                </button>
                <button class="btn btn-secondary" onclick="enPreviewSend()">
                    👁 发送预览
                </button>
                <div class="en-send-result" id="en-send-result"></div>
            </div>
        </div>
    </div>

    <!-- ══ 发送历史 ══ -->
    <div style="margin-top:1.5rem;">
        <div class="en-card">
            <div class="en-card-hd">
                📜 发送历史
                <span>最近 50 条</span>
            </div>
            <div id="en-history-bd">
                <?php if (empty($sendHistory)): ?>
                <div class="en-empty">暂无发送记录</div>
                <?php else: ?>
                <div class="en-history-list" id="en-history-list">
                    <?php foreach ($sendHistory as $h):
                        $okCnt   = (int)($h['ok'] ?? 0);
                        $failCnt = (int)($h['fail'] ?? 0);
                        $total   = $okCnt + $failCnt;
                        if ($failCnt === 0) { $cls = 'en-hs-ok'; $label = '全部成功'; }
                        elseif ($okCnt === 0) { $cls = 'en-hs-fail'; $label = '全部失败'; }
                        else { $cls = 'en-hs-partial'; $label = "部分成功 {$okCnt}/{$total}"; }
                    ?>
                    <div class="en-history-item">
                        <div>
                            <div class="en-history-subject"><?php echo htmlspecialchars($h['subject'] ?? '-'); ?></div>
                            <div class="en-history-meta">收件人 <?php echo $total; ?> 人</div>
                        </div>
                        <span class="en-history-stat <?php echo $cls; ?>"><?php echo $label; ?></span>
                        <span class="en-history-time"><?php echo htmlspecialchars($h['time'] ?? ''); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div><!-- /.admin-section -->

<!-- ══ 发送预览 Modal ══ -->
<div id="en-preview-modal" class="mmodal" style="display:none;" onclick="if(event.target===this)enClosePreview()">
    <div class="mmodal-box" style="width:680px;max-width:96vw;">
        <div class="mmodal-hd">
            <h3>📧 发送预览</h3>
            <button onclick="enClosePreview()">✕</button>
        </div>
        <div class="mmodal-bd" style="padding:0;">
            <div style="padding:.75rem 1.2rem;border-bottom:1px solid var(--admin-border,rgba(155,140,255,.2));font-size:.83rem;color:var(--sub,#777);">
                <div><strong>主题：</strong><span id="enpm-subject">-</span></div>
                <div><strong>收件人：</strong><span id="enpm-to">-</span></div>
            </div>
            <iframe id="enpm-iframe"
                    style="width:100%;height:400px;border:none;background:#fff;"
                    sandbox="allow-same-origin"></iframe>
        </div>
        <div class="mmodal-ft">
            <button class="btn btn-secondary" onclick="enClosePreview()">关闭</button>
        </div>
    </div>
</div>

<script>
(function() {
/* ═════════════════════════════════════════════════
   全局状态
   ═════════════════════════════════════════════════ */
const AJAX_URL  = <?php echo json_encode($ajaxUrl); ?>;
let   isSending = false;
let   stopFlag  = false;

/* ─── 用户数据（服务端注入）─── */
const allUsers  = <?php echo json_encode(array_map(fn($u) => [
    'id'    => $u['id'],
    'name'  => $u['nickname'] ?: $u['username'],
    'email' => $u['email'],
    'role'  => $u['role'],
], $users)); ?>;

/* ═════════════════════════════════════════════════
   收件人选择
   ═════════════════════════════════════════════════ */
function enFilterUsers() {
    const q    = document.getElementById('en-search').value.toLowerCase().trim();
    const role = document.getElementById('en-role-filter').value;
    const items = document.querySelectorAll('#en-user-list .en-user-item');
    let vis = 0;
    items.forEach(item => {
        const match =
            (!q    || item.dataset.name.includes(q) || item.dataset.email.includes(q)) &&
            (!role || item.dataset.role === role);
        item.style.display = match ? '' : 'none';
        if (match) vis++;
    });
    document.getElementById('en-vis-num').textContent = vis;
    enUpdateSelCount();
}

function enToggleAll() {
    const chkAll = document.getElementById('en-chk-all-input');
    const items  = Array.from(document.querySelectorAll('#en-user-list .en-user-item'))
                       .filter(el => el.style.display !== 'none');
    const chks   = items.map(el => el.querySelector('.en-user-chk'));
    const allChecked = chks.every(c => c.checked);
    chks.forEach(c => c.checked = !allChecked);
    chkAll.checked = !allChecked;
    enUpdateSelCount();
}

document.querySelectorAll('.en-user-chk').forEach(c =>
    c.addEventListener('change', enUpdateSelCount)
);

function enUpdateSelCount() {
    const cnt = document.querySelectorAll('.en-user-chk:checked').length;
    document.getElementById('en-sel-num').textContent = cnt;
}

function enGetSelectedRecipients() {
    return Array.from(document.querySelectorAll('.en-user-chk:checked')).map(c => ({
        id    : c.value,
        email : c.dataset.email,
        name  : c.dataset.name,
    }));
}

/* ═════════════════════════════════════════════════
   编辑器
   ═════════════════════════════════════════════════ */
function enSwitchTab(name) {
    document.querySelectorAll('.en-etab').forEach((t, i) => {
        const names = ['edit','preview','template'];
        t.classList.toggle('active', names[i] === name);
    });
    document.querySelectorAll('.en-editor-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('en-pane-' + name).classList.add('active');
    if (name === 'preview') enUpdatePreview();
    document.getElementById('en-toolbar').style.display = name === 'edit' ? 'flex' : 'none';
}

function enUpdatePreview() {
    const html = document.getElementById('en-html-input').value;
    document.getElementById('en-preview-pane').innerHTML = html || '<em style="color:#aaa">（无内容）</em>';
}

/* ── 格式化快捷按钮（操作 textarea 光标区域）── */
function enGetTA() { return document.getElementById('en-html-input'); }

function enFmt(type) {
    const ta = enGetTA();
    const s  = ta.selectionStart, e = ta.selectionEnd;
    const sel = ta.value.substring(s, e) || '文本';
    const tags = { bold: ['<strong>','</strong>'], italic: ['<em>','</em>'], underline: ['<u>','</u>'] };
    const [open, close] = tags[type];
    const ins = open + sel + close;
    ta.value = ta.value.substring(0, s) + ins + ta.value.substring(e);
    ta.selectionStart = s + open.length;
    ta.selectionEnd   = s + open.length + sel.length;
    ta.focus();
    enUpdatePreview();
}

const SNIPPETS = {
    h2   : '\n<h2 style="color:#333;margin-bottom:.5rem;">标题文字</h2>\n',
    h3   : '\n<h3 style="color:#555;margin-bottom:.4rem;">小标题</h3>\n',
    p    : '\n<p style="line-height:1.8;color:#444;margin:.5rem 0;">段落文字内容。</p>\n',
    hr   : '\n<hr style="border:none;border-top:1px solid #eee;margin:1rem 0;">\n',
    ul   : '\n<ul style="line-height:2;color:#444;padding-left:1.2rem;">\n  <li>条目一</li>\n  <li>条目二</li>\n</ul>\n',
    ol   : '\n<ol style="line-height:2;color:#444;padding-left:1.2rem;">\n  <li>第一步</li>\n  <li>第二步</li>\n</ol>\n',
    btn  : '\n<p style="text-align:center;margin:1.2rem 0;">\n  <a href="https://example.com" style="display:inline-block;padding:.6rem 1.8rem;background:#6c5dfb;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;">立即查看</a>\n</p>\n',
    link : '\n<a href="https://example.com" style="color:#6c5dfb;">链接文字</a>\n',
    img  : '\n<img src="https://example.com/image.jpg" alt="图片" style="max-width:100%;border-radius:8px;">\n',
    var_name  : '{{name}}',
    var_email : '{{email}}',
};

function enInsert(type) {
    const ta  = enGetTA();
    const pos = ta.selectionStart;
    const ins = SNIPPETS[type] || '';
    ta.value  = ta.value.substring(0, pos) + ins + ta.value.substring(pos);
    ta.selectionStart = ta.selectionEnd = pos + ins.length;
    ta.focus();
    enUpdatePreview();
}

/* ── 模板 ── */
const TEMPLATES = {
    welcome: `<table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;">
  <tr><td style="background:#6c5dfb;padding:2rem;text-align:center;border-radius:12px 12px 0 0;">
    <h1 style="color:#fff;margin:0;font-size:1.6rem;">🎉 欢迎加入</h1>
  </td></tr>
  <tr><td style="background:#fff;padding:2rem;border:1px solid #eee;border-top:none;border-radius:0 0 12px 12px;">
    <p style="color:#444;line-height:1.8;">亲爱的 <strong>{{name}}</strong>，</p>
    <p style="color:#444;line-height:1.8;">感谢您注册成为我们的用户！我们很高兴有您加入。</p>
    <p style="text-align:center;margin:1.5rem 0;">
      <a href="#" style="display:inline-block;padding:.6rem 2rem;background:#6c5dfb;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;">开始探索</a>
    </p>
    <hr style="border:none;border-top:1px solid #eee;margin:1.5rem 0;">
    <p style="color:#999;font-size:.8rem;text-align:center;">如有疑问请回复此邮件联系我们。</p>
  </td></tr>
</table>`,
    notice: `<table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;">
  <tr><td style="background:#2c2c3e;padding:1.5rem 2rem;border-radius:12px 12px 0 0;">
    <h2 style="color:#fff;margin:0;">📢 系统公告</h2>
  </td></tr>
  <tr><td style="background:#fff;padding:2rem;border:1px solid #eee;border-top:none;border-radius:0 0 12px 12px;">
    <p style="color:#444;line-height:1.8;">尊敬的用户 <strong>{{name}}</strong>：</p>
    <p style="color:#444;line-height:1.8;padding:1rem;background:#f8f8ff;border-left:4px solid #6c5dfb;border-radius:0 6px 6px 0;">
      <strong>公告内容：</strong>在此填写具体公告信息…
    </p>
    <p style="color:#888;font-size:.85rem;">如有疑问，请联系管理员。</p>
  </td></tr>
</table>`,
    activity: `<table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;">
  <tr><td style="background:linear-gradient(135deg,#6c5dfb,#f093fb);padding:2rem;text-align:center;border-radius:12px 12px 0 0;">
    <h1 style="color:#fff;margin:0;">🎁 限时活动</h1>
    <p style="color:rgba(255,255,255,.85);margin:.5rem 0 0;">仅限本周，不容错过！</p>
  </td></tr>
  <tr><td style="background:#fff;padding:2rem;border:1px solid #eee;border-top:none;border-radius:0 0 12px 12px;">
    <p style="color:#444;line-height:1.8;">Hi <strong>{{name}}</strong>，</p>
    <p style="color:#444;line-height:1.8;">我们为您准备了专属活动福利，详情如下：</p>
    <ul style="color:#444;line-height:2.2;padding-left:1.2rem;">
      <li>活动一：描述内容</li>
      <li>活动二：描述内容</li>
    </ul>
    <p style="text-align:center;margin:1.5rem 0;">
      <a href="#" style="display:inline-block;padding:.7rem 2.5rem;background:linear-gradient(135deg,#6c5dfb,#f093fb);color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;font-size:1rem;">立即参与</a>
    </p>
  </td></tr>
</table>`,
    security: `<table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;">
  <tr><td style="background:#e74c3c;padding:1.5rem 2rem;border-radius:12px 12px 0 0;">
    <h2 style="color:#fff;margin:0;">🔒 安全提醒</h2>
  </td></tr>
  <tr><td style="background:#fff;padding:2rem;border:1px solid #eee;border-top:none;border-radius:0 0 12px 12px;">
    <p style="color:#444;line-height:1.8;">尊敬的 <strong>{{name}}</strong>，</p>
    <p style="color:#c0392b;padding:1rem;background:#fff5f5;border:1px solid #fc8181;border-radius:8px;line-height:1.8;">
      ⚠️ 我们检测到您的账号存在安全风险，请及时处理。
    </p>
    <p style="color:#444;line-height:1.8;">如非本人操作，请立即修改密码并联系管理员。</p>
    <p style="color:#999;font-size:.8rem;">此邮件由系统自动发送，请勿回复。</p>
  </td></tr>
</table>`,
    simple: `<div style="max-width:560px;margin:0 auto;font-family:Georgia,serif;padding:2rem;color:#333;line-height:1.9;">
  <p>亲爱的 {{name}}：</p>
  <p>您好。在此填写邮件正文内容，支持普通段落文字。</p>
  <p>如有任何问题，欢迎随时回复本邮件与我们联系。</p>
  <p style="margin-top:2rem;">祝好，<br><strong>站点团队</strong></p>
  <hr style="border:none;border-top:1px solid #ddd;margin:2rem 0;">
  <p style="color:#aaa;font-size:.75rem;">您收到此邮件是因为您注册了我们的服务（{{email}}）。</p>
</div>`,
};

function enLoadTpl(name) {
    if (!confirm('加载模板将替换当前内容，是否继续？')) return;
    const ta = enGetTA();
    ta.value = TEMPLATES[name] || '';
    enUpdatePreview();
    enSwitchTab('edit');
}

/* ═════════════════════════════════════════════════
   发送逻辑
   ═════════════════════════════════════════════════ */
function enLog(msg, cls) {
    const log = document.getElementById('en-progress-log');
    const span = document.createElement('div');
    span.className = cls || '';
    span.textContent = msg;
    log.appendChild(span);
    log.scrollTop = log.scrollHeight;
}

function enSetProgress(done, total) {
    const pct = total > 0 ? Math.round(done / total * 100) : 0;
    document.getElementById('en-progress-bar').style.width = pct + '%';
    document.getElementById('en-prog-pct').textContent = pct + '%';
    document.getElementById('en-prog-label').textContent =
        `已发送 ${done} / ${total} 封`;
}

function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

async function enStartSend() {
    if (isSending) return;

    const subject = document.getElementById('en-subject').value.trim();
    const html    = document.getElementById('en-html-input').value.trim();
    const recips  = enGetSelectedRecipients();

    if (!subject)         return enShowResult('err', '请填写邮件主题');
    if (!html)            return enShowResult('err', '请填写邮件内容');
    if (recips.length < 1) return enShowResult('err', '请至少选择一位收件人');

    const batchSize  = Math.max(1, parseInt(document.getElementById('en-batch-size').value) || 5);
    const batchDelay = Math.max(0, parseInt(document.getElementById('en-batch-delay').value) || 3) * 1000;
    const msgDelay   = Math.max(0, parseInt(document.getElementById('en-msg-delay').value) || 200);
    const fromName   = document.getElementById('en-from-name').value.trim();
    const replyTo    = document.getElementById('en-reply-to').value.trim();

    isSending = true; stopFlag = false;
    document.getElementById('en-send-btn').style.display = 'none';
    document.getElementById('en-stop-btn').style.display = '';
    document.getElementById('en-send-result').className = 'en-send-result';
    document.getElementById('en-send-result').style.display = 'none';
    document.getElementById('en-progress-log').innerHTML = '';
    const progress = document.getElementById('en-progress');
    progress.classList.add('show');
    enSetProgress(0, recips.length);

    let okCnt = 0, failCnt = 0;

    // 分批处理
    for (let batchStart = 0; batchStart < recips.length; batchStart += batchSize) {
        if (stopFlag) { enLog('⏹ 用户已停止发送。', ''); break; }

        const batch = recips.slice(batchStart, batchStart + batchSize);

        for (const r of batch) {
            if (stopFlag) break;

            // 替换变量
            const personHtml    = html.replace(/{{name}}/g, r.name).replace(/{{email}}/g, r.email);
            const personSubject = subject.replace(/{{name}}/g, r.name).replace(/{{email}}/g, r.email);

            try {
                const fd = new FormData();
                fd.append('type',       'email_notify');
                fd.append('action',     'send_one');
                fd.append('to_email',   r.email);
                fd.append('to_name',    r.name);
                fd.append('subject',    personSubject);
                fd.append('html',       personHtml);
                fd.append('from_name',  fromName);
                fd.append('reply_to',   replyTo);

                const res  = await fetch(AJAX_URL, { method:'POST', body:fd });
                const data = await res.json();

                if (data.ok) {
                    okCnt++;
                    enLog(`✓ ${r.email} — 发送成功`, 'en-log-ok');
                } else {
                    failCnt++;
                    enLog(`✗ ${r.email} — ${data.msg || '发送失败'}`, 'en-log-fail');
                }
            } catch(e) {
                failCnt++;
                enLog(`✗ ${r.email} — 网络错误`, 'en-log-fail');
            }

            enSetProgress(okCnt + failCnt, recips.length);
            if (msgDelay > 0) await sleep(msgDelay);
        }

        // 批次间隔（最后一批不等）
        const nextBatch = batchStart + batchSize;
        if (!stopFlag && nextBatch < recips.length && batchDelay > 0) {
            const batIdx = Math.floor(batchStart / batchSize) + 1;
            const totBat = Math.ceil(recips.length / batchSize);
            enLog(`⏸ 第 ${batIdx}/${totBat} 批完成，等待 ${batchDelay/1000}s…`, '');
            await sleep(batchDelay);
        }
    }

    // 完成
    isSending = false;
    document.getElementById('en-send-btn').style.display = '';
    document.getElementById('en-stop-btn').style.display = 'none';

    const total = okCnt + failCnt;
    enLog(`── 发送完毕：成功 ${okCnt}，失败 ${failCnt} ──`, '');

    let resultMsg, resultCls;
    if (failCnt === 0)        { resultCls='ok';  resultMsg=`🎉 全部 ${total} 封邮件发送成功！`; }
    else if (okCnt === 0)     { resultCls='err'; resultMsg=`❌ 全部 ${total} 封邮件均发送失败，请检查 SMTP 配置。`; }
    else                      { resultCls='ok';  resultMsg=`⚠️ 部分成功：${okCnt} 成功，${failCnt} 失败。`; }
    enShowResult(resultCls, resultMsg);

    // 保存历史
    enSaveHistory(subject, okCnt, failCnt);
}

function enStopSend() {
    stopFlag = true;
    document.getElementById('en-stop-btn').textContent = '正在停止…';
    document.getElementById('en-stop-btn').disabled = true;
    setTimeout(() => {
        document.getElementById('en-stop-btn').textContent = '⏹ 停止发送';
        document.getElementById('en-stop-btn').disabled = false;
    }, 1500);
}

function enShowResult(cls, msg) {
    const el = document.getElementById('en-send-result');
    el.className = 'en-send-result ' + cls;
    el.textContent = msg;
    el.style.display = 'block';
}

async function enSaveHistory(subject, ok, fail) {
    const fd = new FormData();
    fd.append('type',    'email_notify');
    fd.append('action',  'save_history');
    fd.append('subject', subject);
    fd.append('ok',      ok);
    fd.append('fail',    fail);
    try {
        const res  = await fetch(AJAX_URL, { method:'POST', body:fd });
        const data = await res.json();
        if (data.history) enRenderHistory(data.history);
    } catch(e) {}
}

function enRenderHistory(history) {
    const bd = document.getElementById('en-history-bd');
    if (!history || !history.length) { bd.innerHTML = '<div class="en-empty">暂无发送记录</div>'; return; }
    const rows = history.map(h => {
        const ok   = h.ok   || 0;
        const fail = h.fail || 0;
        const tot  = ok + fail;
        let cls, lbl;
        if (fail === 0)    { cls='en-hs-ok';      lbl='全部成功'; }
        else if (ok === 0) { cls='en-hs-fail';     lbl='全部失败'; }
        else               { cls='en-hs-partial';  lbl=`部分成功 ${ok}/${tot}`; }
        return `<div class="en-history-item">
          <div>
            <div class="en-history-subject">${esc(h.subject||'-')}</div>
            <div class="en-history-meta">收件人 ${tot} 人</div>
          </div>
          <span class="en-history-stat ${cls}">${lbl}</span>
          <span class="en-history-time">${esc(h.time||'')}</span>
        </div>`;
    }).join('');
    bd.innerHTML = '<div class="en-history-list">' + rows + '</div>';
}

function esc(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

/* ─── 发送前预览 ─── */
function enPreviewSend() {
    const subject = document.getElementById('en-subject').value || '（无主题）';
    const html    = document.getElementById('en-html-input').value;
    const recips  = enGetSelectedRecipients();

    document.getElementById('enpm-subject').textContent = subject;
    document.getElementById('enpm-to').textContent =
        recips.length ? recips.slice(0,3).map(r=>r.email).join(', ') + (recips.length>3?` 等 ${recips.length} 人`:'') : '（未选择收件人）';

    const iframe = document.getElementById('enpm-iframe');
    const doc = iframe.contentDocument || iframe.contentWindow.document;
    doc.open(); doc.write(html || '<p style="color:#aaa;padding:2rem;">（无内容）</p>'); doc.close();

    document.getElementById('en-preview-modal').style.display = 'flex';
}
function enClosePreview() { document.getElementById('en-preview-modal').style.display = 'none'; }

document.addEventListener('keydown', e => { if (e.key === 'Escape') enClosePreview(); });

/* 初始化 */
document.getElementById('en-toolbar').style.removeProperty('display');

/* ── 将 HTML onclick 需要的函数暴露到全局作用域 ── */
window.enFilterUsers  = enFilterUsers;
window.enToggleAll    = enToggleAll;
window.enSwitchTab    = enSwitchTab;
window.enUpdatePreview= enUpdatePreview;
window.enFmt          = enFmt;
window.enInsert       = enInsert;
window.enLoadTpl      = enLoadTpl;
window.enStartSend    = enStartSend;
window.enStopSend     = enStopSend;
window.enPreviewSend  = enPreviewSend;
window.enClosePreview = enClosePreview;
})();
</script>