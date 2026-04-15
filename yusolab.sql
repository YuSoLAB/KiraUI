-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- 主机： 127.0.0.1:3306
-- 生成日期： 2026-04-15 06:33:15
-- 服务器版本： 8.4.7
-- PHP 版本： 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `yusolab`
--

-- --------------------------------------------------------

--
-- 表的结构 `articles`
--

DROP TABLE IF EXISTS `articles`;
CREATE TABLE IF NOT EXISTS `articles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `excerpt` text,
  `content` longtext NOT NULL,
  `date` date NOT NULL,
  `tags` text,
  `word_count` int NOT NULL DEFAULT '0',
  `read_time` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `author` varchar(255) NOT NULL DEFAULT '',
  `author_email` varchar(255) DEFAULT NULL COMMENT '作者邮箱',
  `cover_image` varchar(500) DEFAULT NULL COMMENT '封面图路径（如 /uploads/images/xxx.jpg）',
  `pinned_at` datetime DEFAULT NULL COMMENT '置顶时间，NULL 表示未置顶，越新越靠前',
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `article_index`
--

DROP TABLE IF EXISTS `article_index`;
CREATE TABLE IF NOT EXISTS `article_index` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `excerpt` text,
  `tags` text,
  `word_count` int NOT NULL DEFAULT '0',
  `read_time` int NOT NULL DEFAULT '0',
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `author_email` varchar(255) DEFAULT '' COMMENT '作者邮箱',
  `created_by` int DEFAULT NULL COMMENT '作者ID',
  `cover_image` varchar(500) DEFAULT NULL COMMENT '封面图路径',
  `pinned_at` datetime DEFAULT NULL COMMENT '置顶时间，NULL 表示未置顶，越新越靠前',
  PRIMARY KEY (`id`),
  KEY `idx_pinned_at` (`pinned_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `comments`
--

DROP TABLE IF EXISTS `comments`;
CREATE TABLE IF NOT EXISTS `comments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `article_id` int NOT NULL,
  `parent_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL COMMENT '登录用户ID（关联 users.id），访客评论为 NULL',
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `email_hash` varchar(32) NOT NULL,
  `content` mediumtext NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `approved` tinyint(1) DEFAULT '0',
  `moderation` enum('strict','auto') DEFAULT 'strict',
  PRIMARY KEY (`id`),
  KEY `article_id` (`article_id`),
  KEY `fk_comments_parent` (`parent_id`),
  KEY `idx_comments_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `comment_settings`
--

DROP TABLE IF EXISTS `comment_settings`;
CREATE TABLE IF NOT EXISTS `comment_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email_mode` enum('all','whitelist','blacklist') DEFAULT 'all',
  `allowed_domains` text,
  `blocked_domains` text,
  `default_moderation` enum('strict','auto') DEFAULT 'strict',
  `enable_comments` tinyint(1) DEFAULT '1',
  `allow_guest_comments` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `notify_admin` tinyint(1) NOT NULL DEFAULT '1' COMMENT '有新评论时是否向管理员发送邮件通知（总开关）',
  `email_notify_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '全局邮件通知总开关：1=开启，0=关闭',
  `notify_guest_reply` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否向游客邮箱发送回复通知：1=是，0=否',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `db_migrations`
--

DROP TABLE IF EXISTS `db_migrations`;
CREATE TABLE IF NOT EXISTS `db_migrations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_migration` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `drafts`
--

DROP TABLE IF EXISTS `drafts`;
CREATE TABLE IF NOT EXISTS `drafts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `excerpt` text,
  `content` longtext NOT NULL,
  `date` date NOT NULL,
  `tags` text,
  `word_count` int NOT NULL DEFAULT '0',
  `read_time` int NOT NULL DEFAULT '0',
  `cover_image` varchar(500) DEFAULT NULL COMMENT '封面图路径',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `email_moderation`
--

DROP TABLE IF EXISTS `email_moderation`;
CREATE TABLE IF NOT EXISTS `email_moderation` (
  `email_hash` varchar(32) NOT NULL,
  `moderation` enum('strict','auto') DEFAULT 'strict',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`email_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `email_verification`
--

DROP TABLE IF EXISTS `email_verification`;
CREATE TABLE IF NOT EXISTS `email_verification` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `code` varchar(6) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `verified` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL COMMENT '对应 users.id',
  `attempts` int NOT NULL DEFAULT '0' COMMENT '累计失败次数',
  `locked_until` datetime DEFAULT NULL COMMENT '锁定到期时间，NULL 表示未锁定',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_id` (`user_id`),
  UNIQUE KEY `uq_la_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='登录失败计数与临时账号锁定';

-- --------------------------------------------------------

--
-- 表的结构 `nav_menus`
--

DROP TABLE IF EXISTS `nav_menus`;
CREATE TABLE IF NOT EXISTS `nav_menus` (
  `id` int NOT NULL AUTO_INCREMENT,
  `label` varchar(100) NOT NULL COMMENT '菜单名称',
  `url` varchar(500) NOT NULL DEFAULT '#' COMMENT '链接地址',
  `parent_id` int DEFAULT NULL COMMENT '父菜单ID，NULL为顶级',
  `sort_order` int NOT NULL DEFAULT '0' COMMENT '排序权重，越小越靠前',
  `open_new_tab` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否新标签页打开',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用',
  `icon` varchar(50) DEFAULT NULL COMMENT '可选图标 emoji 或 class',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL COMMENT '接收通知的用户 ID（关联 users.id）',
  `type` enum('reply') NOT NULL DEFAULT 'reply' COMMENT '通知类型，目前仅支持 reply',
  `comment_id` int NOT NULL COMMENT '触发通知的回复评论 ID',
  `article_id` int NOT NULL COMMENT '所在文章 ID',
  `is_read` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已读',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`,`is_read`),
  KEY `fk_notif_comment` (`comment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='评论回复通知';

-- --------------------------------------------------------

--
-- 表的结构 `orders`
--

DROP TABLE IF EXISTS `orders`;
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '订单ID',
  `user_id` int UNSIGNED NOT NULL COMMENT '用户ID',
  `amount` decimal(10,2) NOT NULL COMMENT '金额',
  `order_time` int UNSIGNED NOT NULL COMMENT '下单时间',
  `status` tinyint DEFAULT '0' COMMENT '订单状态',
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='订单表';

-- --------------------------------------------------------

--
-- 表的结构 `password_reset`
--

DROP TABLE IF EXISTS `password_reset`;
CREATE TABLE IF NOT EXISTS `password_reset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `pending_profile_changes`
--

DROP TABLE IF EXISTS `pending_profile_changes`;
CREATE TABLE IF NOT EXISTS `pending_profile_changes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL COMMENT '申请变更的用户 ID',
  `type` enum('nickname','avatar') NOT NULL COMMENT '变更类型',
  `new_value` varchar(255) NOT NULL COMMENT '新昵称文本 或 待审头像文件名（pending_{id}.ext）',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reject_reason` varchar(255) DEFAULT NULL COMMENT '拒绝原因（可选）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户头像/昵称变更审核队列';

-- --------------------------------------------------------

--
-- 表的结构 `phone_verification`
--

DROP TABLE IF EXISTS `phone_verification`;
CREATE TABLE IF NOT EXISTS `phone_verification` (
  `id` int NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL COMMENT '手机号',
  `code` varchar(10) NOT NULL COMMENT '验证码明文（由阿里云返回）',
  `biz_id` varchar(64) DEFAULT NULL COMMENT '阿里云业务 ID',
  `sent_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL COMMENT '过期时间',
  `verified` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已使用',
  PRIMARY KEY (`id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='手机短信验证码记录';

-- --------------------------------------------------------

--
-- 表的结构 `registration_email_settings`
--

DROP TABLE IF EXISTS `registration_email_settings`;
CREATE TABLE IF NOT EXISTS `registration_email_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email_mode` enum('all','whitelist','blacklist') NOT NULL DEFAULT 'all',
  `allowed_domains` text,
  `blocked_domains` text,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `remember_tokens`
--

DROP TABLE IF EXISTS `remember_tokens`;
CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 表的结构 `site_pages`
--

DROP TABLE IF EXISTS `site_pages`;
CREATE TABLE IF NOT EXISTS `site_pages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT '页面标题',
  `slug` varchar(255) NOT NULL COMMENT '页面URL路径（英文、数字、连字符）',
  `content` longtext COMMENT '页面HTML内容',
  `meta_description` text COMMENT 'SEO描述',
  `status` enum('published','draft') NOT NULL DEFAULT 'published' COMMENT '状态',
  `show_in_nav` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否自动加入导航',
  `nav_label` varchar(100) DEFAULT NULL COMMENT '导航显示名（为空则用title）',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `system_config`
--

DROP TABLE IF EXISTS `system_config`;
CREATE TABLE IF NOT EXISTS `system_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `config_key` varchar(50) NOT NULL,
  `config_value` longtext,
  `updated_at` int NOT NULL,
  `created_at` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `tag_stats`
--

DROP TABLE IF EXISTS `tag_stats`;
CREATE TABLE IF NOT EXISTS `tag_stats` (
  `tag` varchar(100) NOT NULL,
  `count` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL COMMENT '邮箱（可为空）',
  `email_verified` tinyint(1) NOT NULL DEFAULT '0' COMMENT '邮箱已验证',
  `password_hash` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `role` enum('admin','editor','user') DEFAULT 'user',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL,
  `nickname` varchar(50) DEFAULT NULL COMMENT '用户昵称',
  `status` enum('normal','frozen','banned') NOT NULL DEFAULT 'normal',
  `status_expires_at` datetime DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL COMMENT '手机号（可为空）',
  `phone_verified` tinyint(1) NOT NULL DEFAULT '0' COMMENT '手机号是否已完成短信验证',
  `notify_on_reply` tinyint(1) NOT NULL DEFAULT '1' COMMENT '收到回复时是否发邮件通知（1=是，0=否）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `user_badges`
--

DROP TABLE IF EXISTS `user_badges`;
CREATE TABLE IF NOT EXISTS `user_badges` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL COMMENT '关联 users.id',
  `badge_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'verified' COMMENT '角标类型：verified/official/vip/admin/hot/star',
  `badge_color` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#1d9bf0' COMMENT '角标背景色（hex）',
  `badge_icon_color` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#ffffff' COMMENT '角标图标色（hex）',
  `title_text` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '头衔文字，空字符串=不显示',
  `title_color` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#6c5dfb' COMMENT '头衔文字色（hex）',
  `title_bg_color` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '头衔背景色（hex），空字符串=无背景',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用：1=启用，0=禁用',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_badge` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户认证角标与头衔配置';

-- --------------------------------------------------------

--
-- 表的结构 `user_favorites`
--

DROP TABLE IF EXISTS `user_favorites`;
CREATE TABLE IF NOT EXISTS `user_favorites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL COMMENT '用户ID，关联 users.id',
  `article_id` int NOT NULL COMMENT '文章ID，关联 articles.id（文章可能被删除）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '收藏时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_article` (`user_id`,`article_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_article_id` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户收藏记录';

-- --------------------------------------------------------

--
-- 表的结构 `user_login_history`
--

DROP TABLE IF EXISTS `user_login_history`;
CREATE TABLE IF NOT EXISTS `user_login_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL COMMENT '关联 users.id',
  `ipv4` varchar(15) DEFAULT NULL COMMENT '客户端 IPv4',
  `ipv6` varchar(45) DEFAULT NULL COMMENT '客户端 IPv6',
  `all_ips` varchar(500) DEFAULT NULL COMMENT '所有检测到的 IP（逗号分隔）',
  `ip_source` varchar(100) DEFAULT 'REMOTE_ADDR' COMMENT 'IP 来源请求头',
  `is_proxy` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否检测到代理/CDN',
  `is_local` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否内网/本地地址',
  `user_agent` varchar(500) DEFAULT NULL COMMENT '原始 User-Agent',
  `browser` varchar(100) DEFAULT NULL COMMENT '浏览器（含主版本）',
  `os` varchar(100) DEFAULT NULL COMMENT '操作系统',
  `device_type` enum('desktop','mobile','tablet') NOT NULL DEFAULT 'desktop' COMMENT '设备类型',
  `login_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '登录时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_login` (`user_id`,`login_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户登录历史：记录每次会话的 IP 与设备信息';

--
-- 限制导出的表
--

--
-- 限制表 `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD CONSTRAINT `fk_la_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- 限制表 `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_comment` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- 限制表 `password_reset`
--
ALTER TABLE `password_reset`
  ADD CONSTRAINT `password_reset_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- 限制表 `user_badges`
--
ALTER TABLE `user_badges`
  ADD CONSTRAINT `fk_ub_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- 限制表 `user_favorites`
--
ALTER TABLE `user_favorites`
  ADD CONSTRAINT `fk_favorites_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- 限制表 `user_login_history`
--
ALTER TABLE `user_login_history`
  ADD CONSTRAINT `fk_login_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
