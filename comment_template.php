<?php
/**
 * comment_template.php — 评论渲染模板（含角标 & 头衔）
 *
 * 放置路径：根目录（与 article.php 同级）
 *
 * 依赖：
 *   - badge_functions.php 中的 getUserBadge() / renderAvatarWithBadge() / renderUserTitle()
 *   - comment_functions.php 中的 getCommentAvatar() / getParentComment()
 *   在引入本模板之前，上述函数必须已加载。
 *
 * 可用变量（由调用方传入）：
 *   $comment  — 当前评论数据数组（含 replies 子数组）
 *   $depth    — 当前嵌套深度（首次调用可缺省，默认 0）
 */

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', __DIR__);
}

// 懒加载角标函数（若 article.php 已 require 则跳过）
if (!function_exists('getUserBadge')) {
    require_once ROOT_DIR . '/admin/badge_functions.php';
}

// ── 深度初始化 ────────────────────────────────────────
if (!isset($depth)) $depth = 0;

// ── 解析 @reply 前缀 & 回复对象 ──────────────────────
$isReply        = !empty($comment['parent_id']);
$displayContent = trim($comment['content']);
$replyToName    = null;

if ($isReply) {
    if (strpos($displayContent, '@') === 0) {
        preg_match('/^@([^\s]+)\s*/', $displayContent, $m);
        if (!empty($m[0])) {
            $replyToName    = $m[1];
            $displayContent = ltrim(substr($displayContent, strlen($m[0])));
        }
    }
    if ($replyToName === null) {
        $parentComment = getParentComment($comment['parent_id']);
        $replyToName   = $parentComment ? $parentComment['name'] : '未知用户';
    }
}

// ── 获取头像 URL ──────────────────────────────────────
// email 可能为空（手机号注册用户），getCommentAvatar 以 userId 为主要查询依据
$userId    = isset($comment['user_id']) ? (int)$comment['user_id'] : 0;
$avatarUrl = getCommentAvatar($comment['email'] ?? '', $userId);

// ── 获取角标配置 ──────────────────────────────────────
$badge = ($userId > 0) ? getUserBadge($userId) : null;
?>
<div class="fb-comment <?php echo $isReply ? 'fb-reply' : 'fb-top-comment'; ?>"
     id="comment_<?php echo (int)$comment['id']; ?>">

    <div class="fb-comment-head">

        <!-- 头像（含角标圆点） -->
        <?php echo renderAvatarWithBadge(
            $avatarUrl,
            $comment['name'] ?? '',
            $badge,
            'fb-avatar',
            16   // 角标直径 px
        ); ?>

        <div class="fb-meta">
            <!-- 昵称 + 头衔 -->
            <span class="fb-name">
                <?php echo htmlspecialchars($comment['name'] ?? ''); ?>
                <?php echo renderUserTitle($badge); ?>
            </span>
            <span class="fb-date"><?php echo htmlspecialchars($comment['created_at'] ?? ''); ?></span>
        </div>

    </div><!-- /.fb-comment-head -->

    <div class="fb-comment-body">

        <!-- 回复标记 -->
        <?php if ($isReply && $replyToName): ?>
        <div class="fb-reply-badge">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.5" style="vertical-align:middle;margin-right:3px;">
                <polyline points="9 17 4 12 9 7"/>
                <path d="M20 18v-2a4 4 0 0 0-4-4H4"/>
            </svg>
            回复 <span class="fb-reply-to-name">@<?php echo htmlspecialchars($replyToName); ?></span>
        </div>
        <?php endif; ?>

        <!-- 评论正文 -->
        <div class="fb-content"><?php echo nl2br(htmlspecialchars($displayContent)); ?></div>

        <!-- 操作按钮 -->
        <div class="fb-actions">
            <a href="#" class="reply-link"
               data-comment-id="<?php echo (int)$comment['id']; ?>"
               data-comment-name="<?php echo htmlspecialchars($comment['name'] ?? ''); ?>">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" style="vertical-align:middle;margin-right:3px;">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>回复
            </a>
        </div>

    </div><!-- /.fb-comment-body -->

    <!-- 子评论（递归渲染） -->
    <?php if (!empty($comment['replies'])): ?>
    <div class="fb-replies">
        <?php
        $depth++;
        foreach ($comment['replies'] as $reply):
            $comment_bak = $comment;
            $comment     = $reply;
        ?>
            <?php include __DIR__ . '/comment_template.php'; ?>
        <?php
            $comment = $comment_bak;
        endforeach;
        $depth--;
        ?>
    </div>
    <?php endif; ?>

</div><!-- /.fb-comment -->