<?php

namespace app\common\model;

use EasyWeChat\Foundation\Application;
use think\Db;
use think\facade\Config;
use think\facade\Log;
use think\Model;

class User extends Model
{
    public static function find($where, $field = true)
    {
        if (is_numeric($where)) {
            $user = env('USER.' . $where);
            if (empty($user)) {
                $user = DB::name('user')->where(['id' => $where])->field(true)->find();
                \think\facade\Env::set('USER.' . $where, $user);
            }

            return $user;
        }

        return DB::name('user')->where($where)->field($field)->find();
    }

    public static function createUser($data)
    {
        if (empty($data['avator']) || strlen($data['avator']) < 10) {
            if (!empty($data['headimgurl'])) {
                $data['avator'] = $data['headimgurl'];
            } else {
                $data['avator'] = '/static/img/head.png';
            }
        }

        if (empty($data['nickname'])) {
            $data['nickname'] = '某人';
        }

        $data['wxname'] = $data['nickname'];
        $data['add_time'] = request()->time();

        $user_id = Db::name('user')->strict(false)->insertGetId($data);

        if ($user_id) {
            // 新人奖励M币
            //self::mb($user_id, 100, 'new_user', $user_id, '新人');

            // 邀请码
            $invite_code = Db::name('user_invite_code')->find();
            if (!$invite_code) {
                // 初始值
                $app_config = db('siteinfo')->where('key', 'app_setting')->value('value');
                $invite_code_start = json_decode($app_config, true)['invite_code_start'];
                if (!$invite_code_start) {
                    $invite_code_start = 1325238;
                }

                // 生成1000 条
                $arr = range($invite_code_start, $invite_code_start + 1500);
                $arr = Common::arr_shuffle($arr, 1000, '');

                // 获取第一条
                $invite_code = array_shift($arr)['code'];

                db('user_invite_code')->insertAll($arr);
                $app_config = [
                    'invite_code_start' => $invite_code_start + 1501
                ];
                db('siteinfo')->where('key', 'app_setting')->update(['value' => json_encode($app_config)]);
            } else {
                db('user_invite_code')->where('id', $invite_code['id'])->delete();
                $invite_code = $invite_code['code'];
            }

            db('user_promotion')->insert([
                'user_id' => $user_id,
                'p_id' => 0,
                'invite_code' => $invite_code,
                'is_couple_weal' => 1
            ]);
            
            db('user_level')->insert([
                'user_id' => $user_id,
                'level' => 1,
                'expier_time' => request()->time() + 315360000,
                'add_time' => request()->time()
            ]);
        } else {
            Log::record('user create error,data:' . json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        return $user_id;
    }

    public static function me()
    {
        $user_id = env('ME');
        $user = env('USER.' . $user_id);
        if (empty($user)) {
            $user = self::find($user_id);
            \think\facade\Env::set('USER.' . $user_id, $user);
        }

        return $user;
    }

    public static function money($user_id, $money = 0, $event = '', $event_id = 0, $event_name = '')
    {
//        $me = env('ME');
//        if (!empty($me) && $me['id'] == $user_id) {
//            if (empty($me['balance'])) {
//                $me = self::me(true);
//            }
//
//            $balance = $me['balance'];
//        } else {
//            $user = self::find($user_id, 'balance');
//            $balance = $user['balance'];
//        }

        $user = env('USER.' . $user_id);
        if (empty($user)) {
            $user = self::find($user_id);
            if (empty($money)) {
                \think\facade\Env::set('User.' . $user_id, $user);
            }
        }

        $balance = $user['balance'];

        if (empty($money)) {
            return $balance;
        }

        if (empty($event) || empty($event_id) || empty($event_name)) {
            return ['', 'param error', 1];
        }

        $money = intval($money);
        $balance += $money;
        if ($balance < 0) {
            return ['', 'user balance not enough', 1];
        }

        $data = [
            'user_id' => $user_id,
            'money' => $money / 100,
            'balance' => $balance / 100,
            'event' => $event,
            'event_id' => $event_id,
            'event_name' => $event_name,
            'add_time' => request()->time()
        ];

        Db::transaction(function () use ($data, &$flow_id, &$res) {
            $flow_id = Db::name('user_money')->insertGetId($data);
            if (!$flow_id) {
                return false;
            }

            $res = DB::name('user')->where('id', '=', $data['user_id'])->inc('balance', $data['money'] * 100)->update();
            if (!$res) {
                return false;
            }
        });

        if (empty($flow_id)) {
            Log::record('user money modify error');
            return ['', 'user money error', 1];
        }

        if (empty($res) && $money > 0) {
            Log::record('user balance modify error');
            return ['', 'user balance error', 1];
        }

//        if (!empty($me)) {
//            $me['mb'] = $balance;
//            \think\facade\Env::set('ME', $me);
//        }

        $user['balance'] = $balance;
        \think\facade\Env::set('User.' . $user_id, $user);

        return ['flow_id' => $flow_id, 'balance' => $balance];
    }

    public static function mb($user_id, $mb = 0, $event = '', $event_id = 0, $event_name = '')
    {
        $user = env('USER.' . $user_id);
        if (empty($user)) {
            $user = self::find($user_id);
            if (empty($mb)) {
                \think\facade\Env::set('User.' . $user_id, $user);
            }
        }

        $balance = $user['mb'];

        if (empty($mb)) {
            return $balance;
        }

        if (empty($event) || empty($event_id) || empty($event_name)) {
            return ['', 'param error', 1];
        }

        $balance += $mb;
        if ($balance < 0) {
            return ['', 'user mb not enough', 1];
        }

        $data = [
            'user_id' => $user_id,
            'mb' => $mb,
            'balance' => $balance,
            'event' => $event,
            'event_id' => $event_id,
            'event_name' => $event_name,
            'add_time' => request()->time()
        ];

        Db::transaction(function () use ($data, &$flow_id, &$res) {
            $flow_id = Db::name('user_mb')->insertGetId($data);
            if (!$flow_id) {
                return false;
            }

            $res = DB::name('user')->where('id', '=', $data['user_id'])->inc('mb', $data['mb'])->update();
            if (!$res) {
                return false;
            }
        });

        if (empty($flow_id)) {
            Log::record('user mb modify error');
            return ['', 'user mb error', 1];
        }

        if (empty($res)) {
            Log::record('user mb modify error');
            return ['', 'user mb error', 1];
        }

        $user['mb'] = $balance;
        \think\facade\Env::set('User.' . $user_id, $user);

        return ['flow_id' => $flow_id, 'mb' => $balance];
    }

    public static function location($user_id, $location = [])
    {
        if (empty($location)) {
            return ['position'];
        }

        // set
    }

    public static function wxOauthUser()
    {
        $code = input('code');
        if (empty($code) && !empty(input('auth_code'))) {
            $code = input('auth_code');
            $config = Config::pull('opwx_app');
            $openid_key = 'openid_app';
        } else {
            $config = Config::pull('wechat');
            $openid_key = 'openid';
        }

        $app = new Application($config);
        $oauth = $app->oauth;

        if (empty($code)) {
            $oauth->redirect()->send();
            // redirect('http://a.app.qq.com/o/simple.jsp?pkgname=com.mayixiguan')->send();

            return false;
        } else {
            $token = $oauth->getAccessToken($code);
            if (isset($token['errcode']) || empty($token)) return false;

            if (isset($token['unionid'])) $user = self::find(['unionid' => $token['unionid']]);
            else $user = self::find(['openid' => $token['openid']]);

            if (empty($user)) {
                $user = $oauth->user($token)->getOriginal();
                if (isset($user['errcode']) || empty($user)) return false;

                if ($openid_key != 'openid') {
                    $user[$openid_key] = $user['openid'];
                    unset($user['openid']);
                }

                $user['id'] = self::createUser($user);
                if (empty($user['id'])) return false;
            } else {
                $data = [];
                if (empty($user['nickname']) || empty($user['avator'])
                    || (strpos($user['avator'], 'http://') === false && strpos($user['avator'], '/media/') === false)) {
                    $data = $oauth->user($token)->getOriginal();
                    $data['avator'] = $data['headimgurl'];
                    unset($data['openid']);
                }

                if (empty($user[$openid_key])) {
                    $data[$openid_key] = $user[$openid_key] = $token['openid'];

                    Activity::pick_new_bag_award($user['id']); // 天天拆现金翻倍
                    Activity::po_buy_one_award($user['id']); // 0.1元购翻倍

                    $sub_user = Db::name('user_tier')
                        ->alias('ut')
                        ->field('ul.user_id, ul.level')
                        ->join('user_level ul', 'ul.user_id = ut.pid', 'LEFT')
                        ->where('ut.user_id', '=', $user['id'])
                        ->find();
                    if ($sub_user && $sub_user['level'] > 1) {
                        db('user_dynamic')->insert([
                            'user_id' => 0,
                            'nickname' => '微选生活',
                            'avator' => '/static/img/logo.png',
                            'receive_uid' => $sub_user['user_id'],
                            'event' => 'invite_success',
                            'event_id' => $user['id'],
                            'data' => serialize([
                                'title' => '邀请成功！',
                                'content' => '恭喜您成功邀请'. (isset($data['nickname']) ? $data['nickname'] : $user['nickname']) .'加入团队，快带小伙伴开启创业之旅吧~'
                            ]),
                            'status' => 1,
                            'add_time' => request()->time()
                        ]);

                        db('xinge_task')->insert([
                            'user_id' => $user['id'],
                            'event' => 'invite_success',
                            'data' => serialize([
                                'title' => '邀请成功！',
                                'content' => '恭喜您成功邀请'. (isset($data['nickname']) ? $data['nickname'] : $user['nickname']) .'加入团队，快带小伙伴开启创业之旅吧~'
                            ])
                        ]);
                    }
                }

                if (!empty($data)) {
                    Db::name('user')->where('id', '=', $user['id'])->strict(false)->update($data);
                }
            }

//            session('user',
//                [
//                    'id' => $user['id'],
//                    'openid' => $user['openid'],
//                    'unionid' => $user['unionid']
//                ]
//            );

            return $user;
        }
    }

    public static function headimg_create_thumb($user_data, $size = 130)
    {
        $save_path = config('app.poster.root_path') . 'head/';
        $save_name = md5($user_data['id']) . '.jpg';
        $file_head = $save_path . $save_name;

        if (!file_exists($file_head)) {
            if (strpos($user_data['avator'], 'http://') !== false || strpos($user_data['avator'], 'https://') !== false) {
                // 下载图片
                Common::curlDownload($user_data['avator'], $save_path, $save_name);
            } elseif (strpos($user_data['avator'], 'media/uploads') !== false) {
                // 用户上传的头像
                copy('.' . $user_data['avator'], $file_head);
            }

            if (!file_exists($file_head)) {
                return '';
            }

            $img = \think\Image::open($file_head);
            $img->thumb($size, $size, \think\Image::THUMB_FIXED)->save($file_head);

            if (!file_exists($file_head)) {
                return '';
            }
        }

        return $file_head;
    }

    public function invite_poster_create($user_id, $domain = true, $para)
    {
        $root_path = Config::get('app.poster.root_path');
        $today_time = strtotime(date('Y-m-d'));

        // 二维码
        $url = config('site.url') . "/user/invite_page?invite_code=" . $para['invite_code'];
        $file_qrcode = $root_path . 'qrcode/invite/' . $user_id . '.png';
        if (!file_exists($file_qrcode)) {
            $rs = Common::make_qrcode($url, $file_qrcode);
            if ($rs == '') {
                return '';
            }
        }

        // 用户信息
        $user_data = self::find($user_id, 'id, nickname, avator');
        // 我的收益
        $bonus = 10;
        // 计划天数
        $plan_day = 11;
        // 早起天数
        $signin_day = 21;
        $signin_day = $signin_day ? $signin_day : 0;

        // 下载头像
        $file_head = self::headimg_create_thumb($user_data);
        if (!$file_head && !is_file($file_head)) {
            $file_head = $root_path . 'head/default.png';
        }

        $file_poster = [];
        for ($num = 1; $num < 5; $num++) {
            $item_file = $root_path . 'poster/invite/u' . $user_id . '_bg' . $num . '.jpg';

            // 是否更新海报
            if (file_exists($item_file)) {
                $file_time = filemtime($item_file);

                if ($file_time > ($today_time + 11 * 3600)) {
                    $file_poster[] = [
                        'path' => $item_file
                    ];
                    continue;
                }
            }
            $img = \think\Image::open($root_path . 'poster/bg/invite_bg' . $num . '.jpg');
            $font_path = realpath('./static/fonts/PINGFANG.TTF');

            switch ($num) {
                case 1 :
                    $size_qrcode = 206;
                    $x_qrcode = 270;
                    $y_qrcode = 488;

                    $size_head = 128;
                    $x_head = 453;
                    $y_head = 827;

                    $img->water($file_qrcode, [$x_qrcode, $y_qrcode], 100, ['width' => $size_qrcode])
                        ->water($file_head, [$x_head, $y_head], 100, ['width' => $size_head, 'radius' => true])
                        ->text($user_data['nickname'], $font_path, 32, [106, 39, 27, 0], \think\Image::WATER_NORTHWEST, [220, 872])
                        ->text('邀请码：' . $para['invite_code'], $font_path, 21, [106, 39, 27, 0], \think\Image::WATER_CENTER, [0, 92])
                        ->text('邀请码：' . $para['invite_code'], $font_path, 21, [106, 39, 27, 0], \think\Image::WATER_CENTER, [1, 92]);
                    // 文字粗体
                    for ($i = 1; $i < 6; $i++) {
                        $img->text('到 现 在 早 起 了 ' . $signin_day . ' 天', $font_path, 30, [255, 255, 255, 0], \think\Image::WATER_NORTHEAST, [-213 + $i, 1124])
                            ->text('一 共 赚 了 ' . $bonus . ' 元', $font_path, 30, [255, 255, 255, 0], \think\Image::WATER_NORTHEAST, [-213 + $i, 1190]);
                    }
                    $img->save($item_file);
                    break;
                case 2 :
                    $size_qrcode = 130;
                    $x_qrcode = 165;
                    $y_qrcode = 1092;

                    $size_head = 106;
                    $x_head = 368;
                    $y_head = 328;

                    $img->water($file_qrcode, [$x_qrcode, $y_qrcode], 100, ['width' => $size_qrcode])
                        ->water($file_head, [$x_head, $y_head], 100, ['width' => $size_head, 'radius' => true])
                        ->text('我是: ' . $user_data['nickname'], $font_path, 19, [105, 57, 9, 0], \think\Image::WATER_NORTHWEST, [85, 372])
                        ->text('邀请码：' . $para['invite_code'], $font_path, 20, [105, 57, 9, 0], \think\Image::WATER_NORTHWEST, [390, 468])
                        ->text('邀请码：' . $para['invite_code'], $font_path, 20, [105, 57, 9, 0], \think\Image::WATER_NORTHWEST, [391, 468]);
                    // 文字粗体
                    for ($i = 1; $i < 3; $i++) {
                        $img->text($plan_day . '天的早起计划', $font_path, 20, [249, 53, 66, 0], \think\Image::WATER_NORTHWEST, [334 + $i, 707])
                            ->text($signin_day . '天', $font_path, 20, [249, 53, 66, 0], \think\Image::WATER_NORTHWEST, [363 + $i, 854])
                            ->text($bonus . '元的早起奖励了哦', $font_path, 20, [249, 53, 66, 0], \think\Image::WATER_NORTHWEST, [268 + $i, 997]);
                    }
                    $img->save($item_file);
                    break;
                case 3 :
                    $size_qrcode = 232;
                    $x_qrcode = 258;
                    $y_qrcode = 958;

                    $img->water($file_qrcode, [$x_qrcode, $y_qrcode], 100, ['width' => $size_qrcode])
                        ->text('邀请码：' . $para['invite_code'], $font_path, 19, [16, 3, 12, 0], \think\Image::WATER_CENTER, [0, 236])
                        ->text('邀请码：' . $para['invite_code'], $font_path, 19, [16, 3, 12, 0], \think\Image::WATER_CENTER, [1, 236])
//                     ->water($file_head, [$x_head, $y_head], 100, ['width' => $size_head, 'radius' => true])
//                     ->text('我是 '. $user_data['nickname'], $font_path, 19, [253, 226, 23, 0], \think\Image::WATER_CENTER, [-207, -16])
//                     ->text('已坚持早起'. $signin_day .'天', $font_path, 19, [253, 226, 23, 0], \think\Image::WATER_CENTER, [207, -16])
//                     ->text('长按识别下载蚂蚁习惯', $font_path, 18, [255, 255, 255, 0], \think\Image::WATER_SOUTH, [8, -256])
                        ->save($item_file);
                    break;
                default :
                    $size_qrcode = 234;
                    $x_qrcode = 268;
                    $y_qrcode = 746;

                    $size_head = 98;
                    $x_head = 326;
                    $y_head = 550;

                    $img->water($file_qrcode, [$x_qrcode, $y_qrcode], 100, ['width' => $size_qrcode])
                        ->water($file_head, [$x_head, $y_head], 100, ['width' => $size_head, 'radius' => true])
                        ->text('邀请码：' . $para['invite_code'], $font_path, 19, [103, 25, 36, 0], \think\Image::WATER_CENTER, [0, 36])
                        ->text('邀请码：' . $para['invite_code'], $font_path, 19, [103, 25, 36, 0], \think\Image::WATER_CENTER, [1, 36])
                        ->save($item_file);
                    break;
            }

            $file_poster[] = [
                'path' => $item_file
            ];
        }

        if ($domain) {
            foreach ($file_poster as &$val) {
                $val['path'] = config('site.url') . trim($val['path'], '.');
            }
        }

        return $file_poster;
    }


    public function withdraw($user_id, $data)
    {
        if (!isset($data['price']) || !is_numeric($data['price'])) {
            return [[], '金额输入不正确', 40000];
        }

        $data['price'] *= 100;
        if ($data['price'] < 100) {
            return [[], '提现金额不能低于1元', 40000];
        }

        $user_info = self::find($user_id, 'balance, withdraw_status');
        if ($user_info['withdraw_status'] == 0) {
            return [[], '暂时不能提现', 40000];
        }

        $balance = $user_info['balance'];
        if ($balance < $data['price']) {
            return [[], '提现金额不能超过账户余额', 40000];
        }

        // 转账方式
        if (!$data['pay_method'] || !in_array($data['pay_method'], ['wx', 'ali'])) {
            return [[], '参数错误', 40000];
        }

        if ($data['pay_method'] == 'wx') {
            // 查看提现正在使用的公众号
            $withdraw_tencent_id = Db::name('user_withdraw_tencent')->where('status', 1)->value('id');
            if (!$withdraw_tencent_id) {
                return [[], '参数错误', 40000];
            }

            // 用户是否已关注
            $openid = Db::name('user_withdraw_tencent_relation')->where([
                ['user_id', '=', $user_id],
                ['withdraw_tencent_id', '=', $withdraw_tencent_id]
            ])->value('openid');

            $user_proof = $openid;
            if (!$user_proof) {
                return [[], '请先关注公众号（或取消关注后再试）', 40000];
            }

        } else {
            if (!isset($data['ali_account']) || !$data['ali_account']) {
                return [[], '请输入支付宝账号', 40000];
            }

            $user_proof = $data['ali_account'];
        }

        $todaytime = Common::today_time();

        // 今日提现次数
        $today_withdraw_count = Db::name('user_withdraw')->where([
            ['user_id', '=', $user_id],
            ['add_time', 'between', [$todaytime, $todaytime + 86400]]
        ])->count();
        if ($today_withdraw_count > 2) {
            //return [[], '当天最多提现3次，请明日再来！', 40001];
        }

        // 今日提现金额
        $today_withdraw = Db::name('user_withdraw')->where([
            ['status', 'in', [0, 1]],
            ['user_id', '=', $user_id],
            ['add_time', 'between', [$todaytime, $todaytime + 86400]]
        ])->sum('price');

        // 提现额度
        $user_identity_data = UserIdentity::where('user_id', $user_id)->field('real_name, status')->find();
        if ($user_identity_data && $user_identity_data->status == 1) {
            // 审核通过
            $withdraw_limit = 10000;
        } else {
            // 未通过
            $withdraw_limit = 3000;
        }

        // 超出额度
        if (($today_withdraw + $data['price']) > ($withdraw_limit * 100)) {
            $can_withdraw = $withdraw_limit - $today_withdraw / 100 > 1 ? round($withdraw_limit - $today_withdraw / 100, 2) : 0;

            return [
                [
                    'status' => $user_identity_data && $user_identity_data->status == 1 ? 1 : 2,
                    'btn' => $user_identity_data && $user_identity_data->status == 1 ? '确定' : '提高额度到10000元',
                    'text' => '亲，为了保障资金安全及第三方支付的规定限制，单日最高提现金额为 ¥ ' . $withdraw_limit . '，今天您已提现 ¥ ' . round($today_withdraw / 100, 2) . '，还能再提 ¥ ' . $can_withdraw . ' ，请重新输入提现金额'
                ], '', 0
            ];
        }

        $service_fee = 0;
        // 提现次数是否超过5次
        $rs = Db::name('user_withdraw')->where([
            ['user_id', '=', $user_id],
            ['status', '=', 1]
        ])->limit(4, 1)->select();
        if ($rs) {
            $service_fee = round($data['price'] * 0.012);
            $data['true_price'] = $data['price'] - $service_fee > 100 ? $data['price'] - $service_fee : 100;
        }

        Db::startTrans();
        try {
            // 提现审核表
            $data_save = array(
                'user_id' => $user_id,
                'real_name' => $data['real_name'],
                'pay_type' => $data['pay_method'],
                'price' => isset($data['true_price']) ? $data['true_price'] : $data['price'],
                'order_no' => Order::order_sn_create(),
                'status' => 0,
                'add_time' => request()->time(),
                'user_proof' => $user_proof,
                'service_fee' => $service_fee,
                'draw_price' => $data['price'],
            );
            $withdraw_id = Db::name('user_withdraw')->insertGetId($data_save);
        } catch (\Exception $e) {
            Db::rollback();
            return [[], '操作失败', 40001];
        }

        $res = User::money($user_id, 0 - $data['price'], 'withdraw', $withdraw_id, '提现');

        if (!isset($res['flow_id'])) {
            Log::record('take_order add error, uid-' . $user_id);
            Db::rollback();
            return [[], '处理失败', 50001];
        }
        Db::commit();

        return [[], '已提交审核!', 0];
    }

    public static function income($user_id = 0, $room_id = 0)
    {
        $where = [['money', '>', 0], ['event', 'in', ['challenge_income_room', 'leader_income']]];
        if ($user_id) array_unshift($where, ['user_id', '=', $user_id]);
        if ($room_id) {
//            $table = Db::getTable('challenge');
//            $where[] = ['user_id', 'exp', Db::raw("IN (SELECT user_id FROM {$table} WHERE room_id = {$room_id})")];

            $user_ids = Db::name('challenge')->where([['room_id', '=', $room_id]])->column('user_id');
            if (!empty($user_ids)) $where[] = ['user_id', 'in', $user_ids];
        }

        $data = Db::name('user_money')->where($where)->group('user_id')
            ->field('user_id,SUM(money) income')
            ->order('income DESC')
            ->limit(20)
            ->select();

        if (empty($data)) return false;

        $user_ids = array_column($data, 'user_id');
        if (count($user_ids) > 1) $where = ' IN (' . implode(',', $user_ids) . ')';
        else $where = ' = ' . $user_ids[0];

        $table = Db::getTable('challenge_record');
        $day = Db::query("SELECT user_id,COUNT(1) day FROM(SELECT `user_id` FROM `{$table}` WHERE `user_id` {$where} AND `stime` > 0 GROUP BY `user_id`,`date`) t GROUP BY user_id");

        if ($user_id) {
            return array_merge($data[0], ['day' => empty($day) ? 0 : $day[0]['day']]);
        }

        if (!empty($day)) {
            foreach ($day as $item) {
                $day['tmp'][$item['user_id']] = $item['day'];
            }

            $day = $day['tmp'];
        }

        $user = Db::name('user')->where('id' . $where)->column('id,nickname,avator');

        foreach ($data as $key => $item) {
            $item['nickname'] = $user[$item['user_id']]['nickname'];
            $item['avator'] = $user[$item['user_id']]['avator'];
            $item['day'] = empty($day[$item['user_id']]) ? 0 : $day[$item['user_id']];
            if ($room_id > 0) $item['room_id'] = $room_id;

            $data[$key] = $item;
        }

        if ($room_id > 0) {
            Db::name('challenge_income')->where([['room_id', '=', $room_id]])->delete();
            Db::name('challenge_income')->insertAll($data);
        } elseif ($user_id == 0) {
            Db::execute('TRUNCATE TABLE ' . Db::getTable('user_income'));
            Db::name('user_income')->insertAll($data);
        }

        return $data;
    }

    public static function system_user()
    {
        return [[1000, 1999]];
    }

    public static function system_user_rand($length = 1)
    {
        $system_user = self::system_user();

        $arr = [];
        for ($i = 0; $i < $length; $i++) {
            $rand = mt_rand(0, count($system_user) - 1);

            $user = $system_user[$rand];
            $arr[] = mt_rand($user[0], $user[1]);
        }

        if (empty($arr)) {
            return 0;
        }

        if ($length == 1) {
            return $arr[0];
        }

        return $arr;
    }

    public static function system_user_filter($key = 'user_id', $range = '')
    {
        $system_user = self::system_user();
        $length = count($system_user);

        $where = [];
        for ($i = 0; $i < $length; $i++) {
            $user = $system_user[$i];
            $where[] = "{$key} BETWEEN {$user[0]} AND {$user[1]}";
        }

        return (empty($range) ? '' : '!') . '(' . implode(' OR ', $where) . ')';
    }

    public static function system_user_check($user_id)
    {
        if (empty($user_id)) {
            return false;
        }

        $system_user = self::system_user();
        foreach ($system_user as $user) {
            if ($user[0] <= $user_id && $user_id <= $user[1]) {
                return true;
            }
        }

        return false;
    }

    public static function platform_money($is_yuan = false)
    {
        $where = self::system_user_filter('id', '!');
        $money = Db::name('user')->where($where)->sum('balance');
        if (!is_numeric($money)) $money = 0;

        $where = self::system_user_filter('user_id', '!');
        $price = Db::name('challenge')->where($where)->where([['status', 'in', [0, 1]]])->sum('price');
        if (is_numeric($price)) $money += $price;

        // 未审核的提现
        $price = Db::name('user_withdraw')->where('status', '=', 0)->sum('price');
        if (is_numeric($price)) $money += $price;

        // 白拿押金
        //$take_price = Db::name('order_take')->where('status', 0)->sum('appoint_price');
        //if (is_numeric($take_price)) $money += $take_price;

        if ($is_yuan) return $money / 100;

        return $money;
    }

//    public static function stat_mb_record()
//    {
//        $today_time = Common::today_time();
//        $today_date = Common::today_date();
//
//        $tax = Db::name('user_money')->where([
//            ['add_time', '>=', $today_time],
//            ['add_time', '<', $today_time + 86400],
//            ['event', 'in', ['challenge_fee_both', 'leader_donate']]
//        ])->sum('money');
//
//        $mb_today = Db::name('user_mb')->where([
//            ['add_time', '>=', $today_time],
//            ['add_time', '<', $today_time]
//        ])->sum('mb');
//
//        $mb_all = Db::name('user')->sum('mb');
//
//        $data = [
//            'date' => $today_date,
//            'tax' => $tax,
//            'today' => $mb_today,
//            'all' => $mb_all
//        ];
//
//        Db::name('stat_mb_change')->insert($data);
//
//        $config = Db::name('siteinfo')->where([['key', '=', 'mb_change']])->value('value');
//        if (empty($config) || empty($config = json_decode($config, true)) || empty($config['auto'])) {
//            return $data;
//        }
//
//        self::stat_mb_change();
//
//        return $data;
//    }
//
//    public static function stat_mb_change()
//    {
//        $today_date = Common::today_date();
//        $data = Db::name('stat_mb_change')->where([['date', '=', $today_date]])->find();
//        if (empty($data)) {
//            $data = self::stat_mb_record();
//            if (empty($data)) return false;
//        } elseif ($data['grant_mb'] > 0
//            || $data['grant_money'] > 0
//            || $data['grant_count'] > 0) {
//            return false;
//        }
//
//        $mb = Db::name('user')->where([['mb', '>', 0]])->sum('mb');
//        if ($mb <= 0) return false;
//
//        $config = Db::name('siteinfo')->where([['key', '=', 'mb_change']])->value('value');
//        if (empty($config) || empty($config = json_decode($config, true))) {
//            return false;
//        }
//
//        $money = 0;
//        if ($config['money'] > 0) $money = $config['money'];
//        elseif ($config['rate'] > 0) $money = round($data['tax'] * $config['rate'], 2);
//
//        if ($money <= 0) return false;
//
//        $grant_mb = $grant_money = $grant_count = $surplus = 0;
//        $rate = $money / $mb;
//
//        $user = Db::name('user')->where([['mb', '>', 0]])->field('id,mb')->select();
//        foreach ($user as $u) {
//            $to = intval($u['mb'] * $rate * 100);
//
//            if ($to > 0) {
//                User::mb($u['id'], -$u['mb'], 'mb_change', $today_date, 'M币兑换');
//                User::money($u['id'], $to, 'mb_change', $today_date, 'M币兑换');
//
//                $grant_mb += $u['mb'];
//                $grant_money += $to;
//                $grant_count++;
//            } else {
//                $surplus += $u['mb'];
//            }
//        }
//
//        $grant_money /= 100;
//        Db::name('stat_mb_change')
//            ->where([['date', '=', $today_date]])
//            ->update([
//                'grant_mb' => $grant_mb,
//                'grant_money' => $grant_money,
//                'grant_count' => $grant_count,
//                'surplus' => $surplus,
//                'profit' => $data['tax'] - $grant_money
//            ]);
//    }

    public static function stat_mb_record()
    {
        $today_time = Common::today_time();
        $yesterday_time = $today_time - 86400;
        $yesterday_date = date('Ymd', $yesterday_time);

        $data = Db::name('stat_mb_change')->where([['date', '=', $yesterday_date]])->find();
        if (empty($data)) {
            $tax = Db::name('user_money')->where([
                ['add_time', '>=', $yesterday_time],
                ['add_time', '<', $today_time],
                ['event', 'in', ['challenge_fee_both', 'leader_donate']]
            ])->sum('money');

            $nature_user = self::system_user_filter('user_id', '!');
            $mb = Db::name('user_mb')->where([
                ['mb', '>', 0],
                ['add_time', '>=', $yesterday_time],
                ['add_time', '<', $today_time]
            ])->where($nature_user)->sum('mb');

            $data = [
                'date' => $yesterday_date,
                'tax' => abs($tax),
                'mb' => $mb,
                'grant_mb' => 0,
                'grant_money' => 0,
                'grant_count' => 0,
            ];

            Db::name('stat_mb_change')->insert($data);
        }

        return $data;
    }

    public static function stat_mb_auto()
    {
        $config = Db::name('siteinfo')->where([['key', '=', 'mb_change']])->value('value');
        if (empty($config) || empty($config = json_decode($config, true)) || empty($config['auto'])) {
            self::stat_mb_record();
            return false;
        }

        self::stat_mb_change();
    }

    public static function stat_mb_change()
    {
        $today_time = Common::today_time();
        $yesterday_time = $today_time - 86400;
        $yesterday_date = date('Ymd', $yesterday_time);

        $data = self::stat_mb_record();

        if (empty($data)
            || $data['grant_mb'] > 0
            || $data['grant_money'] > 0
            || $data['grant_count'] > 0
            || $data['mb'] <= 0) {
            return false;
        }

        $config = Db::name('siteinfo')->where([['key', '=', 'mb_change']])->value('value');
        if (empty($config) || empty($config = json_decode($config, true))) {
            return false;
        }

        $money = 0;
        if ($config['money'] > 0) $money = $config['money'];
        elseif ($config['rate'] > 0) $money = round($data['tax'] * $config['rate'] / 100, 2);

        if ($money <= 0) return false;

        $grant_mb = $grant_money = $grant_count = $surplus = 0;
        $rate = $money / $data['mb'];
        $nature_user = self::system_user_filter('user_id', '!');
        $dynamic = $xinge_task = []; // 系统消息
        $time = request()->time();

        $user = Db::name('user_mb')->where([
            ['mb', '>', 0],
            ['add_time', '>=', $yesterday_time],
            ['add_time', '<', $today_time]
        ])->where($nature_user)->group('user_id')->field('user_id,SUM(mb) AS mb')->select();

        foreach ($user as $u) {
            User::mb($u['user_id'], -$u['mb'], 'mb_change', $yesterday_date, 'M币兑换');

            $to = intval($u['mb'] * $rate * 100);
            if ($to > 0) {
                $res = User::money($u['user_id'], $to, 'mb_change', $yesterday_date, 'M币兑换');

                $grant_mb += $u['mb'];
                $grant_money += $to;
                $grant_count++;

                if ($res) {
                    $dynamic[] = [
                        'user_id' => 0,
                        'nickname' => '微选生活',
                        'avator' => '/static/img/logo.png',
                        'receive_uid' => $u['user_id'],
                        'event' => 'mb_change',
                        'event_id' => $res['flow_id'],
                        'data' => serialize([
                            'title' => 'M币结算通知',
                            'content' => '您昨天共赚到了' . $u['mb'] . '个M币，共兑换到' . sprintf('%.2f', $to / 100) . '元，点击查看'
                        ]),
                        'status' => 1,
                        'add_time' => $time
                    ];

                    $xinge_task[] = [
                        'event' => 'mb_change',
                        'user_id' => $u['user_id'],
                        'data' => serialize([
                            'title' => 'M币结算通知,共换到' . sprintf('%.2f', $to / 100) . '元',
                            'content' => '您昨天共赚到了' . $u['mb'] . '个M币，共兑换到' . sprintf('%.2f', $to / 100) . '元，点击查看'
                        ])
                    ];
                }
            } else {
                $surplus += $u['mb'];
            }
        }

        $grant_money /= 100;
        Db::name('stat_mb_change')
            ->where([['date', '=', $yesterday_date]])
            ->update([
                'grant_mb' => $grant_mb,
                'grant_money' => $grant_money,
                'grant_count' => $grant_count,
                'surplus' => $surplus,
                'profit' => $data['tax'] - $grant_money
            ]);

        // 系统消息
        if (!empty($dynamic)) {
            Db::name('user_dynamic')->insertAll($dynamic);
        }

        // 信鸽推送
        if (!empty($xinge_task)) {
            Db::name('xinge_task')->insertAll($xinge_task);
        }
    }

    public static function xinge_push()
    {
        do {
            $data = Db::name('xinge_task')->order('id asc')->limit(100)->select();

            if (empty($data)) return false;

            $users = Db::name('app_token')->where([['user_id', 'in', array_column($data, 'user_id')]])->column('user_id,client_system');

            // android
            $config_android = config('xinge.android');

            // ios
            $config_ios = config('xinge.ios');

            foreach ($data as $item) {
                try {
                    $s = unserialize($item['data']);
                } catch (\Exception $e) {
                    // dump($e);
                    continue;
                }

                $custom = ['event' => $item['event'], 'mode' => self::dynamic_mode($item['event'])];
                if ($item['event'] == 'redpack') {
                    $s['content'] = '发了一个班级红包';
                }

                if ($item['user_id'] == 0) { // 所有人
                    if ($item['system'] == 'all' || $item['system'] == 'android') {
                        $push_android = new \XingeApp($config_android['access_id'], $config_android['secret_key']);
                        $mess_android = new \Message();
                        $mess_android->setType(\Message::TYPE_NOTIFICATION);
                        $mess_android->setStyle(new \Style(0, 1, 1, 1, 0));
                        $action_android = new \ClickAction();
                        $action_android->setActionType(\ClickAction::TYPE_INTENT);
                        $action_android->setIntent("xgscheme://com.xg.push/notify_detail");
                        $mess_android->setAction($action_android);
                        $mess_android->setTitle($s['title']);
                        $mess_android->setContent($s['content']);
                        $mess_android->setCustom($custom);
                        $res = $push_android->PushAllDevices(0, $mess_android);
                        dump($res);
                    }

                    if ($item['system'] == 'all' || $item['system'] == 'ios') {
                        $push_ios = new \XingeApp($config_ios['access_id'], $config_ios['secret_key']);
                        $mess_ios = new \MessageIOS();
                        $mess_ios->setAlert(['title' => $s['title'], 'body' => $s['content']]);
                        $mess_ios->setCustom($custom);
                        $res = $push_ios->PushAllDevices(0, $mess_ios, $config_ios['environment']);
                        dump($res);
                    }
                } else { // 单独
                    if (empty($users[$item['user_id']])) continue;

                    if ($users[$item['user_id']] == 'android') {
                        $push_android = new \XingeApp($config_android['access_id'], $config_android['secret_key']);
                        $mess_android = new \Message();
                        $mess_android->setType(\Message::TYPE_NOTIFICATION);
                        $mess_android->setStyle(new \Style(0, 1, 1, 1, 0));
                        $action_android = new \ClickAction();
                        $action_android->setActionType(\ClickAction::TYPE_INTENT);
                        $action_android->setIntent("xgscheme://com.xg.push/notify_detail");
                        $mess_android->setAction($action_android);
                        $mess_android->setTitle($s['title']);
                        $mess_android->setContent($s['content']);
                        $mess_android->setCustom($custom);
                        $res = $push_android->PushSingleAccount(0, 'xg_' . $item['user_id'], $mess_android);
                        dump($res);
                    } elseif ($users[$item['user_id']] == 'iOS') {
                        $push_ios = new \XingeApp($config_ios['access_id'], $config_ios['secret_key']);
                        $mess_ios = new \MessageIOS();
                        $mess_ios->setAlert(['title' => $s['title'], 'body' => $s['content']]);
                        $mess_ios->setCustom($custom);
                        $res = $push_ios->PushSingleAccount(0, 'xg_' . $item['user_id'], $mess_ios, $config_ios['environment']);
                        dump($res);
                    }
                }

                dump($item['user_id']);
            }

            Db::name('xinge_task')->where([['id', 'in', array_column($data, 'id')]])->delete();
        } while (!empty($data));
    }

    public static function dynamic_mode($event = '')
    {
        $arr = [
            'system' => [
//                'eduities_1', // 成为合伙人/月
//                'eduities_2', // 成为合伙人/年
//                'mb_change', // M币兑换
//                'recharge_challenge', // 充值
//                'recharge_goodsmodel', // 充值
//                'recharge_redpack', // 充值
//                'superior_award', // 下级团队晋升津贴
//                'system_recharge', // 充值
//                'withdraw', // 提现
//                'withdraw_refund', // 提现失败
            ],
            'order' => [
                'order_commission', // 订单结算
                'dredge_eduities_award', // 津贴周结算
                'free_order_award', // 免单结算
                'dredge_eduities_item', // 津贴单条
                'free_order_item', // 免单单条
                'goods_commission_award', // 商品佣金结算
            ],
            'challenge' => [
                'challenge_accept', // 接受挑战
                'create_room_ssuperior_award', // 下下级创建族群
                'create_room_superior_award', // 下级创建族群
                'redpack', // 发红包
                'signin_both', // 对战打卡成功
                'signin_room', // 族群打卡成功
                'signin_both_fail', // 好友对战失败通知
                'signin_room_fail', // 族群打卡失败通知
//                'challenge_back', // 退还早起挑战金
//                'challenge_donate', // 打赏族长
//                'challenge_fee_both', // 上缴对战公益金
                'challenge_income_both', // 好友对战奖励
                'challenge_income_room', // 族群挑战奖励
//                'challenge_join', // 参与族群挑战
//                'challenge_launch', // 发起挑战
//                'challenge_price_both', // 退还早起挑战契约金
//                'challenge_price_room', // 退还族群挑战契约金
//                'leader_donate', // 发放族员奖励
                'leader_income', // 族员打赏奖励
//                'room_fee', // 创建早起族群/月
//                'redpack_send', // 发红包
//                'redpack_draw', // 领红包
//                'recommend_launch', // 推荐双人对战
//                'recommend_room', // 推荐早起族群
//                'update_leader_rate', // 更改族费比例
            ]
        ];

        if (empty($event)) return $arr;

        foreach ($arr as $k => $item) {
            if (in_array($event, $item)) return $k;
        }

        return 'system';
    }

    public static function invite_sms_set($user_id, $default = false)
    {
        if (!$default) {
            $data = Db::name('invite_sms_set')->where([['user_id', '=', $user_id]])->field('`uname`,`content`,`link`')->find();
        }

        if (empty($data)) {
            $data = [
                'uname' => self::realname_or_nickname($user_id),
                'content' => '送你1个免单，商品任选，真的，我领到过才有名额，现在送你1个',
                // 'link' => self::invite_page($user_id, true, true)
            ];
        }

        $data['link'] = self::goods_page($user_id, true, true);
        $data['tpl'] = '{%taname%}，我是{%myname%}，{%content%}，点击 ' . $data['link'] . ' 领取';

        return $data;
    }

    public static function realname_or_nickname($user_id)
    {
        $name = Db::name('user_identity')->where([['user_id', '=', $user_id]])->value('real_name');
        if (empty($name)) {
            $user = self::find($user_id);
            if (!empty($user['nickname'])) $name = $user['nickname'];
        }

        return $name;
    }

    /**
     * 邀请链接
     * @param $user_id
     * @param bool $domain
     * @param bool $short
     * @param bool $protocol
     * @return mixed|string
     */
    public static function invite_page($user_id, $domain = false, $short = false, $protocol = false)
    {
        $invite_code = Db::name('user_promotion')->where([['user_id', '=', $user_id]])->value('invite_code');
        $url = url('home/user/invite_page', 'invite_code=' . $invite_code, true, $short ? true : $domain);

        if ($short) {
//            // 腾讯 eq:https://w.url.cn/s/AkFcOf8
//            $app = new Application(config('wechat.'));
//            $ret = $app->url->shorten($url);
//            if (!empty($ret['short_url'])) $url = $ret['short_url'];

            // 新浪 eg:http://t.cn/ReCqY16
            $ret = file_get_contents('http://api.t.sina.com.cn/short_url/shorten.json?source=3271760578&url_long=' . $url);
            $ret = json_decode($ret, true);
            if (!empty($ret[0]) && !empty($ret[0]['url_short'])) $url = $ret[0]['url_short'];
        }

        if (!$protocol) return str_replace(['http://', 'https://'], '', $url);

        return $url;
    }

    /**
     * 5元内的随机商品免单链接
     * @param $user_id
     * @param bool $domain
     * @param bool $short
     * @param bool $protocol
     * @return mixed|string
     */
    public static function goods_page($user_id, $domain = false, $short = false, $protocol = false)
    {
        // 其他商品
        $goods = db('goods')->where('price', '<=', 500)
        ->field('id, goods_id, name, img')
        ->limit(mt_rand(0, 100), 1)
        ->find();
        
        $user_info = Db::name('user')->field('nickname, avator')->where('id', '=', $user_id)->find();
        $invite_code = Db::name('user_promotion')->where([['user_id', '=', $user_id]])->value('invite_code');
        $url = url('home/goods/free_goods_share', 'goods_id=' . $goods['goods_id'] . '&invite_code='. $invite_code .'&nickname='. $user_info['nickname'] . '&avator=' . base64_encode($user_info['avator']), true, $short ? true : $domain);

        if ($short) {
            $ret = file_get_contents('http://api.t.sina.com.cn/short_url/shorten.json?source=3271760578&url_long=' . $url);
            $ret = json_decode($ret, true);
            if (!empty($ret[0]) && !empty($ret[0]['url_short'])) $url = $ret[0]['url_short'];
        }

        if (!$protocol) return str_replace(['http://', 'https://'], '', $url);

        return $url;
    }

    /**
     * @param $user_id
     * @return bool|string
     * 合伙人类型；包年、包月、非合伙人
     */
    public static function partner_type($user_id)
    {
        $time = request()->time();
        $count = Db::name('user_dredge_eduities')->where([
            ['user_id', '=', $user_id],
            ['type', '=', 2],
            ['beg_time', 'between', [$time - 31536000, $time + 86400]]
        ])->count(1);
        if ($count > 0) return 'year';

        $expire_time = Db::name('user_promotion')->where([['user_id', '=', $user_id]])->value('expire_time');
        if ($expire_time > $time) return 'month';

        return false;
    }

    // 累计收益
    public static function all_income($user_id = 0)
    {
        $all_income = db('user_money')->where([
            ['user_id', '=', $user_id],
            ['event', 'in', self::all_income_event()]
        ])->sum('money');

        return $all_income ?: 0;
    }

    public static function all_income_event()
    {
        return [
            'challenge_back',
            'challenge_income_both',
            'challenge_income_room',
            'challenge_price_both',
            'challenge_price_room',
            'create_room_ssuperior_award',
            'create_room_superior_award',
            'free_order',
            'goods_commission',
            'leader_income',
            'mb_change',
            'redpack_draw',
            'superior_award',
            'system_recharge',
            'level_promote_awards_2',
            'level_promote_awards_3',
            'level_promote_awards_4',
            'pick_new_cash',
            'pick_new_open',
            'return688_consume500',
            'return688_consume1000',
            'return688_subvip10',
            'return688_subvip20',
            'po_one_buy_return',
            'po_one_buy_cash',
            'po_one_buy_arrival_cash'
        ];
    }
    
    // 获取用户上级
    public static function user_superior($user_id, $user_level)
    {
        $team_data = []; // 团队数据
        $user_level--;
        $step_count = 1;
        
        $map[] = [
            ['ut.user_id', '=', $user_id],
            ['ut.pid', '<>', $user_id]
        ];
        
        // 
        while (1) {
            $data = Db::name('user_tier')
            ->alias('ut')
            ->join('user_level ul', 'ul.user_id = ut.pid', 'LEFT')
            ->field('pid as user_id, ul.level')
            ->where($map)
            ->find();
            if ($data) {
                $step_count++;
                // 添加到上级团队
                if ($data['level'] > $user_level) {
                    $team_data[] = $data;
                    $user_level = $data['level'];
                }
                
                // 超过3级，结束查询
                if ($step_count > 3) {
                    break;
                }
                // 到达合伙人，结束查询
                if ($data['level'] == 4) {
                    break;
                }
                $map[0][0] = ['ut.user_id', '=', $data['user_id']];
            } else {
                break;
            }
        }
        
        return $team_data;
    }
    
    public static function level_promote_commission_grant($promote_commission_data)
    {
        $id = $promote_commission_data['id'];
        
        if (!$promote_commission_data) {
            return [[], '红包不存在', 40000];
        }
        if ($promote_commission_data['status'] == 1) {
            return [[], '红包已领取', 40000];
        }

        Db::startTrans();
        
        try {
            // 升级人昵称
            $nickname = Db::name('user')->where('id', '=', $promote_commission_data['user_id'])->value('nickname');
            
            // 升级获得返利信息
            $level_promote_awards = Db::name('siteinfo')->where('key', '=','level_promote_awards')->value('value');
            $level_promote_awards = json_decode($level_promote_awards, true);
            
            // 总佣金数
            $promote_commission_total = 0;
            $team_last_level = 1;
            
            if ($promote_commission_data['remind_user_status'] == 1) {
                // 提醒人达成条件
                $res = User::money($promote_commission_data['remind_user_id'], $promote_commission_data['remind_user_commisson'], 'level_promote_awards_'. $promote_commission_data['level'], $promote_commission_data['user_id'], '晋升津贴');
                // 添加津贴记录表
                Db::name('user_level_promote_award')->insert([
                    'user_id' => $promote_commission_data['remind_user_id'],
                    'promote_user_id' => $promote_commission_data['user_id'],
                    'promote_level' => $promote_commission_data['level'],
                    'award' => $promote_commission_data['remind_user_commisson'],
                    'promote_remind_id' => $id,
                    'add_time' => request()->time()
                ]);
            
                // 发送消息通知
                Db::name('user_dynamic')->insert([
                    'user_id' => 0,
                    'nickname' => '微选生活',
                    'avator' => '/static/img/logo.png',
                    'receive_uid' => $promote_commission_data['remind_user_id'],
                    'event' => 'dredge_eduities_item',
                    'event_id' => $promote_commission_data['user_id'],
                    'data' => serialize([
                        'title' => '晋升津贴通知  奖励 '. round($promote_commission_data['remind_user_commisson'] / 100, 2) .'元',
                        'content' => '成员【'. $nickname .'】['. date('Y-m-d') .']升级成功，您获得晋升津贴'. round($promote_commission_data['remind_user_commisson'] / 100, 2) .'元'
                    ]),
                    'status' => 1,
                    'add_time' => request()->time()
                ]);
                
                Db::name('xinge_task')->insert([
                    'user_id' => $promote_commission_data['remind_user_id'],
                    'event' => 'dredge_eduities_item',
                    'data' => serialize([
                        'title' => '晋升津贴通知  奖励 '. round($promote_commission_data['remind_user_commisson'] / 100, 2) .'元',
                        'content' => '成员【'. $nickname .'】['. date('Y-m-d') .']升级成功，您获得晋升津贴'. round($promote_commission_data['remind_user_commisson'] / 100, 2) .'元'
                    ])
                ]);
                
                $promote_commission_total += $promote_commission_data['remind_user_commisson'];
                $team_last_level = $promote_commission_data['remind_user_condition'];
            }
            
            // 团队
            $promote_commission_data['team_data'] = json_decode($promote_commission_data['team_data'], true);
            foreach ($promote_commission_data['team_data'] as $key=>$val) {
                // 根据$promote_commission_data['team_data']返利
                if ($val['level'] >= $promote_commission_data['level']) {
                    // 获得佣金津贴
                    $commission = $level_promote_awards[$val['level']][$promote_commission_data['level']] - $promote_commission_total;
            
                    if ($commission > 0) {
                        // 每个等级只返还一个人
                        $res = User::money($val['user_id'], $commission, 'level_promote_awards_'. $promote_commission_data['level'], $promote_commission_data['user_id'], '晋升津贴');
                        // 添加津贴记录表
                        Db::name('user_level_promote_award')->insert([
                            'user_id' => $val['user_id'],
                            'promote_user_id' => $promote_commission_data['user_id'],
                            'promote_level' => $promote_commission_data['level'],
                            'award' => $commission,
                            'promote_remind_id' => $id,
                            'add_time' => request()->time()
                        ]);
                    
                        // 发送消息通知
                        Db::name('user_dynamic')->insert([
                            'user_id' => 0,
                            'nickname' => '微选生活',
                            'avator' => '/static/img/logo.png',
                            'receive_uid' => $val['user_id'],
                            'event' => 'dredge_eduities_item',
                            'event_id' => $promote_commission_data['user_id'],
                            'data' => serialize([
                                'title' => '晋升津贴通知  奖励 '. round($commission / 100, 2) .'元',
                                'content' => '成员【'. $nickname .'】['. date('Y-m-d') .']升级成功，您获得晋升津贴'. round($commission / 100, 2) .'元'
                            ]),
                            'status' => 1,
                            'add_time' => request()->time()
                        ]);
                        
                        Db::name('xinge_task')->insert([
                            'user_id' => $val['user_id'],
                            'event' => 'dredge_eduities_item',
                            'data' => serialize([
                                'title' => '晋升津贴通知  奖励 '. round($commission / 100, 2) .'元',
                                'content' => '成员【'. $nickname .'】['. date('Y-m-d') .']升级成功，您获得晋升津贴'. round($commission / 100, 2) .'元'
                            ])
                        ]);
                    }
                    $promote_commission_total += $commission;
                    $team_last_level = $promote_commission_data['level'];
                }
            }
            
            Db::name('user_level_promote_commission_remind')->where('id', '=', $id)->update([
                'status' => 1
            ]);
        } catch (\Exception $e) {
            Log::record('level_promote_commission_grant error, id-'. $id);
            Db::rollback();
            return [[], '处理失败', 50001];
        }
        
        Db::commit();
        
        return [[], '领取成功，请到我的账户余额查看', 0];
    }

    public static function level($user_id)
    {
        $level = db('user_level')
            ->where([['user_id', '=', $user_id], ['expier_time', '>', request()->time()]])
            ->value('level');

        if (empty($level)) return 1;
        return $level;
    }

    public static function deny_mobile()
    {
        return ['18355279985', '18817565203'];
    }
}
