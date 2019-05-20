<?php

namespace app\home\controller;

use app\common\model\Common;
use app\common\model\GoodsModel;
use app\common\model\Order;
use app\common\model\User as UserModel;
use think\exception\HttpResponseException;
use think\facade\Env;

class User extends Base
{
    public function initialize()
    {
        $this->user_id = session('user.id');
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

        // deny users
        //        if (in_array($this->user_wxid, [])) {
        //            $this->error('', '请稍后...', 10011);
        //        }

        // $this->openid = session('user.openid');
        Env::set('ME', $this->user_id);
    }

    public function invite()
    {
        $para = [];
        $para['invite_code'] = db('user_promotion')->where('user_id', $this->user_id)->value('invite_code');

        $User = new UserModel();
        $file_poster = $User->invite_poster_create($this->user_id, true, $para);
        foreach ($file_poster as &$val) {
            $val['path'] .= '?' . mt_rand(10000, 99999);
        }

        return view('', ['file_poster' => $file_poster, 'invite_code' => $para['invite_code']]);
    }

    public function everlastinglove()
    {
        // 查看邀请码
        $invite_code = db('user_promotion')->where('user_id', $this->user_id)->value('invite_code');
        if (!$invite_code) {
            // 邀请码
            $invite_code = db('user_invite_code')->find();
            if (!$invite_code) {
                // 初始值
                $app_config = db('siteinfo')->where('key', 'app_setting')->value('value');
                if (!isset($app_config['invite_code_start'])) {
                    $invite_code_start = 1325238;
                }

                // 生成1000 条
                $arr = range($invite_code_start, $invite_code_start + 1500);
                $arr = Common::arr_shuffle($arr, 1000, '');

                // 获取第一条
                $invite_code = array_shift($arr)['code'];

                db('user_invite_code')->insertAll($arr);
                $app_config['invite_code_start'] = $invite_code_start + 1500;
                db('siteinfo')->where('key', 'app_setting')->update(['value' => json_encode($app_config)]);
            } else {
                db('user_invite_code')->where('id', $invite_code['id'])->delete();
                $invite_code = $invite_code['code'];
            }

            $rs = db('user_promotion')->where('user_id', $this->user_id)->update(['invite_code' => $invite_code]);
            if (!$rs) {
                db('user_promotion')->where('user_id', $this->user_id)->insert([
                    'user_id' => $this->user_id,
                    'invite_code' => $invite_code,
                ]);
            }
        }

        return view('', ['invite_code' => $invite_code]);
    }

    public function invite_page()
    {
        Common::potential_market($this->user_id);

        return redirect('http://a.app.qq.com/o/simple.jsp?pkgname=com.mayixiguan');
        return view();
    }

    public function mb()
    {
        $today_time = Common::today_time();

        if (request()->isAjax()) {
//            $limit = 20;
//            $next_id = input('next_id', 0, 'intval');

            $where = [['user_id', '=', $this->user_id], ['add_time', '>', $today_time - (86400 * 5)]];
//            if($next_id) $where[] = ['id', 'lt', $next_id];

            $data = db('user_mb')->where($where)->order('flow_id DESC')->field('mb,event_name,add_time')->select();
//            if (empty($data)) return $this->response(['list' => []]);

            foreach ($data as $key => $item) {
                $item['time'] = Common::time_format($item['add_time']);
                unset($item['add_time']);
                $data[$key] = $item;
            }

            return $this->response(['list' => $data]);
        }

        $income = db('user_money')->where([
            ['user_id', '=', $this->user_id],
            ['event', '=', 'mb_change'],
            ['add_time', 'between', [$today_time, $today_time + 86400 - 1]]
        ])->sum('money');

        $income_all = db('user_money')->where([
            ['user_id', '=', $this->user_id],
            ['event', '=', 'mb_change']
        ])->sum('money');

        $mb_today = db('user_mb')->where([
            ['user_id', '=', $this->user_id],
            ['mb', '>', 0],
            ['add_time', 'between', [$today_time, $today_time + 86400 - 1]]
        ])->sum('mb');

        $mb_yesterday = db('user_mb')->where([
            ['user_id', '=', $this->user_id],
            ['mb', '>', 0],
            ['add_time', 'between', [$today_time - 86400, $today_time - 1]]
        ])->sum('mb');

        $data = db('stat_mb_change')->order('date desc')->find();
        if (empty($data) || empty($data['grant_money']) || empty($data['grant_mb'])) {
            $mb_rate = '0.00';
        } else {
            $mb_rate = round($data['grant_money'] / $data['grant_mb'] * 100, 2);
        }

        return view('', [
            'income' => $income,
            'income_all' => $income_all,
            'mb_today' => $mb_today,
            'mb_yesterday' => $mb_yesterday,
            'mb_rate' => $mb_rate
        ]);
    }

    public function contack_customer()
    {
        $pid = db('user_tier')->where('user_id', $this->user_id)->value('pid');
        if ($pid) {
            $mobile = db('user')->where('id', $pid)->value('mobile');
        }
        $mobile = isset($mobile) && $mobile ? $mobile : 'kangnaixin1989';

        return view('', ['mobile' => $mobile]);
    }

    public function level_promote()
    {
        $para = input('post.');
        $para['out_trade_no'] = Order::order_sn_create(); // 订单号

        $res = GoodsModel::level_promote($this->user_id, $para);

        return call_user_func_array([$this, 'response'], $res);
    }
}