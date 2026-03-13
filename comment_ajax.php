<?php
/**
 * comment_ajax.php — AJAX 评论提交接口
 * 接收 POST 请求，返回 JSON，供 article.php 前端调用。
 */
require_once 'auto_login.php';
autoLogin();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', __DIR__);
}

require_once __DIR__ . '/include/Config.php';
require_once ROOT_DIR . '/admin/comment_functions.php';

header('Content-Type: application/json; charset=utf-8');

// 只允许 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '请求方法不允许']);
    exit;
}

$articleId = isset($_POST['article_id']) ? intval($_POST['article_id']) : 0;
if ($articleId <= 0) {
    echo json_encode(['success' => false, 'message' => '无效的文章 ID']);
    exit;
}

// 登录用户自动填充姓名 / 邮箱
$isLoggedIn = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
if ($isLoggedIn) {
    $name  = (isset($_SESSION['user']['nickname']) && $_SESSION['user']['nickname'] !== '')
             ? $_SESSION['user']['nickname']
             : ($_SESSION['user']['username'] ?? '');
    $email = $_SESSION['user']['email'] ?? '';
} else {
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');
}

$content  = trim($_POST['content']   ?? '');
$parentId = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;

// 基础校验
if ($name === '' || $email === '' || $content === '') {
    echo json_encode(['success' => false, 'message' => '昵称、邮箱和评论内容均为必填项']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => '邮箱格式不正确']);
    exit;
}

$commentData = [
    'name'      => $name,
    'email'     => $email,
    'content'   => $content,
    'parent_id' => (string)$parentId,
];

$result = addNewComment($articleId, $commentData);

// addNewComment 返回 ['success'=>bool, 'message'=>str, 'comment_id'=>int, ...]
$success   = $result['success'] ?? false;
$message   = $result['message'] ?? ($success ? '评论提交成功' : '评论提交失败');
$commentId = $result['comment_id'] ?? 0;
$approved  = $result['approved']   ?? false;   // 是否直接过审

// 如果已过审，附带评论数据供前端即时渲染
$commentPayload = null;
if ($success && $approved && $commentId) {
    $avatarUrl = function_exists('getCommentAvatar')
        ? getCommentAvatar($email, $isLoggedIn ? (int)$_SESSION['user']['id'] : 0)
        : 'https://www.gravatar.com/avatar/' . md5(strtolower($email)) . '?s=38&d=mp';

    $commentPayload = [
        'id'         => $commentId,
        'name'       => $name,
        'email'      => $email,
        'content'    => $content,
        'parent_id'  => $parentId,
        'created_at' => date('Y-m-d H:i'),
        'avatar'     => $avatarUrl,
    ];
}

echo json_encode([
    'success'  => $success,
    'message'  => $message,
    'approved' => $approved,
    'comment'  => $commentPayload,
]);
exit;