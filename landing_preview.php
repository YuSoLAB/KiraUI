<?php
session_start();
// 验证管理员权限
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die('没有权限访问此页面');
}

// 获取提交的页面代码
$landingCode = isset($_POST['landing_code']) ? $_POST['landing_code'] : '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>展示页面预览</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            padding: 0;
        }
        .preview-notice {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #fff3cd;
            padding: 10px 15px;
            text-align: center;
            z-index: 9999;
            border-bottom: 1px solid #ffeeba;
        }
        .preview-content {
            margin-top: 40px;
        }
    </style>
</head>
<body>
    <div class="preview-notice">
        ⚠️ 这是展示页面的预览，仅管理员可见
    </div>
    <div class="preview-content">
        <?php echo $landingCode; ?>
    </div>
</body>
</html>