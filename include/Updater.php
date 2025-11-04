<?php
class Updater {
    private $currentVersion;
    private $updateInfoUrl;
    private $dbConfigFile;
    private $progressFile;
    private $excludedFiles = [
        'include/Db.php',
        'cache/',
        'img/',
    ];

    private $cacheWhitelist = [
        'cache/FileCache.php',
        'cache/ArticleIndex.php'
    ];

    public function __construct($updateInfoUrl = 'https://www.yusolab.com/info.json') {
        $this->updateInfoUrl = $updateInfoUrl;
        $this->dbConfigFile = ROOT_DIR . '/include/Db.php';
        $this->progressFile = ROOT_DIR . '/temp_update/progress.json';
        $versionData = include ROOT_DIR . '/version.php';
        $this->currentVersion = $versionData['version'];
    }

    private function updateProgress($step, $message, $percentage = 0) {
        $progress = [
            'step' => $step,
            'message' => $message,
            'percentage' => $percentage,
            'timestamp' => time()
        ];
        
        if (!file_exists(dirname($this->progressFile))) {
            mkdir(dirname($this->progressFile), 0755, true);
        }
        
        file_put_contents($this->progressFile, json_encode($progress));
    }

    public function getProgress() {
        if (file_exists($this->progressFile)) {
            $content = file_get_contents($this->progressFile);
            return json_decode($content, true);
        }
        return ['step' => 0, 'message' => '未开始', 'percentage' => 0];
    }

    public function checkForUpdates() {
        try {
            $ch = curl_init($this->updateInfoUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            curl_close($ch);

            if (!$response) {
                return ['success' => false, 'message' => '无法连接到更新服务器'];
            }
            $updateInfo = json_decode($response, true);            
            if (!$updateInfo || !isset($updateInfo['version'])) {
                return ['success' => false, 'message' => '无效的更新信息'];
            }
            $needsUpdate = version_compare($updateInfo['version'], $this->currentVersion, '>');     
            return [
                'success' => true,
                'has_update' => $needsUpdate,
                'current_version' => $this->currentVersion,
                'latest_version' => $updateInfo['version'],
                'changelog' => $updateInfo['changelog'] ?? '',
                'download_url' => $updateInfo['download_url'] ?? ''
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function getAllFiles($dir) {
        $files = [];
        $dir = rtrim($dir, '/') . '/';
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $relativePath = str_replace($dir, '', $item->getPathname());
                $relativePath = str_replace('\\', '/', $relativePath);
                $files[] = $relativePath;
            }
        }
        return $files;
    }

    private function cleanupObsoleteFiles($newFiles, $targetDir) {
        $targetDir = rtrim($targetDir, '/') . '/';
        $oldFiles = $this->getAllFiles($targetDir);
        $obsoleteFiles = array_diff($oldFiles, $newFiles);
        foreach ($obsoleteFiles as $file) {
            if ($this->shouldExclude($file)) {
                continue;
            }
            $fullPath = $targetDir . $file;
            if (file_exists($fullPath) && is_file($fullPath)) {
                if (!@unlink($fullPath)) {
                    error_log("无法删除废弃文件: {$fullPath}");
                } else {
                    error_log("已删除废弃文件: {$fullPath}");
                }
            }
        }
        $this->cleanupEmptyDirectories($targetDir);
    }

    private function cleanupEmptyDirectories($dir) {
        $dir = rtrim($dir, '/') . '/';
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        $items = array_diff($items, ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . $item;
            if (is_dir($path)) {
                $this->cleanupEmptyDirectories($path);
            }
        }
        $items = scandir($dir);
        $items = array_diff($items, ['.', '..']);
        if (empty($items)) {
            @rmdir($dir);
        }
    }

    public function performUpdate($downloadUrl) {
        $this->updateProgress(0, '开始准备更新', 0);
        set_time_limit(300);
        try {
            $tempDir = ROOT_DIR . '/temp_update/';
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
                $this->updateProgress(1, '已创建临时目录', 5);
            }
            $zipFile = $tempDir . 'update.zip';
            $this->updateProgress(2, '开始下载更新包', 10);
            $this->downloadFile($downloadUrl, $zipFile);
            $this->updateProgress(3, '更新包下载完成', 30);
            $this->updateProgress(4, '开始解压更新包', 35);
            $this->extractZip($zipFile, $tempDir . 'extract/');
            $this->updateProgress(5, '更新包解压完成', 50);
            $extractDir = $tempDir . 'extract/';
            $items = scandir($extractDir);
            $validItems = array_filter($items, function($item) {
                return $item !== '.' && $item !== '..';
            });
            if (count($validItems) === 1) {
                $firstItem = $extractDir . reset($validItems);
                if (is_dir($firstItem)) {
                    $extractDir = $firstItem . '/';
                }
            }
            $this->updateProgress(6, '准备安装更新文件', 60);
            $this->copyUpdatedFiles($extractDir, ROOT_DIR . '/');
            $this->updateProgress(7, '更新文件复制完成', 75);
            $newFiles = $this->getAllFiles($extractDir);
            $this->cleanupObsoleteFiles($newFiles, ROOT_DIR . '/');
            $this->updateProgress(8, '检查并执行数据库迁移', 85);
            $possibleMigrationPaths = [
                $extractDir . 'database/migrate.php',
                $extractDir . 'migrate.php', 
                $tempDir . 'extract/database/migrate.php',
                $tempDir . 'extract/migrate.php'
            ];
            $migrationFileFound = false;
            foreach ($possibleMigrationPaths as $migrationPath) {
                if (file_exists($migrationPath)) {
                    $this->runMigrations($migrationPath);
                    $migrationFileFound = true;
                    $this->updateProgress(8, '数据库迁移执行完成', 90);
                    break;
                }
            }

            if (!$migrationFileFound) {
                $this->updateProgress(8, '未找到数据库迁移文件，跳过此步骤', 90);
            }
            $this->updateVersionFile($tempDir . 'extract/version.php');
            $this->updateProgress(9, '更新完成', 100);
            $this->cleanup($tempDir);
            @unlink($this->progressFile);
             return ['success' => true, 'message' => '更新成功'];
        } catch (Exception $e) {
            $this->updateProgress(-1, '更新失败: ' . $e->getMessage(), 0);
            return ['success' => false, 'message' => '更新失败: ' . $e->getMessage()];
        }
    }

    private function downloadFile($url, $destination) {
        $ch = curl_init($url);
        $file = fopen($destination, 'w');      
        curl_setopt($ch, CURLOPT_FILE, $file);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);        
        $result = curl_exec($ch);
        fclose($file);
        curl_close($ch);
        if (!$result || !file_exists($destination)) {
            throw new Exception('无法下载更新包');
        }
    }

    private function extractZip($zipFile, $destination) {
        $zip = new ZipArchive;
        if ($zip->open($zipFile) !== true) {
            throw new Exception('无法打开更新包');
        }

        if (!$zip->extractTo($destination)) {
            $zip->close();
            throw new Exception('无法解压更新包');
        }
        $zip->close();
    }

    private function copyUpdatedFiles($sourceDir, $destDir) {
        $dir = new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            $relativePath = $iterator->getSubPathName();
            if ($this->shouldExclude($relativePath)) {
                continue;
            }
            $source = $sourceDir . '/' . $relativePath;
            $dest = $destDir . '/' . $relativePath;
            if ($item->isDir()) {
                if (!file_exists($dest)) {
                    mkdir($dest, 0755, true);
                }
            } else {
                $destDirPath = dirname($dest);
                if (!file_exists($destDirPath)) {
                    mkdir($destDirPath, 0755, true);
                }
                if (!copy($source, $dest)) {
                    throw new Exception("无法复制文件: {$relativePath}");
                }
            }
        }
    }

    private function shouldExclude($path) {
        $path = str_replace('\\', '/', $path);        
        foreach ($this->excludedFiles as $exclude) {
            if (strpos($path, $exclude) === 0) {
                if ($exclude === 'cache/') {
                    foreach ($this->cacheWhitelist as $whitelistItem) {
                        if ($path === $whitelistItem) {
                            return false;
                        }
                    }
                    return true;
                }
                return true;
            }
        }
        return false;
    }

    private function runMigrations($migrationFile) {
        try {
            if (!file_exists($migrationFile)) {
                error_log("迁移文件不存在: " . $migrationFile);
                return;
            }
            $dbConfig = '';
            if (file_exists($this->dbConfigFile)) {
                $dbConfig = file_get_contents($this->dbConfigFile);
            }
            error_log("开始执行数据库迁移: " . $migrationFile);
            include $migrationFile;
            error_log("数据库迁移执行完成");
            if ($dbConfig && file_exists($this->dbConfigFile)) {
                file_put_contents($this->dbConfigFile, $dbConfig);
            }
            
        } catch (Exception $e) {
            error_log("数据库迁移失败: " . $e->getMessage());
            throw new Exception("数据库迁移执行失败: " . $e->getMessage());
        }
    }

    private function updateVersionFile($newVersionFile) {
        if (file_exists($newVersionFile)) {
            $versionData = include $newVersionFile;
            $versionData['last_check'] = time();
            file_put_contents(
                ROOT_DIR . '/version.php',
                "<?php\nreturn " . var_export($versionData, true) . ";\n"
            );
        }
    }

    private function cleanup($tempDir) {
        $this->deleteDirectory($tempDir);
    }

    public static function manualCleanup() {
        $tempDir = ROOT_DIR . '/temp_update/';
        $updater = new self();
        $updater->deleteDirectory($tempDir);
        @unlink(ROOT_DIR . '/temp_update/progress.json');
        return ['success' => true, 'message' => '临时文件清理完成'];
    }

    private function deleteDirectory($dir) {
        if (!file_exists($dir)) return true;
        if (!is_dir($dir)) return unlink($dir);
        
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') continue;
            if (!$this->deleteDirectory($dir . '/' . $item)) return false;
        }
        
        return rmdir($dir);
    }
}