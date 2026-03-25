<?php
/**
 * handle_register.php — 注册处理（模式自适应）
 *
 * 支持三种注册模式（由后台 registration_mode 配置控制）：
 *   phone — 仅手机号 + 短信验证码
 *   email — 仅邮箱   + 邮件验证码
 *   both  — 手机号 + 邮箱均需验证
 *
 * 安全：服务端以数据库中的 registration_mode 配置为准，
 *       前端传入的 reg_mode 字段仅作调试参考，不信任。
 */
session_start();
require_once 'include/Db.php';
require_once 'admin/admin_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

// ── 加载配置 ───────────────────────────────────────────────────
if (!defined('ROOT_DIR')) { define('ROOT_DIR', dirname(__FILE__)); }
require_once ROOT_DIR . '/include/Config.php';
$cfg     = Config::getInstance();
$regMode = $cfg->get('registration_mode', 'phone');
if (!in_array($regMode, ['phone', 'email', 'both'])) { $regMode = 'phone'; }

// ── 注册开关检测 ───────────────────────────────────────────────
if ($cfg->get('registration_enabled', '1') === '0') {
    $_SESSION['register_error'] = '当前站点已关闭注册，如有疑问请联系管理员。';
    header('Location: register.php');
    exit;
}

// ── Step 1：用户名 + 密码 ──────────────────────────────────────
$username = trim($_POST['username'] ?? '');
$password = $_POST['password']      ?? '';
$errors   = [];

if (!preg_match('/^[a-zA-Z0-9_]{1,20}$/', $username)) {
    $errors[] = '用户名只能包含数字、字母和下划线，长度不超过 20 位';
}
if (strlen($password) < 6) {
    $errors[] = '密码长度至少 6 位';
}

// ── Step 2：手机号 + 短信验证码（phone / both 模式） ───────────
$phone   = '';
$smsCode = '';
if ($regMode === 'phone' || $regMode === 'both') {
    $phone   = trim($_POST['phone']    ?? '');
    $smsCode = trim($_POST['sms_code'] ?? '');

    if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
        $errors[] = '请输入正确的 11 位手机号';
    }
    if (empty($smsCode) || strlen($smsCode) !== 6) {
        $errors[] = '请输入 6 位短信验证码';
    }
}

// ── Step 3：邮箱 + 邮件验证码（email / both 模式） ────────────
$email     = '';
$emailCode = '';
if ($regMode === 'email' || $regMode === 'both') {
    $email     = trim($_POST['email']      ?? '');
    $emailCode = trim($_POST['email_code'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '请输入有效的邮箱地址';
    }
    if (empty($emailCode) || strlen($emailCode) !== 6) {
        $errors[] = '请输入 6 位邮件验证码';
    }
}

if (!empty($errors)) {
    $_SESSION['register_error'] = implode('<br>', $errors);
    header('Location: register.php');
    exit;
}

// ── Step 4：数据库校验 ─────────────────────────────────────────
try {
    $db = Db::getInstance();

    // 4-A 短信验证码校验
    if ($regMode === 'phone' || $regMode === 'both') {
        $stmt = $db->prepare(
            "SELECT id FROM phone_verification
              WHERE phone = ? AND code = ? AND expires_at > NOW() AND verified = 0
              ORDER BY sent_at DESC LIMIT 1"
        );
        $stmt->execute([$phone, $smsCode]);
        if (!$stmt->fetch()) {
            $_SESSION['register_error'] = '短信验证码无效或已过期';
            header('Location: register.php');
            exit;
        }
    }

    // 4-B 邮件验证码校验
    if ($regMode === 'email' || $regMode === 'both') {
        $stmt = $db->prepare(
            "SELECT id FROM email_verification
              WHERE email = ? AND code = ? AND expires_at > NOW() AND verified = 0
              ORDER BY sent_at DESC LIMIT 1"
        );
        $stmt->execute([$email, $emailCode]);
        if (!$stmt->fetch()) {
            $_SESSION['register_error'] = '邮件验证码无效或已过期';
            header('Location: register.php');
            exit;
        }
    }

    // 4-C 手机号唯一性
    if ($phone !== '') {
        $stmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $_SESSION['register_error'] = '该手机号已被注册，如忘记账号请联系管理员';
            header('Location: register.php');
            exit;
        }
    }

    // 4-D 邮箱唯一性
    if ($email !== '') {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND email != ''");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $_SESSION['register_error'] = '该邮箱已被注册';
            header('Location: register.php');
            exit;
        }
    }

    // 4-E 用户名唯一性
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        $_SESSION['register_error'] = '用户名已被注册';
        header('Location: register.php');
        exit;
    }

    // ── Step 5：写库 ──────────────────────────────────────────
    $passwordHash  = password_hash($password, PASSWORD_DEFAULT);
    $phoneVerified = ($regMode === 'phone' || $regMode === 'both') ? 1 : 0;

    $db->prepare(
        "INSERT INTO users
             (username, nickname, password_hash, phone, phone_verified, email, role, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, 'user', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
    )->execute([
        $username,
        $username,
        $passwordHash,
        $phone ?: null,
        $phoneVerified,
        $email ?: null,
    ]);

    // ── Step 6：标记验证码已使用 ─────────────────────────────
    if ($regMode === 'phone' || $regMode === 'both') {
        $db->prepare(
            "UPDATE phone_verification SET verified = 1
              WHERE phone = ? AND code = ? AND verified = 0"
        )->execute([$phone, $smsCode]);
    }
    if ($regMode === 'email' || $regMode === 'both') {
        $db->prepare(
            "UPDATE email_verification SET verified = 1
              WHERE email = ? AND code = ? AND verified = 0"
        )->execute([$email, $emailCode]);
    }

    $_SESSION['register_success'] = '注册成功，请登录';
    header('Location: login.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['register_error'] = '注册失败：' . $e->getMessage();
    header('Location: register.php');
    exit;
}