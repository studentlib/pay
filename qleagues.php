<?php
//redis ip 端口 index 
$ip="123.59.42.21";
$port=6379;
$index=0;
$redis=new Redis();
$redis->connect($ip,$port);
$redis->select($index);

function _getLeagues($leagues)
{
     global $redis;
     $ret=array();
     foreach ($leagues as $k=>$v)
     {
         $league=$redis->hget('League',pack('I',$v));
         if($league)
         {
             $fmt='Iid/Irank/Ilevel/Iexp/Iauto/Smcount/Smaxcount/Imoney/Ileader/Itleader/Ictime/a32name/a256broad/I5flag/I3cond/I8boss/Idate/Iactive/Ilogout';
             $dt=unpack($fmt, $league);
             $ret[$v]=$dt;
         }
     }
    return $dt;
}
function _getLeagueMembers($id)
{
    global $redis;
    $members=$redis->hgetall('League:'.$id.':1');
    $fmt='Imid/Snlen/a32mname/Imlevel/Imviplevel/Imop/Imbp/Imtdc/Imgexp/Imfcont/Immainid/Imcampid/Imcpos/Imlegong/Imjtime/Imofftime/Cmstatus';
    $dt=array();
    foreach ($members as $value) 
    {
        $dt[]=unpack($fmt, $value);
    }
    return $dt;
}

//要查询的军团ID列表
$leagues=array(25,64,66,19,24,59);
$info=array();
foreach ($leagues as $k=>$v)
{
    $info[$v]=_getLeagueMembers($v);
}

print_r($info);
