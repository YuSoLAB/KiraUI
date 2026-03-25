<?php
/**
 * 007_add_user_badges.php — 用户认证角标与头衔表
 *
 * 变更内容：
 *   - 新建 user_badges 表，存储每位用户的角标类型、颜色和头衔配置
 *
 * 幂等保证：
 *   - 建表使用 CREATE TABLE IF NOT EXISTS，已有表不重建
 *   - 各字段通过 DbMigrator::addColumnIfNotExists 补齐，不破坏既有数据
 *   - 唯一索引通过 DbMigrator::addIndexIfNotExists 添加，已有则跳过
 */
return [
    'description' => '新增 user_badges 表：用户认证角标与头衔配置',
    'up' => function (PDO $db) {
        require_once dirname(__DIR__) . '/include/DbMigrator.php';

        // ── Step 1：建表（含最小字段集） ─────────────────────────
        $db->exec("
            CREATE TABLE IF NOT EXISTS `user_badges` (
                `id`               INT(11)      NOT NULL AUTO_INCREMENT,
                `user_id`          INT(11)      NOT NULL,
                `badge_type`       VARCHAR(30)  NOT NULL DEFAULT 'verified',
                `badge_color`      VARCHAR(20)  NOT NULL DEFAULT '#1d9bf0',
                `badge_icon_color` VARCHAR(20)  NOT NULL DEFAULT '#ffffff',
                `title_text`       VARCHAR(100) NOT NULL DEFAULT '',
                `title_color`      VARCHAR(20)  NOT NULL DEFAULT '#6c5dfb',
                `title_bg_color`   VARCHAR(20)  NOT NULL DEFAULT '',
                `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
                `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='用户认证角标与头衔配置'
        ");

        // ── Step 2：补齐各列（表已存在时的升级路径） ─────────────
        DbMigrator::addColumnIfNotExists($db, 'user_badges', 'badge_type',
            "VARCHAR(30) NOT NULL DEFAULT 'verified' AFTER `user_id`");

        DbMigrator::addColumnIfNotExists($db, 'user_badges', 'badge_color',
            "VARCHAR(20) NOT NULL DEFAULT '#1d9bf0' AFTER `badge_type`");

        DbMigrator::addColumnIfNotExists($db, 'user_badges', 'badge_icon_color',
            "VARCHAR(20) NOT NULL DEFAULT '#ffffff' AFTER `badge_color`");

        DbMigrator::addColumnIfNotExists($db, 'user_badges', 'title_text',
            "VARCHAR(100) NOT NULL DEFAULT '' AFTER `badge_icon_color`");

        DbMigrator::addColumnIfNotExists($db, 'user_badges', 'title_color',
            "VARCHAR(20) NOT NULL DEFAULT '#6c5dfb' AFTER `title_text`");

        DbMigrator::addColumnIfNotExists($db, 'user_badges', 'title_bg_color',
            "VARCHAR(20) NOT NULL DEFAULT '' AFTER `title_color`");

        DbMigrator::addColumnIfNotExists($db, 'user_badges', 'is_active',
            "TINYINT(1) NOT NULL DEFAULT 1 AFTER `title_bg_color`");

        DbMigrator::addColumnIfNotExists($db, 'user_badges', 'created_at',
            "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `is_active`");

        DbMigrator::addColumnIfNotExists($db, 'user_badges', 'updated_at',
            "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`");

        // ── Step 3：唯一索引（每个 user_id 只能有一条配置） ──────
        DbMigrator::addIndexIfNotExists($db, 'user_badges', 'uq_user_badge', '`user_id`', 'UNIQUE');

        // ── Step 4：外键（users 表存在才添加，避免裸安装顺序问题） ─
        $fkExists = $db->query("
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME        = 'user_badges'
               AND CONSTRAINT_NAME   = 'fk_ub_user'
               AND CONSTRAINT_TYPE   = 'FOREIGN KEY'
        ")->fetchColumn();

        $usersExists = $db->query("
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'users'
        ")->fetchColumn();

        if (!$fkExists && $usersExists) {
            $db->exec("
                ALTER TABLE `user_badges`
                    ADD CONSTRAINT `fk_ub_user`
                    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
                    ON DELETE CASCADE
            ");
        }
    },
];