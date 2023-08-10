<?php

namespace On3\DAP\Platforms;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use On3\DAP\Exceptions\InvalidArgumentException;

class RunLiFang extends Platform
{

    protected $token;
    protected $username;
    protected $password;
    protected $cacheKey;
    protected $fillToken = true;
    protected $isUrlQuery = false;
    protected $version = 'v1';

    const DEVICE_TYPE_UNIT = 'unitDoor';
    const DEVICE_TYPE_WALL = 'wallDoor';

    public function __construct(array $config, bool $dev = false, bool $loadingToken = true, bool $autoLogging = true)
    {
        if ($gateway = Arr::get($config, 'gateway')) {
            $this->gateway = $gateway;
        } else {
            $this->gateway = $dev ? Gateways::RUN_LI_FANG_DEV : Gateways::RUN_LI_FANG;
        }

        $this->username = Arr::get($config, 'username');
        $this->password = Arr::get($config, 'password');

        $this->configValidator();

        $autoLogging && $this->injectLogObj();
        $loadingToken && $this->injectToken();
    }

    protected function login(): self
    {
        $this->uri = 'token/refresh';
        $this->name = '获取身份令牌';
        $this->fillToken = false;

        $this->queryBody = ['username' => $this->username, 'password' => $this->password];

        return $this;
    }

    public function setNotifyUrl(array $queryPacket): self
    {
        $this->uri = 'basic/callback';
        $this->name = '登记回调信息';
        $notifyUrl = strval(Arr::get($queryPacket, 'notify_url'));

        $this->queryBody = ['callbackUrl' => $notifyUrl];
        return $this;
    }

    public function bindDevice(array $queryPacket): self
    {
        $this->uri = 'device';
        $this->name = '添加设备';

        if (!$community = Arr::get($queryPacket, 'community')) {
            $this->cancel = true;
            $this->errBox[] = '小区名必填';
        }

        $building = $unit = '';

        switch (Arr::get($queryPacket, 'type')) {
            case self::DEVICE_TYPE_WALL:
                $kind = self::DEVICE_TYPE_WALL;
                break;
            case self::DEVICE_TYPE_UNIT:
            default:

                $kind = self::DEVICE_TYPE_UNIT;

                if (!$building = Arr::get($queryPacket, 'building')) {
                    $this->cancel = true;
                    $this->errBox[] = '楼栋号必填';
                }

                if (!$unit = Arr::get($queryPacket, 'unit')) {
                    $this->cancel = true;
                    $this->errBox[] = '单元号必填';
                }
                break;
        }

        $name = strval(Arr::get($queryPacket, 'name'));
        $sn = strval(Arr::get($queryPacket, 'sn'));

        $this->queryBody = array_filter(compact(['kind', 'community', 'building', 'unit', 'sn', 'name']));

        return $this;
    }

    public function unbindDevice(array $queryPacket): self
    {
        $this->uri = 'device';
        $this->name = '删除设备';
        $this->httpMethod = self::METHOD_DELETE;
        $this->isUrlQuery = true;

        if (!$sn = Arr::get($queryPacket, 'sn')) {
            $this->cancel = true;
            $this->errBox[] = '设备编码必填';
        }

        $this->queryBody = compact('sn');

        return $this;
    }

    public function upFace(array $queryPacket): self
    {
        $this->uri = 'face';
        $this->name = '添加人脸';
        $this->isUrlQuery = true;

        $timeout = intval(Arr::get($queryPacket, 'timeout'));
        $byDevice = true;

        if ($communityIds = Arr::get($queryPacket, 'community_ids')) {
            $this->queryBody = compact('communityIds');
        } elseif ($buildingIds = Arr::get($queryPacket, 'building_ids')) {
            $this->queryBody = compact('buildingIds');
        } elseif ($unitIds = Arr::get($queryPacket, 'unit_ids')) {
            $this->queryBody = compact('unitIds');
        } elseif ($sns = Arr::get($queryPacket, 'sns')) {
            $this->queryBody = compact('sns');
        } else {
            $this->cancel = true;
            $this->errBox[] = '下发对象缺失';
        }

        if (!$faceUrl = Arr::get($queryPacket, 'face_url')) {
            $this->cancel = true;
            $this->errBox[] = '人脸图片不可为空';
        }

        try {
            $IMAGE = file_get_contents($faceUrl);
        } catch (\Throwable $exception) {
            $this->cancel = true;
            $this->errBox[] = '读取图片数据失败';
        }

        $this->queryBody = array_merge($this->queryBody, compact('timeout', 'byDevice', 'IMAGE'));

        return $this;
    }

    public function delFace(array $queryPacket): self
    {
        $this->uri = 'face';
        $this->name = '删除人脸';
        $this->isUrlQuery = true;
        $this->httpMethod = self::METHOD_DELETE;

        if (!$faceRegId = Arr::get($queryPacket, 'face_rid')) {
            $this->cancel = true;
            $this->errBox[] = '人脸注册ID必填';
        }

        $this->queryBody = compact('faceRegId');

        return $this;
    }

    public function remoteOpen(array $queryPacket): self
    {
        $this->uri = 'device/open';
        $this->name = '远程开锁';

        if (!$sn = Arr::get($queryPacket, 'sn')) {
            $this->cancel = true;
            $this->errBox[] = '设备串码必填';
        }

        $kind = strval(Arr::get($queryPacket, 'type'));
        $role = strval(Arr::get($queryPacket, 'role'));
        $opener = strval(Arr::get($queryPacket, 'opener'));

        $this->queryBody = compact('sn', 'kind', 'role', 'opener');

        return $this;
    }

    public function getDynamicPassword(array $queryPacket): self
    {
        $this->uri = 'device/dynamicPassword';
        $this->name = '获取动态密码';
        $this->httpMethod = self::METHOD_GET;
        $this->isUrlQuery = true;

        if (!$sn = Arr::get($queryPacket, 'sn')) {
            $this->cancel = true;
            $this->errBox[] = '设备串码必填';
        }

        $withQrcode = boolval(Arr::get($queryPacket, 'qrcode'));
        $opener = strval(Arr::get($queryPacket, 'opener'));

        $this->queryBody = compact('sn', 'withQrcode', 'opener');

        return $this;
    }

    public function getCallSnapshot(array $queryPacket): self
    {
        $this->uri = 'call/image';
        $this->name = '获取呼入留影';
        $this->httpMethod = self::METHOD_GET;
        $this->isUrlQuery = true;

        if (!$callId = Arr::get($queryPacket, 'call_id')) {
            $this->cancel = true;
            $this->errBox[] = '设备串码必填';
        }

        if (!$sn = Arr::get($queryPacket, 'sn')) {
            $this->cancel = true;
            $this->errBox[] = '设备串码必填';
        }

        $this->queryBody = compact('callId', 'sn');

        return $this;
    }

    public function callAccept(array $queryPacket): self
    {
        $this->uri = 'call/accept';
        $this->name = '接听设备呼入';

        if (!$callId = Arr::get($queryPacket, 'call_id')) {
            $this->cancel = true;
            $this->errBox[] = '设备串码必填';
        }

        if (!$sn = Arr::get($queryPacket, 'sn')) {
            $this->cancel = true;
            $this->errBox[] = '设备串码必填';
        }

        $media = strval(Arr::get($queryPacket, 'media'));
        $acceptKind = strval(Arr::get($queryPacket, 'type'));
        $accepter = strval(Arr::get($queryPacket, 'accepter'));

        if ($media !== 'video') {
            $media = 'audio';
        }

        $this->queryBody = compact('callId', 'sn', 'media', 'acceptKind', 'accepter');

        return $this;
    }

    public function callHangUp(array $queryPacket): self
    {
        $this->uri = 'call/hangUp';
        $this->name = '主动挂断呼叫';

        if (!$callId = Arr::get($queryPacket, 'call_id')) {
            $this->cancel = true;
            $this->errBox[] = '设备串码必填';
        }

        if (!$sn = Arr::get($queryPacket, 'sn')) {
            $this->cancel = true;
            $this->errBox[] = '设备串码必填';
        }

        $this->queryBody = compact('callId', 'sn');

        return $this;
    }

    public function monitor(array $queryPacket): self
    {
        $this->uri = 'monitor';
        $this->name = '监视发起';

        if (!$sn = Arr::get($queryPacket, 'sn')) {
            $this->cancel = true;
            $this->errBox[] = '设备串码必填';
        }

        $watcher = strval(Arr::get($queryPacket, 'watcher'));
        $this->queryBody = compact('sn', 'watcher');

        return $this;
    }

    public function monitorHangUp(array $queryPacket): self
    {
        $this->uri = 'monitor/hangUp';
        $this->name = '被动挂断监视';

        if (!$sn = Arr::get($queryPacket, 'sn')) {
            $this->cancel = true;
            $this->errBox[] = '设备串码必填';
        }

        if (!$monitorId = Arr::get($queryPacket, 'monitor_id')) {
            $this->cancel = true;
            $this->errBox[] = '监视ID必填';
        }

        $this->queryBody = compact('sn', 'monitorId');

        return $this;
    }

    public function deviceStatus(array $queryPacket): self
    {
        $this->httpMethod = self::METHOD_GET;
        $this->isUrlQuery = true;
        $this->uri = 'device/status';
        $this->name = '设备管理';

        if (!$sn = Arr::get($queryPacket, 'sn')) {
            $this->cancel = true;
            $this->errBox[] = '设备串码必填';
        }

        $this->queryBody = compact('sn');

        return $this;
    }

    public function getDeviceSetting(array $queryPacket): self
    {
        $this->httpMethod = self::METHOD_GET;
        $this->isUrlQuery = true;
        $this->uri = 'device/setting';
        $this->name = '获取设备设置';

        if (!$sn = Arr::get($queryPacket, 'sn')) {
            $this->cancel = true;
            $this->errBox[] = '设备串码必填';
        }

        $this->queryBody = compact('sn');

        return $this;
    }

    public function deviceSync(array $queryPacket): self
    {
        $this->uri = 'device/sync';
        $this->name = '数据同步';

        if (!$sn = Arr::get($queryPacket, 'sn')) {
            $this->cancel = true;
            $this->errBox[] = '设备串码必填';
        }

        $this->queryBody = compact('sn');

        return $this;
    }

    public function deviceRoot(array $queryPacket): self
    {
        $this->uri = 'device/reboot';
        $this->name = '重启';

        if (!$sn = Arr::get($queryPacket, 'sn')) {
            $this->cancel = true;
            $this->errBox[] = '设备串码必填';
        }

        $this->queryBody = compact('sn');

        return $this;
    }

    public function deviceUpgrade(array $queryPacket): self
    {
        $this->uri = 'device/upgrade';
        $this->name = '升级';

        if (!$sn = Arr::get($queryPacket, 'sn')) {
            $this->cancel = true;
            $this->errBox[] = '设备序列号必填';
        }

        if (!$part = Arr::get($queryPacket, 'part')) {
            $this->cancel = true;
            $this->errBox[] = '升级指定部分的软件必填';
        }

        if (!$name = Arr::get($queryPacket, 'name')) {
            $this->cancel = true;
            $this->errBox[] = '软件名必填';
        }

        if (!$version = Arr::get($queryPacket, 'version')) {
            $this->cancel = true;
            $this->errBox[] = '目标版本号必填';
        }

        $this->queryBody = compact('sn', 'part', 'name', 'version');

        return $this;
    }

    public function upQrcode(array $queryPacket): self
    {
        $this->uri = 'qrcode/openQrcode';
        $this->name = '上传开锁二维码';

        if (!$qrcodeUrl = Arr::get($queryPacket, 'qrcode_url')) {
            $this->cancel = true;
            $this->errBox[] = '二维码图片不可为空';
        }

        try {
            $qrcode = file_get_contents($qrcodeUrl);
        } catch (\Throwable $exception) {
            $this->cancel = true;
            $this->errBox[] = '读取图片数据失败';
        }

        $sn = strval(Arr::get($queryPacket, 'sn'));
        $text = strval(Arr::get($queryPacket, 'text'));

        $this->queryBody = compact('qrcode', 'sn', 'text');

        return $this;
    }

    public function injectToken(bool $refresh = false)
    {
        $cacheKey = $this->cacheKey();

        if (!($token = cache($cacheKey)) || $refresh) {
            $response = $this->login()->fire();
            if ($this->token = Arr::get($response, 'data.token') ?: Arr::get($response, 'raw_resp.token')) {
                cache([$cacheKey => $this->token], now()->addHours(2)->subMinutes(10));
            }
        } else {
            $this->token = $token;
        }

        if (!$this->token) {
            throw new InvalidArgumentException('获取token失败');
        }
    }

    protected function configValidator()
    {
        if (!$this->username) {
            throw new InvalidArgumentException('账号不得为空');
        }

        if (!$this->password) {
            throw new InvalidArgumentException('密码不得为空');
        }
    }

    protected function generateSignature()
    {
        // TODO: Implement generateSignature() method.
    }

    protected function formatResp(&$response)
    {
        $errCode = Arr::get($response, 'code');
        $errMsg = Arr::get($response, 'msg');

        if ($errCode) {
            $resPacket = ['code' => $errCode, 'msg' => $errMsg, 'data' => [], 'raw_resp' => $response];
        } else {
            $resPacket = ['code' => 0, 'msg' => '', 'data' => $response, 'raw_resp' => $response];
        }

        $response = $resPacket;
    }

    public function cacheKey(): string
    {
        return $this->cacheKey ?: 'RunLiFang:' . $this->username;
    }

    public function setCacheKey(string $cacheKey): self
    {
        $this->cacheKey = $cacheKey;
        return $this;
    }

    public function fire()
    {
        $httpMethod = $this->httpMethod;
        $httpClient = new Client(['base_uri' => $this->gateway, 'timeout' => $this->timeout, 'verify' => false]);

        if ($this->cancel) {
            if (is_array($errBox = $this->errBox)) {
                $errMsg = implode(',', $errBox);
            } else {
                $errMsg = '未知错误';
            }
            $this->cleanup();
            throw new InvalidArgumentException($errMsg);
        } else {

            $uri = '/' . $this->version . '/' . $this->uri;
            $queryBody = [];

            if ($this->isUrlQuery) {
                $uri = $uri . '?' . http_build_query($this->queryBody);
            } else {
                $queryBody['json'] = $this->queryBody;
            }

            if ($this->fillToken) {
                $queryBody['headers'] = ['Authorization' => 'DpToken ' . $this->token];
            }

            try {
                $rawResponse = $httpClient->request($httpMethod, $uri, $queryBody);
            } catch (\Throwable $exception) {
                $rawResponse = $exception->getResponse();
            } finally {

                $contentStr = $rawResponse->getBody()->getContents();
                $contentArr = @json_decode($contentStr, true) ?: [];

                try {
                    $this->logging->info($this->name, ['gateway' => $this->gateway, 'uri' => $uri, 'name' => $this->name, 'queryBody' => $queryBody, 'response' => $contentArr]);
                } catch (\Throwable $ignoredException) {
                }

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
    }
}
