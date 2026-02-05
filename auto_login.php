<?php
session_start();
require_once 'include/Db.php';
require_once 'admin/admin_functions.php';

function autoLogin() {
    if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
        return true; // 已经登录
    }
    
    if (isset($_COOKIE['remember_me'])) {
        $token = $_COOKIE['remember_me'];
        
        try {
            $db = Db::getInstance();
            
            // 验证令牌
            $stmt = $db->prepare("SELECT rt.user_id, rt.token, u.* 
                                FROM remember_tokens rt 
                                JOIN users u ON rt.user_id = u.id 
                                WHERE rt.token = ? AND rt.expires_at > NOW()");
            $stmt->execute([$token]);
            $tokenData = $stmt->fetch();
            
            if ($tokenData) {
                error_log("自动登录成功: 用户ID={$tokenData['id']}, 用户名={$tokenData['username']}");
                // 检查用户状态
                $status = checkUserStatus($tokenData['user_id']);
                if ($status == 'frozen' || $status == 'banned') {
                    // 清除无效令牌
                    $stmt = $db->prepare("DELETE FROM remember_tokens WHERE token = ?");
                    $stmt->execute([$token]);
                    setcookie('remember_me', '', time() - 3600, '/');
                    return false;
                }
                
                // 自动登录成功
                $_SESSION['user_logged_in'] = true;
                $_SESSION['user'] = [
                    'id' => $tokenData['id'],
                    'username' => $tokenData['username'],
                    'nickname' => $tokenData['nickname'],
                    'email' => $tokenData['email'],
                    'role' => $tokenData['role']
                ];
                
                // 更新最后登录时间
                $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$tokenData['id']]);
                
                return true;
            } else {
                // 令牌无效，清除Cookie
                setcookie('remember_me', '', time() - 3600, '/');
            }
        } catch (PDOException $e) {
            error_log("自动登录错误: " . $e->getMessage());
        }
    }
    
    return false;
}
?>