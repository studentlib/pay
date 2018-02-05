<?php
/**
 * @var 修复玩家矿山收取不到宝石问题脚本
 */
$ip="192.168.2.12";
$port=26379;
$index=0;
$redis=new Redis();
$redis->connect($ip,$port);
$redis->select($index);
$hkeys=$redis->hkeys('GlobalMines');

foreach ($hkeys as $value) {
    $hkey=$redis->hget('GlobalMines',$value);
    if($hkey)
    {
    //    UINT32 mid;              //矿山编号 uid<<2+id
    //    UINT32 id;               //矿山序号
    //    UINT32 uid;              //玩家ID
    //    UINT32 type;             //矿山类型
    //    UINT32 last_item_time;   //上次收取物品时间
    //    UINT32 speed_end_time;   //加速结束时间
    //    UINT32 defend_form_id;   //关联防御阵型ID
    //    UINT32 defend_skill_id;  //关联天赋ID
    //    UINT32 last_reward_time; //上一次收取时间
    //    UINT32 be_rob_item_count;//被抢物品次数
    //    UINT32 be_rob_amount;    //被抢数量
        $fmt='Imid/Iid/Iuid/Itype/Ilast_item_time/Ispeed_end_time/Idefend_form_id/Idefend_skill_id/Ilast_reward_time/Ibe_rob_item_count/Ibe_rob_amount';
        $dt=unpack($fmt, $hkey);
        print_r($dt);
//        if($dt['be_rob_item_count'])
//        {
//            echo 'wrong',"\n";
//        }
        if($dt)
        {
            $pfmt='IIIIIIIIIII';
            $info=pack($pfmt,$dt['mid'],$dt['id'],$dt['uid'],$dt['type'],$dt['last_item_time'],$dt['speed_end_time'],$dt['defend_form_id']
            ,$dt['defend_skill_id'],$dt['last_reward_time'],0,$dt['be_rob_amount']);
            $dt=unpack($fmt, $info);
//            print_r($dt);
//            $redis->hset('GlobalMines',$value,$info);
        }
    }
}

