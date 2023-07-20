<?php

namespace On3\DAP\Platforms;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

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

    public function upEnterCar(array $queryPacket)
    {
        if (!$transOrderNo = Arr::get($queryPacket, 'orderCode')) {
            $this->errBox[] = '停车单号为空';
            $this->cancel = true;
        }

        if (!$cardNo = Arr::get($queryPacket, 'cardNo')) {
            $this->errBox[] = '卡号(取车牌号)为空';
            $this->cancel = true;
        }

        if (!$laneNo = Arr::get($queryPacket, 'laneNo')) {
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

        if (!$plateNo = Arr::get($queryPacket, 'carNo')) {
            $this->errBox[] = '车牌号码为空';
            $this->cancel = true;
        }

        if (!$plateNo = Arr::get($queryPacket, 'carNo')) {
            $this->errBox[] = '车牌号码为空';
            $this->cancel = true;
        }

        $queryPacket = [
            'biz_id' => '',
            'waste_sn' => self::getWasteSn(),
            'params' => [

            ]
        ];
    }

    static function getWasteSn(): string
    {
        return intval(microtime(true) * 10000) . mt_rand(10000000, 99999999);
    }

    protected function configValidator()
    {
        // TODO: Implement configValidator() method.
    }

    protected function generateSignature()
    {
        // TODO: Implement generateSignature() method.
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
