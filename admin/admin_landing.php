<?php
require_once dirname(__DIR__) . '/include/Config.php';
$config = Config::getInstance();
$landingConfig = [
    'enabled' => $config->get('landing_enabled', '0') === '1',
    'code'    => $config->get('landing_code', '')
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_landing') {
    $newConfig = [
        'landing_enabled' => isset($_POST['enabled']) ? '1' : '0',
        'landing_code'    => $_POST['landing_code'] ?? ''
    ];
    $config->batchSet($newConfig);
    $landingConfig = array_merge($landingConfig, $newConfig);
    $message = "展示页面配置已保存成功！";
}
?>
<style>
/* ── landing ── */
.ldg-code { width:100%; padding:.55rem .72rem; border:1px solid var(--admin-border,rgba(155,140,255,.4)); border-radius:8px; font-family:Consolas,Monaco,monospace; font-size:.84rem; resize:vertical; background:var(--admin-card,#fff); color:inherit; box-sizing:border-box; transition:border-color .15s; }
.ldg-code:focus { outline:none; border-color:#6c5dfb; box-shadow:0 0 0 3px rgba(108,93,251,.1); }
.ldg-toggle { display:inline-flex; align-items:center; gap:.5rem; cursor:pointer; font-size:.9rem; user-select:none; }
.ldg-toggle input { accent-color:#6c5dfb; width:15px; height:15px; }
.ldg-acts  { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; margin-top:1rem; }
body.dark-mode .ldg-code { background:#2a2a42aa; border-color:var(--dark-admin-border); color:var(--dark-text,#eaeaea); }
body.dark-mode .ldg-code:focus { border-color:var(--dark-vio,#b096ff); box-shadow:0 0 0 3px rgba(176,160,255,.12); }
</style>

<div class="admin-section">

    <div class="mhdr">
        <div>
            <h2 class="mhdr-title">🖥️ 展示页面管理</h2>
            <p class="mhdr-sub">启用后将以自定义 HTML 替代默认首页，支持内联 &lt;style&gt; 和 &lt;script&gt; 标签。</p>
        </div>
    </div>

    <div class="mtip">💡 启用展示页面后，访问首页将直接呈现此处编写的 HTML 代码，原博客首页将被隐藏。建议先点「预览」确认效果后再保存。</div>

    <?php if (isset($message)): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="mbuilder" style="padding:1.2rem;">
        <form method="post" id="landing-form">
            <input type="hidden" name="action" value="save_landing">

            <!-- 启用开关 -->
            <div class="mfg" style="margin-bottom:1rem;">
                <label class="ldg-toggle">
                    <input type="checkbox" name="enabled" <?php echo $landingConfig['enabled'] ? 'checked' : ''; ?>>
                    启用展示页面（启用后将替代默认首页）
                </label>
            </div>

            <!-- 代码编辑区 -->
            <div class="mfg" style="margin-bottom:.5rem;">
                <label style="font-size:.83rem;font-weight:700;color:var(--sub,#666);margin-bottom:.3rem;display:block;">首页展示代码 (HTML)</label>
                <textarea class="ldg-code" id="landing_code" name="landing_code" rows="16"><?php echo htmlspecialchars($landingConfig['code']); ?></textarea>
                <small style="font-size:.75rem;color:var(--sub,#999);margin-top:.3rem;display:block;">可直接包含 &lt;style&gt; 和 &lt;script&gt; 标签编写样式与脚本</small>
            </div>

            <div class="ldg-acts">
                <button type="submit" class="btn btn-primary">💾 保存配置</button>
                <button type="button" class="btn btn-xs mbtn-p" id="previewLandingBtn">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    预览展示页面
                </button>
            </div>
        </form>
    </div>

</div>

<script>
document.getElementById('previewLandingBtn').addEventListener('click', function() {
    const form   = document.createElement('form');
    form.method  = 'POST';
    form.action  = '../landing_preview.php';
    form.target  = '_blank';
    const input  = document.createElement('input');
    input.type   = 'hidden';
    input.name   = 'landing_code';
    input.value  = document.getElementById('landing_code').value;
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
});
</script>