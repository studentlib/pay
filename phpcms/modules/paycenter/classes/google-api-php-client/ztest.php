
<?php
include_once('./src/Google_Client.php');
include_once('./src/contrib/Google_AndroidpublisherService.php');

$ANDROIDUsertoken='{"orderId":"GPA.3387-1440-2995-87391","packageName":"com.gamecaff.tkhero","productId":"com.gamecaff.tkhero.120","purchaseTime":1514187813864,"purchaseState":0,"developerPayload":"adc054cc7bc3bcdad9bef75eaf53ce69aa80758e","purchaseToken":"afgcacoecmgcdcjdjnnncacf.AO-J1OyrxsZhwomvexw4tPWm6EI2HIL6QHDtMNDmVq0UYM0Oewc8ctco0gywaifrfrpFmbzdy-_twH37eP_SFi7XaXAuEHJebkz9BVqo35Akkth5iHpCZoWVt0uhsFUNYAvGXrmLBCFI"}';
$user_token= json_decode($ANDROIDUsertoken,true);

// https://developers.google.com/console/help/#service_accounts
$CLIENT_ID = 'txyxjp001@api-7062615535232458999-640844.iam.gserviceaccount.com';

$SERVICE_ACCOUNT_NAME = 'txyxjp001@api-7062615535232458999-640844.iam.gserviceaccount.com';
$KEY_FILE = './key.p12';

$client = new Google_Client();
$client->setApplicationName($user_token['packageName']);
$client->setClientId($CLIENT_ID);

$key = file_get_contents($KEY_FILE);
$auth = new Google_AssertionCredentials(
    $SERVICE_ACCOUNT_NAME,
    array('https://www.googleapis.com/auth/androidpublisher'),
    $key);

$client->setAssertionCredentials($auth);

$AndroidPublisherService = new Google_AndroidPublisherService($client);
$res = $AndroidPublisherService->inapppurchases->get($user_token['packageName'], $user_token['productId'], $user_token['purchaseToken']);
var_dump($res);
// var_dump($res['purchaseState']); 
 //$data = json_decode($res,true);
 if(0 == $res['purchaseState'] && (1 == $res['consumptionState'])){
   print_r(" check success \n");
   return true;
 }

?>


