<?php
?>
<div class="login-form">
    <h2>管理员登录</h2>
    <?php if ($loginError): ?>
        <div class="message error"><?php echo $loginError; ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label for="email">管理员邮箱</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">密码</label>
            <input type="password" id="password" name="password" required>
        </div>
        <p class="form-hint">忘记密码？请通过数据库管理工具（如phpMyAdmin）修改users表中的password_hash字段</p>
        <button type="submit" name="login_submit" class="btn btn-primary">登录</button>
    </form>
</div>