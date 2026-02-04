// 切换页面
function switchToPage(pageIdentifier) {
    if (!pageIdentifier) return;
    let activeTab = null;
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
        const url = tab.getAttribute('data-url');
        if (url && (url.includes(`page=${pageIdentifier}`) || tab.getAttribute('data-tab') === pageIdentifier)) {
            activeTab = tab;
        }
    });

    // 尝试匹配 data-tab 属性
    if (!activeTab) {
        activeTab = document.querySelector(`.tab[data-tab="${pageIdentifier}"]`);
    }

    // 激活 Tab 按钮
    let tabId = pageIdentifier;
    if (activeTab) {
        activeTab.classList.add('active');
        tabId = activeTab.getAttribute('data-tab'); 
    }

    // 隐藏所有内容区域
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

    const outerPaneId = tabId + '-content';
    const outerPane = document.getElementById(outerPaneId);
    if (outerPane) {
        outerPane.classList.add('active');
    }

    const innerContent = document.getElementById(tabId);
    if (innerContent) {
        innerContent.classList.add('active');
    } else if (outerPane) {
        // 如果找不到 ID 为 tabId 的元素，尝试查找 outerPane 内部的第一个 tab-content
        const nestedContent = outerPane.querySelector('.tab-content');
        if (nestedContent) {
            nestedContent.classList.add('active');
        }
    }
}

// 绑定点击事件
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', (e) => {
        e.preventDefault();
        
        const url = tab.getAttribute('data-url');
        const tabId = tab.getAttribute('data-tab');

        // 更新浏览器历史记录
        if (url) {
            history.pushState(null, '', url);
        }

        // 执行切换
        switchToPage(tabId);
    });
});

// 页面加载完成时初始化
document.addEventListener('DOMContentLoaded', function() {
    // 解析当前 URL 参数
    const urlParams = new URLSearchParams(window.location.search);
    const currentPage = urlParams.get('page') || 'siteinfo';
    
    // 执行初始化切换
    switchToPage(currentPage);

    // 初始化编辑器等其他功能
    initCodeEditors();
    initSparkles();
    initShortcodes();
    initWordCount();
});
// 初始化Sparkles特效
function initSparkles() {
    const box = document.createElement('div');
    box.className = 'sparkles';
    box.id = 'sparkles';
    box.setAttribute('aria-hidden', 'true');
    document.body.appendChild(box);
    const count = 40;
    const vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
    for (let i = 0; i < count; i++) {
        const s = document.createElement('i');
        const size = 6 + Math.random() * 10;
        s.style.width = s.style.height = size + 'px';
        s.style.left = (Math.random() * 100) + 'vw';
        s.style.top = (Math.random() * 100) + 'vh';
        s.style.animationDuration = (10 + Math.random() * 12) + 's';
        s.style.animationDelay = (Math.random() * -20) + 's';
        s.style.opacity = 0.4 + Math.random() * 0.6;
        box.appendChild(s);
    }
    if (vw < 480) {
        const kids = box.querySelectorAll('i');
        for (let j = 0; j < kids.length; j += 2) {
            kids[j].remove();
        }
    }
}
// 初始化编辑器
function initCodeEditors() {
    // 配置所有编辑器
    const editorConfigs = [
        {id: 'content_editor', target: 'content', rows: 15},
        {id: 'footer_content_editor', target: 'footer_content', rows: 8},
        {id: 'footer_css_editor', target: 'footer_css', rows: 8},
        {id: 'footer_js_editor', target: 'footer_js', rows: 8},
        {id: 'announcement_content_editor', target: 'announcement_content', rows: 8},
        {id: 'landing_code_editor', target: 'landing_code', rows: 10}
    ];

    editorConfigs.forEach(conf => {
        const editorContainer = document.getElementById(conf.id);
        const textarea = document.getElementById(conf.target);
        // 检查是否存在编辑器容器和textarea
        if (editorContainer && textarea) {
            const newTextarea = document.createElement('textarea');
            newTextarea.id = conf.target + '_editor';
            newTextarea.name = conf.target;
            newTextarea.value = textarea.value;
            newTextarea.rows = conf.rows;
            newTextarea.style.width = '100%';
            newTextarea.style.padding = '10px';
            newTextarea.style.border = '1px solid #ddd';
            newTextarea.style.borderRadius = '4px';
            newTextarea.style.fontFamily = 'Consolas, Monaco, monospace';
            newTextarea.style.fontSize = '14px';
            newTextarea.style.resize = 'vertical';
            
            // 替换容器内容
            editorContainer.innerHTML = '';
            editorContainer.appendChild(newTextarea);
            
            newTextarea.addEventListener('input', function() {
                textarea.value = newTextarea.value;
                const inputEvent = new Event('input', { bubbles: true });
                textarea.dispatchEvent(inputEvent);
            });
            
            // 为文章编辑器添加特殊处理
            if (conf.id === 'content_editor') {
                window.contentEditor = newTextarea;
                
                // 添加快捷键支持
                newTextarea.addEventListener('keydown', function(e) {
                    // Ctrl+S 保存
                    if (e.ctrlKey && e.key === 's') {
                        e.preventDefault();
                        const form = document.querySelector('form');
                        if(form) form.dispatchEvent(new Event('submit'));
                    }
                });
            }
        }
    });
}

function initWordCount() {
    function updateWordCount() {
        const contentTextarea = document.getElementById('content');
        if (!contentTextarea) return;
        const content = contentTextarea.value || '';
        const chineseChars = content.match(/[\u4e00-\u9fa5]/g) || [];
        const otherChars = content.replace(/[\u4e00-\u9fa5]/g, '').trim();
        const otherWords = otherChars ? otherChars.split(/\s+/).length : 0;
        const wordCount = chineseChars.length + otherWords;
        const readTime = Math.max(1, Math.floor(wordCount / 300));
        const wordCountSpan = document.getElementById('word-count');
        const readTimeSpan = document.getElementById('read-time');        
        if (wordCountSpan) {
            wordCountSpan.textContent = `字数: ${wordCount}`;
        }
        if (readTimeSpan) {
            readTimeSpan.textContent = `阅读时长: ${readTime} 分钟`;
        }
    }
    const contentTextarea = document.getElementById('content');
    if (contentTextarea) {
        updateWordCount();
        contentTextarea.addEventListener('input', updateWordCount);
    }
}

function initShortcodes() {
    const shortcodeButtons = document.querySelectorAll('.shortcode-btn');
    shortcodeButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();            
            const type = this.getAttribute('data-type');
            if (type) {
                insertShortcode(type);
            }
        });
    });
}

function insertShortcode(type) {
    const editor = window.contentEditor || document.getElementById('content');
    if (!editor) {
        console.error('未找到内容编辑器');
        return;
    }
    
    let shortcode = '';
    switch(type) {
        case 'image':
            shortcode = '[image url="图片URL" alt="图片描述"]';
            break;
        case 'video':
            shortcode = '[video url="视频URL"]';
            break;
        case 'code':
            shortcode = '[code lang="编程语言"]\n你的代码在这里\n[/code]';
            break;
        case 'link':
            shortcode = '[link text="链接文本" url="链接地址"]';
            break;
        case 'download':
            shortcode = '[download text="下载文件" url="文件URL"]';
            break;
        case 'encrypted_download':
            shortcode = '[encrypted_download text="加密下载" url="文件URL"]';
            break;
        default:
            console.warn('未知的短代码类型:', type);
            return;
    }
    
    // 插入短代码到textarea
    const startPos = editor.selectionStart;
    const endPos = editor.selectionEnd;
    const text = editor.value;
    
    editor.value = text.substring(0, startPos) + shortcode + text.substring(endPos);
    editor.focus();
    editor.selectionStart = editor.selectionEnd = startPos + shortcode.length;
    
    // 触发change事件
    const inputEvent = new Event('input', { bubbles: true });
    editor.dispatchEvent(inputEvent);
}