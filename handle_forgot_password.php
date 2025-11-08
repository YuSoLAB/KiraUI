<?php
session_start();
require_once 'include/Db.php';
require_once 'include/Config.php';
require_once 'include/Mailer.php';

// 验证请求方式
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot_password.php');
    exit;
}

// 获取表单数据
$userIdentifier = trim($_POST['user_identifier'] ?? '');

// 简单验证
if (empty($userIdentifier)) {
    $_SESSION['forgot_error'] = '请输入用户名或邮箱';
    header('Location: forgot_password.php');
    exit;
}

try {
    $db = Db::getInstance();
    $config = Config::getInstance();
    
    // 检查SMTP是否启用
    $mailer = new Mailer();
    if (!$mailer->isEnabled()) {
        $_SESSION['forgot_error'] = '邮件服务未配置，请联系管理员';
        header('Location: forgot_password.php');
        exit;
    }
    
    // 查询用户（支持用户名或邮箱）
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$userIdentifier, $userIdentifier]);
    $user = $stmt->fetch();

    // 即使用户不存在也不提示，防止信息泄露
    if (!$user) {
        $_SESSION['forgot_success'] = '如果该账号存在，重置密码的链接已发送到对应邮箱';
        header('Location: forgot_password.php');
        exit;
    }

    // 生成重置令牌
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // 存储令牌
    $stmt = $db->prepare("INSERT INTO password_reset (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$user['id'], $token, $expiresAt]);
    
    // 构建重置链接
    $resetLink = rtrim($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/') . "/reset_password.php?token=$token";
    
    // 获取网站名称
    $siteTitle = $config->get('site_title', '测试网站');
    
    // 发送邮件
    $mailBody = <<<HTML
    <h2>密码重置请求 - $siteTitle</h2>
    <p>您收到这封邮件是因为有人请求重置您在 $siteTitle 的账号密码。</p>
    <p>请点击以下链接重置您的密码（链接24小时内有效）：</p>
    <p><a href="$resetLink">$resetLink</a></p>
    <p>如果您没有请求重置密码，请忽略此邮件。</p>
    HTML;
    
    $mailer->send($user['email'], "密码重置 - $siteTitle", $mailBody);
    
    // 提示信息（不泄露用户是否存在）
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