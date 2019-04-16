<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;

class WeixinController extends Controller
{
    /**
     *
     *开发者通过检验signature对请求进行校验（下面有校验方式）。
     *
     * 若确认此次GET请求来自微信服务器，请原样返回echostr参数内容，则接入生效，成为开发者成功
     *
     * 微信接口
     */
    public function index()
    {
        echo $_GET['echostr'];
    }

    /**
     *接受微信的推送事件
     */
    public function wxEven()
    {
        //接受微信服务器推送
        $text = file_get_contents('php://input');
        $time = date('Y-m-d H:i:s');
        $str = $time . $text . "\n";
        // is_dir('logs') or mkdir('logs', 0777, true);
        file_put_contents("logs/wx_event.log", $str, FILE_APPEND);
        $data = simplexml_load_string($text);
        $wx_id = $data-> ToUserName;  //公众号id
        $openid = $data-> FromUserName;//用户的openid
        $Content = $data-> Content; //微信发送的内容
        // echo $Content;
   
        $CreateTime = $data -> CreateTime; //消息发送的时间
        // echo $CreateTime;
    //    echo $data-> CreateTime;echo "<br>";  //推送时间
        $MsgType = $data-> MsgType;   //消息类型  image  voice 
        // echo $MsgType;
        // echo  $content;echo "<br>";
        $type =  $data-> Event;    //事件类型
        $MediaId = $data -> MediaId;
        // echo $MediaId;
//        echo $data-> EventKey;echo "<br>";  //事件密钥
        //substribe 扫码关注事件
       if($type =='subscribe'){
            //根据openid来查是否是唯一用户关注
            $l = DB::table('p_weixin')->where(['openid'=>$openid])->first();
            $x= json_encode($l,true);
            $arr = json_decode($x,true);
            // var_dump($arr);die;
            if($arr){ //关注过
                //微信可咦通过 xml 格式来返回给微信用户消息
                echo '<xml><ToUserName><![CDATA['.$openid.']]></ToUserName><FromUserName><![CDATA['.$wx_id.']]></FromUserName><CreateTime>'.time().'</CreateTime><MsgType><![CDATA[text]]></MsgType><Content><![CDATA['.'欢迎回来'.$arr['nickname'].']]></Content></xml>';
            }else{
                 //获取用户信息
                $result = $this -> userinfo($openid);
                $ll = $result['openid'];
                //用户信息入库
                $usersinfo = [
                    'openid'=>$ll,
                    'nickname'=> $result['nickname'],
                    'sex'=> $result['sex'],
                    'city'=> $result['city'],
                    'province'=> $result['province'],
                    'headimgurl'=> $result['headimgurl'],
                    'subscribe_time'=> $result['subscribe_time']
                ];
                $insert = DB::table('p_weixin')->insert($usersinfo);
                // echo 1;die;
                echo '<xml><ToUserName><![CDATA['.$openid.']]></ToUserName><FromUserName><![CDATA['.$wx_id.']]></FromUserName><CreateTime>'.time().'</CreateTime><MsgType><![CDATA[text]]></MsgType><Content><![CDATA['.'欢迎关注 '.$arr['nickname'].']]></Content></xml>';
            
            }
       }
       if($MsgType == 'text'){

        $date = [
                'openid'=>$openid,
                'text'=> $Content,
                'text_time'=>$CreateTime

        ];
            $save = DB::table('wx_text')->insert($date);

        //自动回复天气
            if(strpos($Content,'+天气')){ //查找字符串首次出现
                // echo $Content;
                $city = explode("+",$Content)[0]; //0 是城市   
                // var_dump($city);
                //get
                $url = 'https://free-api.heweather.net/s6/weather/now?parameters&location='.$city.'&key=HE1904161039151125';
                $arr = json_decode(file_get_contents($url),true);
                // var_dump($arr);die;
                if($arr['HeWeather6'][0]['status'] != 'ok'){
                    echo '<xml><ToUserName><![CDATA['.$openid.']]></ToUserName><FromUserName><![CDATA['.$wx_id.']]></FromUserName><CreateTime>'.time().'</CreateTime><MsgType><![CDATA[text]]></MsgType><Content><![CDATA['.'客观，您好，请输入正确的城市'.']></Content></xml>';
                }else{
                    $fl = $arr['HeWeather6'][0]['now']['tmp'];    //温度
                    $cond_txt = $arr['HeWeather6'][0]['now']['cond_txt'];  //天气桩孔
                    $hum = $arr['HeWeather6'][0]['now']['hum'];   //适度
                    $wind_sc = $arr['HeWeather6'][0]['now']['wind_sc'];   //方向
                    $wind_dir = $arr['HeWeather6'][0]['now']['wind_dir'];  //放立
                    $str = '温度:'.$fl."\n".'天气状况:'.$cond_txt."\n".'相对湿度:'.$hum."\n".'风力:'.$wind_sc."\n".'风向:'.$wind_dir."\n";

                    echo'<xml><ToUserName><![CDATA['.$openid.']]></ToUserName>
                    <FromUserName><![CDATA['.$wx_id.']]></FromUserName>
                    <CreateTime>'.time().'</CreateTime>
                    <MsgType><![CDATA[text]]></MsgType>
                    <Content><![CDATA['.'您所在的'.$city.'天气状况如下'.$str.']]></Content>
                    </xml>';

                }
            }
       }else if($MsgType == 'image'){
            $wx_images_path =  $this->images($MediaId);
             //图片信息入库
             $date = [
                'openid'=>$openid,
                'images'=> $wx_images_path,
                'images_time'=>$CreateTime
             ];
             $info = DB::table('wx_image')->insert($date);
             var_dump($info);
           
       }else if($MsgType == 'voice'){
           $wx_volices_path =  $this->voices($MediaId);
             $date = [
                'openid'=>$openid,
                'volice'=> $wx_volices_path,
                'volice_time'=>$CreateTime
             ];
             $info = DB::table('wx_volice')->insert($date);
             
       }
    }

    /**
     *获取微信accesstoken
     * access_token是公众号的全局唯一接口调用凭据，
     */
    public function token()
    {
        $key = "access_token";
        $data = redis::get($key);
        if($data){

        }else{
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=wx48451c201710dbcd&secret=f583f90f3aed8ec33ae6dd30eceebe5f";
            $response = file_get_contents($url);
            $arr = json_decode($response, true);

//        print_r($arr);

            redis::set($key, $arr['access_token']); //存入access_token
            redis::expire($key, 3600);
            $data = $arr['access_token'];
        }
        return $data;


    }
    //获取微信用户信息
    public function userinfo($openid)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token='.$this->token().'&openid='.$openid.'&lang=zh_CN';
        $res =  file_get_contents($url);
        $info = json_decode($res,true);
        return $info;
    }
    //创建微信公众号菜单
    /**
     * 1、用代码实现需下载第三方类库 
     * 2、POST（请使用https协议） https://api.weixin.qq.com/cgi-bin/menu/create?access_token=ACCESS_TOKEN
     * 3、 composer require guzzlehttp(库名)/guzzle
     */
    public function create(){
        // echo 1111;die;
        $url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token='.$this->token();
        //接口数据
        $post_arr = [
            'button' =>[
                [
                    'type'=> 'click',
                    'name'=> '球球',
                    'key'=>"V1001_TODAY_MUSIC"
                ],
                [
                    // 'name'=> '月七',
                    // "sub_button" =>[
                    //     'type'=> 'view',
                    //     'name'=> '搜索',
                    //     'url'=>"http://www.soso.com/"
                    // ],
                    // [
                        'type'=> 'click',
                        'name'=> '赞下',
                        'key'=>"V1001_GOOD"
                    // ]
                ]
            ]
        ];
        //格式JSON
        $json = json_encode($post_arr,JSON_UNESCAPED_UNICODE); //JSON_UNESCAPED_UNICODE 处理中文
        //发送请求
        $client = new Client();
        $response = $client->request('POST',$url,[
            'body' => $json
        ]);
        //处理响应
        $res = $response->getBody();
        echo $res;

        // $arr = json_decode();
    }
    /**
     * 
     * 下载图片
     * 用代码实现需加载第三方类库 发送请求
     */
    public function images($MediaId)
    {   //调用接口  
        $url =  'https://api.weixin.qq.com/cgi-bin/media/get?access_token='.$this->token().'&media_id='.$MediaId;
        //发送请求
        $client =  new Client();
        $response = $client->get($url);
        // $response=$clinet->request('GET',$url);
        //   var_dump($response);
        //获取文件名
        $file_info = $response->getHeader('Content-disposition'); //数组
        // var_dump($file_info);die;
       $file_name = substr(trim($file_info[0],'"'),-20);
       $new_file_name = rand(1111,9999).'_'.time().$file_name;
        // echo $new_file_name;
       $re= Storage::put('weixin/images/'.$new_file_name,$response->getBody());
    
       $wx_images_path ='weixin/images/'.$new_file_name;
        return  $wx_images_path;
    }
    //下载语音
    public function voices($MediaId)
    {
       
        $url = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token='.$this->token().'&media_id='.$MediaId;
     
        $client = new Client();
       
        $response =  $client->get($url);
    
        $file_info = $response->getHeader('Content-disposition'); //数组
      
       $file_name = substr(trim($file_info[0],'"'),-15);
       
       $new_file_name = rand(1111,9999).'_'.time().$file_name;
   
       $res= Storage::put('weixin/volices/'.$new_file_name,$response->getBody());
       
       $wx_volices_path ='weixin/volices/'.$new_file_name;
       
       return $wx_volices_path;
      
    }
}