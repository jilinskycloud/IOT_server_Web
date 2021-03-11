<?php
/**
 * Created by PhpStorm.
 * User: Dell
 * Date: 2020/4/14
 * Time: 13:04
 */

namespace api\admin\controller;

use cmf\controller\RestBaseController;
use think\Db;
use api\admin\model\IotHbModel;
use think\facade\Validate;
use think\Log;
use think\Session;
use app\admin\controller\IotController as AppIot;


class LockopenController extends RestBaseController
{
    //控制网关是否保存更多sn_id的开关
    private $sn_more_switch = false;

    /**
     * 开锁
     * @return \think\response\Json
     */
public function openlock(){

    $data = $this->request->param();
    $data['post_time']=date('Y-m-d H:i:s',time());
    $log='<接受参数:'.json_encode($data);
    $this->writelog($log,'openlock');
    if(empty($data)){
        return json(['code' => 500, 'msg' => 'is not post', 'data' => '']);
    }
    if(isset($data['status'])&&!empty($data['status'])){
        //wg 发送的请求  status:1 成功   2:失败重新发送请求
       if($data['status']==1){
           //存入数据库
           return json(['code' => 200, 'msg' => 'success', 'data' => '']);
       }


    }
    if(isset($data['id'])&&!empty($data['id'])){
        //安卓端发送的请求
        $request=[];
        $request['data']=array('status'=>1);
        $request['code']=200;
        $request['msg']='success';
//        $url='';
//        $this->curl_post($url,$request);
        $log1='返回参数:'.json_encode(['code' => 200, 'msg' => 'success', 'data' =>$request]);
        $this->writelog($log1,'openlock');

    }


    return json(['code' => 200, 'msg' => 'success', 'data' => array('status'=>1)]);


    }

function curl_get($url){

    $header = array(
    'Accept: application/json',
    );
    $curl = curl_init();
        //设置抓取的url
    curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
    curl_setopt($curl, CURLOPT_HEADER, 0);
        // 超时设置,以秒为单位
    curl_setopt($curl, CURLOPT_TIMEOUT, 1);

        // 超时设置，以毫秒为单位
        // curl_setopt($curl, CURLOPT_TIMEOUT_MS, 500);

        // 设置请求头
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        //执行命令
    $data = curl_exec($curl);

        // 显示错误信息
    if (curl_error($curl)) {
    print "Error: " . curl_error($curl);
    } else {
        // 打印返回的内容
        var_dump($data);
        curl_close($curl);
    }
}

// $url 是请求的链接
// $postdata 是传输的数据，数组格式
    function curl_post( $url, $postdata ) {

        $header = array(
            'Accept: application/json',
        );

        //初始化
        $curl = curl_init();
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, 0);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // 超时设置
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);

        // 超时设置，以毫秒为单位
        // curl_setopt($curl, CURLOPT_TIMEOUT_MS, 500);

        // 设置请求头
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE );
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE );

        //设置post方式提交
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
        //执行命令
        $data = curl_exec($curl);

        // 显示错误信息
        if (curl_error($curl)) {
            print "Error: " . curl_error($curl);
        } else {
            // 打印返回的内容
            var_dump($data);
            curl_close($curl);
        }
    }

    function writelog($loginfo,$filename)
    {
        $file = $dir =realpath('..').'/uploads/log/log_'.$filename. date('y-m-d') . '.log';
        if (!is_file($file)) {
            file_put_contents($file, '', FILE_APPEND);//如果文件不存在，则创建一个新文件。
        }
        $contents = $loginfo . "\r\n";
        file_put_contents($file, $contents, FILE_APPEND);
    }


}