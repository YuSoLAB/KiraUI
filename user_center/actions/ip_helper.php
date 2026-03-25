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
//  IP 声誉查询（VPN / 数据中心 / 代理检测）
// ═══════════════════════════════════════════════════════════════════

/**
 * 查询 IP 声誉信息，检测是否为 VPN / 数据中心 / 商业代理出口节点。
 *
 * 为什么 HTTP 头检测不到 VPN：
 *   VPN 工作在网络层（Layer 3），用户流量在本地加密后由 VPN 服务器
 *   以"干净"的 TCP 连接访问目标，服务器看不到任何代理头。
 *   HTTP 代理（Clash HTTP模式 / Squid 等）会留 X-Forwarded-For，
 *   但 VPN（WireGuard / OpenVPN / 商业VPN）不会。
 *
 * 检测原理：
 *   VPN 出口节点绝大多数是数据中心 / 托管机房的 IP 段，
 *   由专业数据库（如 ip-api.com）持续追踪并标记 proxy / hosting 字段。
 *
 * 接口说明（ip-api.com 免费版）：
 *   - 仅支持 HTTP（HTTPS 需付费 Pro 版）
 *   - 免费限速：45 次 / 分钟（超出返回 429，rep_source 标记为 rate-limited）
 *   - 结果缓存到本地临时目录，默认 TTL 24 小时，同 IP 24 小时内不发起新查询
 *   - 内网 / 保留 IP 直接跳过，不发出网络请求
 *
 * @param  string $ip        要查询的 IP 地址（已规范化）
 * @param  int    $cacheTtl  本地缓存有效期（秒），默认 86400（24 小时）
 * @return array{
 *   is_vpn:     bool,
 *   is_hosting: bool,
 *   isp:        string,
 *   org:        string,
 *   asn:        string,
 *   country:    string,
 *   region:     string,
 *   rep_source: 'api'|'cache'|'api-fail'|'rate-limited'|'skip-local'|'skip-loopback',
 * }
 */
function queryIpReputation(string $ip, int $cacheTtl = 86400): array
{
    $empty = [
        'is_vpn'     => false,
        'is_hosting' => false,
        'isp'        => '',
        'org'        => '',
        'asn'        => '',
        'country'    => '',
        'region'     => '',
        'rep_source' => 'none',
    ];

    // ── 跳过回环地址 ─────────────────────────────────────────────
    if ($ip === '127.0.0.1' || $ip === '::1') {
        return array_merge($empty, ['rep_source' => 'skip-loopback']);
    }

    // ── 跳过内网 / 私有地址 ───────────────────────────────────────
    if (isLocalIp($ip)) {
        return array_merge($empty, ['rep_source' => 'skip-local']);
    }

    // ── 本地文件缓存 ──────────────────────────────────────────────
    $cacheDir  = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
               . DIRECTORY_SEPARATOR . 'ip_rep_cache';
    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . md5($ip) . '.json';

    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }

    if (is_file($cacheFile) && (time() - (int)filemtime($cacheFile)) < $cacheTtl) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['rep_source'])) {
            $cached['rep_source'] = 'cache';
            return $cached;
        }
    }

    // ── 调用 ip-api.com 免费接口 ──────────────────────────────────
    // fields 说明：
    //   proxy   - 已知 VPN / 匿名代理 / Tor 出口节点
    //   hosting - 数据中心 / 托管服务 IP（大多数商业 VPN 都在此范围内）
    //   isp     - 互联网服务提供商名称
    //   org     - 网络组织（通常与 hosting 描述更精确）
    //   as      - ASN 自治系统号（格式如 "AS13335 Cloudflare, Inc."）
    $fields = 'status,message,country,regionName,city,isp,org,as,proxy,hosting';
    $url    = sprintf('http://ip-api.com/json/%s?fields=%s&lang=zh-CN',
                      rawurlencode($ip), $fields);

    $ctx = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'timeout'       => 3,           // 超时 3 秒，避免阻塞页面
            'user_agent'    => 'PHP-IpHelper/1.0',
            'ignore_errors' => true,        // 允许读取 4xx/5xx 响应体
        ],
    ]);

    $raw = @file_get_contents($url, false, $ctx);

    // 从 $http_response_header（file_get_contents 副作用全局变量）读取 HTTP 状态码
    $httpStatus = 0;
    if (!empty($http_response_header[0])) {
        preg_match('/\s(\d{3})\s/', $http_response_header[0], $sm);
        $httpStatus = (int)($sm[1] ?? 0);
    }

    if ($raw === false || $raw === '') {
        return array_merge($empty, ['rep_source' => 'api-fail']);
    }

    $data = json_decode($raw, true);

    // ── 速率超限检测（HTTP 429 或 ip-api.com 响应体含 rate limit 消息）
    $isRateLimit = ($httpStatus === 429)
        || (is_array($data) && stripos($data['message'] ?? '', 'rate') !== false);
    if ($isRateLimit) {
        // 不缓存，下次仍会重试；调用方可据 rep_source 决定是否给用户提示
        return array_merge($empty, ['rep_source' => 'rate-limited']);
    }

    if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
        return array_merge($empty, ['rep_source' => 'api-fail']);
    }

    $result = [
        'is_vpn'     => (bool)($data['proxy']   ?? false),
        'is_hosting' => (bool)($data['hosting'] ?? false),
        'isp'        => (string)($data['isp']        ?? ''),
        'org'        => (string)($data['org']        ?? ''),
        'asn'        => (string)($data['as']         ?? ''),
        'country'    => (string)($data['country']    ?? ''),
        'region'     => trim(($data['city'] ?? '') . ' ' . ($data['regionName'] ?? '')),
        'rep_source' => 'api',
    ];

    // 写缓存（api-fail 不写，避免缓存临时网络故障）
    @file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_UNICODE));

    return $result;
}

/**
 * 在已有 detectIpInfo() 结果基础上，附加 IP 声誉信息。
 *
 * 单独封装的目的：声誉查询涉及网络请求（即使有缓存也有 IO），
 * 保持 detectIpInfo() 纯本地、无副作用；调用方按需决定是否查询。
 *
 * 用法：
 *   $ipInfo = detectIpInfo();
 *   $ipInfo = enrichIpReputation($ipInfo);   // 可选，需要时才调用
 *
 * 函数会向 $ipInfo 追加以下字段并可能修改 is_proxy / proxy_hint：
 *   + is_vpn       bool    是否为已知 VPN / 匿名代理出口
 *   + is_hosting   bool    是否为数据中心 / 托管机房 IP
 *   + isp          string  运营商名称
 *   + org          string  网络组织
 *   + asn          string  自治系统号
 *   + country      string  所在国家
 *   + region       string  所在城市 / 地区
 *   + rep_source   string  数据来源（api / cache / api-fail / skip-*）
 *
 * @param  array $ipInfo    detectIpInfo() 的返回值
 * @param  int   $cacheTtl  声誉缓存有效期（秒），默认 86400（24 小时）
 * @return array            追加声誉字段后的 $ipInfo
 */
function enrichIpReputation(array $ipInfo, int $cacheTtl = 86400): array
{
    $queryIp = $ipInfo['ipv4'] ?? $ipInfo['ipv6'] ?? $ipInfo['display_ip'] ?? null;

    if ($queryIp === null || $queryIp === '未知') {
        return $ipInfo;
    }

    $rep = queryIpReputation($queryIp, $cacheTtl);

    // 合并声誉字段
    $ipInfo['is_vpn']     = $rep['is_vpn'];
    $ipInfo['is_hosting'] = $rep['is_hosting'];
    $ipInfo['isp']        = $rep['isp'];
    $ipInfo['org']        = $rep['org'];
    $ipInfo['asn']        = $rep['asn'];
    $ipInfo['country']    = $rep['country'];
    $ipInfo['region']     = $rep['region'];
    $ipInfo['rep_source'] = $rep['rep_source'];

    // 若声誉库标记为 VPN / 代理，同步更新 is_proxy 和 proxy_hint
    if ($rep['is_vpn'] || $rep['is_hosting']) {
        $ipInfo['is_proxy'] = true;

        $hints = [];
        if ($rep['is_vpn'])     $hints[] = 'VPN / 匿名代理出口';
        if ($rep['is_hosting']) $hints[] = '数据中心 / 托管机房 IP';
        if ($rep['isp'] !== '') $hints[] = 'ISP: ' . $rep['isp'];
        if ($rep['org'] !== '') $hints[] = 'Org: ' . $rep['org'];

        // 追加到现有 proxy_hint，不覆盖（可能已有反向代理描述）
        $extra = implode('；', $hints);
        $ipInfo['proxy_hint'] = $ipInfo['proxy_hint']
            ? $ipInfo['proxy_hint'] . '；' . $extra
            : $extra;
    }

    return $ipInfo;
}

// ═══════════════════════════════════════════════════════════════════
//  User-Agent 解析
// ═══════════════════════════════════════════════════════════════════

/**
 * 解析 User-Agent Client Hints（UA-CH）响应头。
 *
 * 现代浏览器（Chrome 89+、Edge 93+）实施了 UA Reduction 策略：
 *   - UA 字符串中的 Windows 版本永远报 10.0（即使实际是 11）
 *   - Chrome 版本号仅保留主版本，其余位填充 0
 * UA-CH 是 W3C 标准的替代方案，可获取真实高熵值信息。
 *
 * 服务器需先发送 Accept-CH 响应头，浏览器后续请求才会携带完整 Hints。
 * 低熵头（Sec-CH-UA / Sec-CH-UA-Mobile / Sec-CH-UA-Platform）无需请求即默认发送。
 *
 * @param  array|null $server  $_SERVER 或自定义来源，null 时使用全局 $_SERVER
 * @return array{
 *   browser: string|null,
 *   os: string|null,
 *   device_type: string|null,
 *   source: 'ua-ch'|'ua-ch-partial'|'ua-string',
 *   raw: array
 * }
 */
function parseClientHints(?array $server = null): array
{
    $s = $server ?? $_SERVER;

    $result = [
        'browser'     => null,
        'os'          => null,
        'device_type' => null,
        'source'      => 'ua-string',
        'raw'         => [],
    ];

    // ── 读取各 UA-CH 头（$_SERVER 中以 HTTP_ 前缀存储）──────────
    $chUA            = $s['HTTP_SEC_CH_UA']                  ?? '';  // 低熵，默认发送
    $chFullVersions  = $s['HTTP_SEC_CH_UA_FULL_VERSION_LIST'] ?? '';  // 高熵，需 Accept-CH
    $chMobile        = $s['HTTP_SEC_CH_UA_MOBILE']           ?? '';  // 低熵
    $chPlatform      = $s['HTTP_SEC_CH_UA_PLATFORM']         ?? '';  // 低熵
    $chPlatformVer   = $s['HTTP_SEC_CH_UA_PLATFORM_VERSION'] ?? '';  // 高熵
    $chModel         = $s['HTTP_SEC_CH_UA_MODEL']            ?? '';  // 高熵（移动设备型号）

    $result['raw'] = [
        'Sec-CH-UA'                  => $chUA,
        'Sec-CH-UA-Full-Version-List'=> $chFullVersions,
        'Sec-CH-UA-Mobile'           => $chMobile,
        'Sec-CH-UA-Platform'         => $chPlatform,
        'Sec-CH-UA-Platform-Version' => $chPlatformVer,
        'Sec-CH-UA-Model'            => $chModel,
    ];

    // 没有任何 UA-CH 头，直接返回（交由 UA 字符串解析处理）
    if ($chUA === '' && $chPlatform === '' && $chMobile === '') {
        return $result;
    }

    $hasHighEntropy = ($chFullVersions !== '' || $chPlatformVer !== '');
    $result['source'] = $hasHighEntropy ? 'ua-ch' : 'ua-ch-partial';

    // ── 设备类型（Sec-CH-UA-Mobile）────────────────────────────
    // ?1 = 移动端，?0 = 非移动端
    if ($chMobile !== '') {
        $result['device_type'] = ($chMobile === '?1') ? 'mobile' : 'desktop';
    }

    // ── 浏览器识别（优先用完整版本列表，降级到基础列表）───────
    // 格式示例：
    //   Sec-CH-UA: "Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"
    //   Sec-CH-UA-Full-Version-List: "Chromium";v="124.0.6367.60","Google Chrome";v="124.0.6367.60"
    $brandSource = $chFullVersions ?: $chUA;
    if ($brandSource !== '') {
        $browser = parseChBrandList($brandSource);
        if ($browser !== null) {
            $result['browser'] = $browser;
        }
    }

    // ── 操作系统（Sec-CH-UA-Platform + Sec-CH-UA-Platform-Version）
    // 平台值示例："Windows" / "macOS" / "Android" / "iOS" / "Linux" / "ChromeOS"
    if ($chPlatform !== '') {
        // 去除引号
        $platform = trim($chPlatform, '" ');
        $os       = $platform;

        if ($chPlatformVer !== '') {
            $ver = trim($chPlatformVer, '" ');

            if ($platform === 'Windows') {
                // Windows 平台版本为 NT 版本号：13.0.0 = Win11，10.0.x = Win10
                $major = (int) explode('.', $ver)[0];
                $os = match(true) {
                    $major >= 13 => 'Windows 11',    // NT ≥ 13.0 对应 Win11 22H2+
                    $major >= 10 => 'Windows 10',
                    $major >= 6  => 'Windows 8/8.1',
                    default      => 'Windows',
                };
            } elseif ($platform === 'macOS') {
                // macOS 平台版本为标准版本号，如 "14.4.1"
                $os = 'macOS ' . $ver;
            } elseif ($platform === 'Android') {
                $os = 'Android ' . $ver;
            } elseif ($platform === 'iOS') {
                $os = 'iOS ' . str_replace('_', '.', $ver);
            } elseif ($platform === 'ChromeOS') {
                $os = 'ChromeOS ' . $ver;
            } elseif ($ver !== '' && $ver !== '0.0.0' && $ver !== '') {
                $os = $platform . ' ' . $ver;
            }
        } else {
            // 只有平台名，无版本号（低熵模式）
            if ($platform === 'Windows') {
                // 无高熵版本时无法区分 Win10/11，保留通用说明
                $os = 'Windows 10/11';
            }
        }

        $result['os'] = $os;
    }

    return $result;
}

/**
 * 解析 Sec-CH-UA / Sec-CH-UA-Full-Version-List 品牌列表字符串。
 *
 * 格式：`"Brand A";v="ver", "Brand B";v="ver", "Not-A.Brand";v="99"`
 * 规则：
 *   - 忽略 "Not-A.Brand" / "Not.A/Brand" 等占位品牌
 *   - 已知品牌别名映射到标准名称
 *   - 版本取主版本号（如 "124.0.6367.60" → "124"）
 */
function parseChBrandList(string $raw): ?string
{
    // 品牌别名映射（UA-CH 中使用的品牌名 → 显示名称）
    $brandMap = [
        'Microsoft Edge'          => 'Edge',
        'Chromium'                => 'Chromium',
        'Google Chrome'           => 'Chrome',
        'Opera'                   => 'Opera',
        'Samsung Internet'        => 'Samsung Browser',
        'Yandex'                  => 'Yandex Browser',
        'Brave'                   => 'Brave',
        'Vivaldi'                 => 'Vivaldi',
        'HeadlessChrome'          => 'Chrome (Headless)',
    ];

    // 优先级：Edge > Opera > 其它已知品牌 > Chromium
    $priority = ['Microsoft Edge' => 10, 'Opera' => 9, 'Brave' => 8,
                 'Vivaldi' => 7, 'Yandex' => 6, 'Samsung Internet' => 5,
                 'Google Chrome' => 4, 'Chromium' => 1];

    // 解析 "Brand";v="version" 对
    preg_match_all('/"([^"]+)";\s*v="([^"]+)"/i', $raw, $matches, PREG_SET_ORDER);

    $found    = [];
    $notBrand = '/^not[.\-_a]?[a-z]?[.\/\-_]?brand/i';  // 匹配各种占位符变体

    foreach ($matches as $m) {
        $brand = $m[1];
        $ver   = $m[2];

        if (preg_match($notBrand, $brand)) {
            continue;  // 忽略占位品牌
        }

        $displayName = $brandMap[$brand] ?? $brand;
        $pri         = $priority[$brand] ?? 3;
        $found[]     = ['name' => $displayName, 'ver' => $ver, 'pri' => $pri];
    }

    if (empty($found)) {
        return null;
    }

    // 按优先级降序排列，取最高优先级品牌
    usort($found, fn($a, $b) => $b['pri'] <=> $a['pri']);
    $best = $found[0];

    return $best['name'] . ' ' . majorVersion($best['ver']);
}

// ═══════════════════════════════════════════════════════════════════
//  User-Agent 解析（UA 字符串 + UA-CH 双轨融合）
// ═══════════════════════════════════════════════════════════════════

/**
 * 从 User-Agent 字符串提取浏览器、操作系统、设备类型。
 *
 * 策略（优先级从高到低）：
 *   1. UA-CH 高熵头（需服务器发送 Accept-CH 后，浏览器在后续请求中携带）
 *   2. UA-CH 低熵头（Sec-CH-UA / Sec-CH-UA-Mobile / Sec-CH-UA-Platform，默认发送）
 *   3. 传统 User-Agent 字符串（Frozen / 模糊化，精度有限）
 *
 * @param  string     $ua      HTTP_USER_AGENT
 * @param  array|null $server  $_SERVER 或自定义来源，用于读取 UA-CH 头
 * @return array{browser: string, os: string, device_type: string, ua_source: string}
 */
function parseUserAgent(string $ua, ?array $server = null): array
{
    $browser    = '未知浏览器';
    $os         = '未知系统';
    $deviceType = 'desktop';
    $uaSource   = 'ua-string';

    // ── 优先尝试 UA-CH ───────────────────────────────────────────
    $hints = parseClientHints($server);

    if ($hints['browser'] !== null) {
        $browser  = $hints['browser'];
        $uaSource = $hints['source'];
    }
    if ($hints['os'] !== null) {
        $os       = $hints['os'];
    }
    if ($hints['device_type'] !== null) {
        $deviceType = $hints['device_type'];
    }

    // ── UA-CH 未提供的字段，用 UA 字符串补齐 ────────────────────
    $needBrowser = ($hints['browser'] === null);
    $needOs      = ($hints['os']      === null);
    $needDevice  = ($hints['device_type'] === null);

    if (empty($ua)) {
        return [
            'browser'    => $browser,
            'os'         => $os,
            'device_type'=> $deviceType,
            'ua_source'  => $uaSource,
        ];
    }

    // ── 设备类型（UA 字符串兜底）────────────────────────────────
    if ($needDevice) {
        if (preg_match('/Mobile|iPhone|iPod|BlackBerry|IEMobile|Opera Mini|Android.*Mobile/i', $ua)) {
            $deviceType = 'mobile';
        } elseif (preg_match('/iPad|Android(?!.*Mobile)|Tablet|PlayBook/i', $ua)) {
            $deviceType = 'tablet';
        }
    }

    // ── 浏览器（UA 字符串兜底，仅在 UA-CH 未提供时执行）─────────
    if ($needBrowser) {
        $uaSource = 'ua-string';
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
    }

    // ── 操作系统（UA 字符串兜底）────────────────────────────────
    if ($needOs) {
        // 注意：Chrome/Edge 在 UA Reduction 后 Windows 永远报 NT 10.0，无法区分 Win10/11
        if      (preg_match('/Windows NT 10\.0/i', $ua))                    $os = 'Windows 10/11';
        elseif  (preg_match('/Windows NT 6\.3/i',  $ua))                    $os = 'Windows 8.1';
        elseif  (preg_match('/Windows NT 6\.2/i',  $ua))                    $os = 'Windows 8';
        elseif  (preg_match('/Windows NT 6\.1/i',  $ua))                    $os = 'Windows 7';
        elseif  (preg_match('/Windows/i',          $ua))                    $os = 'Windows';
        elseif  (preg_match('/iPhone OS ([0-9_]+)/i', $ua, $m))             $os = 'iOS ' . str_replace('_', '.', $m[1]);
        elseif  (preg_match('/iPad.*OS ([0-9_]+)/i',  $ua, $m))             $os = 'iPadOS ' . str_replace('_', '.', $m[1]);
        elseif  (preg_match('/Mac OS X ([0-9_.]+)/i', $ua, $m))             $os = 'macOS ' . str_replace('_', '.', $m[1]);
        elseif  (preg_match('/Android ([0-9.]+)/i',    $ua, $m))            $os = 'Android ' . $m[1];
        elseif  (preg_match('/HarmonyOS/i',            $ua))                 $os = 'HarmonyOS';
        elseif  (preg_match('/Ubuntu/i',               $ua))                 $os = 'Ubuntu';
        elseif  (preg_match('/Linux/i',                $ua))                 $os = 'Linux';
    }

    return [
        'browser'     => $browser,
        'os'          => $os,
        'device_type' => $deviceType,
        'ua_source'   => $uaSource,
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