<?php

namespace On3\DAP\Platforms;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use On3\DAP\Exceptions\InvalidArgumentException;
use On3\DAP\Exceptions\RequestFailedException;
use On3\DAP\Traits\DAPHashTrait;

class YunDianJiang extends Platform
{

    protected $appid;
    protected $secret;
    protected $httpMethod = self::METHOD_GET;

    use DAPHashTrait;

    public function __construct(array $config, bool $dev = false, bool $loadingToken = true, bool $autoLogging = true)
    {
        if ($gateway = Arr::get($config, 'gateway')) {
            $this->gateway = $gateway;
        } else {
            $this->gateway = $dev ? Gateways::YUN_DIAN_JIANG_DEV : Gateways::YUN_DIAN_JIANG;
        }

        $this->appid = Arr::get($config, 'appid');
        $this->secret = Arr::get($config, 'secret');

        $this->configValidator();

        $autoLogging && $this->injectLogObj();
    }


    protected function configValidator()
    {
        if (!$this->gateway) {
            throw new InvalidArgumentException('接口网关必填');
        }

        if (!$this->appid) {
            throw new InvalidArgumentException('appid必填');
        }

        if (!$this->secret) {
            throw new InvalidArgumentException('secret必填');
        }
    }

    public function getDeviceStatus(array $queryPacket): self
    {
        $this->uri = 'info/realtime';
        $this->name = '获取充电站状态';

        if (!$csIDs = Arr::get($queryPacket, 'SNs')) {
            $this->cancel = true;
            $this->errBox[] = '设备SN不得为空';
        }

        if (is_string($csIDs) || is_integer($csIDs)) {
            $csIDs = array_filter(array_unique(explode(',', $csIDs)));
        } elseif (is_array($csIDs)) {
        } else {
            $this->cancel = true;
            $this->errBox[] = '设备SN不得为空';
        }

        $csIDs = implode(',', $csIDs);
        $this->fillQueryBody(['csid' => $csIDs]);

        return $this;
    }

    public function getDeviceList(array $queryPacket): self
    {
        $this->uri = 'info/get_charge_station_list';
        $this->name = '获取充电站列表';

        $page = intval(Arr::get($queryPacket, 'page')) ?: 1;
        $limit = intval(Arr::get($queryPacket, 'pageSize')) ?: 15;
        $order = strval(Arr::get($queryPacket, 'orderBy')) ?: 'asc';
        $offset = abs($page * $limit - 1);

        $this->fillQueryBody(compact('offset', 'limit', 'order'));

        return $this;
    }

    public function getChargeDetail(array $queryPacket): self
    {
        $this->uri = 'info/get_charge_unit';
        $this->name = '获取充电详情';

        if (!$cuIDs = Arr::get($queryPacket, 'cuIDs')) {
            $this->cancel = true;
            $this->errBox[] = '充电记录ID不得为空';
        }

        if (is_string($cuIDs) || is_integer($cuIDs)) {
            $cuIDs = array_filter(array_unique(explode(',', $cuIDs)));
        } elseif (is_array($cuIDs)) {
        } else {
            $this->cancel = true;
            $this->errBox[] = '充电记录ID不得为空';
        }

        $cuid = implode(',', $cuIDs);
        $this->fillQueryBody(compact('cuid'));

        return $this;
    }

    public function powerUp(array $queryPacket): self
    {
        $this->uri = 'action/power_up';
        $this->name = '上电';

        if (!$pid = Arr::get($queryPacket, 'pid')) {
            $this->cancel = true;
            $this->errBox[] = '插座ID不得为空';
        }

        $pipeArr = explode('_', $pid);

        if (count($pipeArr) !== 2) {
            $this->cancel = true;
            $this->errBox[] = '插座ID与拼接规则不符';
        }

        if (!$seconds = abs(intval(Arr::get($queryPacket, 'secs')))) {
            $this->cancel = true;
            $this->errBox[] = '充电时间不得为空';
        }

        $this->fillQueryBody(compact('pid', 'seconds'));

        return $this;
    }

    public function powerEdit(array $queryPacket): self
    {
        $this->uri = 'action/change_charge_seconds';
        $this->name = '修改充电时间';

        if (!$cuid = Arr::get($queryPacket, 'cuID')) {
            $this->cancel = true;
            $this->errBox[] = '充电记录ID不得为空';
        }

        if (!$seconds = abs(intval(Arr::get($queryPacket, 'secs')))) {
            $this->cancel = true;
            $this->errBox[] = '充电时间不得为空';
        }

        $this->fillQueryBody(compact('cuid', 'seconds'));

        return $this;
    }

    public function powerDown(array $queryPacket): self
    {
        $this->uri = 'action/power_down';
        $this->name = '断电';

        if (!$cuid = Arr::get($queryPacket, 'cuID')) {
            $this->cancel = true;
            $this->errBox[] = '充电记录ID不得为空';
        }

        $this->fillQueryBody(compact('cuid'));

        return $this;
    }

    protected function generateSignature()
    {
        // TODO: Implement generateSignature() method.
    }

    protected function fillQueryBody(array $queryBody)
    {
        $queryBody['tm'] = time();
        $queryBody['appid'] = $this->appid;
        $appendStr = $this->join2StrV2($queryBody);
        $appendStr .= $this->secret;
        $queryBody['sign'] = strtolower(md5($appendStr));
        $this->queryBody = $queryBody;
    }

    protected function formatResp(&$response)
    {
        $rawCode = Arr::get($response, 'code');
        $message = strval(Arr::get($response, 'info'));
        $data = Arr::get($response, 'data') ?: [];

        if ($rawCode == 0) {
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

            $uri = $this->uri . '?' . http_build_query($this->queryBody);

            try {
                $rawResponse = $httpClient->request($httpMethod, $uri);
                $contentStr = $rawResponse->getBody()->getContents();
                $contentArr = @json_decode($contentStr, true);
            } catch (\Throwable $exception) {
                $contentArr = $exception->getMessage();
            } finally {

                try {
                    $this->logging->info($this->name, ['gateway' => $this->gateway, 'name' => $this->name, 'queryBody' => $uri, 'response' => $contentArr]);
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
