<?php
/**
 * rsa PP
 * 需要 openssl 支持
 * @author dietoad
 *
 */
class MyRsa extends Rsa
{
    private static $_instance;
 
    const private_key = '
-----BEGIN RSA PRIVATE KEY-----
MIICXAIBAAKBgQDA4E8H2qksOnCSoBkq+HH3Dcu0/iWt3iNcpC/BCg0F8tnMhF1Q
OQ98cRUM8eeI9h+S6g/5UmO4hBKMOP3vg/u7kI0ujrCN1RXpsrTbWaqry/xTDgTM
8HkKkNhRSyDJWJVye0mPgbvVnx76en+K6LLzDaQH8yKI/dbswSq65XFcIwIDAQAB
AoGAU+uFF3LBdtf6kSGNsc+lrovXHWoTNOJZWn6ptIFOB0+SClVxUG1zWn7NXPOH
/WSxejfTOXTqpKb6dv55JpSzmzf8fZphVE9Dfr8pU68x8z5ft4yv314qLXFDkNgl
MeQht4n6mo1426dyoOcCfmWc5r7LQCi7WmOsKvATe3nzk/kCQQDp1gyDIVAbUvwe
tpsxZpAd3jLD49OVHUIy2eYGzZZLK3rA1uNWWZGsjrJQvfGf+mW+/zeUMYPBpk0B
XYqlgHJNAkEA0yhhu/2SPJYxIS9umCry1mj7rwji5O2qVSssChFyOctcbysbNJLH
qoF7wumr9PAjjWFWdmCzzEJyxMMurL3gLwJBAIEoeNrJQL0G9jlktY3wz7Ofsrye
j5Syh4kc8EBbuCMnDfOL/iAI8zyzyOxuLhMmNKLtx140h0kkOS6C430M2JUCQCnM
a5RX/JOrs2v7RKwwjENvIqsiWi+w8C/NzPjtPSw9mj2TTd5ZU9bnrMUHlnd09cSt
yPzD5bOAT9GtRVcCexcCQBxXHRleikPTJC90GqrC2l6erYJaiSOtv6QYIh0SEDVm
1o6Whw4FEHUPqMW0Z5PobPFiEQT+fFR02xU3NJrjYy0=
-----END RSA PRIVATE KEY-----';
    const public_key = '-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAvuf9efgKJQHNZtnqhNk1
bdsPnquDXqbyczBg8gTEasKk6QMTe4Ss+DbD/79x5Ee4YnLVBTY8B/hKinP5Rwgo
TEq0TadBB3KCShgHBMSJyCkoDk9xiEBmoH97ACyU1ZT8gOECstPP4tAHPj5o7w6m
QgWDgbATNdRIoFF1J7LLa4LtghWgNlSMPdHn2T5rJ4IFam5dC/fNLy3eMPJkeNe1
VV1/2CaqZ/8w2uhjOgwpjdyfhKRlrNBsjuI+QNOJhVcMxVRu0jpCNEvNW6qYbOTh
MApPzVHdtS+pkyXGWfvi24p1i/LLMc3/zDtb5j83TAfhrme24bT5I6FsLBzbFlTC
awIDAQAB
-----END PUBLIC KEY-----';
//    const public_key = '
//-----BEGIN PUBLIC KEY-----
//MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDA4E8H2qksOnCSoBkq+HH3Dcu0
///iWt3iNcpC/BCg0F8tnMhF1QOQ98cRUM8eeI9h+S6g/5UmO4hBKMOP3vg/u7kI0u
//jrCN1RXpsrTbWaqry/xTDgTM8HkKkNhRSyDJWJVye0mPgbvVnx76en+K6LLzDaQH
//8yKI/dbswSq65XFcIwIDAQAB
//-----END PUBLIC KEY-----';
    public function __construct ()
    {
        
    }
    public function __destruct ()
    {}
    public static function instance ()
    {
        if (self::$_instance == null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    public function sign ($sourcestr = NULL,$privatekey=self::private_key)
    {
        return (parent::sign($sourcestr, $privatekey));
    }
    public function verify ($sourcestr = NULL, $signature = NULL,$publickey=self::public_key)
    {
        return parent::verify($sourcestr,  $signature, $publickey);
    }
}
?>
