<?php
require_once 'common.php';
$token = $_GET['token'] ?? '';
if (empty($token)) {
    die('无效的下载链接');
}
$original_url = decrypt_download_url($token);
if (!$original_url) {
    die('下载链接已过期或无效');
}
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $original_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, false);
$response = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $header_size);
$content = substr($response, $header_size);
curl_close($ch);
$filename = '';
if (preg_match('/Content-Disposition:.*?filename="(.*?)"/i', $headers, $matches)) {
    $filename = $matches[1];
} else {
    $parsed_url = parse_url($original_url);
    $path = $parsed_url['path'] ?? '';
    $filename = basename($path);
}
if (!preg_match('/\.[a-zA-Z0-9]+$/', $filename)) {
    $filename = 'download_' . time() . '.bin';
}
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
echo $content;
exit;
?>