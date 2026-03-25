<?php
/**
 * admin_ajax_badges.php — 用户角标与头衔 AJAX 接口
 *
 * 放置路径：admin/admin_ajax_badges.php
 * 前端调用：fetch('admin_ajax_badges.php', { method: 'POST', body: formData })
 *
 * 支持的 action：
 *   save   — 新建或更新角标配置（UPSERT）
 *   delete — 删除角标配置
 *   get    — 获取单个用户的角标配置（GET 参数 ?user_id=N）
 */

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(dirname(__FILE__)));
}

// ── 鉴权：必须是已登录管理员 ──────────────────────────
if (empty($_SESSION['admin_logged_in'])) {
    ob_clean();
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => '未登录或会话已过期']);
    exit;
}

require_once ROOT_DIR . '/include/Db.php';
ob_clean();
header('Content-Type: application/json; charset=utf-8');

// ── 颜色格式校验 ──────────────────────────────────────
function validateColor(string $v, string $default): string
{
    $v = trim($v);
    if ($v === '') return $default;
    if (preg_match('/^#[0-9a-fA-F]{3}$|^#[0-9a-fA-F]{6}$/', $v)) {
        // 扩展 3 位 hex 为 6 位
        if (strlen($v) === 4) {
            $v = '#' . $v[1].$v[1] . $v[2].$v[2] . $v[3].$v[3];
        }
        return strtolower($v);
    }
    return $default;
}

// ── 合法的角标类型 ────────────────────────────────────
const ALLOWED_BADGE_TYPES = ['verified', 'official', 'vip', 'admin', 'hot', 'star'];

try {
    $db     = Db::getInstance();
    $method = $_SERVER['REQUEST_METHOD'];

    // ── GET：获取单个用户角标 ──────────────────────────
    if ($method === 'GET') {
        $userId = intval($_GET['user_id'] ?? 0);
        if ($userId <= 0) {
            echo json_encode(['ok' => false, 'msg' => '无效的用户 ID']);
            exit;
        }
        $stmt = $db->prepare("SELECT * FROM user_badges WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'data' => $row ?: null]);
        exit;
    }

    // ── POST：save / delete ────────────────────────────
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'msg' => '不支持的请求方法']);
        exit;
    }

    $action = trim($_POST['action'] ?? '');
    $userId = intval($_POST['user_id'] ?? 0);

    if ($userId <= 0) {
        echo json_encode(['ok' => false, 'msg' => '无效的用户 ID']);
        exit;
    }

    // 确认用户存在
    $chk = $db->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    $chk->execute([$userId]);
    if (!$chk->fetch()) {
        echo json_encode(['ok' => false, 'msg' => '用户不存在']);
        exit;
    }

    // ══════════════════════════════════════════════════
    // action = save（UPSERT）
    // ══════════════════════════════════════════════════
    if ($action === 'save') {
        $badgeType    = in_array($_POST['badge_type'] ?? '', ALLOWED_BADGE_TYPES)
                        ? $_POST['badge_type']
                        : 'verified';
        $badgeColor   = validateColor($_POST['badge_color']      ?? '', '#1d9bf0');
        $badgeIconC   = validateColor($_POST['badge_icon_color'] ?? '', '#ffffff');
        $titleText    = mb_substr(trim($_POST['title_text']   ?? ''), 0, 30);
        $titleColor   = validateColor($_POST['title_color']    ?? '', '#6c5dfb');
        $titleBgColor = validateColor($_POST['title_bg_color'] ?? '', '');
        $isActive     = ($_POST['is_active'] ?? '0') === '1' ? 1 : 0;

        // UPSERT：如已存在则更新，否则插入
        $stmt = $db->prepare(
            "INSERT INTO user_badges
                (user_id, badge_type, badge_color, badge_icon_color,
                 title_text, title_color, title_bg_color, is_active,
                 created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                badge_type      = VALUES(badge_type),
                badge_color     = VALUES(badge_color),
                badge_icon_color= VALUES(badge_icon_color),
                title_text      = VALUES(title_text),
                title_color     = VALUES(title_color),
                title_bg_color  = VALUES(title_bg_color),
                is_active       = VALUES(is_active),
                updated_at      = NOW()"
        );
        $stmt->execute([
            $userId,
            $badgeType,
            $badgeColor,
            $badgeIconC,
            $titleText,
            $titleColor,
            $titleBgColor,
            $isActive,
        ]);

        echo json_encode(['ok' => true, 'msg' => '角标配置已保存']);
        exit;
    }

    // ══════════════════════════════════════════════════
    // action = delete
    // ══════════════════════════════════════════════════
    if ($action === 'delete') {
        $stmt = $db->prepare("DELETE FROM user_badges WHERE user_id = ?");
        $stmt->execute([$userId]);
        $affected = $stmt->rowCount();
        echo json_encode([
            'ok'  => $affected > 0,
            'msg' => $affected > 0 ? '角标配置已删除' : '未找到该用户的角标配置',
        ]);
        exit;
    }

    // ══════════════════════════════════════════════════
    // action = toggle（快速启用 / 禁用）
    // ══════════════════════════════════════════════════
    if ($action === 'toggle') {
        $isActive = ($_POST['is_active'] ?? '0') === '1' ? 1 : 0;
        $stmt     = $db->prepare(
            "UPDATE user_badges SET is_active = ?, updated_at = NOW() WHERE user_id = ?"
        );
        $stmt->execute([$isActive, $userId]);
        echo json_encode([
            'ok'  => true,
            'msg' => $isActive ? '已启用' : '已禁用',
        ]);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => '未知的操作']);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => '服务器错误：' . $e->getMessage()]);
}