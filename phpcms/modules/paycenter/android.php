<?php
defined('IN_PHPCMS') or exit('No permission resources.');
pc_base::load_app_class('payroid', 'paycenter', 0);

/**
 *
 *
 * 安卓渠道充值回调处理
 * @author dietoad
 *
 */
class android extends payroid
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
    private $_qihoo_order_params;
    /**
     * @var array
     */
    private $_baidu_order_params;
    /**
     * @var array
     */
    private $_uc_order_params;
    /**
     * @var array
     */
    private $_tx_order_params;
    /**
     * @var array
     */
    private $_hw_order_params;
    /**
     * @var array
     */
    private $_xm_order_params;
    /**
     * @var array
     */
    private $_vivo_order_params;
    /**
     * @var array
     */
    private $_anzhi_order_params;
    /**
     * @var array
     */
    private $_kaopu_order_params;
    /**
     * @var array
     */
    private $_youku_order_params;
    /**
     * @var array
     */
    private $_wandoujia_order_params;
    /**
     * @var array
     */
    private $_oppo_order_params;
    /**
     * @var array
     */
    private $_mengcheng_order_params;
    /**
     * @var array
     */
    private $_yijie_order_params;
    /**
     * @var array
     */
    private $_cchong_order_params;
    /**
     * @var array
     */
    private $_app_tx_order_params;

    /**
     * @var array
     */
    private $_mc_channels;
    /**
     * @var array
     */
    private $_yj_channels;

    /**
     * @var array
     */
    private $_tj_order_params;
    /**
     * @var array
     */
    private $_yyc_order_params;
    /**
     * @var array
     */
    private $_liebao_order_params;
    /**
     * @var array
     */
    private $_zhuoyi_order_params;
    /**
     * @var array
     */
    private $_pyw_order_params;
    /**
     * @var array
     */
    private $_fzs_order_params;

    /**
     * 加载所有用到的数据库模型初始化渠道参数
     */
    public function __construct()
    {
        $this->_orders = pc_base::load_model('orders_model');
        $this->_orders_list = pc_base::load_model('orders_list_model');
        $this->_account = pc_base::load_model('account_model');
        $this->_log = pc_base::load_model('log_model');
        $this->_tx = pc_base::load_model('tx_model');
        $this->_tx_amount = pc_base::load_model('tx_amount_model');
        $this->_new_order_params = array('account', 'serverid', 'itemid', 'channel');
        $this->_verify_order_params = array('orderid');
        //360参数
        $this->_qihoo_order_params = array('app_key', 'product_id', 'amount', 'app_uid', 'app_ext1', 'app_ext2',
            'user_id', 'order_id', 'gateway_flag', 'sign_type', 'app_order_id', 'sign_return', 'sign');
        //百度参数
        $this->_baidu_order_params = array('AppID', 'OrderSerial', 'CooperatorOrderSerial', 'Sign', 'Content');
        //UC参数
        $this->_uc_order_params = array('ver', 'data', 'sign');
        //应用宝参数 
        $this->_tx_order_params = array('openid', 'appid', 'ts', 'payitem', 'token', 'billno', 'version',
            'zoneid', 'providetype', 'amt', 'payamt_coins', 'pubacct_payamt_coins', 'sig', 'appmeta');
        //华为参数
        $this->_hw_order_params = array('result', 'userName', 'productName', 'payType', 'amount', 'orderId', 'notifyTime',
            'requestId', 'extReserved', 'sign');
        //小米参数
        $this->_xm_order_params = array('appId', 'cpOrderId', 'cpUserInfo', 'uid', 'orderId', 'orderStatus', 'payFee',
            'productCode', 'productName', 'productCount', 'payTime', 'signature');
        //vivo参数
        $this->_vivo_order_params = array('respCode', 'respMsg', 'signMethod', 'signature', 'tradeType', 'tradeStatus', 'cpId',
            'appId', 'uid', 'cpOrderNumber', 'orderNumber', 'orderAmount', 'extInfo', 'payTime');
        //安智参数
        $this->_anzhi_order_params = array('data');
        //靠谱参数
        $this->_kaopu_order_params = array('username', 'kpordernum', 'ywordernum', 'status', 'paytype', 'amount', 'gameserver', 'errdesc'
        , 'paytime', 'gamename', 'sign');
        //优酷参数
        $this->_youku_order_params = array('apporderID', 'uid', 'price', 'passthrough', 'sign');
        //豌豆荚参数
        $this->_wandoujia_order_params = array('content', 'signType', 'sign');
        //欧普参数
        $this->_oppo_order_params = array('notifyId', 'partnerOrder', 'productName', 'productDesc', 'price', 'count', 'attach', 'sign');
        //梦城参数
        $this->_mengcheng_order_params = array('channel', 'order_id', 'cp_order_id', 'channel_user_id', 'product_name',
            'product_desc', 'order_amount', 'user_id', 'status', 'sign');
        //易接参数
        $this->_yijie_order_params = array('app', 'cbi', 'ct', 'fee', 'pt', 'sdk', 'ssid', 'st', 'tcd', 'uid', 'ver', 'sign');
        //虫虫参数
        $this->_cchong_order_params = array('transactionNo', 'partnerTransactionNo', 'statusCode', 'productId', 'orderPrice', 'packageId', 'sign');
        //应用宝请求验证订单接口
        $this->_app_tx_order_params = array('orderId', 'account');
        //天机爱贝参数
        $this->_tj_order_params = array('transdata', 'sign', 'signtype');
        //悠悠村参数
        $this->_yyc_order_params = array('callback_rsp', 'callback_user', 'callback_appkey');
        //猎宝参数
        $this->_liebao_order_params = array('orderid', 'username', 'gameid', 'roleid', 'serverid', 'paytype', 'amount', 'paytime', 'attach', 'sign');

        //卓易参数
        $this->_zhuoyi_order_params = array('Recharge_Id', 'App_Id', 'Uin', 'Urecharge_Id', 'Extra', 'Recharge_Money', 'Recharge_Gold_Count', 'Pay_Status', 'Create_Time', 'Sign');
        //朋友玩参数
        $this->_pyw_order_params = array('tid', 'gamekey', 'channel', 'cp_orderid', 'ch_orderid', 'amount', 'sign');
        //蜂助手参数
        $this->_fzs_order_params = array('orderId', 'uid', 'amount', 'extraInfo', 'sign');

        $this->_mc_channels = array(
            '10039' => 'mengcheng_letv',
            '10014' => 'mengcheng_baofeng',
            '10008' => 'mengcheng_4399',
            '10012' => 'mengcheng_pptv',
            '10038' => 'mengcheng_samsung',
            '10006' => 'mengcheng_dangle',
            '10023' => 'mengcheng_xx',
            '10043' => 'mengcheng_duomi',
            '10042' => 'mengcheng_sougou',
            '10041' => 'mengcheng_yy',
            '10040' => 'mengcheng_kudong',
            '10013' => 'mengcheng_pps',
            '10015' => 'mengcheng_muziwan',
            '10031' => 'mengcheng_candy',
        );

        $this->_yj_channels = array(
            '83219852fea4191d' => 'one_07073',
            '8dd43fece77a64de' => 'one_meizu',
            'acaa9433b9649ea6' => 'one_yyh',
            'b531c2aa5c613a8c' => 'one_anfeng',
            'fa06cbe517c89009' => 'one_paojiao',
            '0993ac81d95d4e48' => 'one_jinli',
            '152e84d3cab12856' => 'one_jifeng',
            '3481f08bf45db0d6' => 'one_shoumeng',
            '42ac3f04d0229a31' => 'one_haima',
            '5f9e9900d9ccc2d0' => 'one_kupai',
            '64a9cb039df350be' => 'one_yyw',
            '65c90e90aecd89b2' => 'one_ouwan',
            'bb5448802d14c294' => 'one_itools',
            'fc9729ae50fcad69' => 'one_mumayi',
            '82cbe43f60fc012a' => 'one_asyx',
            'c6b5708195b3725c' => 'one_htc',
            '047ebd3d9d8f3ddd' => 'one_yly',
        );
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
            file_put_contents('uuc.log', print_r($data, 1) . print_r($ret, 1), FILE_APPEND);
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
        /**
         * 查到多个结果说明是合过服的特殊处理
         */
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

        $items = $this->_getAndRoidPayConfig($data['channel']);
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
//          file_put_contents('fzs.log', print_r($data,1).print_r($ret,1),FILE_APPEND);
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
        file_put_contents('appstore', print_r($ret, 1), FILE_APPEND);;
        echo json_encode($ret);
    }

    /**
     * 腾讯提交验证订单
     */
    public function app_tx()
    {
        $data = $_POST;
        $ret = $this->_check_params($data, $this->_app_tx_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            exit(json_encode($ret));
        }
        $rs = $this->_tx->get_one(array('orderid' => $data['orderId']));
        if (!$rs) {
            $amount = $this->_get_tx_amount($data['account'], $data['sid']);
            $arr = array('content' => json_encode($data), 'orderid' => $data['orderId'], 'last_amount' => $amount['acc_amount']);
            $this->_tx->insert(array('content' => json_encode($data), 'orderid' => $data['orderId'], 'last_amount' => $amount['acc_amount']));
        } else if (isset($rs['status']) && $rs['status'] == 0) {
            $arr = array('content' => json_encode($data), 'next_process' => 0);
            $this->_tx->update($arr, array('orderid' => $data['orderId']));
        }
    }

    /**
     * 360 回调接口
     */
    public function qihoo()
    {
        $data = $_REQUEST;
        $ret = $this->_check_params($data, $this->_qihoo_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            /**
             * 参数非法
             */
            $this->_logOrder($data, 7, 'qihoo');
            exit('failed');
        }
        $qihoo = pc_base::load_config('android', 'qihoo');
        $sign = $data['sign'];
        $mysign = $this->_gen_qihoo_sign($data, $qihoo['PayKey'], $this->_qihoo_order_params);
        if ($sign != $mysign) {
            /**
             * 验证签名失败
             */
            $this->_logOrder($data, 6, 'qihoo');
            exit('failed');
        }
        if (isset($data['gateway_flag']) && $data['gateway_flag'] != 'success') {
            /**
             * 订单未支付
             */
            $this->_logOrder($data, 10, 'qihoo');
            exit('ok');
        }
        $ret = $this->_push_qihoo_order($data, 'qihoo');
        if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
            exit('ok');
        } else {
            exit('failed');
        }
    }

    public function jpbaidu()
    {
        return $this->baidu();
    }

    /**
     * 百度回调
     */
    public function baidu()
    {
        $data = $_POST;
        $channels = pc_base::load_config('android');
        $appid = $channels['baidu']['AppId'];
        $payKey = $channels['baidu']['PayKey'];
        $ret = $this->_check_params($data, $this->_baidu_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['order_id'] = $data['OrderSerial'];
            $this->_logOrder($data, 7, 'baidu');
            $Result["AppID"] = $channels['baidu']['AppId'];
            $Result["ResultCode"] = 1000;
            $Result["ResultMsg"] = urlencode("接收参数失败");
            $Result["Sign"] = md5($appid . $Result["ResultCode"] . $payKey);
            $Result["Content"] = "";
            $Res = json_encode($Result);
            exit(urldecode($Res));
        }

        $OrderSerial = 0;
        $CooperatorOrderSerial = 0;
        $Content = 0;
        $Sign = 0;
        extract($data);
        //检测请求数据签名是否合法
        if ($Sign != md5($appid . $OrderSerial . $CooperatorOrderSerial . $Content . $payKey)) {
            $Result["AppID"] = $appid;
            $Result["ResultCode"] = 1001;
            $Result["ResultMsg"] = urlencode("签名错误");
            $Result["Sign"] = md5($appid . $Result["ResultCode"] . $payKey);
            $Result["Content"] = "";
            $Res = json_encode($Result);
            $data['order_id'] = $data['OrderSerial'];
            $this->_logOrder($data, 6, 'baidu');
            exit(urldecode($Res));
        }
        //base64解码
        $Content = base64_decode($Content);
        //json解析
        $data = json_decode($Content, true);
        $data['OrderSerial'] = $OrderSerial;
        $data['CooperatorOrderSerial'] = $CooperatorOrderSerial;
        $ret = $this->_push_baidu_order($OrderSerial, $CooperatorOrderSerial, $data, 'baidu');
        if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
            //返回成功结果
            $Result["AppID"] = $appid;
            $Result["ResultCode"] = 1;
            $Result["ResultMsg"] = urlencode("成功");
            $Result["Sign"] = md5($appid . $Result["ResultCode"] . $payKey);
            $Result["Content"] = "";
            $Res = json_encode($Result);
            exit(urldecode($Res));
        } else {
            //返回失败结果
            $Result["AppID"] = $appid;
            $Result["ResultCode"] = $ret['ret'];
            $Result["ResultMsg"] = urlencode($ret['msg']);
            $Result["Sign"] = md5($appid . $Result["ResultCode"] . $payKey);
            $Result["Content"] = "";
            $Res = json_encode($Result);
            exit(urldecode($Res));
        }

    }

    public function jpanzhi()
    {
        return $this->anzhi();
    }

    public function anzhi()
    {
        $data = $_POST;
        $ret = $this->_check_params($data, $this->_anzhi_order_params);
        if (isset($ret['msg']) && $ret['msg']) {

            //$data['order_id']=$data['orderId'];
            //$this->_logOrder($data,7,'xm');
            exit('failed');
        }
        $des = new DES();
        $ret = $des->decrypt($data['data']);
        if (!isset($ret) || empty($ret)) {
            exit('failed');
        }
        $response = json_decode($ret, true);
        if (!isset($response)) {
            exit('failed');
        }

        $ret = $this->_push_anzhi_order($response, 'anzhi');
        if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
            exit('success');
        } else {
            exit('failed');
        }
    }

    /**
     * UC 回调
     */
    public function uc()
    {
        $body = file_get_contents('php://input');
        $data = json_decode($body, TRUE);
        $ret = $this->_check_params($data, $this->_uc_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['data']['order_id'] = $data['data']['orderId'];
            $this->_logOrder($data, 7, 'uc');
            echo 'FAILURE';
            exit();
//            exit(json_encode($ret));
        }
        $channels = pc_base::load_config('android');
        if ($this->_check_uc($data, $channels['uc']['PayKey'])
            && isset($data['data']['orderStatus']) && $data['data']['orderStatus'] == 'S'
            && empty($data['data']['failedDesc'])) {
            $ret = $this->_push_uc_order($data['data'], 'uc');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                echo 'SUCCESS';
            } else {
                echo 'FAILURE';
            }
        } else {
            $data['data']['order_id'] = $data['data']['orderId'];
            $this->_logOrder($data['data'], 6, 'uc');
            echo 'FAILURE';
        }
    }

    /**
     * 腾讯回调(未使用)
     */
    public function tx()
    {
        $data = $_POST;
        $ret = $this->_check_params($data, $this->_tx_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $this->_logOrder($data, 7, 'tx');
            $ret['ret'] = 4;
            exit(json_encode($ret));
        }

        if ($this->_check_tx_order($data)) {
            $ret = $this->_push_tx_order($data, 'tx');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                exit(json_encode(array('ret' => 0, 'msg' => 'ok')));
            } else {
                exit(json_encode(array('ret' => $ret['ret'], 'msg' => $ret['msg'])));
            }
        } else {
            $this->_logOrder($data, 6, 'tx');
            exit(json_encode(array('ret' => 4, 'msg' => 'failed_verify_sign')));
        }
    }

    /**
     * 华为回调
     */
    public function hw()
    {
        $data = $_POST;
        $ret = $this->_check_params($data, $this->_hw_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['order_id'] = $data['orderId'];
            $this->_logOrder($data, 7, 'hw');
            echo "{\"result\" : 1 }";
            return;
        }
        $key_path = CACHE_PATH . '/keys/payPublicKey.pem';
        if (!file_exists($key_path)) {
            echo "{\"result\" : 1 }";
            return;
        }
        $sign = $data['sign'];
        unset($data['sign']);
        ksort($data);
        $params = array();
        foreach ($data as $k => $v) {
            $params[] = $k . '=' . $v;
        }
        $str = join('&', $params);
        $pubKey = file_get_contents($key_path);
        $openssl_public_key = openssl_get_publickey($pubKey);
        $ok = openssl_verify($str, base64_decode($sign), $openssl_public_key);
        openssl_free_key($openssl_public_key);
        $data['sign'] = $sign;
        if ($ok) {
            $ret = $this->_push_hw_order($data, 'hw');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                $result = "0";//支付成功
            } else {
                $result = "1";//支付失败
            }

        } else {
            $data['order_id'] = $data['orderId'];
            $this->_logOrder($data, 6, 'hw');
            $result = "1";
        }
        $res = "{\"result\": $result} ";
        echo $res;
    }

    //安智

    /**
     * 小米回调
     */
    public function xiaomi()
    {
        $data = $_GET;
        $ret = $this->_check_params($data, $this->_xm_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['order_id'] = $data['orderId'];
            $this->_logOrder($data, 7, 'xm');
            $ret['errcode'] = 7;
            exit(json_encode($ret));
        }

        if ($this->_check_xm_order($data, 'xm', $this->_xm_order_params)) {
            $ret = $this->_push_xm_order($data, 'xm');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                exit(json_encode(array('errcode' => 200, 'errMsg' => 'ok')));
            } else {
                exit(json_encode(array('errcode' => $ret['ret'], 'errMsg' => $ret['msg'])));
            }
        } else {
            $data['order_id'] = $data['orderId'];
            $this->_logOrder($data, 6, 'xm');
            exit(json_encode(array('errcode' => 1525, 'errMsg' => 'signature_error')));
        }
    }

    /**
     * vivo 回调
     */
    public function vivo()
    {
        $data = $_POST;
        $ret = $this->_check_params($data, $this->_vivo_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['order_id'] = $data['orderNumber'];
            $this->_logOrder($data, 7, 'vivo');
            $ret['errcode'] = 7;
            exit(json_encode($ret));
        }
        if ($this->_check_vivo_order($data, 'vivo')) {
            $ret = $this->_push_vivo_order($data, 'vivo');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                exit(json_encode(array('errcode' => 0, 'msg' => 'ok')));
            } else {
                exit(json_encode(array('errcode' => $ret['ret'], 'msg' => $ret['msg'])));
            }
        } else {
            $data['order_id'] = $data['orderNumber'];
            $this->_logOrder($data, 6, 'vivo');
            exit(json_encode(array('errcode' => 6)));
        }

    }

    /**
     * 靠谱回调
     */
    public function kaopu()
    {
        $data = $_GET;
        $kaopu = pc_base::load_config('android', 'kaopu');
        $ret = $this->_check_params($data, $this->_kaopu_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['orderid'] = $data['kpordernum'];
            $this->_logOrder($data, 7, 'kaopu');
            $ret['code'] = "1004";
            $ret['msg'] = 'wrong_params';
            $ret['sign'] = md5($ret['code'] . '|' . $kaopu['PayKey']);
            exit(json_encode($ret));
        }
        if ($this->_check_kaopu_order($data, 'kaopu', $this->_kaopu_order_params)) {
            $ret = $this->_push_kaopu_order($data, 'kaopu');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                exit(json_encode(array('code' => "1000", 'msg' => 'ok', 'sign' => md5("1000" . '|' . $kaopu['PayKey']))));
            } else {
                exit(json_encode(array('code' => $ret['ret'], 'msg' => $ret['msg'], 'sign' => md5($ret['code'] . '|' . $kaopu['PayKey']))));
            }
        } else {
            $data['orderid'] = $data['kpordernum'];
            $this->_logOrder($data, 6, 'kaopu');
            exit(json_encode(array('code' => "1002", 'msg' => 'wrong_sign', 'sign' => md5("1002" . '|' . $kaopu['PayKey']))));
        }
    }

    /**
     * 优酷回调
     */
    public function youku()
    {
        $data = $_POST;
        $ret = $this->_check_params($data, $this->_youku_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['orderid'] = $data['apporderID'];
            $this->_logOrder($data, 7, 'youku');
            $ret['status'] = 'failed';
            $ret['desc'] = 'wrong_params';
            exit(json_encode($ret));
        }

        if ($this->_check_youku_order($data, 'youku')) {
            $ret = $this->_push_youku_order($data, 'youku');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                exit(json_encode(array('status' => 'success', 'desc' => 'ok')));
            } else {
                exit(json_encode(array('status' => 'failed', 'desc' => $ret['msg'])));
            }
        } else {
            $data['orderid'] = $data['apporderID'];
            $this->_logOrder($data, 6, 'youku');
            exit(json_encode(array('status' => 'failed', 'desc' => 'wrong_sign')));
        }
    }

    /**
     * 豌豆荚回调
     */
    public function wandoujia()
    {
        $data = $_POST;
        $content = stripslashes($data['content']);
        $order = json_decode($content, TRUE);
        $ret = $this->_check_params($data, $this->_wandoujia_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['orderid'] = $order['orderId'];
            $this->_logOrder($data, 7, 'wandoujia');
            exit('failed');
        }

        $sign = $data['sign'];
        $wrsa = new WRsa();
        $result = $wrsa->verify($content, $sign);
        if ($result) {
            $order['signType'] = $data['signType'];
            $order['sign'] = $data['sign'];
            $ret = $this->_push_wandoujia_order($order, 'wandoujia');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                exit('success');
            } else {
                exit('failed');
            }
        } else {
            $data['orderid'] = $order['orderId'];
            $this->_logOrder($data, 6, 'wandoujia');
            exit('failed');
        }
    }

    /**
     * oppo回调
     */
    public function oppo()
    {
        $data = $_POST;
        $ret = $this->_check_params($data, $this->_oppo_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['orderid'] = $data['notifyId'];
            $this->_logOrder($data, 7, 'oppo');
            exit('result=FAILED&resultMsg=loat_params');
        }
        $contents = $data;
        $oppo = new OppoRsa();
        $str_contents = "notifyId={$contents['notifyId']}&partnerOrder={$contents['partnerOrder']}&productName={$contents['productName']}&productDesc={$contents['productDesc']}&price={$contents['price']}&count={$contents['count']}&attach={$contents['attach']}";
        $sign = base64_decode($data['sign']);
        if ($oppo->verify($str_contents, $sign)) {
            $ret = $this->_push_oppo_order($data, 'oppo');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                exit('result=OK&resultMsg=OK');
            } else {
                exit('result=FAILED&resultMsg=' . $ret['msg']);
            }
        } else {
            $order['orderid'] = $order['notifyId'];
            $this->_logOrder($order, 6, 'oppo');
            exit('result=FAILED&resultMsg=wrong_sign');
        }

    }

    /**
     * 虫虫助手回调
     */
    public function cczs()
    {
        $data = $_POST;
        $ret = $this->_check_params($data, $this->_cchong_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['orderid'] = $data['transactionNo'];
            $this->_logOrder($data, 7, 'chongchong');
            echo('FAILED');
        }

        if ($this->_check_chongchong_orders($data, 'chongchong') && $data['statusCode'] = '0000') {
            $ret = $this->_push_chongchong_order($data, 'chongchong');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                exit('SUCCESS');
            } else {
                exit('FAILED');
            }
        } else {
            $data['orderid'] = $data['transactionNo'];
            $this->_logOrder($data, 6, 'chongchong');
            echo('FAILED');
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
        if ($this->check_an_tj($str, $json)) {
            $ret = $this->_push_an_tj_order($json, 'tj');
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
     * 极品天机回调
     */
    public function jptj()
    {
        $data = $_POST;
        $ret = $this->_check_params($data, $this->_tj_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $order = json_decode(stripslashes($data['transdata']), TRUE);
            $data['orderid'] = $order['transid'];
            $this->_logOrder($data, 7, 'jptj');
            echo 'FAILURE';
            exit();
        }
        $json = array();
        $str = http_build_query($data);
        if ($this->check_an_jptj($str, $json)) {
            $ret = $this->_push_an_jptj_order($json, 'jptj');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                echo 'SUCCESS';
            } else {
                echo 'FAILURE';
            }
        } else {
            $order = json_decode(stripslashes($data['transdata']), TRUE);
            $data['orderid'] = $order['transid'];
            $this->_logOrder($data, 6, 'jptj');
            echo 'FAILURE';
        }
    }

    /**
     * 悠悠村回调
     */
    public function uucun()
    {
        $data = $_POST;
        $ret = $this->_check_params($data, $this->_yyc_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $this->_logOrder($data, 7, 'yyc');
            echo 'LOSTPARAM';
            exit();
        }
        $yyc = pc_base::load_config('android', 'yyc');
        $des = new DESCrypt($yyc['DesKey'], 0);
        $desappkey = new DESCrypt('2SoXIhFB', 0);
        $rsp = $des->decrypt($data['callback_rsp']);
        $user = $des->decrypt($data['callback_user']);
        $appkey = $desappkey->decrypt($data['callback_appkey']);

        if (!$rsp || !$appkey) {
            $this->_logOrder($data, 9, 'yyc');
            echo '1';
            exit();
        }
        $arsp = $this->_get_str_params($rsp);
        $auser = $this->_get_str_params($user, '#');
        $aappkey = $this->_get_str_params($appkey);
        $order = array_merge($arsp, $auser, $aappkey);

        if ($this->check_an_yyc($order)) {
            if ($order['rsp_code'] != '000000') {
                $order['orderid'] = $order['txn_seq'];
                $this->_logOrder($order, 10, 'yyc');
                echo '1';
                return;
            }
            $ret = $this->_push_an_yyc_order($order, 'yyc');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                echo '1';
            } else {
                echo '1';
            }
        } else {
            $order['orderid'] = $order['txn_seq'];
            $this->_logOrder($data, 6, 'yyc');
            echo '1';
        }
    }

    /**
     * 猎豹回调
     */
    public function liebao()
    {
        $data = $_POST;
        $ret = $this->_check_params($data, $this->_liebao_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $this->_logOrder($data, 7, 'liebao');
            echo 'success';
            return;
        }

        if (isset($data['orderid'])) {
            $data['orderid'] = strval($data['orderid']);
        }
        if ($this->check_an_liebao($data, 'liebao', $this->_liebao_order_params)) {
            $ret = $this->_push_an_liebao_order($data, 'liebao');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                echo 'success';
            } else {
                echo 'success';
            }
        } else {
            $this->_logOrder($data, 6, 'liebao');
            echo 'success';
        }
    }

    /**
     * 卓易回调
     */
    public function zy()
    {
        $data = $_POST;
        $ret = $this->_check_params($data, $this->_zhuoyi_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['orderid'] = $data['Recharge_Id'];
            $this->_logOrder($data, 7, 'zhuoyi');
            echo('failure');
            return;
        }
        $zhuoyi = pc_base::load_config('android', 'zhuoyi');
        if (verifySignature($data, $zhuoyi['PayKey'])) {
            if ($data['Pay_Status'] != 1) {
                echo 'failure';
            }
            $ret = $this->_push_an_zhuoyi_order($data, 'zhuoyi');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                echo 'success';
            } else {
                echo 'success';
            }
        } else {
            $data['orderid'] = $data['Recharge_Id'];
            $this->_logOrder($data, 6, 'zhuoyi');
            echo 'failure';
        }
    }

    /**
     * 朋友玩回调
     */
    public function pyw()
    {
        $body = file_get_contents('php://input');
        $data = json_decode($body, TRUE);
        $ret = $this->_check_params($data, $this->_pyw_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['orderid'] = $data['ch_orderid'];
            $this->_logOrder($data, 7, 'pyw');
            echo json_encode(array('ack' => $ret['code'], 'msg' => $ret['msg']));
            return;
        }
//         file_put_contents('pyw.log', print_r($data,1)."\n",FILE_APPEND);
        if ($this->check_an_pyw($data, 'pyw')) {
            $ret = $this->_push_an_pyw_order($data, 'pyw');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                echo json_encode(array('ack' => 200, 'msg' => 'OK'));
            } else {
                echo json_encode(array('ack' => $ret['code'], 'msg' => $ret['msg']));
            }
        } else {
            $data['orderid'] = $data['ch_orderid'];
            $this->_logOrder($data, 6, 'pyw');
            echo json_encode(array('ack' => 6, 'msg' => 'wrong_sign'));
        }
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
        $xy = pc_base::load_config('android', 'xy');
        /**
         * 签名校验
         */
        $sign = $data['sign'];
        $mysign = $this->_gen_safe_sign($data, $xy['AppKey']);
        if ($sign != $mysign) {
            //             echo $mysign;
            $this->_logOrder($data, 6, 'xy');
            $arr = array('ret' => 6, 'msg' => 'App签名错误');
            exit(json_encode($arr));
        }
        $sign = $data['sig'];
        $mysign = $this->_gen_safe_sign($data, $xy['PayKey']);
        if ($sign != $mysign) {
            //               echo $mysign;
            $this->_logOrder($data, 10, 'xy');
            $arr = array('ret' => 6, 'msg' => '支付签名错误');
            exit(json_encode($arr));
        }
        $ret = $this->_push_an_xy_order($data, 'xy');
        echo json_encode($ret);

    }

    /**
     * 峰助手回调
     */
    public function feng()
    {
        $data = $_POST;
        $ret = $this->_check_params($data, $this->_fzs_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['orderid'] = $data['orderId'];
            $this->_logOrder($data, 7, 'fzs');
            exit('fail');
        }
        if ($this->check_an_fzs($data, 'fzs')) {
            $ret = $this->_push_an_fzs_order($data, 'fzs');
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                exit('success');
            } else {
                exit('fail');
            }
        } else {
            $data['orderid'] = $data['orderId'];
            $this->_logOrder($data, 6, 'fzs');
            exit('fail');
        }
    }

    /**
     * 梦城回调
     */
    public function mengcheng()
    {
        return $this->__mengcheng('mengcheng');
    }

    /**
     * 梦城回调
     * @param string $channel 子渠道
     */
    private function __mengcheng($channel)
    {
        $data = $_POST;
        $ret = $this->_check_params($data, $this->_mengcheng_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $this->_logOrder($data, 7, $channel);
            exit('{failed}');
        }
        if ($this->_check_mengcheng_order($data, $channel) && $data['status'] == 1) {
            $subchannel = 'no_channel';
            if (isset($data['channel']) && isset($this->_mc_channels[$data['channel']])) {
                $subchannel = $this->_mc_channels[$data['channel']];
            }
            $ret = $this->_push_mengcheng_order($data, $channel, $subchannel);
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                exit('{success}');
            } else {
                exit ('{failed}');
            }
        } else {
            $this->_logOrder($data, 6, $channel);
            exit ('{failed}');
        }
    }

    /**
     * 易接回调
     */
    public function one()
    {
        return $this->__yijie('one');
    }

    /**
     * 易接回调
     * @param string $channel 子渠道
     */
    private function __yijie($channel)
    {
        $data = $_GET;
        $ret = $this->_check_params($data, $this->_yijie_order_params);
        if (isset($ret['msg']) && $ret['msg']) {
            $data['orderid'] = $data['ssid'];
            $this->_logOrder($data, 7, $channel);
            echo('FAILED');
        }

        if ($this->_check_yijie_orders($data, $channel, $this->_yijie_order_params)) {
            $subchannel = 'no_channel';
            if (isset($data['sdk']) && isset($this->_yj_channels[$data['sdk']])) {
                $subchannel = $this->_yj_channels[$data['sdk']];
            }
            $ret = $this->_push_yijie_order($data, $channel, $subchannel);
            if (isset($ret['ret']) && $ret['ret'] == 0 || $ret['ret'] == 4) {
                exit('SUCCESS');
            } else {
                exit('FAILED');
            }
        } else {
            $data['orderid'] = $data['ssid'];
            $this->_logOrder($data, 6, $channel);
            echo('FAILED');
        }

    }

}