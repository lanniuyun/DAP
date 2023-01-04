<?php

namespace On3\DAP\Platforms;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use On3\DAP\Exceptions\InvalidArgumentException;
use On3\DAP\Exceptions\RequestFailedException;

class KeXiang extends Platform
{

    protected $timeout = 8;

    public function __construct(array $config, bool $dev = false, bool $loadingToken = true, bool $autoLogging = true)
    {
        $this->gateway = Arr::get($config, 'gateway');

        $this->configValidator();
        $autoLogging && $this->injectLogObj();
    }

    protected function configValidator()
    {
        if (!$this->gateway) {
            throw new InvalidArgumentException('未配置设备平台网关');
        }
    }

    protected function generateSignature()
    {
        // TODO: Implement generateSignature() method.
    }

    protected function formatResp(&$response)
    {
        if (Arr::get($response, 'code') === 200) {
            $resPacket = ['code' => 0, 'msg' => 'SUCCESS'];
        } else {
            $msg = Arr::get($response, 'message') ?: '请求发生未知异常';
            $resPacket = ['code' => 500, 'msg' => $msg];
        }
        $resPacket['data'] = Arr::get($response, 'data') ?: [];
        $resPacket['raw_resp'] = $response;
        $response = $resPacket;
    }

    public function cacheKey(): string
    {
        // TODO: Implement cacheKey() method.
        return '';
    }

    public function deviceList(array $queryPacket = []): self
    {
        $this->uri = 'online.action';
        $this->name = '获取设备信息';

        $sns = Arr::get($queryPacket, 'SNs');

        if (is_array($sns)) {
            $sns = implode(',', $sns);
        }

        if (!$sns) {
            $this->cancel = true;
            $this->errBox[] = '设备序列号不得为空';
        }

        $this->queryBody = compact('sns');
        return $this;
    }

    public function deviceSwitch(array $queryPacket = []): self
    {
        $this->uri = 'ele/ele!control.action';
        $this->name = '设备开关';

        if (!$sn = Arr::get($queryPacket, 'SN')) {
            $this->cancel = true;
            $this->errBox[] = '设备序列号不得为空';
        }

        $type = Arr::get($queryPacket, 'type');

        if (!in_array($type, [1, 2], true)) {
            $this->cancel = true;
            $this->errBox[] = '设备开关标识只能是1:开电,2:关电';
        }

        $this->queryBody = compact('sn', 'type');
        return $this;
    }

    public function deviceDetails(array $queryPacket = []): self
    {
        $this->uri = 'admin/release!rptdata.action';
        $this->name = '获取用电用水明细';

        $sns = Arr::get($queryPacket, 'SNs');

        if (is_array($sns)) {
            $sns = implode(',', $sns);
        }

        if (!$sns) {
            $this->cancel = true;
            $this->errBox[] = '设备序列号不得为空';
        }

        try {
            if (!$begin = Arr::get($queryPacket, 'begin')) {
                throw new \Exception('取默认开始时间');
            }
            $begin = Carbon::parse($begin)->toDateString();
        } catch (\Throwable $exception) {
            $begin = now()->startOfMonth()->toDateString();
        }

        try {
            if (!$end = Arr::get($queryPacket, 'end')) {
                throw new \Exception('取默认结束时间');
            }
            $end = Carbon::parse($end)->toDateString();
        } catch (\Throwable $exception) {
            $end = now()->endOfMonth()->toDateString();
        }

        $this->queryBody = compact('sns', 'begin', 'end');
        return $this;
    }

    public function deviceInfos(array $queryPacket = []): self
    {
        $this->uri = 'reading.action';
        $this->name = '获取当前水电表信息';

        $sns = Arr::get($queryPacket, 'SNs');

        if (is_array($sns)) {
            $sns = implode(',', $sns);
        }

        if (!$sns) {
            $this->cancel = true;
            $this->errBox[] = '设备序列号不得为空';
        }

        $this->queryBody = compact('sns');
        return $this;
    }

    public function fire()
    {
        $apiName = $this->name;
        $httpMethod = $this->httpMethod;
        $gateway = trim($this->gateway, '/') . '/';
        $uri = $this->uri;

        $httpClient = new Client(['base_uri' => $gateway, 'timeout' => $this->timeout, 'verify' => false]);

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

            if ($this->queryBody) {
                $queryStr = '';
                foreach ($this->queryBody as $key => $value) {
                    $queryStr .= $key . '=' . $value . '&';
                }
                $queryStr = trim($queryStr, '&');
                $uri = $this->uri . '?' . $queryStr;
                $this->queryBody = [];
            }

            switch ($this->httpMethod) {
                case self::METHOD_GET:
                    $queryBody = ['query' => $this->queryBody];
                    break;
                case self::METHOD_POST:
                case self::METHOD_PUT:
                default:
                    $queryBody = ['json' => $this->queryBody];
                    break;
            }

            $logBody = ['gateway' => $gateway, 'uri' => $uri, 'queryBody' => $this->queryBody, 'response' => '', 'errMsg' => ''];
            $respArr = [];
            $respRaw = '';
            $rawResponse = null;

            try {
                $rawResponse = $httpClient->$httpMethod($uri, $queryBody);
                $respRaw = $rawResponse->getBody()->getContents();
                $respArr = @json_decode($respRaw, true) ?: [];
                $logBody['response'] = $respArr ?: $respRaw;
            } catch (\Throwable $exception) {
                $logBody['errMsg'] = $exception->getMessage();
            } finally {
                try {
                    $this->logging && $this->logging->info($this->name, $logBody);
                } catch (\Throwable $logException) {
                }

                if (isset($exception)) {
                    throw $exception;
                }
            }

            $this->cleanup();

            if (optional($rawResponse)->getStatusCode() !== 200) {
                throw new RequestFailedException('接口请求失败:' . $apiName);
            }

            switch ($this->responseFormat) {
                case self::RESP_FMT_JSON:
                    $responsePacket = $respArr;
                    $this->formatResp($responsePacket);
                    return $responsePacket;
                case self::RESP_FMT_BODY:
                    return $respRaw;
                case self::RESP_FMT_RAW:
                default:
                    return $rawResponse;
            }
        }
    }
}
