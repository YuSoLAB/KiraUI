<?php session_start(); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>用户注册</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2>创建账号</h2>
                <p class="auth-subtitle">注册后即可享受全部功能</p>
            </div>
            <?php if (isset($_SESSION['register_error'])): ?>
                <div class="message error"><?php echo $_SESSION['register_error']; unset($_SESSION['register_error']); ?></div>
            <?php endif; ?>
            <form action="handle_register.php" method="post" class="auth-form">
                <div class="form-group">
                    <label for="username">用户名 <span class="required">*</span></label>
                    <input type="text" id="username" name="username" 
                           pattern="^[a-zA-Z0-9_]{1,20}$" 
                           title="用户名只能包含数字、字母和下划线，长度不超过20位" 
                           required>
                    <small>只能包含数字、字母和下划线，长度不超过20位</small>
                </div>
                <div class="form-group">
                    <label for="nickname">昵称 <span class="required">*</span></label>
                    <input type="text" id="nickname" name="nickname" 
                           maxlength="50" 
                           title="昵称长度不超过50位" 
                           required>
                </div>
                <div class="form-group">
                    <label for="email">邮箱 <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required>
                    <small>用于账号验证和密码找回</small>
                </div>
                <div class="form-group">
                    <label for="password">密码 <span class="required">*</span></label>
                    <input type="password" id="password" name="password" 
                           minlength="6" 
                           title="密码长度至少6位" 
                           required>
                    <small>密码长度至少6位</small>
                </div>
                <div class="form-group">
                    <div style="display: flex; gap: 10px;">
                        <div style="flex: 1;">
                            <label for="verify_code">邮箱验证码 <span class="required">*</span></label>
                            <input type="text" id="verify_code" name="verify_code" 
                                maxlength="6" 
                                title="请输入6位验证码" 
                                required>
                            <small id="verifyCodeHint" style="display: none;">验证码已发送至您的邮箱，12小时内有效</small>
                        </div>
                        <div style="flex: 0 0 auto; align-self: flex-end;">
                            <button type="button" id="send_code" class="btn-small btn-register">发送验证码</button>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn-small btn-register full-width">注册</button>
                <p class="form-hint">已有账号？<a href="login.php" class="link-primary">立即登录</a></p>
                <p class="auth-terms">
                    点击注册即表示您同意我们的<a href="#" class="link">服务条款</a>和<a href="#" class="link">隐私政策</a>
                </p>
            </form>
        </div>
    </div>
    <button id="themeToggle" class="theme-toggle auth-theme-toggle">🌙</button>
    <script src="theme-toggle.js"></script>
</body>
</html>
<script>
document.getElementById('send_code').addEventListener('click', function() {
    const email = document.getElementById('email').value;
    const sendBtn = this;
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        alert('请先输入有效的邮箱地址');
        return;
    }
    sendBtn.disabled = true;
    sendBtn.textContent = '发送中...';
    fetch('send_verify_code.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'email=' + encodeURIComponent(email)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('验证码已发送，请查收');
            document.getElementById('verifyCodeHint').style.display = 'block';
            let countdown = 60;
            sendBtn.textContent = `重新发送(${countdown})`;
            const timer = setInterval(() => {
                countdown--;
                sendBtn.textContent = `重新发送(${countdown})`;
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