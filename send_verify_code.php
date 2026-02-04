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
    <div style="background-color: #f4f6f8; padding: 30px 0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333;">
        <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); overflow: hidden;">
            <div style="background-color: #4A90E2; padding: 20px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px;">$siteTitle</h1>
            </div>
            <div style="padding: 40px 30px; text-align: center;">
                <h2 style="margin-top: 0; color: #333; font-size: 20px;">欢迎注册</h2>
                <p style="font-size: 16px; line-height: 1.6; color: #555; text-align: left;">
                    您正在注册 <strong>$siteTitle</strong> 的账号。为了保障您的账户安全，我们需要验证您的邮箱地址。
                </p>
                
                <p style="font-size: 16px; color: #555; margin-top: 20px;">您的注册验证码是：</p>
                
                <div style="background-color: #f0f7ff; border: 1px dashed #4A90E2; border-radius: 6px; padding: 15px; margin: 20px auto; display: inline-block;">
                    <span style="font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #4A90E2; font-family: monospace;">$code</span>
                </div>
                
                <p style="font-size: 14px; color: #999; margin-top: 30px;">
                    该验证码在 10 分钟内有效。<br>
                    如果您未进行注册操作，请忽略此邮件。
                </p>
            </div>
        </div>
        <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #999;">
            &copy; $siteTitle 团队
        </div>
    </div>
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