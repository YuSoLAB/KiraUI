<?php
session_start();
require_once 'include/Db.php';
require_once 'admin/admin_functions.php';

// 验证请求方式
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

// 获取表单数据
$username = trim($_POST['username'] ?? '');
$nickname = trim($_POST['nickname'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// 后端验证
$errors = [];

// 用户名验证
if (!preg_match('/^[a-zA-Z0-9_]{1,20}$/', $username)) {
    $errors[] = '用户名只能包含数字、字母和下划线，长度不超过20位';
}

// 昵称验证
if (strlen($nickname) > 50) {
    $errors[] = '昵称长度不能超过50位';
}

// 邮箱验证
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = '请输入有效的邮箱地址';
} else {
    // 检查注册邮箱是否在允许的域名列表中
    if (!isRegistrationEmailAllowed($email)) {
        $errors[] = '该邮箱域名不允许注册';
    }
}

// 密码验证
if (strlen($password) < 6) {
    $errors[] = '密码长度至少6位';
}

$verifyCode = trim($_POST['verify_code'] ?? '');
if (empty($verifyCode) || strlen($verifyCode) != 6) {
    $errors[] = '请输入有效的6位验证码';
} else {
    try {
        $db = Db::getInstance();
        $stmt = $db->prepare("SELECT * FROM email_verification WHERE email = ? AND code = ? AND expires_at > NOW()");
        $stmt->execute([$email, $verifyCode]);
        $verification = $stmt->fetch();
        
        if (!$verification) {
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

try {
    $db = Db::getInstance();
    
    // 检查用户名是否已存在
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        $_SESSION['register_error'] = '用户名已被注册';
        header('Location: register.php');
        exit;
    }

    // 检查邮箱是否已存在
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['register_error'] = '邮箱已被注册';
        header('Location: register.php');
        exit;
    }

    // 插入新用户
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users 
        (username, nickname, email, password_hash, role, created_at, updated_at) 
        VALUES (?, ?, ?, ?, 'user', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
    
    $stmt->execute([$username, $nickname, $email, $passwordHash]);
    $stmt = $db->prepare("DELETE FROM email_verification WHERE email = ?");
    $stmt->execute([$email]);
    // 注册成功，跳转到登录页
    $_SESSION['register_success'] = '注册成功，请登录';
    header('Location: login.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['register_error'] = '注册失败：' . $e->getMessage();
    header('Location: register.php');
    exit;
}