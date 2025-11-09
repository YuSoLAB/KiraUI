<?php
session_start();
require_once 'common.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => '只允许 POST 请求']);
    exit;
}
$referrer = $_POST['referrer'] ?? '';
$encrypt_id = $_POST['encrypt_id'] ?? '';
if (empty($encrypt_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '加密ID缺失，请求数据无效']);
    exit;
}
$current_host = $_SERVER['HTTP_HOST'] ?? '';
$parsed_current = parse_url('http://' . $current_host);
$current_host_clean = $parsed_current['host'] ?? $current_host;
$allowed_hosts = [];
if (!empty($current_host_clean)) {
    $allowed_hosts[] = $current_host_clean;
}
if (strpos($current_host_clean, 'www.') === 0) {
    $allowed_hosts[] = substr($current_host_clean, 4);
} elseif (!empty($current_host_clean) && strpos($current_host_clean, '.') !== false) {
    $allowed_hosts[] = 'www.' . $current_host_clean;
}
$allowed_hosts[] = 'localhost';
$allowed_hosts[] = '127.0.0.1';
$allowed_hosts = array_unique($allowed_hosts);
$referrer = $_SERVER['HTTP_REFERER'] ?? '';
if (!empty($referrer)) {
    $parsed_referrer = parse_url($referrer);
    $referrer_host = $parsed_referrer['host'] ?? '';
    if (!in_array($referrer_host, $allowed_hosts)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => '不允许的请求来源',
            'debug' => "当前来源: $referrer_host，允许的主机: " . implode(',', $allowed_hosts)
        ]);
        exit;
    }
}
if (!isset($_SESSION['encrypted_downloads'][$encrypt_id])) {
    http_response_code(410); 
    echo json_encode(['success' => false, 'message' => '下载链接已过期或无效。请刷新页面重试。']);
    exit;
}
$original_url = $_SESSION['encrypted_downloads'][$encrypt_id];
unset($_SESSION['encrypted_downloads'][$encrypt_id]);
$encrypted_url = encrypt_download_url($original_url);
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'url' => 'download.php?token=' . urlencode($encrypted_url)
]);
exit;
?>