<?php

namespace app\api\controller;

use app\common\model\Common;
use app\common\model\User;
use app\common\model\Activity as ActivityModel;
use think\facade\Request;

class Activity extends Base
{
    protected function initialize()
    {
        $token = input('token');
        if (empty($token)) {
            $token = request()->header('token');
        }

        if (!empty($token) || !in_array(request()->action(), ['info', 'active', 'po_buy_goods'])) {
            parent::initialize();
        }
    }

    public function info()
    {
        $name = input('name');
        if (empty($name)) {
            return $this->response('', 'activity name empty', 1);
        }

        $data = db('active')->where([['name', '=', $name]])->find();

        if (!empty($data)) {
            $now_time = request()->time();
            $data['is_active'] = $data['begin_time'] < $now_time && $now_time < $data['end_time'] ? 1 : 0;

            if (strpos($data['link'], 'http://') !== 0 && strpos($data['link'], 'https://') !== 0) {
                $data['link'] = config('site.url') . $data['link'];
            }

            unset($data['id']);
            unset($data['add_time']);
        }

        return $this->response(['res' => $data]);
    }

    public function active()
    {
        $time = request()->time();
        $data = db('active')
            ->where([['begin_time', '<=', $time], ['end_time', '>', $time]])
            ->select();

        if ($data) {
            foreach ($data as $k => $v) {
                switch ($v['name']) {
                    case 'pick_new':
                        $v['name'] = 'pick_new_bag';
                        $price = mt_rand(8, 12);

                        if (!empty($this->user_id)) {
                            // 领
                            $res = ActivityModel::pick_new_bag($this->user_id);
//                            $res = db('activity_pick_new_bag')->where([
//                                ['user_id', '=', $this->user_id],
//                                ['end_time', '<=', 1],
//                            ])->find();

                            if (empty($res)) {
                                $v['data']['receive'] = [
                                    'amount' => (string)$price,
                                    'open_price' => (string)round($price * mt_rand(6666, 8000) / 10000, 2)
                                ];
                            } elseif ($res['end_time'] == 1) {
                                $v['data']['receive'] = [
                                    'amount' => (string)($res['price'] / 100),
                                    'open_price' => (string)($res['price_me'] / 100)
                                ];
                            }

//                            // 拆
//                            $res = db('activity_pick_new_bag_open')->where([
//                                ['user_id', '=', $this->user_id],
//                                ['open_price', '=', 0],
//                                ['status', '>', 0]
//                            ])->order('status asc,add_time desc')->find();
//
//                            if ($res) {
//                                $user = User::find($res['from_uid']);
//                                if ($user && strpos($user['avator'], 'http://') !== 0
//                                    && strpos($user['avator'], 'https://') !== 0) {
//                                    $user['avator'] = config('site.url') . $user['avator'];
//                                }
//
//                                $open_price = db('activity_pick_new_bag_open')
//                                    ->where([['from_uid', '=', $res['from_uid']]])
//                                    ->sum('open_price');
//
//                                $total_price = db('activity_pick_new_bag')
//                                    ->where([['id', '=', $res['bag_id']]])
//                                    ->value('price');
//
//                                $v['data']['open'] = [ // 帮拆
//                                    'id' => $res['id'],
//                                    'from_uid' => $res['from_uid'],
//                                    'nickname' => $user['nickname'],
//                                    'avator' => $user['avator'],
//                                    'total_price' => sprintf('%.2f', $open_price / 100),
//                                    'percent' => $total_price > $open_price ? round($open_price / $total_price * 100, 2) : 100,
//                                    'money' => (string)$price
//                                ];
//                            }
                        } else {
                            $v['data']['receive'] = [
                                'amount' => (string)$price,
                                'open_price' => (string)round($price * mt_rand(6666, 8000) / 10000, 2)
                            ];
                        }

//                        if (!empty($this->user_id)) {
//                            // 领
//                            $res = db('activity_pick_new')->where([
//                                ['user_id', '=', $this->user_id],
//                                ['from_uid', '=', $this->user_id],
//                            ])->find();
//                            if (!$res) {
//                                $v['data']['receive'] = ['money' => '10', 'amount' => '100', 'open_price' => '9.00'];
//                            }
//
//                            // 拆
//                            $res = db('activity_pick_new')->where([
//                                ['user_id', '=', $this->user_id],
//                                ['open_price', '=', 0],
//                                ['status', '>', 0]
//                            ])->order('status asc,add_time desc')->find();
//
//                            if ($res) {
//                                $user = User::find($res['from_uid']);
//
//                                $price = db('activity_pick_new')
//                                    ->where([['from_uid', '=', $res['from_uid']]])
//                                    ->sum('open_price');
//
//                                $v['data']['open'] = [ // 帮拆
//                                    'id' => $res['id'],
//                                    'from_uid' => $res['from_uid'],
//                                    'nickname' => $user['nickname'],
//                                    'avator' => $user['avator'],
//                                    'total_price' => sprintf('%.2f', $price / 100),
//                                    'money' => '10'
//                                ];
//                            }
//                        } else {
//                            $v['data']['receive'] = ['money' => '10', 'amount' => '100', 'open_price' => '9.00'];
//                        }

                        break;
                    default:
                        break;
                }

                if (strpos($v['link'], 'http://') !== 0 && strpos($v['link'], 'https://') !== 0) {
                    $v['link'] = config('site.url') . $v['link'];
                }

                unset($v['id']);
                unset($v['add_time']);
                $data[$k] = $v;
            }
        }

        return $this->response(['res' => $data]);
    }

    public function pick_new_open()
    {
        return $this->response('', 'activity over', 1);

        switch (request()->method()) {
            case 'POST':
                $from_uid = input('from_uid', 0, 'intval');
                if ($from_uid <= 0) return $this->response('', 'param missing', 1);
                if ($from_uid == $this->user_id) return $this->response('', 'can not open it on yourself', 1);

                $price = db('activity_pick_new')
                    ->where([['from_uid', '=', $from_uid]])
                    ->sum('open_price');

                if (empty($price)) return $this->response('', 'user not join in', 1);

                $data = ['money' => '10'];

                $res = db('activity_pick_new')->where([
                    ['user_id', '=', $this->user_id],
                    ['from_uid', '=', $from_uid],
                    ['open_price', '=', 0],
                    ['status', '>', 0]
                ])->order('status asc,add_time asc')->select();

                $count = count($res);

                if (empty($res) || (($res = $res[0]) && $res['status'] != 1)) {
                    if ($count > 0) { // 下次不用拆
                        db('activity_pick_new')
                            ->where([['user_id', '=', $this->user_id]])
                            ->update(['status' => 0]);
                    }

                    $data['total_price'] = sprintf('%.2f', $price / 100);
                    $data['msg'] = '新用户才可帮拆哦~';
                    return $this->response($data);
                }

                $price = $price % 1000;
                $open_price = 0;

                if ($price == 900) $open_price = 50;
                elseif ($price == 950) $open_price = 30;
                elseif ($price == 980) $open_price = 10;
                elseif ($price == 990) $open_price = 5;
                elseif ($price == 995) $open_price = 3;
                elseif ($price == 998) $open_price = 1;
                elseif ($price == 999) $open_price = 901;

                if ($open_price <= 0) return $this->response('', 'handler error', 1);

                $time = request()->time();

                db('activity_pick_new')->where([
                    // ['user_id', '=', $this->user_id],
                    // ['from_uid', '=', $from_uid],
                    ['id', '=', $res['id']]
                ])->update([
                    'open_time' => $time,
                    'open_price' => $open_price,
                    'status' => 0
                ]);

                if ($count > 0) { // 下次不用拆
                    db('activity_pick_new')
                        ->where([['user_id', '=', $this->user_id]])
                        ->update(['status' => 0]);
                }

                if ($open_price == 901) { // 进账
                    db('activity_pick_new_account')->insert([
                        'user_id' => $from_uid,
                        'price' => 1000,
                        'status' => 0,
                        'add_time' => $time
                    ]);

                    $content = '恭喜您已拆满10元现金';
                } else {
                    $content = '红包还差' . sprintf('%.2f', ((1000 - $price - $open_price) / 100)) . '元提现';
                }

                $user = User::find($this->user_id);
                $content = serialize([
                    'title' => $content,
                    'content' => $user['nickname'] . '已帮你拆了' . sprintf('%.2f', $open_price / 100) . '元，赶紧去看看吧'
                ]);

                db('user_dynamic')->insert([
                    'user_id' => 0,
                    'nickname' => '微选生活',
                    'avator' => '/static/img/logo.png',
                    'receive_uid' => $from_uid,
                    'event' => 'pick_new_open',
                    'event_id' => $res['id'],
                    'data' => $content,
                    'add_time' => $time
                ]);

                db('xinge_task')->insert([
                    'user_id' => $from_uid,
                    'event' => 'pick_new_open',
                    'data' => $content
                ]);

                $data['open_price'] = $open_price;
                $data['total_price'] = sprintf('%.2f', ($price + $open_price) / 100);
                return $this->response($data);
                break;
            default:
                break;
        }

        return $this->response();
    }

    public function pick_new_bag_open()
    {
        switch (request()->method()) {
            case 'POST':
                $from_uid = input('from_uid', 0, 'intval');
                $res = ActivityModel::pick_new_bag_open($this->user_id, $from_uid);
                return call_user_func_array([$this, 'response'], $res);
                break;
            default:
                break;
        }

        return $this->response();
    }

    public function pick_new_cash()
    {
        $time = request()->time();
        $data = [];
        $cash_record = db('activity_pick_new_cash')
            ->where([
                ['user_id', '=', $this->user_id],
                ['status', '<>', 2],
                ['time_begin', '<=', $time]
            ])
            ->field('price AS amount,time_begin,time_end')
            ->order('time_begin desc')
            ->select();

        if (!empty($cash_record)) {
            foreach ($cash_record as $k => $v) {
                $v['amount'] = $v['amount'] / 100;

                foreach ($v as $key => $val) {
                    $v[$key] = (string)$val;
                }

                $cash_record[$k] = $v;
            }

            $cash = $cash_record[0];
            if ($cash['time_begin'] <= $time && $time < $cash['time_end']) { // 待领
                $cash['countdown'] = (string)($cash['time_end'] - $time); // (7200 - ($time - $cash['time_begin']));
                $cash_record = array_slice($cash_record, 1);
                $data['cash'] = $cash;
            }
        }

        $data['list'] = $cash_record;
        return $this->response($data);
    }

    public function pick_new_cash_open()
    {
        switch (request()->method()) {
            case 'POST':
                $res = ActivityModel::pick_new_cash_open($this->user_id);
                return call_user_func_array([$this, 'response'], $res);
                break;
            default:
                break;
        }

        return $this->response();
    }

    public function return688_cash_container()
    {
        $user_level = User::level($this->user_id);
        if (!$user_level || $user_level < 3) {
            return $this->response([], 'vip等级以下没有权限', 40000);
        }

        $complete_count = 0; // 已完成个数
        $return_cash = 0; // 已领取现金
        // 任务列表
        $task = ActivityModel::return688_task_data();

        // 用户领取情况
        $complete_task = db('active_return688_task')->where('user_id', $this->user_id)->column('event');
        if ($complete_task) {
            foreach ($task as &$val) {
                if (in_array($val['event'], $complete_task)) {
                    $val['status'] = '1';
                    $val['is_complete'] = '1';
                    $return_cash += $val['price'];

                    if ($val['event'] == 'consume1000') {
                        $complete_count += 2;
                    } elseif ($val['event'] == 'subvip20') {
                        $complete_count += 2;
                    }
                }
            }
        }

        // 用户升级vip时间
        $promote_vip_time = db('user_money')->where([
            ['event', '=', 'level_promote_3'],
            ['user_id', '=', $this->user_id]
        ])->value('add_time');
        if (!$promote_vip_time) {
            $promote_vip_time = db('user_level')->where('user_id', $this->user_id)->value('add_time');
        }

        // 消费任务完成情况
        if ($task[1]['status'] == 0) {
            $consume_money = db('order_promotion')->where([
                ['buy_user_id', '=', $this->user_id],
                ['status', 'in', [4, 5, 6]],
                ['add_time', '>', $promote_vip_time]
            ])->sum('order_amount');
            $consume_money = $consume_money ?: 0;

            if ($consume_money >= 50000) {
                $task[0]['is_complete'] = '1';
                $complete_count++;
            }
            if ($consume_money >= 100000) {
                $task[1]['is_complete'] = '1';
                $complete_count++;
            }
        }

        // 直推vip完成情况
        if ($task[3]['status'] == 0) {
            $subvip_count = db('user_money')->where([
                ['event', '=', 'level_promote_3'],
                ['add_time', '>', $promote_vip_time],
                ['user_id', 'in', function ($query) {
                    $query->name('user_tier')->where('pid', $this->user_id)->field('user_id');
                }]
            ])->count();

            if ($subvip_count >= 10) {
                $task[2]['is_complete'] = '1';
                $complete_count++;
            }
            if ($subvip_count >= 20) {
                $task[3]['is_complete'] = '1';
                $complete_count++;
            }
        }

        return $this->response([
            'result' => [
                'complete_count' => $complete_count,
                'return_cash' => $return_cash,
                'task' => $task
            ]
        ]);
    }

    public function return688_cash_get()
    {
        $type = request()->get('type');
        if (!in_array($type, [0, 1, 2, 3, 4])) {
            return $this->response([], '参数错误', 40000);
        }

        // 任务信息
        $task = ActivityModel::return688_task_data($type);

        $rs = db('active_return688_task')->where([
            ['user_id', '=', $this->user_id],
            ['event', '=', $task['event']]
        ])->find();
        if ($rs) {
            return $this->response([], '奖励已领取！', 40001);
        }

        // 用户升级vip时间
        $promote_vip_time = db('user_money')->where([
            ['event', '=', 'level_promote_3'],
            ['user_id', '=', $this->user_id]
        ])->value('add_time');
        if (!$promote_vip_time) {
            $promote_vip_time = db('user_level')->where('user_id', $this->user_id)->value('add_time');
        }

        switch ($type) {
            case 0:
            case 1:
                // 消费条件
                $reached_total = db('order_promotion')->where([
                    ['buy_user_id', '=', $this->user_id],
                    ['status', 'in', [4, 5, 6]],
                    ['add_time', '>', $promote_vip_time]
                ])->sum('order_amount');
                $reached_total = $reached_total ?: 0;
                break;
            case 2:
            case 3:
                // 直属vip条件
                $reached_total = db('user_money')->where([
                    ['event', '=', 'level_promote_3'],
                    ['add_time', '>', $promote_vip_time],
                    ['user_id', 'in', function ($query) {
                        $query->name('user_tier')->where('pid', $this->user_id)->field('user_id');
                    }]
                ])->count();
                break;
            default:
                // 投稿商学院
                return $this->response([], '未达成条件！', 40002);
                break;
        }

        // 是否达成条件
        if ($reached_total >= $task['limit']) {
            $res = User::money($this->user_id, $task['price'] * 100, 'return688_' . $task['event'], $this->user_id, '活动奖励');

            db('active_return688_task')->insert([
                'user_id' => $this->user_id,
                'event' => $task['event'],
                'price' => $task['price'],
                'add_time' => request()->time()
            ]);

            return $this->response([], '领取成功', 0);
        } else {
            return $this->response([], '未达成条件！', 40002);
        }
    }

    public function po_buy_goods()
    {
        $page = Request::get('page', 1);
        $page_size = Request::get('page_size', 60);

        // 所有抢购商品
        $goods_data = db('actvity_goods')->alias('a')->field('id, goods_name, goods_img, price, snap_up_count, goods_id, 1 as type');

        // 用户抢购中商品
        if ($this->user_id) {
            $goods_data->where('id', 'NOT EXISTS', function($query){
                $query->name('activity_point_one_join')->where([
                    ['status', 'in', [0, 2]],
                    ['user_id', '=', $this->user_id]
                ])->where('goods_id = a.goods_id')->field('id');
            });

            $goods_buying = db('activity_point_one_join')->field('id, goods_name, goods_img, price, snap_up_count, goods_id, 2 as type')
            ->where([
                ['user_id', '=', $this->user_id],
                ['status', 'in', [0, 2]]
            ])
            ->select();
        }

        $goods_data = $goods_data->limit($page_size)->page($page)->select();
        if (isset($goods_buying)) {
            $goods_data = array_merge($goods_buying, $goods_data);
        }

        foreach ($goods_data as &$val) {
            $val['price'] = round($val['price'] / 100, 2);
        }

        return $this->response(['result' => [
            'goods_data' => $goods_data
        ]], 'success', 0);
    }
}
