<?php
require_once dirname(__DIR__) . '/include/Db.php';
require_once __DIR__ . '/admin_functions.php';
if (!defined('COMMENT_SETTINGS_FILE')) {
    if (!defined('ROOT_DIR')) {
        define('ROOT_DIR', dirname(__DIR__));
    }
    define('COMMENT_SETTINGS_FILE', ROOT_DIR . '/cache/comment_settings.php');
    define('COMMENTS_DIR', ROOT_DIR . '/cache/comments/');
    if (!file_exists(COMMENTS_DIR)) {
        mkdir(COMMENTS_DIR, 0755, true);
    }
}
function initCommentSettings() {
    $db = Db::getInstance();
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS comment_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            email_mode VARCHAR(20) NOT NULL DEFAULT 'all',
            allowed_domains TEXT,
            blocked_domains TEXT,
            default_moderation VARCHAR(20) NOT NULL DEFAULT 'strict',
            enable_comments TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    } catch (PDOException $e) {
        error_log("创建评论设置表错误: " . $e->getMessage());
    }
    $defaultSettings = [
        'email_mode' => 'all',
        'allowed_domains' => [],
        'blocked_domains' => [],
        'default_moderation' => 'strict',
        'enable_comments' => true,
        'allow_guest_comments' => true, 
    ];
    $db = Db::getInstance();
    try {
        $stmt = $db->query("SELECT * FROM comment_settings LIMIT 1");
        $saved = $stmt->fetch();
        if ($saved) {
            $allowedDomains = !empty($saved['allowed_domains']) ? explode("\n", $saved['allowed_domains']) : [];
            $blockedDomains = !empty($saved['blocked_domains']) ? explode("\n", $saved['blocked_domains']) : [];            
            return [
                'email_mode' => $saved['email_mode'],
                'allowed_domains' => $allowedDomains,
                'blocked_domains' => $blockedDomains,
                'default_moderation' => $saved['default_moderation'],
                'enable_comments' => (bool)$saved['enable_comments'],
                'allow_guest_comments' => isset($saved['allow_guest_comments']) ? (bool)$saved['allow_guest_comments'] : true, 
            ];
        }
    } catch (PDOException $e) {
        error_log("读取评论设置错误: " . $e->getMessage());
    }   
    saveCommentSettings($defaultSettings);
    return $defaultSettings;
}
function getArticleCommentsFile($articleId) {
    return COMMENTS_DIR . 'article_' . intval($articleId) . '.json';
}
function initArticleComments($articleId) {
    $file = getArticleCommentsFile($articleId);
    if (!file_exists($file)) {
        $comments = [
            'emails' => [],
            'comments' => []
        ];
        file_put_contents($file, json_encode($comments, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
function getArticleComments($articleId) {
    $db = Db::getInstance();
    $stmt = $db->prepare("SELECT * FROM comments 
        WHERE article_id = ? AND parent_id IS NULL AND approved = 1
        ORDER BY created_at DESC");
    $stmt->execute([$articleId]);
    $comments = $stmt->fetchAll();
    foreach ($comments as &$comment) {
        $comment['replies'] = getCommentReplies($comment['id']);
    }
    return ['comments' => $comments];
}
function getCommentReplies($commentId) {
    $db = Db::getInstance();
    $stmt = $db->prepare("SELECT * FROM comments 
        WHERE parent_id = ? AND approved = 1 
        ORDER BY created_at ASC");
    $stmt->execute([$commentId]);
    $replies = $stmt->fetchAll();    
    foreach ($replies as &$reply) {
        $reply['replies'] = getCommentReplies($reply['id']);
    }
    return $replies;
}
function saveCommentSettings($settings) {
    $db = Db::getInstance();
    $allowedDomains = implode("\n", $settings['allowed_domains'] ?? []);
    $blockedDomains = implode("\n", $settings['blocked_domains'] ?? []);
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS comment_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            email_mode ENUM('all', 'whitelist', 'blacklist') NOT NULL DEFAULT 'all',
            allowed_domains TEXT,
            blocked_domains TEXT,
            default_moderation ENUM('strict', 'auto') NOT NULL DEFAULT 'strict',
            enable_comments TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        $sql = "
            INSERT INTO comment_settings
            (id, email_mode, allowed_domains, blocked_domains, default_moderation, enable_comments, allow_guest_comments)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                email_mode = ?,
                allowed_domains = ?,
                blocked_domains = ?,
                default_moderation = ?,
                enable_comments = ?,
                allow_guest_comments = ?
        ";
        $stmt = $db->prepare($sql);
        $values = [
            1,
            $settings['email_mode'] ?? 'all',
            $allowedDomains,
            $blockedDomains,
            $settings['default_moderation'] ?? 'strict',
            $settings['enable_comments'] ? 1 : 0,
            $settings['allow_guest_comments'] ? 1 : 0, 
            $settings['email_mode'] ?? 'all',
            $allowedDomains,
            $blockedDomains,
            $settings['default_moderation'] ?? 'strict',
            $settings['enable_comments'] ? 1 : 0,
            $settings['allow_guest_comments'] ? 1 : 0 
        ];
        $stmt->execute($values);
        return true;
    } catch (PDOException $e) {
        error_log("保存评论设置失败: " . $e->getMessage());
        return false;
    }
}
function isEmailAllowed($email, $settings) {
    $domain = substr(strrchr($email, "@"), 1);
    if (in_array($domain, $settings['blocked_domains'])) {
        return false;
    }
    if ($settings['email_mode'] == 'whitelist' && !in_array($domain, $settings['allowed_domains'])) {
        return false;
    }   
    return true;
}
/**
 * 生成纯 SVG Data URI 头像，无需任何外部请求。
 *
 * @param  string $text  显示的文字（取首字）
 * @param  int    $size  尺寸（px）
 * @return string        data:image/svg+xml;base64,… 格式的 URI
 */
function generateSvgAvatar(string $text, int $size = 64): string {
    // 根据文字哈希选色，让不同用户有不同背景色
    $colors = ['#5C6BC0','#26A69A','#EF5350','#AB47BC','#42A5F5','#FF7043','#66BB6A','#FFA726'];
    $bg     = $colors[abs(crc32($text)) % count($colors)];

    $letter   = mb_strtoupper(mb_substr($text, 0, 1, 'UTF-8'), 'UTF-8');
    $fontSize = (int)($size * 0.45);

    $svg = sprintf(
        '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 %1$d %1$d">'
        . '<rect width="%1$d" height="%1$d" rx="%2$d" fill="%3$s"/>'
        . '<text x="50%%" y="50%%" dominant-baseline="central" text-anchor="middle" '
        . 'font-family="sans-serif" font-size="%4$d" fill="#fff">%5$s</text>'
        . '</svg>',
        $size,
        (int)($size / 2),
        $bg,
        $fontSize,
        htmlspecialchars($letter, ENT_XML1, 'UTF-8')
    );

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function getCommentAvatar($email, $userId = null) {
    if ($userId) {
        try {
            $db = Db::getInstance();
            $stmt = $db->prepare("SELECT avatar FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $avatar = $stmt->fetchColumn();
            if (!empty($avatar) && file_exists(ROOT_DIR . '/uploads/avatars/' . $avatar)) {
                return 'uploads/avatars/' . $avatar;
            }
        } catch (PDOException $e) {
        }
    }
    if (preg_match('/^(\d+)@(qq\.com|vip\.qq\.com)$/', $email, $matches)) {
        return 'https://q1.qlogo.cn/g?b=qq&nk=' . $matches[1] . '&s=640';
    }
    // 取邮箱 @ 前的部分作为显示文字，生成本地 SVG 头像
    $label = strstr($email, '@', true) ?: 'G';
    return generateSvgAvatar($label, 64);
}
function addNewComment($articleId, $data) {
    $settings = initCommentSettings();
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $isLoggedIn = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
    if ($isLoggedIn) {
        $email = $_SESSION['user']['email'] ?? '';
        $name = $_SESSION['user']['nickname'];
        $status = checkUserStatus($_SESSION['user']['id']);
        if ($status == 'banned') {
            return ['success' => false, 'message' => '您的账号已被封禁，无法发表评论'];
        }
    } else {
        $email = $data['email'] ?? '';
        $name = $data['name'] ?? '';
        if (empty($email) || empty($name)) {
            return ['success' => false, 'message' => '请填写昵称和邮箱'];
        }
    }
    if (!$isLoggedIn) {
        if (!$settings['allow_guest_comments']) {
            return ['success' => false, 'message' => '请先登录再发表评论'];
        }
        if (!isEmailAllowed($email, $settings)) {
            return ['success' => false, 'message' => '该邮箱不允许发送评论'];
        }
    }
    if (empty($settings['enable_comments'])) {
        return ['success' => false, 'message' => '评论功能已关闭'];
    }
    // 登录用户若未绑定邮箱（手机号注册），跳过邮箱域名校验
    if (!($isLoggedIn && $email === '') && !isEmailAllowed($email, $settings)) {
        return ['success' => false, 'message' => '该邮箱不允许发送评论'];
    }
    $db = Db::getInstance();
    $emailHash = md5(strtolower(trim($email)));
    $name = htmlspecialchars($name);  
    $content = processCommentContent($data['content'] ?? '');
    $parentId = empty($data['parent_id']) || $data['parent_id'] == '0' ? null : $data['parent_id'];
    $needsModeration = true; 
    $stmt_email = $db->prepare("SELECT moderation FROM email_moderation WHERE email_hash = ?");
    $stmt_email->execute([$emailHash]);
    $emailMode = $stmt_email->fetchColumn();
    if ($emailMode === 'auto') {
        $needsModeration = false;
    } elseif ($emailMode === 'strict') {
        $needsModeration = true;
    } elseif (!$emailMode) {
        if ($settings['default_moderation'] === 'auto') {
            $stmt_check = $db->prepare("SELECT 1 FROM comments WHERE email_hash = ? AND approved = 1 LIMIT 1");
            $stmt_check->execute([$emailHash]);
            if ($stmt_check->fetchColumn()) {
                $needsModeration = false;
            } else {
                $needsModeration = true;
            }
        } else {
            $needsModeration = true;
        }
    }
    // ── 获取登录用户 ID（访客为 null）─────────────────────────
    $userId = $isLoggedIn ? (int)$_SESSION['user']['id'] : null;

    try {
        $stmt = $db->prepare("INSERT INTO comments 
            (article_id, parent_id, user_id, name, email, email_hash, content, approved)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $articleId,
            $parentId,
            $userId,        // ← 修复 Bug 2：保存登录用户 ID
            $name,
            $email,
            $emailHash,
            $content,
            $needsModeration ? 0 : 1
        ]);
        $newCommentId = $db->lastInsertId();

        // 若是回复且已通过审核，立即向被回复用户发送站内通知
        if ($parentId !== null && !$needsModeration) {
            createReplyNotification($parentId, $newCommentId, $articleId);
        }

        return [
            'success'          => true,
            'message'          => $needsModeration ? '评论已提交，等待审核' : '评论已发布',
            'needs_moderation' => $needsModeration,
            'approved'         => !$needsModeration, // ← 修复 Bug 3：统一键名，comment_ajax.php 直接可用
            'comment_id'       => $newCommentId,     // ← 修复 Bug 3：统一键名
            'new_comment_id'   => $newCommentId,     // 保留旧键，向后兼容
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => '评论提交失败: ' . $e->getMessage()];
    }
}
function addReplyToComment(&$comments, $reply) {
    if ($reply['id'] == $reply['parent_id']) {
        return false;
    }    
    foreach ($comments as &$comment) {
        if ($comment['id'] == $reply['id']) {
            continue;
        }
        if ($comment['id'] == $reply['parent_id']) {
            array_unshift($comment['replies'], $reply);
            return true;
        }
        if (!empty($comment['replies']) && addReplyToComment($comment['replies'], $reply)) {
            return true;
        }
    }
    return false;
}
function moderateComment($articleId, $commentId, $approved) {
    $db = Db::getInstance();
    try {
        // approved: 1 = 已通过, 0 = 待审, -1 = 已拒绝
        $approvedValue = $approved ? 1 : -1;
        $stmt = $db->prepare("UPDATE comments SET approved = ? WHERE id = ? AND article_id = ?");
        $result = $stmt->execute([$approvedValue, $commentId, $articleId]);

        // 审核通过时，若是回复评论则补发通知
        if ($result && $approved) {
            $stmt2 = $db->prepare("SELECT parent_id FROM comments WHERE id = ?");
            $stmt2->execute([$commentId]);
            $parentId = $stmt2->fetchColumn();
            if ($parentId) {
                createReplyNotification($parentId, $commentId, $articleId);
            }
        }
        return $result;
    } catch (PDOException $e) {
        error_log("审核评论错误: " . $e->getMessage());
        return false;
    }
}
function moderateCommentRecursive(&$comments, $commentId, $approved) {
    foreach ($comments as &$comment) {
        if ($comment['id'] == $commentId) {
            $comment['approved'] = $approved;
            return [
                'found' => true,
                'email_hash' => $comment['email_hash']
            ];
        }
        if (!empty($comment['replies'])) {
            $result = moderateCommentRecursive($comment['replies'], $commentId, $approved);
            if ($result['found']) {
                return $result;
            }
        }
    }
    return ['found' => false];
}
function deleteComment($articleId, $commentId) {
    $db = Db::getInstance();
    try {
        $childComments = getChildComments($commentId);
        foreach ($childComments as $childId) {
            deleteComment($articleId, $childId);
        }
        $stmt = $db->prepare("DELETE FROM comments WHERE id = ? AND article_id = ?");
        return $stmt->execute([$commentId, $articleId]);
    } catch (PDOException $e) {
        error_log("删除评论错误: " . $e->getMessage());
        return false;
    }
}
function getChildComments($parentId) {
    $db = Db::getInstance();
    $stmt = $db->prepare("SELECT id FROM comments WHERE parent_id = ?");
    $stmt->execute([$parentId]);
    $children = $stmt->fetchAll(PDO::FETCH_COLUMN);    
    $allChildren = [];
    foreach ($children as $childId) {
        $allChildren[] = $childId;
        $allChildren = array_merge($allChildren, getChildComments($childId));
    }
    return $allChildren;
}
// ─── 通知相关 ────────────────────────────────────────────────────────────────

/**
 * 初始化通知表（首次使用时自动建表）
 */
function initNotificationsTable() {
    $db = Db::getInstance();
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS notifications (
            id         INT NOT NULL AUTO_INCREMENT,
            user_id    INT NOT NULL,
            type       ENUM('reply') NOT NULL DEFAULT 'reply',
            comment_id INT NOT NULL,
            article_id INT NOT NULL,
            is_read    TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_read (user_id, is_read),
            CONSTRAINT fk_notif_user    FOREIGN KEY (user_id)    REFERENCES users    (id) ON DELETE CASCADE,
            CONSTRAINT fk_notif_comment FOREIGN KEY (comment_id) REFERENCES comments (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
    } catch (PDOException $e) {
        error_log("初始化通知表失败: " . $e->getMessage());
    }
}

/**
 * 当新回复发布后，向被回复的已登录用户写入通知。
 * 仅当父评论的邮箱能匹配到 users 表中的账号时才生效。
 * 不向自己回复自己的情况发送通知。
 */
function createReplyNotification($parentCommentId, $replyCommentId, $articleId) {
    $db = Db::getInstance();
    try {
        // 取父评论的邮箱
        $stmt = $db->prepare("SELECT email FROM comments WHERE id = ?");
        $stmt->execute([$parentCommentId]);
        $parentEmail = $stmt->fetchColumn();
        if (!$parentEmail) return;

        // 查找对应的注册用户
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$parentEmail]);
        $userId = $stmt->fetchColumn();
        if (!$userId) return; // 访客评论，无法推送站内通知

        // 取回复者邮箱，避免自回复产生通知
        $stmt = $db->prepare("SELECT email FROM comments WHERE id = ?");
        $stmt->execute([$replyCommentId]);
        $replyEmail = $stmt->fetchColumn();
        if ($replyEmail === $parentEmail) return;

        initNotificationsTable();
        $stmt = $db->prepare(
            "INSERT INTO notifications (user_id, type, comment_id, article_id)
             VALUES (?, 'reply', ?, ?)"
        );
        $stmt->execute([$userId, $replyCommentId, $articleId]);
    } catch (PDOException $e) {
        error_log("创建回复通知失败: " . $e->getMessage());
    }
}

/**
 * 获取指定用户的通知列表（附带评论内容与文章信息）。
 *
 * @param  int  $userId
 * @param  bool $unreadOnly  仅返回未读通知
 * @param  int  $limit
 * @return array
 */
function getUserNotifications($userId, $unreadOnly = false, $limit = 50) {
    $db = Db::getInstance();
    initNotificationsTable();
    try {
        $readCond = $unreadOnly ? "AND n.is_read = 0" : "";
        $stmt = $db->prepare("
            SELECT
                n.id,
                n.type,
                n.is_read,
                n.created_at,
                n.article_id,
                n.comment_id,
                -- 回复者信息
                rc.name        AS reply_name,
                rc.email       AS reply_email,
                rc.content     AS reply_content,
                -- 被回复的评论内容（摘要）
                pc.name        AS parent_name,
                pc.content     AS parent_content,
                pc.id          AS parent_comment_id,
                -- 文章标题
                a.title        AS article_title
            FROM notifications n
            JOIN comments rc ON rc.id = n.comment_id
            JOIN comments pc ON pc.id = rc.parent_id
            JOIN articles  a ON a.id  = n.article_id
            WHERE n.user_id = ? $readCond
            ORDER BY n.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("获取通知失败: " . $e->getMessage());
        return [];
    }
}

/**
 * 获取用户未读通知数量（用于显示角标）。
 */
function getUnreadNotificationCount($userId) {
    $db = Db::getInstance();
    initNotificationsTable();
    try {
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0"
        );
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * 删除指定通知（或该用户全部通知）。
 *
 * @param int      $userId
 * @param int|null $notificationId  传 null 则删除全部
 */
function deleteNotification($userId, $notificationId = null) {
    $db = Db::getInstance();
    initNotificationsTable();
    try {
        if ($notificationId !== null) {
            $stmt = $db->prepare(
                "DELETE FROM notifications WHERE id = ? AND user_id = ?"
            );
            $stmt->execute([$notificationId, $userId]);
        } else {
            $stmt = $db->prepare(
                "DELETE FROM notifications WHERE user_id = ?"
            );
            $stmt->execute([$userId]);
        }
        return true;
    } catch (PDOException $e) {
        error_log("删除通知失败: " . $e->getMessage());
        return false;
    }
}

/**
 * 将指定通知（或该用户所有通知）标记为已读。
 *
 * @param int      $userId
 * @param int|null $notificationId  传 null 则标记全部
 */
function markNotificationsRead($userId, $notificationId = null) {
    $db = Db::getInstance();
    initNotificationsTable();
    try {
        if ($notificationId !== null) {
            $stmt = $db->prepare(
                "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?"
            );
            $stmt->execute([$notificationId, $userId]);
        } else {
            $stmt = $db->prepare(
                "UPDATE notifications SET is_read = 1 WHERE user_id = ?"
            );
            $stmt->execute([$userId]);
        }
        return true;
    } catch (PDOException $e) {
        error_log("标记通知已读失败: " . $e->getMessage());
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────

function processCommentContent($content) {
    // 安全处理：转义所有HTML，保留换行符
    $content = htmlspecialchars($content);
    // 清理多余的连续换行符
    $content = preg_replace('/\r?\n{3,}/', "\n\n", $content);
    
    return $content;
}

function deleteCommentRecursive($comments, $commentId) {
    $newComments = [];
    foreach ($comments as $comment) {
        if ($comment['id'] == $commentId) {
            continue;
        }
        if (!empty($comment['replies'])) {
            $comment['replies'] = deleteCommentRecursive($comment['replies'], $commentId);
        }
        $newComments[] = $comment;
    }
    return $newComments;
}
function updateEmailModeration($emailHash, $mode) {
    $db = Db::getInstance();
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS email_moderation (
            email_hash VARCHAR(32) PRIMARY KEY,
            moderation VARCHAR(20) NOT NULL DEFAULT 'strict',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        $stmt = $db->prepare("
            INSERT INTO email_moderation (email_hash, moderation)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE moderation = ?
        ");
        return $stmt->execute([$emailHash, $mode, $mode]);
    } catch (PDOException $e) {
        error_log("更新邮箱审核模式失败: " . $e->getMessage());
        return false;
    }
}
function getParentComment($commentId, $comments = null) {
    if ($comments === null) {
        global $id;
        $commentsData = getArticleComments($id);
        $comments = $commentsData['comments'];
    }
    foreach ($comments as $comment) {
        if ($comment['id'] == $commentId) {
            return $comment;
        }
        if (!empty($comment['replies'])) {
            $found = getParentComment($commentId, $comment['replies']);
            if ($found) {
                return $found;
            }
        }
    }
    return null;
}