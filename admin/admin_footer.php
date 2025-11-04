<?php
require_once dirname(__DIR__) . '/include/Config.php';
$config = Config::getInstance();
$footerConfig = [
    'content' => $config->get('footer_content', ''),
    'css' => $config->get('footer_css', ''),
    'js' => $config->get('footer_js', ''),
    'updated_at' => time()
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_footer') {
    $newConfig = [
        'footer_content' => $_POST['footer_content'] ?? '',
        'footer_css' => $_POST['footer_css'] ?? '',
        'footer_js' => $_POST['footer_js'] ?? ''
    ];
    
    $config->batchSet($newConfig);
    $footerConfig = array_merge($footerConfig, $newConfig);
    $message = "页脚配置已保存成功！";
}
?>
<div class="tab-content" id="footer">
    <div class="section">
        <h2>页脚管理</h2>
        <p>在这里配置网站底部显示的内容，可以包含HTML、CSS和JavaScript代码。</p>
        
        <form method="post">
            <input type="hidden" name="action" value="save_footer">
            
            <div class="form-group">
                <label for="footer_content">页脚内容 (HTML)</label>
                <div class="code-editor">
                    <textarea id="footer_content" name="footer_content" style="display: none;"><?php echo htmlspecialchars($footerConfig['content']); ?></textarea>
                    <div id="footer_content_editor" style="border: 1px solid #ddd; border-radius: 4px;"></div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="footer_css">页脚样式 (CSS)</label>
                <div class="code-editor">
                    <textarea id="footer_css" name="footer_css" style="display: none;"><?php echo htmlspecialchars($footerConfig['css']); ?></textarea>
                    <div id="footer_css_editor" style="border: 1px solid #ddd; border-radius: 4px;"></div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="footer_js">页脚脚本 (JavaScript)</label>
                <div class="code-editor">
                    <textarea id="footer_js" name="footer_js" style="display: none;"><?php echo htmlspecialchars($footerConfig['js']); ?></textarea>
                    <div id="footer_js_editor" style="border: 1px solid #ddd; border-radius: 4px;"></div>
                </div>
            </div>
            
            <div>
                <button type="submit" class="btn btn-primary">保存配置</button>
            </div>
        </form>
        
        <div class="section" style="margin-top: 30px;">
            <h3>预览</h3>
            <div class="preview-container" style="border: 1px solid #eee; padding: 15px; margin-top: 10px;">
                <style><?php echo $footerConfig['css']; ?></style>
                <div class="footer-preview">
                    <?php echo $footerConfig['content']; ?>
                </div>
                <script><?php echo $footerConfig['js']; ?></script>
            </div>
        </div>
    </div>
</div>