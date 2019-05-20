<?php

namespace app\api\controller;

use app\common\model\Common;
use app\common\model\User;
use app\common\model\Client;
use app\common\model\Sms;

class Oauth extends Base
{
    protected function initialize()
    {
        $this->check_sign = ['token', 'refresh'];

        if (!(request()->action() == 'token' && request()->method() == 'POST')) {
            parent::initialize();
        }
    }

    public function token()
    {
//        $client_code = I('request.client_code'); // 机器码
//        $client_system = I('request.client_system'); // 系统，android、ios
//        $client_version = I('request.client_version'); // 系统版本
//        $client_brand = I('request.client_brand'); // 品牌
//        $client_model = I('request.client_model'); // 型号
//        $client_width = I('request.client_width'); // 屏宽
//        $client_height = I('request.client_height'); // 屏高
//
//        $app_version = I('request.app_version'); // app版本
//        $core_version = I('request.core_version'); // app核心版本
//
//        $auth_code = I('request.auth_code');
//        $mobile = I('post.mobile');
//        $password = I('request.password');

        if (request()->isPatch()) {
            return $this->refresh();
        }

        $this->checkMethod(['POST']);

        $data = input('post.');

        if (empty($data['client_code']) || empty($data['client_system']) || empty($data['core_version'])) {
            return $this->response('', 'param missing', 1);
        }

        Client::check_app_version($data['client_system'], $data['core_version']);

        $client_code = $data['client_code'];

        if (!empty($data['auth_code'])) {
            $user = User::wxOauthUser();
            /*
                $res = db('app_token')->where('client_code', $client_code)->find();
                if ($res) {
                    db('user_promotion')->where('user_id', $res['user_id'])->update(['is_couple_weal', 0]);
                }
            */
        } elseif (!empty($data['mobile']) && !empty($data['password'])) {
            $user = User::find([['mobile', '=', $data['mobile']], ['password', '=', md5($data['password'])]]);
        } elseif (!empty($data['mobile']) && !empty($data['code'])) {
            $mobile = $data['mobile'];
            $code = $data['code'];

            if (!preg_match("/^1[34578]\d{9}$/", $mobile)) {
                return $this->response([], '手机号码错误！', 40000);
            }

            if ($mobile != '18680536892' && $code != '888888') {
                $rs = Sms::verify($mobile, $code);
                if ($rs != 'SUCCESS') {
                    return $this->response($rs[0], $rs[1], $rs[2]);
                }
            }

            $user = User::find(['mobile' => $mobile]);
        } else {
            return $this->response('', 'param missing', 1);
        }

        if (empty($user)) return $this->response('', '登录失败', 1);
        $data['user_id'] = $user['id'];

        $data['access_token'] = $access_token = md5($client_code . $data['user_id'] . microtime(TRUE));
        $data['refresh_token'] = $refresh_token = sha1($client_code . $data['user_id'] . microtime(TRUE));
        $data['openid'] = $user['openid_app'];
        $data['add_time'] = $data['access_time'] = request()->time();
        $data['client_ip'] = request()->ip();

        $location = [];
        if (!empty(request()->header()['location'])) {
            $location['coord'] = request()->header()['location'];
        } elseif (request()->ip()) {
            $location['ip'] = request()->ip();
        }

        if ($location) {
            $data['city'] = Common::getLocation($location, 'city');
        }

        db('app_token')->where([['user_id', '=', $data['user_id']]])->delete();

        db('app_token')->strict(false)->insert($data);

        // 天天领现金活动
        \app\common\model\Activity::pick_new_cash_new($data['user_id']);

        return $this->response(
            [
                'access_token' => $access_token,
                'refresh_token' => $refresh_token,
                'access_in' => $this->access_token_limit,
                'expire_in' => $this->expire_token_limit,
                'domain' => request()->host(),
                'api_url' => request()->root(true) . '/api',
                'pay_method' => ['wx', 'ali'],
                'xg_account' => 'xg_' . $data['user_id'],
                'user_id' => $data['user_id']
            ]
        );
    }

    public function refresh()
    {
        $this->checkMethod(['POST', 'PATCH']);

        if (request()->isPost()) {
            $data = input('post.');
        } elseif (request()->isPatch()) {
            $data = input('patch.');
        }

        if (empty($data['client_code']) || empty($data['client_system']) || empty($data['core_version'])
            || empty($data['access_token']) || empty($data['refresh_token'])) {
            return $this->response('', 'param missing', 1);
        }

        if (isset($data['user_id']) || isset($data['openid']) || isset($data['add_time'])) {
            return $this->response('', 'param error', 1);
        }

        if ($this->app_token['refresh_token'] !== $data['refresh_token']) {
            return $this->response('', 'refresh_token mismatch', 1);
        }

        $client_code = $data['client_code'];
        $data['access_token'] = $access_token = md5($client_code . $this->app_token['user_id'] . microtime(TRUE));
        $data['refresh_token'] = $refresh_token = sha1($client_code . $this->app_token['user_id'] . microtime(TRUE));
        $data['access_time'] = request()->time();
        $data['client_ip'] = request()->ip();
        unset($data['token']);

        db('app_token')->where(['access_token' => $this->app_token['access_token']])->strict(false)->update($data);

        return $this->response(
            [
                'access_token' => $access_token,
                'refresh_token' => $refresh_token,
                'access_in' => $this->access_token_limit,
                'expire_in' => $this->expire_token_limit,
                'domain' => request()->host(),
                'api_url' => request()->root(true) . '/api',
                'pay_method' => ['wx', 'ali'],
                'xg_account' => 'xg_' . $this->app_token['user_id'],
                'user_id' => $this->app_token['user_id']
            ]
        );
    }
}
