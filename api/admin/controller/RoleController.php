<?php
/**
 * Created by PhpStorm.
 * User: Dell
 * Date: 2020/4/2
 * Time: 11:17
 */

namespace api\admin\controller;

use cmf\controller\RestBaseController;
use api\admin\model\RoleModel;


class RoleController extends RestBaseController
{

    //获取权限角色列表
    public function getRolelist(){
        $data['list'] = RoleModel::rolelist();
        $this->success('success',$data);
    }

    //删除角色
    public function addRole(){
        //验证
        $validate = new \think\Validate([
            'name' => 'require',
            'remark' => 'require',

        ]);
        $validate->message([
            'name.require' => '请传入新角色名称',
            'remark.number' => '请描述新角色'
        ]);
        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $res = RoleModel::insertGetLast($data);
        if (!$res){ $this->error('编辑角色失败'); }
        $this->success('success',$res);

    }

    //编辑角色
    public function editRole(){
        //验证
        $validate = new \think\Validate([
            'id' => 'require',
            'name' => 'require',
            'remark' => 'require',
            'status' => 'require|number'
        ]);
        $validate->message([
            'id.require' => '请传入编辑角色id',
            'status.number' => '状态为数字'
        ]);
        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $res = RoleModel::update($data);
        if (!$res){ $this->error('编辑角色失败'); }
        $this->success('success');

    }

    //删除角色
    public function delRole(){
        //验证
        $validate = new \think\Validate([
            'id' => 'require|number',

        ]);
        $validate->message([
            'id.require' => '请传入删除角色id',
            'id.number' => '角色id必须是数字'
        ]);
        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        $data['delete_time'] = time();
        $res = RoleModel::update($data);
        if (!$res){ $this->error('编辑角色失败'); }
        $this->success('success');

    }




}