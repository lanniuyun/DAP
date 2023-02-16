<?php

namespace On3\DAP\Platforms;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use On3\DAP\Exceptions\InvalidArgumentException;
use On3\DAP\Exceptions\RequestFailedException;

class E7 extends Platform
{

    protected $port = 60009;
    protected $gID;
    protected $prefix = '/api/';

    public function __construct(array $config, bool $dev = false, bool $loadingToken = true, bool $autoLogging = true)
    {
        if ($gateway = Arr::get($config, 'gateway')) {
            $gatewayPacket = parse_url($gateway);
            if (is_array($gatewayPacket) && isset($gatewayPacket['scheme']) && isset($gatewayPacket['host'])) {
                $this->gateway = $gatewayPacket['scheme'] . '://' . $gatewayPacket['host'];
                if (isset($gatewayPacket['port']) && is_integer($gatewayPacket['port'])) {
                    $this->port = $gatewayPacket['port'];
                }
            }
        }

        if ($port = Arr::get($config, 'port')) {
            if (is_integer($port)) {
                $this->port = $port;
            }
        }

        if ($gID = Arr::get($config, 'gID')) {
            $this->gID = $gID;
        }

        $this->configValidator();
        $autoLogging && $this->injectLogObj();
    }

    protected function configValidator()
    {
        if (!$this->gateway) {
            throw new InvalidArgumentException('服务网关必填');
        }

        if (!$this->gID) {
            throw new InvalidArgumentException('集团标识必填');
        }
    }

    public function setPort(int $port): self
    {
        if ($port) {
            $this->port = $port;
        }
        return $this;
    }

    protected function generateSignature()
    {
        // TODO: Implement generateSignature() method.
    }

    protected function formatResp(&$response)
    {
        $resultCode = 2;
        $message = '';
        $dataPacket = [];

        if (Arr::has($response, 'Code')) {
            $resultCode = Arr::get($response, 'Code');
            $message = Arr::get($response, 'Describe');
        } elseif (Arr::has($response, 'State')) {
            $resultCode = Arr::get($response, 'State.Code');
            $message = Arr::get($response, 'State.Describe');
        }

        if (Arr::has($response, 'Model')) {
            $dataPacket = Arr::get($response, 'Model');
        } elseif (Arr::has($response, 'Records')) {
            $dataPacket = Arr::get($response, 'Records');
        }

        $dataPacket = $dataPacket ?: [];

        if (in_array($resultCode, [0, 1], true)) {
            $resPacket = ['code' => 0, 'msg' => 'SUCCESS', 'data' => $dataPacket, 'raw_resp' => $response];
        } else {
            $resPacket = ['code' => 500, 'msg' => strval($message), 'data' => [], 'raw_resp' => $response];
        }

        if ($pagination = Arr::get($response, 'PageAttri')) {
            $resPacket['pagination'] = $pagination;
        }
        $response = $resPacket;
    }

    public function cacheKey(): string
    {
        return '';
    }

    public function fire()
    {
        $httpMethod = $this->httpMethod;
        $gateway = $this->gateway . ':' . $this->port;
        $uri = $this->prefix . $this->uri;
        $httpClient = new Client(['base_uri' => $gateway, 'timeout' => $this->timeout, 'verify' => false]);
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
            $exception = $rawResponse = $contentStr = null;
            try {
                $rawResponse = $httpClient->$httpMethod($uri, ['json' => $this->queryBody]);
                $contentStr = $rawResponse->getBody()->getContents();
                $contentArr = @json_decode($contentStr, true);
            } catch (\Throwable $exception) {
                $contentArr = $exception->getMessage();
            } finally {
                try {
                    $this->logging->info($this->name, ['gateway' => $gateway, 'uri' => $uri, 'queryBody' => $this->queryBody, 'response' => $contentArr]);
                } catch (\Throwable $ignoredException) {
                }

                $this->cleanup();

                if ($exception instanceof \Throwable) {
                    throw new RequestFailedException($exception->getMessage());
                }

                if (!$rawResponse) {
                    throw new RequestFailedException('接口请求失败:' . $this->name . '无返回');
                }

                if ($rawResponse->getStatusCode() !== 200) {
                    throw new RequestFailedException('接口请求失败:' . $this->name);
                }

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

    /**
     * @param array $queryPacket
     * page int 页数
     * pageSize int 每页条数
     * orderBy string 排序
     * orderType bool 排序类型
     * where string 筛选条件
     * append string 附加值
     * pageTotal int 总数
     * @return $this
     */
    public function getParkList(array $queryPacket = []): self
    {
        $this->uri = 'Park/GetByCustom';
        $this->name = '停车场基础信息列表';
        $this->queryBody = self::getPageQuery($queryPacket);
        return $this;
    }

    /**
     * @param array $queryPacket
     * id string 车场ID
     * @return $this
     */
    public function getParkInfo(array $queryPacket = []): self
    {
        $this->uri = 'Park/Get/';
        $this->name = '停车场基础信息详情';
        $this->httpMethod = self::METHOD_GET;

        $parkID = Arr::get($queryPacket, 'id');

        if (!$parkID || !is_numeric($parkID)) {
            $this->cancel = true;
            $this->errBox[] = '车场ID只能为数字类型不为空';
        }

        $this->uri .= $parkID;

        return $this;
    }

    /**
     * @param array $queryPacket
     * page int 页数
     * pageSize int 每页条数
     * orderBy string 排序
     * orderType bool 排序类型
     * where string 筛选条件
     * append string 附加值
     * pageTotal int 总数
     * @return $this
     */
    public function getGateControllerDeviceList(array $queryPacket = []): self
    {
        $this->uri = 'GateControllerDevice/GetByCustom';
        $this->name = '开闸设备信息列表';
        $this->queryBody = self::getPageQuery($queryPacket);
        return $this;
    }

    /**
     * @param array $queryPacket
     * page int 页数
     * pageSize int 每页条数
     * orderBy string 排序
     * orderType bool 排序类型
     * where string 筛选条件
     * append string 附加值
     * pageTotal int 总数
     * @return $this
     */
    public function getLaneList(array $queryPacket = []): self
    {
        $this->uri = 'Lane/GetByCustom';
        $this->name = 'authDevice?';
        $this->queryBody = self::getPageQuery($queryPacket);
        return $this;
    }

    /**
     * @param array $queryPacket
     * id int 车闸ID
     * @return $this
     */
    public function getGateControllerDeviceInfo(array $queryPacket = []): self
    {
        $this->uri = 'GateControllerDevice/Get/';
        $this->name = '开闸设备信息详情';
        $this->httpMethod = self::METHOD_GET;

        $deviceID = Arr::get($queryPacket, 'id');

        if (!$deviceID || !is_numeric($deviceID)) {
            $this->cancel = true;
            $this->errBox[] = '车闸ID只能为数字类型不为空';
        }

        $this->uri .= $deviceID;

        return $this;
    }

    /**
     * @param array $queryPacket
     * act 1:新建 2:编辑 0:删除
     * tcmID string 卡类
     * monthMoney float 月金额
     * dayMoney float 日金额
     * discountRate float 折扣
     * rID string 标识
     * @return $this
     */
    public function modifyExtendedService(array $queryPacket = []): self
    {

        $this->queryBody['Id'] = Arr::get($queryPacket, 'id');

        switch ($act = Arr::get($queryPacket, 'act')) {
            case 0:
                $this->uri = 'TcmFixedMoney/DeleteFunc';
                $this->name = '月卡延期规则删除';

                if (!$this->queryBody['Id']) {
                    $this->cancel = true;
                    $this->errBox[] = '月卡延期规则ID必填';
                }
                break;
            case 2:
                $this->uri = 'TcmFixedMoney/Modify';
                $this->name = '月卡延期规则修改';
                $this->httpMethod = self::METHOD_PUT;
                break;
            case 1:
            default:
                $this->uri = 'TcmFixedMoney/Add';
                $this->name = '月卡延期规则编辑';
                break;
        }

        if ($act !== 0) {
            if (!$this->queryBody['TcmId'] = Arr::get($queryPacket, 'tcmID')) {
                $this->cancel = true;
                $this->errBox[] = '卡类必填';
            }

            if (!$this->queryBody['MonthMoney'] = floatval(Arr::get($queryPacket, 'monthMoney'))) {
                $this->cancel = true;
                $this->errBox[] = '月金额必填';
            }

            if (!$this->queryBody['DayMoney'] = floatval(Arr::get($queryPacket, 'dayMoney'))) {
                $this->cancel = true;
                $this->errBox[] = '日金额必填';
            }

            if (!$this->queryBody['DiscountRate'] = floatval(Arr::get($queryPacket, 'discountRate'))) {
                $this->cancel = true;
                $this->errBox[] = '抵扣额度必填';
            }

            if (!$this->queryBody['Rid'] = Arr::get($queryPacket, 'rID')) {
                $this->errBox[] = 'rID必填';
                $this->cancel = true;
            }

            $this->queryBody['OperatorName'] = '超级管理员';
            $this->queryBody['OperatorDate'] = now()->toDateTimeString();
        }

        $this->queryBody['Gid'] = $this->gID;

        return $this;
    }

    public function getExtendedServiceInfo(array $queryPacket = []): self
    {
        $this->uri = 'TcmFixedMoney/Get/';
        $this->name = '月卡延期规则查询';
        $this->httpMethod = self::METHOD_GET;

        $serviceID = Arr::get($queryPacket, 'id');

        if (!$serviceID || !is_numeric($serviceID)) {
            $this->cancel = true;
            $this->errBox[] = '月卡延期规则ID只能为数字类型不为空';
        }

        $this->uri .= $serviceID;

        return $this;
    }

    public function getExtendedServiceList(array $queryPacket = []): self
    {
        $this->uri = 'TcmFixedMoney/GetByCustom';
        $this->name = '月卡延期规则查询';

        $this->queryBody = self::getPageQuery($queryPacket);

        return $this;
    }

    public function modifyOrganization(array $queryPacket = []): self
    {

        $this->queryBody['ID'] = Arr::get($queryPacket, 'id');

        switch ($act = Arr::get($queryPacket, 'act')) {
            case 0:
                $this->uri = 'Organization/DeleteFunc';
                $this->name = '组织机构条件删除';

                if (!$this->queryBody['ID']) {
                    $this->cancel = true;
                    $this->errBox[] = '标识号不得为空';
                }

                break;
            case 2:
                $this->uri = 'Organization/Modify';
                $this->name = '组织机构修改';
                $this->httpMethod = self::METHOD_PUT;
                if (!$this->queryBody['ID'] = intval($this->queryBody['ID'])) {
                    $this->cancel = true;
                    $this->errBox[] = '编号必填';
                }
                break;
            case 1:
            default:
                $this->uri = 'Organization/Add';
                $this->name = '组织机构新增';
                $this->queryBody['ID'] = intval($this->queryBody['ID']);
                break;
        }

        if ($act !== 0) {
            if (!$this->queryBody['Name'] = Arr::get($queryPacket, 'name')) {
                $this->cancel = true;
                $this->errBox[] = '名称不为空';
            }

            if (!$this->queryBody['Rid'] = Arr::get($queryPacket, 'rID')) {
                $this->errBox[] = 'rID必填';
                $this->cancel = true;
            }

            $this->queryBody['ParentId'] = intval(Arr::get($queryPacket, 'parentID'));
            $this->queryBody['Remark'] = Arr::get($queryPacket, 'remark');
        }

        $this->queryBody['Gid'] = $this->gID;

        return $this;
    }

    public function getOrganizationInfo(array $queryPacket = []): self
    {
        $this->uri = 'Organization/Get/';
        $this->name = '组织机构编号查询';
        $this->httpMethod = self::METHOD_GET;

        if (!$ID = Arr::get($queryPacket, 'id')) {
            $this->cancel = true;
            $this->errBox[] = '';
        }
        $this->uri .= $ID;
        return $this;
    }

    /**
     * @param array $queryPacket
     * page int 页数
     * pageSize int 每页条数
     * orderBy string 排序
     * orderType bool 排序类型
     * where string 筛选条件
     * append string 附加值
     * pageTotal int 总数
     * @return $this
     */
    public function getOrganizationList(array $queryPacket = []): self
    {
        $this->uri = 'Organization/GetByCustom';
        $this->name = '组织机构查询';
        $this->queryBody = self::getPageQuery($queryPacket);
        return $this;
    }

    /**
     * @param array $queryPacket
     * page int 页数
     * pageSize int 每页条数
     * orderBy string 排序
     * orderType bool 排序类型
     * where string 筛选条件
     * append string 附加值
     * pageTotal int 总数
     * @return $this
     */
    public function getTcmList(array $queryPacket = []): self
    {
        $this->uri = 'Tcm/GetByCustom';
        $this->name = '卡片列表';
        $this->queryBody = self::getPageQuery($queryPacket);
        return $this;
    }

    /**
     * @param array $queryPacket
     * page int 页数
     * pageSize int 每页条数
     * orderBy string 排序
     * orderType bool 排序类型
     * where string 筛选条件
     * append string 附加值
     * pageTotal int 总数
     * @return $this
     */
    public function getStaffList(array $queryPacket = []): self
    {
        $this->uri = 'Staff/GetByCustom';
        $this->name = '人事列表';
        $this->queryBody = self::getPageQuery($queryPacket);
        return $this;
    }

    public function modifyStaff(array $queryPacket = []): self
    {

        $ID = Arr::get($queryPacket, 'id');
        $staff = [];
        switch ($act = Arr::get($queryPacket, 'act')) {
            case 0:
                $this->uri = 'Staff/DeleteFunc';
                $this->name = '人事条件删除';
                if (!$ID) {
                    $this->cancel = true;
                    $this->errBox[] = '编号必填';
                }
                $staff['id'] = $ID;
                break;
            case 2:
                $this->uri = 'StaffService/EditStaff';
                $this->name = '人事修改';
                if (!$ID) {
                    $this->cancel = true;
                    $this->errBox[] = '编号必填';
                }
                $staff['id'] = $ID;
                break;
            case 1:
            default:
                $this->uri = 'StaffService/EditStaff';
                $this->name = '人事新增';
                break;
        }

        if ($act !== 0) {
            if (!$staff['StaffNo'] = Arr::get($queryPacket, 'staffNo')) {
                $this->cancel = true;
                $this->errBox[] = '人员编号必填';
            }

            if (!$staff['StaffName'] = Arr::get($queryPacket, 'staffName')) {
                $this->cancel = true;
                $this->errBox[] = '人员姓名必填';
            }

            if (!$staff['Birthday'] = Arr::get($queryPacket, 'birthday')) {
                $this->cancel = true;
                $this->errBox[] = '生日必填';
            }

            try {
                $staff['Birthday'] = Carbon::parse($staff['Birthday'])->toDateTimeString();
            } catch (\Throwable $exception) {
                $this->cancel = true;
                $this->errBox[] = '生日填写错误';
            }

            if (!$staff['OrganizationId'] = Arr::get($queryPacket, 'organizationID')) {
                $this->cancel = true;
                $this->errBox[] = '机构ID必填';
            }

            $staff['RollDate'] = now()->toDateTimeString();
            if (!$staff['Rid'] = strval(Arr::get($queryPacket, 'rID'))) {
                $this->errBox[] = 'rID必填';
                $this->cancel = true;
            }
        }

        $staff['Gid'] = $this->gID;
        $this->queryBody['staff'] = $staff;
        return $this;
    }

    public function getStaffInfo(array $queryPacket = []): self
    {
        $this->uri = 'Staff/Get/';
        $this->name = '人事编号查询';
        $this->httpMethod = self::METHOD_GET;

        if (!$ID = Arr::get($queryPacket, 'id')) {
            $this->cancel = true;
            $this->errBox[] = '';
        }
        $this->uri .= $ID;
        return $this;
    }

    /**
     * @param array $queryPacket
     * is_admit bool 是否用审核
     * type int 类型 0 车牌 1 卡片 2 ETC 3纸票 4二维码 5 生物
     * act int 操作方式 init=0,//初始化｜register=1,//发卡/批量发卡/登记｜modify=2,//改写/批量改写｜topup=3,//充值｜defer=4,//延期｜LossReport=5,//挂失｜unlost=6,//解挂｜suspend=7,//报停
     * activate=8,//激活｜DoLock=9,//锁定｜unlock=10,//解锁｜replace=11,//换/补卡｜unregister=12,//退卡｜Audit=13,//审核通过｜UnAudit=14//审核未通过
     * payType int 支付方式 0 混合多种方式, 1 银联闪付, 2 微信, 3 现金, 4 ETC, 5 支付宝, 6 银联钱包, 7 翼支付, 8 百度钱包, 9 POS机，10优惠券，11会员积分, 12 其他
     * alreadyIn bool 不知道
     * tokenID string 凭证主体/车牌
     * balance float 余额
     * useModel int 模式 写卡模式=0下载模式=1
     * oldBalance float 原余额
     * deviceNos string 车道no
     * Remark string 备注
     * began datetime 时间
     * ended datetime 时间
     * organizationID string 组织ID
     * tcmID string 卡类ID
     * staffNo string 人事编号
     * staffName string 人事名
     * phone string 电话
     * address string 地址
     * isExistCard bool 是否存在卡
     * isLegal bool 是否合法
     * rID string rid
     * isAutoActivate bool 是否自动激活 act =7
     * amount float 支付的金额 act =4
     * rebateAmount float 优惠的金额 act =4
     * newTokenID string 换新的凭证主体/车牌 act =11
     * @return $this
     */
    public function operateToken(array $queryPacket = []): self
    {
        $began = $ended = null;
        $operateNo = '999999';
        $operateName = '超级管理员';
        $tokenType = Arr::get($queryPacket, 'type');
        $isAdmit = Arr::get($queryPacket, 'is_admit');
        $tokenID = Arr::get($queryPacket, 'tokenID');
        $payType = intval(Arr::get($queryPacket, 'payType'));
        $phone = strval(Arr::get($queryPacket, 'phone'));
        $address = strval(Arr::get($queryPacket, 'address'));
        $balance = floatval(Arr::get($queryPacket, 'balance'));
        $isExistCard = boolval(Arr::get($queryPacket, 'isExistCard'));
        $useModel = intval(Arr::get($queryPacket, 'useModel'));
        $remark = strval(Arr::get($queryPacket, 'remark'));
        $oldBalance = floatval(Arr::get($queryPacket, 'oldBalance'));
        $isLegal = boolval(Arr::get($queryPacket, 'isLegal'));
        $deviceNos = Arr::get($queryPacket, 'deviceNos');
        $tcmID = Arr::get($queryPacket, 'tcmID');
        $organizationID = Arr::get($queryPacket, 'organizationID');
        $staffNo = Arr::get($queryPacket, 'staffNo');
        $StaffName = Arr::get($queryPacket, 'staffName');
        if ($rawBegan = Arr::get($queryPacket, 'began')) {
            try {
                $began = Carbon::parse($rawBegan)->toDateTimeString();
            } catch (\Throwable $exception) {
                $this->cancel = true;
                $this->errBox[] = '起始日期填写错误';
            }
        }

        if ($rawEnded = Arr::get($queryPacket, 'ended')) {
            try {
                $ended = Carbon::parse($rawEnded)->toDateTimeString();
            } catch (\Throwable $exception) {
                $this->cancel = true;
                $this->errBox[] = '截止日期填写错误';
            }
        }

        if (!$rID = Arr::get($queryPacket, 'rID')) {
            $this->cancel = true;
            $this->errBox[] = 'rID必填';
        }

        if (!in_array($tokenType, [0, 2], true)) {
            $this->cancel = true;
            $this->errBox[] = '仅支持车牌/ETC凭证';
        }

        $queryBody = [];

        switch ($act = intval(Arr::get($queryPacket, 'act'))) {
            case 1:

                if ($isAdmit) {
                    $this->uri = 'TokenService/AdmitIssue';
                    $this->name = '凭证审核发行';
                } else {
                    $this->uri = 'TokenService/Issue';
                    $this->name = '凭证发行';
                }

                if (!$deviceNos) {
                    $this->cancel = true;
                    $this->errBox[] = '开通设备:开通车道No必填';
                }

                if (is_string($deviceNos)) {
                    if (!$deviceNos = array_filter(explode(',', $deviceNos))) {
                        $this->cancel = true;
                        $this->errBox[] = '开通设备:开通车道No填写格式错误';
                    }
                }

                if (!$tcmID) {
                    $this->cancel = true;
                    $this->errBox[] = '卡类ID必填';
                }

                if (!$organizationID) {
                    $this->cancel = true;
                    $this->errBox[] = '组织ID必填';
                }

                if (!$staffNo) {
                    $this->cancel = true;
                    $this->errBox[] = '人事编号必填';
                }

                if (!$StaffName) {
                    $this->cancel = true;
                    $this->errBox[] = '人事姓名必填';
                }

                $parkIssue = [
                    'TokenOper' => $act,
                    'AlreadyIn' => boolval(Arr::get($queryPacket, 'alreadyIn')),
                    'TokenId' => $tokenID,
                    'SerialNo' => $tokenID,
                    'StaffNo' => $staffNo,
                    'StaffName' => $StaffName,
                    'TelphoneNo' => $phone,
                    'Address' => $address,
                    'OrginazitionId' => $organizationID,
                    'Tcm' => $tcmID,
                    'PayType' => $payType,
                    'AccountBalance' => $balance,
                    'Token' => $tokenID,
                    'IsExistCard' => $isExistCard,
                    'TokenType' => $tokenType,
                    'UseModel' => $useModel,
                    'Plate' => $tokenID,
                    'Remark' => $remark,
                    'OperNo' => $operateNo,
                    'OperName' => $operateName,
                    'Redate' => now()->toDateTimeString(),
                    'Gid' => $this->gID,
                    'ProjectGids' => [$this->gID],
                    'OldAccountBlace' => $oldBalance,
                    'ID' => 0,
                    'Rid' => $rID,
                    'IsLegal' => $isLegal,
                    'AuthDevice' => $deviceNos
                ];

                if ($began) {
                    $parkIssue['BeginDate'] = $began;
                    $parkIssue['IsUpdateBeginDate'] = true;
                } else {
                    $parkIssue['IsUpdateBeginDate'] = false;
                }

                if ($ended) {
                    $parkIssue['EndDate'] = $ended;
                    $parkIssue['IsUpdateEndDate'] = true;
                } else {
                    $parkIssue['IsUpdateEndDate'] = false;
                }

                $queryBody['ParkIssue'] = $parkIssue;
                break;
            case 2:

                if ($isAdmit) {
                    $this->uri = 'TokenService/AdmitParkModify';
                    $this->name = '车牌凭证待审核修改';
                } else {
                    $this->uri = 'TokenService/ParkModify';
                    $this->name = '车牌凭证修改';
                }

                if (!$deviceNos) {
                    $this->cancel = true;
                    $this->errBox[] = '开通设备:开通车道No必填';
                }

                if (is_string($deviceNos)) {
                    if (!$deviceNos = array_filter(explode(',', $deviceNos))) {
                        $this->cancel = true;
                        $this->errBox[] = '开通设备:开通车道No填写格式错误';
                    }
                }

                if (!$tcmID) {
                    $this->cancel = true;
                    $this->errBox[] = '卡类ID必填';
                }

                if (!$organizationID) {
                    $this->cancel = true;
                    $this->errBox[] = '组织ID必填';
                }

                if (!$staffNo) {
                    $this->cancel = true;
                    $this->errBox[] = '人事编号必填';
                }

                if (!$StaffName) {
                    $this->cancel = true;
                    $this->errBox[] = '人事姓名必填';
                }

                $queryBody = [
                    'TokenOper' => $act,
                    'TokenId' => $tokenID,
                    'SerialNo' => $tokenID,
                    'StaffNo' => $staffNo,
                    'StaffName' => $StaffName,
                    'TelphoneNo' => $phone,
                    'Address' => $address,
                    'OrginazitionId' => $organizationID,
                    'Tcm' => $tcmID,
                    'PayType' => $payType,
                    'AccountBalance' => $balance,
                    'Token' => $tokenID,
                    'IsExistCard' => $isExistCard,
                    'TokenType' => $tokenType,
                    'UseModel' => $useModel,
                    'Plate' => $tokenID,
                    'Remark' => $remark,
                    'OperNo' => $operateNo,
                    'OperName' => $operateName,
                    'Redate' => now()->toDateTimeString(),
                    'Gid' => $this->gID,
                    'ProjectGids' => [$this->gID],
                    'OldAccountBlace' => $oldBalance,
                    'ID' => 0,
                    'Rid' => $rID,
                    'IsLegal' => $isLegal,
                    'AuthDevice' => $deviceNos
                ];

                if ($began) {
                    $queryBody['BeginDate'] = $began;
                    $queryBody['IsUpdateBeginDate'] = true;
                } else {
                    $queryBody['IsUpdateBeginDate'] = false;
                }

                if ($ended) {
                    $queryBody['EndDate'] = $ended;
                    $queryBody['IsUpdateEndDate'] = true;
                } else {
                    $queryBody['IsUpdateEndDate'] = false;
                }

                break;
            case 3:
            case 4:
            case 12:
                $amount = floatval(Arr::get($queryBody, 'amount'));

                $queryBody = [
                    'OperMoney' => $amount,
                    'PayType' => $payType,
                    'Token' => $tokenID,
                    'IsExistCard' => $isExistCard,
                    'TokenType' => $tokenType,
                    'UseModel' => $useModel,
                    'Plate' => $tokenID,
                    'TokenOper' => $act,
                    'Remark' => $remark,
                    'OperNo' => $operateNo,
                    'OperName' => $operateName,
                    'Redate' => now()->toDateTimeString(),
                    'Gid' => $this->gID,
                    'ProjectGids' => [$this->gID],
                    'OldAccountBlace' => $oldBalance,
                    'ID' => 0,
                    'Rid' => $rID,
                    'IsLegal' => $isLegal
                ];

                if ($act === 3) {
                    if ($isAdmit) {
                        $this->uri = 'TokenService/AdmitParkTopup';
                        $this->name = '车牌凭证待审核充值';
                    } else {
                        $this->uri = 'TokenService/ParkTopup';
                        $this->name = '车牌凭证充值';
                    }
                } elseif ($act === 4) {
                    if ($isAdmit) {
                        $this->uri = 'TokenService/AdmitParkDefer';
                        $this->name = '车牌凭证待审核延期';
                    } else {
                        $this->uri = 'TokenService/ParkDefer';
                        $this->name = '车牌凭证延期';
                    }

                    $rebateAmount = floatval(Arr::get($queryBody, 'rebateAmount'));

                    $queryBody['DeferDate'] = $ended;
                    $queryBody['EndDate'] = $began;
                    $queryBody['FreeMoney'] = $rebateAmount;
                } else {
                    $this->uri = 'TokenService/ParkUnregister';
                    $this->name = '车牌凭证注销';
                    unset($queryBody['PayType']);
                }

                break;
            case 7:
                $this->uri = 'TokenService/ParkSuspend';
                $this->name = '车牌凭证报停';
                $isAutoActivate = boolval(Arr::get($queryBody, 'isAutoActivate'));

                $queryBody = [
                    'IsAutoActivate' => $isAutoActivate,
                    'SuspendStartDate' => $began,
                    'SuspendEndDate' => $ended,
                    'Token' => $tokenID,
                    'IsExistCard' => $isExistCard,
                    'TokenType' => $tokenType,
                    'UseModel' => $useModel,
                    'Plate' => $tokenID,
                    'TokenOper' => $act,
                    'Remark' => $remark,
                    'OperNo' => $operateNo,
                    'OperName' => $operateName,
                    'Redate' => now()->toDateTimeString(),
                    'Gid' => $this->gID,
                    'ProjectGids' => [$this->gID],
                    'OldAccountBlace' => $oldBalance,
                    'ID' => 0,
                    'Rid' => $rID,
                    'IsLegal' => $isLegal
                ];
                break;
            case 8:
            case 9:
            case 10:
            case 11:

                $queryBody = [
                    'Token' => $tokenID,
                    'IsExistCard' => $isExistCard,
                    'TokenType' => $tokenType,
                    'UseModel' => $useModel,
                    'Plate' => $tokenID,
                    'TokenOper' => $act,
                    'Remark' => $remark,
                    'OperNo' => $operateNo,
                    'OperName' => $operateName,
                    'Redate' => now()->toDateTimeString(),
                    'Gid' => $this->gID,
                    'ProjectGids' => [$this->gID],
                    'OldAccountBlace' => $oldBalance,
                    'ID' => 0,
                    'Rid' => $rID,
                    'IsLegal' => $isLegal
                ];

                if ($act === 8) {
                    $this->uri = 'TokenService/ParkActivate';
                    $this->name = '车牌凭证激活';
                } elseif ($act === 9) {
                    $this->uri = 'TokenService/ParkDoLock';
                    $this->name = '车牌凭证锁定';
                } elseif ($act === 10) {
                    $this->uri = 'TokenService/ParkUnLock';
                    $this->name = '车牌凭证解锁';
                } else {
                    $this->uri = 'TokenService/ParkChangToken';
                    $this->name = '车牌更换';

                    if (!$newTokenID = Arr::get($queryBody, 'newTokenID')) {
                        $this->cancel = true;
                        $this->errBox[] = '新凭证主体不得为空';
                    }

                    $queryBody['NewToken'] = $queryBody['SerialNo'] = $newTokenID;
                }
                break;
            default:
                $this->cancel = true;
                $this->errBox[] = '不支持的操作';
                break;
        }

        $this->queryBody = $queryBody;
        return $this;
    }

    protected function getPageQuery(array $queryPacket = []): array
    {
        $PageSize = intval(Arr::get($queryPacket, 'pageSize'));
        $CurrentPage = intval(Arr::get($queryPacket, 'page'));
        $OrderBy = strval(Arr::get($queryPacket, 'orderBy'));
        $OrderType = boolval(Arr::get($queryPacket, 'orderType'));
        $where = strval(Arr::get($queryPacket, 'where'));
        $Append = strval(Arr::get($queryPacket, 'append'));
        $TotalCount = intval(Arr::get($queryPacket, 'pageTotal'));

        return compact('PageSize', 'CurrentPage', 'OrderBy', 'OrderType', 'where', 'Append', 'TotalCount');
    }
}
