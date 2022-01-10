<?php

namespace On3\DAP\Traits;

trait DAPBaseTrait
{
    protected static function getCacheKey(): string
    {
        return 'DAP:' . __CLASS__ . ':token';
    }
}
