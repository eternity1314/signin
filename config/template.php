<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// +----------------------------------------------------------------------
// | 模板设置
// +----------------------------------------------------------------------

return [
    // 模板引擎类型 支持 php think 支持扩展
    'type'         => 'Think',
    // 模板路径
    'view_path'    => '',
    // 模板后缀
    'view_suffix'  => 'html',
    // 模板文件名分隔符
    'view_depr'    => DIRECTORY_SEPARATOR,
    // 模板引擎普通标签开始标记
    'tpl_begin'    => '{',
    // 模板引擎普通标签结束标记
    'tpl_end'      => '}',
    // 标签库标签开始标记
    'taglib_begin' => '{',
    // 标签库标签结束标记
    'taglib_end'   => '}',
    // 输出替换
    'tpl_replace_string' => [
        '__PUBLIC__' => '',
        '__BASE_CSS__' => '<link rel="stylesheet" href="/static/css/base.css"/>',
        '__BASE_JS__' => '<script src="/static/js/base.js"></script>',
        '__JQUERY__' => '<script src="/static/jquery/jquery.min.js"></script>', //'<script src="https://cdn.bootcss.com/jquery/3.3.1/jquery.min.js"></script>',
        '__LAYUI_CSS__' => '<link rel="stylesheet" href="/static/layui/css/layui.css"/>',
        '__LAYUI_JS__' => '<script src="/static/layui/layui.js"></script>',
        '__LAYER_CSS__' => '<link rel="stylesheet" href="/static/layui/layer/mobile/need/layer.css"/>',
        '__LAYER_JS__' => '<script src="/static/layui/layer/mobile/layer.js"></script>',
    ],
];
