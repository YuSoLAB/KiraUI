<?php
/**
 * admin_media_ajax.php
 * 媒体库专用 AJAX 端点 —— 处理文件列表/上传/重命名/删除/复制/移动。
 * 由 admin_media.php 中的 JS fetch 调用。
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(dirname(__FILE__)));
}

// 安全检查：必须已登录
if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => '未登录或会话已过期']);
    exit;
}

// ── 常量 ────────────────────────────────────────────────────────────────
define('MEDIA_BASE',    ROOT_DIR . '/uploads/');
define('MEDIA_ALLOWED', ['images', 'videos', 'audios', 'files']);

// ── 静态文件代理（serve）必须在 JSON header 之前处理 ─────────────────────
$_act = $_POST['act'] ?? $_GET['act'] ?? '';
if ($_act === 'serve') {
    $folder = $_GET['folder'] ?? '';
    $name   = basename($_GET['name'] ?? '');

    if (!in_array($folder, MEDIA_ALLOWED, true) || $name === '') {
        http_response_code(400); exit;
    }

    $path = MEDIA_BASE . $folder . '/' . $name;
    if (!file_exists($path) || !is_file($path)) {
        http_response_code(404); exit;
    }

    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $mime = [
        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png',
        'gif'=>'image/gif','webp'=>'image/webp','svg'=>'image/svg+xml',
        'bmp'=>'image/bmp','ico'=>'image/x-icon','avif'=>'image/avif',
        'tiff'=>'image/tiff','tif'=>'image/tiff',
        'mp4'=>'video/mp4','webm'=>'video/webm','avi'=>'video/x-msvideo',
        'mov'=>'video/quicktime','mkv'=>'video/x-matroska','flv'=>'video/x-flv',
        'wmv'=>'video/x-ms-wmv','m4v'=>'video/mp4','3gp'=>'video/3gpp',
        'ogv'=>'video/ogg',
        'mp3'=>'audio/mpeg','wav'=>'audio/wav','ogg'=>'audio/ogg',
        'flac'=>'audio/flac','aac'=>'audio/aac','m4a'=>'audio/mp4',
        'wma'=>'audio/x-ms-wma','opus'=>'audio/opus',
        'pdf'=>'application/pdf',
        'zip'=>'application/zip',
    ][$ext] ?? 'application/octet-stream';

    $etag = '"' . md5($path . filemtime($path)) . '"';
    if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) {
        http_response_code(304); exit;
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('ETag: ' . $etag);
    header('Cache-Control: private, max-age=86400');
    header('Content-Disposition: inline; filename="' . addslashes($name) . '"');
    readfile($path);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// 允许的扩展名映射（黑名单模式：脚本扩展名绝对禁止）
$IMG_EXTS   = ['jpg','jpeg','png','gif','webp','svg','bmp','ico','avif','tiff','tif'];
$VIDEO_EXTS = ['mp4','webm','avi','mov','mkv','flv','wmv','m4v','3gp','ogv'];
$AUDIO_EXTS = ['mp3','wav','ogg','flac','aac','m4a','wma','opus','aiff'];
$DENY_EXTS  = ['php','php3','php4','php5','php7','phtml','phar','asp','aspx',
               'jsp','cgi','py','pl','rb','sh','bash','exe','bat','cmd','ps1',
               'htaccess','htpasswd'];

// ── 工具函数 ─────────────────────────────────────────────────────────────
function mediaDir($folder) {
    if (!in_array($folder, MEDIA_ALLOWED, true)) return false;
    $dir = MEDIA_BASE . $folder . '/';
    if (!file_exists($dir)) mkdir($dir, 0755, true);
    return $dir;
}

function sanitizeFilename($name) {
    $name = basename(trim($name));
    // 保留中文、字母、数字、横杠、下划线、点、括号，其余替换为下划线
    $name = preg_replace('/[^\w\-. ()（）\x{4e00}-\x{9fa5}]/u', '_', $name);
    $name = preg_replace('/_+/', '_', $name);
    return $name ?: 'file';
}

function uniqueName($dir, $filename) {
    if (!file_exists($dir . $filename)) return $filename;
    $base = pathinfo($filename, PATHINFO_FILENAME);
    $ext  = pathinfo($filename, PATHINFO_EXTENSION);
    $dot  = $ext ? '.' . $ext : '';
    $i    = 1;
    while (file_exists($dir . $base . '_' . $i . $dot)) $i++;
    return $base . '_' . $i . $dot;
}

function detectFolder($ext, $imgExts, $videoExts, $audioExts) {
    if (in_array($ext, $imgExts,   true)) return 'images';
    if (in_array($ext, $videoExts, true)) return 'videos';
    if (in_array($ext, $audioExts, true)) return 'audios';
    return 'files';
}

function fmtSize($bytes) {
    if ($bytes < 1024)      return $bytes . ' B';
    if ($bytes < 1048576)   return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

// ── 路由 ─────────────────────────────────────────────────────────────────
$act = $_act; // already defined above (serve was handled early)

try {
    // ═══ LIST ════════════════════════════════════════════════════════════
    if ($act === 'list') {
        $folder  = $_POST['folder'] ?? 'all';
        $folders = ($folder === 'all') ? MEDIA_ALLOWED : [$folder];
        $files   = [];

        foreach ($folders as $f) {
            $dir = MEDIA_BASE . $f . '/';
            if (!file_exists($dir)) continue;
            foreach (scandir($dir) as $fname) {
                if ($fname === '.' || $fname === '..') continue;
                $fp = $dir . $fname;
                if (!is_file($fp)) continue;
                $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                $files[] = [
                    'name'   => $fname,
                    'folder' => $f,
                    'size'   => filesize($fp),
                    'size_h' => fmtSize(filesize($fp)),
                    'mtime'  => filemtime($fp),
                    'ext'    => $ext,
                    'url'    => 'admin_media_ajax.php?act=serve&folder=' . urlencode($f) . '&name=' . urlencode($fname),
                ];
            }
        }

        usort($files, fn($a, $b) => $b['mtime'] - $a['mtime']);
        echo json_encode(['ok' => true, 'files' => $files]);
    }

    // ═══ UPLOAD ══════════════════════════════════════════════════════════
    elseif ($act === 'upload') {
        global $IMG_EXTS, $VIDEO_EXTS, $AUDIO_EXTS, $DENY_EXTS;

        if (empty($_FILES['files'])) {
            echo json_encode(['ok' => false, 'msg' => '没有收到文件']); exit;
        }

        $uploaded = [];
        $errors   = [];
        $fa = $_FILES['files'];
        $count = is_array($fa['name']) ? count($fa['name']) : 1;

        for ($i = 0; $i < $count; $i++) {
            $name = is_array($fa['name'])     ? $fa['name'][$i]     : $fa['name'];
            $tmp  = is_array($fa['tmp_name']) ? $fa['tmp_name'][$i] : $fa['tmp_name'];
            $err  = is_array($fa['error'])    ? $fa['error'][$i]    : $fa['error'];

            if ($err !== UPLOAD_ERR_OK) {
                $errMsg = [
                    UPLOAD_ERR_INI_SIZE   => '文件超过 php.ini 限制',
                    UPLOAD_ERR_FORM_SIZE  => '文件超过表单限制',
                    UPLOAD_ERR_PARTIAL    => '文件上传不完整',
                    UPLOAD_ERR_NO_FILE    => '未选择文件',
                    UPLOAD_ERR_NO_TMP_DIR => '临时目录不存在',
                    UPLOAD_ERR_CANT_WRITE => '写入磁盘失败',
                ][$err] ?? "上传错误 ({$err})";
                $errors[] = "{$name}：{$errMsg}"; continue;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, $DENY_EXTS, true)) {
                $errors[] = "{$name}：禁止上传此类型文件 (.{$ext})"; continue;
            }

            $folder   = detectFolder($ext, $IMG_EXTS, $VIDEO_EXTS, $AUDIO_EXTS);
            $dir      = MEDIA_BASE . $folder . '/';
            if (!file_exists($dir)) mkdir($dir, 0755, true);

            $safeName = sanitizeFilename($name);
            $safeName = uniqueName($dir, $safeName);

            if (move_uploaded_file($tmp, $dir . $safeName)) {
                $uploaded[] = [
                    'name'   => $safeName,
                    'folder' => $folder,
                    'url'    => 'admin_media_ajax.php?act=serve&folder=' . urlencode($folder) . '&name=' . urlencode($safeName),
                    'size_h' => fmtSize(filesize($dir . $safeName)),
                ];
            } else {
                $errors[] = "{$name}：保存失败，请检查目录权限";
            }
        }

        echo json_encode([
            'ok'       => !empty($uploaded),
            'uploaded' => $uploaded,
            'errors'   => $errors,
        ]);
    }

    // ═══ RENAME ══════════════════════════════════════════════════════════
    elseif ($act === 'rename') {
        global $DENY_EXTS;
        $folder  = $_POST['folder']   ?? '';
        $oldName = basename($_POST['old_name'] ?? '');
        $newName = sanitizeFilename($_POST['new_name'] ?? '');

        if (!in_array($folder, MEDIA_ALLOWED, true) || !$oldName || !$newName) {
            echo json_encode(['ok' => false, 'msg' => '参数错误']); exit;
        }

        $newExt = strtolower(pathinfo($newName, PATHINFO_EXTENSION));
        if (in_array($newExt, $DENY_EXTS, true)) {
            echo json_encode(['ok' => false, 'msg' => "禁止重命名为此扩展名 (.{$newExt})"]); exit;
        }

        $dir     = MEDIA_BASE . $folder . '/';
        $oldPath = $dir . $oldName;
        $newPath = $dir . $newName;

        if (!file_exists($oldPath))                      { echo json_encode(['ok'=>false,'msg'=>'源文件不存在']); exit; }
        if (file_exists($newPath) && $oldPath !== $newPath) { echo json_encode(['ok'=>false,'msg'=>'目标文件名已存在，请换一个名称']); exit; }

        rename($oldPath, $newPath);
        echo json_encode(['ok' => true, 'new_name' => $newName]);
    }

    // ═══ DELETE ══════════════════════════════════════════════════════════
    elseif ($act === 'delete') {
        $folder = $_POST['folder'] ?? '';
        $name   = basename($_POST['name'] ?? '');
        $path   = MEDIA_BASE . $folder . '/' . $name;

        if (!in_array($folder, MEDIA_ALLOWED, true) || !$name) {
            echo json_encode(['ok' => false, 'msg' => '参数错误']); exit;
        }
        if (!file_exists($path) || !is_file($path)) {
            echo json_encode(['ok' => false, 'msg' => '文件不存在']); exit;
        }

        unlink($path);
        echo json_encode(['ok' => true]);
    }

    // ═══ COPY ════════════════════════════════════════════════════════════
    elseif ($act === 'copy') {
        $srcFolder = $_POST['src_folder'] ?? '';
        $dstFolder = $_POST['dst_folder'] ?? '';
        $name      = basename($_POST['name'] ?? '');

        if (!in_array($srcFolder, MEDIA_ALLOWED, true) || !in_array($dstFolder, MEDIA_ALLOWED, true) || !$name) {
            echo json_encode(['ok' => false, 'msg' => '参数错误']); exit;
        }

        $srcPath = MEDIA_BASE . $srcFolder . '/' . $name;
        if (!file_exists($srcPath) || !is_file($srcPath)) {
            echo json_encode(['ok' => false, 'msg' => '源文件不存在']); exit;
        }

        $dstDir  = MEDIA_BASE . $dstFolder . '/';
        if (!file_exists($dstDir)) mkdir($dstDir, 0755, true);

        // 同目录复制时自动追加 _copy 后缀
        $dstName = ($srcFolder === $dstFolder)
            ? uniqueName($dstDir, pathinfo($name, PATHINFO_FILENAME) . '_copy.' . pathinfo($name, PATHINFO_EXTENSION))
            : uniqueName($dstDir, $name);

        copy($srcPath, $dstDir . $dstName);
        echo json_encode([
            'ok'     => true,
            'name'   => $dstName,
            'folder' => $dstFolder,
            'url'    => 'admin_media_ajax.php?act=serve&folder=' . urlencode($dstFolder) . '&name=' . urlencode($dstName),
        ]);
    }

    // ═══ MOVE ════════════════════════════════════════════════════════════
    elseif ($act === 'move') {
        $srcFolder = $_POST['src_folder'] ?? '';
        $dstFolder = $_POST['dst_folder'] ?? '';
        $name      = basename($_POST['name'] ?? '');

        if (!in_array($srcFolder, MEDIA_ALLOWED, true) || !in_array($dstFolder, MEDIA_ALLOWED, true) || !$name) {
            echo json_encode(['ok' => false, 'msg' => '参数错误']); exit;
        }
        if ($srcFolder === $dstFolder) {
            echo json_encode(['ok' => false, 'msg' => '源与目标相同，无需移动']); exit;
        }

        $srcPath = MEDIA_BASE . $srcFolder . '/' . $name;
        if (!file_exists($srcPath) || !is_file($srcPath)) {
            echo json_encode(['ok' => false, 'msg' => '源文件不存在']); exit;
        }

        $dstDir  = MEDIA_BASE . $dstFolder . '/';
        if (!file_exists($dstDir)) mkdir($dstDir, 0755, true);

        $dstName = uniqueName($dstDir, $name);
        rename($srcPath, $dstDir . $dstName);
        echo json_encode([
            'ok'     => true,
            'name'   => $dstName,
            'folder' => $dstFolder,
            'url'    => 'admin_media_ajax.php?act=serve&folder=' . urlencode($dstFolder) . '&name=' . urlencode($dstName),
        ]);
    }

    else {
        echo json_encode(['ok' => false, 'msg' => '未知操作: ' . htmlspecialchars($act)]);
    }

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
exit;