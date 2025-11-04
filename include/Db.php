<?php
class Db {
    private static $instance = null;
    private $conn;
    
    // 数据库配置（默认值，将被初始化程序覆盖）
    private $host = 'localhost';
    private $db   = 'yusolab';
    private $user = 'root';
    private $pass = '';
    private $charset = 'utf8mb4';

    private function __construct() {
        $dsn = "mysql:host=$this->host;dbname=$this->db;charset=$this->charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        
        try {
            $this->conn = new PDO($dsn, $this->user, $this->pass, $options);
            $this->conn->exec("SET NAMES {$this->charset} COLLATE utf8mb4_0900_ai_ci");
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->conn;
    }
}