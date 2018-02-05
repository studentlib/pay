<?php
/**
 * ANDROID充值渠道密钥配置
 */
return array(
// 'baidu'=>array(
//         'AppId'=>6574750,                                     //应用ID
//         'AppKey'=>'04HBWWIw07g7KBBjSV2fcAIB',                 //应用KEY
//         'PayKey'=>'Du8xdMRpZe5O8FSnbhuczhGKVDwORO6x',         //支付KEY
//         'Ratio'=>1,                                           //兑换比例
//         ),
'qbrxsg' => array(
    'AppId' => '60100015',                                     //应用ID（商户id）
    'AppKey' => '',                                            //应用KEY
    'PayId' => '11754',                                         //商品ID
    'PayKey'=>'dd1cd063c5ea8aadbeb0163feacfce35',            //支付KEY
    'Ratio'=>100,                                            //兑换比例
    'AppName'=>'Q版热血三国',                                  //应用名
    'notify_url'=>'http://iosqbrxsg.pay.djly.sg2.dianjianggame.com/qbrxsg.php' //回调地址
),


'baidu'=>array(
    'AppId' => 7574887,                                     //应用ID
    'AppKey' => 'BQKo0DAGGUaSweoeI0f7rg42',                 //应用KEY
    'PayKey' => 'ptpOd5TOusUwzKr8qDUaji7Lx2S3gj0v',         //支付KEY
    'Ratio' => 1,
),
'qihoo'=>array(
    'AppId' => 202607396,                                    //应用ID
    'AppKey' => '812385f2247f40ff7bfa09afdeadaaaf',         //应用KEY
    'PayKey' => '178d49108594401a092a0e0128f3cd14',         //支付KEY
    'Ratio' => 0.01,
),
'uc'=>array(
    'AppId' => 554770,                                     //应用ID
    'AppKey' => '293868bbf5ccfc5d2ec0be0e7940e764',        //应用KEY
    'PayKey' => '293868bbf5ccfc5d2ec0be0e7940e764',         //支付KEY
    'Ratio' => 1,
),
'tx'=>array(
    'AppId' => 1104748911,                                  //应用ID
    'SandAppKey' => 'lpepeKZkWuoEDfpk',                     //应用KEY
    'AppKey' => 'CE92plGT0sS1OYt4zpzDrVeP8SmTluc2',         //应用KEY
    'PayKey' => '3b9868e94cc80be5cab2e61ce85984fc',         //支付KEY
    'Ratio' => 1,
    'TxRatio' => 10,
),
'hw'=>array(
    'AppId' => 10332868,                                    //应用ID
    'AppKey' => '10332868',                                 //应用KEY
    'PayKey' => '10332868',                                 //支付KEY
    'Ratio' => 1,
),
'xm'=>array(
    'AppId' => 2882303761517357638,                              //应用ID
    'AppKey' => '5551735747638',                                 //应用KEY
    'PayKey' => 'P4P9bBDshfZSOsbVjWzr2A==',                      //支付KEY
    'Ratio' => 0.01,
),
'vivo'=>array(
    'AppId' => '20140814142858708116',               //应用ID
    'AppKey' => '95425b1547e863a423d050313dda3faf',              //应用KEY
    'PayKey' => '6fd51c388743aff8edfb1c44d144e7dc',              //支付KEY
    'Ratio' => 0.01,
),
'anzhi'=>array(
    'AppId' => '1439195712XjLkm8zwYq1fh6vRcC6V',               //应用ID
    'AppKey' => '1439195712XjLkm8zwYq1fh6vRcC6V',              //应用KEY
    'PayKey' => '1439195712XjLkm8zwYq1fh6vRcC6V',                      //支付KEY
    'Ratio' => 0.01,
),
'kaopu'=>array(
    'AppId' => '10051001',               //应用ID
    'AppKey' => '10051',              //应用KEY
    'PayKey' => '2549CB8E-01B9-4106-B77D-ECE6DDA45CE3',                      //支付KEY
    'Ratio' => 0.01,
),
'youku'=>array(
    'AppId' => '1886',               //应用ID
    'AppKey' => 'f2cdc05c4a21f779',              //应用KEY
    'PayKey' => 'fa893b38cee15ebc8a2c4b69320a24c3',                      //支付KEY
    'AppSecret' => '22e6025777e28c9f8171af8baa07b6ef',                      //appsecret
    'Ratio' => 0.01,
),
'wandoujia'=>array(
    'AppId' => '100030525',               //应用ID
    'AppKey' => 'ae46b7a5eb9bff76b5821f2d7e8d58c1',              //应用KEY
    'PayKey' => 'ae46b7a5eb9bff76b5821f2d7e8d58c1',              //支付KEY
    'AppSecret' => 'ae46b7a5eb9bff76b5821f2d7e8d58c1',           //appsecret
    'Ratio' => 0.01,
),
'oppo'=>array(
    'AppId' => '4199',                                           //应用ID
    'AppKey' => '7iLzd1JQXC84wWcwSSOgKc0wK',                     //应用KEY
    'PayKey' => '24F5dce88b991fe1f70d475A48DEca6e',              //支付KEY
    'AppSecret' => '24F5dce88b991fe1f70d475A48DEca6e',           //appsecret
    'Ratio' => 0.01,
),
'mengcheng'=>array(
    'AppId' => '50000401',               //应用ID
    'AppKey' => 'f26796cc8492c3a6b186840c24d5a979',              //应用KEY
    'PayKey' => 'f26796cc8492c3a6b186840c24d5a979',              //支付KEY
    'Ratio' => 0.01,
),
'chongchong'=>array(
    'AppId' => '102723',               //应用ID
    'AppKey' => 'b304378536424378af2d7c89542f3208',              //应用KEY
    'PayKey' => 'df7a394972964fe1bfa1a474cc80f4a0',              //支付KEY
    'Ratio' => 1,
),
'one'=>array(
    'AppId' => '102723',               //应用ID
    'AppKey' => 'GR7AD5W54IVLXJL0NY7ZGKZOOA17TLPX',              //应用KEY
    'PayKey' => 'GR7AD5W54IVLXJL0NY7ZGKZOOA17TLPX',              //支付KEY
    'Ratio' => 0.01,
),
'chongchong'=>array(
    'AppId' => '102723',               //应用ID
    'AppKey' => 'b304378536424378af2d7c89542f3208',              //应用KEY
    'PayKey' => 'df7a394972964fe1bfa1a474cc80f4a0',              //支付KEY
    'Ratio' => 1,
),
'yyc'=>array(
    'AppId' => '',               //应用ID
    'AppKey' => 'FVCo1a8Ni1FdTny7y86h8RoSBwygkILO',             //应用KEY
    'PayKey' => '6YxsL6',                                       //支付KEY
    'SecKey' => '6YxsL6',                                       //安全秘钥
    'DesKey' => 'n5ar65le',                                     //DESKey
    'Ratio' => 0.01,
),
'liebao'=>array(
    'AppId' => '149',                                           //应用ID
    'AppKey' => 'bf1583728505904ec75488f04a1b4f7e',             //应用KEY
    'PayKey' => 'bf1583728505904ec75488f04a1b4f7e',             //支付KEY
    'Ratio' => 1,
),
'zhuoyi'=>array(
    'AppId' => '1855_01',                                          //应用ID
    'AppKey' => '672f6a2006da564fd25a659d9b539e77',             //应用KEY
    'PayKey' => '4e46053c36ace7da0aef77c4f2fea56c',             //支付KEY
    'Ratio' => 1,
),
'pyw'=>array(
    'AppId' => '46a0c229',                      //应用ID
    'AppKey' => 'a1a599452901034f',             //应用KEY
    'PayKey' => 'd7358dde2fbe8450',             //支付KEY
    'Ratio' => 1,
),
'xy'=>array(
    'AppId' => 10005138,                                     //应用ID
    'AppKey' => 'pm37xZhbyZaCwTRjorsZQgKRxJQr1KQw',           //应用KEY
    'PayKey' => '7KqmRmt6ss5B8qhy5jk6ylXiXvPfSGNn',           //支付KEY
    'Ratio' => 1,
),
'fzs'=>array(
    'AppId' => 4,                                      //应用ID
    'AppKey' => '3629ba5242e844a9bf86afab3e080791',           //应用KEY
    'PayKey' => '3629ba5242e844a9bf86afab3e080791',           //支付KEY
    'Ratio' => 1,
),
'tj'=>array(
    'AppId' => 3002752436,                                     //应用ID
    //公钥
    'PubKey' => 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCwDqNB3fC2FbmINIIOKq/4EjEqHnxlox2A4XybCGHAz1hwbthNKjYlIukmoQ0SKc3x7lmP0i9JBD3QHec1qve2BIdnzEIM1I5SfNAvinekMIFH7ECrqxReeHt4KlzBQdNZ8VdK0zxeeOTBAdaJJVlqS5ExS8F917rSClpMSevoBQIDAQAB',          //应用KEY
    //私钥
    'PriKey' => 'MIICXAIBAAKBgQCwDqNB3fC2FbmINIIOKq/4EjEqHnxlox2A4XybCGHAz1hwbthNKjYlIukmoQ0SKc3x7lmP0i9JBD3QHec1qve2BIdnzEIM1I5SfNAvinekMIFH7ECrqxReeHt4KlzBQdNZ8VdK0zxeeOTBAdaJJVlqS5ExS8F917rSClpMSevoBQIDAQABAoGBAI191hsTgWb1IryiZnt4NyAJjtWo1pTgeM+haIE4RUet3AfQLaomaImD+xj+igC09DyhL/10EGiALiVaQv1Qv+6EzxxOCrr6PX+CpNXe0Tm8vilDbvVjOYzc3KNQRUthmslJ0aH7yHcd10GtYQt/RDi0fHItWnmdW5w7DPK14tpBAkEA4rePBK1QebQEfnU9DrAE3yHwrL7px5SjoTBHYCbi+NcnTM+9o7eEVKo4iysv83nSpZHdYcaIcrwXzcia9EgtiQJBAMbMAGp1nTvKypNz6u6bhBIAmdGcaHmTTqyrg6CDtwmVqEq2re2v0uhdkc1gU/QoIUEMxz5nNy+38R4bmDisY50CQCY1c1gBcY+hRCSf05N3HMsSKEKkxjeJmG4g+dZ9l0EC2a+7TyWZVycBrRffRmyNOnAG/j1tPS/A/W4EAgFrbKkCQH1Y5i46SNEJth+xaIHZBzZ+sH5te6akzmerocxVINVnSv0JILQNOBQR47w2r9j0cLtefkcHt9Fbzynnxlx9vjUCQHRZXECC27Y5nnjL+TdIEp4h+m2XXLriTGMk3lnx2FeEl9uMPQwhttvhzoQqwwtIKToeF7mJiYx/QsYYMAwlLRg=',          //支付验证key
    //平台公钥
    //'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDCwEjTOS8DodyS8sqikMGitkDTg6d/uhYtl/rUAMzZ0HPfE+PCUuB1qLyTFyTeP0fEI4pk7GiY8WyiscozMiYkbYN/k+tWy9u4itGB5JKevedl+S34CDDne9xZwQHDqp9JuY6VSRdMc7j0F/AGbELjNPubOMcIUmDSllcUAG+shwIDAQAB',
    'PlatPubKey' => 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC7uAyqIEmBh+Sxni0rPzaxIl4U+WFcXXTHZIvAXeTydQGMR2qLgbe5Oq0OdTB0QbYe6K/+eId3CGv56GmkrbZiglsqspU7Fh7TXey5znrukX3wRwoMWTEt9ezSd3Jd3envwoQcDFhorD4ADtghZiKkZiiRPh2YdyU0pXUHfpVDIwIDAQAB',          //支付验证key
    'Ratio' => 1,
),
'jptj'=>array(
    'AppId' => 3003718483,                                     //应用ID
    //公钥
    'PubKey' => 'MIICWwIBAAKBgQDL0plSFPCl3Uu8c9PPsgEqBZxLNhq7hN72/glXfRKBZW0TdCxLg8BZ7UJvSjYlC69tJxWX/3kG3Su1NbvOznhqQpJhqobfUOERnKn0C4UdxNqUUn4NnvC2cc2vcMREqjPjaVFirLmqkz0L74MuLGbec1U6ziJJskLDtaDJ7BVmEQIDAQABAoGAX0EY7kdi/+EgF/55qsMh0CW3GV87tw8ttHicnQqSr25bP5A3c0MrjdtYBPLFwyJm5bgyW0rFoPOUDagDEW9kzk4b+gqhhjiRNlTfpuivgX88Z7D7GD6oQUljNN0+oOlt6QX9qAmDoALs+JhSvoAK1hj26qAg794JHXYm2qHfcxECQQD2IANVjrhVSiR78aLAD5XrT+PikupsdzYQcjlCJRm+Irh/uHrhT7YBBr0qdqlrJocrQEEodW1Cz/4sDFZLMoy/AkEA1AAXdvD9io5GtkjiujzGnWmZW0RLVgsr6eU43INu7OEabj0lp0Zvr2y9ypq4ja+OYa75HsZe70oneFvovWMxLwJAEQcJ9ANZsVzdxU1kSxFI9+yhAW3UlghxTxX37CGQ9FyGkx96MNhI6S1ELQnkkqspss5RM7FcC8FD/lwkJDOtFQJAQRCuIvEfv7Ce/z8rZuFUS+enC53QSV4wYh7Z7sPf/Y+w3vihX898Y6jKLy6BSesBlfVvvARCCssFhikfK1EQPQJAVa/nIFPueVC0L1+WvBb0YMnTgDY/BpE+xDGwbrZ9oU/W7dOSLk81RWtCslqbS/cMJ1cp+zXuqEsu2a6umBNahA==',          //应用KEY
    //私钥
    'PriKey' => 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDOJ+cFcQVYSsiVniUFzu25OnxQV8OLEclgyh3LbSgPscoMLX304FLozmqOvyoPWaHzJSMWJSVPhUCEQltLjgc3dxh6Xnk33qi6y7JR5DB1dabeYjra4kzEJP7bF/uECWvDDSrNOKryoakegB0q7wI7PA2CM/sCmMhfDegIE7kuFQIDAQAB',          //支付验证key
    //平台公钥
    //'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDCwEjTOS8DodyS8sqikMGitkDTg6d/uhYtl/rUAMzZ0HPfE+PCUuB1qLyTFyTeP0fEI4pk7GiY8WyiscozMiYkbYN/k+tWy9u4itGB5JKevedl+S34CDDne9xZwQHDqp9JuY6VSRdMc7j0F/AGbELjNPubOMcIUmDSllcUAG+shwIDAQAB',
    'PlatPubKey' => 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDOJ+cFcQVYSsiVniUFzu25OnxQV8OLEclgyh3LbSgPscoMLX304FLozmqOvyoPWaHzJSMWJSVPhUCEQltLjgc3dxh6Xnk33qi6y7JR5DB1dabeYjra4kzEJP7bF/uECWvDDSrNOKryoakegB0q7wI7PA2CM/sCmMhfDegIE7kuFQIDAQAB',          //支付验证key
    'Ratio' => 1,
),
);