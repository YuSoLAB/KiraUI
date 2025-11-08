<?php
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$message = '';
$error = '';

if (!empty($user['avatar'])) {
    $avatarPath = 'uploads/avatars/' . $user['avatar'];
    if (file_exists($avatarPath)) {
        unlink($avatarPath);
    }
    
    require_once 'include/Db.php';
    $db = Db::getInstance();
    $stmt = $db->prepare("UPDATE users SET avatar = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    if ($stmt->execute([$user['id']])) {
        $_SESSION['user']['avatar'] = null;
        $message = '头像已删除';
    } else {
        $error = '删除头像失败';
    }
} else {
    $error = '没有上传的头像';
}

// 重定向回用户中心
$_SESSION['user_center_message'] = $message;
$_SESSION['user_center_error'] = $error;
header('Location: user_center.php');
exit;