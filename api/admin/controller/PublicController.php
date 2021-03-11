<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2017 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
namespace api\admin\controller;

use cmf\controller\RestBaseController;
use think\Db;
use think\facade\Validate;
use api\admin\model\ManageMenuModel;

class PublicController extends RestBaseController
{

    // 用户登录 TODO 增加最后登录信息记录,如 ip
    public function login()
    {
        $validate = new \think\Validate([
            'username' => 'require',
            'password' => 'require'
        ]);
        $validate->message([
            'username.require' => '请输入手机号,邮箱或用户名!',
            'password.require' => '请输入您的密码!'
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $userQuery = Db::name("user");
        if (Validate::is($data['username'], 'email')) {
            $userQuery = $userQuery->where('user_email', $data['username']);
        } else if (cmf_check_mobile($data['username'])) {
            $userQuery = $userQuery->where('mobile', $data['username']);
        } else {
            $userQuery = $userQuery->where('user_login', $data['username']);
        }

        $findUser = $userQuery->field("a.*,b.role_id,c.name as role_name")->alias('a')
                    ->join('cmf_role_user b','a.id = b.user_id','left')
                    ->join('cmf_role c','b.role_id = c.id', 'left')->find();
        //判断账号是否禁用
        if ($findUser['user_status'] == 0){
            $this->success('forbidden');
        }

        if (empty($findUser)) {
            $this->error("用户不存在!");
        } else {

            switch ($findUser['user_status']) {
                case 0:
                    $this->error('您已被拉黑!');
                case 2:
                    $this->error('账户还没有验证成功!');
            }

            if (!cmf_compare_password($data['password'], $findUser['user_pass'])) {
                $this->error("密码不正确!");
            }
            $update_data['last_login_ip']   = get_client_ip(0, true);
            $update_data['last_login_time'] = time();
            Db::name('user')->where('id','=',$findUser['id'])->update($update_data);
        }

        //保存session
        session('ADMIN_ID', $findUser['id']);
        session('name', $findUser['user_login']);

        $allowedDeviceTypes = ['mobile', 'android', 'iphone', 'ipad', 'web', 'pc', 'mac'];

        if (empty($this->deviceType) && (empty($data['device_type']) || !in_array($data['device_type'], $this->allowedDeviceTypes))) {
            $this->error("请求错误,未知设备!");
        } else if(!empty($data['device_type'])) {
            $this->deviceType = $data['device_type'];
        }

        $userTokenQuery = Db::name("user_token")
            ->where('user_id', $findUser['id'])
            ->where('device_type', $this->deviceType);
        $findUserToken  = $userTokenQuery->find();
        $currentTime    = time();
        $expireTime     = $currentTime + 24 * 3600 * 180;
        $token          = md5(uniqid()) . md5(uniqid());
        if (empty($findUserToken)) {
            $result = Db::name("user_token")->insert([
                'token'       => $token,
                'user_id'     => $findUser['id'],
                'expire_time' => $expireTime,
                'create_time' => $currentTime,
                'device_type' => $this->deviceType
            ]);
        } else {
            $result = Db::name("user_token")
                ->where('user_id', $findUser['id'])
                ->where('device_type', $this->deviceType)
                ->update([
                    'token'       => $token,
                    'expire_time' => $expireTime,
                    'create_time' => $currentTime
                ]);
        }

        if (empty($result)) {
            $this->error("登录失败!");
        }
        //去除密码
        unset($findUser['user_pass']);
        //载入菜单
        $menu = ManageMenuModel::getMenu($findUser['id']);
        $data = [
            'token' => $token,
            'user' => $findUser,
            'menu' => $menu
        ];

        $this->success("登录成功!", $data);
    }

    // 管理员退出
    public function logout()
    {
        $userId = $this->getUserId();
        Db::name('user_token')->where([
            'token'       => $this->token,
            'user_id'     => $userId,
            'device_type' => $this->deviceType
        ])->update(['token' => '']);
        session(null);
        $this->success("退出成功!");
    }

}
