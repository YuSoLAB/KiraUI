<?php
/**
 * AliSms.php — 阿里云号码认证服务（dypnsapi-20170525 v2.x）
 * 所有 namespace 均通过服务器源码文件直接确认，不再猜测。
 *
 * Config         → Darabonba\OpenApi\Models\Config        (openapi-core)
 * RuntimeOptions → AlibabaCloud\Dara\Models\RuntimeOptions (darabonba/dara)
 */

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__FILE__) . '/..');
}

(function () {
    $paths = [
        ROOT_DIR . '/vendor/autoload.php',
        dirname(ROOT_DIR) . '/vendor/autoload.php',
    ];
    foreach ($paths as $p) {
        if (file_exists($p)) { require_once $p; return; }
    }
    throw new \RuntimeException('Composer autoload 未找到');
})();

use AlibabaCloud\SDK\Dypnsapi\V20170525\Dypnsapi;
use AlibabaCloud\SDK\Dypnsapi\V20170525\Models\SendSmsVerifyCodeRequest;
use AlibabaCloud\SDK\Dypnsapi\V20170525\Models\CheckSmsVerifyCodeRequest;
use Darabonba\OpenApi\Models\Config;                 // ← 源码确认
use AlibabaCloud\Dara\Models\RuntimeOptions;         // ← 源码确认

class AliSms
{
    private string $accessKeyId;
    private string $accessKeySecret;
    private string $signName;
    private string $templateCode;
    private string $endpoint = 'dypnsapi.aliyuncs.com';

    public function __construct(
        string $accessKeyId,
        string $accessKeySecret,
        string $signName,
        string $templateCode
    ) {
        $this->accessKeyId     = $accessKeyId;
        $this->accessKeySecret = $accessKeySecret;
        $this->signName        = $signName;
        $this->templateCode    = $templateCode;
    }

    public static function fromConfig(): self
    {
        if (!class_exists('Config')) {
            throw new \RuntimeException('Config 类未加载');
        }
        $cfg = \Config::getInstance();
        $id  = $cfg->get('aliyun_access_key_id',    '');
        $sec = $cfg->get('aliyun_access_key_secret', '');
        $sgn = $cfg->get('aliyun_sms_sign_name',     '');
        $tpl = $cfg->get('aliyun_sms_template_code', '100001');
        if (empty($id) || empty($sec)) {
            throw new \RuntimeException('阿里云 AccessKey 尚未配置');
        }
        return new self($id, $sec, $sgn, $tpl);
    }

    private function createClient(): Dypnsapi
    {
        $config = new Config([
            'accessKeyId'     => $this->accessKeyId,
            'accessKeySecret' => $this->accessKeySecret,
        ]);
        $config->endpoint = $this->endpoint;
        return new Dypnsapi($config);
    }

    /** @return array{ok:bool, msg:string, verify_code:string, biz_id:string} */
    public function sendCode(string $phone, int $validMin = 5): array
    {
        try {
            $req = new SendSmsVerifyCodeRequest([
                'countryCode'      => '86',
                'phoneNumber'      => $phone,
                'signName'         => $this->signName,
                'templateCode'     => $this->templateCode,
                'templateParam'    => json_encode(['code' => '##code##', 'min' => (string)$validMin]),
                'codeLength'       => 6,
                'validTime'        => $validMin * 60,
                'duplicatePolicy'  => 1,
                'codeType'         => 1,
                'returnVerifyCode' => true,
            ]);

            $resp = $this->createClient()->sendSmsVerifyCodeWithOptions($req, new RuntimeOptions([]));
            $body = $resp->body;

            if ($body->code === 'OK') {
                return [
                    'ok'          => true,
                    'msg'         => '短信已发送',
                    'verify_code' => $body->model->verifyCode ?? '',
                    'biz_id'      => $body->model->bizId     ?? '',
                ];
            }
            return ['ok' => false, 'msg' => $body->message ?? ('发送失败：' . $body->code), 'verify_code' => '', 'biz_id' => ''];

        } catch (\Throwable $e) {
            return ['ok' => false, 'msg' => '短信服务异常：' . $e->getMessage(), 'verify_code' => '', 'biz_id' => ''];
        }
    }

    /** @return array{ok:bool, msg:string} */
    public function checkCodeRemote(string $phone, string $code): array
    {
        try {
            $req = new CheckSmsVerifyCodeRequest([
                'countryCode' => '86',
                'phoneNumber' => $phone,
                'verifyCode'  => $code,
            ]);
            $resp = $this->createClient()->checkSmsVerifyCodeWithOptions($req, new RuntimeOptions([]));
            $body = $resp->body;
            if ($body->code === 'OK' && ($body->model->verifyResult ?? '') === 'PASS') {
                return ['ok' => true, 'msg' => '验证通过'];
            }
            return ['ok' => false, 'msg' => '验证码错误或已过期'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'msg' => '验证服务异常：' . $e->getMessage()];
        }
    }
}