<?php
defined('IN_PHPCMS') or exit('No permission resources.');
pc_base::load_app_class('pay', 'paycenter', 0);
pc_base::load_sys_class('form', '', 0);

class daemon extends pay
{

    public function __construct()
    {
        $this->_orders = pc_base::load_model('orders_model');
        $this->_account = pc_base::load_model('account_model');
        $this->_orders_list = pc_base::load_model('orders_list_model');
        $this->_log = pc_base::load_model('log_model');
        $this->_tickets = pc_base::load_model('tickets_model');
    }


    public function proccssAppStoreOrders()
    {

        ob_flush();
        ob_end_flush();
        set_time_limit(0);
        $servers = $this->get_server_config();
        $this->__log("Start Proccess AppStore Orders");
        $sleeptime = 0;
        while (1) {
            $orders = $this->_tickets->listinfo(array('status' => 0, 'process' => 0));
            $count = count($orders);
            if ($count == 0) {
                usleep(100000);
                if ($sleeptime++ == 100) {
                    $this->_tickets->ping();
                    $sleeptime = 0;
                }
                continue;
            }
            $this->__log("Got Orders:" . count($orders));
            foreach ($orders as $k => $v) {
                $return = json_decode($v['appreturn'], TRUE);
                $myorder = json_decode($v['orderinfo'], TRUE);
                $ret = $this->_push_app_store_order($myorder, $return['receipt'], 'appStore');
                $this->_tickets->update(array('process' => '1'), array('orderid' => $v['orderid']));
            }
        }
    }


    public function processOrders()
    {
        ob_flush();
        ob_end_flush();
        set_time_limit(0);
        $servers = $this->get_server_config();
        $this->__log("Start Proccess Orders");
        $sleeptime = 0;
        while (1) {
            usleep(100000);
            $orders = $this->_orders->listinfo('`failed`<10');
            $count = count($orders);
            if ($count == 0) {
                usleep(100000);
                if ($sleeptime++ == 100) {
                    $this->_orders->ping();
                    $sleeptime = 0;
                }
                continue;
            }
            $this->__log("Got Orders:" . count($orders));
            foreach ($orders as $k => $v) {
                $this->__log("START PROCESS ORDER:" . $v['orderid'] . "\nCHANNEL=" . $v['channel'] . " OID=" . $v['orderid'] .
                    " SID=" . $v['serverid'] . " UID=" . $v['uid'] . " AMOUNT=" . $v['amount'] . " TS=" . $v['ts'] . " ITEMID=" . $v['itemid']);
                $otable = $this->_orders->getTable();
                $table = strtolower('orders_' . $v['channel'] . '_s' . $v['serverid']);

                $this->_orders->setTable($table);
                if (!$this->_orders->table_exists($table)) {
                    $create = pc_base::load_config('create');
                    $create_sql = str_replace('#TABLE', $table, $create['orders']);
                    $this->_orders->query($create_sql);
                    echo "Create table $table\n";
                }
                if (!isset($servers[$v['serverid']])) {
                    $this->_orders->setTable($otable);
                    $this->_orders->update('`failed`=`failed`+1', '`orderid`=' . "'" . $v['orderid'] . "'");
                    $this->__log('NO SERVER CONFIG ID=' . $v['serverid']);
                    continue;
                }
                $server = $servers[$v['serverid']];
                $this->_orders->setTable($table);
                $arr = $this->_orders->select("`orderid`='" . $v['orderid'] . "'");
                if (!empty($arr)) {
                    $this->__log("repeat order:" . json_encode($arr));
                    $update = array('failed' => '+=10', 'status' => '110');//重复订单
                    $this->_orders->setTable($otable);
                    $this->_orders->update($update, array('orderid' => $v['orderid']));
                    continue;
                }
                $this->__log("SEND TO CHARGE SERVER");
                $ret = $this->sendToGS($server['CIP'], $server['CPort'], $v);
                //55298 : 签名错误 检查key 游戏服pay表是否一直   
                if ($ret == 0) {
                    $this->_orders->setTable($table);
                    $this->_orders->insert($v);
                    $this->_orders->setTable($otable);
                    $conditon = sprintf("`orderid`='%s' and `uid`='%s'", $v['orderid'], $v['uid']);
                    $this->_orders->delete($conditon);
                    $this->_orders_list->update("`ptime`=" . time(), "`orderid`='" . $v['orderid'] . "'");
                    $count++;
                    $this->__log("ORDER:" . $v['orderid'] . " PROCESS SUCCESS END :" . $ret);
                } else {
                    $this->__log("ORDER:" . $v['orderid'] . " PROCESS FAILED  END :" . $ret);
                    $update = array('failed' => '+=1', 'status' => $ret);
                    if ($ret == 55299) {
                        $update['failed'] = '+10';
                        $this->_orders_list->update("`ptime`=" . time(), "`orderid`='" . $v['orderid'] . "'");
                    }
                    $this->_orders->setTable($otable);
                    $this->_orders->update($update, array('orderid' => $v['orderid']));

                }

            }
        }
    }


}
