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
        $this->appId = Arr::get($config, 'appID');
        $this->appSecret = Arr::get($config, 'appSecret');
        $this->parkId = Arr::get($config, 'parkID');

        if ($gateway = Arr::get($config, 'gateway')) {
            $this->gateway = $gateway;
        } else {
            $this->gateway = $dev ? Gateways::KEY_TOP_DEV : Gateways::KEY_TOP;
        }

        $this->configValidator();
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
        $rawKeyStr = $this->join2StrV1($queryBody);
        $rawKeyStr .= '&' . $this->appSecret;
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
        $resMsg = strval(Arr::get($response, 'resMsg'));
        $data = Arr::get($response, 'data') ?: [];

        if (is_string($data) && ($tempDat = @json_decode($data, true))) {
            $data = $tempDat;
        }

        if ($resCode === '0') {
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

    protected function getParkID(array $queryPacket = [])
    {
        $parkID = Arr::get($queryPacket, 'ID') ?: $this->parkId;

        if (!$parkID) {
            $this->errBox[] = '车场ID必填';
            $this->cancel = true;
        }
        return $parkID;
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

        $parkID = self::getParkID($queryPacket);
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

        $parkID = self::getParkID($queryPacket);
        $deviceType = intval(Arr::get($queryPacket, 'type'));
        $rawBody = ['parkId' => $parkID, 'serviceCode' => 'getDeviceList', 'deviceType' => $deviceType];
        $this->injectData($rawBody);

        return $this;
    }

    /**
     * @param array $queryPacket
     * ID string 车场ID
     * @return $this
     */
    public function getParkArea(array $queryPacket = []): self
    {
        $this->uri = 'api/wec/GetParkingPlaceArea';
        $this->name = '区域信息';

        $parkID = self::getParkID($queryPacket);
        $rawBody = ['serviceCode' => 'getParkingPlaceArea', 'parkId' => $parkID];
        $this->injectData($rawBody);

        return $this;
    }

    /**
     * @param array $queryPacket
     * ID string 车场ID
     * page int 页码
     * pageSize int 每页条数
     * @return $this
     */
    public function getParkLane(array $queryPacket = []): self
    {
        $this->uri = 'api/wec/GetParkingNode';
        $this->name = '通道信息';

        $parkID = self::getParkID($queryPacket);
        $pageSize = abs(intval(Arr::get($queryPacket, 'pageSize'))) ?: 15;
        $page = abs(intval(Arr::get($queryPacket, 'page'))) ?: 1;
        $rawBody = ['serviceCode' => 'getParkingNode', 'parkId' => $parkID, 'pageIndex' => $page, 'pageSize' => $pageSize];
        $this->injectData($rawBody);

        return $this;
    }

    /**
     * @param array $queryPacket
     * ID string 车场ID
     * @return $this
     */
    public function getParkExtInfo(array $queryPacket = []): self
    {
        $this->uri = 'api/wec/GetParkingLotInfoExt';
        $this->name = '停车场信息查询扩展';

        $parkID = self::getParkID($queryPacket);
        $rawBody = ['serviceCode' => 'getParkingLotInfo', 'parkId' => $parkID];
        $this->injectData($rawBody);

        return $this;
    }

    public function getLaneInfo(array $queryPacket = []): self
    {
        $this->uri = 'api/wec/GetNodeInfoById';
        $this->name = '根据通道ID查询通道数据及所属区域信息';

        $parkID = self::getParkID($queryPacket);

        if (!$laneID = Arr::get($queryPacket, 'laneID')) {
            $this->errBox[] = '通道ID必填';
            $this->cancel = true;
        }

        $rawBody = ['serviceCode' => 'getNodeInfoById', 'parkId' => $parkID, 'nodeId' => $laneID];
        $this->injectData($rawBody);

        return $this;
    }

    /**
     * @param array $queryPacket
     * @return $this
     */
    public function queryCar(array $queryPacket = []): self
    {
        $this->uri = 'api/wec/GetCarCardInfo';
        $this->name = '固定车查询接口';

        $parkID = self::getParkID($queryPacket);
        $carNo = Arr::get($queryPacket, 'carNo');
        $cardID = Arr::get($queryPacket, 'cardID');

        $rawBody = ['serviceCode' => 'getNodeInfoById', 'parkId' => $parkID];

        if ($carNo) {
            $rawBody['plateNo'] = $carNo;
        } elseif ($cardID) {
            $rawBody['cardId'] = $cardID;
        } else {
            $this->errBox[] = '车牌号or卡片ID为空';
            $this->cancel = true;
        }
        $this->injectData($rawBody);

        return $this;
    }

    /**
     * @param array $queryPacket
     * act int 操作 1:新增 2:更新 0:删除
     * @return $this
     */
    public function operateExtendedService(array $queryPacket = []): self
    {

        switch ($act = Arr::get($queryPacket, 'act')) {
            case 0:
                $this->uri = 'api/wec/DelCarCardInfo';
                $this->name = '固定车删除';
                $serviceCode = 'delCarCardInfo';
                break;
            case 2:
                $this->uri = 'api/wec/ModifyCarCardNo';
                $this->name = '修改车新增';
                $serviceCode = 'modifyCarCardNo';
                break;
            case 1:
            default:
                $this->uri = 'api/wec/AddCarCardNo';
                $this->name = '固定车新增';
                $serviceCode = 'addCarCardNo';
                $act = 1;
                break;
        }

        $parkID = self::getParkID($queryPacket);
        $rawBody = ['serviceCode' => $serviceCode, 'parkId' => $parkID];

        if ($act !== 0) {

            $operateID = intval(Arr::get($queryPacket, 'operateID'));

            if (!$operateName = Arr::get($queryPacket, 'operateName')) {
                $this->errBox[] = '操作人员名称必填';
                $this->cancel = true;
            }

            $rawBody['userId'] = $operateID;
            $rawBody['userName'] = $operateName;

            if (!$cardName = Arr::get($queryPacket, 'cardName')) {
                $this->errBox[] = '卡名称必填';
                $this->cancel = true;
            }

            if (!$userName = Arr::get($queryPacket, 'userName')) {
                $this->errBox[] = '户主名必填';
                $this->cancel = true;
            }

            $cardInfo = [
                'cardName' => $cardName,
                'useName' => $userName,
                'tel' => strval(Arr::get($queryPacket, 'phone')),
                'roomId' => strval(Arr::get($queryPacket, 'roomID')),
                'remak' => strval(Arr::get($queryPacket, 'remark')),
                'contact' => strval(Arr::get($queryPacket, 'contact')),
                'assist' => strval(Arr::get($queryPacket, 'assist'))
            ];

            if ($act === 2) {
                if (!$cardID = Arr::get($queryPacket, 'cardID')) {
                    $this->errBox[] = '卡片id必填';
                    $this->cancel = true;
                }

                $cardInfo['cardId'] = $cardID;
                $cardInfo['updateTime'] = strval(Arr::get($queryPacket, 'updateTime'));
            }

            $rawBody['cardInfo'] = @json_encode($cardInfo);

            if ($lots = Arr::get($queryPacket, 'lots')) {

                $carLotList = [];

                foreach ($lots as $lot) {

                    if (!$lotName = Arr::get($lot, 'name')) {
                        $this->errBox[] = '车位名称必填';
                        $this->cancel = true;
                    }

                    $id = intval(Arr::get($lot, 'ID'));
                    $carType = intval(Arr::get($lot, 'type'));
                    $sequence = intval(Arr::get($lot, 'sequence'));
                    $lotCount = intval(Arr::get($lot, 'count'));
                    $areaIDs = Arr::get($lot, 'areaIDs');

                    if (is_string($areaIDs)) {
                        $areaIDs = explode(',', $areaIDs);
                    }

                    if (!$areaName = Arr::get($lot, 'area')) {
                        $this->errBox[] = '区域名称必填';
                        $this->cancel = true;
                    }

                    if (!$areaIDs || !is_array($areaIDs)) {
                        $this->errBox[] = '区域IDs必填';
                        $this->cancel = true;
                    }

                    $carLotList[] = [
                        'id' => $id,
                        'lotName' => $lotName,
                        'carType' => $carType,
                        'sequence' => $sequence,
                        'areaName' => $areaName,
                        'areaId' => $areaIDs,
                        'lotCount' => $lotCount,
                        'ruleId' => intval(Arr::get($lot, 'ruleID'))
                    ];
                }

                if (!$carLotList) {
                    $this->errBox[] = '车位为空';
                    $this->cancel = true;
                }

                $rawBody['carLotList'] = @json_encode($carLotList);
            } else {
                $this->errBox[] = '车位信息必填';
                $this->cancel = true;
            }

            if ($cars = Arr::get($queryPacket, 'cars')) {

                $plateNoInfo = [];

                foreach ($cars as $car) {

                    $rowInfo = [];

                    if ($carNo = Arr::get($car, 'carNo')) {
                        $rowInfo = ['plateNo' => $carNo];
                    } elseif ($etcNo = Arr::get($car, 'etcNo')) {
                        $rowInfo = ['etcNo' => $etcNo];
                    }

                    if ($act === 2) {
                        if ($ID = Arr::get($car, 'id')) {
                            $rowInfo['id'] = $ID;
                        }
                    }

                    if ($rowInfo) {
                        $plateNoInfo[] = $rowInfo;
                    }
                }

                if (!$plateNoInfo) {
                    $this->errBox[] = '车牌为空';
                    $this->cancel = true;
                }

                $rawBody['plateNoInfo'] = @json_encode($plateNoInfo);
            } else {
                $this->errBox[] = '车辆信息必填';
                $this->cancel = true;
            }

        } else {
            if (!$cardID = Arr::get($queryPacket, 'cardID')) {
                $this->errBox[] = '卡片ID必填';
                $this->cancel = true;
            }
            $rawBody['cardId'] = $cardID;
        }

        $this->injectData($rawBody);
        return $this;
    }

    /**
     * @param array $queryPacket
     * type int 车的类型
     * ID int 规则ID
     * @return $this
     */
    public function getCardRules(array $queryPacket = []): self
    {
        $this->uri = 'api/wec/GetCarCardRule';
        $this->name = '充值规则查询';

        $parkID = self::getParkID($queryPacket);
        $rawBody = ['serviceCode' => 'getCarCardRule', 'parkId' => $parkID];

        $type = Arr::get($queryPacket, 'type');
        $ID = Arr::get($queryPacket, 'ID');

        if (is_integer($type)) {
            $rawBody['carType'] = $type;
        }

        if (is_int($ID)) {
            $rawBody['ruleId'] = $ID;
        }

        $this->injectData($rawBody);

        return $this;
    }

    public function payExtendedService(array $queryPacket = []): self
    {
        $this->uri = 'api/wec/PayCarCardFee';
        $this->name = '固定车充值接口';

        if (!$cardID = Arr::get($queryPacket, 'cardID')) {
            $this->errBox[] = '卡片ID必填';
            $this->cancel = true;
        }

        if (!$operateName = Arr::get($queryPacket, 'operateName')) {
            $this->errBox[] = '操作人员名称必填';
            $this->cancel = true;
        }

        if (!$orderCode = Arr::get($queryPacket, 'orderCode')) {
            $this->errBox[] = '订单号必填';
            $this->cancel = true;
        }

        if (!$validFrom = strval(Arr::get($queryPacket, 'beganAt'))) {
            $this->errBox[] = '充值开始时间必填';
            $this->cancel = true;
        }

        if (!$validTo = strval(Arr::get($queryPacket, 'endedAt'))) {
            $this->errBox[] = '充值结束时间必填';
            $this->cancel = true;
        }

        $parkID = self::getParkID($queryPacket);
        $operateID = intval(Arr::get($queryPacket, 'operateID'));
        $carType = intval(Arr::get($queryPacket, 'type'));
        $payChannel = intval(Arr::get($queryPacket, 'payChannel'));
        $chargeMethod = intval(Arr::get($queryPacket, 'payType'));
        $chargeNumber = abs(intval(Arr::get($queryPacket, 'offset')));
        $freeNumber = abs(intval(Arr::get($queryPacket, 'freeOffset')));
        $amount = abs(intval(Arr::get($queryPacket, 'amount')));
        $remark = strval(Arr::get($queryPacket, 'remark'));

        $rawBody = [
            'serviceCode' => 'payCarCardFee',
            'parkId' => $parkID,
            'userId' => $operateID,
            'userName' => $operateName,
            'cardId' => $cardID,
            'orderNo' => $orderCode,
            'carType' => $carType,
            'payChannel' => $payChannel,
            'chargeMethod' => $chargeMethod,
            'chargeNumber' => $chargeNumber,
            'amount' => $amount,
            'freeNumber' => $freeNumber,
            'validFrom' => $validFrom,
            'validTo' => $validTo,
            'createTime' => now()->toDateTimeString(),
            'remark' => $remark
        ];

        $this->injectData($rawBody);

        return $this;
    }

    public function getExtendedServiceList(array $queryPacket = []): self
    {
        $this->uri = 'api/wec/GetCarCardList';
        $this->name = '固定车列表';

        $parkID = self::getParkID($queryPacket);
        $pageSize = abs(intval(Arr::get($queryPacket, 'pageSize'))) ?: 15;
        $page = abs(intval(Arr::get($queryPacket, 'page'))) ?: 1;
        $carType = intval(Arr::get($queryPacket, 'type'));
        $sortField = intval(Arr::get($queryPacket, 'sortField'));
        $sortType = intval(Arr::get($queryPacket, 'sortType'));

        $rawBody = ['serviceCode' => 'getCarCardList', 'parkId' => $parkID, 'pageIndex' => $page, 'pageSize' => $pageSize, 'carType' => $carType, 'sortField' => $sortField, 'sortType' => $sortType];
        $this->injectData($rawBody);

        return $this;
    }

    public function getExtendedServiceOrderList(array $queryPacket = []): self
    {
        $this->uri = 'api/wec/GetCarCardChargeList';
        $this->name = '查询固定车充值信息';

        $parkID = self::getParkID($queryPacket);
        $pageSize = abs(intval(Arr::get($queryPacket, 'pageSize'))) ?: 15;
        $page = abs(intval(Arr::get($queryPacket, 'page'))) ?: 1;
        $plateNo = strval(Arr::get($queryPacket, 'carNo'));
        if (!$beginTime = strval(Arr::get($queryPacket, 'beganAt'))) {
            $this->errBox[] = '充值开始时间必填';
            $this->cancel = true;
        }

        if (!$endTime = strval(Arr::get($queryPacket, 'endedAt'))) {
            $this->errBox[] = '充值结束时间必填';
            $this->cancel = true;
        }

        $rawBody = ['serviceCode' => 'getCarCardList', 'parkId' => $parkID, 'pageIndex' => $page, 'pageSize' => $pageSize];
        $rawBody = array_merge($rawBody, compact('plateNo', 'beginTime', 'endTime'));
        $this->injectData($rawBody);

        return $this;
    }

    public function refundExtendedService(array $queryPacket = []): self
    {
        $this->uri = 'api/wec/RefundCarCardFee';
        $this->name = '固定车退款接口';

        if (!$cardID = Arr::get($queryPacket, 'cardID')) {
            $this->errBox[] = '卡片ID必填';
            $this->cancel = true;
        }

        if (!$operateName = Arr::get($queryPacket, 'operateName')) {
            $this->errBox[] = '操作人员名称必填';
            $this->cancel = true;
        }

        if (!$orderCode = Arr::get($queryPacket, 'orderCode')) {
            $this->errBox[] = '订单号必填';
            $this->cancel = true;
        }

        if (!$validFrom = strval(Arr::get($queryPacket, 'beganAt'))) {
            $this->errBox[] = '充值开始时间必填';
            $this->cancel = true;
        }

        if (!$validTo = strval(Arr::get($queryPacket, 'endedAt'))) {
            $this->errBox[] = '充值结束时间必填';
            $this->cancel = true;
        }

        $parkID = self::getParkID($queryPacket);
        $operateID = intval(Arr::get($queryPacket, 'operateID'));
        $carType = intval(Arr::get($queryPacket, 'type'));
        $payChannel = intval(Arr::get($queryPacket, 'payChannel'));
        $refundMethod = intval(Arr::get($queryPacket, 'refundType'));
        $refundNumber = abs(intval(Arr::get($queryPacket, 'offset')));
        $amount = abs(intval(Arr::get($queryPacket, 'amount')));
        $remark = strval(Arr::get($queryPacket, 'remark'));

        $rawBody = [
            'parkId' => $parkID,
            'serviceCode' => 'refundCarCardFee',
            'userId' => $operateID,
            'userName' => $operateName,
            'cardId' => $cardID,
            'orderNo' => $orderCode,
            'carType' => $carType,
            'payChannel' => $payChannel,
            'amount' => $amount,
            'refundMethod' => $refundMethod,
            'refundNumber' => $refundNumber,
            'validFrom' => $validFrom,
            'validTo' => $validTo,
            'remark' => $remark
        ];
        $this->injectData($rawBody);

        return $this;
    }

    public function auditExtendedService(array $queryPacket = []): self
    {
        $this->uri = 'api/wec/AuditCardRecharge';
        $this->name = '固定车审核';

        if (!$operateName = Arr::get($queryPacket, 'operateName')) {
            $this->errBox[] = '操作人员名称必填';
            $this->cancel = true;
        }

        $parkID = self::getParkID($queryPacket);
        $operateID = intval(Arr::get($queryPacket, 'operateID'));
        $auditType = intval(Arr::get($queryPacket, 'type'));
        $auditStatus = strval(Arr::get($queryPacket, 'status'));
        $auditRemark = strval(Arr::get($queryPacket, 'remark'));
        $cardID = strval(Arr::get($queryPacket, 'cardID'));
        $orderNo = strval(Arr::get($queryPacket, 'orderCode'));

        $rawBody = [
            'serviceCode' => 'auditCardRecharge',
            'parkId' => $parkID,
            'userId' => $operateID,
            'userName' => $operateName,
            'auditType' => $auditType,
            'auditStatus' => $auditStatus,
            'auditRemark' => $auditRemark
        ];

        if ($cardID) {
            $rawBody['cardId'] = $cardID;
        } elseif ($orderNo) {
            $rawBody['orderNo'] = $orderNo;
        } else {
            $this->cancel = true;
            $this->errBox[] = '订单号或卡号为空';
        }

        $this->injectData($rawBody);
        return $this;
    }
}
