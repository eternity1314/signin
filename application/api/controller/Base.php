<?php

namespace app\api\controller;

use app\common\model\Common;
use think\Db;
use think\exception\HttpResponseException;

class Base
{
    protected $user_id;
    protected $openid;
    protected $access_token_limit = 60 * 60 * 2;
    protected $expire_token_limit = 60 * 60 * 24 * 30;

    public function __construct()
    {
//        $this->response('', '系统升级中...', 1);

        if (!request()->isGet() && Common::is_stat_ing()) {
            $this->response('', '亲，资金清算中，请稍后再来！', 1);
        }

        $this->initialize();

        // 验证签名
        if (!empty(input('sign')) || (!empty($this->check_sign) && in_array(request()->action(), $this->check_sign))) {
            $this->checkSign();
        }

        if (!empty($this->check_post) && in_array(request()->action(), $this->check_post)) {
            $this->checkMethod('POST');
        }
    }

    protected function initialize()
    {
        // 验证令牌
        $this->checkToken();
    }

    // 验证令牌
    protected function checkToken()
    {
        $token = input('token');
        if (empty($token)) {
            $token = request()->header('token');
        }

        if (empty($token)) {
            $token = input('access_token');
        }

        if (empty($token)) {
            throw new HttpResponseException($this->response('', 'token missing', 10001));
        }

        $app_token = Db::name('app_token')->where(['access_token' => $token])->find();
        if (empty($app_token)) {
            throw new HttpResponseException($this->response('', 'token invalid', 10002));
        }

        $action = request()->action();
        $is_refresh = $action == 'refresh';
        if (!$is_refresh) {
            $is_refresh = $action == 'token' && request()->method() == 'PATCH';
        }

        if (!$is_refresh && $app_token['access_time'] + $this->access_token_limit + 10 < request()->time()) {
            throw new HttpResponseException($this->response('', 'token longer', 10003));
        }

        if ($app_token['add_time'] + $this->expire_token_limit + 10 < request()->time()) {
            throw new HttpResponseException($this->response('', 'token expire', 10004));
        }

        $this->user_id = $app_token['user_id'];
        $this->openid = $app_token['openid'];
        $this->app_token = $app_token;

        \think\facade\Env::set('ME', $this->user_id);

        // deny users
//        if (in_array($this->user_wxid, [])) {
//            $this->error('', '请稍后...', 10011);
//        }
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

    // 验证请求方法
    protected function checkMethod($method = ['POST'])
    {
        if (is_string($method)) $method = explode(',', $method);

        if (!in_array(request()->method(), $method)) {
            throw new HttpResponseException($this->response('', 'request error', 10007));
        }
    }

    protected function response($data = [], $msg = '', $code = 0)
    {
        $data_out = ['code' => $code];

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
