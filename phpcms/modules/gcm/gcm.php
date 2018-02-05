<?php
defined('IN_PHPCMS') or exit('No permission resources.');
define("GOOGLE_API_KEY_HK", "AIzaSyAAaPIfEkMdDF8W6O80W9IWBUqFyL2yNXU");//tw  : AIzaSyBNzORFHbTSjVKJ7oAEwR7jMN7PAxLJrss
define("GOOGLE_API_KEY_TW", "AIzaSyBNzORFHbTSjVKJ7oAEwR7jMN7PAxLJrss");
define("GOOGLE_GCM_URL", "https://android.googleapis.com/gcm/send");
class gcm  {
    /**
     * @var Redis
     */
    private $_redis;
    
    /**
     * @var advertise_model
     */
    private $_gcm;
    
    
    public function __construct() {
        $this->_gcm=pc_base::load_model('gcm_model');
    }   
    
    public function index(){
	$data=$_REQUEST;
	$arr=$this->_gcm->select('`uid`='.$data['uid']);
	if($arr){
		$this->_gcm->update($data,'`uid`='.$data['uid']);
	}else{
		 $this->_gcm->insert($data);
	}
	$arr=$this->Config();
	//echo 'ok';
	file_put_contents('gcm.log',date('Y-m-d H:i:s')."gcm:".json_encode($data,JSON_UNESCAPED_UNICODE).PHP_EOL,FILE_APPEND);
    }

    public function AMGetSpirit(){
	$config=$this->Config();
	$data=$this->_gcm->select('','*');
	$arr=array();
/*	foreach($data as $k=>$v){
		if($v['channel']=='antxpghk'){
			$arr['antxpghk']['channel']='antxpghk';
			$arr['antxpghk']['token'][]=$v['token'];
			$arr['antxpghk']['data']['title']=$config[1]['title'];
			$arr['antxpghk']['data']['message']=$config[1]['message'];
			$arr['antxpghk']['key']=GOOGLE_API_KEY_HK;
		}
		if($v['channel']=='antxgptw'){
                        $arr['antxgptw']['channel']='antxpghk'; 
                        $arr['antxgptw']['token'][]=$v['token'];
                        $arr['antxgptw']['data']['title']=$config[1]['title'];
                        $arr['antxgptw']['data']['message']=$config[1]['message'];
			$arr['antxgptw']['key']=GOOGLE_API_KEY_TW;
                }
	}
	foreach($arr as $k=>$v){
		$this->send_gcm_notify($v);
	}
*/
	$keys['antxpghk']=GOOGLE_API_KEY_HK;
	$keys['antxgptw']=GOOGLE_API_KEY_TW;
	foreach($data as $k=>$v){
		$arr['token']=$v['token'];
		$arr['data']['title']=$config[1]['title'];
		$arr['data']['message']=$config[1]['message'];
		$arr['key']=$keys[$v['channel']];var_dump($arr);exit;
		$this->send_gcm_notify($arr);
	}
    } 

    public function PMGetSpirit(){
	 $config=$this->Config();
        $data=$this->_gcm->select('','*');
        $arr=array();
	$keys['antxpghk']=GOOGLE_API_KEY_HK;
        $keys['antxgptw']=GOOGLE_API_KEY_TW;
        foreach($data as $k=>$v){
                $arr['token']=$v['token'];
                $arr['data']['title']=$config[2]['title'];
                $arr['data']['message']=$config[2]['message'];
                $arr['key']=$keys[$v['channel']];
                $this->send_gcm_notify($arr);
        }
    }

    public function ArenaChange(){
	$config=$this->Config();
        $data=$this->_gcm->select('','*');
        $arr=array();
        $keys['antxpghk']=GOOGLE_API_KEY_HK;
        $keys['antxgptw']=GOOGLE_API_KEY_TW;
        foreach($data as $k=>$v){
                $arr['token']=$v['token'];
                $arr['data']['title']=$config[3]['title'];
                $arr['data']['message']=$config[3]['message'];
                $arr['key']=$keys[$v['channel']];
                $this->send_gcm_notify($arr);
        }
    }

    public function GuildBattleAlarm(){
	$config=$this->Config();
        $data=$this->_gcm->select('','*');
        $arr=array();
        $keys['antxpghk']=GOOGLE_API_KEY_HK;
        $keys['antxgptw']=GOOGLE_API_KEY_TW;
        foreach($data as $k=>$v){
                $arr['token']=$v['token'];
                $arr['data']['title']=$config[4]['title'];
                $arr['data']['message']=$config[4]['message'];
                $arr['key']=$keys[$v['channel']];
                $this->send_gcm_notify($arr);
        }
    }

    public function send_gcm_notify($data) {
 
	$fields = array(
		//'to'		   => $reg_id,
		'to' => 	    $data['token'],
	        //'registration_id'  => array( $reg_id),
        	'data'              => $data['data'],
    	);
 
    	$headers = array(
        	'Authorization: key=' . $data['key'],
        	'Content-Type: application/json'
    	);
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, GOOGLE_GCM_URL);
    	curl_setopt($ch, CURLOPT_POST, true);
    	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
 
    	$result = curl_exec($ch);
    	if ($result === FALSE) {
        	echo " send gcm failed \n";
		die('Problem occurred: ' . curl_error($ch));
	}
 
    	curl_close($ch);
    	echo $result;
    }
    
   public function Config()
    {
	 $ret = array();
        if(file_exists(CACHE_PATH."channels/PushMessagesAnd.xml"))
	{
        //simplexml_load_file
        $xml=file_get_contents(CACHE_PATH."channels/PushMessagesAnd.xml");
        $xml=simplexml_load_string($xml);
          foreach ($xml->row as $v) {
            foreach ($v->attributes() as $k1 => $v1) {
                $arr[(string) $k1] = (string) $v1;
            }
            $ret[$arr['MessagesID']] = $arr;
          }
        }
        return $ret;
    }

}
