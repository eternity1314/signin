<?php

namespace app\common\model;

use think\Db;
use think\facade\Log;

class Activity
{
    public static function pay_finish($data, $pay_id = 0)
    {
        if (!empty($data['attach'])) {
            $user_id = $data['attach'][0];
            $event = $data['attach'][3];
            parse_str($data['attach'][4], $option);
            $option['use_balance'] = true;
            $option['out_trade_no'] = $data['out_trade_no'];

            \think\facade\Env::set('ME', $user_id);
            $res = self::$event($user_id, $option);

            return $res;
        }
    }

    public static function pull_new_system()
    {
        $now_time = request()->time();
        $today_time = Common::today_time();
        if ($today_time + 1 * 60 * 60 < $now_time && $now_time < 7 * 60 * 60) {
            if (mt_rand(1, 100) > 10) {
                return false;
            }
        }

        $monday_time = strtotime('this week Monday', $now_time);
        $sunday_end_time = $monday_time + 7 * 86400 - 1;
        $record = db('activity_pull_new')
            ->where([['edit_time', 'BETWEEN', [$monday_time, $sunday_end_time]]])
            ->order('num desc')
            ->select();

        $record_count = count($record);
        if ($record_count < 40 && !($record_count > 5 && mt_rand(0, 100) > 50)) {
            $user_id = User::system_user_rand();
            if ($record_count > 0) {
                $user_ids = array_column($record, 'user_id');
                while (in_array($user_id, $user_ids)) {
                    $user_id = User::system_user_rand();
                };

                $user_ids[] = $user_id;
            }

            db('activity_pull_new')->insert([
                'user_id' => $user_id,
                'num' => 1,
                'edit_time' => $now_time
            ]);

            return false;
        }

        if ($record_count > 0) {
            $v = $record[mt_rand(0, $record_count - 1)];
            db('activity_pull_new')
                ->where([['id', '=', $v['id']]])
                ->update(['num' => ['INC', mt_rand(1, 3)], 'edit_time' => $now_time]);
        }

        if ($now_time > $monday_time + 6 * 86400) { // 周日
            $surplus = intval(($sunday_end_time - $now_time) / 60 / 10); // 剩余执行次数
            $order_count = db('active_order')->field('count(1) as order_count')
                ->where([
                    ['add_time', 'BETWEEN', [$monday_time, $sunday_end_time]],
                    ['is_partner', '=', 1],
                    ['status', '<>', 3]
                ])
                ->group('invite_user_id')
                ->order('order_count', 'DESC')
                ->limit(1)
                ->find();

            if (empty($order_count)) $order_count = 0;
            else $order_count = $order_count['order_count'];

            if ($order_count > 0) {
                foreach ($record as $v) {
                    if ($v['num'] <= $order_count) {
                        $avg = ceil(($order_count - $v['num'] + 6) / $surplus); // 平均每次增加

                        db('activity_pull_new')
                            ->where([['id', '=', $v['id']]])
                            ->update(['num' => ['INC', $avg], 'edit_time' => $now_time]);
                    }
                }

                // if ($now_time > $today_time + 22 * 60 * 60) {}
            }
        }
    }

    public static function pull_new_finish()
    {
        $now_time = request()->time();
        if (date('w', $now_time) != 1) return false;

        $time_monday = strtotime('this week Monday', $now_time);
        $time_limit = [$time_monday - 7 * 86400, $time_monday - 1];
        $order_count = db('active_order')->field('count(1) as order_count')
            ->where([
                ['add_time', 'BETWEEN', $time_limit],
                ['is_partner', '=', 1],
                ['status', '<>', 3]
            ])
            ->group('invite_user_id')
            ->order('order_count', 'DESC')
            ->limit(1)
            ->find();

        if (empty($order_count)) $order_count = 0;
        else $order_count = $order_count['order_count'];

        if ($order_count <= 0) return false;

        $record = db('activity_pull_new')
            ->where([['edit_time', 'BETWEEN', $time_limit]])
            ->order('num desc')
            ->select();

        foreach ($record as $v) {
            if ($v['num'] <= $order_count) {
                db('activity_pull_new')
                    ->where([['id', '=', $v['id']]])
                    ->update(['num' => $order_count + mt_rand(2, 6)]);
            }
        }

        // 不足40个名额补够
        $record_count = count($record);
        if ($record_count < 40) {
            $surplus = 40 - $record_count;
            $user_ids = array_column($record, 'user_id');

            $data = [];
            for ($i = 0; $i < $surplus; $i++) {
                do {
                    $user_id = User::system_user_rand();
                } while (in_array($user_id, $user_ids));

                $user_ids[] = $user_id;

                $data[] = [
                    'user_id' => $user_id,
                    'num' => $order_count + mt_rand(2, 6),
                    'edit_time' => $time_limit[1]
                ];
            }

            db('activity_pull_new')->insertAll($data);
        }
    }

    public static function pick_new_receive($user_id)
    {
        $price = db('activity_pick_new')
            ->where([['from_uid', '=', $user_id]])
            ->sum('open_price');

        if (empty($price)) {
            $time = request()->time();
            $price = 900;

            db('activity_pick_new')->insert([
                'user_id' => $user_id,
                'from_uid' => $user_id,
                'status' => 0,
                'add_time' => $time,
                'open_time' => $time,
                'open_price' => $price
            ]);
        }

        return $price;
    }

    public static function pick_new_withdraw_rand()
    {
        $user_ids = User::system_user_rand(20);
        $users = db('user')->where([['id', 'in', $user_ids]])->field('nickname,avator')->select();
        foreach ($users as $k => $v) {
            $users[$k]['price'] = mt_rand(10, 30);
            // $users[$k]['avator'] = '/static/img/head.png';
        }

        return $users;
    }

    public static function pick_new_open_record($user_id)
    {
        $data = db('activity_pick_new')
            ->where([['from_uid', '=', $user_id], ['open_price', '>', 0]])
            ->field('user_id,open_price')
            ->order('open_time asc')
            ->select();

        if ($data) {
            $users = db('user')
                ->where([['id', 'in', array_column($data, 'user_id')]])
                ->column('id,nickname,avator');

            foreach ($data as $k => $v) {
                $u = $users[$v['user_id']];
                $v['nickname'] = $u['nickname'];
                $v['avator'] = $u['avator'];
                unset($v['user_id']);

                $data[$k] = $v;
            }
        }

        return $data;
    }

    public static function pick_new_cash_new($user_id)
    {
        $time = request()->time();

        $data = db('active')->where([
            ['name', '=', 'upgrade_cash'],
            ['begin_time', '<=', $time],
            ['end_time', '>', $time]
        ])->find();

        if (empty($data)) return false; // 活动结束

        $count = db('activity_pick_new_cash')
            ->where([['user_id', '=', $user_id]])
            ->count(1);

        if ($count == 0) {
            $level = db('user_level')
                ->where([
                    ['user_id', '=', $user_id],
                    ['expier_time', '>', $time]
                ])
                ->find();

            if (empty($level) || $level['level'] < 3) {
                db('activity_pick_new_cash')->insert([
                    'user_id' => $user_id,
                    'price' => mt_rand(20, 80) * 100,
                    'add_time' => $time,
                    'time_begin' => $time,
                    'time_end' => $time + 7200,
                    'status' => 0
                ]);
            }
        }
    }

    public static function pick_new_cash_auto()
    {
        $time = request()->time();

        $data = db('active')->where([
            ['name', '=', 'upgrade_cash'],
            ['begin_time', '<=', $time],
            ['end_time', '>', $time]
        ])->find();

        if (empty($data)) return false; // 活动结束

        $data = db('activity_pick_new_cash')
            ->where([['add_time', '>=', $time - 86400]])// 超过1天不生成现金
            ->group('user_id')
            ->having('MAX(time_begin) <= ' . ($time - 23400))// 超过7小时//7 * 3600
            ->field('user_id,MAX(time_begin) AS time_begin')
            ->order('add_time asc')
            ->select();

        if (empty($data)) return false;

        $user_ids = array_column($data, 'user_id');
        $levels = db('user_level')
            ->where([
                ['user_id', 'in', $user_ids],
                ['expier_time', '>', $time]
            ])
            ->column('user_id,level');

        $save = $dynamic = $xinge_task = [];
        foreach ($data as $v) {
            if (!empty($levels[$v['user_id']]) && $levels[$v['user_id']] >= 3) continue;

            $price = mt_rand(20, 80);
            $time_begin = $v['time_begin'] + mt_rand(23400, 27000);
            $save[] = [
                'user_id' => $v['user_id'],
                'price' => $price * 100,
                'add_time' => $time,
                'time_begin' => $time_begin,
                'time_end' => $time_begin + 7200,
                'status' => 0
            ];

            // 通知
            $content = serialize([
                'title' => '亲，你又有' . $price . '元现金待领取哦~',
                'content' => '领取后可随时提现到微信/支付宝，活动期间每天都可领现金，别再错过了哦~'
            ]);

            $dynamic[] = [
                'user_id' => 0,
                'nickname' => '系统通知',
                'avator' => ' /static/img/logo.png',
                'receive_uid' => $v['user_id'],
                'event' => 'pick_new_cash',
                'event_id' => 0,
                'data' => $content,
                'add_time' => $time
            ];

            $xinge_task[] = [
                'user_id' => $v['user_id'],
                'event' => 'pick_new_cash',
                'data' => $content
            ];
        }

        if ($save) {
            db('activity_pick_new_cash')->insertAll($save);
            db('user_dynamic')->insertAll($dynamic);
            db('xinge_task')->insertAll($xinge_task);

            $today_time = Common::today_time();
            if ($today_time + 28800 < $time && $time < $today_time + 79200) { // 指定时间(08:00~22:00)防骚扰
                $logins = db('app_token')
                    ->where([['user_id', 'in', $user_ids]])
                    ->column('user_id,access_time');

                $remind_time_min = db('activity_pick_new_cash_remind')
                    ->where([['user_id', 'in', $user_ids], ['add_time', '<', $time - 86400 * 7]])// ['type', '=', 1]
                    ->group('user_id')
                    ->column('user_id,MIN(add_time) AS add_time');

                $remind_time_max = db('activity_pick_new_cash_remind')
                    ->where([['user_id', 'in', $user_ids], ['add_time', '>=', $today_time]])// ['type', '=', 1]
                    ->group('user_id')
                    ->column('user_id,MAX(add_time) AS add_time');

                $users = db('user')
                    ->where([['id', 'in', $user_ids]])
                    ->column('id,mobile');

                $realnames = db('user_withdraw')
                    ->where([['user_id', 'in', $user_ids]])
                    ->column('user_id,real_name');

                $sms = new \dysms();

                foreach ($save as $v) {
                    if (!empty($users[$v['user_id']]) && ($mobile = $users[$v['user_id']]) && strlen($mobile) == 11) { // 验证手机号
                        if (empty($logins) || $time - $logins[$v['user_id']] > 86400 * 2) { // 超过2天没登录
                            if (empty($remind_time_min[$v['user_id']]) || $remind_time_min[$v['user_id']] >= $time - 86400 * 7) { // 提醒不超过7天
                                if (empty($remind_time_max[$v['user_id']]) || $remind_time_max[$v['user_id']] < $today_time) { // 1天1条
                                    db('activity_pick_new_cash_remind')->insert([
                                        'user_id' => $v['user_id'],
                                        'add_time' => $time,
                                        'type' => 1
                                    ]);

//                                    $res = $sms->send([
//                                        'TemplateCode' => 'SMS_152700015',
//                                        'TemplateParam' => ['price' => round($v['price'] / 100)],
//                                        'PhoneNumbers' => $mobile
//                                    ]);

                                    $res = $sms->send([
                                        'TemplateCode' => 'SMS_153325849',
                                        'TemplateParam' => [
                                            'price' => round($v['price'] / 100),
                                            'nickname' => empty($realnames[$v['user_id']]) ? '亲' : $realnames[$v['user_id']]
                                        ],
                                        'PhoneNumbers' => $mobile
                                    ]);

                                    if (!empty($res['Code']) || $res['Code'] != 'OK') {
                                        Log::record('dysms send fail,' . json_encode($res, JSON_UNESCAPED_UNICODE));
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public static function pick_new_cash_receive($user_id)
    {
        $time = request()->time();

        $active = db('active')->where([
            ['name', '=', 'upgrade_cash'],
            ['begin_time', '<=', $time],
            ['end_time', '>', $time]
        ])->find();

        if (empty($active)) return false; // 活动结束

        $cash = db('activity_pick_new_cash')
            ->where([
                ['user_id', '=', $user_id],
                ['time_begin', '<=', $time],
                ['time_end', '>', $time],
            ])
            ->order('time_begin asc')
            ->find();

        if (empty($cash)) return false; // 现金过期
        if ($cash['status'] != 0) return false; // 已生成

        db('activity_pick_new_cash')
            ->where([['id', '=', $cash['id']]])
            ->update(['status' => 1]);

        $data = [];
        $i = 1;
        $today_time = Common::today_time();
        $time_limit = 7200;
        do {
            $time_handle = $today_time + ($i * 86400);
            $price = intval((10000 - $cash['price']) / pow(2, $i));

            if ($price < 1 || $time_handle >= $active['end_time']) {
                if ($data) {
                    db('activity_pick_new_cash')->insertAll($data);
                }

                break;
            }

            $time_begin = $time_handle + mt_rand(0, 86400 - $time_limit);

            $data[] = [
                'user_id' => $user_id,
                'price' => $price,
                'add_time' => $time,
                'time_begin' => $time_begin,
                'time_end' => $time_begin + $time_limit,
                'status' => 1
            ];

            $i++;
        } while ($price >= 1);
    }

    public static function pick_new_cash_open($user_id)
    {
        $time = request()->time();

        $cash = db('activity_pick_new_cash')
            ->where([
                ['user_id', '=', $user_id],
                ['time_begin', '<=', $time],
                ['time_end', '>', $time]
            ])
            ->order('time_begin asc')
            ->find();

        if (empty($cash)) {
            return ['', '没有红包了', 101];
        }

        if ($cash['status'] == 0) {
            return ['', '请升级', 102];
        }

        if ($cash['status'] == 2) {
            return ['', '已领过', 103];
        }

        $count = db('user_money')->where([
            ['event', '=', 'pick_new_cash'],
            ['event_id', '=', $cash['id']]
        ])->count(1);
        if ($count > 0) {
            return ['', '已领过', 103];
        }

        User::money($user_id, $cash['price'], 'pick_new_cash', $cash['id'], '天天领红包');

        db('activity_pick_new_cash')
            ->where([['id', '=', $cash['id']]])
            ->update(['status' => 2]);

        return ['', '领取成功，已到账余额', 0];
    }

    /**
     * @param $user_id
     * @param bool $is_new ,true:确定领取;
     * @return array
     */
    public static function pick_new_bag($user_id, $is_new = false)
    {
        $time = request()->time();

        // end_time,0:已领;1:临时;
        $data = db('activity_pick_new_bag')
            ->where([['user_id', '=', $user_id], ['end_time', '<=', 1]])
            ->find();

        if (empty($data)) {
            $range = [8, 12];
            $price = mt_rand($range[0], $range[1]) * 100;
            $num = ceil($price / 1.5 / 100);

            $price_other = [];
            $price_open = 0;

            $price_me = round($price * mt_rand(6666, 8000) / 10000);
            $price_open += $price_me;

            // $price_1st = round(($price - $price_me) * mt_rand(5000, 6666) / 10000);
            $price_1st = round(($price - $price_me) * mt_rand(2500, 3333) / 10000);
            $price_open += $price_1st;
            $price_other[] = $price_1st;

            while ($price_open < $price) {
                $price_tmp = round(
                    ($price - $price_me - $price_1st)
                    * ($num > 2 ? mt_rand(intval(1 / ($num + 2) * 10000), intval(1 / ($num - 2) * 10000)) : intval(1 / ($num + 2) * 10000))
                    / 10000
                );

                $price_open += $price_tmp;
                $price_other[] = $price_tmp;
            }

            // $price_old = round($price_other * mt_rand(intval(1 / ($price / 100) * 10000), intval(1 / $range[0] * 10000)) / 10000);

            $data = [
                'user_id' => $user_id,
                'price' => $price,
                'num' => $num,
                'price_me' => $price_me,
                //'price_1st' => $price_1st,
                'price_other' => join('|', $price_other),
                //'price_old' => $price_old,
                'add_time' => $time,
                'end_time' => $is_new ? 0 : 1
            ];

            $data['id'] = db('activity_pick_new_bag')->insertGetId($data);

            if ($is_new && $data['id']) {
                db('activity_pick_new_bag_open')->insert([
                    'user_id' => $user_id,
                    'from_uid' => $user_id,
                    'bag_id' => $data['id'],
                    'add_time' => $time,
                    'open_time' => $time,
                    'open_price' => $price_me,
                    'status' => 0
                ]);
            }
        } elseif ($is_new && $data['end_time'] == 1) { // 确定领取,临时转为已领进入新一轮帮拆
            $data['end_time'] = 0;

            db('activity_pick_new_bag')
                ->where([['id', '=', $data['id']]])
                ->update(['end_time' => 0]);

            db('activity_pick_new_bag_open')->insert([
                'user_id' => $user_id,
                'from_uid' => $user_id,
                'bag_id' => $data['id'],
                'add_time' => $time,
                'open_time' => $time,
                'open_price' => $data['price_me'],
                'status' => 0
            ]);
        }

        return $data;
    }

    public static function pick_new_bag_open($user_id, $from_uid)
    {
        if ($from_uid <= 0) return ['', 'param missing', 1];
        if ($from_uid == $user_id) return ['', 'can not open it on yourself', 1];

        $bag = db('activity_pick_new_bag')
            ->where([['user_id', '=', $from_uid], ['end_time', '=', 0]])
            ->find();

        if (empty($bag)) return ['', 'user not join in', 1];

        $res = db('activity_pick_new_bag_open')->where([
            ['user_id', '=', $user_id],
            //['from_uid', '=', $from_uid],
            ['bag_id', '=', $bag['id']],
            ['open_price', '=', 0],
            ['status', '>', 0]
        ])->order('status asc,add_time desc')->select();

        if (empty($res)) { // 没帮拆
            return ['', '', 1]; // you not play
        } elseif (count($res) > 1) { // 下次不用拆
            db('activity_pick_new_bag_open')
                ->where([['user_id', '=', $user_id]])
                ->update(['status' => 0]);
        }

        $data = [];
        $today_time = Common::today_time();

        $count = db('activity_pick_new_bag_open')->where([
            ['user_id', '=', $user_id],
            ['user_id', '<>', db()->raw('from_uid')],
            ['open_price', '>', 0],
            ['open_time', 'between', [$today_time, $today_time + 86400 - 1]]
        ])->count(1);

        if ($count >= mt_rand(5, 8)) {
            $data['msg'] = '今天帮拆好多次啦，明天再来吧';
        } else {
            $count = db('activity_pick_new_bag_open')->where([
                ['user_id', '=', $user_id],
                ['from_uid', '=', $from_uid],
                ['open_price', '>', 0],
                ['open_time', 'between', [$today_time, $today_time + 86400 - 1]]
            ])->count(1);
            if ($count > 0) $data['msg'] = '你已经帮我拆过了';
        }

        if (!empty($data['msg'])) {
            db('activity_pick_new_bag_open')
                ->where([['user_id', '=', $user_id]])
                ->update(['status' => 0]);

            return [$data];
        }

        $res = $res[0];
        $price_other = explode('|', $bag['price_other']);
        $price_min = min($price_other);

        $count = db('activity_pick_new_bag_open')
            ->where([
                ['bag_id', '=', $bag['id']],
                ['open_price', '>=', $price_min]
            ])
            ->count(1);

        if ($count <= 1) {
            $open_price = $price_other[0];
        } else {
            $price = $bag['price'];
            $price_me = $bag['price_me'];
            $price_1st = $price_other[0];
            $num = $bag['num'];

            $open_price = round(
                ($price - $price_me - $price_1st)
                * ($num > 2 ? mt_rand(intval(1 / ($num + 2) * 10000), intval(1 / ($num - 2) * 10000)) : intval(1 / ($num + 2) * 10000))
                / 10000 * 0.1
            );
        }

        $time = request()->time();
        $save = [
            'open_time' => $time,
            'open_price' => $open_price,
            'status' => 0
        ];
        if ($open_price >= $price_min) $save['is_award'] = 0; // 第一个不奖励

        db('activity_pick_new_bag_open')->where([['id', '=', $res['id']]])->update($save);

        if (!empty($bag) && $bag['end_time'] == 0) { // 进行中
            $count = db('activity_pick_new_bag_account')->where([['bag_id', '=', $bag['id']]])->count(1);
            if ($count == 0) { // 未进账
                $total_price = db('activity_pick_new_bag_open')
                    ->where([['bag_id', '=', $bag['id']]])
                    ->value('SUM(open_price)');

                if ($total_price >= $bag['price']) { // 进账
                    db('activity_pick_new_bag_account')->insert([
                        'user_id' => $bag['user_id'],
                        'bag_id' => $bag['id'],
                        'price' => $bag['price'],
                        'status' => 0,
                        'add_time' => request()->time()
                    ]);
                }
            }
        }

        $user = User::find($user_id);
        $content = serialize([
            'title' => '现金到账通知',
            'content' => $user['nickname'] . '帮你拆开了一份现金红包，仅差'
                . ($bag['price'] > $total_price ? sprintf('%.2f', ($bag['price'] - $total_price) / 100) : '0')
                . '元可提现，新用户能帮拆10倍以上金额哦~'
        ]);

        db('user_dynamic')->insert([
            'user_id' => 0,
            'nickname' => '微选生活',
            'avator' => '/static/img/logo.png',
            'receive_uid' => $from_uid,
            'event' => 'pick_new_bag_open',
            'event_id' => $res['id'],
            'data' => $content,
            'add_time' => $time
        ]);

        db('xinge_task')->insert([
            'user_id' => $from_uid,
            'event' => 'pick_new_bag_open',
            'data' => $content
        ]);

        $data['open_price'] = sprintf('%.2f', $open_price / 100);
        $data['total_price'] = sprintf('%.2f', $total_price / 100);
        return [$data];
    }

    public static function pick_new_bag_award($user_id)
    {
        $res = db('activity_pick_new_bag_open')
            ->where([
                ['user_id', '=', $user_id],
                ['is_award', '=', 1],
                ['open_price', '>', 0]
            ])
            ->order('open_time asc')
            ->find();

        if ($res && $res['open_price'] > 0 && $res['open_price'] < 100) {
            db('activity_pick_new_bag_open')
                ->where([['id', '=', $res['id']]])
                ->update(['open_price' => db()->raw('open_price * ' . mt_rand(15, 20)), 'is_award' => 2]);

            $bag = db('activity_pick_new_bag')->where([['id', '=', $res['bag_id']]])->find();
            if (!empty($bag) && $bag['end_time'] == 0) { // 进行中
                $count = db('activity_pick_new_bag_account')->where([['bag_id', '=', $bag['id']]])->count(1);
                if ($count == 0) { // 未进账
                    $total_price = db('activity_pick_new_bag_open')
                        ->where([['bag_id', '=', $bag['id']]])
                        ->value('SUM(open_price)');

                    if ($total_price >= $bag['price']) { // 进账
                        db('activity_pick_new_bag_account')->insert([
                            'user_id' => $bag['user_id'],
                            'bag_id' => $bag['id'],
                            'price' => $bag['price'],
                            'status' => 0,
                            'add_time' => request()->time()
                        ]);
                    }
                }
            }

            return true;
        }

        return false;
    }

    public static function pick_new_bag_open_record($user_id, $bag_id = 0)
    {
        if ($bag_id) $where = ['bag_id', '=', $bag_id];
        else $where = ['from_uid', '=', $user_id];

        $data = db('activity_pick_new_bag_open')
            ->where([$where, ['open_price', '>', 0]])
            ->field('user_id,open_price')
            ->order('open_time asc')
            ->select();

        if ($data) {
            $users = db('user')
                ->where([['id', 'in', array_column($data, 'user_id')]])
                ->column('id,nickname,avator');

            foreach ($data as $k => $v) {
                $u = $users[$v['user_id']];
                $v['nickname'] = $u['nickname'];
                $v['avator'] = $u['avator'];
                unset($v['user_id']);

                $data[$k] = $v;
            }
        }

        return $data;
    }

    public static function pick_new_bag_open_price($user_id, $bag_id = 0)
    {
        if ($bag_id) $where = ['bag_id', '=', $bag_id];
        else $where = ['from_uid', '=', $user_id];

        $open_price = db('activity_pick_new_bag_open')->where([$where])->sum('open_price');
        if (empty($open_price)) return 0;

        return $open_price;
    }

    public static function return688_task_data($key = -1)
    {
        $task = [
            [
                'title' => '拼多多或京东消费满500元',
                'desc' => '返8元现金',
                'event' => 'consume500',
                'price' => '8',
                'status' => '0',
                'is_complete' => '0',
                'limit' => '500'
            ],
            [
                'title' => '拼多多或京东消费满1000元',
                'desc' => '返20元现金',
                'event' => 'consume1000',
                'price' => '20',
                'status' => '0',
                'is_complete' => '0',
                'limit' => '1000'
            ],
            [
                'title' => '直推VIP人数达到10人，赚1200元',
                'desc' => '再返180元现金',
                'event' => 'subvip10',
                'price' => '180',
                'status' => '0',
                'is_complete' => '0',
                'limit' => '10'
            ],
            [
                'title' => '直推VIP人数达到20人，赚2400元',
                'desc' => '再返480元现金',
                'event' => 'subvip20',
                'price' => '480',
                'status' => '0',
                'is_complete' => '0',
                'limit' => '20'
            ],
            [
                'title' => '微选商学院投稿成功',
                'desc' => '返50元现金',
                'event' => 'contribute_success',
                'price' => '50',
                'status' => '0',
                'is_complete' => '0',
                'limit' => '100'
            ]
        ];

        if ($key != -1 && isset($task[$key])) {
            return $task[$key];
        } else {
            return $task;
        }
    }

    public static function point_one_buy_join($user_id, $data)
    {
        $rs = db('activity_point_one_join')->where([
            ['user_id', '=', $user_id],
            ['ag_id', '=', $data['ag_id']],
            ['status', '=', 0]
        ])->find();
        if ($rs) {
            return [[], '已抢购过该商品', 40000];
        }

        $need_pay = 10;
        if (!$need_pay) {
            return [[], '支付金额有误', 40000];
        }

        $user = User::find($user_id);
        $paid = $need_pay;
        if (isset($data['use_balance']) && $data['use_balance']) {
            $money = $user['balance'];

            if ($money >= $need_pay) {
                $paid = 0;
            } else {
                $paid = $need_pay - $money;
            }
        }

        if ($paid > 0) {
            // 业务参数
            $option = [
                'ag_id' => $data['ag_id'],
                'pay_method' => $data['pay_method'],
            ];

            $res = Pay::pay($paid, ['attach' => "{$user_id}:{$paid}:Activity:point_one_buy_join", 'openid' => $user['openid']], ['attach_data' => $option]);
            return $res;
        }

        Db::startTrans();

        try {
            // 扣除余额
            User::money($user_id, -$need_pay, 'po_one_buy', $data['ag_id'], '抢购商品');

            // 商品信息
            $goods_data = db('actvity_goods')->field('goods_id, goods_name, goods_img, price, coupon_price, snap_up_count')->where('id', '=', $data['ag_id'])->find();

            // 计算抢购成功率
            $rs = db('activity_point_one_join')->where('user_id', '=', $user_id)->count();
            if ($rs > 0) {
                // 非第一次参与活动
                $success_rate = round(mt_rand(1500, 2500) / 100, 2);
            } else {
                // 第一次参与活动
                $success_rate = round(mt_rand(5000, 6000) / 100, 2);
            }

            // 添加用户参与记录
            $join_id = db('activity_point_one_join')->insertGetId([
                'user_id' => $user_id,
                'ag_id' => $data['ag_id'],
                'success_rate' => $success_rate,
                'add_time' => request()->time(),
                'status' => 0,
                'goods_id' => $goods_data['goods_id'],
                'goods_name' => $goods_data['goods_name'],
                'price' => $goods_data['price'],
                'coupon_price' => $goods_data['coupon_price'],
                'goods_img' => $goods_data['goods_img'],
                'snap_up_count' => $goods_data['snap_up_count'],
                'first_success_rate' => $success_rate
            ]);
        } catch (\Exception $e) {
            Log::record('level_promote add error, uid-'. $user_id);
            Db::rollback();
            return [[], '处理失败', 50001];
        }
        Db::commit();

        return [['join_id' => $join_id], '操作成功', 0];
    }

    public static function point_one_buy_help_rule($user_id, $po_join_id)
    {
        $po_join_data = db('activity_point_one_join')->where('id', '=', $po_join_id)->find();

        if ($po_join_data['user_id'] == $user_id) {
            return [[], '', 40001];
        }

        // 助力规则判断
        if ($po_join_data['status'] != 0) {
            return [['po_join_data' => $po_join_data], 'TA已经抢购了', 40000];
        }

        $todaytime = Common::today_time();
        $rs = db('activity_point_one_help')->where([
            ['from_user_id', '=', $user_id],
            ['to_user_id', '=', $po_join_data['user_id']],
            ['add_time', 'between', [$todaytime, $todaytime + 86400]]
        ])->count(1);
        if ($rs) {
            return [['po_join_data' => $po_join_data], '今天已帮TA助攻过，明天再来', 40000];
        }

        $rs = db('activity_point_one_help')->where([
            ['from_user_id', '=', $user_id],
            ['add_time', 'between', [$todaytime, $todaytime + 86400]]
        ])->count(1);
        if ($rs > mt_rand(4, 7)) {
            return [['po_join_data' => $po_join_data], '今天的助攻次数已用完，明天再来吧', 40000];
        }

        return [['po_join_data' => $po_join_data], 'success', 0];
    }

    public static function point_one_buy_help($user_id, $po_join_id)
    {
        // 助力规则
        $rs = self::point_one_buy_help_rule($user_id, $po_join_id);
        if ($rs[2] !== 0) {
            return $rs;
        }
        $po_join_data = $rs[0]['po_join_data'];

        $is_award = 0;
        if (session('user.is_new_user')) { // 新用户
            $count = db('activity_point_one_help')
                ->where([['from_user_id', '=', $user_id]])
                ->count(1);

            if ($count == 0) {
                $is_award = 1;
            }
        }

        // 计算提升的成功率  b=[(1/(R+1)~1/(R-2)] * （80%-a）*（1/5）;  b=[(1/(R+2)~1/(R-1)] * （80%-a）*（1/20）;
        $invite_user_count = ceil($po_join_data['price'] / 200);
        if ($po_join_data['success_rate'] <= 65) {
            $up_rate = mt_rand(intval(1 / ($invite_user_count + 1) * 10000), intval(1 / ($invite_user_count - 2) * 10000)) * ( 80 - $po_join_data['first_success_rate'] ) / 10000 * 0.2;
        } else {
            $up_rate = mt_rand(intval(1 / ($invite_user_count + 2) * 10000), intval(1 / ($invite_user_count - 1) * 10000)) * ( 80 - $po_join_data['first_success_rate'] ) / 10000 * 0.05;
        }
        $up_rate = round($up_rate, 2);

        try {
            // 添加助力记录
            $insert_data = [
                'po_join_id' => $po_join_id,
                'from_user_id' => $user_id,
                'to_user_id' => $po_join_data['user_id'],
                'up_rate' => $up_rate,
                'add_time' => request()->time(),
                'is_award' => $is_award
            ];
            db('activity_point_one_help')->insert($insert_data);

            // 增加成功率
            $success_rate = $po_join_data['success_rate'] + $up_rate > 100 ? 100 : $po_join_data['success_rate'] + $up_rate;
            db('activity_point_one_join')->where('id', '=', $po_join_id)->update([
                'success_rate' => $success_rate
            ]);

            // 邀请人数 +1
            if ($is_award == 0) { // 新用户
                $insert_data = [
                    'invite_old_user_total' => ['INC', 1],
                    'invite_old_user' => ['INC', 1]
                ];

                $count = db('activity_invite_user')->where('user_id', '=', $po_join_data['user_id'])->count(1);
                if ($count) {
                    // 修改
                    db('activity_invite_user')->where('user_id', '=', $po_join_data['user_id'])->update($insert_data);
                } else {
                    $insert_data['user_id'] = $po_join_data['user_id'];
                    // 新增
                    db('activity_invite_user')->insert($insert_data);
                }
            }

        } catch (\Exception $e) {
            Log::record('point_one_buy_help add error, uid-'. $user_id . '--po_join_id-' . $po_join_id);
            Db::rollback();
            return [[], '处理失败', 50001];
        }
        Db::commit();

        return [['up_rate' => $up_rate, 'success_rate' => $success_rate, 'is_award' => $is_award], '助力成功', 0];
    }

    public static function point_one_buy_act($user_id, $po_join_id)
    {
        $po_join_data = db('activity_point_one_join')->field('user_id, success_rate, price, status, is_share')->where('id', '=', $po_join_id)->find();
        if (!$po_join_data) {
            return [[], '参数错误', 40000];
        }
        if ($po_join_data['user_id'] != $user_id || $po_join_data['status'] != 0 || $po_join_data['is_share'] == 0) {
            return [[], '参数错误', 40001];
        }

        $is_buy_success = 0;
        // 抢购成功情况1
        if ($po_join_data['success_rate'] >= 80) {
            $is_buy_success = 1;
        }

        // 抢购成功情况2
        if ($is_buy_success == 0) {
            $old_new_user_percent = mt_rand(10, 15); // 一个新用户 = n个老用户
            $invite_user_count = ceil($po_join_data['price'] / 200); // 需邀请的用户数
            $user_invite_data = db('activity_invite_user')->field('invite_new_user, invite_old_user')->where('user_id', '=', $user_id)->find(); // 已邀请用户信息

            // 减去抢购进行中邀请的用户数
            if ($user_invite_data) {
                $joining_user = db()->query('select sum(CASE WHEN a.is_award = 0 THEN 1 ELSE 0 END) as new_user_count, sum(CASE WHEN a.is_award != 0 THEN 1 ELSE 0 END) as old_user_count from ( select from_user_id, is_award from sl_activity_point_one_help where po_join_id in (select id from sl_activity_point_one_join where user_id = '. $user_id .' and `status` = 0) GROUP BY from_user_id ) as a')[0];
                $user_invite_data['invite_new_user'] -= $joining_user['new_user_count'];
                $user_invite_data['invite_old_user'] -= $joining_user['old_user_count'];
            }

            if ($user_invite_data && $user_invite_data['invite_new_user'] > 0 && ( $invite_user_count - $user_invite_data['invite_new_user'] ) * $old_new_user_percent < $user_invite_data['invite_old_user']) {
                // 条件 1 必须有邀请过新用户 2 邀请新用户数 + 邀请老用户数 / (10 ~ 15) > 需邀请的用户数
                $is_buy_success = 1;
            }
        }

        // 抢购成功清空邀请用户数
        if ($is_buy_success == 1) {
            $rs = db('activity_invite_user')->where('user_id', '=', $user_id)->count(1);
            if ($rs) {
                db('activity_invite_user')->where('user_id', '=', $user_id)->update([
                    'invite_new_user' => 0,
                    'invite_old_user' => 0
                ]);
            }
        }

        if ($is_buy_success == 1) {
            $status = 2;
        } else {
            $status = 1;

            User::money($user_id, 10, 'po_one_buy_return', $po_join_id, '抢购失败退款');
        }
        db('activity_point_one_join')->where('id', '=', $po_join_id)->update([
            'status' => $status
        ]);

        return [['is_buy_success' => $is_buy_success], 'success', 0];
    }

    public static function point_one_buy_return_cash($user_id, $po_join_id)
    {
        $po_join_data = db('activity_point_one_join')->field('user_id, success_rate, price, status, is_share')->where('id', '=', $po_join_id)->find();
        if (!$po_join_data) {
            return [[], '参数错误', 40000];
        }
        if ($po_join_data['user_id'] != $user_id || $po_join_data['status'] != 2) {
            return [[], '参数错误', 40001];
        }

        Db::startTrans();
        try {
            User::money($po_join_data['user_id'], round($po_join_data['price'] * 0.7), 'po_one_buy_cash', $po_join_id, '抢购成功兑现');

            db('activity_point_one_join')->where('id', '=', $po_join_id)->update([
                'status' => 4
            ]);

        } catch (\Exception $e) {
            Log::record('point_one_buy_return_cash add error, uid-'. $user_id . '--po_join_id-' . $po_join_id);
            Db::rollback();
            return [[], '处理失败', 50001];
        }
        Db::commit();

        return [[], '商品兑现成功，已到账余额', 0];
    }

    public static function point_one_buy_outright_purchase($user_id, $goods_id, $order_sn)
    {
        $po_join = db('activity_point_one_join')->field('id')->where([
            ['user_id', '=', $user_id],
            ['goods_id', '=', $goods_id],
            ['status', '=', 2]
        ])->find();
        if ($po_join) {
            db('activity_point_one_join')->where('id', '=', $po_join['id'])->update([
                'status' => 3,
                'order_no' => $order_sn
            ]);
        }

        return [[], 'success', 0];
    }

    public static function point_one_buy_arrival_return_cash($order_sn)
    {
        $po_join = db('activity_point_one_join')->where([
            ['order_no', '=', $order_sn],
            ['status', '=', 3]
        ])->find();

        Db::startTrans();
        try {
            User::money($po_join['user_id'], $po_join['price'], 'po_one_buy_arrival_cash', $po_join['id'], '抢购成功返款');

            db('activity_point_one_join')->where('id', '=', $po_join['id'])->update([
                'status' => 5
            ]);

        } catch (\Exception $e) {
            Log::record('point_one_buy_arrival_return_cash add error, uid-' . $po_join['user_id'] . '--po_join_id-' . $po_join['id']);
            Db::rollback();
            return [[], '处理失败', 50001];
        }
        Db::commit();

        return [[], 'success', 0];
    }

    public static function po_buy_one_award($user_id)
    {
        $po_help = db('activity_point_one_help')->field('id, po_join_id, up_rate')->where([
            'from_user_id' => $user_id,
            'is_award' => 1
        ])->find();

        if ($po_help) {
            $po_join_data = db('activity_point_one_join')->field('user_id, success_rate, status')->where([
                ['id', '=', $po_help['po_join_id']],
            ])->find();


            if ($po_join_data) {
                // 邀请人数 +1
                $insert_data = [
                    'invite_new_user_total' => ['INC', 1],
                    'invite_new_user' => ['INC', 1]
                ];
                $count = db('activity_invite_user')->where('user_id', '=', $po_join_data['user_id'])->count(1);
                if ($count) {
                    // 修改
                    db('activity_invite_user')->where('user_id', '=', $po_join_data['user_id'])->update($insert_data);
                } else {
                    $insert_data['user_id'] = $po_join_data['user_id'];
                    // 新增
                    db('activity_invite_user')->insert($insert_data);
                }

                // 翻10倍
                if ($po_join_data['status'] == 0) {
                    $up_rate = $po_help['up_rate'] * 10 - $po_help['up_rate'];
                    $success_rate = $po_join_data['success_rate'] + $up_rate > 100 ? 100 : $po_join_data['success_rate'] + $up_rate;

                    db('activity_point_one_join')->where('id', '=', $po_help['po_join_id'])->update([
                        'success_rate' => $success_rate
                    ]);

                    db('activity_point_one_help')->where('id', '=', $po_help['id'])->update([
                        'is_award' => 2,
                        'up_rate' => $po_help['up_rate'] * 10
                    ]);
                }
            }
        }
    }
}