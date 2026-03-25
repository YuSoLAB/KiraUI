<?php
/**
 * handle_reset_password.php — 重置密码：验证验证码 + 更新密码
 *
 * 支持邮箱（email_verification）和短信（phone_verification）两种通道，
 * 通过 session 中的 reset_method 区分。
 */
session_start();
require_once __DIR__ . '/include/Db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot_password.php');
    exit;
}

// ── 读取 session 凭据 ─────────────────────────────────────────
$userId = $_SESSION['reset_user_id'] ?? null;
$method = $_SESSION['reset_method']  ?? 'sms';   // 'email' | 'sms'
$email  = $_SESSION['reset_email']   ?? null;
$phone  = $_SESSION['reset_phone']   ?? null;

// 根据通道检查 session 完整性
$sessionValid = $userId && (
    ($method === 'email' && $email) ||
    ($method === 'sms'   && $phone)
);

if (!$sessionValid) {
    $_SESSION['reset_error'] = '会话已失效，请重新申请验证码';
    header('Location: reset_password.php');
    exit;
}

// ── 收集并校验表单 ────────────────────────────────────────────
$inputCode       = trim($_POST['sms_code']       ?? '');
$newPassword     = $_POST['new_password']         ?? '';
$confirmPassword = $_POST['confirm_password']     ?? '';
$errors          = [];

if (!preg_match('/^\d{6}$/', $inputCode)) {
    $errors[] = '请输入 6 位验证码';
}
if (strlen($newPassword) < 6) {
    $errors[] = '密码长度至少 6 位';
}
if ($newPassword !== $confirmPassword) {
    $errors[] = '两次输入的密码不一致';
}

if (!empty($errors)) {
    $_SESSION['reset_error'] = implode('<br>', $errors);
    header('Location: reset_password.php');
    exit;
}

// ── 验证验证码 ────────────────────────────────────────────────
try {
    $db = Db::getInstance();

    if ($method === 'email') {
        $stmt = $db->prepare("
            SELECT id FROM email_verification
            WHERE email = ? AND code = ? AND verified = 0 AND expires_at > NOW()
            ORDER BY sent_at DESC LIMIT 1
        ");
        $stmt->execute([$email, $inputCode]);
    } else {
        $stmt = $db->prepare("
            SELECT id FROM phone_verification
            WHERE phone = ? AND code = ? AND verified = 0 AND expires_at > NOW()
            ORDER BY sent_at DESC LIMIT 1
        ");
        $stmt->execute([$phone, $inputCode]);
    }

    $row = $stmt->fetch();
} catch (PDOException $e) {
    $_SESSION['reset_error'] = '验证失败，请稍后重试';
    header('Location: reset_password.php');
    exit;
}

if (!$row) {
    $_SESSION['reset_error'] = '验证码无效或已过期，请重新获取';
    header('Location: reset_password.php');
    exit;
}

// ── 更新密码 + 标记验证码已使用 ──────────────────────────────
try {
    $db->beginTransaction();

    $db->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
       ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

    if ($method === 'email') {
        $db->prepare("UPDATE email_verification SET verified = 1 WHERE id = ?")
           ->execute([$row['id']]);
    } else {
        $db->prepare("UPDATE phone_verification SET verified = 1 WHERE id = ?")
           ->execute([$row['id']]);
    }

    // 清除旧的 token 表记录（兼容旧逻辑）
    try {
        $db->prepare("DELETE FROM password_reset WHERE user_id = ?")->execute([$userId]);
    } catch (\Throwable $e) { /* 表可能不存在，忽略 */ }

    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    $_SESSION['reset_error'] = '密码重置失败，请稍后重试';
    header('Location: reset_password.php');
    exit;
}

// ── 清除全部重置 session，跳转登录 ───────────────────────────
unset(
    $_SESSION['reset_user_id'],
    $_SESSION['reset_method'],
    $_SESSION['reset_email'],
    $_SESSION['reset_phone'],
    $_SESSION['reset_contact_masked']
);

$_SESSION['register_success'] = '密码已成功重置，请使用新密码登录';
header('Location: login.php');
exit;