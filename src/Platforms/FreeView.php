<?php

namespace On3\DAP\Platforms;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use On3\DAP\Exceptions\InvalidArgumentException;
use On3\DAP\Traits\DAPBaseTrait;

class FreeView extends Platform
{

    use DAPBaseTrait;

    protected $username;
    protected $password;
    protected $uuid;
    protected $TenantCode;
    protected $gateway;
    protected $token;
    protected $port = 21664;
    protected $headers;
    protected $timeout = 5;

    const GET_TOKEN_URI = 'accesstoken';
    const ACCESS_CARDS_URI = 'GeneralCards';
    const BODY_FEATURES_URI = 'GeneralHumanFeaturesWithTesting';
    const BODY_FEATURES_DEL_URI = 'GeneralHumanFeatures';
    const REMOTE_UNLOCK_DOOR_URI = 'RemoteUnlock/GeneralOpenDoor';

    public function __construct(array $config, bool $dev = false)
    {
        $this->username = Arr::get($config, 'username');
        $this->password = Arr::get($config, 'password');
        $this->uuid = Arr::get($config, 'uuid');
        $this->TenantCode = Arr::get($config, 'TenantCode');
        if ($gateway = Arr::get($config, 'gateway')) {
            $this->gateway = $gateway;
        } else {
            $this->gateway = $dev ? Gateways::FREE_VIEW_DEV : Gateways::FREE_VIEW;
        }
        $this->injectLogObj();
        $this->injectToken();
    }

    protected function injectToken()
    {
        $cacheKey = self::getCacheKey();
        $day = now()->toDateString();
        $cacheKey .= ':' . $day;
        if ($token = cache($cacheKey)) {
            $this->token = $token;
        } else {
            $response = $this->getToken()->fire();
            if ($this->token = Arr::get($response, 'access_token') ?: Arr::get($response, 'raw_resp.access_token')) {
                cache([$cacheKey => $this->token], now()->addDay());
            }
        }

        if (!$this->token) {
            throw new InvalidArgumentException('获取token失败');
        }
    }

    protected function loadingHeaderToken(string $tokenType = 'Bearer')
    {
        $headerToken = '';
        switch (ucfirst($tokenType)) {
            case 'Bearer':
                $headerToken = 'Bearer ' . $this->token;
                break;
            case 'Basic':
                $headerToken = 'Basic ' . base64_encode($this->uuid);
                break;
        }
        $this->headers['Authorization'] = $headerToken;
    }

    protected function configValidator()
    {
        if (!$this->username) {
            throw new InvalidArgumentException('后台账号不可为空');
        }

        if (!$this->password) {
            throw new InvalidArgumentException('后台密码不可为空');
        }

        if (!$this->uuid) {
            throw new InvalidArgumentException('UUID不可为空');
        }
    }

    protected function getToken(): self
    {
        $this->uri = self::GET_TOKEN_URI;
        $this->port = 9700;
        $this->name = '获取访问令牌';
        $this->httpMethod = self::METHOD_GET;
        $this->loadingHeaderToken('Basic');
        $this->queryBody = ['username' => $this->username, 'password' => md5($this->password)];
        return $this;
    }

    /**
     * @param array $bodyPacket
     * TenantCode string N 社区编码
     * CardSerialNumber string Y 卡号
     * DeviceLocalDirectory string Y 设备本地路径（设备编号，含分机号）多个设备以逗号隔开
     * @return $this
     */
    public function cancelAccessCards(array $bodyPacket): self
    {
        $this->name = '撤销门禁卡';
        $this->uri = self::ACCESS_CARDS_URI;
        $this->httpMethod = self::METHOD_DELETE;
        $this->loadingHeaderToken();

        if (!($this->queryBody['TenantCode'] = Arr::get($bodyPacket, 'TenantCode') ?: $this->TenantCode)) {
            $this->cancel = true;
            $this->errBox[] = '社区编码不得为空';
        }

        if (!$cardSerialNumber = Arr::get($bodyPacket, 'CardSerialNumber')) {
            $this->cancel = true;
            $this->errBox[] = '卡号序号不得为空';
        }

        if (!$deviceLocalDirectory = Arr::get($bodyPacket, 'DeviceLocalDirectory')) {
            $this->cancel = true;
            $this->errBox[] = '设备本地路径不得为空';
        }

        $this->queryBody['CardSerialNumber'] = $cardSerialNumber;
        $this->queryBody['DeviceLocalDirectory'] = $deviceLocalDirectory;

        return $this;
    }

    /**
     * @param array $bodyPacket
     * TenantCode string N 社区编码
     * FeatureUniqueID Y  string 特征唯一ID 19个字节
     * FeatureType Y int 1:人脸 2:指纹
     * DeviceLocalDirectory Y string 设备本地路径（设备编号，含分机号），多个设备以逗号隔开
     * @return $this
     */
    public function cancelBodyFeatures(array $bodyPacket): self
    {
        $this->name = '撤销人体特征';
        $this->loadingHeaderToken();
        $this->uri = self::BODY_FEATURES_DEL_URI;
        $this->httpMethod = self::METHOD_DELETE;

        if (!($this->queryBody['TenantCode'] = Arr::get($bodyPacket, 'TenantCode') ?: $this->TenantCode)) {
            $this->cancel = true;
            $this->errBox[] = '社区编码不得为空';
        }

        if (!$featureUniqueID = Arr::get($bodyPacket, 'FeatureUniqueID')) {
            $this->cancel = true;
            $this->errBox[] = '特征唯一ID不得为空';
        }

        if (Arr::get($bodyPacket, 'FeatureType') == 1) {
            $this->queryBody['FeatureType'] = 1;
        } else {
            $this->queryBody['FeatureType'] = 2;
        }

        if (!$deviceLocalDirectory = Arr::get($bodyPacket, 'DeviceLocalDirectory')) {
            $this->cancel = true;
            $this->errBox[] = '设备本地路径不得为空';
        }
        $this->queryBody['DeviceLocalDirectory'] = $deviceLocalDirectory;
        $this->queryBody['FeatureUniqueID'] = $featureUniqueID;

        return $this;
    }

    /**
     * @param array $bodyPacket
     * TenantCode string N 社区编码
     * DeviceDirectory string Y string 设备编码
     * OpenDoorType int N  1：第三方卡验证后开锁 2：第三方密码验证后开锁 3：第三方人脸识别后开锁
     * RequestID string Y(20) 开锁人标识
     * @return $this
     */
    public function remoteUnlock(array $bodyPacket): self
    {
        $this->name = '远程开锁';
        $this->httpMethod = self::METHOD_GET;
        $this->uri = self::REMOTE_UNLOCK_DOOR_URI;
        $this->loadingHeaderToken();

        if (!($this->queryBody['tenantCode'] = Arr::get($bodyPacket, 'TenantCode') ?: $this->TenantCode)) {
            $this->cancel = true;
            $this->errBox[] = '社区编码不得为空';
        }

        if ($openDoorType = Arr::get($bodyPacket, 'OpenDoorType')) {
            if (in_array($openDoorType, [1, 2, 3])) {
                $this->queryBody['opendoorType'] = $openDoorType;
            }
        }

        if (!$deviceDirectory = Arr::get($bodyPacket, 'DeviceDirectory')) {
            $this->cancel = true;
            $this->errBox[] = '设备编码不得为空';
        }

        if (!$requestID = Arr::get($bodyPacket, 'RequestID')) {
            $this->cancel = true;
            $this->errBox[] = '开锁人标识不得为空';
        }

        $this->queryBody['requestID'] = $requestID;
        $this->queryBody['deviceDirectory'] = $deviceDirectory;

        return $this;
    }

    /**
     * @param array $bodyPacket
     * TenantCode string N 社区编码
     * FeatureUniqueID Y  string 特征唯一ID 19个字节
     * FeatureType Y int 1:人脸 2:指纹
     * DeviceLocalDirectory Y string 设备本地路径（设备编号，含分机号），多个设备以逗号隔开
     * ValidTimeStart string N 有效期开始，默认当前时间格式：yyyy-MM-dd HH:mm:ss
     * ValidTimeEnd string N 有效期止，默认五年后格式：yyyy-MM-dd HH:mm:ss
     * HumanFeatureFiles Y array  人体特征文件的base64字符串数组(人脸识别时，此处为照片数据，指纹时为特征数据)
     * New N bool 默认为新建
     * @return $this
     */
    public function authorizedBodyFeatures(array $bodyPacket): self
    {
        $new = Arr::get($bodyPacket, 'New');
        !is_bool($new) && $new = true;
        if ($new) {
            $this->name = '授权人体特征';
        } else {
            $this->name = '授权人体特征(更新)';
            $this->httpMethod = self::METHOD_PUT;
        }
        $this->loadingHeaderToken();
        $this->uri = self::BODY_FEATURES_URI;

        if (!($this->queryBody['TenantCode'] = Arr::get($bodyPacket, 'TenantCode') ?: $this->TenantCode)) {
            $this->cancel = true;
            $this->errBox[] = '社区编码不得为空';
        }

        if (!$featureUniqueID = Arr::get($bodyPacket, 'FeatureUniqueID')) {
            $this->cancel = true;
            $this->errBox[] = '特征唯一ID不得为空';
        }

        if (Arr::get($bodyPacket, 'FeatureType') == 1) {
            $featureType = 1;
        } else {
            $featureType = 2;
        }

        if (!$deviceLocalDirectory = Arr::get($bodyPacket, 'DeviceLocalDirectory')) {
            $this->cancel = true;
            $this->errBox[] = '设备本地路径不得为空';
        }

        if ($validTimeStart = Arr::get($bodyPacket, 'ValidTimeStart')) {
            try {
                $this->queryBody['ValidTimeStart'] = Carbon::parse($validTimeStart)->format('Y-m-d H:i:s');
            } catch (\Throwable $exception) {
            }
        }

        if ($validTimeEnd = Arr::get($bodyPacket, 'ValidTimeEnd')) {
            try {
                $this->queryBody['ValidTimeEnd'] = Carbon::parse($validTimeEnd)->format('Y-m-d H:i:s');
            } catch (\Throwable $exception) {
            }
        }

        if (!$humanFeatureFiles = Arr::get($bodyPacket, 'HumanFeatureFiles')) {
            $this->cancel = true;
            $this->errBox[] = '人体特征数据集合不得为空';
        }

        if (is_string($humanFeatureFiles)) {
            $humanFeatureFiles = Arr::wrap($humanFeatureFiles);
        }

        if ($featureType === 1) {
            foreach ($humanFeatureFiles as $key => $humanFeatureFile) {
                $fileArr = explode('.', $humanFeatureFile);
                $fileExt = end($fileArr);
                if (!in_array(strtolower($fileExt), ['png', 'jpg', 'jpeg'], true)) {
                    $this->cancel = true;
                    $this->errBox[] = '人脸文件后缀不合法';
                    continue;
                }

                try {
                    $fileInfo = getimagesize($humanFeatureFile);
                    if (!$ext = $fileInfo['mime'] ?? null) {
                        throw new \Exception('拉取图片信息失败');
                    }
                    $base64File = "data:{$ext};base64," . base64_encode(file_get_contents($humanFeatureFile));
                    $humanFeatureFiles[$key] = $base64File;
                } catch (\Throwable $exception) {
                    $this->cancel = true;
                    $this->errBox[] = '人脸文件转码base64失败:' . $exception->getMessage();
                    continue;
                }
            }
        }

        $this->queryBody['HumanFeatureFiles'] = $humanFeatureFiles;
        $this->queryBody['DeviceLocalDirectory'] = $deviceLocalDirectory;
        $this->queryBody['FeatureType'] = $featureType;
        $this->queryBody['FeatureUniqueID'] = $featureUniqueID;

        return $this;
    }

    /**
     * @param array $bodyPacket
     * TenantCode string N 社区编码
     * CardSerialNumber string Y 卡号
     * DeviceLocalDirectory string Y 设备本地路径（设备编号，含分机号）多个设备以逗号隔开
     * ValidTimeStart string N 有效期开始，默认当前时间格式：yyyy-MM-dd HH:mm:ss
     * ValidTimeEnd string N 有效期止，默认五年后格式：yyyy-MM-dd HH:mm:ss
     * @return $this
     */
    public function authorizedAccessCards(array $bodyPacket): self
    {
        $this->name = '授权门禁卡';
        $this->uri = self::ACCESS_CARDS_URI;
        $this->loadingHeaderToken();

        if (!($this->queryBody['TenantCode'] = Arr::get($bodyPacket, 'TenantCode') ?: $this->TenantCode)) {
            $this->cancel = true;
            $this->errBox[] = '社区编码不得为空';
        }

        if (!$cardSerialNumber = Arr::get($bodyPacket, 'CardSerialNumber')) {
            $this->cancel = true;
            $this->errBox[] = '卡号序号不得为空';
        }

        if (strlen($cardSerialNumber) !== 8) {
            $this->cancel = true;
            $this->errBox[] = '只支持8位卡号序号';
        }

        $this->queryBody['CardSerialNumber'] = $cardSerialNumber;
        $this->queryBody['CardMediaTypeID'] = 1;

        if (!$deviceLocalDirectory = Arr::get($bodyPacket, 'DeviceLocalDirectory')) {
            $this->cancel = true;
            $this->errBox[] = '设备本地路径不得为空';
        }

        $this->queryBody['DeviceLocalDirectory'] = $deviceLocalDirectory;
        if (!is_string($this->queryBody['DeviceLocalDirectory'])) {
            $this->cancel = true;
            $this->errBox[] = '设备本地路径参数类型错误';
        }

        if ($validTimeStart = Arr::get($bodyPacket, 'ValidTimeStart')) {
            try {
                $this->queryBody['ValidTimeStart'] = Carbon::parse($validTimeStart)->format('Y-m-d H:i:s');
            } catch (\Throwable $exception) {
            }
        }

        if ($validTimeEnd = Arr::get($bodyPacket, 'ValidTimeEnd')) {
            try {
                $this->queryBody['ValidTimeEnd'] = Carbon::parse($validTimeEnd)->format('Y-m-d H:i:s');
            } catch (\Throwable $exception) {
            }
        }
        return $this;
    }

    protected function generateSignature()
    {
        // TODO: Implement generateSignature() method.
    }

    protected function formatResp(&$response)
    {
        if (Arr::get($response, 'Code') === 200) {
            $resPacket = ['code' => 0, 'msg' => 'SUCCESS', 'data' => [], 'raw_resp' => $response];
        } else {
            $resPacket = ['code' => 500, 'msg' => Arr::get($response, 'Msg') ?: '操作失败', 'data' => [], 'raw_resp' => $response];
        }
        $response = $resPacket;
    }

    public function fire()
    {
        $httpMethod = $this->httpMethod;
        $apiGateway = $this->gateway;
        $queryBody = [];
        if ($this->port) {
            $apiGateway = $apiGateway . ':' . $this->port;
        }
        $apiGateway .= '/api/';
        $httpClient = new Client(['base_uri' => $apiGateway, 'timeout' => $this->timeout, 'verify' => false]);
        if ($this->cancel) {
            $errBox = $this->errBox;
            if (is_array($errBox)) {
                $errMsg = implode(',', $errBox);
            } else {
                $errMsg = '未知错误';
            }
            $this->cleanup();
            throw new InvalidArgumentException($errMsg);
        } else {
            switch ($this->httpMethod) {
                case self::METHOD_DELETE:
                case self::METHOD_GET:
                    if ($this->queryBody) {
                        $this->uri .= '?' . http_build_query($this->queryBody);
                        $queryBody = ['headers' => $this->headers];
                    }
                    break;
                default:
                    $queryBody = ['headers' => $this->headers, 'json' => $this->queryBody];
                    break;
            }

            $rawResponse = [];
            $contentStr = '';
            try {
                $rawResponse = $httpClient->$httpMethod($this->uri, $queryBody);
                $contentStr = $rawResponse->getBody()->getContents();
                $contentArr = @json_decode($contentStr, true);
                if (!isset($contentArr['Code'])) {
                    $contentArr['Code'] = 200;
                    $contentArr['Msg'] = '操作成功';
                }
            } catch (\Throwable $exception) {
                $contentArr = ['Code' => $exception->getCode(), 'Msg' => $exception->getMessage()];
            }

            $this->logging->info($this->name, ['gateway' => $apiGateway, 'uri' => $this->uri, 'queryBody' => $queryBody, 'response' => $contentArr]);
            $this->cleanup();

            switch ($this->responseFormat) {
                case self::RESP_FMT_JSON:
                    $responsePacket = $contentArr;
                    $this->formatResp($responsePacket);
                    return $responsePacket;
                case self::RESP_FMT_BODY:
                    return $contentStr;
                case self::RESP_FMT_RAW:
                default:
                    return $rawResponse;
            }
        }
    }

    protected function cleanup()
    {
        parent::cleanup();
        $this->headers = [];
        $this->port = 21664;
    }
}
