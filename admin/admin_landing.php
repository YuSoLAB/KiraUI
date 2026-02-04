<?php
require_once dirname(__DIR__) . '/include/Config.php';
$config = Config::getInstance();
$landingConfig = [
    'enabled' => $config->get('landing_enabled', '0') === '1',
    'code' => $config->get('landing_code', '') 
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_landing') {
    $newConfig = [
        'landing_enabled' => isset($_POST['enabled']) ? '1' : '0',
        'landing_code' => $_POST['landing_code'] ?? ''
    ];
    $config->batchSet($newConfig);
    $landingConfig = array_merge($landingConfig, $newConfig);
    $message = "展示页面配置已保存成功！";
}
?>
<div class="tab-content" id="landing">
    <div class="section">
        <h2>展示页面管理</h2>
        <p>配置网站首页展示页面，启用后将替代默认首页。支持直接输入完整HTML代码（可包含style和script标签）。</p>
        <?php if (isset($message)): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="action" value="save_landing">
            <div class="form-group">
                <label style="display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; margin-bottom: 15px;">
                    <input type="checkbox" name="enabled" <?php echo $landingConfig['enabled'] ? 'checked' : ''; ?>>
                    启用展示页面（启用后将替代默认首页）
                </label>
            </div>
            <div class="form-group">
                <label for="landing_code">首页展示代码</label>
                <textarea id="landing_code" name="landing_code" rows="10" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: Consolas, Monaco, monospace; font-size: 14px; resize: vertical;"><?php echo htmlspecialchars($landingConfig['code']); ?></textarea>
                <small>提示：可直接包含&lt;style&gt;和&lt;script&gt;标签编写样式和脚本</small>
            </div>
            <div>
                <button type="submit" class="btn btn-primary">保存配置</button>
                <button type="button" class="btn btn-secondary" id="previewLandingBtn" style="margin-left: 10px;">预览展示页面</button>
            </div>
        </form>
    </div>
</div>
<script>
    document.getElementById('previewLandingBtn').addEventListener('click', function() {
    // 创建临时表单提交数据到预览页面
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '../landing_preview.php';
    form.target = '_blank';
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'landing_code';
    input.value = document.getElementById('landing_code').value;
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    });
</script>