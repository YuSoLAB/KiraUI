<?php
/**
 * 保存当前登录会话的 IP + 设备信息到 user_login_history 表。
 * 在 init.php 中于会话建立后调用一次（通过 $_SESSION['login_recorded'] 防重）。
 */

if (!defined('APP_INIT')) { http_response_code(403); exit; }

require_once __DIR__ . '/ip_helper.php';

/**
 * @param int   $userId
 * @param \PDO  $db     已连接的 PDO / Db 实例（需支持 prepare/execute）
 */
function saveLoginHistory(int $userId, $db): void
{
    try {
        $ipInfo     = detectIpInfo();
        $ua         = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $deviceInfo = parseUserAgent($ua);

        $stmt = $db->prepare("
            INSERT INTO user_login_history
                (user_id, ipv4, ipv6, all_ips, ip_source,
                 is_proxy, is_local, user_agent,
                 browser, os, device_type)
            VALUES
                (:user_id, :ipv4, :ipv6, :all_ips, :ip_source,
                 :is_proxy, :is_local, :user_agent,
                 :browser, :os, :device_type)
        ");

        $stmt->execute([
            ':user_id'    => $userId,
            ':ipv4'       => $ipInfo['ipv4'],
            ':ipv6'       => $ipInfo['ipv6'],
            ':all_ips'    => implode(', ', $ipInfo['all_ips']),
            ':ip_source'  => $ipInfo['ip_source'],
            ':is_proxy'   => $ipInfo['is_proxy']  ? 1 : 0,
            ':is_local'   => $ipInfo['is_local']  ? 1 : 0,
            ':user_agent' => mb_substr($ua, 0, 500),
            ':browser'    => $deviceInfo['browser'],
            ':os'         => $deviceInfo['os'],
            ':device_type'=> $deviceInfo['device_type'],
        ]);

    } catch (\Throwable $e) {
        // 登录历史记录失败不应影响正常登录流程，仅记录错误日志
        error_log('[saveLoginHistory] ' . $e->getMessage());
    }
}