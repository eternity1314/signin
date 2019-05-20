<?php

namespace app\home\controller;

use app\common\model\Common;
use \app\common\model\User;

class Redpack extends Base
{
    public function send()
    {
        switch (request()->method()) {
            case 'POST':
                $res = \app\common\model\Redpack::send($this->user_id, input());
                if (empty($res['redpack_id'])) return call_user_func_array([$this, 'response'], $res);

                return $this->response($res);
                break;
            default:
                $data = db('redpack_send')
                    ->where([['user_id', '=', $this->user_id]])
                    ->field('show,receive,share,transfer,pic')
                    ->order('redpack_id desc')
                    ->find();

                if (empty($data)) $data = ['show' => 1, 'receive' => 'room', 'share' => 0, 'transfer' => 'account'];

                $data['show_text'] = $data['show'] ? '显示在本族群' : '不显示在本族群';
                $data['receive_text'] = $data['receive'] == 'room' ? '本族成员领取' : ($data['receive'] == 'pinduoduo' ? '拼多多用户领取' : '看到的人就能领');
                $data['share_text'] = $data['share'] ? '分享后才能领取' : '无需分享就能领';
                $data['transfer_text'] = $data['transfer'] == 'wx' ? '微信钱包' : '微选钱包';

                return view('', ['user' => User::me(), 'data' => $data]);
                break;
        }
    }

    public function draw()
    {
        $redpack_id = input('redpack_id', 0, 'intval');
        if (empty($redpack_id)) {
            return $this->response('', '数据有误！', 1);
        }

        if (request()->isPost()) {
            $assign_id = input('assign_id', 0, 'intval');
            $res = \app\common\model\Redpack::assign_draw($redpack_id, $this->user_id, $assign_id);
            if (empty($res['assign_id'])) {
                return call_user_func_array([$this, 'response'], $res);
            }

//                elseif ($res['errcode'] == 105) {
//                    $ad = M('ad')->where(['ad_id' => $res['redpack']['ad_id'], 'status' => 1])->field('ad_id,ad_pic,ad_link')->find();
//                    if (!empty($ad)) {
//                        $res['ad'] = $ad;
//                        $res['assign'] = [
//                            'assign_id' => $res['assign']['assign_id'],
//                            'balance' => $res['redpack']['event'] == 'rmb' ? $res['assign']['balance'] / 100 : $res['assign']['balance'],
//                            'event' => $res['redpack']['event']
//                        ];
//                    } else {
//                        $this->error('', '', ['errmsg' => '手慢了，红包派完了']);
//                    }
//                }

//                elseif ($res['errcode'] == 101) {
//                    // 赞助商红包
//                    if (!empty($redpack) && !empty($redpack['ad_id'])) {
//                        $redpack = M('redpack_send')->where(
//                            [
//                                'class_id' => $redpack['class_id'],
//                                'surplus' => ['gt', 0],
//                                'ad_id' => ['gt', 0]
//                            ]
//                        )->field('redpack_id,sponsor_name AS nickname,sponsor_head AS headimgurl')->find();
//
//                        if (!empty($redpack)) {
//                            $res['redpack'] = $redpack;
//                        }
//                    }
//                }
//
            if (isset($res['redpack']) && $res['redpack']['redpack_id'] == $redpack_id) {
                unset($res['redpack']);
            }

            return $this->response();
        }

        return $this->response(); // '', '', ['errmsg' => '红包已进账']
    }

    public function info()
    {
        $redpack_id = input('redpack_id', 0, 'intval');
        if (empty($redpack_id)) {
            return $this->response('', '信息错误！', 1);
        }

        $data_redpack = db('redpack_send')->where('redpack_id', '=', $redpack_id)->find();
        if (empty($data_redpack)) {
            return $this->response('', '信息错误！', 1);
        }

        if ($data_redpack['event'] == 'rmb') {
            $data_redpack['price'] /= 100;
        }

        if ($data_redpack['draw_time'] > 0) {
            if ($data_redpack['draw_time'] < 60) {
                $data_redpack['draw_time_format'] = $data_redpack['draw_time'] . '秒';
            } elseif ($data_redpack['draw_time'] < 3600) {
                $data_redpack['draw_time_format'] = round($data_redpack['draw_time'] / 60) . '分';
            } else {
                $data_redpack['draw_time_format'] = round($data_redpack['draw_time'] / 3600) . '小时';
            }
        }

        if ($data_redpack['user_id']) {
            $user = User::find($data_redpack['user_id']);
            if (!empty($user)) {
                $data_redpack = array_merge($data_redpack, $user);
            }
        } elseif ($data_redpack['ad_id']) {
            $ad = db('ad')->where('ad_id', '=', $data_redpack['ad_id'])
                ->field('sponsor_name AS nickname,sponsor_head AS headimgurl,ad_id')
                ->find();
            if (!empty($ad)) {
                $data_redpack = array_merge($data_redpack, $ad);
//                $data_redpack['ad_link'] = U('Mpwx/Ad/read') . '&ad_id=' . $ad['ad_id'] . '&event=redpack_info';
            }
        }

        $data_out = [];
        $data_assign = db('redpack_assign')
            ->where([['redpack_id', '=', $redpack_id], ['draw_time', 'gt', 0]])
            ->order('draw_time DESC')->select();

        if (!empty($data_assign)) {
            $user_id = [];
            foreach ($data_assign as $key => $item) {
                if ($data_redpack['event'] == 'rmb') {
                    $item['price'] /= 100;
                }

                if ($item['user_id'] == $this->user_id) {
                    $data_out['mine'] = $item;
                }

                $item['draw_time'] = Common::time_format($item['draw_time']);

                $data_assign[$key] = $item;
                $user_id[] = $item['user_id'];
            }

            $user = db('user')->where('id', 'in', $user_id)->column('id,nickname,avator');

            foreach ($user as $item) {
                $user_assign[$item['id']] = $item;
            }

            $data_out['assign'] = $data_assign;
            $data_out['user_assign'] = $user_assign;
        }

        $data_out['redpack'] = $data_redpack;
        return view('', $data_out);
    }

    public function mysend()
    {
        $data = db('redpack_send')->where([['user_id', '=', $this->user_id]])->order('redpack_id desc')->select();
        return view('', ['data' => $data]);

        // 'link' => url('home/challenge/redpack', '', '', true)]
        // {$link}?room_id={$item['room_id']}&redpack_id={$item['redpack_id']}
    }

    public function auto()
    {
        switch (request()->method()) {
            case 'POST':
                $data = input();
                if (empty($data['room_id']) || empty($room_id = intval($data['room_id']))) {
                    return $this->response('', 'param error', 1);
                }

                if (empty($data['grant'])) return $this->response('', '请选择发放M币', 1);

                list($data['mb'], $data['num']) = explode('_', $data['grant']);
                if (empty($data['mb'] = intval($data['mb'])) || empty($data['num'] = intval($data['num']))) {
                    $this->response('', '请选择发放M币', 1);
                }

                $data['auto'] = empty($data['auto']) ? 0 : 1;
                $data['edit_time'] = request()->time();

                $where = [['user_id', '=', $this->user_id], ['room_id', '=', $room_id]];
                $res = db('redpack_auto')->where($where)->strict(false)->update($data);
                if (!$res) {
                    $count = db('redpack_auto')->where($where)->count(1);
                    if ($count == 0) {
                        $data['user_id'] = $this->user_id;
                        $res = db('redpack_auto')->strict(false)->insert($data);
                    }
                }

                if ($res) return $this->response(['url' => url('home/challenge/redpack') . '?room_id=' . $room_id], '保存成功');
                else return $this->response('', '保存失败，请重试', 1);
                break;
            default:
                $room_id = input('room_id', 0, 'intval');
                if (empty($room_id)) {
                    return $this->response('', 'param error', 1);
                }

                $grant = [
                    [100, 10],
                    [1000, 50],
                    [5000, 100],
                ];

                $data = db('redpack_auto')->where([['user_id', '=', $this->user_id], ['room_id', '=', $room_id]])->find();
                if (empty($data)) {
                    $rand = $grant[mt_rand(0, count($grant) - 1)];
                    $data = ['auto' => 0,
                        'mb' => $rand[0],
                        'num' => $rand[1],
                        'receive' => 'room',
                        'share' => 0,
                        'pic' => '/static/img/head.png',
                        'msg' => ''
                    ];
                }

                if (empty($data['pic'])) $data['pic'] = '/static/img/head.png';

                return view('', ['data' => $data, 'grant' => $grant]);
                break;
        }
    }
}