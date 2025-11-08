<?php
session_start();
require_once 'include/Db.php';

// 验证请求方式
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// 获取表单数据
$token = $_POST['token'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// 验证数据
$errors = [];
if (empty($token)) {
    $errors[] = '无效的请求';
}
if (strlen($newPassword) < 6) {
    $errors[] = '密码长度至少6位';
}
if ($newPassword !== $confirmPassword) {
    $errors[] = '两次输入的密码不一致';
}

if (!empty($errors)) {
    $_SESSION['reset_error'] = implode('<br>', $errors);
    header('Location: reset_password.php?token=' . urlencode($token));
    exit;
}

try {
    $db = Db::getInstance();
    
    // 验证令牌
    $stmt = $db->prepare("SELECT user_id FROM password_reset 
                        WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $resetData = $stmt->fetch();
    
    if (!$resetData) {
        $_SESSION['reset_error'] = '无效的重置链接或链接已过期';
        header('Location: reset_password.php?token=' . urlencode($token));
        exit;
    }
    
    // 更新密码
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$passwordHash, $resetData['user_id']]);
    
    // 失效该令牌（防止重复使用）
    $stmt = $db->prepare("DELETE FROM password_reset WHERE token = ?");
    $stmt->execute([$token]);
    
    $_SESSION['reset_success'] = '密码已成功重置，请使用新密码登录';
    header('Location: reset_password.php?token=' . urlencode($token));
    exit;

} catch (PDOException $e) {
    $_SESSION['reset_error'] = '操作失败：' . $e->getMessage();
    header('Location: reset_password.php?token=' . urlencode($token));
    exit;
}