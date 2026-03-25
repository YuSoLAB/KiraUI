<?php
/**
 * 011_fix_email_default.php
 *
 * 背景：注册流程从邮箱改为手机号后，INSERT 不再写入 email，
 *       但 email 列仍为 NOT NULL 且无默认值，导致注册报错：
 *       SQLSTATE[HY000]: General error: 1364 Field 'email' doesn't have a default value
 *
 * 修复：将 email 改为 NOT NULL DEFAULT ''，兼容历史数据，不影响已存邮箱。
 */
return [
    'description' => 'users.email 字段补充默认值，修复手机号注册失败问题',
    'up' => function (PDO $db) {
        require_once dirname(__DIR__) . '/include/DbMigrator.php';

        // 仅当 email 列存在时才执行修改，防止表结构差异导致报错
        if (DbMigrator::columnExists($db, 'users', 'email')) {
            $db->exec(
                "ALTER TABLE `users`
                 MODIFY `email` VARCHAR(255) NOT NULL DEFAULT ''"
            );
        }
    },
];