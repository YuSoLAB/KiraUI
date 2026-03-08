<?php
/**
 * serve_media.php — 公开图片/媒体代理（无需登录）
 * 放在网站根目录（与 index.php 同级）。
 * 用法：serve_media.php?folder=images&name=xxx.jpg
 *
 * 安全措施：
 *  - folder 严格白名单
 *  - name 强制 basename()，禁止路径穿越
 *  - 只允许图片/视频/音频/文档扩展名，禁止脚本类文件
 */

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', __DIR__);
}

$ALLOWED_FOLDERS = ['images', 'videos', 'audios', 'files'];
$DENY_EXTS       = ['php','php3','php4','php5','php7','phtml','phar',
                     'asp','aspx','jsp','cgi','py','pl','rb','sh',
                     'bash','exe','bat','cmd','ps1','htaccess','htpasswd'];

$folder = $_GET['folder'] ?? '';
$name   = basename($_GET['name'] ?? '');

// ── 参数校验 ─────────────────────────────────────────────────────────────
if (!in_array($folder, $ALLOWED_FOLDERS, true) || $name === '') {
    http_response_code(400);
    exit('Bad request');
}

$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
if (in_array($ext, $DENY_EXTS, true)) {
    http_response_code(403);
    exit('Forbidden');
}

// ── 文件定位：优先找 uploads/（同级），其次找上级 uploads/────────────────
$candidates = [
    ROOT_DIR . '/uploads/' . $folder . '/' . $name,         // 根目录/uploads/
    dirname(ROOT_DIR) . '/uploads/' . $folder . '/' . $name, // 上级/uploads/（兼容旧结构）
];

$path = null;
foreach ($candidates as $c) {
    if (file_exists($c) && is_file($c)) { $path = $c; break; }
}

if ($path === null) {
    http_response_code(404);
    exit('Not found');
}

// ── MIME 映射 ─────────────────────────────────────────────────────────────
$mimeMap = [
    'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png',
    'gif'=>'image/gif','webp'=>'image/webp','svg'=>'image/svg+xml',
    'bmp'=>'image/bmp','ico'=>'image/x-icon','avif'=>'image/avif',
    'tiff'=>'image/tiff','tif'=>'image/tiff',
    'mp4'=>'video/mp4','webm'=>'video/webm','avi'=>'video/x-msvideo',
    'mov'=>'video/quicktime','mkv'=>'video/x-matroska',
    'mp3'=>'audio/mpeg','wav'=>'audio/wav','ogg'=>'audio/ogg',
    'flac'=>'audio/flac','aac'=>'audio/aac','m4a'=>'audio/mp4',
    'pdf'=>'application/pdf',
];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

// ── 缓存头 + ETag ──────────────────────────────────────────────────────────
$etag = '"' . md5($path . filemtime($path)) . '"';
if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) {
    http_response_code(304);
    exit;
}

header('Content-Type: '   . $mime);
header('Content-Length: ' . filesize($path));
header('ETag: '           . $etag);
header('Cache-Control: public, max-age=2592000'); // 30天
header('Content-Disposition: inline; filename="' . addslashes($name) . '"');
readfile($path);
exit;