<?php
require_once dirname(__DIR__) . '/include/Config.php';
$config = Config::getInstance();
$announcementConfig = [
    'content' => $config->get('announcement_content', ''),
    'enabled' => $config->get('announcement_enabled', '0') === '1',
    'updated_at' => time()
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_announcement') {
    $newConfig = [
        'announcement_content' => $_POST['announcement_content'] ?? '',
        'announcement_enabled' => isset($_POST['enabled']) ? '1' : '0',
        'announcement_updated_at' => time()
    ];
    
    $config->batchSet($newConfig);
    $announcementConfig = array_merge($announcementConfig, [
        'content' => $newConfig['announcement_content'],
        'enabled' => $newConfig['announcement_enabled'] === '1',
        'updated_at' => $newConfig['announcement_updated_at']
    ]);
    $message = "公告配置已保存成功！";
}
?>

<div class="tab-content" id="announcement">
    <div class="section">
        <h2>弹窗公告管理</h2>
        <p>配置网站首页显示的弹窗公告内容，支持HTML标签进行格式化。</p>
        
        <form method="post">
            <input type="hidden" name="action" value="save_announcement">
            
            <div class="form-group">
                <label style="display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; margin-bottom: 15px;">
                    <input type="checkbox" name="enabled" <?php echo $announcementConfig['enabled'] ? 'checked' : ''; ?>>
                    启用弹窗公告
                </label>
            </div>
            
            <div class="form-group">
                <label for="announcement_content">公告内容 (支持HTML)</label>
                <div class="code-editor">
                    <textarea id="announcement_content" name="announcement_content" style="display: none;"><?php echo htmlspecialchars($announcementConfig['content']); ?></textarea>
                    <div id="announcement_content_editor" style="border: 1px solid #ddd; border-radius: 4px;"></div>
                </div>
                <small>可以使用HTML标签进行格式化，例如 &lt;strong&gt;加粗&lt;/strong&gt;、&lt;br&gt;换行等</small>
            </div>
            
            <div class="form-group">
                <h4>预览</h4>
                <div style="border: 1px solid #eee; padding: 15px; margin-top: 10px; max-width: 600px;">
                    <?php echo $announcementConfig['content']; ?>
                </div>
            </div>
            
            <div>
                <button type="submit" class="btn btn-primary">保存配置</button>
            </div>
        </form>
    </div>
</div>