<?php
/**
 * 淘宝客通用类
 * 1 生成淘口令
*/
class Tbk{
    private $appKey = '23551673';
    private $appSecret = 'a1af1ab057a1d30e656cfd1f6a22d7ae';
    
    // 生成淘口令
    public function get_tao_pass($data){
        // 必要参数
        $data = [
            'method' => 'taobao.tbk.tpwd.create', // API接口名称'
            'text' => $data['text'], // 口令弹框内容
            'url' => $data['url'], // 口令跳转url
            'user_id' => '119703097', // 生成口令的淘宝用户ID
            /*
            'tpwd_param' => json_encode([
                'url' => $data['url'], // 口令跳转url
                'text' => $data['text'], // 口令弹框内容
                'user_id' => '119703097', // 生成口令的淘宝用户ID
            ])
            */
        ];
        return $this->get_json($data);
        
    }
    
    // 请求淘宝客接口
    protected function get_json($data){
        //参数数组
        $paramArr = [
            'app_key' => $this->appKey,
            'method' => $data['method'],
            'format' => 'json',
            'v' => '2.0',
            'sign_method' => 'md5',
            'timestamp' => date('Y-m-d H:i:s'),
        ];
        // 参数合成
        $paramArr = array_merge($paramArr, $data);
        
        //生成签名
        $sign = $this->createSign($paramArr);
        //组织参数
        $strParam = $this->createStrParam($paramArr);
        $strParam .= 'sign='.$sign;
        //访问服务
        $url = 'http://gw.api.taobao.com/router/rest?'.$strParam;
        $result = file_get_contents($url);
        $result = json_decode($result, true);
        
        return $result;
    }
    
    //签名函数
    private function createSign ($paramArr) {
        $sign = $this->appSecret;
        ksort($paramArr);
        foreach ($paramArr as $key => $val) {
            if ($key !='' && $val !='') {
                $sign .= $key.$val;
            }
        }
        $sign .= $this->appSecret;
        $sign = strtoupper(md5($sign));
        return $sign;
    }
    
    //组参函数
    private function createStrParam ($paramArr) {
        $strParam = '';
        foreach ($paramArr as $key => $val) {
            if ($key !='' && $val !='') {
                $strParam .= $key.'='.urlencode($val).'&';
            }
        }
        return $strParam;
    }
}