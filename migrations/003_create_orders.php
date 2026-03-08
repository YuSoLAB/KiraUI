<?php
return [
    'description' => '创建订单表 orders，关联 users',
    'up' => function (PDO $db) {
        require_once dirname(__DIR__) . '/include/DbMigrator.php';

        // 创建 orders 表（如果不存在）
        $db->exec("CREATE TABLE IF NOT EXISTS `orders` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '订单ID',
            `user_id` INT UNSIGNED NOT NULL COMMENT '用户ID',
            `amount` DECIMAL(10,2) NOT NULL COMMENT '金额',
            `order_time` INT UNSIGNED NOT NULL COMMENT '下单时间',
            INDEX `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单表'");

        // 如果用户已经存在 orders 表，但缺少某些字段，可以单独加字段
        DbMigrator::addColumnIfNotExists($db, 'orders', 'status', "TINYINT DEFAULT 0 COMMENT '订单状态'");

        // 若 orders 表已存在但 user_id 字段类型不匹配？迁移不修改现有字段，只添加缺失字段
    },
];