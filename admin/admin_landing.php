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
                <label for="landing_code">页面代码（包含HTML、CSS和JavaScript）</label>
                <div class="code-editor">
                    <textarea id="landing_code" name="landing_code" style="display: none;"><?php echo htmlspecialchars($landingConfig['code']); ?></textarea>
                    <div id="landing_code_editor" style="border: 1px solid #ddd; border-radius: 4px; height: 302px;"></div>
                </div>
                <small>提示：可直接包含&lt;style&gt;和&lt;script&gt;标签编写样式和脚本</small>
            </div>
            <div>
                <button type="submit" class="btn btn-primary">保存配置</button>
            </div>
        </form>
        <div class="section" style="margin-top: 30px;">
            <h3>预览</h3>
            <div class="preview-container" style="border: 1px solid #eee; padding: 15px; margin-top: 10px;">
                <?php echo $landingConfig['code'];  ?>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const codeEditor = CodeMirror(document.getElementById('landing_code_editor'), {
        mode: 'htmlmixed',
        theme: 'dracula',
        lineNumbers: true,
        autoCloseTags: true,
        lineWrapping: true
    });
    codeEditor.setValue(document.getElementById('landing_code').value);
    codeEditor.on('change', function() {
        document.getElementById('landing_code').value = codeEditor.getValue();
    });
});
</script>