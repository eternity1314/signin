<?php
namespace app\api\controller;

use think\facade\Request;
use think\facade\Cache;
use app\common\model\GoodsModel;
use app\common\model\GoodsCateModel;
use app\common\model\User;
use app\common\model\Order;
use app\common\model\Common;
use think\Db;

class Goods extends Base
{
    protected function initialize()
    {
        $action_name = request()->action();
        $token = input('token');
        if (empty($token)) $token = request()->header('token');
        // || ($action_name !== 'like' && $action_name !== 'search_pdd')

        $no_login_action = ['like', 'search_pdd', 'search_local_pdd', 'pdd_cate', 'banner', 'channel', 'channel_goods', 'theme', 'theme_goods', 'cate_jd', 'search_local_jd', 'guide_nav', 'free_order_goods', 'good_content'];
        if ($token || !in_array($action_name, $no_login_action)) {
            $this->checkToken();
        }
    }
    
    public function pdd_cate()
    {
        $cate = Db::name('goods_pdd_cate')->field('name, cate_id, img')->order('sort', 'ASC')->select();
        foreach ($cate as &$val) {
            $val['img'] = config('site.url') . $val['img'];
        }

        return $this->response($cate);
    }
    
    public function search_local_pdd()
    {
        $sort_type = Request::get('sort_type', 0);
        $key = Request::get('key', '');
        $cat_id = Request::get('cat_id');
        
        $map = [];
        // 关键字查询
        if ($key) {
            $map[] = ['name', 'like', '%'. $key .'%'];
        }
        
        // 拼多多分类查询
        if ($cat_id) {
            $map[] = ['cid', '=', $cat_id];
        }
        
        // 排序方式
        $sort_way = 'DESC';
        switch ($sort_type) {
            case 1:
                $sort_type = 'sales_num';
                break;
            case 2:
                $sort_type = 'price';
                $sort_way  = 'ASC';
                break;
            case 3:
                $sort_type = 'price';
                break; 
            case 4:
                $sort_type = 'price * commission';
                $sort_way  = 'ASC';
                break;
            case 5:
                $sort_type = 'price * commission';
                break;
            case 6:
                $sort_type = 'coupon_price';
                $sort_way  = 'ASC';
                break;
            case 7:
                $sort_type = 'coupon_price';
                break;
            default:
                $sort_type = 'dsr';
                break;
        }
        
        $page = Request::get('page', 1);
        $page_size = Request::get('page_size', 60);
        
        $goods_data = Db::name('goods')->where($map)
        ->field('id, goods_id, name, img, price, commission, coupon_price, sales_num')
        ->orderRaw('if(coupon_price = 0, 1, 0),'. $sort_type .' '.$sort_way)
        ->limit($page_size)
        ->page($page)
        ->select();
        //echo Db::name('goods')->getLastSql();
        // 会员等级
        /*
        if ($this->user_id) {
            $user_level = Db::name('user_level')
            ->where([
                ['user_id', '=', $this->user_id],
                ['expier_time', '>', request()->time()]
            ])->value('level');
        }
        if (!isset($user_level) || !$user_level) {
            $user_level = 2;
        }
        */
        $user_level = 3;
        
        // 返佣比例
        $good_commission_data = Db::name('siteinfo')->where('key', 'good_commission')->value('value');
        $good_commission_data = json_decode($good_commission_data, true);
	
        foreach ($goods_data as &$val) {
            // 佣金计算
            $val['commission'] = sprintf('%.2f', ($val['price'] * $val['commission'] * $good_commission_data['show_commission_scale'] * $good_commission_data['commission_rule'][$user_level]['self_buying']) / 100000);
            $val['price'] = '￥' . sprintf('%.2f', $val['price'] / 100);
            $val['coupon_price'] = intval($val['coupon_price'] / 100);
        }
        
        return $this->response(['goods_data' => $goods_data]);
    }
    
    public function search_pdd(){
        $keyword = Request::get('keyword');
        $page = Request::get('page');
        $sort_type = Request::get('sort_type', 0);
        $cate_id = Request::get('cate_id', 0);
        
        if (!is_numeric($page) || $page < 1) {
            $page = 1;
        }
        
        $data = [
            'type' => 'pdd.ddk.goods.search',
            'sort_type' => $sort_type,
            'page' => $page,
            'page_size' => 60,
            'keyword' => trim($keyword),
            'category_id' => $cate_id
             //'with_coupon' => 'true'
        ];

        $Ddk = new \ddk();
        $res = $Ddk->get_goods($data);

        if (isset($res['error_response']) || !$res['goods_search_response']['goods_list']) {
            return $this->response(['goods_data' => []]);
        }
        
        // 返佣比例
        $good_commission_data = Db::name('siteinfo')->where('key', 'good_commission')->value('value');
        $good_commission_data = json_decode($good_commission_data, true);
        
        // 会员等级
        /*
        if ($this->user_id) {
            $user_level = Db::name('user_level')
            ->where([
                ['user_id', '=', $this->user_id],
                ['expier_time', '>', request()->time()]
            ])->value('level');
        }
        if (!isset($user_level) || !$user_level) {
            $user_level = 2;
        }
        */
        $user_level = 3;
        
        $goods_data = [];
        foreach ($res['goods_search_response']['goods_list'] as $val) {
            // 佣金计算
            $val['commission'] = sprintf('%.2f', (($val['min_group_price'] - $val['coupon_discount']) * $val['promotion_rate'] * $good_commission_data['show_commission_scale'] * $good_commission_data['commission_rule'][$user_level]['self_buying']) / 100000);
            
            $goods_data[] = [
                'id' => 0,
                'goods_id' => $val['goods_id'],
                'name' => $val['goods_name'],
                'img' => $val['goods_thumbnail_url'],
                'price' => '￥' . sprintf('%.2f', ($val['min_group_price'] - $val['coupon_discount']) / 100),
                'commission' => $val['commission'],
                'sales_num' => $val['sold_quantity'],
                'coupon_price' => intval($val['coupon_discount'] / 100)
            ];
        }
        
        return $this->response(['goods_data' => $goods_data]);
    }
    
    // 猜你喜欢
    public function like()
    {
        $page_size = Request::get('page_size', 9);
        $has_col = Request::get('has_col', 0);
        $user_id = $this->user_id ? $this->user_id : 0;
        
        if ($page_size > 30) {
            $page_size = 30;
        }
        
        $Goods = new GoodsModel();
        
        $like_goods = $Goods->like_goods($user_id, $page_size);
        if ($has_col) {
            $search = GoodsModel::comment_search();
            array_splice($like_goods, 4, 0, [['is_cate' => 1, 'keywords' => $search['hot']]]);
        }
        
        return $this->response(['like_data' => $like_goods]);
    }
    
    // 商品详情
    public function detail()
    {
        $id = Request::get('id', 0);
        $goods_id = Request::get('goods_id', 0);
        //$user_info = User::where('user_id', $this->user_id)->field('balance')->find()
        if ($goods_id) {
            $map = [
                ['goods_id', '=', $goods_id],
            ];
        } else {
            $map = [
                ['id', '=', $id],
            ];
        }
        // 商品详情
        $info = GoodsModel::field('id, goods_id, name, org_price, coupon_price, commission, price, img, cid, sales_num, good_quan_link, good_quan_link_h5, tao_pass, type, coupon_time')
        ->where($map)
        ->find();
        if ($info) {
            $info = $info->toArray();
        }

        if (!$info && $goods_id) {
            $Ddk = new \ddk();
            $data = [
                'type' => 'pdd.ddk.goods.detail',
                'goods_id_list' => '['. $goods_id .']',
            ];
            $res = $Ddk->get_goods($data);
            if (isset($res['error_response']) || !$res['goods_detail_response']['goods_details']) {
                return $this->response([], '商品优惠券已抢光', 40001);
            }

            $info = [
                'goods_id' => ''.$res['goods_detail_response']['goods_details'][0]['goods_id'],
                'name' => $res['goods_detail_response']['goods_details'][0]['goods_name'],
                'org_price' => $res['goods_detail_response']['goods_details'][0]['min_normal_price'],
                'coupon_price' => $res['goods_detail_response']['goods_details'][0]['coupon_discount'],
                'coupon_time' => $res['goods_detail_response']['goods_details'][0]['coupon_end_time'] ?: 0,
                'commission' => $res['goods_detail_response']['goods_details'][0]['promotion_rate'],
                'price' => $res['goods_detail_response']['goods_details'][0]['min_group_price'] - $res['goods_detail_response']['goods_details'][0]['coupon_discount'],
                'img' => $res['goods_detail_response']['goods_details'][0]['goods_thumbnail_url'],
                'sales_num' => $res['goods_detail_response']['goods_details'][0]['sold_quantity'],
                'cid' => $res['goods_detail_response']['goods_details'][0]['category_id'] ?: 0,
                'type' => 1
            ];
            $add_data = [
                'd_name' => $res['goods_detail_response']['goods_details'][0]['mall_name'],
                'cut_img' => $res['goods_detail_response']['goods_details'][0]['goods_image_url'],
                'dsr' => $res['goods_detail_response']['goods_details'][0]['goods_eval_score'] ?: 4,
                'coupon_surplus_quantity' => $res['goods_detail_response']['goods_details'][0]['coupon_remain_quantity'] ?: 0,
                'coupon_total_quantity' => $res['goods_detail_response']['goods_details'][0]['coupon_total_quantity'] ?: 0,
                'coupon_min_order_amount' => $res['goods_detail_response']['goods_details'][0]['coupon_min_order_amount'] ?: 0,
            ];
            
            $res = GoodsModel::create(array_merge($info, $add_data));
            //$res = db('goods')->insert(array_merge($info, $add_data));
            if (!$res) {
                return $this->response([], '商品不存在', 40001);
            }
            $info['id'] = $res->id;
        }

        // 返佣比例
        $good_commission_data = Db::name('siteinfo')->where('key', 'good_commission')->value('value');
        $good_commission_data = json_decode($good_commission_data, true);
        // 超级会员佣金
        $info['commission_super'] = sprintf('%.2f', ($info['price'] * $info['commission'] * $good_commission_data['show_commission_scale'] * $good_commission_data['commission_rule']['2']['self_buying']) / 100000);
        // vip佣金
        $info['commission_vip'] = sprintf('%.2f', ($info['price'] * $info['commission'] * $good_commission_data['show_commission_scale'] * $good_commission_data['commission_rule']['3']['self_buying']) / 100000);
        // 合伙人佣金
        $info['commission_parner'] = sprintf('%.2f', ($info['price'] * $info['commission'] * $good_commission_data['show_commission_scale'] * $good_commission_data['commission_rule']['4']['self_buying']) / 100000);
        
        // 商品市场价
        $info['org_price'] = sprintf('%.2f', $info['org_price'] / 100);
        $info['price'] = sprintf('%.2f', $info['price'] / 100);
        $info['coupon_price'] = intval($info['coupon_price'] / 100);
        $info['coupon_time'] = date('Y-m-d', $info['coupon_time']);
        
        if (strpos($info['img'], 'http://') === false && strpos($info['img'], 'https://') === false) {
            $info['img'] = 'https:' . $info['img'];
        }

        switch ($info['type']) {
            case 1 :
                // 拼多多
                // 生成购买链接
                $ddk = new \ddk();
                
                // 获取用户推广位 p_id
                $p_id = db('user_promotion')->where('user_id', $this->user_id)->value('p_id');
                if (!$p_id) {
                    $data = [
                        'type' => 'pdd.ddk.goods.pid.generate',
                        'number' => '1'
                    ];
                    $p_id = $ddk->get_goods($data)['p_id_generate_response']['p_id_list'][0]['p_id'];
                    
                    db('user_promotion')->where('user_id', $this->user_id)->update(['p_id' => $p_id]);
                }

                // 必要参数
                $data = [
                    'type' => 'pdd.ddk.goods.promotion.url.generate',
                    'p_id' => $p_id,
                    'goods_id_list' => '['. $info['goods_id'] .']',
                    'generate_short_url' => 'true',
                    'multi_group' => 'true'
                ];
                $rs = $ddk->get_goods($data);
                if (isset($rs['error_response']) || !isset($rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0])) {
                    return $this->response([], '商品不存在', 40001);
                }
                $info['good_quan_link'] = [
                    'app' => $rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0]['mobile_short_url'],
                    'h5'  => $rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0]['short_url'],
                    'pdd_url' => '',
                    'app_long_url' => $rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0]['url'],
                    'pdd_active_url' => str_replace('https', 'pinduoduo', $rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0]['url']),
                    'we_app_web_view_short_url' => $rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0]['we_app_web_view_short_url'],
                    'we_app_web_view_url' => $rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0]['we_app_web_view_url']
                ];
                // 拼接跳转拼多多App链接
                $pdd_para = substr($rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0]['mobile_url'], strpos($rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0]['mobile_url'], '%3F') + 3);

                if (substr($pdd_para, -15) === 'duoduo_type%3D3') {
                    $info['good_quan_link']['pdd_url'] = 'pinduoduo://com.xunmeng.pinduoduo/duo_coupon_landing.html?' . $pdd_para;
                }
                break;
            default:
                // 淘客
                if (empty($info['tao_pass'])) {
                    // 生成淘口令
                    $tbk = new \Tbk();
                    // 必要参数
                    $data = [
                        'url' => $info['good_quan_link'],
                        'text' => $info['name'],
                        'logo' => $info['img']
                    ];
                    $rs = $tbk->get_tao_pass($data);
                    // 保存淘口令
                    if ($rs['tbk_tpwd_create_response']) {
                        $info['tao_pass'] = $rs['tbk_tpwd_create_response']['data']['model'];
                        GoodsModel::where('id', $info['id'])->update(['tao_pass' => $info['tao_pass']]);
                    }
                }
                break;
        }

        if (($this->app_token['core_version'] > 231 && $this->app_token['client_system'] == 'android') || ($this->app_token['core_version'] > 30 && $this->app_token['client_system'] == 'iOS')) {
            // 其他商品
            $orther_goods = Db::name('goods')
            ->field('id, goods_id, name, price, img, sales_num')
            ->where('cid', '=', $info['cid'])
            ->order('sales_num', 'desc')
            ->limit(12)
            ->select();
            foreach ($orther_goods as &$val) {
                $val['price'] = '￥' . sprintf('%.2f', $val['price'] / 100);
                if (strpos($val['img'], 'http://') === false && strpos($val['img'], 'https://') === false) {
                    $val['img'] = 'https:' . $val['img'];
                }
            }
            
            return $this->response([
                'result' => [
                    'info' => $info,
                    'orther_goods' => $orther_goods
                ]
            ]);
        } else {
            return $this->response($info);
        }
    }
    
    public function good_content(){
        $goods_id = Request::get('goods_id', 0);
        $type = Request::get('type', 1);
        
        if (!$goods_id) {
            $this->response([], '商品id不存在', 40001);
        }
        
        $cache_key = 'good_content';
        $cache_data = Cache::get($cache_key);
        if (isset($cache_data[$goods_id])) {
            return $this->response(['images' => $cache_data[$goods_id]]);
        }
        
        // 商品详情获取
        if ($type == 1) {
            $Ddk = new \ddk();
            $data = [
                'type' => 'pdd.ddk.goods.detail',
                'goods_id_list' => '['. $goods_id .']',
            ];
            $res = $Ddk->get_goods($data);
            
            $images = isset($res['goods_detail_response']['goods_details'][0]) ? $res['goods_detail_response']['goods_details'][0]['goods_gallery_urls'] : [];
        } else {
            $res = file_get_contents('https://hws.m.taobao.com/cache/mtop.wdetail.getItemDescx/4.1/?data={item_num_id:'. $goods_id .'}');
            $res = json_decode($res, true);
            
            $images = $res['data']['images'];
        }
        $cache_data[$goods_id] = $images;
        Cache::set($cache_key, $cache_data, 86400);
        
        return $this->response(['images' => $images]);
    }
    
    // 福利首页收入情况
    public function income(){
        $data = [];
        $today_time = Common::today_time();
        
        $forecast_income_cache_key = 'forecast_income_'. $this->user_id;
        $forecast_income = Cache::get($forecast_income_cache_key);
        if (isset($forecast_income['today_estimate_income'])) {
            if ($this->user_id == 2112) {
                $forecast_income['last_month_income'] += 22568.97;
                //$forecast_income['this_month_income'] += 22568.97;
            }
            
            return $this->response(['result' => $forecast_income]);
        }
        
        // 今日预估收入
        $order = db('order')
        ->field('user_id, directly_user_id, directly_supervisor_user_id, user_commission, directly_user_commission, directly_supervisor_user_commission')
        ->where([
            ['user_id|directly_user_id|directly_supervisor_user_id', '=', $this->user_id],
            ['add_time', 'BETWEEN', [$today_time, $today_time + 86400]],
            ['status', '<>', 3]
        ])->select();
        
        // 利润
        $data['today_estimate_income'] = 0;
        // 订单数
        $data['today_order'] = 0;
        foreach ($order as $val) {
            if ($this->user_id == $val['user_id']) {
                $data['today_estimate_income'] += $val['user_commission'];
            } elseif ($this->user_id == $val['directly_user_id']) {
                $data['today_estimate_income'] += $val['directly_user_commission'];
            } else {
                $data['today_estimate_income'] += $val['directly_supervisor_user_commission'];
            }
            
            $data['today_order']++;
        }
        // 晋升津贴
        $data['today_estimate_income'] += db('user_dredge_eduities')
        ->where([
            ['add_time', 'BETWEEN', [$today_time, $today_time + 86400]],
            ['pid', '=', $this->user_id]
        ])->sum('superior_award');
        
        // 本月收入
        $now_month_start = strtotime(date('Y-m-01'));
        $now_month_end = strtotime(date('Y-m-t 23:59:59'));
        $order = db('order')
        ->field('user_id, directly_user_id, directly_supervisor_user_id, user_commission, directly_user_commission, directly_supervisor_user_commission')
        ->where([
            ['user_id|directly_user_id|directly_supervisor_user_id', '=', $this->user_id],
            ['add_time', 'BETWEEN', [$now_month_start, $now_month_end]],
            ['status', '<>', 3]
        ])->select();
        
        $data['this_month_income'] = 0;
        $data['this_month_order'] = 0;
        foreach ($order as $val) {
            if ($this->user_id == $val['user_id']) {
                $data['this_month_income'] += $val['user_commission'];
            } elseif ($this->user_id == $val['directly_user_id']) {
                $data['this_month_income'] += $val['directly_user_commission'];
            } else {
                $data['this_month_income'] += $val['directly_supervisor_user_commission'];
            }
            
            $data['this_month_order']++;
        }
        // 晋升津贴
        $data['this_month_income'] += db('user_dredge_eduities')
        ->where([
            ['add_time', 'BETWEEN', [$now_month_start, $now_month_end]],
            ['pid', '=', $this->user_id]
        ])->sum('superior_award');
        
        // 上月收入
        $last_month_start = strtotime(date('Y-m-01', strtotime('-1 month', strtotime(date('Y-m')))));
        $last_month_end = strtotime(date('Y-m-t 23:59:59', strtotime('-1 month', strtotime(date('Y-m')))));
        $order = db('order')
        ->field('user_id, directly_user_id, directly_supervisor_user_id, user_commission, directly_user_commission, directly_supervisor_user_commission')
        ->where([
            ['user_id|directly_user_id|directly_supervisor_user_id', '=', $this->user_id],
            ['add_time', 'BETWEEN', [$last_month_start, $last_month_end]],
            ['status', '<>', 3]
        ])->select();

        $data['last_month_income'] = 0;
        $data['last_month_order'] = 0;
        foreach ($order as $val) {
            if ($this->user_id == $val['user_id']) {
                $data['last_month_income'] += $val['user_commission'];
            } elseif ($this->user_id == $val['directly_user_id']) {
                $data['last_month_income'] += $val['directly_user_commission'];
            } else {
                $data['last_month_income'] += $val['directly_supervisor_user_commission'];
            }
            
            $data['last_month_order']++;
        }
        // 晋升津贴
        $data['last_month_income'] += db('user_dredge_eduities')
        ->where([
            ['add_time', 'BETWEEN', [$last_month_start, $last_month_end]],
            ['pid', '=', $this->user_id]
        ])->sum('superior_award');
        
        // 昨日收入
        $order = db('order')
        ->field('user_id, directly_user_id, directly_supervisor_user_id, user_commission, directly_user_commission, directly_supervisor_user_commission')
        ->where([
            ['user_id|directly_user_id|directly_supervisor_user_id', '=', $this->user_id],
            ['add_time', 'BETWEEN', [$today_time - 86400, $today_time]],
            ['status', '<>', 3]
        ])->select();
        
        $data['yesterday_income'] = 0;
        $data['yesterday_order'] = 0;
        foreach ($order as $val) {
            if ($this->user_id == $val['user_id']) {
                $data['yesterday_income'] += $val['user_commission'];
            } elseif ($this->user_id == $val['directly_user_id']) {
                $data['yesterday_income'] += $val['directly_user_commission'];
            } else {
                $data['yesterday_income'] += $val['directly_supervisor_user_commission'];
            }
            
            $data['yesterday_order']++;
        }
        // 晋升津贴
        $data['yesterday_income'] += db('user_dredge_eduities')
        ->where([
            ['add_time', 'BETWEEN', [$today_time - 86400, $today_time]],
            ['pid', '=', $this->user_id]
        ])->sum('superior_award');
        
        $free_order = db()->query('select if(order_amount>500,500,order_amount) as free_amount_sum, add_time from sl_order where add_time > 1534163213 and `status` <> 3 and user_id in (select user_id from sl_user_tier where pid = '. $this->user_id .') GROUP BY user_id');
        if ($free_order) {
            foreach ($free_order as $val) {
                if ($val['add_time'] >= $today_time && $val['add_time'] < $today_time + 86400) {
                    // 今日
                    $data['today_estimate_income'] += $val['free_amount_sum'];
                } else if ($val['add_time'] >= $today_time - 86400 && $val['add_time'] < $today_time) {
                    // 昨日
                    $data['yesterday_income'] += $val['free_amount_sum'];
                }
                if ($val['add_time'] >= $last_month_start && $val['add_time'] < $last_month_end) {
                    // 上月
                    $data['last_month_income'] += $val['free_amount_sum'];
                } else if ($val['add_time'] >= $now_month_start && $val['add_time'] <= $now_month_end) {
                    // 本月
                    $data['this_month_income'] += $val['free_amount_sum'];
                }
            }
        }

        $data['today_estimate_income'] /= 100;
        $data['this_month_income'] /= 100;
        $data['last_month_income'] /= 100;
        $data['yesterday_income'] /= 100;
        
        Cache::set($forecast_income_cache_key, $data, $today_time + 86400 - request()->time());
        
        if ($this->user_id == 2112) {
            $data['last_month_income'] += 22568.97;
            //$data['this_month_income'] += 22568.97;//34568.48
        }
        
        return $this->response(['result' => $data]);
    }
    
    public function marketing()
    {
        $type = Request::get('type');
        $next_id = Request::get('next_id', 0);
        $page_size = Request::get('page_size', 20);
        
        $where = [];
        if ($next_id) {
            $where[] = ['id', '<', $next_id];
        }
        
        if ($type == 1) {
            $where[] = ['type', '=', 1];
        } else {
            $where[] = ['type', '=', 2];
        }

        // 邀请码
        $invite_code = db('user_promotion')->where('user_id', $this->user_id)->value('invite_code');
        // 短链
        $url = config('site.url') . url('home/user/invite_page') . '?invite_code='. $invite_code;
        $url = Common::short_url($url);

        // 营销数据
        $site_url = config('site.url');
        $data = db('goods_marketing')->where($where)->order('id', 'DESC')->limit($page_size)->select();
        $goods_id_arr = [];
        foreach ($data as &$val) {
            $val['info'] = json_decode($val['info'], true);
            $val['avatar'] = $site_url . $val['avatar'];
            $val['add_time'] = date('Y-m-d H:i', $val['add_time']);
            
            if ($type == 1) {
                $val['copy_hide'] = "\n\n点击". $url ."免费下载微选生活下单再返". $val['info']['commission'] ."元";
                foreach ($val['info']['goods'] as $good_item) {
                    if (!in_array($good_item['goods_id'], $goods_id_arr)) {
                        $goods_id_arr[] = $good_item['goods_id'];
                    }
                }
            }
        }

        // 是否下架
        $goods_id_arr = db('goods')->where([
            ['goods_id', 'in', $goods_id_arr]
        ])->column('goods_id');
        
        $count = count($data);
        if ($count > 0) {
            $next_id = $data[$count - 1]['id'];
        } else {
            $next_id = 0;
        }
        
        return $this->response(['result' => $data, 'goods_id_arr' => $goods_id_arr, 'next_id' => $next_id]);
    }
    
    public function hot_poster()
    {
        $id = Request::get('id');
        $user_id = $this->user_id;
        
        $where = [
            ['id', '=', $id],
            ['type', '=', 1]
        ];
        $data = db('goods_marketing')->where($where)->find();
        if (!$data) {
            return $this->response([], '数据不存在', 40001);
        }
        $data['info'] = json_decode($data['info'], true);

        $ddk = new \ddk();
        $file_poster = [];
        // 获取用户推广位 p_id
        $p_id = db('user_promotion')->where('user_id', $this->user_id)->value('p_id');
        if (!$p_id) {
            $data = [
                'type' => 'pdd.ddk.goods.pid.generate',
                'number' => '1'
            ];
            $p_id = $ddk->get_goods($data)['p_id_generate_response']['p_id_list'][0]['p_id'];
            if ($p_id) {
                $rs = db('user_promotion')->where('user_id', $this->user_id)->update(['p_id' => $p_id]);
                if (!$rs) {
                    return $this->response([], '数据保存失败', 40001);
                }
            } else {
                return $this->response([], '参数错误', 40001);
            }
        }
        
        // 生成海报
        foreach ($data['info']['goods'] as $val) {
            $item_poster = config('app.poster.root_path') . 'poster/goods/u_' . $this->user_id . '_pdd_g_'. $val['goods_id'] .'.jpg';
            if (!file_exists($item_poster)) {
                $val['coupon_price'] = intval($val['coupon_price'] / 100);
                
                // 必要参数
                $data = [
                    'type' => 'pdd.ddk.goods.promotion.url.generate',
                    'p_id' => $p_id,
                    'goods_id_list' => '['. $val['goods_id'] .']',
                    'generate_short_url' => 'true',
                    'multi_group' => 'true'
                ];
                $rs = $ddk->get_goods($data);
                if (isset($rs['error_response']) || !isset($rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0])) {
                    continue;
                }
                $val['good_quan_link'] = $rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0]['short_url'];
                $val['goods_type'] = 1;
                
                $Goods = new GoodsModel();
                $res = $Goods->poster_create($this->user_id, $val, $item_poster);
                
                if ($res !== 'SUCCESS') {
                    continue;
                }
            }
            $file_poster[] = config('site.url') . trim($item_poster, '.');
        }
        
        return $this->response(['result' => $file_poster]);
    }
    
    public function eduities_info()
    {
        $user_promotion_data = db('user_promotion')->where('user_id', $this->user_id)->find();
        
        if (!$user_promotion_data || $user_promotion_data['expire_time'] < request()->time()) {
            // 没开通或过期
            return $this->response();
        } else {
            // 没过期
            return $this->response(['result' => ['expire_time' => date('Y-m-d', $user_promotion_data['expire_time'])]]);
        }
    }
    
    public function poster()
    {
        $gid = request()->get('gid');

        // 商品信息
        $goods = GoodsModel::field('id, goods_id, img, name, sales_num, price, org_price, coupon_price')->where('id', $gid)->find();
        if (!$goods) {
            return $this->response([], '商品不存在', 40000);
        }
        $goods = $goods->toArray();
        
        $file_poster = config('app.poster.root_path') . 'poster/goods/u_' . $this->user_id . '_pdd_g_'. $goods['goods_id'] .'.jpg';
        if (!file_exists($file_poster)) {
            $goods['org_price'] = round($goods['org_price'] / 100, 2);
            $goods['price'] = round($goods['price'] / 100, 2);
            $goods['coupon_price'] = intval($goods['coupon_price'] / 100);
            
            $ddk = new \ddk();
            
            // 获取用户推广位 p_id
            $p_id = db('user_promotion')->where('user_id', $this->user_id)->value('p_id');
            if (!$p_id) {
                return $this->response([], '参数有误', 40001);
            }
            
            // 必要参数
            $data = [
                'type' => 'pdd.ddk.goods.promotion.url.generate',
                'p_id' => $p_id,
                'goods_id_list' => '['. $goods['goods_id'] .']',
                'generate_short_url' => 'true',
                'multi_group' => 'true'
            ];
            $rs = $ddk->get_goods($data);
            if (isset($rs['error_response']) || !isset($rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0])) {
                return $this->response([], '商品不存在', 40001);
            }
            $goods['good_quan_link'] = $rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0]['short_url'];
            $goods['goods_type'] = 1;
            
            $Goods = new GoodsModel();
            $res = $Goods->poster_create($this->user_id, $goods, $file_poster);
            
            if ($res !== 'SUCCESS') {
                return $this->response($res);
            }
        }
        
        return $this->response(['poster_path' => config('site.url') . trim($file_poster, '.')]);
    }
    
    public function theme()
    {
        $page = Request::get('page');
        $ddk = new \ddk();
        if (!is_numeric($page) || $page < 1) {
            return $this->response([], '参数错误！', 40000);
        }
        
        // 必要参数
        $data = [
            'type' => 'pdd.ddk.theme.list.get',
            'page_size' => 30,
            'page' => $page
        ];
        $theme_list_pdd = $ddk->get_goods($data);
        
        $theme_list = [
            'theme_list_get_response' => [
                'theme_list' => []
            ]
        ];
        foreach ($theme_list_pdd['theme_list_get_response']['theme_list'] as $val) {
            if ($val['image_url']) {
                $theme_list['theme_list_get_response']['theme_list'][] = $val;
            }
        }
        
        return $this->response(['result' => $theme_list]);
    }
    
    public function theme_goods()
    {
        $id = Request::get('id');
        $ddk = new \ddk();
        
        // 用户等级
        /*
        if ($this->user_id) {
            $user_level = Db::name('user_level')
            ->where([
                ['user_id', '=', $this->user_id],
                ['expier_time', '>', request()->time()]
            ])->value('level');
        }
        if (!isset($user_level) || !$user_level) {
            $user_level = 2;
        }
        */
        $user_level = 3;
        
        // 返佣比例
        $good_commission_data = Db::name('siteinfo')->where('key', 'good_commission')->value('value');
        $good_commission_data = json_decode($good_commission_data, true);
        
        // 必要参数
        $data = [
            'type' => 'pdd.ddk.theme.goods.search',
            'theme_id' => $id,
        ];
        $res = $ddk->get_goods($data);
        $goods_list = [];
        if (isset($res['theme_list_get_response'])) {
            foreach ($res['theme_list_get_response']['goods_list'] as $val) {
                if (isset($val['coupon_discount']) && $val['coupon_discount']) {
                    $val['price'] = $val['min_group_price'] - $val['coupon_discount'];
                } else {
                    $val['price'] = $val['min_group_price'];
                }
                
                $goods_list[] = [
                    'goods_id' => ''.$val['goods_id'],
                    'name' => $val['goods_name'],
                    'org_price' => sprintf('%.2f', $val['min_normal_price'] / 100),
                    'coupon_price' => $val['coupon_discount'] / 100,
                    'coupon_time' => $val['coupon_end_time'] ?: 0,
                    'commission' => sprintf('%.2f', ($val['price'] * $val['promotion_rate'] * $good_commission_data['show_commission_scale'] * $good_commission_data['commission_rule'][$user_level]['self_buying']) / 100000),
                    'price' => sprintf('%.2f', $val['price'] / 100),
                    'img' => $val['goods_thumbnail_url'],
                    'sales_num' => $val['sold_quantity'],
                    'type' => 1
                ];
            }
        }

        return $this->response(['result' => $goods_list]);
    }
    
    public function channel()
    {
        $channel_type = Request::get('channel_type');
        if (!in_array($channel_type, [0, 1, 2])) {
            return $this->response([], 'channel error', 40000);
        }
        
        $ddk = new \ddk();
        
        // 用户p_id
        $p_id = db('user_promotion')->where('user_id', $this->user_id)->value('p_id');
        if (!$p_id) {
            $data = [
                'type' => 'pdd.ddk.goods.pid.generate',
                'number' => '1'
            ];
            $p_id = $ddk->get_goods($data)['p_id_generate_response']['p_id_list'][0]['p_id'];
        
            db('user_promotion')->where('user_id', $this->user_id)->update(['p_id' => $p_id]);
        }
        
        // 必要参数
        $data = [
            'type' => 'pdd.ddk.cms.prom.url.generate',
            'p_id_list' => '["'. $p_id. '"]',
            'generate_mobile' => 'true',
            'we_app_web_view_short_url' => 'true',
            'we_app_web_wiew_url' => 'true',
            'channel_type' => $channel_type
        ];
        $res = $ddk->get_goods($data);
        
        return $this->response([
            'url_list' => [
                'url' => $res['cms_promotion_url_generate_response']['url_list'][0]['single_url_list']['url'],
                'mobile_url' => $res['cms_promotion_url_generate_response']['url_list'][0]['single_url_list']['mobile_url'],
                'we_app_web_view_url' => $res['cms_promotion_url_generate_response']['url_list'][0]['single_url_list']['we_app_web_view_url'],
                'we_app_web_view_short_url' => $res['cms_promotion_url_generate_response']['url_list'][0]['single_url_list']['we_app_web_view_short_url'] ?: ''
            ]
        ]);
    }
    
    public function channel_goods()
    {
        $ddk = new \ddk();
        // 网站域名
        $site_url = config('site.url');
        
        $channel_type = Request::get('channel_type');
        $offest = Request::get('offest');
        $limit = Request::get('limit');
        
        // 图片banner
        switch ($channel_type) {
            case 0:
                $top_banner = $site_url . '/static/goods/images/one-night-banner.png';
                break;
            case 1:
                $top_banner = $site_url . '/static/goods/images/hot-goods-banner.png';
                break;
            case 2:
                $top_banner = $site_url . '/static/goods/images/brand-command-banner.png';
                break;
            case 3:
                $top_banner = $site_url . '/static/goods/images/big-coupon-banner.png';
                break;
            case 4:
                $top_banner = $site_url . '/static/goods/images/high-commission-banner.jpg';
                break;
            case 5:
                $top_banner = $site_url . '/static/goods/images/hot-banner.jpg';
                break;
        }

        if (in_array($channel_type, [0, 1, 2])) {
            // 获取商品
            $data = [
                'type' => 'pdd.ddk.goods.recommend.get',
                'offset' => $offest,
                'limit' => $limit,
                'channel_type' => $channel_type
            ];
            $res = $ddk->get_goods($data);
            
            $user_level = 3;
            // 返佣比例
            $good_commission_data = Db::name('siteinfo')->where('key', 'good_commission')->value('value');
            $good_commission_data = json_decode($good_commission_data, true);
            
            $goods_data = [];
            foreach ($res['goods_basic_detail_response']['list'] as $val) {
                if (!isset($val['coupon_discount']) || $val['coupon_discount'] == 0) {
                    continue;
                }

                $val['commission'] = sprintf('%.2f', (($val['min_group_price'] - $val['coupon_discount']) * $val['promotion_rate'] * $good_commission_data['show_commission_scale'] * $good_commission_data['commission_rule'][$user_level]['self_buying']) / 100000);
            
                $goods_data[] = [
                    'id' => 0,
                    'goods_id' => $val['goods_id'],
                    'name' => $val['goods_name'],
                    'img' => $val['goods_thumbnail_url'],
                    'price' => '￥' . sprintf('%.2f', ($val['min_group_price'] - $val['coupon_discount']) / 100),
                    'commission' => $val['commission'],
                    'sales_num' => $val['sold_quantity'],
                    'coupon_price' => intval($val['coupon_discount'] / 100)
                ];
            }
            
            if ($channel_type == 1) {
                if ($offest == 0) {
                    // 推荐商品
                    $recommend_goods = Db::name('goods')->where('recommend', 1)
                        ->field('id, goods_id, name, img, price, commission, coupon_price, sales_num')
                        ->select();

                    foreach ($recommend_goods as &$val) {
                        $val['commission'] = sprintf('%.2f', ($val['price'] * $val['commission'] * $good_commission_data['show_commission_scale'] * $good_commission_data['commission_rule'][$user_level]['self_buying']) / 100000);
                        $val['price'] = '￥' . sprintf('%.2f', $val['price'] / 100);
                        $val['coupon_price'] = intval($val['coupon_price'] / 100);
                    }

                    $goods_data = array_merge($recommend_goods, $goods_data);
                }
            }
        } elseif (in_array($channel_type, [3, 4, 5])) {
            switch ($channel_type) {
                case 3:
                    // 大额优惠
                    $map[] = ['price', '>', 1000];
                    $where_str = 'coupon_price / price >= 0.3';
                    $sort_type = 'coupon_price';
                    $sort_way  = 'DESC';
                    break;
                case 4:
                    // 限时高拥
                    $map[] = ['price', '>', 1000];
                    $where_str = 'price * commission / 0.000664 / price >= 0.1';
                    $sort_type = 'price * commission / 0.000664 / price';
                    $sort_way  = 'DESC';
                    break;
                case 5:
                    // 爆款排行
                    $map[] = ['price', '>', 1000];
                    $where_str = 'sales_num > 1000';
                    $sort_type = 'sales_num';
                    $sort_way  = 'DESC';
                    break;
                default:
                    $where_str = '';
                    break;
            }

            $goods_data = Db::name('goods')->where($map)
            ->where($where_str)
            ->field('id, goods_id, name, img, price, commission, coupon_price, sales_num')
            ->orderRaw($sort_type .' '. $sort_way)
            ->limit($offest, $limit)
            ->select();
            
            $user_level = 3;
            // 返佣比例
            $good_commission_data = Db::name('siteinfo')->where('key', 'good_commission')->value('value');
            $good_commission_data = json_decode($good_commission_data, true);
            
            foreach ($goods_data as &$val) {
                // 佣金计算
                $val['commission'] = sprintf('%.2f', ($val['price'] * $val['commission'] * $good_commission_data['show_commission_scale'] * $good_commission_data['commission_rule'][$user_level]['self_buying']) / 100000);
                $val['price'] = '￥' . sprintf('%.2f', $val['price'] / 100);
                $val['coupon_price'] = intval($val['coupon_price'] / 100);
            }
        } else {
            return $this->response([], 'channel error', 40000);
        }
        
        return $this->response(['goods_data' => $goods_data, 'top_banner' => $top_banner]);
    }
    
    public function guide_nav()
    {
//        if (!empty($this->user_id)) {
//            $count = Db::name('order')->where([
//                ['user_id', '=', $this->user_id],
//                ['status', '<>', 3],
//            ])->count();
//
//            if ($count > 1) {
//                $name = '签到领现金';
//                $status = 1;
//            } elseif ($count > 0) {
//                $name = '领5元现金';
//                $status = 3;
//            } else {
//                $user = User::find($this->user_id);
//                if (date('Ymd', $user['add_time']) == Common::today_date()) { // 新用户当天
//                    $name = '签到领现金';
//                    $status = 1;
//                } else {
//                    $name = '免费领免单';
//                    $status = 2;
//                }
//            }
//        } else {
        $name = '签到领现金';
        $status = 1;
//        }

        if (empty($this->app_token)) $city = '';
        else $city = $this->app_token['city'];

        $data = [
            'name' => $name,
            'status' => $status,
            'link' => \app\common\model\Challenge::pull_redpack_room($city, true, true),
        ];

        return $this->response(['result' => $data]);

        $today_time = Common::today_time();
        $user_info = User::find($this->user_id, 'add_time');

        $rs = Db::name('order')->where([
            ['user_id', '=', $this->user_id],
            ['add_time', 'between', [$user_info['add_time'], $today_time + 86400]]
        ])->find();

        if ($rs) {
            // 当天24点前无购买过任何东西
            $data = [
                'name' => '免费领免单',
                'status' => 1
            ];
        }

        $rs = Db::name('order')->where([
            ['user_id', '=', $this->user_id],
        ])->find();

        if ($rs) {

        }
    }
    
    public function free_share_user()
    {
        $page = Request::get('page');
        $page_size = Request::get('page_size', '50');
        
        $user_data = db('goods_free_share_user')
        ->field('user_id, add_time')
        ->where([
            ['pid', '=', $this->user_id],
            ['add_time', '>', request()->time() - 172800]
        ])
        ->limit($page_size)
        ->page($page)
        ->order('add_time desc')
        ->select();
        $site_url = config('site.url');
        
        foreach ($user_data as &$val) {
            $val['user_info'] = db('user')->field('openid_app, nickname, avator, mobile')->where('id', '=', $val['user_id'])->find();
            $val['superior_nickname'] = db()->query('SELECT `nickname` FROM `sl_user` WHERE `id` = ( SELECT `pid` FROM `sl_user_tier` WHERE `user_id` = '. $val['user_id'] .' ) LIMIT 1');
            
            if ($val['user_info']['avator'] && strpos($val['user_info']['avator'], 'http://') === false && strpos($val['user_info']['avator'], 'https://') === false) {
                $val['user_info']['avator'] = $site_url . $val['user_info']['avator'];
            }
            
            if (!$val['superior_nickname']) {
                $val['superior_nickname'] = db()->query('SELECT `invite_code` FROM `sl_user_promotion` WHERE `user_id` = ( SELECT `pid` FROM `sl_user_tier` WHERE `user_id` = '. $val['user_id'] .' ) LIMIT 1');
                if ($val['superior_nickname']) {
                    $val['superior_nickname'] = $val['superior_nickname'][0]['invite_code'];
                }
            } else {
                $val['superior_nickname'] = $val['superior_nickname'][0]['nickname'];
            }
            $val['superior_nickname'] = $val['superior_nickname'] ?: '';
            $val['add_time'] = date('Y-m-d H:i:s', $val['add_time']);
        }
        
        return $this->response([
            'result' => [
                'user_data' => $user_data
            ]
        ]);
    }
    
    public function banner()
    {
        $limit = 6;
        $data = db('goods_banner')
            ->where([['status', '=', 1]])
            ->field('img,link')
            ->order('sort asc,id desc')
            ->limit($limit)
            ->select();
        return $this->response(['result' => $data, 'limit' => $limit]);
    }
    
    public function cate_jd()
    {
        $cate_data = Db::name('goods_jd_cate')->field('id as cate_id, name, img')->order('sort', 'ASC')->select();
        foreach ($cate_data as &$val) {
            $val['img'] = config('site.url') . $val['img'];
        }

        return $this->response($cate_data);
    }
    
    public function search_local_jd()
    {
        $sort_type = Request::get('sort_type', 0);
        $key = Request::get('key', '');
        $cat_id = Request::get('cat_id');
        $page = Request::get('page', 1);
        $page_size = Request::get('page_size', 60);

        $map = [];
        // 关键字查询
        if ($key) {
            $map[] = ['name', 'like', '%'. trim($key) .'%'];
        }
    
        // 京东分类查询
        if ($cat_id) {
            $jd_cat_ids = Db::name('goods_jd_cate')->where('id', '=', $cat_id)->value('jd_cat_ids');
    
            $map[] = ['cid', 'in', $jd_cat_ids];
        }
    
        // 排序方式
        $sort_way = 'DESC';
        switch ($sort_type) {
            case 6:
                $sort_type = 'in_order_count';
                break;
            case 9:
                $sort_type = 'price';
                $sort_way  = 'ASC';
                break;
            case 10:
                $sort_type = 'price';
                break;
            case 13:
                $sort_type = 'price * commission';
                $sort_way  = 'ASC';
                break;
            case 14:
                $sort_type = 'price * commission';
                break;
            case 7:
                $sort_type = 'coupon_price';
                $sort_way  = 'ASC';
                break;
            case 8:
                $sort_type = 'coupon_price';
                break;
            case 15:
                $sort_type = 'coupon_price';
                $map[] = ['price', '>', 1000];
                $map[] = ['coupon_price', '>=', Db::raw('price * 0.003')];
                break;
            default:
                $sort_type = 'id';
                $map[] = ['recommend', '=', 0];

                if ($page == 1) {
                    $recommend_goods = Db::name('goods_jd')->where('recommend', 1)
                        ->field('id, sku_id as goods_id, name, img, price, commission, coupon_price')
                        ->select();
                }
                break;
        }
    
        $goods_data = Db::name('goods_jd')->where($map)
        ->field('id, sku_id as goods_id, name, img, price, commission, coupon_price')
        ->orderRaw($sort_type .' '. $sort_way)
        ->limit($page_size)
        ->page($page)
        ->select();
        if (isset($recommend_goods)) {
            $goods_data = array_merge($recommend_goods, $goods_data);
        }
        
        // 返佣比例
        $good_commission_data = Db::name('siteinfo')->where('key', 'good_commission')->value('value');
        $good_commission_data = json_decode($good_commission_data, true);
        
        // 会员等级
        /*
        if ($this->user_id) {
            $user_level = Db::name('user_level')
            ->where([
                ['user_id', '=', $this->user_id],
                ['expier_time', '>', request()->time()]
            ])->value('level');
        }
        if (!isset($user_level) || !$user_level) {
            $user_level = 2;
        }
        */
        $user_level = 3;
        
        foreach ($goods_data as &$val) {
            // 佣金计算
            $val['commission'] = sprintf('%.2f', ($val['price'] * $val['commission'] * $good_commission_data['show_commission_scale'] * $good_commission_data['commission_rule'][$user_level]['self_buying']) / 100000);
            $val['price'] = '￥' . sprintf('%.2f', $val['price'] / 100);
            if (strpos($val['img'], 'http://') === false && strpos($val['img'], 'https://') === false) {
                $val['img'] = 'https://m.360buyimg.com/mobilecms/s750x750_' . $val['img'];
            }
        }
    
        return $this->response(['goods_data' => $goods_data]);
    }
    
    public function detail_jd()
    {
        $id = Request::get('id', 0);
        $Jos = new \jos();
    
        //$user_info = User::where('user_id', $this->user_id)->field('balance')->find()
        // 商品详情
        $map = [
            ['id', '=', $id],
        ];
        $info = Db::name('goods_jd')->field('id, sku_id, name, org_price, coupon_price, coupon_info, commission, price, img, cid, coupon_time')
        ->where($map)
        ->find();
        if (!$info) {
            return $this->response([], '商品优惠券已抢光', 40001);
        }
        
        // 返佣比例
        $good_commission_data = Db::name('siteinfo')->where('key', 'good_commission')->value('value');
        $good_commission_data = json_decode($good_commission_data, true);
        // 超级会员佣金
        $info['commission_super'] = sprintf('%.2f', ($info['price'] * $info['commission'] * $good_commission_data['show_commission_scale'] * $good_commission_data['commission_rule']['2']['self_buying']) / 100000);
        // vip佣金
        $info['commission_vip'] = sprintf('%.2f', ($info['price'] * $info['commission'] * $good_commission_data['show_commission_scale'] * $good_commission_data['commission_rule']['3']['self_buying']) / 100000);
        // 合伙人佣金
        $info['commission_parner'] = sprintf('%.2f', ($info['price'] * $info['commission'] * $good_commission_data['show_commission_scale'] * $good_commission_data['commission_rule']['4']['self_buying']) / 100000);
        
        // 商品市场价
        $info['org_price'] = sprintf('%.2f', $info['org_price'] / 100);
        $info['price'] = sprintf('%.2f', $info['price'] / 100);
        $info['coupon_time'] = date('Y-m-d', $info['coupon_time']);
        $info['coupon_info'] = json_decode($info['coupon_info'], true);

        if (strpos($info['coupon_info'][0]['link'], 'http://') === false && strpos($info['coupon_info'][0]['link'], 'https://') === false) {
            $info['coupon_info'][0]['link'] = 'https:' . $info['coupon_info'][0]['link'];
        }
    
        // 获取用户推广位 p_id
        $sub_unionid = db('user_promotion')->where('user_id', $this->user_id)->value('sub_unionid');
        if (!$sub_unionid) {
            // 生成购买链接
            $sub_unionid = $this->user_id . mt_rand(1000, 9999);
    
            db('user_promotion')->where('user_id', $this->user_id)->update(['sub_unionid' => $sub_unionid]);
        }
    
        // 必要参数
        $data = [
            'materialIds' => $info['sku_id'],
            'subUnionId' => $sub_unionid,
            'couponUrl' => urlencode($info['coupon_info'][0]['link']),
            'positionId' => 1465410820,
            //'pid' => '1000767623_1329957096_1465410820'
        ];
        $res = $Jos->get_json('jingdong.service.promotion.coupon.getCodeBySubUnionId', $data);
        $res = json_decode($res['jingdong_service_promotion_coupon_getCodeBySubUnionId_responce']['getcodebysubunionid_result'], true);
        if (!$res['urlList'][$info['coupon_info'][0]['link'] . ',' . $info['sku_id']]) {
            $data = [
                'proCont' => '1',
                'materialIds' => $info['sku_id'],
                'positionId' => 1465410820,
                'subUnionId' => $sub_unionid
            ];
            $good_res = $Jos->get_json('jingdong.service.promotion.wxsq.getCodeBySubUnionId', $data);
            $good_res = json_decode($good_res['jingdong_service_promotion_wxsq_getCodeBySubUnionId_responce']['getcodebysubunionid_result'], true);
            if (!$good_res['urlList'][$info['sku_id']]) {
                return $this->response([], '商品优惠券已抢光', 40001);
            }
            
            // 单品url
            $info['has_coupon'] = 0;
            $info['good_quan_link'] = [
                'url' => $good_res['urlList'][$info['sku_id']],
            ];
        } else {
            // 券商url
            $info['has_coupon'] = 1;
            $info['good_quan_link'] = [
                'url' => $res['urlList'][$info['coupon_info'][0]['link'] . ',' . $info['sku_id']] ?: '',
            ];
        }
        
        unset($info['coupon_info']);
        
        // 其他商品
        $orther_goods = Db::name('goods_jd')
        ->field('id, sku_id as goods_id, name, org_price, price, img')
        ->where('cid', '=', $info['cid'])
        ->order('in_order_count', 'desc')
        ->limit(12)
        ->select();
        foreach ($orther_goods as &$val) {
            $val['price'] = '￥' . sprintf('%.2f', $val['price'] / 100);
            $val['org_price'] = '￥' . sprintf('%.2f', $val['org_price'] / 100);
            if (strpos($val['img'], 'http://') === false && strpos($val['img'], 'https://') === false) {
                $val['img'] = 'https://m.360buyimg.com/mobilecms/s750x750_' . $val['img'];
            }
        }
        
        return $this->response([
            'result' => [
                'info' => $info,
                'orther_goods' => $orther_goods
            ]
        ]);
    }
    
    public function poster_jd()
    {
        $id = request()->get('id');
    
        // 商品信息
        $goods = db('goods_jd')->field('id, sku_id as goods_id, img, name, price, org_price, coupon_price, coupon_info')->where('id', $id)->find();
        if (!$goods) {
            return $this->response([], '商品不存在', 40000);
        }

        $file_poster = config('app.poster.root_path') . 'poster/goods/u_' . $this->user_id . '_jd_g_'. $goods['goods_id'] .'.jpg';
        //if (!file_exists($file_poster)) {
            $goods['org_price'] = round($goods['org_price'] / 100, 2);
            $goods['price'] = round($goods['price'] / 100, 2);
            $goods['coupon_info'] = json_decode($goods['coupon_info'], true);
            
            if (strpos($goods['coupon_info'][0]['link'], 'http://') === false && strpos($goods['coupon_info'][0]['link'], 'https://') === false) {
                $goods['coupon_info'][0]['link'] = 'https:' . $goods['coupon_info'][0]['link'];
            }
            
            if (strpos($goods['img'], 'http://') === false && strpos($goods['img'], 'https://') === false) {
                $goods['img'] = 'https://m.360buyimg.com/mobilecms/s750x750_' . $goods['img'];
            }

            $jos = new \jos();

            // 获取用户推广位 p_id
            $sub_unionid = db('user_promotion')->where('user_id', $this->user_id)->value('sub_unionid');
            if (!$sub_unionid) {
                // 生成购买链接
                $sub_unionid = $this->user_id . mt_rand(1000, 9999);
            
                db('user_promotion')->where('user_id', $this->user_id)->update(['sub_unionid' => $sub_unionid]);
            }

            // 必要参数
            $data = [
                'materialIds' => $goods['goods_id'],
                'subUnionId' => $sub_unionid,
                'couponUrl' => urlencode($goods['coupon_info'][0]['link']),
                'positionId' => 1465410820,
                //'pid' => '1000767623_1329957096_1465410820'
            ];
            $res = $jos->get_json('jingdong.service.promotion.coupon.getCodeBySubUnionId', $data);
            $res = json_decode($res['jingdong_service_promotion_coupon_getCodeBySubUnionId_responce']['getcodebysubunionid_result'], true);
            if (!$res['urlList'][$goods['coupon_info'][0]['link'] . ',' . $goods['goods_id']]) {
                $data = [
                    'proCont' => '1',
                    'materialIds' => $goods['goods_id'],
                    'positionId' => 1465410820,
                    'subUnionId' => $sub_unionid
                ];
                $good_res = $jos->get_json('jingdong.service.promotion.wxsq.getCodeBySubUnionId', $data);
                $good_res = json_decode($good_res['jingdong_service_promotion_wxsq_getCodeBySubUnionId_responce']['getcodebysubunionid_result'], true);
                if (!$good_res['urlList'][$goods['goods_id']]) {
                    return $this->response([], '商品优惠券已抢光', 40001);
                }
                
                // 单品url
                $goods['good_quan_link'] = $good_res['urlList'][$goods['goods_id']];
            } else {
            	$goods['good_quan_link'] = $res['urlList'][$goods['coupon_info'][0]['link'] . ',' . $goods['goods_id']];
            }
            $goods['goods_type'] = 2;
            
            $Goods = new GoodsModel();
            $res = $Goods->poster_create($this->user_id, $goods, $file_poster);
            
            if ($res !== 'SUCCESS') {
                return $this->response($res);
            }
        //}
echo '<img src="'.trim($file_poster, '.').' ?' . rand(1, 1000) .'">';exit;
        return $this->response(['poster_path' => config('site.url') . trim($file_poster, '.')]);
    }
    
    public function good_content_jd(){
        $goods_id = Request::get('goods_id', 0);
    
        if (!$goods_id) {
            $this->response([], '商品id不存在', 40001);
        }
    
        $cache_key = 'good_content';
        $cache_data = Cache::get($cache_key);
        if (isset($cache_data['jd_'.$goods_id])) {
            return $this->response(['images' => $cache_data['jd_'.$goods_id]]);
        }
    
        // 商品详情获取
        $Jos = new \jos();
        $res = $Jos->getGoodsDetail($goods_id);

        if ($res['code'] != 1) {
            $this->response([], '商品不存在', 40001);
        }
        
        $images = $res['data'];
        foreach ($images as &$val) {
            $val = 'http:'. $val;
        }
        
        $cache_data['jd_'.$goods_id] = $images;
        Cache::set($cache_key, $cache_data, 86400);
    
        return $this->response(['images' => $images]);
    }
}
