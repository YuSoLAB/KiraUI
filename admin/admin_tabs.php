<?php
// 选项卡配置（含菜单管理、页面管理与媒体库）
?>
<div class="tabs">
    <div class="tab <?php echo $currentPage === 'siteinfo' ? 'active' : ''; ?>" 
        data-tab="siteinfo" data-url="?page=siteinfo">信息管理</div>
    <div class="tab <?php echo $currentPage === 'cache' ? 'active' : ''; ?>" 
        data-tab="cache" data-url="?page=cache">缓存管理</div>
    <div class="tab <?php echo $currentPage === 'articles' ? 'active' : ''; ?>" 
        data-tab="articles" data-url="?page=articles">文章管理</div>
    <div class="tab <?php echo $currentPage === 'drafts' ? 'active' : ''; ?>" 
        data-tab="drafts" data-url="?page=drafts">草稿箱</div>
    <?php if (isset($currentArticle)): ?>
        <div class="tab <?php echo $currentPage === 'edit_article' ? 'active' : ''; ?>" 
            data-tab="edit-article" 
            data-url="?page=edit_article&edit=<?php echo $isNewArticle ? 'new' : ($currentArticle['id'] ?? ''); ?>">
            <?php echo $isNewArticle ? '发布新文章' : '编辑文章'; ?>
            <?php if ($isNewArticle): ?>
                <span class="new-article-indicator">新</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if (isset($currentDraft)): ?>
        <div class="tab <?php echo $currentPage === 'edit_draft' ? 'active' : ''; ?>" 
            data-tab="edit-draft" 
            data-url="?page=edit_draft&edit=<?php echo $isNewDraft ? 'new' : ($currentDraft['id'] ?? ''); ?>">
            <?php echo $isNewDraft ? '新建草稿' : '编辑草稿'; ?>
            <?php if ($isNewDraft): ?>
                <span class="new-article-indicator">新</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="tab <?php echo $currentPage === 'menus' ? 'active' : ''; ?>" 
        data-tab="menus" data-url="?page=menus">菜单管理</div>
    <div class="tab <?php echo $currentPage === 'pages' ? 'active' : ''; ?>" 
        data-tab="pages" data-url="?page=pages">页面管理</div>
    <div class="tab <?php echo $currentPage === 'media' ? 'active' : ''; ?>" 
        data-tab="media" data-url="?page=media">媒体库</div>
    <div class="tab <?php echo $currentPage === 'footer' ? 'active' : ''; ?>" 
        data-tab="footer" data-url="?page=footer">页脚管理</div>
    <div class="tab <?php echo $currentPage === 'announcement' ? 'active' : ''; ?>" 
        data-tab="announcement" data-url="?page=announcement">弹窗公告管理</div>
    <div class="tab <?php echo $currentPage === 'comments' ? 'active' : ''; ?>" 
        data-tab="comments" data-url="?page=comments">评论管理</div>
    <div class="tab <?php echo $currentPage === 'profile_review' ? 'active' : ''; ?>" 
        data-tab="profile_review" data-url="?page=profile_review">
        信息变更审核
        <?php
        // 显示待审核数量徽章
        try {
            $db = Db::getInstance();
            $cnt = (int)$db->query("SELECT COUNT(*) FROM pending_profile_changes WHERE status='pending'")->fetchColumn();
            if ($cnt > 0) {
                echo '<span style="display:inline-flex;align-items:center;justify-content:center;
                      background:#f87171;color:#fff;font-size:.65rem;font-weight:700;
                      min-width:16px;height:16px;border-radius:8px;padding:0 4px;
                      vertical-align:middle;margin-left:5px;">' . $cnt . '</span>';
            }
        } catch (Exception $e) {}
        ?>
    </div>
    <div class="tab <?php echo $currentPage === 'smtp' ? 'active' : ''; ?>" 
        data-tab="smtp" data-url="?page=smtp">SMTP管理</div>
    <div class="tab <?php echo $currentPage === 'email_notify' ? 'active' : ''; ?>" 
        data-tab="email_notify" data-url="?page=email_notify">邮件通知</div>
    <div class="tab <?php echo $currentPage === 'users' ? 'active' : ''; ?>" 
        data-tab="users" data-url="?page=users">用户管理</div>
    <div class="tab <?php echo $currentPage === 'user_badges' ? 'active' : ''; ?>" 
        data-tab="user_badges" data-url="?page=user_badges">认证管理</div>
    <div class="tab <?php echo $currentPage === 'landing' ? 'active' : ''; ?>" 
        data-tab="landing" data-url="?page=landing">展示页面管理</div>
    <div class="tab <?php echo $currentPage === 'social' ? 'active' : ''; ?>" 
        data-tab="social" data-url="?page=social">社交展示</div>
    <div class="tab <?php echo $currentPage === 'update' ? 'active' : ''; ?>" 
        data-tab="update" data-url="?page=update">系统更新</div>
</div>