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

    const CAR_TYPE_MONTHLY_CAR_A = '3652';
    const CAR_TYPE_TEMP_CAR_A = '3651';
    const CAR_TYPE_FREE_CAR = '3656';
    const CAR_TYPE_BALANCE_CAR = '3657';
    const CAR_TYPE_BUSINESS_CAR = '3656';

    const CAR_NO_COLOR_BLUE = '1';
    const CAR_NO_COLOR_YELLOW = '2';
    const CAR_NO_COLOR_GREEN = '3';
    const CAR_NO_COLOR_BLACK = '4';
    const CAR_NO_COLOR_WHITE = '5';
    const CAR_NO_COLOR_NONE = '6';

    const PAY_TYPE_CASH = '79001';
    const PAY_TYPE_WECHAT = '79003';
    const PAY_TYPE_ALI = '79007';
    const PAY_TYPE_WECHAT_OFFLINE = '79010';
    const PAY_TYPE_ALI_OFFLINE = '79011';

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
        $dataPacket['beginTime'] = Arr::get($dataPacket, 'startTime');
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
        $dataPacket['beginTime'] = Arr::get($dataPacket, 'startTime');
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
        $dataPacket['beginTime'] = Arr::get($dataPacket, 'startTime');
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
        $dataPacket['beginTime'] = Arr::get($dataPacket, 'startTime');
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
        $dataPacket['beginTime'] = Arr::get($dataPacket, 'startTime');
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

    /**
     * @param array $queryPacket
     *  - laneNo
     *  - act
     * @return $this
     */
    public function remoteControl(array $queryPacket = []): self
    {

        if (Arr::get($queryPacket, 'act')) {
            $this->uri = 'Inform/OpenGate';
            $this->name = '远程开闸';
        } else {
            $this->uri = 'Inform/CloseGate';
            $this->name = '远程关闸';
        }

        if (!$laneNo = Arr::get($queryPacket, 'laneNo')) {
            $this->errBox[] = '车道号必填';
            $this->cancel = true;
        }

        $dataPacket['passwayNo'] = $laneNo;
        $this->injectData($dataPacket);

        return $this;
    }

    /**
     * @param array $queryPacket
     *  - carNo
     *  - carWNo
     *  - orderCode
     *  - carType
     * @return $this
     */
    public function updateCarInfo(array $queryPacket = []): self
    {
        $this->uri = 'Inform/UpdateCar';
        $this->name = '修改场内车牌号或车辆类型';

        if (!$carNo = Arr::get($queryPacket, 'carNo')) {
            $this->cancel = true;
            $this->errBox[] = '车牌必填';
        }

        if (!$orderNo = Arr::get($queryPacket, 'orderCode')) {
            $this->cancel = true;
            $this->errBox[] = '订单编号必填';
        }

        if (!$carTypeNo = Arr::get($queryPacket, 'carType')) {
            $this->cancel = true;
            $this->errBox[] = '订单编号必填';
        }

        $carWNo = strval(Arr::get($queryPacket, 'carWNo'));
        $this->injectData(compact('carNo', 'orderNo', 'carTypeNo', 'carWNo'));

        return $this;
    }

    /**
     * @param array $queryPacket
     *  - carNo
     *  - startTime
     *  - endTime
     *  - act
     *  - orderCode
     * @return $this
     */
    public function actReservationCar(array $queryPacket = []): self
    {

        if (Arr::get($queryPacket, 'act')) {
            $this->uri = 'Inform/Reservation';
            $this->name = '增加访客预约车辆';

            if (!$carNo = Arr::get($queryPacket, 'carNo')) {
                $this->cancel = true;
                $this->errBox[] = '车牌必填';
            }

            $arriveTime = Arr::get($queryPacket, 'startTime');
            $arriveEndTime = Arr::get($queryPacket, 'endTime');

            try {
                if ($arriveTime) {
                    $arriveTime = Carbon::parse($arriveTime)->toDateTimeString();
                } else {
                    throw new \Exception('null_date');
                }
            } catch (\Throwable $exception) {
                $this->cancel = true;
                $this->errBox[] = '预约入场时间必填';
            }

            try {
                if ($arriveEndTime) {
                    $arriveEndTime = Carbon::parse($arriveEndTime)->toDateTimeString();
                } else {
                    throw new \Exception('null_date');
                }
            } catch (\Throwable $exception) {
                $this->cancel = true;
                $this->errBox[] = '预约离场时间必填';
            }

            $dataPacket = compact('carNo', 'arriveTime', 'arriveEndTime');
        } else {

            $this->uri = 'Inform/CancelReservation';
            $this->name = '注销访客预约车辆';

            if (!$orderNo = Arr::get($queryPacket, 'orderCode')) {
                $this->cancel = true;
                $this->errBox[] = '预约单号必填';
            }

            $dataPacket = compact('orderNo');
        }

        $this->injectData($dataPacket);

        return $this;
    }

    /**
     * @param array $queryPacket
     *  - carNo
     *  - startTime
     *  - endTime
     *  - act
     *  - orderCode
     * @return $this
     */
    public function actBusinessCar(array $queryPacket = []): self
    {
        if (Arr::get($queryPacket, 'act')) {
            $this->uri = 'Inform/AddBusiness';
            $this->name = '新增商家车辆';

            if (!$carNo = Arr::get($queryPacket, 'carNo')) {
                $this->cancel = true;
                $this->errBox[] = '车牌必填';
            }

            $beginTime = Arr::get($queryPacket, 'startTime');
            $endTime = Arr::get($queryPacket, 'endTime');

            try {
                if ($beginTime) {
                    $beginTime = Carbon::parse($beginTime)->toDateTimeString();
                } else {
                    throw new \Exception('null_date');
                }
            } catch (\Throwable $exception) {
                $this->cancel = true;
                $this->errBox[] = '开始时间必填';
            }

            try {
                if ($endTime) {
                    $endTime = Carbon::parse($endTime)->toDateTimeString();
                } else {
                    throw new \Exception('null_date');
                }
            } catch (\Throwable $exception) {
                $this->cancel = true;
                $this->errBox[] = '结束时间必填';
            }

            $dataPacket = compact('carNo', 'beginTime', 'endTime');
        } else {
            $this->uri = 'Inform/FailBusiness';
            $this->name = '注销商家车辆';

            if (!$orderNo = Arr::get($queryPacket, 'orderCode')) {
                $this->cancel = true;
                $this->errBox[] = '预约单号必填';
            }

            $dataPacket = compact('orderNo');
        }

        $this->injectData($dataPacket);

        return $this;
    }

    /**
     * @param array $queryPacket
     *  - carNo
     *  - startTime
     *  - endTime
     *  - phone
     *  - act
     *  - remark
     *  - userName
     *  - phone
     * @return $this
     */

    public function actCarBlackList(array $queryPacket = []): self
    {

        if (!$carNo = Arr::get($queryPacket, 'carNo')) {
            $this->cancel = true;
            $this->errBox[] = '车牌必填';
        }

        if (Arr::get($queryPacket, 'act')) {
            $this->uri = 'Inform/AddCarBlack';
            $this->name = '新增黑名单车辆';

            if (!$phone = Arr::get($queryPacket, 'phone')) {
                $this->cancel = true;
                $this->errBox[] = '手机号必填';
            }

            if (!$userName = Arr::get($queryPacket, 'userName')) {
                $this->cancel = true;
                $this->errBox[] = '联系人必填';
            }

            if (!$remark = Arr::get($queryPacket, 'remark')) {
                $this->cancel = true;
                $this->errBox[] = '备注必填';
            }

            $beginTime = Arr::get($queryPacket, 'startTime');
            $endTime = Arr::get($queryPacket, 'endTime');

            try {
                if ($beginTime) {
                    $beginTime = Carbon::parse($beginTime)->toDateTimeString();
                } else {
                    throw new \Exception('null_date');
                }
            } catch (\Throwable $exception) {
                $this->cancel = true;
                $this->errBox[] = '开始时间必填';
            }

            try {
                if ($endTime) {
                    $endTime = Carbon::parse($endTime)->toDateTimeString();
                } else {
                    throw new \Exception('null_date');
                }
            } catch (\Throwable $exception) {
                $this->cancel = true;
                $this->errBox[] = '结束时间必填';
            }

            $dataPacket = compact('carNo', 'phone', 'userName', 'beginTime', 'endTime', 'remark');
        } else {
            $this->uri = 'Inform/FailCarBlack';
            $this->name = '注销黑名单车辆';

            $dataPacket = compact('carNo');
        }

        $this->injectData($dataPacket);

        return $this;
    }

    /**
     * @param array $queryPacket
     *  - userName
     *  - carType
     *  - sex
     *  - address
     *  - phone
     *  - parkingSpaces
     * @return $this
     */
    public function regCarUser(array $queryPacket = []): self
    {
        $this->uri = 'Inform/AddPerson';
        $this->name = '新增月租车用户';

        if (!$carTypeNo = Arr::get($queryPacket, 'carType')) {
            $this->cancel = true;
            $this->errBox[] = '车牌类型编码必填';
        }

        if (!$userName = Arr::get($queryPacket, 'userName')) {
            $this->cancel = true;
            $this->errBox[] = '用户名必填';
        }

        $sex = intval(Arr::get($queryPacket, 'sex'));

        if (!$homeAddress = Arr::get($queryPacket, 'address')) {
            $this->cancel = true;
            $this->errBox[] = '地址必填';
        }

        if (!$mobNumber = Arr::get($queryPacket, 'phone')) {
            $this->cancel = true;
            $this->errBox[] = '手机必填';
        }

        $carSpalcesNum = intval(Arr::get($queryPacket, 'parkingSpaces'));
        $this->injectData(compact('carTypeNo', 'sex', 'userName', 'homeAddress', 'mobNumber', 'carSpalcesNum'));

        return $this;
    }

    public function actMonthlyCar(array $queryPacket = []): self
    {

        if (!$carNo = Arr::get($queryPacket, 'carNo')) {
            $this->cancel = true;
            $this->errBox[] = '车牌必填';
        }

        switch (Arr::get($queryPacket, 'act')) {
            case 0:
                $this->uri = 'Inform/FailMonthCar';
                $this->name = '注销月租车辆';
                $dataPacket = compact('carNo');
                break;
            case 1:

                $this->uri = 'Inform/AddMthCar';
                $this->name = '新增月租车绑定用户';

                $beginTime = Arr::get($queryPacket, 'startTime');
                $endTime = Arr::get($queryPacket, 'endTime');

                if (!$carType = Arr::get($queryPacket, 'carType')) {
                    $this->cancel = true;
                    $this->errBox[] = '车牌类型编码必填';
                }

                if (!$userNo = Arr::get($queryPacket, 'userID')) {
                    $this->cancel = true;
                    $this->errBox[] = '用户编号必填';
                }

                try {
                    if ($beginTime) {
                        $beginTime = Carbon::parse($beginTime)->toDateTimeString();
                    } else {
                        throw new \Exception('null_date');
                    }
                } catch (\Throwable $exception) {
                    $this->cancel = true;
                    $this->errBox[] = '开始时间必填';
                }

                try {
                    if ($endTime) {
                        $endTime = Carbon::parse($endTime)->toDateTimeString();
                    } else {
                        throw new \Exception('null_date');
                    }
                } catch (\Throwable $exception) {
                    $this->cancel = true;
                    $this->errBox[] = '结束时间必填';
                }

                $chargePayedMoney = strval(floatval(Arr::get($queryPacket, 'amount')));
                $chargeMoney = strval(floatval(Arr::get($queryPacket, 'paid_amount')));

                $dataPacket = compact('carNo', 'carType', 'userNo', 'beginTime', 'endTime', 'chargePayedMoney', 'chargeMoney');
                break;
            case 2:
            default:

                $this->uri = 'Inform/ChargeMonthCar';
                $this->name = '月租车延期续费';

                $beginTime = Arr::get($queryPacket, 'startTime');
                $endTime = Arr::get($queryPacket, 'endTime');
                $chargeTime = Arr::get($queryPacket, 'paidTime');

                try {
                    if ($beginTime) {
                        $beginTime = Carbon::parse($beginTime)->toDateTimeString();
                    } else {
                        throw new \Exception('null_date');
                    }
                } catch (\Throwable $exception) {
                    $this->cancel = true;
                    $this->errBox[] = '开始时间必填';
                }

                try {
                    if ($endTime) {
                        $endTime = Carbon::parse($endTime)->toDateTimeString();
                    } else {
                        throw new \Exception('null_date');
                    }
                } catch (\Throwable $exception) {
                    $this->cancel = true;
                    $this->errBox[] = '结束时间必填';
                }

                try {
                    if ($chargeTime) {
                        $chargeTime = Carbon::parse($chargeTime)->toDateTimeString();
                    } else {
                        throw new \Exception('null_date');
                    }
                } catch (\Throwable $exception) {
                    $this->cancel = true;
                    $this->errBox[] = '订单时间必填';
                }

                $chargePayedMoney = strval(floatval(Arr::get($queryPacket, 'amount')));
                $chargeMoney = strval(floatval(Arr::get($queryPacket, 'paidAmount')));

                $dataPacket = compact('carNo', 'beginTime', 'endTime', 'chargeMoney', 'chargePayedMoney', 'chargeTime');
                break;
        }

        $this->injectData($dataPacket);
        return $this;
    }

    /**
     * @param array $queryPacket
     *  - orderCode
     *  - paidAt
     *  - paidAmount
     * @return $this
     */
    public function notifyOrderPaid(array $queryPacket = []): self
    {
        $this->uri = 'Inform/PayOrderNotice';
        $this->name = '缴费通知';

        if (!$orderNo = Arr::get($queryPacket, 'orderCode')) {
            $this->cancel = true;
            $this->errBox[] = '车场订单号必填';
        }

        $payTime = Arr::get($queryPacket, 'paidAt');

        try {
            if ($payTime) {
                $payTime = Carbon::parse($payTime)->toDateTimeString();
            } else {
                throw new \Exception('null_date');
            }
        } catch (\Throwable $exception) {
            $this->cancel = true;
            $this->errBox[] = '时间必填';
        }

        $payAmt = strval(floatval(Arr::get($queryPacket, 'paidAmount')));
        $this->injectData(compact('orderNo', 'payTime', 'payAmt'));

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
            $startTime = '';
        }

        try {
            if ($endTime = Arr::get($queryData, 'endTime')) {
                $endTime = Carbon::parse($endTime)->toDateTimeString();
            } else {
                throw new \Exception('null_date');
            }
        } catch (\Throwable $exception) {
            $endTime = '';
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
        $success = boolval(Arr::get($response, 'success'));
        $errcode = intval(Arr::get($response, 'errcode'));
        $errmsg = strval(Arr::get($response, 'errmsg'));
        $parkingID = strval(Arr::get($response, 'parkno'));
        $data = strval(Arr::get($response, 'data'));
        $data = @json_decode($data, true) ?: [];

        if ($success === true || $errcode === 200) {
            $resPacket = ['code' => 0, 'msg' => $errmsg, 'data' => $data, 'raw_resp' => $response, 'parkingID' => $parkingID];
        } else {
            $resPacket = ['code' => $errcode ?: 1, 'msg' => $errmsg, 'data' => $data, 'raw_resp' => $response, 'parkingID' => $parkingID];
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
