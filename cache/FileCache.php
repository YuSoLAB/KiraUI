<?php
class FileCache {
    private $cache_dir;
    private $expire_time;
    
    public function __construct($cache_dir = null, $expire_time = 3600) {
        $this->cache_dir = $cache_dir ?: dirname(__DIR__) . '/cache/data';
        $this->expire_time = $expire_time;
        if (!file_exists($this->cache_dir)) {
            if (!mkdir($this->cache_dir, 0755, true)) {
                throw new Exception("无法创建缓存目录: " . $this->cache_dir);
            }
        }
    }
    
    public function get($key) {
        try {
            $filename = $this->getFilename($key);            
            if (!file_exists($filename)) {
                return false;
            }
            
            if (time() - filemtime($filename) > $this->expire_time) {
                @unlink($filename);
                return false;
            }
            
            $data = file_get_contents($filename);
            if ($data === false) {
                return false;
            }         
            $cache_data = unserialize($data);
            if ($cache_data === false || !is_array($cache_data)) {
                @unlink($filename);
                return false;
            }
            
            return $cache_data['data'] ?? false;
            
        } catch (Exception $e) {
            error_log("缓存获取错误: " . $e->getMessage());
            return false;
        }
    }
    
    public function set($key, $data, $custom_expire = null) {
        try {
            $filename = $this->getFilename($key);
            $expire = $custom_expire ?: $this->expire_time;
            
            $cache_data = [
                'data' => $data,
                'expire' => time() + $expire,
                'created' => time()
            ];
            
            $result = file_put_contents($filename, serialize($cache_data), LOCK_EX);
            return $result !== false;
            
        } catch (Exception $e) {
            error_log("缓存设置错误: " . $e->getMessage());
            return false;
        }
    }
    
    public function delete($key) {
        $filename = $this->getFilename($key);
        if (file_exists($filename)) {
            return @unlink($filename);
        }
        return true;
    }
    
    public function clear() {
        try {
            $files = glob($this->cache_dir . '/*.cache');
            $success = true;
            foreach ($files as $file) {
                if (is_file($file)) {
                    if (!@unlink($file)) {
                        $success = false;
                    }
                }
            }
            return $success;
        } catch (Exception $e) {
            error_log("缓存清理错误: " . $e->getMessage());
            return false;
        }
    }
    
    public function clearExpired() {
        try {
            $files = glob($this->cache_dir . '/*.cache');
            foreach ($files as $file) {
                if (is_file($file) && (time() - filemtime($file) > $this->expire_time)) {
                    @unlink($file);
                }
            }
            return true;
        } catch (Exception $e) {
            error_log("过期缓存清理错误: " . $e->getMessage());
            return false;
        }
    }
    
    private function getFilename($key) {
        return $this->cache_dir . '/' . md5($key) . '.cache';
    }

    public function getStats() {
        try {
            $files = glob($this->cache_dir . '/*.cache');
            $totalFiles = 0;
            $activeFiles = 0;
            $totalSize = 0;            
            if ($files) {
                $totalFiles = count($files);                
                foreach ($files as $file) {
                    if (is_file($file)) {
                        $totalSize += filesize($file);效
                        $data = @unserialize(file_get_contents($file));
                        if ($data && isset($data['expire']) && $data['expire'] > time()) {
                            $activeFiles++;
                        }
                    }
                }
            }
            
            if ($totalSize >= 1048576) {
                $formattedSize = round($totalSize / 1048576, 2) . ' MB';
            } else {
                $formattedSize = round($totalSize / 1024, 2) . ' KB';
            }
            
            return [
                'total_files' => $totalFiles,
                'active_files' => $activeFiles,
                'total_size' => $formattedSize
            ];
        } catch (Exception $e) {
            error_log("获取缓存统计错误: " . $e->getMessage());
            return [
                'total_files' => 0,
                'active_files' => 0,
                'total_size' => '0 KB'
            ];
        }
    }
}