<?php

namespace On3\DAP\Platforms;

use Illuminate\Support\Facades\Log;
use On3\DAP\Contracts\PlatformInterface;
use Psr\Log\LoggerInterface;

abstract class Platform implements PlatformInterface
{
    const METHOD_GET = 'get';
    const METHOD_POST = 'post';
    const METHOD_DELETE = 'delete';
    const METHOD_PUT = 'put';

    protected $httpMethod = self::METHOD_POST;
    protected $timeout = 3;
    protected $responseFormat = self::RESP_FMT_JSON;

    protected $logging;

    const RESP_FMT_JSON = 'json';
    const RESP_FMT_BODY = 'body';
    const RESP_FMT_RAW = 'raw';

    protected $gateway;
    protected $uri;
    protected $name;
    protected $queryBody = [];
    protected $cancel = false;
    protected $errBox = [];

    protected function cleanup()
    {
        $this->queryBody = [];
        $this->uri = '';
        $this->name = '';
        $this->httpMethod = self::METHOD_POST;
        $this->cancel = false;
        $this->errBox = [];
    }

    public function timeout($timeout)
    {
        $timeout = abs(intval($timeout));
        if ($timeout > $this->timeout) {
            $this->timeout = $timeout;
        }
        return $this;
    }

    public function setLogger(LoggerInterface $logger = null)
    {
        if ($logger) {
            $this->logging = $logger;
        } else {
            $this->logging = null;
        }
        return $this;
    }

    abstract protected function configValidator();

    abstract protected function generateSignature();

    abstract protected function formatResp(&$response);

    abstract public function cacheKey(): string;

    protected function injectLogObj()
    {
        $calledClass = get_called_class();
        $calledClassArr = explode('\\', $calledClass);
        $className = lcfirst(end($calledClassArr));

        $channels = config('logging.channels');
        $dapChannelName = 'dap_' . $className;
        $channels[$dapChannelName] = [
            'driver' => 'daily',
            'path' => storage_path("logs/dap/{$className}.log"),
            'level' => 'debug',
            'days' => 14];
        config(['logging.channels' => $channels]);
        $this->logging = Log::channel($dapChannelName);
    }
}
