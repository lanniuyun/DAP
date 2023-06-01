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

    public function getEnterCar(array $queryPacket = []): self
    {
        $this->uri = 'Inquire/GetEnterCar';
        $this->name = '查询场内记录';

        $carNo = strval(Arr::get($queryPacket, 'carNo'));

        try {
            if ($startTime = Arr::get($queryPacket, 'startTime')) {
                $startTime = Carbon::parse($startTime)->toDateTimeString();
            } else {
                throw new \Exception('null_date');
            }
        } catch (\Throwable $exception) {
            $startTime = now()->startOfDay()->toDateTimeString();
        }

        try {
            if ($endTime = Arr::get($queryPacket, 'endTime')) {
                $endTime = Carbon::parse($endTime)->toDateTimeString();
            } else {
                throw new \Exception('null_date');
            }
        } catch (\Throwable $exception) {
            $endTime = now()->endOfDay()->toDateTimeString();
        }

        $indexId = intval(Arr::get($queryPacket, 'pageIndex'));
        $size = intval(Arr::get($queryPacket, 'pageSize')) ?: 20;

        $dataPacket = compact('carNo', 'startTime', 'endTime', 'indexId', 'size');
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

    protected function generateSignature(): string
    {
        $queryBody = $this->queryBody;
        $queryBody['timestamp'] = Carbon::parse($queryBody['timestamp'])->timestamp * 1000;
        $strAppend = $this->join2StrV2($queryBody);
        $strAppend .= '&key=' . $this->appSecret;
        Log::info($strAppend);
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
