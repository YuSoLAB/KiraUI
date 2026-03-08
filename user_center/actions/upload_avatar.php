<?php
/**
 * 上传头像
 * 支持普通 POST 与 XMLHttpRequest (AJAX) 两种方式
 * 依赖：$db、$user、$isBanned、$_FILES、$_POST
 */

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

// 验证扩展名
$fileInfo  = pathinfo($_FILES['avatar']['name']);
$extension = strtolower($fileInfo['extension'] ?? '');
$allowed   = ['jpg', 'jpeg', 'png', 'gif'];

if (!in_array($extension, $allowed, true)) {
    $respond(false, '只允许上传 jpg、jpeg、png、gif 格式的图片');
}

// 确保目录存在（使用绝对路径写文件，URL 路径保持相对）
$uploadDir = __DIR__ . '/../../uploads/avatars/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename   = $user['id'] . '.' . $extension;
$targetPath = $uploadDir . $filename;

if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
    $respond(false, '头像上传失败');
}

$stmt = $db->prepare("UPDATE users SET avatar = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
if (!$stmt->execute([$filename, $user['id']])) {
    @unlink($targetPath);
    $respond(false, '头像信息更新失败');
}

$_SESSION['user']['avatar'] = $filename;
$avatarUrl = '../uploads/avatars/' . $filename;
$respond(true, '头像上传成功', ['avatarUrl' => $avatarUrl]);
