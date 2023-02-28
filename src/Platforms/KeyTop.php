<?php

namespace On3\DAP\Platforms;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use On3\DAP\Exceptions\InvalidArgumentException;
use On3\DAP\Traits\DAPHashTrait;

class KeyTop extends Platform
{

    use DAPHashTrait;

    protected $appId;
    protected $appSecret;
    protected $parkId;

    const API_VERSION = '1.0.0';

    const RESP_CODES = [
        0 => '成功',
        1 => '失败',
        500 => '系统异常',
        1001 => '无效的AccessKey',
        1101 => '认证失败',
        1102 => '车场未授权',
        1201 => '参数不完整',
        1202 => '参数不符合规定值',
        2001 => '车场处理异常',
        2101 => '车辆进场失败',
        2102 => '进场序列号不匹配',
        -1 => '访问过于频繁，请求被拒绝',
        -2 => '访问受限IP黑白名单，请求被拒绝',
        -3 => '未找到路由配置，请求无法处理',
        -4 => '车场未授权，请求被拒绝',
        -5 => '业务接口未授权，请求被拒绝'
    ];

    public function __construct(array $config, bool $dev = false, bool $loadingToken = true, bool $autoLogging = true)
    {
        $this->appId = Arr::get($config, 'appId');
        $this->appSecret = Arr::get($config, 'appSecret');
        $this->parkId = Arr::get($config, 'parkId');

        if ($gateway = Arr::get($config, 'gateway')) {
            $this->gateway = $gateway;
        } else {
            $this->gateway = $dev ? Gateways::KEY_TOP_DEV : Gateways::KEY_TOP;
        }

        $autoLogging && $this->injectLogObj();
    }

    protected function configValidator()
    {
        if (!$this->appId) {
            throw new InvalidArgumentException('appId不可为空');
        }

        if (!$this->appSecret) {
            throw new InvalidArgumentException('appSecret不可为空');
        }
    }

    protected function getReqID(): string
    {
        return intval(microtime(true) * 1000) . mt_rand(1000, 9999);
    }

    protected function generateSignature(): self
    {
        $queryBody = $this->queryBody;
        ksort($queryBody);
        unset($queryBody['appId'], $queryBody['appSecret']);
        $rawQueryStr = $this->join2Str($queryBody);
        $rawQueryStr .= '&' . $this->appSecret;
        $this->queryBody['key'] = md5($rawQueryStr);
        return $this;
    }

    public function fire()
    {
        $httpMethod = $this->httpMethod;
        $httpClient = new Client(['base_uri' => $this->gateway, 'timeout' => $this->timeout, 'verify' => false]);
    }

    protected function injectData(array &$queryData)
    {
        if (!Arr::get($queryData, 'ts')) {
            $queryData['ts'] = intval(microtime(true) * 1000);
        }

        if (!Arr::get($queryData, 'appId')) {
            $queryData['appId'] = $this->appId;
        }

        if (!Arr::get($queryData, 'reqId')) {
            $queryData['reqId'] = $this->getReqID();
        }

        $this->queryBody = $queryData;
    }

    protected function formatResp(&$response)
    {
        // TODO: Implement formatResp() method.
    }

    public function cacheKey(): string
    {
        return '';
    }

    public function setParkID(string $parkId): self
    {
        $this->parkId = $parkId;
        return $this;
    }
}
