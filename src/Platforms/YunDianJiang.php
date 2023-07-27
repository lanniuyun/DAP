<?php

namespace On3\DAP\Platforms;

use Illuminate\Support\Arr;

class YunDianJiang extends Platform
{

    protected $appid;
    protected $secret;

    public function __construct(array $config, bool $dev = false, bool $loadingToken = true, bool $autoLogging = true)
    {
        if ($gateway = Arr::get($config, 'gateway')) {
            $this->gateway = $gateway;
        } else {
            $this->gateway = $dev ? Gateways::YUN_DIAN_JIANG_DEV : Gateways::YUN_DIAN_JIANG;
        }

        $this->configValidator();

        $autoLogging && $this->injectLogObj();
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
