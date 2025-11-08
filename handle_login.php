<?php
session_start();
require_once 'include/Db.php';
require_once 'admin/admin_functions.php';
// 验证请求方式
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// 获取表单数据
$loginId = trim($_POST['login_id'] ?? '');
$password = $_POST['password'] ?? '';

// 简单验证
if (empty($loginId) || empty($password)) {
    $_SESSION['login_error'] = '请输入账号和密码';
    header('Location: login.php');
    exit;
}

try {
    $db = Db::getInstance();
    
    // 查询用户（支持用户名或邮箱登录）
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$loginId, $loginId]);
    $user = $stmt->fetch();

    // 1. 先检查用户是否存在
    if (!$user) {
        $_SESSION['login_error'] = '账号或密码错误';
        header('Location: login.php');
        exit;
    }

    // 2. 再检查账号状态（关键：无论密码是否正确，只要账号存在就检查）
    $status = checkUserStatus($user['id']);
    if ($status == 'frozen') {
        $_SESSION['login_error'] = '账号已被冻结';
        header('Location: login.php');
        exit;
    }

    // 3. 最后验证密码
    if (!password_verify($password, $user['password_hash'])) {
        $_SESSION['login_error'] = '账号或密码错误';
        header('Location: login.php');
        exit;
    }

    // 更新最后登录时间
    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);

    // 设置会话
    $_SESSION['user_logged_in'] = true;
    $_SESSION['user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'nickname' => $user['nickname'],
        'email' => $user['email'],
        'role' => $user['role']
    ];

    // 跳转到首页
    header('Location: index.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['login_error'] = '登录失败：' . $e->getMessage();
    header('Location: login.php');
    exit;
}