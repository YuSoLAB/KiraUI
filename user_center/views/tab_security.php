<?php
/**
 * 标签页：安全管理
 * 依赖：$activeTab, $user, $db（已在 init.php 中初始化）
 *
 * 功能：
 *   1. 修改密码
 *   2. 绑定 / 更换手机号（短信验证码）
 *   3. 当前会话 IP / 设备信息展示
 *   4. 最近登录记录列表，与上次登录差异过大时高亮提醒
 */

require_once __DIR__ . '/../actions/ip_helper.php';

// ══════════════════════════════════════════════════════════════════════
//  User-Agent Client Hints（UA-CH）— 向浏览器请求真实高熵设备信息
//
//  背景：Chrome 89+ / Edge 93+ 实施了 UA Reduction 策略，传统 UA 字符串
//  已被冻结（Win 版本永远报 10.0、Chrome 小版本号全填 0）。
//  UA-CH 是 W3C 标准替代方案，服务器需主动声明所需字段。
//
//  Accept-CH      ：告知浏览器"本站需要哪些高熵字段"（持久化到域名级别）
//  Critical-CH    ：本次页面响应必须携带的字段（浏览器会自动重发请求）
//  Permissions-Policy：可选，明确策略（防止子框架滥用）
//
//  注意：UA-CH 仅在 HTTPS 下有效；HTTP 环境浏览器不会发送 Sec-CH-* 头。
// ══════════════════════════════════════════════════════════════════════
if (!headers_sent()) {
    // 声明需要的高熵字段（首次加载后浏览器会在后续请求中携带）
    header('Accept-CH: Sec-CH-UA, Sec-CH-UA-Full-Version-List, Sec-CH-UA-Mobile, '
         . 'Sec-CH-UA-Platform, Sec-CH-UA-Platform-Version, Sec-CH-UA-Model, Sec-CH-UA-Arch');

    // 标记为关键字段：浏览器若尚未携带，会在同一页面自动重发一次请求
    header('Critical-CH: Sec-CH-UA-Full-Version-List, Sec-CH-UA-Platform-Version');

    // Vary 头：确保 CDN / 缓存按 UA-CH 字段分别缓存，避免给不同设备返回同一份记录
    header('Vary: Sec-CH-UA, Sec-CH-UA-Mobile, Sec-CH-UA-Platform');
}

// ── 查询当前绑定手机号和邮箱 ──────────────────────────────────────
$phoneStmt = $db->prepare("SELECT phone, phone_verified, email, email_verified FROM users WHERE id = ?");
$phoneStmt->execute([$user['id']]);
$phoneRow      = $phoneStmt->fetch(PDO::FETCH_ASSOC);
$boundPhone    = (!empty($phoneRow['phone'])) ? $phoneRow['phone'] : null;
$phoneVerified = (bool)($phoneRow['phone_verified'] ?? false);
$boundEmail    = (!empty($phoneRow['email'])) ? $phoneRow['email'] : null;
$emailVerified = (bool)($phoneRow['email_verified'] ?? false);

// 工具：隐藏中间四位
function maskPhone(string $p): string {
    return substr($p, 0, 3) . '****' . substr($p, 7);
}

// 工具：遮盖邮箱本地部分
function maskEmailDisplay(string $email): string {
    $parts = explode("@", $email, 2);
    $local  = $parts[0];
    $domain = $parts[1] ?? "";
    $len = strlen($local);
    if ($len <= 2) return str_repeat("*", $len) . "@" . $domain;
    return substr($local, 0, 1) . str_repeat("*", min($len - 2, 4)) . substr($local, -1) . "@" . $domain;
}

// ── 查询最近 10 条登录记录 ──────────────────────────────────────────
$loginHistory    = [];
$historyHasGeo   = false;   // 标记历史表是否含地理位置字段（isp / country / region）
try {
    // 尝试含扩展字段（isp / country / region）的查询；若列不存在则回退到基础查询
    $hStmt = $db->prepare("
        SELECT id, ipv4, ipv6, all_ips, ip_source,
               is_proxy, is_local, browser, os, device_type,
               isp, country, region, login_at
        FROM   user_login_history
        WHERE  user_id = ?
        ORDER  BY login_at DESC
        LIMIT  10
    ");
    $hStmt->execute([$user['id']]);
    $loginHistory  = $hStmt->fetchAll();
    $historyHasGeo = true;
} catch (\Throwable $e) {
    // 扩展字段不存在，降级到基础查询
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
    } catch (\Throwable $e2) {
        $loginHistory = [];
    }
}

// ── 当前会话 IP 信息 ────────────────────────────────────────────────
$currentIpInfo = detectIpInfo();

// IP 声誉增强（VPN / 数据中心检测）
// enrichIpReputation 会发起对 ip-api.com 的 HTTP 请求（有本地缓存，默认 1 小时）
// 如不需要 VPN 检测，可注释掉下面这行
$currentIpInfo = enrichIpReputation($currentIpInfo);
// 传入 $_SERVER 使 parseUserAgent 能同时读取 Sec-CH-UA-* 头（UA-CH 双轨解析）
$currentDeviceInfo = parseUserAgent($_SERVER['HTTP_USER_AGENT'] ?? '', $_SERVER);

// ── 计算最新一条记录与上上条的差异 ──────────────────────────────────
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

    <?php /* ══ 绑定手机号 ══════════════════════════════════════════ */ ?>
    <div class="profile-section" id="phone-section">
        <h2>手机号绑定</h2>

        <?php /* 已绑定状态展示 */ ?>
        <div class="phone-current-row">
            <span class="phone-label">当前绑定手机</span>
            <?php if ($boundPhone): ?>
                <span class="phone-value bound" id="phone-display">
                    <span class="phone-icon">📱</span>
                    <?php echo htmlspecialchars(maskPhone($boundPhone)); ?>
                    <?php if ($phoneVerified): ?>
                        <span class="badge badge-normal" style="margin-left:6px">已验证</span>
                    <?php else: ?>
                        <span class="badge badge-warn" style="margin-left:6px">待验证</span>
                    <?php endif; ?>
                </span>
                <button class="btn secondary btn-sm" id="phone-change-btn" onclick="showPhoneForm(true)">更换手机号</button>
            <?php else: ?>
                <span class="phone-value unbound" id="phone-display">
                    <span class="phone-icon">📱</span>
                    <span class="text-muted">未绑定</span>
                </span>
                <button class="btn primary btn-sm" id="phone-change-btn" onclick="showPhoneForm(true)">立即绑定</button>
            <?php endif; ?>
        </div>

        <?php /* 绑定 / 更换表单（默认隐藏） */ ?>
        <div id="phone-bind-form" class="phone-bind-form" style="display:none">
            <div id="phone-msg" class="phone-feedback" style="display:none"></div>

            <div class="form-group phone-input-row">
                <label for="bind-phone-input">手机号</label>
                <div class="phone-input-wrap">
                    <span class="phone-prefix">+86</span>
                    <input type="tel" id="bind-phone-input" maxlength="11"
                           placeholder="请输入手机号" autocomplete="tel">
                    <button class="btn secondary btn-sm" id="send-code-btn" onclick="requestSendCode()">发送验证码</button>
                </div>
            </div>

            <?php /* 图形验证码内联展开区（点击"发送验证码"后出现） */ ?>
            <div id="phone-captcha-box" style="display:none">
                <div class="form-group captcha-bind-group">
                    <label>图形验证码
                        <span class="captcha-bind-hint">输入图中字符后点击确认发送</span>
                    </label>
                    <div class="captcha-bind-row">
                        <input type="text" id="bind-captcha-input" maxlength="5"
                               autocomplete="off" placeholder="不区分大小写"
                               class="captcha-bind-input">
                        <img id="bindCaptchaImg"
                             src="../captcha.php?t=<?php echo time(); ?>"
                             alt="图形验证码"
                             class="captcha-bind-img"
                             title="看不清？点击刷新"
                             onclick="refreshBindCaptcha()">
                        <button type="button" class="captcha-refresh-sm"
                                onclick="refreshBindCaptcha()" title="刷新验证码">↻</button>
                    </div>
                </div>
                <div class="captcha-bind-actions">
                    <button class="btn ghost btn-sm" onclick="cancelSendCode()">取消</button>
                    <button class="btn btn-primary btn-sm" id="confirm-send-code-btn"
                            onclick="confirmSendCode()">确认并发送短信</button>
                </div>
            </div>

            <div class="form-group" id="code-row" style="display:none">
                <label for="bind-code-input">短信验证码</label>
                <div class="phone-input-wrap">
                    <input type="text" id="bind-code-input" maxlength="6"
                           placeholder="请输入 6 位验证码" autocomplete="one-time-code"
                           inputmode="numeric" pattern="\d{6}">
                    <button class="btn primary btn-sm" onclick="verifyBind()">验证并绑定</button>
                </div>
            </div>

            <div style="margin-top:10px">
                <button class="btn ghost btn-sm" onclick="hidePhoneForm()">取消</button>
            </div>
        </div>
    </div>


    <?php /* ══ 绑定邮箱 ══════════════════════════════════════════════ */ ?>
    <div class="profile-section" id="email-section">
        <h2>邮箱绑定</h2>
        <div class="phone-current-row">
            <span class="phone-label">当前绑定邮箱</span>
            <?php if ($boundEmail): ?>
                <span class="phone-value bound" id="email-display">
                    <span class="phone-icon">📧</span>
                    <?php echo htmlspecialchars(maskEmailDisplay($boundEmail)); ?>
                    <?php if ($emailVerified): ?>
                        <span class="badge badge-normal" style="margin-left:6px">已验证</span>
                    <?php else: ?>
                        <span class="badge badge-warn" style="margin-left:6px">待验证</span>
                    <?php endif; ?>
                </span>
                <button class="btn secondary btn-sm" id="email-change-btn" onclick="showEmailForm(true)">更换邮箱</button>
            <?php else: ?>
                <span class="phone-value unbound" id="email-display">
                    <span class="phone-icon">📧</span>
                    <span class="text-muted">未绑定</span>
                </span>
                <button class="btn primary btn-sm" id="email-change-btn" onclick="showEmailForm(true)">立即绑定</button>
            <?php endif; ?>
        </div>
        <div id="email-bind-form" class="phone-bind-form" style="display:none">
            <div id="email-msg" class="phone-feedback" style="display:none"></div>
            <div class="form-group phone-input-row">
                <label for="bind-email-input">邮箱地址</label>
                <div class="phone-input-wrap">
                    <input type="email" id="bind-email-input" placeholder="请输入新邮箱地址" autocomplete="email">
                    <button class="btn secondary btn-sm" id="send-email-code-btn" onclick="sendEmailCode()">发送验证码</button>
                </div>
            </div>
            <div class="form-group" id="email-code-row" style="display:none">
                <label for="bind-email-code-input">验证码</label>
                <div class="phone-input-wrap">
                    <input type="text" id="bind-email-code-input" maxlength="6" placeholder="请输入 6 位邮箱验证码" autocomplete="one-time-code" inputmode="numeric" pattern="\d{6}">
                    <button class="btn primary btn-sm" onclick="verifyEmailBind()">验证并绑定</button>
                </div>
            </div>
            <div style="margin-top:10px">
                <button class="btn ghost btn-sm" onclick="hideEmailForm()">取消</button>
            </div>
        </div>
    </div>

    <?php /* ══ 当前会话信息 ════════════════════════════════════════ */ ?>
    <div class="profile-section">
        <h2>当前登录信息</h2>
        <div class="cur-session-card">

            <?php /* ── IP 地址 ── */ ?>
            <div class="cs-row">
                <span class="cs-label">IP 地址</span>
                <span class="cs-value ip-mono">
                    <?php echo htmlspecialchars($currentIpInfo['display_ip']); ?>
                    <?php if ($currentIpInfo['ipv6'] && $currentIpInfo['ipv4']): ?>
                        <span class="cs-secondary"><?php echo htmlspecialchars($currentIpInfo['ipv6']); ?></span>
                    <?php endif; ?>
                    <?php if ($currentIpInfo['is_local']): ?>
                        <span class="badge badge-info">内网</span>
                    <?php endif; ?>
                    <?php if ($currentIpInfo['is_vpn'] ?? false): ?>
                        <span class="badge badge-alert" title="ip-api.com 声誉库标记为 VPN / 匿名代理出口">🔒 VPN</span>
                    <?php elseif ($currentIpInfo['is_hosting'] ?? false): ?>
                        <span class="badge badge-warn" title="数据中心 / 托管机房 IP">🏢 数据中心</span>
                    <?php elseif ($currentIpInfo['is_proxy']): ?>
                        <span class="badge badge-warn">代理</span>
                    <?php endif; ?>
                </span>
            </div>

            <?php /* ── 归属地 & 运营商（API 有数据时才显示）── */ ?>
            <?php
            $hasLoc = !empty($currentIpInfo['country']) || !empty($currentIpInfo['region']);
            $hasIsp = !empty($currentIpInfo['isp']);
            ?>
            <?php if ($hasLoc || $hasIsp): ?>
            <div class="cs-row">
                <span class="cs-label">归属地</span>
                <span class="cs-value">
                    <?php if ($hasLoc):
                        $loc = array_filter([$currentIpInfo['country'] ?? '', $currentIpInfo['region'] ?? '']);
                        echo htmlspecialchars(implode(' · ', $loc));
                    endif; ?>
                    <?php if ($hasIsp): ?>
                        <span class="cs-secondary">
                            <?php echo htmlspecialchars($currentIpInfo['isp']); ?>
                            <?php if (!empty($currentIpInfo['asn'])): ?>
                                · <span class="cs-asn"><?php echo htmlspecialchars($currentIpInfo['asn']); ?></span>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </span>
            </div>
            <?php endif; ?>

            <?php /* ── 设备类型 ── */ ?>
            <div class="cs-row">
                <span class="cs-label">设备</span>
                <span class="cs-value">
                    <span id="js-device-icon"><?php echo deviceIcon($currentDeviceInfo['device_type']); ?></span>
                    <?php $dtMap = ['desktop' => '桌面设备', 'mobile' => '手机', 'tablet' => '平板']; ?>
                    <span id="js-device-type"><?php echo $dtMap[$currentDeviceInfo['device_type']] ?? $currentDeviceInfo['device_type']; ?></span>
                </span>
            </div>

            <?php /* ── 操作系统 ── */ ?>
            <div class="cs-row">
                <span class="cs-label">操作系统</span>
                <span class="cs-value">
                    <span id="js-os"><?php echo htmlspecialchars($currentDeviceInfo['os']); ?></span>
                </span>
            </div>

            <?php /* ── 浏览器 ── */ ?>
            <div class="cs-row">
                <span class="cs-label">浏览器</span>
                <span class="cs-value">
                    <span id="js-browser"><?php echo htmlspecialchars($currentDeviceInfo['browser']); ?></span>
                    <?php
                    $srcLabel = match($currentDeviceInfo['ua_source'] ?? 'ua-string') {
                        'ua-ch'         => ['UA-CH ✓',    'badge-normal', '来自 UA Client Hints 高熵值，信息准确'],
                        'ua-ch-partial' => ['UA-CH (低熵)', 'badge-info',  '来自 UA-CH 低熵字段，版本号可能缺失'],
                        default         => ['UA 字符串',   'badge-warn',  'UA 字符串已被冻结/模糊化，信息可能不准确'],
                    };
                    ?>
                    <span class="badge <?php echo $srcLabel[1]; ?>"
                          title="<?php echo $srcLabel[2]; ?>"
                          style="margin-left:4px;font-size:.72rem">
                        <?php echo $srcLabel[0]; ?>
                    </span>
                </span>
            </div>

            <?php /* ── 代理详情（有才显示）── */ ?>
            <?php if ($currentIpInfo['proxy_hint']): ?>
            <div class="cs-row">
                <span class="cs-label">代理详情</span>
                <span class="cs-value ip-mono cs-proxy-hint">
                    <?php echo htmlspecialchars($currentIpInfo['proxy_hint']); ?>
                </span>
            </div>
            <?php endif; ?>

            <?php /* ── 全部 IP（经过多层代理时）── */ ?>
            <?php if (count($currentIpInfo['all_ips']) > 1): ?>
            <div class="cs-row">
                <span class="cs-label">IP 链路</span>
                <span class="cs-value ip-mono cs-proxy-hint">
                    <?php echo htmlspecialchars(implode('  →  ', $currentIpInfo['all_ips'])); ?>
                </span>
            </div>
            <?php endif; ?>

            <?php /* ── 数据来源说明（rate-limited / api-fail 时提示）── */ ?>
            <?php if (in_array($currentIpInfo['rep_source'] ?? '', ['rate-limited', 'api-fail'], true)): ?>
            <div class="cs-row">
                <span class="cs-label">IP 详情</span>
                <span class="cs-value" style="color:var(--text-muted,#aaa);font-size:.82rem">
                    <?php if (($currentIpInfo['rep_source'] ?? '') === 'rate-limited'): ?>
                        ⚡ ip-api.com 查询次数已达上限，归属地信息暂不可用（24 小时内将自动恢复）
                    <?php else: ?>
                        ⚠️ IP 归属查询失败（网络超时或服务不可用）
                    <?php endif; ?>
                </span>
            </div>
            <?php endif; ?>

        </div><!-- /.cur-session-card -->
    </div>

    <?php /* ══ navigator.userAgentData JS 增强层 ═════════════════════
     * 作用：当服务端拿不到 UA-CH 头（首次加载、服务端未收到 Critical-CH 重发等）
     * 时，用浏览器端 JS API 异步获取高熵值并实时更新显示。
     * 同时也处理 Firefox / Safari 不支持 UA-CH 的情况（静默降级）。
     */ ?>
    <script>
    (function () {
        // navigator.userAgentData 仅 Chromium 系支持（Chrome/Edge 90+）
        if (!navigator.userAgentData || !navigator.userAgentData.getHighEntropyValues) {
            return;
        }

        const serverSource = <?php echo json_encode($currentDeviceInfo['ua_source'] ?? 'ua-string'); ?>;
        if (serverSource === 'ua-ch') {
            return; // 服务端已拿到高熵 UA-CH，直接退出
        }

        navigator.userAgentData.getHighEntropyValues([
            'brands', 'fullVersionList', 'platform',
            'platformVersion', 'mobile', 'model', 'architecture',
        ]).then(function (ua) {
            // ── 解析浏览器品牌 ─────────────────────────────────
            const IGNORE = /^not[.\-_a]?[a-z]?[.\/\-_]?brand/i;
            const PRIORITY = {
                'Microsoft Edge': 10, 'Opera': 9, 'Brave': 8,
                'Vivaldi': 7, 'Yandex': 7, 'Samsung Internet': 5,
                'Google Chrome': 4, 'Chromium': 1,
            };
            const ALIAS = {
                'Microsoft Edge': 'Edge', 'Google Chrome': 'Chrome',
                'Samsung Internet': 'Samsung Browser',
            };

            const brands = ua.fullVersionList || ua.brands || [];
            const filtered = brands
                .filter(b => !IGNORE.test(b.brand))
                .map(b => ({
                    name: ALIAS[b.brand] || b.brand,
                    ver:  b.version ? b.version.split('.')[0] : '',
                    pri:  PRIORITY[b.brand] || 3,
                }))
                .sort((a, b) => b.pri - a.pri);

            if (filtered.length > 0) {
                const best = filtered[0];
                const label = best.ver ? `${best.name} ${best.ver}` : best.name;
                const el = document.getElementById('js-browser');
                if (el) el.textContent = label;
            }

            // ── 解析操作系统 ───────────────────────────────────
            const platform = ua.platform || '';
            const platVer  = ua.platformVersion || '';
            let osText = '';

            if (platform === 'Windows') {
                const major = parseInt((platVer || '0').split('.')[0], 10);
                if (major >= 13)      osText = 'Windows 11';
                else if (major >= 10) osText = 'Windows 10';
                else if (major >= 6)  osText = 'Windows 8/8.1';
                else                  osText = 'Windows';
            } else if (platform === 'macOS') {
                osText = platVer ? `macOS ${platVer}` : 'macOS';
            } else if (platform === 'Android') {
                osText = platVer ? `Android ${platVer}` : 'Android';
            } else if (platform === 'iOS') {
                osText = platVer ? `iOS ${platVer.replace(/_/g,'.')}` : 'iOS';
            } else if (platform === 'ChromeOS') {
                osText = platVer ? `ChromeOS ${platVer}` : 'ChromeOS';
            } else if (platform) {
                osText = platVer && platVer !== '0.0.0' ? `${platform} ${platVer}` : platform;
            }

            if (osText) {
                const el = document.getElementById('js-os');
                if (el) el.textContent = osText;
            }

            // ── 设备类型 ────────────────────────────────────────
            if (typeof ua.mobile !== 'undefined') {
                const isMobile = ua.mobile;
                const el   = document.getElementById('js-device-type');
                const icon = document.getElementById('js-device-icon');
                if (el)   el.textContent   = isMobile ? '手机' : '桌面设备';
                if (icon) icon.textContent = isMobile ? '📱' : '💻';
            }

        }).catch(function () {
            // getHighEntropyValues 被拒绝（权限策略等），静默降级
        });
    })();
    </script>

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
                    <?php if ($historyHasGeo): ?><th>归属地</th><?php endif; ?>
                    <th>设备</th>
                    <th>操作系统</th>
                    <th>浏览器</th>
                    <th>状态</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($loginHistory as $idx => $row):
                if ($idx === 0 && count($loginHistory) >= 2) {
                    $rowAnomaly = compareLoginRecords($row, $loginHistory[1]);
                } else {
                    $rowAnomaly = ['level' => 'normal', 'reasons' => []];
                }
                $rowClass  = $rowAnomaly['level'] !== 'normal' ? 'row-anomaly-' . $rowAnomaly['level'] : '';
                $displayIp = $row['ipv4'] ?? $row['ipv6'] ?? '未知';
                $rowGeoStr = '';
                if ($historyHasGeo) {
                    $parts = array_filter([
                        $row['country'] ?? '',
                        $row['region']  ?? '',
                    ]);
                    $rowGeoStr = implode(' · ', $parts);
                }
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
                <?php if ($historyHasGeo): ?>
                <td class="col-geo">
                    <?php if ($rowGeoStr !== ''): ?>
                        <?php echo htmlspecialchars($rowGeoStr); ?>
                        <?php if (!empty($row['isp'])): ?>
                            <br><span class="geo-isp"><?php echo htmlspecialchars($row['isp']); ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
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
            $cardGeoStr = '';
            if ($historyHasGeo) {
                $parts = array_filter([$row['country'] ?? '', $row['region'] ?? '']);
                $cardGeoStr = implode(' · ', $parts);
            }
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
            <?php if ($historyHasGeo && ($cardGeoStr !== '' || !empty($row['isp']))): ?>
            <div class="lh-card-row">
                <span class="lh-label">归属</span>
                <span class="lh-val">
                    <?php echo htmlspecialchars($cardGeoStr !== '' ? $cardGeoStr : ($row['isp'] ?? '')); ?>
                    <?php if ($cardGeoStr !== '' && !empty($row['isp'])): ?>
                        <span class="geo-isp"><?php echo htmlspecialchars($row['isp']); ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <?php endif; ?>
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

<?php /* ══ 手机绑定交互脚本 ════════════════════════════════════════ */ ?>
<script>
(function () {
    'use strict';

    /* ── 倒计时句柄 ────────────────────────────────────────────── */
    let countdown = null;

    /* ── 显示 / 隐藏绑定表单 ───────────────────────────────────── */
    window.showPhoneForm = function (focus) {
        document.getElementById('phone-bind-form').style.display = 'block';
        document.getElementById('phone-change-btn').style.display = 'none';
        if (focus) document.getElementById('bind-phone-input').focus();
    };

    window.hidePhoneForm = function () {
        document.getElementById('phone-bind-form').style.display  = 'none';
        document.getElementById('phone-change-btn').style.display = '';
        document.getElementById('code-row').style.display         = 'none';
        document.getElementById('phone-captcha-box').style.display = 'none';
        document.getElementById('bind-phone-input').value         = '';
        document.getElementById('bind-code-input').value          = '';
        document.getElementById('bind-captcha-input').value       = '';
        showMsg('', '');
        clearCountdown();
    };

    /* ── 消息提示 ──────────────────────────────────────────────── */
    function showMsg(text, type) {
        const el = document.getElementById('phone-msg');
        if (!text) { el.style.display = 'none'; return; }
        el.textContent   = text;
        el.className     = 'phone-feedback phone-feedback-' + (type || 'info');
        el.style.display = 'block';
    }

    /* ── 倒计时 ────────────────────────────────────────────────── */
    function startCountdown(btn, seconds) {
        let s = seconds;
        btn.disabled = true;
        btn.textContent = s + ' 秒后重发';
        clearCountdown();
        countdown = setInterval(function () {
            s--;
            if (s <= 0) {
                clearCountdown();
                btn.disabled    = false;
                btn.textContent = '重新发送';
            } else {
                btn.textContent = s + ' 秒后重发';
            }
        }, 1000);
    }

    function clearCountdown() {
        if (countdown) { clearInterval(countdown); countdown = null; }
    }

    /* ── 图形验证码辅助（用户中心换绑手机） ────────────────── */
    window.refreshBindCaptcha = function () {
        const img = document.getElementById('bindCaptchaImg');
        if (img) {
            img.src = '../captcha.php?t=' + Date.now();
            document.getElementById('bind-captcha-input').value = '';
        }
    };

    window.cancelSendCode = function () {
        document.getElementById('phone-captcha-box').style.display = 'none';
        document.getElementById('bind-captcha-input').value = '';
        showMsg('', '');
    };

    /* ── Step 1：点击"发送验证码" → 校验手机号格式 → 展开图形验证码 */
    window.requestSendCode = function () {
        const phone = document.getElementById('bind-phone-input').value.trim();
        if (!/^1[3-9]\d{9}$/.test(phone)) {
            showMsg('请输入正确的 11 位手机号', 'error');
            return;
        }
        showMsg('', '');
        refreshBindCaptcha();
        document.getElementById('phone-captcha-box').style.display = 'block';
        document.getElementById('bind-captcha-input').focus();
    };

    /* ── Step 2：确认图形验证码 → 真正发短信 ──────────────── */
    window.confirmSendCode = function () {
        const phone   = document.getElementById('bind-phone-input').value.trim();
        const captcha = document.getElementById('bind-captcha-input').value.trim();
        if (!captcha) {
            showMsg('请输入图形验证码', 'error');
            document.getElementById('bind-captcha-input').focus();
            return;
        }
        const btn     = document.getElementById('send-code-btn');
        const confBtn = document.getElementById('confirm-send-code-btn');
        confBtn.disabled    = true;
        confBtn.textContent = '发送中…';
        showMsg('', '');

        fetch('index.php', {
            method:  'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body:    'action=send_phone_code'
                   + '&phone='         + encodeURIComponent(phone)
                   + '&captcha_input=' + encodeURIComponent(captcha),
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                document.getElementById('phone-captcha-box').style.display = 'none';
                document.getElementById('bind-captcha-input').value = '';
                showMsg(data.msg, 'success');
                document.getElementById('code-row').style.display = 'block';
                document.getElementById('bind-code-input').focus();
                startCountdown(btn, 60);
            } else {
                // 图形验证码错误时自动刷新
                refreshBindCaptcha();
                showMsg(data.msg, 'error');
            }
        })
        .catch(() => {
            showMsg('网络异常，请稍后重试', 'error');
        })
        .finally(() => {
            confBtn.disabled    = false;
            confBtn.textContent = '确认发送短信';
        });
    };

    /* ── 保留旧名兼容（不再直接使用） ─────────────────────── */
    window.sendCode = window.requestSendCode;

    /* ── 验证并绑定 ────────────────────────────────────────────── */
    window.verifyBind = function () {
        const phone = document.getElementById('bind-phone-input').value.trim();
        const code  = document.getElementById('bind-code-input').value.trim();
        if (!/^\d{6}$/.test(code)) {
            showMsg('请输入 6 位数字验证码', 'error');
            return;
        }

        fetch('index.php', {
            method:  'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body:    'action=verify_phone_bind&phone=' + encodeURIComponent(phone)
                   + '&code='  + encodeURIComponent(code),
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                showMsg(data.msg, 'success');
                clearCountdown();
                /* 更新页面上的手机号展示，无需刷新 */
                const display = document.getElementById('phone-display');
                display.className  = 'phone-value bound';
                display.innerHTML  = '<span class="phone-icon">📱</span>'
                    + data.masked
                    + ' <span class="badge badge-normal" style="margin-left:6px">已验证</span>';

                const changeBtn = document.getElementById('phone-change-btn');
                changeBtn.textContent = '更换手机号';
                changeBtn.className   = 'btn secondary btn-sm';

                setTimeout(hidePhoneForm, 1500);
            } else {
                showMsg(data.msg, 'error');
            }
        })
        .catch(() => showMsg('网络异常，请稍后重试', 'error'));
    };

    /* ── 回车快捷键：手机号输入框回车触发发送 ──────────────────── */
    document.getElementById('bind-phone-input').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); sendCode(); }
    });
    document.getElementById('bind-code-input').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); verifyBind(); }
    });
}());
</script>

<?php /* ══ 邮箱绑定交互脚本 ════════════════════════════════════════ */ ?>
<script>
(function () {
    'use strict';
    let emailCountdown = null;

    window.showEmailForm = function (focus) {
        document.getElementById('email-bind-form').style.display = 'block';
        document.getElementById('email-change-btn').style.display = 'none';
        if (focus) document.getElementById('bind-email-input').focus();
    };

    window.hideEmailForm = function () {
        document.getElementById('email-bind-form').style.display   = 'none';
        document.getElementById('email-change-btn').style.display  = '';
        document.getElementById('email-code-row').style.display    = 'none';
        document.getElementById('bind-email-input').value          = '';
        document.getElementById('bind-email-code-input').value     = '';
        showEmailMsg('', '');
        clearEmailCountdown();
    };

    function showEmailMsg(text, type) {
        const el = document.getElementById('email-msg');
        if (!text) { el.style.display = 'none'; return; }
        el.textContent   = text;
        el.className     = 'phone-feedback phone-feedback-' + (type || 'info');
        el.style.display = 'block';
    }

    function startEmailCountdown(btn, seconds) {
        let s = seconds;
        btn.disabled = true;
        btn.textContent = s + ' 秒后重发';
        clearEmailCountdown();
        emailCountdown = setInterval(function () {
            s--;
            if (s <= 0) {
                clearEmailCountdown();
                btn.disabled    = false;
                btn.textContent = '重新发送';
            } else {
                btn.textContent = s + ' 秒后重发';
            }
        }, 1000);
    }

    function clearEmailCountdown() {
        if (emailCountdown) { clearInterval(emailCountdown); emailCountdown = null; }
    }

    window.sendEmailCode = function () {
        const email = document.getElementById('bind-email-input').value.trim();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showEmailMsg('请输入有效的邮箱地址', 'error');
            return;
        }
        const btn = document.getElementById('send-email-code-btn');
        btn.disabled    = true;
        btn.textContent = '发送中…';
        showEmailMsg('', '');

        fetch('actions/bind_email.php', {
            method:  'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body:    'sub_action=send_code&email=' + encodeURIComponent(email),
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                showEmailMsg(data.msg, 'success');
                document.getElementById('email-code-row').style.display = 'block';
                document.getElementById('bind-email-code-input').focus();
                startEmailCountdown(btn, 60);
            } else {
                showEmailMsg(data.msg, 'error');
                btn.disabled    = false;
                btn.textContent = '发送验证码';
            }
        })
        .catch(() => {
            showEmailMsg('网络异常，请稍后重试', 'error');
            btn.disabled    = false;
            btn.textContent = '发送验证码';
        });
    };

    window.verifyEmailBind = function () {
        const email = document.getElementById('bind-email-input').value.trim();
        const code  = document.getElementById('bind-email-code-input').value.trim();
        if (!/^\d{6}$/.test(code)) {
            showEmailMsg('请输入 6 位数字验证码', 'error');
            return;
        }

        fetch('actions/bind_email.php', {
            method:  'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body:    'sub_action=verify_bind&email=' + encodeURIComponent(email)
                   + '&code='  + encodeURIComponent(code),
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                showEmailMsg(data.msg, 'success');
                clearEmailCountdown();
                const display = document.getElementById('email-display');
                display.className  = 'phone-value bound';
                display.innerHTML  = '<span class="phone-icon">📧</span>'
                    + data.masked
                    + ' <span class="badge badge-normal" style="margin-left:6px">已验证</span>';
                const changeBtn = document.getElementById('email-change-btn');
                changeBtn.textContent = '更换邮箱';
                changeBtn.className   = 'btn secondary btn-sm';
                setTimeout(hideEmailForm, 1500);
            } else {
                showEmailMsg(data.msg, 'error');
            }
        })
        .catch(() => showEmailMsg('网络异常，请稍后重试', 'error'));
    };

    document.getElementById('bind-email-input').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); sendEmailCode(); }
    });
    document.getElementById('bind-email-code-input').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); verifyEmailBind(); }
    });
}());
</script>

<style>
/* ════════════════════════════════════════════════════════════════
   手机号绑定区样式
   ════════════════════════════════════════════════════════════════ */
.phone-current-row {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    padding: 12px 0 14px;
    border-bottom: 1px solid var(--border-color, rgba(0,0,0,.07));
    margin-bottom: 16px;
}
.phone-label {
    flex-shrink: 0;
    font-size: .82rem;
    font-weight: 700;
    color: var(--text-muted, #999);
    text-transform: uppercase;
    letter-spacing: .04em;
    width: 90px;
}
.phone-value {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: .95rem;
    font-weight: 600;
    flex: 1;
}
.phone-value.unbound { color: var(--text-muted, #aaa); }
.phone-value.bound   { color: var(--text, #222); }
.dark-mode .phone-value.bound { color: var(--dark-text, #e8e4ff); }
.phone-icon { font-size: 1.1rem; }

/* 绑定表单 */
.phone-bind-form {
    background: var(--form-bg, rgba(108,93,251,.04));
    border: 1px solid var(--border-color, rgba(108,93,251,.12));
    border-radius: 14px;
    padding: 18px 20px 16px;
    margin-top: 6px;
}
.dark-mode .phone-bind-form {
    background: rgba(108,93,251,.06);
    border-color: rgba(176,160,255,.15);
}

/* 带前缀的输入框行 */
.phone-input-row .phone-input-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.phone-prefix {
    font-size: .88rem;
    font-weight: 600;
    color: var(--text-secondary, #666);
    background: var(--input-bg-alt, rgba(0,0,0,.04));
    border: 1px solid var(--border-color, rgba(0,0,0,.1));
    border-radius: 8px;
    padding: 7px 10px;
    flex-shrink: 0;
}
.dark-mode .phone-prefix {
    background: rgba(255,255,255,.06);
    border-color: rgba(255,255,255,.1);
    color: var(--dark-text-muted, #aaa);
}
.phone-input-wrap input {
    flex: 1;
    min-width: 140px;
}
.phone-input-wrap .btn { flex-shrink: 0; }

/* 反馈消息 */
.phone-feedback {
    padding: 8px 12px;
    border-radius: 8px;
    font-size: .85rem;
    font-weight: 500;
    margin-bottom: 12px;
}
.phone-feedback-success {
    background: #edfbf3;
    color: #1a7a4a;
    border: 1px solid #b2e8cc;
}
.phone-feedback-error {
    background: #fff3f4;
    color: #c0392b;
    border: 1px solid #f5a0aa;
}
.phone-feedback-info {
    background: #eff6ff;
    color: #1565c0;
    border: 1px solid #bbdefb;
}
.dark-mode .phone-feedback-success { background: #0d2f1e; color: #6fcf97; border-color: #1a5c38; }
.dark-mode .phone-feedback-error   { background: #2a1018; color: #f08080; border-color: #7a2030; }
.dark-mode .phone-feedback-info    { background: #0d1a2f; color: #90caf9; border-color: #1a3050; }

/* 小按钮 */
.btn-sm { padding: 5px 13px !important; font-size: .82rem !important; }
.btn.ghost {
    background: transparent;
    border: 1px solid var(--border-color, rgba(0,0,0,.15));
    color: var(--text-secondary, #666);
}
.btn.ghost:hover { background: rgba(0,0,0,.04); }
.dark-mode .btn.ghost { border-color: rgba(255,255,255,.15); color: var(--dark-text-muted,#aaa); }

/* ── 用户中心：图形验证码展开区 ─────────────────────────── */
/* 展开时淡入下移动画，无额外嵌套盒子 */
#phone-captcha-box {
    animation: captchaFadeIn .18s ease;
}
@keyframes captchaFadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* label 右侧的小提示文字 */
.captcha-bind-hint {
    margin-left: 6px;
    font-size: .78rem;
    font-weight: 400;
    color: var(--text-muted, #aaa);
}

/* 输入框 + 验证码图片 + 刷新按钮 同一行 */
.captcha-bind-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* 输入框：与 .phone-input-wrap input 保持一致的高度和样式 */
.captcha-bind-input {
    flex: 1;
    min-width: 0;
    font-family: monospace;
    letter-spacing: 3px;
    font-size: 15px;
    text-align: center;
}

/* 验证码图片 */
.captcha-bind-img {
    height: 42px;
    width: auto;
    border-radius: 8px;
    border: 1.5px solid var(--border-color, rgba(155,140,255,.35));
    cursor: pointer;
    flex-shrink: 0;
    background: #f6f4ff;
    transition: opacity .2s;
    display: block;
}
.captcha-bind-img:hover { opacity: .82; }
.dark-mode .captcha-bind-img {
    background: #2a2845;
    border-color: rgba(176,160,255,.3);
}

/* 刷新按钮 */
.captcha-refresh-sm {
    background: none;
    border: none;
    padding: 0 2px;
    cursor: pointer;
    font-size: 18px;
    color: #9b8cff;
    flex-shrink: 0;
    line-height: 1;
    transition: transform .3s, color .2s;
}
.captcha-refresh-sm:hover { transform: rotate(180deg); color: #ff4db1; }

/* 取消 / 确认发送 按钮行 */
.captcha-bind-actions {
    display: flex;
    gap: 8px;
    margin-top: 2px;
    margin-bottom: 14px;
}
.captcha-bind-actions .btn { flex: 1; }

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

/* ── 当前登录信息卡片 ─────────────────────────────────────── */
.cur-session-card {
    border: 1px solid var(--border-color, rgba(0,0,0,.09));
    border-radius: 12px;
    padding: 4px 16px 10px;
    background: var(--card-bg, #fff);
}
.dark-mode .cur-session-card {
    background: var(--dark-card, #1e1e2e);
    border-color: rgba(176,160,255,.15);
}
.cs-row {
    display: flex;
    align-items: baseline;
    gap: 10px;
    padding: 9px 0;
    border-top: 1px solid rgba(0,0,0,.05);
    font-size: .88rem;
    line-height: 1.5;
}
.dark-mode .cs-row { border-top-color: rgba(255,255,255,.06); }
.cs-label {
    flex-shrink: 0;
    width: 56px;
    font-size: .77rem;
    font-weight: 700;
    color: var(--text-muted, #999);
    text-transform: uppercase;
    letter-spacing: .03em;
}
.cs-value {
    color: var(--text, #222);
    word-break: break-all;
}
.dark-mode .cs-value { color: var(--dark-text, #e8e4ff); }
/* 归属地下方的 ISP / ASN 副行 */
.cs-secondary {
    display: block;
    font-size: .78rem;
    color: var(--text-muted, #999);
    margin-top: 2px;
}
.dark-mode .cs-secondary { color: var(--dark-text-muted, #777); }
.cs-asn {
    font-family: monospace;
    font-size: .76rem;
    letter-spacing: .02em;
}
/* 代理详情 / IP 链路 */
.cs-proxy-hint {
    font-size: .82rem;
    word-break: break-all;
    color: var(--text-secondary, #555);
}
.dark-mode .cs-proxy-hint { color: var(--dark-text-muted, #aaa); }

/* ── 速率限制 / 查询失败提示条（cs-row 内联，无需单独元素） ── */

/* ── 移动端微调 ───────────────────────────────────────────── */
@media (max-width: 600px) {
    .phone-current-row { gap: 10px; }
    .phone-label { width: 70px; }
}

/* ── 登录异常横幅移动端 ───────────────────────────────────────── */
@media (max-width: 600px) {
    .login-alert-banner { font-size: .84rem; padding: .9rem 1rem; }
}

/* ── VPN / 数据中心 badge ────────────────────────────────────── */
.badge-alert {
    background: #fff0f0;
    color: #c0392b;
    border: 1px solid #f5a0aa;
    border-radius: 5px;
    padding: 1px 6px;
    font-size: .75rem;
    font-weight: 700;
    white-space: nowrap;
}
.dark-mode .badge-alert {
    background: #2a1018;
    color: #f08080;
    border-color: #7a2030;
}

/* ── 运营商 / ASN 副文本（历史登录表格用） ───────────────── */
</style>