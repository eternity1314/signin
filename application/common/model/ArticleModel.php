<?php
namespace app\common\model;

use think\Db;
use think\Model;
use think\facade\Config;
use function GuzzleHttp\json_encode;

class ArticleModel extends Model
{
    protected $table = 'sl_article';
    
    protected function base($query)
    {
        $query->where('status', 1);
    }
    
    public function author(){
        return $this->hasOne(User::class, 'id', 'author');
    }
    
    public function collect_article($cate, $rows, $signature){
        if (!$cate) {
            $this->error('参数错误');
        }
    
        $url_source = 'taotiao_cate';
        switch ($cate) {
            case 2:
                $cate_name = 'news_sports';
                break;
            case 3:
                $cate_name = 'news_travel';
                break;
            case 4:
                $cate_name = 'funny';
                break;
            case 5:
                $cate_name = 'finance_management';
                break;
            case 6:
                $cate_name = 'news_baby';
                break;
            case 7:
                $cate_name = 'news_food';
                break;
            default:
                $cate_name = 'news_regimen';
                break;
        }

        $article_data = [];
        switch ($url_source) {
            case 'taotiao_cate' :
                // 头条文章 下一条请求时间
                $max_behot_time = 0;
                $img_list = [];
                while (1) {
                    $url = 'https://www.toutiao.com/api/pc/feed/?category='. $cate_name .'&utm_source=toutiao&widen=1&max_behot_time='. $max_behot_time .'&max_behot_time_tmp='. $max_behot_time .'&tadrequire=true&as=A1E5CACF41F204C&cp=5AF162A064DC4E1&_signature='. $signature;
                    $data = Common::curl($url);
                    $data = json_decode($data, true);print_r($data);
                    if ($data['message'] == 'error') {
                        continue;
                    }
                    
                    $max_behot_time = $data['next']['max_behot_time'];
    
                    foreach ($data['data'] as $key=>$val) {
                        if (isset($val['ad_id'])) {
                            continue;
                        }
                        
                        // 文章详情
                        $url = 'https://www.toutiao.com/a'. $val['item_id'] .'/';
                        $detail = Common::curl($url);
                        //print_r($detail);exit;
                        // 匹配文章内容
                        $arr = [];
                        $img_list = [];
                        if ($val['source'] == '悟空问答') {
                            preg_match('/"content_abstract":{"text":"(.*?)\",\"avatar_url\"/s', $detail, $arr);
                        } else {
                            preg_match('/articleInfo:\s{.*?content:\s\'(.*?)\'/s', $detail, $arr);
                        }
                        //print_r($arr[$key]);exit;
                        //echo $arr[$key][0];exit;
                        // 下载图片
                        if (isset($arr[1]) && $arr[1]) {
                            preg_match_all('/(?<=&gt;&lt;img src&#x3D;&quot;).*?(?=&quot;)/', $arr[1], $img_list);
                            if ($img_list) {
                                $val['image_list'] = [];
                                foreach ($img_list[0] as $img_item) {
                                    $save_path = './media/'. date('Ymd') .'/';
                                    $save_name = 'a' . substr(time(), -8) . substr(microtime(), 2, 5) .'.jpg';
                                    Common::curlDownload($img_item, $save_path, $save_name);
                                    $download_name = config('site.url') . trim($save_path, '.') . $save_name;
                                    $arr[1] = str_replace($img_item, $download_name, $arr[1]);
                                    $val['image_list'][] = $download_name;
                                }
                            }
                        } else {
                            continue;
                        }
                        
                        $article_data[] = [
                            'title' => $val['title'],
                            'author' => '5201314',
                            'content' => $arr[1],
                            'describe' => $val['abstract'],
                            'type' => 1,
                            'read_num' => 1000,
                            'add_time' => $val['behot_time'],
                            'info' => json_encode([
                                'img_list' => $val['image_list'],
                                'show_type' => isset($val['image_list'][2]) ? mt_rand(1, 2) : 1
                            ])
                        ];
                    }
                    
                    if (isset($article_data[$rows])) {
                        break;
                    }
                }
                //print_r($data);
                break;
        }
        
        db('article')->insertAll($article_data);
    }
    
    public function collect_video($url, $rows)
    {
        set_time_limit(0);
        $video_data = [];
        $offset = 0;
        while(1){
            $url .= '?&offset='. $offset;
            $video_container = Common::curl($url);
            //print_r($video_container);exit;
            // 视频内容
            $arr = [];
            preg_match_all('/<li\sclass=\"list\_item\".*?>\s*?<a\shref=\"(.*?)\"\sclass=\"figure\".*?>.*?<img.*?r-lazyload=\"(.*?)"\salt=\"(.*?)\".*?>.*?<span\sclass=\"figure_info_left\">(.*?)<\/span>.*?<\/a>.*?<\/li>/s', $video_container, $arr);

            foreach ($arr[1] as $item_k => $item_link) {
                // 视频地址
                $res = [];
                $res = $this->getVideoInfo($item_link);
                if ($res['code'] != 0) {
                    continue;
                }
                $save_path = './media/video/';
                $save_name = date('Ymd') . substr(time(), -5) . substr(microtime(), 2, 5) . substr(md5(mt_rand(1, 2000)), -8) .'.mp4';
                Common::curlDownload($res['url'], $save_path, $save_name);
                if (filesize($save_path . $save_name) == 0) {
                    // 检查视频问题
                    unlink($save_path . $save_name);
                    continue;
                }
                $video_path = config('site.url') . trim($save_path, '.') . $save_name;
                
                // 图片下载
                $save_path = './media/'. date('Ymd') .'/';
                $save_name = 'v' . substr(time(), -8) . substr(microtime(), 2, 5) .'.jpg';
                
                if (strpos($arr[2][$item_k], '//') === 0) {
                    $arr[2][$item_k] = 'http:' . $arr[2][$item_k];
                }
                Common::curlDownload($arr[2][$item_k], $save_path, $save_name);
                $image_path = config('site.url') . trim($save_path, '.') . $save_name;
                
                $video_data[] = [
                    'title' => $arr[3][$item_k],
                    'author' => '5201314',
                    'content' => '<video style="width: 100%; height: 100%;" src="'. $video_path .'"></video>',
                    'type' => 2,
                    'read_num' => 1000,
                    'add_time' => request()->time(),
                    'info' => json_encode([
                        'video_img' => $image_path,
                        'video_length' => $arr[4][$item_k]
                    ])
                ];
                
                if (isset($video_data[$rows])) {
                    break 2;
                }
            }
            
            $offset += 30;
        }
        
        db('article')->insertAll($video_data);
    }
    
    public function getVideoInfo($url)
    {
        //        $url = "https://v.qq.com/x/page/c0025lmctmo.html";
        preg_match("/\/([0-9a-zA-Z]+).html/", $url, $match);
        if (empty($match)) {
            return [
                'code' => 1,
                'msg' => '地址出错',
                'url' => $url
            ];
        }
        $vid = $match[1];//视频ID
        
        $getinfo = "http://vv.video.qq.com/getinfo?vids={$vid}&platform=11&charge=0&otype=xml";
        $info = Common::curl($getinfo);
        $info_arr = Common::xmlToArray($info);
        if (isset($info_arr['msg']) && $info_arr['msg'] == 'vid is wrong') {
            return [
                'code' => 1,
                'msg' => '视频出错',
                'url' => $url
            ];
        }
        $fi = $info_arr['fl']['fi'];
        if(isset($fi[1])){
            $format_id = $fi[1]['id'];
            $fmt = $fi[1]['name'];
            $format = 'p'.substr($format_id,-3,3);
            $key = $info_arr['vl']['vi']['fvkey'];
            $vid = $info_arr['vl']['vi']['vid'];
            $url = $info_arr['vl']['vi']['ul']['ui'][0]['url'];
            if(strlen($format_id)>=5){
                $mp4 = $vid.'.'.$format.'.1.mp4';
            }else{
                $mp4 = $vid.'.mp4';
            }
            $video_url = $url . $mp4 .'?vkey='.$key.'&fmt='.$fmt;
        
        }else{
            $getinfo = "http://vv.video.qq.com/getinfo?vids={$vid}&platform=101001&charge=0&otype=xml";
            $info = Common::curl($getinfo);
            $info_arr = Common::xmlToArray($info);
            if (isset($info_arr['msg']) && $info_arr['msg'] == 'vid is wrong') {
                return [
                    'code' => 0,
                    'msg' => '视频出错',
                    'url' => $url
                ];
            }
            $filename = $info_arr['vl']['vi']['fn'];
            $key = $info_arr['vl']['vi']['fvkey'];
            $url = $info_arr['vl']['vi']['ul']['ui'][0]['url'];
            $video_url = $url . $filename . '?vkey=' . $key;
        }
        return [
            'code' => 0,
            'msg' => 'success',
            'url' => $video_url
        ];
        
        try {
            
    
        } catch (\Exception $e) {
            return [
                'code' => 1,
                'msg' => 'fail',
                'url' => $url
            ];
        }
    }
    
    public function get_signature()
    {
        $jsurl = 'https://s3.pstatp.com/toutiao/resource/ntoutiao_web/page/profile/index_8f8a2fb.js';
        $js = Common::curl($jsurl);
        $effect_js = str_split("Function");
        $js = 'var navigator = {};\
           navigator["userAgent"] = "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.87 Safari/537.36";\
        ' .  "Function" . $effect_js[3] .
                "Function" + $effect_js[4] .
                ";function result(){ return TAC.sign(0);} result();";
        
        echo $js;
    }
}