<?php

namespace app\common\model;

use think\facade\Config;

class Common
{
    public static function getLocation($data, $field = 'city')
    {
        if (!empty($data['coord'])) {
            return self::getCoordLocation($data['coord'], $field);
        } elseif (!empty($data['ip'])) {
            return self::getIpLocation($data['ip'], $field);
        }

        return false;
    }

    public static function getCoordLocation($location, $field = 'city')
    {
        try {
            if (is_array($location)) {
                $location = join(',', $location);
            }

            \think\Loader::addAutoLoadDir(env('extend_path') . 'request');
            \Requests::register_autoloader();

            $param = [
                'geotable_id' => 135675,
                'coord_type' => 'bd09ll',
                'ak' => 'rXeqw2UHzIxevVl5BBXDaVNKni9fS8tP',
            ];

            $param = http_build_query($param) . '&location=' . $location;
            $res = \Requests::get('http://api.map.baidu.com/cloudrgc/v1?' . $param, ['Referer' => 'http://signin.higoo123.com/']);
            $body = json_decode($res->body, true);

            if (!empty($field)) {
                if ($field == 'city'
                    && !empty($body['address_component'])
                    && !empty($body['address_component']['city'])) {
                    $city = $body['address_component']['city'];
                    if (mb_substr($city, -1, 1, 'UTF8') == "市") {
                        $city = mb_substr($city, 0, mb_strlen($city, 'UTF8') - 1, 'UTF8');
                    }

                    return $city;
                }

                return false;
            }

            return $body;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function getIpLocation($ip, $field = 'city')
    {
        try {
            if ($ip == '127.0.0.1') $ip = '219.136.75.215';

            \think\Loader::addAutoLoadDir(env('extend_path') . 'request');
            \Requests::register_autoloader();

//        'http://opendata.baidu.com/api.php?query=219.136.75.215&co=&resource_id=6006&t=1433920989928&ie=utf8&oe=utf-8&format=json';
            $res = \Requests::get('http://api.map.baidu.com/location/ip?ak=rXeqw2UHzIxevVl5BBXDaVNKni9fS8tP&ip=' . $ip, ['Referer' => 'http://signin.higoo123.com/']);
            $body = json_decode($res->body, true);

            if (!empty($field)) {
                if ($field == 'city'
                    && !empty($body['content'])
                    && !empty($body['content']['address_detail'])
                    && !empty($body['content']['address_detail']['city'])) {
                    $city = $body['content']['address_detail']['city'];
                    if (mb_substr($city, -1, 1, 'UTF8') == "市") {
                        $city = mb_substr($city, 0, mb_strlen($city, 'UTF8') - 1, 'UTF8');
                    }

                    return $city;
                }

                return false;
            }

            return $body;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 格式化参数格式化成url参数
     */
    public static function toUrlParams($param, $trim = false)
    {
        $buff = "";
        foreach ($param as $k => $v) {
            if ($k != "sign" && $v != "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }

        if ($trim) {
            $buff = trim($buff, "&");
        }

        return $buff;
    }

    public static function aeskey()
    {
        return 'KM4zGmc07VywIAanMh33g8xtWdPANtV2jyyL9PZp0jf';
    }

    /**
     * 生成签名
     * @return 签名，本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
     */
    public static function makeSign($param, $sign_type = 'sha1')
    {
        //签名步骤一：按字典序排序参数
        ksort($param);
        $string = self::toUrlParams($param);
        //签名步骤二：在string后加入KEY
        $string = $string . "AESKey=" . self::aeskey();
        //签名步骤三：MD5加密
        $string = $sign_type($string);
        //签名步骤四：所有字符转为大写
        //$string = strtoupper($string);
        return $string;
    }

    public static function today_time()
    {
        return strtotime(date('Y-m-d', request()->time()));
    }

    public static function today_date($format = 'Ymd')
    {
        return date($format, request()->time());
    }

    public static function time_format($time = 0, $format = '')
    {
        if (empty($time)) $time = request()->time();
        if (empty($format)) $format = 'Y-m-d H:i';

        return date($format, $time);
    }

    /**
     * crul模拟请求
     * @param string $url
     * @param array $data
     * @param string $method
     * @param string $is_cert
     * @return mixed
     */
    public static function curl($url, $data = array(), $method = 'GET')
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
//         curl_setopt($ch, CURLOPT_REFERER, "https://www.toutiao.com/");   //构造来路
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);  //设定是否输出页面头部内容
        //$headers[] = 'X-Apple-Tz: 0';
        //$headers[] = 'X-Apple-Store-Front: 143444,12';
        //$headers[] = 'Accept-Encoding: gzip, deflate';
        //$headers[] = 'Accept-Language: en-US,en;q=0.5';
        //$headers[] = 'Cache-Control: no-cache';
        //$headers[] = 'Content-Type: application/json; charset=utf-8';
        $headers[] = 'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.139 Safari/537.36';
        //$headers[] = 'X-MicrosoftAjax: Delta=true';

        switch ($method) {
            case 'GET':
                break;
            case 'POST':
                // php5.6 上传文件使用
                //curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);

                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data); //设置请求体，提交数据包
                break;
            case 'PUT':
                $headers[] = 'X-HTTP-Method-Override: PUT';
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data); //设置请求体，提交数据包
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code != '200') {
            $data = json_encode(['code' => $http_code, 'msg' => '请求失败！']);
        }

        curl_close($ch);
        return $data;
    }

    /**
     * b64转成安全的url
     * @param string $string
     * @return string
     */
    public static function base64url_encode($string)
    {
        $data = base64_encode($string);
        return str_replace(array('+', '/', '='), array('-', '_', ''), $data);
    }

    /**
     * b64转成安全的url
     * @param string $string
     * @return string
     */
    public static function base64url_decode($string)
    {
        $data = str_replace(array('-', '_'), array('+', '/'), $string);
        $mod4 = strlen($data) % 4;
        if ($mod4) {
            $data .= substr('====', $mod4);
        }

        return base64_decode($data);
    }

    /**
     * 说明:aes-ecb加密
     * @param string $key 秘钥
     * @param string $data 需加密字符串
     * @return string 密文
     */
    public static function encrypt($key, $data)
    {
        $salt = 'S8@c2c#0f1f$1518%e510&ee90b*deRq';
        $key = md5(md5($key) . $salt);//对key复杂处理，并设置长度

        //该函数的5个参数分 别如下：cipher——加密算法、key——密钥、data(str)——需要加密的数据、mode——算法模式、 iv——初始化向量
        $endata = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $data, MCRYPT_MODE_CBC, md5($key));

        // base64加密
        $endata = self::base64url_encode($endata);

        return $endata;
    }

    /**
     * 说明:aes-ecb解密
     * @param string $key 秘钥
     * @param string $data 密文
     * @return string 解密后字符串
     */
    public static function decrypt($key, $endata)
    {
        $salt = 'S8@c2c#0f1f$1518%e510&ee90b*deRq';
        $key = md5(md5($key) . $salt);//对key复杂处理，并设置长度

        // base64解密
        $endata = self::base64url_decode($endata);

        //解密函数
        $data = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $endata, MCRYPT_MODE_CBC, md5($key)), "\0");

        return $data;
    }

    // $string： 明文 或 密文
    // $operation：DECODE表示解密,其它表示加密
    // $key： 密匙
    // $expiry：密文有效期
    public static function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0)
    {
        // 动态密匙长度，相同的明文会生成不同密文就是依靠动态密匙
        $ckey_length = 4;
        // 密匙
        $key = md5($key != '' ? $key : getglobal('authkey'));
        // 密匙a会参与加解密
        $keya = md5(substr($key, 0, 16));
        // 密匙b会用来做数据完整性验证
        $keyb = md5(substr($key, 16, 16));
        // 密匙c用于变化生成的密文
        $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : '';
        // 参与运算的密匙
        $cryptkey = $keya . md5($keya . $keyc);
        $key_length = strlen($cryptkey);
        // 明文，前10位用来保存时间戳，解密时验证数据有效性，10到26位用来保存$keyb(密匙b)，解密时会通过这个密匙验证数据完整性
        // 如果是解码的话，会从第$ckey_length位开始，因为密文前$ckey_length位保存 动态密匙，以保证解密正确
        $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string;
        $string_length = strlen($string);


        $result = '';
        $box = range(0, 255);


        $rndkey = array();
        // 产生密匙簿
        for ($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }
        // 用固定的算法，打乱密匙簿，增加随机性，好像很复杂，实际上对并不会增加密文的强度
        for ($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }
        // 核心加解密部分
        for ($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            // 从密匙簿得出密匙进行异或，再转成字符
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }
        if ($operation == 'DECODE') {
            // substr($result, 0, 10) == 0 验证数据有效性
            // substr($result, 0, 10) - time() > 0 验证数据有效性
            // substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16) 验证数据完整性
            // 验证数据有效性，请看未加密明文的格式
            if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16)) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            // 把动态密匙保存在密文里，这也是为什么同样的明文，生产不同密文后能解密的原因
            // 因为加密后的密文可能是一些特殊字符，复制过程可能会丢失，所以用base64编码
            return $keyc . str_replace('=', '', base64_encode($result));
        }
    }

    public static function endecrypt($string, $operation, $key = '')
    {
        $key = md5($key);
        $key_length = strlen($key);
        $string = $operation == 'D' ? base64_decode($string) : substr(md5($string . $key), 0, 8) . $string;
        $string_length = strlen($string);
        $rndkey = $box = array();
        $result = '';
        for ($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($key[$i % $key_length]);
            $box[$i] = $i;
        }
        for ($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }
        for ($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }
        if ($operation == 'D') {
            if (substr($result, 0, 8) == substr(md5(substr($result, 8) . $key), 0, 8)) {
                return substr($result, 8);
            } else {
                return '';
            }
        } else {
            return str_replace('=', '', base64_encode($result));
        }
    }

//    public static function encrypt3($input, $key)
//    {
//        $size = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB);
//        $input = self::pkcs5_pad($input, $size);
//        $td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_ECB, '');
//        $iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
//        mcrypt_generic_init($td, self::hextobin($key), $iv);
//        $data = mcrypt_generic($td, $input);
//        mcrypt_generic_deinit($td);
//        mcrypt_module_close($td);
//        $data = base64_encode($data);
//        return $data;
//    }
//
//    public static function pkcs5_pad($text, $blocksize)
//    {
//        $pad = $blocksize - (strlen($text) % $blocksize);
//        return $text . str_repeat(chr($pad), $pad);
//    }
//
//    public static function decrypt3($sStr, $sKey)
//    {
//        $decrypted = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, self::hextobin($sKey), base64_decode($sStr), MCRYPT_MODE_ECB);
//        $dec_s = strlen($decrypted);
//        $padding = ord($decrypted[$dec_s - 1]);
//        $decrypted = substr($decrypted, 0, -$padding);
//        return $decrypted;
//    }
//
//    public static function hextobin($hexstr)
//    {
//        $n = strlen($hexstr);
//        $sbin = "";
//        $i = 0;
//        while ($i < $n) {
//            $a = substr($hexstr, $i, 2);
//            $c = pack("H*", $a);
//            if ($i == 0) {
//                $sbin = $c;
//            } else {
//                $sbin .= $c;
//            }
//            $i += 2;
//        }
//        return $sbin;
//    }

    /**
     * PHP DES 加密程式
     *
     * @param $key 密鑰（八個字元內）
     * @param $encrypt 要加密的明文
     * @return string 密文
     */
    public static function des_encrypt($encrypt, $key = '')
    {
        if (empty($key)) $key = Common::aeskey();
        $key = substr($key, 0, 8);

        // 根據 PKCS#7 RFC 5652 Cryptographic Message Syntax (CMS) 修正 Message 加入 Padding
        $block = mcrypt_get_block_size(MCRYPT_DES, MCRYPT_MODE_ECB);
        $pad = $block - (strlen($encrypt) % $block);
        $encrypt .= str_repeat(chr($pad), $pad);

        // 不需要設定 IV 進行加密
        $passcrypt = mcrypt_encrypt(MCRYPT_DES, $key, $encrypt, MCRYPT_MODE_ECB);
        return base64_encode($passcrypt);
    }

    /**
     * PHP DES 解密程式
     *
     * @param $key 密鑰（八個字元內）
     * @param $decrypt 要解密的密文
     * @return string 明文
     */
    public static function des_decrypt($decrypt, $key = '')
    {
        if (empty($key)) $key = Common::aeskey();
        $key = substr($key, 0, 8);

        // 不需要設定 IV
        $str = mcrypt_decrypt(MCRYPT_DES, $key, base64_decode($decrypt), MCRYPT_MODE_ECB);

        // 根據 PKCS#7 RFC 5652 Cryptographic Message Syntax (CMS) 修正 Message 移除 Padding
        $pad = ord($str[strlen($str) - 1]);
        return substr($str, 0, strlen($str) - $pad);
    }


    public static function site($name = '')
    {
        $config = Config::get('site');
        if (empty($config)) {
            $config = Config::load(env('root_path') . 'config/site.php', 'site');
        }

        if (!empty($name) && isset($config[$name])) {
            return $config[$name];
        }

        return $config;
    }

    public static function isWeixin()
    {
        return !empty($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false;
    }

    public static function make_qrcode($url, $file_qrcode)
    {
        require_once '../extend/phpqrcode.php';

        // 保存二维码
        \QRcode::png($url, $file_qrcode, QR_ECLEVEL_L, 10, 1);

        if (!file_exists($file_qrcode)) {
            return '';
        }

        return $file_qrcode;
    }

    //将XML转为array
    public static function xmlToArray($xml)
    {
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $values;
    }

    /**
     * 采集远程文件
     * @access public
     * @param string $remote 远程文件名
     * @param string $local 本地保存文件名
     * @return mixed
     */
    public static function curlDownload($remote, $save_path, $save_name)
    {
        $cp = curl_init($remote);
        if (!is_dir($save_path)) {
            mkdir($save_path, 0777, true);
        }

        $fp = fopen($save_path . $save_name, "w");
        curl_setopt($cp, CURLOPT_SSL_VERIFYPEER, false);//跳过ssl验证
        curl_setopt($cp, CURLOPT_FILE, $fp);
        curl_setopt($cp, CURLOPT_HEADER, 0);
        curl_exec($cp);
        curl_close($cp);
        fclose($fp);
    }

    /**
     * 获取http请求头
     */
    public static function get_all_headers()
    {
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }

    /**
     * 说明:随机抽取数组中n条记录
     * @param array $arr 数组
     * @param number $max 抽取的记录数
     * @return multitype:number
     */
    public static function arr_shuffle($arr, $max = 0, $key)
    {
        $temp = 0;
        $index = 0;
        $shuffle = [];
        for ($i = 0; $i < $max; $i++) {
            $index = mt_rand($i, count($arr) - 1);
            if ($key) {
                $temp = $arr[$index][$key];
            } else {
                $temp = $arr[$index];
            }

            $arr[$index] = $arr[$i];
            $shuffle[]['code'] = $temp;
        }

        return $shuffle;
    }

    public static function is_challenge_stat()
    {
        return cache('challenge_stat');
    }

    public static function is_stat_ing()
    {
        $time = request()->time();
        $today_time = Common::today_time();

        if ($time > $today_time + 11 * 60 * 60 && $time < $today_time + 12 * 60 * 60 && !Common::is_challenge_stat()) {
            return true;
        }

        return false;
    }

    /**作用：统计字符长度包括中文、英文、数字
     * 参数：需要进行统计的字符串、编码格式目前系统统一使用UTF-8
     * 修改记录:
     * $str = "kds";
     * echo sstrlen($str,'utf-8');
     * */
    public static function sstrlen($str)
    {
        $len = strlen($str);
        $i = 0;
        $l = 0;
        while ($i < $len) {
            if (preg_match("/^[" . chr(0xa1) . "-" . chr(0xff) . "]+$/", $str[$i])) {
                $l++;
                $i += 2;
            } else {
                $i += 1;
            }
        }
        return $l;
    }

    public static function short_url($url)
    {
//            // 腾讯 eq:https://w.url.cn/s/AkFcOf8
//            $app = new Application(config('wechat.'));
//            $ret = $app->url->shorten($url);
//            if (!empty($ret['short_url'])) $url = $ret['short_url'];

        // 新浪 eg:http://t.cn/ReCqY16
        $ret = file_get_contents('http://api.t.sina.com.cn/short_url/shorten.json?source=3271760578&url_long=' . $url);
        $ret = json_decode($ret, true);
        if ($ret && !empty($ret[0]) && !empty($ret[0]['url_short'])) return $ret[0]['url_short'];

        return false;
    }

    public static function potential_market($user_id)
    {
        $invite_code = input('invite_code');
        if ($invite_code) {
            $pid = db('user_promotion')->where('invite_code', $invite_code)->value('user_id');
        } else {
            $from_uid = input('from_uid');
            if ($from_uid) {
                $pid = db('user')->where('id', $from_uid)->value('id');
            }
        }

        if (!empty($pid) && $pid != $user_id) {
            // 谁看过我的免单
            $rs = db('goods_free_share_user')->where([
                ['user_id', '=', $user_id],
                ['pid', '=', $pid]
            ])->find();
            if ($rs) {
                db('goods_free_share_user')->where('id', $rs['id'])->update(['add_time' => request()->time()]);
            } else {
                db('goods_free_share_user')->insert([
                    'user_id' => $user_id,
                    'pid' => $pid,
                    'add_time' => request()->time()
                ]);
            }

            // 绑定上级
            if (session('user.is_new_user')) {
                $rs = db('user_tier')->where('user_id', $user_id)->count();
                if ($rs == 0) {
                    db('user_tier')->insert([
                        'user_id' => $user_id,
                        'pid' => $pid,
                        'add_time' => request()->time()
                    ]);
                }
            }
        }
    }

    // 绑定上级
    public static function bind_tier($user_id)
    {
        if (empty(session('user.is_new_user'))) return false;

        $invite_code = input('invite_code');
        if ($invite_code) {
            $pid = db('user_promotion')->where('invite_code', $invite_code)->value('user_id');
        } else {
            $from_uid = input('from_uid');
            if ($from_uid) {
                $pid = db('user')->where('id', $from_uid)->value('id');
            }
        }

        if (!empty($pid) && $pid != $user_id) {
            $rs = db('user_tier')->where('user_id', $user_id)->count();
            if ($rs == 0) {
                db('user_tier')->insert([
                    'user_id' => $user_id,
                    'pid' => $pid,
                    'add_time' => request()->time()
                ]);
            }
        }
    }
}