<?php
/**
 * PHP SDK for  OpenAPI
 *
 * @version 1.0
 * @author dev.91.com
 */


class Sdk{
	
	/*
	 * 这里的AppId和AppKey是我们自己做测试的
	 * 开发者可以自己根据自己在dev.91.com平台上创建的具体应用信息进行修改
	 */ 
	private $AppId  = '与客户端保持一致的APPID';
	private $AppKey = '与客户端保持一致的APPKEY';
	
	
	private $Url = "http://service.sj.91.com/usercenter/ap.aspx";

	function __construct(){
	}

	/**
	 * 执行查询支付购买结果的API调用，返回结果数组
	 *
	 * @param string $CooOrderSerial 商户订单号
	 * @return array 结果数组
	 */
	public function query_pay_result($CooOrderSerial){
		
		$Act = 1;
		//生成Sign
		$Sign = md5($this->AppId.$Act.$CooOrderSerial.$this->AppKey);
		//把需要传送的参数拼接成字符串
		$SourceStr = "AppId=".$this->AppId."&Act=".$Act."&CooOrderSerial=".$CooOrderSerial."&Sign=".$Sign;
		$Params = trim($SourceStr);

		// 发起请求
		$Res = $this->request($this->Url, $Params, 'get');

		if (false === $Res['result']){
			$ResultArray = array(
				'res' => $Res['errno'],
				'msg' => $Res['msg'],
			);
		}

		$ResultArray = json_decode($Res['msg'], true);

		// 远程返回的不是 json 格式, 说明返回包有问题
		if (is_null($ResultArray)){
			$ResultArray = array(
				'res' => false,
				'msg' => $Res['msg']
			);
		}
		return $ResultArray;
	}

	/**
	 * 检查用户登陆SessionId是否有效API调用，返回结果数组
	 *
	 * @param string $Uin 用户的91Uin
	 * @param string $SessionId 用户登陆SessionId
	 * @return array 结果数组
	 */
	public function check_user_login($Uin,$SessionId){

		$Act = 4;
		//生成Sign
		$Sign = md5($this->AppId.$Act.$Uin.$SessionId.$this->AppKey);
		//把需要传送的参数拼接成字符串
		$SourceStr = "AppId=".$this->AppId."&Act=".$Act."&Uin=".$Uin."&SessionId=".$SessionId."&Sign=".$Sign;
		$Params = trim($SourceStr);

		// 发起请求
		$Res = $this->request($this->Url, $Params, 'get');

		if (false === $Res['result']){
			$ResultArray = array(
				'res' => $Res['errno'],
				'msg' => $Res['msg'],
			);
		}

		$ResultArray = json_decode($Res['msg'], true);

		// 远程返回的不是 json 格式, 说明返回包有问题
		if (is_null($ResultArray)) {
			$ResultArray = array(
				'res' => false,
				'msg' => $Res['msg']
			);
		}
		return $ResultArray;
	}

	/**
	 * 执行一个 HTTP 请求
	 *
	 * @param string 	$Url 	执行请求的Url
	 * @param mixed	$Params 表单参数
	 * @param string	$Method 请求方法 post / get
	 * @return array 结果数组
	 */
	public function request($Url, $Params, $Method='post'){

		$Curl = curl_init();//初始化curl

		if ('get' == $Method){//以GET方式发送请求
			curl_setopt($Curl, CURLOPT_URL, "$Url?$Params");
		}else{//以POST方式发送请求
			curl_setopt($Curl, CURLOPT_URL, $Url);
			curl_setopt($Curl, CURLOPT_POST, 1);//post提交方式
			curl_setopt($Curl, CURLOPT_POSTFIELDS, $Params);//设置传送的参数
		}

		curl_setopt($Curl, CURLOPT_HEADER, false);//设置header
		curl_setopt($Curl, CURLOPT_RETURNTRANSFER, true);//要求结果为字符串且输出到屏幕上
		curl_setopt($Curl, CURLOPT_CONNECTTIMEOUT, 3);//设置等待时间

		$Res = curl_exec($Curl);//运行curl
		$Err = curl_error($Curl);
	  
		if (false === $Res || !empty($Err)){
			$Errno = curl_errno($Curl);
			$Info = curl_getinfo($Curl);
			curl_close($Curl);

			return array(
	        	'result' => false,
	        	'errno' => $Errno,
	            'msg' => $Err,
	        	'info' => $Info,
			);
		}
		curl_close($Curl);//关闭curl
		return array(
        	'result' => true,
            'msg' => $Res,
		);
		 
	}
}

?>
