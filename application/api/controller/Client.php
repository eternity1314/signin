<?php

namespace app\api\controller;

class Client extends Base
{
    protected function initialize()
    {
    }

    public function app_version()
    {
        if (request()->isPost()) {
            return $this->response();
        }

        $client_system = input('client_system'); // 系统，android、ios
        $core_version = input('core_version'); // APP内核版本
        if (empty($client_system) || empty($core_version)) {
            return $this->response('', 'param missing', 10017);
        }

        $status = db('app_version')
            ->where(['client_system' => $client_system, 'core_version' => $core_version])
            ->value('status');

        $data_out = [
            'force_update' => $status == 0 ? 1 : 0, // 强制更新
        ];

        $latest = db('app_version')
            ->where([['status', '=', 1], ['client_system', '=', $client_system], ['core_version', '>', $core_version]])
            ->field('app_version,core_version,down_url,update_content')
            ->order('core_version DESC,app_version DESC')->find();

        if (!empty($latest)) {
            $data_out['latest'] = $latest;
        }

        return $this->response($data_out);
    }
}
