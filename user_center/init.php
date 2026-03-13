<?php
/**
 * 初始化模块
 * 负责：自动登录、Session启动、退出登录、权限校验、用户信息加载、登录历史记录
 */

define('APP_INIT', true);   // ← ip_helper.php / save_login_history.php 安全校验用

require_once __DIR__ . '/../auto_login.php';
autoLogin();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── 退出登录 ────────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    require __DIR__ . '/actions/logout.php';
    exit;
}

// ── 登录校验 ────────────────────────────────────────────────────────
require_once __DIR__ . '/../admin/admin_functions.php';

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

// ── 账号状态检查 ─────────────────────────────────────────────────────
$user   = $_SESSION['user'];
$status = checkUserStatus($user['id']);

$isBanned = false;
if ($status === 'frozen') {
    session_destroy();
    header('Location: ../login.php?error=' . urlencode('账号已被冻结'));
    exit;
} elseif ($status === 'banned') {
    $isBanned = true;
}

// ── 加载最新用户信息 ─────────────────────────────────────────────────
require_once __DIR__ . '/../include/Db.php';
$db   = Db::getInstance();
$stmt = $db->prepare("SELECT nickname, email, avatar FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$userInfo = $stmt->fetch();

$user['nickname'] = $userInfo['nickname'];
$user['email']    = $userInfo['email'];
$user['avatar']   = $userInfo['avatar'];
$_SESSION['user'] = $user;

// ── 构建头像 URL ──────────────────────────────────────────────────────
if (!empty($user['avatar'])) {
    $avatarUrl = '../uploads/avatars/' . $user['avatar'];
} elseif (preg_match('/^(\d+)@(qq\.com|vip\.qq\.com)$/', $user['email'], $matches)) {
    $avatarUrl = 'https://q1.qlogo.cn/g?b=qq&nk=' . $matches[1] . '&s=640';
} else {
    // 本地生成 SVG Data URI 头像，无外部请求
    $colors   = ['#5C6BC0','#26A69A','#EF5350','#AB47BC','#42A5F5','#FF7043','#66BB6A','#FFA726'];
    $nickname = $user['nickname'];
    $bg       = $colors[abs(crc32($nickname)) % count($colors)];
    $letter   = mb_strtoupper(mb_substr($nickname, 0, 1, 'UTF-8'), 'UTF-8');
    $svg = sprintf(
        '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120">'
        . '<rect width="120" height="120" rx="60" fill="%s"/>'
        . '<text x="50%%" y="50%%" dominant-baseline="central" text-anchor="middle" '
        . 'font-family="sans-serif" font-size="54" fill="#fff">%s</text>'
        . '</svg>',
        $bg,
        htmlspecialchars($letter, ENT_XML1, 'UTF-8')
    );
    $avatarUrl = 'data:image/svg+xml;base64,' . base64_encode($svg);
}

// ── 记录登录历史（每个会话仅写入一次）──────────────────────────────
if (!isset($_SESSION['login_recorded'])) {
    require_once __DIR__ . '/actions/save_login_history.php';
    saveLoginHistory((int)$user['id'], $db);
    $_SESSION['login_recorded'] = true;
}