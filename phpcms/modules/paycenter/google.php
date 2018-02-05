<?php
defined('IN_PHPCMS') or exit('No permission resources.');
pc_base::load_app_class('payroid', 'paycenter', 0);

class google extends pay
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
     * @var array
     */
    private $_sband_order_params;


    public function __construct()
    {
        $this->_orders = pc_base::load_model('orders_model');
        $this->_orders_list = pc_base::load_model('orders_list_model');
        $this->_account = pc_base::load_model('account_model');
        $this->_log = pc_base::load_model('log_model');
        $this->_tickets = pc_base::load_model('tickets_model');
        $this->_new_order_params = array('account', 'serverid', 'itemid', 'channel');
        $this->_verify_order_params = array('orderid');
        //google play 参数
        $this->_gp_order_params = array('data', 'sign');

    }

    public function createOrder()
    {
        $data = $_REQUEST;
        file_put_contents('gp.log', date('Y-m-d H:i:s') . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
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
        file_put_contents('gp.log', date('Y-m-d H:i:s') . json_encode($ret, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
        echo json_encode($ret);
    }

    //{\\"ef9b97ecaf60ba3e14891d0254c1e2867c3ecef6\\":\\"eyJkYXRhIjoie1wib3JkZXJJZFwiOlwiR1BBLjMzMjgtNTYxMy0xMTM3LTgwMjY4XCIsXCJwYWNr\\\nYWdlTmFtZVwiOlwiY29tLmdhbWVjYWZmLnRraGVyb1wiLFwicHJvZHVjdElkXCI6XCJjb20uZ2Ft\\\nZWNhZmYudGtoZXJvLjEyMFwiLFwicHVyY2hhc2VUaW1lXCI6MTUxNjI2NzMyMDk0NixcInB1cmNo\\\nYXNlU3RhdGVcIjowLFwiZGV2ZWxvcGVyUGF5bG9hZFwiOlwiZWY5Yjk3ZWNhZjYwYmEzZTE0ODkx\\\nZDAyNTRjMWUyODY3YzNlY2VmNlwiLFwicHVyY2hhc2VUb2tlblwiOlwia2htb2ZubWxoamZqZW9l\\\nYWdvZWdtamZnLkFPLUoxT3hXLXFsUmlDSU10eVgtUkJxWjhsNk12bDBZWW5ISHJNSHp4Nk1KeEdO\\\nSnIxSk1mbkI3MjBPeENGZ3JreDJnY0Z3cnpaY0hEVlNzcENLcHBPVTRlSFJxajFDZGdrdTJnVktD\\\nVlNZRUpJZ0JNRDRPajNqanFYV1hKQkxPYW5URFZhLVp5MXBIXCJ9Iiwic2lnbiI6IkdGMlBNTzAw\\\nNXdRcjNzWmhlUkUrczJVdUNxdnMyOW5waXVCZmdWelhuWHFaVmVuN0V2eEptR2lyXC85UkdnNHlz\\\nZWhXblwvMzlmalVjVzFJVGs4K2I2OTZVU0o5Z1wvU1l5MGNDd1RPRjlkRmpwZzJSaHJzdEpFXC9r\\\nNTVOREU3MU9YXC91VlE3NUVKVjBoSTZPaVR4MmRNazJWcnk2bGoxWitLbFdjWlFrc0haN3ZndW4w\\\nTEw3eWJob2xqUlwvMVcxOU4yWlNHNXBiRndNdUFsRTVJZEs2R0pZQ2t2aVBmaHljaDNiS0I1ZGsw\\\neUJqaDJSdDJ1T2QyQnl6SXhGY3BzR0YwWWNTTEw1dzdhYkNsYVdnd3dmRFNvbnY2R0RVY2ZnMHpF\\\ndkRzRlpLTDhjaFQ2VGFDYlpzbm1OWW9IRDI3djVLOTkyaFVoVFwvdnNkVVpxRzdmWXdnYjE1dlE9\\\nPSJ9\\\n\\", \\"6d0130184949a4eb24c5259e0a990bb69272d10e\\":\\"eyJkYXRhIjoie1wib3JkZXJJZFwiOlwiR1BBLjMzNTQtNTMzNi00MzkzLTQzNDA2XCIsXCJwYWNr\\\nYWdlTmFtZVwiOlwiY29tLmdhbWVjYWZmLnRraGVyb1wiLFwicHJvZHVjdElkXCI6XCJjb20uZ2Ft\\\nZWNhZmYudGtoZXJvLjEyMFwiLFwicHVyY2hhc2VUaW1lXCI6MTUxNjI2NzI5MTMzNCxcInB1cmNo\\\nYXNlU3RhdGVcIjowLFwiZGV2ZWxvcGVyUGF5bG9hZFwiOlwiNmQwMTMwMTg0OTQ5YTRlYjI0YzUy\\\nNTllMGE5OTBiYjY5MjcyZDEwZVwiLFwicHVyY2hhc2VUb2tlblwiOlwia2luaW9lb2ZraGhhYWxi\\\namJmbGtuZ2NvLkFPLUoxT3hocTBCcnZHcW9xcTVFUFZtSWFpSDZ1ekUxWnNKdDUxd2JOOTJuYWYy\\\nZWtkTHVnd2dGTlNiOGlwTWI5amhFdGxCZGdvY2hpV3pvaEFEd1NwVmYxS1FnX3RTODlMRHQtQVJu\\\nQnpRWmlfX1NkWEVpNVR4R0xqSXR6U0VQOENwUTZQa0M3SVowXCJ9Iiwic2lnbiI6IktPcHprWSsx\\\nd2ZFK2JveEM2bXJkZUtmMDNsbDZtKzVcLzgxVVVkaHFQUXA2TEFPSGdoTWtMOTdTS2hiSVAwQnJG\\\nb25kRFp2UFFseGQ2cDlDeWU4Q0dYWndtbGFyVWpnRG84NmY1a0FzZ0F4VkpPN2JhNnV0NExmcjVu\\\nRHZTNjA2dXIrMVBlcHk1dFJma0ZoT1pXVXQ3dlE2b2xkc0h4K1ZDRTMycWlGVkp1WTJQUXBFM05L\\\ndDkzdnRXSkM0NHFReFVBTWNPWGhnY2tLMXRpUlBha3MybllkR2lqSjdIRUtXbjdpcDQ0QUkra3Ja\\\ndXB5MEQ0Q2t1S1BqeG80cERabmdicVBQXC9Ea3VcLzhpZlE5dTJvWWthSzdBb3dQSDcyeDNqK1Nn\\\nZkpoOERyd2k3T3ZlS1Q0UmpIYkxwc284RVI4bjlNUzc0OVJ2WmFSZm9uaUYzblFsSjMzQT09In0=\\\n\\"}
    //前端返回充值内容
    public function verifyOrder()
    {
        $vdata = $_POST;
        //获取游戏服务器列表
        $servers = $this->get_server_config();
        //获取item表(pay充值表)信息
        $items = $this->_getPayConfig('gp');
        foreach ($items as $key => $value) {
            $money[$value['ID']]['Paynull'] = $value['Paynull'];//itemid 对应的金额
            $money[$value['ID']]['Gain'] = $value['Gain'];      //itemid 对应元宝数
            $money[$value['ID']]['Gain'] = $value['payCount'];  //itemid 对应美元金额
        }
        //处理订单信息（去除转义符，并对json格式字符串解码）
        $json = json_decode(stripslashes($vdata['orderid']), true);
        foreach ($json as $k => $v) {
            //处理订单信息(转换成数组方便后边操作)
            $orderArray = json_decode(base64_decode($v),true);
        file_put_contents('gp.log', date('Y-m-d H:i:s') . json_encode($ret, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
            //检验参数是否齐全
            //$rets = $this->_check_params($orderArray, $this->_gp_order_params);
            if (isset($rets['msg']) && $rets['msg']) {
                $data['orderid'] = $k;
                $this->_logOrder($orderArray, 7, 'gp');
                $orders[$k] = 1;
                break;
            }
            //签名方法验证订单信息
            $status1 = $this->_check_google_order($orderArray['data'], $orderArray['sign'],'gp');
            //google服务 查询订单信息
            $status2 = $this->_check_google_order_two($orderArray['data'], 'gp');
            if ($status1 && $status2)//google订单校验$status1=='success'
            {
                //$orderArray['data'] 解码获取充值中心生成的orderid
                $dataArray = json_decode($orderArray['data'], true);
                $ret = $this->_push_gp_order($dataArray, 'gp');
                if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                    $orders[$k] = 0;
                } else {
                    $orders[$k] = 1;
                }
            } else {
                $orders[$k] = 1;
                $data['orderid'] = $k;
                $this->_logOrder($data, 6, 'gp');
            }
        }
        $ret['orders'] = $orders;
        echo json_encode($ret);
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
