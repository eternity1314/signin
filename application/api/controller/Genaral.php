<?php

namespace app\api\controller;

use app\common\model\Common;
use app\common\model\Sms;
use app\common\model\User;
use app\common\model\Challenge;
use app\common\model\Pay;
use think\facade\Cache;
use app\common\model\GoodsModel;

class Genaral extends Base
{
    protected function initialize()
    {
        $token = input('token');
        if (empty($token)) {
            $token = request()->header('token');
        }

        if (!empty($token)) {
            parent::initialize();
        }
    }

    public function hello()
    {
        $time = request()->time();
        $today_time = Common::today_time();

        if (request()->isPost()) {
            parent::initialize();

            $msg = '';
            $mb = 0;
            $today_date = Common::today_date();
            if ($today_time + (60 * 60 * 3) <= $time && $time < $today_time + (60 * 60 * 8)) {
                $mb = 10;
                $msg = '恭喜您获得10M币，11:30再来还有20M币！';
            } elseif ($today_time + (60 * 60 * 8) <= $time && $time < $today_time + (60 * 60 * 11 + 60 * 30)) {
                $mb = 10;
                $msg = '11:30之后来说『午安』能获得更高奖励哦！';
            } elseif ($today_time + (60 * 60 * 11 + 60 * 30) <= $time && $time < $today_time + (60 * 60 * 14)) {
                $mb = 20;
                $msg = '恭喜您获得20M币，21:00再来还有30M币！';
            } elseif ($today_time + (60 * 60 * 14) <= $time && $time < $today_time + (60 * 60 * 19)) {
                $mb = 20;
                $msg = '21:00之后来说『晚安』能获得更高奖励哦！';
            } elseif ($today_time + (60 * 60 * 19) <= $time && $time < $today_time + (60 * 60 * 21)) {
                $mb = 20;
                $msg = '21:00之后来说『晚安』能获得更高奖励哦！';
            } elseif ($today_time + (60 * 60 * 21) <= $time && $time < $today_time + (60 * 60 * 3)) {
                $mb = 30;
                $msg = '恭喜您获得30M币，5:00再来还有10M币！';
            }

            if ($mb > 0) User::mb($this->user_id, $mb, 'hello', $today_date, '问候');

            return $this->response('', $msg);
        }

        $location = [];
        if (!empty(request()->header()['location'])) {
            $location['coord'] = request()->header()['location'];
        } elseif (request()->ip()) {
            $location['ip'] = request()->ip();
        }

        if ($location) {
            $city = Common::getLocation($location, 'city');
        }

        if (empty($city)) {
            $city = ['北京', '上海', '广州', '深圳'][mt_rand(0, 3)];
        }

        $config = config('cache.');
        $config['prefix'] = 'weather';
        $cache = Cache::connect($config);
        $c = $cache->get($city);

        if (empty($c) || $c['update_time'] < $today_time || $time - $c['update_time'] > 5 * 60 * 60) {
            try {
                \think\Loader::addAutoLoadDir(env('extend_path') . 'request');
                \Requests::register_autoloader();

                $res = \Requests::get('https://www.sojson.com/open/api/weather/json.shtml?city=' . $city);
                $res = json_decode($res->body, true);

                if (!empty($res)
                    && !empty($res['status'])
                    && $res['status'] == 200
                    && !empty($res['data'])
                    && !empty($res['data']['forecast'])) {
                    $site_url = config('site.url');
                    $type = $res['data']['forecast'][0]['type'];
                    $weather = $type;
                    if (!empty($res['data']['wendu'])) $weather .= ' ' . $res['data']['wendu'] . '℃';
                    if (!empty($res['data']['quality'])) $weather .= ' | 空气 ' . $res['data']['quality'];

                    if (strpos($type, '晴') !== false) $icon = $site_url . '/static/img/weather/qing.png';
                    elseif (strpos($type, '阴') !== false || strpos($type, '多云') !== false) $icon = $site_url . '/static/img/weather/yin.png';
                    elseif (strpos($type, '雨') !== false) $icon = $site_url . '/static/img/weather/yu.png';
                    elseif (strpos($type, '雾') !== false) $icon = $site_url . '/static/img/weather/wu.png';
                    elseif (strpos($type, '雪') !== false) $icon = $site_url . '/static/img/weather/xue.png';

                    $cache->set($city, ['weather' => $weather, 'icon' => $icon, 'update_time' => $time]);
                }
            } catch (\Exception $e) {
                // todo
            }
        } else {
            $weather = $c['weather'];
            $icon = $c['icon'];
        }

        if (empty($weather)) $weather = '晴 25℃ | 空气 优';
        if (empty($icon)) $icon = 'https://ss1.bdstatic.com/5eN1bjq8AAUYm2zgoY3K/r/www/aladdin/img/new_weath/bigicon/1.png';

        $data_out = [
            'icon' => $icon,
            'city' => $city,
            'weather' => $weather
        ];

        $hello = $welcome = '';
        if (input('source') == 'early') {
            $title = '亲，做了早起计划吗';
            $welcome = '创建族群';
            $data_out['welcome_link'] = url('home/challenge/room', '', '', true);
        } else {
            $today_time = Common::today_time();
            if ($today_time + (60 * 60 * 3) <= $time && $time < $today_time + (60 * 60 * 8)) {
                $hello = '早安';
                $welcome = "{$hello}，{$city}！";
            } elseif ($today_time + (60 * 60 * 8) <= $time && $time < $today_time + (60 * 60 * 11 + 60 * 30)) {
                $hello = '上午好';
                $welcome = "亲，{$hello}！";
            } elseif ($today_time + (60 * 60 * 11 + 60 * 30) <= $time && $time < $today_time + (60 * 60 * 14)) {
                $hello = '午安';
                $welcome = "{$hello}，{$city}！";
            } elseif ($today_time + (60 * 60 * 14) <= $time && $time < $today_time + (60 * 60 * 19)) {
                $hello = '下午好';
                $welcome = "亲，{$hello}！";
            } elseif ($today_time + (60 * 60 * 19) <= $time && $time < $today_time + (60 * 60 * 21)) {
                $hello = '下午好';
                $welcome = "亲，{$hello}！";
            } elseif ($today_time + (60 * 60 * 21) <= $time && $time < $today_time + (60 * 60 * 3)) {
                $hello = '晚安';
                $welcome = "{$hello}，{$city}！";
            }

            $title = "Hello ~ {$hello}！";
            $data_out['time'] = date('H:i', $time);
        }

        $data_out['title'] = $title;
        $data_out['welcome'] = $welcome;

        return $this->response($data_out);
    }

    public function warm_tips()
    {
        $table = db('warm_tips')->getTable();
        $sql_max = "SELECT MAX(id) FROM `{$table}`";
        $sql_min = "SELECT MIN(id) FROM `{$table}`";

        $data = db()->query("SELECT content FROM `{$table}` AS t1 JOIN (SELECT ROUND(RAND() * (($sql_max)-($sql_min))+($sql_min)) AS id) AS t2 WHERE t1.id >= t2.id ORDER BY t1.id LIMIT 1");

        if (empty($data)) $data = ['content' => '起床后别忘了喝杯温开水，清理下肠胃哦'];
        else $data = $data[0];

        $data['title'] = '温馨提示';

        return $this->response($data);
    }

    public function quote()
    {
        $table = db('quote')->getTable();
        $sql_max = "SELECT MAX(id) FROM `{$table}`";
        $sql_min = "SELECT MIN(id) FROM `{$table}`";

        $data = db()->query("SELECT content,source FROM `{$table}` AS t1 JOIN (SELECT ROUND(RAND() * (($sql_max)-($sql_min))+($sql_min)) AS id) AS t2 WHERE t1.id >= t2.id ORDER BY t1.id LIMIT 1");

        if (empty($data)) {
            $data = [
                'content' => '人在身处逆境时，适应环境的能力实在惊人。人可以忍受不幸，也可以战胜不幸，因为人有着惊人的潜力，只要立志发挥它，就一定能渡过难关',
                'source' => '卡耐基'
            ];
        } else {
            $data = $data[0];
        }

        $data['title'] = 'ONE · 简单生活';

        return $this->response($data);
    }

    public function challenge_recommend()
    {
        return $this->response([
            'room' => self::challenge_room(),
            'both' => self::challenge_both()
        ]);
    }

    public function challenge_room()
    {
        $time = request()->time();
        $where = [['recommend_time', '>', $time]];
        if (!empty(input('his_id'))) $where[] = ['room_id', 'not in', input('his_id')];
        elseif (!empty(input('cur_id'))) $where[] = ['room_id', 'not in', input('cur_id')];

        $count = db('challenge_room')->where($where)->count(1);

        if ($count == 0) {
            if (!empty($this->app_token) && !empty($this->app_token['city'])) {
                $city = $this->app_token['city'];
            } else {
                $city = Challenge::room_option('city');
                $city = $city[mt_rand(0, count($city) - 1)];
            }

            $where = [['city', '=', $city], ['expire_time', '>', $time]];
            if (!empty(input('his_id'))) $where[] = ['room_id', 'not in', input('his_id')];
            elseif (!empty(input('cur_id'))) $where[] = ['room_id', 'not in', input('cur_id')];

            $count = db('challenge_room')->where($where)->count(1);
        }

        if ($count == 0) {
            // $where = [['user_id', '=', 0]];
            $where = [['', 'exp', db()->raw(User::system_user_filter())]];
            if (!empty(input('his_id'))) $where[] = ['room_id', 'not in', input('his_id')];
            elseif (!empty(input('cur_id'))) $where[] = ['room_id', 'not in', input('cur_id')];

            $count = db('challenge_room')->where($where)->count(1);
        }

        if ($count == 0) {
            $data = [];
        } else {
            $data = db('challenge_room')->where($where)->field('room_id,avator,title,day,btime,etime,income_rate')->order('room_id DESC')->limit(mt_rand(0, $count - 1), 1)->select()[0];
            $data['challenge_time'] = Challenge::convert_signin_time($data)['stime']; // '约定打卡时间 ' .
            $data['income_rate'] = sprintf('%.3f', $data['income_rate']);
            // $data['income_rate'] = '历史日均收益率 ' . $data['income_rate'] . '%';

            if (!empty($data['avator']) && strpos($data['avator'], 'http://') === false) {
                $data['avator'] = config('site.url') . $data['avator'];
            }

            unset($data['btime']);
            unset($data['etime']);
        }

//        $data = [
//            'avator' => 'http://wx.qlogo.cn/mmopen/O19kUBfHXBPywYTxzcphW3AiaDHVY8sf8Tm2iaVoTLo9iaYcvKu5xqicL9MysbokXHQTQEp9IMT67vJgiad8GrtBrAJ060eMkR7mN/0',
//            'title' => '广州早起族',
//            'day' => '连续3天',
//            'challenge_time' => '约定打卡时间 08:00~09:00',
//            'income_rate' => '历史日均收益率 50%'
//        ];

        if (request()->action() != __FUNCTION__) {
            return $data;
        }

        if (empty($data)) return $this->response('', '没有更多了', 1);
        return $this->response($data);
    }

    public function challenge_both()
    {
        $today_date = Common::today_date();
        $where = [
            ['status', '=', 0],
            'recommend' => ['recommend', '=', 1],
            ['join_date', '=', $today_date]
        ];
        if (!empty(input('his_id'))) $where['launch_cid'] = ['launch_cid', 'not in', input('his_id')];
        elseif (!empty(input('cur_id'))) $where['launch_cid'] = ['launch_cid', 'not in', input('cur_id')];

        if (!empty($this->user_id)) $where['launch_uid'] = ['launch_uid', '<>', $this->user_id];
        $count = db('challenge_both')->where($where)->count(1);

        if ($count == 0) {
            unset($where['recommend']);
            if (!empty($where['launch_uid'])) $where['launch_uid'][0] = 'user_id';
            if (!empty($where['launch_cid'])) $where['launch_cid'][0] = 'challenge_id';

            $count = db('challenge')->where($where)->count(1);
            if ($count > 0) $limit = mt_rand(0, $count - 1) . ',' . 1;
        } else {
            $challenge_id = db('challenge_both')->where($where)->order('both_id DESC')->limit(mt_rand(0, $count - 1), 1)->column('launch_cid')[0];
            $where = [['challenge_id', '=', $challenge_id]];
            $limit = 1;
        }

        if ($count == 0) {
            $data = [];
        } else {
            $data = db('challenge')->where($where)->field('challenge_id,price,day,btime,etime,user_id')->order('challenge_id DESC')->limit($limit)->select()[0];
            $data['challenge_time'] = Challenge::convert_signin_time($data)['stime']; // '约定打卡时间 '
            $data['price'] /= 100; // $data['price'] = '契约金 ￥' . ($data['price'] / 100);

            $user = User::find($data['user_id']);
            $data['nickname'] = $user['nickname'];
            $data['avator'] = $user['avator'];
            if (!empty($data['avator']) && strpos($data['avator'], 'http://') === false) {
                $data['avator'] = config('site.url') . $data['avator'];
            }

            unset($data['btime']);
            unset($data['etime']);
            unset($data['user_id']);
        }

//        $data = [
//            'avator' => 'http://wx.qlogo.cn/mmopen/O19kUBfHXBPywYTxzcphW3AiaDHVY8sf8Tm2iaVoTLo9iaYcvKu5xqicL9MysbokXHQTQEp9IMT67vJgiad8GrtBrAJ060eMkR7mN/0',
//            'nickname' => '你的名字',
//            'day' => '连续3天',
//            'challenge_time' => '约定打卡时间 08:00~09:00',
//            'price' => '契约金 ￥10.00'
//        ];

        if (request()->action() != __FUNCTION__) {
            return $data;
        }

        if (empty($data)) return $this->response('', '没有更多了', 1);
        return $this->response($data);
    }

    public function ad()
    {
        return $this->response();

        return $this->response([
            'title' => '迪奥时尚佳人的选择，让你变得白嫩，老公再也不嫌弃我了',
            'pic' => 'http://p2.ifengimg.com/a/2018_15/c634acbe844cea6_size70_w780_h439.jpg'
        ]);
    }

    public function bat()
    {
        /*
        $a = [
            [
                'url' => 'http://signin_tp5.com/api/goods/take_act',
                'type' => 'POST',
                'data' => 'gid=37297&address_id=1&pay_rmb=1&day=1'
            ],
            [
                'url' => 'http://signin_tp5.com/api/genaral/challenge_both',
                'type' => 'GET',
                'data' => ''
            ],
        ];
        */
        $headers = Common::get_all_headers();
        $token = isset($headers['Token']) ? $headers['Token'] : '';
        $a = json_decode(htmlspecialchars_decode(request()->post('request_urls')), true);
        foreach ($a as $key => $val) {
            if (!isset($val['data']) || !$val['data']) {
                $val['data'] = 'token=' . $token;
            } else {
                $val['data'] .= '&token=' . $token;
            }

            $data[$key] = json_decode(Common::curl($val['url'], $val['data'], $val['type']), true);
        }

        return $this->response($data);
    }


    // 发送验证码
    public function send_sms()
    {
        $mobile = request()->get('mobile');
        $check_unique = request()->get('check_unique', 0);
        $unique_type = request()->get('unique_type', 0);

        if (!preg_match("/^1\d{10}$/", $mobile)) {
            return $this->response([], '手机号码有误！', 20000);
        }

        if ($check_unique) {
            $rs = User::find(['mobile' => $mobile], 'id');
            if ($unique_type) {
                if (!$rs) {
                    return $this->response([], '该号码未在平台上注册过，请使用微信直接登陆！', 20000);
                }
            } else {
                if ($rs) {
                    return $this->response([], '手机号码已注册！', 20000);
                }
            }
        }

        $code = rand(100000, 999999);

        $model_sms = new Sms();
        $res = $model_sms->send_code($mobile, $code, '【微选生活】您的验证码：' . $code . '，有效期10分钟，如非本人操作请忽略。别忘了把好东西分享给身边的朋友哦！');

        return $this->response($res[0], $res[1], $res[2]);
    }

    // 我的设置 - 找回密码
    public function find_pass()
    {
        $this->checkSign();

        $mobile = request()->get('mobile');
        $code = request()->get('code');
        $pass = request()->get('pass');

        if (!$mobile || !$code || !$pass) {
            return $this->response([], '参数有误！', 40000);
        }

        if (strlen($pass) < 32) {
            return $this->response([], '参数有误！', 40000);
        }

        if (!preg_match("/^1\d{10}$/", $mobile)) {
            return $this->response([], '手机号码有误！', 40000);
        }

        $user = User::find(['mobile' => $mobile], 'id');
        if (!$user) {
            return $this->response([], '手机号码未绑定！', 40000);
        }

        // 检查验证码
        $rs = Sms::verify($mobile, $code);
        if ($rs != 'SUCCESS') {
            return $this->response($rs[0], $rs[1], $rs[2]);
        }

        User::update(['password' => md5($pass)], ['id' => $user['id']]);

        return $this->response([], '操作成功', 0);
    }

    public function order_query()
    {
        $out_trade_no = input('out_trade_no');
        if (empty($out_trade_no)) {
            return $this->response('', 'param missing', 1);
        }

        $result_code = db('pay')->where('out_trade_no', '=', $out_trade_no)->value('result_code');
        if ($result_code === 'SUCCESS') {
            return $this->response();
        }

        return $this->response('', '', 1);
    }

    // 首页商品
    public function home_featured()
    {
        $key = '早餐';
        $where = 'status = 1 AND coupon_time > ' . request()->time() . ' AND name LIKE \'%' . $key . '%\'';
        //$where = 'name LIKE \'%'. $key .'%\'';
        $count = GoodsModel::where('name', 'like', '%' . $key . '%')->limit(500)->count();

        $goods = GoodsModel::where('name', 'like', '%' . $key . '%')->field('id, goods_id, name, img, price, commission, coupon_price, sales_num')->limit(mt_rand(0, $count), 4)->select();
        foreach ($goods as &$val) {
            $val['commission'] = sprintf('%.2f', ($val['price'] * $val['commission'] * GoodsModel::config('return_commission')) / 100000);
            $val['price'] = '￥' . sprintf('%.2f', $val['price'] / 100);
            $val['coupon_price'] = intval($val['coupon_price'] / 100);
        }
        return $this->response(['goods' => $goods]);
    }

    public function check_withdraw()
    {
        $id = explode(',', input('id'));
        if (!$id) {
            return $this->response([], '参数错误', 40001);
        }

        if (is_array($id)) {
            foreach ($id as $val) {
                $data = db('user_withdraw')->where('id', $val)->find();
                if ($data['status'] == 0) {
                    Pay::merchant_pay($data);
                }
            }
        } else {
            $data = db('user_withdraw')->where('id', $id)->find();
            if ($data['status'] == 0) {
                Pay::merchant_pay($data);
            }
        }

        $this->response([], 'success', 0);
    }

    public function app_active()
    {
        $this->checkMethod(['POST']);
        $this->checkSign();

        $data = input('post.');

        if (empty($data['client_code']) || empty($data['client_system']) || empty($data['core_version'])) {
            return $this->response('', 'param missing', 1);
        }

        $client_code = $data['client_code'];

        $count = db('app_active')->where([['client_code', '=', $client_code]])->count(1);
        if ($count > 0) return $this->response();

        $data['client_ip'] = request()->ip();
        $data['add_time'] = request()->time();
        db('app_active')->strict(false)->insert($data);

        return $this->response();
    }

    public function is_checking()
    {
        $core_version = input('core_version');

        $is_checking = 0;
        if ($core_version) {
            $status = db('app_version')->where('core_version', '=', $core_version)->value('status');
            if ($status == 3) {
                $is_checking = 1; // 审核中
            }
        }

        return $this->response(['result' => ['is_checking' => $is_checking]]);
    }

    public function ad_data()
    {
        $data = [
            'idfa' => input('idfa'),
            'reg_date' => input('reg_date'),
            'os' => input('os', 0),
            'version' => input('version', ''),
            'appstore' => input('appstore', 0),
            'platform' => input('platform', ''),
            'ip' => input('ip', ''),
            'mac' => input('mac', ''),
            'add_time' => request()->time(),
            'uid' => input('uid', ''),
            'appid' => input('appid', '')
        ];

        $count = db('ad_data')->where('idfa', '=', $data['idfa'])->count();

        $data['add_time'] = request()->time();
        $res = db('ad_data')->insert($data);

        if (!$res) {
            return $this->response([], 'fail', 40001);
        }

        if ($count) {
            return $this->response([
                'idfa' => $data['idfa'],
                'reg_date' => $data['reg_date'],
                'result' => false
            ], 'success', 0);
        } else {
            Common::curl(input('callback') . '?appid=' . $data['appid'] . '&idfa=' . $data['idfa'] . '&Reg_date=' . strtotime($data['reg_date']), [], 'GET');
            //redirect(input('callback').'?appid='. $data['appid'] .'&idfa='.$data['idfa'].'&regdate='.strtotime($data['reg_date']));
            return $this->response([
                'idfa' => $data['idfa'],
                'reg_date' => $data['reg_date'],
                'result' => true
            ], 'success', 0);
        }
    }

    public function human()
    {
        $limit = input('limit', 20, 'intval');
        $user_ids = User::system_user_rand($limit);
        $users = db('user')->where([['id', 'in', $user_ids]])->field('nickname,avator')->select();

        $site_url = config('site.url');
        foreach ($users as $k => $v) {
            $users[$k]['avator'] = $site_url . $v['avator'];
        }

        return $this->response(['list' => $users]);
    }

    public function promote_commission_case()
    {
        $limit = input('limit', 20, 'intval');
        $user_ids = User::system_user_rand($limit);
        $users = db('user')->where([['id', 'in', $user_ids]])->field('nickname,avator')->select();

        $data = [];
        $site_url = config('site.url');
        foreach ($users as $v) {
            $data[] = [
                'nickname' => $v['nickname'],
                'avator' => $site_url . $v['avator'],
                'price' => (string)(mt_rand(199, 1200) / 10),
                'time' => mt_rand(1, 10) . '分钟前'
            ];
        }

        return $this->response(['list' => $data]);
    }

    public function add_wxcrowd()
    {
        $is_show = 0;
        $wxchat = 'kangnaixin1989';

        return $this->response(['result' => [
            'is_show'   => $is_show,
            'wxchat'    => $wxchat,
            'weak_tips' => '复制客服微信号成功，快去微信添为好友邀你进群'
        ]]);
    }
}
