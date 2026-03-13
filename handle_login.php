<?php
/**
 * handle_login.php — 登录处理
 *
 * 安全策略：
 *  · 失败累计 ≥ 3 次（当前 Session）→ 强制图形验证码
 *  · 失败累计 ≥ 5 次（数据库记录） → 账号锁定 10 分钟
 *  · 登录成功 → 清除失败计数 & 解除锁定
 */
session_start();
require_once 'include/Db.php';
require_once 'admin/admin_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// ── 常量 ──────────────────────────────────────────────────
const CAPTCHA_THRESHOLD = 3;   // 失败多少次后要求图形验证码
const LOCK_THRESHOLD    = 5;   // 失败多少次后锁定账号
const LOCK_MINUTES      = 10;  // 锁定时长（分钟）

// ── 当前 Session 失败次数 ──────────────────────────────────
$sessionFails = (int)($_SESSION['login_fail_count'] ?? 0);

// ── 辅助：记录一次失败并跳转 ──────────────────────────────
function failAndRedirect(string $msg): never
{
    $_SESSION['login_fail_count'] = (int)($_SESSION['login_fail_count'] ?? 0) + 1;
    $_SESSION['login_error']      = $msg;
    header('Location: login.php');
    exit;
}

// ── Step 1：如果失败次数已达阈值，先校验图形验证码 ────────
if ($sessionFails >= CAPTCHA_THRESHOLD) {
    $inputCaptcha  = strtoupper(trim($_POST['captcha_input'] ?? ''));
    $sessionCaptcha = strtoupper(trim($_SESSION['captcha_code'] ?? ''));

    // 无论成功与否，用后立即销毁防重放
    unset($_SESSION['captcha_code']);

    if ($inputCaptcha === '' || $inputCaptcha !== $sessionCaptcha) {
        // 验证码错误：增加失败次数但 **不** 增加 DB 计数（账号尚未确认）
        $_SESSION['login_fail_count'] = $sessionFails + 1;
        $_SESSION['login_error']      = '图形验证码错误，请重新输入';
        header('Location: login.php');
        exit;
    }
}

// ── Step 2：基本字段校验 ───────────────────────────────────
$loginId  = trim($_POST['login_id'] ?? '');
$password = $_POST['password'] ?? '';

if ($loginId === '' || $password === '') {
    failAndRedirect('请输入账号和密码');
}

// ── Step 3：查询用户 ───────────────────────────────────────
try {
    $db   = Db::getInstance();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$loginId, $loginId]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    $_SESSION['login_error'] = '登录失败，请稍后重试';
    header('Location: login.php');
    exit;
}

if (!$user) {
    // 用户不存在：不暴露细节，同样累计 Session 失败
    failAndRedirect('账号或密码错误');
}

// ── Step 4：检查账号状态（管理员冻结/封禁）────────────────
$adminStatus = checkUserStatus($user['id']);
if ($adminStatus === 'frozen' || $adminStatus === 'banned') {
    // 管理员级别的冻结，不重置计数
    $_SESSION['login_error'] = '账号已被冻结，如有疑问请联系管理员';
    header('Location: login.php');
    exit;
}

// ── Step 5：检查登录失败锁定（来自 login_attempts 表）────
try {
    $stmt = $db->prepare(
        "SELECT attempts, locked_until FROM login_attempts WHERE user_id = ?"
    );
    $stmt->execute([$user['id']]);
    $attemptRow = $stmt->fetch();
} catch (PDOException $e) {
    $attemptRow = null; // 表可能尚未创建，降级处理
}

if ($attemptRow && $attemptRow['locked_until'] !== null) {
    $lockedUntilTs = strtotime($attemptRow['locked_until']);
    if ($lockedUntilTs > time()) {
        $remainSecs = $lockedUntilTs - time();
        $remainMins = ceil($remainSecs / 60);
        $_SESSION['login_error'] = "账号已被临时锁定，请 {$remainMins} 分钟后再试";
        header('Location: login.php');
        exit;
    }
    // 锁定已过期：重置记录
    try {
        $db->prepare("DELETE FROM login_attempts WHERE user_id = ?")->execute([$user['id']]);
    } catch (PDOException $e) { /* 忽略 */ }
    $attemptRow = null;
}

// ── Step 6：验证密码 ───────────────────────────────────────
if (!password_verify($password, $user['password_hash'])) {
    // 密码错误：同时累计 Session 次数 和 DB 次数
    $_SESSION['login_fail_count'] = $sessionFails + 1;

    try {
        // Upsert login_attempts
        $db->prepare(
            "INSERT INTO login_attempts (user_id, attempts)
             VALUES (?, 1)
             ON DUPLICATE KEY UPDATE
               attempts    = attempts + 1,
               locked_until = IF(attempts + 1 >= ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), NULL),
               updated_at  = NOW()"
        )->execute([$user['id'], LOCK_THRESHOLD, LOCK_MINUTES]);

        // 重新读取，判断是否刚触发锁定
        $stmt = $db->prepare(
            "SELECT attempts, locked_until FROM login_attempts WHERE user_id = ?"
        );
        $stmt->execute([$user['id']]);
        $newRow = $stmt->fetch();

        if ($newRow && $newRow['locked_until'] !== null) {
            $_SESSION['login_error'] =
                "密码错误次数过多，账号已被锁定 " . LOCK_MINUTES . " 分钟";
            header('Location: login.php');
            exit;
        }
    } catch (PDOException $e) {
        // login_attempts 表不存在时降级，仅依赖 Session 计数
    }

    $remainAttempts = LOCK_THRESHOLD - (int)(($newRow['attempts'] ?? 1));
    if ($remainAttempts > 0) {
        $_SESSION['login_error'] =
            "账号或密码错误（还可尝试 {$remainAttempts} 次）";
    } else {
        $_SESSION['login_error'] = '账号或密码错误';
    }
    header('Location: login.php');
    exit;
}

// ── Step 7：登录成功 ───────────────────────────────────────
// 清除失败计数
unset($_SESSION['login_fail_count'], $_SESSION['captcha_code']);

try {
    $db->prepare("DELETE FROM login_attempts WHERE user_id = ?")->execute([$user['id']]);
} catch (PDOException $e) { /* 忽略 */ }

// 更新最后登录时间
try {
    $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
} catch (PDOException $e) { /* 忽略 */ }

// 写入 Session
$_SESSION['user_logged_in'] = true;
$_SESSION['user'] = [
    'id'       => $user['id'],
    'username' => $user['username'],
    'nickname' => $user['nickname'],
    'email'    => $user['email'],
    'role'     => $user['role'],
];

// ── 记住我 Cookie ──────────────────────────────────────────
if (isset($_POST['remember_me']) && $_POST['remember_me'] === 'on') {
    $token   = bin2hex(random_bytes(32));
    $expires = time() + (30 * 24 * 60 * 60);

    try {
        $db->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$user['id']]);
        $db->prepare(
            "INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)"
        )->execute([$user['id'], $token, date('Y-m-d H:i:s', $expires)]);

        setcookie('remember_me', $token, $expires, '/', '', false, true);
    } catch (PDOException $e) {
        error_log('记住我令牌保存失败: ' . $e->getMessage());
    }
}

header('Location: index.php');
exit;