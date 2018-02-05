<?php
/**
 * @var 修复玩家矿山不能被匹配到不能收取问题脚本
 */

$ip="192.168.2.12";
$port=26379;
$index=0;
$redis=new Redis();
$redis->connect($ip,$port);
$redis->select($index);
$hkeys=$redis->hkeys('GlobalUserMine');

foreach ($hkeys as $value)
{
//    UINT32 uid;                //玩家ID
//    UINT32 lv;                 //玩家等级
//    UINT32 score;              //积分
//    UINT32 match_time;         //被匹配到的时间
//    UINT32 pro_end_time;       //保护结束时间
//    UINT32 state;              //状态 0 正常 1 被匹配到未挑战  2 正在被挑战
//    UINT32 mines;              //矿山数量 可以动态扩展不限制数量
    $hv=$redis->hget('GlobalUserMine',$value);
    $fmt='Iuid/Ilv/Iscore/Imatch/Iend/Istate/Imines';
    $dt=unpack($fmt, $hv);
    print_r($dt);
    $pfmt='IIIIIII';
    $info=pack($pfmt,$dt['uid'],$dt['lv'],$dt['score'],0,0,0,$dt['mines']);
//    $dt=unpack($fmt, $info);
//    $redis->hset('GlobalUserMine',$value,$info);
}

