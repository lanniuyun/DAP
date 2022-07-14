<?php

namespace On3\DAP\Platforms\UniUbi;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use On3\DAP\Exceptions\InvalidArgumentException;
use On3\DAP\Exceptions\RequestFailedException;
use On3\DAP\Platforms\Gateways;
use On3\DAP\Platforms\Platform;
use On3\DAP\Traits\DAPBaseTrait;

class WT extends Platform
{

    use DAPBaseTrait;

    protected $appId;
    protected $appKey;
    protected $appSecret;
    protected $token;

    const AUTH_URI = "/auth";
    const DEVICE_BIND = '/device';

    const CODES = [
        'GS_EXP-100' => '接口授权appKey错误',
        'GS_EXP-101' => '接口授权sign错误',
        'GS_EXP-102' => 'token错误或失效',
        'GS_EXP-200' => '人员录入失败',
        'GS_EXP-201' => '人员查询失败',
        'GS_EXP-202' => '人员创建name为空',
        'GS_EXP-203' => '人员-设备授权deviceKeys为空',
        'GS_EXP-204' => '人员-设备销权deviceKeys为空',
        'GS_EXP-205' => '注册任务状态变更state无效',
        'GS_EXP-206' => '查询注册任务状态失败',
        'GS_EXP-207' => '更新注册任务状态失败',
        'GS_EXP-209' => '拍照注册taskId为空',
        'GS_EXP-212' => '人员不存在',
        'GS_EXP-213' => '注册任务状态无效（<0）',
        'GS_EXP-214' => 'taskId不存在或状态错误',
        'GS_EXP-215' => '输入taskId状态不合法',
        'GS_EXP-216' => 'taskId状态未就绪',
        'GS_EXP-217' => '人员不属于该应用',
        'GS_EXP-300' => '设备通信失败',
        'GS_EXP-303' => '设备录入参数错误',
        'GS_EXP-304' => '设备获取失败',
        'GS_EXP-305' => '设备创建deviceKey为空',
        'GS_EXP-308' => '人员-设备授权personGuids为空',
        'GS_EXP-309' => '设备绑定deviceKey为空',
        'GS_EXP-310' => '设备解绑deviceKey为空',
        'GS_EXP-311' => '设备禁用deviceKey为空',
        'GS_EXP-312' => '设备启用deviceKey为空',
        'GS_EXP-313' => '设备同步deviceKey为空',
        'GS_EXP-314' => '设备重置deviceKey为空',
        'GS_EXP-316' => '设备人员批量销权personGuids参数格式错误',
        'GS_EXP-317' => '设备查询设备名deviceKey为空',
        'GS_EXP-318' => '设备更新设备名deviceKey为空',
        'GS_EXP-319' => '更新设备失败',
        'GS_EXP-320' => '设备离线',
        'GS_EXP-321' => '序列号被占用',
        'GS_EXP-322' => '设备已存在',
        'GS_EXP-323' => '设备不存在',
        'GS_EXP-325' => '没有可更新字段',
        'GS_EXP-326' => '设备不在线',
        'GS_EXP-327' => '设备配置不存在',
        'GS_EXP-328' => '语音配置内容含有非法符号',
        'GS_EXP-329' => '语音模板格式错误',
        'GS_EXP-330' => '自定义内容格式错误',
        'GS_EXP-331' => '显示模板格式错误',
        'GS_EXP-332' => '串口模板格式错误',
        'GS_EXP-333' => '语音模式下自定义内容不能空',
        'GS_EXP-334' => '显示模式下自定义内容不能空',
        'GS_EXP-335' => '串口模式下自定义内容不能空',
        'GS_EXP-336' => '语音模式类型未定义',
        'GS_EXP-337' => '显示模式类型未定义',
        'GS_EXP-338' => '串口模式类型未定义',
        'GS_EXP-339' => '识别距离模式类型未定义',
        'GS_EXP-340' => '预览视频流开关模式类型未定义',
        'GS_EXP-341' => '设备名称显示类型未定义',
        'GS_EXP-342' => '设备未启动',
        'GS_EXP-343' => '识别陌生人类型未定义',
        'GS_EXP-344' => '方向配置类型未定义',
        'GS_EXP-345' => '该设备不支持刷卡功能',
        'GS_EXP-352' => '时间段参数数量不正确或超出3段限制',
        'GS_EXP-353' => '时间段参数后时间段早于前时间段',
        'GS_EXP-354' => '时间段参数超出限制',
        'GS_EXP-355' => '时间段参数格式错误',
        'GS_EXP-356' => 'logoUrl格式错误',
        'GS_EXP-357' => '设备不属于该应用',
        'GS_EXP-358' => '设备不属于任何应用',
        'GS_EXP-359' => '识别模式硬件TTL接口重复',
        'GS_EXP-360' => '识别模式硬件232接口重复',
        'GS_EXP-361' => '硬件类型只能为IC读卡器',
        'GS_EXP-362' => '硬件类型只能为ID读卡器',
        'GS_EXP-600' => '照片补推失败',
        'GS_EXP-601' => '照片状态查询失败',
        'GS_EXP-602' => '照片创建失败',
        'GS_EXP-603' => '照片创建img为空',
        'GS_EXP-604' => '照片更新状态state无效',
        'GS_EXP-606' => '超出照片添加数量',
        'GS_EXP-607' => '照片过大',
        'GS_EXP-608' => 'img为空,可能未传输或者数据过大',
        'GS_EXP-609' => '照片不存在',
        'GS_EXP-610' => '文件不为jpg或png类型',
        'GS_EXP-611' => '照片不属于该应用',
        'GS_EXP-612' => '该照片与之前注册照相似度不符，请重新提供',
        'GS_EXP-1006' => '邮件服务不可用',
        'GS_EXP-1007' => '消息服务不可用',
        'GS_EXP-1008' => '统一客户服务不可用',
        'GS_EXP-1009' => '设备服务不可用',
        'GS_EXP-1300' => '上传base64非图片',
        'GS_EXP-1301' => '人像框过小,人像面积小于图片面积5%',
        'GS_EXP-1302' => '人像侧角度大于15度',
        'GS_EXP-1303' => '照片中无人像或不止一张人像',
        'GS_EXP-1304' => '上传图片过大',
        'GS_EXP-1305' => 'base64可能未上传或图片过大',
        'GS_EXP-1306' => '检测异常',
        'OP_EXP-2002' => '图片没有检测到人像',
        'OP_EXP-2006' => '图片人像数量过多',
        'OP_EXP-3000' => '人像过小',
        'OP_EXP-3001' => '人像超出或过于靠近图片边界',
        'OP_EXP-3002' => '脸过于模糊',
        'OP_EXP-3003' => '脸光照过暗',
        'OP_EXP-3004' => '脸光照过亮',
        'OP_EXP-3005' => '脸左右亮度不对称',
        'OP_EXP-3006' => '三维旋转之俯仰角度过大',
        'OP_EXP-3007' => '三维旋转之左右旋转角过大',
        'OP_EXP-3008' => '平面内旋转角过大',
        'ERR001' => '数据库异常',
        'ERR002' => '网络异常',
        'ERR003' => '参数异常',
        'ERR004' => '系统内部异常',
        'ERR005' => '数据不存在异常',
        'ERR006' => '验证异常',
    ];

    public function __construct(array $config, bool $dev = false)
    {
        if ($gateway = Arr::get($config, 'gateway')) {
            $this->gateway = $gateway;
        } else {
            $this->gateway = $dev ? Gateways::WT_DEV : Gateways::WT;
        }

        $this->appId = Arr::get($config, 'appId');
        $this->appKey = Arr::get($config, 'appKey');
        $this->appSecret = Arr::get($config, 'appSecret');

        //$this->injectLogObj();
        $this->configValidator();
        $this->injectToken();
    }

    protected function injectToken()
    {
        $cacheKey = self::getCacheKey();
        if (!$this->token = cache($cacheKey)) {
            $resp = $this->auth()->fire();
            if ($this->token = Arr::get($resp, 'data') ?: Arr::get($resp, 'raw_resp.data')) {
                cache([$cacheKey => $this->token], now()->addHours(18));
            }
        }

        if (!$this->token) {
            throw new InvalidArgumentException('获取接口身份令牌失败');
        }
    }

    public function auth(): self
    {
        $this->fixUri(self::AUTH_URI);
        $this->name = '接口鉴权';
        $timestamp = intval(microtime(true));
        $this->queryBody = ['appId' => $this->appId, 'appKey' => $this->appKey, 'timestamp' => $timestamp, 'sign' => strtolower(md5($this->appKey . $timestamp . $this->appSecret))];
        return $this;
    }

    /**
     * @param array $queryPacket
     * 有两种情况：全新的设备，首次创建，首次录入云端；之前创建过，调用设备删除接口断开设备与云端的关联后，可再次创建，重新录入云端
     * deviceKey string Y 设备序列号
     * name string N 设备名称
     * tag string N 设备标签 tag传入后，服务器会返回加密形式后的tag，可用于设备分类
     * @return $this
     */
    public function deviceBind(array $queryPacket = []): self
    {
        $this->fixUri(self::DEVICE_BIND);
        $this->name = '设备绑定';
        if (!$deviceKey = Arr::get($queryPacket, 'deviceKey')) {
            $this->cancel = true;
            $this->errBox[] = '设备编号不得为空';
        }
        $name = Arr::get($queryPacket, 'name') ?: '';
        $tag = Arr::get($queryPacket, 'tag') ?: '';
        $this->packetBody(compact('deviceKey', 'name', 'tag'));
        return $this;
    }

    protected function fixUri(string $uri)
    {
        $this->uri = $this->appId . $uri;
    }

    protected function packetBody(array $body)
    {
        $this->queryBody = array_merge($body, ['appId' => $this->appId, 'token' => $this->token]);
    }

    protected function configValidator()
    {
        if (!$this->appId) {
            throw new InvalidArgumentException('应用ID不得为空');
        }

        if (!$this->appKey) {
            throw new InvalidArgumentException('应用key不得为空');
        }

        if (!$this->appSecret) {
            throw new InvalidArgumentException('应用密钥不得为空');
        }
    }

    protected function generateSignature()
    {
        // TODO: Implement generateSignature() method.
    }

    protected function formatResp(&$response)
    {
        if (Arr::get($response, 'success') === true) {
            $resPacket = ['code' => 0, 'msg' => 'SUCCESS', 'data' => [], 'raw_resp' => $response];
        } else {
            $msg = Arr::get(self::CODES, Arr::get($response, 'code') ?: '') ?: '未知报错';
            $resPacket = ['code' => 500, 'msg' => $msg, 'data' => [], 'raw_resp' => $response];
        }
        $response = $resPacket;
    }

    public function fire()
    {
        $httpMethod = $this->httpMethod;
        $apiName = $this->name;
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
            $rawResponse = $httpClient->$httpMethod($this->uri, ['form_params' => $this->queryBody]);
            $respRaw = $rawResponse->getBody()->getContents();
            $respArr = @json_decode($respRaw, true) ?: [];
            $this->logging && $this->logging->info($this->name, ['gateway' => $this->gateway, 'uri' => $this->uri, 'queryBody' => $this->queryBody, 'response' => $respArr]);

            $this->cleanup();

            if ($rawResponse->getStatusCode() !== 200) {
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
