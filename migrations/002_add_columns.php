<?php
return [
    'description' => '为 users 表增加 email 和 phone 字段',
    'up' => function (PDO $db) {
        require_once dirname(__DIR__) . '/include/DbMigrator.php';

        // 增加 email 列（如果不存在）
        DbMigrator::addColumnIfNotExists($db, 'users', 'email', "VARCHAR(100) DEFAULT NULL COMMENT '邮箱'");

        // 增加 phone 列（如果不存在）
        DbMigrator::addColumnIfNotExists($db, 'users', 'phone', "VARCHAR(20) DEFAULT NULL COMMENT '手机号'");

        // 如果用户自己已经建了 phone 列，但类型不是 varchar(20)？这里不修改，只添加不存在的列
        // 若需要修改列类型，需先判断存在且类型不符，但通常为了安全，不自动修改用户已有列
    },
];