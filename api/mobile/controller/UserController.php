<?php
/**
 * Desc: 移动端用户登录
 * User: Zhaojinsheng
 * Date: 2020/8/4
 * Time: 13:32
 * Filename:UserController.php
 */
namespace api\mobile\controller;

use cmf\controller\RestBaseController;
use jwt\JwtLib;
class UserController extends RestBaseController{



    public function test(){

        echo "<pre>";print_r($this->request);die;
    }

    public function login(){

        $userId = 12;

        $jwt = JwtLib::createJwt($userId);

        $this->success('success',['token'=>$jwt]);
    }
}