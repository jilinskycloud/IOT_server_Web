<?php
/**
 * Created by PhpStorm.
 * User: Dell
 * Date: 2020/4/3
 * Time: 15:48
 */

namespace api\admin\controller;

use cmf\controller\RestBaseController;
use think\Db;
use think\facade\Validate;
use api\admin\model\UserModel;

class UserController extends RestBaseController
{


    //管理员列表
    public function index() {
        $data['list']  = Db::name('user')->field("a.*,b.role_id,c.name as role_name")->alias('a')->where(['a.delete_time' => 0, 'a.user_type' => 1])
            ->join('cmf_role_user b', 'a.id = b.user_id', 'left')
            ->join('cmf_role c', 'b.role_id = c.id', 'left')->select()->toArray();
        $data['roles'] = Db::name('role')->field('id,name')->where('delete_time', 0)->select()->toArray();
        $this->success('success', $data);
    }

    //添加管理员
    public function add() {
        //验证
        $validate = new \think\Validate([
            'user_login'  => 'require',
            'user_pass' => 'require',
            'user_type'   => 'require',
            'role_id' => 'require'
        ]);
        $data     = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        $role_id = $data['role_id'];
        unset($data['role_id']);
        $data['user_pass'] = cmf_password($data['user_pass']);
        $id = Db::name('user')->insertGetId($data);
        if (!$id){ $this->error('添加管理员失败！'); }
        $res = Db::name('roleUser')->insert(['role_id' => $role_id, 'user_id' => $id]);
        if (!$res){ $this->error('添加管理员给与角色失败！'); }
        $user = Db::name('user')->where('id',$id)->find();
        $this->success('success',$user);
    }

    //编辑管理员
    public function edit() {
        //验证
        $validate = new \think\Validate([
            'id'          => 'require',
            'user_login'  => 'require',
            'user_status' => 'require',
            'user_role'   => 'require',
            'user_type'   => 'require',

        ]);
        $data     = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        //保存角色
        $current_role = Db::name('roleUser')->where(['user_id' => $data['id']])->find();
        if ($data['user_role'] != $current_role['role_id']) {
            $cur_res = Db::name('roleUser')->where('id', $current_role['id'])->update(['role_id' => $data['user_role'], 'update_time' => time()]);
            if (!$cur_res) {
                $this->error('管理员变更角色失败！');
            }
        }
        unset($data['user_role']);
        $res = UserModel::update($data);
        if (!$res) {
            $this->error('faild');
        }
        $this->success('success');
    }


    //更改用户密码
    public function modifypwd() {
        //验证
        $validate = new \think\Validate([
            'cur_pwd' => 'require',
            'pwd'     => 'require'
        ]);
        $data     = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $user_id      = cmf_get_current_admin_id();
        $allready_pwd = UserModel::where('id', $user_id)->value('user_pass');
        //判断上传原密码和已有密码是否相同
        if (!cmf_compare_password($data['cur_pwd'], $allready_pwd)) {
            $this->error('different');
        }

        $res = UserModel::modifypwd($user_id, $data['pwd']);
        if (!$res) {
            $this->error('fail');
        }
        $this->success('success');
    }

    //删除管理员
    public function del() {
        //验证
        $validate = new \think\Validate([
            'id' => 'require'
        ]);
        $data     = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        $res = UserModel::where('id', $data['id'])->update(['delete_time' => time()]);
        if (!$res) {
            $this->error('删除管理员失败！');
        }
        $this->success('success');
    }

}