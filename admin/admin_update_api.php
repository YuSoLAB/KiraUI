<?php
// 更新API

// ▼ 修复：屏蔽 PHP 错误/警告的 HTML 输出，防止污染 JSON 响应
ini_set('display_errors', 0);
ob_start();

session_start();
header('Content-Type: application/json');

// 权限验证
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    ob_end_clean();
    echo json_encode(['code' => 403, 'msg' => '未授权']);
    exit;
}

define('ROOT_DIR',   dirname(dirname(__FILE__)));
define('CACHE_DIR',  ROOT_DIR . '/cache');
define('BACKUP_DIR', CACHE_DIR . '/backups');
define('UPDATE_TEMP', CACHE_DIR . '/update_temp');
define('UPDATE_ZIP_PATH', CACHE_DIR . '/update.zip');
define('DL_STATUS_FILE',  CACHE_DIR . '/download_status.json');

// 引入数据库类
require_once ROOT_DIR . '/include/Db.php';
$db = Db::getInstance();

$step = $_POST['step'] ?? '';

// 不被覆盖的文件和目录
$excludes = [
    'include/Db.php',
    'yusolab.sql',
    'img',
    'uploads',
    'cache/data',
    '.git'
];

// ▼ 修复：统一输出 JSON 并清空输出缓冲，防止脏数据污染响应
function jsonOut(array $data): void {
    ob_end_clean();
    echo json_encode($data);
    exit;
}

/* ────────────── 更新源 ────────────── */
function getUpdateSources() {
    global $db;
    $row = $db->query("SELECT config_value FROM system_config WHERE config_key = 'update_sources'")->fetch();
    if ($row) {
        $data = json_decode($row['config_value'], true);
        if (is_array($data) && isset($data['sources'])) return $data;
    }
    return [
        'sources' => [['name' => '官方源', 'url' => 'https://www.kiraui.cn/api/update.json']],
        'default' => 'https://www.kiraui.cn/api/update.json'
    ];
}

function saveUpdateSources($sources) {
    global $db;
    $json = json_encode($sources);
    $exists = $db->query("SELECT id FROM system_config WHERE config_key = 'update_sources'")->fetch();
    if ($exists) {
        $db->prepare("UPDATE system_config SET config_value=?, updated_at=? WHERE config_key='update_sources'")->execute([$json, time()]);
    } else {
        $db->prepare("INSERT INTO system_config (config_key,config_value,created_at,updated_at) VALUES (?,?,?,?)")->execute(['update_sources', $json, time(), time()]);
    }
}

try {
    switch ($step) {

        /* ══ 获取更新源 ══ */
        case 'get_update_sources':
            jsonOut(['code' => 200, 'msg' => 'ok', 'data' => getUpdateSources()]);

        /* ══ 添加更新源 ══ */
        case 'add_update_source':
            $name = trim($_POST['name'] ?? '');
            $url  = trim($_POST['url']  ?? '');
            if (!$name || !$url) throw new Exception('名称和URL不能为空');
            $sources = getUpdateSources();
            foreach ($sources['sources'] as $s) {
                if ($s['url'] === $url) throw new Exception('该URL已存在');
            }
            $sources['sources'][] = ['name' => $name, 'url' => $url];
            if (empty($sources['default']) && count($sources['sources']) === 1) $sources['default'] = $url;
            saveUpdateSources($sources);
            jsonOut(['code' => 200, 'msg' => 'ok']);

        /* ══ 删除更新源 ══ */
        case 'delete_update_source':
            $url = trim($_POST['url'] ?? '');
            if (!$url) throw new Exception('URL不能为空');
            $sources = getUpdateSources();
            $sources['sources'] = array_values(array_filter($sources['sources'], fn($s) => $s['url'] !== $url));
            if ($sources['default'] === $url) $sources['default'] = $sources['sources'][0]['url'] ?? '';
            saveUpdateSources($sources);
            jsonOut(['code' => 200, 'msg' => 'ok']);

        /* ══ 设置默认源 ══ */
        case 'set_default_source':
            $url = trim($_POST['url'] ?? '');
            if (!$url) throw new Exception('URL不能为空');
            $sources = getUpdateSources();
            $found = array_filter($sources['sources'], fn($s) => $s['url'] === $url);
            if (!$found) throw new Exception('该URL不在更新源列表中');
            $sources['default'] = $url;
            saveUpdateSources($sources);
            jsonOut(['code' => 200, 'msg' => 'ok']);

        /* ══ 检查更新 ══ */
        case 'check_update':
            $sourceUrl = trim($_POST['source_url'] ?? '');
            if (!$sourceUrl) {
                $sources   = getUpdateSources();
                $sourceUrl = $sources['default'] ?? ($sources['sources'][0]['url'] ?? 'https://www.kiraui.org/api/update.json');
            }
            $updateUrl = $sourceUrl . (strpos($sourceUrl, '?') === false ? '?' : '&') . 't=' . time();

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $updateUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT      => 'KiraUI-Admin/1.0',
            ]);
            $response  = curl_exec($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode !== 200 || $curlError) {
                $s = getUpdateSources();
                $other = array_values(array_column(array_filter($s['sources'], fn($x) => $x['url'] !== $sourceUrl), 'url'));
                jsonOut(['code' => 500, 'msg' => $curlError ?: "HTTP {$httpCode}", 'available_sources' => $other]);
            }
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $s = getUpdateSources();
                $other = array_values(array_column(array_filter($s['sources'], fn($x) => $x['url'] !== $sourceUrl), 'url'));
                jsonOut(['code' => 500, 'msg' => '更新信息格式错误', 'available_sources' => $other]);
            }
            jsonOut(['code' => 200, 'msg' => 'ok', 'data' => $data]);

        /* ══════════════════════════════════════════
           分片上传 — 接收单个分片
           ══════════════════════════════════════════ */
        case 'upload_chunk':
            $chunkIndex  = intval($_POST['chunk_index']  ?? -1);
            $totalChunks = intval($_POST['total_chunks'] ?? 0);
            $uploadId    = preg_replace('/[^a-z0-9_]/', '', strtolower($_POST['upload_id'] ?? 'default'));

            if ($chunkIndex < 0 || $totalChunks <= 0) throw new Exception('参数错误');
            if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
                $errCode = $_FILES['chunk']['error'] ?? 'N/A';
                throw new Exception("分片接收失败（错误码: {$errCode}）");
            }

            $chunkDir = CACHE_DIR . '/chunks_' . $uploadId;
            if (!is_dir($chunkDir)) {
                if (!mkdir($chunkDir, 0755, true)) throw new Exception('无法创建分片目录');
            }
            $chunkFile = $chunkDir . '/chunk_' . sprintf('%06d', $chunkIndex);
            if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkFile)) {
                throw new Exception("无法保存分片 {$chunkIndex}");
            }
            jsonOut(['code' => 200, 'msg' => 'ok', 'data' => ['chunk' => $chunkIndex]]);

        /* ══════════════════════════════════════════
           分片上传 — 合并所有分片
           ══════════════════════════════════════════ */
        case 'upload_chunk_merge':
            $totalChunks = intval($_POST['total_chunks'] ?? 0);
            $uploadId    = preg_replace('/[^a-z0-9_]/', '', strtolower($_POST['upload_id'] ?? 'default'));

            if ($totalChunks <= 0) throw new Exception('分片数量无效');

            $chunkDir = CACHE_DIR . '/chunks_' . $uploadId;
            if (!is_dir($chunkDir)) throw new Exception('分片目录不存在，请重新上传');

            // 验证所有分片存在
            for ($i = 0; $i < $totalChunks; $i++) {
                $cf = $chunkDir . '/chunk_' . sprintf('%06d', $i);
                if (!file_exists($cf)) throw new Exception("缺少分片 {$i}，请重新上传");
            }

            if (!is_dir(CACHE_DIR)) mkdir(CACHE_DIR, 0755, true);
            $fp = fopen(UPDATE_ZIP_PATH, 'wb');
            if (!$fp) throw new Exception('无法创建目标文件，请检查目录权限');

            for ($i = 0; $i < $totalChunks; $i++) {
                $cf   = $chunkDir . '/chunk_' . sprintf('%06d', $i);
                $data = file_get_contents($cf);
                if ($data === false) { fclose($fp); throw new Exception("读取分片 {$i} 失败"); }
                fwrite($fp, $data);
                unlink($cf);
            }
            fclose($fp);
            @rmdir($chunkDir);

            // 验证合并后是有效的 ZIP
            $zip = new ZipArchive();
            if ($zip->open(UPDATE_ZIP_PATH) !== true) {
                @unlink(UPDATE_ZIP_PATH);
                throw new Exception('合并后文件不是有效的 ZIP 压缩包，请检查文件后重试');
            }
            $zip->close();

            jsonOut(['code' => 200, 'msg' => 'ok', 'data' => ['temp_path' => UPDATE_ZIP_PATH]]);

        /* ══════════════════════════════════════════
           下载更新包（写入下载状态供前端轮询）
           ══════════════════════════════════════════ */
        case 'download':
            $url = trim($_POST['file_source'] ?? '');
            if (!$url) throw new Exception('下载地址为空');

            if (!is_dir(CACHE_DIR)) mkdir(CACHE_DIR, 0755, true);

            // ① 先 HEAD 获取文件大小
            $hch = curl_init($url);
            curl_setopt_array($hch, [
                CURLOPT_NOBODY         => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_USERAGENT      => 'KiraUI-Admin/1.0',
            ]);
            curl_exec($hch);
            $totalSize = (int)curl_getinfo($hch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
            curl_close($hch);

            // ② 写初始状态文件
            file_put_contents(DL_STATUS_FILE, json_encode([
                'status'     => 'downloading',
                'downloaded' => 0,
                'total'      => max(0, $totalSize),
                'started_at' => time(),
            ]));

            // ③ 下载，使用 WRITEFUNCTION 回调实时更新状态
            $fp         = fopen(UPDATE_ZIP_PATH, 'wb');
            $downloaded = 0;
            $lastFlush  = microtime(true);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_TIMEOUT        => 600,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT      => 'KiraUI-Admin/1.0',
                CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$fp, &$downloaded, &$lastFlush, $totalSize) {
                    $bytes       = fwrite($fp, $chunk);
                    $downloaded += $bytes;
                    $now         = microtime(true);
                    if ($now - $lastFlush >= 0.5) {
                        file_put_contents(DL_STATUS_FILE, json_encode([
                            'status'     => 'downloading',
                            'downloaded' => $downloaded,
                            'total'      => max(0, $totalSize),
                        ]));
                        $lastFlush = $now;
                    }
                    return $bytes;
                },
            ]);
            curl_exec($ch);
            if (curl_errno($ch)) {
                fclose($fp);
                file_put_contents(DL_STATUS_FILE, json_encode(['status' => 'error']));
                throw new Exception('下载失败: ' . curl_error($ch));
            }
            curl_close($ch);
            fclose($fp);

            // ④ 写完成状态
            file_put_contents(DL_STATUS_FILE, json_encode([
                'status'     => 'done',
                'downloaded' => $downloaded,
                'total'      => $downloaded,
            ]));

            jsonOut(['code' => 200, 'msg' => 'ok']);

        /* ══════════════════════════════════════════
           查询下载进度（前端轮询）
           ══════════════════════════════════════════ */
        case 'download_status':
            if (file_exists(DL_STATUS_FILE)) {
                $raw  = file_get_contents(DL_STATUS_FILE);
                $data = json_decode($raw, true);
                jsonOut(['code' => 200, 'msg' => 'ok', 'data' => $data ?: ['status' => 'idle']]);
            } else {
                jsonOut(['code' => 200, 'msg' => 'ok', 'data' => ['status' => 'idle']]);
            }

        /* ══ 备份 ══ */
        case 'backup':
            if (!is_dir(BACKUP_DIR)) mkdir(BACKUP_DIR, 0755, true);
            $backupFile = BACKUP_DIR . '/backup_' . date('Ymd_His') . '.zip';

            $zip = new ZipArchive();
            if ($zip->open($backupFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception('创建备份文件失败');
            }
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(ROOT_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $rel = substr($file->getRealPath(), strlen(ROOT_DIR) + 1);
                    if (strpos($rel, 'cache' . DIRECTORY_SEPARATOR) !== 0) {
                        $zip->addFile($file->getRealPath(), $rel);
                    }
                }
            }
            $zip->close();

            $_SESSION['last_backup_file'] = $backupFile;
            jsonOut(['code' => 200, 'msg' => 'ok']);

        /* ══ 验证 ══ */
        case 'verify':
            if (!file_exists(UPDATE_ZIP_PATH)) throw new Exception('更新包不存在');
            $expectedHash = $_POST['hash'] ?? '';
            if (!empty($expectedHash)) {
                $actual = hash_file('sha256', UPDATE_ZIP_PATH);
                if ($actual !== $expectedHash) throw new Exception('文件哈希值不匹配，可能已损坏');
            }
            jsonOut(['code' => 200, 'msg' => 'ok']);

        /* ══ 解压 ══ */
        case 'extract':
            if (!is_dir(UPDATE_TEMP)) mkdir(UPDATE_TEMP, 0755, true);
            $zip = new ZipArchive();
            if ($zip->open(UPDATE_ZIP_PATH) !== true) throw new Exception('解压更新包失败');
            $zip->extractTo(UPDATE_TEMP);
            $zip->close();
            jsonOut(['code' => 200, 'msg' => 'ok']);

        /* ══ 覆盖文件 ══ */
        case 'apply':
            if (!is_dir(UPDATE_TEMP)) throw new Exception('准备目录不存在');
            $baseDir = UPDATE_TEMP;
            $items   = array_diff(scandir($baseDir), ['.', '..']);
            if (count($items) === 1) {
                $first = reset($items);
                $sub   = $baseDir . DIRECTORY_SEPARATOR . $first;
                if (is_dir($sub)) $baseDir = $sub;
            }

            $iter = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iter as $item) {
                $subPath = $iter->getSubPathName();
                $subNorm = str_replace('\\', '/', $subPath);
                $skip    = false;
                foreach ($excludes as $ex) {
                    if ($subNorm === $ex || strpos($subNorm, $ex . '/') === 0) { $skip = true; break; }
                }
                if ($skip) continue;

                $target = ROOT_DIR . DIRECTORY_SEPARATOR . $subPath;
                if ($item->isDir()) {
                    if (!is_dir($target)) mkdir($target, 0755, true);
                } else {
                    $dir = dirname($target);
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    if (file_exists($target)) { @chmod($target, 0777); @unlink($target); }
                    if (!copy($item->getPathname(), $target)) {
                        throw new Exception("覆盖文件失败: {$subPath}（请检查目录权限）");
                    }
                }
            }
            if (function_exists('opcache_reset')) @opcache_reset();
            clearstatcache();
            jsonOut(['code' => 200, 'msg' => 'ok']);

        /* ══ 清理 ══ */
        case 'cleanup':
            @unlink(UPDATE_ZIP_PATH);
            @unlink(DL_STATUS_FILE);
            if (is_dir(UPDATE_TEMP)) {
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(UPDATE_TEMP, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($it as $fi) { $fi->isDir() ? @rmdir($fi->getRealPath()) : @unlink($fi->getRealPath()); }
                @rmdir(UPDATE_TEMP);
            }
            jsonOut(['code' => 200, 'msg' => 'ok']);

        /* ══ 回滚 ══ */
        case 'rollback':
            $backupFile = $_SESSION['last_backup_file'] ?? '';
            if (!$backupFile || !file_exists($backupFile)) throw new Exception('找不到备份文件: ' . $backupFile);

            $zip = new ZipArchive();
            if ($zip->open($backupFile) !== true) throw new Exception('无法打开备份文件');

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                $norm  = str_replace('\\', '/', $entry);
                $skip  = false;
                foreach ($excludes as $ex) { if (strpos($norm, $ex) === 0) { $skip = true; break; } }
                if ($skip) continue;

                $target = ROOT_DIR . DIRECTORY_SEPARATOR . $entry;
                if (substr($entry, -1) === '/') {
                    if (!is_dir($target)) mkdir($target, 0755, true);
                } else {
                    $dir = dirname($target);
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    if (!copy("zip://{$backupFile}#{$entry}", $target)) {
                        $zip->close();
                        throw new Exception('无法恢复文件: ' . $entry);
                    }
                }
            }
            $zip->close();
            jsonOut(['code' => 200, 'msg' => '回滚成功']);

        /* ══ 数据库迁移 ══ */
        case 'db_migrate':
            $migrationsDir = ROOT_DIR . '/migrations';
            if (!is_dir($migrationsDir)) {
                jsonOut(['code' => 200, 'msg' => 'no migrations', 'data' => []]);
            }
            require_once ROOT_DIR . '/include/DbMigrator.php';
            $migrator = new DbMigrator($db, $migrationsDir);
            $pending  = $migrator->getPending();
            if (empty($pending)) {
                jsonOut(['code' => 200, 'msg' => 'already up-to-date', 'data' => []]);
            }
            $results = $migrator->runPending();
            jsonOut(['code' => 200, 'msg' => 'ok', 'data' => $results]);

        default:
            throw new Exception('未知的更新步骤');
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['code' => 500, 'msg' => $e->getMessage()]);
}