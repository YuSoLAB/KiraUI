<?php
require_once dirname(__DIR__) . '/include/Config.php';
$config = Config::getInstance();
$landingConfig = [
    'enabled' => $config->get('landing_enabled', '0') === '1',
    'code'    => $config->get('landing_code', ''),
    'mode'    => $config->get('landing_mode', 'replace'),
];
?>
<style>
.ldg-code { width:100%; padding:.55rem .72rem; border:1px solid var(--admin-border,rgba(155,140,255,.4)); border-radius:8px; font-family:Consolas,Monaco,monospace; font-size:.84rem; resize:vertical; background:var(--admin-card,#fff); color:inherit; box-sizing:border-box; transition:border-color .15s; }
.ldg-code:focus { outline:none; border-color:#6c5dfb; box-shadow:0 0 0 3px rgba(108,93,251,.1); }
.ldg-toggle { display:inline-flex; align-items:center; gap:.5rem; cursor:pointer; font-size:.9rem; user-select:none; }
.ldg-toggle input { accent-color:#6c5dfb; width:15px; height:15px; }
.ldg-acts  { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; margin-top:1rem; }
.ldg-mode-cards { display:flex; gap:.75rem; flex-wrap:wrap; margin-top:.5rem; }
.ldg-mode-card  { flex:1; min-width:160px; border:2px solid var(--admin-border,rgba(155,140,255,.4)); border-radius:10px; padding:.85rem 1rem; cursor:pointer; transition:border-color .15s, background .15s; position:relative; }
.ldg-mode-card:hover { border-color:#6c5dfb; }
.ldg-mode-card input[type=radio] { position:absolute; opacity:0; width:0; height:0; }
.ldg-mode-card.selected { border-color:#6c5dfb; background:rgba(108,93,251,.07); }
.ldg-mode-card .mc-icon { font-size:1.5rem; margin-bottom:.3rem; }
.ldg-mode-card .mc-title { font-weight:700; font-size:.88rem; margin-bottom:.15rem; }
.ldg-mode-card .mc-desc  { font-size:.75rem; color:var(--sub,#888); line-height:1.4; }
.ldg-cover-hint { margin-top:.6rem; padding:.65rem .9rem; background:rgba(108,93,251,.07); border:1px solid rgba(108,93,251,.25); border-radius:8px; font-size:.8rem; line-height:1.7; color:var(--sub,#666); }
.ldg-cover-hint code { background:rgba(108,93,251,.12); padding:.05rem .35rem; border-radius:4px; font-family:Consolas,Monaco,monospace; font-size:.82rem; color:#6c5dfb; }
body.dark-mode .ldg-code { background:#2a2a42aa; border-color:var(--dark-admin-border); color:var(--dark-text,#eaeaea); }
body.dark-mode .ldg-code:focus { border-color:var(--dark-vio,#b096ff); box-shadow:0 0 0 3px rgba(176,160,255,.12); }
body.dark-mode .ldg-mode-card { border-color:var(--dark-admin-border); }
body.dark-mode .ldg-mode-card.selected { border-color:#b096ff; background:rgba(176,160,255,.1); }
body.dark-mode .ldg-cover-hint { background:rgba(176,160,255,.08); border-color:rgba(176,160,255,.2); color:var(--dark-sub,#aaa); }
body.dark-mode .ldg-cover-hint code { background:rgba(176,160,255,.15); color:#b096ff; }
</style>

<div class="admin-section">
    <div class="mhdr">
        <div>
            <h2 class="mhdr-title">🖥️ 展示页面管理</h2>
            <p class="mhdr-sub">启用后将以自定义 HTML 替代默认首页，支持内联 &lt;style&gt; 和 &lt;script&gt; 标签。</p>
        </div>
    </div>

    <div class="mtip">💡 启用展示页面后，访问首页将直接呈现此处编写的 HTML 代码，原博客首页将被隐藏。建议先点「预览」确认效果后再保存。</div>

    <div class="mbuilder" style="padding:1.2rem;">

        <div class="mfg" style="margin-bottom:1rem;">
            <label class="ldg-toggle">
                <input type="checkbox" id="ldg_enabled" <?php echo $landingConfig['enabled'] ? 'checked' : ''; ?>>
                启用展示页面（启用后将替代默认首页）
            </label>
        </div>

        <div class="mfg" style="margin-bottom:1.1rem;">
            <label style="font-size:.83rem;font-weight:700;color:var(--sub,#666);margin-bottom:.45rem;display:block;">展示模式</label>
            <div class="ldg-mode-cards">
                <label class="ldg-mode-card <?php echo $landingConfig['mode']==='replace'?'selected':''; ?>">
                    <input type="radio" name="ldg_mode" value="replace" <?php echo $landingConfig['mode']==='replace'?'checked':''; ?>>
                    <div class="mc-icon">🔄</div>
                    <div class="mc-title">替代首页</div>
                    <div class="mc-desc">展示页完全取代原始首页，访客无法直接进入博客</div>
                </label>
                <label class="ldg-mode-card <?php echo $landingConfig['mode']==='cover'?'selected':''; ?>">
                    <input type="radio" name="ldg_mode" value="cover" <?php echo $landingConfig['mode']==='cover'?'checked':''; ?>>
                    <div class="mc-icon">🪟</div>
                    <div class="mc-title">封面页模式</div>
                    <div class="mc-desc">展示页作为"封面"，在 HTML 中自行添加按钮链接到原始首页</div>
                </label>
            </div>
        </div>

        <div id="ldgCoverOpts" style="<?php echo $landingConfig['mode']==='cover'?'':'display:none;'; ?>margin-bottom:1rem;">
            <div class="ldg-cover-hint">
                🔗 封面页模式下，原始首页的访问链接为 <code>?enter=1</code>（即 <code>index.php?enter=1</code>）。<br>
                在下方 HTML 中自行编写进入按钮并将 <code>href</code> 指向该链接，样式完全由你决定。<br>
                示例：<code>&lt;a href="?enter=1"&gt;进入网站&lt;/a&gt;</code>
            </div>
        </div>

        <div class="mfg" style="margin-bottom:.5rem;">
            <label style="font-size:.83rem;font-weight:700;color:var(--sub,#666);margin-bottom:.3rem;display:block;">首页展示代码 (HTML)</label>
            <textarea class="ldg-code" id="landing_code" rows="16"><?php echo htmlspecialchars($landingConfig['code']); ?></textarea>
            <small style="font-size:.75rem;color:var(--sub,#999);margin-top:.3rem;display:block;">可直接包含 &lt;style&gt; 和 &lt;script&gt; 标签编写样式与脚本</small>
        </div>

        <div class="ldg-acts">
            <button type="button" class="btn btn-primary" id="saveLandingBtn">💾 保存配置</button>
            <button type="button" class="btn btn-xs mbtn-p" id="previewLandingBtn">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                预览展示页面
            </button>
        </div>

    </div>
</div>

<script>
(function() {
    var radios    = document.querySelectorAll('input[name="ldg_mode"]');
    var coverOpts = document.getElementById('ldgCoverOpts');

    function syncCards() {
        radios.forEach(function(r) {
            r.closest('.ldg-mode-card').classList.toggle('selected', r.checked);
        });
        var isCover = document.querySelector('input[name="ldg_mode"][value="cover"]').checked;
        coverOpts.style.display = isCover ? '' : 'none';
    }

    document.querySelectorAll('.ldg-mode-card').forEach(function(card) {
        card.addEventListener('click', function() {
            this.querySelector('input[type=radio]').checked = true;
            syncCards();
        });
    });

    syncCards();

    document.getElementById('saveLandingBtn').addEventListener('click', function() {
        var btn  = this;
        var orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '⏳ 保存中…';

        var fd = new FormData();
        fd.append('type',          'config');
        fd.append('config_action', 'save_landing');
        fd.append('landing_code',  document.getElementById('landing_code').value);
        fd.append('landing_mode',  document.querySelector('input[name="ldg_mode"]:checked').value);
        if (document.getElementById('ldg_enabled').checked) {
            fd.append('landing_enabled', '1');
        }

        fetch('admin_ajax.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok) {
                    if (typeof showAdminMsg === 'function') {
                        showAdminMsg(data.msg || '保存成功！', 'success');
                    } else { alert(data.msg || '保存成功！'); }
                } else {
                    if (typeof showAdminMsg === 'function') {
                        showAdminMsg(data.msg || '保存失败', 'error');
                    } else { alert('保存失败：' + (data.msg || '未知错误')); }
                }
            })
            .catch(function(err) {
                if (typeof showAdminMsg === 'function') {
                    showAdminMsg('网络错误：' + err.message, 'error');
                } else { alert('网络错误：' + err.message); }
            })
            .finally(function() {
                btn.disabled  = false;
                btn.innerHTML = orig;
            });
    });

    document.getElementById('previewLandingBtn').addEventListener('click', function() {
        var form   = document.createElement('form');
        form.method = 'POST';
        form.action = '../landing_preview.php';
        form.target = '_blank';
        var input   = document.createElement('input');
        input.type  = 'hidden';
        input.name  = 'landing_code';
        input.value = document.getElementById('landing_code').value;
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });
})();
</script>