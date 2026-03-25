<?php
/**
 * send_sms_verify.php — 公开 AJAX 端点
 *
 * action=send   : 向手机号发送短信验证码
 * action=check  : 核验用户输入的验证码（前端可选调用，handle_register 也会再次核验）
 *
 * 调用方：register.php 前端 JS
 */
session_start();
ob_start();

if (!defined('ROOT_DIR')) { define('ROOT_DIR', dirname(__FILE__)); }

header('Content-Type: application/json; charset=utf-8');

require_once ROOT_DIR . '/include/Db.php';
require_once ROOT_DIR . '/include/Config.php';
require_once ROOT_DIR . '/include/AliSms.php';

ob_clean();

$action = $_POST['action'] ?? '';
$phone  = trim($_POST['phone'] ?? '');

// ── 手机号格式校验（国内11位） ─────────────────────────────────
function validatePhone(string $p): bool {
    return (bool) preg_match('/^1[3-9]\d{9}$/', $p);
}

// ── 频控：同号码 60 秒内只能发一次 ────────────────────────────
function checkRateLimit(PDO $db, string $phone): bool {
    $stmt = $db->prepare(
        "SELECT sent_at FROM phone_verification
          WHERE phone = ? AND verified = 0
          ORDER BY sent_at DESC LIMIT 1"
    );
    $stmt->execute([$phone]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return true;
    return (time() - strtotime($row['sent_at'])) >= 60;
}

// ═══════════════════════════════════════════════════════════════
// action = send
// ═══════════════════════════════════════════════════════════════
if ($action === 'send') {

    // ── Step 1：图形验证码校验（最优先，防恶意触发短信） ────────
    $inputCaptcha   = strtoupper(trim($_POST['captcha_input'] ?? ''));
    $sessionCaptcha = strtoupper(trim($_SESSION['captcha_code'] ?? ''));
    unset($_SESSION['captcha_code']); // 立即销毁，防重放
    if ($inputCaptcha === '' || $inputCaptcha !== $sessionCaptcha) {
        echo json_encode(['ok' => false, 'msg' => '图形验证码错误，请重新输入']);
        exit;
    }

    // ── Step 2：手机号格式校验 ───────────────────────────────────
    if (!validatePhone($phone)) {
        echo json_encode(['ok' => false, 'msg' => '手机号格式不正确']);
        exit;
    }

    try {
        $db = Db::getInstance();

        // ── Step 3：手机号唯一性检查（已注册则无需发送） ────────
        $uStmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
        $uStmt->execute([$phone]);
        if ($uStmt->fetch()) {
            echo json_encode(['ok' => false, 'msg' => '该手机号已被注册，请直接登录或找回密码']);
            exit;
        }

        // 频控检查
        if (!checkRateLimit($db, $phone)) {
            echo json_encode(['ok' => false, 'msg' => '发送过于频繁，请 60 秒后再试']);
            exit;
        }

        // 调用阿里云发送
        $sms    = AliSms::fromConfig();
        $result = $sms->sendCode($phone, 5); // 5 分钟有效

        if (!$result['ok']) {
            echo json_encode(['ok' => false, 'msg' => $result['msg']]);
            exit;
        }

        // 记录到数据库（含明文验证码，用于 handle_register 二次核验）
        $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        $db->prepare(
            "INSERT INTO phone_verification (phone, code, biz_id, expires_at, verified)
             VALUES (?, ?, ?, ?, 0)"
        )->execute([$phone, $result['verify_code'], $result['biz_id'], $expiresAt]);

        // Session 里也存一份，注册提交时做快速比对
        $_SESSION['sms_phone']      = $phone;
        $_SESSION['sms_expires_at'] = $expiresAt;

        echo json_encode(['ok' => true, 'msg' => '验证码已发送，5 分钟内有效']);

    } catch (\Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => '服务暂时不可用：' . $e->getMessage()]);
    }
    exit;
}

// ═══════════════════════════════════════════════════════════════
// action = check（可选，供前端即时提示）
// ═══════════════════════════════════════════════════════════════
if ($action === 'check') {
    $code = trim($_POST['code'] ?? '');

    if (!validatePhone($phone) || strlen($code) !== 6) {
        echo json_encode(['ok' => false, 'msg' => '参数不完整']);
        exit;
    }

    try {
        $db   = Db::getInstance();
        $stmt = $db->prepare(
            "SELECT id FROM phone_verification
              WHERE phone = ? AND code = ? AND expires_at > NOW() AND verified = 0
              ORDER BY sent_at DESC LIMIT 1"
        );
        $stmt->execute([$phone, $code]);
        if ($stmt->fetch()) {
            echo json_encode(['ok' => true, 'msg' => '验证码正确']);
        } else {
            echo json_encode(['ok' => false, 'msg' => '验证码错误或已过期']);
        }
    } catch (\Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => '核验失败，请稍后重试']);
    }
    exit;
}

echo json_encode(['ok' => false, 'msg' => '无效的操作']);