<?php
$currentPage = $_GET['page'] ?? 'cache';
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
    <div class="tab <?php echo $currentPage === 'footer' ? 'active' : ''; ?>" 
        data-tab="footer" data-url="?page=footer">页脚管理</div>
    <div class="tab <?php echo $currentPage === 'announcement' ? 'active' : ''; ?>" 
        data-tab="announcement" data-url="?page=announcement">弹窗公告管理</div>
    <div class="tab <?php echo $currentPage === 'comments' ? 'active' : ''; ?>" 
        data-tab="comments" data-url="?page=comments">评论管理</div>
</div>
