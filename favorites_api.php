<?php
/**
 * favorites_api.php
 * 收藏功能 AJAX 接口
 *
 * POST  action=toggle  &article_id=N  → 切换收藏状态
 * GET   action=check   &article_id=N  → 查询是否已收藏
 *
 * 所有响应均为 JSON：{ success, favorited, message }
 */

require_once __DIR__ . '/auto_login.php';
autoLogin();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/include/Db.php';

header('Content-Type: application/json; charset=utf-8');

// ── 登录检查 ──────────────────────────────────────────────────
if (empty($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    echo json_encode([
        'success'   => false,
        'favorited' => false,
        'message'   => '请先登录后再收藏',
    ]);
    exit;
}

$userId    = (int)($_SESSION['user']['id'] ?? 0);
$action    = $_REQUEST['action']     ?? '';
$articleId = (int)($_REQUEST['article_id'] ?? 0);

if ($userId <= 0 || $articleId <= 0) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

try {
    $db = Db::getInstance();

    // ── 查询是否已收藏 ────────────────────────────────────────
    if ($action === 'check') {
        $stmt = $db->prepare(
            "SELECT id FROM user_favorites WHERE user_id = ? AND article_id = ? LIMIT 1"
        );
        $stmt->execute([$userId, $articleId]);
        $row = $stmt->fetch();
        echo json_encode([
            'success'   => true,
            'favorited' => (bool)$row,
        ]);
        exit;
    }

    // ── 切换收藏 ─────────────────────────────────────────────
    if ($action === 'toggle') {
        $stmt = $db->prepare(
            "SELECT id FROM user_favorites WHERE user_id = ? AND article_id = ? LIMIT 1"
        );
        $stmt->execute([$userId, $articleId]);
        $existing = $stmt->fetch();

        if ($existing) {
            // 取消收藏
            $del = $db->prepare(
                "DELETE FROM user_favorites WHERE user_id = ? AND article_id = ?"
            );
            $del->execute([$userId, $articleId]);
            echo json_encode([
                'success'   => true,
                'favorited' => false,
                'message'   => '已取消收藏',
            ]);
        } else {
            // 添加收藏
            $ins = $db->prepare(
                "INSERT INTO user_favorites (user_id, article_id) VALUES (?, ?)"
            );
            $ins->execute([$userId, $articleId]);
            echo json_encode([
                'success'   => true,
                'favorited' => true,
                'message'   => '收藏成功',
            ]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => '未知操作']);

} catch (Exception $e) {
    error_log("收藏操作错误: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '服务器内部错误，请稍后重试']);
}