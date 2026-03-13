<?php
/**
 * handle_register.php — 注册处理
 * 图形验证码为强制项，验证优先于其他字段校验
 */
session_start();
require_once 'include/Db.php';
require_once 'admin/admin_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

// ── 注册开关检测 ──────────────────────────────────────────────
if (!defined('ROOT_DIR')) { define('ROOT_DIR', dirname(__FILE__)); }
if (file_exists(ROOT_DIR . '/include/Config.php')) {
    require_once ROOT_DIR . '/include/Config.php';
    if (Config::getInstance()->get('registration_enabled', '1') === '0') {
        $_SESSION['register_error'] = '当前站点已关闭注册，如有疑问请联系管理员。';
        header('Location: register.php');
        exit;
    }
}

// ── Step 1：校验图形验证码（最优先，防止资源滥用）────────────
$inputCaptcha   = strtoupper(trim($_POST['captcha_input'] ?? ''));
$sessionCaptcha = strtoupper(trim($_SESSION['captcha_code'] ?? ''));

// 立即销毁，防止重放
unset($_SESSION['captcha_code']);

if ($inputCaptcha === '' || $inputCaptcha !== $sessionCaptcha) {
    $_SESSION['register_error'] = '图形验证码错误，请重新输入';
    header('Location: register.php');
    exit;
}

// ── Step 2：收集并校验其他字段 ───────────────────────────────
$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email']    ?? '');
$password = $_POST['password']      ?? '';
$errors   = [];

if (!preg_match('/^[a-zA-Z0-9_]{1,20}$/', $username)) {
    $errors[] = '用户名只能包含数字、字母和下划线，长度不超过20位';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = '请输入有效的邮箱地址';
} else {
    if (!isRegistrationEmailAllowed($email)) {
        $errors[] = '该邮箱域名不允许注册';
    }
}

if (strlen($password) < 6) {
    $errors[] = '密码长度至少6位';
}

// ── Step 3：校验邮箱验证码 ───────────────────────────────────
$verifyCode = trim($_POST['verify_code'] ?? '');
if (empty($verifyCode) || strlen($verifyCode) != 6) {
    $errors[] = '请输入有效的6位验证码';
} else {
    try {
        $db   = Db::getInstance();
        $stmt = $db->prepare(
            "SELECT * FROM email_verification WHERE email = ? AND code = ? AND expires_at > NOW()"
        );
        $stmt->execute([$email, $verifyCode]);
        if (!$stmt->fetch()) {
            $errors[] = '验证码无效或已过期';
        }
    } catch (PDOException $e) {
        $errors[] = '验证码验证失败，请重试';
    }
}

if (!empty($errors)) {
    $_SESSION['register_error'] = implode('<br>', $errors);
    header('Location: register.php');
    exit;
}

// ── Step 4：写库 ─────────────────────────────────────────────
try {
    $db = Db::getInstance();

    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        $_SESSION['register_error'] = '用户名已被注册';
        header('Location: register.php');
        exit;
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['register_error'] = '邮箱已被注册';
        header('Location: register.php');
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $db->prepare(
        "INSERT INTO users
             (username, nickname, email, password_hash, role, created_at, updated_at)
         VALUES (?, ?, ?, ?, 'user', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
    )->execute([$username, $username, $email, $passwordHash]);

    // 清理已使用的邮箱验证码
    $db->prepare("DELETE FROM email_verification WHERE email = ?")->execute([$email]);

    $_SESSION['register_success'] = '注册成功，请登录';
    header('Location: login.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['register_error'] = '注册失败：' . $e->getMessage();
    header('Location: register.php');
    exit;
}