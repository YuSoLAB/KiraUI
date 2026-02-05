<?php
// 管理员缓存管理
?>
<div class="tab-content active" id="cache">
    <div class="section">
        <h2>缓存统计</h2>
        <div class="stats">
            <p>总缓存文件: <?php echo $stats['total_files'] ?? 0; ?></p>
            <p>有效缓存文件: <?php echo $stats['active_files'] ?? 0; ?></p>
            <p>缓存总大小: <?php echo $stats['total_size'] ?? '0 KB'; ?></p>
        </div>
        <div class="action-bar">
            <h3>缓存操作</h3>
            <div>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="clear_all">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('确定要清空所有缓存吗？')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                        清空所有缓存
                    </button>
                </form>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="clear_expired">
                    <button type="submit" class="btn btn-warning">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        清理过期缓存
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="section">
        <h2>索引管理</h2>
        <div class="stats-card">
            <h3>索引统计信息</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-label">总文章数</span>
                    <span class="stat-value">
                        <?php echo $index_stats['total_articles'] ?? 0; ?>
                    </span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">总字数</span>
                    <span class="stat-value">
                        <?php echo $index_stats['total_words'] ?? 0; ?>
                    </span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">标签数量</span>
                    <span class="stat-value">
                        <?php echo count($index_stats['tags'] ?? []); ?>
                    </span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">最后更新</span>
                    <span class="stat-value">
                        <?php echo date('Y-m-d H:i', time()); ?>
                    </span>
                </div>
            </div>
            <?php if (!empty($index_stats['tags'])): ?>
            <div class="tags-stats">
                <h4>热门标签</h4>
                <div class="tags-cloud">
                    <?php 
                    $topTags = array_slice($index_stats['tags'], 0, 10);
                    foreach ($topTags as $tag => $count): 
                    ?>
                    <span class="tag-item">
                        <?php echo htmlspecialchars($tag); ?> (<?php echo $count; ?>)
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div class="action-bar">
            <div></div>
            <div>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="rebuild_index">
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                        </svg>
                        重建文章索引
                    </button>
                </form>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="clear_index">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('确定要清空文章索引吗？')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                        清空文章索引
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="section">
        <h2>备份文件管理</h2>
        <?php
        $backupFiles = getBackupFiles();
        if (!empty($backupFiles)):
        ?>
        <div class="stats">
            <p>备份文件数量: <?php echo count($backupFiles); ?></p>
            <p>备份总大小: <?php 
                $totalSize = array_sum(array_column($backupFiles, 'size_bytes'));
                echo formatFileSize($totalSize);
            ?></p>
        </div>
        <div class="backup-files">
            <h3>备份文件列表</h3>
            <table class="file-table">
                <thead>
                    <tr>
                        <th>文件名</th>
                        <th>大小</th>
                        <th>创建时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backupFiles as $file): ?>
                    <tr>
                        <td>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                            <?php echo htmlspecialchars($file['name']); ?>
                        </td>
                        <td><?php echo $file['size']; ?></td>
                        <td><?php echo $file['date']; ?></td>
                        <td>
                            <form method="post" style="display: inline;" onsubmit="return confirm('确定要删除备份文件 <?php echo htmlspecialchars($file['name']); ?> 吗？此操作不可恢复！');">
                                <input type="hidden" name="action" value="delete_backup">
                                <input type="hidden" name="filename" value="<?php echo htmlspecialchars($file['name']); ?>">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                    删除
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="no-backups">
            <p>暂无备份文件</p>
        </div>
        <?php endif; ?>
    </div>
</div>