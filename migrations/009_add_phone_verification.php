<?php
/**
 * 009_add_phone_verification.php
 * 新增手机短信验证码表，并为 users 表补加 phone_verified 列。
 */
return [
    'description' => '新增 phone_verification 表；users 表补加 phone_verified 列',
    'up' => function (PDO $db) {
        require_once dirname(__DIR__) . '/include/DbMigrator.php';

        // 1. 创建短信验证码记录表
        $db->exec("
            CREATE TABLE IF NOT EXISTS `phone_verification` (
              `id`         INT         NOT NULL AUTO_INCREMENT,
              `phone`      VARCHAR(20) NOT NULL               COMMENT '手机号',
              `code`       VARCHAR(10) NOT NULL               COMMENT '验证码明文（由阿里云返回）',
              `biz_id`     VARCHAR(64) DEFAULT NULL           COMMENT '阿里云业务 ID',
              `sent_at`    DATETIME    DEFAULT CURRENT_TIMESTAMP,
              `expires_at` DATETIME    NOT NULL               COMMENT '过期时间',
              `verified`   TINYINT(1)  NOT NULL DEFAULT 0     COMMENT '是否已使用',
              PRIMARY KEY (`id`),
              KEY `idx_phone`   (`phone`),
              KEY `idx_expires` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
              COMMENT='手机短信验证码记录'
        ");

        // 2. users 表补加 phone_verified 列（已存在则跳过）
        DbMigrator::addColumnIfNotExists(
            $db,
            'users',
            'phone_verified',
            "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '手机号是否已完成短信验证'"
        );
    },
];