<?php
pc_base::load_app_class('pay', 'paycenter', 0);

class paytxyx extends pay
{

    /**
     * @var tx_model
     */
    protected $_tx;
    /**
     * @var tx_amount_model
     */
    protected $_tx_amount;

    /**
     * 检查订单是否合法
     * @param string $pay_id 订单ID
     * @param string $order_id 第三方订单ID
     * @param number $amount 金额
     * @param string $channel 渠道
     * @return array
     */
    protected function _check_an_order($pay_id, $amount, $channel)
    {
        $channel = strtolower($channel);
        $config = pc_base::load_config('android', $channel);
        $myorder = $this->_orders_list->get_one("`orderid`='" . $pay_id . "'");
        if (!$myorder) {
            return array('ret' => 5, 'msg' => '订单不是充值中心生成的');
        }
        //注意这里比较金额要乘以兑换比例 每个渠道的兑换比例不同
        if (isset($myorder['amount']) && $myorder['amount'] != $amount * $config['Ratio']) {
            return array('ret' => 8, 'msg' => '订单金额和对应商品价格不匹配');
        }

        $servers = $this->get_server_config();
        if (!isset($servers[$myorder['serverid']])) {
            return array('ret' => 3, 'msg' => '游戏服不存在');
        }
        if (!$this->_account->changeConnection($myorder['serverid'])) {
            return array('ret' => 9, 'msg' => '数据库错误');
        }
        $account = $this->_account->get_one(array('AccountName' => strtoupper($myorder['account']), 'ID' => $myorder['uid']));
        if (!$account) {
            return array('ret' => 2, 'msg' => '玩家不存在');
        }
        $table = strtolower('orders_' . $channel . '_s' . $myorder['serverid']);
        $otable = $this->_orders->getTable();
        $conditon = sprintf("`orderid`='%s' and `uid`='%s'", $pay_id, $account['ID']);
        if ($this->_orders->table_exists($table)) {

            $this->_orders->setTable($table);

            $ret = $this->_orders->get_one($conditon);
            if ($ret) {
                $this->_orders->setTable($otable);
                return array('ret' => 4, 'msg' => '订单已经存在');
            }
        }
        $this->_orders->setTable($otable);
        $ret = $this->_orders->get_one($conditon);
        if ($ret) {
            return array('ret' => 4, 'msg' => '订单已经存在');
        }
        return array('ret' => 0, 'myorder' => $myorder, 'account' => $account);
    }

    /*
    安峰sdk订单验证
     */
    protected function _check_afnew_order($data, $channel, $check_params)
    {
        $url = "http://sdkv4.qcwan.com/api/v1.0/cp/info/order";
        $appId = 1289;
        $signKey = "2fd4a67a973aff25145eeb0a04d14a61";
        $data = array(
            "app_id" => $data['vid'],
            "open_id" => $data['open_id'],
            'sn' => $data['sn'],
            'vorder_id' => $data['vorder_id']
        );
        ksort($data);
        reset($data);
        $postdatastr = "";
        foreach ($data as $k => $v) {
            $postdatastr .= $k . "=" . $v . "&";
        }
        $postdatastr = substr($postdatastr, 0, count($postdatastr) - 2);
        $postdata = $postdatastr;
        $postdatastr .= "&sign_key=" . $signKey;
        $sign = md5($postdatastr);
        $postdata .= "&sign=" . $sign;
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type:application/x-www-form-urlencoded;charset=utf-8 ',
                'content' => $postdata,
                'timeout' => 15 * 60 // 超时时间（单位:s）
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        file_put_contents('checkOrder.log', '$result' . date('Y-m-d H:i:s') . 'txmixaf2订单信息checkaforderxxx:' . print_r($postdata, 1) . PHP_EOL, FILE_APPEND);
        return $result;
    }

    /*
    安峰订单加入到充值队列
     */
    protected function _push_afnew_order($order,$channel)
    {
        $ret=$this->_check_an_order($order['vorder_id'],$order['sn'], $order['fee'],$channel);
        if(isset($ret['ret'])&&$ret['ret'])
        {
            $order['order_id']=$order['vorder_id'];
            $this->_logOrder($order,$ret['ret'],$channel);
            return $ret;
        }
        $data=array( 'channel'=>$channel,
            'serverid'=>$ret['myorder']['serverid'],
            'account'=>$ret['myorder']['account'],
            'uid'=>$ret['account']['ID'],
            'orderid'=>$order['vorder_id'],
            'url'=>$_SERVER['REQUEST_URI'],
            'billno'=>$order['sn'],
            'itemid'=>$ret['myorder']['itemid'],
            'amount'=>$ret['myorder']['amount'],
            'time'=>$ret['myorder']['ctime'],
            'ts'=>time(),
            'product_id'=>$order['subject'],
            'status'=>0,
            'sign'=>$order['sign'],
        );
        $this->_orders->insert($data);
        $order['order_id']=$order['vorder_id'];
        $this->_logOrder($order,0,$channel);
        return array('ret'=>0,'msg'=>'发货成功');
    }

    //360订单验证
    protected function _gen_qihoo_sign($data, $prikey, $check_params)
    {
        $params = array();
        foreach ($check_params as $k => $v) {
            if ($v == "sign_return" ||
                $v == "sign") {
                continue;
            }
            if (isset($data[$v])) {
                $params[$v] = $data[$v];
            }
        }
        ksort($params);
        $src_str = join('#', $params);
        $sign = md5($src_str . '#' . $prikey);
        return $sign;
    }

    //360订单加入到充值队列
    protected function _push_qihoo_order($order, $channel)
    {
        $ret = $this->_check_an_order($order['app_order_id'], $order['order_id'], $order['amount'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['app_order_id'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['order_id'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $ret['myorder']['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
            'sign' => $order['sign'],
        );
        $this->_orders->insert($data);
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加爱奇艺订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_iqy_order($order, $channel)
    {
        $ret = $this->_check_an_order($order['userData'], $order['order_id'], $order['money'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $order['userData'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['userData'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['order_id'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $order['money'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
            'sign' => $order['sign'],
        );
        $this->_orders->insert($data);
        $order['orderid'] = $order['userData'];
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加百度订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_baidu_order($OrderSerial, $CooperatorOrderSerial, $OrderMoney, $channel, $sign, $result)
    {
        $ret = $this->_check_an_order($CooperatorOrderSerial, $OrderSerial, $OrderMoney, $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['order_id'] = $CooperatorOrderSerial;
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $CooperatorOrderSerial,
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $OrderSerial,
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $ret['myorder']['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
            'sign' => $sign,
        );
        $this->_orders->insert($data);
        $order['order_id'] = $CooperatorOrderSerial;
        $order['content'] = $result;
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /*金立需要去金立后台创建订单*/
    protected function createJlOrder($orderid, $item, $account)
    {
        $jl = pc_base::load_config('android', 'jl');
        $dst_url = "https://pay.gionee.com/order/create";
        $post_arr['api_key'] = $jl['APIKey'];
        $post_arr['subject'] = $item['NameStr'];
        $post_arr['out_order_no'] = $orderid;
        $post_arr['deliver_type'] = '1';
        $post_arr['deal_price'] = $item['Paynull'];
        $post_arr['total_fee'] = $item['Paynull'];
        $post_arr['submit_time'] = date('YmdHis');
        $post_arr['notify_url'] = "http://andtxmixjl.pay.txmix.sg2.dianjianggame.com/index.php?m=paycenter&c=txmix&a=txmixjl";
        $post_arr['sign'] = $this->rsa_sign($post_arr);
        $post_arr['player_id'] = $account;
        $json = json_encode($post_arr);
        $return_json = $this->https_curl($dst_url, $json);
        $return_arr = json_decode($return_json, 1);
        //订单创建成功的状态码判断
        if ($return_arr['status'] !== '200010000') {
            //创建失败处理
            $ret = array('ret' => 10, 'msg' => '金立订单创建失败');
        } else {
            $ret = array('ret' => 1, 'msg' => $return_arr);
        }
        $table = 'jlorder';
        $this->_orders->setTable($table);
        $this->_orders->insert($return_arr);
        return $ret;
    }

    protected function https_curl($url, $post_arr = array(), $timeout = 10)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_arr);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        $content = curl_exec($curl);
        curl_close($curl);

        return $content;
    }

    /*金立验证签名*/
    protected function jl_rsa_verify($post_arr, $str_key)
    {
        ksort($post_arr);
        file_put_contents('fzs.log', 'check order :' . print_r(date('Y-m-d H:i:s', time()), 1) . '$post_arr=' . print_r($post_arr, 1) . PHP_EOL . '$str_key=' . print_r($str_key, 1) . PHP_EOL, FILE_APPEND);
        foreach ($post_arr as $key => $value) {
            if ($key == 'sign') continue;
            $signature_str .= $key . '=' . $value . '&';
        }
        $signature_str = substr($signature_str, 0, -1);
        $publickey = $str_key['PublicKey'];
        $pem = chunk_split($publickey, 64, "\n");
        $pem = "-----BEGIN PUBLIC KEY-----\n" . $pem . "-----END PUBLIC KEY-----\n";
        $public_key_id = openssl_pkey_get_public($pem);
        $signature = base64_decode($post_arr['sign']);
        return openssl_verify($signature_str, $signature, $public_key_id);
    }

    /**
     * 添加金立订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_jl_order($order, $channel)
    {
        /*商户订单号：第二个out_order_no 查重订单号*/
        $ret = $this->_check_an_order($order['out_order_no'], $order['out_order_no'], $order['deal_price'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['order_id'] = $order['out_order_no'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        //$this->_orders->setTable('jlorder');
        //$channelOrder=$this->_orders->get_one("`out_order_no`='".$order['out_order_no']."'");
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['out_order_no'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['out_order_no'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $ret['myorder']['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
        );
        //$this->_orders->getTable();
        $this->_orders->insert($data);
        $order['order_id'] = $order['out_order_no'];
        $this->_logOrder($order, 0, $channel);
        $callback_data = array('content' => json_encode($order), 'status' => 0);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /*验证联想签名*/
    protected function _check_lx_order($data, $priKey, $lxsign)
    {
        $test = strpos($priKey, "BEGIN RSA PRIVATE KEY");
        if (strpos($priKey, "BEGIN RSA PRIVATE KEY") === false) {
            $priKey = wordwrap($priKey, 64, "\n", true);
            $priKey = "-----BEGIN PRIVATE KEY-----\n" . $priKey . "\n-----END PRIVATE KEY-----";
        }
        $res = openssl_get_privatekey($priKey);
        openssl_sign($data, $sign, $res);
        openssl_free_key($res);
        $selfSign = base64_encode($sign);
        if ($selfSign == $lxsign) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 添加联想订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_lx_order($order, $channel)
    {
        $ret = $this->_check_an_order($order['exorderno'], $order['transid'], $order['money'], $channel);
        file_put_contents('fzs.log', 'chexkOrder $push_ret=' . print_r(date('Y-m-d H:i:s', time()), 1) . print_r($ret, 1) . PHP_EOL, FILE_APPEND);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $order['exorderno'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['exorderno'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['transid'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $ret['myorder']['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
        );
        $this->_orders->insert($data);
        $order['orderid'] = $order['exorderno'];
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加oppo订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_oppo_order($order, $channel)
    {
        $ret = $this->_check_an_order($order['partnerOrder'], $order['notifyId'], $order['price'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $order['partnerOrder'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['partnerOrder'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['notifyId'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $ret['myorder']['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
        );
        $this->_orders->insert($data);
        $order['orderid'] = $order['partnerOrder'];
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    //oppo 验签
    protected function rsa_verify($contents)
    {
        //$oppo=pc_base::load_config('android','oppo');
        $str_contents = "notifyId={$contents['notifyId']}&partnerOrder={$contents['partnerOrder']}&productName={$contents['productName']}&productDesc={$contents['productDesc']}&price={$contents['price']}&count={$contents['count']}&attach={$contents['attach']}";
        $pem = file_get_contents('caches/keys/pay_rsa_public_key.pem');
        $public_key_id = openssl_pkey_get_public($pem);
        $signature = base64_decode($contents['sign']);
        return openssl_verify($str_contents, $signature, $public_key_id);//成功返回1,0失败，-1错误,其他看手册
    }

    protected function _push_uc_order($order,$channel)
    {

        $ret=$this->_check_an_order($order['callbackInfo'],$order['orderId'], $order['amount'],$channel);
        if(isset($ret['ret'])&&$ret['ret'])
        {
            $order['order_id']=$order['callbackInfo'];
            $this->_logOrder($order,$ret['ret'],$channel);
            return $ret;
        }
        $data=array( 'channel'=>$channel,
            'serverid'=>$ret['myorder']['serverid'],
            'account'=>$ret['myorder']['account'],
            'uid'=>$ret['account']['ID'],
            'orderid'=>$order['callbackInfo'],
            'url'=>$_SERVER['REQUEST_URI'],
            'billno'=>$order['orderId'],
            'itemid'=>$ret['myorder']['itemid'],
            'amount'=>$ret['myorder']['amount'],
            'time'=>$ret['myorder']['ctime'],
            'ts'=>time(),
            'status'=>0,
            'sign'=>$order['sign'],
        );
        $this->_orders->insert($data);
        $order['order_id']=$order['callbackInfo'];
        $this->_logOrder($order,0,$channel);
        return array('ret'=>0,'msg'=>'发货成功');
    }

    /**
     * @param $order
     * @param $channel
     * @return array
     * 华为订单入库
     */
    protected function _push_hw_order($order,$channel)
    {
        $ret=$this->_check_an_order($order['requestId'],$order['orderId'], $order['amount'],$channel);
        if(isset($ret['ret'])&&$ret['ret'])
        {
            $order['order_id']=$order['orderId'];
            $this->_logOrder($order,$ret['ret'],$channel);
            return $ret;
        }
        $data=array( 'channel'=>$channel,
            'serverid'=>$ret['myorder']['serverid'],
            'account'=>$ret['myorder']['account'],
            'uid'=>$ret['account']['ID'],
            'orderid'=>$ret['myorder']['orderid'],
            'url'=>$_SERVER['REQUEST_URI'],
            'billno'=>$order['orderId'],
            'itemid'=>$ret['myorder']['itemid'],
            'amount'=>$ret['myorder']['amount'],
            'time'=>$ret['myorder']['ctime'],
            'ts'=>time(),
            'status'=>0,
        );
        $this->_orders->insert($data);
        $order['order_id']=$order['orderId'];
        $this->_logOrder($order,0,$channel);
        $callback_data=array('content'=>json_encode($order),'status'=>0);
        return array('ret'=>0,'msg'=>'发货成功');
    }

    /**
     * @param $data
     * @param $channel
     * @return bool
     * vivo订单检验
     */
    protected function _check_vivo_order($data,$channel)
    {
        $tx=pc_base::load_config('android',$channel);
        $params=array();
        foreach ($data as $k=>$v)
        {
            if($k=='signature'||$k=='signMethod')
            {
                continue;
            }
            $params[$k]=$v;
        }
        ksort($params);
        $str=array();
        foreach ($params as $k=>$v)
        {
            $str[]=$k.'='.$v;
        }
        $src_str=join('&', $str);
        $sign=strtolower(md5($src_str.'&'.strtolower(md5($tx['AppKey']))));
        return $sign==$data['signature'];
    }

    /**
     * @param $order
     * @param $channel
     * @return array
     * vivo订单入库
     */
    protected function _push_vivo_order($order,$channel)
    {
        $ret=$this->_check_an_order($order['cpOrderNumber'],$order['orderNumber'], $order['orderAmount'],$channel);
        if(isset($ret['ret'])&&$ret['ret'])
        {
            $order['order_id']=$order['cpOrderNumber'];
            $this->_logOrder($order,$ret['ret'],$channel);
            return $ret;
        }
        $data=array( 'channel'=>$channel,
            'serverid'=>$ret['myorder']['serverid'],
            'account'=>$ret['myorder']['account'],
            'uid'=>$ret['account']['ID'],
            'orderid'=>$order['cpOrderNumber'],
            'url'=>$_SERVER['REQUEST_URI'],
            'billno'=>$order['orderNumber'],
            'itemid'=>$ret['myorder']['itemid'],
            'amount'=>$ret['myorder']['amount'],
            'time'=>$ret['myorder']['ctime'],
            'ts'=>time(),
            'status'=>0,
        );
        $this->_orders->insert($data);
        $order['order_id']=$order['cpOrderNumber'];
        $this->_logOrder($order,0,$channel);
        return array('ret'=>0,'msg'=>'发货成功');
    }
    /**
     * @param $data
     * @param $key
     * @return bool
     * uc验证签名
     */
    protected function _check_uc($data,$key)
    {
        $params=$data['data'];
        ksort($params);
        $str='';
        foreach ($params as $k=>$v)
        {
            $str.=$k.'='.$v;
        }
        $sign=md5($str.$key);
        return $sign==$data['sign'];
    }

    /**
     * @param $data
     * @param $channel
     * @param $check_params
     * @return bool
     * 小米订单校验
     */
    protected function _check_xm_order($data,$channel,$check_params)
    {
        $tx=pc_base::load_config('android',$channel);
        $params=array();
        foreach ($check_params as $k=>$v)
        {
            if($v=='signature')
            {
                continue;
            }
            if(isset($data[$v]))
            {
                $params[$v]=$data[$v];
            }
        }
        ksort($params);
        $str=array();
        foreach ($params as $k=>$v)
        {
            $str[]=$k.'='.$v;
        }
        $src_str=join('&', $str);
        $sign=hash_hmac('sha1', $src_str, $tx['PayKey'],FALSE);
        return $sign==$data['signature'];
    }
    /**
     * @param $order
     * @param $channel
     * @return array
     * 小米订单入库
     */
    protected function _push_xm_order($order,$channel)
    {
        $ret=$this->_check_an_order($order['cpOrderId'],$order['orderId'], $order['payFee'],$channel);
        if(isset($ret['ret'])&&$ret['ret'])
        {
            $order['order_id']=$order['orderId'];
            $this->_logOrder($order,$ret['ret'],$channel);
            return $ret;
        }
        $data=array( 'channel'=>$channel,
            'serverid'=>$ret['myorder']['serverid'],
            'account'=>$ret['myorder']['account'],
            'uid'=>$ret['account']['ID'],
            'orderid'=>$order['cpOrderId'],
            'url'=>$_SERVER['REQUEST_URI'],
            'billno'=>$order['orderId'],
            'itemid'=>$ret['myorder']['itemid'],
            'amount'=>$ret['myorder']['amount'],
            'time'=>$ret['myorder']['ctime'],
            'ts'=>time(),
            'status'=>0,
            'sign'=>$order['signature'],

        );
        $this->_orders->insert($data);
        $order['order_id']=$order['orderId'];
        $this->_logOrder($order,0,$channel);
        return array('ret'=>0,'msg'=>'发货成功');
    }

    /**
     * @param $data
     * @param $sign
     * @return int
     * 应用汇订单校验
     */
    protected function _check_yyh_order($data,$sign){
        $config=pc_base::load_config('android',$channel);
        $pk=$config['pubKey'];
        $public_key = "-----BEGIN PUBLIC KEY-----\n" . chunk_split($pk, 64, "\n") . "-----END PUBLIC KEY-----";

        $openssl_public_key = @openssl_get_publickey($public_key);
        $ok = @openssl_verify($data, base64_decode($sign), $openssl_public_key, OPENSSL_ALGO_MD5);
        return $ok;

    }
    /**
     * @param array $order
     * @param string $channel
     * @return array
     * 应用汇订单入库
     */
    protected function _push_yyh_order($order,$channel)
    {
        $ret=$this->_check_an_order($order['cporderid'],$order['cporderid'],$order['money'],$channel);
        if(isset($ret['ret'])&&$ret['ret'])
        {
            $order['order_id']=$order['cporderid'];
            $this->_logOrder($order,$ret['ret'],$channel);
            return $ret;
        }
        $data=array( 'channel'=>$channel,
            'serverid'=>$ret['myorder']['serverid'],
            'account'=>$ret['myorder']['account'],
            'uid'=>$ret['account']['ID'],
            'orderid'=>$order['cporderid'],
            'url'=>$_SERVER['REQUEST_URI'],
            'billno'=>$order['cporderid'],
            'itemid'=>$ret['myorder']['itemid'],
            'amount'=>$order['money'],
            'time'=>$ret['myorder']['ctime'],
            'ts'=>time(),
            'status'=>0,
            'sign'=>$order['sign'],
        );
        $this->_orders->insert($data);
        $order['order_id']=$order['cporderid'];
        $this->_logOrder($order,0,$channel);
        return array('ret'=>0,'msg'=>'发货成功');
    }

    /**
     * @param $account
     * @param $sid
     * @return array
     * 查看账号在本地记录的累计充值金额（为空的话，作为第一次充值插入）
     */
    protected function _get_tx_amount($account,$sid)
    {
        $amount=$this->_tx_amount->get_one(array('account'=>$account,'sid'=>$sid));
        if(!$amount)
        {
            $amount=array('account'=>$account,'sid'=>$sid,'acc_amount'=>0);
            $this->_tx_amount->insert($amount);
        }
        return $amount;
    }


}
