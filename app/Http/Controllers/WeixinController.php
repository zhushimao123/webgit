<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
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
    //    echo $data-> CreateTime;echo "<br>";  //推送时间
        $MsgType = $data-> MsgType;   //消息类型  image  voice
        // echo  $content;echo "<br>";
        $type =  $data-> Event;    //事件类型
        $MediaId = $data -> MediaId;
//        echo $data-> EventKey;echo "<br>";  //事件密钥
        //substribe 扫码关注事件
       if($type =='subscribe'){
            //根据openid来查是否是唯一用户关注
            $arr = DB::table('p_weixin')->where(['openid'=>$openid])->first();
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
            $Content = $data-> Content; //微信发送的内容
            $result = $this -> userinfo($openid);  //用户信息
            $ll = $result['openid'];
            $date = [
                'openid'=>$ll,
                'nickname'=> $result['nickname'],
                'sex'=> $result['sex'],
                'city'=> $result['city'],
                'province'=> $result['province'],
                'headimgurl'=> $result['headimgurl'],
                'subscribe_time'=> $result['subscribe_time'],
                'text'=> $Content
            ];
            $save = DB::table('wtext')->insertGetId($date);
            print_r($save);
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
                        "type"=>"view",
                        "name"=>"搜索",
                        "url"=>"http://www.soso.com/"
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
    //获取临时素材
    public function content()
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token='.$this->token().'&media_id=MEDIA_ID';
        $text = file_get_contents($url);
        echo $text;
      

    }
}