<?php
/**
 * DbMigrator — 数据库迁移执行器
 *
 * 迁移文件约定：
 *   migrations/NNN_描述.php  （NNN 三位以上数字，按字典序升序执行）
 *
 * 迁移文件格式：
 *   <?php
 *   return [
 *       'description' => '简要说明',
 *       'up' => function(PDO $db) {
 *           // 幂等 SQL，先用辅助方法判断再执行
 *       },
 *   ];
 */
class DbMigrator
{
    private PDO $db;
    private string $dir;

    public function __construct(PDO $db, string $migrationsDir)
    {
        $this->db  = $db;
        $this->dir = rtrim($migrationsDir, '/\\');
        $this->ensureTable();
    }

    /* ── 公开接口 ─────────────────────────────── */

    /** 执行所有待定迁移，返回每条执行结果 */
    public function runPending(): array
    {
        $results = [];
        foreach ($this->getPending() as $file) {
            $name = basename($file, '.php');
            try {
                // 用 static 方法隔离 require 作用域，确保 return 值能正确传出
                $migration = self::loadMigrationFile($file);
                if (!is_array($migration) || !isset($migration['up']) || !is_callable($migration['up'])) {
                    throw new \RuntimeException(
                        "迁移文件格式错误（需返回含 up 键的数组），实际返回类型：" . gettype($migration)
                    );
                }
                // 注意：MySQL DDL（CREATE TABLE / ALTER TABLE）会触发隐式提交，
                // 无法被事务包裹回滚，因此这里不使用事务。
                // 迁移执行成功后再写入 db_migrations，保证原子性（执行失败则不记录，下次仍会重试）。
                ($migration['up'])($this->db);
                $this->markApplied($name);
                $results[] = [
                    'migration'   => $name,
                    'status'      => 'ok',
                    'description' => $migration['description'] ?? '',
                ];
            } catch (\Throwable $e) {
                $results[] = ['migration' => $name, 'status' => 'error', 'msg' => $e->getMessage()];
                throw new \RuntimeException("[迁移 {$name}] " . $e->getMessage(), 0, $e);
            }
        }
        return $results;
    }

    /** 获取待执行迁移文件列表（已排序，只匹配数字开头的文件） */
    public function getPending(): array
    {
        $applied = $this->getApplied();
        // 只抓数字开头的 .php，排除 _TEMPLATE.php 等辅助文件
        $files   = glob($this->dir . DIRECTORY_SEPARATOR . '[0-9]*.php') ?: [];
        sort($files);
        return array_values(
            array_filter($files, fn($f) => !in_array(basename($f, '.php'), $applied, true))
        );
    }

    /** 已执行的迁移名列表 */
    public function getApplied(): array
    {
        return $this->db
            ->query("SELECT `migration` FROM `db_migrations` ORDER BY `id`")
            ->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * 在干净的 static 上下文中加载迁移文件。
     * 直接在实例方法里 require 时，PHP 有时会把文件内的 return
     * 当作方法 return 处理导致返回值丢失，static 方法可规避此问题。
     */
    private static function loadMigrationFile(string $path): mixed
    {
        return require $path;
    }

    /* ── 迁移文件内可用的静态辅助方法 ────────── */

    /** 判断表是否存在 */
    public static function tableExists(PDO $db, string $table): bool
    {
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** 判断列是否存在 */
    public static function columnExists(PDO $db, string $table, string $column): bool
    {
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** 判断索引是否存在 */
    public static function indexExists(PDO $db, string $table, string $indexName): bool
    {
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?"
        );
        $stmt->execute([$table, $indexName]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * 安全添加列：列不存在时才执行 ALTER TABLE
     * @param string $afterColumn  指定插入到哪列之后，留空则追加末尾
     */
    public static function addColumnIfNotExists(
        PDO    $db,
        string $table,
        string $column,
        string $definition,
        string $afterColumn = ''
    ): void {
        if (self::columnExists($db, $table, $column)) return;
        $after = $afterColumn ? " AFTER `{$afterColumn}`" : '';
        $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}{$after}");
    }

    /**
     * 安全添加索引：索引不存在时才执行 ALTER TABLE
     * @param string $type  INDEX | UNIQUE | FULLTEXT
     */
    public static function addIndexIfNotExists(
        PDO    $db,
        string $table,
        string $indexName,
        string $columns,
        string $type = 'INDEX'
    ): void {
        if (self::indexExists($db, $table, $indexName)) return;
        $db->exec("ALTER TABLE `{$table}` ADD {$type} `{$indexName}` ({$columns})");
    }

    /* ── 私有 ─────────────────────────────────── */

    private function ensureTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS `db_migrations` (
                `id`          int          NOT NULL AUTO_INCREMENT,
                `migration`   varchar(255) NOT NULL,
                `applied_at`  datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function markApplied(string $name): void
    {
        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO `db_migrations` (`migration`) VALUES (?)"
        );
        $stmt->execute([$name]);
    }
}