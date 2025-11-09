<?php
require_once __DIR__ . '/FileCache.php';
require_once dirname(__DIR__) . '/include/Db.php';
class ArticleIndex {
    private $cache;
    private $db;
    public function __construct() {
        $this->cache = new FileCache();
        $this->db = Db::getInstance();
    }
    public function buildIndex() {
        try {
            $this->db->exec("TRUNCATE TABLE article_index");
            $stmt = $this->db->query("SELECT * FROM articles ORDER BY date DESC");
            $articles = $stmt->fetchAll();
            $index = [];
            foreach ($articles as $article) {
                $tags = !empty($article['tags']) ? explode(',', $article['tags']) : [];
                $tags = array_map('trim', $tags);
                $tagsStr = implode(',', $tags);
                $stmt = $this->db->prepare("INSERT INTO article_index 
                    (id, title, date, excerpt, tags, word_count, read_time)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $article['id'],
                    $article['title'] ?? '无标题',
                    $article['date'] ?? date('Y-m-d'),
                    $article['excerpt'] ?? '',
                    $tagsStr,
                    $article['word_count'] ?? 0,
                    $article['read_time'] ?? 0
                ]);                
                $index[$article['id']] = [
                    'id' => $article['id'],
                    'title' => $article['title'] ?? '无标题',
                    'date' => $article['date'] ?? date('Y-m-d'),
                    'excerpt' => $article['excerpt'] ?? '',
                    'tags' => $tags,
                    'word_count' => $article['word_count'] ?? 0,
                    'read_time' => $article['read_time'] ?? 0,
                    'modified' => strtotime($article['updated_at'] ?? $article['created_at'])
                ];
            }
            $this->updateTagStats();
            $this->cache->delete('article_index');
            $this->cache->delete('all_articles_basic');          
            return $index;
        } catch (Exception $e) {
            error_log("构建文章索引错误: " . $e->getMessage());
            return [];
        }
    }
    public function getIndex($forceRefresh = false) {
        try {
            $cache_key = 'article_index';
            if ($forceRefresh) {
                $this->cache->delete($cache_key);
                $index = false;
            } else {
                $index = $this->cache->get($cache_key);
            }
            if ($index === false || empty($index)) {
                $stmt = $this->db->query("SELECT * FROM article_index ORDER BY date DESC");
                $articles = $stmt->fetchAll();   
                $index = [];
                foreach ($articles as $article) {
                    $index[$article['id']] = [
                        'id' => $article['id'],
                        'title' => $article['title'],
                        'date' => $article['date'],
                        'excerpt' => $article['excerpt'],
                        'tags' => !empty($article['tags']) ? explode(',', $article['tags']) : [],
                        'word_count' => $article['word_count'],
                        'read_time' => $article['read_time'],
                        'modified' => strtotime($article['modified'])
                    ];
                }
                $this->cache->set($cache_key, $index, 1800);
            }
            return is_array($index) ? $index : [];
        } catch (Exception $e) {
            error_log("获取文章索引错误: " . $e->getMessage());
            return [];
        }
    }
    public function getArticleInfo($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM article_index WHERE id = ?");
            $stmt->execute([$id]);
            $article = $stmt->fetch();            
            if ($article) {
                return [
                    'id' => $article['id'],
                    'title' => $article['title'],
                    'date' => $article['date'],
                    'excerpt' => $article['excerpt'],
                    'tags' => !empty($article['tags']) ? explode(',', $article['tags']) : [],
                    'word_count' => $article['word_count'],
                    'read_time' => $article['read_time'],
                    'modified' => strtotime($article['modified'])
                ];
            }
            return false;
        } catch (Exception $e) {
            error_log("获取文章信息错误: " . $e->getMessage());
            return false;
        }
    }
    public function updateTagStats() {
        $this->db->exec("TRUNCATE TABLE tag_stats");
        $stmt = $this->db->query("SELECT tags FROM article_index WHERE tags IS NOT NULL AND tags != ''");
        $tagRows = $stmt->fetchAll();
        $tagCounts = [];
        foreach ($tagRows as $row) {
            $tags = explode(',', $row['tags']);
            foreach ($tags as $tag) {
                $tag = trim($tag);
                if (!empty($tag)) {
                    if (!isset($tagCounts[$tag])) {
                        $tagCounts[$tag] = 0;
                    }
                    $tagCounts[$tag]++;
                }
            }
        }
        $stmt = $this->db->prepare("INSERT INTO tag_stats (tag, count) VALUES (?, ?)");
        foreach ($tagCounts as $tag => $count) {
            $stmt->execute([$tag, $count]);
        }
    }
    public function clearIndex() {
        try {
            $this->db->exec("TRUNCATE TABLE article_index");
            $this->db->exec("TRUNCATE TABLE tag_stats");
            $this->cache->delete('article_index');
            $this->cache->delete('all_articles_basic');
            return true;
        } catch (Exception $e) {
            error_log("清空文章索引错误: " . $e->getMessage());
            return false;
        }
    }
    public function getIndexStats() {
        $index = $this->getIndex();
        $tagStats = [];
        try {
            $stmt = $this->db->query("SELECT tag, count FROM tag_stats ORDER BY count DESC");
            while ($row = $stmt->fetch()) {
                $tagStats[$row['tag']] = $row['count'];
            }
        } catch (Exception $e) {
            error_log("获取标签统计错误: " . $e->getMessage());
        }
        $totalWords = 0;
        foreach ($index as $article) {
            $totalWords += $article['word_count'] ?? 0;
        }        
        return [
            'total_articles' => count($index),
            'tags' => $tagStats,
            'total_words' => $totalWords
        ];
    }
}