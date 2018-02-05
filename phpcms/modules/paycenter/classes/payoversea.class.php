<?php
pc_base::load_app_class('pay', 'paycenter', 0);

class payoversea extends pay
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
     * 检查海外版订单是否合法
     * @param string $pay_id 订单ID
     * @param string $order_id 第三方订单ID
     * @param number $amount 金额
     * @param string $channel 渠道
     * @return array
     */
    protected function _check_ov_order($pay_id, $order_id, $amount, $channel)
    {
        $channel = strtolower($channel);
        $config = pc_base::load_config('oversea', $channel);
        $myorder = $this->_orders_list->get_one("`orderid`='" . $pay_id . "'");
        if (!$myorder) {
            return array('ret' => 5, 'msg' => '订单不是充值中心生成的');
        }
        //注意这里比较金额要乘以兑换比例 每个渠道的兑换比例不同
        if (isset($myorder['amount']) && $myorder['amount'] != intval($amount * $config['Ratio'])) {
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
     * 韩国onestore 订单校验
     */
    protected function check_onestore_order($data){
        $data=json_decode($data,true);
        $url='https://iapdev.tstore.co.kr/digitalsignconfirm.iap';
        //替换appid
        $rdata=array('txid'=>$data['result']['txid'],'appid'=>'OA00719909','signdata'=>stripslashes($data['result']['receipt']));
        $ldata=json_encode($rdata);
        file_put_contents('onestone.log',date('Y-m-d H:i:s').'---$ldata:'.$ldata.PHP_EOL,FILE_APPEND);
        $headers = array(
            "Content-type: application/json;charset='utf-8'",
        );
        $ch = curl_init($url);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt ($ch, CURLOPT_POST, true);
        curl_setopt ($ch, CURLOPT_POSTFIELDS, $ldata);
        curl_setopt ($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $ret = curl_exec($ch);
        curl_close($ch);
        file_put_contents('onestone.log',date('Y-m-d H:i:s').'$ret---curl:'.$ret.PHP_EOL,FILE_APPEND);
        return json_decode($ret,true);
    }
    /**
     * 添加onestore订单到充值队列
     * @param array $order
     * @param string $channel
     * @return array
     */
    protected function _push_onestore_order($order,$status,$channel)
    {
        $ret=$this->_check_an_order($order,$status['tid'], $status['charge_amount'],$channel);
        if(isset($ret['ret'])&&$ret['ret'])
        {
            $this->_logOrder($ret,$ret['ret'],$channel);
            return $ret;
        }
        $data=array(
            'channel'=>$channel,
            'serverid'=>$ret['myorder']['serverid'],
            'account'=>$ret['myorder']['account'],
            'uid'=>$ret['account']['ID'],
            'orderid'=>$order,
            'url'=>$_SERVER['REQUEST_URI'],
            'billno'=>$status['TID'],
            'itemid'=>$ret['myorder']['itemid'],
            'amount'=>$ret['myorder']['amount'],
            'time'=>$ret['myorder']['ctime'],
            'ts'=>time(),
            'status'=>0,
            'sign'=>'',
        );
        $this->_orders->insert($data);
        $this->_logOrder($data,0,$channel);
        return array('ret'=>0,'msg'=>'发货成功');
    }

    /*
     * 越南签名验证  在充值回调文件中设置保密
     */
    private function parse_signed_request($signed_request, $secret)
    {
        list($encoded_sig, $payload) = explode('.', $signed_request, 2);
        // decode the data
        $sig = base64_decode(strtr($encoded_sig, '-_', '+/'));
        $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

        if (strtoupper($data['algorithm']) !== 'HMAC-SHA256') {
            error_log('Unknown algorithm. Expected HMAC-SHA256');
            return false;
        }
        // check sig
        $expected_sig = hash_hmac('sha256', $payload, $secret, $raw = true);
        if ($sig !== $expected_sig) {
            return false;
        }
        return $data;
    }
    /*
     * 查找角色是否存在(越南官网充值)
     */
    private function find_role($data)
    {
        $servers = $this->get_server_config();
        if (!isset($servers[$data['area_id']])) {
            $ret = array('ret' => 1, 'msg' => '游戏服不存在');
            return ($ret);
        }
        /**
         * 充值中心没有角色信息需要去对应的游戏数据库查询一次
         */
        if (!$this->_account->changeConnection($data['area_id'])) {
            $ret = array('ret' => 2, 'msg' => '数据库错误');
            return ($ret);
        }
        $account = $this->_account->select(array('AccountName' => strtoupper('vt' . $data['user_id'])));
        if (count($account) > 1) {
            /**
             * 查到多个结果说明是合过服的特殊处理
             */
            foreach ($account as $k => $user) {
                if (isset($user['ServerType']) && $user['ServerType'] == $data['area_id']) {
                    $account = $user;
                    break;
                }
            }
        } else if (isset($account[0]) && count($account[0])) {
            $account = $account[0];
        }
        if (!$account) {
            $ret = array('ret' => 3, 'msg' => '玩家不存在', 'desc' => 'failure', 'status' => 'false');
            return ($ret);
        }
        return (array('ret' => 6, 'msg' => '玩家信息正确'));
    }

    /**
     * 检查订单是否合法
     * @param array $orders
     * @param array $params
     * @param string $channel
     * @return bool
     */
    protected function _check_mface_order($orders, $params, $channel)
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

    /**
     *  生成官网充值签名
     * @param array $data 订单信息
     * @param string $prikey 密钥
     * @param array $check_params 加密使用的参数
     * @return string
     */
    protected function dnyoff($data, $prikey, $check_params)
    {
        $params = array();
        foreach ($check_params as $k => $v) {
            if ($v == "sign") {
                continue;
            }
            if (isset($data[$v])) {
                $params[$v] = $data[$v];
            }
        }
        //ksort($params);
        $src_str = join('', $params);
        $sign = md5($src_str . $prikey);
        return $sign;
    }

    /**
     * 东南亚官网平台订单添加到队列
     * @param array $order
     * @param unknown $channel
     * @return array
     */
    protected function _push_over_dnyoff_order($order, $channel)
    {
        if (isset($ret['ret']) && $ret['ret']) {
            $order['orderid'] = $order['merchantref'];
            $this->_logOrder($order, $ret['ret'], $channel);
            return $ret;
        }

        $data = array('channel' => $channel,
            'app_id' => $order['appid'],
            'serverid' => $order['zoneid'],
            'account' => 'DNYSB' . $order['idn'],
            'uid' => $order['roleid'],
            'orderid' => $order['merchantref'],
            'url' => $_SERVER['REQUEST_URI'],
            'billno' => $order['merchantref'],
            'amount' => $order['amount'],
            'itemid' => $order['amount'],
            'glod' => $order['currency_num'],
            'ts' => $order['time'],
            'time' => date('Y-m-d H:i:s', $order['time']),
            'status' => 0,
            'extra' => $order['extra'],
            'sign' => $order['sign'],
            'status' => $order['status'],
        );
        $this->_orders->insert($data);
        $order['orderid'] = $order['merchantref'];
        $this->_logOrder($order, 0, $channel);
        return array('ret' => 0, 'msg' => '发货成功');
    }

    /**
     * 越南平台订单添加到队列
     * @param array $order
     * @param unknown $channel
     * @return array
     */
    protected function _push_over_soha_order($order, $channel)
    {

        $data = array('channel' => $channel,
            'app_id' => $order['app_id'],
            'serverid' => $order['area_id'],
            'account' => 'vt' . $order['user_id'],
            'uid' => $order['role_id'],
            'itemid' => $order['itemID'],
            'gold' => $order['gold'],
            'orderid' => $order['order_id'],
            'url' => json_encode($order),
            'billno' => $order['order_id'],
            'amount' => $order['amount'],
            'product_id' => $order['order_info'],
            'ts' => $order['time'],
            'time' => date('Y-m-d H:i:s', $order['time']),
            'status' => $order['status'],
            'extra' => $order['extra'],
            'sign' => $order['signed_request'],
        );
        $this->_orders->insert($data);
        $order['orderid'] = $order['order_id'];
        $this->_logOrder($order, 0, $channel);
    }

    /*
     * 处理成功的订单加入orders_list
     */
    public function order_insert_orders_list($order, $channel)
    {
        $data = array(
            'channel' => $channel,
            'serverid' => $order['area_id'],
            'account' => $order['user_id'],
            'uid' => $order['role_id'],
            'orderid' => $order['order_id'],
            'amount' => $order['amount'],
            'product_id' => $order['order_info'],
            'ptime' => date('Y-m-d H:i:s', time()),
            'ctime' => date('Y-m-d H:i:s', $order['time']),
        );
        $this->_orders_list->insert($data);
        $this->_logOrder($order, 0, $channel);
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
