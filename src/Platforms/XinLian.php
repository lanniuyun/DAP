<?php

namespace On3\DAP\Platforms;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use On3\DAP\Exceptions\InvalidArgumentException;

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
        $this->queryBody = [
            'appid' => $this->appid,
            'versions' => '1.0',
            'data' => json_encode($queryData)
        ];
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
     * triggered_at
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

        if (!$entranceTime = Arr::get($queryPacket, 'triggered_at')) {
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

        $plateColorCode = Arr::get($queryPacket, 'car_no_Color');
        $balance = intval(Arr::get($queryPacket, 'balance'));

        if (is_integer($plateColorCode)) {
            $this->errBox[] = '车牌颜色编码为空';
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
        $remark = Arr::get($queryPacket, 'remark');
        $vehicleColor = intval(Arr::get($queryPacket, 'car_color'));
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
        // TODO: Implement formatResp() method.
    }

    public function cacheKey(): string
    {
        // TODO: Implement cacheKey() method.
    }

    public function fire()
    {
        // TODO: Implement fire() method.
    }
}
