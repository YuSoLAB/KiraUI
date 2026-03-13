<?php
// Resolve depth for nested rendering
if (!isset($depth)) $depth = 0;

// Parse reply-to info and strip @mention prefix from content
$isReply = !empty($comment['parent_id']);
$displayContent = trim($comment['content']);
$replyToName = null;

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
?>
<div class="fb-comment <?php echo $isReply ? 'fb-reply' : 'fb-top-comment'; ?>"
     id="comment_<?php echo $comment['id']; ?>">

    <div class="fb-comment-head">
        <img src="<?php echo getCommentAvatar($comment['email']); ?>"
             alt="<?php echo htmlspecialchars($comment['name'] ?? ''); ?>"
             class="fb-avatar">
        <div class="fb-meta">
            <span class="fb-name"><?php echo htmlspecialchars($comment['name'] ?? ''); ?></span>
            <span class="fb-date"><?php echo $comment['created_at']; ?></span>
        </div>
    </div>

    <div class="fb-comment-body">
        <?php if ($isReply && $replyToName): ?>
        <div class="fb-reply-badge">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle;margin-right:3px;"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
            回复 <span class="fb-reply-to-name">@<?php echo htmlspecialchars($replyToName); ?></span>
        </div>
        <?php endif; ?>
        <div class="fb-content"><?php echo nl2br(htmlspecialchars($displayContent)); ?></div>
        <div class="fb-actions">
            <a href="#" class="reply-link"
               data-comment-id="<?php echo $comment['id']; ?>"
               data-comment-name="<?php echo htmlspecialchars($comment['name'] ?? ''); ?>">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:3px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>回复
            </a>
        </div>
    </div>

    <?php if (!empty($comment['replies'])): ?>
    <div class="fb-replies">
        <?php
        $depth++;
        foreach ($comment['replies'] as $reply):
            $comment_bak = $comment;
            $comment = $reply;
        ?>
            <?php include 'comment_template.php'; ?>
        <?php
            $comment = $comment_bak;
        endforeach;
        $depth--;
        ?>
    </div>
    <?php endif; ?>
</div>