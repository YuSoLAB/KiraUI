<?php
/**
 * 侧边栏导航
 * 依赖：$activeTab
 */

$navItems = [
    'profile'  => [
        'label' => '个人信息',
        'icon'  => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    ],
    'security' => [
        'label' => '安全管理',
        'icon'  => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    ],
    'articles' => [
        'label' => '我的收藏',
        'icon'  => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
    ],
    'messages' => [
        'label' => '我的消息',
        'icon'  => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    ],
    'email_prefs' => [
        'label' => '消息设置',
        'icon' => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z M20 8l-8 5-8-5" stroke="currentColor" stroke-width="1.5" fill="none"/>',
    ],
];
?>
<div class="sidebar">
    <ul class="sidebar-menu">
        <?php foreach ($navItems as $tab => $item): ?>
        <li>
            <a href="?tab=<?php echo $tab; ?>"
               class="<?php echo $activeTab === $tab ? 'active' : ''; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <?php echo $item['icon']; ?>
                </svg>
                <?php echo $item['label']; ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</div>