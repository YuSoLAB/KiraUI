<?php
/**
 * 迁移 013 — 新增邮件通知相关字段
 *
 * 涉及表：
 *   - comment_settings：email_notify_enabled / notify_admin / notify_guest_reply
 *   - users：notify_on_reply
 */
return [
    'description' => '新增评论邮件通知开关字段（comment_settings × 3，users × 1）',
    'up' => function (PDO $db) {
        require_once dirname(__DIR__) . '/include/DbMigrator.php';

        // comment_settings: 全局邮件通知总开关
        DbMigrator::addColumnIfNotExists(
            $db,
            'comment_settings',
            'email_notify_enabled',
            "TINYINT(1) NOT NULL DEFAULT 1 COMMENT '全局邮件通知总开关：1=开启，0=关闭'"
        );

        // comment_settings: 是否向管理员发送新评论通知
        DbMigrator::addColumnIfNotExists(
            $db,
            'comment_settings',
            'notify_admin',
            "TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否向管理员发送新评论通知：1=是，0=否'"
        );

        // comment_settings: 是否向游客邮箱发送回复通知
        DbMigrator::addColumnIfNotExists(
            $db,
            'comment_settings',
            'notify_guest_reply',
            "TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否向游客邮箱发送回复通知：1=是，0=否'"
        );

        // users: 接收回复邮件通知
        DbMigrator::addColumnIfNotExists(
            $db,
            'users',
            'notify_on_reply',
            "TINYINT(1) NOT NULL DEFAULT 1 COMMENT '接收回复邮件通知：1=接收，0=不接收'"
        );
    },
];