<?php
// 编辑文章页面
?>
<div class="admin-section" id="edit-article">

    <!-- ── 页头 ── -->
    <div class="mhdr">
        <div>
            <h2 class="mhdr-title">
                <?php echo $isNewArticle ? '✏️ 发布新文章' : '✏️ 编辑文章'; ?>
                <?php if (!$isNewArticle): ?>
                    <small style="font-size:.75rem;font-weight:400;color:var(--sub,#888);margin-left:.4rem;">
                        ID: <?php echo $currentArticle['id'] ?? '未知'; ?>
                    </small>
                <?php endif; ?>
            </h2>
            <p class="mhdr-sub"><?php echo $isNewArticle ? '填写内容后点击「发布文章」即可上线。' : '修改内容后点击「保存更改」以更新文章。'; ?></p>
        </div>
        <a href="?page=articles" class="btn btn-secondary" style="white-space:nowrap;">← 返回文章列表</a>
    </div>

    <?php if (!$isNewArticle && empty($currentArticle)): ?>
        <div class="ea-notice ea-notice-error">⚠️ 文章不存在，请检查链接或返回列表。</div>
    <?php else: ?>

    <form id="articleForm">
        <input type="hidden" name="type" value="article">
        <input type="hidden" name="article_action" value="save">
        <?php if (!$isNewArticle): ?>
            <input type="hidden" name="id" value="<?php echo $currentArticle['id']; ?>">
        <?php endif; ?>
        <input type="hidden" name="cover_image" id="ea_cover_image" value="<?php echo htmlspecialchars($currentArticle['cover_image'] ?? ''); ?>">

        <!-- ── 两栏布局：主内容 + 侧边栏 ── -->
        <div class="ea-layout">

            <!-- ══ 主内容列 ══ -->
            <div class="ea-main">

                <!-- 标题 -->
                <div class="ea-card">
                    <div class="mfg">
                        <label for="ea_title">文章标题 <span class="req">*</span></label>
                        <input type="text" id="ea_title" name="title"
                               value="<?php echo htmlspecialchars($currentArticle['title'] ?? ''); ?>"
                               placeholder="输入文章标题…" required>
                    </div>
                </div>

                <!-- 摘要 -->
                <div class="ea-card">
                    <div class="mfg">
                        <label for="ea_excerpt">摘要 <span class="ea-opt">（可选，留空则自动截取）</span></label>
                        <textarea id="ea_excerpt" name="excerpt" rows="3"
                                  placeholder="简短描述文章内容，用于列表页预览…"><?php echo htmlspecialchars($currentArticle['excerpt'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- 正文编辑器 -->
                <div class="ea-card" id="ea-editor-card">
                    <div class="mfg" style="gap:.5rem">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;">
                            <label style="margin:0">文章内容 <span class="req">*</span></label>
                            <!-- 视觉 / 代码 切换 -->
                            <div class="ea-mode-switch" id="eaModeSwitch">
                                <button type="button" class="ea-mode-btn ea-mode-active" id="btnVisual" onclick="eaSwitchMode('visual')">
                                    🖊 可视化
                                </button>
                                <button type="button" class="ea-mode-btn" id="btnCode" onclick="eaSwitchMode('code')">
                                    &lt;/&gt; HTML
                                </button>
                            </div>
                        </div>

                        <!-- 工具栏 -->
                        <div class="ea-toolbar" id="eaToolbar">
                            <!-- 撤销/重做 -->
                            <div class="ea-tb-group">
                                <button type="button" class="ea-tb-btn" title="撤销 (Ctrl+Z)" onclick="document.execCommand('undo')">↩</button>
                                <button type="button" class="ea-tb-btn" title="重做 (Ctrl+Y)" onclick="document.execCommand('redo')">↪</button>
                            </div>
                            <div class="ea-tb-sep"></div>
                            <!-- 段落/标题 -->
                            <div class="ea-tb-group">
                                <select class="ea-tb-select" onchange="eaBlock(this.value);this.value=''" title="段落格式">
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
                                <select class="ea-tb-select" onchange="eaFontSize(this.value);this.value=''" title="字体大小">
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
                                <button type="button" class="ea-tb-btn" title="加粗 (Ctrl+B)" onclick="eaCmd('bold')"><b>B</b></button>
                                <button type="button" class="ea-tb-btn" title="斜体 (Ctrl+I)" onclick="eaCmd('italic')"><i>I</i></button>
                                <button type="button" class="ea-tb-btn" title="下划线 (Ctrl+U)" onclick="eaCmd('underline')"><u>U</u></button>
                                <button type="button" class="ea-tb-btn" title="删除线" onclick="eaCmd('strikeThrough')"><s>S</s></button>
                            </div>
                            <div class="ea-tb-sep"></div>
                            <!-- 上标 下标 -->
                            <div class="ea-tb-group">
                                <button type="button" class="ea-tb-btn" title="上标" onclick="eaCmd('superscript')">x²</button>
                                <button type="button" class="ea-tb-btn" title="下标" onclick="eaCmd('subscript')">x₂</button>
                            </div>
                            <div class="ea-tb-sep"></div>
                            <!-- 颜色 -->
                            <div class="ea-tb-group ea-color-group">
                                <label class="ea-tb-btn ea-color-btn" title="文字颜色">
                                    <span id="eaFgIcon">A</span>
                                    <input type="color" id="eaFgColor" value="#333333" oninput="eaColor('foreColor',this.value)" onchange="eaColor('foreColor',this.value)">
                                    <div class="ea-color-bar" id="eaFgBar" style="background:#333333"></div>
                                </label>
                                <label class="ea-tb-btn ea-color-btn" title="文字背景色">
                                    <span>▣</span>
                                    <input type="color" id="eaBgColor" value="#ffff00" oninput="eaColor('hiliteColor',this.value)" onchange="eaColor('hiliteColor',this.value)">
                                    <div class="ea-color-bar" id="eaBgBar" style="background:#ffff00"></div>
                                </label>
                                <!-- 快速颜色 -->
                                <div class="ea-quick-colors">
                                    <span class="ea-qc" style="background:#e74c3c" onmousedown="event.preventDefault()" onclick="eaColor('foreColor','#e74c3c')" title="红色"></span>
                                    <span class="ea-qc" style="background:#e67e22" onmousedown="event.preventDefault()" onclick="eaColor('foreColor','#e67e22')" title="橙色"></span>
                                    <span class="ea-qc" style="background:#f1c40f" onmousedown="event.preventDefault()" onclick="eaColor('foreColor','#f1c40f')" title="黄色"></span>
                                    <span class="ea-qc" style="background:#27ae60" onmousedown="event.preventDefault()" onclick="eaColor('foreColor','#27ae60')" title="绿色"></span>
                                    <span class="ea-qc" style="background:#6c5dfb" onmousedown="event.preventDefault()" onclick="eaColor('foreColor','#6c5dfb')" title="紫色"></span>
                                    <span class="ea-qc" style="background:#3498db" onmousedown="event.preventDefault()" onclick="eaColor('foreColor','#3498db')" title="蓝色"></span>
                                    <span class="ea-qc" style="background:#888" onmousedown="event.preventDefault()" onclick="eaColor('foreColor','#888888')" title="灰色"></span>
                                    <span class="ea-qc" style="background:#222" onmousedown="event.preventDefault()" onclick="eaColor('foreColor','#222222')" title="黑色"></span>
                                    <span class="ea-qc" style="background:transparent;border:1px solid #ccc" onmousedown="event.preventDefault()" onclick="eaRemoveColor()" title="清除颜色">✕</span>
                                </div>
                            </div>
                            <div class="ea-tb-sep"></div>
                            <!-- 对齐 -->
                            <div class="ea-tb-group">
                                <button type="button" class="ea-tb-btn" title="左对齐" onclick="eaCmd('justifyLeft')">≡←</button>
                                <button type="button" class="ea-tb-btn" title="居中" onclick="eaCmd('justifyCenter')">≡</button>
                                <button type="button" class="ea-tb-btn" title="右对齐" onclick="eaCmd('justifyRight')">≡→</button>
                            </div>
                            <div class="ea-tb-sep"></div>
                            <!-- 列表 缩进 -->
                            <div class="ea-tb-group">
                                <button type="button" class="ea-tb-btn" title="无序列表" onclick="eaCmd('insertUnorderedList')">• ≡</button>
                                <button type="button" class="ea-tb-btn" title="有序列表" onclick="eaCmd('insertOrderedList')">1. ≡</button>
                                <button type="button" class="ea-tb-btn" title="减少缩进" onclick="eaCmd('outdent')">⇤</button>
                                <button type="button" class="ea-tb-btn" title="增加缩进" onclick="eaCmd('indent')">⇥</button>
                            </div>
                            <div class="ea-tb-sep"></div>
                            <!-- 链接 图片 水平线 清除格式 -->
                            <div class="ea-tb-group">
                                <button type="button" class="ea-tb-btn" title="插入链接" onclick="eaInsertLink()">🔗</button>
                                <button type="button" class="ea-tb-btn" title="插入分隔线" onclick="eaCmd('insertHorizontalRule')">—</button>
                                <button type="button" class="ea-tb-btn" title="清除格式" onclick="eaClearFormat()">✕ 格式</button>
                            </div>
                            <div class="ea-tb-sep"></div>
                            <!-- 短代码 -->
                            <div class="ea-tb-group">
                                <button type="button" class="ea-tb-btn ea-sc-btn" title="插入图片短代码" onclick="eaShortcode('image')">🖼 图片</button>
                                <button type="button" class="ea-tb-btn ea-sc-btn" title="插入视频短代码" onclick="eaShortcode('video')">▶ 视频</button>
                                <button type="button" class="ea-tb-btn ea-sc-btn" title="代码框" onclick="eaShortcode('code')">{ } 代码</button>
                                <button type="button" class="ea-tb-btn ea-sc-btn" title="链接按钮" onclick="eaShortcode('link')">🔘 链接</button>
                                <button type="button" class="ea-tb-btn ea-sc-btn" title="下载按钮" onclick="eaShortcode('download')">⬇ 下载</button>
                                <button type="button" class="ea-tb-btn ea-sc-btn" title="加密下载" onclick="eaShortcode('encrypted_download')">🔒 加密</button>
                            </div>
                            <div class="ea-tb-sep"></div>
                            <!-- 从媒体库插入 -->
                            <div class="ea-tb-group">
                                <button type="button" class="ea-tb-btn ea-tb-filepick" title="从 uploads 文件夹选择文件插入内容" onclick="eaOpenFilePicker()">📂 插入文件</button>
                            </div>
                        </div>

                        <!-- 可视化编辑区 -->
                        <div id="eaVisual"
                             class="ea-visual-editor"
                             contenteditable="true"
                             spellcheck="false"><?php echo $currentArticle['content'] ?? ''; ?></div>

                        <!-- HTML 代码编辑区（隐藏） -->
                        <textarea id="eaCode" class="ea-code-editor" name="content" style="display:none;"><?php echo htmlspecialchars($currentArticle['content'] ?? ''); ?></textarea>

                        <!-- 字数统计 -->
                        <div class="ea-wordcount">
                            <span id="ea-wc">字数：0</span>
                            <span style="opacity:.4">|</span>
                            <span id="ea-rt">阅读时长：0 分钟</span>
                        </div>
                    </div>
                </div>

            </div><!-- /ea-main -->

            <!-- ══ 侧边栏 ══ -->
            <div class="ea-sidebar">

                <!-- 发布操作 -->
                <div class="ea-card ea-publish-card">
                    <h3 class="ea-card-title">📤 发布</h3>
                    <div style="display:flex;flex-direction:column;gap:.6rem;margin-top:.75rem">
                        <button type="submit" class="btn btn-primary" style="width:100%">
                            <?php echo $isNewArticle ? '🚀 发布文章' : '💾 保存更改'; ?>
                        </button>
                        <a href="?page=articles" class="btn btn-secondary" style="width:100%;text-align:center">取消</a>
                    </div>
                </div>

                <!-- 封面图 -->
                <div class="ea-card">
                    <h3 class="ea-card-title">🖼 封面图</h3>
                    <div style="margin-top:.75rem;display:flex;flex-direction:column;gap:.6rem">
                        <!-- 预览区 -->
                        <div class="ea-cover-preview" id="eaCoverPreview">
                            <?php $ci = $currentArticle['cover_image'] ?? ''; ?>
                            <?php $ciAdmin = $ci ? preg_replace('#^serve_media\.php#', '../serve_media.php', $ci) : ''; ?>
                            <?php if ($ci): ?>
                                <img id="eaCoverImg" src="<?php echo htmlspecialchars($ciAdmin); ?>" alt="封面图预览">
                            <?php else: ?>
                                <div class="ea-cover-placeholder" id="eaCoverPlaceholder"><span>🖼</span><em>暂未设置封面图</em></div>
                                <img id="eaCoverImg" src="" alt="封面图预览" style="display:none">
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;gap:.45rem">
                            <button type="button" class="btn btn-secondary" style="flex:1;font-size:.82rem" onclick="eaOpenCoverPicker()">🔍 选择图片</button>
                            <button type="button" class="btn btn-secondary" style="font-size:.82rem;padding:.4rem .7rem" title="清除封面图" onclick="eaClearCover()">✕</button>
                        </div>
                        <div style="font-size:.75rem;color:var(--sub,#aaa)">从 uploads/images 快捷选取</div>
                    </div>
                </div>

                <!-- 文章属性 -->
                <div class="ea-card">
                    <h3 class="ea-card-title">📋 属性</h3>
                    <div style="display:flex;flex-direction:column;gap:.75rem;margin-top:.75rem">
                        <div class="mfg">
                            <label for="ea_date">发布日期</label>
                            <input type="date" id="ea_date" name="date"
                                   value="<?php echo htmlspecialchars($currentArticle['date'] ?? date('Y-m-d')); ?>">
                        </div>
                        <div class="mfg">
                            <label for="ea_tags">标签 <span class="ea-opt">（逗号分隔）</span></label>
                            <input type="text" id="ea_tags" name="tags" placeholder="PHP, 教程, Web…"
                                   value="<?php
                                       $tags = $currentArticle['tags'] ?? '';
                                       if (is_string($tags)) {
                                           $tags = array_map('trim', explode(',', $tags));
                                       }
                                       echo htmlspecialchars(implode(', ', $tags));
                                   ?>">
                        </div>
                    </div>
                </div>

            </div><!-- /ea-sidebar -->

        </div><!-- /ea-layout -->
    </form>

    <?php endif; ?>
</div><!-- /admin-section -->


<style>
/* ══════════════════════════════════════════════════
   Edit Article / Draft — scoped styles
   ══════════════════════════════════════════════════ */

/* ── Notice banner ── */
.ea-notice { padding:.75rem 1.1rem; border-radius:10px; font-size:.9rem; margin-bottom:1rem; }
.ea-notice-error { background:rgba(255,71,87,.08); border:1px solid rgba(255,71,87,.25); color:#c0392b; }

/* ── Layout ── */
.ea-layout { display:grid; grid-template-columns:1fr 280px; gap:1.2rem; align-items:start; }
@media (max-width:820px) { .ea-layout { grid-template-columns:1fr; } }

/* ── Card ── */
.ea-card {
    background:var(--admin-card,#fff);
    border:1px solid var(--admin-border,rgba(155,140,255,.35));
    border-radius:12px;
    padding:1.1rem 1.2rem;
    margin-bottom:1rem;
}
.ea-card-title {
    margin:0; font-size:.9rem; font-weight:700;
    color:#6c5dfb; display:flex; align-items:center; gap:.35rem;
}

/* ── Form overrides for this page ── */
.ea-main .mfg input[type=text],
.ea-main .mfg input[type=date],
.ea-main .mfg textarea,
.ea-sidebar .mfg input[type=text],
.ea-sidebar .mfg input[type=date] {
    padding:.5rem .75rem;
    border:1px solid var(--admin-border,rgba(155,140,255,.4));
    border-radius:8px; font-size:.92rem;
    box-sizing:border-box; width:100%;
    background:var(--admin-card,#fff); color:inherit;
    transition:border-color .15s;
}
.ea-main .mfg input:focus,
.ea-main .mfg textarea:focus,
.ea-sidebar .mfg input:focus {
    outline:none; border-color:#6c5dfb;
    box-shadow:0 0 0 3px rgba(108,93,251,.1);
}
.ea-main .mfg label, .ea-sidebar .mfg label {
    font-size:.83rem; font-weight:600; color:var(--sub,#666);
}
.ea-opt { font-weight:400; opacity:.65; font-size:.78rem; }

/* ── Publish card ── */
.ea-publish-card .btn { box-sizing:border-box; }

/* ── Mode switch ── */
.ea-mode-switch {
    display:flex; border:1px solid var(--admin-border,rgba(155,140,255,.4));
    border-radius:8px; overflow:hidden;
}
.ea-mode-btn {
    padding:.3rem .85rem; font-size:.8rem; font-weight:600; cursor:pointer;
    border:none; background:transparent; color:var(--sub,#777);
    transition:background .15s, color .15s;
}
.ea-mode-btn:hover { background:rgba(108,93,251,.07); }
.ea-mode-active { background:#6c5dfb !important; color:#fff !important; }

/* ── Toolbar ── */
.ea-toolbar {
    display:flex; flex-wrap:wrap; align-items:center; gap:.25rem;
    padding:.55rem .7rem;
    background:rgba(155,140,255,.05);
    border:1px solid var(--admin-border,rgba(155,140,255,.3));
    border-radius:8px 8px 0 0;
    border-bottom:none;
}
.ea-tb-group { display:flex; align-items:center; gap:.15rem; flex-wrap:wrap; }
.ea-tb-sep { width:1px; height:1.4rem; background:rgba(155,140,255,.3); margin:0 .2rem; flex-shrink:0; }
.ea-tb-btn {
    padding:.28rem .52rem; font-size:.8rem; cursor:pointer;
    border:1px solid transparent; border-radius:6px;
    background:transparent; color:inherit;
    transition:background .12s, border-color .12s;
    white-space:nowrap; line-height:1.4;
}
.ea-tb-btn:hover { background:rgba(108,93,251,.1); border-color:rgba(108,93,251,.25); }
.ea-tb-btn:active { background:rgba(108,93,251,.18); }
.ea-tb-select {
    padding:.28rem .4rem; font-size:.78rem; border-radius:6px;
    border:1px solid var(--admin-border,rgba(155,140,255,.4));
    background:var(--admin-card,#fff); color:inherit; cursor:pointer;
}
.ea-sc-btn { color:#6c5dfb; }

/* ── Color picker ── */
.ea-color-group { gap:.3rem; align-items:center; }
.ea-color-btn {
    display:flex; flex-direction:column; align-items:center;
    gap:1px; cursor:pointer; padding:.28rem .38rem; position:relative;
}
.ea-color-btn input[type=color] {
    position:absolute; opacity:0; width:100%; height:100%;
    top:0; left:0; cursor:pointer; border:none; padding:0;
}
.ea-color-bar { width:1.2rem; height:3px; border-radius:2px; margin-top:1px; }
.ea-quick-colors {
    display:flex; gap:.18rem; align-items:center; flex-wrap:wrap; max-width:120px;
}
.ea-qc {
    width:14px; height:14px; border-radius:3px; cursor:pointer;
    flex-shrink:0; transition:transform .1s;
}
.ea-qc:hover { transform:scale(1.25); }

/* ── Visual editor ── */
.ea-visual-editor {
    min-height:340px; padding:.9rem 1rem;
    border:1px solid var(--admin-border,rgba(155,140,255,.4));
    border-top:none;
    border-radius:0 0 8px 8px;
    font-size:.95rem; line-height:1.75;
    outline:none; overflow-y:auto;
    background:var(--admin-card,#fff);
    transition:box-shadow .15s;
}
.ea-visual-editor:focus {
    box-shadow:0 0 0 3px rgba(108,93,251,.1);
    border-color:#6c5dfb;
}
.ea-visual-editor h1,.ea-visual-editor h2,.ea-visual-editor h3,
.ea-visual-editor h4 { margin:.6em 0 .3em; }
.ea-visual-editor blockquote {
    border-left:3px solid #6c5dfb; margin:.5em 0;
    padding:.4em .9em; background:rgba(108,93,251,.05);
    border-radius:0 6px 6px 0; color:var(--sub,#666);
}
.ea-visual-editor pre {
    background:rgba(0,0,0,.04); padding:.7em 1em; border-radius:6px;
    font-family:Consolas,Monaco,monospace; font-size:.88em; overflow:auto;
}
.ea-visual-editor a { color:#6c5dfb; }
.ea-visual-editor img { max-width:100%; border-radius:4px; }

/* ── Code editor ── */
.ea-code-editor {
    width:100%; min-height:340px; padding:.9rem 1rem;
    border:1px solid var(--admin-border,rgba(155,140,255,.4));
    border-top:none;
    border-radius:0 0 8px 8px;
    font-family:Consolas,Monaco,'Courier New',monospace;
    font-size:.88rem; line-height:1.65; resize:vertical;
    box-sizing:border-box;
    background:#1e1e2e; color:#cdd6f4;
    outline:none;
}
.ea-code-editor:focus { box-shadow:0 0 0 3px rgba(108,93,251,.1); border-color:#6c5dfb; }

/* ── Word count ── */
.ea-wordcount {
    font-size:.78rem; color:var(--sub,#888);
    display:flex; gap:.6rem; margin-top:.4rem;
}

/* ── Dark mode ── */
body.dark-mode .ea-card { background:var(--dark-admin-card,#2a2a42dd); border-color:var(--dark-admin-border); }
body.dark-mode .ea-card-title { color:var(--dark-vio,#b096ff); }
body.dark-mode .ea-mode-switch { border-color:var(--dark-admin-border); }
body.dark-mode .ea-mode-btn { color:var(--dark-sub,#b0b0c5); }
body.dark-mode .ea-toolbar { background:rgba(176,160,255,.04); border-color:var(--dark-admin-border); }
body.dark-mode .ea-tb-sep { background:rgba(176,160,255,.2); }
body.dark-mode .ea-tb-btn { color:var(--dark-text,#eaeaea); }
body.dark-mode .ea-tb-btn:hover { background:rgba(176,160,255,.1); border-color:rgba(176,160,255,.25); }
body.dark-mode .ea-tb-select { background:var(--dark-admin-card,#2a2a42); border-color:var(--dark-admin-border); color:var(--dark-text,#eaeaea); }
body.dark-mode .ea-visual-editor { background:var(--dark-admin-card,#2a2a42aa); border-color:var(--dark-admin-border); color:var(--dark-text,#eaeaea); }
body.dark-mode .ea-visual-editor blockquote { border-left-color:var(--dark-vio,#b096ff); background:rgba(176,160,255,.06); color:var(--dark-sub,#b0b0c5); }
body.dark-mode .ea-visual-editor pre { background:rgba(255,255,255,.04); }
body.dark-mode .ea-main .mfg input,
body.dark-mode .ea-main .mfg textarea,
body.dark-mode .ea-sidebar .mfg input,
body.dark-mode .ea-sidebar .mfg select { background:#2a2a42aa; border-color:var(--dark-admin-border); color:var(--dark-text,#eaeaea); }
body.dark-mode .ea-main .mfg input:focus,
body.dark-mode .ea-main .mfg textarea:focus,
body.dark-mode .ea-sidebar .mfg input:focus { border-color:var(--dark-vio,#b096ff); box-shadow:0 0 0 3px rgba(176,160,255,.12); }
body.dark-mode .ea-main .mfg label, body.dark-mode .ea-sidebar .mfg label { color:var(--dark-sub,#b0b0c5); }
body.dark-mode .ea-notice-error { background:rgba(255,71,87,.07); border-color:rgba(255,71,87,.2); color:#eb5757; }

/* ── Cover image ── */
.ea-cover-preview {
    width:100%; aspect-ratio:16/9; border-radius:8px; overflow:hidden;
    border:1px solid var(--admin-border,rgba(155,140,255,.3));
    background:rgba(155,140,255,.05);
    display:flex; align-items:center; justify-content:center;
    position:relative;
}
.ea-cover-preview img { width:100%; height:100%; object-fit:cover; display:block; }
.ea-cover-placeholder {
    display:flex; flex-direction:column; align-items:center; gap:.3rem;
    opacity:.35; user-select:none;
}
.ea-cover-placeholder span { font-size:2rem; }
.ea-cover-placeholder em { font-size:.65rem; font-style:normal; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--primary,#6c5dfb); }

/* ── File picker toolbar button ── */
.ea-tb-filepick { color:#27ae60; }

/* ── Picker Modal (shared by cover + file picker) ── */
.ea-picker-modal {
    display:none; position:fixed; inset:0; z-index:9900;
    background:rgba(0,0,0,.55); align-items:center; justify-content:center;
    backdrop-filter:blur(3px);
}
.ea-picker-modal.open { display:flex; }
.ea-picker-box {
    background:var(--admin-card,#fff);
    border:1px solid var(--admin-border,rgba(155,140,255,.3));
    border-radius:14px; width:min(860px,95vw); max-height:88vh;
    display:flex; flex-direction:column;
    box-shadow:0 20px 60px rgba(0,0,0,.3);
    animation:eaPickerIn .18s ease;
}
@keyframes eaPickerIn { from { opacity:0; transform:scale(.96) translateY(8px); } }
.ea-picker-hd {
    display:flex; align-items:center; justify-content:space-between;
    padding:.9rem 1.2rem; border-bottom:1px solid var(--admin-border,rgba(155,140,255,.2));
    flex-shrink:0;
}
.ea-picker-hd h3 { margin:0; font-size:.95rem; color:#6c5dfb; }
.ea-picker-hd button { background:none; border:none; cursor:pointer; font-size:1.1rem; color:var(--sub,#888); padding:.2rem .4rem; border-radius:6px; }
.ea-picker-hd button:hover { background:rgba(255,71,87,.1); color:#e74c3c; }
.ea-picker-toolbar {
    display:flex; gap:.6rem; align-items:center; flex-wrap:wrap;
    padding:.65rem 1.2rem; border-bottom:1px solid var(--admin-border,rgba(155,140,255,.1));
    flex-shrink:0;
}
.ea-picker-tabs { display:flex; gap:.3rem; flex-wrap:wrap; }
.ea-picker-tab {
    padding:.25rem .72rem; border-radius:20px; font-size:.8rem; cursor:pointer;
    border:1px solid var(--admin-border,rgba(155,140,255,.3));
    background:transparent; color:var(--sub,#666); transition:all .13s;
}
.ea-picker-tab:hover { border-color:#6c5dfb; color:#6c5dfb; }
.ea-picker-tab.active { background:#6c5dfb; color:#fff; border-color:#6c5dfb; }
.ea-picker-search {
    flex:1; min-width:140px; padding:.3rem .7rem; font-size:.85rem;
    border:1px solid var(--admin-border,rgba(155,140,255,.35)); border-radius:8px;
    background:var(--admin-card,#fff); color:inherit;
}
.ea-picker-search:focus { outline:none; border-color:#6c5dfb; box-shadow:0 0 0 2px rgba(108,93,251,.1); }
.ea-picker-grid {
    display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr));
    gap:.7rem; padding:1rem 1.2rem; overflow-y:auto; flex:1;
    min-height:200px;
}
.ea-picker-loading { grid-column:1/-1; text-align:center; padding:2.5rem; color:var(--sub,#aaa); }
.ea-picker-empty { grid-column:1/-1; text-align:center; padding:2rem; color:var(--sub,#aaa); font-size:.88rem; }
.ea-picker-item {
    border:2px solid transparent; border-radius:10px; overflow:hidden;
    cursor:pointer; transition:border-color .13s, transform .12s;
    background:rgba(155,140,255,.05);
    display:flex; flex-direction:column;
    position:relative;
}
.ea-picker-item:hover { border-color:#6c5dfb; transform:translateY(-2px); }
.ea-picker-item.selected { border-color:#6c5dfb; box-shadow:0 0 0 3px rgba(108,93,251,.18); }
.ea-picker-thumb {
    aspect-ratio:4/3; display:flex; align-items:center; justify-content:center;
    overflow:hidden; background:rgba(155,140,255,.06);
}
.ea-picker-thumb img { width:100%; height:100%; object-fit:cover; }
.ea-picker-thumb-icon { font-size:2.2rem; }
.ea-picker-name {
    padding:.3rem .4rem; font-size:.72rem; font-weight:600;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    border-top:1px solid rgba(155,140,255,.12);
}
.ea-picker-ft {
    display:flex; align-items:center; justify-content:flex-end; gap:.5rem;
    padding:.75rem 1.2rem; border-top:1px solid var(--admin-border,rgba(155,140,255,.15));
    flex-shrink:0;
}
/* Insert options for file picker */
.ea-picker-insert-opts { display:flex; gap:.4rem; flex-wrap:wrap; }
.ea-picker-insert-opts button { font-size:.8rem; }

/* dark mode */
body.dark-mode .ea-picker-box { background:var(--dark-admin-card,#2a2a42); border-color:var(--dark-admin-border); }
body.dark-mode .ea-picker-hd { border-bottom-color:var(--dark-admin-border); }
body.dark-mode .ea-picker-hd h3 { color:var(--dark-vio,#b096ff); }
body.dark-mode .ea-picker-hd button { color:var(--dark-sub,#b0b0c5); }
body.dark-mode .ea-picker-toolbar { border-bottom-color:var(--dark-admin-border); }
body.dark-mode .ea-picker-tab { border-color:var(--dark-admin-border); color:var(--dark-sub,#b0b0c5); }
body.dark-mode .ea-picker-tab:hover { color:var(--dark-vio,#b096ff); border-color:var(--dark-vio,#b096ff); }
body.dark-mode .ea-picker-tab.active { background:var(--dark-vio,#b096ff); color:#1a1a2e; border-color:var(--dark-vio,#b096ff); }
body.dark-mode .ea-picker-search { background:#2a2a42aa; border-color:var(--dark-admin-border); color:var(--dark-text,#eaeaea); }
body.dark-mode .ea-picker-item { background:rgba(176,160,255,.04); }
body.dark-mode .ea-picker-item:hover { border-color:var(--dark-vio,#b096ff); }
body.dark-mode .ea-picker-item.selected { border-color:var(--dark-vio,#b096ff); box-shadow:0 0 0 3px rgba(176,160,255,.15); }
body.dark-mode .ea-picker-name { border-top-color:rgba(176,160,255,.1); }
body.dark-mode .ea-picker-ft { border-top-color:var(--dark-admin-border); }
body.dark-mode .ea-cover-preview { border-color:var(--dark-admin-border); background:rgba(176,160,255,.04); }
</style>

<!-- ══ 封面图选择器 Modal ══════════════════════════════════════════════════ -->
<div id="eaCoverModal" class="ea-picker-modal" onclick="if(event.target===this)eaCloseCoverPicker()">
    <div class="ea-picker-box">
        <div class="ea-picker-hd">
            <h3>🖼 选择封面图</h3>
            <button type="button" onclick="eaCloseCoverPicker()">✕</button>
        </div>
        <div class="ea-picker-toolbar">
            <span style="font-size:.82rem;color:var(--sub,#888)">uploads/images/</span>
            <input type="text" class="ea-picker-search" id="eaCoverSearch" placeholder="搜索图片名…" oninput="eaCoverFilter()">
        </div>
        <div class="ea-picker-grid" id="eaCoverGrid">
            <div class="ea-picker-loading">⏳ 加载中…</div>
        </div>
        <div class="ea-picker-ft">
            <button type="button" class="btn btn-secondary" onclick="eaCloseCoverPicker()">取消</button>
            <button type="button" class="btn btn-primary" id="eaCoverConfirmBtn" disabled onclick="eaConfirmCover()">✓ 使用此图</button>
        </div>
    </div>
</div>

<!-- ══ 文件插入选择器 Modal ════════════════════════════════════════════════ -->
<div id="eaFileModal" class="ea-picker-modal" onclick="if(event.target===this)eaCloseFilePicker()">
    <div class="ea-picker-box">
        <div class="ea-picker-hd">
            <h3>📂 从媒体库插入文件</h3>
            <button type="button" onclick="eaCloseFilePicker()">✕</button>
        </div>
        <div class="ea-picker-toolbar">
            <div class="ea-picker-tabs" id="eaFileTabs">
                <button type="button" class="ea-picker-tab active" data-folder="all"    onclick="eaFileTab('all',this)">全部</button>
                <button type="button" class="ea-picker-tab"        data-folder="images" onclick="eaFileTab('images',this)">🖼 图片</button>
                <button type="button" class="ea-picker-tab"        data-folder="videos" onclick="eaFileTab('videos',this)">🎬 视频</button>
                <button type="button" class="ea-picker-tab"        data-folder="audios" onclick="eaFileTab('audios',this)">🎵 音频</button>
                <button type="button" class="ea-picker-tab"        data-folder="files"  onclick="eaFileTab('files',this)">📄 其他</button>
            </div>
            <input type="text" class="ea-picker-search" id="eaFileSearch" placeholder="搜索文件名…" oninput="eaFileFilter()">
        </div>
        <div class="ea-picker-grid" id="eaFileGrid">
            <div class="ea-picker-loading">⏳ 加载中…</div>
        </div>
        <div class="ea-picker-ft">
            <div id="eaFileInsertOpts" class="ea-picker-insert-opts" style="flex:1">
                <!-- 根据选中文件类型动态显示插入方式 -->
            </div>
            <button type="button" class="btn btn-secondary" onclick="eaCloseFilePicker()">取消</button>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════
   封面图 & 文件插入选择器
   ══════════════════════════════════════════════════ */
(function() {

const MEDIA_AJAX  = 'admin_media_ajax.php';
const IMG_EXTS    = new Set(['jpg','jpeg','png','gif','webp','svg','bmp','ico','avif','tiff','tif']);
const VIDEO_EXTS  = new Set(['mp4','webm','avi','mov','mkv','flv','wmv','m4v','3gp','ogv']);
const AUDIO_EXTS  = new Set(['mp3','wav','ogg','flac','aac','m4a','wma','opus','aiff']);
const EXT_ICONS   = { pdf:'📄', zip:'🗜️', rar:'🗜️', doc:'📝', docx:'📝', xls:'📊', xlsx:'📊', ppt:'📑', pptx:'📑', txt:'📃', md:'📃', js:'💻', css:'🎨', py:'🐍' };

/* ── 辅助：HTML 转义 ── */
function he(s) {
    return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
/* ── 辅助：从 proxy URL 转公开 URL（走根目录 serve_media.php，无需登录）── */
function toPublicUrl(proxyUrl) {
    try {
        const u      = new URL(proxyUrl, location.href);
        const folder = u.searchParams.get('folder');
        const fname  = u.searchParams.get('name');
        if (folder && fname) {
            return 'serve_media.php?folder=' + encodeURIComponent(folder)
                 + '&name=' + encodeURIComponent(fname);
        }
    } catch(e) {}
    return proxyUrl;
}
/* ── 辅助：文件扩展名 ── */
function ext(name) { return (name.split('.').pop() || '').toLowerCase(); }

/* ────────────────────────────────────────────────
   封面图选择器
   ──────────────────────────────────────────────── */
let _coverAll = [];
let _coverSel = null; // { url, name }

window.eaOpenCoverPicker = async function() {
    _coverSel = null;
    document.getElementById('eaCoverConfirmBtn').disabled = true;
    document.getElementById('eaCoverSearch').value = '';
    document.getElementById('eaCoverModal').classList.add('open');
    await _loadCoverImages();
};
window.eaCloseCoverPicker = function() {
    document.getElementById('eaCoverModal').classList.remove('open');
};
window.eaConfirmCover = function() {
    if (!_coverSel) return;
    const storeUrl   = _coverSel.url;
    const previewUrl = storeUrl.replace(/^serve_media\.php/, '../serve_media.php');
    document.getElementById('ea_cover_image').value = storeUrl;
    const img = document.getElementById('eaCoverImg');
    const ph  = document.getElementById('eaCoverPlaceholder');
    img.src = previewUrl;
    img.style.display = 'block';
    if (ph) ph.style.display = 'none';
    eaCloseCoverPicker();
};
window.eaClearCover = function() {
    document.getElementById('ea_cover_image').value = '';
    const img = document.getElementById('eaCoverImg');
    const ph  = document.getElementById('eaCoverPlaceholder');
    img.src = '';
    img.style.display = 'none';
    if (ph) ph.style.display = 'flex';
};

async function _loadCoverImages() {
    const grid = document.getElementById('eaCoverGrid');
    grid.innerHTML = '<div class="ea-picker-loading">⏳ 加载中…</div>';
    try {
        const fd = new FormData();
        fd.append('act','list'); fd.append('folder','images');
        const res  = await fetch(MEDIA_AJAX, { method:'POST', body:fd });
        const data = await res.json();
        _coverAll = (data.files || []).filter(f => IMG_EXTS.has(ext(f.name)));
        eaCoverFilter();
    } catch(e) {
        grid.innerHTML = '<div class="ea-picker-empty">加载失败：' + he(e.message) + '</div>';
    }
}
window.eaCoverFilter = function() {
    const q = document.getElementById('eaCoverSearch').value.toLowerCase().trim();
    const filtered = q ? _coverAll.filter(f => f.name.toLowerCase().includes(q)) : _coverAll;
    _renderCoverGrid(filtered);
};
function _renderCoverGrid(files) {
    const grid = document.getElementById('eaCoverGrid');
    if (!files.length) {
        grid.innerHTML = '<div class="ea-picker-empty">📭 uploads/images/ 暂无图片</div>';
        return;
    }
    grid.innerHTML = files.map(f => {
        const pub = toPublicUrl(f.url);
        return `<div class="ea-picker-item" data-url="${he(pub)}" data-name="${he(f.name)}" onclick="_selectCoverItem(this,'${he(pub)}','${he(f.name)}')">
            <div class="ea-picker-thumb"><img src="${he(f.url)}" alt="${he(f.name)}" loading="lazy" onerror="this.style.display='none'"></div>
            <div class="ea-picker-name" title="${he(f.name)}">${he(f.name)}</div>
        </div>`;
    }).join('');
}
window._selectCoverItem = function(el, url, name) {
    document.querySelectorAll('#eaCoverGrid .ea-picker-item').forEach(i => i.classList.remove('selected'));
    el.classList.add('selected');
    _coverSel = { url, name };
    document.getElementById('eaCoverConfirmBtn').disabled = false;
};

/* ────────────────────────────────────────────────
   文件插入选择器
   ──────────────────────────────────────────────── */
let _fileAll      = [];
let _fileFolder   = 'all';
let _fileSel      = null; // file object

window.eaOpenFilePicker = async function() {
    _fileSel = null;
    _fileFolder = 'all';
    document.getElementById('eaFileSearch').value = '';
    // Reset tabs
    document.querySelectorAll('#eaFileTabs .ea-picker-tab').forEach(b => {
        b.classList.toggle('active', b.dataset.folder === 'all');
    });
    document.getElementById('eaFileInsertOpts').innerHTML = '';
    document.getElementById('eaFileModal').classList.add('open');
    await _loadAllFiles();
};
window.eaCloseFilePicker = function() {
    document.getElementById('eaFileModal').classList.remove('open');
};
window.eaFileTab = function(folder, btn) {
    _fileFolder = folder;
    document.querySelectorAll('#eaFileTabs .ea-picker-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    eaFileFilter();
};

async function _loadAllFiles() {
    const grid = document.getElementById('eaFileGrid');
    grid.innerHTML = '<div class="ea-picker-loading">⏳ 加载中…</div>';
    try {
        const fd = new FormData();
        fd.append('act','list'); fd.append('folder','all');
        const res  = await fetch(MEDIA_AJAX, { method:'POST', body:fd });
        const data = await res.json();
        _fileAll = data.files || [];
        eaFileFilter();
    } catch(e) {
        grid.innerHTML = '<div class="ea-picker-empty">加载失败：' + he(e.message) + '</div>';
    }
}
window.eaFileFilter = function() {
    const q = document.getElementById('eaFileSearch').value.toLowerCase().trim();
    const filtered = _fileAll.filter(f => {
        if (_fileFolder !== 'all' && f.folder !== _fileFolder) return false;
        if (q && !f.name.toLowerCase().includes(q)) return false;
        return true;
    });
    _renderFileGrid(filtered);
};
function _renderFileGrid(files) {
    const grid = document.getElementById('eaFileGrid');
    if (!files.length) {
        grid.innerHTML = '<div class="ea-picker-empty">📭 暂无文件</div>';
        return;
    }
    grid.innerHTML = files.map(f => {
        const pub   = toPublicUrl(f.url);
        const fExt  = ext(f.name);
        const isImg = IMG_EXTS.has(fExt);
        const isVid = VIDEO_EXTS.has(fExt);
        const isAud = AUDIO_EXTS.has(fExt);
        const icon  = isImg ? '🖼️' : isVid ? '🎬' : isAud ? '🎵' : (EXT_ICONS[fExt] || '📁');
        const thumbHtml = isImg
            ? `<img src="${he(f.url)}" alt="${he(f.name)}" loading="lazy" onerror="this.style.display='none'">`
            : `<span class="ea-picker-thumb-icon">${icon}</span>`;
        const fdata = he(JSON.stringify({ name: f.name, folder: f.folder, url: pub, ext: fExt }));
        return `<div class="ea-picker-item" data-pub="${he(pub)}" onclick="_selectFileItem(this,${fdata})">
            <div class="ea-picker-thumb">${thumbHtml}</div>
            <div class="ea-picker-name" title="${he(f.name)}">${he(f.name)}</div>
        </div>`;
    }).join('');
}

window._selectFileItem = function(el, f) {
    document.querySelectorAll('#eaFileGrid .ea-picker-item').forEach(i => i.classList.remove('selected'));
    el.classList.add('selected');
    _fileSel = f;
    _renderFileInsertOpts(f);
};
function _renderFileInsertOpts(f) {
    const optsEl = document.getElementById('eaFileInsertOpts');
    const isImg  = IMG_EXTS.has(f.ext);
    const isVid  = VIDEO_EXTS.has(f.ext);
    const isAud  = AUDIO_EXTS.has(f.ext);
    let btns = '';
    if (isImg) {
        btns += `<button type="button" class="btn btn-primary" onclick="eaInsertFile('img')">🖼 插入图片</button>`;
        btns += `<button type="button" class="btn btn-secondary" onclick="eaInsertFile('link')">🔗 插入链接</button>`;
    } else if (isVid) {
        btns += `<button type="button" class="btn btn-primary" onclick="eaInsertFile('video')">▶ 插入视频</button>`;
        btns += `<button type="button" class="btn btn-secondary" onclick="eaInsertFile('link')">🔗 插入链接</button>`;
    } else {
        btns += `<button type="button" class="btn btn-primary" onclick="eaInsertFile('download')">⬇ 插入下载</button>`;
        btns += `<button type="button" class="btn btn-secondary" onclick="eaInsertFile('link')">🔗 插入链接</button>`;
    }
    optsEl.innerHTML = btns;
}
window.eaInsertFile = function(mode) {
    if (!_fileSel) return;
    const f   = _fileSel;
    let sc = '';
    if (mode === 'img') {
        sc = `[image url="${f.url}" alt="${f.name}"]`;
    } else if (mode === 'video') {
        sc = `[video url="${f.url}"]`;
    } else if (mode === 'download') {
        sc = `[download text="${f.name}" url="${f.url}"]`;
    } else {
        sc = `<a href="${f.url}" target="_blank" rel="noopener">${f.name}</a>`;
    }
    // Get eaMode and editor from outer scope (articleForm scope)
    const visual = document.getElementById('eaVisual');
    const code   = document.getElementById('eaCode');
    const modeEl = document.getElementById('btnVisual');
    const isVisual = modeEl && modeEl.classList.contains('ea-mode-active');
    if (isVisual && mode !== 'link') {
        visual.focus();
        document.execCommand('insertText', false, sc);
    } else if (isVisual && mode === 'link') {
        visual.focus();
        document.execCommand('insertHTML', false, sc);
    } else {
        const s = code.selectionStart, e = code.selectionEnd;
        code.value = code.value.slice(0,s) + sc + code.value.slice(e);
        code.selectionStart = code.selectionEnd = s + sc.length;
        code.focus();
    }
    eaCloseFilePicker();
};

/* ── ESC 关闭 ── */
document.addEventListener('keydown', function(e) {
    if (e.key !== 'Escape') return;
    eaCloseCoverPicker();
    eaCloseFilePicker();
});

})();
</script>

<script>
(function() {
    /* ── 编辑器状态 ── */
    let eaMode = 'visual'; // 'visual' | 'code'
    const visual  = document.getElementById('eaVisual');
    const code    = document.getElementById('eaCode');
    const form    = document.getElementById('articleForm');

    /* ── 工具栏点击不抢走编辑器焦点（保留选区） ── */
    /* select 元素的下拉依赖 mousedown 默认行为，需排除在外 */
    document.getElementById('eaToolbar').addEventListener('mousedown', function(e) {
        if (e.target.tagName === 'SELECT') return;
        e.preventDefault();
    });

    /* ── 模式切换 ── */
    window.eaSwitchMode = function(mode) {
        if (mode === eaMode) return;
        if (mode === 'code') {
            code.value = visual.innerHTML;
            visual.style.display = 'none';
            code.style.display   = 'block';
            document.getElementById('eaToolbar').style.opacity = '.4';
            document.getElementById('eaToolbar').style.pointerEvents = 'none';
        } else {
            visual.innerHTML     = code.value;
            code.style.display   = 'none';
            visual.style.display = 'block';
            document.getElementById('eaToolbar').style.opacity = '';
            document.getElementById('eaToolbar').style.pointerEvents = '';
        }
        eaMode = mode;
        document.getElementById('btnVisual').classList.toggle('ea-mode-active', mode === 'visual');
        document.getElementById('btnCode').classList.toggle('ea-mode-active', mode === 'code');
    };

    /* ── 表单提交：AJAX 对接 admin_ajax.php ── */
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // 1. 同步可视化编辑器内容到隐藏 textarea
            if (eaMode === 'visual') {
                code.value = visual.innerHTML;
            }
            code.name = 'content';

            // 2. 构建 FormData（已含 type=article, article_action=save, cover_image 等）
            const fd = new FormData(form);

            // 3. 提交状态
            const submitBtn = form.querySelector('button[type="submit"]');
            const origHtml  = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '⏳ 保存中…'; }

            // 4. 发送到 admin_ajax.php
            fetch('admin_ajax.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        _showSaveToast('✅ ' + (data.msg || '保存成功！'), 'success');
                        // 新建文章保存后跳转到编辑页
                        if (data.id) {
                            setTimeout(() => {
                                window.location.href = '?page=edit_article&id=' + data.id;
                            }, 800);
                        }
                    } else {
                        _showSaveToast('❌ ' + (data.msg || '保存失败，请重试'), 'error');
                    }
                })
                .catch(err => {
                    _showSaveToast('❌ 网络错误：' + err.message, 'error');
                })
                .finally(() => {
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = origHtml; }
                });
        });
    }

    /* ── Toast 提示 ── */
    function _showSaveToast(msg, type) {
        let t = document.getElementById('_eaSaveToast');
        if (!t) {
            t = document.createElement('div');
            t.id = '_eaSaveToast';
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

    /* ── execCommand 包装 ── */
    window.eaCmd = function(cmd, val) {
        if (eaMode !== 'visual') return;
        visual.focus();
        document.execCommand(cmd, false, val || null);
    };

    /* ── 段落格式 ── */
    window.eaBlock = function(tag) {
        if (!tag || eaMode !== 'visual') return;
        visual.focus();
        document.execCommand('formatBlock', false, '<' + tag + '>');
    };

    /* ── 字体大小（用 span style） ── */
    window.eaFontSize = function(size) {
        if (!size || eaMode !== 'visual') return;
        visual.focus();
        const sel = window.getSelection();
        if (!sel || sel.isCollapsed) return;
        document.execCommand('fontSize', false, '7'); // 先设占位
        visual.querySelectorAll('font[size="7"]').forEach(el => {
            el.removeAttribute('size');
            el.style.fontSize = size;
        });
    };

    /* ── 颜色 ── */
    window.eaColor = function(cmd, hex) {
        if (eaMode !== 'visual') return;
        visual.focus();
        document.execCommand(cmd, false, hex);
        if (cmd === 'foreColor') document.getElementById('eaFgBar').style.background = hex;
        if (cmd === 'hiliteColor') document.getElementById('eaBgBar').style.background = hex;
    };
    window.eaRemoveColor = function() {
        if (eaMode !== 'visual') return;
        visual.focus();
        document.execCommand('foreColor', false, 'inherit');
        document.execCommand('hiliteColor', false, 'transparent');
    };

    /* ── 清除格式 ── */
    window.eaClearFormat = function() {
        if (eaMode !== 'visual') return;
        visual.focus();
        document.execCommand('removeFormat');
        document.execCommand('formatBlock', false, '<p>');
    };

    /* ── 插入链接 ── */
    window.eaInsertLink = function() {
        if (eaMode !== 'visual') return;
        const url = prompt('请输入链接地址：', 'https://');
        if (!url) return;
        visual.focus();
        document.execCommand('createLink', false, url);
        // 设置 target="_blank"
        visual.querySelectorAll('a[href="' + url + '"]:not([target])').forEach(a => {
            a.target = '_blank'; a.rel = 'noopener';
        });
    };

    /* ── 短代码插入 ── */
    window.eaShortcode = function(type) {
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
        if (eaMode === 'visual') {
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
        const text = eaMode === 'visual'
            ? (visual.innerText || visual.textContent || '')
            : code.value.replace(/<[^>]+>/g, '');
        const count = text.replace(/\s+/g,' ').trim().length;
        const mins  = Math.max(1, Math.round(count / 400));
        document.getElementById('ea-wc').textContent  = '字数：' + count;
        document.getElementById('ea-rt').textContent  = '阅读时长：' + mins + ' 分钟';
    }
    visual.addEventListener('input', updateWordCount);
    code.addEventListener('input', updateWordCount);
    updateWordCount();

    /* ── Tab 键在代码模式插入空格 ── */
    code.addEventListener('keydown', function(e) {
        if (e.key === 'Tab') {
            e.preventDefault();
            const s = this.selectionStart;
            this.value = this.value.slice(0, s) + '    ' + this.value.slice(this.selectionEnd);
            this.selectionStart = this.selectionEnd = s + 4;
        }
    });

    /* ── 阻止可视化编辑器内 form 意外提交 ── */
    visual.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.shiftKey) {
            e.preventDefault();
            document.execCommand('insertHTML', false, '<br>');
        }
    });

})();
</script>