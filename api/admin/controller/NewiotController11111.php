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
use Redis;


class NewIotController extends RestBaseController
{
    //控制网关是否保存更多sn_id的开关
    private $sn_more_switch = false;

    /**
     * 上传经纬度
     */
public function saveCoordinate(){
    $data = $this->request->param();
    $data['time']=date('Y-m-d H:i:s',time());
    $log='接受参数:'.json_encode($data);
    $this->writelog($log,'jwd');
    if(empty($data['distance'])&&!isset($data['distance'])){
        return json(['code' => 500, 'msg' => 'distance is not post', 'data' => '']);

    }
//    if(empty($data['latitude'])&&!isset($data['latitude'])){
//        return json(['code' => 500, 'msg' => 'latitude is not post', 'data' => '',]);
//
//    }
    $dt = explode(',',$data['distance']);
//    $ab = $dt[0]."|".$dt[1]."|".$dt[2];
    $ip=get_client_ip(0, true);
    $time=date('Y-m-d H:i:s',time());
    $info['longitude']=$dt[0];
    $info['latitude']=$dt[1];
    $info['time']=$dt[2];
    $info['wg']=$dt[3];
    $info['xh']=$dt[4];
    $info['dc']=$dt[5];
    $info['create_time']=$time;
    $info['strotime']=time();
    $info['ip']=$ip;
    $insert=Db::name('dt_loc')->insert($info);
    if($insert) {
        $ab = $dt[0] . "|" . $dt[1] . "|" . $time."|".$dt[3]."|".$dt[4]."|".$dt[5]."|".$dt[2];
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $keys=date('Y-m-d',time());
        $redis->lpush($keys, $ab);
    }
    return json(['code' => 200, 'msg' => 'ok', 'data' => '']);

}
public function saveHeartBeat(){//http://localhost:8080/wind/public/api.php/admin/NewIot/saveHeartBeat

    $data = $this->request->param();
    $log='接受参数:'.json_encode($data);
    $this->writelog($log,'hb');
     if(empty($data['gw_id'])&&!isset($data['gw_id'])){
          return json(['code' => 500, 'msg' => 'is not post', 'data' => '', 'statuscode' => '']);

     }
    $gwId = $data['gw_id'];
    $statusCode = isset($data['statuscode']) ? $data['statuscode'] : '';
    $objOper=Db::name('wind_operation_score');
    //首先先更新快照表中操作的处理状态

    if ($statusCode) {

        $objOper->where('id', $statusCode)->update(['is_send' => 1]);
    }
    //查看是否是新的网关
    $objGate = Db::name('wind_gate');
    $gateInfo = $objGate->where(array('gw_id'=> $gwId))->find();
    $ip=get_client_ip(0, true);
    if(empty($gateInfo)){
        $gateFiled['gw_id']=$gwId;
        $gateFiled['ip']=$ip;
        $gateFiled['update_time']=time();
        $objGate->insert($gateFiled);
    }else{
        $gateFiled['update_time']=time();
        $gateFiled['ip']=$ip;
        $objGate->where(array('gw_id'=>$gwId))->update();
    }
    //首次发送传感器列表
    $objSensors=Db::name('wind_sensors');
    if (isset($data['sn_list'])) {
        $str = str_replace('[', "", $data['sn_list']);
        $str = str_replace(']', "", $str);
        $snList = explode(',', $str);

        foreach ($snList as $item) {

            $item = trim($item, "'");
            //查看是否已经存在该记录
            $where['gw_id']=$gwId;
            $where['sn_id']=$item;

            $sensorsInfo = $objSensors->where($where)->find();
            if (!$sensorsInfo) {

                $sensorFiled['gw_id']=$gwId;
                $sensorFiled['sn_id']=$item;
                $sensorFiled['create_time']=time();
                $objSensors->insert($sensorFiled);
            }

        }
        //首次启动直接返回
        $log1='返回参数:'.json_encode(['code' => 200, 'msg' => 'sn_rec', 'data' => '', 'statuscode' => '']);
        $this->writelog($log1,'hb');
        return json(['code' => 200, 'msg' => 'sn_rec', 'data' => '', 'statuscode' => '']);

    }
    //从快照表中取出最新的操作发送给网关
    $result = ['code' => 200, 'msg' => '', 'data' => '', 'statuscode' => ''];
    $operWhere['gw_id']=$gwId;
    $operWhere['is_send']=0;
    //取第一条
    $operation = $objOper->where($operWhere)->find();
    $flag = [1 => 'gw_config', 2 => 'gw_reboot', 3 => 'download_logs', 4 => 'sn_config', 5 => 'del_snId', 6 => 'back_connect'];

    if ($operation) {

        if (in_array($operation['type'], [1, 4])) {

            $operationData = json($operation['data']);

            $result['data'] = $operationData;


        } else if ($operation['type'] === 5) {

            $result['data'] = $operation['data'];
        }

        $result['msg'] = $flag[$operation['type']];

        $result['statuscode'] = $operation['id'];

    } else {

        $result['msg'] = 'success';
    }
    $log1='返回参数:'.json_encode($result);
    $this->writelog($log1,'hb');
    return json($result);



}
public function saveSensorsData(){
    $data = $this->request->param();
    $arrPostData = json_decode($data, true);
    $ip=get_client_ip(0, true);
    $log='接受参数:'.$arrPostData;
    $this->writelog($log,'cg_');
    $arrPostData['s_time'] = strtotime($arrPostData['s_time']);

    $arrPostData['ip'] = $ip;

    $arrPostData['create_time'] = time();
    $where['gw_id']=$arrPostData['gw_id'];
    $where['sn_id']=$arrPostData['sn_id'];
    $objModle=Db::name('wind_sensors');
    $sensorInfo = $objModle->where($where)->find();
    if(empty($sensorInfo)){
        $arrInsert['gw_id']=$arrPostData['gw_id'];
        $arrInsert['sn_id']=$arrPostData['sn_id'];
        $arrInsert['ST']=$arrPostData['ST'];
        $arrInsert['temp']=$arrPostData['temp'];
        $arrInsert['update_time']=time();
        $arrInsert['create_time']=time();
        $objModle->insert($arrInsert);
    }else{
        //更新传感器表
        $arrSensor['ST']=$arrPostData['ST'];
        $arrSensor['temp']=$arrPostData['temp'];
        $arrSensor['update_time']=time();
        $objModle->where($where)->update($arrSensor);
    }
    Db::name('wind_sensors_data')->insert($arrPostData);

    $result = ['code' => 200, 'msg' => 'success', 'data' => ''];
    $log1='返回参数:'.json_encode($result);
    $this->writelog($log1,'cg');
    return json($result);
}
    //上传文件log
    public function receive() {
        //$param =  file_get_contents("php://input");
        $file  = $this->request->file('file');
        $gw_id = input('gw_id');
        // file_put($file_param,'file_param');
        $info = $file->move('../uploads','');

        if ($info) {
            //上传成功,保存路径
            $insert_data['file_name']       = $info;
            $insert_data['gw_id']       = $gw_id;
            $insert_data['create_time'] = time();
            $insert_data['file_url']    = $info->getSaveName(); // getSaveName() 输出 20160820/42a79759f284b767dfcb2a0197904287.jpg
            Db::name('wind_gate_log')->insert($insert_data);
            //通知网关已收到
            $res = [
                'code' => 200,
                'msg' => 'get file',
                'data' => ''
            ];
        } else {
            $res = [
                'code' => 500,
                'msg' => 'error',
                'data' => ''
            ];
        }
        return json($res);
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