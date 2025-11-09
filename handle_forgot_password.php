<?php
session_start();
require_once 'include/Db.php';
require_once 'include/Config.php';
require_once 'include/Mailer.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot_password.php');
    exit;
}
$userIdentifier = trim($_POST['user_identifier'] ?? '');
if (empty($userIdentifier)) {
    $_SESSION['forgot_error'] = '请输入用户名或邮箱';
    header('Location: forgot_password.php');
    exit;
}
try {
    $db = Db::getInstance();
    $config = Config::getInstance();
    $mailer = new Mailer();
    if (!$mailer->isEnabled()) {
        $_SESSION['forgot_error'] = '邮件服务未配置，请联系管理员';
        header('Location: forgot_password.php');
        exit;
    }
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$userIdentifier, $userIdentifier]);
    $user = $stmt->fetch();
    if (!$user) {
        $_SESSION['forgot_success'] = '如果该账号存在，重置密码的链接已发送到对应邮箱';
        header('Location: forgot_password.php');
        exit;
    }
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $stmt = $db->prepare("INSERT INTO password_reset (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$user['id'], $token, $expiresAt]);
    $resetLink = rtrim($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/') . "/reset_password.php?token=$token";
    $siteTitle = $config->get('site_title', '测试网站');
    $mailBody = <<<HTML
    <h2>密码重置请求 - $siteTitle</h2>
    <p>您收到这封邮件是因为有人请求重置您在 $siteTitle 的账号密码。</p>
    <p>请点击以下链接重置您的密码（链接24小时内有效）：</p>
    <p><a href="$resetLink">$resetLink</a></p>
    <p>如果您没有请求重置密码，请忽略此邮件。</p>
    HTML;
    $mailer->send($user['email'], "密码重置 - $siteTitle", $mailBody);
    $_SESSION['forgot_success'] = '如果该账号存在，重置密码的链接已发送到对应邮箱';
    header('Location: forgot_password.php');
    exit;
} catch (PDOException $e) {
    $_SESSION['forgot_error'] = '操作失败：' . $e->getMessage();
    header('Location: forgot_password.php');
    exit;
} catch (Exception $e) {
    $_SESSION['forgot_error'] = '邮件发送失败：' . $e->getMessage();
    header('Location: forgot_password.php');
    exit;
}