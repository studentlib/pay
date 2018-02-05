<?php
/**
 * 修复军团数据
 */

define('ZONE_ID',101000000);
$ip="120.132.55.112";
$port=6379;
$index=0;
$redis=new Redis();
$redis->connect($ip,$port);
$redis->select($index);
$leagues=$redis->hGetAll('League');
$fmtlg='Iid/Irank/Ilevel/Iexp/Iauto/Smcount/Smaxcount/Imoney/Ileader/Itleader/Ictime/a32name/a256broad/I5flag/I3cond/I8boss/Idate/Iactive/Ilogout';
$fmt_mb='Imid/Snlen/a32mname/Imlevel/Imviplevel/Imop/Imbp/Imtdc/Imgexp/Imfcont/Immainid/Imcampid/Imcpos/Imlegong/Imjtime/Imofftime/Cmstatus';
$fmt_info="Iuid/a65acount/a65name/Ipic_id/Csex/Iexp/Clevel/Ivipexp/Cviplevel/Ilogout_time/igold/idiamond/".
"Sap/Sapnum/Sapbuynum/Sapmax/Sstamina/Sstamina_max/Igong/Ihonor/Smapid/crace/Icreate_time/a348card/cgmlevel/".
"Ijade/Ifirstapretime/Isecondapretime/Ifirststaminaretime/Isecondstaminaretime/Iapfrompillvalue/Istaminafrompillvalue/".
"Isoul/Iapfriend/Ilastchattime/Irelive/Iexploit/Ibattlepower/Ifodder/Inationcontribute/Ileagueid/Inationid/Iscore/Icar/Iflag".
"/IstaminaBuyCount/IapBuy/IstaminaBuy/IapBuyToday/IstaminaBuyToday/ImainHeroId";

$str=$redis->get('RoleInfo:129000092');
echo strlen($str);
$data=unpack($fmt_info, $str);
print_r($data);
$dstr=pack('a149Ia488',substr($str, 0,149),1450147712,substr($str,153));
// echo strlen($dstr);

$data=unpack($fmt_info, $dstr);
print_r($data);

// $redis->set('RoleInfo:129000092',$dstr);


// foreach ($leagues as $k=>$value)
// {
// //     echo strlen($value);
//     $league=unpack($fmtlg, $value);
//     if(!$league)
//     {
//         continue;
//     }
// //     echo intval($league['id'])&0x000FF;
// //     echo intval($league['id']);
// // echo base_convert(101000001, 10, 2),"\n";
// // echo base_convert(65535, 10, 2),"\n";
// // echo base_convert(hexdec(0xffff), 16, 2),"\n";
// // echo base_convert(65535, 10, 16),"\n";
// // echo decbin(0xffff)&decbin(101000001),"\n";
// // echo decbin(101000001);
// //     echo 101000001&65535;
// // echo 101000001%;
// //     exit();
// //     $mod_base=($league['id']<<16)>>16;
//     $nleagueid=ZONE_ID+(intval($league['id'])%ZONE_ID);
// //     print_r($league);
// //     print_r($nleagueid);
//     $dt=pack('Ia400',$nleagueid,substr($value,4));
//     $members=$redis->hGetAll('League:'.$league['id'].':1');
//     foreach ($members as $kmb=>$vmb)
//     {
//        $member=unpack($fmt_mb, $vmb);
//        if($member['mid'])
//        {
//            $info_data=$redis->get('RoleInfo:'.$member['mid']);
//            $info=unpack($fmt_info, $info_data);
//            if($info['leagueid']==$league['id'])
//            {
//                $info_dt=pack('a597Ia40',substr($info_data,0,597),$nleagueid,substr($info_data, 601));
//                $redis->set('RoleInfo:'.$member['mid'],$info_dt);
//                $redis->hset('LeagueStatus',pack('I',$member['mid']),pack('I',$nleagueid));
//            }else{
//                $redis->hdel('League:'.$league['id'].':1',$kmb);
//            }
//        }
//     }
    
//     $lkeys=$redis->keys('League:'.$league['id'].':*'); 
//     foreach ($lkeys as $k1=>$v1)
//     {
//         $tmp=explode(':', $v1);
//         if(count($tmp)==3)
//         {
//              $newKey=$tmp[0].':'.$nleagueid.':'.$tmp[2];
//              $redis->rename($v1,$newKey);
//         }
//     }
//     $redis->hdel('League',$k);
//     $redis->hSet('League',pack('I',$nleagueid),$dt);
    
// }

