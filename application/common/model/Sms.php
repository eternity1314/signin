<?php
namespace app\common\model;

use think\Db;
use think\facade\Cache;
use think\facade\Log;

class Sms
{
    protected static $_instance;

    public function __construct()
    {
        self::getInstance();
    }

    public static function getInstance($type = '', array $config = array(), $snyc = false)
    {
        if (empty($type)) {
            $type = 'chuanglan';
//            $type = 'Ronglian';
        }

        if (!isset(self::$_instance) || $snyc) {
            self::$_instance = new $type($config);
        }

        return self::$_instance;
    }

    public function send_code($mobile, $code = '', $msg, $limit_time = 600, $user_wxid = 0)
    {
        if (empty($code)) {
            $code = rand(100000, 999999);
        }
        
        try {
            Db('user_mobile_verify')->insert([
                'ip' => request()->ip(),
                'mobile' => $mobile,
                'code' => $code,
                'expire_time' => request()->time() + $limit_time,
                'add_time' => request()->time()
            ]);
        } catch (\Exception $e) {
            return [[], '短信发送失败，请重试！', 20001];
        }
        
        $res = self::$_instance->send($mobile, $msg);
        if ($res['errcode'] != 0) {
            $sms = new \dysms();
            $res = $sms->send([
                'TemplateCode' => 'SMS_152856936',
                'TemplateParam' => ['code' => $code],
                'PhoneNumbers' => $mobile
            ]);
            if (!empty($res['Code']) || $res['Code'] != 'OK') {
                Log::record('dysms send fail,' . json_encode($res, JSON_UNESCAPED_UNICODE));
                return [[], '短信发送失败，请重试！', 20002];
            }
        }
        
        return [[], '发送成功', 0];
    }

    public static function verify($mobile, $code)
    {
        $info = Db('user_mobile_verify')->where([
            ['mobile', '=', $mobile],
            ['verify', '=', 0],
            ['expire_time', '>', request()->time()]
        ])->order('id desc')
        ->find();

        if (!$info || $info['code'] != $code) {
            return [[], '验证码错误！', 40001];
        }
        
        Db('user_mobile_verify')->where(['id' => $info['id']])->update(['verify' => 1]);
        
        return 'SUCCESS';
    }
    
    public function __call($name, $arguments)
    {
        if (isset($arguments[2]['sms_type'])) {
            self::getInstance($arguments[2]['sms_type'], $arguments[2], true);
        }

        return call_user_func_array(array(self::$_instance, $name), $arguments);
    }
}