<?php
/**
 * 迁移 008 — 给 comments 表补充 user_id 字段
 *
 * 背景：
 *   comments 表原始结构缺少 user_id 列，导致登录用户发表评论时无法关联
 *   用户身份，进而导致认证角标与头衔在评论区完全无法显示。
 *
 * 变更内容：
 *   1. comments 表新增 user_id INT DEFAULT NULL（访客为 NULL，登录用户存 users.id）
 *   2. 新增索引 idx_comments_user_id 加速按用户查询评论
 *   3. 存量数据补全：按邮箱匹配 users 表，自动回填已有评论的 user_id
 */
return [
    'description' => 'comments 表新增 user_id 字段，并按邮箱回填存量数据，修复角标与头衔无法显示问题',
    'up' => function (PDO $db) {
        require_once dirname(__DIR__) . '/include/DbMigrator.php';

        // 1. 新增 user_id 列（列已存在则跳过，绝不重建）
        DbMigrator::addColumnIfNotExists(
            $db,
            'comments',
            'user_id',
            "INT DEFAULT NULL COMMENT '登录用户ID（关联 users.id），访客评论为 NULL' AFTER `parent_id`"
        );

        // 2. 新增索引（索引已存在则跳过）
        DbMigrator::addIndexIfNotExists(
            $db,
            'comments',
            'idx_comments_user_id',
            '`user_id`'
        );

        // 3. 存量数据回填：将 user_id 为 NULL 的历史评论按邮箱匹配注册用户
        //    只更新能匹配到 users.email 的行，访客评论继续保持 NULL
        $db->exec("
            UPDATE `comments` c
            JOIN `users` u ON u.`email` = c.`email`
            SET c.`user_id` = u.`id`
            WHERE c.`user_id` IS NULL
        ");
    },
];