<?php

namespace On3\DAP\Traits;

trait DAPBaseTrait
{
    protected static function getCacheKey(string $key = null): string
    {
        $cacheKey = 'DAP:' . __CLASS__ . ':token';
        $key && $cacheKey .= ':' . $key;
        return $cacheKey;
    }
}
