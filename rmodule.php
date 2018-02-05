<?php
header("Content-type:text/html;charset=utf-8");
$ip="192.168.2.12";
$port=16379;
$index=0;
$redis=new Redis();
$redis->connect($ip,$port);
$redis->select($index);
$keys=$redis->keys('Module:*:record:0');
foreach ($keys as $k=>$v)
{
    
    $hkey=$redis->hget($v,pack('I',22));
    if($hkey)
    {
        echo $hkey;
        $redis->hdel($v,pack('I',22));
    }
      
}