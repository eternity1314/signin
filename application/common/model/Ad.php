<?php

namespace app\common\model;

use think\Db;

class Ad
{
    // 打卡广告
    public function get_ad_signin($user_id = 0)
    {
        $where = $this->get_valid_filter($user_id, 'signin');

        $ad = Db::name('ad')->where($where)->field('ad_id,ad_title,ad_pic AS img')->order('rand()')->find();

        if (empty($ad)) {
            $ad = D('Signin')->get_one_good();
            $ad['link'] = U('promotion/detail') . '&id=' . $ad['id'];
            $ad['type'] = 'goods';
            unset($ad['id']);
        } else {
            $ad['link'] = U('Mpwx/Ad/read') . '&ad_id=' . $ad['ad_id'] . '&event=signin';
            $ad['type'] = 'ad';
            $ad['img'] = SITE_URL . __PUBLIC__ . $ad['img'];
            unset($ad['ad_id']);
        }

        return $ad;
    }

    // 福利首页广告
    public function get_ad_promotion($user_wxid = 0)
    {
        $where = $this->get_valid_filter($user_wxid, 'promotion');

        $ad = M('ad')->where($where)->field('ad_id,ad_pic AS pic,ad_link AS link')->limit(8)->select();

        $count = count($ad);
        if ($count < 8) {
            $data = M('dtk_ad')->field('id, pic, link')
                ->where(['category_id' => 1])->order('sort asc,id desc')
                ->limit(8 - $count)->select();

            if (!empty($data)) {
                $ad = array_merge($ad, $data);
            }
        }

        return $ad;
    }

    public function get_valid_filter($user_wxid = 0, $position = '')
    {
        $today_time = strtotime(date('Y-m-d'));
        $now_time = intval(date('Hi'));

        $where = [
            'status' => 1,
            'pv_max' => ['exp', '> pv'],
            'start_time' => ['elt', $now_time],
            '_string' => '(end_time =0 OR end_time >= ' . $now_time . ')'
        ];

        if ($position) {
            $where['_string'] .= 'AND (LOCATE("' . $position . '", position) > 0)';
        }

        if ($user_wxid) {
            $tbname = M('ad_read')->getTableName();

            $where['_string'] .= 'AND ad_id NOT IN(
                SELECT ad_id FROM ' . $tbname
                . ' WHERE user_wxid = ' . $user_wxid
                . ' AND add_time >= ' . $today_time
                . ' AND add_time <' . ($today_time + 86400) . ')';
        }

        return $where;
    }
}