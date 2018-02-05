<?php
defined('IN_PHPCMS') or exit('No permission resources.');
pc_base::load_app_class('pay', 'paycenter', 0);

/**
 *  appstore 越狱渠道充值回调处理
 * @author dietoad
 */
class index extends pay
{

    /**
     * @var array
     */
    private $_xy_order_params;
    /**
     * @var array
     */
    private $_pp_order_params;
    /**
     * @var array
     */
    private $_i4_order_params;
    /**
     * @var array
     */
    private $_itools_order_params;
    /**
     * @var array
     */
    private $_ky_order_params;
    /**
     * @var array
     */
    private $_tbt_order_params;
    /**
     * @var array
     */
    private $_nd_order_params;
    /**
     * @var array
     */
    private $_iapple_order_params;
    /**
     * @var array
     */
    private $_xx_order_params;
    /**
     * @var array
     */
    private $_tj_order_params;
    /**
     * @var array
     */
    private $_hmw_order_params;
    /**
     * @var array
     */
    private $_new_order_params;
    /**
     * @var array
     */
    private $_verify_order_params;

    /**
     * 加载所有用到的数据库模型初始化渠道参数
     */
    public function __construct()
    {
        $this->_orders = pc_base::load_model('orders_model');
        $this->_orders_list = pc_base::load_model('orders_list_model');
        $this->_account = pc_base::load_model('account_model');
        $this->_log = pc_base::load_model('log_model');
        $this->_tickets = pc_base::load_model('tickets_model');
        $this->_xy_order_params = array('orderid', 'uid', 'serverid', 'amount', 'extra', 'ts', 'sign', 'sig');
        $this->_pp_order_params = array('order_id', 'billno', 'account', 'amount', 'status', 'app_id', 'roleid', 'zone');
        $this->_i4_order_params = array('order_id', 'billno', 'account', 'amount', 'status', 'app_id', 'role', 'zone');
        $this->_itools_order_params = array('notify_data', 'sign');
        $this->_ky_order_params = array('notify_data', 'orderid', 'dealseq', 'uid', 'subject', 'sign', 'v');
        $this->_tbt_order_params = array('source', 'trade_no', 'amount', 'partner', 'paydes', 'debug', 'tborder', 'sign');
        $this->_nd_order_params = array('AppId', 'Act', 'ProductName', 'ConsumeStreamId', 'CooOrderSerial', 'Uin', 'GoodsId', 'GoodsInfo',
            'GoodsCount', 'OriginalMoney', 'OrderMoney', 'Note', 'PayStatus', 'CreateTime', 'Sign');
        $this->_iapple_order_params = array('transaction', 'payType', 'userId', 'serverNo', 'amount', 'cardPoint', 'gameUserId', 'transactionTime',
            'gameExtend', 'platform', 'status', 'currency', '_sign');
        $this->_xx_order_params = array('trade_no', 'serialNumber', 'money', 'status', 't', 'sign');
        $this->_tj_order_params = array('transdata', 'sign', 'signtype');
        $this->_hmw_order_params = array('notify_time', 'appid', 'out_trade_no', 'total_fee', 'subject', 'body', 'trade_status', 'sign');
        $this->_new_order_params = array('account', 'serverid', 'itemid', 'channel');
        $this->_verify_order_params = array('orderid');
    }

    /**
     * 创建订单
     * 参数 serverid  服务器编号    account 账号 channel 渠道 itemid 购买的物品
     */
    public function createOrder()
    {
        $data = $_REQUEST;
        $ret = $this->_check_params($data, $this->_new_order_params);
//         $ret['data']=$data; 
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

        $items = $this->_getPayConfig($data['channel']);
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
        echo json_encode($ret);
    }

    /**
     * 前端请求验证某个订单是否已经处理完成 如果完成前端会删除本地订单记录
     */
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
//         file_put_contents('appstore', print_r($ret,1),FILE_APPEND);;
        echo json_encode($ret);
    }

    /**
     * xy 回调
     */
    public function xy()
    {
        $data = $_POST;
        $ret = $this->_check_params($data, $this->_xy_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $this->_logOrder($data, 7, 'xy');
            exit(json_encode($ret));
        }
        $channels = pc_base::load_config('channels');
        /**
         * 签名校验
         */
        $sign = $data['sign'];
        $mysign = $this->_gen_safe_sign($data, $channels['xy']['AppKey']);
        if ($sign != $mysign) {
//             echo $mysign;
            $this->_logOrder($data, 6, 'xy');
            $arr = array('ret' => 6, 'msg' => 'App签名错误');
            exit(json_encode($arr));
        }
        $sign = $data['sig'];
        $mysign = $this->_gen_safe_sign($data, $channels['xy']['PayKey']);
        if ($sign != $mysign) {
//               echo $mysign;
            $this->_logOrder($data, 10, 'xy');
            $arr = array('ret' => 6, 'msg' => '支付签名错误');
            exit(json_encode($arr));
        }
        $ret = $this->_push_xy_order($data, 'xy');
        echo json_encode($ret);

    }

    /**
     * pp回调
     */
    public function pp()
    {
        $data = $_POST;
        $ret = $this->_check_params($data, $this->_pp_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $this->_logOrder($data, 7, 'pp');
            echo "fail";
            exit();
        }
        if ($this->chk_pp($data)) {
            $ret = $this->_push_pp_like_order($data, 'pp');

            if (isset($ret['ret']) && $ret['ret'] == 0) {
                echo "success";
            } else {
                echo "fail";
            }
        } else {
            $this->_logOrder($data, 6, 'pp');
            echo "fail";
        }
    }

    /**
     * 爱思回调
     */
    public function i4()
    {
        $data = $_POST;
        $ret = $this->_check_params($data, $this->_i4_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $this->_logOrder($data, 7, 'i4');
            echo "fail";
            exit();
        }
        if ($this->chk_i4($data)) {
            $ret = $this->_push_pp_like_order($data, 'i4');

            if (isset($ret['ret']) && $ret['ret'] == 0) {
                echo "success";
            } else {
                echo "fail";
            }
        } else {
            $this->_logOrder($data, 6, 'i4');
            echo "fail";
        }
    }

    /**
     * itools回调
     */
    public function its()
    {
        $data = $_POST;
        $notify = new Notify();
        $sign = $data['sign'];
        $notify_data = $notify->decrypt($data['notify_data']);
        $notify_json = json_decode($notify_data);
        $ret = $this->_check_params($data, $this->_itools_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $this->_logOrder($notify_json, 7, 'its');
            echo "fail";
            exit();
        }
        //验证签名
        if ($notify->verify($notify_data, $sign)) {
            //逻辑处理, $notify_data: json数据(格式: {"order_id_com":"2013050900000712","user_id":"10010","amount":"0.10","account":"test001","order_id":"2013050900000713","result":"success"})
            $json = json_decode($notify_data, true);
            $ret = $this->_push_itools_order($json, 'its');
            if (isset($ret['ret']) && $ret['ret'] == 0) {
                echo "success";
            } else {
                echo "fail";
            }
        } else {
            $this->_logOrder($data, 6, 'its');
            echo "fail";
        }
    }

    /**
     * 快用回调
     */
    public function ky()
    {
        $data = $_POST;
        $ret = $this->_check_params($data, $this->_ky_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $this->_logOrder($data, 7, 'ky');
            echo "fail";
            exit();
        }
        $init = new Init();
        if ($init->verify($data['sign'], $data['notify_data'], $data['orderid'], $data['dealseq'], $data['uid'], $data['subject'], $data['v'])) {
            $ret = $this->_push_ky_order($data, 'ky');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                echo "success";
            } else {

                echo "failed";
            }
        } else {
            $this->_logOrder($data, 6, 'ky');
            echo 'failed';
        }

    }

    /**
     * 同步推回调
     */
    public function tbt()
    {
        $ret = array('status' => 'success');
        $data = $_GET;
        $ret = $this->_check_params($data, $this->_tbt_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['orderid'] = $data['tborder'];
            $this->_logOrder($data, 7, 'tbt');
            echo json_encode(array('status' => 'fail'));
            exit();
        }
        if ($this->check_tbt($data)) {
            $ret = $this->_push_tbt_order($data, 'tbt');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                echo json_encode(array('status' => 'success'));
            } else {
                echo json_encode(array('status' => 'error'));
            }
        } else {
            $data['orderid'] = $data['tborder'];
            $this->_logOrder($data, 6, 'tbt');
            $ret['status'] = 'error';
            echo json_encode($ret);
        }
    }

    /**
     * iapple回调
     */
    public function iiapple()
    {
        $data = $_POST;
        $ret = $this->_check_params($data, $this->_iapple_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['orderid'] = $data['transaction'];
            $this->_logOrder($data, 7, 'iiapple');
            echo json_encode(array('status' => 'fail'));
            exit();
        }
        if ($this->check_iapple($data, $this->_iapple_order_params)) {
            $ret = $this->_push_iapple_order($data, 'iapple');
            if (isset($ret['ret']) && $ret['ret'] == 0) {
                echo json_encode(array('status' => '0', 'transIDO' => $data['gameExtend']));
            } else {
                echo json_encode(array('status' => '1', 'transIDO' => $data['gameExtend']));
            }
        } else {
            $data['orderid'] = $data['transaction'];
            $this->_logOrder($data, 6, 'iiapple');
            $ret = array('status' => '1', 'transIDO' => $data['gameExtend']);
            echo json_encode($ret);
        }
    }

    /**
     * 百度91回调
     */
    public function nd()
    {
        $data = $_GET;
        $ret = $this->_check_params($data, $this->_nd_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['orderid'] = $data['ConsumeStreamId'];
            $this->_logOrder($data, 7, 'nd');
            $Result["ErrorCode"] = "0";//注意这里的错误码一定要是字符串格式
            $Result["ErrorDesc"] = urlencode("接收失败");
            $Res = json_encode($Result);
            echo urldecode($Res);
            exit();
        }
        $ret = $this->chk_nd($data);
        if ($ret['ErrorCode'] === "1") {
            $iret = $this->_push_nd_order($data, 'nd');
            if (isset($iret['ret']) && $iret['ret'] == 0) {
                $Result["ErrorCode"] = "1";//注意这里的错误码一定要是字符串格式
                $Result["ErrorDesc"] = urlencode("接收成功");
                $Res = json_encode($Result);
                echo urldecode($Res);
            } else {
                $Result["ErrorCode"] = (string)$iret['ret'];//注意这里的错误码一定要是字符串格式
                $Result["ErrorDesc"] = urlencode($iret['msg']);
                $Res = json_encode($Result);
                echo urldecode($Res);
            }
        } else {
            $data['orderid'] = $data['ConsumeStreamId'];
            $this->_logOrder($data, 6, 'nd');
            echo urldecode(json_encode($ret));
        }
    }

    /**
     * xx回调
     */
    public function xx()
    {
        $data = $_POST;
        $ret = $this->_check_params($data, $this->_xx_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['orderid'] = $data['trade_no'];
            $this->_logOrder($data, 7, 'xx');
            echo 'failed';
            exit();
        }
        if ($this->check_xx($data)) {
            $ret = $this->_push_xx_order($data, 'xx');
            if (isset($ret['ret']) && $ret['ret'] == 0) {
                echo 'success';
            } else {
                echo 'failed';
            }
        } else {
            $data['orderid'] = $data['trade_no'];
            $this->_logOrder($data, 6, 'xx');
            echo 'failed';
        }
    }

    /**
     * 天机回调
     */
    public function tj()
    {
        $data = $_POST;
        $ret = $this->_check_params($data, $this->_tj_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $order = json_decode(stripslashes($data['transdata']), TRUE);
            $data['orderid'] = $order['transid'];
            $this->_logOrder($data, 7, 'tj');
            echo 'FAILURE';
            exit();
        }
        $json = array();
        $str = http_build_query($data);
        if ($this->check_tj($str, $json)) {
            $ret = $this->_push_tj_order($json, 'tj');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                echo 'SUCCESS';
            } else {
                echo 'FAILURE';
            }
        } else {
            $order = json_decode(stripslashes($data['transdata']), TRUE);
            $data['orderid'] = $order['transid'];
            $this->_logOrder($data, 6, 'tj');
            echo 'FAILURE';
        }
    }

    /**
     * 海马玩回调
     */
    public function hmw()
    {
        $data = $_POST;
        $ret = $this->_check_params($data, $this->_hmw_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['orderid'] = $data['out_trade_no'];
            $data['subject'] = urlencode($data['subject']);
            $this->_logOrder($data, 7, 'hmw');
            echo 'FAILURE';
            exit();
        }

        if ($this->check_hmw($data, 'hmw')) {
            $ret = $this->_push_hmw_order($data, 'hmw');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                echo 'success';
            } else {
                echo 'failure';
            }
        } else {
            $data['orderid'] = $data['out_trade_no'];
            $data['subject'] = urlencode($data['subject']);
            $this->_logOrder($data, 6, 'hmw');
            echo 'failure';
        }
    }

    /**
     * 检查参数是否正确
     * @param array $params 实际参数
     * @param array $keys 需要的参数
     * @return array
     */
    public function _check_params($params, $keys)
    {
        foreach ($keys as $k => $v) {
            if (!isset($params[$v])) {
                return array('code' => 1, 'msg' => 'lost_param_' . $v);
            }
        }
        return array();
    }

}