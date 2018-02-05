<?php
//快用
class Init
{
    //测试用公钥，请替换为对应游戏的公钥，从快用平台上获取的是无格式的公钥，需要转换  
    const public_key = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDynTotu22cXXL82NKPzMLhLhxqSsJ4Jw9Hu3a4WkZiGRZ/QTAcmK09P3vgrm66dH04juK563WjAm2nZBwnSTfi7HfSe0j5rLo4WTkKNLaEfbc7nuvnAkX2eYS6ROaGel1gyEu1IGFISQsQIzW9nhsR5BURZtTsD8YC2UNlFwBNLQIDAQAB';
    public function verify ($post_sign, $post_notify_data,$post_orderid, $post_dealseq, $post_uid, $post_subject, $post_v)
    {
        $publicKey = self::public_key; 
        $post_sign = base64_decode($post_sign);
        //对输入参数根据参数名排序，并拼接为key=value&key=value格式；
        $parametersArray = array();
        $parametersArray['notify_data'] = $post_notify_data;
        $parametersArray['orderid'] = $post_orderid;
        $parametersArray['dealseq'] = $post_dealseq;
        $parametersArray['uid'] = $post_uid;
        $parametersArray['subject'] = $post_subject;
        $parametersArray['v'] = $post_v;
        ksort($parametersArray);
        $sourcestr = "";
        foreach ($parametersArray as $key => $val) {
            $sourcestr == "" ? $sourcestr = $key .
             "=" . $val : $sourcestr .= "&" .
             $key . "=" . $val;
        }
        //对数据进行验签，注意对公钥做格式转换
        $publicKey = Rsa::instance()->convert_publicKey($publicKey);
        $verify = Rsa::instance()->verify($sourcestr, $post_sign, $publicKey);
        //对加密的notify_data进行解密
        $post_notify_data_decode = base64_decode($post_notify_data);
        $decode_notify_data = Rsa::instance()->publickey_decodeing($post_notify_data_decode, $publicKey);
        parse_str($decode_notify_data);
        //比较解密出的数据中的dealseq和参数中的dealseq是否一致
        if ($dealseq == $post_dealseq) {
            return TRUE;
        } else {
            return FALSE;
        }
    }
}
?>