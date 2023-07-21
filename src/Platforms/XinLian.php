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
