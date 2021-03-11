<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
// [ 应用入口文件 ]
// 定义应用目录
define('APP_PATH', __DIR__ . '/../application/');

require_once dirname(__FILE__)."/../extend/CAS/CAS.php";
//指定log文件
phpCAS::setDebug('./phpCAS.log');
//指定cas地址，最后一个true表示是否cas服务器为https，第二个参数为域名或是ip，第三个参数为服务器端口号，第四个参数为上下文路径
phpCAS::client(CAS_VERSION_2_0,'authserver.hlju.edu.cn',80,'authserver',false);
phpCAS::setNoCasServerValidation();
phpCAS::handleLogoutRequests();
phpCAS::forceAuthentication();

//本地退出应该重定向到CAS进行退出，传递service参数可以使CAS退出后返回本应用
//demo表示退出请求为logout的请求
if(isset($_GET['logout'])){
    $param = array('service'=>'http://authserver.hlju.edu.cn/authserver/login.jsp');
    phpCAS::logout($param);
    exit;
}

// 加载框架引导文件
require __DIR__ . '/../thinkphp/start.php';

//phpCAS::getAttributes(); //可以返回所有所有授权的属性

$user = phpCAS::getUser();
$iscas = app\index\controller\User::iscas('index');
if ($iscas == 1){
    app\index\controller\User::caslogin($user);
}



?>




