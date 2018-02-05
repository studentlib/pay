<?php
// function __autoload($class)
// {
//     pc_base::load_app_class($class);
// }
class paygp
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
     * 检查订单是否合法
     * @param string $pay_id 订单ID
     * @param string $order_id 第三方订单ID
     * @param number $amount 金额
     * @param string $channel 渠道
     * @return array
     */
    protected function _check_order($pay_id, $order_id, $channel)
    {
        $channel = strtolower($channel);
        $myorder = $this->_orders_list->get_one("`orderid`='" . $pay_id . "'");
        if (!$myorder) {
            return array('ret' => 5, 'msg' => '订单不是充值中心生成的');
        }

        //注意这里比较金额要乘以兑换比例 每个渠道的兑换比例不同(google 不需要)
//        $config = pc_base::load_config('channels', $channel);
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
     * 发送订单信息到游戏充值代理
     * @param string $ip
     * @param number $port
     * @param array $data
     * @return number
     */
    protected function sendToGS($ip, $port, $data)
    {
        $this->__log("CHARGESERVER RETURN print:" . $data);
        $keys = pc_base::load_config('gs_keys');
        $key = $keys['s' . $data['serverid']];
        $itemids = 0;
        $paytyps = 2;
        $skey = hash_hmac('md5', $data['role_id'] . $data['area_id'] . $data['amount'] . $itemids . $paytyps . $data['currency_num'] . $data['orderid'], $key, TRUE);
        $pkfmt = 'IISSIIIIIfIIIa64a64';
        $upkfmt = 'Isize/Imagic/Stype/Scmd/Ieno/Ipf/Ips';
        $data = pack($pkfmt, 168, 0xF1E2D3C4, 0x01, 0xD805, 0, 0, 0, $data['roleid'], $data['zoneid'], $data['amount'], $itemids, $paytyps, $data['currency_num'], $data['orderid'], $skey);
        $timeout = 5;
        $this->__log("START CONNECT TO CHARGESERVER@" . $ip . ':' . $port);
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
                    $this->__log("CHARGESERVER RETURN:" . $data['eno']);
                    if ($data['eno'] == 0) {
                        return 0;
                    } else {
                        return $data['eno'];
                    }
                    break;
                }
                usleep(20000);
            }
            $this->__log("WAIT CHARGE SERVER TIMEOUT");
        } else {
            $this->__log("CHARGE SERVER OFFLINE");
            return 1;
        }
        return -1;
    }

    protected function __log($str)
    {
        echo date('Y-m-d H:i:s') . ' ' . $str, "\n";
    }
}