<?php
require_once __DIR__ . '/../include/Db.php';
require_once 'admin_functions.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $db = Db::getInstance();
    $userId = $_POST['user_id'] ?? 0;
    switch ($_POST['action']) {
        case 'update_status':
            $status = $_POST['status'] ?? 'normal';
            $duration = $_POST['duration'] ?? 0;
            $expiresAt = null;
            if ($duration > 0 && in_array($status, ['frozen', 'banned'])) {
                $expiresAt = date('Y-m-d H:i:s', strtotime("+$duration hours"));
            }
            $stmt = $db->prepare("UPDATE users SET status = ?, status_expires_at = ? WHERE id = ?");
            $stmt->execute([$status, $expiresAt, $userId]);
            $message = "用户状态已更新";
            break;
        case 'save_email_settings':
            $settings = [
                'email_mode' => $_POST['email_mode'] ?? 'all',
                'allowed_domains' => isset($_POST['allowed_domains']) ? explode("\n", $_POST['allowed_domains']) : [],
                'blocked_domains' => isset($_POST['blocked_domains']) ? explode("\n", $_POST['blocked_domains']) : []
            ];
            $settings['allowed_domains'] = array_filter(array_map('trim', $settings['allowed_domains']));
            $settings['blocked_domains'] = array_filter(array_map('trim', $settings['blocked_domains']));
            saveRegistrationEmailSettings($settings);
            $message = "注册邮箱设置已保存";
            break;
    }
}
$db = Db::getInstance();
$stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
$emailSettings = getRegistrationEmailSettings();
?>
<div class="tab-content" id="users">
    <div class="section">
        <h2>用户管理</h2>
        <?php if (isset($message)): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        <h3>用户列表</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>用户名</th>
                    <th>昵称</th>
                    <th>邮箱</th>
                    <th>注册时间</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <?php 
                if ($user['role'] == 'admin' || $user['email'] == 'admin@example.com') {
                    continue;
                }
                ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['nickname']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo $user['created_at']; ?></td>
                    <td>
                        <?php 
                        $statusText = [
                            'normal' => '正常',
                            'frozen' => '冻结',
                            'banned' => '封禁'
                        ][$user['status']];
                        if ($user['status'] !== 'normal' && $user['status_expires_at']) {
                            $statusText .= " (至 " . $user['status_expires_at'] . ")";
                        }
                        echo $statusText;
                        ?>
                    </td>
                    <td>
                        <form method="post" class="inline-form">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <input type="hidden" name="action" value="update_status">
                            <select name="status" required>
                                <option value="normal" <?php echo $user['status'] == 'normal' ? 'selected' : ''; ?>>正常</option>
                                <option value="frozen" <?php echo $user['status'] == 'frozen' ? 'selected' : ''; ?>>冻结</option>
                                <option value="banned" <?php echo $user['status'] == 'banned' ? 'selected' : ''; ?>>封禁</option>
                            </select>
                            <select name="duration">
                                <option value="0">永久</option>
                                <option value="24">24小时</option>
                                <option value="72">3天</option>
                                <option value="168">7天</option>
                                <option value="720">30天</option>
                            </select>
                            <button type="submit" class="btn btn-sm">更新</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="settings-card">
            <h3>注册邮箱设置</h3>
            <form method="post">
                <input type="hidden" name="action" value="save_email_settings">
                <div class="form-group">
                    <label for="reg_email_mode">邮箱过滤模式</label>
                    <select id="reg_email_mode" name="email_mode">
                        <option value="all" <?php echo $emailSettings['email_mode'] == 'all' ? 'selected' : ''; ?>>
                            允许所有邮箱
                        </option>
                        <option value="whitelist" <?php echo $emailSettings['email_mode'] == 'whitelist' ? 'selected' : ''; ?>>
                            仅允许白名单邮箱
                        </option>
                        <option value="blacklist" <?php echo $emailSettings['email_mode'] == 'blacklist' ? 'selected' : ''; ?>>
                            禁止黑名单邮箱
                        </option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="reg_allowed_domains">允许的邮箱域名（每行一个）</label>
                    <textarea id="reg_allowed_domains" name="allowed_domains" rows="5"><?php echo implode("\n", $emailSettings['allowed_domains']); ?></textarea>
                    <small>仅在白名单模式下生效</small>
                </div>
                <div class="form-group">
                    <label for="reg_blocked_domains">禁止的邮箱域名（每行一个）</label>
                    <textarea id="reg_blocked_domains" name="blocked_domains" rows="5"><?php echo implode("\n", $emailSettings['blocked_domains']); ?></textarea>
                    <small>在黑名单模式下生效</small>
                </div>
                <button type="submit" class="btn btn-primary">保存设置</button>
            </form>
        </div>
    </div>
</div>