<?php

namespace On3\DAP\Platforms\UniUbi;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use On3\DAP\Exceptions\InvalidArgumentException;
use On3\DAP\Exceptions\RequestFailedException;
use On3\DAP\Platforms\Gateways;
use On3\DAP\Platforms\Platform;
use On3\DAP\Traits\DAPBaseTrait;

class WO extends Platform
{

    const CODES = [
        'WO_SUS1000' => '统一操作正确码',
        'WO_EXP-1000' => '统一操作错误码',
        'WO_EXP-5000' => '微服务熔断',
        'WO_EXP-5001' => '微服务超时',
        'WO_EXP-5002' => '系统返回解析错误',
        'WO_EXP-5003' => '方法不支持',
        'WO_EXP-5004' => 'Http 媒体类型不支持',
        'WO_EXP-5005' => '重试异常',
        'WO_EXP-5006' => '数据库 数据完整性异常',
        'WO_EXP-5007' => '空指针异常',
        'WO_EXP-5008' => '请求参数解析异常',
        'WO_EXP-5009' => '数据库 重复键异常',
        'WO_EXP-5011' => '微服务返回非预期',
        'WO_EXP-5012' => 'Json 解析异常',
        'WO_EXP-5013' => 'Http 消息不可读的异常',
        'WO_EXP-5014' => '请求参数检验异常',
        'WO_EXP-5015' => '请求的参数异常',
        'WO_EXP-5016' => '系统内部不支持的操作',
        'WO_EXP-6000' => 'session 过期',
        'WO_EXP-6001' => '日期格式错误',
        'WO_EXP-6002' => '非法许可',
        'WO_EXP-100' => '设备不存在',
        'WO_EXP-101' => '设备不属于该应用',
        'WO_EXP-102' => '设备未启动',
        'WO_EXP-103' => '设备序列号被占用',
        'WO_EXP-104' => '设备离线',
        'WO_EXP-105' => '设备状态已启用',
        'WO_EXP-106' => '设备状态已禁用',
        'WO_EXP-107' => '设备配置不存在',
        'WO_EXP-108' => '大屏背景图片格式错误',
        'WO_EXP-109' => '语音内容含有非法符号',
        'WO_EXP-110' => '语音模板格式错误',
        'WO_EXP-111' => '自定义内容格式错误',
        'WO_EXP-112' => '显示模板格式错误',
        'WO_EXP-113' => '串口模板格式错误',
        'WO_EXP-114' => '语音模式下自定义内容不能空',
        'WO_EXP-115' => '显示模式下自定义内容不能空',
        'WO_EXP-116' => '串口模式下自定义内容不能空',
        'WO_EXP-117' => '刷脸模式开关未定义',
        'WO_EXP-118' => '人像识别阈值错误',
        'WO_EXP-119' => '人像检测类型类型未定义',
        'WO_EXP-120' => '刷卡识别参数.刷卡模式开关类型未定义',
        'WO_EXP-121' => '刷卡识别参数.卡号传输接口类型未定义',
        'WO_EXP-122' => '刷卡识别参数.外接硬件类型未定义',
        'WO_EXP-123' => '卡&人像双重认证.卡&人像双重认证开关类型未定义',
        'WO_EXP-124' => '卡&人像双重认证.人像识别阈值错误',
        'WO_EXP-125' => '卡&人像双重认证.卡号传输接口类型未定义',
        'WO_EXP-126' => '卡&人像双重认证.外接硬件类型类型未定义',
        'WO_EXP-127' => '人证比对参数:人证比对开关未定义',
        'WO_EXP-128' => '人证比对参数:人像识别阈值未定义',
        'WO_EXP-129' => '人证比对参数:卡号传输接口未定义',
        'WO_EXP-130' => '人证比对参数:外接硬件类型未定义',
        'WO_EXP-131' => '识别成功参数:语音播类型未定义',
        'WO_EXP-132' => '识别成功参数:屏幕显示文字 1 类型未定义',
        'WO_EXP-133' => '识别成功参数:屏幕显示文字 2 类型未定义',
        'WO_EXP-134' => '识别成功参数:串口输出类型未定义',
        'WO_EXP-135' => '识别成功参数:韦根输出类型未定义',
        'WO_EXP-136' => '识别成功参数:继电器输出类型未定义',
        'WO_EXP-137' => '识别失败参数:识别失败开关未定义',
        'WO_EXP-138' => '识别失败参数:判定次数未定义',
        'WO_EXP-139' => '识别失败参数:语音播报类型未定义',
        'WO_EXP-140' => '识别失败参数:屏幕显示文字类型未定义',
        'WO_EXP-141' => '识别失败参数:串口输出类型未定义',
        'WO_EXP-142' => '识别失败参数:韦根输出类型未定义',
        'WO_EXP-143' => '识别失败参数:继电器输出类型未定义',
        'WO_EXP-144' => '权限不足参数:语音播报未定义',
        'WO_EXP-145' => '权限不足参数:屏幕显示文字 1 类型未定义',
        'WO_EXP-146' => '权限不足参数:屏幕显示文字 2 类型未定义',
        'WO_EXP-147' => '权限不足参数:串口输出类型未定义',
        'WO_EXP-148' => '权限不足参数:韦根输出类型未定义',
        'WO_EXP-149' => '权限不足参数:继电器输出类型未定义',
        'WO_EXP-150' => '识别通用参数:时间窗未定义',
        'WO_EXP-151' => '识别通用参数:识别等级未定义',
        'WO_EXP-152' => '识别通用参数:识别距离未定义',
        'WO_EXP-153' => '识别通用参数:继电器控制时间未定义',
        'WO_EXP-154' => '固定显示参数:屏幕方向未定义',
        'WO_EXP-155' => '固定显示参数:显示文字内容 1 未定义',
        'WO_EXP-156' => '固定显示参数:显示文字内容 2 未定义',
        'WO_EXP-157' => '设备操作时数量超过上限',
        'WO_EXP-158' => 'deviceKeys 不能为空',
        'WO_EXP-159' => '部分设备不存在',
        'WO_EXP-160' => '识别模式硬件 TTL 接口重复',
        'WO_EXP-161' => '识别模式硬件 232 接口重复',
        'WO_EXP-162' => '硬件类型只能为 IC 读卡器',
        'WO_EXP-163' => '硬件类型只能为 ID 读卡器 ',
        'WO_EXP-164' => '设备识别模式参数错误',
        'WO_EXP-165' => '禁用界面图片格式错误',
        'WO_EXP-166' => '显示图片 1 格式错误',
        'WO_EXP-167' => '显示图片 2 格式错误',
        'WO_EXP-170' => '设备已禁用',
        'WO_EXP-171' => '韦根自定义内容不能空',
        'WO_EXP-172' => '韦根自定义内容格式错误',
        'WO_EXP-173' => '识别模式硬件 I2C 接口重复',
        'WO_EXP-174' => '人证比对参数: 身份证校验开关未定义',
        'WO_EXP-175' => '设备绑定异常',
        'WO_EXP-200' => '设备序列号不存在',
        'WO_EXP-201' => '设备序列号状态无效',
        'WO_EXP-300' => '识别集合不存在',
        'WO_EXP-301' => '识别集合名称不可为空',
        'WO_EXP-302' => '应用默认识别集合不可删除',
        'WO_EXP-303' => '识别集合与应用仍有关联',
        'WO_EXP-304' => '识别集合达到人数上限',
        'WO_EXP-305' => '操作的识别集合数量超过上限',
        'WO_EXP-400' => '人员不存在',
        'WO_EXP-401' => '人员的 guid 是必填项',
        'WO_EXP-402' => '人员在这个 personset 中不存在',
        'WO_EXP-403' => '人员名称不可为空',
        'WO_EXP-404' => '人员不属于此设备',
        'WO_EXP-405' => '人员名称是必填项',
        'WO_EXP-406' => '识别方式是必填项',
        'WO_EXP-407' => '操作的人员数量超过上限',
        'WO_EXP-408' => '人员 guids 不能为空',
        'WO_EXP-409' => '部分人员不存在',
        'WO_EXP-410' => '人员已在该 personset 中存在',
        'WO_EXP-430' => '导入人员 excel url 不正确',
        'WO_EXP-431' => '单次导入人员过多',
        'WO_EXP-432' => '人员姓名不唯一',
        'WO_EXP-433' => '导入文件解析失败',
        'WO_EXP-434' => 'excel 不能为空',
        'WO_EXP-435' => 'excel 序号不符合规则',
        'WO_EXP-436' => '当前任务正在进行，请稍后',
        'WO_EXP-437' => '文件内容填写有误，请重试',
        'WO_EXP-500' => 'FACE 不存在',
        'WO_EXP-501' => '照片过大',
        'WO_EXP-502' => 'img 为空,可能未传输或照片过大',
        'WO_EXP-503' => '照片数量超过限制',
        'WO_EXP-504' => '照片注册失败',
        'WO_EXP-505' => '照片相似度过低',
        'WO_EXP-530' => '人像照片命名格式不正确',
        'WO_EXP-531' => '人像照片格式错误',
        'WO_EXP-532' => '人像数量超过界限',
        'WO_EXP-600' => '时间段参数数量不正确或超出 3 段限制',
        'WO_EXP-601' => '时间段参数后时间段早于前时间段',
        'WO_EXP-602' => '时间段参数超出限制',
        'WO_EXP-603' => '时间段参数格式错误',
        'WO_EXP-604' => '设备识别模式错误',
        'WO_EXP-605' => '权限有效期参数数量不正确或超出 1 段限制',
        'WO_EXP-606' => '权限有效期参数格式错误',
        'WO_EXP-607' => '权限有效期参数后时间段早于前时间段或小于当前时间',
        'WO_EXP-608' => '权限有效期参数后时间段早于前时间段或小于当前时间',
        'WO_EXP-700' => '设备交互模式参数错误',
        'WO_EXP-701' => '设备交互模式参数错误',
        'WO_EXP-800' => '固件包不存在',
        'WO_EXP-801' => '固件包硬件版本不匹配',
        'WO_EXP-900' => '设备注册模式参数错误',
        'WO_EXP-901' => '设备注册模式任务没找到',
        'WO_EXP-902' => '设备注册模式人员 Id 缺失',
        'WO_EXP-903' => '设备注册模式 type 缺失',
        'WO_EXP-904' => '设备注册模式 type 错误',
        'WO_EXP-1001' => '服务处理异常，请稍后重试',
        'WO_EXP-1100' => '设备硬件不支持',
        'WO_EXP-1101' => '设备序列号长度错误',
        'WO_EXP-1102' => '产品编码错误',
        'WO_EXP-1200' => '签名不合法',
        'WO_EXP-1201' => '时间戳不合法',
        'WO_EXP-1202' => '请求头不合法',
        'WO_EXP-1203' => 'token 不合法',
        'WO_EXP-1204' => '链接不合法',
        'WO_EXP-1205' => '二维码签名不合法',
        'WO_EXP-1700' => '应用不存在',
        'WO_EXP-1701' => '用户 guid 必填',
        'WO_EXP-1702' => '应用状态不符合规范',
        'WO_EXP-1703' => '应用已下线',
        'WO_EXP-1800' => '回调钩子类型参数错误',
        'WO_EXP-1801' => '识别记录回调钩子为空',
        'WO_EXP-1802' => '重试识别记录功能的任务已满，请稍后重试',
        'WO_EXP-1900' => '参数格式错误',
        'WO_EXP-1901' => '设备名称长度过长',
        'WO_EXP-2000' => '门禁控制板 superPassword 校验失败',
        'WO_EXP-50000' => '账号或者密码错误',
        'WO_EXP-50001' => '令牌过期',
        'WO_EXP-50002' => '项目名称已经存在',
        'WO_EXP-50003' => '项目申请数量超过最高限额',
        'WO_EXP-50004' => '项目 key 不存在',
        'WO_EXP-50005' => '授权组不存在',
        'WO_EXP-50006' => '授权组中数量超过最大限制',
        'WO_EXP-50007' => '没有权限访问此平台',
        'WO_EXP-50008' => '参数错误',
        'WO_EXP-50009' => '时间戳错误',
        'WO_EXP-50010' => '用户已经存在',
        'WO_EXP-50011' => '手机或邮件已经存在',
        'WO_EXP-50012' => '签名失败',
        'WO_EXP-50013' => '项目状态错误',
        'WO_EXP-50014' => '参数解析失败',
        'WO_EXP-50015' => '获取锁失败',
        'WO_EXP-50016' => '用户不存在',
        'WO_EXP-50017' => '识别主体不存在',
        'WO_EXP-50018' => '调用远程方法失败',
        'WO_EXP-50019' => '项目不存在',
        'WO_EXP-50020' => '记录不存在',
        'WO_EXP-50021' => '重复操作',
        'WO_EXP-50022' => '用户不一致',
        'WO_EXP-50023' => '项目关闭',
        'WO_EXP-50024' => '人员集合不存在',
        'WO_EXP-50025' => '手机和邮箱不能为空',
        'WO_EXP-50026' => '用户帐户必须是手机或邮件',
        'WO_EXP-50027' => '识别主体 ID 不能为空',
        'WO_EXP-50028' => '项目关系不存在',
        'WO_EXP-50029' => '用户查询行超过最大限制',
        'WO_EXP-50031' => '这个设备不存在',
        'WO_EXP-50032' => '设备重置失败',
        'WO_EXP-50033' => '识别主体授权失败',
        'WO_EXP-50034' => '授权组的名称不能为空',
        'WO_EXP-100001' => '参数不能为空',
        'WO_EXP-100002' => '参数过长',
        'WO_EXP-100003' => '用户 ID 不能为空',
        'WO_EXP-100004' => '识别主体不能为空',
        'WO_EXP-100005' => '授权组不能为空',
        'WO_EXP-100006' => '项目 ID 不能为空',
        'WO_EXP-100007' => '人像 Id 不能为空',
        'WO_EXP-100008' => '图片地址或照片 base64 或文件不能都为空',
        'WO_EXP-100009' => '识别主体不能超过 3 张照片',
        'WO_EXP-100010' => '授权组删除类型不能为空',
        'WO_EXP-100011' => '分区 ID 不能为空',
        'WO_EXP-100012' => '人像相似度过低',
        'WO_EXP-100013' => '存储位置不能为空',
        'WO_EXP-100014' => '识别主体的人像为空',
        'WO_EXP-100015' => '必须只有一张人像',
        'WO_EXP-100016' => '没有检测到人像',
        'WO_EXP-100017' => '识别主体不能为空',
        'WO_EXP-100018' => '人像太小',
        'WO_EXP-100019' => '人像超出或过于靠近图片边界',
        'WO_EXP-100020' => '人像过于模糊',
        'WO_EXP-100021' => '人像光照过暗',
        'WO_EXP-100022' => '人像光照过亮',
        'WO_EXP-100023' => '人像左右亮度不对称',
        'WO_EXP-100024' => '三维旋转之俯仰角度过大',
        'WO_EXP-100025' => '三维旋转之左右旋转角过大',
        'WO_EXP-100026' => '平面内旋转角过大',
        'WO_EXP-100027' => '提供的图片文件由于文件不完整或格式不对而无法进行识别',
        'WO_EXP-100028' => '授权下发位置非法',
        'WO_EXP-100029' => '权限结束时间应该晚于开始时间',
        'WO_EXP-100030' => '区间权限结束时间应当晚于开始时间',
        'WO_EXP-100031' => '时间参数非法',
        'WO_EXP-100032' => '调用微信接口失败',
        'WO_EXP-100033' => '微信关联账号不存在',
        'WO_EXP-100034' => '微信账号未绑定',
        'WO_EXP-100035' => '微信账号注册失败',
        'WO_EXP-100036' => '微信手机已经注册',
        'WO_EXP-100037' => '微信获取 session 失败',
        'WO_EXP-100038' => '微信解密失败',
        'WO_EXP-100039' => '微信解密获取用户手机失败',
        'WO_EXP-100040' => '微信账号已经绑定',
        'WO_EXP-100041' => '上传异常',
        'WO_EXP-84001' => '消息模型已存在',
        'WO_EXP-84002' => '父级消息模型不存在',
        'WO_EXP-84003' => '消息模型不存在',
        'WO_EXP-20001' => '保存设备异常',
        'WO_EXP-20002' => '设备绑定异常',
        'WO_EXP-20003' => '更新设备信息异常',
        'WO_EXP-20004' => '删除设备信息异常',
        'WO_EXP-20005' => '更新设备状态异常',
        'WO_EXP-20006' => '设备不存在',
        'WO_EXP-20007' => '鉴权失败',
        'WO_EXP-20008' => '下发指令处理异常',
        'WO_EXP-20009' => '未能删除授权组设备关系',
        'WO_EXP-20010' => '设备已存在',
        'WO_EXP-20011' => '创建设备失败',
        'WO_EXP-20012' => '查询项目信息错误',
        'WO_EXP-20013' => '设备分配 IP 异常',
        'WO_EXP-20014' => '设备和授权组绑定失败',
        'WO_EXP-20015' => '绑定设备异常',
        'WO_EXP-20016' => '设备配置获取失败',
        'WO_EXP-20017' => '设置设备配置信息异常',
        'WO_EXP-20018' => '识别主体不存在人像信息',
        'WO_EXP-20019' => '设备没有任何授权组',
        'WO_EXP-20020' => '参数为空',
        'WO_EXP-20021' => '设备注册 IOT 失败',
        'WO_EXP-20022' => '设备列表为空',
        'WO_EXP-20023' => '设备授权组已绑定',
        'WO_EXP-20024' => '设备授权组解绑失败',
        'WO_EXP-20025' => '项目查询失败',
        'WO_EXP-20026' => '项目不匹配',
        'WO_EXP-20027' => '设备不在线',
        'WO_EXP-20028' => '设备 ID 不能为空',
        'WO_EXP-20029' => '参数不能为空',
        'WO_EXP-20030' => '需要设备 ID 或设备序列号',
        'WO_EXP-20031' => '时间戳不能为空',
        'WO_EXP-20032' => '签名不能为空',
        'WO_EXP-20033' => '版本不能为空',
        'WO_EXP-20034' => '项目 key 不能为空',
        'WO_EXP-20035' => '命令异常',
        'WO_EXP-20036' => '人像搜索失败',
        'WO_EXP-20037' => '项目 ID 不能为空',
        'WO_EXP-20038' => '设备 Id 和设备序列号不能为空',
        'WO_EXP-20039' => '分区 ID 不能为空',
        'WO_EXP-20040' => '参数异常',
        'WO_EXP-20041' => '获取设备初始信息失败',
        'WO_EXP-20042' => '平台不能为空',
        'WO_EXP-20043' => '用户 ID 不能为空',
        'WO_EXP-20044' => '设备序列号不能为空',
        'WO_EXP-20045' => '产品 key 不能为空',
        'WO_EXP-20046' => '设备序列号必须 16 位',
        'WO_EXP-20047' => '设备 ID 不能为空',
        'WO_EXP-20048' => '授权组 ID 不能为空',
        'WO_EXP-20049' => '设备配置为空',
        'WO_EXP-20050' => '执行任务 Id 为空',
        'WO_EXP-20051' => '识别主体 ID 为空',
        'WO_EXP-20052' => '识别主体名称为空',
        'WO_EXP-20053' => '内容不能为空',
        'WO_EXP-20054' => '任务 ID 不能为空',
        'WO_EXP-20055' => '创建时间为空',
        'WO_EXP-20056' => '确定时间为空',
        'WO_EXP-20057' => '用户 ID 为空',
        'WO_EXP-20058' => '设备序列号为空',
        'WO_EXP-20059' => '操作类型为空',
        'WO_EXP-20060' => '更新包不存在',
        'WO_EXP-20061' => '算法 So 初始化失败',
        'WO_EXP-20062' => '算法 SO 的路径为空',
        'WO_EXP-20063' => '算法 SO 未注册',
        'WO_EXP-20064' => '签名为空',
        'WO_EXP-20065' => '时间戳为空',
        'WO_EXP-20066' => 'accessKey 为空',
        'WO_EXP-20067' => '设备唯一标识为空',
        'WO_EXP-20068' => '签名不正确',
        'WO_EXP-20069' => '时间戳非法',
        'WO_EXP-20071' => '设备数量超过应用最大限制 1000 个',
        'WO_EXP-20072' => '指令下发异常',
        'WO_EXP-20073' => '接口不支持',
        'WO_EXP-20075' => '调用 mqtt 服务失败',
        'WO_EXP-20076' => '传入消息不是 JSON 格式',
        'WO_EXP-20077' => '不支持',
        'WO_EXP-41001' => '参数为空',
        'WO_EXP-41002' => 'accessKey 为空',
        'WO_EXP-41003' => '授权组 ID 为空',
        'WO_EXP-41004' => '人像 ID 为空',
        'WO_EXP-41005' => '人像最大索引为空',
        'WO_EXP-41006' => '初始算法缓存失败',
        'WO_EXP-41007' => '设备序列号为空',
        'WO_EXP-41008' => '人员 ID 为空',
        'WO_EXP-41009' => '特征值为空',
        'WO_EXP-41010' => '算法版本为空',
        'WO_EXP-41011' => '时间非法',
        'WO_EXP-41012' => '授权组 ID 为空',
        'WO_EXP-41013' => '账号 ID 为空',
        'WO_EXP-41014' => '参数太多',
        'WO_EXP-41015' => '授权组类型为空',
        'WO_EXP-41016' => '授权组类型非法',
        'WO_EXP-41017' => '设备序列号为空',
        'WO_EXP-41018' => '每页限制 50',
        'WO_EXP-41019' => '开始时间小于结束时间',
        'WO_EXP-41020' => '操作人像数量限制为 50',
        'WO_EXP-41021' => '授权组已存在',
        'WO_EXP-41022' => '无数据',
        'WO_EXP-41023' => '人像不存在',
        'WO_EXP-41024' => '授权组不存在',
        'WO_EXP-41025' => '人员已经绑定到授权组',
        'WO_EXP-41026' => '设备已经绑定到授权组',
        'WO_EXP-41027' => '授权组未授权该设备',
        'WO_EXP-41028' => '授权组未授权该人员',
        'WO_EXP-41029' => '人员不存在',
        'WO_EXP-41030' => '设备未添加到授权组中',
        'WO_EXP-41031' => '获取锁失败',
        'WO_EXP-41032' => '请求量超出限制',
        'WO_EXP-41033' => '下载照片的请求量超出了限制',
        'WO_EXP-30001' => '设备 ID 为空',
        'WO_EXP-30003' => '照片处理异常',
        'WO_EXP-30004' => '参数为空',
        'WO_EXP-30005' => '设备序列号为空',
        'WO_EXP-30006' => '设备不存在',
        'WO_EXP-30007' => '通道为空',
        'WO_EXP-30008' => '状态为空',
        'WO_EXP-30009' => '配置为空',
        'WO_EXP-30010' => '用户 Id 为空',
        'WO_EXP-30011' => '应用为空',
        'WO_EXP-30012' => '统一账号为空',
        'WO_EXP-30013' => '产品 KEY 为空',
        'WO_EXP-30014' => '无效通道',
        'WO_EXP-30015' => '用户 ID 错误',
        'WO_EXP-30016' => '应用 ID 错误',
        'WO_EXP-30017' => '配置保存异常',
        'WO_EXP-30018' => '事件保存异常',
        'WO_EXP-30019' => '通道名称长度再 0 到 32',
        'WO_EXP-30020' => '无效模型类型',
        'WO_EXP-30021' => '分数范围 0 到 100',
        'WO_EXP-30022' => '质量范围 0 到 100',
        'WO_EXP-30023' => '检测范围在 30 到 500',
        'WO_EXP-30024' => '查询配置异常',
        'WO_EXP-30025' => '设备离线',
        'WO_EXP-30026' => '停车场参数为空',
        'WO_EXP-30027' => '设备不属于该用户',
        'WO_EXP-30028' => '设备不属于该应用',
        'WO_EXP-30029' => '设备需要被添加到平台上',
        'WO_EXP-30030' => '设备 MAC 地址非法',
        'WO_EXP-30031' => '事件缺失事件类型',
        'WO_EXP-60000' => '用户或应用不存在',
        'WO_EXP-60001' => '用户 Id 不存在',
        'WO_EXP-60002' => '应用 Id 不存在',
        'WO_EXP-60003' => '分页数量上限 50',
        'WO_EXP-60004' => '车牌号码不能为空',
        'WO_EXP-60005' => '参数错误',
        'WO_EXP-60006' => '服务连接异常',
        'WO_EXP-60007' => 'ID 不存在',
        'WO_EXP-60008' => '车牌号已经存在',
        'WO_EXP-60009' => '获取锁失败',
        'WO_EXP-60010' => 'mac 地址非法',
        'WO_EXP-60011' => '车位组名称已存在',
        'WO_EXP-60012' => '设备离线',
        'WO_EXP-60013' => '设备 ID 不存在',
        'WO_EXP-60014' => '请求参数解析异常',
        'WO_EXP-60015' => '停车场编码不足，请联系管理员',
        'WO_EXP-60016' => '停车场编码已经存在',
        'WO_EXP-10001' => '请求参数为空',
        'WO_EXP-10002' => 'accessKey 为空',
        'WO_EXP-10004' => '人像 ID 为空',
        'WO_EXP-10007' => '设备序列号为空',
        'WO_EXP-10009' => '人员 ID 为空',
        'WO_EXP-10010' => '特征值为空',
        'WO_EXP-10011' => '算法版本为空',
        'WO_EXP-10012' => '时间非法',
        'WO_EXP-10013' => '授权组 ID 为空',
        'WO_EXP-10014' => '账号 ID 为空',
        'WO_EXP-10015' => '参数太多',
        'WO_EXP-10016' => '授权组类型为空',
        'WO_EXP-10017' => '授权组类型非法',
        'WO_EXP-10018' => '设备序列号为空',
        'WO_EXP-10019' => '每页限制 100',
        'WO_EXP-10020' => '开始时间小于结束时间',
        'WO_EXP-10021' => '操作人像数量限制为 50',
        'WO_EXP-10022' => '授权组已存在',
        'WO_EXP-10023' => '授权组类型非法',
        'WO_EXP-10024' => '存储类型非法',
        'WO_EXP-10025' => '人员不存在',
        'WO_EXP-10026' => '人员集合为空',
        'WO_EXP-10027' => '设备未解绑授权组',
        'WO_EXP-10028' => '通道 ID 为空',
        'WO_EXP-10029' => '授权组已经绑定设备，请先解绑',
        'WO_EXP-10030' => '人像数据不存在',
        'WO_EXP-10031' => '授权组不存在',
        'WO_EXP-10032' => '人员已经绑定到授权组',
        'WO_EXP-10033' => '设备已经绑定到授权组',
        'WO_EXP-10034' => '授权组未授权该设备',
        'WO_EXP-10035' => '授权组未授权该人员',
        'WO_EXP-10036' => '人员不存在',
        'WO_EXP-10037' => '设备未添加到授权组中',
        'WO_EXP-10038' => '设备配置参数为空',
        'WO_EXP-10039' => '设备配置的 Key 为空',
        'WO_EXP-10040' => '设备配置 Value 为空',
        'WO_EXP-10041' => '设备 Value 或者 Explain 为空',
        'WO_EXP-10042' => '应用 ID 为空',
        'WO_EXP-10043' => '设备 ID 为空',
        'WO_EXP-10044' => '设备已存在',
        'WO_EXP-10045' => '设备配置不存在',
        'WO_EXP-10046' => '设备配置 map 参数为空',
        'WO_EXP-10047' => '设备配置状态为空',
        'WO_EXP-10048' => '设备默认配置所属者为空',
        'WO_EXP-10049' => '位点为空',
        'WO_EXP-10050' => '设备配置请求参数 Key、value 为空',
        'WO_EXP-10051' => '设备配置请求参数为空',
        'WO_EXP-10052' => '全局设备配置已存在',
        'WO_EXP-10053' => '设备的用户 ID 为空',
        'WO_EXP-10054' => '存储 ID 为空',
        'WO_EXP-10055' => '存储不存在',
        'WO_EXP-10056' => '事件查询时间为空',
        'WO_EXP-10057' => '用户 ID 为空',
        'WO_EXP-10058' => '事件类型为空',
        'WO_EXP-10060' => '获取锁失败',
        'WO_EXP-10061' => '设备配置的 Key 重复',
        'WO_EXP-10062' => '产品回调数据为空',
        'WO_EXP-10063' => '回调类型为空',
        'WO_EXP-10064' => '回调 URL 为空',
        'WO_EXP-10065' => '产品 KEY 为空',
        'WO_EXP-10066' => '人员数量和数据库不一致',
        'WO_EXP-10067' => '人员索引生成失败',
        'WO_EXP-10068' => '授权位置为空',
        'WO_EXP-10069' => '人像集合为空',
        'WO_EXP-10070' => '识别时间为空',
        'WO_EXP-10071' => '导出数量太大',
        'WO_EXP-10072' => '设备模型参数为空',
        'WO_EXP-10073' => '数据格式非法',
        'WO_EXP-10074' => '接口不支持'
    ];

    use DAPBaseTrait;

    protected $projectID;
    protected $appKey;
    protected $appSecret;
    protected $token;
    protected $headers = [];

    protected $apiVer = self::API_V2;

    const API_V1 = 'v1/';
    const API_V2 = 'v2/';
    const SOURCE_UFACE = '000000';

    public function __construct(array $config, bool $dev = false)
    {
        if ($gateway = Arr::get($config, 'gateway')) {
            $this->gateway = $gateway;
        } else {
            $this->gateway = $dev ? Gateways::WO_DEV : Gateways::WO;
        }

        $this->appKey = Arr::get($config, 'appKey');
        $this->appSecret = Arr::get($config, 'appSecret');
        $this->projectID = Arr::get($config, 'projectGuid');

        $this->injectLogObj();
        $this->configValidator();
        $this->injectToken();
    }

    public function injectToken(bool $refresh = false)
    {
        $cacheKey = self::getCacheKey();
        if (!($this->token = cache($cacheKey)) || $refresh) {
            $response = $this->auth()->fire();
            if ($this->token = Arr::get($response, 'data') ?: Arr::get($response, 'raw_resp.data')) {
                cache([$cacheKey => $this->token], now()->addHours(18));
            }
        }

        if (!$this->token) {
            throw new InvalidArgumentException('获取接口身份令牌失败');
        }
    }

    protected function configValidator()
    {
        if (!$this->projectID) {
            throw new InvalidArgumentException('项目ID不得为空');
        }

        if (!$this->appKey) {
            throw new InvalidArgumentException('应用key不得为空');
        }

        if (!$this->appSecret) {
            throw new InvalidArgumentException('应用密钥不得为空');
        }
    }

    protected function generateSignature()
    {
        // TODO: Implement generateSignature() method.
    }

    protected function formatResp(&$response)
    {
        if (Arr::get($response, 'success') === true) {
            $resPacket = ['code' => 0, 'msg' => 'SUCCESS'];
        } else {
            $msg = (Arr::get(self::CODES, Arr::get($response, 'code') ?: '') ?: Arr::get($response, 'msg')) ?: '请求发生未知异常';
            $resPacket = ['code' => 500, 'msg' => $msg];
        }
        $resPacket['data'] = Arr::get($response, 'data') ?: [];
        $resPacket['raw_resp'] = $response;
        $response = $resPacket;
    }

    protected function cleanup()
    {
        parent::cleanup();

        $this->apiVer = self::API_V2;
        $this->headers = [];
    }

    protected function auth(): self
    {
        $this->uri = $this->projectID . '/auth';
        $this->name = '接口鉴权';
        $this->apiVer = self::API_V1;
        $this->httpMethod = self::METHOD_GET;
        $timestamp = intval(microtime(true) * 1000);
        $this->headers = ['appKey' => $this->appKey, 'timestamp' => $timestamp, 'sign' => strtolower(md5($this->appKey . $timestamp . $this->appSecret))];
        $this->queryBody = ['projectGuid' => $this->projectID];
        return $this;
    }

    /**
     * @param array $queryPacket
     * SN string Y 设备序列号
     * name string N 设备名称
     * tag string N 设备标签 tag传入后，服务器会返回加密形式后的tag，可用于设备分类
     * act string N 操作 1:建立 2:更新
     * sceneID string N 场景ID
     * addition mixed N 扩展字段
     * bindDefaultScene bool N 是否绑定默认场景（场景 Guid 为空此字段生效）
     * @return $this
     */
    public function bindDevice(array $queryPacket = []): self
    {
        if ((Arr::get($queryPacket, 'act') ?: 1) === 1) {
            $this->name = '设备添加';
            $this->uri = 'device/create';
        } else {
            $this->name = '设备更新';
            $this->uri = 'device/update';
        }

        if (!$deviceNo = Arr::get($queryPacket, 'SN')) {
            $this->cancel = true;
            $this->errBox[] = '设备序列号不得为空';
        }

        if (!$name = Arr::get($queryPacket, 'name')) {
            $this->cancel = true;
            $this->errBox[] = '设备名称不得为空';
        }

        $tag = Arr::get($queryPacket, 'tag');
        $source = Arr::get($queryPacket, 'source') ?: self::SOURCE_UFACE;
        $sceneGuid = Arr::get($queryPacket, 'sceneID');
        $bindDefaultScene = boolval(Arr::get($queryPacket, 'bindDefaultScene'));
        $addition = Arr::get($queryPacket, 'addition');
        if (is_array($addition)) {
            $addition = @json_encode($addition);
        }
        $this->queryBody = compact('deviceNo', 'name', 'tag', 'sceneGuid', 'bindDefaultScene', 'addition', 'source');
        return $this;
    }

    /**
     * @param array $queryPacket
     * SN string Y 设备序列号
     * source string N 来源
     * @return $this
     */
    public function unBindDevice(array $queryPacket = []): self
    {
        $this->uri = 'device/delete';
        $this->name = '设备删除';

        $source = Arr::get($queryPacket, 'source') ?: self::SOURCE_UFACE;
        if (!$deviceNo = Arr::get($queryPacket, 'SN')) {
            $this->cancel = true;
            $this->errBox[] = '设备序列号不得为空';
        }

        $this->queryBody = compact('deviceNo', 'source');
        return $this;
    }

    /**
     * @param array $queryPacket
     * SN string Y 设备序列号
     * source string N 来源
     * @return $this
     */
    public function deviceInfo(array $queryPacket = []): self
    {
        $this->name = '设备详情';
        $this->uri = 'device/detail';

        $source = Arr::get($queryPacket, 'source') ?: self::SOURCE_UFACE;
        if (!$deviceNo = Arr::get($queryPacket, 'SN')) {
            $this->cancel = true;
            $this->errBox[] = '设备序列号不得为空';
        }

        $this->queryBody = compact('deviceNo', 'source');
        return $this;
    }

    /**
     * @param array $queryPacket
     * SN string N 设备序列号
     * source string N 来源
     * index int N 页码
     * length int N 每页个数
     * name string N 设备名称
     * tag string N 设备标签
     * versionNo string N 设备应用版本号
     * state string N 设备状态,1:未绑定 2:已绑定 3:已禁用
     * startTime string N 查询开始时间
     * endTime string N 查询结束时间
     * @return $this
     */
    public function deviceList(array $queryPacket = []): self
    {
        $this->uri = 'device/page';
        $this->name = '设备列表';
        $deviceNo = Arr::get($queryPacket, 'SN');
        $index = intval(Arr::get($queryPacket, 'index')) ?: 1;
        $length = min(intval(Arr::get($queryPacket, 'length')) ?: 50, 100);
        $source = Arr::get($queryPacket, 'source') ?: self::SOURCE_UFACE;
        $name = Arr::get($queryPacket, 'name');
        $tag = Arr::get($queryPacket, 'tag');
        $versionNo = Arr::get($queryPacket, 'versionNo');
        $state = Arr::get($queryPacket, 'state');
        $startTime = Arr::get($queryPacket, 'startTime');
        $endTime = Arr::get($queryPacket, 'endTime');
        $this->queryBody = compact('deviceNo', 'index', 'length', 'source', 'name', 'tag', 'versionNo', 'state', 'startTime', 'endTime');
        return $this;
    }

    /**
     * @param array $queryPacket
     * SN string Y 设备序列号
     * source string N 设备来源
     * @return $this
     */
    public function getDeviceConf(array $queryPacket = []): self
    {
        $this->uri = 'device/setting/query';
        $this->name = '设备配置查询';

        if (!$deviceNo = Arr::get($queryPacket, 'SN')) {
            $this->cancel = true;
            $this->errBox[] = '设备序列号不得为空';
        }

        if ($keys = Arr::get($queryPacket, 'keys')) {
            if (is_array($keys)) {
                $keys = implode(',', $keys);
            }
        }
        $source = Arr::get($queryPacket, 'source') ?: self::SOURCE_UFACE;
        $this->queryBody = compact('keys', 'deviceNo', 'source');
        return $this;
    }

    /**
     * @param array $queryPacket
     * SN string Y 设备序列号
     * source string N 设备来源
     * configs array Y 配置集合/多维数组
     * 示例 {
     * "source": "",
     * "deviceNo": "",
     * "deviceConfigs": [{
     *          "key": "antiTamper",
     *          "value": "1",
     *          "explain": null
     *          }]
     * }
     * bigScrUrl    固定显示参数.大屏背景图片 默认使用设备自带图片(jpg,jpeg,png,mp4)
     * cardEnable    刷卡识别参数.刷卡模式开关 1:打开(默认) 2:关闭
     * cardFaceEnable    卡&人像双重认证.卡&人像双重认证开关 1:打开 2:关闭(默认)
     * cardFaceHardware    卡&人像双重认证.外接硬件类型 1:IC 读卡器(默认) 2:新中新 3:精伦 4:中控
     * cardFaceIntf    卡&人像双重认证参数.卡号传输接口 1:USB 2:TTL 串口(MXXX2、MXXX6 系列默认) 3:232 串口（MXXX1 系列默认） 4:I²C（MXXX3 系列默认）
     * cardFaceScore    卡&人像双重认证.人像识别阈值 60(默认)
     * cardHardware    刷卡识别参数.外接硬件类型 1:IC 读卡器(默认) 2:新中新 3:精伦 4:中控
     * cardIntf    刷卡识别参数.卡号传输接口 1:USB 2:TTL 串口(MXXX2、MXXX6 系列默认) 3:232 串口（MXXX1 系列默认） 4:I²C（MXXX3 系列默认） USB（或 TTL 或 232 或 I²C）接口只能被一种识别模式使用，若>1 种模式使用 USB（或 TTL 或 232 或 I²C）则报错。
     * comRecDistModeType    识别通用参数.识别距离 *1：无限制 *2：0.5 米以内 *3：1 米以内（Uface C 默认） *4：1.5 米以内 *5：2 米以内 *6：3 米以内 *7：4 米以内 若识别等级选择 2 活体，则 1、4、5、6、7 不可选；若识别等级选择 1 非活体，则 1-7 均可选。
     * comRecRank    识别通用参数.识别等级 1:非活体 2:活体(默认)
     * comRecTimeWindow    识别通用参数.时间窗 60 秒(默认)
     * comRelayTime    识别通用参数.继电器控制时间 500ms(默认) 继电器控制开门到关门之间的时间间隔，默认 500ms。请输入 100-25500 之间的整数，向下取整百。如：输入 101-199 之间的整数，实际生效的是 100ms。
     * deviceKey    设备序列号
     * faceDetectionType    刷脸识别参数.人像检测类型 1:多人识别(默认) 2:单人识别
     * faceEnable    刷脸识别参数.刷脸模式开关 1:打开(默认) 2:关闭
     * timeZone    时区
     * repeatRegEnable    是否连续识别 1: 关（默认） 2: 开
     * languageType    中英文语言类型 zh_CN: 简体中文 en: 英文
     * timestamp    设备时间（时间戳）
     * faceScore    刷脸识别参数.人像识别阈值 80(默认) 实际允许 0-100 之间的所有整数。提示：请输入 50-100 之间的整数。分数越高，识别准确率越高，但识别速度会变慢。
     * idCardFaceEnable    人证比对参数.人证比对开关 1:打开 2:关闭(默认)
     * idCardFaceHardware    人证比对参数.外接硬件类型 1:IC 读卡器 2:新中新(默认) 3:精伦 4:中控
     * idCardFaceIntf    人证比对参数.卡号传输接口 1:USB 2:TTL 串口 3:232 串口(默认) 4:I²C
     * idCardFaceScore    人证比对参数.人像识别阈值 50(默认)
     * idCardVerificationEnable    人证比对参数.身份证校验（白名单）开关：1：关；2：开，默认是关闭
     * isShowDeviceKey    固定显示参数.是否显示设备序列号 true(默认) false
     * isShowIp    固定显示参数.是否显示 IP true(默认) false
     * isShowPersonCount    固定显示参数.是否显示人数 true(默认) false
     * recFailComModeContent    识别失败参数.串口输出自定义内容
     * recFailComModeType    识别失败参数.串口输出类型 1:开门 2:不输出(默认) 100:自定义
     * recFailDisplayTextContent    识别失败参数.屏幕显示文字自定义内容 内容只允许数字、中英文和中英文符号，长度限制 255 个字符。如：注意陌生人！
     * recFailDisplayTextType    识别失败参数.屏幕显示文字类型 1:识别失败(默认) 100:自定义
     * recFailEnable    识别失败参数.识别失败开关 1:打开(默认) 2:关闭
     * recFailRelayType    识别失败参数.继电器输出类型 1:输出 2:不输出(默认)
     * recFailTimesThreshold    识别失败参数.判定次数 3(默认) 打开识别失败开关后，该选项有效；连续比对 N 次都未达到分数阈值，则判定为识别失败，默认 3 次；传入值请选择 1-20 之间的整数，1 表示快速判定但精确率最低，随着数值增加，判定时间增加，精确度提高
     * recFailTtsModeContent    识别失败参数.语音播报自定义内容 内容只允许数字、英文和汉字，长度限制 255 个字符。如：注意陌生人
     * recFailTtsModeType    识别失败参数.语音播类型 1:识别失败(默认) 2:不播放 100:自定义
     * recFailWiegandContent    识别失败参数.韦根输出自定义内容
     * recFailWiegandType    识别失败参数.韦根输出类型 1:不输出(默认) 2:韦根 26 3:韦根 34
     * recNoPerComModeContent    权限不足参数.串口输出自定义内容
     * recNoPerComModeType    权限不足参数.串口输出类型 1:开门 2:不输出(默认) 3:输出 phone 4:输出 cardNo 100:自定义
     * recNoPerDisplayText1Content    权限不足参数.屏幕显示文字 1 自定义内容
     * recNoPerDisplayText1Type    权限不足参数.屏幕显示文字 1 类型 1:姓名(默认) 100:自定义
     * recNoPerDisplayText2Content    权限不足参数.屏幕显示文字 2 自定义内容
     * recNoPerDisplayText2Type    权限不足参数.屏幕显示文字 2 类型 1:权限不足(默认) 100:自定义
     * recNoPerRelayType    权限不足参数.继电器输出类型 1:输出 2:不输出(默认)
     * recNoPerTtsModeContent    权限不足参数.语音播报自定义内容 允许{name}、{tag}。字段格式固定，其他内容只允许数字、英文和汉字，长度限制 255 个字符。如：{name}无权通行
     * recNoPerTtsModeType    权限不足参数.语音播类型 1:播报姓名权限不足(默认) 2:不播放 100:自定义
     * recNoPerWiegandContent    权限不足参数.韦根输出自定义内容
     * recNoPerWiegandType    权限不足参数.韦根输出类型 1:不输出(默认) 2:韦根 26 3:韦根 34
     * recSucComModeContent    识别成功参数.串口输出自定义内容
     * recSucComModeType    识别成功参数.串口输出类型 1:开门(默认) 2:不输出 3:输出 phone 4:输出 cardNo 100:自定义
     * recSucDisplayText1Content    识别成功参数.屏幕显示文字 1 自定义内容
     * recSucDisplayText1Type    识别成功参数.屏幕显示文字 1 类型 1:姓名(默认) 100:自定义
     * recSucDisplayText2Content    识别成功参数.屏幕显示文字 2 自定义内容
     * recSucDisplayText2Type    识别成功参数.屏幕显示文字 2 类型 1:识别成功(默认) 100:自定义
     * recSucRelayType    识别成功参数.继电器输出类型 1:输出(默认) 2:不输出
     * recSucTtsModeContent    识别成功参数.语音播报自定义内容 允许{name}、{tag}。字段格式固定，其他内容只允许数字、英文和汉字，长度限制 255 个字符。如：{name}欢迎光临
     * recSucTtsModeType    识别成功参数.语音播类型 1:播报名字(默认) 2:不播放 100:自定义
     * recSucWiegandContent    识别成功参数.韦根输出自定义内容 允许{phone}、{cardNo}。字段格式固定且只能为数字或字母，其他内容只允许数字、英文和英文符号，长度限制 255 个字符。串口支持输出韦根信号，设备需要外接串口 → 韦根信号转换小板，小板由本公司定制。自定义内容传入格式：韦根 26：#26WG{cardNo}#，韦根 34：#34WG{cardNo}#注意：{cardNo}+数字组合后，韦根 26 范围为 1-65535（待定），有效范围为 5 位；韦根 34 范围为 1-4294967295（待定），有效范围为 10 位。若超出范围，则输出的信号会进行转换，输出无效信号。
     * recSucWiegandType    识别成功参数.韦根输出类型 1:不输出(默认) 2:韦根 26 3:韦根 34
     * scrDisableUrl    固定显示参数.禁用界面图片 默认使用设备自带图片(jpg,jpeg,png,mp4)
     * scrDisplayText1Content    固定显示参数.显示文字内容 1 自定义内容 长度限制 255
     * scrDisplayText1Type    固定显示参数.显示文字内容 1 类型 1:不显示(默认) 2:应用名称 100:自定义
     * scrDisplayText2Content    固定显示参数.显示文字内容 2 自定义内容 长度限制 255
     * scrDisplayText2Type    固定显示参数.显示文字内容 2 类型 1:不显示 2:设备名称(默认) 100:自定义
     * scrImage1Url    固定显示参数.显示图片 1 默认使用设备自带图片(jpg,jpeg,png,mp4)
     * scrImage2Url    固定显示参数.显示图片 2 默认无图片(jpg,jpeg,png,mp4)
     * scrOrntType    固定显示参数.屏幕方向 1:横屏(默认) 2:竖屏
     * uniquenessRegImage    注册照全局唯一，1 开，2 关
     * isTemperatureOpen    测温开关，1 开，2 关
     * tempUnit    测温单位，1 摄氏度，2 华氏度
     * isTempVoiceOpen    是否异常语音播放，1 开，2 关
     * errorTemperature    异常温度判断值
     * temperatureMeasurePlace    测温位置，1 额头，2 手腕
     * temperatureMeasureMin    有效温度最低值
     * temperatureMeasureMax    有效温度最高值
     * temperatureCompensation    低温补偿开关.1 开，2 关
     * tempMapSwitch    高温补偿开关，1 开，2 关
     * relaySwitchOnTempFail    温度继电器输出开关，1 开，2 关
     * wgSwitchOnTempFail    温度韦根输出开关，1 开，2 关
     * serialSwitchOnTempFail    温度异常串口输出开关，1 开，2 关
     * quickTemperatureMode    快速测温模式开关，1 开，2 关
     * isMaskOpen    口罩模式开关：1 开，2 关
     * isMaskVoiceOpen    是口罩检测异常语音播报：1 开，2 关
     * isMaskForceOpen    是否强制戴口罩：1 开，2 关
     * recModeQREnable    二维码开关 1 关（默认） 2 开
     * http_QRCode_url    二维码回调地址
     * recModePasswordEnable    密码识别开关 1 关，2 开（默认）
     * #
     * @return $this
     */
    public function configureDeviceConf(array $queryPacket = []): self
    {
        $this->uri = 'device/setting/update';
        $this->name = '设备配置';
        $source = Arr::get($queryPacket, 'source') ?: self::SOURCE_UFACE;
        if (!$deviceNo = Arr::get($queryPacket, 'SN')) {
            $this->cancel = true;
            $this->errBox[] = '设备序列号不得为空';
        }

        if (!$deviceConfigs = Arr::get($queryPacket, 'configs')) {
            $this->cancel = true;
            $this->errBox[] = '设备配置集合不得为空';
        }

        if (!is_array($deviceConfigs)) {
            $this->cancel = true;
            $this->errBox[] = '设备配置集合参数类型错误';
        }

        $this->queryBody = compact('deviceConfigs', 'deviceNo', 'source');
        return $this;
    }

    /**
     * @param array $queryPacket
     * index int N 页码
     * length int N 每页个数
     * type int N 版本类型 1:ota 2:app
     * hardwareVersion int N 硬件类型 1:1 代 2:2 代 3:3 代 6:C 10:2C 50:5C 51:5K 52:DV300 平板 57:DV300 模组 58:CV500 模组 59:无感系列 60:6C 61:6CC
     * source string N 设备来源
     * @return $this
     */
    public function getDevicePackageList(array $queryPacket = []): self
    {
        $this->uri = 'device/devicePackage';
        $this->name = '获取固件';

        $index = intval(Arr::get($queryPacket, 'index')) ?: 1;
        $length = min(intval(Arr::get($queryPacket, 'length')) ?: 50, 100);
        $source = Arr::get($queryPacket, 'source') ?: self::SOURCE_UFACE;
        $hardwareVersion = Arr::get($queryPacket, 'hardwareVersion');
        $this->queryBody = compact('index', 'length', 'source', 'hardwareVersion');
        return $this;
    }

    /**
     * @param array $queryPacket
     * SNs string Y 设备字串集合 可用,隔开
     * source string N 设备来源
     * @return $this
     */
    public function getDeviceStates(array $queryPacket = []): self
    {
        $this->uri = 'device/onlineState';
        $this->name = '设备在线状态';

        if (!$deviceNos = Arr::get($queryPacket, 'SNs')) {
            $this->cancel = true;
            $this->errBox[] = '设备序列号不得为空';
        }
        $source = Arr::get($queryPacket, 'source') ?: self::SOURCE_UFACE;
        $this->queryBody = compact('deviceNos', 'source');
        return $this;
    }

    /**
     * @param array $queryPacket
     * SN string Y 设备字串
     * source string N 设备来源
     * do int Y 1 重启 2：重置 3：启用 4 禁用
     * @return $this
     */
    public function commandDevice(array $queryPacket = []): self
    {
        $this->uri = 'device/command';
        $this->name = '设备操作指令下发';

        if (!$deviceNo = Arr::get($queryPacket, 'SN')) {
            $this->cancel = true;
            $this->errBox[] = '设备序列号不得为空';
        }

        $source = Arr::get($queryPacket, 'source') ?: self::SOURCE_UFACE;
        if (!$operateType = intval(Arr::get($queryPacket, 'do'))) {
            $this->cancel = true;
            $this->errBox[] = '设备操作不得为空';
        }

        $this->queryBody = compact('operateType', 'source', 'deviceNo');
        return $this;
    }

    /**
     * @param array $queryPacket
     * SNs string Y 设备字串集合 可用,隔开
     * packageID string Y 固件ID
     * @return $this
     */
    public function upgradeDeviceSys(array $queryPacket = []): self
    {
        $this->uri = 'device/upgrade';
        $this->name = '设备升级';

        if (!$deviceNos = Arr::get($queryPacket, 'SNs')) {
            $this->cancel = true;
            $this->errBox[] = '设备序列号不得为空';
        }
        $source = Arr::get($queryPacket, 'source') ?: self::SOURCE_UFACE;

        if (!$packageGuid = Arr::get($queryPacket, 'packageID')) {
            $this->cancel = true;
            $this->errBox[] = '固件ID';
        }

        $this->queryBody = compact('source', 'deviceNos', 'packageGuid');
        return $this;
    }

    /**
     * @param array $queryPacket
     * SN string Y 设备序列号
     * mode int Y 设备模式类型 1：拍照注册；2：IC卡/身份证卡号注册
     * type int Y 0 开启 1 关闭
     * taskID string N 任务ID
     * timeOut int N 超时时间(单位秒)（默认 60s，安卓设备不支持设置超时时间）
     * callBackUrl string N 回调地址(注册成功会进行通知，若不填需要主动来查询)
     * UID string N 识别主体的 guid (开启注册时候必传)
     * @return $this
     */
    public function setDeviceRegMode(array $queryPacket = []): self
    {
        $this->uri = 'device/registerMode';
        $this->name = '设备注册任务';

        if (!$deviceNo = Arr::get($queryPacket, 'SN')) {
            $this->cancel = true;
            $this->errBox[] = '设备序列号不得为空';
        }

        $registerMode = intval(Arr::get($queryPacket, 'mode'));
        $taskGuid = Arr::get($queryPacket, 'taskID');
        $timeOut = Arr::get($queryPacket, 'timeOut');
        $callBackUrl = Arr::get($queryPacket, 'callBackUrl');
        $admitGuid = Arr::get($queryPacket, 'UID');
        if ($activeType = intval(Arr::get($queryPacket, 'type'))) {
            if (!$admitGuid) {
                $this->cancel = true;
                $this->errBox[] = '识别主体的guid(开启注册时候必传)';
            }
        } else {
            if (!$taskGuid) {
                $this->cancel = true;
                $this->errBox[] = '任务guid(关闭注册任务必传)';
            }
        }

        $this->queryBody = compact('deviceNo', 'registerMode', 'taskGuid', 'timeOut', 'callBackUrl', 'activeType');
        return $this;
    }

    /**
     * @param array $queryPacket
     * taskID string N 任务ID
     * @return $this
     */
    public function getDeviceRegMode(array $queryPacket = []): self
    {
        $this->uri = 'device/registerMode/query';
        $this->name = '设备注册任务查询';

        if (!$taskGuid = Arr::get($queryPacket, 'taskID')) {
            $this->cancel = true;
            $this->errBox[] = '任务guid不可为空';
        }

        $this->queryBody = compact('taskGuid');
        return $this;
    }

    /**
     * @param array $queryPacket
     * SN string Y 设备序列号
     * type int Y 1 开门
     * @return $this
     */
    public function controlDevice(array $queryPacket = []): self
    {
        $this->uri = 'device/interactive';
        $this->name = '设备交互模式';

        if (!$deviceNo = Arr::get($queryPacket, 'SN')) {
            $this->cancel = true;
            $this->errBox[] = '设备序列号不得为空';
        }
        $type = intval(Arr::get($queryPacket, 'type'));

        $this->queryBody = compact('type', 'deviceNo');
        return $this;
    }

    public function fire()
    {
        $apiName = $this->name;
        $httpMethod = $this->httpMethod;
        $gateway = trim($this->gateway, '/') . '/';
        $uri = $this->apiVer . $this->uri;
        $headers = $this->headers ?: ['token' => $this->token, 'projectGuid' => $this->projectID];

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
            switch ($this->httpMethod) {
                case self::METHOD_GET:
                    $rawResponse = $httpClient->$httpMethod($uri, ['headers' => $headers, 'query' => $this->queryBody]);
                    break;
                case self::METHOD_POST:
                case self::METHOD_PUT:
                default:
                    $rawResponse = $httpClient->$httpMethod($uri, ['headers' => $headers, 'json' => $this->queryBody]);
                    break;
            }

            $respRaw = $rawResponse->getBody()->getContents();
            $respArr = @json_decode($respRaw, true) ?: [];
            $this->logging && $this->logging->info($this->name, ['gateway' => $gateway, 'uri' => $uri, 'headers' => $headers, 'queryBody' => $this->queryBody, 'response' => $respArr]);

            $this->cleanup();

            if ($rawResponse->getStatusCode() !== 200) {
                throw new RequestFailedException('接口请求失败:' . $apiName);
            }

            switch ($this->responseFormat) {
                case self::RESP_FMT_JSON:
                    $responsePacket = $respArr;
                    $this->formatResp($responsePacket);
                    return $responsePacket;
                case self::RESP_FMT_BODY:
                    return $respRaw;
                case self::RESP_FMT_RAW:
                default:
                    return $rawResponse;
            }
        }
    }
}
