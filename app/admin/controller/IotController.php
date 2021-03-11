<?php
/**
 * Created by PhpStorm.
 * User: Dell
 * Date: 2020/4/14
 * Time: 13:04
 */

namespace app\admin\controller;

use app\admin\model\IotDataModel;
use cmf\controller\AdminBaseController;
use cmf\controller\RestBaseController;
use think\Db;
use api\admin\model\IotHbModel;
use think\facade\Validate;
use think\Log;
use think\Session;


class IotController extends AdminBaseController
{
    //控制网关是否保存更多sn_id的开关
    private $sn_more_switch = false;

    //分页条数
    private $_page = 20;


    //网关信息列表(本系统)
    public function iotlist()
    {
        $where = [];
        $gw_id = '';
        $time_flag = 0;
        if ($this->request->isPost()) {
            $data = $this->request->param();
            $gw_id = isset($data['gw_id']) ? $data['gw_id'] : '';
            //有查询条件
            if (isset($data['gw_id']) && $data['gw_id']) {
                // $where['gw_id'] = $data['select_gw'];
                $where[] = ['gw_id', 'like', "%" . $data['gw_id'] . "%"];
            }
            if (isset($data['sn_id']) && $data['sn_id']) {
                // $where['sn_id'] = $data['select_sn'];
                $where[] = ['sn_id', 'like', "%" . $data['sn_id'] . "%"];
            }
            if (isset($data['select_start_time']) && isset($data['select_end_time']) && $data['select_start_time'] && $data['select_end_time']) {
                //有时间段查询条件
                $start_time = '' . strtotime($data['select_start_time']) * 1000; //变成string类型
                $end_time = '' . strtotime($data['select_end_time']) * 1000;
                $where[] = ['create_time', 'between time', [$start_time, $end_time]];
                $time_flag = 1;
                $list = Db::name('iotData')->where($where)->where('show', 1)->order('create_time desc')->paginate(20, false, ['query' => request()->param()]);
            }
        }
        if ($time_flag != 1) {
            $list = Db::name('iotData')->where($where)->where('show', 1)->order('create_time asc')->paginate(20, false, ['query' => request()->param()]);
        }
        $this->assign('page', $list->render());
        $this->assign('data', $list);
        $this->assign('data_gw_id', $gw_id);
        return $this->fetch();
    }

    //删除网关的某传感器
    public function delGateSn()
    {
        $id = input('id');
        $gw_id = input('gw_id');
        $sn_id = input('sn_id');
        Db::name('iotData')->where('id', $id)->update(['show' => 0]);
        //通知网关删除掉哪个传感器
        Db::name('iotHbConfig')->where(['config_gw_id' => $gw_id, 'config_sn_id' => $sn_id])->update(['hb_status_flag' => 6, 'update_time' => time()]);
        $this->success('删除成功！');
    }

    //总数据网关信息
    public function alliotlist()
    {
        $data = $this->request->param();
        $where = [];
        if (!empty($data)) {
            //有查询条件
            if ($data['select_gw'] != '') {
                // $where['gw_id'] = $data['select_gw'];
                $where[] = ['gw_id', 'like', "%" . $data['select_gw'] . "%"];


            }
            if ($data['select_sn'] != '') {
                // $where['sn_id'] = $data['select_sn'];
                $where[] = ['sn_id', 'like', "%" . $data['select_sn'] . "%"];
            }

            if ($data['select_start_time'] != '') {
                //有时间段查询条件
                $start_time = '' . strtotime($data['select_start_time']) * 1000; //变成string类型
                $end_time = '' . strtotime($data['select_end_time']) * 1000;
                $list = Db::name('iotData')->where($where)->where('create_time', 'between', [$start_time, $end_time])->select()->toArray();
            } else {
                //有查询条件，但没时间段

                $list = Db::name('iotData')->where($where)->select()->toArray();
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

    //传感器单元格(本系统)
    public function hbIotlist()
    {
        $where = [];
        //查询
        if ($this->request->isPost()) {
            $sea_sn = $this->request->param('sn_id');
            //有查询条件
            $where[] = ['a.sn_id', 'like', "%" . $sea_sn . "%"];
        }
        $list = Db::name('iotData')->alias('a')->join('cmf_iot_hb b', 'a.gw_id = b.gw_id', 'left')->where($where)->where('show', 1)
            ->field('a.*,b.update_time')->select()->toArray();
        $return_data = [];
        $pip_time = get_microtime() - 60 * 1000 * 10; //10分钟前时间
        array_walk($list, function ($item, $key) use (&$return_data, $pip_time) {
            $return_data[$key]['gw_id'] = $item['gw_id'];
            $return_data[$key]['sn_id'] = $item['sn_id'];
            $return_data[$key]['temp'] = $item['temp'];
            $return_data[$key]['update_time'] = $item['update_time'];
            $return_data[$key]['last_time'] = get_microtime_format($item['create_time'] * 0.001);
            $return_data[$key]['status'] = 1;
            if ($item['update_time'] < $pip_time) {
                $return_data[$key]['status'] = 0;
            }
            if ($item['temp'] == 0) {
                unset($return_data[$key]);
            }
        });
        $this->assign('list', $return_data);
        return $this->fetch();
    }


    //网关心跳监测表(本系统)
    public function hbIotlistList()
    {
        $data = $this->request->param();
        $where = [];
        //有查询条件
        if (isset($data['gw_id']) && $data['gw_id']) {
            // $where['gw_id'] = $data['select_gw'];
            $where[] = ['gw_id', 'like', "%" . $data['gw_id'] . "%"];
        }
        $gw_id = isset($data['gw_id']) ? $data['gw_id'] : '';
        $pip_time = get_microtime() - 60 * 1000 * 10; //10分钟前时间
        $list = Db::name('iotHb')->where($where)->order('update_time desc')->select()->each(function ($item, $key) use ($pip_time) {
            $item['status'] = true;
            if ($item['update_time'] < $pip_time) {
                $item['status'] = false;
            }
            return $item;
        });
        // dump($list);
        // die;
        //

        $this->assign('data', $list);
        $this->assign('data_gw_id', $gw_id);
        return $this->fetch();
    }

    //获取传感器配置
    public function getGateConfig()
    {
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
        if ($res != null) {
            $res['hb_interval'] = round($res['hb_interval'] / 60, 0);
            $res['data_interval'] = round($res['data_interval'] / 60, 0);
        }
        $assign['gate'] = $res;
        $assign['gw_id'] = $data['gw_id'];
        $assign['sn_id'] = $data['sn_id'];
        $this->assign($assign);
        return $this->fetch();
    }

    //保存传感器配置
    public function setGateConfig()
    {
        //验证
        $validate = new \think\Validate([
            'gw_id' => 'require',
            'sn_id' => 'require',
            'ST' => 'require',
            'hb_api_address' => 'require',
            'data_api_address' => 'require',
            'hb_interval' => 'require',
            'data_interval' => 'require',
            'version' => 'require',
        ]);
        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        //这两个字段是分钟单位
        $data['hb_interval'] = $data['hb_interval'] * 60;
        $data['data_interval'] = $data['data_interval'] * 60;
        $curr_config_version = Db::name('iotDataConfig')->where(['gw_id' => $data['gw_id'], 'sn_id' => $data['sn_id'], 'last' => 1])->value('version');
        if ($curr_config_version != null) {
            if ($data['version'] == $curr_config_version) {
                //version相同
                $data['update_time'] = time();
                $res = Db::name('iotDataConfig')->where(['gw_id' => $data['gw_id'], 'sn_id' => $data['sn_id'], 'version' => $curr_config_version])->update($data);
            } else {
                //version不同，软删除
                Db::name('iotDataConfig')->where(['gw_id' => $data['gw_id'], 'sn_id' => $data['sn_id'], 'last' => 1])->update(['last' => 0]);
                $data['update_time'] = time();
                $data['last'] = 1;
                $res = Db::name('iotDataConfig')->where(['gw_id' => $data['gw_id'], 'sn_id' => $data['sn_id'], 'version' => $data['version']])->update($data);
            }

        } else {
            //新增
            Db::name('iotDataConfig')->where(['gw_id' => $data['gw_id'], 'sn_id' => $data['sn_id'], 'last' => 1])->update(['last' => 0]);
            $data['create_time'] = time();
            $res = Db::name('iotDataConfig')->insert($data);
        }

        if (!$res) {
            $this->error('保存网关配置失败', 'iotlist');
        } else {
            $this->success('success', 'iotlist');
        }
    }

    //发送网关具体配置
    public function sendGateConfig()
    {
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
        $where['config_gw_id'] = $data['gw_id'];
        $where['config_sn_id'] = $data['sn_id'];
        $curr_config = Db::name('iotHbConfig')->where($where)->find();
        if ($curr_config == null) {
            //插入
            $insert_arr['hb_status_flag'] = 3;
            $insert_arr['update_time'] = time();
            $insert_arr['config_gw_id'] = $data['gw_id'];
            $insert_arr['config_sn_id'] = $data['sn_id'];
            Db::name('iotHbConfig')->insert($insert_arr);
        } else {
            //更新
            Db::name('iotHbConfig')->where($where)->update([
                'hb_status_flag' => 3,
                'update_time' => time(),
            ]);
        }

        $this->success('success', 'iotlist');
    }

    //保存并发送网关配置
    public function sendGateStatus()
    {
        //验证
        $validate = new \think\Validate([
            'gw_id' => 'require',
            'hbeat_status' => 'require',
            'pdata_status' => 'require',
        ]);
        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        Db::name('iotHb')->where(['gw_id' => $data['gw_id']])->update(['hbeat_status' => $data['hbeat_status'], 'pdata_status' => $data['pdata_status']]);
        $curr_config = Db::name('iotHbConfig')->where('config_gw_id', $data['gw_id'])->select();
        if ($curr_config == null) {
            //插入
            $insert_arr['hb_status_flag'] = 4;
            $insert_arr['update_time'] = time();
            $insert_arr['config_gw_id'] = $data['gw_id'];
            Db::name('iotHbConfig')->insert($insert_arr);
        } else {
            //更新
            Db::name('iotHbConfig')->where('config_gw_id', $data['gw_id'])->update([
                'hb_status_flag' => 4,
                'update_time' => time(),
            ]);
        }

        $this->success('success', 'cellGates');
    }

    //添加网关间隔时间
    public function addGateTime()
    {
        $gw_id = input('gw_id');
        $assign['gw_id'] = $gw_id;
        if ($this->request->isPost()) {
            $time_point = input('time_point');
            $time_type = input('time_type');
            Db::name('gateTime')->insert(['gw_id' => $gw_id, 'time_point' => $time_point, 'time_type' => $time_type]);
            $this->success('success', 'gateEdit?gw_id=' . $gw_id);
        }
        $this->assign($assign);
        return $this->fetch();
    }

    //删除网关间隔时间
    public function delGateTime()
    {
        $id = input('id');
        Db::name('gateTime')->where('id', $id)->delete();
        $this->success('删除成功');
    }


    //添加传感器
    public function addSn()
    {

        return $this->fetch();
    }

    //网关重启
    public function gatewayReboot()
    {
        $gw_id = input('gw_id');
        $curr_config = Db::name('iotHbConfig')->where('config_gw_id', $gw_id)->select()->toArray();
        if (empty($curr_config)) {
            //插入
            $insert_arr['hb_status_flag'] = 2;
            $insert_arr['update_time'] = time();
            $insert_arr['config_gw_id'] = $gw_id;
            Db::name('iotHbConfig')->insert($insert_arr);
        } else {
            //更新
            Db::name('iotHbConfig')->where('config_gw_id', $gw_id)->update([
                'hb_status_flag' => 2,
                'update_time' => time(),
            ]);
        }
        $this->success('success');
    }

    //网关单元格显示接口
    public function cellGates()
    {
        $where = [];
        //查询
        if ($this->request->isPost()) {
            $sea_gw = $this->request->param('gw_id');
            //有查询条件
            $where[] = ['gw_id', 'like', "%" . $sea_gw . "%"];

        }
        $res = Db::name('iotHb')->where($where)->select()->toArray();
        foreach ($res as $k => $cell) {
            $res[$k]['status'] = true;
            $pip_time = get_microtime() - 60 * 1000 * 10; //10分钟前时间
            if ($cell['update_time'] < $pip_time) {
                $res[$k]['status'] = false;
            }
            $res[$k]['update_time_date'] = get_microtime_format($cell['update_time'] * 0.001);
        }
        $assign['list'] = $res;
        $this->assign($assign);
        return $this->fetch();

    }

    //网关单元格设置页面
    public function gateEdit()
    {
        //验证
        $validate = new \think\Validate([
            'gw_id' => 'require',
        ]);
        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        $res = Db::name('iotHb')->where('gw_id', $data['gw_id'])->find();
        $times = Db::name('gateTime')->where('gw_id', $data['gw_id'])->select();
        $assign['times'] = $times;
        $assign['gate'] = $res;
        $this->assign($assign);
        return $this->fetch();
    }

    //点击网关下载按钮tempCensus
    public function gateDownload()
    {
        $gw_id = input('gw_id');
        Db::name('iotHbConfig')->where('config_gw_id', $gw_id)->update(['hb_status_flag' => 5]);
        $this->success('log下载请求已发送，响应成功后请到log文件列表中下载');

    }

    //网关下载文件列表
    public function gateDownlist()
    {
        $gw_id = input('gw_id');
        $list = Db::name('gateLog')->where('gw_id', $gw_id)->order('create_time','desc')->paginate($this->_page);

        $page = $list->render();

        $assign['list'] = $list;
        $assign['page'] = $page;
        $this->assign($assign);
        return $this->fetch();
    }

    //下载文件列表点击下载操作
    public function gateLogDownload()
    {
        $id = input('id');
        $getSaveName = Db::name('gateLog')->where('id', $id)->value('file_url');
        //下载操作
        $file_name = $getSaveName;
        $file_path = CMF_ROOT . '/uploads/' . $file_name; //下载文件的存放目录
        if (!file_exists($file_path)) {
            $this->error("文件不存在");
        } else {
            $open_file = fopen($file_path, "r"); //打开文件
            //输入文件标签
            header("Content-type: application/octet-stream");
            header("Accept-Ranges: bytes");
            header("Accept-Length: " . filesize($file_path));
            header("Content-Disposition: attachment; filename=" . $file_name);
            ob_clean();
            flush();
            //输出文件内容
            echo fread($open_file, filesize($file_path));
            fclose($open_file);
            exit();
        }
    }

    //网关传感器单元格显示接口
    public function cellSn()
    {
        $res = Db::name('iotData')->alias('a')->join('iotHb b', 'a.gw_id = b.gw_id', 'left')
            ->where(['a.show' => 1])->field('a.create_time,a.gw_id,a.sn_id,a.temp,b.update_time')->select()->toArray();
        $return_data = [];
        $pip_time = get_microtime() - 60 * 1000 * 10; //10分钟前时间
        array_walk($res, function ($item, $key) use (&$return_data, $pip_time) {
            $return_data[$item['gw_id']]['gw_id'] = $item['gw_id'];
            $return_data[$item['gw_id']]['sn'][$key]['sn_id'] = $item['sn_id'];
            $return_data[$item['gw_id']]['sn'][$key]['sn_id'] = $item['sn_id'];
            $return_data[$item['gw_id']]['sn'][$key]['temp'] = $item['temp'];
            $return_data[$item['gw_id']]['sn'][$key]['last_time'] = get_microtime_format($item['create_time'] * 0.001);
            $return_data[$item['gw_id']]['sn'][$key]['status'] = true;
            if ($item['update_time'] < $pip_time) {
                $return_data[$item['gw_id']]['sn'][$key]['status'] = false;
            }
        });
        $this->success('success', $return_data);
    }

    //温度统计页面
    public function censusPage()
    {
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
        $this->assign([
            'gw_id' => $data['gw_id'],
            'sn_id' => $data['sn_id']
        ]);
        return $this->fetch();
    }

    //温度统计图数据接口
    public function tempCensus()
    {
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
        $where['gw_id'] = $data['gw_id'];
        $where['sn_id'] = $data['sn_id'];
        $start_time = (string)strtotime('today') * 1000;
        $end_time = (string)(strtotime('today') + 86400) * 1000;
        if ($this->request->isPost()) {
            $search_start_time = strtotime(input('select_start_time')) * 1000;
            $search_end_time = strtotime(input('select_end_time')) * 1000;
            if ($search_start_time != '') {
                $start_time = $search_start_time;
            }
            if ($search_end_time != '') {
                $end_time = $search_end_time;
            }
        }
        $res_arr = Db::name('iotData')->alias('A')->where($where)->where('create_time', 'between', [$start_time, $end_time])->field("A.*, from_unixtime(s_time,'%Y-%m-%d %H:%i:%s') as sl_time")->order('create_time asc')->select()->toArray();
        foreach ($res_arr as $k => $res) {
            $res_arr[$k]['s_time'] = $this->get_microtime_date($res['create_time'] * 0.001);
        }

        $this->assign('list', $res_arr);
        $this->assign('gw_id', $data['gw_id']);
        $this->assign('sn_id', $data['sn_id']);
        return $this->fetch();

    }

    /**
     *时间戳 转   日期格式 ： 精确到毫秒，x代表毫秒($time为毫秒时，要乘以0.001)
     */
    private function get_microtime_date($time)
    {
        if (strstr($time, '.')) {
            sprintf("%01.3f", $time); //小数点。不足三位补0
            list($usec, $sec) = explode(".", $time);
            $sec = str_pad($sec, 3, "0", STR_PAD_RIGHT); //不足3位。右边补0
        } else {
            $usec = $time;
            $sec = "000";
        }
//    $date = date("Y-m-d H:i:s.x",$usec);
        $date = date("Y-m-d H:i:s", $usec);
        return str_replace('x', $sec, $date);
    }


}