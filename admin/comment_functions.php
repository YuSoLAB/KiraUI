<?php
/**
 * comment_functions.php — 评论核心函数库（含完整邮件通知逻辑）
 *
 * 本文件在原版基础上完善了以下通知逻辑：
 *
 *  1. initCommentSettings()     — 新增 notify_admin / email_notify_enabled /
 *                                 notify_guest_reply 三个开关字段的读取与自动补列
 *  2. sendCommentAdminNotify()  — 尊重全局总开关 + 管理员通知开关
 *  3. createReplyNotification() — 区分以下四种情况：
 *       a. 被回复者是已登录用户且有邮箱 → 站内通知 + 邮件（受 notify_on_reply 控制）
 *       b. 被回复者是已登录用户但无邮箱（手机号注册）→ 仅站内通知，跳过邮件
 *       c. 被回复者是游客（有邮箱）→ 仅发邮件（受 notify_guest_reply 全局开关控制）
 *       d. 自己回复自己 → 不通知
 *       全部情况均受 email_notify_enabled 总开关控制
 *
 * ─────────────────────────────────────────────────────────────────────
 * ★ 使用方式：用本文件替换项目中原有的 comment_functions.php，
 *   或仅将下方标注「UPDATED」的函数覆盖到原文件中。
 * ─────────────────────────────────────────────────────────────────────
 */

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

// ─────────────────────────────────────────────────────────────────────
// [UPDATED] initCommentSettings — 新增三个通知开关字段
// ─────────────────────────────────────────────────────────────────────
function initCommentSettings() {
    $db = Db::getInstance();

    // 自动补列（兼容旧数据库，无需手动执行 SQL）
    $notifyColumns = [
        'email_notify_enabled' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'notify_admin'         => 'TINYINT(1) NOT NULL DEFAULT 1',
        'notify_guest_reply'   => 'TINYINT(1) NOT NULL DEFAULT 1',
        'allow_guest_comments' => 'TINYINT(1) NOT NULL DEFAULT 1',
    ];
    try {
        foreach ($notifyColumns as $col => $def) {
            $chk = $db->query(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'comment_settings'
                   AND COLUMN_NAME  = '{$col}'"
            );
            if ($chk && !$chk->fetch()) {
                $db->exec("ALTER TABLE comment_settings ADD COLUMN `{$col}` {$def}");
            }
        }
    } catch (PDOException $e) {
        error_log('[initCommentSettings] 自动补列失败: ' . $e->getMessage());
    }

    try {
        $db->exec("CREATE TABLE IF NOT EXISTS comment_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            email_mode VARCHAR(20) NOT NULL DEFAULT 'all',
            allowed_domains TEXT,
            blocked_domains TEXT,
            default_moderation VARCHAR(20) NOT NULL DEFAULT 'strict',
            enable_comments TINYINT(1) NOT NULL DEFAULT 1,
            email_notify_enabled TINYINT(1) NOT NULL DEFAULT 1,
            notify_admin TINYINT(1) NOT NULL DEFAULT 1,
            notify_guest_reply TINYINT(1) NOT NULL DEFAULT 1,
            allow_guest_comments TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    } catch (PDOException $e) {
        error_log("创建评论设置表错误: " . $e->getMessage());
    }

    $defaultSettings = [
        'email_mode'           => 'all',
        'allowed_domains'      => [],
        'blocked_domains'      => [],
        'default_moderation'   => 'strict',
        'enable_comments'      => true,
        'allow_guest_comments' => true,
        'email_notify_enabled' => true,
        'notify_admin'         => true,
        'notify_guest_reply'   => true,
    ];

    try {
        $stmt  = $db->query("SELECT * FROM comment_settings LIMIT 1");
        $saved = $stmt->fetch();
        if ($saved) {
            $allowedDomains = !empty($saved['allowed_domains']) ? explode("\n", $saved['allowed_domains']) : [];
            $blockedDomains = !empty($saved['blocked_domains']) ? explode("\n", $saved['blocked_domains']) : [];
            return [
                'email_mode'           => $saved['email_mode'],
                'allowed_domains'      => $allowedDomains,
                'blocked_domains'      => $blockedDomains,
                'default_moderation'   => $saved['default_moderation'],
                'enable_comments'      => (bool)$saved['enable_comments'],
                'allow_guest_comments' => isset($saved['allow_guest_comments']) ? (bool)$saved['allow_guest_comments'] : true,
                'email_notify_enabled' => isset($saved['email_notify_enabled']) ? (bool)$saved['email_notify_enabled'] : true,
                'notify_admin'         => isset($saved['notify_admin'])         ? (bool)$saved['notify_admin']         : true,
                'notify_guest_reply'   => isset($saved['notify_guest_reply'])   ? (bool)$saved['notify_guest_reply']   : true,
            ];
        }
    } catch (PDOException $e) {
        error_log("读取评论设置错误: " . $e->getMessage());
    }

    saveCommentSettings($defaultSettings);
    return $defaultSettings;
}

// ─────────────────────────────────────────────────────────────────────
// [UPDATED] saveCommentSettings — 持久化新增的通知字段
// ─────────────────────────────────────────────────────────────────────
function saveCommentSettings($settings) {
    $db = Db::getInstance();
    $allowedDomains = implode("\n", $settings['allowed_domains'] ?? []);
    $blockedDomains = implode("\n", $settings['blocked_domains'] ?? []);
    try {
        $sql = "
            INSERT INTO comment_settings
            (id, email_mode, allowed_domains, blocked_domains,
             default_moderation, enable_comments, allow_guest_comments,
             email_notify_enabled, notify_admin, notify_guest_reply)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                email_mode           = VALUES(email_mode),
                allowed_domains      = VALUES(allowed_domains),
                blocked_domains      = VALUES(blocked_domains),
                default_moderation   = VALUES(default_moderation),
                enable_comments      = VALUES(enable_comments),
                allow_guest_comments = VALUES(allow_guest_comments),
                email_notify_enabled = VALUES(email_notify_enabled),
                notify_admin         = VALUES(notify_admin),
                notify_guest_reply   = VALUES(notify_guest_reply)
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            1,
            $settings['email_mode']           ?? 'all',
            $allowedDomains,
            $blockedDomains,
            $settings['default_moderation']   ?? 'strict',
            $settings['enable_comments']      ? 1 : 0,
            $settings['allow_guest_comments'] ? 1 : 0,
            isset($settings['email_notify_enabled']) ? ($settings['email_notify_enabled'] ? 1 : 0) : 1,
            isset($settings['notify_admin'])         ? ($settings['notify_admin']         ? 1 : 0) : 1,
            isset($settings['notify_guest_reply'])   ? ($settings['notify_guest_reply']   ? 1 : 0) : 1,
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("保存评论设置失败: " . $e->getMessage());
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────
// [UPDATED] sendCommentAdminNotify — 完整版（双开关 + 自动补列）
// ─────────────────────────────────────────────────────────────────────
/**
 * 向所有管理员发送「新评论待审核」通知邮件。
 *
 * 触发条件（全部满足才发送）：
 *   1. 全局总开关 email_notify_enabled = 1
 *   2. 管理员通知开关 notify_admin = 1
 *   3. SMTP 已启用（Mailer::isEnabled()）
 *   4. 数据库中存在至少一个拥有有效邮箱的管理员账号
 *
 * @param int    $commentId  新评论 ID
 * @param int    $articleId  所属文章 ID
 * @param string $content    评论纯文本内容
 */
function sendCommentAdminNotify(int $commentId, int $articleId, string $content): void
{
    try {
        require_once dirname(__DIR__) . '/include/Config.php';
        $cfg = Config::getInstance();
        $db  = Db::getInstance();

        // ── 读取开关（自动处理列不存在的情况）──
        $row = null;
        try {
            $stmt = $db->query("SELECT email_notify_enabled, notify_admin FROM comment_settings LIMIT 1");
            $row  = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        } catch (PDOException $e) {
            // 列还不存在：静默忽略，按默认值（开启）处理
        }

        $globalEnabled = isset($row['email_notify_enabled']) ? (bool)$row['email_notify_enabled'] : true;
        $notifyAdmin   = isset($row['notify_admin'])         ? (bool)$row['notify_admin']         : true;

        if (!$globalEnabled || !$notifyAdmin) return;

        require_once dirname(__DIR__) . '/include/Mailer.php';
        $mailer = new Mailer();
        if (!$mailer->isEnabled()) return;

        // ── 获取所有有效邮箱的管理员 ──
        $stmtAdmins  = $db->query(
            "SELECT email FROM users
             WHERE role = 'admin'
               AND status = 'normal'
               AND email IS NOT NULL
               AND email != ''"
        );
        $adminEmails = $stmtAdmins ? $stmtAdmins->fetchAll(PDO::FETCH_COLUMN) : [];
        if (empty($adminEmails)) return;

        // ── 获取文章标题 ──
        $stmtArt  = $db->prepare("SELECT title FROM articles WHERE id = ?");
        $stmtArt->execute([$articleId]);
        $artTitle = $stmtArt->fetchColumn() ?: '（未知文章）';

        // ── 获取评论者名称 ──
        $stmtCmt      = $db->prepare("SELECT name FROM comments WHERE id = ?");
        $stmtCmt->execute([$commentId]);
        $commenterName = $stmtCmt->fetchColumn() ?: '匿名用户';

        $siteUrl  = rtrim($cfg->get('site_url', ''), '/');
        $artUrl   = $siteUrl . '/article.php?id=' . $articleId . '#comment_' . $commentId;
        $adminUrl = $siteUrl . '/admin/admin.php?page=comments';

        foreach ($adminEmails as $adminEmail) {
            $mailer->sendCommentNotifyToAdmin($adminEmail, [
                'commenter_name'  => $commenterName,
                'article_title'   => $artTitle,
                'article_url'     => $artUrl,
                'comment_content' => strip_tags($content),
                'admin_url'       => $adminUrl,
            ]);
        }
    } catch (Exception $e) {
        error_log('[sendCommentAdminNotify] ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────────────
// [UPDATED] createReplyNotification — 完整四情况处理
// ─────────────────────────────────────────────────────────────────────
/**
 * 当回复评论被发布后，向被回复方写入站内通知并发送邮件。
 *
 * 四种情形：
 *   A. 被回复者 = 已登录用户 + 有邮箱
 *        → 站内通知 ✓  邮件（受 notify_on_reply 用户开关控制）
 *   B. 被回复者 = 已登录用户 + 无邮箱（手机注册）
 *        → 站内通知 ✓  邮件 ✗（静默跳过，记录 debug 日志）
 *   C. 被回复者 = 游客（有邮箱，无账号）
 *        → 站内通知 ✗  邮件（受全局 notify_guest_reply 开关控制）
 *   D. 自回复
 *        → 不处理
 */
function createReplyNotification(
    int $parentCommentId,
    int $replyCommentId,
    int $articleId
): void {
    try {
        $db = Db::getInstance();

        // ── 读取父评论（被回复方） ──
        $stmt = $db->prepare(
            "SELECT name, email, user_id, content FROM comments WHERE id = ?"
        );
        $stmt->execute([$parentCommentId]);
        $parentRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$parentRow) return;

        $parentEmail   = trim($parentRow['email']   ?? '');
        $parentContent = $parentRow['content']       ?? '';
        $parentUserId  = isset($parentRow['user_id']) ? (int)$parentRow['user_id'] : 0;

        // ── 读取新回复（回复方） ──
        $stmt = $db->prepare(
            "SELECT name, email, user_id, content FROM comments WHERE id = ?"
        );
        $stmt->execute([$replyCommentId]);
        $replyRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$replyRow) return;

        $replyEmail      = trim($replyRow['email']   ?? '');
        $replierName     = $replyRow['name']          ?? '有人';
        $replyContent    = $replyRow['content']       ?? '';
        $replyUserId     = isset($replyRow['user_id']) ? (int)$replyRow['user_id'] : 0;

        // ── D. 自回复检测（同账号 或 同邮箱） ──
        if ($parentUserId > 0 && $parentUserId === $replyUserId) return;
        if ($parentEmail !== '' && $replyEmail !== '' && $parentEmail === $replyEmail) return;

        // ── 读取全局通知开关 ──
        $globalEnabled    = true;
        $notifyGuestReply = true;
        try {
            $gs = $db->query(
                "SELECT email_notify_enabled, notify_guest_reply
                 FROM comment_settings LIMIT 1"
            );
            if ($gs) {
                $gRow = $gs->fetch(PDO::FETCH_ASSOC);
                if ($gRow) {
                    $globalEnabled    = (bool)$gRow['email_notify_enabled'];
                    $notifyGuestReply = (bool)$gRow['notify_guest_reply'];
                }
            }
        } catch (PDOException $e) { /* 列不存在时按默认值 */ }

        // ── 读取被回复用户的账号信息（若有） ──
        $userId          = null;
        $registeredEmail = null;  // 账号绑定的邮箱（可能为空）
        $userName        = $parentRow['name'] ?? '用户';
        $notifyOnReply   = true;  // 默认接收

        if ($parentUserId > 0) {
            // 被回复方是已登录用户，直接用 user_id 查询（比邮箱更可靠）
            $uStmt = $db->prepare(
                "SELECT id, nickname, username, email, notify_on_reply
                 FROM users WHERE id = ?"
            );
            $uStmt->execute([$parentUserId]);
            $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
            if ($uRow) {
                $userId          = (int)$uRow['id'];
                $registeredEmail = trim($uRow['email'] ?? '');
                $userName        = $uRow['nickname'] ?: ($uRow['username'] ?? $userName);
                $notifyOnReply   = (bool)($uRow['notify_on_reply'] ?? 1);
            }
        } elseif ($parentEmail !== '') {
            // 游客评论：尝试通过邮箱匹配账号（游客有时也会用注册邮箱评论）
            $uStmt = $db->prepare(
                "SELECT id, nickname, username, email, notify_on_reply
                 FROM users WHERE email = ? AND email != ''"
            );
            $uStmt->execute([$parentEmail]);
            $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
            if ($uRow) {
                $userId          = (int)$uRow['id'];
                $registeredEmail = trim($uRow['email'] ?? '');
                $userName        = $uRow['nickname'] ?: ($uRow['username'] ?? $userName);
                $notifyOnReply   = (bool)($uRow['notify_on_reply'] ?? 1);
            }
        }

        // ── 确保 notify_on_reply 列存在 ──
        _ensureNotifyOnReplyColumn($db);

        // ─────────────────────────────────────────────────
        // A / B. 被回复方是已注册用户 → 写入站内通知
        // ─────────────────────────────────────────────────
        if ($userId !== null) {
            initNotificationsTable();
            // 避免重复插入
            $dup = $db->prepare(
                "SELECT 1 FROM notifications
                 WHERE user_id = ? AND comment_id = ? AND article_id = ? LIMIT 1"
            );
            $dup->execute([$userId, $replyCommentId, $articleId]);
            if (!$dup->fetch()) {
                $ins = $db->prepare(
                    "INSERT INTO notifications (user_id, type, comment_id, article_id)
                     VALUES (?, 'reply', ?, ?)"
                );
                $ins->execute([$userId, $replyCommentId, $articleId]);
            }

            // ── 情形 B：用户无邮箱（手机注册），跳过邮件 ──
            if ($registeredEmail === '') {
                error_log("[createReplyNotification] user_id={$userId} 无绑定邮箱，跳过邮件通知（仅站内通知）");
                return;
            }

            // ── 情形 A：用户有邮箱，检查个人开关 + 全局总开关 ──
            if (!$globalEnabled || !$notifyOnReply) return;

            $targetEmail = $registeredEmail;
        } else {
            // ─────────────────────────────────────────────────
            // C. 纯游客，无账号
            // ─────────────────────────────────────────────────
            if ($parentEmail === '') return; // 游客无邮箱，无法通知
            if (!$globalEnabled || !$notifyGuestReply) return;

            $targetEmail = $parentEmail;
        }

        // ── 获取文章标题 & 构建 URL ──
        $stmtArt = $db->prepare("SELECT title FROM articles WHERE id = ?");
        $stmtArt->execute([$articleId]);
        $artTitle = $stmtArt->fetchColumn() ?: '';

        require_once dirname(__DIR__) . '/include/Config.php';
        $cfg     = Config::getInstance();
        $siteUrl = rtrim($cfg->get('site_url', ''), '/');
        // 使用 comment_xxx 锚点（与前端模板 id="comment_N" 对应）
        $artUrl  = $siteUrl . '/article.php?id=' . $articleId . '#comment_' . $replyCommentId;

        // ── 发送邮件 ──
        require_once dirname(__DIR__) . '/include/Mailer.php';
        $mailer = new Mailer();
        if (!$mailer->isEnabled()) return;

        $mailer->sendReplyNotifyToUser($targetEmail, [
            'user_name'        => $userName,
            'replier_name'     => $replierName,
            'article_title'    => $artTitle,
            'original_content' => strip_tags(mb_substr($parentContent, 0, 200)),
            'reply_content'    => strip_tags($replyContent),
            'article_url'      => $artUrl,
        ]);

    } catch (Exception $e) {
        error_log('[createReplyNotification] ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────────────
// 辅助：确保 users.notify_on_reply 列存在
// ─────────────────────────────────────────────────────────────────────
function _ensureNotifyOnReplyColumn(PDO $db): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $chk = $db->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'users'
               AND COLUMN_NAME  = 'notify_on_reply'"
        );
        if ($chk && !$chk->fetch()) {
            $db->exec("ALTER TABLE users ADD COLUMN notify_on_reply TINYINT(1) NOT NULL DEFAULT 1");
        }
    } catch (PDOException $e) {
        error_log('[_ensureNotifyOnReplyColumn] ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────────────
// 以下函数与原版完全相同，保持不变
// ─────────────────────────────────────────────────────────────────────

function getArticleCommentsFile($articleId) {
    return COMMENTS_DIR . 'article_' . intval($articleId) . '.json';
}
function initArticleComments($articleId) {
    $file = getArticleCommentsFile($articleId);
    if (!file_exists($file)) {
        $comments = ['emails' => [], 'comments' => []];
        file_put_contents($file, json_encode($comments, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
function getArticleComments($articleId) {
    $db   = Db::getInstance();
    $stmt = $db->prepare(
        "SELECT * FROM comments
         WHERE article_id = ? AND parent_id IS NULL AND approved = 1
         ORDER BY created_at DESC"
    );
    $stmt->execute([$articleId]);
    $comments = $stmt->fetchAll();
    foreach ($comments as &$comment) {
        $comment['replies'] = getCommentReplies($comment['id']);
    }
    return ['comments' => $comments];
}
function getCommentReplies($commentId) {
    $db   = Db::getInstance();
    $stmt = $db->prepare(
        "SELECT * FROM comments WHERE parent_id = ? AND approved = 1 ORDER BY created_at ASC"
    );
    $stmt->execute([$commentId]);
    $replies = $stmt->fetchAll();
    foreach ($replies as &$reply) {
        $reply['replies'] = getCommentReplies($reply['id']);
    }
    return $replies;
}
function isEmailAllowed($email, $settings) {
    $domain = substr(strrchr($email, "@"), 1);
    if (in_array($domain, $settings['blocked_domains'])) return false;
    if ($settings['email_mode'] == 'whitelist' && !in_array($domain, $settings['allowed_domains'])) return false;
    return true;
}
function generateSvgAvatar(string $text, int $size = 64): string {
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
        $size, (int)($size / 2), $bg, $fontSize,
        htmlspecialchars($letter, ENT_XML1, 'UTF-8')
    );
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}
function getCommentAvatar($email, $userId = null) {
    if ($userId) {
        try {
            $db   = Db::getInstance();
            $stmt = $db->prepare("SELECT avatar FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $avatar = $stmt->fetchColumn();
            if (!empty($avatar) && file_exists(ROOT_DIR . '/uploads/avatars/' . $avatar)) {
                return 'uploads/avatars/' . $avatar;
            }
        } catch (PDOException $e) {}
    }
    if ($email && preg_match('/^(\d+)@(qq\.com|vip\.qq\.com)$/', $email, $matches)) {
        return 'https://q1.qlogo.cn/g?b=qq&nk=' . $matches[1] . '&s=640';
    }
    $label = $email ? (strstr($email, '@', true) ?: 'G') : 'G';
    return generateSvgAvatar($label, 64);
}
function addNewComment($articleId, $data) {
    $settings   = initCommentSettings();
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    $isLoggedIn = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
    if ($isLoggedIn) {
        $email  = $_SESSION['user']['email'] ?? '';
        $name   = $_SESSION['user']['nickname'] ?? ($_SESSION['user']['username'] ?? '');
        $status = checkUserStatus($_SESSION['user']['id']);
        if ($status == 'banned') {
            return ['success' => false, 'message' => '您的账号已被封禁，无法发表评论'];
        }
    } else {
        $email = $data['email'] ?? '';
        $name  = $data['name']  ?? '';
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
    if (!($isLoggedIn && $email === '') && !isEmailAllowed($email, $settings)) {
        return ['success' => false, 'message' => '该邮箱不允许发送评论'];
    }
    $db        = Db::getInstance();
    $emailHash = md5(strtolower(trim($email)));
    $name      = htmlspecialchars($name);
    $content   = processCommentContent($data['content'] ?? '');
    $parentId  = empty($data['parent_id']) || $data['parent_id'] == '0' ? null : $data['parent_id'];

    $needsModeration = true;
    $stmt_email = $db->prepare("SELECT moderation FROM email_moderation WHERE email_hash = ?");
    $stmt_email->execute([$emailHash]);
    $emailMode  = $stmt_email->fetchColumn();
    if ($emailMode === 'auto') {
        $needsModeration = false;
    } elseif ($emailMode === 'strict') {
        $needsModeration = true;
    } elseif (!$emailMode) {
        if ($settings['default_moderation'] === 'auto') {
            $stmt_check = $db->prepare("SELECT 1 FROM comments WHERE email_hash = ? AND approved = 1 LIMIT 1");
            $stmt_check->execute([$emailHash]);
            $needsModeration = !$stmt_check->fetchColumn();
        } else {
            $needsModeration = true;
        }
    }
    $userId = $isLoggedIn ? (int)$_SESSION['user']['id'] : null;
    try {
        $stmt = $db->prepare(
            "INSERT INTO comments
             (article_id, parent_id, user_id, name, email, email_hash, content, approved)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $articleId, $parentId, $userId,
            $name, $email, $emailHash, $content,
            $needsModeration ? 0 : 1
        ]);
        $newCommentId = (int)$db->lastInsertId();

        // 若是回复且已通过审核，立即向被回复用户发送通知
        if ($parentId !== null && !$needsModeration) {
            createReplyNotification((int)$parentId, $newCommentId, (int)$articleId);
        }

        // 通知管理员：有新评论提交
        sendCommentAdminNotify($newCommentId, (int)$articleId, $content);

        return [
            'success'          => true,
            'message'          => $needsModeration ? '评论已提交，等待审核' : '评论已发布',
            'needs_moderation' => $needsModeration,
            'approved'         => !$needsModeration,
            'comment_id'       => $newCommentId,
            'new_comment_id'   => $newCommentId,
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => '评论提交失败: ' . $e->getMessage()];
    }
}
function addReplyToComment(&$comments, $reply) {
    if ($reply['id'] == $reply['parent_id']) return false;
    foreach ($comments as &$comment) {
        if ($comment['id'] == $reply['id']) continue;
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
        $approvedValue = $approved ? 1 : -1;
        $stmt = $db->prepare("UPDATE comments SET approved = ? WHERE id = ? AND article_id = ?");
        $result = $stmt->execute([$approvedValue, $commentId, $articleId]);
        if ($result && $approved) {
            $stmt2 = $db->prepare("SELECT parent_id, article_id FROM comments WHERE id = ?");
            $stmt2->execute([$commentId]);
            $row = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['parent_id']) {
                createReplyNotification((int)$row['parent_id'], (int)$commentId, (int)($row['article_id'] ?? $articleId));
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
            return ['found' => true, 'email_hash' => $comment['email_hash']];
        }
        if (!empty($comment['replies'])) {
            $result = moderateCommentRecursive($comment['replies'], $commentId, $approved);
            if ($result['found']) return $result;
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
    $db   = Db::getInstance();
    $stmt = $db->prepare("SELECT id FROM comments WHERE parent_id = ?");
    $stmt->execute([$parentId]);
    $children    = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $allChildren = [];
    foreach ($children as $childId) {
        $allChildren[] = $childId;
        $allChildren   = array_merge($allChildren, getChildComments($childId));
    }
    return $allChildren;
}
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
function getUserNotifications($userId, $unreadOnly = false, $limit = 50) {
    $db = Db::getInstance();
    initNotificationsTable();
    try {
        $readCond = $unreadOnly ? "AND n.is_read = 0" : "";
        $stmt     = $db->prepare("
            SELECT
                n.id, n.type, n.is_read, n.created_at, n.article_id, n.comment_id,
                rc.name    AS reply_name,
                rc.email   AS reply_email,
                rc.content AS reply_content,
                pc.name    AS parent_name,
                pc.content AS parent_content,
                pc.id      AS parent_comment_id,
                a.title    AS article_title
            FROM notifications n
            JOIN comments rc ON rc.id = n.comment_id
            JOIN comments pc ON pc.id = rc.parent_id
            JOIN articles  a ON a.id  = n.article_id
            WHERE n.user_id = ? {$readCond}
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
function getUnreadNotificationCount($userId) {
    $db = Db::getInstance();
    initNotificationsTable();
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}
function deleteNotification($userId, $notificationId = null) {
    $db = Db::getInstance();
    initNotificationsTable();
    try {
        if ($notificationId !== null) {
            $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            $stmt->execute([$notificationId, $userId]);
        } else {
            $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ?");
            $stmt->execute([$userId]);
        }
        return true;
    } catch (PDOException $e) {
        error_log("删除通知失败: " . $e->getMessage());
        return false;
    }
}
function markNotificationsRead($userId, $notificationId = null) {
    $db = Db::getInstance();
    initNotificationsTable();
    try {
        if ($notificationId !== null) {
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$notificationId, $userId]);
        } else {
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$userId]);
        }
        return true;
    } catch (PDOException $e) {
        error_log("标记通知已读失败: " . $e->getMessage());
        return false;
    }
}
function processCommentContent($content) {
    $content = htmlspecialchars($content);
    $content = preg_replace('/\r?\n{3,}/', "\n\n", $content);
    return $content;
}
function deleteCommentRecursive($comments, $commentId) {
    $newComments = [];
    foreach ($comments as $comment) {
        if ($comment['id'] == $commentId) continue;
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
        $stmt = $db->prepare(
            "INSERT INTO email_moderation (email_hash, moderation)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE moderation = ?"
        );
        return $stmt->execute([$emailHash, $mode, $mode]);
    } catch (PDOException $e) {
        error_log("更新邮箱审核模式失败: " . $e->getMessage());
        return false;
    }
}
function getParentComment($commentId, $comments = null) {
    if ($comments === null) {
        $db   = Db::getInstance();
        $stmt = $db->prepare("SELECT * FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    foreach ($comments as $comment) {
        if ($comment['id'] == $commentId) return $comment;
        if (!empty($comment['replies'])) {
            $found = getParentComment($commentId, $comment['replies']);
            if ($found) return $found;
        }
    }
    return null;
}