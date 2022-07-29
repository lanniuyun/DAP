<?php

namespace On3\DAP\Platforms;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use On3\DAP\Exceptions\InvalidArgumentException;
use On3\DAP\Exceptions\RequestFailedException;
use On3\DAP\Traits\DAPBaseTrait;

class JIeShunJHTDC extends Platform
{

    use DAPBaseTrait;

    protected $pno;
    protected $secret;
    protected $ve = '1.0';
    protected $hashPacket;
    protected $token;
    protected $sn;

    public function __construct(array $config, bool $dev = false, bool $loadingToken = true)
    {
        $this->pno = Arr::get($config, 'pno');
        $this->secret = Arr::get($config, 'secret');
        $this->gateway = !$dev ? Gateways::JIE_SHUN_JHTDC : Gateways::JIE_SHUN_JHTDC_DEV;
        $this->injectLogObj();
        $this->configValidator();
        $loadingToken && $this->injectToken();
    }

    protected function configValidator()
    {
        if (!$this->pno) {
            throw new InvalidArgumentException('数据中心账号不可为空');
        }

        if (!$this->secret) {
            throw new InvalidArgumentException('数据中心密钥不可为空');
        }
    }

    public function injectToken(bool $refresh = false)
    {
        $cacheKey = self::getCacheKey($this->pno);
        if (!($token = cache($cacheKey)) || $refresh) {
            $response = $this->login()->fire();
            if ($this->token = Arr::get($response, 'tn') ?: Arr::get($response, 'raw_resp.tn')) {
                cache([$cacheKey => $this->token], now()->addSeconds(Arr::get($response, 'timeOut') ?: 86400));
            }
        } else {
            $this->token = $token;
        }

        if (!$this->token) {
            throw new InvalidArgumentException('获取token失败');
        }
    }

    protected function login()
    {
        $this->uri = 'login';
        $this->name = '平台登录';
        $this->queryBody = ['pno' => $this->pno, 'secret' => $this->secret];
        return $this;
    }

    public function fetchFileUrl($filePatches): self
    {
        $this->uri = 'pic/picSearchUrl';
        $this->name = '获取文件完整路径';
        if (is_string($filePatches)) {
            $filePatches = Arr::wrap($filePatches);
        }

        $filePatchPacket = [];

        foreach ($filePatches as $filePatch) {
            $filePatchPacket[] = ['filePath' => $filePatch];
        }
        $this->loading($filePatchPacket);
        return $this;
    }

    protected function loading(array $dataItems)
    {
        $dataItems = json_encode($dataItems);
        $ts = now()->format('YmdHis');
        $this->hashPacket = [$this->secret, $ts, $dataItems];
        $this->generateSignature();
        $this->queryBody = [
            'pno' => $this->pno,
            'tn' => $this->token,
            'ts' => $ts,
            've' => $this->ve,
            'sn' => $this->sn,
            'dataItems' => $dataItems
        ];
    }

    protected function generateSignature()
    {
        $hashStr = implode('', $this->hashPacket);
        $this->sn = strtoupper(md5($hashStr));
    }

    protected function formatResp(&$response)
    {
        if (Arr::get($response, 'resultCode') === 0) {
            $resPacket = ['code' => 0, 'msg' => 'SUCCESS', 'data' => Arr::get($response, 'dataItems') ?: [], 'raw_resp' => $response];
        } else {
            $resPacket = ['code' => 500, 'msg' => Arr::get($response, 'message'), 'data' => [], 'raw_resp' => $response];
        }
        $response = $resPacket;
    }

    public function fire()
    {
        $httpMethod = $this->httpMethod;
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
            $rawResponse = $httpClient->$httpMethod($this->uri, ['json' => $this->queryBody]);
            $contentStr = $rawResponse->getBody()->getContents();
            $contentArr = @json_decode($contentStr, true);
            $this->logging->info($this->name, ['gateway' => $this->gateway, 'uri' => $this->uri, 'queryBody' => $this->queryBody, 'response' => $contentArr]);
            $this->cleanup();

            if ($rawResponse->getStatusCode() === 200) {
                switch ($this->responseFormat) {
                    case self::RESP_FMT_JSON:
                        $responsePacket = $contentArr;
                        $this->formatResp($responsePacket);
                        return $responsePacket;
                    case self::RESP_FMT_BODY:
                        return $contentStr;
                    case self::RESP_FMT_RAW:
                    default:
                        return $rawResponse;
                }
            } else {
                throw new RequestFailedException('接口请求失败:' . $this->name);
            }
        }
    }
}
