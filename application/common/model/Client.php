<?php

namespace app\common\model;

use think\exception\HttpResponseException;

class Client
{
    public static function check_app_version($client_system, $core_version)
    {
        if (empty($client_system) || empty($core_version)) {
            throw new HttpResponseException(json(['msg' => 'token missing', 'code' => 10011]));
        }

        $res = db('app_version')->where([
                ['client_system', '=', $client_system],
                ['core_version', '=', $core_version]
            ]
        )->find();

        if (empty($res)) {
            self::check_app_version_fail($client_system, 10012, 'app version missing');
        }

        if (empty($res['status'])) {
            self::check_app_version_fail($client_system, 10013, 'app version invalid');
        }

        return true;
    }

    public static function check_app_version_fail($client_system, $errcode, $errmsg)
    {
        $data = self::latest_app_version($client_system);

        throw new HttpResponseException(json(['data' => $data, 'msg' => $errmsg, 'code' => $errcode]));
    }

    public static function latest_app_version($client_system)
    {
        return db('app_version')
            ->where([['status', '=', 1], ['client_system', '=', $client_system]])
            ->field('app_version,core_version,down_url,update_content')
            ->order('core_version DESC,app_version DESC')->find();
    }
}
