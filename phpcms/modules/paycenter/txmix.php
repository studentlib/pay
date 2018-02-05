<?php
defined('IN_PHPCMS') or exit('No permission resources.');
pc_base::load_app_class('payroid', 'paycenter', 0);
pc_base::load_app_class('Notify_bcyx', 'paycenter', 0);

class txmix extends paytxyx
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
     * 安峰
     * @var array
     */
    private $_afnew_order_params;
    /**
     * 360
     * @var array
     */
    private $_qihoo_order_params;
    /**
     * 爱奇艺
     * @var array
     */
    private $_iqy_order_params;
    /**
     * 百度
     * @var array
     */
    private $_baidu_order_params;
    /**
     * 金立
     * @var array
     */
    private $_jl_order_params;
    /**
     * 联想
     * @var array
     */
    private $_lx_order_params;
    /**
     * oppo
     * @var array
     */
    private $_oppo_order_params;
    /**
     * uc
     * @var array
     */
    private $_uc_order_params;
    /**
     * 华为
     * @var array
     */
    private $_hw_order_params;
    /**
     * vivo
     * @var array
     */
    private $_vivo_order_params;
    /**
     * 小米
     * @var array
     */
    private $_xm_order_params;
    /**
     * 应用汇
     * @var array
     */
    private $_yyh_order_params;
    /**
     * 应用宝
     * @var array
     */
    private $_tx_order_params;

    public function __construct()
    {
        $this->_orders = pc_base::load_model('orders_model');
        $this->_orders_list = pc_base::load_model('orders_list_model');
        $this->_account = pc_base::load_model('account_model');
        $this->_log = pc_base::load_model('log_model');
        //应用宝函数
        $this->_tx = pc_base::load_model('tx_model');
        $this->_tx_amount = pc_base::load_model('tx_amount_model');
        $this->_new_order_params = array('account', 'serverid', 'itemid', 'channel');
        $this->_verify_order_params = array('orderid');

        //安峰参数
        $this->_afnew_order_params = array('open_id', 'body', 'fee', 'subject', 'vid', 'sn', 'vorder_id', 'create_time', 'sign');
        //360参数
        $this->_qihoo_order_params = array('app_key', 'product_id', 'amount', 'app_uid', 'app_ext1', 'app_ext2', 'user_id', 'order_id', 'gateway_flag', 'sign_type', 'app_order_id', 'sign_return', 'sign');
        /*爱奇艺参数*/
        $this->_iqy_order_params = array('user_id', 'role_id', 'order_id', 'money', 'time', 'userData', 'sign');
        //百度参数
        $this->_baidu_order_params = array('AppID', 'OrderSerial', 'CooperatorOrderSerial', 'Sign', 'Content');
        //金立参数
        $this->_jl_order_params = array('api_key', 'close_time', 'create_time', 'deal_price', 'out_order_no', 'pay_channel', 'submit_time', 'user_id', 'sign');
        //联想参数
        $this->_lx_order_params = array('exorderno', 'transid', 'appid', 'waresid', 'feetype', 'money', 'result', 'count', 'transtype', 'transtime', 'cpprivate', 'paytype');
        //欧普参数
        $this->_oppo_order_params = array('notifyId', 'partnerOrder', 'productName', 'productDesc', 'price', 'count', 'attach', 'sign');
        //UC参数
        $this->_uc_order_params=array('ver','data','sign');
        //华为参数
        $this->_hw_order_params=array('result','userName','productName','payType','amount','orderId','notifyTime','requestId','sign');
        //vivo参数
        $this->_vivo_order_params=array('respCode','respMsg','signMethod','signature','tradeType','tradeStatus','cpId','appId','uid','cpOrderNumber','orderNumber','orderAmount','extInfo','payTime');
        //小米参数
        $this->_xm_order_params=array('appId','cpOrderId','cpUserInfo','uid','orderId','orderStatus','payFee','productCode','productName','productCount','payTime','signature');
        //应用汇手参数
        $this->_yyh_order_params=array('transdata','sign','signtype');
        //应用宝参数
        $this->_tx_order_params=array('openid','appid','ts','payitem','token','billno','version','zoneid','providetype','amt','payamt_coins','pubacct_payamt_coins','sig','appmeta');
        //应用宝请求验证订单接口
        $this->_app_tx_order_params = array('billno','account');

    }

    public function createOrder()
    {
        $data = $_REQUEST;
        file_put_contents('log.log', date('Y-m-d H:i:s', time()).':' . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
        $ret = $this->_check_params($data, $this->_new_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            file_put_contents('uuc.log', print_r($data, 1) . print_r($ret, 1), FILE_APPEND);
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

        $items = $this->_getAndRoidPayConfig($data['channel']);
        if ($ret['ret'] == 0 && !isset($items[$data['itemid']])) {
            $ret = array('ret' => 4, 'msg' => '购买物品不存在');
        }
        if ($ret['ret'] == 0) {
            /**
             * old创建订单号方法（ sha1(uniqid(rand(1, 10000), TRUE) ） sha1 rand 避免重复
             * 有的渠道订单号有长度限制
             * $this->createOrderid() //随机数两位数+时间（精确到微秒）+随机数两位数  (长度：24)
             */
            $array = array('orderid' =>$this->createOrderid(), 'channel' => $data['channel'], 'account' => $data['account'], 'itemid' => $data['itemid'], 'serverid' => $data['serverid'], 'uid' => $account['ID'], 'amount' => $items[$data['itemid']]['Paynull']);

            $this->_orders_list->insert($array);
            $ret['orderid'] = $array['orderid'];
            $ret['amount'] = $array['amount'];
        }
        /*去金立下订单
        $JlOrder = $this->createJlOrder($array['orderid'], $items[$data['itemid']], $data['account']);
        if ($JlOrder['ret'] == 10) {
            $ret['ret'] = 10;
            $ret['msg'] = '订单创建失败';
        } else {
            $ret['orderid'] = $JlOrder['msg']['out_order_no'];
            $ret['amount'] = $items[$data['itemid']]['Paynull'];
            $ret['createtime'] = $JlOrder['msg']['submit_time'];
        }
        */
        file_put_contents('log.log', date('Y-m-d H:i:s', time()) .':'. json_encode($ret, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
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
        file_put_contents('appstore', print_r($ret, 1), FILE_APPEND);;
        echo json_encode($ret);
    }

    /*安峰接口*/
    public function afandNew($data)
    {
        $data = $_POST;
        file_put_contents('checkOrder.log', date('Y-m-d H:i:s') . 'txmixaf2订单信息oridata:' . print_r($data, 1) . PHP_EOL, FILE_APPEND);
        $ret = $this->_check_params($data, $this->_afnew_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['order_id'] = $data['vorder_id'];
            $this->_logOrder($data, 7, 'afand');
            $ret['errcode'] = 7;
            echo json_encode($ret);
        }
        $status = json_decode($this->_check_afnew_order($data, 'afand'), true);
        if ($status['code'] == 0) {
            $ret = $this->_push_afnew_order($data, 'afand');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                echo 'SUCCESS';
            } else {
                echo json_encode(array('errcode' => $ret['ret'], 'msg' => $ret['msg']));//,JSON_UNESCAPED_UNICODE));
            }
        } else if ($status['code'] == 100) {
            echo json_encode(array('errcode' => $ret['ret'], 'msg' => $ret['msg']));
        } else {
            $data['order_id'] = $data['vorder_id'];
            $this->_logOrder($data, 6, 'afand');
            echo json_encode($status);
        }
    }

    //360渠道接口
    public function txmix360()
    {
        $data = $_REQUEST;
        file_put_contents('fzs.log', 'checkOrder $data=' . print_r(date('Y-m-d H:i:s', time()), 1) . print_r($data, 1) . PHP_EOL, FILE_APPEND);
        $ret = $this->_check_params($data, $this->_qihoo_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $this->_logOrder($data, 7, '360');
            exit('failed');
        }
        $qihoo = pc_base::load_config('android', '360');
        $sign = $data['sign'];
        $mysign = $this->_gen_qihoo_sign($data, $qihoo['PayKey'], $this->_qihoo_order_params);
        file_put_contents('fzs.log', 'checkOrder $data=' . print_r(date('Y-m-d H:i:s', time()), 1) . print_r($mysign, 1) . PHP_EOL, FILE_APPEND);
        if ($sign != $mysign) {
            $this->_logOrder($data, 6, '360');
            exit('failed');
        }
        if (isset($data['gateway_flag']) && $data['gateway_flag'] != 'success') {
            $this->_logOrder($data, 10, '360');
            exit('ok');
        }
        $ret = $this->_push_qihoo_order($data, '360');
        if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
            exit('ok');
        } else {
            exit('failed');
        }
    }

    //爱奇艺
    public function txmixaqy()
    {
        $data = $_REQUEST;
        file_put_contents('txmix.log', date('Y-m-d H:i:s') . '订单信息:' . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
        $ret = $this->_check_params($data, $this->_iqy_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $order['orderid'] = $order['userData'];
            $code = array('result' => '-6', 'message' => $ret['msg']);
            $this->_logOrder($data, 7, 'andiqy');
            exit(json_encode($code));
        }
        $key = 'b01dbd55d7e0e10f7528fb271858d7f2';
        $sign = MD5($data['user_id'] . $data['role_id'] . $data['order_id'] . $data['money'] . $data['time'] . $key);
        if ($data['sign'] == $sign) {
            $ret = $this->_push_iqy_order($data, 'andiqy');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                $code = array('result' => 0, 'message' => 'success');
                exit(json_encode($code));
            } else {
                $order['orderid'] = $order['userData'];
                $code = array('result' => '-6', 'message' => $ret['msg']);
                exit(json_encode($code));//,JSON_UNESCAPED_UNICODE));
            }
        } else {
            $order['orderid'] = $order['userData'];
            $code = array('result' => '-1', 'message' => 'Sign error');
            $this->_logOrder($data, 6, 'andiqy');
            exit(json_encode($code));
        }

    }

    //百度
    public function txmixbd()
    {
        $data = $_POST;
        pc_base::load_app_class('BaiduSdk', 'paycenter', 0);
        file_put_contents('fzs.log', 'check order:' . print_r(date('Y-m-d H:i:s', time()), 1) . '$data=' . print_r($data, 1) . PHP_EOL, FILE_APPEND);
        $channels = pc_base::load_config('android', 'baidu');
        $ret = $this->_check_params($data, $this->_baidu_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['order_id'] = $data['OrderSerial'];
            $this->_logOrder($data, 7, 'baidu');
            $Result["AppID"] = $channels['baidu']['AppId'];
            $Result["ResultCode"] = 1000;
            $Result["ResultMsg"] = urlencode("接收参数失败");
            $Result["Sign"] = md5($appid . $Result["ResultCode"] . $payKey);
            $Result["Content"] = "";
            $Res = json_encode($Result);
            exit(urldecode($Res));
        }
        extract($data);
        $sdk = new Sdk();
        $Res = $sdk->query_order_result($CooperatorOrderSerial);

        if ($Res['ResultCode'] == "1" && $Res['Sign'] == $sdk->SignMd5($Res['ResultCode'], urldecode($Res['Content']))) {
            //Content参数需要urldecode后再进行base64解码
            $result = base64_decode(urldecode($Res['Content']));
            //json解析
            $Item = extract(json_decode($result, true));
            //根据获取的信息，执行业务处理
            file_put_contents('fzs.log', 'check order:' . print_r(date('Y-m-d H:i:s', time()), 1) . '$result=' . print_r($OrderMoney, 1) . PHP_EOL, FILE_APPEND);
            $ret = $this->_push_baidu_order($OrderSerial, $CooperatorOrderSerial, $OrderMoney, 'baidu', $Res['Sign'], $result);
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                //返回成功结果
                $Result["AppID"] = $appid;
                $Result["ResultCode"] = 1;
                $Result["ResultMsg"] = urlencode("成功");
                $Result["Sign"] = md5($appid . $Result["ResultCode"] . $payKey);
                $Result["Content"] = "";
                $Res = json_encode($Result);
                exit(urldecode($Res));
            } else {
                //返回失败结果
                $Result["AppID"] = $appid;
                $Result["ResultCode"] = $ret['ret'];
                $Result["ResultMsg"] = urlencode($ret['msg']);
                $Result["Sign"] = md5($appid . $Result["ResultCode"] . $payKey);
                $Result["Content"] = "";
                $Res = json_encode($Result);
                exit(urldecode($Res));
            }
        }
    }

    //金立
    public function txmixjl()
    {
        $data = $_POST;
        file_put_contents('fzs.log', 'check order :' . print_r(date('Y-m-d H:i:s', time()), 1) . '$data=' . print_r($data, 1) . PHP_EOL, FILE_APPEND);
        $ret = $this->_check_params($data, $this->_jl_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['order_id'] = $data['out_order_no'];
            $this->_logOrder($data, 7, 'jl');
            exit('failed');
        }
        $jl = pc_base::load_config('android', 'jl');
        $rsa_verify = $this->jl_rsa_verify($data, $jl);
        if ($rsa_verify) {
            $ret = $this->_push_jl_order($data, 'jl');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                $result = "success";//支付成功
            } else {
                $result = "failure";//支付失败
            }
        } else {
            $data['order_id'] = $data['out_order_no'];
            $this->_logOrder($data, 6, 'jl');
            $result = "failure";
        }
        echo $result;
    }

    //联想
    public function txmixlx()
    {
        $data = $_REQUEST;
        file_put_contents('fzs.log', 'chexkOrder $data=' . print_r(date('Y-m-d H:i:s', time()), 1) . print_r($data, 1) . PHP_EOL, FILE_APPEND);
        $transdata = stripslashes($data['transdata']);
        $order = json_decode($transdata, true);
        $ret = $this->_check_params($order, $this->_lx_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['order_id'] = $order['exorderno'];
            $this->_logOrder($data, 7, 'lx');
            $ret['errcode'] = 7;
            exit('FAILURE');
        }
        $config = pc_base::load_config('android', 'lx');
        $sign = str_replace(' ', '+', trim($data['sign']));
        if ($this->_check_lx_order($transdata, $config['payKey'], $sign)) {
            $ret = $this->_push_lx_order($order, 'lx');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                exit ('SUCCESS');
            } else {
                exit ('FAILURE');
            }
        } else {
            $data['order_id'] = $order['exorderno'];
            $this->_logOrder($data, 6, 'lx');
            exit ('FAILURE');
        }

    }

    //oppo
    public function txmixoppo()
    {
        $data = $_POST;
        $ret = $this->_check_params($data, $this->_oppo_order_params);
        file_put_contents('fzs.log', print_r(date('Y-m-d H:i:s', time()), 1) . print_r($data, 1) . PHP_EOL, FILE_APPEND);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['orderid'] = $data['partnerOrder'];
            $this->_logOrder($data, 7, 'oppo');
            exit('result=FAILED&resultMsg=loat_params');
        }
        $contents = $data;
        if ($this->rsa_verify($contents)) {
            $ret = $this->_push_oppo_order($data, 'oppo');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                exit('result=OK&resultMsg=OK');
            } else {
                exit('result=FAILED&resultMsg=' . $ret['msg']);
            }
        } else {
            $data['orderid'] = $data['partnerOrder'];
            $this->_logOrder($data, 6, 'oppo');
            exit('result=FAILED&resultMsg=wrong_sign');
        }

    }

    /**
     *  uc
     */
    public function txmixuc()
    {
        $body = file_get_contents('php://input');
        $data=json_decode($body,TRUE);
        file_put_contents('uc.log', date('Y-m-d H:i:s').'--'.json_encode($data).PHP_EOL,FILE_APPEND);
        $ret=$this->_check_params($data, $this->_uc_order_params);
        if(isset($ret['msg'])&&$ret['msg'])
        {
            $data['order_id']=$data['data']['callbackInfo'];
            $this->_logOrder($data,7,'uc');
            exit('FAILURE');
        }
        $test=$this->_check_uc($data, $channels['uc']['apiKey']);
        file_put_contents('uc.log', date('Y-m-d H:i:s').'-_check_uc-'.json_encode($test).PHP_EOL,FILE_APPEND);
        $channels=pc_base::load_config('android');
        if($this->_check_uc($data, $channels['uc']['apiKey'])
            &&isset($data['data']['orderStatus'])&&$data['data']['orderStatus']=='S'
            &&empty($data['data']['failedDesc']))
        {
            $ret=$this->_push_uc_order($data['data'],'uc');
            if(isset($ret['ret'])&&$ret['ret']==0||$ret['ret']==4)
            {
                echo 'SUCCESS';
            }else{
                echo 'FAILURE';
            }
        }else{
            $data['order_id']=$data['data']['callbackInfo'];
            $this->_logOrder($data['data'],6,'uc');
            echo 'FAILURE';
        }
    }

    /**
     * 华为
     */
    public function txmixhw()
    {
        $data=$_POST;
        $ret=$this->_check_params($data, $this->_hw_order_params);
        if(isset($ret['msg'])&&$ret['msg'])
        {
            $data['order_id']=$data['orderId'];
            $this->_logOrder($data,7,'hw');
            echo "{\"result\" : 1 }";
            return;
        }
        $key_path=CACHE_PATH.'/keys/payPublicKey.pem';
        if(!file_exists($key_path))
        {
            echo "{\"result\" : 1 }";
            return;
        }
        $sign = $data['sign'];
        unset($data['sign']);
        ksort($data);
        $params=array();
        foreach ($data as $k=>$v)
        {
            $params[]=$k.'='.$v;
        }
        $str=join('&', $params);
        $pubKey = @file_get_contents($key_path);
        $openssl_public_key = @openssl_get_publickey($pubKey);
        $ok = @openssl_verify($str,base64_decode($sign), $openssl_public_key);
        @openssl_free_key($openssl_public_key);
        $data['sign']=$sign;
        if($ok)
        {
            $ret=$this->_push_hw_order($data,'hw');
            if(isset($ret['ret'])&&$ret['ret']==0||$ret['ret']==4)
            {
                $result = "0";//支付成功
            }else{
                $result = "1";//支付失败
            }

        }else
        {
            $data['order_id']=$data['orderId'];
            $this->_logOrder($data,6,'hw');
            $result = "1";
        }
        $res = "{\"result\": $result} ";
        echo $res;
    }

    /**
     * @param $params
     * @param $keys
     * @return array
     *  vivo订单回调
     */
    public function txmixvivo()
    {
        $data=$_POST;
        $ret=$this->_check_params($data, $this->_vivo_order_params);
        if(isset($ret['msg'])&&$ret['msg'])
        {
            $data['order_id']=$data['cpOrderNumber'];
            $this->_logOrder($data,7,'vivo');
            $ret['errcode']=7;
            exit(json_encode($ret));
        }
        if($this->_check_vivo_order($data,'vivo'))
        {
            $ret=$this->_push_vivo_order($data,'vivo');
            if(isset($ret['ret'])&&$ret['ret']==0||$ret['ret']==4)
            {
                exit(json_encode(array('errcode'=>0,'msg'=>'ok')));
            }else{
                exit(json_encode(array('errcode'=>$ret['ret'],'msg'=>$ret['msg'])));
            }
        }else{
            $data['order_id']=$data['cpOrderNumber'];
            $this->_logOrder($data,6,'vivo');
            exit(json_encode(array('errcode'=>6)));
        }

    }

    /**
     * 小米订单回调
     */
    public function txmixxm()
    {
        $data=$_GET;
        file_put_contents('fzs.log',print_r(date('Y-m-d H:i:s',time()),1). print_r($data,1).PHP_EOL,FILE_APPEND);
        $ret=$this->_check_params($data, $this->_xm_order_params);
        if(isset($ret['msg'])&&$ret['msg'])
        {
            $data['order_id']=$data['orderId'];
            $this->_logOrder($data,7,'xm');
            $ret['errcode']=7;
            exit(json_encode($ret));
        }

        if($this->_check_xm_order($data,'xm',$this->_xm_order_params))
        {
            $ret=$this->_push_xm_order($data,'xm');
            if(isset($ret['ret'])&&$ret['ret']==0||$ret['ret']==4)
            {
                exit(json_encode(array('errcode'=>200,'errMsg'=>'ok')));
            }else{
                exit(json_encode(array('errcode'=>$ret['ret'],'errMsg'=>$ret['msg'])));
            }
        }else{
            $data['order_id']=$data['orderId'];
            $this->_logOrder($data,6,'xm');
            exit(json_encode(array('errcode'=>1525,'errMsg'=>'signature_error')));
        }
    }

    /**
     * 应用汇
     */
    public function txmixyyh(){
        $data=$_POST;//$_REQUEST;//$_POST;
        $data['transdata']=str_replace('\\','',$data['transdata']);
        $ret=$this->_check_params($data, $this->_yyh_order_params);
        if(isset($ret['msg'])&&$ret['msg'])
        {
            $transdata=json_decode($data['transdata']);
            $transdata['order_id']=$transdata['cporderid'];
            $this->_logOrder($transdata,7,'yyh');
            exit($ret['msg']);
        }
        $sign=$this->_check_yyh_order($data['transdata'],$data['sign']);
        $transdata=json_decode($data['transdata'],true);
        if($transdata['result']==0&&$sign)
        {
            $ret=$this->_push_yyh_order($transdata,'yyh');
            if(isset($ret['ret'])&&$ret['ret']==0||$ret['ret']==4)
            {
                exit('success');
            }else{
                exit($ret['msg']);//,JSON_UNESCAPED_UNICODE));
            }
        }else{
            $transdata['order_id']=$transdata['cporderid'];
            $this->_logOrder($transdata,6,'yyh');
            exit('签名错误');
        }

    }
    /**
     * 应用宝
     */
    public function txyyb()
    {
        $data=$_POST;
        $ret=$this->_check_params($data, $this->_app_tx_order_params);
        $data['sid']=$data['zoneid'];
        $data['orderId']=$data['billno'];
        if(isset($ret['msg'])&&$ret['msg'])
        {
            $this->_logOrder($data,7,'yyb');
            exit(json_encode($ret));
        }
        $rs=$this->_tx->get_one(array('orderid'=>$data['orderId']));
        if(!$rs)
        {
            $amount=$this->_get_tx_amount($data['account'], $data['sid']);
            $arr=array('content'=>json_encode($data),'orderid'=>$data['orderId'],'last_amount'=>$amount['acc_amount']);
            $this->_tx->insert($arr);
        }else if(isset($rs['status'])&&$rs['status']==0){
            $arr=array('content'=>json_encode($data),'next_process'=>0);
            $this->_tx->update($arr,array('orderid'=>$data['orderId']));
        }
    }

    /**
     * @param $params
     * @param $keys
     * @return array
     * 检查订单参数是否齐全
     */
    private function _check_params($params, $keys)
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
     * 一个随机数两位数+时间（精确到微秒）+随机数两位数
     */
    public function createOrderid()
    {
        list($usec, $sec) = explode(" ", microtime());
        $time = (float)date('YmdHis', $sec) . (float)$usec * 1000000;
        return rand(10, 99) . $time . rand(10, 99);
    }


}
