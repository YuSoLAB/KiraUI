<?php
/**
 * 迁移模板 — 新版本迁移示例
 *
 * 使用说明：
 *   1. 复制此文件，命名为 NNN_描述.php（NNN 必须大于已有编号）
 *   2. 修改 description 和 up() 函数
 *   3. 放入 ZIP 更新包的 migrations/ 目录中
 *   4. 用户更新后会自动执行，已执行过的不会重复运行
 *
 * 常用操作示例（均为幂等安全写法）：
 *
 *   // 新建表
 *   $db->exec("CREATE TABLE IF NOT EXISTS `new_table` (...) ENGINE=InnoDB");
 *
 *   // 加列
 *   DbMigrator::addColumnIfNotExists($db, 'articles', 'new_col', "varchar(100) DEFAULT NULL");
 *
 *   // 加索引
 *   DbMigrator::addIndexIfNotExists($db, 'articles', 'idx_date', '`date`');
 *
 *   // 修改列（先判断）
 *   if (DbMigrator::columnExists($db, 'users', 'old_col')) {
 *       $db->exec("ALTER TABLE `users` CHANGE `old_col` `new_col` varchar(200) NOT NULL");
 *   }
 *
 *   // 数据迁移（INSERT 前先判断）
 *   $count = $db->query("SELECT COUNT(*) FROM system_config WHERE config_key='new_key'")->fetchColumn();
 *   if ($count == 0) {
 *       $db->exec("INSERT INTO system_config (config_key, config_value, created_at, updated_at)
 *                  VALUES ('new_key', 'default_value', UNIX_TIMESTAMP(), UNIX_TIMESTAMP())");
 *   }
 */
return [
    'description' => '此处填写此次迁移的简要说明',
    'up' => function (PDO $db) {
        require_once dirname(__DIR__) . '/include/DbMigrator.php';

        // 在此编写迁移逻辑 ↓
        // DbMigrator::addColumnIfNotExists($db, 'table', 'column', 'TYPE DEFAULT ...');
    },
];