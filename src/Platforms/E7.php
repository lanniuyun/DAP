<?php

namespace On3\DAP\Platforms;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use On3\DAP\Exceptions\InvalidArgumentException;
use On3\DAP\Exceptions\RequestFailedException;

class E7 extends Platform
{

    protected $port = 60009;
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
        $this->configValidator();
        $autoLogging && $this->injectLogObj();
    }

    protected function configValidator()
    {
        if (!$this->gateway) {
            throw new InvalidArgumentException('服务网关不可为空');
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
        // TODO: Implement formatResp() method.
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
    public function getParkList(array $queryPacket): self
    {
        $this->uri = 'Park/GetByCustom';
        $this->name = '停车场基础信息列表';

        $PageSize = intval(Arr::get($queryPacket, 'pageSize'));
        $CurrentPage = intval(Arr::get($queryPacket, 'page'));
        $OrderBy = strval(Arr::get($queryPacket, 'orderBy'));
        $OrderType = boolval(Arr::get($queryPacket, 'orderType'));
        $where = strval(Arr::get($queryPacket, 'where'));
        $Append = strval(Arr::get($queryPacket, 'append'));
        $TotalCount = intval(Arr::get($queryPacket, 'pageTotal'));

        $this->queryBody = compact('PageSize', 'CurrentPage', 'OrderBy', 'OrderType', 'where', 'Append', 'TotalCount');

        return $this;
    }

    /**
     * @param array $queryPacket
     * id string 车场ID
     * @return $this
     */
    public function getParkInfo(array $queryPacket): self
    {
        $this->uri = 'Park/Get/';
        $this->name = '停车场基础信息详情';
        $this->httpMethod = self::METHOD_GET;

        $parkID = Arr::get($queryPacket, 'id');

        if (!$parkID) {
            $this->cancel = true;
            $this->errBox[] = '车场ID不为空';
        }
        $this->uri .= $parkID;
        return $this;
    }
}
