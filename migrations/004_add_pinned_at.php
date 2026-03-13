<?php
/**
 * 迁移 004 — 文章置顶功能
 *
 * 在 articles 和 article_index 两张表中各添加 pinned_at 字段，
 * 并为 article_index 加查询索引，用于支持首页置顶排序功能。
 *
 * pinned_at IS NULL      → 未置顶（默认）
 * pinned_at IS NOT NULL  → 已置顶，值为置顶时间，越新越靠前
 */
return [
    'description' => '添加文章置顶字段 pinned_at（articles & article_index）及索引',
    'up' => function (PDO $db) {
        require_once dirname(__DIR__) . '/include/DbMigrator.php';

        // articles 表加 pinned_at 列
        DbMigrator::addColumnIfNotExists(
            $db,
            'articles',
            'pinned_at',
            "datetime DEFAULT NULL COMMENT '置顶时间，NULL 表示未置顶，越新越靠前' AFTER `cover_image`"
        );

        // article_index 表加 pinned_at 列
        DbMigrator::addColumnIfNotExists(
            $db,
            'article_index',
            'pinned_at',
            "datetime DEFAULT NULL COMMENT '置顶时间，NULL 表示未置顶，越新越靠前' AFTER `cover_image`"
        );

        // article_index 加索引（文章量大时加速置顶查询）
        DbMigrator::addIndexIfNotExists(
            $db,
            'article_index',
            'idx_pinned_at',
            '`pinned_at`'
        );
    },
];