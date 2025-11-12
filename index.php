<?php
require_once 'include/Db.php';
require_once __DIR__ . '/include/Config.php';
require_once 'cache/ArticleIndex.php';
$articleIndex = new ArticleIndex();
$articles = $articleIndex->getIndex();
$config = Config::getInstance();
$landingEnabled = $config->get('landing_enabled', '0') === '1';
if ($config->get('landing_enabled', '0') === '1') {
    echo $config->get('landing_code', '');
    exit;
}
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
if (empty($articles)) {
    $articles = $articleIndex->buildIndex();
}
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', __DIR__);
}
if (!file_exists('cache')) {
    @mkdir('cache', 0755, true);
}
$cache_loaded = false;
$index_loaded = false;
try {
    if (file_exists('cache/SimpleCache.php')) {
        require_once 'cache/SimpleCache.php';
        $cache_loaded = true;
    }
} catch (Exception $e) {
}
if ($cache_loaded) {
    try {
        require_once 'cache/FileCache.php';
        $cache = new FileCache();
        $cache_key = 'all_articles_basic';
        $articles = $cache->get($cache_key);      
        if ($articles === false) {
            $articles = array_values($articleIndex->getIndex()); 
            if (!empty($articles)) {
                $cache->set($cache_key, $articles);
            }
        }
    } catch (Exception $e) {
        $articles = array_values($articleIndex->getIndex());
    }
} else {
    $articles = array_values($articleIndex->getIndex());
}
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search && !empty($articles)) {
    $filtered_articles = array_filter($articles, function($article) use ($search) {
        $found = false;
        if (stripos($article['title'], $search) !== false) {
            $found = true;
        }
        if (stripos($article['excerpt'], $search) !== false) {
            $found = true;
        }
        if (isset($article['tags']) && is_array($article['tags'])) {
            foreach ($article['tags'] as $tag) {
                if (stripos($tag, $search) !== false) {
                    $found = true;
                    break;
                }
            }
        }
        return $found;
    });
} else {
    $filtered_articles = $articles;
}
session_start();
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$total_articles = count($filtered_articles);
$total_pages = ceil($total_articles / $per_page);
$offset = ($page - 1) * $per_page;
$paginated_articles = array_slice($filtered_articles, $offset, $per_page);
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
            flex-wrap: nowrap;
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
            padding: 2rem 0;
        }
        .article-card {
            text-decoration: none;
        }
        .article-card:hover {
            text-decoration: none;
        }
        .article-card h3,
        .article-card p,
        .article-card .article-meta,
        .article-card .article-tags {
            text-decoration: none !important;
        }
        @media (max-width: 768px) {
            .wrap {
                margin-top: 70px;
            }
        }
        .card {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(1px);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            margin: 0 auto;
            max-width: 1200px;
        }
        .nav-right {
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            gap: 1rem !important;
        }
        .theme-toggle {
            order: 1; 
        }
        .user-auth {
            order: 2; 
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: nowrap;
        }
    </style>
</head>
<body>
    <?php
    $showAnnouncement = false;
    $announcementContent = '';
    $config = Config::getInstance();
    $announcementEnabled = $config->get('announcement_enabled', '0') === '1';
    if ($announcementEnabled) {
        $announcementContent = $config->get('announcement_content', '');
        $updatedAt = $config->get('announcement_updated_at');
        if (empty($updatedAt)) {
            $updatedAt = time();
            $config->set('announcement_updated_at', $updatedAt);
        }
        $cookieNameShort = 'announcement_hide_short_' . $updatedAt;
        $cookieNameLong = 'announcement_hide_long_' . $updatedAt;
        if (!isset($_COOKIE[$cookieNameShort]) && !isset($_COOKIE[$cookieNameLong])) {
            $showAnnouncement = true;
        }
    }
    ?>
    <?php if ($showAnnouncement): ?>
    <div id="announcement-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1000; display: flex; align-items: center; justify-content: center;">
        <div id="announcement-modal" style="max-width: 80%; width: 600px; overflow-y: auto;">
            <div style="margin-bottom: 20px;">
                <?php echo $announcementContent; ?>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button id="hide-short" class="btn btn-secondary">
                    关闭（5分钟内不显示）
                </button>
                <button id="hide-long" class="btn btn-primary">
                    今日不显示
                </button>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            document.getElementById('announcement-overlay').classList.add('active');
        }, 100);
        function setCookie(name, value, minutes) {
            const date = new Date();
            date.setTime(date.getTime() + (minutes * 60 * 1000));
            const expires = "expires=" + date.toUTCString();
            document.cookie = name + "=" + value + ";" + expires + ";path=/";
        }
        function closeAnnouncement() {
            const overlay = document.getElementById('announcement-overlay');
            overlay.classList.remove('active');
            setTimeout(() => {
                overlay.style.display = 'none';
            }, 400);
        }
        document.getElementById('hide-short').addEventListener('click', function() {
            setCookie('<?php echo $cookieNameShort; ?>', '1', 5);
            closeAnnouncement();
        });
        document.getElementById('hide-long').addEventListener('click', function() {
            setCookie('<?php echo $cookieNameLong; ?>', '1', 1440); 
            closeAnnouncement();
        });
        document.getElementById('announcement-overlay').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAnnouncement();
            }
        });
    });
    </script>
    <?php endif; ?>
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
        <main class="card" role="main">
            <div class="blog-header">
                <div>
                    <span class="badge"><?php echo htmlspecialchars($badgeText); ?></span>
                    <h1 class="title"><?php echo htmlspecialchars($siteTitle); ?></h1>
                </div>
                <form class="search-box" method="GET" action="">
                    <input type="text" name="search" placeholder="搜索文章..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn primary">搜索</button>
                </form>
            </div>
            <p class="lead">
                <?php echo htmlspecialchars($welcomeText); ?>
                <?php if ($search): ?>
                    <br>搜索 "<strong><?php echo htmlspecialchars($search); ?></strong>" 的结果：
                <?php endif; ?>
            </p>
            <div class="articles-grid">
                <?php if (count($paginated_articles) > 0): ?>
                    <?php foreach ($paginated_articles as $article): ?>
                        <a href="article.php?id=<?php echo $article['id']; ?>" class="article-card">
                            <h3 class="article-title"><?php echo $article['title']; ?></h3>
                            <p class="article-excerpt"><?php echo $article['excerpt']; ?></p>
                            <div class="article-meta">
                                <span><?php echo $article['date']; ?></span>
                                <span>阅读时间: <?php echo $article['read_time'] ?? 5; ?> 分钟</span>
                            </div>
                            <div class="article-tags">
                                <?php foreach ($article['tags'] as $tag): ?>
                                    <span class="tag"><?php echo $tag; ?></span>
                                <?php endforeach; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="note" style="grid-column: 1 / -1; text-align: center;">
                        没有找到相关文章，请尝试其他搜索词。
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">上一页</a>
                    <?php endif; ?>
                    <?php 
                    $start_page = max(1, $page - 3);
                    $end_page = min($total_pages, $start_page + 6);
                    $start_page = max(1, $end_page - 6);
                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                           class="<?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo ($page + 1); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">下一页</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="footer">
                <div>共 <?php echo $total_articles; ?> 篇文章</div>
            </div>
        </main>
        </div>
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
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggle = document.getElementById('themeToggle');
            const htmlElement = document.documentElement;
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