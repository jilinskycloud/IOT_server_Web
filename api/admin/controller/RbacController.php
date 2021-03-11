<?php
/**
 * Created by PhpStorm.
 * User: Dell
 * Date: 2020/4/1
 * Time: 9:00
 */

namespace api\admin\controller;

use cmf\controller\RestBaseController;
use api\admin\model\RoleModel;
use api\admin\model\AuthAccessModel;
use api\admin\model\AuthRuleModel;
use api\admin\model\ManageMenuModel;
use think\db;

class RbacController extends RestBaseController
{

    //获取所有权限列表
    public function getall() {
        $res = ManageMenuModel::getMenu();
        if (!$res) {
            $this->error('faild');
        }

        $this->success('success', $res);
    }

    //获取某角色已有权限
    public function getRbac(){
        $validate = new \think\Validate([
            'role_id'  => 'require',
        ]);
        $validate->message([
            'role_id.require'  => '需要授权的角色不存在！'
        ]);
        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        //操作
        $res = Db::name('authAccess')->where(['role_id' => $data['role_id'], 'delete_time' => 0, 'type' => 'admin'])->column('menu_id');
        if (empty($res)){
            $this->success('empty');
        }
        $this->success('success',$res);
    }

    //保存当前管理员赋予的权限
    public function setRbac() {
        $validate = new \think\Validate([
            'rbac_ids' => 'require',
            'role_id'  => 'require',
            'type'     => 'require'
        ]);
        $validate->message([
            'rbac_ids.require' => '权限id为空',
            'role_id.require'  => '需要授权的角色不存在！',
            'type.require'     => '权限类型不存在！'
        ]);
        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        //操作
        //删除角色已有权限
        Db::name('authAccess')->where(['role_id' => $data['role_id'], 'type' => $data['type'], 'delete_time' => 0 ])->update(['delete_time' => time()]);
        //保存新权限
        foreach($data['rbac_ids'] as $k => $menu_id){
            $rule_name = ManageMenuModel::where(['id' => $menu_id, 'delete_time' => 0])->value('index');
            if (!$rule_name){ $this->error('授权功能id不存在！'); }
            Db::name("authAccess")->insert(["role_id" => $data['role_id'], "rule_name" => $rule_name, 'menu_id' => $menu_id, 'type' => $data['type']]);
        }
        $this->success('success');

    }


}