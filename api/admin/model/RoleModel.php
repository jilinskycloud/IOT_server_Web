<?php
/**
 * Created by PhpStorm.
 * User: Dell
 * Date: 2020/4/1
 * Time: 8:54
 */

namespace api\admin\model;


use think\Model;

class RoleModel extends Model
{
    //自动时间戳
    protected $autoWriteTimestamp = true;


    //角色管理列表
    public static function rolelist(){
                                                     //原来是：DESC
        $res = self::where('delete_time',0)->order([ "list_order" => "ASC" , "id" => "ASC"])->select()->toArray();
        return $res;
    }

    //添加角色并返回新添加角色的数据
    public static function insertGetLast($data){
        $data['create_time'] = time();
        $last_id = self::insertGetId($data);
        $res = self::where('id',$last_id)->find()->toArray();
        return $res;
    }





}