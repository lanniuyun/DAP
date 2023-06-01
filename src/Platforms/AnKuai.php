<?php

namespace On3\DAP\Platforms;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use On3\DAP\Exceptions\InvalidArgumentException;
use On3\DAP\Exceptions\RequestFailedException;
use On3\DAP\Traits\DAPHashTrait;

class AnKuai extends Platform
{

    use DAPHashTrait;

    protected $appid;
    protected $appSecret;
    protected $parkKey;
    protected $port = '8888';
    protected $prefix = '/api/';
    protected $version = 'v1.0';

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

        $this->appid = Arr::get($config, 'appid');
        $this->appSecret = Arr::get($config, 'appsecret');
        $this->parkKey = Arr::get($config, 'park_key');

        $autoLogging && $this->injectLogObj();
    }

    public function setParkKey(string $parkKey): self
    {
        $this->parkKey = $parkKey;
        return $this;
    }

    public function setPort(int $port): self
    {
        if ($port) {
            $this->port = $port;
        }
        return $this;
    }

    public function timestamp(): string
    {
        return now()->format('YmdHis');
    }

    /**
     * @param array $queryPacket
     *  - carNo
     *  - startTime
     *  - endTime
     *  - pageIndex
     *  - pageSize
     * @return $this
     */
    public function getEnterCar(array $queryPacket = []): self
    {
        $this->uri = 'Inquire/GetEnterCar';
        $this->name = '查询场内记录';

        $dataPacket = $this->injectPageData($queryPacket);
        $this->injectData($dataPacket);

        return $this;
    }

    /**
     * @param array $queryPacket
     *  - carNo
     *  - startTime
     *  - endTime
     *  - pageIndex
     *  - pageSize
     * @return $this
     */
    public function getOutCar(array $queryPacket = []): self
    {
        $this->uri = 'Inquire/GetOutCar';
        $this->name = '查询出场记录';

        $dataPacket = $this->injectPageData($queryPacket);
        $this->injectData($dataPacket);

        return $this;
    }

    /**
     * @param array $queryPacket
     *  - carNo
     *  - startTime
     *  - endTime
     *  - pageIndex
     *  - pageSize
     *  - laneNo
     * @return $this
     */
    public function getRecordList(array $queryPacket = []): self
    {
        $this->uri = 'Inquire/GetRecordList';
        $this->name = '查询人工开闸记录';

        $dataPacket = $this->injectPageData($queryPacket);

        if (!$laneNo = Arr::get($queryPacket, 'laneNo')) {
            $this->errBox[] = '通道No必填';
            $this->cancel = true;
        }

        $dataPacket['passwayNo'] = $laneNo;

        $this->injectData($dataPacket);

        return $this;
    }

    public function getRemainingSpace(array $queryPacket = []): self
    {
        $this->uri = 'Inquire/GetRemainingSpace';
        $this->name = '查询剩余车位';

        $this->injectData($queryPacket);

        return $this;
    }

    /**
     * @param array $queryPacket
     *  - laneNo
     * @return $this
     */
    public function getVehicleOnline(array $queryPacket = []): self
    {
        $this->uri = 'Inquire/GetVehicleOnline';
        $this->name = '查询车道状态';

        if (!$laneNo = Arr::get($queryPacket, 'laneNo')) {
            $this->errBox[] = '通道No必填';
            $this->cancel = true;
        }

        $dataPacket['passwayNo'] = $laneNo;

        $this->injectData($dataPacket);

        return $this;
    }

    /**
     * @param array $queryPacket
     *  - carNo
     *  - startTime
     *  - endTime
     *  - pageIndex
     *  - pageSize
     * @return $this
     */
    public function getReserveList(array $queryPacket = []): self
    {
        $this->uri = 'Inquire/GetReserveList';
        $this->name = '查询访客预约车辆';

        $dataPacket = $this->injectPageData($queryPacket);
        $dataPacket['beginTime'] = Arr::get($dataPacket, 'startTime') ?: now()->startOfDay()->toDateTimeString();
        unset($dataPacket['startTime']);
        $this->injectData($dataPacket);

        return $this;
    }

    /**
     * @param array $queryPacket
     *  - orderCode
     * @return $this
     */
    public function getBusiness(array $queryPacket = []): self
    {
        $this->uri = 'Inquire/GetBusiness';
        $this->name = '查询商家车辆';

        if (!$orderNo = Arr::get($queryPacket, 'orderCode')) {
            $this->errBox[] = '单号必填';
            $this->cancel = true;
        }

        $dataPacket['orderNo'] = $orderNo;
        $this->injectData($dataPacket);

        return $this;
    }

    /**
     * @param array $queryPacket
     *  - carNo
     *  - startTime
     *  - endTime
     *  - pageIndex
     *  - pageSize
     * @return $this
     */
    public function getMonthlyCarInfo(array $queryPacket = []): self
    {
        $this->uri = 'Inquire/GetMthCarInfo';
        $this->name = '查询月租车辆';

        $dataPacket = $this->injectPageData($queryPacket);
        $dataPacket['beginTime'] = Arr::get($dataPacket, 'startTime') ?: now()->startOfDay()->toDateTimeString();
        unset($dataPacket['startTime']);
        $this->injectData($dataPacket);

        return $this;
    }

    /**
     * @param array $queryPacket
     *  - carNo
     *  - startTime
     *  - endTime
     *  - pageIndex
     *  - pageSize
     * @return $this
     */
    public function getCarBlackInfo(array $queryPacket = []): self
    {
        $this->uri = 'Inquire/GetCarBlackInfo';
        $this->name = '查询黑名单车辆';

        $dataPacket = $this->injectPageData($queryPacket);
        $dataPacket['beginTime'] = Arr::get($dataPacket, 'startTime') ?: now()->startOfDay()->toDateTimeString();
        unset($dataPacket['startTime']);
        $this->injectData($dataPacket);

        return $this;
    }

    /**
     * @param array $queryPacket
     *  - carNo
     *  - startTime
     *  - endTime
     *  - pageIndex
     *  - pageSize
     * @return $this
     */
    public function getParkingOrder(array $queryPacket = []): self
    {
        $this->uri = 'Inquire/GetPayOrder';
        $this->name = '查询缴费记录';

        $dataPacket = $this->injectPageData($queryPacket);
        $dataPacket['beginTime'] = Arr::get($dataPacket, 'startTime') ?: now()->startOfDay()->toDateTimeString();
        unset($dataPacket['startTime']);
        $this->injectData($dataPacket);

        return $this;
    }

    /**
     * @param array $queryPacket
     *  - carNo
     *  - startTime
     *  - endTime
     *  - pageIndex
     *  - pageSize
     * @return $this
     */
    public function GetParkingOrderDetail(array $queryPacket = []): self
    {
        $this->uri = 'Inquire/GetPayPart';
        $this->name = '查询缴费记录明细记录';

        $dataPacket = $this->injectPageData($queryPacket);
        $dataPacket['beginTime'] = Arr::get($dataPacket, 'startTime') ?: now()->startOfDay()->toDateTimeString();
        unset($dataPacket['startTime']);
        $this->injectData($dataPacket);

        return $this;
    }

    /**
     * @param array $queryPacket
     *  - carNo
     *  - orderCode
     *  - laneNo
     * @return $this
     */
    public function getToBePaidParkingOrder(array $queryPacket = []): self
    {
        $dataPacket = [];
        
        if ($carNo = strval(Arr::get($queryPacket, 'carNo'))) {
            $this->uri = 'Inquire/GetParkOrderByCarNo';
            $this->name = '根据车牌号获取价格';
            $dataPacket['carNo'] = $carNo;
        } elseif ($orderNo = strval(Arr::get($queryPacket, 'orderCode'))) {
            $this->uri = 'Inquire/GetParkOrderByOrderNo';
            $this->name = '根据订单号获取价格';
            $dataPacket['orderNo'] = $orderNo;
        } elseif ($laneNo = strval(Arr::get($queryPacket, 'laneNo'))) {
            $this->uri = 'Inquire/GetParkOrderByPasswayNo';
            $this->name = '获取车道当前订单';
            $dataPacket['passwayNo'] = $laneNo;
        } else {
            $this->errBox[] = '车牌号或订单号或车道号必填';
            $this->cancel = true;
        }

        $this->injectData($dataPacket);

        return $this;
    }

    protected function configValidator()
    {
        if (!$this->gateway) {
            throw new InvalidArgumentException('服务网关必填');
        }

        if (!$this->appSecret) {
            throw new InvalidArgumentException('appsecret必填');
        }

        if (!$this->appid) {
            throw new InvalidArgumentException('appid必填');
        }
    }

    protected function injectData(array $queryData)
    {
        $this->queryBody = [
            'appid' => $this->appid,
            'parkkey' => $this->parkKey,
            'timestamp' => $this->timestamp(),
            'version' => $this->version,
            'data' => json_encode($queryData)
        ];

        $this->queryBody['sign'] = $this->generateSignature();
    }

    protected function injectPageData(array $queryData): array
    {
        $carNo = strval(Arr::get($queryData, 'carNo'));

        try {
            if ($startTime = Arr::get($queryData, 'startTime')) {
                $startTime = Carbon::parse($startTime)->toDateTimeString();
            } else {
                throw new \Exception('null_date');
            }
        } catch (\Throwable $exception) {
            $startTime = now()->startOfDay()->toDateTimeString();
        }

        try {
            if ($endTime = Arr::get($queryData, 'endTime')) {
                $endTime = Carbon::parse($endTime)->toDateTimeString();
            } else {
                throw new \Exception('null_date');
            }
        } catch (\Throwable $exception) {
            $endTime = now()->endOfDay()->toDateTimeString();
        }

        $indexId = strval(intval(Arr::get($queryData, 'pageIndex')));
        $size = strval(intval(Arr::get($queryData, 'pageSize')) ?: 20);

        return compact('carNo', 'startTime', 'endTime', 'indexId', 'size');
    }

    protected function generateSignature(): string
    {
        $queryBody = $this->queryBody;
        $strAppend = $this->join2StrV2($queryBody);
        $strAppend .= '&key=' . $this->appSecret;
        return strtolower(md5($strAppend));
    }

    protected function formatResp(&$response)
    {
        // TODO: Implement formatResp() method.
    }

    public function cacheKey(): string
    {
        return '';
    }

    public function fire()
    {
        $httpMethod = $this->httpMethod;
        $gateway = $this->gateway . ':' . $this->port . $this->prefix;
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
                $rawResponse = $httpClient->request($httpMethod, $this->uri, ['json' => $this->queryBody]);
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
}
