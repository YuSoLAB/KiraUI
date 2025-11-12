<?php
session_start();
require_once 'include/Db.php';
require_once 'include/Mailer.php';
require_once 'include/Config.php';
require_once 'admin/admin_functions.php';  
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '方法不允许']);
    exit;
}
$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => '请输入有效的邮箱地址']);
    exit;
}
if (!isRegistrationEmailAllowed($email)) {
    echo json_encode(['success' => false, 'message' => '该邮箱域名不允许注册']);
    exit;
}
try {
    $mailer = new Mailer();
    if (!$mailer->isEnabled()) {
        echo json_encode(['success' => false, 'message' => '邮件服务未配置，请联系管理员']);
        exit;
    }
    $db = Db::getInstance();
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => '该邮箱已被注册']);
        exit;
    }
    $code = mt_rand(100000, 999999);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $stmt = $db->prepare("DELETE FROM email_verification WHERE email = ?");
    $stmt->execute([$email]);
    $stmt = $db->prepare("INSERT INTO email_verification (email, code, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$email, $code, $expiresAt]);
    $siteTitle = Config::getInstance()->get('site_title', '测试网站');
    $mailBody = <<<HTML
    <h2>邮箱验证 - $siteTitle</h2>
    <p>您的注册验证码是：</p>
    <p style="font-size: 24px; font-weight: bold; letter-spacing: 4px;">$code</p>
    <p>验证码10分钟内有效，请勿泄露给他人。</p>
    HTML;
    $mailer->send($email, "注册验证码 - $siteTitle", $mailBody);
    echo json_encode(['success' => true, 'message' => '验证码已发送']);
    exit;
} catch (PDOException $e) {
    error_log("数据库错误: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '数据库错误，请重试']);
    exit;
} catch (Exception $e) {
    error_log("邮件发送失败: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '邮件发送失败：' . $e->getMessage()]);
    exit;
}