<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Str;

use App\Http\Controllers\WXBizDataCryptController;

class WxPayController extends Controller
{
    /*生成一个二维码
			    调用统一下单接口	https://api.mch.weixin.qq.com/pay/unifiedorder
				1 组合参数
				2 签名
				3 请求接口，接收响应数据	
				4 将code_url传递给前端，并生成二维码
				5 用户扫码支付
				6 服务端接收通知回调
				7 验证签名，处理业务逻辑（更新订单信息，支付时间 支付金额 支付的用户）
     * 
     */


    //测试微信支付
    //全局变量
    public $values = [];
    public  $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder'; //统一下单接口
    public  $notify_url = 'http://1809zhushimao.comcto.com/notify'; //支付成功回调
    public function test()
    {
       //组合参数
       $total_fee = 1; //用户要支付的金额  1分
       $order_id = time().mt_rand(11111,99999).'_zhushimao'; //生成订单号
       //必填参数
       $order_info =[
           'appid' => 'wxd5af665b240b75d4', //公众帐号id
           'mch_id' => '1500086022', //商户id
           'nonce_str'=> Str::random(16), //随机的字符串
           'sign_type' => 'MD5', //签名类型
           'body' => '测试微信支付-'.mt_rand(1111,9999).Str::random(6), //商品简单描述，
            'out_trade_no' => $order_id, //订单
            'total_fee' => $total_fee,//订单总金额
            'spbill_create_ip' => $_SERVER['REMOTE_ADDR'], //客户端ip
            'notify_url' => $this-> notify_url, //通知回调地址
            'trade_type' => 'NATIVE' // 交易类型
       ];
         $this-> values = $order_info;
       //签名
       //商户后台和微信支付后台根据相同的密钥和算法生成一个结果，用于校验双方身份合法性。
         $this ->Setsign(); //order_info
         $xml = $this-> Toxml(); //将数组转化为xml格式
        //  var_dump($xml); echo "<hr>";
        //发送请求
         $res =$this ->postXmlCurl($xml,$this->url,$useCert = false, $second = 30 );
         $data =  simplexml_load_string($res);
         //trade_type=NATIVE时有返回，此url用于生成支付二维码，然后提供给用户进行扫码支付。
        // echo 1111;
         echo 'return_code: '.$data->return_code;echo '<br>';
		echo 'return_msg: '.$data->return_msg;echo '<br>';
		echo 'appid: '.$data->appid;echo '<br>';
		echo 'mch_id: '.$data->mch_id;echo '<br>';
		echo 'nonce_str: '.$data->nonce_str;echo '<br>';
		echo 'sign: '.$data->sign;echo '<br>';
		echo 'result_code: '.$data->result_code;echo '<br>';
		echo 'prepay_id: '.$data->prepay_id;echo '<br>';
		echo 'trade_type: '.$data->trade_type;echo '<br>';
       echo 'code_url: '.$data->code_url;echo '<br>';

         $data = [
            'code_url'  => $data->code_url
        ];
        return view('weixin.test',$data);

    }
    public function Setsign()  //& 引用值  order_info  
    {   
        $sign = $this ->MakeSign();
        $this ->values['sign'] = $sign;
        return $sign;
    }
   /* private 只能在自身所在类中访问
	public 在任何地方都可以访问
    protected 只能在自身所在得类，以及子类访问
  */
  //生成签名
    private function MakeSign()
    {
        //签名步骤一：按字典序排序参数
        ksort($this-> values);
        $string = $this -> ToUrlParams();
         //签名步骤二：在string后加入KEY
        $string = $string . "&key=".'7c4a8d09ca3762af61e59520943AB26Q';
        // echo $string;
         //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }
    //格式化组成的参数
    protected function ToUrlParams()
    {
        $buff = "";
        foreach($this-> values as $k=>$v)
        {
            //把组成参数拼接上 
            //如果键名不是签名 并且$v值为空   并且$v不为数组
            if($k != 'sign' && $v != "" && !is_array($v)){
                $buff .= $k. "=" . $v . "&";
            }
        }
        $buff = trim($buff,'&');
        return $buff;
    }
    //将数组转化为xml格式
    protected function Toxml()
    {
        if(!is_array($this->values) || count($this->values) <= 0)
        {
            die("数组数据异常！");
        }
        $xml = "<xml>";
        foreach ($this-> values as $key=>$val)
        {
            // var_dump($val);echo "<hr>";
            //检测变量是否是数字
            if (is_numeric($val)){
                $xml.="<".$key.">".$val."</".$key.">";
            }else{
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
            $xml.="</xml>";
            return $xml;
    }
    private  function postXmlCurl($xml, $url, $useCert = false, $second = 30)
    {
        // echo 22222222222;
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);//严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //		if($useCert == true){
        //			//设置证书
        //			//使用证书：cert 与 key 分别属于两个.pem文件
        //			curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
        //			curl_setopt($ch,CURLOPT_SSLCERT, WxPayConfig::SSLCERT_PATH);
        //			curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
        //			curl_setopt($ch,CURLOPT_SSLKEY, WxPayConfig::SSLKEY_PATH);
        //		}
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if($data){
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            die("curl出错，错误码:$error");
        }
    }   
    public function notify()
    {
        $data = file_get_contents('php://input');
        //记录日志
        $log_str = date('Y-m-d H:i:s') . "\n" . $data . "\n<<<<<<<";
        file_put_contents('logs/wx_pay_notice.log',$log_str,FILE_APPEND);
        $xml = simplexml_load_string($data);
        if($xml->result_code=='SUCCESS' && $xml->return_code=='SUCCESS'){      //微信支付成功回调
            //验证签名
            $sign = true;
            if($sign){       //签名验证成功
                //TODO 逻辑处理  订单状态更新
            }else{
                //TODO 验签失败
                echo '验签失败，IP: '.$_SERVER['REMOTE_ADDR'];
                // TODO 记录日志
            }
        }
        $response = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        echo $response;
    }
}