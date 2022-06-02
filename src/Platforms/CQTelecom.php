<?php

namespace On3\DAP\Platforms;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use On3\DAP\Exceptions\InvalidArgumentException;
use On3\DAP\Exceptions\RequestFailedException;
use On3\DAP\Traits\DAPHashTrait;

class CQTelecom extends Platform
{

    use DAPHashTrait;

    protected $gateway;
    protected $headers = [];
    protected $key;
    const USER_URI = 'pmPerson/save';
    protected $timeout = 10;

    const ACT_ADD = 'add';
    const ACT_UPDATE = 'update';
    const ACT_DEL = 'delete';

    public function __construct(array $config, bool $dev = false)
    {
        if ($gateway = Arr::get($config, 'gateway')) {
            $this->gateway = $gateway;
        } else {
            $this->gateway = $dev ? Gateways::CQ_TELECOM_DEV : Gateways::CQ_TELECOM;
        }

        $this->key = Arr::get($config, 'key');
        $this->configValidator();
        $this->injectLogObj();
    }

    public function setUsers(array $bodyPacket): self
    {
        $this->uri = self::USER_URI;
        $this->name = '编辑人员信息';
        $users = Arr::get($bodyPacket, 'users');
        foreach ($users as $user) {
            if (!Arr::get($user, 'uid')) {
                $this->cancel = true;
                $this->errBox[] = '人员的标识不得为空';
            }

            if (!Arr::get($user, 'name')) {
                $this->cancel = true;
                $this->errBox[] = '人员的姓名不得为空';
            }

            if (!Arr::get($user, 'phone')) {
                $this->cancel = true;
                $this->errBox[] = '人员的电话不得为空';
            }

            if (!Arr::get($user, 'complex')) {
                $this->cancel = true;
                $this->errBox[] = '小区名称不得为空';
            }

            if (!Arr::get($user, 'address')) {
                $this->cancel = true;
                $this->errBox[] = '小区地址不得为空';
            }
        }

        $sn = Arr::get($bodyPacket, 'sn');
        if (is_array($sn)) {
            $sn = implode(',', $sn);
        }

        $this->queryBody = ['data' => $users, 'deviceSnList' => $sn, 'sync' => Arr::get($bodyPacket, 'sync') == 1 ? 1 : 0];
        $this->generateSignature();
        return $this;
    }

    public function rmUsers(array $bodyPacket): self
    {
        $this->name = '移除人员信息';
        $this->uri = 'pmPerson/delete';

        if (!$uid = Arr::get($bodyPacket, 'uid')) {
            $this->cancel = true;
            $this->errBox[] = '人员标识不得为空';
        }

        if (is_array($uid)) {
            $uid = implode(',', $uid);
        }
        $this->queryBody = compact('uid');
        $this->generateSignature();
        return $this;
    }

    public function authorizedBodyFeatures(array $bodyPacket): self
    {
        $this->uri = 'face/down';
        $this->name = '下发人体特征';

        if (!$uid = Arr::get($bodyPacket, 'uid')) {
            $this->cancel = true;
            $this->errBox[] = '人员标识不得为空';
        }

        if (is_array($uid)) {
            $uid = implode(',', $uid);
        }

        if (!$deviceSn = Arr::get($bodyPacket, 'sn')) {
            $this->cancel = true;
            $this->errBox[] = '设备编号不得为空';
        }

        if (is_array($deviceSn)) {
            $deviceSn = implode(',', $deviceSn);
        }

        if (!$type = Arr::get($bodyPacket, 'type')) {
            $type = self::ACT_ADD;
        }

        if (!in_array($type, [self::ACT_ADD, self::ACT_DEL, self::ACT_UPDATE], true)) {
            $this->cancel = true;
            $this->errBox[] = '操作类型错误';
        }
        $this->queryBody = compact('uid', 'deviceSn', 'type');
        return $this;
    }

    protected function configValidator()
    {
        if (!$this->key) {
            throw new InvalidArgumentException('key不得为空');
        }
    }

    protected function generateSignature()
    {
        $this->headers['Sign'] = strtoupper(md5($this->join2Str($this->queryBody)));
    }

    protected function formatResp(&$response)
    {
        if (Arr::get($response, 'code') === 0) {
            $resPacket = ['code' => 0, 'msg' => 'SUCCESS', 'data' => [], 'raw_resp' => $response];
        } else {
            $resPacket = ['code' => 500, 'msg' => Arr::get($response, 'msg') ?: '操作失败', 'data' => [], 'raw_resp' => $response];
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
            $queryBody = ['json' => $this->queryBody, 'headers' => $this->headers];
            $rawResponse = $httpClient->$httpMethod($this->uri, $queryBody);
            $contentStr = $rawResponse->getBody()->getContents();
            $contentArr = @json_decode($contentStr, true);
            $this->logging->info($this->name, ['gateway' => $this->gateway, 'uri' => $this->uri, 'queryBody' => $queryBody, 'response' => $contentArr]);
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

    protected function cleanup()
    {
        parent::cleanup();
        $this->headers = [];
    }
}
