<?php

namespace On3\DAP\Platforms;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use On3\DAP\Exceptions\InvalidArgumentException;
use On3\DAP\Traits\DAPBaseTrait;
use On3\DAP\Traits\DAPHashTrait;

class AnJuBao extends Platform
{
    use DAPHashTrait, DAPBaseTrait;

    protected $secretId;
    protected $secretKey;

    protected $userName;
    protected $passWord;

    public function __construct(array $config, bool $dev = false)
    {
        $this->userName = Arr::get($config, 'userName');
        $this->passWord = Arr::get($config, 'passWord');
        $this->secretKey = Arr::get($config, 'secretKey');
        $this->gateway = $dev ? Gateways::AN_JU_BAO_DEV : Gateways::AN_JU_BAO;
        $this->configValidator();
        $this->injectSecretId();
    }

    protected function injectSecretId()
    {
        $cacheKey = self::getCacheKey();
        if ($secretId = cache($cacheKey)) {
            $this->secretId = $secretId;
        } else {
            $response = $this->login()->fire();
            if ($secretId = Arr::get($response, 'data.secretId')) {
                $this->secretId = $secretId;
                cache([$cacheKey => $secretId], now()->addDay());
            }
        }

        if (!$this->secretId) {
            throw new InvalidArgumentException('获取secretId失败');
        }
    }

    public function login()
    {
        $this->uri = 'SQ-User/Login';
        $this->name = '账号登录';
        $queryDat = ['userName' => $this->userName, 'passWord' => $this->passWord];
        $this->injectData($queryDat);
        $this->queryBody = $queryDat;
        $this->generateSignature();
        return $this;
    }

    protected function configValidator()
    {
        if (!$this->userName) {
            throw new InvalidArgumentException('username不可为空');
        }

        if (!$this->passWord) {
            throw new InvalidArgumentException('password不可为空');
        }

        if (!$this->secretKey) {
            throw new InvalidArgumentException('secretKey不可为空');
        }
        return $this;
    }

    protected function generateSignature()
    {
        $queryBody = $this->queryBody;
        ksort($queryBody);
        $rawQueryStr = $this->join2Str($queryBody);
        $fullRawQueryStr = urldecode($this->gateway . $this->uri . '?' . $rawQueryStr);
        $encryptedQueryStr = $this->hmacSha1($fullRawQueryStr, $this->secretKey);
        $queryBody['signature'] = base64_encode($encryptedQueryStr);
        $this->queryBody = $queryBody;
        return $this;
    }

    protected function injectData(array &$queryData)
    {
        if (!Arr::get($queryData, 'timeStamp')) {
            $queryData['timeStamp'] = time();
        }
        $this->queryBody = $queryData;
    }

    public function fire()
    {
        $httpMethod = $this->httpMethod;
        $uri = urldecode($this->uri . '?' . http_build_query($this->queryBody));
        $httpClient = new Client(['base_uri' => $this->gateway, 'timeout' => $this->timeout, 'verify' => false]);
        if ($this->cancel) {
            $errBox = $this->errBox;
            if (is_array($errBox)) {
                $errMsg = implode(',', $errBox);
            } else {
                $errMsg = '未知错误';
            }
            $this->cleanup();
            throw new InvalidArgumentException($errMsg);
        } else {
            $rawResponse = $httpClient->$httpMethod($this->uri, $this->queryBody);
            $contentStr = $rawResponse->getBody()->getContents();
            $contentArr = @json_decode($contentStr, true);
            dd($contentStr,$contentArr);
        }
    }

    protected function formatResp(&$response)
    {
        // TODO: Implement formatResp() method.
    }
}
