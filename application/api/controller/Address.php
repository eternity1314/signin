<?php

namespace app\api\controller;

use app\common\model\Address as AddressModel;

class Address extends Base
{
    // 我的收获地址 - 列表
    public function lists()
    {
        $address_data = AddressModel::field('id, consignee, mobile, province, city, district, address, default')->where('user_id', $this->user_id)->select();
        
        return $this->response(['address_data' => $address_data]);
    }
    
    // 我的收获地址 - 添加
    public function add()
    {
        $data = [
            'user_id' => $this->user_id,
            'consignee' => request()->post('consignee'),
            'mobile' => request()->post('mobile'),
            'province' => request()->post('province'),
            'province_code' => request()->post('province_code', ''),
            'city' => request()->post('city'),
            'city_code' => request()->post('city_code', ''),
            'district' => request()->post('district'),
            'district_code' => request()->post('district_code', ''),
            'address' => request()->post('address'),
            'add_time' => request()->time()
        ];
        
        if (!$data['consignee']) {
            return $this->response([], '联系人不能为空！', 40000);
        }
        if (!$data['mobile']) {
            return $this->response([], '手机号不能为空！', 40000);
        }
        if (!$data['province'] || !$data['city'] || !$data['address']) {
            return $this->response([], '地址不完整！', 40000);
        }
        
        AddressModel::create($data);
        
        return $this->response([], '添加成功', 0);
    }
    
    // 我的收获地址 - 删除
    public function del()
    {
        $id = request()->delete('id');
        
        $rs = AddressModel::destroy(
            ['id', '=', $id],
            ['user_id', '=', $this->user_id]
        );
        
        if ($rs > 0) {
            return $this->response([], '删除成功', 0);
        } else {
            return $this->response([], '删除失败', 40001);
        }
    }
    
    // 我的收获地址 - 设置默认
    public function set_default()
    {
        $id = request()->get('id');
    
        $rs = AddressModel::update(['default' => 0], [
            ['user_id', '=', $this->user_id],
            ['default', '=', 1]
        ]);
    
        if ($rs) {
            AddressModel::update(['default' => 1], ['id' => $id]);
            
            return $this->response([], '操作成功', 0);
        } else {
            return $this->response([], '操作失败', 40001);
        }
    }
}
