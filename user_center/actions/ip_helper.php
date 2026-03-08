<?php
/**
 * IP 检测与设备识别辅助函数
 *
 * 处理场景：
 *   - CDN / 反向代理 / 负载均衡（Cloudflare、Nginx、HAProxy 等）
 *   - IPv4 映射 IPv6（::ffff:x.x.x.x）
 *   - X-Forwarded-For 含多个 IP
 *   - 本地 / 局域网环境
 *   - 用户使用代理访问
 */

// ── 禁止直接访问 ────────────────────────────────────────────────────
if (!defined('APP_INIT')) { http_response_code(403); exit; }

// ═══════════════════════════════════════════════════════════════════
//  IP 检测
// ═══════════════════════════════════════════════════════════════════

/**
 * 检测并返回完整 IP 信息。
 *
 * @return array{
 *   ipv4: string|null,
 *   ipv6: string|null,
 *   display_ip: string,
 *   all_ips: string[],
 *   ip_source: string,
 *   is_local: bool,
 *   is_proxy: bool,
 *   proxy_hint: string|null
 * }
 */
function detectIpInfo(): array
{
    $result = [
        'ipv4'       => null,
        'ipv6'       => null,
        'display_ip' => '未知',
        'all_ips'    => [],
        'ip_source'  => 'REMOTE_ADDR',
        'is_local'   => false,
        'is_proxy'   => false,
        'proxy_hint' => null,
    ];

    /*
     * 代理 / CDN 头部，按优先级排列。
     * 越靠前的头部可信度越高（通常由可信代理注入）。
     */
    $proxyHeaders = [
        'HTTP_CF_CONNECTING_IP'          => 'Cloudflare',
        'HTTP_TRUE_CLIENT_IP'            => 'Cloudflare Enterprise / Akamai',
        'HTTP_X_REAL_IP'                 => 'Nginx 反向代理',
        'HTTP_X_FORWARDED_FOR'           => 'X-Forwarded-For',
        'HTTP_X_CLUSTER_CLIENT_IP'       => '负载均衡',
        'HTTP_X_FORWARDED'               => 'X-Forwarded',
        'HTTP_FORWARDED_FOR'             => 'Forwarded-For',
        'HTTP_FORWARDED'                 => 'Forwarded',
        'HTTP_CLIENT_IP'                 => 'Client-IP',
        'HTTP_X_ORIGINAL_FORWARDED_FOR'  => 'X-Original-Forwarded-For',
    ];

    $allIps      = [];  // 去重后的所有 IP（已规范化）
    $proxySource = null;

    // ── 1. 遍历代理头，提取所有 IP ────────────────────────────────
    foreach ($proxyHeaders as $header => $source) {
        if (empty($_SERVER[$header])) {
            continue;
        }
        // X-Forwarded-For 可能含多个逗号分隔的 IP
        $parts = array_map('trim', explode(',', $_SERVER[$header]));
        foreach ($parts as $raw) {
            $ip = normalizeIp($raw);
            if ($ip !== null && !in_array($ip, $allIps, true)) {
                $allIps[] = $ip;
            }
        }
        if ($proxySource === null) {
            $proxySource = $source;
        }
    }

    // ── 2. REMOTE_ADDR（服务器直接看到的连接 IP）────────────────
    $remoteAddr = normalizeIp($_SERVER['REMOTE_ADDR'] ?? '');
    if ($remoteAddr !== null && !in_array($remoteAddr, $allIps, true)) {
        $allIps[] = $remoteAddr;
    }

    // ── 3. 判断是否经过代理 ──────────────────────────────────────
    $hasProxyHeader = false;
    foreach (array_keys($proxyHeaders) as $h) {
        if (!empty($_SERVER[$h])) {
            $hasProxyHeader = true;
            break;
        }
    }

    // Via 头是用户侧代理（HTTP 代理软件）的标志
    $viaHeader = $_SERVER['HTTP_VIA'] ?? '';
    if ($viaHeader) {
        $result['is_proxy']   = true;
        $result['proxy_hint'] = 'Via: ' . substr(strip_tags($viaHeader), 0, 120);
    }

    // REMOTE_ADDR 是内网 IP 但存在代理头 → 反向代理 / CDN
    if ($hasProxyHeader && $remoteAddr !== null && isLocalIp($remoteAddr)) {
        $result['is_proxy']   = true;
        $result['proxy_hint'] = $result['proxy_hint']
            ?? ('经过反向代理 / CDN（' . ($proxySource ?? '未知来源') . '）');
    }

    // ── 4. 选主 IP：优先选第一个非内网 IP ───────────────────────
    $primaryIp = null;
    foreach ($allIps as $ip) {
        if (!isLocalIp($ip)) {
            $primaryIp = $ip;
            break;
        }
    }
    // 全是内网（本地测试环境）则取第一个
    if ($primaryIp === null && !empty($allIps)) {
        $primaryIp = $allIps[0];
    }

    // ── 5. 分离 IPv4 / IPv6 ─────────────────────────────────────
    if ($primaryIp !== null) {
        $result['display_ip'] = $primaryIp;

        if (filter_var($primaryIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $result['ipv4'] = $primaryIp;
            // 从剩余 IP 中找 IPv6
            foreach ($allIps as $ip) {
                if ($ip !== $primaryIp &&
                    filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    $result['ipv6'] = $ip;
                    break;
                }
            }
        } elseif (filter_var($primaryIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $result['ipv6'] = $primaryIp;
            // 从剩余 IP 中找 IPv4
            foreach ($allIps as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $result['ipv4'] = $ip;
                    break;
                }
            }
        }
    }

    // ── 6. 本地 / 内网标记 ──────────────────────────────────────
    if ($primaryIp !== null && isLocalIp($primaryIp)) {
        $result['is_local'] = true;
    }

    $result['all_ips']   = $allIps;
    $result['ip_source'] = $proxySource ?? 'REMOTE_ADDR';

    return $result;
}

/**
 * 规范化 IP 字符串：
 *   - 去除首尾空格
 *   - 剥离 IPv4 映射 IPv6 前缀（::ffff:）
 *   - 校验合法性
 *
 * @return string|null  合法 IP 或 null
 */
function normalizeIp(string $raw): ?string
{
    $ip = trim($raw);

    // ::ffff:1.2.3.4 → 1.2.3.4
    if (preg_match('/^::ffff:(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/i', $ip, $m)) {
        $ip = $m[1];
    }

    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
}

/**
 * 判断 IP 是否为本地 / 私有 / 保留地址。
 */
function isLocalIp(string $ip): bool
{
    // 回环
    if ($ip === '127.0.0.1' || $ip === '::1') {
        return true;
    }

    // IPv4：使用 PHP 内置过滤器检测私有 / 保留段
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    // IPv6：fc00::/7 唯一本地、fe80::/10 链路本地
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        if (preg_match('/^f[cd]/i', $ip)) return true;   // fc00::/7
        if (preg_match('/^fe[89ab]/i', $ip)) return true; // fe80::/10
    }

    return false;
}

// ═══════════════════════════════════════════════════════════════════
//  User-Agent 解析
// ═══════════════════════════════════════════════════════════════════

/**
 * 从 User-Agent 提取浏览器、操作系统、设备类型。
 *
 * @return array{browser: string, os: string, device_type: string}
 */
function parseUserAgent(string $ua): array
{
    $browser    = '未知浏览器';
    $os         = '未知系统';
    $deviceType = 'desktop';

    if (empty($ua)) {
        return compact('browser', 'os', 'deviceType') + ['device_type' => $deviceType];
    }

    // ── 设备类型 ─────────────────────────────────────────────────
    if (preg_match('/Mobile|iPhone|iPod|BlackBerry|IEMobile|Opera Mini|Android.*Mobile/i', $ua)) {
        $deviceType = 'mobile';
    } elseif (preg_match('/iPad|Android(?!.*Mobile)|Tablet|PlayBook/i', $ua)) {
        $deviceType = 'tablet';
    }

    // ── 浏览器（注意顺序：特化版本先于通用版本）────────────────
    if      (preg_match('/Edg(?:e|A|iOS)?\/([0-9.]+)/i', $ua, $m))    $browser = 'Edge '    . majorVersion($m[1]);
    elseif  (preg_match('/OPR\/([0-9.]+)/i',              $ua, $m))    $browser = 'Opera '   . majorVersion($m[1]);
    elseif  (preg_match('/Opera.*Version\/([0-9.]+)/i',   $ua, $m))    $browser = 'Opera '   . majorVersion($m[1]);
    elseif  (preg_match('/SamsungBrowser\/([0-9.]+)/i',   $ua, $m))    $browser = 'Samsung ' . majorVersion($m[1]);
    elseif  (preg_match('/UCBrowser\/([0-9.]+)/i',        $ua, $m))    $browser = 'UC '      . majorVersion($m[1]);
    elseif  (preg_match('/QQBrowser\/([0-9.]+)/i',        $ua, $m))    $browser = 'QQ浏览器 ' . majorVersion($m[1]);
    elseif  (preg_match('/MicroMessenger\/([0-9.]+)/i',   $ua, $m))    $browser = '微信 '     . majorVersion($m[1]);
    elseif  (preg_match('/Chrome\/([0-9.]+)/i',           $ua, $m))    $browser = 'Chrome '  . majorVersion($m[1]);
    elseif  (preg_match('/Firefox\/([0-9.]+)/i',          $ua, $m))    $browser = 'Firefox ' . majorVersion($m[1]);
    elseif  (preg_match('/Version\/([0-9.]+).*Safari/i',  $ua, $m))    $browser = 'Safari '  . majorVersion($m[1]);
    elseif  (preg_match('/Safari\//i',                    $ua))         $browser = 'Safari';
    elseif  (preg_match('/MSIE ([0-9.]+)/i',              $ua, $m))    $browser = 'IE '      . $m[1];
    elseif  (preg_match('/Trident.*rv:([0-9.]+)/i',       $ua, $m))    $browser = 'IE '      . $m[1];

    // ── 操作系统 ─────────────────────────────────────────────────
    if      (preg_match('/Windows NT 10\.0/i', $ua))                    $os = 'Windows 10/11';
    elseif  (preg_match('/Windows NT 6\.3/i',  $ua))                    $os = 'Windows 8.1';
    elseif  (preg_match('/Windows NT 6\.2/i',  $ua))                    $os = 'Windows 8';
    elseif  (preg_match('/Windows NT 6\.1/i',  $ua))                    $os = 'Windows 7';
    elseif  (preg_match('/Windows/i',          $ua))                    $os = 'Windows';
    elseif  (preg_match('/iPhone OS ([0-9_]+)/i', $ua, $m))             $os = 'iOS ' . str_replace('_', '.', $m[1]);
    elseif  (preg_match('/iPad.*OS ([0-9_]+)/i',  $ua, $m))             $os = 'iPadOS ' . str_replace('_', '.', $m[1]);
    elseif  (preg_match('/Mac OS X ([0-9_.]+)/i', $ua, $m))             $os = 'macOS ' . str_replace('_', '.', $m[1]);
    elseif  (preg_match('/Android ([0-9.]+)/i',   $ua, $m))             $os = 'Android ' . $m[1];
    elseif  (preg_match('/HarmonyOS/i',            $ua))                 $os = 'HarmonyOS';
    elseif  (preg_match('/Ubuntu/i',               $ua))                 $os = 'Ubuntu';
    elseif  (preg_match('/Linux/i',                $ua))                 $os = 'Linux';

    return [
        'browser'     => $browser,
        'os'          => $os,
        'device_type' => $deviceType,
    ];
}

/** 仅保留版本号中的主版本（如 "120.0.0.1" → "120"）*/
function majorVersion(string $ver): string
{
    return explode('.', $ver)[0];
}

// ═══════════════════════════════════════════════════════════════════
//  登录记录比对
// ═══════════════════════════════════════════════════════════════════

/**
 * 比较两条登录记录，返回异常级别与原因列表。
 *
 * @param  array $current  当前登录记录（DB 行数组）
 * @param  array $last     上次登录记录（DB 行数组）
 * @return array{level: 'normal'|'warn'|'alert', reasons: string[]}
 */
function compareLoginRecords(array $current, array $last): array
{
    $reasons = [];
    $level   = 'normal';

    $curIp  = $current['ipv4'] ?? $current['ipv6'] ?? '';
    $lstIp  = $last['ipv4']    ?? $last['ipv6']    ?? '';

    // ── IP 变化 ──────────────────────────────────────────────────
    if ($curIp !== '' && $lstIp !== '' && $curIp !== $lstIp) {
        $v4Cur = filter_var($curIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        $v4Lst = filter_var($lstIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);

        if ($v4Cur && $v4Lst) {
            $c = explode('.', $curIp);
            $l = explode('.', $lstIp);
            if ($c[0] !== $l[0]) {
                // A 段不同：跨运营商 / 跨地区
                $reasons[] = "IP 地址变化显著（{$lstIp} → {$curIp}）";
                $level     = levelUp($level, 'alert');
            } elseif ($c[1] !== $l[1]) {
                // B 段不同：同地区不同网段
                $reasons[] = "IP 地址有所变化（{$lstIp} → {$curIp}）";
                $level     = levelUp($level, 'warn');
            } else {
                // C/D 段变化：同网段内漂移（动态拨号正常情况）
                $reasons[] = "IP 地址小幅变化（{$lstIp} → {$curIp}）";
                // 不升级警告等级
            }
        } else {
            // IPv4 ↔ IPv6 切换，或无法比较
            $reasons[] = "IP 地址已变化（{$lstIp} → {$curIp}）";
            $level     = levelUp($level, 'warn');
        }
    }

    // ── 设备类型变化 ─────────────────────────────────────────────
    if (($current['device_type'] ?? '') !== ($last['device_type'] ?? '')
        && ($last['device_type'] ?? '') !== '') {
        $map = ['desktop' => '桌面设备', 'mobile' => '手机', 'tablet' => '平板'];
        $from = $map[$last['device_type']]    ?? $last['device_type'];
        $to   = $map[$current['device_type']] ?? $current['device_type'];
        $reasons[] = "登录设备类型变化（{$from} → {$to}）";
        $level     = levelUp($level, 'warn');
    }

    // ── 操作系统变化 ─────────────────────────────────────────────
    $curOs  = $current['os'] ?? '';
    $lstOs  = $last['os']    ?? '';
    // 提取 OS 基础名称，排除版本号差异（如 Windows 10 → Windows 11 正常升级）
    $curOsBase = preg_replace('/[\s\d._\/]+$/', '', $curOs);
    $lstOsBase = preg_replace('/[\s\d._\/]+$/', '', $lstOs);
    if ($curOsBase !== $lstOsBase && $lstOs !== '' && $lstOs !== '未知系统') {
        $reasons[] = "操作系统变化（{$lstOs} → {$curOs}）";
        $level     = levelUp($level, 'warn');
    }

    // ── 浏览器品牌变化（忽略版本更新）──────────────────────────
    $curBrowser = $current['browser'] ?? '';
    $lstBrowser = $last['browser']    ?? '';
    // 提取浏览器品牌（去除版本号）
    $curBrand = preg_replace('/\s+\d+.*$/', '', $curBrowser);
    $lstBrand = preg_replace('/\s+\d+.*$/', '', $lstBrowser);
    if ($curBrand !== $lstBrand && $lstBrowser !== '' && $lstBrowser !== '未知浏览器') {
        $reasons[] = "浏览器变化（{$lstBrowser} → {$curBrowser}）";
        $level     = levelUp($level, 'warn');
    }

    // ── 代理状态突变 ─────────────────────────────────────────────
    $curProxy = (int)($current['is_proxy'] ?? 0);
    $lstProxy = (int)($last['is_proxy']    ?? 0);
    if ($curProxy === 1 && $lstProxy === 0) {
        $reasons[] = '检测到代理特征（上次登录未使用代理）';
        $level     = levelUp($level, 'warn');
    }

    return ['level' => $level, 'reasons' => $reasons];
}

/** 返回两个等级中更高的那个 */
function levelUp(string $current, string $new): string
{
    $order = ['normal' => 0, 'warn' => 1, 'alert' => 2];
    return ($order[$current] ?? 0) >= ($order[$new] ?? 0) ? $current : $new;
}