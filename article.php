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
// POST 提交已改为 AJAX（comment_ajax.php），此处无需处理 $_POST
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
$preloadBanner = $randomBanner ?: 'img/default-banner.png';
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

// ── 动态导航菜单 ──────────────────────────────────────────────
function getNavMenuTree(): array {
    try {
        $db   = Db::getInstance();
        $stmt = $db->query(
            "SELECT * FROM nav_menus WHERE is_active=1
             ORDER BY COALESCE(parent_id,0) ASC, sort_order ASC, id ASC"
        );
        $flat = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($flat)) return _defaultNavMenus();
        $map = []; $tree = [];
        foreach ($flat as $item) { $item['children'] = []; $map[$item['id']] = $item; }
        foreach ($map as $id_key => &$item) {
            if ($item['parent_id'] && isset($map[$item['parent_id']])) {
                $map[$item['parent_id']]['children'][] = &$item;
            } else { $tree[] = &$item; }
        }
        unset($item);
        return $tree;
    } catch (Exception $e) { return _defaultNavMenus(); }
}
function _defaultNavMenus(): array {
    return [
        ['label'=>'首页','url'=>'index.php','children'=>[],'open_new_tab'=>0,'icon'=>''],
        ['label'=>'文章','url'=>'index.php','children'=>[],'open_new_tab'=>0,'icon'=>''],
        ['label'=>'关于','url'=>'#',        'children'=>[],'open_new_tab'=>0,'icon'=>''],
        ['label'=>'联系','url'=>'#',        'children'=>[],'open_new_tab'=>0,'icon'=>''],
    ];
}
$navMenus = getNavMenuTree();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(($article['title'] ?? '') ? $article['title'] . ' - ' . $htmlTitle : $htmlTitle); ?></title>
    <?php if ($preloadBanner): ?>
    <link rel="preload" href="<?php echo htmlspecialchars($preloadBanner); ?>" as="image">
    <?php endif; ?>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script>hljs.highlightAll();</script>
    
</head>
<body class="article-page">
    <!-- Reading progress bar -->
    <div class="reading-progress" id="readingProgress"></div>
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="nav-logo">
                <?php
                if (file_exists($imgDir . 'logo.png')): ?>
                    <img src="img/logo.png" alt="Logo" class="logo-img">
                <?php elseif (file_exists($imgDir . 'logo.ico')): ?>
                    <img src="img/logo.ico" alt="Logo" class="logo-img">
                <?php else: ?>
                    <span class="logo-text"><?php echo htmlspecialchars($htmlTitle); ?></span>
                <?php endif; ?>
            </a>
            <ul class="nav-menu">
                <?php foreach ($navMenus as $item): ?>
                <li class="<?php echo !empty($item['children']) ? 'has-dropdown' : ''; ?>">
                    <a href="<?php echo htmlspecialchars($item['url']); ?>"
                       class="nav-link"
                       <?php echo !empty($item['open_new_tab']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                        <?php if (!empty($item['icon'])): ?>
                            <span class="nav-icon"><?php echo htmlspecialchars($item['icon']); ?></span>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($item['label']); ?>
                        <?php if (!empty($item['children'])): ?>
                            <span class="dropdown-arrow">▾</span>
                        <?php endif; ?>
                    </a>
                    <?php if (!empty($item['children'])): ?>
                    <ul class="dropdown-menu">
                        <?php foreach ($item['children'] as $child): ?>
                        <li>
                            <a href="<?php echo htmlspecialchars($child['url']); ?>"
                               class="nav-link"
                               <?php echo !empty($child['open_new_tab']) ? 'target="_blank" rel="noopener"' : ''; ?>>
                                <?php if (!empty($child['icon'])): ?>
                                    <span class="nav-icon"><?php echo htmlspecialchars($child['icon']); ?></span>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($child['label']); ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <div class="nav-right">
                <button id="themeToggle" class="theme-toggle">🌙</button>
                <div class="user-auth">
                    <?php
                    if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
                        echo '<span class="user-welcome">欢迎，' . htmlspecialchars($_SESSION['user']['nickname']) . '</span>';
                        echo '<a href="user_center" class="btn btn-small btn-login">用户中心</a>';
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
    <div class="wrap">
        <main class="main-content" role="main">
            <a href="index.php" class="back-link">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                返回首页
            </a>

            <!-- ── Unified article card ── -->
            <div class="article-unified-card">

                <div class="auc-header">
                    <span class="badge">📖 文章详情</span>
                    <h1 class="title"><?php echo $article['title'] ?? '文章未找到'; ?></h1>
                </div>

                <div class="auc-meta">
                    <span class="meta-item"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;opacity:.65"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><?php echo $article['date'] ?? '未知'; ?></span>
                    <span class="meta-divider">·</span>
                    <span class="meta-item"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;opacity:.65"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><?php echo $article['word_count'] ?? 0; ?> 字</span>
                    <span class="meta-divider">·</span>
                    <span class="meta-item"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;opacity:.65"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>约 <?php echo $article['read_time'] ?? 5; ?> 分钟</span>
                    <span class="meta-divider meta-spacer"></span>
                    <span class="auc-tags">
                        <?php foreach (($article['tags'] ?? []) as $tag): ?>
                            <span class="tag"><?php echo $tag; ?></span>
                        <?php endforeach; ?>
                    </span>
                </div>

                <!-- TOC: auto-generated by JS -->
                <div class="toc-widget" id="tocWidget" style="display:none;">
                    <p class="toc-title">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="opacity:.6"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                        目录
                    </p>
                    <ul class="toc-list" id="tocList"></ul>
                </div>

                <div class="article-content">
                    <?php echo parse_shortcodes($article['content'] ?? '<p>文章内容加载失败。</p>'); ?>
                </div>

            </div><!-- /.article-unified-card -->
            <?php
            // ── 收藏状态初始化（服务端预判，避免首屏闪烁）────────────────
            $isFavorited  = false;
            $isLoggedInFav = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
            if ($isLoggedInFav && isset($id) && !isset($article['is_default'])) {
                try {
                    $favDb   = Db::getInstance();
                    $favStmt = $favDb->prepare(
                        "SELECT id FROM user_favorites WHERE user_id = ? AND article_id = ? LIMIT 1"
                    );
                    $favStmt->execute([(int)$_SESSION['user']['id'], $id]);
                    $isFavorited = (bool)$favStmt->fetch();
                } catch (Exception $e) { /* 静默失败 */ }
            }
            ?>
            <div class="actions">
                <a href="index.php" class="btn primary">返回首页</a>
                <?php if ($isLoggedInFav && !isset($article['is_default'])): ?>
                <button id="favBtn"
                        class="btn secondary fav-btn <?php echo $isFavorited ? 'fav-active' : ''; ?>"
                        data-article-id="<?php echo $id; ?>"
                        data-favorited="<?php echo $isFavorited ? '1' : '0'; ?>"
                        title="<?php echo $isFavorited ? '取消收藏' : '收藏文章'; ?>">
                    <svg width="15" height="15" viewBox="0 0 24 24"
                         fill="<?php echo $isFavorited ? 'currentColor' : 'none'; ?>"
                         stroke="currentColor" stroke-width="2" id="favIcon">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                    <span id="favText"><?php echo $isFavorited ? '已收藏' : '收藏文章'; ?></span>
                </button>
                <?php elseif (!$isLoggedInFav && !isset($article['is_default'])): ?>
                <a href="login" class="btn secondary" title="登录后可收藏">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                    收藏文章
                </a>
                <?php endif; ?>
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
                <!-- AJAX 状态提示区 -->
                <div id="commentStatus" class="comment-ajax-status" style="display:none;"></div>
                <?php
                if ($commentSettings['allow_guest_comments'] || $isLoggedIn) {
                ?>
                <div class="comment-form">
                    <form method="post" id="commentForm">
                        <input type="hidden" name="article_id" value="<?php echo $id; ?>">
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
                        <button type="submit" id="submitCommentBtn" class="btn primary">提交评论</button>
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
                        // Parse reply-to info
                        $replyContent = trim($reply['content']);
                        $replyToName  = null;
                        if (strpos($replyContent, '@') === 0) {
                            preg_match('/^@([^\s]+)\s*/', $replyContent, $m);
                            if (!empty($m[0])) {
                                $replyToName  = $m[1];
                                $replyContent = ltrim(substr($replyContent, strlen($m[0])));
                            }
                        }
                        if ($replyToName === null) {
                            $parentComment = getParentComment($reply['parent_id']);
                            $replyToName   = $parentComment ? $parentComment['name'] : '未知用户';
                        }
                        ?>
                        <div class="fb-comment fb-reply" id="comment_<?php echo $reply['id']; ?>">
                            <div class="fb-comment-head">
                                <img src="<?php echo getCommentAvatar($reply['email'], $reply['user_id'] ?? 0); ?>"
                                     alt="<?php echo htmlspecialchars($reply['name']); ?>"
                                     class="fb-avatar">
                                <div class="fb-meta">
                                    <span class="fb-name"><?php echo htmlspecialchars($reply['name']); ?></span>
                                    <span class="fb-date"><?php echo $reply['created_at']; ?></span>
                                </div>
                            </div>
                            <div class="fb-comment-body">
                                <div class="fb-reply-badge">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle;margin-right:3px;"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
                                    回复 <span class="fb-reply-to-name">@<?php echo htmlspecialchars($replyToName); ?></span>
                                </div>
                                <div class="fb-content"><?php echo nl2br(htmlspecialchars($replyContent)); ?></div>
                                <div class="fb-actions">
                                    <a href="#" class="reply-link"
                                       data-comment-id="<?php echo $reply['id']; ?>"
                                       data-comment-name="<?php echo htmlspecialchars($reply['name']); ?>">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:3px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>回复
                                    </a>
                                </div>
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
                    <div class="fb-comment fb-top-comment" id="comment_<?php echo $comment['id']; ?>">
                        <div class="fb-comment-head">
                            <img src="<?php echo getCommentAvatar($comment['email'], $comment['user_id'] ?? 0); ?>"
                                 alt="<?php echo htmlspecialchars($comment['name']); ?>"
                                 class="fb-avatar">
                            <div class="fb-meta">
                                <span class="fb-name"><?php echo htmlspecialchars($comment['name']); ?></span>
                                <span class="fb-date"><?php echo $comment['created_at']; ?></span>
                            </div>
                        </div>
                        <div class="fb-comment-body">
                            <div class="fb-content"><?php echo nl2br(htmlspecialchars(trim($comment['content']))); ?></div>
                            <div class="fb-actions">
                                <a href="#" class="reply-link"
                                   data-comment-id="<?php echo $comment['id']; ?>"
                                   data-comment-name="<?php echo htmlspecialchars($comment['name']); ?>">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:3px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>回复
                                </a>
                            </div>
                        </div>
                        <?php if (!empty($comment['replies'])): ?>
                        <div class="fb-replies">
                            <?php render_flat_replies($comment['replies']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php
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
        document.querySelectorAll('.nav-menu > li:not(.has-dropdown) .nav-link').forEach(link => {
            link.addEventListener('click', () => {
                navMenu.classList.remove('active');
                navToggle.classList.remove('active');
            });
        });
        document.querySelectorAll('.has-dropdown > .nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    this.closest('li').classList.toggle('open');
                }
            });
        });
        // 预加载banner图片并立即设置背景
        (function() {
            const preloadBanner = '<?php echo $preloadBanner; ?>';
            const img = new Image();
            img.src = preloadBanner;
            
            // 立即设置背景，避免空白期
            document.body.style.setProperty('--bg-url', `url('${preloadBanner}')`);
            
            img.onload = function() {
                // 图片加载完成后，确保背景已设置
                document.body.style.setProperty('--bg-url', `url('${preloadBanner}')`);
            };
            img.onerror = function() {
                document.body.style.setProperty('--bg-url', 'url("img/default-banner.png")');
            };
        })();
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
            /* ── 工具函数：转义 HTML ── */
            function esc(str) {
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            /* ── 工具函数：nl2br ── */
            function nl2br(str) {
                return esc(str).replace(/\n/g, '<br>');
            }

            /* ── 状态提示区 ── */
            const statusEl = document.getElementById('commentStatus');
            function showStatus(msg, type /* 'success'|'error'|'info' */) {
                statusEl.textContent = msg;
                statusEl.className = 'comment-ajax-status comment-ajax-status--' + type;
                statusEl.style.display = '';
                clearTimeout(statusEl._timer);
                statusEl._timer = setTimeout(function() {
                    statusEl.style.opacity = '0';
                    setTimeout(function() {
                        statusEl.style.display = 'none';
                        statusEl.style.opacity = '';
                    }, 400);
                }, 5000);
            }

            /* ── 构建评论 HTML（已过审时即时插入）── */
            function buildCommentHTML(c, isReply, replyToName, pending) {
                var replyBadge = '';
                if (isReply && replyToName) {
                    replyBadge =
                        '<div class="fb-reply-badge">' +
                        '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle;margin-right:3px;"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>' +
                        '回复 <span class="fb-reply-to-name">@' + esc(replyToName) + '</span>' +
                        '</div>';
                }
                var typeClass = isReply ? 'fb-reply' : 'fb-top-comment';
                return (
                    '<div class="fb-comment ' + typeClass + ' comment-new-flash" id="comment_' + c.id + '">' +
                        '<div class="fb-comment-head">' +
                            '<img src="' + esc(c.avatar) + '" alt="' + esc(c.name) + '" class="fb-avatar">' +
                            '<div class="fb-meta">' +
                                '<span class="fb-name">' + esc(c.name) + (pending ? ' <span class="fb-pending-badge">审核中</span>' : '') + '</span>' +
                                '<span class="fb-date">' + esc(c.created_at) + '</span>' +
                            '</div>' +
                        '</div>' +
                        '<div class="fb-comment-body">' +
                            replyBadge +
                            '<div class="fb-content">' + nl2br(c.content) + '</div>' +
                            '<div class="fb-actions">' +
                                '<a href="#" class="reply-link" data-comment-id="' + c.id + '" data-comment-name="' + esc(c.name) + '">' +
                                '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:3px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>回复' +
                                '</a>' +
                            '</div>' +
                        '</div>' +
                    '</div>'
                );
            }

            /* ── 绑定回复链接（支持动态元素）── */
            function bindReplyLink(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    var commentId   = this.getAttribute('data-comment-id');
                    var commentName = this.getAttribute('data-comment-name');
                    var contentField = document.getElementById('content');
                    if (contentField.value.indexOf('@' + commentName) !== 0) {
                        contentField.value = '@' + commentName + ' ' + contentField.value;
                    }
                    document.getElementById('parent_id').value = commentId;
                    contentField.focus();
                    autoResizeTextarea(contentField);
                });
            }
            document.querySelectorAll('.reply-link').forEach(bindReplyLink);

            /* ── AJAX 评论提交 ── */
            var form    = document.getElementById('commentForm');
            var submitBtn = document.getElementById('submitCommentBtn');
            if (!form) return;

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                var parentId    = document.getElementById('parent_id').value;
                var contentField = document.getElementById('content');
                var rawContent  = contentField.value.trim();

                // 提交前：从内容里剥离 @mention 前缀（保持与服务端一致）
                var submitContent = rawContent;
                var replyToName   = null;
                if (parentId && parentId !== '0') {
                    var replyLink = document.querySelector('.reply-link[data-comment-id="' + parentId + '"]');
                    if (replyLink) {
                        replyToName = replyLink.getAttribute('data-comment-name');
                        var prefix = '@' + replyToName + ' ';
                        if (submitContent.startsWith(prefix)) {
                            submitContent = submitContent.substring(prefix.length);
                        }
                    }
                }

                // 禁用按钮防重复提交
                submitBtn.disabled = true;
                submitBtn.textContent = '提交中…';
                showStatus('正在提交评论…', 'info');

                var formData = new FormData(form);
                // 用剥离了 @前缀 的内容提交
                formData.set('content', submitContent);

                fetch('comment_ajax.php', {
                    method: 'POST',
                    body: formData,
                })
                .then(function(r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(function(data) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = '提交评论';

                    if (!data.success) {
                        showStatus('❌ ' + (data.message || '提交失败，请稍后重试'), 'error');
                        return;
                    }

                    // 清空表单
                    contentField.value = '';
                    document.getElementById('parent_id').value = '0';
                    autoResizeTextarea(contentField);

                    var list = document.querySelector('.comments-list');

                    if (data.comment) {
                        /* 无论是否过审，都立即插入评论到 DOM */
                        var c        = data.comment;
                        var isReply  = c.parent_id && parseInt(c.parent_id, 10) !== 0;
                        var html     = buildCommentHTML(c, isReply, replyToName, !data.approved);
                        var tmp      = document.createElement('div');
                        tmp.innerHTML = html;
                        var newNode  = tmp.firstElementChild;

                        if (isReply) {
                            var parentEl = document.getElementById('comment_' + c.parent_id);
                            if (parentEl) {
                                var repliesBlock = parentEl.querySelector(':scope > .fb-replies');
                                if (!repliesBlock) {
                                    repliesBlock = document.createElement('div');
                                    repliesBlock.className = 'fb-replies';
                                    parentEl.appendChild(repliesBlock);
                                }
                                repliesBlock.appendChild(newNode);
                            } else {
                                list.insertBefore(newNode, list.firstChild);
                            }
                        } else {
                            var emptyMsg = list.querySelector('p');
                            if (emptyMsg) emptyMsg.remove();
                            list.insertBefore(newNode, list.firstChild);
                        }

                        newNode.querySelectorAll('.reply-link').forEach(bindReplyLink);
                        setTimeout(function() { newNode.classList.remove('comment-new-flash'); }, 1800);
                        newNode.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

                        if (data.approved) {
                            showStatus('✅ 评论发布成功！', 'success');
                        } else {
                            showStatus('✅ 评论已提交，审核通过前仅自己可见', 'info');
                        }
                    } else {
                        /* 服务端未返回 comment 对象（极端情况）— 乐观渲染 */
                        var optimistic = {
                            id: 'tmp_' + Date.now(),
                            name: (document.getElementById('name') ? document.getElementById('name').value : '<?php echo addslashes($isLoggedIn ? $_SESSION['user']['nickname'] : ''); ?>') || '我',
                            content: submitContent,
                            parent_id: parentId,
                            created_at: new Date().toLocaleString('zh-CN', {hour12:false}).replace(/\//g,'-'),
                            avatar: 'https://www.gravatar.com/avatar/?d=mp&s=38',
                        };
                        var isReplyOpt = parentId && parseInt(parentId, 10) !== 0;
                        var htmlOpt    = buildCommentHTML(optimistic, isReplyOpt, replyToName, true);
                        var tmpOpt     = document.createElement('div');
                        tmpOpt.innerHTML = htmlOpt;
                        var newNodeOpt = tmpOpt.firstElementChild;
                        if (isReplyOpt) {
                            var pEl = document.getElementById('comment_' + parentId);
                            if (pEl) {
                                var rb = pEl.querySelector(':scope > .fb-replies');
                                if (!rb) { rb = document.createElement('div'); rb.className = 'fb-replies'; pEl.appendChild(rb); }
                                rb.appendChild(newNodeOpt);
                            } else { list.insertBefore(newNodeOpt, list.firstChild); }
                        } else {
                            var ep = list.querySelector('p'); if (ep) ep.remove();
                            list.insertBefore(newNodeOpt, list.firstChild);
                        }
                        newNodeOpt.querySelectorAll('.reply-link').forEach(bindReplyLink);
                        setTimeout(function() { newNodeOpt.classList.remove('comment-new-flash'); }, 1800);
                        newNodeOpt.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        showStatus('✅ 评论已提交，审核通过前仅自己可见', 'info');
                    }
                })
                .catch(function(err) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = '提交评论';
                    showStatus('❌ 网络错误，请检查连接后重试', 'error');
                    console.error('Comment AJAX error:', err);
                });
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

    <style>
    /* ── 收藏按钮样式 ────────────────────────────────────────── */
    .fav-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: background .2s, color .2s, transform .15s;
    }
    .fav-btn:active { transform: scale(.94); }
    .fav-btn.fav-active {
        background: linear-gradient(135deg, #ffe066, #ffb347);
        color: #7a4f00 !important;
        border-color: #f5c842 !important;
    }
    .fav-btn.fav-active svg { color: #e67e00; }
    .fav-btn.fav-loading { opacity: .7; pointer-events: none; }
    /* ── 夜间模式 ── */
    body.dark-mode .fav-btn {
        background: rgba(176,160,255,.1);
        color: #b096ff;
        border-color: rgba(176,160,255,.22);
    }
    body.dark-mode .fav-btn:hover {
        background: rgba(176,160,255,.18);
        border-color: rgba(176,160,255,.38);
    }
    body.dark-mode .fav-btn.fav-active {
        background: linear-gradient(135deg, #b07d10, #d4870e);
        color: #fff3cd !important;
        border-color: #b07d10 !important;
    }
    body.dark-mode .fav-btn.fav-active svg { color: #ffd666; }
    /* 收藏成功短暂提示 */
    .fav-toast {
        position: fixed;
        bottom: 32px;
        left: 50%;
        transform: translateX(-50%) translateY(20px);
        background: rgba(30,30,40,.88);
        color: #fff;
        padding: 10px 22px;
        border-radius: 24px;
        font-size: .9rem;
        opacity: 0;
        transition: opacity .25s, transform .25s;
        pointer-events: none;
        z-index: 9999;
        white-space: nowrap;
    }
    .fav-toast.show {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
    </style>

    <style>
    /* ══════════════════════════════════════════════════
       Facebook-style comment layout
       ══════════════════════════════════════════════════ */

    /* ── Top-level comment ── */
    .fb-comment {
        display: flex;
        flex-direction: column;
        gap: 0;
    }
    .fb-top-comment {
        padding-bottom: 18px;
        border-bottom: 1px solid rgba(155,140,255,.12);
        margin-bottom: 4px;
    }
    .fb-top-comment:last-child {
        border-bottom: none;
    }

    /* ── Header row ── */
    .fb-comment-head {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 6px;
    }
    .fb-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        flex-shrink: 0;
        border: 2px solid rgba(155,140,255,.25);
        object-fit: cover;
    }
    .fb-reply .fb-avatar {
        width: 30px;
        height: 30px;
    }
    .fb-meta {
        display: flex;
        flex-direction: column;
        gap: 1px;
    }
    .fb-name {
        font-weight: 700;
        font-size: .88rem;
        color: inherit;
    }
    .fb-date {
        font-size: .72rem;
        opacity: .55;
    }

    /* ── Body (indented under avatar) ── */
    .fb-comment-body {
        margin-left: 48px;  /* 38px avatar + 10px gap */
    }
    .fb-reply .fb-comment-body {
        margin-left: 40px;  /* 30px avatar + 10px gap */
    }
    .fb-content {
        font-size: .9rem;
        line-height: 1.6;
        word-break: break-word;
        white-space: pre-wrap;
    }

    /* ── Reply badge "↩ 回复 @Name" ── */
    .fb-reply-badge {
        display: inline-flex;
        align-items: center;
        font-size: .75rem;
        color: #9b8cff;
        background: rgba(155,140,255,.1);
        border-radius: 20px;
        padding: 2px 9px 2px 6px;
        margin-bottom: 5px;
        font-weight: 500;
        line-height: 1.8;
    }
    .fb-reply-to-name {
        font-weight: 700;
        margin-left: 2px;
        color: #7c6df5;
    }

    /* ── Actions (回复 link) ── */
    .fb-actions {
        margin-top: 6px;
        display: flex;
        gap: 16px;
    }
    .fb-actions .reply-link {
        display: inline-flex;
        align-items: center;
        font-size: .78rem;
        font-weight: 600;
        color: #9b8cff;
        text-decoration: none;
        opacity: .75;
        transition: opacity .2s;
    }
    .fb-actions .reply-link:hover {
        opacity: 1;
        color: #7c6df5;
    }

    /* ── Replies block (indented + connector line) ── */
    .fb-replies {
        margin-left: 19px;          /* half of top-level avatar width */
        padding-left: 18px;
        border-left: 2px solid rgba(155,140,255,.2);
        margin-top: 12px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    /* ── Dark mode overrides ── */
    body.dark-mode .fb-top-comment {
        border-bottom-color: rgba(155,140,255,.18);
    }
    body.dark-mode .fb-reply-badge {
        background: rgba(155,140,255,.15);
        color: #b8acff;
    }
    body.dark-mode .fb-reply-to-name {
        color: #a899ff;
    }
    body.dark-mode .fb-actions .reply-link {
        color: #b8acff;
    }
    body.dark-mode .fb-replies {
        border-left-color: rgba(155,140,255,.28);
    }
    body.dark-mode .fb-avatar {
        border-color: rgba(155,140,255,.35);
    }
    </style>



    <script>
    (function () {
        const btn = document.getElementById('favBtn');
        if (!btn) return;

        const toast = document.getElementById('favToast');
        let toastTimer;

        function showToast(msg) {
            toast.textContent = msg;
            toast.classList.add('show');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => toast.classList.remove('show'), 2200);
        }

        btn.addEventListener('click', function () {
            if (btn.classList.contains('fav-loading')) return;

            const articleId = btn.getAttribute('data-article-id');
            btn.classList.add('fav-loading');

            fetch('favorites_api.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    'action=toggle&article_id=' + encodeURIComponent(articleId),
            })
            .then(r => r.json())
            .then(data => {
                btn.classList.remove('fav-loading');
                if (data.success) {
                    const icon    = document.getElementById('favIcon');
                    const text    = document.getElementById('favText');
                    const active  = data.favorited;
                    btn.setAttribute('data-favorited', active ? '1' : '0');
                    btn.title = active ? '取消收藏' : '收藏文章';

                    if (active) {
                        btn.classList.add('fav-active');
                        icon.setAttribute('fill', 'currentColor');
                        text.textContent = '已收藏';
                    } else {
                        btn.classList.remove('fav-active');
                        icon.setAttribute('fill', 'none');
                        text.textContent = '收藏文章';
                    }
                    showToast(data.message || (active ? '收藏成功 ⭐' : '已取消收藏'));
                } else {
                    showToast(data.message || '操作失败，请稍后重试');
                    if (data.message && data.message.includes('登录')) {
                        setTimeout(() => { window.location.href = 'login'; }, 1500);
                    }
                }
            })
            .catch(() => {
                btn.classList.remove('fav-loading');
                showToast('网络错误，请检查连接');
            });
        });
    })();
    </script>
    <!-- Back to top -->
    <button class="back-top-btn" id="backTopBtn" title="回到顶部" aria-label="回到顶部">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>
    </button>
    <script>
    // ── Reading Progress Bar ────────────────────────────
    (function() {
        const bar = document.getElementById('readingProgress');
        if (!bar) return;
        function updateProgress() {
            const scrollTop = window.scrollY || document.documentElement.scrollTop;
            const docHeight = document.documentElement.scrollHeight - window.innerHeight;
            const pct = docHeight > 0 ? Math.min(100, (scrollTop / docHeight) * 100) : 0;
            bar.style.width = pct + '%';
        }
        window.addEventListener('scroll', updateProgress, { passive: true });
        updateProgress();
    })();

    // ── Back to Top ─────────────────────────────────────
    (function() {
        const btn = document.getElementById('backTopBtn');
        if (!btn) return;
        window.addEventListener('scroll', function() {
            if (window.scrollY > 400) {
                btn.classList.add('visible');
            } else {
                btn.classList.remove('visible');
            }
        }, { passive: true });
        btn.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    })();

    // ── Auto Table of Contents ──────────────────────────
    document.addEventListener('DOMContentLoaded', function() {
        const content = document.querySelector('.article-content');
        const tocWidget = document.getElementById('tocWidget');
        const tocList = document.getElementById('tocList');
        if (!content || !tocWidget || !tocList) return;
        const headings = content.querySelectorAll('h2, h3');
        if (headings.length < 2) return;
        headings.forEach(function(h, i) {
            if (!h.id) h.id = 'heading-' + i;
            const li = document.createElement('li');
            const a = document.createElement('a');
            a.href = '#' + h.id;
            a.textContent = h.textContent.replace(/^[▸"\s]+/, '');
            a.className = h.tagName === 'H3' ? 'toc-h3' : '';
            a.addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById(h.id).scrollIntoView({ behavior: 'smooth', block: 'start' });
                history.pushState(null, null, '#' + h.id);
            });
            li.appendChild(a);
            tocList.appendChild(li);
        });
        tocWidget.style.display = '';

        // Highlight active TOC item on scroll
        const allLinks = tocList.querySelectorAll('a');
        function onScroll() {
            let activeId = null;
            headings.forEach(function(h) {
                if (h.getBoundingClientRect().top <= 120) activeId = h.id;
            });
            allLinks.forEach(function(a) {
                a.classList.toggle('active', a.getAttribute('href') === '#' + activeId);
            });
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    });

    // ── Scroll-reveal for article content ──────────────
    document.addEventListener('DOMContentLoaded', function() {
        if (!('IntersectionObserver' in window)) return;
        const content = document.querySelector('.article-content');
        if (!content) return;
        const items = content.querySelectorAll('h2, h3, p, blockquote, .code-block, table, ul, ol, img, video');
        const obs = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('content-revealed');
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
        items.forEach(function(el, i) {
            el.classList.add('content-reveal');
            el.style.transitionDelay = Math.min(i * 0.04, 0.3) + 's';
            obs.observe(el);
        });
    });
    </script>
    <style>
    /* ── AJAX 评论状态提示 ── */
    .comment-ajax-status {
        margin: 10px 0 14px;
        padding: 10px 16px;
        border-radius: 10px;
        font-size: .88rem;
        font-weight: 500;
        line-height: 1.5;
        transition: opacity .4s ease;
    }
    .comment-ajax-status--success {
        background: rgba(72, 199, 142, .15);
        border: 1px solid rgba(72, 199, 142, .4);
        color: #1a7a4a;
    }
    .comment-ajax-status--error {
        background: rgba(255, 90, 90, .12);
        border: 1px solid rgba(255, 90, 90, .35);
        color: #c0392b;
    }
    .comment-ajax-status--info {
        background: rgba(155, 140, 255, .12);
        border: 1px solid rgba(155, 140, 255, .35);
        color: #5a4fcf;
    }
    body.dark-mode .comment-ajax-status--success { color: #5ee8a0; background: rgba(72,199,142,.1); }
    body.dark-mode .comment-ajax-status--error   { color: #ff8080; background: rgba(255,90,90,.1); }
    body.dark-mode .comment-ajax-status--info    { color: #b8acff; background: rgba(155,140,255,.1); }

    /* ── 新评论高亮闪烁 ── */
    @keyframes commentFlash {
        0%   { box-shadow: 0 0 0 3px rgba(155,140,255,.55); background: rgba(155,140,255,.07); }
        60%  { box-shadow: 0 0 0 6px rgba(155,140,255,.15); }
        100% { box-shadow: none; background: transparent; }
    }
    .comment-new-flash {
        animation: commentFlash 1.6s ease forwards;
        border-radius: 10px;
    }
    /* ── 待审核标签 ── */
    .fb-pending-badge {
        display: inline-block;
        font-size: .65rem;
        font-weight: 600;
        padding: 1px 7px;
        border-radius: 20px;
        background: rgba(255, 180, 50, .18);
        border: 1px solid rgba(255, 180, 50, .45);
        color: #b07a00;
        vertical-align: middle;
        margin-left: 5px;
        letter-spacing: .02em;
    }
    body.dark-mode .fb-pending-badge {
        background: rgba(255, 200, 80, .12);
        border-color: rgba(255, 200, 80, .3);
        color: #ffd166;
    }
    </style>
</body>
</html>