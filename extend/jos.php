<?php
/**
 * 淘宝客通用类
 * 1 生成淘口令
*/
class jos{
    private $client_id = 'E12DB5CCEE461592D450E625A33EA5A3';
    private $client_secret = 'af0789d818d74b6aac1313f0cf42d289';
    
    public function get_code(){
        $param = [
            'response_type' => 'code',
            'client_id' => $this->client_id,
            'redirect_uri' => 'http://signin_tp5.com/jingdong/index'
        ];

        $url = 'https://oauth.jd.com/oauth/authorize?'. http_build_query($param);
		header("Location: $url");
		exit;
    }
    
    public function oauth()
    {
        $code = input('code');
        if (!$code) {
            return false;
        }
    
        $param = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => 'http://signin_tp5.com/jingdong/index',
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret
        ];
        $data = $this->curl('https://oauth.jd.com/oauth/token?'. http_build_query($param));
        
        db('siteinfo')->where('key', '=', 'jd_access_token')->update([
            'value' => $data
        ]);
    }
    
    public function refresh_access_token(){
        $jd_token = db('siteinfo')->where('key', '=', 'jd_access_token')->value('value');
        $res = json_decode(trim($jd_token), true);
        
        if ($res['code'] == 0) {
            if ($res['expires_in'] * 1000 + $res['time']  <  self::getMillisecond() - 86400000) {
                // 过期前一天
                $param = [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $res['refresh_token'],
                    'client_id' => $this->client_id,
                    'client_secret' => $this->client_secret
                ];
                $new_access_token_json = $this->curl('https://oauth.jd.com/oauth/token?'. http_build_query($param));
                db('siteinfo')->where('key', '=', 'jd_access_token')->update([
                    'value' => $new_access_token_json
                ]);
                
                $new_access_token_arr = json_decode($new_access_token_json,true);
                $accessToken = $new_access_token_arr['access_token'];
            } else {
                $accessToken = $res['access_token'];
            }
            return $accessToken;
        } else {
            return $res['msg'];
        }
    }
    
    // 获取拼多多商品
    public function get_goods($data){
        // 必要参数
        $data['client_id'] = $this->client_id;
        return $this->get_json($data);
        
    }
    
    // 请求淘宝客接口
    public function get_json($method, $data){
        //参数数组
        $paramArr = [
            'method' => $method,
            'access_token' => $this->refresh_access_token(),
            'app_key' => $this->client_id,
            'timestamp' => date('Y-m-d H:i:s'),
            'v' => '2.0',
            '360buy_param_json' => json_encode($data)
        ];
        
        //生成签名
        $sign = $this->createSign($paramArr);
        //组织参数
        $strParam = $this->createStrParam($paramArr);
        $strParam .= 'sign='.$sign;
        //访问服务
        $url = 'https://api.jd.com/routerjson?'.$strParam;
        $result = self::curl($url, [], 'POST');
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
    
    //  获取13位时间戳
    private static function  getMillisecond(){
        list($t1, $t2) = explode(' ', microtime());
        return sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
    }
    
    // 查询商品详情图片
    public function getGoodsDetail($sku_id){
        if (!$sku_id) {
            return ['code' => 0, 'data' => [], 'msg' => '参数不存在'];
        }
    
        if(!is_numeric($sku_id)){
            return ['code' => 0, 'data' => [], 'msg' => '不是有效的商品ID'];
        }
        $jdurl = "http://item.jd.com/$sku_id.html";
        $html = $this->curl($jdurl);
        preg_match("/desc: '(\S+)'/",$html, $regs);
    
        if(count($regs)==2){
            $descUrl  = $regs[1];
            $detail = file_get_contents("https:".$descUrl);
            if($detail){
                $detail = json_decode($detail,true);
                $content = $detail["content"];
    
                if(strstr($content,"data-lazyload")>-1){
                    $regImg = '/data-lazyload="([^ \t]+)"/';
                    preg_match_all($regImg,$content,$matchAll);
                    return ['code' => 1, 'data' => $matchAll[1], 'msg' => 'success'];
                }else{
                    return ['code' => 1, 'data' => $content, 'msg' => 'success'];
                }
            }
        }
        return ['code' => 0, 'data' => [], 'msg' => '没有解析到详情信息'];
    }
}