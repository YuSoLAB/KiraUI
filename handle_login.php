<?php
session_start();
require_once 'include/Db.php';
require_once 'admin/admin_functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}
$loginId = trim($_POST['login_id'] ?? '');
$password = $_POST['password'] ?? '';
if (empty($loginId) || empty($password)) {
    $_SESSION['login_error'] = '请输入账号和密码';
    header('Location: login.php');
    exit;
}
try {
    $db = Db::getInstance();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$loginId, $loginId]);
    $user = $stmt->fetch();
    if (!$user) {
        $_SESSION['login_error'] = '账号或密码错误';
        header('Location: login.php');
        exit;
    }
    $status = checkUserStatus($user['id']);
    if ($status == 'frozen') {
        $_SESSION['login_error'] = '账号已被冻结';
        header('Location: login.php');
        exit;
    }
    if (!password_verify($password, $user['password_hash'])) {
        $_SESSION['login_error'] = '账号或密码错误';
        header('Location: login.php');
        exit;
    }
    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    $_SESSION['user_logged_in'] = true;
    $_SESSION['user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'nickname' => $user['nickname'],
        'email' => $user['email'],
        'role' => $user['role']
    ];
    if (isset($_POST['remember_me']) && $_POST['remember_me'] === 'on') {
        $token = bin2hex(random_bytes(32));
        $expires = time() + (30 * 24 * 60 * 60); // 30天
        
        try {
            $stmt = $db->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $stmt = $db->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
            $result = $stmt->execute([$user['id'], $token, date('Y-m-d H:i:s', $expires)]);
            
            if ($result) {
                // 设置记住我Cookie
                setcookie('remember_me', $token, $expires, '/', '', false, true);
                error_log("记住我令牌已成功保存: 用户ID={$user['id']}, 令牌={$token}");
            } else {
                error_log("记住我令牌保存失败: 用户ID={$user['id']}");
            }
        } catch (PDOException $e) {
            error_log("记住我令牌数据库错误: " . $e->getMessage());
        }
    }
    header('Location: index.php');
    exit;
} catch (PDOException $e) {
    $_SESSION['login_error'] = '登录失败：' . $e->getMessage();
    header('Location: login.php');
    exit;
}