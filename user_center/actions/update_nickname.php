<?php
/**
 * 修改昵称
 * 依赖：$db、$user、$isBanned、$_POST
 * 若后台已启用信息变更审核，变更将进入待审核队列，审核通过后才生效。
 */

$tab = $_POST['active_tab'] ?? 'profile';

if ($isBanned) {
    $_SESSION['error'] = '您的账号已被封禁，无法修改个人信息';
    header("Location: index.php?tab=$tab");
    exit;
}

$newNickname = trim($_POST['nickname'] ?? '');

if ($newNickname === '' || mb_strlen($newNickname) > 50) {
    $_SESSION['error'] = '请输入有效的昵称（不超过50个字符）';
    header("Location: index.php?tab=$tab");
    exit;
}

// 昵称未发生变化则无需处理
$currentNickname = $user['nickname'] ?? '';
if ($newNickname === $currentNickname) {
    $_SESSION['message'] = '昵称未发生变化';
    header("Location: index.php?tab=$tab");
    exit;
}

require_once __DIR__ . '/../../include/Config.php';
$reviewEnabled = Config::getInstance()->get('profile_review_enabled', '0') === '1';

if ($reviewEnabled) {
    // ── 审核模式：写入待审核队列 ──────────────────────────────
    // 若该用户已有 pending 的昵称申请，先撤销旧的（同类型只保留最新一条）
    $db->prepare(
        "UPDATE pending_profile_changes SET status='rejected', reject_reason='已被新申请替代', reviewed_at=NOW()
          WHERE user_id=? AND type='nickname' AND status='pending'"
    )->execute([$user['id']]);

    $db->prepare(
        "INSERT INTO pending_profile_changes (user_id, type, new_value) VALUES (?, 'nickname', ?)"
    )->execute([$user['id'], $newNickname]);

    $_SESSION['message'] = '昵称变更已提交，等待管理员审核后生效';
} else {
    // ── 直接生效模式 ───────────────────────────────────────────
    $stmt = $db->prepare("UPDATE users SET nickname = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    if ($stmt->execute([$newNickname, $user['id']])) {
        $_SESSION['user']['nickname'] = $newNickname;
        $_SESSION['message'] = '昵称更新成功';
    } else {
        $_SESSION['error'] = '昵称更新失败';
    }
}

header("Location: index.php?tab=$tab");
exit;