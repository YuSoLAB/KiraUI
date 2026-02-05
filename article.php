<?php
require_once 'auto_login.php'; // 接入自动登录功能

// 尝试自动登录
autoLogin();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
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
$htmlTitle = $config->get('html_title', 'YuSoLAB');
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
            return '<div style="margin: 15px 0; text-align: center;"><img src="' . htmlspecialchars($url) . '" alt="' . htmlspecialchars($matches[2]) . '" style="max-width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(155,140,255,.15);"></div>';
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

            return '<div style="margin: 15px 0;"><video src="' . htmlspecialchars($url) . '" controls style="width: 100%; border-radius: 8px; background: #f1f1f1;">您的浏览器不支持视频播放</video></div>';
        },
        $content
    );

    $content = preg_replace_callback(
        '/\[code lang="(.*?)"\](.*?)\[\/code\]/s',
        function($matches) {
            $lang = $matches[1] ? '语言: ' . htmlspecialchars($matches[1]) : '';
            $code = htmlspecialchars($matches[2]);
            $code = trim($code); 
            $code = preg_replace('/\n\s*\n\s*\n/', "\n\n", $code);
            
            return '<div class="code-block"><div class="code-header">' . $lang . '</div><div class="code-container"><pre><code class="language-' . ($matches[1] ? htmlspecialchars($matches[1]) : 'plaintext') . '">' . $code . '</code></pre></div></div>';
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

            return '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener noreferrer" class="btn secondary" style="margin: 5px 0; display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 12px; font-weight: 700; text-decoration: none; background: #ffffffaa; border: 1.5px solid rgba(155,140,255,.55); color: #6c5dfb; transition: all 0.2s ease;">' . htmlspecialchars($matches[1]) . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg></a>';
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
            return '<a href="' . htmlspecialchars($url) . '" class="btn primary" style="margin: 5px 0; display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 12px; font-weight: 700; text-decoration: none; color: #fff; background: linear-gradient(180deg, #ff7ad9, #9b8cff); border: 1px solid rgba(255,255,255,.5); transition: all 0.2s ease;">' . htmlspecialchars($matches[1]) . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg></a>';
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
            
            return '<button class="btn encrypted-download-btn" style="margin: 5px 0; display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 12px; font-weight: 700; text-decoration: none; color: #fff; background: linear-gradient(180deg, #4CAF50, #8BC34A); border: 1px solid rgba(255,255,255,.5); transition: all 0.2s ease; cursor: pointer;" data-encrypt-id="' . $encrypt_id . '">' . $text . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg></button>';
        },
        $content
    );

    $content = preg_replace('/(?<!>)\n(?!<)/', "<br>\n", $content);
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
    <title><?php echo htmlspecialchars($htmlTitle); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script>hljs.highlightAll();</script>
    
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="nav-logo">
                <?php if (file_exists($imgDir . 'logo.ico')): ?>
                    <img src="img/logo.ico" alt="Logo" class="logo-img">
                <?php else: ?>
                    <img src="logo.ico" alt="YuSoLAB" class="logo-img">
                <?php endif; ?>
            </a>
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link">首页</a></li>
                <li><a href="index.php" class="nav-link">文章</a></li>
                <li><a href="#" class="nav-link">关于</a></li>
                <li><a href="#" class="nav-link">联系</a></li>
            </ul>
            <div class="nav-right">
                <button id="themeToggle" class="theme-toggle">🌙</button>
                <div class="user-auth">
                    <?php
                    if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
                        echo '<span class="user-welcome">欢迎，' . htmlspecialchars($_SESSION['user']['nickname']) . '</span>';
                        echo '<a href="user_center.php" class="btn btn-small btn-login">用户中心</a>';
                    } else {
                        echo '<a href="login" class="btn btn-small btn-login">登录</a>';
                        echo '<a href="register" class="btn btn-small btn-register">注册</a>';
                    }
                    ?>
                </div>
            </div>
            <button class="nav-toggle" id="navToggle">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </nav>
    <div class="sparkles" id="sparkles" aria-hidden="true"></div>
    <div class="wrap">
        <main class="main-content" role="main">
            <a href="index.php" class="back-link">← 返回首页</a>
            <div class="blog-header">
                <div>
                    <span class="badge">📖 文章详情</span>
                    <h1 class="title"><?php echo $article['title'] ?? '文章未找到'; ?></h1>
                </div>
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
                $articleIds = array_keys($index);
                sort($articleIds);
                $currentPosition = array_search($id, $articleIds);
                if ($currentPosition !== false && $currentPosition > 0) {
                    $prevId = $articleIds[$currentPosition - 1];
                } else {
                    $prevId = end($articleIds) ?? 1;
                }
                if ($currentPosition !== false && isset($articleIds[$currentPosition + 1])) {
                    $nextId = $articleIds[$currentPosition + 1];
                } else {
                    $nextId = $articleIds[0] ?? 1;
                }
                ?>
                <a href="article.php?id=<?php echo $prevId; ?>" class="btn secondary">阅读上一篇文章</a>
                <a href="article.php?id=<?php echo $nextId; ?>" class="btn secondary">阅读下一篇文章</a>
            </div>
            <?php if ($commentSettings['enable_comments']):
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                }
                $isLoggedIn = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;             
            ?>
            <div class="comments-section">
                <h3>评论区</h3>
                <?php 
                if (isset($_GET['comment_msg'])): 
                    $commentMessage = urldecode($_GET['comment_msg']);
                ?>
                    <div class="comment-message"><?php echo $commentMessage; ?></div>
                <?php endif; ?>
                <?php
                if ($commentSettings['allow_guest_comments'] || $isLoggedIn) {
                ?>
                <div class="comment-form">
                    <form method="post" id="commentForm">
                        <input type="hidden" name="parent_id" id="parent_id" value="0">
                        <?php
                        if (session_status() == PHP_SESSION_NONE) {
                            session_start();
                        }
                        $isLoggedIn = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
                        if ($isLoggedIn) {
                            $userNickname = htmlspecialchars($_SESSION['user']['nickname']);
                            $userEmail = htmlspecialchars($_SESSION['user']['email']);
                            echo '<input type="hidden" name="name" value="' . $userNickname . '">';
                            echo '<input type="hidden" name="email" value="' . $userEmail . '">';
                        } else {
                        ?>
                        <div class="form-group">
                            <label for="name">昵称 *</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="email">邮箱 *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <?php } ?>
                        <div class="form-group">
                            <label for="content">评论内容 *</label>
                            <textarea id="content" name="content" rows="4" required></textarea>
                        </div>
                        <button type="submit" name="submit_comment" class="btn primary">提交评论</button>
                    </form>
                </div>
                
                <script>
                function autoResizeTextarea(textarea) {
                    textarea.style.height = 'auto';
                    textarea.style.height = (textarea.scrollHeight) + 'px';
                }
                
                document.addEventListener('DOMContentLoaded', function() {
                    const textarea = document.getElementById('content');
                    if (textarea) {
                        autoResizeTextarea(textarea);
                        textarea.addEventListener('input', function() {
                            autoResizeTextarea(this);
                        });
                        textarea.addEventListener('focus', function() {
                            this.style.transition = 'all 0.3s ease';
                        });
                        
                        textarea.addEventListener('blur', function() {
                            this.style.transition = 'all 0.2s ease';
                        });
                    }
                    document.addEventListener('click', function(e) {
                        if (e.target.classList.contains('reply-link')) {
                            setTimeout(function() {
                                const replyTextarea = document.querySelector('#commentForm textarea');
                                if (replyTextarea) {
                                    autoResizeTextarea(replyTextarea);
                                    replyTextarea.addEventListener('input', function() {
                                        autoResizeTextarea(this);
                                    });
                                }
                            }, 100);
                        }
                    });
                });
                </script>
            <?php
            } else {
                // 不允许游客评论且用户未登录，显示登录提示
                echo '<div class="comment-login-prompt">';
                echo '<p>请先登录后再发表评论</p>';
                echo '<a href="login" class="btn primary">登录</a>';
                echo '<a href="register" class="btn secondary" style="margin-left: 10px;">注册</a>';
                echo '</div>';
            }
            ?>              
                <?php
                function render_flat_replies($comments) {
                    foreach ($comments as $reply) {
                        if (!$reply['approved']) {
                            continue;
                        }
                        ?>
                        <div class="comment-item reply-comment" id="comment_<?php echo $reply['id']; ?>">
                            <div class="comment-header">
                                <img src="<?php echo getCommentAvatar($reply['email'], $reply['user_id'] ?? 0); ?>" 
                                    alt="<?php echo htmlspecialchars($reply['name']); ?>" class="comment-avatar">
                                <div>
                                    <div class="comment-name"><?php echo htmlspecialchars($reply['name']); ?></div>
                                    <div class="comment-date"><?php echo $reply['created_at']; ?></div>
                                </div>
                            </div>
                            <div class="comment-content" style="white-space: pre-wrap; word-wrap: break-word;"><?php 
                                $content = trim($reply['content']); 
                                $parentComment = getParentComment($reply['parent_id']);
                                if ($parentComment) {
                                    echo '<span class="comment-reply-to">@' . htmlspecialchars($parentComment['name']) . '</span> ';
                                }                                                               
                                // echo htmlspecialchars($content);
                                echo trim($content);
                            ?></div>
                            <div class="comment-actions">
                                <a href="#" class="reply-link" 
                                data-comment-id="<?php echo $reply['id']; ?>"
                                data-comment-name="<?php echo htmlspecialchars($reply['name']); ?>">回复</a>
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
                            <img src="<?php echo getCommentAvatar($comment['email'], $comment['user_id'] ?? 0); ?>" 
                                alt="<?php echo htmlspecialchars($comment['name']); ?>" class="comment-avatar">
                            <div>
                                <div class="comment-name"><?php echo htmlspecialchars($comment['name']); ?></div>
                                <div class="comment-date"><?php echo $comment['created_at']; ?></div>
                            </div>
                        </div>
                        <div class="comment-content" style="white-space: pre-wrap; word-wrap: break-word;"><?php echo trim($comment['content']); ?></div>
                        <div class="comment-actions">
                            <a href="#" class="reply-link" 
                            data-comment-id="<?php echo $comment['id']; ?>"
                            data-comment-name="<?php echo htmlspecialchars($comment['name']); ?>">回复</a>
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
                document.body.style.setProperty('--bg-url', `url('${selectedImage}')`);
            };
            img.onerror = function() {
                document.body.style.setProperty('--bg-url', 'url("img/default-banner.png")');
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
        document.addEventListener('DOMContentLoaded', function() {
            // 为每个代码块添加复制按钮
            document.querySelectorAll('.code-block').forEach(function(block) {
                const header = block.querySelector('.code-header');
                const copyBtn = document.createElement('button');
                copyBtn.className = 'copy-btn';
                copyBtn.textContent = '复制';
                copyBtn.onclick = function() {
                    const code = block.querySelector('code').textContent;
                    navigator.clipboard.writeText(code).then(function() {
                        copyBtn.textContent = '已复制!';
                        copyBtn.classList.add('copied');
                        setTimeout(function() {
                            copyBtn.textContent = '复制';
                            copyBtn.classList.remove('copied');
                        }, 2000);
                    }).catch(function(err) {
                        // 如果clipboard API失败，使用备用方法
                        const textArea = document.createElement('textarea');
                        textArea.value = code;
                        document.body.appendChild(textArea);
                        textArea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textArea);
                        
                        copyBtn.textContent = '已复制!';
                        copyBtn.classList.add('copied');
                        setTimeout(function() {
                            copyBtn.textContent = '复制';
                            copyBtn.classList.remove('copied');
                        }, 2000);
                    });
                };
                header.appendChild(copyBtn);
            });
            
            // 初始化 highlight.js
            if (typeof hljs !== 'undefined') {
                document.querySelectorAll('pre code').forEach(function(block) {
                    hljs.highlightElement(block);
                });
            }
        });
    </script>
    <?php include 'include/footer.php'; ?>
</body>
</html>