<?php
/**
 * actions/bind_phone.php
 * AJAX 端点：手机号绑定
 *   action=send_phone_code   → 发送短信验证码
 *   action=verify_phone_bind → 核验验证码并写入 users.phone
 *
 * 依赖：$db、$user（由 index.php 在 POST 路由前已完成 init.php 初始化）
 */

// ── 严格限定仅 AJAX 调用 ────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');

$subAction = $_POST['sub_action'] ?? '';

// ── 公共：手机号格式校验 ────────────────────────────────────────────
$phone = trim($_POST['phone'] ?? '');
if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
    echo json_encode(['ok' => false, 'msg' => '手机号格式不正确']);
    exit;
}

// ══════════════════════════════════════════════════════════════════
//  子动作 1：发送验证码
// ══════════════════════════════════════════════════════════════════
if ($subAction === 'send_code') {

    // ── 图形验证码校验（发送前必须通过） ────────────────────────────
    $inputCaptcha   = strtoupper(trim($_POST['captcha_input'] ?? ''));
    $sessionCaptcha = strtoupper(trim($_SESSION['captcha_code'] ?? ''));
    unset($_SESSION['captcha_code']); // 立即销毁，防重放
    if ($inputCaptcha === '' || $inputCaptcha !== $sessionCaptcha) {
        echo json_encode(['ok' => false, 'msg' => '图形验证码错误，请刷新后重试']);
        exit;
    }

    // 频率限制：同手机号 60 秒内只能发一次
    $rateStmt = $db->prepare("
        SELECT sent_at FROM phone_verification
        WHERE phone = ? AND verified = 0
        ORDER BY sent_at DESC
        LIMIT 1
    ");
    $rateStmt->execute([$phone]);
    $lastRow = $rateStmt->fetch();
    if ($lastRow) {
        $elapsed = time() - strtotime($lastRow['sent_at']);
        if ($elapsed < 60) {
            echo json_encode(['ok' => false, 'msg' => '操作太频繁，请 ' . (60 - $elapsed) . ' 秒后重试']);
            exit;
        }
    }

    // 检查该手机号是否已被其他账号绑定
    $bindStmt = $db->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
    $bindStmt->execute([$phone, $user['id']]);
    if ($bindStmt->fetch()) {
        echo json_encode(['ok' => false, 'msg' => '该手机号已被其他账号绑定']);
        exit;
    }

    // 调用阿里云发送短信
    try {
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', dirname(dirname(__FILE__)));
        }
        require_once ROOT_DIR . '/include/Config.php';
        require_once ROOT_DIR . '/include/AliSms.php';
        $sms    = AliSms::fromConfig();
        $result = $sms->sendCode($phone, 5);
    } catch (\Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => '短信服务加载失败：' . $e->getMessage()]);
        exit;
    }

    if (!$result['ok']) {
        echo json_encode(['ok' => false, 'msg' => $result['msg']]);
        exit;
    }

    // 写入验证码记录（有效期 5 分钟）
    $insStmt = $db->prepare("
        INSERT INTO phone_verification (phone, code, biz_id, expires_at)
        VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))
    ");
    $insStmt->execute([$phone, $result['verify_code'], $result['biz_id']]);

    echo json_encode(['ok' => true, 'msg' => '验证码已发送，请在 5 分钟内完成验证']);
    exit;
}

// ══════════════════════════════════════════════════════════════════
//  子动作 2：核验验证码并绑定手机号
// ══════════════════════════════════════════════════════════════════
if ($subAction === 'verify_bind') {

    $code = trim($_POST['code'] ?? '');
    if (!preg_match('/^\d{6}$/', $code)) {
        echo json_encode(['ok' => false, 'msg' => '验证码格式错误（应为 6 位数字）']);
        exit;
    }

    // 查找最新一条未使用且未过期的验证码
    $chkStmt = $db->prepare("
        SELECT id, code FROM phone_verification
        WHERE phone = ? AND verified = 0 AND expires_at > NOW()
        ORDER BY sent_at DESC
        LIMIT 1
    ");
    $chkStmt->execute([$phone]);
    $row = $chkStmt->fetch();

    if (!$row) {
        echo json_encode(['ok' => false, 'msg' => '验证码已过期，请重新发送']);
        exit;
    }

    // 本地比对（阿里云 returnVerifyCode=true 时已将明文验证码返回并存储）
    if ($row['code'] !== $code) {
        echo json_encode(['ok' => false, 'msg' => '验证码不正确，请重新输入']);
        exit;
    }

    // 再次检查手机号是否已被其他账号占用（并发保护）
    $bindStmt = $db->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
    $bindStmt->execute([$phone, $user['id']]);
    if ($bindStmt->fetch()) {
        echo json_encode(['ok' => false, 'msg' => '该手机号已被其他账号绑定']);
        exit;
    }

    // 标记验证码已使用
    $db->prepare("UPDATE phone_verification SET verified = 1 WHERE id = ?")
       ->execute([$row['id']]);

    // 更新用户手机号
    $upStmt = $db->prepare("
        UPDATE users SET phone = ?, phone_verified = 1, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $upStmt->execute([$phone, $user['id']]);

    // 同步到 session，避免下次刷新前显示旧数据
    $_SESSION['user']['phone']          = $phone;
    $_SESSION['user']['phone_verified'] = 1;

    echo json_encode(['ok' => true, 'msg' => '手机号绑定成功', 'masked' => maskPhone($phone)]);
    exit;
}

echo json_encode(['ok' => false, 'msg' => '未知操作']);
exit;

// ── 工具函数 ────────────────────────────────────────────────────────
function maskPhone(string $phone): string {
    return substr($phone, 0, 3) . '****' . substr($phone, 7);
}