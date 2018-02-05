<?php
$ip="123.59.74.9";
// $ip="192.168.2.15";
$port=6379;
$index=0;
$redis=new Redis();
$redis->connect($ip,$port);
$redis->select($index);
$dt=$redis->hget('GlobalCookie',104);
// $dt=$redis->hgetall('GlobalCookie');
$len=unpack('Ilen', $dt);
var_dump($len);
$dtstr=substr($dt, 4);
echo strlen($dt);
foreach (range(1,$len['len']) as $k=>$v)
{
    $fmt='Ikey/Itid/Icount/Iprop/Ctype';
    $data=unpack($fmt,$dtstr);
    $dtstr=substr($dtstr,17);
    print_r($data);
}
//    $pfmt='IIII';
//    $info=pack($pfmt,107000102,107001221,0,0);
//    $redis->hset('Module:107000102:mine:3',pack('I',107000102),$info);