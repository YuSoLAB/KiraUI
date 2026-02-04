<?php
// 初始化检查
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(dirname(__FILE__)));
}
$dbConfigPath = ROOT_DIR . '/include/Db.php';
$dbConfigDistPath = $dbConfigPath . '.dist';
$configPath = ROOT_DIR . '/include/Config.php';
$isInitialized = false;
// 检查数据库初始化
if (file_exists($dbConfigPath) && file_exists($dbConfigDistPath)) {
    $fileContentDiff = file_get_contents($dbConfigPath) !== file_get_contents($dbConfigDistPath);
    $dbInitialized = false;
    try {
        require_once $dbConfigPath;
        if (file_exists($configPath)) {
            require_once $configPath;
        }
        $db = Db::getInstance();
        $stmt = $db->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $stmt->execute();
        $dbInitialized = $stmt->fetch() !== false;
    } catch (Exception $e) {
        $dbInitialized = false;
    }
    $isInitialized = $fileContentDiff || $dbInitialized;
}
$currentPage = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$initErrors = [];
$initSuccess = false;
if (!$isInitialized && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['init_submit'])) {
    if (empty($_POST['admin_email'])) $initErrors[] = '管理员邮箱不能为空';
    if (empty($_POST['admin_password'])) $initErrors[] = '管理员密码不能为空';
    if ($_POST['admin_password'] !== $_POST['admin_password_confirm']) $initErrors[] = '两次密码不一致';
    if (empty($_POST['db_host'])) $initErrors[] = '数据库主机不能为空';
    if (empty($_POST['db_user'])) $initErrors[] = '数据库用户名不能为空';
    if (empty($_POST['db_name'])) $initErrors[] = '数据库名不能为空';
    if (empty($initErrors)) {
        try {
            $dbDsn = "mysql:host={$_POST['db_host']};charset={$_POST['db_charset']}";
            $db = new PDO(
                $dbDsn,
                $_POST['db_user'],
                $_POST['db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $db->exec("CREATE DATABASE IF NOT EXISTS `{$_POST['db_name']}` CHARACTER SET {$_POST['db_charset']} COLLATE utf8mb4_0900_ai_ci");
            $db->exec("USE `{$_POST['db_name']}`");
            $sqlContent = file_get_contents(ROOT_DIR . '/yusolab.sql');
            $db->exec($sqlContent);
            $passwordHash = password_hash($_POST['admin_password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
            $stmt->execute(['admin', $_POST['admin_email'], $passwordHash]);
            $dbConfigContent = file_get_contents($dbConfigPath . '.dist');
            $dbConfigContent = str_replace("'localhost'", "'{$_POST['db_host']}'", $dbConfigContent);
            $dbConfigContent = str_replace("'yusolab'", "'{$_POST['db_name']}'", $dbConfigContent);
            $dbConfigContent = str_replace("'root'", "'{$_POST['db_user']}'", $dbConfigContent);
            $dbConfigContent = str_replace("''", "'{$_POST['db_pass']}'", $dbConfigContent);
            $dbConfigContent = str_replace("'utf8mb4'", "'{$_POST['db_charset']}'", $dbConfigContent);            
            if (!file_put_contents($dbConfigPath, $dbConfigContent)) {
                throw new Exception("无法写入数据库配置文件，请检查文件权限");
            }            
            $initSuccess = true;
            $_SESSION['admin_logged_in'] = true;
            $isInitialized = true;            
        } catch (PDOException $e) {
            $initErrors[] = "数据库错误: " . $e->getMessage();
        } catch (Exception $e) {
            $initErrors[] = "配置失败: " . $e->getMessage();
        }
    }
}
require_once ROOT_DIR . '/cache/FileCache.php';
if ($isInitialized) {
    require_once ROOT_DIR . '/cache/ArticleIndex.php';
    require_once ROOT_DIR . '/include/Db.php';
}
define('ARTICLES_DIR', ROOT_DIR . '/articles/');
$cache = new FileCache(ROOT_DIR . '/cache/data');
$articleIndex = $isInitialized ? new ArticleIndex() : null;
$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$loginError = '';
if ($isInitialized && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';    
    if (empty($email) || empty($password)) {
        $loginError = '邮箱和密码不能为空';
    } else {
        try {
            $db = Db::getInstance();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();          
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user'] = $user;
                $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);                
                $isLoggedIn = true;
            } else {
                $loginError = '邮箱或密码错误';
            }
        } catch (PDOException $e) {
            $loginError = '数据库错误: ' . $e->getMessage();
        }
    }
}
// 评论目录配置(弃用)
if (!defined('COMMENTS_DIR')) {
    define('COMMENTS_DIR', ROOT_DIR . '/cache/comments/');
}
// 确保评论目录存在(弃用)
if (!file_exists(COMMENTS_DIR)) {
    mkdir(COMMENTS_DIR, 0755, true);
}
define('COMMENT_SETTINGS_FILE', ROOT_DIR . '/cache/comment_settings.php');
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit;
}
$currentPage = $_GET['page'] ?? 'siteinfo';
if (in_array($currentPage, ['edit_article', 'edit_draft']) && !isset($_GET['edit'])) {
    $currentPage = 'articles';
}
// 处理登录后操作
if ($isLoggedIn) {
    require_once 'admin_functions.php';
    $message = '';
    // 处理选项卡切换
    $page = $_GET['page'] ?? 'cache';
    // 处理表单提交
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        require_once 'admin_actions.php';
    }
    $stats = $cache->getStats();
    $index_stats = [];
    if ($articleIndex !== null) {
        $index_stats = $articleIndex->getIndexStats();
    }
    $articles = [];
    if ($articleIndex !== null) {
        $index_stats = $articleIndex->getIndexStats();
        $articles = $articleIndex->getIndex();
    }
    $currentArticle = null;
    $isNewArticle = false;
    if ($currentPage === 'edit_article' && isset($_GET['edit'])) { 
        $editParam = $_GET['edit'];
        if ($editParam === 'new') {
            $isNewArticle = true;
            $currentArticle = [
                'id' => getNextArticleId(),
                'title' => '',
                'excerpt' => '',
                'date' => date('Y-m-d'),
                'tags' => [],
                'content' => '',
                'word_count' => 0,
                'read_time' => 0
            ];
        } else {
            $editId = intval($editParam);
            $currentArticle = loadArticleForEdit($editId);
            $isNewArticle = false;
        }
    }
    $drafts = getDrafts();
    $currentDraft = null;
    $isNewDraft = false;
    if (isset($_GET['page']) && $_GET['page'] === 'edit_draft') {
        $editParam = $_GET['edit'] ?? '';
        if ($editParam === 'new') {
            $isNewDraft = true;
            $currentDraft = [
                'id' => getNextArticleId(),
                'title' => '',
                'excerpt' => '',
                'date' => date('Y-m-d'),
                'tags' => [],
                'content' => '',
                'word_count' => 0,
                'read_time' => 0
            ];
        } else {
            $editId = intval($editParam);
            $currentDraft = loadDraftForEdit($editId);
            $isNewDraft = false;
        }
    }
    if (isset($_GET['saved']) && $_GET['saved'] == 1) {
        $message = isset($_GET['page']) && $_GET['page'] === 'edit_draft' ? 
            "草稿已保存成功！" : "文章已保存成功！";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $isInitialized ? '网站管理后台 - ' . htmlspecialchars(Config::getInstance()->get('html_title', 'YuSoLAB')) : '系统初始化 - ' . htmlspecialchars(Config::getInstance()->get('html_title', 'YuSoLAB')); ?></title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin_style.css">
    <?php if ($isInitialized && $isLoggedIn): ?>
    <?php endif; ?>
</head>
<body>
    <div class="container">
        <?php if (!$isInitialized): ?>
            <div class="init-form">
                <h2>系统初始化</h2>
                <?php if ($initSuccess): ?>
                    <div class="message success">
                        <p>初始化成功！正在加载管理后台...</p>
                    </div>
                    <script>setTimeout(function(){ window.location.reload(); }, 2000);</script>
                <?php else: ?>
                    <?php if (!empty($initErrors)): ?>
                        <div class="message error">
                            <?php foreach ($initErrors as $error): ?>
                                <p><?php echo $error; ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <form method="post">
                        <input type="hidden" name="init_submit" value="1">
                        <fieldset>
                            <legend>管理员信息</legend>
                            <div class="form-group">
                                <label for="admin_email">管理员邮箱</label>
                                <input type="email" id="admin_email" name="admin_email" required 
                                       value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="admin_password">管理员密码</label>
                                <input type="password" id="admin_password" name="admin_password" required>
                            </div>
                            <div class="form-group">
                                <label for="admin_password_confirm">确认密码</label>
                                <input type="password" id="admin_password_confirm" name="admin_password_confirm" required>
                            </div>
                        </fieldset>
                        <fieldset>
                            <legend>数据库配置</legend>
                            <div class="form-group">
                                <label for="db_host">数据库主机</label>
                                <input type="text" id="db_host" name="db_host" required 
                                       value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="db_user">数据库用户名</label>
                                <input type="text" id="db_user" name="db_user" required 
                                       value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'root'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="db_pass">数据库密码</label>
                                <input type="password" id="db_pass" name="db_pass" 
                                       value="<?php echo htmlspecialchars($_POST['db_pass'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="db_name">数据库名</label>
                                <input type="text" id="db_name" name="db_name" required 
                                       value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'yusolab'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="db_charset">数据库字符集</label>
                                <input type="text" id="db_charset" name="db_charset" required 
                                       value="<?php echo htmlspecialchars($_POST['db_charset'] ?? 'utf8mb4'); ?>"
                                       readonly>
                                <small>默认使用 utf8mb4，排序规则强制为 utf8mb4_0900_ai_ci</small>
                            </div>
                        </fieldset>
                        <button type="submit" class="btn btn-primary">完成初始化</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php elseif (!$isLoggedIn): ?>
            <?php include 'admin_login.php'; ?>
        <?php else: ?>
            <?php include 'admin_header.php'; ?>            
            <?php if ($message): ?>
                <div class="message"><?php echo $message; ?></div>
            <?php endif; ?>            
            <?php include 'admin_tabs.php'; ?>
            <div class="tab-contents">
                <div id="siteinfo-content" class="tab-pane <?php echo $currentPage === 'siteinfo' ? 'active' : ''; ?>">
                    <?php include 'admin_siteinfo.php'; ?>
                </div>
                <div id="cache-content" class="tab-pane <?php echo $currentPage === 'cache' ? 'active' : ''; ?>">
                    <?php include 'admin_cache.php'; ?>
                </div>
                <div id="articles-content" class="tab-pane <?php echo $currentPage === 'articles' ? 'active' : ''; ?>">
                    <?php include 'admin_articles.php'; ?>
                </div>
                <div id="drafts-content" class="tab-pane <?php echo $currentPage === 'drafts' ? 'active' : ''; ?>">
                    <?php include 'admin_drafts.php'; ?>
                </div>
                <?php if (isset($currentDraft)): ?>
                <div id="edit-draft-content" class="tab-pane <?php echo $currentPage === 'edit_draft' ? 'active' : ''; ?>">
                    <?php include 'admin_edit_draft.php'; ?>
                </div>
                <?php endif; ?>
                <?php if (isset($currentArticle)): ?>
                <div id="edit-article-content" class="tab-pane <?php echo $currentPage === 'edit_article' ? 'active' : ''; ?>">
                    <?php include 'admin_edit_article.php'; ?>
                </div>
                <?php endif; ?>
                <div id="footer-content" class="tab-pane <?php echo $currentPage === 'footer' ? 'active' : ''; ?>">
                    <?php include 'admin_footer.php'; ?>
                </div>
                <div id="announcement-content" class="tab-pane <?php echo $currentPage === 'announcement' ? 'active' : ''; ?>">
                    <?php include 'admin_announcement.php'; ?>
                </div>
                <div id="comments-content" class="tab-pane <?php echo $currentPage === 'comments' ? 'active' : ''; ?>">
                    <?php include 'admin_comments.php'; ?>
                </div>
                <div id="smtp-content" class="tab-pane <?php echo $currentPage === 'smtp' ? 'active' : ''; ?>">
                    <?php include 'admin_smtp.php'; ?>
                </div>
                <div id="users-content" class="tab-pane <?php echo $currentPage === 'users' ? 'active' : ''; ?>">
                    <?php include 'admin_users.php'; ?>
                </div>
                <div id="landing-content" class="tab-pane <?php echo $currentPage === 'landing' ? 'active' : ''; ?>">
                    <?php include 'admin_landing.php'; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script>
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
    <script src="admin_script.js"></script>
</body>
</html>