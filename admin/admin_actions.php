<?php
switch ($_POST['action']) {
    case 'clear_all':
        $cache->clear();
        $message = "所有缓存已清空！";
        break;
    case 'clear_expired':
        $cache->clearExpired();
        $message = "过期缓存已清理！";
        break;
    case 'rebuild_index':
        $articleIndex->buildIndex();
        $message = "文章索引已重建！";
        break;
    case 'clear_index':
        $articleIndex->clearIndex();
        $message = "文章索引已清空！";
        break;
    case 'move_to_draft':
        $id = $_POST['id'] ?? 0;
        if (moveToDraft($id)) {
            $message = "文章已移至草稿箱";
            header('Location: ?page=articles');
            exit;
        } else {
            $message = "移动失败";
        }
        break;
    case 'publish_draft':
        $id = $_POST['id'] ?? 0;
        if (publishFromDraft($id)) {
            $message = "草稿已发布为文章";
            header('Location: ?page=articles');
            exit;
        } else {
            $message = "发布失败";
        }
        break;
    case 'save_draft':
        $result = saveDraft($_POST);
        if ($result['success']) {
            $message = "草稿已保存";
            if (isset($result['id'])) {
                header('Location: ?page=edit_draft&edit=' . $result['id'] . '&saved=1');
                exit;
            }
        } else {
            $message = "保存失败: " . $result['error'];
        }
        break;
    case 'delete_draft':
        $id = $_POST['id'] ?? 0;
        if (deleteDraft($id)) {
            $message = "草稿已删除";
            header('Location: ?page=drafts');
            exit;
        } else {
            $message = "删除失败";
        }
        break;
    case 'save_article':
        $result = saveArticle($_POST);
        if ($result['success']) {
            $message = "文章已保存";
            if (isset($result['id'])) {
                $id = $result['id'];
                $cache->delete("article_{$id}");
                $cache->delete("article_content_{$id}");
                $cache->delete('article_index');
                $cache->delete('all_articles_basic');
                $articleIndex->buildIndex(); 
            }
            header('Location: ?page=edit_article&edit=' . $result['id'] . '&saved=1');
            exit;
        } else {
            $message = "保存失败: " . $result['error'];
        }
        break;
    case 'delete_article':
        $id = $_POST['id'] ?? 0;
        if (deleteArticle($id)) {
            $articleIndex->buildIndex();          
            $cache->delete('all_articles_basic');
            $cache->delete('article_index');
            $cache->delete("article_{$id}");
            $cache->delete("article_content_{$id}");
            $articles = $articleIndex->getIndex(true);            
            $message = "文章已删除";
            header('Location: ?page=articles');
            exit;
        } else {
            $message = "删除失败";
        }
        break;
}
?>