<?php session_start(); ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户注册</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .input-wrapper { position: relative; margin-bottom: 5px; }
        .input-icon {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            width: 20px; height: 20px; fill: #9b8cff; transition: 0.3s; pointer-events: none;
        }
        .auth-form input:not([type="checkbox"]) { padding-left: 44px !important; }
        .auth-form input:focus + .input-icon { fill: #ff4db1; }
        body.dark-mode .input-icon { fill: #b0a0ff; }
        body.dark-mode .auth-form input:focus + .input-icon { fill: #ff66b8; }
        
        /* 验证码区域样式 */
        .verify-group { 
            display: flex; 
            gap: 10px; 
            align-items: stretch; 
        }
        .verify-group .input-wrapper { 
            flex: 1 1 0; 
            margin-bottom: 0; 
            min-width: 0; 
        }
        .verify-btn { 
            white-space: nowrap; 
            padding: 0 15px; 
            height: 50px; 
            margin: 0; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            font-size: 14px;
            min-width: 120px;
            max-width: 140px;
        }
        
        /* 移动端适配优化 */
        @media (max-width: 768px) {
            .verify-btn {
                padding: 0 12px;
                min-width: 110px;
                max-width: 130px;
                font-size: 13px;
            }
        }
        
        @media (max-width: 600px) {
            .verify-group {
                flex-direction: row;
                flex-wrap: wrap;
            }
            .verify-group .input-wrapper {
                flex: 2 1 0;
                min-width: 150px;
            }
            .verify-btn {
                flex: 1 1 0;
                min-width: auto;
                max-width: none;
                height: 44px;
            }
        }
        
        @media (max-width: 480px) {
            .verify-group {
                flex-direction: column;
                gap: 8px;
            }
            .verify-group .input-wrapper {
                width: 100%;
                min-width: 100%;
            }
            .verify-btn {
                width: 100%;
                height: 44px;
                max-width: 100%;
                padding: 0 20px;
            }
        }
        
        @media (max-width: 360px) {
            .verify-btn {
                font-size: 12px;
                padding: 0 10px;
            }
        }
        
        small { display: block; margin-top: 4px; opacity: 0.8; }
        
        /* 验证码输入框优化 */
        #verify_code {
            font-family: monospace;
            letter-spacing: 2px;
            font-size: 16px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2>加入我们</h2>
                <p class="auth-subtitle">只需几步，开启您的旅程</p>
            </div>

            <?php if (isset($_SESSION['register_error'])): ?>
                <div class="message error">⚠️ <?php echo $_SESSION['register_error']; unset($_SESSION['register_error']); ?></div>
            <?php endif; ?>

            <form action="handle_register.php" method="post" class="auth-form">
                <div class="form-group">
                    <label for="username">用户名 <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="text" id="username" name="username" pattern="^[a-zA-Z0-9_]{1,20}$" required placeholder="设置唯一用户ID">
                        <svg class="input-icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
                    </div>
                    <small>字母、数字或下划线，20位以内</small>
                </div>

                <div class="form-group">
                    <label for="nickname">昵称 <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="text" id="nickname" name="nickname" maxlength="50" required placeholder="大家怎么称呼你">
                        <svg class="input-icon" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">邮箱 <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="email" id="email" name="email" required placeholder="example@mail.com">
                        <svg class="input-icon" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                    </div>
                </div>

                <div class="form-group">
                    <label for="verify_code">邮箱验证码 <span class="required">*</span></label>
                    <div class="verify-group">
                        <div class="input-wrapper">
                            <input type="text" id="verify_code" name="verify_code" maxlength="6" required placeholder="6位验证码">
                            <svg class="input-icon" viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                        </div>
                        <button type="button" id="send_code" class="btn btn-secondary verify-btn">发送验证码</button>
                    </div>
                    <small id="verifyCodeHint" style="display: none; color: #4caf50;">✨ 验证码已发送，10分钟内有效</small>
                </div>

                <div class="form-group">
                    <label for="password">密码 <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" minlength="6" required placeholder="至少6位字符">
                        <svg class="input-icon" viewBox="0 0 24 24"><path d="M2 17h20v2H2zm1.15-4.05L4 11.47l.85 1.48 1.3-.75-.85-1.48H7v-1.5H5.3l.85-1.47-1.3-.75-.85 1.48L4 7.03l-1.3.75.85 1.47L2.7 10.73l1.3.75.85-1.48zM12 6c-3.87 0-7 3.13-7 7s3.13 7 7 7 7-3.13 7-7-3.13-7-7-7zm0 12c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm6 1h2v2h-2z"/></svg>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary full-width" style="margin-top: 10px;">立即注册</button>
                <p class="form-hint">已有账号？<a href="login.php" class="link-primary">直接登录</a></p>
                <p class="auth-terms">
                    注册即代表同意 <a href="#" class="link">服务条款</a> 与 <a href="#" class="link">隐私政策</a>
                </p>
            </form>
        </div>
    </div>
    <button id="themeToggle" class="theme-toggle auth-theme-toggle">🌙</button>
    <script src="theme-toggle.js"></script>
    <script>
    document.getElementById('send_code').addEventListener('click', function() {
        const email = document.getElementById('email').value;
        const sendBtn = this;
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            alert('请先输入有效的邮箱地址');
            return;
        }
        sendBtn.disabled = true;
        sendBtn.textContent = '少女祈祷中...';  
        fetch('send_verify_code.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'email=' + encodeURIComponent(email)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('verifyCodeHint').style.display = 'block';
                let countdown = 60;
                sendBtn.textContent = `${countdown}秒后重试`;  
                const timer = setInterval(() => {
                    countdown--;
                    sendBtn.textContent = `${countdown}秒后重试`;
                    if (countdown <= 0) {
                        clearInterval(timer);
                        sendBtn.disabled = false;
                        sendBtn.textContent = '发送验证码';
                    }
                }, 1000);
            } else {
                alert('发送失败: ' + data.message);
                sendBtn.disabled = false;
                sendBtn.textContent = '发送验证码';
            }
        })
        .catch(error => {
            console.error('错误:', error);
            alert('发送失败，请重试');
            sendBtn.disabled = false;
            sendBtn.textContent = '发送验证码';
        });
    });
    </script>
</body>
</html>