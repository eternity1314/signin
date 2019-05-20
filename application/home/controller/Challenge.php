<?php

namespace app\home\controller;

use app\common\model\Challenge as ChallengeModel;
use app\common\model\Common;
use app\common\model\User;
use Hooklife\ThinkphpWechat\Wechat;
use think\Db;

class Challenge extends Base
{
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
            case 'OPTIONS':
                $res = ChallengeModel::launch_option();
                $res['expires_in'] = 60 * 60 * 24 * 30;
                return $this->response($res);
                break;
            default:
                break;
        }

        return $this->response();
    }

    public function accept()
    {
        switch (request()->method()) {
            case 'POST':
                $res = ChallengeModel::accept($this->user_id, input());
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
                $res = ChallengeModel::join($this->user_id, input());
                if (empty($res['challenge_id'])) {
                    return call_user_func_array([$this, 'response'], $res);
                }

                return $this->response($res);
                break;
            default:
                if (input('room_id') == 'me') {
                    $room_id = Db::name('challenge_room')
                        ->where('user_id', '=', $this->user_id)
                        ->order('room_id DESC')
                        ->value('room_id');
                    return redirect(url() . '&room_id=' . $room_id);
                }

                $room_id = input('room_id', 0, 'intval');
                if (empty($room_id)) return $this->response('', 'param error', 1);

                $room_id = intval($room_id);
                $room = Db::name('challenge_room')->where('room_id', '=', $room_id)->find();

                if (empty($room)) return $this->response('', 'param error', 1);

                $user = User::find($room['user_id']);
                $room['nickname'] = $user['nickname'];
                $room['avator'] = $user['avator'];
                $room['stime'] = ChallengeModel::convert_signin_time($room)['stime'];

                $link_join = url('join', '', true, true) . '?room_id=' . $room_id;
                $option = ChallengeModel::join_option();

                // 参与人数、头像
                $join = Db::name('challenge')->where('room_id', '=', $room_id)
                    ->field('user_id,MAX(edit_time) edit_time')
                    ->order('edit_time desc')
                    ->group('user_id')
                    ->limit(19)
                    ->select();
                if (!empty($join)) {
                    array_multisort(array_column($join, 'edit_time'), SORT_DESC, $join);
                    $user_id = array_column($join, 'user_id');
                    $join_head = Db::name('user')->where('id', 'in', $user_id)
                        ->order('FIELD(id, ' . implode($user_id, ',') . ')')
                        ->column('avator');

                    $join_count = count($join_head);
                    if ($join_count >= 19) {
                        $join_count = Db::name('challenge')->where('room_id', '=', $room_id)->count('DISTINCT user_id');
                    }
                } else {
                    $join_head = [];
                    $join_count = 0;
                }

                $stat = Db::name('challenge_best')->where([
                    ['room_id', '=', $room_id],
                    ['edit_time', '>', Common::today_time()]
                ])->find();

                $income = Db::name('challenge_income')->where('room_id', '=', $room_id)->order('income desc')->select();

                $today_time = Common::today_time();
                $record = Db::name('challenge_record')->where([
                    ['user_id', '=', $this->user_id],
                    ['room_id', '=', $room_id],
                    ['date', '>=', $today_time],
                ])->order('date asc')->find();

                if (empty($record)) { // 未加入 1、6
                    $btn_signin = '<button class="act-signin">签到打卡</button>'; // disabled
                } else { // 已加入
                    if ($record['date'] != $today_time) { // 明天打卡 2
                        $btn_signin = '<button class="act-signin">非打卡时间</button>'; // disabled
                    } else { // 今天打卡
                        $time = request()->time();
                        if ($record['btime'] <= $time && $time < $record['etime']) { // 时间范围内 3
                            $btn_signin = '<button class="act-signin">签到打卡</button>';
                        } elseif ($record['stime'] == 0) { // 未打卡
                            if ($time >= $record['etime']) { // 时间范围后 5
                                $btn_signin = '<button class="act-signin">今天已超时</button>'; // disabled
                            } else {
                                $btn_signin = '<button class="act-signin">非打卡时间</button>'; // disabled
                            }
                        } else { // 已打卡 if ($record['stime'] > 0)
                            if ($time >= $record['etime'] && $time < $today_time + 39600) { // 时间范围后-结算前 4
                                $btn_signin = '<button class="act-signin">签到打卡</button>';
                            } else { // 结算之后 7
                                $btn_signin = '<button class="act-signin">今天已结算</button>';
                            }
                        }
                    }
                }

                $redpack_count = db('redpack_send')->where([
                        ['room_id', '=', $room_id],
                        ['show', '=', 1],
                        ['surplus', '>', 0],
                        ['redpack_id', 'exp', db()->raw('NOT IN(SELECT redpack_id FROM ' . db()->getTable('redpack_status') . ' WHERE user_id = ' . $this->user_id . ')')]]
                )->count(1);

                $data = [
                    'room_id' => $room_id,
                    'room' => $room,
                    'link_join' => $link_join,
                    'link_poster' => url('poster') . '?room_id=' . $room_id,
                    'room_option' => $option,
                    'user' => User::me(),
                    'join_head' => $join_head,
                    'join_count' => $join_count,
                    'stat' => $stat,
                    'income' => $income,
                    'btn_signin' => $btn_signin,
                    'redpack_count' => $redpack_count
                ];

                if (empty($this->invite_code)) {
                    $this->invite_code = db('user_promotion')
                        ->where('user_id', $this->user_id)
                        ->value('invite_code');
                }

                $day = ChallengeModel::challenge_day($this->user_id);
                // $income = ChallengeModel::income_room($this->user_id);
                $data['wx_share'] = [
                    // 'title' => '早起打卡啦，我已坚持' . $day . '天，领了' . $income . '元奖金',
                    'title' => '早起打卡啦，我已坚持' . $day . '天，可以领一笔现金奖金',
                    'desc' => '坚持每天早起瓜分奖金，享健康生活，分享拼多多内部券再躺赚，享持久管道收入...',
                    'imgUrl' => config('site.url') . '/static/img/wx_share.png', // $room['pic'],
                    'link' => $link_join . '&invite_code=' . $this->invite_code
                ];

                // 微信分享
                if ($_SERVER['SERVER_ADDR'] == '59.110.54.15' && Common::isWeixin()) {
                    $data['wx_config'] = Wechat::js()->getConfigArray(['onMenuShareAppMessage', 'onMenuShareTimeline']);
                }

                return view('', $data);
                break;
        }

        return $this->response();
    }

    public function room()
    {
        switch (request()->method()) {
            case 'POST':
                $res = ChallengeModel::create_room($this->user_id, input());

                if (empty($res['room_id'])) {
                    return call_user_func_array([$this, 'response'], $res);
                }

                return $this->response($res);
                break;
            case 'PATCH':
                $res = ChallengeModel::update_room($this->user_id, input());
                if (empty($res['update_id'])) {
                    return call_user_func_array([$this, 'response'], $res);
                }

                return $this->response($res);
                break;
            case 'DELETE':
                return $this->response(input());
                break;
            default:
                $room_id = input('room_id', 0, 'intval');
                $option = ChallengeModel::room_option();
                $data = ['option' => $option];

                if (empty($room_id)) { // 创建
                    $data['user'] = User::me();
                    $data['city'] = session('city');
                    if (empty($data['city'])) {
                        $data['city'] = Common::getLocation(['ip' => request()->ip()]);
                        if (!empty($data['city'])) {
                            session('city', $data['city']);
                        }
                    }

                    $count = Db::name('user_money')->where([
                        ['user_id', '=', $this->user_id],
                        ['event', '=', 'room_fee'],
                        ['money', '=', 0]
                    ])->count(1);
                    if ($count == 0) {
                        // $free_type = User::partner_type($this->user_id);
                        // if ($free_type) $data['free_type'] = $free_type;

                        $user_level = User::level($this->user_id);
                        if ($user_level >= 3) $data['free_type'] = 'month';
                    }

                    return view('room_add', $data);
                } else { // 修改
                    $room = Db::name('challenge_room')->where('room_id', '=', $room_id)->find();
                    if (empty($room) || $room['status'] != 1) return $this->response('', 'room not exists', 1);
                    if ($room['user_id'] != $this->user_id) return redirect(url('join') . '&room_id=' . $room_id);

                    $room['stime'] = ChallengeModel::convert_signin_time($room)['stime'];
                    if ($room['expire_time'] < request()->time()) $room['expire_time'] = '已过期';
                    else $room['expire_time'] = '有效期截止至 ' . date('Y-m-d', $room['expire_time']);

                    $data['room'] = $room;
                    $data['room_id'] = $room_id;

                    $data['income'] = Db::name('user_money')->where([
                        ['user_id', '=', $this->user_id],
                        ['event', 'in', ['challenge_income_room', 'leader_income']]
                    ])->sum('money');
                    $data['income'] = sprintf('%.2f', $data['income']);
                    $data['link_poster'] = url('poster') . '?room_id=' . $room_id;

                    return view('room_edit', $data);
                }

                break;
        }

        return $this->response();
    }

    public function create_room_success()
    {
        if (input('room_id') == 'me') {
            $room = db('challenge_room')
                ->where('user_id', '=', $this->user_id)
                ->order('room_id DESC')
                ->find();

            if (empty($room)) $this->response('', '数据有误！');

            $room_id = $room['room_id'];
            $leader_rate = $room['leader_rate'];

            $where = [
                ['user_id', '=', $this->user_id],
                ['room_id', '=', $room_id],
                ['add_time', '=', $room['add_time']]
            ];

            $redpack_id = db('redpack_send')->where($where)->value('redpack_id');
        } else {
            $room_id = input('room_id', 0, 'intval');
            $leader_rate = input('leader_rate', 0, 'intval');
            $redpack_id = input('redpack_id', 0, 'intval');

            // $where = [['redpack_id', '=', $redpack_id]];
        }

        if (!$room_id) return $this->response('', '数据有误！');

        $data = ['leader_rate' => $leader_rate];

        if (empty($this->invite_code)) {
            $this->invite_code = db('user_promotion')
                ->where('user_id', $this->user_id)
                ->value('invite_code');
        }

        // $url = db('redpack_send')->where($where)->value('short_url');
        $url = url('home/challenge/redpack', '', '', true)
            . "?room_id={$room_id}&redpack_id={$redpack_id}&invite_code={$this->invite_code}";
        $user = User::find($this->user_id);

        $data['wx_share'] = [
            'title' => $user['nickname'] . '送你一份小福利，现金红包送完即止',
            'desc' => '创建属于自己的生活族群即可获得10个现金红包，坚持每天早起瓜分奖金，享受健康生活...',
            'imgUrl' => config('site.url') . '/static/redpack/img/red_packet.png',
            'link' => $url
        ];

        // 微信分享
        if ($_SERVER['SERVER_ADDR'] == '59.110.54.15' && Common::isWeixin()) {
            $data['wx_config'] = Wechat::js()->getConfigArray(['onMenuShareAppMessage', 'onMenuShareTimeline']);
        }

        return view('', $data);
    }

    public function mine()
    {
        if (request()->isAjax()) {
            $limit = 10;
            $next_id = input('next_id', 0, 'intval');
            $event = input('event');
            if (empty($event)) $event = 'ing';
            $where = [['user_id', '=', $this->user_id]];

            switch ($event) {
                case 'ing':
                    $where[] = ['status', '<=', 1];
                    $where[] = ['event', '=', 'join'];
                    if ($next_id) $where[] = ['challenge_id', '<', $next_id];

                    $data = Db::name('challenge')
                        ->where($where)
                        ->field('challenge_id,room_id,price,day,btime,etime,join_date,expire_date,auto')// income
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

                    foreach ($data as $key => $item) {
                        $surplus = (strtotime($item['expire_date']) - $today_time) / 86400;
                        $item['stime'] = ChallengeModel::convert_signin_time($item)['stime'] . '打卡|' . $surplus . '天后拿回契约金';
                        // $item['income'] /= 100;
                        $item['price'] /= 100;

                        if ($item['join_date'] == $today_date) {
                            $item['state'] = 0;
                            $item['status'] = '未到时';
                            $item['remark'] = '明天开始准时过来打卡';
                        } elseif ($time < $item['btime']) {
                            $item['state'] = 0;
                            $item['status'] = '未到时';
                            $item['remark'] = '准时打卡后11点前发放奖励';
                        } else {
                            $challenge_id[$key] = $item['challenge_id'];
                        }

                        $room_id[$key] = $item['room_id'];
                        $data[$key] = $item;
                    }

                    if (!empty($challenge_id)) {
                        $record = Db::name('challenge_record')->where([
                            ['challenge_id', 'in', $challenge_id],
                            ['date', '=', $today_time],
                            ['stime', '>', 0]
                        ])->column('challenge_id');

                        $is_stat = Common::is_challenge_stat();

                        foreach ($challenge_id as $key => $v) {
                            $item = $data[$key];

                            if (in_array($v, $record)) {
                                if ($is_stat) {
                                    $item['state'] = 1;
                                    $item['status'] = '已结算';
                                    $item['remark'] = '拿到奖励金，请看账单明细';
                                } else {
                                    $item['state'] = 1;
                                    $item['status'] = '已打卡';
                                    $item['remark'] = '待结算';
                                }
                            } else {
                                if ($item['btime'] <= $time && $time < $item['etime']) {
                                    $item['state'] = 0;
                                    $item['status'] = '请打卡';
                                    $item['remark'] = '已到打卡时间，请到族群打卡';
                                } else {
                                    $item['state'] = 0;
                                    $item['status'] = '已超时';
                                    $item['remark'] = '契约金将被分掉';
                                }
                            }

                            // $room_id[$key] = $item['room_id'];
                            $data[$key] = $item;
                        }
                    }

                    foreach ($data as $key => $item) {
                        // unset($item['challenge_id']);
                        unset($item['btime']);
                        unset($item['etime']);
                        unset($item['join_date']);
                        unset($item['expire_date']);
                        $data[$key] = $item;
                    }

                    $room = Db::name('challenge_room')->where([['room_id', 'in', $room_id]])->column('room_id,title,avator');
                    $stat = Db::name('challenge')->where([['user_id', 'in', $this->user_id], ['room_id', 'in', $room_id]])->group('room_id')->column('room_id,SUM(income) income');
                    foreach ($room as $k => $v) {
                        $room[$k]['income'] = empty($stat[$k]) ? 0 : $stat[$k] / 100;
                    }

                    return $this->response(['list' => $data, 'next_id' => $next_id, 'room' => $room]);
                    break;
                case 'over':
                    $where[] = ['status', '>', 1];
                    $where[] = ['event', '=', 'join'];
                    if ($next_id) $where[] = ['challenge_id', '<', $next_id];

                    $data = Db::name('challenge')
                        ->where($where)
                        ->field('challenge_id,room_id,price,day,btime,etime,expire_date,status')// income
                        ->order('stat_time desc,challenge_id desc')
                        ->limit($limit)
                        ->select();

                    if (empty($data)) return $this->response(['list' => [], 'next_id' => 0]);

                    if (count($data) < $limit) $next_id = 0;
                    else $next_id = end($data)['challenge_id'];

                    $room_id = [];

                    foreach ($data as $key => $item) {
                        $item['stime'] = ChallengeModel::convert_signin_time($item)['stime'] . '打卡|'
                            . Common::time_format(strtotime($item['expire_date']), 'Y-m-d');
                        // $item['income'] /= 100;

                        if ($item['status'] == 2) {
                            $item['status'] = '已退出，￥' . ($item['price'] / 100) . '契约金已退，奖励金已发';
                        } elseif ($item['status'] > 5) {
                            $item['status'] = date('Y-m-d', strtotime($item['status'])) . '未准时打卡，￥' . ($item['price'] / 100) . '契约金被群分';
                        } else {
                            $item['status'] = '未知';
                        }

                        $room_id[$key] = $item['room_id'];
                        $data[$key] = $item;
                    }

                    foreach ($data as $key => $item) {
                        unset($item['challenge_id']);
                        unset($item['btime']);
                        unset($item['etime']);
                        unset($item['expire_date']);
                        $data[$key] = $item;
                    }

                    $room = Db::name('challenge_room')->where('room_id', 'in', $room_id)->column('room_id,title,avator');
                    $stat = Db::name('challenge')->where([['user_id', 'in', $this->user_id], ['room_id', 'in', $room_id]])->group('room_id')->column('room_id,SUM(income) income');
                    foreach ($room as $k => $v) {
                        $room[$k]['income'] = empty($stat[$k]) ? 0 : $stat[$k] / 100;
                    }

                    return $this->response(['list' => $data, 'next_id' => $next_id, 'room' => $room]);

                    break;
                case 'create':
                    $where[] = ['status', '=', 1];
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

                    foreach ($data as $key => $item) {
                        $item['income_rate'] = floatval($item['income_rate']);
                        $item['stime'] = ChallengeModel::convert_signin_time($item)['stime'];
                        $data[$key] = $item;
                    }

                    return $this->response(['list' => $data, 'next_id' => $next_id]);
                    break;
                default :
                    break;
            }

            return $this->response([]);
        } else {
            if (!empty(input('sign'))) {
                return redirect(url()); // 去掉签名，避免ajax签名错误
            }

            $data = [
                'income_all' => ChallengeModel::income_room($this->user_id),
                'income_room' => Db::name('user_money')->where([
                    ['user_id', '=', $this->user_id],
                    ['event', '=', 'leader_income']
                ])->sum('money'),
                'income_join' => Db::name('user_money')->where([
                    ['user_id', '=', $this->user_id],
                    ['event', '=', 'challenge_income_room']
                ])->sum('money'),
                'balance' => User::me()['balance']
            ];

            if (!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'room_id=') !== false) {
                $arr = parse_url($_SERVER['HTTP_REFERER']);
                if (!empty($arr['query'])) {
                    parse_str($arr['query'], $arr);
                    if (!empty($arr['room_id'])) {
                        $data['room_id'] = intval($arr['room_id']);
                    }
                }
            }

            if (empty($data['room_id'])) {
                $count = db('challenge')->where([
                    ['user_id', '=', $this->user_id],
                    ['event', '=', 'join']
                ])->count(1);
                if ($count > 0) {
                    $room_id = db('challenge')->where([
                        ['user_id', '=', $this->user_id],
                        ['event', '=', 'join']
                    ])->limit(mt_rand(0, $count - 1), 1)->field('room_id')->select();
                    $data['room_id'] = $room_id[0]['room_id'];
                } else {
                    $count = db('challenge_room')->where([['user_id', '=', $this->user_id]])->count(1);
                    if ($count > 0) {
                        $room_id = db('challenge_room')->where([
                            ['user_id', '=', $this->user_id]
                        ])->limit(mt_rand(0, $count - 1), 1)->field('room_id')->select();
                        $data['room_id'] = $room_id[0]['room_id'];
                    }
                }

                if ($count == 0) {
                    $data['room_id'] = 0;
                }

//                else {
//                    $count = db('challenge_room')->count(1);
//                    if ($count > 0) {
//                        $room_id = db('challenge_room')->limit(mt_rand(0, $count - 1), 1)->field('room_id')->select();
//                        $data['room_id'] = $room_id[0]['room_id'];
//                    }
//                }
            }

            $day = ChallengeModel::challenge_day($this->user_id);
            // $income = ChallengeModel::income_room($this->user_id);
            $data['wx_share'] = [
                // 'title' => '早起打卡啦，我已坚持' . $day . '天，领了' . $income . '元奖金',
                'title' => '早起打卡啦，我已坚持' . $day . '天，可以领一笔现金奖金',
                'desc' => '坚持每天早起瓜分奖金，享健康生活，分享拼多多内部券再躺赚，享持久管道收入...',
            ];

            if (!empty($data['room_id'])) {
                // $room_id = $data['room_id'];
                // $room = db('challenge_room')->where([['room_id', '=', $room_id]])->field('pic')->find();
                if (empty($this->invite_code)) {
                    $this->invite_code = db('user_promotion')
                        ->where('user_id', $this->user_id)
                        ->value('invite_code');
                }

                $link_join = url('join', '', true, true) . '?room_id=' . $data['room_id'];
                $data['wx_share']['imgUrl'] = config('site.url') . '/static/img/wx_share.png'; // $room['pic'],
                $data['wx_share']['link'] = $link_join . '&invite_code=' . $this->invite_code;
            } else {
                $user = User::find($this->user_id);
                $data['wx_share']['imgUrl'] = $user['avator'];
                $data['wx_share']['link'] = url('', '', true, true);
                $link_join = url('room');
            }

            // 微信分享
            if ($_SERVER['SERVER_ADDR'] == '59.110.54.15' && Common::isWeixin()) {
                $data['wx_config'] = Wechat::js()->getConfigArray(['onMenuShareAppMessage', 'onMenuShareTimeline']);
            }

            $data['link_join'] = $link_join;
            return view('', $data);
        }
    }

    public function room_change()
    {
        $data = Db::name('challenge')->where([
            ['user_id', '=', $this->user_id],
            ['status', '<=', 1],
            ['event', '=', 'join']
        ])->column('room_id');

        if (empty($data)) return $this->response(['list' => []]);

        $data = Db::name('challenge_room')->where('room_id', 'in', $data)->field('room_id,title,avator,user_id')->select();
        foreach ($data as $key => $item) {
            $item['is_me'] = $item['user_id'] == $this->user_id;

            unset($item['user_id']);
            $data[$key] = $item;
        }

        return $this->response(['list' => $data]);
    }

    public function join_auto_change()
    {
        $challenge_id = input('challenge_id', 0, 'intval');
        if (!$challenge_id) $this->response('', 'param missing', 1);

        $auto = input('auto', false);

        db('challenge')->where([['challenge_id', '=', $challenge_id], ['user_id', '=', $this->user_id]])->update(['auto' => $auto]);

        return $this->response();
    }

    public function dynamic()
    {
        $room_id = input('room_id', 0, 'intval');
        if (empty($room_id)) return $this->response('', 'room empty', 1);

        if (request()->isAjax()) {
            $data = ChallengeModel::dynamic($room_id, $this->user_id, null);
            return $this->response('', $data);
        }

        $room = db('challenge_room')->where('room_id', '=', $room_id)->find();
        if (empty($room) || $room['status'] != 1) return $this->response('', 'room empty', 1);

        $join_count = db('Challenge')->where('room_id', '=', $room_id)->count(1);

        $data = [
            'room_id' => $room_id,
            'room' => $room,
            'title' => $room['title'],
            'is_join' => $join_count,
            'link_load' => url() . '?room_id=' . $room_id,
            'link_join' => url('join'),
            'both_option' => ChallengeModel::both_option(),
            'user' => User::me()
        ];

        // 微信分享
        if ($_SERVER['SERVER_ADDR'] == '59.110.54.15' && Common::isWeixin()) {
            $data['wx_config'] = Wechat::js()->getConfigArray(['onMenuShareAppMessage', 'onMenuShareTimeline']);
        }

        if (empty($this->invite_code)) {
            $this->invite_code = db('user_promotion')
                ->where('user_id', $this->user_id)
                ->value('invite_code');
        }

        $day = ChallengeModel::challenge_day($this->user_id);
        // $income = ChallengeModel::income_room($this->user_id);
        $data['wx_share'] = [
            // 'title' => '早起打卡啦，我已坚持' . $day . '天，领了' . $income . '元奖金',
            'title' => '早起打卡啦，我已坚持' . $day . '天，可以领一笔现金奖金',
            'desc' => '坚持每天早起瓜分奖金，享健康生活，分享拼多多内部券再躺赚，享持久管道收入...',
            'imgUrl' => config('site.url') . '/static/img/wx_share.png', // $room['pic'],
            'link' => url('join', '', true, true) . '?room_id=' . $room_id . '&invite_code=' . $this->invite_code
        ];

        return view('user/dynamic', $data);
    }

    public function signin()
    {
        switch (request()->method()) {
            case 'POST': // 打卡
                $room_id = input('room_id', 0, 'intval');
                $res = ChallengeModel::signin($this->user_id, $room_id);

                if ($res[2] == 0) $res[1] = ['msg' => $res[1], 'url' => url('', ['room_id' => $room_id], false, false)];

                return call_user_func_array([$this, 'response'], $res);
                break;
            default: // 打卡成功
                $room_id = input('room_id', 0, 'intval');
                if (empty($room_id)) {
                    $title = '好友早起对战';
                } else {
                    $title = db('challenge_room')->where([['room_id', '=', $room_id]])->value('title');
                }

                $data = ['title' => $title, 'link_join' => url('join')];
                $data['rooms'] = ChallengeModel::room_system_rand(3);

                // 免单商品
                $map = [
                    ['price', '<=', 500]
                ];

                $data['goods_data'] = db('goods')->where($map)
                    ->field('id, goods_id, name, img')
                    ->orderRaw('if(coupon_price = 0, 1, 0), id desc')
                    ->limit(6)
                    ->select();


                // 微信分享
                if ($_SERVER['SERVER_ADDR'] == '59.110.54.15' && Common::isWeixin()) {
                    $data['wx_config'] = Wechat::js()->getConfigArray(['onMenuShareAppMessage', 'onMenuShareTimeline']);
                }

                // 用户信息
                $data['user_info'] = Db::name('user')->field('nickname, avator')->where('id', '=', $this->user_id)->find();
                $data['user_info']['avator'] = base64_encode($data['user_info']['avator']);

//                // 免单名额
//                $user_promotion = db('user_promotion')->field('free_order, invite_code')->where('user_id', '=', $this->user_id)->find();
//
//                // 已用免单名额
//                $used_free_order = $all_first_order = Db::query('select count(*) as count from (select min(id) from sl_order where add_time > 1534163213 and status <> 3 and user_id in (select user_id from sl_user_tier where pid = ' . $this->user_id . ') GROUP BY user_id) t')[0]['count'];
//
//                if ($user_promotion['free_order'] < 5) {
//                    $data['free_order'] = 5;
//                } else {
//                    $data['free_order'] = $user_promotion['free_order'] > $used_free_order ? $user_promotion['free_order'] - $used_free_order : 0;
//                }
//
//                $data['invite_code'] = $user_promotion['invite_code'];

                if (empty($this->invite_code)) {
                    $this->invite_code = db('user_promotion')
                        ->where('user_id', $this->user_id)
                        ->value('invite_code');
                }

                $data['invite_code'] = $this->invite_code;

                $day = ChallengeModel::challenge_day($this->user_id);
                $data['wx_share'] = [
                    'title' => '早起打卡啦，我已坚持' . $day . '天，可以领一笔现金奖金',
                    'desc' => '坚持每天早起瓜分奖金，享健康生活，分享拼多多内部券再躺赚，享持久管道收入...',
                    'imgUrl' => config('site.url') . '/static/img/wx_share.png', // $room['pic'],
                    'link' => url('join', '', true, true) . '?room_id=' . $room_id . '&invite_code=' . $this->invite_code
                ];

                return view('', $data);
                break;
        }

        return $this->response();
    }

    public function poster()
    {
        $room_id = input('room_id', 0, 'intval');
        if (empty($room_id)) return $this->response('', 'room empty', 1);

        if (empty($this->invite_code)) {
            $this->invite_code = db('user_promotion')
                ->where('user_id', $this->user_id)
                ->value('invite_code');
        }

        $Model = new ChallengeModel();
        $file_poster = $Model->poster_create($this->user_id, $room_id, true, ['invite_code' => $this->invite_code]);
        $link_join = url('join', '', true, true) . '?room_id=' . $room_id;

        $data = ['poster_path' => $file_poster, 'room_id' => $room_id, 'link_join' => $link_join];

        // $room = db('challenge_room')->where([['room_id', '=', $room_id]])->field('pic')->find();
        $day = ChallengeModel::challenge_day($this->user_id);
        // $income = ChallengeModel::income_room($this->user_id);
        $data['wx_share'] = [
            // 'title' => '早起打卡啦，我已坚持' . $day . '天，领了' . $income . '元奖金',
            'title' => '早起打卡啦，我已坚持' . $day . '天，可以领一笔现金奖金',
            'desc' => '坚持每天早起瓜分奖金，享健康生活，分享拼多多内部券再躺赚，享持久管道收入...',
            'imgUrl' => config('site.url') . '/static/img/wx_share.png', // $room['pic'],
            'link' => $link_join . '&invite_code=' . $this->invite_code
        ];

        // 微信分享
        if ($_SERVER['SERVER_ADDR'] == '59.110.54.15' && Common::isWeixin()) {
            $data['wx_config'] = Wechat::js()->getConfigArray(['onMenuShareAppMessage', 'onMenuShareTimeline']);
        }

        return view('', $data);
    }

    public function redpack()
    {
        $room_id = input('room_id', 0, 'intval');
        if (empty($room_id)) return $this->response('', 'room empty', 1);

        $room = db('challenge_room')->where('room_id', '=', $room_id)->find();
        if (empty($room) || $room['status'] != 1) return $this->response('', 'room empty', 1);

        $link_join = url('join', '', true, true) . '?room_id=' . $room_id;
        $is_join = $room['user_id'] == $this->user_id;
        if (!$is_join) {
            $is_join = db('Challenge')->where('room_id', '=', $room_id)->count(1) > 0;
        }

        $data = [
            'room_id' => $room_id,
            'room' => $room,
            'is_join' => $is_join ? 1 : 0,
            'link_join' => $link_join,
            'btn_right' => $room['user_id'] == $this->user_id ?
                '<a href="' . url('home/redpack/auto') . '?room_id=' . $room_id . '">给族员发福利</a>'
                : '<a href="' . $link_join . '">早起瓜分奖金</a>'
        ];

        $redpack = db('redpack_send')->where([
            ['room_id', '=', $room_id],
            ['show', '=', 1],
            ['surplus', '>', 0],
            ['redpack_id', 'exp', db()->raw('NOT IN(SELECT redpack_id FROM ' . db()->getTable('redpack_status') . ' WHERE user_id = ' . $this->user_id . ')')]
        ])->order('redpack_id desc')->limit(100)->select();

        if (!empty($redpack_id = input('redpack_id', 0, 'intval')) // 指定红包
            && (empty($redpack) || !in_array($redpack_id, array_column($redpack, 'redpack_id')))) {
            $res = db('redpack_send')->where([['redpack_id', '=', $redpack_id], ['room_id', '=', $room_id]])
                ->where('!EXISTS(SELECT * FROM ' . db()->getTable('redpack_status') . ' WHERE user_id = ' . $this->user_id . ' AND redpack_id = ' . $redpack_id . ')')
                ->find();
            if (!empty($res)) {
                $redpack[] = $res;
            }
        }

        $data['redpack'] = $redpack;
        if (!empty($redpack)) {
            $user_ids = array_column($redpack, 'user_id');
            $data['users'] = db('user')->where([['id', 'in', $user_ids]])->column('id,nickname,avator');
        }

        if (empty($this->invite_code)) {
            $this->invite_code = db('user_promotion')
                ->where('user_id', $this->user_id)
                ->value('invite_code');
        }

        $day = ChallengeModel::challenge_day($this->user_id);
        $data['wx_share'] = [
            // 'dialog_title' => '分享后即可领取现金红包',
            'title' => '早起打卡啦，我已坚持' . $day . '天，可以领一笔现金奖金',
            'desc' => '坚持每天早起瓜分奖金，享健康生活，分享拼多多内部券再躺赚，享持久管道收入...',
            'imgUrl' => config('site.url') . '/static/img/wx_share.png', // $room['pic'],
            'link' => $link_join . '&invite_code=' . $this->invite_code
        ];

        // 微信分享
        if ($_SERVER['SERVER_ADDR'] == '59.110.54.15' && Common::isWeixin()) {
            $data['wx_config'] = Wechat::js()->getConfigArray(['onMenuShareAppMessage', 'onMenuShareTimeline']);
        }

        return view('', $data);
    }

    public function note()
    {
        $room_id = input('room_id', 0, 'intval');
        if (empty($room_id)) return $this->response('', 'room empty', 1);

        $room = db('challenge_room')->where('room_id', '=', $room_id)->find();
        if (empty($room) || $room['status'] != 1) return $this->response('', 'room empty', 1);

        $link_join = url('join', '', true, true) . '?room_id=' . $room_id;

        // 已提现
        $withdraw_pass = db('user_withdraw')->where([
            ['user_id', '=', $this->user_id],
            ['status', '=', 1]
        ])->sum('draw_price');

        $income_room = Db::name('challenge')->where([
            ['user_id', '=', $this->user_id],
            ['room_id', '=', $room_id]
        ])->sum('income');
        if (is_null($income_room)) $income_room = 0;
        else $income_room /= 100;

        if ($this->user_id == $room['user_id']) {
            $leader_income = Db::name('user_money')->where([
                ['user_id', '=', $this->user_id],
                ['event', '=', 'leader_income'],
                ['event_id', '=', $room_id]
            ])->sum('money');

            if ($leader_income > 0) $income_room += $leader_income;
        }

        $data = [
            'room_id' => $room_id,
            'room' => $room,
            'is_me' => $room['user_id'] == $this->user_id,
            'link_join' => $link_join,
            'link_poster' => url('poster') . '&room_id=' . $room_id,
            'all_income' => User::all_income($this->user_id),
            'user' => User::find($this->user_id),
            'withdraw_pass' => round($withdraw_pass / 100, 2) ?: 0,
            'income_room' => $income_room
        ];

        if (empty($this->invite_code)) {
            $this->invite_code = db('user_promotion')
                ->where('user_id', $this->user_id)
                ->value('invite_code');
        }

        $data['user_level'] = User::level($this->user_id);
        $day = ChallengeModel::challenge_day($this->user_id);
        $data['wx_share'] = [
            'title' => '早起打卡啦，我已坚持' . $day . '天，可以领一笔现金奖金',
            'desc' => '坚持每天早起瓜分奖金，享健康生活，分享拼多多内部券再躺赚，享持久管道收入...',
            'imgUrl' => config('site.url') . '/static/img/wx_share.png', // $room['pic'],
            'link' => $link_join . '&invite_code=' . $this->invite_code
        ];

        // 微信分享
        if (Common::isWeixin()) {
            if ($_SERVER['SERVER_ADDR'] == '59.110.54.15') {
                $data['wx_config'] = Wechat::js()->getConfigArray(['onMenuShareAppMessage', 'onMenuShareTimeline']);
            }
        }

        return view('', $data);
    }

    public function discover()
    {
        $room_id = input('room_id', 0, 'intval');
        if (empty($room_id)) return $this->response('', 'room empty', 1);

        $room = db('challenge_room')->where('room_id', '=', $room_id)->find();
        if (empty($room) || $room['status'] != 1) return $this->response('', 'room empty', 1);

        $link_join = url('join', '', true, true) . '?room_id=' . $room_id;
        $redpack_count = db('redpack_send')->where([
                ['room_id', '=', $room_id],
                ['show', '=', 1],
                ['surplus', '>', 0],
                ['redpack_id', 'exp', db()->raw('NOT IN(SELECT redpack_id FROM ' . db()->getTable('redpack_status') . ' WHERE user_id = ' . $this->user_id . ')')]]
        )->count(1);

//        // 其他文章
//        $orther_article = db('Article')
//            ->field('id, title, author, info, read_num')
//            ->where('status = 1 and is_recommend = 1')
//            ->select();
//
//        // 文章用户信息
//        $authors = [];
//        foreach ($orther_article as &$val) {
//            if (!in_array($val['author'], $authors)) {
//                $authors[] = $val['author'];
//            }
//
//            $val['info'] = json_decode($val['info'], true);
//        }
//
//        if ($authors) {
//            $authors = db('user')->where([['id', 'in', $authors]])->column('nickname, avator', 'id');
//        }

        // 族长
        $leader = User::find($room['user_id']);
        $leader['invite_code'] = db('user_promotion')->where([['user_id', '=', $this->user_id]])->value('invite_code');

        $data = [
            'room_id' => $room_id,
            'room' => $room,
            'is_me' => $room['user_id'] == $this->user_id ? 1 : 0,
            'link_join' => $link_join,
            // 'link_poster' => url('poster') . '&room_id=' . $room_id,
            // 'all_income' => User::all_income($this->user_id),
            // 'user' => User::find($this->user_id),
            'leader' => $leader,
            'redpack_count' => $redpack_count,
//            'orther_article' => $orther_article,
//            'authors' => $authors,
//            'invite_code' => $invite_code
        ];

        // if ($orther_article)
        $data['link_article'] = url('home/article/detail');

        if (empty($this->invite_code)) {
            $this->invite_code = db('user_promotion')
                ->where('user_id', $this->user_id)
                ->value('invite_code');
        }

        $day = ChallengeModel::challenge_day($this->user_id);
        $data['wx_share'] = [
            'title' => '早起打卡啦，我已坚持' . $day . '天，可以领一笔现金奖金',
            'desc' => '坚持每天早起瓜分奖金，享健康生活，分享拼多多内部券再躺赚，享持久管道收入...',
            'imgUrl' => config('site.url') . '/static/img/wx_share.png', // $room['pic'],
            'link' => $link_join . '&invite_code=' . $this->invite_code
        ];

        // 微信分享
        if ($_SERVER['SERVER_ADDR'] == '59.110.54.15' && Common::isWeixin()) {
            $data['wx_config'] = Wechat::js()->getConfigArray(['onMenuShareAppMessage', 'onMenuShareTimeline']);
        }

        return view('', $data);
    }
}
