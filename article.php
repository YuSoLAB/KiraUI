<?php
session_start(); 
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', __DIR__);
}
$id = isset($_GET['id']) ? intval($_GET['id']) : 1;

require_once __DIR__ . '/include/Config.php';
require_once 'cache/ArticleIndex.php';
require_once 'cache/FileCache.php';
require_once ROOT_DIR . '/admin/comment_functions.php';
$article = loadArticleFromCache($id);

if ($article === false) {
    $article = loadDefaultArticle();
    $article['is_fallback'] = true;
}
$next_id = $id + 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    $commentData = [
        'name' => $_POST['name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'content' => $_POST['content'] ?? '',
        'parent_id' => $_POST['parent_id'] ?? '0'
    ];
    
    $result = addNewComment($id, $commentData);
    $redirectUrl = "article.php?id={$id}&comment_msg=" . urlencode($result['message']);
    header("Location: {$redirectUrl}");
    exit;
}

$commentSettings = initCommentSettings();
$commentsData = getArticleComments($id);
$approvedComments = array_filter($commentsData['comments'], function($comment) {
    return $comment['approved'];
});
$config = Config::getInstance();
$badgeText = $config->get('badge_text', '📝 YuSoLAB');
$siteTitle = $config->get('site_title', '测试网站');
$welcomeText = $config->get('welcome_text', '这是一个网站');
$banners = [];
$imgDir = __DIR__ . '/img/';
if (file_exists($imgDir)) {
    $banners = glob($imgDir . 'banner*.png');
    $banners = array_map(function($path) {
        return 'img/' . basename($path);
    }, $banners);
}
$randomBanner = $banners ? $banners[array_rand($banners)] : '';

function loadArticleFromDb($id) {
    $db = Db::getInstance();
    $stmt = $db->prepare("SELECT * FROM articles WHERE id = ?");
    $stmt->execute([$id]);
    $article = $stmt->fetch();    
    if ($article) {
        $article['tags'] = !empty($article['tags']) ? explode(',', $article['tags']) : [];
        return $article;
    }
    return false;
}

function loadArticleFromCache($id) {
    try {
        $cache = new FileCache('cache/data', 3600);
        $cache_key = 'article_content_' . $id;
        $article = $cache->get($cache_key);
        if ($article !== false && is_array($article) && isset($article['title'])) {
            return $article;
        }
        $article = loadArticleFromDb($id);
        if ($article && isset($article['title'])) {
            $cache->set($cache_key, $article);
            return $article;
        }
        return loadFallbackArticle($id);        
    } catch (Exception $e) {
        error_log("缓存加载失败: " . $e->getMessage());
        return loadArticleFromDb($id);
    }
}

function loadArticleFromFile($id) {
    $article_file = "articles/article_{$id}.php";    
    if (file_exists($article_file)) {
        $article = @include $article_file;
        if (is_array($article) && isset($article['title'])) {
            return $article;
        }
    }
    
    return false;
}

function loadFallbackArticle($requested_id) {
    $files = @glob('articles/article_*.php');
    if ($files && count($files) > 0) {
        foreach ($files as $file) {
            $article = @include $file;
            if (is_array($article) && isset($article['title'])) {
                $article['original_requested_id'] = $requested_id;
                return $article;
            }
        }
    }
    
    return false;
}

function loadDefaultArticle() {
    return [
        'id' => 1,
        'title' => '欢迎来到 YuSoLAB ',
        'excerpt' => '这是一个网站',
        'date' => date('Y-m-d'),
        'tags' => ['欢迎'],
        'content' => '<p>感谢访问 YuSoLAB ！</p><p>我们将为您提供精彩的内容。</p>',
        'is_default' => true
    ];
}

function parse_shortcodes($content) {
    if ($content === null) {
        $content = '';
    }
    $content = preg_replace_callback(
        '/\[image url="(.*?)" alt="(.*?)"\]/',
        function($matches) {
            $url = $matches[1];
            if (!preg_match('/^https?:\/\//i', $url)) {
                $url = 'https://' . $url;
            }
            
            return '<div style="margin: 15px 0; text-align: center;">
                        <img src="' . htmlspecialchars($url) . '" 
                            alt="' . htmlspecialchars($matches[2]) . '" 
                            style="max-width: 100%; border-radius: 8px; 
                                    box-shadow: 0 4px 12px rgba(155,140,255,.15);">
                    </div>';
        },
        $content
    );
    
    $content = preg_replace_callback(
        '/\[video url="(.*?)"\]/',
        function($matches) {
            $url = $matches[1];
            if (!preg_match('/^https?:\/\//i', $url)) {
                $url = 'https://' . $url;
            }
            
            return '<div style="margin: 15px 0;">
                        <video src="' . htmlspecialchars($url) . '" 
                            controls style="width: 100%; border-radius: 8px; 
                                            background: #f1f1f1;">
                            您的浏览器不支持视频播放
                        </video>
                    </div>';
        },
        $content
    );
        
    $content = preg_replace_callback(
        '/\[code lang="(.*?)"\](.*?)\[\/code\]/s',
        function($matches) {
            $lang = $matches[1] ? '语言: ' . htmlspecialchars($matches[1]) : '';
            return '<div style="margin: 15px 0; border-radius: 8px; overflow: hidden;">
                        <div style="padding: 6px 12px; background: #f1eaff; 
                                    border-bottom: 1px solid #e6d8ff; 
                                    font-size: 0.9em; color: #6c5dfb;">' . $lang . '</div>
                        <pre style="margin: 0; padding: 12px; background: #f9f9ff; 
                                   border: 1px solid #e6d8ff; border-top: none; 
                                   overflow-x: auto;"><code>' . 
                                        htmlspecialchars($matches[2]) . 
                                   '</code></pre>
                    </div>';
        },
        $content
    );
    
    $content = preg_replace_callback(
        '/\[link text="(.*?)" url="(.*?)"\]/',
        function($matches) {
            $url = $matches[2];
            if (!preg_match('/^https?:\/\//i', $url)) {
                $url = 'https://' . $url;
            }
            
            return '<a href="' . htmlspecialchars($url) . '" 
                    class="btn secondary" 
                    style="margin: 5px 0; display: inline-flex;
                            align-items: center; gap: 8px;
                            padding: 10px 16px; border-radius: 12px;
                            font-weight: 700; text-decoration: none;
                            background: #ffffffaa; border: 1.5px solid rgba(155,140,255,.55);
                            color: #6c5dfb; transition: all 0.2s ease;">
                    ' . htmlspecialchars($matches[1]) . '
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                </a>';
        },
        $content
    );
    
    $content = preg_replace_callback(
        '/\[download text="(.*?)" url="(.*?)"\]/',
        function($matches) {
            $url = $matches[2];
            if (!preg_match('/^https?:\/\//i', $url)) {
                $url = 'https://' . $url;
            }
            
            return '<a href="' . htmlspecialchars($url) . '" 
                    class="btn primary" 
                    style="margin: 5px 0; display: inline-flex;
                            align-items: center; gap: 8px;
                            padding: 10px 16px; border-radius: 12px;
                            font-weight: 700; text-decoration: none;
                            color: #fff; background: linear-gradient(180deg, #ff7ad9, #9b8cff);
                            border: 1px solid rgba(255,255,255,.5);
                            transition: all 0.2s ease;">
                    ' . htmlspecialchars($matches[1]) . '
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                </a>';
        },
        $content
    );
    
    $content = preg_replace_callback(
        '/\[encrypted_download text="(.*?)" url="(.*?)"\]/',
        function($matches) {
            $text = htmlspecialchars($matches[1]);
            $original_url = $matches[2];
            if (!preg_match('/^https?:\/\//i', $original_url)) {
                $original_url = 'https://' . $original_url;
            }
            $encrypt_id = bin2hex(random_bytes(16));
            $_SESSION['encrypted_downloads'][$encrypt_id] = $original_url;
            
            return '<button class="btn encrypted-download-btn" 
                            style="margin: 5px 0; display: inline-flex;
                                    align-items: center; gap: 8px;
                                    padding: 10px 16px; border-radius: 12px;
                                    font-weight: 700; text-decoration: none;
                                    color: #fff; background: linear-gradient(180deg, #4CAF50, #8BC34A);
                                    border: 1px solid rgba(255,255,255,.5);
                                    transition: all 0.2s ease; cursor: pointer;"
                            data-encrypt-id="' . $encrypt_id . '">
                        ' . $text . '
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                    </button>';
        },
        $content
    );
    return $content;
}
$article = loadArticleFromCache($id);
$next_id = $id + 1;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YuSoLAB </title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #f5f5f7;
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(245, 245, 247, 0);
            z-index: -1;
        }

        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            z-index: 1000;
            padding: 1rem 0;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .nav-logo {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .logo-img {
            height: 40px;
            width: auto;
        }

        .nav-menu {
            display: flex;
            gap: 2rem;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .nav-link {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link:hover {
            background: rgba(155, 140, 255, 0.1);
            color: #9b8cff;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 50%;
            width: 0;
            height: 2px;
            background: #9b8cff;
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .nav-link:hover::after {
            width: 80%;
        }

        .nav-toggle {
            display: none;
            flex-direction: column;
            cursor: pointer;
            padding: 0.5rem;
            border: none;
            background: none;
        }

        .nav-toggle span {
            width: 25px;
            height: 3px;
            background: #333;
            margin: 3px 0;
            transition: 0.3s;
            border-radius: 2px;
        }

        @media (max-width: 768px) {
            .nav-menu {
                position: fixed;
                top: 70px;
                right: -100%;
                flex-direction: column;
                background: rgba(255, 255, 255, 0.98);
                backdrop-filter: blur(20px);
                width: 80%;
                max-width: 300px;
                text-align: center;
                transition: 0.3s;
                box-shadow: 0 10px 27px rgba(0, 0, 0, 0.05);
                border-radius: 12px;
                padding: 1rem 0;
                gap: 0;
            }

            .nav-menu.active {
                right: 2rem;
            }

            .nav-link {
                display: block;
                padding: 1rem 2rem;
                margin: 0.5rem 1rem;
                border-radius: 8px;
            }

            .nav-toggle {
                display: flex;
            }

            .nav-toggle.active span:nth-child(1) {
                transform: rotate(-45deg) translate(-5px, 6px);
            }

            .nav-toggle.active span:nth-child(2) {
                opacity: 0;
            }

            .nav-toggle.active span:nth-child(3) {
                transform: rotate(45deg) translate(-5px, -6px);
            }
        }

        .wrap {
            margin-top: 80px;
        }

        @media (max-width: 768px) {
            .wrap {
                margin-top: 70px;
            }
        }

        .comments-section {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .comment-form {
            margin-bottom: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input, .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .comment-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            margin-bottom: 15px;
        }

        .comment-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .comment-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }

        .comment-name {
            font-weight: bold;
            margin-right: 10px;
        }

        .comment-date {
            color: #666;
            font-size: 0.9em;
        }

        .comment-content {
            margin-bottom: 10px;
        }

        .comment-actions {
            font-size: 0.9em;
        }

        .comment-actions a {
            color: #6c5dfb;
            text-decoration: none;
        }

        .comment-message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            background: #e3f2fd;
            color: #0d47a1;
        }

        body.dark-mode .comments-section {
            border-top-color: var(--dark-admin-border);
        }

        body.dark-mode .comment-form {
            background: var(--dark-admin-card);
            border: 1px solid var(--dark-admin-border);
        }

        body.dark-mode .form-group label {
            color: var(--dark-text);
        }

        body.dark-mode .form-group input,
        body.dark-mode .form-group textarea {
            background: #2a2a42aa;
            border-color: var(--dark-admin-border);
            color: var(--dark-text);
        }

        body.dark-mode .comment-item {
            border-bottom-color: var(--dark-admin-border);
        }

        body.dark-mode .comment-date {
            color: var(--dark-sub);
        }

        body.dark-mode .comment-content {
            color: var(--dark-text);
        }

        body.dark-mode .comment-actions a {
            color: var(--dark-vio);
        }

        body.dark-mode .comment-actions a:hover {
            color: var(--dark-pink);
        }

        body.dark-mode .replies {
            border-left: 1px solid var(--dark-admin-border);
        }

        body.dark-mode .comment-message {
            background: #3d2c00;
            color: #ffd27a;
        }

        .comment-reply-to {
            color: #ff4db1;
            font-weight: bold;
            display: inline-block;
            margin-right: 5px;
        }

        .replies {
            margin-left: 40px;
            margin-top: 15px;
            padding-left: 10px;
            border-left: 2px solid #e0e0e0;
        }

        .comment-item.reply-comment {
            margin-left: 0 !important;
            padding-left: 0 !important;
        }

        .comment-content {
            margin-bottom: 10px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        body.dark-mode .replies {
            border-left-color: var(--dark-admin-border);
        }

        body.dark-mode .comment-reply-to {
            color: #ff66b3;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="article.php" class="nav-logo">
                <img src="logo.ico" alt="YuSoLAB " class="logo-img">
            </a>
            
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link">首页</a></li>
                <li><a href="index.php" class="nav-link">文章</a></li>
                <li><a href="#" class="nav-link">关于</a></li>
                <li><a href="#" class="nav-link">联系</a></li>
            </ul>
            <button id="themeToggle" class="theme-toggle">🌙</button>
            <button class="nav-toggle" id="navToggle">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </nav>
    <div class="sparkles" id="sparkles" aria-hidden="true"></div>

    <div class="wrap">
        <main class="card" role="main">
            <a href="index.php" class="back-link">← 返回首页</a>
            
            <div class="header">
                <span class="badge">📖 文章详情</span>
                <h1 class="title"><?php echo $article['title'] ?? '文章未找到'; ?></h1>
            </div>

            <div class="article-meta">
                <span>发布日期: <?php echo $article['date'] ?? '未知'; ?></span>
                <span>字数: <?php echo $article['word_count'] ?? 0; ?></span>
                <span>阅读时间: <?php echo $article['read_time'] ?? 5; ?> 分钟</span>
            </div>

            <div class="article-tags">
                <?php foreach (($article['tags'] ?? []) as $tag): ?>
                    <span class="tag"><?php echo $tag; ?></span>
                <?php endforeach; ?>
            </div>

            <div class="article-content">
                <?php echo parse_shortcodes($article['content'] ?? '<p>文章内容加载失败。</p>'); ?>
            </div>
            
            <div class="actions">
                <a href="index.php" class="btn primary">返回首页</a>
                <?php
                $articleIndex = new ArticleIndex();
                $index = $articleIndex->getIndex();
                $totalArticles = count($index);
                $nextId = ($id % $totalArticles) + 1;
                ?>
                <a href="article.php?id=<?php echo $nextId; ?>" class="btn secondary">阅读下一篇文章</a>
            </div>
            <?php if ($commentSettings['enable_comments']):?>
            <div class="comments-section">
                <h3>评论区</h3>
                
                <?php 
                if (isset($_GET['comment_msg'])): 
                    $commentMessage = urldecode($_GET['comment_msg']);
                ?>
                    <div class="comment-message"><?php echo $commentMessage; ?></div>
                <?php endif; ?>
                
                <div class="comment-form">
                    <form method="post" id="commentForm">
                        <input type="hidden" name="parent_id" id="parent_id" value="0">

                        <div class="form-group">
                            <label for="name">昵称 *</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">邮箱 *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="content">评论内容 *</label>
                            <textarea id="content" name="content" rows="4" required></textarea>
                        </div>
                        
                        <button type="submit" name="submit_comment" class="btn primary">提交评论</button>
                    </form>
                </div>
                
                <?php
                function render_flat_replies($comments) {
                    foreach ($comments as $reply) {
                        if (!$reply['approved']) {
                            continue;
                        }
                        ?>
                        <div class="comment-item reply-comment" id="comment_<?php echo $reply['id']; ?>">
                            <div class="comment-header">
                                <img src="<?php echo getCommentAvatar($reply['email']); ?>" 
                                    alt="<?php echo $reply['name']; ?>" class="comment-avatar">
                                <div>
                                    <div class="comment-name"><?php echo $reply['name']; ?></div>
                                    <div class="comment-date"><?php echo $reply['created_at']; ?></div>
                                </div>
                            </div>
                            <div class="comment-content">
                                <?php 
                                $content = $reply['content'];
                                $parentComment = getParentComment($reply['parent_id']);
                                
                                if ($parentComment) {
                                    echo '<span class="comment-reply-to">@' . $parentComment['name'] . '</span> ';
                                }                                                               
                                echo htmlspecialchars($content);
                                ?>
                            </div>
                            <div class="comment-actions">
                                <a href="#" class="reply-link" 
                                data-comment-id="<?php echo $reply['id']; ?>"
                                data-comment-name="<?php echo $reply['name']; ?>">回复</a>
                            </div>
                        </div>
                        <?php
                        
                        if (!empty($reply['replies'])) {
                            render_flat_replies($reply['replies']);
                        }
                    }
                }

                function display_comment_thread($comment) {
                    ?>
                    <div class="comment-item" id="comment_<?php echo $comment['id']; ?>">
                        <div class="comment-header">
                            <img src="<?php echo getCommentAvatar($comment['email']); ?>" 
                                alt="<?php echo $comment['name']; ?>" class="comment-avatar">
                            <div>
                                <div class="comment-name"><?php echo $comment['name']; ?></div>
                                <div class="comment-date"><?php echo $comment['created_at']; ?></div>
                            </div>
                        </div>
                        <div class="comment-content">
                            <?php echo $comment['content']; ?>
                        </div>
                        <div class="comment-actions">
                            <a href="#" class="reply-link" 
                            data-comment-id="<?php echo $comment['id']; ?>"
                            data-comment-name="<?php echo $comment['name']; ?>">回复</a>
                        </div>
                    </div>
                    <?php

                    if (!empty($comment['replies'])) {
                        echo '<div class="replies">';
                        render_flat_replies($comment['replies']);
                        echo '</div>';
                    }
                }
                ?>
                <div class="comments-list">
                    <?php if (count($approvedComments) > 0): ?>
                        <?php foreach ($approvedComments as $comment):  ?>
                            <?php display_comment_thread($comment);  ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>暂无评论，快来发表第一条评论吧！</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        const navToggle = document.getElementById('navToggle');
        const navMenu = document.querySelector('.nav-menu');
        navToggle.addEventListener('click', () => {
            navMenu.classList.toggle('active');
            navToggle.classList.toggle('active');
        });
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                navMenu.classList.remove('active');
                navToggle.classList.remove('active');
            });
        });

        (function(){
            var box = document.getElementById('sparkles');
            var count = 60;
            var vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
            for (var i=0;i<count;i++){
                var s = document.createElement('i');
                var size = 6 + Math.random()*10;
                s.style.width = s.style.height = size + 'px';
                s.style.left = (Math.random()*100) + 'vw';
                s.style.top = (Math.random()*100) + 'vh';
                s.style.animationDuration = (10 + Math.random()*12) + 's';
                s.style.animationDelay = (Math.random()*-20) + 's';
                s.style.opacity = .4 + Math.random()*.6;
                box.appendChild(s);
            }
            
            if(vw < 480){
                var kids = box.querySelectorAll('i');
                for (var j=0;j<kids.length;j+=2){ kids[j].remove(); }
            }
        })();

        document.addEventListener('DOMContentLoaded', function() {
            const bannerImages = <?php echo json_encode($banners); ?>;            
            const randomIndex = Math.floor(Math.random() * bannerImages.length);
            const selectedImage = bannerImages[randomIndex];        
            const img = new Image();
            img.src = selectedImage;
            
            img.onload = function() {
                document.body.style.backgroundImage = `url('${selectedImage}')`;
            };
            
            img.onerror = function() {
                document.body.style.backgroundImage = 'url("img/default-banner.png")';
            };
        });

        document.querySelectorAll('.encrypted-download-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                const encryptId = this.getAttribute('data-encrypt-id');
                this.disabled = true;
                this.innerHTML = '处理中...';
                
                try {
                    const response = await fetch('get_download_url.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'encrypt_id=' + encodeURIComponent(encryptId) + 
                            '&referrer=' + encodeURIComponent(window.location.href)
                    });                    
                    if (!response.ok) throw new Error('获取下载链接失败');                    
                    const data = await response.json();                    
                    if (data.success && data.url) {
                        const iframe = document.createElement('iframe');
                        iframe.style.display = 'none';
                        iframe.src = data.url;
                        document.body.appendChild(iframe);
                        setTimeout(() => iframe.remove(), 3000);
                        setTimeout(() => {
                            this.disabled = false;
                            this.innerHTML = this.innerHTML.replace('处理中...', '重新下载');
                        }, 1000);
                    } else {
                        throw new Error(data.message || '下载链接无效或已过期');
                    }
                } catch (error) {
                    this.disabled = false;
                    this.innerHTML = '下载失败';
                    alert(error.message);
                    setTimeout(() => {
                        this.innerHTML = this.innerHTML.replace('下载失败', '重试下载');
                    }, 5000);
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.reply-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const commentId = this.getAttribute('data-comment-id');
                    const commentName = this.getAttribute('data-comment-name');
                    const contentField = document.getElementById('content');
                    if (contentField.value.indexOf('@' + commentName) !== 0) {
                        const cursorPos = contentField.selectionStart;
                        const currentValue = contentField.value;
                        contentField.value = '@' + commentName + ' ' + currentValue;
                        contentField.focus();
                        contentField.setSelectionRange(commentName.length + 2, commentName.length + 2);
                    }
                    
                    document.getElementById('parent_id').value = commentId;
                    document.getElementById('content').focus();
                });
            });
            
            document.getElementById('commentForm').addEventListener('submit', function(e) {
                const parentId = document.getElementById('parent_id').value;
                if (parentId && parentId !== '0') {
                    const replyLink = document.querySelector(`.reply-link[data-comment-id="${parentId}"]`);
                    if (replyLink) {
                        const commentName = replyLink.getAttribute('data-comment-name');
                        const contentField = document.getElementById('content');
                        const prefix = '@' + commentName + ' ';
                        if (contentField.value.startsWith(prefix)) {
                            contentField.value = contentField.value.substring(prefix.length);
                        }
                    }
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const themeToggle = document.getElementById('themeToggle');
            if (localStorage.getItem('theme') === 'dark' || 
                (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.body.classList.add('dark-mode');
                themeToggle.textContent = '☀️';
            } else {
                document.body.classList.remove('dark-mode');
                themeToggle.textContent = '🌙';
            }
            
            themeToggle.addEventListener('click', function() {
                if (document.body.classList.contains('dark-mode')) {
                    document.body.classList.remove('dark-mode');
                    localStorage.setItem('theme', 'light');
                    themeToggle.textContent = '🌙';
                } else {
                    document.body.classList.add('dark-mode');
                    localStorage.setItem('theme', 'dark');
                    themeToggle.textContent = '☀️';
                }
            });
        });
    </script>
    <?php include 'include/footer.php'; ?>
</body>
</html>