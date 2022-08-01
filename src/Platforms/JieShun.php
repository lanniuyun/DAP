<?php

namespace On3\DAP\Platforms;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use On3\DAP\Traits\DAPBaseTrait;
use On3\DAP\Traits\DAPHashTrait;
use On3\DAP\Exceptions\RequestFailedException;
use On3\DAP\Exceptions\InvalidArgumentException;

class JieShun extends Platform
{
    use DAPBaseTrait, DAPHashTrait;

    protected $cid;
    protected $usr;
    protected $psw;
    protected $businesserCode;
    protected $v = '2';
    protected $requestType = 'DATA';
    protected $hashPacket;
    protected $sn;
    protected $signKey;
    protected $httpMethod = self::METHOD_GET;
    protected $jhtdc;

    const ORDER_TYPE_VNP = 'VNP';
    const ORDER_TYPE_SP = 'SP';

    const LOCK_CAR_LOCK = 0;
    const LOCK_CAR_UNLOCK = 1;

    const LOCK_CARS = [self::LOCK_CAR_LOCK, self::LOCK_CAR_UNLOCK];

    const COUPON_TYPE_AMOUNT = 0;
    const COUPON_TYPE_TIME = 1;
    const COUPON_TYPE_ALL = 2;

    const COUPON_IS_REPEAT_YES = 1;
    const COUPON_IS_REPEAT_NO = 0;

    const COUPON_TYPES = [
        self::COUPON_TYPE_ALL => '全免',
        self::COUPON_TYPE_AMOUNT => '减免金额',
        self::COUPON_TYPE_TIME => '减免时间',
    ];

    const PLATE_COLOR_BLUE = 'BLUE';
    const PLATE_COLOR_YELLOW = 'YELLOW';

    const PLATE_TYPE_MOTOR_VEHICLES = 0;
    const PLATE_TYPE_NON_MOTORIZED_VEHICLES = 1;
    const PLATE_TYPE_UNLICENSED_VEHICLES = 0;
    const PLATE_TYPE_LICENSED_VEHICLES = 1;

    const PLATE_TYPES = [self::PLATE_TYPE_MOTOR_VEHICLES, self::PLATE_TYPE_NON_MOTORIZED_VEHICLES];
    const PLATE_TYPES_V1 = [self::PLATE_TYPE_UNLICENSED_VEHICLES, self::PLATE_TYPE_LICENSED_VEHICLES];

    const VOUCHER_TYPE_TEMPORARY_CAR = 0;
    const VOUCHER_TYPE_MONTHLY_CAR = 1;

    const VOUCHER_TYPES = [self::VOUCHER_TYPE_TEMPORARY_CAR, self::VOUCHER_TYPE_MONTHLY_CAR];

    const PLATE_COLORS = [self::PLATE_COLOR_BLUE, self::PLATE_COLOR_YELLOW];

    const PAY_TYPE_WX = 'WX';
    const PAY_TYPE_ZFB = 'ZFB';
    const PAY_TYPE_YL = 'YL';
    const PAY_TYPE_YZF = 'YZF';
    const PAY_TYPE_YFB = 'YFB';
    const PAY_TYPE_JSJK = 'JSJK';
    const PAY_TYPE_YHJM = 'YHJM';
    const PAY_TYPE_OTHER = 'OTHER';

    const PAY_TYPES = [
        self::PAY_TYPE_WX => '微信',
        self::PAY_TYPE_ZFB => '支付宝',
        self::PAY_TYPE_YL => '银联',
        self::PAY_TYPE_YZF => '翼支付',
        self::PAY_TYPE_YFB => '易付宝',
        self::PAY_TYPE_JSJK => '捷顺金科',
        self::PAY_TYPE_YHJM => '优惠全免',
        self::PAY_TYPE_OTHER => '其他'
    ];

    const DELAY_TYPE_SCHEDULED = 0;
    const DELAY_TYPE_MONTHLY = 1;
    const DELAY_TYPE_NATURAL_MONTH = 2;
    const DELAY_TYPE_CUSTOMIZATION = 3;

    const DELAY_TYPES = [
        self::DELAY_TYPE_SCHEDULED,
        self::DELAY_TYPE_MONTHLY,
        self::DELAY_TYPE_NATURAL_MONTH,
        self::DELAY_TYPE_CUSTOMIZATION
    ];

    const LOGIN_URI = 'login';
    const FUNC_URI = 'as';

    protected $token;

    public function __construct(array $config, bool $dev = false, bool $loadingToken = true)
    {
        $this->cid = Arr::get($config, 'cid');
        $this->usr = Arr::get($config, 'usr');
        $this->psw = Arr::get($config, 'psw');
        $this->signKey = Arr::get($config, 'signKey');
        $this->businesserCode = Arr::get($config, 'businesserCode');
        if ($gateway = Arr::get($config, 'gateway')) {
            $this->gateway = $gateway;
        } else {
            $this->gateway = $dev ? Gateways::JIE_SHUN_DEV : Gateways::JIE_SHUN;
        }
        $this->jhtdc = new JIeShunJHTDC(Arr::only($config, ['pno', 'secret']), $dev);
        $this->injectLogObj();
        $this->configValidator();
        $loadingToken && $this->injectToken();
    }

    //临停缴费

    /**
     * @param array $queryPacket
     * parkCode string Y 车场
     * carNo string Y 车牌
     * @return $this
     */
    public function queryCar(array $queryPacket = []): self
    {
        if (!$parkCode = Arr::get($queryPacket, 'parkCode')) {
            $this->cancel = true;
            $this->errBox[] = '车场编号不可为空';
        }

        if (!$carNo = Arr::get($queryPacket, 'carNo')) {
            $this->cancel = true;
            $this->errBox[] = '车牌号不可为空';
        }

        $serviceId = '3c.pay.querycarbycarno';
        $this->uri = self::FUNC_URI;
        $this->name = '查询相似车辆';
        $this->hashPacket = [
            'serviceId' => $serviceId,
            'requestType' => $this->requestType,
            'attributes' => [
                'parkCode' => $parkCode,
                'carNo' => $carNo
            ]
        ];
        $this->loading();
        return $this;
    }

    /**
     * @param array $queryPacket
     * orderNo string Y 订单号
     * tradeStatus int Y 交易状态 0：成功 1：失败
     * isCallBack int N 需要回调 0：否 1：是
     * notifyUrl string N 回调地址 返回数据会带有三个参数，订单编号和结果：
     * orderNo：订单编号
     * result：结果，0：成功，1：失败
     * sn:参数和商户密钥MD5签名，
     * ｜- 签名内容：orderNo=BK1962101776908048883&result=0
     * ｜- 如：url?orderNo=xxx&result=0&sn=xxx
     * payType string N 支付方式 WX：微信 ZFB：支付 YL：银联 YZF：翼支付 YFB：易付宝 JSJK：捷顺金科（清分必传）YHJM：优惠全免（全免）OTHER：其他
     * payTypeName string N 支付方式中文名
     * payTime string N 支付时间 yyyy-MM-dd HH:mm:ss（清分必传）
     * transactionId string N 支付流水号 订单在支付平台的流水号，如微信，支付宝，捷顺金科的流水号, （清分必传）
     * @return $this
     */
    public function notifyOrderResult(array $queryPacket = []): self
    {
        $serviceId = '3c.pay.notifyorderresult';
        $this->uri = self::FUNC_URI;
        $this->name = '支付结果通知';

        $attributes = [];
        if (!$orderNo = Arr::get($queryPacket, 'orderNo')) {
            $this->cancel = true;
            $this->errBox[] = '订单号不可为空';
        }

        $attributes['orderNo'] = $orderNo;

        $tradeStatus = Arr::get($queryPacket, 'tradeStatus');

        if (!in_array($tradeStatus, [0, 1], true)) {
            $this->cancel = true;
            $this->errBox[] = '交易状态标识错误';
        }

        $attributes['tradeStatus'] = $tradeStatus;

        if ($tradeStatus) {

            $payType = strtoupper(Arr::get($queryPacket, 'payType') ?: '');

            if (!in_array($payType, array_keys(self::PAY_TYPES), true)) {
                $this->cancel = true;
                $this->errBox[] = '支付方式错误';
            }

            $attributes['payType'] = $payType;
            $attributes['payTypeName'] = Arr::get($queryPacket, 'payTypeName') ?: Arr::get(self::PAY_TYPES, $payType);
            $tradeStatus && $attributes['payTime'] = Carbon::parse(Arr::get($queryPacket, 'payTime') ?: now())->format('Y-m-d H:i:s');

            if (Arr::get($queryPacket, 'isCallBack') && ($notifyUrl = Arr::get($queryPacket, 'notifyUrl'))) {
                $attributes['isCallBack'] = 1;
                $attributes['notifyUrl'] = $notifyUrl;
            }

            if ($transactionId = Arr::get($queryPacket, 'transactionId')) {
                $attributes['transactionId'] = $transactionId;
            }
        }

        $this->hashPacket = [
            'serviceId' => $serviceId,
            'requestType' => $this->requestType,
            'attributes' => $attributes
        ];

        $this->loading();
        return $this;
    }

    /**
     * @param array $queryPacket
     * orderNo string Y 订单号
     * @return $this
     */
    public function queryOrder(array $queryPacket = []): self
    {
        if (!$orderNo = Arr::get($queryPacket, 'orderNo')) {
            $this->cancel = true;
            $this->errBox[] = '订单号不可为空';
        }

        $this->uri = self::FUNC_URI;

        switch (Arr::get($queryPacket, 'orderType')) {
            case 'parking':
            default:
                $this->name = '查询停车订单';
                $serviceId = '3c.pay.queryorder';
                $this->hashPacket = [
                    'serviceId' => $serviceId,
                    'requestType' => $this->requestType,
                    'attributes' => [
                        "orderNo" => $orderNo
                    ]
                ];
                break;
        }

        $this->loading();
        return $this;
    }

    /**
     * @param array $queryPacket
     * orderType string Y 订单类型 VNP:按车牌(限定车场)
     * businesserCode string Y 商户编号
     * parkCode string Y 车场
     * carNo string Y 车牌
     * ===========================
     * orderType string Y 订单类型 SP:按卡号
     * cardNo string Y 卡号
     * businesserCode string Y 商户编号
     * parkCode string Y 车场
     * ============================
     * orderType string N 订单类型 默认为空
     * carNo string Y 车牌
     * ===========公共参数===========
     * couponType string N 优惠类型 0:减免金额 1:减免时间 2:全免
     * couponValue int N 0:金额元 1:小时 2:固定值1
     * couponSource string N 优惠来源，请使用固定编号或名称
     * couponList string N json数组格式：
     * ｜- 例：[{“name”:1,“type”:0,“value”:5,“code”: “123456”}]
     * ｜- name: 抵扣方式，0：积分抵扣，1：抵扣券，2：购物小票
     * ｜- type：优惠方式，0：金额减免，1：时间减免，2：全免
     * ｜- value：优惠值：
     * ｜- ①优惠类型为金额时，单位：元；
     * ｜- ②优惠类型为时间时，单位：小时；
     * ｜- ③优惠类型为全免时，值为1，但无直接意义
     * ｜- code：唯一id（非空）/抵扣券号/购物小票号
     * ｜- 注：couponList和couponValue参数默认优先使用couponList。
     * isMember int N 0:否 1:是
     * memberNo string N 会员号
     * memberLevel string N 会员等级
     * queryUrl string N 反查url 如需车场到第三方反查订单状态，可提供查询地址
     * @return $this
     */
    public function placeOrder(array $queryPacket = []): self
    {

        $this->uri = self::FUNC_URI;
        $this->name = '生成停车订单';

        $queryBody = ['requestType' => $this->requestType];

        switch (strtoupper(Arr::get($queryPacket, 'orderType'))) {
            case 'VNP':
                $this->name .= ':车牌/限定车场';

                if (!$carNo = Arr::get($queryPacket, 'carNo')) {
                    $this->cancel = true;
                    $this->errBox[] = '车牌不可为空';
                }

                if (!$parkCode = Arr::get($queryPacket, 'parkCode')) {
                    $this->cancel = true;
                    $this->errBox[] = '车场编号不可为空';
                }

                $queryBody['serviceId'] = '3c.pay.createorderbycarno';
                $attributes = [
                    'businesserCode' => $this->businesserCode,
                    'parkCode' => $parkCode,
                    "orderType" => "VNP",
                    "carNo" => $carNo
                ];
                break;
            case 'SP':
                $this->name .= ':卡号';

                if (!$carNo = Arr::get($queryPacket, 'carNo')) {
                    $this->cancel = true;
                    $this->errBox[] = '卡号不可为空';
                }

                if (!$parkCode = Arr::get($queryPacket, 'parkCode')) {
                    $this->cancel = true;
                    $this->errBox[] = '车场编号不可为空';
                }

                $queryBody['serviceId'] = '3c.pay.createorderbycard';
                $attributes = [
                    'businesserCode' => $this->businesserCode,
                    'parkCode' => $parkCode,
                    "orderType" => "SP",
                    "carNo" => $carNo
                ];

                break;
            default:

                if (!$carNo = Arr::get($queryPacket, 'carNo')) {
                    $this->cancel = true;
                    $this->errBox[] = '车牌不可为空';
                }

                $this->name .= ':车牌/不限定车场';
                $queryBody['serviceId'] = '3c.pay.generateorderbycarno';
                $attributes = ["carNo" => $carNo];
                break;
        }

        if (Arr::has($queryPacket, 'couponType')) {
            $couponType = Arr::get($queryPacket, 'couponType');
            $couponValue = Arr::get($queryPacket, 'couponValue');
            $couponList = Arr::get($queryPacket, 'couponList');
            switch ($couponType) {
                case 0:
                case 1:
                    $attributes['couponType'] = $couponType;
                    $attributes['couponValue'] = intval($couponValue);
                    $attributes['couponList'] = $couponList;
                    break;
                case 2:
                    $attributes['couponType'] = 2;
                    $attributes['couponValue'] = 1;
                    $attributes['couponList'] = $couponList;
                    break;
            }
        }

        if (Arr::has($queryPacket, 'isMember')) {
            if ($isMember = intval(Arr::get($queryPacket, 'isMember'))) {
                $memberNo = Arr::get($queryPacket, 'memberNo');
                $memberLevel = Arr::get($queryPacket, 'memberLevel');
                if ($isMember === 1 && $memberNo && $memberLevel) {
                    $attributes['isMember'] = $isMember;
                    $attributes['memberNo'] = $memberNo;
                    $attributes['memberLevel'] = $memberLevel;
                }
            }
        }

        if ($queryUrl = Arr::get($queryPacket, 'queryUrl')) {
            $attributes['queryUrl'] = $queryUrl;
        }

        if ($attach = Arr::get($queryPacket, 'attach')) {
            $attributes['attach'] = $attach;
        }

        $queryBody['attributes'] = $attributes;
        $this->hashPacket = $queryBody;

        $this->loading();
        return $this;
    }

    /**
     * @param array $queryPacket
     * carNo string Y 车牌
     * parkCode string Y 车场
     * plateColor string Y 车牌颜色 如BLUE 蓝牌  YELLOW 黄牌
     * inTime string N 入场时间 yyyy-MM-dd HH:mm:ss
     * @return $this
     */
    public function closeCurrentWithholding(array $queryPacket = []): self
    {
        $this->uri = self::FUNC_URI;
        $this->name = '关闭当前代扣';
        $serviceId = '3c.pay.closecurrentdk';

        if (!$carNo = Arr::get($queryPacket, 'carNo')) {
            $this->cancel = true;
            $this->errBox[] = '车牌不可为空';
        }

        if (!$parkCode = Arr::get($queryPacket, 'parkCode')) {
            $this->cancel = true;
            $this->errBox[] = '车场编号不可为空';
        }

        if (!$plateColor = Arr::get($queryPacket, 'plateColor')) {
            $this->cancel = true;
            $this->errBox[] = '车牌颜色不可为空';
        }

        $plateColor = strtoupper($plateColor);

        if (!in_array($plateColor, self::PLATE_COLORS, true)) {
            $this->cancel = true;
            $this->errBox[] = '车牌颜色描述错误';
        }

        if ($inTime = Arr::get($queryPacket, 'inTime')) {
            $inTime = Carbon::parse($inTime)->toDateTimeString();
        } else {
            $inTime = now()->toDateTimeString();
        }

        $this->hashPacket = [
            'serviceId' => $serviceId,
            'requestType' => $this->requestType,
            'attributes' => [
                'carNo' => $carNo,
                'parkCode' => $parkCode,
                'plateColor' => $plateColor,
                'inTime' => $inTime
            ]
        ];
        $this->loading();
        return $this;
    }

    /**
     * @param array $queryPacket
     * carNo string Y 车牌
     * parkCode string Y 车场
     * couponType int Y 优惠类型 0：减免金额 1：减免时间 2：全免
     * couponValue double Y 优化值 0：元 1：小时 3：固定值1
     * isRepeat int N 0:只能减免一次 1:多次打折减免 默认为1
     * @return $this
     */
    public function discount(array $queryPacket = []): self
    {
        $this->uri = self::FUNC_URI;
        $this->name = '根据车牌减免';
        $serviceId = '3c.order.discount';

        if (!$carNo = Arr::get($queryPacket, 'carNo')) {
            $this->cancel = true;
            $this->errBox[] = '车牌不可为空';
        }

        if (!$parkCode = Arr::get($queryPacket, 'parkCode')) {
            $this->cancel = true;
            $this->errBox[] = '车场编号不可为空';
        }

        $couponType = Arr::get($queryPacket, 'couponType');

        if (!in_array($couponType, array_keys(self::COUPON_TYPES), true)) {
            $this->cancel = true;
            $this->errBox[] = '优惠类型不可识别';
        }

        if (!is_numeric($couponValue = Arr::get($queryPacket, 'couponValue'))) {
            $this->cancel = true;
            $this->errBox[] = '优惠值只能为数字';
        }

        if ($couponType === 2) {
            $couponValue = 1;
        } else {
            $couponValue = doubleval(strval(intval($couponValue * 100) / 100));
        }

        $isRepeat = Arr::get($queryPacket, 'isRepeat');
        if ($isRepeat !== self::COUPON_IS_REPEAT_NO) {
            $isRepeat = self::COUPON_IS_REPEAT_YES;
        }

        $this->hashPacket = [
            'serviceId' => $serviceId,
            'requestType' => $this->requestType,
            'attributes' => [
                'carNo' => $carNo,
                'parkCode' => $parkCode,
                'couponType' => $couponType,
                'couponValue' => $couponValue,
                'isRepeat' => $isRepeat
            ]
        ];

        $this->loading();
        return $this;
    }

    //临停缴费

    //月卡延期（调用方计算时间和费用）

    /**
     * @param array $queryPacket
     * areaCode string Y 小区编号
     * telephone string Y. 手机号码
     * carNo string Y. 车牌
     * @return $this
     */
    public function queryMonthlyCard(array $queryPacket = []): self
    {
        $this->uri = self::FUNC_URI;
        $this->name = '查询月卡信息';
        $attributes = [];

        if (!$areaCode = Arr::get($queryPacket, 'areaCode')) {
            $this->cancel = true;
            $this->errBox[] = '小区编号不可为空';
        }

        if ($telephone = Arr::get($queryPacket, 'telephone')) {
            $attributes['telephone'] = $telephone;
            $serviceId = '3c.base.querypersonsbytel';
        } elseif ($carNo = Arr::get($queryPacket, 'carNo')) {
            $attributes['carNo'] = $carNo;
            $serviceId = '3c.base.querypersonsbycar';
        } else {
            $serviceId = '';
            $this->cancel = true;
            $this->errBox[] = '手机号码或车牌为空';
        }

        $attributes['areaCode'] = $areaCode;
        $this->hashPacket = [
            'serviceId' => $serviceId,
            'requestType' => $this->requestType,
            'attributes' => $attributes
        ];
        $this->loading();
        return $this;
    }

    /**
     * @param array $queryPacket
     * parkCode string Y 车场
     * carNo string Y 车牌
     * @return $this
     */
    public function queryExtendedServices(array $queryPacket = []): self
    {
        $this->uri = self::FUNC_URI;
        $this->name = '查询延期服务列表';

        $serviceId = '3c.card.querydelaylistbycarno';

        if (!$parkCode = Arr::get($queryPacket, 'parkCode')) {
            $this->cancel = true;
            $this->errBox[] = '车场编号不可为空';
        }

        if (!$carNo = Arr::get($queryPacket, 'carNo')) {
            $this->cancel = true;
            $this->errBox[] = '车牌不可为空';
        }

        $this->hashPacket = [
            'serviceId' => $serviceId,
            'requestType' => $this->requestType,
            'attributes' => [
                'carNo' => $carNo,
                'parkCode' => $parkCode
            ]
        ];
        $this->loading();
        return $this;
    }

    /**
     * @param array $queryPacket
     * cardId string Y. 卡ID
     * carNo string Y. 车牌
     * serviceId string Y. 服务
     * parkCode string N 车场
     * month int Y 月数
     * money float Y 金额 元
     * newBeginDate string Y 开始时间 yyyy-MM-dd
     * newEndDate string Y 结束时间 yyyy-MM-dd
     * payType string N 支付方式 WX：微信 ZFB：支付 YL：银联 YZF：翼支付 YFB：易付宝 JSJK：捷顺金科（清分必传）YHJM：优惠全免（全免）OTHER：其他
     * @return $this
     */
    public function addExtendedService(array $queryPacket = []): self
    {
        $this->uri = self::FUNC_URI;
        $this->name = '下发延期服务';

        $serviceId = '';
        $cardId = Arr::get($queryPacket, 'cardId');
        $carNo = Arr::get($queryPacket, 'carNo');
        $serviceIdV1 = Arr::get($queryPacket, 'serviceId');
        $payType = Arr::get($queryPacket, 'payType');
        $attributes = [];

        $parkCode = Arr::get($queryPacket, 'parkCode');
        $month = Arr::get($queryPacket, 'month');
        $money = Arr::get($queryPacket, 'money');
        if (!$newBeginDate = Arr::get($queryPacket, 'newBeginDate')) {
            $this->cancel = true;
            $this->errBox[] = '延期后新开始日期不可为空';
        }

        if (!$newEndDate = Arr::get($queryPacket, 'newEndDate')) {
            $this->cancel = true;
            $this->errBox[] = '延期后新结束日期不可为空';
        }

        $newBeginDate = Carbon::parse($newBeginDate);
        $newEndDate = Carbon::parse($newEndDate);

        if ($newEndDate->isBefore($newBeginDate)) {
            $this->cancel = true;
            $this->errBox[] = '延期后新结束日期不可小于延期后新开始日期';
        }

        $newBeginDate = $newBeginDate->format('Y-m-d H:i:s');
        $newEndDate = $newEndDate->format('Y-m-d H:i:s');

        if (in_array($payType, array_keys(self::PAY_TYPES), true)) {
            $attributes['payType'] = $payType;
            if ($payName = Arr::get(self::PAY_TYPES, $payType)) {
                $attributes['payName'] = $payName;
            }
        }

        if ($cardId) {
            $serviceId = '3c.card.carddelay';
            $attributes['cardId'] = $cardId;
            $parkCode && $attributes['parkCode'] = $parkCode;
            if (!is_int($month)) {
                $this->cancel = true;
                $this->errBox[] = '月份必须为整数';
            }
            $attributes['month'] = $month;

            if (!is_numeric($money)) {
                $this->cancel = true;
                $this->errBox[] = '金额必须为数字';
            }
            $attributes['money'] = doubleval(strval(intval($money * 100) / 100));
        } elseif ($carNo) {
            $serviceId = '3c.card.delaybycar';
            $attributes['carNo'] = $carNo;
            if (!$parkCode) {
                $this->cancel = true;
                $this->errBox[] = '车场编号不可为空';
            }
            $attributes['parkCode'] = $parkCode;

            if (is_int($month)) {
                $attributes['month'] = $month;
            }

            if (is_numeric($money)) {
                $attributes['money'] = doubleval(strval(intval($money * 100) / 100));
            }
        } elseif ($serviceIdV1) {
            $serviceId = '3c.card.delaybymode';
            $attributes['serviceId'] = $serviceIdV1;
            $attributes['delayMode'] = 1;

            if (!is_int($month)) {
                $this->cancel = true;
                $this->errBox[] = '月份必须为整数';
            }
            $attributes['month'] = $month;

            if (!$parkCode) {
                $this->cancel = true;
                $this->errBox[] = '车场编号不可为空';
            }
            $attributes['parkCode'] = $parkCode;

            if (!is_numeric($money)) {
                $this->cancel = true;
                $this->errBox[] = '金额必须为数字';
            }
            $attributes['money'] = doubleval(strval(intval($money * 100) / 100));
        } else {
            $this->cancel = true;
            $this->errBox[] = '不能确定下发方式';
        }

        $attributes['newBeginDate'] = $newBeginDate;
        $attributes['newEndDate'] = $newEndDate;

        $this->hashPacket = [
            'serviceId' => $serviceId,
            'requestType' => $this->requestType,
            'attributes' => $attributes
        ];
        $this->loading();
        return $this;
    }

    /**
     * @param array $queryPacket
     * cardId string Y. 卡ID
     * serviceId string Y. 服务ID
     * beginDate string Y 缴费开始时间
     * endDate string Y 缴费结束时间
     * pageSize int Y 条数 最大100
     * pageIndex int Y 页码
     * @return $this
     */
    public function queryMonthlyCardTransactionHis(array $queryPacket = []): self
    {
        $this->uri = self::FUNC_URI;
        $this->name = '月卡缴费明细查询';
        $serviceId = '3c.card.querycarddealylist';
        $attributes = [];

        if (!$beginDate = Arr::get($queryPacket, 'beginDate')) {
            $this->cancel = true;
            $this->errBox[] = '缴费开始时间不可为空';
        }

        if (!$endDate = Arr::get($queryPacket, 'endDate')) {
            $this->cancel = true;
            $this->errBox[] = '缴费结束时间不可为空';
        }

        $beginDate = Carbon::parse($beginDate);
        $endDate = Carbon::parse($endDate);

        if ($endDate->isBefore($beginDate)) {
            $this->cancel = true;
            $this->errBox[] = '缴费结束时间不可比缴费开始时间早';
        }

        $attributes['beginDate'] = $beginDate->format('Y-m-d H:i:s');
        $attributes['endDate'] = $endDate->format('Y-m-d H:i:s');

        $pageSize = intval(Arr::get($queryPacket, 'pageSize'));
        $pageIndex = intval(Arr::get($queryPacket, 'pageIndex'));

        if ($pageSize > 100) {
            $pageSize = 100;
        }

        if ($pageSize < 10) {
            $pageSize = 10;
        }

        if ($pageIndex < 1) {
            $pageIndex = 1;
        }

        if ($serviceIdV1 = Arr::get($queryPacket, 'serviceId')) {
            $attributes['serviceId'] = $serviceIdV1;
        } elseif ($cardId = Arr::get($queryPacket, 'cardId')) {
            $attributes['cardId'] = $cardId;
        } else {
            $this->cancel = true;
            $this->errBox[] = '服务ID或卡ID都为空';
        }

        $attributes['pageSize'] = $pageSize;
        $attributes['pageIndex'] = $pageIndex;

        $this->hashPacket = [
            'serviceId' => $serviceId,
            'requestType' => $this->requestType,
            'attributes' => $attributes
        ];
        $this->loading();
        return $this;
    }

    //月卡延期（调用方计算时间和费用）

    //月卡延期（车场软件计算时间和费用）

    /**
     * @param array $queryPacket
     * carNoList string Y 车牌号，多个用,隔开
     * @return $this
     */
    public function queryExtendedServicesV1(array $queryPacket = []): self
    {
        $this->uri = self::FUNC_URI;
        $this->name = '根据车牌查询服务信息';
        $serviceId = '3c.delay.qryservicedelaybycarno';

        if (!$carNoList = Arr::get($queryPacket, 'carNoList')) {
            $this->cancel = true;
            $this->errBox[] = '车牌号不可为空';
        }

        $this->hashPacket = [
            'serviceId' => $serviceId,
            'requestType' => $this->requestType,
            'attributes' => ['carNoList' => $carNoList]
        ];
        $this->loading();
        return $this;
    }

    /**
     * @param array $queryPacket
     * parkCode string Y 车场
     * queryType int Y 0:查询服务延期开始日期 1:查询服务延期结束日期及金额
     * delayType int Y 0:顺延 1:月结日 2:自然月 3:按自定义
     * serviceId string Y. 服务ID 按服务延期必填
     * cardId string Y. 卡ID 按卡延期必填
     * ==========queryType = 1=============
     * startDate string Y 延期开始时间
     * month int Y 月份
     * @return $this
     */
    public function queryExtendedServicesDetailV1(array $queryPacket = []): self
    {
        $this->uri = self::FUNC_URI;
        $this->name = '查询服务延期详情';
        $attributes = [];

        if ($serviceIdV1 = Arr::get($queryPacket, 'serviceId')) {
            $attributes['serviceId'] = $serviceIdV1;
            $attributes['delayMode'] = 1;
        } elseif ($cardId = Arr::get($queryPacket, 'cardId')) {
            $attributes['cardId'] = $cardId;
            $attributes['delayMode'] = 0;
        } else {
            $this->cancel = true;
            $this->errBox[] = '服务ID或卡ID不可为空';
        }

        $delayType = Arr::get($queryPacket, 'delayType');

        if (!in_array($delayType, self::DELAY_TYPES, true)) {
            $this->cancel = true;
            $this->errBox[] = '延期类型不是预期取值';
        }
        $attributes['delayType'] = $delayType;

        if (!$parkCode = Arr::get($queryPacket, 'parkCode')) {
            $this->cancel = true;
            $this->errBox[] = '车场编码不可为空';
        }
        $attributes['parkCode'] = $parkCode;

        switch (Arr::get($queryPacket, 'queryType')) {
            case 1:
                $serviceId = '3c.delay.qryenddateandmoney';
                if (!$startDate = Arr::get($queryPacket, 'startDate')) {
                    $this->cancel = true;
                    $this->errBox[] = '延期开始日期不可为空';
                }
                $attributes['startDate'] = Carbon::parse($startDate)->format('Y-m-d');

                if (!is_int($month = Arr::get($queryPacket, 'month'))) {
                    $this->cancel = true;
                    $this->errBox[] = '月份不可为空';
                }
                $attributes['month'] = $month;
                break;
            case 0:
            default:
                $serviceId = '3c.delay.qryrenewstartdate';
                break;
        }

        $this->hashPacket = [
            'serviceId' => $serviceId,
            'requestType' => $this->requestType,
            'attributes' => $attributes
        ];
        $this->loading();
        return $this;
    }

    /**
     * @param array $queryPacket
     * parkCode string Y 车场
     * serviceId string Y. 服务ID 按服务延期必填
     * cardId string Y. 卡ID 按卡延期必填
     * carNo string Y 车牌号
     * @return $this
     */
    public function queryUnusedExtendedServices(array $queryPacket = []): self
    {
        $this->uri = self::FUNC_URI;
        $this->name = '查询用户办理的月卡但未使用时间段';
        $serviceId = '3c.delay.qryunusedtimesection';
        $attributes = [];

        if (!$parkCode = Arr::get($queryPacket, 'parkCode')) {
            $this->cancel = true;
            $this->errBox[] = '车场编码不可为空';
        }
        $attributes['parkCode'] = $parkCode;

        if (!$carNo = Arr::get($queryPacket, 'carNo')) {
            $this->cancel = true;
            $this->errBox[] = '车牌不可为空';
        }
        $attributes['carNo'] = $carNo;

        if ($serviceIdV1 = Arr::get($queryPacket, 'serviceId')) {
            $attributes['serviceId'] = $serviceIdV1;
            $attributes['delayMode'] = 1;
        } elseif ($cardId = Arr::get($queryPacket, 'cardId')) {
            $attributes['cardId'] = $cardId;
            $attributes['delayMode'] = 0;
        } else {
            $this->cancel = true;
            $this->errBox[] = '服务ID或卡ID不可为空';
        }

        $this->hashPacket = [
            'serviceId' => $serviceId,
            'requestType' => $this->requestType,
            'attributes' => $attributes
        ];

        $this->loading();
        return $this;
    }

    /**
     * @param array $queryPacket
     * cardId string Y. 卡ID
     * serviceId string Y. 服务
     * parkCode string Y 车场
     * delayType int Y  0顺延 1月结日 2自然月 3按自定义
     * month int Y 月数
     * money float Y 金额 元
     * startDate string Y 开始时间 yyyy-MM-dd
     * endDate string Y 结束时间 yyyy-MM-dd
     * payType string N 支付方式 WX：微信 ZFB：支付 YL：银联 YZF：翼支付 YFB：易付宝 JSJK：捷顺金科（清分必传）YHJM：优惠全免（全免）OTHER：其他
     * @return $this
     */
    public function addExtendedServiceV1(array $queryPacket = []): self
    {
        $this->uri = self::FUNC_URI;
        $this->name = '查询用户办理的月卡但未使用时间段';
        $serviceId = '3c.delay.delaydown';
        $attributes = [];

        if (!$parkCode = Arr::get($queryPacket, 'parkCode')) {
            $this->cancel = true;
            $this->errBox[] = '车场编号不得为空';
        }
        $attributes['parkCode'] = $parkCode;

        $delayType = Arr::get($queryPacket, 'delayType');

        if (!in_array($delayType, self::DELAY_TYPES, true)) {
            $this->cancel = true;
            $this->errBox[] = '延期类型不是可预期的取值';
        }
        $attributes['delayType'] = $delayType;

        if ($cardId = Arr::get($queryPacket, 'cardId')) {
            $attributes['cardId'] = $cardId;
            $attributes['delayMode'] = 0;
        } elseif ($serviceIdV1 = Arr::get($queryPacket, 'serviceId')) {
            $attributes['serviceId'] = $serviceIdV1;
            $attributes['delayMode'] = 1;
        } else {
            $this->cancel = true;
            $this->errBox[] = '卡ID或服务ID为空';
        }

        $payType = Arr::get($queryPacket, 'payType');
        if (in_array($payType, array_keys(self::PAY_TYPES), true)) {
            $attributes['payType'] = $payType;
            $attributes['payName'] = Arr::get(self::PAY_TYPES, $payType);
        }

        if (!is_int($month = Arr::get($queryPacket, 'month'))) {
            $this->cancel = true;
            $this->errBox[] = '延期月份不可是非整数';
        }
        $attributes['month'] = $month;

        if (!is_numeric($money = Arr::get($queryPacket, 'money'))) {
            $this->cancel = true;
            $this->errBox[] = '延期金额必须是数字';
        }
        $attributes['money'] = doubleval(strval(intval($money * 100) / 100));

        if (!$startDate = Arr::get($queryPacket, 'startDate')) {
            $this->cancel = true;
            $this->errBox[] = '延期开始时间不可为空';
        }

        if (!$endDate = Arr::get($queryPacket, 'endDate')) {
            $this->cancel = true;
            $this->errBox[] = '延期结束时间不可为空';
        }

        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);
        if ($endDate->isBefore($startDate)) {
            $this->cancel = true;
            $this->errBox[] = '延期结束时间不可小于延期开始时间';
        }

        $attributes['startDate'] = $startDate->format('Y-m-d H:i:s');
        $attributes['endDate'] = $endDate->format('Y-m-d H:i:s');

        $this->hashPacket = [
            'serviceId' => $serviceId,
            'requestType' => $this->requestType,
            'attributes' => $attributes
        ];
        $this->loading();
        return $this;
    }

    //月卡延期（车场软件计算时间和费用）

    //无牌车（旧方案）

    /**
     * @param array $queryPacket
     * parkCode string Y 车场
     * equipCode string Y 设备编号
     * certificateNo string Y 入场凭证 唯一，例如可用微信openid作为凭证号
     * @return $this
     */
    public function addIntoCarCertificate(array $queryPacket = []): self
    {
        $queryPacket['offset'] = 1;
        return self::addCarCertificate($queryPacket);
    }

    /**
     * @param array $queryPacket
     * parkCode string Y 车场
     * equipCode string Y 设备编号
     * certificateNo string Y 入场凭证 唯一，例如可用微信openid作为凭证号
     * queryUrl string N 反查url 出场时可传
     * @return $this
     */
    public function addOutCarCertificate(array $queryPacket = []): self
    {
        $queryPacket['offset'] = 0;
        return self::addCarCertificate($queryPacket);
    }

    /**
     * @param array $queryPacket
     * parkCode string Y 车场
     * equipCode string Y 设备编号
     * certificateNo string Y 入场凭证 唯一，例如可用微信openid作为凭证号
     * offset int Y 1:进场 0:出场
     * queryUrl string N 反查url 出场时可传
     * @return $this
     */
    public function addCarCertificate(array $queryPacket = []): self
    {
        $this->uri = self::FUNC_URI;
        $this->name = '无牌车入场扫码';
        $attributes = [];

        if (Arr::get($queryPacket, 'offset')) {
            $serviceId = '3c.park.downcarcertificate';
        } else {
            $serviceId = '3c.order.generateuncarorder';
            if ($queryUrl = Arr::get($queryPacket, 'queryUrl')) {
                $attributes['queryUrl'] = $queryUrl;
            }
        }

        if (!$parkCode = Arr::get($queryPacket, 'parkCode')) {
            $this->cancel = true;
            $this->errBox[] = '车场编号不可为空';
        }
        $attributes['parkCode'] = $parkCode;

        if (!$equipCode = Arr::get($queryPacket, 'equipCode')) {
            $this->cancel = true;
            $this->errBox[] = '设备编号不可为空';
        }
        $attributes['equipCode'] = $equipCode;

        if (!$certificateNo = Arr::get($queryPacket, 'certificateNo')) {
            $this->cancel = true;
            $this->errBox[] = '设备编号不可为空';
        }
        $attributes['certificateNo'] = $certificateNo;

        $this->hashPacket = [
            'serviceId' => $serviceId,
            'requestType' => $this->requestType,
            'attributes' => $attributes
        ];
        $this->loading();
        return $this;
    }

    //无牌车（旧方案）

    //无牌车（新方案）

    /**
     * @param array $queryPacket
     * parkCode string Y 车场
     * openId string Y 微信openid
     * userId string N 用户ID
     * equipCode string Y 设备ID
     * personId string N 人员ID
     * personName string N 月租车获取虚拟车牌时
     * telephone string N 电话
     * plateType int N 0:机动车 1:非机动车
     * voucherType int N 0:临时车 1:月租车
     * queryUrl string N 反查地址
     * @return $this
     */
    public function addIntoCarCertificateV1(array $queryPacket = []): self
    {
        $this->uri = self::FUNC_URI;
        $this->name = '无牌车入场扫码';
        $serviceId = '3c.park.downincarno';
        $attributes = [];

        if (!$parkCode = Arr::get($queryPacket, 'parkCode')) {
            $this->cancel = true;
            $this->errBox[] = '车场编号不可为空';
        }
        $attributes['parkCode'] = $parkCode;

        if (!$openId = Arr::get($queryPacket, 'openId')) {
            $this->cancel = true;
            $this->errBox[] = '微信openid不可为空';
        }
        $attributes['openId'] = $openId;

        if ($userId = Arr::get($queryPacket, 'userId')) {
            $attributes['userId'] = $userId;
        }

        if (!$equipCode = Arr::get($queryPacket, 'equipCode')) {
            $this->cancel = true;
            $this->errBox[] = '设备串号不可为空';
        }
        $attributes['equipCode'] = $equipCode;

        if ($personId = Arr::get($queryPacket, 'personId')) {
            $attributes['personId'] = $personId;
        }

        if ($personName = Arr::get($queryPacket, 'personName')) {
            $attributes['personName'] = $personName;
        }

        if ($telephone = Arr::get($queryPacket, 'telephone')) {
            $attributes['telephone'] = $telephone;
        }

        $plateType = Arr::get($queryPacket, 'plateType');
        $voucherType = Arr::get($queryPacket, 'voucherType');

        if (in_array($plateType, self::PLATE_TYPES, true)) {
            $attributes['plateType'] = $plateType;
        }

        if (in_array($voucherType, self::VOUCHER_TYPES, true)) {
            $attributes['voucherType'] = $voucherType;
        }

        if ($queryUrl = Arr::get($queryPacket, 'queryUrl')) {
            $attributes['queryUrl'] = $queryUrl;
        }

        $this->hashPacket = [
            'serviceId' => $serviceId,
            'requestType' => $this->requestType,
            'attributes' => $attributes
        ];
        $this->loading();

        return $this;
    }

    /**
     * @param array $queryPacket
     * parkCode string Y 车场
     * openId string N 微信openid 当plateType为无牌车时必传
     * equipCode string Y 设备ID
     * plateType int N 0:机动车 1:非机动车
     * couponType string N 优惠类型，0：减免金额，1：减免时间，2：全免
     * couponValue int N 1、优惠类型为金额时，单位为元；2、优惠类型为时间时，单位为小时；3、优惠类型为全免时，值为1，但无直接意义
     * couponSource string N 优惠来源
     * couponList string N 优惠列表详情
     * ｜- json数组格式：例：[{“name”:1,“type”:0,“value”:5,“code”: “123456”}]
     * ｜- name: 抵扣方式，0：积分抵扣，1：抵扣券，2：购物小票
     * ｜- type：优惠方式，0：金额减免，1：时间减免，2：全免
     * ｜- value：优惠值：
     * ｜- ①优惠类型为金额时，单位：元；
     * ｜- ②优惠类型为时间时，单位：小时；
     * ｜- ③优惠类型为全免时，值为1，但无直接意义
     * ｜- code：会员id/抵扣券号/购物小票号
     * ｜- 注：couponList和couponValue参数默认优先使用couponList。
     * @return $this
     */
    public function addOutCarCertificateV1(array $queryPacket = []): self
    {
        $this->uri = self::FUNC_URI;
        $this->name = '无牌车出场扫码';
        $serviceId = '3c.park.downoutcarno';
        $attributes = [];

        $openId = Arr::get($queryPacket, 'openId');
        if (!$parkCode = Arr::get($queryPacket, 'parkCode')) {
            $this->cancel = true;
            $this->errBox[] = '车场编号不可为空';
        }
        $attributes['parkCode'] = $parkCode;

        if (!$equipCode = Arr::get($queryPacket, 'equipCode')) {
            $this->cancel = true;
            $this->errBox[] = '微信openid不可为空';
        }
        $attributes['equipCode'] = $equipCode;

        $plateType = Arr::get($queryPacket, 'plateType');
        if (in_array($plateType, self::PLATE_TYPES_V1, true)) {
            $attributes['plateType'] = $plateType;
            if ($plateType === self::PLATE_TYPE_UNLICENSED_VEHICLES) {
                if (!$openId) {
                    $this->cancel = true;
                    $this->errBox[] = '微信openid不可为空';
                }
            }
        }
        $attributes['openId'] = $openId;

        $couponType = Arr::get($queryPacket, 'couponType');

        if (in_array($couponType, self::COUPON_TYPES, true)) {
            $attributes['couponType'] = $couponType;
            if (is_int($couponValue = Arr::get($queryPacket, 'couponValue'))) {
                if ($couponType == self::COUPON_TYPE_ALL) {
                    $couponValue = 1;
                }
                $attributes['couponValue'] = $couponValue;
            }

            if ($couponSource = Arr::get($queryPacket, 'couponSource')) {
                $attributes['couponSource'] = $couponSource;
            }

            if ($couponList = Arr::get($queryPacket, 'couponList')) {
                $attributes['couponList'] = $couponList;
            }
        }

        $this->hashPacket = [
            'serviceId' => $serviceId,
            'requestType' => $this->requestType,
            'attributes' => $attributes
        ];
        $this->loading();

        return $this;
    }
    //无牌车（新方案）

    //车辆锁车/解锁

    /**
     * @param array $queryPacket
     * parkCode string Y 车场
     * carNo string Y 车牌
     * lockFlag int Y 0:锁车 1:解锁
     * featureCode string Y 特征码
     * @return $this
     */
    public function lockCar(array $queryPacket = []): self
    {
        $this->uri = self::FUNC_URI;
        $this->name = '用户发起锁车解锁请求';
        $serviceId = '3c.lock.lock';
        $attributes = [];

        if (!$parkCode = Arr::get($queryPacket, 'parkCode')) {
            $this->cancel = true;
            $this->errBox[] = '车场编号不可为空';
        }
        $attributes['parkCode'] = $parkCode;

        if (!$carNo = Arr::get($queryPacket, 'carNo')) {
            $this->cancel = true;
            $this->errBox[] = '车牌不可为空';
        }
        $attributes['carNo'] = $carNo;

        $lockFlag = Arr::get($queryPacket, 'lockFlag');
        if (!in_array($lockFlag, self::LOCK_CARS, true)) {
            $this->cancel = true;
            $this->errBox[] = '锁车标识是不可预期的取值';
        }
        $attributes['lockFlag'] = $lockFlag;

        if (!$featureCode = Arr::get($queryPacket, 'featureCode')) {
            $this->cancel = true;
            $this->errBox[] = '车场编号不可为空';
        }
        $attributes['featureCode'] = $featureCode;

        $this->hashPacket = [
            'serviceId' => $serviceId,
            'requestType' => $this->requestType,
            'attributes' => $attributes
        ];
        $this->loading();

        return $this;
    }

    /**
     * @param array $queryPacket
     * parkCode string Y 车场
     * carNo string Y 车牌
     * queryType int N 0:当前锁定车辆 1:锁定车辆历史记录
     * @return $this
     */
    public function queryLockedCar(array $queryPacket = []): self
    {
        $this->uri = self::FUNC_URI;
        $this->name = '查询当前锁定车辆';
        $attributes = [];

        switch (Arr::get($queryPacket, 'queryType')) {
            case 1:
                $serviceId = '3c.lock.querycarhistory';
                break;
            case 0:
            default:
                $serviceId = '3c.lock.querylockedcar';
                break;
        }

        if (!$parkCode = Arr::get($queryPacket, 'parkCode')) {
            $this->cancel = true;
            $this->errBox[] = '车场编号不可为空';
        }
        $attributes['parkCode'] = $parkCode;

        if (!$carNo = Arr::get($queryPacket, 'carNo')) {
            $this->cancel = true;
            $this->errBox[] = '车牌不可为空';
        }
        $attributes['carNo'] = $carNo;

        $this->hashPacket = [
            'serviceId' => $serviceId,
            'requestType' => $this->requestType,
            'attributes' => $attributes
        ];
        $this->loading();
        return $this;
    }
    //车辆锁车/解锁

    //工具方法

    protected function configValidator()
    {
        if (!$this->cid) {
            throw new InvalidArgumentException('客户号不可为空');
        }

        if (!$this->usr) {
            throw new InvalidArgumentException('账号不可为空');
        }

        if (!$this->psw) {
            throw new InvalidArgumentException('密码不可为空');
        }

        if (!$this->signKey) {
            throw new InvalidArgumentException('密钥不可为空');
        }

        if (!$this->businesserCode) {
            throw new InvalidArgumentException('商户号不可为空');
        }
    }

    protected function generateSignature(): string
    {
        $hashStr = json_encode($this->hashPacket);
        $this->sn = strtoupper(md5($hashStr . $this->signKey));
        return $this->sn;
    }

    public function fire()
    {
        $apiName = $this->name;
        $httpMethod = $this->httpMethod;
        $httpClient = new Client(['base_uri' => $this->gateway, 'timeout' => $this->timeout, 'verify' => false]);
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
            $rawResponse = $httpClient->$httpMethod($this->uri, $this->queryBody);
            $contentStr = $rawResponse->getBody()->getContents();
            $contentArr = @json_decode($contentStr, true);
            $this->logging->info($this->name, ['gateway' => $this->gateway, 'uri' => $this->uri, 'queryBody' => $this->queryBody, 'response' => $contentArr]);

            $this->cleanup();

            if ($rawResponse->getStatusCode() === 200) {
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
            } else {
                throw new RequestFailedException('接口请求失败:' . $apiName);
            }
        }
    }

    protected function loading()
    {
        $this->generateSignature();
        $this->uriPatch($this->uri, ['p' => json_encode(is_array($this->hashPacket) ? $this->hashPacket : [])]);
    }

    public function cacheKey(): string
    {
        return self::getCacheKey($this->usr);
    }

    public function injectToken(bool $refresh = false)
    {
        $cacheKey = $this->cacheKey();
        if (!($token = cache($cacheKey)) || $refresh) {
            $response = $this->login()->fire();
            if ($this->token = Arr::get($response, 'token') ?: Arr::get($response, 'raw_resp.token')) {
                cache([$cacheKey => $this->token], now()->addHour());
            }
        } else {
            $this->token = $token;
        }

        if (!$this->token) {
            throw new InvalidArgumentException('获取token失败');
        }
    }

    protected function uriPatch(string &$uri, array $uriParams, bool $auto = true)
    {
        if (!Arr::get($uriParams, 'cid')) {
            $uriParams['cid'] = $this->cid;
        }

        if ($auto) {
            $uriParams['tn'] = $this->getLocalToken($this->cacheKey());
            $uriParams['v'] = $this->v;
            $uriParams['sn'] = $this->sn;
        }

        if ($strParams = $this->join2Str($uriParams)) {
            $uri .= '?' . $strParams;
        }
    }

    protected function login(): self
    {
        $this->uri = self::LOGIN_URI;
        $this->name = '账号登录';
        $this->uriPatch($this->uri, ['usr' => $this->usr, 'psw' => $this->psw], false);
        return $this;
    }

    protected function formatResp(&$response)
    {
        if (Arr::get($response, 'resultCode') === 0) {
            $resPacket = ['code' => 0, 'msg' => 'SUCCESS', 'data' => Arr::get($response, 'dataItems') ?: [], 'raw_resp' => $response];
        } else {
            $resPacket = ['code' => 500, 'msg' => Arr::get($response, 'message'), 'data' => [], 'raw_resp' => $response];
        }
        $response = $resPacket;
    }

    public function getJhtdc()
    {
        return $this->jhtdc;
    }

    /**
     * @param $filePatches
     * @return mixed
     * @throws InvalidArgumentException
     * @throws RequestFailedException
     */
    public function fetchFileUrl($filePatches)
    {
        return $this->jhtdc->fetchFileUrl($filePatches);
    }

    static public function inputsAdapter(\Closure $closure)
    {
        return self::inputsAdapterCore($closure);
    }

    static protected function inputsAdapterCore(\Closure $closure)
    {
        $failed = null;
        $fmtMsg = $rawMsg = null;
        try {
            $rawMsg = file_get_contents('php://input');
            $rawMsgArr = json_decode($rawMsg, true);
            $dataItems = \Arr::get($rawMsgArr, 'dataItems');
            $fmtMsg = json_decode($dataItems, true);
            $fmtMsg = array_map(function ($value) {
                if ($vehicleInfo = \Arr::get($value, 'vehicleInfo')) {
                    $value['vehicleInfo'] = json_decode($vehicleInfo, true);
                }
                return $value;
            }, $fmtMsg ?: []);
        } catch (\Throwable $exception) {
            $failed = $exception->getMessage();
        }
        return call_user_func_array($closure, [$fmtMsg, $rawMsg, $failed]);
    }
}
