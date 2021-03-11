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


class iotController extends RestBaseController
{
    //控制网关是否保存更多sn_id的开关
    private $sn_more_switch = false;


    //网关总信息列表(本系统)
    public function iotlist() {
//        $sub_query = Db::name('iotData')->order('create_time desc')->limit(10000000000)->buildSql();
//        $data['list'] = Db::table($sub_query.'a')->group('gw_id')->select();
        $list = Db::name('iotData')->where('show', 1)->select()->toArray();
        if (empty($list)) {
            $this->error('没有获取网关信息');
        }
        foreach ($list as $k => $item) {
            $list[$k]['date'] = get_microtime_format($item['create_time'] * 0.001);
        }
        $data['list'] = $list;
        $this->success('success', $data);

    }

    //总数据网关信息
    public function alliotlist() {
        $data  = $this->request->param();
        $where = [];
        if (!empty($data)) {
            //有查询条件
            if ($data['select_gw'] != '') {
                $where['gw_id'] = $data['select_gw'];
            }
            if ($data['select_sn'] != '') {
                $where['sn_id'] = $data['select_sn'];
            }
            if ($data['select_start_time'] != '') {
                //有时间段查询条件
                $start_time = '' . strtotime($data['select_start_time']) * 1000; //变成string类型
                $end_time   = '' . strtotime($data['select_end_time']) * 1000;
                $list       = Db::name('iotData')->where($where)->where('create_time', 'between', [$start_time, $end_time])->select()->toArray();
            } else {
                //有查询条件，但没时间段
                $list = Db::name('iotData')->where($where)->limit(100)->select()->toArray();
            }
        } else {
            //无查询条件
            $list = Db::name('iotData')->select()->toArray();
        }

        foreach ($list as $k => $item) {
            $list[$k]['date'] = get_microtime_format($item['create_time'] * 0.001);
        }
        $data['list'] = $list;
        $this->success('success', $data);
    }

    //网关心跳监测表(本系统)
    public function hbIotlist() {
        $data['list'] = IotHbModel::select();

        if (empty($data['list'])) {
            $this->error('没有获取网关心跳信息');
        }
        $this->success('success', $data);
    }

    //获取网关配置
    public function getGateConfig() {
        //验证
        $validate = new \think\Validate([
            'gw_id' => 'require',
            'sn_id' => 'require'
        ]);
        $validate->message([
            'gw_id.require' => 'gw_id is not post!',
            'sn_id.require' => 'sn_id is not post!'
        ]);
        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        $res = Db::name('iotDataConfig')->where(['gw_id' => $data['gw_id'], 'sn_id' => $data['sn_id'], 'last' => 1])->find();
        if (!empty($res)) {

            $this->success('success', $res);
        } else {
            $res['gw_id'] = $data['gw_id'];
            $res['sn_id'] = $data['sn_id'];
            $this->success('empty', $res);
        }
    }

    //保存网关配置
    public function setGateConfig() {
        //验证
        $validate = new \think\Validate([
            'gw_id'            => 'require',
            'sn_id'            => 'require',
            's_time'           => 'require',
            'hb_api_address'   => 'require',
            'data_api_address' => 'require',
            'data_reboot_time' => 'require',
            'hb_interval'      => 'require',
            'data_interval'    => 'require',
            'version'          => 'require',
        ]);
        $data     = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        $curr_config_version = Db::name('iotDataConfig')->where(['gw_id' => $data['gw_id'], 'sn_id' => $data['sn_id'], 'last' => 1])->value('version');
        if ($curr_config_version != null) {
            if ($data['version'] == $curr_config_version) {
                //version相同
                $data['update_time'] = time();
                $res                 = Db::name('iotDataConfig')->where(['gw_id' => $data['gw_id'], 'sn_id' => $data['sn_id'], 'version' => $curr_config_version])->update($data);
            } else {
                //version不同，软删除
                Db::name('iotDataConfig')->where(['gw_id' => $data['gw_id'], 'sn_id' => $data['sn_id'], 'last' => 1])->update(['last' => 0]);
                $data['update_time'] = time();
                $data['last']        = 1;
                $res                 = Db::name('iotDataConfig')->where(['gw_id' => $data['gw_id'], 'sn_id' => $data['sn_id'], 'version' => $data['version']])->update($data);
            }

        } else {
            Db::name('iotDataConfig')->where(['gw_id' => $data['gw_id'], 'sn_id' => $data['sn_id'], 'last' => 1])->update(['last' => 0]);
            $data['create_time'] = time();
            $res                 = Db::name('iotDataConfig')->insert($data);
        }

        if (!$res) {
            $this->error('保存网关配置失败！');
        } else {
            $this->success('success');
        }
    }

    //发送网关具体配置
    public function sendGateConfig() {
        //验证
        $validate = new \think\Validate([
            'gw_id' => 'require',
            'sn_id' => 'require'
        ]);
        $validate->message([
            'gw_id.require' => 'gw_id is not post!',
            'sn_id.require' => 'sn_id is not post!'
        ]);
        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        Db::name('iotHbConfig')->where('id', 1)->update([
            'hb_status_flag' => 3,
            'update_time'    => time(),
            'config_gw_id'   => $data['gw_id'],
            'config_sn_id'   => $data['sn_id']
        ]);
        $this->success('success');
    }

    //发送网关开关设置
    public function sendGateStatus() {
        //验证
        $validate = new \think\Validate([
            'gw_id'        => 'require',
            'hbeat_status' => 'require',
            'pdata_status' => 'require'
        ]);
        $data     = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        Db::name('iotHb')->where(['gw_id' => $data['gw_id']])->update(['hbeat_status' => $data['hbeat_status'], 'pdata_status' => $data['pdata_status']]);
        Db::name('iotHbConfig')->where('id', 1)->update([
            'hb_status_flag' => 4,
            'config_gw_id'   => $data['gw_id'],
            'update_time'    => time(),

        ]);
        $this->success('success');
    }

    //网关重启
    public function gatewayReboot() {
        $flag = Db::name('iotHbConfig')->where('id', 1)->value('hb_status_flag');
        if ($flag == 1) {
            Db::name('iotHbConfig')->where('id', 1)->update(['hb_status_flag' => 2, 'update_time' => time()]);
            $this->success('success');
        } else {
            $this->success('rebooting');
        }

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
            $insert_data['gw_id']       = $gw_id;
            $insert_data['create_time'] = time();
            $insert_data['file_url']    = $info->getSaveName(); // getSaveName() 输出 20160820/42a79759f284b767dfcb2a0197904287.jpg
            Db::name('gateLog')->insert($insert_data);
            //通知网关已收到
            $this->success('get file');

        } else {
            $this->error('error');
        }
    }


    //物联网设备心跳接口(iot关联用)
    public function hbMonitor() {
        //验证
        $validate = new \think\Validate([
            'gw_id'  => 'require',
            'status' => 'require'  //1首次启动;0正常;3证明收到config配置;4证明收到status_config配置;5保存log文件;6删除传感器回复;
        ]);
        $validate->message([
            'gw_id.require'  => 'gw_id is not post!',
            'status.require' => 'start is not post!'
        ]);
        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        //status判断
        $sn_list = '';
        if ($data['status'] == 1) {
            /*首次启动*/
            //sn_list字符串
            $str         = str_replace('[', "", $data['sn_list']);
            $str         = str_replace(']', "", $str);
            $sn_list_arr = explode(',', $str);
            $sn_list     = $str;
            //保存到iot_data
            $curr_sn = Db::name('iotData')->where(['gw_id' => $data['gw_id'], 'show' => 1])->column('sn_id');
            foreach ($sn_list_arr as $K => $sn) {
                if (!in_array($sn, $curr_sn)) {
                    $insert['gw_id']       = $data['gw_id'];
                    //$sn = str_replace("'", "", $sn);
                    $insert['sn_id']       = $sn;
                    $insert['create_time'] = get_microtime();
                    $insert['show']        = 1;
                    Db::name('iotData')->insert($insert);
                }
            }
        }
        if ($data['status'] == 3) {
            //网关方收到config配置，变更成正常传success
            Db::name('iotHbConfig')->where('config_gw_id', $data['gw_id'])->update(['hb_status_flag' => 1, 'update_time' => time()]);
        }
        if ($data['status'] == 4) {
            //网关方收到status_config配置，变更成正常传success
            Db::name('iotHbConfig')->where('config_gw_id', $data['gw_id'])->update(['hb_status_flag' => 1, 'update_time' => time()]);
        }
        if ($data['status'] == 5){
            //网关已知道保存log文件，变更成正常传success
            Db::name('iotHbConfig')->where('config_gw_id', $data['gw_id'])->update(['hb_status_flag' => 1, 'update_time' => time()]);
        }
        if ($data['status'] == 6) {
            //网关方收到status_config配置，变更成正常传success
            Db::name('iotHbConfig')->where('config_gw_id', $data['gw_id'])->update(['hb_status_flag' => 1, 'update_time' => time()]);
        }
        $gw_id_arr = Db::name('iotHb')->column('gw_id');
        $ip        = get_client_ip(0, true);
        if (!in_array($data['gw_id'], $gw_id_arr)) {
            $insert_data['gw_id']       = $data['gw_id'];
            $insert_data['update_time'] = get_microtime();
            $insert_data['ip']          = $ip;
            $insert_data['sn_list'] = $sn_list;

            Db::name('iotHb')->insert($insert_data);
        } else {
            $update_data['update_time'] = get_microtime();
            $update_data['ip']          = $ip;
            $update_data['sn_list'] = $sn_list;
            $res = Db::name('iotHb')->where('gw_id', $data['gw_id'])->update($update_data);
            if (!$res) {
                $this->error('faild');
            }
        }
        //首次启动，返回
        if ($data['status'] == 1) {
            $this->success('sn_rec');
        }

        //获取网关心跳功能标识
        $flags = Db::name('iotHbConfig')->where('config_gw_id', $data['gw_id'])->select()->toArray();
        if (empty($flags)) {
            //当没有此心跳功能标识时
            $this->success('success');
        }
        $fun = false;
        $flag_arr = Db::name('iotHbConfig')->where('config_gw_id', $data['gw_id'])->column('hb_status_flag');
        if (in_array(2,$flag_arr) || in_array(3,$flag_arr) || in_array(4,$flag_arr) || in_array(5,$flag_arr) || in_array(6,$flag_arr)){
            $fun = true;
        }
        if ($fun == true){
            //说明有功能要执行
            foreach ($flags as $k => $flag) {

                if ($flag['hb_status_flag'] == 2) {
                    //重启
                    Db::name('iotHbConfig')->where('config_gw_id', $data['gw_id'])->update(['hb_status_flag' => 1, 'update_time' => time()]);
                    $this->success('reboot');
                } elseif ($flag['hb_status_flag'] == 3) {
                    //网关具体配置
                    $res = Db::name('iotDataConfig')->where(['gw_id' => $flag['config_gw_id'], 'sn_id' => $flag['config_sn_id'], 'last' => 1])->field('gw_id,sn_id,ST,hb_api_address,data_api_address,hb_interval,data_interval,version')->find();
                    if ($res != null) {
                        $res['hb_interval']   = '' . $res['hb_interval'];
                        $res['data_interval'] = '' . $res['data_interval'];
                    }
                    $this->success('config', $res);
                } elseif ($flag['hb_status_flag'] == 4) {
                    //网关开关配置
                    $res   = Db::name('iotHb')->where('gw_id', $flag['config_gw_id'])->field('gw_id,hbeat_status,pdata_status')->find();
                    $times = Db::name('gateTime')->where('gw_id', $data['gw_id'])->field('time_point,time_type')->select();
                    foreach ($times as $k => $time) {
                        $res['times'][$k][$time['time_type']] = $time['time_point'];
                    }
                    $res['system_time'] = time();
                    $res['key']         = '';
                    $this->success('status_config', $res);
                } elseif ($flag['hb_status_flag'] == 5) {
                    //命令网关上传log文件

                    $this->success('req_logs');
                } elseif ($flag['hb_status_flag'] == 6){
                    //删除传感器
                    $del_sn_data['sn_id'] = $flag['config_sn_id'];
                    $this->success('del_SnId',$del_sn_data);
                }
            }
        }
        //正常
        $this->success('success');

    }

    //test
    public function test() {
        $a = get_microtime();
        $c = strtotime('May 6') * 1000;
//        if (1587003941623 < 1587052800000) {
//            print_r('xiao于');
//        }
        $b = get_microtime_format(1588694401010 * 0.001); //显示正常时间方法
        print_r($b);
        exit;
    }

    //物联网设备信息保存接口(iot关联用)
    public function saveMonitor() {
        // print_r(123);exit;
        //验证
        $validate = new \think\Validate([
            'post_data' => 'require'
        ]);
        $validate->message([
            'post_data.require' => 'post_data is not post!'
        ]);
        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        $ip = get_client_ip(0, true);
//        file_put($data['post_data'],1); //打印到文本public/text_1.txt
        $data_arr = json_decode($data['post_data'], true);
        //控制网关是否保存更多sn_id的开关
        if ($this->sn_more_switch == true) {
            $curr_sn_arr = Db::name('iotData')->where(['gw_id' => $data_arr['gw_id'], 'show' => 1])->column('sn_id');
            if (!in_array($data_arr['sn_id'], $curr_sn_arr)) {
                $this->error("sn_id cann't no more!");
            }
        }
        $insert_data['gw_id']       = $data_arr['gw_id'];
        $insert_data['s_time']      = strtotime($data_arr['s_time']);
        $insert_data['sn_id']       = $data_arr['sn_id'];
        $insert_data['ST']          = $data_arr['ST'];
        $insert_data['temp']        = $data_arr['temp'];
        $insert_data['ip']          = $ip;
        $insert_data['data']        = $data['post_data'];
        $insert_data['create_time'] = get_microtime();
        $insert_data['show']        = 1;
//        $this->handlesaveMonitor($insert_data);
        Db::name('iotData')->where(['gw_id' => $insert_data['gw_id'], 'sn_id' => $insert_data['sn_id'], 'show' => 1])->update(['show' => 0]);
        $res = Db::name('iotData')->insert($insert_data);
        if (!$res) {
            $this->error('failed');
        } else {
            $this->success('success');
        }

    }

    //网关单元格显示接口
    public function cellGates() {
        $res = Db::name('iotHb')->select()->toArray();
        foreach ($res as $k => $cell) {
            $res[$k]['status'] = true;
            $pip_time          = get_microtime() - 60 * 1000 * 10; //10分钟前时间
            if ($cell['update_time'] < $pip_time) {
                $res[$k]['status'] = false;
            }
            $res[$k]['update_time_date'] = get_microtime_format($cell['update_time'] * 0.001);
        }
        $this->success('success', $res);
    }

    //网关传感器单元格显示接口
    public function cellSn() {
        $res         = Db::name('iotData')->alias('a')->join('iotHb b', 'a.gw_id = b.gw_id', 'left')
            ->where(['a.show' => 1])->field('a.create_time,a.gw_id,a.sn_id,a.temp,b.update_time')->select()->toArray();
        $return_data = [];
        $pip_time    = get_microtime() - 60 * 1000 * 10; //10分钟前时间
        array_walk($res, function ($item, $key) use (&$return_data, $pip_time) {
            $return_data[$item['gw_id']]['gw_id']                 = $item['gw_id'];
            $return_data[$item['gw_id']]['sn'][$key]['sn_id']     = $item['sn_id'];
            $return_data[$item['gw_id']]['sn'][$key]['sn_id']     = $item['sn_id'];
            $return_data[$item['gw_id']]['sn'][$key]['temp']      = $item['temp'];
            $return_data[$item['gw_id']]['sn'][$key]['last_time'] = get_microtime_format($item['create_time'] * 0.001);
            $return_data[$item['gw_id']]['sn'][$key]['status']    = true;
            if ($item['update_time'] < $pip_time) {
                $return_data[$item['gw_id']]['sn'][$key]['status'] = false;
            }
        });
        $this->success('success', $return_data);
    }

    //温度统计图
    public function tempCensus() {
        //验证
        $validate = new \think\Validate([
            'gw_id' => 'require',
            'sn_id' => 'require'
        ]);
        $validate->message([
            'gw_id.require' => 'gw_id is not post!',
            'sn_id.require' => 'sn_id is not post!'
        ]);
        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        $temp_data            = [];
        $where['gw_id']       = $data['gw_id'];
        $where['sn_id']       = $data['sn_id'];
        $start_time           = (string)strtotime('today') * 1000;
        $end_time             = (string)(strtotime('today') + 86400) * 1000;
        $where['create_time'] = ['between', $start_time, $end_time];
        $res_arr              = Db::name('iotData')->where($where)->order('create_time asc')->select()->toArray();
        if (!empty($res_arr)) {
            foreach ($res_arr as $k => $res) {
                $temp_data[$k]['value'] = $res['temp'];
                $temp_data[$k]['name']  = (string)$this->get_microtime_date($res['create_time'] * 0.001);
            }
        } else {
            $demo_arr = [
                ['value' => 25, 'name' => '6:30'], ['value' => 26, 'name' => '7:00'], ['value' => 26.3, 'name' => '7:30'],
                ['value' => 26.7, 'name' => '8:00'], ['value' => 26.3, 'name' => '8:30'], ['value' => 26.7, 'name' => '9:00']
            ];
            foreach ($demo_arr as $k => $demo) {
                $temp_data[$k]['value'] = $demo['value'];
                $temp_data[$k]['name']  = $demo['name'];
            }
        }

        $this->success('success', $temp_data);
    }

    /**
     *时间戳 转   日期格式 ： 精确到毫秒，x代表毫秒($time为毫秒时，要乘以0.001)
     */
    private function get_microtime_date($time) {
        if (strstr($time, '.')) {
            sprintf("%01.3f", $time); //小数点。不足三位补0
            list($usec, $sec) = explode(".", $time);
            $sec = str_pad($sec, 3, "0", STR_PAD_RIGHT); //不足3位。右边补0
        } else {
            $usec = $time;
            $sec  = "000";
        }
//    $date = date("Y-m-d H:i:s.x",$usec);
        $date = date("Y-m-d H:m", $usec);
        return str_replace('x', $sec, $date);
    }






    //保存接口辅助接口
//    private function handlesaveMonitor($insert_data){
//        $insert_data['update_time'] = $insert_data['create_time'];
//        unset($insert_data['data']);
//        unset($insert_data['create_time']);
//        $cur_arr = Db::name('iotShowdata')->field('gw_id,sn_id')->select()->toArray();
//        $flag = 0;
//        if (!empty($cur_arr)){
//            foreach($cur_arr as $k => $cur){
//                if ($insert_data['gw_id'] == $cur['gw_id'] && $insert_data['sn_id'] == $cur['sn_id']){
//
//                    Db::name('iotShowdata')->where(['gw_id' => $insert_data['gw_id'], 'sn_id' =>  $insert_data['sn_id']])->update($insert_data);
//                    $flag = 1;
//                }
//            }
//        }
//        if ($flag == 0){
//            Db::name('iotShowdata')->insert($insert_data);
//        }
//
//    }


}