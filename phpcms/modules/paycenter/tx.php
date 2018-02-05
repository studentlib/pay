<?php
defined('IN_PHPCMS') or exit('No permission resources.');
pc_base::load_app_class('payroid', 'paycenter', 0);
pc_base::load_app_class('OpenApiV3', 'paycenter', 0);
define('PRODUCTION', 1); //0 测试环境 1 正式环境
define('OPEN_URL_SANDBOX', 'ysdktest.qq.com');
define('OPEN_URL', 'ysdk.qq.com');
define('OPEN_API', '/mpay/get_balance_m');

class tx extends payroid
{


    public function __construct()
    {
        $this->_orders = pc_base::load_model('orders_model');
        $this->_account = pc_base::load_model('account_model');
        $this->_orders_list = pc_base::load_model('orders_list_model');
        $this->_log = pc_base::load_model('log_model');
        $this->_tx = pc_base::load_model('tx_model');
        $this->_tx_amount = pc_base::load_model('tx_amount_model');
    }

    public function call_back_charge()
    {
        ob_flush();
        ob_end_flush();
        set_time_limit(0);
        $servers = $this->get_server_config();
        $tx = pc_base::load_config('android', 'tx');
        $openv3 = new OpenApiV3($tx['AppId'], $tx['AppKey']);
        if (PRODUCTION == 1) {
            $openv3->setServerName(OPEN_URL);
        } else {
            $openv3->setServerName(OPEN_URL_SANDBOX);
        }
        file_put_content('test.log', '191' . print_r($tx, 1), FILE_APPEND);
        echo "Start Proccess Tx CallBack Orders\n";
        $sleeptime = 0;
        while (1) {
            $orders = $this->_tx->listinfo(array('status' => 0));
            $count = count($orders);
            if ($count == 0) {
                usleep(500000);
                if ($sleeptime++ == 100) {
                    $this->_tx->ping();
                    $sleeptime = 0;
                }
                continue;
            }
            $this->__log("Got Orders:" . count($orders));
            foreach ($orders as $k => $v) {
                $data = json_decode($v['content'], TRUE);
                $ret = $openv3->api('/v3/pay/confirm_delivery', $data, 'POST', 'https');
                if (isset($ret['ret'])) {
                    $this->_tx->update(array('status' => 1, 'ret' => json_encode($ret)), array('id' => $v['id']));
                }

            }
        }
    }

    public function query()
    {
        ob_flush();
        ob_end_flush();
        set_time_limit(0);
        file_put_contents('test.log', '180', FILE_APPEND);
        $servers = $this->get_server_config();
        $tx = pc_base::load_config('android', 'tx');
        // 创建YSDK实例
        $openv3 = new Api($tx['AppId'], $tx['AppKey']);
        // 设置支付信息
        $openv3->setPay($tx['AppId'], $tx['AppKey']);
        file_put_contents('test.log', '10' . print_r($tx, 1), FILE_APPEND);
        if (PRODUCTION == 1) {
            $openv3->setServerName(OPEN_URL);
        } else {
            $openv3->setServerName(OPEN_URL_SANDBOX);
        }
        $this->__log("Start Proccess Tx CallBack Orders");
        $sleeptime = 0;
        while (1) {
            $orders = $this->_tx->listinfo('`status`!=1 and `failed`<2 and `next_process`<' . "'" . time() . "'");
            $count = count($orders);
            if ($count == 0) {
                usleep(500000);
                if ($sleeptime++ == 100) {
                    $this->_tx->ping();
                    $sleeptime = 0;
                }
                continue;
            }
            $this->__log("Got Orders:" . count($orders));
            foreach ($orders as $k => $v) {
                $data = json_decode($v['content'], TRUE);
                $req = json_decode($v['content'], TRUE);
                $orderid = $req['orderId'];
                #$platform=$req['platform'];//qq 和微信标识
                $platform = 'wx';//qq 和微信标识
                if (strstr($req['pf'], 'm_qq')) {
                    $platform = 'qq';
                }
                unset($req['orderId']);
                unset($req['account']);
                unset($req['sid']);
                unset($req['platform']);
                $req['ts'] = time();
                $ret = get_balance_m($openv3, $req, $platform);
                file_put_contents('test.log', '55' . print_r($ret, 1), FILE_APPEND);
                $order = $this->_orders_list->get_one(array('orderid' => $orderid));
                file_put_contents('test.log', '99' . print_r($order, 1), FILE_APPEND);
                $acc_amount = $this->_get_tx_amount($order['account'], $order['serverid']);
                file_put_contents('test.log', '100' . print_r($acc_amount, 1), FILE_APPEND);
                if ($ret['ret'] == 0 && ($ret['save_amt'] - $acc_amount['acc_amount']) >= $order['amount'] * $tx['TxRatio']) {
                    file_put_contents('test.log', '110', FILE_APPEND);
                    $this->_tx->update(array('status' => 1, 'tx_return' => json_encode($ret)), array('id' => $v['id']));
                    $this->_push_tx_order($order, 'tx');
                    $this->_tx_amount->update(array('acc_amount' => $acc_amount['acc_amount'] + $order['amount'] * $tx['TxRatio']), array('account' => $order['account'], 'sid' => $order['serverid']));
                } else if ($ret['ret'] == 0 && ($ret['save_amt'] - $acc_amount['acc_amount']) < $order['amount'] * $tx['TxRatio']) {
                    file_put_contents('test.log', '111', FILE_APPEND);
                    //$this->_tx->update(array('status'=>1,'tx_return'=>json_encode($ret),'last_amount'=>$ret['save_amt']),array('id'=>$v['id']));
                    $this->_tx->update(array('tx_return' => json_encode($ret), 'status' => 110, 'failed' => $v['failed'] + 1, 'next_process' => time() + 3, 'last_amount' => $ret['save_amt']), array('id' => $v['id']));//所加元宝与档位不符110
                } else {
                    file_put_contents('test.log', '112', FILE_APPEND);
                    $this->_tx->update(array('tx_return' => json_encode($ret), 'failed' => $v['failed'] + 1, 'status' => 101, 'next_process' => time() + 3, 'last_amount' => $ret['save_amt']), array('id' => $v['id']));//订单校验失败101
                }
            }
        }
    }

}
