<?php

namespace On3\DAP;

use On3\DAP\Platforms\AnJuBao;
use On3\DAP\Platforms\FreeView;
use On3\DAP\Platforms\JieShun;
use On3\DAP\Platforms\KeyTop;

class DAP
{
    protected $platform;

    private function __construct()
    {
        //NOTHING TO DO
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
