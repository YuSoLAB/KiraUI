<?php
/**
 * 迁移 005 — 信息变更审核功能
 *
 * 变更内容：
 *   1. 新建 pending_profile_changes 表（用户头像/昵称变更审核队列）
 *   2. 在 system_config 中插入 profile_review_enabled 默认配置项（默认关闭）
 */
return [
    'description' => '新增信息变更审核队列表及后台开关配置项',
    'up' => function (PDO $db) {
        require_once dirname(__DIR__) . '/include/DbMigrator.php';

        // ── 1. 创建 pending_profile_changes 表 ──────────────────────
        $db->exec("
            CREATE TABLE IF NOT EXISTS `pending_profile_changes` (
                `id`            int           NOT NULL AUTO_INCREMENT,
                `user_id`       int           NOT NULL COMMENT '申请变更的用户 ID',
                `type`          enum('nickname','avatar') NOT NULL COMMENT '变更类型',
                `new_value`     varchar(255)  NOT NULL  COMMENT '新昵称文本 或 待审头像文件名',
                `status`        enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                `reject_reason` varchar(255)  DEFAULT NULL COMMENT '拒绝原因（可选）',
                `created_at`    datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `reviewed_at`   datetime      DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_user`   (`user_id`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
              COMMENT='用户头像/昵称变更审核队列'
        ");

        // ── 2. 插入 profile_review_enabled 配置项（若不存在）────────
        $exists = (int) $db->query(
            "SELECT COUNT(*) FROM system_config WHERE config_key = 'profile_review_enabled'"
        )->fetchColumn();

        if ($exists === 0) {
            $db->exec("
                INSERT INTO system_config (config_key, config_value, updated_at, created_at)
                VALUES ('profile_review_enabled', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP())
            ");
        }
    },
];