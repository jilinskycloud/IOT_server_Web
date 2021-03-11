<?php
/**
 * Created by PhpStorm.
 * User: Dell
 * Date: 2020/4/3
 * Time: 16:07
 */

namespace api\admin\model;


use think\Model;

class UserModel extends Model
{






    //更新管理员密码
    public static function modifypwd($id,$pwd){
        $pwd = cmf_password($pwd);
        $res = self::where('id',$id)->update(['user_pass' => $pwd]);
        return $res;
    }

}