<?php

namespace app\common\model;

use think\facade\Cache;
use think\facade\Config;
use think\facade\Log;
use think\Loader;
use EasyWeChat\Foundation\Application;
use EasyWeChat\Payment\Order as easyOrder;
use EasyWeChat\Payment\Merchant;

class Pay
{
    public static function pay($paid, $pay_data, $option, $pay_method = '')
    {
        if (empty($pay_data['out_trade_no'])) {
            $pay_data['out_trade_no'] = Order::order_sn_create(); // 订单号
        }

        if (empty($pay_data['body'])) {
            $pay_data['body'] = '微选生活';
        }

        $debug = env('APP_DEBUG');
        if ($debug) {
            $paid = 1;
        }

        if (!empty($option['attach_data'])) {
            unset($option['attach_data']['added']);
            unset($option['attach_data']['token']);

            $pay_data['attach'] .= ':' . http_build_query($option['attach_data']);
        }

        if (!empty($pay_data['attach'])) {
            if ($debug) Log::record('pay_data attach ' . $pay_data['attach']);

            $config = config('cache.');
            $config['prefix'] = 'pay_attach';
            Cache::connect($config)->set($pay_data['out_trade_no'], $pay_data['attach']);
            unset($pay_data['attach']);
        }

        if (empty($pay_method) && !empty($option['pay_method'])) $pay_method = $option['pay_method'];
        if (empty($pay_method) && !empty($option['attach_data']) && !empty($option['attach_data']['pay_method'])) $pay_method = $option['attach_data']['pay_method'];

        $module_name = request()->module();
        $is_weixin = Common::isWeixin();
        $data_out = [];

        if ($pay_method == 'ali' && !$is_weixin) {
            $biz = [
                'body' => $pay_data['body'],
                'subject' => $pay_data['body'],
                'out_trade_no' => $pay_data['out_trade_no'],
                'timeout_express' => '1c',
                'total_amount' => $paid / 100,
                'product_code' => 'QUICK_MSECURITY_PAY',
                // 'passback_params' => Common::endecrypt($pay_data['attach'], 'E', 'alipay')
            ];

            if ($debug) Log::record($biz);

            Loader::addAutoLoadDir(env('EXTEND_PATH') . 'alipay/aop');
            Loader::addAutoLoadDir(env('EXTEND_PATH') . 'alipay/aop/request');

            $aop = new \AopClient();
            if ($module_name == 'api') $request = new \AlipayTradeAppPayRequest();
            else $request = new \AlipayTradeWapPayRequest();

            $request->setBizContent(json_encode($biz));
            $request->setNotifyUrl(config('site.url') . '/home/payment/notify_ali');

            if ($module_name == 'api') {
                $data_out['pay_param_ali'] = $aop->sdkExecute($request, true);
            } else {
                // $data_out['pay_h5'] = $aop->pageExecute($request, 'GET');
                $pay_url = $aop->pageExecute($request, 'GET');
                $data_out['pay_html'] = '<iframe src="' . $pay_url . '" class="frame-pay" out_trade_no="' . $pay_data['out_trade_no'] . '" sandbox="allow-scripts allow-top-navigation allow-same-origin"></iframe>';
            }
        } else {
            if ($is_weixin) {
                $trade_type = 'JSAPI';
                $config = Config::pull('wechat');
            } else {
                if (isset($pay_data['openid'])) unset($pay_data['openid']);
                $trade_type = 'MWEB';
                $config = Config::pull('opwx_app_pay');
            }

            $pay_data['total_fee'] = $paid;
            $pay_data['trade_type'] = $trade_type;
            $pay_data['spbill_create_ip'] = request()->ip();

            if ($debug) Log::record('pay_data ' . json_encode($pay_data));

            $app = new Application($config);
            $order = new easyOrder($pay_data);

            $res = $app->payment->prepare($order)->all();

            if ($debug) Log::record('pay res ' . json_encode($res));

            if ($res['return_code'] == 'SUCCESS' && $res['result_code'] == 'SUCCESS') {
                if ($is_weixin) {
                    $data_out['pay_param_wx'] = $app->payment->configForPayment($res['prepay_id'], false);//configForJSSDKPayment($res['prepay_id']);
                } elseif ($module_name == 'api') {
                    $data_out['pay_h5'] = $res['mweb_url'] . '&redirect_url=' . urlencode(config('site.domain') . '://?qushenghuo&out_trade_no=' . $pay_data['out_trade_no']);
                    $data_out['out_trade_no'] = $pay_data['out_trade_no'];
                } else {
                    $pay_url = $res['mweb_url'] . '&redirect_url=' . urlencode(config('site.domain') . '://?qushenghuo&out_trade_no=' . $pay_data['out_trade_no']);
                    $data_out['pay_html'] = '<iframe src="' . $pay_url . '" class="frame-pay" out_trade_no="' . $pay_data['out_trade_no'] . '" sandbox="allow-scripts allow-top-navigation allow-same-origin"></iframe>';
                }
            } else {
                return ['', '数据有误，请重试！10012006' . ($debug ? json_encode($res) . json_encode($pay_data) : ''), 1];
            }

            $pay_method = 'wx';
        }

        $data_out['pay_method'] = $pay_method;
        return [$data_out];
    }

    public static function merchant_pay($data)
    {
        $withdraw_price = round($data['price'] / 100, 2);
        $debug = env('APP_DEBUG');
        if ($debug) {
            $withdraw_price = 1;
            $data['price'] = 100;
        }

        if ($data['pay_type'] == 'ali') {
            Loader::addAutoLoadDir(env('EXTEND_PATH') . 'alipay/aop');
            Loader::addAutoLoadDir(env('EXTEND_PATH') . 'alipay/aop/request');

            $aop = new \AopClient();
            $request = new \AlipayFundTransToaccountTransferRequest();

            $request->setBizContent(json_encode([
                'out_biz_no' => $data['order_no'],
                'payee_type' => 'ALIPAY_LOGONID', // ALIPAY_USERID：支付宝账号对应的支付宝唯一用户号    ALIPAY_LOGONID：支付宝登录号
                'payee_account' => $data['user_proof'],
                'amount' => $withdraw_price,
                'payer_show_name' => '微选生活',
                'payee_real_name' => $data['real_name'],
                'remark' => '提现到账'
            ]));
            $result = $aop->execute($request);
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $result = $result->$responseNode;

            if (!empty($result->code) && $result->code == 10000) {
                $withdraw_status = 1;
                $withdraw_event = 'withdraw_success';
                $result_msg = serialize([
                    'title' => '提现成功',
                    'content' => '亲，你在' . date('Y年m月d日 H:i', $data['add_time']) . ' 提交提现到支付宝的资金<font color="#169aff">' . round($data['draw_price'] / 100, 2) . '元</font>已审核通过到账，支付宝按新规标准已收取1.2%手续费，请注意查收'
                ]);
            } else {
                $withdraw_status = 2;
                $withdraw_event = 'withdraw_fail';
                $result_msg = serialize([
                    'title' => '提现失败',
                    'content' => '亲，你在' . date('Y年m月d日 H:i', $data['add_time']) . ' 提交提现到支付宝的资金<font color="#169aff">' . round($data['draw_price'] / 100, 2) . '元</font>，提现失败，款项已退回到您的账户余额，提现时请注意填写提现账号以及相对应账号实名认证的真实姓名以及别留任何特殊符号，详细问题请到常见问题查看，如还不明白再咨询客服'
                ]);
//                 if ($result->sub_code === 'PAYEE_NOT_EXIST') {
//                     $result_msg = serialize([
//                         'title' => '提现失败，支付宝账号不正确', 
//                         'content' => '亲，你在'. date('Y年m月d日 H:i', $data['add_time']) .' 提交提现到微信的资金<font color="#169aff">'. $withdraw_price .'元</font>，提现失败，款项已退回到您的账户余额，提现时请注意填写提现账号以及相对应账号实名认证的真实姓名以及别留任何特殊符号，详细问题请到常见问题查看，如还不明白再咨询客服'
//                     ]);
//                 }

                $res = User::money($data['user_id'], $data['draw_price'], 'withdraw_refund', $data['id'], '提现失败');
                if (!isset($res['flow_id'])) {
                    return ['code' => 40002];
                }
            }
        } else {
            $tencent_config = db('user_withdraw_tencent')->field('app_id, mch_id, key')->where('status', 1)->find();
            $config = Config::pull('wechat');
            $config['app_id'] = $tencent_config['app_id'];
            $config['payment']['merchant_id'] = $tencent_config['mch_id'];
            $config['payment']['key'] = $tencent_config['key'];

            $app = new Application($config);

            $param = array(
                'partner_trade_no' => $data['order_no'],
                'check_name' => 'FORCE_CHECK',//NO_CHECK 不验证姓名  FORCE_CHECK 验证姓名
                're_user_name' => $data['real_name'],
                'openid' => $data['user_proof'],
                'amount' => $data['price'],
                'desc' => '微选生活',
                'spbill_create_ip' => request()->ip()
            );
            $result = $app->merchant_pay->send($param);

            if ($result->return_code === 'SUCCESS' && $result->result_code === 'SUCCESS') {
                $withdraw_status = 1;
                $withdraw_event = 'withdraw_success';
                $result_msg = serialize([
                    'title' => '提现成功',
                    'content' => '亲，你在' . date('Y年m月d日 H:i', $data['add_time']) . ' 提交提现到微信的资金<font color="#169aff">' . round($data['draw_price'] / 100, 2) . '元</font>已审核通过到账，微信按新规标准已收取1.2%手续费，请注意查收'
                ]);
            } else {
                $withdraw_status = 2;
                $withdraw_event = 'withdraw_fail';
                $result_msg = serialize([
                    'title' => '提现失败',
                    'content' => '亲，你在' . date('Y年m月d日 H:i', $data['add_time']) . ' 提交提现到微信的资金<font color="#169aff">' . round($data['draw_price'] / 100, 2) . '元</font>，提现失败，款项已退回到您的账户余额，提现时请注意填写提现账号以及相对应账号实名认证的真实姓名以及别留任何特殊符号，详细问题请到常见问题查看，如还不明白再咨询客服'
                ]);

                $res = User::money($data['user_id'], $data['draw_price'], 'withdraw_refund', $data['id'], '提现失败');
                if (!isset($res['flow_id'])) {
                    return ['code' => 40002];
                }
            }
        }

        $savedata = [
            'status' => $withdraw_status,
            'result' => json_encode($result)
        ];
        db('user_withdraw')->where('id', $data['id'])->update($savedata);

        // 添加系统通知
        db('user_dynamic')->insert([
            'user_id' => 0,
            'nickname' => '微选生活',
            'avator' => '/static/img/logo.png',
            'receive_uid' => $data['user_id'],
            'event' => $withdraw_event,
            'event_id' => $data['id'],
            'data' => $result_msg,
            'status' => 1,
            'add_time' => request()->time()
        ]);

        db('xinge_task')->insert([
            'user_id' => $data['user_id'],
            'event' => $withdraw_event,
            'data' => $result_msg
        ]);
    }
}
