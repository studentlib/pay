
<?php
//phpinfo();
if(PHP_INT_SIZE!=8)
{
    exit('NEED_64BIT_PHP');
}
$ip="192.168.2.15";
$port=16379;
$index=0;
$redis=new Redis();
$redis->connect($ip,$port);
$redis->select($index);
$keys=$redis->hkeys('camp:scores');
$mod_array=array();
foreach ($keys as $k=>$v)
{ 
    $pkfmt='Iscore/Isl/Isr';
    $hkey=$redis->hget('camp:scores',$v);
    $dt=unpack($pkfmt, $hkey);
    echo $v;
    print_r($dt);
   
    echo ($dt['sr']<<32)+$dt['sl'],"\n";
    if(in_array($v, $mod_array))
    {
        $pkfmt='III';
        $info=pack($pkfmt,125,$dt['sl'],$dt['sr']);
        $redis->hset('camp:scores',$v,$info);
    }
//     echo $v,$hkey;
//    if($hkey)
//    {
//        echo $hkey;
//        $redis->hdel($v,pack('I',22));
//    }
      
}
echo count($keys);