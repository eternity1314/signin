<?php

namespace app\home\controller;

use app\common\model\Common;
use think\Db;
use think\exception\HttpResponseException;
use think\facade\Env;

class Base // extends Controller
{
    protected $user_id;
    protected $openid;

    public function __construct() //initialize()
    {
//        $this->response('', '系统升级中...', 1);

        if (!request()->isGet() && Common::is_stat_ing()) {
            $this->response('', '亲，资金清算中，请稍后再来！', 1);
        }

        $this->initialize();
    }

    protected function initialize()
    {
        $this->user_id = session('user.id');$this->user_id = 163;
        if (empty($this->user_id)) {
            // 验证签名
            if (!empty(input('sign'))) {
                $this->checkSign();

                // 验证令牌
                $this->checkToken();
            }
        } else {
            $this->openid = session('user.openid');
        }

        if (empty($this->user_id)) {
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

        Env::set('ME', $this->user_id);
    }

    // 验证令牌
    protected function checkToken()
    {
        $this->access_token_limit = 60 * 60 * 2;
        $this->expire_token_limit = 60 * 60 * 24 * 30;

        $token = input('token');
        if (empty($token)) {
            throw new HttpResponseException($this->response('', 'token missing', 10001));
        }

        $app_token = Db::name('app_token')->where(['access_token' => $token])->find();
        if (empty($app_token)) {
            throw new HttpResponseException($this->response('', 'token invalid', 10002));
        }

        if ($app_token['access_time'] + $this->access_token_limit + 10 < request()->time()) {
            throw new HttpResponseException($this->response('', 'token longer', 10003));
        }

        if ($app_token['add_time'] + $this->expire_token_limit + 10 < request()->time()) {
            throw new HttpResponseException($this->response('', 'token expire', 10004));
        }

        $this->user_id = $app_token['user_id'];
        $this->openid = $app_token['openid'];
        session('user', ['id' => $this->user_id, 'openid' => $this->openid]);

        Env::set('SOURCE', ['from' => 'app', 'client' => 'ios']);
    }

    // 验证签名
    protected function checkSign()
    {
        $method = strtolower(request()->method(true));
        $param = array_merge(request()->get(false), request()->$method(false));//input();

        if (empty($param['sign']) || empty($param['timestamp'])) {
            throw new HttpResponseException($this->response('', 'sign error', 10005));
        }

        $sign = $param['sign'];
        unset($param['sign']);

        if ($sign !== Common::makeSign($param)) {
            $error = '';
            $debug = env('APP_DEBUG');
            if ($debug) {
                ksort($param);
                $error .= ' ' . Common::toUrlParams($param, true);
                $error .= ' ' . Common::makeSign($param);
            }

            throw new HttpResponseException($this->response('', 'sign error' . $error, 10006));
        }

//        if (request()->time() - input('timestamp') > 300) {}

        return true;
    }

    protected function response($data = [], $msg = '', $code = 0)
    {
        $data_out['code'] = $code;

        if (!empty($msg)) {
            if (is_array($msg)) {
                $data_out = array_merge($data_out, $msg);
            } else {
                $data_out['msg'] = $msg;
            }
        }

        if (!empty($data)) {
            $data_out['data'] = $data;
        }

        return json($data_out);
    }
}
