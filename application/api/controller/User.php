<?php

namespace app\api\controller;

use think\Db;
use think\facade\Cache;
use think\facade\Config;
use app\common\model\Common;
use app\common\model\User as UserModel;
use app\common\model\Sms;
use app\common\model\UserIdentity;
use app\common\model\Order;
use app\common\model\GoodsModel;
use think\facade\Request;

class User extends Base
{
    protected function initialize()
    {
        $action_name = request()->action();
        $token = input('token');
        if (empty($token)) {
            $token = request()->header('token');
        }
        if ($token || ($action_name !== 'web_url' && $action_name !== 'level_promote_info' && $action_name !== 'superior')) {
            $this->checkToken();
        }
    }

    public function me()
    {
        switch (request()->method()) {
            case 'PUT': // 修改资料
                if (!empty(input('nickname'))) {
                    $data = ['nickname' => trim(input('nickname'))];
                } elseif (!empty(input('wechat'))) {
                    $data = ['wechat' => trim(input('wechat'))];
                } else {
                    return $this->response('', 'param missing', 1);
                }

                db('user')->where([['id', '=', $this->user_id]])->update($data);

                return $this->response();
                break;
            default: // 读取信息
                $field = input('field');
                if (empty($field)) {
                    return $this->response('', 'field empty', 1);
                }

                $field = explode(',', $field);
                if (array_diff($field, ['nickname', 'avator', 'balance', 'integral', 'mb', 'mobile', 'wechat'])) {
                    return $this->response('', 'field over', 1);
                }

                $user = UserModel::me();
                $data = [];
                foreach ($field as $v) {
                    if (isset($user[$v])) {
                        if ($v == 'balance') {
                            $data[$v] = $user[$v] / 100;
                        } elseif ($v == 'avator' && !empty($user[$v]) && strpos($user[$v], 'http://') === false) {
                            $data[$v] = config('site.url') . $user[$v];
                        } else {
                            $data[$v] = $user[$v];
                        }
                    }
                }

                // 邀请码
                $data['invite_code'] = db('user_promotion')->where('user_id', $this->user_id)->value('invite_code');

                // 当前会员等级
                $data['user_level'] = Db::name('user_level')
                ->where([
                    ['user_id', '=', $this->user_id],
                    ['expier_time', '>', request()->time()]
                ])->value('level');
                if (!$data['user_level']) {
                    $data['user_level'] = 1;
                }
                
                return $this->response($data);
                break;
        }

        return $this->response();
    }

    // 上级
    public function superior()
    {
        $pid = db('user_tier')->where('user_id', $this->user_id)->value('pid');

        if ($pid) {
            $sub_info = db('user')->field('mobile, wechat')->where('id', $pid)->find();
            $sub_info['invite_code'] = db('user_promotion')->where('user_id', $pid)->value('invite_code');
        }

        $sub_info['mobile'] = isset($sub_info['mobile']) && $sub_info['mobile'] ? $sub_info['mobile'] : '';
        $sub_info['wxname'] = isset($sub_info['wechat']) && $sub_info['wechat'] ? $sub_info['wechat'] : '';
        $sub_info['invite_code'] = isset($sub_info['invite_code']) && $sub_info['invite_code'] ? $sub_info['invite_code'] : '';

        return $this->response(['sub_info' => $sub_info, 'customer_mobile' => 'kangnaixin1989']);
    }

    public function dynamic()
    {
        $limit = 20;
        $next_id = input('next_id', 0, 'intval');

        $where = [['receive_uid', 'in', [0, $this->user_id]]];
        if ($next_id) $where[] = ['id', '<', $next_id];

        $data = Db::name('user_dynamic')->where($where)->order('id DESC')->limit($limit)->select();

        if (empty($data)) {
            return $this->response(['list' => [], 'next_id' => 0]);
        }

        $next_id = count($data) >= $limit ? end($data)['id'] : 0;

        $link_challenge_room = url('home/challenge/room', '', true, true);
        $link_challenge_join = url('home/challenge/join', '', true, true);
        $link_challenge_problem = url('home/genaral/problem_list', '', true, true);
        $site_url = config('site.url');

        foreach ($data as $key => $item) {
            $item['data'] = unserialize($item['data']);
            $item['time'] = Common::time_format($item['add_time']);
            if (strpos($item['avator'], 'http://') === false) {
                $item['avator'] = $site_url . $item['avator'];
            }

            switch ($item['event']) {
                case 'redpack' :
                    $item['data']['title'] = '发了一个班级红包';
                    $item['data']['remark'] = ['title' => '微选生活红包', 'desc' => '好习惯也值钱'];
                    break;
                case 'withdraw_fail':
                    $item['data']['remark'] = [
                        'title' => '好的，去看看',
                        'link' => $link_challenge_problem,
                    ];
                    break;
                case 'room_expire':
                    $item['data']['room_id'] = $item['event_id'];
                    $item['data']['remark'] = [
                        'title' => '好的，去看看',
                        'link' => $link_challenge_room // . '?room_id=' . $item['event_id'] // $item['data']['room_id']
                    ];
                    break;
                case 'passport_fail':
                    $item['data']['remark'] = ['title' => '重新提交验证'];
                    break;
                case 'system':
                    if (!empty($item['data']['link'])) {
                        $item['data']['remark'] = ['title' => '戳我啊！看看是什么', 'link' => $item['data']['link']];
                    }
                    break;
                case 'challenge_launch':
                    $item['data']['title'] = '发起了维持<font color="#169aff">' . $item['data']['day'] . '天</font>早起耐力挑战';
                    $item['data']['remark'] = ['title' => '点击接受挑战', 'desc' => '图片有惊喜'];

                    $item['data']['ad'] = [
                        'pic' => 'https://ss0.baidu.com/6ONWsjip0QIZ8tyhnq/it/u=2273281026,287208266&fm=173&app=25&f=JPEG?w=218&h=146&s=3CE7F900FAF94C9EC4A5609803008092',
                        'link' => 'http://baidu.com'
                    ];
                    break;
                case 'challenge_accept':
                    $item['data'] = [
                        'title' => '接受了你的早起耐力挑战',
                        'content' => '早睡早起好身体，记得要按约定日期和时间打卡哦，努力培养早睡早起的健康生活习惯，好习惯是需要坚持住的！',
                        'remark' => ['title' => '戳我呀！看看是什么']
                    ];
                    break;
                case 'challenge_room':
                    $item['data']['room_id'] = $item['event_id'];
                    $item['data']['remark'] = [
                        'title' => '戳我啊！看看是什么',
                        'link' => $link_challenge_join // . '?room_id=' . $item['event_id'] // $item['data']['room_id']
                    ];
                    break;
                default:
                    break;
            }

            unset($item['id']);
            unset($item['user_id']);
            unset($item['receive_uid']);
            unset($item['event_id']);
            unset($item['add_time']);

            $data[$key] = $item;
        }

        return $this->response(['list' => $data, 'next_id' => $next_id]);
    }

    public function dynamic_v2()
    {
        $arr = \app\common\model\User::dynamic_mode();

        $mode = input('mode');
        if (empty($mode) || !isset($arr[$mode])) {
            return $this->response('', 'param error', 1);
        }

        $limit = 20;
        $next_id = input('next_id', 0, 'intval');

        $where = [['receive_uid', 'in', [0, $this->user_id]]];
        if ($next_id) $where[] = ['id', '<', $next_id];
        if ($mode == 'system') $where[] = ['event', 'not in', array_merge($arr['challenge'], $arr['order'])];
        else $where[] = ['event', 'in', $arr[$mode]];

        $data = Db::name('user_dynamic')->where($where)->order('id DESC')->limit($limit)->select();

        if (empty($data)) {
            return $this->response(['list' => [], 'next_id' => 0]);
        }

        $next_id = count($data) >= $limit ? end($data)['id'] : 0;

        $link_challenge_room = url('home/challenge/room', '', true, true);
        $link_challenge_join = url('home/challenge/join', '', true, true);
        $link_challenge_problem = url('home/genaral/problem_list', '', true, true);
        // $link_dredge_eduities = url('home/goods/user_equities', '', true, true);
        $link_pick_new_bag = url('home/activity/pick_new_bag_app', '', true, true);
        $site_url = config('site.url');

        foreach ($data as $key => $item) {
            $item['data'] = unserialize($item['data']);
            $item['time'] = Common::time_format($item['add_time']);
            if (strpos($item['avator'], 'http://') === false) {
                $item['avator'] = $site_url . $item['avator'];
            }

            switch ($item['event']) {
                case 'redpack' :
                    $item['data']['title'] = '发了一个班级红包';
                    $item['data']['remark'] = ['title' => '微选生活红包', 'desc' => '好习惯也值钱'];
                    break;
                case 'withdraw_fail':
                    $item['data']['remark'] = [
                        'title' => '好的，去看看',
                        'link' => $link_challenge_problem,
                    ];
                    break;
                case 'room_expire':
                    $item['data']['room_id'] = $item['event_id'];
                    $item['data']['remark'] = [
                        'title' => '好的，去看看',
                        'link' => $link_challenge_room // . '?room_id=' . $item['event_id'] // $item['data']['room_id']
                    ];
                    break;
                case 'passport_fail':
                    $item['data']['remark'] = ['title' => '重新提交验证'];
                    break;
                case 'system':
                    if (!empty($item['data']['link'])) {
                        $item['data']['remark'] = ['title' => '戳我啊！看看是什么', 'link' => $item['data']['link']];
                    }
                    break;
                case 'challenge_launch':
                    $item['data']['title'] = '发起了维持<font color="#169aff">' . $item['data']['day'] . '天</font>早起耐力挑战';
                    $item['data']['remark'] = ['title' => '点击接受挑战', 'desc' => '图片有惊喜'];

                    $item['data']['ad'] = [
                        'pic' => 'https://ss0.baidu.com/6ONWsjip0QIZ8tyhnq/it/u=2273281026,287208266&fm=173&app=25&f=JPEG?w=218&h=146&s=3CE7F900FAF94C9EC4A5609803008092',
                        'link' => 'http://baidu.com'
                    ];
                    break;
                case 'challenge_accept':
                    $item['data'] = [
                        'title' => '接受了你的早起耐力挑战',
                        'content' => '早睡早起好身体，记得要按约定日期和时间打卡哦，努力培养早睡早起的健康生活习惯，好习惯是需要坚持住的！',
                        'remark' => ['title' => '戳我呀！看看是什么']
                    ];
                    break;
                case 'challenge_room':
                    $item['data']['room_id'] = $item['event_id'];
                    $item['data']['remark'] = [
                        'title' => '戳我啊！看看是什么',
                        'link' => $link_challenge_join // . '?room_id=' . $item['event_id'] // $item['data']['room_id']
                    ];
                    break;
                case 'free_order_item':
                    $item['data']['remark'] = [
                        'title' => '我要更多免单名额来送人',
                        // 'link' => $link_dredge_eduities
                    ];
                    break;
                case 'invite_success':
                    $item['data']['remark'] = [
                        'title' => '点击查看 >'
                    ];
                    break;
                case 'pick_new_bag_open':
                    $item['data']['remark'] = [
                        'title' => '立即查看',
                        'link' => $link_pick_new_bag
                    ];
                    break;
                default:
                    break;
            }

            unset($item['id']);
            unset($item['user_id']);
            unset($item['receive_uid']);
            unset($item['event_id']);
            unset($item['add_time']);

            $data[$key] = $item;
        }

        return $this->response(['list' => $data, 'next_id' => $next_id]);
    }

    public function money()
    {
        $next_id = input('next_id', 0, 'intval');
        $limit = 20;

        $where[] = ['user_id', '=', $this->user_id];
        if ($next_id) {
            $where[] = ['flow_id', '<', $next_id];
        }

        $data = Db::name('user_money')->where($where)
            ->field('flow_id,money,balance,event_name,add_time')
            ->order('flow_id desc')
            ->limit($limit)->select();
        if (!empty($data)) {
            $item = end($data);
            $next_id = count($data) >= $limit ? $item['flow_id'] : 0;

            foreach ($data as $k => $item) {
                $item['time'] = Common::time_format($item['add_time']);
                unset($item['flow_id']);
                unset($item['add_time']);
                $data[$k] = $item;
            }
        } else {
            $next_id = 0;
        }

        return $this->response(['list' => $data, 'next_id' => $next_id]);
    }

    public function mb()
    {
        $next_id = input('next_id', 0, 'intval');
        $limit = 3;

        $where[] = ['user_id', '=', $this->user_id];
        if ($next_id) {
            $where[] = ['flow_id', '<', $next_id];
        }

        $data = Db::name('user_mb')->where($where)
            ->field('flow_id,mb,balance,event_name,add_time')
            ->order('flow_id desc')
            ->limit($limit)->select();
        if (!empty($data)) {
            $item = end($data);
            $next_id = count($data) >= $limit ? $item['flow_id'] : 0;

            foreach ($data as $k => $item) {
                $item['time'] = Common::time_format($item['add_time']);
                unset($item['flow_id']);
                unset($item['add_time']);
                $data[$k] = $item;
            }
        } else {
            $next_id = 0;
        }

        return $this->response(['list' => $data, 'next_id' => $next_id]);
    }

    // 我的页面 - 收益
    public function award()
    {
        $today_time = Common::today_time();
        $info = [];

        // 今日早起奖励
        $income = db('user_money')->where([
            ['user_id', '=', $this->user_id],
            ['add_time', 'between', [$today_time, $today_time + 86400]],
            ['event', 'in', ['challenge_income_both', 'challenge_income_room', 'leader_income']]
        ])->sum('money');

        $info['today_earlier_award'] = sprintf('%.2f', $income);

        // 福利频道收入
        $order = db('order')
            ->field('user_id, directly_user_id, directly_supervisor_user_id, user_commission, directly_user_commission, directly_supervisor_user_commission')
            ->where([
                ['user_id|directly_user_id|directly_supervisor_user_id', '=', $this->user_id],
                ['add_time', 'BETWEEN', [$today_time, $today_time + 86400]],
                ['status', '<>', 3]
            ])->select();

        $info['goods_channel_award'] = 0;
        foreach ($order as $val) {
            if ($this->user_id == $val['user_id']) {
                $info['goods_channel_award'] += $val['user_commission'];
            } elseif ($this->user_id == $val['directly_user_id']) {
                $info['goods_channel_award'] += $val['directly_user_commission'];
            } else {
                $info['goods_channel_award'] += $val['directly_supervisor_user_commission'];
            }
        }
        // 晋升津贴
        $info['goods_channel_award'] += db('user_dredge_eduities')
            ->where([
                ['add_time', 'BETWEEN', [$today_time, $today_time + 86400]],
                ['pid', '=', $this->user_id]
            ])->sum('superior_award');
        // 免单金额
        $free_order = db()->query('select if(order_amount>500,500,order_amount) as free_amount_sum, add_time from sl_order where add_time > 1534163213 and `status` <> 3 and user_id in (select user_id from sl_user_tier where pid = ' . $this->user_id . ') GROUP BY user_id');
        if ($free_order) {
            foreach ($free_order as $val) {
                if ($val['add_time'] >= $today_time && $val['add_time'] < $today_time + 86400) {
                    // 今日
                    $info['goods_channel_award'] += $val['free_amount_sum'];
                }
            }
        }
        $info['goods_channel_award'] = round($info['goods_channel_award'] / 100, 2);

        // 累计收益率
        $all_income = db('user_money')->where([
            ['user_id', '=', $this->user_id],
            ['event', 'in', ['challenge_income_both', 'challenge_income_room', 'leader_income', 'superior_award', 'goods_commission']]
        ])->sum('money');

        $all_recharge = db('user_money')->where([
            ['user_id', '=', $this->user_id],
            ['event', 'like', 'recharge_%']
        ])->sum('money');

        $info['total_yield'] = $all_recharge ? round($all_income / $all_recharge, 2) * 100 : 0;

        return $this->response(['user_info' => $info]);
    }

    public function award_v2()
    {
        // 累计收益
        $all_income = UserModel::all_income($this->user_id);

        // 账户余额
        $balance = UserModel::money($this->user_id);

        // 已提现
        $withdraw_pass = db('user_withdraw')->where([
            ['user_id', '=', $this->user_id],
            ['status', '=', 1]
        ])->sum('draw_price');
        
        /*
        $data = [];
        $today_time = Common::today_time();

        $forecast_income_cache_key = 'forecast_income_' . $this->user_id;
        $forecast_income = Cache::get($forecast_income_cache_key);
        $data = $forecast_income;

        if (!isset($forecast_income['today_estimate_income'])) {
            // 今日预估收入
            $order = db('order')
                ->field('user_id, directly_user_id, directly_supervisor_user_id, user_commission, directly_user_commission, directly_supervisor_user_commission')
                ->where([
                    ['user_id|directly_user_id|directly_supervisor_user_id', '=', $this->user_id],
                    ['add_time', 'BETWEEN', [$today_time, $today_time + 86400]],
                    ['status', '<>', 3]
                ])->select();

            // 利润
            $data['today_estimate_income'] = 0;
            // 订单数
            $data['today_order'] = 0;
            foreach ($order as $val) {
                if ($this->user_id == $val['user_id']) {
                    $data['today_estimate_income'] += $val['user_commission'];
                } elseif ($this->user_id == $val['directly_user_id']) {
                    $data['today_estimate_income'] += $val['directly_user_commission'];
                } else {
                    $data['today_estimate_income'] += $val['directly_supervisor_user_commission'];
                }

                $data['today_order']++;
            }
            // 晋升津贴
            $data['today_estimate_income'] += db('user_dredge_eduities')
                ->where([
                    ['add_time', 'BETWEEN', [$today_time, $today_time + 86400]],
                    ['pid', '=', $this->user_id]
                ])->sum('superior_award');

            // 本月收入
            $now_month_start = strtotime(date('Y-m-01'));
            $now_month_end = strtotime(date('Y-m-t 23:59:59'));
            $order = db('order')
                ->field('user_id, directly_user_id, directly_supervisor_user_id, user_commission, directly_user_commission, directly_supervisor_user_commission')
                ->where([
                    ['user_id|directly_user_id|directly_supervisor_user_id', '=', $this->user_id],
                    ['add_time', 'BETWEEN', [$now_month_start, $now_month_end]],
                    ['status', '<>', 3]
                ])->select();

            $data['this_month_income'] = 0;
            $data['this_month_order'] = 0;
            foreach ($order as $val) {
                if ($this->user_id == $val['user_id']) {
                    $data['this_month_income'] += $val['user_commission'];
                } elseif ($this->user_id == $val['directly_user_id']) {
                    $data['this_month_income'] += $val['directly_user_commission'];
                } else {
                    $data['this_month_income'] += $val['directly_supervisor_user_commission'];
                }

                $data['this_month_order']++;
            }
            // 晋升津贴
            $data['this_month_income'] += db('user_dredge_eduities')
                ->where([
                    ['add_time', 'BETWEEN', [$now_month_start, $now_month_end]],
                    ['pid', '=', $this->user_id]
                ])->sum('superior_award');

            // 上月收入
            $last_month_start = strtotime(date('Y-m-01', strtotime('-1 month', strtotime(date('Y-m')))));
            $last_month_end = strtotime(date('Y-m-t 23:59:59', strtotime('-1 month', strtotime(date('Y-m')))));
            $order = db('order')
                ->field('user_id, directly_user_id, directly_supervisor_user_id, user_commission, directly_user_commission, directly_supervisor_user_commission')
                ->where([
                    ['user_id|directly_user_id|directly_supervisor_user_id', '=', $this->user_id],
                    ['add_time', 'BETWEEN', [$last_month_start, $last_month_end]],
                    ['status', '<>', 3]
                ])->select();

            $data['last_month_income'] = 0;
            $data['last_month_order'] = 0;
            foreach ($order as $val) {
                if ($this->user_id == $val['user_id']) {
                    $data['last_month_income'] += $val['user_commission'];
                } elseif ($this->user_id == $val['directly_user_id']) {
                    $data['last_month_income'] += $val['directly_user_commission'];
                } else {
                    $data['last_month_income'] += $val['directly_supervisor_user_commission'];
                }

                $data['last_month_order']++;
            }
            // 晋升津贴
            $data['last_month_income'] += db('user_dredge_eduities')
                ->where([
                    ['add_time', 'BETWEEN', [$last_month_start, $last_month_end]],
                    ['pid', '=', $this->user_id]
                ])->sum('superior_award');

            // 昨日收入
            $order = db('order')
                ->field('user_id, directly_user_id, directly_supervisor_user_id, user_commission, directly_user_commission, directly_supervisor_user_commission')
                ->where([
                    ['user_id|directly_user_id|directly_supervisor_user_id', '=', $this->user_id],
                    ['add_time', 'BETWEEN', [$today_time - 86400, $today_time]],
                    ['status', '<>', 3]
                ])->select();

            $data['yesterday_income'] = 0;
            $data['yesterday_order'] = 0;
            foreach ($order as $val) {
                if ($this->user_id == $val['user_id']) {
                    $data['yesterday_income'] += $val['user_commission'];
                } elseif ($this->user_id == $val['directly_user_id']) {
                    $data['yesterday_income'] += $val['directly_user_commission'];
                } else {
                    $data['yesterday_income'] += $val['directly_supervisor_user_commission'];
                }

                $data['yesterday_order']++;
            }
            // 晋升津贴
            $data['yesterday_income'] += db('user_dredge_eduities')
                ->where([
                    ['add_time', 'BETWEEN', [$today_time - 86400, $today_time]],
                    ['pid', '=', $this->user_id]
                ])->sum('superior_award');

            $free_order = db()->query('select if(order_amount>500,500,order_amount) as free_amount_sum, add_time from sl_order where add_time > 1534163213 and `status` <> 3 and user_id in (select user_id from sl_user_tier where pid = ' . $this->user_id . ') GROUP BY user_id');
            if ($free_order) {
                foreach ($free_order as $val) {
                    if ($val['add_time'] >= $today_time && $val['add_time'] < $today_time + 86400) {
                        // 今日
                        $data['today_estimate_income'] += $val['free_amount_sum'];
                    } else if ($val['add_time'] >= $today_time - 86400 && $val['add_time'] < $today_time) {
                        // 昨日
                        $data['yesterday_income'] += $val['free_amount_sum'];
                    }
                    if ($val['add_time'] >= $last_month_start && $val['add_time'] < $last_month_end) {
                        // 上月
                        $data['last_month_income'] += $val['free_amount_sum'];
                    } else if ($val['add_time'] >= $now_month_start && $val['add_time'] <= $now_month_end) {
                        // 本月
                        $data['this_month_income'] += $val['free_amount_sum'];
                    }
                }
            }

            $data['today_estimate_income'] /= 100;
            $data['this_month_income'] /= 100;
            $data['last_month_income'] /= 100;
            $data['yesterday_income'] /= 100;

            Cache::set($forecast_income_cache_key, $data, $today_time + 86400 - request()->time());
        }

        if (!isset($forecast_income['all_income'])) {
            $order = db('order')
                ->field('user_id, directly_user_id, directly_supervisor_user_id, user_commission, directly_user_commission, directly_supervisor_user_commission')
                ->where([
                    ['user_id|directly_user_id|directly_supervisor_user_id', '=', $this->user_id],
                    ['status', '<>', 3]
                ])->select();

            $forecast_income['all_income'] = 0;
            $forecast_income['all_order'] = 0;
            foreach ($order as $val) {
                if ($this->user_id == $val['user_id']) {
                    $forecast_income['all_income'] += $val['user_commission'];
                } elseif ($this->user_id == $val['directly_user_id']) {
                    $forecast_income['all_income'] += $val['directly_user_commission'];
                } else {
                    $forecast_income['all_income'] += $val['directly_supervisor_user_commission'];
                }
                $forecast_income['all_order']++;
            }
            // 免单数
            $forecast_income['all_income'] += db()->query('select sum(o.free_amount_sum) as free_amount_sum from (select if(order_amount>500,500,order_amount) as free_amount_sum from sl_order where add_time > 1534163213 and `status` <> 3 and user_id in (select user_id from sl_user_tier where pid = ' . $this->user_id . ') GROUP BY user_id) o')[0]['free_amount_sum'];
            // 晋升津贴
            $forecast_income['all_income'] += db('user_dredge_eduities')->where('pid', '=', $this->user_id)->sum('superior_award');

            $forecast_income['all_income'] = round($forecast_income['all_income'] / 100, 2);
        }
        */
        
        // 佣金收益
        $data = Order::income_marketing($this->user_id);
        // 佣金总收益
        $data['promotion_all_income'] = $data['all_income'];
        
        $data['all_income'] = $all_income ?: 0; // 总收益
        $data['balance'] = round($balance / 100, 2); // 账户余额
        $data['withdraw_pass'] = round($withdraw_pass / 100, 2) ?: 0; // 已通过提现
        
        $data['stat_income'] = db('user_money')->where([
            ['user_id', '=', $this->user_id],
            ['event', 'in', ['superior_award', 'free_order', 'goods_commission', 'invite_active_award', 'level_promote_awards_2', 'level_promote_awards_3', 'level_promote_awards_4']]
        ])->sum('money');
        $data['no_stat_income'] = $data['promotion_all_income'] > $data['stat_income'] ? $data['promotion_all_income'] - $data['stat_income'] : 0;

        if ($this->user_id == 2112) {
            $fake_data = Db::name('siteinfo')->where('key', '=', 'fake_data')->value('value');
            $fake_data = json_decode($fake_data, true);
            
            $data['no_stat_income'] = $fake_data['no_stat_income'] + $data['this_month_income'];
            $data['all_income'] = $fake_data['all_income'];
            $data['balance'] = $fake_data['balance'];
            $data['withdraw_pass'] = $fake_data['all_income'] - $fake_data['balance'];
            
            $this_day = date('d', time());
            if ($this_day >= 25 && date('Ym25', time()) != $fake_data['stat_month']) {
                // 上月结算金额
                $last_month_stat = round($data['last_month_income'] * mt_rand(70, 80) / 100, 2);
                
                $fake_data['balance'] += $last_month_stat + $fake_data['no_stat_income'];
                $fake_data['all_income'] += $last_month_stat + $fake_data['no_stat_income'];
                $fake_data['no_stat_income'] = $data['last_month_income'] - $last_month_stat;
                $fake_data['stat_month'] = date('Ym25', time());
                
                Db::name('siteinfo')->where('key', '=', 'fake_data')->update([
                    'value' => json_encode($fake_data)
                ]);
            }
        }
        
        return $this->response(['result' => $data]);
    }

    // 我的设置 - 保存头像
    public function avator_save()
    {
        $data = [];

        // 头像
        $file = request()->file('avator');
        if ($file) {
            $path = request()->root() . DIRECTORY_SEPARATOR . 'media/uploads';
            $info = $file->validate(['ext' => 'jpg,png'])->move('.' . $path, true, false);
            if ($info) {
                $data['avator'] = str_replace('\\', '/', $path . DIRECTORY_SEPARATOR . $info->getSaveName());
            } else {
                return $this->response([], '上传失败', 40001);
            }
        }

        $res = UserModel::update($data, ['id' => $this->user_id]);

        if ($res) {
            $old_avator = UserModel::find($this->user_id, 'avator')['avator'];
            if (strpos($old_avator, 'http://') !== false) {
                unlink($old_avator);
            }

            return $this->response(['avator' => config('site.url') . $data['avator']], '操作成功', 0);
        } else {
            return $this->response([], '上传失败', 40002);
        }
    }

    // 我的设置 - 绑定手机号
    public function bind_mobile()
    {
        $mobile = request()->put('mobile');
        $code = request()->put('code');

        if (!$mobile || !$code) {
            return $this->response([], '验证码错误！', 40000);
        }

        if (!preg_match("/^1\d{10}$/", $mobile)) {
            return $this->response([], '手机号码有误！', 40000);
        }

        $rs = UserModel::find(['mobile' => $mobile], 'id');
        if ($rs) {
            return $this->response([], '手机号码已存在！', 40000);
        }

        $rs = Sms::verify($mobile, $code);
        if ($rs != 'SUCCESS') {
            return $this->response($rs[0], $rs[1], $rs[2]);
        }

        UserModel::update(['mobile' => $mobile], ['id' => $this->user_id]);

        // 新用户红包
        $is_couple_weal = db('user_promotion')->where('user_id', $this->user_id)->value('is_couple_weal');
        if ($is_couple_weal == 1) {
            db('user_promotion')->where('user_id', $this->user_id)->update(['is_couple_weal' => 0]);

            $flow_id = UserModel::mb($this->user_id, 100, 'couple_weal', $this->user_id, '新人福利');
            // 邀请人红包
            $pid = db('user_tier')->where('user_id', $this->user_id)->value('pid');
            if ($pid) {
                $flow_id = UserModel::mb($pid, 200, 'invite_couple_weal', $this->user_id, '邀请');
            }
        }

        return $this->response([], '操作成功', 0);
    }

    // 我的设置 - 绑定上级
    public function bind_superior()
    {
        $invite_code = request()->put('invite_code');
        // 是否有上级
        $rs = db('user_tier')->where('user_id', $this->user_id)->find();
        if ($rs) {
            return $this->response([], '已经有上级', 40000);
        }

        $pid = db('user_promotion')->where('invite_code', $invite_code)->value('user_id');
        if (!$pid || $pid == $this->user_id) {
            return $this->response([], '邀请码不正确', 40000);
        }

        db('user_tier')->insert([
            'user_id' => $this->user_id,
            'pid' => $pid,
            'add_time' => request()->time()
        ]);

        return $this->response([], '操作成功', 0);
    }

    // 登录初始设置
    public function init_setting()
    {
        // 绑定上级
        $invite_code = request()->put('invite_code');
        if ($invite_code) {
            // 是否有上级
            $rs = db('user_tier')->where('user_id', $this->user_id)->find();
            if ($rs) {
                $pid = db('user_promotion')->where('invite_code', $invite_code)->value('user_id');
                if (!$pid || $pid == $this->user_id) {
                    return $this->response([], '邀请码不正确', 40000);
                }

                // 更换邀请人
                db('user_tier')->where('user_id', $this->user_id)->update([
                    'pid' => $pid,
                    'add_time' => request()->time()
                ]);
            } else {
                $rs = $this->bind_superior();
                if ($rs->getData()['code'] != 0) {
                    return $rs;
                }
            }
        }


        // 绑定手机号
        $rs = $this->bind_mobile();
        if ($rs->getData()['code'] != 0) {
            return $rs;
        }

        return $this->response([], '操作成功', 0);
    }

    public function is_check_identity()
    {
        $rs = db('user_identity')->where('user_id', $this->user_id)->find();

        if ($rs && $rs['status'] == 0) {
            return $this->response([], '您已提交，请耐心等待，留意系统消息通知', 40001);
        }
        if ($rs && $rs['status'] == 1) {
            return $this->response([], '您已通过审核', 40001);
        }

        return $this->response([], 'success', 0);
    }

    // 提现 - 上传身份证
    public function identity_card_save()
    {
        $type = request()->post('type');
        switch ($type) {
            case 1:
                $type = 'front';
                break;
            default:
                $type = 'back';
                break;
        }

        $file = request()->file('pic');
        if ($file) {
            $save_name = $type . '_' . $this->user_id;
            $res = $file->validate(['size' => 2097152, 'ext' => 'jpg,png'])->move('media/identity', $save_name . '.jpg');
            if ($res) {
                return $this->response(['file_path' => config('site.url') . '/media/identity/' . $res->getFilename()], '上传成功', 0);
            } else {
                $res = $file->getError();
                if ($res === '上传文件大小不符！') {
                    $res = '上传的图片请不要超过2M';
                }

                return $this->response([], $res, 40001);
            }
        } else {
            return $this->response([], '没图片上传', 40002);
        }
    }

    // 提现 - 身份验证
    public function check_identity()
    {
        $IDcart_front = request()->post('IDcart_front');
        $IDcart_back = request()->post('IDcart_back');

        if (!$IDcart_front || !$IDcart_back) {
            return $this->response([], '请先上传身份证图片', 40001);
        }

        try {
            $rand = mt_rand(100000, 999999);
            $UserIdentity = UserIdentity::get($this->user_id);
            if ($UserIdentity) {
                // 更新
                $UserIdentity->front_id_card = $IDcart_front . '?' . $rand;
                $UserIdentity->back_id_card = $IDcart_back . '?' . $rand;
                $UserIdentity->status = 0;
                $UserIdentity->save();
            } else {
                // 新增
                UserIdentity::create([
                    'user_id' => $this->user_id,
                    'real_name' => '',
                    'front_id_card' => $IDcart_front . '?' . $rand,
                    'back_id_card' => $IDcart_back . '?' . $rand
                ]);
            }

            return $this->response([], '操作成功！', 0);
        } catch (\Exception $e) {
            return $this->response([], '操作失败', 40002);
        }
    }

    public function is_subscibe()
    {
        // 查看提现正在使用的公众号
        $withdraw_tencent = Db::name('user_withdraw_tencent')->field('id, qrcode')->where('status', 1)->find();
        if (!$withdraw_tencent) {
            return $this->response([], '参数错误', '40000');
        }

        // 用户是否已关注
        $openid = Db::name('user_withdraw_tencent_relation')->where([
            ['user_id', '=', $this->user_id],
            ['withdraw_tencent_id', '=', $withdraw_tencent['id']]
        ])->value('openid');

        return $this->response(['is_subcribe' => $openid ? 1 : 0]);
    }

    // 提现 - 提现页面
    public function withdraw()
    {
        // 查看提现正在使用的公众号
        $withdraw_tencent = Db::name('user_withdraw_tencent')->field('id, qrcode')->where('status', 1)->find();
        if (!$withdraw_tencent) {
            return $this->response([], '正在升级！请稍后再来', '40000');
        }

        // 用户是否已关注
        $openid = Db::name('user_withdraw_tencent_relation')->where([
            ['user_id', '=', $this->user_id],
            ['withdraw_tencent_id', '=', $withdraw_tencent['id']]
        ])->value('openid');

//         // 是否已关注公众号
//         Db::name('user')->field('')->where('id', $this->user_id)->find();
//         if () {

//         }

        // 当日已提现金额
        $todaytime = Common::today_time();
        $map = [
            ['user_id', '=', $this->user_id],
            ['status', 'in', [0, 1]],
            ['add_time', 'between', [$todaytime, $todaytime + 86400]]
        ];
        $today_withdraw = Db::name('user_withdraw')->where($map)->sum('price');
        $today_withdraw /= 100;

        // 获取用户提现额度
        $btn_status = 0;
        $btn_text = '立即提交';
        $user_identity_data = UserIdentity::where('user_id', $this->user_id)->field('real_name, status')->find();

        if ($user_identity_data && $user_identity_data->status == 1) {
            // 审核通过
            $withdraw_limit = 10000;
            if ($today_withdraw >= $withdraw_limit) {
                // 已超过额度
                $btn_status = 1;
                $btn_text = '已超出当日限额，请明天再来';
            }
        } else {
            // 未通过
            $withdraw_limit = 3000;
            if ($today_withdraw >= $withdraw_limit) {
                // 可提升
                $btn_status = 2;
                $btn_text = '超出当日限额，提升额度';
            }
        }

        $data = [
            'qrcode' => $withdraw_tencent['qrcode'],
            'is_subcribe' => $openid ? 1 : 0,
            'real_name' => $user_identity_data ? $user_identity_data->real_name : '',
            'btn_status' => $btn_status,
            'btn_text' => $btn_text,
            'service_fee' => 0
        ];

        // 提现次数是否超过5次
        $rs = Db::name('user_withdraw')->where([
            ['user_id', '=', $this->user_id],
            ['status', '=', 1]
        ])->limit(4, 1)->select();
        if ($rs) {
            $data['service_fee'] = 1.2;
        }

        return $this->response($data);
    }

    public function withdraw_act()
    {
        $data = request()->post();

        $User = new UserModel();
        $res = $User->withdraw($this->user_id, $data);

        return $this->response($res[0], $res[1], $res[2]);
    }

    public function withdraw_record()
    {
        $data = Db::name('user_withdraw')->field('status, draw_price, price, add_time')->where('user_id', '=', $this->user_id)->order('add_time', 'desc')->select();
        foreach ($data as &$val) {
            $val['add_time'] = date('Y-m-d H:i:s', $val['add_time']);
            $val['draw_price'] = $val['draw_price'] ? round($val['draw_price'] / 100, 2) : round($val['price'] / 100, 2);

            // 提现状态
            switch ($val['status']) {
                case 1:
                    $val['status_desc'] = '提现成功';
                    break;
                case 2:
                    $val['status_desc'] = '提现失败';
                    break;
                default:
                    $val['status_desc'] = '提现';
                    break;
            }
        }

        return $this->response(['result' => [
            'withdraw_data' => $data
        ]]);
    }

    public function team()
    {
        $type = input('type');
        $next_id = input('next_id', 0);
        $page_size = input('page_size', 20);

        // 当前会员等级
        $user_level = Db::name('user_level')
        ->where([
            ['user_id', '=', $this->user_id],
            ['expier_time', '>', request()->time()]
        ])->value('level');
        if (!$user_level) {
            $user_level = 1;
        }
        
        $where = [
            ['b.level', '>', $user_level]
        ];
        if ($next_id) {
            $where[] = ['id', '<', $next_id];
        }

        switch ($type) {
            case 1:
                // 下级
                $data = Db::name('user')
                    ->alias('a')
                    ->field('a.id, a.nickname, a.avator, a.mobile, b.level, b.expier_time')
                    ->join(config('database.prefix') . 'user_level b', 'a.id =b.user_id', 'LEFT')
                    ->where('id', 'IN', function ($query) {
                        $query->name('user_tier')->where('pid', $this->user_id)->field('user_id');
                    })
                    ->where($where)
                    ->order('id', 'DESC')
                    ->limit($page_size)
                    ->select();
                break;
            case 2:
                // 下下级
                $data = Db::name('user')
                    ->alias('a')
                    ->field('a.id, a.nickname, a.avator, insert(a.`mobile`, 4, 4, "****") as `mobile`, b.expire_time')
                    ->join(config('database.prefix') . 'user_level b', 'a.id =b.user_id', 'LEFT')
                    ->where('id', 'IN', function ($query) {
                        $query->name('user_tier')->where('pid', 'IN', function ($query) {
                            $query->name('user_tier')->where('pid', $this->user_id)->field('user_id');
                        })->field('user_id');
                    })
                    ->where($where)
                    ->order('id', 'DESC')
                    ->limit($page_size)
                    ->select();
                foreach ($data as &$val) {
                    $val['superior_nickname'] = db()->query('SELECT `nickname` FROM `sl_user` WHERE `id` = ( SELECT `pid` FROM `sl_user_tier` WHERE `user_id` = ' . $val['id'] . ' ) LIMIT 1')[0]['nickname'];
                    if (!$val['superior_nickname']) {
                        $val['superior_nickname'] = db()->query('SELECT `invite_code` FROM `sl_user_promotion` WHERE `user_id` = ( SELECT `pid` FROM `sl_user_tier` WHERE `user_id` = ' . $val['id'] . ' ) LIMIT 1')[0]['invite_code'];
                    }
                }
                break;
            default:
                // 全部
                $sql = 'select distinct a.*,b.expire_time from (
SELECT `id`,`nickname`,`avator`,`mobile` FROM `sl_user` WHERE `id` IN (SELECT `user_id` FROM `sl_user_tier` WHERE  `pid` = ' . $this->user_id . ')
union
SELECT `id`,`nickname`,`avator`,insert(`mobile`, 4, 4, "****") FROM `sl_user` WHERE `id` IN (SELECT `user_id` FROM `sl_user_tier` WHERE `pid` IN (SELECT `user_id` FROM `sl_user_tier` WHERE  `pid` = ' . $this->user_id . '))
) as a LEFT JOIN ' . config('database.prefix') . 'user_promotion as b ON a.id=b.user_id ' . ($next_id ? ' WHERE a.id < ' . $next_id : '') . ' ORDER BY a.id DESC LIMIT ' . $page_size;

                $data = Db::query($sql);
                break;
        }

        $site_url = config('site.url');
        foreach ($data as &$val) {
            if ($val['avator'] && strpos($val['avator'], 'http://') === false && strpos($val['avator'], 'https://') === false) {
                $val['avator'] = $site_url . $val['avator'];
            }

            if ($val['expire_time'] > request()->time()) {
                switch ($val['level']) {
                    case 3:
                        $val['user_level'] = 'vip';
                        break;
                    case 4:
                        $val['user_level'] = '合伙人';
                        break;
                    default:
                        $val['user_level'] = '超级会员';
                        break;
                }
            } else {
                $val['user_level'] = '普通会员';
            }

            unset($val['expire_time']);
        }

        $count = count($data);
        if ($count > 0) {
            $next_id = $data[$count - 1]['id'];
        }

        return $this->response(['result' => $data, 'next_id' => $next_id]);
    }

    public function lower_team()
    {
        $type = input('type');
        $next_id = input('next_id', 0);
        $page_size = input('page_size', 20);
        if (!input('user_id')) {
            return $this->response([], '正在升级！请稍后再来', '40000');
        }


        $where = [];
        if ($next_id) {
            $where[] = ['id', '<', $next_id];
        }

        switch ($type) {
            case 1:
                // 下级
                $data = Db::name('user')
                    ->alias('a')
                    ->field('a.id, nickname, avator, mobile, b.expire_time')
                    ->join(config('database.prefix') . 'user_promotion b', 'a.id =b.user_id', 'LEFT')
                    ->where('id', 'IN', function ($query) {
                        $query->name('user_tier')->where('pid', input('user_id'))->field('user_id');
                    })
                    ->where($where)
                    ->order('id', 'DESC')
                    ->limit($page_size)
                    ->select();
                break;
            case 2:
                // 下下级
                $data = Db::name('user')
                    ->alias('a')
                    ->field('a.id, nickname, avator, insert(`mobile`, 4, 4, "****") as `mobile`, b.expire_time')
                    ->join(config('database.prefix') . 'user_promotion b', 'a.id =b.user_id', 'LEFT')
                    ->where('id', 'IN', function ($query) {
                        $query->name('user_tier')->where('pid', 'IN', function ($query) {
                            $query->name('user_tier')->where('pid', input('user_id'))->field('user_id');
                        })->field('user_id');
                    })
                    ->where($where)
                    ->order('id', 'DESC')
                    ->limit($page_size)
                    ->select();
                break;
            default:
                // 全部
                $sql = 'select distinct a.*,b.expire_time from (
SELECT `id`,`nickname`,`avator`,`mobile` FROM `sl_user` WHERE `id` IN (SELECT `user_id` FROM `sl_user_tier` WHERE  `pid` = ' . input('user_id') . ')
union
SELECT `id`,`nickname`,`avator`,insert(`mobile`, 4, 4, "****") FROM `sl_user` WHERE `id` IN (SELECT `user_id` FROM `sl_user_tier` WHERE `pid` IN (SELECT `user_id` FROM `sl_user_tier` WHERE  `pid` = ' . input('user_id') . '))
) as a LEFT JOIN ' . config('database.prefix') . 'user_promotion as b ON a.id=b.user_id ' . ($next_id ? ' WHERE a.id < ' . $next_id : '') . ' ORDER BY a.id DESC LIMIT ' . $page_size;

                $data = Db::query($sql);
                break;
        }

        $site_url = config('site.url');
        foreach ($data as &$val) {
            if ($val['avator'] && strpos($val['avator'], 'http://') === false && strpos($val['avator'], 'https://') === false) {
                $val['avator'] = $site_url . $val['avator'];
            }

            if ($val['expire_time'] && $val['expire_time'] > request()->time()) {
                $val['user_level'] = '合伙人';
            } else {
                $val['user_level'] = '超级会员';
            }

            unset($val['expire_time']);
        }

        $count = count($data);
        if ($count > 0) {
            $next_id = $data[$count - 1]['id'];
        }

        return $this->response(['result' => $data, 'next_id' => $next_id]);
    }

    public function team_search()
    {
        $search_key = input('search_key');

        $where = [];
        if ($search_key) {
            $where[] = ['nickname|mobile', 'like', '%' . $search_key . '%'];
        }

        // 下级
        $data = Db::name('user')
            ->alias('a')
            ->field('a.id, nickname, avator, mobile, b.expire_time')
            ->join(config('database.prefix') . 'user_promotion b', 'a.id =b.user_id', 'LEFT')
            ->where('id', 'IN', function ($query) {
                $query->name('user_tier')->where('pid', $this->user_id)->field('user_id');
            })
            ->where($where)
            ->select();

        // 下下级
        $lower_data = Db::name('user')
            ->alias('a')
            ->field('a.id, nickname, avator, insert(`mobile`, 4, 4, "****") as `mobile`, b.expire_time')
            ->join(config('database.prefix') . 'user_promotion b', 'a.id =b.user_id', 'LEFT')
            ->where('id', 'IN', function ($query) {
                $query->name('user_tier')->where('pid', 'IN', function ($query) {
                    $query->name('user_tier')->where('pid', $this->user_id)->field('user_id');
                })->field('user_id');
            })
            ->where($where)
            ->select();
        foreach ($lower_data as &$val) {
            $val['superior_nickname'] = db()->query('SELECT `nickname` FROM `sl_user` WHERE `id` = ( SELECT `pid` FROM `sl_user_tier` WHERE `user_id` = ' . $val['id'] . ' ) LIMIT 1')[0]['nickname'];
        }

        // 合并下级 下下级
        $site_url = config('site.url');
        $data = array_merge($data, $lower_data);
        foreach ($data as &$val) {
            if ($val['avator'] && strpos($val['avator'], 'http://') === false && strpos($val['avator'], 'https://') === false) {
                $val['avator'] = $site_url . $val['avator'];
            }

            if ($val['expire_time'] && $val['expire_time'] > request()->time()) {
                $val['user_level'] = '合伙人';
            } else {
                $val['user_level'] = '超级会员';
            }
            unset($val['expire_time']);
        }

        return $this->response(['result' => $data]);
    }

    public function web_url()
    {
        $site_url = config('site.url');

        if (empty($this->app_token)) $city = '';
        else $city = $this->app_token['city'];

        $urls = [
            // 常见问题
            'common_problem_new' => $site_url . '/genaral/problem_list',
            // 超级分享
            'super_share' => $site_url . '/user/everlastinglove',
            // 邀请好友（海报）
            'invite' => $site_url . '/user/invite',
            // 会员权益
            'user_equities' => $site_url . '/goods/user_equities',
            // 挑战加入
            'challenge_join' => url('home/challenge/join', '', '', true),
            // 我的族群
            'challenge_mine' => url('home/challenge/mine', '', '', true),
            // 族群消息
            'challenge_dynamic' => url('home/challenge/dynamic', '', '', true),
            // 创建族群
            'challenge_room' => url('home/challenge/room', '', '', true),
            // M币
            'user_mb' => url('home/user/mb', '', '', true),
            // 联系客服
            'contack_customer' => url('home/user/contack_customer', '', '', true),
            // 文章详情
            'news_detail_link' => url('home/article/detail', '', '', true),
            // 注册条款
            'agreement' => url('home/genaral/agreement', '', '', true),
            // 发现如何使用
            'free_order_use' => url('home/goods/free_order_use', '', '', true),
            // 免单分享
            'free_order_share' => url('home/goods/free_goods_share', '', '', true),
            // 新手教程
            'user_guide' => config('site.url') . '/static/user/guide.html',
            // 签到领现金
            'signin_income' => \app\common\model\Challenge::pull_redpack_room($city, true, true),
            // 京东免单分享
            'free_order_share_jd' => url('home/goods/free_goods_share_jd', '', '', true),
            // 0.1元抢购活动
            'po_buy_goods' => url('home/activity/po_buy_goods', '', '', true)
        ];

        return $this->response(['result' => $urls]);
    }

    public function contact()
    {
        switch (request()->method()) {
            case 'POST':
                $data = input('post.');
                if (empty($data['user'])) return $this->response('', 'param missing', 1);

                $user = $data['user'];
                if (is_string($user)) {
                    if (substr($user, 0, 1) != '[') {
                        $user = Common::des_decrypt($user);
                    }

                    $user = json_decode($user, true);

                    if (empty($user)) {
                        $user = unserialize($user);
                    }
                }

                if (empty($user)) return $this->response('', 'param error', 1);

                $time = request()->time();
                foreach ($user as $k => $v) {
                    $v['user_id'] = $this->user_id;
                    $v['add_time'] = $time;

                    $user[$k] = $v;
                }

                db('user_contact')->insertAll($user);

                // 删除重复
                $data = db('user_contact')
                    ->where([['user_id', '=', $this->user_id]])
                    ->field('GROUP_CONCAT(id ORDER BY id DESC) ids')
                    ->group('user_id,mobile')
                    ->having('COUNT(1) > 1')
                    ->select();

                if (!empty($data)) {
                    $ids = [];
                    foreach ($data as $item) {
                        if (!empty($item['ids'])) {
                            $ids[] = substr($item['ids'], strpos($item['ids'], ',') + 1);
                        }
                    }

                    if (!empty($ids)) {
                        db('user_contact')->where([['id', 'in', join(',', $ids)]])->delete();
                    }
                }

                return $this->response();
                break;
            case 'PUT': // 修改资料
                break;
            default:
                $limit = 100;
                $where = [['user_id', '=', $this->user_id]];
                $next_id = input('next_id', 0, 'intval');
                if ($next_id) $where[] = ['id', '<', $next_id];

                $data = db('user_contact')->where($where)->order('id DESC')->limit($limit)->column('mobile,uname,id');
                if (empty($data)) {
                    return $this->response(['list' => [], 'next_id' => 0]);
                }

                $data_out = ['next_id' => count($data) >= $limit ? end($data)['id'] : 0];
                $site_url = config('site.url');
                $today_time = Common::today_time();
                $mobile_arr = [];
                $mobile_send = db('invite_sms_send')->where([
                    ['user_id', '=', $this->user_id],
                    ['add_time', '>=', $today_time],
                    ['add_time', '<', $today_time + 86400]
                ])->column('mobile');

                foreach ($data as $k => $v) {
                    // $v['mobile'] = Common::des_encrypt($v['mobile']);
                    $v['avator'] = $site_url . '/static/img/logo.png';
                    $v['remark'] = '未使用过微选生活';
                    $v['status'] = 0; // 未加入
                    if (in_array($k, $mobile_send)) {
                        $v['is_send'] = 1;
                        $v['send_name'] = '已发送';
                    } else {
                        $v['is_send'] = 0;
                        $v['send_name'] = '送给TA';
                    }

                    unset($v['id']);

                    $data[$k] = $v;

                    if (is_numeric($k)) $mobile_arr[] = (string)$k;
                }

                if (!empty($mobile_arr)) {
                    $user = db('user')->where([['mobile', 'in', $mobile_arr]])->column('mobile,nickname,avator');
                    if (!empty($user)) {
                        foreach ($user as $k => $v) {
                            $u = $data[$k];
                            $u['avator'] = (strpos($v['avator'], 'http://') === false ? $site_url : '') . $v['avator'];
                            $u['remark'] = '微选生活:' . $v['nickname'];
                            $u['status'] = 2; // 已加入
                            $u['is_send'] = 1;
                            $u['send_name'] = '已使用';
                            $data[$k] = $u;
                        }
                    }
                }

                $data_out['list'] = array_values($data);

                return $this->response($data_out);
                break;
        }

        return $this->response();
    }

    public function invite_info()
    {
        $root_path = Config::get('app.poster.root_path');
        $invite_code = db('user_promotion')->where('user_id', $this->user_id)->value('invite_code');

        $url = config('site.url') . "/user/invite_page?invite_code=" . $invite_code;
        $file_qrcode = $root_path . 'qrcode/invite/' . $this->user_id . '.png';
        if (!file_exists($file_qrcode)) {
            $rs = Common::make_qrcode($url, $file_qrcode);
        }

        return $this->response([
            'result' => [
                'invite_code' => $invite_code,
                'file_qrcode' => config('site.url') . trim($file_qrcode, '.')
            ]
        ]);
    }

    public function invite_sms_set()
    {
        switch (request()->method()) {
            case 'POST':
                $uname = input('uname');
                $content = input('content');
                if (empty($uname) || empty($content)) {
                    return $this->response(['', 'param missing', 1]);
                }

                $data = ['uname' => $uname, 'content' => $content, 'edit_time' => request()->time()];

                $res = db('invite_sms_set')->where([['user_id', '=', $this->user_id]])->update($data);
                if (!$res) {
                    $count = db('invite_sms_set')->where([['user_id', '=', $this->user_id]])->count(1);
                    if ($count == 0) {
                        $data['user_id'] = $this->user_id;
                        $data['link'] = UserModel::invite_page($this->user_id, true, true);

                        db('invite_sms_set')->insert($data);
                    }
                }

                return $this->response();
                break;
            default:
                return $this->response(UserModel::invite_sms_set($this->user_id));
                break;
        }
    }

    public function invite_sms_send()
    {
        switch (request()->method()) {
            case 'POST':
                $mobile = input('mobile');
                $uname = input('uname');
                if (empty($mobile) || empty($uname)) return $this->response('', 'param missing', 1);

                if (!preg_match("/^1\d{10}$/", $mobile)) {
                    return $this->response('', '手机号码有误！', 20000);
                }

                $now_time = request()->time();
                $today_time = Common::today_time();

                $expire_time = Db::name('user_promotion')->where([['user_id', '=', $this->user_id]])->value('expire_time');
                if ($expire_time > $now_time) { // 合伙人
                    $max_all = 300;
                    $max_today = 30;
                } else { // 超级会员
                    $max_all = 100;
                    $max_today = 10;
                }

                // 数量验证
                $sql = 'SELECT COUNT(1) FROM `' . db('invite_sms_send')->getTable() . '` WHERE `user_id` = ' . $this->user_id;
                $data = db()->query('SELECT 
                ( ' . $sql . ' ) c_all,
                ( ' . $sql . ' AND `add_time` >= ' . $today_time . '  AND `add_time` < ' . ($today_time + 86400) . ' ) c_today');

                if ($data[0]['c_all'] >= $max_all || $data[0]['c_today'] >= $max_today) {
                    return $this->response('', '亲，今天发的有点多了，明天再来吧', 101);
                }

                $data = UserModel::invite_sms_set($this->user_id);
                $myname = $data['uname'];
                $content = $data['content'];

                $res = Sms::getInstance()->send($mobile, str_replace(['{%taname%}', '{%myname%}', '{%content%}'], [$uname, $myname, $content], $data['tpl']));
                if ($res['errcode'] != 0) {
                    return $this->response('', '短信发送失败，请重试！' . (env('APP_DEBUG') ? $res['errmsg'] : ''), 20002);
                }

                db('invite_sms_send')->insert(['user_id' => $this->user_id, 'mobile' => $mobile, 'add_time' => $now_time]);
                return $this->response('', '已发送，您也可以电话跟他联系说明下哦');
            case 'PUT':
                $mobile = input('mobile');
                // $uname = input('uname');

                $now_time = request()->time();

                db('invite_sms_send')->insert(['user_id' => $this->user_id, 'mobile' => $mobile, 'add_time' => $now_time]);
                break;
            default:
                break;
        }

        return $this->response();
    }

    public function income_record()
    {
        $next_id = input('next_id', 0, 'intval');
        $limit = 20;

        $where = [
            ['user_id', '=', $this->user_id],
            ['event', 'in', UserModel::all_income_event()]
        ];

        if ($next_id) {
            $where[] = ['flow_id', '<', $next_id];
        }

        $data = Db::name('user_money')->where($where)
            ->field('flow_id,money,balance,event_name,add_time')
            ->order('flow_id desc')
            ->limit($limit)->select();
        if (!empty($data)) {
            $item = end($data);
            $next_id = count($data) >= $limit ? $item['flow_id'] : 0;

            foreach ($data as $k => $item) {
                $item['time'] = Common::time_format($item['add_time']);
                unset($item['flow_id']);
                unset($item['add_time']);
                $data[$k] = $item;
            }
        } else {
            $next_id = 0;
        }

        return $this->response(['list' => $data, 'next_id' => $next_id]);
    }
    
    public function level()
    {
        // 当前会员等级
        $user_level = Db::name('user_level')
        ->where([
            ['user_id', '=', $this->user_id],
            ['expier_time', '>', request()->time()]
        ])->value('level');
        if (!$user_level) {
            $user_level = 1;
        }
        
        return $this->response([
            'result' => [
                'user_level' => $user_level,
            ]
        ]);
    }
    
    public function level_promote_info()
    {
        // 页面详情
        $level_welfare = [
            'super_member' => [
                'price' => '19.9',
                'describe' => [
                    '19.9元现金奖励',
                    '100%商品返利',
                    '100%佣金返利',
                    '10%佣金提成'
                ]
            ],
            'vip' => [
                'price' => '399',
                'describe' => [
                    '139.9元现金奖励',
                    '150%商品返利',
                    '150%佣金返利',
                    '128%佣金提成',
                    '送拼多多/京东店铺',
                    '送价值999元族群'
                ]
            ],
            'partner' => [
                'price' => '3999',
                'describe' => [
                    '好友升级奖励1719.9元',
                    '自购或分享，赚佣金176%',
                    '团队成员消费，赚佣金176%',
                    '好友升级赚其团队佣金45%'
                ]
            ]
        ];
        
        return $this->response([
            'result' => [
                'level_welfare' => $level_welfare,
            ]
        ]);
    }
    
    public function level_promote()
    {
        $para = input('post.');
        $para['out_trade_no'] = Order::order_sn_create(); // 订单号

        $res = GoodsModel::level_promote($this->user_id, $para);

        return call_user_func_array([$this, 'response'], $res);
    }
    
    public function level_promote_commission_remind()
    {
        // 提醒用户可领取的升级佣金
        $data = Db::name('user_level_promote_commission_remind')
        ->field('id as _id, '. request()->time() .' - add_time as countdown, add_time as create_time,remind_user_commisson as amount, remind_user_condition as business, remind_user_status as condition_status')
        ->orderRaw('if(remind_user_status = 1, 1, 0), add_time DESC')
        ->where([
            ['remind_user_id', '=', $this->user_id],
            ['status', '=', 0],
            ['add_time', '>', request()->time() - 7200]
        ])->select();
        
        $time = request()->time();

        $ret_data = [
            'cash' => [],
            'list' => []
        ];
        foreach ($data as $val) {
            $val['amount'] = ''. round($val['amount'] / 100, 2);
            $val['countdown'] = 7200 - $val['countdown'] > 0 ? ''. (7200 - $val['countdown']) : '0';
            
            // 红包开始时间
            $val['time_begin'] = $val['create_time'];
            // 红包结束时间
            $val['time_end'] = ''. ($val['create_time'] + 7200);
            
            if ($time - $val['time_begin'] <= 7200) {
                $ret_data['cash'][] = $val;
            } else {
                $val['countdown'] = '0';
                $ret_data['list'][] = $val;
            }
        }
        
        return $this->response([
            'result' => [
                'data' => $ret_data
            ]
        ]);
    }
    
    public function level_promote_commission_grant()
    {
        $id = request()->post('id');
        if (!$id) {
            return [[], '参数错误', 40000];
        }
        
        // 等级提升返佣记录
        $promote_commission_data = Db::name('user_level_promote_commission_remind')
        ->field('id, user_id, level, add_time, status, remind_user_id, remind_user_condition, remind_user_commisson, remind_user_status, team_data')
        ->where([
            ['id', '=', $id],
        ])
        ->find();
        if ($promote_commission_data['add_time'] <= request()->time() - 7200) {
            return [[], '红包已过期', 40001];
        }
        
        $res = UserModel::level_promote_commission_grant($promote_commission_data);
        
        return call_user_func_array([$this, 'response'], $res);
    }
    
    public function team_v2()
    {
        $type = request()->get('type');
        $page = input('page', 1);
        $page_size = input('page_size', 20);
        $keyword = input('keyword', '');
        $sort_type = input('sort_type', 0);
        $sort_way = 'DESC';
        $level_type = input('level_type', 0);

        // 排序方式
        switch ($sort_type) {
            case 1:
                $sort_type = 'id';
                $sort_way = 'ASC';
                break;
            default:
                $sort_type = 'id';
                break;
        }

        // 当前会员等级
        $user_level = Db::name('user_level')
        ->where([
            ['user_id', '=', $this->user_id],
            ['expier_time', '>', request()->time()]
        ])->value('level');
        if (!$user_level) {
            $user_level = 1;
        }
        
        $where = [];
        $where[] = ['a.openid_app', '<>', ''];
        // 关键字筛选
        if ($keyword) {
            $where[] = ['nickname', 'like', '%'. $keyword .'%'];
        }
        // 用户等级筛选
        if ($level_type) {
            $where[] = ['ul.level', '=', $level_type];
        }
        
        switch ($type) {
            case 1:
                // 下级
                $data = Db::name('user')
                ->alias('a')
                ->join('user_level ul', 'ul.user_id = a.id', 'LEFT')
                ->field('a.id, nickname, avator, mobile, ul.expier_time, ul.level')
                ->where('id', 'IN', function ($query) {
                    $query->name('user_tier')->where('pid', $this->user_id)->field('user_id');
                })
                ->where($where)
                ->order($sort_type, $sort_way)
                ->page($page)
                ->limit($page_size)
                ->select();
                break;
            default:
                // 下下级
                $data = Db::name('user')
                ->alias('a')
                ->join('user_level ul', 'ul.user_id = a.id', 'LEFT')
                ->field('a.id, nickname, avator, insert(`mobile`, 4, 4, "****") as `mobile`, ul.expier_time, ul.level')
                ->where('id', 'IN', function ($query) {
                    $query->name('user_tier')->where('pid', 'IN', function ($query) {
                        $query->name('user_tier')->where('pid', $this->user_id)->field('user_id');
                    })->field('user_id');
                })
                ->where($where)
                ->order($sort_type, $sort_way)
                ->page($page)
                ->limit($page_size)
                ->select();
                foreach ($data as &$val) {
                    $val['superior_nickname'] = db()->query('SELECT `nickname` FROM `sl_user` WHERE `id` = ( SELECT `pid` FROM `sl_user_tier` WHERE `user_id` = ' . $val['id'] . ' ) LIMIT 1')[0]['nickname'];
                    if (!$val['superior_nickname']) {
                        $val['superior_nickname'] = db()->query('SELECT `invite_code` FROM `sl_user_promotion` WHERE `user_id` = ( SELECT `pid` FROM `sl_user_tier` WHERE `user_id` = ' . $val['id'] . ' ) LIMIT 1')[0]['invite_code'];
                    }
                }
                break;
        }
    
        $site_url = config('site.url');
        $team_data = [];
        foreach ($data as $key=>&$val) {
            if ($val['avator'] && strpos($val['avator'], 'http://') === false && strpos($val['avator'], 'https://') === false) {
                $val['avator'] = $site_url . $val['avator'];
            }

            if ($val['expier_time'] > request()->time()) {
                if ($val['level'] < $user_level) {
                    switch ($val['level']) {
                        case 3:
                            $val['user_level'] = 'vip';
                            break;
                        case 4:
                            $val['user_level'] = '合伙人';
                            break;
                        case 2:
                            $val['user_level'] = '超级会员';
                            break;
                        default:
                            $val['user_level'] = '普通会员';
                            break;
                    }
                } else {
                    continue;
                }
            } else {
                $val['user_level'] = '普通会员';
            }
    
            unset($val['expire_time']);
            $team_data[] = $val;
        }
    
        return $this->response([
            'result' => [
                'team_data' => $team_data
            ]
        ]);
    }
    
    public function team_search_v2()
    {
        // 下级团队
        Request::get(['type' => 1]);
        $lower_team = $this->team_v2()->getData();
        
        // 下下级团队
        Request::get(['type' => 2]);
        $lower_lower_team = $this->team_v2()->getData();
        
        $data = array_merge($lower_team['data']['result']['team_data'], $lower_lower_team['data']['result']['team_data']);
        
        return $this->response([
            'result' => [
                'team_data' => $data
            ]
        ]);
    }
    
    public function team_info()
    {
        $team_user_id = Request::get('team_user_id');
        
        // 用户等级
        $user_level = Db::name('user_level')
        ->where([
            ['user_id', '=', $this->user_id],
            ['expier_time', '>', request()->time()]
        ])->value('level');
        
        // 团队人数
        $count_team_user = 0;
        if ($user_level) {
            // 推荐市场
            $count_directly_user = db()->query('select count(*) as count from sl_user_level where `level` < '. $user_level .' and user_id in ( select user_id from sl_user_tier where pid = '. $team_user_id .' )')[0]['count'];
            // 招募市场
            $count_directly_lower_user = db()->query('select count(*) as count from sl_user_level where `level` < '. $user_level .' and user_id in ( select user_id from sl_user_tier where pid in ( select user_id from sl_user_tier where pid = '. $team_user_id .' ) )')[0]['count'];
            
            $count_team_user = $count_directly_user + $count_directly_lower_user;
        }
        
        // 总收入
        $income_data = Order::income_marketing($team_user_id);
        
        // 用户信息
        $user_info = Db::name('user')->field('nickname, wxname, mobile')->where('id', '=', $team_user_id)->find();
        
        return $this->response([
            'result' => [
                'count_team_user' => $count_team_user,
                'all_income' => $income_data['all_income'],
                'user_info' => $user_info
            ]
        ]);
    }
    
    public function share_copywriting()
    {
        // 查看邀请码
        $invite_code = db('user_promotion')->where('user_id', $this->user_id)->value('invite_code');
        // 域名
        $site_url = config('site.url');
        
        $data = [
            'team_user' => [
                'title' => '有好习惯就能分钱，还送免单和现金',
                'desc'=> '精致微选生活，赚赚赚！早起、阅读，免费领券还能再赚，让你拥有独立的拼多多社群商城，自用省，分享赚...',
                'imgUrl' => $site_url . '/static/img/wx_share.png',
                'link' => $site_url . url('home/user/invite_page') . '?invite_code='. $invite_code
            ]
        ];
        
        return $this->response([
            'result' => [
                'data' => $data,
            ]
        ]);
    }
    
    public function once_login_time()
    {
        // 只记录一次的登录时间
        $rs = db('user_promotion')->where('user_id', $this->user_id)->value('once_login_time');
        if (!$rs){
            $rs = db('user_promotion')->where('user_id', $this->user_id)->update([
                'once_login_time' => request()->time()
            ]);
        }
        
        return $this->response([], 'success');
    }
    
    /**
     * 生成无上级的红包
     */
    public function no_superior_commission()
    {
        // 升级 超级会员/vip 时间
        $user_level = db('user_level')->where([
            ['user_id', '=', $this->user_id],
            ['expier_time', '>', request()->time()]
        ])->value('level');
        if (!$user_level) {
            $user_level = 1;
        }
        if ($user_level > 2) {
            return $this->response([], 'fail');
        }
        
        // 新模式上线后的登录时间
        $once_login_time = db('user_promotion')->where('user_id', $this->user_id)->value('once_login_time');
        if (!$once_login_time) {
            $once_login_time = '1543468455';
        }
        
        // 生成错过的红包（超级会员）
        $count_packet_super = 0;
        $superuser_diff_time = request()->time() - $once_login_time;
        if ($superuser_diff_time > 3600) {
            $superuser_diff_time -= 3600;
            // 生成红包个数 = ((升级超级会员时间 - 登录时间) - 3600) / (3600 * 7)
            $count_packet_super = intval($superuser_diff_time / 25200);
            // 最后一个红包时间 = ((升级超级会员时间 - 登录时间) - 3600) % (3600 * 7)
            $last_packet_time = $superuser_diff_time % 25200;
            if ($last_packet_time < 7200) {
                $count_packet_super--;
                $super_packet_working = [
                    'countdown' => ''. (7200 - $last_packet_time),
                    'create_time' => ''. ($once_login_time + 3600 + (($count_packet_super + 1) * 25200)),
                    'amount' => '19.9',
                    'time_begin' => ''. ($once_login_time + 3600 + (($count_packet_super + 1) * 25200)),
                    'time_end' => ''. ($once_login_time + 10800 + (($count_packet_super + 1) * 25200)),
                    'business' => '2'
                ];
            } else {
                $count_packet_super++;
            }
        }
        
        // 生成错过的红包（vip）
        $count_packet_vip = 0;
        $vip_diff_time = request()->time() - $once_login_time;
        if ($vip_diff_time > 3600) {
            $vip_diff_time -= 3600;
            // 生成红包个数 = ((升级超级会员时间 - 登录时间) - 3600) / (3600 * 7)
            $count_packet_vip = intval($vip_diff_time / 25200);
            // 最后一个红包时间 = ((升级超级会员时间 - 登录时间) - 3600) % (3600 * 7)
            $last_packet_time = $vip_diff_time % 25200;
            if ($last_packet_time < 7200) {
                $count_packet_vip--;
                $vip_packet_working = [
                    'countdown' => ''. (7200 - $last_packet_time),
                    'create_time' => ''. ($once_login_time + 3600 + (($count_packet_vip + 1) * 25200)),
                    'amount' => '120',
                    'time_begin' => ''. ($once_login_time + 3600 + (($count_packet_vip + 1) * 25200)),
                    'time_end' => ''. ($once_login_time + 10800 + (($count_packet_vip + 1) * 25200)),
                    'business' => '3'
                ];
            } else {
                $count_packet_vip++;
            }
        }
        
        // 错过了多少红包
        $data = [];
        if ($count_packet_super) {
            for ($i = 1; $i <= $count_packet_super; $i++) {
                $data[] = [
                    'countdown' => '0',
                    'create_time' => ''. ($once_login_time + 3600 + ($i * 25200)),
                    'amount' => '19.9',
                    'time_begin' => ''. ($once_login_time + 3600 + ($i * 25200)),
                    'time_end' => ''. ($once_login_time + 10800 + ($i * 25200)),
                    'business' => '2'
                ];
                if ($count_packet_vip >= $i) {
                    $data[] = [
                        'countdown' => '0',
                        'create_time' => ''. ($once_login_time + 3600 + ($i * 25200)),
                        'amount' => '120',
                        'time_begin' => ''. ($once_login_time + 3600 + ($i * 25200)),
                        'time_end' => ''. ($once_login_time + 10800 + ($i * 25200)),
                        'business' => '3'
                    ];
                }
            }
        }
        
        $result_data = [
            'pass_amount' => ($count_packet_super * 19.9) + ($count_packet_vip * 120),
            'pass_data' => $data
        ];
        if (isset($vip_packet_working)) {
            $result_data['vip_packet_working'] = $vip_packet_working;
        }
        if (isset($super_packet_working)) {
            $result_data['super_packet_working'] = $super_packet_working;
        }
        return $this->response([
            'result' => $result_data
        ]);
    }
}
