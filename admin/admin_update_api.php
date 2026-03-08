<?php
// 更新API
session_start();
header('Content-Type: application/json');

// 权限验证
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die(json_encode(['code' => 403, 'msg' => '未授权']));
}

define('ROOT_DIR', dirname(dirname(__FILE__)));
define('CACHE_DIR', ROOT_DIR . '/cache');
define('BACKUP_DIR', CACHE_DIR . '/backups');
define('UPDATE_TEMP', CACHE_DIR . '/update_temp');
define('UPDATE_ZIP_PATH', CACHE_DIR . '/update.zip');

// 引入数据库类
require_once ROOT_DIR . '/include/Db.php';
$db = Db::getInstance();

$step = $_POST['step'] ?? '';

// 需要保留、不被覆盖的文件和目录相对路径
$excludes = [
    'include/Db.php',
    'yusolab.sql',
    'img',
    'uploads',
    'cache',
    '.git'
];

// 获取更新源配置
function getUpdateSources() {
    global $db;
    $row = $db->query("SELECT config_value FROM system_config WHERE config_key = 'update_sources'")->fetch();
    if ($row) {
        $data = json_decode($row['config_value'], true);
        if (is_array($data) && isset($data['sources'])) {
            return $data;
        }
    }
    // 默认配置
    return [
        'sources' => [
            ['name' => '官方源', 'url' => 'https://www.kiraui.org/api/update.json']
        ],
        'default' => 'https://www.kiraui.org/api/update.json'
    ];
}

// 保存更新源配置
function saveUpdateSources($sources) {
    global $db;
    $json = json_encode($sources);
    $exists = $db->query("SELECT id FROM system_config WHERE config_key = 'update_sources'")->fetch();
    if ($exists) {
        $stmt = $db->prepare("UPDATE system_config SET config_value = ?, updated_at = ? WHERE config_key = 'update_sources'");
        $stmt->execute([$json, time()]);
    } else {
        $stmt = $db->prepare("INSERT INTO system_config (config_key, config_value, created_at, updated_at) VALUES (?, ?, ?, ?)");
        $stmt->execute(['update_sources', $json, time(), time()]);
    }
}

try {
    switch ($step) {
        // 获取更新源列表
        case 'get_update_sources':
            $sources = getUpdateSources();
            echo json_encode(['code' => 200, 'msg' => 'ok', 'data' => $sources]);
            break;

        // 添加更新源
        case 'add_update_source':
            $name = $_POST['name'] ?? '';
            $url = $_POST['url'] ?? '';
            if (empty($name) || empty($url)) {
                throw new Exception('名称和URL不能为空');
            }
            $sources = getUpdateSources();
            // 检查是否已存在相同URL
            foreach ($sources['sources'] as $s) {
                if ($s['url'] === $url) {
                    throw new Exception('该URL已存在');
                }
            }
            $sources['sources'][] = ['name' => $name, 'url' => $url];
            // 如果还没有默认源，则将第一个设为默认
            if (empty($sources['default']) && count($sources['sources']) === 1) {
                $sources['default'] = $url;
            }
            saveUpdateSources($sources);
            echo json_encode(['code' => 200, 'msg' => 'ok']);
            break;

        // 删除更新源
        case 'delete_update_source':
            $url = $_POST['url'] ?? '';
            if (empty($url)) {
                throw new Exception('URL不能为空');
            }
            $sources = getUpdateSources();
            $newSources = [];
            foreach ($sources['sources'] as $s) {
                if ($s['url'] !== $url) {
                    $newSources[] = $s;
                }
            }
            $sources['sources'] = $newSources;
            // 如果删除的是默认源，则重新设置默认源为第一个
            if ($sources['default'] === $url) {
                $sources['default'] = !empty($newSources) ? $newSources[0]['url'] : '';
            }
            saveUpdateSources($sources);
            echo json_encode(['code' => 200, 'msg' => 'ok']);
            break;

        // 设置默认源
        case 'set_default_source':
            $url = $_POST['url'] ?? '';
            if (empty($url)) {
                throw new Exception('URL不能为空');
            }
            $sources = getUpdateSources();
            // 检查URL是否在列表中
            $found = false;
            foreach ($sources['sources'] as $s) {
                if ($s['url'] === $url) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                throw new Exception('该URL不在更新源列表中');
            }
            $sources['default'] = $url;
            saveUpdateSources($sources);
            echo json_encode(['code' => 200, 'msg' => 'ok']);
            break;

        // 检查更新，使用指定源
        case 'check_update':
            // 获取当前选中的源URL
            $sourceUrl = $_POST['source_url'] ?? '';
            if (empty($sourceUrl)) {
                // 未指定，从数据库获取默认
                $sources = getUpdateSources();
                $sourceUrl = $sources['default'] ?? '';
                if (empty($sourceUrl) && !empty($sources['sources'])) {
                    $sourceUrl = $sources['sources'][0]['url'];
                }
                if (empty($sourceUrl)) {
                    // 最终默认
                    $sourceUrl = 'https://www.kiraui.org/api/update.json';
                }
            }

            // 构建请求URL
            $updateUrl = $sourceUrl . (strpos($sourceUrl, '?') === false ? '?' : '&') . 't=' . time();

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $updateUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'KiraUI-Admin/1.0');

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // 如果请求失败，收集其他可用源
            if ($httpCode !== 200 || $curlError) {
                $sources = getUpdateSources();
                $otherSources = array_filter($sources['sources'], function($s) use ($sourceUrl) {
                    return $s['url'] !== $sourceUrl;
                });
                $otherUrls = array_values(array_column($otherSources, 'url'));
                echo json_encode([
                    'code' => 500,
                    'msg' => $curlError ?: "HTTP {$httpCode}",
                    'available_sources' => $otherUrls
                ]);
                break;
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // JSON解析失败，也返回可用源
                $sources = getUpdateSources();
                $otherSources = array_filter($sources['sources'], function($s) use ($sourceUrl) {
                    return $s['url'] !== $sourceUrl;
                });
                $otherUrls = array_values(array_column($otherSources, 'url'));
                echo json_encode([
                    'code' => 500,
                    'msg' => '更新信息格式错误',
                    'available_sources' => $otherUrls
                ]);
                break;
            }

            echo json_encode(['code' => 200, 'msg' => 'ok', 'data' => $data]);
            break;

        case 'upload_manual':
            if (!isset($_FILES['update_file']) || $_FILES['update_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("文件上传失败");
            }
            if (!move_uploaded_file($_FILES['update_file']['tmp_name'], UPDATE_ZIP_PATH)) {
                throw new Exception("无法保存上传的文件");
            }
            echo json_encode(['code' => 200, 'msg' => 'ok', 'data' => ['temp_path' => UPDATE_ZIP_PATH]]);
            break;

        case 'backup':
            if (!is_dir(BACKUP_DIR)) mkdir(BACKUP_DIR, 0755, true);
            $backupFile = BACKUP_DIR . '/backup_' . date('Ymd_His') . '.zip';

            $zip = new ZipArchive();
            if ($zip->open($backupFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(ROOT_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($files as $name => $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen(ROOT_DIR) + 1);
                        if (strpos($relativePath, 'cache' . DIRECTORY_SEPARATOR) !== 0) {
                            $zip->addFile($filePath, $relativePath);
                        }
                    }
                }
                $zip->close();
            } else {
                throw new Exception("创建文件备份失败");
            }

            // 记录当前备份路径供回滚使用
            $_SESSION['last_backup_file'] = $backupFile;
            echo json_encode(['code' => 200, 'msg' => 'ok']);
            break;

        case 'download':
            $url = $_POST['file_source'] ?? '';
            if (empty($url)) throw new Exception("下载地址为空");

            $fp = fopen(UPDATE_ZIP_PATH, 'w+');
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception("下载失败: " . curl_error($ch));
            }
            curl_close($ch);
            fclose($fp);
            echo json_encode(['code' => 200, 'msg' => 'ok']);
            break;

        case 'verify':
            $expectedHash = $_POST['hash'] ?? '';
            if (!file_exists(UPDATE_ZIP_PATH)) throw new Exception("更新包不存在");
            if (!empty($expectedHash)) {
                $actualHash = hash_file('sha256', UPDATE_ZIP_PATH);
                if ($actualHash !== $expectedHash) {
                    throw new Exception("文件哈希值不匹配，可能已损坏");
                }
            }
            echo json_encode(['code' => 200, 'msg' => 'ok']);
            break;

        case 'extract':
            if (!is_dir(UPDATE_TEMP)) mkdir(UPDATE_TEMP, 0755, true);
            $zip = new ZipArchive();
            if ($zip->open(UPDATE_ZIP_PATH) === TRUE) {
                $zip->extractTo(UPDATE_TEMP);
                $zip->close();
            } else {
                throw new Exception("解压更新包失败");
            }
            echo json_encode(['code' => 200, 'msg' => 'ok']);
            break;

        case 'apply':
            if (!is_dir(UPDATE_TEMP)) throw new Exception("准备目录不存在");
            $baseDir = UPDATE_TEMP;
            $scannedFiles = array_diff(scandir($baseDir), ['.', '..']);
            if (count($scannedFiles) === 1) {
                $firstItem = reset($scannedFiles);
                $potentialBase = $baseDir . DIRECTORY_SEPARATOR . $firstItem;
                if (is_dir($potentialBase)) {
                    $baseDir = $potentialBase;
                }
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $subPath = $iterator->getSubPathName();
                $subPathNormalized = str_replace('\\', '/', $subPath);

                $skip = false;
                foreach ($excludes as $exclude) {
                    if ($subPathNormalized === $exclude || strpos($subPathNormalized, $exclude . '/') === 0) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip) continue;

                $targetPath = ROOT_DIR . DIRECTORY_SEPARATOR . $subPath;
                if ($item->isDir()) {
                    if (!is_dir($targetPath)) mkdir($targetPath, 0755, true);
                } else {
                    $targetDir = dirname($targetPath);
                    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

                    if (file_exists($targetPath)) {
                        @chmod($targetPath, 0777);
                        @unlink($targetPath);
                    }

                    if (!copy($item->getPathname(), $targetPath)) {
                        throw new Exception("覆盖文件失败: " . $subPath . " (请检查目录权限)");
                    }
                }
            }

            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            clearstatcache();

            echo json_encode(['code' => 200, 'msg' => 'ok']);
            break;

        case 'cleanup':
            @unlink(UPDATE_ZIP_PATH);
            $tempFiles = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(UPDATE_TEMP, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($tempFiles as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                @$todo($fileinfo->getRealPath());
            }
            @rmdir(UPDATE_TEMP);
            echo json_encode(['code' => 200, 'msg' => 'ok']);
            break;

        case 'rollback':
            $backupFile = $_SESSION['last_backup_file'] ?? '';
            if (!$backupFile || !file_exists($backupFile)) {
                throw new Exception("找不到备份文件: " . $backupFile);
            }

            $zip = new ZipArchive();
            if ($zip->open($backupFile) !== TRUE) {
                throw new Exception("无法打开备份文件: " . $backupFile);
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                $normalizedEntry = str_replace('\\', '/', $entry);

                $skip = false;
                foreach ($excludes as $exclude) {
                    if (strpos($normalizedEntry, $exclude) === 0) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip) continue;

                $target = ROOT_DIR . DIRECTORY_SEPARATOR . $entry;

                if (substr($entry, -1) === '/') {
                    if (!is_dir($target)) mkdir($target, 0755, true);
                } else {
                    $targetDir = dirname($target);
                    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
                    if (!copy("zip://{$backupFile}#{$entry}", $target)) {
                        $zip->close();
                        throw new Exception("无法恢复文件: " . $entry);
                    }
                }
            }
            $zip->close();
            echo json_encode(['code' => 200, 'msg' => '回滚成功']);
            break;

        // ── 数据库迁移 ──────────────────────────────────────────────
        case 'db_migrate':
            $migrationsDir = ROOT_DIR . '/migrations';
            // 没有迁移目录视为无需迁移（兼容旧更新包）
            if (!is_dir($migrationsDir)) {
                echo json_encode(['code' => 200, 'msg' => 'no migrations', 'data' => []]);
                break;
            }
            require_once ROOT_DIR . '/include/DbMigrator.php';
            $migrator = new DbMigrator($db, $migrationsDir);
            $pending  = $migrator->getPending();
            if (empty($pending)) {
                echo json_encode(['code' => 200, 'msg' => 'already up-to-date', 'data' => []]);
                break;
            }
            $results = $migrator->runPending();
            echo json_encode(['code' => 200, 'msg' => 'ok', 'data' => $results]);
            break;

        default:
            throw new Exception("未知的更新步骤");
    }
} catch (Exception $e) {
    echo json_encode(['code' => 500, 'msg' => $e->getMessage()]);
}