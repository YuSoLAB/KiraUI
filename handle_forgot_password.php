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
    $expiresAt = date('Y-m-d H:i:s', strtotime('+2 hours'));
    $stmt = $db->prepare("INSERT INTO password_reset (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$user['id'], $token, $expiresAt]);
    $resetLink = rtrim($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/') . "/reset_password.php?token=$token";
    $siteTitle = $config->get('site_title', '测试网站');
    $username = htmlspecialchars($user['username']);
    $mailBody = <<<HTML
    <div style="background-color: #f4f6f8; padding: 30px 0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333;">
        <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); overflow: hidden;">
            <div style="background-color: #4A90E2; padding: 20px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px;">$siteTitle</h1>
            </div>
            <div style="padding: 40px 30px;">
                <h2 style="margin-top: 0; color: #333; font-size: 20px;">你好，$username</h2>
                <p style="font-size: 16px; line-height: 1.6; color: #555;">
                    我们收到了重置您在 <strong>$siteTitle</strong> 账号密码的请求。
                </p>
                <p style="font-size: 16px; line-height: 1.6; color: #555;">
                    如果是您本人的操作，请点击下方的按钮设置新密码：
                </p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="$resetLink" style="background-color: #4A90E2; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px; display: inline-block;">重置我的密码</a>
                </div>
                
                <p style="font-size: 14px; color: #999; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                    链接有效期为 2 小时。<br>
                    如果您没有请求重置密码，请忽略此邮件，您的账户依然安全。
                </p>
                
                <div style="font-size: 12px; color: #aaa; margin-top: 10px;">
                    <p>如果按钮无法点击，请复制以下链接到浏览器打开：<br>$resetLink</p>
                </div>
            </div>
        </div>
        <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #999;">
            &copy; $siteTitle 安全中心
        </div>
    </div>
    HTML;

    $mailer->send($user['email'], "重置密码 - $siteTitle", $mailBody);
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