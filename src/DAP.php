<?php

namespace On3\DAP;

use Illuminate\Support\Arr;
use On3\DAP\Platforms\AnJuBao;
use On3\DAP\Platforms\CQTelecom;
use On3\DAP\Platforms\FreeView;
use On3\DAP\Platforms\JieShun;
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
    public static function jieShun(array $config, bool $dev = false): self
    {
        $dap = new self;
        $dap->platform = new JieShun($config, $dev);
        return $dap;
    }

    public static function freeView(array $config, bool $dev = false): self
    {
        $dap = new self;
        $dap->platform = new FreeView($config, $dev);
        return $dap;
    }

    public static function CQTelecom(array $config, bool $dev = false): self
    {
        $dap = new self;
        $dap->platform = new CQTelecom($config, $dev);
        return $dap;
    }

    public static function keyTop(array $config, bool $dev = false): self
    {
        $dap = new self;
        $dap->platform = new KeyTop($config, $dev);
        return $dap;
    }

    public static function anJuBao(array $config, bool $dev = false): self
    {
        $dap = new self;
        $dap->platform = new AnJuBao($config, $dev);
        return $dap;
    }

    static public function uniUbi(array $config, bool $dev = false): self
    {
        $dap = new self;
        $ver = Arr::get($config, 'ver') ?: 'wo';
        if (strtolower($ver) === 'wt') {
            $platformClass = WT::class;
        } else {
            $platformClass = WO::class;
        }
        $dap->platform = new $platformClass($config, $dev);
        return $dap;
    }

    public function __call($name, $arguments)
    {
        if (method_exists($this->platform, $name)) {
            return call_user_func_array([$this->platform, $name], $arguments);
        }
        throw new \Exception('试图访问未定义的函数[' . $name . ']');
    }

    static public function jieShunInputsAdapter(\Closure $closure)
    {
        return JieShun::inputsAdapter($closure);
    }
}
