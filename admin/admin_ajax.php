<?php
/**
 * admin_ajax.php
 * 专用 AJAX 端点，在任何 HTML 输出之前处理请求并返回 JSON。
 * 由 admin_menus.php / admin_pages.php / admin_*.php 中的 JS fetch 调用。
 */

// ── 最先开启输出缓冲，防止 PHP Notice/Warning 污染 JSON 响应 ──
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(dirname(__FILE__)));
}

// 安全检查：必须已登录
if (empty($_SESSION['admin_logged_in'])) {
    ob_clean();
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => '未登录或会话已过期']);
    exit;
}

require_once ROOT_DIR . '/include/Db.php';
require_once ROOT_DIR . '/include/Config.php';
ob_clean(); // 清除 require 可能产生的任何杂散输出
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
                'site_url'     => rtrim($_POST['site_url'] ?? '', '/'),
            ]);
            echo json_encode(['ok' => true, 'msg' => '网站信息已保存成功！']);
        }

        // ── 社交链接 ─────────────────────────────────────────
        elseif ($act === 'save_social') {
            $socialKeys = ['qq','wechat','weibo','x','facebook','instagram','youtube','github','steam','tiktok','douyin','bilibili','telegram','discord','line'];
            $data = [];
            foreach ($socialKeys as $k) {
                $data['social_' . $k] = trim($_POST['social_' . $k] ?? '');
            }
            $config->batchSet($data);
            echo json_encode(['ok' => true, 'msg' => '社交链接已保存成功！']);
        }

        // ── 图片上传（multipart, 单独走 upload_image 子动作）─
        elseif ($act === 'upload_image') {
            $uploadDir = ROOT_DIR . '/img/';
            if (!file_exists($uploadDir)) { mkdir($uploadDir, 0755, true); }

            $messages = [];
            $errors   = [];
            $uploadedFiles = [];  // 记录成功上传的文件，供前端刷新图库

            // Logo (png/jpg/jpeg/gif) → logo.png（仅用于导航栏）
            if (!empty($_FILES['logo']['name'])) {
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['png','jpg','jpeg','gif','ico'], true)) {
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . 'logo.png')) {
                        $messages[] = '导航栏 Logo 上传成功！';
                        $uploadedFiles['logo'] = 'logo.png';
                    } else {
                        $errors[] = '导航栏 Logo 上传失败，请检查目录权限';
                    }
                } else {
                    $errors[] = '导航栏 Logo 必须是 png/jpg/jpeg/gif 格式';
                }
            }

            // Favicon (.ico / .png / .svg) → favicon.{ext}（浏览器标签图标）
            if (!empty($_FILES['favicon']['name'])) {
                $ext = strtolower(pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['ico', 'png', 'svg'], true)) {
                    $destName = 'favicon.' . $ext;
                    // 清除其他格式的旧 favicon，防止多文件共存时浏览器取错
                    foreach (['ico', 'png', 'svg'] as $oldExt) {
                        if ($oldExt !== $ext && file_exists($uploadDir . 'favicon.' . $oldExt)) {
                            @unlink($uploadDir . 'favicon.' . $oldExt);
                        }
                    }
                    if (move_uploaded_file($_FILES['favicon']['tmp_name'], $uploadDir . $destName)) {
                        $messages[] = 'Favicon 上传成功（' . $destName . '）！';
                        $uploadedFiles['favicon'] = $destName;
                    } else {
                        $errors[] = 'Favicon 上传失败，请检查目录权限';
                    }
                } else {
                    $errors[] = 'Favicon 必须是 .ico / .png / .svg 格式';
                }
            }

            // Banner (png/jpg/jpeg/gif) — 兼容旧单文件字段
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
                        $uploadedFiles['banner'] = 'banner' . ($maxNum + 1) . '.png';
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
                echo json_encode(['ok' => true, 'msg' => implode(' ', $messages) ?: '没有文件被上传', 'files' => $uploadedFiles]);
            }
        }

        // ── 分片上传：检查已有分片（断点续传查询）────────────────
        elseif ($act === 'check_chunks') {
            $uploadId   = preg_replace('/[^a-f0-9]/', '', $_POST['upload_id'] ?? '');
            $totalChunks = max(1, (int)($_POST['chunk_total'] ?? 0));
            if (!$uploadId) { echo json_encode(['ok' => false, 'msg' => '参数错误']); exit; }

            $tmpDir = sys_get_temp_dir() . '/imgchunks/' . $uploadId . '/';
            $done = [];
            if (is_dir($tmpDir)) {
                for ($i = 0; $i < $totalChunks; $i++) {
                    if (file_exists($tmpDir . 'chunk_' . $i)) { $done[] = $i; }
                }
            }
            echo json_encode(['ok' => true, 'done' => $done]);
        }

        // ── 分片上传：接收单个分片并在完成时拼合 ─────────────────
        elseif ($act === 'upload_chunk') {
            $uploadId   = preg_replace('/[^a-f0-9]/', '', $_POST['upload_id'] ?? '');
            $chunkIndex = (int)($_POST['chunk_index'] ?? -1);
            $chunkTotal = (int)($_POST['chunk_total'] ?? 0);
            $fileType   = in_array($_POST['file_type'] ?? '', ['logo','favicon','banner'], true)
                          ? $_POST['file_type'] : 'banner';
            $origName   = basename($_POST['orig_name'] ?? 'file.png');

            if (!$uploadId || $chunkIndex < 0 || $chunkTotal < 1) {
                echo json_encode(['ok' => false, 'msg' => '分片参数错误']); exit;
            }
            if (empty($_FILES['chunk']['tmp_name']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
                $errCode = $_FILES['chunk']['error'] ?? -1;
                echo json_encode(['ok' => false, 'msg' => "分片数据为空或上传出错（错误码 {$errCode}）"]); exit;
            }

            $tmpDir = sys_get_temp_dir() . '/imgchunks/' . $uploadId . '/';
            if (!is_dir($tmpDir) && !mkdir($tmpDir, 0755, true)) {
                echo json_encode(['ok' => false, 'msg' => '无法创建临时目录，请检查服务器 /tmp 权限']); exit;
            }

            $chunkPath = $tmpDir . 'chunk_' . $chunkIndex;
            if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkPath)) {
                echo json_encode(['ok' => false, 'msg' => '分片保存失败']); exit;
            }

            // 检查是否所有分片已到齐
            $allDone = true;
            for ($i = 0; $i < $chunkTotal; $i++) {
                if (!file_exists($tmpDir . 'chunk_' . $i)) { $allDone = false; break; }
            }
            if (!$allDone) {
                echo json_encode(['ok' => true, 'complete' => false, 'msg' => "分片 {$chunkIndex} 已保存"]);
                exit;
            }

            // ── 全部分片到齐，拼合文件 ──
            $uploadDir = ROOT_DIR . '/img/';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                echo json_encode(['ok' => false, 'msg' => '图片目录不存在且无法创建']); exit;
            }

            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            if ($fileType === 'logo') {
                $destFile = 'logo.png';
                $label    = '导航栏 Logo（logo.png）';
            } elseif ($fileType === 'favicon') {
                if (!in_array($ext, ['ico','png','svg'], true)) {
                    echo json_encode(['ok' => false, 'msg' => 'Favicon 必须是 .ico / .png / .svg']); exit;
                }
                $destFile = 'favicon.' . $ext;
                $label    = '网站 Favicon（' . $destFile . '）';
                foreach (['ico','png','svg'] as $oldExt) {
                    if ($oldExt !== $ext && file_exists($uploadDir . 'favicon.' . $oldExt)) {
                        @unlink($uploadDir . 'favicon.' . $oldExt);
                    }
                }
            } else { // banner
                if (!in_array($ext, ['png','jpg','jpeg','gif'], true)) {
                    echo json_encode(['ok' => false, 'msg' => '背景图必须是 png/jpg/jpeg/gif 格式']); exit;
                }
                $existing = glob($uploadDir . 'banner*.png') ?: [];
                $maxNum   = 0;
                foreach ($existing as $f) {
                    if (preg_match('/banner(\d+)\.png$/', basename($f), $m)) {
                        $maxNum = max($maxNum, (int)$m[1]);
                    }
                }
                $destFile = 'banner' . ($maxNum + 1) . '.png';
                $label    = $destFile;
            }

            // 拼合分片
            $tmpAssemble = $tmpDir . 'assembled_' . $uploadId;
            $out = @fopen($tmpAssemble, 'wb');
            if (!$out) {
                echo json_encode(['ok' => false, 'msg' => '无法创建临时拼合文件']); exit;
            }
            for ($i = 0; $i < $chunkTotal; $i++) {
                $chunkData = @file_get_contents($tmpDir . 'chunk_' . $i);
                if ($chunkData === false) {
                    fclose($out); @unlink($tmpAssemble);
                    echo json_encode(['ok' => false, 'msg' => "分片 {$i} 读取失败"]); exit;
                }
                fwrite($out, $chunkData);
                @unlink($tmpDir . 'chunk_' . $i);
            }
            fclose($out);

            $destPath = $uploadDir . $destFile;
            if (!rename($tmpAssemble, $destPath)) {
                // rename 跨分区可能失败，改用 copy+unlink
                if (!copy($tmpAssemble, $destPath)) {
                    @unlink($tmpAssemble);
                    echo json_encode(['ok' => false, 'msg' => '文件写入目标目录失败，请检查目录权限']); exit;
                }
                @unlink($tmpAssemble);
            }
            @rmdir($tmpDir); // 尝试清理（非空时安全跳过）

            echo json_encode([
                'ok'        => true,
                'complete'  => true,
                'msg'       => '文件上传成功！',
                'file'      => $destFile,
                'label'     => $label,
                'file_type' => $fileType,
            ]);
        }

        // ── 删除图片 ──────────────────────────────────────────
        elseif ($act === 'delete_image') {
            $uploadDir = ROOT_DIR . '/img/';
            $file      = basename($_POST['file'] ?? '');
            // 仅允许删除 banner*.png、logo.png、favicon.ico/png/svg
            if ($file && preg_match('/^(banner\d+\.png|logo\.png|favicon\.(ico|png|svg))$/', $file)) {
                $path = $uploadDir . $file;
                if (file_exists($path) && @unlink($path)) {
                    echo json_encode(['ok' => true, 'msg' => $file . ' 已删除']);
                } else {
                    echo json_encode(['ok' => false, 'msg' => '文件不存在或无法删除']);
                }
            } else {
                echo json_encode(['ok' => false, 'msg' => '不允许删除该文件']);
            }
        }
        elseif ($act === 'save_landing') {
            $mode = $_POST['landing_mode'] ?? 'replace';
            if (!in_array($mode, ['replace', 'cover'], true)) { $mode = 'replace'; }
            $config->batchSet([
                'landing_enabled' => !empty($_POST['landing_enabled']) ? '1' : '0',
                'landing_code'    => $_POST['landing_code'] ?? '',
                'landing_mode'    => $mode,
            ]);
            echo json_encode(['ok' => true, 'msg' => '展示页面配置已保存成功！']);
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

        elseif ($act === 'test_smtp') {
            $toEmail = trim($_POST['to_email'] ?? '');
            if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['ok' => false, 'msg' => '请输入有效的测试收件邮箱']);
                exit;
            }
            require_once ROOT_DIR . '/include/Mailer.php';
            $mailer = new Mailer();
            if (!$mailer->isEnabled()) {
                echo json_encode(['ok' => false, 'msg' => 'SMTP 未启用或配置不完整，请先保存 SMTP 配置']);
                exit;
            }
            $result = $mailer->sendTestMail($toEmail);
            echo json_encode(['ok' => $result['ok'], 'msg' => $result['msg']]);
        }

        // ── 注册模式配置 ─────────────────────────────────────
        elseif ($act === 'save_registration') {
            $mode = $_POST['registration_mode'] ?? 'phone';
            if (!in_array($mode, ['phone', 'email', 'both'], true)) { $mode = 'phone'; }
            $config->batchSet([
                'registration_enabled' => !empty($_POST['registration_enabled']) ? '1' : '0',
                'registration_mode'    => $mode,
            ]);
            echo json_encode(['ok' => true, 'msg' => '注册配置已保存成功！']);
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
                // 同步元数据到 article_index（含 word_count / read_time，保留 pinned_at）
                $wc = mb_strlen(strip_tags($content));
                $rt = max(1, (int)round($wc / 400));
                // 同步更新 articles 表的 word_count / read_time
                $db->prepare("UPDATE articles SET word_count=?, read_time=? WHERE id=?")
                   ->execute([$wc, $rt, $newId]);
                $db->prepare(
                    "INSERT INTO article_index (id, title, date, excerpt, tags, word_count, read_time, cover_image)
                     VALUES (?,?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE
                       title=VALUES(title), date=VALUES(date), excerpt=VALUES(excerpt),
                       tags=VALUES(tags), word_count=VALUES(word_count),
                       read_time=VALUES(read_time), cover_image=VALUES(cover_image)"
                )->execute([$newId, $title, $date, $excerpt, $tags, $wc, $rt, $cover_image]);
                _updateTagStats($db);   // ← 立即刷新标签云
                _clearArticleCache();
                echo json_encode(['ok' => true, 'id' => $newId, 'msg' => '文章发布成功！']);
            } else {
                $stmt = $db->prepare(
                    "UPDATE articles SET title=?, excerpt=?, content=?, date=?, tags=?, cover_image=?, updated_at=NOW() WHERE id=?"
                );
                $stmt->execute([$title, $excerpt, $content, $date, $tags, $cover_image, $id]);
                // 同步元数据到 article_index（含 word_count / read_time，保留 pinned_at）
                $wc = mb_strlen(strip_tags($content));
                $rt = max(1, (int)round($wc / 400));
                $db->prepare("UPDATE articles SET word_count=?, read_time=? WHERE id=?")
                   ->execute([$wc, $rt, $id]);
                $db->prepare(
                    "INSERT INTO article_index (id, title, date, excerpt, tags, word_count, read_time, cover_image)
                     VALUES (?,?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE
                       title=VALUES(title), date=VALUES(date), excerpt=VALUES(excerpt),
                       tags=VALUES(tags), word_count=VALUES(word_count),
                       read_time=VALUES(read_time), cover_image=VALUES(cover_image)"
                )->execute([$id, $title, $date, $excerpt, $tags, $wc, $rt, $cover_image]);
                _updateTagStats($db);   // ← 立即刷新标签云
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

        // ── 切换置顶 ─────────────────────────────────────────
        elseif ($act === 'toggle_pin') {
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['ok' => false, 'msg' => '无效的文章 ID']);
                exit;
            }

            $chk = $db->prepare("SELECT pinned_at FROM article_index WHERE id = ?");
            $chk->execute([$id]);
            $row = $chk->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                echo json_encode(['ok' => false, 'msg' => '文章不存在']);
                exit;
            }

            if ($row['pinned_at'] === null) {
                $now = date('Y-m-d H:i:s');
                $db->prepare("UPDATE article_index SET pinned_at = ? WHERE id = ?")->execute([$now, $id]);
                $db->prepare("UPDATE articles       SET pinned_at = ? WHERE id = ?")->execute([$now, $id]);
                $msg    = '文章已置顶';
                $pinned = true;
            } else {
                $db->prepare("UPDATE article_index SET pinned_at = NULL WHERE id = ?")->execute([$id]);
                $db->prepare("UPDATE articles       SET pinned_at = NULL WHERE id = ?")->execute([$id]);
                $msg    = '已取消置顶';
                $pinned = false;
            }

            _clearArticleCache();
            echo json_encode(['ok' => true, 'msg' => $msg, 'pinned' => $pinned]);
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
            // 同步元数据到 article_index（含 word_count / read_time，保留 pinned_at）
            $wc = mb_strlen(strip_tags($draft['content']));
            $rt = max(1, (int)round($wc / 400));
            $db->prepare("UPDATE articles SET word_count=?, read_time=? WHERE id=?")
               ->execute([$wc, $rt, $newId]);
            $db->prepare(
                "INSERT INTO article_index (id, title, date, excerpt, tags, word_count, read_time, cover_image)
                 VALUES (?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                   title=VALUES(title), date=VALUES(date), excerpt=VALUES(excerpt),
                   tags=VALUES(tags), word_count=VALUES(word_count),
                   read_time=VALUES(read_time), cover_image=VALUES(cover_image)"
            )->execute([
                $newId, $draft['title'], $draft['date'], $draft['excerpt'],
                $draft['tags'], $wc, $rt, $draft['cover_image'] ?? ''
            ]);
            _updateTagStats($db);   // ← 立即刷新标签云
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

    // ═══════════════════════════════════════════════════════
    // 用户设置（user）— 注册邮箱域名白/黑名单
    // ═══════════════════════════════════════════════════════
    elseif ($type === 'user') {
        require_once __DIR__ . '/admin_functions.php';
        $act = $_POST['user_action'] ?? '';

        if ($act === 'save_registration_settings') {
            $enabled = ($_POST['registration_enabled'] ?? '1') === '0' ? '0' : '1';
            require_once ROOT_DIR . '/include/Config.php';
            Config::getInstance()->set('registration_enabled', $enabled);
            echo json_encode(['ok' => true, 'msg' => $enabled === '1' ? '注册已开放' : '注册已关闭']);
        } elseif ($act === 'save_email_settings') {
            $settings = [
                'email_mode'      => $_POST['email_mode'] ?? 'all',
                'allowed_domains' => array_values(array_filter(array_map('trim',
                    explode("\n", str_replace("\r", "\n", $_POST['allowed_domains'] ?? ''))))),
                'blocked_domains' => array_values(array_filter(array_map('trim',
                    explode("\n", str_replace("\r", "\n", $_POST['blocked_domains'] ?? ''))))),
            ];
            saveRegistrationEmailSettings($settings);
            echo json_encode(['ok' => true, 'msg' => '注册邮箱设置已保存！']);

        } elseif ($act === 'save_sms_settings') {
            // 保存阿里云短信凭证到 Config
            require_once ROOT_DIR . '/include/Config.php';
            $cfg = Config::getInstance();
            $keyId  = trim($_POST['aliyun_access_key_id']     ?? '');
            $sign   = trim($_POST['aliyun_sms_sign_name']     ?? '');
            $tpl    = trim($_POST['aliyun_sms_template_code'] ?? '100001');
            $secret = trim($_POST['aliyun_access_key_secret'] ?? '');

            if ($keyId !== '') { $cfg->set('aliyun_access_key_id',    $keyId); }
            if ($sign  !== '') { $cfg->set('aliyun_sms_sign_name',    $sign);  }
            if ($tpl   !== '') { $cfg->set('aliyun_sms_template_code', $tpl);  }
            // Secret 仅在非空时覆盖，防止留空误清除
            if ($secret !== '') { $cfg->set('aliyun_access_key_secret', $secret); }

            echo json_encode(['ok' => true, 'msg' => '短信设置已保存']);

        } elseif ($act === 'test_sms_connection') {
            // 用已保存的凭证尝试初始化客户端（不实际发送短信）
            require_once ROOT_DIR . '/include/Config.php';
            require_once ROOT_DIR . '/include/AliSms.php';
            try {
                $sms = AliSms::fromConfig();
                // 通过构建 Client 对象来验证凭证格式（不调用 API）
                echo json_encode(['ok' => true, 'msg' => '凭证格式验证通过，SDK 客户端已成功初始化']);
            } catch (\Throwable $e) {
                echo json_encode(['ok' => false, 'msg' => '初始化失败：' . $e->getMessage()]);
            }
        } 
        
        elseif ($act === 'save_email_prefs') {
            // 用户自助保存邮件通知偏好（用户中心 AJAX 调用）
            // 此路由需验证是普通用户 Session（非管理员 Session）
            // 若用户中心与后台共用 admin_ajax.php，请在此处检查
            // $_SESSION['user_logged_in'] 而非 $_SESSION['admin_logged_in']
        
            $uid    = (int)($_SESSION['user']['id'] ?? 0);
            if ($uid <= 0) {
                echo json_encode(['ok' => false, 'msg' => '未登录']);
                exit;
            }
            $newVal = (isset($_POST['notify_on_reply']) && $_POST['notify_on_reply'] === '1') ? 1 : 0;
        
            try {
                // 确保列存在
                $chk = $db->query(
                    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = 'users'
                    AND COLUMN_NAME  = 'notify_on_reply'"
                );
                if (!$chk || !$chk->fetch()) {
                    $db->exec("ALTER TABLE users ADD COLUMN notify_on_reply TINYINT(1) NOT NULL DEFAULT 1");
                }
        
                $upd = $db->prepare("UPDATE users SET notify_on_reply = ? WHERE id = ?");
                $upd->execute([$newVal, $uid]);
                $_SESSION['user']['notify_on_reply'] = $newVal;
                echo json_encode(['ok' => true, 'msg' => '设置已保存']);
            } catch (PDOException $e) {
                echo json_encode(['ok' => false, 'msg' => '保存失败：' . $e->getMessage()]);
            }
        }
        else {
            echo json_encode(['ok' => false, 'msg' => '未知用户操作']);
        }
    }

    // ═══════════════════════════════════════════════════════
    // 评论设置（comment）— 域名过滤 / 功能开关
    // ═══════════════════════════════════════════════════════
    elseif ($type === 'comment') {
        require_once __DIR__ . '/admin_functions.php';
        require_once __DIR__ . '/comment_functions.php';
        $act = $_POST['comment_action'] ?? '';

        if ($act === 'save_settings') {
            $settings = [
                'email_mode'           => $_POST['email_mode']         ?? 'all',
                'default_moderation'   => $_POST['default_moderation'] ?? 'strict',
                'enable_comments'      => !empty($_POST['enable_comments']),
                'allow_guest_comments' => !empty($_POST['allow_guest_comments']),
                'allowed_domains'      => array_values(array_filter(array_map('trim',
                    explode("\n", str_replace("\r", "\n", $_POST['allowed_domains'] ?? ''))))),
                'blocked_domains'      => array_values(array_filter(array_map('trim',
                    explode("\n", str_replace("\r", "\n", $_POST['blocked_domains'] ?? ''))))),
            ];
            saveCommentSettings($settings);

            // ── 持久化 notify_admin ───────────────────────────────────
            $notifyAdmin = !empty($_POST['notify_admin']) ? 1 : 0;
            try {
                $db->exec("UPDATE comment_settings SET notify_admin = {$notifyAdmin} WHERE id = 1");
            } catch (PDOException $naE) {
                error_log('[save_settings] notify_admin: ' . $naE->getMessage());
            }

            echo json_encode(['ok' => true, 'msg' => '评论设置已保存！']);

        } elseif ($act === 'approve') {
            $commentId = intval($_POST['comment_id'] ?? 0);
            $articleId = intval($_POST['article_id'] ?? 0);
            if ($commentId <= 0) { echo json_encode(['ok' => false, 'msg' => '无效的评论 ID']); exit; }
            $ok = moderateComment($articleId, $commentId, true);
            // 返回剩余待审数量，供前端更新徽章
            $pending = (int)$db->query("SELECT COUNT(*) FROM comments WHERE approved = 0")->fetchColumn();
            echo json_encode(['ok' => $ok, 'msg' => $ok ? '评论已通过审核' : '操作失败，请重试', 'pending' => $pending]);

        } elseif ($act === 'reject') {
            $commentId = intval($_POST['comment_id'] ?? 0);
            $articleId = intval($_POST['article_id'] ?? 0);
            if ($commentId <= 0) { echo json_encode(['ok' => false, 'msg' => '无效的评论 ID']); exit; }
            $ok = moderateComment($articleId, $commentId, false);
            $pending = (int)$db->query("SELECT COUNT(*) FROM comments WHERE approved = 0")->fetchColumn();
            echo json_encode(['ok' => $ok, 'msg' => $ok ? '评论已拒绝' : '操作失败，请重试', 'pending' => $pending]);

        } elseif ($act === 'delete') {
            $commentId = intval($_POST['comment_id'] ?? 0);
            $articleId = intval($_POST['article_id'] ?? 0);
            if ($commentId <= 0) { echo json_encode(['ok' => false, 'msg' => '无效的评论 ID']); exit; }
            $ok = deleteComment($articleId, $commentId);
            $pending = (int)$db->query("SELECT COUNT(*) FROM comments WHERE approved = 0")->fetchColumn();
            echo json_encode(['ok' => $ok, 'msg' => $ok ? '评论已删除' : '操作失败，请重试', 'pending' => $pending]);

        } elseif ($act === 'approve_all') {
            $stmt = $db->prepare("UPDATE comments SET approved = 1 WHERE approved = 0");
            $stmt->execute();
            $affected = $stmt->rowCount();
            echo json_encode(['ok' => true, 'msg' => "已批量通过 {$affected} 条待审评论", 'pending' => 0]);

        } elseif ($act === 'update_email_moderation') {
            $emailHash = $_POST['email_hash'] ?? '';
            $mode      = $_POST['mode'] ?? 'strict';
            if ($emailHash && in_array($mode, ['auto', 'strict'], true)) {
                updateEmailModeration($emailHash, $mode);
                echo json_encode(['ok' => true, 'msg' => '邮箱审核模式已更新']);
            } else {
                echo json_encode(['ok' => false, 'msg' => '参数错误']);
            }

        } else {
            echo json_encode(['ok' => false, 'msg' => '未知评论操作']);
        }
    }

    // ═══════════════════════════════════════════════════════
    // 信息变更审核（profile_review）
    // ═══════════════════════════════════════════════════════
    elseif ($type === 'profile_review') {
        $act = $_POST['action'] ?? '';

        // ── 开关审核功能 ─────────────────────────────────────
        if ($act === 'toggle_setting') {
            $enabled = ($_POST['enabled'] ?? '0') === '1' ? '1' : '0';
            Config::getInstance()->set('profile_review_enabled', $enabled);
            echo json_encode(['ok' => true]);
        }

        // ── 通过变更申请 ─────────────────────────────────────
        elseif ($act === 'approve') {
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) { echo json_encode(['ok' => false, 'msg' => '无效的 ID']); exit; }

            $stmt = $db->prepare(
                "SELECT * FROM pending_profile_changes WHERE id = ? AND status = 'pending'"
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['ok' => false, 'msg' => '记录不存在或已处理']); exit; }

            if ($row['type'] === 'nickname') {
                $db->prepare("UPDATE users SET nickname = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$row['new_value'], $row['user_id']]);
            } else {
                // 头像：将 pending 文件重命名为正式文件
                $avatarDir  = ROOT_DIR . '/uploads/avatars/';
                $pendingFile = $avatarDir . $row['new_value'];

                // 删除该用户旧头像（所有扩展名）
                $extMap = ['jpg','jpeg','png','gif'];
                foreach ($extMap as $e) {
                    $old = $avatarDir . $row['user_id'] . '.' . $e;
                    if (file_exists($old) && $old !== $pendingFile) { @unlink($old); }
                }

                // 将 pending_{id}.ext → {user_id}.ext
                $ext     = pathinfo($row['new_value'], PATHINFO_EXTENSION);
                $newFile = $row['user_id'] . '.' . $ext;
                if (file_exists($pendingFile)) {
                    rename($pendingFile, $avatarDir . $newFile);
                }

                $db->prepare("UPDATE users SET avatar = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$newFile, $row['user_id']]);
            }

            $db->prepare(
                "UPDATE pending_profile_changes SET status='approved', reviewed_at=NOW() WHERE id=?"
            )->execute([$id]);

            echo json_encode(['ok' => true, 'msg' => '已通过']);
        }

        // ── 拒绝变更申请 ─────────────────────────────────────
        elseif ($act === 'reject') {
            $id     = intval($_POST['id'] ?? 0);
            $reason = mb_substr(trim($_POST['reason'] ?? ''), 0, 200);
            if ($id <= 0) { echo json_encode(['ok' => false, 'msg' => '无效的 ID']); exit; }

            $stmt = $db->prepare(
                "SELECT * FROM pending_profile_changes WHERE id = ? AND status = 'pending'"
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['ok' => false, 'msg' => '记录不存在或已处理']); exit; }

            // 头像拒绝时删除 pending 文件
            if ($row['type'] === 'avatar') {
                $pendingFile = ROOT_DIR . '/uploads/avatars/' . $row['new_value'];
                if (file_exists($pendingFile)) { @unlink($pendingFile); }
            }

            $db->prepare(
                "UPDATE pending_profile_changes SET status='rejected', reject_reason=?, reviewed_at=NOW() WHERE id=?"
            )->execute([$reason ?: null, $id]);

            echo json_encode(['ok' => true, 'msg' => '已拒绝']);
        }

        // ── 获取列表（AJAX 分页，无刷新切换 Tab）──────────────
        elseif ($act === 'get_list') {
            $allowedStatuses = ['pending', 'approved', 'rejected', 'all'];
            $statusFilter = $_POST['status'] ?? 'pending';
            if (!in_array($statusFilter, $allowedStatuses, true)) { $statusFilter = 'pending'; }

            $perPage = 20;
            $page    = max(1, intval($_POST['page'] ?? 1));

            $whereStatus = $statusFilter === 'all'
                ? ''
                : "WHERE p.status = " . $db->quote($statusFilter);

            $total      = (int)$db->query("SELECT COUNT(*) FROM pending_profile_changes p $whereStatus")->fetchColumn();
            $totalPages = max(1, (int)ceil($total / $perPage));
            $page       = min($page, $totalPages);
            $offset     = ($page - 1) * $perPage;

            $stmt = $db->prepare(
                "SELECT p.*, u.username, u.nickname AS current_nickname, u.avatar AS current_avatar
                   FROM pending_profile_changes p
                   JOIN users u ON u.id = p.user_id
                 $whereStatus
                 ORDER BY p.created_at DESC
                 LIMIT :limit OFFSET :offset"
            );
            $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $pendingCount = (int)$db->query(
                "SELECT COUNT(*) FROM pending_profile_changes WHERE status='pending'"
            )->fetchColumn();

            echo json_encode([
                'ok'           => true,
                'items'        => $items,
                'total'        => $total,
                'page'         => $page,
                'totalPages'   => $totalPages,
                'pendingCount' => $pendingCount,
                'statusFilter' => $statusFilter,
            ]);
        }

        else {
            echo json_encode(['ok' => false, 'msg' => '未知审核操作']);
        }
    }

    elseif ($type === 'comment_notify') {
    
        // 只允许有效值
        $toInt = function ($key) {
            return isset($_POST[$key]) && $_POST[$key] === '1' ? 1 : 0;
        };
    
        $emailNotifyEnabled = $toInt('email_notify_enabled');
        $notifyAdmin        = $toInt('notify_admin');
        $notifyGuestReply   = $toInt('notify_guest_reply');
    
        try {
            // 确保列存在（首次使用时自动补列，免手动执行 SQL）
            $colCheck = $db->query(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'comment_settings'
                AND COLUMN_NAME IN ('email_notify_enabled','notify_admin','notify_guest_reply')"
            );
            $existingCols = $colCheck ? array_column($colCheck->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME') : [];
    
            foreach ([
                'email_notify_enabled' => 'TINYINT(1) NOT NULL DEFAULT 1',
                'notify_admin'         => 'TINYINT(1) NOT NULL DEFAULT 1',
                'notify_guest_reply'   => 'TINYINT(1) NOT NULL DEFAULT 1',
            ] as $col => $def) {
                if (!in_array($col, $existingCols)) {
                    $db->exec("ALTER TABLE comment_settings ADD COLUMN `{$col}` {$def}");
                }
            }
    
            // 确保有一条记录
            $cnt = (int)$db->query("SELECT COUNT(*) FROM comment_settings")->fetchColumn();
            if ($cnt === 0) {
                $db->exec("INSERT INTO comment_settings (id, email_mode, default_moderation, enable_comments) VALUES (1,'all','strict',1)");
            }
    
            $upd = $db->prepare(
                "UPDATE comment_settings
                SET email_notify_enabled = ?,
                    notify_admin         = ?,
                    notify_guest_reply   = ?
                WHERE id = 1"
            );
            $upd->execute([$emailNotifyEnabled, $notifyAdmin, $notifyGuestReply]);
    
            echo json_encode(['ok' => true, 'msg' => '通知设置已保存']);
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'msg' => '保存失败：' . $e->getMessage()]);
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
 * _updateTagStats — 重新统计所有标签并写入 tag_stats 表。
 * 在每次文章发布/保存/删除后调用，确保标签云即时更新，无需手动重建索引。
 */
function _updateTagStats(PDO $db): void {
    try {
        $rows = $db->query(
            "SELECT tags FROM article_index WHERE tags IS NOT NULL AND tags != ''"
        )->fetchAll(PDO::FETCH_COLUMN);

        $counts = [];
        foreach ($rows as $tagStr) {
            foreach (explode(',', $tagStr) as $tag) {
                $tag = trim($tag);
                if ($tag !== '') {
                    $counts[$tag] = ($counts[$tag] ?? 0) + 1;
                }
            }
        }

        $db->exec("TRUNCATE TABLE tag_stats");
        $ins = $db->prepare("INSERT INTO tag_stats (tag, count) VALUES (?, ?)");
        foreach ($counts as $tag => $count) {
            $ins->execute([$tag, $count]);
        }
    } catch (Exception $e) {
        error_log('_updateTagStats error: ' . $e->getMessage());
    }
}

/**
 * 清除文章列表缓存，确保封面图等修改立即反映到首页。
 * 兼容 ArticleIndex JSON 缓存 + FileCache 文件缓存两套机制。
 */
function _clearArticleCache(): void {
    if (!defined('ROOT_DIR')) { return; }
    $cacheDir = ROOT_DIR . '/cache/';
    $dataDir  = $cacheDir . 'data/';

    // 1) 清除可能的旧格式缓存文件（历史兼容）
    $targets = [
        $cacheDir . 'article_index.json',
        $cacheDir . 'article_index.php',
        $cacheDir . 'articles_index.json',
    ];
    foreach ($targets as $f) {
        if (file_exists($f)) { @unlink($f); }
    }

    // 2) 通过 FileCache API 精确删除核心缓存 key
    try {
        if (file_exists(ROOT_DIR . '/cache/FileCache.php')) {
            require_once ROOT_DIR . '/cache/FileCache.php';
            $cache = new FileCache();
            $cache->delete('article_index');
            $cache->delete('all_articles_basic');
            // 遍历所有已发布文章，清除每篇文章的内容缓存
            global $db;
            if ($db) {
                $stmt = $db->query("SELECT id FROM articles");
                while ($row = $stmt->fetch()) {
                    $cache->delete('article_content_' . $row['id']);
                }
            }
        }
    } catch (Exception $e) {
        error_log('_clearArticleCache FileCache error: ' . $e->getMessage());
    }

    // 3) 清除 cache/data/ 下所有 FileCache 生成的缓存文件（彻底清除）
    if (is_dir($dataDir)) {
        $files = glob($dataDir . '*.cache');
        if ($files) {
            foreach ($files as $f) {
                if (is_file($f)) { @unlink($f); }
            }
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