<?php

namespace app\home\controller;

class Genaral extends Base
{
    protected function initialize()
    {
    }

    public function problem_list()
    {
        $cate = db('problem_cate')->order('sort', 'ASC')->select();

//        $this->assign('cate', $cate);
//        return $this->fetch();

        return view('', ['cate' => $cate]);
    }

    public function problem_info()
    {
        $cate_id = input('get.cate_id');
        $list = db('problem_list')->where('cate_id', $cate_id)->order('sort', 'asc')->select();

//        $this->assign('list', $list);
//        return $this->fetch();

        return view('', ['list' => $list]);
    }

    public function order_query()
    {
        $out_trade_no = input('out_trade_no');
        if (empty($out_trade_no)) {
            return $this->response('', '', 1);
        }

        $data = db('pay')->where('out_trade_no', '=', $out_trade_no)->find();
        if (!empty($data) && $data['result_code'] === 'SUCCESS') {

//            $event = input('event');
//            switch ($event) {
//                case 'room_add':
//                    return $this->response(['room_id', 1]);
//                    break;
//                default :
//                    break;
//            }

            return $this->response();
        }

        return $this->response('', '', 1);
    }

    public function agreement()
    {
        $html = db('siteinfo')->where([['key', '=', 'agreement']])->value('value');
        return view('', ['html' => $html]);
    }

    public function index()
    {

    }
}