<?php

namespace On3\DAP\Platforms;

use On3\DAP\Contracts\PlatformInterface;

abstract class Platform implements PlatformInterface
{
    const METHOD_GET = 'get';
    const METHOD_POST = 'post';

    protected $format = 'json';
    protected $httpMethod = self::METHOD_POST;
    protected $timeout = 5;
    protected $responseFormat = 'json';

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

    abstract protected function configValidator();

    abstract protected function generateSignature();

    abstract protected function formatResp(&$response);
}
