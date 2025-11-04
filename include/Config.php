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
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadAllConfigs() {
        $stmt = $this->db->query("SELECT config_key, config_value FROM system_config");
        while ($row = $stmt->fetch()) {
            $this->cache[$row['config_key']] = $row['config_value'];
        }
    }

    public function get($key, $default = '') {
        return isset($this->cache[$key]) ? $this->cache[$key] : $default;
    }

    public function set($key, $value) {
        $this->cache[$key] = $value;
        $time = time();
        
        $stmt = $this->db->prepare("
            INSERT INTO system_config (config_key, config_value, updated_at, created_at)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                config_value = VALUES(config_value),
                updated_at = VALUES(updated_at)
        ");
        
        return $stmt->execute([$key, $value, $time, $time]);
    }

    public function batchSet($data) {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
    }
}