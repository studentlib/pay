<?php
  /**
 *功能：爱贝云计费接口公用函数
 *详细：该页面是请求、通知返回两个文件所调用的公用函数核心处理文件
 *版本：1.0
 *修改日期：2014-06-26
 '说明：
 '以下代码只是为了方便商户测试而提供的样例代码，商户可以根据自己的需要，按照技术文档编写,并非一定要使用该代码。
 '该代码仅供学习和研究爱贝云计费接口使用，只是提供一个参考。
 */

/**格式化公钥
 * $pubKey PKCS#1格式的公钥串
 * return pem格式公钥， 可以保存为.pem文件
 */
function formatPubKey($pubKey) {
    $fKey = "-----BEGIN PUBLIC KEY-----\n";
    $len = strlen($pubKey);
    for($i = 0; $i < $len; ) {
        $fKey = $fKey . substr($pubKey, $i, 64) . "\n";
        $i += 64;
    }
    $fKey .= "-----END PUBLIC KEY-----";
    return $fKey;
}


/**格式化公钥
 * $priKey PKCS#1格式的私钥串
 * return pem格式私钥， 可以保存为.pem文件
 */
function formatPriKey($priKey) {
    $fKey = "-----BEGIN RSA PRIVATE KEY-----\n";
    $len = strlen($priKey);
    for($i = 0; $i < $len; ) {
        $fKey = $fKey . substr($priKey, $i, 64) . "\n";
        $i += 64;
    }
    $fKey .= "-----END RSA PRIVATE KEY-----";
    return $fKey;
}

/**RSA签名
 * $data待签名数据
 * $priKey商户私钥
 * 签名用商户私钥
 * 使用MD5摘要算法
 * 最后的签名，需要用base64编码
 * return Sign签名
 */
function sign($data, $priKey) {
    //转换为openssl密钥
    $res = openssl_get_privatekey($priKey);

    //调用openssl内置签名方法，生成签名$sign
    openssl_sign($data, $sign, $res, OPENSSL_ALGO_MD5);

    //释放资源
    openssl_free_key($res);
    
    //base64编码
    $sign = base64_encode($sign);
    return $sign;
}

/**RSA验签
 * $data待签名数据
 * $sign需要验签的签名
 * $pubKey爱贝公钥
 * 验签用爱贝公钥，摘要算法为MD5
 * return 验签是否通过 bool值
 */
function verify($data, $sign, $pubKey)  {
    //转换为openssl格式密钥
    $res = openssl_get_publickey($pubKey);

    //调用openssl内置方法验签，返回bool值
    $result = (bool)openssl_verify($data, base64_decode($sign), $res, OPENSSL_ALGO_MD5);
     file_put_contents('tj.log', openssl_error_string()."\n",FILE_APPEND);
    //释放资源
    openssl_free_key($res);
    //返回资源是否成功
    return $result;
}

/**
 * curl方式发送post报文
 * $remoteServer 请求地址
 * $postData post报文内容
 * $userAgent用户属性
 * return 返回报文
 */
function request_by_curl($remoteServer, $postData, $userAgent) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $remoteServer);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    $data = curl_exec($ch);
    curl_close($ch);

    return $data;
}


/**
 * 组装request报文
 * $reqJson 需要组装的json报文
 * $vkey  cp私钥，格式化之前的私钥
 * return 返回组装后的报文
 */
function composeReq($reqJson, $vkey) {
    //获取待签名字符串
    $content = json_encode($reqJson);
    
    //格式化key，建议将格式化后的key保存，直接调用
    $vkey = formatPriKey($vkey);
    
    //生成签名
    $sign = sign($content, $vkey);
    
    //组装请求报文，目前签名方式只支持RSA这一种
    $reqData = "transdata=".urlencode($content)."&sign=".urlencode($sign)."&signtype=RSA";
 
    return $reqData;
}



/**
 * 解析response报文
 * $content  收到的response报文
 * $pkey     爱贝平台公钥，用于验签
 * $respJson 返回解析后的json报文
 * return    解析成功TRUE，失败FALSE
 */
function parseResp($content, $pkey, &$respJson) {
    $arr=array_map(create_function('$v', 'return explode("=", $v);'), explode('&', $content));
    foreach($arr as $value) {
        $resp[($value[0])] = urldecode($value[1]);
    }
    
    //解析transdata
    if(array_key_exists("transdata", $resp)) {
        $respJson = json_decode(stripslashes($resp["transdata"]),TRUE);
    } else {
        return FALSE;
    }
//     file_put_contents('tj.log', stripslashes($resp["transdata"])."\n",FILE_APPEND);
    //验证签名，失败应答报文没有sign，跳过验签
    if(array_key_exists("sign", $resp)) {
        //校验签名
        $pkey = formatPubKey($pkey); 
        return verify(stripslashes($resp["transdata"]), $resp["sign"], $pkey);
    } else if(!array_key_exists("errmsg", $respJson)) {
        return FALSE;
    }

    return TRUE;
}

/*
 * @description 签名验证
 * @param array $p_data
 * @param array $p_key
 * @param array $p_sign_type
 * @return Boolean TRUE|FALSE
 * */
function verifySignature($p_data, $p_key, $p_sign_type='MD5') {

    $result_sign   		= trim($p_data['Sign']);
    $delete_sign   		= paraFilter($p_data);
    $arg_sort      		= arg_sort($delete_sign);
    $return_string 		= createLinkString($arg_sort);
    $sign_return_string = buildMysign($return_string, $p_key, $p_sign_type);
    if ($result_sign === $sign_return_string) {
        return true;
    } else {
        return false;
    }
}

function paraFilter($p_data) {
    $para = array();
    while ((list ($key, $val) = each ($p_data))!=FALSE) {
        if($key == "Sign" || $key === '') continue;
        else $para[$key] = $p_data[$key];
    }
    return $para;
}

function arg_sort($array) {
    ksort($array);
    reset($array);
    return $array;
}

/*
 * @description 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
 * @param  array  $p_array
 * @return string $data
 *
 */
function createLinkString($p_array) {
    $arg  = "";
    while ((list ($key, $val) = each ($p_array))!=FALSE) {
        $arg.=$key."=".$val."&";
    }
    $data = substr($arg,0,count($arg)-2);		     //去掉最后一个&字符
    return $data;
}

function buildMysign($p_string, $p_key, $p_sign_type = 'MD5') {

    $data = $p_string . $p_key;

    if($p_sign_type == 'MD5') {
        $data = md5($data);
    } elseif($p_sign_type =='DSA') {
        //TODO 后续开发
    } else {
        //无效签名
        $data = false;
    }

    $data = urlencode($data);
    return $data;
}


?>