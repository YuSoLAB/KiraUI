<?php
/**
 * badge_functions.php — 用户认证角标与头衔核心函数
 *
 * 放置路径：admin/badge_functions.php
 * 引用方式（从 root 文件）：require_once ROOT_DIR . '/admin/badge_functions.php';
 * 引用方式（从 admin/ 文件）：require_once __DIR__ . '/badge_functions.php';
 */

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(dirname(__FILE__)));
}

require_once ROOT_DIR . '/include/Db.php';

// ──────────────────────────────────────────────
// 数据读取
// ──────────────────────────────────────────────

/**
 * 获取单个用户的角标与头衔配置
 *
 * @param int $userId
 * @return array|null
 */
function getUserBadge(int $userId): ?array
{
    if ($userId <= 0) return null;
    try {
        $db   = Db::getInstance();
        $stmt = $db->prepare(
            "SELECT * FROM user_badges WHERE user_id = ? AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * 批量获取多个用户的角标（减少 N+1 查询）
 *
 * @param int[] $userIds
 * @return array  [user_id => badge_row]
 */
function getUserBadges(array $userIds): array
{
    $ids = array_filter(array_map('intval', $userIds));
    if (empty($ids)) return [];
    try {
        $db   = Db::getInstance();
        $ph   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare(
            "SELECT * FROM user_badges WHERE user_id IN ($ph) AND is_active = 1"
        );
        $stmt->execute(array_values($ids));
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(int)$row['user_id']] = $row;
        }
        return $result;
    } catch (Exception $e) {
        return [];
    }
}

// ──────────────────────────────────────────────
// 角标类型定义
// ──────────────────────────────────────────────

/**
 * 返回所有内置角标类型
 *
 * @return array  [type => ['label'=>string, 'defaultBadgeColor'=>string, 'defaultIconColor'=>string]]
 */
function getAllBadgeTypes(): array
{
    return [
        'verified' => ['label' => '✓ 认证',   'defaultBadgeColor' => '#1d9bf0', 'defaultIconColor' => '#ffffff'],
        'official' => ['label' => '★ 官方',   'defaultBadgeColor' => '#f59e0b', 'defaultIconColor' => '#ffffff'],
        'vip'      => ['label' => '♛ VIP',    'defaultBadgeColor' => '#8b5cf6', 'defaultIconColor' => '#ffffff'],
        'admin'    => ['label' => '⛨ 管理员', 'defaultBadgeColor' => '#ef4444', 'defaultIconColor' => '#ffffff'],
        'hot'      => ['label' => '🔥 活跃',  'defaultBadgeColor' => '#f97316', 'defaultIconColor' => '#ffffff'],
        'star'     => ['label' => '⭐ 明星',  'defaultBadgeColor' => '#eab308', 'defaultIconColor' => '#ffffff'],
    ];
}

/**
 * 获取角标类型对应的 SVG path 内容
 *
 * @param string $type
 * @param string $iconColor  图标前景色（白色或自定义）
 * @return string  内联 SVG 字符串
 */
function getBadgeIconSvg(string $type, string $iconColor = '#ffffff'): string
{
    $c = htmlspecialchars($iconColor);
    switch ($type) {
        case 'verified':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="'.$c.'" stroke-width="3"'
                 . ' stroke-linecap="round" stroke-linejoin="round">'
                 . '<polyline points="20 6 9 17 4 12"/></svg>';
        case 'official':
            return '<svg viewBox="0 0 24 24" fill="'.$c.'">'
                 . '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02'
                 . ' 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
        case 'vip':
            return '<svg viewBox="0 0 24 24" fill="'.$c.'">'
                 . '<path d="M5 16L2 6l5.5 5L12 4l4.5 7L22 6l-3 10H5z"/>'
                 . '<rect x="5" y="18" width="14" height="2" rx="1"/></svg>';
        case 'admin':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="'.$c.'" stroke-width="2"'
                 . ' stroke-linecap="round" stroke-linejoin="round">'
                 . '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>';
        case 'hot':
            return '<svg viewBox="0 0 24 24" fill="'.$c.'">'
                 . '<path d="M12.36 2.02C10.05 5.27 13 8.65 11 12c-1.07-3.22-4-3-4-7'
                 . 'C4.14 7.29 3 10.35 3 13c0 5 4 9 9 9s9-4 9-9'
                 . 'c0-5.44-4.55-9.02-8.64-10.98z"/></svg>';
        case 'star':
            return '<svg viewBox="0 0 24 24" fill="'.$c.'">'
                 . '<path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61'
                 . 'L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>';
        default:
            return '<svg viewBox="0 0 24 24" fill="none" stroke="'.$c.'" stroke-width="3">'
                 . '<polyline points="20 6 9 17 4 12"/></svg>';
    }
}

/**
 * 获取角标类型中文标签
 */
function getBadgeLabel(string $type): string
{
    $types  = getAllBadgeTypes();
    return $types[$type]['label'] ?? '已认证';
}

// ──────────────────────────────────────────────
// HTML 渲染
// ──────────────────────────────────────────────

/**
 * 渲染带角标的头像 HTML
 *
 * @param string     $avatarUrl   头像图片 URL
 * @param string     $altText     alt 属性
 * @param array|null $badge       getUserBadge() 返回值
 * @param string     $imgClass    img 的 CSS class（默认 fb-avatar）
 * @param int        $dotSize     角标直径 px（默认 15）
 * @return string
 */
function renderAvatarWithBadge(
    string $avatarUrl,
    string $altText,
    ?array $badge,
    string $imgClass = 'fb-avatar',
    int    $dotSize  = 15
): string {
    $src = htmlspecialchars($avatarUrl);
    $alt = htmlspecialchars($altText);
    $img = '<img src="'.$src.'" alt="'.$alt.'" class="'.$imgClass.'">';

    if (!$badge) {
        return '<div class="ub-avatar-wrap" style="position:relative;display:inline-block;">'.$img.'</div>';
    }

    $badgeColor = htmlspecialchars($badge['badge_color']    ?? '#1d9bf0');
    $iconColor  = htmlspecialchars($badge['badge_icon_color'] ?? '#ffffff');
    $badgeType  = $badge['badge_type'] ?? 'verified';
    $iconSvg    = getBadgeIconSvg($badgeType, $iconColor);
    $label      = htmlspecialchars(getBadgeLabel($badgeType));

    $pad    = max(2, (int)($dotSize * 0.18));
    $border = max(1, (int)($dotSize * 0.14));

    return
        '<div class="ub-avatar-wrap" style="position:relative;display:inline-block;">'
        . $img
        . '<div class="ub-badge-dot" title="'.$label.'" style="'
        .   'position:absolute;bottom:-'.$border.'px;right:-'.$border.'px;'
        .   'width:'.$dotSize.'px;height:'.$dotSize.'px;border-radius:50%;'
        .   'background:'.$badgeColor.';'
        .   'border:'.$border.'px solid var(--comment-bg,#fff);'
        .   'display:flex;align-items:center;justify-content:center;'
        .   'box-shadow:0 1px 3px rgba(0,0,0,.25);overflow:hidden;'
        .   'pointer-events:none;">'
        .   '<span style="display:flex;align-items:center;justify-content:center;'
        .         'width:'.(int)($dotSize * 0.6).'px;height:'.(int)($dotSize * 0.6).'px;">'
        .     $iconSvg
        .   '</span>'
        . '</div>'
        . '</div>';
}

/**
 * 渲染用户头衔标签 HTML
 *
 * @param array|null $badge
 * @return string
 */
function renderUserTitle(?array $badge): string
{
    if (!$badge || trim($badge['title_text'] ?? '') === '') return '';

    $text    = htmlspecialchars(trim($badge['title_text']));
    $color   = htmlspecialchars($badge['title_color']    ?? '#6c5dfb');
    $bgColor = trim($badge['title_bg_color'] ?? '');

    if ($bgColor !== '') {
        $bg = 'background:'.htmlspecialchars($bgColor).';padding:1px 6px;border-radius:20px;';
    } else {
        $bg = '';
    }

    return '<span class="ub-user-title" style="'
         . 'font-size:.72em;font-weight:700;color:'.$color.';'
         . $bg
         . 'margin-left:4px;vertical-align:middle;white-space:nowrap;">'
         . $text
         . '</span>';
}