<?php
/**
 * Created by PhpStorm.
 * User: Dell
 * Date: 2020/3/31
 * Time: 14:08
 */
namespace api\admin\model;
use think\Model;
use think\db;

class ManageMenuModel extends Model
{






    //管理员获取菜单列表
    public static function getMenu($user_id = 1){

        $role_id = Db::name('roleUser')->where(['user_id' => $user_id])->value('role_id');
        if ($role_id == 1){
            //超级管理员
            $menu = self::field('id,parent_id,index,title,icon')->select()->toarray();
        }else{
            $menu_arr = Db::name('authAccess')->where(['role_id' => $role_id])->column('menu_id');
            $menu = self::where('id','in',$menu_arr)->field('id,parent_id,index,title,icon')->select()->toarray();
        }

           $menu = list_to_tree($menu);
           return $menu;
    }


}