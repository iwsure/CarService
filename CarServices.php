<?php defined('BASEPATH') OR exit('No direct script access allowed');

class CarServices
{
    const API_URL_PREFIX = 'https://es.api.xiaojukeji.com/v1/';     //API域名前缀
    const AUTH_URL = 'Auth/authorize';                              //POST授权认证
    const ORDERID_URL = "order/Create/orderId";                     //Get方式请求oeder的ID
    const REQUEST_URL = "order/Create/request";                     //Post叫车请求
    const GETORDERDETAIL="order/Detail/getOrderDetail";             //获取订单详情
    const REREQUEST="order/Create/reRequest";                       //重新请求叫车
    const ADDRESS="common/Address/getAddress";                      //地址联想
    const GETCITYCAR = "common/CarLevel/getCityCar";                //获取城市车型
    const CANCEL ="order/Cancel";                                   //取消订单
    const PAYCONFIRM="order/PayConfirm/index";                      //支付确认
    const HISTORY="order/History/index";

    
    private $clientId;
    private $clientSecret;
    private $grantType;
    private $signKey;
    private $phone;
    
    /**
     * [初始化滴滴叫车类]
     * @author [fengchenorz@gmail.com]
     * @DateTime 2016-02-06T10:23:46+0800
     */
    public function __construct()
    {
        $this->clientId = isset(config_item('didi.client_id')) ? config_item('didi.client_id') : '';
        $this->clientSecret = isset(config_item('didi.client_secret')) ? config_item('didi.client_secret') : '';
        $this->grantType = isset(config_item('didi.grant_type')) ? config_item('didi.grant_type') : '';
        $this->signKey = isset(config_item('didi.sign_key')) ? config_item('didi.sign_key') : '';
        $this->phone = isset(config_item('didi.phone')) ? config_item('didi.phone') : '';

        $this->load->driver('cache');
    }
    /**
     * [通过sign_key生成sign算法实现]
     * @author [fengchenorz@gmail.com]
     * @DateTime 2016-02-06T10:24:34+0800
     * @param    [array]                   $params [参数数组]
     * @return   [string]                          [sign]
     */
    private  function getSign($params){
        if(!empty($this->signKey)){
            $params['sign_key'] = $this->signKey;
            ksort($params); 
            $str = '';
            foreach ($params as $k => $v) {         
                if ('' == $str) {
                    $str .= $k . '=' . trim($v);
                } else {
                    $str .= '&' . $k . '=' . trim($v);
                }
            } 
            $sign = md5($str);
            return $sign;
        }else{
            return "参数缺失！";
        }
        
    }
    /**
     * 第一步，进行授权认证，获取access_token
     * 参数  client_id       string  yes 申请应用时分配的AppKey
     *      client_secret   string  yes 申请应用时分配的AppSecret
     *      grant_type      string  yes 请求的类型，填写client_credentials
     *      phone           string  yes 当前用户的手机号
     *      timestamp       int     yes 当前时间戳
     *      sign            string  yes 签名
     */
    public function curlToken($timestamp){

        $didi_token = $this->cache->redis->get('car_services_token');
        if(isset($didi_token) && !empty($didi_token)) {
            $token = $didi_token;
        } else {
            $data = array(
                'client_id'=>$this->clientId,
                'client_secret'=>$this->clientSecret,
                'grant_type'=>$this->grantType,
                'phone'=>$this->phone,
                'timestamp'=>$timestamp,
            );
            $data['sign'] = $this->getSign($data);
            $url = self::API_URL_PREFIX.self::AUTH_URL;
            $result = $this->subCurl($url, $data);
            
            $result=json_decode($result,true);  //将获得的json格式转换为数组
            $token = $result['access_token'];
                
            $this->cache->redis->save('car_services_token', $token, 3600);//存入redis
        }
        
        return $token;
    }
    /**
     * [获取请求id]
     * @author [fengchenorz@gmail.com]
     * @DateTime 2017-02-06T10:26:24+0800
     * @param    [string]          $token     
     * @param    [type]            $timestamp
     * @return   [type]                             
     */
    public function getOrderId($token, $timestamp){
        
        $data = array(
            'client_id'=>$this->clientId,
            'access_token'=>$token,
            'timestamp'=>$timestamp,
        );
        $data['sign'] = $this->getSign($data);
        $url = self::API_URL_PREFIX.self::ORDERID_URL;
        $result = $this->subCurl($url, $data,0);
            
        $result=json_decode($result,true);  //将获得的json格式转换为数组
        $order_id = $result['data']['order_id'];
        
        if($order_id){
            return $order_id;
        }else{
            return $result;
        }
        
    }
    /**
    *发起叫车请求
    * client_id         string      yes 申请应用时分配的AppKey
    * access_token      string      yes 授权后的access token
    * timestamp         int         yes 当前时间戳
    * sign              string      yes 签名 详细算法参见 签名验证 章节
    * order_id          string      yes 请求id 获取请参见 获取请求id
    * rule              int         yes 计价模型分类，201(普通)；202(套餐)；301(快车)
    * type              int         yes 叫车车型，0(实时)；1(预约)
    * passenger_phone   string      no  乘客手机号，不填表示给自己叫车
    * city              int         yes 出发地城市
    * flat              float       yes 出发地纬度
    * flng              float       yes 出发地经度
    * start_name        string      yes 出发地名称(最多50个字)
    * start_address     string      no  出发地详细地址(最多100个字)
    * departure_time    datetime    no  出发时间，不传表示现在用车（例如：2015-06-16 12:00:09）
    * equire_level      string      yes 所需车型
    * app_time          datetime    yes 客户端时间（例如：2015-06-16 12:00:09）
     */
    public function orderRequest($token, $timestamp, $order_id, $passenger_phone, $city, $flat, $flng, $start_name, $tlat, $tlng, $end_name, $end_address, $departure_time, $app_time){
        $data = array(
            'client_id'=>$this->clientId,
            'access_token'=>$token,
            'timestamp'=>$timestamp,
            //'sign'=>$sign,
            'order_id'=>$order_id,
            'passenger_phone'=>$passenger_phone,
            'rule'=>201,
            'type'=>1,
            'city'=>$city,
            'flat'=>$flat,
            'flng'=>$flng,
            'start_name'=>urlencode($start_name),
            'tlat'=>$tlat,
            'tlng'=>$tlng,
            'end_name'=>urlencode($end_name),
            'end_address'=>urlencode($end_address),
            'require_level'=>'100',
            'departure_time'=>$departure_time,
            'app_time'=>$app_time,
        );
        $data['sign'] = $this->getSign($data);
        $url = self::API_URL_PREFIX.self::REQUEST_URL;
        $result = $this->fileGetContentsPost($url, $data);
            
        $result=json_decode($result,true);  //将获得的json格式转换为数组
        //$order_id = $result['data']['order']['id'];
        return $result;
    }
    /*
    * 请求订单详情
    * client_id string  yes 申请应用时分配的APP_KEY
    * access_token  string  yes 乘客认证信息
    * timestamp int yes 时间戳
    * order_id  int yes 订单id
    * sign  string  yes 签名
    */
    public function getOrderDetail($token, $timestamp, $order_id){
        $data = array(
            'client_id'=>$this->clientId,
            'access_token'=>$token,
            'timestamp'=>$timestamp,
            'order_id'=>$order_id,
            //'sign'=>$sign,
        );
        $data['sign'] = $this->getSign($data);
        $url = self::API_URL_PREFIX.self::GETORDERDETAIL;
        $result = $this->subCurl($url, $data,0);
            
        $result=json_decode($result,true);  //将获得的json格式转换为数组
        return $result;
        // $order_id = $result['data']['order']['status'];
        
        // if($order_id){
        //  return $result;
        // }else{
        //  return $result['errmsg'];
        // }
    }
    /**
    * client_id         string  yes 申请应用时分配的AppKey
    * access_token      string  yes 授权后的access token
    * timestamp         int     yes 当前时间戳
    * sign              string  yes 签名 详细算法参见 签名验证 章节
    * order_id          string  yes 订单id
    * require_level     string  no  叫车车型
    */
    public function reRequest($token, $timestamp, $sign, $order_id, $city, $flat, $flng, $start_name){
        $data = array(
            'client_id'=>$this->clientId,
            'access_token'=>$token,
            'timestamp'=>$timestamp,
            //'sign'=>$sign,
            'order_id'=>$order_id,
            'require_level'=>100,
        );
        $data['sign'] = $this->getSign($data);
        $url = self::API_URL_PREFIX.self::REREQUEST;
        $result = $this->subCurl($url, $data);
            
        $result=json_decode($result,true);  //将获得的json格式转换为数组
        $order_id = $result['data']['order']['id'];
        
        if($order_id){
            return $result;
        }else{
            return $result['errmsg'];
        }
    }
    /**
     * 地址联想
     * @createtime 2016.3.16
     * client_id    string  yes 申请应用时分配的AppKey
    * access_token  string  yes 授权后的access token
    * city  string  yes 城市名称，如北京
    * input string  yes 搜索词
    * timestamp int yes 当前时间戳
    * sign  string  yes 签名
     */
    public function getAddress($timestamp, $token, $city_name, $keyword){
        $data = array(
            'client_id'=>$this->clientId,
            'access_token'=>$token,
            'timestamp'=>$timestamp,
            //'sign'=>$sign,
            'city'=>$city_name,
            'input'=>$keyword,
        );
        $data['sign'] = $this->getSign($data);
        $url = self::API_URL_PREFIX.self::ADDRESS;
        $result = $this->subCurl($url, $data, 0);
            
        $result=json_decode($result,true);  //将获得的json格式转换为数组
        //$place_data = $result['data']['place_data'];
        return $result;
        // if($place_data){
        //  return $result;
        // }else{
        //  return $result['errmsg'];
        // }
    }
    //获取城市车型
    public function getCityCar($token,$timestamp)
    {
        $data = array(
            'client_id'=>$this->clientId,
            'access_token'=>$token,
            'timestamp'=>$timestamp,
            'rule'=>201,
        );
        $data['sign'] = $this->getSign($data);
        $url = self::API_URL_PREFIX.self::GETCITYCAR;
        $result = $this->subCurl($url, $data, 0);
            
        $result=json_decode($result,true);  //将获得的json格式转换为数组
        //$place_data = $result['data']['place_data'];
        return $result;
    }
    /**
     * CURL请求
     * @createtime 2016.3.16
     * @param $url curl请求地址
     * @param $data curl请求数据
     * @param $is_post curl请求类型，1-POST 0-GET
     */
    public function subCurl($url,$data,$is_post=1){
        $ch = curl_init();
        if(!$is_post) {
            $url =  $url.'?'.http_build_query($data);
        }
        /* 设置验证方式 */
        //curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Content-Type:application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, $is_post);
        if($is_post) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $info = curl_exec($ch);
        curl_close($ch);

        return $info;
    }
    /**
     * [fileGetContentsPost]
     * @author [author]
     * @DateTime 2017-02-06T10:29:25+0800
     * @param    [string]                  $url  [description]
     * @param    [array]                   $data [description]
     */
    private function fileGetContentsPost($url, $data) {
        $options = array(
                'http' => array(
                        'method' => 'POST',
                        'header' => "Content-type: application/x-www-form-urlencoded",
                        'content' => http_build_query($data),
                        'timeout' => 1,
                ),
        );
        $result = file_get_contents($url, false, stream_context_create($options));
        return $result;
    }
    /**
     * [取消订单]
     * @author [author]
     * @DateTime 2017-02-06T10:30:36+0800
     * @param    [type]                   $token     [description]
     * @param    [type]                   $timestamp [description]
     * @param    [type]                   $order_id  [description]
     * @param    string                   $force     [description]
     * @return   [type]                              [description]
     */
    public function cancelOrder($token, $timestamp, $order_id, $force = ''){
        $data = array(
            'client_id'=>$this->clientId,
            'access_token'=>$token,
            'timestamp'=>$timestamp,
            'order_id'=>$order_id,
        );
        if(!empty($false)){
              $data['force'] = $force;
        }
        $data['sign'] = $this->getSign($data);
        $url = self::API_URL_PREFIX.self::CANCEL;
        $result = $this->fileGetContentsPost($url, $data);
            
        $result=json_decode($result,true);  //将获得的json格式转换为数组
        //$cost = $result['data']['cost'];
        return $result;
    }
    /**
     * [支付确认]
     * @author [author]
     * @DateTime 2017-02-06T10:31:08+0800
     * @param    [type]                   $token     [description]
     * @param    [type]                   $timestamp [description]
     * @param    [type]                   $order_id  [description]
     */
    public function PayConfirm($token,$timestamp,$order_id){
        $data = array(
            'client_id'=>$this->clientId,
            'access_token'=>$token,
            'timestamp'=>$timestamp,
            'order_id'=>$order_id,
        );
        $data['sign'] = $this->getSign($data);
        $url = self::API_URL_PREFIX.self::PAYCONFIRM;
        $result = $this->fileGetContentsPost($url, $data);
            
        $result=json_decode($result,true);  //将获得的json格式转换为数组
        //$place_data = $result['data']['place_data'];
        return $result;
    }
    /**
     * [getAllOrder 获取所有订单接口]
     * @author [author]
     * @DateTime 2017-02-06T10:31:37+0800
     * @param    [type]                   $token      [description]
     * @param    [type]                   $timestamp  [description]
     * @param    [type]                   $start_date [description]
     * @param    [type]                   $end_date   [description]
     * @param    [type]                   $pageno     [description]
     * @return   [type]                               [description]
     */
    public function getAllOrder($token, $timestamp, $start_date, $end_date,$pageno){
        $data = array(
            'client_id'=>$this->clientId,
            'access_token'=>$token,
            'timestamp'=>$timestamp,
            'start_date'=>$start_date,
            'end_date'=>$end_date,
            'pageno'=>$pageno,
            'is_all'=>0
        );
        $data['sign'] = $this->getSign($data);
        $url = self::API_URL_PREFIX.self::HISTORY;
        $result = $this->subCurl($url, $data,0);
            
        $result=json_decode($result,true);  //将获得的json格式转换为数组
        //$place_data = $result['data']['place_data'];
        return $result;
    }

}