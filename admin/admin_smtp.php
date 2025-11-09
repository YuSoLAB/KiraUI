<?php
require_once dirname(__DIR__) . '/include/Config.php';
$config = Config::getInstance();
$smtpConfig = [
    'enabled' => $config->get('smtp_enabled', '0') === '1',
    'host' => $config->get('smtp_host', ''),
    'port' => $config->get('smtp_port', '587'),
    'username' => $config->get('smtp_username', ''),
    'password' => $config->get('smtp_password', ''),
    'from_email' => $config->get('smtp_from_email', ''),
    'from_name' => $config->get('smtp_from_name', ''),
    'encryption' => $config->get('smtp_encryption', 'tls')
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_smtp') {
    $newConfig = [
        'smtp_enabled' => isset($_POST['enabled']) ? '1' : '0',
        'smtp_host' => $_POST['host'] ?? '',
        'smtp_port' => $_POST['port'] ?? '587',
        'smtp_username' => $_POST['username'] ?? '',
        'smtp_password' => $_POST['password'] ?? '',
        'smtp_from_email' => $_POST['from_email'] ?? '',
        'smtp_from_name' => $_POST['from_name'] ?? '',
        'smtp_encryption' => $_POST['encryption'] ?? 'tls'
    ];
    $config->batchSet($newConfig);
    $smtpConfig = array_merge($smtpConfig, [
        'enabled' => $newConfig['smtp_enabled'] === '1',
        'host' => $newConfig['smtp_host'],
        'port' => $newConfig['smtp_port'],
        'username' => $newConfig['smtp_username'],
        'password' => $newConfig['smtp_password'],
        'from_email' => $newConfig['smtp_from_email'],
        'from_name' => $newConfig['smtp_from_name'],
        'encryption' => $newConfig['smtp_encryption']
    ]);
    $message = "SMTP配置已保存成功！";
}
?>
<div class="tab-content" id="smtp">
    <div class="section">
        <h2>SMTP邮件配置</h2>
        <p class="section-description">配置邮件发送服务器，用于发送注册验证、密码找回等邮件。</p>      
        <?php if (isset($message)): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="action" value="save_smtp">
            <div class="form-group">
                <label style="display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; margin-bottom: 15px;">
                    <input type="checkbox" name="enabled" <?php echo $smtpConfig['enabled'] ? 'checked' : ''; ?>>
                    启用SMTP邮件功能
                </label>
            </div>
            <div class="form-group">
                <label for="smtp_host">SMTP服务器地址</label>
                <input type="text" id="smtp_host" name="host" 
                       value="<?php echo htmlspecialchars($smtpConfig['host']); ?>" 
                       placeholder="例如: smtp.example.com">
            </div>
            <div class="form-group">
                <label for="smtp_port">SMTP端口</label>
                <input type="number" id="smtp_port" name="port" 
                       value="<?php echo htmlspecialchars($smtpConfig['port']); ?>" 
                       placeholder="通常是 25, 465 或 587">
            </div>
            <div class="form-group">
                <label for="smtp_encryption">加密方式</label>
                <select id="smtp_encryption" name="encryption">
                    <option value="tls" <?php echo $smtpConfig['encryption'] == 'tls' ? 'selected' : ''; ?>>TLS</option>
                    <option value="ssl" <?php echo $smtpConfig['encryption'] == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    <option value="" <?php echo $smtpConfig['encryption'] == '' ? 'selected' : ''; ?>>无</option>
                </select>
            </div>
            <div class="form-group">
                <label for="smtp_username">SMTP用户名</label>
                <input type="text" id="smtp_username" name="username" 
                       value="<?php echo htmlspecialchars($smtpConfig['username']); ?>">
            </div>
            <div class="form-group">
                <label for="smtp_password">SMTP密码/授权码</label>
                <input type="password" id="smtp_password" name="password" 
                       value="<?php echo htmlspecialchars($smtpConfig['password']); ?>">
                <small>部分邮箱需要使用授权码而非登录密码</small>
            </div>
            <div class="form-group">
                <label for="smtp_from_email">发件人邮箱</label>
                <input type="email" id="smtp_from_email" name="from_email" 
                       value="<?php echo htmlspecialchars($smtpConfig['from_email']); ?>">
            </div>
            <div class="form-group">
                <label for="smtp_from_name">发件人名称</label>
                <input type="text" id="smtp_from_name" name="from_name" 
                       value="<?php echo htmlspecialchars($smtpConfig['from_name']); ?>">
            </div>
            <button type="submit" class="btn btn-primary">保存SMTP配置</button>
        </form>
    </div>
</div>