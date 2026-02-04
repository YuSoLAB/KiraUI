<?php
require_once 'auto_login.php'; // 包含自动登录功能

// 尝试自动登录
autoLogin();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // 清除 Session 变量
    $_SESSION = array();
    // 销毁 Session
    session_destroy();
    
    // 清除Cookie和数据库令牌
    if (isset($_COOKIE['remember_me'])) {
        $token = $_COOKIE['remember_me'];
        // 清除Cookie
        setcookie('remember_me', '', time() - 3600, '/');
        
        // 从数据库中删除令牌
        try {
            require_once 'include/Db.php';
            $db = Db::getInstance();
            $stmt = $db->prepare("DELETE FROM remember_tokens WHERE token = ?");
            $stmt->execute([$token]);
        } catch (PDOException $e) {
            error_log("删除记住我令牌失败: " . $e->getMessage());
        }
    }
    
    // 跳转回首页
    header('Location: index.php');
    exit;
}
require_once 'admin/admin_functions.php';
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$status = checkUserStatus($user['id']);
$isBanned = false;
if ($status == 'frozen') {
    session_destroy();
    header('Location: login.php?error=账号已被冻结');
    exit;
} elseif ($status == 'banned') {
    $isBanned = true;
}
require_once 'include/Db.php';
$message = '';
$error = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); 
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']); 
}
$user = $_SESSION['user'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Db::getInstance();
    if (isset($_POST['action']) && $_POST['action'] === 'update_nickname') {
        if ($isBanned) {
            $_SESSION['error'] = '您的账号已被封禁，无法修改个人信息';
            $tab = $_POST['active_tab'] ?? 'profile';
            header("Location: user_center.php?tab=$tab");
            exit; 
        }
        $newNickname = trim($_POST['nickname']);
        if (!empty($newNickname) && strlen($newNickname) <= 50) {
            $stmt = $db->prepare("UPDATE users SET nickname = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            if ($stmt->execute([$newNickname, $user['id']])) {
                $_SESSION['user']['nickname'] = $newNickname;
                $_SESSION['message'] = '昵称更新成功';
            } else {
                $_SESSION['error'] = '昵称更新失败';
            }
        } else {
            $_SESSION['error'] = '请输入有效的昵称（不超过50个字符）';
        }
        $tab = $_POST['active_tab'] ?? 'profile'; 
        header("Location: user_center.php?tab=$tab");
        exit; 
    }
    if (isset($_POST['action']) && $_POST['action'] === 'update_password') {
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        if ($newPassword !== $confirmPassword) {
            $error = '两次输入的密码不一致';
        } elseif (strlen($newPassword) < 6) {
            $error = '密码长度至少6位';
        } else {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            if ($stmt->execute([$passwordHash, $user['id']])) {
                $message = '密码更新成功';
            } else {
                $error = '密码更新失败';
            }
        }
        $tab = $_POST['active_tab'] ?? 'profile'; 
        header("Location: user_center.php?tab=$tab");
        exit; 
    }
    if (isset($_POST['action']) && $_POST['action'] === 'upload_avatar') {
        if ($isBanned) {
            $_SESSION['error'] = '您的账号已被封禁，无法修改个人信息';
            if ($isAjax) {
                echo json_encode(['success' => false, 'message' => '您的账号已被封禁，无法修改个人信息']);
            } else {
                $tab = $_POST['active_tab'] ?? 'profile';
                header("Location: user_center.php?tab=$tab");
            }
            exit; 
        }
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if ($isAjax) {
            header('Content-Type: application/json');
        }
        if (!empty($_FILES['avatar']['name'])) {
            $uploadDir = 'uploads/avatars/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $fileInfo = pathinfo($_FILES['avatar']['name']);
            $extension = strtolower($fileInfo['extension']);
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($extension, $allowedExtensions)) {
                $filename = $user['id'] . '.' . $extension;
                $targetPath = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
                    $stmt = $db->prepare("UPDATE users SET avatar = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    if ($stmt->execute([$filename, $user['id']])) {
                        $_SESSION['user']['avatar'] = $filename;
                        $avatarUrl = 'uploads/avatars/' . $filename;
                        if ($isAjax) {
                            echo json_encode([
                                'success' => true,
                                'message' => '头像上传成功',
                                'avatarUrl' => $avatarUrl
                            ]);
                            exit;
                        } else {
                            $_SESSION['message'] = '头像上传成功';
                        }
                    } else {
                        unlink($targetPath); 
                        if ($isAjax) {
                            echo json_encode([
                                'success' => false,
                                'message' => '头像信息更新失败'
                            ]);
                            exit;
                        } else {
                            $_SESSION['error'] = '头像信息更新失败';
                        }
                    }
                } else {
                    if ($isAjax) {
                        echo json_encode([
                            'success' => false,
                            'message' => '头像上传失败'
                        ]);
                        exit;
                    } else {
                        $_SESSION['error'] = '头像上传失败';
                    }
                }
            } else {
                if ($isAjax) {
                    echo json_encode([
                        'success' => false,
                        'message' => '只允许上传jpg、jpeg、png、gif格式的图片'
                    ]);
                    exit;
                } else {
                    $_SESSION['error'] = '只允许上传jpg、jpeg、png、gif格式的图片';
                }
            }
        } else {
            if ($isAjax) {
                echo json_encode([
                    'success' => false,
                    'message' => '请选择要上传的头像文件'
                ]);
                exit;
            } else {
                $_SESSION['error'] = '请选择要上传的头像文件';
            }
        }
        if (!$isAjax) {
            $tab = $_POST['active_tab'] ?? 'profile';
            header("Location: user_center.php?tab=$tab");
            exit;
        }
    }
}
$db = Db::getInstance();
$stmt = $db->prepare("SELECT nickname, email, avatar FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$userInfo = $stmt->fetch();
$user['nickname'] = $userInfo['nickname'];
$user['email'] = $userInfo['email'];
$user['avatar'] = $userInfo['avatar'];
$_SESSION['user'] = $user;
if (!empty($user['avatar'])) {
    $avatarUrl = 'uploads/avatars/' . $user['avatar'];
} elseif (preg_match('/^(\d+)@(qq\.com|vip\.qq\.com)$/', $user['email'], $matches)) {
    $avatarUrl = 'https://q1.qlogo.cn/g?b=qq&nk=' . $matches[1] . '&s=640';
} else {
    $avatarUrl = 'https://via.placeholder.com/120?text=' . urlencode(substr($user['nickname'], 0, 1));
}
$activeTab = $_GET['tab'] ?? 'profile';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>用户中心 - <?php echo htmlspecialchars($user['nickname']); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* 定义基础变量，防止 style.css 缺失导致失效 */
        :root {
            --card: #ffffff;
            --text: #333333;
            --sub: #666666;
            
            /* 在这里补充深色模式的变量定义 */
            --dark-bg: #0f0f13;
            --dark-card: #1a1a2e; 
            --dark-text: #e0e0e0;
            --dark-vio: #9b8cff;
        }

        .user-center-wrap {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: clamp(20px, 4vw, 48px);
            position: relative;
            z-index: 1;
            transition: all 0.3s ease; /* 添加过渡动画 */
        }

        /* 关键修复：深色模式下的全局背景 */
        body.dark-mode {
            background-color: var(--dark-bg) !important;
            color: var(--dark-text);
        }

        .user-center-card {
            width: min(1000px, 94vw);
            background: linear-gradient(180deg, rgba(255,255,255,0), rgba(255,255,255,0.12)) , var(--card);
            border: 1.5px solid rgba(255, 158, 236, .35);
            border-radius: 28px;
            padding: clamp(24px, 4vw, 40px);
            box-shadow: 0 30px 80px rgba(155,140,255,.25), inset 0 0 0 1px rgba(255,255,255,.4);
            backdrop-filter: blur(1px);
            position: relative;
            overflow: hidden;
            margin: 0 auto;
            transition: background 0.3s ease, border 0.3s ease, box-shadow 0.3s ease;
        }
        .user-center-card::before {
            content: "";
            position: absolute;
            inset: -2px;
            background: conic-gradient(from 180deg at 50% 50%, #ffd6f1, #d4c9ff, #ffc9f2, #ffd6f1);
            filter: blur(20px);
            opacity: .35;
            z-index: -1;
            transition: opacity 0.3s ease;
        }
        .user-center-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 16px;
            position: relative;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .user-center-title {
            font-size: clamp(24px, 4vw, 36px);
            letter-spacing: .4px;
            margin: 0;
            font-weight: 900;
            background: linear-gradient(90deg, #ff4db1, #9b8cff 55%, #ff8af5 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-shadow: 0 2px 0 #ffffff80;
        }
        .theme-toggle-header {
            background: #ffffffaa;
            border: 1.5px solid rgba(155,140,255,.55);
            border-radius: 14px;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.2s ease;
            backdrop-filter: blur(6px);
        }
        .theme-toggle-header:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(155, 140, 255, 0.2);
        }
        
        /* 修复：确保深色模式样式生效，并使用正确定义的变量 */
        body.dark-mode .user-center-card,
        body.dark-mode .sidebar,
        body.dark-mode .profile-section {
            /* 使用 var(--dark-card) 替代未定义变量，并添加 fallback */
            background: linear-gradient(180deg, rgba(42, 42, 66, 0.3), rgba(42, 42, 66, 0.12)) , var(--dark-card, #1a1a2e);
            border-color: rgba(176, 160, 255, 0.35);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.3), inset 0 0 0 1px rgba(255,255,255,.05);
        }
        
        body.dark-mode .user-center-card::before,
        body.dark-mode .sidebar::before,
        body.dark-mode .profile-section::before {
            opacity: 0.1; /* 降低光晕亮度 */
        }

        body.dark-mode .sidebar-menu a {
            color: rgba(255, 255, 255, 0.85);
        }
        body.dark-mode .avatar-info h3 {
            color: rgba(255, 255, 255, 0.9);
        }
        body.dark-mode .avatar-info p {
            color: rgba(255, 255, 255, 0.7);
        }
        body.dark-mode .form-group label {
            color: rgba(255, 255, 255, 0.8);
        }
        body.dark-mode .empty-state {
            color: rgba(255, 255, 255, 0.7);
        }
        body.dark-mode .profile-section h2 {
            color: rgba(255, 255, 255, 0.9);
        }
        #themeToggle {
            display: none;
        }
        body.dark-mode .theme-toggle-header {
            background: rgba(42, 42, 66, 0.6);
            border-color: rgba(176, 160, 255, 0.35);
            color: var(--dark-vio);
        }
        @media (max-width: 768px) {
            .header-left {
                width: 100%;
                justify-content: space-between;
            }
            .user-center-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        .back-home {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            border-radius: 14px;
            font-weight: 700;
            text-decoration: none;
            color: #6c5dfb;
            background: #ffffffaa;
            border: 1.5px solid rgba(155,140,255,.55);
            backdrop-filter: blur(6px);
            transition: all 0.2s ease;
        }
        .back-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(155, 140, 255, 0.2);
            text-decoration: none;
            color: #6c5dfb;
        }
        .user-center-content {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 24px;
        }
        @media (max-width: 768px) {
            .user-center-content {
                grid-template-columns: 1fr;
            }
        }
        .sidebar {
            background: linear-gradient(180deg, rgba(255,255,255,0), rgba(255,255,255,0.12)) , var(--card);
            border: 1.5px solid rgba(255, 158, 236, .35);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 20px 60px rgba(155,140,255,.15), inset 0 0 0 1px rgba(255,255,255,.3);
            backdrop-filter: blur(1px);
            position: relative;
            overflow: hidden;
            transition: background 0.3s ease, border 0.3s ease;
        }
        .sidebar::before {
            content: "";
            position: absolute;
            inset: -2px;
            background: conic-gradient(from 180deg at 50% 50%, #ffd6f1, #d4c9ff, #ffc9f2, #ffd6f1);
            filter: blur(20px);
            opacity: .2;
            z-index: -1;
            transition: opacity 0.3s ease;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-menu li {
            margin-bottom: 8px;
        }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 14px;
            color: var(--text);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 1.5px solid transparent;
        }
        .sidebar-menu a:hover {
            background: rgba(155, 140, 255, 0.1);
            border-color: rgba(155, 140, 255, 0.3);
            transform: translateX(5px);
        }
        .sidebar-menu a.active {
            background: linear-gradient(135deg, rgba(255, 77, 177, 0.15), rgba(155, 140, 255, 0.15));
            border-color: rgba(255, 77, 177, 0.3);
            color: #6c5dfb;
            box-shadow: 0 8px 25px rgba(155, 140, 255, 0.15);
        }
        .sidebar-menu a svg {
            width: 18px;
            height: 18px;
        }
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        .profile-section {
            background: linear-gradient(180deg, rgba(255,255,255,0), rgba(255,255,255,0.12)) , var(--card);
            border: 1.5px solid rgba(255, 158, 236, .35);
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 20px 60px rgba(155,140,255,.15), inset 0 0 0 1px rgba(255,255,255,.3);
            backdrop-filter: blur(1px);
            position: relative;
            overflow: hidden;
            transition: background 0.3s ease, border 0.3s ease;
        }
        .profile-section::before {
            content: "";
            position: absolute;
            inset: -2px;
            background: conic-gradient(from 180deg at 50% 50%, #ffd6f1, #d4c9ff, #ffc9f2, #ffd6f1);
            filter: blur(20px);
            opacity: .2;
            z-index: -1;
            transition: opacity 0.3s ease;
        }
        .profile-section h2 {
            font-size: 24px;
            margin-top: 0;
            margin-bottom: 24px;
            font-weight: 800;
            background: linear-gradient(90deg, #ff4db1, #9b8cff);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .avatar-container {
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px dashed rgba(155, 140, 255, 0.4);
        }
        @media (max-width: 640px) {
            .avatar-container {
                flex-direction: column;
                text-align: center;
            }
        }
        .avatar-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #6c5dfb;
            box-shadow: 0 12px 30px rgba(155, 140, 255, 0.25);
        }
        .avatar-info h3 {
            margin: 0 0 8px 0;
            font-size: 20px;
            font-weight: 800;
            color: var(--text);
        }
        .avatar-info p {
            margin: 0;
            color: var(--sub);
        }
        .avatar-upload {
            display: flex;
            gap: 12px;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        .form-group {
            margin-bottom: 24px;
        }
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 700;
            color: var(--sub);
        }
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid rgba(155, 140, 255, 0.3);
            border-radius: 14px;
            background: #ffffffaa;
            font-family: inherit;
            font-size: 15px;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #9b8cff;
            box-shadow: 0 0 0 3px rgba(155, 140, 255, 0.1);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
            animation: fadeIn 0.4s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .message {
            padding: 14px 18px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-weight: 600;
            border: 1.5px solid;
        }
        .message.success {
            color: #2e7d32;
            background: linear-gradient(180deg, #e8f5e9, #c8e6c9);
            border-color: #a5d6a7;
        }
        .message.error {
            color: #d32f2f;
            background: linear-gradient(180deg, #ffebee, #ffcdd2);
            border-color: #ef9a9a;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--sub);
        }
        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        body.dark-mode .back-home {
            background: rgba(42, 42, 66, 0.6);
            color: var(--dark-vio);
            border-color: rgba(176, 160, 255, 0.35);
        }
        body.dark-mode .sidebar-menu a {
            color: var(--dark-text);
        }
        body.dark-mode .sidebar-menu a.active {
            background: linear-gradient(135deg, rgba(255, 102, 184, 0.15), rgba(176, 160, 255, 0.15));
            border-color: rgba(255, 102, 184, 0.3);
            color: var(--dark-vio);
        }
        body.dark-mode .form-group input {
            background: rgba(42, 42, 66, 0.6);
            border-color: rgba(176, 160, 255, 0.35);
            color: var(--dark-text);
        }
        body.dark-mode .message.success {
            background: linear-gradient(180deg, rgba(46, 125, 50, 0.15), rgba(76, 175, 80, 0.1));
            border-color: rgba(76, 175, 80, 0.3);
            color: #81c784;
        }
        body.dark-mode .message.error {
            background: linear-gradient(180deg, rgba(211, 47, 47, 0.15), rgba(244, 67, 54, 0.1));
            border-color: rgba(244, 67, 54, 0.3);
            color: #e57373;
        }
        .form-actions {
            display: flex;
            align-items: center;
            gap: 16px; /* 按钮之间的间距 */
            margin-top: 24px;
        }

        .btn-logout {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 14px 24px;
            border-radius: 14px;
            font-weight: 700; /* 匹配原有字体粗细 */
            text-decoration: none;
            font-size: 15px; /* 匹配原有输入框字体 */
            cursor: pointer;
            
            /* 浅红色风格，表示退出/警告 */
            color: #ff4d4f;
            background: rgba(255, 77, 79, 0.08);
            border: 1.5px solid rgba(255, 77, 79, 0.3);
            
            transition: all 0.2s ease;
        }

        .btn-logout:hover {
            background: rgba(255, 77, 79, 0.15);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 77, 79, 0.15);
            color: #ff4d4f;
            text-decoration: none;
        }

        /* 深色模式适配 */
        body.dark-mode .btn-logout {
            background: rgba(255, 77, 79, 0.15);
            border-color: rgba(255, 77, 79, 0.4);
            color: #ff7875;
        }
    </style>
</head>
<body>
    <div class="sparkles" id="sparkles"></div>
    <div class="user-center-wrap">
        <div class="user-center-card">
            <div class="user-center-header">
                <div class="header-left">
                    <button id="themeToggleHeader" class="theme-toggle-header">🌙</button>
                    <a href="index.php" class="back-home">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="m12 19-7-7 7-7"/>
                            <path d="M19 12H5"/>
                        </svg>
                        返回首页
                    </a>
                </div>
                <h1 class="user-center-title">用户中心</h1>
            </div>
            <?php if ($message): ?>
                <div class="message success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>
            <div class="user-center-content">
                <div class="sidebar">
                    <ul class="sidebar-menu">
                        <li>
                            <a href="?tab=profile" class="<?php echo $activeTab === 'profile' ? 'active' : ''; ?>">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                                个人信息
                            </a>
                        </li>
                        <li>
                            <a href="?tab=security" class="<?php echo $activeTab === 'security' ? 'active' : ''; ?>">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                                安全管理
                            </a>
                        </li>
                        <li>
                            <a href="?tab=articles" class="<?php echo $activeTab === 'articles' ? 'active' : ''; ?>">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                                </svg>
                                我的收藏
                            </a>
                        </li>
                        <li>
                            <a href="?tab=messages" class="<?php echo $activeTab === 'messages' ? 'active' : ''; ?>">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                </svg>
                                我的消息
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="main-content">
                    <div id="profile" class="tab-content <?php echo $activeTab === 'profile' ? 'active' : ''; ?>">
                        <div class="profile-section">
                            <h2>个人信息</h2>
                            <div class="avatar-container">
                                <img src="<?php echo $avatarUrl; ?>" alt="头像" class="avatar-preview" id="currentAvatar">
                                <div id="previewContainer" style="display: none;">
                                    <img id="avatarPreview" alt="预览" class="avatar-preview">
                                </div>
                                <div class="avatar-info">
                                    <h3><?php echo htmlspecialchars($user['nickname']); ?></h3>
                                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                                    <p>KID: <?php echo htmlspecialchars($user['id']); ?></p>
                                    <form method="post" enctype="multipart/form-data" class="avatar-upload" id="avatarForm">
                                        <input type="hidden" name="action" value="upload_avatar">
                                        <input type="hidden" name="active_tab" value="<?php echo $activeTab; ?>">
                                        <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif" style="display: none;" id="avatar-upload">
                                        <div class="avatar-upload">
                                            <label for="avatar-upload" class="btn secondary">选择头像</label>
                                            <button type="submit" class="btn primary" id="uploadButton" disabled>上传头像</button>
                                        </div>
                                    </form>
                                    <div id="uploadProgress" style="display: none; margin-top: 10px; width: 100%; background-color: #eee; border-radius: 5px;">
                                        <div id="progressBar" style="width: 0%; height: 10px; border-radius: 5px; background-color: #4CAF50; transition: width 0.3s ease;"></div>
                                    </div>
                                    <div id="uploadMessage" style="margin-top: 10px; color: #666;"></div>
                                </div>
                            </div>
                            <form method="post">
                                <input type="hidden" name="action" value="update_nickname">
                                <input type="hidden" name="active_tab" value="<?php echo $activeTab; ?>">
                                <div class="form-group">
                                    <label for="nickname">昵称</label>
                                    <input type="text" id="nickname" name="nickname" value="<?php echo htmlspecialchars($user['nickname']); ?>" maxlength="50" placeholder="请输入您的昵称">
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn primary">更新昵称</button>
                                    
                                    <a href="user_center.php?action=logout" class="btn-logout" onclick="return confirm('确定要退出登录吗？');">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">
                                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                            <polyline points="16 17 21 12 16 7"></polyline>
                                            <line x1="21" y1="12" x2="9" y2="12"></line>
                                        </svg>
                                        退出账号
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div id="security" class="tab-content <?php echo $activeTab === 'security' ? 'active' : ''; ?>">
                        <div class="profile-section">
                            <h2>安全管理</h2>
                            <form method="post">
                                <input type="hidden" name="action" value="update_password">
                                <input type="hidden" name="active_tab" value="security">
                                <div class="form-group">
                                    <label for="new_password">新密码</label>
                                    <input type="password" id="new_password" name="new_password" minlength="6" placeholder="请输入新密码">
                                </div>
                                <div class="form-group">
                                    <label for="confirm_password">确认新密码</label>
                                    <input type="password" id="confirm_password" name="confirm_password" placeholder="请再次输入新密码">
                                </div>
                                <button type="submit" class="btn primary">更新密码</button>
                            </form>
                        </div>
                    </div>
                    <div id="articles" class="tab-content <?php echo $activeTab === 'articles' ? 'active' : ''; ?>">
                        <div class="profile-section">
                            <h2>我的收藏</h2>
                            <div class="empty-state">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                                </svg>
                                <p>您尚未收藏任何文章。</p>
                            </div>
                        </div>
                    </div>
                    <div id="messages" class="tab-content <?php echo $activeTab === 'messages' ? 'active' : ''; ?>">
                        <div class="profile-section">
                            <h2>我的消息</h2>
                            <div class="empty-state">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                </svg>
                                <p>您没有新的消息。</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <button id="themeToggle" class="theme-toggle" style="display: none;">🌙</button>
    <script>
        // 页面加载时立即执行主题检查，防止闪烁
        (function() {
            const savedTheme = localStorage.getItem('theme');
            const body = document.body;
            const themeToggleHeader = document.getElementById('themeToggleHeader');
            
            if (savedTheme === 'dark') {
                body.classList.add('dark-mode');
                if (themeToggleHeader) themeToggleHeader.textContent = '☀️';
            } else {
                if (themeToggleHeader) themeToggleHeader.textContent = '🌙';
            }
        })();

        document.addEventListener('DOMContentLoaded', function() {
            // 重新绑定按钮事件，确保 DOM 加载完毕
            const themeToggleHeader = document.getElementById('themeToggleHeader');
            if(themeToggleHeader) {
                // 更新按钮状态以匹配当前模式
                themeToggleHeader.textContent = document.body.classList.contains('dark-mode') ? '☀️' : '🌙';
                
                themeToggleHeader.addEventListener('click', function() {
                    document.body.classList.toggle('dark-mode');
                    const isDark = document.body.classList.contains('dark-mode');
                    this.textContent = isDark ? '☀️' : '🌙';
                    localStorage.setItem('theme', isDark ? 'dark' : 'light');
                });
            }
            
            createSparkles();
        });

        // 头像上传相关逻辑保持不变
        document.getElementById('avatar-upload').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const uploadButton = document.getElementById('uploadButton');
            const previewContainer = document.getElementById('previewContainer');
            const avatarPreview = document.getElementById('avatarPreview');
            const currentAvatar = document.getElementById('currentAvatar');
            if (file) {
                const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
                const maxSize = 2 * 1024 * 1024; 
                if (!validTypes.includes(file.type)) {
                    alert('请选择 JPEG、PNG 或 GIF 格式的图片');
                    this.value = '';
                    uploadButton.disabled = true;
                    return;
                }
                if (file.size > maxSize) {
                    alert('图片大小不能超过 2MB');
                    this.value = '';
                    uploadButton.disabled = true;
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(event) {
                    avatarPreview.src = event.target.result;
                    previewContainer.style.display = 'block';
                    currentAvatar.style.display = 'none';
                };
                reader.readAsDataURL(file);
                uploadButton.disabled = false;
            } else {
                uploadButton.disabled = true;
                previewContainer.style.display = 'none';
                currentAvatar.style.display = 'block';
            }
        });
        document.getElementById('avatarForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const fileInput = document.getElementById('avatar-upload');
            if (!fileInput.files[0]) {
                alert('请先选择头像文件');
                return;
            }
            const formData = new FormData(this);
            const progressBar = document.getElementById('progressBar');
            const uploadProgress = document.getElementById('uploadProgress');
            const uploadMessage = document.getElementById('uploadMessage');
            const submitButton = document.getElementById('uploadButton');
            const currentAvatar = document.getElementById('currentAvatar');
            const previewContainer = document.getElementById('previewContainer');
            uploadProgress.style.display = 'block';
            progressBar.style.width = '0%';
            uploadMessage.textContent = '准备上传...';
            uploadMessage.style.color = '#666';
            submitButton.disabled = true;
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'user_center.php', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percent = (e.loaded / e.total) * 100;
                    progressBar.style.width = percent + '%';
                    uploadMessage.textContent = `上传中: ${Math.round(percent)}%`;
                }
            });
            xhr.addEventListener('load', function() {
                submitButton.disabled = false;
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            uploadMessage.style.color = 'green';
                            uploadMessage.textContent = response.message || '头像上传成功！';
                            if (response.avatarUrl) {
                                currentAvatar.src = response.avatarUrl + '?t=' + new Date().getTime();
                            }
                            previewContainer.style.display = 'none';
                            currentAvatar.style.display = 'block';
                            document.getElementById('avatar-upload').value = '';
                            setTimeout(() => {
                                uploadProgress.style.display = 'none';
                                uploadMessage.textContent = '';
                            }, 3000);
                        } else {
                            uploadMessage.style.color = 'red';
                            uploadMessage.textContent = response.message || '上传失败';
                        }
                    } catch (error) {
                        uploadMessage.style.color = 'red';
                        uploadMessage.textContent = '服务器响应格式错误，请刷新页面重试';
                        console.error('JSON解析错误:', error);
                        console.log('服务器响应:', xhr.responseText);
                    }
                } else {
                    uploadMessage.style.color = 'red';
                    uploadMessage.textContent = '上传失败，服务器错误: ' + xhr.status;
                }
            });
            xhr.addEventListener('error', function() {
                submitButton.disabled = false;
                uploadMessage.style.color = 'red';
                uploadMessage.textContent = '上传失败，请检查网络连接';
            });
            xhr.addEventListener('timeout', function() {
                submitButton.disabled = false;
                uploadMessage.style.color = 'red';
                uploadMessage.textContent = '上传超时，请重试';
            });
            xhr.timeout = 30000; 
            xhr.send(formData);
        });
        
        function createSparkles() {
            const sparklesContainer = document.getElementById('sparkles');
            if(!sparklesContainer) return;
            const sparkleCount = 30;
            for (let i = 0; i < sparkleCount; i++) {
                const sparkle = document.createElement('i');
                sparkle.style.left = Math.random() * 100 + 'vw';
                sparkle.style.width = Math.random() * 8 + 4 + 'px';
                sparkle.style.height = sparkle.style.width;
                sparkle.style.animationDelay = Math.random() * 5 + 's';
                sparkle.style.animationDuration = Math.random() * 3 + 4 + 's';
                sparklesContainer.appendChild(sparkle);
            }
        }
    </script>
</body>
</html>