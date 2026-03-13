<?php
/**
 * 006_add_login_attempts.php
 *
 * 新增 login_attempts 表，支持登录图形验证码阈值与账号临时锁定功能：
 *   - 连续登录失败 >= 3 次（Session 计数）→ 强制图形验证码
 *   - 连续登录失败 >= 5 次（本表计数）   → 账号锁定 10 分钟
 *   - 登录成功后清除本表对应记录
 */
return [
    'description' => '新增 login_attempts 表（登录失败计数 & 账号临时锁定）',
    'up' => function (PDO $db) {
        require_once dirname(__DIR__) . '/include/DbMigrator.php';

        // ── 1. 建表（幂等，已存在则跳过）─────────────────────
        $db->exec("
            CREATE TABLE IF NOT EXISTS `login_attempts` (
              `id`           INT      NOT NULL AUTO_INCREMENT,
              `user_id`      INT      NOT NULL
                               COMMENT '对应 users.id',
              `attempts`     INT      NOT NULL DEFAULT 0
                               COMMENT '累计失败次数',
              `locked_until` DATETIME DEFAULT NULL
                               COMMENT '锁定到期时间，NULL 表示未锁定',
              `updated_at`   DATETIME NOT NULL
                               DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_la_user_id` (`user_id`),
              CONSTRAINT `fk_la_user`
                FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_0900_ai_ci
              COMMENT='登录失败计数与临时账号锁定'
        ");

        // ── 2. 防御：若表已存在但缺列，逐列补齐 ─────────────
        //    （极少情况：用户曾手动建过不完整的同名表）
        DbMigrator::addColumnIfNotExists(
            $db, 'login_attempts', 'attempts',
            "INT NOT NULL DEFAULT 0 COMMENT '累计失败次数'"
        );
        DbMigrator::addColumnIfNotExists(
            $db, 'login_attempts', 'locked_until',
            "DATETIME DEFAULT NULL COMMENT '锁定到期时间，NULL 表示未锁定'"
        );
        DbMigrator::addColumnIfNotExists(
            $db, 'login_attempts', 'updated_at',
            "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        );

        // ── 3. 确保唯一索引存在 ───────────────────────────────
        //    addIndexIfNotExists 不接受 UNIQUE 参数，用 INFORMATION_SCHEMA 手动判断
        $idxExists = $db->query("
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'login_attempts'
              AND INDEX_NAME   = 'uq_la_user_id'
        ")->fetchColumn();
        if (!$idxExists) {
            $db->exec(
                "ALTER TABLE `login_attempts`
                 ADD UNIQUE KEY `uq_la_user_id` (`user_id`)"
            );
        }
    },
];