<?php

namespace On3\DAP\Platforms;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use On3\DAP\Exceptions\InvalidArgumentException;
use On3\DAP\Exceptions\RequestFailedException;

class E7 extends Platform
{

    protected $port = 60009;
    protected $gID;
    protected $prefix = '/api/';

    public function __construct(array $config, bool $dev = false, bool $loadingToken = true, bool $autoLogging = true)
    {
        if ($gateway = Arr::get($config, 'gateway')) {
            $gatewayPacket = parse_url($gateway);
            if (is_array($gatewayPacket) && isset($gatewayPacket['scheme']) && isset($gatewayPacket['host'])) {
                $this->gateway = $gatewayPacket['scheme'] . '://' . $gatewayPacket['host'];
                if (isset($gatewayPacket['port']) && is_integer($gatewayPacket['port'])) {
                    $this->port = $gatewayPacket['port'];
                }
            }
        }

        if ($port = Arr::get($config, 'port')) {
            if (is_integer($port)) {
                $this->port = $port;
            }
        }

        if ($gID = Arr::get($config, 'gID')) {
            $this->gID = $gID;
        }

        $this->configValidator();
        $autoLogging && $this->injectLogObj();
    }

    protected function configValidator()
    {
        if (!$this->gateway) {
            throw new InvalidArgumentException('服务网关必填');
        }

        if (!$this->gID) {
            throw new InvalidArgumentException('集团标识必填');
        }
    }

    public function setPort(int $port): self
    {
        if ($port) {
            $this->port = $port;
        }
        return $this;
    }

    protected function generateSignature()
    {
        // TODO: Implement generateSignature() method.
    }

    protected function formatResp(&$response)
    {
        $resultCode = 2;
        $message = '';
        $dataPacket = [];

        if (Arr::has($response, 'Code')) {
            $resultCode = Arr::get($response, 'Code');
            $message = Arr::get($response, 'Describe');
        } elseif (Arr::has($response, 'State')) {
            $resultCode = Arr::get($response, 'State.Code');
            $message = Arr::get($response, 'State.Describe');
        }

        if (Arr::has($response, 'Models')) {
            $dataPacket = Arr::get($response, 'Models');
        } elseif (Arr::has($response, 'Records')) {
            $dataPacket = Arr::get($response, 'Records');
        }

        if (in_array($resultCode, [0, 1], true)) {
            $resPacket = ['code' => 0, 'msg' => 'SUCCESS', 'data' => $dataPacket, 'raw_resp' => $response];
        } else {
            $resPacket = ['code' => 500, 'msg' => strval($message), 'data' => [], 'raw_resp' => $response];
        }

        if ($pagination = Arr::get($response, 'PageAttri')) {
            $resPacket['pagination'] = $pagination;
        }
        $response = $resPacket;
    }

    public function cacheKey(): string
    {
        return '';
    }

    public function fire()
    {
        $httpMethod = $this->httpMethod;
        $gateway = $this->gateway . ':' . $this->port;
        $uri = $this->prefix . $this->uri;
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
            $exception = $rawResponse = $contentStr = null;
            try {
                $rawResponse = $httpClient->$httpMethod($uri, ['json' => $this->queryBody]);
                $contentStr = $rawResponse->getBody()->getContents();
                $contentArr = @json_decode($contentStr, true);
            } catch (\Throwable $exception) {
                $contentArr = $exception->getMessage();
            } finally {
                try {
                    $this->logging->info($this->name, ['gateway' => $gateway, 'uri' => $this->uri, 'queryBody' => $this->queryBody, 'response' => $contentArr]);
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

    /**
     * @param array $queryPacket
     * page int 页数
     * pageSize int 每页条数
     * orderBy string 排序
     * orderType bool 排序类型
     * where string 筛选条件
     * append string 附加值
     * pageTotal int 总数
     * @return $this
     */
    public function getParkList(array $queryPacket = []): self
    {
        $this->uri = 'Park/GetByCustom';
        $this->name = '停车场基础信息列表';
        $this->queryBody = self::getPageQuery($queryPacket);
        return $this;
    }

    /**
     * @param array $queryPacket
     * id string 车场ID
     * @return $this
     */
    public function getParkInfo(array $queryPacket = []): self
    {
        $this->uri = 'Park/Get/';
        $this->name = '停车场基础信息详情';
        $this->httpMethod = self::METHOD_GET;

        $parkID = Arr::get($queryPacket, 'id');

        if (!$parkID || !is_numeric($parkID)) {
            $this->cancel = true;
            $this->errBox[] = '车场ID只能为数字类型不为空';
        }

        $this->uri .= $parkID;

        return $this;
    }

    /**
     * @param array $queryPacket
     * page int 页数
     * pageSize int 每页条数
     * orderBy string 排序
     * orderType bool 排序类型
     * where string 筛选条件
     * append string 附加值
     * pageTotal int 总数
     * @return $this
     */
    public function getGateControllerDeviceList(array $queryPacket = []): self
    {
        $this->uri = 'GateControllerDevice/GetByCustom';
        $this->name = '开闸设备信息列表';
        $this->queryBody = self::getPageQuery($queryPacket);
        return $this;
    }

    /**
     * @param array $queryPacket
     * id int 车闸ID
     * @return $this
     */
    public function getGateControllerDeviceInfo(array $queryPacket = []): self
    {
        $this->uri = 'GateControllerDevice/Get/';
        $this->name = '开闸设备信息详情';
        $this->httpMethod = self::METHOD_GET;

        $deviceID = Arr::get($queryPacket, 'id');

        if (!$deviceID || !is_numeric($deviceID)) {
            $this->cancel = true;
            $this->errBox[] = '车闸ID只能为数字类型不为空';
        }

        $this->uri .= $deviceID;

        return $this;
    }

    public function createExtendedService(array $queryPacket = []): self
    {
        $this->uri = 'TcmFixedMoney/Add';
        $this->name = '月卡延期规则编辑';

        if (!$this->queryBody['TcmId'] = Arr::get($queryPacket, 'tcmID')) {
            $this->cancel = true;
            $this->errBox[] = '卡类必填';
        }

        if (!$this->queryBody['MonthMoney'] = floatval(Arr::get($queryPacket, 'monthMoney'))) {
            $this->cancel = true;
            $this->errBox[] = '月金额必填';
        }

        if (!$this->queryBody['DayMoney'] = floatval(Arr::get($queryPacket, 'dayMoney'))) {
            $this->cancel = true;
            $this->errBox[] = '日金额必填';
        }

        if (!$this->queryBody['DiscountRate'] = floatval(Arr::get($queryPacket, 'discountRate'))) {
            $this->cancel = true;
            $this->errBox[] = '抵扣额度必填';
        }

        if (!$this->queryBody['OperatorName'] = Arr::get($queryPacket, 'operatorName')) {
            $this->cancel = true;
            $this->errBox[] = '操作员必填';
        }

        $this->queryBody['OperatorDate'] = now()->toDateTimeString();
        $this->queryBody['Gid'] = $this->gID;

        if (!$this->queryBody['Rid'] = floatval(Arr::get($queryPacket, 'rID'))) {
            $this->cancel = true;
            $this->errBox[] = '标识号必填';
        }

        return $this;
    }

    /**
     * @param array $queryPacket
     * page int 页数
     * pageSize int 每页条数
     * orderBy string 排序
     * orderType bool 排序类型
     * where string 筛选条件
     * append string 附加值
     * pageTotal int 总数
     * @return $this
     */
    public function getTcmList(array $queryPacket = []): self
    {
        $this->uri = 'Tcm/GetByCustom';
        $this->name = '获取可用卡';
        $this->queryBody = self::getPageQuery($queryPacket);
        return $this;
    }

    protected function getPageQuery(array $queryPacket = []): array
    {
        $PageSize = intval(Arr::get($queryPacket, 'pageSize'));
        $CurrentPage = intval(Arr::get($queryPacket, 'page'));
        $OrderBy = strval(Arr::get($queryPacket, 'orderBy'));
        $OrderType = boolval(Arr::get($queryPacket, 'orderType'));
        $where = strval(Arr::get($queryPacket, 'where'));
        $Append = strval(Arr::get($queryPacket, 'append'));
        $TotalCount = intval(Arr::get($queryPacket, 'pageTotal'));

        return compact('PageSize', 'CurrentPage', 'OrderBy', 'OrderType', 'where', 'Append', 'TotalCount');
    }
}
