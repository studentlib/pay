<?php
defined('IN_PHPCMS') or exit('No permission resources.');
pc_base::load_app_class('pay', 'paycenter', 0);

class role extends pay
{

    private $ufmt;

    public function __construct()
    {
        $this->_orders = pc_base::load_model('orders_model');
        $this->_orders_list = pc_base::load_model('orders_list_model');
        $this->_account = pc_base::load_model('account_model');
        $this->_log = pc_base::load_model('log_model');
        $this->ufmt = "Iuid/a65acount/a65name/Ipic_id/Csex/Iexp/Clevel/Ivipexp/Cviplevel/Ilogout_time/igold/idiamond/" .
            "Sap/Sapnum/Sapbuynum/Sapmax/Sstamina/Sstamina_max/Igong/Ihonor/Smapid/crace/Icreate_time/a348card/cgmlevel/" .
            "Ijade/Ifirstapretime/Isecondapretime/Ifirststaminaretime/Isecondstaminaretime/Iapfrompillvalue/Istaminafrompillvalue/" .
            "Isoul/Iapfriend/Ilastchattime/Irelive/Iexploit/Ibattlepower/Ifodder/Inationcontribute/Ileagueid/Inationid/Iscore/Icar/Iflag" .
            "/IstaminaBuyCount/IapBuy/IstaminaBuy/IapBuyToday/IstaminaBuyToday/ImainHeroId";

    }

    public function submitRoleData()
    {
        $data = $_POST;
        file_put_contents('gat.log', date('Y-m-d H:i:s') . '--' . json_encode($data) . PHP_EOL, FILE_APPEND);
        $ret['data'] = $this->getInfo($data['roleId'], $data['zoneId']);//var_dump($ret['data']);
        //$ret['data']=$this->getInfo($data['uid'],$data['serverid'],$data['channel']);
        $ret['id'] = $this->getMillisecond();
        $ret['service'] = 'ucid.game.gameData';
        $ret['game'] = array('gameId' => '758447');
        $key = '984b72d339c8f05e0f04988a4e312963';//var_dump('accountId='.$ret['data']['accountId'].'gameData='.$ret['data']['gameData'].$key);
        $ret['sign'] = strtolower(md5('accountId=' . $ret['data']['accountId'] . 'gameData=' . $ret['data']['gameData'] . $key));
        $ret['data'] = json_encode($ret['data']);
        file_put_contents('gat.log', date('Y-m-d H:i:s') . '--' . json_encode($ret) . PHP_EOL, FILE_APPEND);
        $this->sendToAli($ret);
    }

    public function getInfo($id, $serverid, $os)
    {
        $time = array('logout_time', 'create_time', 'firstapretime', 'firststaminaretime', 'lastchattime', 'secondapretime', 'secondstaminaretime');
        $server = $this->getRedisConfig($serverid);
        $redis = $this->getRedis($server['RIP'], $server['RIndex'], $server['RPort']);
        if (!strstr($id, 'RoleInfo:')) {
            $id = 'RoleInfo:' . $id;
        }
        $data = $redis->get($id);

        $data = (unpack($this->ufmt, $data));
        foreach ($data as $k => $v) {
            if (in_array($k, $time) && $v != 0) {
                $data[$k] = date('Y-m-d H:i:s', $v);
            }
        }

        if (is_array($data)) {
            $ret['accountId'] = substr($data['acount'], -32);
            $gameData['content']['category'] = 'loginGameRole';
            $gameData['content']['zoneId'] = $serverid;
            $gameData['content']['zoneName'] = $server['text'];
            $gameData['content']['roleId'] = $data['uid'];
            $gameData['content']['roleName'] = $data['name'];
            $gameData['content']['roleCTime'] = strtotime($data['create_time']);
            $gameData['content']['roleLevel'] = $data['level'];
            $gameData['content']['os'] = 'android';
            $gameData['content']['roleLevelMTime'] = -1;//var_dump($gameData);
            //   $gameData=$this->array_to_object($gameData);//var_dump($gameData);
            //   var_dump($gameData);
            $ret['gameData'] = urlencode(json_encode($gameData));//var_dump($ret);
            return $ret;
        } else {
            return json_encode(array('code' => 1, 'msg' => '该角色不存在'));
        }
    }

    /**
     * 获取毫秒级的时间参数
     *
     */
    private function getMillisecond()
    {
        $time = explode(" ", microtime());
        $time = $time [1] . ($time [0] * 1000);
        $time2 = explode(".", $time);
        $time = $time2 [0];
        return $time;
    }

    public function sendToAli($data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://collect.sdkyy.9game.cn:8080/ng/cpserver/gamedata/ucid.game.gameData');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $result = curl_exec($ch);
        curl_close($ch);
        file_put_contents('gat.log', date('Y-m-d H:i:s') . '--' . json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
        echo $result;
    }

    /**
     * 数组 转 对象
     *
     * @param array $arr 数组
     * @return object
     */
    public function array_to_object($arr)
    {
        if (gettype($arr) != 'array') {
            return;
        }
        foreach ($arr as $k => $v) {
            if (gettype($v) == 'array' || getType($v) == 'object') {
                $arr[$k] = (object)$this->array_to_object($v);
            }
        }

        return (object)$arr;
    }

}   
