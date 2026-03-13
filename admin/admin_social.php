<?php
/**
 * admin_social.php — 社交展示管理
 * 管理员可在此设置各平台社交链接，保存后首页右下角自动显示对应图标。
 */
$config = Config::getInstance();

// 所有支持的社交平台定义
$socialPlatforms = [
    'qq'        => ['label' => 'QQ',         'placeholder' => 'https://qm.qq.com/... 或 QQ号',    'icon_color' => '#12B7F5'],
    'wechat'    => ['label' => '微信',        'placeholder' => '微信号或公众号链接',                 'icon_color' => '#07C160'],
    'weibo'     => ['label' => '微博',        'placeholder' => 'https://weibo.com/u/...',           'icon_color' => '#E6162D'],
    'x'         => ['label' => 'X (Twitter)', 'placeholder' => 'https://x.com/yourname',            'icon_color' => '#000000'],
    'facebook'  => ['label' => 'Facebook',    'placeholder' => 'https://facebook.com/yourpage',     'icon_color' => '#1877F2'],
    'instagram' => ['label' => 'Instagram',   'placeholder' => 'https://instagram.com/yourname',    'icon_color' => '#E4405F'],
    'youtube'   => ['label' => 'YouTube',     'placeholder' => 'https://youtube.com/@yourhandle',   'icon_color' => '#FF0000'],
    'github'    => ['label' => 'GitHub',      'placeholder' => 'https://github.com/yourname',       'icon_color' => '#24292E'],
    'steam'     => ['label' => 'Steam',       'placeholder' => 'https://steamcommunity.com/id/...', 'icon_color' => '#1b2838'],
    'tiktok'    => ['label' => 'TikTok',      'placeholder' => 'https://www.tiktok.com/@yourname',  'icon_color' => '#010101'],
    'douyin'    => ['label' => '抖音',        'placeholder' => '抖音主页链接',                      'icon_color' => '#010101'],
    'bilibili'  => ['label' => 'Bilibili',    'placeholder' => 'https://space.bilibili.com/...',    'icon_color' => '#FF6699'],
    'telegram'  => ['label' => 'Telegram',    'placeholder' => 'https://t.me/yourname',             'icon_color' => '#26A5E4'],
    'discord'   => ['label' => 'Discord',     'placeholder' => 'https://discord.gg/yourserver',     'icon_color' => '#5865F2'],
    'line'      => ['label' => 'LINE',        'placeholder' => 'https://line.me/ti/p/...',          'icon_color' => '#06C755'],
];

// 读取已保存的值
$currentValues = [];
foreach ($socialPlatforms as $key => $p) {
    $currentValues[$key] = $config->get('social_' . $key, '');
}
?>

<div class="admin-section">
    <div class="social-hdr">
        <div>
            <h2 class="social-hdr-title">🌐 社交展示管理</h2>
            <p class="social-hdr-sub">填写各平台链接后点击「保存」，首页右下角将自动显示已填写的社交图标。未填写的平台不会显示图标。</p>
        </div>
    </div>

    <div class="social-tip">
        💡 链接填写后会在前台首页右下角以悬浮图标形式展示，点击可跳转对应页面。清空链接并保存即可隐藏该图标。
    </div>

    <form id="socialForm">
        <div class="social-grid">
            <?php foreach ($socialPlatforms as $key => $platform): ?>
            <div class="social-field-card">
                <div class="social-field-header">
                    <span class="social-icon-preview" style="background:<?php echo $platform['icon_color']; ?>">
                        <?php echo getSocialSvgIcon($key, '#fff', 20); ?>
                    </span>
                    <label class="social-label" for="social_<?php echo $key; ?>">
                        <?php echo htmlspecialchars($platform['label']); ?>
                    </label>
                </div>
                <input
                    type="text"
                    id="social_<?php echo $key; ?>"
                    name="social_<?php echo $key; ?>"
                    class="social-input"
                    value="<?php echo htmlspecialchars($currentValues[$key]); ?>"
                    placeholder="<?php echo htmlspecialchars($platform['placeholder']); ?>"
                    autocomplete="off"
                >
                <div class="social-status <?php echo !empty($currentValues[$key]) ? 'social-status-set' : 'social-status-empty'; ?>">
                    <?php echo !empty($currentValues[$key]) ? '✓ 已设置' : '未设置'; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="social-save-row">
            <button type="button" class="btn btn-primary" onclick="saveSocialLinks()">
                💾 保存社交链接
            </button>
            <span id="socialSaveMsg" class="social-save-msg"></span>
        </div>
    </form>

    <!-- 预览区 -->
    <div class="social-preview-section">
        <h3 class="social-preview-title">📱 前台预览效果</h3>
        <p class="social-preview-sub">以下为已设置链接的图标预览（与首页右下角显示一致）</p>
        <div class="social-preview-bar" id="socialPreviewBar">
            <?php
            $hasAny = false;
            foreach ($socialPlatforms as $key => $platform):
                if (!empty($currentValues[$key])): $hasAny = true; ?>
                <a class="social-preview-icon" href="<?php echo htmlspecialchars($currentValues[$key]); ?>"
                   target="_blank" title="<?php echo htmlspecialchars($platform['label']); ?>"
                   style="background:<?php echo $platform['icon_color']; ?>">
                    <?php echo getSocialSvgIcon($key, '#fff', 22); ?>
                </a>
            <?php endif; endforeach;
            if (!$hasAny): ?>
                <span class="social-preview-empty">暂无已设置的社交链接</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function saveSocialLinks() {
    const form   = document.getElementById('socialForm');
    const inputs = form.querySelectorAll('.social-input');
    const data   = new FormData();
    data.append('type', 'config');
    data.append('config_action', 'save_social');
    inputs.forEach(inp => data.append(inp.name, inp.value.trim()));

    const btn = document.querySelector('.social-save-row .btn-primary');
    const msg = document.getElementById('socialSaveMsg');
    btn.disabled = true;
    btn.textContent = '保存中…';
    msg.textContent = '';
    msg.className = 'social-save-msg';

    fetch('admin_ajax.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            btn.disabled  = false;
            btn.textContent = '💾 保存社交链接';
            if (res.ok) {
                msg.textContent = '✓ ' + (res.msg || '保存成功！');
                msg.classList.add('social-save-ok');
                // 更新各字段状态指示
                inputs.forEach(inp => {
                    const card   = inp.closest('.social-field-card');
                    const status = card.querySelector('.social-status');
                    if (inp.value.trim()) {
                        status.textContent = '✓ 已设置';
                        status.className   = 'social-status social-status-set';
                    } else {
                        status.textContent = '未设置';
                        status.className   = 'social-status social-status-empty';
                    }
                });
            } else {
                msg.textContent = '✗ ' + (res.msg || '保存失败');
                msg.classList.add('social-save-err');
            }
        })
        .catch(() => {
            btn.disabled    = false;
            btn.textContent = '💾 保存社交链接';
            msg.textContent = '✗ 网络错误，请重试';
            msg.classList.add('social-save-err');
        });
}
</script>

<style>
/* ══════ 社交管理页样式 ══════ */
.social-hdr { display:flex; align-items:flex-start; justify-content:space-between;
    gap:1rem; margin-bottom:1rem; flex-wrap:wrap; }
.social-hdr-title { margin:0 0 .2rem; font-size:1.25rem; color:#6c5dfb; }
.social-hdr-sub { margin:0; font-size:.86rem; color:var(--sub,#888); }

.social-tip { padding:.5rem 1rem; margin-bottom:1.5rem;
    background:rgba(108,93,251,.06); border-left:3px solid #6c5dfb;
    border-radius:6px; font-size:.84rem; color:var(--sub,#777); }

/* ── Grid ── */
.social-grid { display:grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap:1rem; margin-bottom:1.5rem; }

.social-field-card { background:var(--admin-card,#fff);
    border:1px solid var(--admin-border,rgba(155,140,255,.3));
    border-radius:12px; padding:1rem; transition:box-shadow .2s; }
.social-field-card:hover { box-shadow:0 4px 18px rgba(108,93,251,.12); }

.social-field-header { display:flex; align-items:center; gap:.6rem; margin-bottom:.6rem; }

.social-icon-preview { width:36px; height:36px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0; box-shadow:0 2px 6px rgba(0,0,0,.15); }
.social-icon-preview svg { display:block; }

.social-label { font-size:.88rem; font-weight:700; color:inherit; }

.social-input { width:100%; box-sizing:border-box;
    padding:.42rem .7rem; border:1px solid var(--admin-border,rgba(155,140,255,.4));
    border-radius:8px; font-size:.85rem;
    background:var(--admin-card,#fff); color:inherit;
    transition:border-color .15s; }
.social-input:focus { outline:none; border-color:#6c5dfb;
    box-shadow:0 0 0 3px rgba(108,93,251,.1); }

.social-status { font-size:.72rem; margin-top:.35rem; font-weight:600; }
.social-status-set   { color:#27ae60; }
.social-status-empty { color:#bbb; }

/* ── Save row ── */
.social-save-row { display:flex; align-items:center; gap:1rem;
    margin-bottom:2rem; flex-wrap:wrap; }
.social-save-msg { font-size:.86rem; }
.social-save-ok  { color:#27ae60; }
.social-save-err { color:#c0392b; }

/* ── Preview ── */
.social-preview-section { border-top:1px solid var(--admin-border,rgba(155,140,255,.2));
    padding-top:1.25rem; }
.social-preview-title { margin:0 0 .2rem; font-size:1rem; color:#6c5dfb; }
.social-preview-sub { margin:0 0 1rem; font-size:.84rem; color:var(--sub,#888); }

.social-preview-bar { display:flex; flex-wrap:wrap; gap:.55rem; align-items:center;
    padding:1rem; background:rgba(155,140,255,.05);
    border:1px solid var(--admin-border,rgba(155,140,255,.2));
    border-radius:12px; min-height:60px; }

.social-preview-icon { width:42px; height:42px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    box-shadow:0 3px 10px rgba(0,0,0,.18);
    transition:transform .2s, box-shadow .2s; text-decoration:none; }
.social-preview-icon:hover { transform:scale(1.15) translateY(-3px);
    box-shadow:0 6px 18px rgba(0,0,0,.25); }
.social-preview-empty { color:var(--sub,#aaa); font-size:.85rem; }

/* ── Dark Mode ── */
body.dark-mode .social-hdr-title { color:var(--dark-vio,#b096ff); }
body.dark-mode .social-hdr-sub,
body.dark-mode .social-preview-sub { color:var(--dark-sub,#b0b0c5); }
body.dark-mode .social-tip { color:var(--dark-sub,#b0b0c5);
    background:rgba(176,160,255,.07); border-left-color:var(--dark-vio,#b096ff); }
body.dark-mode .social-field-card { background:var(--dark-admin-card,#2a2a42dd);
    border-color:var(--dark-admin-border); }
body.dark-mode .social-input { background:#2a2a42aa;
    border-color:var(--dark-admin-border); color:var(--dark-text,#eaeaea); }
body.dark-mode .social-input:focus { border-color:var(--dark-vio,#b096ff);
    box-shadow:0 0 0 3px rgba(176,160,255,.12); }
body.dark-mode .social-status-empty { color:#555; }
body.dark-mode .social-preview-bar { background:rgba(176,160,255,.04);
    border-color:var(--dark-admin-border); }
body.dark-mode .social-preview-title { color:var(--dark-vio,#b096ff); }
</style>

<?php
/**
 * 返回社交平台的 SVG 图标 HTML 字符串
 */
function getSocialSvgIcon(string $platform, string $color = '#fff', int $size = 24): string {
    $s = $size;
    switch ($platform) {
        case 'qq':
            // QQ 官方企鹅图标（Bootstrap Icons bi-tencent-qq，缩放至 24×24）
            return <<<SVG
<svg width="$s" height="$s" viewBox="0 0 16 16" fill="$color" xmlns="http://www.w3.org/2000/svg">
  <path d="M6.048 3.323c.022.277-.13.523-.338.55-.21.026-.397-.176-.419-.453-.022-.277.13-.523.338-.55.21-.026.397.176.42.453Zm2.265-.24c-.603-.146-.894.256-.936.333-.027.048-.008.117.037.15.045.035.092.025.119-.003.361-.39.751-.172.829-.129l.011.007c.053.024.147.028.193-.098.023-.063.017-.11-.006-.142-.016-.023-.089-.08-.247-.118Z"/>
  <path fill-rule="evenodd" d="M11.727 6.719c0-.022.01-.375.01-.557 0-3.07-1.45-6.156-5.015-6.156-3.564 0-5.014 3.086-5.014 6.156 0 .182.01.535.01.557l-.72 1.795a25.85 25.85 0 0 0-.534 1.508c-.68 2.187-.46 3.093-.292 3.113.36.044 1.401-1.647 1.401-1.647 0 .979.504 2.256 1.594 3.179-.408.126-.907.319-1.228.556-.29.213-.253.43-.201.518.228.386 3.92.246 4.985.126 1.065.12 4.756.26 4.984-.126.052-.088.088-.305-.2-.518-.322-.237-.822-.43-1.23-.557 1.09-.922 1.594-2.2 1.594-3.178 0 0 1.041 1.69 1.401 1.647.168-.02.388-.926-.292-3.113a25.78 25.78 0 0 0-.534-1.508l-.72-1.795ZM9.773 5.53c-.13-.286-1.431-.605-3.042-.605h-.017c-1.611 0-2.913.319-3.042.605a.096.096 0 0 0-.01.04c0 .022.008.04.018.056.11.159 1.554.943 3.034.943h.017c1.48 0 2.924-.784 3.033-.943a.095.095 0 0 0 .008-.096Zm-4.32-.989c-.483.022-.896-.529-.922-1.229-.026-.7.344-1.286.828-1.308.483-.022.896.529.922 1.23.027.7-.344 1.286-.827 1.307Zm2.538 0c.483.022.896-.529.922-1.229.026-.7-.344-1.286-.827-1.308-.484-.022-.896.529-.923 1.23-.026.7.344 1.285.828 1.307ZM2.928 8.99a10.674 10.674 0 0 0-.097 2.284c.146 2.45 1.6 3.99 3.846 4.012h.091c2.246-.023 3.7-1.562 3.846-4.011.054-.9 0-1.663-.097-2.285-1.312.26-2.669.41-3.786.396h-.017c-.297.003-.611-.005-.937-.023v2.148c-1.106.154-2.21-.068-2.21-.068V9.107a22.93 22.93 0 0 1-.639-.117Z"/>
</svg>
SVG;
        case 'wechat':
            return <<<SVG
<svg width="$s" height="$s" viewBox="0 0 24 24" fill="$color" xmlns="http://www.w3.org/2000/svg">
<path d="M8.69 4C5.03 4 2 6.57 2 9.72c0 1.77.98 3.36 2.52 4.41l-.71 2.14 2.36-1.18c.67.19 1.38.29 2.12.29.22 0 .44-.01.65-.03-.14-.44-.22-.9-.22-1.38 0-2.88 2.71-5.21 6.05-5.21h.44C14.64 6.24 11.93 4 8.69 4zM6.5 8.25a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5zm4.5 0a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5zM22 14.03c0-2.68-2.68-4.86-5.98-4.86s-5.97 2.18-5.97 4.86 2.67 4.86 5.97 4.86c.6 0 1.19-.08 1.74-.22l1.95.97-.58-1.76C20.57 17.1 22 15.67 22 14.03zm-8-.5a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5zm4 0a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5z"/>
</svg>
SVG;
        case 'weibo':
            return <<<SVG
<svg width="$s" height="$s" viewBox="0 0 24 24" fill="$color" xmlns="http://www.w3.org/2000/svg">
<path d="M10.09 4c-4.01.08-7.24 2.65-7.23 5.85 0 .54.09 1.07.26 1.57C2.42 11.8 2 12.3 2 12.87c0 .94.93 1.7 2.08 1.7.18 0 .35-.02.51-.06C5.33 16.37 7.6 18 10.24 18c3.26 0 5.91-2.12 5.91-4.73 0-2.32-2.11-4.28-5.02-4.67.16-.39.25-.82.25-1.27 0-1.83-1.49-3.31-3.33-3.33zm0 1.33c1.1 0 2 .89 2 2 0 .38-.11.74-.3 1.04A5.48 5.48 0 0 0 10.24 8c-.23 0-.46.01-.68.04-.15-.21-.24-.47-.24-.74 0-.71.56-1.99.77-1.97zm.15 5.34c2.45.07 4.43 1.68 4.43 3.6C14.67 16.2 12.68 18 10.24 18c-2.44 0-4.42-1.8-4.42-4.01 0-1.99 1.86-3.38 4.42-3.32zM17 7a2 2 0 0 0-2 2 2 2 0 0 0 2 2 2 2 0 0 0 2-2 2 2 0 0 0-2-2zm-6.5 4.5c-1.93 0-3.5 1.12-3.5 2.5S8.57 16.5 10.5 16.5 14 15.38 14 14s-1.57-2.5-3.5-2.5zm0 1c1.38 0 2.5.67 2.5 1.5S11.88 15.5 10.5 15.5 8 14.83 8 14s1.12-1.5 2.5-1.5z"/>
</svg>
SVG;
        case 'x':
            return <<<SVG
<svg width="$s" height="$s" viewBox="0 0 24 24" fill="$color" xmlns="http://www.w3.org/2000/svg">
<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.748l7.73-8.835L2.044 2.25h6.292l4.266 5.638 5.642-5.638zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
</svg>
SVG;
        case 'facebook':
            return <<<SVG
<svg width="$s" height="$s" viewBox="0 0 24 24" fill="$color" xmlns="http://www.w3.org/2000/svg">
<path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
</svg>
SVG;
        case 'instagram':
            return <<<SVG
<svg width="$s" height="$s" viewBox="0 0 24 24" fill="$color" xmlns="http://www.w3.org/2000/svg">
<path d="M12 0C8.74 0 8.333.015 7.053.072 5.775.132 4.905.333 4.14.63c-.789.306-1.459.717-2.126 1.384S.935 3.35.63 4.14C.333 4.905.131 5.775.072 7.053.012 8.333 0 8.74 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 2.16c3.203 0 3.585.016 4.85.071 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/>
</svg>
SVG;
        case 'youtube':
            return <<<SVG
<svg width="$s" height="$s" viewBox="0 0 24 24" fill="$color" xmlns="http://www.w3.org/2000/svg">
<path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
</svg>
SVG;
        case 'github':
            return <<<SVG
<svg width="$s" height="$s" viewBox="0 0 24 24" fill="$color" xmlns="http://www.w3.org/2000/svg">
<path d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0 1 12 6.844a9.59 9.59 0 0 1 2.504.337c1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.02 10.02 0 0 0 22 12.017C22 6.484 17.522 2 12 2z"/>
</svg>
SVG;
        case 'steam':
            return <<<SVG
<svg width="$s" height="$s" viewBox="0 0 24 24" fill="$color" xmlns="http://www.w3.org/2000/svg">
<path d="M11.979 0C5.678 0 .511 4.86.022 11.037l6.432 2.658c.545-.371 1.203-.59 1.912-.59.063 0 .125.004.188.006l2.861-4.142V8.91c0-2.495 2.028-4.524 4.524-4.524 2.494 0 4.524 2.029 4.524 4.524s-2.03 4.525-4.524 4.525h-.105l-4.076 2.911c0 .052.004.105.004.159 0 1.875-1.515 3.396-3.39 3.396-1.635 0-3.016-1.173-3.331-2.711L.436 15.27C1.862 20.307 6.486 24 11.979 24c6.627 0 11.999-5.373 11.999-12S18.605 0 11.979 0zM7.54 18.21l-1.473-.61c.262.543.714.999 1.314 1.25 1.297.539 2.793-.076 3.332-1.375.263-.63.264-1.319.005-1.949s-.75-1.121-1.377-1.383c-.624-.26-1.29-.249-1.878-.03l1.523.63c.956.4 1.409 1.497 1.01 2.452-.397.957-1.494 1.41-2.456 1.015zm11.415-9.303c0-1.662-1.353-3.015-3.015-3.015-1.665 0-3.015 1.353-3.015 3.015 0 1.665 1.35 3.015 3.015 3.015 1.662 0 3.015-1.35 3.015-3.015zm-5.273-.005c0-1.252 1.013-2.266 2.265-2.266 1.249 0 2.266 1.014 2.266 2.266 0 1.251-1.017 2.265-2.266 2.265-1.252 0-2.265-1.014-2.265-2.265z"/>
</svg>
SVG;
        case 'tiktok':
            return <<<SVG
<svg width="$s" height="$s" viewBox="0 0 24 24" fill="$color" xmlns="http://www.w3.org/2000/svg">
<path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.29 6.29 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V9.38a8.16 8.16 0 0 0 4.77 1.52V7.45a4.85 4.85 0 0 1-1-.76z"/>
</svg>
SVG;
        case 'douyin':
            return <<<SVG
<svg width="$s" height="$s" viewBox="0 0 24 24" fill="$color" xmlns="http://www.w3.org/2000/svg">
<path d="M16.6 5.82s.51.5 0 0A4.278 4.278 0 0 1 15.54 3h-3.09v12.4a2.592 2.592 0 0 1-2.59 2.5 2.592 2.592 0 0 1-2.59-2.59 2.592 2.592 0 0 1 2.59-2.59c.28 0 .54.04.79.1V9.64a6.13 6.13 0 0 0-.79-.05 5.73 5.73 0 0 0-5.73 5.73 5.73 5.73 0 0 0 5.73 5.73 5.73 5.73 0 0 0 5.73-5.73V8.91A7.315 7.315 0 0 0 19.4 10V6.94a4.315 4.315 0 0 1-2.8-1.12z"/>
</svg>
SVG;
        case 'bilibili':
            return <<<SVG
<svg width="$s" height="$s" viewBox="0 0 24 24" fill="$color" xmlns="http://www.w3.org/2000/svg">
<path d="M17.813 4.653h.854c1.51.054 2.769.578 3.773 1.574 1.004.995 1.524 2.249 1.56 3.76v7.36c-.036 1.51-.556 2.769-1.56 3.773s-2.262 1.524-3.773 1.56H5.333c-1.51-.036-2.769-.556-3.773-1.56S.036 18.858 0 17.347v-7.36c.036-1.511.556-2.765 1.56-3.76 1.004-.996 2.262-1.52 3.773-1.574h.774l-1.174-1.12a1.234 1.234 0 0 1-.373-.906c0-.356.124-.658.373-.907l.027-.027c.267-.249.573-.373.92-.373.347 0 .653.124.92.373L9.653 4.44c.071.071.134.142.187.213h4.267a.836.836 0 0 1 .16-.213l2.853-2.747c.267-.249.573-.373.92-.373.347 0 .662.151.929.4.267.249.391.551.391.907 0 .355-.124.657-.373.906zM5.333 7.24c-.746.018-1.373.276-1.88.773-.506.498-.769 1.13-.786 1.894v7.52c.017.764.28 1.395.786 1.893.507.498 1.134.756 1.88.773h13.334c.746-.017 1.373-.275 1.88-.773.506-.498.769-1.129.786-1.893v-7.52c-.017-.765-.28-1.396-.786-1.894-.507-.497-1.134-.755-1.88-.773zM8 11.107c.373 0 .684.124.933.373.25.249.383.569.4.96v1.173c-.017.391-.15.711-.4.96-.249.25-.56.374-.933.374s-.684-.125-.933-.374c-.25-.249-.383-.569-.4-.96V12.44c0-.373.129-.689.386-.947.258-.257.574-.386.947-.386zm8 0c.373 0 .684.124.933.373.25.249.383.569.4.96v1.173c-.017.391-.15.711-.4.96-.249.25-.56.374-.933.374s-.684-.125-.933-.374c-.25-.249-.383-.569-.4-.96V12.44c.017-.391.15-.711.4-.96.249-.249.56-.373.933-.373z"/>
</svg>
SVG;
        case 'telegram':
            return <<<SVG
<svg width="$s" height="$s" viewBox="0 0 24 24" fill="$color" xmlns="http://www.w3.org/2000/svg">
<path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
</svg>
SVG;
        case 'discord':
            return <<<SVG
<svg width="$s" height="$s" viewBox="0 0 24 24" fill="$color" xmlns="http://www.w3.org/2000/svg">
<path d="M20.317 4.492c-1.53-.69-3.17-1.2-4.885-1.49a.075.075 0 0 0-.079.036c-.21.369-.444.85-.608 1.23a18.566 18.566 0 0 0-5.487 0 12.36 12.36 0 0 0-.617-1.23A.077.077 0 0 0 8.562 3c-1.714.29-3.354.8-4.885 1.491a.07.07 0 0 0-.032.027C.533 9.093-.32 13.555.099 17.961a.08.08 0 0 0 .031.055 20.03 20.03 0 0 0 5.993 2.98.078.078 0 0 0 .084-.026c.462-.62.874-1.275 1.226-1.963.021-.04.001-.088-.041-.104a13.201 13.201 0 0 1-1.872-.878.075.075 0 0 1-.008-.125c.126-.093.252-.19.372-.287a.075.075 0 0 1 .078-.01c3.927 1.764 8.18 1.764 12.061 0a.075.075 0 0 1 .079.009c.12.098.245.195.372.288a.075.075 0 0 1-.006.125c-.598.344-1.22.635-1.873.877a.075.075 0 0 0-.041.105c.36.687.772 1.341 1.225 1.962a.077.077 0 0 0 .084.028 19.963 19.963 0 0 0 6.002-2.981.076.076 0 0 0 .032-.054c.5-5.094-.838-9.52-3.549-13.442a.06.06 0 0 0-.031-.028zM8.02 15.278c-1.182 0-2.157-1.069-2.157-2.38 0-1.312.956-2.38 2.157-2.38 1.21 0 2.176 1.077 2.157 2.38 0 1.312-.956 2.38-2.157 2.38zm7.975 0c-1.183 0-2.157-1.069-2.157-2.38 0-1.312.955-2.38 2.157-2.38 1.21 0 2.176 1.077 2.157 2.38 0 1.312-.946 2.38-2.157 2.38z"/>
</svg>
SVG;
        case 'line':
            return <<<SVG
<svg width="$s" height="$s" viewBox="0 0 24 24" fill="$color" xmlns="http://www.w3.org/2000/svg">
<path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.627-.63.349 0 .631.285.631.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.281.629-.629.629M24 10.314C24 4.943 18.615.5 12 .5S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/>
</svg>
SVG;
        default:
            return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="' . $color . '"><circle cx="12" cy="12" r="10"/></svg>';
    }
}