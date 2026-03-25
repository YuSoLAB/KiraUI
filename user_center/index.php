<?php
/**
 * 用户中心 - 入口文件
 * 负责协调：鉴权初始化、路由分发、页面渲染
 */

require_once __DIR__ . '/init.php';

// ── 路由分发 POST 请求 ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'update_nickname':
            require __DIR__ . '/actions/update_nickname.php';
            exit;
        case 'update_password':
            require __DIR__ . '/actions/update_password.php';
            exit;
        case 'upload_avatar':
            require __DIR__ . '/actions/upload_avatar.php';
            exit;

        // ── 手机号绑定 AJAX ────────────────────────────────────────
        case 'send_phone_code':
            $_POST['sub_action'] = 'send_code';
            require __DIR__ . '/actions/bind_phone.php';
            exit;
        case 'verify_phone_bind':
            $_POST['sub_action'] = 'verify_bind';
            require __DIR__ . '/actions/bind_phone.php';
            exit;

        // ── 通知相关 AJAX ──────────────────────────────────────
        case 'mark_read':
        case 'mark_all_read':
        case 'delete_notification':
        case 'delete_all_notifications':
            require __DIR__ . '/../admin/comment_functions.php';
            header('Content-Type: application/json');
            $uid = $_SESSION['user']['id'] ?? null;
            if (!$uid) { echo json_encode(['success' => false]); exit; }
            if ($action === 'mark_read') {
                $ok = markNotificationsRead($uid, intval($_POST['id'] ?? 0));
            } elseif ($action === 'mark_all_read') {
                $ok = markNotificationsRead($uid);
            } elseif ($action === 'delete_notification') {
                $ok = deleteNotification($uid, intval($_POST['id'] ?? 0));
            } else {
                $ok = deleteNotification($uid);
            }
            echo json_encode(['success' => (bool)$ok]);
            exit;
    }
}

// ── 读取一次性提示消息 ──────────────────────────────────────────────
$message = '';
$error   = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

$activeTab = $_GET['tab'] ?? 'profile';

// ── 渲染页面 ────────────────────────────────────────────────────────
require __DIR__ . '/views/layout_head.php';
require __DIR__ . '/views/sidebar.php';

// 主内容区：各标签页
$tabs = ['profile', 'security', 'articles', 'messages'];
echo '<div class="main-content">';
foreach ($tabs as $tab) {
    require __DIR__ . "/views/tab_{$tab}.php";
}
echo '</div>';

require __DIR__ . '/views/layout_foot.php';