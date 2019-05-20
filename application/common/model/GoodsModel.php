<?php
namespace app\common\model;

use think\Db;
use think\Model;
use think\facade\Log;
use think\facade\Cache;

class GoodsModel extends Model
{
    protected $table = 'sl_goods';
    
    public static function pay_finish($data, $pay_id = 0)
    {
        if (!empty($data['attach'])) {
            $user_id = $data['attach'][0];
            $event = $data['attach'][3];
            parse_str($data['attach'][4], $option);
            $option['use_balance'] = true;
            $option['out_trade_no'] = $data['out_trade_no'];
            
            \think\facade\Env::set('ME', $user_id);
            $res = self::$event($user_id, $option);
    
            return $res;
        }
    }
    
    protected function base($query)
    {
        $query->where([
            ['status', '=', 1],
            //['coupon_time', '>', request()->time()]
        ]);
    }
    
    public static function config($key, $num = -1){
        $data = [
            'take_day_appoint' => [
                180 => 2.8, 
                90 => 3.8, 
                60 => 4.8, 
                21 => 6.8, 
                7 => 9.8
            ],
            'take_time_range' => [
                7 => [30, 10, 3],
                21 => [30, 10, 5],
                60 => [40, 20, 10],
                90 => [40, 20, 10],
                180 => [40, 30, 20]
            ],
            'return_commission' => 0.5,
            'return_commission_jd' => 0.5,
            'eduities_para' => [
                1 => [
                    'price' => 9900,
                    'increase_time' => 2592000,
                    'superior_award' => 3000,
                ],
                2 => [
                    'price' => 39900,
                    'increase_time' => 31536000,
                    'superior_award' => 12000,
                ]
            ]
        ];
        
        if ($num >= 0) {
            if (isset($data[$key][$num])) {
                return $data[$key][$num];
            } else {
                return ['errcode' => 40001];
            }
        } else {
            return $data[$key];
        }
    }
    
    // 用户足迹
    public function footprint($user_id, $data){
        $rs = Db::name('user_goods_footprint')
            ->where([
                ['type', '=', $data['type']],
                ['user_id', '=', $user_id],
                ['rid', '=', $data['rid']]
            ])->count();
            
        if ($rs == 0) {
            $data['user_id'] = $user_id;
            $data['add_time'] = request()->time();
            
            $rs = Db::name('user_goods_footprint')->insert($data);
        }
        
        return $rs;
    }
    
    // 推荐分类
    public static function comment_search(){
        $search = Db::name('siteinfo')->field('value')->where('key', 'good_search')->find();
        $search = json_decode($search['value'], true);
        
        return $search;
    }
    
    // 猜你喜欢
    public function like_goods($user_id, $num_max = 9){
        $sql = "SELECT rid, name FROM `sl_user_goods_footprint` AS t1 JOIN (
        SELECT ROUND(RAND() * (SELECT MAX(id) FROM `sl_user_goods_footprint` WHERE type = 2 AND user_id = $user_id)) AS id
        ) AS t2 WHERE t1.id >= t2.id AND user_id = $user_id ORDER BY t1.id ASC LIMIT 3;";
    
        $like_cate = DB::query($sql);
        if (!$like_cate) {
            $like_cate = [
                0 => [
                    'rid' => 1,
                    'name' => '送女友'
                ]
            ];
        }
        
        $count = count($like_cate);
        if ($count == 0) {
            return [];
        }
    
        switch ($count) {
            case 1: $num_item = $num_max;break;
            case 2: $num_item = floor($num_max / 2);$num_item2 = $num_max - $num_item;break;
            case 3: $num_item = floor($num_max / 3);$num_item2 = $num_max - ($num_item * 2);break;
            default:;break;
        }
    
        $data = [];
        foreach ($like_cate as $key=>$val) {
            $num_item = ($key == 1 && isset($num_item2) ? $num_item2 : $num_item);
            $pro_cate = Db::name('goods_cate_custom')->field('keywords')->where('id', $val['rid'])->find();
            $pro_cate = explode(',', $pro_cate['keywords']);
            
            foreach ($pro_cate as &$val) {
                $val = '%' . trim($val) . '%';
            }
            $map[] = ['name', 'like', $pro_cate, 'OR'];
            $map[] = ['status', '=', 1];
            //$map['quan_time'] = ['gt', date('Y-m-d H:i:s', strtotime("+1 day"))];
    
            $data[] = GoodsModel::where($map)
            ->field('id, name, price, img, commission, sales_num')
            ->order('sales_num', 'desc')
            ->limit($num_item)
            ->select()
            ->toarray();
        }
    
        switch ($count) {
            case 1: $data = $data[0];break;
            case 2: $data = array_merge($data[0], $data[1]);break;
            case 3: $data = array_merge($data[0], $data[1], $data[2]);break;
        }
        foreach ($data as &$val) {
            $val['commission'] = sprintf('%.2f', ($val['price'] * $val['commission'] * self::config('return_commission')) / 100000);
            $val['price'] = '￥' . sprintf('%.2f', $val['price'] / 100);
        }
    
        return $data;
    }
    
    public function check_take($uid, $day = 0){
        // 已参与过的白拿
        $take_count = Db::name('order_take')->where([
            ['user_id', '=', $uid],
            ['status', '<>', 2]
        ])->group('day')->column('count(id) AS count', 'day');
        $take_appoint = self::config('take_day_appoint', -1);
        $take_day = [];
    
        foreach ($take_appoint as $key=>$val) {
            if (isset($take_count[$key]) && $take_count[$key] > 2) {
                unset($take_appoint[$key]);
                continue;
            }
            $take_day[] = $key;
        }

        
        $return_data = [
            'take_day' => $take_day,
            'take_count' => $take_count,
            'take_day_appoint' => $take_appoint,
            'default_num' => 0
        ];
    
        return $return_data;
    }
    
    public function get_minute($uid, $day, $take_day, $take_count){
        $take_list = Db::name('order_take')->where([
            ['user_id', '=', $uid],
            ['status', '<>', 2],
            ['id', 'IN', $take_day]
        ])
        ->field('day, min_minute, max_minute')
        ->order('min_minute', 'ASC')
        ->select();
    
        $take_list_time = []; // 对应天数所有的规定打卡时间
        foreach ($take_list as $val) {
            if ($val['max_minute']) {
                $take_list_time[$val['day']][] = ['min_minute' => $val['min_minute'], 'max_minute' => $val['max_minute']];
            }
        }
    
        // 获取对应天数的打卡时间范围
        $minute_range = self::config('take_time_range', $day)[$take_count];

        if (isset($take_list_time[$day])) {
            $add_position = []; // 可插入位置 -1 规定最少时间的左方; -2 规定最大时间的右方; n 在第n-1个 - n个 中间插入时间
            $participation_min_minute = $take_list_time[$day][0]['min_minute']; // 该天数规定最少打卡时间
            $participation_max_minute = $take_list_time[$day][0]['max_minute']; // 该天数规定最大打卡时间
            for ($i = 1; $i < 2; $i++) {
                if (!isset($take_list_time[$day][$i])) {
                    break;
                }
    
                if ($take_list_time[$day][$i]['min_minute'] < $participation_min_minute) {
                    $participation_min_minute = $take_list_time[$day][$i]['min_minute'];
                }
                if ($take_list_time[$day][$i]['max_minute'] > $participation_max_minute) {
                    $participation_max_minute = $take_list_time[$day][$i]['max_minute'];
                }
                if ($take_list_time[$day][$i]['min_minute'] - $take_list_time[$day][$i - 1]['max_minute'] >= $minute_range) {
                    // $i 与 $i-1 间时间差 > 打卡时间范围
                    $add_position[] = $i;
                }
            }
            if ($participation_min_minute >= $minute_range) {
                $add_position[] = -1;
            }
            if (180 - $participation_max_minute >= $minute_range) {
                $add_position[] = -2;
            }
    
            // 随机插入位置
            if ($add_position) {
                $position_current = mt_rand(1, count($add_position)) - 1;
                switch ($add_position[$position_current]) {
                    case -1:
                        $min_minute = mt_rand(0, $participation_min_minute - $minute_range);
                        $max_minute = $min_minute + $minute_range;
                        break;
                    case -2:
                        $min_minute = mt_rand($participation_max_minute, 180 - $minute_range);
                        $max_minute = $min_minute + $minute_range;
                        break;
                    default:
                        $min_minute = mt_rand($take_list_time[$day][$add_position[$position_current] - 1]['max_minute'], $take_list_time[$day][$add_position[$position_current]]['min_minute'] - $minute_range);
                        $max_minute = $min_minute + $minute_range;
                        break;
                }
            } else {
                $min_minute = mt_rand(0, 180 - $minute_range);
                $max_minute = $min_minute + $minute_range;
            }
        } else {
            // 该天数未参加过白拿
            $min_minute = mt_rand(0, 180 - $minute_range);
            $max_minute = $min_minute + $minute_range;
        }
    
        return ['min_minute' => $min_minute, 'max_minute' => $max_minute];
    }
    
    public static function dredge_eduities($user_id, $data)
    {
        $type = $data['type'];
        $eduities_para = self::config('eduities_para', $type);
        $need_pay = $eduities_para['price'];
        
        if (isset($eduities_para['errcode'])) {
            return [[], '参数错误', 40001];
        }

        if (!isset($data['pay_method'])) {
            $data['pay_method'] = 'wx';
        }

        $paid = $need_pay;
        if (isset($data['use_balance']) && $data['use_balance']) {
            $money = User::me()['balance'];
        
            if ($money >= $need_pay) {
                $paid = 0;
            } else {
                $paid = $need_pay - $money;
            }
        }
        
        if ($paid > 0) {
            // 业务参数
            $option = [
                'type' => $type,
                'pay_method' => $data['pay_method'],
            ];
            
            $res = Pay::pay($paid, ['attach' => "{$user_id}:{$paid}:GoodsModel:dredge_eduities"], ['attach_data' => $option]);
            return $res;
        }
        
        // 已开通 到期时间
        $user_promotion_data = Db::name('user_promotion')->where('user_id', '=', $user_id)->find();
        if (!$user_promotion_data || $user_promotion_data['expire_time'] < request()->time()) {
            // 没开通或过期
            $eduities_expire_time = Common::today_time() + 86400 + $eduities_para['increase_time'];
        } else {
            // 没过期
            $eduities_expire_time = $user_promotion_data['expire_time'] + $eduities_para['increase_time'];
        }
        
        // 是否有上级
        $pid = Db::name('user_tier')->where('user_id', $user_id)->value('pid');
        $superior_grade = 0;
        $count_superior_year_partner = 0;
        $count_on_superior_year_partner = 0;
        $count_on_superior_partner = 0;
        
        // 上级是否是合伙人
        if ($pid) {
            $superior_promotion_data = db('user_promotion')->where('user_id', '=', $pid)->find();
            if ($superior_promotion_data && $superior_promotion_data['expire_time'] > request()->time()) {
                $superior_grade = 1;
                
                // 清除缓存
                $forecast_income_cache_key = 'forecast_income_'. $pid;
                Cache::rm($forecast_income_cache_key);
            }
            
            // 上级年合伙人人数
            $count_superior_year_partner = Db::query('select count(*) as count from (select min(id) from sl_user_dredge_eduities where user_id in (select user_id from sl_user_tier where pid = '. $pid .') and type = 2 and beg_time between '. (request()->time() - 31536000) .' and '. (request()->time() + 86400) .' GROUP BY user_id) t')[0]['count'];
            
            // 上上级
            $ppid = Db::name('user_tier')->where('user_id', $pid)->value('pid');
            if ($ppid) {
                file_put_contents('weixin.log', date('Y-m-d H:i:s')."\n".var_export('select count(*) as count from (select min(id) from sl_user_dredge_eduities where user_id in (select user_id from sl_user_tier where pid = '. $ppid .') and type = 2 and beg_time between '. (request()->time() - 31536000) .' and '. (request()->time() + 86400) .' GROUP BY user_id) t', true), FILE_APPEND );
                // 上上级年合伙人人数
                $count_on_superior_year_partner = Db::query('select count(*) as count from (select min(id) from sl_user_dredge_eduities where user_id in (select user_id from sl_user_tier where pid = '. $ppid .') and type = 2 and beg_time between '. (request()->time() - 31536000) .' and '. (request()->time() + 86400) .' GROUP BY user_id) t')[0]['count'];
                // 上上级合伙人人数
                $count_on_superior_partner = Db::query('select count(*) as count from sl_user_promotion where user_id in (select user_id from sl_user_tier where pid = '. $ppid .') and expire_time > '. request()->time())[0]['count'];
            }
        } else {
            $pid = 0;
        }

        Db::startTrans();
        try {
            $nickname = Db::name('user')->where('id', '=', $user_id)->value('nickname');
            
            // 过期时间
            if ($user_promotion_data) {
                Db::name('user_promotion')->where('user_id', $user_id)->setField('expire_time', $eduities_expire_time);
            } else {
                Db::name('user_promotion')->insert([
                    'user_id' => $user_id,
                    'p_id' => 0,
                    'expire_time' => $eduities_expire_time
                ]);
            }
            
            if ($type == 2 && $count_superior_year_partner >= 20) {
                $eduities_para['superior_award'] = 20000;
            }
            
            // 添加开通记录
            $dredge_id = Db::name('user_dredge_eduities')->insert([
                'user_id' => $user_id,
                'type' => $data['type'],
                'order_no' => $data['out_trade_no'],
                'price' => $need_pay,
                'add_time' => request()->time(),
                'pid' => $superior_grade == 1 ? $pid : 0,
                'superior_award' => $eduities_para['superior_award'],
                'beg_time' => (!$user_promotion_data || $user_promotion_data['expire_time'] < request()->time()) ? Common::today_time() + 86400 : $user_promotion_data['expire_time']
            ]);
            
            if ($superior_grade) {
                Db::name('user_dynamic')->insert([
                    'user_id' => 0,
                    'nickname' => '微选生活',
                    'avator' => '/static/img/logo.png',
                    'receive_uid' => $pid,
                    'event' => 'dredge_eduities_item',
                    'event_id' => $user_id,
                    'data' => serialize([
                        'title' => '晋升津贴通知  奖励 '. round($eduities_para['superior_award'] / 100, 2) .'元',
                        'content' => '成员【'. $nickname .'】['. date('Y-m-d') .']升级成功，您获得晋升津贴'. round($eduities_para['superior_award'] / 100, 2) .'元'
                    ]),
                    'status' => 1,
                    'add_time' => request()->time()
                ]);
                
                Db::name('xinge_task')->insert([
                    'user_id' => $pid,
                    'event' => 'dredge_eduities_item',
                    'data' => serialize([
                        'title' => '晋升津贴通知  奖励 '. round($eduities_para['superior_award'] / 100, 2) .'元',
                        'content' => '成员【'. $nickname .'】['. date('Y-m-d') .']升级成功，您获得晋升津贴'. round($eduities_para['superior_award'] / 100, 2) .'元'
                    ])
                ]);
            }
            
            if ($count_on_superior_partner >= 10) {
                // 非直属合伙人费用15%
                Db::name('user_dredge_eduities')->insert([
                    'user_id' => $user_id,
                    'type' => 3,
                    'order_no' => $data['out_trade_no'],
                    'price' => $need_pay,
                    'add_time' => request()->time(),
                    'pid' => $ppid,
                    'superior_award' => $need_pay * 0.15,
                    'beg_time' => 0
                ]);
                
                Db::name('user_dynamic')->insert([
                    'user_id' => 0,
                    'nickname' => '微选生活',
                    'avator' => '/static/img/logo.png',
                    'receive_uid' => $ppid,
                    'event' => 'dredge_eduities_item',
                    'event_id' => $user_id,
                    'data' => serialize([
                        'title' => '晋升津贴通知  奖励 '. round($need_pay * 0.15 / 100, 2) .'元',
                        'content' => '成员【'. $nickname .'】['. date('Y-m-d') .']升级成功，您获得晋升津贴'. round($need_pay * 0.15 / 100, 2) .'元'
                    ]),
                    'status' => 1,
                    'add_time' => request()->time()
                ]);
                
                Db::name('xinge_task')->insert([
                    'user_id' => $ppid,
                    'event' => 'dredge_eduities_item',
                    'data' => serialize([
                        'title' => '晋升津贴通知  奖励 '. round($need_pay * 0.15 / 100, 2) .'元',
                        'content' => '成员【'. $nickname .'】['. date('Y-m-d') .']升级成功，您获得晋升津贴'. round($need_pay * 0.15 / 100, 2) .'元'
                    ])
                ]);
                
                // 清除缓存
                $forecast_income_cache_key = 'forecast_income_'. $ppid;
                Cache::rm($forecast_income_cache_key);
            }
            
        } catch (\Exception $e) {
            Log::record('dredge_eduities add error, uid-'. $user_id);
            Db::rollback();
            return [[], '处理失败', 50001];
        }
        
        // 扣除余额
        $res = User::money($user_id, 0 - $need_pay, 'eduities_'. $type, $dredge_id, '成为合伙人/'. ($type == 1 ? '月' : '年'));
        if (!isset($res['flow_id'])) {
            Log::record('dredge_eduities paymoney add error, uid-'. $user_id);
            Db::rollback();
            return [[], '处理失败', 50001];
        }
        
        // 自己获取免单金额
        $team_partner = db('order_free_log')->where([
            ['event', '=', 'team_partner'],
            ['user_id', '=', $user_id]
        ])->find();
        if ($type == 1) {
            $free_order_count = 5;
        } else {
            $free_order_count = 10;
        }
        if (!$team_partner) {
            $first_order = db('order_free_log')->where([
                ['event', '=', 'first_order'],
                ['user_id', '=', $user_id]
            ])->find();
            if ($first_order) {
                $free_order_count--;
            }
        }
        
        $rs = Order::add_free_order($user_id, $user_id, $free_order_count, '自己成为合伙人，用户id：'.$user_id, 'team_partner');
        if (!$rs) {
            Log::record('dredge_eduities add_free_order error, uid-'. $user_id);
            Db::rollback();
            return [[], '处理失败', 50001];
        }
        
        if ($pid) {
//             if ($superior_grade == 1) {
//                 // 晋升津贴 
//                 $res = User::money($pid, $eduities_para['superior_award'], 'superior_award', $dredge_id, '下级团队晋升津贴');
//                 if (!isset($res['flow_id'])) {
//                     Log::record('dredge_eduities superior_award add error, uid-'. $user_id);
//                     Db::rollback();
//                     return [[], '处理失败', 50001];
//                 }
                
//                 // 清除缓存
//                 $forecast_income_cache_key = 'forecast_income_'. $pid;
//                 Cache::rm($forecast_income_cache_key);
//             }
            
            // 上级获取免单名额
            $team_partner = db('order_free_log')->where([
                ['event', '=', 'team_partner'],
                ['user_id', '=', $pid]
            ])->find();
            if ($type == 1) {
                $free_order_count = 5;
            } else {
                $free_order_count = 10;
            }
            if (!$team_partner) {
                $first_order = db('order_free_log')->where([
                    ['event', '=', 'first_order'],
                    ['user_id', '=', $pid]
                ])->find();
                if ($first_order) {
                    $free_order_count--;
                }
            }
            
            $rs = Order::add_free_order($pid, $user_id, $free_order_count, '下级成为合伙人，用户id：'.$pid, 'team_partner');
            if (!$rs) {
                Log::record('dredge_eduities add_free_order error, uid-'. $pid);
                Db::rollback();
                return [[], '处理失败', 50001];
            }
        }
        
        if ($type == 2 && $count_on_superior_year_partner >= 10) {
            $rs = Order::add_free_order($ppid, $user_id, 8, '下下成为合伙人，且年合伙人>=10，用户id：'.$user_id, 'on_superior_year_parner');
            if (!$rs) {
                Log::record('dredge_eduities add_free_order error, uid-'. $pid);
                Db::rollback();
                return [[], '处理失败', 50001];
            }
        }
        
        Db::commit();
        
        return [['dredge_id' => $dredge_id], 'success', 0];
    }
    
    // 发放奖励
    public function grant_commission($grant_rate)
    {
        if (!$grant_rate) {
            return '发放比例不正确';
        }
        $grant_rate /= 100;
        
        // 所有审核中订单
        $order_data = Db::name('order')
        ->field('id, user_id, directly_user_id, directly_supervisor_user_id, user_commission, directly_user_commission, directly_supervisor_user_commission')
        ->where('status', 6)
        ->select();
        
        $grant_commission = 0;
        foreach ($order_data as $val) {
            Db::startTrans();
            // 发放佣金
            if ($val['user_id'] > 0 && $val['user_commission'] > 0) {
                
                $res = User::money($val['user_id'], $val['user_commission'] * $grant_rate, 'goods_commission', $val['id'], '商品佣金收入');
                
                if (!isset($res['flow_id'])) {
                    Log::record('grant_commission user_id-user_commission, order_id-'. $val['id']);
                    Db::rollback();
                    return 'FAIR';
                }
                $grant_commission += $val['user_commission'] * $grant_rate;
            }
            if ($val['directly_user_id'] > 0 && $val['directly_user_commission'] > 0) {
                $res = User::money($val['directly_user_id'], $val['directly_user_commission'] * $grant_rate, 'goods_commission', $val['id'], '商品佣金收入');
                
                if (!isset($res['flow_id'])) {
                    Log::record('grant_commission directly_user_id-directly_user_commission, order_id-'. $val['id']);
                    Db::rollback();
                    return 'FAIR';
                }
                $grant_commission += $val['directly_user_commission'] * $grant_rate;
            }
            if ($val['directly_supervisor_user_id'] > 0 && $val['directly_supervisor_user_commission'] > 0) {
                $res = User::money($val['directly_supervisor_user_id'], $val['directly_supervisor_user_commission'] * $grant_rate, 'goods_commission', $val['id'], '商品佣金收入');
                
                if (!isset($res['flow_id'])) {
                    Log::record('grant_commission directly_supervisor_user_id-directly_supervisor_user_commission, order_id-'. $val['id']);
                    Db::rollback();
                    return 'FAIR';
                }
                $grant_commission += $val['directly_supervisor_user_commission'] * $grant_rate;
            }
            
            // 订单状态改为已结算
            $rs = Db::name('order')->where('id', $val['id'])->update(['status' => 5]);
            if (!$rs) {
                Log::record('grant_commission order_status, order_id-'. $val['id']);
                Db::rollback();
                return 'FAIR';
            }
            
            Db::commit();
        }
        
        $stat_date = strtotime(date('Ym', strtotime('-1 month')));
        $data = [
            'grant_commission' => $grant_commission,
            'grant_rate' => $grant_rate
        ];
        Db::name('goods_promotion_stat')->where('stat_date', $stat_date)->update($data);
    }
    
    public function goods_img_create($goods_id, $img_src, $info){
        $save_path = config('app.poster.root_path') . 'poster/goods_img/';
        $save_name = $goods_id . '.jpg';
        $file_goods_img = $save_path . $save_name;
        
        if (!file_exists($file_goods_img)) {
            if (empty($img_src)) {
                return '';
            }
        
            if (strpos($img_src, 'http://') === false && strpos($img_src, 'https://') === false) {
                $img_src = 'https:' . $img_src;
            }
        
            Common::curlDownload($img_src, $save_path, $save_name);
            
            if (!file_exists($file_goods_img)) {
                return '';
            }
            
            // 图片裁剪
            $image = \think\Image::open($file_goods_img);
            $image->thumb($info[0], $info[1], \think\Image::THUMB_CENTER)->save($file_goods_img);
        }
        
        return $file_goods_img;
    }
    
    function autowrap($fontsize, $angle, $fontface, $string, $width, $max_line = 0) {
        // 这几个变量分别是 字体大小, 角度, 字体名称, 字符串, 预设宽度, 最大行数
        $content = "";
    
        // 将字符串拆分成一个个单字 保存到数组 letter 中
        for ($i=0;$i<mb_strlen($string);$i++) {
            $letter[] = mb_substr($string, $i, 1);
        }
    
        $cur_line = 1; // 当前行
        foreach ($letter as $l) {
            $teststr = $content." ".$l;
            $testbox = imagettfbbox($fontsize, $angle, $fontface, $teststr);
            
            // 判断拼接后的字符串是否超过预设的宽度
            if (($testbox[2] > $width) && ($content !== "")) {
                if ($max_line && $max_line == $cur_line) {
                    $content = mb_substr($content, 0, -2);
                    $content .= "...";
                    break;
                }
                
                $content .= "\n";
                $cur_line++;
            }
            $content .= $l;
        }
        return $content;
    }
    
    public function poster_create($user_id, $goods, $file_poster) {
        $root_path = config('app.poster.root_path');
    
        // 二维码
        $url = $goods['good_quan_link'];
        $file_qrcode = $root_path . 'qrcode/goods/u_' . $user_id . '_'. ($goods['goods_type'] == 1 ? 'pdd' : 'jd') .'_g_'. $goods['goods_id'] .'.png';
        if (!file_exists($file_qrcode)) {
            $rs = Common::make_qrcode($url, $file_qrcode);
            if ($rs == '') {
                return [[], '海报二维码生成失败', 40001];
            }
        }
        
        $font_path = './static/fonts/PINGFANG.TTF';
        $goods['name'] = $this->autowrap(23, 0, $font_path, '        ' . $goods['name'], 690, 2);
        
        $img_width = 750;
        $img_height = $img_width;
        // 商品图片
        $file_goods_img = $this->goods_img_create($goods['goods_id'], $goods['img'], [$img_width, $img_height]);
        $file_goods_img = $file_goods_img ? $file_goods_img : $root_path . 'poster/signin/bg-v2.png';

        // 保存分享商品海报
        $bg = $root_path . 'poster/bg/goods_bg.jpg';
        if (!file_exists($bg)) {
            $img = imagecreatetruecolor($img_width, $img_height + 300);
            $color = imagecolorallocate($img, 255, 255, 255);
            imagefill($img, 0, 0, $color);
            imagejpeg($img, $bg);
        }
        $img = \think\Image::open($bg);

        $padding_left = 30;
        $padding_right = 30;
    
        $size_qrcode = 250;
        $x_qrcode = $img_width - 280;
        $y_qrcode = $img_height + 300;
    
        // 海报底部备注
        //$btn_desc = $this->autowrap(11, 0, $font_path, $btn_desc, 390);

        $img->water($file_goods_img, array(0, 0), 100, array('width' => 750))
        ->water($file_qrcode, array($x_qrcode, $y_qrcode), 100, array('width' => $size_qrcode))
        ->text($goods['name'], $font_path, 23, array(51, 51, 51, 0), 1, array($padding_left, $img_height + 45))
        ->text('券后价', $font_path, 20, [238, 0, 8, 0], 1, [$padding_left, $img_height + 160])
        ->text('￥'. $goods['price'], $font_path, 35, [238, 0, 8, 0], 1, [$padding_left + 80, $img_height + 154])
        ->text('原价￥'. $goods['org_price'], $font_path, 18, [124, 124, 124, 0], \think\Image::WATER_NORTHWEST, [$padding_left, $img_height + 214])
        ->text('__________', $font_path, 19, [124, 124, 124, 0], \think\Image::WATER_NORTHWEST, [$padding_left, $img_height + 222])
        //->text('￥'. $goods['price'], $font_path, 30, [238, 0, 8, 0], 1, [$padding_left + 29, $size_head + $y_head + 94])
        //->text('券后价:  ￥'. $goods['price'], $font_path, 24, [238, 0, 8, 0], 1, [$padding_left - 1, $size_head + $y_head + 94])
        ->text('长按图片识别二维码，下单立省'. ($goods['org_price'] - $goods['price']) .'元', $font_path, 18, [124, 124, 124, 0], \think\Image::WATER_SOUTHEAST, [-304, -120]);
        
        // 商品类型
        switch ($goods['goods_type']) {
            case 1:
                $img->water($root_path . 'poster/bg/pdd.jpg', [$padding_left, $img_height + 45], 100, ['width' => 68]);
                break;
            default:
                $img->water($root_path . 'poster/bg/jd.jpg', [$padding_left, $img_height + 45], 100, ['width' => 70]);
                break;
        }
        
        $img->save($file_poster);
    
        return 'SUCCESS';
    }
    
    public static function level_promote($user_id, $data)
    {
        // 当前会员等级
        $user_level = Db::name('user_level')
        ->where([
            ['user_id', '=', $user_id],
            ['expier_time', '>', request()->time()]
        ])->value('level');
        if (!$user_level) {
            $user_level = 1;
        }

        // 1.所升等级必须比当前等级高   2.所升等级（2 超级会员 3 vip 4 合伙人）
        if ($data['level'] <= $user_level || !in_array($data['level'], [2, 3, 4])) {
            return [[], '不正确的会员等级', 40000];
        }
    
        if (!isset($data['pay_method'])) {
            $data['pay_method'] = 'wx';
        }
    
        // 会员等级费用
        $level_price = Db::name('siteinfo')->where('key', '=', 'level_price')->value('value');
        $level_price = json_decode($level_price, true);
    
        $need_pay = $level_price[$data['level']];
        if (!$need_pay) {
            return [[], '支付金额有误', 40000];
        }

        $user = User::find($user_id);
        $paid = $need_pay;
        if (isset($data['use_balance']) && $data['use_balance']) {
            $money = $user['balance'];

            if ($money >= $need_pay) {
                $paid = 0;
            } else {
                $paid = $need_pay - $money;
            }
        }

        if ($paid > 0) {
            // 业务参数
            $option = [
                'level' => $data['level'],
                'pay_method' => $data['pay_method'],
            ];

            $res = Pay::pay($paid, ['attach' => "{$user_id}:{$paid}:GoodsModel:level_promote", 'openid' => $user['openid']], ['attach_data' => $option]);
            return $res;
        }
	
        Db::startTrans();

        try {
            // 升级人昵称
            $user_info = Db::name('user')->field('nickname, insert(`mobile`, 4, 5, "*****") as `mobile`')->where('id', '=', $user_id)->find();
            $nickname = $user_info['nickname'];
            $mobile = $user_info['mobile'] ? $user_info['mobile'] : $user_info['nickname'];
            // 扣除升级费用
            $res = User::money($user_id, -$need_pay, 'level_promote_'. $data['level'], $user_id, '升级');
            
            // 从哪个等级开始获取上级
            if ($data['level'] == 4) {
                $first_level = 3;
            } else {
                $first_level = 1;
            }
            
            // 升级获得返利信息
            $level_promote_awards = Db::name('siteinfo')->where('key', '=','level_promote_awards')->value('value');
            $level_promote_awards = json_decode($level_promote_awards, true);
            
            // 上级团队
            $team_data = User::user_superior($user_id, $first_level);
            if ($team_data) {
                if (($data['level'] == 2 && $team_data[0]['level'] != 1) || $team_data[0]['level'] == 4) {
                    // 直接到账情况 1.升级为超级会员，上级不是普通会员 2.上级是合伙人
                    $res = User::money($team_data[0]['user_id'], $level_promote_awards[$team_data[0]['level']][$data['level']], 'level_promote_awards_'. $data['level'], $user_id, '晋升津贴');
                    // 添加津贴记录表
                    Db::name('user_level_promote_award')->insert([
                        'user_id' => $team_data[0]['user_id'],
                        'promote_user_id' => $user_id,
                        'promote_level' => $data['level'],
                        'award' => $level_promote_awards[$team_data[0]['level']][$data['level']],
                        'promote_remind_id' => 0,
                        'add_time' => request()->time()
                    ]);
                    
                    // 发送消息通知
                    Db::name('user_dynamic')->insert([
                        'user_id' => 0,
                        'nickname' => '微选生活',
                        'avator' => '/static/img/logo.png',
                        'receive_uid' => $team_data[0]['user_id'],
                        'event' => 'dredge_eduities_item',
                        'event_id' => $user_id,
                        'data' => serialize([
                            'title' => '晋升津贴通知  奖励 '. round($level_promote_awards[$team_data[0]['level']][$data['level']] / 100, 2) .'元',
                            'content' => '成员【'. $nickname .'】['. date('Y-m-d') .']升级成功，您获得晋升津贴'. round($level_promote_awards[$team_data[0]['level']][$data['level']] / 100, 2) .'元'
                        ]),
                        'status' => 1,
                        'add_time' => request()->time()
                    ]);
                    
                    Db::name('xinge_task')->insert([
                        'user_id' => $team_data[0]['user_id'],
                        'event' => 'dredge_eduities_item',
                        'data' => serialize([
                            'title' => '晋升津贴通知  奖励 '. round($level_promote_awards[$team_data[0]['level']][$data['level']] / 100, 2) .'元',
                            'content' => '成员【'. $nickname .'】['. date('Y-m-d') .']升级成功，您获得晋升津贴'. round($level_promote_awards[$team_data[0]['level']][$data['level']] / 100, 2) .'元'
                        ])
                    ]);
                    
                } else {
                    // 提醒用户升级
                    $insert_data = [
                        'user_id' => $user_id,
                        'order_no' => $data['out_trade_no'],
                        'level' => $data['level'],
                        'price' => $need_pay,
                        'add_time' => request()->time(),
                        'remind_user_id' => $team_data[0]['user_id'],
                        'remind_user_status' => 0,
                        'team_data' => json_encode($team_data)
                    ];
                    if ($data['level'] == $team_data[0]['level']) {
                        // 为了提升佣金，比用户升级后的等级+1
                        $insert_data['remind_user_condition'] = $data['level'] + 1;
                        $insert_data['remind_user_commisson'] = $level_promote_awards[$insert_data['remind_user_condition']][$data['level']];
                    } else {
                        // 为了获取佣金，等于用户升级后的等级
                        $insert_data['remind_user_condition'] = $data['level'];
                        $insert_data['remind_user_commisson'] = $level_promote_awards[$insert_data['remind_user_condition']][$data['level']];
                    }
            
                    Db::name('user_level_promote_commission_remind')->insert($insert_data);
                    
                    // 发送消息通知
                    Db::name('user_dynamic')->insert([
                        'user_id' => 0,
                        'nickname' => '微选生活',
                        'avator' => '/static/img/logo.png',
                        'receive_uid' => $team_data[0]['user_id'],
                        'event' => 'level_redpack',
                        'event_id' => $user_id,
                        'data' => serialize([
                            'title' => '亲，你又有一笔现金待领取哦~',
                            'content' => '你的好友'. $mobile .'刚刚已升级，你获得'. round($insert_data['remind_user_commisson'] / 100, 2) .'元现金奖励，2小时后过期哦~'
                        ]),
                        'status' => 1,
                        'add_time' => request()->time()
                    ]);
                    
                    Db::name('xinge_task')->insert([
                        'user_id' => $team_data[0]['user_id'],
                        'event' => 'level_redpack',
                        'data' => serialize([
                            'title' => '亲，你又有一笔现金待领取哦~',
                            'content' => '你的好友'. $mobile .'刚刚已升级，你获得'. round($insert_data['remind_user_commisson'] / 100, 2) .'元现金奖励，2小时后过期哦~'
                        ])
                    ]);

                    // 发送短信通知
                    $mobile = db('user')->where('id', '=', $team_data[0]['user_id'])->value('mobile');
                    if ($mobile) {
                        $realnames = db('user_withdraw')
                            ->where([['user_id', '=', $team_data[0]['user_id']]])
                            ->value('real_name');

                        $sms = new \dysms();
                        $res = $sms->send([
                            'TemplateCode' => 'SMS_153325849',
                            'TemplateParam' => [
                                'price' => round($insert_data['remind_user_commisson'] / 100, 2),
                                'nickname' => empty($realnames) ? '亲' : $realnames
                            ],
                            'PhoneNumbers' => $mobile
                        ]);

                        if (!empty($res['Code']) || $res['Code'] != 'OK') {
                            Log::record('dysms send fail,' . json_encode($res, JSON_UNESCAPED_UNICODE));
                        }
                    }
                }
            } else {
                // 新模式上线后的登录时间
                $once_login_time = db('user_promotion')->where('user_id', $user_id)->value('once_login_time');
                if (!$once_login_time) {
                    $once_login_time = '1543468455';
                }

                if ($data['level'] != 4 && ( ( request()->time() - $once_login_time - 3600 ) % 25200 ) < 7200 ) {
                    // 1.用户没有上级且升级为超级会员或vip && 有红包正在倒计时
                    $insert_data = [
                        'user_id' => $user_id,
                        'order_no' => $data['out_trade_no'],
                        'level' => $data['level'],
                        'price' => $need_pay,
                        'add_time' => request()->time(),
                        'remind_user_id' => $user_id,
                        'remind_user_status' => 1,
                        'team_data' => json_encode($team_data),
                        'remind_user_condition' => $data['level'],
                        'remind_user_commisson' => $level_promote_awards[$data['level']][$data['level']]
                    ];
                    Db::name('user_level_promote_commission_remind')->insert($insert_data);

                    // 发送消息通知
                    Db::name('user_dynamic')->insert([
                        'user_id' => 0,
                        'nickname' => '微选生活',
                        'avator' => '/static/img/logo.png',
                        'receive_uid' => $user_id,
                        'event' => 'level_redpack',
                        'event_id' => $user_id,
                        'data' => serialize([
                            'title' => '亲，你又有一笔现金待领取哦~',
                            'content' => '你的好友'. $mobile .'刚刚已升级，你获得'. round($level_promote_awards[$data['level']][$data['level']] / 100, 2) .'元现金奖励，2小时后过期哦~'
                        ]),
                        'status' => 1,
                        'add_time' => request()->time()
                    ]);
                    
                    Db::name('xinge_task')->insert([
                        'user_id' => $user_id,
                        'event' => 'level_redpack',
                        'data' => serialize([
                            'title' => '亲，你又有一笔现金待领取哦~',
                            'content' => '你的好友'. $mobile .'刚刚已升级，你获得'. round($level_promote_awards[$data['level']][$data['level']] / 100, 2) .'元现金奖励，2小时后过期哦~'
                        ])
                    ]);
                }
            }

            // 修改符合条件的下级升级红包奖励
            Db::name('user_level_promote_commission_remind')->where([
                ['remind_user_id', '=', $user_id],
                ['remind_user_condition', '<=', $data['level']]
            ])->update([
                'remind_user_status' => 1
            ]);
            
            $rs = Db::name('user_level')->where('user_id', '=', $user_id)->count();
            if ($rs > 0) {
                // 修改会员等级记录
                Db::name('user_level')->where('user_id', '=', $user_id)->update([
                    'level' => $data['level'],
                    'expier_time' => request()->time() + 315360000,
                    'add_time' => request()->time()
                ]);
            } else {
                // 添加会员等级记录
                Db::name('user_level')->insert([
                    'user_id' => $user_id,
                    'level' => $data['level'],
                    'expier_time' => request()->time() + 315360000,
                    'add_time' => request()->time(),
                ]);
            }

            // 天天领现金
            if ($data['level'] >= 3) {
                Activity::pick_new_cash_receive($user_id);
            }
        } catch (\Exception $e) {
            Log::record('level_promote add error, uid-'. $user_id);
            Db::rollback();
            return [[], '处理失败', 50001];
        }
        
        Db::commit();
        
        return [[], '升级成功', 0];
    }
}