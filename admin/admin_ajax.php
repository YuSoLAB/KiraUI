<?php
/**
 * admin_ajax.php
 * 专用 AJAX 端点，在任何 HTML 输出之前处理请求并返回 JSON。
 * 由 admin_menus.php / admin_pages.php / admin_*.php 中的 JS fetch 调用。
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(dirname(__FILE__)));
}

// 安全检查：必须已登录
if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => '未登录或会话已过期']);
    exit;
}

require_once ROOT_DIR . '/include/Db.php';
require_once ROOT_DIR . '/include/Config.php';
header('Content-Type: application/json; charset=utf-8');

$type = $_POST['type'] ?? '';

try {
    $db = Db::getInstance();

    // ═══════════════════════════════════════════════════════
    // 菜单操作
    // ═══════════════════════════════════════════════════════
    if ($type === 'menu') {
        $act = $_POST['menu_action'] ?? '';

        if ($act === 'save_order') {
            $items = json_decode($_POST['items'] ?? '[]', true);
            if (!is_array($items)) { echo json_encode(['ok'=>false,'msg'=>'数据格式错误']); exit; }
            $stmt = $db->prepare("UPDATE nav_menus SET parent_id=?, sort_order=? WHERE id=?");
            foreach ($items as $it) {
                $pid = (isset($it['parent_id']) && $it['parent_id'] > 0) ? (int)$it['parent_id'] : null;
                $stmt->execute([$pid, (int)$it['sort_order'], (int)$it['id']]);
            }
            echo json_encode(['ok' => true]);
        }

        elseif ($act === 'add' || $act === 'edit') {
            $id    = intval($_POST['id'] ?? 0);
            $label = trim($_POST['label'] ?? '');
            $url   = trim($_POST['url'] ?? '#');
            $pid   = intval($_POST['parent_id'] ?? 0) ?: null;
            $tab   = intval($_POST['open_new_tab'] ?? 0);
            $icon  = trim($_POST['icon'] ?? '') ?: null;

            if ($label === '') { echo json_encode(['ok'=>false,'msg'=>'菜单名称不能为空']); exit; }

            if ($act === 'add') {
                $maxStmt = $db->query("SELECT COALESCE(MAX(sort_order),0) FROM nav_menus WHERE parent_id IS NULL");
                $maxOrd  = (int)$maxStmt->fetchColumn();
                $stmt = $db->prepare(
                    "INSERT INTO nav_menus (label,url,parent_id,sort_order,open_new_tab,icon) VALUES (?,?,?,?,?,?)"
                );
                $stmt->execute([$label, $url, $pid, $maxOrd + 10, $tab, $icon]);
                echo json_encode(['ok'=>true,'id'=>(int)$db->lastInsertId()]);
            } else {
                $stmt = $db->prepare(
                    "UPDATE nav_menus SET label=?,url=?,parent_id=?,open_new_tab=?,icon=? WHERE id=?"
                );
                $stmt->execute([$label, $url, $pid, $tab, $icon, $id]);
                echo json_encode(['ok'=>true]);
            }
        }

        elseif ($act === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            $db->prepare("UPDATE nav_menus SET parent_id=NULL WHERE parent_id=?")->execute([$id]);
            $db->prepare("DELETE FROM nav_menus WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true]);
        }

        elseif ($act === 'toggle') {
            $id = intval($_POST['id'] ?? 0);
            $db->prepare("UPDATE nav_menus SET is_active = 1 - is_active WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true]);
        }

        else { echo json_encode(['ok'=>false,'msg'=>'未知菜单操作']); }
    }

    // ═══════════════════════════════════════════════════════
    // 页面操作
    // ═══════════════════════════════════════════════════════
    elseif ($type === 'page') {
        $act = $_POST['page_action'] ?? '';

        if ($act === 'save') {
            $id      = intval($_POST['id'] ?? 0);
            $title   = trim($_POST['title'] ?? '');
            $slug    = trim($_POST['slug'] ?? '');
            $content = $_POST['content'] ?? '';
            $desc    = trim($_POST['meta_description'] ?? '');
            $status  = in_array($_POST['status'] ?? '', ['published','draft']) ? $_POST['status'] : 'published';

            if ($title === '') { echo json_encode(['ok'=>false,'msg'=>'页面标题不能为空']); exit; }

            if ($slug === '') {
                $slug = strtolower(preg_replace('/[^a-z0-9-]/i', '-', $title));
                $slug = preg_replace('/-+/', '-', trim($slug, '-')) ?: ('page-' . time());
            }
            $slug = strtolower(preg_replace('/-+/', '-', trim(preg_replace('/[^a-z0-9\-]/i', '-', $slug), '-')));

            if ($id === 0) {
                $check = $db->prepare("SELECT id FROM site_pages WHERE slug=?");
                $check->execute([$slug]);
                if ($check->fetch()) { echo json_encode(['ok'=>false,'msg'=>"Slug「{$slug}」已存在，请更换"]); exit; }
                $stmt = $db->prepare(
                    "INSERT INTO site_pages (title,slug,content,meta_description,status,created_by) VALUES (?,?,?,?,?,?)"
                );
                $stmt->execute([$title,$slug,$content,$desc,$status,$_SESSION['admin_user']['id']??null]);
                echo json_encode(['ok'=>true,'id'=>(int)$db->lastInsertId(),'slug'=>$slug]);
            } else {
                $check = $db->prepare("SELECT id FROM site_pages WHERE slug=? AND id!=?");
                $check->execute([$slug,$id]);
                if ($check->fetch()) { echo json_encode(['ok'=>false,'msg'=>"Slug「{$slug}」已被其他页面使用"]); exit; }
                $stmt = $db->prepare(
                    "UPDATE site_pages SET title=?,slug=?,content=?,meta_description=?,status=? WHERE id=?"
                );
                $stmt->execute([$title,$slug,$content,$desc,$status,$id]);
                echo json_encode(['ok'=>true,'slug'=>$slug]);
            }
        }

        elseif ($act === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            $ps = $db->prepare("SELECT slug FROM site_pages WHERE id=?");
            $ps->execute([$id]);
            $row = $ps->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $db->prepare("UPDATE nav_menus SET url='#' WHERE url LIKE ?")->execute(['%slug='.$row['slug'].'%']);
            }
            $db->prepare("DELETE FROM site_pages WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true]);
        }

        elseif ($act === 'get') {
            $id = intval($_POST['id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM site_pages WHERE id=?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['ok'=>(bool)$row,'data'=>$row?:null]);
        }

        else { echo json_encode(['ok'=>false,'msg'=>'未知页面操作']); }
    }

    // ═══════════════════════════════════════════════════════
    // 系统配置操作（公告 / 页脚 / 网站信息 / SMTP）
    // ═══════════════════════════════════════════════════════
    elseif ($type === 'config') {
        $act    = $_POST['config_action'] ?? '';
        $config = Config::getInstance();

        // ── 公告 ────────────────────────────────────────────
        if ($act === 'save_announcement') {
            $config->batchSet([
                'announcement_content'    => $_POST['announcement_content'] ?? '',
                'announcement_enabled'    => !empty($_POST['announcement_enabled']) ? '1' : '0',
                'announcement_updated_at' => (string)time(),
            ]);
            echo json_encode(['ok' => true, 'msg' => '公告配置已保存成功！']);
        }

        // ── 页脚 ────────────────────────────────────────────
        elseif ($act === 'save_footer') {
            $config->batchSet([
                'footer_content' => $_POST['footer_content'] ?? '',
                'footer_css'     => $_POST['footer_css']     ?? '',
                'footer_js'      => $_POST['footer_js']      ?? '',
            ]);
            echo json_encode(['ok' => true, 'msg' => '页脚配置已保存成功！']);
        }

        // ── 网站基本信息 ─────────────────────────────────────
        elseif ($act === 'save_siteinfo') {
            $config->batchSet([
                'badge_text'   => $_POST['badge_text']   ?? '',
                'site_title'   => $_POST['site_title']   ?? '',
                'welcome_text' => $_POST['welcome_text'] ?? '',
                'html_title'   => $_POST['html_title']   ?? '',
            ]);
            echo json_encode(['ok' => true, 'msg' => '网站信息已保存成功！']);
        }

        // ── 图片上传（multipart, 单独走 upload_image 子动作）─
        elseif ($act === 'upload_image') {
            $uploadDir = ROOT_DIR . '/img/';
            if (!file_exists($uploadDir)) { mkdir($uploadDir, 0755, true); }

            $messages = [];
            $errors   = [];

            // Logo (.ico)
            if (!empty($_FILES['logo']['name'])) {
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                if ($ext === 'ico') {
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . 'logo.ico')) {
                        $messages[] = 'Logo 上传成功！';
                    } else {
                        $errors[] = 'Logo 上传失败，请检查目录权限';
                    }
                } else {
                    $errors[] = 'Logo 必须是 .ico 格式';
                }
            }

            // Banner (png/jpg/jpeg/gif)
            if (!empty($_FILES['banner']['name'])) {
                $ext = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['png','jpg','jpeg','gif'], true)) {
                    $existing = glob($uploadDir . 'banner*.png') ?: [];
                    $maxNum   = 0;
                    foreach ($existing as $f) {
                        if (preg_match('/banner(\d+)\.png$/', basename($f), $m)) {
                            $maxNum = max($maxNum, (int)$m[1]);
                        }
                    }
                    $dest = $uploadDir . 'banner' . ($maxNum + 1) . '.png';
                    if (move_uploaded_file($_FILES['banner']['tmp_name'], $dest)) {
                        $messages[] = '背景图片上传成功！';
                    } else {
                        $errors[] = '背景图片上传失败，请检查目录权限';
                    }
                } else {
                    $errors[] = '背景图片必须是 png/jpg/jpeg/gif 格式';
                }
            }

            if (!empty($errors)) {
                echo json_encode(['ok' => false, 'msg' => implode(' ', $errors)]);
            } else {
                echo json_encode(['ok' => true, 'msg' => implode(' ', $messages) ?: '没有文件被上传']);
            }
        }

        // ── SMTP ─────────────────────────────────────────────
        elseif ($act === 'save_smtp') {
            // 密码留空时保留原有值
            $oldPwd    = $config->get('smtp_password', '');
            $newPwd    = $_POST['password'] ?? '';
            if ($newPwd === '') { $newPwd = $oldPwd; }

            $config->batchSet([
                'smtp_enabled'    => !empty($_POST['smtp_enabled'])    ? '1' : '0',
                'smtp_host'       => trim($_POST['host']       ?? ''),
                'smtp_port'       => trim($_POST['port']       ?? '587'),
                'smtp_username'   => trim($_POST['username']   ?? ''),
                'smtp_password'   => $newPwd,
                'smtp_from_email' => trim($_POST['from_email'] ?? ''),
                'smtp_from_name'  => trim($_POST['from_name']  ?? ''),
                'smtp_encryption' => $_POST['encryption']      ?? 'tls',
            ]);
            echo json_encode(['ok' => true, 'msg' => 'SMTP 配置已保存成功！']);
        }

        else {
            echo json_encode(['ok' => false, 'msg' => '未知配置操作']);
        }
    }

    // ═══════════════════════════════════════════════════════
    // 文章操作（含封面图保存 / 缓存清除）
    // ═══════════════════════════════════════════════════════
    elseif ($type === 'article') {
        $act = $_POST['article_action'] ?? '';

        if ($act === 'save') {
            $id          = intval($_POST['id'] ?? 0);
            $title       = trim($_POST['title'] ?? '');
            $excerpt     = trim($_POST['excerpt'] ?? '');
            $content     = $_POST['content'] ?? '';
            $date        = trim($_POST['date'] ?? date('Y-m-d'));
            $tags        = trim($_POST['tags'] ?? '');
            $cover_image = trim($_POST['cover_image'] ?? '');

            if ($title === '') { echo json_encode(['ok' => false, 'msg' => '文章标题不能为空']); exit; }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $date = date('Y-m-d'); }

            if ($id === 0) {
                $stmt = $db->prepare(
                    "INSERT INTO articles (title, excerpt, content, date, tags, cover_image, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"
                );
                $stmt->execute([$title, $excerpt, $content, $date, $tags, $cover_image]);
                $newId = (int)$db->lastInsertId();
                _clearArticleCache();
                echo json_encode(['ok' => true, 'id' => $newId, 'msg' => '文章发布成功！']);
            } else {
                $stmt = $db->prepare(
                    "UPDATE articles SET title=?, excerpt=?, content=?, date=?, tags=?, cover_image=?, updated_at=NOW() WHERE id=?"
                );
                $stmt->execute([$title, $excerpt, $content, $date, $tags, $cover_image, $id]);
                _clearArticleCache();
                echo json_encode(['ok' => true, 'msg' => '文章保存成功！']);
            }
        }

        elseif ($act === 'get') {
            $id   = intval($_POST['id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM articles WHERE id=?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => (bool)$row, 'data' => $row ?: null]);
        }

        else { echo json_encode(['ok' => false, 'msg' => '未知文章操作']); }
    }

    // ═══════════════════════════════════════════════════════
    // 草稿操作（含封面图保存）
    // ═══════════════════════════════════════════════════════
    elseif ($type === 'draft') {
        $act = $_POST['draft_action'] ?? '';

        if ($act === 'save') {
            $id          = intval($_POST['id'] ?? 0);
            $title       = trim($_POST['title'] ?? '');
            $excerpt     = trim($_POST['excerpt'] ?? '');
            $content     = $_POST['content'] ?? '';
            $date        = trim($_POST['date'] ?? date('Y-m-d'));
            $tags        = trim($_POST['tags'] ?? '');
            $cover_image = trim($_POST['cover_image'] ?? '');

            if ($title === '') { echo json_encode(['ok' => false, 'msg' => '草稿标题不能为空']); exit; }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $date = date('Y-m-d'); }

            if ($id === 0) {
                $stmt = $db->prepare(
                    "INSERT INTO drafts (title, excerpt, content, date, tags, cover_image, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"
                );
                $stmt->execute([$title, $excerpt, $content, $date, $tags, $cover_image]);
                $newId = (int)$db->lastInsertId();
                echo json_encode(['ok' => true, 'id' => $newId, 'msg' => '草稿保存成功！']);
            } else {
                $stmt = $db->prepare(
                    "UPDATE drafts SET title=?, excerpt=?, content=?, date=?, tags=?, cover_image=?, updated_at=NOW() WHERE id=?"
                );
                $stmt->execute([$title, $excerpt, $content, $date, $tags, $cover_image, $id]);
                echo json_encode(['ok' => true, 'msg' => '草稿保存成功！']);
            }
        }

        elseif ($act === 'publish') {
            // 将草稿发布为正式文章（含封面图）
            $id = intval($_POST['id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM drafts WHERE id=?");
            $stmt->execute([$id]);
            $draft = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$draft) { echo json_encode(['ok' => false, 'msg' => '草稿不存在']); exit; }

            $ins = $db->prepare(
                "INSERT INTO articles (title, excerpt, content, date, tags, cover_image, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"
            );
            $ins->execute([
                $draft['title'], $draft['excerpt'], $draft['content'],
                $draft['date'],  $draft['tags'],    $draft['cover_image'] ?? '',
            ]);
            $newId = (int)$db->lastInsertId();
            $db->prepare("DELETE FROM drafts WHERE id=?")->execute([$id]);
            _clearArticleCache();
            echo json_encode(['ok' => true, 'id' => $newId, 'msg' => '草稿已发布为正式文章！']);
        }

        else { echo json_encode(['ok' => false, 'msg' => '未知草稿操作']); }
    }


    // ═══════════════════════════════════════════════════════
    // 邮件通知操作（email_notify）
    // ═══════════════════════════════════════════════════════
    elseif ($type === 'email_notify') {
        $act = $_POST['action'] ?? '';

        // ── 发送单封邮件 ──────────────────────────────────────
        if ($act === 'send_one') {
            $toEmail  = trim($_POST['to_email']  ?? '');
            $toName   = trim($_POST['to_name']   ?? '');
            $subject  = trim($_POST['subject']   ?? '');
            $htmlBody = $_POST['html']            ?? '';
            $fromName = trim($_POST['from_name'] ?? '');
            $replyTo  = trim($_POST['reply_to']  ?? '');

            if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['ok' => false, 'msg' => '无效邮箱地址: ' . $toEmail]);
                exit;
            }
            if ($subject === '') {
                echo json_encode(['ok' => false, 'msg' => '邮件主题不能为空']);
                exit;
            }

            // 读取 SMTP 配置
            $cfg = Config::getInstance();
            if ($cfg->get('smtp_enabled', '0') !== '1') {
                echo json_encode(['ok' => false, 'msg' => 'SMTP 未启用']);
                exit;
            }

            $smtpHost   = $cfg->get('smtp_host',       '');
            $smtpPort   = (int)$cfg->get('smtp_port',  587);
            $smtpUser   = $cfg->get('smtp_username',   '');
            $smtpPass   = $cfg->get('smtp_password',   '');
            $smtpFrom   = $cfg->get('smtp_from_email', $smtpUser);
            $smtpFName  = $fromName ?: $cfg->get('smtp_from_name', '');
            $smtpEnc    = $cfg->get('smtp_encryption', 'tls');

            if ($smtpHost === '') {
                echo json_encode(['ok' => false, 'msg' => 'SMTP 服务器未配置']);
                exit;
            }

            // ── PHPMailer（若已安装）或原生 SMTP socket ──────
            // 优先使用 PHPMailer；若不存在则回退到原生 mail()
            $phpmailerPath = ROOT_DIR . '/vendor/autoload.php';
            $usePHPMailer  = file_exists($phpmailerPath);

            if ($usePHPMailer) {
                require_once $phpmailerPath;
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host        = $smtpHost;
                    $mail->SMTPAuth    = true;
                    $mail->Username    = $smtpUser;
                    $mail->Password    = $smtpPass;
                    $mail->SMTPSecure  = $smtpEnc === 'ssl'
                        ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                        : ($smtpEnc === 'tls' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : '');
                    $mail->Port        = $smtpPort;
                    $mail->CharSet     = 'UTF-8';
                    $mail->setFrom($smtpFrom, $smtpFName);
                    $mail->addAddress($toEmail, $toName);
                    if ($replyTo !== '') { $mail->addReplyTo($replyTo); }
                    $mail->isHTML(true);
                    $mail->Subject     = $subject;
                    $mail->Body        = $htmlBody;
                    $mail->AltBody     = strip_tags($htmlBody);
                    $mail->send();
                    echo json_encode(['ok' => true]);
                } catch (\PHPMailer\PHPMailer\Exception $e) {
                    echo json_encode(['ok' => false, 'msg' => $mail->ErrorInfo]);
                }
            } else {
                // ── 原生 SMTP（socket 方式）──────────────────
                // 简洁实现：通过 fsockopen 手工建立 SMTP 会话
                $result = _enSmtpSend([
                    'host'      => $smtpHost,
                    'port'      => $smtpPort,
                    'user'      => $smtpUser,
                    'pass'      => $smtpPass,
                    'enc'       => $smtpEnc,
                    'from'      => $smtpFrom,
                    'from_name' => $smtpFName,
                    'to'        => $toEmail,
                    'to_name'   => $toName,
                    'reply_to'  => $replyTo,
                    'subject'   => $subject,
                    'html'      => $htmlBody,
                ]);
                echo json_encode($result);
            }
        }

        // ── 保存发送历史 ──────────────────────────────────────
        elseif ($act === 'save_history') {
            $cfg     = Config::getInstance();
            $history = json_decode($cfg->get('email_notify_history', '[]'), true) ?: [];

            array_unshift($history, [
                'subject' => trim($_POST['subject'] ?? ''),
                'ok'      => (int)($_POST['ok']   ?? 0),
                'fail'    => (int)($_POST['fail']  ?? 0),
                'time'    => date('Y-m-d H:i'),
            ]);
            $history = array_slice($history, 0, 50);

            $cfg->set('email_notify_history', json_encode($history, JSON_UNESCAPED_UNICODE));
            echo json_encode(['ok' => true, 'history' => $history]);
        }

        else {
            echo json_encode(['ok' => false, 'msg' => '未知邮件通知操作']);
        }
    }
    // ═══════════════════════════════════════════════════════
    // 缓存 & 索引操作（cache）
    // ═══════════════════════════════════════════════════════
    elseif ($type === 'cache') {
        error_log('[cache-debug] ROOT_DIR=' . ROOT_DIR . ' | __FILE__=' . __FILE__);
        require_once ROOT_DIR . '/cache/FileCache.php';
        require_once ROOT_DIR . '/cache/ArticleIndex.php';

        $cache        = new FileCache();
        $articleIndex = new ArticleIndex();
        $act          = $_POST['cache_action'] ?? '';

        if ($act === 'clear_all') {
            $cache->clear();
            echo json_encode(['ok' => true, 'msg' => '所有缓存已清空！']);

        } elseif ($act === 'clear_expired') {
            $cache->clearExpired();
            echo json_encode(['ok' => true, 'msg' => '过期缓存已清理！']);

        } elseif ($act === 'rebuild_index') {
            $result = $articleIndex->buildIndex();
            if ($result !== false) {
                echo json_encode(['ok' => true, 'msg' => '文章索引已重建！', 'count' => count($result)]);
            } else {
                echo json_encode(['ok' => false, 'msg' => '索引重建失败，请查看错误日志']);
            }

        } elseif ($act === 'clear_index') {
            $articleIndex->clearIndex();
            echo json_encode(['ok' => true, 'msg' => '文章索引已清空！']);

        } else {
            echo json_encode(['ok' => false, 'msg' => '未知缓存操作']);
        }
    }

    else {
        echo json_encode(['ok'=>false,'msg'=>'未知请求类型']);
    }

} catch (Exception $e) {
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
exit;

/**
 * 清除文章列表缓存，确保封面图等修改立即反映到首页。
 * 兼容 ArticleIndex JSON 缓存 + FileCache 文件缓存两套机制。
 */
function _clearArticleCache(): void {
    if (!defined('ROOT_DIR')) { return; }
    $cacheDir = ROOT_DIR . '/cache/';

    // ArticleIndex 常见缓存文件名
    $targets = [
        $cacheDir . 'article_index.json',
        $cacheDir . 'article_index.php',
        $cacheDir . 'articles_index.json',
    ];
    foreach ($targets as $f) {
        if (file_exists($f)) { @unlink($f); }
    }

    // FileCache 通配清除（all_articles* / article_*）
    if (is_dir($cacheDir)) {
        foreach (array_merge(
            glob($cacheDir . 'all_articles*') ?: [],
            glob($cacheDir . 'article_*')     ?: []
        ) as $f) {
            @unlink($f);
        }
    }
}

/**
 * _enSmtpSend — 原生 socket SMTP 发件（无需 PHPMailer）
 * 支持 TLS (STARTTLS) / SSL / 无加密三种模式。
 * 返回 ['ok'=>bool, 'msg'=>string]
 */
function _enSmtpSend(array $p): array {
    $timeout = 15;
    $port    = (int)$p['port'];
    $host    = $p['host'];
    $enc     = $p['enc'] ?? 'tls';

    // 建立连接
    $errno = 0; $errstr = '';
    if ($enc === 'ssl') {
        $sock = @fsockopen("ssl://{$host}", $port, $errno, $errstr, $timeout);
    } else {
        $sock = @fsockopen($host, $port, $errno, $errstr, $timeout);
    }
    if (!$sock) {
        return ['ok' => false, 'msg' => "连接 SMTP 失败（{$errno}）：{$errstr}"];
    }
    stream_set_timeout($sock, $timeout);

    $recv = function() use ($sock) {
        $resp = '';
        while (!feof($sock)) {
            $line = fgets($sock, 1024);
            $resp .= $line;
            if (isset($line[3]) && $line[3] === ' ') break; // 最后一行
        }
        return $resp;
    };
    $send = function(string $cmd) use ($sock) { fwrite($sock, $cmd . "\r\n"); };

    $recv(); // 220 greeting
    $send("EHLO {$host}");
    $ehlo = $recv();

    // STARTTLS 升级
    if ($enc === 'tls') {
        if (strpos($ehlo, 'STARTTLS') === false) {
            fclose($sock);
            return ['ok' => false, 'msg' => '服务器不支持 STARTTLS'];
        }
        $send('STARTTLS');
        $recv();
        if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($sock);
            return ['ok' => false, 'msg' => 'STARTTLS 握手失败'];
        }
        $send("EHLO {$host}");
        $recv();
    }

    // 登录认证
    $send('AUTH LOGIN');
    $recv();
    $send(base64_encode($p['user']));
    $recv();
    $send(base64_encode($p['pass']));
    $authResp = $recv();
    if (strpos($authResp, '235') === false) {
        fclose($sock);
        return ['ok' => false, 'msg' => 'SMTP 认证失败：' . trim($authResp)];
    }

    // 构建邮件
    $boundary = 'enBnd_' . md5(uniqid());
    $fromEnc  = '=?UTF-8?B?' . base64_encode($p['from_name']) . '?=';
    $toEnc    = '=?UTF-8?B?' . base64_encode($p['to_name'])   . '?=';
    $subjEnc  = '=?UTF-8?B?' . base64_encode($p['subject'])   . '?=';

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "From: {$fromEnc} <{$p['from']}>\r\n";
    $headers .= "To: {$toEnc} <{$p['to']}>\r\n";
    if (!empty($p['reply_to'])) { $headers .= "Reply-To: {$p['reply_to']}\r\n"; }
    $headers .= "Subject: {$subjEnc}\r\n";
    $headers .= "Date: " . date('r') . "\r\n";

    $plain   = strip_tags($p['html']);
    $body    = "--{$boundary}\r\n";
    $body   .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $body   .= chunk_split(base64_encode($plain)) . "\r\n";
    $body   .= "--{$boundary}\r\n";
    $body   .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $body   .= chunk_split(base64_encode($p['html'])) . "\r\n";
    $body   .= "--{$boundary}--";

    $message = $headers . "\r\n" . $body;
    // 转义独行的点号
    $message = preg_replace('/^\.$/m', '..', $message);

    $send("MAIL FROM:<{$p['from']}>");
    $recv();
    $send("RCPT TO:<{$p['to']}>");
    $recv();
    $send('DATA');
    $recv();
    fwrite($sock, $message . "\r\n.\r\n");
    $dataResp = $recv();
    $send('QUIT');
    fclose($sock);

    if (strpos($dataResp, '250') !== false) {
        return ['ok' => true];
    }
    return ['ok' => false, 'msg' => 'SMTP DATA 响应：' . trim($dataResp)];
}