<?php

namespace On3\DAP\Traits;

trait DAPHashTrait
{
    /**
     * @param string $rawQueryStr
     * @param string $key
     * @return string
     */
    protected function hmacSha1(string $rawQueryStr, string $key): string
    {
        $SHA = hash_hmac("sha1", $rawQueryStr, $key, false);
        return md5(pack('H*', $SHA));
    }

    /**
     * @param array $queryPacket
     * @return string
     */
    protected function join2Str(array $queryPacket): string
    {
        $str = '';
        foreach ($queryPacket as $key => $value) {
            if (($value !== null) && ($value !== '')) {
                if (is_array($value)) {
                    $value = @json_encode($value);
                }
                $str && $str .= '&';
                $str .= $key . '=' . $value;
            }
        }
        return $str;
    }

    /**
     * @param array $queryPacket
     * @return string
     */
    protected function join2StrV1(array $queryPacket): string
    {
        $str = '';
        foreach ($queryPacket as $key => $value) {
            if (($value !== null) && ($value !== '')) {
                if (is_array($value)) {
                    continue;
                }
                $str && $str .= '&';
                $str .= $key . '=' . $value;
            }
        }
        return $str;
    }

    /**
     * @param array $queryPacket
     * @return string
     */
    protected function join2StrV2(array $queryPacket): string
    {
        $strAppend = '';
        ksort($queryPacket);
        foreach ($queryPacket as $key => $value) {

            if (is_array($value)) {
                continue;
            }

            if (!$value) {
                $value = strval($value);
            }

            $strAppend && $strAppend .= '&';
            $strAppend .= $key . '=' . $value;
        }
        return $strAppend;
    }
}
