<?php
/**
 * 淘宝客通用类
 * 1 生成淘口令
*/
class ddk{
    private $client_id = 'db9f097f883c467eb1ee5854328abfc2';
    private $client_secret = '68532fa1adb5aa48c9af2a4d972a1eb5c89e41ef';
    
    // 获取拼多多商品
    public function get_goods($data){
        // 必要参数
        $data['client_id'] = $this->client_id;
        return $this->get_json($data);
        
    }
    
    // 请求淘宝客接口
    protected function get_json($data){
        //参数数组
        $paramArr = [
            'client_id' => $this->client_id,
            'data_type' => 'JSON',
            'v' => '2.0',
            'sign_method' => 'md5',
            'timestamp' => time(),
        ];
        // 参数合成
        $paramArr = array_merge($paramArr, $data);
        
        //生成签名
        $sign = $this->createSign($paramArr);
        //组织参数
        $strParam = $this->createStrParam($paramArr);
        $strParam .= 'sign='.$sign;
        //访问服务
        $url = 'http://gw-api.pinduoduo.com/api/router?'.$strParam;
        $result = $this->curl($url, [], 'POST');
        $result = json_decode($result, true);
        
        return $result;
    }
    
    public function add_generate($num){
        $data['number'] = $num;
        $data['type'] = 'pdd.ddk.goods.pid.generate';
        
        return $this->get_json($data);
    }
    
    protected static function curl($url, $data=array(), $method = 'GET'){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_HEADER, false);  //设定是否输出页面头部内容
    
        if( $method == 'POST' ){
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    
        $data =  curl_exec($ch);
        curl_close ($ch);
        return $data;
    }
    
    //签名函数
    private function createSign ($paramArr) {
        $sign = $this->client_secret;
        ksort($paramArr);
        foreach ($paramArr as $key => $val) {
            if ($key !='' && $val !='') {
                $sign .= $key.$val;
            }
        }
        $sign .= $this->client_secret;
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