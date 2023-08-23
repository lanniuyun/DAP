<?php

namespace On3\DAP\Platforms;

use Carbon\Carbon;
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
    protected $isImageUpload = false;
    protected $version = 'v1';
    protected $respFmt = self::RESP_FMT_JSON;

    const DEVICE_TYPE_UNIT = 'unitDoor';
    const DEVICE_TYPE_WALL = 'wallDoor';
    const DEVICE_TYPE_ROOM = 'indoor';
    const DEVICE_TYPE_PARK = 'parkingDoor';
    const DEVICE_TYPE_LIFT_IN = 'liftInside';
    const DEVICE_TYPE_LIFT_OUT = 'liftOutside';
    const DEVICE_TYPE_ONE_KEY = 'oneKey';

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

        $building = $unit = $floor = $room = $lift = '';

        switch ($kind = Arr::get($queryPacket, 'type')) {
            case self::DEVICE_TYPE_WALL:
            case self::DEVICE_TYPE_ONE_KEY:
            case self::DEVICE_TYPE_PARK:
                break;
            case self::DEVICE_TYPE_UNIT:
                if (!$building = strval(Arr::get($queryPacket, 'building'))) {
                    $this->cancel = true;
                    $this->errBox[] = '单元机所属楼栋必填';
                }

                if (!$unit = strval(Arr::get($queryPacket, 'unit'))) {
                    $this->cancel = true;
                    $this->errBox[] = '单元机所属单元必填';
                }
                break;
            case self::DEVICE_TYPE_LIFT_IN:
            case self::DEVICE_TYPE_LIFT_OUT:
                if (!$lift = strval(Arr::get($queryPacket, 'lift'))) {
                    $this->cancel = true;
                    $this->errBox[] = '梯控名称必填';
                }

                if ($floor = strval(Arr::get($queryPacket, 'floor'))) {
                    $this->cancel = true;
                    $this->errBox[] = '梯控所属楼层必填';
                }
                break;
            case self::DEVICE_TYPE_ROOM:
                if (!$building = strval(Arr::get($queryPacket, 'building'))) {
                    $this->cancel = true;
                    $this->errBox[] = '室内机所属楼栋必填';
                }

                if (!$unit = strval(Arr::get($queryPacket, 'unit'))) {
                    $this->cancel = true;
                    $this->errBox[] = '室内机所属单元必填';
                }

                if (!$floor = strval(Arr::get($queryPacket, 'floor'))) {
                    $this->cancel = true;
                    $this->errBox[] = '室内机所属楼层必填';
                }

                if (!$room = strval(Arr::get($queryPacket, 'room'))) {
                    $this->cancel = true;
                    $this->errBox[] = '室内机所属房号必填';
                }
                break;
            default:
                $this->cancel = true;
                $this->errBox[] = '未知的设备类型';
                break;
        }

        $name = strval(Arr::get($queryPacket, 'name'));
        $sn = strval(Arr::get($queryPacket, 'sn'));
        $communityExternalId = strval(Arr::get($queryPacket, 'to_community_id'));

        $this->queryBody = array_filter(compact(['kind', 'community', 'building', 'unit', 'sn', 'name', 'communityExternalId', 'floor', 'room', 'lift']));

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

    public function deviceInfo(array $queryPacket): self
    {
        $this->uri = 'devices';
        $this->name = '查询设备';
        $this->httpMethod = self::METHOD_GET;
        $this->isUrlQuery = true;

        $communityId = $buildingId = $unitId = $sn = '';

        if ($communityId = Arr::get($queryPacket, 'community_id')) {
        } elseif ($buildingId = Arr::get($queryPacket, 'building_id')) {
        } elseif ($unitId = Arr::get($queryPacket, 'unit_id')) {
        } elseif ($sn = Arr::get($queryPacket, 'sn')) {
        } else {
            $this->cancel = true;
            $this->errBox[] = '未获取任何参数';
        }

        $this->queryBody = array_filter(compact('communityId', 'buildingId', 'unitId', 'sn'));

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
            if (!$file = file_get_contents($faceUrl)) {
                throw new \Exception('null face');
            }
        } catch (\Throwable $exception) {
            $this->cancel = true;
            $this->errBox[] = '获取人脸图片数据流失败';
        }

        if ($expiration = Arr::get($queryPacket, 'expired_at')) {
            try {
                $expiration = Carbon::parse($expiration)->format('Ymd');
            } catch (\Throwable $exception) {
                $this->cancel = true;
                $this->errBox[] = '有效期格式错误';
            }
        }
        $tel = strval(Arr::get($queryPacket, 'phone'));
        $name = strval(Arr::get($queryPacket, 'name'));

        $this->isImageUpload = true;
        $this->queryBody = array_merge($this->queryBody, compact('timeout', 'byDevice', 'file', 'expiration', 'tel', 'name'));

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

    public function upCard(array $queryPacket): self
    {
        $this->uri = 'card';
        $this->name = '添加门禁卡';
        $this->httpMethod = self::METHOD_POST;

        $communityId = $buildingId = $unitId = $sn = $floor = $room = '';

        switch (Arr::get($queryPacket, 'type')) {
            case 1:
                $kind = 'managementCard';
                if (!$communityId = Arr::get($queryPacket, 'community_id')) {
                    $this->cancel = true;
                    $this->errBox[] = '小区ID必填';
                }
                break;
            case 0:
            default:
                $kind = 'occupantCard';

                if ($buildingId = Arr::get($queryPacket, 'building_id')) {
                } elseif ($unitId = Arr::get($queryPacket, 'unit_id')) {
                } elseif ($sn = Arr::get($queryPacket, 'sn')) {
                } else {
                    $this->cancel = true;
                    $this->errBox[] = '未获取卡号任一绑定标识';
                }

                if ($room = Arr::get($queryPacket, 'room')) {
                    $floor = Arr::get($queryPacket, 'floor');
                }
                break;
        }

        if (!$rfid = Arr::get($queryPacket, 'card_no')) {
            $this->cancel = true;
            $this->errBox[] = '卡号必填';
        }

        $this->queryBody = array_filter(compact('kind', 'communityId', 'buildingId', 'unitId', 'sn', 'floor', 'room', 'rfid'));

        return $this;
    }

    public function delCard(array $queryPacket): self
    {
        $this->uri = 'card';
        $this->name = '删除门禁卡';
        $this->httpMethod = self::METHOD_DELETE;
        $this->isUrlQuery = true;
        $communityId = $buildingId = $unitId = $sn = '';
        if ($communityId = Arr::get($queryPacket, 'community_id')) {
        } elseif ($buildingId = Arr::get($queryPacket, 'building_id')) {
        } elseif ($unitId = Arr::get($queryPacket, 'unit_id')) {
        } elseif ($sn = Arr::get($queryPacket, 'sn')) {
        } else {
            $this->cancel = true;
            $this->errBox[] = '未获取卡号任一绑定标识';
        }

        if (!$rfid = Arr::get($queryPacket, 'card_no')) {
            $this->cancel = true;
            $this->errBox[] = '卡号必填';
        }

        $this->queryBody = array_filter(compact('communityId', 'buildingId', 'unitId', 'sn', 'rfid'));

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
        $this->respFmt = self::RESP_FMT_BODY;

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

    public function callDevice(array $queryPacket): self
    {
        $this->uri = 'call/device';
        $this->name = '主呼设备';

        if (!$sn = Arr::get($queryPacket, 'sn')) {
            $this->cancel = true;
            $this->errBox[] = '设备串码必填';
        }

        $devicePushUrl = Arr::get($queryPacket, 'push_url');
        $devicePullUrl = Arr::get($queryPacket, 'pull_url');

        $this->queryBody = compact('sn', 'devicePushUrl', 'devicePullUrl');

        return $this;
    }

    public function callRoomDevice(array $queryPacket): self
    {
        $this->uri = 'call';
        $this->name = '主呼室内设备';

        $buildingId = $unitId = '';

        if ($buildingId = Arr::get($queryPacket, 'building_id')) {
        } elseif ($unitId = Arr::get($queryPacket, 'unit_id')) {
        } else {
            $this->cancel = true;
            $this->errBox[] = '无呼起设备所属标识';
        }

        $room = Arr::get($queryPacket, 'room');
        $calleePushUrl = Arr::get($queryPacket, 'push_url');
        $calleePullUrl = Arr::get($queryPacket, 'pull_url');

        $this->queryBody = compact('buildingId', 'unitId', 'room', 'calleePullUrl', 'calleePushUrl');

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
            $file = file_get_contents($qrcodeUrl);
        } catch (\Throwable $exception) {
            $this->cancel = true;
            $this->errBox[] = '读取图片数据失败';
        }

        $sn = strval(Arr::get($queryPacket, 'sn'));
        $text = strval(Arr::get($queryPacket, 'text'));
        $this->isImageUpload = true;

        $this->queryBody = compact('file', 'sn', 'text');

        return $this;
    }

    public function getRemoteOpenRecordSnapshot(array $queryPacket): self
    {
        $this->uri = 'record/open/image';
        $this->name = '开锁记录留影';
        $this->httpMethod = self::METHOD_GET;
        $this->isUrlQuery = true;
        $this->respFmt = self::RESP_FMT_BODY;

        if (!$sn = strval(Arr::get($queryPacket, 'sn'))) {
            $this->cancel = true;
            $this->errBox[] = '设备的串码必填';
        }

        if (!$time = strval(Arr::get($queryPacket, 'time'))) {
            $this->cancel = true;
            $this->errBox[] = '开门时间必填';
        }

        if ($time) {
            try {
                $time = Carbon::parse($time)->format('YmdHis');
            } catch (\Throwable $exception) {
                $this->cancel = true;
                $this->errBox[] = '开门时间格式化错误';
            }
        }

        $this->queryBody = compact('sn', 'time');

        return $this;
    }

    public function getCallRecordSnapshot(array $queryPacket): self
    {
        $this->uri = 'record/call/image';
        $this->name = '呼叫记录留影';
        $this->httpMethod = self::METHOD_GET;
        $this->isUrlQuery = true;
        $this->respFmt = self::RESP_FMT_BODY;

        if (!$sn = strval(Arr::get($queryPacket, 'sn'))) {
            $this->cancel = true;
            $this->errBox[] = '设备的串码必填';
        }

        if (!$time = strval(Arr::get($queryPacket, 'time'))) {
            $this->cancel = true;
            $this->errBox[] = '开门时间必填';
        }

        if ($time) {
            try {
                $time = Carbon::parse($time)->format('YmdHis');
            } catch (\Throwable $exception) {
                $this->cancel = true;
                $this->errBox[] = '开门时间格式化错误';
            }
        }

        $this->queryBody = compact('sn', 'time');

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
            $queryBody = ['headers' => []];

            if ($this->isImageUpload) {
                $queryBody['body'] = Arr::get($this->queryBody, 'file');
                $queryBody['headers']['Content-Type'] = 'image/jpeg';
                unset($this->queryBody['file']);
            }

            if ($this->isUrlQuery) {
                $uri = $uri . '?' . http_build_query($this->queryBody);
            } else {
                $queryBody['json'] = $this->queryBody;
            }

            if ($this->fillToken) {
                $queryBody['headers']['Authorization'] = 'DpToken ' . $this->token;
            }

            try {
                $rawResponse = $httpClient->request($httpMethod, $uri, $queryBody);
            } catch (\Throwable $exception) {
                $rawResponse = $exception->getResponse();
            } finally {

                $contentStr = $rawResponse->getBody()->getContents();
                if ($this->respFmt === self::RESP_FMT_JSON) {
                    $contentArr = @json_decode($contentStr, true) ?: [];
                } else {
                    $contentArr = ['body' => $contentStr];
                }

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

    protected function cleanup()
    {
        parent::cleanup();
        $this->respFmt = self::RESP_FMT_JSON;
        $this->fillToken = true;
        $this->isUrlQuery = false;
        $this->isImageUpload = false;
    }
}
