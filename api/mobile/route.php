<?php
/**
 * Desc: 移动端路由文件
 * User: Zhaojinsheng
 * Date: 2020/8/4
 * Time: 14:00
 * Filename:route.php
 */
Route::group('mobile',function(){
    //需要验证token的
    Route::group([
//        'middleware'=>['\api\http\middleware\checkToken']
        'middleware'=>['checkToken']
    ],function(){

        Route::get('test','mobile/User/test');
    });

//不需要验证token
    Route::get('login','mobile/User/login');
});
//不需要验证token
//Route::get('login','mobile/User/login');