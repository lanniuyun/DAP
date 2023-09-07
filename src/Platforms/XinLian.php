<?php

namespace On3\DAP\Platforms;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use On3\DAP\Exceptions\InvalidArgumentException;
use On3\DAP\Exceptions\RequestFailedException;

class XinLian extends Platform
{

    protected $appid;
    protected $privateKey;
    protected $publicKey;

    public function __construct(array $config, bool $dev = false, bool $loadingToken = true, bool $autoLogging = true)
    {
        if ($gateway = Arr::get($config, 'gateway')) {
            $this->gateway = $gateway;
        } else {
            $this->gateway = $dev ? Gateways::XIN_LIN_DEV : Gateways::XIN_LIN;
        }

        $this->appid = Arr::get($config, 'appid');
        $this->privateKey = Arr::get($config, 'private_key');
        $this->publicKey = Arr::get($config, 'public_key');
        $this->configValidator();

        $autoLogging && $this->injectLogObj();
    }

    protected function injectData(array $queryData)
    {
        if (!Arr::get($queryData, 'params.vehicle_color')) {
            unset($queryData['params']['vehicle_color']);
        }

        try {
            $jsonQueryDat = json_encode($queryData);
            $sign = self::generateSign($jsonQueryDat, $this->privateKey);
            $this->queryBody = [
                'appid' => $this->appid,
                'versions' => '1.0',
                'data' => json_encode($queryData),
                'sign' => $sign
            ];
        } catch (\Throwable $exception) {
            $this->cancel = true;
            $this->errBox[] = '生成签名失败';
        }
    }

    static public function generateSign(string $toBeEncryptedStr, string $privateKey): string
    {
        $signature = '';
        $privateKey = "-----BEGIN RSA PRIVATE KEY-----\n" . wordwrap($privateKey, 64, "\n", true) . "\n-----END RSA PRIVATE KEY-----";
        $toBeEncryptedStr = base64_encode($toBeEncryptedStr);
        $sslKey = openssl_get_privatekey($privateKey);
        openssl_sign($toBeEncryptedStr, $signature, $sslKey, "SHA256");
        openssl_free_key($sslKey);
        return base64_encode($signature);
    }

    /**
     * @param array $queryPacket
     *  order_code
     *  card_no
     * lane_no
     * entered_at
     * collector_name
     * collector_order
     * car_no
     * car_no_Color
     * balance
     * car_type
     * pass_type
     * parking_type
     * device_sn
     * remark
     * car_color
     * query_no_sense
     * @return $this
     */
    public function upEnterCar(array $queryPacket)
    {

        $this->name = '车辆入场';

        if (!$transOrderNo = Arr::get($queryPacket, 'order_code')) {
            $this->errBox[] = '停车单号为空';
            $this->cancel = true;
        }

        if (!$cardSn = Arr::get($queryPacket, 'card_no')) {
            $this->errBox[] = '卡号(取车牌号)为空';
            $this->cancel = true;
        }

        if (!$entranceNo = Arr::get($queryPacket, 'lane_no')) {
            $this->errBox[] = '入口参数[格式：入口编号+下划线(_)+入口名称]为空';
            $this->cancel = true;
        }

        if (!$entranceTime = Arr::get($queryPacket, 'entered_at')) {
            $entranceTime = now()->format('YmdHis');
        } else {
            try {
                $entranceTime = Carbon::parse($entranceTime)->format('YmdHis');
            } catch (\Throwable $exception) {
                $this->errBox[] = '入场时间(yyyyMMddHHmmss)格式错误';
                $this->cancel = true;
            }
        }

        if (!$collectorName = Arr::get($queryPacket, 'collector_name')) {
            $this->errBox[] = '收费员名称为空';
            $this->cancel = true;
        }

        if (!$collectorOrder = Arr::get($queryPacket, 'collector_order')) {
            $this->errBox[] = '收费员班次为空';
            $this->cancel = true;
        }

        if (!$plateNo = Arr::get($queryPacket, 'car_no')) {
            $this->errBox[] = '车牌号码为空';
            $this->cancel = true;
        }

        $plateColorCode = intval(Arr::get($queryPacket, 'car_no_Color'));
        $balance = intval(Arr::get($queryPacket, 'balance'));

        if (!$plateTypeCode = Arr::get($queryPacket, 'car_type')) {
            $this->errBox[] = '车辆类型为空';
            $this->cancel = true;
        }

        if (!$passMethod = Arr::get($queryPacket, 'pass_type')) {
            $this->errBox[] = '通行方式为空';
            $this->cancel = true;
        }

        if (!$parkingTypeCode = Arr::get($queryPacket, 'parking_type')) {
            $this->errBox[] = '停车类型为空';
            $this->cancel = true;
        }

        $deviceNo = Arr::get($queryPacket, 'device_sn');
        $remark = Arr::get($queryPacket, 'remark');
        $vehicleColor = Arr::get($queryPacket, 'car_color');
        $queryNoSense = intval(Arr::get($queryPacket, 'query_no_sense'));

        $queryPacket = [
            'biz_id' => 'etc.parking.enterinfo.sync',
            'waste_sn' => self::getWasteSn(),
            'params' => [
                'trans_order_no' => $transOrderNo,
                'card_sn' => $cardSn,
                'entrance_no' => $entranceNo,
                'entrance_time' => $entranceTime,
                'collector_name' => $collectorName,
                'collector_order' => $collectorOrder,
                'plate_no' => $plateNo,
                'plate_color_code' => $plateColorCode,
                'balance' => $balance,
                'plate_type_code' => $plateTypeCode,
                'pass_method' => $passMethod,
                'parking_type_code' => $parkingTypeCode,
                'device_no' => $deviceNo,
                'remark' => $remark,
                'vehicle_color' => $vehicleColor,
                'query_no_sense' => $queryNoSense
            ]
        ];

        $this->injectData($queryPacket);

        return $this;
    }

    public function upExitCar(array $queryPacket): self
    {
        $this->name = '车辆出场';

        if (!$transOrderNo = Arr::get($queryPacket, 'order_code')) {
            $this->errBox[] = '停车单号为空';
            $this->cancel = true;
        }

        if (!$parkRecordTime = Arr::get($queryPacket, 'record_time')) {
            $this->errBox[] = '停车时长为驶出停车场时刻与驶入停车场时刻的时间差.例如：3天1小时34分20秒';
            $this->cancel = true;
        }

        if (!$payMethodCode = intval(Arr::get($queryPacket, 'pay_type'))) {
            $this->errBox[] = '支付类型编码为空';
            $this->cancel = true;
        }

        if (!$cardSN = Arr::get($queryPacket, 'card_no')) {
            $this->errBox[] = '卡号（ETC支付时与卡物理号一致；非ETC时上传车牌号）为空';
            $this->cancel = true;
        }

        if (!$plateNo = Arr::get($queryPacket, 'car_no')) {
            $this->errBox[] = '车牌号码为空';
            $this->cancel = true;
        }

        $plateColorCode = intval(Arr::get($queryPacket, 'car_no_Color'));

        if (!$entranceTime = Arr::get($queryPacket, 'entered_at')) {
            $entranceTime = now()->format('YmdHis');
        } else {
            try {
                $entranceTime = Carbon::parse($entranceTime)->format('YmdHis');
            } catch (\Throwable $exception) {
                $this->errBox[] = '入场时间(yyyyMMddHHmmss)格式错误';
                $this->cancel = true;
            }
        }

        if (!$entranceNo = Arr::get($queryPacket, 'lane_no')) {
            $this->errBox[] = '入口参数[格式：入口编号+下划线(_)+入口名称]为空';
            $this->cancel = true;
        }

        if (!$exitTime = Arr::get($queryPacket, 'exited_at')) {
            $exitTime = now()->format('YmdHis');
        } else {
            try {
                $exitTime = Carbon::parse($exitTime)->format('YmdHis');
            } catch (\Throwable $exception) {
                $this->errBox[] = '出场时间(yyyyMMddHHmmss)格式错误';
                $this->cancel = true;
            }
        }

        if (!$exitNo = Arr::get($queryPacket, 'exit_lane_no')) {
            $this->errBox[] = '出口参数[格式：出口编号+下划线(_)+出口名称]为空';
            $this->cancel = true;
        }

        $specialInfo = strval(Arr::get($queryPacket, 'special_info'));

        $receivableTotalAmount = intval(Arr::get($queryPacket, 'receivable_total_amount'));
        $discountAmount = intval(Arr::get($queryPacket, 'discount_amount'));
        $actualIncomeAmount = intval(Arr::get($queryPacket, 'actual_income_amount'));

        if ($list1 = Arr::get($queryPacket, 'discount_list') ?: []) {

            if (!$list1['discount_type'] = intval(Arr::get($list1, 'discount_type'))) {
                $this->errBox[] = '优惠类型为空';
                $this->cancel = true;
            }

            if (!$list1['discount_serial_no'] = Arr::get($list1, 'discount_serial_no')) {
                $this->errBox[] = '优惠流水号为空';
                $this->cancel = true;
            }

            $list1['discount_detail_amount'] = intval(Arr::get($list1, 'discount_detail_amount'));

            if (!$list1['pay_company'] = strval(Arr::get($list1, 'pay_company'))) {
                $this->errBox[] = '出资单位为空';
                $this->cancel = true;
            }

            if (Arr::has($queryPacket, 'discount_detail')) {
                $list1['discount_detail'] = strval(Arr::get($list1, 'discount_detail'));
            }

            if (!$list1['discount_no'] = strval(Arr::get($list1, 'discount_no'))) {
                $this->errBox[] = '优惠券标识为空';
                $this->cancel = true;
            }
        }

        if ($list2 = Arr::get($queryPacket, 'payment_list') ?: []) {
            if (!$list2['pay_type'] = intval(Arr::get($list2, 'pay_type'))) {
                $this->errBox[] = '支付类型为空';
                $this->cancel = true;
            }

            if (!$list2['pay_serial_no'] = Arr::get($list2, 'pay_serial_no')) {
                $this->errBox[] = '支付流水号为空';
                $this->cancel = true;
            }

            $list2['pay_amount'] = intval(Arr::get($list2, 'pay_amount'));
        }

        $discountNum = count($list1);
        $payNum = count($list2);

        if (!$collectorName = Arr::get($queryPacket, 'collector_name')) {
            $this->errBox[] = '收费员名称为空';
            $this->cancel = true;
        }

        if (!$collectorOrder = Arr::get($queryPacket, 'collector_order')) {
            $this->errBox[] = '收费员班次为空';
            $this->cancel = true;
        }

        if (!$plateTypeCode = Arr::get($queryPacket, 'car_type')) {
            $this->errBox[] = '车辆类型为空';
            $this->cancel = true;
        }

        if (!$passMethod = Arr::get($queryPacket, 'pass_type')) {
            $this->errBox[] = '通行方式为空';
            $this->cancel = true;
        }

        if (!$parkingTypeCode = Arr::get($queryPacket, 'parking_type')) {
            $this->errBox[] = '停车类型为空';
            $this->cancel = true;
        }

        $deviceNo = Arr::get($queryPacket, 'device_sn');

        $queryPacket = [
            'biz_id' => 'etc.parking.exitinfo.sync',
            'waste_sn' => self::getWasteSn(),
            'params' => [
                'trans_order_no' => $transOrderNo,
                'park_record_time' => $parkRecordTime,
                'pay_method_code' => $payMethodCode,
                'card_sn' => $cardSN,
                'plate_no' => $plateNo,
                'plate_color_code' => $plateColorCode,
                'entrance_no' => $entranceNo,
                'entrance_time' => $entranceTime,
                'exit_time' => $exitTime,
                'exit_no' => $exitNo,
                'special_info' => $specialInfo,
                'receivable_total_amount' => $receivableTotalAmount,
                'discount_amount' => $discountAmount,
                'actual_income_amount' => $actualIncomeAmount,
                'discount_num' => $discountNum,
                'list1' => $list1,
                'pay_num' => $payNum,
                'list2' => $list2,
                'collector_name' => $collectorName,
                'collector_order' => $collectorOrder,
                'plate_type_code' => $plateTypeCode,
                'pass_method' => $passMethod,
                'parking_type_code' => $parkingTypeCode,
                'device_no' => $deviceNo
            ]
        ];

        $this->injectData($queryPacket);

        return $this;
    }

    public function upETCParkingOrder(array $queryPacket): self
    {
        $this->name = 'ETC无感-交易上传';

        if (!$transOrderNo = Arr::get($queryPacket, 'order_code')) {
            $this->errBox[] = '停车单号为空';
            $this->cancel = true;
        }

        if (!$paySerialNo = Arr::get($queryPacket, 'pay_serial_no')) {
            $this->errBox[] = '支付流水号：商户号+支付流水号保证唯一；出场时需要填写该支付流水号为空';
            $this->cancel = true;
        }

        if (!$plateNo = Arr::get($queryPacket, 'car_no')) {
            $this->errBox[] = '车牌号码为空';
            $this->cancel = true;
        }

        $plateColorCode = intval(Arr::get($queryPacket, 'car_no_Color'));
        $plateTypeCode = Arr::get($queryPacket, 'car_type');
        $amount = intval(Arr::get($queryPacket, 'amount'));

        if (!$entranceTime = Arr::get($queryPacket, 'entered_at')) {
            $entranceTime = now()->format('YmdHis');
        } else {
            try {
                $entranceTime = Carbon::parse($entranceTime)->format('YmdHis');
            } catch (\Throwable $exception) {
                $this->errBox[] = '入场时间(yyyyMMddHHmmss)格式错误';
                $this->cancel = true;
            }
        }

        if (!$exitTime = Arr::get($queryPacket, 'exited_at')) {
            $exitTime = now()->format('YmdHis');
        } else {
            try {
                $exitTime = Carbon::parse($exitTime)->format('YmdHis');
            } catch (\Throwable $exception) {
                $this->errBox[] = '出场时间(yyyyMMddHHmmss)格式错误';
                $this->cancel = true;
            }
        }

        if (!$parkRecordTime = Arr::get($queryPacket, 'record_time')) {
            $this->errBox[] = '停车时长为驶出停车场时刻与驶入停车场时刻的时间差.例如：3天1小时34分20秒';
            $this->cancel = true;
        }

        $vehicleColor = Arr::get($queryPacket, 'car_color');

        $queryPacket = [
            'biz_id' => 'etc.parking.pay.apply',
            'waste_sn' => self::getWasteSn(),
            'params' => [
                'trans_order_no' => $transOrderNo,
                'pay_type' => 1,
                'pay_serial_no' => $paySerialNo,
                'plate_no' => $plateNo,
                'park_record_time' => $parkRecordTime,
                'plate_color_code' => $plateColorCode,
                'plate_type_code' => $plateTypeCode,
                'amount' => $amount,
                'entrance_time' => $entranceTime,
                'exit_time' => $exitTime,
                'vehicle_color' => $vehicleColor
            ]
        ];

        $this->injectData($queryPacket);

        return $this;
    }

    public function queryETCParkingOrder(array $queryPacket): self
    {
        $this->name = '无感支付扣费列表查询';

        $plateNo = Arr::get($queryPacket, 'car_no');
        $plateColor = intval(Arr::get($queryPacket, 'car_no_Color'));
        $cardNo = Arr::get($queryPacket, 'card_no');
        $pageNo = min(intval(Arr::get($queryPacket, 'page')), 1);
        $pageSize = min(intval(Arr::get($queryPacket, 'page_size')), 10);

        if ($startTime = Arr::get($queryPacket, 'start_time')) {
            try {
                $startTime = Carbon::parse($startTime)->toDateTimeString();
            } catch (\Throwable $exception) {
                $this->errBox[] = '时间(yyyy-MM-dd HH:mm:ss)格式错误';
                $this->cancel = true;
            }
        }

        if ($endTime = Arr::get($queryPacket, 'end_time')) {
            try {
                $endTime = Carbon::parse($endTime)->toDateTimeString();
            } catch (\Throwable $exception) {
                $this->errBox[] = '时间(yyyy-MM-dd HH:mm:ss)格式错误';
                $this->cancel = true;
            }
        }

        $queryPacket = [
            'biz_id' => 'etc.parking.payment.query.order',
            'waste_sn' => self::getWasteSn(),
            'params' => [
                'plate_no' => $plateNo,
                'plate_color' => $plateColor,
                'card_no' => $cardNo,
                'pay_type' => 1,
                'page_no' => $pageNo,
                'page_size' => $pageSize,
                'start_time' => $startTime,
                'end_time' => $endTime
            ]
        ];

        $this->injectData($queryPacket);

        return $this;
    }

    public function isETCParkingWhiteCheck(array $queryPacket): self
    {
        $this->name = 'ETC无感-白名单查询';

        if (!$plateNo = Arr::get($queryPacket, 'car_no')) {
            $this->errBox[] = '车牌号码为空';
            $this->cancel = true;
        }

        $plateColorCode = intval(Arr::get($queryPacket, 'car_no_Color'));
        $amount = intval(Arr::get($queryPacket, 'amount'));
        $vehicleColor = Arr::get($queryPacket, 'car_color');

        $queryPacket = [
            'biz_id' => 'etc.parking.white.check',
            'waste_sn' => self::getWasteSn(),
            'params' => [
                'plate_no' => $plateNo,
                'plate_color_code' => $plateColorCode,
                'pay_type' => 1,
                'amount' => $amount,
                'vehicle_color' => $vehicleColor,
            ]
        ];

        $this->injectData($queryPacket);

        return $this;
    }

    public function upETCParkingOrderAttachment(array $queryPacket): self
    {
        $this->name = '上传订单附件';

        if (!$paySerialNo = Arr::get($queryPacket, 'pay_serial_no')) {
            $this->errBox[] = '交易流水号必填';
            $this->cancel = true;
        }
        $antennaLocation = Arr::get($queryPacket, 'antenna_location');
        $parkingLocation = Arr::get($queryPacket, 'parking_location');
        $entrancePic = Arr::get($queryPacket, 'entered_pic');
        $exitPic = Arr::get($queryPacket, 'exited_pic');

        $queryPacket = [
            'biz_id' => 'etc.parking.etctrans.otherinfo',
            'waste_sn' => self::getWasteSn(),
            'params' => [
                'pay_serial_no' => $paySerialNo,
                'antenna_location' => $antennaLocation,
                'parking_location' => $parkingLocation,
                'entrance_pic' => $entrancePic,
                'exit_pic' => $exitPic,
            ]
        ];

        $this->injectData($queryPacket);
        return $this;
    }

    static function getWasteSn(): string
    {
        return intval(microtime(true) * 10000) . mt_rand(10000000, 99999999);
    }

    protected function configValidator()
    {
        if (!$this->gateway) {
            throw new InvalidArgumentException('网关地址没有配置');
        }

        if (!$this->appid) {
            throw new InvalidArgumentException('appID必填');
        }

        if (!$this->privateKey) {
            throw new InvalidArgumentException('私钥必填');
        }
    }

    protected function generateSignature()
    {

    }

    protected function formatResp(&$response)
    {
        $jsonResponse = Arr::get($response, 'response') ?: [];
        $rawCode = Arr::get($jsonResponse, 'code');
        $message = Arr::get($jsonResponse, 'message');
        $data = Arr::get($jsonResponse, 'data') ?: [];

        if ($rawCode === '000000') {
            $resPacket = ['code' => 0, 'msg' => $message, 'data' => $data, 'raw_resp' => $response];
        } else {
            $resPacket = ['code' => $rawCode, 'msg' => $message, 'data' => $data, 'raw_resp' => $response];
        }

        $response = $resPacket;
    }

    public function cacheKey(): string
    {
        // TODO: Implement cacheKey() method.
    }

    public function fire()
    {
        $httpMethod = $this->httpMethod;
        $httpClient = new Client(['base_uri' => $this->gateway, 'timeout' => $this->timeout, 'verify' => false]);

        if ($this->cancel) {
            if (is_array($errBox = $this->errBox)) {
                $errMsg = implode(',', $errBox);
            } else {
                $errMsg = '未知错误';
            }
            $this->cleanup();
            throw new InvalidArgumentException($errMsg);
        } else {
            $exception = $rawResponse = $contentStr = null;
            try {
                $rawResponse = $httpClient->request($httpMethod, '', ['form_params' => $this->queryBody]);
                $contentStr = $rawResponse->getBody()->getContents();
                $contentArr = @json_decode($contentStr, true);
            } catch (\Throwable $exception) {
                $contentArr = $exception->getMessage();
            } finally {
                try {
                    $this->logging->info($this->name, ['gateway' => $this->gateway, 'name' => $this->name, 'queryBody' => $this->queryBody, 'response' => $contentArr]);
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
