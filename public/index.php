<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2019 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: 老猫 <zxxjjforever@163.com>
// +----------------------------------------------------------------------

namespace think;

// [ 入口文件 ]

// 调试模式开关
define('APP_DEBUG', false);

// 定义CMF根目录,可更改此目录
define('CMF_ROOT', dirname(__DIR__) . '/');

// 定义CMF数据目录,可更改此目录
define('CMF_DATA', CMF_ROOT . 'data/');

// 定义应用目录
define('APP_PATH', CMF_ROOT . 'app/');

// 定义网站入口目录
define('WEB_ROOT', __DIR__ . '/');

//支持跨域
//header("Access-Control-Allow-Origin:http://127.0.0.1:8010");
//header('Access-Control-Allow-Headers:Authorization');
//header("Access-Control-Allow-Credentials: true");
//header("Access-Control-Allow-Methods:GET, POST, OPTIONS, DELETE");
//header("Access-Control-Allow-Headers:DNT,X-Mx-ReqToken,Keep-Alive,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type, Accept-Language, Origin, Accept-Encoding");


// 加载基础文件
require CMF_ROOT . 'vendor/thinkphp/base.php';

// 执行应用并响应
Container::get('app', [APP_PATH])->run()->send();
