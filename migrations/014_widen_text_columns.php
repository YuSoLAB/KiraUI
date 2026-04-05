<?php
/**
 * 014_widen_text_columns.php
 *
 * 将可能存放大数据的 text 列升级为更大的类型，避免超长内容被截断：
 *
 *   system_config.config_value  text → longtext
 *     理由：可能存储邮件 HTML 模板、自定义 CSS/JS、大段 JSON 配置等，
 *           text 上限约 64 KB，longtext 上限约 4 GB，完全无后顾之忧。
 *
 *   comments.content            text → mediumtext
 *     理由：若评论支持 Markdown / 富文本，长评论超过 64 KB 并非不可能；
 *           mediumtext 上限约 16 MB，对评论场景绰绰有余，且比 longtext
 *           更节省存储引擎的内联长度判断开销。
 *
 * 均使用 INFORMATION_SCHEMA 先判断当前类型，已是目标类型则跳过，幂等安全。
 */
return [
    'description' => '将 system_config.config_value 升为 longtext，comments.content 升为 mediumtext',
    'up' => function (PDO $db) {
        require_once dirname(__DIR__) . '/include/DbMigrator.php';

        // ── 1. system_config.config_value: text → longtext ──────────────────
        $row = $db->query("
            SELECT DATA_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'system_config'
              AND COLUMN_NAME  = 'config_value'
        ")->fetch(PDO::FETCH_ASSOC);

        if ($row && strtolower($row['DATA_TYPE']) !== 'longtext') {
            $db->exec("
                ALTER TABLE `system_config`
                MODIFY COLUMN `config_value` longtext
            ");
        }

        // ── 2. comments.content: text → mediumtext ───────────────────────────
        $row = $db->query("
            SELECT DATA_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'comments'
              AND COLUMN_NAME  = 'content'
        ")->fetch(PDO::FETCH_ASSOC);

        if ($row && strtolower($row['DATA_TYPE']) !== 'mediumtext') {
            $db->exec("
                ALTER TABLE `comments`
                MODIFY COLUMN `content` mediumtext NOT NULL
            ");
        }
    },
];