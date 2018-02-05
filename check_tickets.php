<?php
date_default_timezone_set('Asia/Shanghai');
ini_set('output_buffering','off');
define('MYSQL_IP','123.59.42.77');
define('MYSQL_USER','sg');
define('MYSQL_PWD','f3%DEc*io7');
define('MYSQL_PORT','3306');
define('MYSQL_DB','PayCenter_ios');
define('MYSQL_TABLE','tickets');
define('MYSQL_CHARSET','UTF-8');
define('CHECK_URL_SANDBOX' , 'https://sandbox.itunes.apple.com/verifyReceipt');
define('CHECK_URL','https://buy.itunes.apple.com/verifyReceipt');
define('PAY_KEY' , '4ae4f0e2b72246de9a95d183e2503e64');
define('DAY_SECONDS' , 86400);//订单有效期超过这个时间认为是过期订单让玩家找客服人为干预
$link=NULL;
$sand_boxes=array(999,0);
$bid='com.tj.sbdsg.ios.appstore';
$productions=array('sbdsg_6','sbdsg_30','sbdsg_98','sbdsg_198','sbdsg_328','sbdsg_648','sbdsg_yueka','sbdsg_zzk');
$prodectmaptoitem=array("sbdsg_6"=>"100001","sbdsg_30"=>"100002","sbdsg_98"=>"100003","sbdsg_198"=>"100004", "sbdsg_328"=>"100005","sbdsg_648"=>"100006","sbdsg_yueka"=>"100009","sbdsg_zzk"=>"100010");
 
function GetMySql()
{
    global $link;
    //注意这里 mysql 超时会断开这里要做重连
    if($link==NULL||!mysql_ping($link))
    {
        $link=mysql_connect(MYSQL_IP.':'.MYSQL_PORT,MYSQL_USER,MYSQL_PWD,TRUE);
        mysql_set_charset(MYSQL_CHARSET);
        mysql_select_db(MYSQL_DB);
    }
    return $link;
}
function __log($str)
{
    echo date('Y-m-d H:i:s').' '.$str,"\n";
}
function ping()
{
    global $link;
    mysql_ping($link);
}

function query($sql)
{
    global $link;
    GetMySql();
    return mysql_query($sql,$link);
}
function free($res)
{
    if(is_resource($res))
    {
        return mysql_free_result($res);
    }
}

function getList($sql)
{
    global $link;
    $res=query($sql);
    $ret=array();
    if($res)
    {
        while($row=mysql_fetch_assoc($res))
        {
            $ret[]=$row;
        } 
        free($res);
    }else{
        echo mysql_errno($link),mysql_error($link),"\n";
    }
    return $ret;
}
/**
 * 解析苹果小票
 * @param string $ticket
 * @param string $underline
 */
function getTicket($ticket,$underline=FALSE)
{
    $mc=array();
    $ret=array();
    if(preg_match_all('/"(.*)" \= "(.*)"/',base64_decode($ticket),$mc))
    {
        if(isset($mc[1])&&isset($mc[2])&&is_array($mc[1])&&is_array($mc[2]))
        {
            $count=count($mc[1]);
            for($i=0;$i<$count;++$i)
            {
                $key=$mc[1][$i];
                if($underline==TRUE)
                {
                    $key=str_replace('-', '_', $key);
                }
                 $ret[$key]=$mc[2][$i];
            }
        }
    }
    return $ret;
}

/**
 * 检查appstore小票是否合法
 * @param string $ticket
 * @param number $production
 */
function check_tickets($ticket,$production)
{
        $str='';
        $data=array(
        'receipt-data'=>$ticket,
        'password'=>PAY_KEY,
        );
        $ch=NULL;
        if($production==0)
        {
            $ch = curl_init(CHECK_URL_SANDBOX);
        }else{
            $ch = curl_init(CHECK_URL);
        }
        
        curl_setopt($ch, CURLOPT_POST,TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_USERAGENT, 'DJChargeRobot_1.0');
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $str = curl_exec($ch);
        $ret=json_decode($str,true);
        return $ret;
}
echo $processor=gethostbyname(gethostname()).'@'.getmypid ()," runat:",date('Y-m-d H:i:s'),"\n";
$ping_count=200;
$count=0;
$deal_cout=1;
while(1)
{
    $sql=sprintf("update %s set processor='%s'  where status is null and processor=''  limit %d",MYSQL_TABLE,$processor,$deal_cout);
    query($sql);
    $sql=sprintf("select * from %s where status is null and processor='%s' limit %d",MYSQL_TABLE,$processor,$deal_cout);
    $list=getList($sql);
    if(!count($list))
    {
        usleep(100000);
        if($count++>$ping_count)
        {
            $count=0;
            ping();
        }
    }
    
    foreach ($list as $k=>$v)
    {
        $sql=sprintf("select * from orders_list where `orderid`='%s'",$v['orderid']);
        $server=getList($sql);
        $production=1;
        if(isset($server[0]['serverid'])&&in_array($server[0]['serverid'], $sand_boxes))
        {
            $production=0;
        }
        __log("start check order:".$v['orderid']);
        $local_data=getTicket($v['ticket']);
        $local_ticket=getTicket($local_data['purchase-info'],TRUE);
    
    //
//    $local_data_orderinfo=json_decode($v['orderinfo'], true);
    //var_dump($local_data_orderinfo);
        $local_data_orderitemid=$server[0]['itemid'];	
        $local_data_ticketsitemid=$prodectmaptoitem[$local_ticket['product_id']];   
//如果购买时间比订单发起时间还早直接跳过
        if($local_ticket['bid']!=$bid||!in_array($local_ticket['product_id'],$productions)||
        (time()-($local_ticket['purchase_date_ms']/1000))>DAY_SECONDS*5 ){
            __log("Before Check Wrong Ticket");
            __log(print_r($local_ticket,1));
            query(sprintf("update %s set `status`='%d' where `orderid`='%s'",MYSQL_TABLE,-1,$v['orderid']));
            continue;
        }
//物品id不匹配跳过
    if($local_data_orderitemid != $local_data_ticketsitemid){
        __log(" checkitem Before Check Wrong Ticket not the same item id from orders and tickets table ");
            __log(print_r($local_ticket,1));
            query(sprintf("update %s set `status`='%d' where `orderid`='%s'",MYSQL_TABLE,-1,$v['orderid']));
        __log(" checkitem orderinfo itemdid:".$local_data_orderitemid."ticketitemid:".$local_data_ticketsitemid);
        continue;
    }
        
        $st=microtime(true);
        $ret=check_tickets($v['ticket'],$production);
        
        $ed=microtime(true);
        __log("end check order:".$v['orderid']);
        __log("check order cost time:".($ed-$st));
        //5天内的订单有效
        if(isset($ret['status'])&&$ret['status']==0&&$ret['receipt']['bid']==$bid&&in_array($ret['receipt']['product_id'],$productions)
            &&(time()-$ret['receipt']['purchase_date_ms']/1000)<DAY_SECONDS*5)
        {  
            __log("order status:".$ret['status']);
            query(sprintf("update %s set `status`='%d',`appreturn`='%s' where `orderid`='%s'",MYSQL_TABLE,$ret['status'],json_encode($ret),$v['orderid']));
            __log("update order:".$v['orderid']);
        }else{
            __log("After Check Wrong Ticket");
            if(is_null($ret))
            {
                query(sprintf("update %s set `status`=NULL,`appreturn`='%s',processor='' where `orderid`='%s'",MYSQL_TABLE,json_encode($ret),$v['orderid']));
            }else{
                query(sprintf("update %s set `status`='%d',`appreturn`='%s' where `orderid`='%s'",MYSQL_TABLE,-1,json_encode($ret),$v['orderid']));
            }
            __log(print_r($ret,1));
        }
    }
    
}
