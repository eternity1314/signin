<?php

namespace app\common\model;

use think\Model;

class UserIdentity extends Model
{
    protected $pk = 'user_id';
    protected $table = 'sl_user_identity';
    
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'add_time';
}
