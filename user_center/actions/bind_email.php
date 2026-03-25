<?php
/**
 * actions/bind_email.php
 * AJAX 端点：邮箱绑定 / 更换（独立端点，可直接 POST）
 *   sub_action=send_code   → 发送邮箱验证码
 *   sub_action=verify_bind → 核验验证码并写入 users.email
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!defined('ROOT_DIR')) { define('ROOT_DIR', dirname(dirname(dirname(__FILE__)))); }

header('Content-Type: application/json; charset=utf-8');

// 必须已登录
if (empty($_SESSION['user_logged_in']) || empty($_SESSION['user']['id'])) {
    echo json_encode(['ok' => false, 'msg' => '请先登录']);
    exit;
}

require_once dirname(__DIR__, 2) . '/include/Db.php';
$db   = \Db::getInstance();
$user = $_SESSION['user'];

$subAction = $_POST['sub_action'] ?? '';

// ── 公共：邮箱格式校验 ──────────────────────────────────────
$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'msg' => '邮箱格式不正确']);
    exit;
}

// ══════════════════════════════════════════════════════════════════
//  子动作 1：发送验证码
// ══════════════════════════════════════════════════════════════════
if ($subAction === 'send_code') {

    // 频率限制：同邮箱 60 秒内只能发一次
    $rateStmt = $db->prepare("
        SELECT sent_at FROM email_verification
        WHERE email = ? AND verified = 0
        ORDER BY sent_at DESC
        LIMIT 1
    ");
    $rateStmt->execute([$email]);
    $lastRow = $rateStmt->fetch();
    if ($lastRow) {
        $elapsed = time() - strtotime($lastRow['sent_at']);
        if ($elapsed < 60) {
            echo json_encode(['ok' => false, 'msg' => '操作太频繁，请 ' . (60 - $elapsed) . ' 秒后重试']);
            exit;
        }
    }

    // 检查该邮箱是否已被其他账号绑定
    $bindStmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $bindStmt->execute([$email, $user['id']]);
    if ($bindStmt->fetch()) {
        echo json_encode(['ok' => false, 'msg' => '该邮箱已被其他账号绑定']);
        exit;
    }

    // 生成 6 位数字验证码
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    // 写入验证码记录（有效期 10 分钟）
    $insStmt = $db->prepare("
        INSERT INTO email_verification (email, code, expires_at, sent_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW())
    ");
    $insStmt->execute([$email, $code]);

    // 发送邮件
    try {
        require_once dirname(__DIR__, 2) . '/include/Config.php';
        require_once dirname(__DIR__, 2) . '/include/Mailer.php';

        $mailer = new \Mailer();
        if (!$mailer->isEnabled()) {
            $db->prepare("DELETE FROM email_verification WHERE email = ? AND code = ?")
               ->execute([$email, $code]);
            echo json_encode(['ok' => false, 'msg' => '邮件服务未配置，请联系管理员']);
            exit;
        }

        $siteName = \Config::getInstance()->get('site_title', '本站');
        $subject  = "【{$siteName}】邮箱绑定验证码";
        $body     = "<p>您正在绑定 <strong>{$siteName}</strong> 账号邮箱。</p>"
                  . "<p>验证码为：<strong style='font-size:1.4em;letter-spacing:4px'>{$code}</strong></p>"
                  . "<p>10 分钟内有效，请勿泄露给他人。若非本人操作请忽略。</p>";

        $mailer->send($email, $subject, $body);
    } catch (\Throwable $e) {
        // 邮件服务异常时回滚验证码记录
        $db->prepare("DELETE FROM email_verification WHERE email = ? AND code = ?")
           ->execute([$email, $code]);
        echo json_encode(['ok' => false, 'msg' => '邮件发送失败：' . $e->getMessage()]);
        exit;
    }

    echo json_encode(['ok' => true, 'msg' => '验证码已发送，请在 10 分钟内完成验证']);
    exit;
}

// ══════════════════════════════════════════════════════════════════
//  子动作 2：核验验证码并绑定邮箱
// ══════════════════════════════════════════════════════════════════
if ($subAction === 'verify_bind') {

    $code = trim($_POST['code'] ?? '');
    if (!preg_match('/^\d{6}$/', $code)) {
        echo json_encode(['ok' => false, 'msg' => '验证码格式错误（应为 6 位数字）']);
        exit;
    }

    // 查找最新一条未使用且未过期的验证码
    $chkStmt = $db->prepare("
        SELECT id, code FROM email_verification
        WHERE email = ? AND verified = 0 AND expires_at > NOW()
        ORDER BY sent_at DESC
        LIMIT 1
    ");
    $chkStmt->execute([$email]);
    $row = $chkStmt->fetch();

    if (!$row) {
        echo json_encode(['ok' => false, 'msg' => '验证码已过期，请重新发送']);
        exit;
    }

    if ($row['code'] !== $code) {
        echo json_encode(['ok' => false, 'msg' => '验证码不正确，请重新输入']);
        exit;
    }

    // 再次检查邮箱是否已被其他账号占用（并发保护）
    $bindStmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $bindStmt->execute([$email, $user['id']]);
    if ($bindStmt->fetch()) {
        echo json_encode(['ok' => false, 'msg' => '该邮箱已被其他账号绑定']);
        exit;
    }

    // 标记验证码已使用
    $db->prepare("UPDATE email_verification SET verified = 1 WHERE id = ?")
       ->execute([$row['id']]);

    // 更新用户邮箱
    $upStmt = $db->prepare("
        UPDATE users SET email = ?, email_verified = 1, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $upStmt->execute([$email, $user['id']]);

    // 同步到 session
    $_SESSION['user']['email']          = $email;
    $_SESSION['user']['email_verified'] = 1;

    echo json_encode(['ok' => true, 'msg' => '邮箱绑定成功', 'masked' => maskEmailLocal($email)]);
    exit;
}

echo json_encode(['ok' => false, 'msg' => '未知操作']);
exit;

// ── 工具函数：遮盖邮箱本地部分 ────────────────────────────────
function maskEmailLocal(string $email): string {
    [$local, $domain] = explode('@', $email, 2);
    $len = strlen($local);
    if ($len <= 2) {
        return str_repeat('*', $len) . '@' . $domain;
    }
    return substr($local, 0, 1)
         . str_repeat('*', min($len - 2, 4))
         . substr($local, -1)
         . '@' . $domain;
}