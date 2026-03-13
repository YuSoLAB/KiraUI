<?php
/**
 * admin_profile_review.php
 * 信息变更审核管理面板
 * 依赖：$db（来自 admin.php 上下文）、Config::getInstance()
 */

$config      = Config::getInstance();
$reviewEnabled = $config->get('profile_review_enabled', '0') === '1';

// 分页
$perPage    = 20;
$statusFilter = $_GET['review_status'] ?? 'pending';
$allowedStatuses = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($statusFilter, $allowedStatuses, true)) { $statusFilter = 'pending'; }

$whereStatus = $statusFilter === 'all' ? '' : "WHERE p.status = " . $db->quote($statusFilter);

$totalStmt = $db->query(
    "SELECT COUNT(*) FROM pending_profile_changes p $whereStatus"
);
$total    = (int)$totalStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page     = max(1, min((int)($_GET['rp'] ?? 1), $totalPages));
$offset   = ($page - 1) * $perPage;

$pendingStmt = $db->prepare(
    "SELECT p.*, u.username, u.nickname AS current_nickname, u.avatar AS current_avatar
       FROM pending_profile_changes p
       JOIN users u ON u.id = p.user_id
     $whereStatus
     ORDER BY p.created_at DESC
     LIMIT :limit OFFSET :offset"
);
$pendingStmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$pendingStmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$pendingStmt->execute();
$pendingItems = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

// 待处理数量（用于徽章）
$pendingCountStmt = $db->query("SELECT COUNT(*) FROM pending_profile_changes WHERE status='pending'");
$pendingCount = (int)$pendingCountStmt->fetchColumn();

// 显示名称辅助
function displayName(array $row): string {
    return htmlspecialchars($row['nickname'] ?? $row['username'] ?? '(未知)');
}
?>

<div class="section">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
        <h2 style="margin:0;">
            信息变更审核
            <?php if ($pendingCount > 0): ?>
                <span style="display:inline-flex;align-items:center;justify-content:center;
                             background:#f87171;color:#fff;font-size:.72rem;font-weight:700;
                             min-width:20px;height:20px;border-radius:10px;padding:0 5px;
                             vertical-align:middle;margin-left:6px;"><?php echo $pendingCount; ?></span>
            <?php endif; ?>
        </h2>

        <!-- 审核开关 -->
        <label class="review-toggle-wrap" style="display:flex;align-items:center;gap:10px;cursor:pointer;">
            <span style="font-size:.88rem;color:var(--sub,#888);">启用审核</span>
            <div class="toggle-switch <?php echo $reviewEnabled ? 'on' : ''; ?>"
                 id="reviewToggle"
                 title="<?php echo $reviewEnabled ? '点击关闭审核' : '点击启用审核'; ?>">
                <div class="toggle-knob"></div>
            </div>
            <span id="reviewToggleLabel" style="font-size:.88rem;font-weight:600;
                  color:<?php echo $reviewEnabled ? '#4ade80' : '#f87171'; ?>;">
                <?php echo $reviewEnabled ? '已启用' : '已关闭'; ?>
            </span>
        </label>
    </div>

    <p style="font-size:.84rem;color:var(--sub,#888);margin-bottom:20px;">
        启用后，用户提交的昵称和头像变更将进入待审核队列，通过审核后才会公开展示。
    </p>

    <!-- 状态筛选 Tab -->
    <div style="display:flex;gap:6px;margin-bottom:18px;flex-wrap:wrap;">
        <?php
        $filterLabels = [
            'pending'  => ['label' => '待审核', 'color' => '#f59e0b'],
            'approved' => ['label' => '已通过', 'color' => '#4ade80'],
            'rejected' => ['label' => '已拒绝', 'color' => '#f87171'],
            'all'      => ['label' => '全部',   'color' => '#9b8cff'],
        ];
        foreach ($filterLabels as $sv => $meta):
            $active = $statusFilter === $sv;
        ?>
        <a href="?page=profile_review&review_status=<?php echo $sv; ?>"
           style="padding:5px 14px;border-radius:20px;font-size:.82rem;font-weight:600;
                  text-decoration:none;transition:all .2s;
                  background:<?php echo $active ? $meta['color'] : 'rgba(155,140,255,.1)'; ?>;
                  color:<?php echo $active ? '#fff' : 'var(--sub,#888)'; ?>;
                  border:1px solid <?php echo $active ? $meta['color'] : 'rgba(155,140,255,.2)'; ?>;">
            <?php echo $meta['label']; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($pendingItems)): ?>
        <div style="text-align:center;padding:60px 20px;color:var(--sub,#888);font-size:.9rem;">
            <div style="font-size:2.5rem;margin-bottom:12px;">✅</div>
            <?php echo $statusFilter === 'pending' ? '暂无待审核的变更申请' : '该分类下暂无记录'; ?>
        </div>
    <?php else: ?>
        <div class="review-table-wrap" style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.88rem;">
                <thead>
                    <tr style="border-bottom:2px solid var(--admin-border,rgba(155,140,255,.25));">
                        <th style="padding:10px 12px;text-align:left;white-space:nowrap;">用户</th>
                        <th style="padding:10px 12px;text-align:left;white-space:nowrap;">变更类型</th>
                        <th style="padding:10px 12px;text-align:left;">变更内容</th>
                        <th style="padding:10px 12px;text-align:left;white-space:nowrap;">申请时间</th>
                        <th style="padding:10px 12px;text-align:left;white-space:nowrap;">状态</th>
                        <?php if ($statusFilter === 'pending' || $statusFilter === 'all'): ?>
                        <th style="padding:10px 12px;text-align:center;white-space:nowrap;">操作</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pendingItems as $item):
                    $isPending = $item['status'] === 'pending';
                    $isAvatar  = $item['type'] === 'avatar';
                    $pendingAvatarUrl = '../uploads/avatars/' . htmlspecialchars($item['new_value']);
                    $currentAvatarUrl = $item['current_avatar']
                        ? '../uploads/avatars/' . htmlspecialchars($item['current_avatar'])
                        : '../img/default-avatar.png';
                ?>
                <tr style="border-bottom:1px solid var(--admin-border,rgba(155,140,255,.15));
                           transition:background .15s;"
                    onmouseover="this.style.background='var(--admin-hover,rgba(155,140,255,.07))'"
                    onmouseout="this.style.background=''">
                    <!-- 用户 -->
                    <td style="padding:12px;">
                        <div style="font-weight:600;"><?php echo displayName($item); ?></div>
                        <div style="font-size:.78rem;color:var(--sub,#888);">@<?php echo htmlspecialchars($item['username']); ?></div>
                    </td>

                    <!-- 变更类型 -->
                    <td style="padding:12px;">
                        <span style="display:inline-block;padding:2px 10px;border-radius:12px;font-size:.78rem;font-weight:600;
                              background:<?php echo $isAvatar ? 'rgba(99,102,241,.15)' : 'rgba(251,191,36,.15)'; ?>;
                              color:<?php echo $isAvatar ? '#818cf8' : '#fbbf24'; ?>;">
                            <?php echo $isAvatar ? '🖼️ 头像' : '✏️ 昵称'; ?>
                        </span>
                    </td>

                    <!-- 变更内容预览 -->
                    <td style="padding:12px;">
                        <?php if ($isAvatar): ?>
                            <div style="display:flex;align-items:center;gap:14px;">
                                <div style="text-align:center;font-size:.72rem;color:var(--sub,#888);">
                                    <img src="<?php echo $currentAvatarUrl; ?>" alt="当前"
                                         style="width:44px;height:44px;border-radius:50%;object-fit:cover;display:block;
                                                border:2px solid rgba(155,140,255,.25);">
                                    <span style="margin-top:3px;display:block;">当前</span>
                                </div>
                                <span style="font-size:1.2rem;color:var(--sub,#888);">→</span>
                                <div style="text-align:center;font-size:.72rem;color:var(--sub,#888);">
                                    <img src="<?php echo $pendingAvatarUrl; ?>" alt="申请"
                                         style="width:44px;height:44px;border-radius:50%;object-fit:cover;display:block;
                                                border:2px solid rgba(108,93,251,.5);">
                                    <span style="margin-top:3px;display:block;">申请</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                <span style="color:var(--sub,#888);text-decoration:line-through;font-size:.83rem;">
                                    <?php echo htmlspecialchars($item['current_nickname'] ?: $item['username']); ?>
                                </span>
                                <span style="color:var(--sub,#888);">→</span>
                                <span style="font-weight:600;color:var(--text,#222);">
                                    <?php echo htmlspecialchars($item['new_value']); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </td>

                    <!-- 时间 -->
                    <td style="padding:12px;color:var(--sub,#888);white-space:nowrap;font-size:.82rem;">
                        <?php echo date('m-d H:i', strtotime($item['created_at'])); ?>
                    </td>

                    <!-- 状态 -->
                    <td style="padding:12px;white-space:nowrap;">
                        <?php
                        $statusMap = [
                            'pending'  => ['label' => '待审核', 'color' => '#f59e0b'],
                            'approved' => ['label' => '已通过', 'color' => '#4ade80'],
                            'rejected' => ['label' => '已拒绝', 'color' => '#f87171'],
                        ];
                        $sm = $statusMap[$item['status']] ?? ['label' => $item['status'], 'color' => '#888'];
                        ?>
                        <span style="display:inline-block;padding:2px 10px;border-radius:12px;font-size:.78rem;
                              font-weight:600;background:<?php echo $sm['color']; ?>22;color:<?php echo $sm['color']; ?>;">
                            <?php echo $sm['label']; ?>
                        </span>
                        <?php if ($item['status'] === 'rejected' && $item['reject_reason']): ?>
                            <div style="font-size:.75rem;color:#f87171;margin-top:4px;max-width:160px;word-break:break-all;">
                                <?php echo htmlspecialchars($item['reject_reason']); ?>
                            </div>
                        <?php endif; ?>
                    </td>

                    <!-- 操作按钮（仅待审核显示） -->
                    <?php if ($statusFilter === 'pending' || $statusFilter === 'all'): ?>
                    <td style="padding:12px;text-align:center;white-space:nowrap;">
                        <?php if ($isPending): ?>
                        <button class="btn-review-approve" data-id="<?php echo $item['id']; ?>"
                                style="padding:5px 14px;border-radius:8px;border:none;cursor:pointer;
                                       font-size:.82rem;font-weight:600;margin-right:6px;
                                       background:rgba(74,222,128,.15);color:#4ade80;
                                       transition:background .2s;">✓ 通过</button>
                        <button class="btn-review-reject" data-id="<?php echo $item['id']; ?>"
                                style="padding:5px 14px;border-radius:8px;border:none;cursor:pointer;
                                       font-size:.82rem;font-weight:600;
                                       background:rgba(248,113,113,.15);color:#f87171;
                                       transition:background .2s;">✗ 拒绝</button>
                        <?php else: ?>
                        <span style="color:var(--sub,#888);font-size:.78rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- 分页 -->
        <?php if ($totalPages > 1): ?>
        <div style="display:flex;justify-content:center;gap:6px;margin-top:20px;flex-wrap:wrap;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=profile_review&review_status=<?php echo $statusFilter; ?>&rp=<?php echo $i; ?>"
                   style="display:inline-flex;align-items:center;justify-content:center;
                          width:34px;height:34px;border-radius:8px;font-size:.85rem;
                          text-decoration:none;transition:all .2s;
                          background:<?php echo $i === $page ? '#6c5dfb' : 'rgba(155,140,255,.12)'; ?>;
                          color:<?php echo $i === $page ? '#fff' : 'var(--sub,#888)'; ?>;">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- 拒绝原因弹窗 -->
<div id="rejectModal" style="display:none;position:fixed;inset:0;z-index:9999;
     background:rgba(10,8,28,.72);backdrop-filter:blur(6px);
     align-items:center;justify-content:center;">
    <div style="background:var(--admin-card,#fff);border:1px solid var(--admin-border,rgba(155,140,255,.3));
                border-radius:14px;padding:28px;width:min(420px,90vw);
                box-shadow:0 20px 60px rgba(0,0,0,.35);">
        <h3 style="margin:0 0 16px;font-size:1rem;">填写拒绝原因（可选）</h3>
        <input type="hidden" id="rejectId" value="">
        <textarea id="rejectReason" rows="3"
                  placeholder="请简短说明拒绝原因，将显示给用户..."
                  style="width:100%;padding:10px;border-radius:8px;font-size:.88rem;resize:vertical;
                         border:1px solid var(--admin-border,rgba(155,140,255,.3));
                         background:var(--admin-bg,#f9f2ff);color:inherit;box-sizing:border-box;">
        </textarea>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
            <button id="rejectCancel"
                    style="padding:8px 18px;border-radius:8px;border:1px solid var(--admin-border,rgba(155,140,255,.3));
                           background:transparent;cursor:pointer;font-size:.88rem;">取消</button>
            <button id="rejectConfirm"
                    style="padding:8px 18px;border-radius:8px;border:none;
                           background:#f87171;color:#fff;cursor:pointer;font-size:.88rem;font-weight:600;">
                确认拒绝
            </button>
        </div>
    </div>
</div>

<style>
.toggle-switch {
    position: relative;
    width: 44px;
    height: 24px;
    border-radius: 12px;
    background: rgba(155,140,255,.25);
    border: 1px solid rgba(155,140,255,.3);
    cursor: pointer;
    transition: background .25s, border-color .25s;
}
.toggle-switch.on {
    background: #4ade80;
    border-color: #4ade80;
}
.toggle-knob {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 4px rgba(0,0,0,.25);
    transition: left .25s;
}
.toggle-switch.on .toggle-knob {
    left: 22px;
}
.btn-review-approve:hover { background: rgba(74,222,128,.3) !important; }
.btn-review-reject:hover  { background: rgba(248,113,113,.3) !important; }
</style>

<script>
(function () {
    const toggleEl    = document.getElementById('reviewToggle');
    const toggleLabel = document.getElementById('reviewToggleLabel');

    /* ── 审核开关 ── */
    if (toggleEl) {
        toggleEl.addEventListener('click', function () {
            const isOn  = toggleEl.classList.contains('on');
            const newVal = isOn ? '0' : '1';

            fetch('admin_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'type=profile_review&action=toggle_setting&enabled=' + newVal
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    toggleEl.classList.toggle('on', newVal === '1');
                    toggleLabel.textContent = newVal === '1' ? '已启用' : '已关闭';
                    toggleLabel.style.color = newVal === '1' ? '#4ade80' : '#f87171';
                } else {
                    alert('保存失败：' + (data.msg || '未知错误'));
                }
            });
        });
    }

    /* ── 通过 ── */
    document.querySelectorAll('.btn-review-approve').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            if (!confirm('确认通过该变更申请？')) return;
            reviewAction(id, 'approve', '');
        });
    });

    /* ── 拒绝弹窗 ── */
    const modal        = document.getElementById('rejectModal');
    const rejectIdEl   = document.getElementById('rejectId');
    const rejectReason = document.getElementById('rejectReason');

    document.querySelectorAll('.btn-review-reject').forEach(btn => {
        btn.addEventListener('click', function () {
            rejectIdEl.value    = this.dataset.id;
            rejectReason.value  = '';
            modal.style.display = 'flex';
        });
    });

    document.getElementById('rejectCancel').addEventListener('click', () => {
        modal.style.display = 'none';
    });
    modal.addEventListener('click', e => {
        if (e.target === modal) modal.style.display = 'none';
    });

    document.getElementById('rejectConfirm').addEventListener('click', () => {
        const id     = rejectIdEl.value;
        const reason = rejectReason.value.trim();
        modal.style.display = 'none';
        reviewAction(id, 'reject', reason);
    });

    function reviewAction(id, action, reason) {
        fetch('admin_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'type=profile_review&action=' + action
                + '&id=' + encodeURIComponent(id)
                + '&reason=' + encodeURIComponent(reason)
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                location.reload();
            } else {
                alert('操作失败：' + (data.msg || '未知错误'));
            }
        });
    }
})();
</script>