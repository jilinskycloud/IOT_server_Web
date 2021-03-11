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
use Redis;

class RwosiController extends AdminBaseController
{
    //控制网关是否保存更多sn_id的开关
    private $sn_more_switch = false;

    //分页条数
    private $_page = 20;

    const x_PI  = 3.14159265358979324 * 3000.0 / 180.0;
    const PI  = 3.1415926535897932384626;
    const a = 6378245.0;
    const ee = 0.00669342162296594323;

    public function rwlist()
    {
        $where = '';
        $fcnmae = '';
        $time_flag = 0;
        if ($this->request->isPost()) {
            $data = $this->request->param();
           if(!empty($data['jrrw'])&&isset($data['jrrw'])){
               if($data['jrrw']==1){
                   $time=date('Y-m-d',time());
                   $where.=" b.datevalue like '%".$time."%'";
                   $this->assign('jrrwbutton', '1');
               }
           }else{
               $fcnmae=$data['rwstr'];
               //有查询条件
               if (isset($data['rwstr']) && $data['rwstr']) {
                   // $where['gw_id'] = $data['select_gw'];
//                $where[] = ['gw_id', 'like', "%" . $data['gw_id'] . "%"];
                   $where=" a.taskname like '%".$data['rwstr']."%'";
                   $where.=" or b.planname like '%".$data['rwstr']."%'";
                   $where.=" or c.user_nickname like '%".$data['rwstr']."%'";


               }
           }
        }
            $list = Db::name('taskinfo')->alias('a')
                ->join('planinfo b', 'a.planinfoid = b.id', 'left')
                ->join('user c', 'a.taskcharge = c.id', 'left')
                ->field('a.*')
                ->order('id desc')->where($where)->paginate(10, false, ['query' => request()->param()]);
            $data=$list->items();
            foreach($data as $key=>$val){
                //查看所属风机
                $fc=Db::name('planinfo')->where(array('id'=>$val['planinfoid']))->find();
                $data[$key]['planname']=$fc['planname'];
                $u=Db::name('user')->where(array('id'=>$val['taskcharge']))->find();
                $data[$key]['taskcharge1']=$u['user_nickname'];
            }
        $this->assign('page', $list->render());
        $this->assign('data', $data);
        $this->assign('data_gw_id', $fcnmae);
        return $this->fetch();
    }

    public function xmadd(){
        $xmlist=Db::name('deviceinfo')->select();
        $this->assign('xmlist',$xmlist);
        return $this->fetch();
    }

    /**
     * 添加提交
     */
    public function addPost()
    {
        if ($this->request->isPost()) {
                $data['itemname']=input('itemname');
                $data['iteminfo']=input('iteminfo');
                $data['itemtype']=input('itemtype');
                $data['itemcode']=$this->GetRandStr(6);
                $data['devicecode']=input('devicecode');
                $result = DB::name('iteminfo')->insertGetId($data);
                if ($result !== false) {


                    $this->success("添加成功！", url("xmosi/xmlist"));


                } else {
                    $this->error("网络繁忙！");
                }

        }
    }
    function GetRandStr($length){
        //字符组合
        $str = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $len = strlen($str)-1;
        $randstr = '';
        for ($i=0;$i<$length;$i++) {
            $num=mt_rand(0,$len);
            $randstr .= $str[$num];
        }
        return $randstr;
    }
    /**
     * 详情
     */
    public function rwinfo()
    {
//        echo phpinfo();
        $id    = $this->request->param('id', 0, 'intval');
        $user = DB::name('taskinfo')->where("id", $id)->find();
        $taskitem=Db::name('task_item_info')->alias('a')->join('iteminfo b','a.itemcode=b.itemcode')->field('b.*,a.id as itemid
        ')->where(array('a.taskcode'=>$user['taskcode']))->select();
        $jh=Db::name('planinfo')->where(array('id'=>$user['planinfoid']))->find();
        $date=unserialize($jh['datevalue']);
        $arr=array();
        for($i=0;$i<count($date['time']);$i++){
//            $date['time'][$i]="<a href='#' class='jhtimes' >". $date['time'][$i]."</a>";
            $arr1=array();
            $arr1['startDate']=$date['time'][$i];
            $arr1['name']='巡检日';
            $arr[]=$arr1;
        }
        $user['jhtime']=implode(',',$date['time']);
        $user['jhid']=$date['id'];
        $user['planname']=$jh['planname'];
        $this->assign('jsontime',json_encode($arr));
        $this->assign('xmlist',$taskitem);
        $this->assign('id',$user['id']);
        $this->assign('user',$user);
        return $this->fetch();
    }
    /**
     * 编辑
     */
    public function rwedit()
    {

        $id    = $this->request->param('id', 0, 'intval');
        $user = DB::name('iteminfo')->where("id", $id)->find();
        $fclist=Db::name('deviceinfo')->select();
        $this->assign('xmlist',$fclist);
        $this->assign('id',$user['id']);
        $this->assign('user',$user);
        return $this->fetch();
    }
    /*
     * 去呼叫
     */
    public function tocall(){
        $data['mid']=input('uid');
        $data['callstate']='0';
        $data['starttime']=date('Y-m-d',time());
        $del=Db::name('video_record')->where(array('mid'=>input('uid'),'callstate'=>'0'))->delete();
        Db::name('video_record')->insert($data);
        echo input('uid');
    }
    /**
     * 编辑提交

     */
    public function editPost()
    {
        if ($this->request->isPost()) {
            $id=input('id');
            $result = DB::name('iteminfo')->where(array('id'=>$id))->update($_POST);
            if ($result !== false) {

                $this->success("保存成功！");
            } else {
                $this->error("保存失败！");
            }
        }
    }

    /*
     * 删除
     */
    public function rwdel(){
        $id = $this->request->param('id', 0, 'intval');
        $code=Db::name('taskinfo')->where(array('id'=>$id))->find();
        if (Db::name('taskinfo')->delete($id) !== false) {
            Db::name('task_item_info')->where(array('taskcode'=>$code['taskcode']))->delete();
            $this->success("删除成功！");
        } else {
            $this->error("删除失败！");
        }
    }
    public function rwgetiteminfo(){
        $id = $this->request->param('itemid', 0, 'intval');
        $info=Db::name('task_item_info')->where(array('id'=>$id))->find();
//        $this->writelog(Db::name('task_item_info')->getLastSql());
        $item=Db::name('iteminfo')->where(array('itemcode'=>$info['itemcode']))->find();
        $task=Db::name('taskinfo')->where(array('taskcode'=>$info['taskcode']))->find();
        $user=Db::name('user')->where(array('id'=>$task['taskcharge']))->find();

        //巡检前
        $where[]=['task_iteminfo_id','=',$id];
        $where[]=['execdatetime','like','%'.input('times').'%'];
        $where[]=['status','=','1'];
        $task_item=Db::name('task_check')->where($where)->order('id desc')->find();
        $pic=json_decode($task_item['pic'],true);
        //巡检后
        $where1[]=['task_iteminfo_id','=',$id];
        $where1[]=['execdatetime','like','%'.input('times').'%'];
        $where1[]=['status','=','2'];
        $task_item1=Db::name('task_check')->where($where1)->order('id desc')->find();
        $checkpic=json_decode($task_item1['checkpic'],true);
        $checkremark=$task_item1['checkremark'];

        $task_item['execdatetime']=date('Y-m-d',strtotime($task_item['execdatetime']));
        $this->assign('pic',$pic);
        $this->assign('checkpic',$checkpic);
        $this->assign('checkremark',$checkremark);
        $this->assign('taskitem',$task_item);
        $this->assign('user',$user);
        $this->assign('item',$item);
        $this->assign('info',$info);
        $this->assign('task',$task);
        return $this->fetch();
    }
public function timeshow(){
    $id = $this->request->param('id', 0, 'intval');
    $user = DB::name('taskinfo')->where("id", $id)->find();
    $jh=Db::name('planinfo')->where(array('id'=>$user['planinfoid']))->find();
    $date=unserialize($jh['datevalue']);
    $user['jhtime']=implode(',',$date['time']);
    $year=array();
//    $yr=array();
    $yrn=array();
    for($i=0;$i<count($date['time']);$i++){
        if(!empty($date['time'][$i])){
            $year[]=date('Y',strtotime($date['time'][$i]));
//            $yr[]=date('m-d',strtotime($date['time'][$i]));
        }
    }
    $year=array_unique($year);
    $year=array_merge($year,array());
    for($i=0;$i<count($year);$i++){
          $arr=array(); $arr1=array();
        for($j=0;$j<count($date['time']);$j++){
            $arrtime=array();
            $n= date('Y',strtotime($date['time'][$j]));
            $yr=date('m-d',strtotime($date['time'][$j]));
            if($year[$i]==$n){
                $arrtime['time']=$yr;
                $arr1[$year[$i]][]=$arrtime;
            }
        }

        $arr['nian']=$arr1;

        $yrn[]=$arr;

    }
    $this->assign('year',$year);
    $this->assign('yrn',$yrn);
    $this->assign('user',$user);
    return $this->fetch();
}
    public function geturl(){
//        $this->curl_post("https://192.168.1.111:81/videocallTest.html",array('isCall'=>'1','mId'=>'11','callId'=>'2'));
//        $a=$this->curl_get("https://192.168.1.111:81/videocallTest.html?isCall=1&mId=11&callId=2");
//        header("Location:https://192.168.1.111:81/videocallTest.html?isCall=1&mId=11&callId=2");
//        $this->assign('a',$a);
//        return $this->fetch();
//        header('Access-Control-Allow-Origin:https://192.168.1.111:81');
        $url="https://192.168.1.111:81/videocallTest.html?isCall=1&mId=11&callId=2";
//        $this->redirect($url);window.open
        echo "<script>window.open('https://192.168.1.111:81/videocallTest.html?isCall=1&mId=11&callId=2')</script>";
//        return $this->fetch();
    }

    /**
     * 监听
     */
    public function listion(){

         $l=Db::name('video_record')->where(array('mid'=>'1','callstate'=>'0'))->find();
         if(!empty($l)){
            echo $l['id'];
         }else{
             echo 2;
         }
    }

    /**
     * 修改接听状态
     */
    public function updatelistion(){
        $id=input('id');
        $data['callstate']='2';
        Db::name('video_record')->where(array('id'=>$id))->update($data);
    }

    /**
     * @param 任务轨迹
     */
    public function dtinfo(){
        $id=input('id');
        $item=Db::name('task_item_info')->where(array('id'=>$id))->find();
        $arr=[];
        $a['j']=$item['pic1'];
        $a['w']=$item['pic2'];
        $arr[]=$a;
//        dump(json_encode($arr));
        $this->assign('json',json_encode($arr));

//        $json='[{"j":"125.414076","w":"43.904238"},{"j":"125.382456","w":"43.874093"}]';
        return $this->fetch();
    }

    /**
     * @param $url
     * @param $postdata
     */
    public function dtlist(){

        $file= dirname(__FILE__).'/fc.kml';
        $xml = simplexml_load_file($file);
        $result = array();
//echo phpinfo();
        // 读取固定风机位置
        $values = $xml->Document->Folder->Placemark;
        $fjarr=[];
        if(!empty($values)){
            foreach($values as $v) {
                $fjarr1 = [];

                $lname=json_encode($v->name);
                $lname=json_decode($lname,true);
                $lng=json_encode($v->LookAt->longitude);
                $lng=json_decode($lng,true);
                $lat=json_encode($v->LookAt->latitude);
                $lat=json_decode($lat,true);
                //转换成百度经纬度
                $a=$this->wgs84togcj02($lng[0],$lat[0]);
                $b1=$this->gcj02tobd09($a[0],$a[1]);

                $fjarr1['longitude'] =$b1[0];
                $fjarr1['latitude'] =$b1[1];
                $fjarr1['name'] = $lname;
                $fjarr[]=$fjarr1;
            }

        }
        $data = $this->request->param();
        //获取redis
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $dw=1;//地图定位
//        $nrl = $redis->llen("dt");
//        $dt = $redis->lrange("dt", 0,-1);
//        dump($dt);

        $str='';
        $where=[];
        $arr=[];
        $planinfoid='';
        if ($this->request->isPost()) {
            if (isset($data['select_start_time']) && isset($data['select_end_time']) && $data['select_start_time'] && $data['select_end_time']) {
                //有时间段查询条件
                if(!empty($data['planinfoid'])){
                    $planinfoid=$data['planinfoid'];
                    $where[]=['a.wg','=',$data['planinfoid']];
                }
                $start_time = '' . $data['select_start_time'] ; //变成string类型
                $end_time = '' . $data['select_end_time'];
                $where[] = ['a.create_time', 'between time', [$start_time, $end_time]];
                $ul=Db::name('dt_loc')->alias('a')->field('a.*')->where($where)->order('id')->group('longitude,latitude')->select()->toArray();
//                dump($ul);
//                dump(Db::name('dt_loc')->getLastSql());
//                $data=$ul->items();
                foreach($ul as $key=>$val){
                    $a['name']=$val['create_time'];
                    $a['j']=$val['longitude'];
                    $a['w']=$val['latitude'];
                    $r=$this->escapeJsonString($val['time']);
                    $a['content']='网关:'.$val['wg'].'<br>上传时间:'.$r.'<br>经度:'.$val['longitude'].'<br>纬度:'.$val['latitude'];

                    $arr[]=$a;
                }
            }
        }else{
            //查看当日redis的列表
            $time=date('Y-m-d',time());
//            $time='2020-12-02';
            $rlist=$redis->lrange($time,0,-1);
//            if(!empty($rlist)){
                $len=$redis->lLen($time);//默认展示最后一个.
                if($len>=1){
                    $count=$len-1;
                    $strarr=explode('|',$rlist[$count]);
                    $a['name']=$strarr[3];
                    $a['j']=$strarr[0];
                    $a['w']=$strarr[1];
                    $time=empty($strarr[6])?$strarr[2]:$strarr[6];
                    $r=$this->escapeJsonString($time);
                    $a['content']='网关:'.$strarr[3].'上传时间:'.$r.'<br> 经度:'.$strarr[0].'<br>纬度:'.$strarr[1];
                    $arr[]=$a;
                    $dw=2;
                }
//                }
//                dump($arr);
//                $count=$len-1;
//                dump($len);
//                $strarr=explode('|',$rlist[$count]);
//                dump($strarr);
//                    $a['j']=$strarr[0];
//                    $a['w']=$strarr[1];
//                    $arr[]=$a;
//                $a['name']=$strarr[3];
//            }
//            $start_time= mktime(0, 0, 0, date('m'), date('d'), date('Y'));
//            $end_time= mktime(23, 59, 59, date('m'), date('d'), date('Y'));
//            $where[] = ['create_time', 'between time', [date('Y-m-d H:i:s',$start_time), date('Y-m-d H:i:s',$end_time)]];
        }
        $wgid=Db::name('dt_loc')->field('distinct(wg)')->paginate();
        $wglist=[];
        $wgi=$wgid->items();
        foreach($wgi as $valwg){
            if(!empty($valwg['wg'])){
                $wglist[]=$valwg;
            }
        }
        $this->assign('fjarr',json_encode($fjarr));
        $this->assign('dw',$dw);
        $this->assign('planinfoid',$planinfoid);
        $this->assign('wglist',$wglist);
        $this->assign('str',$str);
        $this->assign('json',json_encode($arr));
        return   $this->fetch();
    }

# list from www.json.org: (\b backspace, \f formfeed)
    public function escapeJsonString($value) {
        $escapers = array("\\", "/", "\"", "\n", "\r", "\t", "\x08", "\x0c");
        $replacements = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b");
        $result = str_replace($escapers, $replacements, $value);
        return $result;
    }

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
    function curl_get($url){

//        $header = array(
//        'Accept: application/json',
//        );
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
//        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
            //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            //执行命令
        $data = curl_exec($curl);
            // 打印返回的内容
            curl_close($curl);
            return $data;

        }

    //解析kml
    /**
     * 解析kml文件返回一个解析后的数据
     * @param $file
     * @return array
     */
    function parseKML($file)
    {
        // 获得文件内容
        $xml = simplexml_load_file($file);
        // 输出KML数据数组
        $result = array();

        // 读取document标签
        $values = $xml->Document;
        $floderArr = array();
        foreach ($values->Folder as $folder) {
            $floderArr[] = $folder;
        }

        // 读取style标签
        $styleArrs = array();
        foreach ($values->Style as $style) {

//            $jsonStyle = $this->xmltoarray($style);
//            $key = $jsonStyle["@attributes"]["id"];
//            $styleArrs[$key] = $jsonStyle;
        }
exit;
        // 分别获得线和区域的数据
        $placeMarksCache = array();
        foreach ($floderArr as $key => $value) {
            $name = (string)$value->name;
            if ($name == 'Area Features') {
                foreach ($value->Placemark as $placeMark) {
                    $placeMarksCache['area'][] = $placeMark;
                }
            } else {
                foreach ($value->Placemark as $placeMark) {
                    $placeMarksCache['lines'][] = $placeMark;
                }
            }
        }

        // 循环输出数据
        foreach ($placeMarksCache as $k => $place){
            // 获取要输出的点集
            $placeMarkOutCache = array();
            // 将点集read出来
            foreach ($place as $placeMark) {
                $styleCache = (string)$placeMark->styleUrl;
                $styleCache = str_replace("#", "", $styleCache);
                $styleCache = $styleArrs[$styleCache];
                if (!$styleCache) {
                    $styleCache = "00000000";
                }
                // 获取点集合
                $strCache = $placeMark->Polygon;
                if ($strCache) {
                    $styleCache = "#" . $styleCache["PolyStyle"]["color"];
                    $strCache = (string)$strCache->outerBoundaryIs->LinearRing->coordinates;
                    $strCache = explode("\n              ", trim($strCache));
                } else {
                    $styleCache = "#" . $styleCache["LineStyle"]["color"];
                    $strCache = (string)$placeMark->LineString->coordinates;
                    $strCache = explode("\n          ", trim($strCache));
                }
                // 分割点集 作为数组进行保存
                foreach ($strCache as $sc) {
                    $args = explode(",", $sc);
                    $coords[] = array($args[0], $args[1], $args[2]);
                }
                // 将color 和 points 作为对象保存到result中
                $result[$k][] = array("color" => $styleCache, "points" => $coords);
                // 将这个数组清空
                $coords = array();
            }
        }
        // 最后返回集合点数据
        return $result;
    }
    /**
     * WGS84转GCj02(北斗转高德)
     * @param lng
     * @param lat
     * @returns {*[]}
     */
    public function wgs84togcj02($lng,  $lat) {
        if ($this->out_of_china($lng, $lat)) {
            return array($lng, $lat);
        } else {
            $dlat = $this->transformlat($lng - 105.0, $lat - 35.0);
            $dlng = $this->transformlng($lng - 105.0, $lat - 35.0);
            $radlat = $lat / 180.0 * self::PI;
            $magic = sin($radlat);
            $magic = 1 - self::ee * $magic * $magic;
            $sqrtmagic = sqrt($magic);
            $dlat = ($dlat * 180.0) / ((self::a * (1 - self::ee)) / ($magic * $sqrtmagic) * self::PI);
            $dlng = ($dlng * 180.0) / (self::a / $sqrtmagic * cos($radlat) * self::PI);
            $mglat = $lat + $dlat;
            $mglng = $lng + $dlng;
            return array($mglng, $mglat);
        }
    }
    /**
     * GCJ02 转换为 WGS84 (高德转北斗)
     * @param lng
     * @param lat
     * @return array(lng, lat);
     */
    public function gcj02towgs84($lng, $lat) {
        if ($this->out_of_china($lng, $lat)) {
            return array($lng, $lat);
        } else {
            $dlat = $this->transformlat($lng - 105.0, $lat - 35.0);
            $dlng = $this->transformlng($lng - 105.0, $lat - 35.0);
            $radlat = $lat / 180.0 * self::PI;
            $magic = sin($radlat);
            $magic = 1 - self::ee * $magic * $magic;
            $sqrtmagic = sqrt($magic);
            $dlat = ($dlat * 180.0) / ((self::a * (1 - self::ee)) / ($magic * $sqrtmagic) * self::PI);
            $dlng = ($dlng * 180.0) / (self::a / $sqrtmagic * cos($radlat) * self::PI);
            $mglat = $lat + $dlat;
            $mglng = $lng + $dlng;
            return array($lng * 2 - $mglng, $lat * 2 - $mglat);
        }
    }


    /**
    　　* 百度坐标系 (BD-09) 与 火星坐标系 (GCJ-02)的转换
    　　* 即 百度 转 谷歌、高德
    　　* @param bd_lon
    　　* @param bd_lat
    　　* @returns
    　　*/
    public function bd09togcj02 ($bd_lon, $bd_lat) {
        $x_pi = 3.14159265358979324 * 3000.0 / 180.0;
        $x = $bd_lon - 0.0065;
        $y = $bd_lat - 0.006;
        $z = sqrt($x * $x + $y * $y) - 0.00002 * sin($y * $x_pi);
        $theta = atan2($y, $x) - 0.000003 * cos($x * $x_pi);
        $gg_lng = $z * cos($theta);
        $gg_lat = $z * sin($theta);
        return array($gg_lng, $gg_lat);
    }

    /**
     * GCJ-02 转换为 BD-09  （火星坐标系 转百度即谷歌、高德 转 百度）
     * @param $lng
     * @param $lat
     * @returns array(bd_lng, bd_lat)
     */
    public function gcj02tobd09($lng, $lat) {
        $z = sqrt($lng * $lng + $lat * $lat) + 0.00002 * sin($lat * self::x_PI);
        $theta = atan2($lat, $lng) + 0.000003 * cos($lng * self::x_PI);
        $bd_lng = $z * cos($theta) + 0.0065;

        $bd_lat = $z * sin($theta) + 0.006;
        return array($bd_lng, $bd_lat);
    }
    public function gcjtobd($lng,$lat){
        echo $lng.'!!!'.$lat;
        $z = sqrt($lng * $lng + $lat * $lat) + 0.00002 * sin($lat * self::x_PI);
        $theta = atan2($lat, $lng) + 0.000003 * cos($lng * self::x_PI);
        $bd_lng = $z * cos($theta) + 0.0065;

        $bd_lat = $z * sin($theta) + 0.006;
        return array($bd_lng, $bd_lat);
    }


    private function transformlat($lng, $lat) {
        $ret = -100.0 + 2.0 * $lng + 3.0 * $lat + 0.2 * $lat * $lat + 0.1 * $lng * $lat + 0.2 * sqrt(abs($lng));
        $ret += (20.0 * sin(6.0 * $lng * self::PI) + 20.0 * sin(2.0 * $lng * self::PI)) * 2.0 / 3.0;
        $ret += (20.0 * sin($lat * self::PI) + 40.0 * sin($lat / 3.0 * self::PI)) * 2.0 / 3.0;
        $ret += (160.0 * sin($lat / 12.0 * self::PI) + 320 * sin($lat * self::PI / 30.0)) * 2.0 / 3.0;
        return $ret;
    }
    private function transformlng($lng, $lat) {
        $ret = 300.0 + $lng + 2.0 * $lat + 0.1 * $lng * $lng + 0.1 * $lng * $lat + 0.1 * sqrt(abs($lng));
        $ret += (20.0 * sin(6.0 * $lng * self::PI) + 20.0 * sin(2.0 * $lng * self::PI)) * 2.0 / 3.0;
        $ret += (20.0 * sin($lng * self::PI) + 40.0 * sin($lng / 3.0 * self::PI)) * 2.0 / 3.0;
        $ret += (150.0 * sin($lng / 12.0 * self::PI) + 300.0 * sin($lng / 30.0 * self::PI)) * 2.0 / 3.0;
        return $ret;
    }


    private function rad($param)
    {
        return  $param * self::PI / 180.0;
    }
    /**
     * 判断是否在国内，不在国内则不做偏移
     * @param $lng
     * @param $lat
     * @returns {boolean}
     */
    private function out_of_china($lng, $lat) {
        return ($lng < 72.004 || $lng > 137.8347) || (($lat < 0.8293 || $lat > 55.8271) || false);
    }





}