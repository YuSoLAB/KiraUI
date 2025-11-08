<?php
session_start();
// 清除会话数据
$_SESSION = [];
// 销毁会话
session_destroy();
// 跳转到首页
header('Location: index.php');
exit;