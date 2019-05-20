<?php

namespace app\common\model;

use think\Db;
use think\facade\Log;
use think\Model;
use think\facade\Cache;

class Order extends Model
{
    public static function order_sn_create(){
        $orderSn = 'SL';
        
        $year_code = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
        $order_sn = $orderSn
        . $year_code[intval(date('Y')) - 2017]
        . strtoupper(dechex(date('m')))
        . date('d')
        . substr(time(), -5)
        . substr(microtime(), 2, 5)
        . sprintf('%02d', rand(0, 99));
        
        return $order_sn;
    }
    

    public static function first_order($where = [])
    {
        $where[] = ['add_time', '>', 0];
    
        $rs = db('order')->field('id')->where($where)->find();
    
        return $rs ? false : true;
    }
    
    public static function has_partner($user_id)
    {
        $GLOBALS['user_id'] = $user_id;
        
        $rs = db('user_promotion')->where([
            ['user_id', '=', $user_id],
            ['expire_time', '>', request()->time()]
        ])->find();
        
        if (!$rs) {
            $rs = db('user_promotion')->where([
                ['user_id', 'IN', function ($query) {
                    $query->name('user_tier')->where('pid', $GLOBALS['user_id'])->field('user_id');
                }],
                ['expire_time', '>', request()->time()]
            ])->find();
        }
        
        return $rs ? true : false;
    }
    
    public static function add_free_order($user_id, $target_user_id, $count, $cause, $event)
    {
        if (!$user_id || !$target_user_id || !$count || !$cause || !$event) {
            return false;
        }
        
        // 机器码
        $client_code = Db::name('app_token')->where('user_id', '=', $user_id)->value('client_code');
        
        Db::startTrans();
        
        // 添加免单记录表
        $rs = Db::name('order_free_log')->insert([
            'user_id' => $user_id,
            'target_user_id' => $target_user_id,
            'add_free_order' => $count,
            'cause' => $cause,
            'event' => $event,
            'ip' => request()->ip(),
            'add_time' => request()->time(),
            'client_code' => $client_code ?: ''
        ]);
        if (!$rs) {
            Db::rollback();
            return false;
        }
        
        // 增加免单数
        $rs = DB::name('user_promotion')->where('user_id', '=', $user_id)->inc('free_order', $count)->update();
        if (!$rs) {
            Db::rollback();
            return false;
        }
        
        Db::commit();
        return true;
    }
    
    public function orderuser(){
        return $this->hasOne(User::class, 'id', 'user_id');
    }
    
    public function take($user_id, $data, $pay_id = 0){
        $gid = $data['gid'];
        $address_id = $data['address_id']; // 收货地址
        $day = $data['day']; // 天数
        
        if (!$user_id || !$gid) {
            return [[], '信息有误', 40001];
        }
        if (!$address_id) {
            return [[], '请填写收货地址！', 40002];
        }
        if (!isset($data['pay_method'])) {
            $data['pay_method'] = 'wx';
        }
        
        $Goods = new GoodsModel();
        
        // 商品信息
        $goods = GoodsModel::field('id, name, img, org_price, price, coupon_time, coupon_price, good_quan_link, tao_pass')
        ->where('id', $gid)
        ->find();
        if (!$goods) {
            return [[], '商品信息有误', 40002];
        }
        if ($goods->price > 42000) {
            return [[], '该商品不能参与白拿', 40002];
        }
        
        // 进行中白拿个数
        $progress_take_count = db('order_take')->where([
            ['status', '=', 0],
            ['user_id', '=', $user_id]
        ])->count();
        
        if ($progress_take_count > 2) {
            return [[], '亲，请先完成现有的早起白拿任务再来参与', 40003];
        }
        
        $take_day = $Goods->check_take($user_id);
        if (!in_array($data['day'], $take_day['take_day'])) {
            return [[], '缴纳押金失败，请刷新页面重试', 40004];
        }
        
        // 白拿押金
        $day_appoint = GoodsModel::config('take_day_appoint', $data['day']);
        $need_pay = $goods['price'] * $day_appoint;
        $paid = $need_pay = round($need_pay);
        
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
                'gid' => $gid,
                'day' => $day,
                'address_id' => $address_id,
                'pay_method' => $data['pay_method']
            ];
            
            $res = Pay::pay($paid, ['attach' => "Order:{$user_id}:{$paid}:take", 'out_trade_no' => $data['out_trade_no']], ['attach_data' => $option]);
            return $res;
        }
    
        // 地址信息
        $address = Address::where('id', $data['address_id'])->field('consignee, mobile, province, city, district, address')->find();

        // 打卡时间范围
        $take_count = isset($take_day['take_count'][$day]) ? $take_day['take_count'][$day] : 0;
        $minute_range = $Goods->get_minute($user_id, $day, $take_day['take_day'], $take_count);
        
        Db::startTrans();
        try {
            // 添加白拿记录
            $take_id = Db::name('order_take')->insertGetId([
                'user_id' => $user_id,
                'order_no' => $data['out_trade_no'],
                'gid' => $data['gid'],
                'residue_day' => $data['day'] - 1,
                'day' => $data['day'],
                'appoint_price' => $need_pay,
                'status' => 0,
                'add_time' => request()->time(),
                'min_minute' => $minute_range['min_minute'],
                'max_minute' => $minute_range['max_minute'],
                'pay_id' => $pay_id,
                'pay_type' => $data['pay_method'],
                'address_id' => $data['address_id'],
                'accept_name' => $address->consignee,
                'mobile' => $address->mobile,
                'address' => $address->province . $address->city . $address->district . $address->address,
                'good_type' => 1,
                'order_status' => 0,
            ]);
            // 记录商品信息
            Db::name('order_goods')->insertGetId([
                'order_id' => $take_id,
                'good_id' => $data['gid'],
                'good_name' => $goods['name'],
                'img' => $goods['img'],
                'good_price' => $goods['org_price'],
                'quan_price' => $goods['price'],
                'good_num' => 1,
                'quan_price' => $goods['coupon_price'],
                'type' => 1
            ]);
        } catch (\Exception $e) {
            Log::record('take_order add error, uid-'. $user_id);
            Db::rollback();
            return [[], '处理失败', 50001];
        }
        
        // 扣除余额
        $res = User::money($user_id, 0 - $need_pay, 'take_appoint', $take_id, '商品押金');

        if (!isset($res['flow_id'])) {
            Log::record('take_order add error, uid-'. $user_id);
            Db::rollback();
            return [[], '处理失败', 50001];
        }
        Db::commit();
    
        return [[], 'success', 0];
    }
    
    public function get_top_user($user_id){
        // 直属用户id
        $directly_user_id = Db::name('user_tier')->where('user_id', $user_id)->value('pid');
        $directly_user_id = $directly_user_id ? $directly_user_id : 0;
        
        // 直属上级用户id
        $directly_supervisor_user_id = Db::name('user_tier')->where('user_id', $directly_user_id)->value('pid');
        $directly_supervisor_user_id = $directly_supervisor_user_id ? $directly_supervisor_user_id : 0;
        
        // 团队会员等级
        $super_expire_time = Db::name('user_promotion')->where([['user_id', 'in', [$user_id, $directly_user_id, $directly_supervisor_user_id]]])->column('expire_time', 'user_id');
        
        $team_level['user_level'] = isset($super_expire_time[$user_id]) && $super_expire_time[$user_id] > request()->time() ? 1 : 0;
        $team_level['directly_level'] = isset($super_expire_time[$directly_user_id]) && $super_expire_time[$directly_user_id] > request()->time() ? 1 : 0;
        $team_level['directly_supervisor_level'] = isset($super_expire_time[$directly_supervisor_user_id]) && $super_expire_time[$directly_supervisor_user_id] > request()->time() ? 1 : 0;
        
        return [
            'user' => [
                'user_id' => $user_id,
                'directly_user_id' => $directly_user_id,
                'directly_supervisor_user_id' => $directly_supervisor_user_id
            ],
            'level' => $team_level
        ];
    }
    
    /**
     * 计算佣金
     * @param unknown $commission
     * @param unknown $team_level
     * @return multitype:number unknown
     *
    public function calc_commission($commission, $team_level){
        $commission_reward = [];
        
        // 返佣金
        $commission_reward['level_1'] = $commission;
        if ($team_level['user_level'] == 1) {
            $commission_reward['level_1'] += $commission * 0.6;
        }
        
        // 上级
        $commission_reward['level_2'] = 0;
        if ($team_level['user_level'] == 0) {
            if ($team_level['directly_level'] == 1) {
                $commission_reward['level_2'] = $commission * 0.6;
            } else {
                $commission_reward['level_2'] = $commission * 0.2;
            }
        } else {
            if ($team_level['directly_level'] == 1) {
                $commission_reward['level_2'] = $commission * 0.06;
            }
        }
        
        // 上上级
        $commission_reward['level_3'] = 0;
        if ($team_level['user_level'] == 0) {
            if ($team_level['directly_supervisor_level'] == 1) {
                $commission_reward['level_3'] += $commission * 0.4;
            }
        }
        if ($team_level['directly_level'] == 1 && $team_level['directly_supervisor_level'] == 1) {
            $commission_reward['level_3'] += $commission * 0.06;
        }
        
        return $commission_reward;
    }
    */
    
    /**
     * 计算佣金
     * @param unknown $commission
     * @param unknown $team_level
     * @return multitype:number unknown
     */
    public function calc_commission($commission, $team_level, $commission_rule){
        $commission_reward = [];
    
        // 返佣金
        $commission_rate_total = 0;
        foreach ($team_level as $key=>&$val) {
            // type 1 自推订单 2 团队订单
            if (!isset($val['type'])) {
                $val['type'] = 2;
            }
            
            if ($key == 0) {
                // 自购或分享获得佣金比例
                $val['commission_rate'] = $commission_rule[$val['level']]['self_buying'];
            } elseif ($key == 1 && $val['level'] == $team_level[0]['level']) {
                // 推荐用户获得佣金比例
                $val['commission_rate'] = $commission_rule[$val['level']]['recommend'];
            } else {
                // 团队获得佣金比例
                $val['commission_rate'] = $commission_rule[$val['level']]['self_buying'] - $commission_rate_total;
            }
            
            if ($val['commission_rate'] > 0) {
                // 计算佣金金额
                $val['commission'] = round($commission * $val['commission_rate']);
                if ($val['commission'] < 1) {
                    $val['commission'] = 1;
                }
                // 计算总返佣比例
                $commission_rate_total += $val['commission_rate'];
            } else {
                unset($team_level[$key]);
            }
        }

        return $team_level;
    }
    
    public function bole_commission($user_id, $user_level, $commission)
    {
        // 查询伯乐团队
        $team_data = []; // 团队数据
        $step_count = 1;
        
        $map[] = [
            ['ut.user_id', '=', $user_id],
            ['ut.pid', '<>', $user_id],
            ['ul.level', '=', $user_level]
        ];
        
        while (1) {
            $data = Db::name('user_tier')
            ->alias('ut')
            ->join('user_level ul', 'ul.user_id = ut.pid', 'LEFT')
            ->field('pid as user_id, ul.level')
            ->where($map)
            ->find();
            if ($data) {
                // 超过3级，结束查询
                if ($step_count > 3) {
                    break;
                }
                
                if ($user_level == $data['level']) {
                    // 添加到伯乐团队
                    $team_data[] = $data;
                } else {
                    // 等级不一样，结束查询
                    break;
                }
        
                $step_count++;
                $map[0][0] = ['ut.user_id', '=', $data['user_id']];
            } else {
                break;
            }
        }
        
        // 佣金计算
        switch ($user_level) {
            case 2:
                $commission_rate = 0.1;
                break;
            case 3:
                $commission_rate = 0.06;
                break;
            default:
                $commission_rate = 0.01;
                break;
        }
        
        // 伯乐团队返佣
        foreach ($team_data as $key=>&$val) {
            $val['type'] = 2;
            $val['commission_rate'] = 0;
            $val['commission'] = round($commission * $commission_rate);
            $commission = $val['commission'];
            if ($val['commission'] < 1) {
                $val['commission'] = 1;
            }
        }
        
        return $team_data;
    }
    
    public function add_order($data, $team_data)
    {
        $add_data = [
            'order_no' => $data['order_sn'],
            'add_time' => $data['order_create_time'],
            'modify_at_time' => $data['order_verify_time'] ?: 0,
            'order_amount' => $data['order_amount'],
            'valid_code' => $data['skuList'][0]['validCode'],
            'status' => $data['order_status'],
            'platform_rebeat' => $data['promotion_rate'],
            'estimate_commission' => $data['promotion_amount'],
            'platform_commission' => $data['promotion_amount'],
            'p_id' => $data['p_id'],
            'type' => 1,
            'good_id' => ''.$data['goods_id'],
            'goods_name' => $data['goods_name'],
            'img' => $data['goods_thumbnail_url'],
            'good_price' => $data['goods_price'],
            'buy_user_id' => $data['buy_user_id']
        ];
        
        $order_id = Db::name('order_promotion')->insertGetId($add_data);
        
        if ($data['buy_user_id']) {
            // 用户昵称
            $pay_nickname = Db::name('user')->where('id', $data['buy_user_id'])->value('nickname');
            
            // 佣金发放记录
            foreach ($team_data as &$val) {
                $val['order_id'] = $order_id;
                $val['commission_rate'] *= 100;
                
                // 添加系统通知
                if ($val['type'] == 2) {
                    db('user_dynamic')->insert([
                        'user_id' => 0,
                        'nickname' => '微选生活',
                        'avator' => '/static/img/logo.png',
                        'receive_uid' => $val['user_id'],
                        'event' => 'order_commission',
                        'event_id' => $order_id,
                        'data' => serialize([
                            'title' => '订单收入通知  奖励 '. round($val['commission'] / 100, 2) .'元',
                            'content' => '成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. date('Y-m-d') .'] 出单成功，您获得奖励'. round($val['commission'] / 100, 2) .'元'
                        ]),
                        'status' => 1,
                        'add_time' => request()->time()
                    ]);
                    
                    db('xinge_task')->insert([
                        'user_id' => $val['user_id'],
                        'event' => 'order_commission',
                        'data' => serialize([
                            'title' => '订单收入通知  奖励 '. round($val['commission'] / 100, 2) .'元',
                            'content' => '成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. date('Y-m-d') .'] 出单成功，您获得奖励'. round($val['commission'] / 100, 2) .'元'
                        ])
                    ]);
                }
            }
            Db::name('order_promotion_commission')->insertAll($team_data);
        }
        
        return ['id' => $order_id];
    }
    
    public function add_order_jd($data, $team_data)
    {
        $goods_img = Db::name('goods_jd')->where('sku_id', '=', ''.$data['skuList'][0]['skuId'])->value('img');

        $add_data = [
            'order_no' => $data['orderId'],
            'add_time' => $data['orderTime'],
            'modify_at_time' => $data['finishTime'] ?: 0,
            'order_amount' => $data['skuList'][0]['estimateCosPrice'] * 100,
            'valid_code' => $data['skuList'][0]['validCode'],
            'status' => $data['order_status'],
            'platform_rebeat' => $data['skuList'][0]['commissionRate'] * 10,
            'estimate_commission' => $data['skuList'][0]['estimateCommission'],
            'platform_commission' => $data['skuList'][0]['actualCommission'] * 100,
            'p_id' => $data['skuList'][0]['subUnionId'],
            'type' => 2,
            'good_id' => ''.$data['skuList'][0]['skuId'],
            'goods_name' => $data['skuList'][0]['skuName'],
            'img' => $goods_img ?: '',
            'good_price' => $data['skuList'][0]['price'] * 100,
            'buy_user_id' => $data['buy_user_id']
        ];
        $order_id = Db::name('order_promotion')->insertGetId($add_data);
        
        if ($data['buy_user_id']) {
            // 用户昵称
            $pay_nickname = Db::name('user')->where('id', $data['buy_user_id'])->value('nickname');
            
            // 佣金发放记录
            foreach ($team_data as &$val) {
                $val['order_id'] = $order_id;
                $val['commission_rate'] *= 100;
                
                // 添加系统通知
                if ($val['type'] == 2) {
                    db('user_dynamic')->insert([
                        'user_id' => 0,
                        'nickname' => '微选生活',
                        'avator' => '/static/img/logo.png',
                        'receive_uid' => $val['user_id'],
                        'event' => 'order_commission',
                        'event_id' => $order_id,
                        'data' => serialize([
                            'title' => '订单收入通知  奖励 '. round($val['commission'] / 100, 2) .'元',
                            'content' => '成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. date('Y-m-d') .'] 出单成功，您获得奖励'. round($val['commission'] / 100, 2) .'元'
                        ]),
                        'status' => 1,
                        'add_time' => request()->time()
                    ]);
                
                    db('xinge_task')->insert([
                        'user_id' => $val['user_id'],
                        'event' => 'order_commission',
                        'data' => serialize([
                            'title' => '订单收入通知  奖励 '. round($val['commission'] / 100, 2) .'元',
                            'content' => '成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. date('Y-m-d') .'] 出单成功，您获得奖励'. round($val['commission'] / 100, 2) .'元'
                        ])
                    ]);
                }
            }
            Db::name('order_promotion_commission')->insertAll($team_data);
        }
        
        return ['id' => $order_id];
    }
    
    /**
     * 分钟数 转 h:i
     * @param string $minute 分钟数
     * @return string
     */
    public static function format_minute($minute){
        $h = intval($minute / 60) + 5;
        $m = intval($minute % 60);
        if ($m < 10) {
            $m = '0' . $m;
        }
    
        return $h . ':' . $m;
    }
    
    public static function income($user_id)
    {
        $data = [
            'today_estimate_income_pdd' => 0,
            'today_order_pdd' => 0,
            'yesterday_income_pdd' => 0,
            'yesterday_order_pdd' => 0,
            'last_month_income_pdd' => 0,
            'last_month_order_pdd' => 0,
            'this_month_income_pdd' => 0,
            'this_month_order_pdd' => 0,
            'all_income_pdd' => 0,
            
            'today_estimate_income_jd' => 0,
            'today_order_jd' => 0,
            'yesterday_income_jd' => 0,
            'yesterday_order_jd' => 0,
            'last_month_income_jd' => 0,
            'last_month_order_jd' => 0,
            'this_month_income_jd' => 0,
            'this_month_order_jd' => 0,
            'all_income_jd' => 0,
            
            'all_income' => 0,
            'all_order' => 0
        ];
        
        // 拼多多订单数据
        $forecast_income_cache_key = 'statement_income_'. $user_id;
        $forecast_income = Cache::get($forecast_income_cache_key);
        if ($forecast_income) {
            //return $forecast_income;
        }
        
        $today_time       = Common::today_time(); // 今天时间戳
        $last_month_start = strtotime(date('Y-m-01', strtotime('-1 month', strtotime(date('Y-m'))))); // 上月时间戳开始
        $last_month_end   = strtotime(date('Y-m-t 23:59:59', strtotime('-1 month', strtotime(date('Y-m'))))); // 上月时间戳结束
        $now_month_start  = strtotime(date('Y-m-01')); // 本月时间戳开始
        $now_month_end    = strtotime(date('Y-m-t 23:59:59')); // 本月时间戳结束

        // 是否假订单
        $order_database_name = 'order';
        if ($user_id == '2112') {
            $order_database_name = 'order_fake';
        }
        
        $order = db($order_database_name)
        ->field('add_time, type, user_id, directly_user_id, directly_supervisor_user_id, user_commission, directly_user_commission, directly_supervisor_user_commission')
        ->where([
            ['user_id|directly_user_id|directly_supervisor_user_id', '=', $user_id],
            ['status', '<>', 3]
        ])->select();

        foreach ($order as $val) {
            $data['all_order']++;
            $income_variable_name_day = '';
            $income_variable_name_month = '';
            switch ($val['type']) {
                case 1:
                    if ($val['add_time'] >= $today_time && $val['add_time'] < $today_time + 86400) {
                        // 今日
                        $income_variable_name_day = 'today_estimate_income_pdd';
                        // 今日订单数
                        $data['today_order_pdd']++;
                    } else if ($val['add_time'] >= $today_time - 86400 && $val['add_time'] < $today_time) {
                        // 昨日
                        $income_variable_name_day = 'yesterday_income_pdd';
                        // 昨日日订单数
                        $data['yesterday_order_pdd']++;
                    } else {
                    
                    }
                    if ($val['add_time'] >= $last_month_start && $val['add_time'] < $last_month_end) {
                        // 上月
                        $income_variable_name_month = 'last_month_income_pdd';
                        // 上月订单数
                        $data['last_month_order_pdd']++;
                    } else if ($val['add_time'] >= $now_month_start && $val['add_time'] <= $now_month_end) {
                        // 本月
                        $income_variable_name_month = 'this_month_income_pdd';
                        // 本月订单数
                        $data['this_month_order_pdd']++;
                    } else {
                    
                    }
                    break;
                default:
                    if ($val['add_time'] >= $today_time && $val['add_time'] < $today_time + 86400) {
                        // 今日
                        $income_variable_name_day = 'today_estimate_income_jd';
                        // 今日订单数
                        $data['today_order_jd']++;
                    } else if ($val['add_time'] >= $today_time - 86400 && $val['add_time'] < $today_time) {
                        // 昨日
                        $income_variable_name_day = 'yesterday_income_jd';
                        // 昨日日订单数
                        $data['yesterday_order_jd']++;
                    } else {
                    
                    }
                    if ($val['add_time'] >= $last_month_start && $val['add_time'] < $last_month_end) {
                        // 上月
                        $income_variable_name_month = 'last_month_income_jd';
                        // 上月订单数
                        $data['last_month_order_jd']++;
                    } else if ($val['add_time'] >= $now_month_start && $val['add_time'] <= $now_month_end) {
                        // 本月
                        $income_variable_name_month = 'this_month_income_jd';
                        // 本月订单数
                        $data['this_month_order_jd']++;
                    } else {
                    
                    }
                    break;
            }
            
        
            // 订单佣金统计
            if ($user_id == $val['user_id']) {
                if ($income_variable_name_day) {
                    $data[$income_variable_name_day] += $val['user_commission'];
                }
                if ($income_variable_name_month) {
                    $data[$income_variable_name_month] += $val['user_commission'];
                }
                // 京东/拼多多 一级订单佣金
                switch ($val['type']) {
                    case 1:
                        $data['all_income_pdd'] += $val['user_commission'];
                        break;
                    default:
                        $data['all_income_jd'] += $val['user_commission'];
                        break;
                }
                $data['all_income'] += $val['user_commission'];
            } elseif ($user_id == $val['directly_user_id']) {
                if ($income_variable_name_day) {
                    $data[$income_variable_name_day] += $val['directly_user_commission'];
                }
                if ($income_variable_name_month) {
                    $data[$income_variable_name_month] += $val['directly_user_commission'];
                }
                // 京东/拼多多 下级订单佣金
                switch ($val['type']) {
                    case 1:
                        $data['all_income_pdd'] += $val['directly_user_commission'];
                        break;
                    default:
                        $data['all_income_jd'] += $val['directly_user_commission'];
                        break;
                }
                $data['all_income'] += $val['directly_user_commission'];
            } else {
                if ($income_variable_name_day) {
                    $data[$income_variable_name_day] += $val['directly_supervisor_user_commission'];
                }
                if ($income_variable_name_month) {
                    $data[$income_variable_name_month] += $val['directly_supervisor_user_commission'];
                }
                // 京东/拼多多 下下级订单佣金
                switch ($val['type']) {
                    case 1:
                        $data['all_income_pdd'] += $val['directly_user_commission'];
                        break;
                    default:
                        $data['all_income_jd'] += $val['directly_user_commission'];
                        break;
                }
                $data['all_income'] += $val['directly_supervisor_user_commission'];
            }
        }
        
        // 免单金额
        $free_order = db()->query('select if(order_amount>500,500,order_amount) as free_amount_sum, add_time, type from sl_'. $order_database_name .' where add_time > 1534163213 and add_time < 1543075200 and `status` <> 3 and user_id in (select user_id from sl_user_tier where pid = '. $user_id .') GROUP BY user_id');
        if ($free_order) {
            foreach ($free_order as $val) {
                $data['all_income'] += $val['free_amount_sum'];
                switch ($val['type']) {
                    case 1:
                        $data['all_income_pdd'] += $val['free_amount_sum'];
                        if ($val['add_time'] >= $today_time && $val['add_time'] < $today_time + 86400) {
                            // 今日
                            $data['today_estimate_income_pdd'] += $val['free_amount_sum'];
                        } else if ($val['add_time'] >= $today_time - 86400 && $val['add_time'] < $today_time) {
                            // 昨日
                            $data['yesterday_income_pdd'] += $val['free_amount_sum'];
                        }
                        if ($val['add_time'] >= $last_month_start && $val['add_time'] < $last_month_end) {
                            // 上月
                            $data['last_month_income_pdd'] += $val['free_amount_sum'];
                        } else if ($val['add_time'] >= $now_month_start && $val['add_time'] <= $now_month_end) {
                            // 本月
                            $data['this_month_income_pdd'] += $val['free_amount_sum'];
                        }
                        break;
                    default:
                        $data['all_income_jd'] += $val['free_amount_sum'];
                        if ($val['add_time'] >= $today_time && $val['add_time'] < $today_time + 86400) {
                            // 今日
                            $data['today_estimate_income_jd'] += $val['free_amount_sum'];
                        } else if ($val['add_time'] >= $today_time - 86400 && $val['add_time'] < $today_time) {
                            // 昨日
                            $data['yesterday_income_jd'] += $val['free_amount_sum'];
                        }
                        if ($val['add_time'] >= $last_month_start && $val['add_time'] < $last_month_end) {
                            // 上月
                            $data['last_month_income_jd'] += $val['free_amount_sum'];
                        } else if ($val['add_time'] >= $now_month_start && $val['add_time'] <= $now_month_end) {
                            // 本月
                            $data['this_month_income_jd'] += $val['free_amount_sum'];
                        }
                        break;
                }
            }
        }
        
        // 晋升津贴
        $dredge_eduities_income = db('user_dredge_eduities')
        ->field('SUM(CASE WHEN add_time >= '. $today_time .' AND add_time < '. ($today_time + 86400) .' THEN superior_award ELSE 0 END) AS today_dredge_eduities_income,
                SUM(CASE WHEN add_time >= '. ($today_time - 86400) .' AND add_time < '. $today_time .' THEN superior_award ELSE 0 END) AS yesterday_dredge_eduities_income,
                SUM(CASE WHEN add_time >= '. $now_month_start .' AND add_time <= '. $now_month_end .' THEN superior_award ELSE 0 END) AS this_month_dredge_eduities_income,
                SUM(CASE WHEN add_time >= '. $last_month_start .' AND add_time < '. $last_month_end .' THEN superior_award ELSE 0 END) AS last_month_dredge_eduities_income,
                SUM(superior_award) AS total_dredge_eduities_income')
        ->where([
            ['pid', '=', $user_id]
        ])->find();

        // 拉新补贴
        $active_order_data = db()->query('select add_time, type from sl_order where status != 3 and id in (select order_id from sl_active_order where is_partner = 1 and invite_user_id = '. $user_id .')');
        foreach ($active_order_data as $val) {
            $data['all_income'] += 500;
            switch ($val['type']) {
                case 1:
                    $data['all_income_pdd'] += 500;
                    if ($val['add_time'] >= $today_time && $val['add_time'] < $today_time + 86400) {
                        // 今日
                        $data['today_estimate_income_pdd'] += 500;
                    } else if ($val['add_time'] >= $today_time - 86400 && $val['add_time'] < $today_time) {
                        // 昨日
                        $data['yesterday_income_pdd'] += 500;
                    }
                    if ($val['add_time'] >= $last_month_start && $val['add_time'] < $last_month_end) {
                        // 上月
                        $data['last_month_income_pdd'] += 500;
                    } else if ($val['add_time'] >= $now_month_start && $val['add_time'] <= $now_month_end) {
                        // 本月
                        $data['this_month_income_pdd'] += 500;
                    }
                    break;
                default:
                    $data['all_income_jd'] += 500;
                    if ($val['add_time'] >= $today_time && $val['add_time'] < $today_time + 86400) {
                        // 今日
                        $data['today_estimate_income_jd'] += 500;
                    } else if ($val['add_time'] >= $today_time - 86400 && $val['add_time'] < $today_time) {
                        // 昨日
                        $data['yesterday_income_jd'] += 500;
                    }
                    if ($val['add_time'] >= $last_month_start && $val['add_time'] < $last_month_end) {
                        // 上月
                        $data['last_month_income_jd'] += 500;
                    } else if ($val['add_time'] >= $now_month_start && $val['add_time'] <= $now_month_end) {
                        // 本月
                        $data['this_month_income_jd'] += 500;
                    }
                    break;
            }
        }
        
        // 总收益 = 晋升津贴收益 + 拼多多收益 + 京东收益 + 拉新补贴
        $data['today_estimate_income'] = $dredge_eduities_income['today_dredge_eduities_income'] + $data['today_estimate_income_pdd'] + $data['today_estimate_income_jd'];
        $data['yesterday_income']      = $dredge_eduities_income['yesterday_dredge_eduities_income'] + $data['yesterday_income_pdd'] + $data['yesterday_income_jd'];
        $data['last_month_income']     = $dredge_eduities_income['last_month_dredge_eduities_income'] + $data['last_month_income_pdd'] + $data['last_month_income_jd'];
        $data['this_month_income']     = $dredge_eduities_income['this_month_dredge_eduities_income'] + $data['this_month_income_pdd'] + $data['this_month_income_jd'];
        $data['all_income']            += $dredge_eduities_income['total_dredge_eduities_income'];

        // 拼多多收入
        $data['today_estimate_income_pdd'] = round($data['today_estimate_income_pdd'] / 100, 2);
        $data['yesterday_income_pdd'] = round($data['yesterday_income_pdd'] / 100, 2);
        $data['last_month_income_pdd'] = round($data['last_month_income_pdd'] / 100, 2);
        $data['this_month_income_pdd'] = round($data['this_month_income_pdd'] / 100, 2);
        $data['all_income_pdd'] = round($data['all_income_pdd'] / 100, 2);

        // 京东收入
        $data['today_estimate_income_jd'] = round($data['today_estimate_income_jd'] / 100, 2);
        $data['yesterday_income_jd'] = round($data['yesterday_income_jd'] / 100, 2);
        $data['last_month_income_jd'] = round($data['last_month_income_jd'] / 100, 2);
        $data['this_month_income_jd'] = round($data['this_month_income_jd'] / 100, 2);
        $data['all_income_jd'] = round($data['all_income_jd'] / 100, 2);

        // 总收入
        $data['today_estimate_income'] = round($data['today_estimate_income'] / 100, 2);
        $data['yesterday_income']      = round($data['yesterday_income'] / 100, 2);
        $data['last_month_income']     = round($data['last_month_income'] / 100, 2);
        $data['this_month_income']     = round($data['this_month_income'] / 100, 2);
        $data['all_income']            = round($data['all_income'] / 100, 2);
        
        Cache::set($forecast_income_cache_key, $data);
        return $data;
    }
    
    public static function income_marketing($user_id)
    {
        $data = [
            'today_estimate_income_pdd' => 0,
            'today_order_pdd' => 0,
            'yesterday_income_pdd' => 0,
            'yesterday_order_pdd' => 0,
            'last_month_income_pdd' => 0,
            'last_month_order_pdd' => 0,
            'this_month_income_pdd' => 0,
            'this_month_order_pdd' => 0,
            'all_income_pdd' => 0,
        
            'today_estimate_income_jd' => 0,
            'today_order_jd' => 0,
            'yesterday_income_jd' => 0,
            'yesterday_order_jd' => 0,
            'last_month_income_jd' => 0,
            'last_month_order_jd' => 0,
            'this_month_income_jd' => 0,
            'this_month_order_jd' => 0,
            'all_income_jd' => 0,
        
            'all_income' => 0,
            'all_order' => 0
        ];
        
        // 拼多多订单数据
        $forecast_income_cache_key = 'statement_income_marketing_'. $user_id;
        $forecast_income = Cache::get($forecast_income_cache_key);
        if ($forecast_income) {
            //return $forecast_income;
        }
        
        $today_time       = Common::today_time(); // 今天时间戳
        $last_month_start = strtotime(date('Y-m-01', strtotime('-1 month', strtotime(date('Y-m'))))); // 上月时间戳开始
        $last_month_end   = strtotime(date('Y-m-t 23:59:59', strtotime('-1 month', strtotime(date('Y-m'))))); // 上月时间戳结束
        $now_month_start  = strtotime(date('Y-m-01')); // 本月时间戳开始
        $now_month_end    = strtotime(date('Y-m-t 23:59:59')); // 本月时间戳结束
        
        $order = db('order_promotion_commission')
        ->alias('opc')
        ->join('order_promotion op', 'op.id = opc.order_id', 'LEFT')
        ->field('op.add_time, op.type, opc.user_id, opc.commission')
        ->where([
            ['opc.user_id', '=', $user_id],
            ['op.status', '<>', 3]
        ])->select();
        
        foreach ($order as $val) {
            $data['all_order']++;
            $data['all_income'] += $val['commission'];
            switch ($val['type']) {
                case 1:
                    $data['all_income_pdd'] += $val['commission'];
                    if ($val['add_time'] >= $today_time && $val['add_time'] < $today_time + 86400) {
                        // 今日
                        $data['today_estimate_income_pdd'] += $val['commission'];
                        // 今日订单数
                        $data['today_order_pdd']++;
                    } else if ($val['add_time'] >= $today_time - 86400 && $val['add_time'] < $today_time) {
                        // 昨日
                        $data['yesterday_income_pdd'] += $val['commission'];
                        // 昨日日订单数
                        $data['yesterday_order_pdd']++;
                    } else {
        
                    }
                    if ($val['add_time'] >= $last_month_start && $val['add_time'] < $last_month_end) {
                        // 上月
                        $data['last_month_income_pdd'] += $val['commission'];
                        // 上月订单数
                        $data['last_month_order_pdd']++;
                    } else if ($val['add_time'] >= $now_month_start && $val['add_time'] <= $now_month_end) {
                        // 本月
                        $data['this_month_income_pdd'] += $val['commission'];
                        // 本月订单数
                        $data['this_month_order_pdd']++;
                    } else {
        
                    }
                    break;
                default:
                    $data['all_income_jd'] += $val['commission'];
                    if ($val['add_time'] >= $today_time && $val['add_time'] < $today_time + 86400) {
                        // 今日
                        $data['today_estimate_income_jd'] += $val['commission'];
                        // 今日订单数
                        $data['today_order_jd']++;
                    } else if ($val['add_time'] >= $today_time - 86400 && $val['add_time'] < $today_time) {
                        // 昨日
                        $data['yesterday_income_jd'] += $val['commission'];
                        // 昨日日订单数
                        $data['yesterday_order_jd']++;
                    } else {
        
                    }
                    if ($val['add_time'] >= $last_month_start && $val['add_time'] < $last_month_end) {
                        // 上月
                        $data['last_month_income_jd'] += $val['commission'];
                        // 上月订单数
                        $data['last_month_order_jd']++;
                    } else if ($val['add_time'] >= $now_month_start && $val['add_time'] <= $now_month_end) {
                        // 本月
                        $data['this_month_income_jd'] += $val['commission'];
                        // 本月订单数
                        $data['this_month_order_jd']++;
                    } else {
        
                    }
                    break;
            }
        }
        
        // 晋升津贴
        $dredge_eduities_income = db('user_level_promote_award')
        ->field('SUM(CASE WHEN add_time >= '. $today_time .' AND add_time < '. ($today_time + 86400) .' THEN award ELSE 0 END) AS today_dredge_eduities_income,
                SUM(CASE WHEN add_time >= '. ($today_time - 86400) .' AND add_time < '. $today_time .' THEN award ELSE 0 END) AS yesterday_dredge_eduities_income,
                SUM(CASE WHEN add_time >= '. $now_month_start .' AND add_time <= '. $now_month_end .' THEN award ELSE 0 END) AS this_month_dredge_eduities_income,
                SUM(CASE WHEN add_time >= '. $last_month_start .' AND add_time < '. $last_month_end .' THEN award ELSE 0 END) AS last_month_dredge_eduities_income,
                SUM(award) AS total_dredge_eduities_income')
        ->where([
            ['user_id', '=', $user_id]
        ])->find();

        // 返688现金
        $return688_cash = db('active_return688_task')
        ->field('SUM(CASE WHEN add_time >= '. $today_time .' AND add_time < '. ($today_time + 86400) .' THEN price ELSE 0 END) AS today_return688_cash,
            SUM(CASE WHEN add_time >= '. ($today_time - 86400) .' AND add_time < '. $today_time .' THEN price ELSE 0 END) AS yesterday_return688_cash,
            SUM(CASE WHEN add_time >= '. $now_month_start .' AND add_time <= '. $now_month_end .' THEN price ELSE 0 END) AS this_month_return688_cash,
            SUM(CASE WHEN add_time >= '. $last_month_start .' AND add_time < '. $last_month_end .' THEN price ELSE 0 END) AS last_month_return688_cash,
            SUM(price) AS total_return688_cash')
        ->where([
            ['user_id', '=', $user_id]
        ])->find();

        // 总收益 = 晋升津贴收益 + 拼多多收益 + 京东收益 + 返688现金
        $data['today_estimate_income'] = $dredge_eduities_income['today_dredge_eduities_income'] + $data['today_estimate_income_pdd'] + $data['today_estimate_income_jd'] + $return688_cash['today_return688_cash'] * 100;
        $data['yesterday_income']      = $dredge_eduities_income['yesterday_dredge_eduities_income'] + $data['yesterday_income_pdd'] + $data['yesterday_income_jd'] + $return688_cash['yesterday_return688_cash'] * 100;
        $data['last_month_income']     = $dredge_eduities_income['last_month_dredge_eduities_income'] + $data['last_month_income_pdd'] + $data['last_month_income_jd'] + $return688_cash['last_month_return688_cash'] * 100;
        $data['this_month_income']     = $dredge_eduities_income['this_month_dredge_eduities_income'] + $data['this_month_income_pdd'] + $data['this_month_income_jd'] + $return688_cash['this_month_return688_cash'] * 100;
        $data['all_income']            += $dredge_eduities_income['total_dredge_eduities_income'] + $return688_cash['today_return688_cash'] * 100;
        
        // 拼多多收入
        $data['today_estimate_income_pdd'] = round($data['today_estimate_income_pdd'] / 100, 2);
        $data['yesterday_income_pdd'] = round($data['yesterday_income_pdd'] / 100, 2);
        $data['last_month_income_pdd'] = round($data['last_month_income_pdd'] / 100, 2);
        $data['this_month_income_pdd'] = round($data['this_month_income_pdd'] / 100, 2);
        $data['all_income_pdd'] = round($data['all_income_pdd'] / 100, 2);
        
        // 京东收入
        $data['today_estimate_income_jd'] = round($data['today_estimate_income_jd'] / 100, 2);
        $data['yesterday_income_jd'] = round($data['yesterday_income_jd'] / 100, 2);
        $data['last_month_income_jd'] = round($data['last_month_income_jd'] / 100, 2);
        $data['this_month_income_jd'] = round($data['this_month_income_jd'] / 100, 2);
        $data['all_income_jd'] = round($data['all_income_jd'] / 100, 2);
        
        // 总收入
        $data['today_estimate_income'] = round($data['today_estimate_income'] / 100, 2);
        $data['yesterday_income']      = round($data['yesterday_income'] / 100, 2);
        $data['last_month_income']     = round($data['last_month_income'] / 100, 2);
        $data['this_month_income']     = round($data['this_month_income'] / 100, 2);
        $data['all_income']            = round($data['all_income'] / 100, 2);
        
        $old_income = self::income($user_id);
        foreach ($old_income as $item_income_key=>$item_income_val) {
            $data[$item_income_key] += $item_income_val;
        }

        Cache::set($forecast_income_cache_key, $data, $today_time + 86400 - request()->time());
        
        return $data;
    }
}
