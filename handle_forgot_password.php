<?php
/**
 * handle_forgot_password.php — 找回密码（模式自适应）
 *
 * 通道选择由后台 registration_mode 决定：
 *   phone — 只走短信通道（账号必须有手机号）
 *   email — 只走邮箱通道（账号必须有邮箱）
 *   both  — 优先邮箱，无邮箱时降级短信
 *
 * 流程：
 *   1. 图形验证码校验
 *   2. 按用户名 / 手机号 / 邮箱查找用户
 *   3. 按模式选择通道，发送验证码
 *   4. 存 session，跳转重置页
 */
session_start();
require_once __DIR__ . '/include/Db.php';
require_once __DIR__ . '/include/Config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot_password.php');
    exit;
}

// ── 加载注册模式 ───────────────────────────────────────────────
$cfg     = Config::getInstance();
$regMode = $cfg->get('registration_mode', 'phone');
if (!in_array($regMode, ['phone', 'email', 'both'])) { $regMode = 'phone'; }

// ── Step 1：图形验证码校验 ─────────────────────────────────────
$inputCaptcha   = strtoupper(trim($_POST['captcha_input'] ?? ''));
$sessionCaptcha = strtoupper(trim($_SESSION['captcha_code'] ?? ''));
unset($_SESSION['captcha_code']);

if ($inputCaptcha === '' || $inputCaptcha !== $sessionCaptcha) {
    $_SESSION['forgot_error'] = '图形验证码错误，请重新输入';
    $_SESSION['forgot_input'] = trim($_POST['user_identifier'] ?? '');
    header('Location: forgot_password.php');
    exit;
}

// ── Step 2：查找用户 ───────────────────────────────────────────
$userIdentifier = trim($_POST['user_identifier'] ?? '');
if (empty($userIdentifier)) {
    $_SESSION['forgot_error'] = '请输入账号标识';
    header('Location: forgot_password.php');
    exit;
}

try {
    $db   = Db::getInstance();
    $user = resolveUserForReset($db, $userIdentifier);
} catch (PDOException $e) {
    $_SESSION['forgot_error'] = '系统错误，请稍后重试';
    header('Location: forgot_password.php');
    exit;
}

// 用户不存在：模糊提示，不泄露账号是否存在
if (!$user) {
    $_SESSION['forgot_success'] = '如果该账号存在且已绑定联系方式，验证码将在短时间内发送';
    header('Location: forgot_password.php');
    exit;
}

$hasEmail = !empty($user['email']);
$hasPhone = !empty($user['phone']);

// ── Step 3：根据模式选择通道 ───────────────────────────────────
// phone 模式：只走短信
// email 模式：只走邮箱
// both  模式：优先邮箱，无邮箱降级短信

$useEmail = false;
$usePhone = false;

if ($regMode === 'email') {
    if (!$hasEmail) {
        $_SESSION['forgot_error'] = '该账号未绑定邮箱，无法自助重置密码，请联系管理员';
        header('Location: forgot_password.php');
        exit;
    }
    $useEmail = true;
} elseif ($regMode === 'phone') {
    if (!$hasPhone) {
        $_SESSION['forgot_error'] = '该账号未绑定手机号，无法自助重置密码，请联系管理员';
        header('Location: forgot_password.php');
        exit;
    }
    $usePhone = true;
} else {
    // both 模式：优先邮箱
    if ($hasEmail) {
        $useEmail = true;
    } elseif ($hasPhone) {
        $usePhone = true;
    } else {
        $_SESSION['forgot_error'] = '该账号未绑定手机号或邮箱，无法自助重置密码，请联系管理员';
        header('Location: forgot_password.php');
        exit;
    }
}

// ══════════════════════════════════════════════════════════════
//  通道 A：邮箱
// ══════════════════════════════════════════════════════════════
if ($useEmail) {
    $email = $user['email'];

    // 频率限制：同邮箱 60 秒一次
    try {
        $rStmt = $db->prepare("
            SELECT sent_at FROM email_verification
            WHERE email = ? AND verified = 0
            ORDER BY sent_at DESC LIMIT 1
        ");
        $rStmt->execute([$email]);
        $lastRow = $rStmt->fetch();
        if ($lastRow) {
            $elapsed = time() - strtotime($lastRow['sent_at']);
            if ($elapsed < 60) {
                $_SESSION['forgot_error'] = '发送过于频繁，请 ' . (60 - $elapsed) . ' 秒后再试';
                $_SESSION['forgot_input'] = $userIdentifier;
                header('Location: forgot_password.php');
                exit;
            }
        }
    } catch (PDOException $e) { /* 降级，跳过 */ }

    // 生成 6 位验证码
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    // 写库（有效期 10 分钟）
    try {
        $db->prepare("
            INSERT INTO email_verification (email, code, expires_at, sent_at, verified)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW(), 0)
        ")->execute([$email, $code]);
    } catch (PDOException $e) {
        $_SESSION['forgot_error'] = '系统错误，请稍后重试';
        header('Location: forgot_password.php');
        exit;
    }

    // 发送邮件
    try {
        require_once __DIR__ . '/include/Mailer.php';
        $mailer = new Mailer();
        if (!$mailer->isEnabled()) {
            $db->prepare("DELETE FROM email_verification WHERE email = ? AND code = ?")
               ->execute([$email, $code]);
            $_SESSION['forgot_error'] = '邮件服务未配置，请联系管理员';
            header('Location: forgot_password.php');
            exit;
        }

        $siteName = $cfg->get('site_title', '本站');
        $subject  = "【{$siteName}】密码重置验证码";
        $masked   = maskEmailForDisplay($email);
        $body     = "<p>您正在重置 <strong>{$siteName}</strong> 账号的登录密码。</p>"
                  . "<p>验证码为：<strong style='font-size:1.4em;letter-spacing:4px'>{$code}</strong></p>"
                  . "<p>10 分钟内有效，请勿泄露给他人。若非本人操作请忽略此邮件。</p>";

        $mailer->send($email, $subject, $body);
    } catch (\Throwable $e) {
        $db->prepare("DELETE FROM email_verification WHERE email = ? AND code = ?")
           ->execute([$email, $code]);
        $_SESSION['forgot_error'] = '邮件发送失败：' . $e->getMessage();
        header('Location: forgot_password.php');
        exit;
    }

    $_SESSION['reset_user_id']        = $user['id'];
    $_SESSION['reset_method']         = 'email';
    $_SESSION['reset_email']          = $email;
    $_SESSION['reset_contact_masked'] = maskEmailForDisplay($email);

    header('Location: reset_password.php');
    exit;
}

// ══════════════════════════════════════════════════════════════
//  通道 B：短信
// ══════════════════════════════════════════════════════════════
if ($usePhone) {
    $phone = $user['phone'];

    // 频率限制：同手机号 60 秒一次
    try {
        $rStmt = $db->prepare("
            SELECT sent_at FROM phone_verification
            WHERE phone = ? AND verified = 0
            ORDER BY sent_at DESC LIMIT 1
        ");
        $rStmt->execute([$phone]);
        $lastRow = $rStmt->fetch();
        if ($lastRow) {
            $elapsed = time() - strtotime($lastRow['sent_at']);
            if ($elapsed < 60) {
                $_SESSION['forgot_error'] = '发送过于频繁，请 ' . (60 - $elapsed) . ' 秒后再试';
                $_SESSION['forgot_input'] = $userIdentifier;
                header('Location: forgot_password.php');
                exit;
            }
        }
    } catch (PDOException $e) { /* 降级，跳过 */ }

    try {
        require_once __DIR__ . '/include/AliSms.php';
        $sms    = AliSms::fromConfig();
        $result = $sms->sendCode($phone, 5);
    } catch (\Throwable $e) {
        $_SESSION['forgot_error'] = '短信服务异常，请稍后重试';
        header('Location: forgot_password.php');
        exit;
    }

    if (!$result['ok']) {
        $_SESSION['forgot_error'] = '短信发送失败：' . $result['msg'];
        $_SESSION['forgot_input'] = $userIdentifier;
        header('Location: forgot_password.php');
        exit;
    }

    try {
        $db->prepare("
            INSERT INTO phone_verification (phone, code, biz_id, expires_at, sent_at)
            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE), NOW())
        ")->execute([$phone, $result['verify_code'], $result['biz_id'] ?? null]);
    } catch (PDOException $e) {
        $_SESSION['forgot_error'] = '系统错误，请稍后重试';
        header('Location: forgot_password.php');
        exit;
    }

    $_SESSION['reset_user_id']        = $user['id'];
    $_SESSION['reset_method']         = 'sms';
    $_SESSION['reset_phone']          = $phone;
    $_SESSION['reset_contact_masked'] = substr($phone, 0, 3) . '****' . substr($phone, 7);

    header('Location: reset_password.php');
    exit;
}

// 理论上不应到达此处
$_SESSION['forgot_error'] = '系统配置异常，请联系管理员';
header('Location: forgot_password.php');
exit;

// ── 辅助：查找用户（同时取 email 字段）──────────────────────
function resolveUserForReset(PDO $db, string $id): mixed
{
    if (preg_match('/^\d{11}$/', $id)) {
        $s = $db->prepare("SELECT id, username, phone, email FROM users WHERE phone = ? LIMIT 1");
        $s->execute([$id]);
        $u = $s->fetch();
        if ($u) return $u;
        $s = $db->prepare("SELECT id, username, phone, email FROM users WHERE username = ? LIMIT 1");
        $s->execute([$id]);
        return $s->fetch();
    }
    if (strpos($id, '@') !== false) {
        $s = $db->prepare(
            "SELECT id, username, phone, email FROM users
              WHERE email = ? AND email IS NOT NULL AND email != '' LIMIT 1"
        );
        $s->execute([$id]);
        $u = $s->fetch();
        if ($u) return $u;
        $s = $db->prepare("SELECT id, username, phone, email FROM users WHERE username = ? LIMIT 1");
        $s->execute([$id]);
        return $s->fetch();
    }
    $s = $db->prepare("SELECT id, username, phone, email FROM users WHERE username = ? LIMIT 1");
    $s->execute([$id]);
    return $s->fetch();
}

// ── 辅助：遮盖邮箱本地部分 ──────────────────────────────────
function maskEmailForDisplay(string $email): string
{
    [$local, $domain] = explode('@', $email, 2);
    $len = strlen($local);
    if ($len <= 2) return str_repeat('*', $len) . '@' . $domain;
    return substr($local, 0, 1)
         . str_repeat('*', min($len - 2, 4))
         . substr($local, -1)
         . '@' . $domain;
}