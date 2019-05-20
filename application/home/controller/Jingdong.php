<?php

namespace app\home\controller;

use think\Db;
use app\common\model\Sync;
use think\facade\Request;
use function GuzzleHttp\json_decode;

class Jingdong
{
    public function get_code()
    {
        $jos = new \jos();
        $jos->get_code();
    }
    
    public function index()
    {
        $jos = new \jos();
        $jos->oauth();
    }
    
    // 请求淘宝客接口
    public function get_json($method, $data){
        //参数数组
        $paramArr = [
            'method' => $method,
            //'access_token' => $this->refresh_access_token(),
            'app_key' => $this->client_id,
            'timestamp' => date('Y-m-d H:i:s'),
            'format' => 'json',
            'sign_method' => 'md5',
            'v' => '1.0',
            'param_json' => json_encode($data)
        ];
    
        //生成签名
        $sign = $this->createSign($paramArr);
        //组织参数
        $strParam = $this->createStrParam($paramArr);
        $strParam .= 'sign='.$sign;
        //访问服务
        $url = 'https://router.jd.com/api?'.$strParam;
        $result = $this->curl($url, [], 'POST');
        $result = json_decode($result, true);
    
        return $result;
    }
    
    public function aa()
    {
        $jos = new \jos();

//         $data = [
//             'wareId' => '18720404475',
//             'colorId' => '0000000000'
//         ];
//         $res = $jos->get_json('jingdong.image.read.findImagesByColor', $data);print_r($res);exit;
//         print_r(json_decode($res['jingdong_UnionService_queryOrderList_responce']['result'], true));exit;
        
        $data = [
            'proCont' => '1',
            'materialIds' => '32250609693',
            'positionId' => 1465410820,
            'subUnionId' => '200012345'
        ];
        $res = $jos->get_json('jingdong.service.promotion.wxsq.getCodeBySubUnionId', $data);
        print_r(json_decode($res['jingdong_service_promotion_wxsq_getCodeBySubUnionId_responce']['getcodebysubunionid_result'], true));exit;

        /*
        // 爆款
        $data = [
            'from' => 0,
            'pageSize' => 100
        ];
        $res = $jos->get_json('jingdong.UnionThemeGoodsService.queryExplosiveGoods', $data);
        print_r(json_decode($res['jingdong_UnionThemeGoodsService_queryExplosiveGoods_responce']['queryExplosiveGoods_result'], true));exit;
        */
        // 分类
        //$jd_cate = [1320,12218,1319,6233,13678,1713,6196,9847,15901,1620,15248,9192,12259,12367,1315,1316,16750,6144,11729,17329,670,6728,12379,737,5025,9987,652,1318];
        $cid1 = Request::get('cid1');//[1315];
        //foreach ($jd_cate as $cid1) {
            $data = [
                'parent_id' => $cid1,
                'grade' => 1
            ];
            $res = $jos->get_json('jingdong.union.search.goods.category.query', $data);
            $res = json_decode($res['jingdong_union_search_goods_category_query_responce']['querygoodscategory_result'], true);
            foreach ($res['data'] as $cid2) {
                $data = [
                    'parent_id' => $cid2['id'],
                    'grade' => 2,
                ];
                $res = $jos->get_json('jingdong.union.search.goods.category.query', $data);
                $res = json_decode($res['jingdong_union_search_goods_category_query_responce']['querygoodscategory_result'], true);
                foreach ($res['data'] as $cid3_key=>&$cid3) {
                    $cid3['pid_name'] = $cid2['name'];
                    // 
                    $data = [
                        'pageIndex' => 1,
                        'pageSize' => 1,
                        'cid3' => $cid3['id']
                    ];
                    $goods_data = $jos->get_json('jingdong.union.search.queryCouponGoods', $data);
                    $goods_data = json_decode($goods_data['jingdong_union_search_queryCouponGoods_responce']['query_coupon_goods_result'], true);
                    $cid3['total'] = $goods_data['total'];
                    if ($cid3['total'] == 0) {
                        unset($res['data'][$cid3_key]);
                    }
                    //print_r($goods_data);exit;
                }
                print_r($res);
            }
        //}
        exit;
        
        // 获取商品详情
        /*
        if (!$info && $sku_id) {
            $data = [
                'skuIdList' => $sku_id
            ];
            $res = $Jos->get_json('jingdong.union.search.queryCouponGoods', $data);
            if ($res['jingdong_union_search_queryCouponGoods_responce']['code'] != 0) {
                return $this->response([], '商品优惠券已抢光！', 40001);
            }
        
            $res = json_decode($res['jingdong_union_search_queryCouponGoods_responce']['query_coupon_goods_result'], true);
            if ($res['resultCode'] != 1) {
                return $this->response([], '商品优惠券已抢光！', 40001);
            }
            if (!isset($res['data']) || empty($res['data'])) {
                return $this->response([], '商品优惠券已抢光！', 40001);
            }
        
            $info = [
                'sku_id' => ''.$res['data'][0]['skuId'],
                'name' => $res['data'][0]['skuName'],
                'org_price' => $res['data'][0]['wlPrice'] * 100,
                'coupon_price' => $res['data'][0]['couponList'][0]['discount'] ?: 0,
                'coupon_time' => $res['data'][0]['couponList'][0]['endTime'] / 1000 ?: 0,
                'commission' => $res['data'][0]['commissionShare'] * 10 ?: 0,
                'price' => ($res['data'][0]['wlPrice'] - $res['data'][0]['couponList'][0]['discount']) * 100,
                'img' => $res['data'][0]['imageurl'],
            ];
            $add_data = [
                'coupon_info' => json_encode($res['data'][0]['couponList']),
                'vid' => ''.$res['data'][0]['vid'] ?: 0,
                'cid' => $res['data'][0]['cid'] ?: 0,
                'cid2' => $res['data'][0]['cid2'] ?: 0,
                'cid3' => $res['data'][0]['cid3'] ?: 0,
                'cid_name' => $res['data'][0]['cidName'] ?: ''
            ];
        
            $id = db('goods_jd')->insertGetId(array_merge($info, $add_data));
            //$res = db('goods')->insert(array_merge($info, $add_data));
            if (!$id) {
                return $this->response([], '商品不存在', 40001);
            }
            $info['id'] = $id;
        }
        */
        $data = [
            'materialIds' => '14064653456',
            'subUnionId' => '200012345',
            'couponUrl' => urlencode('https://coupon.jd.com/ilink/couponSendFront/send_index.action?key=ff06a6c655ea4c73bc612c6abcd8906a&roleId=13938922&to=mdikawe.jd.com'),
            'positionId' => 1465410820,
            //'pid' => '1000767623_1329957096_1465410820'
        ];
        $res = $jos->get_json('jingdong.service.promotion.coupon.getCodeBySubUnionId', $data);
        print_r($res);
    }
    
    public function bb()
    {
        $sync = new Sync();
        
        $sync->get_jd_order();exit;
        $sync->get_jd_goods();
    }
    public function cc()
    {
        $data = [
            'couponUrl' => urlencode('https://coupon.jd.com/ilink/couponSendFront/send_index.action?key=ff06a6c655ea4c73bc612c6abcd8906a&roleId=13938922&to=mdikawe.jd.com'),
            'materialIds' => '14064653456',
            'unionId' => '1000767623',
            //'positionId' => '1465410820'
            //'pid'
        ];
        
        $para = http_build_query($data);

        print_r(file_get_contents('https://jd.open.apith.cn/union/getCouponCodeByUnionId?'. $para));
    }
    
    public function dd()
    {
        $Ddk = new \ddk();
        
        $data = [
            'type' => 'pdd.goods.cats.get',
            'parent_cat_id' => '0'
        ];
        $res = $Ddk->get_goods($data);
        print_r($res);
    }
    
    public function ff()
    {
        //34309667881
        
        $Jos = new \jos();
        $data = [
            'skuIdList' => '[34309667881]'
        ];
        $res = $Jos->get_json('jingdong.union.search.queryCouponGoods', $data);
        $res = json_decode($res['jingdong_union_search_queryCouponGoods_responce']['query_coupon_goods_result'], true);
        print_r($res);
    }
}