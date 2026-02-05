<?php
// 函数管理
require_once dirname(__DIR__) . '/include/Db.php';
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(dirname(__FILE__)));
}
if (!defined('ARTICLES_DIR')) {
    define('ARTICLES_DIR', ROOT_DIR . '/articles/');
}
if (!defined('DRAFTS_DIR')) {
    define('DRAFTS_DIR', dirname(ARTICLES_DIR) . '/drafts/');
}
// 加载文章用于编辑
function loadArticleForEdit($id) {
    $db = Db::getInstance();
    $stmt = $db->prepare("SELECT * FROM articles WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: [];
}
// 获取下一个文章ID
function getNextArticleId() {
    $db = Db::getInstance();
    $stmt = $db->query("SELECT MAX(id) as max_id FROM articles");
    $result = $stmt->fetch();
    $maxId = intval($result['max_id'] ?? 0);
    return $maxId + 1;
}
// 保存文章
function saveArticle($data) {
    $db = Db::getInstance();  
    if (!isset($data['title']) || !isset($data['content'])) {
        return ['success' => false, 'error' => '缺少必要字段'];
    }  
    if (isset($data['tags'])) {
        if (is_array($data['tags'])) {
            $tags = $data['tags'];
        } else {
            $tags = explode(',', $data['tags']);
        }
        $tags = array_map('trim', $tags);
        $tags = array_filter($tags);
        $tagsStr = implode(',', $tags);
    } else {
        $tagsStr = '';
    }    
    // 计算文章字数和阅读时间
    $content = $data['content'] ?? '';
    preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $content, $chineseMatches);
    $chineseCount = count($chineseMatches[0]);
    preg_match_all('/[a-zA-Z]+/', $content, $englishMatches);
    $englishCount = count($englishMatches[0]);
    $totalWords = $chineseCount + $englishCount;
    $readTime = ceil($totalWords / 250);    
    $articleDate = !empty($data['date']) ? $data['date'] : date('Y-m-d');    
    // 检查文章是否存在
    try {
        if (isset($data['id']) && is_numeric($data['id']) && intval($data['id']) > 0) {
            $checkStmt = $db->prepare("SELECT id FROM articles WHERE id = ?");
            $checkStmt->execute([intval($data['id'])]);
            $existingArticle = $checkStmt->fetch();            
            if ($existingArticle) {
                $id = intval($data['id']);
                $stmt = $db->prepare("UPDATE articles SET 
                    title = ?, excerpt = ?, content = ?, date = ?, tags = ?,
                    word_count = ?, read_time = ?
                    WHERE id = ?");
                $stmt->execute([
                    $data['title'], $data['excerpt'] ?? '', $content, $articleDate,
                    $tagsStr, $totalWords, $readTime, $id
                ]);
                $stmt = $db->prepare("UPDATE article_index SET
                    title = ?, date = ?, excerpt = ?, tags = ?,
                    word_count = ?, read_time = ?
                    WHERE id = ?");
                $stmt->execute([
                    $data['title'], $articleDate, $data['excerpt'] ?? '',
                    $tagsStr, $totalWords, $readTime, $id
                ]);                
                return ['success' => true, 'id' => $id];
            }
        }  
        // 插入新文章
        $stmt = $db->prepare("INSERT INTO articles 
            (title, excerpt, content, date, tags, word_count, read_time)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['title'], $data['excerpt'] ?? '', $content, $articleDate,
            $tagsStr, $totalWords, $readTime
        ]);
        $id = $db->lastInsertId();
        $stmt = $db->prepare("INSERT INTO article_index 
            (id, title, date, excerpt, tags, word_count, read_time)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $id, $data['title'], $articleDate, $data['excerpt'] ?? '',
            $tagsStr, $totalWords, $readTime
        ]);
        global $cache;
        $cache->delete("article_{$id}");
        $cache->delete("article_content_{$id}");
        $cache->delete('article_index');
        $cache->delete('all_articles_basic');
        require_once ROOT_DIR . '/cache/ArticleIndex.php';
        $articleIndex = new ArticleIndex();
        $articleIndex->updateTagStats();        
        return ['success' => true, 'id' => $id];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
// 删除文章
function deleteArticle($id) {
    $db = Db::getInstance();
    try {
        $stmt = $db->prepare("DELETE FROM comments WHERE article_id = ?");
        $stmt->execute([$id]);
        $stmt = $db->prepare("DELETE FROM articles WHERE id = ?");
        $result = $stmt->execute([$id]);        
        if ($result) {
            require_once ROOT_DIR . '/cache/ArticleIndex.php';
            $articleIndex = new ArticleIndex();
            $articleIndex->updateTagStats();
        }        
        return $result;
    } catch (PDOException $e) {
        error_log("删除文章错误: " . $e->getMessage());
        return false;
    }
}
// 移动文章到草稿箱
function moveToDraft($id) {
    $db = Db::getInstance();
    try {
        // 获取文章数据
        $stmt = $db->prepare("SELECT * FROM articles WHERE id = ?");
        $stmt->execute([$id]);
        $article = $stmt->fetch();
        
        if (empty($article)) {
            return false;
        }
        
        // 将文章数据保存到草稿表
        $stmt = $db->prepare("INSERT INTO drafts 
            (title, excerpt, content, date, tags, word_count, read_time)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $article['title'], $article['excerpt'], $article['content'], 
            $article['date'], $article['tags'], $article['word_count'], 
            $article['read_time']
        ]);
        
        // 删除原文章
        $stmt = $db->prepare("DELETE FROM comments WHERE article_id = ?");
        $stmt->execute([$id]);
        
        $stmt = $db->prepare("DELETE FROM articles WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        if ($result) {
            // 更新缓存和索引
            global $cache;
            $cache->delete("article_{$id}");
            $cache->delete("article_content_{$id}");
            $cache->delete('article_index');
            $cache->delete('all_articles_basic');
            
            require_once ROOT_DIR . '/cache/ArticleIndex.php';
            $articleIndex = new ArticleIndex();
            $articleIndex->buildIndex(); // 重建索引
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("移动文章到草稿箱错误: " . $e->getMessage());
        return false;
    }
}
// 从草稿箱发布文章
function publishFromDraft($id) {
    $draft = loadDraftForEdit($id);
    if (empty($draft)) {
        return false;
    }    
    unset($draft['id']);   
    $result = saveArticle($draft);
    if (!$result['success']) {
        return false;
    }    
    global $cache;
    $articleId = $result['id'];
    $cache->delete("article_{$articleId}");
    $cache->delete("article_content_{$articleId}");
    $cache->delete('article_index');
    $cache->delete('all_articles_basic');
    require_once ROOT_DIR . '/cache/ArticleIndex.php';
    $articleIndex = new ArticleIndex();
    $articleIndex->updateTagStats();
    $deleteResult = deleteDraft($id);
    return $deleteResult;
}

// 获取备份文件列表
function getBackupFiles() {
    $backupDir = ROOT_DIR . '/cache/backups/';
    $backupFiles = [];
    
    if (is_dir($backupDir)) {
        $files = scandir($backupDir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
                $filePath = $backupDir . $file;
                $fileSize = filesize($filePath);
                $fileTime = filemtime($filePath);
                
                $backupFiles[] = [
                    'name' => $file,
                    'path' => $filePath,
                    'size' => formatFileSize($fileSize),
                    'size_bytes' => $fileSize,
                    'date' => date('Y-m-d H:i:s', $fileTime),
                    'timestamp' => $fileTime
                ];
            }
        }
        
        // 按时间倒序排列
        usort($backupFiles, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
    }
    
    return $backupFiles;
}

// 删除备份文件
function deleteBackupFile($filename) {
    $backupDir = ROOT_DIR . '/cache/backups/';
    $filePath = $backupDir . $filename;
    
    // 安全检查
    if (strpos(realpath($filePath), realpath($backupDir)) !== 0 || 
        pathinfo($filename, PATHINFO_EXTENSION) !== 'zip') {
        return false;
    }
    
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    
    return false;
}

// 格式化文件大小
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}
// 获取草稿箱文章
function getDrafts() {
    $db = Db::getInstance();
    $stmt = $db->query("SELECT * FROM drafts ORDER BY date DESC");
    $drafts = $stmt->fetchAll();    
    foreach ($drafts as &$draft) {
        $draft['tags'] = !empty($draft['tags']) ? explode(',', $draft['tags']) : [];
    }    
    return $drafts;
}
// 加载草稿箱文章用于编辑
function loadDraftForEdit($id) {
    $db = Db::getInstance();
    $stmt = $db->prepare("SELECT * FROM drafts WHERE id = ?");
    $stmt->execute([$id]);
    $draft = $stmt->fetch();
    if ($draft) {
        $draft['tags'] = !empty($draft['tags']) ? explode(',', $draft['tags']) : [];
        return $draft;
    }
    return [];
}
// 保存草稿箱文章
function saveDraft($data) {
    $db = Db::getInstance();    
    if (!isset($data['title']) || !isset($data['content'])) {
        return ['success' => false, 'error' => '缺少必要字段'];
    }    
    if (isset($data['tags'])) {
        if (is_array($data['tags'])) {
            $tags = $data['tags'];
        } else {
            $tags = explode(',', $data['tags']);
        }
        $tags = array_map('trim', $tags);
        $tags = array_filter($tags);
        $tagsStr = implode(',', $tags);
    } else {
        $tagsStr = '';
    }    
    $content = $data['content'] ?? '';
    preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $content, $chineseMatches);
    $chineseCount = count($chineseMatches[0]);
    preg_match_all('/[a-zA-Z]+/', $content, $englishMatches);
    $englishCount = count($englishMatches[0]);
    $totalWords = $chineseCount + $englishCount;
    $readTime = ceil($totalWords / 250);    
    $draftDate = $data['date'] ?? date('Y-m-d'); 
    // 处理ID
    try {
        if (isset($data['id']) && is_numeric($data['id']) && intval($data['id']) > 0) {
            $id = intval($data['id']);
            $stmt = $db->prepare("UPDATE drafts SET 
                title = ?, excerpt = ?, content = ?, date = ?, tags = ?,
                word_count = ?, read_time = ?
                WHERE id = ?");
            $stmt->execute([
                $data['title'], $data['excerpt'] ?? '', $content, $draftDate,
                $tagsStr, $totalWords, $readTime, $id
            ]);
        } else {
            $stmt = $db->prepare("INSERT INTO drafts 
                (title, excerpt, content, date, tags, word_count, read_time)
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['title'], $data['excerpt'] ?? '', $content, $draftDate,
                $tagsStr, $totalWords, $readTime
            ]);
            $id = $db->lastInsertId();
        }        // 更新缓存
        return ['success' => true, 'id' => $id];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
// 删除草稿箱文章
function deleteDraft($id) {
    $db = Db::getInstance();
    try {
        $stmt = $db->prepare("DELETE FROM drafts WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log("删除草稿错误: " . $e->getMessage());
        return false;
    }
}
// 获取注册邮箱设置
function getRegistrationEmailSettings() {
    $db = Db::getInstance();
    try {
        $stmt = $db->prepare("SELECT * FROM registration_email_settings WHERE id = 1");
        $stmt->execute();
        $settings = $stmt->fetch();
        if (!$settings) {
            return [
                'email_mode' => 'all',
                'allowed_domains' => [],
                'blocked_domains' => []
            ];
        }
        return [
            'email_mode' => $settings['email_mode'],
            'allowed_domains' => $settings['allowed_domains'] ? explode("\n", $settings['allowed_domains']) : [],
            'blocked_domains' => $settings['blocked_domains'] ? explode("\n", $settings['blocked_domains']) : []
        ];
    } catch (PDOException $e) {
        error_log("获取注册邮箱设置失败: " . $e->getMessage());
        return ['email_mode' => 'all', 'allowed_domains' => [], 'blocked_domains' => []];
    }
}
// 保存注册邮箱设置
function saveRegistrationEmailSettings($settings) {
    $db = Db::getInstance();
    $allowedDomains = implode("\n", $settings['allowed_domains'] ?? []);
    $blockedDomains = implode("\n", $settings['blocked_domains'] ?? []);
    try {
        $sql = "
            UPDATE registration_email_settings
            SET email_mode = ?, allowed_domains = ?, blocked_domains = ?
            WHERE id = 1
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $settings['email_mode'] ?? 'all',
            $allowedDomains,
            $blockedDomains
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("保存注册邮箱设置失败: " . $e->getMessage());
        return false;
    }
}
// 检查用户状态并自动恢复
function checkUserStatus($userId) {
    $db = Db::getInstance();
    $stmt = $db->prepare("SELECT status, status_expires_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) return false;
    if ($user['status'] !== 'normal' && $user['status_expires_at'] && strtotime($user['status_expires_at']) < time()) {
        $stmt = $db->prepare("UPDATE users SET status = 'normal', status_expires_at = NULL WHERE id = ?");
        $stmt->execute([$userId]);
        return 'normal';
    }
    return $user['status'];
}
// 检查注册邮箱是否允许
function isRegistrationEmailAllowed($email) {
    $settings = getRegistrationEmailSettings();
    $domain = substr(strrchr($email, "@"), 1);
    if ($settings['email_mode'] == 'blacklist' && in_array($domain, $settings['blocked_domains'])) {
        return false;
    }
    if ($settings['email_mode'] == 'whitelist' && !in_array($domain, $settings['allowed_domains'])) {
        return false;
    }
    return true;
}
?>