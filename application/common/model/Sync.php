<?php

namespace app\common\model;

use think\facade\Cache;
use think\Db;
use think\facade\Log;

class Sync
{
    // 抓取商品
    public function get_goods()
    {
        $time = request()->time();
        $Goods = new GoodsModel();
        $table = $Goods->getTable();
        // Db::name('goods')->delete(true);

        $Ddk = new \ddk();
        $ddk_cate = [1, 4, 13, 14, 15, 16, 18, 743, 818, 1281, 1451, 1543];
        //$ddk_cate = [8172,6398,6536,6785,6883,6586,6630,77,282,479,483,489,519,9316,9317,3202,6630,8439,259,8669,8634,8508,8509,8538,5752,5955,6076,6128,239,69,1432,1464,5851,5921,6290,9319,5834,2933,11684,11685,11686,11687,11688,8583];
        
        $config = config('database.');
        $config['params'] = [\PDO::ATTR_EMULATE_PREPARES => true];
        $db = Db::connect($config)->name('goods');

        foreach ($ddk_cate as $cate_id) {
            $page = 1;
            while (1) {
                $data = [
                    'type' => 'pdd.ddk.goods.search',
                    'category_id' => $cate_id,
                    'sort_type' => 0,
                    'with_coupon' => 'true',
                    'page' => $page,
                    'page_size' => 100,
                    'range_list' => '[{"range_id":2,"range_from":150,"range_to":1000},{"range_id":5,"range_from":100,"range_to":1000000}]'
                ];
                $res = $Ddk->get_goods($data);

                if (isset($res['error_response'])) {
                    break;
                }
                if (!$res['goods_search_response']['goods_list']) {
                    break;
                }
                if ($page == 1) {
                    echo $res['goods_search_response']['total_count'] . '--';
                }

                // $add_data = [];
                $sql = $bind = [];
                foreach ($res['goods_search_response']['goods_list'] as $key => $val) {
                    if (!isset($val['coupon_discount']) || $val['coupon_discount'] == 0) {
                        continue;
                    }

                    $add_data = [
                        // 'goods_id' => '' . $val['goods_id'],
                        'name' => $val['goods_name'],
                        'd_name' => $val['mall_name'],
                        'img' => $val['goods_thumbnail_url'],
                        'cut_img' => $val['goods_image_url'],
                        'cid' => $cate_id,
                        'org_price' => $val['min_normal_price'],
                        'price' => $val['min_group_price'] - $val['coupon_discount'],
                        'sales_num' => $val['sold_quantity'],
                        'dsr' => isset($val['goods_eval_score']) && $val['goods_eval_score'] ? $val['goods_eval_score'] : 0,
                        'commission' => $val['promotion_rate'],
                        'coupon_price' => isset($val['coupon_discount']) && $val['coupon_discount'] ? $val['coupon_discount'] : 0,
                        'coupon_time' => isset($val['coupon_end_time']) && $val['coupon_end_time'] ? $val['coupon_end_time'] : 0,
                        'coupon_surplus_quantity' => isset($val['coupon_remain_quantity']) && $val['coupon_remain_quantity'] ? $val['coupon_remain_quantity'] : 0,
                        'coupon_total_quantity' => isset($val['coupon_total_quantity']) && $val['coupon_total_quantity'] ? $val['coupon_total_quantity'] : 0,
                        'coupon_min_order_amount' => isset($val['coupon_min_order_amount']) && $val['coupon_min_order_amount'] ? $val['coupon_min_order_amount'] : 0,
                        'edit_time' => $time
                    ];

                    if (empty($field)) $field = '`' . implode('` , `', array_keys($add_data)) . '`';

                    $add = $edit = '';
                    foreach ($add_data as $k => $v) {
                        $add .= ", '{$v}'";
                        $edit .= ", `{$k}` = '{$v}'";
                    }

                    $sql[] = "INSERT INTO $table(`goods_id` , {$field}) VALUES('{$val['goods_id']}'{$add}) ON DUPLICATE KEY UPDATE " . substr($edit, 1);
                }

                // $Goods->saveAll($add_data);
                // $Goods->insertAll($add_data, true);
                $db->execute(implode(';', $sql));
                // dump($cate_id . ' | ' . $page . ' | ' . count($res['goods_search_response']['goods_list']));
                $page++;
            }
        }

        Db::name('goods')
            ->where([['coupon_time', '>', 0], ['coupon_time', '<', $time]])// 优惠券过期
            ->whereOr([['edit_time', '<', $time]])// 旧数据
            ->delete();

        return 'SUCCESS';
    }

    public function get_jd_goods()
    {
        Db::name('goods_jd')->delete(true);
        
        $Jos = new \jos();
//         $data = [
//             'pageIndex' => 250,
//             'pageSize' => 25,
//             //'cid3' => $cate_id
//         ];
//         $res = $Jos->get_json('jingdong.union.search.queryCouponGoods', $data);
//         if ($res['jingdong_union_search_queryCouponGoods_responce']['code'] != 0) {
//             echo 1; exit;
//         }
        
//         $res = json_decode($res['jingdong_union_search_queryCouponGoods_responce']['query_coupon_goods_result'], true);
//         if ($res['resultCode'] != 1) {
//             echo 1; exit;
//         }
//         if (!isset($res['data']) || empty($res['data'])) {
//             echo 1; exit;
//         }
//         print_r($res);exit;
        //$jd_cate = [1320,12218,1319,6233,13678,1713,6196,9847,15901,1620,15248,9192,12259,12367,1315,1316,16750,6144,11729,17329,670,6728,12379,737,5025,9987,652,1318];
        $jd_cate = [1595, 1594, 1593];
        
        //foreach ($jd_cate as $cate_id) {
            $page = 1;
            while (1) {
                $data = [
                    'pageIndex' => $page,
                    'pageSize' => 25,
                    //'cid3' => $cate_id
                ];
                $res = $Jos->get_json('jingdong.union.search.queryCouponGoods', $data);
                if ($res['jingdong_union_search_queryCouponGoods_responce']['code'] != 0) {print_r($res);echo 1;
                    break;
                }
    
                $res = json_decode($res['jingdong_union_search_queryCouponGoods_responce']['query_coupon_goods_result'], true);
                if ($res['resultCode'] != 1) {print_r($res);echo 2;
                    break;
                }
                if (!isset($res['data']) || empty($res['data'])) {print_r($res);echo 3;
                    break;
                }
    
                $add_data = [];
                foreach ($res['data'] as $val) {
                    if (!isset($val['couponList'])) {
                        continue;
                    }
                    if ($val['wlPrice'] - $val['couponList'][0]['discount'] <= 0) {
                        continue;
                    }
                    if (strpos($val['imageurl'], 'http://') === false && strpos($val['imageurl'], 'https://') === false) {
                        $val['imageurl'] = 'https://m.360buyimg.com/mobilecms/s750x750_' . $val['imageurl'];
                    }
                    
                    $add_data[] = [
                        'sku_id' => ''.$val['skuId'],
                        'name' => $val['skuName'],
                        'img' => $val['imageurl'],
                        'org_price' => $val['wlPrice'] * 100,
                        'price' => ($val['wlPrice'] - $val['couponList'][0]['discount']) * 100,
                        'commission' => $val['wlCommissionShare'] * 10,
                        'coupon_price' => $val['couponList'][0]['discount'] ?: 0,
                        'coupon_time' => $val['couponList'][0]['endTime'] / 1000 ?: 0,
                        'coupon_info' => json_encode($val['couponList']),
                        'vid' => ''.$val['vid'] ?: 0,
                        'cid' => $val['cid'] ?: 0,
                        'cid2' => $val['cid2'] ?: 0,
                        'cid3' => $val['cid3'] ?: 0,
                        'cid_name' => $val['cidName'] ?: '',
                        'in_order_count' => $val['inOrderCount'] ?: 0
                    ];
                }
                echo $page.'-'.count($add_data).'---';
                $page++;
            
                $rs = Db::name('goods_jd')->insertAll($add_data);
            }
        //}
    }
    /*
    // *  *\/1  *  *  * 抓取拼多多订单
    public function get_ddk_order()
    {
        $Ddk = new \ddk();
        $Order = new Order();
        $page = 1;

        // 获取上次更新时间
        $order_cache_key = 'goods_order_para';
        $goods_order_para = Cache::get($order_cache_key);
        if ($goods_order_para['ddk_order_lasttime']) {
            $start_time = $goods_order_para['ddk_order_lasttime'];
        } else {
            $start_time = strtotime('-10 day');
        }
        $start_time = strtotime('-1 day');
        // 返佣比例
        $good_commission_data = Db::name('siteinfo')->where('key', 'good_commission')->value('value');
        $good_commission_data = json_decode($good_commission_data, true);

        // 拉新活动信息
        $active = db('active')->where('id', '=', 1)->find();
        
        while (1) {
            $data = [
                'type' => 'pdd.ddk.order.list.increment.get',
                'start_update_time' => $start_time,
                'end_update_time' => request()->time(),
                'page_size' => 50,
                'page' => $page
            ];
            $res = $Ddk->get_goods($data);
            $page++;

            if (isset($res['error_response'])) {
                break;
            }
            if (!$res['order_list_get_response']['order_list']) {
                break;
            }

            $add_data = [];
            foreach ($res['order_list_get_response']['order_list'] as $val) {
                $val['skuList'][0]['validCode'] = $val['order_status'];
                if ($val['order_status'] == -1) {
                    continue;
                }
                
                switch ($val['order_status']) {
                    case 0:
                    case 1:
                        $val['order_status'] = 1;
                        break;
                    case 2:
                    case 3:
                        $val['order_status'] = 2;
                        break;
                    case 5:
                        $val['order_status'] = 4;
                        break;
                    default:
                        $val['order_status'] = 3;
                        break;
                }

                $order = Order::where('order_no', $val['order_sn'])->field('id, status, user_id, directly_user_id, directly_supervisor_user_id')->find();
                if ($order) {
                    if ($order->status != $val['order_status']) {
                        Db::name('order')->where('id', '=', $order->id)->update([
                            'status' => $val['order_status'],
                            'platform_rebeat' => $val['promotion_rate'],
                            'platform_commission' => $val['promotion_amount'],
                            'modify_at_time' => $val['order_verify_time']
                        ]);
                        
                        // 拉新订单状态修改
                        Db::name('active_order')->where('order_id', '=', $order['id'])->update([
                            'status' => $val['order_status'],
                        ]);
                    }
                    
                    // 清除缓存
                    $forecast_income_cache_key = 'forecast_income_' . $order['user_id'];
                    Cache::rm($forecast_income_cache_key);
                    
                    $forecast_income_cache_key = 'forecast_income_' . $order['directly_user_id'];
                    Cache::rm($forecast_income_cache_key);
                    
                    $forecast_income_cache_key = 'forecast_income_' . $order['directly_supervisor_user_id'];
                    Cache::rm($forecast_income_cache_key);
                    continue;
                }
                // 订单用户id
                $user_id = Db::name('user_promotion')->field('user_id')->where('p_id', $val['p_id'])->value('user_id');

                $user_id = $user_id ? $user_id : 0;
                if ($user_id) {
                    $pay_nickname = Db::name('user')->where('id', $user_id)->value('nickname');
                }

                // 订单佣金
                $commission = $val['promotion_amount'] * ($good_commission_data['show_commission_scale'] / 100);
                $team_data = $Order->get_top_user($user_id);
                $commission_reward = $Order->calc_commission($commission, $team_data['level']);

                $create_date = date('Y-m-d', $val['order_create_time']);
                // 不足一分钱  不返佣
                $commission_reward['level_1'] = round($commission_reward['level_1']);
                if ($commission_reward['level_1'] == 0) {
                    $team_data['user']['user_id'] = 0;
                }

                $commission_reward['level_2'] = round($commission_reward['level_2']);
                if ($commission_reward['level_2'] == 0) {
                    $team_data['user']['directly_user_id'] = 0;
                }

                $commission_reward['level_3'] = round($commission_reward['level_3']);
                if ($commission_reward['level_3'] == 0) {
                    $team_data['user']['directly_supervisor_user_id'] = 0;
                }

                // 添加订单
                $res = $Order->add_order($val, $team_data, $commission_reward);
                $order_id = $res['id'];
                
                if ($team_data['user']['user_id']) {
                    // 清除缓存
                    $forecast_income_cache_key = 'forecast_income_' . $team_data['user']['user_id'];
                    Cache::rm($forecast_income_cache_key);
                
                    // 添加系统通知
                    db('user_dynamic')->insert([
                        'user_id' => 0,
                        'nickname' => '微选生活',
                        'avator' => '/static/img/logo.png',
                        'receive_uid' => $team_data['user']['user_id'],
                        'event' => 'order_commission',
                        'event_id' => $order_id,
                        'data' => serialize([
                            'title' => '订单收入通知  奖励 '. round($commission_reward['level_1'] / 100, 2) .'元',
                            'content' => '成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. $create_date .'] 出单成功，您获得奖励'. round($commission_reward['level_1'] / 100, 2) .'元'
                        ]),
                        'status' => 1,
                        'add_time' => request()->time()
                    ]);
                    
                    db('xinge_task')->insert([
                        'user_id' => $team_data['user']['user_id'],
                        'event' => 'order_commission',
                        'data' => serialize([
                            'title' => '订单收入通知  奖励 '. round($commission_reward['level_1'] / 100, 2) .'元',
                            'content' => '成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. $create_date .'] 出单成功，您获得奖励'. round($commission_reward['level_1'] / 100, 2) .'元'
                        ])
                    ]);
                    
                    // 是否有合伙人奖励
                    $rs = db('order_free_log')->where([
                        ['event', '=', 'team_partner'],
                        ['user_id', '=', $team_data['user']['user_id']]
                    ])->find();
                    if (!$rs) {
                        $rs = db('order_free_log')->where([
                            ['event', '=', 'first_order'],
                            ['user_id', '=', $team_data['user']['user_id']]
                        ])->find();
                        // 是否是首单
                        if (!$rs) {
                            $client_code = Db::name('app_token')->where('user_id', '=', $team_data['user']['user_id'])->value('client_code');
                            $rs = db('order_free_log')->where([
                                ['event', '=', 'first_order'],
                                ['client_code', '=', $client_code]
                            ])->find();
                            if (!$rs) {
                                Order::add_free_order($team_data['user']['user_id'], $team_data['user']['user_id'], 1, '用户购买首单，订单id：'.$order_id, 'first_order');
                                db('user_dynamic')->insert([
                                    'user_id' => 0,
                                    'nickname' => '微选生活',
                                    'avator' => '/static/img/logo.png',
                                    'receive_uid' => $team_data['user']['user_id'],
                                    'event' => 'free_order_item',
                                    'event_id' => $order_id,
                                    'data' => serialize([
                                        'title' => '恭喜您，获得1个免单名额',
                                        'content' => '亲，为感谢您对我们的支持，通过微选生活以更低的价格购买到了同一店铺同一个商品，现奖励您1个免单名额，只需邀请1位好友进来领券消费，该免单即可生效，好友消费的金额将进入您的账户，随时可提现'
                                    ]),
                                    'status' => 1,
                                    'add_time' => request()->time()
                                ]);
                                
                                db('xinge_task')->insert([
                                    'user_id' => $team_data['user']['user_id'],
                                    'event' => 'free_order_item',
                                    'data' => serialize([
                                        'title' => '恭喜您，获得1个免单名额',
                                        'content' => '亲，为感谢您对我们的支持，通过微选生活以更低的价格购买到了同一店铺同一个商品，现奖励您1个免单名额，只需邀请1位好友进来领券消费，该免单即可生效，好友消费的金额将进入您的账户，随时可提现'
                                    ])
                                ]);
                            }
                        }
                    }
                }
                if ($team_data['user']['directly_user_id']) {
                    // 清除缓存
                    $forecast_income_cache_key = 'forecast_income_' . $team_data['user']['directly_user_id'];
                    Cache::rm($forecast_income_cache_key);
                
                    // 添加系统通知
                    db('user_dynamic')->insert([
                        'user_id' => 0,
                        'nickname' => '微选生活',
                        'avator' => '/static/img/logo.png',
                        'receive_uid' => $team_data['user']['directly_user_id'],
                        'event' => 'order_commission',
                        'event_id' => $order_id,
                        'data' => serialize([
                            'title' => '订单收入通知  奖励 '. round($commission_reward['level_2'] / 100, 2) .'元',
                            'content' => '成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. $create_date .'] 出单成功，您获得奖励'. round($commission_reward['level_2'] / 100, 2) .'元'
                        ]),
                        'status' => 1,
                        'add_time' => request()->time()
                    ]);
                    
                    db('xinge_task')->insert([
                        'user_id' => $team_data['user']['directly_user_id'],
                        'event' => 'order_commission',
                        'data' => serialize([
                            'title' => '订单收入通知  奖励 '. round($commission_reward['level_2'] / 100, 2) .'元',
                            'content' => '成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. $create_date .'] 出单成功，您获得奖励'. round($commission_reward['level_2'] / 100, 2) .'元'
                        ])
                    ]);
                    
                    // 免单订单添加系统通知
                    $rs = Db::name('order')->field('id')->where([
                        ['user_id', '=', $team_data['user']['user_id']],
                        ['status', '<>', 3],
                    ])->find();
                    if ($rs['id'] == $order_id) {
                        db('user_dynamic')->insert([
                            'user_id' => 0,
                            'nickname' => '微选生活',
                            'avator' => '/static/img/logo.png',
                            'receive_uid' => $team_data['user']['directly_user_id'],
                            'event' => 'free_order_item',
                            'event_id' => $order_id,
                            'data' => serialize([
                                'title' => '免单补贴通知  奖励 '. ($val['order_amount'] > 500 ? 5 : round($val['order_amount'] / 100, 2)) .'元',
                                'content' => '直属成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. $create_date .']开始买了1单啦，您将获得免单补贴返现'. ($val['order_amount'] > 500 ? 5 : round($val['order_amount'] / 100, 2)) .'元'
                            ]),
                            'status' => 1,
                            'add_time' => request()->time()
                        ]);
                        
                        db('xinge_task')->insert([
                            'user_id' => $team_data['user']['directly_user_id'],
                            'event' => 'free_order_item',
                            'data' => serialize([
                                'title' => '免单补贴通知  奖励 '. ($val['order_amount'] > 500 ? 5 : round($val['order_amount'] / 100, 2)) .'元',
                                'content' => '直属成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. $create_date .']开始买了1单啦，您将获得免单补贴返现'. ($val['order_amount'] > 500 ? 5 : round($val['order_amount'] / 100, 2)) .'元'
                            ])
                        ]);

                        if (request()->time() >= $active['begin_time'] && request()->time() < $active['end_time']) {
                            $expire_time = Db::name('user_promotion')->where('user_id', $team_data['user']['directly_user_id'])->value('expire_time');
                            if ($expire_time && $expire_time > request()->time()) {
                                $is_partner = 1;
                            } else {
                                $is_partner = 0;
                            }
                            db('active_order')->insert([
                                'order_id' => $order_id,
                                'is_partner' => $is_partner,
                                'invite_user_id' => $team_data['user']['directly_user_id'],
                                'add_time' => request()->time(),
                                'status' => $val['order_status'],
                                'user_id' => $team_data['user']['user_id']
                            ]);
                        }
                        
                    }
                }
                if ($team_data['user']['directly_supervisor_user_id']) {
                    // 清除缓存
                    $forecast_income_cache_key = 'forecast_income_' . $team_data['user']['directly_supervisor_user_id'];
                    Cache::rm($forecast_income_cache_key);
                
                    // 添加系统通知
                    db('user_dynamic')->insert([
                        'user_id' => 0,
                        'nickname' => '微选生活',
                        'avator' => '/static/img/logo.png',
                        'receive_uid' => $team_data['user']['directly_supervisor_user_id'],
                        'event' => 'order_commission',
                        'event_id' => $order_id,
                        'data' => serialize([
                            'title' => '订单收入通知  奖励 '. round($commission_reward['level_3'] / 100, 2) .'元',
                            'content' => '成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. $create_date .'] 出单成功，您获得奖励'. round($commission_reward['level_3'] / 100, 2) .'元'
                        ]),
                        'status' => 1,
                        'add_time' => request()->time()
                    ]);
                    
                    db('xinge_task')->insert([
                        'user_id' => $team_data['user']['directly_supervisor_user_id'],
                        'event' => 'order_commission',
                        'data' => serialize([
                            'title' => '订单收入通知  奖励 '. round($commission_reward['level_3'] / 100, 2) .'元',
                            'content' => '成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. $create_date .'] 出单成功，您获得奖励'. round($commission_reward['level_3'] / 100, 2) .'元'
                        ])
                    ]);
                }
            }
        }

        $goods_order_para['ddk_order_lasttime'] = request()->time();
        Cache::set($order_cache_key, $goods_order_para);
    }
    
    public function get_jd_order()
    {
        $jos = new \jos();
        $Order = new Order();
        $page = 1;

        // 返佣比例
        $good_commission_data = Db::name('siteinfo')->where('key', 'good_commission')->value('value');
        $good_commission_data = json_decode($good_commission_data, true);

        // 拉新活动信息
        $active = db('active')->where('id', '=', 1)->find();
        
        while (1) {
            $data = [
                'unionId' => '1000767623',
                'time' => date('YmdH'),
                'pageIndex' => $page,
                'pageSize' => '10'
            ];
            $res = $jos->get_json('jingdong.UnionService.queryOrderList', $data);
            $res = json_decode($res['jingdong_UnionService_queryOrderList_responce']['result'], true);
            //print_r($res);exit;
            
            $data = [
                'unionId' => '1000767623',
                'time' => date("YmdH", strtotime("-1 hour")),
                'pageIndex' => $page,
                'pageSize' => '10'
            ];
            $before_hour_res = $jos->get_json('jingdong.UnionService.queryOrderList', $data);
            $before_hour_res = json_decode($before_hour_res['jingdong_UnionService_queryOrderList_responce']['result'], true);
            
            $page++;

            if (!isset($res['data']) && !isset($before_hour_res['data'])) {
                break;
            } elseif (!isset($res['data']) && isset($before_hour_res['data'])) {
                $res['data'] = $before_hour_res['data'];
            } elseif (isset($res['data']) && isset($before_hour_res['data'])) {
                $res['data'] = array_merge($res['data'], $before_hour_res['data']);
            } else {
                
            }

            $add_data = [];
            foreach ($res['data'] as $val) {
                $val['orderTime'] = intval($val['orderTime'] / 1000);
                $val['skuList'][0]['estimateCommission'] = $val['skuList'][0]['estimateCommission'] * 100;
                if ($val['skuList'][0]['validCode'] == -1 || $val['skuList'][0]['validCode'] == 15) {
                    continue;
                }
                
                switch ($val['skuList'][0]['validCode']) {
                    case 16:
                        $val['order_status'] = 1;
                        break;
                    case 17:
                        $val['order_status'] = 2;
                        break;
                    case 18:
                        $val['order_status'] = 4;
                        break;
                    default:
                        $val['order_status'] = 3;
                        break;
                }

                $order = Db::name('order')->where('order_no', $val['orderId'])->field('id, status, user_id, directly_user_id, directly_supervisor_user_id')->find();
                if ($order) {
                    if ($order['status'] != $val['order_status']) {
                        Db::name('order')->where('id', '=', $order['id'])->update([
                            'status' => $val['order_status'],
                            'platform_commission' => $val['skuList'][0]['actualCommission'],
                            'modify_at_time' => $val['finishTime'] / 1000
                        ]);
                        
                        // 拉新订单状态修改
                        Db::name('active_order')->where('order_id', '=', $order['id'])->update([
                            'status' => $val['order_status'],
                        ]);
                    }
                    
                    // 清除缓存
                    $forecast_income_cache_key = 'statement_income_' . $order['user_id'];
                    Cache::rm($forecast_income_cache_key);
                    
                    $forecast_income_cache_key = 'statement_income_' . $order['directly_user_id'];
                    Cache::rm($forecast_income_cache_key);
                    
                    $forecast_income_cache_key = 'statement_income_' . $order['directly_supervisor_user_id'];
                    Cache::rm($forecast_income_cache_key);
                    continue;
                }
                // 订单用户id
                $user_id = Db::name('user_promotion')->field('user_id')->where('sub_unionid', $val['skuList'][0]['subUnionId'])->value('user_id');

                $user_id = $user_id ? $user_id : 0;
                if ($user_id) {
                    $pay_nickname = Db::name('user')->where('id', $user_id)->value('nickname');
                }

                // 订单佣金
                $commission = $val['skuList'][0]['estimateCommission'] * ($good_commission_data['show_commission_scale'] / 100);
                $team_data = $Order->get_top_user($user_id);
                $commission_reward = $Order->calc_commission($commission, $team_data['level']);

                $create_date = date('Y-m-d', $val['orderTime']);
                // 不足一分钱  不返佣
                $commission_reward['level_1'] = round($commission_reward['level_1']);
                if ($commission_reward['level_1'] == 0) {
                    $team_data['user']['user_id'] = 0;
                }

                $commission_reward['level_2'] = round($commission_reward['level_2']);
                if ($commission_reward['level_2'] == 0) {
                    $team_data['user']['directly_user_id'] = 0;
                }

                $commission_reward['level_3'] = round($commission_reward['level_3']);
                if ($commission_reward['level_3'] == 0) {
                    $team_data['user']['directly_supervisor_user_id'] = 0;
                }
                
                // 添加订单
                $res = $Order->add_order_jd($val, $team_data, $commission_reward);
                $order_id = $res['id'];
                
                if ($team_data['user']['user_id']) {
                    // 清除缓存
                    $forecast_income_cache_key = 'statement_income_' . $team_data['user']['user_id'];
                    Cache::rm($forecast_income_cache_key);
                
                    // 添加系统通知
                    db('user_dynamic')->insert([
                        'user_id' => 0,
                        'nickname' => '微选生活',
                        'avator' => '/static/img/logo.png',
                        'receive_uid' => $team_data['user']['user_id'],
                        'event' => 'order_commission',
                        'event_id' => $order_id,
                        'data' => serialize([
                            'title' => '订单收入通知  奖励 '. round($commission_reward['level_1'] / 100, 2) .'元',
                            'content' => '成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. $create_date .'] 出单成功，您获得奖励'. round($commission_reward['level_1'] / 100, 2) .'元'
                        ]),
                        'status' => 1,
                        'add_time' => request()->time()
                    ]);
                    
                    db('xinge_task')->insert([
                        'user_id' => $team_data['user']['user_id'],
                        'event' => 'order_commission',
                        'data' => serialize([
                            'title' => '订单收入通知  奖励 '. round($commission_reward['level_1'] / 100, 2) .'元',
                            'content' => '成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. $create_date .'] 出单成功，您获得奖励'. round($commission_reward['level_1'] / 100, 2) .'元'
                        ])
                    ]);
                    
                    // 是否有合伙人奖励
                    $rs = db('order_free_log')->where([
                        ['event', '=', 'team_partner'],
                        ['user_id', '=', $team_data['user']['user_id']]
                    ])->find();
                    if (!$rs) {
                        $rs = db('order_free_log')->where([
                            ['event', '=', 'first_order'],
                            ['user_id', '=', $team_data['user']['user_id']]
                        ])->find();
                        // 是否是首单
                        if (!$rs) {
                            $client_code = Db::name('app_token')->where('user_id', '=', $team_data['user']['user_id'])->value('client_code');
                            $rs = db('order_free_log')->where([
                                ['event', '=', 'first_order'],
                                ['client_code', '=', $client_code]
                            ])->find();
                            if (!$rs) {
                                Order::add_free_order($team_data['user']['user_id'], $team_data['user']['user_id'], 1, '用户购买首单，订单id：'.$order_id, 'first_order');
                                db('user_dynamic')->insert([
                                    'user_id' => 0,
                                    'nickname' => '微选生活',
                                    'avator' => '/static/img/logo.png',
                                    'receive_uid' => $team_data['user']['user_id'],
                                    'event' => 'free_order_item',
                                    'event_id' => $order_id,
                                    'data' => serialize([
                                        'title' => '恭喜您，获得1个免单名额',
                                        'content' => '亲，为感谢您对我们的支持，通过微选生活以更低的价格购买到了同一店铺同一个商品，现奖励您1个免单名额，只需邀请1位好友进来领券消费，该免单即可生效，好友消费的金额将进入您的账户，随时可提现'
                                    ]),
                                    'status' => 1,
                                    'add_time' => request()->time()
                                ]);
                                
                                db('xinge_task')->insert([
                                    'user_id' => $team_data['user']['user_id'],
                                    'event' => 'free_order_item',
                                    'data' => serialize([
                                        'title' => '恭喜您，获得1个免单名额',
                                        'content' => '亲，为感谢您对我们的支持，通过微选生活以更低的价格购买到了同一店铺同一个商品，现奖励您1个免单名额，只需邀请1位好友进来领券消费，该免单即可生效，好友消费的金额将进入您的账户，随时可提现'
                                    ])
                                ]);
                            }
                        }
                    }
                }
                if ($team_data['user']['directly_user_id']) {
                    // 清除缓存
                    $forecast_income_cache_key = 'statement_income_' . $team_data['user']['directly_user_id'];
                    Cache::rm($forecast_income_cache_key);
                
                    // 添加系统通知
                    db('user_dynamic')->insert([
                        'user_id' => 0,
                        'nickname' => '微选生活',
                        'avator' => '/static/img/logo.png',
                        'receive_uid' => $team_data['user']['directly_user_id'],
                        'event' => 'order_commission',
                        'event_id' => $order_id,
                        'data' => serialize([
                            'title' => '订单收入通知  奖励 '. round($commission_reward['level_2'] / 100, 2) .'元',
                            'content' => '成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. $create_date .'] 出单成功，您获得奖励'. round($commission_reward['level_2'] / 100, 2) .'元'
                        ]),
                        'status' => 1,
                        'add_time' => request()->time()
                    ]);
                    
                    db('xinge_task')->insert([
                        'user_id' => $team_data['user']['directly_user_id'],
                        'event' => 'order_commission',
                        'data' => serialize([
                            'title' => '订单收入通知  奖励 '. round($commission_reward['level_2'] / 100, 2) .'元',
                            'content' => '成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. $create_date .'] 出单成功，您获得奖励'. round($commission_reward['level_2'] / 100, 2) .'元'
                        ])
                    ]);
                    
                    // 免单订单添加系统通知
                    $rs = Db::name('order')->field('id')->where([
                        ['user_id', '=', $team_data['user']['user_id']],
                        ['status', '<>', 3],
                    ])->find();
                    if ($rs['id'] == $order_id) {
                        db('user_dynamic')->insert([
                            'user_id' => 0,
                            'nickname' => '微选生活',
                            'avator' => '/static/img/logo.png',
                            'receive_uid' => $team_data['user']['directly_user_id'],
                            'event' => 'free_order_item',
                            'event_id' => $order_id,
                            'data' => serialize([
                                'title' => '免单补贴通知  奖励 '. ($val['skuList'][0]['estimateCosPrice'] > 5 ? 5 : $data['skuList'][0]['estimateCosPrice']) .'元',
                                'content' => '直属成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. $create_date .']开始买了1单啦，您将获得免单补贴返现'. ($val['skuList'][0]['estimateCosPrice'] > 5 ? 5 : $data['skuList'][0]['estimateCosPrice']) .'元'
                            ]),
                            'status' => 1,
                            'add_time' => request()->time()
                        ]);
                        
                        db('xinge_task')->insert([
                            'user_id' => $team_data['user']['directly_user_id'],
                            'event' => 'free_order_item',
                            'data' => serialize([
                                'title' => '免单补贴通知  奖励 '. ($val['skuList'][0]['estimateCosPrice'] > 5 ? 5 : $data['skuList'][0]['estimateCosPrice']) .'元',
                                'content' => '直属成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. $create_date .']开始买了1单啦，您将获得免单补贴返现'. ($val['skuList'][0]['estimateCosPrice'] > 5 ? 5 : $data['skuList'][0]['estimateCosPrice']) .'元'
                            ])
                        ]);
                        
                        if (request()->time() >= $active['begin_time'] && request()->time() < $active['end_time']) {
                            $expire_time = Db::name('user_promotion')->where('user_id', $team_data['user']['directly_user_id'])->value('expire_time');
                            if ($expire_time && $expire_time > request()->time()) {
                                $is_partner = 1;
                            } else {
                                $is_partner = 0;
                            }
                            db('active_order')->insert([
                                'order_id' => $order_id,
                                'is_partner' => $is_partner,
                                'invite_user_id' => $team_data['user']['directly_user_id'],
                                'add_time' => request()->time(),
                                'status' => $val['order_status'],
                                'user_id' => $team_data['user']['user_id']
                            ]);
                        }
                    }
                }
                if ($team_data['user']['directly_supervisor_user_id']) {
                    // 清除缓存
                    $forecast_income_cache_key = 'statement_income_' . $team_data['user']['directly_supervisor_user_id'];
                    Cache::rm($forecast_income_cache_key);
                
                    // 添加系统通知
                    db('user_dynamic')->insert([
                        'user_id' => 0,
                        'nickname' => '微选生活',
                        'avator' => '/static/img/logo.png',
                        'receive_uid' => $team_data['user']['directly_supervisor_user_id'],
                        'event' => 'order_commission',
                        'event_id' => $order_id,
                        'data' => serialize([
                            'title' => '订单收入通知  奖励 '. round($commission_reward['level_3'] / 100, 2) .'元',
                            'content' => '成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. $create_date .'] 出单成功，您获得奖励'. round($commission_reward['level_3'] / 100, 2) .'元'
                        ]),
                        'status' => 1,
                        'add_time' => request()->time()
                    ]);
                    
                    db('xinge_task')->insert([
                        'user_id' => $team_data['user']['directly_supervisor_user_id'],
                        'event' => 'order_commission',
                        'data' => serialize([
                            'title' => '订单收入通知  奖励 '. round($commission_reward['level_3'] / 100, 2) .'元',
                            'content' => '成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. $create_date .'] 出单成功，您获得奖励'. round($commission_reward['level_3'] / 100, 2) .'元'
                        ])
                    ]);
                }
            }
        }
    }
    
    */
    public function get_ddk_order()
    {
        $Ddk = new \ddk();
        $Order = new Order();
        $page = 1;
        
        // 获取上次更新时间
        $order_cache_key = 'goods_order_para';
        $goods_order_para = Cache::get($order_cache_key);
        if ($goods_order_para['ddk_order_lasttime']) {
            $start_time = $goods_order_para['ddk_order_lasttime'];
        } else {
            $start_time = strtotime('-10 day');
        }
        $start_time = strtotime('-1 day');
        // 返佣比例
        $good_commission_data = Db::name('siteinfo')->where('key', 'good_commission')->value('value');
        $good_commission_data = json_decode($good_commission_data, true);
        
        // 拉新活动信息
        $active = db('active')->where('id', '=', 1)->find();
        
        while (1) {
            $data = [
                'type' => 'pdd.ddk.order.list.increment.get',
                'start_update_time' => $start_time,
                'end_update_time' => request()->time(),
                'page_size' => 50,
                'page' => $page
            ];
            $res = $Ddk->get_goods($data);
            $page++;
        
            if (isset($res['error_response'])) {
                break;
            }
            if (!$res['order_list_get_response']['order_list']) {
                break;
            }
        
            $add_data = [];
            foreach ($res['order_list_get_response']['order_list'] as $val) {
                $val['skuList'][0]['validCode'] = $val['order_status'];
                if ($val['order_status'] == -1) {
                    continue;
                }
        
                switch ($val['order_status']) {
                    case 0:
                    case 1:
                        $val['order_status'] = 1;
                        break;
                    case 2:
                    case 3:
                        $val['order_status'] = 2;
                        break;
                    case 5:
                        $val['order_status'] = 4;
                        break;
                    default:
                        $val['order_status'] = 3;
                        break;
                }
        
                $order = Db::name('order')->where('order_no', $val['order_sn'])->field('id, status, user_id, directly_user_id, directly_supervisor_user_id')->find();
                if ($order) {
                    if ($order['status'] != $val['order_status']) {
                        Db::name('order')->where('id', '=', $order['id'])->update([
                            'status' => $val['order_status'],
                            'platform_rebeat' => $val['promotion_rate'],
                            'platform_commission' => $val['promotion_amount'],
                            'modify_at_time' => $val['order_verify_time']
                        ]);
                
                        // 拉新订单状态修改
                        Db::name('active_order')->where('order_id', '=', $order['id'])->update([
                            'status' => $val['order_status'],
                        ]);

                        // 0.1元购到货返现
                        if ($val['order_status'] == 2) {
                            Activity::point_one_buy_arrival_return_cash($val['order_sn']);
                        }
                    }
                    continue;
                }
                
                $order = DB::name('order_promotion')->where('order_no', $val['order_sn'])->field('id, status')->find();
                if ($order) {
                    if ($order['status'] != $val['order_status']) {
                        Db::name('order')->where('id', '=', $order['id'])->update([
                            'status' => $val['order_status'],
                            'platform_rebeat' => $val['promotion_rate'],
                            'platform_commission' => $val['promotion_amount'],
                            'modify_at_time' => $val['order_verify_time']
                        ]);
        
                        // 拉新订单状态修改
                        Db::name('active_order')->where('order_id', '=', $order['id'])->update([
                            'status' => $val['order_status'],
                        ]);
                    }
        
                    /*
                    // 清除缓存
                    $forecast_income_cache_key = 'forecast_income_' . $order['user_id'];
                    Cache::rm($forecast_income_cache_key);
        
                    $forecast_income_cache_key = 'forecast_income_' . $order['directly_user_id'];
                    Cache::rm($forecast_income_cache_key);
        
                    $forecast_income_cache_key = 'forecast_income_' . $order['directly_supervisor_user_id'];
                    Cache::rm($forecast_income_cache_key);
                    */
                    continue;
                }
                // 购买用户id
                $user_id = Db::name('user_promotion')->field('user_id')->where('p_id', $val['p_id'])->value('user_id');
                $val['buy_user_id'] = $user_id = $user_id ? $user_id : 0;
                $team_data = []; // 团队佣金数据
                
                if ($user_id) {
                    // 用户会员等级
                    $user_level = Db::name('user_level')
                    ->where([
                        ['user_id', '=', $user_id],
                        ['expier_time', '>', request()->time()]
                    ])->value('level');
                    if (!$user_level) {
                        $user_level = 1;
                    }
                    
                    // 上级团队
                    $team_data = User::user_superior($user_id, $user_level > 2 ? $user_level : 2);
                    if ($user_level != 1) {
                        // 不是普通会员，可以获得佣金
                        array_unshift($team_data, [
                            'user_id' => $user_id,
                            'level' => $user_level,
                            'type' => 1
                        ]);
                    }
                    
                    // 订单佣金发放
                    $commission = $val['promotion_amount'] * ($good_commission_data['show_commission_scale']);
                    $team_data = $Order->calc_commission($commission, $team_data, $good_commission_data['commission_rule']);
                    
                    // 伯乐奖佣金发放
                    if ($team_data) {
                        $bole_first_user = isset($team_data[1]) ? $team_data[1] : $team_data[0];
                        
                        $bole_team_data = $Order->bole_commission($bole_first_user['user_id'], $bole_first_user['level'], $bole_first_user['commission']);
                        if ($bole_team_data) {
                            $team_data = array_merge($team_data, $bole_team_data);
                        }
                    }

                    // 0.1元抢购
                    Activity::point_one_buy_outright_purchase($user_id, $val['goods_id'], $val['order_sn']);
                }
                
                // 添加订单
                $res = $Order->add_order($val, $team_data);
                $order_id = $res['id'];
            }
        }
        
        $goods_order_para['ddk_order_lasttime'] = request()->time();
        Cache::set($order_cache_key, $goods_order_para);
    }

    public function get_jd_order()
    {
        $jos = new \jos();
        $Order = new Order();
        $page = 1;

        // 返佣比例
        $good_commission_data = Db::name('siteinfo')->where('key', 'good_commission')->value('value');
        $good_commission_data = json_decode($good_commission_data, true);

        // 拉新活动信息
        $active = db('active')->where('id', '=', 1)->find();
    
        while (1) {
            $data = [
                'unionId' => '1000767623',
                'time' => '2018112013',//date('YmdH'),
                'pageIndex' => $page,
                'pageSize' => '10'
            ];
            $res = $jos->get_json('jingdong.UnionService.queryOrderList', $data);
            $res = json_decode($res['jingdong_UnionService_queryOrderList_responce']['result'], true);
            
            $data = [
                'unionId' => '1000767623',
                'time' => date("YmdH", strtotime("-1 hour")),
                'pageIndex' => $page,
                'pageSize' => '10'
            ];
            $before_hour_res = $jos->get_json('jingdong.UnionService.queryOrderList', $data);
            $before_hour_res = json_decode($before_hour_res['jingdong_UnionService_queryOrderList_responce']['result'], true);
            
            $page++;

            if (!isset($res['data']) && !isset($before_hour_res['data'])) {
                break;
            } elseif (!isset($res['data']) && isset($before_hour_res['data'])) {
                $res['data'] = $before_hour_res['data'];
            } elseif (isset($res['data']) && isset($before_hour_res['data'])) {
                $res['data'] = array_merge($res['data'], $before_hour_res['data']);
            } else {
                
            }

            $add_data = [];
            foreach ($res['data'] as $val) {
                $val['orderTime'] = intval($val['orderTime'] / 1000);
                $val['skuList'][0]['estimateCommission'] = $val['skuList'][0]['estimateCommission'] * 100;
                if ($val['skuList'][0]['validCode'] == -1 || $val['skuList'][0]['validCode'] == 15) {
                    continue;
                }
    
                switch ($val['skuList'][0]['validCode']) {
                    case 16:
                        $val['order_status'] = 1;
                        break;
                    case 17:
                        $val['order_status'] = 2;
                        break;
                    case 18:
                        $val['order_status'] = 4;
                        break;
                    default:
                        $val['order_status'] = 3;
                        break;
                }
    
                $order = Db::name('order')->where('order_no', $val['orderId'])->field('id, status, user_id, directly_user_id, directly_supervisor_user_id')->find();
                if ($order) {
                    if ($order['status'] != $val['order_status']) {
                        Db::name('order')->where('id', '=', $order['id'])->update([
                            'status' => $val['order_status'],
                            'platform_commission' => $val['skuList'][0]['actualCommission'],
                            'modify_at_time' => $val['finishTime'] / 1000
                        ]);
                
                        // 拉新订单状态修改
                        Db::name('active_order')->where('order_id', '=', $order['id'])->update([
                            'status' => $val['order_status'],
                        ]);
                    }
                    continue;
                }
                
                $order = DB::name('order_promotion')->where('order_no', $val['orderId'])->field('id, status')->find();
                if ($order) {
                    if ($order['status'] != $val['order_status']) {
                        Db::name('order_promotion')->where('id', '=', $order['id'])->update([
                            'status' => $val['order_status'],
                            'platform_commission' => $val['skuList'][0]['actualCommission'],
                            'modify_at_time' => $val['finishTime'] / 1000
                        ]);
    
                        // 拉新订单状态修改
                        Db::name('active_order')->where('order_id', '=', $order['id'])->update([
                            'status' => $val['order_status'],
                        ]);
                    }
    
                    /*
                     // 清除缓存
                     $forecast_income_cache_key = 'forecast_income_' . $order['user_id'];
                     Cache::rm($forecast_income_cache_key);
    
                     $forecast_income_cache_key = 'forecast_income_' . $order['directly_user_id'];
                     Cache::rm($forecast_income_cache_key);
    
                     $forecast_income_cache_key = 'forecast_income_' . $order['directly_supervisor_user_id'];
                     Cache::rm($forecast_income_cache_key);
                     */
                    continue;
                }
                // 购买用户id
                $user_id = Db::name('user_promotion')->field('user_id')->where('sub_unionid', $val['skuList'][0]['subUnionId'])->value('user_id');
                $val['buy_user_id'] = $user_id = $user_id ? $user_id : 0;
                $team_data = []; // 团队佣金数据
                
                if ($user_id) {
                    // 用户昵称
                    $pay_nickname = Db::name('user')->where('id', $user_id)->value('nickname');
    
                    // 用户会员等级
                    $user_level = Db::name('user_level')
                    ->where([
                        ['user_id', '=', $user_id],
                        ['expier_time', '>', request()->time()]
                    ])->value('level');
                    if (!$user_level) {
                        $user_level = 1;
                    }
                    
                    // 上级团队
                    $team_data = User::user_superior($user_id, $user_level > 2 ? $user_level : 2);
                    if ($user_level != 1) {
                        // 不是普通会员，也可以获得佣金
                        array_unshift($team_data, [
                            'user_id' => $user_id,
                            'level' => $user_level,
                            'type' => 1
                        ]);
                    }
    
                    // 订单佣金发放
                    $commission = $val['skuList'][0]['estimateCommission'] * ($good_commission_data['show_commission_scale']);
                    $team_data = $Order->calc_commission($commission, $team_data, $good_commission_data['commission_rule']);

                    // 伯乐奖佣金发放
                    if ($team_data) {
                        $bole_first_user = isset($team_data[1]) ? $team_data[1] : $team_data[0];
                        
                        $bole_team_data = $Order->bole_commission($bole_first_user['user_id'], $bole_first_user['level'], $bole_first_user['commission']);
                        if ($bole_team_data) {
                            $team_data = array_merge($team_data, $bole_team_data);
                        }
                    }
                }
                
                // 添加订单
                $res = $Order->add_order_jd($val, $team_data);
                $order_id = $res['id'];
            }
        }
    }
    
    // 0  0  *  *  * 抓取淘宝订单
    public function get_tbk_order()
    {
        $order_data = json_decode($_POST['order_data'], true);
        $Order = new Order();

        if ($order_data['paymentList']) {
            // 返佣比例
            $good_commission_data = Db::name('siteinfo')->where('key', 'good_commission')->value('value');
            $good_commission_data = json_decode($good_commission_data, true);

            // 3 订单结算  12 订单付款  13 订单失效
            $data = [];
            foreach ($order_data['paymentList'] as $key => $val) {
                switch ($val['order_status']) {
                    case 12:
                        $val['order_status'] = 1;
                        break;
                    case 3:
                        $val['order_status'] = 4;
                        break;
                    default:
                        $val['order_status'] = 3;
                        $modify_at_time = request()->time();
                        break;
                }

                if ($val['tkPubShareFeeString']) {
                    $val['feeString'] = $val['tkPubShareFeeString'];
                }
                if ($val['realPayFeeString']) {
                    $val['totalAlipayFeeString'] = $val['realPayFeeString'];
                }

                // 获取数据库订单
                $order = Order::where('order_no', $val['taobaoTradeParentId'])->find();
                if ($order) {
                    $val['feeString'] *= 100;
                    $val['totalAlipayFeeString'] *= 100;
                    // 重新计算返佣
                    if ($order->platform_commission != $val['feeString']) {
                        $commission = $val['feeString'] * ($good_commission_data['show_commission_scale'] / 100);
                        $team_level = unserialize($order->team_level);
                        $commission_reward = $Order->calc_commission($commission, $team_level);

                        // 不足一分钱  不返佣
                        $commission_reward['level_1'] = round($commission_reward['level_1']);
                        if ($commission_reward['level_1'] == 0) {
                            $team_data['user']['user_id'] = 0;
                            $commission_reward['level_1'] = 1;
                        }
                        $commission_reward['level_2'] = round($commission_reward['level_2']);
                        if ($commission_reward['level_2'] == 0) {
                            $team_data['user']['directly_user_id'] = 0;
                            $commission_reward['level_2'] = 1;
                        }
                        $commission_reward['level_3'] = round($commission_reward['level_3']);
                        if ($commission_reward['level_3'] == 0) {
                            $team_data['user']['directly_supervisor_user_id'] = 0;
                            $commission_reward['level_3'] = 1;
                        }

                        $order->platform_rebeat = $val['finalDiscountToString'] * 10;
                        $order->platform_commission = $val['feeString'];
                        $order->order_amount = $val['totalAlipayFeeString'];
                        $order->user_commission = $commission_reward['level_1'];
                        $order->directly_user_commission = $commission_reward['level_2'];
                        $order->directly_supervisor_user_commission = $commission_reward['level_3'];
                        $order->modify_at_time = $modify_at_time;
                    }

                    $order->status = $val['payStatus'];
                    $order->save();
                    continue;
                }
            }

            exit(json_encode(['info' => 'ok', 'status' => 1]));
        } else {
            exit(json_encode(['status' => 0]));
        }

    }

    public function promotion_day_stat()
    {
        $todaytime = Common::today_time();
        $stat_month = date('Ym', $todaytime - 86400);

        $order = db('order')->field('id, platform_commission, user_id, directly_user_id, directly_supervisor_user_id, user_commission, directly_user_commission, directly_supervisor_user_commission')
        ->where([
            ['add_time', 'BETWEEN', [$todaytime - 86400, $todaytime]],
            ['type', '=', 1]
        ])
        ->select();

        $data = [
            'stat_month' => $stat_month,
            'add_time' => $todaytime - 86400
        ];
        $data['buy_count'] = 0;
        $data['order_count'] = count($order);
        $data['predict_commission_settlement'] = 0;
        $data['predict_commission_issued'] = 0;
        $buy_user = [];
        foreach ($order as $val) {
            if (!in_array($val['user_id'], $buy_user)) {
                $data['buy_count']++;
                $buy_user[] = $val['user_id'];
            }

            $data['predict_commission_settlement'] += $val['platform_commission'];
            if ($val['user_id']) {
                $data['predict_commission_issued'] += $val['user_commission'];
            }
            if ($val['directly_user_id']) {
                $data['predict_commission_issued'] += $val['directly_user_commission'];
            }
            if ($val['directly_supervisor_user_id']) {
                $data['predict_commission_issued'] += $val['directly_supervisor_user_commission'];
            }
        }

        // 用户等级
        $level_count = db('user_level')
        ->field('SUM(CASE WHEN level = 2 THEN 1 ELSE 0 END) AS super_count,
                SUM(CASE WHEN level = 3 THEN 1 ELSE 0 END) AS vip_count,
                SUM(CASE WHEN level = 4 THEN 1 ELSE 0 END) AS parner_count')
        ->where([
            ['add_time', 'BETWEEN', [$todaytime - 86400, $todaytime]]
        ])->find();
        $data['super_count'] = $level_count['super_count'] ?: 0;
        $data['vip_count'] = $level_count['vip_count'] ?: 0;
        $data['parner_count'] = $level_count['parner_count'] ?: 0;

        // 立体营销模式
        
        // 每日订单总量
        $order_promotion_total = db('order_promotion')->field('count(`id`) as order_count, sum(`estimate_commission`) as predict_commission_settlement')->where([
            ['add_time', 'BETWEEN', [$todaytime - 86400, $todaytime]],
            ['type', '=', 1]
        ])->find();
        $data['order_count'] = $order_promotion_total['order_count']; // 订单数量
        $data['predict_commission_settlement'] = $order_promotion_total['predict_commission_settlement']; // 预测佣金结算
        
        // 订单统计
        $order_promotion = db('order_promotion_commission')
        ->alias('opc')
        ->join('order_promotion op', 'op.id = opc.order_id', 'LEFT')
        ->field('op.estimate_commission, opc.order_id, opc.user_id, opc.commission, opc.type')
        ->where([
            ['op.add_time', 'BETWEEN', [$todaytime - 86400, $todaytime]],
            ['op.type', '=', 1]
        ])->select();
        foreach ($order_promotion as $op_key=>$op_item) {
            // 购买数量
            if ($op_item['type'] == 1 && !in_array($op_item['user_id'], $buy_user)) {
                $data['buy_count']++;
                $buy_user[] = $op_item['user_id'];
            }
                        
            // 预测佣金发放
            $data['predict_commission_issued'] += $op_item['commission'];
        }
        
        db('goods_promotion_day_stat')->insert($data);

        $rs = db('goods_promotion_stat')->where('stat_date', $stat_month)->count();
        if ($rs == 0) {
            // 每月份
            db('goods_promotion_stat')->insert([
                'add_time' => $todaytime - 86400,
                'stat_date' => $stat_month
            ]);
        }
    }

    public function promotion_day_stat_jd()
    {
        $todaytime = Common::today_time();
        $stat_month = date('Ym', $todaytime - 86400);
        
        $order = db('order')->field('id, platform_commission, user_id, directly_user_id, directly_supervisor_user_id, user_commission, directly_user_commission, directly_supervisor_user_commission')
        ->where([
            ['add_time', 'BETWEEN', [$todaytime - 86400, $todaytime]],
            ['type', '=', 2]
        ])
        ->select();

        $data = [
            'stat_month' => $stat_month,
            'add_time' => $todaytime - 86400
        ];
        $data['buy_count'] = 0;
        $data['order_count'] = count($order);
        $data['predict_commission_settlement'] = 0;
        $data['predict_commission_issued'] = 0;
        $buy_user = [];
        foreach ($order as $val) {
            if (!in_array($val['user_id'], $buy_user)) {
                $data['buy_count']++;
                $buy_user[] = $val['user_id'];
            }
    
            $data['predict_commission_settlement'] += $val['platform_commission'];
            if ($val['user_id']) {
                $data['predict_commission_issued'] += $val['user_commission'];
            }
            if ($val['directly_user_id']) {
                $data['predict_commission_issued'] += $val['directly_user_commission'];
            }
            if ($val['directly_supervisor_user_id']) {
                $data['predict_commission_issued'] += $val['directly_supervisor_user_commission'];
            }
        }
        
        // 用户等级
        $level_count = db('user_level')
        ->field('SUM(CASE WHEN level = 2 THEN 1 ELSE 0 END) AS super_count,
                SUM(CASE WHEN level = 3 THEN 1 ELSE 0 END) AS vip_count,
                SUM(CASE WHEN level = 4 THEN 1 ELSE 0 END) AS parner_count')
        ->where([
            ['add_time', 'BETWEEN', [$todaytime - 86400, $todaytime]]
        ])->find();
        $data['super_count'] = $level_count['super_count'] ?: 0;
        $data['vip_count'] = $level_count['vip_count'] ?: 0;
        $data['parner_count'] = $level_count['parner_count'] ?: 0;
        
        // 立体营销模式订单
        
        // 每日订单总量
        $order_promotion_total = db('order_promotion')->field('count(`id`) as order_count, sum(`estimate_commission`) as predict_commission_settlement')->where([
            ['add_time', 'BETWEEN', [$todaytime - 86400, $todaytime]],
            ['type', '=', 2]
        ])->find();
        $data['order_count'] = $order_promotion_total['order_count']; // 订单数量
        $data['predict_commission_settlement'] = $order_promotion_total['predict_commission_settlement'] ?: 0; // 预测佣金结算
        
        $order_promotion = db('order_promotion_commission')
        ->alias('opc')
        ->join('order_promotion op', 'op.id = opc.order_id', 'LEFT')
        ->field('op.estimate_commission, opc.order_id, opc.user_id, opc.commission, opc.type')
        ->where([
            ['op.add_time', 'BETWEEN', [$todaytime - 86400, $todaytime]],
            ['op.type', '=', 2]
        ])->select();
        foreach ($order_promotion as $op_key=>$op_item) {
            // 购买数量
            if ($op_item['type'] == 1 && !in_array($op_item['user_id'], $buy_user)) {
                $data['buy_count']++;
                $buy_user[] = $op_item['user_id'];
            }
            
            // 预测佣金发放
            $data['predict_commission_issued'] += $op_item['commission'];
        }

        db('goods_jd_promotion_day_stat')->insert($data);
    
        $rs = db('goods_jd_promotion_stat')->where('stat_date', $stat_month)->count();
        if ($rs == 0) {
            // 每月份
            db('goods_jd_promotion_stat')->insert([
                'add_time' => $todaytime - 86400,
                'stat_date' => $stat_month
            ]);
        }
    }
    
    // 0  0  *  *  * 订单结算
    public function promotion_stat()
    {
        $last_month_start = strtotime(date('Y-m-01', strtotime('-1 month')));
        $last_month_end = strtotime(date('Y-m-t 23:59:59', strtotime('-1 month')));
        $stat_month = date('Ym', $last_month_start);

        $map = [
            ['status', '=', 4],
            ['type', '=', 1]
            //['modify_at_time', 'between', [$last_month_start, $last_month_end]]
        ];
        $order_data = Db::name('order')
            ->field('id, user_id, directly_user_id, directly_supervisor_user_id, platform_commission, user_commission, directly_user_commission, directly_supervisor_user_commission, order_amount')
            ->where($map)
            ->select();

        // 所有首单
        $map[] = ['user_id', '>', 0];
        $free_order = Db::name('order')->field('min(id) as id, user_id, directly_user_id, order_amount, status')->field('id')->where([
            ['status', 'in', '1,2,4,5,6'],
            ['add_time', '>', 1534163213]
        ])->group('user_id')->select();
        
        foreach ($free_order as $val) {
            if ($val['status'] != 4) {
                continue;
            }
            // 订单免单记录
            $rs = db('order_free')->insert([
                'order_id' => $val['id'],
                'user_id' => $val['directly_user_id'],
                'status' => 0,
                'free_amount' => $val['order_amount'] > 500 ? 500 : $val['order_amount'],
                'free_log_id' => 0
            ]);
        }
        
        $free_log = Db::name('order_free_log')->field('id, user_id, use_count')->where('use_count < add_free_order')->select();
        $free_order = Db::name('order_free')->field('id, user_id')->select();
        
        // 上月结算订单改为审核
        $platform_commission = 0;
        $grant_commission = 0;
        foreach ($order_data as $val) {
            $rs = Db::name('order')->where('id', $val['id'])->update(['status' => 6]);

            if ($rs) {
                $platform_commission += $val['platform_commission'];

                if ($val['user_id']) {
                    $grant_commission += $val['user_commission'];
                }
                if ($val['directly_user_id']) {
                    $grant_commission += $val['directly_user_commission'];
                }
                if ($val['directly_supervisor_user_id']) {
                    $grant_commission += $val['directly_supervisor_user_commission'];
                }
            }
        }

        // 立体营销模式订单
        $order_promotion = db('order_promotion_commission')
        ->alias('opc')
        ->join('order_promotion op', 'op.id = opc.order_id', 'LEFT')
        ->field('op.platform_commission, opc.order_id, opc.user_id, opc.commission, opc.type')
        ->where([
            ['op.status', '=', 4],
            ['op.type', '=', 1]
        ])->select();
        foreach ($order_promotion as $op_key=>$op_item) {
            $rs = Db::name('order_promotion')->where('id', $op_item['order_id'])->update(['status' => 6]);
        
            if ($op_key == 0 || $order_promotion[$op_key]['order_id'] != $order_promotion[$op_key - 1]['order_id']) {
                $platform_commission += $op_item['platform_commission'];
            }
            $grant_commission += $op_item['commission'];
        }

        $promotion_platform_commission = db('order_promotion')->where([
            ['status', '=', 4],
            ['type', '=', 1]
        ])->sum('platform_commission');
        
        $rs = db('goods_promotion_stat')->where('stat_date', $stat_month)->count();
        if ($rs == 0) {
            $data = [
                'platform_commission' => $platform_commission + $promotion_platform_commission,
                'grant_commission' => $grant_commission,
                'add_time' => request()->time(),
                'stat_date' => $stat_month
            ];

            Db::name('goods_promotion_stat')->insert($data);
        } else {
            Db::name('goods_promotion_stat')->where('stat_date', $stat_month)->update([
                'platform_commission' => $platform_commission + $promotion_platform_commission,
                'grant_commission' => $grant_commission,
            ]);
        }

        return 'SUCCESS';
    }

    // 0  0  *  *  * 订单结算
    public function promotion_stat_jd()
    {
        $last_month_start = strtotime(date('Y-m-01', strtotime('-1 month')));
        $last_month_end = strtotime(date('Y-m-t 23:59:59', strtotime('-1 month')));
        $stat_month = date('Ym', $last_month_start);
    
        $map = [
            ['status', '=', 4],
            ['type', '=', 2]
            //['modify_at_time', 'between', [$last_month_start, $last_month_end]]
        ];
        $order_data = Db::name('order')
        ->field('id, user_id, directly_user_id, directly_supervisor_user_id, platform_commission, user_commission, directly_user_commission, directly_supervisor_user_commission, order_amount')
        ->where($map)
        ->select();
    
        // 所有首单
        $map[] = ['user_id', '>', 0];
        $free_order = Db::name('order')->field('min(id) as id, user_id, directly_user_id, order_amount, status')->field('id')->where([
            ['status', 'in', '1,2,4,5,6'],
            ['add_time', '>', 1534163213]
        ])->group('user_id')->select();
    
        foreach ($free_order as $val) {
            if ($val['status'] != 4) {
                continue;
            }
            // 订单免单记录
            $rs = db('order_free')->insert([
                'order_id' => $val['id'],
                'user_id' => $val['directly_user_id'],
                'status' => 0,
                'free_amount' => $val['order_amount'] > 500 ? 500 : $val['order_amount'],
                'free_log_id' => 0
            ]);
        }
    
        $free_log = Db::name('order_free_log')->field('id, user_id, use_count')->where('use_count < add_free_order')->select();
        $free_order = Db::name('order_free')->field('id, user_id')->select();
    
        // 佣金收入
        $platform_commission = 0;
        // 佣金发放
        $grant_commission = 0;
        foreach ($order_data as $val) {
            // 上月结算订单改为审核
            $rs = Db::name('order')->where('id', $val['id'])->update(['status' => 6]);
    
            if ($rs) {
                $platform_commission += $val['platform_commission'];
    
                if ($val['user_id']) {
                    $grant_commission += $val['user_commission'];
                }
                if ($val['directly_user_id']) {
                    $grant_commission += $val['directly_user_commission'];
                }
                if ($val['directly_supervisor_user_id']) {
                    $grant_commission += $val['directly_supervisor_user_commission'];
                }
            }
        }
    
        // 立体营销模式订单
        $order_promotion = db('order_promotion_commission')
        ->alias('opc')
        ->join('order_promotion op', 'op.id = opc.order_id', 'LEFT')
        ->field('op.platform_commission, opc.order_id, opc.user_id, opc.commission, opc.type')
        ->where([
            ['op.status', '=', 4],
            ['op.type', '=', 2]
        ])->select();
        foreach ($order_promotion as $op_key=>$op_item) {
            $rs = Db::name('order_promotion')->where('id', $op_item['order_id'])->update(['status' => 6]);
            
            if ($op_key == 0 || $order_promotion[$op_key]['order_id'] != $order_promotion[$op_key - 1]['order_id']) {
                $platform_commission += $op_item['platform_commission'];
            }
            $grant_commission += $op_item['commission'];
        }
        
        $promotion_platform_commission = db('order_promotion')->where([
            ['status', '=', 4],
            ['type', '=', 1]
        ])->sum('platform_commission');
        
        $rs = db('goods_jd_promotion_stat')->where('stat_date', $stat_month)->count();
        if ($rs == 0) {
            $data = [
                'platform_commission' => $platform_commission + $promotion_platform_commission,
                'grant_commission' => $grant_commission,
                'add_time' => request()->time(),
                'stat_date' => $stat_month
            ];
    
            Db::name('goods_jd_promotion_stat')->insert($data);
        } else {
            Db::name('goods_jd_promotion_stat')->where('stat_date', $stat_month)->update([
                'platform_commission' => $platform_commission + $promotion_platform_commission,
                'grant_commission' => $grant_commission,
            ]);
        }
    
        return 'SUCCESS';
    }
    
    public function week_stat()
    {
        $map = [
            ['pid', '>', 0],
            ['status', '=', 0]
        ];
        
        $grant_award = [];
        $data = Db::name('user_dredge_eduities')->where($map)->select();
        foreach ($data as $val) {
            // 发放奖励
            if (isset($grant_award[$val['pid']])) {
                $grant_award[$val['pid']] += $val['superior_award'];
            } else {
                $grant_award[$val['pid']] = $val['superior_award'];
            }
            
            // 更改状态为已结算
            Db::name('user_dredge_eduities')->where('id', '=', $val['id'])->update(['status' => 1]);
        }
        
        foreach ($grant_award as $key=>$val) {
            // 晋升津贴
            $res = User::money($key, $val, 'superior_award', $key, '周薪');
            if (!isset($res['flow_id'])) {
                Log::record('dredge_eduities superior_award add error, uid-'. $key);
                Db::rollback();
                return [[], '处理失败', 50001];
            }
            
            // 清除缓存
            $forecast_income_cache_key = 'forecast_income_'. $key;
            Cache::rm($forecast_income_cache_key);
            
            // 添加系统通知
            db('user_dynamic')->insert([
                'user_id' => 0,
                'nickname' => '微选生活',
                'avator' => '/static/img/logo.png',
                'receive_uid' => $key,
                'event' => 'dredge_eduities_award',
                'event_id' => $key,
                'data' => serialize([
                    'title' => '周薪发放通知  奖励 '. round($val / 100, 2) .'元',
                    'content' => '一亿元扶持对象+每周培训津贴+考核额外奖励您：'. round($val / 100, 2) .'元'
                ]),
                'status' => 1,
                'add_time' => request()->time()
            ]);
            
            db('xinge_task')->insert([
                'user_id' => $key,
                'event' => 'dredge_eduities_award',
                'data' => serialize([
                    'title' => '周薪发放通知  奖励 '. round($val / 100, 2) .'元',
                    'content' => '一亿元扶持对象+每周培训津贴+考核额外奖励您：'. round($val / 100, 2) .'元'
                ])
            ]);
        }
    }
    
    public function challenge_auto_join()
    {
        $time = $next_time = request()->time();
        $cache = cache('challenge_auto_join');
        if (!empty($cache) && !empty($cache['next_time'])) {
            $next_time = $cache['next_time'];
        }

        if ($next_time > $time) return false;

        $where = User::system_user_filter();
        $room_ids = Db::name('challenge_room')->where($where)->column('room_id');

        if (!empty($room_ids)) {
            $room_price = Db::name('challenge')
                ->where([['room_id', 'in', $room_ids], ['status', '=', 1]])
                ->group('room_id')->column('room_id,SUM(price) sum_price');

            foreach ($room_ids as $room_id) {
                $rand_price = mt_rand(15000, 25000);
                if (!empty($room_price[$room_id]) && $room_price[$room_id] > $rand_price * 100) continue;

                $user = User::system_user_rand($rand_num = mt_rand(3, 8));
                $price = Challenge::join_option()['price'];

                foreach ($user as $uid) {
                    // $rand_price = mt_rand(1, 10) * 10;
                    $rand_price = $price[mt_rand(0, 2)];

                    $res = Challenge::join($uid, ['room_id' => $room_id, 'price' => $rand_price, 'use_balance' => 1]);
                    dump($res);
                }
            }

            cache('challenge_auto_join', ['update_time' => $time, 'next_time' => $time + mt_rand(5, 10) * 60]);
            return true;
        }

        return false;
    }

    public function challenge_auto_launch()
    {
        $time = $next_time = request()->time();
        $cache = cache('challenge_auto_launch');
        if (!empty($cache) && !empty($cache['next_time'])) {
            $next_time = $cache['next_time'];
        }

        if ($next_time > $time) return false;

        $user = [User::system_user_rand(1)]; // mt_rand(3, 10);
        $option = Challenge::both_option();
        $price = $option['price'];
        $day = $option['day'];

        foreach ($user as $uid) {
            $rand_price = $price[mt_rand(0, 2)];
            $rand_time = [[530, 630], [630, 730], [700, 800]];
            list($btime, $etime) = $rand_time[mt_rand(0, count($rand_time) - 1)];

            $res = Challenge::launch($uid, [
                'price' => $rand_price,
                'day' => $day[mt_rand(0, count($day) - 1)],
                'btime' => $btime,
                'etime' => $etime,
                'use_balance' => 1
            ]);

            dump($res);

            cache('challenge_auto_launch', ['update_time' => $time, 'next_time' => $time + mt_rand(10, 15) * 60]);
        }

        return true;
    }
    
    public function order_add()
    {
        $Order = new Order();
        
        $i_rand = mt_rand(16, 22);
        $goods = [];
        for ($i = 0; $i <= $i_rand; $i++) {
            $goods_item = Db::name('goods')
            ->field('id, goods_id, name, img, price, commission, coupon_price')
            ->limit(mt_rand(1, 10000), 1)
            ->select();
	        $goods[] = $goods_item[0];
        }
        
        $good_commission_data = Db::name('siteinfo')->where('key', 'good_commission')->value('value');
        $good_commission_data = json_decode($good_commission_data, true);
        
        foreach ($goods as $val) {
            $user_id = mt_rand(1000, 1999);
            $pay_nickname = Db::name('user')->where('id', $user_id)->value('nickname');
            
            $commission = $val['price'] * $val['commission'];
            $team_data = [
                [
                    'user_id' => 2112,
                    'level' => 3
                ]
            ];
            
            $team_data = $Order->calc_commission($commission * 0.5 / 1000, $team_data, $good_commission_data['commission_rule']);
            print_r($team_data);
            $add_data = [
                'order_no' => '1',
                'add_time' => mt_rand(request()->time() - 600, request()->time()),
                'modify_at_time' => 0,
                'order_amount' => $val['price'],
                'status' => 1,
                'valid_code' => 1,
                'platform_rebeat' => $val['commission'],
                'estimate_commission' => $commission,
                'platform_commission' => $commission,
                'user_id' => 0,
                'directly_user_id' => $team_data[0]['user_id'],
                'directly_supervisor_user_id' => 0,
                'user_commission' => 0,
                'directly_user_commission' => $team_data[0]['commission'],
                'directly_supervisor_user_commission' => 0,
                'p_id' => '70003_26907055',
                'team_level' => serialize($team_data),
                'type' => mt_rand(1, 100) < 70 ? 1 : 2
            ];
            $order_id = db('order_fake')->insertGetId($add_data);

            Db::name('order_goods')->insert([
                'order_id' => $order_id,
                'good_id' => ''.$val['goods_id'],
                'good_name' => $val['name'],
                'img' => $val['img'],
                'good_price' => $val['price'],
                'good_num' => 1,
                'quan_price' => 0,
                'type' => 2
            ]);
            
            if ($team_data[0]['user_id']) {
                // 清除缓存
                $forecast_income_cache_key = 'forecast_income_' . $team_data[0]['user_id'];
                Cache::rm($forecast_income_cache_key);
            
                // 添加系统通知
                db('user_dynamic')->insert([
                    'user_id' => 0,
                    'nickname' => '微选生活',
                    'avator' => '/static/img/logo.png',
                    'receive_uid' => $team_data[0]['user_id'],
                    'event' => 'order_commission',
                    'event_id' => $order_id,
                    'data' => serialize([
                        'title' => '订单收入通知  奖励 '. round($team_data[0]['commission'] / 100, 2) .'元',
                        'content' => '成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. date('Y-m-d') .'] 出单成功，您获得奖励'. round($team_data[0]['commission'] / 100, 2) .'元'
                    ]),
                    'status' => 1,
                    'add_time' => request()->time()
                ]);
            
                db('xinge_task')->insert([
                    'user_id' => $team_data[0]['user_id'],
                    'event' => 'order_commission',
                    'data' => serialize([
                        'title' => '订单收入通知  奖励 '. round($team_data[0]['commission'] / 100, 2) .'元',
                        'content' => '成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. date('Y-m-d') .'] 出单成功，您获得奖励'. round($team_data[0]['commission'] / 100, 2) .'元'
                    ])
                ]);
            
                /*
                // 免单订单添加系统通知
                $rs = Db::name('order')->field('id')->where([
                    ['user_id', '=', $team_data['user']['user_id']],
                    ['status', '<>', 3],
                ])->find();
                if ($rs['id'] == $order_id) {
                    db('user_dynamic')->insert([
                        'user_id' => 0,
                        'nickname' => '微选生活',
                        'avator' => '/static/img/logo.png',
                        'receive_uid' => $team_data['user']['directly_user_id'],
                        'event' => 'free_order_item',
                        'event_id' => $order_id,
                        'data' => serialize([
                            'title' => '免单补贴通知  奖励 '. ($val['order_amount'] > 500 ? 5 : round($val['order_amount'] / 100, 2)) .'元',
                            'content' => '直属成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. $create_date .']开始买了1单啦，您将获得免单补贴返现'. ($val['order_amount'] > 500 ? 5 : round($val['order_amount'] / 100, 2)) .'元'
                        ]),
                        'status' => 1,
                        'add_time' => request()->time()
                    ]);
            
                    db('xinge_task')->insert([
                        'user_id' => $team_data['user']['directly_user_id'],
                        'event' => 'free_order_item',
                        'data' => serialize([
                            'title' => '免单补贴通知  奖励 '. ($val['order_amount'] > 500 ? 5 : round($val['order_amount'] / 100, 2)) .'元',
                            'content' => '直属成员【'. ($pay_nickname ? $pay_nickname : '') .'】['. $create_date .']开始买了1单啦，您将获得免单补贴返现'. ($val['order_amount'] > 500 ? 5 : round($val['order_amount'] / 100, 2)) .'元'
                        ])
                    ]);
                }
                */
            }
        }
    }
    
    public function partner_add()
    {
        /*
        for ($i = 1000; $i <= 1999; $i++) {
            Db::name('user_tier')->insert([
                'user_id' => $i,
                'pid' => 2112,
                'add_time' => request()->time()
            ]);
        }
        exit;
        */
        $para = [
            'out_trade_no' => Order::order_sn_create(), // 订单号
            'use_balance' => 1,
            'pay_method' => 'ali',
            'type' => 2
        ];
        $res = GoodsModel::dredge_eduities(User::system_user_rand(), $para);
    }
    
    public function level_promote_commission_grant()
    {
        $promote_commission_data = Db::name('user_level_promote_commission_remind')
        ->field('id, user_id, level, add_time, status, remind_user_id, remind_user_condition, remind_user_commisson, remind_user_status, team_data')
        ->where('add_time', '<', request()->time() - 7200)
        ->select();

        foreach ($promote_commission_data as $val) {
            $res = User::level_promote_commission_grant($val);
        }
        
        return 'SUCCESS';
    }
    
    public function no_superior_dynamic()
    {
        $time = request()->time();
        $today_time = Common::today_time();
        if ($today_time + 28800 < $time && $time < $today_time + 79200) { // 指定时间(08:00~22:00)防骚扰
            $send_users = db()->query('select ul.user_id, ul.`level`, up.once_login_time, u.mobile from sl_user_level as ul, sl_user_promotion as up, sl_user as u where ul.user_id = up.user_id AND u.id = ul.user_id AND `level` < 3 AND NOT EXISTS ( select 1 from sl_user_tier where user_id = ul.user_id )');
            foreach ($send_users as $val) {
                if (!$val['once_login_time']) {
                    $val['once_login_time'] = '1543468455';
                }
                if ((request()->time() - $val['once_login_time'] > 3600) && ((request()->time() - $val['once_login_time'] - 3600) % 25200) < 600) {
                    // 仅在前十分钟发送通知
                    if ($val['level'] == 1) {
                        // 发送超级会员红包通知
                        Db::name('user_dynamic')->insert([
                            'user_id' => 0,
                            'nickname' => '微选生活',
                            'avator' => '/static/img/logo.png',
                            'receive_uid' => $val['user_id'],
                            'event' => 'level_redpack',
                            'event_id' => $val['user_id'],
                            'data' => serialize([
                                'title' => '亲，你又有一笔现金待领取哦~',
                                'content' => '你的好友刚刚已升级，你获得19.9元现金奖励，2小时后过期哦~'
                            ]),
                            'status' => 1,
                            'add_time' => request()->time()
                        ]);

                        Db::name('xinge_task')->insert([
                            'user_id' => $val['user_id'],
                            'event' => 'level_redpack',
                            'data' => serialize([
                                'title' => '亲，你又有一笔现金待领取哦~',
                                'content' => '你的好友刚刚已升级，你获得19.9元现金奖励，2小时后过期哦~'
                            ])
                        ]);
                    }
                    if ($val['level'] == 1 || $val['level'] == 2) {
                        // 发送vip红包通知
                        Db::name('user_dynamic')->insert([
                            'user_id' => 0,
                            'nickname' => '微选生活',
                            'avator' => '/static/img/logo.png',
                            'receive_uid' => $val['user_id'],
                            'event' => 'level_redpack',
                            'event_id' => $val['user_id'],
                            'data' => serialize([
                                'title' => '亲，你又有一笔现金待领取哦~',
                                'content' => '你的好友刚刚已升级，你获得120元现金奖励，2小时后过期哦~'
                            ]),
                            'status' => 1,
                            'add_time' => request()->time()
                        ]);

                        Db::name('xinge_task')->insert([
                            'user_id' => $val['user_id'],
                            'event' => 'level_redpack',
                            'data' => serialize([
                                'title' => '亲，你又有一笔现金待领取哦~',
                                'content' => '你的好友刚刚已升级，你获得120元现金奖励，2小时后过期哦~'
                            ])
                        ]);

                        // 当天是否有发送过短信
                        $todaytime = Common::today_time();
                        $rs = Db::name('activity_pick_new_cash_remind')->where([
                            ['user_id', '=', $val['user_id']],
                            ['add_time', 'between', [$todaytime, $todaytime + 86400]]
                        ])->find();
                        // 发送短信通知
                        if (!$rs && $val['mobile'] && ($val['once_login_time'] > request()->time() - 432000)) {
                            $realnames = db('user_withdraw')
                                ->where([['user_id', '=', $val['user_id']]])
                                ->value('real_name');

                            $sms = new \dysms();
                            $res = $sms->send([
                                'TemplateCode' => 'SMS_153325849',
                                'TemplateParam' => [
                                    'price' => 120,
                                    'nickname' => empty($realnames) ? '亲' : $realnames
                                ],
                                'PhoneNumbers' => $val['mobile']
                            ]);

                            if (!empty($res['Code']) || $res['Code'] != 'OK') {
                                Log::record('dysms send fail,' . json_encode($res, JSON_UNESCAPED_UNICODE));
                                continue;
                            }

                            Db::name('activity_pick_new_cash_remind')->insert([
                                'user_id' => $val['user_id'],
                                'add_time' => request()->time(),
                                'type' => 2
                            ]);
                        }
                    }
                }
            }
        }
        return 'SUCCESS';
    }

    public function po_buy_one_activity_goods()
    {
        $ddk_cate = [1, 4, 13, 14, 15, 16, 18, 743, 818, 1281, 1451];
        Db::name('actvity_goods')->delete(true);

        foreach ($ddk_cate as $cate_id) {
            $goods_data = Db::name('goods')->where([
                ['price', '>', 800],
                ['price', '<', 3000],
                ['sales_num', '>', 300],
                ['cid', '=', $cate_id]
            ])->field('goods_id, name as goods_name, img as goods_img, price, org_price, coupon_price, cid')
                ->limit(100)
                ->select();
            foreach ($goods_data as &$val) {
                $val['snap_up_count'] = mt_rand(100, 1000);
            }

            Db::name('actvity_goods')->insertAll($goods_data, true);
        }
    }
}