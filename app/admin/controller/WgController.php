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


class WgController extends AdminBaseController
{
    //控制网关是否保存更多sn_id的开关
    private $sn_more_switch = false;

    //分页条数
    private $_page = 20;

    /**
     * 传感器列表
     */
    public function getsensorlist()
    {
        $where = '';
        $gw_id = '';
        $time_flag = 0;
        if ($this->request->isPost()) {
            $data = $this->request->param();
            $gw_id=$data['fcname'];
            //有查询条件
            if (isset($data['fcname']) && $data['fcname']) {
                $where="gw_id  like '%".$data['fcname']."%'";
                $where.=" or sn_id like '%".$data['fcname']."%'";
            }

        }
        if($where){
            $where .= " and  hb_api_address != '' and data_api_address != '' ";
        }else{
            $where = "hb_api_address != '' and data_api_address != '' ";
        }
        
        $list = Db::name('wind_sensors')->order('id desc')->where($where)->paginate(20, false, ['query' => request()->param()]);
        $data=$list->items();
        $time = strtotime('-600 seconds');
         foreach($data as $key=>$val){
             if ($val['update_time'] < $time) {
                 $data[$key]['status_name']='失效';
                 $data[$key]['status']=2;
             } else {
                 $data[$key]['status_name']='正常';
                 $data[$key]['status']=1;
             }
             $data[$key]['update_time'] = $val['update_time'] ? date("Y-m-d H:i:s", $val['update_time']) : '';
         }
        $this->assign('page', $list->render());
        $this->assign('data', $data);
        $this->assign('data_gw_id', $gw_id);
        return $this->fetch();
    }

    public function fjadd(){
        $userlist=Db::name('user')->select();
        $this->assign('userlist',$userlist);
        return $this->fetch();
    }

    public function not_configured_getsensorlist(){
        $where = [];
        $list = Db::name('wind_sensors')->order('id desc')->where("hb_api_address = '' or data_api_address = '' ")->paginate(20, false, ['query' => request()->param()]);
        $data=$list->items();
        $this->assign('page', $list->render());
        $this->assign('data', $data);
        return $this->fetch();
    }

    /**
     * 添加提交
     */
    public function addPost()
    {
        if ($this->request->isPost()) {
            if(empty(input('fcname'))){
                $this->error("请输入风场名称！");
            }
            if(empty(input('fcaddress'))){
                $this->error("请输入风场位置！");
            }
            if(empty(input('fcarea'))){
                $this->error("请输入风场面积！");
            }
            if(empty(input('fccharge'))){
                $this->error("请输入风场负责人！");
            }
            if(empty(input('fccreatetime'))){
                $this->error("请输入风场投运时间！");
            }
                $data['fcname']=input('fcname');
                $data['fcaddress']=input('fcaddress');
                $data['fcarea']=input('fcarea');
                $data['fccharge']=input('fccharge');
                $data['fccode']=$this->GetRandStr(6);
                $data['fccreatetime']=input('fccreatetime');
                $result = DB::name('fcinfo')->insertGetId($data);
                if ($result !== false) {


                    $this->success("添加成功！", url("fjosi/fjlist"));


                } else {
                    $this->error("网络繁忙！");
                }

        }
    }
    function GetRandStr($length){
        //字符组合
        $str = '0123456789';
        $len = strlen($str)-1;
        $randstr = '';
        for ($i=0;$i<$length;$i++) {
            $num=mt_rand(0,$len);
            $randstr .= $str[$num];
        }
        $nian=date('Y',time());
        $randstr=$nian.''.$randstr;
        return $randstr;
    }
    /**
     * 配置
     */
    public function pz()
    {

        $id    = $this->request->param('id', 0, 'intval');
        $user = DB::name('wind_sensors')->where("id", $id)->find();
        $this->assign('id',$user['id']);
        $this->assign('user',$user);
        return $this->fetch();
    }

    /**
     * 编辑提交

     */
    public function editPost()
    {
        if ($this->request->isPost()) {
//            if(empty(input('fcname'))){
//                $this->error("请输入风场名称！");
//            }
//            if(empty(input('fcaddress'))){
//                $this->error("请输入风场位置！");
//            }
//            if(empty(input('fcarea'))){
//                $this->error("请输入风场面积！");
//            }
//            if(empty(input('fccharge'))){
//                $this->error("请输入风场负责人！");
//            }
//            if(empty(input('fccreatetime'))){
//                $this->error("请输入风场投运时间！");
//            }
            $data=input();
            $id=input('id');
            $result = DB::name('wind_sensors')->where(array('id'=>$id))->update($_POST);
            if ($result !== false) {
                    //加入快照表
                    $arrFiled = [];

                    $arrFiled['gw_id'] = $data['gw_id'];
                    $arrFiled['sn_id'] = $data['sn_id'];
                    $arrFiled['type'] = 4;
                    $arrFiled['is_send'] = 0;
                    unset($data['id']);
                    unset($data['gw_id']);
                    $arrFiled['data'] = json_encode($data, JSON_UNESCAPED_SLASHES);
                    $arrFiled['create_time'] = time();

                    DB::name("wind_operation_score")->insert($arrFiled);

                $this->success("保存成功！");
            } else {
                $this->error("保存失败！");
            }
        }
    }

    /*
     * 删除
     */
    public function fjdel(){
        $id = $this->request->param('id', 0, 'intval');
        $data = Db::name('wind_sensors')->find($id);
        if (Db::name('wind_sensors')->delete($id) !== false) {

            $arrFiled = [];
            $arrFiled['gw_id'] = $data['gw_id'];
            $arrFiled['sn_id'] = $data['sn_id'];
            $arrFiled['type'] = 5;
            $arrFiled['is_send'] = 0;
            $arrFiled['data'] = $data['sn_id'];
            $arrFiled['create_time'] = time();
            DB::name("wind_operation_score")->insert($arrFiled);

            $this->success("删除成功！");
        } else {
            $this->error("删除失败！");
        }
    }

    public function tempdata_del(){
        $where = [];
        $gw_id = '';
        $sn_id = '';
        $time_flag = 0;
        $data1 = $this->request->param();
        if ($this->request->isGet()) {
            //有查询条件
            if (isset($data1['gw_id']) && $data1['gw_id']) {
                $where[]=['gw_id', 'like', "%" . $data1['gw_id'] . "%"];
                $gw_id=$data1['gw_id'];
            }
            if (isset($data1['sn_id']) && $data1['sn_id']) {
                $where[]=['sn_id', 'like', "%" . $data1['sn_id'] . "%"];
                $sn_id = $data1['sn_id'];
            }
            if (isset($data1['select_start_time']) && isset($data1['select_end_time']) && $data1['select_start_time'] && $data1['select_end_time']) {
                //有时间段查询条件
                $start_time = '' . strtotime($data1['select_start_time']) ; //变成string类型
                $end_time = '' . strtotime($data1['select_end_time']) ;
                $where[] = ['create_time', 'between time', [$start_time, $end_time]];
            }

        }


        Db::name('wind_sensors_data')->where($where)->delete();
        return json(['code' => 1, 'msg' => 'OK']);
    }
        /*
     * 删除
     */
    public function wg_fjdel(){
        $id = $this->request->param('id', 0, 'intval');
        if (Db::name('wind_gate')->delete($id) !== false) {
            $this->success("删除成功！");
        } else {
            $this->error("删除失败！");
        }
    }
    /**
     * 传感器数据查询
     */

    public function getalltempdata(){
        $where = [];
        $gw_id = '';
        $sn_id = '';
        $time_flag = 0;
        $data1 = $this->request->param();
        if ($this->request->isGet()) {
            //有查询条件
            if (isset($data1['gw_id']) && $data1['gw_id']) {
                $where[]=['gw_id', 'like', "%" . $data1['gw_id'] . "%"];
                $gw_id=$data1['gw_id'];
            }
            if (isset($data1['sn_id']) && $data1['sn_id']) {
                $where[]=['sn_id', 'like', "%" . $data1['sn_id'] . "%"];
                $sn_id = $data1['sn_id'];
            }
            if (isset($data1['select_start_time']) && isset($data1['select_end_time']) && $data1['select_start_time'] && $data1['select_end_time']) {
                //有时间段查询条件
                $start_time = '' . strtotime($data1['select_start_time']) ; //变成string类型
                $end_time = '' . strtotime($data1['select_end_time']) ;
                $where[] = ['create_time', 'between time', [$start_time, $end_time]];
            }

        }


            $list = Db::name('wind_sensors_data')->order('create_time desc')->where($where)->paginate(20, false, ['query' => request()->param()]);

        $data=$list->items();
        foreach($data as $key=>$val){
            $data[$key]['create_time'] = date("Y-m-d H:i:s", $val['create_time']);
        }
        $this->assign('page', $list->render());
        $this->assign('data', $data);
        $this->assign('data_gw_id', $gw_id);
        $this->assign('data_sn_id', $sn_id);
        return $this->fetch();

    }

    /**
     * 网关列表
     */
    public function getgatelist(){
        $where = '';
        $gw_id = '';
        $time_flag = 0;
        if ($this->request->isPost()) {
            $data = $this->request->param();
            $gw_id=$data['fcname'];
            //有查询条件
            if (isset($data['fcname']) && $data['fcname']) {
                $where="gw_id  like '%".$data['fcname']."%'";
                $where.=" or ip like '%".$data['fcname']."%'";
            }

        }
        $list = Db::name('wind_gate')->order('update_time desc')->where($where)->paginate(20, false, ['query' => request()->param()]);
        $data=$list->items();
        $time = strtotime('-600 seconds');
        foreach($data as $key=>$val){
            if ($val['update_time'] < $time) {
                $data[$key]['status_name']='失效';
                $data[$key]['status']=2;
            } else {
                $data[$key]['status_name']='正常';
                $data[$key]['status']=1;
            }
            $data[$key]['update_time'] = date("Y-m-d H:i:s", $val['update_time']);
        }
        $this->assign('page', $list->render());
        $this->assign('data', $data);
        $this->assign('data_gw_id', $gw_id);
        return $this->fetch();
    }

    /**
     * 网关配置
     */
    public function gatepz()
    {

        $id    = $this->request->param('id', 0, 'intval');
        $user = DB::name('wind_gate')->where("id", $id)->find();
        $this->assign('id',$user['id']);
        $this->assign('user',$user);
        return $this->fetch();
    }

    /**
     * 网关配置
     */
    public function gateeditPost(){
        if ($this->request->isPost()) {
            $id=input('id');
            $result = DB::name('wind_gate')->where(array('id'=>$id))->update($_POST);

            if ($result !== false) {
                $gate=DB::name('wind_gate')->where(array('id'=>$id))->find();
                $dataConfig = [
                    'key' => '',
                    'data_status' => intval($_POST['data_status']),
                    'reboot_time' => $_POST['reboot_time'],
                    'system_time' => time(),
                    'hb_status' => intval($_POST['hb_status']),
                    'gw_id' => $gate['gw_id'],
                    'data_key' => $_POST['data_key'],
                    'heartbeat_url' => $_POST['heartbeat_url'],
                    'natcat_url' => $_POST['natcat_url'],
                    'log_url' => $_POST['log_url']
                ];
                //加入快照表
                $arrFiled = [];

                $arrFiled['gw_id'] = $gate['gw_id'];
                $arrFiled['type'] = 1;
                $arrFiled['is_send'] = 0;
                $arrFiled['data'] = json_encode($dataConfig, JSON_UNESCAPED_SLASHES);
                $arrFiled['create_time'] = time();

                DB::name("wind_operation_score")->insert($arrFiled);
                $this->success("保存成功！");
            } else {
                $this->error("保存失败！");
            }
        }
    }
    /**
     * 查看log文件
     */
    public function lookLog(){
        $gw_id =input('gw_id');
        $list = Db::name('wind_gate_log')->where(array('gw_id'=>$gw_id))->paginate(20, false, ['query' => request()->param()]);
        $data=$list->items();
        foreach($data as $key=>$val){

            $data[$key]['create_time'] = date("Y-m-d H:i:s", $val['create_time']);
        }
        $this->assign('page', $list->render());
        $this->assign('data', $data);
        $this->assign('data_gw_id', $gw_id);
        return $this->fetch();
    }
    /**
     * 发送log
     */
    public function sendlog(){
        $arrFiled = [];
        $gw_id =input('gw_id');
        $arrFiled['gw_id'] = $gw_id;
        $arrFiled['type'] = 3;
        $arrFiled['is_send'] = 0;
        $arrFiled['data'] = '';
        $arrFiled['create_time'] = time();
        Db::name('wind_operation_score')->insert($arrFiled);
         $this->success("发送成功！",'wg/getgatelist');
    }
    /**
     *重启
     */
    public function gwReboot(){
        $gw_id =input('gw_id');

        //加入快照表
        $arrFiled = [];

        $arrFiled['gw_id'] = $gw_id;
        $arrFiled['type'] = 2;
        $arrFiled['is_send'] = 0;
        $arrFiled['data'] = '';
        $arrFiled['create_time'] = time();

        Db::name("wind_operation_score")->insert($arrFiled);
        $this->success("发送成功！");
    }
    /**
     * 反向链接
     */
    public function backConnect(){
        $gw_id =input('gw_id');

        //加入快照表
        $arrFiled = [];

        $arrFiled['gw_id'] = $gw_id;
        $arrFiled['type'] = 6;
        $arrFiled['is_send'] = 0;
        $arrFiled['data'] = '';
        $arrFiled['create_time'] = time();

        Db::name("wind_operation_score")->insert($arrFiled);
        $this->success("发送成功！");
    }
    //下载文件列表点击下载操作
    public function gateLogDownload()
    {
        $id = input('id');

        $getSaveName = Db::name('wind_gate_log')->where('id', $id)->value('file_url');

        $file_name = $getSaveName;
        $file_path = CMF_ROOT . 'uploads/' . $file_name; //下载文件的存放目录
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
    //温度统计图数据接口
    public function tempcensus()
    {
        $where=[];
        $data=input();
//        $t = time();
//        $start_time = mktime(0,0,0,date("m",$t),date("d",$t),date("Y",$t));
//        $end_time = mktime(23,59,59,date("m",$t),date("d",$t),date("Y",$t));
        $search_start_time =  strtotime('today');
        $search_end_time = time();
        if ($this->request->isPost()) {
            if(!empty($data['gw_id'])&&isset($data['gw_id'])){
                $where[]=['gw_id', '=',  $data['gw_id'] ];
            }
            if(!empty($data['sn_id'])&&isset($data['sn_id'])){
                $where[]=['sn_id', '=', $data['sn_id']];
            }
            if(!empty($data['select_end_time'])&&!empty($data['select_start_time'])){
                $search_start_time=strtotime($data['select_start_time']);
                $search_end_time=strtotime($data['select_end_time']);
            }

        }
        ini_set('memory_limit','256M');
        $where[] = ['create_time', 'between time', [$search_start_time, $search_end_time]];
        $res_arr = Db::name('wind_sensors_data')->where($where)->select()->toArray();
        foreach ($res_arr as $k => $res) {
            $res_arr[$k]['s_time'] = date('Y-m-d h:i:s',$res['create_time']);
        }
//        dump($res_arr);
        $this->assign('list', $res_arr);
//        $this->assign('gw_id', $data['gw_id']);
//        $this->assign('sn_id', $data['sn_id']);
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