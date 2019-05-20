<?php

namespace app\common\model;

use EasyWeChat\Foundation\Application;
use think\Db;
use think\facade\Log;

class Redpack
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

    public static function send($user_id, $option = [], $pay_id = 0)
    {
        if (empty($option['price']) || empty($price = round(floatval($option['price']), 2)) || $price <= 0) {
            return ['', 'param error 1', 1];
        }

        if (empty($option['room_id']) || empty($room_id = intval($option['room_id']))) {
            return ['', 'param error 2', 1];
        }

//        $join_count = Db::name('challenge')
//            ->where([
//                ['user_id', '=', $user_id],
//                ['room_id', '=', $room_id]
//            ])->count(1);
//        if ($join_count <= 0) {
//            $room_user = Db::name('challenge_room')->where([['room_id', '=', $room_id]])->value('user_id');
//            if ($room_user != $user_id) {
//                return ['', '非族员无法发红包', 1];
//            }
//        }

        if (empty($option['event']) || !in_array($event = $option['event'], ['rmb', 'mb'])) {
            $event = 'rmb';
            // return ['', 'param error 3', 1];
        }

        if (empty($option['num']) || ($num = intval($option['num'])) < 1) {
            $num = 1;
        }

        if ($event == 'rmb') {
            $price = intval($price * 100);
        }

        $user = User::find($user_id);
        $paid = $price;

        $use_mb = 0;
        if ($event == 'mb') {
            if ($user['mb'] >= $paid) {
                $use_mb = $paid;
                $paid = 0;
            } else {
                $use_mb = $paid - $user['mb'];
                $paid -= $user['mb'];
            }
        }

        $use_balance = 0;
        if ($paid > 0 && !empty($option['use_balance'])) {
            if ($user['balance'] >= $paid) {
                $use_balance = $paid;
                $paid = 0;
            }
        }

        if ($paid > 0) {
            return Pay::pay($paid, ['attach' => "{$user_id}:{$paid}:Redpack:send", 'openid' => $user['openid']], ['attach_data' => $option]);
        }

        $msg = empty($option['msg']) ? '恭喜发财，大吉大利' : $option['msg'];
        $time = request()->time();

        $data = [
            'user_id' => $user_id,
            'room_id' => $room_id,
            'event' => $event,
            'price' => $price,
            'num' => $num,
            'surplus' => $num,
            'msg' => $msg,
            'status' => 1,
            'add_time' => $time,
            'show' => isset($option['show']) ? $option['show'] : 1,
            'receive' => empty($option['receive']) ? 'room' : $option['receive'],
            'share' => empty($option['share']) ? 0 : 1,
            'transfer' => empty($option['transfer']) ? 'account' : $option['transfer'],
            'pic' => empty($option['pic']) ? '' : $option['pic']
        ];

        $redpack_id = Db::name('redpack_send')->insertGetId($data);
        if (!$redpack_id) return ['', 'error 1', 1];

        $res = true;

        // 抵扣人民币
        if ($use_balance > 0) {
            $res = User::money($user_id, -$use_balance, 'redpack_send', $redpack_id, '发红包');
        }

        // 扣M币
        if ($use_mb > 0 && $res) {
            $res = User::mb($user_id, -$use_mb, 'redpack_send', $redpack_id, '发红包');
        }

        if (!$res) {
            Db::name('redpack_send')->where('redpack_id', '=', $redpack_id)->delete();
            Db::name('redpack_assign')->where('redpack_id', '=', $redpack_id)->delete();
            Log::record('redpack send error,balance_modify fail,redpack_id:' . $redpack_id);
            return ['', 'error 2', 1];
        }

        $data['redpack_id'] = $redpack_id;
        $res = self::send_finish($data);

        if ($res !== true) return $res;

//        $data = ['title' => $msg];
//        if (!empty($option['share'])) $data['share'] = true;
//
//        Db::name('challenge_dynamic')->insert(
//            [
//                'user_id' => $user_id,
//                'nickname' => $user['nickname'],
//                'avator' => $user['avator'],
//                'room_id' => $room_id,
//                'event' => 'redpack',
//                'event_id' => $redpack_id,
//                'data' => serialize($data),
//                'add_time' => $time
//            ]
//        );

//        // 好友动态
//        $user_ids = Db::name('user_friend')->where('user_id', '=', $user_id)->column('friend_uid');
//        if (!empty($user_ids)) {
//            $data = [];
//            foreach ($friend_id as $u) {
//                $data[] = [
//                    'user_id' => $user_id,
//                    'nickname' => $user['nickname'],
//                    'avator' => $user['avator'],
//                    'receive_uid' => $u,
//                    'event' => 'redpack',
//                    'event_id' => $redpack_id,
//                    'data' => serialize(['title' => $msg, 'room_id' => $room_id]),
//                    'add_time' => $time
//                ];
//            }
//
//            Db::name('user_dynamic')->insertAll($data);
//        }

//        // 族长和族员动态
//        $leader_uid = Db::name('challenge_room')->where([['room_id', '=', $room_id]])->value('user_id');
//        $user_ids = Db::name('challenge')->where([['room_id', '=', $room_id]])->column('user_id');
//        if (!empty($leader_uid) && !empty($user_ids) && !in_array($leader_uid, $user_ids)) $user_ids[] = $leader_uid;
//        if (!empty($user_ids)) {
//            $data = $xinge_task = [];
//            foreach ($user_ids as $u) {
//                $data[] = [
//                    'user_id' => $user_id,
//                    'nickname' => $user['nickname'],
//                    'avator' => $user['avator'],
//                    'receive_uid' => $u,
//                    'event' => 'redpack',
//                    'event_id' => $redpack_id,
//                    'data' => serialize(['title' => $msg, 'room_id' => $room_id]),
//                    'add_time' => $time
//                ];
//
//                $xinge_task[] = [
//                    'user_id' => $u,
//                    'event' => 'redpack',
//                    'data' => serialize(['title' => '微选生活红包', 'content' => '发了一个班级红包'])
//                ];
//            }
//
//            Db::name('user_dynamic')->insertAll($data);
//            Db::name('xinge_task')->insertAll($xinge_task);
//        }

        return ['redpack_id' => $redpack_id];
    }


    public static function send_finish($data)
    {
        $redpack_id = $data['redpack_id'];
        $assign = self::allot($data['price'], $data['num']);

        foreach ($assign as $key => $value) {
            $data_save[] = [
                'redpack_id' => $redpack_id,
                'price' => $value,
                'user_id' => 0,
                'draw_time' => 0
            ];
        }

        $res = Db::name('redpack_assign')->insertAll($data_save);
        if (!$res) ['', 'error finish', 1];

        $url = Common::short_url(
            urlencode(
                url('home/challenge/redpack', '', '', true)
                . "?room_id={$data['room_id']}&redpack_id={$redpack_id}"
            )
        );

        Db::name('redpack_send')->where('redpack_id', '=', $redpack_id)
            ->update(['short_url' => str_replace(['http://', 'https://'], '', $url)]);

        return true;
    }

    public static function allot($balance, $num, $ratio = 1, $scale = 1)
    {
        $data = array();
        $balance *= $scale;
        $min = 1;
        $max = round(($balance / $num) * (1 + $ratio), 2);

        for ($i = 0; $i < $num; $i++) {
            if ($balance <= 0) {
                return $data;
            }

            $data[] = $min / $scale;
            $balance -= $min;
        }

        // $balance -= $min * $num;

        $index = 0;
        while ($balance > 0) {
            $rand = rand(0, $max);

            if ($balance >= $rand) { //余额充足
                $data[$index] += ($rand / $scale);
                $balance -= $rand;
            } elseif ($balance > 0) {     //余额不足
                $data[$index] += ($balance / $scale);
                $balance = 0;
            } else {                   //没有余额
                break;
            }

            if ($index == $num - 1) {
                $index = 0;
            } else {
                $index++;
            }
        }

        shuffle($data);

        return $data;
    }

    public static function assign_draw($redpack = 0, $user_id = 0, $assign_id = 0)
    {
        if (empty($redpack)) {
            return ['', 'data empty', 1];
        }

        if (is_numeric($redpack)) {
            $redpack = Db::name('redpack_send')->where('redpack_id', '=', $redpack)->find();
        }

        if (empty($redpack)) {
            return ['', 'data empty', 1];
        }

        if ($redpack['surplus'] <= 0) {
            // 动态处理
            self::draw_dynamic($redpack, $user_id);

            return [['txt' => '很抱歉<br>红包已领完'], '', 101];
        }

        if ($redpack['status'] != 1) {
            return ['', '数据有误', 1];
        }

//        // 超过24小时处理
//        if (NOW_TIME - $redpack['add_time'] >= 86400) { // 24 * 60 * 60
//            $res = $this->rollback($redpack);
//
//            if ($res <= 0) {
//                \Think\Log::record('redpack assign_draw error,redpack_rollback fail,user_wxid:' . $user_wxid . ',redpack_id:' . $redpack['redpack_id'] . ',code:' . $res);
//            }
//
//            return -5;
//        }

        // 已领过
        $data_assign = Db::name('redpack_assign')
            ->where([
                ['redpack_id', '=', $redpack['redpack_id']],
                ['user_id', '=', $user_id],
                ['draw_time', 'gt', 0]
            ])->find();
        if (!empty($data_assign)) {
            // 动态处理
            self::draw_dynamic($redpack, $user_id);

            return [['txt' => '已领过'], '', 103];
        }

        if ($assign_id) { // 再次领取
            if ($redpack['room_id'] && $redpack['receive'] == 'room') {
                if ($redpack['user_id'] != $user_id) {
                    $join_count = Db::name('challenge')
                        ->where([
                            ['user_id', '=', $user_id],
                            ['room_id', '=', $redpack['room_id']]
                        ])->count(1);

//                if ($join_count <= 0) {
//                    $leader_uid = Db::name('challenge_room')->where([['room_id', '=', $redpack['room_id']]])->value('user_id');
//                    if ($leader_uid == $user_id) {
//                        $join_count = 1;
//                    }
//                }

                    if ($join_count <= 0) {
                        return ['', '非族员无法领取该红包', 102];
                    }
                }
            }

            $data_assign = Db::name('redpack_assign')
                ->where([
                    ['assign_id', '=', $assign_id],
                    ['redpack_id', '=', $redpack['redpack_id']],
                    ['draw_time', '=', 0]
                ])->find();

            if (!empty($data_assign) && !empty($data_assign['user_id']) && $data_assign['user_id'] != $user_id) {
                return ['', '数据有误，请重新领取', 107];
            }
        } else {
            // 指定红包
            $data_assign = Db::name('redpack_assign')
                ->where([
                    ['redpack_id', '=', $redpack['redpack_id']],
                    ['user_id', '=', $user_id],
                    ['draw_time', '=', 0]
                ])->find();
            if (empty($data_assign)) { // 随机红包
                $data_assign = Db::name('redpack_assign')
                    ->where([
                        ['redpack_id', '=', $redpack['redpack_id']],
                        ['user_id', '=', 0],
                        ['draw_time', '=', 0]
                    ])->find();
            }

//            if ($redpack['ad_id']) {
//                $ad = Db::name('ad')
//                    ->where([
//                        ['ad_id', '=', $redpack['ad_id']],
//                        ['status', '=', 1]
//                    ])->field('ad_id,ad_pic,forward')->find();
//                if (empty($ad)) {
//                    $data_assign = [];
//                } else {
//                    $ad['ad_link'] = url('home/Ad/read') . '&ad_id=' . $ad['ad_id'] . '&event=redpack';
//
//                    return [
//                        [
//                            'ad' => $ad,
//                            'assign' => [
//                                'assign_id' => $data_assign['assign_id'],
//                                'balance' => $redpack['event'] == 'rmb' ? $data_assign['balance'] / 100 : $data_assign['balance'],
//                                'event' => $redpack['event']
//                            ]
//                        ], '', 105];
//                }
//            }
        }

        $time = request()->time();

        if (empty($data_assign) || ($data_assign['last_draw_time'] > 0 && $data_assign['last_draw_time'] < $time)) {
            // 动态处理
            self::draw_dynamic($redpack, $user_id);

            return [['txt' => '很抱歉<br>红包已领完'], '', 101];
        }

        if (!$assign_id) {
            return [[
                'assign_id' => $data_assign['assign_id'],
                'price' => $data_assign['price'],
                'event' => $redpack['event']
            ], '', 0];
        }

        // 领取 start
        if ($redpack['event'] == 'rmb') {
            if ($redpack['transfer'] == 'account' || $data_assign['price'] < 100) { // 账户余额
                $res = User::money($user_id, $data_assign['price'], 'redpack_draw', $redpack['redpack_id'], '领红包');
                $ret = [
                    'icon' => '/static/img/logo.png',
                    'msg' => '红包已到您的微选生活钱包<br>打开微选生活APP查看账单明细'
                ];
            } else { // 微信转账
                $tencent_config = db('user_withdraw_tencent')->field('app_id, mch_id, key, id, qrcode')->where('status', 1)->find();
                if (empty($tencent_config)) return ['', '红包无法到达您的微信钱包,请咨询客服', 105];

                $openid = db('user_withdraw_tencent_relation')
                    ->where([['user_id', '=', $user_id], ['withdraw_tencent_id', '=', $tencent_config['id']]])
                    ->value('openid');

                if (empty($openid)) return [[
                    'icon' => '/static/img/icon-wechat.png',
                    'pic' => $tencent_config['qrcode'],
                    'msg' => '红包无法到达您的微信钱包<br>请长按下图关注公众号后再来领取'
                ], '', 105];

                $config = config('wechat.');
                $config['app_id'] = $tencent_config['app_id'];
                $config['payment']['merchant_id'] = $tencent_config['mch_id'];
                $config['payment']['key'] = $tencent_config['key'];

                $param = [
                    'partner_trade_no' => Order::order_sn_create(),
                    'check_name' => 'NO_CHECK',
                    'openid' => $openid,
                    'amount' => env('APP_DEBUG') ? 100 : $data_assign['price'],
                    'desc' => '微选生活',
                    'spbill_create_ip' => request()->ip()
                ];

                $app = new Application($config);
                $result = $app->merchant_pay->send($param);

                if ($result->return_code === 'SUCCESS' && $result->result_code === 'SUCCESS') {
                    $ret = [
                        'icon' => '/static/img/icon-wechat.png',
                        'msg' => '红包已通过微选生活VIP公众号<br>发放到微信钱包，注意查收'
                    ];
                } else {
                    // return ['', '转账失败，请咨询客服', 106];
                    $ret = [
                        'icon' => '/static/img/icon-wechat.png',
                        'msg' => '红包无法到达您的微信钱包<br>请咨询客服'
                    ];
                }

                // $ret['res'] = $result->all();
            }
        } else {
            $res = User::mb($user_id, $data_assign['price'], 'redpack_draw', $redpack['redpack_id'], '领红包');
            $ret = [
                'icon' => '/static/img/logo.png',
                'msg' => 'M币已到账，明天自动兑换现金到您的微选生活钱包，随时提现' // '红包已到您的微选生活钱包<br>打开微选生活APP查看账单明细'
            ];
        }

        if (empty($redpack['pic'])) {
            $ret['pic'] = '/static/challenge/img/bg-haoxiguan.png';
            if (Common::isWeixin()) $ret['link'] = 'http://a.app.qq.com/o/simple.jsp?pkgname=com.mayixiguan';
        } else {
            $ret['pic'] = $redpack['pic'];
        }


        $save_data = ['surplus' => ['DEC', 1]];
        if ($redpack['surplus'] == 1) {
            $save_data['draw_time'] = $time - $redpack['add_time'];
        }

        $res = Db::name('redpack_send')->where('redpack_id', '=', $redpack['redpack_id'])->update($save_data);
        if (!$res) return ['', 'draw error 1', 1];

        $res = Db::name('redpack_assign')->where('assign_id', '=', $data_assign['assign_id'])
            ->update(['user_id' => $user_id, 'draw_time' => $time]);
        if (!$res) return ['', 'draw error 2', 1];
        // 领取 end

        // 动态处理
        self::draw_dynamic($redpack, $user_id);

        return [$ret, '', 0];
    }

    // 红包领取动态
    public static function draw_dynamic($redpack, $user_id)
    {
        Db::name('redpack_status')->insert([
            'redpack_id' => $redpack['redpack_id'],
            'user_id' => $user_id,
            'add_time' => request()->time()
        ]);
    }

    // 族长自动发红包
    public static function auto_send_set()
    {
        $data = Db::name('redpack_auto')->where([['auto', '=', 1]])->select();
        if (empty($data)) return false;

//        $user_ids = array_column($data, 'user_id');
//        $user = Db::name('user')->where([['id', 'in', $user_ids]])->column('id,mb');

        foreach ($data as $v) {
            $user = User::find($v['user_id']);
            if ($v['mb'] > $user['mb']) continue;
            $res = self::send($v['user_id'], [
                'room_id' => $v['room_id'],
                'price' => $v['mb'],
                'event' => 'mb',
                'num' => $v['num'],
                'msg' => $v['msg'],
                'share' => $v['share'],
                'receive' => $v['receive'],
                'pic' => $v['pic']
            ]);

            dump($res);
        }
    }

    // 系统自动发红包
    public static function auto_send_default()
    {
        $time = request()->time();
        $rooms = Db::name('challenge_room')->where([['expire_time', '>', $time]])->select();

        foreach ($rooms as $room) {
            $data = [
                'user_id' => $room['user_id'],
                'room_id' => $room['room_id'],
                'event' => 'mb',
                'price' => 100,
                'num' => 10,
                'surplus' => 10,
                'msg' => '恭喜发财，大吉大利',
                'status' => 1,
                'add_time' => $time,
                'show' => 1,
                'receive' => 'room',
                'share' => 0,
                'transfer' => 'account',
                'pic' => ''
            ];

            $redpack_id = Db::name('redpack_send')->insertGetId($data);
            $data['redpack_id'] = $redpack_id;
            self::send_finish($data);
        }
    }
}