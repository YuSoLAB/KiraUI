<div class="comment-item <?php echo isset($depth) && $depth > 0 ? 'reply-comment' : ''; ?>" id="comment_<?php echo $comment['id']; ?>">
    <div class="comment-header">
        <img src="<?php echo getCommentAvatar($comment['email']); ?>" 
             alt="<?php echo $comment['name']; ?>" class="comment-avatar">
        <div>
            <div class="comment-name"><?php echo $comment['name']; ?></div>
            <div class="comment-date"><?php echo $comment['created_at']; ?></div>
        </div>
    </div>
    <div class="comment-content">
        <?php 
        $isReply = !empty($comment['parent_id']);
        if ($isReply) {
            if (strpos($comment['content'], '@') === 0) {
                preg_match('/^@([^\s]+)/', $comment['content'], $matches);
                if (!empty($matches[1])) {
                    echo '<span class="comment-reply-to">@' . $matches[1] . '</span> ';
                    echo substr($comment['content'], strlen($matches[0]) + 1);
                } else {
                    $parentComment = getParentComment($comment['parent_id']);
                    $parentName = $parentComment ? $parentComment['name'] : '未知用户';
                    echo '<span class="comment-reply-to">@' . $parentName . '</span> ';
                    echo $comment['content'];
                }
            } else {
                $parentComment = getParentComment($comment['parent_id']);
                $parentName = $parentComment ? $parentComment['name'] : '未知用户';
                echo '<span class="comment-reply-to">@' . $parentName . '</span> ';
                echo $comment['content'];
            }
        } else {
            echo $comment['content'];
        }
        ?>
    </div>
    <div class="comment-actions">
        <a href="#" class="reply-link" 
           data-comment-id="<?php echo $comment['id']; ?>"
           data-comment-name="<?php echo $comment['name']; ?>">回复</a>
    </div>
    <?php if (!empty($comment['replies'])): ?>
    <div class="replies">
        <?php foreach ($comment['replies'] as $reply): ?>
            <?php include 'comment_template.php'; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>