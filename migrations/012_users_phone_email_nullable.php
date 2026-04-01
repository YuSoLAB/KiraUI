<?php
/**
 * 迁移 012 — 将 users 表的 phone / email 改为允许 NULL
 *
 * 背景：
 *   原字段定义为 NOT NULL，导致单一登录模式下另一字段必须填入空字符串，
 *   与 UNIQUE 索引产生冲突。改为 NULL 后，MySQL 对多个 NULL 值不会触发
 *   唯一约束，phone 模式下 email=NULL、email 模式下 phone=NULL 均可共存。
 *
 * 变更内容：
 *   1. MODIFY phone  → VARCHAR(20)  NULL DEFAULT NULL
 *   2. MODIFY email  → VARCHAR(255) NULL DEFAULT NULL
 *   3. 将历史存量空字符串修正为 NULL，避免干扰唯一约束
 */
return [
    'description' => '将 users 表的 phone / email 字段改为允许 NULL，并清理历史空字符串数据',
    'up' => function (PDO $db) {
        require_once dirname(__DIR__) . '/include/DbMigrator.php';

        // 1. 将 phone、email 改为 NULL 允许
        //    MODIFY COLUMN 本身幂等：重复执行只是再套一次相同定义，无副作用
        $db->exec("
            ALTER TABLE `users`
                MODIFY COLUMN `phone` VARCHAR(20)  NULL DEFAULT NULL COMMENT '手机号（可为空）',
                MODIFY COLUMN `email` VARCHAR(255) NULL DEFAULT NULL COMMENT '邮箱（可为空）'
        ");

        // 2. 清理历史空字符串 → NULL
        //    WHERE 条件保证只影响确实为空字符串的行，幂等安全
        $db->exec("UPDATE `users` SET `phone` = NULL WHERE `phone` = ''");
        $db->exec("UPDATE `users` SET `email` = NULL WHERE `email` = ''");
    },
];