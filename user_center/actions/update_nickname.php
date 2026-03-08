<?php
/**
 * 修改昵称
 * 依赖：$db、$user、$isBanned、$_POST
 */

$tab = $_POST['active_tab'] ?? 'profile';

if ($isBanned) {
    $_SESSION['error'] = '您的账号已被封禁，无法修改个人信息';
    header("Location: index.php?tab=$tab");
    exit;
}

$newNickname = trim($_POST['nickname'] ?? '');

if ($newNickname === '' || mb_strlen($newNickname) > 50) {
    $_SESSION['error'] = '请输入有效的昵称（不超过50个字符）';
} else {
    $stmt = $db->prepare("UPDATE users SET nickname = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    if ($stmt->execute([$newNickname, $user['id']])) {
        $_SESSION['user']['nickname'] = $newNickname;
        $_SESSION['message'] = '昵称更新成功';
    } else {
        $_SESSION['error'] = '昵称更新失败';
    }
}

header("Location: index.php?tab=$tab");
exit;
