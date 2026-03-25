<?php
/**
 * 010_email_verification.php
 * 新增邮箱验证码表，并为 users 表增加 email_verified 字段
 */
return [
    'description' => '新增 email_verification 表；users 表增加 email_verified 字段',
    'up' => function (PDO $db) {
        require_once dirname(__DIR__) . '/include/DbMigrator.php';

        // 1. users 表加列：email_verified
        DbMigrator::addColumnIfNotExists(
            $db,
            'users',
            'email_verified',
            "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '邮箱已验证' AFTER `email`"
        );

        // 2. 新建 email_verification 表（含 sent_at / verified，建表即包含，无需单独 ALTER）
        $db->exec("
            CREATE TABLE IF NOT EXISTS `email_verification` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `email`      VARCHAR(255) NOT NULL COMMENT '目标邮箱',
                `code`       CHAR(6)      NOT NULL COMMENT '6位数字验证码',
                `verified`   TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '0=未使用 1=已使用',
                `expires_at` DATETIME     NOT NULL COMMENT '过期时间',
                `sent_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '发送时间',
                PRIMARY KEY (`id`),
                KEY `idx_email_verified` (`email`, `verified`),
                KEY `idx_expires`        (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 3. 容错：若表已存在但缺少 sent_at / verified（旧环境兼容），补上
        DbMigrator::addColumnIfNotExists(
            $db,
            'email_verification',
            'sent_at',
            "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '发送时间'"
        );
        DbMigrator::addColumnIfNotExists(
            $db,
            'email_verification',
            'verified',
            "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=未使用 1=已使用'"
        );
    },
];