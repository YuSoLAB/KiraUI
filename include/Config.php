<?php
require_once __DIR__ . '/Db.php';
class Config {
    private static $instance = null;
    private $db;
    private $cache = [];
    private function __construct() {
        $this->db = Db::getInstance();
        $this->loadAllConfigs();
    }
    public static function getInstance() {
        if (self::$instance === null || self::$instance->db === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    private function loadAllConfigs() {
        if ($this->db === null) return;
        $stmt = $this->db->query("SELECT config_key, config_value FROM system_config");
        while ($row = $stmt->fetch()) {
            $this->cache[$row['config_key']] = $row['config_value'];
        }
    }
    public function get($key, $default = '') {
        return isset($this->cache[$key]) ? $this->cache[$key] : $default;
    }
    public function getTimezone() {
        return $this->get('timezone', 'Asia/Shanghai');
    }
    public function setTimezone($timezone) {
        date_default_timezone_set($timezone);
    }
    public function getCurrentTime() {
        return new DateTime('now', new DateTimeZone($this->getTimezone()));
    }
    public function getCurrentDateTime($format = 'Y-m-d H:i:s') {
        return $this->getCurrentTime()->format($format);
    }
    public function getFutureTime($interval, $format = 'Y-m-d H:i:s') {
        $current = $this->getCurrentTime();
        $current->modify($interval);
        return $current->format($format);
    }
    public function set($key, $value) {
        $time = time();
        if ($this->db === null) throw new \RuntimeException('DB not initialized');
        $stmt = $this->db->prepare("
            INSERT INTO system_config (config_key, config_value, updated_at, created_at)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                config_value = ?,
                updated_at = ?
        ");

        // ── FIX: prepare() 可能返回 false，直接 execute 会产生致命错误 ──
        if ($stmt === false) {
            throw new RuntimeException("SQL 预处理失败，键名：{$key}，错误：" . implode(' ', $this->db->errorInfo()));
        }

        // 修改 execute 的参数，提供 6 个绑定的值以对应上方所有的问号占位符
        $result = $stmt->execute([$key, $value, $time, $time, $value, $time]);

        if ($result === false) {
            throw new RuntimeException("数据库写入失败，键名：{$key}，错误：" . implode(' ', $stmt->errorInfo()));
        }

        // ── 只在写库成功后才更新缓存 ──
        $this->cache[$key] = $value;
        return true;
    }
    public function batchSet($data) {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
    }
}