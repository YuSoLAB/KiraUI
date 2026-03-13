<?php
/**
 * page.php — 自定义页面前端渲染器
 * 访问方式：page.php?slug=about 或 page.php?slug=privacy
 */

require_once __DIR__ . '/include/Db.php';
require_once __DIR__ . '/include/Config.php';

// 自动登录（若存在）
if (file_exists(__DIR__ . '/auto_login.php')) {
    require_once __DIR__ . '/auto_login.php';
    autoLogin();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$slug = isset($_GET['slug']) ? trim(preg_replace('/[^a-z0-9\-]/i', '', $_GET['slug'])) : '';

if (empty($slug)) {
    header('HTTP/1.0 404 Not Found');
    $errorMsg = '请求的页面不存在。';
    $page = null;
} else {
    try {
        $db   = Db::getInstance();
        $stmt = $db->prepare("SELECT * FROM site_pages WHERE slug=? AND status='published' LIMIT 1");
        $stmt->execute([$slug]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $page = null;
    }

    if (!$page) {
        header('HTTP/1.0 404 Not Found');
        $errorMsg = '页面「' . htmlspecialchars($slug) . '」不存在或已下线。';
    }
}

// 站点配置
$config   = Config::getInstance();
$htmlTitle = $config->get('html_title', 'YuSoLAB');
$pageTitle = $page ? htmlspecialchars($page['title']) : '404 — 页面未找到';
$metaDesc  = $page ? htmlspecialchars($page['meta_description'] ?? '') : '';

// ── 读取导航菜单（同 index.php）──────────────────────────────
function getNavMenus(PDO $db): array {
    try {
        $stmt = $db->query(
            "SELECT * FROM nav_menus WHERE is_active=1 ORDER BY COALESCE(parent_id,0) ASC, sort_order ASC, id ASC"
        );
        $flat = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $map  = [];
        $tree = [];
        foreach ($flat as $item) { $item['children'] = []; $map[$item['id']] = $item; }
        foreach ($map as $id => &$item) {
            if ($item['parent_id'] && isset($map[$item['parent_id']])) {
                $map[$item['parent_id']]['children'][] = &$item;
            } else {
                $tree[] = &$item;
            }
        }
        unset($item);
        return $tree;
    } catch (Exception $e) {
        return [];
    }
}

$navMenus = [];
try {
    $db = Db::getInstance();
    $navMenus = getNavMenus($db);
} catch (Exception $e) {}

// 如果没有配置菜单，使用默认项
if (empty($navMenus)) {
    $navMenus = [
        ['label'=>'首页','url'=>'index.php','children'=>[],'open_new_tab'=>0,'icon'=>''],
        ['label'=>'关于','url'=>'#','children'=>[],'open_new_tab'=>0,'icon'=>''],
    ];
}

$imgDir = __DIR__ . '/img/';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> — <?php echo htmlspecialchars($htmlTitle); ?></title>
    <?php if ($metaDesc): ?>
    <meta name="description" content="<?php echo $metaDesc; ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="style.css">
    <style>
        /* 自定义页面专属样式 */
        .page-hero {
            padding: 3rem 0 2rem;
            text-align: center;
        }
        .page-hero h1 {
            font-size: 2rem;
            margin: 0 0 .5rem;
        }
        .page-hero .page-meta {
            color: var(--sub, #888);
            font-size: .88rem;
        }
        .page-body {
            background: var(--card, #fff);
            border-radius: 12px;
            padding: 2rem 2.5rem;
            box-shadow: 0 2px 16px rgba(0,0,0,.06);
            line-height: 1.9;
            font-size: 1rem;
        }
        .page-body h1, .page-body h2, .page-body h3 {
            margin-top: 1.8rem;
            margin-bottom: .6rem;
        }
        .page-body p { margin: .8rem 0; }
        .page-body ul, .page-body ol { padding-left: 1.8rem; margin: .8rem 0; }
        .page-body a { color: var(--accent, #6c63ff); }
        .page-body img { max-width: 100%; border-radius: 8px; }
        .page-body hr { border: none; border-top: 1px solid var(--border, #eee); margin: 1.5rem 0; }
        .page-body blockquote {
            border-left: 3px solid var(--accent, #6c63ff);
            margin: 1rem 0;
            padding: .5rem 1rem;
            background: var(--tip-bg, #f7f5ff);
            border-radius: 0 6px 6px 0;
            color: var(--sub, #555);
        }
        .page-404 {
            text-align: center;
            padding: 4rem 1rem;
        }
        .page-404 .err-code {
            font-size: 5rem;
            font-weight: 800;
            color: var(--accent, #6c63ff);
            opacity: .3;
            line-height: 1;
        }
        .page-404 h2 { margin: 1rem 0 .5rem; }
        .page-404 p { color: var(--sub, #888); }
        .back-nav {
            margin-bottom: 1rem;
        }
        .back-nav a {
            color: var(--sub, #888);
            text-decoration: none;
            font-size: .9rem;
        }
        .back-nav a:hover { color: var(--accent, #6c63ff); }

        /* ── 夜间模式适配 ── */
        body.dark-mode .page-body {
            background: rgba(42, 42, 66, .92);
            box-shadow: 0 2px 20px rgba(0, 0, 0, .35);
            color: #eaeaf8;
        }
        body.dark-mode .page-body h1,
        body.dark-mode .page-body h2,
        body.dark-mode .page-body h3,
        body.dark-mode .page-body h4,
        body.dark-mode .page-body h5,
        body.dark-mode .page-body h6 {
            color: #eaeaf8;
        }
        body.dark-mode .page-body p,
        body.dark-mode .page-body li {
            color: #c8c8e0;
        }
        body.dark-mode .page-body a {
            color: #b096ff;
        }
        body.dark-mode .page-body a:hover {
            color: #d0bcff;
        }
        body.dark-mode .page-body hr {
            border-top-color: rgba(176, 160, 255, .18);
        }
        body.dark-mode .page-body blockquote {
            border-left-color: #b096ff;
            background: rgba(108, 93, 251, .12);
            color: #b0b0c8;
        }
        body.dark-mode .page-body img {
            opacity: .9;
        }
        body.dark-mode .page-body code {
            background: rgba(176, 160, 255, .12);
            color: #d0bcff;
        }
        body.dark-mode .page-body pre {
            background: rgba(15, 15, 30, .6);
            border: 1px solid rgba(176, 160, 255, .15);
        }
        body.dark-mode .page-body table th {
            background: rgba(108, 93, 251, .18);
            color: #eaeaf8;
        }
        body.dark-mode .page-body table td {
            border-color: rgba(176, 160, 255, .12);
            color: #c8c8e0;
        }
        body.dark-mode .page-body table tr:nth-child(even) {
            background: rgba(42, 42, 66, .5);
        }
        body.dark-mode .page-hero h1 {
            color: #eaeaf8;
        }
        body.dark-mode .page-hero .page-meta {
            color: #b0b0c8;
        }
        body.dark-mode .back-nav a {
            color: #b0b0c8;
        }
        body.dark-mode .back-nav a:hover {
            color: #b096ff;
        }
        body.dark-mode .page-404 .err-code {
            color: #b096ff;
        }
        body.dark-mode .page-404 h2 {
            color: #eaeaf8;
        }
        body.dark-mode .page-404 p {
            color: #b0b0c8;
        }
    </style>
</head>
<body>
    <!-- ── 导航栏（与 index.php 完全一致）────────────────── -->
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
                       class="nav-link <?php echo (strpos($item['url'], 'slug=' . $slug) !== false) ? 'active' : ''; ?>"
                       <?php echo $item['open_new_tab'] ? 'target="_blank" rel="noopener"' : ''; ?>>
                        <?php if (!empty($item['icon'])): ?><span class="nav-icon"><?php echo htmlspecialchars($item['icon']); ?></span><?php endif; ?>
                        <?php echo htmlspecialchars($item['label']); ?>
                        <?php if (!empty($item['children'])): ?><span class="dropdown-arrow">▾</span><?php endif; ?>
                    </a>
                    <?php if (!empty($item['children'])): ?>
                    <ul class="dropdown-menu">
                        <?php foreach ($item['children'] as $child): ?>
                        <li>
                            <a href="<?php echo htmlspecialchars($child['url']); ?>"
                               class="nav-link"
                               <?php echo $child['open_new_tab'] ? 'target="_blank" rel="noopener"' : ''; ?>>
                                <?php if (!empty($child['icon'])): ?><span class="nav-icon"><?php echo htmlspecialchars($child['icon']); ?></span><?php endif; ?>
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
        <main class="main-content" role="main" style="max-width:820px;">

            <?php if ($page): ?>

            <div class="back-nav">
                <a href="javascript:history.back()">← 返回</a>
            </div>

            <div class="page-hero">
                <h1><?php echo htmlspecialchars($page['title']); ?></h1>
                <?php if ($page['updated_at']): ?>
                <p class="page-meta">最后更新：<?php echo date('Y年m月d日', strtotime($page['updated_at'])); ?></p>
                <?php endif; ?>
            </div>

            <div class="page-body">
                <?php echo $page['content']; // 管理员输入，信任内容 ?>
            </div>

            <?php else: ?>

            <div class="page-404">
                <div class="err-code">404</div>
                <h2>页面未找到</h2>
                <p><?php echo $errorMsg ?? '请求的页面不存在。'; ?></p>
                <a href="index.php" class="btn btn-primary" style="margin-top:1.5rem;display:inline-block;">返回首页</a>
            </div>

            <?php endif; ?>

        </main>
    </div>

    <script>
        // 导航汉堡菜单
        const navToggle = document.getElementById('navToggle');
        const navMenu   = document.querySelector('.nav-menu');
        if (navToggle) {
            navToggle.addEventListener('click', () => {
                navMenu.classList.toggle('active');
                navToggle.classList.toggle('active');
            });
        }
        // 下拉菜单（移动端点击展开）
        document.querySelectorAll('.has-dropdown > .nav-link').forEach(link => {
            link.addEventListener('click', e => {
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    link.closest('li').classList.toggle('open');
                }
            });
        });
        // Sparkles
        (function(){
            var box = document.getElementById('sparkles');
            for (var i=0;i<40;i++){
                var s = document.createElement('i');
                var size = 6 + Math.random()*10;
                s.style.cssText = 'width:'+size+'px;height:'+size+'px;left:'+(Math.random()*100)+'vw;top:'+(Math.random()*100)+'vh;animationDuration:'+(10+Math.random()*12)+'s;animationDelay:'+(Math.random()*-20)+'s;opacity:'+(0.4+Math.random()*0.6);
                box.appendChild(s);
            }
        })();
        // 主题切换
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggle = document.getElementById('themeToggle');
            if (localStorage.getItem('theme') === 'dark' ||
                (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.body.classList.add('dark-mode');
                themeToggle.textContent = '☀️';
            }
            themeToggle.addEventListener('click', function() {
                document.body.classList.toggle('dark-mode');
                const isDark = document.body.classList.contains('dark-mode');
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
                themeToggle.textContent = isDark ? '☀️' : '🌙';
            });
        });
    </script>
    <?php include 'include/footer.php'; ?>
</body>
</html>