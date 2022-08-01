<?php

namespace On3\DAP\Traits;

trait DAPBaseTrait
{
    protected static function  getCacheKey(string $key = null): string
    {
        $cacheKey = 'DAP:' . __CLASS__ . ':token';
        $key && $cacheKey .= ':' . $key;
        return $cacheKey;
    }

    public function getLocalToken(string $key = null)
    {
        if (!$this->token && $key) {
            $this->token = cache($key);
        }
        return $this->token;
    }
}
