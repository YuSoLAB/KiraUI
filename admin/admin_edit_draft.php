<?php
// 编辑草稿页面
?>
<div class="admin-section" id="edit-draft">

    <!-- ── 页头 ── -->
    <div class="mhdr">
        <div>
            <h2 class="mhdr-title">
                <?php echo $isNewDraft ? '📝 新建草稿' : '📝 编辑草稿'; ?>
                <?php if (!$isNewDraft): ?>
                    <small style="font-size:.75rem;font-weight:400;color:var(--sub,#888);margin-left:.4rem;">
                        ID: <?php echo $currentDraft['id'] ?? ''; ?>
                    </small>
                <?php endif; ?>
            </h2>
            <p class="mhdr-sub"><?php echo $isNewDraft ? '填写内容后点击「保存草稿」，随时可继续编辑。' : '修改草稿内容，保存后可一键发布为正式文章。'; ?></p>
        </div>
        <a href="?page=drafts" class="btn btn-secondary" style="white-space:nowrap;">← 返回草稿箱</a>
    </div>

    <?php if (!$isNewDraft && empty($currentDraft)): ?>
        <div class="ea-notice ea-notice-error">⚠️ 草稿不存在，请检查链接或返回列表。</div>
    <?php else: ?>

    <form id="draftForm">
        <input type="hidden" name="type"         value="draft">
        <input type="hidden" name="draft_action" value="save">
        <?php if (!$isNewDraft): ?>
            <input type="hidden" name="id" value="<?php echo $currentDraft['id']; ?>">
        <?php endif; ?>
        <input type="hidden" name="cover_image" id="dr_cover_image" value="<?php echo htmlspecialchars($currentDraft['cover_image'] ?? ''); ?>">

        <!-- ── 两栏布局：主内容 + 侧边栏 ── -->
        <div class="ea-layout">

            <!-- ══ 主内容列 ══ -->
            <div class="ea-main">

                <!-- 标题 -->
                <div class="ea-card">
                    <div class="mfg">
                        <label for="dr_title">草稿标题 <span class="req">*</span></label>
                        <input type="text" id="dr_title" name="title"
                               value="<?php echo htmlspecialchars($currentDraft['title'] ?? ''); ?>"
                               placeholder="输入草稿标题…" required>
                    </div>
                </div>

                <!-- 摘要 -->
                <div class="ea-card">
                    <div class="mfg">
                        <label for="dr_excerpt">摘要 <span class="ea-opt">（可选）</span></label>
                        <textarea id="dr_excerpt" name="excerpt" rows="3"
                                  placeholder="简短描述文章内容…"><?php echo htmlspecialchars($currentDraft['excerpt'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- 正文编辑器 -->
                <div class="ea-card" id="dr-editor-card">
                    <div class="mfg" style="gap:.5rem">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;">
                            <label style="margin:0">草稿内容 <span class="req">*</span></label>
                            <!-- 视觉 / 代码 切换 -->
                            <div class="ea-mode-switch" id="drModeSwitch">
                                <button type="button" class="ea-mode-btn ea-mode-active" id="drBtnVisual" onclick="drSwitchMode('visual')">
                                    🖊 可视化
                                </button>
                                <button type="button" class="ea-mode-btn" id="drBtnCode" onclick="drSwitchMode('code')">
                                    &lt;/&gt; HTML
                                </button>
                            </div>
                        </div>

                        <!-- 工具栏 -->
                        <div class="ea-toolbar" id="drToolbar">
                            <!-- 撤销/重做 -->
                            <div class="ea-tb-group">
                                <button type="button" class="ea-tb-btn" title="撤销 (Ctrl+Z)" onclick="document.execCommand('undo')">↩</button>
                                <button type="button" class="ea-tb-btn" title="重做 (Ctrl+Y)" onclick="document.execCommand('redo')">↪</button>
                            </div>
                            <div class="ea-tb-sep"></div>
                            <!-- 段落/标题 -->
                            <div class="ea-tb-group">
                                <select class="ea-tb-select" onchange="drBlock(this.value);this.value=''" title="段落格式">
                                    <option value="">段落格式</option>
                                    <option value="p">正文</option>
                                    <option value="h1">标题 H1</option>
                                    <option value="h2">标题 H2</option>
                                    <option value="h3">标题 H3</option>
                                    <option value="h4">标题 H4</option>
                                    <option value="blockquote">引用块</option>
                                    <option value="pre">预格式化</option>
                                </select>
                            </div>
                            <div class="ea-tb-sep"></div>
                            <!-- 字体大小 -->
                            <div class="ea-tb-group">
                                <select class="ea-tb-select" onchange="drFontSize(this.value);this.value=''" title="字体大小">
                                    <option value="">字号</option>
                                    <option value="12px">12px</option>
                                    <option value="14px">14px</option>
                                    <option value="16px">16px</option>
                                    <option value="18px">18px</option>
                                    <option value="20px">20px</option>
                                    <option value="24px">24px</option>
                                    <option value="28px">28px</option>
                                    <option value="32px">32px</option>
                                    <option value="36px">36px</option>
                                </select>
                            </div>
                            <div class="ea-tb-sep"></div>
                            <!-- 加粗 斜体 下划线 删除线 -->
                            <div class="ea-tb-group">
                                <button type="button" class="ea-tb-btn" title="加粗 (Ctrl+B)" onclick="drCmd('bold')"><b>B</b></button>
                                <button type="button" class="ea-tb-btn" title="斜体 (Ctrl+I)" onclick="drCmd('italic')"><i>I</i></button>
                                <button type="button" class="ea-tb-btn" title="下划线 (Ctrl+U)" onclick="drCmd('underline')"><u>U</u></button>
                                <button type="button" class="ea-tb-btn" title="删除线" onclick="drCmd('strikeThrough')"><s>S</s></button>
                            </div>
                            <div class="ea-tb-sep"></div>
                            <!-- 上标 下标 -->
                            <div class="ea-tb-group">
                                <button type="button" class="ea-tb-btn" title="上标" onclick="drCmd('superscript')">x²</button>
                                <button type="button" class="ea-tb-btn" title="下标" onclick="drCmd('subscript')">x₂</button>
                            </div>
                            <div class="ea-tb-sep"></div>
                            <!-- 颜色 -->
                            <div class="ea-tb-group ea-color-group">
                                <label class="ea-tb-btn ea-color-btn" title="文字颜色">
                                    <span id="drFgIcon">A</span>
                                    <input type="color" id="drFgColor" value="#333333" oninput="drColor('foreColor',this.value)" onchange="drColor('foreColor',this.value)">
                                    <div class="ea-color-bar" id="drFgBar" style="background:#333333"></div>
                                </label>
                                <label class="ea-tb-btn ea-color-btn" title="文字背景色">
                                    <span>▣</span>
                                    <input type="color" id="drBgColor" value="#ffff00" oninput="drColor('hiliteColor',this.value)" onchange="drColor('hiliteColor',this.value)">
                                    <div class="ea-color-bar" id="drBgBar" style="background:#ffff00"></div>
                                </label>
                                <!-- 快速颜色 -->
                                <div class="ea-quick-colors">
                                    <span class="ea-qc" style="background:#e74c3c" onmousedown="event.preventDefault()" onclick="drColor('foreColor','#e74c3c')" title="红色"></span>
                                    <span class="ea-qc" style="background:#e67e22" onmousedown="event.preventDefault()" onclick="drColor('foreColor','#e67e22')" title="橙色"></span>
                                    <span class="ea-qc" style="background:#f1c40f" onmousedown="event.preventDefault()" onclick="drColor('foreColor','#f1c40f')" title="黄色"></span>
                                    <span class="ea-qc" style="background:#27ae60" onmousedown="event.preventDefault()" onclick="drColor('foreColor','#27ae60')" title="绿色"></span>
                                    <span class="ea-qc" style="background:#6c5dfb" onmousedown="event.preventDefault()" onclick="drColor('foreColor','#6c5dfb')" title="紫色"></span>
                                    <span class="ea-qc" style="background:#3498db" onmousedown="event.preventDefault()" onclick="drColor('foreColor','#3498db')" title="蓝色"></span>
                                    <span class="ea-qc" style="background:#888" onmousedown="event.preventDefault()" onclick="drColor('foreColor','#888888')" title="灰色"></span>
                                    <span class="ea-qc" style="background:#222" onmousedown="event.preventDefault()" onclick="drColor('foreColor','#222222')" title="黑色"></span>
                                    <span class="ea-qc" style="background:transparent;border:1px solid #ccc" onmousedown="event.preventDefault()" onclick="drRemoveColor()" title="清除颜色">✕</span>
                                </div>
                            </div>
                            <div class="ea-tb-sep"></div>
                            <!-- 对齐 -->
                            <div class="ea-tb-group">
                                <button type="button" class="ea-tb-btn" title="左对齐" onclick="drCmd('justifyLeft')">≡←</button>
                                <button type="button" class="ea-tb-btn" title="居中" onclick="drCmd('justifyCenter')">≡</button>
                                <button type="button" class="ea-tb-btn" title="右对齐" onclick="drCmd('justifyRight')">≡→</button>
                            </div>
                            <div class="ea-tb-sep"></div>
                            <!-- 列表 缩进 -->
                            <div class="ea-tb-group">
                                <button type="button" class="ea-tb-btn" title="无序列表" onclick="drCmd('insertUnorderedList')">• ≡</button>
                                <button type="button" class="ea-tb-btn" title="有序列表" onclick="drCmd('insertOrderedList')">1. ≡</button>
                                <button type="button" class="ea-tb-btn" title="减少缩进" onclick="drCmd('outdent')">⇤</button>
                                <button type="button" class="ea-tb-btn" title="增加缩进" onclick="drCmd('indent')">⇥</button>
                            </div>
                            <div class="ea-tb-sep"></div>
                            <!-- 链接 分割线 清除格式 -->
                            <div class="ea-tb-group">
                                <button type="button" class="ea-tb-btn" title="插入链接" onclick="drInsertLink()">🔗</button>
                                <button type="button" class="ea-tb-btn" title="插入分隔线" onclick="drCmd('insertHorizontalRule')">—</button>
                                <button type="button" class="ea-tb-btn" title="清除格式" onclick="drClearFormat()">✕ 格式</button>
                            </div>
                            <div class="ea-tb-sep"></div>
                            <!-- 短代码 -->
                            <div class="ea-tb-group">
                                <button type="button" class="ea-tb-btn ea-sc-btn" title="插入图片短代码" onclick="drShortcode('image')">🖼 图片</button>
                                <button type="button" class="ea-tb-btn ea-sc-btn" title="插入视频短代码" onclick="drShortcode('video')">▶ 视频</button>
                                <button type="button" class="ea-tb-btn ea-sc-btn" title="代码框" onclick="drShortcode('code')">{ } 代码</button>
                                <button type="button" class="ea-tb-btn ea-sc-btn" title="链接按钮" onclick="drShortcode('link')">🔘 链接</button>
                                <button type="button" class="ea-tb-btn ea-sc-btn" title="下载按钮" onclick="drShortcode('download')">⬇ 下载</button>
                                <button type="button" class="ea-tb-btn ea-sc-btn" title="加密下载" onclick="drShortcode('encrypted_download')">🔒 加密</button>
                            </div>
                            <div class="ea-tb-sep"></div>
                            <div class="ea-tb-group">
                                <button type="button" class="ea-tb-btn ea-tb-filepick" title="从 uploads 文件夹选择文件插入内容" onclick="drOpenFilePicker()">📂 插入文件</button>
                            </div>
                        </div>

                        <!-- 可视化编辑区 -->
                        <div id="drVisual"
                             class="ea-visual-editor"
                             contenteditable="true"
                             spellcheck="false"><?php echo $currentDraft['content'] ?? ''; ?></div>

                        <!-- HTML 代码编辑区（隐藏） -->
                        <textarea id="drCode" class="ea-code-editor" name="content" style="display:none;"><?php echo htmlspecialchars($currentDraft['content'] ?? ''); ?></textarea>

                        <!-- 字数统计 -->
                        <div class="ea-wordcount">
                            <span id="dr-wc">字数：0</span>
                            <span style="opacity:.4">|</span>
                            <span id="dr-rt">阅读时长：0 分钟</span>
                        </div>
                    </div>
                </div>

            </div><!-- /ea-main -->

            <!-- ══ 侧边栏 ══ -->
            <div class="ea-sidebar">

                <!-- 操作 -->
                <div class="ea-card ea-publish-card">
                    <h3 class="ea-card-title">💾 草稿操作</h3>
                    <div style="display:flex;flex-direction:column;gap:.6rem;margin-top:.75rem">

                        <!-- 保存草稿 -->
                        <button type="submit" class="btn btn-primary" style="width:100%">
                            💾 保存草稿
                        </button>

                        <?php if (!$isNewDraft): ?>
                        <!-- 预览 -->
                        <a href="../draft_preview.php?id=<?php echo $currentDraft['id']; ?>"
                           class="btn btn-secondary" style="width:100%;text-align:center" target="_blank">
                            👁 预览草稿
                        </a>
                        <!-- 发布为文章 -->
                        <button type="button" id="drPublishBtn"
                                class="btn btn-success" style="width:100%"
                                onclick="drPublishDraft()">
                            🚀 发布为文章
                        </button>
                        <?php endif; ?>

                        <a href="?page=drafts" class="btn btn-warning" style="width:100%;text-align:center">取消</a>
                    </div>
                </div>

                <!-- 封面图 -->
                <div class="ea-card">
                    <h3 class="ea-card-title">🖼 封面图</h3>
                    <div style="margin-top:.75rem;display:flex;flex-direction:column;gap:.6rem">
                        <div class="ea-cover-preview" id="drCoverPreview">
                            <?php $dci = $currentDraft['cover_image'] ?? ''; ?>
                            <?php if ($dci): ?>
                                <img id="drCoverImg" src="<?php echo htmlspecialchars($dci); ?>" alt="封面图预览">
                            <?php else: ?>
                                <div class="ea-cover-placeholder" id="drCoverPlaceholder"><span>🖼</span><em>暂未设置封面图</em></div>
                                <img id="drCoverImg" src="" alt="封面图预览" style="display:none">
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;gap:.45rem">
                            <button type="button" class="btn btn-secondary" style="flex:1;font-size:.82rem" onclick="drOpenCoverPicker()">🔍 选择图片</button>
                            <button type="button" class="btn btn-secondary" style="font-size:.82rem;padding:.4rem .7rem" title="清除封面图" onclick="drClearCover()">✕</button>
                        </div>
                        <div style="font-size:.75rem;color:var(--sub,#aaa)">从 uploads/images 快捷选取</div>
                    </div>
                </div>

                <!-- 草稿属性 -->
                <div class="ea-card">
                    <h3 class="ea-card-title">📋 属性</h3>
                    <div style="display:flex;flex-direction:column;gap:.75rem;margin-top:.75rem">
                        <div class="mfg">
                            <label for="dr_date">日期</label>
                            <input type="date" id="dr_date" name="date"
                                   value="<?php echo htmlspecialchars($currentDraft['date'] ?? date('Y-m-d')); ?>">
                        </div>
                        <div class="mfg">
                            <label for="dr_tags">标签 <span class="ea-opt">（逗号分隔）</span></label>
                            <input type="text" id="dr_tags" name="tags" placeholder="PHP, 教程, Web…"
                                   value="<?php echo htmlspecialchars(implode(', ', $currentDraft['tags'] ?? [])); ?>">
                        </div>
                    </div>
                </div>

                <!-- 提示 -->
                <div class="mtip" style="font-size:.8rem">
                    💡 草稿不会对外公开，随时可以继续编辑，满意后点「发布为文章」上线。
                </div>

            </div><!-- /ea-sidebar -->

        </div><!-- /ea-layout -->
    </form>

    <?php endif; ?>
</div><!-- /admin-section -->

<script>
(function() {
    let drMode = 'visual';
    const visual = document.getElementById('drVisual');
    const code   = document.getElementById('drCode');
    const form   = document.getElementById('draftForm');

    /* ── Toast 提示 ── */
    function _showSaveToast(msg, type) {
        let t = document.getElementById('_drSaveToast');
        if (!t) {
            t = document.createElement('div');
            t.id = '_drSaveToast';
            t.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:99999;'
                + 'padding:.65rem 1.2rem;border-radius:10px;font-size:.9rem;font-weight:600;'
                + 'box-shadow:0 4px 18px rgba(0,0,0,.18);transition:opacity .3s;pointer-events:none;';
            document.body.appendChild(t);
        }
        t.textContent = msg;
        t.style.background = (type === 'success') ? '#27ae60' : '#e74c3c';
        t.style.color = '#fff';
        t.style.opacity = '1';
        clearTimeout(t._timer);
        t._timer = setTimeout(() => { t.style.opacity = '0'; }, 3000);
    }
    window._drShowToast = _showSaveToast;

    /* ── 工具栏点击不抢走编辑器焦点（保留选区） ── */
    document.getElementById('drToolbar').addEventListener('mousedown', function(e) {
        if (e.target.tagName === 'SELECT') return;
        e.preventDefault();
    });

    /* ── 模式切换 ── */
    window.drSwitchMode = function(mode) {
        if (mode === drMode) return;
        if (mode === 'code') {
            code.value = visual.innerHTML;
            visual.style.display = 'none';
            code.style.display   = 'block';
            document.getElementById('drToolbar').style.opacity = '.4';
            document.getElementById('drToolbar').style.pointerEvents = 'none';
        } else {
            visual.innerHTML     = code.value;
            code.style.display   = 'none';
            visual.style.display = 'block';
            document.getElementById('drToolbar').style.opacity = '';
            document.getElementById('drToolbar').style.pointerEvents = '';
        }
        drMode = mode;
        document.getElementById('drBtnVisual').classList.toggle('ea-mode-active', mode === 'visual');
        document.getElementById('drBtnCode').classList.toggle('ea-mode-active', mode === 'code');
    };

    /* ── 构建并发送 FormData 到 admin_ajax.php ── */
    function _submitDraft(extraFields, successCb) {
        if (drMode === 'visual') code.value = visual.innerHTML;
        code.name = 'content';
        const fd = new FormData(form);
        if (extraFields) {
            Object.entries(extraFields).forEach(([k, v]) => fd.set(k, v));
        }
        return fetch('admin_ajax.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    _showSaveToast('✅ ' + (data.msg || '操作成功！'), 'success');
                    if (successCb) successCb(data);
                } else {
                    _showSaveToast('❌ ' + (data.msg || '操作失败，请重试'), 'error');
                }
                return data;
            })
            .catch(err => {
                _showSaveToast('❌ 网络错误：' + err.message, 'error');
            });
    }

    /* ── 表单提交：保存草稿 ── */
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtn = form.querySelector('button[type="submit"]');
            const origHtml  = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '⏳ 保存中…'; }

            _submitDraft(null, function(data) {
                // 新建草稿保存后跳转到编辑页
                if (data.id) {
                    setTimeout(() => {
                        window.location.href = '?page=edit_draft&id=' + data.id;
                    }, 800);
                }
            }).finally(() => {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = origHtml; }
            });
        });
    }

    /* ── 发布草稿为正式文章 ── */
    window.drPublishDraft = function() {
        if (!confirm('确定要将此草稿发布为正式文章吗？')) return;
        const btn = document.getElementById('drPublishBtn');
        if (btn) { btn.disabled = true; btn.innerHTML = '⏳ 发布中…'; }

        _submitDraft({ draft_action: 'publish' }, function(data) {
            if (data.id) {
                setTimeout(() => {
                    window.location.href = '?page=edit_article&id=' + data.id;
                }, 900);
            }
        }).finally(() => {
            if (btn) { btn.disabled = false; btn.innerHTML = '🚀 发布为文章'; }
        });
    };

    /* ── 自动保存（30 秒，仅编辑现有草稿时） ── */
    (function initAutoSave() {
        const idInput = form ? form.querySelector('input[name="id"]') : null;
        if (!idInput) return; // 新建草稿不自动保存
        let _asTimer = null;
        let _asLabel = null;

        function scheduleAutoSave() {
            clearTimeout(_asTimer);
            _asTimer = setTimeout(doAutoSave, 30000);
        }

        function doAutoSave() {
            if (drMode === 'visual') code.value = visual.innerHTML;
            code.name = 'content';
            const fd = new FormData(form);
            fetch('admin_ajax.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        _showSaveToast('💾 草稿已自动保存', 'success');
                        _updateAutoSaveTime();
                    }
                })
                .catch(() => {});
        }

        function _updateAutoSaveTime() {
            if (!_asLabel) {
                _asLabel = document.createElement('div');
                _asLabel.style.cssText = 'font-size:.72rem;color:var(--sub,#aaa);text-align:center;margin-top:.3rem;';
                const tip = document.querySelector('.mtip');
                if (tip) tip.parentNode.insertBefore(_asLabel, tip);
            }
            const now = new Date();
            _asLabel.textContent = '⏱ 最后自动保存：' + now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0') + ':' + now.getSeconds().toString().padStart(2,'0');
        }

        visual.addEventListener('input', scheduleAutoSave);
        code.addEventListener('input', scheduleAutoSave);
        form.querySelectorAll('input,textarea,select').forEach(el => {
            el.addEventListener('change', scheduleAutoSave);
        });
    })();

    /* ── execCommand 包装 ── */
    window.drCmd = function(cmd, val) {
        if (drMode !== 'visual') return;
        visual.focus();
        document.execCommand(cmd, false, val || null);
    };

    window.drBlock = function(tag) {
        if (!tag || drMode !== 'visual') return;
        visual.focus();
        document.execCommand('formatBlock', false, '<' + tag + '>');
    };

    window.drFontSize = function(size) {
        if (!size || drMode !== 'visual') return;
        visual.focus();
        const sel = window.getSelection();
        if (!sel || sel.isCollapsed) return;
        document.execCommand('fontSize', false, '7');
        visual.querySelectorAll('font[size="7"]').forEach(el => {
            el.removeAttribute('size');
            el.style.fontSize = size;
        });
    };

    window.drColor = function(cmd, hex) {
        if (drMode !== 'visual') return;
        visual.focus();
        document.execCommand(cmd, false, hex);
        if (cmd === 'foreColor') document.getElementById('drFgBar').style.background = hex;
        if (cmd === 'hiliteColor') document.getElementById('drBgBar').style.background = hex;
    };

    window.drRemoveColor = function() {
        if (drMode !== 'visual') return;
        visual.focus();
        document.execCommand('foreColor', false, 'inherit');
        document.execCommand('hiliteColor', false, 'transparent');
    };

    window.drClearFormat = function() {
        if (drMode !== 'visual') return;
        visual.focus();
        document.execCommand('removeFormat');
        document.execCommand('formatBlock', false, '<p>');
    };

    window.drInsertLink = function() {
        if (drMode !== 'visual') return;
        const url = prompt('请输入链接地址：', 'https://');
        if (!url) return;
        visual.focus();
        document.execCommand('createLink', false, url);
        visual.querySelectorAll('a[href="' + url + '"]:not([target])').forEach(a => {
            a.target = '_blank'; a.rel = 'noopener';
        });
    };

    window.drShortcode = function(type) {
        const templates = {
            image:              () => { const s = prompt('图片URL：',''); if (!s) return null; const a = prompt('图片描述(alt)：',''); return `[image url="${s}" alt="${a||''}"]`; },
            video:              () => { const s = prompt('视频URL：',''); return s ? `[video url="${s}"]` : null; },
            code:               () => { const l = prompt('语言（如 php/js/python）：','html'); return `[code lang="${l||'html'}"]代码内容[/code]`; },
            link:               () => { const u=prompt('链接URL：',''); const t=prompt('按钮文字：','点击查看'); return (u&&t)?`[link text="${t}" url="${u}"]`:null; },
            download:           () => { const u=prompt('文件URL：',''); const t=prompt('按钮文字：','下载文件'); return (u&&t)?`[download text="${t}" url="${u}"]`:null; },
            encrypted_download: () => { const u=prompt('加密文件URL：',''); const t=prompt('按钮文字：','加密下载'); return (u&&t)?`[encrypted_download text="${t}" url="${u}"]`:null; },
        };
        const gen = templates[type];
        if (!gen) return;
        const sc = gen();
        if (sc === null) return;
        if (drMode === 'visual') {
            visual.focus();
            document.execCommand('insertText', false, sc);
        } else {
            const ta = code;
            const s = ta.selectionStart, e = ta.selectionEnd;
            ta.value = ta.value.slice(0, s) + sc + ta.value.slice(e);
            ta.selectionStart = ta.selectionEnd = s + sc.length;
            ta.focus();
        }
    };

    /* ── 字数统计 ── */
    function updateWordCount() {
        const text = drMode === 'visual'
            ? (visual.innerText || visual.textContent || '')
            : code.value.replace(/<[^>]+>/g, '');
        const count = text.replace(/\s+/g,' ').trim().length;
        const mins  = Math.max(1, Math.round(count / 400));
        document.getElementById('dr-wc').textContent = '字数：' + count;
        document.getElementById('dr-rt').textContent = '阅读时长：' + mins + ' 分钟';
    }
    visual.addEventListener('input', updateWordCount);
    code.addEventListener('input', updateWordCount);
    updateWordCount();

    /* ── Tab 缩进（HTML 代码模式）── */
    code.addEventListener('keydown', function(e) {
        if (e.key === 'Tab') {
            e.preventDefault();
            const s = this.selectionStart;
            this.value = this.value.slice(0, s) + '    ' + this.value.slice(this.selectionEnd);
            this.selectionStart = this.selectionEnd = s + 4;
        }
    });

    /* ── Shift+Enter 插入 <br>（可视化模式）── */
    visual.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.shiftKey) {
            e.preventDefault();
            document.execCommand('insertHTML', false, '<br>');
        }
    });

})();
</script>

<!-- ══ 封面图 & 文件选择器 Modal ══════════════════════════════════════════ -->
<style>
.ea-cover-preview { width:100%; aspect-ratio:16/9; border-radius:8px; overflow:hidden; border:1px solid var(--admin-border,rgba(155,140,255,.3)); background:rgba(155,140,255,.05); display:flex; align-items:center; justify-content:center; position:relative; }
.ea-cover-preview img { width:100%; height:100%; object-fit:cover; display:block; }
.ea-cover-placeholder { display:flex; flex-direction:column; align-items:center; gap:.3rem; opacity:.35; user-select:none; }
.ea-cover-placeholder span { font-size:2rem; }
.ea-cover-placeholder em { font-size:.65rem; font-style:normal; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--primary,#6c5dfb); }
.ea-tb-filepick { color:#27ae60; }
.ea-picker-modal { display:none; position:fixed; inset:0; z-index:9900; background:rgba(0,0,0,.55); align-items:center; justify-content:center; backdrop-filter:blur(3px); }
.ea-picker-modal.open { display:flex; }
.ea-picker-box { background:var(--admin-card,#fff); border:1px solid var(--admin-border,rgba(155,140,255,.3)); border-radius:14px; width:min(860px,95vw); max-height:88vh; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:eaPickerIn .18s ease; }
@keyframes eaPickerIn { from { opacity:0; transform:scale(.96) translateY(8px); } }
.ea-picker-hd { display:flex; align-items:center; justify-content:space-between; padding:.9rem 1.2rem; border-bottom:1px solid var(--admin-border,rgba(155,140,255,.2)); flex-shrink:0; }
.ea-picker-hd h3 { margin:0; font-size:.95rem; color:#6c5dfb; }
.ea-picker-hd button { background:none; border:none; cursor:pointer; font-size:1.1rem; color:var(--sub,#888); padding:.2rem .4rem; border-radius:6px; }
.ea-picker-toolbar { display:flex; gap:.6rem; align-items:center; flex-wrap:wrap; padding:.65rem 1.2rem; border-bottom:1px solid var(--admin-border,rgba(155,140,255,.1)); flex-shrink:0; }
.ea-picker-tabs { display:flex; gap:.3rem; flex-wrap:wrap; }
.ea-picker-tab { padding:.25rem .72rem; border-radius:20px; font-size:.8rem; cursor:pointer; border:1px solid var(--admin-border,rgba(155,140,255,.3)); background:transparent; color:var(--sub,#666); transition:all .13s; }
.ea-picker-tab:hover { border-color:#6c5dfb; color:#6c5dfb; }
.ea-picker-tab.active { background:#6c5dfb; color:#fff; border-color:#6c5dfb; }
.ea-picker-search { flex:1; min-width:140px; padding:.3rem .7rem; font-size:.85rem; border:1px solid var(--admin-border,rgba(155,140,255,.35)); border-radius:8px; background:var(--admin-card,#fff); color:inherit; }
.ea-picker-search:focus { outline:none; border-color:#6c5dfb; }
.ea-picker-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:.7rem; padding:1rem 1.2rem; overflow-y:auto; flex:1; min-height:200px; }
.ea-picker-loading,.ea-picker-empty { grid-column:1/-1; text-align:center; padding:2rem; color:var(--sub,#aaa); font-size:.88rem; }
.ea-picker-item { border:2px solid transparent; border-radius:10px; overflow:hidden; cursor:pointer; transition:border-color .13s, transform .12s; background:rgba(155,140,255,.05); display:flex; flex-direction:column; }
.ea-picker-item:hover { border-color:#6c5dfb; transform:translateY(-2px); }
.ea-picker-item.selected { border-color:#6c5dfb; box-shadow:0 0 0 3px rgba(108,93,251,.18); }
.ea-picker-thumb { aspect-ratio:4/3; display:flex; align-items:center; justify-content:center; overflow:hidden; background:rgba(155,140,255,.06); }
.ea-picker-thumb img { width:100%; height:100%; object-fit:cover; }
.ea-picker-thumb-icon { font-size:2.2rem; }
.ea-picker-name { padding:.3rem .4rem; font-size:.72rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; border-top:1px solid rgba(155,140,255,.12); }
.ea-picker-ft { display:flex; align-items:center; justify-content:flex-end; gap:.5rem; padding:.75rem 1.2rem; border-top:1px solid var(--admin-border,rgba(155,140,255,.15)); flex-shrink:0; }
.ea-picker-insert-opts { display:flex; gap:.4rem; flex-wrap:wrap; }
.ea-picker-insert-opts button { font-size:.8rem; }
body.dark-mode .ea-picker-box { background:var(--dark-admin-card,#2a2a42); border-color:var(--dark-admin-border); }
body.dark-mode .ea-picker-hd h3 { color:var(--dark-vio,#b096ff); }
body.dark-mode .ea-picker-tab { border-color:var(--dark-admin-border); color:var(--dark-sub,#b0b0c5); }
body.dark-mode .ea-picker-tab.active { background:var(--dark-vio,#b096ff); color:#1a1a2e; }
body.dark-mode .ea-picker-search { background:#2a2a42aa; border-color:var(--dark-admin-border); color:var(--dark-text,#eaeaea); }
body.dark-mode .ea-picker-item:hover,.ea-picker-item.selected { border-color:var(--dark-vio,#b096ff); }
</style>

<div id="drCoverModal" class="ea-picker-modal" onclick="if(event.target===this)drCloseCoverPicker()">
    <div class="ea-picker-box">
        <div class="ea-picker-hd"><h3>🖼 选择封面图</h3><button type="button" onclick="drCloseCoverPicker()">✕</button></div>
        <div class="ea-picker-toolbar">
            <span style="font-size:.82rem;color:var(--sub,#888)">uploads/images/</span>
            <input type="text" class="ea-picker-search" id="drCoverSearch" placeholder="搜索图片名…" oninput="drCoverFilter()">
        </div>
        <div class="ea-picker-grid" id="drCoverGrid"><div class="ea-picker-loading">⏳ 加载中…</div></div>
        <div class="ea-picker-ft">
            <button type="button" class="btn btn-secondary" onclick="drCloseCoverPicker()">取消</button>
            <button type="button" class="btn btn-primary" id="drCoverConfirmBtn" disabled onclick="drConfirmCover()">✓ 使用此图</button>
        </div>
    </div>
</div>

<div id="drFileModal" class="ea-picker-modal" onclick="if(event.target===this)drCloseFilePicker()">
    <div class="ea-picker-box">
        <div class="ea-picker-hd"><h3>📂 从媒体库插入文件</h3><button type="button" onclick="drCloseFilePicker()">✕</button></div>
        <div class="ea-picker-toolbar">
            <div class="ea-picker-tabs" id="drFileTabs">
                <button type="button" class="ea-picker-tab active" data-folder="all"    onclick="drFileTab('all',this)">全部</button>
                <button type="button" class="ea-picker-tab"        data-folder="images" onclick="drFileTab('images',this)">🖼 图片</button>
                <button type="button" class="ea-picker-tab"        data-folder="videos" onclick="drFileTab('videos',this)">🎬 视频</button>
                <button type="button" class="ea-picker-tab"        data-folder="audios" onclick="drFileTab('audios',this)">🎵 音频</button>
                <button type="button" class="ea-picker-tab"        data-folder="files"  onclick="drFileTab('files',this)">📄 其他</button>
            </div>
            <input type="text" class="ea-picker-search" id="drFileSearch" placeholder="搜索文件名…" oninput="drFileFilter()">
        </div>
        <div class="ea-picker-grid" id="drFileGrid"><div class="ea-picker-loading">⏳ 加载中…</div></div>
        <div class="ea-picker-ft">
            <div id="drFileInsertOpts" class="ea-picker-insert-opts" style="flex:1"></div>
            <button type="button" class="btn btn-secondary" onclick="drCloseFilePicker()">取消</button>
        </div>
    </div>
</div>

<script>
(function(){
const MEDIA_AJAX = 'admin_media_ajax.php';
const IMG_EXTS   = new Set(['jpg','jpeg','png','gif','webp','svg','bmp','ico','avif','tiff','tif']);
const VIDEO_EXTS = new Set(['mp4','webm','avi','mov','mkv','flv','wmv','m4v','3gp','ogv']);
const AUDIO_EXTS = new Set(['mp3','wav','ogg','flac','aac','m4a','wma','opus','aiff']);
const EXT_ICONS  = { pdf:'📄', zip:'🗜️', rar:'🗜️', doc:'📝', docx:'📝', xls:'📊', xlsx:'📊', ppt:'📑', pptx:'📑', txt:'📃', md:'📃', js:'💻', css:'🎨', py:'🐍' };
function he(s){return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function toPublicUrl(u){try{const p=new URL(u,location.href);const f=p.searchParams.get('folder'),n=p.searchParams.get('name');if(f&&n)return'serve_media.php?folder='+encodeURIComponent(f)+'&name='+encodeURIComponent(n);}catch(e){}return u;}
function ext(n){return(n.split('.').pop()||'').toLowerCase();}

/* Cover */
let _dCoverAll=[],_dCoverSel=null;
window.drOpenCoverPicker=async function(){_dCoverSel=null;document.getElementById('drCoverConfirmBtn').disabled=true;document.getElementById('drCoverSearch').value='';document.getElementById('drCoverModal').classList.add('open');const g=document.getElementById('drCoverGrid');g.innerHTML='<div class="ea-picker-loading">⏳ 加载中…</div>';try{const fd=new FormData();fd.append('act','list');fd.append('folder','images');const res=await fetch(MEDIA_AJAX,{method:'POST',body:fd});const d=await res.json();_dCoverAll=(d.files||[]).filter(f=>IMG_EXTS.has(ext(f.name)));drCoverFilter();}catch(e){g.innerHTML='<div class="ea-picker-empty">加载失败</div>';}};
window.drCloseCoverPicker=function(){document.getElementById('drCoverModal').classList.remove('open');};
window.drConfirmCover=function(){if(!_dCoverSel)return;document.getElementById('dr_cover_image').value=_dCoverSel.url;const img=document.getElementById('drCoverImg'),ph=document.getElementById('drCoverPlaceholder');img.src=_dCoverSel.url;img.style.display='block';if(ph)ph.style.display='none';drCloseCoverPicker();};
window.drClearCover=function(){document.getElementById('dr_cover_image').value='';const img=document.getElementById('drCoverImg'),ph=document.getElementById('drCoverPlaceholder');img.src='';img.style.display='none';if(ph)ph.style.display='flex';};
window.drCoverFilter=function(){const q=document.getElementById('drCoverSearch').value.toLowerCase().trim();const fl=q?_dCoverAll.filter(f=>f.name.toLowerCase().includes(q)):_dCoverAll;const g=document.getElementById('drCoverGrid');if(!fl.length){g.innerHTML='<div class="ea-picker-empty">📭 暂无图片</div>';return;}g.innerHTML=fl.map(f=>{const pub=toPublicUrl(f.url);return`<div class="ea-picker-item" onclick="_drSelectCover(this,'${he(pub)}','${he(f.name)}')"><div class="ea-picker-thumb"><img src="${he(f.url)}" alt="${he(f.name)}" loading="lazy" onerror="this.style.display='none'"></div><div class="ea-picker-name">${he(f.name)}</div></div>`;}).join('');};
window._drSelectCover=function(el,url,name){document.querySelectorAll('#drCoverGrid .ea-picker-item').forEach(i=>i.classList.remove('selected'));el.classList.add('selected');_dCoverSel={url,name};document.getElementById('drCoverConfirmBtn').disabled=false;};

/* File picker */
let _dFileAll=[],_dFileFolder='all',_dFileSel=null;
window.drOpenFilePicker=async function(){_dFileSel=null;_dFileFolder='all';document.getElementById('drFileSearch').value='';document.querySelectorAll('#drFileTabs .ea-picker-tab').forEach(b=>b.classList.toggle('active',b.dataset.folder==='all'));document.getElementById('drFileInsertOpts').innerHTML='';document.getElementById('drFileModal').classList.add('open');const g=document.getElementById('drFileGrid');g.innerHTML='<div class="ea-picker-loading">⏳ 加载中…</div>';try{const fd=new FormData();fd.append('act','list');fd.append('folder','all');const res=await fetch(MEDIA_AJAX,{method:'POST',body:fd});const d=await res.json();_dFileAll=d.files||[];drFileFilter();}catch(e){g.innerHTML='<div class="ea-picker-empty">加载失败</div>';}};
window.drCloseFilePicker=function(){document.getElementById('drFileModal').classList.remove('open');};
window.drFileTab=function(folder,btn){_dFileFolder=folder;document.querySelectorAll('#drFileTabs .ea-picker-tab').forEach(b=>b.classList.remove('active'));btn.classList.add('active');drFileFilter();};
window.drFileFilter=function(){const q=document.getElementById('drFileSearch').value.toLowerCase().trim();const fl=_dFileAll.filter(f=>(_dFileFolder==='all'||f.folder===_dFileFolder)&&(!q||f.name.toLowerCase().includes(q)));const g=document.getElementById('drFileGrid');if(!fl.length){g.innerHTML='<div class="ea-picker-empty">📭 暂无文件</div>';return;}g.innerHTML=fl.map(f=>{const pub=toPublicUrl(f.url),fExt=ext(f.name),isImg=IMG_EXTS.has(fExt),icon=isImg?'🖼️':VIDEO_EXTS.has(fExt)?'🎬':AUDIO_EXTS.has(fExt)?'🎵':(EXT_ICONS[fExt]||'📁');const th=isImg?`<img src="${he(f.url)}" loading="lazy" alt="${he(f.name)}" onerror="this.style.display='none'">`:`<span class="ea-picker-thumb-icon">${icon}</span>`;const fd2=he(JSON.stringify({name:f.name,folder:f.folder,url:pub,ext:fExt}));return`<div class="ea-picker-item" onclick="_drSelectFile(this,${fd2})"><div class="ea-picker-thumb">${th}</div><div class="ea-picker-name">${he(f.name)}</div></div>`;}).join('');};
window._drSelectFile=function(el,f){document.querySelectorAll('#drFileGrid .ea-picker-item').forEach(i=>i.classList.remove('selected'));el.classList.add('selected');_dFileSel=f;const isImg=IMG_EXTS.has(f.ext),isVid=VIDEO_EXTS.has(f.ext);let b='';if(isImg){b=`<button type="button" class="btn btn-primary" onclick="drInsertFile('img')">🖼 插入图片</button><button type="button" class="btn btn-secondary" onclick="drInsertFile('link')">🔗 链接</button>`;}else if(isVid){b=`<button type="button" class="btn btn-primary" onclick="drInsertFile('video')">▶ 插入视频</button><button type="button" class="btn btn-secondary" onclick="drInsertFile('link')">🔗 链接</button>`;}else{b=`<button type="button" class="btn btn-primary" onclick="drInsertFile('download')">⬇ 下载</button><button type="button" class="btn btn-secondary" onclick="drInsertFile('link')">🔗 链接</button>`;}document.getElementById('drFileInsertOpts').innerHTML=b;};
window.drInsertFile=function(mode){if(!_dFileSel)return;const f=_dFileSel;let sc=mode==='img'?`[image url="${f.url}" alt="${f.name}"]`:mode==='video'?`[video url="${f.url}"]`:mode==='download'?`[download text="${f.name}" url="${f.url}"]`:`<a href="${f.url}" target="_blank" rel="noopener">${f.name}</a>`;const visual=document.getElementById('drVisual'),code=document.getElementById('drCode');const isVisual=(typeof drMode!=='undefined'?drMode:window._drMode)==='visual';if(isVisual&&mode!=='link'){visual.focus();document.execCommand('insertText',false,sc);}else if(isVisual){visual.focus();document.execCommand('insertHTML',false,sc);}else{const s=code.selectionStart,e=code.selectionEnd;code.value=code.value.slice(0,s)+sc+code.value.slice(e);code.selectionStart=code.selectionEnd=s+sc.length;code.focus();}drCloseFilePicker();};

document.addEventListener('keydown',e=>{if(e.key==='Escape'){drCloseCoverPicker();drCloseFilePicker();}});
})();
</script>