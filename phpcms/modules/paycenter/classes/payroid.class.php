<?php
pc_base::load_app_class('pay', 'paycenter', 0);

class payroid extends pay
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
//        file_put_contents('appstore.log', print_r($myorder,1).$amount.print_r($config,1),FILE_APPEND);
        //注意这里比较金额要乘以兑换比例 每个渠道的兑换比例不同
        if (isset($myorder['amount']) && $myorder['amount'] != $amount) {// * $config['Ratio']) {
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

    /**
     * 模拟post进行url请求
     * @param $url
     * @param bool $ispost
     * @param array $data
     * @return array|mixed
     */
    protected function request_post($url,$ispost = true, $data = array()){
        //生成 URL-encode 之后的请求字符串
        $data=http_build_query($data);
        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_URL, $url);//抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0);//设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
        if ($ispost) {
            curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        $data = curl_exec($ch);//运行curl
        curl_close($ch);
        $ret=json_decode($data,true);
        if($ret['status']==0){
            return $data;
        }
    }

    /**
     * (第三方)贝付宝
     * @param $order
     * @param $channel
     * @return array
     */
    protected function _push_rxsg_order($order , $channel){
        $ret = $this->_check_an_order($order['orderno'], $order['wxno'], $order['fee'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        //充值表pay 的信息
        $items = $this->_getPayConfig($channel);
        $data = array(
            'channel' => $channel,//渠道
            'serverid' => $ret['myorder']['serverid'],//游戏服务器编号
            'account' => $ret['myorder']['account'],//游戏账号
            'uid' => $ret['account']['ID'],
            'orderid' => $ret['myorder']['orderid'],//充值中心生成的订单号
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['wxno'],//平台订单号
            'itemid' => $ret['myorder']['itemid'],//商品编号（我方）
            'product_id' => '',//商品编号（平台） ,没有就为空
            'amount' => $ret['myorder']['amount'],//商品金额
            'dollar' => $items[$ret['myorder']['itemid']]['payCount'],//商品金额（美元）
            'gold' => (int)$items[$ret['myorder']['itemid']]['Gain'],//获得元宝数
            'ts' => time(),//平台提交订单时间
            'time' => date('Y-m-d H:i:s', $ret['myorder']['ctime']),//发起时间
            'status' => 0,//订单处理状态
            'sign' => $order['sign'],//签名,没有就为空
        );
        $this->_orders->insert($data);
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加支付宝充值订单到充值队列
     * @param $order
     * @param $channel
     * @return array
     */
    protected  function _push_gatcnAli_order($order,$channel)
    {
        $ret=array('ret'=>0,'msg'=>'发货成功');
        $ret=$this->_check_an_order($order['out_trade_no'],$order['total_amount'],$channel);
        if(isset($ret['ret'])&&$ret['ret'])
        {
            $order['orderid']=$order['out_trade_no'];
            $this->_logOrder($order,$ret['ret'],$channel);
            return $ret;
        }
        $data=array('channel'=>$channel,
            'serverid'=>$ret['myorder']['serverid'],
            'account'=>$ret['myorder']['account'],
            'uid'=>$ret['myorder']['uid'],
            'orderid'=>$order['out_trade_no'],
            'url'=>$_SERVER['REQUEST_URI'],
            'billno'=>$order['trade_no'],
            'itemid'=>$ret['myorder']['itemid'],
            'amount'=>$ret['myorder']['amount'],
            'ts'=>$ret['myorder']['ctime'],
            'time'=>date('Y-m-d H:i:s',time()),
            'status'=>'0',
        );
        $this->_orders->insert($data);
        $order['orderid']=$order['out_trade_no'];
        $this->_logOrder($order,0,$channel);
        return array('ret'=>0,'msg'=>'发货成功');
    }

    /**
     * 添加微信订单到充值队列
     * @param $order
     * @param $channel
     * @return array
     */
    protected  function _push_gatcnWx_order($order,$channel)
    {
        $order['cash_fee']=$order['cash_fee']*0.01;
        $ret=array('ret'=>0,'msg'=>'发货成功');
        $ret=$this->_check_an_order($order['out_trade_no'],$order['cash_fee'],$channel);
        if(isset($ret['ret'])&&$ret['ret'])
        {
            $order['orderid']=$order['out_trade_no'];
            $this->_logOrder($order,$ret['ret'],$channel);
            return $ret;
        }
        $data=array('channel'=>$channel,
            'serverid'=>$ret['myorder']['serverid'],
            'account'=>$ret['myorder']['account'],
            'uid'=>$ret['myorder']['uid'],
            'orderid'=>$order['out_trade_no'],
            'url'=>$_SERVER['REQUEST_URI'],
            'billno'=>$order['transaction_id'],
            'itemid'=>$ret['myorder']['itemid'],
            'amount'=>$ret['myorder']['amount'],
            'ts'=>$ret['myorder']['ctime'],
            'time'=>date('Y-m-d H:i:s',time()),
            'status'=>'0',
        );
        $this->_orders->insert($data);
        $order['orderid']=$order['out_trade_no'];
        $this->_logOrder($order,0,$channel);
        return array('ret'=>0,'msg'=>'发货成功');
    }

    /**
     * 添加台湾gd订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_gdt_order($order, $channel)
    {
        $data = array(
            'channel' => $order['channel'],
            'serverid' => $order['serverCode'],
            'account' => $order['userId'],
            'uid' => $order['roleId'],
            'orderid' => $order['orderId'],
            'url' => $_SERVER['REQUEST_URI'],
            'gold' => $order['gameCurrency'],
            'itemid' => $order['proItemId'],
            'amount' => $order['amount'],
            'time' => $order['timeStamp'],
            'ts' => time(),
            'status' => 0,
            'sign' => $order['sign'],
        );
        file_put_contents('gat.log', date('Y-m-d H:i:s') . 'gd:' . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
        $this->_orders->insert($data);
        $order['orderid'] = $order['orderId'];
        $this->_logOrder($order, 0, $order['channel']);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加91百度订单到充值队列
     * @param string $OrderSerial 百度订单号
     * @param string $CooperatorOrderSerial 平台订单号
     * @param array $order 订单信息
     * @param string $channel 渠道名
     * @return array
     */
    protected function _push_baidu_order($OrderSerial, $CooperatorOrderSerial, $order, $channel)
    {
        $ret = $this->_check_an_order($CooperatorOrderSerial, $OrderSerial, $order['OrderMoney'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['order_id'] = $OrderSerial;
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $OrderSerial,
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $CooperatorOrderSerial,
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $ret['myorder']['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
            'sign' => $order['sign'],
        );
        $this->_orders->insert($data);
        $order['order_id'] = $OrderSerial;
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加UC订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_uc_order($order, $channel)
    {

        $ret = $this->_check_an_order($order['callbackInfo'], $order['orderId'], $order['amount'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['order_id'] = $order['orderId'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['orderId'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['callbackInfo'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $ret['myorder']['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
            'sign' => $order['sign'],
        );
        $this->_orders->insert($data);
        $order['order_id'] = $order['orderId'];
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加腾讯订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_tx_order($order, $channel)
    {
        $ret = $this->_check_an_order($order['orderid'], $order['orderid'], $order['amount'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['orderid'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['orderid'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $ret['myorder']['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
        );
        $this->_orders->insert($data);
        $this->_logOrder($order, 0, $channel);
        $callback_data = array('content' => json_encode($order), 'status' => 0);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加华为订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_hw_order($order, $channel)
    {
        $ret = $this->_check_an_order($order['extReserved'], $order['orderId'], $order['amount'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['order_id'] = $order['orderId'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['orderId'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['extReserved'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $ret['myorder']['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
        );
        $this->_orders->insert($data);
        $order['order_id'] = $order['orderId'];
        $this->_logOrder($order, 0, $channel);
        $callback_data = array('content' => json_encode($order), 'status' => 0);
        $this->_tx->insert($data);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加小米订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_xm_order($order, $channel)
    {
        $ret = $this->_check_an_order($order['cpOrderId'], $order['orderId'], $order['payFee'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['order_id'] = $order['orderId'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['orderId'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['cpOrderId'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $ret['myorder']['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
            'sign' => $order['signature'],

        );
        $this->_orders->insert($data);
        $order['order_id'] = $order['orderId'];
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加vivo订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_vivo_order($order, $channel)
    {
        $ret = $this->_check_an_order($order['cpOrderNumber'], $order['orderNumber'], $order['orderAmount'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['order_id'] = $order['orderNumber'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['orderNumber'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['cpOrderNumber'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $ret['myorder']['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
        );
        $this->_orders->insert($data);
        $order['order_id'] = $order['orderNumber'];
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加安智订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_anzhi_order($order, $channel)
    {
        $ret = $this->_check_an_order($order['cpInfo'], $order['orderId'], $order['payAmount'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['order_id'] = $order['orderId'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['orderId'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['cpInfo'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $ret['myorder']['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
        );
        $this->_orders->insert($data);
        $order['order_id'] = $order['orderId'];
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加靠谱订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_kaopu_order($order, $channel)
    {
        $ret = $this->_check_an_order($order['ywordernum'], $order['kpordernum'], $order['amount'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $order['kpordernum'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['kpordernum'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['ywordernum'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $ret['myorder']['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
        );
        $this->_orders->insert($data);
        $order['orderid'] = $order['kpordernum'];
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加优酷订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_youku_order($order, $channel)
    {
        $ret = $this->_check_an_order($order['passthrough'], $order['apporderID'], $order['price'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $order['apporderID'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['apporderID'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['passthrough'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $ret['myorder']['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
        );
        $this->_orders->insert($data);
        $order['orderid'] = $order['apporderID'];
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加豌豆荚订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_wandoujia_order($order, $channel)
    {
        $ret = $this->_check_an_order($order['out_trade_no'], $order['orderId'], $order['money'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $order['orderId'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['orderId'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['out_trade_no'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $ret['myorder']['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
        );
        $this->_orders->insert($data);
        $order['orderid'] = $order['orderId'];
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
            $order['orderid'] = $order['notifyId'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['notifyId'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['partnerOrder'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $ret['myorder']['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
        );
        $this->_orders->insert($data);
        $order['orderid'] = $order['notifyId'];
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加梦城订单到充值队列
     * @param array $order
     * @param string $channel
     * @param string $subchannel 子渠道
     * @return array
     */
    protected function _push_mengcheng_order($order, $channel, $subchannel)
    {
        $ret = $this->_check_an_order($order['cp_order_id'], $order['order_id'], $order['order_amount'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $this->_logOrder($order, $ret['ret'], $subchannel);
            return $ret;
        }
        $data = array('channel' => $subchannel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['order_id'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['cp_order_id'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $ret['myorder']['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
        );
        $this->_orders->insert($data);
        $this->_logOrder($order, 0, $subchannel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加易接订单到充值队列
     * @param array $order
     * @param string $channel
     * @param string $subchannel 子渠道
     * @return array
     */
    protected function _push_yijie_order($order, $channel, $subchannel)
    {
        $ret = $this->_check_an_order($order['cbi'], $order['tcd'], $order['fee'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $order['tcd'];
            $this->_logOrder($order, $ret['ret'], $subchannel);
            return $ret;
        }
        //充值表pay 的信息
        $items = $this->_getPayConfig($channel);
        $data = array(
            'channel' => $channel,//渠道
            'subchannel' => $subchannel, //分渠道
            'serverid' => $ret['myorder']['serverid'],//游戏服务器编号
            'account' => $ret['myorder']['account'],//游戏账号
            'uid' => $ret['account']['ID'],
            'orderid' => $ret['myorder']['orderid'],//充值中心生成的订单号
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['cbi'],//平台订单号
            'itemid' => $ret['myorder']['itemid'],//商品编号（我方）
            'product_id' => '',//商品编号（平台） ,没有就为空
            'amount' => $ret['myorder']['amount'],//商品金额
            'dollar' => $items[$ret['myorder']['itemid']]['payCount'],//商品金额（美元）
            'gold' => (int)$items[$ret['myorder']['itemid']]['Gain'],//获得元宝数
            'ts' => time(),//平台提交订单时间
            'time' => date('Y-m-d H:i:s', $ret['myorder']['ctime']),//发起时间
            'status' => 0,//订单处理状态
            'sign' => $order['sign'],//签名,没有就为空
        );
        $this->_orders->insert($data);
        $order['orderid'] = $order['tcd'];
        $this->_logOrder($order, 0, $subchannel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加虫虫助手订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_chongchong_order($order, $channel)
    {
        $ret = $this->_check_an_order($order['partnerTransactionNo'], $order['transactionNo'], $order['orderPrice'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $order['transactionNo'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['transactionNo'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['partnerTransactionNo'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $ret['myorder']['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
        );
        $this->_orders->insert($data);
        $order['orderid'] = $order['transactionNo'];
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加天机订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_an_tj_order($order, $channel)
    {
        static $sub_channels = array(
            '100001' => 'antj_baidu', '100002' => 'antj_ddztc',
            '100003' => 'antj_xyxpt', '100004' => 'antj_abzf',
            '110001' => 'antj_by', '110002' => 'antj_yy',
            '110003' => 'antj_jrtt1', '110004' => 'antj_jrtt2',
            '110005' => 'antj_jrtt3', '110006' => 'antj_jrtt4',
            '110007' => 'antj_jrtt5', '110008' => 'antj_tjdg',
            '110009' => 'antj_tjym', '110010' => 'antj_tjwb',
            '110011' => 'antj_bddsp2', '110012' => 'antj_tjbddsp3',
            '110013' => 'antj_bddsp4', '110014' => 'antj_bddsp5',
            '110015' => 'antj_dbdsp6', '110016' => 'antj_yf',
            '110017' => 'antj_cs', '110018' => 'antj_uc',
        );

        $ret = $this->_check_an_order($order['cporderid'], $order['transid'], $order['money'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $order['transid'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }

        if (isset($sub_channels[$order['cpprivate']])) {
            $channel = $sub_channels[$order['cpprivate']];
        }

        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['transid'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['cporderid'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $order['money'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
            'sign' => $order['sign'],
        );
        $this->_orders->insert($data);
        $order['orderid'] = $order['transid'];
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加极品天机订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_an_jptj_order($order, $channel)
    {
        static $sub_channels = array(
            '120001' => 'anjptj_yf', '120002' => 'anjptj_dg',
            '120003' => 'anjptj_by', '120004' => 'anjptj_jrtt1',
            '120005' => 'anjptj_jrtt2', '120006' => 'anjptj_jrtt3',
            '120007' => 'anjptj_jrtt4', '120008' => 'anjptj_jrtt5',
            '120009' => 'anjptj_uuc'
        );

        $ret = $this->_check_an_order($order['cporderid'], $order['transid'], $order['money'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $order['transid'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }

        if (isset($sub_channels[$order['cpprivate']])) {
            $channel = $sub_channels[$order['cpprivate']];
        }

        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['transid'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['cporderid'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $order['money'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
            'sign' => $order['sign'],
        );
        $this->_orders->insert($data);
        $order['orderid'] = $order['transid'];
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加悠悠村订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_an_yyc_order($order, $channel)
    {
        static $sub_channels = array('100001' => 'antj_baidu');

        $ret = $this->_check_an_order($order['order_id'], $order['txn_seq'], $order['actual_txn_amt'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $order['txn_seq'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }

        if (isset($sub_channels[$order['cpprivate']])) {
            $channel = $sub_channels[$order['cpprivate']];
        }

        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['txn_seq'],
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
        $order['orderid'] = $order['txn_seq'];
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加猎宝订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_an_liebao_order($order, $channel)
    {

        $ret = $this->_check_an_order($order['attach'], $order['orderid'], $order['amount'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }

        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['orderid'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['attach'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $order['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
            'sign' => $order['sign'],
        );
        $this->_orders->insert($data);
        $order['orderid'] = $order['orderid'];
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加卓易订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_an_zhuoyi_order($order, $channel)
    {

        $ret = $this->_check_an_order($order['Urecharge_Id'], $order['Recharge_Id'], $order['Recharge_Money'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $order['Recharge_Id'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }

        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['Recharge_Id'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['Urecharge_Id'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $order['Recharge_Money'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
            'sign' => $order['sign'],
        );
        $this->_orders->insert($data);
        $order['orderid'] = $order['Recharge_Id'];
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加朋友玩订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_an_pyw_order($order, $channel)
    {
        $ret = $this->_check_an_order($order['cp_orderid'], $order['ch_orderid'], $order['amount'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $order['ch_orderid'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }

        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['ch_orderid'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['cp_orderid'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $order['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
            'sign' => $order['sign'],
        );
        $this->_orders->insert($data);
        $order['orderid'] = $order['ch_orderid'];
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加xy订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_an_xy_order($order, $channel)
    {
        $ret = array('ret' => 0, 'msg' => '发货成功');
        $ret = $this->_check_an_order($order['extra'], $order['orderid'], $order['amount'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $this->_logOrder($order, $ret['ret'], $channel);
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $order['uid'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['orderid'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['extra'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $order['amount'],
            'ts' => $order['ts'],
            'status' => 0,
            'extra' => $order['extra'],
            'sign' => $order['sign'],
        );
        $this->_orders->insert($data);
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加峰助手订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_an_fzs_order($order, $channel)
    {
        $ret = array('ret' => 0, 'msg' => '发货成功');
        $ret = $this->_check_an_order($order['extraInfo'], $order['orderId'], $order['amount'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $order['orderId'];
            $this->_logOrder($order, $ret['ret'], $channel);
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $order['uid'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['orderId'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['extraInfo'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $order['amount'],
            'ts' => $order['ts'],
            'status' => 0,
            'extra' => $order['extra'],
            'sign' => $order['sign'],
        );
        $this->_orders->insert($data);
        $order['orderid'] = $order['orderId'];
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     *  生成360签名
     * @param array $data 订单信息
     * @param string $prikey 密钥
     * @param array $check_params 加密使用的参数
     * @return string
     */
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

    /**
     * 检查uc订单是否合法
     * @param array $data
     * @param string $key
     */
    protected function _check_uc($data, $key)
    {
        $params = $data['data'];
        ksort($params);
        $str = '';
        foreach ($params as $k => $v) {
            $str .= $k . '=' . $v;
        }
        $sign = md5($str . $key);
        return $sign == $data['sign'];
    }

    /**
     * 生成腾讯签名
     * @param string $method
     * @param string $url
     * @param array $data
     * @param string $key
     * @return string
     */
    protected function _get_tx_sign($method, $url, $data, $key)
    {
        $url = rawurlencode($url);
        $params = $data;
        if (isset($params['sign'])) {
            unset($params['sign']);
        }
        ksort($params);
        $str = rawurlencode(http_build_query($params));
        $src_str = $method . '&' . $url . '&' . $str;
        $sign = base64_encode(hash_hmac('sha1', $src_str, $key . '&', TRUE));
    }

    /**
     * 验证腾讯订单
     * @param array $data
     * @param string $channel
     * @return bool
     */
    protected function _check_tx_order($data, $channel)
    {
        $tx = pc_base::load_config('android', $channel);
        $openv3 = new OpenApiV3($tx['AppId'], $tx['AppKey']);
        return SnsSigCheck::verifySig($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $data, $tx['AppKey'], $data['sig']);
    }

    /**
     * 检查小米订单是否合法
     * @param array $data
     * @param string $channel
     * @param array $check_params
     * @return bool
     */
    protected function _check_xm_order($data, $channel, $check_params)
    {
        $tx = pc_base::load_config('android', $channel);
        $params = array();
        foreach ($check_params as $k => $v) {
            if ($v == 'signature') {
                continue;
            }
            if (isset($data[$v])) {
                $params[$v] = $data[$v];
            }
        }
        ksort($params);
        $str = array();
        foreach ($params as $k => $v) {
            $str[] = $k . '=' . $v;
        }
        $src_str = join('&', $str);
        $sign = hash_hmac('sha1', $src_str, $tx['PayKey'], FALSE);
        return $sign == $data['signature'];
    }

    /**
     * 检查靠谱助手订单
     * @param array $data
     * @param string $channel
     * @param array $check_params
     * @return bool
     */
    protected function _check_kaopu_order($data, $channel, $check_params)
    {
        $tx = pc_base::load_config('android', $channel);
        $params = array();
        foreach ($check_params as $k => $v) {
            if ($v == 'sign') {
                continue;
            }
            if (isset($data[$v])) {
                $params[$v] = $data[$v];
            }
        }
//        $src=array();
//        foreach ($params as $k=>$v) 
//        {
//            $src[]=$v;
//        }
        $src_str = join('|', $params);
        $sign = md5($src_str . '|' . $tx['PayKey']);
        return $sign == $data['sign'];
    }

    /**
     * 检查优酷订单
     * @param array $data
     * @param string $channel
     * @return bool
     */
    protected function _check_youku_order($data, $channel)
    {
        $url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $tx = pc_base::load_config('android', $channel);
        $params = array();
        foreach ($data as $k => $v) {
            if ($k == 'sign' || $k == 'passthrough' || $k == 'result' || $k == 'success_amount') {
                continue;
            }
            $params[$k] = $v;
        }
        ksort($params);
        $str = array();
        foreach ($params as $k => $v) {
            $str[] = $k . '=' . $v;
        }
        $src_str = $url . '?' . join('&', $str);
        $sign = hash_hmac('md5', $src_str, $tx['PayKey'], FALSE);
        return $sign == $data['sign'];
    }

    /**
     * 检查VIVO订单
     * @param array $data
     * @param string $channel
     * @return bool
     */
    protected function _check_vivo_order($data, $channel)
    {
        $tx = pc_base::load_config('android', $channel);
        $params = array();
        foreach ($data as $k => $v) {
            if ($k == 'signature' || $k == 'signMethod') {
                continue;
            }
            $params[$k] = $v;
        }
        ksort($params);
        $str = array();
        foreach ($params as $k => $v) {
            $str[] = $k . '=' . $v;
        }
        $src_str = join('&', $str);
        $sign = strtolower(md5($src_str . '&' . strtolower(md5($tx['AppKey']))));
        return $sign == $data['signature'];
    }

    /**
     * 检查梦城订单
     * @param array $data
     * @param string $channel
     * @return bool
     */
    protected function _check_mengcheng_order($data, $channel)
    {
        $tx = pc_base::load_config('android', $channel);
        $params = array();
        foreach ($data as $k => $v) {
            if ($k == 'sign') {
                continue;
            }
            $params[$k] = $v;
        }
        ksort($params);
        $src_str = join('', $params);
        $sign = md5($src_str . $tx['AppKey']);
        return $sign == $data['sign'];
    }

    /**
     * 检查虫虫助手订单
     * @param array $data
     * @param string $channel
     * @return bool
     */
    protected function _check_chongchong_orders($data, $channel)
    {
        $cc = pc_base::load_config('android', $channel);
        $params = array();
        foreach ($data as $k => $v) {
            if ($k == 'sign') {
                continue;
            }
            $params[$k] = $v;
        }
        ksort($params);
        $str = array();
        foreach ($params as $k => $v) {
            $str[] = $k . '=' . $v;
        }
        $src_str = join('&', $str);
        $sign = md5($src_str . '&' . $cc['PayKey']);
        return $sign == $data['sign'];
    }

    /**
     * 检查易接订单
     * @param array $data
     * @param string $channel
     * @param array $check_params
     * @return bool
     */
    protected function _check_yijie_orders($data, $channel, $check_params)
    {
        $tx = pc_base::load_config('android', $channel);
        $params = array();
        foreach ($check_params as $k => $v) {

            if ($v == 'sign') {
                continue;
            }
            if (isset($data[$v])) {
                $params[$v] = $data[$v];
            }
        }
        ksort($params);
        $str = array();
        foreach ($params as $k => $v) {
            $str[] = $k . '=' . $v;
        }
        $src_str = join('&', $str);
        $sign = md5($src_str . $tx['PayKey']);
        return $sign == $data['sign'];
    }

    /**
     * 检查google订单
     * @param array $rsp
     * @param array $user
     * @param string $appkey
     * @return bool
     */
    protected function _check_google_order($data, $sign, $channel)
    {
        $inapp_purchase_data = $data;//原始订单信息json
        $inapp_data_signature = $sign;//签名
        $google_public_key = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAgmDFtbDV7qRstfs0WunhFxoRaf33+HOXEg3x1f5Zz670JBw5NegDPh0T6Bi+5fTzs3hrT+ilxWoUTM2JtOkNjLWr7hF2QVbkdZxsMTiQEPTchT6UGtRASzUMir9zakbj3rEHISBiTWRM4UAYDpsdCcC6v/5JApQz2M9EnVh3d+5z3TYRFMRbxOkAYthh2iKa+pIKMzNDpQqMQoAkM4Ha5cgDCxpnPGcyxxKGrMftzmXW8PStL45gtMrYo8qRGItKhvyBIzIWs+dM5MWglO+FvTUVNQStAc8eVGWDuw7xbiEa1c+mRrwVhYA+WMjeYh+jGVkCJJF55/FnhiXnQWOHVwIDAQAB';
        $public_key = "-----BEGIN PUBLIC KEY-----\n" . chunk_split($google_public_key, 64, "\n") . "-----END PUBLIC KEY-----";

        $public_key_handle = openssl_get_publickey($public_key);

        $result = openssl_verify($inapp_purchase_data, base64_decode($inapp_data_signature), $public_key_handle, OPENSSL_ALGO_SHA1);

        //客户端汇报上来的订单处理状态确认 purchaseState        订单的购买状态。可能的值为 0（已购买）、1（已取消）或者 2（已退款）
        if ($data['purchaseState'] != 0) {
            return false;
        }

        if ($result == 1) {
            return 'success';
        } elseif ($result == 0) {
            return 'failure';
        } else {
            return 'other';
        }
    }

    /*
     *请求验证google订单
     */
    protected function _check_google_order_two($data, $self_order)
    {
        usleep(2000000);
        include_once('/phpcms/modules/paycenter/google-api-php-client/src/Google_Client.php');
        include_once('/phpcms/modules/paycenter/google-api-php-client/src/contrib/Google_AndroidpublisherService.php');
        $ANDROIDUsertoken = $data;
        $user_token = json_decode($ANDROIDUsertoken, true);
        $CLIENT_ID = 'id-297@api-8147854253376649668-77989.iam.gserviceaccount.com';
        $SERVICE_ACCOUNT_NAME = 'id-297@api-8147854253376649668-77989.iam.gserviceaccount.com';
        $KEY_FILE = '/phpcms/modules/paycenter/google-api-php-client/key.p12';
        $client = new Google_Client();
        $client->setApplicationName($user_token['packageName']);
        $client->setClientId($CLIENT_ID);
        $key = file_get_contents($KEY_FILE);

        $auth = new Google_AssertionCredentials(
            $SERVICE_ACCOUNT_NAME,
            array('https://www.googleapis.com/auth/androidpublisher'),
            $key);
        $client->setAssertionCredentials($auth);
        $AndroidPublisherService = new Google_AndroidPublisherService($client);
        $res = $AndroidPublisherService->inapppurchases->get($user_token['packageName'], $user_token['productId'], $user_token['purchaseToken']);
        #    if($res['purchaseState'] != 0){
        #        $str=array('status'='failed','msg'='没有付款');
        #    }
        #    if($res['consumptionState'] !=1){
        #        $str=array('status'=>'failed','msg'=>'没有使用道具');
        #   }
        #       if($['developerPayload'] == $self_order){
        #        $str=array('status'=>'failed','msg'=>'订单不正确');
        #    }
        #    return $str;
        if (0 == $res['purchaseState'] && (1 == $res['consumptionState'])) {
            print_r(" check success \n");
            return true;
        }
        return false;
    }

    /**
     * 检查google订单(验证订单状态)
     * @param array $rsp
     * @param array $user
     * @param string $appkey
     * @return bool
     */
    protected function new_check_google_order($data, $sign, $channel, $norderid)
    {
//      file_put_contents('/data/www/sg/pay/and/phpcms/modules/paycenter/google110.log','存表内容'.print_r($data,1).'-----------\n\n\n\n',FILE_APPEND);
        $inapp_purchase_data = $data;//原始订单信息json
        $inapp_data_signature = $sign;//签名
        $google_public_key = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAgmDFtbDV7qRstfs0WunhFxoRaf33+HOXEg3x1f5Zz670JBw5NegDPh0T6Bi+5fTzs3hrT+ilxWoUTM2JtOkNjLWr7hF2QVbkdZxsMTiQEPTchT6UGtR
ASzUMir9zakbj3rEHISBiTWRM4UAYDpsdCcC6v/5JApQz2M9EnVh3d+5z3TYRFMRbxOkAYthh2iKa+pIKMzNDpQqMQoAkM4Ha5cgDCxpnPGcyxxKGrMftzmXW8PStL45gtMrYo8qRGItKhvyBIzIWs+dM5MWglO+FvTUVNQStAc8eVGWDuw7xbiEa1c+m
RrwVhYA+WMjeYh+jGVkCJJF55/FnhiXnQWOHVwIDAQAB';
        $public_key = "-----BEGIN PUBLIC KEY-----\n" . chunk_split($google_public_key, 64, "\n") . "-----END PUBLIC KEY-----";

        $public_key_handle = openssl_get_publickey($public_key);

        $result = openssl_verify($inapp_purchase_data, base64_decode($inapp_data_signature, $public_key_handle, OPENSSL_ALGO_SHA1));
        //
        if (1 !== $result) {
            return false;
        }
        //解码出订单数据
        $data = json_decode($inapp_purchase_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        //玩家在我们平台上创建的订单
#        if ($data['developerPayload'] != $norderid) {
#            return false;
#        }

        //客户端汇报上来的订单处理状态确认 purchaseState        订单的购买状态。可能的值为 0（已购买）、1（已取消）或者 2（已退款）
        if ($data['purchaseState'] != 0) {
            return false;
        }
        return true;
    }

    /*
     * 添加自运营sband充值订单到充值队列
     */
    protected function _push_google_and_order($order, $channel)
    {
//        $ret=array('ret'=>0,'msg'=>'发货成功');
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $order['MerchantRef'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $order['res']['serverid'],
            'account' => $order['res']['account'],
            'uid' => $order['res']['uid'],
            'orderid' => $order['MerchantRef'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['Extraparam1'],
            'itemid' => $order['res']['itemid'],
            'amount' => $order['Amount'],
            'ts' => $order['ts'],
            'time' => date('Y-m-d H:i:s', time()),
            'status' => '0',
            'extra' => $order['extra'],
            'sign' => $order['purchaseToken'],
        );
        file_put_contents('/data/www/sg/pay/and/phpcms/modules/paycenter/google12.log', print_r($data, 1), FILE_APPEND);
        $this->_orders->insert($data);
        $order['orderid'] = $order['MerchantRef'];
        $this->_logOrder($order, 0, $channel);
//        return array('ret'=>0,'msg'=>'发货成功');
    }

    /**
     * 检查自运营sbios订单
     * @param array $rsp
     * @param array $user
     * @param string $appkey
     * @return bool
     */
    protected function _check_sbios_order($orders, $params, $channel)
    {
        $app = pc_base::load_config('oversea', $channel);
        $str = '';
        foreach ($params as $k => $v) {
            if ($v == 'Sign') {
                continue;
            }
            if (isset($orders[$v])) {
                $str .= $orders[$v];
            }
        }
        $sign = md5($str . $app['PayKey']);
        return $orders['Sign'] == $sign;
    }

    /*
     * 添加自运营sbios充值订单到充值队列
     */
    protected function _push_over_sbios_order($order, $channel)
    {
        $ret = array('ret' => 0, 'msg' => '发货成功');
        $ret = $this->_check_an_order($order['Extraparam1'], $order['MerchantRef'], $order['Amount'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $order['MerchantRef'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['MerchantRef'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['Extraparam1'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $order['Amount'],
            'ts' => $order['ts'],
            'status' => 0,
            'extra' => $order['extra'],
            'sign' => $order['sign'],
        );
        $this->_orders->insert($data);
        $order['orderid'] = $order['MerchantRef'];
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 检查悠悠村订单
     * @param array $rsp
     * @param array $user
     * @param string $appkey
     * @return bool
     */
    public function check_an_yyc($rsp, $user, $appkey)
    {
        $yyc = pc_base::load_config('android', 'yyc');
        $keys = array(
            'app_key', 'txn_seq', 'order_id', 'rsp_code', 'txn_time', 'actual_txn_amt', 'time_stamp', 'key'
        );
        $params = array();
        foreach ($rsp as $k => $v) {
            if (in_array($k, $keys)) {
                $params[] = $k . '=' . $v;
            }
        }
        $sign_str = join('&', $params);

        $sign = md5($sign_str . '&key=' . $yyc['PayKey']);

        return $sign == strtolower($rsp['signMsg']);

    }

    /**
     * 检查猎宝订单
     * @param array $data
     * @param string $channel
     * @param array $check_params
     * @return bool
     */
    public function check_an_liebao($data, $channel, $check_params)
    {

        $lb = pc_base::load_config('android', $channel);
        $params = array();
        foreach ($check_params as $k => $v) {
            if ($v != 'sign') {
                $params[$v] = $data[$v];
            }
        }
        $str = http_build_query($params);

        $sign = md5($str . '&appkey=' . $lb['AppKey']);

        return $sign == $data['sign'];
    }

    /**
     * 检查朋友玩订单
     * @param array $data
     * @param string $channel
     * @return bool
     */
    public function check_an_pyw($data, $channel)
    {
        $pyw = pc_base::load_config('android', $channel);
        $str = $pyw['PayKey'] . $data['cp_orderid'] . $data['ch_orderid'] . $data['amount'];
        $sign = md5($str);
        return $sign == $data['sign'];
    }

    /**
     * 检查峰助手订单
     * @param array $data
     * @param string $channel
     * @return bool
     */
    public function check_an_fzs($data, $channel)
    {
        $fzs = pc_base::load_config('android', $channel);
        $str = $data['orderId'] . $data['uid'] . $data['amount'] . $data['extraInfo'] . $fzs['AppKey'];
        $sign = md5($str);
        return $sign == $data['sign'];
    }

    /**
     * 检查天机订单
     * @param array $data
     * @param string $channel
     * @return bool
     */
    public function check_an_tj($order, &$notifyJson)
    {
        $tj = pc_base::load_config('android', 'tj');
        return parseResp($order, $tj['PlatPubKey'], $notifyJson);
    }

    /**
     * 检查极品天机订单
     * @param array $data
     * @param string $channel
     * @return bool
     */
    public function check_an_jptj($order, &$notifyJson)
    {
        $tj = pc_base::load_config('android', 'jptj');
        return parseResp($order, $tj['PlatPubKey'], $notifyJson);
    }

    /**
     * 获取玩家累计充值金额
     * @param array $account
     * @param number $sid
     * @return number
     */
    protected function _get_tx_amount($account, $sid)
    {
        $amount = $this->_tx_amount->get_one(array('account' => $account, 'sid' => $sid));
        if (!$amount) {
            $amount = array('account' => $account, 'sid' => $sid, 'acc_amount' => 0);
            $this->_tx_amount->insert($amount);
        }
        return $amount;
    }

    /**
     * 2级字符串分割
     * @param string $str
     * @param string $delim1
     * @param string $delim2
     * @return array
     */
    protected function _get_str_params($str, $delim1 = '&', $delim2 = '=')
    {
        $params = array();
        $param_str = explode($delim1, $str);
        foreach ($param_str as $k => $v) {
            $tmp = explode($delim2, $v);
            if (isset($tmp[0]) && isset($tmp[1])) {
                $params[$tmp[0]] = $tmp[1];
            }
        }
        return $params;
    }

}
