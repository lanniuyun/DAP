<?php

namespace On3\DAP\Platforms\UniUbi;

use Carbon\Carbon;
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
        'OGS_EXP-1906' => '错误的请求方式',
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

        $this->injectLogObj();
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
        $this->uri = 'auth';
        $this->name = '接口鉴权';
        $timestamp = intval(microtime(true));
        $this->queryBody = ['appId' => $this->appId, 'appKey' => $this->appKey, 'timestamp' => $timestamp, 'sign' => strtolower(md5($this->appKey . $timestamp . $this->appSecret))];
        return $this;
    }

    /**
     * @param array $queryPacket
     * 有两种情况：全新的设备，首次创建，首次录入云端；之前创建过，调用设备删除接口断开设备与云端的关联后，可再次创建，重新录入云端
     * SN string Y 设备序列号
     * name string N 设备名称
     * tag string N 设备标签 tag传入后，服务器会返回加密形式后的tag，可用于设备分类
     * act string N 操作 1:建立 2:更新
     * @return $this
     */
    public function bindDevice(array $queryPacket = []): self
    {
        if (!$deviceKey = Arr::get($queryPacket, 'SN')) {
            $this->cancel = true;
            $this->errBox[] = '设备序列号不得为空';
        }

        if ((Arr::get($queryPacket, 'act') ?: 1) === 1) {
            $this->uri = 'device';
            $this->name = '设备绑定建立';
        } else {
            $this->uri = 'device/' . $deviceKey;
            $this->name = '设备绑定更新';
            $this->httpMethod = self::METHOD_PUT;
        }

        $name = Arr::get($queryPacket, 'name');
        $tag = Arr::get($queryPacket, 'tag');
        $this->packetBody(compact('deviceKey', 'name', 'tag'));
        return $this;
    }

    /**
     * @param array $queryPacket
     * 设备创建、已同步后，一般都要查询该设备状态，以检查设备是否能够进行后续操作；当设备状态state为6：已同步，则设备可以开始授权和识别
     * SN string Y 设备序列号
     * @return $this
     */
    public function deviceInfo(array $queryPacket = []): self
    {
        if (!$deviceKey = Arr::get($queryPacket, 'SN')) {
            $this->cancel = true;
            $this->errBox[] = '设备序列号不得为空';
        }

        $this->name = '设备信息查询';
        $this->uri = 'device/' . $deviceKey;
        $this->httpMethod = self::METHOD_GET;
        $this->packetBody(['deviceKey' => $deviceKey]);
        return $this;
    }

    /**
     * @param array $queryPacket
     * SN string N 设备序列号
     * tag string N 设备标签 tag传入后，服务器会返回加密形式后的tag，可用于设备分类
     * state int N 设备状态  1：未绑定，设备-appId绑定关系
     *                      2：绑定中，设备与云端确认通信中
     *                      3：解绑中，设备删除中，与appId解绑中
     *                      4：未同步，云端数据未同步至设备
     *                      5：同步中，云端数据正在同步至设备
     *                      6：已同步，云端数据已同步至设备
     *                      7：已禁用，设备不可被使用
     *                      8：禁用中，设备正在“禁用”过程中
     *                      9：启用中，设备正在“启用”过程中
     * startTime Date N 录入时间起始
     * endTime Date N 录入时间末尾
     * index int N 页数
     * length int N 每页条数 默认为5
     * systemVersionNo string N 设备系统版本号
     * versionNo string N 设备应用版本号
     * orderFieldKey string N 按照某字段排序  2:DEVICE_KEY //设备序列号 9:CREATE_TIME //创建时间 默认DEVICE_KEY
     * orderTypeKey string N 生姜序 1:ASC //升序 2:DESC //降序　默认升序
     * @return $this
     */
    public function deviceList(array $queryPacket = []): self
    {
        $this->uri = 'devices/search';
        $this->name = '设备列表查询';

        $tag = Arr::get($queryPacket, 'tag');
        $deviceKey = Arr::get($queryPacket, 'SN');
        if ($state = Arr::get($queryPacket, 'state')) {
            if ($state < 1 || $state > 9) {
                $this->cancel = true;
                $this->errBox[] = '设备状态传参错误';
            }
        }

        if ($startTime = Arr::get($queryPacket, 'startTime')) {
            try {
                $startTime = Carbon::parse($startTime)->toDateString();
            } catch (\Throwable $exception) {
            }
        }

        if ($endTime = Arr::get($queryPacket, 'endTime')) {
            try {
                $endTime = Carbon::parse($endTime)->toDateString();
            } catch (\Throwable $exception) {
            }
        }

        $index = intval(Arr::get($queryPacket, 'index'));
        $length = intval(Arr::get($queryPacket, 'length'));
        $systemVersionNo = Arr::get($queryPacket, 'systemVersionNo');
        $versionNo = Arr::get($queryPacket, 'versionNo');
        $orderFieldKey = Arr::get($queryPacket, 'orderFieldKey');
        $orderTypeKey = Arr::get($queryPacket, 'orderTypeKey');
        $this->packetBody(compact('deviceKey', 'state', 'tag', 'startTime', 'endTime', 'index', 'length', 'systemVersionNo', 'versionNo', 'orderFieldKey', 'orderTypeKey'));
        return $this;
    }

    /**
     * @param array $queryPacket
     * 调用设备创建接口前，需调用设备删除接口将设备-appId解绑，否则重复添加设备会报错
     * 调用过设备删除接口后，不可继续调用业务接口，异常如：重复调用设备删除接口出错；需重新调用设备创建接口后才可调用业务接口
     * SN string Y 设备序列号
     * @return $this
     */
    public function unBindDevice(array $queryPacket = []): self
    {
        if (!$deviceKey = Arr::get($queryPacket, 'SN')) {
            $this->cancel = true;
            $this->errBox[] = '设备序列号不得为空';
        }

        $this->name = '设备解绑';
        $this->uri = 'device/' . $deviceKey;
        $this->httpMethod = self::METHOD_DELETE;
        $this->packetBody(['deviceKey' => $deviceKey]);
        return $this;
    }

    /**
     * @param array $queryPacket
     *设备创建、设备绑定成功后，设备会自动同步
     * 设备绑定成功后state为4：未同步、5：同步、6：已同步，都可以调用设备同步接口，将云端中存储的信息同步到设备上
     * 调用设备批量授权人员接口后，设备会向云端服务器请求进行同步，将新增人员数据同步至设备
     * 调用设备批量销权人员接口后，设备会向云端服务器请求进行同步，设备接收并进行删除人员操作
     * SN string Y 设备序列号
     * @return $this
     */
    public function syncDevice(array $queryPacket = []): self
    {
        $this->uri = 'device/synchronization';
        $this->name = '设备同步';

        if (!$deviceKey = Arr::get($queryPacket, 'SN')) {
            $this->cancel = true;
            $this->errBox[] = '设备序列号不得为空';
        }
        $this->packetBody(['deviceKey' => $deviceKey]);
        return $this;
    }

    /**
     * @param array $queryPacket
     * SN string Y 设备序列号
     * do string Y  enable:启用 disable:禁用 reset:重置 recovery:校准
     * @return $this
     */
    public function settingDevice(array $queryPacket = []): self
    {

        switch (strtolower(Arr::get($queryPacket, 'do'))) {
            case 'enable':
                $this->name = '设备启用';
                $this->uri = 'device/enabling';
                break;
            case 'disable':
                $this->name = '设备禁用';
                $this->uri = 'device/disabling';
                break;
            case 'reset':
                $this->name = '设备重置';
                $this->uri = 'device/reset';
                break;
            case 'recovery':
                $this->name = '设备校对';
                $this->uri = 'device/dataRecovery';
                break;
            default:
                $this->cancel = true;
                $this->errBox[] = '设备设置的行为不得为空';
                break;
        }

        if (!$deviceKey = Arr::get($queryPacket, 'SN')) {
            $this->cancel = true;
            $this->errBox[] = '设备序列号不得为空';
        }
        $this->packetBody(['deviceKey' => $deviceKey]);
        return $this;
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
            $resPacket = ['code' => 0, 'msg' => 'SUCCESS'];
        } else {
            $msg = (Arr::get(self::CODES, Arr::get($response, 'code') ?: '') ?: Arr::get($response, 'msg')) ?: '请求发生未知异常';
            $resPacket = ['code' => 500, 'msg' => $msg];
        }
        $resPacket['data'] = Arr::get($response, 'data') ?: [];
        $resPacket['raw_resp'] = $response;
        $response = $resPacket;
    }

    public function fire()
    {
        $apiName = $this->name;
        $httpMethod = $this->httpMethod;
        $uri = $this->appId . '/' . $this->uri;
        $gateway = trim($this->gateway, '/') . '/';

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
            switch ($this->httpMethod) {
                case self::METHOD_GET:
                    $rawResponse = $httpClient->$httpMethod($uri . '?' . http_build_query($this->queryBody));
                    break;
                case self::METHOD_POST:
                case self::METHOD_PUT:
                default:
                    $rawResponse = $httpClient->$httpMethod($uri, ['form_params' => $this->queryBody]);
                    break;
            }
            $respRaw = $rawResponse->getBody()->getContents();
            $respArr = @json_decode($respRaw, true) ?: [];
            $this->logging && $this->logging->info($this->name, ['gateway' => $gateway, 'uri' => $uri, 'queryBody' => $this->queryBody, 'response' => $respArr]);

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
