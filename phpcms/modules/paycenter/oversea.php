<?php
defined('IN_PHPCMS') or exit('No permission resources.');
pc_base::load_app_class('payoversea', 'paycenter', 0);

/**
 * 海外版充值回调处理
 * @author dietoad
 *
 */
class oversea extends payoversea
{

    /**
     * @var array
     */
    private $_new_order_params;
    /**
     * @var array
     */
    private $_verify_order_params;

    private $_server_order_params;
    /**
     * @var array
     */
    private $_role_order_params;
    /**
     * @var array
     */
    private $_dnyoff_order_params;
    /**
     * @var array
     */
    private $ufmt;
    /**
     * @var array
     */
    private $_verify_order_params;

    /*
     * 加载所有用到的数据库模型初始化渠道参数
     */
    public function __construct()
    {
        $this->_orders = pc_base::load_model('orders_model');
        $this->_orders_list = pc_base::load_model('orders_list_model');
        $this->_account = pc_base::load_model('account_model');
        $this->_log = pc_base::load_model('log_model');
        $this->_new_order_params = array('account', 'serverid', 'itemid', 'channel');
        $this->_server_order_params = array('idn', 'appid', 'platform', 'time', 'sign');
        $this->_role_order_params = array('idn', 'appid', 'platform', 'zoneid', 'time');
        $this->_dnyoff_order_params = array('idn', 'appid', 'platform', 'zoneid', 'roleid', 'currency_num', 'amount', 'merchantref', 'pay_type', 'time');
        $this->ufmt = "Iuid/a65acount/a65name/Ipic_id/Csex/Iexp/Clevel/Ivipexp/Cviplevel/Ilogout_time/igold/idiamond/" .
            "Sap/Sapnum/Sapbuynum/Sapmax/Sstamina/Sstamina_max/Igong/Ihonor/Smapid/crace/Icreate_time/a348card/cgmlevel/" .
            "Ijade/Ifirstapretime/Isecondapretime/Ifirststaminaretime/Isecondstaminaretime/Iapfrompillvalue/Istaminafrompillvalue/" .
            "Isoul/Iapfriend/Ilastchattime/Irelive/Iexploit/Ibattlepower/Ifodder/Inationcontribute/Ileagueid/Inationid/Iscore/Icar/Iflag" .
            "/IstaminaBuyCount/IapBuy/IstaminaBuy/IapBuyToday/IstaminaBuyToday/ImainHeroId";
        $this->_verify_order_params=array('orderid');
        $this->_soha_order_params = array('app_id', 'user_id', 'order_info', 'role_id', 'area_id', 'order_id', 'time');
    }

    /**
     * 创建订单
     * 参数 serverid  服务器编号    account 账号 channel 渠道 itemid 购买的物品
     */
    public function createOrder()
    {
        $data = $_REQUEST;
        file_put_contents('yuenan.log', date('Y-m-d H:i:s') . '----createOrder' . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
        $ret = $this->_check_params($data, $this->_new_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            exit(json_encode($ret));
        }
        $ret = array('ret' => 0);
        $servers = $this->get_server_config();
        if (!isset($servers[$data['serverid']])) {
            $ret = array('ret' => 1, 'msg' => '游戏服不存在');
        }
        /**
         * 充值中心没有角色信息需要去对应的游戏数据库查询一次
         */
        if (!$this->_account->changeConnection($data['serverid'])) {
            $ret = array('ret' => 2, 'msg' => '数据库错误');
        }
        $account = $this->_account->select(array('AccountName' => strtoupper($data['account'])));
        if (count($account) > 1) {
            /**
             * 查到多个结果说明是合过服的特殊处理
             */
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

        $items = $this->_getOverSeaPayConfig($data['channel']);
        if ($ret['ret'] == 0 && !isset($items[$data['itemid']])) {
            $ret = array('ret' => 4, 'msg' => '购买物品不存在');
        }
        if ($ret['ret'] == 0) {

            //sha1 rand 避免重复
            $array = array('orderid' => sha1(uniqid(rand(1, 10000), TRUE)), 'channel' => $data['channel'], 'account' => $data['account'], 'itemid' => $data['itemid'], 'serverid' => $data['serverid'], 'uid' => $account['ID'], 'amount' => $items[$data['itemid']]['Paynull']);

            $this->_orders_list->insert($array);
            $ret['orderid'] = $array['orderid'];
            $ret['amount'] = $array['amount'];
        }
        file_put_contents('yuenan.log', date('Y-m-d H:i:s') . '----createOrder' . json_encode($ret, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
        echo json_encode($ret);
    }

    /**
     * 前端请求验证某个订单是否已经处理完成 如果完成前端会删除本地订单记录
     * 韩国 onestore
     * 流程基本模仿appstore的 ，但是订单校验比较简单
     */
    public function verifyOrder()
    {
        $data=$_POST;
        file_put_contents('onestone.log',date('Y-m-d H:i:s').'verifyOrder----$data:'.json_encode($data,JSON_UNESCAPED_UNICODE).PHP_EOL,FILE_APPEND);
        $ret=$this->_check_params($data, $this->_verify_order_params);
        if(isset($ret['msg'])&&$ret['msg'])
        {
            exit(json_encode($ret));
        }
        $ret=array('ret'=>0);
        $orders=array();
        $json=json_decode(stripslashes($data['orderid']),TRUE);
        if(is_array($json))
        {
            foreach ($json as $k=>$v) {
                $arr=$this->check_onestore_order($v);//查看订单情况
                if($arr['status']==0)
                {
                    $ret=$this->_push_onestore_order($k,$arr,'onestore');
                    if(isset($ret['ret'])&&($ret['ret']==0||$ret['ret']==4))
                    {
                        $orders[$k]=1;
                    }else{
                        $orders[$k]=0;
                    }
                }else{
                    file_put_contents('onestone.log',date('Y-m-d H:i:s').'---verifyOrder:签名出错'.PHP_EOL,FILE_APPEND);
                    $orders[$k]=0;
                }
            }
            $ret['orders']=$orders;
        }else{
            $ret['ret']=1;
            $ret['msg']='orders_wrong_format';
        }
        echo json_encode($ret);
        file_put_contents('onestone.log',date('Y-m-d H:i:s').'---verifyOrder:'.json_encode($ret,JSON_UNESCAPED_UNICODE).PHP_EOL,FILE_APPEND);
    }

    /**
     *  越南平台充值发送元宝
     */
    public function ynios()
    {
        $vdata = $_POST;
        file_put_contents('yuenan.log', date('Y-m-d H:i:s') . '-ynios--333:' . json_encode($vdata, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
        $soha = pc_base::load_config('oversea', 'soha');
        $secret = $soha['AppKey'];
        $signed_request = $vdata['signed_request'];
        $data = $this->parse_signed_request($signed_request, $secret);
        file_put_contents('yuenan.log', date('Y-m-d H:i:s') . '-ynios--444:' . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
        if ($signed_request == '') {
            $result = array('status' => 'failed', 'message' => 'signed_request empty');
            print_r(json_encode($result));
            exit;
        }
        //表名规则 orders_channel_sid
        $table = strtolower('orders_soha_s' . $data['area_id']);
        if (!$this->_orders->table_exists($table)) {
            //对应的渠道订单表是第一次使用的时候创建的
            $create = pc_base::load_config('create');
            $create_sql = str_replace('#TABLE', $table, $create['orders']);
            $this->_orders->query($create_sql);
            //  echo "Create table $table\n";
        }
        $servers = $this->get_server_config();
        $this->_orders->setTable($table);
        $server = $servers[$data['area_id']];
        $res = $this->_orders->get_one('`orderid`=' . '"' . $data['order_id'] . '"');
        if (empty($res) || $res['status'] == 0) {
            if ($res['failed'] > 5) {
                echo json_encode(array('status' => 'failed', 'message' => ''));
                exit;
            }
            $role = $this->find_role($data);//查看角色是否存在
            if ($role['ret'] != 6) {
                file_put_contents('yuenan.log', date('Y-m-d H:i:s') . '--555--没有账号信息:' . json_encode($role, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
                exit;
            }
            $this->_orders->setTable($table);
            $data['status'] = 0;
            $items = $this->getglod();
            file_put_contents('yuenan.log', date('Y-m-d H:i:s') . '--555--$items:' . json_encode($items, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
            $data['amount'] = $items[$data['order_info']]['amount'];
            $data['gold'] = $items[$data['order_info']]['gold'];
            $data['itemID'] = $items[$data['order_info']]['itemID'];
            if (empty($res)) {
                $this->_push_over_soha_order($data, 'soha');
            }
            $server = $servers[$data['area_id']];//var_dump($data);
            $ret = $this->sendToGS($server['CIP'], $server['CPort'], $data);
            file_put_contents('yuenan.log', date('Y-m-d H:i:s') . '--666--$data' . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
            file_put_contents('yuenan.log', date('Y-m-d H:i:s') . '--777--$ret:' . json_encode($ret, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
            if ($ret == 0) {
                $this->_orders->update('`status`=1', '`orderid`=' . "'" . $data['order_id'] . "'");
                echo json_encode(array('status' => 'settled', 'message' => 'success', 'repeat' => 0, 'request' => $vdata));
            } else {
                $failed = $this->_orders->get_one('`orderid`=' . '"' . $data['order_id'] . '"');
                $this->_orders->update('`failed`="' . $failed['failed'] . '"+1', '`orderid`=' . "'" . $data['order_id'] . "'");
                echo json_encode(array('status' => 'failed', 'message' => ''));
            }
        } else {
            $this->_orders->setTable($table);
            $failed = $this->_orders->get_one('`orderid`=' . '"' . $data['order_id'] . '"');
            $this->_orders->update('`failed`="' . $failed['failed'] . '"+1', '`orderid`=' . "'" . $data['order_id'] . "'");
            echo json_encode(array('status' => 'settled', 'message' => 'success', 'repeat' => 1));
        }
    }

    /**
     *  东南亚合作方官网充值发送元宝
     */
    public function dny_Official()
    {
        $data = $_REQUEST;
        //表明规则 orders_channel_sid
        $table = strtolower('orders_dnyoff_s' . $data['zoneid']);
        if (!$this->_orders->table_exists($table)) {
            //对应的渠道订单表是第一次使用的时候创建的
            $create = pc_base::load_config('create');
            $create_sql = str_replace('#TABLE', $table, $create['orders']);
            $this->_orders->query($create_sql);
            //  echo "Create table $table\n";
        }
        $servers = $this->get_server_config();
        $this->_orders->setTable($table);
        $server = $servers[$data['zoneid']];
        $res = $this->_orders->get_one('`orderid`=' . '"' . $data['merchantref'] . '"');
        //	var_dump($res['status']);
        if (empty($res) && $res['status'] == 0) {
            $ret = $this->_check_params($data, $this->_dnyoff_order_params);
            if ($ret['code'] == '1') {
                echo json_encode($ret);
                return;
            }
            $dnyoff = pc_base::load_config('oversea', 'dnyoff');
            $sign = $data['sign'];
            $mysign = $this->dnyoff($data, $dnyoff['PayKey'], $this->_dnyoff_order_params);
            if ($sign != $mysign) {
                /**
                 * 验证签名失败
                 */
                $this->_logOrder($data, 6, 'dnyoff');
                exit('failed');
            }

            if (!isset($servers[$data['zoneid']])) {
                $ret = array('ret' => 1, 'msg' => '游戏服不存在');
                exit(var_dump($ret));
            }
            /**
             * 充值中心没有角色信息需要去对应的游戏数据库查询一次
             */
            if (!$this->_account->changeConnection($data['zoneid'])) {
                $ret = array('ret' => 2, 'msg' => '数据库错误');
                exit(var_dump($ret));
            }
            $account = $this->_account->select(array('AccountName' => strtoupper('DNY' . $data['idn'])));
            if (count($account) > 1) {
                /**
                 * 查到多个结果说明是合过服的特殊处理
                 */
                foreach ($account as $k => $user) {
                    if (isset($user['ServerType']) && $user['ServerType'] == $data['zoneid']) {
                        $account = $user;
                        break;
                    }
                }
            } else if (isset($account[0]) && count($account[0])) {
                $account = $account[0];
            }
            if (!$account) {
                $ret = array('ret' => 3, 'msg' => '玩家不存在', 'desc' => 'failure', 'status' => 'false');
                exit(var_dump($ret));
            }

            if ($data['appid'] == $dnyoff['AppId']) {
                $this->_orders->setTable($table);
                $data['status'] = 0;
                $this->_push_over_dnyoff_order($data, 'dnyoff');
                $server = $servers[$data['zoneid']];
                $ret = $this->sendToGS($server['CIP'], $server['CPort'], $data);
                if ($ret == 0) {
                    $this->_orders->update('`status`=1', '`orderid`=' . "'" . $data['merchantref'] . "'");
                    echo success;
                } else {
                    echo failed;
                }
                # }
            } else {
                $data['orderid'] = $data['merchantref'];
                $this->_logOrder($data, 6, 'dnyoff');
                exit('failed');
            }
        } elseif ($res['status'] == 1) {
            $this->_orders->setTable($table);
            $this->_orders->update('`failed`=1', '`orderid`=' . "'" . $data['merchantref'] . "'");
            echo 'repeat order';
        }


    }
    /*
     * 查找角色信息
     */
    public function getInfo($serverid, $id)
    {
        $servers = $this->get_server_config();
        $server = $this->getRedisConfig($serverid);
        $redis = $this->getRedis($server['RIP'], $server['RIndex'], $server['RPort']);
        if (!strstr($id, 'RoleInfo:')) {
            $id = 'RoleInfo:' . $id;
        }
        $data = $redis->get($id);
        $data = (unpack($this->ufmt, $data));
        //var_dump($data);
        if (is_array($data)) {
            return $data;
        } else {
            return array('code' => 1, 'msg' => '该角色不存在');
        }
    }
    /*
     * 查找角色信息(东南亚官网)
     */
    public function role()
    {
        $data = $_GET;
        $data['serverid'] = $data['zoneid'];
        $dnyoff = pc_base::load_config('oversea', 'dnyoff');
        $ret = $this->_check_params($data, $this->_role_order_params);
        $mysign = $this->dnyoff($data, $dnyoff['PayKey'], $this->_role_order_params);
        $data['account'] = 'DNYSB' . $data['idn'];
        $sign = $data['sign'];
        if ($sign != $mysign) {
            /**
             * 验证签名失败
             */
            $this->_logOrder($data, 6, 'dnyoff');
            exit('failed');
        }
        if ($ret['code'] == '1') {
            echo json_encode($ret);
            return;
        }
        $ret = array();
        $servers = $this->get_server_config();
        if (!isset($servers[$data['serverid']])) {
            $ret = array('ret' => 1, 'msg' => '游戏服不存在');
        }
        /**
         * 充值中心没有角色信息需要去对应的游戏数据库查询一次
         */
        if (!$this->_account->changeConnection($data['serverid'])) {
            $ret = array('ret' => 2, 'msg' => '数据库错误');
        }
        $account = $this->_account->select(array('AccountName' => strtoupper($data['account'])));
        if (count($account) > 1) {
            /**
             * 查到多个结果说明是合过服的特殊处理
             */
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
            $ret = array('ret' => 3, 'msg' => '玩家不存在', 'desc' => 'failure', 'status' => 'false');
        } else {
            $role = $this->getInfo($data['serverid'], $account['ID']);
            if ($role && isset($role)) {
                $ret['roleid'] = $role['uid'];
                $ret['rolename'] = $role['name'];
                $ret['level'] = $role['level'];
                $ret['desc'] = 'success';
                $ret['status'] = 'true';
            } else {
                $ret['status'] = 'False';
            }
        }
        echo json_encode($ret);
    }

    /*
     * 查找角色接口(越南)
     */
    public function role_yue()
    {
        $data = $_REQUEST;
        file_put_contents('role.log', date('Y-m-d H:i:s') . '--information1：' . print_r($data, 1) . PHP_EOL, FILE_APPEND);
        $data['serverid'] = $data['server_id'];
        $dnyoff = pc_base::load_config('oversea', 'soha');
        $ret = $this->_check_params($data, $this->_role_order_params);
        $mysign = $this->dnyoff($data, $dnyoff['AppKey'], $this->_role_order_params);
        $data['account'] = 'VT' . $data['user_id'];
        $sign = $data['checkdata'];#var_dump($data);
        file_put_contents('role.log', date('Y-m-d H:i:s') . '--information2：' . print_r($mysign, 1) . PHP_EOL, FILE_APPEND);
        if ($sign != $mysign) {
            /**
             * 验证签名失败
             */
            $this->_logOrder($data, 6, 'dnyoff');
            $ret['status'] = '1';
            $ret['mess'] = 'checkdata error';
            exit(json_encode($ret));
        }
        if ($ret['code'] == '1') {
            echo json_encode($ret);
            return;
        }
        $ret = array();
        $servers = $this->get_server_config();
        if (!isset($servers[$data['serverid']])) {
            $this->_logOrder($data, 6, 'dnyoff');
            $ret['status'] = '1';
            $ret['mess'] = 'The game does not exist';
            exit(json_encode($ret));
        }
        /**
         * 充值中心没有角色信息需要去对应的游戏数据库查询一次
         */
        if (!$this->_account->changeConnection($data['serverid'])) {
            $this->_logOrder($data, 6, 'dnyoff');
            $ret['status'] = '1';
            $ret['mess'] = '(数据库错误)';
        }
        $account = $this->_account->select(array('AccountName' => strtoupper($data['account'])));
        if (count($account) > 1) {
            /**
             * 查到多个结果说明是合过服的特殊处理
             */
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
            $this->_logOrder($data, 6, 'dnyoff');
            $ret['status'] = '1';
            $ret['mess'] = 'role not exist';
        } else {
            $role = $this->getInfo($data['serverid'], $account['ID']);
            if ($role && isset($role)) {#var_dump($role);
                if (!empty($role['level']) && $role['level'] < 6) {
                    $role['name'] = 'Chúa Công';
                }
                $ret['mess'] = 'successful';
                $ret['status'] = '0';
                $ret['info']['0'] = array("area_id" => $data['serverid'], "role_id" => $role['uid'], "role_name" => $role['name'], "role_level" => $role['level']);
            } else {
                $ret['status'] = '1';
                $ret['mess'] = 'checkdata error';
            }
        }
        file_put_contents('role.log', 'information：' . json_encode($ret, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
        echo json_encode($ret);
    }

    /*
     * 获取服务器信息
     */
    public function serverList()
    {
        $data = $_GET;
        $ret = array();
        $ret = $this->_check_params($data, $this->_server_order_params);
        $appid = pc_base::load_config('oversea', 'dnyoff');
        if ($ret['code'] == '1') {
            echo json_encode($ret);
            return;
        }
        if ($data['appid'] == $appid['AppId']) {
            $servers = $this->get_server_config();
            foreach ($servers as $k => $v) {
                $res[] = $v;
            }
            foreach ($res as $kk => $vv) {
                //  if($vv['ServerType']==99){continue;}
                $ret[$kk]['zoneid'] = $vv['ServerType'];
                $ret[$kk]['zonename'] = $vv['text'];
                $ret[$kk]['platform'] = $vv['Channel'];
            }
        } else {
            $ret['status'] = False;
        }
        echo json_encode($ret);
    }

    /**
     * 检查参数是否正确
     * @param array $params 实际参数
     * @param array $keys 需要的参数
     * @return array
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

}
