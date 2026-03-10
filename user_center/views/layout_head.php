<?php
/**
 * 页面头部布局
 * 依赖：$user（nickname）、$message、$error、$activeTab
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>用户中心 - <?php echo htmlspecialchars($user['nickname']); ?></title>
    <link rel="stylesheet" href="../style.css">
    <style>
        /* ══════════════════════════════════════════════════════
           用户中心 — 与 style.css 设计系统保持统一
           Primary: #6c5dfb  ·  Card border: rgba(155,140,255,.3)
           ══════════════════════════════════════════════════════ */

        /* ── 全局 box-sizing 修正（防止 width:100% + padding 溢出）── */
        .user-center-wrap *,
        .user-center-wrap *::before,
        .user-center-wrap *::after {
            box-sizing: border-box;
        }

        /* ── 页面包裹层 ────────────────────────────────────────── */
        .user-center-wrap {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: clamp(16px, 4vw, 48px);
            padding-top: clamp(20px, 5vw, 56px);
            position: relative;
            z-index: 1;
            /* 防止包裹层自身溢出 */
            overflow-x: hidden;
        }

        /* ── 主卡片 ────────────────────────────────────────────── */
        .user-center-card {
            /* 改用 100% 宽度，由 wrap 的 padding 控制边距，避免 vw 叠加 */
            width: 100%;
            max-width: 1020px;
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-xl);
            padding: clamp(16px, 3vw, 38px);
            box-shadow: 0 4px 28px rgba(108,93,251,.09), 0 1px 4px rgba(108,93,251,.05);
            margin: 0 auto;
            transition: background .3s, border-color .3s, box-shadow .3s;
            /* 内容溢出时剪裁，不允许卡片撑开 */
            overflow: hidden;
        }
        body.dark-mode .user-center-card {
            background: var(--dark-card);
            border-color: var(--dark-card-border);
            box-shadow: 0 6px 32px rgba(0,0,0,.32);
        }

        /* ── 页头 ──────────────────────────────────────────────── */
        .user-center-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--card-border);
            flex-wrap: wrap;
            gap: 14px;
        }
        body.dark-mode .user-center-header { border-bottom-color: var(--dark-card-border); }

        .header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-center-title {
            font-size: clamp(1.15rem, 3.5vw, 1.85rem);
            font-weight: 900;
            letter-spacing: -.02em;
            margin: 0;
            color: var(--text);
            /* 防止长标题撑开 */
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .user-center-title span { color: var(--primary); }
        body.dark-mode .user-center-title { color: var(--dark-text); }

        /* 主题切换按钮 */
        .theme-toggle-header {
            background: var(--primary-soft);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-md);
            width: 36px; height: 36px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1rem;
            color: var(--primary);
            line-height: 1;
            transition: background .15s, transform .2s;
        }
        .theme-toggle-header:hover {
            background: rgba(108,93,251,.16);
            transform: rotate(20deg);
        }
        body.dark-mode .theme-toggle-header {
            background: rgba(176,160,255,.1);
            border-color: var(--dark-card-border);
            color: var(--dark-vio);
        }

        /* 返回首页按钮 */
        .back-home {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            font-size: .85rem;
            font-weight: 700;
            color: var(--sub);
            text-decoration: none;
            padding: .38rem .85rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--card-border);
            background: var(--primary-soft);
            white-space: nowrap;
            flex-shrink: 0;
            transition: color .14s, background .14s, transform .14s;
        }
        .back-home:hover {
            color: var(--primary);
            background: rgba(108,93,251,.13);
            border-color: rgba(108,93,251,.28);
            transform: translateY(-1px);
            text-decoration: none;
        }
        body.dark-mode .back-home {
            color: var(--dark-sub);
            background: rgba(176,160,255,.08);
            border-color: var(--dark-card-border);
        }
        body.dark-mode .back-home:hover { color: var(--dark-vio); background: rgba(176,160,255,.14); }

        /* ── 内容网格 ────────────────────────────────────────────── */
        .user-center-content {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 20px;
            align-items: start;
            /* 关键：防止网格列超出父容器 */
            min-width: 0;
            width: 100%;
        }
        /* 关键：所有网格直接子项必须设 min-width:0，否则内容会撑破列宽 */
        .user-center-content > * {
            min-width: 0;
        }

        /* ── 移动端全局响应 ──────────────────────────────────────── */
        @media (max-width: 768px) {
            .user-center-wrap  { padding: 10px 8px; }
            .user-center-card  { padding: 14px 12px; border-radius: 16px; }
            .user-center-header {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
                margin-bottom: 14px;
                padding-bottom: 12px;
            }
            .user-center-content {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }

        /* ── 侧边栏 ─────────────────────────────────────────────── */
        .sidebar {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            padding: 1rem;
            box-shadow: 0 2px 10px rgba(108,93,251,.05);
            position: sticky;
            top: 24px;
            transition: background .3s, border-color .3s;
        }
        body.dark-mode .sidebar {
            background: var(--dark-card);
            border-color: var(--dark-card-border);
        }
        .sidebar-menu { list-style: none; padding: 0; margin: 0; }
        .sidebar-menu li { margin-bottom: 4px; }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: .6rem .85rem;
            border-radius: var(--radius-md);
            color: var(--sub);
            text-decoration: none;
            font-weight: 600;
            font-size: .88rem;
            transition: color .15s, background .15s, transform .15s;
            border: 1px solid transparent;
        }
        .sidebar-menu a:hover {
            background: var(--primary-soft);
            color: var(--primary);
            transform: translateX(3px);
        }
        .sidebar-menu a.active {
            background: var(--primary-soft);
            color: var(--primary);
            border-color: rgba(108,93,251,.22);
            box-shadow: 0 2px 8px rgba(108,93,251,.1);
            font-weight: 700;
        }
        .sidebar-menu a svg { width: 16px; height: 16px; flex-shrink: 0; }
        body.dark-mode .sidebar-menu a { color: var(--dark-sub); }
        body.dark-mode .sidebar-menu a:hover { color: var(--dark-vio); background: rgba(176,160,255,.1); }
        body.dark-mode .sidebar-menu a.active { background: rgba(176,160,255,.12); color: var(--dark-vio); border-color: rgba(176,160,255,.22); }

        /* ── 移动端：侧边栏变为水平滚动 Tab 栏 ───────────────────── */
        @media (max-width: 768px) {
            .sidebar {
                position: static;
                padding: 6px;
                border-radius: 12px;
                margin-bottom: 12px;
                box-shadow: none;
            }
            .sidebar-menu {
                display: flex;
                flex-direction: row;
                gap: 4px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
                padding-bottom: 2px;
            }
            .sidebar-menu::-webkit-scrollbar { display: none; }
            .sidebar-menu li { margin-bottom: 0; flex-shrink: 0; }
            .sidebar-menu a {
                padding: 7px 13px;
                font-size: .81rem;
                gap: 5px;
                white-space: nowrap;
                border-radius: 9px;
                transform: none !important;
            }
            .sidebar-menu a svg { width: 14px; height: 14px; }
        }

        /* ── 主内容区 ────────────────────────────────────────────── */
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            /* 关键：防止主内容撑破网格 */
            min-width: 0;
            overflow: hidden;
        }

        /* 内容分区卡片 */
        .profile-section {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            padding: 1.5rem 1.6rem;
            box-shadow: 0 2px 12px rgba(108,93,251,.06);
            transition: background .3s, border-color .3s;
            /* 防止内部内容撑出 */
            overflow: hidden;
            word-break: break-word;
        }
        body.dark-mode .profile-section {
            background: var(--dark-card);
            border-color: var(--dark-card-border);
        }
        @media (max-width: 768px) {
            .profile-section { padding: 1rem .9rem; border-radius: 12px; }
        }

        /* 分区标题 */
        .profile-section h2 {
            font-size: 1.1rem;
            font-weight: 800;
            margin: 0 0 1.4rem;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: .5rem;
            padding-bottom: .7rem;
            border-bottom: 1px solid rgba(155,140,255,.12);
        }
        .profile-section h2::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 1em;
            border-radius: 2px;
            background: linear-gradient(180deg, var(--primary), rgba(155,140,255,.5));
            flex-shrink: 0;
        }
        body.dark-mode .profile-section h2 {
            color: var(--dark-text);
            border-bottom-color: rgba(176,160,255,.12);
        }

        /* ── 头像区域 ────────────────────────────────────────────── */
        .avatar-container {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.75rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--card-border);
        }
        body.dark-mode .avatar-container { border-bottom-color: var(--dark-card-border); }
        @media (max-width: 600px) {
            .avatar-container {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
                align-items: center;
                margin-bottom: 1.2rem;
                padding-bottom: 1.2rem;
            }
            .avatar-info { width: 100%; }
            .avatar-upload { justify-content: center; }
            .form-actions { flex-direction: column; align-items: stretch; }
            .form-actions .btn, .form-actions .btn-logout {
                width: 100%;
                justify-content: center;
                padding: .7rem 1rem;
                font-size: .9rem;
            }
            .btn-logout { text-align: center; }
        }
        .avatar-preview {
            width: 88px; height: 88px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            box-shadow: 0 4px 16px rgba(108,93,251,.22), 0 0 0 4px var(--primary-soft);
            flex-shrink: 0;
        }
        .avatar-info h3 {
            margin: 0 0 .3rem;
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -.01em;
        }
        body.dark-mode .avatar-info h3 { color: var(--dark-text); }
        .avatar-info p {
            margin: 0 0 .15rem;
            color: var(--sub);
            font-size: .83rem;
        }
        body.dark-mode .avatar-info p { color: var(--dark-sub); }
        .avatar-upload { display: flex; gap: .6rem; margin-top: .9rem; flex-wrap: wrap; }

        /* ── 表单 ────────────────────────────────────────────────── */
        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block;
            margin-bottom: .45rem;
            font-size: .82rem;
            font-weight: 700;
            color: var(--sub);
            letter-spacing: .01em;
        }
        body.dark-mode .form-group label { color: var(--dark-sub); }
        .form-group input {
            width: 100%;
            padding: .6rem .9rem;
            border: 1.5px solid rgba(155,140,255,.3);
            border-radius: var(--radius-md);
            background: rgba(248,246,255,.7);
            font-family: inherit;
            font-size: .92rem;
            color: var(--text);
            transition: border-color .2s, box-shadow .2s, background .2s;
            box-sizing: border-box;
            outline: none;
            /* 防止输入框撑出父容器 */
            max-width: 100%;
        }
        .form-group input::placeholder { color: rgba(96,96,128,.45); }
        .form-group input:hover {
            border-color: rgba(108,93,251,.45);
            background: rgba(248,246,255,.95);
        }
        .form-group input:focus {
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 3.5px rgba(108,93,251,.14), 0 2px 8px rgba(108,93,251,.08);
        }
        body.dark-mode .form-group input {
            background: rgba(42,42,66,.6);
            border-color: rgba(176,160,255,.2);
            color: var(--dark-text);
        }
        body.dark-mode .form-group input::placeholder { color: rgba(176,176,200,.35); }
        body.dark-mode .form-group input:hover {
            border-color: rgba(176,160,255,.4);
            background: rgba(42,42,66,.8);
        }
        body.dark-mode .form-group input:focus {
            border-color: var(--dark-vio);
            background: rgba(42,42,66,.95);
            box-shadow: 0 0 0 3.5px rgba(176,160,255,.12);
        }

        .form-actions { display: flex; align-items: center; gap: 1rem; margin-top: 1.5rem; flex-wrap: wrap; }

        /* 退出登录 */
        .btn-logout {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            padding: .55rem 1.1rem;
            border-radius: var(--radius-md);
            font-weight: 700;
            font-size: .88rem;
            text-decoration: none;
            cursor: pointer;
            color: #c0284a;
            background: rgba(235,77,105,.07);
            border: 1px solid rgba(235,77,105,.22);
            transition: background .15s, border-color .15s, transform .12s, box-shadow .15s;
        }
        .btn-logout:hover {
            background: rgba(235,77,105,.13);
            border-color: rgba(235,77,105,.38);
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(235,77,105,.15);
            color: #c0284a;
            text-decoration: none;
        }
        body.dark-mode .btn-logout { color: #ff7ca3; background: rgba(235,77,105,.08); border-color: rgba(235,77,105,.2); }
        body.dark-mode .btn-logout:hover { background: rgba(235,77,105,.14); border-color: rgba(235,77,105,.32); }

        /* ── 标签页动画 ───────────────────────────────────────────── */
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: uc-fadeIn .3s ease both; }
        @keyframes uc-fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── 提示消息 ─────────────────────────────────────────────── */
        .message {
            display: flex;
            align-items: flex-start;
            gap: .55rem;
            padding: .72rem 1rem;
            border-radius: var(--radius-md);
            font-size: .85rem;
            font-weight: 600;
            line-height: 1.5;
            margin-bottom: 1.1rem;
            border: 1px solid transparent;
            animation: uc-fadeIn .3s ease both;
        }
        .message.success { background: rgba(39,174,96,.09); color: #157a3a; border-color: rgba(39,174,96,.22); }
        .message.error   { background: rgba(235,77,105,.08); color: #c0284a; border-color: rgba(235,77,105,.22); }
        body.dark-mode .message.success { background: rgba(39,174,96,.08); color: #6fcf97; border-color: rgba(39,174,96,.18); }
        body.dark-mode .message.error   { background: rgba(235,77,105,.07); color: #ff7ca3; border-color: rgba(235,77,105,.2); }

        /* ── 空状态 ──────────────────────────────────────────────── */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            color: var(--sub);
        }
        .empty-state svg {
            width: 56px; height: 56px;
            margin-bottom: 1rem;
            opacity: .3;
            stroke: var(--primary);
        }
        .empty-state p { font-size: .9rem; margin: 0; }
        body.dark-mode .empty-state { color: var(--dark-sub); }

        /* 进度条着色 */
        #progressBar { background: var(--primary) !important; }
        #themeToggle { display: none; }
    </style>
</head>
<body>
    <div class="sparkles" id="sparkles"></div>
    <div class="user-center-wrap">
        <div class="user-center-card">
            <div class="user-center-header">
                <div class="header-left">
                    <button id="themeToggleHeader" class="theme-toggle-header">🌙</button>
                    <a href="../index.php" class="back-home">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="m12 19-7-7 7-7"/>
                            <path d="M19 12H5"/>
                        </svg>
                        返回首页
                    </a>
                </div>
                <h1 class="user-center-title">用户中心</h1>
            </div>

            <?php if ($message): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="user-center-content">