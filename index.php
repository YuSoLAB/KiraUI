<?php
require_once 'include/Db.php';
require_once __DIR__ . '/include/Config.php';
require_once 'cache/ArticleIndex.php';
require_once 'auto_login.php';

autoLogin();
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
try {
    if (file_exists('cache/SimpleCache.php')) {
        require_once 'cache/SimpleCache.php';
        $cache_loaded = true;
    }
} catch (Exception $e) {}

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
        if (stripos($article['title'], $search) !== false) $found = true;
        if (stripos($article['excerpt'], $search) !== false) $found = true;
        if (isset($article['tags']) && is_array($article['tags'])) {
            foreach ($article['tags'] as $tag) {
                if (stripos($tag, $search) !== false) { $found = true; break; }
            }
        }
        return $found;
    });
} else {
    $filtered_articles = $articles;
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$total_articles = count($filtered_articles);
$total_pages = ceil($total_articles / $per_page);
$offset = ($page - 1) * $per_page;
$paginated_articles = array_slice($filtered_articles, $offset, $per_page);

// ── 动态导航菜单 ────────────────────────────────────────────────
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
        foreach ($map as $id => &$item) {
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
    <title><?php echo htmlspecialchars($htmlTitle); ?></title>
    <?php if ($preloadBanner): ?>
    <link rel="preload" href="<?php echo htmlspecialchars($preloadBanner); ?>" as="image">
    <?php endif; ?>
    <link rel="stylesheet" href="style.css">
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
        $cookieNameLong  = 'announcement_hide_long_'  . $updatedAt;
        if (!isset($_COOKIE[$cookieNameShort]) && !isset($_COOKIE[$cookieNameLong])) {
            $showAnnouncement = true;
        }
    }
    ?>
    <?php if ($showAnnouncement): ?>
    <div id="announcement-overlay" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:1000;display:flex;align-items:center;justify-content:center;">
        <div id="announcement-modal" style="max-width:80%;width:600px;overflow-y:auto;">
            <div style="margin-bottom:20px;"><?php echo $announcementContent; ?></div>
            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
                <button id="hide-short" class="btn btn-secondary">关闭（5分钟内不显示）</button>
                <button id="hide-long"  class="btn btn-primary">今日不显示</button>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => document.getElementById('announcement-overlay').classList.add('active'), 100);
        function setCookie(name, value, minutes) {
            const date = new Date();
            date.setTime(date.getTime() + (minutes * 60 * 1000));
            document.cookie = name + "=" + value + ";expires=" + date.toUTCString() + ";path=/";
        }
        function closeAnnouncement() {
            const overlay = document.getElementById('announcement-overlay');
            overlay.classList.remove('active');
            setTimeout(() => { overlay.style.display = 'none'; }, 400);
        }
        document.getElementById('hide-short').addEventListener('click', function() {
            setCookie('<?php echo $cookieNameShort; ?>', '1', 5); closeAnnouncement();
        });
        document.getElementById('hide-long').addEventListener('click', function() {
            setCookie('<?php echo $cookieNameLong; ?>', '1', 1440); closeAnnouncement();
        });
        document.getElementById('announcement-overlay').addEventListener('click', function(e) {
            if (e.target === this) closeAnnouncement();
        });
    });
    </script>
    <?php endif; ?>

    <!-- ── 导航栏（动态菜单）──────────────────────────────── -->
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
                <span></span><span></span><span></span>
            </button>
        </div>
    </nav>

    <div class="sparkles" id="sparkles" aria-hidden="true"></div>
    <div class="wrap">
        <main class="main-content" role="main">
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
                            <div class="article-card-inner">
                                <div class="article-thumb">
                                    <?php if (!empty($article['cover_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($article['cover_image']); ?>" alt="<?php echo htmlspecialchars($article['title']); ?>">
                                    <?php else: ?>
                                        <div class="article-thumb-placeholder">
                                            <span>📄</span>
                                            <em>封面图</em>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="article-card-body">
                                    <h3 class="article-title"><?php echo $article['title']; ?></h3>
                                    <p class="article-excerpt"><?php echo $article['excerpt']; ?></p>
                                    <div class="article-footer-row">
                                        <div class="article-meta">
                                            <span>📅 <?php echo $article['date']; ?></span>
                                            <span>⏱ <?php echo $article['read_time'] ?? 5; ?> 分钟</span>
                                        </div>
                                        <div class="article-tags">
                                            <?php foreach ($article['tags'] as $tag): ?>
                                                <span class="tag"><?php echo $tag; ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <span class="article-arrow" aria-hidden="true">›</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="articles-empty">
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
                    $end_page   = min($total_pages, $start_page + 6);
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
        <aside class="sidebar">
            <div class="sidebar-widget">
            <h3>标签云</h3>
            <?php
            try {
                $indexStats  = $articleIndex->getIndexStats();
                $tags = $indexStats['tags'] ?? [];
                if (!empty($tags)) {
                    arsort($tags);
                    $maxCount = max($tags);
                    $minCount = min($tags);
                    $sizeRange = max($maxCount - $minCount, 1);
                    $displayTags = array_slice($tags, 0, 30, true);
                    echo '<div class="tags-cloud" style="display:flex;flex-wrap:wrap;gap:.8rem;margin-top:1rem;">';
                    foreach ($displayTags as $tag => $count) {
                        $fontSize = 12 + (($count - $minCount) / $sizeRange) * 12;
                        $colorIntensity = 0.4 + (($count - $minCount) / $sizeRange) * 0.4;
                        $hue = crc32($tag) % 360;
                        $tagUrl = 'index.php?search=' . urlencode($tag);
                        echo '<a href="' . $tagUrl . '" class="tag-cloud-item" 
                              style="font-size:' . round($fontSize) . 'px;
                                     background:hsla(' . $hue . ',70%,60%,' . $colorIntensity . ');
                                     color:white;text-shadow:0 1px 2px rgba(0,0,0,.3);
                                     padding:.4rem .8rem;border-radius:20px;
                                     text-decoration:none;display:inline-block;transition:all .3s ease;">';
                        echo htmlspecialchars($tag);
                        echo '<span style="font-size:.8em;margin-left:.3rem;opacity:.8;">(' . $count . ')</span>';
                        echo '</a>';
                    }
                    echo '</div>';
                } else {
                    echo '<p style="color:var(--sub);margin-top:1rem;">暂无标签数据</p>';
                }
            } catch (Exception $e) {
                echo '<p style="color:var(--sub);margin-top:1rem;">标签数据加载失败</p>';
            }
            ?>
            </div><!-- /sidebar-widget -->
        </aside>
    </div>
    <script>
        const navToggle = document.getElementById('navToggle');
        const navMenu   = document.querySelector('.nav-menu');
        navToggle.addEventListener('click', () => {
            navMenu.classList.toggle('active');
            navToggle.classList.toggle('active');
        });
        // 移动端下拉菜单支持
        document.querySelectorAll('.has-dropdown > .nav-link').forEach(link => {
            link.addEventListener('click', e => {
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    link.closest('li').classList.toggle('open');
                }
            });
        });
        document.querySelectorAll('.nav-menu > li:not(.has-dropdown) .nav-link').forEach(link => {
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
                s.style.top  = (Math.random()*100) + 'vh';
                s.style.animationDuration = (10 + Math.random()*12) + 's';
                s.style.animationDelay = (Math.random()*-20) + 's';
                s.style.opacity = .4 + Math.random()*.6;
                box.appendChild(s);
            }
            if(vw < 480){ var kids = box.querySelectorAll('i'); for(var j=0;j<kids.length;j+=2) kids[j].remove(); }
        })();
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