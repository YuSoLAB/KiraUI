<?php
session_start();
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__FILE__));
}
define('ARTICLES_DIR', ROOT_DIR . '/articles/');
require_once ROOT_DIR . '/admin/admin_functions.php';
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die('没有权限访问此页面');
}
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die('无效的草稿ID');
}
$draft = loadDraftForEdit($id);
if (empty($draft)) {
    die('草稿不存在');
}
function parse_shortcodes($content) {
    $content = preg_replace_callback(
        '/\[image url="(.*?)" alt="(.*?)"\]/',
        function($matches) {
            $url = $matches[1];
            if (!preg_match('/^https?:\/\//i', $url)) {
                $url = 'https://' . $url;
            }
            return '<div style="margin: 15px 0; text-align: center;">
                        <img src="' . htmlspecialchars($url) . '" 
                            alt="' . htmlspecialchars($matches[2]) . '" 
                            style="max-width: 100%; border-radius: 8px; 
                                    box-shadow: 0 4px 12px rgba(155,140,255,.15);">
                    </div>';
        },
        $content
    );
    $content = preg_replace_callback(
        '/\[video url="(.*?)"\]/',
        function($matches) {
            $url = $matches[1];
            if (!preg_match('/^https?:\/\//i', $url)) {
                $url = 'https://' . $url;
            }
            return '<div style="margin: 15px 0;">
                        <video src="' . htmlspecialchars($url) . '" 
                            controls style="width: 100%; border-radius: 8px; 
                                            background: #f1f1f1;">
                            您的浏览器不支持视频播放
                        </video>
                    </div>';
        },
        $content
    );
    $content = preg_replace_callback(
        '/\[code lang="(.*?)"\](.*?)\[\/code\]/s',
        function($matches) {
            $lang = $matches[1] ? '语言: ' . htmlspecialchars($matches[1]) : '';
            return '<div style="margin: 15px 0; border-radius: 8px; overflow: hidden;">
                        <div style="padding: 6px 12px; background: #f1eaff; 
                                    border-bottom: 1px solid #e6d8ff; 
                                    font-size: 0.9em; color: #6c5dfb;">' . $lang . '</div>
                        <pre style="margin: 0; padding: 12px; background: #f9f9ff; 
                                   border: 1px solid #e6d8ff; border-top: none; 
                                   overflow-x: auto;"><code>' . 
                                        htmlspecialchars($matches[2]) . 
                                   '</code></pre>
                    </div>';
        },
        $content
    );
    $content = preg_replace_callback(
        '/\[link text="(.*?)" url="(.*?)"\]/',
        function($matches) {
            $url = $matches[2];
            if (!preg_match('/^https?:\/\//i', $url)) {
                $url = 'https://' . $url;
            }
            return '<a href="' . htmlspecialchars($url) . '" 
                    class="btn secondary" 
                    style="margin: 5px 0; display: inline-flex;
                            align-items: center; gap: 8px;
                            padding: 10px 16px; border-radius: 12px;
                            font-weight: 700; text-decoration: none;
                            background: #ffffffaa; border: 1.5px solid rgba(155,140,255,.55);
                            color: #6c5dfb; transition: all 0.2s ease;">
                    ' . htmlspecialchars($matches[1]) . '
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                </a>';
        },
        $content
    );
    $content = preg_replace_callback(
        '/\[download text="(.*?)" url="(.*?)"\]/',
        function($matches) {
            $url = $matches[2];
            if (!preg_match('/^https?:\/\//i', $url)) {
                $url = 'https://' . $url;
            }
            return '<a href="' . htmlspecialchars($url) . '" 
                    class="btn primary" 
                    style="margin: 5px 0; display: inline-flex;
                            align-items: center; gap: 8px;
                            padding: 10px 16px; border-radius: 12px;
                            font-weight: 700; text-decoration: none;
                            color: #fff; background: linear-gradient(180deg, #ff7ad9, #9b8cff);
                            border: 1px solid rgba(255,255,255,.5);
                            transition: all 0.2s ease;">
                    ' . htmlspecialchars($matches[1]) . '
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                </a>';
        },
        $content
    );
    $content = preg_replace_callback(
        '/\[encrypted_download text="(.*?)" url="(.*?)"\]/',
        function($matches) {
            $text = htmlspecialchars($matches[1]);
            $original_url = $matches[2];
            if (!preg_match('/^https?:\/\//i', $original_url)) {
                $original_url = 'https://' . $original_url;
            }
            $encrypt_id = bin2hex(random_bytes(16));
            $_SESSION['encrypted_downloads'][$encrypt_id] = $original_url;
            return '<button class="btn encrypted-download-btn" 
                            style="margin: 5px 0; display: inline-flex;
                                    align-items: center; gap: 8px;
                                    padding: 10px 16px; border-radius: 12px;
                                    font-weight: 700; text-decoration: none;
                                    color: #fff; background: linear-gradient(180deg, #4CAF50, #8BC34A);
                                    border: 1px solid rgba(255,255,255,.5);
                                    transition: all 0.2s ease; cursor: pointer;"
                            data-encrypt-id="' . $encrypt_id . '">
                        ' . $text . '
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                    </button>';
        },
        $content
    );
    return $content;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>草稿预览 - <?php echo htmlspecialchars($draft['title']); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
        }
        .preview-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        .article-title {
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .article-meta {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .article-tags {
            margin: 20px 0;
        }
        .tag {
            display: inline-block;
            background: #f1f1f1;
            padding: 4px 10px;
            border-radius: 4px;
            margin-right: 5px;
            font-size: 0.8em;
        }
        .article-content {
            line-height: 1.8;
            color: #333;
        }
        .preview-notice {
            background: #fff3cd;
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 0.9em;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="preview-container">
        <div class="preview-notice">
            ⚠️ 这是草稿预览，仅管理员可见，未发布到网站前端
        </div>
        <h1 class="article-title"><?php echo htmlspecialchars($draft['title']); ?></h1>
        <div class="article-meta">
            <span>日期: <?php echo $draft['date']; ?></span> |
            <span>字数: <?php echo $draft['word_count'] ?? 0; ?></span> |
            <span>阅读时间: <?php echo $draft['read_time'] ?? 5; ?> 分钟</span>
        </div>
        <div class="article-tags">
            <?php foreach ($draft['tags'] as $tag): ?>
                <span class="tag"><?php echo $tag; ?></span>
            <?php endforeach; ?>
        </div>
        <div class="article-content">
            <?php echo parse_shortcodes($draft['content']); ?>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.encrypted-download-btn').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const encryptId = this.getAttribute('data-encrypt-id');
                    this.disabled = true;
                    this.innerHTML = '处理中...';
                    try {
                        const response = await fetch('get_download_url.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'encrypt_id=' + encodeURIComponent(encryptId) + 
                                '&referrer=' + encodeURIComponent(window.location.href)
                        });                        
                        if (!response.ok) throw new Error('获取下载链接失败');                        
                        const data = await response.json();                        
                        if (data.success && data.url) {
                            const iframe = document.createElement('iframe');
                            iframe.style.display = 'none';
                            iframe.src = data.url;
                            document.body.appendChild(iframe);
                            setTimeout(() => iframe.remove(), 3000);
                            setTimeout(() => {
                                this.disabled = false;
                                this.innerHTML = this.innerHTML.replace('处理中...', '重新下载');
                            }, 1000);
                        } else {
                            throw new Error(data.message || '下载链接无效或已过期');
                        }
                    } catch (error) {
                        this.disabled = false;
                        this.innerHTML = '下载失败';
                        alert(error.message);
                        setTimeout(() => {
                            this.innerHTML = this.innerHTML.replace('下载失败', '重试下载');
                        }, 5000);
                    }
                });
            });
        });
    </script>
</body>
</html>