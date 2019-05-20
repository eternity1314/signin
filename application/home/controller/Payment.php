<?php

namespace app\home\controller;

use EasyWeChat\Foundation\Application;
use think\Db;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Log;
use think\Loader;
use function GuzzleHttp\json_decode;

class Payment
{
    public function notify_wx()
    {
        $debug = env('APP_DEBUG');
        if ($debug) Log::record(empty($GLOBALS['HTTP_RAW_POST_DATA']) ? '' : $GLOBALS['HTTP_RAW_POST_DATA']);

        $type = input('type');
        if (!empty($type)) $config = Config::pull($type);
        if (empty($config)) $config = Config::pull('wechat');

        $app = new Application($config);
        $res = $app->payment->handleNotify(function ($notify, $successful) {
            if (!$successful) {
                if (!empty($GLOBALS['HTTP_RAW_POST_DATA'])) {
                    Log::record($GLOBALS['HTTP_RAW_POST_DATA'], 'debug');
                }

                return true;
            }

            $data = $notify->all();

            $config = config('cache.');
            $config['prefix'] = 'pay_attach';
            $attach = Cache::connect($config)->get($notify->out_trade_no);
            if ($attach) $data['attach'] = $attach;

            $data_save = $data;
            $data_save['data'] = json_encode($data, JSON_UNESCAPED_UNICODE);
            $data_save['add_time'] = request()->time();
            $pay_id = Db::name('pay')->strict(false)->insertGetId($data_save);
            if (!$pay_id) {
                Log::record($data_save, 'debug');
                return true;
            }

            if (!empty($attach)) {
                // if (!empty($notify->attach)) {
                $count = Db::name('pay')->where('out_trade_no', '=', $data['out_trade_no'])->count(1);
                if ($count > 1) return true;

                // $attach = Common::endecrypt($notify->attach, 'D', 'wxpay');
                // if (empty($attach)) return 'success';
                // $attach = $notify->attach;
                $attach = $data['attach'] = explode(':', $attach);
                if (count($attach) < 3) return true;
                if (empty($attach)) return true;

                $debug = env('APP_DEBUG');
                $user_id = intval($attach[0]);
                if ($debug) $total_fee = intval($attach[1]);
                else $total_fee = $notify->total_fee;

                $res = \app\common\model\User::money($user_id, $total_fee, 'recharge_' . strtolower($attach[2]), $pay_id, '充值');
                if (empty($res['flow_id'])) return true;

                $class = '\app\common\model\\' . $attach[2];
                if (method_exists($class, 'pay_finish')) {
                    call_user_func_array($class . '::pay_finish', [$data, $pay_id]);
                }
            }

            return true;
        });

        $res->send();
    }

    public function notify_ali()
    {
        $debug = env('APP_DEBUG');
        if ($debug) Log::record($_POST);

        $data = $_POST;
        $signType = $data['sign_type'];

        Loader::addAutoLoadDir(env('EXTEND_PATH') . 'alipay/aop');
        $aop = new \AopClient();
        $flag = $aop->rsaCheckV1($data, NULL, $signType);

        if ($flag !== true) {
            return 'success';
        }

        // 支付成功:TRADE_SUCCESS,交易完成:TRADE_FINISHED
        $successful = $data['trade_status'] == 'TRADE_SUCCESS' || $data['trade_status'] == 'TRADE_FINISHED';
        if (!$successful) {
            Log::record($data);
            return 'success';
        }

        $config = config('cache.');
        $config['prefix'] = 'pay_attach';
        $attach = Cache::connect($config)->get($data['out_trade_no']);
        if ($attach) $data['attach'] = $attach;

        $data_save = [];
        $data_save['data'] = json_encode($data, JSON_UNESCAPED_UNICODE);
        $data_save['appid'] = $data['app_id'];
        $data_save['openid'] = $data['buyer_id'];
        $data_save['out_trade_no'] = $data['out_trade_no'];
        $data_save['result_code'] = 'SUCCESS';
        $data_save['total_fee'] = $data['receipt_amount'] * 100;
        $data_save['transaction_id'] = $data['trade_no'];
        $data_save['time_end'] = date('YmdHis', strtotime($data['gmt_payment']));
        $data_save['trade_type'] = 'ali';
        $data_save['add_time'] = request()->time();
        $pay_id = Db::name('pay')->insertGetId($data_save);
        if (!$pay_id) {
            Log::record($data_save);
            return 'success';
        }

        if (!empty($attach)) {
//        if (!empty($data['passback_params'])) {
            $count = Db::name('pay')->where('out_trade_no', '=', $data['out_trade_no'])->count(1);
            if ($count > 1) return 'success';

//            $attach = Common::endecrypt(str_replace(' ', '+', $data['passback_params']), 'D', 'alipay');
//            if (empty($attach)) return 'success';
            $attach = $data['attach'] = explode(':', $attach);
            if (count($attach) < 3) return 'success';

            $user_id = intval($attach[0]);
            if ($debug) $total_fee = intval($attach[1]);
            else $total_fee = round($data['receipt_amount'] * 100);

            $res = \app\common\model\User::money($user_id, $total_fee, 'recharge_' . strtolower($attach[2]), $pay_id, '充值');
            if (empty($res['flow_id'])) return 'success';

            $class = '\app\common\model\\' . $attach[2];
            if (method_exists($class, 'pay_finish')) {
                call_user_func_array($class . '::pay_finish', [$data, $pay_id]);
            }
        }

        return 'success';
    }
}
