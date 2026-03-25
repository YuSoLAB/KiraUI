<?php
/**
 * send_verify_code.php — 发送邮箱注册验证码（AJAX 端点）
 *
 * POST 参数：
 *   email         — 目标邮箱
 *   captcha_input — 图形验证码（用于防刷，服务端校验 session）
 *
 * 响应：JSON { ok: bool, msg: string }
 */
session_start();
ob_start();
header('Content-Type: application/json; charset=utf-8');

require_once 'include/Db.php';
require_once 'include/Mailer.php';
require_once 'include/Config.php';
require_once 'admin/admin_functions.php';

ob_clean();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => '方法不允许']);
    exit;
}

// ── Step 1：图形验证码校验（防恶意触发邮件） ─────────────────
$inputCaptcha   = strtoupper(trim($_POST['captcha_input'] ?? ''));
$sessionCaptcha = strtoupper(trim($_SESSION['captcha_code'] ?? ''));
unset($_SESSION['captcha_code']); // 立即销毁，防重放

if ($inputCaptcha === '' || $inputCaptcha !== $sessionCaptcha) {
    echo json_encode(['ok' => false, 'msg' => '图形验证码错误，请重新输入']);
    exit;
}

// ── Step 2：邮箱格式校验 ──────────────────────────────────────
$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'msg' => '请输入有效的邮箱地址']);
    exit;
}

// ── Step 3：邮箱域名白名单（可选） ───────────────────────────
if (!isRegistrationEmailAllowed($email)) {
    echo json_encode(['ok' => false, 'msg' => '该邮箱域名不允许注册']);
    exit;
}

try {
    $mailer = new Mailer();
    if (!$mailer->isEnabled()) {
        echo json_encode(['ok' => false, 'msg' => '邮件服务未配置，请联系管理员']);
        exit;
    }

    $db = Db::getInstance();

    // ── Step 4：邮箱唯一性检查 ───────────────────────────────
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND email != ''");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['ok' => false, 'msg' => '该邮箱已被注册']);
        exit;
    }

    // ── Step 5：频率限制（同邮箱 60 秒一次） ─────────────────
    $rStmt = $db->prepare(
        "SELECT sent_at FROM email_verification
          WHERE email = ? AND verified = 0
          ORDER BY sent_at DESC LIMIT 1"
    );
    $rStmt->execute([$email]);
    $lastRow = $rStmt->fetch();
    if ($lastRow) {
        $elapsed = time() - strtotime($lastRow['sent_at']);
        if ($elapsed < 60) {
            echo json_encode(['ok' => false, 'msg' => '发送过于频繁，请 ' . (60 - $elapsed) . ' 秒后再试']);
            exit;
        }
    }

    // ── Step 6：生成验证码并写库 ─────────────────────────────
    $code      = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // 清理旧的未使用验证码
    $db->prepare("DELETE FROM email_verification WHERE email = ? AND verified = 0")->execute([$email]);
    $db->prepare(
        "INSERT INTO email_verification (email, code, expires_at, sent_at, verified) VALUES (?, ?, ?, NOW(), 0)"
    )->execute([$email, $code, $expiresAt]);

    // ── Step 7：发送邮件 ──────────────────────────────────────
    $siteTitle = Config::getInstance()->get('site_title', '测试网站');
    $mailBody  = <<<HTML
<div style="background-color:#f4f6f8;padding:30px 0;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;color:#333;">
    <div style="max-width:600px;margin:0 auto;background-color:#ffffff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.05);overflow:hidden;">
        <div style="background-color:#6c5dfb;padding:20px;text-align:center;">
            <h1 style="color:#ffffff;margin:0;font-size:24px;">{$siteTitle}</h1>
        </div>
        <div style="padding:40px 30px;text-align:center;">
            <h2 style="margin-top:0;color:#333;font-size:20px;">欢迎注册</h2>
            <p style="font-size:16px;line-height:1.6;color:#555;text-align:left;">
                您正在注册 <strong>{$siteTitle}</strong> 的账号，请使用以下验证码完成邮箱验证：
            </p>
            <div style="background-color:#f0f7ff;border:1px dashed #6c5dfb;border-radius:6px;padding:15px;margin:20px auto;display:inline-block;">
                <span style="font-size:32px;font-weight:bold;letter-spacing:8px;color:#6c5dfb;font-family:monospace;">{$code}</span>
            </div>
            <p style="font-size:14px;color:#999;margin-top:30px;">
                该验证码在 10 分钟内有效。<br>
                如果您未进行注册操作，请忽略此邮件。
            </p>
        </div>
    </div>
    <div style="text-align:center;margin-top:20px;font-size:12px;color:#999;">&copy; {$siteTitle} 团队</div>
</div>
HTML;

    $mailer->send($email, "注册验证码 - {$siteTitle}", $mailBody);
    echo json_encode(['ok' => true, 'msg' => '验证码已发送，10 分钟内有效']);
    exit;

} catch (PDOException $e) {
    error_log('send_verify_code DB error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => '数据库错误，请重试']);
    exit;
} catch (Exception $e) {
    error_log('send_verify_code mail error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => '邮件发送失败：' . $e->getMessage()]);
    exit;
}