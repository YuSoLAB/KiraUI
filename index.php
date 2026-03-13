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
$landingMode    = $config->get('landing_mode', 'replace'); // 'replace' | 'cover'

if ($landingEnabled) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }

    // 封面页模式跳过条件（满足任意一条即跳过，并持久化 session）：
    //   1. ?enter=1 显式进入
    //   2. 携带其他查询参数（?page= / ?search= 等站内链接）
    //   3. 本次会话内已经进入过站点
    $skipLanding = false;
    if ($landingMode === 'cover') {
        if (!empty($_GET)) {
            $skipLanding = true;               // 带任意参数
        } elseif (!empty($_SESSION['site_entered'])) {
            $skipLanding = true;               // 本会话已入站
        }
        if ($skipLanding) {
            $_SESSION['site_entered'] = true;  // 持久化，后续裸访问也跳过
        }
    }

    if (!$skipLanding) {
        echo $config->get('landing_code', '');
        exit;
    }
    // $skipLanding === true → 跳过展示页，继续渲染原始首页
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

$pinnedIds = [];   // [article_id => pinned_at_string]  按置顶时间倒序
try {
    $db = Db::getInstance();
    $pinStmt = $db->query(
        "SELECT id, pinned_at FROM article_index
         WHERE pinned_at IS NOT NULL
         ORDER BY pinned_at DESC"
    );
    foreach ($pinStmt->fetchAll(PDO::FETCH_ASSOC) as $pr) {
        $pinnedIds[(int)$pr['id']] = $pr['pinned_at'];
    }
} catch (Exception $_pinEx) {
    // 列尚未迁移或数据库不可用时，静默忽略，不影响正常展示
}
 
if (!empty($pinnedIds) && !empty($articles)) {
    $pinned  = [];
    $regular = [];
    foreach ($articles as $a) {
        if (isset($pinnedIds[(int)$a['id']])) {
            // 将 pinned_at 附到文章数据上（模板可选用）
            $a['pinned_at'] = $pinnedIds[(int)$a['id']];
            $pinned[] = $a;
        } else {
            $regular[] = $a;
        }
    }
    // 置顶区：按 pinned_at 倒序（最新置顶的排最前）
    usort($pinned, function($x, $y) {
        return strcmp($y['pinned_at'], $x['pinned_at']);
    });
    $articles = array_merge($pinned, $regular);
}
// ══════════════════════════════════════════════════════
 
// ─── 搜索过滤（保持原逻辑；置顶文章自然排在匹配结果前面）───────
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search && !empty($articles)) {
    $filtered_articles = array_filter($articles, function($article) use ($search) {
        $found = false;
        if (stripos($article['title'],   $search) !== false) $found = true;
        if (stripos($article['excerpt'], $search) !== false) $found = true;
        if (isset($article['tags']) && is_array($article['tags'])) {
            foreach ($article['tags'] as $tag) {
                if (stripos($tag, $search) !== false) { $found = true; break; }
            }
        }
        return $found;
    });
    // array_filter 保留原键，重新索引以保证顺序
    $filtered_articles = array_values($filtered_articles);
} else {
    $filtered_articles = $articles;
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
    <style>
    /* ── 文章卡片封面图 ── */
    .article-thumb img.article-thumb-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        border-radius: inherit;
    }
    /* banner 用作封面时，让宽景图居中裁切，视觉更佳 */
    .article-thumb img.is-banner-fallback {
        object-position: center center;
        opacity: .88;
        filter: brightness(.95) saturate(1.05);
        transition: opacity .3s, filter .3s, transform .35s cubic-bezier(.22,1,.36,1);
    }
    .article-card:hover .article-thumb img.is-banner-fallback {
        opacity: 1;
        filter: brightness(1) saturate(1.1);
        transform: scale(1.04);
    }
    .article-card { position: relative; }
    .pin-badge {
        display: inline-flex;
        align-items: center;
        font-size: .68rem;
        font-weight: 600;
        color: #8a6800;
        background: rgba(245,197,24,.18);
        border: 1px solid rgba(245,197,24,.5);
        border-radius: 4px;
        padding: .1rem .35rem;
        margin-left: .4rem;
        vertical-align: middle;
        white-space: nowrap;
        flex-shrink: 0;
        line-height: 1.4;
    }
    body.dark-mode .pin-badge {
        color: #f5c518;
        background: rgba(245,197,24,.12);
        border-color: rgba(245,197,24,.35);
    }
    .article-title-row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: .2rem;
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
                        echo '<span class="user-welcome">欢迎，' . htmlspecialchars($_SESSION['user']['nickname'] ?: $_SESSION['user']['username']) . '</span>';
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
                    <?php foreach ($paginated_articles as $idx => $article): ?>
                        <a href="article.php?id=<?php echo $article['id']; ?>" class="article-card" data-idx="<?php echo $idx; ?>">
                            <div class="article-card-inner">
                                <div class="article-thumb">
                                    <?php
                                    if (!empty($article['cover_image'])) {
                                        $thumbSrc = htmlspecialchars($article['cover_image']);
                                    } else {
                                        // 无封面图时随机取 banner，用文章 ID 哈希保证同篇文章显示相同封面
                                        $fallbackBanners = !empty($banners) ? $banners : ['img/default-banner.png'];
                                        $thumbSrc = htmlspecialchars(
                                            $fallbackBanners[array_rand($fallbackBanners)]
                                        );
                                    }
                                    ?>
                                    <img src="<?php echo $thumbSrc; ?>" alt="<?php echo htmlspecialchars($article['title']); ?>" class="article-thumb-img<?php echo empty($article['cover_image']) ? ' is-banner-fallback' : ''; ?>">
                                </div>
                                <div class="article-card-body">
                                    <h3 class="article-title article-title-row"><?php echo $article['title']; ?><?php if (!empty($article['pinned_at'])): ?><span class="pin-badge" title="置顶文章">📌 置顶</span><?php endif; ?></h3>
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
        // ── 文章卡片滚动弹出（纯 JS，不依赖 CSS）────────────────────
        document.addEventListener('DOMContentLoaded', function () {
            var cards = Array.from(document.querySelectorAll('.article-card'));
            if (!cards.length) return;

            var STAGGER = 120; // 每张卡片间隔 ms

            // 立即把所有卡片隐藏（直接操作 style，不依赖 CSS）
            cards.forEach(function (card) {
                card.style.opacity    = '0';
                card.style.transform  = 'translateY(52px) scale(0.96)';
                card.style.transition = 'none';
            });

            function revealCard(card, delay) {
                setTimeout(function () {
                    card.style.transition = 'opacity 0.55s cubic-bezier(0.16,1,0.3,1), transform 0.55s cubic-bezier(0.16,1,0.3,1)';
                    card.style.opacity    = '1';
                    card.style.transform  = 'translateY(0) scale(1)';
                    // 动画结束后恢复 hover 能力
                    setTimeout(function () {
                        card.style.transition = 'transform .2s cubic-bezier(.22,1,.36,1), box-shadow .2s ease, border-color .2s ease';
                    }, 600);
                }, delay);
            }

            var observer = new IntersectionObserver(function (entries) {
                var batch = entries
                    .filter(function (e) { return e.isIntersecting; })
                    .sort(function (a, b) {
                        return a.target.getBoundingClientRect().top - b.target.getBoundingClientRect().top;
                    });

                batch.forEach(function (entry, i) {
                    observer.unobserve(entry.target);
                    revealCard(entry.target, i * STAGGER);
                });
            }, { threshold: 0.05 });

            cards.forEach(function (card) { observer.observe(card); });
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

    <?php
    // ── 社交悬浮图标 ────────────────────────────────────────────────
    $socialPlatforms = [
        'qq'       => ['label'=>'QQ',         'color'=>'#12B7F5'],
        'wechat'   => ['label'=>'微信',        'color'=>'#07C160'],
        'weibo'    => ['label'=>'微博',        'color'=>'#E6162D'],
        'x'        => ['label'=>'X',           'color'=>'#000000'],
        'facebook' => ['label'=>'Facebook',    'color'=>'#1877F2'],
        'instagram'=> ['label'=>'Instagram',   'color'=>'#E4405F'],
        'youtube'  => ['label'=>'YouTube',     'color'=>'#FF0000'],
        'github'   => ['label'=>'GitHub',      'color'=>'#24292E'],
        'steam'    => ['label'=>'Steam',       'color'=>'#1b2838'],
        'tiktok'   => ['label'=>'TikTok',      'color'=>'#010101'],
        'douyin'   => ['label'=>'抖音',        'color'=>'#010101'],
        'bilibili' => ['label'=>'Bilibili',    'color'=>'#FF6699'],
        'telegram' => ['label'=>'Telegram',    'color'=>'#26A5E4'],
        'discord'  => ['label'=>'Discord',     'color'=>'#5865F2'],
        'line'     => ['label'=>'LINE',        'color'=>'#06C755'],
    ];
    $socialLinks = [];
    foreach ($socialPlatforms as $key => $info) {
        $val = $config->get('social_' . $key, '');
        if (!empty(trim($val))) {
            $socialLinks[$key] = array_merge($info, ['url' => trim($val)]);
        }
    }
    if (!empty($socialLinks)):
    ?>
    <div class="social-float-bar" id="socialFloatBar">
        <?php foreach ($socialLinks as $key => $info): ?>
        <a class="social-float-icon"
           href="<?php echo htmlspecialchars($info['url']); ?>"
           target="_blank" rel="noopener noreferrer"
           title="<?php echo htmlspecialchars($info['label']); ?>"
           style="--sc:<?php echo $info['color']; ?>">
            <?php echo _getSocialSvgFront($key, '#fff', 20); ?>
            <span class="social-float-tooltip"><?php echo htmlspecialchars($info['label']); ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <!-- 折叠按钮（桌面 + 手机通用） -->
    <button class="social-float-toggle" id="socialFloatToggle" aria-label="社交链接" aria-expanded="false">
        <!-- 关闭态：分享图标 -->
        <svg class="sficon-share" width="20" height="20" viewBox="0 0 24 24" fill="#fff" xmlns="http://www.w3.org/2000/svg">
            <path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/>
        </svg>
        <!-- 展开态：关闭 × 图标 -->
        <svg class="sficon-close" width="18" height="18" viewBox="0 0 24 24" fill="#fff" xmlns="http://www.w3.org/2000/svg">
            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
        </svg>
    </button>
    <style>
    /* ── 折叠按钮（桌面 + 手机通用） ── */
    .social-float-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
        position: fixed;
        right: 18px;
        bottom: 24px;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: rgba(55, 53, 80, 0.82);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        border: none;
        cursor: pointer;
        z-index: 1001;
        box-shadow: 0 4px 16px rgba(0,0,0,.28);
        transition: transform .26s cubic-bezier(.22,1,.36,1),
                    background .22s ease;
    }
    .social-float-toggle:hover {
        background: rgba(80, 76, 120, 0.92);
    }
    .social-float-toggle.is-open {
        background: rgba(90, 72, 160, 0.92);
    }
    /* 两个图标切换 */
    .sficon-share, .sficon-close {
        position: absolute;
        transition: opacity .2s ease, transform .22s cubic-bezier(.22,1,.36,1);
    }
    .sficon-close  { opacity: 0; transform: rotate(-45deg) scale(.7); }
    .sficon-share  { opacity: 1; transform: rotate(0) scale(1); }
    .social-float-toggle.is-open .sficon-share { opacity: 0; transform: rotate(45deg) scale(.7); }
    .social-float-toggle.is-open .sficon-close { opacity: 1; transform: rotate(0) scale(1); }

    /* ── 图标栏：默认收起，展开后从底部弹出 ── */
    .social-float-bar {
        position: fixed;
        right: 18px;
        bottom: 80px;           /* 让出按钮空间 */
        display: flex;
        flex-direction: column;
        flex-wrap: wrap-reverse;
        align-content: flex-end;
        gap: 10px;
        z-index: 999;
        max-height: calc(100vh - 130px);
        pointer-events: none;
        opacity: 0;
        transform: translateY(10px);
        transition: opacity .26s ease,
                    transform .26s cubic-bezier(.22,1,.36,1);
    }
    .social-float-bar.is-open {
        pointer-events: auto;
        opacity: 1;
        transform: translateY(0);
    }

    /* ── 单个图标 ── */
    .social-float-icon {
        position: relative;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: var(--sc, #888);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 14px rgba(0,0,0,.22);
        text-decoration: none;
        transition: transform .22s cubic-bezier(.22,1,.36,1),
                    box-shadow .22s ease,
                    opacity .22s ease;
        opacity: .88;
        z-index: 1;
    }
    .social-float-icon:hover {
        transform: scale(1.18) translateX(-4px);
        box-shadow: 0 8px 24px rgba(0,0,0,.3);
        opacity: 1;
        z-index: 100;
    }
    .social-float-icon svg { display: block; pointer-events: none; }

    /* ── Tooltip（桌面向左，手机向上） ── */
    .social-float-tooltip {
        position: absolute;
        right: calc(100% + 10px);
        top: 50%;
        transform: translateY(-50%) translateX(6px);
        background: rgba(30,28,50,.88);
        color: #fff;
        font-size: .75rem;
        font-weight: 600;
        white-space: nowrap;
        padding: 4px 10px;
        border-radius: 20px;
        pointer-events: none;
        opacity: 0;
        transition: opacity .18s ease, transform .18s ease;
        backdrop-filter: blur(4px);
        z-index: 101;
    }
    .social-float-icon:hover .social-float-tooltip {
        opacity: 1;
        transform: translateY(-50%) translateX(0);
    }

    /* ── 手机端微调 ── */
    @media (max-width: 600px) {
        .social-float-toggle { right: 16px; }
        .social-float-bar    { right: 16px; gap: 8px; }
        .social-float-icon   { width: 40px; height: 40px; }
        /* tooltip 改为向上，避免超出屏幕 */
        .social-float-tooltip {
            right: 50%;
            top: auto;
            bottom: calc(100% + 8px);
            transform: translateX(50%) translateY(4px);
        }
        .social-float-icon:hover .social-float-tooltip {
            transform: translateX(50%) translateY(0);
        }
        .social-float-icon:hover {
            transform: scale(1.12) translateX(0);
        }
    }
    </style>
    <script>
    (function() {
        var toggle = document.getElementById('socialFloatToggle');
        var bar    = document.getElementById('socialFloatBar');
        if (!toggle || !bar) return;
        toggle.addEventListener('click', function() {
            var open = bar.classList.toggle('is-open');
            toggle.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        // 点击图标后自动收起
        bar.querySelectorAll('.social-float-icon').forEach(function(a) {
            a.addEventListener('click', function() {
                bar.classList.remove('is-open');
                toggle.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            });
        });
        // 点击页面其他区域收起
        document.addEventListener('click', function(e) {
            if (!bar.contains(e.target) && !toggle.contains(e.target)) {
                bar.classList.remove('is-open');
                toggle.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    })();
    </script>
    <?php endif; ?>

    <?php
    /**
     * 前台社交图标 SVG 渲染函数（独立于后台，避免重复定义冲突）
     */
    function _getSocialSvgFront(string $platform, string $color='#fff', int $size=24): string {
        $s = $size;
        // QQ 官方企鹅图标（Bootstrap Icons bi-tencent-qq，viewBox 16x16，需单独返回）
        if ($platform === 'qq') {
            return "<svg width=\"{$s}\" height=\"{$s}\" viewBox=\"0 0 16 16\" fill=\"{$color}\" xmlns=\"http://www.w3.org/2000/svg\">"
                 . '<path d="M6.048 3.323c.022.277-.13.523-.338.55-.21.026-.397-.176-.419-.453-.022-.277.13-.523.338-.55.21-.026.397.176.42.453Zm2.265-.24c-.603-.146-.894.256-.936.333-.027.048-.008.117.037.15.045.035.092.025.119-.003.361-.39.751-.172.829-.129l.011.007c.053.024.147.028.193-.098.023-.063.017-.11-.006-.142-.016-.023-.089-.08-.247-.118Z"/>'
                 . '<path fill-rule="evenodd" d="M11.727 6.719c0-.022.01-.375.01-.557 0-3.07-1.45-6.156-5.015-6.156-3.564 0-5.014 3.086-5.014 6.156 0 .182.01.535.01.557l-.72 1.795a25.85 25.85 0 0 0-.534 1.508c-.68 2.187-.46 3.093-.292 3.113.36.044 1.401-1.647 1.401-1.647 0 .979.504 2.256 1.594 3.179-.408.126-.907.319-1.228.556-.29.213-.253.43-.201.518.228.386 3.92.246 4.985.126 1.065.12 4.756.26 4.984-.126.052-.088.088-.305-.2-.518-.322-.237-.822-.43-1.23-.557 1.09-.922 1.594-2.2 1.594-3.178 0 0 1.041 1.69 1.401 1.647.168-.02.388-.926-.292-3.113a25.78 25.78 0 0 0-.534-1.508l-.72-1.795ZM9.773 5.53c-.13-.286-1.431-.605-3.042-.605h-.017c-1.611 0-2.913.319-3.042.605a.096.096 0 0 0-.01.04c0 .022.008.04.018.056.11.159 1.554.943 3.034.943h.017c1.48 0 2.924-.784 3.033-.943a.095.095 0 0 0 .008-.096Zm-4.32-.989c-.483.022-.896-.529-.922-1.229-.026-.7.344-1.286.828-1.308.483-.022.896.529.922 1.23.027.7-.344 1.286-.827 1.307Zm2.538 0c.483.022.896-.529.922-1.229.026-.7-.344-1.286-.827-1.308-.484-.022-.896.529-.923 1.23-.026.7.344 1.285.828 1.307ZM2.928 8.99a10.674 10.674 0 0 0-.097 2.284c.146 2.45 1.6 3.99 3.846 4.012h.091c2.246-.023 3.7-1.562 3.846-4.011.054-.9 0-1.663-.097-2.285-1.312.26-2.669.41-3.786.396h-.017c-.297.003-.611-.005-.937-.023v2.148c-1.106.154-2.21-.068-2.21-.068V9.107a22.93 22.93 0 0 1-.639-.117Z"/>'
                 . '</svg>';
        }

        $svgs = [
            'wechat'   => '<path d="M8.69 4C5.03 4 2 6.57 2 9.72c0 1.77.98 3.36 2.52 4.41l-.71 2.14 2.36-1.18c.67.19 1.38.29 2.12.29.22 0 .44-.01.65-.03-.14-.44-.22-.9-.22-1.38 0-2.88 2.71-5.21 6.05-5.21h.44C14.64 6.24 11.93 4 8.69 4zM6.5 8.25a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5zm4.5 0a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5zM22 14.03c0-2.68-2.68-4.86-5.98-4.86s-5.97 2.18-5.97 4.86 2.67 4.86 5.97 4.86c.6 0 1.19-.08 1.74-.22l1.95.97-.58-1.76C20.57 17.1 22 15.67 22 14.03zm-8-.5a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5zm4 0a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5z"/>',
            'weibo'    => '<path d="M10.09 4c-4.01.08-7.24 2.65-7.23 5.85 0 .54.09 1.07.26 1.57C2.42 11.8 2 12.3 2 12.87c0 .94.93 1.7 2.08 1.7.18 0 .35-.02.51-.06C5.33 16.37 7.6 18 10.24 18c3.26 0 5.91-2.12 5.91-4.73 0-2.32-2.11-4.28-5.02-4.67.16-.39.25-.82.25-1.27 0-1.83-1.49-3.31-3.33-3.33zm0 1.33c1.1 0 2 .89 2 2 0 .38-.11.74-.3 1.04A5.48 5.48 0 0 0 10.24 8c-.23 0-.46.01-.68.04-.15-.21-.24-.47-.24-.74 0-.71.56-1.99.77-1.97zm.15 5.34c2.45.07 4.43 1.68 4.43 3.6C14.67 16.2 12.68 18 10.24 18c-2.44 0-4.42-1.8-4.42-4.01 0-1.99 1.86-3.38 4.42-3.32zM17 7a2 2 0 0 0-2 2 2 2 0 0 0 2 2 2 2 0 0 0 2-2 2 2 0 0 0-2-2zm-6.5 4.5c-1.93 0-3.5 1.12-3.5 2.5S8.57 16.5 10.5 16.5 14 15.38 14 14s-1.57-2.5-3.5-2.5zm0 1c1.38 0 2.5.67 2.5 1.5S11.88 15.5 10.5 15.5 8 14.83 8 14s1.12-1.5 2.5-1.5z"/>',
            'x'        => '<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.748l7.73-8.835L2.044 2.25h6.292l4.266 5.638 5.642-5.638zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>',
            'facebook' => '<path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>',
            'instagram'=> '<path d="M12 0C8.74 0 8.333.015 7.053.072 5.775.132 4.905.333 4.14.63c-.789.306-1.459.717-2.126 1.384S.935 3.35.63 4.14C.333 4.905.131 5.775.072 7.053.012 8.333 0 8.74 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 2.16c3.203 0 3.585.016 4.85.071 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/>',
            'youtube'  => '<path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>',
            'github'   => '<path d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0 1 12 6.844a9.59 9.59 0 0 1 2.504.337c1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.02 10.02 0 0 0 22 12.017C22 6.484 17.522 2 12 2z"/>',
            'steam'    => '<path d="M11.979 0C5.678 0 .511 4.86.022 11.037l6.432 2.658c.545-.371 1.203-.59 1.912-.59.063 0 .125.004.188.006l2.861-4.142V8.91c0-2.495 2.028-4.524 4.524-4.524 2.494 0 4.524 2.029 4.524 4.524s-2.03 4.525-4.524 4.525h-.105l-4.076 2.911c0 .052.004.105.004.159 0 1.875-1.515 3.396-3.39 3.396-1.635 0-3.016-1.173-3.331-2.711L.436 15.27C1.862 20.307 6.486 24 11.979 24c6.627 0 11.999-5.373 11.999-12S18.605 0 11.979 0zM7.54 18.21l-1.473-.61c.262.543.714.999 1.314 1.25 1.297.539 2.793-.076 3.332-1.375.263-.63.264-1.319.005-1.949s-.75-1.121-1.377-1.383c-.624-.26-1.29-.249-1.878-.03l1.523.63c.956.4 1.409 1.497 1.01 2.452-.397.957-1.494 1.41-2.456 1.015zm11.415-9.303c0-1.662-1.353-3.015-3.015-3.015-1.665 0-3.015 1.353-3.015 3.015 0 1.665 1.35 3.015 3.015 3.015 1.662 0 3.015-1.35 3.015-3.015zm-5.273-.005c0-1.252 1.013-2.266 2.265-2.266 1.249 0 2.266 1.014 2.266 2.266 0 1.251-1.017 2.265-2.266 2.265-1.252 0-2.265-1.014-2.265-2.265z"/>',
            'tiktok'   => '<path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.29 6.29 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V9.38a8.16 8.16 0 0 0 4.77 1.52V7.45a4.85 4.85 0 0 1-1-.76z"/>',
            'douyin'   => '<path d="M16.6 5.82s.51.5 0 0A4.278 4.278 0 0 1 15.54 3h-3.09v12.4a2.592 2.592 0 0 1-2.59 2.5 2.592 2.592 0 0 1-2.59-2.59 2.592 2.592 0 0 1 2.59-2.59c.28 0 .54.04.79.1V9.64a6.13 6.13 0 0 0-.79-.05 5.73 5.73 0 0 0-5.73 5.73 5.73 5.73 0 0 0 5.73 5.73 5.73 5.73 0 0 0 5.73-5.73V8.91A7.315 7.315 0 0 0 19.4 10V6.94a4.315 4.315 0 0 1-2.8-1.12z"/>',
            'bilibili' => '<path d="M17.813 4.653h.854c1.51.054 2.769.578 3.773 1.574 1.004.995 1.524 2.249 1.56 3.76v7.36c-.036 1.51-.556 2.769-1.56 3.773s-2.262 1.524-3.773 1.56H5.333c-1.51-.036-2.769-.556-3.773-1.56S.036 18.858 0 17.347v-7.36c.036-1.511.556-2.765 1.56-3.76 1.004-.996 2.262-1.52 3.773-1.574h.774l-1.174-1.12a1.234 1.234 0 0 1-.373-.906c0-.356.124-.658.373-.907l.027-.027c.267-.249.573-.373.92-.373.347 0 .653.124.92.373L9.653 4.44c.071.071.134.142.187.213h4.267a.836.836 0 0 1 .16-.213l2.853-2.747c.267-.249.573-.373.92-.373.347 0 .662.151.929.4.267.249.391.551.391.907 0 .355-.124.657-.373.906zM5.333 7.24c-.746.018-1.373.276-1.88.773-.506.498-.769 1.13-.786 1.894v7.52c.017.764.28 1.395.786 1.893.507.498 1.134.756 1.88.773h13.334c.746-.017 1.373-.275 1.88-.773.506-.498.769-1.129.786-1.893v-7.52c-.017-.765-.28-1.396-.786-1.894-.507-.497-1.134-.755-1.88-.773zM8 11.107c.373 0 .684.124.933.373.25.249.383.569.4.96v1.173c-.017.391-.15.711-.4.96-.249.25-.56.374-.933.374s-.684-.125-.933-.374c-.25-.249-.383-.569-.4-.96V12.44c0-.373.129-.689.386-.947.258-.257.574-.386.947-.386zm8 0c.373 0 .684.124.933.373.25.249.383.569.4.96v1.173c-.017.391-.15.711-.4.96-.249.25-.56.374-.933.374s-.684-.125-.933-.374c-.25-.249-.383-.569-.4-.96V12.44c.017-.391.15-.711.4-.96.249-.249.56-.373.933-.373z"/>',
            'telegram' => '<path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>',
            'discord'  => '<path d="M20.317 4.492c-1.53-.69-3.17-1.2-4.885-1.49a.075.075 0 0 0-.079.036c-.21.369-.444.85-.608 1.23a18.566 18.566 0 0 0-5.487 0 12.36 12.36 0 0 0-.617-1.23A.077.077 0 0 0 8.562 3c-1.714.29-3.354.8-4.885 1.491a.07.07 0 0 0-.032.027C.533 9.093-.32 13.555.099 17.961a.08.08 0 0 0 .031.055 20.03 20.03 0 0 0 5.993 2.98.078.078 0 0 0 .084-.026c.462-.62.874-1.275 1.226-1.963.021-.04.001-.088-.041-.104a13.201 13.201 0 0 1-1.872-.878.075.075 0 0 1-.008-.125c.126-.093.252-.19.372-.287a.075.075 0 0 1 .078-.01c3.927 1.764 8.18 1.764 12.061 0a.075.075 0 0 1 .079.009c.12.098.245.195.372.288a.075.075 0 0 1-.006.125c-.598.344-1.22.635-1.873.877a.075.075 0 0 0-.041.105c.36.687.772 1.341 1.225 1.962a.077.077 0 0 0 .084.028 19.963 19.963 0 0 0 6.002-2.981.076.076 0 0 0 .032-.054c.5-5.094-.838-9.52-3.549-13.442a.06.06 0 0 0-.031-.028zM8.02 15.278c-1.182 0-2.157-1.069-2.157-2.38 0-1.312.956-2.38 2.157-2.38 1.21 0 2.176 1.077 2.157 2.38 0 1.312-.956 2.38-2.157 2.38zm7.975 0c-1.183 0-2.157-1.069-2.157-2.38 0-1.312.955-2.38 2.157-2.38 1.21 0 2.176 1.077 2.157 2.38 0 1.312-.946 2.38-2.157 2.38z"/>',
            'line'     => '<path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.627-.63.349 0 .631.285.631.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.281.629-.629.629M24 10.314C24 4.943 18.615.5 12 .5S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/>',
        ];
        $path = $svgs[$platform] ?? '<circle cx="12" cy="12" r="10"/>';
        return "<svg width=\"{$s}\" height=\"{$s}\" viewBox=\"0 0 24 24\" fill=\"{$color}\" xmlns=\"http://www.w3.org/2000/svg\">{$path}</svg>";
    }
    ?>
</body>
</html>