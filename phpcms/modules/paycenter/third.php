<?php
defined('IN_PHPCMS') or exit('No permission resources.');
pc_base::load_app_class('pay', 'paycenter', 0);
pc_base::load_app_class('AopClient','paycenter',0);
pc_base::load_app_class('AlipayTradeAppPayRequest','paycenter',0);
pc_base::load_app_class('wechatAppPay','paycenter',0);

class third extends payroid
{

    /**
     * @var array
     */
    private $_new_order_params;
    /**
     * @var array
     */
    private $_verify_order_params;
    /**
     * @var
     */
    private $_rxsg_order_params;

    public function __construct()
    {
        $this->_orders = pc_base::load_model('orders_model');
        $this->_orders_list = pc_base::load_model('orders_list_model');
        $this->_account = pc_base::load_model('account_model');
        $this->_log = pc_base::load_model('log_model');
        $this->_tickets = pc_base::load_model('tickets_model');
        $this->_new_order_params = array('account', 'serverid', 'itemid', 'channel');
        $this->_verify_order_params = array('orderid');
        $this->_rxsg_order_params=array('orderno','fee','sign','app_id','attach','child_para_id','wxno');
    }

    public function createOrder()
    {
        $data = $_REQUEST;
        file_put_contents('log.log', date('Y-m-d H:i:s') . 'createOrder' . json_encode($data) . PHP_EOL, FILE_APPEND);;
        $ret = $this->_check_params($data, $this->_new_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            exit(json_encode($ret));
        }
        $ret = array('ret' => 0);
        $servers = $this->get_server_config();
        if (!isset($servers[$data['serverid']])) {
            $ret = array('ret' => 1, 'msg' => '游戏服不存在');
        }
        if (!$this->_account->changeConnection($data['serverid'])) {
            $ret = array('ret' => 2, 'msg' => '数据库错误');
        }
        $account = $this->_account->select(array('AccountName' => strtoupper($data['account'])));
        if (count($account) > 1) {
            foreach ($account as $k => $user) {
                if (isset($user['ServerType']) && $user['ServerType'] == $data['serverid']) {
                    $account = $user;
                    break;
                }
            }
        } else if (isset($account[0]) && count($account[0])) {
            $account = $account[0];
        }
        if (!$account) {
            $ret = array('ret' => 3, 'msg' => '玩家不存在');
        }

        $items = $this->_getPayConfig($data['channel']);
        if ($ret['ret'] == 0 && !isset($items[$data['itemid']])) {
            $ret = array('ret' => 4, 'msg' => '购买物品不存在');
        }
        if ($ret['ret'] == 0) {
            $array = array('orderid' => $this->createOrderid(), 'channel' => $data['channel'], 'account' => $data['account'], 'itemid' => $data['itemid'], 'serverid' => $data['serverid'], 'uid' => $account['ID'], 'amount' => $items[$data['itemid']]['Paynull']);
            $this->_orders_list->insert($array);
            $ret['orderid'] = $array['orderid'];
            $ret['amount'] = $array['amount'];
            /**
             * 贝付宝下单

            $ret['pay_url']['Ali']=$this->_place_an_order($array['orderid'],$data,'Ali');
            $ret['pay_url']['WeChat']=$this->_place_an_order($array['orderid'],$data,'WeChat');
            $ret['pay_url']['Bank']=$this->_place_an_order($array['orderid'],$data,'Bank');
            */
            /**
             * 支付宝 微信下单

            $ret['alipay']=$this->alipay($items[$data['itemid']]['Des'],$items[$data['itemid']]['Gain'],$array['orderid'],$array['amount']);
            $ret['wxpay']=$this->wxpay($items[$data['itemid']]['Gain'],$array['orderid'],$array['amount']);
            */
        }
        file_put_contents('log.log', date('Y-m-d H:i:s') . 'createOrder' . json_encode($ret, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
        echo json_encode($ret);
    }

    public function verifyOrder()
    {
        $data = $_REQUEST;
        $ret = $this->_check_params($data, $this->_verify_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            exit(json_encode($ret));
        }
        $ret = array('ret' => 0);
        $orders = array();
        $json = json_decode(stripslashes($data['orderid']), TRUE);
        file_put_contents('appstore.log', date('Y-m-d H:i:s') . 'verifyOrder :22 --' . json_encode($json, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
        if (is_array($json)) {
            $this->check_appstore_orders($json);
            foreach ($json as $k => $v) {
                $myorder = $this->_orders_list->get_one("`orderid`='" . $k . "'");
                if (!$myorder || (isset($myorder['ptime']) && $myorder['ptime'] != NULL)) {
                    $orders[$k] = 0;
                } else {
                    $orders[$k] = 1;
                }
            }
            $ret['orders'] = $orders;
        } else {
            $ret['ret'] = 1;
            $ret['msg'] = 'orders_wrong_format';
        }
        echo json_encode($ret);
    }

    public function test(){
        $orderid=$this->createOrderid();
        $platform='WeChat';
        $array['itemid']='100002';
        $array['account']='123456987';
        $array['channel']='rxsgios';
        $test=$this->_place_an_order($orderid,$array,$platform);
        echo $test;
    }

    /**
     * 贝付宝下单接口
     * @param $orderid
     * @param array $array
     * @param $platform
     * @return array|mixed
     */
    public function _place_an_order($orderid, $array=array(),$platform)
    {
        //获取平台配置
        $config = pc_base::load_config('android', 'qbrxsg');
        //获取商品信息
        $items = $this->_getPayConfig($array['channel']);
        $data = array(
            'body' => $items[$array['itemid']]['NameID'],                                //用户支付时显示的购买产品名称
            'total_fee'=>$items[$array['itemid']]['Paynull'] * $config['Ratio'],      //用 户 支 付 的 金 额 为 分（total_fee=1 代表 1 分）
            'para_id'=>$config['AppId'],                                   //平台分配的商户
            'app_id'=>$config['PayId'],                                    //平台分配的产品编号
            'order_no'=>$orderid,                                          //   不超过 26 位且需要保证唯
            'notify_url'=>$config['notify_url'],                           //通知商户最终支付状态接口
            'returnurl'=>'',                                               //客户端同步转跳的地址
            'attach'=>$items[$array['itemid']]['ItemID'],                           //  不超过 32 位且不能为中文（我传的是：ItemID）
            'device_id'=>'2',                                              //   应用类型：1 是安卓 2 是 ios
            //'mch_crarte_ip'=>$_SERVER['REMOTE_ADDR'];                      //必须是真实的客户端 Ip，否则无法调起支付页面；
            'mch_app_name'=>$config['AppName'],                            //产品的官网地址，必须保证公网能访问
            'userldetity'=>$array['account'],                                       // 用户唯一标识
            'child_para_id'=>'1',                                          //子渠道号，无此需求固定传 1，用户渠道分包
        );
        $data['sign'] = strtolower(md5($data['para_id'] . $data['app_id'] . $data['order_no'] . $data['total_fee'] . $config['PayKey']));
        switch ($platform)
        {
            case 'Ali'://支付宝
                $url='http://pay.payfubao.com/sdk_transform/wap_api';
                break;
            case 'WeChat'://微信
                $data['mch_crarte_ip']=$_SERVER['REMOTE_ADDR'];
                $url='http://pay.payfubao.com/sdk_transform/wx_wap_sdk';
                break;
            case 'Bank'://银行
                $url='http://pay.payfubao.com/Eco_pay/shortpay';
                break;
        }
        $ret = $this->request_post($url,true,$data);
        return $ret;
    }

    /**
     * Q版热血三国（贝付宝）
     */
    public function iosrxsg(){
        $data = $_REQUEST;
        file_put_contents('appstore.log', date('Y-m-d H:i:s') . 'checkOrder' . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
        $ret = $this->_check_params($data, $this->_rxsg_order_params);
        $data['orderid']=$data['orderno'];
        if (isset($ret['msg']) && $ret['msg']) {
            /**
             * 参数非法
             */
            $this->_logOrder($data, 7, 'rxsg');
            exit('fail');
        }
        $config = pc_base::load_config('android', 'qbrxsg');
        //$mysign = strtolower(md5($config['AppId'] . $data['PayId'] . $data['order_no'] . $config['PayKey']));
        //$status=$this->check_qbrxsg($data);
        $mysign = strtolower(md5($data['orderno'] . $data['fee'] . $config['PayKey']));
        if ($data['sign'] == $mysign){
            $ret = $this->_push_rxsg_order($data, 'rxsg');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                exit('ok');
            } else {
                exit('fail');
            }
        }else {
            /**
             * 验证签名失败
             */
            $this->_logOrder($data, 6, 'rxsg');
            exit('fail');
        }
    }

    /**
     * 支付宝订单创建
     * @param $body   名称
     * @param $subject 获得元宝数
     * @param $out_trade_no  订单号
     * @param $amount 金额
     * @return string
     */

    public function alipay($body,$subject,$out_trade_no,$amount)
    {
        $aop = new AopClient();
        $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
        $aop->appId = "2017050307097180";
        $aop->rsaPrivateKey = 'MIIEowIBAAKCAQEAziXvk3ow3hc6rcxdFqfxwL554pLw4Mya28iURpCL6QiA5AZYJKbCVmpkmDqL2uX9h1SQWHCyA4v2Hj9pDc3QRZ+Lkq0kdbubPNDDxkHA0cJE3BsGVAfi/pphr3mVducP2VdOAEKxZC7TKzS/BjAsQdL8mE4xpRfwhjpVJPf4k0XsfWtR8K6EFo0f3NmZG
bt22ccisJMEGlK+h/NF+hBKwg+eNEP+H5L8Y7iDhiF1dMuaPnrDCClRQwIs2cSrhe5J8m+l0EsZsrZWtzzsNL3RHGM1v66CAXOaucM1vLdwUA4pAuEH99MWmruwDVD7lErwp9EJsJBdL9nt/aTiUNmQ4QIDAQABAoIBADxg2hGs2UFDNnmPALjRCbq1T0ewWALPio/S6LLeRUxEpFOlFA3wFb1vvfTkOPAtKpuHhhuRL
mJhjP2A/wj+/gBWYW/dbG9bOnWOg7i+q3YEW1zIQYs9IBwIJZJanw9LT9YNfxgOcJxyJoFiGoz6cQv6euc1B89d+qGl1ihUxKaFbrsX2aqUpGWc6RLY3fttgeDYnlCIaoBMBYj2X4p1ROxb162hLlFom+0XYzNXJj49SXlh47Z1TEnCE0nDIv4kpfQGo9GUPwDBVIoiURuawo683u4v1dB2DlysAcPgO9TdkcVcdM8db
UgjqSE/Pt8WaeDEzpWgLYQAMhPdEUSJNh0CgYEA7pecC6gLKKIsRSgDuxDK7jpsr5yW52g75rW64RfWLehz8F6LikO6GkhGnTjIzTT+5tljiljFLJP7bjF1/btYXId5dWTX/cLroWEMMCjkLQFHW5JJRhai+52ybJsR2/eGBPb0ku/C+/at9n1dC8gTZ2Xg797629KeBV8jDDwRDysCgYEA3TBXYT65iWIW5+m+SkXDI
ZBzES8TUVOXSsV1S+tg6u9xj5gQ+6VyhM6RQCR6SO0GOeoRMSHBEHALi56h6spIoEFiCFpgruiE0c34W01ZVruBPjqyA5eKqLPR9qbJxCFaIjhphwdQGiPLWA7jOV9YQ+CHshRI1+5XooUm53beeiMCgYB3FUiXMMpQ/5Bk+/HQvjLq8FIq3FcdLJMpNW8OxdzqkOi9AhNXcTJDx+smsZ7XfSn0gnACLwhKlZmaiClB+
O3DUQK4Kxr61vzQUSXKK76nD8pT1BGqX8X9G9pgBo3FYieL9s5fQDfrllWBf1Sfer7kjp7UWlCOwvKH79IIWzqQfQKBgQCBqV7vwKde48Ntu9YzD+YtfNIXVrNKT2g3Cr2R/a9YyXiWppv+CKSzOaxoH3oX2ep2dMITPShCDIyYEiv0yRP97ZYcM27N2bxSiR132EXw1AYVhq7n8CP2dUM2BdvtP2fo+4zQ/PZ1/Fmti
zykW0IQuESTndR90FhLCycdNM+CZQKBgANbGQ+5BW88N4amAIbrE7T5XLlhZJYk7C+sWzkJ8BWEl8RSew5espRsnqLfSqZdVFXNFU7tt2XukFLbrWNFs2pW8SYWW3+8BVz9qPkoMuNRlcc0PVNvSStuDplNPmShKreYdd4ocWn/upb3TSiFiar0v5KyNl6UBQT5LuR9fiMS';
        $aop->alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA9CNEPVTYM2tWhzHOYk26ht+tepU+UeFPEg/3Ze06nt5YxbrfqMV41g889pxPWpdtlvDXna252xP7gAqnMn4BRW74mGreqHEZ8U9Y2nAZJqvwoa6DgzhNjL8k07tR4DFnhcpyDgKZpHxr/LjhLsw+Soze+MTT
sj1ZX36KdInnDS9wOQZPyQNQgzqrElznEhlGWSwPw2TDUBIa2rt9Jvc5Qc4xeCOFSQxaxSa6F48qZkhiLaQUa7pzi7JHGSMBOSheeRJdOgNj84rheC5do5lki+2h7yhE5U0qijojlBGDccTVZqQa8cvS7YhhBz8o7f2qYfbRJbnCNDsB2YKPWyKtdwIDAQAB';
        $aop->format = "json";
        $aop->charset = "UTF-8";
        $aop->signType = "RSA2";
        //实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.app.pay
        $request = new AlipayTradeAppPayRequest();
        //SDK已经封装掉了公共参数，这里只需要传入业务参数i
        $bizcontent = "{\"body\":\"".$body."\","
            . "\"subject\": \"我的萌将时代:".$subject."元宝\","
            . "\"out_trade_no\": \"".$out_trade_no."\","
            . "\"timeout_express\": \"60m\","
            . "\"total_amount\": \"".number_format($amount,2,'.','')."\","
            . "\"product_code\":\"QUICK_MSECURITY_PAY\""
            . "}";
        $request->setNotifyUrl("http://and.pay.gatcn.sg.dianjianggame.com/gat_alipay.php");//回调地址
        $request->setBizContent($bizcontent);
        //这里和普通的接口调用不同，使用的是sdkExecute
        $response = $aop->sdkExecute($request);
        //$test=$aop->execute($request);var_dump($test);
        return $response;
    }

    /**
     * @param $body  获得元宝数
     * @param $out_trade_no 自定义的订单号
     * @param $amount  订单金额（只能为整数 单位为分）
     * @return string
     */
    public function wxpay($body,$out_trade_no,$amount)
    {
        $appid='wxef2a725b8eed1e3a';
        $mch_id='1468437502';
        $notify_url='http://and.pay.gatcn.sg.dianjianggame.com/gat_wxpay.php'; //回调地址
        $key='MKJhgYUIOCF6546789fgNJUloIU897gb';
        //1.统一下单方法
        $wechatAppPay = new wechatAppPay($appid, $mch_id, $notify_url, $key);
        $params['body'] = $body.'元宝';                       //商品描述
        $params['out_trade_no'] = $out_trade_no;    //自定义的订单号
        $params['total_fee'] = $amount*100;                       //订单金额 只能为整数 单位为分
        $params['trade_type'] = 'APP';                      //交易类型 JSAPI | NATIVE | APP | WAP
//      file_put_contents('gat.log',"wxpay 0:".json_encode($params,JSON_UNESCAPED_UNICODE).PHP_EOL,FILE_APPEND);
        $result = $wechatAppPay->unifiedOrder( $params );
//      file_put_contents('gat.log',"wxpay:".json_encode($result,JSON_UNESCAPED_UNICODE).PHP_EOL,FILE_APPEND);
        //print_r($result); // result中就是返回的各种信息信息，成功的情况下也包含很重要的prepay_id
        //2.创建APP端预支付参数
        /** @var TYPE_NAME $result */
        $data = @$wechatAppPay->getAppPayParams( $result['prepay_id'] );
        return json_encode($data);
    }

    /**
     * 支付宝订单处理
     */
    public function gatcnand_ali(){
        $data=$_POST;
        $data['fund_bill_list']=stripslashes($data['fund_bill_list']);
        file_put_contents('gat.log',date('Y-m-d H:i:s')."gatcnand1:".json_encode($data,JSON_UNESCAPED_UNICODE).PHP_EOL,FILE_APPEND);
        $aop = new AopClient;
        $aop->alipayrsaPublicKey ='MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA9CNEPVTYM2tWhzHOYk26ht+tepU+UeFPEg/3Ze06nt5YxbrfqMV41g889pxPWpdtlvDXna252xP7gAqnMn4BRW74mGreqHEZ8U9Y2nAZJqvwoa6DgzhNjL8k07tR4DFnhcpyDgKZpHxr/LjhLsw+Soze+MTTs
j1ZX36KdInnDS9wOQZPyQNQgzqrElznEhlGWSwPw2TDUBIa2rt9Jvc5Qc4xeCOFSQxaxSa6F48qZkhiLaQUa7pzi7JHGSMBOSheeRJdOgNj84rheC5do5lki+2h7yhE5U0qijojlBGDccTVZqQa8cvS7YhhBz8o7f2qYfbRJbnCNDsB2YKPWyKtdwIDAQAB';
        $flag = $aop->rsaCheckV1($data, $aop->alipayrsaPublicKey,"RSA2");
//      file_put_contents('gat.log',"gatcnand2:-----".$flag.PHP_EOL,FILE_APPEND);
        if($data['trade_status']=='TRADE_SUCCESS'||$data['trade_status']=='TRADE_FINISHED'){
            $trade_status=true;
        }
        if($flag==1&&$trade_status&&$data['app_id']=='2017050307097180'&&$data['seller_id']=='2088621968375694')
        {
            $ret=$this->_push_gatcnAli_order($data,'gatcn');
            if(isset($ret['ret'])&&($ret['ret']==0||$ret['ret']==4))
            {
                exit('success');
            }else{
                exit('failed');
            }
        }else{
            $data['orderid']=$data['out_trade_no'];
            $this->_logOrder($data,6,'gatcn');
            exit('failed');
        }

    }

    /**
     * 微信订单处理
     * @return mixed
     */
    public function gatcnand_wx(){
        $datas=$GLOBALS["HTTP_RAW_POST_DATA"];
        $data=$this->xml_array($datas);
        $sign=$this->sign($data);
        if($sign)
        {
            $ret=$this->_push_gatcnWx_order($data,'gatcn');
            if(isset($ret['ret'])&&($ret['ret']==0||$ret['ret']==4))
            {
                $xml['return_code']='SUCCESS';
                $xml['return_msg']='OK';
            }else{
                $xml['return_code']='FAIL';
                $xml['return_msg']=$ret['msg'];
            }
        }else{
            $data['orderid']=$data['out_trade_no'];
            $this->_logOrder($data,6,'gatcn');
            $xml['return_code']='FAIL';
            $xml['return_msg']='sign wrong';
        }
        $arr=$this->array_xml($xml);
        return $arr;
    }

    public function xml_array($xml){
        if(!$xml){
            return false;
        }
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $data;
    }

    public function array_xml($params){
        if(!is_array($params)|| count($params) <= 0)
        {
            return false;
        }
        $xml = "<xml>";
        foreach ($params as $key=>$val)
        {
            if (is_numeric($val)){
                $xml.="<".$key.">".$val."</".$key.">";
            }else{
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml.="</xml>";
        return $xml;
    }

    public function sign($data){
        $key='MKJhgYUIOCF6546789fgNJUloIU897gb';
        $sign=$data['sign'];
        ksort($data);
        foreach($data as $k=>$v){
            if($k=='sign' || empty($v)){
                unset($data[$k]);
                continue;
            }
            $stringSign.='&'.$k.'='.$v;
        }
        $stringSign=substr($stringSign,1);
        $stringSign.='&key='.$key;
        return $sign==strtoupper(MD5($stringSign));
    }
    /**
     * @param $params
     * @param $keys
     * @return array
     */
    public function _check_params($params, $keys)
    {
        foreach ($keys as $k => $v) {
            if (!isset($params[$v])) {
                return array('code' => 1, 'msg' => 'lost_param_' . $v);
            }
        }
        return array();
    }

    /*
     * 创建订单号
     */
    public function createOrderid()
    {
        list($usec, $sec) = explode(" ", microtime());
        $time = (float)date('YmdHis', $sec) . (float)$usec * 1000000;
        return rand(10, 99) . $time . rand(10, 99);
    }

}
