<?php
/**
 * 标签页：安全管理
 * 依赖：$activeTab, $user, $db（已在 init.php 中初始化）
 *
 * 功能：
 *   1. 修改密码
 *   2. 当前会话 IP / 设备信息展示
 *   3. 最近登录记录列表，与上次登录差异过大时高亮提醒
 */

require_once __DIR__ . '/../actions/ip_helper.php';

// ── 查询最近 10 条登录记录 ──────────────────────────────────────────
$loginHistory = [];
try {
    $hStmt = $db->prepare("
        SELECT id, ipv4, ipv6, all_ips, ip_source,
               is_proxy, is_local, browser, os, device_type, login_at
        FROM   user_login_history
        WHERE  user_id = ?
        ORDER  BY login_at DESC
        LIMIT  10
    ");
    $hStmt->execute([$user['id']]);
    $loginHistory = $hStmt->fetchAll();
} catch (\Throwable $e) {
    // 表可能尚未创建，不影响其他功能
    $loginHistory = [];
}

// ── 当前会话 IP 信息 ────────────────────────────────────────────────
$currentIpInfo     = detectIpInfo();
$currentDeviceInfo = parseUserAgent($_SERVER['HTTP_USER_AGENT'] ?? '');

// ── 计算最新一条记录与上上条的差异（最新条即本次登录）──────────────
$anomaly = ['level' => 'normal', 'reasons' => []];
if (count($loginHistory) >= 2) {
    $anomaly = compareLoginRecords($loginHistory[0], $loginHistory[1]);
}

// ── 辅助：设备图标 ──────────────────────────────────────────────────
function deviceIcon(string $type): string {
    return match ($type) {
        'mobile'  => '📱',
        'tablet'  => '📟',
        default   => '💻',
    };
}

// ── 辅助：等级对应的样式类 ──────────────────────────────────────────
function levelClass(string $level): string {
    return match ($level) {
        'alert' => 'badge-alert',
        'warn'  => 'badge-warn',
        default => 'badge-normal',
    };
}

function levelLabel(string $level): string {
    return match ($level) {
        'alert' => '⚠️ 高风险',
        'warn'  => '🔔 注意',
        default => '✅ 正常',
    };
}
?>
<div id="security" class="tab-content <?php echo $activeTab === 'security' ? 'active' : ''; ?>">

    <?php /* ══ 异常登录全局横幅 ════════════════════════════════════ */ ?>
    <?php if ($anomaly['level'] !== 'normal' && !empty($anomaly['reasons'])): ?>
    <div class="login-alert-banner <?php echo $anomaly['level'] === 'alert' ? 'banner-alert' : 'banner-warn'; ?>">
        <span class="banner-icon"><?php echo $anomaly['level'] === 'alert' ? '🚨' : '🔔'; ?></span>
        <div>
            <strong>检测到登录异常</strong>
            <ul>
                <?php foreach ($anomaly['reasons'] as $r): ?>
                <li><?php echo htmlspecialchars($r); ?></li>
                <?php endforeach; ?>
            </ul>
            <small>如非本人操作，请立即修改密码并检查账号安全。</small>
        </div>
    </div>
    <?php endif; ?>

    <?php /* ══ 修改密码 ════════════════════════════════════════════ */ ?>
    <div class="profile-section">
        <h2>安全管理</h2>
        <form method="post">
            <input type="hidden" name="action"     value="update_password">
            <input type="hidden" name="active_tab" value="security">
            <div class="form-group">
                <label for="current_password">当前密码</label>
                <input type="password" id="current_password" name="current_password"
                       placeholder="请输入当前密码">
            </div>
            <div class="form-group">
                <label for="new_password">新密码</label>
                <input type="password" id="new_password" name="new_password"
                       minlength="6" placeholder="至少 6 位">
            </div>
            <div class="form-group">
                <label for="confirm_password">确认新密码</label>
                <input type="password" id="confirm_password" name="confirm_password"
                       placeholder="请再次输入新密码">
            </div>
            <button type="submit" class="btn primary">更新密码</button>
        </form>
    </div>

    <?php /* ══ 当前会话信息 ════════════════════════════════════════ */ ?>
    <div class="profile-section">
        <h2>当前登录信息</h2>
        <div class="session-info-grid">

            <?php /* IPv4 */ ?>
            <?php if ($currentIpInfo['ipv4']): ?>
            <div class="session-info-card">
                <span class="sic-label">IPv4 地址</span>
                <span class="sic-value ip-mono"><?php echo htmlspecialchars($currentIpInfo['ipv4']); ?></span>
            </div>
            <?php endif; ?>

            <?php /* IPv6 */ ?>
            <?php if ($currentIpInfo['ipv6']): ?>
            <div class="session-info-card">
                <span class="sic-label">IPv6 地址</span>
                <span class="sic-value ip-mono"><?php echo htmlspecialchars($currentIpInfo['ipv6']); ?></span>
            </div>
            <?php endif; ?>

            <?php /* IP来源 */ ?>
            <div class="session-info-card">
                <span class="sic-label">IP 来源</span>
                <span class="sic-value">
                    <?php echo htmlspecialchars($currentIpInfo['ip_source']); ?>
                    <?php if ($currentIpInfo['is_local']): ?>
                        <span class="badge badge-info">内网</span>
                    <?php endif; ?>
                    <?php if ($currentIpInfo['is_proxy']): ?>
                        <span class="badge badge-warn">检测到代理</span>
                    <?php endif; ?>
                </span>
            </div>

            <?php /* 代理提示 */ ?>
            <?php if ($currentIpInfo['proxy_hint']): ?>
            <div class="session-info-card full-width">
                <span class="sic-label">代理 / CDN 详情</span>
                <span class="sic-value ip-mono small-text"><?php echo htmlspecialchars($currentIpInfo['proxy_hint']); ?></span>
            </div>
            <?php endif; ?>

            <?php /* 多个IP */ ?>
            <?php if (count($currentIpInfo['all_ips']) > 1): ?>
            <div class="session-info-card full-width">
                <span class="sic-label">检测到的所有 IP</span>
                <span class="sic-value ip-mono small-text">
                    <?php echo htmlspecialchars(implode('  →  ', $currentIpInfo['all_ips'])); ?>
                </span>
            </div>
            <?php endif; ?>

            <?php /* 浏览器 */ ?>
            <div class="session-info-card">
                <span class="sic-label">浏览器</span>
                <span class="sic-value"><?php echo htmlspecialchars($currentDeviceInfo['browser']); ?></span>
            </div>

            <?php /* 操作系统 */ ?>
            <div class="session-info-card">
                <span class="sic-label">操作系统</span>
                <span class="sic-value"><?php echo htmlspecialchars($currentDeviceInfo['os']); ?></span>
            </div>

            <?php /* 设备类型 */ ?>
            <div class="session-info-card">
                <span class="sic-label">设备类型</span>
                <span class="sic-value">
                    <?php echo deviceIcon($currentDeviceInfo['device_type']); ?>
                    <?php
                    $dtMap = ['desktop' => '桌面设备', 'mobile' => '手机', 'tablet' => '平板'];
                    echo $dtMap[$currentDeviceInfo['device_type']] ?? $currentDeviceInfo['device_type'];
                    ?>
                </span>
            </div>

        </div><!-- /.session-info-grid -->
    </div>

    <?php /* ══ 登录历史 ════════════════════════════════════════════ */ ?>
    <div class="profile-section">
        <h2>近期登录记录</h2>

        <?php if (empty($loginHistory)): ?>
            <p class="empty-hint">暂无登录记录（请确认已运行 login_history.sql 建表）。</p>
        <?php else: ?>
        <div class="login-history-table-wrap">
        <table class="login-history-table">
            <thead>
                <tr>
                    <th>时间</th>
                    <th>IP 地址</th>
                    <th>设备</th>
                    <th>操作系统</th>
                    <th>浏览器</th>
                    <th>状态</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($loginHistory as $idx => $row):
                // 第 0 条（最新）与第 1 条做对比
                if ($idx === 0 && count($loginHistory) >= 2) {
                    $rowAnomaly = compareLoginRecords($row, $loginHistory[1]);
                } else {
                    $rowAnomaly = ['level' => 'normal', 'reasons' => []];
                }
                $rowClass = $rowAnomaly['level'] !== 'normal' ? 'row-anomaly-' . $rowAnomaly['level'] : '';
                $displayIp = $row['ipv4'] ?? $row['ipv6'] ?? '未知';
            ?>
            <tr class="<?php echo $rowClass; ?>"
                <?php if (!empty($rowAnomaly['reasons'])): ?>
                title="<?php echo htmlspecialchars(implode('；', $rowAnomaly['reasons'])); ?>"
                <?php endif; ?>>
                <td class="col-time">
                    <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($row['login_at']))); ?>
                    <?php if ($idx === 0): ?><span class="badge badge-info">本次</span><?php endif; ?>
                </td>
                <td class="ip-mono">
                    <?php echo htmlspecialchars($displayIp); ?>
                    <?php if ($row['ipv6'] && $row['ipv4']): ?>
                    <br><span class="ipv6-small"><?php echo htmlspecialchars($row['ipv6']); ?></span>
                    <?php endif; ?>
                    <?php if ($row['is_local']): ?>
                        <span class="badge badge-info" title="内网/本地">内网</span>
                    <?php endif; ?>
                    <?php if ($row['is_proxy']): ?>
                        <span class="badge badge-warn" title="检测到代理特征">代理</span>
                    <?php endif; ?>
                </td>
                <td><?php echo deviceIcon($row['device_type'] ?? 'desktop'); ?>
                    <?php
                    $dtMap = ['desktop' => '桌面', 'mobile' => '手机', 'tablet' => '平板'];
                    echo $dtMap[$row['device_type'] ?? 'desktop'] ?? ($row['device_type'] ?? '');
                    ?>
                </td>
                <td><?php echo htmlspecialchars($row['os'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['browser'] ?? ''); ?></td>
                <td>
                    <?php if ($rowAnomaly['level'] !== 'normal'): ?>
                        <span class="badge <?php echo levelClass($rowAnomaly['level']); ?>"
                              title="<?php echo htmlspecialchars(implode('；', $rowAnomaly['reasons'])); ?>">
                            <?php echo levelLabel($rowAnomaly['level']); ?>
                        </span>
                    <?php else: ?>
                        <span class="badge badge-normal">✅ 正常</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div><!-- /.login-history-table-wrap -->

        <!-- 移动端卡片视图 -->
        <div class="login-history-cards">
        <?php foreach ($loginHistory as $idx => $row):
            if ($idx === 0 && count($loginHistory) >= 2) {
                $rowAnomaly = compareLoginRecords($row, $loginHistory[1]);
            } else {
                $rowAnomaly = ['level' => 'normal', 'reasons' => []];
            }
            $displayIp = $row['ipv4'] ?? $row['ipv6'] ?? '未知';
        ?>
        <div class="lh-card <?php echo $rowAnomaly['level'] !== 'normal' ? 'lh-card-' . $rowAnomaly['level'] : ''; ?>">
            <div class="lh-card-top">
                <span class="lh-card-time">
                    <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($row['login_at']))); ?>
                    <?php if ($idx === 0): ?><span class="badge badge-info">本次</span><?php endif; ?>
                </span>
                <?php if ($rowAnomaly['level'] !== 'normal'): ?>
                    <span class="badge <?php echo levelClass($rowAnomaly['level']); ?>">
                        <?php echo levelLabel($rowAnomaly['level']); ?>
                    </span>
                <?php else: ?>
                    <span class="badge badge-normal">✅ 正常</span>
                <?php endif; ?>
            </div>
            <div class="lh-card-row">
                <span class="lh-label">IP</span>
                <span class="ip-mono lh-val">
                    <?php echo htmlspecialchars($displayIp); ?>
                    <?php if ($row['is_local']): ?><span class="badge badge-info">内网</span><?php endif; ?>
                    <?php if ($row['is_proxy']): ?><span class="badge badge-warn">代理</span><?php endif; ?>
                </span>
            </div>
            <div class="lh-card-row">
                <span class="lh-label">设备</span>
                <span class="lh-val">
                    <?php echo deviceIcon($row['device_type'] ?? 'desktop'); ?>
                    <?php
                    $dtMap = ['desktop' => '桌面', 'mobile' => '手机', 'tablet' => '平板'];
                    echo $dtMap[$row['device_type'] ?? 'desktop'] ?? ($row['device_type'] ?? '');
                    ?> · <?php echo htmlspecialchars($row['os'] ?? ''); ?>
                </span>
            </div>
            <div class="lh-card-row">
                <span class="lh-label">浏览器</span>
                <span class="lh-val"><?php echo htmlspecialchars($row['browser'] ?? ''); ?></span>
            </div>
        </div>
        <?php endforeach; ?>
        </div><!-- /.login-history-cards -->

        <?php endif; ?>
    </div>

</div><!-- /#security -->

<style>
/* ── 移动端登录历史：表格隐藏，卡片显示 ─────────────────────── */
.login-history-cards { display: none; }
@media (max-width: 700px) {
    .login-history-table-wrap { display: none; }
    .login-history-cards {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: 4px;
    }
}
.lh-card {
    border: 1px solid var(--border-color, rgba(0,0,0,.09));
    border-radius: 12px;
    padding: 12px 14px;
    background: var(--card-bg, #fff);
    transition: box-shadow .2s;
}
.lh-card:hover { box-shadow: 0 3px 14px rgba(108,93,251,.1); }
.lh-card-alert { border-color: #f5a0aa; background: #fff8f8; }
.lh-card-warn  { border-color: #f5dfa0; background: #fffbf0; }
.dark-mode .lh-card { background: var(--dark-card, #1e1e2e); border-color: rgba(176,160,255,.15); }
.dark-mode .lh-card-alert { border-color: #7a2030; background: #2a1018; }
.dark-mode .lh-card-warn  { border-color: #7a6020; background: #262010; }
.lh-card-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    flex-wrap: wrap;
    gap: 6px;
}
.lh-card-time { font-size: .82rem; font-weight: 600; color: var(--text-secondary, #555); }
.lh-card-row {
    display: flex;
    align-items: baseline;
    gap: 8px;
    padding: 4px 0;
    border-top: 1px solid rgba(0,0,0,.05);
    font-size: .84rem;
}
.dark-mode .lh-card-row { border-top-color: rgba(255,255,255,.06); }
.lh-label {
    flex-shrink: 0;
    width: 44px;
    color: var(--text-muted, #999);
    font-size: .77rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .03em;
}
.lh-val { color: var(--text, #222); word-break: break-all; }
.dark-mode .lh-val { color: var(--dark-text, #e8e4ff); }

/* ── 当前登录信息网格移动端优化 ──────────────────────────────── */
@media (max-width: 600px) {
    .session-info-grid {
        grid-template-columns: 1fr !important;
    }
    .session-info-card.full-width { grid-column: 1 !important; }
}

/* ── 登录异常横幅移动端 ───────────────────────────────────────── */
@media (max-width: 600px) {
    .login-alert-banner { font-size: .84rem; padding: .9rem 1rem; }
}
</style>