<?php

namespace On3\DAP\Exceptions;

class InvalidArgumentException extends \Exception
{
    public function __construct($message = "", $code = 0, \Throwable $previous = null)
    {
        $message = '参数错误:' . $message;
        parent::__construct($message, $code, $previous);
    }
}
