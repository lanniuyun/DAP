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

    public function __construct(array $config, bool $dev = false, bool $loadingToken = true, bool $autoLogging = true)
    {
        $this->appId = Arr::get($config, 'appId');
        $this->appSecret = Arr::get($config, 'appSecret');
        $this->parkId = Arr::get($config, 'parkId');
        $this->gateway = $dev ? Gateways::KEY_TOP_DEV : Gateways::KEY_TOP;
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

    public function getConfigInfo(): self
    {
        $this->uri = '/config/platform/GetConfigInfo';
        $this->name = '获取平台配置信息';

        $queryDat = ['serviceCode' => 'getConfigInfo'];
        $this->injectData($queryDat);
        $this->generateSignature();
        return $this;
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
}
