<?php
// function __autoload($class)
// {
//     pc_base::load_app_class($class);
// }
class pay
{
    /**
     * @var Redis
     */
    protected $_redis;

    /**
     * @var orders_model
     */
    protected $_orders;
    /**
     * @var account_model
     */
    protected $_account;
    /**
     * @var orders_list_model
     */
    protected $_orders_list;
    /**
     * @var log_model
     */
    protected $_log;
    /**
     * @var tickets_model
     */
    protected $_tickets;

    /**
     * 获取redis 链接
     * @param string $ip
     * @param int $index
     * @param int $port
     * @return Redis
     */
    protected function getRedis($ip, $index, $port = 6379)
    {
        $redis = new Redis();
        $redis->connect($ip, $port);
        $redis->select($index);
        return $redis;
    }

    /**
     * 获取redis配置
     * @param int $sid
     * @return array
     */
    protected function getRedisConfig($sid)
    {
        $servers = $this->get_server_config();
        $server = array();
        if (isset($servers[$sid])) {
            $server = $servers[$sid];
        }
        if (!count($server)) {
            exit('NO ACCESS');
        }
        return $server;
    }

    /**
     * 获取ios正版以及越狱渠道配置
     * @param string $channel
     * @return array
     */
    protected function _getPayConfig($channel)
    {
        $ret = array();
        $channel = strtolower($channel);
        if (file_exists(CACHE_PATH . "channels" . DIRECTORY_SEPARATOR . $channel . "_Pay.xml")) {
            $xml = simplexml_load_file(CACHE_PATH . "channels" . DIRECTORY_SEPARATOR . $channel . "_Pay.xml");
            foreach ($xml->row as $v) {
                foreach ($v->attributes() as $k1 => $v1) {
                    $arr[(string)$k1] = (string)$v1;
                }
                $ret[$arr['ID']] = $arr;
            }
        }
        return $ret;
    }

    /**
     * 获取android渠道对应配置
     * @param string $channel
     * @return array
     */
    protected function _getAndRoidPayConfig($channel)
    {
        $ret = array();
        $channel = strtolower($channel);
        if (file_exists(CACHE_PATH . "android" . DIRECTORY_SEPARATOR . $channel . "_Pay.xml")) {
            $xml = simplexml_load_file(CACHE_PATH . "android" . DIRECTORY_SEPARATOR . $channel . "_Pay.xml");
            foreach ($xml->row as $v) {
                foreach ($v->attributes() as $k1 => $v1) {
                    $arr[(string)$k1] = (string)$v1;
                }
                $ret[$arr['ID']] = $arr;
            }
        }
        return $ret;
    }

    /**
     * 获取海外渠道对应配置
     * @param string $channel
     * @return array
     */
    protected function _getOverSeaPayConfig($channel)
    {
        $ret = array();
        $channel = strtolower($channel);
        if (file_exists(CACHE_PATH . "oversea" . DIRECTORY_SEPARATOR . $channel . "_Pay.xml")) {
            $xml = simplexml_load_file(CACHE_PATH . "oversea" . DIRECTORY_SEPARATOR . $channel . "_Pay.xml");
            foreach ($xml->row as $v) {
                foreach ($v->attributes() as $k1 => $v1) {
                    $arr[(string)$k1] = (string)$v1;
                }
                $ret[$arr['ID']] = $arr;
            }
        }
        return $ret;
    }

    /**
     * 从serverlist获取服务器配置
     * @return array
     */
    protected function get_server_config()
    {
        $opts = array('http' => array('method' => "GET", 'timeout' => 10));
        $option = stream_context_create($opts);
        $system = pc_base::load_config('system');
        $file = file_get_contents($system['server_list'], null, $option);
        $servers = simplexml_load_string($file);
        $ret = array();
        foreach ($servers->row as $v) {
            foreach ($v->attributes() as $k1 => $v1) {
                $arr[(string)$k1] = (string)$v1;
            }
            $ret[$arr['ServerType']] = $arr;

        }
        return $ret;
    }

    /**
     * 记录日志
     * @param array $order
     * @param number $status
     * @param string $channel
     */
    protected function _logOrder($order, $status = 0, $channel = '')
    {
        foreach ($order as $k => &$v) {
            if (preg_match("/[\x7f-\xff]/", $v)) {
                $v = urlencode($v);
            }
        }
        $arr = array(
            'orderid' => isset($order['orderid']) ? $order['orderid'] : $order['order_id'],
            'content' => json_encode($order),
            'status' => $status,
            'url' => $_SERVER['REQUEST_URI'],
            'remote' => $_SERVER['REMOTE_ADDR'],
            'serverip' => $_SERVER['SERVER_ADDR'],
            'channel' => $channel,
        );
        $this->_log->insert($arr);
    }

    /**
     * 添加pp渠道的订单到队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_pp_like_order($order, $channel = 'pp')
    {
        $ret = array('ret' => 0, 'msg' => '发货成功');
        $ret = $this->_check_order($order['billno'], $order['order_id'], $order['amount'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $this->_logOrder($order, $ret['ret'], $channel);
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $order['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['order_id'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['billno'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $order['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
            'app_id' => $order['app_id'],
            'uuid' => $order['uuid'],
            'sign' => $order['sign'],
        );

        $this->_orders->insert($data);
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加xy渠道的订单到队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_xy_order($order, $channel)
    {
        $ret = array('ret' => 0, 'msg' => '发货成功');
        $ret = $this->_check_order($order['extra'], $order['orderid'], $order['amount'], $channel);
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
     * 添加itools渠道的订单到队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_itools_order($order, $channel)
    {
        $ret = $this->_check_order($order['order_id_com'], $order['order_id'], $order['amount'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }

        $data = array('channel' => 'itools',
            'serverid' => $ret['myorder']['serverid'],
            'account' => $order['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['order_id'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['order_id_com'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $order['amount'],
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
     * 添加快用渠道的订单到队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_ky_order($order, $channel)
    {
        $publicKey = Init::public_key;
        $publicKey = Rsa::instance()->convert_publicKey($publicKey);
        $notify_data = base64_decode($order['notify_data']);
        $decode_notify_data = Rsa::instance()->publickey_decodeing($notify_data, $publicKey);
        parse_str($decode_notify_data);
        if ($payresult != 0) {
            return array('ret' => '1', 'msg' => 'payresult_not_zero');
        }
        $ret = $this->_check_order($order['dealseq'], $order['orderid'], $fee, $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $order['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['orderid'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['dealseq'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $fee,
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
     * 添加同步推渠道的订单到队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_tbt_order($order, $channel)
    {
        $ret = $this->_check_order($order['trade_no'], $order['tborder'], $order['amount'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $order['tborder'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['tborder'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['trade_no'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $ret['myorder']['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
            'sign' => $order['sign'],
        );
        $order['orderid'] = $order['tborder'];
        $this->_orders->insert($data);
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加91百度渠道的订单到队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_nd_order($order, $channel)
    {
        $ret = $this->_check_order($order['CooOrderSerial'], $order['ConsumeStreamId'], $order['OrderMoney'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $order['ConsumeStreamId'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }

        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['ConsumeStreamId'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['CooOrderSerial'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $order['OrderMoney'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
            'sign' => $order['Sign'],
        );
        $this->_orders->insert($data);
        $order['orderid'] = $order['ConsumeStreamId'];
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加iapple渠道的订单到队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_iapple_order($order, $channel)
    {
        $ret = $this->_check_order($order['gameExtend'], $order['transaction'], $order['amount'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $order['transaction'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['transaction'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['gameExtend'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $order['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
            'sign' => $order['_sign'],
        );
        $this->_orders->insert($data);
        $order['orderid'] = $order['transaction'];
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加xx渠道的订单到队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_xx_order($order, $channel)
    {
        $ret = $this->_check_order($order['serialNumber'], $order['trade_no'], $order['money'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $data['orderid'] = $data['trade_no'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['trade_no'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['serialNumber'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $order['money'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
            'sign' => $order['sign'],
        );
        $this->_orders->insert($data);
        $order['orderid'] = $order['trade_no'];
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加天机渠道的订单到队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_tj_order($order, $channel)
    {
        $ret = $this->_check_order($order['cporderid'], $order['transid'], $order['money'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $order['transid'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
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
     * 添加海马玩渠道的订单到队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_hmw_order($order, $channel)
    {
        $ret = $this->_check_order($order['out_trade_no'], $order['out_trade_no'], $order['total_fee'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $order['out_trade_no'];
            $data['subject'] = urlencode($data['subject']);
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $ret['myorder']['serverid'],
            'account' => $ret['myorder']['account'],
            'uid' => $ret['account']['ID'],
            'orderid' => $order['out_trade_no'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['out_trade_no'],
            'itemid' => $ret['myorder']['itemid'],
            'amount' => $order['total_fee'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
            'sign' => $order['sign'],
        );
        $this->_orders->insert($data);
        $order['orderid'] = $order['out_trade_no'];
        $data['subject'] = urlencode($data['subject']);
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 添加ios订单到验证队列
     * @param array $myorder
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_app_store_order($myorder, $order, $channel)
    {
//         苹果官方 transaction_id 短时间内会重复不可靠 用平台自己的订单号
        $ret = $this->_check_order($myorder['orderid'], $myorder['orderid'], $myorder['amount'], $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $myorder['orderid'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array('channel' => $channel,
            'serverid' => $myorder['serverid'],
            'account' => $myorder['account'],
            'uid' => $myorder['uid'],
            'orderid' => $myorder['orderid'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $myorder['orderid'],
            'itemid' => $myorder['itemid'],
            'product_id' => $order['product_id'],
            'amount' => $myorder['amount'],
            'time' => $ret['myorder']['ctime'],
            'ts' => time(),
            'status' => 0,
            'sign' => $order['sign'],
        );
        $this->_orders->insert($data);
        $order['orderid'] = $myorder['orderid'];
        $this->_logOrder($order, 0, $channel);
        return $ret;
    }

    /**
     * 检查订单是否合法
     * @param string $pay_id 订单ID
     * @param string $order_id 第三方订单ID
     * @param number $amount 金额
     * @param string $channel 渠道
     * @return array
     */
    protected function _check_order($pay_id, $order_id, $amount, $channel)
    {
        $channel = strtolower($channel);
        $config = pc_base::load_config('channels', $channel);
        $myorder = $this->_orders_list->get_one("`orderid`='" . $pay_id . "'");
        if (!$myorder) {
            return array('ret' => 5, 'msg' => '订单不是充值中心生成的');
        }
//        file_put_contents('appstore.log', print_r($myorder,1).$amount.print_r($config,1),FILE_APPEND);
        //注意这里比较金额要乘以兑换比例 每个渠道的兑换比例不同
//        if (isset($myorder['amount']) && $myorder['amount'] != $amount * $config['Ratio']) {
//            return array('ret' => 8, 'msg' => '订单金额和对应商品价格不匹配');
//        }

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
        $conditon = sprintf("`orderid`='%s' and `uid`='%s'", $order_id, $account['ID']);
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
     * 生成签名
     * @param array $params
     * @param string $prikey
     * @return string
     */
    protected function _gen_safe_sign($params, $prikey)
    {
        ksort($params);
        $query_string = array();
        foreach ($params as $key => $val) {
            if ($key == "sig" ||
                $key == "sign") {
                continue;
            }
            array_push(
                $query_string,
                $key . '=' . $val);
        }
        $query_string = join('&', $query_string);
        $sign = md5($prikey . $query_string);
        return $sign;
    }

    /**
     * 检查同步推订单是否合法
     * @param array $data
     * @return bool
     */
    public function check_tbt($data)
    {
        $tbt = pc_base::load_config('channels', 'tbt');
        $paramArray = array(
            'source' => '',
            'trade_no' => '',
            'amount' => '',
            'partner' => '',
            'paydes' => '',//SDK2.4新增字段，传支付说明信息
            'debug' => 0,//是否是测试模式
            'tborder' => '',//同步平台订单号
            'sign' => '',
        );
        //参数赋值
        foreach ($paramArray as $key => $v) {
            if (isset($data[$key]))
                $paramArray[$key] = $data[$key];
        }
        $str = 'source=' . $paramArray['source'] . '&trade_no=' . $paramArray['trade_no'] . '&amount=' . $paramArray['amount'] . '&partner=' . $paramArray['partner'];
        //生成 sign 加密串 参数顺序要保持一致
        if (isset($data['paydes']) && isset($data['debug'])) {//SDK 2.4 版本以上(含2.4)paydes 需加入验证
            $str = $str . '&paydes=' . $paramArray['paydes'] . '&debug=' . $paramArray['debug'];
        }
        if (isset($data['tborder'])) {//sdk 3.1版本（含3.1）tborder需加入验证
            $str = $str . '&tborder=' . $paramArray['tborder'];
        }
        $str .= '&key=' . $tbt['PayKey'];
        $sign = md5($str);
//        file_put_contents('log.log', json_encode($data)."\n".$tbt['PayKey']."\n$str\n".$sign.'=='.$paramArray['sign']."\n",FILE_APPEND);
//        file_put_contents('log.log', json_encode($paramArray)."\n".$tbt['PayKey']."\n",FILE_APPEND);
        return $sign == $paramArray['sign'];
    }

    /**
     * 检查pp订单是否合法
     * @param array $notify_data
     * @return bool
     */
    public function chk_pp($notify_data)
    {
        $privatedata = $notify_data['sign'];
        error_log(date("Y-m-d h:i:s") . " " . serialize($privatedata) . "\r\n", 3, 'rsa.log');
        $privatebackdata = base64_decode($privatedata);
//        error_log(date("Y-m-d h:i:s")."base64_decode ".serialize($privatebackdata)."\r\n",3,'rsa.log');
        $MyRsa = new MyRsa();
        $data = $MyRsa->publickey_decodeing($privatebackdata, MyRsa::public_key);
//        error_log(date("Y-m-d h:i:s")."publickey_decodeing ".$data."\r\n",3,'rsa.log');
        $rs = json_decode($data, true);
//        error_log(date("Y-m-d h:i:s")."rs ".serialize($rs)."\r\n",3,'rsa.log');
        if (empty($rs) || empty($notify_data)) return false;
        //解密出来的数据和接收到的明文数据对比
        if ($rs["billno"] == $notify_data['billno'] && $rs["amount"] == $notify_data['amount'] && $rs["status"] == $notify_data['status']) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *  检查xx订单是否合法
     * @param array $data
     * @return boolean
     */
    public function check_xx($data)
    {
        $xx = pc_base::load_config('channels', 'xx');
        $sign = md5($data['serialNumber'] . $data['money'] . $data['status'] . $data['t'] . $xx['PayKey']);
        return $sign == $data['sign'];
    }

    /**
     * 检查爱思订单是否合法
     * @param array $notify_data
     * @return boolean
     */
    public function chk_i4($notify_data)
    {
        $privatedata = $notify_data['sign'];
//        error_log(date("Y-m-d h:i:s").": ".serialize($privatedata)."\r\n",3,'irsa.log');

        $privatebackdata = base64_decode($privatedata);
//        error_log(date("Y-m-d h:i:s")."base64_decode: ".serialize($privatebackdata)."\r\n",3,'irsa.log');
        $MyRsa = new IMyRsa();
        // MyRsa.public_key 替换成自己的公钥（登录我们后台，点击顶部导航栏的开发者中心提取）
        //解密出来的数据
        $data = $MyRsa->rsa_decrypt($privatebackdata, IMyRsa::public_key);
//        error_log(date("Y-m-d h:i:s")."publickey_decodeing: ".$data."\r\n",3,'irsa.log');

        //$rs = json_decode($data,true);
        //error_log(date("Y-m-d h:i:s")."rs ".serialize($rs)."\r\n",3,'rsa.log');
        //if(empty($rs)||empty($notify_data))return false;
        //将解密出来的数据转换成数组
        foreach (explode('&', $data) as $val) {
            $arr = explode('=', $val);
            $dataArr[$arr[0]] = $arr[1];
        }
//        error_log(date("Y-m-d h:i:s")."dataArr\t\t".var_export($dataArr, true)."\r\n",3,'irsa.log');
        //验证
        if ($dataArr["billno"] == $notify_data['billno'] && $dataArr["amount"] == $notify_data['amount'] && $dataArr["status"] == $notify_data['status']) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 检查91百度订单是否合法
     * @param unknown $order
     * @return bool
     */
    public function chk_nd($order)
    {
        $AppId = $order['AppId'];//应用ID
        $Act = $order['Act'];//操作
        $ProductName = $order['ProductName'];//应用名称
        $ConsumeStreamId = $order['ConsumeStreamId'];//消费流水号
        $CooOrderSerial = $order['CooOrderSerial'];//商户订单号
        $Uin = $order['Uin'];//91帐号ID
        $GoodsId = $order['GoodsId'];//商品ID
        $GoodsInfo = $order['GoodsInfo'];//商品名称
        $GoodsCount = $order['GoodsCount'];//商品数量
        $OriginalMoney = $order['OriginalMoney'];//原始总价（格式：0.00）
        $OrderMoney = $order['OrderMoney'];//实际总价（格式：0.00）
        $Note = $order['Note'];//支付描述
        $PayStatus = $order['PayStatus'];//支付状态：0=失败，1=成功
        $CreateTime = $order['CreateTime'];//创建时间
        $Sign = $order['Sign'];//91服务器直接传过来的sign
        if ($Act != 1) {
            $Result["ErrorCode"] = "3";//注意这里的错误码一定要是字符串格式
            $Result["ErrorDesc"] = urlencode("Act无效");
            return $Result;
        }
        $nd = pc_base::load_config('channels', 'nd');
        //如果传过来的应用ID开发者自己的应用ID不同，那说明这个应用ID无效
        if ($nd['AppId'] != $AppId) {
            $Result["ErrorCode"] = "2";//注意这里的错误码一定要是字符串格式
            $Result["ErrorDesc"] = urlencode("AppId无效");
            return $Result;
        }
        //按照API规范里的说明，把相应的数据进行拼接加密处理
        $sign_check = md5($nd['AppId'] . $Act . $ProductName . $ConsumeStreamId . $CooOrderSerial . $Uin . $GoodsId . $GoodsInfo . $GoodsCount . $OriginalMoney . $OrderMoney . $Note . $PayStatus . $CreateTime . $nd['AppKey']);
        if ($sign_check == $Sign) {//当本地生成的加密sign跟传过来的sign一样时说明数据没问题
            $Result["ErrorCode"] = "1";//注意这里的错误码一定要是字符串格式
            $Result["ErrorDesc"] = urlencode("接收成功");
            return $Result;
        } else {
            $Result["ErrorCode"] = "5";//注意这里的错误码一定要是字符串格式
            $Result["ErrorDesc"] = urlencode("Sign无效");
            return $Result;
        }
    }

    /**
     * 检查iapple订单是否合法
     * @param array $data
     * @param array $params
     * @return boolean
     */
    public function check_iapple($data, $params)
    {
        $iapple = pc_base::load_config('channels', 'iapple');
        $sign = $data['_sign'];
        unset($data['_sign']);
        ksort($data);
        $str = '';
        foreach ($data as $k => $v) {
            $str .= $k . '=' . $v . "&";
        }
        $str = substr($str, 0, -1);
        $mysign = md5(md5($str) . $iapple['PayKey']);
        return $sign === $mysign;
    }

    /**
     * 检查天机订单是否合法
     * @param array $order
     * @param array $notifyJson
     * @return boo
     */
    public function check_tj($order, &$notifyJson)
    {
        $tj = pc_base::load_config('channels', 'tj');
        return parseResp($order, $tj['PlatPubKey'], $notifyJson);
    }

    /**
     * 检查海马玩订单是否合法
     * @param array $order
     * @param string $channel
     * @return bool
     */
    public function check_hmw($order, $channel)
    {
        $hmw = pc_base::load_config('channels', $channel);
        static $keys = array('notify_time', 'appid', 'out_trade_no', 'total_fee', 'subject', 'body', 'trade_status');
        $params = array();

        foreach ($keys as $k => $v) {
            if (isset($order[$v])) {
                $params[] = $v . '=' . urlencode($order[$v]);
            }
        }
        $str = join('&', $params);
        $sign = md5($str . $hmw['AppKey']);
        return $sign == $order['sign'] && $order['trade_status'] == 1;
    }

    /**
     * 检查google订单(签名)
     * @param array $rsp
     * @param array $user
     * @param string $appkey
     * @return bool
     */
    protected function _check_google_order($data, $sign,$channel)
    {
        $config = pc_base::load_config('channels', $channel);
        $inapp_purchase_data = $data;//原始订单信息json
        $inapp_data_signature = $sign;//签名
        $google_public_key = $config['PayKey'];
        $public_key = "-----BEGIN PUBLIC KEY-----\n" . chunk_split($google_public_key, 64, "\n") . "-----END PUBLIC KEY-----";

        $public_key_handle = openssl_get_publickey($public_key);

        $result = openssl_verify($inapp_purchase_data, base64_decode($inapp_data_signature), $public_key_handle, OPENSSL_ALGO_SHA1);

        return $result;
        if ($result == 1) {
            return 'success';
        } elseif ($result == 0) {
            return 'failure';
        } else {
            return 'other';
        }
    }

    /*
     * 验证google订单（查询订单信息）
     *
     */
    protected function _check_google_order_two($data, $channel)
    {
        usleep(1000);
        $config = pc_base::load_config('channels', $channel);
        include_once(PHPCMS_PATH.'/phpcms/modules/paycenter/classes/google-api-php-client/src/Google_Client.php');
        include_once(PHPCMS_PATH.'/phpcms/modules/paycenter/classes/google-api-php-client/src/contrib/Google_AndroidpublisherService.php');
        $ANDROIDUsertoken = $data;
        $user_token = json_decode($ANDROIDUsertoken, true);
        $CLIENT_ID = $config['CLIENT_ID'];
        $SERVICE_ACCOUNT_NAME = $config['SERVICE_ACCOUNT_NAME'];
        $KEY_FILE = PHPCMS_PATH.'caches/keys/key.p12';
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
//        if ($res['purchaseState'] != 0) {
//            $str = array('status' = 'failed', 'msg' = '没有付款');
//        }
//        if ($res['consumptionState'] != 1) {
//            $str = array('status' => 'failed', 'msg' => '没有使用道具');
//        }
//        if ($['developerPayload'] == $self_order) {
//            $str = array('status' => 'failed', 'msg' => '订单不正确');
//        }
//        return $str;
        //客户端汇报上来的订单处理状态确认 purchaseState        订单的购买状态。可能的值为 0（已购买）、1（已取消）或者 2（已退款）
        if (0 == $res['purchaseState'] && (1 == $res['consumptionState'])) {
            return true;
        }
        return false;
    }

    /*
     * 添加google充值订单到充值队列
     */
    protected function _push_gp_order($order, $channel)
    {
        $ret = array('ret' => 0, 'msg' => '发货成功');
        //充值表pay 的信息
        $items = $this->_getPayConfig($channel);
        //检查订单是否合法
        $ret = $this->_check_order($order['developerPayload'], $order['orderId'],'', $channel);
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $order['developerPayload'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }
        $data = array(
            'channel' => $channel,//渠道
            'serverid' => $ret['myorder']['serverid'],//游戏服务器编号
            'account' => $ret['myorder']['account'],//游戏账号
            'uid' => $ret['account']['ID'],
            'orderid' => $ret['myorder']['orderid'],//充值中心生成的订单号
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['orderId'],//平台订单号
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
        $order['orderid'] = $order['developerPayload'];
        $this->_logOrder($order, 0, $channel);
        return $ret;
    }

    /**
     * 检查appstore订单是否已经处理过
     * @param array $orders
     */
    public function check_appstore_orders($orders)
    {
        set_time_limit(30 * count($orders));

        foreach ($orders as $k => $v) {
            if ($v) {
                $myorder = $this->_orders_list->get_one("`orderid`='" . $k . "'");
                if (isset($myorder['channel']) && $myorder['channel'] == 'appStore') {
                    if ($this->_check_app_store($myorder, $v)) {
                        $this->_orders_list->update(array('ptime' => time()), "`orderid`='" . $k . "'");
                    }
                }
            }
        }
    }

    /**
     * 解析appstore小票
     * @param string $ticket
     * @param string $underline
     * @return array
     */
    private function __parseTicket($ticket, $underline = FALSE)
    {
        $mc = array();
        $ret = array();
        if (preg_match_all('/"(.*)" \= "(.*)"/', base64_decode($ticket), $mc)) {
            if (isset($mc[1]) && isset($mc[2]) && is_array($mc[1]) && is_array($mc[2])) {
                $count = count($mc[1]);
                for ($i = 0; $i < $count; ++$i) {
                    $key = $mc[1][$i];
                    if ($underline == TRUE) {
                        $key = str_replace('-', '_', $key);
                    }
                    $ret[$key] = $mc[2][$i];
                }
            }
        }
        return $ret;
    }

    /**
     * 检查订单小票是否合法是否重复
     * @param array $myorder
     * @param string $ticket
     */
    protected function _check_app_store($myorder, $ticket)
    {
        $ticket_data = $this->__parseTicket($ticket);
        $local_ticket = $this->__parseTicket($ticket_data['purchase-info'], TRUE);
        if (!$local_ticket) {
            file_put_contents('app_store.log', date('Y-m-d H:i:s') . ' WRONG_TICKET:' . $ticket . "\n" . $_SERVER['REMOTE_ADDR'] . ":" . $_SERVER['HTTP_USER_AGENT'] . "\n", FILE_APPEND);
            return TRUE;
        }
        $ticket_md5 = md5($ticket);
        $condition = array('ticket_md5' => $ticket_md5);
        $myticket = $this->_tickets->get_one($condition);
        $otable = $this->_tickets->getTable();
        $this->_tickets->setTable('trans_record');

        $trans_cond = array('transid' => $local_ticket['transaction_id']);
        $record = $this->_tickets->get_one($trans_cond);
        if ($record) {
            $this->_tickets->insert(array('transid' => $local_ticket['transaction_id'],
                    'account' => $myorder['account'],
                    'orderid' => $myorder['orderid'],
                    'uid' => $myorder['uid'],
                )
            );
            if (!$myticket) {

                $appticket = array(
                    'orderid' => $myorder['orderid'],
                    'ticket' => $ticket,
                    'orderinfo' => json_encode($myorder),
                    'ticket_md5' => $ticket_md5,
                    'status' => -2
                );
                $this->_tickets->setTable($otable);
                $this->_tickets->insert($appticket);
            }
            $this->_tickets->setTable($otable);
            return TRUE;
        } else {
            $this->_tickets->insert(array('transid' => $local_ticket['transaction_id'],
                    'account' => $myorder['account'],
                    'orderid' => $myorder['orderid'],
                    'uid' => $myorder['uid'],
                )
            );
            $this->_tickets->setTable($otable);
        }
        if (is_array($myticket) && array_key_exists('status', $myticket) && $myticket['status'] == 0 || $myticket['status'] == -1 || $myticket['status'] == -2) {
            return TRUE;
        }
        $appticket = array(
            'orderid' => $myorder['orderid'],
            'ticket' => $ticket,
            'orderinfo' => json_encode($myorder),
            'ticket_md5' => $ticket_md5,
        );
        $this->_tickets->insert($appticket);
        return TRUE;
    }

    /**
     * 发送订单信息到游戏充值代理
     * @param string $ip
     * @param number $port
     * @param array $data
     * @return number
     */
    protected function sendToGS($ip, $port, $data)
    {
        //      $this->__log("CHARGESERVER RETURN print:".$data);
        $keys = pc_base::load_config('gs_keys');
        $key = $keys['s' . $data['serverid']];
        $itemids = 0;
        $paytyps = 2;
        $skey = hash_hmac('md5', $data['role_id'] . $data['area_id'] . $data['amount'] . $itemids . $paytyps . $data['currency_num'] . $data['orderid'], $key, TRUE);
        #return $data['serverid'];//.$data['amount'].$data['itemid'].$data['paytype'].$data['gold'].$data['orderid'];
        $pkfmt = 'IISSIIIIIfIIIa64a64';
        $upkfmt = 'Isize/Imagic/Stype/Scmd/Ieno/Ipf/Ips';
        $data = pack($pkfmt, 168, 0xF1E2D3C4, 0x01, 0xD805, 0, 0, 0, $data['roleid'], $data['zoneid'], $data['amount'], $itemids, $paytyps, $data['currency_num'], $data['orderid'], $skey);
//         $skey=hash_hmac('md5', $data['uid'].$data['serverid'].$data['amount'].$data['itemid'].$data['orderid'], $key,TRUE);
//         $pkfmt='IISSIIIIIfIa64a64II';
//         $upkfmt='Isize/Imagic/Stype/Scmd/Ieno/Ipf/Ips';
//         $data=pack($pkfmt,168,0xF1E2D3C4,0x01,0xD805,0,0,0,$data['uid'],$data['serverid'],$data['amount'],$data['itemid'],$data['orderid'],$skey,$data['paytype'],$data['gold']);
        $timeout = 5;
        //      $this->__log("START CONNECT TO CHARGESERVER@".$ip.':'.$port);
        $sock = fsockopen($ip, (int)$port, $errno, $errstr, $timeout);
        stream_set_blocking($sock, 0);
        if ($sock) {
            fwrite($sock, $data, 168);
            $i = 0;
            $count = 50;
            while ($i++ < $count) {
                $rdata = fread($sock, 1024);
                if ($rdata) {
                    $data = unpack($upkfmt, $rdata);
//                    $this->__log("CHARGESERVER RETURN:" . $data['eno']);
                    if ($data['eno'] == 0) {
                        return 0;
                    } else {
                        return $data['eno'];
                    }
                    break;
                }
                usleep(20000);
            }
            //        $this->__log("WAIT CHARGE SERVER TIMEOUT");
        } else {
            //       $this->__log("CHARGE SERVER OFFLINE");
            return 1;
        }
        return -1;
    }

    protected function __log($str)
    {
        echo date('Y-m-d H:i:s') . ' ' . $str, "\n";
    }
}