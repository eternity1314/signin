<?php

namespace app\home\controller;

use app\common\model\Common;
use app\common\model\Activity as ActivityModel;
use app\common\model\Order;
use app\common\model\Pay;
use app\common\model\User;
use Hooklife\ThinkphpWechat\Wechat;
use think\facade\Env;
use think\facade\Request;

class Activity extends Base
{
    public function initialize()
    {
        $this->user_id = session('user.id');$this->user_id = 163;
        if (empty($this->user_id)) {
            // 验证签名
            if (!empty(input('sign'))) {
                $this->checkSign();

                // 验证令牌
                $this->checkToken();
            }
        }

        $action_name = request()->action();
        if (empty($this->user_id) && strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            $user = \app\common\model\User::wxOauthUser();
            if ($user === false) throw new HttpResponseException(json()->content('redirect'));

            $this->user_id = $user['id'];
            $this->openid = $user['openid'];

            session('user',
                [
                    'id' => $user['id'],
                    'openid' => $user['openid'],
                    'unionid' => $user['unionid'],
                    'is_new_user' => isset($user['add_time']) ? 0 : 1
                ]
            );
        }

        Common::potential_market($this->user_id);// 潜在市场

        // deny users
        //        if (in_array($this->user_wxid, [])) {
        //            $this->error('', '请稍后...', 10011);
        //        }

        // $this->openid = session('user.openid');
        Env::set('ME', $this->user_id);
    }

    public function pull_new()
    {
        switch (request()->method()) {
            case 'POST':
                return $this->response();
                break;
            default:
                if (request()->isAjax()) {
                    $now_time = request()->time();

                    // 上周排行榜
                    $last_week_begin_time = strtotime('last week Monday', $now_time);
                    $last_week_end_time = strtotime('last week Sunday', $now_time) + 86400 - 1;
                    $last_week_user_pull_new_rank = db('active_order')->field('invite_user_id, count(1) as order_count, min(add_time) as add_time')
                        ->where([
                            ['add_time', 'BETWEEN', [$last_week_begin_time, $last_week_end_time]],
                            ['is_partner', '=', 1],
                            ['status', '<>', 3]
                        ])
                        ->group('invite_user_id')
                        ->order('order_count DESC, add_time ASC')
                        ->limit(20)
                        ->select();

                    $record = db('activity_pull_new')
                        ->where([['edit_time', 'BETWEEN', [$last_week_begin_time, $last_week_end_time]]])
                        ->order('num desc')
                        ->select();

                    foreach ($record as $v) {
                        $last_week_user_pull_new_rank[] = [
                            'invite_user_id' => $v['user_id'],
                            'order_count' => $v['num'],
                            'add_time' => $v['edit_time']
                        ];
                    }

                    array_multisort(
                        array_column($last_week_user_pull_new_rank, 'order_count'), SORT_DESC,
                        array_column($last_week_user_pull_new_rank, 'add_time'), SORT_ASC,
                        $last_week_user_pull_new_rank
                    );
                    $last_week_user_pull_new_rank = array_slice($last_week_user_pull_new_rank, 0, 20);

                    // 本周排行榜
                    $this_week_begin_time = strtotime('this week Monday', $now_time);
                    $this_week_end_time = strtotime('this week Sunday', $now_time) + 86400 - 1;
                    $this_week_user_pull_new_rank = db('active_order')->field('invite_user_id, count(1) as order_count, min(add_time) as add_time')
                        ->where([
                            ['add_time', 'BETWEEN', [$this_week_begin_time, $this_week_end_time]],
                            ['is_partner', '=', 1],
                            ['status', '<>', 3]
                        ])
                        ->group('invite_user_id')
                        ->order('order_count DESC, add_time ASC')
                        ->limit(20)
                        ->select();

                    $record = db('activity_pull_new')
                        ->where([['edit_time', 'BETWEEN', [$this_week_begin_time, $this_week_end_time]]])
                        ->order('num desc')
                        ->select();

                    foreach ($record as $v) {
                        $this_week_user_pull_new_rank[] = [
                            'invite_user_id' => $v['user_id'],
                            'order_count' => $v['num'],
                            'add_time' => $v['edit_time']
                        ];
                    }

                    array_multisort(
                        array_column($this_week_user_pull_new_rank, 'order_count'), SORT_DESC,
                        array_column($this_week_user_pull_new_rank, 'add_time'), SORT_ASC,
                        $this_week_user_pull_new_rank);
                    $this_week_user_pull_new_rank = array_slice($this_week_user_pull_new_rank, 0, 20);

                    $user_ids = [];
                    if (!empty($this_week_user_pull_new_rank)) $user_ids = array_column($this_week_user_pull_new_rank, 'invite_user_id');
                    if (!empty($last_week_user_pull_new_rank)) $user_ids = array_merge($user_ids, array_column($last_week_user_pull_new_rank, 'invite_user_id'));
                    if (!empty($user_ids)) $users = db('user')->where([['id', 'in', $user_ids]])->column('id,nickname,avator');

                    foreach ($last_week_user_pull_new_rank as &$val) {
                        $val = array_merge($val, $users[$val['invite_user_id']]);
                        $val['award'] = $val['order_count'] * 10;

                        unset($val['id']);
                        unset($val['invite_user_id']);
                        unset($val['order_count']);
                    }

                    foreach ($this_week_user_pull_new_rank as &$val) {
                        $val = array_merge($val, $users[$val['invite_user_id']]);
                        $val['award'] = $val['order_count'] * 10;

                        unset($val['id']);
                        unset($val['invite_user_id']);
                        unset($val['order_count']);
                    }

                    // 拉新活动信息
                    //$active = db('active')->where('id', '=', 1)->find();
                    $active = db('active')->where([['name', '=', 'pull_new']])->find();

                    // 已邀请
                    $invite_count = db()->query('SELECT count(*) as count FROM sl_user WHERE id IN ( SELECT user_id FROM sl_user_tier AS a WHERE pid = ' . $this->user_id . ' AND NOT EXISTS (SELECT 1 FROM sl_order WHERE add_time < ' . $active['begin_time'] . ' AND user_id = a.user_id) )')[0]['count'];

                    // 共领取
                    $all_invite_award = db('active_order')->where([
                            ['invite_user_id', '=', $this->user_id],
                            ['is_partner', '=', 1]
                        ])->count() * 10;

                    // 本周已领取
                    $week_invite_award = db('active_order')->where([
                            ['invite_user_id', '=', $this->user_id],
                            ['is_partner', '=', 1],
                            ['add_time', 'BETWEEN', [$this_week_begin_time, $this_week_end_time]]
                        ])->count() * 10;

                    // 我的排名
                    $this_week_rank = db('active_order')
                        ->where([
                            ['invite_user_id', '=', $this->user_id],
                            ['add_time', 'BETWEEN', [$this_week_begin_time, $this_week_end_time]],
                            ['is_partner', '=', 1],
                            ['status', '<>', 3]
                        ])
                        ->field('count(1) as order_count, min(add_time) as add_time')
                        ->find();

                    if ($this_week_rank['order_count'] == 0) {
                        $this_week_rank = '未上榜';
                    } else {
                        $record_rank = 0;
                        foreach ($record as $v) {
                            if ($v['num'] > $this_week_rank['order_count'] ||
                                ($v['num'] == $this_week_rank['order_count'] && $v['edit_time'] <= $this_week_rank['add_time'])
                            ) {
                                $record_rank++;
                            }
                        }

                        $sql = db('active_order')->field('count(1) as order_count, min(add_time) as add_time')
                            ->where([
                                ['add_time', 'BETWEEN', [$this_week_begin_time, $this_week_end_time]],
                                ['is_partner', '=', 1],
                                ['status', '<>', 3]
                            ])
                            ->group('invite_user_id')
                            ->buildSql();

                        $this_week_rank = db()->query('SELECT COUNT(1) AS order_count FROM ' . $sql . ' t 
                            where order_count > ' . $this_week_rank['order_count'] . ' AND add_time < ' . $this_week_rank['add_time']
                        );

                        $this_week_rank = $this_week_rank[0]['order_count'] + $record_rank + 1;
                    }

                    $data = db('user_promotion')->where('user_id', $this->user_id)->field('expire_time,invite_code')->find();
                    $expire_time = empty($data['expire_time']) ? 0 : $data['expire_time'];
                    $invite_code = empty($data['invite_code']) ? 0 : $data['invite_code'];

                    $User = new \app\common\model\User();
                    $file_poster = $User->invite_poster_create($this->user_id, true, ['invite_code' => $invite_code]);

                    $res = [
                        'partner_time' => $expire_time,
                        'wx_share' => [
                            'title' => '有好习惯就能分钱，还送免单和现金',
                            'desc' => '精致微选生活，赚赚赚！早起、阅读，免费领券还能再赚，让你拥有独立的拼多多社群商城，自用省，分享赚...',
                            'imgUrl' => config('site.url') . '/static/img/wx_share.png',
                            'link' => url('home/user/invite_page', '', '', true) . '?invite_code=' . $invite_code
                        ],
                        'link_poster' => $file_poster[3]['path'],
                        'last_week' => $last_week_user_pull_new_rank,
                        'this_week' => $this_week_user_pull_new_rank,
                        'my_award' => [
                            'this_week_rank' => $this_week_rank,
                            'invite_count' => $invite_count,
                            'week_invite_award' => $week_invite_award,
                            'all_invite_award' => $all_invite_award
                        ]
                    ];

                    // 微信分享
                    if ($_SERVER['SERVER_ADDR'] == '59.110.54.15' && Common::isWeixin()) {
                        $res['wx_config'] = Wechat::js()->getConfigArray(['onMenuShareAppMessage', 'onMenuShareTimeline']);
                    }

                    return $this->response($res);
                } else {
                    return redirect('/activity/pull_new/index.html');
                    // return view();
                }

                break;
        }

        return $this->response();
    }

    public function pull_new_info()
    {
        $active = db('active')->where('id', '=', 1)->find();

        // 已领取
        $get_award_data = db()->query('SELECT nickname, avator FROM sl_user WHERE id IN ( SELECT user_id FROM sl_active_order WHERE is_partner = 1 AND invite_user_id = ' . $this->user_id . ' )');

        // 未领取
        $not_get_award_data = db()->query('SELECT id, nickname, avator FROM sl_user WHERE id IN ( SELECT user_id FROM sl_user_tier AS a WHERE pid = ' . $this->user_id . ' AND NOT EXISTS (SELECT 1 FROM sl_order WHERE add_time < ' . $active['begin_time'] . ' AND user_id = a.user_id) )');
        foreach ($not_get_award_data as $key => &$val) {
            $active_info = db('active_order')->where([
                ['user_id', '=', $val['id']],
                ['status', 'in', [1, 2, 4, 5, 6]],
                ['is_partner', '=', 1]
            ])->find();
            if ($active_info) {
                unset($not_get_award_data[$key]);
                continue;
            }

            $active_info = db('active_order')->where([
                ['user_id', '=', $val['id']],
                ['status', 'in', [1, 2, 4, 5, 6]]
            ])->find();

            if ($active_info) {
                $val['status_desc'] = '已消费';
            } else {
                $val['status_desc'] = '未消费';
            }
        }

        return view('', [
            'result' => [
                'not_get_award_data' => $not_get_award_data,
                'get_award_data' => $get_award_data,
                'not_get_award' => count($not_get_award_data) * 10,
                'get_award' => count($get_award_data) * 10
            ]
        ]);
    }

    public function pick_new_app()
    {
        if (Common::isWeixin()) {
            return redirect(url('home/activity/pick_new_wx', '', '', true));
        }

        $total_price = 1000;
        $open_price = ActivityModel::pick_new_receive($this->user_id);
        $surplus_price = $total_price - ($open_price % $total_price);
        $withdraw_price = db('activity_pick_new_account')
            ->where([['user_id', '=', $this->user_id], ['withdraw_time', '>', 0]])
            ->sum('price');
        if (empty($withdraw_price)) $withdraw_price = 0;

        $time = request()->time();
        $cash = $cash_record = [];
        $cash_total = 0;

        $active = db('active')->where([
            ['name', '=', 'upgrade_cash'],
            ['begin_time', '<=', $time],
            ['end_time', '>', $time]
        ])->find();

        if (empty($active)) { // 活动结束
            $user_level = 4;
        } else {
            $level = db('user_level')
                ->where([
                    ['user_id', '=', $this->user_id],
                    ['expier_time', '>', $time]
                ])
                ->find();

            $user_level = $level['level'];

            $cash_record = db('activity_pick_new_cash')
                ->where([['user_id', '=', $this->user_id], ['status', '<>', 2]])// ['time_begin', '<=', $time]
                ->order('time_begin desc')
                ->select();

            if (empty($level) || $user_level < 3) {
                $data = [];
                $time_handle = empty($cash_record) ? 0 : $cash_record[0]['time_begin'];
                $time_limit = mt_rand(23400, 27000); // 3600 * 7;

                while ($time_handle == 0 || $time - $time_handle >= $time_limit) { // 生成待领现金
                    $time_handle = $time_handle == 0 ? $time : $time_handle + $time_limit;
                    $time_limit = mt_rand(23400, 27000);

                    $res = [
                        'user_id' => $this->user_id,
                        'price' => mt_rand(20, 80) * 100,
                        'add_time' => $time,
                        'time_begin' => $time_handle,
                        'time_end' => $time_handle + 7200,
                        'status' => 0
                    ];

                    $data[] = $res;
                    array_unshift($cash_record, $res);
                }

                if ($data) db('activity_pick_new_cash')->insertAll($data);
            }

            foreach ($cash_record as $k => $v) {
                if ($v['time_begin'] > $time) { // 未到时间删除
                    $cash_record = array_slice($cash_record, 1);
                    continue;
                }

                $cash_total += $v['price'];
            }

            if (!empty($cash_record) && $time < $cash_record[0]['time_end']) {
                $cash = $cash_record[0];
                $cash['countdown'] = gmstrftime('%H:%M:%S', $cash['time_end'] - $time);
                $cash_total -= $cash['price'];

                unset($cash_record[0]);
            }
        }

        $account = db('activity_pick_new_account')
            ->where([['user_id', '=', $this->user_id], ['withdraw_time', '=', 0]])
            ->sum('price');
        if (empty($account)) {
            $account = 0;
        } else {
            db('activity_pick_new_account')
                ->where([['user_id', '=', $this->user_id], ['withdraw_time', '=', 0]])
                ->update(['withdraw_time' => $time]);

            User::money($this->user_id, $account, 'pick_new_open', Common::today_date(), '好友帮拆红包');
        }

        if (empty($this->invite_code)) {
            $this->invite_code = db('user_promotion')
                ->where('user_id', $this->user_id)
                ->value('invite_code');
        }

        return view('', [
            'open_price' => $open_price,
            'surplus_price' => $surplus_price,
            'withdraw_price' => $withdraw_price,
            'withdraw' => ActivityModel::pick_new_withdraw_rand(),
            'open_record' => ActivityModel::pick_new_open_record($this->user_id),
            'account' => $account,
            'user_level' => $user_level,
            'cash' => $cash,
            'cash_record' => $cash_record,
            'cash_total' => $cash_total,
            'active' => $active,
            'wx_share' => [
                'title' => '帮我拆一下，就能和我一起领现金',
                'desc' => '天天领现金，每天至少领10元',
                'imgUrl' => config('site.url') . '/static/redpack/img/icon-share.jpg',
                'link' => url('pick_new_wx', '', '', true)
                    . '?from_uid=' . $this->user_id
                    . '&invite_code=' . $this->invite_code,
                'dialog_title' => '还差' . sprintf('%.2f', $surplus_price / 100) . '元可提现，发给好友继续拆'
            ]
        ]);
    }

    public function pick_new_wx()
    {
        $time = request()->time();
        $from_uid = input('from_uid', 0, 'intval');

        $total_price = 1000;
        $data = [];

        if ($from_uid > 0 && $from_uid != $this->user_id) { // 帮拆记录
            $user = User::find($from_uid);
            if (empty($user)) return 'user empty';

            $open_price = db('activity_pick_new')
                ->where([['from_uid', '=', $from_uid]])
                ->sum('open_price');

            if (empty($open_price)) return 'user not join in';

            $status = 2;
            if (session('user.is_new_user')) { // 新用户
                $res = db('activity_pick_new')
                    ->where([['user_id', '=', $this->user_id]])
                    ->order('status desc')
                    ->find();

                if (empty($res)) {
                    $status = 1;
                }
            }

            db('activity_pick_new')->insert([
                'user_id' => $this->user_id,
                'from_uid' => $from_uid,
                'status' => $status,
                'add_time' => $time,
            ]);

            $data = [
                'event' => 'open',
                'open' => [
                    'nickname' => $user['nickname'],
                    'avator' => $user['avator'],
                    'open_price' => $open_price,
                    'surplus_price' => $total_price - ($open_price % $total_price),
                ]
            ];
        }

        $user = User::find($this->user_id);
        $open_price = ActivityModel::pick_new_receive($this->user_id);

        $data['withdraw'] = ActivityModel::pick_new_withdraw_rand();
        $data['open_record'] = ActivityModel::pick_new_open_record($this->user_id);

//        $data['account'] = db('activity_pick_new_account')
//            ->where([['user_id', '=', $this->user_id], ['withdraw_time', '=', 0]])
//            ->sum('price');
//        if (empty($data['account'])) $data['account'] = 0;

        $data['receive'] = [
            'nickname' => $user['nickname'],
            'avator' => $user['avator'],
            'open_price' => $open_price,
            'surplus_price' => $total_price - ($open_price % $total_price)
        ];

        if (empty($this->invite_code)) {
            $this->invite_code = db('user_promotion')
                ->where('user_id', $this->user_id)
                ->value('invite_code');
        }

        $data['wx_share'] = [
            'title' => '帮我拆一下，就能和我一起领现金',
            'desc' => '天天领现金，每天至少领10元',
            'imgUrl' => config('site.url') . '/static/redpack/img/icon-share.jpg',
            'link' => url('', '', '', true)
                . '?from_uid=' . $this->user_id
                . '&invite_code=' . $this->invite_code
        ];

        // 微信分享
        if ($_SERVER['SERVER_ADDR'] == '59.110.54.15' && Common::isWeixin()) {
            $data['wx_config'] = Wechat::js()->getConfigArray(['onMenuShareAppMessage', 'onMenuShareTimeline']);
        }

        return view('', $data);
    }

    public function pick_new_bag_app()
    {
        if (Common::isWeixin()) {
            return redirect(url('home/activity/pick_new_bag_wx', '', '', true));
        }

        $bag = ActivityModel::pick_new_bag($this->user_id, true);

        $total_price = $bag['price'];
        $open_price = ActivityModel::pick_new_bag_open_price($this->user_id, $bag['id']);

        $withdraw_price = db('activity_pick_new_bag_account')
            ->where([['user_id', '=', $this->user_id], ['withdraw_time', '>', 0]])
            ->sum('price');
        if (empty($withdraw_price)) $withdraw_price = 0;

        $time = request()->time();
        $cash = $cash_record = [];
        $cash_total = 0;

        $active = db('active')->where([
            ['name', '=', 'upgrade_cash'],
            ['begin_time', '<=', $time],
            ['end_time', '>', $time]
        ])->find();

        if (empty($active)) { // 活动结束
            $user_level = 4;
        } else {
            $level = db('user_level')
                ->where([
                    ['user_id', '=', $this->user_id],
                    ['expier_time', '>', $time]
                ])
                ->find();

            $user_level = $level['level'];

            $cash_record = db('activity_pick_new_cash')
                ->where([['user_id', '=', $this->user_id], ['status', '<>', 2]])// ['time_begin', '<=', $time]
                ->order('time_begin desc')
                ->select();

            if (empty($level) || $user_level < 3) {
                $data = [];
                $time_handle = empty($cash_record) ? 0 : $cash_record[0]['time_begin'];
                $time_limit = mt_rand(23400, 27000); // 3600 * 7;

                while ($time_handle == 0 || $time - $time_handle >= $time_limit) { // 生成待领现金
                    $time_handle = $time_handle == 0 ? $time : $time_handle + $time_limit;
                    $time_limit = mt_rand(23400, 27000);

                    $res = [
                        'user_id' => $this->user_id,
                        'price' => mt_rand(40, 80) * 100,
                        'add_time' => $time,
                        'time_begin' => $time_handle,
                        'time_end' => $time_handle + 7200,
                        'status' => 0
                    ];

                    $data[] = $res;
                    array_unshift($cash_record, $res);
                }

                if ($data) db('activity_pick_new_cash')->insertAll($data);
            }

            foreach ($cash_record as $k => $v) {
                if ($v['time_begin'] > $time) { // 未到时间删除
                    $cash_record = array_slice($cash_record, 1);
                    continue;
                }

                $cash_total += $v['price'];
            }

            if (!empty($cash_record) && $time < $cash_record[0]['time_end']) {
                $cash = $cash_record[0];
                $cash['countdown'] = gmstrftime('%H:%M:%S', $cash['time_end'] - $time);
                $cash_total -= $cash['price'];

                unset($cash_record[0]);
            }
        }

        $account = db('activity_pick_new_bag_account')
            ->where([['user_id', '=', $this->user_id], ['withdraw_time', '=', 0]])
            ->sum('price');
        if (empty($account)) $account = 0;

//        else {
//            db('activity_pick_new_bag_account')
//                ->where([['user_id', '=', $this->user_id], ['withdraw_time', '=', 0]])
//                ->update(['withdraw_time' => $time]);
//
//            User::money($this->user_id, $account, 'pick_new_open', Common::today_date(), '好友帮拆红包');
//        }

        // 拆红包翻10倍提示
        $where = [['bag_id', '=', $bag['id']], ['is_award', '=', 2], ['open_time', '>', 0]];
        $award = db('activity_pick_new_bag_open')
            ->where($where)
            ->order('open_time desc,id desc')->value('user_id');
        if ($award) {
            if ($account == 0) {
                db('activity_pick_new_bag_open')->where($where)->update(['is_award' => 3]);
            }

            $award = User::find($award);
            if ($award) {
                $award = ['nickname' => $award['nickname']];
            }
        }

        $surplus_price = $total_price > $open_price ? $total_price - $open_price : 0;
        if (empty($this->invite_code)) {
            $this->invite_code = db('user_promotion')
                ->where('user_id', $this->user_id)
                ->value('invite_code');
        }

        return view('', [
            'total_price' => $total_price,
            'open_price' => $open_price,
            'open_percent' => $total_price > $open_price ? round($open_price / $total_price * 100, 2) : 100,
            'surplus_price' => $surplus_price,
            'withdraw_price' => $withdraw_price,
            'withdraw' => ActivityModel::pick_new_withdraw_rand(),
            'open_record' => ActivityModel::pick_new_bag_open_record($this->user_id, $bag['id']),
            'account' => $account,
            'user_level' => $user_level,
            'cash' => $cash,
            'cash_record' => $cash_record,
            'cash_total' => $cash_total,
            'active' => $active,
            'award' => $award,
            'wx_share' => [
                'title' => '帮我拆一下，就能和我一起领现金',
                'desc' => '天天领现金，每天至少领10元',
                'imgUrl' => config('site.url') . '/static/redpack/img/icon-share.jpg',
                'link' => url('pick_new_bag_wx', '', '', true)
                    // . '?from_uid=' . $this->user_id
                    . '?bag_id=' . $bag['id']
                    . '&invite_code=' . $this->invite_code,
                'dialog_title' => '还差' . sprintf('%.2f', $surplus_price / 100) . '元可提现，发给好友继续拆'
            ]
        ]);
    }

    public function pick_new_bag_wx()
    {
        $from_uid = 0; // input('from_uid', 0, 'intval');
        $bag_id = input('bag_id', 0, 'intval');
        if (!empty($bag_id)) {
            $bag = db('activity_pick_new_bag')
                ->where([['id', '=', $bag_id]])
                //->where([['user_id', '=', $from_uid], ['end_time', '=', 0]])
                ->find();

            if (empty($bag)) return 'user not join in'; // empty($bag_id)
            // if ($bag['end_time'] > 0) return 'game over';
            if ($bag['end_time'] == 0) $from_uid = $bag['user_id'];
        }

        $user = User::find($this->user_id);
        $is_new = session('user.is_new_user'); // 新用户
        if (!$is_new) {
            $user = User::find($this->user_id);
            $is_new = empty($user) || empty($user['openid_app']); // 新APP用户
        }

        $data = ['is_new' => $is_new, 'is_end' => !empty($bag) && $bag['end_time'] > 1 && $from_uid != $this->user_id];
        if ($from_uid > 0 && $from_uid != $this->user_id) { // 帮拆记录
            $status = 2;
            $is_award = 0;

            if ($is_new) {
                $count = db('activity_pick_new_bag_open')
                    ->where([['user_id', '=', $this->user_id]])
                    ->count(1);

                if ($count == 0) {
                    $status = 1;
                    $is_award = 1;
                }
            }

            db('activity_pick_new_bag_open')->insert([
                'user_id' => $this->user_id,
                'from_uid' => $from_uid,
                'bag_id' => $bag_id,
                'add_time' => request()->time(),
                'status' => $status,
                'is_award' => $is_award
            ]);

            $user_from = User::find($from_uid);
            if (empty($user_from)) return 'user empty';

            $total_price = $bag['price'];
            $open_price = ActivityModel::pick_new_bag_open_price($from_uid, $bag['id']);

            $data['open'] = [
                'user_id' => $from_uid,
                'nickname' => $user_from['nickname'],
                'avator' => $user_from['avator'],
                'open_price' => $open_price,
                'open_percent' => $total_price > $open_price ? round($open_price / $total_price * 100, 2) : 100,
                'surplus_price' => $total_price > $open_price ? $total_price - $open_price : 0,
                'open_range_rand' => mt_rand(8, 12),
                'receive_range_rand' => mt_rand(40, 80),
            ];

            // 查看提现正在使用的公众号
            $qrcode = db('user_withdraw_tencent')->where('status', 1)->value('qrcode');
            if ($qrcode) {
                $count = db('user_withdraw_tencent_relation')
                    ->where([['user_id', '=', $this->user_id]])
                    ->count(1);

                $data['wx_info'] = ['qrcode' => $qrcode, 'subscribe' => $count];
            }
        }

        $bag = ActivityModel::pick_new_bag($this->user_id);
        $total_price = $bag['price'];
        if ($bag['end_time'] == 0) {
            $open_price = ActivityModel::pick_new_bag_open_price($this->user_id, $bag['id']);
        } else {
            $open_price = $bag['price_me'];
        }

        // $data['withdraw'] = ActivityModel::pick_new_withdraw_rand();
        $data['open_record'] = ActivityModel::pick_new_bag_open_record($this->user_id, $bag['id']);

//        $data['account'] = db('activity_pick_new_bag_account')
//            ->where([['user_id', '=', $this->user_id], ['withdraw_time', '=', 0]])
//            ->sum('price');
//        if (empty($data['account'])) $data['account'] = 0;

        $data['receive'] = [
            'nickname' => $user['nickname'],
            'avator' => $user['avator'],
            'level' => User::level($this->user_id),
            'open_price' => $open_price,
            'open_percent' => $total_price > $open_price ? round($open_price / $total_price * 100, 2) : 100,
            'surplus_price' => $total_price > $open_price ? $total_price - $open_price : 0,
        ];

        if (empty($this->invite_code)) {
            $this->invite_code = db('user_promotion')
                ->where('user_id', $this->user_id)
                ->value('invite_code');
        }

        $data['wx_share'] = [
            'title' => '帮我拆一下，就能和我一起领现金',
            'desc' => '天天领现金，每天至少领10元',
            'imgUrl' => config('site.url') . '/static/redpack/img/icon-share.jpg',
            'link' => url('', '', '', true)
                // . '?from_uid=' . $this->user_id
                . '?bag_id=' . $bag['id']
                . '&invite_code=' . $this->invite_code
        ];

        // 微信分享
        if ($_SERVER['SERVER_ADDR'] == '59.110.54.15' && Common::isWeixin()) {
            $data['wx_config'] = Wechat::js()->getConfigArray(['onMenuShareAppMessage', 'onMenuShareTimeline']);
        }

        return view('', $data);
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

    public function pick_new_bag_withdraw()
    {
        switch (request()->method()) {
            case 'POST':
                $account = db('activity_pick_new_bag_account')
                    ->where([['user_id', '=', $this->user_id], ['withdraw_time', '=', 0]])
                    ->sum('price');

                if (empty($account)) $this->response('', 'data empty', 1);

                $time = request()->time();
                db('activity_pick_new_bag_account')
                    ->where([['user_id', '=', $this->user_id], ['withdraw_time', '=', 0]])
                    ->update(['withdraw_time' => $time]);

                db('activity_pick_new_bag')
                    ->where([['user_id', '=', $this->user_id], ['end_time', '=', 0]])
                    ->update(['end_time' => $time]);

                db('activity_pick_new_bag_open')
                    ->where([['from_uid', '=', $this->user_id]])
                    ->update(['status' => 0]);

                User::money($this->user_id, $account, 'pick_new_open', Common::today_date(), '好友帮拆红包');
                break;
            default:
                break;
        }

        return $this->response();
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

    public function pick_new_goods()
    {
        switch (request()->method()) {
            case 'POST':
                $paid = 10;
                $res = Pay::pay($paid, ['openid' => $this->openid], []);

                if (empty($res['room_id'])) {
                    return call_user_func_array([$this, 'response'], $res);
                }

                return $this->response($res);
                break;
            default:
                break;
        }

        return $this->response();
    }

    public function po_buy_goods()
    {
        $data = ['withdraw' => ActivityModel::pick_new_withdraw_rand()];
        return view('', $data);
    }

    public function po_buy_goods_detail()
    {
        $id = request()->get('id');
        $detail = db('actvity_goods')->where('id', '=', $id)->find();
        $detail['price'] = round($detail['price'] / 100, 2);

        // 图文详情

        $goods_content = Common::curl(config('site.url') . url('api/goods/good_content') . '?type=1&goods_id=' . $detail['goods_id']);
        $goods_content = json_decode($goods_content, true);

        $data = [
            'detail' => $detail,
            'goods_content' => $goods_content['data']['images'],
            'withdraw' => ActivityModel::pick_new_withdraw_rand()
        ];

        return view('', $data);
    }

    public function point_one_buy_join()
    {
        $para = input('post.');
        $para['out_trade_no'] = Order::order_sn_create(); // 订单号

        $res = ActivityModel::point_one_buy_join($this->user_id, $para);

        return call_user_func_array([$this, 'response'], $res);
    }

    public function po_buy_goods_buying()
    {
        $id = input('id', 0);
        if (empty($id)) return 'param missing';

        if ($id == 'new') {
            $join = db('activity_point_one_join')->where('user_id', '=', $this->user_id)->order('id desc')->find();
        } else {
            $join = db('activity_point_one_join')->where([['id', '=', $id]])->find();
        }
        if (empty($join)) return '参数错误';
        $join['price'] = round($join['price'] / 100, 2);

        // 成功率相同
        switch ($join['success_rate']) {
            case $join['success_rate'] >= 10 && $join['success_rate'] < 20:
                $join['same_count'] = substr($join['add_time'], -1) + 80;
                break;
            case $join['success_rate'] >= 20 && $join['success_rate'] < 30:
                $join['same_count'] = substr($join['add_time'], -1) + 70;
                break;
            case $join['success_rate'] >= 40 && $join['success_rate'] < 50:
                $join['same_count'] = substr($join['add_time'], -1) + 60;
                break;
            case $join['success_rate'] >= 50 && $join['success_rate'] < 60:
                $join['same_count'] = substr($join['add_time'], -1) + 50;
                break;
            case $join['success_rate'] >= 60 && $join['success_rate'] < 70:
                $join['same_count'] = substr($join['add_time'], -1) + 40;
                break;
            default:
                $join['same_count'] = substr($join['add_time'], -1) + 30;
                break;
        }

        if (empty($join)) return 'data empty';

        $data = ['join' => $join];

        $help = db('activity_point_one_help')
            ->where([['po_join_id', '=', $id]])
            ->order('id asc')
            ->select();

        if (!empty($help)) {
            $users = db('user')
                ->where([['id', 'in', array_column($help, 'from_user_id')]])
                ->column('id,nickname,avator');

            foreach ($help as $k => $v) {
                if (!isset($users[$v['from_user_id']])) {
                    $users[$v['from_user_id']] = ['nickname' => '匿名', 'avator' => '/static/img/head.png'];
                }

                $v = array_merge($v, $users[$v['from_user_id']]);
                $help[$k] = $v;
            }
        }

        $data['help'] = $help;
        $data['help_up_rate'] = array_sum(array_column($help, 'up_rate'));
        $data['withdraw'] = ActivityModel::pick_new_withdraw_rand();
        $data['user_level'] = User::level($this->user_id);

        // 翻10倍提示
        $where = [['po_join_id', '=', $id], ['is_award', '=', 2]];
        $award = db('activity_point_one_help')
            ->where($where)
            ->order('add_time desc,id desc')
            ->value('from_user_id');
        if ($award) {
            db('activity_point_one_help')->where($where)->update(['is_award' => 3]);

            $award = User::find($award);
            if ($award) {
                $award = ['nickname' => $award['nickname']];
            }
        }
        $data['award'] = $award;

        if (empty($this->invite_code)) {
            $this->invite_code = db('user_promotion')
                ->where('user_id', $this->user_id)
                ->value('invite_code');
        }

        $data['wx_share'] = [
            //'method' => 'point1FriendHelpShare',
            'title' => '求助攻，能不能抢到就看你了',
            'desc' => '微选生活官方回馈用户的福利，全场商品0.1元疯狂抢',
            'imgUrl' => $join['goods_img'], // config('site.url') . '/static/redpack/img/icon-share.jpg',
            'link' => url('po_buy_goods_help_wx', '', '', true)
                . '?id=' . $join['id']
                . '&invite_code=' . $this->invite_code
        ];

        if ($join['is_share']) {
            $data['wx_share']['hint_title'] = '好友还有红包拿哦';
            $data['wx_share']['dialog_title'] = '求助好友，帮忙提升成功率';
        } else {
            $data['wx_share']['hint_title'] = '只差最后1步';
            $data['wx_share']['dialog_title'] = '分享到微信群才能开始抢购';
        }

        return view('', $data);
    }

    public function po_buy_goods_help_wx()
    {
        $po_join_id = input('id', 0, 'intval');
        $rule_data = ActivityModel::point_one_buy_help_rule($this->user_id, $po_join_id);
        if ($rule_data[2] == 40001) {
            return redirect('po_buy_goods_buying', ['id' => $po_join_id]);
        }

        $is_award = 0;
        $is_new = session('user.is_new_user'); // 新用户
        if ($is_new) { // 新用户
            $count = db('activity_point_one_help')
                ->where([['from_user_id', '=', $this->user_id]])
                ->count(1);

            if ($count == 0) {
                $is_award = 1;
            }
        } else {
            $user = User::find($this->user_id);
            $is_new = empty($user) || empty($user['openid_app']); // 新APP用户
        }

        $data = [
            'rule_txt' => '',
            'is_award' => $is_award,
            'po_join_data' => $rule_data[0]['po_join_data'],
            'withdraw' => ActivityModel::pick_new_withdraw_rand(),
            'is_new' => $is_new,
            'open_range_rand' => mt_rand(8, 12),
            'receive_range_rand' => mt_rand(40, 80)
        ];

        // 查看提现正在使用的公众号
        $qrcode = db('user_withdraw_tencent')->where('status', 1)->value('qrcode');
        if ($qrcode) {
            $count = db('user_withdraw_tencent_relation')
                ->where([['user_id', '=', $this->user_id]])
                ->count(1);

            $data['wx_info'] = ['qrcode' => $qrcode, 'subscribe' => $count];
        }

        $data['is_app_user'] = db('app_token')->where([['user_id', '=', $this->user_id]])->count(1);

        return view('', $data);
    }

    public function po_buy_help()
    {
        $po_join_id = input('id', 0, 'intval');
        if (!$po_join_id) {
            return $this->response([], '参数错误', 40000);
        }
        $rs = ActivityModel::point_one_buy_help($this->user_id, $po_join_id);

        return call_user_func_array([$this, 'response'], $rs);
    }

    public function point_one_buy_act()
    {
        $id = input('id', 0, 'intval');
        if (empty($id)) return 'param missing';

        switch (request()->method()) {
            case 'POST':
                $res = ActivityModel::point_one_buy_act($this->user_id, $id);
                return call_user_func_array([$this, 'response'], $res);
                break;
            default:
                break;
        }

        return $this->response();
    }

    public function po_buy_share()
    {
        $id = input('id', 0, 'intval');
        if (empty($id)) return 'param missing' . $id;

        switch (request()->method()) {
            case 'POST':
                db('activity_point_one_join')
                    ->where([['id', '=', $id]])
                    ->update(['is_share' => 1]);

                return $this->response();
                break;
            default:
                break;
        }

        return $this->response();
    }

    public function po_buy_return_cash()
    {
        $po_join_id = request()->post('id');
        if (!$po_join_id) {
            return $this->response([], '参数错误', 40000);
        }

        $rs = ActivityModel::point_one_buy_return_cash($this->user_id, $po_join_id);

        return call_user_func_array([$this, 'response'], $rs);
    }

    public function po_buy_order()
    {
        $order_data = db('activity_point_one_join')->field('goods_name, price, goods_img, status')->where([
            ['user_id', '=', $this->user_id],
            ['status', 'in', [3, 4, 5]]
        ])->order('id desc')->select();
        foreach ($order_data as &$val) {
            switch ($val['status']) {
                case 3:
                    $val['status_desc'] = '待返还: ￥' . round($val['price'] / 100, 2);
                    $val['status_info'] = '确认收货后返还你' . round($val['price'] / 100, 2) . '元现金';
                    break;
                case 4:
                    $val['status_desc'] = '已兑换: ￥' . round($val['price'] * 0.007, 2);
                    $val['status_info'] = '已返还' . round($val['price'] * 0.007, 2) . '元现金，请到账户中查看';
                    break;
                default:
                    $val['status_desc'] = '已返还: ￥' . round($val['price'] / 100, 2);
                    $val['status_info'] = '已将商品兑换成现金，请到账户中查看';
                    break;
            }
        }

        return view('', ['order_data' => $order_data]);
    }

    public function po_buy_goods_data()
    {
        $page = input('page', 1);
        $page_size = input('page_size', 60);

        // 所有抢购商品
        $goods_data = db('actvity_goods')->alias('a')->orderRaw('if(cid = 4, 0, 2), if(cid = 1, 0, 1), id asc')->field('id, goods_name, goods_img, price, snap_up_count, goods_id, 1 as type');

        // 用户抢购中商品
        if ($this->user_id) {
            $goods_data->where('id', 'NOT EXISTS', function ($query) {
                $query->name('activity_point_one_join')->where([
                    ['status', 'in', [0, 2]],
                    ['user_id', '=', $this->user_id]
                ])->where('goods_id = a.goods_id')->field('id');
            });

            if ($page == 1) {
                $goods_buying = db('activity_point_one_join')->field('id, goods_name, goods_img, price, snap_up_count, goods_id, 2 as type')
                    ->where([
                        ['user_id', '=', $this->user_id],
                        ['status', 'in', [0, 2]]
                    ])
                    ->select();
            }
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