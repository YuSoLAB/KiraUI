<?php
define('ROOT_DIR', __DIR__);
$ARTICLES_DIR = ROOT_DIR . '/articles/';
if (!file_exists($ARTICLES_DIR)) {
    die("文章目录不存在: {$ARTICLES_DIR}");
}
$files = glob($ARTICLES_DIR . 'article_*.php');
if (empty($files)) {
    die("没有找到文章文件");
}
echo "开始重新计算 " . count($files) . " 篇文章的字数和阅读时长...\n";
$updatedCount = 0;
foreach ($files as $file) {
    $article = include $file;
    if (!is_array($article) || !isset($article['id'])) {
        echo "跳过无效文件: {$file}\n";
        continue;
    }

    $content = $article['content'] ?? '';
    preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $content, $chineseMatches);
    $chineseCount = is_array($chineseMatches[0]) ? count($chineseMatches[0]) : 0;
    preg_match_all('/[a-zA-Z]+/', $content, $englishMatches);
    $englishCount = is_array($englishMatches[0]) ? count($englishMatches[0]) : 0; 
    $totalWords = $chineseCount + $englishCount;
    $readTime = ceil($totalWords / 250);
    $article['word_count'] = $totalWords;
    $article['read_time'] = $readTime;
    $phpContent = "<?php\nreturn " . var_export($article, true) . ";\n?>";
    if (file_put_contents($file, $phpContent, LOCK_EX) !== false) {
        $updatedCount++;
        echo "已更新文章 ID: {$article['id']} - 字数: {$totalWords} - 阅读时长: {$readTime}分钟\n";
    } else {
        echo "更新失败: {$file}\n";
    }
}

require_once ROOT_DIR . '/cache/ArticleIndex.php';
$articleIndex = new ArticleIndex();
$articleIndex->buildIndex();
$cache = new FileCache();
$cache->delete('all_articles_basic');
echo "\n处理完成！共更新 {$updatedCount} 篇文章\n";
echo "文章索引已重建\n";
?>