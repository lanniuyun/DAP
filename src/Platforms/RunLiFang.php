<?php

namespace On3\DAP\Platforms;

use Illuminate\Support\Arr;
use On3\DAP\Exceptions\InvalidArgumentException;

class RunLiFang extends Platform
{

    protected $username;
    protected $password;
    protected $cacheKey;
    protected $fillToken = true;
    protected $isUrlQuery = false;

    const DEVICE_TYPE_UNIT = 'unitDoor';
    const DEVICE_TYPE_WALL = 'wallDoor';

    public function __construct(array $config, bool $dev = false, bool $loadingToken = true, bool $autoLogging = true)
    {
        if ($gateway = Arr::get($config, 'gateway')) {
            $this->gateway = $gateway;
        } else {
            $this->gateway = $dev ? Gateways::XIN_LIN_DEV : Gateways::XIN_LIN;
        }

        $this->username = Arr::get($config, 'username');
        $this->password = Arr::get($config, 'password');

        $this->configValidator();
        $autoLogging && $this->injectLogObj();
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

                if (!$building = Arr::get($queryPacket, 'building')) {
                    $this->cancel = true;
                    $this->errBox[] = '楼栋号必填';
                }
                break;
            case self::DEVICE_TYPE_UNIT:
            default:

                $kind = self::DEVICE_TYPE_UNIT;

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

    public function injectToken(bool $refresh = false)
    {

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
        // TODO: Implement formatResp() method.
    }

    public function cacheKey(): string
    {
        return $this->cacheKey ?: $this->username;
    }

    public function setCacheKey(string $cacheKey): self
    {
        $this->cacheKey = $cacheKey;
        return $this;
    }

    public function fire()
    {
        // TODO: Implement fire() method.
    }
}
