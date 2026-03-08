<?php
?>
<div class="header-wrapper">
    <div class="header">
        <div class="header-brand">
            <div class="header-logo" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <rect x="3" y="3" width="7" height="7" rx="2"></rect>
                    <rect x="14" y="3" width="7" height="7" rx="2"></rect>
                    <rect x="3" y="14" width="7" height="7" rx="2"></rect>
                    <rect x="14" y="14" width="7" height="7" rx="2"></rect>
                </svg>
            </div>
            <h1>网站管理后台</h1>
        </div>
        <div class="header-actions">
            <button id="themeToggle" class="theme-toggle" aria-label="切换主题">🌙</button>
            <a href="?action=logout" class="btn btn-danger btn-logout">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                <span class="logout-text">退出登录</span>
            </a>
        </div>
    </div>
</div>