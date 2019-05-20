<?php
namespace app\home\controller;

use think\facade\Request;
use app\common\model\User;
use app\common\model\ArticleModel;
use think\facade\Env;
use GuzzleHttp\json_decode;
use app\common\model\Common;

class Article extends Base
{
    protected function initialize(){
        $this->user_id = session('user.id');
        $action_name = request()->action();
        if (empty($this->user_id)) {
            // 验证签名
            if (!empty(input('sign'))) {
                $this->checkSign();
        
                // 验证令牌
                $this->checkToken();
            }
        }
        
        if (empty($this->user_id) && strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            $user = \app\common\model\User::wxOauthUser();
            if ($user === false) throw new \Exception();

            $this->user_id = $user['id'];
            $this->openid = $user['openid'];

            session('user',
                [
                    'id' => $user['id'],
                    'openid' => $user['openid'],
                    'unionid' => $user['unionid']
                ]
            );
        }
        
        $this->openid = session('user.openid');
        Env::set('ME', $this->user_id);
    }
    
    public function detail()
    {
        $id = Request::param('id', 0);

        $info = db('Article')
            ->field('id, cate_id, title, author, type, describe, content, add_time, link, info, level')
            ->where('status', 1)
            ->where('id = ' . $id)
            ->find();
        
        if (!$info) {
            return $this->response([], '文章不存在！', 40001);
        }
        
        db('Article')->where('id = '. $id)->setInc('read_num');

//        if ($info['level'] > 0 && $info['level'] > User::level($this->user_id)) { // 校验权限
//            return $this->response([], '权限不足！', 40002);
//        }

        // 是否有外链
        if ($info['link']) {
            header('Location: '. $info['link']);
        }
        
        /*
        // 文章设置
        $article_config = db('siteinfo')->field('value')->where('key', 'article_setting')->find();
        $article_config = unserialize($article_config['value']);
        $article_config['start_time'] = strtotime($article_config['start_time']);
        $article_config['end_time'] = strtotime($article_config['end_time']);
        if ($article_config['start_time'] < request()->time() && $article_config['end_time'] > request()->time()) {
            // 打开模仿微信页面
            //return redirect('Article/wx_detail', ['id' => $id]);
        }
        */
        
        // 用户信息
        if ($info['cate_id'] > 0) {
            $info['user'] = ['nickname' => '微选生活'];
        } else {
            $info['user'] = db('user')->field('nickname')->where('id', $info['author'])->find();
        }
	$info['user']['invite_code'] = db('user_promotion')->where('user_id', $this->user_id)->value('invite_code');
        
        if ($info['type'] == 1) {
            $info['img_list'] = json_decode($info['info'], true)['img_list'];
            
            // 文章长度
            $info['article_length'] = Common::sstrlen($info['content']);
            $info['read_time'] = ceil($info['article_length'] / 30);
        } else {
            $video_length = json_decode($info['info'], true)['video_length'];
            $video_length = explode(':', $video_length);
            
            $info['read_time'] = $video_length[2];
            if (isset($video_length[0]) && $video_length[0]) {
                $info['read_time'] += 3600 * $video_length[0];
            }
            if (isset($video_length[1]) && $video_length[1]) {
                $info['read_time'] += 60 * $video_length[1];
            }
        }

        // 时间
        $info['add_time'] = date('Y-m-d', $info['add_time']);
        
        $info['content'] = htmlspecialchars_decode(str_replace('&#x3D;', '=', $info['content']));

        // 其他文章
        $orther_article = db('Article')
        ->field('id, title, author, info, read_num')
        ->where('status = 1 and is_recommend = 1 and id != '. $id)
        ->select();
        // 文章用户信息
        $user_ids = [];
        $user_data = [];
        foreach ($orther_article as &$val) {
            if (!in_array($val['author'], $user_ids)) {
                $user_ids[] = $val['author'];
            }
        
            $val['info'] = json_decode($val['info'], true);
        }
        if ($user_ids) {
            $user_data = db('user')->where([['id', 'in', $user_ids]])->column('nickname, avator', 'id');
        }
        
        $article_collect = 0;
        $info['in_station_read_status'] = 1;
        if ($this->user_id) {
            // 是否已经阅读
            $r_map = [
                ['user_id', '=', $this->user_id],
                ['article_id', '=', $id],
                ['type', '=', 0]
            ];
            $rs = db('article_user_relation')->where($r_map)->find();
            if (!$rs) {
                db('article_user_relation')->insert([
                    'user_id' => $this->user_id,
                    'article_id' => $id,
                    'type' => 0,
                    'add_time' => request()->time()
                ]);
                $info['in_station_read_status'] = 0;
            } else {
                $info['in_station_read_status'] = $rs['status'];
            }
            
            // 是否收藏
            $c_map = [
                ['user_id', '=', $this->user_id],
                ['article_id', '=', $id],
                ['type', '=', 1]
            ];
            $article_collect = db('article_user_relation')->where($c_map)->count();
        }

        //print_r($info);exit;
        // 微信JS-SDK
        //$wx_config = D('Mpwx')->wx_config();
        
        $assign_data = [
            'article_collect' => $article_collect,
            'user_data' => $user_data,
            'orther_article' => $orther_article,
            'info' => $info,
            'user_id' => $this->user_id
        ];

        if ($info['level'] > 0 && $info['level'] > User::level($this->user_id)) { // 升级
            $assign_data['level_promote'] = $info['level'];
        }

        return view('', $assign_data);
    }
    
    public function share_detail()
    {
        $id = Request::param('id', 0);
        
        $info = db('Article')
        ->field('id, cate_id, title, author, type, describe, content, add_time, link, info, level')
        ->where('status', 1)
        ->where('id = '. $id)
        ->find();
        
        if (!$info) {
            return $this->response([], '文章不存在！', 40001);
        }
        
        db('Article')->where('id = '. $id)->setInc('read_num');

//        if ($info['level'] > 0 && $info['level'] > User::level($this->user_id)) { // 校验权限
//            return $this->response([], '权限不足！', 40002);
//        }

        if ($this->user_id) {
            // 分享获得
            $article_pid = Request::param('article_pid', 0);
            if ($article_pid) {
                // 同一篇文章，同一个被分享者，阅读多次也只给分享者奖励一次
                $rs = db('article_user_relation')->where([
                    ['user_id', '=', $this->user_id],
                    ['type', '=', 2],
                    ['status', '=', $article_pid],
                    ['article_id', '=', $id]
                ])->find();
                if (!$rs) {
                    $todaytime = strtotime(date('Y-m-d'));
                    $share_count = db('article_user_relation')
                    ->where([
                        ['status', '=', $article_pid],
                        ['type', '=', '2'],
                        ['add_time', 'BETWEEN', [$todaytime, $todaytime + 86400]]
                    ])->count();
                    // 每天最高获得200mb
                    if ($share_count < 10) {
                        $flow_id = User::mb($article_pid, 20, 'share_read_award',  $this->user_id, '分享文章');
        
                        db('article_user_relation')->insert([
                        'user_id' => $this->user_id,
                        'article_id' => $id,
                        'type' => 2,
                        'add_time' => request()->time(),
                        'status' => $article_pid
                        ]);
                    }
                }
            }
        }
        
        // 是否有外链
        if ($info['link']) {
            header('Location: '. $info['link']);
        }
        
        /*
         // 文章设置
         $article_config = db('siteinfo')->field('value')->where('key', 'article_setting')->find();
         $article_config = unserialize($article_config['value']);
         $article_config['start_time'] = strtotime($article_config['start_time']);
         $article_config['end_time'] = strtotime($article_config['end_time']);
         if ($article_config['start_time'] < request()->time() && $article_config['end_time'] > request()->time()) {
         // 打开模仿微信页面
         //return redirect('Article/wx_detail', ['id' => $id]);
         }
         */
        
        // 用户信息
        if ($info['cate_id'] > 0) {
            $info['user'] = ['nickname' => '微选生活'];
        } else {
            $info['user'] = db('user')->field('nickname')->where('id', $info['author'])->find();
        }

        if ($info['type'] == 1) {
            $info['img_list'] = json_decode($info['info'], true)['img_list'];
        }
        
        // 时间
        $info['add_time'] = date('Y-m-d', $info['add_time']);
        
        $info['content'] = htmlspecialchars_decode(str_replace('&#x3D;', '=', $info['content']));
        
        // 其他文章
        $orther_article = db('Article')
        ->field('id, title, author, info, read_num')
        ->where('status = 1 and id <> '. $id)
        ->limit(8)
        ->select();
        // 文章用户信息
        $user_ids = [];
        $user_data = [];
        foreach ($orther_article as &$val) {
            if (!in_array($val['author'], $user_ids)) {
                $user_ids[] = $val['author'];
            }
        
            $val['info'] = json_decode($val['info'], true);
        }
        if ($user_ids) {
            $user_data = db('user')->where([['id', 'in', $user_ids]])->column('nickname, avator', 'id');
        }
        
        // 潜在市场
        $invite_code = input('invite_code');
        if ($invite_code && $this->user_id) {
            Common::potential_market($this->user_id, $invite_code);
        }
        //print_r($orther_article);exit;
        // 微信JS-SDK
        //$wx_config = D('Mpwx')->wx_config();
        
        $assign_data = [
            'user_data' => $user_data,
            'orther_article' => $orther_article,
            'info' => $info,
            'user_id' => $this->user_id
        ];

        if ($info['level'] > 0 && $info['level'] > User::level($this->user_id)) { // 升级
            $assign_data['level_promote'] = $info['level'];
        }

        return view('', $assign_data);
    }
    
    public function wx_detail()
    {
        $id = Request::param('id', 0);

        $map = [
            ['status', '=', 1],
            ['id', '=', $id]
        ];
        $info = db('Article')->field('id, cate_id, title, author, content, add_time, link, read_num, level')->where($map)->find();
        if (!$info) {
            return $this->response([], '文章不存在！', 40001);
        }

        db('Article')->where('id = ' . $id)->setInc('read_num');

//        if ($info['level'] > 0 && $info['level'] > User::level($this->user_id)) { // 校验权限
//            return $this->response([], '权限不足！', 40002);
//        }

        // 用户信息
        if ($info['cate_id'] > 0) {
            $info['user'] = ['nickname' => '微选生活'];
        } else {
            $info['user'] = db('user')->field('nickname')->where('id', $info['author'])->find();
        }

        // 时间
        $info['add_time'] = date('Y-m-d', $info['add_time']);
        
        // 文章设置
        $article_config = db('siteinfo')->field('value')->where('key', 'article_setting')->find();
        $article_config = unserialize($article_config['value']);
        
        $info['content'] = htmlspecialchars_decode(str_replace('&#x3D;', '=', $info['content']));
        
        // 广告
        // $where = D('Ad')->get_valid_filter($this->user_wxid, '');
        // $ad = M('ad')->where($where)->field('ad_id,ad_pic AS pic,ad_link AS link')->find();
        $ad = [];
        
        $assign_data = [
            'article_config' => $article_config,
            'info' => $info,
            'ad' => $ad
        ];

        if ($info['level'] > 0 && $info['level'] > User::level($this->user_id)) { // 升级
            $assign_data['level_promote'] = $info['level'];
        }

        return view('', $assign_data);
    }
    
    public function collect(){
        if (Request::isAjax()) {
            $id = Request::get('id', 0);
            if (!$id) {
                return $this->response([], '参数错误', 40001);
            }

            // 已收藏
            $map = [
                'user_id' => $this->user_id,
                'article_id' => $id,
                'type' => 1
            ];
            $rs = db('article_user_relation')->where($map)->find();
            if ($rs) {
                return $this->response([], '已收藏过', 40002);
            }
    
            // 添加收藏
            $data = [
                'user_id' => $this->user_id,
                'article_id' => $id,
                'type' => 1,
                'add_time' => request()->time()
            ];
            $rs = db('article_user_relation')->insert($data);
            if ($rs) {
                return $this->response([], '收藏成功！');
            } else {
                return $this->response([], '操作失败，请重试', 40003);
            }
        }
    
        $map = [
            'user_id' => $this->user_id,
            'type' => 1
        ];
        $article_arr = db('article_user_relation')->where($map)->getField('article_id', true);
    
        $map = [
            'id' => ['in', $article_arr]
        ];
        $article_collect = ArticleModel::field('id, title, author, info, read_num')
        ->where($map)
        ->select();
    
        // 文章用户信息
        $user_ids = [];
        $user_data = [];
        foreach ($article_collect as &$val) {
            if (!in_array($val['author'], $user_ids)) {
                $user_ids = $val['author'];
            }
    
            $val['img_list'] = json_decode($val['img_list'], true);
        }
    
        $assign_data = [
            'article_collect' => $article_collect,
            'user_data' => $user_data
        ];
        return view('', $assign_data);
    }
    
    // 站内阅读
    public function in_station_read(){
        $id = Request::get('id', 0);
        if (!$id) {
            return $this->response([], '参数错误', 40001);
        }
    
        $map = [
            'user_id' => $this->user_id,
            'article_id' => $id,
            'type' => 0
        ];
        $rs = db('article_user_relation')->where($map)->find();
        // 没有阅读记录  || 获得过阅读奖励
        if (!$rs || $rs['status'] == 1) {
            return $this->response([], '参数错误', 40001);
        }
    
        // 文章长度
        $article_info = db('Article')->field('content')->where('id', $id)->find();
        $article_info['article_length'] = Common::sstrlen($article_info['content']);
        $article_info['read_time'] = ceil($article_info['article_length'] / 30);

        if ($rs['add_time'] + $article_info['read_time'] > request()->time()) {
            return $this->response([], '阅读时间不足', 40003);
        }
    
        // 获得阅读奖励
        $rs = db('article_user_relation')->where('id', $rs['id'])->update(['status' => 1]);
        if ($rs) {
            $flow_id = User::mb($this->user_id, 10, 'read_award',  $id, '阅读奖励');
        }
    
        return $this->response(['integral_award' => 10]);
    }
}