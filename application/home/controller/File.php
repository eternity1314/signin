<?php

namespace app\home\controller;

use think\Controller;

class File extends Controller
{
//    protected function initialize()
//    {
//    }
//
//    public function upload()
//    {
//    }

    // 图片上传处理
    public function picture()
    {
        // 获取表单上传文件
        $file = request()->file('file');
//        dump($file);
        //校验器，判断图片格式是否正确
        if (true !== $res = $this->validate(['image' => $file], ['image' => 'require|image'])) {
            return $this->error('请选择图像文件' . $res);
        } else {
            // 移动到框架应用根目录/public/uploads/ 目录下
            $path = request()->root() . DIRECTORY_SEPARATOR . 'media/uploads/';// . date('Ymd') . DIRECTORY_SEPARATOR;
            $info = $file->move('.' . $path);
            if ($info) {
                // 成功上传
                return $this->success('', '', ['path' => str_replace('\\', '/', $path . DIRECTORY_SEPARATOR . $info->getSaveName())]);
            } else {
                // 上传失败获取错误信息
                return $this->error($file->getError());
            }
        }
    }
}
