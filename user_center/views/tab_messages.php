<?php
/**
 * 标签页：我的消息
 * POST 动作由 index.php switch 路由处理，此处仅负责展示。
 */

$isLoggedIn = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
$notifications = [];
$unreadCount   = 0;

if ($isLoggedIn) {
    require_once dirname(dirname(__DIR__)) . '/admin/comment_functions.php';
    $notifications = getUserNotifications($_SESSION['user']['id'], false, 60);
    foreach ($notifications as $n) {
        if (!$n['is_read']) $unreadCount++;
    }
}

// 站点根路径：/yusolab/user_center/index.php → dirname×2 → /yusolab
$siteRoot = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
?>

<div id="messages" class="tab-content <?php echo $activeTab === 'messages' ? 'active' : ''; ?>">
    <div class="profile-section">
        <div class="messages-header">
            <h2>
                我的消息
                <?php if ($isLoggedIn && $unreadCount > 0): ?>
                    <span class="notif-badge" id="notifBadge"><?php echo $unreadCount; ?></span>
                <?php endif; ?>
            </h2>
            <?php if ($isLoggedIn && !empty($notifications)): ?>
                <div class="messages-toolbar">
                    <?php if ($unreadCount > 0): ?>
                        <button class="btn-notif-action" id="markAllReadBtn" type="button">全部已读</button>
                    <?php endif; ?>
                    <button class="btn-notif-action btn-danger" id="deleteAllBtn" type="button">清空消息</button>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$isLoggedIn): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                <p>请先登录以查看消息。</p>
            </div>
        <?php elseif (empty($notifications)): ?>
            <div class="empty-state" id="emptyState">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                <p>暂无消息，当有人回复你的评论时会在这里通知你。</p>
            </div>
        <?php else: ?>
            <ul class="notif-list" id="notifList">
            <?php foreach ($notifications as $notif): ?>
                <?php
                    $isUnread    = !$notif['is_read'];
                    $replyAvatar = getCommentAvatar($notif['reply_email']);
                    $articleUrl  = $siteRoot . '/article.php?id=' . intval($notif['article_id'])
                                   . '#comment_' . intval($notif['comment_id']);
                    $parentSnippet = mb_strlen($notif['parent_content']) > 60
                        ? mb_substr($notif['parent_content'], 0, 60) . '…'
                        : $notif['parent_content'];
                    $ts   = strtotime($notif['created_at']);
                    $diff = time() - $ts;
                    if ($diff < 60)         $timeLabel = '刚刚';
                    elseif ($diff < 3600)   $timeLabel = floor($diff / 60) . ' 分钟前';
                    elseif ($diff < 86400)  $timeLabel = floor($diff / 3600) . ' 小时前';
                    elseif ($diff < 604800) $timeLabel = floor($diff / 86400) . ' 天前';
                    else                    $timeLabel = date('Y-m-d', $ts);
                ?>
                <li class="notif-item <?php echo $isUnread ? 'notif-unread' : ''; ?>"
                    data-id="<?php echo intval($notif['id']); ?>">
                    <img src="<?php echo htmlspecialchars($replyAvatar); ?>"
                         alt="<?php echo htmlspecialchars($notif['reply_name']); ?>"
                         class="notif-avatar">
                    <div class="notif-body">
                        <div class="notif-meta">
                            <span class="notif-actor"><?php echo htmlspecialchars($notif['reply_name']); ?></span>
                            回复了你在
                            <a href="<?php echo htmlspecialchars($articleUrl); ?>" class="notif-article" target="_blank">《<?php echo htmlspecialchars($notif['article_title']); ?>》</a>
                            的评论
                            <span class="notif-time"><?php echo $timeLabel; ?></span>
                            <?php if ($isUnread): ?><span class="notif-dot" title="未读"></span><?php endif; ?>
                        </div>
                        <blockquote class="notif-quote"><?php echo htmlspecialchars($parentSnippet); ?></blockquote>
                        <div class="notif-reply-content"><?php echo htmlspecialchars($notif['reply_content']); ?></div>
                        <div class="notif-actions">
                            <a href="<?php echo htmlspecialchars($articleUrl); ?>" class="notif-link" target="_blank">查看原文 →</a>
                            <button class="btn-delete-notif" type="button" data-id="<?php echo intval($notif['id']); ?>">✕ 删除</button>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
            </ul>
            <div class="empty-state" id="emptyState" style="display:none;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                <p>暂无消息，当有人回复你的评论时会在这里通知你。</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.messages-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem;flex-wrap:wrap;gap:.5rem}
.messages-header h2{margin:0;display:flex;align-items:center;gap:.5rem}
.messages-toolbar{display:flex;gap:.5rem;flex-wrap:wrap}
.notif-badge{display:inline-flex;align-items:center;justify-content:center;min-width:1.4rem;height:1.4rem;padding:0 .35rem;border-radius:999px;background:#e74c3c;color:#fff;font-size:.72rem;font-weight:700;line-height:1}
.btn-notif-action{padding:.3rem .85rem;border:1px solid #bbb;border-radius:6px;background:transparent;font-size:.82rem;color:#555;cursor:pointer;transition:background .15s,color .15s;white-space:nowrap}
.btn-notif-action:hover{background:#f0f0f0;color:#222}
.btn-notif-action.btn-danger{border-color:#e8a0a0;color:#c0392b}
.btn-notif-action.btn-danger:hover{background:#fdf0f0}
.notif-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.6rem}
.notif-item{display:flex;gap:.9rem;padding:.95rem 1rem;border-radius:10px;background:#f9f9f9;border:1px solid #ebebeb;transition:background .15s,opacity .25s,transform .25s;cursor:pointer}
.notif-item.notif-unread{background:#fffbf0;border-color:#f5dfa0}
.notif-item:hover{background:#f3f3f3}
.notif-item.notif-unread:hover{background:#fff7e0}
.notif-item.removing{opacity:0;transform:translateX(12px)}
.notif-avatar{width:42px;height:42px;border-radius:50%;object-fit:cover;flex-shrink:0;margin-top:2px}
.notif-body{flex:1;min-width:0}
.notif-meta{font-size:.88rem;color:#444;line-height:1.5;display:flex;flex-wrap:wrap;align-items:center;gap:.2rem .3rem}
.notif-actor{font-weight:600;color:#222}
.notif-article{color:#3a7bd5;text-decoration:none;font-weight:500}
.notif-article:hover{text-decoration:underline}
.notif-time{color:#999;font-size:.78rem;margin-left:.2rem}
.notif-dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:#e74c3c;margin-left:.15rem;vertical-align:middle;flex-shrink:0}
.notif-quote{margin:.45rem 0 .35rem;padding:.35rem .7rem;border-left:3px solid #ccc;color:#888;font-size:.82rem;line-height:1.5;white-space:pre-wrap;word-break:break-word;background:transparent}
.notif-reply-content{font-size:.9rem;color:#333;white-space:pre-wrap;word-break:break-word;line-height:1.6;margin-bottom:.5rem}
.notif-actions{margin-top:.35rem;display:flex;align-items:center;gap:.8rem}
.notif-link{font-size:.8rem;color:#3a7bd5;text-decoration:none}
.notif-link:hover{text-decoration:underline}
.btn-delete-notif{padding:.15rem .5rem;border:1px solid #ddd;border-radius:4px;background:transparent;font-size:.75rem;color:#aaa;cursor:pointer;transition:color .15s,border-color .15s}
.btn-delete-notif:hover{color:#c0392b;border-color:#e8a0a0}
.empty-state{display:flex;flex-direction:column;align-items:center;padding:3rem 1rem;color:#aaa;gap:.8rem}
.empty-state svg{width:48px;height:48px;opacity:.4}
.empty-state p{margin:0;font-size:.9rem}
</style>

<script>
(function(){
    var listEl   = document.getElementById('notifList');
    var emptyEl  = document.getElementById('emptyState');
    var badgeEl  = document.getElementById('notifBadge');
    var markBtn  = document.getElementById('markAllReadBtn');
    var delAllBtn= document.getElementById('deleteAllBtn');

    function post(params){
        return fetch(location.pathname,{
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:new URLSearchParams(params).toString()
        }).then(function(r){return r.json();}).catch(function(){return{success:false};});
    }

    function updateBadge(delta){
        if(!badgeEl)return;
        var n=parseInt(badgeEl.textContent,10)+delta;
        if(n<=0){badgeEl.remove();badgeEl=null;if(markBtn){markBtn.remove();markBtn=null;}}
        else{badgeEl.textContent=n;}
    }

    function checkEmpty(){
        if(!listEl)return;
        if(listEl.querySelectorAll('.notif-item').length===0){
            listEl.remove();listEl=null;
            if(emptyEl)emptyEl.style.display='';
            var tb=document.querySelector('.messages-toolbar');
            if(tb)tb.style.display='none';
        }
    }

    function removeItem(item){
        var wasUnread=item.classList.contains('notif-unread');
        item.classList.add('removing');
        setTimeout(function(){item.remove();checkEmpty();},270);
        if(wasUnread)updateBadge(-1);
    }

    // 点击条目 → 标为已读
    document.querySelectorAll('.notif-item.notif-unread').forEach(function(item){
        item.addEventListener('click',function(e){
            if(e.target.closest('.btn-delete-notif,.notif-link'))return;
            post({action:'mark_read',id:item.dataset.id}).then(function(res){
                if(!res.success)return;
                item.classList.remove('notif-unread');
                var dot=item.querySelector('.notif-dot');
                if(dot)dot.remove();
                updateBadge(-1);
            });
        });
    });

    // 单条删除
    document.querySelectorAll('.btn-delete-notif').forEach(function(btn){
        btn.addEventListener('click',function(e){
            e.stopPropagation();
            var item=btn.closest('.notif-item');
            post({action:'delete_notification',id:item.dataset.id}).then(function(res){
                if(res.success)removeItem(item);
            });
        });
    });

    // 全部已读
    if(markBtn){
        markBtn.addEventListener('click',function(){
            post({action:'mark_all_read'}).then(function(res){
                if(!res.success)return;
                document.querySelectorAll('.notif-item.notif-unread').forEach(function(item){
                    item.classList.remove('notif-unread');
                    var dot=item.querySelector('.notif-dot');
                    if(dot)dot.remove();
                });
                if(badgeEl){badgeEl.remove();badgeEl=null;}
                markBtn.remove();markBtn=null;
            });
        });
    }

    // 清空全部
    if(delAllBtn){
        delAllBtn.addEventListener('click',function(){
            if(!confirm('确定清空所有消息吗？'))return;
            post({action:'delete_all_notifications'}).then(function(res){
                if(!res.success)return;
                if(listEl){listEl.remove();listEl=null;}
                if(emptyEl)emptyEl.style.display='';
                if(badgeEl){badgeEl.remove();badgeEl=null;}
                var tb=document.querySelector('.messages-toolbar');
                if(tb)tb.style.display='none';
            });
        });
    }
})();
</script>