</div><!-- /.user-center-content -->
        </div><!-- /.user-center-card -->
    </div><!-- /.user-center-wrap -->

    <button id="themeToggle" class="theme-toggle" style="display: none;">🌙</button>

    <script>
        // 立即执行主题初始化，防止闪烁
        (function () {
            const savedTheme = localStorage.getItem('theme');
            const body = document.body;
            const btn  = document.getElementById('themeToggleHeader');
            if (savedTheme === 'dark') {
                body.classList.add('dark-mode');
                if (btn) btn.textContent = '☀️';
            } else {
                if (btn) btn.textContent = '🌙';
            }
        })();

        document.addEventListener('DOMContentLoaded', function () {
            // ── 主题切换 ──────────────────────────────────────────
            const btn = document.getElementById('themeToggleHeader');
            if (btn) {
                btn.textContent = document.body.classList.contains('dark-mode') ? '☀️' : '🌙';
                btn.addEventListener('click', function () {
                    document.body.classList.toggle('dark-mode');
                    const isDark = document.body.classList.contains('dark-mode');
                    this.textContent = isDark ? '☀️' : '🌙';
                    localStorage.setItem('theme', isDark ? 'dark' : 'light');
                });
            }

            createSparkles();
        });

        // ── 头像文件选择预览 ────────────────────────────────────────
        document.getElementById('avatar-upload').addEventListener('change', function (e) {
            const file            = e.target.files[0];
            const uploadButton    = document.getElementById('uploadButton');
            const previewContainer = document.getElementById('previewContainer');
            const avatarPreview   = document.getElementById('avatarPreview');
            const currentAvatar   = document.getElementById('currentAvatar');

            if (!file) {
                uploadButton.disabled = true;
                previewContainer.style.display = 'none';
                currentAvatar.style.display = 'block';
                return;
            }

            const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
            const maxSize    = 20 * 1024 * 1024;

            if (!validTypes.includes(file.type)) {
                alert('请选择 JPEG、PNG 或 GIF 格式的图片');
                this.value = '';
                uploadButton.disabled = true;
                return;
            }
            if (file.size > maxSize) {
                alert('图片大小不能超过 20MB');
                this.value = '';
                uploadButton.disabled = true;
                return;
            }

            const reader = new FileReader();
            reader.onload = function (event) {
                avatarPreview.src = event.target.result;
                previewContainer.style.display = 'block';
                currentAvatar.style.display = 'none';
            };
            reader.readAsDataURL(file);
            uploadButton.disabled = false;
        });

        // ── 头像 AJAX 上传 ──────────────────────────────────────────
        document.getElementById('avatarForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const fileInput      = document.getElementById('avatar-upload');
            if (!fileInput.files[0]) { alert('请先选择头像文件'); return; }

            const formData       = new FormData(this);
            const progressBar    = document.getElementById('progressBar');
            const uploadProgress = document.getElementById('uploadProgress');
            const uploadMessage  = document.getElementById('uploadMessage');
            const submitButton   = document.getElementById('uploadButton');
            const currentAvatar  = document.getElementById('currentAvatar');
            const previewContainer = document.getElementById('previewContainer');

            uploadProgress.style.display = 'block';
            progressBar.style.width      = '0%';
            uploadMessage.textContent    = '准备上传...';
            uploadMessage.style.color    = '#666';
            submitButton.disabled        = true;

            const xhr = new XMLHttpRequest();
            // POST 到当前目录的 index.php（即本页面）
            xhr.open('POST', 'index.php', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.timeout = 30000;

            xhr.upload.addEventListener('progress', function (e) {
                if (e.lengthComputable) {
                    const pct = (e.loaded / e.total) * 100;
                    progressBar.style.width  = pct + '%';
                    uploadMessage.textContent = `上传中: ${Math.round(pct)}%`;
                }
            });

            xhr.addEventListener('load', function () {
                submitButton.disabled = false;
                if (xhr.status !== 200) {
                    uploadMessage.style.color   = 'red';
                    uploadMessage.textContent   = '上传失败，服务器错误: ' + xhr.status;
                    return;
                }
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        uploadMessage.style.color = 'green';
                        uploadMessage.textContent = res.message || '头像上传成功！';
                        if (res.avatarUrl) {
                            currentAvatar.src = res.avatarUrl + '?t=' + Date.now();
                        }
                        previewContainer.style.display = 'none';
                        currentAvatar.style.display    = 'block';
                        fileInput.value = '';
                        setTimeout(() => {
                            uploadProgress.style.display = 'none';
                            uploadMessage.textContent    = '';
                        }, 3000);
                    } else {
                        uploadMessage.style.color   = 'red';
                        uploadMessage.textContent   = res.message || '上传失败';
                    }
                } catch (err) {
                    uploadMessage.style.color   = 'red';
                    uploadMessage.textContent   = '服务器响应格式错误，请刷新页面重试';
                    console.error('JSON解析错误:', err, xhr.responseText);
                }
            });

            xhr.addEventListener('error',   () => { submitButton.disabled = false; uploadMessage.style.color = 'red'; uploadMessage.textContent = '上传失败，请检查网络连接'; });
            xhr.addEventListener('timeout', () => { submitButton.disabled = false; uploadMessage.style.color = 'red'; uploadMessage.textContent = '上传超时，请重试'; });

            xhr.send(formData);
        });

        // ── 背景粒子 ────────────────────────────────────────────────
        function createSparkles() {
            const container = document.getElementById('sparkles');
            if (!container) return;
            for (let i = 0; i < 36; i++) {
                const s    = document.createElement('i');
                const size = Math.random() * 8 + 4 + 'px';
                s.style.left              = Math.random() * 100 + 'vw';
                s.style.top               = Math.random() * 100 + 'vh';
                s.style.width             = size;
                s.style.height            = size;
                s.style.animationDelay    = Math.random() * 6 + 's';
                s.style.animationDuration = Math.random() * 4 + 4 + 's';
                container.appendChild(s);
            }
        }
    </script>
</body>
</html>