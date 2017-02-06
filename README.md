
滴滴打车SDK整合
-------------------
### 引入
    将类文件夹放在Library目录下，你也可以按需要放在其他地方。

### 参数
client_id，client_secret，sign_key，grant_type，test_call_phone


### 使用DEMO
##### 初始化代码
	public function _initialize(){
		//读取滴滴打车配置文件
		$this->config = C("DD_CONFIG");
		//实例化滴滴类
		import("Common.Vendor.DidiCar.DiDiCallCar",dirname(COMMON_PATH),".php");
		$this->Didi = new \DiDiCallCar($this->config);
		$this->starttime = date("Y-m-d H:i:s",time()+60*60*12);//预约时间
		$this->timestamp = time();
		$this->token = $this->Didi->curlToken($this->timestamp);//授权码
		$this->requestId = $this->Didi->getOrderId($this->token, $this->timestamp);//获取请求id
		//echo $this->token;exit();
	}