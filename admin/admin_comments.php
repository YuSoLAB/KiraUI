<?php
require_once 'comment_functions.php';
$commentSettings = initCommentSettings();
function displayPendingCommentAdmin($comment, $articleId) {
?>
<div class="comment-item pending">
    <div class="comment-header">
        <img src="<?php echo getCommentAvatar($comment['email']); ?>" 
             alt="<?php echo $comment['name']; ?>" class="comment-avatar">
        <div>
            <div class="comment-name">
                <?php echo $comment['name']; ?>
                <small><?php echo $comment['email']; ?></small>
            </div>
            <div class="comment-date">
                <?php echo $comment['created_at']; ?>
                <span class="pending-badge">待审核</span>
            </div>
        </div>
    </div>
    <div class="comment-content">
        <?php echo $comment['content']; ?>
    </div>
    <div class="comment-actions">
        <form method="post" style="display: inline;">
            <input type="hidden" name="comment_action" value="approve">
            <input type="hidden" name="article_id" value="<?php echo $articleId; ?>">
            <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
            <button type="submit" class="btn btn-success btn-sm">批准</button>
        </form>
        
        <form method="post" style="display: inline;">
            <input type="hidden" name="comment_action" value="reject">
            <input type="hidden" name="article_id" value="<?php echo $articleId; ?>">
            <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
            <button type="submit" class="btn btn-warning btn-sm">拒绝并删除</button>
        </form>
    </div>
    
    <?php if (!empty($comment['replies'])): ?>
    <div class="replies">
        <?php foreach ($comment['replies'] as $reply): ?>
            <?php displayPendingCommentAdmin($reply, $articleId); ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php
}

function displayApprovedCommentAdmin($comment, $articleId) {
?>
<div class="comment-item approved">
    <div class="comment-header">
        <img src="<?php echo getCommentAvatar($comment['email']); ?>" 
             alt="<?php echo $comment['name']; ?>" class="comment-avatar">
        <div>
            <div class="comment-name">
                <?php echo $comment['name']; ?>
                <small><?php echo $comment['email']; ?></small>
            </div>
            <div class="comment-date">
                <?php echo $comment['created_at']; ?>
                <span class="approved-badge">已通过</span>
            </div>
        </div>
    </div>
    <div class="comment-content">
        <?php echo $comment['content']; ?>
    </div>
    <div class="comment-actions">
        <form method="post" style="display: inline;" onsubmit="return confirm('确定要删除这条评论吗？');">
            <input type="hidden" name="comment_action" value="delete">
            <input type="hidden" name="article_id" value="<?php echo $articleId; ?>">
            <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
            <button type="submit" class="btn btn-danger btn-sm">删除</button>
        </form>
    </div>
    
    <?php if (!empty($comment['replies'])): ?>
    <div class="replies">
        <?php foreach ($comment['replies'] as $reply): ?>
            <?php displayApprovedCommentAdmin($reply, $articleId); ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_action'])) {
    $action = $_POST['comment_action'];
    $articleId = $_POST['article_id'] ?? 0;
    $commentId = $_POST['comment_id'] ?? '';
    
    switch ($action) {
        case 'approve':
            moderateComment($articleId, $commentId, true);
            $message = "评论已批准";
            break;
        case 'reject':
            deleteComment($articleId, $commentId);
            $message = "评论已拒绝";
            break;
        case 'delete':
            deleteComment($articleId, $commentId);
            $message = "评论已删除";
            break;
        case 'update_email_mode':
            $emailHash = $_POST['email_hash'] ?? '';
            $mode = $_POST['mode'] ?? 'strict';
            if (updateEmailModeration($emailHash, $mode)) {
                $message = "邮箱审核模式已更新";
            } else {
                $message = "更新邮箱审核模式失败";
            }
            break;
        case 'save_settings':
            $newSettings = [
                'email_mode' => $_POST['email_mode'] ?? 'all',
                'allowed_domains' => isset($_POST['allowed_domains']) ? explode("\n", $_POST['allowed_domains']) : [],
                'blocked_domains' => isset($_POST['blocked_domains']) ? explode("\n", $_POST['blocked_domains']) : [],
                'default_moderation' => $_POST['default_moderation'] ?? 'strict',
                'enable_comments' => isset($_POST['enable_comments']) ? true : false,
                'allow_guest_comments' => isset($_POST['allow_guest_comments']) ? true : false
            ];        
            $newSettings['allowed_domains'] = array_filter(array_map('trim', $newSettings['allowed_domains']));
            $newSettings['blocked_domains'] = array_filter(array_map('trim', $newSettings['blocked_domains']));
            
            if (saveCommentSettings($newSettings)) {
                $commentSettings = $newSettings;
                $message = "评论设置已保存";
            } else {
                $message = "保存设置失败";
            }
            break;
    }
}

$db = Db::getInstance();
$stmt = $db->query("SELECT c.*, a.title FROM comments c
                   LEFT JOIN articles a ON c.article_id = a.id
                   ORDER BY c.created_at DESC");
$allDbComments = $stmt->fetchAll();
$articleComments = [];
foreach ($allDbComments as $comment) {
    $articleId = $comment['article_id'];
    if (!isset($articleComments[$articleId])) {
        $articleComments[$articleId] = [
            'id' => $articleId,
            'title' => $comment['title'] ?? '未知文章',
            'comments' => [],
            'emails' => []
        ];
    }
    $articleComments[$articleId]['comments'][] = $comment;
}

$allComments = ['pending' => [], 'approved' => []];
foreach ($articleComments as $article) {
    $pending = array_filter($article['comments'], function($c) {
        return $c['approved'] == 0;
    });
    $approved = array_filter($article['comments'], function($c) {
        return $c['approved'] == 1;
    });
    if (!empty($pending)) {
        $allComments['pending'][$article['id']] = $article;
        $allComments['pending'][$article['id']]['comments'] = $pending;
    }
    if (!empty($approved)) {
        $allComments['approved'][$article['id']] = $article;
        $allComments['approved'][$article['id']]['comments'] = $approved;
    }
}

$allEmails = [];
foreach ($allDbComments as $comment) {
    $emailHash = $comment['email_hash'] ?? '';
    $email = $comment['email'] ?? '';
    if (!empty($emailHash) && !isset($allEmails[$emailHash])) {
        $moderationMode = 'strict';
        $files = glob(COMMENTS_DIR . 'article_*.json');
        foreach ($files as $file) {
            $commentsData = json_decode(file_get_contents($file), true);
            if (isset($commentsData['emails'][$emailHash]['moderation'])) {
                $moderationMode = $commentsData['emails'][$emailHash]['moderation'];
                break;
            }
        }
        $allEmails[$emailHash] = [
            'email' => $email,
            'moderation' => $moderationMode,
            'hash' => $emailHash
        ];
    }
}
$allEmails = array_values($allEmails);
?>
<div class="tab-content" id="comments">
    <div class="section">
        <h2>评论管理</h2>
        
        <?php if (isset($message) && !empty(trim($message))): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        <div class="settings-card">
            <h3>评论设置</h3>
            <form method="post">
                <input type="hidden" name="comment_action" value="save_settings">
                
                <div class="form-group">
                    <label style="display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; margin-bottom: 15px;">
                        <input type="checkbox" name="enable_comments" <?php echo $commentSettings['enable_comments'] ? 'checked' : ''; ?>>
                        启用评论功能
                    </label>
                </div>
                
                <div class="form-group">
                    <label style="display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; margin-bottom: 15px;">
                        <input type="checkbox" name="allow_guest_comments" <?php echo isset($commentSettings['allow_guest_comments']) && $commentSettings['allow_guest_comments'] ? 'checked' : ''; ?>>
                        允许游客评论
                    </label>
                    <small>不勾选时，只有登录用户可以评论</small>
                </div>

                <div class="form-group">
                    <label for="default_moderation">默认审核模式</label>
                    <select id="default_moderation" name="default_moderation">
                        <option value="strict" <?php echo $commentSettings['default_moderation'] == 'strict' ? 'selected' : ''; ?>>
                            严格模式（每条评论都需要审核）
                        </option>
                        <option value="auto" <?php echo $commentSettings['default_moderation'] == 'auto' ? 'selected' : ''; ?>>
                            自动模式（首次需要审核，之后自动通过）
                        </option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="email_mode">邮箱过滤模式</label>
                    <select id="email_mode" name="email_mode">
                        <option value="all" <?php echo $commentSettings['email_mode'] == 'all' ? 'selected' : ''; ?>>
                            允许所有邮箱
                        </option>
                        <option value="whitelist" <?php echo $commentSettings['email_mode'] == 'whitelist' ? 'selected' : ''; ?>>
                            仅允许白名单邮箱
                        </option>
                        <option value="blacklist" <?php echo $commentSettings['email_mode'] == 'blacklist' ? 'selected' : ''; ?>>
                            禁止黑名单邮箱
                        </option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="allowed_domains">允许的邮箱域名（每行一个）</label>
                    <textarea id="allowed_domains" name="allowed_domains" rows="5"><?php echo implode("\n", $commentSettings['allowed_domains']); ?></textarea>
                    <small>仅在白名单模式下生效</small>
                </div>
                
                <div class="form-group">
                    <label for="blocked_domains">禁止的邮箱域名（每行一个）</label>
                    <textarea id="blocked_domains" name="blocked_domains" rows="5"><?php echo implode("\n", $commentSettings['blocked_domains']); ?></textarea>
                    <small>在黑名单模式下生效</small>
                </div>
                
                <button type="submit" class="btn btn-primary">保存设置</button>
            </form>
        </div>
        
        <div class="comments-management">
            <h3>待审核评论</h3>
            
            <?php if (empty($allComments['pending'])): ?>
                <p>暂无待审核评论</p>
            <?php else: ?>
                <?php foreach ($allComments['pending'] as $article): ?>
                <div class="article-comments">
                    <h4>
                        文章: <a href="../article.php?id=<?php echo $article['id']; ?>" target="_blank">
                            <?php echo $article['title']; ?>
                        </a>
                    </h4>
                    
                    <?php foreach ($article['comments'] as $comment): ?>
                        <?php displayPendingCommentAdmin($comment, $article['id']); ?>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="comments-management">
            <h3>已通过评论</h3>
            
            <?php if (empty($allComments['approved'])): ?>
                <p>暂无已通过评论</p>
            <?php else: ?>
                <?php foreach ($allComments['approved'] as $article): ?>
                <div class="article-comments">
                    <h4>
                        文章: <a href="../article.php?id=<?php echo $article['id']; ?>" target="_blank">
                            <?php echo $article['title']; ?>
                        </a>
                    </h4>
                    
                    <?php foreach ($article['comments'] as $comment): ?>
                        <?php displayApprovedCommentAdmin($comment, $article['id']); ?>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="email-management">
            <h3>邮箱管理</h3>
            <table class="email-table">
                <thead>
                    <tr>
                        <th>邮箱</th>
                        <th>状态</th>
                        <th>审核模式</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $allEmails = [];                    
                    $db = Db::getInstance();
                    $stmt = $db->query("
                        SELECT DISTINCT email, email_hash, 
                            (SELECT approved FROM comments c2 WHERE c2.email_hash = c1.email_hash ORDER BY created_at DESC LIMIT 1) as latest_status
                        FROM comments c1
                        WHERE email_hash IS NOT NULL AND email_hash != ''
                    ");
                    $uniqueEmails = $stmt->fetchAll();
                    
                    foreach ($uniqueEmails as $emailData):
                        $emailHash = $emailData['email_hash'];
                        $email = $emailData['email'];
                        $moderationMode = 'strict';
                        $stmt = $db->prepare("SELECT moderation FROM email_moderation WHERE email_hash = ?");
                        $stmt->execute([$emailHash]);
                        $result = $stmt->fetch();
                        if ($result) {
                            $moderationMode = $result['moderation'];
                        }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($email); ?></td>
                        <td><?php echo $emailData['latest_status'] ? '已通过' : '待审核'; ?></td>
                        <td><?php echo $moderationMode == 'strict' ? '严格审核' : '自动通过'; ?></td>
                        <td>
                            <form method="post">
                                <input type="hidden" name="comment_action" value="update_email_mode">
                                <input type="hidden" name="email_hash" value="<?php echo $emailHash; ?>">
                                <input type="hidden" name="mode" value="<?php echo $moderationMode == 'strict' ? 'auto' : 'strict'; ?>">
                                <button type="submit" class="btn btn-sm <?php echo $moderationMode == 'strict' ? 'btn-success' : 'btn-warning'; ?>">
                                    <?php echo $moderationMode == 'strict' ? '改为自动通过' : '改为严格审核'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($uniqueEmails)): ?>
                    <tr>
                        <td colspan="4">暂无评论邮箱记录</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>