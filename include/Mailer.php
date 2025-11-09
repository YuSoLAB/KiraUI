<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
class Mailer {
    private $config;
    private $mail;
    public function __construct() {
        $this->config = Config::getInstance();
        $this->mail = new PHPMailer(true);
        $this->init();
    }
    private function init() {
        if (!$this->isEnabled()) {
            return;
        }
        try {
            $this->mail->isSMTP();
            $this->mail->Host = $this->config->get('smtp_host');
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $this->config->get('smtp_username');
            $this->mail->Password = $this->config->get('smtp_password');
            $encryption = $this->config->get('smtp_encryption');
            if ($encryption) {
                $this->mail->SMTPSecure = $encryption;
            }            
            $this->mail->Port = $this->config->get('smtp_port', 587);
            $fromEmail = $this->config->get('smtp_from_email');
            $fromName = $this->config->get('smtp_from_name', '系统通知');
            if ($fromEmail) {
                $this->mail->setFrom($fromEmail, $fromName);
            }
            $this->mail->isHTML(true);
            $this->mail->CharSet = 'UTF-8';
        } catch (Exception $e) {
            error_log("邮件配置初始化失败: " . $e->getMessage());
        }
    }
    public function isEnabled() {
        return $this->config->get('smtp_enabled', '0') === '1';
    }
    public function send($to, $subject, $body, $altBody = '') {
        if (!$this->isEnabled()) {
            throw new Exception("SMTP功能未启用");
        }
        try {
            $this->mail->addAddress($to);
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;
            $this->mail->AltBody = $altBody ?: strip_tags($body);
            return $this->mail->send();
        } catch (Exception $e) {
            error_log("邮件发送失败: " . $e->getMessage());
            throw $e;
        }
    }
}