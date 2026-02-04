<?php
session_start();
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
// 此功能文件计划弃用