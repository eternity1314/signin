<?php

namespace app\home\controller;

use think\Db;
use app\common\model\Order;
use app\common\model\GoodsModel;
use think\facade\Env;
use think\exception\HttpResponseException;
use think\facade\Request;

class Goods extends Base
{
    public function initialize()
    {
        $this->user_id = session('user.id');
        if (empty($this->user_id)) {
            // 验证签名
            if (!empty(input('sign'))) {
                $this->checkSign();

                // 验证令牌
                $this->checkToken();
            }
        }

        $action_name = request()->action();
        if (empty($this->user_id) && strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            $user = \app\common\model\User::wxOauthUser();
            if ($user === false) throw new HttpResponseException(json()->content('redirect'));

            $this->user_id = $user['id'];
            $this->openid = $user['openid'];

            session('user',
                [
                    'id' => $user['id'],
                    'openid' => $user['openid'],
                    'unionid' => $user['unionid'],
                    'is_new_user' => isset($user['add_time']) ? 0 : 1
                ]
            );
        }

        // deny users
        //        if (in_array($this->user_wxid, [])) {
        //            $this->error('', '请稍后...', 10011);
        //        }

        // $this->openid = session('user.openid');
        Env::set('ME', $this->user_id);
    }

    public function user_equities()
    {
        if (empty($this->user_id)) parent::initialize();

        $expire_time = Db::name('user_promotion')->where('user_id', $this->user_id)->value('expire_time');
        if ($expire_time && $expire_time > request()->time()) {
            $expire_time = '有效截止：' . date('Y-m-d', $expire_time);
            $has_equities = 1;
            $partner_type = \app\common\model\User::partner_type($this->user_id);
        } else {
            $expire_time = '你已获得升级合伙人资格';
            $has_equities = 0;
            $partner_type = false;
        }

        $user = \app\common\model\User::find($this->user_id);

        return view('', [
            'expire_time' => $expire_time,
            'has_equities' => $has_equities,
            'partner_type' => empty($partner_type) ? '' : $partner_type,
            'link_invite' => url('home/user/invite'),
            'room_fee' => $partner_type == 'month' ? 99 : 999,
            'room_income' => $partner_type == 'month' ? 405 : 8773,
            'free_order_count' => $partner_type == 'month' ? 5 : 10,
            'free_order_income' => $partner_type == 'month' ? 412.5 : 12875,
            'free_order_quota' => $partner_type == 'month' ? 5 : 18,
            'free_order_content' => $partner_type == 'month' ? '直属升级送5个/人，自己升级送5个/人，每人总价值>50元' : '直属升级送10个/人，非直属8个/人，自己升级送10个/次，每人总价值>140元',
            'time_limit' => $partner_type == 'month' ? '月' : '年',
            'user' => $user
        ]);
    }

    public function user_equities_goods()
    {
        $limit = 10;
        $where = [['coupon_price', '>=', 5000]];
        $next_id = input('next_id', 0, 'intval');
        if ($next_id <= 0) $next_id = 1;

        $goods = Db::name('goods')
            ->where($where)
            ->field('id,goods_id,img,name,coupon_price,sales_num,price,commission')
            ->order('coupon_price desc')
            ->limit(($next_id - 1) * $limit, $limit)
            ->select();

        if (count($goods) < $limit) $next_id = -1;
        else $next_id++;

        foreach ($goods as &$val) {
            // 佣金计算
            $val['commission'] = sprintf('%.2f', ($val['price'] * $val['commission'] * GoodsModel::config('return_commission')) / 100000);
            $val['price'] = sprintf('%.2f', $val['price'] / 100);
        }

        return json(['result' => $goods, 'next_id' => $next_id]);
    }

    // 会员特权
    public function dredge_eduities()
    {
        return $this->response([], 'fail', 40001);

        $para = input('post.');
        $para['out_trade_no'] = Order::order_sn_create(); // 订单号

        $res = GoodsModel::dredge_eduities($this->user_id, $para);

        return call_user_func_array([$this, 'response'], $res);
    }

    // 如何使用
    public function free_order_use()
    {
        $pid = db('user_tier')->where('user_id', $this->user_id)->value('pid');
        if ($pid) {
            $mobile = db('user')->where('id', $pid)->value('mobile');
        }

        return view('', [
                'mobile' => empty($mobile) ? 'kangnaixin1989' : $mobile,
                'wx_share' => [
                    'title' => '亿元免单补贴大型活动',
                    'desc' => '亿元免单补贴大型活动',
                    'imgUrl' => config('site.url') . '/static/img/wx_share.png',
                    'link' => url('', '', true, true)
                ]
            ]
        );
    }

    public function free_goods_share()
    {
        $goods_id = input('goods_id');
        if (!$goods_id) {
            return $this->response([], '商品已抢光', 40001);
        }

        $info = db('goods')->field('id, goods_id, name, org_price, coupon_price, commission, price, img, sales_num, tao_pass, type')
            ->where([['goods_id', '=', $goods_id]])
            ->find();
        if (!$info) {
            return $this->response([], '商品已抢光', 40001);
        }

        $info['org_price'] = sprintf('%.2f', $info['org_price'] / 100);
        $info['price'] = sprintf('%.2f', $info['price'] / 100);

        if (strpos($info['img'], 'http://') === false && strpos($info['img'], 'https://') === false) {
            $info['img'] = 'https:' . $info['img'];
        }

        $info['good_quan_link'] = '';
        if ($this->user_id) {
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
                'goods_id_list' => '[' . $info['goods_id'] . ']',
                'generate_short_url' => 'true',
                'multi_group' => 'true'
            ];
            $rs = $ddk->get_goods($data);
            if (isset($rs['error_response']) || !isset($rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0])) {
                return $this->response([], '商品已抢光', 40001);
            }
            $info['good_quan_link'] = $rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0]['we_app_web_view_url'];

            $is_new_user = session('user.is_new_user');
            $invite_code = input('invite_code');
            $pid = db('user_promotion')->where('invite_code', $invite_code)->value('user_id');
            if ($is_new_user) {
                // 绑定上级
                $rs = db('user_tier')->where('user_id', $this->user_id)->count();
                if ($rs == 0) {
                    if ($invite_code) {
                        if ($pid && $pid != $this->user_id) {
                            db('user_tier')->insert([
                                'user_id' => $this->user_id,
                                'pid' => $pid,
                                'add_time' => request()->time()
                            ]);
                        }
                    }
                }
                //db('user_promotion')->where('user_id', $this->user_id)->update(['is_couple_weal' => 1]);
            }

            if ($this->user_id != $pid) {
                // 潜在市场
                $rs = db('goods_free_share_user')->where([
                    ['user_id', '=', $this->user_id],
                    ['pid', '=', $pid]
                ])->find();
                if (!$rs) {
                    db('goods_free_share_user')->insert([
                        'user_id' => $this->user_id,
                        'pid' => $pid,
                        'add_time' => request()->time()
                    ]);
                }
            }
        }

        // 用户信息
        $user_info = [
            'nickname' => input('nickname'),
            'avator' => base64_decode(input('avator'))
        ];

        // 其他商品
        $goods_data = db('goods')->where('price', '<=', 500)
            ->field('id, goods_id, name, img')
            ->orderRaw('if(coupon_price = 0, 1, 0), id desc')
            ->limit(6)
            ->select();
        return view('', ['goods_info' => $info, 'user_info' => $user_info, 'goods_data' => $goods_data]);
    }

    public function free_goods_share_jd()
    {
        $goods_id = input('goods_id');
        if (!$goods_id) {
            return $this->response([], '商品已抢光', 40001);
        }

        $info = db('goods_jd')->field('id, sku_id as goods_id, name, org_price, coupon_price, coupon_info, commission, price, img')
            ->where([['sku_id', '=', $goods_id]])
            ->find();
        if (!$info) {
            return $this->response([], '商品已抢光', 40001);
        }

        $info['org_price'] = sprintf('%.2f', $info['org_price'] / 100);
        $info['price'] = sprintf('%.2f', $info['price'] / 100);
        $info['coupon_info'] = json_decode($info['coupon_info'], true);

        $info['good_quan_link'] = '';
        if ($this->user_id) {
            $Jos = new \jos();

            // 获取用户推广位 p_id
            $sub_unionid = db('user_promotion')->where('user_id', $this->user_id)->value('sub_unionid');
            if (!$sub_unionid) {
                // 生成购买链接
                $sub_unionid = $this->user_id . mt_rand(1000, 9999);

                db('user_promotion')->where('user_id', $this->user_id)->update(['sub_unionid' => $sub_unionid]);
            }

            // 必要参数
            $data = [
                'materialIds' => $info['goods_id'],
                'subUnionId' => $sub_unionid,
                'couponUrl' => urlencode($info['coupon_info'][0]['link']),
                'positionId' => 1465410820,
                //'pid' => '1000767623_1329957096_1465410820'
            ];
            $res = $Jos->get_json('jingdong.service.promotion.coupon.getCodeBySubUnionId', $data);
            $res = json_decode($res['jingdong_service_promotion_coupon_getCodeBySubUnionId_responce']['getcodebysubunionid_result'], true);
            if (!$res['urlList'][$info['coupon_info'][0]['link'] . ',' . $info['goods_id']]) {
                $data = [
                    'proCont' => '1',
                    'materialIds' => $info['goods_id'],
                    'positionId' => 1465410820,
                    'subUnionId' => $sub_unionid
                ];
                $good_res = $Jos->get_json('jingdong.service.promotion.wxsq.getCodeBySubUnionId', $data);
                $good_res = json_decode($good_res['jingdong_service_promotion_wxsq_getCodeBySubUnionId_responce']['getcodebysubunionid_result'], true);
                if (!$good_res['urlList'][$info['goods_id']]) {
                    return $this->response([], '商品优惠券已抢光', 40001);
                }

                // 单品url
                $info['good_quan_link'] = $good_res['urlList'][$info['goods_id']];
            } else {
                // 券商url
                $info['good_quan_link'] = $res['urlList'][$info['coupon_info'][0]['link'] . ',' . $info['goods_id']];
            }

            $is_new_user = session('user.is_new_user');
            $invite_code = input('invite_code');
            $pid = db('user_promotion')->where('invite_code', $invite_code)->value('user_id');
            if ($is_new_user) {
                // 绑定上级
                $rs = db('user_tier')->where('user_id', $this->user_id)->count();
                if ($rs == 0) {
                    if ($invite_code) {
                        if ($pid && $pid != $this->user_id) {
                            db('user_tier')->insert([
                                'user_id' => $this->user_id,
                                'pid' => $pid,
                                'add_time' => request()->time()
                            ]);
                        }
                    }
                }
                //db('user_promotion')->where('user_id', $this->user_id)->update(['is_couple_weal' => 1]);
            }

            // 谁看过我的免单
            if ($pid) {
                $rs = db('goods_free_share_user')->where([
                    ['user_id', '=', $this->user_id],
                    ['pid', '=', $pid]
                ])->find();
                if (!$rs) {
                    db('goods_free_share_user')->insert([
                        'user_id' => $this->user_id,
                        'pid' => $pid,
                        'add_time' => request()->time()
                    ]);
                }
            }
        }

        // 用户信息
        $user_info = [
            'nickname' => input('nickname'),
            'avator' => base64_decode(input('avator'))
        ];

        // 其他商品
        $goods_data = db('goods_jd')->where('price', '<=', 500)
            ->field('id, sku_id as goods_id, name, img')
            ->orderRaw('if(coupon_price = 0, 1, 0), id desc')
            ->limit(6)
            ->select();
        return view('', ['goods_info' => $info, 'user_info' => $user_info, 'goods_data' => $goods_data]);
    }

    public function quan_link_pdd()
    {
        $id = Request::get('id', 0);
        $goods_id = Request::get('goods_id', 0);

        if ($goods_id) {
            $map = [['goods_id', '=', $goods_id]];
        } else {
            $map = [['id', '=', $id]];
        }

        // 商品详情
        $info = GoodsModel::field('id, goods_id, name, org_price, coupon_price, commission, price, img, cid, sales_num, good_quan_link, good_quan_link_h5, tao_pass, type, coupon_time')
            ->where($map)
            ->find();

        if (empty($info)) {
            if ($goods_id) {
                $Ddk = new \ddk();
                $data = [
                    'type' => 'pdd.ddk.goods.detail',
                    'goods_id_list' => '[' . $goods_id . ']',
                ];
                $res = $Ddk->get_goods($data);
                if (isset($res['error_response']) || !$res['goods_detail_response']['goods_details']) {
                    return $this->response([], '晚来一步，商品下架了，请前往兑现吧~', 40001);
                }

                $info = [
                    'goods_id' => '' . $res['goods_detail_response']['goods_details'][0]['goods_id'],
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
                if (!$res) {
                    return $this->response([], '商品不存在', 40001);
                }

                $info['id'] = $res->id;
            } else {
                return $this->response([], '商品不存在', 40001);
            }
        }

        // 商品市场价
        $info['org_price'] = sprintf('%.2f', $info['org_price'] / 100);
        $info['price'] = sprintf('%.2f', $info['price'] / 100);
        $info['coupon_price'] = intval($info['coupon_price'] / 100);
        $info['coupon_time'] = date('Y-m-d', $info['coupon_time']);

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
            'goods_id_list' => '[' . $info['goods_id'] . ']',
            'generate_short_url' => 'true',
            'multi_group' => 'true'
        ];
        $rs = $ddk->get_goods($data);
        if (isset($rs['error_response']) || !isset($rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0])) {
            return $this->response([], '商品不存在', 40001);
        }
        $good_quan_link = [
            'app' => $rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0]['mobile_short_url'],
            'h5' => $rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0]['short_url'],
            'pdd_url' => '',
            'app_long_url' => $rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0]['url'],
            'pdd_active_url' => str_replace('https', 'pinduoduo', $rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0]['url']),
            'we_app_web_view_short_url' => $rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0]['we_app_web_view_short_url'],
            'we_app_web_view_url' => $rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0]['we_app_web_view_url']
        ];
        // 拼接跳转拼多多App链接
        $pdd_para = substr($rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0]['mobile_url'], strpos($rs['goods_promotion_url_generate_response']['goods_promotion_url_list'][0]['mobile_url'], '%3F') + 3);

        if (substr($pdd_para, -15) === 'duoduo_type%3D3') {
            $good_quan_link['pdd_url'] = 'pinduoduo://com.xunmeng.pinduoduo/duo_coupon_landing.html?' . $pdd_para;
        }

        $good_quan_link['url'] = $good_quan_link['we_app_web_view_short_url'];
        $info['good_quan_link'] = $good_quan_link;

        return $this->response(['info' => $info]);
    }

    public function quan_link_jd()
    {
        $id = Request::get('id', 0);
        $Jos = new \jos();

        // 商品详情
        $info = Db::name('goods_jd')->field('id, sku_id, name, org_price, coupon_price, coupon_info, commission, price, img, cid, coupon_time')
            ->where([['id', '=', $id]])
            ->find();
        if (!$info) {
            return $this->response([], '商品优惠券已抢光', 40001);
        }

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
            $info = [
                'has_coupon' => 0,
                'good_quan_link' => [
                    'url' => $good_res['urlList'][$info['sku_id']],
                ]
            ];
        } else {
            // 券商url
            $info = [
                'has_coupon' => 1,
                'good_quan_link' => [
                    'url' => $res['urlList'][$info['coupon_info'][0]['link'] . ',' . $info['sku_id']] ?: '',
                ]
            ];
        }

        return $this->response(['info' => $info]);
    }
}