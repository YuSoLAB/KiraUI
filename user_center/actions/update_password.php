<?php
/**
 * 修改密码
 * 依赖：$db、$user、$_POST
 */

$tab             = $_POST['active_tab'] ?? 'security';
$newPassword     = $_POST['new_password']     ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if ($newPassword !== $confirmPassword) {
    $_SESSION['error'] = '两次输入的密码不一致';
} elseif (strlen($newPassword) < 6) {
    $_SESSION['error'] = '密码长度至少6位';
} else {
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    if ($stmt->execute([$passwordHash, $user['id']])) {
        $_SESSION['message'] = '密码更新成功';
    } else {
        $_SESSION['error'] = '密码更新失败';
    }
}

header("Location: index.php?tab=$tab");
exit;
