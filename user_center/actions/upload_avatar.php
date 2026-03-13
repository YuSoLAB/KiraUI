<?php
/**
 * 上传头像
 * 支持普通 POST 与 XMLHttpRequest (AJAX) 两种方式
 * 支持裁剪后的 Blob（PNG）直接上传
 * 依赖：$db、$user、$isBanned、$_FILES、$_POST
 *
 * 注意：上传的是浏览器裁剪后的 Blob（512×512 PNG，通常 < 500 KB），
 * 而非用户选择的原始大图，因此无需在此限制原始文件大小。
 * 若 PHP 的 upload_max_filesize / post_max_size 过小（默认 2 MB），
 * 请在 php.ini 或 .htaccess 中调整，或通过运行时覆盖（见下方）。
 */

// 运行时扩大 post_max_size，避免 PHP 默认 2 MB 限制拦截裁剪 Blob
// （upload_max_filesize 只能在 php.ini / .htaccess 中修改，无法运行时覆盖）
@ini_set('post_max_size', '20M');

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
    header('Content-Type: application/json');
}

/** 返回 JSON 或重定向，然后终止 */
$respond = function (bool $success, string $msg, array $extra = []) use ($isAjax) {
    $tab = $_POST['active_tab'] ?? 'profile';
    if ($isAjax) {
        echo json_encode(array_merge(['success' => $success, 'message' => $msg], $extra));
    } else {
        $_SESSION[$success ? 'message' : 'error'] = $msg;
        header("Location: index.php?tab=$tab");
    }
    exit;
};

// 封禁用户不允许上传
if ($isBanned) {
    $respond(false, '您的账号已被封禁，无法修改个人信息');
}

// 检查文件是否已选择
if (empty($_FILES['avatar']['name'])) {
    $respond(false, '请选择要上传的头像文件');
}

// 上传错误检测
if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    $errMap = [
        UPLOAD_ERR_INI_SIZE   => '文件超过服务器允许的最大尺寸',
        UPLOAD_ERR_FORM_SIZE  => '文件超过表单允许的最大尺寸',
        UPLOAD_ERR_PARTIAL    => '文件只有部分被上传',
        UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
        UPLOAD_ERR_CANT_WRITE => '文件写入失败',
        UPLOAD_ERR_EXTENSION  => '上传被扩展程序阻止',
    ];
    $respond(false, $errMap[$_FILES['avatar']['error']] ?? '文件上传出错');
}

// ---------- 文件类型校验（MIME + 扩展名双重验证）----------

// 1. 使用 finfo 检测真实 MIME（避免伪造扩展名）
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($_FILES['avatar']['tmp_name']);

$allowedMimes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
];

if (!array_key_exists($mimeType, $allowedMimes)) {
    $respond(false, '只允许上传 jpg、png、gif 格式的图片');
}

// 2. 扩展名兜底：若客户端传来的是裁剪 Blob（filename="avatar.jpg" 等），
//    以 MIME 检测结果为准，保证扩展名与真实格式一致
$extension = $allowedMimes[$mimeType];

// ---------- 目录与路径 ----------
$uploadDir = __DIR__ . '/../../uploads/avatars/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// 判断是否启用审核模式
require_once __DIR__ . '/../../include/Config.php';
$reviewEnabled = Config::getInstance()->get('profile_review_enabled', '0') === '1';

if ($reviewEnabled) {
    // 审核模式：保存为 pending_{user_id}.ext，不覆盖现有头像
    $filename   = 'pending_' . $user['id'] . '.' . $extension;
    $targetPath = $uploadDir . $filename;

    // 清理同用户旧的 pending 文件（若存在）
    foreach ($allowedMimes as $ext) {
        $oldPending = $uploadDir . 'pending_' . $user['id'] . '.' . $ext;
        if ($oldPending !== $targetPath && file_exists($oldPending)) {
            @unlink($oldPending);
        }
    }

    if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
        $respond(false, '头像上传失败，请检查目录权限');
    }

    // 撤销旧的待审核头像申请，插入新申请
    $db->prepare(
        "UPDATE pending_profile_changes SET status='rejected', reject_reason='已被新申请替代', reviewed_at=NOW()
          WHERE user_id=? AND type='avatar' AND status='pending'"
    )->execute([$user['id']]);

    $db->prepare(
        "INSERT INTO pending_profile_changes (user_id, type, new_value) VALUES (?, 'avatar', ?)"
    )->execute([$user['id'], $filename]);

    $respond(true, '头像已提交，等待管理员审核后生效');
}

// 非审核模式：直接覆盖正式头像
$filename   = $user['id'] . '.' . $extension;
$targetPath = $uploadDir . $filename;

// 若用户更换了格式（例如之前是 jpg 现在传 png），清理旧文件
foreach ($allowedMimes as $ext) {
    $oldPath = $uploadDir . $user['id'] . '.' . $ext;
    if ($oldPath !== $targetPath && file_exists($oldPath)) {
        @unlink($oldPath);
    }
}

if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
    $respond(false, '头像上传失败，请检查目录权限');
}

// ---------- 写入数据库 ----------
$stmt = $db->prepare("UPDATE users SET avatar = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
if (!$stmt->execute([$filename, $user['id']])) {
    @unlink($targetPath);
    $respond(false, '头像信息更新失败');
}

$_SESSION['user']['avatar'] = $filename;
$avatarUrl = '../uploads/avatars/' . $filename;
$respond(true, '头像上传成功', ['avatarUrl' => $avatarUrl]);