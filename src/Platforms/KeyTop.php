<?php

namespace On3\DAP\Platforms;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use On3\DAP\Exceptions\InvalidArgumentException;
use On3\DAP\Exceptions\RequestFailedException;
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
        $rawKeyStr = $this->join2Str($queryBody);
        $rawKeyStr .= '&' . $this->appSecret;
        Log::info($rawKeyStr);
        $this->queryBody['key'] = strtoupper(md5($rawKeyStr));
        return $this;
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

            $exception = $rawResponse = $contentStr = null;

            try {
                $rawResponse = $httpClient->$httpMethod($this->uri, ['headers' => ['version' => self::API_VERSION], 'json' => $this->queryBody]);
                $contentStr = $rawResponse->getBody()->getContents();
                $contentArr = @json_decode($contentStr, true);
            } catch (\Throwable $exception) {
                $contentArr = $exception->getMessage();
            } finally {
                try {
                    $this->logging->info($this->name, ['gateway' => $this->gateway, 'uri' => $this->uri, 'queryBody' => $this->queryBody, 'response' => $contentArr]);
                } catch (\Throwable $ignoredException) {
                }

                $this->cleanup();

                if ($exception instanceof \Throwable) {
                    throw new RequestFailedException($exception->getMessage());
                }

                if (!$rawResponse) {
                    throw new RequestFailedException('接口请求失败:' . $this->name . '无返回');
                }

                if ($rawResponse->getStatusCode() !== 200) {
                    throw new RequestFailedException('接口请求失败:' . $this->name);
                }

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
            }
        }
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
        $this->generateSignature();
    }

    protected function formatResp(&$response)
    {
        $resCode = Arr::get($response, 'resCode');
        $resMsg = strval(Arr::get($response, 'resMag'));
        $data = Arr::get($response, 'data') ?: [];

        if (is_string($data) && ($tempDat = @json_decode($data, true))) {
            $data = $tempDat;
        }

        if ($resCode === 0) {
            $resPacket = ['code' => 0, 'msg' => $resMsg, 'data' => $data, 'raw_resp' => $response];
        } else {
            $resPacket = ['code' => $resCode ?: 1, 'msg' => $resMsg, 'data' => $data, 'raw_resp' => $response];
        }

        $response = $resPacket;
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

    /**
     * @return $this
     */
    public function getConfig(array $queryPacket = []): self
    {
        $this->uri = 'config/platform/GetConfigInfo';
        $this->name = '获取平台配置信息';

        $queryDat = ['serviceCode' => 'getConfigInfo'];
        $this->injectData($queryDat);

        return $this;
    }

    /**
     * @param array $queryPacket
     * code string 支付来源码
     * @return $this
     */
    public function getPaySource(array $queryPacket = []): self
    {
        $this->uri = 'config/platform/GetPaySource';
        $this->name = '根据支付来源编码获取来源名称';

        if (!$code = Arr::get($queryPacket, 'code')) {
            $this->errBox[] = '支付来源编码不得为空';
            $this->cancel = true;
        }

        $rawBody = ['serviceCode' => 'getPaySource', 'paySourceCode' => $code];
        $this->injectData($rawBody);

        return $this;
    }

    /**
     * @param array $queryPacket
     * page int 页数
     * pageSize int 每页条数
     * @return $this
     */
    public function getParkList(array $queryPacket = []): self
    {
        $this->uri = 'config/platform/GetParkingLotList';
        $this->name = '停车场列表';

        $pageSize = abs(intval(Arr::get($queryPacket, 'pageSize'))) ?: 15;
        $page = abs(intval(Arr::get($queryPacket, 'page'))) ?: 1;
        $rawBody = ['serviceCode' => 'getParkingLotList', 'pageIndex' => $page, 'pageSize' => $pageSize];

        $this->injectData($rawBody);

        return $this;
    }

    /**
     * @param array $queryPacket
     * ID string 车场ID
     * @return $this
     */
    public function getParkInfo(array $queryPacket = []): self
    {
        $this->uri = 'api/wec/GetParkingLotInfo';
        $this->name = '停车场信息查询';

        if (!$parkID = Arr::get($queryPacket, 'ID')) {
            $this->errBox[] = '车场ID必填';
            $this->cancel = true;
        }

        $rawBody = ['serviceCode' => 'getParkingLotInfo', 'parkId' => $parkID];
        $this->injectData($rawBody);

        return $this;
    }

    /**
     * @param array $queryPacket
     * ID string 车场ID
     * type int 设备类型
     * @return $this
     */
    public function getDeviceList(array $queryPacket = []): self
    {
        $this->uri = 'api/wec/GetDeviceList';
        $this->name = '获取车场终端设备信息列表';

        if (!$parkID = Arr::get($queryPacket, 'ID')) {
            $this->errBox[] = '车场ID必填';
            $this->cancel = true;
        }

        $deviceType = intval(Arr::get($queryPacket, 'type'));
        $rawBody = ['parkId' => $parkID, 'serviceCode' => 'getDeviceList', 'deviceType' => $deviceType];
        $this->injectData($rawBody);

        return $this;
    }

    public function getParkArea(array $queryPacket = []): self
    {
        $this->uri = 'api/wec/GetParkingPlaceArea';
        $this->name = '区域信息';

        if (!$parkID = Arr::get($queryPacket, 'ID')) {
            $this->errBox[] = '车场ID必填';
            $this->cancel = true;
        }
        $rawBody = ['serviceCode' => 'getParkingPlaceArea', 'parkId' => $parkID];
        $this->injectData($rawBody);

        return $this;
    }

    public function getParkLane(array $queryPacket = []): self
    {
        return $this;
    }
}
