<?php
/**
 * 退出登录
 * 清除 Session、Cookie 及数据库令牌，并跳转回首页
 */

$_SESSION = [];
session_destroy();

if (isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    setcookie('remember_me', '', time() - 3600, '/');

    try {
        require_once __DIR__ . '/../../include/Db.php';
        $db   = Db::getInstance();
        $stmt = $db->prepare("DELETE FROM remember_tokens WHERE token = ?");
        $stmt->execute([$token]);
    } catch (PDOException $e) {
        error_log("删除记住我令牌失败: " . $e->getMessage());
    }
}

header('Location: ../index.php');
exit;
