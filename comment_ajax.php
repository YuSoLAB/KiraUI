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
require_once ROOT_DIR . '/admin/badge_functions.php';

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
    // 手机号注册的用户可能没有邮箱，允许为空字符串
    $email = trim($_SESSION['user']['email'] ?? '');
} else {
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');
}

$content  = trim($_POST['content']   ?? '');
$parentId = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;

// 基础校验
// 登录用户不要求填写邮箱（手机号注册账号可能没有邮箱）
if ($isLoggedIn) {
    if ($name === '' || $content === '') {
        echo json_encode(['success' => false, 'message' => '评论内容不能为空']);
        exit;
    }
} else {
    if ($name === '' || $email === '' || $content === '') {
        echo json_encode(['success' => false, 'message' => '昵称、邮箱和评论内容均为必填项']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => '邮箱格式不正确']);
        exit;
    }
}

$commentData = [
    'name'      => $name,
    'email'     => $email,
    'content'   => $content,
    'parent_id' => (string)$parentId,
];

$result = addNewComment($articleId, $commentData);

$success   = $result['success'] ?? false;
$message   = $result['message'] ?? ($success ? '评论提交成功' : '评论提交失败');
$commentId = $result['comment_id'] ?? $result['new_comment_id'] ?? 0;
$approved  = $result['approved'] ?? (isset($result['needs_moderation']) ? !$result['needs_moderation'] : false);

// 如果已过审，附带评论数据供前端即时渲染
$commentPayload = null;
if ($success && $approved && $commentId) {
    $userId    = $isLoggedIn ? (int)$_SESSION['user']['id'] : 0;
    if (function_exists('getCommentAvatar')) {
        // 优先用 userId 查头像，email 可能为空（手机号注册用户）
        $src = getCommentAvatar($email, $userId);
        $avatarUrl = ($src && strpos($src, 'gravatar.com') === false)
            ? $src
            : (defined('DEFAULT_AVATAR_SVG') ? DEFAULT_AVATAR_SVG : 'data:image/svg+xml,%3Csvg xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22 viewBox%3D%220 0 60 60%22%3E%3Crect width%3D%2260%22 height%3D%2260%22 rx%3D%2230%22 fill%3D%22%23b0b8c9%22%2F%3E%3Ccircle cx%3D%2230%22 cy%3D%2223%22 r%3D%2210%22 fill%3D%22%23fff%22%2F%3E%3Cellipse cx%3D%2230%22 cy%3D%2248%22 rx%3D%2215%22 ry%3D%2210%22 fill%3D%22%23fff%22%2F%3E%3C%2Fsvg%3E');
    } else {
        $avatarUrl = defined('DEFAULT_AVATAR_SVG') ? DEFAULT_AVATAR_SVG : 'data:image/svg+xml,%3Csvg xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22 viewBox%3D%220 0 60 60%22%3E%3Crect width%3D%2260%22 height%3D%2260%22 rx%3D%2230%22 fill%3D%22%23b0b8c9%22%2F%3E%3Ccircle cx%3D%2230%22 cy%3D%2223%22 r%3D%2210%22 fill%3D%22%23fff%22%2F%3E%3Cellipse cx%3D%2230%22 cy%3D%2248%22 rx%3D%2215%22 ry%3D%2210%22 fill%3D%22%23fff%22%2F%3E%3C%2Fsvg%3E';
    }

    // 获取角标配置（仅登录用户才可能有角标）
    $badge      = ($userId > 0) ? getUserBadge($userId) : null;
    $badgeHtml  = renderAvatarWithBadge($avatarUrl, $name, $badge, 'fb-avatar', 16);
    $titleHtml  = renderUserTitle($badge);

    $commentPayload = [
        'id'          => $commentId,
        'name'        => $name,
        'email'       => $email,
        'content'     => $content,
        'parent_id'   => $parentId,
        'created_at'  => date('Y-m-d H:i'),
        'avatar'      => $avatarUrl,
        'badge_html'  => $badgeHtml,   // 含角标的头像 HTML
        'title_html'  => $titleHtml,   // 头衔 HTML（可能为空字符串）
    ];
}

echo json_encode([
    'success'  => $success,
    'message'  => $message,
    'approved' => $approved,
    'comment'  => $commentPayload,
]);
exit;