<?php
/**
 * Created by PhpStorm.
 * User: Dell
 * Date: 2020/4/16
 * Time: 15:34
 */

namespace api\admin\model;


use think\Model;

class IotHbModel extends Model
{
    //添加字段
    protected $append = ['hb_status'];

    //心跳状态
    public function getHbStatusAttr($value) {
        $hb_time   = $this->update_time;
        $pip_time  = get_microtime() - 60 * 1000 * 10; //10分钟前时间

        if ($hb_time < $pip_time) { //10分钟
            //失效
            return false;
        } else {
            //正常
            return true;


        }
    }

}