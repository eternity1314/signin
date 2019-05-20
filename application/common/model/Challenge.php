<?php

namespace app\common\model;

use think\Db;
use think\facade\Config;
use think\facade\Debug;
use think\facade\Log;

class Challenge
{
    public static function pay_finish($data, $pay_id = 0)
    {
        if (!empty($data['attach'])) {
            $user_id = $data['attach'][0];
            $event = $data['attach'][3];
            parse_str($data['attach'][4], $option);
            $option['use_balance'] = true;

            \think\facade\Env::set('ME', $user_id);
            self::$event($user_id, $option);
        }
    }

    public static function both_option()
    {
        return [
            'day' => [3, 5, 7, 14],
            'price' => [10, 50, 100, 300, 500],
            'begin_hour' => 5,
            'begin_minute' => 0,
            'end_hour' => 8,
            'end_minute' => 0,
            'recommend_fee' => 2,
            'default_time' => [6, 30, 7, 30],
            'inteval' => 300,
            'remark' => [
                'title' => '提醒',
                'content' => '1、每天11:00前结算，如打卡有问题，请在打卡时间范围内反馈；2、如结算有问题，请当日内反馈；3、反馈渠道：关注“微选生活APP”公众号直接留言；4、逾期反馈无法处理，请见谅'
            ],
            'income_tips' => '如获奖将捐25%的奖励到对战公益金'
        ];
    }

    public static function join_option()
    {
        return [
            'remark' => [
                'title' => '提醒',
                'content' => '1、每天11:00前结算，如打卡有问题，请在打卡时间范围内反馈；2、如结算有问题，请当日内反馈；3、反馈渠道：关注“微选生活APP”公众号直接留言；4、逾期反馈无法处理，请见谅'
            ],
            'price' => [10, 50, 100, 500, 1000]
        ];
    }

    public static function room_option($name = '')
    {
        $data = [
            'day' => [3, 7, 21],
            'bhour' => 5,
            'bminute' => 0,
            'ehour' => 8,
            'eminute' => 0,
            'recommend_fee' => 10,
            'default_time' => [6, 30, 7, 30],
            'inteval' => 300,
            'city' => ['广州', '深圳', '北京', '上海'],
            'info' => [
                '3' => ['day' => 3, 'leader_rate' => [40, 50], 'room_fee' => ['month' => 99, 'year' => 999], 'income_rate' => 3],
                '7' => ['day' => 7, 'leader_rate' => [25, 35], 'room_fee' => ['month' => 49, 'year' => 499], 'income_rate' => 6],
                '21' => ['day' => 21, 'leader_rate' => [15, 20], 'room_fee' => ['month' => 29, 'year' => 299], 'income_rate' => 12],
            ],
            'remark' => [
                'title' => '提醒',
                'content' => '让我们每个人都用自己的正能量来影响身边的人，改变他们，提升他们的生活品质，同时也让自己轻松月入过万'
            ],
            'income_desc' => [
                'title' => '预计回报金额说明',
                'content' => [
                    '预计回报金额根据每个人邀请能力的不同而有所不同，以平均数值计算，进入您族群打卡的人数300人计算，平均每人参与金额按200元/月计算，合计60000元，挑战失败率按3%计算，按最低40%的费用算，则有504元的收入扣除创建族群费用每月净赚405元，续费一年一共有4860元',
                    '邀请推广能力不同，参与人数也不同；邀请的人的属性不同，失败比例也不同，因此以上预计回报仅供参考，不做实际承诺'
                ]
            ]
        ];

        if (!empty($name) && isset($data[$name])) return $data[$name];

        return $data;
    }

    public static function create_room($user_id, $option = [], $pay_id = 0)
    {
        $info = self::room_option();

        if (empty($option['day']) || !in_array($option['day'], $info['day'])) {
            $day = $info['day'][0];
            // return ['', '天数有误', 1];
        } else {
            $day = $option['day'];
        }

        $room_fee_opt = $info['info'][$day]['room_fee'];

        if (empty($option['room_fee']) || empty($room_fee_opt[$option['room_fee']])) {
            $room_fee = 'month';
        } else {
            $room_fee = $option['room_fee'];
        }

//        if (empty($option['create_fee']) || empty($room_fee[$option['create_fee']])) {
//            $create_fee = 'year';
//        } else {
//            $create_fee = $option['create_fee'];
//        }

        $title = empty($option['title']) ? '族群' . mt_rand(10000, 99999) : trim($option['title']);
        $count = Db::name('challenge_room')->where([['title', '=', $title]])->count(1);
        if ($count > 0) {
            return ['', '族群名已被使用', 1];
        }

        $user = User::find($user_id);
        $paid = $create_fee = $room_fee_opt[$room_fee] * 100;

        $count = Db::name('user_money')->where([
            ['user_id', '=', $user_id],
            ['event', '=', 'room_fee'],
            ['money', '=', 0]
        ])->count(1);
        if ($count == 0) {
//            // 合伙人免费创建族群
//            $free_type = User::partner_type($user_id);
//            if ($free_type == 'year' || ($free_type == 'month' && $room_fee == 'month')) {
//                $paid = 0;
//                $create_fee = 0.00001;
//            }

            $user_level = User::level($user_id);
            if ($user_level >= 3) { // && $room_fee == 'month'
                $paid = 0;
                $create_fee = 0.00001;
            }
        }

        $recommend_fee = 0;
        if (!empty($option['recommend'])) {
            $recommend_fee = intval($info['recommend_fee'] * 100);
            $paid += $recommend_fee;
        }

        if (!empty($option['use_balance'])) {
            if ($user['balance'] >= $paid) {
                $paid = 0;
            }
        }

        if ($paid > 0) {
            return Pay::pay($paid, ['attach' => "{$user_id}:{$paid}:Challenge:create_room", 'openid' => $user['openid']], ['attach_data' => $option]);
        }

        $stime = self::parse_signin_time($option);
        if (empty($stime['btime']) || empty($stime['etime'])) {
            return ['', 'signin time error', 1];
        }

        $btime = $stime['btime'];
        $etime = $stime['etime'];

        $leader_rate = $info['info'][$day]['leader_rate'];
        if (empty($option['leader_rate']) || !in_array($option['leader_rate'], $leader_rate)) {
            $leader_rate = $leader_rate[0];
        } else {
            $leader_rate = $option['leader_rate'];
        }

        $city = empty($option['city']) ? $info['city'][mt_rand(0, count($info['city']) - 1)] : $option['city'];
        $avator = empty($option['avator']) ? $user['avator'] : $option['avator'];

        $now_time = request()->time();
        $data_save = [
            'user_id' => $user_id,
            'nickname' => $user['nickname'],
            'day' => $day,
            'btime' => $btime,
            'etime' => $etime,
            'leader_rate' => $leader_rate,
            'avator' => $avator,
            'city' => $city,
            'title' => $title,
            'income_rate' => 0.388, // $info['info'][$day]['income_rate'],
            // 'subsidy' => 1, // 用户族群补贴1分钱
            'expire_time' => strtotime('+1 ' . $room_fee, Common::today_time() + 86400),
            'add_time' => $now_time
        ];

        if ($recommend_fee > 0) {
            $data_save['recommend_time'] = $now_time + 86400;
        }

        $room_id = DB::name('challenge_room')->insertGetId($data_save);
        if (!$room_id) return ['', 'create room error', 1];

        if ($create_fee > 0) {
            $res = User::money($data_save['user_id'], -$create_fee, 'room_fee', $room_id, $room_fee == 'month' ? '创建早起族群/月' : ($room_fee == 'year' ? '创建早起族群/年' : '创建族群')); // challenge_room
            if (empty($res['flow_id']) && $create_fee >= 1) {
                DB::name('challenge_room')->where(['room_id' => $room_id])->delete();
                Log::record('create room user balance error,data:' . json_encode($data_save, JSON_UNESCAPED_UNICODE));
                return ['', 'create room create fee fail', 1];
            }
        }

        if ($recommend_fee > 0) {
            $res = User::money($data_save['user_id'], -$recommend_fee, 'recommend_room', $room_id, '推荐早起族群');
            if (empty($res['flow_id'])) {
                DB::name('challenge_room')->where(['room_id' => $room_id])->delete();
                Log::record('create room user balance error,data:' . json_encode($data_save, JSON_UNESCAPED_UNICODE));
                return ['', 'create room recommend fee fail', 1];
            }
        }

        // 上级获得提成
        $tier_pid = Db::name('user_tier')->where([['user_id', '=', $user_id]])->value('pid');
        if ($tier_pid) {
            $expire_time = Db::name('user_promotion')->where([['user_id', '=', $tier_pid]])->value('expire_time');
            if ($expire_time > $now_time) {
                $award = intval($create_fee * 0.3);
            } else {
                $award = intval($create_fee * 0.1);
            }

            User::money($tier_pid, $award, 'create_room_superior_award', $tier_pid, '下级创建族群');

            $dynamic = $xinge_task = [];
            $data = serialize([
                'title' => '订单收入通知,奖励' . ($award / 100) . '元',
                'content' => '成员【' . $user['nickname'] . '】[' . date('Y-m-d', $now_time) . ']创建生活族群成功,您获得奖励' . ($award / 100) . '元'
            ]);

            $dynamic[] = [
                'user_id' => 0,
                'nickname' => '微选生活',
                'avator' => '/static/img/logo.png',
                'receive_uid' => $tier_pid,
                'event' => 'create_room_superior_award',
                'event_id' => $room_id,
                'data' => $data,
                'add_time' => $now_time
            ];

            $xinge_task[] = [
                'user_id' => $tier_pid,
                'event' => 'create_room_superior_award',
                'data' => $data
            ];

            // 上上级
            $tier_pid = Db::name('user_tier')->where([['user_id', '=', $tier_pid]])->value('pid');
            if ($tier_pid) {
                $expire_time = Db::name('user_promotion')->where([['user_id', '=', $tier_pid]])->value('expire_time');
                if ($expire_time > $now_time) {
                    $award = intval($create_fee * 0.1);
                    User::money($tier_pid, $award, 'create_room_ssuperior_award', $tier_pid, '下下级创建族群');

                    $data = serialize([
                        'title' => '订单收入通知,奖励' . ($award / 100) . '元',
                        'content' => '成员【' . $user['nickname'] . '】[' . date('Y-m-d', $now_time) . ']创建生活族群成功,您获得奖励' . ($award / 100) . '元'
                    ]);

                    $dynamic[] = [
                        'user_id' => 0,
                        'nickname' => '微选生活',
                        'avator' => '/static/img/logo.png',
                        'receive_uid' => $tier_pid,
                        'event' => 'create_room_ssuperior_award',
                        'event_id' => $room_id,
                        'data' => $data,
                        'add_time' => $now_time
                    ];

                    $xinge_task[] = [
                        'user_id' => $tier_pid,
                        'event' => 'create_room_ssuperior_award',
                        'data' => $data
                    ];
                }
            }

            Db::name('user_dynamic')->insertAll($dynamic);
            Db::name('xinge_task')->insertAll($xinge_task);
        }

        // 系统补贴发红包
        $data = [
            'user_id' => $user_id,
            'room_id' => $room_id,
            'event' => 'rmb',
            'price' => mt_rand(360, 460),
            'num' => 10,
            'surplus' => 10,
            'msg' => '恭喜发财，大吉大利',
            'status' => 1,
            'add_time' => $now_time,
            'show' => 0,
            'receive' => 'all',
            'share' => 0,
            'transfer' => 'account',
            'pic' => ''
        ];

        $redpack_id = Db::name('redpack_send')->insertGetId($data);
        $data['redpack_id'] = $redpack_id;
        Redpack::send_finish($data);

        return ['room_id' => $room_id, 'redpack_id' => $redpack_id];
    }

    public static function parse_signin_time($option = [])
    {
        $info = self::room_option();

        if (!empty($option['btime']) && !empty($option['etime'])) {
            $bhour = intval($option['btime'] / 100);
            $bminute = intval(substr($option['btime'], -2));
            $ehour = intval($option['etime'] / 100);
            $eminute = intval(substr($option['etime'], -2));
        } else {
            $bhour = !isset($option['bhour']) ? $info['default_time'][0] : intval($option['bhour']);
            $bminute = !isset($option['bminute']) ? $info['default_time'][1] : intval($option['bminute']);
            $ehour = !isset($option['ehour']) ? $info['default_time'][2] : intval($option['ehour']);
            $eminute = !isset($option['eminute']) ? $info['default_time'][3] : intval($option['eminute']);
        }

        if ($bhour < $info['bhour'] || $bhour > $info['ehour']) {
            $bhour = $info['bhour'];
        }

        if ($ehour < $info['bhour'] || $ehour > $info['ehour']) {
            $ehour = $info['ehour'];
        }

        if ($bminute < 0 || $bminute >= 60) {
            $bminute = $info['bminute'];
        }

        if ($eminute < 0 || $eminute >= 60) {
            $eminute = $info['eminute'];
        }

        $btime = $bhour * 60 * 60 + $bminute * 60;
        $etime = $ehour * 60 * 60 + $eminute * 60;

        if ($etime - $btime < 300) {
            $etime = $btime + 300;
            // return ['', '开始时间和结束时间间隔至少5分钟', 1];
        }

        $time_min = $info['bhour'] * 60 * 60 + $info['bminute'] * 60;
        $time_max = $info['ehour'] * 60 * 60 + $info['eminute'] * 60;

        if ($btime < $time_min || $btime > $time_max || $etime < $time_min || $etime > $time_max) {
            // $btime = $time_min;
            // $etime = $time_max;

            $bhour = $info['bhour'];
            $bminute = $info['bminute'];
            $ehour = $info['ehour'];
            $eminute = $info['eminute'];

            // return ['', '时间有误，打卡时间是5:00~8:00！', 1];
        }

        return [
            'btime' => intval($bhour . substr('0' . $bminute, -2)),
            'etime' => intval($ehour . substr('0' . $eminute, -2)),
            'bhour' => $bhour,
            'bminute' => $bminute,
            'ehour' => $ehour,
            'eminute' => $eminute,
            'stime' => $bhour . ':' . substr('0' . $bminute, -2) . ' ~ ' . $ehour . ':' . substr('0' . $eminute, -2)
        ];
    }

    public static function convert_signin_time($option = [])
    {
        if (!empty($option['btime'])) {
            $btime = intval($option['btime'] / 100) . ':' . substr($option['btime'], -2);
        }

        if (!empty($option['etime'])) {
            $etime = intval($option['etime'] / 100) . ':' . substr($option['etime'], -2);
        }

        return ['btime' => $btime, 'etime' => $etime, 'stime' => $btime . ' ~ ' . $etime];
    }

    public static function launch($user_id, $option = [], $pay_id = 0)
    {
        if (empty($option['price'])) {
            return ['', 'param error 1', 1];
        }

        $price = floatval($option['price']);
        $info = self::both_option();
        if (!in_array($price, $info['price'])) {
            return ['', 'param error 2', 1];
        }

        if (empty($option['day']) || !in_array($option['day'], $info['day'])) {
            $day = $option['day'] = $info['day'][0];
            // return ['', '天数有误', 1];
        } else {
            $day = $option['day'];
        }

        $price = intval($price * 100);
        $recommend_fee = 0;
        if (!empty($option['recommend'])) {
            $recommend_fee = intval($info['recommend_fee'] * 100);
        }

        $stime = self::parse_signin_time($option);
        if (empty($stime['btime']) || empty($stime['etime'])) {
            return ['', 'signin time error', 1];
        }

        $count = Db::name('challenge')->where([
            ['user_id', '=', $user_id],
            ['event', '=', 'launch'],
            ['add_time', '>', request()->time() - 30 * 60]
        ])->count();
        if ($count > 0) return ['', '你发的太过频繁了', 1];

        $option['btime'] = $stime['btime'];
        $option['etime'] = $stime['etime'];
        $option['added'] = $recommend_fee;
        $res = self::join($user_id, $option, 'launch');
        if (empty($res['challenge_id'])) {
            return $res;
        }

        $data_save = [
            'price' => $price,
            'day' => $day,
            'join_date' => $res['join_date'],
            'expire_date' => $res['expire_date'],
            'launch_uid' => $user_id,
            'launch_time' => request()->time(),
            'launch_cid' => $res['challenge_id']
        ];

        if ($recommend_fee > 0) { // 扣费
            $ret = User::money($user_id, -$recommend_fee, 'recommend_launch', $res['challenge_id'], '推荐双人对战');
            if (!empty($ret['flow_id'])) {
                $data_save['recommend'] = 1;
            }
        }

        $res['both_id'] = DB::name('challenge_both')->insertGetId($data_save);

        $user = User::find($user_id);
        $time = request()->time();

        if (!empty($option['room_id'])) {
            Db::name('challenge_dynamic')->insert([
                'user_id' => $user_id,
                'nickname' => $user['nickname'],
                'avator' => $user['avator'],
                'room_id' => $option['room_id'],
                'event' => 'challenge_launch',
                'event_id' => $res['challenge_id'],
                'data' => serialize([
                    'challenge_id' => $res['challenge_id'],
                    'stime' => $stime['stime'],
                    'price' => $price / 100,
                    'day' => $day,
                    'reward' => round($price * 2, 2)
                ]),
                'add_time' => $time
            ]);
        }

//        // 好友动态
//        $friend_uid = Db::name('user_friend')->where(['user_id' => $user_id])->column('friend_uid');
//        if (!empty($friend_uid)) {
//            $data = [];
//            foreach ($friend_uid as $uid) {
//                $data[] = [
//                    'user_id' => $uid,
//                    'nickname' => $user['nickname'],
//                    'avator' => $user['avator'],
//                    'receive_uid' => $uid,
//                    'event' => 'challenge_launch',
//                    'event_id' => $res['challenge_id'],
//                    'data' => serialize(
//                        [
//                            'challenge_id' => $res['challenge_id'],
//                            'stime' => $stime['stime'],
//                            'price' => $price / 100,
//                            'day' => $day,
//                            'reward' => round($price * 2, 2)
//                        ]
//                    ),
//                    'add_time' => $time
//                ];
//            }
//
//            Db::name('user_dynamic')->insertAll($data);
//        }

        return $res;
    }

    public static function accept($user_id, $option = [], $pay_id = 0)
    {
        if (empty($option['challenge_id']) || empty($challenge_id = intval($option['challenge_id']))) {
            return ['', 'param error', 1];
        };

        $challenge = DB::name('challenge')->where(['challenge_id' => $challenge_id])->find();
        if (empty($challenge)) {
            return ['', 'challenge empty', 1];
        }

        if ($challenge['status'] != 0) {
            return ['', '此挑战已被他人接受，请寻找下个目标', 1];
        }

        if ($challenge['user_id'] == $user_id) {
            return ['', '不能接受自己发起的挑战', 1];
        }

        $today_date = Common::today_date();
        if ($challenge['join_date'] != $today_date) {
            return ['', '此挑战已被他人接受，请寻找下个目标', 1];
        }

        $option['price'] = $challenge['price'] / 100;
        $option['day'] = $challenge['day'];
        $option['btime'] = $challenge['btime'];
        $option['etime'] = $challenge['etime'];
        $res = self::join($user_id, $option, 'accept');

        if (empty($res['challenge_id'])) return $res;

        $ret = DB::name('challenge_both')->where(['launch_cid' => $challenge_id, 'launch_status' => 0])
            ->update([
                'accept_uid' => $user_id,
                'accept_time' => request()->time(),
                'accept_cid' => $res['challenge_id'],
                'accept_status' => 1,
                'launch_status' => 1,
                'status' => 1,
            ]);

        if (empty($ret)) {
            Log::record('challenge_both update error ' . $challenge_id);
            return ['', '出了点问题，请与客服联系', 1];
        }

        Db::name('challenge')
            ->where('challenge_id', 'in', [$challenge_id, $res['challenge_id']])
            ->update(['status' => 1]);

        $user = User::find($user_id);

        // 发起人动态
        Db::name('user_dynamic')->insert([
            'user_id' => $user_id,
            'nickname' => $user['nickname'],
            'avator' => $user['avator'],
            'receive_uid' => $challenge['user_id'],
            'event' => 'challenge_accept',
            'event_id' => $res['challenge_id'],
            'data' => '',
            'add_time' => request()->time()
        ]);

        Db::name('xinge_task')->insert([
            'user_id' => $challenge['user_id'],
            'event' => 'challenge_accept',
            'data' => serialize([
                'title' => '接受了你的早起耐力挑战',
                'content' => '早睡早起好身体，记得要按约定日期和时间打卡哦，努力培养早睡早起的健康生活习惯，好习惯是需要坚持住的！'
            ])
        ]);

//        // 更改动态状态 // 好友通知已删，不需要更改
//        Db::name('user_dynamic')->where([
//            ['event', '=', 'challenge_launch'],
//            ['event_id', '=', $challenge_id]
//        ])->update(['status' => 2]);

        return $res;
    }

    public static function join($user_id, $option = [], $event = 'join', $pay_id = 0)
    {
        $today_time = Common::today_time();
        $today_date = Common::today_date();

        if ($event == 'join') {
            if (empty($option['price'])) {
                return ['', 'param error 1', 1];
            }

            if (empty($option['room_id'])) {
                return ['', 'param error 2', 1];
            }

            $price = floatval($option['price']);
            if (empty($option['no_check_price'])) {
                $info = self::join_option();
                if (!in_array($price, $info['price'])) {
                    return ['', 'param error 3', 1];
                }
            }

            $room_id = intval($option['room_id']);
            $room = DB::name('challenge_room')->where(['room_id' => $room_id])->find();
            if (empty($room)) {
                return ['', 'room not exists', 1];
            }

            $challenge = DB::name('challenge')->where([
                'user_id' => $user_id,
                'room_id' => $room_id,
                'join_date' => $today_date,
                'event' => 'join'
            ])->find();

            $day = $room['day'];
            $btime = $room['btime'];
            $etime = $room['etime'];
        } else {
            $day = $option['day'];
            $btime = $option['btime'];
            $etime = $option['etime'];
            $room_id = 0;
            $price = $option['price'];
        }

        $price = intval($price * 100);
        $user = User::find($user_id);
        $paid = $price;

        if (!empty($option['added'])) {
            $paid += $option['added'];
        }

        if (!empty($option['use_balance'])) {
            if ($user['balance'] >= $paid) {
                $paid = 0;
            }
        }

        if ($paid > 0) {
            unset($option['added']);
            $res = Pay::pay($paid, ['attach' => "{$user_id}:{$paid}:Challenge:{$event}", 'openid' => $user['openid']], ['attach_data' => $option]);
            return $res;
        }

        if (empty($challenge)) { // 新增
            $expire_date = date('Ymd', strtotime("+{$day} day", $today_time));

            $data_save = [
                'user_id' => $user_id,
                'room_id' => $room_id,
                'price' => $price,
                'day' => $day,
                'btime' => $btime,
                'etime' => $etime,
                'join_date' => $today_date,
                'expire_date' => $expire_date,
                'event' => $event,
                'add_time' => request()->time(),
                'edit_time' => request()->time()
            ];

            if ($event == 'join') {
                $data_save['status'] = 1;
            }

            $challenge_id = $ret = DB::name('challenge')->insertGetId($data_save);
            if (empty($challenge_id)) {
                return ['', 'handle fail', 1];
            }
        } else { // 合并
            $challenge_id = $challenge['challenge_id'];
            $expire_date = $challenge['expire_date'];
        }

        // 扣费
        $event_name = $event == 'launch' ? '发起' : ($event == 'accept' ? '接受' : '参与族群');
        $use_balance = [$user_id, -$price, 'challenge_' . $event, $challenge_id, $event_name . '挑战'];
        DB::transaction(function () use ($use_balance, $event, &$flow_id) {
            $res = User::money($use_balance[0], $use_balance[1], $use_balance[2], $use_balance[3], $use_balance[4]);
            if (empty($res['flow_id'])) {
                DB::name('challenge')->where(['challenge_id' => $use_balance[3]])->delete();
                Log::record('create room user balance error,data:' . json_encode($use_balance, JSON_UNESCAPED_UNICODE));
                return false;
            }

            $flow_id = $res['flow_id'];
        });

        if (empty($challenge)) { // 新增
            $data_record = [];
            $data_save = [
                'challenge_id' => $challenge_id,
                'user_id' => $user_id,
                'status' => 1,
                'room_id' => $room_id
            ];

            $btime = intval($btime / 100) * 60 * 60 + intval(substr($btime, -2)) * 60;
            $etime = intval($etime / 100) * 60 * 60 + intval(substr($etime, -2)) * 60;

            for ($i = 1; $i <= $day; $i++) {
                $data_save['date'] = $today_time + ($i * 86400);
                $data_save['btime'] = $data_save['date'] + $btime;
                $data_save['etime'] = $data_save['date'] + $etime;
                $data_record[] = $data_save;
            }

            $res = DB::name('challenge_record')->insertAll($data_record);
            if (empty($res)) {
                Log::record('challenge_record insert error,' . json_encode($data_record));
                return ['', 'join fail', 1];
            }
        } else { // 合并
            $res = DB::name('challenge')
                ->where(['challenge_id' => $challenge_id])
                ->inc('price', $price)
                ->update(['edit_time' => request()->time()]);
        }

        if (empty($res)) {
            Log::record('challenge_record save error,' . DB::getLastSql());
            return ['', 'join fail', 1];
        }

        if ($event == 'join') {
            $data_save = ['join_time' => request()->time()];

            if (empty($challenge)) {
                $data_save['join_count'] = Db::name('challenge')->where('room_id', '=', $room_id)->count('DISTINCT user_id');
            }

            Db::name('challenge_room')->where('room_id', '=', $room_id)
                ->inc('price_all', $price)
                ->inc('price_ing', $price)
                ->update($data_save);
        }

        return ['challenge_id' => $challenge_id, 'join_date' => $today_date, 'expire_date' => $expire_date];
    }

    public static function update_room($user_id, $option)
    {
        if (count($option) < 2 || empty($option['room_id']) || empty($room_id = intval($option['room_id']))) {
            return ['', 'param error', 1];
        }

        $room = Db::name('challenge_room')->where('room_id', '=', $room_id)->find();
        if (empty($room) || $room['status'] != 1) {
            return ['', 'room not exists', 1];
        }

        if ($room['user_id'] != $user_id) {
            return ['', 'permission denied', 1];
        }

        $info = self::room_option();

        if (!empty($option['leader_rate'])) {
            $leader_rate = $info['info'][$room['day']]['leader_rate'];

            if (!in_array($option['leader_rate'], $leader_rate)) {
                $leader_rate = $leader_rate[0];
            } else {
                $leader_rate = $option['leader_rate'];
            }

            $paid = 1000;
            $user = User::find($user_id);

            if ($user['balance'] < $paid) {
                return Pay::pay($paid - $user['balance'], [
                    'attach' => "{$user_id}:{$paid}:Challenge:update_room",
                    'openid' => $user['openid']
                ], ['attach_data' => $option]
                );
            }

            $res = User::money($user_id, -$paid, 'update_leader_rate', $room_id, '更改族费比例');
            if (empty($res['flow_id'])) {
                return ['', 'update_leader_rate fee fail', 1];
            }

            $update_id = Db::name('challenge_room')->where('room_id', '=', $room_id)->update(['leader_rate' => $leader_rate]);

            return [['update_id' => $update_id], '', 1];
        } elseif (!empty($option['recommend'])) {
            $paid = $info['recommend_fee'] * 100;
            $user = User::find($user_id);

            if ($user['balance'] < $paid) {
                return Pay::pay($paid - $user['balance'], [
                    'attach' => "{$user_id}:{$paid}:Challenge:update_room",
                    'openid' => $user['openid']
                ], ['attach_data' => $option]
                );
            }

            $res = User::money($user_id, -$paid, 'recommend_room', $room_id, '推荐早起族群');
            if (empty($res['flow_id'])) {
                return ['', 'recommend_room fee fail', 1];
            }

            $now_time = request()->time();
            if ($room['recommend_time'] < $now_time) {
                $recommend_time = $now_time + 86400;
            } else {
                $recommend_time = $room['recommend_time'] + 86400;
            }

            $update_id = Db::name('challenge_room')->where('room_id', '=', $room_id)->update(['recommend_time' => $recommend_time]);

            return [['update_id' => $update_id], '', 1];
        } elseif (!empty($option['room_fee'])) {
            $room_fee = $info['info'][$room['day']]['room_fee'];
            if (empty($room_fee[$option['room_fee']])) $option['room_fee'] = 'month';

            $paid = $room_fee[$option['room_fee']] * 100;
            $user = User::find($user_id);

            if ($user['balance'] < $paid) {
                return Pay::pay($paid - $user['balance'], [
                    'attach' => "{$user_id}:{$paid}:Challenge:update_room",
                    'openid' => $user['openid']
                ], ['attach_data' => $option]
                );
            }

            $res = User::money($user_id, -$paid, 'room_fee', $room_id, $option['room_fee'] == 'month' ? '早起族群续费/月' : ($option['room_fee'] == 'year' ? '早起族群续费/年' : '早起族群续费'));
            if (empty($res['flow_id'])) {
                return ['', 'room_fee fee fail', 1];
            }

            $now_time = request()->time();
            if ($room['expire_time'] > $now_time) $expire_time = $room['expire_time'];
            else $expire_time = Common::today_time() + 86400;

            $expire_time = strtotime('+1 ' . $option['room_fee'], $expire_time);

            $update_id = Db::name('challenge_room')->where('room_id', '=', $room_id)->update(['expire_time' => $expire_time]);

            return [['update_id' => $update_id], '', 1];
        } elseif (!empty($option['title'])) {
            $title = trim($option['title']);
            $count = Db::name('challenge_room')->where([['title', '=', $title], ['room_id', '<>', $room_id]])->count(1);
            if ($count > 0) {
                return ['', '族群名已被使用', 1];
            }

            $update_id = Db::name('challenge_room')->where('room_id', '=', $room_id)->update(['title' => $title]);

            return [['update_id' => $update_id], '', 1];
        } elseif (!empty($option['avator'])) {
            $update_id = Db::name('challenge_room')->where('room_id', '=', $room_id)->update(['avator' => $option['avator']]);

            return [['update_id' => $update_id], '', 1];
        } elseif (!empty($option['pic'])) {
            if (!empty($room['pic'])) {
                $paid = 500;
                $user = User::find($user_id);

                if ($user['balance'] < $paid) {
                    return Pay::pay($paid - $user['balance'], [
                        'attach' => "{$user_id}:{$paid}:Challenge:update_room",
                        'openid' => $user['openid']
                    ], ['attach_data' => $option]
                    );
                }

                $res = User::money($user_id, -$paid, 'update_room_pic', $room_id, '更改族群二维码');
                if (empty($res['flow_id'])) {
                    return ['', 'update_leader_rate fee fail', 1];
                }
            }

            $update_id = Db::name('challenge_room')->where('room_id', '=', $room_id)->update(['pic' => $option['pic']]);

            return [['update_id' => $update_id], '', 1];
        } elseif (isset($option['auto'])) {
            $update_id = Db::name('challenge_room')->where('room_id', '=', $room_id)->update(['auto' => $option['auto']]);

            return [['update_id' => $update_id], '', 1];
        }

        return ['', 'event not match', 1];
    }

    public static function signin($user_id, $room_id = 0)
    {
        if (empty($room_id)) {
            $res = self::signin_both($user_id);
        } else {
            $res = self::signin_room($user_id, $room_id);
        }

        if ($res) {
            if (isset($res['ok'])) {
                $tier = Db::name('user_tier')->where([['user_id', '=', $user_id]])->find();
                if ($tier) {
                    $count = Db::name('user_mb')->where([
                        ['user_id', '=', $tier['pid']],
                        ['event_id', '=', $user_id],
                        ['event', '=', 'lower_signin']
                    ])->count(1);

                    if ($count == 0) {
                        User::mb($tier['pid'], 300, 'lower_signin', $user_id, '下级好友打卡');
                    }
                }

                unset($res['ok']);
            }

            return $res;
        }

        return ['', '打卡失败，操作有误', 1];
    }

    public static function signin_both($user_id)
    {
        $time = request()->time();
        $today_time = Common::today_time();

        if ($today_time + 18000 <= $time && $time <= $today_time + 28800) {
            $res = Db::name('challenge_record')->where([
                ['user_id', '=', $user_id],
                ['room_id', '=', 0],
                ['btime', '<=', $time],
                ['etime', '>', $time],
                ['stime', '=', 0]
            ])->update(['stime' => $time]);

            if ($res) {
                // 系统消息
//                $challenge_id = Db::name('challenge_record')->where([
//                    ['user_id', '=', $user_id],
//                    ['room_id', '=', 0],
//                    ['btime', '<=', $time],
//                    ['etime', '>', $time]
//                ])->column('challenge_id');
//
//                $challenge = Db::name('challenge_both')->where([['launch_cid|accept_cid', 'in', $challenge_id]])->select();
//
//                $user_ids = [];
//                foreach ($challenge as $item) {
//                    if ($item['launch_uid'] == $user_id) $user_ids[] = $item['accept_uid'];
//                    elseif ($item['accept_uid'] == $user_id) $user_ids[] = $item['accept_uid'];
//                }
//
//                $users = Db::name('user')->where([['id', 'in', $user_ids]])->column('id,nickname');
//                $dynamic = $xinge_task = [];
//
//                foreach ($challenge as $item) {
//                    if ($item['launch_uid'] == $user_id) $uid = $item['accept_uid'];
//                    elseif ($item['accept_uid'] == $user_id) $uid = $item['launch_uid'];
//
//                    $content = '你和【' . (empty($users[$uid]) ? '某人' : $users[$uid]) . '】的好友对战打卡成功，你的打卡时间是' . Common::time_format($time);
//
//                    $dynamic[] = [
//                        'user_id' => 0,
//                        'nickname' => '系统通知',
//                        'avator' => '/static/img/logo.png',
//                        'receive_uid' => $user_id,
//                        'event' => 'signin_both',
//                        'event_id' => $item['both_id'],
//                        'data' => serialize([
//                            'title' => '好友对战打卡成功通知',
//                            'content' => $content
//                        ]),
//                        'add_time' => $time
//                    ];
//
//                    $xinge_task[] = [
//                        'user_id' => $user_id,
//                        'event' => 'signin_both',
//                        'data' => serialize([
//                            'title' => '好友对战打卡成功通知',
//                            'content' => $content
//                        ])
//                    ];
//                }
//
//                Db::name('user_dynamic')->insertAll($dynamic);
//                Db::name('xinge_task')->insertAll($xinge_task);

                $content = '好友对战打卡成功，你的打卡时间是' . Common::time_format($time);

                $dynamic = [
                    'user_id' => 0,
                    'nickname' => '系统通知',
                    'avator' => '/static/img/logo.png',
                    'receive_uid' => $user_id,
                    'event' => 'signin_both',
                    'event_id' => 0,
                    'data' => serialize([
                        'title' => '好友对战打卡成功通知',
                        'content' => $content
                    ]),
                    'add_time' => $time
                ];

                $xinge_task = [
                    'user_id' => $user_id,
                    'event' => 'signin_both',
                    'data' => serialize([
                        'title' => '好友对战打卡成功通知',
                        'content' => $content
                    ])
                ];

                Db::name('user_dynamic')->insert($dynamic);
                Db::name('xinge_task')->insert($xinge_task);

                return ['', '打卡成功，如对方未打卡，11:00前结算本金和奖励，请留意', 0, 'ok' => true];
            } else {
                $count = Db::name('challenge_record')->where([
                    ['user_id', '=', $user_id],
                    ['room_id', '=', 0],
                    ['btime', '<=', $time],
                    ['etime', '>', $time],
                    ['stime', '>', 0]
                ])->count(1);

                if ($count > 0) {
                    return ['', '打卡成功，如对方未打卡，11:00前结算本金和奖励，请留意', 0];
                }
            }
        }

        $count = Db::name('challenge_record')->where([
            ['user_id', '=', $user_id],
            ['room_id', '=', 0],
            ['date', '>=', $today_time]
        ])->count(1);

        if ($count == 0) {
            return ['', '亲，今天没有要打卡的挑战，请先找人对战先', 1];
        } else {
            return ['', '非约定打卡时间内，请查看本页面『进行中』详情', 1];
        }
    }

    public static function signin_room($user_id, $room_id)
    {
        $today_time = Common::today_time();
        $record = Db::name('challenge_record')->where([
            ['user_id', '=', $user_id],
            ['room_id', '=', $room_id],
            ['date', '>=', $today_time],
        ])->order('date asc')->find();

        if (empty($record)) { // 未加入 1、6
            return ['', '你还未加入该族的早起挑战，请先加入', 1];
        } else { // 已加入
            if ($record['date'] != $today_time) { // 明天打卡 2
                return ['', '非约定打卡时间内，请到『我的族群』页面查看', 1];
            } else { // 今天打卡
                $time = request()->time();
                if ($record['btime'] <= $time && $time < $record['etime']) { // 时间范围内 3
                    if ($record['stime'] == 0) {
                        $res = Db::name('challenge_record')->where([
                            ['user_id', '=', $user_id],
                            ['room_id', '=', $room_id],
                            ['btime', '<=', $time],
                            ['etime', '>', $time],
                            ['stime', '=', 0]
                        ])->update(['stime' => $time]);

                        if ($res) {
                            // 系统消息
                            $title = Db::name('challenge_room')->where([['room_id', '=', $room_id]])->value('title');
                            $content = '你参与的族群【' . $title . '】今天打卡成功，你的打卡时间' . Common::time_format($time);

                            $dynamic = [
                                'user_id' => 0,
                                'nickname' => '系统通知',
                                'avator' => '/static/img/logo.png',
                                'receive_uid' => $user_id,
                                'event' => 'signin_room',
                                'event_id' => $room_id,
                                'data' => serialize([
                                    'title' => '族群打卡成功通知',
                                    'content' => $content
                                ]),
                                'add_time' => $time
                            ];

                            $xinge_task = [
                                'user_id' => $user_id,
                                'event' => 'signin_room',
                                'data' => serialize([
                                    'title' => '族群打卡成功通知',
                                    'content' => $content
                                ])
                            ];

                            Db::name('user_dynamic')->insert($dynamic);
                            Db::name('xinge_task')->insert($xinge_task);

                            return ['', '打卡成功，11:00前结算发放奖励，请留意查收', 0, 'ok' => true];
                        } else {
                            return ['', '打卡失败，请重试', 1];
                        }
                    } else {
                        return ['', '打卡成功，11:00前结算发放奖励，请留意查收', 0];
                    }
                } elseif ($record['stime'] == 0) { // 未打卡
                    if ($time >= $record['etime']) { // 时间范围后 5
                        return ['', '今天你未准时打卡，契约金已被群分，请重新加入族群', 1];
                    } else {
                        return ['', '非约定打卡时间内，请到『我的族群』页面查看', 1];
                    }
                } else { // 已打卡 if ($record['stime'] > 0)
                    if ($time >= $record['etime'] && $time < $today_time + 39600) { // 时间范围后-结算前 4
                        return ['', '今天已成功打卡，11:00前结算发放奖励，请留意查收', 1];
                    } else { // 结算之后 7
                        return ['', '今天的早起打卡奖励已在11:00前结算发放，请明天继续准时过来打卡', 1];
                    }
                }
            }
        }
    }

    public static function signin_system_user()
    {
        debug(__FUNCTION__ . '_start');

        $today_time = Common::today_time();
        $filter = User::system_user_filter();

        do {
            $data = Db::name('challenge_record')
                ->where($filter)
                ->where([['stime', '=', 0], ['date', '<=', $today_time]])
                ->find();

            if (!empty($data)) {
                $stime = mt_rand($data['btime'], $data['etime']);

                Db::name('challenge_record')
                    ->where([
                        ['stime', '=', 0],
                        ['user_id', '=', $data['user_id']],
                        ['room_id', '=', $data['room_id']],
                        ['date', '=', $data['date']],
                    ])->update(['stime' => $stime]);
            }
        } while (!empty($data));
    }

    public static function signin_finish()
    {
        debug(__FUNCTION__ . '_start');

        $today_time = Common::today_time();
        $today_date = Common::today_date();

        $challenge_id = Db::name('challenge_record')->where([
            ['date', '=', $today_time],
            ['stime', '=', 0]
        ])->column('challenge_id');
        if (empty($challenge_id)) {
            return 0;
        }

        // 参与人未打卡
        Db::name('challenge')->where([
            ['status', '=', 1],
            ['join_date', '<', $today_date],
            ['expire_date', '>=', $today_date],
            ['challenge_id', 'in', $challenge_id]
        ])->update(['status' => $today_date]);

        // 发起人未打卡
        Db::name('challenge_both')->where([
            ['status', '=', 1],
            ['join_date', '<', $today_date],
            ['expire_date', '>=', $today_date],
            ['launch_cid', 'in', $challenge_id]
        ])->update(['launch_status' => 3]);

        // 接受人未打卡
        Db::name('challenge_both')->where([
            ['status', '=', 1],
            ['join_date', '<', $today_date],
            ['expire_date', '>=', $today_date],
            ['accept_cid', 'in', $challenge_id]
        ])->update(['accept_status' => 3]);

        // 挑战记录明天后算失败
        Db::name('challenge_record')->where([
            ['challenge_id', 'in', $challenge_id],
            ['date', 'gt', $today_time]
        ])->delete(); // ->update(['status' => 0]);
    }

    public static function stat()
    {
        // 8点后结算
        $today_time = Common::today_time();
        if (request()->time() < $today_time + 8 * 60 * 60) return false;

        self::signin_system_user();
        self::signin_finish();

        // 结算过
        $today_date = Common::today_date();
        $count = Db::name('stat')->where([['date', '=', $today_date]])->count(1);
        if ($count > 0) return false;

        // 结算
        self::stat_both();
        $res = self::stat_room();

        // M币结算自动发放
        User::stat_mb_auto();

        // 统计信息
        self::stat_room_info();
        self::stat_record($res);
        self::stat_record_both();
        self::stat_record_room();

        cache('challenge_stat', request()->time(true), $today_time + 86400);

        // 记录调试日志
        Debug::save_log();

        return true;
    }

    public static function stat_both()
    {
        debug(__FUNCTION__ . '_start');

        $time = request()->time();
        $today_date = Common::today_date();
        $today_time = Common::today_time();

        $income_mb_max = 100;
        $income_mb_info = $challenge_id = $challenge_id_win = [];
        $lose_puid = $lose_info = $lose_launch_cid = [];
        $dynamic = $xinge_task = [];

        // 单人
        $data = Db::name('challenge_both')->where([
            ['status', '=', 1],
            ['join_date', '<', $today_date],
            ['expire_date', '>=', $today_date]
        ])->select();

        $users = array_column($data, 'launch_uid');
        $users = array_merge($users, array_column($data, 'accept_uid'));
        $users = Db::name('user')->where([['id', 'in', $users]])->column('id,nickname');

        foreach ($data as $k => $item) {
            $challenge_id[] = $item['launch_cid'];
            $challenge_id[] = $item['accept_cid'];
            $data_save = [];

            if ($item['launch_status'] == 1 && $item['accept_status'] == 1) { // 双方完成
                if ($item['expire_date'] == $today_date) { // 结束日
                    $data_save = ['status' => 5, 'launch_status' => 2, 'accept_status' => 2];

                    // 双赢
                    $challenge_id_win[] = $item['launch_cid'];
                    $challenge_id_win[] = $item['accept_cid'];

                    // 平局奖励
                    if (empty($income_mb_info[$item['launch_uid']])) $income_mb_info[$item['launch_uid']] = 0;
                    if ($income_mb_info[$item['launch_uid']] < $income_mb_max) {
                        User::mb($item['launch_uid'], 20, 'challenge_income_both', $item['launch_cid'], '好友对战奖励');
                        $income_mb_info[$item['launch_uid']] += 20;

                        // 收益通知
                        $content = serialize([
                            'title' => '好友早起对战奖励 20M币',
                            'content' => "您跟【" . $users[$item['accept_uid']] . "】比拼谁能准时早起，两人都已完成打卡，双方各奖励20M币"
                        ]);

                        $dynamic[] = [
                            'user_id' => 0,
                            'nickname' => '系统通知',
                            'avator' => '/static/img/logo.png',
                            'receive_uid' => $item['launch_uid'],
                            'event' => 'challenge_income_both',
                            'event_id' => $item['both_id'],
                            'data' => $content,
                            'add_time' => $time
                        ];

                        $xinge_task[] = [
                            'user_id' => $item['launch_uid'],
                            'event' => 'challenge_income_both',
                            'data' => $content
                        ];
                    }

                    if (empty($income_mb_info[$item['accept_uid']])) $income_mb_info[$item['accept_uid']] = 0;
                    if ($income_mb_info[$item['accept_uid']] < $income_mb_max) {
                        User::mb($item['accept_uid'], 20, 'challenge_income_both', $item['accept_cid'], '好友对战奖励');
                        $income_mb_info[$item['accept_uid']] += 20;

                        // 收益通知
                        $content = serialize([
                            'title' => '好友早起对战奖励 20M币',
                            'content' => "您跟【" . $users[$item['launch_uid']] . "】比拼谁能准时早起，两人都已完成打卡，双方各奖励20M币"
                        ]);

                        $dynamic[] = [
                            'user_id' => 0,
                            'nickname' => '系统通知',
                            'avator' => '/static/img/logo.png',
                            'receive_uid' => $item['accept_uid'],
                            'event' => 'challenge_income_both',
                            'event_id' => $item['both_id'],
                            'data' => $content,
                            'add_time' => $time
                        ];

                        $xinge_task[] = [
                            'user_id' => $item['accept_uid'],
                            'event' => 'challenge_income_both',
                            'data' => $content
                        ];
                    }

                    // 返还本金
                    User::money($item['launch_uid'], $item['price'], 'challenge_price_both', $item['launch_cid'], '退还早起挑战契约金');
                    User::money($item['accept_uid'], $item['price'], 'challenge_price_both', $item['accept_cid'], '退还早起挑战契约金');
                }
            } elseif ($item['launch_status'] > 2 && $item['accept_status'] > 2) { // 双方失败
                $data_save = ['status' => 5];

                $lose_info[$k][] = [$item['launch_uid'], $item['accept_uid'], $item['both_id']];
                $lose_info[$k][] = [$item['accept_uid'], $item['launch_uid'], $item['both_id']];
                $lose_puid[] = $item['launch_uid'];
                $lose_puid[] = $item['accept_uid'];
                $lose_launch_cid[] = $item['launch_cid'];
            } else { // 单方完成
                $income = $item['price'];
                $fee = intval($income * 0.25); // 25%手续费
                $data_save = ['status' => 5]; // 直接结束
                $lose_launch_cid[] = $item['launch_cid'];

                if ($item['launch_status'] == 1) {
                    $win_uid = $item['launch_uid'];
                    $win_cid = $challenge_id_win[] = $item['launch_cid'];

                    $lose_uid = $item['accept_uid'];
                    // $lose_cid = $item['accept_cid'];

                    $lose_info[$k][] = [$item['accept_uid'], $item['launch_uid'], $item['both_id']];
                    $lose_puid[] = $item['launch_uid'];

                    $data_save['launch_status'] = 2;
                } elseif ($item['accept_status'] == 1) {
                    $win_uid = $item['accept_uid'];
                    $win_cid = $challenge_id_win[] = $item['accept_cid'];

                    $lose_uid = $item['launch_uid'];
                    // $lose_cid = $item['launch_cid'];

                    $lose_info[$k][] = [$item['launch_uid'], $item['accept_uid'], $item['both_id']];
                    $lose_puid[] = $item['accept_uid'];

                    $data_save['accept_status'] = 2;
                }

                if (!empty($win_uid)) {
                    User::money($win_uid, $income, 'challenge_income_both', $win_cid, '好友对战奖励');

                    if ($fee > 0) {
                        User::money($win_uid, -$fee, 'challenge_fee_both', $win_cid, '上缴对战公益金');
                    }

                    // if ($item['expire_date'] == $today_date) {} // 结束日返还本金
                    User::money($win_uid, $item['price'], 'challenge_price_both', $win_cid, '退还早起挑战契约金');

                    // 挑战记录明天后算失败
                    Db::name('challenge_record')->where([
                        ['challenge_id', 'in', $win_cid],
                        ['date', 'gt', $today_time]
                    ])->delete();

                    // 收益通知
                    $content = serialize([
                        'title' => '好友早起对战奖励 ' . ($income / 100) . '元',
                        'content' => "您跟【" . $users[$lose_uid] . "】比拼谁能准时早起，他今天未能兑现承诺，奖励您" . ($income / 100) . "元"
                    ]);

                    $dynamic[] = [
                        'user_id' => 0,
                        'nickname' => '系统通知',
                        'avator' => '/static/img/logo.png',
                        'receive_uid' => $win_uid,
                        'event' => 'challenge_income_both',
                        'event_id' => $win_cid,
                        'data' => $content,
                        'add_time' => $time
                    ];

                    $xinge_task[] = [
                        'user_id' => $win_uid,
                        'event' => 'challenge_income_both',
                        'data' => $content
                    ];
                }
            }

            // 更新状态
            if (!empty($data_save)) {
                Db::name('challenge_both')->where('both_id', '=', $item['both_id'])->update($data_save);
            }
        }

        if (!empty($challenge_id_win)) {
            Db::name('challenge')
                ->where('challenge_id', 'in', $challenge_id_win)
                ->update(['status' => 2]);
        }

        if (!empty($challenge_id)) {
            Db::name('challenge')
                ->where('challenge_id', 'in', $challenge_id)
                ->update(['stat_time' => $time]);
        }

        // 收益通知
        if (!empty($dynamic)) {
            Db::name('user_dynamic')->insertAll($dynamic);
            Db::name('xinge_task')->insertAll($xinge_task);
        }

        // 失败通知
        if (!empty($lose_info)) {
            $dynamic = $xinge_task = [];
            // $users = Db::name('user')->where([['id', 'in', $lose_puid]])->column('id,nickname');
            $challenge = Db::name('challenge')->where([['challenge_id', 'in', $lose_launch_cid]])->select();

            foreach ($lose_info as $k => $v) {
                if (empty($challenge[$k])) continue;
                $stime = self::parse_signin_time($challenge[$k])['stime'];

                foreach ($v as $u) {
                    if (empty($users[$u[1]])) continue;
                    $content = serialize([
                        'title' => '好友对战失败通知',
                        'content' => "您跟[{$users[$u[1]]}]的对战约定打卡时间是{$stime}，由于您今天未在约定时间范围内打卡，导致您该笔契约金被分给了对方，该对战任务结束，非常遗憾！参与后，请务必按约定的天数约定的打卡时间范围准时来打卡"
                    ]);

                    $dynamic[] = [
                        'user_id' => 0,
                        'nickname' => '系统通知',
                        'avator' => '/static/img/logo.png',
                        'receive_uid' => $u[0],
                        'event' => 'signin_both_fail',
                        'event_id' => $u[2],
                        'data' => $content,
                        'add_time' => $time
                    ];

                    $xinge_task[] = [
                        'user_id' => $u[0],
                        'event' => 'signin_both_fail',
                        'data' => $content
                    ];
                }
            }

            Db::name('user_dynamic')->insertAll($dynamic);
            Db::name('xinge_task')->insertAll($xinge_task);
        }

        debug(__FUNCTION__ . '_end');
    }

    public static function stat_room()
    {
        debug(__FUNCTION__ . '_start');

        $subsidy_total = 0;
        $income_mb_max = 100;
        $save_room = $income_mb_info = [];
        $dynamic = $xinge_task = [];
        $today_date = Common::today_date();
        $time = request()->time();
        $where = [['event', '=', 'join'], ['join_date', '<', $today_date], ['expire_date', '>=', $today_date]];

        // 失败统计
        $data_lose = Db::name('challenge')
            ->where($where)
            ->where('status', '=', $today_date)
            ->group('room_id')
            ->column('room_id,SUM(price) sum_price,COUNT(1) count_user');

        $has_lose = !empty($data_lose);

//        if (!empty($data_lose)) {
//            foreach ($data_lose as $item) {
//                $data_lose['data'][$item['room_id'] . '_' . $item['expire_date']] = $item;
//            }
//
//            $data_lose = $data_lose['data'];
//        }

        // 完成统计
        $data_win = Db::name('challenge')
            ->where($where)
            ->where('status', '=', 1)
            ->group('room_id')
            ->field('room_id,SUM(price) sum_price,COUNT(1) count_user')
            ->select();

        $room_ids = $challenge_save = [];

        if (!empty($data_lose)) {
            $room_ids = array_column($data_lose, 'room_id');
        }
        if (!empty($data_win)) {
            $room_ids = array_merge($room_ids, array_column($data_win, 'room_id'));
        }

        if (!empty($room_ids)) {
            $challenge_room = Db::name('challenge_room')
                ->where('room_id', 'in', $room_ids)
                ->column('room_id,user_id,leader_rate,subsidy,max_rate,expire_time,status,title,btime,etime');
        }

        debug(__FUNCTION__ . '_ready');

        foreach ($data_win as $item) {
            if (empty($challenge_room[$item['room_id']])) continue;

            $challenge = Db::name('challenge')
                ->where($where)
                ->where([
                    ['status', '=', 1],
                    ['room_id', '=', $item['room_id']],
                    //['expire_date', '=', $item['expire_date']]
                ])->select();

            $room = $challenge_room[$item['room_id']]; // 族群
            // $is_finish = $item['expire_date'] == $today_date; // 结束日
            $key = $item['room_id']; // . '_' . $item['expire_date'];
            $count_lose = empty($data_lose[$key]) ? 0 : $data_lose[$key]['count_user'];
            $save_room[$item['room_id']] = ['price_lose' => 0, 'price_grant' => 0, 'price_subsidy' => 0, 'price_tax' => 0, 'leader_income' => 0];

            $new_user_ids = [];
            if ($room['user_id'] == 0 || User::system_user_check($room['user_id'])) { // 官方族群
                $new_user_ids = Db::name('user')->where([
                    ['id', 'in', array_column($challenge, 'user_id')],
                    ['add_time', '>=', $time - (86400 * 5)]
                ])->column('id');
            }

            if ($count_lose == 0) { // 全部完成
                $subsidy = $room['subsidy'];

                foreach ($challenge as $v) {
                    if ($subsidy > 0) {
                        User::money($v['user_id'], $subsidy, 'challenge_income_room', $v['challenge_id'], '早起全勤奖励');
                        $subsidy_total += $subsidy;

                        $challenge_save[$v['challenge_id']]['income'] = $subsidy;
                        $save_room[$item['room_id']]['price_subsidy'] += $subsidy;
                        $save_room[$item['room_id']]['price_grant'] += $subsidy;

                        // 收益通知
                        $content = serialize([
                            'title' => '早起奖励通知  奖励 ' . ($subsidy / 100) . '元',
                            'content' => "你参与的早睡生活族群【" . $room['title'] . "】今天成功早起，奖励您" . ($subsidy / 100) . "元"
                        ]);
                    } else {
                        if (empty($income_mb_info[$v['user_id']])) $income_mb_info[$v['user_id']] = 0;
                        if ($income_mb_info[$v['user_id']] < $income_mb_max) {
                            User::mb($v['user_id'], 20, 'challenge_income_room', $v['challenge_id'], '早起全勤奖励');
                            $income_mb_info[$v['user_id']] += 20;
                        }

                        // 收益通知
                        $content = serialize([
                            'title' => '早起奖励通知  奖励 20M币',
                            'content' => "你参与的早睡生活族群【" . $room['title'] . "】今天成功早起，奖励您20M币"
                        ]);
                    }

                    //if ($is_finish) { // 返还本金
                    if ($v['expire_date'] == $today_date) {
                        User::money($v['user_id'], $v['price'], 'challenge_price_room', $v['challenge_id'], '退还族群挑战契约金');

                        if ($v['auto']) { // 继续挑战
                            self::join($v['user_id'], ['room_id' => $v['room_id'], 'price' => $v['price'] / 100, 'use_balance' => 1, 'no_check_price' => true]);
                        }
                    }

                    // 收益通知
                    $dynamic[] = [
                        'user_id' => 0,
                        'nickname' => '系统通知',
                        'avator' => '/static/img/logo.png',
                        'receive_uid' => $v['user_id'],
                        'event' => 'challenge_income_room',
                        'event_id' => $v['challenge_id'],
                        'data' => $content,
                        'add_time' => $time
                    ];

                    $xinge_task[] = [
                        'user_id' => $v['user_id'],
                        'event' => 'challenge_income_room',
                        'data' => $content
                    ];
                }
            } else {
                $income_min = 1; // 最低收益
                $leader_income = 0; // 族长收益
                $donate_rate = 0.3; // 族长捐系统比例
                $price_lose = intval($data_lose[$key]['sum_price']); // 未打卡金额
                $price_win = intval($item['sum_price']); // 已打卡金额
                $save_room[$item['room_id']]['price_lose'] = $price_lose;

                $income_rate = $price_lose / $price_win; // 收益比例
                $max_rate = $room['max_rate'] / 100; // 最高比例
                if ($max_rate > 0 && $income_rate > $max_rate) $income_rate = $max_rate;

                $leader_rate = $room['leader_rate'] / 100; // 族长收益比例
                $leader_uid = $room['user_id']; // 族长

                foreach ($challenge as $v) {
                    $income = round($v['price'] * $income_rate, 2);

                    if ($v['price'] <= 5000 && in_array($v['user_id'], $new_user_ids)) {
                        $income_rate_rand = mt_rand(8, 12) / 1000;
                        if ($income_rate_rand > $income_rate) { // 高于实际补贴
                            $subsidy_total -= $income;
                            $save_room[$item['room_id']]['price_subsidy'] -= $income;
                            $income = round($v['price'] * $income_rate_rand, 2);
                            $subsidy_total += $income;
                            $save_room[$item['room_id']]['price_subsidy'] += $income;
                        }
                    }

                    if ($income < $income_min) $income = $income_min;
                    elseif ($income > $v['price']) $income = $v['price'];

                    $fee = round($income * $leader_rate, 2);
                    $leader_income += $fee;
                    User::money($v['user_id'], $income, 'challenge_income_room', $v['challenge_id'], '族群挑战奖励');
                    User::money($v['user_id'], -$fee, 'challenge_donate', $v['challenge_id'], '打赏族长');
                    $save_room[$item['room_id']]['price_grant'] += $income;

                    //if ($is_finish) { // 返还本金
                    if ($v['expire_date'] == $today_date) {
                        User::money($v['user_id'], $v['price'], 'challenge_price_room', $v['challenge_id'], '退还族群挑战契约金');

                        if ($v['auto']) { // 继续挑战
                            self::join($v['user_id'], ['room_id' => $v['room_id'], 'price' => $v['price'] / 100, 'use_balance' => 1, 'no_check_price' => true]);
                        }
                    }

                    $challenge_save[$v['challenge_id']]['income'] = $income;

                    // 收益通知
                    $content = serialize([
                        'title' => '早起奖励通知  奖励 ' . ($income / 100) . '元',
                        'content' => "你参与的早睡生活族群【" . $room['title'] . "】今天成功早起，奖励您" . ($income / 100) . "元"
                    ]);

                    $dynamic[] = [
                        'user_id' => 0,
                        'nickname' => '系统通知',
                        'avator' => '/static/img/logo.png',
                        'receive_uid' => $v['user_id'],
                        'event' => 'challenge_income_room',
                        'event_id' => $v['challenge_id'],
                        'data' => $content,
                        'add_time' => $time
                    ];

                    $xinge_task[] = [
                        'user_id' => $v['user_id'],
                        'event' => 'challenge_income_room',
                        'data' => $content
                    ];
                }

                if ($leader_income > 0 && $leader_uid > 0 && !empty($room['status']) && $room['expire_time'] > request()->time()) {
                    $leader_donate = round($leader_income * $donate_rate, 2);
                    User::money($leader_uid, $leader_income, 'leader_income', $item['room_id'], '族员打赏奖励');
                    User::money($leader_uid, -$leader_donate, 'leader_donate', $item['room_id'], '发放族员奖励');

                    $save_room[$item['room_id']]['leader_income'] += $leader_income;
                    $save_room[$item['room_id']]['price_tax'] += $leader_donate;

                    // 收益通知
                    $content = serialize([
                        'title' => '族员打赏奖励通知  奖励 ' . ($leader_income / 100) . '元',
                        'content' => "你的早起生活族群【" . $room['title'] . "】的族员们打赏您" . ($leader_income / 100) . "元，记得给他们多发红包哦"
                    ]);

                    $dynamic[] = [
                        'user_id' => 0,
                        'nickname' => '系统通知',
                        'avator' => '/static/img/logo.png',
                        'receive_uid' => $leader_uid,
                        'event' => 'leader_income',
                        'event_id' => $item['room_id'],
                        'data' => $content,
                        'add_time' => $time
                    ];

                    $xinge_task[] = [
                        'user_id' => $leader_uid,
                        'event' => 'leader_income',
                        'data' => $content
                    ];
                }

                unset($data_lose[$key]);
            }

//            // 更新族群收益率
//            Db::name('challenge_room')
//                ->where([['room_id', '=', $item['room_id']]])
//                ->update(['income_rate' => $count_lose == 0 ? 0 : ($price_lose / $price_win * 100)]);

            debug(__FUNCTION__ . '_room' . $item['room_id']);
        }

        // 所有人没打卡班长有收入
        foreach ($data_lose as $item) {
            if (empty($challenge_room[$item['room_id']])) continue;

            $room = $challenge_room[$item['room_id']]; // 族群
            if (empty($room['status']) || $room['expire_time'] <= request()->time()) continue;
            $save_room[$item['room_id']] = ['price_lose' => 0, 'price_grant' => 0, 'price_subsidy' => 0, 'price_tax' => 0, 'leader_income' => 0];

            $leader_income = round($item['sum_price'] * $room['leader_rate'] / 100, 2); // 族长收益
            $donate_rate = 0.3; // 族长捐系统比例
            $leader_uid = $room['user_id']; // 族长

            if ($leader_income > 0 && $leader_uid > 0) {
                $leader_donate = round($leader_income * $donate_rate, 2);
                User::money($leader_uid, $leader_income, 'leader_income', $item['room_id'], '族员打赏奖励');
                User::money($leader_uid, -$leader_donate, 'leader_donate', $item['room_id'], '发放族员奖励');

                $save_room[$item['room_id']]['leader_income'] += $leader_income;
                $save_room[$item['room_id']]['price_tax'] += $leader_donate;

                // 收益通知
                $content = serialize([
                    'title' => '族员打赏奖励通知  奖励 ' . ($leader_income / 100) . '元',
                    'content' => "你的早起生活族群【" . $room['title'] . "】的族员们打赏您" . ($leader_income / 100) . "元，记得给他们多发红包哦"
                ]);

                $dynamic[] = [
                    'user_id' => 0,
                    'nickname' => '系统通知',
                    'avator' => '/static/img/logo.png',
                    'receive_uid' => $leader_uid,
                    'event' => 'leader_income',
                    'event_id' => $item['room_id'],
                    'data' => $content,
                    'add_time' => $time
                ];

                $xinge_task[] = [
                    'user_id' => $leader_uid,
                    'event' => 'leader_income',
                    'data' => $content
                ];
            }

            $save_room[$item['room_id']]['price_lose'] += $item['sum_price'];
        }

        // 挑战成功
        Db::name('challenge')
            ->where([
                ['expire_date', '=', $today_date],
                ['status', '=', 1]
            ])->update(['status' => 2]);

        // 更新挑战信息
        foreach ($challenge_save as $k => $v) {
            if (!empty($v['income'])) $v['income'] = Db::raw('income + ' . $v['income']);

            Db::name('challenge')->where([['challenge_id', '=', $k]])->update($v);
        }

        // 更新族群信息
        foreach ($save_room as $k => $v) {
            if (empty($v['price_lose'])) {
                unset($v['price_lose']);
            } else {
                $v['price_lose'] = Db::raw('price_lose + ' . $v['price_lose']);
            }

            if (empty($v['price_grant'])) {
                unset($v['price_grant']);
            } else {
                $v['price_grant'] = Db::raw('price_grant + ' . $v['price_grant']);
            }

            if (empty($v['price_subsidy'])) {
                unset($v['price_subsidy']);
            } else {
                $v['price_subsidy'] = Db::raw('price_subsidy + ' . $v['price_subsidy']);
            }

            if (empty($v['price_tax'])) {
                unset($v['price_tax']);
            } else {
                $v['price_tax'] = Db::raw('price_tax + ' . $v['price_tax']);
            }

            if (empty($v['leader_income'])) {
                unset($v['leader_income']);
            } else {
                $v['leader_income'] = Db::raw('leader_income + ' . $v['leader_income']);
            }

            Db::name('challenge_room')->where([['room_id', '=', $k]])->update($v);
        }

        // 更新结束时间
        Db::name('challenge')
            ->where($where)
            ->where([['stat_time', '=', 0], ['status', '>', 1]])
            ->update(['stat_time' => request()->time()]);

        // 收益通知
        if (!empty($dynamic)) {
            Db::name('user_dynamic')->insertAll($dynamic, false, 100);
            Db::name('xinge_task')->insertAll($xinge_task, false, 100);
        }

        // 失败通知
        if ($has_lose) {
            $dynamic = $xinge_task = [];
            $challenge = Db::name('challenge')
                ->where($where)
                ->where('status', '=', $today_date)
                ->select();

            foreach ($challenge as $item) {
                if (empty($challenge_room[$item['room_id']])) continue;
                $room = $challenge_room[$item['room_id']]; // 族群
                $stime = self::parse_signin_time($room)['stime'];
                $content = serialize([
                    'title' => '族群打卡失败通知',
                    'content' => "你参与的族群[{$room['title']}]约定的打卡时间{$stime}，由于您今天未在约定时间范围内打卡，导致您参与该族群的契约金被其他成功打卡的族员分掉，该族群打卡任务结束，非常遗憾！参与后，请务必按约定的天数约定的打卡时间范围准时来打卡"
                ]);

                $dynamic[] = [
                    'user_id' => 0,
                    'nickname' => '系统通知',
                    'avator' => '/static/img/logo.png',
                    'receive_uid' => $item['user_id'],
                    'event' => 'signin_room_fail',
                    'event_id' => $item['challenge_id'],
                    'data' => $content,
                    'add_time' => $time
                ];

                $xinge_task[] = [
                    'user_id' => $item['user_id'],
                    'event' => 'signin_room_fail',
                    'data' => $content
                ];
            }

            Db::name('user_dynamic')->insertAll($dynamic, false, 100);
            Db::name('xinge_task')->insertAll($xinge_task, false, 100);
        }

        debug(__FUNCTION__ . '_end');

        return ['subsidy' => $subsidy_total];
    }

    // 更新族群信息
    public static function stat_room_info($room_ids = [])
    {
        debug(__FUNCTION__ . '_start');

        $table_best = Db::name('challenge_best')->getTable();
        $table_record = Db::name('challenge_record')->getTable();
        $today_time = Common::today_time();

        Db::execute('TRUNCATE TABLE ' . $table_best);

        if (empty($room_ids)) {
            $room_ids = Db::name('challenge_record')
                ->where([['date', '=', $today_time], ['room_id', '>', 0]])
                ->group('room_id')
                ->column('room_id');
        }

        if (!empty($room_ids)) {
            $time = request()->time();
            $data_save = $sql_earlier = $sql_insist = $sql_nature_user = $user_ids = [];
            $fields = Db::name('challenge_best')->getTableFields();
            $nature_user = User::system_user_filter('user_id', '!');
            if (!empty($nature_user)) $nature_user = ' AND ' . $nature_user;
            foreach ($fields as $v) {
                $fields['tmp'][$v] = '';
            }
            $fields = $fields['tmp'];
            unset($fields['id']);

            foreach ($room_ids as $room_id) {
                User::income(0, $room_id);

                $sql_earlier[] = "(SELECT room_id,user_id,stime FROM {$table_record} WHERE room_id = {$room_id} AND date = {$today_time} AND stime > 0 ORDER BY stime,record_id LIMIT 1)";
                $sql_insist[] = "(SELECT room_id,user_id,COUNT(1) c FROM {$table_record} WHERE room_id = {$room_id} AND stime > 0 AND stime < {$time} GROUP BY user_id ORDER BY c DESC LIMIT 1)";
                $sql_nature_user[] = "(SELECT {$room_id} AS room_id,COUNT(1) c FROM {$table_record} WHERE room_id = {$room_id} AND date = {$today_time} {$nature_user} LIMIT 1)";

                $data_save[$room_id] = $fields;
            }

            $data = Db::query(implode(' UNION ALL ', $sql_earlier));
            if (!empty($data)) {
                foreach ($data as $item) {
                    $tmp = $data_save[$item['room_id']];
                    $tmp['earlier_uid'] = $item['user_id'];
                    $tmp['earlier_time'] = $item['stime'];

                    $data_save[$item['room_id']] = $tmp;
                    $user_ids[] = $item['user_id'];
                }
            }

            $data = Db::query(implode(' UNION ALL ', $sql_insist));
            if (!empty($data)) {
                foreach ($data as $item) {
                    $tmp = $data_save[$item['room_id']];
                    $tmp['insist_uid'] = $item['user_id'];
                    $tmp['insist_day'] = $item['c'];

                    $data_save[$item['room_id']] = $tmp;
                    $user_ids[] = $item['user_id'];
                }
            }

            $nature_user = [];
            $data = Db::query(implode(' UNION ALL ', $sql_nature_user));
            if (!empty($data)) {
                foreach ($data as $item) {
                    if (empty($item['c'])) {
                        $nature_user[] = $item['room_id'];
                    }
                }
            }

            $user = DB::name('user')->where([['id', 'in', $user_ids]])->column('id,nickname,avator');
            foreach ($data_save as $k => $item) {
                $item['room_id'] = $k;
                $item['edit_time'] = $time;

                if (!empty($item['earlier_uid']) && !empty($user[$item['earlier_uid']])) {
                    $item['earlier_nickname'] = $user[$item['earlier_uid']]['nickname'];
                    $item['earlier_avator'] = $user[$item['earlier_uid']]['avator'];
                }

                if (!empty($item['insist_uid']) && !empty($user[$item['insist_uid']])) {
                    $item['insist_nickname'] = $user[$item['insist_uid']]['nickname'];
                    $item['insist_avator'] = $user[$item['insist_uid']]['avator'];
                }

                $data_save[$k] = $item;
            }

            // 输人数统计
            $data = Db::name('challenge_record')->where([
                ['room_id', 'in', $room_ids],
                ['date', '=', $today_time],
                ['stime', '=', 0]
            ])->group('room_id')->field('room_id,COUNT(DISTINCT user_id) c')->select();
            if (!empty($data)) {
                foreach ($data as $item) {
                    $data_save[$item['room_id']]['lose_num'] = $item['c'];
                }
            }

            // 赢人数统计
            $data = Db::name('challenge_record')->where([
                ['room_id', 'in', $room_ids],
                ['date', '=', $today_time],
                ['stime', '>', 0]
            ])->group('room_id')->field('room_id,COUNT(DISTINCT user_id) c')->select();
            if (!empty($data)) {
                // 官方族群
                $room_ids = Db::name('challenge_record')
                    ->where([['date', '=', $today_time], ['room_id', '>', 0]])
                    ->where(User::system_user_filter())
                    ->group('room_id')
                    ->column('room_id');

                foreach ($data as $item) {
                    $win_num = $item['c'];

                    if (in_array($item['room_id'], $room_ids) // 官方族群
                        && in_array($item['room_id'], $nature_user) // 全系统用户参与
                        && ($lose_num = $data_save[$item['room_id']]['lose_num']) == 0 // 全赢
                    ) {
                        $num = intval($win_num * mt_rand(5, 13) / 100);
                        if ($num > 0) {
                            $data_save[$item['room_id']]['lose_num'] = $num;
                            $win_num -= $num;
                        }
                    }

                    $data_save[$item['room_id']]['win_num'] = $win_num;
                }
            }

            if ($data_save) Db::name('challenge_best')->insertAll($data_save);
        }

        // 更新族群进行中的挑战金
        Db::name('challenge_room')->alias('r')->where('1 = 1')
            ->exp('price_ing', '(SELECT SUM(price) FROM sl_challenge WHERE room_id = r.room_id AND `status` = 1)')
            ->update();

        // 更新收益率
        Db::name('challenge_room')
            ->where([['price_all', '>', 0], ['income_rate', 'exp', Db::raw('< price_lose / price_all * 100')]])
            ->exp('income_rate', 'price_lose / price_all * 100')
            ->update();

        debug(__FUNCTION__ . '_end');
    }

    // 总报表，按统计时间统计全部，不需要按自然天
    public static function stat_record($option = [])
    {
        debug(__FUNCTION__ . '_start');

        $subsidy = 0; // 系统补贴
        if (!empty($option['subsidy'])) {
            $subsidy = $option['subsidy'];
        }

        // 支付用户数
        $pay_user = Db::query('SELECT COUNT(1) c FROM (' . Db::name('user_money')->where([['event', 'like', 'recharge%']])->group('user_id')->field('user_id')->buildSql() . ') t');
        $pay_user = $pay_user[0]['c'];

        // 支付金额
        $payment = Db::name('user_money')->where([['event', 'like', 'recharge%']])->sum('money');

        // 族群费用
        $room_fee = Db::name('user_money')->where([['event', 'in', ['room_fee', 'recommend_room']]])->sum('money');

        // 上缴金额
        $tax = Db::name('user_money')->where([['event', 'in', ['challenge_fee_both', 'leader_donate']]])->sum('money');

        // 发放奖励
        $grant = Db::name('user_money')->where([['event', 'in', ['challenge_income_both', 'challenge_income_room']]])->sum('money');

        // M币奖励
        $mb_change = Db::name('user_money')->where([['event', '=', 'mb_change']])->sum('money');

        // 激活数
        $active_count = Db::name('app_active')->count(1);

        // 注册数
        $where = User::system_user_filter('id', '!');
        $regist_count = Db::name('user')->where($where)->count(1);

        // 电商结算
        $platform_commission = 0;

        // 提现金额
        $withdraw = Db::name('user_withdraw')->where('status', '=', 1)->sum('price');
        $withdraw = round($withdraw / 100, 2);

        // 通道费用
        $channel = round($payment * 0.006, 2);

        // 发放佣金
        $grant_commission = 0;

        // 不足一元
        $data = Db::name('user')->where([['balance', '>', 0], ['balance', '<', 100]])->field('COUNT(1) c,SUM(balance) s')->find();
        $less_1yuan_count = $data['c'];
        $less_1yuan_balance = intval($data['s']) / 100;

        $data = [
            'date' => Common::today_date(),
            'active_count' => $active_count,
            'regist_count' => $regist_count,
            'deposit' => User::platform_money(true), // 沉淀金额
            'pay_user' => $pay_user,
            'payment' => $payment,
            'room_fee' => abs($room_fee),
            'tax' => abs($tax),
            'grant' => $grant,
            'mb_change' => $mb_change,
            'subsidy' => $subsidy,
            'platform_commission' => $platform_commission,
            'withdraw' => $withdraw,
            'channel' => $channel,
            'grant_commission' => $grant_commission,
            'less_1yuan_count' => $less_1yuan_count,
            'less_1yuan_balance' => $less_1yuan_balance
        ];

        Db::name('stat')->insert($data);

        debug(__FUNCTION__ . '_end');
    }

    public static function stat_record_both()
    {
        debug(__FUNCTION__ . '_start');

        $tax = Db::name('user_money')->where([['event', '=', 'challenge_fee_both']])->sum('money');
        $grant = Db::name('user_money')->where([['event', '=', 'challenge_income_both']])->sum('money');

        // $price_all = Db::name('user_money')->where([['event', '=', 'challenge_launch']])->sum('money');

        // 所有
        $all = Db::name('challenge')
            ->where([['event', 'in', ['launch', 'accept']], ['status', 'not in', [0, 3]]])
            ->field('SUM(price) sum_price,COUNT(DISTINCT user_id) count_user,COUNT(1) count_record')
            ->find();
        $all_price = $all['sum_price'] / 100;
        $all_count_user = $all['count_user'];
        $all_count_record = $all['count_record'];

        // 失败
        $lose = Db::name('challenge')
            ->where([['event', 'in', ['launch', 'accept']], ['status', '>', 5]])
            ->field('SUM(price) sum_price,COUNT(DISTINCT user_id) count_user,COUNT(1) count_record')
            ->find();
        $lose_price = $lose['sum_price'] / 100;
        $lose_count_user = $lose['count_user'];
        $lose_count_record = $lose['count_record'];

        // 成功
        $win = Db::name('challenge')
            ->where([['event', 'in', ['launch', 'accept']], ['status', '=', 2]])
            ->field('SUM(price) sum_price,COUNT(DISTINCT user_id) count_user,COUNT(1) count_record')
            ->find();
        $win_price = $win['sum_price'] / 100;
        $win_count_user = $win['count_user'];
        $win_count_record = $win['count_record'];

        // 进行中
        $ing = Db::name('challenge')
            ->where([['event', 'in', ['launch', 'accept']], ['status', '=', 1]])
            ->field('SUM(price) sum_price,COUNT(DISTINCT user_id) count_user,COUNT(1) count_record')
            ->find();
        $ing_price = $ing['sum_price'] / 100;
        $ing_count_user = $ing['count_user'];
        $ing_count_record = $win['count_record'];

        Db::name('stat_challenge_both')->insert([
            'date' => Common::today_date(),
            'day' => 0,
            'tax' => abs($tax),
            'grant' => $grant - $tax,
            'profit' => $lose_price - $grant + $tax,
            'all_price' => $all_price,
            'all_count_user' => $all_count_user,
            'all_count_record' => $all_count_record,
            'lose_price' => $lose_price,
            'lose_count_user' => $lose_count_user,
            'lose_count_record' => $lose_count_record,
            'win_price' => $win_price,
            'win_count_user' => $win_count_user,
            'win_count_record' => $win_count_record,
            'ing_price' => $ing_price,
            'ing_count_user' => $ing_count_user,
            'ing_count_record' => $ing_count_record
        ]);

        debug(__FUNCTION__ . '_end');
    }

    public static function stat_record_room()
    {
        debug(__FUNCTION__ . '_start');

        $rooms = Db::name('challenge_room')->select();
        if (empty($rooms)) return false;

        $data = [];
        foreach ($rooms as $room) {
            $room_id = $room['room_id'];

            // 所有
            $all = Db::name('challenge')
                ->where([['room_id', '=', $room_id]])
                ->field('SUM(price) sum_price,COUNT(DISTINCT user_id) count_user,COUNT(1) count_record')
                ->find();
            $all_price = $all['sum_price'] / 100;
            $all_count_user = $all['count_user'];
            $all_count_record = $all['count_record'];

            // 失败
            $lose = Db::name('challenge')
                ->where([['room_id', '=', $room_id], ['status', '>', 5]])
                ->field('SUM(price) sum_price,COUNT(DISTINCT user_id) count_user,COUNT(1) count_record')
                ->find();
            $lose_price = $lose['sum_price'] / 100;
            $lose_count_user = $lose['count_user'];
            $lose_count_record = $lose['count_record'];

            // 成功
            $win = Db::name('challenge')
                ->where([['room_id', '=', $room_id], ['status', '=', 2]])
                ->field('SUM(price) sum_price,COUNT(DISTINCT user_id) count_user,COUNT(1) count_record')
                ->find();
            $win_price = $win['sum_price'] / 100;
            $win_count_user = $win['count_user'];
            $win_count_record = $win['count_record'];

            // 进行中
            $ing = Db::name('challenge')
                ->where([['room_id', '=', $room_id], ['status', '=', 1]])
                ->field('SUM(price) sum_price,COUNT(DISTINCT user_id) count_user,COUNT(1) count_record')
                ->find();
            $ing_price = $ing['sum_price'] / 100;
            $ing_count_user = $ing['count_user'];
            $ing_count_record = $win['count_record'];

            $data[] = [
                'date' => Common::today_date(),
                'room_id' => $room_id,
                'day' => $room['day'],
                'nickname' => $room['nickname'],
                'tax' => $room['price_tax'] / 100,
                'grant' => $room['price_grant'] / 100,
                'profit' => $lose_price - $room['price_grant'] / 100 + $room['price_tax'] / 100,
                'subsidy' => $room['price_subsidy'] / 100,
                'leader_income' => $room['leader_income'] / 100,
                'all_price' => $all_price,
                'all_count_user' => $all_count_user,
                'all_count_record' => $all_count_record,
                'lose_price' => $lose_price,
                'lose_count_user' => $lose_count_user,
                'lose_count_record' => $lose_count_record,
                'win_price' => $win_price,
                'win_count_user' => $win_count_user,
                'win_count_record' => $win_count_record,
                'ing_price' => $ing_price,
                'ing_count_user' => $ing_count_user,
                'ing_count_record' => $ing_count_record
            ];
        }

        Db::name('stat_challenge_room')->insertAll($data);

        debug(__FUNCTION__ . '_end');
    }

    public static function rollback()
    {
        debug(__FUNCTION__ . '_start');

        $yeaterday_date = date('Ymd', Common::today_time() - 86400);
        $where = [['join_date', '=', $yeaterday_date], ['status', '=', 0]];

        $challenge_id_all = $challenge_id_both = [];
        $data = Db::name('challenge')->where($where)->select();

        if (empty($data)) return false;

        foreach ($data as $item) {
            if ($item['event'] == 'launch') {
                $challenge_id_both[] = $item['challenge_id'];
            }

            $challenge_id_all[] = $item['challenge_id'];

            User::money($item['user_id'], $item['price'], 'challenge_back', $item["challenge_id"], '退还早起挑战金');
        }

        if (!empty($challenge_id_both)) {
            Db::name('challenge_both')->where('launch_cid', 'in', $challenge_id_both)->delete();
        }

        Db::name('challenge_record')->where('challenge_id', 'in', $challenge_id_all)->delete();
        Db::name('challenge')->where('challenge_id', 'in', $challenge_id_all)->update(['status' => 4, 'stat_time' => request()->time()]);

        debug(__FUNCTION__ . '_end');
    }

    public static function dynamic($room_id, $user_id, $next_id = 0, $limit = 20)
    {
        $where = [['room_id', '=', $room_id]];
        if (is_null($next_id)) $next_id = input('next_id', 0, 'intval');
        if ($next_id) $where[] = ['id', 'lt', $next_id];
        $data = Db::name('challenge_dynamic')->where($where)->order('id DESC')->limit($limit)->select();

        if (empty($data)) return ['data' => [], 'next_id' => 0];

        $next_id = count($data) >= $limit ? end($data)['id'] : 0;

//        $where = D('Ad')->get_valid_filter($user_id, 'dynamic');
//        $data_ad = M('ad')->where($where)->field('ad_id,ad_pic,ad_title')->limit(100)->select();
//        $count_ad = count($data_ad) - 1;
//        $ad_link = U('Mpwx/Ad/read');

        foreach ($data as $key => $item) {
            $item['data'] = unserialize($item['data']);

            if ($item['user_id'] == $user_id) {
                $item['position'] = 'right';
            } else {
                $item['position'] = 'left';
            }

            if ($item['event'] == 'redpack') {
                if ($item['room_id']) {
                    $class_redpack[$key] = $item['id'];
                }
            }

            if ($item['event'] == 'challenge_launch') {
                if ($item['user_id'] == $user_id) {
                    $item['errmsg'] = '不能接受自己发起的挑战';
                }
            }

            $item['add_time'] = Common::time_format($item['add_time']);
            unset($item['user_id']);
            unset($item['id']);
            $data[$key] = $item;
        }

        if ($class_redpack) {
            // 已领红包
            $dynamic_read = Db::name('challenge_dynamic_read')->where(
                [
                    ['user_id', '=', $user_id],
                    ['status', '=', 2],
                    ['dynamic_id', 'in', $class_redpack]
                ]
            )->column('dynamic_id');

            foreach ($class_redpack as $key => $item) {
                if (in_array($item, $dynamic_read)) {
                    $data[$key]['status'] = 2;
                }
            }
        }

        return ['data' => $data, 'next_id' => $next_id];
    }

    public function poster_create($user_id, $room_id, $domain = true, $options = [])
    {
        $root_path = Config::get('app.poster.root_path');
        $file_qrcode = $root_path . 'qrcode/challenge/' . $room_id;
        if (!empty($options['invite_code'])) {
            $file_qrcode .= '_' . $user_id;
        }

        $file_qrcode .= '.png';

        if (!file_exists($file_qrcode)) {
            // 二维码
            $url = config('site.url') . "/?s=home/challenge/join&room_id=" . $room_id;
            if (!empty($options['invite_code'])) $url .= '&invite_code=' . $options['invite_code'];

            $rs = Common::make_qrcode($url, $file_qrcode);
            if ($rs == '') {
                return false;
            }
        }

        // 用户信息
        $user_data = User::find($user_id, 'id, nickname, avator');

        // 下载头像
        $file_head = User::headimg_create_thumb($user_data);
        if (!$file_head && !is_file($file_head)) {
            $file_head = $root_path . 'head/default.png';
        }

        // 生成海报
        $file_poster = $root_path . 'poster/challenge/' . $room_id . '_' . $user_data['id'] . '.jpg';
        // if (env('APP_DEBUG')) unlink($file_poster);

        // 是否更新海报
//        if (!file_exists($file_poster)) {
//            $img = \think\Image::open($root_path . 'poster/bg/challenge_bg.jpg');
//            $font_path = './static/fonts/PINGFANG.TTF';
//
//            $size_qrcode = 225;
//            $x_qrcode = 263;
//            $y_qrcode = 837;
//
//            $size_head = 80;
//            $x_head = 266;
//            $y_head = 160;
//
//            $img->water($file_qrcode, [$x_qrcode, $y_qrcode], 100, ['width' => $size_qrcode])
//                ->water($file_head, [$x_head, $y_head], 100, ['width' => $size_head, 'radius' => true])
//                ->text($user_data['nickname'], $font_path, 19, [0, 0, 0, 0], \think\Image::WATER_NORTHWEST, [358, 211])
//                ->save($file_poster);
//        }

        $room_title = Db::name('challenge_room')->where([['room_id', '=', $room_id]])->value('title');
        if (mb_strlen($room_title) > 6) $room_title = mb_substr($room_title, 0, 5) . '...';

        if (!file_exists($file_poster)) {
            $img = \think\Image::open($root_path . 'poster/bg/challenge_bg3.jpg');
            $font_path = './static/fonts/PINGFANG.TTF';

            $size_qrcode = 225;
            $x_qrcode = 263;
            $y_qrcode = 900;

            $size_head = 200;
            $x_head = 310;
            $y_head = 400;

            $img->water($file_qrcode, [$x_qrcode, $y_qrcode], 100, ['width' => $size_qrcode])
                ->water($file_head, [$x_head, $y_head], 100, ['width' => $size_head, 'radius' => true])
                ->text($room_title, $font_path, 60, [252, 163, 22, 0], \think\Image::WATER_CENTER, [0, 140])
                ->text($room_title, $font_path, 60, [252, 163, 22, 0], \think\Image::WATER_CENTER, [3, 140])
                ->save($file_poster);
        }

        if ($domain) {
            $file_poster = config('site.url') . trim($file_poster, '.');
        }

        return $file_poster;
    }

    public static function income_room($user_id)
    {
        return Db::name('user_money')->where([
            ['user_id', '=', $user_id],
            ['event', 'in', ['challenge_income_room', 'leader_income']]
        ])->sum('money');
    }

    public static function challenge_day($user_id)
    {
        $day = Db::query("SELECT COUNT(1) day FROM(" .
            db('challenge_record')->where([
                ['user_id', '=', $user_id],
                ['stime', '>', 0]
            ])->group('date')->buildSql()
            . ") t GROUP BY user_id");

        if (empty($day)) {
            $day = 0;
        } else {
            $day = $day[0]['day'];
        }

        return $day;
    }

    public static function room_expire_dynamic()
    {
        $time = request()->time();
        $room = Db::name('challenge_room')->where([
            ['expire_time', '>', $time],
            ['expire_time', '<', $time + 3 * 86400]
        ])->field('room_id,user_id,title,expire_time')->select();

        if (empty($room)) return false;

        $data = $xinge_task = [];
        foreach ($room as $item) {
            // $day = floor(($item['expire_time'] - $time) / 86400); // 有效期只剩下{$day}天
            $data[] = [
                'user_id' => 0,
                'nickname' => '系统通知',
                'avator' => '/static/img/logo.png',
                'receive_uid' => $item['user_id'],
                'event' => 'room_expire',
                'event_id' => $item['room_id'],
                'data' => serialize([
                    'title' => '族群族长身份失效通知',
                    'content' => "亲，您的族群“{$item['title']}”的族长身份特权过期时间" . date('Y-m-d', $item['expire_time']) . "，过期后将不再享受族长应有的提成，如不想续期，可无视该条通知，如果想续期请点击查看并进行续期"
                ]),
                'add_time' => $time
            ];

            $xinge_task[] = [
                'user_id' => $item['user_id'],
                'event' => 'room_expire',
                'data' => serialize([
                    'title' => '族群族长身份失效通知',
                    'content' => "亲，您的族群“{$item['title']}”的族长身份特权过期时间" . date('Y-m-d', $item['expire_time']) . "，过期后将不再享受族长应有的提成，如不想续期，可无视该条通知，如果想续期请点击查看并进行续期"
                ])
            ];
        }

        Db::name('user_dynamic')->insertAll($data);
        Db::name('xinge_task')->insertAll($xinge_task);
    }

    public static function room_system_rand($limit = 3)
    {
        $table = Db::name('challenge_room')->getTable();
        $where = \app\common\model\User::system_user_filter();
        $count = Db::name('challenge_room')->where($where)->count(1);
        $data = [];

        if ($count > 0) {
            if ($count > $limit) {
                $join_sql = $join_rand = [];
                $data_count = 0;

                do {
                    $rand = mt_rand(0, $count - 1);
                    if (in_array($rand, $join_rand)) continue;

                    $join_rand[] = $rand;
                    $join_sql[] = "(SELECT room_id,avator,title,day,btime,etime,income_rate FROM `{$table}` WHERE {$where} LIMIT {$rand},1)";
                    ++$data_count;
                } while ($data_count < $limit);

                $data = Db::query(implode(' UNION ALL ', $join_sql));
            } else {
                $data = Db::name('challenge_room')->where($where)->field('room_id,avator,title,day,btime,etime,income_rate')->select();
            }

//            foreach ($data as $key => $item) {
//                $item['income_rate'] = sprintf('%.3f', $item['income_rate']);
//                $item['challenge_time'] = ChallengeModel::convert_signin_time($item)['stime'];
//
//                if (strpos($item['avator'], 'http://') === false) $item['avator'] = config('site.url') . $item['avator'];
//
//                unset($item['btime']);
//                unset($item['etime']);
//
//                $data[$key] = $item;
//            }
        }

        return $data;
    }

    public static function auto_accept()
    {
        $today_date = Common::today_date();

        $data = Db::name('challenge_both')->where([['launch_status', '=', 0], ['join_date', '=', $today_date]])->order('both_id asc')->limit(50)->column('launch_cid');
        if (count($data) <= 6) return false;

        self::accept(User::system_user_rand(), ['challenge_id' => $data[mt_rand(0, count($data) - 1)], 'use_balance' => true]);

        return true;
    }

    public static function pull_redpack_room($city = '', $url = true, $domain = true)
    {
        $room_id = Db::name('redpack_send')->where([['surplus', '>', 0], ['show', '=', 1]])->column('room_id');

        if (empty($room_id)) {
            // 系统补贴发红包
            $room = Db::name('challenge_room')->where([['mode', '=', 2]])->field('room_id,user_id')->select();
            if (empty($room)) return false;

            $room = $room[mt_rand(0, count($room) - 1)];
            $room_id = $room['room_id'];
            $now_time = request()->time();

            $data = [
                'user_id' => $room['user_id'],
                'room_id' => $room_id,
                'event' => 'mb',
                'price' => 100,
                'num' => 10,
                'surplus' => 10,
                'msg' => '恭喜发财，大吉大利',
                'status' => 1,
                'add_time' => $now_time,
                'show' => 1,
                'receive' => 'room',
                'share' => 0,
                'transfer' => 'account',
                'pic' => ''
            ];

            $redpack_id = Db::name('redpack_send')->insertGetId($data);
            $data['redpack_id'] = $redpack_id;
            Redpack::send_finish($data);
        } else {
            // 官方 - 推荐 - 同城 - 其他
            $map = [['room_id', 'in', $room_id]];
            $where = [['mode', '=', 2]]; // 官方
            $count = Db::name('challenge_room')->where($where)->where($map)->count(1);
            if ($count == 0) { // 推荐
                $time = request()->time();
                $where = [['recommend_time', '>', $time]];
                $count = Db::name('challenge_room')->where($where)->where($map)->count(1);
            }

            if ($count == 0) { // 同城
                if (empty($city)) {
                    $city = Challenge::room_option('city');
                    $city = $city[mt_rand(0, count($city) - 1)];
                }

                $where = [['city', '=', $city], ['expire_time', '>', $time]];
                $count = Db::name('challenge_room')->where($where)->where($map)->count(1);
            }

            if ($count == 0) { // 其他
                $room_id = $room_id[mt_rand(0, count($room_id) - 1)];
            } else {
                $room_id = Db::name('challenge_room')
                    ->where($where)->where($map)
                    ->field('room_id')
                    ->limit(mt_rand(0, $count - 1), 1)
                    ->select()[0]['room_id'];
            }
        }

        if ($url) return url('home/challenge/join', 'room_id=' . $room_id, '', $domain);

        return $room_id;
    }

    public static function auto_room_fee()
    {
        $time = request()->time();
        $rooms = Db::name('challenge_room')->where([
            ['auto', '=', 1],
            ['expire_time', '<', $time + 2 * 86400]
        ])->select();

        foreach ($rooms as $room) {
            $res = self::update_room($room['user_id'], ['room_id' => $room['room_id'], 'room_fee' => 'month']);

//            dump(Common::time_format($room['expire_time']));
//            dump($res);
        }

        return true;
    }
}