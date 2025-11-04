document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        tab.classList.add('active');
        const tabId = tab.getAttribute('data-tab');
        const tabElement = document.getElementById(tabId);
        if (tabElement) {
            tabElement.classList.add('active');
        }        
        const url = tab.getAttribute('data-url');
        if (url) {
            history.pushState(null, null, url);
        }
    });
});

(function(){
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
})();

function initCodeEditors() {
    if (document.getElementById('content_editor')) {
        const contentTextarea = document.getElementById('content');
        window.contentEditor = CodeMirror(document.getElementById('content_editor'), {
            value: contentTextarea.value,
            mode: 'htmlmixed',
            theme: 'dracula',
            lineNumbers: true,
            autoCloseTags: true,
            lineWrapping: true,
            height: '400px',
            extraKeys: {
                'Ctrl-S': function(cm) {
                    document.querySelector('form').dispatchEvent(new Event('submit'));
                }
            }
        });
        
        window.contentEditor.on('change', function() {
            contentTextarea.value = window.contentEditor.getValue();
            const inputEvent = new Event('input', { bubbles: true });
            contentTextarea.dispatchEvent(inputEvent);
        });
    }
    
    if (document.getElementById('footer_content_editor')) {
        const textarea = document.getElementById('footer_content');
        const editor = CodeMirror(document.getElementById('footer_content_editor'), {
            value: textarea.value,
            mode: 'htmlmixed',
            theme: 'dracula',
            lineNumbers: true,
            autoCloseTags: true,
            lineWrapping: true,
            height: '300px'
        });
        
        editor.on('change', function() {
            textarea.value = editor.getValue();
        });
    }
    
    if (document.getElementById('footer_css_editor')) {
        const textarea = document.getElementById('footer_css');
        const editor = CodeMirror(document.getElementById('footer_css_editor'), {
            value: textarea.value,
            mode: 'css',
            theme: 'dracula',
            lineNumbers: true,
            lineWrapping: true,
            height: '200px'
        });
        
        editor.on('change', function() {
            textarea.value = editor.getValue();
        });
    }
    
    if (document.getElementById('footer_js_editor')) {
        const textarea = document.getElementById('footer_js');
        const editor = CodeMirror(document.getElementById('footer_js_editor'), {
            value: textarea.value,
            mode: 'javascript',
            theme: 'dracula',
            lineNumbers: true,
            lineWrapping: true,
            height: '200px'
        });
        
        editor.on('change', function() {
            textarea.value = editor.getValue();
        });
    }
    
    if (document.getElementById('announcement_content_editor')) {
        const textarea = document.getElementById('announcement_content');
        const editor = CodeMirror(document.getElementById('announcement_content_editor'), {
            value: textarea.value,
            mode: 'htmlmixed',
            theme: 'dracula',
            lineNumbers: true,
            autoCloseTags: true,
            lineWrapping: true,
            height: '300px'
        });
        
        editor.on('change', function() {
            textarea.value = editor.getValue();
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    initCodeEditors();
    const tabs = document.querySelectorAll('.tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            const tabId = this.getAttribute('data-tab');
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('active');
            });
            const contentElement = document.getElementById(tabId + '-content');
            if (contentElement) {
                contentElement.classList.add('active');
            }
        });
    });

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
    
    function insertShortcode(type) {
        const contentTextarea = document.getElementById('content');
        let editor = window.contentEditor;
        
        if (!contentTextarea && !editor) {
            console.error('未找到内容编辑器');
            return;
        }
        
        let shortcode = '';
        let cursorPos = 0;
        let selectionEnd = 0;
        
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
        
        if (editor) {
            const doc = editor.getDoc();
            const cursor = doc.getCursor();
            const selection = doc.getSelection();
            if (selection) {
                doc.replaceSelection(shortcode);
            } else {
                doc.replaceRange(shortcode, cursor);
            }
            
            editor.trigger('change');
            
        } else if (contentTextarea) {
            cursorPos = contentTextarea.selectionStart;
            selectionEnd = contentTextarea.selectionEnd;            
            const content = contentTextarea.value;
            const newContent = content.substring(0, cursorPos) + shortcode + content.substring(selectionEnd);
            contentTextarea.value = newContent;            
            const newCursorPos = cursorPos + shortcode.length;
            contentTextarea.setSelectionRange(newCursorPos, newCursorPos);
            contentTextarea.focus();
            const inputEvent = new Event('input', { bubbles: true });
            contentTextarea.dispatchEvent(inputEvent);
        }
        
        console.log('短代码插入完成:', type);
    }
});