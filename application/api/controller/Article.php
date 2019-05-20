<?php
namespace app\api\controller;

use think\facade\Request;
use extend\Ddk;
use app\common\model\ArticleModel;
use think\Loader;

class Article extends Base
{
    protected function initialize(){
        $token = input('token');
        if ($token) {
            // 验证令牌
            $this->checkToken();
        }
    }
    
    // 文章列表
    public function index()
    {
        /*
        //require env('VENDOR_PATH') .'ffmpeg-ffmpeg-php\src\FFMpeg\FFMpeg.php';
        
        Loader::addAutoLoadDir(env('VENDOR_PATH') .'ffmpeg-ffmpeg-php\src\FFMpeg');
        dump(env('VENDOR_PATH') .'ffmpeg-ffmpeg-php\src\FFMpeg');
        \FFMpeg\FFMpeg::create();
                
        //var_dump($ffmpeg);
        exit;
        */
        
        $next_id = Request::get('next_id', 0);
        $page_size = Request::get('page_size', 20);
        $cate_id = Request::get('cate_id', 0);
        $type = Request::get('type', 1);
        
        if ($page_size > 20) {
            $page_size = 20;
        }
        
        // 下一页
        $map = [];
        if ($next_id) {
            $map[] = ['id', '<', $next_id];
        }
        
        if ($type == 1) {
            // 文章
            $map[] = ['type', '=', 1];
            // 根据分类查询
            if ($cate_id != -1) {
                $cate_id = explode(',', $cate_id);
                if (count($cate_id) == 1) $map[] = ['cate_id', '=', $cate_id[0]];
                else $map[] = ['cate_id', 'in', $cate_id];
            }
            
            $ar_query = ArticleModel::where($map)->field('id, title, author, type, read_num, info, level');
            if ($cate_id != 1) {
                $ar_query->with(['author' => function ($query) {
                    $query->field('id, nickname');
                }]);
            }
        } else {
            // 视频
            $map[] = ['type', '=', 2];
            $ar_query = ArticleModel::where($map)->field('id, title, info, type');
        }
        
        $article_data = $ar_query->limit($page_size)->order('id', 'DESC')->select()->toArray();
        
        foreach ($article_data as &$val) {
            // 图片
            $val['info'] = json_decode($val['info'], true);
            if ($val['type'] == 1) {
                foreach ($val['info']['img_list'] as &$img) {
                    if ($img && strpos($img, 'http://') === false && strpos($img, 'https://') === false) {
                        $img = config('site.url') . $img;
                    }
                }
                if (!isset($val['info']['show_type'])) {
                    $val['info']['show_type'] = mt_rand(1, 2);
                }
            }
            
            if (!isset($val['author']['nickname'])) {
                $val['author'] = [
                    'id' => '0',
                    'nickname' => '微选生活'
                ];
            }
        }

        $next_id = count($article_data) >= $page_size ? end($article_data)['id'] : 0;

        return $this->response(['article_data' => $article_data, 'next_id' => $next_id]);
    }
    
    // 查询商品
    public function search()
    {
        
    }

    public function business()
    {
        return $this->response([
            'category' => [
                ['id' => 2, 'title' => '会员必看'],
                ['id' => 3, 'title' => '高手进阶']
            ]
        ]);
    }
}
