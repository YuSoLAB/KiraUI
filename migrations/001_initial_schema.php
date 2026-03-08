<?php
/**
 * 迁移 001 — 基准 Schema
 *
 * 包含系统当前所有表的 CREATE TABLE IF NOT EXISTS，以及各列的安全补全。
 * 对全新安装：建全所有表。
 * 对已有安装：IF NOT EXISTS 保证不重建，逐列补全保证不丢数据。
 *
 * 注意：DbMigrator 类在此文件被加载前已由主程序 require，无需再次引入。
 */
return [
    'description' => '基准 Schema：建表 + 安全补全所有字段',
    'up' => function (PDO $db) {

        // ════════════════════════════════════════════════════════════
        // 1. articles
        // ════════════════════════════════════════════════════════════
        $db->exec("CREATE TABLE IF NOT EXISTS `articles` (
            `id`          int          NOT NULL AUTO_INCREMENT,
            `title`       varchar(255) NOT NULL,
            `excerpt`     text,
            `content`     longtext     NOT NULL,
            `date`        date         NOT NULL,
            `tags`        text,
            `word_count`  int          NOT NULL DEFAULT '0',
            `read_time`   int          NOT NULL DEFAULT '0',
            `created_at`  datetime     DEFAULT CURRENT_TIMESTAMP,
            `updated_at`  datetime     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `created_by`  int          DEFAULT NULL,
            `author`      varchar(255) NOT NULL DEFAULT '',
            `author_email` varchar(255) DEFAULT NULL COMMENT '作者邮箱',
            `cover_image` varchar(500) DEFAULT NULL COMMENT '封面图路径',
            PRIMARY KEY (`id`),
            KEY `created_by` (`created_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DbMigrator::addColumnIfNotExists($db, 'articles', 'author',       "varchar(255) NOT NULL DEFAULT ''",    'created_by');
        DbMigrator::addColumnIfNotExists($db, 'articles', 'author_email', "varchar(255) DEFAULT NULL",           'author');
        DbMigrator::addColumnIfNotExists($db, 'articles', 'cover_image',  "varchar(500) DEFAULT NULL",           'author_email');

        // ════════════════════════════════════════════════════════════
        // 2. article_index
        // ════════════════════════════════════════════════════════════
        $db->exec("CREATE TABLE IF NOT EXISTS `article_index` (
            `id`          int          NOT NULL,
            `title`       varchar(255) NOT NULL,
            `date`        date         NOT NULL,
            `excerpt`     text,
            `tags`        text,
            `word_count`  int          NOT NULL DEFAULT '0',
            `read_time`   int          NOT NULL DEFAULT '0',
            `modified`    timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `author_email` varchar(255) DEFAULT '' COMMENT '作者邮箱',
            `created_by`  int          DEFAULT NULL COMMENT '作者ID',
            `cover_image` varchar(500) DEFAULT NULL COMMENT '封面图路径',
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DbMigrator::addColumnIfNotExists($db, 'article_index', 'author_email', "varchar(255) DEFAULT ''",  'modified');
        DbMigrator::addColumnIfNotExists($db, 'article_index', 'created_by',   "int DEFAULT NULL",         'author_email');
        DbMigrator::addColumnIfNotExists($db, 'article_index', 'cover_image',  "varchar(500) DEFAULT NULL",'created_by');

        // ════════════════════════════════════════════════════════════
        // 3. comments
        // ════════════════════════════════════════════════════════════
        $db->exec("CREATE TABLE IF NOT EXISTS `comments` (
            `id`         int          NOT NULL AUTO_INCREMENT,
            `article_id` int          NOT NULL,
            `parent_id`  int          DEFAULT NULL,
            `name`       varchar(100) NOT NULL,
            `email`      varchar(100) NOT NULL,
            `email_hash` varchar(32)  NOT NULL DEFAULT '',
            `content`    text         NOT NULL,
            `created_at` datetime     DEFAULT CURRENT_TIMESTAMP,
            `approved`   tinyint(1)   DEFAULT '0',
            `moderation` enum('strict','auto') DEFAULT 'strict',
            PRIMARY KEY (`id`),
            KEY `article_id` (`article_id`),
            KEY `fk_comments_parent` (`parent_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DbMigrator::addColumnIfNotExists($db, 'comments', 'email_hash', "varchar(32) NOT NULL DEFAULT ''",        'email');
        DbMigrator::addColumnIfNotExists($db, 'comments', 'approved',   "tinyint(1) DEFAULT '0'",                 'created_at');
        DbMigrator::addColumnIfNotExists($db, 'comments', 'moderation', "enum('strict','auto') DEFAULT 'strict'", 'approved');

        // ════════════════════════════════════════════════════════════
        // 4. comment_settings
        // ════════════════════════════════════════════════════════════
        $db->exec("CREATE TABLE IF NOT EXISTS `comment_settings` (
            `id`                   int  NOT NULL AUTO_INCREMENT,
            `email_mode`           enum('all','whitelist','blacklist') DEFAULT 'all',
            `allowed_domains`      text,
            `blocked_domains`      text,
            `default_moderation`   enum('strict','auto') DEFAULT 'strict',
            `enable_comments`      tinyint(1) DEFAULT '1',
            `allow_guest_comments` tinyint(1) NOT NULL DEFAULT '1',
            `updated_at`           datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DbMigrator::addColumnIfNotExists($db, 'comment_settings', 'allow_guest_comments', "tinyint(1) NOT NULL DEFAULT '1'", 'enable_comments');

        // ════════════════════════════════════════════════════════════
        // 5. drafts
        // ════════════════════════════════════════════════════════════
        $db->exec("CREATE TABLE IF NOT EXISTS `drafts` (
            `id`          int          NOT NULL AUTO_INCREMENT,
            `title`       varchar(255) NOT NULL,
            `excerpt`     text,
            `content`     longtext     NOT NULL,
            `date`        date         NOT NULL,
            `tags`        text,
            `word_count`  int          NOT NULL DEFAULT '0',
            `read_time`   int          NOT NULL DEFAULT '0',
            `cover_image` varchar(500) DEFAULT NULL,
            `created_at`  datetime     DEFAULT CURRENT_TIMESTAMP,
            `updated_at`  datetime     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `created_by`  int          DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `created_by` (`created_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DbMigrator::addColumnIfNotExists($db, 'drafts', 'cover_image', "varchar(500) DEFAULT NULL", 'read_time');

        // ════════════════════════════════════════════════════════════
        // 6. email_moderation
        // ════════════════════════════════════════════════════════════
        $db->exec("CREATE TABLE IF NOT EXISTS `email_moderation` (
            `email_hash` varchar(32)            NOT NULL,
            `moderation` enum('strict','auto')  DEFAULT 'strict',
            `updated_at` timestamp              NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`email_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ════════════════════════════════════════════════════════════
        // 7. email_verification
        // ════════════════════════════════════════════════════════════
        $db->exec("CREATE TABLE IF NOT EXISTS `email_verification` (
            `id`         int          NOT NULL AUTO_INCREMENT,
            `email`      varchar(255) NOT NULL,
            `code`       varchar(6)   NOT NULL,
            `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `expires_at` datetime     NOT NULL,
            PRIMARY KEY (`id`),
            KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ════════════════════════════════════════════════════════════
        // 8. nav_menus
        // ════════════════════════════════════════════════════════════
        $db->exec("CREATE TABLE IF NOT EXISTS `nav_menus` (
            `id`           int          NOT NULL AUTO_INCREMENT,
            `label`        varchar(100) NOT NULL,
            `url`          varchar(500) NOT NULL DEFAULT '#',
            `parent_id`    int          DEFAULT NULL,
            `sort_order`   int          NOT NULL DEFAULT '0',
            `open_new_tab` tinyint(1)   NOT NULL DEFAULT '0',
            `is_active`    tinyint(1)   NOT NULL DEFAULT '1',
            `icon`         varchar(50)  DEFAULT NULL,
            `created_at`   datetime     DEFAULT CURRENT_TIMESTAMP,
            `updated_at`   datetime     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `parent_id`  (`parent_id`),
            KEY `sort_order` (`sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ════════════════════════════════════════════════════════════
        // 9. notifications
        // ════════════════════════════════════════════════════════════
        $db->exec("CREATE TABLE IF NOT EXISTS `notifications` (
            `id`         int  NOT NULL AUTO_INCREMENT,
            `user_id`    int  NOT NULL,
            `type`       enum('reply') NOT NULL DEFAULT 'reply',
            `comment_id` int  NOT NULL,
            `article_id` int  NOT NULL,
            `is_read`    tinyint(1) NOT NULL DEFAULT '0',
            `created_at` datetime   NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_user_read`    (`user_id`, `is_read`),
            KEY `fk_notif_comment` (`comment_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='评论回复通知'");

        // ════════════════════════════════════════════════════════════
        // 10. password_reset
        // ════════════════════════════════════════════════════════════
        $db->exec("CREATE TABLE IF NOT EXISTS `password_reset` (
            `id`         int          NOT NULL AUTO_INCREMENT,
            `user_id`    int          NOT NULL,
            `token`      varchar(255) NOT NULL,
            `expires_at` datetime     NOT NULL,
            `created_at` datetime     DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `token`   (`token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ════════════════════════════════════════════════════════════
        // 11. registration_email_settings
        // ════════════════════════════════════════════════════════════
        $db->exec("CREATE TABLE IF NOT EXISTS `registration_email_settings` (
            `id`              int  NOT NULL AUTO_INCREMENT,
            `email_mode`      enum('all','whitelist','blacklist') NOT NULL DEFAULT 'all',
            `allowed_domains` text,
            `blocked_domains` text,
            `updated_at`      timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ════════════════════════════════════════════════════════════
        // 12. remember_tokens
        // ════════════════════════════════════════════════════════════
        $db->exec("CREATE TABLE IF NOT EXISTS `remember_tokens` (
            `id`         int         NOT NULL AUTO_INCREMENT,
            `user_id`    int         NOT NULL,
            `token`      varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
            `expires_at` datetime    NOT NULL,
            `created_at` timestamp   NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `token`   (`token`),
            KEY        `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // ════════════════════════════════════════════════════════════
        // 13. site_pages
        // ════════════════════════════════════════════════════════════
        $db->exec("CREATE TABLE IF NOT EXISTS `site_pages` (
            `id`               int          NOT NULL AUTO_INCREMENT,
            `title`            varchar(255) NOT NULL,
            `slug`             varchar(255) NOT NULL,
            `content`          longtext,
            `meta_description` text,
            `status`           enum('published','draft') NOT NULL DEFAULT 'published',
            `show_in_nav`      tinyint(1)   NOT NULL DEFAULT '0',
            `nav_label`        varchar(100) DEFAULT NULL,
            `created_by`       int          DEFAULT NULL,
            `created_at`       datetime     DEFAULT CURRENT_TIMESTAMP,
            `updated_at`       datetime     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `slug`   (`slug`),
            KEY        `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ════════════════════════════════════════════════════════════
        // 14. system_config
        // ════════════════════════════════════════════════════════════
        $db->exec("CREATE TABLE IF NOT EXISTS `system_config` (
            `id`           int         NOT NULL AUTO_INCREMENT,
            `config_key`   varchar(50) NOT NULL,
            `config_value` text,
            `updated_at`   int         NOT NULL,
            `created_at`   int         NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `config_key` (`config_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ════════════════════════════════════════════════════════════
        // 15. tag_stats
        // ════════════════════════════════════════════════════════════
        $db->exec("CREATE TABLE IF NOT EXISTS `tag_stats` (
            `tag`   varchar(100) NOT NULL,
            `count` int          NOT NULL DEFAULT '0',
            PRIMARY KEY (`tag`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // ════════════════════════════════════════════════════════════
        // 16. users
        // ════════════════════════════════════════════════════════════
        $db->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id`                int          NOT NULL AUTO_INCREMENT,
            `username`          varchar(50)  NOT NULL,
            `email`             varchar(100) NOT NULL,
            `password_hash`     varchar(255) NOT NULL,
            `avatar`            varchar(255) DEFAULT NULL,
            `role`              enum('admin','editor','user') DEFAULT 'user',
            `created_at`        datetime     DEFAULT CURRENT_TIMESTAMP,
            `updated_at`        datetime     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `last_login`        datetime     DEFAULT NULL,
            `nickname`          varchar(50)  DEFAULT NULL,
            `status`            enum('normal','frozen','banned') NOT NULL DEFAULT 'normal',
            `status_expires_at` datetime     DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`),
            UNIQUE KEY `email`    (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DbMigrator::addColumnIfNotExists($db, 'users', 'nickname',          "varchar(50) DEFAULT NULL",                                     'last_login');
        DbMigrator::addColumnIfNotExists($db, 'users', 'status',            "enum('normal','frozen','banned') NOT NULL DEFAULT 'normal'",    'nickname');
        DbMigrator::addColumnIfNotExists($db, 'users', 'status_expires_at', "datetime DEFAULT NULL",                                        'status');

        // ════════════════════════════════════════════════════════════
        // 17. user_favorites
        // ════════════════════════════════════════════════════════════
        $db->exec("CREATE TABLE IF NOT EXISTS `user_favorites` (
            `id`         int      NOT NULL AUTO_INCREMENT,
            `user_id`    int      NOT NULL,
            `article_id` int      NOT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_user_article` (`user_id`, `article_id`),
            KEY `idx_user_id`    (`user_id`),
            KEY `idx_article_id` (`article_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户收藏记录'");

        // ════════════════════════════════════════════════════════════
        // 18. user_login_history
        // ════════════════════════════════════════════════════════════
        $db->exec("CREATE TABLE IF NOT EXISTS `user_login_history` (
            `id`          int          NOT NULL AUTO_INCREMENT,
            `user_id`     int          NOT NULL,
            `ipv4`        varchar(15)  DEFAULT NULL,
            `ipv6`        varchar(45)  DEFAULT NULL,
            `all_ips`     varchar(500) DEFAULT NULL,
            `ip_source`   varchar(100) DEFAULT 'REMOTE_ADDR',
            `is_proxy`    tinyint(1)   NOT NULL DEFAULT '0',
            `is_local`    tinyint(1)   NOT NULL DEFAULT '0',
            `user_agent`  varchar(500) DEFAULT NULL,
            `browser`     varchar(100) DEFAULT NULL,
            `os`          varchar(100) DEFAULT NULL,
            `device_type` enum('desktop','mobile','tablet') NOT NULL DEFAULT 'desktop',
            `login_at`    datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_user_login` (`user_id`, `login_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户登录历史'");

        DbMigrator::addColumnIfNotExists($db, 'user_login_history', 'ipv4',        "varchar(15)  DEFAULT NULL",  'user_id');
        DbMigrator::addColumnIfNotExists($db, 'user_login_history', 'ipv6',        "varchar(45)  DEFAULT NULL",  'ipv4');
        DbMigrator::addColumnIfNotExists($db, 'user_login_history', 'all_ips',     "varchar(500) DEFAULT NULL",  'ipv6');
        DbMigrator::addColumnIfNotExists($db, 'user_login_history', 'ip_source',   "varchar(100) DEFAULT 'REMOTE_ADDR'", 'all_ips');
        DbMigrator::addColumnIfNotExists($db, 'user_login_history', 'is_proxy',    "tinyint(1)   NOT NULL DEFAULT '0'", 'ip_source');
        DbMigrator::addColumnIfNotExists($db, 'user_login_history', 'is_local',    "tinyint(1)   NOT NULL DEFAULT '0'", 'is_proxy');
        DbMigrator::addColumnIfNotExists($db, 'user_login_history', 'browser',     "varchar(100) DEFAULT NULL",  'user_agent');
        DbMigrator::addColumnIfNotExists($db, 'user_login_history', 'os',          "varchar(100) DEFAULT NULL",  'browser');
        DbMigrator::addColumnIfNotExists($db, 'user_login_history', 'device_type', "enum('desktop','mobile','tablet') NOT NULL DEFAULT 'desktop'", 'os');
    },
];