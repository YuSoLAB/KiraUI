<?php
/**
 * Mailer.php — 基于原生 Socket 的 SMTP 邮件发送类
 *
 * 无需任何第三方依赖，支持 TLS / SSL / 无加密三种方式。
 * 提供以下业务邮件方法：
 *   - sendCommentNotifyToAdmin()  — 通知管理员有新评论待审核
 *   - sendReplyNotifyToUser()     — 通知用户的评论被回复
 *   - sendTestMail()              — 发送测试邮件（后台 SMTP 调试用）
 *
 * 使用方式：
 *   $mailer = new Mailer();
 *   if ($mailer->isEnabled()) {
 *       $mailer->sendCommentNotifyToAdmin('admin@example.com', [...]);
 *   }
 */
class Mailer
{
    private array  $cfg;
    private bool   $enabled;
    private int    $timeout = 15;

    public function __construct()
    {
        if (!class_exists('Config')) {
            require_once __DIR__ . '/Config.php';
        }
        $config = Config::getInstance();

        $this->enabled = $config->get('smtp_enabled', '0') === '1';
        $this->cfg = [
            'host'       => $config->get('smtp_host',       ''),
            'port'       => (int)$config->get('smtp_port',  587),
            'username'   => $config->get('smtp_username',   ''),
            'password'   => $config->get('smtp_password',   ''),
            'from_email' => $config->get('smtp_from_email', ''),
            'from_name'  => $config->get('smtp_from_name',  ''),
            'encryption' => $config->get('smtp_encryption', 'tls'),
        ];
    }

    /** SMTP 是否已配置并启用 */
    public function isEnabled(): bool
    {
        return $this->enabled
            && $this->cfg['host'] !== ''
            && $this->cfg['username'] !== ''
            && $this->cfg['from_email'] !== '';
    }

    // ─────────────────────────────────────────────────────────────────────
    // 业务邮件方法
    // ─────────────────────────────────────────────────────────────────────

    /**
     * 向管理员发送「有新评论待审核」通知邮件。
     *
     * @param string $toEmail       管理员邮箱
     * @param array  $data {
     *   commenter_name   string  评论者昵称
     *   article_title    string  文章标题
     *   article_url      string  文章链接
     *   comment_content  string  评论正文（纯文本）
     *   admin_url        string  后台审核链接
     * }
     */
    public function sendCommentNotifyToAdmin(string $toEmail, array $data): bool
    {
        $siteName = $this->cfg['from_name'] ?: '博客系统';
        $subject  = "【{$siteName}】新评论待审核 — {$data['article_title']}";

        $html = $this->renderAdminCommentMail($data, $siteName);

        return $this->send($toEmail, $subject, $html);
    }

    /**
     * 向用户发送「你的评论被回复了」通知邮件。
     *
     * @param string $toEmail  收件人邮箱
     * @param array  $data {
     *   user_name         string  被通知用户的昵称
     *   replier_name      string  回复者昵称
     *   article_title     string  文章标题
     *   original_content  string  被回复的原评论（纯文本，截断）
     *   reply_content     string  回复内容（纯文本）
     *   article_url       string  含锚点的文章链接（#comment_xxx）
     * }
     */
    public function sendReplyNotifyToUser(string $toEmail, array $data): bool
    {
        $siteName = $this->cfg['from_name'] ?: '博客系统';
        $subject  = "【{$siteName}】{$data['replier_name']} 回复了你的评论";

        $html = $this->renderUserReplyMail($data, $siteName);

        return $this->send($toEmail, $subject, $html);
    }

    /**
     * 发送测试邮件，供后台 SMTP 调试使用。
     *
     * @param string $toEmail  测试收件人邮箱
     */
    public function sendTestMail(string $toEmail): array
    {
        $siteName = $this->cfg['from_name'] ?: '博客系统';
        $subject  = "【{$siteName}】SMTP 测试邮件";
        $html     = $this->wrapLayout(
            "SMTP 配置测试",
            $siteName,
            '<p style="font-size:16px;color:#333;">🎉 恭喜！SMTP 邮件发送配置正确，你已成功收到来自 <strong>'
            . htmlspecialchars($siteName)
            . '</strong> 的测试邮件。</p>'
            . '<p style="color:#666;font-size:14px;">若非本人操作，请忽略此邮件。</p>',
            null
        );

        $ok = $this->send($toEmail, $subject, $html);
        return ['ok' => $ok, 'msg' => $ok ? '测试邮件已发送，请检查收件箱' : '发送失败，请检查 SMTP 配置'];
    }

    // ─────────────────────────────────────────────────────────────────────
    // 邮件模板渲染
    // ─────────────────────────────────────────────────────────────────────

    private function renderAdminCommentMail(array $d, string $siteName): string
    {
        $commenter = htmlspecialchars($d['commenter_name']  ?? '匿名');
        $artTitle  = htmlspecialchars($d['article_title']   ?? '');
        $artUrl    = htmlspecialchars($d['article_url']     ?? '#');
        $adminUrl  = htmlspecialchars($d['admin_url']       ?? '#');
        $content   = nl2br(htmlspecialchars(mb_substr($d['comment_content'] ?? '', 0, 500)));

        $body = <<<HTML
<p style="font-size:15px;color:#333;margin:0 0 16px;">
    嗨，管理员！<strong style="color:#5b4ef8;">{$commenter}</strong> 在文章
    <a href="{$artUrl}" style="color:#5b4ef8;text-decoration:none;">《{$artTitle}》</a>
    下提交了一条新评论，请前往后台审核。
</p>

<div style="background:#f7f6ff;border-left:4px solid #5b4ef8;border-radius:4px;
            padding:14px 18px;margin:0 0 20px;font-size:14px;color:#444;line-height:1.7;">
    {$content}
</div>

<div style="text-align:center;margin-top:8px;">
    <a href="{$adminUrl}"
       style="display:inline-block;background:#5b4ef8;color:#fff;font-size:14px;
              font-weight:600;padding:12px 32px;border-radius:8px;text-decoration:none;
              letter-spacing:.5px;">
        🔍 前往审核评论
    </a>
</div>
HTML;

        return $this->wrapLayout('新评论通知', $siteName, $body, '如非本人操作，请忽略此邮件。');
    }

    private function renderUserReplyMail(array $d, string $siteName): string
    {
        $userName    = htmlspecialchars($d['user_name']        ?? '用户');
        $replierName = htmlspecialchars($d['replier_name']     ?? '有人');
        $artTitle    = htmlspecialchars($d['article_title']    ?? '');
        $artUrl      = htmlspecialchars($d['article_url']      ?? '#');
        $original    = nl2br(htmlspecialchars(mb_substr($d['original_content'] ?? '', 0, 200)));
        $replyText   = nl2br(htmlspecialchars(mb_substr($d['reply_content']    ?? '', 0, 500)));

        $body = <<<HTML
<p style="font-size:15px;color:#333;margin:0 0 16px;">
    嗨，<strong>{$userName}</strong>！
    <strong style="color:#5b4ef8;">{$replierName}</strong>
    回复了你在文章
    <a href="{$artUrl}" style="color:#5b4ef8;text-decoration:none;">《{$artTitle}》</a>
    下的评论。
</p>

<p style="font-size:13px;color:#888;margin:0 0 6px;">你的原评论：</p>
<div style="background:#f3f4f6;border-radius:6px;padding:12px 16px;
            font-size:13px;color:#666;line-height:1.65;margin:0 0 16px;
            border-left:3px solid #ccc;">
    {$original}
</div>

<p style="font-size:13px;color:#888;margin:0 0 6px;">{$replierName} 的回复：</p>
<div style="background:#f7f6ff;border-left:4px solid #5b4ef8;border-radius:4px;
            padding:14px 18px;font-size:14px;color:#333;line-height:1.7;margin:0 0 24px;">
    {$replyText}
</div>

<div style="text-align:center;">
    <a href="{$artUrl}"
       style="display:inline-block;background:#5b4ef8;color:#fff;font-size:14px;
              font-weight:600;padding:12px 32px;border-radius:8px;text-decoration:none;
              letter-spacing:.5px;">
        💬 查看完整对话
    </a>
</div>
HTML;

        return $this->wrapLayout('有人回复了你的评论', $siteName, $body,
            '你收到此邮件是因为你的评论被回复。如需关闭邮件通知，请登录后在「用户中心 → 消息设置」中关闭。');
    }

    /**
     * 通用邮件布局包装器 — 简洁的单列响应式布局
     */
    private function wrapLayout(
        string  $title,
        string  $siteName,
        string  $bodyHtml,
        ?string $footer
    ): string {
        $year       = date('Y');
        $siteEsc    = htmlspecialchars($siteName);
        $titleEsc   = htmlspecialchars($title);
        $footerHtml = $footer !== null
            ? '<p style="font-size:12px;color:#aaa;margin:16px 0 0;text-align:center;">' . htmlspecialchars($footer) . '</p>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$titleEsc}</title>
</head>
<body style="margin:0;padding:0;background:#f0f0f5;font-family:'PingFang SC','Hiragino Sans GB','Microsoft YaHei',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px;">
  <tr><td align="center">
    <table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;">

      <!-- Header -->
      <tr>
        <td style="background:#5b4ef8;border-radius:12px 12px 0 0;padding:28px 32px;text-align:center;">
          <p style="margin:0;font-size:22px;font-weight:700;color:#fff;letter-spacing:.5px;">{$siteEsc}</p>
          <p style="margin:6px 0 0;font-size:13px;color:rgba(255,255,255,.75);">{$titleEsc}</p>
        </td>
      </tr>

      <!-- Body -->
      <tr>
        <td style="background:#fff;padding:32px;border-left:1px solid #e8e8f0;border-right:1px solid #e8e8f0;">
          {$bodyHtml}
        </td>
      </tr>

      <!-- Footer -->
      <tr>
        <td style="background:#fafafa;border:1px solid #e8e8f0;border-top:none;
                   border-radius:0 0 12px 12px;padding:18px 32px;text-align:center;">
          <p style="margin:0;font-size:12px;color:#bbb;">
            © {$year} {$siteEsc} · 此邮件由系统自动发送，请勿直接回复
          </p>
          {$footerHtml}
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
    }

    // ─────────────────────────────────────────────────────────────────────
    // 底层 SMTP 发送
    // ─────────────────────────────────────────────────────────────────────

    /**
     * 发送 HTML 邮件（自动附带纯文本备用版本）。
     *
     * @param string $toEmail   收件人邮箱
     * @param string $subject   邮件主题
     * @param string $htmlBody  HTML 正文
     * @param string $toName    收件人显示名（可选）
     * @return bool
     */
    public function send(
        string $toEmail,
        string $subject,
        string $htmlBody,
        string $toName = ''
    ): bool {
        if (!$this->isEnabled()) {
            error_log('[Mailer] SMTP 未启用或配置不完整，跳过发送');
            return false;
        }

        try {
            $result = $this->smtpSend([
                'host'      => $this->cfg['host'],
                'port'      => $this->cfg['port'],
                'enc'       => $this->cfg['encryption'],
                'user'      => $this->cfg['username'],
                'pass'      => $this->cfg['password'],
                'from'      => $this->cfg['from_email'],
                'from_name' => $this->cfg['from_name'],
                'to'        => $toEmail,
                'to_name'   => $toName,
                'subject'   => $subject,
                'html'      => $htmlBody,
            ]);

            if (!$result['ok']) {
                error_log('[Mailer] 发送失败 → ' . ($result['msg'] ?? '未知错误'));
            }
            return $result['ok'];
        } catch (Throwable $e) {
            error_log('[Mailer] 异常：' . $e->getMessage());
            return false;
        }
    }

    /**
     * 原生 Socket SMTP 发送核心（支持 TLS / SSL / 明文）。
     * 与 admin_ajax.php 中的 _enSmtpSend() 保持一致的实现策略。
     */
    private function smtpSend(array $p): array
    {
        $timeout = $this->timeout;
        $host    = $p['host'];
        $port    = (int)$p['port'];
        $enc     = $p['enc'] ?? 'tls';

        // 建立 TCP 连接
        $errno = 0; $errstr = '';
        if ($enc === 'ssl') {
            $sock = @fsockopen("ssl://{$host}", $port, $errno, $errstr, $timeout);
        } else {
            $sock = @fsockopen($host, $port, $errno, $errstr, $timeout);
        }
        if (!$sock) {
            return ['ok' => false, 'msg' => "连接 SMTP 服务器失败（{$errno}）：{$errstr}"];
        }
        stream_set_timeout($sock, $timeout);

        $recv = function () use ($sock): string {
            $resp = '';
            while (!feof($sock)) {
                $line  = fgets($sock, 1024);
                $resp .= $line;
                // SMTP 响应最后一行第 4 个字符是空格
                if (isset($line[3]) && $line[3] === ' ') break;
            }
            return $resp;
        };
        $send = function (string $cmd) use ($sock): void {
            fwrite($sock, $cmd . "\r\n");
        };

        $recv(); // 220 greeting
        $send("EHLO {$host}");
        $ehlo = $recv();

        // STARTTLS 升级
        if ($enc === 'tls') {
            if (strpos($ehlo, 'STARTTLS') === false) {
                fclose($sock);
                return ['ok' => false, 'msg' => 'SMTP 服务器不支持 STARTTLS'];
            }
            $send('STARTTLS');
            $recv();
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($sock);
                return ['ok' => false, 'msg' => 'STARTTLS 握手失败，请检查证书配置'];
            }
            $send("EHLO {$host}");
            $recv();
        }

        // AUTH LOGIN
        $send('AUTH LOGIN');
        $recv();
        $send(base64_encode($p['user']));
        $recv();
        $send(base64_encode($p['pass']));
        $authResp = $recv();
        if (strpos($authResp, '235') === false) {
            fclose($sock);
            return ['ok' => false, 'msg' => 'SMTP 认证失败：' . trim($authResp)];
        }

        // 构建邮件正文
        $boundary = 'MailerBnd_' . bin2hex(random_bytes(8));
        $fromEnc  = '=?UTF-8?B?' . base64_encode($p['from_name']) . '?=';
        $toEnc    = $p['to_name']
            ? '=?UTF-8?B?' . base64_encode($p['to_name']) . '?= <' . $p['to'] . '>'
            : $p['to'];
        $subjEnc  = '=?UTF-8?B?' . base64_encode($p['subject']) . '?=';
        $plain    = strip_tags($p['html']);

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $headers .= "From: {$fromEnc} <{$p['from']}>\r\n";
        $headers .= "To: {$toEnc}\r\n";
        $headers .= "Subject: {$subjEnc}\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "X-Mailer: PHPMailer-Native/1.0\r\n";

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($plain)) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($p['html'])) . "\r\n";
        $body .= "--{$boundary}--";

        // 独行的点号需转义（RFC 5321）
        $message = preg_replace('/^\.$/m', '..', $headers . "\r\n" . $body);

        $send("MAIL FROM:<{$p['from']}>");
        $recv();
        $send("RCPT TO:<{$p['to']}>");
        $recv();
        $send('DATA');
        $recv();
        fwrite($sock, $message . "\r\n.\r\n");
        $dataResp = $recv();
        $send('QUIT');
        fclose($sock);

        if (strpos($dataResp, '250') !== false) {
            return ['ok' => true];
        }
        return ['ok' => false, 'msg' => 'SMTP DATA 响应异常：' . trim($dataResp)];
    }
}