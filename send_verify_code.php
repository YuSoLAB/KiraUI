<?php
session_start();
require_once 'include/Db.php';
require_once 'include/Mailer.php';
require_once 'include/Config.php';
// 引入邮箱验证函数
require_once 'admin/admin_functions.php';  // 新增：引入域名检查函数所在文件

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '方法不允许']);
    exit;
}

$email = trim($_POST['email'] ?? '');

// 验证邮箱格式
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => '请输入有效的邮箱地址']);
    exit;
}

// 新增：检查邮箱域名是否允许注册
if (!isRegistrationEmailAllowed($email)) {
    echo json_encode(['success' => false, 'message' => '该邮箱域名不允许注册']);
    exit;
}

try {
    // 检查SMTP是否启用
    $mailer = new Mailer();
    if (!$mailer->isEnabled()) {
        echo json_encode(['success' => false, 'message' => '邮件服务未配置，请联系管理员']);
        exit;
    }

    $db = Db::getInstance();
    
    // 检查邮箱是否已被注册
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => '该邮箱已被注册']);
        exit;
    }
    
    // 生成6位数字验证码
    $code = mt_rand(100000, 999999);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+12 hours'));
    
    // 先删除该邮箱已有的验证码
    $stmt = $db->prepare("DELETE FROM email_verification WHERE email = ?");
    $stmt->execute([$email]);
    
    // 保存新验证码
    $stmt = $db->prepare("INSERT INTO email_verification (email, code, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$email, $code, $expiresAt]);
    
    // 发送验证码邮件
    $siteTitle = Config::getInstance()->get('site_title', '测试网站');
    $mailBody = <<<HTML
    <h2>邮箱验证 - $siteTitle</h2>
    <p>您的注册验证码是：</p>
    <p style="font-size: 24px; font-weight: bold; letter-spacing: 4px;">$code</p>
    <p>验证码12小时内有效，请勿泄露给他人。</p>
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