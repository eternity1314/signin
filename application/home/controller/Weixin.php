<?php

namespace app\home\controller;

use EasyWeChat\Foundation\Application;
use think\Db;

class Weixin
{
    public $app;
    public $withdraw_tencent_id;

    public function index()
    {
        // 查看提现正在使用的公众号
        $withdraw_tencent = Db::name('user_withdraw_tencent')->field('id, app_id, secret, token, aes_key')->where('status', 1)->find();
        if (!$withdraw_tencent) {
            return 'fail';
        }

        $this->withdraw_tencent_id = $withdraw_tencent['id'];
        $config = [
            'debug' => true,
            'app_id' => $withdraw_tencent['app_id'],
            'secret' => $withdraw_tencent['secret'],
            'token' => $withdraw_tencent['token'],
            'aes_key' => $withdraw_tencent['aes_key'],

            'log' => [
                'level' => 'debug',
                'permission' => 0777,
                'file' => '/tmp/easywechat.log',
            ]
        ];

        $this->app = new Application($config);
        $server = $this->app->server;

        $server->setMessageHandler(function ($message) {
            // 消息类型：event, text....
            if ($message->MsgType == 'event') {
                if ($message->Event == 'subscribe') {
                    $user = $this->app->user->get($message->FromUserName);
                    $user = Db::name('user')->field('id')->where('unionid', $user->unionid)->find();
                    if ($user) {
                        Db::name('user_withdraw_tencent_relation')->insert([
                            'user_id' => $user['id'],
                            'withdraw_tencent_id' => $this->withdraw_tencent_id,
                            'openid' => $message->FromUserName
                        ]);
                    }
                } elseif ($message->Event == 'CLICK') {
                    $key = $message->EventKey;
                    if ($key == 'kefu') {
                        return '客服微信号：kangnaixin1989';
                    }
                }
            }

            return "您好！欢迎关注我!";
        });

        $response = $server->serve();

        return $response->send();
    }

    public function withdraw_subscribe()
    {
        // 查看提现正在使用的公众号
        $withdraw_tencent = Db::name('user_withdraw_tencent')->field('id, app_id, secret, token, aes_key')->where('status', 1)->find();
        if (!$withdraw_tencent) {
            return 'fail';
        }

        $this->withdraw_tencent_id = $withdraw_tencent['id'];
        $config = [
            'debug' => true,
            'app_id' => $withdraw_tencent['app_id'],
            'secret' => $withdraw_tencent['secret'],
            'token' => $withdraw_tencent['token'],
            'aes_key' => $withdraw_tencent['aes_key'],

            'log' => [
                'level' => 'debug',
                'permission' => 0777,
                'file' => '/tmp/easywechat.log',
            ]
        ];

        $this->app = new Application($config);
        $server = $this->app->server;

        $server->setMessageHandler(function ($message) {
            // 消息类型：event, text....
            if ($message->MsgType == 'event') {
                if ($message->Event == 'subscribe') {
                    $user = $this->app->user->get($message->FromUserName);
                    $user = Db::name('user')->field('id')->where('unionid', $user->unionid)->find();
                    if ($user) {
                        Db::name('user_withdraw_tencent_relation')->insert([
                            'user_id' => $user['id'],
                            'withdraw_tencent_id' => $this->withdraw_tencent_id,
                            'openid' => $message->FromUserName
                        ]);
                    }
                }
            }
            return "您好！欢迎关注我!";
        });

        $response = $server->serve();

        return $response->send();
    }
}