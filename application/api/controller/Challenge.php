<?php

namespace app\api\controller;

use app\common\model\Challenge as ChallengeModel;
use app\common\model\Common;
use think\Db;

class Challenge extends Base
{
    protected function initialize()
    {
        $this->check_sign = ['signin'];
        parent::initialize();
    }

    public function today_info()
    {
        $data_out = [
//            'type' => 1,
//            'title' => '今日早起族结算通知',
//            'data' => [
//                [
//                    'room_id' => 1,
//                    'price_win' => 12356.78,
//                    'price_lose' => 567.89,
//                    'title' => '广州早起族',
//                    'day' => 3
//                ], [
//                    'room_id' => 2,
//                    'price_win' => 12356.78,
//                    'price_lose' => 567.89,
//                    'title' => '深圳早起族',
//                    'day' => 7
//                ], [
//                    'room_id' => 3,
//                    'price_win' => 12356.78,
//                    'price_lose' => 567.89,
//                    'title' => '北京早起族',
//                    'day' => 21
//                ], [
//                    'room_id' => 4,
//                    'price_win' => 12356.78,
//                    'price_lose' => 567.89,
//                    'title' => '上海早起族',
//                    'day' => 60
//                ]
//            ]
        ];

        return $this->response($data_out);
    }

    public function today_me()
    {
        $today_time = Common::today_time();
        $today_date = Common::today_date();
        $time = request()->time();

        if ($today_time <= $time && $time < $today_time + 8 * 60 * 60) {
            $income = '待结算中';
        } elseif (($today_time + 8 * 60 * 60 <= $time && $time < $today_time + 11 * 60 * 60) || !Common::is_challenge_stat()) {
            $income = '结算中...';
        } else {
            $today_time = Common::today_time();
            $income = db('user_money')->where([
                ['user_id', '=', $this->user_id],
                ['add_time', 'between', [$today_time, $today_time + 86400]],
                ['event', 'in', ['challenge_income_both', 'challenge_income_room', 'leader_income']]
            ])->sum('money');

            $income = '￥' . sprintf('%.2f', $income);
        }

        $count_room = db('challenge')->where([
            ['user_id', '=', $this->user_id],
            ['status', '=', 1],
            ['expire_date', '<=', $today_date],
            ['room_id', '>', 0]
        ])->count(1);

        $count_both = db('challenge')->where([
            ['user_id', '=', $this->user_id],
            ['status', '=', 1],
            ['expire_date', '<=', $today_date],
            ['room_id', '=', 0]
        ])->count(1);

        $day = ChallengeModel::challenge_day($this->user_id);

        $data_out = [
            'title' => '今日共获得早起奖励',
            'income' => $income,
            'count_room' => $count_room,
            'count_both' => $count_both,
//            'challenge_day' => $day,
            'challenge_tips' => '你已早起' . $day . '天了哦',
        ];

        return $this->response($data_out);
    }

    public function room_hot()
    {
        $limit = 3;
        $time = request()->time();
        $where = 'recommend_time > ' . $time; // . ' AND user_id <> ' . $this->user_id;
        $count = db('challenge_room')->where($where)->count(1);
        $table = db('challenge_room')->getTable();
        $data = [];
        $data_count = 0;
        $data_id = [];

        if ($count > 0) {
            if ($count > $limit) {
                $join_sql = [];
                $join_rand = [];

                do {
                    $rand = mt_rand(0, $count - 1);
                    if (!in_array($rand, $join_rand)) {
                        $join_sql[] = "(SELECT room_id,avator,title,day,btime,etime,income_rate FROM `{$table}` WHERE {$where} LIMIT {$rand},1)";

                        $join_rand[] = $rand;
                        ++$data_count;
                    }
                } while ($data_count < $limit);

                $data = db()->query(implode(' UNION ALL ', $join_sql));
            } else {
                $data = db('challenge_room')->where($where)->field('room_id,avator,title,day,btime,etime,income_rate')->select();
                $data_count = $count;
            }

            $data_id = array_column($data, 'room_id');
        }

        if ($count < $limit) {
            if (!empty($this->app_token) && !empty($this->app_token['city'])) {
                $city = $this->app_token['city'];
            } else {
                $city = ChallengeModel::room_option('city');
                $city = $city[mt_rand(0, count($city) - 1)];
            }

            $where = "city = '{$city}' AND expire_time > $time"; // AND user_id <> " . $this->user_id;
            if (!empty($data_id)) $where .= ' AND room_id NOT IN(' . implode(',', $data_id) . ')';
            $count = db('challenge_room')->where($where)->count(1);

            if ($count > 0) {
                if ($count > $limit - $data_count) {
                    $join_sql = [];
                    $join_rand = [];

                    do {
                        $rand = mt_rand(0, $count - 1);
                        if (!in_array($rand, $join_rand)) {
                            $join_sql[] = "(SELECT room_id,avator,title,day,btime,etime,income_rate FROM `{$table}` WHERE {$where} LIMIT {$rand},1)";

                            $join_rand[] = $rand;
                            ++$data_count;
                        }
                    } while ($data_count < $limit);

                    $data = array_merge($data, db()->query(implode(' UNION ALL ', $join_sql)));
                } else {
                    $data = array_merge($data, db('challenge_room')->where($where)->field('room_id,avator,title,day,btime,etime,income_rate')->select());
                    $data_count += $count;
                }
            }

            $data_id = array_column($data, 'room_id');
        }

        if ($count < $limit) {
            // $where = 'user_id = 0';
            $where = \app\common\model\User::system_user_filter();
            if (!empty($data_id)) $where .= ' AND room_id NOT IN(' . implode(',', $data_id) . ')';
            $count = db('challenge_room')->where($where)->count(1);

            if ($count > 0) {
                if ($count > $limit - $data_count) {
                    $join_sql = [];
                    $join_rand = [];

                    do {
                        $rand = mt_rand(0, $count - 1);
                        if (!in_array($rand, $join_rand)) {
                            $join_sql[] = "(SELECT room_id,avator,title,day,btime,etime,income_rate FROM `{$table}` WHERE {$where} LIMIT {$rand},1)";

                            $join_rand[] = $rand;
                            ++$data_count;
                        }
                    } while ($data_count < $limit);

                    $data = array_merge($data, db()->query(implode(' UNION ALL ', $join_sql)));
                } else {
                    $data = array_merge($data, db('challenge_room')->where($where)->field('room_id,avator,title,day,btime,etime,income_rate')->select());
                    // $data_count += $count;
                }
            }
        }

        foreach ($data as $key => $item) {
            $item['income_rate'] = sprintf('%.3f', $item['income_rate']);
            $item['challenge_time'] = ChallengeModel::convert_signin_time($item)['stime'];

            if (strpos($item['avator'], 'http://') === false) $item['avator'] = config('site.url') . $item['avator'];

            unset($item['btime']);
            unset($item['etime']);

            $data[$key] = $item;
        }

//        $data_out = [
//            [
//                'title' => '广州早起族',
//                'avator' => 'http://wx.qlogo.cn/mmopen/O19kUBfHXBPywYTxzcphW3AiaDHVY8sf8Tm2iaVoTLo9iaYcvKu5xqicL9MysbokXHQTQEp9IMT67vJgiad8GrtBrAJ060eMkR7mN/0',
//                'day' => 3,
//                'challenge_time' => '08:00~09:00',
//                'income_rate' => '50'
//            ], [
//                'title' => '深圳早起族',
//                'avator' => 'http://wx.qlogo.cn/mmopen/O19kUBfHXBPywYTxzcphW3AiaDHVY8sf8Tm2iaVoTLo9iaYcvKu5xqicL9MysbokXHQTQEp9IMT67vJgiad8GrtBrAJ060eMkR7mN/0',
//                'day' => 3,
//                'challenge_time' => '08:00~09:00',
//                'income_rate' => '50'
//            ], [
//                'title' => '上海早起族',
//                'avator' => 'http://wx.qlogo.cn/mmopen/O19kUBfHXBPywYTxzcphW3AiaDHVY8sf8Tm2iaVoTLo9iaYcvKu5xqicL9MysbokXHQTQEp9IMT67vJgiad8GrtBrAJ060eMkR7mN/0',
//                'day' => 3,
//                'challenge_time' => '08:00~09:00',
//                'income_rate' => '50'
//            ], [
//                'title' => '北京早起族',
//                'avator' => 'http://wx.qlogo.cn/mmopen/O19kUBfHXBPywYTxzcphW3AiaDHVY8sf8Tm2iaVoTLo9iaYcvKu5xqicL9MysbokXHQTQEp9IMT67vJgiad8GrtBrAJ060eMkR7mN/0',
//                'day' => 3,
//                'challenge_time' => '08:00~09:00',
//                'income_rate' => '50'
//            ]
//        ];

        return $this->response(['data' => $data]);
    }

    public function friend_launch()
    {
//        $limit = 3;
//        $data = [];
//        $data_count = 0;
//        $data_id = [];
//        $table = db('user_friend')->getTable();
//        $where = 'f.user_id =' . $this->user_id;
//        $today_date = Common::today_date();
//        $join = "b.status = 0 AND b.recommend = 1 AND b.join_date = {$today_date} AND b.launch_uid = f.friend_uid";
//        $field = 'challenge_id,price,day,btime,etime,user_id';
//
//        $count = db('user_friend')->alias('f')
//            ->where($where)
//            ->join('challenge_both b', $join)
//            ->count(1);
//
//        if ($count > 0) {
//            if ($count > $limit) {
//                $join_sql = [];
//                $join_rand = [];
//                $table_tmp = db('challenge_both')->getTable();
//
//                do {
//                    $rand = mt_rand(0, $count - 1);
//                    if (!in_array($rand, $join_rand)) {
//                        $join_sql[] = "(SELECT launch_cid FROM `{$table}` f INNER JOIN {$table_tmp} b ON {$join} WHERE {$where} LIMIT {$rand},1)";
//
//                        $join_rand[] = $rand;
//                        ++$data_count;
//                    }
//                } while ($data_count < $limit);
//
//                $data = db()->query(implode(' UNION ALL ', $join_sql));
//            } else {
//                $data = db('user_friend')->alias('f')
//                    ->where($where)
//                    ->join('challenge_both b', $join)
//                    ->field('launch_cid')
//                    ->select();
//                $data_count = $count;
//            }
//
//            $data_id = array_column($data, 'launch_cid');
//            $data = db('challenge')->where('challenge_id', 'in', $data_id)->field($field)->select();
//        }
//
//        if ($count < $limit) {
//            $join = "b.status = 0 AND b.join_date = {$today_date} AND b.user_id = f.friend_uid";
//            if (!empty($data_id)) $where .= ' AND challenge_id NOT IN(' . implode(',', $data_id) . ')';
//            $count = db('user_friend')->alias('f')
//                ->where($where)
//                ->join('challenge b', $join)
//                ->count(1);
//
//            if ($count > 0) {
//                if ($count > $limit - $data_count) {
//                    $join_sql = [];
//                    $join_rand = [];
//
//                    do {
//                        $rand = mt_rand(0, $count - 1);
//                        if (!in_array($rand, $join_rand)) {
//                            $join_sql[] = "(SELECT {$field} FROM `{$table}` f INNER JOIN {$table_tmp} b ON {$join} WHERE {$where} LIMIT {$rand},1)";
//
//                            $join_rand[] = $rand;
//                            ++$data_count;
//                        }
//                    } while ($data_count < $limit);
//
//                    $data = array_merge($data, db()->query(implode(' UNION ALL ', $join_sql)));
//                } else {
//                    $data = array_merge($data, db('user_friend')->alias('f')->where($where)->join('challenge b', $join)->field($field)->select());
//                    // $data_count += $count;
//                }
//            }
//        }

        $limit = 3;
        $today_date = Common::today_date();
        $where = 'status = 0 AND join_date = ' . $today_date . ' AND recommend > 0 AND launch_uid <> ' . $this->user_id;
        $count = db('challenge_both')->where($where)->count(1);
        $table = db('challenge_both')->getTable();
        $data = [];
        $data_count = 0;

        if ($count > 0) {
            if ($count > $limit) {
                $join_sql = [];
                $join_rand = [];

                do {
                    $rand = mt_rand(0, $count - 1);
                    if (!in_array($rand, $join_rand)) {
                        $join_sql[] = "(SELECT launch_cid FROM `{$table}` WHERE {$where} LIMIT {$rand},1)";

                        $join_rand[] = $rand;
                        ++$data_count;
                    }
                } while ($data_count < $limit);

                $data = db()->query(implode(' UNION ALL ', $join_sql));
            } else {
                $data = db('challenge_both')->where($where)->field('launch_cid')->select();
                $data_count = $count;
            }
        }

        if ($count < $limit) {
            $where = 'status = 0 AND join_date = ' . $today_date . ' AND recommend = 0 AND launch_uid <> ' . $this->user_id;
            $count = db('challenge_both')->where($where)->count(1);

            if ($count > 0) {
                if ($count > $limit - $data_count) {
                    $join_sql = [];
                    $join_rand = [];

                    do {
                        $rand = mt_rand(0, $count - 1);
                        if (!in_array($rand, $join_rand)) {
                            $join_sql[] = "(SELECT launch_cid FROM `{$table}` WHERE {$where} LIMIT {$rand},1)";

                            $join_rand[] = $rand;
                            ++$data_count;
                        }
                    } while ($data_count < $limit);

                    $data = array_merge($data, db()->query(implode(' UNION ALL ', $join_sql)));
                } else {
                    $data = array_merge($data, db('challenge_both')->where($where)->field('launch_cid')->select());
                    // $data_count += $count;
                }
            }
        }

        if (!empty($data)) {
            $data = db('challenge')
                ->where([['challenge_id', 'in', array_column($data, 'launch_cid')]])
                ->field('challenge_id,price,day,btime,etime,user_id')
                ->order('challenge_id DESC')
                ->select();

            $user = db('user')->where('id', 'in', array_column($data, 'user_id'))->column('id,nickname,avator');

            foreach ($data as $key => $item) {
                $item['challenge_time'] = ChallengeModel::convert_signin_time($item)['stime'];
                $item['price'] /= 100;

                $item['nickname'] = $user[$item['user_id']]['nickname'];
                if (strpos($user[$item['user_id']]['avator'], 'http://') === false) {
                    $item['avator'] = config('site.url') . $user[$item['user_id']]['avator'];
                } else {
                    $item['avator'] = $user[$item['user_id']]['avator'];
                }

                unset($item['btime']);
                unset($item['etime']);
                unset($item['user_id']);

                $data[$key] = $item;
            }
        }

//        $data_out = [
//            [
//                'nickname' => '无名的他',
//                'avator' => 'http://wx.qlogo.cn/mmopen/O19kUBfHXBPywYTxzcphW3AiaDHVY8sf8Tm2iaVoTLo9iaYcvKu5xqicL9MysbokXHQTQEp9IMT67vJgiad8GrtBrAJ060eMkR7mN/0',
//                'day' => 3,
//                'challenge_time' => '08:00~09:00',
//                'price' => 123.56
//            ], [
//                'nickname' => '克里斯',
//                'avator' => 'http://wx.qlogo.cn/mmopen/O19kUBfHXBPywYTxzcphW3AiaDHVY8sf8Tm2iaVoTLo9iaYcvKu5xqicL9MysbokXHQTQEp9IMT67vJgiad8GrtBrAJ060eMkR7mN/0',
//                'day' => 3,
//                'challenge_time' => '08:00~09:00',
//                'price' => 123.56
//            ], [
//                'nickname' => '交电费',
//                'avator' => 'http://wx.qlogo.cn/mmopen/O19kUBfHXBPywYTxzcphW3AiaDHVY8sf8Tm2iaVoTLo9iaYcvKu5xqicL9MysbokXHQTQEp9IMT67vJgiad8GrtBrAJ060eMkR7mN/0',
//                'day' => 3,
//                'challenge_time' => '08:00~09:00',
//                'price' => 123.56
//            ]
//        ];

        return $this->response(['data' => $data]);
    }

    public function room()
    {
        switch (request()->method()) {
            case 'POST':
                $res = ChallengeModel::create_room($this->user_id, input());
                return $this->response($res);
                break;
            case 'DELETE':
                return $this->response(input());
                break;
            case 'OPTIONS':
                $res = ChallengeModel::room_option();
                $res['expires_in'] = 60 * 60 * 24 * 30;
                return $this->response($res);
                break;
            default:
                $room_id = input('room_id');
                if (empty($room_id)) break;

                $data = db('challenge_room')->where('room_id', $room_id)->field('title,avator')->find();
                if (!empty($data)) {
                    return $this->response($data);
                }

                break;
        }

        return $this->response();
    }

    public function my_room()
    {
        $limit = 10;
        $next_id = input('next_id', 0, 'intval');
        $where = [
            ['user_id', '=', $this->user_id],
            ['status', '=', 1]
        ];

        if ($next_id) $where[] = ['room_id', '<', $next_id];
        $data = Db::name('challenge_room')
            ->where($where)
            ->field('room_id,title,avator,day,btime,etime,income_rate')
            ->order('room_id desc')
            ->limit($limit)
            ->select();

        if (empty($data)) return $this->response(['list' => [], 'next_id' => 0]);

        if (count($data) < $limit) $next_id = 0;
        else $next_id = end($data)['room_id'];
        $site_url = config('site.url');

        foreach ($data as $key => $item) {
            $item['income_rate'] = floatval($item['income_rate']);
            $item['stime'] = ChallengeModel::convert_signin_time($item)['stime'];
            if (strpos($item['avator'], 'http://') === false) $item['avator'] = $site_url . $item['avator'];

            unset($item['btime']);
            unset($item['etime']);
            $data[$key] = $item;
        }

        return $this->response(['list' => $data, 'next_id' => $next_id]);
    }

    public function my_join()
    {
        $event = input('event');
        if (empty($event)) {
            return ['', 'param error', 1];
        }

        $data_out = [];

        if (is_string($event)) {
            $event = explode(',', $event);
        }

        if (in_array('stat', $event)) {
            $data_out['stat'] = $this->my_join_stat();
        }

        if (in_array('ing', $event)) {
            $data_out['ing'] = $this->my_join_ing();
        }

        if (in_array('over', $event)) {
            $data_out['over'] = $this->my_both_over();
        }

        return $this->response($data_out);

        $data_out = [
            [
                'title' => '广州早起族',
                'avator' => 'http://wx.qlogo.cn/mmopen/O19kUBfHXBPywYTxzcphW3AiaDHVY8sf8Tm2iaVoTLo9iaYcvKu5xqicL9MysbokXHQTQEp9IMT67vJgiad8GrtBrAJ060eMkR7mN/0',
                'status' => '有进行中',
                'is_me' => true
            ], [
                'title' => '深圳早起族',
                'avator' => 'http://wx.qlogo.cn/mmopen/O19kUBfHXBPywYTxzcphW3AiaDHVY8sf8Tm2iaVoTLo9iaYcvKu5xqicL9MysbokXHQTQEp9IMT67vJgiad8GrtBrAJ060eMkR7mN/0',
                'status' => '结束',
                'is_me' => false
            ]
        ];

        return $this->response($data_out);
    }

    public function my_both()
    {
        $event = input('event');
        if (empty($event)) {
            return ['', 'param error', 1];
        }

        $data_out = [];

        if (is_string($event)) {
            $event = explode(',', $event);
        }

        if (in_array('stat', $event)) {
            $data_out['stat'] = $this->my_both_stat();
        }

        if (in_array('ing', $event)) {
            $data_out['ing'] = $this->my_both_ing();
        }

        if (in_array('over', $event)) {
            $data_out['over'] = $this->my_both_over();
        }

        return $this->response($data_out);
    }

    public function my_join_stat()
    {
        $data = [
            'income_all' => ChallengeModel::income_room($this->user_id),
            'income_room' => Db::name('user_money')->where([
                ['user_id', '=', $this->user_id],
                ['event', '=', 'leader_income']
            ])->sum('money'),
            'income_join' => Db::name('user_money')->where([
                ['user_id', '=', $this->user_id],
                ['event', '=', 'challenge_income_room']
            ])->sum('money')
        ];

        if (request()->action() != __FUNCTION__) {
            return $data;
        }

        return $this->response($data);
    }

    public function my_both_stat()
    {
        $data = [
            'income' => Db::name('user_money')->where([
                ['user_id', '=', $this->user_id],
                ['event', '=', 'challenge_income_both']
            ])->sum('money'),
            'count_all' => Db::name('challenge')->where([
                ['user_id', '=', $this->user_id],
                ['event', 'in', ['launch', 'accept']],
                ['status', 'not in', [0, 4]]
            ])->count(),
            'count_win' => Db::name('challenge')->where([
                ['user_id', '=', $this->user_id],
                ['event', 'in', ['launch', 'accept']],
                ['status', '=', 2]
            ])->count()
        ];

        if (request()->action() != __FUNCTION__) {
            return $data;
        }

        return $this->response($data);
    }

    public function my_join_ing()
    {
        $limit = 10;
        $next_id = input('next_id', 0, 'intval');
        $where = [
            ['user_id', '=', $this->user_id],
            ['status', '<=', 1],
            ['event', '=', 'join']
        ];
        if ($next_id) $where[] = ['challenge_id', '<', $next_id];

        $data = Db::name('challenge')
            ->where($where)
            ->field('challenge_id,room_id,price,day,btime,etime,join_date,expire_date,auto')
            ->order('challenge_id desc')
            ->limit($limit)
            ->select();

        if (empty($data)) return $this->response(['list' => [], 'next_id' => 0]);

        if (count($data) < $limit) $next_id = 0;
        else $next_id = end($data)['challenge_id'];

        $room_id = $challenge_id = [];
        $time = Common::time_format(0, 'Gi');
        $today_date = Common::today_date();
        $today_time = Common::today_time();

        foreach ($data as $k => $v) {
            $v['auto_change'] = 1;
            $v['auto_msg'] = '挑战成功后自动参与';
            $v['highlight'] = 0;
            $v['tips1'] = ChallengeModel::convert_signin_time($v)['stime'] . ' 打卡';
            $v['tips2'] = ((strtotime($v['expire_date']) - $today_time) / 86400) . '天后拿回契约金';

            $v['price'] /= 100;

            if ($v['join_date'] == $today_date) {
                $v['state'] = '未到时';
                $v['remark'] = '明天开始准时过来打卡';
            } elseif ($time < $v['btime']) {
                $v['state'] = '未到时';
                $v['remark'] = '准时打卡后11点前发放奖励';
            } else {
                $challenge_id[$k] = $v['challenge_id'];
            }

            $room_id[$k] = $v['room_id'];
            $data[$k] = $v;
        }

        if (!empty($challenge_id)) {
            $record = Db::name('challenge_record')->where([
                ['challenge_id', 'in', $challenge_id],
                ['date', '=', $today_time],
                ['stime', '>', 0]
            ])->column('challenge_id');

            $is_stat = Common::is_challenge_stat();

            foreach ($challenge_id as $k => $v) {
                $item = $data[$k];

                if (in_array($v, $record)) {
                    $item['highlight'] = 1;

                    if ($is_stat) {
                        $item['state'] = '已结算';
                        $item['remark'] = '拿到奖励金，请看账单明细';
                    } else {
                        $item['state'] = '已打卡';
                        $item['remark'] = '待结算';
                    }
                } else {
                    if ($item['btime'] <= $time && $time < $item['etime']) {
                        $item['state'] = '请打卡';
                        $item['remark'] = '已到打卡时间，请到族群打卡';
                    } else {
                        $item['state'] = '已超时';
                        $item['remark'] = '契约金将被分掉';

                        $item['auto_change'] = 0;
                        $item['auto_msg'] = '未准时打卡，不可再设置';
                    }
                }

                $data[$k] = $item;
            }
        }

        foreach ($data as $k => $v) {
            unset($v['btime']);
            unset($v['etime']);
            unset($v['join_date']);
            unset($v['expire_date']);
            $data[$k] = $v;
        }

        $site_url = config('site.url');
        $room = Db::name('challenge_room')->where([['room_id', 'in', $room_id]])->column('room_id,title,avator');
        $stat = Db::name('challenge')->where([['user_id', 'in', $this->user_id], ['room_id', 'in', $room_id]])->group('room_id')->column('room_id,SUM(income) income');
        foreach ($room as $k => $v) {
            $v['income'] = empty($stat[$k]) ? 0 : $stat[$k] / 100;
            if (strpos($v['avator'], 'http://') === false) $v['avator'] = $site_url . $v['avator'];
            $room[$k] = $v;
        }

        if (request()->action() != __FUNCTION__) {
            return $data;
        }

        return $this->response(['list' => $data, 'next_id' => $next_id, 'room' => $room]);
    }

    public function my_both_ing()
    {
        $data = Db::name('challenge')->where([
            ['user_id', '=', $this->user_id],
            ['room_id', '=', 0],
            ['status', 'in', [0, 1]]
        ])->field('challenge_id,price,day,btime,etime,status,join_date,expire_date')->select();

        if (!empty($data)) {
            $time = request()->time();
            $today_time = Common::today_time();
            $today_date = Common::today_date();
            $site_url = config('site.url');

            $challenge_id = array_column($data, 'challenge_id');
            $both = Db::query(Db::name('challenge_both')
                    ->where([['launch_cid', 'in', $challenge_id], ['accept_uid', '>', 0]])
                    ->field('launch_cid cid,accept_uid uid')
                    ->buildSql() . ' UNION ALL ' .
                Db::name('challenge_both')
                    ->where([['accept_cid', 'in', $challenge_id], ['launch_uid', '>', 0]])
                    ->field('accept_cid cid,launch_uid uid')
                    ->buildSql()
            );

            $user_id = [];
            if (!empty($both)) {
                foreach ($both as $item) {
                    $user_id[$item['cid']] = $item['uid'];
                }

                $user = DB::name('user')
                    ->where([['id', 'in', $user_id]])
                    ->column('id,nickname,avator');
            }

            $record = Db::name('challenge_record')
                ->where([['challenge_id', 'in', $challenge_id], ['date', '=', $today_time], ['stime', '>', 0]])
                ->column('challenge_id');

            foreach ($data as $k => $v) {
                if (empty($user_id[$v['challenge_id']])) {
                    $v['nickname'] = '...';
                    $v['avator'] = $site_url . '/static/img/who.png';
                } else {
                    $u = $user[$user_id[$v['challenge_id']]];
                    $v['nickname'] = $u['nickname'];

                    $v['avator'] = $u['avator'];
                    if (strpos($v['avator'], 'http://') === false) $v['avator'] = $site_url . $v['avator'];
                }

                $v['highlight'] = 0;
                $v['tips1'] = intval($v['btime'] / 100) . ':' . substr($v['btime'], -2)
                    . '~' . intval($v['etime'] / 100) . ':' . substr($v['etime'], -2) . ' 打卡';
                $v['tips2'] = ((strtotime($v['expire_date']) - $today_time) / 86400) . '天后拿回契约金';

                if ($v['status'] == 0) {
                    $v['state'] = '未到时';
                    $v['remark'] = '等待好友接受中';
                } else {
                    if (in_array($v['challenge_id'], $record)) {
                        $v['state'] = '已打卡';
                        $v['remark'] = '如对方未打卡，连本金翻倍奖励';
                        $v['highlight'] = 1;
                    } elseif ($v['join_date'] == $today_date) {
                        $v['state'] = '未到时';
                        $v['remark'] = '明天开始准时过来打卡';
                    } else {
                        if ($time < $today_time + 5 * 60 * 60) {
                            $v['state'] = '未到时';
                            $v['remark'] = '请在约定时间范围内打卡';
                        } elseif ($time > $today_time + 8 * 60 * 60) {
                            $v['state'] = '已超时';
                            $v['remark'] = '抱歉，未准时打卡，契约金被分掉';
                        } else {
                            $v['state'] = '请打卡';
                            $v['remark'] = '已到打卡时间，请在顶部签到打卡';
                        }
                    }
                }

                $v['price'] /= 100;

                unset($v['btime']);
                unset($v['etime']);
                unset($v['status']);
                unset($v['join_date']);
                unset($v['challenge_id']);

                $data[$k] = $v;
            }
        }

        if (request()->action() != __FUNCTION__) {
            return $data;
        }

        return $this->response($data);
    }

    public function my_join_over()
    {
        $limit = 10;
        $next_id = input('next_id', 0, 'intval');
        $where = [
            ['user_id', '=', $this->user_id],
            ['status', '>', 1],
            ['event', '=', 'join']
        ];

        if ($next_id) $where[] = ['challenge_id', '<', $next_id];

        $data = Db::name('challenge')
            ->where($where)
            ->field('challenge_id,room_id,price,day,btime,etime,expire_date,status')
            ->order('stat_time desc,challenge_id desc')
            ->limit($limit)
            ->select();

        if (empty($data)) return $this->response(['list' => [], 'next_id' => 0]);

        if (count($data) < $limit) $next_id = 0;
        else $next_id = end($data)['challenge_id'];

        $room_id = [];

        foreach ($data as $k => $v) {
            $v['tips1'] = ChallengeModel::convert_signin_time($v)['stime'] . '打卡';
            $v['tips2'] = Common::time_format(strtotime($v['expire_date']), 'Y-m-d');

            if ($v['status'] == 2) {
                $v['remark'] = '已退出，￥' . ($v['price'] / 100) . '契约金已退，奖励金已发';
            } elseif ($v['status'] > 5) {
                $v['remark'] = date('Y-m-d', strtotime($v['status'])) . '未准时打卡，￥' . ($v['price'] / 100) . '契约金被群分';
            } else {
                $v['remark'] = '未知';
            }

            $room_id[$k] = $v['room_id'];
            $data[$k] = $v;
        }

        foreach ($data as $key => $item) {
            unset($item['challenge_id']);
            unset($item['price']);
            unset($item['btime']);
            unset($item['etime']);
            unset($item['expire_date']);
            unset($item['status']);
            $data[$key] = $item;
        }

        $site_url = config('site.url');
        $room = Db::name('challenge_room')->where('room_id', 'in', $room_id)->column('room_id,title,avator');
        $stat = Db::name('challenge')->where([['user_id', 'in', $this->user_id], ['room_id', 'in', $room_id]])->group('room_id')->column('room_id,SUM(income) income');
        foreach ($room as $k => $v) {
            $v['income'] = empty($stat[$k]) ? 0 : $stat[$k] / 100;
            if (strpos($v['avator'], 'http://') === false) $v['avator'] = $site_url . $v['avator'];
            $room[$k] = $v;
        }

        return $this->response(['list' => $data, 'next_id' => $next_id, 'room' => $room]);
    }

    public function my_both_over()
    {
        $data = Db::name('challenge')->where([
            ['user_id', '=', $this->user_id],
            ['room_id', '=', 0],
            ['status', '>', 1]
        ])->field('challenge_id,price,day,btime,etime,expire_date,status')->select();

        if (!empty($data)) {
            $site_url = config('site.url');

            $challenge_id = array_column($data, 'challenge_id');
            $both = Db::query(Db::name('challenge_both')
                    ->where([['launch_cid', 'in', $challenge_id], ['accept_uid', '>', 0]])
                    ->field('launch_cid cid,accept_uid uid')
                    ->buildSql() . ' UNION ALL ' .
                Db::name('challenge_both')
                    ->where([['accept_cid', 'in', $challenge_id], ['launch_uid', '>', 0]])
                    ->field('accept_cid cid,launch_uid uid')
                    ->buildSql()
            );

            $user_id = [];
            if (!empty($both)) {
                foreach ($both as $item) {
                    $user_id[$item['cid']] = $item['uid'];
                }

                $user = DB::name('user')
                    ->where([['id', 'in', $user_id]])
                    ->column('id,nickname,avator');
            }

            foreach ($data as $k => $v) {
                if (empty($user_id[$v['challenge_id']])) {
                    $v['nickname'] = '...';
                    $v['avator'] = $site_url . '/static/img/who.png';
                } else {
                    $u = $user[$user_id[$v['challenge_id']]];
                    $v['nickname'] = $u['nickname'];

                    $v['avator'] = $u['avator'];
                    if (strpos($v['avator'], 'http://') === false) $v['avator'] = $site_url . $v['avator'];
                }

                $v['tips1'] = intval($v['btime'] / 100) . ':' . substr($v['btime'], -2)
                    . '~' . intval($v['etime'] / 100) . ':' . substr($v['etime'], -2) . ' 打卡';
                $v['tips2'] = Common::time_format(strtotime($v['expire_date']), 'Y年m月d日');

                if ($v['status'] == 2) {
                    $v['state'] = '已结算';
                    $v['remark'] = '恭喜，挑战成功，到账单明细核对';
                    $v['highlight'] = 1;
                } elseif ($v['status'] == 4) {
                    $v['state'] = '无人接';
                    $v['remark'] = '无人接受，已退回，到账单明细查看';
                    $v['highlight'] = 0;
                } else {
                    $v['state'] = '已结算';
                    $v['remark'] = '抱歉，未准时打卡，契约金被分掉';
                    $v['highlight'] = 1;
                }

                $v['price'] /= 100;

                unset($v['btime']);
                unset($v['etime']);
                unset($v['status']);
                unset($v['challenge_id']);
                unset($v['expire_date']);

                $data[$k] = $v;
            }
        }

        if (request()->action() != __FUNCTION__) {
            return $data;
        }

        return $this->response($data);
    }

    public function launch()
    {
        switch (request()->method()) {
            case 'POST':
                $res = ChallengeModel::launch($this->user_id, input('post.'));
                if (empty($res['challenge_id'])) {
                    return call_user_func_array([$this, 'response'], $res);
                }

                return $this->response($res);
                break;
            default:
                $res = ChallengeModel::both_option();
                $res['expires_in'] = 60 * 60 * 24 * 30;
                return $this->response($res);
                break;
        }

        return $this->response();
    }

    public function accept()
    {
        switch (request()->method()) {
            case 'POST':
                $res = ChallengeModel::accept($this->user_id, input('post.'));
                if (empty($res['challenge_id'])) {
                    return call_user_func_array([$this, 'response'], $res);
                }

                return $this->response($res);
                break;
            default:
                break;
        }

        return $this->response();
    }

    public function join()
    {
        switch (request()->method()) {
            case 'POST':
                $res = ChallengeModel::join($this->user_id, input('post.'));
                if (empty($res['challenge_id'])) {
                    return call_user_func_array([$this, 'response'], $res);
                }

                return $this->response($res);
                break;
            default:
                break;
        }

        return $this->response();
    }

    public function signin()
    {
        switch (request()->method()) {
            case 'POST':
                $room_id = input('room_id', 0, 'intval');
                $res = ChallengeModel::signin($this->user_id, $room_id);

                if ($res[2] == 0) {
                    $url = url('home/challenge/signin', ['room_id' => $room_id], false, true);
                    if (empty($res[0])) $res[0] = ['url' => $url];
                    else $res[0]['url'] = $url;
                }

                return call_user_func_array([$this, 'response'], $res);
                break;
            default:
                break;
        }

        return $this->response();
    }

    public function join_auto_change()
    {
        $challenge_id = input('challenge_id', 0, 'intval');
        if (!$challenge_id) $this->response('', 'param missing', 1);

        $auto = input('auto', false);

        db('challenge')->where([['challenge_id', '=', $challenge_id], ['user_id', '=', $this->user_id]])->update(['auto' => $auto]);

        return $this->response();
    }
}
