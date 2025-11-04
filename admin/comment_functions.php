<?php
require_once dirname(__DIR__) . '/include/Db.php';

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
        'enable_comments' => true
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
                'enable_comments' => (bool)$saved['enable_comments']
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
        WHERE article_id = ? AND parent_id IS NULL 
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
            (id, email_mode, allowed_domains, blocked_domains, default_moderation, enable_comments)
            VALUES (?, ?, ?, ?, ?, ?)  -- 我们固定 settings 的 id 为 1
            ON DUPLICATE KEY UPDATE
                email_mode = ?,
                allowed_domains = ?,
                blocked_domains = ?,
                default_moderation = ?,
                enable_comments = ?
        ";
        
        $stmt = $db->prepare($sql);
        $values = [
            $settings['email_mode'] ?? 'all',
            $allowedDomains,
            $blockedDomains,
            $settings['default_moderation'] ?? 'strict',
            $settings['enable_comments'] ? 1 : 0,
            $settings['email_mode'] ?? 'all',
            $allowedDomains,
            $blockedDomains,
            $settings['default_moderation'] ?? 'strict',
            $settings['enable_comments'] ? 1 : 0
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

function getCommentAvatar($email) {
    if (preg_match('/^(\d+)@(qq\.com|vip\.qq\.com)$/', $email, $matches)) {
        return 'https://q1.qlogo.cn/g?b=qq&nk=' . $matches[1] . '&s=640';
    }
    return 'https://via.placeholder.com/64?text=Guest';
}

function addNewComment($articleId, $data) {
    $settings = initCommentSettings();
    $email = $data['email'] ?? '';
    if (empty($settings['enable_comments'])) {
        return ['success' => false, 'message' => '评论功能已关闭'];
    }

    if (!isEmailAllowed($email, $settings)) {
        return ['success' => false, 'message' => '该邮箱不允许发送评论'];
    }

    $db = Db::getInstance();
    $emailHash = md5(strtolower(trim($email)));
    $name = htmlspecialchars($data['name'] ?? '');
    $content = nl2br(htmlspecialchars($data['content'] ?? ''));
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

    try {
        $stmt = $db->prepare("INSERT INTO comments 
            (article_id, parent_id, name, email, email_hash, content, approved)
            VALUES (?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $articleId,
            $parentId,
            $name,
            $email,
            $emailHash,
            $content,
            $needsModeration ? 0 : 1
        ]);

        return [
            'success' => true,
            'message' => $needsModeration ? '评论已提交，等待审核' : '评论已发布',
            'needs_moderation' => $needsModeration
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
        $stmt = $db->prepare("UPDATE comments SET approved = ? WHERE id = ? AND article_id = ?");
        return $stmt->execute([$approved ? 1 : 0, $commentId, $articleId]);
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