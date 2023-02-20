<?php

namespace On3\DAP;

use Illuminate\Support\Arr;
use On3\DAP\Exceptions\InvalidArgumentException;
use On3\DAP\Platforms\AnJuBao;
use On3\DAP\Platforms\CQTelecom;
use On3\DAP\Platforms\E7;
use On3\DAP\Platforms\FreeView;
use On3\DAP\Platforms\JieShun;
use On3\DAP\Platforms\KeXiang;
use On3\DAP\Platforms\KeyTop;
use On3\DAP\Platforms\UniUbi\WT;
use On3\DAP\Platforms\UniUbi\WO;

class DAP
{
    protected $platform;

    private function __construct()
    {
    }

    /**
     * @param array $config Y 参数数组
     * ｜- cid Y
     * ｜- usr Y
     * ｜- psw Y
     * ｜- signKey Y
     * ｜- businesserCode Y
     * @param bool $dev
     * @return static
     */
    public static function jieShun(array $config, bool $dev = false, bool $loadingToken = true): self
    {
        $dap = new self;
        $dap->platform = new JieShun($config, $dev, $loadingToken);
        return $dap;
    }

    public static function freeView(array $config, bool $dev = false, bool $loadingToken = true): self
    {
        $dap = new self;
        $dap->platform = new FreeView($config, $dev, $loadingToken);
        return $dap;
    }

    public static function CQTelecom(array $config, bool $dev = false, bool $loadingToken = true): self
    {
        $dap = new self;
        $dap->platform = new CQTelecom($config, $dev, $loadingToken);
        return $dap;
    }

    public static function keyTop(array $config, bool $dev = false, bool $loadingToken = true): self
    {
        $dap = new self;
        $dap->platform = new KeyTop($config, $dev, $loadingToken);
        return $dap;
    }

    public static function anJuBao(array $config, bool $dev = false, bool $loadingToken = true): self
    {
        $dap = new self;
        $dap->platform = new AnJuBao($config, $dev, $loadingToken);
        return $dap;
    }

    static public function uniUbi(array $config, bool $dev = false, bool $loadingToken = true): self
    {
        $dap = new self;
        $ver = Arr::get($config, 'ver') ?: 'wo';
        if (strtolower($ver) === 'wt') {
            $platformClass = WT::class;
        } else {
            $platformClass = WO::class;
        }
        $dap->platform = new $platformClass($config, $dev, $loadingToken);
        return $dap;
    }

    static public function keXiang(array $config, bool $dev = false, bool $loadingToken = true): self
    {
        $dap = new self;
        $dap->platform = new KeXiang($config, $dev, $loadingToken);
        return $dap;
    }

    static public function E7(array $config, bool $dev = false, bool $loadingToken = true): self
    {
        $dap = new self;
        $dap->platform = new E7($config, $dev, $loadingToken);
        return $dap;
    }

    public function __call($name, $arguments)
    {
        if (in_array($name, ['post', 'get', 'put', 'delete']) && method_exists($this->platform, 'request')) {
            if (count($arguments) === 2) {
                $arguments[] = [];
            }

            if (count($arguments) === 3) {
                $arguments[] = __FUNCTION__;
            } else {
                throw new InvalidArgumentException('超出预期的参数');
            }
            return call_user_func_array([$this->platform, 'request'], $arguments);
        } elseif (method_exists($this->platform, $name)) {
            return call_user_func_array([$this->platform, $name], $arguments);
        }
        throw new \Exception('试图访问未定义的函数[' . $name . ']');
    }

    static public function jieShunInputsAdapter(\Closure $closure)
    {
        return JieShun::inputsAdapter($closure);
    }
}
