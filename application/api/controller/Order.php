<?php
namespace app\api\controller;

use think\facade\Request;
use think\Db;
use app\common\model\Order as OrderModel;
use app\common\model\Common;
use think\facade\Cache;
use app\common\model\GoodsModel;
use app\common\model\User;
use app\common\model\OrderFake;

class Order extends Base
{
    // 订单列表
    public function lists()
    {
        $type = Request::get('type', 0);
        $status = Request::get('status', 0);
        $next_id = Request::get('next_id', 0);
        $page_size = Request::get('page_size', 60);
        $order_type = Request::get('order_type', 1);
        
        $map = [];
        $map[] = ['og.type', '=', 2];
        $map[] = ['o.type', '=', $order_type];
        if ($type == 1) {
            $map[] = ['directly_user_id|directly_supervisor_user_id', '=', $this->user_id];
            
            $field_append = ', o.directly_user_id, o.directly_user_commission, o.directly_supervisor_user_commission';
        } else {
            $map[] = ['user_id', '=', $this->user_id];
            
            $field_append = ', o.user_commission';
        }
        
        if ($status) {
            switch ($status) {
                case 2:
                    $map[] = ['status', 'in', [2,4,6]];
                    break;
                default:
                    $map[] = ['status', '=', $status];
                    break;
            }
            
        }
        
        if ($next_id) {
            $map[] = ['o.id', '<', $next_id];
        }
        
        // 订单列表
        $order_database_name = OrderModel::where($map);
        if ($this->user_id == '2112') {
            $order_database_name = OrderFake::where($map);
        }
        
        $order_data = $order_database_name
        ->alias('o')
        ->with(['orderuser'=>function($query){
            $query->field('id, nickname, avator');
        }])
        ->field('o.id, o.add_time, o.directly_user_id, o.user_id, o.status, o.platform_commission, o.order_amount, og.good_name, og.img' . $field_append)
        ->join('sl_order_goods og', 'o.id = og.order_id', 'LEFT')
        ->order('o.id', 'DESC')
        ->limit($page_size)
        ->select();
        if ($order_data) {
            $order_data = $order_data->toArray();
        }
        
        foreach ($order_data as &$val) {
            $val['add_time'] = date('Y-m-d H:i:s', $val['add_time']);
            if (!empty($val['orderuser']['avator']) && strpos($val['orderuser']['avator'], 'http://') === false) {
                $val['orderuser']['avator'] = config('site.url') . $val['orderuser']['avator'];
            }
            
            switch ($val['status']) {
                case 0:
                    $val['status_desc'] = '未付款';
                    break;
                case 1:
                    $val['status_desc'] = '已付款';
                    break;
                case 3:
                    $val['status_desc'] = '已失效';
                    break;
                case 5:
                    $val['status_desc'] = '已结算';
                    break;
                default:
                    $val['status_desc'] = '已收货';
                    break;
            }
            
            $val['free_status'] = -1;
            $val['free_amount'] = 0;
            if ($type == 1) {
                if ($val['directly_user_id'] == $this->user_id) {
                    $val['commission'] = $val['directly_user_commission'];
                    unset($val['directly_user_commission']);
                    unset($val['directly_user_id']);
                    
                    // 免单显示
                    $rs = Db::name('order_free')->field('status, free_amount')->where('order_id', '=', $val['id'])->find();
                    if ($rs) {
                        $val['free_status'] = $rs['status'];
                        $val['free_amount'] = round($rs['free_amount'] / 100, 2);
                    } else {
                        $rs = Db::name('order')->field('id')->where([
                            ['user_id', '=', $val['user_id']],
                            ['status', '<>', 3],
                            ['add_time', '>', 1534163213]
                        ])->find();
                        if ($rs['id'] == $val['id']) {
                            $val['free_status'] = -2;
                            $val['free_amount'] = $val['order_amount'] < 500 ? round($val['order_amount'] / 100, 2) : 5;
                        } else {
                            $val['free_status'] = -1;
                            $val['free_amount'] = 0;
                        }
                    }
                } else {
                    $val['commission'] = $val['directly_supervisor_user_commission'];
                    unset($val['directly_supervisor_user_commission']);
                }
            } else {
                $val['commission'] = $val['user_commission'];
                unset($val['user_commission']);
            }
            
            $val['commission'] = round($val['commission'] / 100, 2);
            $val['order_amount'] = round($val['order_amount'] / 100, 2);
        }
        
        $count = count($order_data);
        if ($count > 0) {
            $next_id = $order_data[$count - 1]['id'];
        } else {
            $next_id = 0;
        }
        
        return $this->response(['order_data' => $order_data, 'next_id' => $next_id]);
    }
    
    // 我的团队 - 报表数据
    public function statement()
    {
        // 获取缓存
        $today_time = $sel_time = Common::today_time();
        $cache_key = 'user_data_'. $this->user_id;
        $user_data_cache = Cache::get($cache_key);
        $order_report_cache = $user_data_cache['order_report'];
        if ($order_report_cache) {
            $sel_count = ($today_time - $order_report_cache['update_time']) / 86400;
            if ($sel_count > 1) {
                array_splice($order_report_cache['data'], -$sel_count);
            }
            array_splice($order_report_cache['data'], 0, 1);
            $sel_count++;
        } else {
            $sel_count = 7;
            $order_report_cache['data'] = [];
        }
        
        // 报表数据
        $order_report = [];
        $free_order = db()->query('select if(order_amount>500,500,order_amount) as free_amount_sum, add_time from sl_order where add_time > 1534163213 and `status` <> 3 and user_id in (select user_id from sl_user_tier where pid = '. $this->user_id .') GROUP BY user_id');
        for ($i = 0; $i < $sel_count; $i++) {
            $order_report[$i]['date'] = date('Y-m-d', $sel_time);
            
            // 新直属会员
            $directly_user_ids = Db::name('user_tier')->where([
                ['pid', '=', $this->user_id],
                ['add_time', 'BETWEEN', [$sel_time, $sel_time + 86400]]
            ])->column('user_id');
            
            $order_report[$i]['count_directly_user'] = count($directly_user_ids);
            
            // 新非直属会员
            $order_report[$i]['count_directly_supervisor_user'] = Db::name('user_tier')->where([
                ['pid', 'in', $all_directly_user_ids],
                ['add_time', 'BETWEEN', [$sel_time, $sel_time + 86400]]
            ])->count();
            
            // 新团队成员
            $order_report[$i]['count_team_user'] = $order_report[$i]['count_directly_user'] + $order_report[$i]['count_directly_supervisor_user'];
            
            // 新订单
            $order_report[$i]['count_buy'] = 0;
            $order_report[$i]['sum_income'] = 0;
            $new_order = Db::name('order')
            ->field('user_id, directly_user_id, directly_supervisor_user_id, user_commission, directly_user_commission, directly_supervisor_user_commission')
            ->where([
                ['user_id|directly_user_id|directly_supervisor_user_id', '=', $this->user_id],
                ['add_time', 'BETWEEN', [$sel_time, $sel_time + 86400]],
                ['status', '<>', 3]
            ])->select();
            
            foreach ($new_order as $val) {
                $order_report[$i]['count_buy']++;
                
                if ($this->user_id == $val['user_id']) {
                    $order_report[$i]['sum_income'] += $val['user_commission'];
                } elseif ($this->user_id == $val['directly_user_id']) {
                    $order_report[$i]['sum_income'] += $val['directly_user_commission'];
                } else {
                    $order_report[$i]['sum_income'] += $val['directly_supervisor_user_commission'];
                }
            }
            
            // 立体营销订单
            
            
            // 晋升津贴
            $order_report[$i]['sum_income'] += db('user_dredge_eduities')
            ->where([
                ['add_time', 'BETWEEN', [$sel_time, $sel_time + 86400]],
                ['pid', '=', $this->user_id]
            ])->sum('superior_award');
            
            if ($free_order) {
                foreach ($free_order as $val) {
                    if ($val['add_time'] >= $sel_time && $val['add_time'] < $sel_time + 86400) {
                        // 今日
                        $order_report[$i]['sum_income'] += $val['free_amount_sum'];
                    }
                }
            }
            
            $order_report[$i]['sum_income'] = sprintf('%.2f', $order_report[$i]['sum_income'] / 100);
            $sel_time -= 86400;
        }
        
        $order_report = array_merge($order_report, $order_report_cache['data']);
        $data['order_report'] = $order_report;
        
        $user_data_cache['order_report'] = [
            'data' => $order_report,
            'update_time' => $today_time
        ];
        Cache::set($cache_key, $user_data_cache);
        
        
        return $this->response(['result' => $data]);
    }
    
    public function statement_v2()
    {
        // 用户等级
        $user_level = Db::name('user_level')
        ->where([
            ['user_id', '=', $this->user_id],
            ['expier_time', '>', request()->time()]
        ])->value('level');
        
        $count_directly_user = 0;
        $count_directly_lower_user = 0;
        if ($user_level) {
            // 推荐市场
            $count_directly_user = db()->query('select count(*) as count from sl_user_level as ul, sl_user as u where ul.user_id = u.id and u.openid_app <> "" and `level` < '. $user_level .' and user_id in ( select user_id from sl_user_tier where pid = '. $this->user_id .' )')[0]['count'];
            
            // 招募市场
            $count_directly_lower_user = db()->query('select count(*) as count from sl_user_level as ul, sl_user as u where ul.user_id = u.id and u.openid_app <> "" and `level` < '. $user_level .' and user_id in ( select user_id from sl_user_tier where pid in ( select user_id from sl_user_tier where pid = '. $this->user_id .' ) )')[0]['count'];
        }

        $income_data = OrderModel::income_marketing($this->user_id);

        // 团队人数
        $count_directly_user_all = db()->query('select count(*) as count from sl_user_level where `level` < '. $user_level .' and user_id in ( select user_id from sl_user_tier where pid = '. $this->user_id .' )')[0]['count'];
        $count_directly_lower_user_all = db()->query('select count(*) as count from sl_user_level where `level` < '. $user_level .' and user_id in ( select user_id from sl_user_tier where pid in ( select user_id from sl_user_tier where pid = '. $this->user_id .' ) )')[0]['count'];
        $income_data['count_team_user'] = $count_directly_user_all + $count_directly_lower_user_all;
        // 直属会员人数
        $income_data['count_directly_user'] = $count_directly_user;
        // 超级会员人数
        $income_data['count_directly_lower_user'] = $count_directly_lower_user;
        
        $income_data['count_super_user'] = 0;
        
        if ($this->user_id == 2112) {
            $fake_data = Db::name('siteinfo')->where('key', '=', 'fake_data')->value('value');
            $fake_data = json_decode($fake_data, true);
            
            $income_data['count_team_user'] = $fake_data['count_team_user'];
            $income_data['count_directly_user'] = $fake_data['count_directly_user'];
            $income_data['count_super_user'] = $fake_data['count_super_user'];
            $income_data['all_order'] += 60000;
            
            $diff_time = intval((request()->time() - $fake_data['update_time']) / 7200);
            if ($diff_time > 0) {
                $income_data['count_team_user'] = $fake_data['count_team_user'] += mt_rand(40, 60);
                $income_data['count_directly_user'] = $fake_data['count_directly_user'] += mt_rand(10, 20);
                $income_data['count_super_user'] = $fake_data['count_super_user'] += mt_rand(1, 3);
                $fake_data['update_time'] = time();
                
                Db::name('siteinfo')->where('key', '=', 'fake_data')->update([
                    'value' => json_encode($fake_data)
                ]);
            }
        }
        
        return $this->response(['result' => [
            'income_data' => $income_data
        ]]);
    }
    
    // 我的订单 - 团队晋升
    public function team_promote(){
        $next_id = Request::get('next_id', 0);
        $page_size = Request::get('page_size', 60);
     
        $map = [
            ['a.pid', '=', $this->user_id]
        ];
        if ($next_id) {
            $map[] = ['a.id', '<', $next_id];
        }
        
        $promote_data = Db::name('user_dredge_eduities')
        ->alias('a')
        ->join('sl_user u', 'u.id = a.user_id', 'LEFT')
        ->field('a.id, a.user_id, a.add_time, a.price, a.superior_award, a.status, u.nickname, u.avator')
        ->where($map)
        ->order('a.id', 'DESC')
        ->limit($page_size)
        ->select();
        
        foreach ($promote_data as &$val) {
            $val['add_time'] = date('Y-m-d H:i:s', $val['add_time']);
            $val['price'] = sprintf('%.2f', $val['price'] / 100);
            $val['superior_award'] = sprintf('%.2f', $val['superior_award'] / 100);
            $val['status_desc'] = '';
            if ($val['status']) {
                $val['status_desc'] = '津贴已到账';
            }
            
            // 头像
            if ($val['avator'] && (strpos($val['avator'], 'http://') === false && strpos($val['avator'], 'https://') === false)) {
                $val['avator'] = config('site.url') . $val['avator'];
            }
        }
        
        $count = count($promote_data);
        if ($count > 0) {
            $next_id = $promote_data[$count - 1]['id'];
        }
        
        return $this->response(['result' => $promote_data, 'next_id' => $next_id]);
    }
    
    // 白拿订单
    public function take_lists()
    {
        $next_id = Request::get('next_id', 0);
        $page_size = Request::get('page_size', 60);
        
        $map = [];
        $map[] = ['og.type', '=', 1];
        $map[] = ['user_id', '=', $this->user_id];
        if ($next_id) {
            $map[] = ['o.id', '<', $next_id];
        }
        
        $take_data = Db::name('order_take')
        ->alias('o')
        ->join('sl_order_goods og', 'o.id = og.order_id', 'LEFT')
        ->field('o.id, o.order_no, o.add_time, o.day, o.residue_day, o.min_minute, o.max_minute, o.user_id, o.status, o.appoint_price, og.good_name, og.img')
        ->where($map)
        ->order('o.id', 'DESC')
        ->limit($page_size)
        ->select();
        
        foreach ($take_data as &$val) {
            // 开始结束日期
            $val['start_date'] = date('Y-m-d', $val['add_time'] + 86400);
            $val['end_date'] = date('Y-m-d', strtotime('+'. $val['day'] .' day', $val['add_time']));
            // 遗漏打卡时间
            if ($val['status'] == 2) {
                $forget_day = $val['day'] - $val['residue_day'];
                $val['forget_date'] = date('Y-m-d', strtotime("+$forget_day day", $val['add_time']));
            }
            $val['add_time'] = date('Y-m-d H:i:s', $val['add_time']);
            
            // 契约金
            $val['appoint_price'] = sprintf('%.2f', $val['appoint_price'] / 100);
            
            // 打卡时间范围
            $val['min_minute'] = OrderModel::format_minute($val['min_minute']);
            $val['max_minute'] = OrderModel::format_minute($val['max_minute']);
            
            // 白拿状态
            switch ($val['status']) {
                case 0:
                    $val['status_desc'] = '进行中...';
                    $val['status'] = 'march';
                    break;
                case 1:
                    $val['status_desc'] = '契约金已退';
                    $val['status'] = 'success';
                    break;
                default:
                    $val['status_desc'] = '契约金被群分';
                    $val['status'] = 'fail';
                    break;
            }
        }
        
        $count = count($take_data);
        if ($count > 0) {
            $next_id = $take_data[$count - 1]['id'];
        }
        
        // 状态详情
        $status_info = [
            'march' => '温馨提示：该商品是您通过承诺早起打卡{%day%}天并缴纳了{%appoint_price%}元的商品押金免费获得的，请记得连续坚持早起打卡（每天打卡时间为{%min_minute%}~{%max_minute%}，如多个商品，请在多个商品点击签到打卡），否则您所缴纳的商品押金将会被群分掉。早起打卡时间是从{%start_date%} 至{%end_date%}，{%end_date%}完成按时早起打卡任务后11点前商品押金会全额退回到您的账户。',
            'success' => '温馨提示：该商品是您通过承诺早起打卡{%day%}天并缴纳了{%appoint_price%}元的商品押金免费获得的，请记得连续坚持早起打卡（每天打卡时间为{%min_minute%}~{%max_minute%}，如多个商品，请在多个商品点击签到打卡）。早起打卡时间是从{%start_date%} 至{%end_date%}，现在您已完成任务，商品押金已退回到您的账户余额，敬请查收。',
            'fail' => '温馨提示：该商品是您通过承诺早起打卡{%day%}天并缴纳了{%appoint_price%}元的商品押金免费获得的，请记得连续坚持早起打卡（每天打卡时间为{%min_minute%}~{%max_minute%}，如多个商品，请在多个商品点击签到打卡）。早起打卡时间是从{%start_date%} 至{%end_date%}，由于您在{%forget_date%}日未准时打卡，按规定，您所缴纳的商品押金已被群分，无法退还。'
        ];
        
        return $this->response(['take_data' => $take_data, 'status_info' => $status_info, 'next_id' => $next_id]);
    }
    
    // 白拿确认收货
    public function take_confirm_receipt()
    {
        $id = Request::put('id', 0);
        
        $map = [
            ['id', '=', $id],
            ['user_id', '=', $this->user_id]
        ];
        $rs = Db::name('order_take')->where($map)->update(['order_status' => 2]);
        if (!$rs) {
            return $this->response([], '操作失败，请重试', '40001');
        }
        
        return $this->response([], '操作成功');
    }
    
    // 白拿打卡
    public function take_signin()
    {
        $id = Request::post('id', 0);
        $today_time = Common::today_time();
        
        $map = [
            ['id', '=', $id],
            ['user_id', '=', $this->user_id]
        ];
        $take_data = Db::name('order_take')->field('min_minute, max_minute')->where($map)->find();
        
        // 判断打卡时间范围
        $signin_minute = (request()->time() - $today_time - 18000) / 60;
        if ($signin_minute < $take_data['min_minute'] || $signin_minute > $take_data['max_minute']) {
            return $this->response([], '现在不是打卡时间哦', '40001');
        }
        
        // 判断是否已打卡
        $map = [
            ['signin_time', 'BETWEEN', [$today_time + 18000 + ($take_data['min_minute'] * 60), $today_time + 18000 + ($take_data['max_minute'] * 60)]],
            ['user_id', '=', $this->user_id],
            ['take_id', '=', $id]
        ];
        $rs = Db::name('order_take_signin')->where($map)->count();
        if ($rs > 0) {
            return $this->response([], '已成功打卡', '40001');
        }
        
        $rs = Db::name('order_take_signin')->insert([
            'user_id' => $this->user_id,
            'take_id' => $id,
            'signin_time' => request()->time()
        ]);
        if (!$rs) {
            return $this->response([], '打卡失败', '40001');
        }
        
        return $this->response([], '打卡成功');
    }
    
    public function lists_v2()
    {
        $type = Request::get('type', 0);
        $status = Request::get('status', 0);
        $page = Request::get('page', 0);
        $page_size = Request::get('page_size', 60);
        $order_type = Request::get('order_type', 1);
        
        if ($type != 1 && $type != 2) {
            return $this->response(['order_data' => []]);
        }
        
        $map = [];
        $map[] = [
            ['op.type', '=', $order_type],
            ['opc.user_id', '=', $this->user_id],
            ['opc.type', '=', $type]
        ];
        
        // 状态查询
        if ($status) {
            switch ($status) {
                case 2:
                    $map[] = ['status', 'in', [2,4,6]];
                    break;
                default:
                    $map[] = ['status', '=', $status];
                    break;
            }
        }

        // 订单列表
        $order_data = Db::name('order_promotion_commission')->where($map)
        ->alias('opc')
        ->field('op.id, op.add_time, op.status, op.platform_commission, op.order_amount, op.goods_name as good_name, op.img, op.buy_user_id as user_id, opc.commission')
        ->join('sl_order_promotion op', 'op.id = opc.order_id', 'LEFT')
        ->order('op.id', 'DESC')
        ->page($page)
        ->limit($page_size)
        ->select();

        foreach ($order_data as &$val) {
            $val['add_time'] = date('Y-m-d H:i:s', $val['add_time']);
        
            // 用户信息
            $val['orderuser'] = Db::name('user')->field('id, avator, nickname')->where('id', '=', $val['user_id'])->find();
            if (!empty($val['orderuser']['avator']) && strpos($val['orderuser']['avator'], 'http://') === false) {
                $val['orderuser']['avator'] = config('site.url') . $val['orderuser']['avator'];
            }
            
            switch ($val['status']) {
                case 0:
                    $val['status_desc'] = '未付款';
                    break;
                case 1:
                    $val['status_desc'] = '已付款';
                    break;
                case 3:
                    $val['status_desc'] = '已失效';
                    break;
                case 5:
                    $val['status_desc'] = '已结算';
                    break;
                default:
                    $val['status_desc'] = '已收货';
                    break;
            }
        
            $val['commission'] = round($val['commission'] / 100, 2);
            $val['order_amount'] = round($val['order_amount'] / 100, 2);
        }

        if (!$order_data || count($order_data) < $page_size) {
            $map = [];
            $map[] = ['o.type', '=', $order_type];
            if ($type == 1) {
                $map[] = ['user_id', '=', $this->user_id];
                
                $field_append = ', o.user_commission';
            } else {
                $map[] = ['directly_user_id|directly_supervisor_user_id', '=', $this->user_id];
                
                $field_append = ', o.directly_user_id, o.directly_user_commission, o.directly_supervisor_user_commission';
            }
            
            if ($status) {
                switch ($status) {
                    case 2:
                        $map[] = ['status', 'in', [2,4,6]];
                        break;
                    default:
                        $map[] = ['status', '=', $status];
                        break;
                }
            }
            
            // 订单列表
            $order_database_name = OrderModel::where($map);

            $order_data_old = $order_database_name
            ->alias('o')
            ->with(['orderuser'=>function($query){
                $query->field('id, nickname, avator');
            }])
            ->field('o.id, o.add_time, o.directly_user_id, o.user_id, o.status, o.platform_commission, o.order_amount, og.good_name, og.img' . $field_append)
            ->join('sl_order_goods og', 'o.id = og.order_id', 'LEFT')
            ->order('o.add_time', 'DESC')
            //->limit(100)
            ->select();
            if ($order_data_old) {
                $order_data_old = $order_data_old->toArray();
            }
            
            foreach ($order_data_old as &$val) {
                $val['add_time'] = date('Y-m-d H:i:s', $val['add_time']);
            
                if (!empty($val['orderuser']['avator']) && strpos($val['orderuser']['avator'], 'http://') === false) {
                    $val['orderuser']['avator'] = config('site.url') . $val['orderuser']['avator'];
                }
            
                switch ($val['status']) {
                    case 0:
                        $val['status_desc'] = '未付款';
                        break;
                    case 1:
                        $val['status_desc'] = '已付款';
                        break;
                    case 3:
                        $val['status_desc'] = '已失效';
                        break;
                    case 5:
                        $val['status_desc'] = '已结算';
                        break;
                    default:
                        $val['status_desc'] = '已收货';
                        break;
                }
            
                if ($type == 1) {
                    $val['commission'] = $val['user_commission'];
                    unset($val['user_commission']);
                } else {
                    if ($val['directly_user_id'] == $this->user_id) {
                        $val['commission'] = $val['directly_user_commission'];
                        unset($val['directly_user_commission']);
                        unset($val['directly_user_id']);
                    } else {
                        $val['commission'] = $val['directly_supervisor_user_commission'];
                        unset($val['directly_supervisor_user_commission']);
                    }
                }
            
                $val['commission'] = round($val['commission'] / 100, 2);
                $val['order_amount'] = round($val['order_amount'] / 100, 2);
            }
            
            if ($order_data_old) {
                $order_data = array_merge($order_data, $order_data_old);
            }
        }
        
        return $this->response(['order_data' => $order_data]);
    }
}
